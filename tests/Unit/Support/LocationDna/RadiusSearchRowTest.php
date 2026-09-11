<?php

namespace Tests\Unit\Support\LocationDna;

use App\Support\LocationDna\RadiusSearchRow;
use PHPUnit\Framework\TestCase;

/**
 * RadiusSearchRow is the one reading of a stored radius search, shared by the detail map,
 * the flood and school-district lookups, the enrichment runner and the detail summary.
 *
 * Its job is to read BOTH stored shapes identically. The flat shape is what every current
 * Buyer/Tenant surface writes; four readers used to accept only the nested `center` shape
 * and so skipped every radius search saved today. These cases pin both, and the order
 * between them.
 */
class RadiusSearchRowTest extends TestCase
{
    public function test_the_flat_shape_the_widget_writes_is_read(): void
    {
        $row = ['address' => '315 E Madison St, Tampa, FL 33602', 'lat' => 27.9506, 'lng' => -82.4572, 'radius_miles' => 5];

        $this->assertSame(['lat' => 27.9506, 'lng' => -82.4572], RadiusSearchRow::center($row));
        $this->assertSame(5.0, RadiusSearchRow::miles($row));
        $this->assertSame(['lat' => 27.9506, 'lng' => -82.4572, 'radius_miles' => 5.0], RadiusSearchRow::circle($row));
        $this->assertSame('315 E Madison St, Tampa, FL 33602', RadiusSearchRow::description($row));
    }

    public function test_the_nested_legacy_shape_is_still_read(): void
    {
        $row = ['center' => ['lat' => 27.9, 'lng' => -82.45], 'radius_miles' => 10, 'label' => 'Tampa'];

        $this->assertSame(['lat' => 27.9, 'lng' => -82.45, 'radius_miles' => 10.0], RadiusSearchRow::circle($row));
        $this->assertSame('Tampa', RadiusSearchRow::description($row));
    }

    public function test_flat_keys_win_over_center_the_same_way_the_matchers_read_them(): void
    {
        $row = ['lat' => 27.1, 'lng' => -82.1, 'center' => ['lat' => 28.9, 'lng' => -81.9], 'radius_miles' => 3];

        $this->assertSame(['lat' => 27.1, 'lng' => -82.1], RadiusSearchRow::center($row));
    }

    public function test_an_unusable_flat_point_falls_back_to_a_usable_center(): void
    {
        $row = ['lat' => 'n/a', 'lng' => -82.1, 'center' => ['lat' => 28.0, 'lng' => -82.0], 'radius_miles' => 3];

        $this->assertSame(['lat' => 28.0, 'lng' => -82.0], RadiusSearchRow::center($row));
    }

    /** @dataProvider unusableRows */
    public function test_a_row_without_a_usable_circle_describes_no_area(array $row): void
    {
        $this->assertNull(RadiusSearchRow::circle($row));
    }

    public static function unusableRows(): array
    {
        return [
            'no radius'           => [['lat' => 27.9, 'lng' => -82.4]],
            'zero radius'         => [['lat' => 27.9, 'lng' => -82.4, 'radius_miles' => 0]],
            'negative radius'     => [['lat' => 27.9, 'lng' => -82.4, 'radius_miles' => -2]],
            'text radius'         => [['lat' => 27.9, 'lng' => -82.4, 'radius_miles' => 'five']],
            'no centre'           => [['address' => 'Somewhere', 'radius_miles' => 5]],
            'latitude off globe'  => [['lat' => 900, 'lng' => -82.4, 'radius_miles' => 5]],
            'longitude off globe' => [['lat' => 27.9, 'lng' => -482.4, 'radius_miles' => 5]],
            'text coordinates'    => [['lat' => 'north', 'lng' => 'west', 'radius_miles' => 5]],
        ];
    }

    public function test_non_array_input_is_not_a_row(): void
    {
        $this->assertNull(RadiusSearchRow::center(null));
        $this->assertNull(RadiusSearchRow::miles('5'));
        $this->assertSame('', RadiusSearchRow::description(42));
    }

    public function test_address_is_preferred_to_label_and_blanks_are_ignored(): void
    {
        $this->assertSame('1 Main St', RadiusSearchRow::description(['address' => '1 Main St', 'label' => 'Circle 1']));
        $this->assertSame('Circle 1', RadiusSearchRow::description(['address' => '   ', 'label' => 'Circle 1']));
        $this->assertSame('', RadiusSearchRow::description(['lat' => 1, 'lng' => 2]));
    }

    public function test_reading_a_row_never_changes_it(): void
    {
        $row    = ['center' => ['lat' => 27.9, 'lng' => -82.45], 'radius_miles' => '10', 'label' => 'Tampa'];
        $before = $row;

        RadiusSearchRow::circle($row);
        RadiusSearchRow::description($row);

        $this->assertSame($before, $row);
    }
}
