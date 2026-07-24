<?php

namespace Tests\Feature\Spatial;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Batch 2C — the offline import command: refuses production, runs against the
 * committed normalized fixture with NO DuckDB and NO PostGIS, authors the load
 * artifacts, and leaves the default (SQLite) database untouched. Nothing is
 * executed against a cluster.
 */
class CorpusImportOvertureCommandTest extends TestCase
{
    private string $fixture;
    private string $outDir;
    private string $partition = 'places_p_overture_2026_06_17_0_pinellas';

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = base_path('tests/fixtures/spatial/overture/pinellas_normalized_places.ndjson');
        $this->outDir = sys_get_temp_dir() . '/b2c_import_' . getmypid();
        $this->rrmdir($this->outDir);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->outDir);
        parent::tearDown();
    }

    /** @test */
    public function it_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('corpus:import-overture', [
            '--input' => $this->fixture,
            '--out-dir' => $this->outDir,
        ])->assertExitCode(1);

        $this->assertFileDoesNotExist($this->outDir . '/copy_payload.txt', 'production run must author nothing');
    }

    /** @test */
    public function it_authors_the_full_import_plan_offline(): void
    {
        // SPATIAL_* are unset in the test env; success proves no cluster is touched.
        $this->artisan('corpus:import-overture', [
            '--region' => 'pinellas',
            '--input' => $this->fixture,
            '--out-dir' => $this->outDir,
        ])->assertExitCode(0);

        // 1) COPY payload — one line per fixture row, EWKT geography present.
        $payload = $this->outDir . '/copy_payload.txt';
        $this->assertFileExists($payload);
        $lines = array_values(array_filter(explode("\n", file_get_contents($payload)), 'strlen'));
        $this->assertCount(8, $lines, '8 normalized fixture rows → 8 COPY rows');
        foreach ($lines as $line) {
            $this->assertStringContainsString('SRID=4326;POINT', $line);
            $this->assertStringStartsWith('overture-2026-06-17.0-pinellas', $line, 'corpus_version leads each row');
        }

        // 2) Staging DDL — create staging + COPY ONLY (no CHECK, no ATTACH).
        $ddl = file_get_contents($this->outDir . '/partition_load.sql');
        $this->assertStringContainsString($this->partition, $ddl);
        $this->assertStringContainsString('LIKE places', $ddl);
        $this->assertStringContainsString('\copy', $ddl, 'staging DDL must include the COPY load');
        $this->assertStringNotContainsString('ATTACH PARTITION', $ddl, 'attach belongs only to activate.sql (M1)');
        $this->assertStringNotContainsString('ADD CONSTRAINT', $ddl, 'CHECK belongs only to activate.sql (M1)');

        // 3) Ledger — a single staging provenance row.
        $ledger = json_decode(file_get_contents($this->outDir . '/ledger.json'), true);
        $this->assertSame('staging', $ledger['row']['status']);
        $this->assertSame('overture-2026-06-17.0-pinellas', $ledger['row']['corpus_version']);
        $this->assertSame(8, $ledger['row']['row_count']);
        $this->assertSame(8 * 450, $ledger['row']['bytes']);
        $this->assertStringContainsString('INSERT INTO corpus_imports', $ledger['insert_sql']);

        // 4) Activation plan.
        $activate = file_get_contents($this->outDir . '/activate.sql');
        $this->assertStringContainsString('ATTACH PARTITION', $activate);
        $this->assertStringContainsString("status = 'active'", $activate);
    }

    /**
     * @test End-to-end from the FLAT extractor shape: extract-overture normalizes
     * the flat fixture directly (no adapter), and import-overture then authors a
     * clean import plan — all acceptance gates pass, accepted == kept == payload
     * rows, and every canonical category survives into the payload.
     */
    public function the_flat_extract_normalizes_and_authors_a_clean_import_plan(): void
    {
        $flatFixture = base_path('tests/fixtures/spatial/overture/pinellas_raw_flat_places.ndjson');
        $normalized = $this->outDir . '/flat_normalized.ndjson';
        @mkdir($this->outDir, 0777, true);

        // 1) Normalize the flat extractor output directly — no adapter step.
        $this->artisan('corpus:extract-overture', [
            '--region' => 'pinellas',
            '--input' => $flatFixture,
            '--output' => $normalized,
        ])->assertExitCode(0);
        $this->assertFileExists($normalized);

        $keptRows = array_values(array_filter(explode("\n", file_get_contents($normalized)), 'strlen'));
        $this->assertCount(9, $keptRows, 'flat fixture normalizes to 9 kept rows');

        // 2) Author the import plan from that normalized extract. Exit 0 means all
        //    seven acceptance gates passed (non_empty, source_uniform,
        //    identity_present, identity_unique, category_registered,
        //    confidence_floor, coordinates_valid) — the command aborts non-zero
        //    on any violation.
        $this->artisan('corpus:import-overture', [
            '--region' => 'pinellas',
            '--input' => $normalized,
            '--out-dir' => $this->outDir,
        ])->assertExitCode(0);

        // 3) Accepted count == normalized kept count == payload row count; 0 rejected.
        $payload = $this->outDir . '/copy_payload.txt';
        $this->assertFileExists($payload);
        $payloadLines = array_values(array_filter(explode("\n", file_get_contents($payload)), 'strlen'));
        $this->assertCount(9, $payloadLines, 'accepted count == 9 kept rows (0 rejected)');

        // 4) All seven canonical categories appear in the payload (col 7 = category_key).
        $categories = [];
        foreach ($payloadLines as $line) {
            $cols = explode("\t", $line);
            $categories[$cols[6]] = true;
        }
        $this->assertEqualsCanonicalizing(
            ['coffee_shop', 'gas_station', 'grocery_store', 'gym', 'pharmacy', 'restaurant', 'shopping_center'],
            array_keys($categories),
            'all seven canonical categories represented in the payload'
        );

        // 5) corpus_version + partition naming remain correct.
        foreach ($payloadLines as $line) {
            $this->assertStringStartsWith('overture-2026-06-17.0-pinellas', $line, 'corpus_version leads each row');
        }
        $ledger = json_decode(file_get_contents($this->outDir . '/ledger.json'), true);
        $this->assertSame('overture-2026-06-17.0-pinellas', $ledger['row']['corpus_version']);
        $this->assertSame(9, $ledger['row']['row_count']);
        $this->assertStringContainsString(
            $this->partition,
            file_get_contents($this->outDir . '/partition_load.sql'),
            'partition name remains places_p_overture_2026_06_17_0_pinellas'
        );
    }

    /** @test */
    public function attach_partition_appears_in_exactly_one_generated_artifact(): void
    {
        // M1: the staging artifact and the activation artifact must not both
        // attach — a double ATTACH would fail on the second run. The split must
        // mirror the spike recipes (create_partition/load_copy vs attach_activate).
        $this->artisan('corpus:import-overture', [
            '--input' => $this->fixture,
            '--out-dir' => $this->outDir,
        ])->assertExitCode(0);

        $generatedSql = ['partition_load.sql', 'activate.sql'];
        $withAttach = [];
        $withCheck = [];
        foreach ($generatedSql as $file) {
            $sql = file_get_contents($this->outDir . '/' . $file);
            if (str_contains($sql, 'ATTACH PARTITION')) {
                $withAttach[] = $file;
            }
            if (str_contains($sql, 'ADD CONSTRAINT')) {
                $withCheck[] = $file;
            }
        }

        $this->assertSame(['activate.sql'], $withAttach, 'ATTACH PARTITION must appear in exactly one artifact — activate.sql');
        $this->assertSame(['activate.sql'], $withCheck, 'the CHECK constraint must appear only in activate.sql');

        // partition_load.sql owns create + copy only (spike split).
        $staging = file_get_contents($this->outDir . '/partition_load.sql');
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS', $staging);
        $this->assertStringContainsString('\copy', $staging);

        // activate.sql owns CHECK + ATTACH + ledger activation.
        $activate = file_get_contents($this->outDir . '/activate.sql');
        $this->assertStringContainsString('ADD CONSTRAINT', $activate);
        $this->assertStringContainsString('ATTACH PARTITION', $activate);
        $this->assertStringContainsString("status = 'active'", $activate);
    }

    /** @test */
    public function it_rejects_an_unknown_region(): void
    {
        $this->artisan('corpus:import-overture', [
            '--region' => 'atlantis',
            '--input' => $this->fixture,
            '--out-dir' => $this->outDir,
        ])->assertExitCode(1);
    }

    /** @test */
    public function it_errors_when_the_extract_is_missing(): void
    {
        $this->artisan('corpus:import-overture', [
            '--input' => $this->outDir . '/does-not-exist.ndjson',
            '--out-dir' => $this->outDir,
        ])->assertExitCode(1);
    }

    /** @test */
    public function running_it_creates_no_spatial_tables_under_sqlite(): void
    {
        $this->assertSame('sqlite', Schema::getConnection()->getDriverName());

        $this->artisan('corpus:import-overture', [
            '--input' => $this->fixture,
            '--out-dir' => $this->outDir,
        ])->assertExitCode(0);

        foreach (['places', 'corpus_imports', 'place_categories'] as $table) {
            $this->assertFalse(Schema::hasTable($table),
                "offline import must not create the PostGIS table [{$table}]");
        }
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
