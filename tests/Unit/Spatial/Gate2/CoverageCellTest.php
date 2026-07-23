<?php

namespace Tests\Unit\Spatial\Gate2;

use App\Services\Spatial\Gate2\CoverageCell;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Batch 2D Part C3d-a — CoverageCell: one (dataset × category × territory) observation. It records
 * evidence (measured? how many present?), never a coverage metric. Pure; no DB, no network.
 */
class CoverageCellTest extends TestCase
{
    /** @test */
    public function a_measured_present_cell_reports_present_status(): void
    {
        $cell = new CoverageCell('overture_places', 'grocery_store', 'FL', true, 42);

        $this->assertSame(CoverageCell::STATUS_PRESENT, $cell->status());
        $this->assertTrue($cell->isPresent());
        $this->assertFalse($cell->isAbsent());
        $this->assertFalse($cell->isUnmeasured());
        $this->assertSame(42, $cell->presentCount);
    }

    /** @test */
    public function a_measured_zero_cell_is_absent_not_unmeasured(): void
    {
        $cell = new CoverageCell('epa_walkability', 'walkability_index', 'PR', true, 0);

        $this->assertSame(CoverageCell::STATUS_ABSENT, $cell->status());
        $this->assertTrue($cell->isAbsent());
        $this->assertFalse($cell->isUnmeasured());
    }

    /** @test */
    public function an_unmeasured_cell_is_distinct_from_measured_zero_and_carries_no_count(): void
    {
        $cell = new CoverageCell('noaa_cusp', 'coastline', 'PR', false);

        $this->assertSame(CoverageCell::STATUS_UNMEASURED, $cell->status());
        $this->assertTrue($cell->isUnmeasured());
        $this->assertFalse($cell->isAbsent());
        $this->assertNull($cell->presentCount);
    }

    /** @test */
    public function a_not_measured_cell_may_not_carry_a_count(): void
    {
        // The exact honesty invariant: unmeasured is not zero, so it cannot smuggle a number.
        $this->expectException(InvalidArgumentException::class);
        new CoverageCell('noaa_cusp', 'coastline', 'PR', false, 0);
    }

    /** @test */
    public function a_measured_cell_requires_a_non_negative_count(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CoverageCell('overture_places', 'grocery_store', 'FL', true, null);
    }

    /** @test */
    public function a_negative_count_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CoverageCell('overture_places', 'grocery_store', 'FL', true, -1);
    }

    /** @test */
    public function blank_axis_values_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CoverageCell('', 'grocery_store', 'FL', true, 1);
    }

    /** @test */
    public function to_array_has_a_fixed_key_order_and_derived_status(): void
    {
        $cell = new CoverageCell('dot_nad', 'address_point', 'AK', true, 5, 'a note');

        $this->assertSame(
            ['dataset', 'category', 'territory', 'measured', 'present_count', 'status', 'note'],
            array_keys($cell->toArray()),
        );
        $this->assertSame('present', $cell->toArray()['status']);
        $this->assertSame('a note', $cell->toArray()['note']);
    }

    /** @test */
    public function from_array_ignores_any_supplied_status_and_derives_it(): void
    {
        // A caller cannot forge `status`; it is always derived from measured + present_count.
        $cell = CoverageCell::fromArray([
            'dataset'       => 'cms_health',
            'category'      => 'hospital',
            'territory'     => 'PR',
            'measured'      => true,
            'present_count' => 0,
            'status'        => 'present', // lie — must be ignored
        ]);

        $this->assertSame('absent', $cell->status());
    }

    /** @test */
    public function from_array_requires_the_identity_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CoverageCell::fromArray(['dataset' => 'x', 'category' => 'y']); // missing territory
    }

    /** @test */
    public function to_array_from_array_round_trips_for_each_status(): void
    {
        foreach ([
            new CoverageCell('d', 'c', 'FL', true, 7),
            new CoverageCell('d', 'c', 'PR', true, 0),
            new CoverageCell('d', 'c', 'AK', false),
        ] as $cell) {
            $round = CoverageCell::fromArray($cell->toArray());
            $this->assertSame($cell->toArray(), $round->toArray());
        }
    }
}
