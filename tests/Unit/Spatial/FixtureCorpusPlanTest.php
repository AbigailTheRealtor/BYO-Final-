<?php

namespace Tests\Unit\Spatial;

use PHPUnit\Framework\TestCase;
use Tests\Support\Spatial\FixtureCorpusPlan;

/**
 * B1.2 — unit tests for the Tier-2 fixture distribution (no DB), and its
 * agreement with the runnable generator SQL manifest.
 */
class FixtureCorpusPlanTest extends TestCase
{
    private string $sqlPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sqlPath = dirname(__DIR__, 3)
            . '/spikes/phase-2-batch-1b-postgis-schema/fixtures/generate_tier2_fixture.sql';
    }

    /** @test */
    public function tier2_counts_sum_to_the_stage0b_total(): void
    {
        $counts = (new FixtureCorpusPlan())->tier2Counts();
        $this->assertSame(FixtureCorpusPlan::TIER2_TOTAL, array_sum($counts));
        $this->assertSame(5_000_200, array_sum($counts));
    }

    /** @test */
    public function for_total_at_tier2_reproduces_the_exact_counts(): void
    {
        $plan = new FixtureCorpusPlan();
        $this->assertSame($plan->tier2Counts(), $plan->forTotal(FixtureCorpusPlan::TIER2_TOTAL));
    }

    /** @test */
    public function for_total_always_sums_exactly(): void
    {
        $plan = new FixtureCorpusPlan();
        foreach ([1000, 50_000, 250_001, 5_000_200, 10_000_000] as $n) {
            $this->assertSame($n, array_sum($plan->forTotal($n)), "forTotal({$n}) must sum exactly");
        }
    }

    /** @test */
    public function for_total_rejects_non_positive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new FixtureCorpusPlan())->forTotal(0);
    }

    /** @test */
    public function every_sparse_category_stays_under_point_six_percent(): void
    {
        $plan = new FixtureCorpusPlan();
        foreach ($plan->sparseFractions() as $cat => $frac) {
            $this->assertLessThan(0.006, $frac, "{$cat} must stay < 0.6% to be a meaningful KNN target");
        }
        $this->assertSame(['urgent_care', 'marina', 'boat_ramp', 'airport'], $plan->sparseCategories());
    }

    /** @test */
    public function the_corpus_is_a_true_geometry_mix(): void
    {
        $plan = new FixtureCorpusPlan();
        $this->assertTrue($plan->isMixedGeometry());
        $this->assertSame('Polygon', $plan->geometryType('park'));
        $this->assertSame('Polygon', $plan->geometryType('airport'));
        $this->assertSame('LineString', $plan->geometryType('boat_ramp'));
        $this->assertSame('Point', $plan->geometryType('marina'));
        $this->assertSame('Point', $plan->geometryType('restaurant'));
    }

    /** @test */
    public function the_generator_sql_manifest_matches_the_plan(): void
    {
        $this->assertFileExists($this->sqlPath);
        $sql = file_get_contents($this->sqlPath);

        // Parse the "-- @fixture-manifest ... @end-manifest" block.
        $this->assertMatchesRegularExpression('/@fixture-manifest.*@end-manifest/s', $sql);
        preg_match('/@fixture-manifest(.*?)@end-manifest/s', $sql, $m);
        $block = $m[1];

        $manifest = [];
        foreach (explode("\n", $block) as $line) {
            if (preg_match('/^\s*--\s*([a-z_]+)\s*=\s*(\d+)\s+\w+/', $line, $mm)) {
                $manifest[$mm[1]] = (int) $mm[2];
            }
        }

        $this->assertSame((new FixtureCorpusPlan())->tier2Counts(), $manifest,
            'The SQL @fixture-manifest counts must equal FixtureCorpusPlan::tier2Counts()');
    }

    /** @test */
    public function the_generator_generate_series_calls_match_the_counts(): void
    {
        $sql = file_get_contents($this->sqlPath);
        $counts = (new FixtureCorpusPlan())->tier2Counts();

        // Every category count must appear as a generate_series(1, N) upper bound.
        foreach ($counts as $cat => $n) {
            $this->assertMatchesRegularExpression(
                '/generate_series\(1,\s*' . $n . '\)/',
                $sql,
                "Expected generate_series(1, {$n}) for {$cat}"
            );
        }
    }
}
