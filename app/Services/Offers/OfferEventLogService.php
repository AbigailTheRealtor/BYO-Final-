<?php

namespace App\Services\Offers;

use App\Models\Offer;
use App\Models\OfferEventLog;

class OfferEventLogService
{
    /**
     * Append one immutable audit row to offer_event_logs.
     *
     * This method only inserts — it never updates or deletes existing rows
     * and never touches Offer.status.
     *
     * @param  Offer        $offer       The offer being audited.
     * @param  int|null     $actorId     ID of the user who triggered the event; null for system events.
     * @param  string       $actorRole   Role label (e.g. 'buyer', 'agent', 'system').
     * @param  string       $eventType   Event identifier (e.g. 'submitted', 'status_changed').
     * @param  string|null  $fromStatus  Status before the event; null when not applicable.
     * @param  string|null  $toStatus    Status after the event; null when not applicable.
     * @param  array        $metadata    Arbitrary key-value context stored as JSON.
     * @param  string|null  $ipAddress   Request IP; null for system/CLI events.
     *
     * @return OfferEventLog The newly created (persisted) log row.
     */
    public function log(
        Offer $offer,
        ?int $actorId,
        string $actorRole,
        string $eventType,
        ?string $fromStatus,
        ?string $toStatus,
        array $metadata = [],
        ?string $ipAddress = null
    ): OfferEventLog {
        return OfferEventLog::create([
            'offer_id'    => $offer->id,
            'actor_id'    => $actorId,
            'actor_role'  => $actorRole,
            'event_type'  => $eventType,
            'from_status' => $fromStatus,
            'to_status'   => $toStatus,
            'metadata'    => $metadata,
            'ip_address'  => $ipAddress,
        ]);
    }
}
