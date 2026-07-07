<?php

namespace Tests\Unit\Services\LocationDna;

use App\Models\PropertyLocationDna;
use App\Services\LocationDna\GooglePlacesPoiAdapter;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Proves the two Google Places NearbySearch callers resolve their HTTP client
 * from the service container (req 2 of the incident remediation), so a mocked
 * client is always used and no bare `new Client()` can reach the live network.
 *
 * These tests set a fake API key and, because they exercise the pipeline with a
 * MOCKED provider, opt in to the kill switch — the documented exception in the
 * remediation spec.
 */
class GooglePlacesHttpClientBindingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.places_key' => 'fake-test-key',
            'google_places.enabled'      => true, // explicit opt-in: mocked provider
            'location_dna.poi.tile_precision' => null,
            'cache.default'              => 'array',
        ]);
        Cache::flush();
    }

    /** The default container binding is a real Guzzle client (production behaviour). */
    public function test_default_binding_returns_a_guzzle_client(): void
    {
        $this->assertInstanceOf(Client::class, app(ClientInterface::class));
    }

    /** Path B: GooglePlacesPoiAdapter uses the container-bound (mocked) client. */
    public function test_adapter_uses_container_bound_mock_client(): void
    {
        $mock = $this->createMock(ClientInterface::class);
        $mock->expects($this->atLeastOnce())
            ->method('request')
            ->willReturn(new Response(200, [], json_encode([
                'results' => [[
                    'name'     => 'Mock School',
                    'vicinity' => '123 Test St',
                    'geometry' => ['location' => ['lat' => 27.95, 'lng' => -82.45]],
                    'rating'   => 4.5,
                    'user_ratings_total' => 120,
                ]],
            ])));

        $this->app->instance(ClientInterface::class, $mock);

        // No injected client — must resolve the mock from the container.
        $adapter = new GooglePlacesPoiAdapter();
        $results = $adapter->search(27.95, -82.45, 'schools', 5, 5);

        $this->assertNotEmpty($results);
        $this->assertSame('Mock School', $results[0]['name']);
    }

    /** Path A: LocationDnaPoiDistanceService uses the container-bound (mocked) client. */
    public function test_distance_service_uses_container_bound_mock_client(): void
    {
        PropertyLocationDna::create([
            'listing_type'   => 'seller_agent_auction',
            'listing_id'     => 4242,
            'geocode_status' => 'geocoded',
            'geocoded_lat'   => 27.95,
            'geocoded_lng'   => -82.45,
        ]);

        $mock = $this->createMock(ClientInterface::class);
        // Every NearbySearch category call goes through the mock; empty results are
        // fine — we only assert the container client was used, not the network.
        $mock->expects($this->atLeastOnce())
            ->method('request')
            ->willReturn(new Response(200, [], json_encode(['results' => []])));

        $this->app->instance(ClientInterface::class, $mock);

        // No injected client (null) — must resolve the mock from the container.
        $service = new LocationDnaPoiDistanceService();
        $output  = $service->calculateForListing('seller_agent_auction', 4242);

        $this->assertTrue($output['success']);
        $this->assertSame('completed', $output['status']);
    }
}
