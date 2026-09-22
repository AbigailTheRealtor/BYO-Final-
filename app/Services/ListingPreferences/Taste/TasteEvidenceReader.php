<?php

namespace App\Services\ListingPreferences\Taste;

use App\Models\ListingPreferenceEvent;
use App\Support\ListingPreferences\SeekerRole;
use App\Support\ListingPreferences\Taste\TasteEventRecord;
use App\Support\ListingPreferences\Taste\TasteEvidence;
use DateTimeImmutable;

/**
 * Reads ONE customer's explicit preference history for ONE seeker role.
 *
 * `listing_preference_events` IS the evidence — the append-only record every
 * Save, Maybe, Pass, reason revision and clear already writes. Nothing else is
 * read: not page views, not searches, not dwell time. Passive browsing is not
 * a choice, and there is no second feedback store.
 *
 * SCOPED BY CONSTRUCTION. The only query takes a user id AND a seeker role, both
 * supplied by the caller from the signed-in account. There is no method that
 * reads across users, so no other customer's history can reach the learner —
 * which is the structural half of the ban on collaborative filtering.
 *
 * READ-ONLY. It selects; it never writes, and the event model refuses updates
 * and deletes anyway.
 */
class TasteEvidenceReader
{
    /**
     * A SAFETY CEILING, not a window. Far above what one customer produces by
     * hand; it exists so a runaway account cannot make one page view load an
     * unbounded history.
     *
     * When it binds, the history is reported INCOMPLETE and nothing is derived
     * from it (TasteEvidence). It is never silently treated as the whole story:
     * reading only the newest N events could cut one home's sequence in the
     * middle and change the direction that home appears to point.
     */
    public const MAX_EVENTS = 5000;

    public function __construct(private readonly int $maxEvents = self::MAX_EVENTS)
    {
    }

    public function for(int $userId, SeekerRole $role): TasteEvidence
    {
        if ($userId <= 0) {
            return TasteEvidence::complete([]);
        }

        $ceiling = max(1, $this->maxEvents);

        // One row past the ceiling is how truncation is DETECTED rather than
        // assumed away: if it comes back, more history exists than may be read.
        $rows = ListingPreferenceEvent::query()
            ->where('user_id', $userId)
            ->where('seeker_role', $role->value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($ceiling + 1)
            ->get(['id', 'subject_key', 'listing_type', 'listing_id', 'to_state', 'reasons_json', 'created_at']);

        if ($rows->count() > $ceiling) {
            return TasteEvidence::truncated();
        }

        $records = [];

        foreach ($rows as $row) {
            if (! is_string($row->subject_key) || $row->subject_key === '' || $row->created_at === null) {
                continue;
            }

            $records[] = new TasteEventRecord(
                id:          (int) $row->id,
                subjectKey:  $row->subject_key,
                listingType: (string) $row->listing_type,
                listingId:   (int) $row->listing_id,
                // No cast on the model, deliberately: a NULL is a clear and must
                // stay distinguishable from any state.
                toState:     $row->to_state === null ? null : (string) $row->to_state,
                reasonKeys:  is_array($row->reasons_json) ? array_values($row->reasons_json) : [],
                at:          DateTimeImmutable::createFromInterface($row->created_at),
            );
        }

        return TasteEvidence::complete($records);
    }
}
