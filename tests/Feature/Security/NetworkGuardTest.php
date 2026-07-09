<?php

namespace Tests\Feature\Security;

use App\Contracts\CommuteTimeAdapterInterface;
use App\Contracts\PoiLookupAdapterInterface;
use App\Services\LocationDna\CommuteTimeStubAdapter;
use App\Services\LocationDna\StubPoiLookupAdapter;
use GuzzleHttp\ClientInterface;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Support\BlocksGooglePlacesHttpClient;
use Tests\Support\Http\StrayHttpRequestException;
use Tests\Support\Http\StrayRequestGuardFactory;
use Tests\TestCase;

/**
 * Phase 0 / S1e — the network guards, proven live.
 *
 * The test environment has two independent outbound paths, and until Batch 2 only
 * one of them was guarded:
 *
 *   1. The container-bound Guzzle `ClientInterface` — guarded since the 2026-07-05
 *      incident by `BlocksGooglePlacesHttpClient`.
 *   2. The `Http` facade, which builds its own Guzzle client and never consults the
 *      container. Nothing guarded it. `GeocodeSelleryLandlordListings.php:127` calls
 *      Google's geocode endpoint through it.
 *
 * These tests are the positive control for both. If the guards are ever silently
 * uninstalled — a refactor of `TestCase`, a container rebind, a framework upgrade
 * that changes `PendingRequest::buildStubHandler()` — the suite reports a clean
 * "zero stray requests" that means nothing. That failure mode is invisible unless
 * something asserts the guard *fires*. This does.
 *
 * @see erratum E-39 (Laravel 8.83 has no Http::preventStrayRequests())
 * @see INV-11
 */
class NetworkGuardTest extends TestCase
{
    /** @test */
    public function the_http_facade_is_backed_by_the_stray_request_guard(): void
    {
        $this->assertInstanceOf(
            StrayRequestGuardFactory::class,
            app(HttpFactory::class),
            'The Http facade is not bound to the guard factory — every Http::get() in a '
            . 'test can reach the live network. See TestCase::setUpTraits().',
        );
    }

    /** @test */
    public function an_unstubbed_http_facade_request_is_refused(): void
    {
        // The load-bearing assertion. `.invalid` is reserved by RFC 2606 and can never
        // resolve, so if the guard were absent this would fail as a DNS/connection
        // error rather than passing — it cannot pass by accident.
        $this->expectException(StrayHttpRequestException::class);

        Http::timeout(2)->get('https://stray-request.invalid/must-not-be-reached');
    }

    /** @test */
    public function an_unstubbed_request_to_google_via_the_http_facade_is_refused(): void
    {
        // This is the path GeocodeSelleryLandlordListings uses. It was unguarded.
        $this->expectException(StrayHttpRequestException::class);

        Http::timeout(2)->get('https://maps.googleapis.com/maps/api/geocode/json', ['address' => 'x']);
    }

    /** @test */
    public function a_stubbed_http_facade_request_still_resolves_normally(): void
    {
        // The guard must not break the six existing Http::fake() suites. Laravel
        // evaluates every stub callback eagerly, so a throwing global fake would fire
        // even here; the guard lives after stub resolution precisely to avoid that.
        Http::fake([
            'stubbed.invalid/*' => Http::response(['ok' => true], 200),
        ]);

        $response = Http::get('https://stubbed.invalid/endpoint');

        $this->assertTrue($response->json('ok'));
    }

    /** @test */
    public function the_container_guzzle_client_refuses_any_outbound_request(): void
    {
        $this->assertInstanceOf(BlocksGooglePlacesHttpClient::class, app(ClientInterface::class));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('BLOCKED live Google Places/Maps request');

        app(ClientInterface::class)->request('GET', 'https://maps.googleapis.com/maps/api/place/nearbysearch/json');
    }

    /** @test */
    public function the_provider_adapters_default_to_their_stubs(): void
    {
        // S1c. No reachable configuration resolves a live provider adapter in tests.
        $this->assertInstanceOf(StubPoiLookupAdapter::class, app(PoiLookupAdapterInterface::class));
        $this->assertInstanceOf(CommuteTimeStubAdapter::class, app(CommuteTimeAdapterInterface::class));
    }
}
