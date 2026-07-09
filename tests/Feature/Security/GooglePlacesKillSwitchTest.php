<?php

namespace Tests\Feature\Security;

use App\Models\PropertyLocationDna;
use App\Services\LocationDna\GooglePlacesPoiAdapter;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 0 / S2 — the master kill switch, wired.
 *
 * `config/google_places.php` has carried `enabled`, `daily_limit`, and `hourly_limit`
 * since the 2026-07-05 incident, and until this commit
 * `grep -rn "google_places\." app/` returned **zero hits**. The circuit breaker written
 * in response to a $1,223 incident did not exist in code.
 *
 * These tests prove GOOGLE_PLACES_ENABLED=false short-circuits BOTH Nearby Search
 * callers before any HTTP call is attempted, by binding an HTTP client that explodes
 * if it is ever touched.
 *
 * @see docs/architecture/SPATIAL-INTELLIGENCE-PLATFORM.md §17 Phase 0 item 7
 */
class GooglePlacesKillSwitchTest extends TestCase
{
    use DatabaseTransactions;

    private const LISTING_TYPE = 'seller_agent_auction';
    private const LISTING_ID   = 90210;

    /**
     * `Tests\TestCase` already binds `Tests\Support\BlocksGooglePlacesHttpClient`
     * into the container for every test: a Guzzle client that throws on any outbound
     * request. Because both Nearby callers resolve `ClientInterface` from the
     * container (Phase 0 / S1b), these tests fail loudly if the kill switch ever
     * lets a call through. No mock of our own is needed — we are exercising the real
     * production seam.
     */

    /** @test */
    public function path_b_google_places_poi_adapter_short_circuits_when_the_switch_is_off(): void
    {
        config(['google_places.enabled' => false]);
        config(['services.google.places_key' => 'a-real-looking-key']); // key present, switch off

        $results = app(GooglePlacesPoiAdapter::class)->search(27.77, -82.64, 'schools', 5, 10);

        $this->assertSame([], $results, 'The adapter must return empty results without calling Google.');
    }

    /** @test */
    public function path_b_adapter_short_circuits_before_the_api_key_check(): void
    {
        // Order matters: the switch is the outermost guard. Even with a valid-looking
        // key, no call is attempted for any category.
        config(['google_places.enabled' => false]);
        config(['services.google.places_key' => 'a-real-looking-key']);

        foreach (['schools', 'parks', 'shopping', 'hospitals', 'gyms', 'airports', 'downtown'] as $category) {
            $this->assertSame([], app(GooglePlacesPoiAdapter::class)->search(27.77, -82.64, $category, 5, 10));
        }
    }

    /** @test */
    public function path_a_location_dna_poi_distance_service_short_circuits_when_the_switch_is_off(): void
    {
        config(['google_places.enabled' => false]);
        config(['services.google.places_key' => 'a-real-looking-key']);

        PropertyLocationDna::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'geocode_status' => 'geocoded',
            'geocoded_lat'   => 27.7676,
            'geocoded_lng'   => -82.6403,
        ]);

        $output = app(LocationDnaPoiDistanceService::class)
            ->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertFalse($output['success']);
        $this->assertSame('failed', $output['status']);
        $this->assertSame('google_places_disabled', $output['error']);
    }

    /** @test */
    public function the_api_key_guard_still_fires_when_the_switch_is_on_but_the_key_is_absent(): void
    {
        // The two guards are independent. Removing one must not silently disable the other.
        config(['google_places.enabled' => true]);
        config(['services.google.places_key' => '']);

        PropertyLocationDna::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'geocode_status' => 'geocoded',
            'geocoded_lat'   => 27.7676,
            'geocoded_lng'   => -82.6403,
        ]);

        $output = app(LocationDnaPoiDistanceService::class)
            ->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertFalse($output['success']);
        $this->assertSame('missing_google_api_key', $output['error']);
    }

    /** @test */
    public function no_service_in_the_location_dna_namespace_constructs_a_bare_guzzle_client(): void
    {
        // S1b. A bare `new Client()` cannot be intercepted by the container binding or
        // by Http::fake(), and it bypasses GoogleOutboundTelemetryMiddleware entirely.
        $files = glob(app_path('Services/LocationDna/*.php'));
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $source = file_get_contents($file);
            // Strip comments so documentation mentioning the old pattern does not trip this.
            $stripped = preg_replace('#(//.*$)|(/\*.*?\*/)#ms', '', $source);

            $this->assertDoesNotMatchRegularExpression(
                '/new\s+Client\s*\(/',
                $stripped,
                basename($file) . ' constructs a bare Guzzle client. Resolve ClientInterface '
                . 'from the container instead (Phase 0 / S1b).',
            );
        }
    }
}
