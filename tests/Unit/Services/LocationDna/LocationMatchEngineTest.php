<?php

namespace Tests\Unit\Services\LocationDna;

use App\Services\LocationDna\LocationMatchEngine;
use Tests\TestCase;

/**
 * LocationMatchEngineTest — Phase 6A
 *
 * Pure in-memory unit tests — no DB trait, no HTTP calls, no external services.
 *
 * Coverage:
 *   (1)  City match (case-insensitive)
 *   (2)  City mismatch
 *   (3)  ZIP match
 *   (4)  ZIP mismatch
 *   (5)  Neighborhood match (case-insensitive)
 *   (6)  Neighborhood mismatch
 *   (7)  Polygon inclusion (point clearly inside)
 *   (8)  Polygon exclusion (point clearly outside)
 *   (9)  Radius inclusion (point within radius)
 *   (10) Radius exclusion (point beyond radius)
 *   (11) Empty preferences → all defaults, no exception
 *   (12) Empty property data → all defaults, no exception
 *   (13) Output shape consistency — all 10 keys present on every code path
 *   (14) Governance — no OpenAI/Eloquent/DB imports in source file
 *   (15) overlap_signals correctly lists fired signal types
 *   (16) Multiple polygons — matched_polygon_count is accurate
 *   (17) Multiple radius searches — matched_radius_count is accurate
 *   (18) Polygon with fewer than 3 points is skipped gracefully
 *   (19) Radius entry with non-positive radius_miles is skipped
 *   (20) Mixed signals — city + zip + neighborhood + polygon + radius all fire together
 */
class LocationMatchEngineTest extends TestCase
{
    // =========================================================================
    // Contract constants
    // =========================================================================

    private const CONTRACT_KEYS = [
        'matched_cities',
        'city_match',
        'matched_zips',
        'zip_match',
        'matched_neighborhoods',
        'polygon_match',
        'matched_polygon_count',
        'radius_match',
        'matched_radius_count',
        'overlap_signals',
    ];

    // A simple unit square polygon: (0,0) → (0,1) → (1,1) → (1,0)
    // lat = y axis, lng = x axis
    private const UNIT_SQUARE = [
        ['lat' => 0.0, 'lng' => 0.0],
        ['lat' => 1.0, 'lng' => 0.0],
        ['lat' => 1.0, 'lng' => 1.0],
        ['lat' => 0.0, 'lng' => 1.0],
    ];

    // Tampa, FL coordinates for radius tests
    private const TAMPA_LAT = 27.9506;
    private const TAMPA_LNG = -82.4572;

    // =========================================================================
    // Helpers
    // =========================================================================

    private function engine(): LocationMatchEngine
    {
        return new LocationMatchEngine();
    }

    private function assertContractShape(array $result): void
    {
        foreach (self::CONTRACT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result,
                "Output contract key '{$key}' is missing from match() result");
        }

        $this->assertSame(
            count(self::CONTRACT_KEYS),
            count($result),
            'match() must return exactly the 10 approved contract keys'
        );
    }

    // =========================================================================
    // (1) City match — case-insensitive
    // =========================================================================

    /** @test */
    public function it_matches_city_case_insensitively(): void
    {
        $result = $this->engine()->match(
            ['cities' => ['Tampa', 'Orlando']],
            ['city' => 'TAMPA', 'zip' => '33601']
        );

        $this->assertContractShape($result);
        $this->assertTrue($result['city_match']);
        $this->assertContains('Tampa', $result['matched_cities']);
        $this->assertContains('city', $result['overlap_signals']);
    }

    /** @test */
    public function it_matches_city_when_property_city_has_leading_trailing_whitespace(): void
    {
        $result = $this->engine()->match(
            ['cities' => ['Tampa']],
            ['city' => '  Tampa  ']
        );

        $this->assertTrue($result['city_match']);
        $this->assertContains('Tampa', $result['matched_cities']);
    }

    // =========================================================================
    // (2) City mismatch
    // =========================================================================

    /** @test */
    public function it_returns_no_city_match_when_city_differs(): void
    {
        $result = $this->engine()->match(
            ['cities' => ['Miami', 'Jacksonville']],
            ['city' => 'Tampa']
        );

        $this->assertContractShape($result);
        $this->assertFalse($result['city_match']);
        $this->assertEmpty($result['matched_cities']);
        $this->assertNotContains('city', $result['overlap_signals']);
    }

    /** @test */
    public function it_returns_no_city_match_when_property_city_is_absent(): void
    {
        $result = $this->engine()->match(
            ['cities' => ['Tampa']],
            ['zip' => '33601']
        );

        $this->assertContractShape($result);
        $this->assertFalse($result['city_match']);
        $this->assertEmpty($result['matched_cities']);
    }

    // =========================================================================
    // (3) ZIP match
    // =========================================================================

    /** @test */
    public function it_matches_zip_code_exactly(): void
    {
        $result = $this->engine()->match(
            ['zip_codes' => ['33601', '33602']],
            ['zip' => '33602']
        );

        $this->assertContractShape($result);
        $this->assertTrue($result['zip_match']);
        $this->assertContains('33602', $result['matched_zips']);
        $this->assertContains('zip', $result['overlap_signals']);
    }

    /** @test */
    public function it_trims_whitespace_from_zip_comparisons(): void
    {
        $result = $this->engine()->match(
            ['zip_codes' => [' 33601 ']],
            ['zip' => '33601']
        );

        $this->assertTrue($result['zip_match']);
    }

    // =========================================================================
    // (4) ZIP mismatch
    // =========================================================================

    /** @test */
    public function it_returns_no_zip_match_when_zip_differs(): void
    {
        $result = $this->engine()->match(
            ['zip_codes' => ['10001', '10002']],
            ['zip' => '33601']
        );

        $this->assertContractShape($result);
        $this->assertFalse($result['zip_match']);
        $this->assertEmpty($result['matched_zips']);
        $this->assertNotContains('zip', $result['overlap_signals']);
    }

    /** @test */
    public function it_returns_no_zip_match_when_property_zip_is_absent(): void
    {
        $result = $this->engine()->match(
            ['zip_codes' => ['33601']],
            ['city' => 'Tampa']
        );

        $this->assertContractShape($result);
        $this->assertFalse($result['zip_match']);
    }

    // =========================================================================
    // (5) Neighborhood match — case-insensitive
    // =========================================================================

    /** @test */
    public function it_matches_neighborhood_case_insensitively(): void
    {
        $result = $this->engine()->match(
            ['neighborhoods' => ['Palma Ceia', 'Hyde Park']],
            ['neighborhood' => 'PALMA CEIA']
        );

        $this->assertContractShape($result);
        $this->assertContains('Palma Ceia', $result['matched_neighborhoods']);
        $this->assertContains('neighborhood', $result['overlap_signals']);
    }

    // =========================================================================
    // (6) Neighborhood mismatch
    // =========================================================================

    /** @test */
    public function it_returns_empty_matched_neighborhoods_when_neighborhood_differs(): void
    {
        $result = $this->engine()->match(
            ['neighborhoods' => ['Hyde Park']],
            ['neighborhood' => 'Seminole Heights']
        );

        $this->assertContractShape($result);
        $this->assertEmpty($result['matched_neighborhoods']);
        $this->assertNotContains('neighborhood', $result['overlap_signals']);
    }

    /** @test */
    public function it_skips_neighborhood_check_when_property_neighborhood_is_absent(): void
    {
        $result = $this->engine()->match(
            ['neighborhoods' => ['Hyde Park']],
            ['city' => 'Tampa']
        );

        $this->assertContractShape($result);
        $this->assertEmpty($result['matched_neighborhoods']);
    }

    // =========================================================================
    // (7) Polygon inclusion — point clearly inside unit square
    // =========================================================================

    /** @test */
    public function it_detects_point_inside_polygon(): void
    {
        $result = $this->engine()->match(
            ['polygons' => [['path' => self::UNIT_SQUARE]]],
            ['lat' => 0.5, 'lng' => 0.5]
        );

        $this->assertContractShape($result);
        $this->assertTrue($result['polygon_match']);
        $this->assertSame(1, $result['matched_polygon_count']);
        $this->assertContains('polygon', $result['overlap_signals']);
    }

    // =========================================================================
    // (8) Polygon exclusion — point clearly outside unit square
    // =========================================================================

    /** @test */
    public function it_returns_no_polygon_match_when_point_is_outside(): void
    {
        $result = $this->engine()->match(
            ['polygons' => [['path' => self::UNIT_SQUARE]]],
            ['lat' => 5.0, 'lng' => 5.0]
        );

        $this->assertContractShape($result);
        $this->assertFalse($result['polygon_match']);
        $this->assertSame(0, $result['matched_polygon_count']);
        $this->assertNotContains('polygon', $result['overlap_signals']);
    }

    // =========================================================================
    // (9) Radius inclusion — point within radius
    // =========================================================================

    /** @test */
    public function it_detects_point_within_radius(): void
    {
        // Center is Tampa; property is also Tampa — distance ~ 0 miles
        $result = $this->engine()->match(
            [
                'radius_searches' => [[
                    'center'       => ['lat' => self::TAMPA_LAT, 'lng' => self::TAMPA_LNG],
                    'radius_miles' => 5.0,
                ]],
            ],
            ['lat' => self::TAMPA_LAT, 'lng' => self::TAMPA_LNG]
        );

        $this->assertContractShape($result);
        $this->assertTrue($result['radius_match']);
        $this->assertSame(1, $result['matched_radius_count']);
        $this->assertContains('radius', $result['overlap_signals']);
    }

    /** @test */
    public function it_detects_point_within_radius_when_near_but_inside_boundary(): void
    {
        // Property ~4.5 miles north of Tampa center; 10-mile radius should include it
        $result = $this->engine()->match(
            [
                'radius_searches' => [[
                    'center'       => ['lat' => self::TAMPA_LAT, 'lng' => self::TAMPA_LNG],
                    'radius_miles' => 10.0,
                ]],
            ],
            ['lat' => 28.015, 'lng' => self::TAMPA_LNG]
        );

        $this->assertContractShape($result);
        $this->assertTrue($result['radius_match']);
    }

    // =========================================================================
    // (10) Radius exclusion — point beyond radius
    // =========================================================================

    /** @test */
    public function it_returns_no_radius_match_when_point_is_beyond_radius(): void
    {
        // Miami is ~280 miles from Tampa
        $miamiLat = 25.7617;
        $miamiLng = -80.1918;

        $result = $this->engine()->match(
            [
                'radius_searches' => [[
                    'center'       => ['lat' => self::TAMPA_LAT, 'lng' => self::TAMPA_LNG],
                    'radius_miles' => 50.0,
                ]],
            ],
            ['lat' => $miamiLat, 'lng' => $miamiLng]
        );

        $this->assertContractShape($result);
        $this->assertFalse($result['radius_match']);
        $this->assertSame(0, $result['matched_radius_count']);
        $this->assertNotContains('radius', $result['overlap_signals']);
    }

    // =========================================================================
    // (11) Empty preferences → all defaults, no exception
    // =========================================================================

    /** @test */
    public function it_returns_all_defaults_for_empty_preferences(): void
    {
        $result = $this->engine()->match(
            [],
            ['city' => 'Tampa', 'zip' => '33601', 'lat' => self::TAMPA_LAT, 'lng' => self::TAMPA_LNG]
        );

        $this->assertContractShape($result);
        $this->assertSame([], $result['matched_cities']);
        $this->assertFalse($result['city_match']);
        $this->assertSame([], $result['matched_zips']);
        $this->assertFalse($result['zip_match']);
        $this->assertSame([], $result['matched_neighborhoods']);
        $this->assertFalse($result['polygon_match']);
        $this->assertSame(0, $result['matched_polygon_count']);
        $this->assertFalse($result['radius_match']);
        $this->assertSame(0, $result['matched_radius_count']);
        $this->assertSame([], $result['overlap_signals']);
    }

    // =========================================================================
    // (12) Empty property data → all defaults, no exception
    // =========================================================================

    /** @test */
    public function it_returns_all_defaults_for_empty_property_data(): void
    {
        $result = $this->engine()->match(
            [
                'cities'          => ['Tampa'],
                'zip_codes'       => ['33601'],
                'neighborhoods'   => ['Hyde Park'],
                'polygons'        => [['path' => self::UNIT_SQUARE]],
                'radius_searches' => [[
                    'center'       => ['lat' => self::TAMPA_LAT, 'lng' => self::TAMPA_LNG],
                    'radius_miles' => 5.0,
                ]],
            ],
            []
        );

        $this->assertContractShape($result);
        $this->assertFalse($result['city_match']);
        $this->assertFalse($result['zip_match']);
        $this->assertEmpty($result['matched_neighborhoods']);
        $this->assertFalse($result['polygon_match']);
        $this->assertSame(0, $result['matched_polygon_count']);
        $this->assertFalse($result['radius_match']);
        $this->assertSame(0, $result['matched_radius_count']);
        $this->assertSame([], $result['overlap_signals']);
    }

    // =========================================================================
    // (13) Output shape consistency — all 10 keys on every code path
    // =========================================================================

    /** @test */
    public function output_shape_is_consistent_across_all_return_paths(): void
    {
        $paths = [
            // empty both
            [[], []],
            // city match
            [['cities' => ['Tampa']], ['city' => 'Tampa']],
            // city mismatch
            [['cities' => ['Miami']], ['city' => 'Tampa']],
            // zip match
            [['zip_codes' => ['33601']], ['zip' => '33601']],
            // zip mismatch
            [['zip_codes' => ['10001']], ['zip' => '33601']],
            // neighborhood match
            [['neighborhoods' => ['Hyde Park']], ['neighborhood' => 'Hyde Park']],
            // polygon inclusion
            [['polygons' => [['path' => self::UNIT_SQUARE]]], ['lat' => 0.5, 'lng' => 0.5]],
            // polygon exclusion
            [['polygons' => [['path' => self::UNIT_SQUARE]]], ['lat' => 5.0, 'lng' => 5.0]],
            // radius inclusion
            [
                ['radius_searches' => [['center' => ['lat' => self::TAMPA_LAT, 'lng' => self::TAMPA_LNG], 'radius_miles' => 5.0]]],
                ['lat' => self::TAMPA_LAT, 'lng' => self::TAMPA_LNG],
            ],
            // radius exclusion
            [
                ['radius_searches' => [['center' => ['lat' => self::TAMPA_LAT, 'lng' => self::TAMPA_LNG], 'radius_miles' => 5.0]]],
                ['lat' => 25.7617, 'lng' => -80.1918],
            ],
        ];

        foreach ($paths as [$preferences, $propertyData]) {
            $result = $this->engine()->match($preferences, $propertyData);
            $this->assertContractShape($result);
        }
    }

    // =========================================================================
    // (14) Governance — no OpenAI/Eloquent/DB imports
    // =========================================================================

    /** @test */
    public function source_file_contains_no_openai_or_eloquent_or_db_imports(): void
    {
        $path  = base_path('app/Services/LocationDna/LocationMatchEngine.php');
        $lines = file($path, FILE_IGNORE_NEW_LINES);

        foreach ($lines as $line) {
            $trimmed = ltrim($line);

            // Only inspect `use` import lines for class-level violations
            if (str_starts_with($trimmed, 'use ')) {
                $this->assertStringNotContainsStringIgnoringCase('openai', $trimmed,
                    "LocationMatchEngine must not import OpenAI classes (found: {$trimmed})");
                $this->assertStringNotContainsString('Illuminate\\Database\\Eloquent', $trimmed,
                    "LocationMatchEngine must not import Eloquent model classes (found: {$trimmed})");
                $this->assertStringNotContainsString('Illuminate\\Support\\Facades\\DB', $trimmed,
                    "LocationMatchEngine must not import the DB facade (found: {$trimmed})");
            }

            // DB:: calls are a code-level violation regardless of import style
            if (!str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//')) {
                $this->assertStringNotContainsString('DB::', $trimmed,
                    "LocationMatchEngine must not call DB:: directly (found: {$trimmed})");
            }
        }
    }

    // =========================================================================
    // (15) overlap_signals lists exactly the fired signal types
    // =========================================================================

    /** @test */
    public function overlap_signals_lists_only_the_signals_that_fired(): void
    {
        // City + ZIP both match
        $result = $this->engine()->match(
            ['cities' => ['Tampa'], 'zip_codes' => ['33601']],
            ['city' => 'Tampa', 'zip' => '33601']
        );

        $this->assertContains('city', $result['overlap_signals']);
        $this->assertContains('zip',  $result['overlap_signals']);
        $this->assertNotContains('neighborhood', $result['overlap_signals']);
        $this->assertNotContains('polygon',      $result['overlap_signals']);
        $this->assertNotContains('radius',       $result['overlap_signals']);
        $this->assertCount(2, $result['overlap_signals']);
    }

    /** @test */
    public function overlap_signals_is_empty_when_no_signal_fires(): void
    {
        $result = $this->engine()->match(
            ['cities' => ['Miami']],
            ['city' => 'Tampa']
        );

        $this->assertSame([], $result['overlap_signals']);
    }

    // =========================================================================
    // (16) Multiple polygons — matched_polygon_count is accurate
    // =========================================================================

    /** @test */
    public function it_counts_correctly_when_point_is_inside_multiple_polygons(): void
    {
        // Two overlapping squares centered around (0.5, 0.5) and a larger square
        $squareA = self::UNIT_SQUARE;
        $squareB = [
            ['lat' => 0.0, 'lng' => 0.0],
            ['lat' => 2.0, 'lng' => 0.0],
            ['lat' => 2.0, 'lng' => 2.0],
            ['lat' => 0.0, 'lng' => 2.0],
        ];

        $result = $this->engine()->match(
            ['polygons' => [['path' => $squareA], ['path' => $squareB]]],
            ['lat' => 0.5, 'lng' => 0.5]
        );

        $this->assertTrue($result['polygon_match']);
        $this->assertSame(2, $result['matched_polygon_count']);
    }

    /** @test */
    public function it_counts_only_the_polygons_that_contain_the_point(): void
    {
        // squareA: (0,0)→(1,1); squareB far away at (10,10)→(11,11)
        $squareA = self::UNIT_SQUARE;
        $squareB = [
            ['lat' => 10.0, 'lng' => 10.0],
            ['lat' => 11.0, 'lng' => 10.0],
            ['lat' => 11.0, 'lng' => 11.0],
            ['lat' => 10.0, 'lng' => 11.0],
        ];

        $result = $this->engine()->match(
            ['polygons' => [['path' => $squareA], ['path' => $squareB]]],
            ['lat' => 0.5, 'lng' => 0.5]
        );

        $this->assertTrue($result['polygon_match']);
        $this->assertSame(1, $result['matched_polygon_count']);
    }

    // =========================================================================
    // (17) Multiple radius searches — matched_radius_count is accurate
    // =========================================================================

    /** @test */
    public function it_counts_correctly_when_property_falls_within_multiple_radius_searches(): void
    {
        // Two large circles both centered near Tampa — property at Tampa matches both
        $result = $this->engine()->match(
            [
                'radius_searches' => [
                    ['center' => ['lat' => self::TAMPA_LAT, 'lng' => self::TAMPA_LNG], 'radius_miles' => 10.0],
                    ['center' => ['lat' => self::TAMPA_LAT + 0.01, 'lng' => self::TAMPA_LNG], 'radius_miles' => 10.0],
                ],
            ],
            ['lat' => self::TAMPA_LAT, 'lng' => self::TAMPA_LNG]
        );

        $this->assertTrue($result['radius_match']);
        $this->assertSame(2, $result['matched_radius_count']);
    }

    // =========================================================================
    // (18) Polygon with fewer than 3 points is skipped gracefully
    // =========================================================================

    /** @test */
    public function it_skips_polygon_with_fewer_than_three_points(): void
    {
        $result = $this->engine()->match(
            [
                'polygons' => [
                    ['path' => [['lat' => 0.0, 'lng' => 0.0], ['lat' => 1.0, 'lng' => 0.0]]],
                ],
            ],
            ['lat' => 0.5, 'lng' => 0.5]
        );

        $this->assertContractShape($result);
        $this->assertFalse($result['polygon_match']);
        $this->assertSame(0, $result['matched_polygon_count']);
    }

    /** @test */
    public function it_skips_polygon_entries_missing_the_path_key(): void
    {
        $result = $this->engine()->match(
            ['polygons' => [['no_path' => 'here']]],
            ['lat' => 0.5, 'lng' => 0.5]
        );

        $this->assertContractShape($result);
        $this->assertFalse($result['polygon_match']);
    }

    // =========================================================================
    // (19) Radius entry with non-positive radius_miles is skipped
    // =========================================================================

    /** @test */
    public function it_skips_radius_entry_with_zero_radius_miles(): void
    {
        $result = $this->engine()->match(
            [
                'radius_searches' => [[
                    'center'       => ['lat' => self::TAMPA_LAT, 'lng' => self::TAMPA_LNG],
                    'radius_miles' => 0,
                ]],
            ],
            ['lat' => self::TAMPA_LAT, 'lng' => self::TAMPA_LNG]
        );

        $this->assertContractShape($result);
        $this->assertFalse($result['radius_match']);
        $this->assertSame(0, $result['matched_radius_count']);
    }

    /** @test */
    public function it_skips_radius_entry_with_negative_radius_miles(): void
    {
        $result = $this->engine()->match(
            [
                'radius_searches' => [[
                    'center'       => ['lat' => self::TAMPA_LAT, 'lng' => self::TAMPA_LNG],
                    'radius_miles' => -5.0,
                ]],
            ],
            ['lat' => self::TAMPA_LAT, 'lng' => self::TAMPA_LNG]
        );

        $this->assertContractShape($result);
        $this->assertFalse($result['radius_match']);
    }

    /** @test */
    public function it_skips_radius_entry_with_missing_center(): void
    {
        $result = $this->engine()->match(
            [
                'radius_searches' => [['radius_miles' => 5.0]],
            ],
            ['lat' => self::TAMPA_LAT, 'lng' => self::TAMPA_LNG]
        );

        $this->assertContractShape($result);
        $this->assertFalse($result['radius_match']);
    }

    // =========================================================================
    // (20) Mixed signals — all five fire together
    // =========================================================================

    /** @test */
    public function it_reports_all_five_overlap_signals_when_all_match(): void
    {
        // Property sits at (0.5, 0.5) inside the unit square, near Tampa also
        // For city/zip/neighborhood we use literal Tampa/33601/Hyde Park
        $result = $this->engine()->match(
            [
                'cities'          => ['Tampa'],
                'zip_codes'       => ['33601'],
                'neighborhoods'   => ['Hyde Park'],
                'polygons'        => [['path' => self::UNIT_SQUARE]],
                'radius_searches' => [[
                    'center'       => ['lat' => 0.5, 'lng' => 0.5],
                    'radius_miles' => 1.0,
                ]],
            ],
            [
                'city'         => 'Tampa',
                'zip'          => '33601',
                'neighborhood' => 'Hyde Park',
                'lat'          => 0.5,
                'lng'          => 0.5,
            ]
        );

        $this->assertContractShape($result);
        $this->assertTrue($result['city_match']);
        $this->assertTrue($result['zip_match']);
        $this->assertNotEmpty($result['matched_neighborhoods']);
        $this->assertTrue($result['polygon_match']);
        $this->assertTrue($result['radius_match']);

        $signals = $result['overlap_signals'];
        $this->assertContains('city',         $signals);
        $this->assertContains('zip',          $signals);
        $this->assertContains('neighborhood', $signals);
        $this->assertContains('polygon',      $signals);
        $this->assertContains('radius',       $signals);
        $this->assertCount(5, $signals);
    }
}
