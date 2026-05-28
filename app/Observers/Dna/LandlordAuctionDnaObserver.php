<?php

namespace App\Observers\Dna;

use App\Jobs\ComputePropertyDnaProfile;
use App\Models\LandlordAuction;
use Illuminate\Support\Facades\Log;

class LandlordAuctionDnaObserver
{
    public function saved(LandlordAuction $listing): void
    {
        try {
            ComputePropertyDnaProfile::dispatch('landlord', $listing->id);
        } catch (\Throwable $e) {
            Log::warning('LandlordAuctionDnaObserver: failed to dispatch ComputePropertyDnaProfile', [
                'listing_id' => $listing->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
