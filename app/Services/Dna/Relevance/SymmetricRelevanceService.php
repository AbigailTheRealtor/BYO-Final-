<?php

namespace App\Services\Dna\Relevance;

use App\Models\DnaScore;
use InvalidArgumentException;

/**
 * SymmetricRelevanceService — §F6 Universal Relevance Scoring, smallest
 * read-side primitive.
 *
 * Combines ONE property-side DnaScore with ONE demand-side DnaScore for the
 * SAME score_key into a single relevance contribution on the shared 0–100
 * axis. It is a pure, deterministic, side-effect-free transformer (like
 * AgentBidMapperService): it reads only the two rows handed to it and persists
 * nothing.
 *
 * SEMANTICS — "does the property satisfy the searcher's need on this dimension?"
 *   - demand.value  = how much the searcher wants this dimension (the WEIGHT).
 *   - property.value = how much the listing provides it (the PROVISION).
 *   - Relevance penalises only the UNMET portion of a demand:
 *
 *         relevance = 100 − demand.value × (100 − property.value) / 100
 *
 *   so a low demand weight is NON-PENALISING (the subtracted term shrinks with
 *   demand), a strong demand met by a strong property scores high, and a strong
 *   demand met by a weak property scores low.
 *
 * CONFIDENCE (§F4, non-inflating): a match can be no more certain than its
 * least-certain input, so confidence = min(property.confidence, demand.confidence).
 * When either side WITHHELD its value (null), the contribution is undetermined
 * and confidence is forced to 0.
 *
 * FAIR HOUSING (§F3): this primitive only reads dna_scores, which already
 * excluded protected-class proxies when they were produced; no protected data
 * can re-enter here.
 *
 * OUT OF SCOPE (later §F6 phases): overall cross-key aggregation, match tiers
 * (Exact/Strong/Similar/Opportunity), two-way orchestration, runtime wiring,
 * and any consumer-facing exposure.
 */
class SymmetricRelevanceService
{
    /** Human labels for the score_keys this primitive is exercised against. */
    private const LABELS = [
        'pet_friendliness'     => 'Pet-Friendliness',
        'lock_and_leave'       => 'Lock-and-Leave',
        'waterfront_lifestyle' => 'Waterfront-Lifestyle',
    ];

    /** At/under this demand weight the dimension is treated as a low priority. */
    private const LOW_DEMAND_WEIGHT = 20;

    /**
     * Combine a property-side and demand-side score for the same score_key.
     *
     * @throws InvalidArgumentException if the sides are wrong or the keys differ
     */
    public function combine(DnaScore $property, DnaScore $demand): RelevanceResult
    {
        if ($property->side !== 'property') {
            throw new InvalidArgumentException("First argument must be a property-side score, got [{$property->side}].");
        }
        if ($demand->side !== 'demand') {
            throw new InvalidArgumentException("Second argument must be a demand-side score, got [{$demand->side}].");
        }
        if ($property->score_key !== $demand->score_key) {
            throw new InvalidArgumentException("Cannot combine mismatched score_keys [{$property->score_key}] and [{$demand->score_key}].");
        }

        $scoreKey    = $property->score_key;
        $label       = self::LABELS[$scoreKey] ?? $scoreKey;
        $propValue   = $property->value;   // ?int
        $demandValue = $demand->value;     // ?int
        $propConf    = (int) $property->confidence;
        $demandConf  = (int) $demand->confidence;

        // Either side withheld a value → contribution undetermined, confidence 0.
        if ($propValue === null || $demandValue === null) {
            $missing = match (true) {
                $propValue === null && $demandValue === null => 'both sides',
                $propValue === null                          => 'the property side',
                default                                      => 'the demand side',
            };

            return new RelevanceResult(
                $scoreKey,
                null,
                0,
                $propValue,
                $demandValue,
                $propConf,
                $demandConf,
                "{$label} relevance undetermined: {$missing} withheld a value; confidence 0.",
            );
        }

        $p = $this->clamp($propValue);
        $d = $this->clamp($demandValue);

        // relevance = 100 − demand × unmet-share; low demand weight stays near 100.
        $value = (int) floor(100 - ($d * (100 - $p) / 100));
        $value = $this->clamp($value);

        // §F4: no more certain than the weakest side.
        $confidence = min($propConf, $demandConf);

        return new RelevanceResult(
            $scoreKey,
            $value,
            $confidence,
            $propValue,
            $demandValue,
            $propConf,
            $demandConf,
            $this->explain($label, $value, $d, $p),
        );
    }

    private function explain(string $label, int $value, int $demand, int $property): string
    {
        if ($demand <= self::LOW_DEMAND_WEIGHT) {
            $qualifier = 'low demand priority, so this dimension is not limiting';
        } elseif ($value >= 70) {
            $qualifier = 'property meets the demand on this dimension';
        } elseif ($value >= 40) {
            $qualifier = 'property partially meets the demand';
        } else {
            $qualifier = 'property does not meet a strong demand on this dimension';
        }

        return "{$label} relevance {$value}: demand weight {$demand}/100 vs property value {$property}/100 — {$qualifier}.";
    }

    private function clamp(int $v): int
    {
        return max(0, min(100, $v));
    }
}
