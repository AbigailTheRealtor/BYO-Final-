<?php

namespace App\Services\LocationDna;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * LocationDnaIntelligenceContextService — Phase H Listing Intelligence Integration Layer
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is a thin, read-only integration layer that reshapes already-persisted
 * property_location_dna.summary_json data into a structured location intelligence
 * context block. No Location DNA data is recalculated or regenerated — all thematic
 * values are sourced exclusively from the existing thematic blocks inside summary_json.
 *
 * DESIGNATED AI HOOK — location_intelligence_context:
 *   The `location_intelligence_context` payload returned by this service is the
 *   designated hook for future AI system consumption (Ask AI, Listing Chatbot,
 *   Buyer/Tenant Matching, Property DNA, Marketing Intelligence). Downstream AI
 *   systems should consume this structured block without needing to know the internal
 *   shape of summary_json. No AI integration occurs in this phase.
 *
 * This service MUST NEVER:
 *   - Call LocationDnaGeocodeService, LocationDnaPoiDistanceService, or
 *     LocationDnaSummaryService — regenerating or triggering Location DNA is
 *     explicitly out of scope.
 *   - Write to any database table.
 *   - Make any external API calls of any kind.
 *   - Connect to the AI marketing report or OpenAI pipelines.
 *   - Perform AI or OpenAI calls of any kind.
 *   - Introduce routes, controllers, Blade views, Livewire components, or JavaScript.
 *   - Compute scores, recommendations, or generate marketing copy.
 *   - Apply qualitative labels (best/ideal/luxury/safe/etc.) of any kind.
 *   - Introduce demographic assumptions or audience targeting language.
 * ==================================================================================
 */
class LocationDnaIntelligenceContextService
{
    /**
     * The four thematic block keys read from summary_json and their output names
     * in the location_intelligence_context payload.
     *
     * Key   = key name as stored in summary_json (Phase D output)
     * Value = key name emitted in location_intelligence_context
     */
    private const THEMATIC_MAP = [
        'coastal'            => 'coastal_features',
        'daily_convenience'  => 'daily_convenience',
        'outdoor_recreation' => 'outdoor_recreation',
        'transportation'     => 'transportation',
    ];

    /**
     * The five canonical nearest_highlights keys and the thematic block each is
     * sourced from. These are extracted directly from the decoded summary_json
     * thematic blocks; null is used when a key is absent.
     *
     * Key   = distance field name in the thematic block (and in nearest_highlights)
     * Value = thematic block name in summary_json
     */
    private const NEAREST_HIGHLIGHTS_MAP = [
        'nearest_beach_miles'   => 'coastal',
        'nearest_marina_miles'  => 'coastal',
        'nearest_grocery_miles' => 'daily_convenience',
        'nearest_park_miles'    => 'outdoor_recreation',
        'nearest_transit_miles' => 'transportation',
    ];

    /**
     * Retrieve the persisted Location DNA summary for a listing and return it
     * as a structured location intelligence context block.
     *
     * Returns the approved Phase H six-key output contract in all cases:
     * [
     *     'success'                       => bool,         // true only when status === 'available'
     *     'status'                         => string,       // 'available' | 'missing' | 'not_generated' | 'failed'
     *     'listing_type'                   => string,       // echoed from $listingType
     *     'listing_id'                     => int,          // echoed from $listingId
     *     'location_intelligence_context'  => array|null,   // populated on 'available', null otherwise
     *     'error'                          => string|null,  // reason on non-available paths, null on 'available'
     * ]
     *
     * When status === 'available', location_intelligence_context contains:
     * [
     *     'coastal_features'    => array,         // thematic block from summary_json['coastal']
     *     'daily_convenience'   => array,         // thematic block from summary_json['daily_convenience']
     *     'outdoor_recreation'  => array,         // thematic block from summary_json['outdoor_recreation']
     *     'transportation'      => array,         // thematic block from summary_json['transportation']
     *     'nearest_highlights'  => array,         // five canonical distance keys (null if absent)
     *     'available_categories' => array,        // thematic keys with at least one non-null distance value
     *     'missing_categories'   => array,        // thematic keys where all distance values are null
     * ]
     *
     * nearest_highlights always contains exactly these five keys:
     *   nearest_beach_miles   (from coastal)
     *   nearest_marina_miles  (from coastal)
     *   nearest_grocery_miles (from daily_convenience)
     *   nearest_park_miles    (from outdoor_recreation)
     *   nearest_transit_miles (from transportation)
     *
     * Status rules:
     *   'missing'       — no property_location_dna record found for this listing.
     *   'not_generated' — record exists but summary_json is null or empty.
     *   'available'     — record exists and summary_json is populated.
     *   'failed'        — any Throwable caught during execution.
     *
     * Uses DB::table() (not Eloquent) to avoid transaction-poisoning risk on
     * PostgreSQL (see postgres-gate-resolver memory note).
     *
     * This method performs no database writes of any kind.
     *
     * @param  string $listingType  The listing model type (e.g. 'seller_agent_auction').
     * @param  int    $listingId    The primary key of the listing record.
     * @return array                Approved Phase H six-key output contract.
     */
    public function getForListing(string $listingType, int $listingId): array
    {
        try {
            $record = DB::table('property_location_dna')
                ->where('listing_type', $listingType)
                ->where('listing_id', $listingId)
                ->select(['summary_json', 'generated_at'])
                ->first();

            if ($record === null) {
                return $this->missingOutput($listingType, $listingId,
                    'No property_location_dna record found for this listing');
            }

            $summaryJson = $record->summary_json;

            if ($summaryJson === null || $summaryJson === '' || $summaryJson === '[]') {
                return $this->notGeneratedOutput($listingType, $listingId,
                    'property_location_dna record exists but summary_json has not been generated');
            }

            $summary = is_string($summaryJson) ? json_decode($summaryJson, true) : (array) $summaryJson;

            if (empty($summary)) {
                return $this->notGeneratedOutput($listingType, $listingId,
                    'property_location_dna summary_json decoded to an empty array');
            }

            $intelligenceContext = $this->buildIntelligenceContext($summary);

            return $this->availableOutput($listingType, $listingId, $intelligenceContext);

        } catch (Throwable $e) {
            return $this->failedOutput($listingType, $listingId, $e->getMessage());
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Build the location_intelligence_context payload from a decoded summary_json array.
     *
     * Extracts the four thematic blocks (coastal → coastal_features, daily_convenience,
     * outdoor_recreation, transportation) from the summary and computes
     * available_categories and missing_categories based on whether each thematic block
     * contains at least one non-null distance value.
     *
     * Additionally builds nearest_highlights by extracting the five canonical distance
     * keys from their respective thematic blocks. Keys absent from the block are null.
     *
     * No scores, no qualitative labels, no marketing copy, no external calls.
     *
     * @param  array $summary  Decoded summary_json array from property_location_dna.
     * @return array           The location_intelligence_context payload.
     */
    private function buildIntelligenceContext(array $summary): array
    {
        $thematicBlocks      = [];
        $availableCategories = [];
        $missingCategories   = [];

        foreach (self::THEMATIC_MAP as $summaryKey => $outputKey) {
            $block = isset($summary[$summaryKey]) && is_array($summary[$summaryKey])
                ? $summary[$summaryKey]
                : [];

            $thematicBlocks[$outputKey] = $block;

            $hasNonNull = false;
            foreach ($block as $value) {
                if ($value !== null) {
                    $hasNonNull = true;
                    break;
                }
            }

            if ($hasNonNull) {
                $availableCategories[] = $outputKey;
            } else {
                $missingCategories[] = $outputKey;
            }
        }

        $nearestHighlights = $this->buildNearestHighlights($summary);

        return array_merge($thematicBlocks, [
            'nearest_highlights'   => $nearestHighlights,
            'available_categories' => $availableCategories,
            'missing_categories'   => $missingCategories,
        ]);
    }

    /**
     * Build the nearest_highlights sub-array by extracting the five canonical
     * distance keys from their respective thematic blocks in the decoded summary.
     *
     * Always returns exactly the five canonical keys; value is null when the key
     * is absent from the thematic block.
     *
     * @param  array $summary  Decoded summary_json array from property_location_dna.
     * @return array           Associative array of the five canonical distance keys.
     */
    private function buildNearestHighlights(array $summary): array
    {
        $highlights = [];

        foreach (self::NEAREST_HIGHLIGHTS_MAP as $distanceKey => $thematicBlock) {
            $block = isset($summary[$thematicBlock]) && is_array($summary[$thematicBlock])
                ? $summary[$thematicBlock]
                : [];

            $highlights[$distanceKey] = $block[$distanceKey] ?? null;
        }

        return $highlights;
    }

    // =========================================================================
    // Output shape helpers — approved Phase H six-key contract in all cases
    // =========================================================================

    private function availableOutput(string $listingType, int $listingId, array $intelligenceContext): array
    {
        return [
            'success'                      => true,
            'status'                       => 'available',
            'listing_type'                 => $listingType,
            'listing_id'                   => $listingId,
            'location_intelligence_context' => $intelligenceContext,
            'error'                        => null,
        ];
    }

    private function missingOutput(string $listingType, int $listingId, string $error): array
    {
        return [
            'success'                      => false,
            'status'                       => 'missing',
            'listing_type'                 => $listingType,
            'listing_id'                   => $listingId,
            'location_intelligence_context' => null,
            'error'                        => $error,
        ];
    }

    private function notGeneratedOutput(string $listingType, int $listingId, string $error): array
    {
        return [
            'success'                      => false,
            'status'                       => 'not_generated',
            'listing_type'                 => $listingType,
            'listing_id'                   => $listingId,
            'location_intelligence_context' => null,
            'error'                        => $error,
        ];
    }

    private function failedOutput(string $listingType, int $listingId, ?string $error): array
    {
        return [
            'success'                      => false,
            'status'                       => 'failed',
            'listing_type'                 => $listingType,
            'listing_id'                   => $listingId,
            'location_intelligence_context' => null,
            'error'                        => $error,
        ];
    }
}
