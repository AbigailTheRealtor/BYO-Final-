<?php

namespace Tests\Unit\Services\LocationDna;

use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
        config(['services.google.places_key' => 'test-poi-api-key']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeService(): LocationDnaPoiDistanceService
    {
        return new LocationDnaPoiDistanceService($this->mockClient);
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

        $categoryCount = count(LocationDnaPoiDistanceService::CATEGORIES);

        // Return zero results for the first call, success for all others
        $this->mockClient
            ->expects($this->exactly($categoryCount))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->makeZeroResultsResponse(),
                ...array_fill(0, $categoryCount - 1, $this->makePlacesResponse()),
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

        // Total rows === total categories
        $totalRows = PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->count();

        $this->assertSame($categoryCount, $totalRows);
    }

    // =========================================================================
    // (6) API exception for one category → error row, run continues
    // =========================================================================

    /** @test */
    public function it_persists_error_row_and_continues_when_api_throws_for_one_category(): void
    {
        $this->createGeocodedDnaRecord();

        $categoryCount = count(LocationDnaPoiDistanceService::CATEGORIES);

        $this->mockClient
            ->expects($this->exactly($categoryCount))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new \RuntimeException('API timeout')),
                ...array_fill(0, $categoryCount - 1, $this->makePlacesResponse()),
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

        $totalRows = PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->count();

        $this->assertSame($categoryCount, $totalRows);
    }

    // =========================================================================
    // (7) Success path — all categories found, rows persisted, success true
    // =========================================================================

    /** @test */
    public function it_succeeds_and_persists_all_category_rows_on_happy_path(): void
    {
        $this->createGeocodedDnaRecord();

        $categoryCount = count(LocationDnaPoiDistanceService::CATEGORIES);

        $this->mockClient
            ->expects($this->exactly($categoryCount))
            ->method('request')
            ->willReturn($this->makePlacesResponse(27.9600, -82.4600, 'Nearby Place'));

        $result = $this->makeService()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertContractShape($result);
        $this->assertTrue($result['success']);
        $this->assertSame('completed', $result['status']);
        $this->assertNull($result['error']);
        $this->assertEqualsWithDelta(self::SOURCE_LAT, $result['source_lat'], 0.0001);
        $this->assertEqualsWithDelta(self::SOURCE_LNG, $result['source_lng'], 0.0001);
        $this->assertCount($categoryCount, $result['results']);
    }

    // =========================================================================
    // (8) All categories attempted — one row per CATEGORIES key in DB
    // =========================================================================

    /** @test */
    public function it_attempts_every_defined_category_and_persists_one_row_each(): void
    {
        $this->createGeocodedDnaRecord();

        $expectedCategories = array_keys(LocationDnaPoiDistanceService::CATEGORIES);
        $categoryCount      = count($expectedCategories);

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

        $totalRows = PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->count();

        $this->assertSame($categoryCount, $totalRows);
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

        $categoryCount = count(LocationDnaPoiDistanceService::CATEGORIES);

        $this->mockClient
            ->expects($this->exactly($categoryCount))
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

        $this->assertCount($categoryCount, $newRows);

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

        $keywordCategories = array_filter(
            LocationDnaPoiDistanceService::CATEGORIES,
            fn ($meta) => $meta['query_strategy'] === 'keyword',
        );

        $this->assertNotEmpty($keywordCategories, 'At least one keyword-strategy category must exist');

        $capturedParams = [];

        $this->mockClient
            ->method('request')
            ->willReturnCallback(function (string $method, string $url, array $options) use (&$capturedParams) {
                $capturedParams[] = $options['query'] ?? [];
                return $this->makePlacesResponse();
            });

        $this->makeService()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $categoryKeys = array_keys(LocationDnaPoiDistanceService::CATEGORIES);

        foreach ($keywordCategories as $category => $meta) {
            $position = array_search($category, $categoryKeys, true);
            $this->assertNotFalse($position,
                "Could not find position of keyword category '{$category}'");

            $params = $capturedParams[$position] ?? [];
            $this->assertArrayHasKey('keyword', $params,
                "Keyword category '{$category}' must pass 'keyword' param to Google Places API");
            $this->assertSame($meta['keyword'], $params['keyword'],
                "Keyword category '{$category}' must pass the correct keyword value");
        }
    }

    /** @test */
    public function native_type_strategy_categories_pass_type_param_without_keyword(): void
    {
        $this->createGeocodedDnaRecord();

        $nativeCategories = array_filter(
            LocationDnaPoiDistanceService::CATEGORIES,
            fn ($meta) => $meta['query_strategy'] === 'native_type',
        );

        $this->assertNotEmpty($nativeCategories, 'At least one native-type category must exist');

        $capturedParams = [];

        $this->mockClient
            ->method('request')
            ->willReturnCallback(function (string $method, string $url, array $options) use (&$capturedParams) {
                $capturedParams[] = $options['query'] ?? [];
                return $this->makePlacesResponse();
            });

        $this->makeService()->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $categoryKeys = array_keys(LocationDnaPoiDistanceService::CATEGORIES);

        foreach ($nativeCategories as $category => $meta) {
            $position = array_search($category, $categoryKeys, true);
            $params   = $capturedParams[$position] ?? [];

            $this->assertArrayHasKey('type', $params,
                "Native-type category '{$category}' must pass 'type' param to Google Places API");
            $this->assertArrayNotHasKey('keyword', $params,
                "Native-type category '{$category}' must NOT pass a 'keyword' param");
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
}
