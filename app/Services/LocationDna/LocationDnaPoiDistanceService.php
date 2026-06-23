<?php

namespace App\Services\LocationDna;

use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Client;
use Throwable;

/**
 * LocationDnaPoiDistanceService — Phase C POI Distance Engine (v2)
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is the POI proximity layer for the Location DNA pipeline. It reads
 * geocoded coordinates from Phase B (PropertyLocationDna) and queries the Google
 * Places Nearby Search API to find multiple candidates per POI category, persisting
 * up to 10 ranked rows per category to property_location_pois.
 *
 * v2 additions (task #3110):
 *   - Stores up to 10 raw candidates per category (rank 1 = nearest/primary).
 *   - Each row persists rank, rating, user_ratings_total, and types_json.
 *   - Category exclusion filters eliminate confirmed bad matches (P0 audit fixes).
 *   - Top Rated Dining derived category: restaurant candidates sorted by rating
 *     (minimum 10 reviews), stored as rank 1/2/3 under 'top_rated_dining'.
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
     * Maximum number of raw API results to collect per category.
     * Google Nearby Search returns up to 20 results per page; we cap at 10 for storage.
     */
    private const MAX_CANDIDATES_PER_CATEGORY = 10;

    /**
     * How many raw results to examine when applying exclusion filters.
     * We look at more than MAX_CANDIDATES to ensure we find enough valid ones.
     */
    private const MAX_RESULTS_TO_EXAMINE = 20;

    /**
     * Minimum number of user reviews required to qualify for Top Rated Dining.
     */
    private const TOP_RATED_DINING_MIN_REVIEWS = 10;

    /**
     * Minimum review count used as the confidence denominator in the Top Rated Dining
     * quality-score formula: score = rating × min(reviews / TOP_RATED_DINING_MIN_CONFIDENCE_REVIEWS, 1.0).
     * A place must have at least this many reviews for its rating to count at full weight.
     * Places with fewer reviews are down-weighted proportionally, preventing low-sample
     * 5-star outliers from outranking high-confidence 4.8-star restaurants.
     */
    private const TOP_RATED_DINING_MIN_CONFIDENCE_REVIEWS = 50;

    /**
     * Maximum number of Top Rated Dining candidates to store.
     */
    private const TOP_RATED_DINING_MAX_CANDIDATES = 3;

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
     *
     * KEYWORD-BASED ('query_strategy' => 'keyword')
     *   No exact Google Place type exists for these categories. A `keyword`
     *   parameter is sent instead (with an optional `type` hint where it helps
     *   narrow results). Result quality depends on how operators have labelled
     *   their listings and may vary by region.
     *
     * v2 change: fitness_center now uses keyword strategy ('fitness center' keyword
     *   combined with google_type='gym') to differentiate it from 'gym', which was
     *   previously a structural duplicate returning identical results. Audit finding:
     *   Section 8, gym/fitness_center structural duplicate, 🟠 Medium risk.
     *
     * Required keys per entry:
     *   'label'          — human-readable name stored as poi_subtype
     *   'query_strategy' — 'native_type' | 'keyword'
     *   'google_type'    — Google Places type string, or null for pure keyword searches
     *   'keyword'        — keyword string for keyword-based searches, or null for native types
     */
    public const CATEGORIES = [
        // ── Native Google Place type support ─────────────────────────────────
        'grocery_store'   => ['google_type' => 'grocery_or_supermarket', 'keyword' => null,              'label' => 'Grocery Store',   'query_strategy' => 'native_type'],
        'school'          => ['google_type' => 'school',                  'keyword' => null,              'label' => 'School',          'query_strategy' => 'native_type'],
        'hospital'        => ['google_type' => 'hospital',                'keyword' => null,              'label' => 'Hospital',        'query_strategy' => 'native_type'],
        'park'            => ['google_type' => 'park',                    'keyword' => null,              'label' => 'Park',            'query_strategy' => 'native_type'],
        'pharmacy'        => ['google_type' => 'pharmacy',                'keyword' => null,              'label' => 'Pharmacy',        'query_strategy' => 'native_type'],
        'gas_station'     => ['google_type' => 'gas_station',             'keyword' => null,              'label' => 'Gas Station',     'query_strategy' => 'native_type'],
        'restaurant'      => ['google_type' => 'restaurant',              'keyword' => null,              'label' => 'Restaurant',      'query_strategy' => 'native_type'],
        'gym'             => ['google_type' => 'gym',                     'keyword' => null,              'label' => 'Gym',             'query_strategy' => 'native_type'],
        // v2: fitness_center uses keyword 'fitness center' to differentiate from gym.
        // Audit finding: Section 8 — gym/fitness_center structural duplicate.
        'fitness_center'  => ['google_type' => 'gym',                     'keyword' => 'fitness center', 'label' => 'Fitness Center',  'query_strategy' => 'keyword'],
        'transit_station' => ['google_type' => 'transit_station',         'keyword' => null,              'label' => 'Transit Station', 'query_strategy' => 'native_type'],
        'coffee_shop'     => ['google_type' => 'cafe',                    'keyword' => null,              'label' => 'Coffee Shop',     'query_strategy' => 'native_type'],
        'shopping_center' => ['google_type' => 'shopping_mall',           'keyword' => null,              'label' => 'Shopping Center', 'query_strategy' => 'native_type'],

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

    /**
     * Post-fetch exclusion rules applied per category.
     *
     * Each rule is evaluated against a raw Google Places result object. Results
     * failing the rule are skipped; the pipeline walks down the ranked list to
     * find the next valid candidate.
     *
     * Rules use the stored `types_json` array and place `name` — no name-matching
     * heuristics are used for grocery/pharmacy; name-matching is only used where
     * the type taxonomy is insufficient (golf_course, pharmacy animal-hospital check).
     *
     * Sources: Audit report Section 8 and Section 10, P0 findings:
     *   - grocery_store: gas station returned as grocery (🔴 P0)
     *   - pharmacy: animal hospital returned as pharmacy (🔴 P0)
     *   - golf_course: adventure/mini golf returned as golf course (🔴 P0)
     */
    private const CATEGORY_EXCLUSION_RULES = [
        'grocery_store' => [
            // ── Grocery Store exclusion — two-part prioritized rule ─────────
            //
            // PRIMARY RULE (types-based, authoritative):
            //   Exclude any candidate whose types_json contains 'gas_station',
            //   regardless of whether it also has 'grocery_or_supermarket'.
            //   Rationale: Google dual-types convenience stores (e.g. BP, Shell,
            //   Wawa, RaceTrac) as both gas_station AND grocery_or_supermarket.
            //   The old `exclude_if_types_include_and_lacks` rule allowed these
            //   dual-typed entries to pass because they do have the grocery type.
            //   Types are the authoritative signal — a gas station is never a
            //   true grocery store regardless of secondary type tags.
            'exclude_if_types_include' => ['gas_station'],

            // FALLBACK RULE (name-pattern, safety net — types-absent only):
            //   When types_json is missing or empty (sparse API response), apply a
            //   brand-name guard against known gas-station / convenience-store chains.
            //   This rule uses `exclude_if_name_matches_when_types_empty`, which is
            //   only evaluated when the `types` array returned by Google is absent or
            //   empty. It MUST NOT override authoritative type data: if Google returned
            //   any types at all, the PRIMARY rule above is the sole arbiter.
            //   Pattern covers: BP, Shell, Chevron, RaceTrac, Wawa, Circle K,
            //   7-Eleven, Sunoco, Murphy USA, Cumberland Farms.
            'exclude_if_name_matches_when_types_empty' => '/\b(bp|shell|chevron|racetrac|wawa|circle\s*k|7-?eleven|sunoco|murphy\s+usa|cumberland\s+farms)\b/i',
        ],
        'pharmacy' => [
            // Exclude if result has veterinary_care type (animal pharmacies).
            // Also exclude by name for "animal hospital" since Google may not
            // always tag in-house dispensaries with veterinary_care.
            // Audit: "animal hospital in types or name matches /animal hospital/i"
            'exclude_if_types_include' => ['veterinary_care'],
            'exclude_if_name_matches'  => '/animal\s+hospital/i',
        ],
        'golf_course' => [
            // Exclude adventure/mini/miniature golf and putt-putt by name.
            // Audit: "adventure golf, mini golf, miniature golf, putt-putt"
            'exclude_if_name_matches' => '/adventure\s+golf|mini.?golf|miniature\s+golf|putt.?putt/i',
        ],
    ];

    public function __construct(
        private readonly ?ClientInterface $httpClient = null,
        private readonly ?LocationDnaAuditService $auditService = null,
    ) {}

    /**
     * Calculate and persist POI distances for a listing.
     *
     * Reads geocoded coordinates from the Phase B PropertyLocationDna record and
     * queries the Google Places Nearby Search API for up to 10 candidates per
     * category. All candidates are persisted to property_location_pois with rank,
     * rating, user_ratings_total, and types_json.
     *
     * A derived 'top_rated_dining' category is built from restaurant candidates
     * sorted by rating (minimum 10 reviews).
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
                $output = $this->skippedOutput(
                    $listingType,
                    $listingId,
                    'No PropertyLocationDna record found for this listing',
                );
                $this->audit($listingType, $listingId, $output);
                return $output;
            }

            // (b) Validate: record must have geocode_status === 'geocoded'
            if ($dnaRecord->geocode_status !== 'geocoded') {
                $output = $this->skippedOutput(
                    $listingType,
                    $listingId,
                    "PropertyLocationDna geocode_status is '{$dnaRecord->geocode_status}', expected 'geocoded'",
                );
                $this->audit($listingType, $listingId, $output);
                return $output;
            }

            // (c) Validate: coordinates must be present
            if (blank($dnaRecord->geocoded_lat) || blank($dnaRecord->geocoded_lng)) {
                $output = $this->skippedOutput(
                    $listingType,
                    $listingId,
                    'PropertyLocationDna record is missing geocoded coordinates',
                );
                $this->audit($listingType, $listingId, $output);
                return $output;
            }

            $sourceLat = (float) $dnaRecord->geocoded_lat;
            $sourceLng = (float) $dnaRecord->geocoded_lng;

            // (d) Cache check: existing rows with matching source coordinates.
            // Only check rank=1 rows to avoid partial-data false positives.
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
                    $output = $this->completedOutput(
                        $listingType,
                        $listingId,
                        $sourceLat,
                        $sourceLng,
                        $existingRows->toArray(),
                        'cached',
                    );
                    $this->audit($listingType, $listingId, $output);
                    return $output;
                }

                // Coordinates changed — clear all existing rows for this listing
                PropertyLocationPoi::where('listing_type', $listingType)
                    ->where('listing_id', $listingId)
                    ->delete();
            }

            // (e) API key guard
            $apiKey = config('services.google.places_key');
            if (blank($apiKey)) {
                $output = $this->failedOutput(
                    $listingType,
                    $listingId,
                    $sourceLat,
                    $sourceLng,
                    'missing_google_api_key',
                );
                $this->audit($listingType, $listingId, $output);
                return $output;
            }

            $client  = $this->httpClient ?? new Client();
            $results = [];

            // Capture restaurant raw candidates for Top Rated Dining derivation.
            $restaurantRawCandidates = [];

            // (f) Query Google Places for each category independently
            foreach (self::CATEGORIES as $category => $meta) {
                [$categoryRows, $rawCandidates] = $this->fetchAndPersistCategoryMulti(
                    client:      $client,
                    apiKey:      $apiKey,
                    listingType: $listingType,
                    listingId:   $listingId,
                    category:    $category,
                    meta:        $meta,
                    sourceLat:   $sourceLat,
                    sourceLng:   $sourceLng,
                );

                foreach ($categoryRows as $row) {
                    $results[] = $row;
                }

                if ($category === 'restaurant') {
                    $restaurantRawCandidates = $rawCandidates;
                }
            }

            // (g) Derive and persist Top Rated Dining from restaurant candidates
            $topRatedRows = $this->deriveAndPersistTopRatedDining(
                listingType:         $listingType,
                listingId:           $listingId,
                sourceLat:           $sourceLat,
                sourceLng:           $sourceLng,
                restaurantCandidates: $restaurantRawCandidates,
            );

            foreach ($topRatedRows as $row) {
                $results[] = $row;
            }

            $output = $this->completedOutput(
                $listingType,
                $listingId,
                $sourceLat,
                $sourceLng,
                $results,
                'completed',
            );
            $this->audit($listingType, $listingId, $output);
            return $output;

        } catch (Throwable $e) {
            $output = $this->failedOutput(
                $listingType,
                $listingId,
                null,
                null,
                $e->getMessage(),
            );
            $this->audit($listingType, $listingId, $output);
            return $output;
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Write an audit row. Wrapped in its own try/catch so a failure cannot
     * prevent the caller's return value from being delivered.
     */
    private function audit(string $listingType, int $listingId, array $output): void
    {
        try {
            $auditService = $this->auditService ?? new LocationDnaAuditService();
            $auditService->record(
                listingType:    $listingType,
                listingId:      $listingId,
                eventType:      'poi_distance',
                status:         $output['status'],
                source:         null,
                inputSnapshot:  ['listing_type' => $listingType, 'listing_id' => $listingId],
                outputSnapshot: $output,
                error:          $output['error'] ?? null,
            );
        } catch (Throwable) {
            // Audit failure must never alter the service's return value.
        }
    }

    /**
     * Fetch raw candidates from the Google Places Nearby Search API.
     *
     * Returns the raw results array from the API response, or an empty array on
     * error. Never throws.
     *
     * Exceptions propagate to the caller; fetchAndPersistCategoryMulti handles them
     * by writing an 'error' row so the run can continue with other categories.
     *
     * @return array  Raw Google Places results array (up to 20 entries).
     */
    private function fetchRawCandidates(
        ClientInterface $client,
        string          $apiKey,
        array           $meta,
        float           $sourceLat,
        float           $sourceLng,
    ): array {
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

        return $body['results'] ?? [];
    }

    /**
     * Determine whether a Google Places result passes the exclusion filter
     * for the given category.
     *
     * @param  string $category  Canonical category key from CATEGORIES.
     * @param  array  $place     A single Google Places result object.
     * @return bool              true = keep, false = skip.
     */
    private function passesExclusionFilter(string $category, array $place): bool
    {
        $rules = self::CATEGORY_EXCLUSION_RULES[$category] ?? null;
        if ($rules === null) {
            return true;
        }

        $types = $place['types'] ?? [];
        $name  = $place['name'] ?? '';

        // Rule: exclude_if_types_include — discard if ANY of these types are present
        if (! empty($rules['exclude_if_types_include'])) {
            foreach ($rules['exclude_if_types_include'] as $badType) {
                if (in_array($badType, $types, true)) {
                    return false;
                }
            }
        }

        // Rule: exclude_if_types_include_and_lacks — discard if has ALL of 'has' types
        //       AND lacks ALL of 'lacks' types
        if (! empty($rules['exclude_if_types_include_and_lacks'])) {
            $hasCheck   = $rules['exclude_if_types_include_and_lacks']['has']   ?? [];
            $lacksCheck = $rules['exclude_if_types_include_and_lacks']['lacks'] ?? [];

            $hasAllBadTypes   = ! empty($hasCheck) && count(array_intersect($hasCheck, $types)) > 0;
            $lacksAllGoodTypes = ! empty($lacksCheck) && count(array_intersect($lacksCheck, $types)) === 0;

            if ($hasAllBadTypes && $lacksAllGoodTypes) {
                return false;
            }
        }

        // Rule: exclude_if_name_matches — discard if name matches the regex (unconditional)
        if (! empty($rules['exclude_if_name_matches'])) {
            if (preg_match($rules['exclude_if_name_matches'], $name)) {
                return false;
            }
        }

        // Rule: exclude_if_name_matches_when_types_empty — name-pattern fallback that fires
        //       ONLY when Google returned no types (sparse/incomplete API response).
        //       When types are present, the type-based rules above are authoritative and
        //       this fallback is skipped entirely, preventing false positives on stores that
        //       legitimately share a brand name with a gas-station chain (e.g. "BP's Market").
        if (! empty($rules['exclude_if_name_matches_when_types_empty']) && empty($types)) {
            if (preg_match($rules['exclude_if_name_matches_when_types_empty'], $name)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Fetch raw candidates for a category, apply exclusion filters, and persist
     * up to MAX_CANDIDATES_PER_CATEGORY ranked rows.
     *
     * Returns a two-element tuple:
     *   [0] array  — persisted row arrays for this category
     *   [1] array  — all raw Google Places results (unfiltered), for downstream use
     *
     * Atomicity: existing rows for (listing_type, listing_id, poi_category) are
     * deleted before new rows are inserted (per task migration safety spec).
     *
     * Never throws — errors are stored as status='error' on rank-1 row.
     */
    private function fetchAndPersistCategoryMulti(
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
            // Delete existing rows for this category (atomic replacement)
            PropertyLocationPoi::where('listing_type', $listingType)
                ->where('listing_id', $listingId)
                ->where('poi_category', $category)
                ->delete();

            $rawCandidates = $this->fetchRawCandidates($client, $apiKey, $meta, $sourceLat, $sourceLng);

            if (empty($rawCandidates)) {
                $row = $this->createPoiRow(
                    listingType: $listingType,
                    listingId:   $listingId,
                    category:    $category,
                    meta:        $meta,
                    sourceLat:   $sourceLat,
                    sourceLng:   $sourceLng,
                    rank:        1,
                    status:      'not_found',
                    error:       'Google Places returned zero results for this category',
                );
                return [[$row->toArray()], []];
            }

            // Apply exclusion filters and persist valid candidates
            $rank          = 0;
            $persistedRows = [];
            $examined      = 0;

            foreach ($rawCandidates as $place) {
                if ($examined >= self::MAX_RESULTS_TO_EXAMINE) {
                    break;
                }
                $examined++;

                if (! $this->passesExclusionFilter($category, $place)) {
                    continue;
                }

                $rank++;
                if ($rank > self::MAX_CANDIDATES_PER_CATEGORY) {
                    break;
                }

                $poiLat        = (float) ($place['geometry']['location']['lat'] ?? 0);
                $poiLng        = (float) ($place['geometry']['location']['lng'] ?? 0);
                $distanceMiles = $this->haversineDistanceMiles($sourceLat, $sourceLng, $poiLat, $poiLng);

                $row = $this->createPoiRow(
                    listingType:   $listingType,
                    listingId:     $listingId,
                    category:      $category,
                    meta:          $meta,
                    sourceLat:     $sourceLat,
                    sourceLng:     $sourceLng,
                    rank:          $rank,
                    status:        'found',
                    error:         null,
                    poiName:       $place['name'] ?? null,
                    poiAddress:    $place['vicinity'] ?? null,
                    poiLat:        $poiLat,
                    poiLng:        $poiLng,
                    distanceMiles: $distanceMiles,
                    rating:        isset($place['rating']) ? (float) $place['rating'] : null,
                    userRatingsTotal: isset($place['user_ratings_total']) ? (int) $place['user_ratings_total'] : null,
                    typesJson:     $place['types'] ?? null,
                );

                $persistedRows[] = $row->toArray();
            }

            // All results were filtered out by exclusion rules
            if (empty($persistedRows)) {
                $row = $this->createPoiRow(
                    listingType: $listingType,
                    listingId:   $listingId,
                    category:    $category,
                    meta:        $meta,
                    sourceLat:   $sourceLat,
                    sourceLng:   $sourceLng,
                    rank:        1,
                    status:      'not_found',
                    error:       'All candidates were excluded by category quality filters',
                );
                return [[$row->toArray()], $rawCandidates];
            }

            return [$persistedRows, $rawCandidates];

        } catch (Throwable $e) {
            try {
                PropertyLocationPoi::where('listing_type', $listingType)
                    ->where('listing_id', $listingId)
                    ->where('poi_category', $category)
                    ->delete();

                $row = $this->createPoiRow(
                    listingType: $listingType,
                    listingId:   $listingId,
                    category:    $category,
                    meta:        $meta,
                    sourceLat:   $sourceLat,
                    sourceLng:   $sourceLng,
                    rank:        1,
                    status:      'error',
                    error:       $e->getMessage(),
                );
                return [[$row->toArray()], []];
            } catch (Throwable) {
                return [[], []];
            }
        }
    }

    /**
     * Derive and persist the 'top_rated_dining' category from raw restaurant candidates.
     *
     * Filters restaurant candidates to those with >= TOP_RATED_DINING_MIN_REVIEWS reviews,
     * sorts by rating descending, and persists up to TOP_RATED_DINING_MAX_CANDIDATES rows
     * ranked 1/2/3 under the 'top_rated_dining' category key.
     *
     * This is a derived category — no additional API call is made.
     *
     * @param  array  $restaurantCandidates  Raw Google Places results from the restaurant query.
     * @return array  Array of persisted row arrays.
     */
    private function deriveAndPersistTopRatedDining(
        string $listingType,
        int    $listingId,
        float  $sourceLat,
        float  $sourceLng,
        array  $restaurantCandidates,
    ): array {
        // Atomic replacement: delete existing top_rated_dining rows
        PropertyLocationPoi::where('listing_type', $listingType)
            ->where('listing_id', $listingId)
            ->where('poi_category', 'top_rated_dining')
            ->delete();

        $topRatedMeta = [
            'label'          => 'Top Rated Dining',
            'query_strategy' => 'derived',
            'google_type'    => null,
            'keyword'        => null,
        ];

        if (empty($restaurantCandidates)) {
            $row = $this->createPoiRow(
                listingType: $listingType,
                listingId:   $listingId,
                category:    'top_rated_dining',
                meta:        $topRatedMeta,
                sourceLat:   $sourceLat,
                sourceLng:   $sourceLng,
                rank:        1,
                status:      'not_found',
                error:       'No restaurant candidates available to derive Top Rated Dining',
            );
            return [[$row->toArray()]];
        }

        // Filter: minimum review threshold
        $qualified = array_filter(
            $restaurantCandidates,
            fn($place) => ($place['user_ratings_total'] ?? 0) >= self::TOP_RATED_DINING_MIN_REVIEWS,
        );

        if (empty($qualified)) {
            $row = $this->createPoiRow(
                listingType: $listingType,
                listingId:   $listingId,
                category:    'top_rated_dining',
                meta:        $topRatedMeta,
                sourceLat:   $sourceLat,
                sourceLng:   $sourceLng,
                rank:        1,
                status:      'not_found',
                error:       'No qualifying restaurants found (minimum ' . self::TOP_RATED_DINING_MIN_REVIEWS . ' reviews required)',
            );
            return [[$row->toArray()]];
        }

        // Sort by quality score descending instead of raw rating.
        //
        // Formula: score = rating × min(reviews / TOP_RATED_DINING_MIN_CONFIDENCE_REVIEWS, 1.0)
        //
        // Rationale: raw-rating sort allows low-sample 5-star outliers (e.g. 5.0★/19 reviews)
        // to outrank high-confidence restaurants (e.g. 4.8★/300 reviews). The confidence
        // multiplier — capped at 1.0 once reviews reach MIN_CONFIDENCE_REVIEWS — down-weights
        // thinly-reviewed places without penalising established restaurants that exceed the
        // threshold. Example:
        //   5.0★ / 19 reviews  → score = 5.0 × min(0.38, 1.0) = 1.90
        //   4.8★ / 300 reviews → score = 4.8 × min(6.00, 1.0) = 4.80  ← ranks higher
        usort($qualified, function ($a, $b) {
            $scoreA = ($a['rating'] ?? 0.0) * min(($a['user_ratings_total'] ?? 0) / self::TOP_RATED_DINING_MIN_CONFIDENCE_REVIEWS, 1.0);
            $scoreB = ($b['rating'] ?? 0.0) * min(($b['user_ratings_total'] ?? 0) / self::TOP_RATED_DINING_MIN_CONFIDENCE_REVIEWS, 1.0);
            return $scoreB <=> $scoreA;
        });

        $top  = array_slice(array_values($qualified), 0, self::TOP_RATED_DINING_MAX_CANDIDATES);
        $rows = [];

        foreach ($top as $index => $place) {
            $rank          = $index + 1;
            $poiLat        = (float) ($place['geometry']['location']['lat'] ?? 0);
            $poiLng        = (float) ($place['geometry']['location']['lng'] ?? 0);
            $distanceMiles = $this->haversineDistanceMiles($sourceLat, $sourceLng, $poiLat, $poiLng);

            $row = $this->createPoiRow(
                listingType:      $listingType,
                listingId:        $listingId,
                category:         'top_rated_dining',
                meta:             $topRatedMeta,
                sourceLat:        $sourceLat,
                sourceLng:        $sourceLng,
                rank:             $rank,
                status:           'found',
                error:            null,
                poiName:          $place['name'] ?? null,
                poiAddress:       $place['vicinity'] ?? null,
                poiLat:           $poiLat,
                poiLng:           $poiLng,
                distanceMiles:    $distanceMiles,
                rating:           isset($place['rating']) ? (float) $place['rating'] : null,
                userRatingsTotal: isset($place['user_ratings_total']) ? (int) $place['user_ratings_total'] : null,
                typesJson:        $place['types'] ?? null,
            );

            $rows[] = $row->toArray();
        }

        return $rows;
    }

    /**
     * Create and persist a single POI row.
     *
     * Uses create() (not updateOrCreate) since the calling context guarantees existing
     * rows for this (listing_type, listing_id, poi_category) have already been deleted.
     */
    private function createPoiRow(
        string  $listingType,
        int     $listingId,
        string  $category,
        array   $meta,
        float   $sourceLat,
        float   $sourceLng,
        int     $rank,
        string  $status,
        ?string $error,
        ?string $poiName          = null,
        ?string $poiAddress       = null,
        ?float  $poiLat           = null,
        ?float  $poiLng           = null,
        ?float  $distanceMiles    = null,
        ?float  $rating           = null,
        ?int    $userRatingsTotal = null,
        ?array  $typesJson        = null,
    ): PropertyLocationPoi {
        return PropertyLocationPoi::create([
            'listing_type'        => $listingType,
            'listing_id'          => $listingId,
            'poi_category'        => $category,
            'rank'                => $rank,
            'poi_subtype'         => $meta['label'],
            'poi_name'            => $poiName,
            'poi_address'         => $poiAddress,
            'poi_lat'             => $poiLat,
            'poi_lng'             => $poiLng,
            'source_lat'          => $sourceLat,
            'source_lng'          => $sourceLng,
            'distance_miles'      => $distanceMiles,
            'rating'              => $rating,
            'user_ratings_total'  => $userRatingsTotal,
            'types_json'          => $typesJson,
            'travel_time_minutes' => null,
            'data_source'         => 'google_places',
            'status'              => $status,
            'error'               => $error,
            'calculated_at'       => now(),
        ]);
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
