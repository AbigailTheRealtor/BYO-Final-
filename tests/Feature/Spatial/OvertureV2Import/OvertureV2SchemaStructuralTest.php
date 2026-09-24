<?php

namespace Tests\Feature\Spatial\OvertureV2Import;

use Tests\TestCase;

/**
 * Source-level guards for the Overture v2 schema and importer — what CI can prove without a
 * PostGIS. The same properties are exercised for real, against a scratch PostGIS, by
 * OvertureV2PostgisIntegrationTest (local validation; skipped where no scratch server is named).
 */
class OvertureV2SchemaStructuralTest extends TestCase
{
    private const MIGRATIONS = [
        '2026_09_24_000001_spatial_overture_v2_create_corpora.php',
        '2026_09_24_000002_spatial_overture_v2_create_places.php',
        '2026_09_24_000003_spatial_overture_v2_create_chain_memberships.php',
    ];

    /** Every v1 spatial table. v2 may not create, alter, reference, read or write any of them. */
    private const V1_TABLES = [
        'place_categories', 'place_category_mappings', 'places', 'place_authority_links',
        'boundaries', 'boundaries_parts', 'listing_locations', 'addresses', 'isochrone_cache',
        'corpus_imports',
    ];

    /** The import path: everything this change adds that runs. */
    private const IMPORT_SOURCES = [
        'app/Services/Spatial/OvertureV2Import/InvalidOvertureV2Import.php',
        'app/Services/Spatial/OvertureV2Import/OvertureV2CorpusImporter.php',
        'app/Services/Spatial/OvertureV2Import/OvertureV2ImportContract.php',
        'app/Services/Spatial/OvertureV2Import/OvertureV2ImportGate.php',
        'app/Services/Spatial/OvertureV2Import/OvertureV2ImportPlan.php',
        'app/Console/Commands/CorpusImportOvertureV2.php',
        'config/overture_v2_corpus.php',
    ];

    private function sql(string $migration): string
    {
        return (string) file_get_contents(base_path('database/migrations/spatial/' . $migration));
    }

    /** Source with comments removed, so prose explaining a prohibition cannot trip it. */
    private function code(string $path): string
    {
        $out = '';
        foreach (token_get_all((string) file_get_contents(base_path($path))) as $t) {
            if (! is_array($t) || ! in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $out .= is_array($t) ? $t[1] : $t;
            }
        }

        return $out;
    }

    /** Table names a migration or the importer names in DDL/DML. */
    private function tablesNamedIn(string $code): array
    {
        preg_match_all('/\b(?:TABLE(?: IF (?:NOT )?EXISTS)?|INTO|FROM|UPDATE|REFERENCES|JOIN|ON)\s+([a-z_][a-z0-9_]*)/i', $code, $m);

        // `ON CONFLICT`, `ON DELETE`, `DO UPDATE SET`: keywords after ON/UPDATE, not tables.
        $keywords = ['conflict', 'delete', 'set', 'update'];

        return array_values(array_diff(array_unique(array_map('strtolower', $m[1])), $keywords));
    }

    public function test_the_three_migrations_are_ordered_parent_first(): void
    {
        $names = array_map('basename', glob(base_path('database/migrations/spatial') . '/*_spatial_overture_v2_*.php'));
        sort($names);
        $this->assertSame(self::MIGRATIONS, $names);
    }

    public function test_no_status_can_say_a_corpus_is_active(): void
    {
        $sql = $this->sql(self::MIGRATIONS[0]);

        $this->assertStringContainsString("CHECK (status IN ('preparing', 'ready', 'failed'))", $sql);
        $this->assertDoesNotMatchRegularExpression("/'active'/", $this->code('database/migrations/spatial/' . self::MIGRATIONS[0]));
    }

    public function test_ready_and_the_lane_contract_treat_unknown_as_a_violation(): void
    {
        // A CHECK passes on NULL. Both of these compare nullable columns, so both must be
        // COALESCE(..., false) or a NULL count / role would satisfy them.
        $this->assertMatchesRegularExpression('/overture_v2_corpora_ready CHECK \(COALESCE\(.*?,\s*false\s*\)\)/s', $this->sql(self::MIGRATIONS[0]));
        $this->assertMatchesRegularExpression('/overture_v2_places_lane_contract CHECK \(COALESCE\(.*?,\s*false\s*\)\)/s', $this->sql(self::MIGRATIONS[1]));
    }

    public function test_matcher_only_can_never_satisfy_the_lane_contract(): void
    {
        preg_match('/overture_v2_places_lane_contract CHECK \((.*?)\n\s*\)\n\s*SQL/s', $this->sql(self::MIGRATIONS[1]), $m);
        $contract = $m[1] ?? '';

        $this->assertNotSame('', $contract);
        $this->assertStringNotContainsString('matcher_only', $contract);
        $this->assertStringContainsString("materialization_policy = 'corpus'", $contract);
        $this->assertStringContainsString("materialization_policy = 'rescued'", $contract);
        $this->assertStringContainsString("rescue_verdict = 'admitted'", $contract);
        $this->assertStringContainsString('materialization_policy text NOT NULL', $this->sql(self::MIGRATIONS[1]));
    }

    public function test_keys_uniqueness_geometry_and_indexes(): void
    {
        $places = $this->sql(self::MIGRATIONS[1]);
        $members = $this->sql(self::MIGRATIONS[2]);

        $this->assertStringContainsString('UNIQUE (corpus_version, source_ref)', $places, 'one Overture place once per corpus — and only per corpus');
        $this->assertStringContainsString('UNIQUE (id, corpus_version)', $places);
        $this->assertStringContainsString('geom                   geography(Point,4326) NOT NULL', $places);
        $this->assertStringContainsString('USING gist (geom)', $places);
        $this->assertStringContainsString('REFERENCES overture_v2_corpora (corpus_version)', $places);
        $this->assertStringNotContainsString('ON DELETE', $places, 'a corpus with places cannot be deleted out from under them');

        $this->assertStringContainsString('FOREIGN KEY (place_id, corpus_version)', $members);
        $this->assertStringContainsString('REFERENCES overture_v2_places (id, corpus_version) ON DELETE CASCADE', $members);
        $this->assertStringContainsString('UNIQUE (place_id, brand_key)', $members, 'several chains per place, each once');
        // Every membership carries ITS corpus's registry: one corpus, one registry.
        $this->assertStringContainsString('UNIQUE (corpus_version, registry_version, registry_rule_hash)', $this->sql(self::MIGRATIONS[0]));
        $this->assertStringContainsString('FOREIGN KEY (corpus_version, registry_version, registry_rule_hash)', $members);
        $this->assertStringContainsString('REFERENCES overture_v2_corpora (corpus_version, registry_version, registry_rule_hash)', $members);
    }

    public function test_the_importer_records_database_counts_and_scopes_every_lifecycle_write_to_its_run(): void
    {
        $importer = $this->code('app/Services/Spatial/OvertureV2Import/OvertureV2CorpusImporter.php');

        // `ready` is stamped with what verify() COUNTED, so the ledger CHECK compares storage to the
        // manifest rather than the plan to itself.
        $this->assertStringContainsString('[$base, $rescued, $memberships] = $this->verify(', $importer);
        $this->assertStringContainsString('[$base, $rescued, $memberships, $version, $run]', $importer);
        // Lock, ready and failed all match this run's token.
        $this->assertStringContainsString('$locked->import_run !== $run', $importer);
        $this->assertStringContainsString("WHERE corpus_version = ? AND import_run = ?", $importer);
        $this->assertStringContainsString("AND status = 'preparing' AND import_run = ?", $importer);
        $this->assertStringContainsString('import_run                        text NOT NULL', $this->sql(self::MIGRATIONS[0]));
    }

    public function test_every_migration_drops_only_its_own_table(): void
    {
        foreach (self::MIGRATIONS as $i => $migration) {
            preg_match('/function down\(\).*?\n    \}/s', $this->sql($migration), $down);
            preg_match_all('/DROP TABLE IF EXISTS ([a-z_0-9]+)/', $down[0] ?? '', $dropped);
            $this->assertSame([['overture_v2_corpora'], ['overture_v2_places'], ['overture_v2_chain_memberships']][$i], $dropped[1], $migration);
        }
    }

    public function test_v2_migrations_never_name_a_v1_table(): void
    {
        foreach (self::MIGRATIONS as $migration) {
            $named = $this->tablesNamedIn($this->code('database/migrations/spatial/' . $migration));
            $this->assertNotEmpty($named);
            $this->assertSame([], array_values(array_intersect($named, self::V1_TABLES)), "{$migration} names a v1 table");
            foreach ($named as $table) {
                $this->assertStringStartsWith('overture_v2_', $table, "{$migration} names [{$table}]");
            }
        }
    }

    public function test_the_importer_reads_and_writes_only_v2_tables(): void
    {
        $named = $this->tablesNamedIn($this->code('app/Services/Spatial/OvertureV2Import/OvertureV2CorpusImporter.php'));

        $this->assertEqualsCanonicalizing(['overture_v2_corpora', 'overture_v2_places', 'overture_v2_chain_memberships', 'pg_extension'], $named);
    }

    public function test_the_import_path_has_no_activation_network_site_or_location_dna_reach(): void
    {
        $forbidden = [
            // activation: provider routing, the corpus pin, or any runtime config / environment write
            'location_providers', 'overture_corpus', 'OVERTURE_CORPUS', 'CorpusSurface', 'poi.default',
            'config([', 'putenv', '.env',
            // Location DNA
            'LocationDna', 'location_dna',
            // network
            'Http::', 'GuzzleHttp', 'curl_', 'http://', 'https://', 'fsockopen', 'stream_socket_client',
            // site materialization / proximity grouping / brand radius
            'ChainSiteClassifier', 'ST_DWithin', 'ST_Cluster', 'ST_Distance', '<->',
        ];
        foreach (self::IMPORT_SOURCES as $path) {
            $code = $this->code($path);
            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsStringIgnoringCase($needle, $code, "{$path} contains [{$needle}]");
            }
        }
    }

    public function test_every_ci_runnable_importer_test_is_in_the_spatial_manifest(): void
    {
        $manifest = (string) file_get_contents(base_path('tests/spatial-ci-files.txt'));
        // Needs a scratch PostGIS that CI does not provision: local validation, never listed.
        $localOnly = 'tests/Feature/Spatial/OvertureV2Import/OvertureV2PostgisIntegrationTest.php';

        $tests = glob(base_path('tests/Feature/Spatial/OvertureV2Import') . '/*Test.php');
        $this->assertGreaterThanOrEqual(5, count($tests));
        foreach ($tests as $file) {
            $rel = str_replace(base_path() . '/', '', $file);
            if ($rel === $localOnly) {
                $this->assertStringNotContainsString($rel, $manifest, 'CI has no PostGIS; listing it would only add skips');
                continue;
            }
            $this->assertStringContainsString($rel, $manifest, "{$rel} missing from tests/spatial-ci-files.txt");
        }
    }

    public function test_nothing_outside_the_import_path_reads_the_v2_tables(): void
    {
        $allowed = array_merge(self::IMPORT_SOURCES, array_map(fn ($m) => 'database/migrations/spatial/' . $m, self::MIGRATIONS));
        $readers = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('app'))) as $file) {
            $rel = str_replace(base_path() . '/', '', $file->getPathname());
            if ($file->isFile() && str_ends_with($rel, '.php') && str_contains($this->code($rel), 'overture_v2_')
                && ! in_array($rel, $allowed, true)) {
                $readers[] = $rel;
            }
        }

        $this->assertSame([], $readers, 'Location DNA, Ask AI, matching and every runtime path stay blind to v2');
    }
}
