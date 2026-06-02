<?php

namespace App\Services\Offers;

use App\Models\Offer;
use App\Models\OfferEventLog;

class OfferDecisionService
{
    public function __construct(
        private OfferStateMachineService $stateMachine,
        private OfferEventLogService $eventLog,
    ) {}

    public function accept(
        Offer $offer,
        ?int $actorId,
        string $actorRole,
        array $metadata = [],
        ?string $ipAddress = null
    ): array {
        return $this->transition($offer, 'accepted', 'offer_accepted', $actorId, $actorRole, $metadata, $ipAddress);
    }

    public function reject(
        Offer $offer,
        ?int $actorId,
        string $actorRole,
        array $metadata = [],
        ?string $ipAddress = null
    ): array {
        return $this->transition($offer, 'rejected', 'offer_rejected', $actorId, $actorRole, $metadata, $ipAddress);
    }

    public function withdraw(
        Offer $offer,
        ?int $actorId,
        string $actorRole,
        array $metadata = [],
        ?string $ipAddress = null
    ): array {
        return $this->transition($offer, 'withdrawn', 'offer_withdrawn', $actorId, $actorRole, $metadata, $ipAddress);
    }

    private function transition(
        Offer $offer,
        string $toStatus,
        string $eventType,
        ?int $actorId,
        string $actorRole,
        array $metadata,
        ?string $ipAddress
    ): array {
        $fromStatus = $offer->status;

        $validation = $this->stateMachine->validateTransition($fromStatus, $toStatus);

        if ($validation['allowed']) {
            $offer->status = $toStatus;
            $offer->save();

            $log = $this->eventLog->log(
                $offer,
                $actorId,
                $actorRole,
                $eventType,
                $fromStatus,
                $toStatus,
                $metadata,
                $ipAddress
            );

            return [
                'allowed'     => true,
                'offer'       => $offer,
                'from_status' => $fromStatus,
                'to_status'   => $toStatus,
                'reason'      => '',
                'event_log'   => $log,
            ];
        }

        $log = $this->eventLog->log(
            $offer,
            $actorId,
            $actorRole,
            'forbidden_transition_attempt',
            $fromStatus,
            $toStatus,
            $metadata,
            $ipAddress
        );

        return [
            'allowed'     => false,
            'offer'       => $offer,
            'from_status' => $fromStatus,
            'to_status'   => $toStatus,
            'reason'      => $validation['reason'],
            'event_log'   => $log,
        ];
    }
}
