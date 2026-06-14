<?php

namespace App\Services\LocationDna;

/**
 * LocationMatchInsightService — Phase 6B Match Insight Layer
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is a pure, stateless interpretation layer for LocationMatchEngine
 * output. It converts raw boolean/array match signals into deterministic,
 * human-readable insight strings.
 *
 * This service MUST NEVER:
 *   - Make any database reads or writes.
 *   - Use Eloquent models or DB facades.
 *   - Make any external API calls of any kind (Google, OpenAI, Census, etc.).
 *   - Import or use OpenAI, scoring, or marketing report classes.
 *   - Introduce routes, controllers, Blade views, Livewire components, or JavaScript.
 *   - Compute weighted scores, numeric percentages, or recommendations.
 *   - Integrate with the pipeline runner or context builder.
 * ==================================================================================
 *
 * Input — $matchResults: the approved 10-key contract from LocationMatchEngine::match().
 *
 * Output:
 *   ['insights' => string[]]
 *
 * Emission order:
 *   1. Strong match header  (3+ distinct signal types in overlap_signals)
 *   2. City insight         (city_match === true)
 *   3. ZIP insight          (zip_match === true)
 *   4. Neighborhood insight (matched_neighborhoods non-empty)
 *   5. Polygon insight      (polygon_match === true)
 *   6. Radius insight       (radius_match === true)
 *   7. Multi-signal footer  (2+ distinct signal types in overlap_signals)
 *   8. No-signals fallback  (overlap_signals is empty)
 */
class LocationMatchInsightService
{
    /**
     * Convert a LocationMatchEngine result into human-readable insight strings.
     *
     * @param  array $matchResults Approved 10-key contract from LocationMatchEngine.
     * @return array               Shape: ['insights' => string[]]
     */
    public function buildInsights(array $matchResults): array
    {
        $signals  = $this->getSignals($matchResults);
        $count    = count($signals);
        $insights = [];

        // 1. Strong match header — emitted first when 3+ distinct signal types fired.
        if ($count >= 3) {
            $insights[] = 'Strong location match.';
        }

        // 2–6. Per-signal lines.
        if ($matchResults['city_match'] ?? false) {
            $insights[] = 'Property aligns with a preferred city.';
        }

        if ($matchResults['zip_match'] ?? false) {
            $insights[] = 'Property aligns with a preferred ZIP code.';
        }

        if (!empty($matchResults['matched_neighborhoods'])) {
            $insights[] = 'Property aligns with a preferred neighborhood.';
        }

        if ($matchResults['polygon_match'] ?? false) {
            $insights[] = 'Property falls within a preferred search area.';
        }

        if ($matchResults['radius_match'] ?? false) {
            $insights[] = 'Property falls within a preferred search radius.';
        }

        // 7. Multi-signal footer — emitted after per-signal lines when 2+ signals fired.
        if ($count >= 2) {
            $insights[] = 'Multiple location preference signals align.';
        }

        // 8. No-signals fallback.
        if ($count === 0) {
            $insights[] = 'No direct location preference overlap detected.';
        }

        return ['insights' => $insights];
    }

    /**
     * Extract the overlap_signals array from matchResults, returning [] on any issue.
     *
     * @param  array $matchResults
     * @return string[]
     */
    private function getSignals(array $matchResults): array
    {
        $signals = $matchResults['overlap_signals'] ?? [];

        return is_array($signals) ? array_values($signals) : [];
    }
}
