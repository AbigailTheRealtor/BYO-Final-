<?php

namespace App\Repositories;

use App\Models\Offer;
use Illuminate\Support\Collection;

class OfferRepository
{
    public function findById(int $offerId): ?Offer
    {
        return Offer::find($offerId);
    }

    public function findWithRelations(int $offerId): ?Offer
    {
        return Offer::with([
            'user',
            'offerAuction',
            'parentOffer',
            'childOffers',
            'metas',
            'eventLogs',
        ])->find($offerId);
    }

    public function findByAuction(int $offerAuctionId): Collection
    {
        return Offer::where('offer_auction_id', $offerAuctionId)->get();
    }

    public function findActiveByAuction(int $offerAuctionId): Collection
    {
        return Offer::where('offer_auction_id', $offerAuctionId)
            ->whereIn('status', ['submitted', 'countered'])
            ->get();
    }

    public function findChildren(int $offerId): Collection
    {
        return Offer::where('parent_offer_id', $offerId)->get();
    }

    public function findParent(int $offerId): ?Offer
    {
        $offer = Offer::with('parentOffer')->find($offerId);

        return $offer?->parentOffer;
    }

    public function getEventHistory(int $offerId): Collection
    {
        $offer = Offer::find($offerId);

        if (!$offer) {
            return collect();
        }

        return $offer->eventLogs()->orderBy('created_at')->get();
    }

    public function getOfferMeta(int $offerId): Collection
    {
        $offer = Offer::find($offerId);

        if (!$offer) {
            return collect();
        }

        return $offer->metas()->get();
    }

    public function getAcceptedOfferForAuction(int $offerAuctionId): ?Offer
    {
        return Offer::where('offer_auction_id', $offerAuctionId)
            ->where('status', 'accepted')
            ->first();
    }

    public function loadRelationships(Offer $offer): Offer
    {
        return $offer->load([
            'user',
            'offerAuction',
            'parentOffer',
            'childOffers',
            'metas',
            'eventLogs',
        ]);
    }
}
