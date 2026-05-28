<?php

namespace App\Observers\Dna;

use App\Jobs\ComputeCompatibilityScore;
use App\Models\BuyerTenantDnaProfile;
use App\Models\PropertyDnaProfile;
use App\Services\Dna\Compatibility\CompatibilityEngine;
use Illuminate\Support\Facades\Log;

/**
 * PropertyDnaProfileCompatibilityObserver — Phase F
 *
 * Hooks the PropertyDnaProfile `saved` event and dispatches ComputeCompatibilityScore
 * jobs for each active counterpart BuyerTenantDnaProfile, up to the hard fanout cap.
 *
 * GOVERNANCE CONSTRAINTS enforced here:
 * - Only dispatches compatibility jobs in response to PropertyDnaProfile saves.
 * - Never dispatches DNA generation jobs or any other job type.
 * - Never triggers additional DNA generation.
 * - Enforces a hard fanout cap (fail-closed): if counterpart count exceeds the cap,
 *   logs the overcount and dispatches only up to the cap — never unbounded.
 * - All dispatch errors are caught, logged with identifiers only, and discarded silently.
 * - seller profiles dispatch against buyer counterparts only.
 * - landlord profiles dispatch against tenant counterparts only.
 * - No recursive compatibility enqueuing — this observer only runs on DNA profile saves,
 *   never on ListingCompatibilityScore saves.
 */
class PropertyDnaProfileCompatibilityObserver
{
    /**
     * Hard cap on the number of counterpart profiles dispatched per observer invocation.
     * Prevents uncontrolled dispatch storms as the platform scales.
     * This is prototype-scale behavior only — large-scale fanout requires a separate
     * scalability architecture phase.
     */
    private const FANOUT_CAP = CompatibilityEngine::FANOUT_CAP;

    /**
     * Respond to a PropertyDnaProfile being saved (created or updated).
     *
     * Maps listing_type to the corresponding demand-side counterpart type:
     *   seller   → buyer  counterparts
     *   landlord → tenant counterparts
     */
    public function saved(PropertyDnaProfile $profile): void
    {
        try {
            $counterpartType = $this->resolveCounterpartType($profile->listing_type);
            if ($counterpartType === null) {
                return;
            }

            // Count total active counterpart profiles before applying the cap,
            // so we can log an overcount warning if the cap is exceeded.
            $totalCount = BuyerTenantDnaProfile::where('listing_type', $counterpartType)
                ->whereNull('archived_at')
                ->count();

            if ($totalCount > self::FANOUT_CAP) {
                // Fail-closed: log the overcount and dispatch only up to the cap.
                Log::warning('PropertyDnaProfileCompatibilityObserver: counterpart count exceeds fanout cap', [
                    'supply_listing_type'  => $profile->listing_type,
                    'supply_listing_id'    => $profile->listing_id,
                    'demand_listing_type'  => $counterpartType,
                    'total_counterparts'   => $totalCount,
                    'fanout_cap'           => self::FANOUT_CAP,
                ]);
            }

            $counterparts = BuyerTenantDnaProfile::where('listing_type', $counterpartType)
                ->whereNull('archived_at')
                ->limit(self::FANOUT_CAP)
                ->get(['id', 'listing_type', 'listing_id']);

            foreach ($counterparts as $counterpart) {
                try {
                    ComputeCompatibilityScore::dispatch(
                        $counterpart->listing_type,
                        $counterpart->listing_id,
                        $profile->listing_type,
                        $profile->listing_id
                    );
                } catch (\Throwable $e) {
                    Log::warning('PropertyDnaProfileCompatibilityObserver: failed to dispatch job for counterpart', [
                        'supply_listing_type'  => $profile->listing_type,
                        'supply_listing_id'    => $profile->listing_id,
                        'demand_listing_type'  => $counterpart->listing_type,
                        'demand_listing_id'    => $counterpart->listing_id,
                        'error'                => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('PropertyDnaProfileCompatibilityObserver: failed during dispatch', [
                'supply_listing_type' => $profile->listing_type ?? null,
                'supply_listing_id'   => $profile->listing_id ?? null,
                'error'               => $e->getMessage(),
            ]);
        }
    }

    /**
     * Map a supply-side listing_type to its demand-side counterpart type.
     *
     * seller   → buyer
     * landlord → tenant
     *
     * Returns null for any unrecognized type — observer is a no-op for unknown types.
     */
    private function resolveCounterpartType(string $listingType): ?string
    {
        return match ($listingType) {
            'seller'   => 'buyer',
            'landlord' => 'tenant',
            default    => null,
        };
    }
}
