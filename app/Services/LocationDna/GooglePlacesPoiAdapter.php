<?php

namespace App\Services\LocationDna;

use App\Contracts\PoiLookupAdapterInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Client;
use Throwable;

class GooglePlacesPoiAdapter implements PoiLookupAdapterInterface
{
    private const NEARBY_API_URL = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json';

    private const EARTH_RADIUS_MILES = 3958.8;

    private const METERS_PER_MILE = 1609.34;

    /**
     * Confidence model for a rated commercial POI provider
     * (docs/canonical-field-mapping-spec.md §2). A rated place scores from
     * CONFIDENCE_RATED_BASE up toward CONFIDENCE_RATED_BASE + CONFIDENCE_RATED_SPAN
     * as review volume rises, saturating at REVIEW_SATURATION reviews. A place
     * Google returned but did not rate carries CONFIDENCE_STRUCTURAL (existence
     * only — no quality signal is fabricated).
     */
    private const CONFIDENCE_STRUCTURAL  = 0.5;
    private const CONFIDENCE_RATED_BASE  = 0.6;
    private const CONFIDENCE_RATED_SPAN  = 0.3;
    private const REVIEW_SATURATION      = 200;

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

    public function __construct(
        private readonly ?ClientInterface $httpClient = null,
    ) {}

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

        $apiKey = config('services.google.places_key');
        if (blank($apiKey)) {
            return [];
        }

        try {
            $client = $this->httpClient ?? new Client();
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
                    'confidence'     => $this->deriveConfidence($rating, $reviewCount),
                    'last_refreshed' => $fetchedAt,
                ];
            }

            return $results;

        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Derive a 0.0–1.0 confidence for a POI item from its rating signal
     * (docs/canonical-field-mapping-spec.md §2). Unrated → structural existence
     * confidence; rated → base scaled toward the cap by review volume.
     */
    private function deriveConfidence(?float $rating, int $reviewCount): float
    {
        if ($rating === null) {
            return self::CONFIDENCE_STRUCTURAL;
        }

        $reviewFactor = min(1.0, max(0, $reviewCount) / self::REVIEW_SATURATION);

        return round(
            self::CONFIDENCE_RATED_BASE + (self::CONFIDENCE_RATED_SPAN * $reviewFactor),
            3
        );
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
