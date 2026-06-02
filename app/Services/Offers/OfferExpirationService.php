<?php

namespace App\Services\Offers;

use App\Models\Offer;
use App\Models\OfferEventLog;

class OfferExpirationService
{
    public function __construct(
        private readonly OfferStateMachineService $stateMachine,
        private readonly OfferEventLogService $eventLogger,
    ) {}

    /**
     * Transition an Offer from its current status to 'expired'.
     *
     * On success  — sets Offer.status to 'expired', persists via save(), writes
     *               an 'offer_expired' event log, and returns an array with allowed=true.
     *
     * On failure  — writes a 'forbidden_transition_attempt' event log and returns
     *               an array with allowed=false. Offer.status is never touched;
     *               save() is never called.
     *
     * @param  Offer        $offer      The offer being expired.
     * @param  int|null     $actorId    ID of the user triggering the expiration; null for system.
     * @param  string       $actorRole  Role label (e.g. 'system', 'agent').
     * @param  array        $metadata   Arbitrary context forwarded to the event log.
     * @param  string|null  $ipAddress  Request IP; null for system/CLI events.
     *
     * @return array{
     *     allowed:      bool,
     *     offer:        Offer,
     *     from_status:  string,
     *     to_status:    string,
     *     reason:       string,
     *     event_log:    OfferEventLog,
     * }
     */
    public function expire(
        Offer $offer,
        ?int $actorId,
        string $actorRole = 'system',
        array $metadata = [],
        ?string $ipAddress = null,
    ): array {
        $fromStatus = $offer->status;

        $validation = $this->stateMachine->validateTransition($fromStatus, 'expired');

        if (! $validation['allowed']) {
            $eventLog = $this->eventLogger->log(
                offer: $offer,
                actorId: $actorId,
                actorRole: $actorRole,
                eventType: 'forbidden_transition_attempt',
                fromStatus: $fromStatus,
                toStatus: 'expired',
                metadata: $metadata,
                ipAddress: $ipAddress,
            );

            return [
                'allowed'     => false,
                'offer'       => $offer,
                'from_status' => $fromStatus,
                'to_status'   => 'expired',
                'reason'      => $validation['reason'],
                'event_log'   => $eventLog,
            ];
        }

        $offer->status = 'expired';
        $offer->save();

        $eventLog = $this->eventLogger->log(
            offer: $offer,
            actorId: $actorId,
            actorRole: $actorRole,
            eventType: 'offer_expired',
            fromStatus: $fromStatus,
            toStatus: 'expired',
            metadata: $metadata,
            ipAddress: $ipAddress,
        );

        return [
            'allowed'     => true,
            'offer'       => $offer,
            'from_status' => $fromStatus,
            'to_status'   => 'expired',
            'reason'      => '',
            'event_log'   => $eventLog,
        ];
    }
}
