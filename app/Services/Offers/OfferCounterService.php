<?php

namespace App\Services\Offers;

use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\OfferEventLog;
use App\Services\Offers\Concerns\EnforcesRequestTimeExpiry;
use Illuminate\Support\Facades\DB;

class OfferCounterService
{
    use EnforcesRequestTimeExpiry;

    private OfferStateMachineService $stateMachine;
    private OfferEventLogService $eventLogger;
    private OfferExpirationService $expirationService;

    public function __construct(
        OfferStateMachineService $stateMachine,
        OfferEventLogService $eventLogger,
        ?OfferExpirationService $expirationService = null,
    ) {
        $this->stateMachine = $stateMachine;
        $this->eventLogger  = $eventLogger;
        // Defaulted so existing positional two-arg construction (unit tests) keeps
        // working; the container injects the real singleton in production.
        $this->expirationService = $expirationService ?? new OfferExpirationService($stateMachine, $eventLogger);
    }

    /**
     * Transition the parent offer to 'countered', create a child offer, and log the event.
     *
     * On success  — sets parent Offer.status to 'countered', saves parent, creates child
     *               offer via Offer::create(), logs 'offer_countered' event, returns array
     *               with allowed=true and the child offer.
     *
     * On failure  — does not modify parent status, does not save parent, does not create
     *               child offer. Logs 'forbidden_transition_attempt' and returns array with
     *               allowed=false and counter_offer=null.
     *
     * @param  Offer        $parent     The offer being countered.
     * @param  int|null     $actorId    ID of the user triggering the counter; null for system.
     * @param  string       $actorRole  Role label (e.g. 'buyer', 'agent', 'system').
     * @param  array        $overrides  Optional field overrides for the child offer.
     * @param  array        $metadata   Arbitrary context forwarded to the event log.
     * @param  string|null  $ipAddress  Request IP; null for system/CLI events.
     *
     * @return array{
     *     allowed:       bool,
     *     parent_offer:  Offer,
     *     counter_offer: Offer|null,
     *     from_status:   string,
     *     to_status:     string,
     *     reason:        string,
     *     event_log:     OfferEventLog,
     * }
     */
    public function counter(
        Offer $parent,
        ?int $actorId,
        string $actorRole,
        array $overrides = [],
        array $metadata = [],
        ?string $ipAddress = null,
    ): array {
        return DB::transaction(function () use ($parent, $actorId, $actorRole, $overrides, $metadata, $ipAddress) {
            // Lock the parent auction, then the parent offer row, and re-read the
            // authoritative status/expiry under lock before deciding (BLK-06).
            if ($parent->offer_auction_id !== null) {
                OfferAuction::query()->whereKey($parent->offer_auction_id)->lockForUpdate()->first();
            }

            $locked = Offer::query()->whereKey($parent->getKey())->lockForUpdate()->first();
            if ($locked !== null) {
                $parent->setRawAttributes($locked->getAttributes(), true);
            }

            $fromStatus = $parent->status;

            // Request-time expiry: an expired parent can no longer be countered.
            if ($this->shouldExpireAtRequestTime($parent, $this->stateMachine)) {
                $this->expirationService->expire(
                    $parent,
                    $actorId,
                    'system',
                    array_merge($metadata, ['source' => 'request_time_expiry', 'blocked_action' => 'offer_countered']),
                    $ipAddress,
                );

                return [
                    'allowed'       => false,
                    'parent_offer'  => $parent,
                    'counter_offer' => null,
                    'from_status'   => $fromStatus,
                    'to_status'     => 'countered',
                    'reason'        => 'This offer has expired and can no longer be countered.',
                    'event_log'     => $this->eventLogger->log(
                        offer: $parent,
                        actorId: $actorId,
                        actorRole: $actorRole,
                        eventType: 'forbidden_transition_attempt',
                        fromStatus: $fromStatus,
                        toStatus: 'countered',
                        metadata: array_merge($metadata, ['blocked_reason' => 'expired']),
                        ipAddress: $ipAddress,
                    ),
                ];
            }

            $validation = $this->stateMachine->validateTransition($fromStatus, 'countered');

            if (! $validation['allowed']) {
            $log = $this->eventLogger->log(
                offer: $parent,
                actorId: $actorId,
                actorRole: $actorRole,
                eventType: 'forbidden_transition_attempt',
                fromStatus: $fromStatus,
                toStatus: 'countered',
                metadata: $metadata,
                ipAddress: $ipAddress,
            );

            return [
                'allowed'       => false,
                'parent_offer'  => $parent,
                'counter_offer' => null,
                'from_status'   => $fromStatus,
                'to_status'     => 'countered',
                'reason'        => $validation['reason'],
                'event_log'     => $log,
            ];
        }

        $parent->status = 'countered';
        $parent->save();

        $childAttributes = array_merge(
            [
                'offer_auction_id' => $parent->offer_auction_id,
                'user_id'          => $actorId ?? $parent->user_id,
                'role'             => $parent->role,
                'listing_snapshot' => $parent->listing_snapshot,
            ],
            $overrides,
            [
                'parent_offer_id' => $parent->id,
                'status'          => 'countered',
            ]
        );

        $child = Offer::create($childAttributes);

        $log = $this->eventLogger->log(
            offer: $parent,
            actorId: $actorId,
            actorRole: $actorRole,
            eventType: 'offer_countered',
            fromStatus: $fromStatus,
            toStatus: 'countered',
            metadata: $metadata,
            ipAddress: $ipAddress,
        );

            return [
                'allowed'       => true,
                'parent_offer'  => $parent,
                'counter_offer' => $child,
                'from_status'   => $fromStatus,
                'to_status'     => 'countered',
                'reason'        => '',
                'event_log'     => $log,
            ];
        });
    }
}
