<?php

namespace App\Services\Dna\Relevance;

/**
 * MatchTierClassifier — §F6 match-tier banding (roadmap line 141).
 *
 * Classifies one listing ↔ demand pair (an AggregateRelevanceResult) into
 * Exact / Strong / Similar / Opportunity by the two roadmap-specified inputs:
 * OVERALL RELEVANCE and WHICH CATEGORIES CLEARED. The tier is the lower of two
 * bands, so BOTH a high overall relevance AND broad category clearance are
 * required to reach the top tiers:
 *
 *   relevance band ── value ≥ 90 → 4, ≥ 70 → 3, ≥ 45 → 2, else 1
 *   clearance band ── all cleared → 4, covered-with-shortfalls → 3,
 *                     partial coverage → 2, nothing cleared → 1
 *   tier rank      ── min(relevance band, clearance band)
 *
 * Because a coverage gap can never "clear", a thinly-covered pair cannot reach
 * Exact/Strong without a separate coverage gate — determinacy is honoured for
 * free. An undetermined aggregate yields a null tier (not a band).
 *
 * Pure, deterministic, side-effect-free; reads only the aggregate handed to it
 * and persists nothing.
 *
 * ── FUTURE EXTENSION POINT: Must-Have hard gate (roadmap §F6/§F9) ────────────
 * Preference strength (Must Have / Strong / Nice To Have) is NOT yet modelled
 * in dna_scores, so NO hard gate is evaluated today. The banding above is the
 * complete classification given the data that currently exists. When demand
 * scores carry a strength signal, gate behaviour hooks in at ONE place only —
 * gateCeilingRank() — which today returns null (no ceiling). A future override
 * will cap the tier (e.g. a Must-Have category that is a gap or shortfall
 * ceilings the match at Opportunity) WITHOUT changing the banding logic. Do not
 * approximate or infer Must-Have behaviour from the current 0–100 weights.
 */
class MatchTierClassifier
{
    /** A demanded category "clears" when its relevance meets this bar. */
    public const CLEARANCE_BAR = 60;

    /** Overall-relevance band thresholds. */
    public const EXACT_MIN   = 90;
    public const STRONG_MIN  = 70;
    public const SIMILAR_MIN = 45;

    public function classify(AggregateRelevanceResult $aggregate): MatchTierResult
    {
        [$cleared, $shortfall, $gaps] = $this->partition($aggregate);

        if ($aggregate->isUndetermined()) {
            $reason = $aggregate->demandedCount === 0
                ? 'no demanded dimensions overlap.'
                : 'no demanded dimension had property data.';

            return MatchTierResult::undetermined(
                $aggregate->coverage,
                "Match tier undetermined: {$reason}",
                $gaps,
            );
        }

        $rank = min(
            $this->relevanceBand((int) $aggregate->value),
            $this->clearanceBand(count($cleared), count($shortfall), count($gaps)),
        );

        // FUTURE seam only — null today (see class docblock). Never redesign the
        // banding above to add gating; extend gateCeilingRank() instead.
        $ceiling = $this->gateCeilingRank($aggregate);
        if ($ceiling !== null) {
            $rank = min($rank, $ceiling);
        }

        $tier = MatchTier::fromRank($rank);

        return new MatchTierResult(
            $tier,
            (int) $aggregate->value,
            $aggregate->confidence,
            $aggregate->coverage,
            $cleared,
            $shortfall,
            $gaps,
            $this->explain($tier, $aggregate, $cleared, $shortfall, $gaps),
        );
    }

    /**
     * Partition the demanded dimensions into cleared / shortfall / gap key lists.
     *
     * @return array{0:string[],1:string[],2:string[]}
     */
    private function partition(AggregateRelevanceResult $aggregate): array
    {
        $cleared = $shortfall = $gaps = [];

        foreach ($aggregate->contributions as $c) {
            if ($c->isUndetermined()) {
                $gaps[] = $c->scoreKey;
            } elseif ((int) $c->value >= self::CLEARANCE_BAR) {
                $cleared[] = $c->scoreKey;
            } else {
                $shortfall[] = $c->scoreKey;
            }
        }

        sort($cleared);
        sort($shortfall);
        sort($gaps);

        return [$cleared, $shortfall, $gaps];
    }

    private function relevanceBand(int $value): int
    {
        return match (true) {
            $value >= self::EXACT_MIN   => 4,
            $value >= self::STRONG_MIN  => 3,
            $value >= self::SIMILAR_MIN => 2,
            default                     => 1,
        };
    }

    private function clearanceBand(int $cleared, int $shortfall, int $gaps): int
    {
        if ($cleared === 0) {
            return 1; // nothing the searcher wanted was satisfied
        }
        if ($gaps === 0 && $shortfall === 0) {
            return 4; // every demanded category cleared
        }
        if ($gaps === 0) {
            return 3; // fully covered, some categories fall short
        }

        return 2; // some categories cleared, but coverage gaps remain
    }

    /**
     * FUTURE EXTENSION POINT — Must-Have hard-gate ceiling. Returns null today
     * because preference strength is not modelled in dna_scores yet (see class
     * docblock). Override to return a MatchTier rank ceiling once strength data
     * exists; the classify() pipeline already applies it non-destructively.
     */
    protected function gateCeilingRank(AggregateRelevanceResult $aggregate): ?int
    {
        return null;
    }

    /**
     * @param string[] $cleared
     * @param string[] $shortfall
     * @param string[] $gaps
     */
    private function explain(MatchTier $tier, AggregateRelevanceResult $aggregate, array $cleared, array $shortfall, array $gaps): string
    {
        $parts = [
            "{$tier->label()} (overall relevance {$aggregate->value}, confidence {$aggregate->confidence}, coverage {$aggregate->coverage}%)",
        ];

        if ($cleared !== []) {
            $parts[] = 'cleared: ' . implode(', ', $cleared);
        }
        if ($shortfall !== []) {
            $parts[] = 'falls short: ' . implode(', ', $shortfall);
        }
        if ($gaps !== []) {
            $parts[] = 'no property data: ' . implode(', ', $gaps);
        }

        return implode('; ', $parts) . '.';
    }
}
