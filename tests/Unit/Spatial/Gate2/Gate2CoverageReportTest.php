<?php

namespace Tests\Unit\Spatial\Gate2;

use App\Services\Spatial\Gate2\Gate2CoverageAssembler;
use App\Services\Spatial\Gate2\Gate2CoverageReport;
use Tests\TestCase;

/**
 * Batch 2D Part C3d-a — Gate2CoverageReport: factual roll-ups for a product owner. It carries NO
 * pass/fail and NO coverage metric — acceptance is explicitly deferred to C3d-b.
 */
class Gate2CoverageReportTest extends TestCase
{
    private function report(): Gate2CoverageReport
    {
        $matrix = (new Gate2CoverageAssembler())->fromArray([
            'territories'       => ['FL', 'PR', 'AK', 'rural_CONUS'],
            'pr_watch_datasets' => ['epa_walkability', 'noaa_cusp'],
            'datasets'          => [
                ['key' => 'overture_places', 'categories' => ['grocery_store']],
                ['key' => 'epa_walkability', 'categories' => ['walkability_index']],
                ['key' => 'noaa_cusp', 'categories' => ['coastline']],
            ],
            'observations' => [
                ['dataset' => 'overture_places', 'category' => 'grocery_store', 'territory' => 'FL', 'measured' => true, 'present_count' => 10],
                ['dataset' => 'epa_walkability', 'category' => 'walkability_index', 'territory' => 'PR', 'measured' => true, 'present_count' => 0],
                ['dataset' => 'noaa_cusp', 'category' => 'coastline', 'territory' => 'PR', 'measured' => false],
            ],
        ]);

        return new Gate2CoverageReport($matrix, 'PR');
    }

    /** @test */
    public function cell_totals_partition_every_cell(): void
    {
        $t = $this->report()->cellTotals();
        // 3 datasets × 1 cat × 4 territories = 12 cells.
        $this->assertSame(12, $t['total']);
        $this->assertSame($t['present'] + $t['absent'] + $t['unmeasured'], $t['total']);
        $this->assertSame($t['present'] + $t['absent'], $t['measured']);
    }

    /** @test */
    public function per_territory_follows_the_declared_axis_order(): void
    {
        $this->assertSame(['FL', 'PR', 'AK', 'rural_CONUS'], array_keys($this->report()->perTerritory()));
    }

    /** @test */
    public function pr_watch_verification_distinguishes_absent_from_unmeasured(): void
    {
        $watch = $this->report()->prWatchVerification();

        $this->assertSame('absent', $watch['epa_walkability'][0]['status']);
        $this->assertSame(0, $watch['epa_walkability'][0]['present_count']);

        // NOAA CUSP for PR is unknown, NOT zero — the exact honesty the SSOT demands.
        $this->assertSame('unmeasured', $watch['noaa_cusp'][0]['status']);
        $this->assertNull($watch['noaa_cusp'][0]['present_count']);
    }

    /** @test */
    public function acceptance_block_is_all_false_and_deferred_with_no_passed_key(): void
    {
        $acc = $this->report()->acceptance();

        $this->assertFalse($acc['coverage_metric_defined']);
        $this->assertFalse($acc['numerator_defined']);
        $this->assertFalse($acc['denominator_defined']);
        $this->assertFalse($acc['threshold_defined']);
        $this->assertFalse($acc['automated_pass_fail']);
        $this->assertStringContainsString('deferred to C3d-b', $acc['product_owner_acceptance']);
        $this->assertArrayNotHasKey('passed', $acc);
    }

    /** @test */
    public function to_array_carries_no_pass_fail_or_metric_keys(): void
    {
        $json = json_encode($this->report()->toArray());
        $this->assertStringNotContainsString('"passed"', $json);
        foreach (['ratio', 'percent', 'threshold_value', 'coverage_score'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $json);
        }
    }
}
