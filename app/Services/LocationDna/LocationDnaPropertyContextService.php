<?php

namespace App\Services\LocationDna;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * LocationDnaPropertyContextService — Phase F Integration Layer
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is a thin, read-only integration layer that exposes already-persisted
 * property_location_dna.summary_json data as a factual context block for the
 * Property DNA layer.
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
 *   - Modify geocoded_lat, geocoded_lng, or any property_location_pois rows.
 *   - Compute scores, recommendations, or generate marketing copy.
 * ==================================================================================
 */
class LocationDnaPropertyContextService
{
    /**
     * Retrieve the persisted Location DNA summary for a listing and return it
     * as a structured context block for the Property DNA layer.
     *
     * Returns the approved Phase F six-key output contract in all cases:
     * [
     *     'success'      => bool,         // true only when status === 'available'
     *     'status'       => string,       // 'available' | 'missing' | 'not_generated' | 'failed'
     *     'listing_type' => string,       // echoed from $listingType
     *     'listing_id'   => int,          // echoed from $listingId
     *     'location_dna' => array|null,   // populated on 'available', null otherwise
     *     'error'        => string|null,  // reason on non-available paths, null on 'available'
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
     * @return array                Approved Phase F six-key output contract.
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

            return $this->availableOutput($listingType, $listingId, $summary);

        } catch (Throwable $e) {
            return $this->failedOutput($listingType, $listingId, $e->getMessage());
        }
    }

    // =========================================================================
    // Output shape helpers — approved Phase F six-key contract in all cases
    // =========================================================================

    private function availableOutput(string $listingType, int $listingId, array $summary): array
    {
        return [
            'success'      => true,
            'status'       => 'available',
            'listing_type' => $listingType,
            'listing_id'   => $listingId,
            'location_dna' => $summary,
            'error'        => null,
        ];
    }

    private function missingOutput(string $listingType, int $listingId, string $error): array
    {
        return [
            'success'      => false,
            'status'       => 'missing',
            'listing_type' => $listingType,
            'listing_id'   => $listingId,
            'location_dna' => null,
            'error'        => $error,
        ];
    }

    private function notGeneratedOutput(string $listingType, int $listingId, string $error): array
    {
        return [
            'success'      => false,
            'status'       => 'not_generated',
            'listing_type' => $listingType,
            'listing_id'   => $listingId,
            'location_dna' => null,
            'error'        => $error,
        ];
    }

    private function failedOutput(string $listingType, int $listingId, ?string $error): array
    {
        return [
            'success'      => false,
            'status'       => 'failed',
            'listing_type' => $listingType,
            'listing_id'   => $listingId,
            'location_dna' => null,
            'error'        => $error,
        ];
    }
}
