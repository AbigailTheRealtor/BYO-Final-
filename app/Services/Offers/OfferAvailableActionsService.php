<?php

namespace App\Services\Offers;

use App\Models\Offer;

class OfferAvailableActionsService
{
    public function __construct(
        private OfferPermissionService $permissions
    ) {}

    public function forOffer(Offer $offer, ?int $actorId, string $actorRole): array
    {
        $submit      = $this->permissions->canSubmit($offer, $actorId, $actorRole);
        $counter     = $this->permissions->canCounter($offer, $actorId, $actorRole);
        $accept      = $this->permissions->canAccept($offer, $actorId, $actorRole);
        $reject      = $this->permissions->canReject($offer, $actorId, $actorRole);
        $withdraw    = $this->permissions->canWithdraw($offer, $actorId, $actorRole);
        $cancel      = $this->permissions->canCancel($offer, $actorId, $actorRole);
        $expire      = $this->permissions->canExpire($offer, $actorId, $actorRole);
        $viewTimeline = $this->permissions->canViewTimeline($offer, $actorId, $actorRole);

        return [
            'can_submit'        => (bool) $submit['allowed'],
            'can_counter'       => (bool) $counter['allowed'],
            'can_accept'        => (bool) $accept['allowed'],
            'can_reject'        => (bool) $reject['allowed'],
            'can_withdraw'      => (bool) $withdraw['allowed'],
            'can_cancel'        => (bool) $cancel['allowed'],
            'can_expire'        => (bool) $expire['allowed'],
            'can_view_timeline' => (bool) $viewTimeline['allowed'],
            'reasons'           => [
                'submit'        => (string) $submit['reason'],
                'counter'       => (string) $counter['reason'],
                'accept'        => (string) $accept['reason'],
                'reject'        => (string) $reject['reason'],
                'withdraw'      => (string) $withdraw['reason'],
                'cancel'        => (string) $cancel['reason'],
                'expire'        => (string) $expire['reason'],
                'view_timeline' => (string) $viewTimeline['reason'],
            ],
        ];
    }
}
