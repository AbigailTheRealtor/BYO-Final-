<?php

namespace App\Services\Spatial\OvertureV2Import;

use Illuminate\Database\ConnectionInterface;

/**
 * Writes a validated {@see OvertureV2ImportPlan} into the v2 spatial tables — and nothing else.
 * It touches only `overture_v2_corpora`, `overture_v2_places` and `overture_v2_chain_memberships`;
 * v1's `places` / `corpus_imports` are never read or written, and there is no activation code:
 * the best state an import can reach is `ready`.
 *
 * THREE STEPS, AND ONLY THE MIDDLE ONE WRITES DATA:
 *   1. Mark the corpus `preparing` under a fresh RUN TOKEN (its own short statement, so an
 *      interrupted import is visible).
 *   2. ONE transaction: lock the corpus row, require it still `preparing` under THIS run's token,
 *      with this plan's checksums and no rows yet; insert every place and membership; re-count
 *      everything from the database against the plan; then flip to `ready`, recording the
 *      DATABASE's counts — which the table's own CHECK refuses unless each equals its expected
 *      count. Any failure rolls ALL of it back.
 *   3. On failure, mark the corpus `failed` with the reason — only while it is still this run's
 *      `preparing` row, so a failing run can never stamp its failure on a later run's attempt. It
 *      holds no rows (step 2 rolled back) and can never read as ready.
 *
 * RE-RUNS: same version + same checksums + `ready` → an explicit no-op (nothing written). Same
 * version + ANY different checksum or rule hash + `ready` → hard failure; a ready corpus is never
 * overwritten. `preparing` / `failed` → retried from zero (step 2 proves no rows exist first).
 */
final class OvertureV2CorpusImporter
{
    public const IMPORTED = 'imported';
    public const ALREADY_READY = 'already_ready';

    public const TABLES = ['overture_v2_corpora', 'overture_v2_places', 'overture_v2_chain_memberships'];

    private const PLACE_BATCH = 500;
    private const MEMBERSHIP_BATCH = 1000;

    private const PLACE_COLUMNS = [
        'corpus_version', 'source', 'source_ref', 'source_release', 'extract_recipe_version',
        'taxonomy_map_version', 'lane', 'supplementary_role', 'materialization_policy', 'rescue_verdict',
        'rescued_lane', 'rescued_chain', 'rescued_as_category', 'rescued_format', 'source_category',
        'category_key', 'legacy_category', 'basic_category', 'name', 'brand_name', 'brand_wikidata',
        'confidence', 'operating_status', 'operating_status_known', 'geom', 'address_freeform',
        'address_locality', 'address_postcode', 'address_region', 'address_country', 'eligibility',
    ];

    /** @return string {@see IMPORTED} or {@see ALREADY_READY} */
    public function import(ConnectionInterface $db, OvertureV2ImportPlan $plan): string
    {
        $this->assertSchema($db);
        $version = $plan->corpusVersion();

        $existing = $db->selectOne('SELECT * FROM overture_v2_corpora WHERE corpus_version = ?', [$version]);
        if ($existing !== null && $existing->status === 'ready') {
            if ($this->sameSource($existing, $plan)) {
                return self::ALREADY_READY;
            }
            throw new InvalidOvertureV2Import("corpus {$version} is already ready with DIFFERENT checksums or registry; a ready corpus is never overwritten");
        }

        $run = bin2hex(random_bytes(16));
        try {
            $this->markPreparing($db, $plan, $run);
        } catch (InvalidOvertureV2Import $e) {
            throw $e;
        } catch (\Throwable $e) {
            // Nothing was written; report the class and a bounded message, never the SQL bindings.
            throw new InvalidOvertureV2Import("corpus {$version} could not be marked preparing: " . $this->reason($e), 0, $e);
        }

        try {
            $db->transaction(function () use ($db, $plan, $version, $run): void {
                $this->importRows($db, $plan, $version, $run);
            });
        } catch (\Throwable $e) {
            $this->markFailed($db, $version, $run, $e);
            if ($e instanceof InvalidOvertureV2Import) {
                throw $e;
            }
            throw new InvalidOvertureV2Import("import of {$version} rolled back: " . $this->reason($e), 0, $e);
        }

        return self::IMPORTED;
    }

    private function assertSchema(ConnectionInterface $db): void
    {
        if ($db->getDriverName() !== 'pgsql') {
            throw new InvalidOvertureV2Import('the v2 corpus lives in PostGIS; this connection is not pgsql');
        }
        if ($db->selectOne("SELECT count(*) AS n FROM pg_extension WHERE extname = 'postgis'")->n < 1) {
            throw new InvalidOvertureV2Import('the connection is PostgreSQL without PostGIS; the v2 corpus needs geography columns');
        }
        foreach (self::TABLES as $table) {
            if ($db->selectOne('SELECT to_regclass(?) AS t', [$table])->t === null) {
                throw new InvalidOvertureV2Import("table {$table} does not exist; run the spatial migrations first");
            }
        }
    }

    private function sameSource(object $row, OvertureV2ImportPlan $plan): bool
    {
        $c = $plan->contract;

        return $row->manifest_sha256 === $plan->manifestSha256
            && $row->base_sha256 === $plan->baseSha256
            && $row->supplementary_sha256 === $plan->supplementarySha256
            && $row->registry_rule_hash === $c->registryRuleHash
            && $row->source_release === $c->sourceRelease
            && $row->extract_recipe_version === $c->extractRecipeVersion
            && $row->taxonomy_map_version === $c->taxonomyMapVersion;
    }

    private function markPreparing(ConnectionInterface $db, OvertureV2ImportPlan $plan, string $run): void
    {
        $c = $plan->contract;
        $values = [
            'corpus_version' => $c->corpusVersion,
            'status' => 'preparing',
            'import_run' => $run,
            'source_release' => $c->sourceRelease,
            'extract_recipe_version' => $c->extractRecipeVersion,
            'taxonomy_map_version' => $c->taxonomyMapVersion,
            'registry_version' => $c->registryVersion,
            'registry_rule_hash' => $c->registryRuleHash,
            'registry_match_precedence_version' => $plan->registryMatchPrecedenceVersion,
            'registry_normalizer_version' => $plan->registryNormalizerVersion,
            'manifest_sha256' => $plan->manifestSha256,
            'base_sha256' => $plan->baseSha256,
            'supplementary_sha256' => $plan->supplementarySha256,
            'input_file_sha256' => $plan->manifest['source']['input_file_sha256'] ?? null,
            'base_rows' => $plan->baseRows,
            'supplementary_rows' => $plan->supplementaryRows,
            'matcher_analysis_rows' => $plan->matcherAnalysisRows(),
            'diagnostic_rows' => $plan->diagnosticRows,
            'rescue_admitted_rows' => $plan->rescueAdmittedRows,
            'rescue_refused_rows' => $plan->rescueRefusedRows,
            'expected_memberships' => $plan->membershipCount,
            'manifest' => json_encode($plan->manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION),
        ];
        $cols = array_keys($values);
        $set = implode(', ', array_map(static fn (string $col) => "{$col} = EXCLUDED.{$col}", array_slice($cols, 1)));

        $affected = $db->affectingStatement(
            'INSERT INTO overture_v2_corpora (' . implode(', ', $cols) . ', started_at, finished_at, failure_reason, '
            . 'imported_base_rows, imported_rescued_rows, imported_memberships) VALUES ('
            . implode(', ', array_fill(0, count($cols), '?')) . ', now(), NULL, NULL, NULL, NULL, NULL) '
            . "ON CONFLICT (corpus_version) DO UPDATE SET {$set}, started_at = now(), finished_at = NULL, "
            . 'failure_reason = NULL, imported_base_rows = NULL, imported_rescued_rows = NULL, imported_memberships = NULL '
            . "WHERE overture_v2_corpora.status <> 'ready'",
            array_values($values),
        );
        if ($affected !== 1) {
            throw new InvalidOvertureV2Import("corpus {$c->corpusVersion} became ready concurrently; nothing written");
        }
    }

    private function importRows(ConnectionInterface $db, OvertureV2ImportPlan $plan, string $version, string $run): void
    {
        $locked = $db->selectOne('SELECT status, import_run, manifest_sha256, base_sha256, supplementary_sha256 FROM overture_v2_corpora WHERE corpus_version = ? FOR UPDATE', [$version]);
        if ($locked === null || $locked->status !== 'preparing' || $locked->import_run !== $run || $locked->manifest_sha256 !== $plan->manifestSha256
            || $locked->base_sha256 !== $plan->baseSha256 || $locked->supplementary_sha256 !== $plan->supplementarySha256
        ) {
            throw new InvalidOvertureV2Import("corpus {$version} changed under this import; nothing written");
        }
        if ((int) $db->selectOne('SELECT count(*) AS n FROM overture_v2_places WHERE corpus_version = ?', [$version])->n !== 0) {
            throw new InvalidOvertureV2Import("corpus {$version} already holds place rows; refusing to add to them");
        }

        $ids = $this->insertPlaces($db, $plan, $version);
        $membershipRows = $this->insertMemberships($db, $plan, $version, $ids);
        [$base, $rescued, $memberships] = $this->verify($db, $plan, $version, $membershipRows);

        // The DATABASE's counts, not the plan's: the ledger's `ready` CHECK then compares what is
        // actually stored against what the manifest expected, independently of verify().
        $readied = $db->update(
            "UPDATE overture_v2_corpora SET status = 'ready', finished_at = now(), imported_base_rows = ?, "
            . 'imported_rescued_rows = ?, imported_memberships = ? WHERE corpus_version = ? AND import_run = ?',
            [$base, $rescued, $memberships, $version, $run],
        );
        // Rows must never commit under a corpus that did not become ready.
        if ($readied !== 1) {
            throw new InvalidOvertureV2Import("corpus {$version} could not be marked ready by this run; nothing written");
        }
    }

    /** @return array<string, int> source_ref => place id */
    private function insertPlaces(ConnectionInterface $db, OvertureV2ImportPlan $plan, string $version): array
    {
        $rowSql = '(' . implode(', ', array_map(
            static fn (string $col) => match ($col) {
                'geom' => 'ST_SetSRID(ST_MakePoint(?::double precision, ?::double precision), 4326)::geography',
                'confidence' => '?::double precision',
                'operating_status_known' => '?::boolean',
                default => '?',
            },
            self::PLACE_COLUMNS,
        )) . ')';

        $ids = [];
        foreach (array_chunk($plan->places, self::PLACE_BATCH) as $chunk) {
            $bindings = [];
            foreach ($chunk as $r) {
                array_push(
                    $bindings,
                    $version, $r['source'], $r['source_ref'], $r['source_release'], $r['extract_recipe_version'],
                    $r['taxonomy_map_version'], $r['lane'], $r['supplementary_role'], $r['materialization_policy'],
                    $r['rescue_verdict'], $r['rescued_lane'], $r['rescued_chain'], $r['rescued_as_category'],
                    $r['rescued_format'], $r['source_category'], $r['category_key'], $r['legacy_category'],
                    $r['basic_category'], $r['name'], $r['brand_name'], $r['brand_wikidata'],
                    self::float($r['confidence']), $r['operating_status'], $r['operating_status_known'] ? 'true' : 'false',
                    self::float($r['lon']), self::float($r['lat']),
                    $r['address']['freeform'], $r['address']['locality'], $r['address']['postcode'],
                    $r['address']['region'], $r['address']['country'], $r['eligibility'],
                );
            }
            $returned = $db->select(
                'INSERT INTO overture_v2_places (' . implode(', ', self::PLACE_COLUMNS) . ') VALUES '
                . implode(', ', array_fill(0, count($chunk), $rowSql)) . ' RETURNING id, source_ref',
                $bindings,
            );
            foreach ($returned as $row) {
                $ids[$row->source_ref] = (int) $row->id;
            }
        }
        if (count($ids) !== count($plan->places)) {
            throw new InvalidOvertureV2Import('the database returned ' . count($ids) . ' place ids for ' . count($plan->places) . ' rows');
        }

        return $ids;
    }

    /** @param array<string, int> $ids */
    private function insertMemberships(ConnectionInterface $db, OvertureV2ImportPlan $plan, string $version, array $ids): int
    {
        $c = $plan->contract;
        $rows = [];
        foreach ($plan->memberships as $sourceRef => $memberships) {
            if (! isset($ids[$sourceRef])) {
                throw new InvalidOvertureV2Import("membership for {$sourceRef}, which was not imported");
            }
            foreach ($memberships as $m) {
                $rows[] = [
                    $version, $ids[$sourceRef], $m['brand_key'], $m['role'], $m['format_key'], $m['match_method'],
                    $m['storefront_status'], json_encode(array_values($m['co_brand_with']), JSON_THROW_ON_ERROR),
                    $m['rescued_from_source_category'], $c->registryVersion, $c->registryRuleHash,
                ];
            }
        }
        $rowSql = '(?, ?, ?, ?, ?, ?, ?, ARRAY(SELECT json_array_elements_text(?::json))::text[], ?, ?, ?)';
        foreach (array_chunk($rows, self::MEMBERSHIP_BATCH) as $chunk) {
            $db->insert(
                'INSERT INTO overture_v2_chain_memberships (corpus_version, place_id, brand_key, role, format_key, '
                . 'match_method, storefront_status, co_brand_with, rescued_from_source_category, registry_version, '
                . 'registry_rule_hash) VALUES ' . implode(', ', array_fill(0, count($chunk), $rowSql)),
                array_merge(...$chunk),
            );
        }

        return count($rows);
    }

    /**
     * Re-counts from the database, inside the transaction, against the plan.
     *
     * @return array{0: int, 1: int, 2: int} the stored base places, rescued places and memberships
     */
    private function verify(ConnectionInterface $db, OvertureV2ImportPlan $plan, string $version, int $membershipRows): array
    {
        $counts = $db->selectOne(
            "SELECT count(*) FILTER (WHERE lane = 'base' AND materialization_policy = 'corpus') AS base, "
            . "count(*) FILTER (WHERE lane = 'supplementary' AND materialization_policy = 'rescued') AS rescued, "
            . 'count(*) AS total FROM overture_v2_places WHERE corpus_version = ?',
            [$version],
        );
        if ((int) $counts->base !== $plan->baseRows || (int) $counts->rescued !== $plan->rescueAdmittedRows
            || (int) $counts->total !== $plan->baseRows + $plan->rescueAdmittedRows
        ) {
            throw new InvalidOvertureV2Import('imported place counts do not reconcile with the plan');
        }

        $byCategory = [];
        foreach ($db->select('SELECT category_key, count(*) AS n FROM overture_v2_places WHERE corpus_version = ? AND lane = \'base\' GROUP BY category_key', [$version]) as $row) {
            $byCategory[$row->category_key] = (int) $row->n;
        }
        ksort($byCategory, SORT_STRING);
        if ($byCategory !== $plan->baseByCategory) {
            throw new InvalidOvertureV2Import('imported per-category counts do not reconcile with the plan');
        }

        $memberships = (int) $db->selectOne('SELECT count(*) AS n FROM overture_v2_chain_memberships WHERE corpus_version = ?', [$version])->n;
        if ($memberships !== $membershipRows || $memberships !== $plan->membershipCount) {
            throw new InvalidOvertureV2Import('imported membership count does not reconcile with the plan');
        }

        return [(int) $counts->base, (int) $counts->rescued, $memberships];
    }

    private function markFailed(ConnectionInterface $db, string $version, string $run, \Throwable $e): void
    {
        try {
            // Scoped to THIS run: if another importer has since re-armed the row, it is theirs.
            $db->update(
                "UPDATE overture_v2_corpora SET status = 'failed', finished_at = now(), failure_reason = ? "
                . "WHERE corpus_version = ? AND status = 'preparing' AND import_run = ?",
                [$this->reason($e), $version, $run],
            );
        } catch (\Throwable) {
            // The corpus stays `preparing` — still never `ready` — and the original failure is reported.
        }
    }

    /** Exception class and a bounded message; never bound values or connection details. */
    private function reason(\Throwable $e): string
    {
        $message = preg_replace('/\(SQL: .*$/s', '', $e->getMessage()) ?? '';

        return mb_substr(get_class($e) . ': ' . trim($message), 0, 500);
    }

    private static function float(int|float $v): string
    {
        return var_export((float) $v, true);
    }
}
