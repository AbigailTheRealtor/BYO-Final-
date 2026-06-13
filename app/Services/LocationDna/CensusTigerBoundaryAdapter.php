<?php

namespace App\Services\LocationDna;

use App\Contracts\BoundaryAdapterInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CensusTigerBoundaryAdapter implements BoundaryAdapterInterface
{
    private const CACHE_TTL   = 86400; // 24 hours in seconds
    private const API_TIMEOUT = 10;    // seconds per request

    private const BASE = 'https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb';

    /*
     * Layer IDs verified against Census TIGERweb MapServer metadata (June 2026).
     *
     * ZIP:    PUMA_TAD_TAZ_UGA_ZCTA/MapServer/11 — "ZIP Code Tabulation Areas" (Feature Layer)
     *         Field: ZCTA5 (5-digit ZIP string)
     *
     * City:   Places_CouSub_ConCity_SubMCD/MapServer/4  — "Incorporated Places" (Feature Layer)
     *         Places_CouSub_ConCity_SubMCD/MapServer/5  — "Census Designated Places" (Feature Layer)
     *         Field: BASENAME (plain name without " city" / " CDP" suffix)
     *         State filter field: STATE (FIPS code string, e.g. '12' for FL)
     *
     * County: State_County/MapServer/1 — "Counties" (Feature Layer)
     *         Field: NAME (includes "County" suffix, e.g. "Hillsborough County")
     *         State filter field: STATE (FIPS code string)
     */
    private const ENDPOINT_ZIP            = self::BASE . '/PUMA_TAD_TAZ_UGA_ZCTA/MapServer/11/query';
    private const ENDPOINT_CITY_INCORP    = self::BASE . '/Places_CouSub_ConCity_SubMCD/MapServer/4/query';
    private const ENDPOINT_CITY_CDP       = self::BASE . '/Places_CouSub_ConCity_SubMCD/MapServer/5/query';
    private const ENDPOINT_COUNTY         = self::BASE . '/State_County/MapServer/1/query';

    /**
     * @inheritDoc
     */
    public function lookup(string $type, array $names, ?string $stateAbbrev): array
    {
        if (!in_array($type, ['city', 'zip', 'county'], true) || empty($names)) {
            return array_fill(0, count($names), []);
        }

        $results = [];
        foreach ($names as $name) {
            $results[] = $this->lookupOne($type, $name, $stateAbbrev);
        }

        return $results;
    }

    /**
     * Look up a single boundary name, with caching.
     *
     * Caching strategy:
     *   - Successful API response with geometry found  → cached 24 h
     *   - Successful API response, no feature match    → cached 24 h (valid negative)
     *   - Transport failure / non-2xx / timeout        → NOT cached; returns [] immediately
     *
     * This ensures transient network errors never poison the cache and force prolonged
     * chip fallback. The page load degrades gracefully to chips for that request only,
     * and the next request retries the live API.
     *
     * @return array  Coordinate rings array (empty = not found or transient failure)
     */
    private function lookupOne(string $type, string $name, ?string $stateAbbrev): array
    {
        $cacheKey = 'boundary_' . $type . '_' . md5(strtolower(trim($name)) . '_' . ($stateAbbrev ?? ''));

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $result = $this->fetchFromCensus($type, $name, $stateAbbrev);
            // Only cache when fetchFromCensus returns cleanly (valid API response,
            // whether a feature was found or not). Exceptions (transport/HTTP failures)
            // skip Cache::put so the next request retries the live API.
            Cache::put($cacheKey, $result, self::CACHE_TTL);
            return $result;
        } catch (\Throwable $e) {
            // Transient failure (network error, timeout, non-2xx) — do not cache.
            Log::warning('CensusTigerBoundaryAdapter: lookup failed', [
                'type'  => $type,
                'name'  => $name,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Perform the actual HTTP request(s) to the Census TIGER/Line REST API.
     *
     * For cities, queries Incorporated Places first; falls back to Census Designated Places
     * when no match is found (handles unincorporated communities, CDPs, etc.).
     *
     * Throws a \RuntimeException on non-2xx responses so the caller can distinguish
     * transport/API failures (not cached) from valid "no feature found" empty
     * responses (cached as [] so we don't hammer the API on every page view).
     *
     * @return array  Coordinate rings (may be empty when no feature matched)
     * @throws \RuntimeException  On non-2xx HTTP status
     */
    private function fetchFromCensus(string $type, string $name, ?string $stateAbbrev): array
    {
        switch ($type) {
            case 'zip':
                return $this->fetchZip($name);

            case 'city':
                $rings = $this->fetchCity(self::ENDPOINT_CITY_INCORP, $name, $stateAbbrev);
                if (empty($rings)) {
                    $rings = $this->fetchCity(self::ENDPOINT_CITY_CDP, $name, $stateAbbrev);
                }
                return $rings;

            case 'county':
                return $this->fetchCounty($name, $stateAbbrev);

            default:
                return [];
        }
    }

    /**
     * Fetch ZCTA polygon for a 5-digit ZIP code.
     *
     * @throws \RuntimeException  On non-2xx HTTP status
     */
    private function fetchZip(string $zip): array
    {
        $safe = str_replace("'", "''", trim($zip));
        $response = Http::timeout(self::API_TIMEOUT)->get(self::ENDPOINT_ZIP, [
            'where'          => "ZCTA5='" . $safe . "'",
            'outFields'      => 'ZCTA5',
            'returnGeometry' => 'true',
            'outSR'          => '4326',
            'f'              => 'geojson',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Census TIGER ZIP API returned HTTP ' . $response->status());
        }

        $body = $response->json();
        if (empty($body['features'])) {
            return [];
        }

        return $this->extractCoordinates($body['features'][0]);
    }

    /**
     * Fetch polygon for a city name from a given Places endpoint (Incorporated Places or CDP).
     * Uses BASENAME for matching (plain city name without " city" / " CDP" suffixes).
     *
     * @throws \RuntimeException  On non-2xx HTTP status
     */
    private function fetchCity(string $endpoint, string $cityName, ?string $stateAbbrev): array
    {
        $safe  = str_replace("'", "''", trim($cityName));
        $where = "BASENAME='" . $safe . "'";

        if ($stateAbbrev) {
            $fips = $this->stateAbbrevToFips($stateAbbrev);
            if ($fips) {
                $where .= " AND STATE='" . $fips . "'";
            }
        }

        $response = Http::timeout(self::API_TIMEOUT)->get($endpoint, [
            'where'          => $where,
            'outFields'      => 'NAME,BASENAME',
            'returnGeometry' => 'true',
            'outSR'          => '4326',
            'f'              => 'geojson',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Census TIGER City API returned HTTP ' . $response->status());
        }

        $body = $response->json();
        if (empty($body['features'])) {
            return [];
        }

        return $this->extractCoordinates($body['features'][0]);
    }

    /**
     * Fetch polygon for a county name.
     * Uses NAME LIKE 'X%' so both "Hillsborough" and "Hillsborough County" inputs work.
     *
     * @throws \RuntimeException  On non-2xx HTTP status
     */
    private function fetchCounty(string $countyName, ?string $stateAbbrev): array
    {
        // Strip trailing " County" / " county" from user-supplied input before matching
        // against NAME (which includes the suffix), using LIKE 'X%' to cover both forms.
        $base  = preg_replace('/\s+county\s*$/i', '', trim($countyName));
        $safe  = str_replace("'", "''", $base);
        $where = "NAME LIKE '" . $safe . "%'";

        if ($stateAbbrev) {
            $fips = $this->stateAbbrevToFips($stateAbbrev);
            if ($fips) {
                $where .= " AND STATE='" . $fips . "'";
            }
        }

        $response = Http::timeout(self::API_TIMEOUT)->get(self::ENDPOINT_COUNTY, [
            'where'          => $where,
            'outFields'      => 'NAME,STATE',
            'returnGeometry' => 'true',
            'outSR'          => '4326',
            'f'              => 'geojson',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Census TIGER County API returned HTTP ' . $response->status());
        }

        $body = $response->json();
        if (empty($body['features'])) {
            return [];
        }

        return $this->extractCoordinates($body['features'][0]);
    }

    /**
     * Extract polygon ring data from a GeoJSON feature.
     *
     * Returns an array of "polygons", where each polygon is its own array of rings:
     *
     *   Polygon      → [ [exterior_ring, hole_ring?, ...] ]           (one polygon)
     *   MultiPolygon → [ [exterior1, hole1?], [exterior2, ...], ... ] (N polygons)
     *
     * This structure is critical for correct rendering: each returned polygon entry
     * becomes a separate google.maps.Polygon on the map. Flattening all rings from a
     * MultiPolygon into a single entry would cause disconnected land masses (islands,
     * exclaves) to render as one polygon where subsequent rings are treated as holes.
     *
     * An empty array [] signals "no usable geometry" to the caller.
     *
     * @return array  array<array<ring>>  — array of polygons, each polygon = array of rings
     */
    private function extractCoordinates(array $feature): array
    {
        $geometry = $feature['geometry'] ?? null;
        if (!$geometry || !isset($geometry['coordinates'])) {
            return [];
        }

        $type   = $geometry['type'] ?? '';
        $coords = $geometry['coordinates'];

        if ($type === 'Polygon') {
            // Wrap in an outer array so the caller always iterates over "polygons"
            return [$coords];
        }

        if ($type === 'MultiPolygon') {
            // $coords is already [ polygon1, polygon2, ... ] where each polygon = [exterior, hole?, ...]
            // Return as-is — each element is one disconnected polygon with its own rings.
            return $coords;
        }

        return [];
    }

    /**
     * Convert a 2-letter state abbreviation to its Census FIPS code string.
     * Census TIGER uses plain numeric strings (e.g. '12' for Florida, not '012').
     * Unlisted states return null — queries run without a STATE filter rather than failing.
     */
    private function stateAbbrevToFips(string $abbrev): ?string
    {
        $map = [
            'AL' => '1',  'AK' => '2',  'AZ' => '4',  'AR' => '5',  'CA' => '6',
            'CO' => '8',  'CT' => '9',  'DE' => '10', 'FL' => '12', 'GA' => '13',
            'HI' => '15', 'ID' => '16', 'IL' => '17', 'IN' => '18', 'IA' => '19',
            'KS' => '20', 'KY' => '21', 'LA' => '22', 'ME' => '23', 'MD' => '24',
            'MA' => '25', 'MI' => '26', 'MN' => '27', 'MS' => '28', 'MO' => '29',
            'MT' => '30', 'NE' => '31', 'NV' => '32', 'NH' => '33', 'NJ' => '34',
            'NM' => '35', 'NY' => '36', 'NC' => '37', 'ND' => '38', 'OH' => '39',
            'OK' => '40', 'OR' => '41', 'PA' => '42', 'RI' => '44', 'SC' => '45',
            'SD' => '46', 'TN' => '47', 'TX' => '48', 'UT' => '49', 'VT' => '50',
            'VA' => '51', 'WA' => '53', 'WV' => '54', 'WI' => '55', 'WY' => '56',
            'DC' => '11',
        ];

        return $map[strtoupper($abbrev)] ?? null;
    }
}
