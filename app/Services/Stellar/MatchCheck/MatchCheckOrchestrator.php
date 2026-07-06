<?php

namespace App\Services\Stellar\MatchCheck;

use App\Models\BridgeProperty;
use App\Models\User;
use App\Services\Stellar\BuyerCriteriaLoader;
use App\Services\Stellar\BuyerOfferListingCriteriaLoader;
use App\Services\Stellar\CriteriaListingResolver;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\TenantCriteriaLoader;
use App\Services\Stellar\TenantOfferListingCriteriaLoader;

/**
 * Match Check orchestration entry point (Phase 4 · Wave 2 / C5 + C6).
 *
 * Composes the already-built Wave 0/1 pieces into a single read-only decision for a
 * (listing, user) pair, and nothing more:
 *   1. master feature flag  (config/mls_match_check.php — CheckMatchCheckEnabled mirrors it)
 *   2. ListingVisibilityGate      (F9)
 *   3. CriteriaIntentDetector     (F5)
 *   4. CriteriaListingResolver::resolvePreferred() (F5)
 *
 * prepare() (C5) returns that composed decision. evaluate() (C6) extends it one step: it
 * runs prepare() and hands the result to MatchCheckScorer, which turns it into a
 * MatchCheckResult — delegating any actual numeric scoring to the existing BuyerMatchScorer
 * engine only in the single state where a score is meaningful.
 *
 * git-C8 closes the CRITERIA_NOT_LOADED seam: when evaluate()'s caller supplies no payload AND
 * the preparation resolved a preferred criteria record, evaluate() uses MatchCheckCriteriaLoader
 * to produce the BuyerCriteriaPayload itself, so the backend can reach a SCORED result end-to-end.
 * An explicitly-supplied payload is still honored verbatim (the loader is skipped).
 *
 * INERT BY DESIGN. This class performs no rendering, no external enrichment, and no
 * persistence — only reads. It is not wired to any route, controller, or UI, and both
 * entry points short-circuit while the master flag is OFF (its default): prepare() returns
 * MatchCheckPreparation::disabled() and evaluate() returns MatchCheckResult::disabled()
 * without ever touching the score engine. Enabling it is a later wave's job.
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
        private readonly ?MatchCheckScorer $scorer = null,
        private readonly ?MatchCheckCriteriaLoader $criteriaLoader = null,
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

    /**
     * Prepare() + score (Phase 4 · Wave 2 / C6; seam closed in git-C8). Composes the C5 decision
     * with the scoring layer into a single MatchCheckResult. Read-only; see class docblock.
     *
     * While the master flag is OFF (default), prepare() returns disabled and the scorer
     * short-circuits to MatchCheckResult::disabled() — the score engine is never reached, and the
     * criteria loader is never constructed or called, so this path stays fully inert.
     *
     * @param  BuyerCriteriaPayload|null  $criteria  An explicitly-supplied scorable payload. When
     *                                              null (the default) and the preparation resolved
     *                                              a preferred criteria record, evaluate() loads the
     *                                              payload itself via MatchCheckCriteriaLoader
     *                                              (git-C8). A non-null value is used verbatim and
     *                                              the loader is skipped.
     */
    public function evaluate(
        BridgeProperty $listing,
        User $user,
        ?BuyerCriteriaPayload $criteria = null,
    ): MatchCheckResult {
        $preparation = $this->prepare($listing, $user);

        // git-C8 — close the CRITERIA_NOT_LOADED seam. Auto-load a payload ONLY when the caller
        // supplied none AND the preparation actually resolved a preferred criteria record. Any
        // explicit $criteria is honored as-is (override preserved). Disabled / blocked / READY-
        // without-record preparations all carry hasPreferredCriteria() === false, so the loader is
        // never constructed or called — the inert guarantee holds while the flag is OFF.
        if ($criteria === null && $preparation->hasPreferredCriteria()) {
            $criteria = $this->resolveCriteriaLoader()->load($preparation, $user);
        }

        // Default the scorer so direct (non-container) construction still works. BuyerMatchScorer
        // has no dependencies and MatchCheckScorer::score() only reaches it in the SCORED state.
        $scorer = $this->scorer ?? new MatchCheckScorer(new BuyerMatchScorer());

        return $scorer->score($preparation, $listing, $criteria);
    }

    /**
     * The injected criteria loader, or a lazily-built default. Mirrors the scorer-default idiom
     * (inline new, no container): all five dependencies are no-arg constructible, and the already-
     * injected CriteriaListingResolver is reused so access scoping stays consistent. Built only
     * when an auto-load is actually needed, so prepare()-only / flag-OFF paths never touch it.
     */
    private function resolveCriteriaLoader(): MatchCheckCriteriaLoader
    {
        return $this->criteriaLoader ?? new MatchCheckCriteriaLoader(
            new BuyerCriteriaLoader(),
            new TenantCriteriaLoader(),
            new BuyerOfferListingCriteriaLoader(),
            new TenantOfferListingCriteriaLoader(),
            $this->criteriaResolver,
        );
    }
}
