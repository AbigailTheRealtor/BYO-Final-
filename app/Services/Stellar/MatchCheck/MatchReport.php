<?php

namespace App\Services\Stellar\MatchCheck;

/**
 * Rich single-property Match Report (Phase 4 · git-C9 == Plan-C5, F3/F8).
 *
 * The presentation-grade report model for the consumer-facing Match Check feature: the criteria
 * and listing identity, the score and its per-category breakdown, and the F3 explanation blocks
 * (why / why-not / tradeoffs / missing / confidence / recommendations), plus a nullable AI/narrative
 * slot (F8) that a much later, out-of-scope slice may decorate.
 *
 * DISTINCT FROM MatchCheckResult. MatchCheckResult stays the lean scoring VO (status + totalScore +
 * categoryScores). This is the richer report. Like MatchCheckResult, and per F8, it holds ONLY
 * serializable scalars/arrays — no BuyerMatchResult or BridgeProperty reference — so toArray() is a
 * self-contained, JSON-encodable read model.
 *
 * INERT / DATA-ONLY BY DESIGN. This object holds data; it performs no scoring, building, rendering,
 * persistence, or I/O, and it reads no feature flag. The timestamp is INJECTED (never now() inside)
 * so reports are deterministic and testable. git-C9 ships the shape only; the code that builds a
 * MatchReport from a scored result is a later slice.
 */
final class MatchReport
{
    /**
     * @param  int                 $criteriaId       The scored criteria record id.
     * @param  string              $criteriaType     'buyer' | 'tenant' | 'buyer_offer' | 'tenant_offer'.
     * @param  string              $listingKey       Bridge listing key.
     * @param  string              $source           Data source, e.g. 'bridge' (caller-supplied).
     * @param  int                 $totalScore       0–100, already clamped upstream.
     * @param  array<string,int>   $categoryScores   Per-category points breakdown.
     * @param  array               $whyThisMatches   Positive contributors (F3).
     * @param  array               $whyNot           Low/zero-scoring detractors (F3).
     * @param  array               $tradeoffs        Tradeoff notes (F3).
     * @param  array               $missingData      Missing-data notes (F3).
     * @param  array|null          $confidence       Nullable structured confidence block, or null.
     * @param  array               $recommendations  Rule-based v1 suggestions (F3).
     * @param  string              $generatedAt      Injected ISO-8601 timestamp (never now() inside).
     * @param  array|null          $narrative        Nullable AI/narrative slot (F8); default null.
     */
    public function __construct(
        public readonly int $criteriaId,
        public readonly string $criteriaType,
        public readonly string $listingKey,
        public readonly string $source,
        public readonly int $totalScore,
        public readonly array $categoryScores,
        public readonly array $whyThisMatches,
        public readonly array $whyNot,
        public readonly array $tradeoffs,
        public readonly array $missingData,
        public readonly ?array $confidence,
        public readonly array $recommendations,
        public readonly string $generatedAt,
        public readonly ?array $narrative = null,
    ) {
    }

    /**
     * Fully serializable snake_case representation. Contains nothing non-serializable (F8).
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'criteria_id'      => $this->criteriaId,
            'criteria_type'    => $this->criteriaType,
            'listing_key'      => $this->listingKey,
            'source'           => $this->source,
            'total_score'      => $this->totalScore,
            'category_scores'  => $this->categoryScores,
            'why_this_matches' => $this->whyThisMatches,
            'why_not'          => $this->whyNot,
            'tradeoffs'        => $this->tradeoffs,
            'missing_data'     => $this->missingData,
            'confidence'       => $this->confidence,
            'recommendations'  => $this->recommendations,
            'generated_at'     => $this->generatedAt,
            'narrative'        => $this->narrative,
        ];
    }
}
