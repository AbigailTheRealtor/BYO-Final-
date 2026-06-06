<?php

namespace Tests\Feature;

use App\Jobs\ComputeLocationDna;
use App\Models\PropertyAuction;
use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Models\User;
use App\Services\LocationDna\LocationDnaGeocodeService;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use App\Services\LocationDna\LocationDnaPipelineRunner;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * LocationDnaPipelineTriggerTest
 *
 * Verifies that saving a PropertyAuction results in the Location DNA pipeline
 * running automatically (via the observer → ComputeLocationDna job → runner),
 * and that the expected database rows are produced.
 *
 * Google API calls are intercepted by injecting pre-built service instances
 * that use mock Guzzle clients into the IoC container before the save triggers
 * the observer and job dispatch.
 *
 * §1 — Saving a PropertyAuction produces a property_location_dna row
 * §2 — The property_location_dna row has geocode_status = 'geocoded'
 * §3 — property_location_pois rows are written for at least some categories
 * §4 — The property_location_dna row has a non-null lifestyle_json
 * §5 — The property_location_dna row has a non-null summary_json
 * §6 — Calling LocationDnaPipelineRunner::run() directly returns status='success'
 * §7 — Pipeline skips gracefully when address fields are missing (no throw)
 * §8 — ComputeLocationDna job dispatches from the PropertyAuction observer on save
 */
class LocationDnaPipelineTriggerTest extends TestCase
{
    use DatabaseTransactions;

    private const SOURCE_LAT = 27.9506;
    private const SOURCE_LNG = -82.4572;

    private const POI_LAT = 27.9600;
    private const POI_LNG = -82.4600;

    // =========================================================================
    // Setup / teardown
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();
        $this->bindMockedServices();
    }

    // =========================================================================
    // §1 — Saving a PropertyAuction produces a property_location_dna row
    // =========================================================================

    public function test_saving_property_auction_creates_location_dna_row(): void
    {
        $listing = $this->makeSellerListing();
        $listing->touch();

        $this->assertDatabaseHas('property_location_dna', [
            'listing_type' => 'seller',
            'listing_id'   => $listing->id,
        ]);
    }

    // =========================================================================
    // §2 — The property_location_dna row has geocode_status = 'geocoded'
    // =========================================================================

    public function test_location_dna_row_is_geocoded(): void
    {
        $listing = $this->makeSellerListing();
        $listing->touch();

        $this->assertDatabaseHas('property_location_dna', [
            'listing_type'   => 'seller',
            'listing_id'     => $listing->id,
            'geocode_status' => 'geocoded',
        ]);
    }

    // =========================================================================
    // §3 — property_location_pois rows are written for at least some categories
    // =========================================================================

    public function test_property_location_pois_are_written(): void
    {
        $listing = $this->makeSellerListing();
        $listing->touch();

        $poiCount = DB::table('property_location_pois')
            ->where('listing_type', 'seller')
            ->where('listing_id', $listing->id)
            ->count();

        $this->assertGreaterThan(0, $poiCount, 'Expected at least one property_location_pois row');
    }

    // =========================================================================
    // §4 — The property_location_dna row has a non-null lifestyle_json
    // =========================================================================

    public function test_lifestyle_json_is_populated(): void
    {
        $listing = $this->makeSellerListing();
        $listing->touch();

        $record = PropertyLocationDna::where('listing_type', 'seller')
            ->where('listing_id', $listing->id)
            ->first();

        $this->assertNotNull($record, 'property_location_dna record should exist');
        $this->assertNotNull($record->lifestyle_json, 'lifestyle_json should not be null');
    }

    // =========================================================================
    // §5 — The property_location_dna row has a non-null summary_json
    // =========================================================================

    public function test_summary_json_is_populated(): void
    {
        $listing = $this->makeSellerListing();
        $listing->touch();

        $record = PropertyLocationDna::where('listing_type', 'seller')
            ->where('listing_id', $listing->id)
            ->first();

        $this->assertNotNull($record, 'property_location_dna record should exist');
        $this->assertNotNull($record->summary_json, 'summary_json should not be null');
    }

    // =========================================================================
    // §6 — Calling LocationDnaPipelineRunner::run() directly returns 'success'
    // =========================================================================

    public function test_pipeline_runner_returns_success_for_valid_listing(): void
    {
        $listing = $this->makeSellerListing();

        $runner = $this->makeRunner();
        $result = $runner->run('seller', $listing->id);

        $this->assertSame('success', $result['status']);
        $this->assertArrayHasKey('geocode', $result['steps']);
        $this->assertArrayHasKey('poi', $result['steps']);
        $this->assertArrayHasKey('summary', $result['steps']);
        $this->assertArrayHasKey('lifestyle', $result['steps']);
    }

    // =========================================================================
    // §7 — Pipeline skips gracefully when address fields are missing (no throw)
    // =========================================================================

    public function test_pipeline_skips_gracefully_when_address_missing(): void
    {
        // Insert a listing with an empty address string (city/state IDs exist but address is blank)
        $stateId = $this->ensureState('FL', 'Florida');
        $cityId  = $this->ensureCity('Tampa', $stateId);

        $listingId = DB::table('property_auctions')->insertGetId([
            'user_id'      => $this->makeUserId(),
            'is_approved'  => true,
            'sold'         => false,
            'auction_type' => 'Traditional Listing',
            'title'        => 'Empty Address Listing',
            'address'      => '',
            'city_id'      => $cityId,
            'state_id'     => $stateId,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $runner = $this->makeRunner();
        $result = $runner->run('seller', $listingId);

        $this->assertSame('skipped', $result['status'], 'Pipeline should skip when address is missing');
        $this->assertArrayHasKey('geocode', $result['steps']);
    }

    // =========================================================================
    // §8 — ComputeLocationDna job dispatches from the PropertyAuction observer on save
    // =========================================================================

    public function test_observer_dispatches_compute_location_dna_job(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $listing = $this->makeSellerListing();
        $listing->touch();

        \Illuminate\Support\Facades\Queue::assertPushed(ComputeLocationDna::class, function ($job) use ($listing) {
            return $job->listingType === 'seller' && $job->listingId === $listing->id;
        });
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Bind pre-built LocationDnaGeocodeService and LocationDnaPoiDistanceService
     * instances that use mock Guzzle clients into the container.
     * SummaryService and LifestyleService are pure-DB and do not need mocking.
     */
    private function bindMockedServices(): void
    {
        $this->app->instance(
            LocationDnaGeocodeService::class,
            new LocationDnaGeocodeService($this->makeGeocodeMockClient()),
        );

        $this->app->instance(
            LocationDnaPoiDistanceService::class,
            new LocationDnaPoiDistanceService($this->makePoiMockClient()),
        );
    }

    /**
     * Build the pipeline runner using the container-bound (mocked) services.
     */
    private function makeRunner(): LocationDnaPipelineRunner
    {
        return $this->app->make(LocationDnaPipelineRunner::class);
    }

    /**
     * Create a mock Guzzle client that returns a successful geocode response.
     */
    private function makeGeocodeMockClient(): ClientInterface
    {
        $body = json_encode([
            'status'  => 'OK',
            'results' => [
                [
                    'geometry' => [
                        'location' => [
                            'lat' => self::SOURCE_LAT,
                            'lng' => self::SOURCE_LNG,
                        ],
                    ],
                ],
            ],
        ]);

        $mock = $this->createMock(ClientInterface::class);
        $mock->method('request')->willReturn(new Response(200, [], $body));
        return $mock;
    }

    /**
     * Create a mock Guzzle client that returns a single POI result for every request.
     */
    private function makePoiMockClient(): ClientInterface
    {
        $body = json_encode([
            'status'  => 'OK',
            'results' => [
                [
                    'name'     => 'Mock Place',
                    'vicinity' => '1 Test Blvd, Tampa',
                    'geometry' => [
                        'location' => [
                            'lat' => self::POI_LAT,
                            'lng' => self::POI_LNG,
                        ],
                    ],
                ],
            ],
        ]);

        $mock = $this->createMock(ClientInterface::class);
        $mock->method('request')->willReturn(new Response(200, [], $body));
        return $mock;
    }

    /**
     * Insert a seller listing with a valid address referencing a real-or-inserted
     * us_states and us_cities row. Returns the PropertyAuction model.
     */
    private function makeSellerListing(): PropertyAuction
    {
        config(['services.google.places_key' => 'fake-test-key']);

        $stateId  = $this->ensureState('FL', 'Florida');
        $cityId   = $this->ensureCity('Tampa', $stateId);

        $listingId = DB::table('property_auctions')->insertGetId([
            'user_id'      => $this->makeUserId(),
            'is_approved'  => true,
            'sold'         => false,
            'auction_type' => 'Traditional Listing',
            'title'        => 'Test Listing',
            'address'      => '123 Main St',
            'city_id'      => $cityId,
            'state_id'     => $stateId,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return PropertyAuction::find($listingId);
    }

    /** Return or insert a us_states row; returns id. */
    private function ensureState(string $abbreviation, string $name): int
    {
        $existing = DB::table('us_states')->where('abbreviation', $abbreviation)->first();
        if ($existing) {
            return $existing->id;
        }
        return DB::table('us_states')->insertGetId([
            'name'         => $name,
            'abbreviation' => $abbreviation,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    /** Return or insert a us_cities row; returns id. */
    private function ensureCity(string $name, int $stateId): int
    {
        $existing = DB::table('us_cities')
            ->where('name', $name)
            ->where('state_id', $stateId)
            ->first();
        if ($existing) {
            return $existing->id;
        }
        return DB::table('us_cities')->insertGetId([
            'name'       => $name,
            'state_id'   => $stateId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Create a minimal user via the factory and return its id. */
    private function makeUserId(): int
    {
        return User::factory()->create()->id;
    }
}
