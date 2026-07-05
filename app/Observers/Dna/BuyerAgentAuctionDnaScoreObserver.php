<?php

namespace App\Observers\Dna;

use App\Jobs\ComputeDnaScores;
use App\Models\BuyerAgentAuction;
use Illuminate\Support\Facades\Log;

/**
 * BuyerAgentAuctionDnaScoreObserver — dispatches dna_scores generation when a
 * buyer criteria listing is saved (§ Phase 13). The buyer_agent type produces
 * demand-side scores. No business logic (addition 3); only enqueues
 * ComputeDnaScores behind the default-off master flag.
 */
class BuyerAgentAuctionDnaScoreObserver
{
    public function saved(BuyerAgentAuction $listing): void
    {
        if (! config('dna_scores.generation_enabled', false)) {
            return;
        }

        try {
            ComputeDnaScores::dispatch('buyer_agent', $listing->id);
        } catch (\Throwable $e) {
            Log::warning('BuyerAgentAuctionDnaScoreObserver: failed to dispatch ComputeDnaScores', [
                'listing_id' => $listing->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
