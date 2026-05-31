<?php

namespace Tests\Unit\Services\LocationDna;

use App\Models\PropertyLocationDna;
use App\Services\LocationDna\LocationDnaGeocodeService;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LocationDnaGeocodeServiceTest
 *
 * Verifies LocationDnaGeocodeService::geocodeForListing() using a SQLite
 * in-memory database. The Guzzle HTTP client is mocked to avoid real API calls.
 *
 * Output contract (Phase B approved — 8 keys):
 *   success, status, listing_type, listing_id, lat, lng, source, error
 *
 * Test coverage:
 *   (1)  Missing 'address' field         → success false, status 'skipped', error set
 *   (2)  Missing 'city' field            → success false, status 'skipped', error set
 *   (3)  Missing 'state' field           → success false, status 'skipped', error set
 *   (4)  Valid address — creates record  → success true, status 'geocoded', lat/lng populated
 *   (5)  Cache reuse — same address      → success true, status 'geocoded', no HTTP call
 *   (6)  Cache clearing — address change → prior lat/lng cleared, new geocode performed
 *   (7)  API returns no results          → success false, status 'failed', error set, DB updated
 *   (7b) ZIP-only change                 → cache invalidated, re-geocoded
 *   (7c) County-only change              → cache invalidated, re-geocoded
 *   (8)  Blank API key                   → success false, status 'failed', error='missing_google_api_key'
 *   (8b) Throwable during execution      → success false, status 'failed', no exception propagated
 *   (8c) Throwable path persists DB      → geocode_status='failed', geocode_error set in DB
 *   (9)  Output shape consistency        → all eight keys present in every return path
 *   (10) No fabricated coordinates       → lat/lng null for skipped/failed, set for geocoded
 *   (11) listing_type/listing_id echoed  → output keys match the input arguments
 *   (12) No AI/OpenAI class imports      → service file does not import OpenAI classes
 *   (13) No marketing report imports     → service file does not import marketing report classes
 */
class LocationDnaGeocodeServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const LISTING_TYPE = 'seller_agent_auction';
    private const LISTING_ID   = 42;

    private const VALID_ADDRESS = [
        'address' => '123 Main St',
        'city'    => 'Tampa',
        'state'   => 'FL',
        'county'  => 'Hillsborough',
        'zip'     => '33601',
    ];

    /** Approved Phase B output contract keys. */
    private const CONTRACT_KEYS = ['success', 'status', 'listing_type', 'listing_id', 'lat', 'lng', 'source', 'error'];

    private ClientInterface $mockClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockClient = $this->createMock(ClientInterface::class);

        // Provide a non-blank sentinel key for every test so the blank-key guard
        // does not fire by default (env var is not set in the test environment and
        // config() returns null, which blank() treats as truthy).
        config(['services.google.places_key' => 'test-google-api-key']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeService(): LocationDnaGeocodeService
    {
        return new LocationDnaGeocodeService($this->mockClient);
    }

    private function makeGeoResponse(float $lat = 27.9506, float $lng = -82.4572): Response
    {
        $body = json_encode([
            'status'  => 'OK',
            'results' => [
                [
                    'geometry' => [
                        'location' => ['lat' => $lat, 'lng' => $lng],
                    ],
                ],
            ],
        ]);

        return new Response(200, [], $body);
    }

    private function makeEmptyGeoResponse(): Response
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
        $this->assertSame(count(self::CONTRACT_KEYS), count($result), 'Output must contain exactly the approved contract keys');
    }

    // =========================================================================
    // (1) Missing 'address' → skipped
    // =========================================================================

    /** @test */
    public function it_returns_skipped_when_address_field_is_missing(): void
    {
        $this->mockClient->expects($this->never())->method('request');

        $result = $this->makeService()->geocodeForListing(
            self::LISTING_TYPE,
            self::LISTING_ID,
            ['city' => 'Tampa', 'state' => 'FL']
        );

        $this->assertContractShape($result);
        $this->assertFalse($result['success']);
        $this->assertSame('skipped', $result['status']);
        $this->assertSame('missing_required_address_fields', $result['error']);
        $this->assertNull($result['lat']);
        $this->assertNull($result['lng']);
    }

    // =========================================================================
    // (2) Missing 'city' → skipped
    // =========================================================================

    /** @test */
    public function it_returns_skipped_when_city_field_is_missing(): void
    {
        $this->mockClient->expects($this->never())->method('request');

        $result = $this->makeService()->geocodeForListing(
            self::LISTING_TYPE,
            self::LISTING_ID,
            ['address' => '123 Main St', 'state' => 'FL']
        );

        $this->assertContractShape($result);
        $this->assertFalse($result['success']);
        $this->assertSame('skipped', $result['status']);
        $this->assertSame('missing_required_address_fields', $result['error']);
    }

    // =========================================================================
    // (3) Missing 'state' → skipped
    // =========================================================================

    /** @test */
    public function it_returns_skipped_when_state_field_is_missing(): void
    {
        $this->mockClient->expects($this->never())->method('request');

        $result = $this->makeService()->geocodeForListing(
            self::LISTING_TYPE,
            self::LISTING_ID,
            ['address' => '123 Main St', 'city' => 'Tampa']
        );

        $this->assertContractShape($result);
        $this->assertFalse($result['success']);
        $this->assertSame('skipped', $result['status']);
        $this->assertSame('missing_required_address_fields', $result['error']);
    }

    // =========================================================================
    // (4) Valid address — creates DB record and returns geocoded
    // =========================================================================

    /** @test */
    public function it_geocodes_a_valid_address_and_persists_the_record(): void
    {
        $this->mockClient->expects($this->once())
            ->method('request')
            ->willReturn($this->makeGeoResponse(27.9506, -82.4572));

        $result = $this->makeService()->geocodeForListing(
            self::LISTING_TYPE,
            self::LISTING_ID,
            self::VALID_ADDRESS
        );

        $this->assertContractShape($result);
        $this->assertTrue($result['success']);
        $this->assertSame('geocoded', $result['status']);
        $this->assertEqualsWithDelta(27.9506, $result['lat'], 0.0001);
        $this->assertEqualsWithDelta(-82.4572, $result['lng'], 0.0001);
        $this->assertSame('google', $result['source']);
        $this->assertNull($result['error']);

        // Verify the record was persisted
        $this->assertDatabaseHas('property_location_dna', [
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'geocode_status' => 'geocoded',
            'geocode_source' => 'google',
            'source_address' => '123 Main St',
            'source_city'    => 'Tampa',
            'source_state'   => 'FL',
        ]);
    }

    // =========================================================================
    // (5) Cache reuse — same address, already geocoded, no HTTP call
    // =========================================================================

    /** @test */
    public function it_returns_cached_result_when_address_is_unchanged_and_status_is_geocoded(): void
    {
        PropertyLocationDna::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'source_address' => '123 Main St',
            'source_city'    => 'Tampa',
            'source_state'   => 'FL',
            'source_county'  => 'Hillsborough',
            'source_zip'     => '33601',
            'geocoded_lat'   => 27.9506,
            'geocoded_lng'   => -82.4572,
            'geocode_source' => 'google',
            'geocode_status' => 'geocoded',
            'geocoded_at'    => now(),
        ]);

        $this->mockClient->expects($this->never())->method('request');

        $result = $this->makeService()->geocodeForListing(
            self::LISTING_TYPE,
            self::LISTING_ID,
            self::VALID_ADDRESS
        );

        $this->assertContractShape($result);
        $this->assertTrue($result['success']);
        $this->assertSame('geocoded', $result['status']);
        $this->assertEqualsWithDelta(27.9506, $result['lat'], 0.0001);
        $this->assertEqualsWithDelta(-82.4572, $result['lng'], 0.0001);
        $this->assertNull($result['error']);
    }

    // =========================================================================
    // (6) Cache clearing — address changed, old coordinates not reused
    // =========================================================================

    /** @test */
    public function it_clears_prior_geocode_and_re_geocodes_when_address_changes(): void
    {
        PropertyLocationDna::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'source_address' => '999 Old Rd',
            'source_city'    => 'Tampa',
            'source_state'   => 'FL',
            'geocoded_lat'   => 10.0,
            'geocoded_lng'   => -10.0,
            'geocode_source' => 'google',
            'geocode_status' => 'geocoded',
            'geocoded_at'    => now()->subDay(),
        ]);

        $this->mockClient->expects($this->once())
            ->method('request')
            ->willReturn($this->makeGeoResponse(27.9506, -82.4572));

        $result = $this->makeService()->geocodeForListing(
            self::LISTING_TYPE,
            self::LISTING_ID,
            self::VALID_ADDRESS
        );

        $this->assertContractShape($result);
        $this->assertTrue($result['success']);
        $this->assertSame('geocoded', $result['status']);
        $this->assertEqualsWithDelta(27.9506, $result['lat'], 0.0001);

        $record = PropertyLocationDna::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->first();

        $this->assertNotNull($record);
        $this->assertSame('123 Main St', $record->source_address);
        $this->assertEqualsWithDelta(27.9506, (float) $record->geocoded_lat, 0.0001);
    }

    // =========================================================================
    // (7) API returns no results → failed, error set, DB updated
    // =========================================================================

    /** @test */
    public function it_returns_failed_when_api_returns_no_results(): void
    {
        $this->mockClient->expects($this->once())
            ->method('request')
            ->willReturn($this->makeEmptyGeoResponse());

        $result = $this->makeService()->geocodeForListing(
            self::LISTING_TYPE,
            self::LISTING_ID,
            self::VALID_ADDRESS
        );

        $this->assertContractShape($result);
        $this->assertFalse($result['success']);
        $this->assertSame('failed', $result['status']);
        $this->assertNull($result['lat']);
        $this->assertNull($result['lng']);
        $this->assertNotNull($result['error']);

        $this->assertDatabaseHas('property_location_dna', [
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'geocode_status' => 'failed',
        ]);
    }

    // =========================================================================
    // (7b) ZIP-only address change invalidates cache and re-geocodes
    // =========================================================================

    /** @test */
    public function it_invalidates_cache_and_re_geocodes_when_only_zip_changes(): void
    {
        PropertyLocationDna::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'source_address' => '123 Main St',
            'source_city'    => 'Tampa',
            'source_state'   => 'FL',
            'source_county'  => 'Hillsborough',
            'source_zip'     => '33601',
            'geocoded_lat'   => 27.9506,
            'geocoded_lng'   => -82.4572,
            'geocode_source' => 'google',
            'geocode_status' => 'geocoded',
            'geocoded_at'    => now(),
        ]);

        $this->mockClient->expects($this->once())
            ->method('request')
            ->willReturn($this->makeGeoResponse(27.9600, -82.4600));

        $result = $this->makeService()->geocodeForListing(
            self::LISTING_TYPE,
            self::LISTING_ID,
            array_merge(self::VALID_ADDRESS, ['zip' => '33602'])
        );

        $this->assertContractShape($result);
        $this->assertTrue($result['success']);
        $this->assertSame('geocoded', $result['status']);
        $this->assertEqualsWithDelta(27.9600, $result['lat'], 0.0001);
    }

    // =========================================================================
    // (7c) County-only address change invalidates cache and re-geocodes
    // =========================================================================

    /** @test */
    public function it_invalidates_cache_and_re_geocodes_when_only_county_changes(): void
    {
        PropertyLocationDna::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'source_address' => '123 Main St',
            'source_city'    => 'Tampa',
            'source_state'   => 'FL',
            'source_county'  => 'Hillsborough',
            'source_zip'     => '33601',
            'geocoded_lat'   => 27.9506,
            'geocoded_lng'   => -82.4572,
            'geocode_source' => 'google',
            'geocode_status' => 'geocoded',
            'geocoded_at'    => now(),
        ]);

        $this->mockClient->expects($this->once())
            ->method('request')
            ->willReturn($this->makeGeoResponse(28.0000, -82.5000));

        $result = $this->makeService()->geocodeForListing(
            self::LISTING_TYPE,
            self::LISTING_ID,
            array_merge(self::VALID_ADDRESS, ['county' => 'Pinellas'])
        );

        $this->assertContractShape($result);
        $this->assertTrue($result['success']);
        $this->assertSame('geocoded', $result['status']);
        $this->assertEqualsWithDelta(28.0000, $result['lat'], 0.0001);
    }

    // =========================================================================
    // (8) Blank API key → failed output without making an HTTP call
    // =========================================================================

    /** @test */
    public function it_returns_failed_when_google_api_key_is_blank(): void
    {
        // Ensure no HTTP call is made when the key is absent
        $this->mockClient->expects($this->never())->method('request');

        // Override the config key to blank for this test
        config(['services.google.places_key' => '']);

        $result = $this->makeService()->geocodeForListing(
            self::LISTING_TYPE,
            self::LISTING_ID,
            self::VALID_ADDRESS
        );

        $this->assertContractShape($result);
        $this->assertFalse($result['success']);
        $this->assertSame('failed', $result['status']);
        $this->assertSame('missing_google_api_key', $result['error']);
        $this->assertNull($result['lat']);
        $this->assertNull($result['lng']);
    }

    // =========================================================================
    // (8b) Throwable during execution → failed output, no exception propagated
    // =========================================================================

    /** @test */
    public function it_catches_throwable_and_returns_failed_without_propagating(): void
    {
        $this->mockClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \RuntimeException('Network timeout'));

        $result = $this->makeService()->geocodeForListing(
            self::LISTING_TYPE,
            self::LISTING_ID,
            self::VALID_ADDRESS
        );

        $this->assertContractShape($result);
        $this->assertFalse($result['success']);
        $this->assertSame('failed', $result['status']);
        $this->assertNull($result['lat']);
        $this->assertNull($result['lng']);
        $this->assertStringContainsString('Network timeout', $result['error']);
    }

    // =========================================================================
    // (8b) Throwable path persists geocode_status=failed and geocode_error in DB
    // =========================================================================

    /** @test */
    public function it_persists_failed_status_and_error_to_db_when_throwable_is_caught(): void
    {
        $this->mockClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $this->makeService()->geocodeForListing(
            self::LISTING_TYPE,
            self::LISTING_ID,
            self::VALID_ADDRESS
        );

        $record = PropertyLocationDna::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->first();

        $this->assertNotNull($record, 'A property_location_dna record must be persisted after throwable');
        $this->assertSame('failed', $record->geocode_status);
        $this->assertStringContainsString('Connection refused', $record->geocode_error);
    }

    // =========================================================================
    // (9) Output shape consistency — all eight contract keys in every path
    // =========================================================================

    /** @test */
    public function output_shape_is_consistent_across_all_return_paths(): void
    {
        // skipped path
        $skipped = $this->makeService()->geocodeForListing(
            self::LISTING_TYPE, 1, []
        );
        $this->assertContractShape($skipped);

        // failed path (API returns no results)
        $this->mockClient->method('request')->willReturn($this->makeEmptyGeoResponse());

        $failed = $this->makeService()->geocodeForListing(
            self::LISTING_TYPE, 2, self::VALID_ADDRESS
        );
        $this->assertContractShape($failed);

        // geocoded path
        $this->mockClient = $this->createMock(ClientInterface::class);
        $this->mockClient->method('request')->willReturn($this->makeGeoResponse());

        $geocoded = $this->makeService()->geocodeForListing(
            self::LISTING_TYPE, 3, self::VALID_ADDRESS
        );
        $this->assertContractShape($geocoded);
    }

    // =========================================================================
    // (10) No fabricated coordinates — lat/lng null for non-geocoded statuses
    // =========================================================================

    /** @test */
    public function lat_and_lng_are_null_for_non_geocoded_statuses(): void
    {
        $result = $this->makeService()->geocodeForListing(
            self::LISTING_TYPE, 99, ['address' => '', 'city' => 'Tampa', 'state' => 'FL']
        );
        $this->assertNull($result['lat']);
        $this->assertNull($result['lng']);
    }

    // =========================================================================
    // (11) listing_type and listing_id are echoed in every output path
    // =========================================================================

    /** @test */
    public function listing_type_and_listing_id_are_echoed_in_output(): void
    {
        // skipped path
        $result = $this->makeService()->geocodeForListing(
            'buyer_agent_auction', 77, []
        );
        $this->assertSame('buyer_agent_auction', $result['listing_type']);
        $this->assertSame(77, $result['listing_id']);

        // geocoded path
        $this->mockClient = $this->createMock(ClientInterface::class);
        $this->mockClient->method('request')->willReturn($this->makeGeoResponse());

        $result = $this->makeService()->geocodeForListing(
            'landlord_agent_auction', 999, self::VALID_ADDRESS
        );
        $this->assertSame('landlord_agent_auction', $result['listing_type']);
        $this->assertSame(999, $result['listing_id']);
    }

    // =========================================================================
    // (12) No AI/OpenAI class imports in service source
    // =========================================================================

    /** @test */
    public function service_source_contains_no_openai_or_ai_service_imports(): void
    {
        $source = file_get_contents(
            __DIR__ . '/../../../../app/Services/LocationDna/LocationDnaGeocodeService.php'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/^use\s+.*OpenAi/im',
            $source,
            'LocationDnaGeocodeService must not import any OpenAI class'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/^use\s+.*AiMarketingReport/im',
            $source,
            'LocationDnaGeocodeService must not import AiMarketingReport services'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/^use\s+.*OpenAiClientService/im',
            $source,
            'LocationDnaGeocodeService must not import OpenAiClientService'
        );
    }

    // =========================================================================
    // (13) No marketing report class imports in service source
    // =========================================================================

    /** @test */
    public function service_source_contains_no_marketing_report_imports(): void
    {
        $source = file_get_contents(
            __DIR__ . '/../../../../app/Services/LocationDna/LocationDnaGeocodeService.php'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/^use\s+.*MarketingReport/im',
            $source,
            'LocationDnaGeocodeService must not import MarketingReport classes'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/^use\s+.*PropertyDnaProfile/im',
            $source,
            'LocationDnaGeocodeService must not import PropertyDnaProfile'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/^use\s+.*DnaMarketingOutput/im',
            $source,
            'LocationDnaGeocodeService must not import DnaMarketingOutput'
        );
    }
}
