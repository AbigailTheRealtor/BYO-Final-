<?php

namespace Tests\Unit\Spatial\Gate2;

use Tests\TestCase;

/**
 * Batch 2D Part C3d-a — the authored Class-2 Gate 2 coverage recipe must stay guarded/offline,
 * COUNT-only, and free of any invented metric or pass/fail. Pure file inspection; no PostGIS.
 * Mirrors the C3a/b/c SqlManifest tests.
 */
class Gate2CoverageSqlManifestTest extends TestCase
{
    private string $spikeDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->spikeDir = dirname(__DIR__, 4) . '/spikes/phase-2-batch-2d-part-c3d-a-gate2-evidence-schema';
    }

    private function sql(): string
    {
        $path = $this->spikeDir . '/sql/coverage_matrix.sql';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /** @test */
    public function the_recipe_is_marked_authored_not_run_and_cluster_guarded(): void
    {
        $sql = $this->sql();
        $this->assertStringContainsString('AUTHORED, NOT RUN', $sql);
        $this->assertStringContainsString('SPATIAL_', $sql, 'must reference the (unset) spatial guard');
    }

    /** @test */
    public function the_recipe_only_counts_and_computes_no_metric_or_verdict(): void
    {
        $upper = strtoupper($this->sql());

        // COUNT-only evidence.
        $this->assertStringContainsString('COUNT(*)', $upper, 'the recipe produces raw counts');

        // No computed coverage metric: no ratio/percentage casts and no averaging/summing of counts
        // into a score. Only unambiguous SQL arithmetic signals are checked — NOT prose words like
        // "percentage" or "ratio", which the header legitimately uses in NEGATION ("computes NO
        // coverage ratio, NO percentage").
        foreach (['::NUMERIC', '::FLOAT', '::DECIMAL', 'AVG(', 'SUM('] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $upper, "the recipe must not compute '{$forbidden}'");
        }
    }

    /** @test */
    public function the_recipe_writes_nothing(): void
    {
        $upper = strtoupper($this->sql());
        foreach (['INSERT ', 'UPDATE ', 'DELETE ', 'DROP ', 'CREATE ', 'TRUNCATE ', 'ALTER '] as $verb) {
            $this->assertStringNotContainsString($verb, $upper, "the recipe must not {$verb}");
        }
    }

    /** @test */
    public function the_recipe_reports_all_four_territories_and_the_pr_watch_datasets(): void
    {
        $sql = $this->sql();
        foreach (['FL', 'PR', 'AK', 'rural_CONUS'] as $territory) {
            $this->assertStringContainsString($territory, $sql, "territory {$territory} must appear");
        }
        foreach (['epa_walkability', 'usgs_boat_ramps', 'noaa_cusp', 'dot_nad'] as $watch) {
            $this->assertStringContainsString($watch, $sql, "PR watch dataset {$watch} must appear");
        }
    }

    /** @test */
    public function the_recipe_does_not_invent_a_rural_definition(): void
    {
        // "rural_CONUS" is a product/data decision; the recipe uses a placeholder param, not a set.
        $this->assertStringContainsString(':rural_conus_fips', $this->sql(), 'rural must be a placeholder param, not fabricated');
    }

    /** @test */
    public function the_spike_ships_a_readme_and_results_template(): void
    {
        $this->assertFileExists($this->spikeDir . '/README.md');
        $this->assertFileExists($this->spikeDir . '/RESULTS_TEMPLATE.md');
    }
}
