<?php

namespace App\Services\Offers;

use App\Models\Offer;
use App\Models\OfferEventLog;

class OfferSubmissionService
{
    public function __construct(
        private readonly OfferStateMachineService $stateMachine,
        private readonly OfferEventLogService $eventLogger,
    ) {}

    /**
     * Transition an Offer from its current status to 'submitted'.
     *
     * On success  — sets Offer.status, stamps submitted_at (if empty), persists
     *               via save(), writes an 'offer_submitted' event log, and returns
     *               an array with allowed=true.
     *
     * On failure  — writes a 'forbidden_transition_attempt' event log and returns
     *               an array with allowed=false. Offer.status and submitted_at are
     *               never touched; save() is never called.
     *
     * @param  Offer        $offer      The offer being submitted.
     * @param  int|null     $actorId    ID of the user triggering the submission; null for system.
     * @param  string       $actorRole  Role label (e.g. 'buyer', 'agent', 'system').
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
    public function submit(
        Offer $offer,
        ?int $actorId,
        string $actorRole = 'buyer',
        array $metadata = [],
        ?string $ipAddress = null,
    ): array {
        $fromStatus = $offer->status;

        $validation = $this->stateMachine->validateTransition($fromStatus, 'submitted');

        if (! $validation['allowed']) {
            $eventLog = $this->eventLogger->log(
                offer: $offer,
                actorId: $actorId,
                actorRole: $actorRole,
                eventType: 'forbidden_transition_attempt',
                fromStatus: $fromStatus,
                toStatus: 'submitted',
                metadata: $metadata,
                ipAddress: $ipAddress,
            );

            return [
                'allowed'     => false,
                'offer'       => $offer,
                'from_status' => $fromStatus,
                'to_status'   => 'submitted',
                'reason'      => $validation['reason'],
                'event_log'   => $eventLog,
            ];
        }

        $offer->status = 'submitted';

        if (empty($offer->submitted_at)) {
            $offer->submitted_at = now();
        }

        $offer->save();

        $eventLog = $this->eventLogger->log(
            offer: $offer,
            actorId: $actorId,
            actorRole: $actorRole,
            eventType: 'offer_submitted',
            fromStatus: $fromStatus,
            toStatus: 'submitted',
            metadata: $metadata,
            ipAddress: $ipAddress,
        );

        return [
            'allowed'     => true,
            'offer'       => $offer,
            'from_status' => $fromStatus,
            'to_status'   => 'submitted',
            'reason'      => '',
            'event_log'   => $eventLog,
        ];
    }
}
