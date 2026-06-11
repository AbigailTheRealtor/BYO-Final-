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

    /**
     * Walk to the root of the chain, then descend through non-final children
     * — always choosing the most recent by (created_at, id) — until no
     * non-final children remain.  The result is the "active leaf": the only
     * offer in the chain that should be acted on.
     *
     * If the entire chain is in a final state (e.g. accepted/rejected) the
     * leaf will be the deepest final node; callers should check its status if
     * they only want to redirect to actionable offers.
     *
     * Assumption: at any given depth there should be at most one non-final
     * child, since the permission layer blocks countering a stale parent.
     * When multiple non-final children somehow exist (data anomaly), the most
     * recent by (created_at DESC, id DESC) is chosen.
     *
     * Guard: a visited-ID set prevents infinite loops on circular references.
     */
    public function getActiveLeaf(Offer $offer): Offer
    {
        $current = $this->getRootOffer($offer);
        $visited = [$current->id => true];

        while (true) {
            $child = Offer::query()
                ->where('parent_offer_id', $current->id)
                ->whereNotIn('status', OfferStateMachineService::FINAL_STATUSES)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            if (!$child || isset($visited[$child->id])) {
                break;
            }

            $visited[$child->id] = true;
            $current = $child;
        }

        return $current;
    }
}
