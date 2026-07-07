<?php

namespace App\Services\Stellar\MatchCheck;

use App\Jobs\ComputeLocationDna;
use App\Models\BridgeProperty;
use App\Models\User;
use App\Services\Bridge\BridgeListingLookupService;
use App\Services\Property\PropertyCandidate;
use App\Services\Stellar\BuyerCriteriaLoader;
use App\Services\Stellar\BuyerOfferListingCriteriaLoader;
use App\Services\Stellar\CriteriaListingResolver;
use App\Services\Stellar\Matching\BuyerMatchResultBuilder;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\TenantCriteriaLoader;
use App\Services\Stellar\TenantOfferListingCriteriaLoader;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

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
 * git-C13a grows the class into the Plan-C9 composition root. The analyzeBy*() entry points add an
 * MLS#/ListingKey/address lookup FRONT-END on top of evaluate(): they resolve a consumer identifier
 * to a BridgeProperty via BridgeListingLookupService (with the seam's own DNA auto-dispatch
 * suppressed), run evaluate(), route any Match Check-initiated Location DNA enrichment through the
 * git-C12 LocationDnaEnrichmentGuard, and wrap it all into a single MatchCheckAnalysis.
 *
 * git-C13b fills the analysis's rich MatchReport slot for the SCORED case via a dedicated report
 * step (buildReport()). The lean MatchCheckScorer discards the rich BuyerMatchResult, so the report
 * step RE-RUNS the pure BuyerMatchScorer::score() (no I/O), decorates it with the git-C10
 * buildDetailed() F3 blocks, and projects it through MatchReportFactory. MatchCheckScorer and
 * MatchCheckResult stay lean and unchanged; every non-SCORED status keeps report = null.
 *
 * INERT BY DESIGN. prepare()/evaluate() perform only reads. The analyzeBy*() entries DO perform I/O
 * (lookup, enrichment dispatch) — so their inertness is STRUCTURAL, not purity-based: every entry
 * short-circuits to MatchCheckAnalysis::disabled() on !isEnabled() BEFORE any lookup, DB read, or
 * dispatch. With mls_match_check.enabled at its default OFF the whole class is fully inert, and it is
 * still wired to no route, controller, or UI (git-C14).
 *
 * Ordering rationale: visibility is checked BEFORE intent/criteria so a listing that would
 * never be shown to a consumer never triggers criteria resolution (a DB read) on its behalf.
 */
class MatchCheckOrchestrator
{
    /** Canonical source/listing-type tag threaded to the lookup, guard, throttle store, and job. */
    private const SOURCE = 'bridge';

    public function __construct(
        private readonly ListingVisibilityGate $visibilityGate,
        private readonly CriteriaIntentDetector $intentDetector,
        private readonly CriteriaListingResolver $criteriaResolver,
        private readonly ?MatchCheckScorer $scorer = null,
        private readonly ?MatchCheckCriteriaLoader $criteriaLoader = null,
        // git-C13a collaborators. Nullable so existing (C5/C6/C8) construction still works; when the
        // container wires the orchestrator all three are injected. They are resolved lazily only on
        // the analyzeBy*() path AFTER the flag gate, so a flag-OFF call never touches them.
        private readonly ?BridgeListingLookupService $lookup = null,
        private readonly ?LocationDnaEnrichmentGuard $enrichmentGuard = null,
        private readonly ?EnrichmentThrottleStore $throttleStore = null,
        // git-C13b collaborators for the SCORED-only report step. Nullable + lazily defaulted like the
        // above; both are pure/no-arg constructible, so a flag-OFF or non-SCORED path never builds them.
        private readonly ?BuyerMatchResultBuilder $reportBuilder = null,
        private readonly ?MatchReportFactory $reportFactory = null,
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
        $criteria    = $this->resolveScorableCriteria($preparation, $user, $criteria);

        return $this->runScorer($preparation, $listing, $criteria);
    }

    /**
     * Resolve the scorable payload for a preparation (git-C8's auto-load seam), extracted so both
     * evaluate() and the analyzeBy*() path resolve it once. An explicit payload is honored verbatim
     * (loader skipped). Otherwise the payload is auto-loaded ONLY when the preparation resolved a
     * preferred criteria record; disabled / blocked / READY-without-record all carry
     * hasPreferredCriteria() === false, so the loader is never constructed or called — the inert
     * guarantee holds while the flag is OFF.
     */
    private function resolveScorableCriteria(
        MatchCheckPreparation $preparation,
        User $user,
        ?BuyerCriteriaPayload $explicit,
    ): ?BuyerCriteriaPayload {
        if ($explicit !== null) {
            return $explicit;
        }

        if ($preparation->hasPreferredCriteria()) {
            return $this->resolveCriteriaLoader()->load($preparation, $user);
        }

        return null;
    }

    /**
     * Run the lean scoring layer. Defaults the scorer so direct (non-container) construction still
     * works — BuyerMatchScorer has no dependencies and MatchCheckScorer::score() only reaches it in
     * the SCORED state.
     */
    private function runScorer(
        MatchCheckPreparation $preparation,
        BridgeProperty $listing,
        ?BuyerCriteriaPayload $criteria,
    ): MatchCheckResult {
        $scorer = $this->scorer ?? new MatchCheckScorer(new BuyerMatchScorer());

        return $scorer->score($preparation, $listing, $criteria);
    }

    // =========================================================================
    // git-C13a — MLS#/ListingKey/address lookup front-end + end-to-end composition.
    //
    // Each entry short-circuits on !isEnabled() BEFORE any lookup, so with the master flag OFF
    // (its default) no Bridge call, DB read, or enrichment dispatch occurs. The lookup is called
    // with dispatchDna:false so the seam's own auto-dispatch is suppressed and enrichment is routed
    // exclusively through the git-C12 guard (see routeEnrichment()).
    // =========================================================================

    /**
     * Analyze the listing identified by a human-facing MLS Number for this user.
     */
    public function analyzeByMlsNumber(string $mlsNumber, User $user): MatchCheckAnalysis
    {
        if (! $this->isEnabled()) {
            return MatchCheckAnalysis::disabled();
        }

        $candidate = $this->resolveLookup()->findByMlsNumber($mlsNumber, dispatchDna: false);

        return $this->analyzeCandidate($candidate, $user);
    }

    /**
     * Analyze the listing identified by a globally-unique RESO ListingKey for this user.
     */
    public function analyzeByListingKey(string $listingKey, User $user): MatchCheckAnalysis
    {
        if (! $this->isEnabled()) {
            return MatchCheckAnalysis::disabled();
        }

        $candidate = $this->resolveLookup()->findByListingKey($listingKey, dispatchDna: false);

        return $this->analyzeCandidate($candidate, $user);
    }

    /**
     * Analyze by address parts. Address search may return 0/1/N candidates (F1):
     *   0 → NOT_FOUND; 1 → analyze it; N → AMBIGUOUS carrying the candidate list for the caller
     * (git-C14 UI) to disambiguate. No auto-pick of a possibly-wrong unit.
     *
     * @param  array<string,string>  $addressParts
     */
    public function analyzeByAddress(array $addressParts, User $user): MatchCheckAnalysis
    {
        if (! $this->isEnabled()) {
            return MatchCheckAnalysis::disabled();
        }

        /** @var Collection<int,PropertyCandidate> $candidates */
        $candidates = $this->resolveLookup()->searchByAddress($addressParts, dispatchDna: false);

        if ($candidates->isEmpty()) {
            return MatchCheckAnalysis::notFound();
        }

        if ($candidates->count() > 1) {
            return MatchCheckAnalysis::ambiguous($candidates);
        }

        return $this->analyzeCandidate($candidates->first(), $user);
    }

    /**
     * Resolve a single looked-up candidate to its BridgeProperty, evaluate it, route enrichment,
     * produce the rich MatchReport for SCORED, and wrap it all. A null candidate (or one whose source
     * record no longer resolves) → NOT_FOUND.
     *
     * Preparation and the scorable payload are resolved ONCE here (not via evaluate(), which would
     * re-run prepare() and the criteria load) and reused for the report step (git-C13b, decision B) —
     * so the SCORED path performs no duplicate criteria DB read.
     */
    private function analyzeCandidate(?PropertyCandidate $candidate, User $user): MatchCheckAnalysis
    {
        if ($candidate === null || $candidate->sourceRecordId === null) {
            return MatchCheckAnalysis::notFound();
        }

        $listing = BridgeProperty::find($candidate->sourceRecordId);
        if ($listing === null) {
            return MatchCheckAnalysis::notFound();
        }

        $preparation = $this->prepare($listing, $user);
        $criteria    = $this->resolveScorableCriteria($preparation, $user, null);
        $result      = $this->runScorer($preparation, $listing, $criteria);

        // Rich report ONLY for SCORED (which guarantees a payload); every other status → null.
        $report = $result->isScored()
            ? $this->buildReport($preparation, $listing, $criteria)
            : null;

        return MatchCheckAnalysis::fromResult(
            $result,
            $this->routeEnrichment($listing, $user, $result),
            $report,
        );
    }

    /**
     * Report step (git-C13b, decision A). Re-runs the PURE BuyerMatchScorer::score() to recover the
     * rich BuyerMatchResult the lean MatchCheckScorer discards (a side-effect-free comparison — no DB,
     * no API, no lazy import), decorates it with the git-C10 buildDetailed() F3 blocks, and projects
     * it into a MatchReport via MatchReportFactory with the criteria identity, source, and an INJECTED
     * ISO-8601 generatedAt (never now() inside the factory/DTO).
     *
     * Reached only for a SCORED result, which guarantees a non-null payload and a resolved preferred
     * criteria record; the null-guards are defensive belt-and-braces.
     */
    private function buildReport(
        MatchCheckPreparation $preparation,
        BridgeProperty $listing,
        ?BuyerCriteriaPayload $criteria,
    ): ?MatchReport {
        if ($criteria === null || ! $preparation->hasPreferredCriteria()) {
            return null;
        }

        $engineResult = (new BuyerMatchScorer())->score($listing, $criteria);
        $detailed     = $this->resolveReportBuilder()->buildDetailed($engineResult, $criteria);

        return $this->resolveReportFactory()->fromDetailed(
            $detailed,
            (int) $preparation->preferredCriteria['id'],
            (string) $preparation->preferredCriteria['type'],
            self::SOURCE,
            CarbonImmutable::now()->toIso8601String(),
        );
    }

    /**
     * Route Match Check-initiated Location DNA enrichment through the git-C12 guard (F6) and return
     * the machine reason for audit/telemetry.
     *
     * Enrichment is tied to a REAL match-check evaluation — i.e. a listing where criteria context
     * actually exists to compare against: SCORED (checked and scored) or CRITERIA_NOT_LOADED (a
     * criteria record exists but its payload could not be loaded this pass). Every other state is a
     * non-attempt and enrichment stays DEFERRED: DISABLED/BLOCKED (never evaluated), and NO_CRITERIA
     * (the listing is visible but there is no criteria to check against yet, so nothing to enrich
     * for). NOT_FOUND/AMBIGUOUS never reach this method (no single listing was resolved). For all
     * non-attempt cases we report FEATURE_DISABLED (no dispatch happened). Otherwise: build the
     * throttle snapshot, ask the guard, and ONLY on an allow dispatch ComputeLocationDna once and
     * record the attempt.
     */
    private function routeEnrichment(BridgeProperty $listing, User $user, MatchCheckResult $result): string
    {
        if (! $result->isScored() && ! $result->isCriteriaNotLoaded()) {
            return EnrichmentGuardDecision::REASON_FEATURE_DISABLED;
        }

        $listingId = (int) $listing->id;
        $userId    = (int) $user->id;

        $store    = $this->resolveThrottleStore();
        $snapshot = $store->snapshot(self::SOURCE, $listingId, $userId);

        $decision = $this->resolveEnrichmentGuard()->decide(
            self::SOURCE,
            $listingId,
            $userId,
            $snapshot,
            CarbonImmutable::now(),
        );

        if ($decision->allowed) {
            ComputeLocationDna::dispatch(self::SOURCE, $listingId);
            $store->recordAttempt(self::SOURCE, $listingId, $userId);
        }

        return $decision->reason;
    }

    /** The injected lookup service, or the container's binding. Only reached after the flag gate. */
    private function resolveLookup(): BridgeListingLookupService
    {
        return $this->lookup ?? app(BridgeListingLookupService::class);
    }

    /** The injected enrichment guard, or a lazily-built default (the guard is stateless/pure). */
    private function resolveEnrichmentGuard(): LocationDnaEnrichmentGuard
    {
        return $this->enrichmentGuard ?? new LocationDnaEnrichmentGuard();
    }

    /** The injected throttle store, or a lazily-built default (no-arg constructible). */
    private function resolveThrottleStore(): EnrichmentThrottleStore
    {
        return $this->throttleStore ?? new EnrichmentThrottleStore();
    }

    /** The injected report builder, or a lazily-built default (pure, no-arg constructible). */
    private function resolveReportBuilder(): BuyerMatchResultBuilder
    {
        return $this->reportBuilder ?? new BuyerMatchResultBuilder();
    }

    /** The injected report factory, or a lazily-built default (pure, no-arg constructible). */
    private function resolveReportFactory(): MatchReportFactory
    {
        return $this->reportFactory ?? new MatchReportFactory();
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
