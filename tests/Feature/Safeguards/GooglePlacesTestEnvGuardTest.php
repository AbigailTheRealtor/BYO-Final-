<?php

namespace Tests\Feature\Safeguards;

use App\Services\LocationDna\GooglePlacesPoiAdapter;
use GuzzleHttp\ClientInterface;
use RuntimeException;
use Tests\Support\BlocksGooglePlacesHttpClient;
use Tests\TestCase;

/**
 * Proves the test-environment hard block for Google Places (req 1 + 9 of the
 * incident remediation):
 *   - the API key is blank/null in testing;
 *   - a leaked live key would abort the suite;
 *   - any outbound Google Maps/Places request is blocked, not sent.
 *
 * See docs/investigations/Google-Places-Root-Cause-Analysis.md.
 */
class GooglePlacesTestEnvGuardTest extends TestCase
{
    /** The Places key must be blank in the testing environment. */
    public function test_google_places_api_key_is_blank_in_testing(): void
    {
        $this->assertTrue(
            blank(config('services.google.places_key')),
            'GOOGLE_PLACES_API_KEY must be blank during tests so no live NearbySearch call can authenticate.'
        );
    }

    /** No live Google Places key may be present in the process environment. */
    public function test_no_live_google_places_key_is_present(): void
    {
        $this->assertNull(
            static::detectLiveGooglePlacesKey(),
            'A live GOOGLE_PLACES_API_KEY is leaking into the test environment. Unset it.'
        );
    }

    /** The guard throws a RuntimeException when a key is present under testing. */
    public function test_guard_throws_when_a_live_key_is_injected(): void
    {
        $original = $_SERVER['GOOGLE_PLACES_API_KEY'] ?? null;
        $_SERVER['GOOGLE_PLACES_API_KEY'] = 'AIzaSyFAKE-not-a-real-key-000000000000';

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/live GOOGLE_PLACES_API_KEY/');
            $this->guardAgainstLiveGooglePlacesKey();
        } finally {
            if ($original === null) {
                unset($_SERVER['GOOGLE_PLACES_API_KEY']);
            } else {
                $_SERVER['GOOGLE_PLACES_API_KEY'] = $original;
            }
        }
    }

    /** The container HTTP client is the fail-loud blocking client in testing. */
    public function test_container_http_client_is_the_blocking_client(): void
    {
        $this->assertInstanceOf(
            BlocksGooglePlacesHttpClient::class,
            app(ClientInterface::class)
        );
    }

    /** A direct outbound request to a Google host is blocked, not sent. */
    public function test_outbound_google_request_is_blocked(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/BLOCKED live Google Places\/Maps request/');

        app(ClientInterface::class)->request(
            'GET',
            'https://maps.googleapis.com/maps/api/place/nearbysearch/json'
        );
    }

    /**
     * End-to-end: even with a (fake) key set and the kill switch on, the adapter
     * cannot reach the network because the blocking client throws — the adapter
     * catches it and degrades to empty results. Proves no live call escapes.
     */
    public function test_adapter_cannot_reach_network_via_container_client(): void
    {
        config([
            'services.google.places_key' => 'fake-key-for-this-test',
            'google_places.enabled'      => true,
        ]);

        // No injected client → resolves the blocking client from the container.
        $results = (new GooglePlacesPoiAdapter())->search(27.95, -82.45, 'schools', 5, 5);

        $this->assertSame([], $results, 'Adapter must degrade to empty results when the network is blocked.');
    }
}
