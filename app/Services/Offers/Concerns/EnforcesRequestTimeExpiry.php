<?php

namespace App\Services\Offers\Concerns;

use App\Models\Offer;
use App\Services\Offers\OfferStateMachineService;

/**
 * Request-time expiry enforcement (Phase 1 / BLK-06).
 *
 * A shared, side-effect-free predicate used by the transition services to decide
 * whether an offer has expired at the moment an action is requested — independent
 * of the scheduled `offers:expire-pending` command. This is the safety net that
 * keeps accept/reject/withdraw/counter correct when the scheduler is delayed or
 * unavailable.
 *
 * Semantics (mirrors the SSOT query contract):
 *   active  ⇔ expires_at IS NULL OR expires_at > now()
 *   expired ⇔ expires_at IS NOT NULL AND expires_at <= now()
 *
 * The server clock (now()) and the project timezone convention are used, matching
 * OfferAuction::getStatusAttribute() and ExpireOffersCommand.
 */
trait EnforcesRequestTimeExpiry
{
    /**
     * True when the offer carries a response-by deadline that has passed.
     */
    protected function isOfferExpired(Offer $offer): bool
    {
        return $offer->expires_at !== null
            && now()->greaterThanOrEqualTo($offer->expires_at);
    }

    /**
     * True when the offer is expired AND currently in a status from which the
     * state machine permits a transition to 'expired' (i.e. an active offer).
     * Draft/final offers are never force-expired at request time.
     */
    protected function shouldExpireAtRequestTime(Offer $offer, OfferStateMachineService $stateMachine): bool
    {
        return $this->isOfferExpired($offer)
            && in_array($offer->status, OfferStateMachineService::ACTIVE_STATUSES, true)
            && $stateMachine->canTransition($offer->status, 'expired');
    }
}
