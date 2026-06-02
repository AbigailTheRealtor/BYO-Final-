<?php

namespace App\Services\Offers;

use App\Models\Offer;

class OfferPermissionService
{
    private function isFinalStatus(string $status): bool
    {
        return in_array($status, OfferStateMachineService::FINAL_STATUSES, true);
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

        if (!in_array($actorRole, ['buyer', 'system'], true)) {
            return ['allowed' => false, 'action' => $action, 'reason' => "Cannot submit: actor role '{$actorRole}' is not permitted for this action."];
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

        if (!in_array($actorRole, ['seller', 'agent', 'system'], true)) {
            return ['allowed' => false, 'action' => $action, 'reason' => "Cannot accept: actor role '{$actorRole}' is not permitted for this action."];
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

        if (!in_array($actorRole, ['seller', 'agent', 'system'], true)) {
            return ['allowed' => false, 'action' => $action, 'reason' => "Cannot reject: actor role '{$actorRole}' is not permitted for this action."];
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

        if (!in_array($actorRole, ['buyer', 'system'], true)) {
            return ['allowed' => false, 'action' => $action, 'reason' => "Cannot withdraw: actor role '{$actorRole}' is not permitted for this action."];
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

        if (!in_array($actorRole, ['buyer', 'seller', 'agent', 'system'], true)) {
            return ['allowed' => false, 'action' => $action, 'reason' => "Cannot view timeline: actor role '{$actorRole}' is not permitted for this action."];
        }

        return ['allowed' => true, 'action' => $action, 'reason' => ''];
    }
}
