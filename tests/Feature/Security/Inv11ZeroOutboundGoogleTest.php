<?php

namespace Tests\Feature\Security;

use App\Models\PropertyLocationDna;
use App\Services\LocationDna\GooglePlacesPoiAdapter;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use App\Support\Telemetry\GoogleOutboundTelemetryMiddleware;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Phase 0 / S1e — INV-11: no test depends on a Google credential, and the
 * instrumented code paths make zero outbound Google requests.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * SCOPE OF THIS ASSERTION — READ BEFORE STRENGTHENING
 * ─────────────────────────────────────────────────────────────────────────────
 * This test asserts **zero outbound attempts from instrumented / test-controlled
 * paths**, which is deliberately weaker than INV-11's eventual "zero outbound
 * attempts" full stop.
 *
 * The reason is erratum **E-38**: 15 bare Guzzle clients outside
 * `app/Services/LocationDna/` still construct their own `new \GuzzleHttp\Client()`
 * and call `maps.googleapis.com` directly. They resolve nothing from the container,
 * so `GoogleOutboundTelemetryMiddleware` never sees them and
 * `BlocksGooglePlacesHttpClient` never intercepts them. Three of those 15 live in
 * `TenantAgentAuction.php`, frozen under **INV-8**, and two more in
 * `TenantAgentAuctionEdit.php`, frozen by product-owner decision pending **Q11**.
 *
 * Asserting full zero-outbound coverage today would therefore assert something the
 * codebase cannot yet satisfy, and the test would sit red for reasons no one is
 * allowed to fix.
 *
 * @todo TIGHTEN THIS ASSERTION once the remaining bare clients are resolved.
 *       Blocked on: E-38 (the 12 non-frozen clients) and Q11 (the 5 frozen ones).
 *       When both close, this test must assert that `counter()` is zero after
 *       exercising *any* application path — not merely the instrumented ones — and
 *       the qualifier must be struck from INV-11 in the architecture document.
 *       Until then, "zero outbound" is upheld in tests, not in production.
 */
class Inv11ZeroOutboundGoogleTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget(GoogleOutboundTelemetryMiddleware::COUNTER_KEY);
    }

    /** @test */
    public function no_google_credential_is_present_in_the_test_environment(): void
    {
        $this->assertNull(
            static::detectLiveGooglePlacesKey(),
            'A live Google credential leaked into the test environment. tests/bootstrap.php '
            . 'should have blanked it across getenv()/$_ENV/$_SERVER (erratum E-37).',
        );

        $this->assertTrue(blank(config('services.google.places_key')));
    }

    /** @test */
    public function the_kill_switch_is_off_by_default_in_tests(): void
    {
        $this->assertFalse(config('google_places.enabled'));
    }

    /** @test */
    public function the_enrichment_path_makes_zero_outbound_google_requests(): void
    {
        PropertyLocationDna::create([
            'listing_type'   => 'seller_agent_auction',
            'listing_id'     => 77001,
            'geocode_status' => 'geocoded',
            'geocoded_lat'   => 27.7676,
            'geocoded_lng'   => -82.6403,
        ]);

        $output = app(LocationDnaPoiDistanceService::class)
            ->calculateForListing('seller_agent_auction', 77001);

        $this->assertFalse($output['success']);
        $this->assertSame('google_places_disabled', $output['error']);

        $this->assertSame(
            0,
            GoogleOutboundTelemetryMiddleware::counter(),
            'The Location DNA enrichment path attempted an outbound Google request.',
        );
    }

    /** @test */
    public function the_poi_adapter_makes_zero_outbound_google_requests(): void
    {
        $results = app(GooglePlacesPoiAdapter::class)->search(27.77, -82.64, 'schools', 5, 10);

        $this->assertSame([], $results);
        $this->assertSame(0, GoogleOutboundTelemetryMiddleware::counter());
    }
}
