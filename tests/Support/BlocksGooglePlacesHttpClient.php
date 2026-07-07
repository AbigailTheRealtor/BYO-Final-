<?php

namespace Tests\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * A container-bound Guzzle client used in the test environment that FAILS LOUDLY
 * on any outbound request. It exists so that the Google Places NearbySearch
 * callers — which resolve their client from the container — can never reach the
 * live network in a test that forgot to inject a mock.
 *
 * Requests to Google Maps/Places hosts get a pointed error naming the incident;
 * any other unmocked host also throws (stray-request guard). Tests that legitimately
 * exercise the pipeline bind their own mock client, which replaces this one.
 *
 * See docs/investigations/Google-Places-Root-Cause-Analysis.md.
 */
class BlocksGooglePlacesHttpClient extends Client
{
    /** Hosts whose outbound requests are treated as the billable Places incident path. */
    public const GOOGLE_HOSTS = [
        'maps.googleapis.com',
        'places.googleapis.com',
    ];

    public function request(string $method, $uri = '', array $options = []): ResponseInterface
    {
        $this->refuse((string) $uri);
    }

    public function requestAsync(string $method, $uri = '', array $options = []): PromiseInterface
    {
        $this->refuse((string) $uri);
    }

    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        $this->refuse((string) $request->getUri());
    }

    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface
    {
        $this->refuse((string) $request->getUri());
    }

    /**
     * @throws RuntimeException always
     * @return never
     */
    private function refuse(string $uri): void
    {
        $host = parse_url($uri, PHP_URL_HOST) ?: $uri;

        if ($this->isGoogleHost((string) $host)) {
            throw new RuntimeException(
                "BLOCKED live Google Places/Maps request to '{$host}' during testing. "
                . 'No test may make a real NearbySearch call. Inject a mock ClientInterface, '
                . 'use Bus::fake(), or keep the Google Places kill switch off. See '
                . 'docs/investigations/Google-Places-Root-Cause-Analysis.md.'
            );
        }

        throw new RuntimeException(
            "BLOCKED stray outbound HTTP request to '{$host}' during testing. "
            . 'Mock the HTTP client for this call instead of hitting the network.'
        );
    }

    private function isGoogleHost(string $host): bool
    {
        foreach (self::GOOGLE_HOSTS as $blocked) {
            if (strcasecmp($host, $blocked) === 0) {
                return true;
            }
        }

        return false;
    }
}
