<?php

namespace App\Services\LocationDna;

use App\Contracts\BoundaryAdapterInterface;

class BoundaryLookupService
{
    public function __construct(private BoundaryAdapterInterface $adapter)
    {
    }

    /**
     * Resolve GeoJSON polygon coordinate rings for the active location tier.
     *
     * Applies the same priority chain used by the Blade component:
     *   Tier 1: custom polygons  (skip — already drawn, no lookup needed)
     *   Tier 2: radius circles   (skip — no boundary lookup needed)
     *   Tier 3: cities
     *   Tier 4: zip codes
     *   Tier 5: counties
     *
     * Returns a payload:
     *   [
     *     'geojson_polygons' => [
     *       // Each entry is one boundary name's coordinate-ring array.
     *       // An entry may be [] if the Census API returned no match.
     *     ],
     *     'fallback' => bool,   // true when no polygons could be resolved
     *   ]
     *
     * When Tiers 1 or 2 are active, or when no tier has data, returns fallback=true
     * with an empty polygon list so the Blade component falls back to chip display.
     *
     * @param  array|null  $preferences  Decoded location_dna_preferences array
     * @param  array       $legacyLocation  Keys: cities[], counties[], states[], zip_codes[]
     * @return array
     */
    public function resolve(?array $preferences, array $legacyLocation): array
    {
        $empty = ['geojson_polygons' => [], 'fallback' => true];

        $prefs = is_array($preferences) ? $preferences : [];

        $polygons = $prefs['polygons']        ?? [];
        $radii    = $prefs['radius_searches'] ?? [];
        $dnaCities = $prefs['cities']         ?? [];
        $dnaZips  = $prefs['zip_codes']       ?? [];

        $legCities   = array_values(array_filter((array)($legacyLocation['cities']   ?? [])));
        $legCounties = array_values(array_filter((array)($legacyLocation['counties'] ?? [])));
        $legZips     = array_values(array_filter((array)($legacyLocation['zip_codes'] ?? [])));

        $allCities   = array_values(array_unique(array_merge($dnaCities, $legCities)));
        $allZips     = array_values(array_unique(array_merge($dnaZips, $legZips)));
        $allCounties = array_values(array_filter(array_unique($legCounties)));

        // Tiers 1 & 2 are handled entirely on the front end — skip lookup.
        if (!empty($polygons) || !empty($radii)) {
            return $empty;
        }

        // Determine the active named-boundary tier.
        if (!empty($allCities)) {
            $type  = 'city';
            $names = $allCities;
        } elseif (!empty($allZips)) {
            $type  = 'zip';
            $names = $allZips;
        } elseif (!empty($allCounties)) {
            $type  = 'county';
            $names = $allCounties;
        } else {
            return $empty;
        }

        // Infer state abbreviation from legacy location for narrowing Census queries.
        $stateAbbrev = $this->resolveStateAbbrev($legacyLocation, $prefs);

        $rawResults = $this->adapter->lookup($type, $names, $stateAbbrev);

        // Flatten: keep only non-empty coordinate-ring arrays (successful lookups).
        $resolved = array_values(array_filter($rawResults, fn($rings) => !empty($rings)));

        return [
            'geojson_polygons' => $resolved,
            'fallback'         => empty($resolved),
        ];
    }

    /**
     * Attempt to derive a 2-letter state abbreviation from the available location data.
     * Returns null when it cannot be determined (queries run without state filter).
     */
    private function resolveStateAbbrev(array $legacyLocation, array $prefs): ?string
    {
        $states = array_values(array_filter((array)($legacyLocation['states'] ?? [])));
        if (!empty($states)) {
            $candidate = trim((string)$states[0]);
            // Accept both "FL" (abbrev) and longer state names (skip — too risky to map inline)
            if (strlen($candidate) === 2) {
                return strtoupper($candidate);
            }
        }

        return null;
    }
}
