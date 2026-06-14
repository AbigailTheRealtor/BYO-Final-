<?php

namespace App\Services\LocationDna;

/**
 * LocationMatchIntegrationService — Phase 6C Integration Layer
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is a pure coordination layer. It sequences LocationMatchEngine
 * and LocationMatchInsightService and returns a display-ready payload.
 *
 * This service MUST NEVER:
 *   - Make any database reads or writes.
 *   - Use Eloquent models or DB facades.
 *   - Make any external API calls of any kind (Google, OpenAI, Census, etc.).
 *   - Import or use OpenAI, scoring, or marketing report classes.
 *   - Introduce routes, controllers, Blade views, Livewire components, or JavaScript.
 *   - Compute weighted scores, numeric percentages, or recommendations.
 *   - Contain any matching or insight logic — delegate only to the two collaborators.
 * ==================================================================================
 *
 * Output shape:
 *   [
 *     'match_results' => array   // 10-key contract from LocationMatchEngine, or []
 *     'insights'      => array   // string[] from LocationMatchInsightService, or []
 *   ]
 *
 * Empty-state rule:
 *   When either $preferences or $propertyData is an empty array, both output keys
 *   return as empty arrays and neither collaborator is called.
 */
class LocationMatchIntegrationService
{
    public function __construct(
        private readonly LocationMatchEngine        $engine,
        private readonly LocationMatchInsightService $insightService,
    ) {}

    /**
     * Build a display-ready match payload from preferences and property data.
     *
     * @param  array $preferences  Buyer/tenant location_dna_preferences array.
     * @param  array $propertyData Seller/landlord property location data.
     * @return array               Shape: ['match_results' => array, 'insights' => array]
     */
    public function build(array $preferences, array $propertyData): array
    {
        if (empty($preferences) || empty($propertyData)) {
            return [
                'match_results' => [],
                'insights'      => [],
            ];
        }

        $matchResults = $this->engine->match($preferences, $propertyData);

        $insightData  = $this->insightService->buildInsights($matchResults);

        return [
            'match_results' => $matchResults,
            'insights'      => $insightData['insights'],
        ];
    }
}
