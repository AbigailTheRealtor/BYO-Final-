<?php

namespace App\Services\Spatial\Gate2;

use InvalidArgumentException;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C3d-b (Gate 2 Florida pilot, Class-2).
 *
 * CoverageQueryCatalog — the productionised, parameterised form of the AUTHORED-NOT-RUN
 * `spikes/…c3d-a/sql/coverage_matrix.sql`. It maps each MEASURABLE (dataset × category × territory)
 * tuple to a single READ-ONLY `count(*)` query against the loaded pgsql_spatial corpus, and produces
 * the ordered binding list for it.
 *
 * WHAT IT DOES — AND DELIBERATELY DOES NOT DO
 * -------------------------------------------
 *   • Produces ONLY `SELECT count(*)` queries. No INSERT/UPDATE/DELETE/DROP/TRUNCATE/ALTER/CREATE —
 *     ever. It reads the corpus; it never touches it (writes are TerritoryCoverageLedgerWriter alone).
 *   • Computes NO coverage numerator, denominator, ratio, percentage, threshold, or pass/fail. It
 *     emits raw COUNTs; judgement is the product owner's (SSOT — no Gate 2 formula).
 *   • Emits a query ONLY for a tuple that is genuinely measurable: the dataset has a `places` measure
 *     binding AND the territory resolves to a state FIPS. Everything else (a declared-unmeasured
 *     dataset such as an E-32 PR watch dataset; the rural_CONUS territory with no FIPS) yields NO
 *     query — so the assembler renders it `unmeasured`, never a fabricated zero (D-C3d-a-2).
 *
 * FLORIDA-ONLY (pilot)
 * --------------------
 * `queriesFor(['FL'])` binds state FIPS '12'. A place has no state_fips column (SSOT §7.2); it is
 * attributed to FL by spatial containment within a `place_territory_boundary_kind` boundary whose
 * `attrs->>'state_fips'` is '12'. PR ('72') / AK ('02') are bindable for a later approved run but the
 * pilot command requests FL alone; rural_CONUS is never bindable here (no FIPS — a product decision).
 *
 * Pure and deterministic — no DB handle, no PostGIS, no network, no secret, no clock. It builds SQL
 * strings and bindings; executing them is {@see CorpusCoverageObservationSource}'s job.
 *
 * @see \App\Services\Spatial\Gate2\CorpusCoverageObservationSource
 * @see \App\Services\Spatial\Gate2\Gate2CoverageAssembler
 * @see \Tests\Unit\Spatial\Gate2\CoverageQueryCatalogTest
 */
final class CoverageQueryCatalog
{
    public const STRATEGY_PLACES = 'places';

    /** @var array<string,array{categories:list<string>,measure:array<string,mixed>|null}> */
    private readonly array $datasets;

    /** @var array<string,string> territory => state FIPS */
    private readonly array $stateFips;

    /**
     * @param array<string,mixed> $datasets  the config('spatial_gate2_corpus.datasets') registry
     * @param array<string,string> $stateFips territory => state FIPS (rural_CONUS deliberately absent)
     * @param string $placeTerritoryBoundaryKind boundary `kind` used to attribute a place to a territory
     */
    public function __construct(
        array $datasets,
        array $stateFips,
        private readonly string $placeTerritoryBoundaryKind = 'county',
    ) {
        $normalized = [];
        foreach ($datasets as $key => $spec) {
            $key = (string) $key;
            if ($key === '') {
                throw new InvalidArgumentException('CoverageQueryCatalog: a dataset key must be non-empty.');
            }
            $categories = array_values(array_map('strval', (array) ($spec['categories'] ?? [])));
            if ($categories === []) {
                throw new InvalidArgumentException("CoverageQueryCatalog: dataset '{$key}' declares no categories.");
            }
            $measure = $spec['measure'] ?? null;
            $normalized[$key] = [
                'categories' => $categories,
                'measure'    => is_array($measure) ? $measure : null,
            ];
        }

        $fips = [];
        foreach ($stateFips as $territory => $code) {
            $fips[(string) $territory] = (string) $code;
        }

        $this->datasets  = $normalized;
        $this->stateFips = $fips;

        if (trim($this->placeTerritoryBoundaryKind) === '') {
            throw new InvalidArgumentException('CoverageQueryCatalog: place_territory_boundary_kind must be non-empty.');
        }
    }

    /**
     * Every declared dataset key, measurable or not, in declared order. Callers use this to declare
     * the full matrix dataset axis; the assembler renders unmeasured cells for the non-measurable ones.
     *
     * @return list<string>
     */
    public function datasetKeys(): array
    {
        return array_keys($this->datasets);
    }

    /**
     * Dataset keys that carry a supported measure binding (currently the `places` strategy).
     *
     * @return list<string>
     */
    public function measurableDatasetKeys(): array
    {
        $out = [];
        foreach ($this->datasets as $key => $spec) {
            if ($this->isSupportedMeasure($spec['measure'])) {
                $out[] = $key;
            }
        }

        return $out;
    }

    /**
     * The read-only COUNT queries for every measurable (dataset × category × territory) tuple in the
     * requested territories. A territory with no state FIPS (e.g. rural_CONUS) contributes nothing.
     *
     * @param  list<string> $territories territories to measure (the pilot passes ['FL'])
     * @return list<array{dataset:string,category:string,territory:string,sql:string,bindings:list<string>}>
     */
    public function queriesFor(array $territories): array
    {
        $out = [];
        foreach ($this->datasets as $dataset => $spec) {
            $measure = $spec['measure'];
            if (! $this->isSupportedMeasure($measure)) {
                continue; // declared-unmeasured (e.g. PR watch dataset) → no query, stays unmeasured.
            }
            foreach ($territories as $territory) {
                $territory = (string) $territory;
                $fips = $this->stateFips[$territory] ?? null;
                if ($fips === null) {
                    continue; // no FIPS (rural_CONUS / unknown) → not measurable, never a fake zero.
                }
                foreach ($spec['categories'] as $category) {
                    $out[] = $this->buildPlacesQuery($dataset, $category, $territory, $fips, $measure);
                }
            }
        }

        return $out;
    }

    /**
     * @param array<string,mixed>|null $measure
     */
    private function isSupportedMeasure(?array $measure): bool
    {
        return is_array($measure) && ($measure['strategy'] ?? null) === self::STRATEGY_PLACES;
    }

    /**
     * Owned-places count for one (category × territory): places of `category_key` whose geom is
     * spatially covered by a territory boundary (kind = place_territory_boundary_kind, state FIPS
     * bound). Read-only; two bound parameters keep the FIPS/category out of the SQL text.
     *
     * @param  array<string,mixed> $measure
     * @return array{dataset:string,category:string,territory:string,sql:string,bindings:list<string>}
     */
    private function buildPlacesQuery(string $dataset, string $category, string $territory, string $fips, array $measure): array
    {
        $table   = $this->identifier((string) ($measure['table'] ?? 'places'));
        $catCol  = $this->identifier((string) ($measure['category_column'] ?? 'category_key'));
        $geomCol = $this->identifier((string) ($measure['geom_column'] ?? 'geom'));
        $kind    = $this->placeTerritoryBoundaryKind; // bound, not interpolated.

        $sql = "SELECT count(*) AS present_count\n"
            . "FROM {$table} p\n"
            . "WHERE p.{$catCol} = ?\n"
            . "  AND EXISTS (\n"
            . "    SELECT 1 FROM boundaries b\n"
            . "    WHERE b.kind = ?\n"
            . "      AND b.attrs->>'state_fips' = ?\n"
            . "      AND ST_Covers(b.geom, p.{$geomCol})\n"
            . "  )";

        return [
            'dataset'   => $dataset,
            'category'  => $category,
            'territory' => $territory,
            'sql'       => $sql,
            'bindings'  => [$category, $kind, $fips],
        ];
    }

    /**
     * Whitelist a table/column identifier to a strict `[a-z0-9_]` token. The catalog only ever emits
     * identifiers from committed config, but this is defence-in-depth against a hand-edited registry
     * smuggling anything into the SQL text (all VALUES are already bound parameters).
     */
    private function identifier(string $name): string
    {
        if (! preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("CoverageQueryCatalog: unsafe identifier '{$name}'.");
        }

        return $name;
    }
}
