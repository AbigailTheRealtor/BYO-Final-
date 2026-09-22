<?php

namespace App\Services\ListingPreferences\Taste;

use App\Support\ListingPreferences\SeekerRole;
use App\Support\ListingPreferences\Taste\TasteChoiceTimeline;
use App\Support\ListingPreferences\Taste\TasteDnaDeriver;
use App\Support\ListingPreferences\Taste\TasteProfile;

/**
 * Taste DNA for one customer and one seeker role — computed, never stored.
 *
 *   events  (TasteEvidenceReader)       the customer's own explicit history
 *   → choices (TasteChoiceTimeline)     one decision per run, per home
 *   → facts   (TasteListingFactsReader) governed characteristics of those homes
 *   → profile (TasteDnaDeriver)         deterministic signals
 *
 * NEVER FROM PART OF THE HISTORY. If the reader's safety ceiling binds, the
 * profile is `incomplete` and carries no signals, rather than a confident
 * conclusion drawn from the newest slice of the customer's choices.
 *
 * NO PERSISTENCE, AND THAT IS THE REBUILD STRATEGY. There is no derived table
 * to fall out of step with the history, no job to schedule and no migration to
 * run: every call recomputes from the append-only events, so "rebuild" is this
 * method, and the same history gives the same profile every time.
 *
 * NO CONSUMER BUT THE CUSTOMER'S OWN PAGE. Ranking, matching, Stellar, Ask AI
 * and recommendations do not call this, and TasteDnaArchitectureGuardTest fails
 * the build if one starts to. Phase 4 learns and explains; it changes nothing a
 * customer is shown anywhere else.
 */
class TasteDnaService
{
    public function __construct(
        private readonly TasteEvidenceReader $evidence,
        private readonly TasteListingFactsReader $facts,
    ) {
    }

    public function profileFor(int $userId, SeekerRole $role): TasteProfile
    {
        $evidence = $this->evidence->for($userId, $role);

        // A history cut off by the reader's safety ceiling is never derived
        // from: a partial read can change which way a home appears to point.
        if (! $evidence->complete) {
            return TasteProfile::incomplete($userId, $role);
        }

        $histories = TasteChoiceTimeline::build($evidence->records);

        if ($histories === []) {
            return TasteProfile::empty($userId, $role);
        }

        $refKeys = [];

        foreach ($histories as $history) {
            $refKeys[$history->refKey] = true;
        }

        return TasteDnaDeriver::derive($userId, $role, $histories, $this->facts->for(array_keys($refKeys)));
    }
}
