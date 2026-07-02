<?php

namespace App\Services\Dna\Relevance;

/**
 * RankedMatchSet — the outcome of matching one subject against a set of
 * counterparts (§F6 batch matching): the determined matches ordered best-first,
 * the count of undetermined pairs (excluded from the ranking), and the per-tier
 * counts.
 *
 * `tierCounts()` is the privacy-safe aggregate that a future Marketplace
 * Intelligence layer (reverse tiered demand counts) will build on — it exposes
 * the depth and quality of matches without any individual identity. This object
 * persists nothing and exposes nothing to consumers by itself.
 */
final class RankedMatchSet
{
    /**
     * @param RankedMatch[] $matches ordered best-first (determined only)
     */
    public function __construct(
        public readonly array $matches,
        public readonly int $undeterminedCount,
    ) {
    }

    public function determinedCount(): int
    {
        return count($this->matches);
    }

    /**
     * Privacy-safe per-tier counts; all four tiers always present (zero-filled).
     *
     * @return array<string,int>
     */
    public function tierCounts(): array
    {
        $counts = [
            MatchTier::Exact->value       => 0,
            MatchTier::Strong->value      => 0,
            MatchTier::Similar->value     => 0,
            MatchTier::Opportunity->value => 0,
        ];

        foreach ($this->matches as $match) {
            $counts[$match->result->tier->value]++;
        }

        return $counts;
    }

    /**
     * The top N matches (already ordered best-first).
     *
     * @return RankedMatch[]
     */
    public function top(int $n): array
    {
        if ($n <= 0) {
            return [];
        }

        return array_slice($this->matches, 0, $n);
    }

    /**
     * @return array{determined_count:int,undetermined_count:int,tier_counts:array<string,int>,matches:array<int,array>}
     */
    public function toArray(): array
    {
        return [
            'determined_count'   => $this->determinedCount(),
            'undetermined_count' => $this->undeterminedCount,
            'tier_counts'        => $this->tierCounts(),
            'matches'            => array_map(static fn (RankedMatch $m): array => $m->toArray(), $this->matches),
        ];
    }
}
