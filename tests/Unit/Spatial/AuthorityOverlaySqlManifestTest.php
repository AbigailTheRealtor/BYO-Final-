<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\AuthorityStagingMaterializer;
use Tests\TestCase;

/**
 * Batch 2D Part C2 — the authored Class-2 overlay recipes must not drift from the framework
 * (COPY column order) and must stay guarded, offline, and read-only except the single base-source
 * INSERT. Pure file inspection; no PostGIS. Mirrors C1's LinkAuthoritySqlManifest.
 */
class AuthorityOverlaySqlManifestTest extends TestCase
{
    private string $spikeDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->spikeDir = dirname(__DIR__, 3) . '/spikes/phase-2-batch-2d-part-c2-authority-overlay-import';
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
        foreach (['stage_authority_overlay.sql', 'load_usgs_boat_ramps.sql'] as $file) {
            $sql = $this->sql($file);
            $this->assertStringContainsString('AUTHORED, NOT RUN', $sql, "{$file} must be marked authored-not-run");
            $this->assertStringContainsString('SPATIAL_', $sql, "{$file} must reference the (unset) spatial guard");
        }
    }

    /** @test */
    public function the_stage_copy_column_list_equals_the_materializer_columns(): void
    {
        $this->assertStringContainsString(
            implode(', ', AuthorityStagingMaterializer::COLUMNS),
            $this->sql('stage_authority_overlay.sql'),
            'stage COPY column list must equal AuthorityStagingMaterializer::COLUMNS',
        );
    }

    /** @test */
    public function the_stage_recipe_never_writes_to_places(): void
    {
        $this->assertStringNotContainsString('INSERT INTO PLACES', strtoupper($this->sql('stage_authority_overlay.sql')));
    }

    /** @test */
    public function the_base_source_load_has_exactly_one_places_insert_and_nothing_else_mutates(): void
    {
        $sql = strtoupper($this->sql('load_usgs_boat_ramps.sql'));
        $this->assertSame(1, substr_count($sql, 'INSERT INTO PLACES'), 'exactly one base-source INSERT');
        foreach (['UPDATE ', 'DELETE ', 'DROP ', 'ATTACH ', 'TRUNCATE '] as $writeVerb) {
            $this->assertStringNotContainsString($writeVerb, $sql, "recipe must not {$writeVerb}");
        }
    }

    /** @test */
    public function the_spike_ships_a_readme_and_results_template(): void
    {
        $this->assertFileExists($this->spikeDir . '/README.md');
        $this->assertFileExists($this->spikeDir . '/RESULTS_TEMPLATE.md');
    }
}
