<?php

namespace Tests\Unit\Dna;

use App\Models\DnaScore;
use App\Services\Dna\Relevance\RelevanceResult;
use App\Services\Dna\Relevance\SymmetricRelevanceService;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Phase 4 — §F6 Universal Relevance Scoring, smallest read-side primitive.
 *
 * Proves the symmetric relevance combiner over existing property-side and
 * demand-side dna_scores rows: the deterministic contribution, F4 confidence
 * propagation, F5 explanation, non-penalising low demand weight, and the
 * withheld-value → confidence-0 rule. Pure unit (no persistence).
 */
class SymmetricRelevanceTest extends TestCase
{
    private function service(): SymmetricRelevanceService
    {
        return new SymmetricRelevanceService();
    }

    private function property(string $scoreKey, ?int $value, int $confidence): DnaScore
    {
        return new DnaScore([
            'side'       => 'property',
            'score_key'  => $scoreKey,
            'value'      => $value,
            'confidence' => $confidence,
        ]);
    }

    private function demand(string $scoreKey, ?int $value, int $confidence): DnaScore
    {
        return new DnaScore([
            'side'       => 'demand',
            'score_key'  => $scoreKey,
            'value'      => $value,
            'confidence' => $confidence,
        ]);
    }

    // ── Pet-Friendliness ────────────────────────────────────────────────────

    public function test_pet_permissive_property_fully_meets_pet_owner_demand(): void
    {
        $r = $this->service()->combine(
            $this->property('pet_friendliness', 100, 90),
            $this->demand('pet_friendliness', 100, 90),
        );

        $this->assertSame('pet_friendliness', $r->scoreKey);
        $this->assertSame(100, $r->value);
        $this->assertSame(90, $r->confidence);
        $this->assertStringContainsString('meets the demand', $r->explanation);
    }

    public function test_pet_prohibited_property_scores_low_against_strong_pet_demand(): void
    {
        $r = $this->service()->combine(
            $this->property('pet_friendliness', 5, 36),   // pets not permitted
            $this->demand('pet_friendliness', 100, 90),   // owner with pets
        );

        $this->assertSame(5, $r->value);                  // 100 − 100×95/100
        $this->assertSame(36, $r->confidence);            // min(36, 90)
        $this->assertStringContainsString('does not meet a strong demand', $r->explanation);
    }

    public function test_pet_prohibited_property_is_not_penalised_for_a_petless_searcher(): void
    {
        $r = $this->service()->combine(
            $this->property('pet_friendliness', 5, 36),   // pets not permitted
            $this->demand('pet_friendliness', 10, 49),    // no pets → low priority
        );

        // 100 − 10×95/100 = 90.5 → 90: a weak property is NOT a bad match here.
        $this->assertSame(90, $r->value);
        $this->assertSame(36, $r->confidence);            // min(36, 49)
        $this->assertStringContainsString('not limiting', $r->explanation);
    }

    // ── Lock-and-Leave ──────────────────────────────────────────────────────

    public function test_lock_and_leave_condo_meets_seasonal_demand(): void
    {
        $r = $this->service()->combine(
            $this->property('lock_and_leave', 80, 90),
            $this->demand('lock_and_leave', 100, 90),
        );

        $this->assertSame(80, $r->value);                 // 100 − 100×20/100
        $this->assertSame(90, $r->confidence);
    }

    public function test_lock_and_leave_high_maintenance_property_misses_seasonal_demand(): void
    {
        $r = $this->service()->combine(
            $this->property('lock_and_leave', 30, 54),
            $this->demand('lock_and_leave', 100, 72),
        );

        $this->assertSame(30, $r->value);                 // 100 − 100×70/100
        $this->assertSame(54, $r->confidence);            // min(54, 72)
    }

    // ── Waterfront-Lifestyle ────────────────────────────────────────────────

    public function test_waterfront_property_meets_water_view_demand(): void
    {
        $r = $this->service()->combine(
            $this->property('waterfront_lifestyle', 100, 90),
            $this->demand('waterfront_lifestyle', 80, 54),
        );

        $this->assertSame(100, $r->value);                // 100 − 80×0/100
        $this->assertSame(54, $r->confidence);            // min(90, 54)
    }

    public function test_non_waterfront_property_scores_weak_against_water_demand(): void
    {
        $r = $this->service()->combine(
            $this->property('waterfront_lifestyle', 5, 36),
            $this->demand('waterfront_lifestyle', 80, 54),
        );

        $this->assertSame(24, $r->value);                 // 100 − 80×95/100
        $this->assertSame(36, $r->confidence);
    }

    // ── F4 confidence propagation invariant ─────────────────────────────────

    public function test_confidence_never_exceeds_the_weaker_side(): void
    {
        $pairs = [[90, 90], [90, 40], [40, 90], [0, 90], [55, 36], [100, 1]];

        foreach ($pairs as [$pConf, $dConf]) {
            $r = $this->service()->combine(
                $this->property('pet_friendliness', 100, $pConf),
                $this->demand('pet_friendliness', 100, $dConf),
            );

            $this->assertSame(min($pConf, $dConf), $r->confidence, "confidence inflated for [{$pConf},{$dConf}]");
            $this->assertLessThanOrEqual($pConf, $r->confidence);
            $this->assertLessThanOrEqual($dConf, $r->confidence);
        }
    }

    // ── Withheld / null values → undetermined, confidence 0 ─────────────────

    public function test_withheld_property_value_yields_undetermined_zero_confidence(): void
    {
        $r = $this->service()->combine(
            $this->property('pet_friendliness', null, 0),
            $this->demand('pet_friendliness', 100, 90),
        );

        $this->assertNull($r->value);
        $this->assertTrue($r->isUndetermined());
        $this->assertSame(0, $r->confidence);
        $this->assertStringContainsString('property side', $r->explanation);
    }

    public function test_withheld_demand_value_yields_undetermined_zero_confidence(): void
    {
        $r = $this->service()->combine(
            $this->property('pet_friendliness', 100, 90),
            $this->demand('pet_friendliness', null, 0),
        );

        $this->assertNull($r->value);
        $this->assertSame(0, $r->confidence);
        $this->assertStringContainsString('demand side', $r->explanation);
    }

    public function test_both_sides_withheld_reports_both(): void
    {
        $r = $this->service()->combine(
            $this->property('pet_friendliness', null, 0),
            $this->demand('pet_friendliness', null, 0),
        );

        $this->assertNull($r->value);
        $this->assertSame(0, $r->confidence);
        $this->assertStringContainsString('both sides', $r->explanation);
    }

    // Even a high side-confidence cannot rescue a withheld value.
    public function test_withheld_value_forces_zero_confidence_regardless_of_side_confidence(): void
    {
        $r = $this->service()->combine(
            $this->property('pet_friendliness', null, 90),
            $this->demand('pet_friendliness', 100, 90),
        );

        $this->assertNull($r->value);
        $this->assertSame(0, $r->confidence);
    }

    // ── Low demand weight is non-penalising ─────────────────────────────────

    public function test_zero_demand_weight_never_penalises_a_low_property(): void
    {
        $r = $this->service()->combine(
            $this->property('pet_friendliness', 0, 90),
            $this->demand('pet_friendliness', 0, 90),
        );

        $this->assertSame(100, $r->value);                // 100 − 0×100/100
    }

    public function test_low_demand_weight_keeps_relevance_high_for_any_property(): void
    {
        foreach ([0, 25, 50, 75, 100] as $propertyValue) {
            $r = $this->service()->combine(
                $this->property('pet_friendliness', $propertyValue, 90),
                $this->demand('pet_friendliness', 10, 90),  // low priority
            );

            // Worst case (property 0): 100 − 10×100/100 = 90.
            $this->assertGreaterThanOrEqual(90, $r->value, "low demand penalised property={$propertyValue}");
        }
    }

    // ── Determinism & guards ────────────────────────────────────────────────

    public function test_combiner_is_deterministic(): void
    {
        $a = $this->service()->combine(
            $this->property('waterfront_lifestyle', 60, 72),
            $this->demand('waterfront_lifestyle', 80, 54),
        );
        $b = $this->service()->combine(
            $this->property('waterfront_lifestyle', 60, 72),
            $this->demand('waterfront_lifestyle', 80, 54),
        );

        $this->assertEquals($a->toArray(), $b->toArray());
    }

    public function test_mismatched_score_keys_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->combine(
            $this->property('pet_friendliness', 100, 90),
            $this->demand('waterfront_lifestyle', 100, 90),
        );
    }

    public function test_wrong_side_arguments_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // demand row passed where a property row is required
        $this->service()->combine(
            $this->demand('pet_friendliness', 100, 90),
            $this->demand('pet_friendliness', 100, 90),
        );
    }

    public function test_result_value_object_exposes_both_sides_for_explainability(): void
    {
        $r = $this->service()->combine(
            $this->property('lock_and_leave', 80, 88),
            $this->demand('lock_and_leave', 100, 66),
        );

        $arr = $r->toArray();
        $this->assertSame(80, $arr['property_value']);
        $this->assertSame(100, $arr['demand_value']);
        $this->assertSame(88, $arr['property_confidence']);
        $this->assertSame(66, $arr['demand_confidence']);
        $this->assertInstanceOf(RelevanceResult::class, $r);
    }
}
