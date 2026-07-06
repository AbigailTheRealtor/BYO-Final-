<?php

namespace App\Services\Dna\Relevance;

/**
 * RankedMatch — one counterpart's tiered match result within a RankedMatchSet
 * (§F6 batch matching). Pairs the counterpart's identifier with its
 * MatchTierResult. Only determined matches (a non-null tier) are ranked, so a
 * RankedMatch always carries a real tier and overall value.
 *
 * `counterpartType` (Matching V2 C6, additive) optionally records the
 * counterpart's listing_type, so a ranked result over a mixed-type candidate pool
 * (e.g. seller_agent + landlord_agent, whose ids can collide) stays unambiguous.
 * It defaults to null — existing §F6 callers that pass only (id, result) are
 * unaffected, and toArray() omits the key when null to preserve the legacy shape.
 *
 * A pure value object; persists nothing.
 */
final class RankedMatch
{
    public function __construct(
        public readonly int|string $counterpartId,
        public readonly MatchTierResult $result,
        public readonly ?string $counterpartType = null,
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

    public function counterpartType(): ?string
    {
        return $this->counterpartType;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $head = ['counterpart_id' => $this->counterpartId];
        if ($this->counterpartType !== null) {
            $head['counterpart_type'] = $this->counterpartType;
        }

        return $head + $this->result->toArray();
    }
}
