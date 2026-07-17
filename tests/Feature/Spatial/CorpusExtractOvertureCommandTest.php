<?php

namespace Tests\Feature\Spatial;

use App\Services\Spatial\NormalizedExtractIo;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Batch 2A (B2/B3) — the offline extract command: refuses production, runs
 * against the committed fixture with no DuckDB and no PostGIS, and leaves the
 * default (SQLite) database untouched.
 */
class CorpusExtractOvertureCommandTest extends TestCase
{
    private string $out;

    protected function setUp(): void
    {
        parent::setUp();
        $this->out = sys_get_temp_dir() . '/b2a_cmd_' . getmypid() . '.ndjson';
        @unlink($this->out);
    }

    protected function tearDown(): void
    {
        @unlink($this->out);
        parent::tearDown();
    }

    /** @test */
    public function it_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('corpus:extract-overture', ['--output' => $this->out])
            ->assertExitCode(1);

        $this->assertFileDoesNotExist($this->out, 'production run must not write an extract');
    }

    /** @test */
    public function it_normalizes_the_committed_fixture_without_duckdb_or_postgis(): void
    {
        // SPATIAL_* are unset in the test env, so any PostGIS dependency would
        // fail — success here proves the command needs neither DuckDB nor a cluster.
        $this->artisan('corpus:extract-overture', ['--region' => 'pinellas', '--output' => $this->out])
            ->assertExitCode(0);

        $this->assertFileExists($this->out);

        $records = (new NormalizedExtractIo())->readFile($this->out);
        $this->assertCount(9, $records, 'expected 9 kept rows from the Pinellas fixture');

        $byCategory = [];
        foreach ($records as $r) {
            $byCategory[$r->category_key] = ($byCategory[$r->category_key] ?? 0) + 1;
            $this->assertSame('overture', $r->source);
            $this->assertGreaterThanOrEqual(0.90, $r->confidence);
        }

        $this->assertSame(2, $byCategory['gym'], 'gym + fitness_center collapse to gym');
        $this->assertSame(2, $byCategory['restaurant']);
        $this->assertEqualsCanonicalizing(
            ['grocery_store', 'restaurant', 'pharmacy', 'shopping_center', 'coffee_shop', 'gym', 'gas_station'],
            array_keys($byCategory)
        );
    }

    /** @test */
    public function it_rejects_an_unknown_region(): void
    {
        $this->artisan('corpus:extract-overture', ['--region' => 'atlantis', '--output' => $this->out])
            ->assertExitCode(1);
    }

    /** @test */
    public function running_it_creates_no_spatial_tables_under_sqlite(): void
    {
        $this->assertSame('sqlite', Schema::getConnection()->getDriverName());

        $this->artisan('corpus:extract-overture', ['--output' => $this->out])->assertExitCode(0);

        foreach (['place_categories', 'place_category_mappings', 'places'] as $table) {
            $this->assertFalse(Schema::hasTable($table),
                "offline extract must not create the PostGIS table [{$table}]");
        }
    }

    /** @test */
    public function the_config_pins_the_owner_decisions(): void
    {
        $cfg = config('overture_places');

        $this->assertSame('2026-06-17.0', $cfg['release'], 'pinned Overture release');
        $this->assertSame(0.90, $cfg['confidence_min']);
        $this->assertSame('ndjson', $cfg['extract_format']);
        $this->assertSame(450, $cfg['sizing']['bytes_per_row_total']);
        $this->assertSame(94, $cfg['sizing']['gist_bytes_per_row']);
        $this->assertArrayHasKey('pinellas', $cfg['regions']);
        $this->assertArrayHasKey('florida', $cfg['regions']);
        $this->assertArrayHasKey('conus', $cfg['regions']);
    }
}
