<?php

namespace App\Observers\Dna;

use App\Jobs\ComputeBuyerTenantDnaProfile;
use App\Models\BuyerCriteriaAuction;
use Illuminate\Support\Facades\Log;

class BuyerCriteriaAuctionDnaObserver
{
    public function saved(BuyerCriteriaAuction $listing): void
    {
        try {
            ComputeBuyerTenantDnaProfile::dispatch('buyer', $listing->id);
        } catch (\Throwable $e) {
            Log::warning('BuyerCriteriaAuctionDnaObserver: failed to dispatch ComputeBuyerTenantDnaProfile', [
                'listing_id' => $listing->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
