<?php

namespace App\Observers\Dna;

use App\Jobs\ComputeCompatibilityScore;
use App\Models\BuyerTenantDnaProfile;
use App\Models\PropertyDnaProfile;
use App\Services\Dna\Compatibility\CompatibilityEngine;
use Illuminate\Support\Facades\Log;

/**
 * BuyerTenantDnaProfileCompatibilityObserver — Phase F / Phase K
 *
 * Hooks the BuyerTenantDnaProfile `saved` event and dispatches ComputeCompatibilityScore
 * jobs for each active counterpart PropertyDnaProfile, up to the hard fanout cap.
 *
 * GOVERNANCE CONSTRAINTS enforced here:
 * - Only dispatches compatibility jobs in response to BuyerTenantDnaProfile saves.
 * - Never dispatches DNA generation jobs or any other job type.
 * - Never triggers additional DNA generation.
 * - Enforces a hard fanout cap (fail-closed): if counterpart count exceeds the cap,
 *   logs the overcount and dispatches only up to the cap — never unbounded.
 * - Dispatch loop is chunked (DISPATCH_CHUNK_SIZE) to prevent enqueue storms and
 *   keep per-iteration memory deterministic regardless of how many records are fetched.
 * - All dispatch errors are caught, logged with identifiers only, and discarded silently.
 * - buyer profiles dispatch against seller counterparts only.
 * - tenant profiles dispatch against landlord counterparts only.
 * - No recursive compatibility enqueuing — this observer only runs on DNA profile saves,
 *   never on ListingCompatibilityScore saves.
 * - Compatibility jobs dispatched here must never recursively dispatch additional
 *   compatibility recomputation waves. Fanout is bounded and one-directional only.
 */
class BuyerTenantDnaProfileCompatibilityObserver
{
    /**
     * Hard cap on the number of counterpart profiles dispatched per observer invocation.
     * Prevents uncontrolled dispatch storms as the platform scales.
     * This is prototype-scale behavior only — large-scale fanout requires a separate
     * scalability architecture phase (see FUTURE SCALING NOTE in saved() below).
     */
    private const FANOUT_CAP = CompatibilityEngine::FANOUT_CAP;

    /**
     * Number of counterpart profiles processed per dispatch iteration.
     * Chunking the dispatch loop prevents enqueue storms and keeps peak memory
     * deterministic when FANOUT_CAP-sized result sets are loaded into memory.
     * This is a collection-level chunk (not a query cursor) — the full result set
     * up to FANOUT_CAP is fetched once, then processed in batches of this size.
     *
     * FUTURE SCALING NOTE: When active counterpart counts regularly approach or exceed
     * FANOUT_CAP, replace the collection-chunk pattern in saved() with a cursor-based
     * chunked queue orchestration strategy (e.g., Bus::batch() or a dedicated
     * FanoutCompatibilityBatch job) to avoid loading large result sets into memory
     * in a single query.
     */
    private const DISPATCH_CHUNK_SIZE = 100;

    /**
     * Respond to a BuyerTenantDnaProfile being saved (created or updated).
     *
     * Maps listing_type to the corresponding supply-side counterpart type:
     *   buyer  → seller   counterparts
     *   tenant → landlord counterparts
     */
    public function saved(BuyerTenantDnaProfile $profile): void
    {
        try {
            $counterpartType = $this->resolveCounterpartType($profile->listing_type);
            if ($counterpartType === null) {
                return;
            }

            // Count total active counterpart profiles before applying the cap,
            // so we can log an overcount warning if the cap is exceeded.
            $totalCount = PropertyDnaProfile::where('listing_type', $counterpartType)
                ->whereNull('archived_at')
                ->count();

            if ($totalCount > self::FANOUT_CAP) {
                // Fail-closed: log the overcount and dispatch only up to the cap.
                Log::warning('BuyerTenantDnaProfileCompatibilityObserver: counterpart count exceeds fanout cap', [
                    'demand_listing_type'  => $profile->listing_type,
                    'demand_listing_id'    => $profile->listing_id,
                    'supply_listing_type'  => $counterpartType,
                    'total_counterparts'   => $totalCount,
                    'fanout_cap'           => self::FANOUT_CAP,
                ]);
            }

            $counterparts = PropertyDnaProfile::where('listing_type', $counterpartType)
                ->whereNull('archived_at')
                ->limit(self::FANOUT_CAP)
                ->get(['id', 'listing_type', 'listing_id']);

            // Dispatch in chunks to prevent enqueue storms and keep peak memory
            // deterministic across large fanout waves. FANOUT_CAP enforces the hard
            // upper bound on total dispatches; DISPATCH_CHUNK_SIZE controls how many
            // jobs are enqueued per iteration.
            //
            // FANOUT GOVERNANCE RULE — one-directional, non-recursive:
            // Compatibility jobs dispatched here must never recursively dispatch
            // additional compatibility recomputation waves or DNA profile generation
            // jobs. Fanout is bounded and one-directional only:
            //   BuyerTenantDnaProfile::saved
            //     → ComputeCompatibilityScore::dispatch (up to FANOUT_CAP)
            //       → ListingCompatibilityScore::create  ← chain terminates here
            // ListingCompatibilityScore has no registered observer; no further jobs
            // are ever enqueued as a consequence of these dispatches.
            $counterparts->chunk(self::DISPATCH_CHUNK_SIZE)->each(function ($chunk) use ($profile) {
                foreach ($chunk as $counterpart) {
                    try {
                        ComputeCompatibilityScore::dispatch(
                            $profile->listing_type,
                            $profile->listing_id,
                            $counterpart->listing_type,
                            $counterpart->listing_id
                        );
                    } catch (\Throwable $e) {
                        Log::warning('BuyerTenantDnaProfileCompatibilityObserver: failed to dispatch job for counterpart', [
                            'demand_listing_type'  => $profile->listing_type,
                            'demand_listing_id'    => $profile->listing_id,
                            'supply_listing_type'  => $counterpart->listing_type,
                            'supply_listing_id'    => $counterpart->listing_id,
                            'error'                => $e->getMessage(),
                        ]);
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::warning('BuyerTenantDnaProfileCompatibilityObserver: failed during dispatch', [
                'demand_listing_type' => $profile->listing_type ?? null,
                'demand_listing_id'   => $profile->listing_id ?? null,
                'error'               => $e->getMessage(),
            ]);
        }
    }

    /**
     * Map a demand-side listing_type to its supply-side counterpart type.
     *
     * buyer  → seller
     * tenant → landlord
     *
     * Returns null for any unrecognized type — observer is a no-op for unknown types.
     */
    private function resolveCounterpartType(string $listingType): ?string
    {
        return match ($listingType) {
            'buyer'  => 'seller',
            'tenant' => 'landlord',
            default  => null,
        };
    }
}
