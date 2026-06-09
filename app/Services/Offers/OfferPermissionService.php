<?php

namespace App\Services\Offers;

use App\Models\Offer;

class OfferPermissionService
{
    private function isFinalStatus(string $status): bool
    {
        return in_array($status, OfferStateMachineService::FINAL_STATUSES, true);
    }

    /**
     * Collect the two legitimate party IDs for this offer negotiation:
     *   1. The listing owner (offerAuction.user_id)
     *   2. The root submitter (walk up parent chain to the original offer's user_id)
     *
     * Returns a de-duplicated array.  Agents and system callers bypass this check.
     */
    private function getLegitimatePartyIds(Offer $offer): array
    {
        $listingOwnerId = $offer->offerAuction?->user_id;

        // Walk to the root of the counter chain to find the original submitter.
        $root = $offer;
        while ($root->parent_offer_id !== null) {
            $root = $root->parentOffer;
        }
        $rootSubmitterId = $root->user_id;

        return array_unique(array_filter([$listingOwnerId, $rootSubmitterId]));
    }

    public function canSubmit(Offer $offer, ?int $actorId, string $actorRole): array
    {
        $action = 'submit';
        $status = $offer->status;

        if ($this->isFinalStatus($status)) {
            return ['allowed' => false, 'action' => $action, 'reason' => "Cannot submit: offer is in a final state '{$status}'."];
        }

        if ($status !== 'draft') {
            return ['allowed' => false, 'action' => $action, 'reason' => "Cannot submit: offer status is '{$status}', expected 'draft'."];
        }

        if ($actorRole === 'system') {
            return ['allowed' => true, 'action' => $action, 'reason' => ''];
        }

        if ($actorId === null) {
            return ['allowed' => false, 'action' => $action, 'reason' => 'Cannot submit: actor must be authenticated.'];
        }

        if ($actorId !== $offer->user_id) {
            return ['allowed' => false, 'action' => $action, 'reason' => 'Cannot submit: only the offer creator may submit a draft.'];
        }

        return ['allowed' => true, 'action' => $action, 'reason' => ''];
    }

    public function canCounter(Offer $offer, ?int $actorId, string $actorRole): array
    {
        $action = 'counter';
        $status = $offer->status;

        if ($this->isFinalStatus($status)) {
            return ['allowed' => false, 'action' => $action, 'reason' => "Cannot counter: offer is in a final state '{$status}'."];
        }

        if (!in_array($status, ['submitted', 'countered'], true)) {
            return ['allowed' => false, 'action' => $action, 'reason' => "Cannot counter: offer status is '{$status}', expected 'submitted' or 'countered'."];
        }

        if (!in_array($actorRole, ['buyer', 'seller', 'agent', 'system'], true)) {
            return ['allowed' => false, 'action' => $action, 'reason' => "Cannot counter: actor role '{$actorRole}' is not permitted for this action."];
        }

        if ($actorId !== null && $actorId === $offer->user_id) {
            return ['allowed' => false, 'action' => $action, 'reason' => 'Cannot counter: you submitted this offer and must wait for the other party to respond.'];
        }

        // Guard: only the active leaf of the negotiation chain can be acted upon.
        // If this offer already has a non-final child, it is a stale parent.
        if ($offer->childOffers()->whereNotIn('status', OfferStateMachineService::FINAL_STATUSES)->exists()) {
            return ['allowed' => false, 'action' => $action, 'reason' => 'Cannot counter: a counter offer is already pending; act on the counter offer instead.'];
        }

        // Only 'system' bypasses party membership. All other actors (including agents) must be
        // one of the two negotiation parties: the listing owner or the root offer submitter.
        if ($actorId !== null && $actorRole !== 'system') {
            $legitimateIds = $this->getLegitimatePartyIds($offer);
            if (!in_array($actorId, $legitimateIds, true)) {
                return ['allowed' => false, 'action' => $action, 'reason' => 'Cannot counter: you are not a party to this offer negotiation.'];
            }
        }

        return ['allowed' => true, 'action' => $action, 'reason' => ''];
    }

    public function canAccept(Offer $offer, ?int $actorId, string $actorRole): array
    {
        $action = 'accept';
        $status = $offer->status;

        if ($this->isFinalStatus($status)) {
            return ['allowed' => false, 'action' => $action, 'reason' => "Cannot accept: offer is in a final state '{$status}'."];
        }

        if (!in_array($status, ['submitted', 'countered'], true)) {
            return ['allowed' => false, 'action' => $action, 'reason' => "Cannot accept: offer status is '{$status}', expected 'submitted' or 'countered'."];
        }

        if (!in_array($actorRole, ['buyer', 'seller', 'agent', 'system'], true)) {
            return ['allowed' => false, 'action' => $action, 'reason' => "Cannot accept: actor role '{$actorRole}' is not permitted for this action."];
        }

        if ($actorId !== null && $actorId === $offer->user_id) {
            return ['allowed' => false, 'action' => $action, 'reason' => 'Cannot accept: you submitted this offer and must wait for the other party to respond.'];
        }

        // Guard: only the active leaf of the negotiation chain can be acted upon.
        if ($offer->childOffers()->whereNotIn('status', OfferStateMachineService::FINAL_STATUSES)->exists()) {
            return ['allowed' => false, 'action' => $action, 'reason' => 'Cannot accept: a counter offer is already pending; act on the counter offer instead.'];
        }

        // Only 'system' bypasses party membership. All other actors (including agents) must be
        // one of the two negotiation parties: the listing owner or the root offer submitter.
        if ($actorId !== null && $actorRole !== 'system') {
            $legitimateIds = $this->getLegitimatePartyIds($offer);
            if (!in_array($actorId, $legitimateIds, true)) {
                return ['allowed' => false, 'action' => $action, 'reason' => 'Cannot accept: you are not a party to this offer negotiation.'];
            }
        }

        return ['allowed' => true, 'action' => $action, 'reason' => ''];
    }

    public function canReject(Offer $offer, ?int $actorId, string $actorRole): array
    {
        $action = 'reject';
        $status = $offer->status;

        if ($this->isFinalStatus($status)) {
            return ['allowed' => false, 'action' => $action, 'reason' => "Cannot reject: offer is in a final state '{$status}'."];
        }

        if (!in_array($status, ['submitted', 'countered'], true)) {
            return ['allowed' => false, 'action' => $action, 'reason' => "Cannot reject: offer status is '{$status}', expected 'submitted' or 'countered'."];
        }

        if (!in_array($actorRole, ['buyer', 'seller', 'agent', 'system'], true)) {
            return ['allowed' => false, 'action' => $action, 'reason' => "Cannot reject: actor role '{$actorRole}' is not permitted for this action."];
        }

        if ($actorId !== null && $actorId === $offer->user_id) {
            return ['allowed' => false, 'action' => $action, 'reason' => 'Cannot reject: you submitted this offer and must wait for the other party to respond.'];
        }

        // Guard: only the active leaf of the negotiation chain can be acted upon.
        if ($offer->childOffers()->whereNotIn('status', OfferStateMachineService::FINAL_STATUSES)->exists()) {
            return ['allowed' => false, 'action' => $action, 'reason' => 'Cannot reject: a counter offer is already pending; act on the counter offer instead.'];
        }

        // Only 'system' bypasses party membership. All other actors (including agents) must be
        // one of the two negotiation parties: the listing owner or the root offer submitter.
        if ($actorId !== null && $actorRole !== 'system') {
            $legitimateIds = $this->getLegitimatePartyIds($offer);
            if (!in_array($actorId, $legitimateIds, true)) {
                return ['allowed' => false, 'action' => $action, 'reason' => 'Cannot reject: you are not a party to this offer negotiation.'];
            }
        }

        return ['allowed' => true, 'action' => $action, 'reason' => ''];
    }

    public function canWithdraw(Offer $offer, ?int $actorId, string $actorRole): array
    {
        $action = 'withdraw';
        $status = $offer->status;

        if ($this->isFinalStatus($status)) {
            return ['allowed' => false, 'action' => $action, 'reason' => "Cannot withdraw: offer is in a final state '{$status}'."];
        }

        if (!in_array($status, ['submitted', 'countered'], true)) {
            return ['allowed' => false, 'action' => $action, 'reason' => "Cannot withdraw: offer status is '{$status}', expected 'submitted' or 'countered'."];
        }

        if ($actorRole === 'system') {
            return ['allowed' => true, 'action' => $action, 'reason' => ''];
        }

        if ($actorId === null) {
            return ['allowed' => false, 'action' => $action, 'reason' => 'Cannot withdraw: actor must be authenticated.'];
        }

        if ($actorId !== $offer->user_id) {
            return ['allowed' => false, 'action' => $action, 'reason' => 'Cannot withdraw: only the offer creator may withdraw this offer.'];
        }

        return ['allowed' => true, 'action' => $action, 'reason' => ''];
    }

    public function canExpire(Offer $offer, ?int $actorId, string $actorRole = 'system'): array
    {
        $action = 'expire';
        $status = $offer->status;

        if ($this->isFinalStatus($status)) {
            return ['allowed' => false, 'action' => $action, 'reason' => "Cannot expire: offer is in a final state '{$status}'."];
        }

        if (!in_array($status, ['submitted', 'countered'], true)) {
            return ['allowed' => false, 'action' => $action, 'reason' => "Cannot expire: offer status is '{$status}', expected 'submitted' or 'countered'."];
        }

        if ($actorRole !== 'system') {
            return ['allowed' => false, 'action' => $action, 'reason' => "Cannot expire: actor role '{$actorRole}' is not permitted; only 'system' may expire offers."];
        }

        return ['allowed' => true, 'action' => $action, 'reason' => ''];
    }

    public function canViewTimeline(Offer $offer, ?int $actorId, string $actorRole): array
    {
        $action = 'view_timeline';

        if ($actorRole === 'system') {
            return ['allowed' => true, 'action' => $action, 'reason' => ''];
        }

        if ($actorId === null) {
            return ['allowed' => false, 'action' => $action, 'reason' => ''];
        }

        $legitimateIds = $this->getLegitimatePartyIds($offer);
        if (!in_array($actorId, $legitimateIds, true)) {
            return ['allowed' => false, 'action' => $action, 'reason' => ''];
        }

        return ['allowed' => true, 'action' => $action, 'reason' => ''];
    }
}
