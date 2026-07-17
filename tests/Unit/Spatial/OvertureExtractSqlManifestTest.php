<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\OvertureCategoryMap;
use Tests\TestCase;

/**
 * Batch 2A (B2/B4) — the authored DuckDB SQL must not drift from the taxonomy
 * SSOT (OvertureCategoryMap) or the pinned config values. Pure file inspection;
 * no DuckDB, no network. Mirrors B1.2's FixtureCorpusPlanTest manifest check.
 */
class OvertureExtractSqlManifestTest extends TestCase
{
    private string $spikeDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->spikeDir = dirname(__DIR__, 3) . '/spikes/phase-2-batch-2a-overture-first-slice';
    }

    /** @test */
    public function the_extract_sql_lists_exactly_the_eight_source_categories(): void
    {
        $sql = $this->read('sql/extract_places.sql');
        foreach ((new OvertureCategoryMap())->sourceCategories() as $cat) {
            $this->assertStringContainsString("'{$cat}'", $sql, "extract SQL missing category {$cat}");
        }
    }

    /** @test */
    public function the_extract_sql_pins_release_and_confidence_floor(): void
    {
        $sql = $this->read('sql/extract_places.sql');
        $this->assertStringContainsString('2026-06-17.0', $sql, 'pinned release must appear');
        $this->assertStringContainsString('0.90', $sql, 'confidence floor must appear');
    }

    /** @test */
    public function the_extract_sql_reads_native_geometry_not_wkb(): void
    {
        // Drift guard (Batch 2B): with `LOAD spatial`, read_parquet returns the
        // GeoParquet geometry as native GEOMETRY, so coordinates must be read via
        // ST_X(geometry)/ST_Y(geometry). ST_GeomFromWKB(GEOMETRY) is a type error
        // and must never come back. Proven by the live Pinellas smoke run.
        $sql = $this->read('sql/extract_places.sql');
        $this->assertStringContainsString('LOAD spatial', $sql, 'spatial must stay loaded');
        $this->assertStringContainsString('ST_X(geometry)', $sql, 'lon must read native geometry');
        $this->assertStringContainsString('ST_Y(geometry)', $sql, 'lat must read native geometry');
        $this->assertStringNotContainsStringIgnoringCase('ST_GeomFromWKB', $sql,
            'ST_GeomFromWKB is incompatible with native GEOMETRY under LOAD spatial');
    }

    /** @test */
    public function every_q2_count_sql_filters_the_first_slice_and_is_count_only(): void
    {
        foreach (['count_pinellas.sql', 'count_florida.sql', 'count_conus.sql'] as $file) {
            $sql = $this->read("sql/q2/{$file}");
            $this->assertStringContainsString('count(*)', $sql, "{$file} must be count-only");
            $this->assertStringContainsString('confidence >= 0.90', $sql, "{$file} must apply the floor");
            $this->assertStringContainsString("'restaurant'", $sql, "{$file} must filter the first slice");
            // Count-only harness must never write / mutate.
            $this->assertStringNotContainsStringIgnoringCase('COPY', $sql, "{$file} must not COPY/write");
        }
    }

    /** @test */
    public function each_region_count_sql_uses_its_config_bbox_west_edge(): void
    {
        $regions = config('overture_places.regions');
        $map = [
            'count_pinellas.sql' => (string) $regions['pinellas']['west'],
            'count_florida.sql'  => (string) $regions['florida']['west'],
            'count_conus.sql'    => (string) $regions['conus']['west'],
        ];
        foreach ($map as $file => $west) {
            $this->assertStringContainsString($west, $this->read("sql/q2/{$file}"),
                "{$file} must use its config west edge {$west}");
        }
    }

    /** @test */
    public function the_measurement_runner_carries_the_accepted_proxies(): void
    {
        $php = $this->read('q2/run_measurements.php');
        $this->assertStringContainsString('BYTES_PER_ROW_TOTAL = 450', $php);
        $this->assertStringContainsString('GIST_BYTES_PER_ROW  = 94', $php);
    }

    private function read(string $rel): string
    {
        $path = $this->spikeDir . '/' . $rel;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
