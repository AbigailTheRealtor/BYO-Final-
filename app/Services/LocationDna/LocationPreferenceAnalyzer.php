<?php

namespace App\Services\LocationDna;

/**
 * LocationPreferenceAnalyzer — Phase 5A Preference Intelligence Layer
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is a pure, stateless text-formatting layer. It converts raw
 * location_dna_preferences arrays into human-readable summary lines.
 *
 * This service MUST NEVER:
 *   - Make any external API calls of any kind.
 *   - Make any database reads or writes.
 *   - Import or use OpenAI, scoring, or marketing report classes.
 *   - Introduce routes, controllers, Blade views, Livewire components, or JavaScript.
 *   - Compute scores, recommendations, or generate marketing copy.
 * ==================================================================================
 *
 * Input: raw location_dna_preferences array. Recognised keys:
 *   flexible_location  — bool: user is flexible about exact location
 *   cities             — string[]: named cities of interest
 *   zip_codes          — string[]: ZIP codes of interest
 *   neighborhoods      — string[]: neighborhood/subdivision names
 *   polygons           — array[]: drawn polygon entries (each has a 'path' key)
 *   radius_searches    — array[]: radius entries (each has 'center' + 'radius_miles')
 *
 * Output shape:
 *   ['summary_lines' => string[]]
 *
 * Lines are emitted in standardized order:
 *   1. Flexibility
 *   2. Search breadth (broad/narrow density signal)
 *   3. Geographic targeting (multi-city submarket framing)
 *   4. Polygon / radius spatial insight
 *   5. Preference specificity (targeted density signal + preference-type label)
 */
class LocationPreferenceAnalyzer
{
    private const BROAD_CITY_THRESHOLD    = 5;
    private const BROAD_ZIP_THRESHOLD     = 6;
    private const BROAD_POLYGON_THRESHOLD = 3;

    private const TARGETED_MAX_CITIES = 1;
    private const TARGETED_MAX_ZIPS   = 2;

    /**
     * Analyze raw location_dna_preferences and return human-readable summary lines.
     *
     * Each section is skipped gracefully when absent or malformed.
     * Returns empty summary_lines for empty preferences (no exception).
     *
     * @param  array $preferences  Decoded location_dna_preferences array.
     * @return array               ['summary_lines' => string[]]
     */
    public function analyze(array $preferences): array
    {
        if (empty($preferences)) {
            return ['summary_lines' => []];
        }

        $lines = [];

        $lines = array_merge($lines, $this->flexibilityLines($preferences));
        $lines = array_merge($lines, $this->breadthLines($preferences));
        $lines = array_merge($lines, $this->geographicTargetingLines($preferences));
        $lines = array_merge($lines, $this->polygonRadiusLines($preferences));
        $lines = array_merge($lines, $this->specificityLines($preferences));

        return ['summary_lines' => $lines];
    }

    // =========================================================================
    // Private section generators — each returns string[]
    // =========================================================================

    private function flexibilityLines(array $preferences): array
    {
        if (!empty($preferences['flexible_location'])) {
            return ['Open to multiple areas and willing to prioritize overall fit over a specific neighborhood.'];
        }

        return [];
    }

    private function breadthLines(array $preferences): array
    {
        if ($this->isBroad($preferences)) {
            return ['Broad geographic search area.'];
        }

        return [];
    }

    private function geographicTargetingLines(array $preferences): array
    {
        $cities = $this->getArray($preferences, 'cities');

        if (count($cities) >= 2) {
            $formatted = $this->formatList($cities);
            return ["Seeking opportunities across multiple submarkets including {$formatted}."];
        }

        return [];
    }

    private function polygonRadiusLines(array $preferences): array
    {
        $polygons = $this->getArray($preferences, 'polygons');
        $radii    = $this->getArray($preferences, 'radius_searches');

        $lines = [];

        $polyCount = count($polygons);
        if ($polyCount === 1) {
            $lines[] = 'Focused on a specifically defined target area.';
        } elseif ($polyCount > 1) {
            $lines[] = 'Searching across several custom-defined target areas.';
        }

        if (!empty($radii)) {
            $lines[] = 'Searching within a defined radius from a preferred location.';
        }

        return $lines;
    }

    private function specificityLines(array $preferences): array
    {
        $cities   = $this->getArray($preferences, 'cities');
        $zips     = $this->getArray($preferences, 'zip_codes');
        $hoods    = $this->getArray($preferences, 'neighborhoods');
        $polygons = $this->getArray($preferences, 'polygons');
        $radii    = $this->getArray($preferences, 'radius_searches');

        $hasPolygonsOrRadii = !empty($polygons) || !empty($radii);

        if ($hasPolygonsOrRadii) {
            return [];
        }

        $lines = [];

        // Density signal — "highly targeted" coexists with the preference-type label below.
        // Not emitted when broad (broad already appeared in breadth step) or when
        // neighborhoods are present (neighborhoods are the more specific signal).
        if (!$this->isBroad($preferences) && empty($hoods) && $this->isTargeted($preferences)) {
            $lines[] = 'Highly targeted location preferences.';
        }

        // Preference-type label — always emitted when applicable, independent of
        // broad/targeted density signal so both dimensions appear together.
        $lines = array_merge($lines, $this->preferenceTypeLines($cities, $zips, $hoods));

        return $lines;
    }

    // =========================================================================
    // Density helpers
    // =========================================================================

    private function isBroad(array $preferences): bool
    {
        return count($this->getArray($preferences, 'cities'))    >= self::BROAD_CITY_THRESHOLD
            || count($this->getArray($preferences, 'zip_codes')) >= self::BROAD_ZIP_THRESHOLD
            || count($this->getArray($preferences, 'polygons'))  >= self::BROAD_POLYGON_THRESHOLD;
    }

    private function isTargeted(array $preferences): bool
    {
        $cities = $this->getArray($preferences, 'cities');
        $zips   = $this->getArray($preferences, 'zip_codes');

        $narrowCities = count($cities) > 0 && count($cities) <= self::TARGETED_MAX_CITIES && empty($zips);
        $narrowZips   = count($zips) > 0 && count($zips) <= self::TARGETED_MAX_ZIPS && empty($cities);

        return $narrowCities || $narrowZips;
    }

    // =========================================================================
    // Preference-type label generator
    // =========================================================================

    private function preferenceTypeLines(array $cities, array $zips, array $hoods): array
    {
        $hasCities = !empty($cities);
        $hasZips   = !empty($zips);
        $hasHoods  = !empty($hoods);

        if ($hasHoods) {
            return ['Preferences include specific neighborhoods or subdivisions.'];
        }

        if ($hasCities && $hasZips) {
            return ['Mixed location preferences combining cities and ZIP codes.'];
        }

        if ($hasCities) {
            return ['Preferences defined by city or municipality.'];
        }

        if ($hasZips) {
            return ['Preferences defined by ZIP code.'];
        }

        return [];
    }

    // =========================================================================
    // Utility helpers
    // =========================================================================

    private function getArray(array $preferences, string $key): array
    {
        $val = $preferences[$key] ?? null;

        return is_array($val) ? array_values(array_filter($val, fn ($v) => $v !== null && $v !== '')) : [];
    }

    /**
     * Format a list of strings as "A, B and C" (no Oxford comma before "and").
     */
    private function formatList(array $items): string
    {
        $items = array_values(array_filter($items, fn ($v) => is_string($v) && $v !== ''));
        $count = count($items);

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $items[0];
        }

        if ($count === 2) {
            return $items[0] . ' and ' . $items[1];
        }

        $last = array_pop($items);

        return implode(', ', $items) . ' and ' . $last;
    }
}
