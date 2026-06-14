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

            foreach ($sliced as $place) {
                $poiLat = (float) ($place['geometry']['location']['lat'] ?? 0);
                $poiLng = (float) ($place['geometry']['location']['lng'] ?? 0);

                $results[] = [
                    'category'       => $category,
                    'name'           => (string) ($place['name'] ?? ''),
                    'address'        => (string) ($place['vicinity'] ?? ''),
                    'latitude'       => $poiLat,
                    'longitude'      => $poiLng,
                    'distance_miles' => $this->haversineDistanceMiles($lat, $lng, $poiLat, $poiLng),
                    'source'         => 'google_places',
                ];
            }

            return $results;

        } catch (Throwable) {
            return [];
        }
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
