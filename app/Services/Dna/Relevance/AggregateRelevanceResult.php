<?php

namespace App\Services\Dna\Relevance;

/**
 * AggregateRelevanceResult — the overall relevance of a single listing ↔ demand
 * pair, blended from the per-dimension RelevanceResult contributions (§F6
 * Universal Relevance Scoring; the aggregation kernel that match tiers and
 * two-way orchestration will build on).
 *
 * `value` is the demand-weighted overall relevance (0–100), or `null` when
 * undetermined (no demanded dimensions overlap, or none had property data).
 *
 * `coverage` is the demand-weighted share of demanded dimensions that could be
 * determined — the match-side Data Completeness. `confidence` is the overall
 * Match Confidence: a non-inflating blend of the per-dimension confidences,
 * reduced by coverage gaps (§F4 chain: Data Completeness → DNA Confidence →
 * Match Confidence).
 *
 * `contributions` retains every demanded dimension (determined and gap) for
 * §F5 explainability. This object persists nothing.
 */
final class AggregateRelevanceResult
{
    /**
     * @param RelevanceResult[] $contributions
     */
    public function __construct(
        public readonly ?int $value,
        public readonly int $confidence,
        public readonly int $coverage,
        public readonly int $demandedCount,
        public readonly int $determinedCount,
        public readonly int $undeterminedCount,
        public readonly array $contributions,
        public readonly string $explanation,
    ) {
    }

    /** An undetermined aggregate (no demanded overlap) — value null, confidence 0. */
    public static function undetermined(string $explanation): self
    {
        return new self(null, 0, 0, 0, 0, 0, [], $explanation);
    }

    public function isUndetermined(): bool
    {
        return $this->value === null;
    }

    /**
     * @return array{value:?int,confidence:int,coverage:int,demanded_count:int,determined_count:int,undetermined_count:int,contributions:array<int,array>,explanation:string}
     */
    public function toArray(): array
    {
        return [
            'value'              => $this->value,
            'confidence'         => $this->confidence,
            'coverage'           => $this->coverage,
            'demanded_count'     => $this->demandedCount,
            'determined_count'   => $this->determinedCount,
            'undetermined_count' => $this->undeterminedCount,
            'contributions'      => array_map(static fn (RelevanceResult $c): array => $c->toArray(), $this->contributions),
            'explanation'        => $this->explanation,
        ];
    }
}
