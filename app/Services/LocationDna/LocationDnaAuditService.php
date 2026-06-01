<?php

namespace App\Services\LocationDna;

use App\Models\PropertyLocationDnaAudit;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * LocationDnaAuditService — Phase E Storage + Audit Layer
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is the append-only audit trail for the Location DNA pipeline.
 * It records every geocode, POI calculation, and summary generation event for
 * observability and debugging.
 *
 * This service MUST NEVER:
 *   - Connect to the AI marketing report or Property DNA persistence pipelines.
 *   - Perform AI or OpenAI calls of any kind.
 *   - Introduce routes, controllers, Blade views, Livewire components, or JavaScript.
 *   - Make any external API calls.
 *   - Throw exceptions to callers — all failures are swallowed internally.
 * ==================================================================================
 */
class LocationDnaAuditService
{
    /**
     * Record an audit event for a Location DNA pipeline operation.
     *
     * Always inserts a new row (append-only). Never throws — on any failure the
     * exception is logged at WARNING level and an unsaved model instance is returned
     * so callers always receive a return value without an exception propagating.
     *
     * @param  string      $listingType    The listing model type (e.g. 'seller_agent_auction').
     * @param  int         $listingId      The primary key of the listing record.
     * @param  string      $eventType      One of: 'geocode', 'poi_distance', 'summary_generated'.
     * @param  string      $status         The outcome status from the service (e.g. 'geocoded', 'skipped', 'failed').
     * @param  string|null $source         Data source identifier, if applicable (e.g. 'google').
     * @param  array|null  $inputSnapshot  Snapshot of input data passed to the service.
     * @param  array|null  $outputSnapshot Snapshot of the output array returned by the service.
     * @param  string|null $error          Error message, if the operation failed or was skipped.
     * @return PropertyLocationDnaAudit    The persisted model (or an unsaved instance on DB failure).
     */
    public function record(
        string  $listingType,
        int     $listingId,
        string  $eventType,
        string  $status,
        ?string $source,
        ?array  $inputSnapshot,
        ?array  $outputSnapshot,
        ?string $error,
    ): PropertyLocationDnaAudit {
        try {
            return PropertyLocationDnaAudit::create([
                'listing_type'    => $listingType,
                'listing_id'      => $listingId,
                'event_type'      => $eventType,
                'status'          => $status,
                'source'          => $source,
                'input_snapshot'  => $inputSnapshot,
                'output_snapshot' => $outputSnapshot,
                'error'           => $error,
                'created_at'      => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('LocationDnaAuditService::record() failed to persist audit row', [
                'listing_type' => $listingType,
                'listing_id'   => $listingId,
                'event_type'   => $eventType,
                'exception'    => $e->getMessage(),
            ]);

            return new PropertyLocationDnaAudit([
                'listing_type'    => $listingType,
                'listing_id'      => $listingId,
                'event_type'      => $eventType,
                'status'          => $status,
                'source'          => $source,
                'input_snapshot'  => $inputSnapshot,
                'output_snapshot' => $outputSnapshot,
                'error'           => $error,
            ]);
        }
    }
}
