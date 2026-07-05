<?php

namespace App\Observers\Dna;

use App\Jobs\ComputeDnaScores;
use App\Models\SellerAgentAuction;
use Illuminate\Support\Facades\Log;

/**
 * SellerAgentAuctionDnaScoreObserver — dispatches dna_scores generation when a
 * seller listing is saved (§ Phase 13). Contains NO business logic (addition 3):
 * it only enqueues ComputeDnaScores, gated by the default-off master flag, so
 * the same service can be driven by any other trigger unchanged.
 */
class SellerAgentAuctionDnaScoreObserver
{
    public function saved(SellerAgentAuction $listing): void
    {
        if (! config('dna_scores.generation_enabled', false)) {
            return;
        }

        try {
            ComputeDnaScores::dispatch('seller_agent', $listing->id);
        } catch (\Throwable $e) {
            Log::warning('SellerAgentAuctionDnaScoreObserver: failed to dispatch ComputeDnaScores', [
                'listing_id' => $listing->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
