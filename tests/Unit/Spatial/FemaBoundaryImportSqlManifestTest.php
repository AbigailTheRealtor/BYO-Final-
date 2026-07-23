<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\BoundaryRowMaterializer;
use Tests\TestCase;

/**
 * Batch 2D Part C3c — the authored Class-2 FEMA NFHL boundary recipes must not drift from the
 * framework (COPY column order), stay guarded/offline, subdivide via ST_Subdivide (E-24), and verify
 * geometry with ST_IsValid without auto-applying ST_MakeValid. Pure file inspection; no PostGIS.
 * Mirrors the C3a/C3b SqlManifest tests.
 */
class FemaBoundaryImportSqlManifestTest extends TestCase
{
    private string $spikeDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->spikeDir = dirname(__DIR__, 3) . '/spikes/phase-2-batch-2d-part-c3c-fema-nfhl-boundary-import';
    }

    private function sql(string $file): string
    {
        $path = $this->spikeDir . '/sql/' . $file;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /** @test */
    public function both_recipes_are_marked_authored_not_run_and_cluster_guarded(): void
    {
        foreach (['stage_boundaries.sql', 'load_fema_boundaries.sql'] as $file) {
            $sql = $this->sql($file);
            $this->assertStringContainsString('AUTHORED, NOT RUN', $sql, "{$file} must be marked authored-not-run");
            $this->assertStringContainsString('SPATIAL_', $sql, "{$file} must reference the (unset) spatial guard");
        }
    }

    /** @test */
    public function the_stage_copy_column_list_equals_the_materializer_columns(): void
    {
        $this->assertStringContainsString(
            implode(', ', BoundaryRowMaterializer::COLUMNS),
            $this->sql('stage_boundaries.sql'),
            'stage COPY column list must equal BoundaryRowMaterializer::COLUMNS',
        );
    }

    /** @test */
    public function the_stage_recipe_never_writes_to_boundaries_directly(): void
    {
        $this->assertStringNotContainsString('INSERT INTO BOUNDARIES ', strtoupper($this->sql('stage_boundaries.sql')));
    }

    /** @test */
    public function the_load_recipe_appends_boundaries_and_derives_parts_via_st_subdivide(): void
    {
        $sql = $this->sql('load_fema_boundaries.sql');
        $upper = strtoupper($sql);

        $this->assertSame(1, substr_count($upper, 'INSERT INTO BOUNDARIES '), 'exactly one boundaries append');
        $this->assertSame(1, substr_count($upper, 'INSERT INTO BOUNDARIES_PARTS'), 'exactly one boundaries_parts derivation');

        $this->assertStringContainsString('ST_Subdivide(', $sql);
        $this->assertStringContainsString('::geometry', $sql);
        $this->assertStringContainsString('::geography', $sql);
        $this->assertStringContainsString('256', $sql, 'ST_Subdivide <=256 vertex cap');

        // Both FEMA kinds are the load target — the coverage footprint is not optional (INV-5).
        foreach (['flood_zone', 'flood_coverage'] as $kind) {
            $this->assertStringContainsString("'{$kind}'", $sql, "load must target kind {$kind}");
        }
    }

    /** @test */
    public function the_load_recipe_verifies_with_st_isvalid_but_never_auto_applies_st_makevalid(): void
    {
        $sql = $this->sql('load_fema_boundaries.sql');
        $upper = strtoupper($sql);

        $this->assertStringContainsString('ST_IsValid', $sql, 'geometry verification query present');
        $this->assertStringContainsString('ST_MakeValid', $sql, 'ST_MakeValid documented as possible remediation');
        $this->assertStringNotContainsString('UPDATE ', $upper, 'no auto-remediation UPDATE');
        foreach (['DELETE ', 'DROP ', 'ATTACH ', 'TRUNCATE '] as $verb) {
            $this->assertStringNotContainsString($verb, $upper, "recipe must not {$verb}");
        }
    }

    /**
     * C3c authors the import only. Flood RESOLUTION (zone vs outside-SFHA vs not-mapped vs live
     * fallback) is downstream and deliberately absent from this recipe.
     *
     * @test
     */
    public function the_load_recipe_contains_no_downstream_flood_resolution(): void
    {
        $upper = strtoupper($this->sql('load_fema_boundaries.sql'));

        foreach (['FLOOD_SOURCE', 'ST_INTERSECTS', 'ST_CONTAINS', 'ST_DWITHIN', 'LISTING_LOCATIONS'] as $token) {
            $this->assertStringNotContainsString($token, $upper, "resolution token {$token} must not appear in the import recipe");
        }
    }

    /** @test */
    public function the_spike_ships_a_readme_and_results_template(): void
    {
        $this->assertFileExists($this->spikeDir . '/README.md');
        $this->assertFileExists($this->spikeDir . '/RESULTS_TEMPLATE.md');
    }
}
