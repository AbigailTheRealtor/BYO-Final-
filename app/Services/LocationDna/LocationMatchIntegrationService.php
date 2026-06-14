<?php

namespace App\Services\LocationDna;

/**
 * LocationMatchIntegrationService — Phase 6C Integration Layer
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * Pure orchestration service. Sequences LocationMatchEngine (6A) into
 * LocationMatchInsightService (6B) and returns the combined payload.
 *
 * Responsibilities:
 *   - Accept fully-resolved demand preferences and supply property data.
 *   - Delegate raw matching to LocationMatchEngine::match().
 *   - Pass engine output to LocationMatchInsightService::buildInsights().
 *   - Return both the raw match results and the insight strings.
 *
 * This service MUST NEVER:
 *   - Make any database reads or writes.
 *   - Use Eloquent models or DB facades.
 *   - Make any external API calls.
 *   - Compute weighted scores or recommendations.
 *   - Apply presentation fallbacks, user-facing strings, or null/empty guards.
 *     (Those concerns belong in the display layer — Phase 6D.)
 * ==================================================================================
 *
 * Input — $preferences (buyer/tenant location_dna_preferences array):
 *   cities          — string[]: named cities of interest
 *   zip_codes       — string[]: ZIP codes of interest
 *   neighborhoods   — string[]: neighborhood/subdivision names
 *   polygons        — array[]: drawn polygon entries
 *   radius_searches — array[]: radius entries
 *
 * Input — $propertyData (seller/landlord property location):
 *   city         — string
 *   zip          — string
 *   neighborhood — string
 *   lat          — float
 *   lng          — float
 *
 * Output:
 *   [
 *     'match_results' => array   // 10-key contract from LocationMatchEngine
 *     'insights'      => array   // string[] from LocationMatchInsightService
 *   ]
 */
class LocationMatchIntegrationService
{
    public function __construct(
        private readonly LocationMatchEngine         $engine,
        private readonly LocationMatchInsightService $insightService,
    ) {}

    /**
     * Orchestrate a location match payload from preferences and property data.
     *
     * @param  array $preferences  Decoded location_dna_preferences array (demand side).
     * @param  array $propertyData Property location data (supply side).
     * @return array               Shape: ['match_results' => array, 'insights' => array]
     */
    public function build(array $preferences, array $propertyData): array
    {
        $matchResults = $this->engine->match($preferences, $propertyData);
        $insightData  = $this->insightService->buildInsights($matchResults);

        return [
            'match_results' => $matchResults,
            'insights'      => $insightData['insights'] ?? [],
        ];
    }
}
