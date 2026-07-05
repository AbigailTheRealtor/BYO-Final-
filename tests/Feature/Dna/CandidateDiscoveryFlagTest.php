<?php

namespace Tests\Feature\Dna;

use App\Models\DnaScore;
use App\Services\Dna\Relevance\CandidateDiscoveryService;
use App\Services\Dna\Relevance\DnaMatchService;
use App\Services\Dna\Relevance\MatchDirection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Matching V2 — consumption slice 2 (Candidate Discovery), end-to-end.
 *
 * Discovery (slice 2) resolves a bounded candidate list from dna_scores that
 * feeds DnaMatchService (slice 1) directly, and the whole path is inert + read-only
 * when the master flag is off.
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

    private function seedDemandSubjectAndListingCandidates(): void
    {
        // Demand subject (buyer) wants pet-friendliness strongly.
        $this->score('buyer_agent', 8001, 'demand', 'pet_friendliness', 80);

        // Two property candidates with DNA on the property side.
        $this->score('seller_agent', 9001, 'property', 'pet_friendliness', 100);
        $this->score('seller_agent', 9002, 'property', 'pet_friendliness', 30);
    }

    public function test_discovery_output_feeds_dna_match_service_when_enabled(): void
    {
        config(['matching.v2_enabled' => true]);
        $this->seedDemandSubjectAndListingCandidates();

        $candidates = app(CandidateDiscoveryService::class)
            ->discover('buyer_agent', 8001, MatchDirection::DemandToListings);

        // Discovery found both property candidates (and not the demand subject).
        $this->assertEqualsCanonicalizing([
            ['listing_type' => 'seller_agent', 'listing_id' => 9001],
            ['listing_type' => 'seller_agent', 'listing_id' => 9002],
        ], $candidates->toArray());

        // The tuple shape drops straight into the slice-1 matcher.
        $ranked = app(DnaMatchService::class)
            ->matchDemandAgainstListings('buyer_agent', 8001, $candidates->toArray());

        $this->assertSame(2, $ranked->determinedCount());
        // The listing that provides pet fully (9001) outranks the weak one (9002).
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
