<?php

namespace App\Services\Stellar\MatchCheck;

/**
 * Immutable output of the Match Check scoring layer (Phase 4 · Wave 2 / C6).
 *
 * MatchCheckScorer turns a MatchCheckPreparation (the C5 read-only decision) into
 * one of five terminal outputs. The first four are non-scoring states carried
 * straight through from the preparation; only the last actually reflects a score
 * computed by the existing BuyerMatchScorer engine:
 *
 *   DISABLED            — master flag OFF (the default, inert state). No work done.
 *   BLOCKED             — flag ON but the listing is not IDX-visible. Not scored.
 *   NO_CRITERIA         — flag ON, listing visible, but the user has no preferred
 *                         criteria record → caller shows an empty state, never a score.
 *   CRITERIA_NOT_LOADED — flag ON, listing visible, a preferred criteria record exists,
 *                         but its scorable payload was not supplied to the scorer. This is
 *                         the seam a later wave fills in (criteria → BuyerCriteriaPayload
 *                         loading); until then the feature stays scoreless here.
 *   SCORED              — flag ON, listing visible, criteria payload supplied; $totalScore
 *                         and $categoryScores are populated from BuyerMatchScorer.
 *
 * INERT / OUTPUT-ONLY BY DESIGN. This object holds data; it performs no scoring,
 * rendering, persistence, or I/O. It deliberately does NOT depend on the Matching
 * subsystem's DTOs — the scorer extracts the primitive score fields and passes them
 * in, so this value object stays a self-contained read model.
 *
 * `scorable` is true only for the SCORED state; every other state is a caller signal
 * that no numeric match score is available (and why).
 */
final class MatchCheckResult
{
    public const STATUS_DISABLED            = 'disabled';
    public const STATUS_BLOCKED             = 'blocked';
    public const STATUS_NO_CRITERIA         = 'no_criteria';
    public const STATUS_CRITERIA_NOT_LOADED = 'criteria_not_loaded';
    public const STATUS_SCORED              = 'scored';

    /**
     * @param  array<string, int>  $categoryScores  Per-category points (empty unless SCORED).
     */
    private function __construct(
        public readonly string $status,
        public readonly bool $scorable,
        public readonly ?string $intent,
        public readonly string $visibilityReason,
        public readonly ?string $listingKey,
        public readonly ?int $totalScore,
        public readonly array $categoryScores,
    ) {
    }

    /**
     * Master flag OFF — nothing was computed. Mirrors MatchCheckPreparation::disabled().
     */
    public static function disabled(MatchCheckPreparation $prep): self
    {
        return new self(
            status: self::STATUS_DISABLED,
            scorable: false,
            intent: null,
            visibilityReason: $prep->visibilityReason,
            listingKey: null,
            totalScore: null,
            categoryScores: [],
        );
    }

    /**
     * Flag ON but the listing is not consumer-visible (IDX). Never scored.
     */
    public static function blocked(MatchCheckPreparation $prep): self
    {
        return new self(
            status: self::STATUS_BLOCKED,
            scorable: false,
            intent: null,
            visibilityReason: $prep->visibilityReason,
            listingKey: null,
            totalScore: null,
            categoryScores: [],
        );
    }

    /**
     * Flag ON and listing visible, but the user has no preferred criteria record to
     * score against (empty state). $intent is carried through for the caller's copy.
     */
    public static function noCriteria(MatchCheckPreparation $prep): self
    {
        return new self(
            status: self::STATUS_NO_CRITERIA,
            scorable: false,
            intent: $prep->intent,
            visibilityReason: $prep->visibilityReason,
            listingKey: null,
            totalScore: null,
            categoryScores: [],
        );
    }

    /**
     * Flag ON, listing visible, a preferred criteria record exists — but no scorable
     * payload was supplied. Seam for the later criteria→payload loading wave.
     */
    public static function criteriaNotLoaded(MatchCheckPreparation $prep): self
    {
        return new self(
            status: self::STATUS_CRITERIA_NOT_LOADED,
            scorable: false,
            intent: $prep->intent,
            visibilityReason: $prep->visibilityReason,
            listingKey: null,
            totalScore: null,
            categoryScores: [],
        );
    }

    /**
     * Flag ON, listing visible, criteria payload supplied and scored by BuyerMatchScorer.
     * Score fields are primitives extracted by the scorer (0–100 total, already clamped).
     *
     * @param  array<string, int>  $categoryScores
     */
    public static function scored(
        MatchCheckPreparation $prep,
        string $listingKey,
        int $totalScore,
        array $categoryScores,
    ): self {
        return new self(
            status: self::STATUS_SCORED,
            scorable: true,
            intent: $prep->intent,
            visibilityReason: $prep->visibilityReason,
            listingKey: $listingKey,
            totalScore: $totalScore,
            categoryScores: $categoryScores,
        );
    }

    public function isDisabled(): bool
    {
        return $this->status === self::STATUS_DISABLED;
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
}
