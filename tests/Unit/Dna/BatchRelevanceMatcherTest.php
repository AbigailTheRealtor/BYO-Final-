<?php

namespace Tests\Unit\Dna;

use App\Models\DnaScore;
use App\Services\Dna\Relevance\BatchRelevanceMatcher;
use App\Services\Dna\Relevance\MatchTier;
use App\Services\Dna\Relevance\MatchTierClassifier;
use App\Services\Dna\Relevance\RankedMatch;
use App\Services\Dna\Relevance\RankedMatchSet;
use App\Services\Dna\Relevance\RelevanceAggregator;
use App\Services\Dna\Relevance\SymmetricRelevanceService;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Phase 7 — §F6 pure batch-match kernel.
 *
 * Proves the safe computational core of two-way orchestration: direction-correct
 * pairing, deterministic tier/value/id ranking, undetermined pairs excluded but
 * counted, tier counts (reverse-demand foundation), and top(n) — end to end
 * through the real aggregator and classifier. Pure unit (no persistence).
 */
class BatchRelevanceMatcherTest extends TestCase
{
    private function matcher(): BatchRelevanceMatcher
    {
        return new BatchRelevanceMatcher(
            new RelevanceAggregator(new SymmetricRelevanceService()),
            new MatchTierClassifier(),
        );
    }

    private function property(string $key, ?int $value, int $confidence = 90): DnaScore
    {
        return new DnaScore(['side' => 'property', 'score_key' => $key, 'value' => $value, 'confidence' => $confidence]);
    }

    private function demand(string $key, ?int $value, int $confidence = 90): DnaScore
    {
        return new DnaScore(['side' => 'demand', 'score_key' => $key, 'value' => $value, 'confidence' => $confidence]);
    }

    // ── Direction correctness ───────────────────────────────────────────────

    public function test_listing_against_demands_pairs_property_with_demand(): void
    {
        // Asymmetric case: property 100 vs demand weight 20 → relevance 100.
        // If the roles were flipped, the aggregator would see no property side
        // and return undetermined — so a determined Exact here proves direction.
        $set = $this->matcher()->matchListingAgainstDemands(
            [$this->property('pet_friendliness', 100)],
            [
                ['id' => 1, 'scores' => [$this->demand('pet_friendliness', 20)]],
            ],
        );

        $this->assertSame(0, $set->undeterminedCount);
        $this->assertCount(1, $set->matches);
        $this->assertSame(100, $set->matches[0]->value());
        $this->assertSame(MatchTier::Exact, $set->matches[0]->tier());
    }

    public function test_demand_against_listings_pairs_property_with_demand(): void
    {
        $set = $this->matcher()->matchDemandAgainstListings(
            [$this->demand('pet_friendliness', 100)],
            [
                ['id' => 1, 'scores' => [$this->property('pet_friendliness', 100)]], // Exact
                ['id' => 2, 'scores' => [$this->property('pet_friendliness', 5)]],   // prohibited
            ],
        );

        $this->assertSame(2, $set->determinedCount());
        // id 1: relevance 100 → Exact; id 2: relevance 5 → Opportunity.
        $this->assertSame(1, $set->matches[0]->counterpartId);
        $this->assertSame(MatchTier::Exact, $set->matches[0]->tier());
        $this->assertSame(2, $set->matches[1]->counterpartId);
        $this->assertSame(MatchTier::Opportunity, $set->matches[1]->tier());
        $this->assertSame(5, $set->matches[1]->value());
    }

    // ── Ranking + tie-breaks + tier counts ──────────────────────────────────

    public function test_ranking_orders_by_tier_then_value_then_id(): void
    {
        $listing = [
            $this->property('pet_friendliness', 100),
            $this->property('lock_and_leave', 50),
        ];

        $set = $this->matcher()->matchListingAgainstDemands($listing, [
            // id 30: pet only → relevance 100 → Exact (ties id 10)
            ['id' => 30, 'scores' => [$this->demand('pet_friendliness', 100)]],
            // id 10: pet only → relevance 100 → Exact (before 30 by id asc)
            ['id' => 10, 'scores' => [$this->demand('pet_friendliness', 100)]],
            // id 25: lock demand 80 vs property 50 → relevance 60 → Similar
            ['id' => 25, 'scores' => [$this->demand('lock_and_leave', 80)]],
            // id 20: lock demand 100 vs property 50 → relevance 50 → Opportunity
            ['id' => 20, 'scores' => [$this->demand('lock_and_leave', 100)]],
        ]);

        $order = array_map(static fn (RankedMatch $m) => $m->counterpartId, $set->matches);
        $this->assertSame([10, 30, 25, 20], $order);

        $tiers = array_map(static fn (RankedMatch $m) => $m->tier(), $set->matches);
        $this->assertSame(
            [MatchTier::Exact, MatchTier::Exact, MatchTier::Similar, MatchTier::Opportunity],
            $tiers,
        );

        $this->assertSame(
            ['exact' => 2, 'strong' => 0, 'similar' => 1, 'opportunity' => 1],
            $set->tierCounts(),
        );
        $this->assertSame(0, $set->undeterminedCount);
    }

    // ── Undetermined excluded from ranking but counted ──────────────────────

    public function test_undetermined_pairs_are_excluded_but_counted(): void
    {
        $set = $this->matcher()->matchListingAgainstDemands(
            [$this->property('pet_friendliness', 100)],
            [
                ['id' => 1, 'scores' => [$this->demand('pet_friendliness', 100)]],     // Exact
                ['id' => 2, 'scores' => [$this->demand('waterfront_lifestyle', 100)]], // all gaps → undetermined
                ['id' => 3, 'scores' => []],                                           // no demand → undetermined
                ['id' => 4, 'scores' => [$this->demand('pet_friendliness', null, 0)]], // withheld → undetermined
            ],
        );

        $this->assertCount(1, $set->matches);
        $this->assertSame(1, $set->matches[0]->counterpartId);
        $this->assertSame(3, $set->undeterminedCount);
    }

    // ── top(n) ──────────────────────────────────────────────────────────────

    public function test_top_n_returns_the_leading_matches(): void
    {
        $listing = [$this->property('pet_friendliness', 100), $this->property('lock_and_leave', 50)];
        $set = $this->matcher()->matchListingAgainstDemands($listing, [
            ['id' => 30, 'scores' => [$this->demand('pet_friendliness', 100)]],
            ['id' => 10, 'scores' => [$this->demand('pet_friendliness', 100)]],
            ['id' => 25, 'scores' => [$this->demand('lock_and_leave', 80)]],
            ['id' => 20, 'scores' => [$this->demand('lock_and_leave', 100)]],
        ]);

        $top2 = array_map(static fn (RankedMatch $m) => $m->counterpartId, $set->top(2));
        $this->assertSame([10, 30], $top2);

        $this->assertSame([], $set->top(0));
        $this->assertSame([], $set->top(-3));
        $this->assertCount(4, $set->top(100)); // clamps to available
    }

    // ── Empty input ─────────────────────────────────────────────────────────

    public function test_empty_counterparts_yields_empty_set(): void
    {
        $set = $this->matcher()->matchListingAgainstDemands(
            [$this->property('pet_friendliness', 100)],
            [],
        );

        $this->assertInstanceOf(RankedMatchSet::class, $set);
        $this->assertSame([], $set->matches);
        $this->assertSame(0, $set->determinedCount());
        $this->assertSame(0, $set->undeterminedCount);
        $this->assertSame(['exact' => 0, 'strong' => 0, 'similar' => 0, 'opportunity' => 0], $set->tierCounts());
        $this->assertSame([], $set->top(5));
    }

    public function test_counterpart_without_id_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->matcher()->matchListingAgainstDemands(
            [$this->property('pet_friendliness', 100)],
            [['scores' => [$this->demand('pet_friendliness', 100)]]], // missing id
        );
    }

    // ── Determinism ─────────────────────────────────────────────────────────

    public function test_matching_is_deterministic(): void
    {
        $listing = [$this->property('pet_friendliness', 100), $this->property('lock_and_leave', 50)];
        $counterparts = [
            ['id' => 20, 'scores' => [$this->demand('lock_and_leave', 100)]],
            ['id' => 10, 'scores' => [$this->demand('pet_friendliness', 100)]],
            ['id' => 25, 'scores' => [$this->demand('lock_and_leave', 80)]],
        ];

        $a = $this->matcher()->matchListingAgainstDemands($listing, $counterparts);
        $b = $this->matcher()->matchListingAgainstDemands($listing, $counterparts);

        $this->assertEquals($a->toArray(), $b->toArray());
    }

    // ── End-to-end shape through the real kernels ───────────────────────────

    public function test_result_set_serialises_with_full_breakdown(): void
    {
        $set = $this->matcher()->matchListingAgainstDemands(
            [$this->property('pet_friendliness', 100), $this->property('lock_and_leave', 80)],
            [
                ['id' => 7, 'scores' => [$this->demand('pet_friendliness', 100), $this->demand('lock_and_leave', 90)]],
            ],
        );

        $arr = $set->toArray();
        $this->assertSame(1, $arr['determined_count']);
        $this->assertSame(0, $arr['undetermined_count']);
        $this->assertArrayHasKey('tier_counts', $arr);
        $this->assertSame(7, $arr['matches'][0]['counterpart_id']);
        $this->assertSame('exact', $arr['matches'][0]['tier']);
        $this->assertSame('Exact Match', $arr['matches'][0]['tier_label']);
        $this->assertArrayHasKey('cleared', $arr['matches'][0]);
        $this->assertArrayHasKey('explanation', $arr['matches'][0]);
    }
}
