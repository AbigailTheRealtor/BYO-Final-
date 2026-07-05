<?php

namespace Tests\Unit\Dna;

use App\Models\DnaScore;
use App\Services\Dna\Relevance\DnaMatchService;
use App\Services\Dna\Relevance\RankedMatchSet;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Matching V2 — consumption slice 1. DnaMatchService is a pure read-only
 * consumer of dna_scores: inert when disabled, correctly ranks explicit
 * candidates when enabled, and never writes to dna_scores.
 */
class DnaMatchServiceTest extends TestCase
{
    use DatabaseTransactions;

    private function score(string $type, int $id, string $side, string $key, ?int $value): void
    {
        DnaScore::create([
            'listing_type'      => $type,
            'listing_id'        => $id,
            'score_key'         => $key,
            'side'              => $side,
            'value'             => $value,
            'data_completeness' => 100,
            'confidence'        => 90,
            'explanation'       => 'seed',
            'version'           => 'TEST_V1',
            'generator_version' => 'TEST_V1',
            'generated_by'      => 'system',
        ]);
    }

    /**
     * Subject listing provides pet fully (100) but waterfront weakly (20).
     * Candidate 7001 barely wants waterfront → strong match.
     * Candidate 7002 strongly wants waterfront → weaker match.
     */
    private function seedListingAndDemandCandidates(): void
    {
        $this->score('seller_agent', 6001, 'property', 'pet_friendliness', 100);
        $this->score('seller_agent', 6001, 'property', 'waterfront_lifestyle', 20);

        $this->score('buyer_agent', 7001, 'demand', 'pet_friendliness', 80);
        $this->score('buyer_agent', 7001, 'demand', 'waterfront_lifestyle', 10);

        $this->score('buyer_agent', 7002, 'demand', 'pet_friendliness', 80);
        $this->score('buyer_agent', 7002, 'demand', 'waterfront_lifestyle', 90);
    }

    public function test_disabled_returns_inert_empty_set_and_reads_nothing(): void
    {
        config(['matching.v2_enabled' => false]);
        $this->seedListingAndDemandCandidates();

        $set = app(DnaMatchService::class)->matchListingAgainstDemands('seller_agent', 6001, [
            ['listing_type' => 'buyer_agent', 'listing_id' => 7001],
            ['listing_type' => 'buyer_agent', 'listing_id' => 7002],
        ]);

        $this->assertInstanceOf(RankedMatchSet::class, $set);
        $this->assertSame(0, $set->determinedCount());
        $this->assertSame(0, $set->undeterminedCount);
    }

    public function test_enabled_ranks_candidates_best_first_with_tier_counts(): void
    {
        config(['matching.v2_enabled' => true]);
        $this->seedListingAndDemandCandidates();

        $set = app(DnaMatchService::class)->matchListingAgainstDemands('seller_agent', 6001, [
            ['listing_type' => 'buyer_agent', 'listing_id' => 7002], // weaker, listed first on purpose
            ['listing_type' => 'buyer_agent', 'listing_id' => 7001], // stronger
        ]);

        $this->assertSame(2, $set->determinedCount());

        // Stronger candidate (7001) is ranked ahead of the weaker (7002).
        $this->assertSame(7001, $set->matches[0]->counterpartId);
        $this->assertSame(7002, $set->matches[1]->counterpartId);
        $this->assertGreaterThan(
            $set->matches[1]->result->tier->rank(),
            $set->matches[0]->result->tier->rank(),
        );

        // Tier counts are internally consistent with the determined matches.
        $this->assertSame($set->determinedCount(), array_sum($set->tierCounts()));
    }

    public function test_demand_against_listings_direction_produces_matches(): void
    {
        config(['matching.v2_enabled' => true]);
        // Subject is a buyer demand; candidates are property listings.
        $this->score('buyer_agent', 8001, 'demand', 'pet_friendliness', 80);
        $this->score('seller_agent', 9001, 'property', 'pet_friendliness', 100);
        $this->score('seller_agent', 9002, 'property', 'pet_friendliness', 30);

        $set = app(DnaMatchService::class)->matchDemandAgainstListings('buyer_agent', 8001, [
            ['listing_type' => 'seller_agent', 'listing_id' => 9001],
            ['listing_type' => 'seller_agent', 'listing_id' => 9002],
        ]);

        $this->assertSame(2, $set->determinedCount());
        // The listing that provides pet fully (9001) outranks the weak one (9002).
        $this->assertSame(9001, $set->matches[0]->counterpartId);
    }

    public function test_match_is_read_only_dna_score_count_unchanged(): void
    {
        config(['matching.v2_enabled' => true]);
        $this->seedListingAndDemandCandidates();

        $before = DnaScore::count();

        app(DnaMatchService::class)->matchListingAgainstDemands('seller_agent', 6001, [
            ['listing_type' => 'buyer_agent', 'listing_id' => 7001],
            ['listing_type' => 'buyer_agent', 'listing_id' => 7002],
        ]);

        $this->assertSame($before, DnaScore::count());
    }
}
