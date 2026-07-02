<?php

namespace Tests\Unit\Dna;

use App\Models\DnaScore;
use App\Services\Dna\Relevance\AggregateRelevanceResult;
use App\Services\Dna\Relevance\RelevanceAggregator;
use App\Services\Dna\Relevance\SymmetricRelevanceService;
use Tests\TestCase;

/**
 * Phase 5 — §F6 single-pair, multi-dimension relevance aggregation.
 *
 * Proves the demand-weighted blend, coverage tracking, non-inflating Match
 * Confidence reduced by coverage gaps, the demand-only / property-only pairing
 * rules, and the undetermined cases. Pure unit (no persistence).
 */
class RelevanceAggregationTest extends TestCase
{
    private function aggregator(): RelevanceAggregator
    {
        return new RelevanceAggregator(new SymmetricRelevanceService());
    }

    private function property(string $key, ?int $value, int $confidence): DnaScore
    {
        return new DnaScore(['side' => 'property', 'score_key' => $key, 'value' => $value, 'confidence' => $confidence]);
    }

    private function demand(string $key, ?int $value, int $confidence): DnaScore
    {
        return new DnaScore(['side' => 'demand', 'score_key' => $key, 'value' => $value, 'confidence' => $confidence]);
    }

    // ── Demand-weighted blend (not a plain average) ─────────────────────────

    public function test_overall_is_dominated_by_what_the_searcher_cares_about(): void
    {
        // Strong pet need unmet (prohibited); mild lock-and-leave need met; a
        // waterfront want with no property data (coverage gap).
        $r = $this->aggregator()->aggregate(
            [
                $this->property('pet_friendliness', 5, 36),
                $this->property('lock_and_leave', 80, 90),
                // no waterfront property row
            ],
            [
                $this->demand('pet_friendliness', 100, 90),
                $this->demand('lock_and_leave', 50, 90),
                $this->demand('waterfront_lifestyle', 40, 54),
            ],
        );

        // contributions: pet rel 5, lock rel 90, waterfront undetermined.
        // value  = (100·5 + 50·90) / 150               = 5000/150  = 33
        // cover  = 150 / 190 × 100                      = 78
        // wConf  = (100·36 + 50·90) / 150               = 8100/150  = 54
        // conf   = floor(54 × 78 / 100)                 = 42
        $this->assertSame(33, $r->value);
        $this->assertSame(78, $r->coverage);
        $this->assertSame(42, $r->confidence);
        $this->assertSame(3, $r->demandedCount);
        $this->assertSame(2, $r->determinedCount);
        $this->assertSame(1, $r->undeterminedCount);

        // A plain average of the determined contributions would be (5+90)/2 = 47.5;
        // the demand-weighted blend is far lower because the unmet need dominates.
        $this->assertLessThan(47, $r->value);
    }

    // ── Low demand weight neither inflates nor penalises ────────────────────

    public function test_unsatisfied_low_priority_dimension_barely_dents_a_strong_match(): void
    {
        $base = $this->aggregator()->aggregate(
            [$this->property('pet_friendliness', 100, 90)],
            [$this->demand('pet_friendliness', 100, 90)],
        );
        $this->assertSame(100, $base->value);

        $withLowPriorityGap = $this->aggregator()->aggregate(
            [
                $this->property('pet_friendliness', 100, 90),
                $this->property('lock_and_leave', 0, 90),   // property provides nothing
            ],
            [
                $this->demand('pet_friendliness', 100, 90),
                $this->demand('lock_and_leave', 10, 90),     // but searcher barely cares
            ],
        );

        // lock relevance = 100 − 10×100/100 = 90 (non-penalising); weight 10.
        // value = (100·100 + 10·90) / 110 = 10900/110 = 99 — barely moved.
        $this->assertSame(99, $withLowPriorityGap->value);
        $this->assertGreaterThanOrEqual(95, $withLowPriorityGap->value);
    }

    public function test_satisfied_low_priority_dimension_does_not_inflate_a_weak_match(): void
    {
        // Strong need poorly met, plus an easily-satisfied low-priority want.
        $r = $this->aggregator()->aggregate(
            [
                $this->property('pet_friendliness', 5, 36),
                $this->property('waterfront_lifestyle', 100, 90),
            ],
            [
                $this->demand('pet_friendliness', 100, 90),
                $this->demand('waterfront_lifestyle', 10, 90),
            ],
        );

        // value = (100·5 + 10·100) / 110 = 1500/110 = 13 — stays low.
        $this->assertSame(13, $r->value);
        $this->assertLessThan(52, $r->value); // a plain average would be ~52
    }

    // ── Coverage gaps lower coverage AND confidence ─────────────────────────

    public function test_demand_only_missing_property_is_a_coverage_gap(): void
    {
        $noGap = $this->aggregator()->aggregate(
            [$this->property('pet_friendliness', 100, 90)],
            [$this->demand('pet_friendliness', 100, 90)],
        );
        $this->assertSame(100, $noGap->coverage);
        $this->assertSame(90, $noGap->confidence);

        $withGap = $this->aggregator()->aggregate(
            [$this->property('pet_friendliness', 100, 90)],
            [
                $this->demand('pet_friendliness', 100, 90),
                $this->demand('waterfront_lifestyle', 100, 90), // no property → gap
            ],
        );

        // coverage = 100 / 200 × 100 = 50; confidence = floor(90 × 50/100) = 45.
        $this->assertSame(100, $withGap->value); // value unaffected by the gap
        $this->assertSame(50, $withGap->coverage);
        $this->assertSame(45, $withGap->confidence);
        $this->assertSame(1, $withGap->determinedCount);
        $this->assertSame(1, $withGap->undeterminedCount);
    }

    public function test_withheld_property_value_is_also_a_coverage_gap(): void
    {
        $r = $this->aggregator()->aggregate(
            [$this->property('pet_friendliness', null, 0)], // present row, withheld value
            [$this->demand('pet_friendliness', 100, 90)],
        );

        $this->assertTrue($r->isUndetermined());
        $this->assertNull($r->value);
        $this->assertSame(0, $r->confidence);
        $this->assertSame(0, $r->coverage);
        $this->assertSame(1, $r->demandedCount);
        $this->assertSame(1, $r->undeterminedCount);
    }

    // ── Property-only keys are ignored ──────────────────────────────────────

    public function test_property_only_dimension_without_demand_is_ignored(): void
    {
        $r = $this->aggregator()->aggregate(
            [
                $this->property('pet_friendliness', 100, 90),
                $this->property('waterfront_lifestyle', 100, 90), // no demand for this
            ],
            [$this->demand('pet_friendliness', 50, 80)],
        );

        $this->assertSame(1, $r->demandedCount); // waterfront ignored
        $this->assertSame(100, $r->value);
        $this->assertCount(1, $r->contributions);
        $this->assertSame('pet_friendliness', $r->contributions[0]->scoreKey);
    }

    public function test_withheld_demand_preference_is_not_counted(): void
    {
        $r = $this->aggregator()->aggregate(
            [$this->property('pet_friendliness', 100, 90)],
            [$this->demand('pet_friendliness', null, 0)], // preference withheld
        );

        $this->assertTrue($r->isUndetermined());
        $this->assertSame(0, $r->demandedCount);
    }

    // ── Undetermined cases ──────────────────────────────────────────────────

    public function test_no_overlapping_demanded_dimensions_is_undetermined(): void
    {
        $r = $this->aggregator()->aggregate(
            [$this->property('pet_friendliness', 100, 90)],
            [], // no demand at all
        );

        $this->assertTrue($r->isUndetermined());
        $this->assertNull($r->value);
        $this->assertSame(0, $r->confidence);
        $this->assertSame(0, $r->demandedCount);
        $this->assertStringContainsString('no demanded dimensions overlap', $r->explanation);
    }

    public function test_all_demanded_dimensions_missing_property_is_undetermined(): void
    {
        $r = $this->aggregator()->aggregate(
            [], // no property scores at all
            [
                $this->demand('pet_friendliness', 100, 90),
                $this->demand('lock_and_leave', 50, 90),
            ],
        );

        $this->assertTrue($r->isUndetermined());
        $this->assertNull($r->value);
        $this->assertSame(0, $r->confidence);
        $this->assertSame(2, $r->demandedCount);
        $this->assertSame(0, $r->determinedCount);
        $this->assertSame(2, $r->undeterminedCount);
    }

    // ── Confidence non-inflating ────────────────────────────────────────────

    public function test_overall_confidence_never_exceeds_the_strongest_dimension(): void
    {
        $r = $this->aggregator()->aggregate(
            [
                $this->property('pet_friendliness', 60, 36),
                $this->property('lock_and_leave', 70, 90),
                $this->property('waterfront_lifestyle', 50, 54),
            ],
            [
                $this->demand('pet_friendliness', 80, 40),
                $this->demand('lock_and_leave', 90, 90),
                $this->demand('waterfront_lifestyle', 70, 54),
            ],
        );

        $maxDimConfidence = 0;
        foreach ($r->contributions as $c) {
            $maxDimConfidence = max($maxDimConfidence, $c->confidence);
        }

        $this->assertLessThanOrEqual($maxDimConfidence, $r->confidence);
        $this->assertLessThanOrEqual(100, $r->confidence);
    }

    // ── Determinism (independent of input order) ────────────────────────────

    public function test_aggregation_is_deterministic_regardless_of_input_order(): void
    {
        $propertyA = [
            $this->property('pet_friendliness', 40, 36),
            $this->property('waterfront_lifestyle', 90, 90),
        ];
        $demandA = [
            $this->demand('pet_friendliness', 70, 90),
            $this->demand('waterfront_lifestyle', 60, 54),
        ];

        $a = $this->aggregator()->aggregate($propertyA, $demandA);
        $b = $this->aggregator()->aggregate(array_reverse($propertyA), array_reverse($demandA));

        $this->assertEquals($a->toArray(), $b->toArray());
    }

    // ── Explainability breakdown ────────────────────────────────────────────

    public function test_result_exposes_per_dimension_contributions(): void
    {
        $r = $this->aggregator()->aggregate(
            [
                $this->property('pet_friendliness', 100, 90),
                $this->property('lock_and_leave', 60, 88),
            ],
            [
                $this->demand('pet_friendliness', 80, 90),
                $this->demand('lock_and_leave', 40, 66),
            ],
        );

        $this->assertInstanceOf(AggregateRelevanceResult::class, $r);
        $this->assertCount(2, $r->contributions);
        $keys = array_map(static fn ($c) => $c->scoreKey, $r->contributions);
        $this->assertSame(['lock_and_leave', 'pet_friendliness'], $keys); // ksorted

        $arr = $r->toArray();
        $this->assertArrayHasKey('contributions', $arr);
        $this->assertSame('lock_and_leave', $arr['contributions'][0]['score_key']);
        $this->assertStringContainsString('coverage', $r->explanation);
    }
}
