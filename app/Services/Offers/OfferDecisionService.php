<?php

namespace App\Services\Offers;

use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\OfferMeta;
use App\Services\Offers\Concerns\EnforcesRequestTimeExpiry;
use Illuminate\Support\Facades\DB;

class OfferDecisionService
{
    use EnforcesRequestTimeExpiry;

    private OfferStateMachineService $stateMachine;
    private OfferEventLogService $eventLog;
    private OfferExpirationService $expirationService;
    private OfferNegotiationChainService $chainService;

    public function __construct(
        OfferStateMachineService $stateMachine,
        OfferEventLogService $eventLog,
        ?OfferExpirationService $expirationService = null,
        ?OfferNegotiationChainService $chainService = null,
    ) {
        $this->stateMachine      = $stateMachine;
        $this->eventLog          = $eventLog;
        // Defaulted so existing positional two-arg construction (and the unit tests
        // that use it) keep working; the container injects the real singletons.
        $this->expirationService = $expirationService ?? new OfferExpirationService($stateMachine, $eventLog);
        $this->chainService      = $chainService ?? new OfferNegotiationChainService();
    }

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

    /**
     * B2.1B — administrative cancellation of an accepted offer (accepted → cancelled).
     *
     * Routes through the same atomic, row-locked transition() as accept/reject/
     * withdraw, so the B1.2 locking, request-time-expiry safety net, and immutable
     * event-log write are reused unchanged. Because $toStatus is neither 'accepted'
     * nor an active status, the competing-offer close and snapshot capture never
     * fire — previously-rejected competitors stay rejected and the accepted-terms
     * snapshot is preserved. The cancelled branch inside transition() then resets
     * the parent listing to Active under the same lock.
     */
    public function cancel(
        Offer $offer,
        ?int $actorId,
        string $actorRole,
        array $metadata = [],
        ?string $ipAddress = null
    ): array {
        return $this->transition($offer, 'cancelled', 'offer_cancelled', $actorId, $actorRole, $metadata, $ipAddress);
    }

    /**
     * Atomic, row-locked offer transition (BLK-06).
     *
     * The whole operation runs inside a database transaction. The parent auction
     * and the offer row are locked with lockForUpdate (a no-op on SQLite, real on
     * PostgreSQL), then status and expiry are re-read from the locked row before
     * any decision is made. If any step throws, the transaction rolls back and no
     * status change, snapshot, event log, or competing-offer close survives.
     *
     * Authorization is enforced by the controller/actions layer before this call;
     * identity cannot change under a race, so the invariants re-checked here are
     * the ones that can: current status, expiry, and (for accept) whether a
     * competing offer has already been accepted.
     */
    private function transition(
        Offer $offer,
        string $toStatus,
        string $eventType,
        ?int $actorId,
        string $actorRole,
        array $metadata,
        ?string $ipAddress
    ): array {
        return DB::transaction(function () use ($offer, $toStatus, $eventType, $actorId, $actorRole, $metadata, $ipAddress) {
            // Lock the parent auction first (consistent lock ordering across all
            // offer mutations on the same parent), then the offer row itself.
            if ($offer->offer_auction_id !== null) {
                OfferAuction::query()->whereKey($offer->offer_auction_id)->lockForUpdate()->first();
            }

            $locked = Offer::query()->whereKey($offer->getKey())->lockForUpdate()->first();
            if ($locked !== null) {
                // Re-seed the in-memory instance with the authoritative locked state so
                // status/expiry checks below see committed reality, not a stale snapshot.
                $offer->setRawAttributes($locked->getAttributes(), true);
            }

            $fromStatus = $offer->status;

            // ── Request-time expiry (BLK-06) ──────────────────────────────────
            // If the offer has passed its response-by deadline, block the requested
            // action and transition it to 'expired' — do not rely on the scheduler.
            if ($toStatus !== 'expired' && $this->shouldExpireAtRequestTime($offer, $this->stateMachine)) {
                $this->expirationService->expire(
                    $offer,
                    $actorId,
                    'system',
                    array_merge($metadata, ['source' => 'request_time_expiry', 'blocked_action' => $eventType]),
                    $ipAddress,
                );

                return [
                    'allowed'     => false,
                    'offer'       => $offer,
                    'from_status' => $fromStatus,
                    'to_status'   => $toStatus,
                    'reason'      => 'This offer has expired and can no longer be actioned.',
                    'event_log'   => null,
                ];
            }

            // ── Competing-offer guard for acceptance (BLK-05) ─────────────────
            // Under lock, confirm no other root offer on the same parent has already
            // been accepted before we accept this one — prevents a double-accept race.
            $acceptedChainIds = [];
            if ($toStatus === 'accepted' && $offer->offer_auction_id !== null) {
                $root             = $this->chainService->getRootOffer($offer);
                $acceptedChainIds = $this->chainService->getChainFromRoot($root)->pluck('id')->all();

                $competingAcceptedExists = Offer::query()
                    ->where('offer_auction_id', $offer->offer_auction_id)
                    ->whereNotIn('id', $acceptedChainIds)
                    ->where('status', 'accepted')
                    ->lockForUpdate()
                    ->exists();

                if ($competingAcceptedExists) {
                    return [
                        'allowed'     => false,
                        'offer'       => $offer,
                        'from_status' => $fromStatus,
                        'to_status'   => $toStatus,
                        'reason'      => 'Another offer on this listing has already been accepted.',
                        'event_log'   => null,
                    ];
                }
            }

            $validation = $this->stateMachine->validateTransition($fromStatus, $toStatus);

            if ($validation['allowed']) {
                $offer->status = $toStatus;
                $offer->save();

                if ($toStatus === 'accepted') {
                    $this->captureAcceptedTermsSnapshot($offer);
                }

                // B2.1B — on administrative cancellation of an accepted offer, reset
                // the parent listing to Active under the same lock (idempotent safety
                // reset). Competing offers are intentionally NOT touched here, so any
                // previously-rejected competitors remain rejected.
                if ($toStatus === 'cancelled') {
                    $this->reactivateListing($offer);
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

                $result = [
                    'allowed'     => true,
                    'offer'       => $offer,
                    'from_status' => $fromStatus,
                    'to_status'   => $toStatus,
                    'reason'      => '',
                    'event_log'   => $log,
                ];

                // ── Close competing offers on acceptance (BLK-05) ─────────────
                if ($toStatus === 'accepted') {
                    $result['closed_competing_offers'] = $this->closeCompetingOffers(
                        $offer,
                        $acceptedChainIds,
                        $actorId,
                        $actorRole,
                        $ipAddress,
                    );
                }

                return $result;
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
        });
    }

    /**
     * Transition every other active competing offer on the same parent to
     * 'rejected' (BLK-05). "Competing" means: same offer_auction_id, status in
     * {submitted, countered}, and NOT part of the accepted offer's own chain
     * (so the accepted counteroffer chain is preserved intact). Offers on any
     * other listing/auction are never touched — the query is scoped by
     * offer_auction_id.
     *
     * Runs inside the acceptance transaction with the rows locked, so it either
     * commits with the acceptance or rolls back entirely. Returns the closed
     * offers so the caller can dispatch rejection notifications after commit.
     *
     * @param  int[]  $acceptedChainIds  ids belonging to the accepted chain (preserved)
     * @return Offer[]
     */
    private function closeCompetingOffers(
        Offer $accepted,
        array $acceptedChainIds,
        ?int $actorId,
        string $actorRole,
        ?string $ipAddress,
    ): array {
        if ($accepted->offer_auction_id === null) {
            return [];
        }

        $competitors = Offer::query()
            ->where('offer_auction_id', $accepted->offer_auction_id)
            ->whereNotIn('id', $acceptedChainIds)
            ->whereIn('status', OfferStateMachineService::ACTIVE_STATUSES)
            ->whereNotIn('status', ['draft']) // drafts are not yet competing submissions
            ->lockForUpdate()
            ->get();

        $closed = [];

        foreach ($competitors as $competitor) {
            if (! $this->stateMachine->canTransition($competitor->status, 'rejected')) {
                continue;
            }

            $fromStatus         = $competitor->status;
            $competitor->status = 'rejected';
            $competitor->save();

            $this->eventLog->log(
                $competitor,
                $actorId,
                $actorRole,
                'offer_rejected',
                $fromStatus,
                'rejected',
                ['reason' => 'competing_offer_auto_closed', 'accepted_offer_id' => $accepted->id],
                $ipAddress,
            );

            $closed[] = $competitor;
        }

        return $closed;
    }

    /**
     * B2.1B — idempotent "return to Active" reset for the parent listing when an
     * accepted offer is cancelled (requirement #7). The parent auction row is
     * already locked by the enclosing transition() transaction, so this write is
     * atomic with the status change. Clears the sold flag and forces the
     * listing_status meta back to 'Active'; a no-op in effect when already active.
     */
    private function reactivateListing(Offer $offer): void
    {
        if ($offer->offer_auction_id === null) {
            return;
        }

        $auction = OfferAuction::query()->whereKey($offer->offer_auction_id)->first();
        if ($auction === null) {
            return;
        }

        $auction->is_sold = false;
        $auction->save();
        $auction->saveMeta('listing_status', 'Active');
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
