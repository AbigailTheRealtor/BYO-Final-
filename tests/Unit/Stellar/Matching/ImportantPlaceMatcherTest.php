<?php

namespace Tests\Unit\Stellar\Matching;

use App\Services\Stellar\Matching\ImportantPlaceMatcher;
use App\Support\Geo\GreatCircleDistance;
use PHPUnit\Framework\TestCase;

/**
 * Each Important Place is judged independently against the listing's own MLS coordinate, by local
 * straight-line distance — and nothing the matcher returns says where the place is.
 *
 * Fixed coordinates on one meridian: there the great-circle distance is exactly R·Δφ, so a place
 * placed "2.1 miles north" is 2.1 miles away to far better than the one decimal displayed.
 */
class ImportantPlaceMatcherTest extends TestCase
{
    private const LNG = -82.4572;
    private const PROPERTY_LAT = 27.9506;

    private const WORK_ADDRESS = '116 8th St E, Tierra Verde, FL 33715';

    /** Latitude $miles north (negative = south) of $lat on the fixed meridian. */
    private static function north(float $lat, float $miles): float
    {
        return $lat + $miles * 180 / (M_PI * GreatCircleDistance::EARTH_RADIUS_MILES);
    }

    private static function place(string $type, float $milesFromProperty, ?float $requiredMiles, array $extra = []): array
    {
        return array_merge([
            'type'           => $type,
            'type_other'     => '',
            'address'        => self::WORK_ADDRESS,
            'lat'            => self::north(self::PROPERTY_LAT, $milesFromProperty),
            'lng'            => self::LNG,
            'distance_pref'  => 'miles',
            'distance_value' => $requiredMiles,
            'travel_mode'    => 'driving',
        ], $extra);
    }

    private static function evaluate(array $places, ?float $lat = self::PROPERTY_LAT, ?float $lng = self::LNG): array
    {
        return ImportantPlaceMatcher::evaluate($lat, $lng, $places);
    }

    // A / B ────────────────────────────────────────────────────────────────────

    public function test_a_property_inside_the_work_radius_passes(): void
    {
        [$row] = self::evaluate([self::place('Work', 2.1, 3)]);

        $this->assertSame('Work', $row['label']);
        $this->assertSame(3.0, $row['required_miles']);
        $this->assertEqualsWithDelta(2.1, $row['actual_miles'], 1e-6);
        $this->assertSame(ImportantPlaceMatcher::WITHIN, $row['status']);
        $this->assertTrue($row['matches']);
    }

    public function test_a_property_outside_the_work_radius_fails_that_requirement(): void
    {
        [$row] = self::evaluate([self::place('Work', -5.4, 3)]);

        $this->assertEqualsWithDelta(5.4, $row['actual_miles'], 1e-6);
        $this->assertSame(ImportantPlaceMatcher::OUTSIDE, $row['status']);
        $this->assertFalse($row['matches']);
    }

    // D ────────────────────────────────────────────────────────────────────────

    public function test_several_places_are_measured_independently(): void
    {
        $rows = self::evaluate([
            self::place('Work', -3.7, 5),
            self::place('School', 2.4, 3),
            self::place('Other', 1.2, 2, ['type_other' => 'Publix']),
            self::place('Family/Friends', 7.8, 5),
        ]);

        $this->assertSame(['Work', 'School', 'Publix', 'Family/Friends'], array_column($rows, 'label'));
        $this->assertSame([3.7, 2.4, 1.2, 7.8], array_map(fn ($r) => round($r['actual_miles'], 1), $rows));
        $this->assertSame([true, true, true, false], array_column($rows, 'matches'));
        $this->assertEqualsWithDelta(0.75, ImportantPlaceMatcher::satisfiedShare($rows), 1e-12);
    }

    // E ────────────────────────────────────────────────────────────────────────

    public function test_exactly_the_requested_distance_is_within_it_and_a_hair_beyond_is_not(): void
    {
        $exact = GreatCircleDistance::miles(self::PROPERTY_LAT, self::LNG, self::north(self::PROPERTY_LAT, 3), self::LNG);

        [$onTheLine] = self::evaluate([self::place('Work', 3, $exact)]);
        [$atThree]   = self::evaluate([self::place('Work', 3, 3)]);
        [$beyond]    = self::evaluate([self::place('Work', 3.01, 3)]);

        $this->assertTrue($onTheLine['matches'], 'A listing at exactly the requested distance is within it');
        $this->assertTrue($atThree['matches'], 'Float noise at the boundary must not flip the verdict');
        $this->assertFalse($beyond['matches']);
    }

    // F / G ────────────────────────────────────────────────────────────────────

    public function test_a_listing_without_coordinates_is_distance_unavailable_never_a_match(): void
    {
        foreach ([[null, null], [null, self::LNG], [0.0, 0.0]] as [$lat, $lng]) {
            $rows = self::evaluate([self::place('Work', 1, 3), self::place('School', 1, 5)], $lat, $lng);

            foreach ($rows as $row) {
                $this->assertSame(ImportantPlaceMatcher::UNAVAILABLE, $row['status']);
                $this->assertSame(ImportantPlaceMatcher::REASON_PROPERTY_NOT_LOCATED, $row['reason']);
                $this->assertNull($row['matches']);
                $this->assertNull($row['actual_miles']);
            }
            $this->assertNull(ImportantPlaceMatcher::satisfiedShare($rows), 'Nothing measured → no credit and no fail');
        }
    }

    public function test_an_unlocated_place_is_unavailable_and_does_not_count_against_the_others(): void
    {
        $rows = self::evaluate([
            self::place('Work', 1, 3, ['lat' => null, 'lng' => null]),
            self::place('School', 1, 3),
        ]);

        $this->assertSame(ImportantPlaceMatcher::REASON_PLACE_NOT_LOCATED, $rows[0]['reason']);
        $this->assertNull($rows[0]['matches']);
        $this->assertTrue($rows[1]['matches']);
        $this->assertSame(1.0, ImportantPlaceMatcher::satisfiedShare($rows));
    }

    // H / I ────────────────────────────────────────────────────────────────────

    public function test_no_row_carries_the_places_address_or_coordinate(): void
    {
        $place = self::place('Work', 2.1, 3);
        $rows  = self::evaluate([$place]);

        foreach ([$rows, ImportantPlaceMatcher::present($rows)] as $output) {
            $flat = json_encode($output);
            foreach (['address', '"lat"', '"lng"', 'Tierra Verde', '116 8th St', (string) round($place['lat'], 4), '82.4572'] as $needle) {
                $this->assertStringNotContainsString($needle, $flat, "Matcher output must not carry [{$needle}]");
            }
        }
    }

    // L ────────────────────────────────────────────────────────────────────────

    public function test_minutes_and_travel_mode_never_become_a_distance_requirement(): void
    {
        $rows = self::evaluate([
            self::place('School', 1, 25, ['distance_pref' => 'minutes', 'travel_mode' => 'transit']),
            self::place('Gym/Fitness', 1, 0),
            self::place('Grocery', 1, null),
            self::place('Work', 1, 3),
        ]);

        $this->assertSame(['Work'], array_column($rows, 'label'), 'Only a positive miles value is a requirement');
        $this->assertNull(ImportantPlaceMatcher::requiredMiles(['distance_pref' => 'minutes', 'distance_value' => 25]));
    }

    // Presentation ─────────────────────────────────────────────────────────────

    public function test_presented_rows_read_as_category_distance_and_requested_maximum(): void
    {
        $presented = ImportantPlaceMatcher::present(self::evaluate([
            self::place('Work', 2.1, 3),
            self::place('Family/Friends', 7.8, 5),
            self::place('School', 1, 2.5, ['lat' => null]),
        ]));

        $this->assertSame([
            ['type' => 'Work', 'label' => 'Work', 'status' => 'within', 'matches' => true, 'actual_display' => '2.1 mi', 'required_display' => 'within 3 mi'],
            ['type' => 'Family/Friends', 'label' => 'Family/Friends', 'status' => 'outside', 'matches' => false, 'actual_display' => '7.8 mi', 'required_display' => 'within 5 mi'],
            ['type' => 'School', 'label' => 'School', 'status' => 'distance_unavailable', 'matches' => null, 'actual_display' => null, 'required_display' => 'within 2.5 mi'],
        ], $presented);
    }

    public function test_a_rounded_distance_never_contradicts_its_verdict(): void
    {
        [$justOutside] = ImportantPlaceMatcher::present(self::evaluate([self::place('Work', 3.04, 3)]));

        $this->assertFalse($justOutside['matches']);
        $this->assertSame('3.04 mi', $justOutside['actual_display'], '"3.0 mi" beside a cross would contradict itself');
    }
}
