<?php

namespace App\Services\LocationDna;

use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Client;
use Throwable;

/**
 * LocationDnaPoiDistanceService — Phase C POI Distance Engine
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is the POI proximity layer for the Location DNA pipeline. It reads
 * geocoded coordinates from Phase B (PropertyLocationDna) and queries the Google
 * Places Nearby Search API to find the nearest result per POI category, persisting
 * one row per category to property_location_pois.
 *
 * This service MUST NEVER:
 *   - Call the geocoding API or modify Phase B records.
 *   - Connect to the AI marketing report or Property DNA persistence pipelines.
 *   - Perform AI or OpenAI calls of any kind.
 *   - Introduce routes, controllers, Blade views, Livewire components, or JavaScript.
 *   - Compute walk/bike/transit/coastal/compatibility/marketing scores.
 *   - Calculate drive times (travel_time_minutes column is reserved for a future phase).
 * ==================================================================================
 */
class LocationDnaPoiDistanceService
{
    private const NEARBY_API_URL = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json';

    private const EARTH_RADIUS_MILES = 3958.8;

    /**
     * All supported POI categories.
     *
     * Each entry maps a canonical category key to the parameters used for a
     * Google Places Nearby Search call. Two query strategies are used:
     *
     * NATIVE TYPE ('query_strategy' => 'native_type')
     *   The `type` parameter is passed directly to the Google Places API.
     *   These are first-class types in Google's taxonomy and return the most
     *   accurate, consistently-ranked results.
     *   Categories: grocery_store, school, hospital, park, pharmacy, gas_station,
     *                restaurant, gym, fitness_center, transit_station, coffee_shop,
     *                shopping_center.
     *
     * KEYWORD-BASED ('query_strategy' => 'keyword')
     *   No exact Google Place type exists for these categories. A `keyword`
     *   parameter is sent instead (with an optional `type` hint where it helps
     *   narrow results). Result quality depends on how operators have labelled
     *   their listings and may vary by region.
     *   Categories: beach, beach_access, boat_ramp, marina, waterfront_park,
     *                dog_park, golf_course.
     *
     * FUTURE PHASE (travel_time_minutes)
     *   The `travel_time_minutes` column is reserved. Drive-time calculation for
     *   any category can be added as a separate method without restructuring these
     *   definitions or the core POI fetch loop.
     *
     * Required keys per entry:
     *   'label'          — human-readable name stored as poi_subtype
     *   'query_strategy' — 'native_type' | 'keyword'
     *   'google_type'    — Google Places type string, or null for pure keyword searches
     *   'keyword'        — keyword string for keyword-based searches, or null for native types
     */
    public const CATEGORIES = [
        // ── Native Google Place type support ─────────────────────────────────
        'grocery_store'   => ['google_type' => 'grocery_or_supermarket', 'keyword' => null, 'label' => 'Grocery Store',   'query_strategy' => 'native_type'],
        'school'          => ['google_type' => 'school',                  'keyword' => null, 'label' => 'School',          'query_strategy' => 'native_type'],
        'hospital'        => ['google_type' => 'hospital',                'keyword' => null, 'label' => 'Hospital',        'query_strategy' => 'native_type'],
        'park'            => ['google_type' => 'park',                    'keyword' => null, 'label' => 'Park',            'query_strategy' => 'native_type'],
        'pharmacy'        => ['google_type' => 'pharmacy',                'keyword' => null, 'label' => 'Pharmacy',        'query_strategy' => 'native_type'],
        'gas_station'     => ['google_type' => 'gas_station',             'keyword' => null, 'label' => 'Gas Station',     'query_strategy' => 'native_type'],
        'restaurant'      => ['google_type' => 'restaurant',              'keyword' => null, 'label' => 'Restaurant',      'query_strategy' => 'native_type'],
        'gym'             => ['google_type' => 'gym',                     'keyword' => null, 'label' => 'Gym',             'query_strategy' => 'native_type'],
        'fitness_center'  => ['google_type' => 'gym',                     'keyword' => null, 'label' => 'Fitness Center',  'query_strategy' => 'native_type'],
        'transit_station' => ['google_type' => 'transit_station',         'keyword' => null, 'label' => 'Transit Station', 'query_strategy' => 'native_type'],
        'coffee_shop'     => ['google_type' => 'cafe',                    'keyword' => null, 'label' => 'Coffee Shop',     'query_strategy' => 'native_type'],
        'shopping_center' => ['google_type' => 'shopping_mall',           'keyword' => null, 'label' => 'Shopping Center', 'query_strategy' => 'native_type'],

        // ── Keyword-based searches (no native Google Place type available) ───
        // Result quality depends on operator-provided labels and may vary by region.
        'beach'           => ['google_type' => null,   'keyword' => 'beach',         'label' => 'Beach',           'query_strategy' => 'keyword'],
        'beach_access'    => ['google_type' => null,   'keyword' => 'beach access',  'label' => 'Beach Access',    'query_strategy' => 'keyword'],
        'boat_ramp'       => ['google_type' => null,   'keyword' => 'boat ramp',     'label' => 'Boat Ramp',       'query_strategy' => 'keyword'],
        'marina'          => ['google_type' => null,   'keyword' => 'marina',        'label' => 'Marina',          'query_strategy' => 'keyword'],
        'waterfront_park' => ['google_type' => 'park', 'keyword' => 'waterfront',    'label' => 'Waterfront Park', 'query_strategy' => 'keyword'],
        'dog_park'        => ['google_type' => null,   'keyword' => 'dog park',      'label' => 'Dog Park',        'query_strategy' => 'keyword'],
        'golf_course'     => ['google_type' => null,   'keyword' => 'golf course',   'label' => 'Golf Course',     'query_strategy' => 'keyword'],
    ];

    public function __construct(
        private readonly ?ClientInterface $httpClient = null,
    ) {}

    /**
     * Calculate and persist POI distances for a listing.
     *
     * Reads geocoded coordinates from the Phase B PropertyLocationDna record and
     * queries the Google Places Nearby Search API for the nearest result per
     * category. One row per category is persisted to property_location_pois.
     *
     * Cache behaviour:
     *   - If existing rows match the current geocoded_lat/lng, no API call is made
     *     and cached rows are returned with status='cached'.
     *   - If coordinates changed, all existing rows are deleted and all categories
     *     are recalculated.
     *
     * A single failed category does not abort the run. Each row independently
     * stores its status ('found' | 'not_found' | 'error') and error message.
     *
     * Output contract (8-key shape — identical across all paths):
     * [
     *     'success'      => bool,
     *     'status'       => string,      // 'completed' | 'cached' | 'skipped' | 'failed'
     *     'listing_type' => string,
     *     'listing_id'   => int,
     *     'results'      => array,       // per-category result rows; empty on skipped/failed
     *     'error'        => string|null,
     *     'source_lat'   => float|null,
     *     'source_lng'   => float|null,
     * ]
     *
     * Future extension point: add calculateCommuteDistance(string $listingType, int $listingId,
     * float $destLat, float $destLng) as an additional public method to support custom
     * commute destinations without restructuring the core POI flow.
     *
     * @param  string $listingType  The listing model type (e.g. 'seller_agent_auction').
     * @param  int    $listingId    The primary key of the listing record.
     * @return array                Approved Phase C eight-key output contract.
     */
    public function calculateForListing(string $listingType, int $listingId): array
    {
        try {
            // (a) Validate: Phase B record must exist
            $dnaRecord = PropertyLocationDna::where('listing_type', $listingType)
                ->where('listing_id', $listingId)
                ->first();

            if ($dnaRecord === null) {
                return $this->skippedOutput(
                    $listingType,
                    $listingId,
                    'No PropertyLocationDna record found for this listing',
                );
            }

            // (b) Validate: record must have geocode_status === 'geocoded'
            if ($dnaRecord->geocode_status !== 'geocoded') {
                return $this->skippedOutput(
                    $listingType,
                    $listingId,
                    "PropertyLocationDna geocode_status is '{$dnaRecord->geocode_status}', expected 'geocoded'",
                );
            }

            // (c) Validate: coordinates must be present
            if (blank($dnaRecord->geocoded_lat) || blank($dnaRecord->geocoded_lng)) {
                return $this->skippedOutput(
                    $listingType,
                    $listingId,
                    'PropertyLocationDna record is missing geocoded coordinates',
                );
            }

            $sourceLat = (float) $dnaRecord->geocoded_lat;
            $sourceLng = (float) $dnaRecord->geocoded_lng;

            // (d) Cache check: existing rows with matching source coordinates
            $existingRows = PropertyLocationPoi::where('listing_type', $listingType)
                ->where('listing_id', $listingId)
                ->get();

            if ($existingRows->isNotEmpty()) {
                $firstRow = $existingRows->first();
                $cachedLat = (float) $firstRow->source_lat;
                $cachedLng = (float) $firstRow->source_lng;

                if (
                    abs($cachedLat - $sourceLat) < 0.0000001 &&
                    abs($cachedLng - $sourceLng) < 0.0000001
                ) {
                    return $this->completedOutput(
                        $listingType,
                        $listingId,
                        $sourceLat,
                        $sourceLng,
                        $existingRows->toArray(),
                        'cached',
                    );
                }

                // Coordinates changed — clear all existing rows
                PropertyLocationPoi::where('listing_type', $listingType)
                    ->where('listing_id', $listingId)
                    ->delete();
            }

            // (e) API key guard
            $apiKey = config('services.google.places_key');
            if (blank($apiKey)) {
                return $this->failedOutput(
                    $listingType,
                    $listingId,
                    $sourceLat,
                    $sourceLng,
                    'missing_google_api_key',
                );
            }

            $client  = $this->httpClient ?? new Client();
            $results = [];

            // (f) Query Google Places for each category independently
            foreach (self::CATEGORIES as $category => $meta) {
                $row = $this->fetchAndPersistCategory(
                    client:      $client,
                    apiKey:      $apiKey,
                    listingType: $listingType,
                    listingId:   $listingId,
                    category:    $category,
                    meta:        $meta,
                    sourceLat:   $sourceLat,
                    sourceLng:   $sourceLng,
                );

                $results[] = $row;
            }

            return $this->completedOutput(
                $listingType,
                $listingId,
                $sourceLat,
                $sourceLng,
                $results,
                'completed',
            );

        } catch (Throwable $e) {
            return $this->failedOutput(
                $listingType,
                $listingId,
                null,
                null,
                $e->getMessage(),
            );
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Fetch the nearest Google Places result for one category and persist the row.
     *
     * Returns the persisted/updated model data as an array. Never throws —
     * any exception is caught and stored as status='error' on the row.
     */
    private function fetchAndPersistCategory(
        ClientInterface $client,
        string          $apiKey,
        string          $listingType,
        int             $listingId,
        string          $category,
        array           $meta,
        float           $sourceLat,
        float           $sourceLng,
    ): array {
        try {
            $queryParams = [
                'location' => "{$sourceLat},{$sourceLng}",
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

            if (empty($body['results'])) {
                return $this->persistPoiRow(
                    listingType:  $listingType,
                    listingId:    $listingId,
                    category:     $category,
                    meta:         $meta,
                    sourceLat:    $sourceLat,
                    sourceLng:    $sourceLng,
                    status:       'not_found',
                    error:        'Google Places returned zero results for this category',
                );
            }

            $place        = $body['results'][0];
            $poiLat       = (float) $place['geometry']['location']['lat'];
            $poiLng       = (float) $place['geometry']['location']['lng'];
            $distanceMiles = $this->haversineDistanceMiles($sourceLat, $sourceLng, $poiLat, $poiLng);

            return $this->persistPoiRow(
                listingType:   $listingType,
                listingId:     $listingId,
                category:      $category,
                meta:          $meta,
                sourceLat:     $sourceLat,
                sourceLng:     $sourceLng,
                status:        'found',
                error:         null,
                poiName:       $place['name'] ?? null,
                poiAddress:    $place['vicinity'] ?? null,
                poiLat:        $poiLat,
                poiLng:        $poiLng,
                distanceMiles: $distanceMiles,
            );

        } catch (Throwable $e) {
            return $this->persistPoiRow(
                listingType: $listingType,
                listingId:   $listingId,
                category:    $category,
                meta:        $meta,
                sourceLat:   $sourceLat,
                sourceLng:   $sourceLng,
                status:      'error',
                error:       $e->getMessage(),
            );
        }
    }

    /**
     * Upsert a single POI row (by listing_type + listing_id + poi_category).
     */
    private function persistPoiRow(
        string  $listingType,
        int     $listingId,
        string  $category,
        array   $meta,
        float   $sourceLat,
        float   $sourceLng,
        string  $status,
        ?string $error,
        ?string $poiName       = null,
        ?string $poiAddress    = null,
        ?float  $poiLat        = null,
        ?float  $poiLng        = null,
        ?float  $distanceMiles = null,
    ): array {
        $attributes = [
            'listing_type' => $listingType,
            'listing_id'   => $listingId,
            'poi_category' => $category,
        ];

        $values = [
            'poi_subtype'    => $meta['label'],
            'poi_name'       => $poiName,
            'poi_address'    => $poiAddress,
            'poi_lat'        => $poiLat,
            'poi_lng'        => $poiLng,
            'source_lat'     => $sourceLat,
            'source_lng'     => $sourceLng,
            'distance_miles' => $distanceMiles,
            'travel_time_minutes' => null,
            'data_source'    => 'google_places',
            'status'         => $status,
            'error'          => $error,
            'calculated_at'  => now(),
        ];

        $row = PropertyLocationPoi::updateOrCreate($attributes, $values);

        return $row->toArray();
    }

    /**
     * Haversine formula — straight-line distance between two coordinates in miles.
     */
    private function haversineDistanceMiles(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2,
    ): float {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_MILES * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    // =========================================================================
    // Output shape helpers — approved Phase C eight-key contract in all cases
    // =========================================================================

    private function completedOutput(
        string $listingType,
        int    $listingId,
        float  $sourceLat,
        float  $sourceLng,
        array  $results,
        string $status,
    ): array {
        return [
            'success'      => true,
            'status'       => $status,
            'listing_type' => $listingType,
            'listing_id'   => $listingId,
            'results'      => $results,
            'error'        => null,
            'source_lat'   => $sourceLat,
            'source_lng'   => $sourceLng,
        ];
    }

    private function skippedOutput(string $listingType, int $listingId, string $error): array
    {
        return [
            'success'      => false,
            'status'       => 'skipped',
            'listing_type' => $listingType,
            'listing_id'   => $listingId,
            'results'      => [],
            'error'        => $error,
            'source_lat'   => null,
            'source_lng'   => null,
        ];
    }

    private function failedOutput(
        string  $listingType,
        int     $listingId,
        ?float  $sourceLat,
        ?float  $sourceLng,
        ?string $error,
    ): array {
        return [
            'success'      => false,
            'status'       => 'failed',
            'listing_type' => $listingType,
            'listing_id'   => $listingId,
            'results'      => [],
            'error'        => $error,
            'source_lat'   => $sourceLat,
            'source_lng'   => $sourceLng,
        ];
    }
}
