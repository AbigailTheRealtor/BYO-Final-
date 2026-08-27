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
     * Applies the canonical Location DNA precedence — Polygon > Radius > ZIP > City
     * > County:
     *   Tier 1: custom polygons  (skip — already drawn, no lookup needed)
     *   Tier 2: radius searches  (skip — no boundary lookup needed; a radius IS the
     *                            circle, there is no separate circle tier)
     *   Tier 3: zip codes
     *   Tier 4: cities
     *   Tier 5: counties
     *
     * A named tier wins only if it RESOLVES to at least one usable boundary.
     * Presence alone does not consume precedence — an unresolvable ZIP falls through
     * to a valid city rather than suppressing it.
     *
     * State is NOT a tier. It is narrowing context handed to the adapter to
     * disambiguate a name, and is never returned as a winning preference.
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

        // Infer state abbreviation from legacy location for narrowing Census queries.
        // State is narrowing context only — it never becomes a winning tier.
        $stateAbbrev = $this->resolveStateAbbrev($legacyLocation, $prefs);

        // Canonical named-boundary precedence: ZIP > City > County.
        //
        // ZIP is the most specific of the three, so it must be consulted first. This
        // used to test cities first, which meant a buyer who listed both a ZIP and a
        // city had the (broader) city boundary rendered and the ZIP silently ignored.
        //
        // A tier wins only if it RESOLVES. Presence alone does not consume precedence:
        // the previous code selected the first non-empty tier unconditionally, so a ZIP
        // the adapter could not resolve returned an empty fallback payload while a
        // perfectly good city boundary sat unused one branch below. Each tier is now
        // attempted in turn and the first one that yields at least one usable ring set
        // wins outright.
        $tiers = [
            ['zip',    $allZips],
            ['city',   $allCities],
            ['county', $allCounties],
        ];

        foreach ($tiers as [$type, $names]) {
            if (empty($names)) {
                continue;
            }

            // The whole tier is looked up in one call, preserving the service's existing
            // multi-value semantics: every member of the winning tier is offered to the
            // adapter, and every member that resolves is returned.
            $rawResults = $this->adapter->lookup($type, $names, $stateAbbrev);

            // Keep only non-empty coordinate-ring arrays (successful lookups).
            $resolved = array_values(array_filter($rawResults, fn($rings) => !empty($rings)));

            if (!empty($resolved)) {
                return [
                    'geojson_polygons' => $resolved,
                    'fallback'         => false,
                ];
            }
        }

        return $empty;
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
