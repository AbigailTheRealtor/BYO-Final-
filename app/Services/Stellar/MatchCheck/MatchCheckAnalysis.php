<?php

namespace App\Services\Stellar\MatchCheck;

use App\Services\Property\PropertyCandidate;
use Illuminate\Support\Collection;

/**
 * Composed, serializable outcome of a Match Check lookup + evaluation (Phase 4 · git-C13a / Plan-C9).
 *
 * MatchCheckOrchestrator::analyzeBy*() resolves a consumer identifier (MLS#, ListingKey, or address)
 * to a listing and runs the existing evaluate() chain, then wraps the lookup outcome, the lean score
 * VO, the enrichment decision, and (later) the rich report into this one value. It adds two statuses
 * on top of the five MatchCheckResult statuses, for the two outcomes that occur BEFORE any scoring:
 *
 *   NOT_FOUND  — the identifier resolved to no listing (0 candidates / unresolvable record).
 *   AMBIGUOUS  — an address matched multiple candidates (units at one address); $candidates carries
 *                the factual candidate list for the caller (git-C14 UI) to disambiguate. No auto-pick.
 *
 * The remaining statuses are carried straight through from the wrapped MatchCheckResult:
 *   DISABLED / BLOCKED / NO_CRITERIA / CRITERIA_NOT_LOADED / SCORED.
 *
 * DATA-ONLY BY DESIGN. This object holds serializable scalars/arrays only — no BridgeProperty,
 * PropertyCandidate object, or BuyerMatchResult reference. $candidates is built with
 * PropertyCandidate::toArray() (which EXCLUDES the restricted $raw source record by default), so the
 * analysis never carries raw_json / PublicRemarks / PII / lockbox data (F7).
 *
 * $report is the rich MatchReport slot. It is ALWAYS null in git-C13a; git-C13b adds the
 * MatchReportFactory that populates it for the SCORED case. The slot is declared now so the DTO shape
 * is stable across the a→b split.
 */
final class MatchCheckAnalysis
{
    public const STATUS_DISABLED            = MatchCheckResult::STATUS_DISABLED;
    public const STATUS_BLOCKED             = MatchCheckResult::STATUS_BLOCKED;
    public const STATUS_NO_CRITERIA         = MatchCheckResult::STATUS_NO_CRITERIA;
    public const STATUS_CRITERIA_NOT_LOADED = MatchCheckResult::STATUS_CRITERIA_NOT_LOADED;
    public const STATUS_SCORED              = MatchCheckResult::STATUS_SCORED;
    public const STATUS_NOT_FOUND           = 'not_found';
    public const STATUS_AMBIGUOUS           = 'ambiguous';

    /**
     * @param  array<int,array<string,mixed>>  $candidates  PropertyCandidate::toArray() facts (AMBIGUOUS only).
     */
    private function __construct(
        public readonly string $status,
        public readonly ?MatchCheckResult $result,
        public readonly ?MatchReport $report,
        public readonly array $candidates,
        public readonly string $enrichmentReason,
    ) {
    }

    /**
     * Master flag OFF — no lookup, no scoring, no enrichment. Mirrors the other layers' disabled state.
     */
    public static function disabled(): self
    {
        return new self(
            status: self::STATUS_DISABLED,
            result: null,
            report: null,
            candidates: [],
            enrichmentReason: EnrichmentGuardDecision::REASON_FEATURE_DISABLED,
        );
    }

    /**
     * The identifier resolved to no listing. No result, no candidates, no enrichment attempted.
     */
    public static function notFound(): self
    {
        return new self(
            status: self::STATUS_NOT_FOUND,
            result: null,
            report: null,
            candidates: [],
            enrichmentReason: EnrichmentGuardDecision::REASON_FEATURE_DISABLED,
        );
    }

    /**
     * An address matched multiple candidates. The factual candidate list is carried for the caller to
     * disambiguate; $raw is excluded (PropertyCandidate::toArray() default). No listing is scored.
     *
     * @param  Collection<int,PropertyCandidate>  $candidates
     */
    public static function ambiguous(Collection $candidates): self
    {
        return new self(
            status: self::STATUS_AMBIGUOUS,
            result: null,
            report: null,
            candidates: $candidates->map(fn (PropertyCandidate $c) => $c->toArray())->values()->all(),
            enrichmentReason: EnrichmentGuardDecision::REASON_FEATURE_DISABLED,
        );
    }

    /**
     * Wrap a computed MatchCheckResult (the lean score VO) with the enrichment decision reason and,
     * once git-C13b lands, the rich MatchReport. The analysis status is the result's own status.
     *
     * @param  string  $enrichmentReason  An EnrichmentGuardDecision::REASON_* value (audit/telemetry).
     */
    public static function fromResult(
        MatchCheckResult $result,
        string $enrichmentReason,
        ?MatchReport $report = null,
    ): self {
        return new self(
            status: $result->status,
            result: $result,
            report: $report,
            candidates: [],
            enrichmentReason: $enrichmentReason,
        );
    }

    public function isDisabled(): bool
    {
        return $this->status === self::STATUS_DISABLED;
    }

    public function isNotFound(): bool
    {
        return $this->status === self::STATUS_NOT_FOUND;
    }

    public function isAmbiguous(): bool
    {
        return $this->status === self::STATUS_AMBIGUOUS;
    }

    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOCKED;
    }

    public function isNoCriteria(): bool
    {
        return $this->status === self::STATUS_NO_CRITERIA;
    }

    public function isCriteriaNotLoaded(): bool
    {
        return $this->status === self::STATUS_CRITERIA_NOT_LOADED;
    }

    public function isScored(): bool
    {
        return $this->status === self::STATUS_SCORED;
    }

    public function hasReport(): bool
    {
        return $this->report !== null;
    }

    /**
     * Fully serializable snake_case representation. Contains only scalars/arrays — never a
     * BridgeProperty, PropertyCandidate object, or $raw source record (F7).
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'status'            => $this->status,
            'enrichment_reason' => $this->enrichmentReason,
            'result'            => $this->result === null ? null : [
                'status'            => $this->result->status,
                'scorable'          => $this->result->scorable,
                'intent'            => $this->result->intent,
                'visibility_reason' => $this->result->visibilityReason,
                'listing_key'       => $this->result->listingKey,
                'total_score'       => $this->result->totalScore,
                'category_scores'   => $this->result->categoryScores,
            ],
            'report'     => $this->report?->toArray(),
            'candidates' => $this->candidates,
        ];
    }
}
