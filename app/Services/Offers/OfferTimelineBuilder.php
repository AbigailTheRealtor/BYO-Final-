<?php

namespace App\Services\Offers;

use App\Models\Offer;
use Illuminate\Support\Collection;

class OfferTimelineBuilder
{
    public function __construct(
        private readonly OfferNegotiationChainService $chainService,
        private readonly OfferHistoryService $historyService,
    ) {}

    /**
     * Build a display-ready timeline for the full negotiation chain that contains
     * the given offer.
     *
     * @param  Offer  $offer  Any offer in the chain.
     * @return array<int, array>
     */
    public function buildForOffer(Offer $offer): array
    {
        $chain        = $this->chainService->getChainForOffer($offer);
        $terminalLeaf = $this->chainService->getTerminalLeaf($offer);

        return $this->buildForChain($chain, $terminalLeaf?->id);
    }

    /**
     * Build a display-ready timeline from an already-assembled chain collection.
     *
     * Each item includes an `is_terminal` boolean flag. The flag is set by
     * matching against $terminalLeafId (the actual deepest-path terminal offer
     * resolved by OfferNegotiationChainService::getTerminalLeaf). Flagging by
     * offer ID rather than by scanning for the last final-status item avoids
     * misidentifying an older rejected sibling in a branching anomaly.
     *
     * @param  Collection<int, Offer>  $offers           Offers in chain order (root first).
     * @param  int|null                $terminalLeafId   ID of the terminal leaf, or null if chain is active.
     * @return array<int, array>
     */
    public function buildForChain(Collection $offers, ?int $terminalLeafId = null): array
    {
        $items = [];

        foreach ($offers as $offer) {
            $logs      = $this->historyService->forOffer($offer);
            $eventCount = $logs->count();

            $latestLog = $logs->sortByDesc('created_at')->first();

            $items[] = [
                'offer_id'          => $offer->id,
                'parent_offer_id'   => $offer->parent_offer_id,
                'status'            => $offer->status,
                'created_at'        => $offer->created_at?->format('Y-m-d H:i:s'),
                'submitted_at'      => $offer->submitted_at?->format('Y-m-d H:i:s'),
                'event_count'       => $eventCount,
                'latest_event_type' => $latestLog?->event_type ?? null,
                'latest_event_at'   => $latestLog ? $latestLog->created_at?->format('Y-m-d H:i:s') : null,
                'is_terminal'       => $terminalLeafId !== null && $offer->id === $terminalLeafId,
            ];
        }

        return $items;
    }
}
