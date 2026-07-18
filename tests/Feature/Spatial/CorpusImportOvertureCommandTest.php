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

        // 2) Partition DDL — staging + attach for this corpus_version.
        $ddl = file_get_contents($this->outDir . '/partition_load.sql');
        $this->assertStringContainsString($this->partition, $ddl);
        $this->assertStringContainsString('LIKE places', $ddl);
        $this->assertStringContainsString('ATTACH PARTITION', $ddl);

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
