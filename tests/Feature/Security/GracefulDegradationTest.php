<?php

namespace Tests\Feature\Security;

use App\Http\Livewire\HireBuyerAgent\BuyerAgentAuction;
use App\Http\Livewire\HireBuyerAgent\BuyerAgentAuctionEdit;
use App\Http\Livewire\HireLandLordAgent\LandLordAgentAuctionEdit;
use App\Http\Livewire\HireSellerAgent\SellerAgentAuctionEdit;
use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing;
use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListingEdit;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListingEdit;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListingEdit;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListingEdit;
use App\Support\Google\GoogleCredential;
use App\Support\Telemetry\GoogleOutboundTelemetryMiddleware;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Phase 0 item 1 — graceful degradation (SIP-P15, INV-12).
 *
 * > "This makes the credential's state irrelevant to correctness. Nothing crashes
 * >  whether the key is alive, dead, or revoked tomorrow."
 *
 * The claim under test is stronger than "it does not crash": with no credential the
 * application must make **zero outbound attempts**. Previously every one of these
 * methods interpolated a blank key into the query string and called Google anyway.
 * Google answered HTTP 200 + REQUEST_DENIED with an empty `predictions` array, and the
 * callers read that as "no suggestions" — a wrong answer that looked like a right one,
 * bought at the price of one request per keystroke (erratum E-42).
 *
 * `Tests\TestCase` binds `BlocksGooglePlacesHttpClient`, which throws on any outbound
 * request. So each assertion below is really two: the method returns its degraded value,
 * AND no HTTP client was ever reached. If a guard is removed, the test does not merely
 * fail — it throws.
 */
class GracefulDegradationTest extends TestCase
{
    use DatabaseTransactions;

    /** Every guarded suggestion method: [component, method]. */
    private const SUGGESTION_SURFACES = [
        [TenantOfferListing::class,       'getPlaceSuggestionsFromApi'],
        [TenantOfferListingEdit::class,   'getPlaceSuggestionsFromApi'],
        [BuyerAgentAuction::class,        'getPlaceSuggestionsFromApi'],
        [BuyerOfferListing::class,        'getPlaceSuggestionsFromApi'],
        [BuyerAgentAuctionEdit::class,    'getPlaceSuggestions'],
        [SellerAgentAuctionEdit::class,   'getPlaceSuggestions'],
        [LandLordAgentAuctionEdit::class, 'getPlaceSuggestions'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // TestCase already forces the key to null; be explicit about the precondition.
        config(['services.google.places_key' => null]);
        Cache::forget(GoogleOutboundTelemetryMiddleware::COUNTER_KEY);
    }

    private function invoke(object $component, string $method, ...$args)
    {
        $reflected = new ReflectionMethod($component, $method);
        $reflected->setAccessible(true);

        return $reflected->invoke($component, ...$args);
    }

    /** @test */
    public function the_credential_is_reported_absent_when_the_key_is_blank(): void
    {
        $this->assertTrue(GoogleCredential::absent());
        $this->assertFalse(GoogleCredential::present());

        config(['services.google.places_key' => '   ']);
        $this->assertTrue(GoogleCredential::absent(), 'A whitespace-only key must count as absent.');

        config(['services.google.places_key' => 'AIza-looks-real']);
        $this->assertTrue(GoogleCredential::present());
    }

    /** @test */
    public function the_kill_switch_does_not_gate_address_autocomplete(): void
    {
        // google_places.enabled defaults to FALSE and governs the billable Nearby Search
        // enrichment path. If GoogleCredential consulted it, wiring the switch would have
        // silently killed address entry on every listing form. Guard that regression.
        config([
            'google_places.enabled'      => false,
            'services.google.places_key' => 'AIza-looks-real',
        ]);

        $this->assertTrue(GoogleCredential::present());
    }

    /** @test */
    public function every_suggestion_surface_returns_no_suggestions_without_calling_google(): void
    {
        foreach (self::SUGGESTION_SURFACES as [$class, $method]) {
            $result = $this->invoke(new $class(), $method, 'Tampa', 'address');

            $this->assertSame(
                [],
                $result,
                "{$class}::{$method}() must degrade to an empty suggestion list when the "
                . 'credential is absent.',
            );
        }

        $this->assertSame(0, GoogleOutboundTelemetryMiddleware::counter());
    }

    /** @test */
    public function the_inline_address_branches_degrade_without_calling_google(): void
    {
        // BuyerOfferListingEdit / SellerOfferListingEdit / LandlordOfferListingEdit guard
        // inside an `elseif ($type === 'address')` branch rather than at method entry.
        foreach ([BuyerOfferListingEdit::class, SellerOfferListingEdit::class, LandlordOfferListingEdit::class] as $class) {
            $result = $this->invoke(new $class(), 'getPlaceSuggestions', '123 Main St', 'address');

            $this->assertSame([], $result, "{$class} address branch must degrade to [].");
        }

        $this->assertSame(0, GoogleOutboundTelemetryMiddleware::counter());
    }

    /**
     * Bind a client that COUNTS requests and answers REQUEST_DENIED, returning a
     * reference to the counter.
     *
     * Counting rather than throwing is essential: four of these methods wrap the call in
     * `try { … } catch (\Exception $e) { return []; }`, so a throwing client is swallowed
     * and "did it throw?" cannot distinguish "guarded" from "called and caught".
     */
    private function bindCountingGoogleClient(): \stdClass
    {
        $spy        = new \stdClass();
        $spy->calls = 0;

        $stack = \GuzzleHttp\HandlerStack::create(static function () use ($spy) {
            $spy->calls++;

            return \GuzzleHttp\Promise\Create::promiseFor(
                new \GuzzleHttp\Psr7\Response(200, [], '{"status":"REQUEST_DENIED","predictions":[],"results":[]}'),
            );
        });

        $this->app->instance(\GuzzleHttp\ClientInterface::class, new \GuzzleHttp\Client(['handler' => $stack]));

        return $spy;
    }

    /**
     * Positive control for every assertion above.
     *
     * Those tests prove the surfaces return `[]` and touch no HTTP client. But a method
     * that returned `[]` for some unrelated reason — a DB branch taken first, a typo in
     * the method name — would satisfy them just as well, and the suite would report a
     * guard that does not exist. E-41 is exactly what that looks like in production.
     *
     * So: give the surfaces a credential and count. Each must now issue exactly one
     * request. If this count drops, the empty lists above are not coming from the
     * credential guard and the guard is not being tested.
     *
     * @test
     */
    public function with_a_credential_present_the_same_surfaces_do_reach_the_http_client(): void
    {
        config(['services.google.places_key' => 'AIza-looks-real']);
        $spy = $this->bindCountingGoogleClient();

        foreach (self::SUGGESTION_SURFACES as [$class, $method]) {
            $before = $spy->calls;
            $this->invoke(new $class(), $method, 'Tampa', 'address');

            $this->assertSame(
                $before + 1,
                $spy->calls,
                "{$class}::{$method}() did not reach the HTTP client with a credential present. "
                . 'Its empty-list return is coming from somewhere other than the credential guard.',
            );
        }
    }

    /** @test */
    public function with_no_credential_the_same_surfaces_issue_zero_requests(): void
    {
        // The mirror image of the positive control, on the same counting client.
        config(['services.google.places_key' => null]);
        $spy = $this->bindCountingGoogleClient();

        foreach (self::SUGGESTION_SURFACES as [$class, $method]) {
            $this->invoke(new $class(), $method, 'Tampa', 'address');
        }

        $this->assertSame(0, $spy->calls, 'A missing credential must produce zero outbound attempts (INV-11).');
    }

    /** @test */
    public function tenant_address_lookup_leaves_fields_untouched_without_calling_google(): void
    {
        $component       = new TenantOfferListing();
        $component->city = 'Original City';

        $this->invoke($component, 'getAddressDetailsFromApi', '123 Main St, Tampa FL');

        $this->assertSame('Original City', $component->city, 'A degraded lookup must not clobber the field.');
        $this->assertSame(0, GoogleOutboundTelemetryMiddleware::counter());
    }

    /** @test */
    public function county_state_extraction_falls_back_to_string_parsing_without_calling_google(): void
    {
        // The catch block already fell back to extractStateFromCounty(). With no credential
        // we must take that same path directly, rather than provoking an exception to get there.
        $component = new TenantOfferListing();

        $this->invoke($component, 'extractStateFromCountyUsingAPI', 'Hillsborough County, Florida, USA');

        $this->assertSame('Florida', $component->state);
        $this->assertSame(0, GoogleOutboundTelemetryMiddleware::counter());
    }
}
