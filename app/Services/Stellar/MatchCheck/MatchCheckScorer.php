<?php

namespace App\Services\Stellar\MatchCheck;

use App\Models\BridgeProperty;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;

/**
 * Match Check scoring layer (Phase 4 · Wave 2 / C6).
 *
 * Translates a MatchCheckPreparation (the C5 read-only decision) into a MatchCheckResult,
 * delegating the actual numeric scoring to the already-built BuyerMatchScorer engine so no
 * scoring math is duplicated or re-invented here. BuyerMatchScorer::score() is a pure,
 * side-effect-free comparison of one BridgeProperty against one BuyerCriteriaPayload — it
 * runs no queries, no lazy Bridge import, and no external API calls (that machinery lives in
 * BuyerMatchService, which this class intentionally does NOT touch).
 *
 * GATING / INERT BY DESIGN. The score engine is invoked only in the single terminal state
 * where it is meaningful (flag ON, listing visible, criteria payload supplied). Every other
 * preparation state short-circuits to a scoreless MatchCheckResult WITHOUT calling
 * BuyerMatchScorer at all — so while the master flag is OFF (its default) the whole layer is
 * inert: prepare() returns disabled → score() returns MatchCheckResult::disabled() and the
 * engine is never reached.
 *
 * This class is not wired to any route, controller, or UI. It performs reads only: no writes,
 * no persistence, no queue dispatch, no enrichment.
 */
class MatchCheckScorer
{
    public function __construct(
        private readonly BuyerMatchScorer $buyerScorer,
    ) {
    }

    /**
     * Produce the Match Check output for an already-prepared (listing, user) decision.
     *
     * @param  MatchCheckPreparation      $preparation  The C5 decision (flag/visibility/intent/criteria).
     * @param  BridgeProperty             $listing      The listing being checked (only read when scoring).
     * @param  BuyerCriteriaPayload|null  $criteria     The scorable criteria payload, or null when the
     *                                                  preferred criteria record has not been loaded into
     *                                                  a payload yet (later-wave seam).
     */
    public function score(
        MatchCheckPreparation $preparation,
        BridgeProperty $listing,
        ?BuyerCriteriaPayload $criteria,
    ): MatchCheckResult {
        // Flag OFF (default) → engine untouched. Keeps the whole layer inert.
        if ($preparation->isDisabled()) {
            return MatchCheckResult::disabled($preparation);
        }

        // Listing not IDX-visible → never scored.
        if ($preparation->isBlocked()) {
            return MatchCheckResult::blocked($preparation);
        }

        // Visible, but the user has no preferred criteria record → empty state, not a score.
        if (! $preparation->hasPreferredCriteria()) {
            return MatchCheckResult::noCriteria($preparation);
        }

        // Preferred criteria exists but its scorable payload was not supplied → seam for the
        // later criteria→payload loading wave. Still scoreless; the engine stays untouched.
        if ($criteria === null) {
            return MatchCheckResult::criteriaNotLoaded($preparation);
        }

        // Flag ON, visible, criteria payload present → delegate to the existing engine.
        $result = $this->buyerScorer->score($listing, $criteria);

        return MatchCheckResult::scored(
            $preparation,
            $result->listingKey,
            $result->totalScore,
            $result->categoryScores,
        );
    }
}
