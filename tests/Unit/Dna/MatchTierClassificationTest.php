<?php

namespace Tests\Unit\Dna;

use App\Models\DnaScore;
use App\Services\Dna\Relevance\AggregateRelevanceResult;
use App\Services\Dna\Relevance\MatchTier;
use App\Services\Dna\Relevance\MatchTierClassifier;
use App\Services\Dna\Relevance\MatchTierResult;
use App\Services\Dna\Relevance\RelevanceAggregator;
use App\Services\Dna\Relevance\RelevanceResult;
use App\Services\Dna\Relevance\SymmetricRelevanceService;
use Tests\TestCase;

/**
 * Phase 6 — §F6 match-tier classification (Exact / Strong / Similar / Opportunity).
 *
 * Bands one listing ↔ demand pair by overall Relevance and which categories
 * cleared, with the §F5 cleared/shortfall/gap breakdown. Proves the two-axis
 * banding, coverage-gap demotion, undetermined handling, determinism, and the
 * documented Must-Have gate extension seam. Pure unit (no persistence).
 */
class MatchTierClassificationTest extends TestCase
{
    private function classifier(): MatchTierClassifier
    {
        return new MatchTierClassifier();
    }

    /** A per-dimension contribution; null relevance ⇒ coverage gap. */
    private function contribution(string $key, ?int $relevance): RelevanceResult
    {
        return new RelevanceResult(
            $key,
            $relevance,
            $relevance === null ? 0 : 90,
            100,
            100,
            90,
            90,
            'contribution',
        );
    }

    /**
     * @param RelevanceResult[] $contributions
     */
    private function aggregate(?int $value, int $coverage, array $contributions, int $confidence = 80): AggregateRelevanceResult
    {
        $demanded    = count($contributions);
        $determined  = count(array_filter($contributions, static fn (RelevanceResult $c): bool => ! $c->isUndetermined()));

        return new AggregateRelevanceResult(
            $value,
            $confidence,
            $coverage,
            $demanded,
            $determined,
            $demanded - $determined,
            $contributions,
            'aggregate',
        );
    }

    // ── Canonical tiers ─────────────────────────────────────────────────────

    public function test_high_relevance_all_cleared_is_exact(): void
    {
        $r = $this->classifier()->classify($this->aggregate(95, 100, [
            $this->contribution('pet_friendliness', 90),
            $this->contribution('lock_and_leave', 80),
        ]));

        $this->assertSame(MatchTier::Exact, $r->tier);
        $this->assertSame('Exact Match', $r->tier->label());
        $this->assertSame(['lock_and_leave', 'pet_friendliness'], $r->clearedKeys);
        $this->assertSame([], $r->shortfallKeys);
        $this->assertSame([], $r->gapKeys);
    }

    public function test_high_relevance_all_cleared_no_gaps_strong_when_relevance_between_bands(): void
    {
        $r = $this->classifier()->classify($this->aggregate(75, 100, [
            $this->contribution('pet_friendliness', 90),
            $this->contribution('lock_and_leave', 80),
        ]));

        $this->assertSame(MatchTier::Strong, $r->tier); // relevance band 3 dominates
    }

    public function test_moderate_relevance_is_similar(): void
    {
        $r = $this->classifier()->classify($this->aggregate(55, 60, [
            $this->contribution('pet_friendliness', 80),
            $this->contribution('waterfront_lifestyle', null), // gap
        ]));

        $this->assertSame(MatchTier::Similar, $r->tier);
        $this->assertSame(['pet_friendliness'], $r->clearedKeys);
        $this->assertSame(['waterfront_lifestyle'], $r->gapKeys);
    }

    public function test_low_relevance_is_opportunity(): void
    {
        $r = $this->classifier()->classify($this->aggregate(30, 100, [
            $this->contribution('pet_friendliness', 30),
            $this->contribution('lock_and_leave', 20),
        ]));

        $this->assertSame(MatchTier::Opportunity, $r->tier);
        $this->assertSame([], $r->clearedKeys);
        $this->assertSame(['lock_and_leave', 'pet_friendliness'], $r->shortfallKeys);
    }

    public function test_determinate_zero_relevance_is_still_opportunity_not_undetermined(): void
    {
        $r = $this->classifier()->classify($this->aggregate(0, 100, [
            $this->contribution('pet_friendliness', 0),
        ]));

        $this->assertSame(MatchTier::Opportunity, $r->tier);
        $this->assertFalse($r->isUndetermined());
    }

    // ── Clearance axis demotes independently of relevance ───────────────────

    public function test_a_shortfall_demotes_exact_to_strong(): void
    {
        // High overall relevance but one determined category falls short (no gap).
        $r = $this->classifier()->classify($this->aggregate(92, 100, [
            $this->contribution('pet_friendliness', 95),
            $this->contribution('lock_and_leave', 40), // shortfall
        ]));

        $this->assertSame(MatchTier::Strong, $r->tier);
        $this->assertSame(['pet_friendliness'], $r->clearedKeys);
        $this->assertSame(['lock_and_leave'], $r->shortfallKeys);
    }

    public function test_a_coverage_gap_demotes_exact_to_similar(): void
    {
        // High overall relevance but a demanded category has no property data.
        $r = $this->classifier()->classify($this->aggregate(95, 60, [
            $this->contribution('pet_friendliness', 95),
            $this->contribution('waterfront_lifestyle', null), // gap
        ]));

        $this->assertSame(MatchTier::Similar, $r->tier);
        $this->assertSame(['waterfront_lifestyle'], $r->gapKeys);
    }

    public function test_nothing_cleared_is_opportunity_even_when_overall_value_is_high(): void
    {
        // Clearance band floor (1) caps the tier regardless of the relevance band.
        $r = $this->classifier()->classify($this->aggregate(95, 50, [
            $this->contribution('pet_friendliness', 55),          // shortfall (<60)
            $this->contribution('waterfront_lifestyle', null),    // gap
        ]));

        $this->assertSame(MatchTier::Opportunity, $r->tier);
    }

    // ── Relevance-band boundaries (clearance held at max) ───────────────────

    /**
     * @dataProvider relevanceBoundaries
     */
    public function test_relevance_band_boundaries(int $value, MatchTier $expected): void
    {
        // One always-cleared category → clearance band 4, so the relevance band
        // determines the tier at each boundary.
        $r = $this->classifier()->classify($this->aggregate($value, 100, [
            $this->contribution('pet_friendliness', 100),
        ]));

        $this->assertSame($expected, $r->tier, "value {$value}");
    }

    public static function relevanceBoundaries(): array
    {
        return [
            'exact at 90'        => [90, MatchTier::Exact],
            'strong at 89'       => [89, MatchTier::Strong],
            'strong at 70'       => [70, MatchTier::Strong],
            'similar at 69'      => [69, MatchTier::Similar],
            'similar at 45'      => [45, MatchTier::Similar],
            'opportunity at 44'  => [44, MatchTier::Opportunity],
            'opportunity at 1'   => [1, MatchTier::Opportunity],
        ];
    }

    // ── Clearance-band composition (relevance held at max) ──────────────────

    public function test_clearance_band_composition(): void
    {
        $c = $this->classifier();

        $allCleared = $c->classify($this->aggregate(100, 100, [
            $this->contribution('a', 90), $this->contribution('b', 80),
        ]));
        $this->assertSame(MatchTier::Exact, $allCleared->tier);

        $withShortfall = $c->classify($this->aggregate(100, 100, [
            $this->contribution('a', 90), $this->contribution('b', 40),
        ]));
        $this->assertSame(MatchTier::Strong, $withShortfall->tier);

        $withGap = $c->classify($this->aggregate(100, 60, [
            $this->contribution('a', 90), $this->contribution('b', null),
        ]));
        $this->assertSame(MatchTier::Similar, $withGap->tier);

        $nothingCleared = $c->classify($this->aggregate(100, 40, [
            $this->contribution('a', 40), $this->contribution('b', null),
        ]));
        $this->assertSame(MatchTier::Opportunity, $nothingCleared->tier);
    }

    // ── Undetermined ────────────────────────────────────────────────────────

    public function test_undetermined_aggregate_has_no_tier(): void
    {
        $r = $this->classifier()->classify(
            AggregateRelevanceResult::undetermined('no overlap')
        );

        $this->assertNull($r->tier);
        $this->assertTrue($r->isUndetermined());
        $this->assertNull($r->value);
        $this->assertSame(0, $r->confidence);
        $this->assertStringContainsString('no demanded dimensions overlap', $r->explanation);
    }

    public function test_undetermined_with_all_gaps_reports_gap_keys(): void
    {
        $r = $this->classifier()->classify($this->aggregate(null, 0, [
            $this->contribution('pet_friendliness', null),
            $this->contribution('lock_and_leave', null),
        ]));

        $this->assertNull($r->tier);
        $this->assertSame(['lock_and_leave', 'pet_friendliness'], $r->gapKeys);
        $this->assertStringContainsString('no demanded dimension had property data', $r->explanation);
    }

    // ── Determinism & explanation ───────────────────────────────────────────

    public function test_classification_is_deterministic(): void
    {
        $agg = $this->aggregate(78, 80, [
            $this->contribution('pet_friendliness', 90),
            $this->contribution('lock_and_leave', 40),
            $this->contribution('waterfront_lifestyle', null),
        ]);

        $a = $this->classifier()->classify($agg);
        $b = $this->classifier()->classify($agg);

        $this->assertEquals($a->toArray(), $b->toArray());
    }

    public function test_explanation_lists_cleared_shortfall_and_gap_categories(): void
    {
        $r = $this->classifier()->classify($this->aggregate(78, 66, [
            $this->contribution('pet_friendliness', 90),
            $this->contribution('lock_and_leave', 40),
            $this->contribution('waterfront_lifestyle', null),
        ]));

        $this->assertStringContainsString('cleared: pet_friendliness', $r->explanation);
        $this->assertStringContainsString('falls short: lock_and_leave', $r->explanation);
        $this->assertStringContainsString('no property data: waterfront_lifestyle', $r->explanation);
    }

    // ── End-to-end through the real aggregation kernel ──────────────────────

    public function test_classifies_a_real_aggregate_from_the_kernel(): void
    {
        $aggregator = new RelevanceAggregator(new SymmetricRelevanceService());

        $aggregate = $aggregator->aggregate(
            [
                new DnaScore(['side' => 'property', 'score_key' => 'pet_friendliness', 'value' => 100, 'confidence' => 90]),
                new DnaScore(['side' => 'property', 'score_key' => 'lock_and_leave', 'value' => 80, 'confidence' => 90]),
            ],
            [
                new DnaScore(['side' => 'demand', 'score_key' => 'pet_friendliness', 'value' => 100, 'confidence' => 90]),
                new DnaScore(['side' => 'demand', 'score_key' => 'lock_and_leave', 'value' => 90, 'confidence' => 90]),
            ],
        );

        $r = $this->classifier()->classify($aggregate);

        $this->assertInstanceOf(MatchTierResult::class, $r);
        $this->assertSame(MatchTier::Exact, $r->tier); // both satisfied, fully covered
        $this->assertSame(['lock_and_leave', 'pet_friendliness'], $r->clearedKeys);
    }

    // ── Documented Must-Have gate extension seam ────────────────────────────

    public function test_default_classifier_applies_no_gate_today(): void
    {
        // An otherwise-Exact match is not demoted, because no strength data exists.
        $r = $this->classifier()->classify($this->aggregate(95, 100, [
            $this->contribution('pet_friendliness', 95),
        ]));

        $this->assertSame(MatchTier::Exact, $r->tier);
    }

    public function test_gate_ceiling_extension_point_caps_the_tier_without_touching_banding(): void
    {
        // A future preference-strength implementation overrides ONE method; the
        // banding pipeline applies the ceiling with no other change.
        $gated = new class extends MatchTierClassifier {
            protected function gateCeilingRank(AggregateRelevanceResult $aggregate): ?int
            {
                return MatchTier::Opportunity->rank(); // simulate a failed Must-Have gate
            }
        };

        $r = $gated->classify($this->aggregate(95, 100, [
            $this->contribution('pet_friendliness', 95),
        ]));

        $this->assertSame(MatchTier::Opportunity, $r->tier); // capped, not Exact
        $this->assertSame(['pet_friendliness'], $r->clearedKeys); // breakdown unchanged
    }
}
