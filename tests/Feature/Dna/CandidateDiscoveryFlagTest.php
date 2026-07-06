<?php

namespace Tests\Feature\Dna;

use App\Models\BuyerAgentAuction;
use App\Models\DnaScore;
use App\Models\SellerAgentAuction;
use App\Services\Dna\Relevance\CandidateDiscoveryService;
use App\Services\Dna\Relevance\DnaMatchService;
use App\Services\Dna\Relevance\MatchDirection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Matching V2 — consumption slice 2 (Candidate Discovery), end-to-end.
 *
 * Discovery resolves a bounded, ELIGIBLE candidate list from dna_scores that
 * feeds DnaMatchService directly, and is inert + read-only when the flag is off.
 * (With slice 2B, candidates must be approved offer-listings to survive the
 * mandatory eligibility gate.)
 */
class CandidateDiscoveryFlagTest extends TestCase
{
    use DatabaseTransactions;

    private function score(string $type, int $id, string $side, string $key, int $value): void
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

    private function seedEligibleSeller(int $id): void
    {
        SellerAgentAuction::create(['id' => $id, 'user_id' => 1, 'is_approved' => true, 'is_sold' => false])
            ->saveMeta('workflow_type', 'offer_listing');
    }

    private function seedSubjectBuyer(int $id): void
    {
        BuyerAgentAuction::create(['id' => $id, 'user_id' => 2, 'title' => 't', 'is_approved' => true, 'is_sold' => false])
            ->saveMeta('workflow_type', 'offer_listing');
    }

    private function seedDemandSubjectAndListingCandidates(): void
    {
        $this->seedSubjectBuyer(8001);
        $this->score('buyer_agent', 8001, 'demand', 'pet_friendliness', 80);

        $this->seedEligibleSeller(9001);
        $this->score('seller_agent', 9001, 'property', 'pet_friendliness', 100);

        $this->seedEligibleSeller(9002);
        $this->score('seller_agent', 9002, 'property', 'pet_friendliness', 30);
    }

    public function test_discovery_output_feeds_dna_match_service_when_enabled(): void
    {
        config(['matching.v2_enabled' => true]);
        $this->seedDemandSubjectAndListingCandidates();

        $candidates = app(CandidateDiscoveryService::class)
            ->discover('buyer_agent', 8001, MatchDirection::DemandToListings);

        $this->assertEqualsCanonicalizing([
            ['listing_type' => 'seller_agent', 'listing_id' => 9001],
            ['listing_type' => 'seller_agent', 'listing_id' => 9002],
        ], $candidates->toArray());

        $ranked = app(DnaMatchService::class)
            ->matchDemandAgainstListings('buyer_agent', 8001, $candidates->toArray());

        $this->assertSame(2, $ranked->determinedCount());
        $this->assertSame(9001, $ranked->matches[0]->counterpartId);
    }

    public function test_discovery_is_inert_when_disabled(): void
    {
        config(['matching.v2_enabled' => false]);
        $this->seedDemandSubjectAndListingCandidates();

        $candidates = app(CandidateDiscoveryService::class)
            ->discover('buyer_agent', 8001, MatchDirection::DemandToListings);

        $this->assertTrue($candidates->isEmpty());
    }

    public function test_discovery_is_read_only(): void
    {
        config(['matching.v2_enabled' => true]);
        $this->seedDemandSubjectAndListingCandidates();

        $before = DnaScore::count();

        app(CandidateDiscoveryService::class)
            ->discover('buyer_agent', 8001, MatchDirection::DemandToListings);

        $this->assertSame($before, DnaScore::count());
    }
}
