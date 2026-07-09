<?php

namespace Tests\Unit\Services\LocationDna;

use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use App\Services\LocationDna\LocationDnaPoiTileCache;
use App\Services\LocationDna\LocationDnaVersionService;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * LocationDnaPoiDistanceServiceTest
 *
 * Verifies LocationDnaPoiDistanceService::calculateForListing() using a SQLite
 * in-memory database. The Guzzle HTTP client is mocked to avoid real API calls.
 *
 * Output contract (Phase C approved — 8 keys):
 *   success, status, listing_type, listing_id, results, error, source_lat, source_lng
 *
 * v3 additions (task #3200):
 *   - Category grouping: park/waterfront_park, gym/fitness_center, beach/beach_access
 *     share one API call each → 16 fresh calls per full tile miss (down from 19).
 *   - Tile cache: opt-in via config; disabled when tile_precision is absent/empty.
 *   - Stats persisted to location_dna_poi_run_stats after each completed run.
 *
 * Test coverage:
 *   (1)  Missing Phase B record                → success false, status 'skipped', error set
 *   (2)  geocode_status !== 'geocoded'         → success false, status 'skipped', error set
 *   (3)  Missing coordinates on Phase B record → success false, status 'skipped', error set
 *   (4)  Missing API key                       → success false, status 'failed', error='missing_google_api_key'
 *   (5)  API zero results for a category       → that category persisted as 'not_found', run continues
 *   (6)  API exception for a category          → that category persisted as 'error', run continues
 *   (7)  Success path                          → all categories attempted, rows persisted, success true
 *   (8)  All categories attempted              → one row per CATEGORIES key in DB
 *   (9)  Cache reuse                           → source coordinates match, no HTTP call, status 'cached'
 *   (10) Coordinate change recalculation       → old rows deleted, new rows written, HTTP calls made
 *   (11) Output shape consistency              → all eight keys present in every return path
 *   (12) source_lat/source_lng on every row    → rows in DB carry the listing's geocoded coordinates
 *   (13) travel_time_minutes reserved null     → column left null for future phase
 *   (14) No OpenAI/AI imports                  → service file does not import OpenAI or AI service classes
 *   (15) No marketing/PropertyDna imports      → service file does not import banned pipeline classes
 *   (16) No routes/controllers/Blade/Livewire  → no such files created under the governed paths
 *   (17) Phase B service unmodified            → LocationDnaGeocodeService.php unchanged hash/content check
 *   (v3-G1) Category grouping reduces API calls → 16 calls instead of 19 on full tile miss
 *   (v3-G2) Secondary categories produce DB rows → waterfront_park, fitness_center, beach_access rows exist
 *   (v3-G3) Tile cache hit path               → cache stores candidates, second listing skips API call
 *   (v3-G4) Tile cache miss path              → when disabled, every listing fetches fresh
 *   (v3-G5) Stats persisted                   → location_dna_poi_run_stats row written after completed run
 */
class LocationDnaPoiDistanceServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const LISTING_TYPE = 'seller_agent_auction';
    private const LISTING_ID   = 55;
    private const SOURCE_LAT   = 27.9506;
    private const SOURCE_LNG   = -82.4572;

    private const CONTRACT_KEYS = [
        'success', 'status', 'listing_type', 'listing_id',
        'results', 'error', 'source_lat', 'source_lng',
    ];

    private ClientInterface $mockClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockClient = $this->createMock(ClientInterface::class);
        config([
            // Phase 0 / S2: this class is a provider-mocked test that deliberately
            // exercises the Google Nearby Search path. config/google_places.php keeps
            // the kill switch OFF everywhere by default; such tests must opt in
            // explicitly. Every other test in the suite stays behind the switch.
            'google_places.enabled'                 => true,
            'services.google.places_key'            => 'test-poi-api-key',
            'location_dna.poi.tile_precision'        => null,
            'cache.default'                          => 'array',
        ]);
        Cache::flush();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeService(?LocationDnaPoiTileCache $tileCache = null): LocationDnaPoiDistanceService
    {
        return new LocationDnaPoiDistanceService($this->mockClient, null, null, $tileCache);
    }

    /**
     * Current fetch version, matching what the service computes at run time.
     * Cached-path seeds stamp this on their rows to represent the post-backfill
     * steady state (Stage E0), where versioned rows are eligible for reuse.
     */
    private function currentFetchVersion(): string
    {
        return (new LocationDnaVersionService())->fetchVersion();
    }

    /**
     * Expected number of API calls per full tile-miss run after category grouping.
     * = total CATEGORIES (19) − secondary categories in CATEGORY_GROUPS (3)
     */
    private function expectedApiCallCount(): int
    {
        return count(LocationDnaPoiDistanceService::CATEGORIES) - count(LocationDnaPoiDistanceService::CATEGORY_GROUPS);
    }

    /** Create a fully-geocoded Phase B record for the default listing. */
    private function createGeocodedDnaRecord(
        string $listingType = self::LISTING_TYPE,
        int    $listingId   = self::LISTING_ID,
        float  $lat         = self::SOURCE_LAT,
        float  $lng         = self::SOURCE_LNG,
    ): PropertyLocationDna {
        return PropertyLocationDna::create([
            'listing_type'   => $listingType,
            'listing_id'     => $listingId,
            'source_address' => '123 Main St',
            'source_city'    => 'Tampa',
            'source_state'   => 'FL',
            'geocoded_lat'   => $lat,
            'geocoded_lng'   => $lng,
            'geocode_source' => 'google',
            'geocode_status' => 'geocoded',
            'geocoded_at'    => now(),
        ]);
    }

    /** Build a fake Google Places Nearby Search response with one result. */
    private function makePlacesResponse(
        float  $lat  = 27.9600,
        float  $lng  = -82.4600,
        string $name = 'Test Place',
    ): Response {
        $body = json_encode([
            'status'  => 'OK',
            'results' => [
                [
                    'name'     => $name,
                    'vicinity' => '456 Test Ave, Tampa',
                    'geometry' => [
                        'location' => ['lat' => $lat, 'lng' => $lng],
                    ],
                ],
            ],
        ]);

        return new Response(200, [], $body);
    }

    /** Build a fake zero-results response. */
    private function makeZeroResultsResponse(): Response
    {
        return new Response(200, [], json_encode([
            'status'  => 'ZERO_RESULTS',
            'results' => [],
        ]));
    }

    private function assertContractShape(array $result): void
    {
        foreach (self::CONTRACT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Output contract key '{$key}' is missing");
        }
        $this->assertSame(
            count(self::CONTRACT_KEYS),
            count($result),
            'Output must contain exactly the approved contract keys',
        );
    }

    // =========================================================================
    // (1) Missing Phase B record → skipped
    // =========================================================================

    /** @test */
    public function it_returns_skipped_when_no_phase_b_record_exists(): void
    {
        $this->mockClient->expects($this->never())->method('request');

        $result = $this->makeService()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertFalse($result['success']);
        $this->assertSame('skipped', $result['status']);
        $this->assertNotEmpty($result['error']);
        $this->assertEmpty($result['results']);
        $this->assertNull($result['source_lat']);
        $this->assertNull($result['source_lng']);
    }

    // =========================================================================
    // (2) geocode_status !== 'geocoded' → skipped
    // =========================================================================

    /** @test */
    public function it_returns_skipped_when_phase_b_record_is_not_geocoded(): void
    {
        PropertyLocationDna::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'source_address' => '123 Main St',
            'source_city'    => 'Tampa',
            'source_state'   => 'FL',
            'geocode_status' => 'failed',
        ]);

        $this->mockClient->expects($this->never())->method('request');

        $result = $this->makeService()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertFalse($result['success']);
        $this->assertSame('skipped', $result['status']);
        $this->assertStringContainsString('failed', $result['error']);
    }

    // =========================================================================
    // (3) Missing coordinates on Phase B record → skipped
    // =========================================================================

    /** @test */
    public function it_returns_skipped_when_phase_b_record_has_no_coordinates(): void
    {
        PropertyLocationDna::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'source_address' => '123 Main St',
            'source_city'    => 'Tampa',
            'source_state'   => 'FL',
            'geocode_status' => 'geocoded',
            'geocoded_lat'   => null,
            'geocoded_lng'   => null,
        ]);

        $this->mockClient->expects($this->never())->method('request');

        $result = $this->makeService()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertFalse($result['success']);
        $this->assertSame('skipped', $result['status']);
        $this->assertNotEmpty($result['error']);
    }

    // =========================================================================
    // (4) Missing API key → failed without making HTTP calls
    // =========================================================================

    /** @test */
    public function it_returns_failed_when_google_api_key_is_blank(): void
    {
        $this->createGeocodedDnaRecord();
        config(['services.google.places_key' => '']);

        $this->mockClient->expects($this->never())->method('request');

        $result = $this->makeService()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertFalse($result['success']);
        $this->assertSame('failed', $result['status']);
        $this->assertSame('missing_google_api_key', $result['error']);
        $this->assertEmpty($result['results']);
    }

    // =========================================================================
    // (5) API zero results for one category → not_found row, run continues
    // =========================================================================

    /** @test */
    public function it_persists_not_found_row_and_continues_when_api_returns_zero_results(): void
    {
        $this->createGeocodedDnaRecord();

        $expectedCallCount = $this->expectedApiCallCount();

        // Return zero results for the first call, success for all others
        $this->mockClient
            ->expects($this->exactly($expectedCallCount))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->makeZeroResultsResponse(),
                ...array_fill(0, $expectedCallCount - 1, $this->makePlacesResponse()),
            );

        $result = $this->makeService()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertTrue($result['success']);
        $this->assertSame('completed', $result['status']);

        // At least one row with not_found status
        $notFound = PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->where('status', 'not_found')
            ->count();

        $this->assertGreaterThanOrEqual(1, $notFound);

        // Total rows >= total categories (top_rated_dining derived row adds at least one extra)
        $categoryCount = count(LocationDnaPoiDistanceService::CATEGORIES);
        $totalRows = PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->count();

        $this->assertGreaterThanOrEqual($categoryCount, $totalRows);
    }

    // =========================================================================
    // (6) API exception for one category → error row, run continues
    // =========================================================================

    /** @test */
    public function it_persists_error_row_and_continues_when_api_throws_for_one_category(): void
    {
        $this->createGeocodedDnaRecord();

        $expectedCallCount = $this->expectedApiCallCount();

        $this->mockClient
            ->expects($this->exactly($expectedCallCount))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new \RuntimeException('API timeout')),
                ...array_fill(0, $expectedCallCount - 1, $this->makePlacesResponse()),
            );

        $result = $this->makeService()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertTrue($result['success']);
        $this->assertSame('completed', $result['status']);

        $errorRows = PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->where('status', 'error')
            ->count();

        $this->assertGreaterThanOrEqual(1, $errorRows);

        $categoryCount = count(LocationDnaPoiDistanceService::CATEGORIES);
        $totalRows = PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->count();

        $this->assertGreaterThanOrEqual($categoryCount, $totalRows);
    }

    // =========================================================================
    // (7) Success path — all categories found, rows persisted, success true
    // =========================================================================

    /** @test */
    public function it_succeeds_and_persists_all_category_rows_on_happy_path(): void
    {
        $this->createGeocodedDnaRecord();

        $expectedCallCount = $this->expectedApiCallCount();

        $this->mockClient
            ->expects($this->exactly($expectedCallCount))
            ->method('request')
            ->willReturn($this->makePlacesResponse(27.9600, -82.4600, 'Nearby Place'));

        $result = $this->makeService()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertTrue($result['success']);
        $this->assertSame('completed', $result['status']);
        $this->assertNull($result['error']);
        $this->assertEqualsWithDelta(self::SOURCE_LAT, $result['source_lat'], 0.0001);
        $this->assertEqualsWithDelta(self::SOURCE_LNG, $result['source_lng'], 0.0001);
        // results includes all persisted rows (multi-candidate + derived top_rated_dining)
        $categoryCount = count(LocationDnaPoiDistanceService::CATEGORIES);
        $this->assertGreaterThanOrEqual($categoryCount, count($result['results']));
    }

    // =========================================================================
    // (8) All categories attempted — one row per CATEGORIES key in DB
    // =========================================================================

    /** @test */
    public function it_attempts_every_defined_category_and_persists_one_row_each(): void
    {
        $this->createGeocodedDnaRecord();

        $expectedCategories = array_keys(LocationDnaPoiDistanceService::CATEGORIES);

        $this->mockClient
            ->method('request')
            ->willReturn($this->makePlacesResponse());

        $this->makeService()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        foreach ($expectedCategories as $category) {
            $this->assertDatabaseHas('property_location_pois', [
                'listing_type' => self::LISTING_TYPE,
                'listing_id'   => self::LISTING_ID,
                'poi_category' => $category,
            ]);
        }

        $categoryCount = count($expectedCategories);
        $totalRows = PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->count();

        // >= categoryCount: derived top_rated_dining row(s) are stored in addition to CATEGORIES rows
        $this->assertGreaterThanOrEqual($categoryCount, $totalRows);
    }

    // =========================================================================
    // (9) Cache reuse — source coordinates match, no HTTP call, status 'cached'
    // =========================================================================

    /** @test */
    public function it_returns_cached_rows_without_api_call_when_coordinates_match(): void
    {
        $this->createGeocodedDnaRecord();

        // Pre-populate POI rows with matching source coordinates
        foreach (array_keys(LocationDnaPoiDistanceService::CATEGORIES) as $category) {
            PropertyLocationPoi::create([
                'listing_type'   => self::LISTING_TYPE,
                'listing_id'     => self::LISTING_ID,
                'poi_category'   => $category,
                'poi_subtype'    => 'Test',
                'poi_name'       => 'Cached Place',
                'source_lat'     => self::SOURCE_LAT,
                'source_lng'     => self::SOURCE_LNG,
                'distance_miles' => 0.5,
                'data_source'    => 'google_places',
                'pois_fetch_version' => $this->currentFetchVersion(),
                'status'         => 'found',
                'calculated_at'  => now()->subHour(),
            ]);
        }

        $this->mockClient->expects($this->never())->method('request');

        $result = $this->makeService()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertTrue($result['success']);
        $this->assertSame('cached', $result['status']);
        $this->assertNull($result['error']);
        $this->assertNotEmpty($result['results']);
    }

    // =========================================================================
    // (10) Coordinate change recalculation — old rows deleted, new rows written
    // =========================================================================

    /** @test */
    public function it_clears_old_rows_and_recalculates_when_coordinates_change(): void
    {
        $oldLat = 10.0;
        $oldLng = -10.0;

        // Phase B record has new coordinates
        $this->createGeocodedDnaRecord(lat: self::SOURCE_LAT, lng: self::SOURCE_LNG);

        // Pre-populate POI rows with OLD coordinates
        PropertyLocationPoi::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'poi_category'   => 'grocery_store',
            'poi_subtype'    => 'Grocery Store',
            'poi_name'       => 'Old Grocery',
            'source_lat'     => $oldLat,
            'source_lng'     => $oldLng,
            'distance_miles' => 99.9,
            'data_source'    => 'google_places',
            'status'         => 'found',
            'calculated_at'  => now()->subDay(),
        ]);

        $expectedCallCount = $this->expectedApiCallCount();

        $this->mockClient
            ->expects($this->exactly($expectedCallCount))
            ->method('request')
            ->willReturn($this->makePlacesResponse(27.9600, -82.4600));

        $result = $this->makeService()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertTrue($result['success']);
        $this->assertSame('completed', $result['status']);

        // Old grocery row with old coordinates must be gone
        $this->assertDatabaseMissing('property_location_pois', [
            'listing_type' => self::LISTING_TYPE,
            'listing_id'   => self::LISTING_ID,
            'source_lat'   => $oldLat,
        ]);

        // New rows must exist with updated source coordinates
        $newRows = PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->get();

        $categoryCount = count(LocationDnaPoiDistanceService::CATEGORIES);
        $this->assertGreaterThanOrEqual($categoryCount, $newRows->count());

        foreach ($newRows as $row) {
            $this->assertEqualsWithDelta(self::SOURCE_LAT, (float) $row->source_lat, 0.0001);
            $this->assertEqualsWithDelta(self::SOURCE_LNG, (float) $row->source_lng, 0.0001);
        }
    }

    // =========================================================================
    // (11) Output shape consistency — all eight contract keys in every path
    // =========================================================================

    /** @test */
    public function output_shape_is_consistent_across_all_return_paths(): void
    {
        // skipped path (no Phase B record)
        $skipped = $this->makeService()->calculateForListing('buyer_agent_auction', 1);
        $this->assertContractShape($skipped);

        // failed path (blank API key)
        $this->createGeocodedDnaRecord('buyer_agent_auction', 2);
        config(['services.google.places_key' => '']);
        $failed = $this->makeService()->calculateForListing('buyer_agent_auction', 2);
        $this->assertContractShape($failed);

        // completed path
        config(['services.google.places_key' => 'test-poi-api-key']);
        $this->createGeocodedDnaRecord('buyer_agent_auction', 3);
        $this->mockClient = $this->createMock(ClientInterface::class);
        $this->mockClient->method('request')->willReturn($this->makePlacesResponse());

        $completed = $this->makeService()->calculateForListing('buyer_agent_auction', 3);
        $this->assertContractShape($completed);
    }

    // =========================================================================
    // (12) source_lat/source_lng stored on every persisted row
    // =========================================================================

    /** @test */
    public function every_persisted_poi_row_carries_the_listing_source_coordinates(): void
    {
        $this->createGeocodedDnaRecord();

        $this->mockClient
            ->method('request')
            ->willReturn($this->makePlacesResponse());

        $this->makeService()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $rows = PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->get();

        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertEqualsWithDelta(self::SOURCE_LAT, (float) $row->source_lat, 0.0001,
                "Row for category '{$row->poi_category}' is missing correct source_lat");
            $this->assertEqualsWithDelta(self::SOURCE_LNG, (float) $row->source_lng, 0.0001,
                "Row for category '{$row->poi_category}' is missing correct source_lng");
        }
    }

    // =========================================================================
    // (13) travel_time_minutes is null — reserved for future phase
    // =========================================================================

    /** @test */
    public function travel_time_minutes_is_null_on_all_persisted_rows(): void
    {
        $this->createGeocodedDnaRecord();

        $this->mockClient
            ->method('request')
            ->willReturn($this->makePlacesResponse());

        $this->makeService()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $rows = PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->get();

        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertNull(
                $row->travel_time_minutes,
                "travel_time_minutes must be null (reserved for future phase)",
            );
        }
    }

    // =========================================================================
    // (13b) Category definitions: native_type and keyword strategies are correct
    // =========================================================================

    /** @test */
    public function every_category_has_required_definition_keys_and_valid_query_strategy(): void
    {
        $requiredKeys     = ['google_type', 'keyword', 'label', 'query_strategy'];
        $validStrategies  = ['native_type', 'keyword'];

        foreach (LocationDnaPoiDistanceService::CATEGORIES as $category => $meta) {
            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey($key, $meta,
                    "Category '{$category}' is missing required key '{$key}'");
            }

            $this->assertContains($meta['query_strategy'], $validStrategies,
                "Category '{$category}' has invalid query_strategy '{$meta['query_strategy']}'");

            // native_type: must have google_type set, keyword null
            if ($meta['query_strategy'] === 'native_type') {
                $this->assertNotEmpty($meta['google_type'],
                    "Native-type category '{$category}' must have a non-empty google_type");
                $this->assertNull($meta['keyword'],
                    "Native-type category '{$category}' must have keyword=null");
            }

            // keyword: must have keyword set; google_type may be null or a hint
            if ($meta['query_strategy'] === 'keyword') {
                $this->assertNotEmpty($meta['keyword'],
                    "Keyword category '{$category}' must have a non-empty keyword string");
            }
        }
    }

    /** @test */
    public function known_coastal_and_recreation_categories_are_all_present(): void
    {
        $expectedCategories = [
            'beach', 'beach_access', 'boat_ramp', 'marina',
            'waterfront_park', 'dog_park', 'golf_course',
            'shopping_center', 'fitness_center',
        ];

        foreach ($expectedCategories as $category) {
            $this->assertArrayHasKey(
                $category,
                LocationDnaPoiDistanceService::CATEGORIES,
                "Expected coastal/recreation category '{$category}' is not defined in CATEGORIES",
            );
        }
    }

    /** @test */
    public function keyword_strategy_categories_pass_keyword_param_to_api(): void
    {
        $this->createGeocodedDnaRecord();

        $allCapturedKeywords = [];

        $this->mockClient
            ->method('request')
            ->willReturnCallback(function (string $method, string $url, array $options) use (&$allCapturedKeywords) {
                if (isset($options['query']['keyword'])) {
                    $allCapturedKeywords[] = $options['query']['keyword'];
                }
                return $this->makePlacesResponse();
            });

        $this->makeService()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        // Secondary categories (waterfront_park, fitness_center, beach_access) do not make
        // their own API calls — they reuse the primary's raw candidates. Only verify
        // standalone keyword categories and primary keyword categories.
        $secondaries = array_values(LocationDnaPoiDistanceService::CATEGORY_GROUPS);

        foreach (LocationDnaPoiDistanceService::CATEGORIES as $category => $meta) {
            if ($meta['query_strategy'] !== 'keyword') {
                continue;
            }
            if (in_array($category, $secondaries, true)) {
                // Secondary category: no own API call expected
                continue;
            }
            $this->assertContains(
                $meta['keyword'],
                $allCapturedKeywords,
                "Keyword '{$meta['keyword']}' for category '{$category}' was not passed to any API call",
            );
        }
    }

    /** @test */
    public function native_type_strategy_categories_pass_type_param_without_keyword(): void
    {
        $this->createGeocodedDnaRecord();

        $allCapturedParams = [];

        $this->mockClient
            ->method('request')
            ->willReturnCallback(function (string $method, string $url, array $options) use (&$allCapturedParams) {
                $allCapturedParams[] = $options['query'] ?? [];
                return $this->makePlacesResponse();
            });

        $this->makeService()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        // Collect all google_type values that were passed with no keyword
        $typesWithoutKeyword = array_map(
            fn($p) => $p['type'] ?? null,
            array_filter($allCapturedParams, fn($p) => ! isset($p['keyword'])),
        );

        $nativeCategories = array_filter(
            LocationDnaPoiDistanceService::CATEGORIES,
            fn($meta) => $meta['query_strategy'] === 'native_type',
        );

        foreach ($nativeCategories as $category => $meta) {
            $this->assertContains(
                $meta['google_type'],
                $typesWithoutKeyword,
                "Native-type category '{$category}' (google_type={$meta['google_type']}) must send 'type' param without 'keyword'",
            );
        }
    }

    // =========================================================================
    // (14) No OpenAI/AI imports in service source
    // =========================================================================

    /** @test */
    public function service_source_contains_no_openai_or_ai_service_imports(): void
    {
        $source = file_get_contents(
            __DIR__ . '/../../../../app/Services/LocationDna/LocationDnaPoiDistanceService.php'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/^use\s+.*OpenAi/im',
            $source,
            'LocationDnaPoiDistanceService must not import any OpenAI class',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/^use\s+.*OpenAiClientService/im',
            $source,
            'LocationDnaPoiDistanceService must not import OpenAiClientService',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/^use\s+.*AiMarketingReport/im',
            $source,
            'LocationDnaPoiDistanceService must not import AiMarketingReport services',
        );
    }

    // =========================================================================
    // (15) No marketing/PropertyDna imports in service source
    // =========================================================================

    /** @test */
    public function service_source_contains_no_marketing_or_property_dna_imports(): void
    {
        $source = file_get_contents(
            __DIR__ . '/../../../../app/Services/LocationDna/LocationDnaPoiDistanceService.php'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/^use\s+.*MarketingReport/im',
            $source,
            'LocationDnaPoiDistanceService must not import MarketingReport classes',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/^use\s+.*PropertyDnaProfile/im',
            $source,
            'LocationDnaPoiDistanceService must not import PropertyDnaProfile',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/^use\s+.*DnaMarketingOutput/im',
            $source,
            'LocationDnaPoiDistanceService must not import DnaMarketingOutput',
        );
    }

    // =========================================================================
    // (16) No routes/controllers/Blade/Livewire/JS files created
    // =========================================================================

    /** @test */
    public function no_routes_controllers_blade_livewire_or_js_files_were_created(): void
    {
        $serviceDir  = __DIR__ . '/../../../../app/Services/LocationDna/';
        $httpDir     = __DIR__ . '/../../../../app/Http/';
        $routesDir   = __DIR__ . '/../../../../routes/';
        $resourcesDir = __DIR__ . '/../../../../resources/';

        // No controller for POI
        $controllerPattern = $httpDir . 'Controllers/*Poi*';
        $this->assertEmpty(
            glob($controllerPattern),
            'No POI controller files should exist under app/Http/Controllers/',
        );

        // Service directory must not contain Blade or Livewire files
        foreach ((glob($serviceDir . '*.php') ?: []) as $file) {
            $content = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression(
                '/extends\s+Component/',
                $content,
                basename($file) . ' must not extend Livewire Component',
            );
        }

        // No new route file for POI
        $poiRouteFiles = glob($routesDir . '*poi*') ?: [];
        $this->assertEmpty($poiRouteFiles, 'No POI route files should exist under routes/');

        // No Blade views for POI in resources
        $bladeFiles = glob($resourcesDir . '**/*poi*.blade.php') ?: [];
        $this->assertEmpty($bladeFiles, 'No POI Blade view files should exist');
    }

    // =========================================================================
    // (17) Phase B service is unmodified (governance: do not touch Phase B)
    // =========================================================================

    /** @test */
    public function phase_b_geocode_service_was_not_modified_by_phase_c(): void
    {
        $source = file_get_contents(
            __DIR__ . '/../../../../app/Services/LocationDna/LocationDnaGeocodeService.php'
        );

        // Phase B service must reference Phase B governance block phrase
        $this->assertStringContainsString(
            'Phase B Address / Geocode Service',
            $source,
            'Phase B service header must remain intact',
        );

        // Phase B service must NOT reference the POI service class
        $this->assertDoesNotMatchRegularExpression(
            '/LocationDnaPoiDistanceService/',
            $source,
            'Phase B LocationDnaGeocodeService must not reference Phase C POI service',
        );
    }

    // =========================================================================
    // Phase E integration — audit row written, audit failure does not mutate output
    // =========================================================================

    /** @test */
    public function poi_distance_service_writes_an_audit_row_after_a_completed_run(): void
    {
        $this->createGeocodedDnaRecord();

        $this->mockClient
            ->method('request')
            ->willReturn($this->makePlacesResponse());

        $result = $this->makeService()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue($result['success']);
        $this->assertSame('completed', $result['status']);

        $this->assertDatabaseHas('property_location_dna_audits', [
            'listing_type' => self::LISTING_TYPE,
            'listing_id'   => self::LISTING_ID,
            'event_type'   => 'poi_distance',
            'status'       => 'completed',
        ]);
    }

    /** @test */
    public function poi_distance_service_writes_an_audit_row_on_skipped_path(): void
    {
        $this->mockClient->expects($this->never())->method('request');

        $result = $this->makeService()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertFalse($result['success']);
        $this->assertSame('skipped', $result['status']);

        $this->assertDatabaseHas('property_location_dna_audits', [
            'listing_type' => self::LISTING_TYPE,
            'listing_id'   => self::LISTING_ID,
            'event_type'   => 'poi_distance',
            'status'       => 'skipped',
        ]);
    }

    // =========================================================================
    // (18) passesExclusionFilter — NULL types_json with BP name → excluded via
    //       name fallback (exclude_if_name_matches_when_types_empty)
    // =========================================================================

    /** @test */
    public function passes_exclusion_filter_excludes_bp_name_when_types_json_is_null(): void
    {
        $service = $this->makeService();

        // Simulates a pre-ranking-engine row: types_json stored as NULL in DB,
        // cast to null by Eloquent, coerced to [] in the filter.
        $place = [
            'name'  => 'BP',
            'types' => null,
        ];

        $this->assertFalse(
            $service->passesExclusionFilter('grocery_store', $place),
            'BP with NULL types should be excluded by exclude_if_name_matches_when_types_empty',
        );
    }

    /** @test */
    public function passes_exclusion_filter_does_not_exclude_real_grocery_when_types_json_is_null(): void
    {
        $service = $this->makeService();

        // A legitimate grocery store without types (sparse API response) must NOT
        // be excluded by the gas-station name guard.
        $place = [
            'name'  => 'Publix Super Market',
            'types' => null,
        ];

        $this->assertTrue(
            $service->passesExclusionFilter('grocery_store', $place),
            'Publix with NULL types should NOT be excluded by the grocery_store name guard',
        );
    }

    /** @test */
    public function passes_exclusion_filter_excludes_animal_hospital_when_types_json_is_null(): void
    {
        $service = $this->makeService();

        // Animal Hospital of Seminole scenario: types_json = NULL, name matches
        // pharmacy exclude_if_name_matches pattern (unconditional name rule).
        $place = [
            'name'  => 'Animal Hospital of Seminole',
            'types' => null,
        ];

        $this->assertFalse(
            $service->passesExclusionFilter('pharmacy', $place),
            'Animal Hospital name with NULL types should be excluded by exclude_if_name_matches',
        );
    }

    /** @test */
    public function passes_exclusion_filter_excludes_adventure_golf_when_types_json_is_null(): void
    {
        $service = $this->makeService();

        // Smugglers Cove Adventure Golf scenario: types_json = NULL, but name
        // matches golf_course exclude_if_name_matches unconditional pattern.
        $place = [
            'name'  => 'Smugglers Cove Adventure Golf',
            'types' => null,
        ];

        $this->assertFalse(
            $service->passesExclusionFilter('golf_course', $place),
            'Adventure Golf name should be excluded by golf_course exclude_if_name_matches regardless of types',
        );
    }

    // =========================================================================
    // (19) Cache path re-applies exclusions — disqualified rank-1 triggers
    //       a fresh Google fetch for that category only
    // =========================================================================

    /** @test */
    public function cache_path_deletes_stale_category_and_fetches_fresh_when_rank1_fails_exclusion(): void
    {
        $this->createGeocodedDnaRecord();

        // Pre-populate POI rows with matching source coordinates.
        // grocery_store rank-1 row has name='BP' and types_json=NULL
        // (simulates a pre-ranking-engine row that now fails the exclusion rule).
        // All other categories have clean rank-1 rows.
        $categories = array_keys(LocationDnaPoiDistanceService::CATEGORIES);

        foreach ($categories as $category) {
            $isBpRow = ($category === 'grocery_store');
            PropertyLocationPoi::create([
                'listing_type'   => self::LISTING_TYPE,
                'listing_id'     => self::LISTING_ID,
                'poi_category'   => $category,
                'poi_subtype'    => $isBpRow ? 'Grocery Store' : 'Test Place',
                'poi_name'       => $isBpRow ? 'BP' : 'Legit Place',
                'source_lat'     => self::SOURCE_LAT,
                'source_lng'     => self::SOURCE_LNG,
                'rank'           => 1,
                'distance_miles' => 0.5,
                'types_json'     => null,
                'data_source'    => 'google_places',
                'pois_fetch_version' => $this->currentFetchVersion(),
                'status'         => 'found',
                'calculated_at'  => now()->subDay(),
            ]);
        }

        // Add a top_rated_dining row (derived, should be preserved since restaurant is clean)
        PropertyLocationPoi::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'poi_category'   => 'top_rated_dining',
            'poi_subtype'    => 'Top Rated Dining',
            'poi_name'       => 'Great Restaurant',
            'source_lat'     => self::SOURCE_LAT,
            'source_lng'     => self::SOURCE_LNG,
            'rank'           => 1,
            'distance_miles' => 0.3,
            'types_json'     => ['restaurant', 'food'],
            'data_source'    => 'google_places',
            'status'         => 'found',
            'calculated_at'  => now()->subDay(),
        ]);

        // Only grocery_store should trigger a fresh fetch — one HTTP call expected.
        $this->mockClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($this->makePlacesResponse(27.9500, -82.4500, 'Publix'));

        $result = $this->makeService()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertTrue($result['success']);

        // The BP rank-1 row must be gone
        $this->assertDatabaseMissing('property_location_pois', [
            'listing_type' => self::LISTING_TYPE,
            'listing_id'   => self::LISTING_ID,
            'poi_category' => 'grocery_store',
            'poi_name'     => 'BP',
        ]);

        // A fresh grocery_store row must now exist
        $this->assertDatabaseHas('property_location_pois', [
            'listing_type' => self::LISTING_TYPE,
            'listing_id'   => self::LISTING_ID,
            'poi_category' => 'grocery_store',
            'poi_name'     => 'Publix',
        ]);

        // Other categories remain cached and untouched
        $this->assertDatabaseHas('property_location_pois', [
            'listing_type' => self::LISTING_TYPE,
            'listing_id'   => self::LISTING_ID,
            'poi_category' => 'pharmacy',
            'poi_name'     => 'Legit Place',
        ]);

        // top_rated_dining (derived, restaurant was cached) must still exist
        $this->assertDatabaseHas('property_location_pois', [
            'listing_type' => self::LISTING_TYPE,
            'listing_id'   => self::LISTING_ID,
            'poi_category' => 'top_rated_dining',
            'poi_name'     => 'Great Restaurant',
        ]);
    }

    /** @test */
    public function cache_path_refetches_categories_absent_from_db_after_external_deletion(): void
    {
        // Simulates the state after ldna:backfill-exclusions deleted grocery_store rows:
        // all other categories have DB rows with matching coordinates, grocery_store has none.
        $this->createGeocodedDnaRecord();

        foreach (array_keys(LocationDnaPoiDistanceService::CATEGORIES) as $category) {
            if ($category === 'grocery_store') {
                continue; // simulates external (backfill) deletion
            }

            PropertyLocationPoi::create([
                'listing_type'   => self::LISTING_TYPE,
                'listing_id'     => self::LISTING_ID,
                'poi_category'   => $category,
                'poi_subtype'    => 'Legit Place',
                'poi_name'       => 'Legit Place',
                'source_lat'     => self::SOURCE_LAT,
                'source_lng'     => self::SOURCE_LNG,
                'rank'           => 1,
                'distance_miles' => 0.5,
                'types_json'     => null,
                'data_source'    => 'google_places',
                'pois_fetch_version' => $this->currentFetchVersion(),
                'status'         => 'found',
                'calculated_at'  => now()->subDay(),
            ]);
        }

        // grocery_store is absent from DB → exactly 1 Google API call expected.
        $this->mockClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($this->makePlacesResponse(27.9500, -82.4500, 'Publix'));

        $result = $this->makeService()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertTrue($result['success']);

        // A fresh grocery_store row must now exist.
        $this->assertDatabaseHas('property_location_pois', [
            'listing_type' => self::LISTING_TYPE,
            'listing_id'   => self::LISTING_ID,
            'poi_category' => 'grocery_store',
            'poi_name'     => 'Publix',
        ]);

        // Other categories remain cached and untouched.
        $this->assertDatabaseHas('property_location_pois', [
            'listing_type' => self::LISTING_TYPE,
            'listing_id'   => self::LISTING_ID,
            'poi_category' => 'pharmacy',
            'poi_name'     => 'Legit Place',
        ]);
    }

    /** @test */
    public function cache_path_returns_cached_when_all_rank1_rows_pass_exclusions(): void
    {
        $this->createGeocodedDnaRecord();

        // Pre-populate with clean rows (Publix passes grocery_store rules).
        foreach (array_keys(LocationDnaPoiDistanceService::CATEGORIES) as $category) {
            PropertyLocationPoi::create([
                'listing_type'   => self::LISTING_TYPE,
                'listing_id'     => self::LISTING_ID,
                'poi_category'   => $category,
                'poi_subtype'    => 'Clean Place',
                'poi_name'       => 'Publix Super Market',
                'source_lat'     => self::SOURCE_LAT,
                'source_lng'     => self::SOURCE_LNG,
                'rank'           => 1,
                'distance_miles' => 0.5,
                'types_json'     => null,
                'data_source'    => 'google_places',
                'pois_fetch_version' => $this->currentFetchVersion(),
                'status'         => 'found',
                'calculated_at'  => now()->subDay(),
            ]);
        }

        // No HTTP calls should be made — all cached rows pass current rules.
        $this->mockClient->expects($this->never())->method('request');

        $result = $this->makeService()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertTrue($result['success']);
        $this->assertSame('cached', $result['status']);
    }

    /** @test */
    public function poi_distance_service_audit_failure_does_not_alter_return_array(): void
    {
        $this->createGeocodedDnaRecord();

        $this->mockClient
            ->method('request')
            ->willReturn($this->makePlacesResponse());

        // Inject a failing audit service
        $failingAudit = new class extends \App\Services\LocationDna\LocationDnaAuditService {
            public function record(
                string  $listingType,
                int     $listingId,
                string  $eventType,
                string  $status,
                ?string $source,
                ?array  $inputSnapshot,
                ?array  $outputSnapshot,
                ?string $error,
            ): \App\Models\PropertyLocationDnaAudit {
                throw new \RuntimeException('Audit service is down');
            }
        };

        $service = new \App\Services\LocationDna\LocationDnaPoiDistanceService($this->mockClient, $failingAudit);

        $result = $service->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        // Return value must be identical regardless of audit failure
        $this->assertContractShape($result);
        $this->assertTrue($result['success']);
        $this->assertSame('completed', $result['status']);
        $this->assertNull($result['error']);
        $this->assertNotEmpty($result['results']);
    }

    // =========================================================================
    // (v3-G1) Category grouping — 16 API calls instead of 19
    // =========================================================================

    /** @test */
    public function category_grouping_reduces_api_calls_to_16_on_full_tile_miss(): void
    {
        $this->createGeocodedDnaRecord();

        $capturedCallCount = 0;
        $this->mockClient
            ->method('request')
            ->willReturnCallback(function () use (&$capturedCallCount) {
                $capturedCallCount++;
                return $this->makePlacesResponse();
            });

        $this->makeService()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $totalCategories      = count(LocationDnaPoiDistanceService::CATEGORIES);
        $secondaryCategories  = count(LocationDnaPoiDistanceService::CATEGORY_GROUPS);
        $expectedCalls        = $totalCategories - $secondaryCategories;

        $this->assertSame(
            $expectedCalls,
            $capturedCallCount,
            "Expected {$expectedCalls} API calls (19 categories − 3 secondaries), got {$capturedCallCount}",
        );
    }

    /** @test */
    public function category_groups_constant_has_exactly_three_entries(): void
    {
        $this->assertCount(3, LocationDnaPoiDistanceService::CATEGORY_GROUPS,
            'CATEGORY_GROUPS must declare exactly three primary→secondary pairs');

        $expectedPairs = [
            'park'  => 'waterfront_park',
            'gym'   => 'fitness_center',
            'beach' => 'beach_access',
        ];

        $this->assertSame($expectedPairs, LocationDnaPoiDistanceService::CATEGORY_GROUPS);
    }

    // =========================================================================
    // (v3-G2) Secondary categories still produce DB rows (via preloaded candidates)
    // =========================================================================

    /** @test */
    public function secondary_grouped_categories_produce_db_rows_without_own_api_call(): void
    {
        $this->createGeocodedDnaRecord();

        $this->mockClient
            ->method('request')
            ->willReturn($this->makePlacesResponse());

        $this->makeService()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $secondaryCategories = array_values(LocationDnaPoiDistanceService::CATEGORY_GROUPS);

        foreach ($secondaryCategories as $category) {
            $rowExists = PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
                ->where('listing_id', self::LISTING_ID)
                ->where('poi_category', $category)
                ->exists();

            $this->assertTrue($rowExists,
                "Secondary grouped category '{$category}' must still produce a DB row");
        }
    }

    // =========================================================================
    // (v3-G3) Tile cache hit path — second call skips API
    // =========================================================================

    /** @test */
    public function tile_cache_hit_skips_api_call_for_same_tile(): void
    {
        config(['location_dna.poi.tile_precision' => '0.005']);

        // Create two listings at positions that fall in the same 0.005° tile.
        // Both lat/lng pairs map to the same tile floor: lat→27.95, lng→-82.46.
        // floor(27.9506/0.005)*0.005 = 27.950, floor(27.9507/0.005)*0.005 = 27.950
        // floor(-82.4572/0.005)*0.005 = -82.460, floor(-82.4573/0.005)*0.005 = -82.460
        $this->createGeocodedDnaRecord(
            listingType: self::LISTING_TYPE,
            listingId:   100,
            lat:         27.9506,
            lng:         -82.4572,
        );
        $this->createGeocodedDnaRecord(
            listingType: self::LISTING_TYPE,
            listingId:   101,
            lat:         27.9507,
            lng:         -82.4573,
        );

        $tileCache = new LocationDnaPoiTileCache();
        $this->assertTrue($tileCache->isEnabled(), 'Tile cache must be enabled for this test');

        $firstCallCount  = 0;
        $secondCallCount = 0;

        // First listing — populates tile cache
        $this->mockClient
            ->method('request')
            ->willReturnCallback(function () use (&$firstCallCount) {
                $firstCallCount++;
                return $this->makePlacesResponse();
            });

        $service1 = new LocationDnaPoiDistanceService($this->mockClient, null, null, $tileCache);
        $result1  = $service1->calculateForListing(self::LISTING_TYPE, 100);
        $this->assertTrue($result1['success']);

        // Second listing at same tile — must not trigger new API calls beyond what the
        // grouping savings already cover. Since both listings are in the same tile,
        // the second listing should have cache hits.
        $mockClient2 = $this->createMock(ClientInterface::class);
        $mockClient2->method('request')
            ->willReturnCallback(function () use (&$secondCallCount) {
                $secondCallCount++;
                return $this->makePlacesResponse();
            });

        $service2 = new LocationDnaPoiDistanceService($mockClient2, null, null, $tileCache);
        $result2  = $service2->calculateForListing(self::LISTING_TYPE, 101);
        $this->assertTrue($result2['success']);

        // First run must have made API calls (cache was cold)
        $this->assertGreaterThan(0, $firstCallCount, 'First listing must make API calls to populate tile cache');
        // Second run at same tile must make FEWER calls (cache hits occurred)
        $this->assertLessThan($firstCallCount, $secondCallCount,
            'Second listing at same tile must make fewer API calls due to tile cache hits');
    }

    // =========================================================================
    // (v3-G4) Tile cache disabled by default — full fresh fetch
    // =========================================================================

    /** @test */
    public function tile_cache_is_disabled_when_tile_precision_is_not_set(): void
    {
        config(['location_dna.poi.tile_precision' => null]);
        $tileCache = new LocationDnaPoiTileCache();

        $this->assertFalse($tileCache->isEnabled(),
            'Tile cache must be disabled when tile_precision config is null/empty');
    }

    /** @test */
    public function tile_cache_is_disabled_when_tile_precision_is_empty_string(): void
    {
        config(['location_dna.poi.tile_precision' => '']);
        $tileCache = new LocationDnaPoiTileCache();

        $this->assertFalse($tileCache->isEnabled(),
            'Tile cache must be disabled when tile_precision config is empty string');
    }

    // =========================================================================
    // (v3-G5) Stats persisted to location_dna_poi_run_stats after completed run
    // =========================================================================

    /** @test */
    public function stats_are_persisted_to_run_stats_table_after_completed_run(): void
    {
        $this->createGeocodedDnaRecord();

        $this->mockClient
            ->method('request')
            ->willReturn($this->makePlacesResponse());

        $service = $this->makeService();
        $result  = $service->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue($result['success']);

        $this->assertDatabaseHas('location_dna_poi_run_stats', [
            'listing_type' => self::LISTING_TYPE,
            'listing_id'   => self::LISTING_ID,
        ]);

        // Verify grouped count matches CATEGORY_GROUPS size
        $row = \Illuminate\Support\Facades\DB::table('location_dna_poi_run_stats')
            ->where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(
            count(LocationDnaPoiDistanceService::CATEGORY_GROUPS),
            (int) $row->categories_grouped,
            'categories_grouped must equal number of secondary CATEGORY_GROUPS entries',
        );
    }

    /** @test */
    public function get_last_run_stats_returns_stats_shape_after_completed_run(): void
    {
        $this->createGeocodedDnaRecord();

        $this->mockClient
            ->method('request')
            ->willReturn($this->makePlacesResponse());

        $service = $this->makeService();
        $service->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $stats = $service->getLastRunStats();

        $this->assertArrayHasKey('categories_fetched_fresh', $stats);
        $this->assertArrayHasKey('categories_from_tile_cache', $stats);
        $this->assertArrayHasKey('categories_grouped', $stats);
        $this->assertArrayHasKey('precision_used', $stats);

        $this->assertSame(
            count(LocationDnaPoiDistanceService::CATEGORY_GROUPS),
            $stats['categories_grouped'],
        );
        $this->assertNull($stats['precision_used'], 'precision_used must be null when tile cache is disabled');
    }
}
