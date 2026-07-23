<?php

namespace Tests\Unit\Spatial\Gate2;

use App\Services\Spatial\Gate2\Gate2CoverageAssembler;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Batch 2D Part C3d-a — Gate2CoverageAssembler: provider-agnostic matrix builder. Fails closed on
 * incomplete or dishonest input; assembles the full grid with unmeasured cells explicit. No DB.
 */
class Gate2CoverageAssemblerTest extends TestCase
{
    /** @return array<string,mixed> A minimal, valid input covering all required territories. */
    private function validInput(): array
    {
        return [
            'territories'       => ['FL', 'PR', 'AK', 'rural_CONUS'],
            'pr_watch_datasets' => ['epa_walkability'],
            'datasets'          => [
                ['key' => 'overture_places', 'categories' => ['grocery_store']],
                ['key' => 'epa_walkability', 'categories' => ['walkability_index']],
            ],
            'observations' => [
                ['dataset' => 'overture_places', 'category' => 'grocery_store', 'territory' => 'FL', 'measured' => true, 'present_count' => 10],
                ['dataset' => 'epa_walkability', 'category' => 'walkability_index', 'territory' => 'PR', 'measured' => true, 'present_count' => 0],
            ],
        ];
    }

    /** @test */
    public function it_assembles_the_full_cartesian_grid_with_unmeasured_cells_explicit(): void
    {
        $matrix = (new Gate2CoverageAssembler())->fromArray($this->validInput());

        // 2 datasets × 1 category each × 4 territories = 8 cells; only 2 observed → 6 unmeasured.
        $this->assertCount(8, $matrix->cells());
        $this->assertCount(6, $matrix->unmeasured());
        $this->assertCount(1, $matrix->present());
        $this->assertCount(1, $matrix->gaps());
    }

    /** @test */
    public function an_omitted_observation_becomes_an_unmeasured_cell_never_a_hole(): void
    {
        $matrix = (new Gate2CoverageAssembler())->fromArray($this->validInput());
        $cell = $matrix->cell('overture_places', 'grocery_store', 'AK');

        $this->assertNotNull($cell, 'the grid must contain every expected cell');
        $this->assertTrue($cell->isUnmeasured());
    }

    /** @test */
    public function it_fails_closed_when_a_required_territory_is_missing(): void
    {
        $input = $this->validInput();
        $input['territories'] = ['FL', 'PR', 'rural_CONUS']; // AK dropped

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("missing required territory 'AK'");
        (new Gate2CoverageAssembler())->fromArray($input);
    }

    /** @test */
    public function it_fails_closed_when_pr_is_folded_away(): void
    {
        $input = $this->validInput();
        $input['territories'] = ['FL', 'AK', 'rural_CONUS']; // PR dropped

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("missing required territory 'PR'");
        (new Gate2CoverageAssembler())->fromArray($input);
    }

    /** @test */
    public function a_pr_watch_dataset_must_be_declared(): void
    {
        $input = $this->validInput();
        $input['pr_watch_datasets'] = ['usgs_boat_ramps']; // not among the declared datasets

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("PR watch dataset 'usgs_boat_ramps' is not a declared dataset");
        (new Gate2CoverageAssembler())->fromArray($input);
    }

    /** @test */
    public function empty_axes_fail_closed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Gate2CoverageAssembler())->fromArray([
            'territories' => [],
            'datasets'    => [['key' => 'x', 'categories' => ['c']]],
        ]);
    }

    /** @test */
    public function a_stray_observation_referencing_an_undeclared_axis_is_rejected(): void
    {
        $input = $this->validInput();
        $input['observations'][] = ['dataset' => 'nonexistent', 'category' => 'c', 'territory' => 'FL', 'measured' => true, 'present_count' => 1];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("undeclared dataset 'nonexistent'");
        (new Gate2CoverageAssembler())->fromArray($input);
    }

    /** @test */
    public function a_duplicate_observation_for_one_cell_is_rejected(): void
    {
        $input = $this->validInput();
        $input['observations'][] = ['dataset' => 'overture_places', 'category' => 'grocery_store', 'territory' => 'FL', 'measured' => true, 'present_count' => 99];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('duplicate observation');
        (new Gate2CoverageAssembler())->fromArray($input);
    }

    /** @test */
    public function cells_are_in_deterministic_declared_order(): void
    {
        $matrix = (new Gate2CoverageAssembler())->fromArray($this->validInput());
        $keys = array_map(static fn ($c) => $c->key(), $matrix->cells());

        // dataset (declared) → category (declared) → territory (declared).
        $this->assertSame('overture_places|grocery_store|FL', $keys[0]);
        $this->assertSame('overture_places|grocery_store|PR', $keys[1]);
        $this->assertSame('overture_places|grocery_store|AK', $keys[2]);
        $this->assertSame('overture_places|grocery_store|rural_CONUS', $keys[3]);
        $this->assertSame('epa_walkability|walkability_index|FL', $keys[4]);
    }

    /** @test */
    public function the_same_input_shape_is_provider_agnostic_and_reproducible(): void
    {
        // The Class-2 SQL feeds the SAME shape; assembling twice yields identical wire output.
        $a = (new Gate2CoverageAssembler())->fromArray($this->validInput())->toArray();
        $b = (new Gate2CoverageAssembler())->fromArray($this->validInput())->toArray();
        $this->assertSame($a, $b);
    }
}
