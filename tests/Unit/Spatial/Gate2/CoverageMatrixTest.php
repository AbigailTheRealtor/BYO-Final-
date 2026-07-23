<?php

namespace Tests\Unit\Spatial\Gate2;

use App\Services\Spatial\Gate2\CoverageCell;
use App\Services\Spatial\Gate2\CoverageMatrix;
use Tests\TestCase;

/**
 * Batch 2D Part C3d-a — CoverageMatrix: the assembled dataset × territory grid. It partitions
 * observations (present / absent / unmeasured) factually; it computes no coverage metric.
 */
class CoverageMatrixTest extends TestCase
{
    private function matrix(): CoverageMatrix
    {
        return new CoverageMatrix(
            territories: ['FL', 'PR', 'AK', 'rural_CONUS'],
            datasets: ['overture_places', 'epa_walkability'],
            datasetCategories: ['overture_places' => ['grocery_store'], 'epa_walkability' => ['walkability_index']],
            prWatchDatasets: ['epa_walkability'],
            cells: [
                new CoverageCell('overture_places', 'grocery_store', 'FL', true, 100),
                new CoverageCell('overture_places', 'grocery_store', 'PR', true, 5),
                new CoverageCell('overture_places', 'grocery_store', 'AK', false),
                new CoverageCell('epa_walkability', 'walkability_index', 'PR', true, 0),
            ],
        );
    }

    /** @test */
    public function pr_and_ak_are_distinct_territories(): void
    {
        $m = $this->matrix();
        $this->assertContains('PR', $m->territories());
        $this->assertContains('AK', $m->territories());
        $this->assertNotEmpty($m->cellsForTerritory('PR'));
        $this->assertNotEmpty($m->cellsForTerritory('AK'));
    }

    /** @test */
    public function gaps_are_measured_zero_cells_only(): void
    {
        $gaps = $this->matrix()->gaps();
        $this->assertCount(1, $gaps);
        $this->assertSame('epa_walkability', $gaps[0]->dataset);
        $this->assertTrue($gaps[0]->isAbsent());
    }

    /** @test */
    public function unmeasured_and_present_partitions_are_disjoint_and_factual(): void
    {
        $m = $this->matrix();
        $this->assertCount(1, $m->unmeasured());
        $this->assertCount(2, $m->present());
        // present + absent + unmeasured == all cells (a total partition, no overlap).
        $this->assertCount(
            count($m->cells()),
            array_merge($m->present(), $m->gaps(), $m->unmeasured()),
        );
    }

    /** @test */
    public function cell_lookup_resolves_by_identity(): void
    {
        $cell = $this->matrix()->cell('overture_places', 'grocery_store', 'PR');
        $this->assertNotNull($cell);
        $this->assertSame(5, $cell->presentCount);
        $this->assertNull($this->matrix()->cell('overture_places', 'grocery_store', 'nowhere'));
    }

    /** @test */
    public function pr_watch_cells_are_the_watch_datasets_in_the_pr_territory(): void
    {
        $watch = $this->matrix()->prWatchCells('PR');
        $this->assertCount(1, $watch);
        $this->assertSame('epa_walkability', $watch[0]->dataset);
        $this->assertSame('PR', $watch[0]->territory);
    }

    /** @test */
    public function to_array_carries_no_metric_or_verdict_keys(): void
    {
        $json = json_encode($this->matrix()->toArray());
        foreach (['coverage', 'ratio', 'percent', 'threshold', 'passed', 'numerator', 'denominator'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $json, "matrix.json must not contain '{$forbidden}'");
        }
    }

    /** @test */
    public function to_array_shape_is_deterministic(): void
    {
        $this->assertSame(
            ['territories', 'datasets', 'pr_watch_datasets', 'cells'],
            array_keys($this->matrix()->toArray()),
        );
    }
}
