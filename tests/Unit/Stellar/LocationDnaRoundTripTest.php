<?php

namespace Tests\Unit\Stellar;

use App\Models\BuyerCriteriaAuction;
use App\Models\User;
use App\Services\LocationDna\LocationMatchEngine;
use App\Services\Stellar\BuyerCriteriaLoader;
use App\Services\Stellar\Matching\BuyerMatchQueryBuilder;
use App\Services\Stellar\Matching\BuyerMatchResultBuilder;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\Matching\BuyerMatchService;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * LocationDnaRoundTripTest
 *
 * Verifies that:
 *   (a) A radius save in flat {address, lat, lng, radius_miles} format round-trips
 *       through BuyerCriteriaLoader correctly.
 *   (b) A polygon save round-trips with path coordinates intact.
 *   (c) An empty location_dna_preferences blob returns empty geometry arrays
 *       (simulates the "Clear All" case).
 *   (d) BuyerMatchQueryBuilder builds a bounding-box filter when a flat-format
 *       radius center is present.
 *   (e) LocationMatchEngine fires polygon_match when a property point is inside
 *       a saved polygon path.
 *   (f) LocationMatchEngine fires radius_match for the canonical flat {lat, lng}
 *       radius format.
 *   (g) LocationMatchEngine continues to fire radius_match for the legacy
 *       {center: {lat, lng}} format (backward compatibility).
 */
class LocationDnaRoundTripTest extends TestCase
{
    use DatabaseTransactions;

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeLoader(): BuyerCriteriaLoader
    {
        return new BuyerCriteriaLoader();
    }

    private function makeEngine(): LocationMatchEngine
    {
        return new LocationMatchEngine();
    }

    /**
     * Create a minimal BuyerCriteriaAuction row and return the loaded model.
     * The model has very limited $fillable so we insert via DB::table().
     * buyer_criteria_auctions has NOT NULL: user_id, buyer_id, max_price, title.
     */
    private function makeCriteriaRecord(int $userId, array $extraColumns = []): BuyerCriteriaAuction
    {
        $id = DB::table('buyer_criteria_auctions')->insertGetId(array_merge([
            'user_id'     => $userId,
            'buyer_id'    => $userId,
            'max_price'   => 500000,
            'title'       => 'Test Buyer Criteria',
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ], $extraColumns));

        return BuyerCriteriaAuction::with('meta')->findOrFail($id);
    }

    /**
     * Attach a meta key-value to a BuyerCriteriaAuction row.
     * buyer_criteria_auction_metas has NO timestamps (id, fk, meta_key, meta_value only).
     */
    private function setMeta(BuyerCriteriaAuction $record, string $key, string $value): void
    {
        DB::table('buyer_criteria_auction_metas')->insert([
            'buyer_criteria_auction_id' => $record->id,
            'meta_key'                  => $key,
            'meta_value'                => $value,
        ]);
        $record->load('meta');
    }

    private function makeUserId(): int
    {
        return DB::table('users')->insertGetId([
            'first_name'  => 'Test',
            'last_name'   => 'Buyer',
            'name'        => 'Test Buyer',
            'short_id'    => 'ldna-' . uniqid(),
            'user_name'   => 'ldna_user_' . uniqid(),
            'email'       => 'ldna_' . uniqid() . '@example.com',
            'password'    => bcrypt('password'),
            'user_type'   => 'buyer',
            'is_approved' => false,
            'is_super'    => false,
            'is_deleted'  => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    // =========================================================================
    // (a) Radius round-trip — flat format
    // =========================================================================

    /** @test */
    public function it_round_trips_radius_search_in_flat_format_through_buyer_criteria_loader(): void
    {
        $userId = $this->makeUserId();
        $record = $this->makeCriteriaRecord($userId);

        $ldna = [
            'cities'          => [],
            'zip_codes'       => [],
            'neighborhoods'   => [],
            'polygons'        => [],
            'radius_searches' => [
                [
                    'address'      => '123 Main St, Orlando, FL',
                    'lat'          => 28.5383,
                    'lng'          => -81.3792,
                    'radius_miles' => 7.5,
                ],
            ],
            'flexible_location' => false,
            'location_notes'    => '',
        ];

        $this->setMeta($record, 'location_dna_preferences', json_encode($ldna));
        $this->setMeta($record, 'property_types', json_encode(['Residential']));

        $data = $this->makeLoader()->load($userId);

        $this->assertNotNull($data, 'Loader should return data for a valid record');
        $this->assertCount(1, $data['radius_searches'], 'Exactly one radius search expected');

        $rs = $data['radius_searches'][0];
        $this->assertArrayHasKey('lat', $rs, 'Flat format must have top-level lat key');
        $this->assertArrayHasKey('lng', $rs, 'Flat format must have top-level lng key');
        $this->assertArrayHasKey('radius_miles', $rs, 'radius_miles key expected');
        $this->assertArrayHasKey('address', $rs, 'address key expected after round-trip');
        $this->assertEqualsWithDelta(28.5383, (float) $rs['lat'], 0.0001);
        $this->assertEqualsWithDelta(-81.3792, (float) $rs['lng'], 0.0001);
        $this->assertEqualsWithDelta(7.5, (float) $rs['radius_miles'], 0.001);
        $this->assertSame('123 Main St, Orlando, FL', $rs['address']);
    }

    // =========================================================================
    // (b) Polygon round-trip
    // =========================================================================

    /** @test */
    public function it_round_trips_polygon_path_through_buyer_criteria_loader(): void
    {
        $userId = $this->makeUserId();
        $record = $this->makeCriteriaRecord($userId);

        $path = [
            ['lat' => 27.9, 'lng' => -82.5],
            ['lat' => 28.1, 'lng' => -82.5],
            ['lat' => 28.1, 'lng' => -82.3],
            ['lat' => 27.9, 'lng' => -82.3],
        ];

        $ldna = [
            'cities'          => [],
            'zip_codes'       => [],
            'neighborhoods'   => [],
            'polygons'        => [
                ['label' => 'Custom Area 1', 'path' => $path],
            ],
            'radius_searches' => [],
            'flexible_location' => false,
            'location_notes'    => '',
        ];

        $this->setMeta($record, 'location_dna_preferences', json_encode($ldna));
        $this->setMeta($record, 'property_types', json_encode(['Residential']));

        // Polygons alone do not satisfy the hasLocation check in the controller,
        // but the loader should still extract them into the returned array.
        $data = $this->makeLoader()->load($userId);

        $this->assertNotNull($data);
        $this->assertCount(1, $data['polygons'], 'Exactly one polygon expected');

        $poly = $data['polygons'][0];
        $this->assertArrayHasKey('path', $poly);
        $this->assertCount(4, $poly['path'], 'All 4 path coordinates must be preserved');

        foreach ($path as $i => $expectedPoint) {
            $this->assertEqualsWithDelta($expectedPoint['lat'], (float) $poly['path'][$i]['lat'], 0.0001);
            $this->assertEqualsWithDelta($expectedPoint['lng'], (float) $poly['path'][$i]['lng'], 0.0001);
        }
    }

    // =========================================================================
    // (c) Clear All — empty blob yields empty geometry arrays
    // =========================================================================

    /** @test */
    public function it_returns_empty_geometry_when_location_dna_blob_has_no_geometry(): void
    {
        $userId = $this->makeUserId();
        $record = $this->makeCriteriaRecord($userId);

        $ldna = [
            'cities'          => ['Tampa'],
            'zip_codes'       => [],
            'neighborhoods'   => [],
            'polygons'        => [],
            'radius_searches' => [],
            'flexible_location' => false,
            'location_notes'    => '',
        ];

        $this->setMeta($record, 'location_dna_preferences', json_encode($ldna));
        $this->setMeta($record, 'property_types', json_encode(['Residential']));

        $data = $this->makeLoader()->load($userId);

        $this->assertNotNull($data);
        $this->assertEmpty($data['polygons'], 'polygons must be empty after Clear All');
        $this->assertEmpty($data['radius_searches'], 'radius_searches must be empty after Clear All');
        $this->assertSame(['Tampa'], $data['preferred_cities'], 'City tags must still be present');
    }

    // =========================================================================
    // (d) BuyerMatchQueryBuilder — bounding box for flat radius format
    // =========================================================================

    /** @test */
    public function it_applies_bounding_box_filter_for_flat_radius_format(): void
    {
        if (!Schema::hasTable('bridge_properties')) {
            $this->markTestSkipped('bridge_properties table does not exist in this environment.');
        }

        // Insert a listing that is clearly INSIDE a 100-mile radius around Tampa
        $insideKey  = 'LDNA-INSIDE-'  . uniqid();
        // Insert a listing that is clearly OUTSIDE (far north Georgia)
        $outsideKey = 'LDNA-OUTSIDE-' . uniqid();

        DB::table('bridge_properties')->insert([
            [
                'listing_key'             => $insideKey,
                'listing_id'              => 'LID-' . uniqid(),
                'standard_status'         => 'Active',
                'property_type'           => 'Residential',
                'list_price'              => 350000,
                'city'                    => 'Tampa',
                'state_or_province'       => 'FL',
                'postal_code'             => '33601',
                'latitude'                => 27.9506,
                'longitude'               => -82.4572,
                'bedrooms_total'          => 3,
                'bathrooms_total_integer' => 2,
                'living_area'             => 1800,
                'senior_community_yn'     => false,
                'raw_json'                => json_encode(['IDXParticipationYN' => true]),
                'created_at'              => now(),
                'updated_at'              => now(),
            ],
            [
                'listing_key'             => $outsideKey,
                'listing_id'              => 'LID-' . uniqid(),
                'standard_status'         => 'Active',
                'property_type'           => 'Residential',
                'list_price'              => 350000,
                'city'                    => 'Atlanta',
                'state_or_province'       => 'GA',
                'postal_code'             => '30301',
                'latitude'                => 33.749,
                'longitude'               => -84.388,
                'bedrooms_total'          => 3,
                'bathrooms_total_integer' => 2,
                'living_area'             => 1800,
                'senior_community_yn'     => false,
                'raw_json'                => json_encode(['IDXParticipationYN' => true]),
                'created_at'              => now(),
                'updated_at'              => now(),
            ],
        ]);

        // Flat format radius centered on Tampa, FL — 100 mile radius
        $criteria = new BuyerCriteriaPayload([
            'property_types'      => ['Residential'],
            'is_55_plus_eligible' => false,
            'radius_searches'     => [
                ['lat' => 27.9506, 'lng' => -82.4572, 'radius_miles' => 100],
            ],
        ]);

        $service = new BuyerMatchService(
            new BuyerMatchQueryBuilder(),
            new BuyerMatchScorer(),
            new BuyerMatchResultBuilder()
        );

        $results = $service->match($criteria, 200);
        $keys    = $results->pluck('listingKey')->all();

        $this->assertContains($insideKey,  $keys, 'Tampa listing must be inside 100-mile Tampa radius');
        $this->assertNotContains($outsideKey, $keys, 'Atlanta listing must be outside 100-mile Tampa radius');
    }

    // =========================================================================
    // (e) LocationMatchEngine — polygon_match fires for point inside polygon
    // =========================================================================

    /** @test */
    public function it_fires_polygon_match_when_property_is_inside_saved_polygon(): void
    {
        // Unit square in degrees for deterministic geometry
        $square = [
            ['lat' => 0.0, 'lng' => 0.0],
            ['lat' => 1.0, 'lng' => 0.0],
            ['lat' => 1.0, 'lng' => 1.0],
            ['lat' => 0.0, 'lng' => 1.0],
        ];

        $result = $this->makeEngine()->match(
            [
                'polygons' => [
                    ['label' => 'Test Square', 'path' => $square],
                ],
            ],
            [
                'lat' => 0.5,
                'lng' => 0.5,
            ]
        );

        $this->assertTrue($result['polygon_match'], 'polygon_match must fire for a point inside the saved polygon');
        $this->assertGreaterThan(0, $result['matched_polygon_count']);
        $this->assertContains('polygon', $result['overlap_signals']);
    }

    // =========================================================================
    // (f) LocationMatchEngine — radius_match fires for flat {lat, lng} format
    // =========================================================================

    /** @test */
    public function it_fires_radius_match_for_flat_lat_lng_format(): void
    {
        // Tampa, FL — property is AT the center, so distance = 0 < radius
        $result = $this->makeEngine()->match(
            [
                'radius_searches' => [
                    ['lat' => 27.9506, 'lng' => -82.4572, 'radius_miles' => 10],
                ],
            ],
            [
                'lat' => 27.9506,
                'lng' => -82.4572,
            ]
        );

        $this->assertTrue($result['radius_match'], 'radius_match must fire for flat-format radius search');
        $this->assertGreaterThan(0, $result['matched_radius_count']);
        $this->assertContains('radius', $result['overlap_signals']);
    }

    // =========================================================================
    // (g) LocationMatchEngine — backward compat with legacy center:{lat,lng} format
    // =========================================================================

    /** @test */
    public function it_fires_radius_match_for_legacy_nested_center_format(): void
    {
        $result = $this->makeEngine()->match(
            [
                'radius_searches' => [
                    [
                        'center'       => ['lat' => 27.9506, 'lng' => -82.4572],
                        'radius_miles' => 10,
                    ],
                ],
            ],
            [
                'lat' => 27.9506,
                'lng' => -82.4572,
            ]
        );

        $this->assertTrue($result['radius_match'], 'radius_match must fire for legacy nested center format');
        $this->assertGreaterThan(0, $result['matched_radius_count']);
    }

    // =========================================================================
    // BuyerCriteriaLoader — falls back to legacy separate meta keys
    // =========================================================================

    /** @test */
    public function it_falls_back_to_separate_meta_keys_when_ldna_blob_is_absent(): void
    {
        $userId = $this->makeUserId();
        $record = $this->makeCriteriaRecord($userId);

        // No location_dna_preferences blob — use legacy separate keys
        $this->setMeta($record, 'preferred_cities',   json_encode(['St. Petersburg']));
        $this->setMeta($record, 'preferred_zip_codes', json_encode(['33701']));
        $this->setMeta($record, 'radius_searches',    json_encode([
            ['lat' => 27.7676, 'lng' => -82.6403, 'radius_miles' => 5],
        ]));
        $this->setMeta($record, 'property_types', json_encode(['Residential']));

        $data = $this->makeLoader()->load($userId);

        $this->assertNotNull($data);
        $this->assertContains('St. Petersburg', $data['preferred_cities']);
        $this->assertContains('33701',           $data['preferred_zip_codes']);
        $this->assertCount(1, $data['radius_searches']);
        $this->assertEqualsWithDelta(27.7676, (float) $data['radius_searches'][0]['lat'], 0.0001);
    }

    // =========================================================================
    // Clear All regression: explicit empty arrays in blob must silence legacy keys
    // =========================================================================

    /** @test */
    public function it_returns_empty_geometry_when_blob_has_empty_arrays_even_if_legacy_keys_are_populated(): void
    {
        // This is the critical "Clear All" regression: a user cleared the map,
        // save wrote radius_searches:[] and polygons:[] into the LDNA blob.
        // Legacy meta keys still have old radius/polygon data (not yet overwritten).
        // The loader MUST honor the blob's explicit [] and not resurrect stale geometry.

        $userId = $this->makeUserId();
        $record = $this->makeCriteriaRecord($userId);

        // LDNA blob saved AFTER clear-all — explicit empty arrays for geometry
        $ldna = [
            'cities'          => ['Tampa'],
            'zip_codes'       => [],
            'neighborhoods'   => [],
            'polygons'        => [],         // user cleared polygons
            'radius_searches' => [],         // user cleared radius circles
            'flexible_location' => false,
            'location_notes'    => '',
        ];
        $this->setMeta($record, 'location_dna_preferences', json_encode($ldna));

        // Stale legacy meta still present from before the blob was introduced
        $this->setMeta($record, 'radius_searches', json_encode([
            ['lat' => 28.0, 'lng' => -81.0, 'radius_miles' => 10],
        ]));
        $this->setMeta($record, 'polygons', json_encode([
            ['label' => 'Old Polygon', 'path' => [
                ['lat' => 27.0, 'lng' => -82.0],
                ['lat' => 27.5, 'lng' => -82.0],
                ['lat' => 27.5, 'lng' => -81.5],
            ]],
        ]));
        $this->setMeta($record, 'property_types', json_encode(['Residential']));

        $data = $this->makeLoader()->load($userId);

        $this->assertNotNull($data);
        $this->assertEmpty(
            $data['polygons'],
            'Stale legacy polygon meta must NOT be loaded when blob has explicit polygons:[]'
        );
        $this->assertEmpty(
            $data['radius_searches'],
            'Stale legacy radius_searches meta must NOT be loaded when blob has explicit radius_searches:[]'
        );
        // City tags from the blob should still come through
        $this->assertContains('Tampa', $data['preferred_cities']);
    }

    // =========================================================================
    // BuyerCriteriaLoader — blob cities/zips take priority over separate keys
    // =========================================================================

    /** @test */
    public function it_prefers_ldna_blob_cities_over_separate_preferred_cities_meta(): void
    {
        $userId = $this->makeUserId();
        $record = $this->makeCriteriaRecord($userId);

        $ldna = [
            'cities'          => ['Orlando', 'Tampa'],
            'zip_codes'       => ['32801'],
            'radius_searches' => [],
            'polygons'        => [],
            'flexible_location' => false,
            'location_notes'    => '',
        ];

        $this->setMeta($record, 'location_dna_preferences', json_encode($ldna));
        // This legacy key should be ignored because the blob has data
        $this->setMeta($record, 'preferred_cities', json_encode(['Miami']));
        $this->setMeta($record, 'property_types', json_encode(['Residential']));

        $data = $this->makeLoader()->load($userId);

        $this->assertNotNull($data);
        $this->assertContains('Orlando', $data['preferred_cities'], 'Blob cities must win over separate meta');
        $this->assertContains('Tampa',   $data['preferred_cities']);
        $this->assertNotContains('Miami', $data['preferred_cities'], 'Legacy separate key must be ignored when blob has data');
        $this->assertContains('32801',   $data['preferred_zip_codes']);
    }
}
