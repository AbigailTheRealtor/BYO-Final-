<?php

namespace App\Services\Offers;

use App\Models\Offer;
use App\Models\OfferEventLog;
use Illuminate\Database\Eloquent\Collection;

class OfferHistoryService
{
    public function forOffer(Offer $offer): Collection
    {
        return OfferEventLog::where('offer_id', $offer->id)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function forOfferId(int $offerId): Collection
    {
        return OfferEventLog::where('offer_id', $offerId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function latestForOffer(Offer $offer, int $limit = 10): Collection
    {
        return OfferEventLog::where('offer_id', $offer->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
