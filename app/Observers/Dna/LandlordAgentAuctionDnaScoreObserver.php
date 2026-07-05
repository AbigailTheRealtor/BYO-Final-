<?php

namespace App\Observers\Dna;

use App\Jobs\ComputeDnaScores;
use App\Models\LandlordAgentAuction;
use Illuminate\Support\Facades\Log;

/**
 * LandlordAgentAuctionDnaScoreObserver — dispatches dna_scores generation when a
 * landlord listing is saved (§ Phase 13). No business logic (addition 3); only
 * enqueues ComputeDnaScores behind the default-off master flag.
 */
class LandlordAgentAuctionDnaScoreObserver
{
    public function saved(LandlordAgentAuction $listing): void
    {
        if (! config('dna_scores.generation_enabled', false)) {
            return;
        }

        try {
            ComputeDnaScores::dispatch('landlord_agent', $listing->id);
        } catch (\Throwable $e) {
            Log::warning('LandlordAgentAuctionDnaScoreObserver: failed to dispatch ComputeDnaScores', [
                'listing_id' => $listing->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
