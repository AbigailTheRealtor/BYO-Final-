<?php

namespace App\Services\LocationDna;

use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use Throwable;

/**
 * LocationDnaSummaryService — Phase D Summary Service
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is the deterministic aggregation layer for the Location DNA pipeline.
 * It reads existing Phase B geocode data and Phase C POI rows and compiles them into
 * a structured Location DNA summary, persisting it to property_location_dna.summary_json
 * and generated_at.
 *
 * This service MUST NEVER:
 *   - Make any external API calls of any kind.
 *   - Connect to the AI marketing report or Property DNA persistence pipelines.
 *   - Perform AI or OpenAI calls of any kind.
 *   - Introduce routes, controllers, Blade views, Livewire components, or JavaScript.
 *   - Modify geocoded_lat, geocoded_lng, or any property_location_pois rows.
 *   - Compute scores, recommendations, or generate marketing copy.
 * ==================================================================================
 */
class LocationDnaSummaryService
{
    /**
     * Maps each thematic block key to a [poi_category => output_field_name] dictionary.
     * Output field names follow the approved Phase D contract.
     */
    private const THEMATIC_BLOCKS = [
        'coastal' => [
            'beach'        => 'nearest_beach_miles',
            'beach_access' => 'nearest_beach_access_miles',
            'boat_ramp'    => 'nearest_boat_ramp_miles',
            'marina'       => 'nearest_marina_miles',
        ],
        'daily_convenience' => [
            'grocery_store' => 'nearest_grocery_miles',
            'pharmacy'      => 'nearest_pharmacy_miles',
            'coffee_shop'   => 'nearest_coffee_miles',
            'restaurant'    => 'nearest_restaurant_miles',
        ],
        'outdoor_recreation' => [
            'park'            => 'nearest_park_miles',
            'dog_park'        => 'nearest_dog_park_miles',
            'golf_course'     => 'nearest_golf_course_miles',
            'waterfront_park' => 'nearest_waterfront_park_miles',
        ],
        'transportation' => [
            'transit_station' => 'nearest_transit_miles',
            'gas_station'     => 'nearest_gas_station_miles',
        ],
    ];

    public function __construct(
        private readonly ?LocationDnaAuditService $auditService = null,
    ) {}

    /**
     * Compile and persist a Location DNA summary for the given listing.
     *
     * Returns the approved Phase D six-key output contract in all cases:
     * [
     *     'success'      => bool,         // true only when status === 'completed'
     *     'status'       => string,       // 'completed' | 'skipped' | 'failed'
     *     'listing_type' => string,       // echoed from $listingType
     *     'listing_id'   => int,          // echoed from $listingId
     *     'summary'      => array|null,   // populated on success, null otherwise
     *     'error'        => string|null,  // skip/failure reason, null on success
     * ]
     *
     * Guard conditions (return 'skipped'):
     *   (a) No PropertyLocationDna record exists for the listing.
     *   (b) PropertyLocationDna.geocode_status is not 'geocoded'.
     *   (c) No PropertyLocationPoi rows exist for the listing.
     *
     * On any unexpected Throwable, returns 'failed' without re-throwing.
     *
     * @param  string $listingType  The listing model type (e.g. 'seller_agent_auction').
     * @param  int    $listingId    The primary key of the listing record.
     * @return array                Approved Phase D six-key output contract.
     */
    public function summarizeForListing(string $listingType, int $listingId): array
    {
        try {
            // (a) Guard: DNA record must exist
            $dnaRecord = PropertyLocationDna::where('listing_type', $listingType)
                ->where('listing_id', $listingId)
                ->first();

            if ($dnaRecord === null) {
                $output = $this->skippedOutput(
                    $listingType,
                    $listingId,
                    'No PropertyLocationDna record found for this listing',
                );
                $this->audit($listingType, $listingId, $output);
                return $output;
            }

            // (b) Guard: DNA record must be geocoded
            if ($dnaRecord->geocode_status !== 'geocoded') {
                $output = $this->skippedOutput(
                    $listingType,
                    $listingId,
                    "PropertyLocationDna geocode_status is '{$dnaRecord->geocode_status}', expected 'geocoded'",
                );
                $this->audit($listingType, $listingId, $output);
                return $output;
            }

            // (c) Guard: POI rows must exist
            $poiRows = PropertyLocationPoi::where('listing_type', $listingType)
                ->where('listing_id', $listingId)
                ->get();

            if ($poiRows->isEmpty()) {
                $output = $this->skippedOutput(
                    $listingType,
                    $listingId,
                    'No PropertyLocationPoi rows found for this listing',
                );
                $this->audit($listingType, $listingId, $output);
                return $output;
            }

            // Build nearest_by_category — keyed by poi_category
            $nearestByCategory = [];
            foreach ($poiRows as $row) {
                $nearestByCategory[$row->poi_category] = [
                    'label'         => $row->poi_subtype,
                    'name'          => $row->poi_name,
                    'distance_miles' => $row->distance_miles !== null ? (float) $row->distance_miles : null,
                    'status'        => $row->status,
                    'data_source'   => $row->data_source,
                ];
            }

            // Build category_counts
            $totalCount    = $poiRows->count();
            $foundCount    = $poiRows->where('status', 'found')->count();
            $notFoundCount = $poiRows->where('status', 'not_found')->count();
            $errorCount    = $poiRows->where('status', 'error')->count();

            $categoryCounts = [
                'total_categories' => $totalCount,
                'found'            => $foundCount,
                'not_found'        => $notFoundCount,
                'error'            => $errorCount,
            ];

            // Build geocode block from DNA record
            $geocode = [
                'lat'        => $dnaRecord->geocoded_lat !== null ? (float) $dnaRecord->geocoded_lat : null,
                'lng'        => $dnaRecord->geocoded_lng !== null ? (float) $dnaRecord->geocoded_lng : null,
                'source'     => $dnaRecord->geocode_source,
                'geocoded_at' => $dnaRecord->geocoded_at?->toIso8601String(),
            ];

            // Build thematic sub-blocks — each entry uses the approved Phase D output key names
            $thematicBlocks = [];
            foreach (self::THEMATIC_BLOCKS as $blockKey => $categoryMap) {
                $block = [];
                foreach ($categoryMap as $catKey => $outputKey) {
                    $row = $poiRows->firstWhere('poi_category', $catKey);
                    $block[$outputKey] = ($row !== null && $row->status === 'found' && $row->distance_miles !== null)
                        ? (float) $row->distance_miles
                        : null;
                }
                $thematicBlocks[$blockKey] = $block;
            }

            // Build missing_categories and error_categories
            $missingCategories = $poiRows->where('status', 'not_found')->pluck('poi_category')->values()->all();
            $errorCategories   = $poiRows->where('status', 'error')->pluck('poi_category')->values()->all();

            // Assemble full summary array
            $summary = array_merge(
                [
                    'geocode'            => $geocode,
                    'nearest_by_category' => $nearestByCategory,
                    'category_counts'    => $categoryCounts,
                ],
                $thematicBlocks,
                [
                    'missing_categories' => $missingCategories,
                    'error_categories'   => $errorCategories,
                ],
            );

            // Persist to DNA record
            $dnaRecord->summary_json  = $summary;
            $dnaRecord->generated_at  = now();
            $dnaRecord->save();

            $output = $this->completedOutput($listingType, $listingId, $summary);
            $this->audit($listingType, $listingId, $output);
            return $output;

        } catch (Throwable $e) {
            $output = $this->failedOutput($listingType, $listingId, $e->getMessage());
            $this->audit($listingType, $listingId, $output);
            return $output;
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Write an audit row. Wrapped in its own try/catch so a failure cannot
     * prevent the caller's return value from being delivered.
     */
    private function audit(string $listingType, int $listingId, array $output): void
    {
        try {
            $auditService = $this->auditService ?? new LocationDnaAuditService();
            $auditService->record(
                listingType:    $listingType,
                listingId:      $listingId,
                eventType:      'summary_generated',
                status:         $output['status'],
                source:         null,
                inputSnapshot:  ['listing_type' => $listingType, 'listing_id' => $listingId],
                outputSnapshot: $output,
                error:          $output['error'] ?? null,
            );
        } catch (Throwable) {
            // Audit failure must never alter the service's return value.
        }
    }

    // =========================================================================
    // Output shape helpers — approved Phase D six-key contract in all cases
    // =========================================================================

    private function completedOutput(string $listingType, int $listingId, array $summary): array
    {
        return [
            'success'      => true,
            'status'       => 'completed',
            'listing_type' => $listingType,
            'listing_id'   => $listingId,
            'summary'      => $summary,
            'error'        => null,
        ];
    }

    private function skippedOutput(string $listingType, int $listingId, string $error): array
    {
        return [
            'success'      => false,
            'status'       => 'skipped',
            'listing_type' => $listingType,
            'listing_id'   => $listingId,
            'summary'      => null,
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
            'summary'      => null,
            'error'        => $error,
        ];
    }
}
