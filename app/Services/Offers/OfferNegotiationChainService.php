<?php

namespace App\Services\Offers;

use App\Models\Offer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

class OfferNegotiationChainService
{
    public function getRootOffer(Offer $offer): Offer
    {
        $current = $offer;

        while ($current->parent_offer_id !== null) {
            $current = Offer::query()->find($current->parent_offer_id);
        }

        return $current;
    }

    public function getChainFromRoot(Offer $rootOffer): Collection
    {
        $allIds    = [$rootOffer->id];
        $levelIds  = [$rootOffer->id];

        while (true) {
            $childIds = Offer::query()
                ->whereIn('parent_offer_id', $levelIds)
                ->pluck('id')
                ->all();

            if (empty($childIds)) {
                break;
            }

            $allIds   = array_merge($allIds, $childIds);
            $levelIds = $childIds;
        }

        return Offer::query()
            ->whereIn('id', $allIds)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    public function getChainForOffer(Offer $offer): Collection
    {
        $root = $this->getRootOffer($offer);

        return $this->getChainFromRoot($root);
    }
}
