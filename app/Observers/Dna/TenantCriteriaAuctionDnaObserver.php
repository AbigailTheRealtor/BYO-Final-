<?php

namespace App\Observers\Dna;

use App\Jobs\ComputeBuyerTenantDnaProfile;
use App\Models\TenantCriteriaAuction;
use Illuminate\Support\Facades\Log;

class TenantCriteriaAuctionDnaObserver
{
    public function saved(TenantCriteriaAuction $listing): void
    {
        try {
            ComputeBuyerTenantDnaProfile::dispatch('tenant', $listing->id);
        } catch (\Throwable $e) {
            Log::warning('TenantCriteriaAuctionDnaObserver: failed to dispatch ComputeBuyerTenantDnaProfile', [
                'listing_id' => $listing->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
