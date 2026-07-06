<?php

namespace App\Services\Stellar\MatchCheck;

use App\Models\BridgeProperty;

/**
 * Consumer-facing IDX / listing-visibility gate for Match Check (Phase 4 · F9).
 *
 * A listing may only be shown through /match-check if it is IDX-eligible for public
 * consumer display. This gate is the single visibility policy, so the existing
 * BuyerMatchService::match() IDX filter can later delegate to it ("one policy, one
 * place") and a future permission-gated internal path can substitute a different
 * policy without touching scoring or rendering.
 *
 * IDX semantics deliberately MIRROR BuyerMatchService::match() exactly:
 *   - Only an explicit falsey IDXParticipationYN blocks a listing.
 *   - Absent key, malformed json, or an unparseable value fail OPEN (eligible),
 *     because IDXParticipationYN is near-100% true in the live Bridge/Stellar feed.
 * This equivalence is what makes the future match() → gate unification behavior-neutral.
 * (If the owner later wants a stricter fail-closed posture, change it here, once.)
 */
class ListingVisibilityGate
{
    public function decide(BridgeProperty $listing): VisibilityDecision
    {
        $data = $listing->raw_json ? json_decode($listing->raw_json, true) : [];

        if (!is_array($data)) {
            return VisibilityDecision::visible('idx_malformed_json_default_eligible');
        }

        if (!array_key_exists('IDXParticipationYN', $data)) {
            return VisibilityDecision::visible('idx_absent_default_eligible');
        }

        $raw = $data['IDXParticipationYN'];

        if (is_bool($raw)) {
            return $raw
                ? VisibilityDecision::visible('idx_true')
                : VisibilityDecision::blocked('idx_participation_false');
        }

        // Handles integer 0/1 and string "true"/"false" the Bridge API may return.
        $normalized = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($normalized === false) {
            return VisibilityDecision::blocked('idx_participation_false');
        }

        // null → unparseable → fail open, per the live-feed spec above.
        return VisibilityDecision::visible($normalized === null ? 'idx_unparseable_default_eligible' : 'idx_true');
    }

    public function isConsumerVisible(BridgeProperty $listing): bool
    {
        return $this->decide($listing)->visible;
    }
}
