<?php

namespace App\Services\Dna\Relevance;

use App\Models\DnaScore;

/**
 * RelevanceAggregator — §F6 aggregation kernel.
 *
 * Blends the per-dimension relevance contributions of ONE listing ↔ demand pair
 * into a single overall relevance, confidence, and coverage. It pairs the two
 * sides' dna_scores by score_key, reuses SymmetricRelevanceService::combine()
 * as its per-dimension kernel, and applies a DEMAND-WEIGHTED blend so that
 * dimensions the searcher does not care about neither inflate nor penalise the
 * overall.
 *
 * Pure, deterministic, side-effect-free; it persists nothing and reads only the
 * two sets of scores handed to it (one known pair — NOT marketplace-wide
 * candidate selection, which is later two-way orchestration).
 *
 * SCOPING RULES:
 *   - A demand-side score with a value present is a "demanded dimension".
 *     Withheld demand (value null) is not an expressed preference → skipped.
 *   - A demanded dimension whose property side is absent or withheld is a
 *     COVERAGE GAP: it contributes no relevance value but lowers coverage and
 *     confidence.
 *   - Property-only score_keys (no demand preference) are IGNORED.
 *
 * CONFIDENCE (§F4, non-inflating): overall confidence is the demand-weighted
 * mean of the determined dimensions' confidences, then scaled by coverage — so
 * missing high-priority dimensions drag Match Confidence down and it can never
 * exceed the underlying per-dimension confidences.
 */
class RelevanceAggregator
{
    public function __construct(
        private readonly SymmetricRelevanceService $combiner,
    ) {
    }

    /**
     * Aggregate one listing's property-side scores against one demand profile's
     * demand-side scores.
     *
     * @param iterable<DnaScore> $propertyScores
     * @param iterable<DnaScore> $demandScores
     */
    public function aggregate(iterable $propertyScores, iterable $demandScores): AggregateRelevanceResult
    {
        $propertyByKey = [];
        foreach ($propertyScores as $score) {
            if ($score->side === 'property') {
                $propertyByKey[$score->score_key] = $score;
            }
        }

        $demandByKey = [];
        foreach ($demandScores as $score) {
            if ($score->side === 'demand') {
                $demandByKey[$score->score_key] = $score;
            }
        }

        // Stable, input-order-independent output.
        ksort($demandByKey);

        /** @var RelevanceResult[] $contributions */
        $contributions = [];
        foreach ($demandByKey as $key => $demand) {
            if ($demand->value === null) {
                continue; // not an expressed preference
            }

            // Absent property row is a determinable coverage gap: synthesise a
            // withheld property score so the same combine() path applies.
            $property = $propertyByKey[$key] ?? new DnaScore([
                'side'       => 'property',
                'score_key'  => $key,
                'value'      => null,
                'confidence' => 0,
            ]);

            $contributions[] = $this->combiner->combine($property, $demand);
        }

        return $this->summarise($contributions);
    }

    /**
     * @param RelevanceResult[] $contributions
     */
    private function summarise(array $contributions): AggregateRelevanceResult
    {
        $demandedCount = count($contributions);
        if ($demandedCount === 0) {
            return AggregateRelevanceResult::undetermined(
                'Overall relevance undetermined: no demanded dimensions overlap between the listing and the demand profile.'
            );
        }

        $determined        = array_values(array_filter($contributions, static fn (RelevanceResult $c): bool => ! $c->isUndetermined()));
        $determinedCount   = count($determined);
        $undeterminedCount = $demandedCount - $determinedCount;

        $sumWeightAll = 0;
        foreach ($contributions as $c) {
            $sumWeightAll += (int) $c->demandValue;
        }

        $sumWeightDet = 0;
        $sumValueW    = 0;
        $sumConfW     = 0;
        foreach ($determined as $c) {
            $w = (int) $c->demandValue;
            $sumWeightDet += $w;
            $sumValueW    += $w * (int) $c->value;
            $sumConfW     += $w * (int) $c->confidence;
        }

        $coverage = $sumWeightAll > 0 ? (int) floor($sumWeightDet / $sumWeightAll * 100) : 0;

        // No positively-weighted determined dimension → overall undetermined.
        if ($sumWeightDet === 0) {
            $explanation = $sumWeightAll === 0
                ? 'Overall relevance undetermined: no positively-weighted demanded dimensions.'
                : "Overall relevance undetermined: none of the {$demandedCount} demanded dimension(s) had property data.";

            return new AggregateRelevanceResult(
                null,
                0,
                $coverage,
                $demandedCount,
                $determinedCount,
                $undeterminedCount,
                $contributions,
                $explanation,
            );
        }

        $value        = $this->clamp((int) floor($sumValueW / $sumWeightDet));
        $weightedConf = (int) floor($sumConfW / $sumWeightDet);
        $confidence   = (int) floor($weightedConf * $coverage / 100); // §F4: reduced by coverage

        $gapClause = $undeterminedCount > 0
            ? " {$undeterminedCount} demanded dimension(s) lacked property data."
            : '';

        $explanation = "Overall relevance {$value} (confidence {$confidence}, coverage {$coverage}%): "
            . "demand-weighted across {$determinedCount} of {$demandedCount} demanded dimension(s).{$gapClause}";

        return new AggregateRelevanceResult(
            $value,
            $confidence,
            $coverage,
            $demandedCount,
            $determinedCount,
            $undeterminedCount,
            $contributions,
            $explanation,
        );
    }

    private function clamp(int $v): int
    {
        return max(0, min(100, $v));
    }
}
