<?php

namespace App\Services\LocationDna;

use App\Contracts\NearbyPoiFetcherInterface;
use App\Contracts\PoiLookupAdapterInterface;
use GuzzleHttp\ClientInterface;
use Throwable;

class GooglePlacesPoiAdapter implements PoiLookupAdapterInterface, NearbyPoiFetcherInterface
{
    private const NEARBY_API_URL = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json';

    private const EARTH_RADIUS_MILES = 3958.8;

    private const METERS_PER_MILE = 1609.34;

    /**
     * Maps canonical category slugs to Google Places API parameters.
     *
     * 'type'    — Google Places type string (null for keyword-only searches)
     * 'keyword' — Keyword string sent alongside the type (null for native type searches)
     */
    private const CATEGORY_MAP = [
        'schools'   => ['type' => 'school',        'keyword' => null],
        'parks'     => ['type' => 'park',           'keyword' => null],
        'shopping'  => ['type' => 'shopping_mall',  'keyword' => null],
        'hospitals' => ['type' => 'hospital',       'keyword' => null],
        'gyms'      => ['type' => 'gym',            'keyword' => null],
        'airports'  => ['type' => 'airport',        'keyword' => null],
        'downtown'  => ['type' => null,             'keyword' => 'downtown'],
    ];

    private readonly PoiConfidenceScorer $confidenceScorer;

    public function __construct(
        private readonly ?ClientInterface $httpClient = null,
        ?PoiConfidenceScorer $confidenceScorer = null,
    ) {
        // Batch 3: the confidence formula now lives in one place and is reused by the
        // persistence path (LocationDnaPoiDistanceService's single writer). Injectable
        // so a test can prove delegation; production defaults to a fresh scorer.
        $this->confidenceScorer = $confidenceScorer ?? new PoiConfidenceScorer();
    }

    /**
     * Resolve the outbound HTTP client from the service container so tests can
     * bind a fake/blocking client.
     *
     * Phase 0 / S1b: the former `new Client()` fallback is removed. A bare client
     * cannot be intercepted by Http::fake() or by the container binding, which is
     * how the test suite reached live Google. If the binding is absent we now fail
     * loudly rather than silently opening a socket.
     */
    private function resolveHttpClient(): ClientInterface
    {
        return app(ClientInterface::class);
    }

    /**
     * {@inheritDoc}
     *
     * Queries the Google Places Nearby Search API for the given category.
     * Catches all Guzzle/HTTP errors and returns [] so callers degrade gracefully.
     */
    public function search(float $lat, float $lng, string $category, int $radiusMiles, int $limit): array
    {
        $categoryParams = self::CATEGORY_MAP[$category] ?? null;
        if ($categoryParams === null) {
            return [];
        }

        // Phase 0 / S2 — master kill switch. Short-circuits before any HTTP call.
        // Fail-safe default is DISABLED (config/google_places.php). Until this
        // commit the switch was referenced by zero application code.
        if (! config('google_places.enabled', false)) {
            return [];
        }

        $apiKey = config('services.google.places_key');
        if (blank($apiKey)) {
            return [];
        }

        try {
            // Resolve from the container (bound in AppServiceProvider) so tests can
            // inject a fake/blocking client. There is no bare-client fallback.
            $client = $this->httpClient ?? $this->resolveHttpClient();
            $timeout = (int) config('location_dna.poi.timeout', 5);
            $radiusMeters = (int) round($radiusMiles * self::METERS_PER_MILE);

            $queryParams = [
                'location' => "{$lat},{$lng}",
                'radius'   => $radiusMeters,
                'key'      => $apiKey,
            ];

            if (!empty($categoryParams['type'])) {
                $queryParams['type'] = $categoryParams['type'];
            }

            if (!empty($categoryParams['keyword'])) {
                $queryParams['keyword'] = $categoryParams['keyword'];
            }

            $response = $client->request('GET', self::NEARBY_API_URL, [
                'query'   => $queryParams,
                'timeout' => $timeout,
            ]);

            $body = json_decode((string) $response->getBody(), true);

            if (empty($body['results'])) {
                return [];
            }

            $results = [];
            $sliced  = array_slice($body['results'], 0, $limit);

            // All items in one response share a fetch time (canonical-field-mapping-spec §4).
            $fetchedAt = now()->toIso8601String();

            foreach ($sliced as $place) {
                $poiLat = (float) ($place['geometry']['location']['lat'] ?? 0);
                $poiLng = (float) ($place['geometry']['location']['lng'] ?? 0);

                // Read the quality signal locally to derive confidence. These raw
                // fields are NOT added to the output contract — only the derived
                // 'confidence' is.
                $rating      = isset($place['rating']) ? (float) $place['rating'] : null;
                $reviewCount = (int) ($place['user_ratings_total'] ?? 0);

                $results[] = [
                    'category'       => $category,
                    'name'           => (string) ($place['name'] ?? ''),
                    'address'        => (string) ($place['vicinity'] ?? ''),
                    'latitude'       => $poiLat,
                    'longitude'      => $poiLng,
                    'distance_miles' => $this->haversineDistanceMiles($lat, $lng, $poiLat, $poiLng),
                    'source'         => 'google_places',
                    'confidence'     => $this->confidenceScorer->score($rating, $reviewCount),
                    'last_refreshed' => $fetchedAt,
                ];
            }

            return $results;

        } catch (Throwable) {
            return [];
        }
    }

    /**
     * {@inheritDoc}
     *
     * Raw Google Places Nearby Search for the production Location DNA path. Returns the
     * provider-native `results` rows unchanged — the caller's exclusion rules, transit
     * dedup, ranking (`PoiCandidate::fromGooglePlaces()`), and persistence read that shape.
     *
     * This mirrors the query the service issued inline before Phase 1 Batch 1: `rankby=distance`
     * with the category's `google_type`/`keyword`, and NO `timeout` option — byte-identical to
     * the former `LocationDnaPoiDistanceService::fetchRawCandidates()` request.
     *
     * Unlike {@see search()}, this method does NOT catch exceptions: per
     * {@see \App\Contracts\NearbyPoiFetcherInterface} they must propagate so the caller can
     * persist `status = 'error'` rather than `status = 'not_found'`. The kill-switch and
     * blank-key guards below are defence-in-depth for standalone/registry use; in the
     * production flow the service's whole-run guards short-circuit before this is reached.
     */
    public function fetchNearby(float $lat, float $lng, array $meta): array
    {
        if (! config('google_places.enabled', false)) {
            return [];
        }

        $apiKey = config('services.google.places_key');
        if (blank($apiKey)) {
            return [];
        }

        $client = $this->httpClient ?? $this->resolveHttpClient();

        $queryParams = [
            'location' => "{$lat},{$lng}",
            'rankby'   => 'distance',
            'key'      => $apiKey,
        ];

        if (! empty($meta['google_type'])) {
            $queryParams['type'] = $meta['google_type'];
        }

        if (! empty($meta['keyword'])) {
            $queryParams['keyword'] = $meta['keyword'];
        }

        $response = $client->request('GET', self::NEARBY_API_URL, [
            'query' => $queryParams,
        ]);

        $body = json_decode((string) $response->getBody(), true);

        return $body['results'] ?? [];
    }

    private function haversineDistanceMiles(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_MILES * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
