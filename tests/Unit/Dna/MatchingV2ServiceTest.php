<?php

namespace Tests\Unit\Dna;

use App\Services\Dna\Relevance\CandidateDiscoveryService;
use App\Services\Dna\Relevance\CandidateSet;
use App\Services\Dna\Relevance\DnaMatchService;
use App\Services\Dna\Relevance\MatchDirection;
use App\Services\Dna\Relevance\MatchingV2Service;
use App\Services\Dna\Relevance\MatchTier;
use App\Services\Dna\Relevance\MatchTierResult;
use App\Services\Dna\Relevance\RankedMatch;
use App\Services\Dna\Relevance\RankedMatchSet;
use Tests\TestCase;

/**
 * Matching V2 C6 — the orchestration facade. Pure unit tests with recording fakes
 * for discovery + matcher (no DB): flag/direction/empty/compose + type preservation.
 */
class MatchingV2ServiceTest extends TestCase
{
    private function fakeDiscovery(CandidateSet $return): CandidateDiscoveryService
    {
        return new class($return) extends CandidateDiscoveryService {
            public int $calls = 0;
            public ?MatchDirection $direction = null;

            public function __construct(private CandidateSet $return)
            {
                // bypass parent constructor — fake records only
            }

            public function discover(string $listingType, int $listingId, MatchDirection $direction, ?int $cap = null): CandidateSet
            {
                $this->calls++;
                $this->direction = $direction;
                return $this->return;
            }
        };
    }

    private function fakeMatcher(RankedMatchSet $return): DnaMatchService
    {
        return new class($return) extends DnaMatchService {
            public int $demandCalls = 0;
            public int $listingCalls = 0;
            public array $candidates = [];

            public function __construct(private RankedMatchSet $return)
            {
            }

            public function matchDemandAgainstListings(string $listingType, int $listingId, array $candidates): RankedMatchSet
            {
                $this->demandCalls++;
                $this->candidates = $candidates;
                return $this->return;
            }

            public function matchListingAgainstDemands(string $listingType, int $listingId, array $candidates): RankedMatchSet
            {
                $this->listingCalls++;
                $this->candidates = $candidates;
                return $this->return;
            }
        };
    }

    private function tr(): MatchTierResult
    {
        return new MatchTierResult(MatchTier::Strong, 80, 90, 100, [], [], [], 'seed');
    }

    public function test_disabled_returns_empty_and_touches_nothing(): void
    {
        config(['matching.v2_enabled' => false]);
        $discovery = $this->fakeDiscovery(new CandidateSet([['listing_type' => 'seller_agent', 'listing_id' => 1]], false));
        $matcher   = $this->fakeMatcher(new RankedMatchSet([], 0));

        $result = (new MatchingV2Service($discovery, $matcher))->matchForSubject('buyer_agent', 8001);

        $this->assertTrue($result->isEmpty());
        $this->assertSame(0, $discovery->calls);
        $this->assertSame(0, $matcher->demandCalls + $matcher->listingCalls);
    }

    public function test_unsupported_type_returns_empty_without_discovery(): void
    {
        config(['matching.v2_enabled' => true]);
        $discovery = $this->fakeDiscovery(new CandidateSet([], false));
        $matcher   = $this->fakeMatcher(new RankedMatchSet([], 0));

        $result = (new MatchingV2Service($discovery, $matcher))->matchForSubject('agent_service', 1);

        $this->assertTrue($result->isEmpty());
        $this->assertNull($result->direction());
        $this->assertSame(0, $discovery->calls);
    }

    public function test_demand_subject_infers_demand_to_listings(): void
    {
        config(['matching.v2_enabled' => true]);
        $discovery = $this->fakeDiscovery(new CandidateSet([['listing_type' => 'seller_agent', 'listing_id' => 9001]], false));
        $matcher   = $this->fakeMatcher(new RankedMatchSet([], 0));

        (new MatchingV2Service($discovery, $matcher))->matchForSubject('buyer_agent', 8001);

        $this->assertSame(MatchDirection::DemandToListings, $discovery->direction);
        $this->assertSame(1, $matcher->demandCalls);
        $this->assertSame(0, $matcher->listingCalls);
    }

    public function test_property_subject_infers_listing_to_demands(): void
    {
        config(['matching.v2_enabled' => true]);
        $discovery = $this->fakeDiscovery(new CandidateSet([['listing_type' => 'buyer_agent', 'listing_id' => 7001]], false));
        $matcher   = $this->fakeMatcher(new RankedMatchSet([], 0));

        (new MatchingV2Service($discovery, $matcher))->matchForSubject('seller_agent', 6001);

        $this->assertSame(MatchDirection::ListingToDemands, $discovery->direction);
        $this->assertSame(1, $matcher->listingCalls);
        $this->assertSame(0, $matcher->demandCalls);
    }

    public function test_empty_candidates_short_circuit_without_matching(): void
    {
        config(['matching.v2_enabled' => true]);
        $discovery = $this->fakeDiscovery(new CandidateSet([], false));
        $matcher   = $this->fakeMatcher(new RankedMatchSet([], 0));

        $result = (new MatchingV2Service($discovery, $matcher))->matchForSubject('buyer_agent', 8001);

        $this->assertSame(0, $matcher->demandCalls + $matcher->listingCalls);
        $this->assertSame(0, $result->candidatesConsidered());
        $this->assertTrue($result->isEmpty());
    }

    public function test_composes_and_preserves_type_and_truncation(): void
    {
        config(['matching.v2_enabled' => true]);
        $candidates = new CandidateSet([
            ['listing_type' => 'seller_agent', 'listing_id' => 9001],
            ['listing_type' => 'landlord_agent', 'listing_id' => 9002],
        ], true);
        $ranked = new RankedMatchSet([
            new RankedMatch(9001, $this->tr(), 'seller_agent'),
            new RankedMatch(9002, $this->tr(), 'landlord_agent'),
        ], 1);

        $result = (new MatchingV2Service($this->fakeDiscovery($candidates), $this->fakeMatcher($ranked)))
            ->matchForSubject('buyer_agent', 8001);

        $this->assertSame([
            ['listing_type' => 'seller_agent', 'listing_id' => 9001, 'tier' => 'strong', 'value' => 80],
            ['listing_type' => 'landlord_agent', 'listing_id' => 9002, 'tier' => 'strong', 'value' => 80],
        ], $result->matches());
        $this->assertSame(2, $result->candidatesConsidered());
        $this->assertTrue($result->candidatePoolTruncated());
        $this->assertSame(2, $result->determinedCount());
        $this->assertSame(1, $result->undeterminedCount());
    }
}
