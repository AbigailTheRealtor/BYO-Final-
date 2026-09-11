<?php

namespace Tests\Unit\Support\LocationDna;

use App\Support\LocationDna\LocationDnaCriteriaDisplay;
use PHPUnit\Framework\TestCase;

/**
 * The words beside a Buyer/Tenant search map. Every row here corresponds to something the
 * map draws — and nothing here may be a coordinate, a JSON fragment or a converted unit.
 */
class LocationDnaCriteriaDisplayTest extends TestCase
{
    private function preferences(): array
    {
        return [
            'radius_searches' => [
                ['address' => '315 E Madison St, Tampa, FL 33602', 'lat' => 27.9506, 'lng' => -82.4572, 'radius_miles' => 5],
                ['address' => '11687 Oxford St N, Seminole, FL 33772', 'lat' => 27.8403, 'lng' => -82.7876, 'radius_miles' => 5],
            ],
            'polygons' => [
                ['label' => 'Area 1', 'path' => [['lat' => 27.7, 'lng' => -82.7], ['lat' => 27.8, 'lng' => -82.7], ['lat' => 27.8, 'lng' => -82.6]]],
            ],
            'cities'            => ['Tampa', 'Seminole'],
            'zip_codes'         => ['33602'],
            'counties'          => ['Pinellas County'],
            'state'             => 'FL',
            'flexible_location' => true,
            'location_notes'    => 'Near good schools.',
        ];
    }

    private function places(): array
    {
        return [[
            'type' => 'Work', 'type_other' => '', 'address' => '116 8th St E, Tierra Verde, FL 33715',
            'lat' => 27.6917, 'lng' => -82.7215, 'distance_pref' => 'miles', 'distance_value' => 5, 'travel_mode' => 'driving',
        ]];
    }

    public function test_it_describes_each_radius_search_by_its_address_and_miles(): void
    {
        $display = LocationDnaCriteriaDisplay::from($this->preferences(), $this->places());

        $this->assertSame([
            ['title' => '315 E Madison St, Tampa, FL 33602', 'drawn' => false, 'distance' => 'Within 5 miles'],
            ['title' => '11687 Oxford St N, Seminole, FL 33772', 'drawn' => false, 'distance' => 'Within 5 miles'],
        ], $display->radiusSearches);
    }

    public function test_it_describes_each_important_place_by_type_address_and_miles(): void
    {
        $display = LocationDnaCriteriaDisplay::from($this->preferences(), $this->places());

        $this->assertSame([[
            'type'           => 'Work',
            'address'        => '116 8th St E, Tierra Verde, FL 33715',
            'distance'       => 'Within 5 miles',
            'on_map'         => 'pin_and_ring',
            'legacy_minutes' => false,
        ]], $display->importantPlaces);
    }

    public function test_custom_areas_flexibility_notes_and_named_areas_are_carried(): void
    {
        $display = LocationDnaCriteriaDisplay::from($this->preferences(), [], ['counties' => ['Pinellas County', 'Hillsborough County']]);

        $this->assertSame(1, $display->customAreaCount);
        $this->assertTrue($display->flexible);
        $this->assertSame('Near good schools.', $display->notes);
        $this->assertSame([
            'State'     => ['FL'],
            'Counties'  => ['Pinellas County', 'Hillsborough County'],
            'Cities'    => ['Tampa', 'Seminole'],
            'ZIP codes' => ['33602'],
        ], $display->areas);
    }

    public function test_a_drawn_circle_with_no_address_is_described_in_words_never_by_its_coordinates(): void
    {
        $display = LocationDnaCriteriaDisplay::from([
            'radius_searches' => [['lat' => 27.84, 'lng' => -82.78, 'radius_miles' => 2.5]],
        ]);

        $this->assertSame([['title' => 'Area drawn on the map', 'drawn' => true, 'distance' => 'Within 2.5 miles']], $display->radiusSearches);
    }

    public function test_the_legacy_nested_radius_shape_is_described_too(): void
    {
        $display = LocationDnaCriteriaDisplay::from([
            'radius_searches' => [['center' => ['lat' => 27.9, 'lng' => -82.45], 'radius_miles' => 1, 'label' => 'Tampa']],
        ]);

        $this->assertSame([['title' => 'Tampa', 'drawn' => true, 'distance' => 'Within 1 mile']], $display->radiusSearches);
    }

    public function test_a_historical_minutes_place_stays_minutes_and_is_marked_pin_only(): void
    {
        $display = LocationDnaCriteriaDisplay::from([], [[
            'type' => 'School', 'type_other' => '', 'address' => '1 School Rd', 'lat' => 27.9, 'lng' => -82.4,
            'distance_pref' => 'minutes', 'distance_value' => 25, 'travel_mode' => 'transit',
        ]]);

        $place = $display->importantPlaces[0];
        $this->assertSame('Within 25 minutes (travel time)', $place['distance']);
        $this->assertSame('pin', $place['on_map']);
        $this->assertTrue($place['legacy_minutes']);
        $this->assertStringNotContainsString('mile', $place['distance'], 'Minutes are never shown as miles');
    }

    public function test_other_type_unlocated_place_and_missing_distance_are_described_honestly(): void
    {
        $display = LocationDnaCriteriaDisplay::from([], [
            ['type' => 'Other', 'type_other' => 'Sailing club', 'address' => '5 Harbor Rd', 'lat' => null, 'lng' => null, 'distance_pref' => 'miles', 'distance_value' => 3],
            ['type' => 'Gym/Fitness', 'type_other' => '', 'address' => '', 'lat' => 27.9, 'lng' => -82.4, 'distance_pref' => 'miles', 'distance_value' => null],
        ]);

        $this->assertSame('Sailing club', $display->importantPlaces[0]['type']);
        $this->assertSame('none', $display->importantPlaces[0]['on_map']);
        $this->assertNull($display->importantPlaces[1]['distance']);
        $this->assertSame('pin', $display->importantPlaces[1]['on_map'], 'A pin with no usable distance draws no ring');
    }

    public function test_flexible_location_reads_the_stored_answer_not_its_presence(): void
    {
        $this->assertFalse(LocationDnaCriteriaDisplay::from(['flexible_location' => false])->flexible);
        $this->assertFalse(LocationDnaCriteriaDisplay::from(['flexible_location' => 'false'])->flexible);
        $this->assertTrue(LocationDnaCriteriaDisplay::from(['flexible_location' => 'true'])->flexible);
    }

    public function test_nothing_saved_is_empty_and_a_malformed_blob_does_not_throw(): void
    {
        $this->assertTrue(LocationDnaCriteriaDisplay::from(null)->isEmpty());
        $this->assertTrue(LocationDnaCriteriaDisplay::from(['radius_searches' => 'garbage', 'polygons' => [['path' => 'x']]], ['not-a-row'])->isEmpty());
    }

    public function test_no_output_carries_a_coordinate_or_json(): void
    {
        $display = LocationDnaCriteriaDisplay::from($this->preferences(), $this->places());
        $flat    = json_encode([$display->radiusSearches, $display->importantPlaces, $display->areas]);

        foreach (['27.9506', '-82.4572', '27.8403', '-82.7876', '27.6917', '-82.7215', '"lat"', '"lng"', 'radius_miles'] as $needle) {
            $this->assertStringNotContainsString($needle, $flat);
        }
    }
}
