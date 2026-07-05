<?php

namespace Tests\Unit\Dna;

use App\Services\Dna\Relevance\CandidateDiscoveryService;
use App\Services\Dna\Relevance\CandidateQuery;
use App\Services\Dna\Relevance\CandidateSet;
use App\Services\Dna\Relevance\CandidateSourceInterface;
use App\Services\Dna\Relevance\MatchDirection;
use Tests\TestCase;

/**
 * Matching V2 — consumption slice 2 (Candidate Discovery).
 *
 * CandidateDiscoveryService is the flag-gated orchestrator. It never scores and
 * never writes; when Matching V2 is off it returns an empty set WITHOUT touching
 * the candidate source (so no DB reads occur). When on, it maps the direction to
 * the correct counterpart side and forwards cap + exclusions to the source.
 *
 * These are pure unit tests: the source is a recording fake, so no database is
 * needed (the read-only DB path is covered by ScoredEntityCandidateSourceTest and
 * the feature test).
 */
class CandidateDiscoveryServiceTest extends TestCase
{
    private function recordingSource(): CandidateSourceInterface
    {
        return new class implements CandidateSourceInterface {
            public int $calls = 0;
            public ?CandidateQuery $lastQuery = null;

            public function resolve(CandidateQuery $query): CandidateSet
            {
                $this->calls++;
                $this->lastQuery = $query;

                return new CandidateSet([
                    ['listing_type' => 'buyer_agent', 'listing_id' => 7001],
                ], false);
            }
        };
    }

    private function service(CandidateSourceInterface $source): CandidateDiscoveryService
    {
        return new CandidateDiscoveryService($source);
    }

    public function test_disabled_returns_empty_and_never_calls_the_source(): void
    {
        config(['matching.v2_enabled' => false]);
        $source = $this->recordingSource();

        $set = $this->service($source)
            ->discover('seller_agent', 6001, MatchDirection::ListingToDemands);

        $this->assertInstanceOf(CandidateSet::class, $set);
        $this->assertTrue($set->isEmpty());
        $this->assertSame(0, $source->calls, 'Source must not be touched when Matching V2 is off.');
    }

    public function test_listing_to_demands_asks_for_demand_side_counterparts(): void
    {
        config(['matching.v2_enabled' => true]);
        $source = $this->recordingSource();

        $this->service($source)
            ->discover('seller_agent', 6001, MatchDirection::ListingToDemands);

        $this->assertSame(1, $source->calls);
        $this->assertSame('demand', $source->lastQuery->counterpartSide);
        $this->assertSame('seller_agent', $source->lastQuery->excludeListingType);
        $this->assertSame(6001, $source->lastQuery->excludeListingId);
    }

    public function test_demand_to_listings_asks_for_property_side_counterparts(): void
    {
        config(['matching.v2_enabled' => true]);
        $source = $this->recordingSource();

        $this->service($source)
            ->discover('buyer_agent', 8001, MatchDirection::DemandToListings);

        $this->assertSame('property', $source->lastQuery->counterpartSide);
    }

    public function test_cap_defaults_to_config_and_can_be_overridden(): void
    {
        config(['matching.v2_enabled' => true]);
        config(['matching.candidate_discovery.cap' => 200]);
        $source = $this->recordingSource();

        $this->service($source)
            ->discover('buyer_agent', 8001, MatchDirection::DemandToListings);
        $this->assertSame(200, $source->lastQuery->cap);

        $this->service($source)
            ->discover('buyer_agent', 8001, MatchDirection::DemandToListings, cap: 25);
        $this->assertSame(25, $source->lastQuery->cap);
    }

    public function test_allowed_listing_types_read_from_config_per_side(): void
    {
        config(['matching.v2_enabled' => true]);
        config(['matching.candidate_discovery.allowed_listing_types.property' => ['seller_agent']]);
        config(['matching.candidate_discovery.allowed_listing_types.demand' => []]);
        $source = $this->recordingSource();

        // Demand->listings looks at the 'property' side allowlist.
        $this->service($source)
            ->discover('buyer_agent', 8001, MatchDirection::DemandToListings);
        $this->assertSame(['seller_agent'], $source->lastQuery->allowedListingTypes);

        // Listing->demands looks at the 'demand' side allowlist (empty = all).
        $this->service($source)
            ->discover('seller_agent', 6001, MatchDirection::ListingToDemands);
        $this->assertSame([], $source->lastQuery->allowedListingTypes);
    }

    public function test_enabled_returns_the_sources_candidate_set(): void
    {
        config(['matching.v2_enabled' => true]);
        $source = $this->recordingSource();

        $set = $this->service($source)
            ->discover('seller_agent', 6001, MatchDirection::ListingToDemands);

        $this->assertSame([
            ['listing_type' => 'buyer_agent', 'listing_id' => 7001],
        ], $set->toArray());
    }
}
