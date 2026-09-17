<?php

namespace Tests\Unit\LocationDna;

use App\Services\LocationDna\GooglePlacesPoiAdapter;
use App\Services\LocationDna\Providers\NearbyPoiFetcherFactory;
use App\Services\LocationDna\StubNearbyPoiFetcher;
use GuzzleHttp\ClientInterface;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Phase 1 Batch 2 — proves NearbyPoiFetcherFactory is the single, registry-driven
 * construction site for the production fetcher, and that Batch 1 sub-option 1a
 * client-forwarding is preserved.
 */
class NearbyPoiFetcherFactoryTest extends TestCase
{
    /** poi.default declares google_places as the BASE (see the overlay test below). */
    private function config(): array
    {
        return [
            'providers' => [
                'google_places' => [
                    'tier'    => 'premium',
                    'license' => 'google-tos',
                    'serves'  => ['rating'],
                    'enabled' => true,
                ],
                'osm_overpass' => [
                    'tier'    => 'free',
                    'license' => 'odbl',
                    'serves'  => ['existence'],
                    'enabled' => false, // not yet implemented — must be skipped
                ],
            ],
            'capabilities' => [
                'poi.default' => [
                    ['provider' => 'osm_overpass',  'role' => 'base'],
                    ['provider' => 'google_places', 'role' => 'base'],
                ],
            ],
            'regional_overrides' => [],
        ];
    }

    /**
     * The same fixture with Google back in its real shipped role. Nothing else differs.
     */
    private function configWithGoogleAsOverlay(): array
    {
        $config = $this->config();
        $config['capabilities']['poi.default'] = [
            ['provider' => 'osm_overpass',  'role' => 'base'],
            ['provider' => 'google_places', 'role' => 'overlay'],
        ];

        return $config;
    }

    /**
     * THE REGRESSION THIS PHASE EXISTS TO PREVENT.
     *
     * Enabled provider, valid key, and the only binding left standing — and still no
     * Google adapter, because its declared role is `overlay`. Before this change the
     * factory constructed Google here, which is how a capability map that named it a
     * rating overlay ended up making it the billable existence provider by elimination.
     */
    public function test_google_declared_as_an_overlay_is_never_constructed(): void
    {
        config(['services.google.places_key' => 'fake-test-key']);

        $fetcher = (new NearbyPoiFetcherFactory($this->configWithGoogleAsOverlay()))->make();

        $this->assertInstanceOf(StubNearbyPoiFetcher::class, $fetcher);
    }

    public function test_resolves_google_adapter_when_key_present(): void
    {
        config(['services.google.places_key' => 'fake-test-key']);

        $fetcher = (new NearbyPoiFetcherFactory($this->config()))->make();

        $this->assertInstanceOf(GooglePlacesPoiAdapter::class, $fetcher);
    }

    public function test_falls_back_to_stub_when_key_absent(): void
    {
        config(['services.google.places_key' => null]);

        $fetcher = (new NearbyPoiFetcherFactory($this->config()))->make();

        $this->assertInstanceOf(StubNearbyPoiFetcher::class, $fetcher);
    }

    public function test_forwards_injected_client_into_the_adapter(): void
    {
        config(['services.google.places_key' => 'fake-test-key']);

        /** @var ClientInterface $mock */
        $mock    = $this->createMock(ClientInterface::class);
        $fetcher = (new NearbyPoiFetcherFactory($this->config()))->make($mock);

        $this->assertInstanceOf(GooglePlacesPoiAdapter::class, $fetcher);

        // Sub-option 1a: the injected client must be forwarded into the adapter so a
        // mock/blocking client passed to the service constructor still reaches the call.
        $prop = new ReflectionProperty(GooglePlacesPoiAdapter::class, 'httpClient');
        $prop->setAccessible(true);
        $this->assertSame($mock, $prop->getValue($fetcher));
    }

    public function test_null_client_leaves_adapter_to_resolve_the_container_binding(): void
    {
        config(['services.google.places_key' => 'fake-test-key']);

        $fetcher = (new NearbyPoiFetcherFactory($this->config()))->make(null);

        $prop = new ReflectionProperty(GooglePlacesPoiAdapter::class, 'httpClient');
        $prop->setAccessible(true);
        $this->assertNull($prop->getValue($fetcher), 'null client is preserved so the adapter resolves the container binding');
    }
}
