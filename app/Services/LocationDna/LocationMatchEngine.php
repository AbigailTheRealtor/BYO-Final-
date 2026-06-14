<?php

namespace App\Services\LocationDna;

/**
 * LocationMatchEngine — Phase 6A Matching Engine Foundation
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is a pure, stateless geographic overlap detector. It measures
 * how well a buyer/tenant's location preferences match a seller/landlord's
 * property location data.
 *
 * This service MUST NEVER:
 *   - Make any database reads or writes.
 *   - Use Eloquent models or DB facades.
 *   - Make any external API calls of any kind (Google, OpenAI, Census, etc.).
 *   - Import or use OpenAI, scoring, or marketing report classes.
 *   - Introduce routes, controllers, Blade views, Livewire components, or JavaScript.
 *   - Compute weighted scores or recommendations — raw signal detection only.
 *   - Integrate with the pipeline runner or context builder.
 * ==================================================================================
 *
 * Input — $preferences (buyer/tenant location preferences):
 *   cities          — string[]: named cities of interest
 *   zip_codes       — string[]: ZIP codes of interest
 *   neighborhoods   — string[]: neighborhood/subdivision names
 *   polygons        — array[]: drawn polygon entries (each has a 'path' key —
 *                     array of {lat, lng} associative arrays)
 *   radius_searches — array[]: radius entries (each has 'center' {lat, lng}
 *                     and 'radius_miles' float)
 *
 * Input — $propertyData (seller/landlord property location):
 *   city         — string
 *   zip          — string
 *   neighborhood — string
 *   lat          — float
 *   lng          — float
 *
 * Output — approved 10-key contract:
 *   matched_cities        => string[]
 *   city_match            => bool
 *   matched_zips          => string[]
 *   zip_match             => bool
 *   matched_neighborhoods => string[]
 *   polygon_match         => bool
 *   matched_polygon_count => int
 *   radius_match          => bool
 *   matched_radius_count  => int
 *   overlap_signals       => string[]
 */
class LocationMatchEngine
{
    private const EARTH_RADIUS_MILES = 3958.8;

    /**
     * Measure location overlap between buyer/tenant preferences and a property.
     *
     * Returns all 10 contract keys with zero/false/empty defaults for any empty
     * or missing inputs. Never throws.
     *
     * @param  array $preferences  Buyer/tenant location_dna_preferences array.
     * @param  array $propertyData Seller/landlord property location data.
     * @return array               Approved 10-key result contract.
     */
    public function match(array $preferences, array $propertyData): array
    {
        $result = $this->emptyResult();

        // City matching
        [$matchedCities, $cityMatch] = $this->matchCities($preferences, $propertyData);
        $result['matched_cities'] = $matchedCities;
        $result['city_match']     = $cityMatch;

        // ZIP matching
        [$matchedZips, $zipMatch] = $this->matchZips($preferences, $propertyData);
        $result['matched_zips'] = $matchedZips;
        $result['zip_match']    = $zipMatch;

        // Neighborhood matching
        $result['matched_neighborhoods'] = $this->matchNeighborhoods($preferences, $propertyData);

        // Polygon matching
        [$polygonMatch, $polygonCount] = $this->matchPolygons($preferences, $propertyData);
        $result['polygon_match']         = $polygonMatch;
        $result['matched_polygon_count'] = $polygonCount;

        // Radius matching
        [$radiusMatch, $radiusCount] = $this->matchRadius($preferences, $propertyData);
        $result['radius_match']         = $radiusMatch;
        $result['matched_radius_count'] = $radiusCount;

        // Overlap signals
        $result['overlap_signals'] = $this->buildOverlapSignals($result);

        return $result;
    }

    // =========================================================================
    // Signal detectors
    // =========================================================================

    /**
     * @return array{string[], bool}
     */
    private function matchCities(array $preferences, array $propertyData): array
    {
        $preferredCities = $this->getStringArray($preferences, 'cities');
        $propertyCity    = isset($propertyData['city']) ? trim($propertyData['city']) : '';

        if (empty($preferredCities) || $propertyCity === '') {
            return [[], false];
        }

        $normalizedProperty = strtolower($propertyCity);
        $matched = [];

        foreach ($preferredCities as $city) {
            if (strtolower(trim($city)) === $normalizedProperty) {
                $matched[] = $city;
            }
        }

        return [$matched, !empty($matched)];
    }

    /**
     * @return array{string[], bool}
     */
    private function matchZips(array $preferences, array $propertyData): array
    {
        $preferredZips = $this->getStringArray($preferences, 'zip_codes');
        $propertyZip   = isset($propertyData['zip']) ? trim($propertyData['zip']) : '';

        if (empty($preferredZips) || $propertyZip === '') {
            return [[], false];
        }

        $matched = [];

        foreach ($preferredZips as $zip) {
            if (trim($zip) === $propertyZip) {
                $matched[] = $zip;
            }
        }

        return [$matched, !empty($matched)];
    }

    /**
     * @return string[]
     */
    private function matchNeighborhoods(array $preferences, array $propertyData): array
    {
        $preferredHoods    = $this->getStringArray($preferences, 'neighborhoods');
        $propertyHood      = isset($propertyData['neighborhood']) ? trim($propertyData['neighborhood']) : '';

        if (empty($preferredHoods) || $propertyHood === '') {
            return [];
        }

        $normalizedProperty = strtolower($propertyHood);
        $matched = [];

        foreach ($preferredHoods as $hood) {
            if (strtolower(trim($hood)) === $normalizedProperty) {
                $matched[] = $hood;
            }
        }

        return $matched;
    }

    /**
     * @return array{bool, int}
     */
    private function matchPolygons(array $preferences, array $propertyData): array
    {
        $polygons = $this->getArray($preferences, 'polygons');

        if (empty($polygons)) {
            return [false, 0];
        }

        $lat = isset($propertyData['lat']) ? (float) $propertyData['lat'] : null;
        $lng = isset($propertyData['lng']) ? (float) $propertyData['lng'] : null;

        if ($lat === null || $lng === null) {
            return [false, 0];
        }

        $count = 0;

        foreach ($polygons as $polygon) {
            if (!is_array($polygon) || !isset($polygon['path']) || !is_array($polygon['path'])) {
                continue;
            }

            $path = $polygon['path'];

            if (count($path) < 3) {
                continue;
            }

            if ($this->pointInPolygon($lat, $lng, $path)) {
                $count++;
            }
        }

        return [$count > 0, $count];
    }

    /**
     * @return array{bool, int}
     */
    private function matchRadius(array $preferences, array $propertyData): array
    {
        $radiusSearches = $this->getArray($preferences, 'radius_searches');

        if (empty($radiusSearches)) {
            return [false, 0];
        }

        $propLat = isset($propertyData['lat']) ? (float) $propertyData['lat'] : null;
        $propLng = isset($propertyData['lng']) ? (float) $propertyData['lng'] : null;

        if ($propLat === null || $propLng === null) {
            return [false, 0];
        }

        $count = 0;

        foreach ($radiusSearches as $search) {
            if (!is_array($search)) {
                continue;
            }

            $center      = $search['center'] ?? null;
            $radiusMiles = $search['radius_miles'] ?? null;

            if (!is_array($center) || !isset($center['lat'], $center['lng'])) {
                continue;
            }

            if (!is_numeric($radiusMiles) || (float) $radiusMiles <= 0) {
                continue;
            }

            $centerLat = (float) $center['lat'];
            $centerLng = (float) $center['lng'];

            $distance = $this->haversineDistance($propLat, $propLng, $centerLat, $centerLng);

            if ($distance <= (float) $radiusMiles) {
                $count++;
            }
        }

        return [$count > 0, $count];
    }

    /**
     * Assemble compact overlap_signals list from fired signals.
     *
     * @param  array $result Partially assembled result (all keys except overlap_signals).
     * @return string[]
     */
    private function buildOverlapSignals(array $result): array
    {
        $signals = [];

        if ($result['city_match']) {
            $signals[] = 'city';
        }

        if ($result['zip_match']) {
            $signals[] = 'zip';
        }

        if (!empty($result['matched_neighborhoods'])) {
            $signals[] = 'neighborhood';
        }

        if ($result['polygon_match']) {
            $signals[] = 'polygon';
        }

        if ($result['radius_match']) {
            $signals[] = 'radius';
        }

        return $signals;
    }

    // =========================================================================
    // Geometric algorithms
    // =========================================================================

    /**
     * Ray-casting point-in-polygon test.
     *
     * Counts eastward horizontal ray crossings from the test point against each
     * polygon edge. An odd crossing count means the point is inside.
     *
     * @param  float   $lat  Test point latitude.
     * @param  float   $lng  Test point longitude.
     * @param  array   $path Array of {lat, lng} associative arrays (≥3 points).
     * @return bool
     */
    private function pointInPolygon(float $lat, float $lng, array $path): bool
    {
        $n       = count($path);
        $inside  = false;
        $j       = $n - 1;

        for ($i = 0; $i < $n; $i++) {
            $xi = (float) ($path[$i]['lng'] ?? 0);
            $yi = (float) ($path[$i]['lat'] ?? 0);
            $xj = (float) ($path[$j]['lng'] ?? 0);
            $yj = (float) ($path[$j]['lat'] ?? 0);

            // Check if the horizontal ray from ($lng, $lat) eastward crosses this edge.
            $intersects = (($yi > $lat) !== ($yj > $lat))
                && ($lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi);

            if ($intersects) {
                $inside = !$inside;
            }

            $j = $i;
        }

        return $inside;
    }

    /**
     * Haversine great-circle distance formula.
     *
     * @param  float $lat1 Point A latitude (degrees).
     * @param  float $lng1 Point A longitude (degrees).
     * @param  float $lat2 Point B latitude (degrees).
     * @param  float $lng2 Point B longitude (degrees).
     * @return float Distance in miles.
     */
    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        $c = 2 * asin(sqrt($a));

        return self::EARTH_RADIUS_MILES * $c;
    }

    // =========================================================================
    // Utility helpers
    // =========================================================================

    /**
     * Extract a scalar-string array from a preferences key, filtering nulls and empty strings.
     *
     * @return string[]
     */
    private function getStringArray(array $preferences, string $key): array
    {
        $val = $preferences[$key] ?? null;

        if (!is_array($val)) {
            return [];
        }

        return array_values(
            array_filter($val, fn ($v) => is_string($v) && $v !== '')
        );
    }

    /**
     * Extract an array from a preferences key.
     *
     * @return array
     */
    private function getArray(array $preferences, string $key): array
    {
        $val = $preferences[$key] ?? null;

        return is_array($val) ? array_values($val) : [];
    }

    /**
     * Return an empty result array with all 10 contract keys at safe defaults.
     *
     * @return array
     */
    private function emptyResult(): array
    {
        return [
            'matched_cities'        => [],
            'city_match'            => false,
            'matched_zips'          => [],
            'zip_match'             => false,
            'matched_neighborhoods' => [],
            'polygon_match'         => false,
            'matched_polygon_count' => 0,
            'radius_match'          => false,
            'matched_radius_count'  => 0,
            'overlap_signals'       => [],
        ];
    }
}
