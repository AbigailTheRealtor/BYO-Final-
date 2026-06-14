<?php

namespace App\Services\LocationDna;

/**
 * LocationDnaChipPresenter — Phase 7A Search Results Integration
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is a pure, stateless chip-presentation layer. It converts raw
 * location_dna_preferences arrays into compact chip labels for browse cards.
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
 *   polygons           — array[]: drawn polygon entries (each has a 'path' key)
 *   radius_searches    — array[]: radius entries (each has 'center' + 'radius_miles')
 *
 * Output shape:
 *   ['chips' => string[], 'overflow' => int]
 *
 * Chips are derived in this order:
 *   1. Flexible Location   (flexible_location = true)
 *   2. City Submarkets     (2+ cities)
 *   3. Custom Search Area  (polygons present)
 *   4. Radius Search       (radius_searches present)
 *
 * Only the first 3 chips are returned; overflow = max(0, total - 3).
 * Empty preferences return ['chips' => [], 'overflow' => 0].
 */
class LocationDnaChipPresenter
{
    private const MAX_CHIPS = 3;

    /**
     * Build chip list from raw location_dna_preferences.
     *
     * @param  array $preferences  Decoded location_dna_preferences array.
     * @return array               ['chips' => string[], 'overflow' => int]
     */
    public function present(array $preferences): array
    {
        if (empty($preferences)) {
            return ['chips' => [], 'overflow' => 0];
        }

        $allChips = [];

        if (!empty($preferences['flexible_location'])) {
            $allChips[] = 'Flexible Location';
        }

        $cities = $this->getStringArray($preferences, 'cities');
        if (count($cities) >= 2) {
            $allChips[] = $this->cityChipLabel($cities);
        }

        if (!empty($this->getArray($preferences, 'polygons'))) {
            $allChips[] = 'Custom Search Area';
        }

        if (!empty($this->getArray($preferences, 'radius_searches'))) {
            $allChips[] = 'Radius Search';
        }

        $total    = count($allChips);
        $overflow = max(0, $total - self::MAX_CHIPS);

        return [
            'chips'    => array_slice($allChips, 0, self::MAX_CHIPS),
            'overflow' => $overflow,
        ];
    }

    private function cityChipLabel(array $cities): string
    {
        if (count($cities) === 2) {
            return $cities[0] . ' / ' . $cities[1] . ' Submarkets';
        }

        return 'Multiple Submarkets';
    }

    private function getArray(array $preferences, string $key): array
    {
        $val = $preferences[$key] ?? null;
        return is_array($val) ? array_values(array_filter($val, fn($v) => $v !== null && $v !== '')) : [];
    }

    private function getStringArray(array $preferences, string $key): array
    {
        $val = $preferences[$key] ?? null;
        if (!is_array($val)) {
            return [];
        }
        return array_values(array_filter($val, fn($v) => is_string($v) && $v !== ''));
    }
}
