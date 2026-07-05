<?php

namespace App\Observers\Dna;

use App\Jobs\ComputeDnaScores;
use App\Models\TenantAgentAuction;
use Illuminate\Support\Facades\Log;

/**
 * TenantAgentAuctionDnaScoreObserver — dispatches dna_scores generation when a
 * tenant criteria listing is saved (§ Phase 13). The tenant_agent type produces
 * demand-side scores.
 *
 * NOTE: observing TenantAgentAuction is purely additive (dispatch-on-save); it
 * does NOT refactor the model onto HasListingLifecycle and touches none of its
 * internals — the frozen-code rule is respected. No business logic (addition 3).
 */
class TenantAgentAuctionDnaScoreObserver
{
    public function saved(TenantAgentAuction $listing): void
    {
        if (! config('dna_scores.generation_enabled', false)) {
            return;
        }

        try {
            ComputeDnaScores::dispatch('tenant_agent', $listing->id);
        } catch (\Throwable $e) {
            Log::warning('TenantAgentAuctionDnaScoreObserver: failed to dispatch ComputeDnaScores', [
                'listing_id' => $listing->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
