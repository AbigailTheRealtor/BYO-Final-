<?php

namespace App\Services\Dna\Relevance;

/**
 * RelevanceResult — the outcome of combining one property-side and one
 * demand-side DnaScore for the same score_key into a single relevance
 * contribution on the shared 0–100 axis (§F6 Universal Relevance Scoring,
 * smallest read-side primitive).
 *
 * `value` is the relevance contribution (0 = the property fails to meet a
 * strong demand; 100 = the demand is fully met or is a non-factor). It is
 * `null` when the contribution is undetermined because a side withheld its
 * value; in that case `confidence` is forced to 0 (§F4 non-inflating).
 *
 * The per-side inputs are retained for §F5 explainability and for later
 * aggregation — this primitive never persists anything.
 */
final class RelevanceResult
{
    public function __construct(
        public readonly string $scoreKey,
        public readonly ?int $value,
        public readonly int $confidence,
        public readonly ?int $propertyValue,
        public readonly ?int $demandValue,
        public readonly int $propertyConfidence,
        public readonly int $demandConfidence,
        public readonly string $explanation,
    ) {
    }

    /** True when a relevance contribution could not be determined. */
    public function isUndetermined(): bool
    {
        return $this->value === null;
    }

    /**
     * @return array{score_key:string,value:?int,confidence:int,property_value:?int,demand_value:?int,property_confidence:int,demand_confidence:int,explanation:string}
     */
    public function toArray(): array
    {
        return [
            'score_key'           => $this->scoreKey,
            'value'               => $this->value,
            'confidence'          => $this->confidence,
            'property_value'      => $this->propertyValue,
            'demand_value'        => $this->demandValue,
            'property_confidence' => $this->propertyConfidence,
            'demand_confidence'   => $this->demandConfidence,
            'explanation'         => $this->explanation,
        ];
    }
}
