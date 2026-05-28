<?php

namespace App\Observers\Dna;

use App\Jobs\ComputePropertyDnaProfile;
use App\Models\PropertyAuction;
use Illuminate\Support\Facades\Log;

class PropertyAuctionDnaObserver
{
    public function saved(PropertyAuction $listing): void
    {
        try {
            ComputePropertyDnaProfile::dispatch('seller', $listing->id);
        } catch (\Throwable $e) {
            Log::warning('PropertyAuctionDnaObserver: failed to dispatch ComputePropertyDnaProfile', [
                'listing_id' => $listing->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
