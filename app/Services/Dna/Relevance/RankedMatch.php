<?php

namespace App\Services\Dna\Relevance;

/**
 * RankedMatch — one counterpart's tiered match result within a RankedMatchSet
 * (§F6 batch matching). Pairs the counterpart's identifier with its
 * MatchTierResult. Only determined matches (a non-null tier) are ranked, so a
 * RankedMatch always carries a real tier and overall value.
 *
 * A pure value object; persists nothing.
 */
final class RankedMatch
{
    public function __construct(
        public readonly int|string $counterpartId,
        public readonly MatchTierResult $result,
    ) {
    }

    public function tier(): ?MatchTier
    {
        return $this->result->tier;
    }

    public function value(): ?int
    {
        return $this->result->value;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return ['counterpart_id' => $this->counterpartId] + $this->result->toArray();
    }
}
