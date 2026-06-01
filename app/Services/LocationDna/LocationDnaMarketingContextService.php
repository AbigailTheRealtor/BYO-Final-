<?php

namespace App\Services\LocationDna;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * LocationDnaMarketingContextService — Phase G Marketing Intelligence Integration Layer
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is a thin, read-only integration layer that reshapes already-persisted
 * property_location_dna.summary_json data into a structured marketing location context
 * block for the Marketing Intelligence pipeline. No Location DNA data is recalculated
 * or regenerated — all thematic values are sourced exclusively from the existing
 * thematic blocks inside summary_json.
 *
 * DEFERRED HOOK — AiMarketingReportGeneratorService:
 *   AiMarketingReportGeneratorService is a closed prompt-assembly pipeline with no
 *   optional context injection point. Attaching marketing_location_context as a
 *   nested block in the prompt payload would require modifying a protected service
 *   (Phase XD governance block explicitly forbids external modifications). Integration
 *   of this service's output into the AI Marketing Report pipeline is deferred until
 *   a separately approved hook phase is planned and reviewed.
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
class LocationDnaMarketingContextService
{
    /**
     * The four thematic block keys read from summary_json and their output names
     * in the marketing_location_context payload.
     *
     * Key   = key name as stored in summary_json (Phase D output)
     * Value = key name emitted in marketing_location_context
     */
    private const THEMATIC_MAP = [
        'coastal'            => 'coastal_features',
        'daily_convenience'  => 'daily_convenience',
        'outdoor_recreation' => 'outdoor_recreation',
        'transportation'     => 'transportation',
    ];

    /**
     * Retrieve the persisted Location DNA summary for a listing and return it
     * as a structured marketing location context block.
     *
     * Returns the approved Phase G six-key output contract in all cases:
     * [
     *     'success'                    => bool,         // true only when status === 'available'
     *     'status'                     => string,       // 'available' | 'missing' | 'not_generated' | 'failed'
     *     'listing_type'               => string,       // echoed from $listingType
     *     'listing_id'                 => int,          // echoed from $listingId
     *     'marketing_location_context' => array|null,   // populated on 'available', null otherwise
     *     'error'                      => string|null,  // reason on non-available paths, null on 'available'
     * ]
     *
     * When status === 'available', marketing_location_context contains:
     * [
     *     'coastal_features'    => array,   // thematic block from summary_json['coastal']
     *     'daily_convenience'   => array,   // thematic block from summary_json['daily_convenience']
     *     'outdoor_recreation'  => array,   // thematic block from summary_json['outdoor_recreation']
     *     'transportation'      => array,   // thematic block from summary_json['transportation']
     *     'available_categories' => array,  // thematic keys with at least one non-null distance value
     *     'missing_categories'   => array,  // thematic keys where all distance values are null
     * ]
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
     * @return array                Approved Phase G six-key output contract.
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

            $marketingContext = $this->buildMarketingContext($summary);

            return $this->availableOutput($listingType, $listingId, $marketingContext);

        } catch (Throwable $e) {
            return $this->failedOutput($listingType, $listingId, $e->getMessage());
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Build the marketing_location_context payload from a decoded summary_json array.
     *
     * Extracts the four thematic blocks (coastal → coastal_features, daily_convenience,
     * outdoor_recreation, transportation) from the summary and computes
     * available_categories and missing_categories based on whether each thematic block
     * contains at least one non-null distance value.
     *
     * No scores, no qualitative labels, no marketing copy, no external calls.
     *
     * @param  array $summary  Decoded summary_json array from property_location_dna.
     * @return array           The marketing_location_context payload.
     */
    private function buildMarketingContext(array $summary): array
    {
        $thematicBlocks    = [];
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

        return array_merge($thematicBlocks, [
            'available_categories' => $availableCategories,
            'missing_categories'   => $missingCategories,
        ]);
    }

    // =========================================================================
    // Output shape helpers — approved Phase G six-key contract in all cases
    // =========================================================================

    private function availableOutput(string $listingType, int $listingId, array $marketingContext): array
    {
        return [
            'success'                    => true,
            'status'                     => 'available',
            'listing_type'               => $listingType,
            'listing_id'                 => $listingId,
            'marketing_location_context' => $marketingContext,
            'error'                      => null,
        ];
    }

    private function missingOutput(string $listingType, int $listingId, string $error): array
    {
        return [
            'success'                    => false,
            'status'                     => 'missing',
            'listing_type'               => $listingType,
            'listing_id'                 => $listingId,
            'marketing_location_context' => null,
            'error'                      => $error,
        ];
    }

    private function notGeneratedOutput(string $listingType, int $listingId, string $error): array
    {
        return [
            'success'                    => false,
            'status'                     => 'not_generated',
            'listing_type'               => $listingType,
            'listing_id'                 => $listingId,
            'marketing_location_context' => null,
            'error'                      => $error,
        ];
    }

    private function failedOutput(string $listingType, int $listingId, ?string $error): array
    {
        return [
            'success'                    => false,
            'status'                     => 'failed',
            'listing_type'               => $listingType,
            'listing_id'                 => $listingId,
            'marketing_location_context' => null,
            'error'                      => $error,
        ];
    }
}
