<?php

namespace App\Services\Offers;

use App\Models\Offer;
use App\Models\OfferEventLog;
use App\Models\OfferMeta;

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

            if ($toStatus === 'accepted') {
                $this->captureAcceptedTermsSnapshot($offer);
            }

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

    /**
     * Write-once snapshot of the offer's meta bag at the moment of acceptance.
     *
     * Scope: the full meta bag is intentionally captured. Every key stored in
     * offer_metas at acceptance time — including screening information submitted
     * as part of a rental application, financing details, contingencies, and
     * custom terms — is part of the legal record. Capturing everything avoids
     * the risk of missing a key that is rendered on the accepted-offer page
     * through future schema changes.
     *
     * The one exclusion is the snapshot key itself (accepted_terms_snapshot),
     * which would cause circular serialisation.
     *
     * If a snapshot already exists, this method is a no-op — it must never
     * overwrite or regenerate an existing snapshot entry.
     */
    private function captureAcceptedTermsSnapshot(Offer $offer): void
    {
        $exists = OfferMeta::where('offer_id', $offer->id)
            ->where('meta_key', 'accepted_terms_snapshot')
            ->exists();

        if ($exists) {
            return;
        }

        $metas = OfferMeta::where('offer_id', $offer->id)
            ->where('meta_key', '!=', 'accepted_terms_snapshot')
            ->pluck('meta_value', 'meta_key')
            ->toArray();

        OfferMeta::create([
            'offer_id'   => $offer->id,
            'meta_key'   => 'accepted_terms_snapshot',
            'meta_value' => json_encode($metas),
        ]);
    }
}
