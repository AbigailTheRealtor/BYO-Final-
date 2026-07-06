<?php

namespace App\Services\Stellar\MatchCheck;

use App\Models\BridgeProperty;
use App\Models\User;
use App\Services\Stellar\CriteriaListingResolver;

/**
 * Match Check orchestration entry point (Phase 4 · Wave 2 / C5).
 *
 * Composes the already-built Wave 0/1 pieces into a single read-only decision for a
 * (listing, user) pair, and nothing more:
 *   1. master feature flag  (config/mls_match_check.php — CheckMatchCheckEnabled mirrors it)
 *   2. ListingVisibilityGate      (F9)
 *   3. CriteriaIntentDetector     (F5)
 *   4. CriteriaListingResolver::resolvePreferred() (F5)
 *
 * INERT BY DESIGN. This class performs no scoring, no rendering, no external enrichment,
 * and no persistence — only reads. It is not wired to any route, controller, or UI, and
 * prepare() short-circuits to MatchCheckPreparation::disabled() while the master flag is
 * OFF (its default). Enabling it is a later wave's job; C5 only assembles the plumbing.
 *
 * Ordering rationale: visibility is checked BEFORE intent/criteria so a listing that would
 * never be shown to a consumer never triggers criteria resolution (a DB read) on its behalf.
 */
class MatchCheckOrchestrator
{
    public function __construct(
        private readonly ListingVisibilityGate $visibilityGate,
        private readonly CriteriaIntentDetector $intentDetector,
        private readonly CriteriaListingResolver $criteriaResolver,
    ) {
    }

    /**
     * Whether the consumer-facing Match Check feature is enabled. Single source of truth,
     * identical to the CheckMatchCheckEnabled middleware. Defaults OFF.
     */
    public function isEnabled(): bool
    {
        return (bool) config('mls_match_check.enabled', false);
    }

    /**
     * Assemble the Match Check preparation for a listing + user. Read-only; see class docblock.
     */
    public function prepare(BridgeProperty $listing, User $user): MatchCheckPreparation
    {
        // Flag OFF (default) → do nothing. Keeps the whole composition inert.
        if (! $this->isEnabled()) {
            return MatchCheckPreparation::disabled();
        }

        // F9 — a listing that is not IDX-eligible is never shown; stop before touching criteria.
        $decision = $this->visibilityGate->decide($listing);
        if (! $decision->visible) {
            return MatchCheckPreparation::blocked($decision);
        }

        // F5 — detect sale vs rental so the right criteria engine is auto-selected (null = ambiguous).
        $intent = $this->intentDetector->detectFromModel($listing);

        // F5 — auto-select the user's preferred criteria for that side (null = empty state / agent chooses).
        $preferred = $this->criteriaResolver->resolvePreferred($user, $intent);

        return MatchCheckPreparation::ready($decision, $intent, $preferred);
    }
}
