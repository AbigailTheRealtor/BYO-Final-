<?php

namespace Tests\Unit\Dna;

use App\Services\Dna\Relevance\CandidateDiscoveryService;
use App\Services\Dna\Relevance\CandidateNarrowingPipeline;
use App\Services\Dna\Relevance\CandidateQuery;
use App\Services\Dna\Relevance\CandidateSet;
use App\Services\Dna\Relevance\CandidateSourceInterface;
use App\Services\Dna\Relevance\MatchDirection;
use Tests\TestCase;

/**
 * Matching V2 — consumption slice 2 orchestrator. Flag-gated; when off it returns
 * an empty set without touching the source or the Stage-B pipeline. When on it
 * maps the direction to the counterpart side, over-fetches for Stage A, and hands
 * the result to the narrowing pipeline.
 *
 * Pure unit tests: source and pipeline are recording fakes (no DB). The real
 * Stage-B behavior is covered by the pipeline/narrower/feature tests.
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
                    ['listing_type' => 'seller_agent', 'listing_id' => 101],
                ], false);
            }
        };
    }

    private function recordingPipeline(): CandidateNarrowingPipeline
    {
        return new class extends CandidateNarrowingPipeline {
            public int $calls = 0;
            public ?CandidateSet $received = null;
            public ?int $finalCap = null;

            public function __construct()
            {
                // Intentionally bypass parent constructor — this fake records only.
            }

            public function narrow(
                CandidateSet $stageA,
                string $subjectType,
                int $subjectId,
                MatchDirection $direction,
                int $finalCap,
            ): CandidateSet {
                $this->calls++;
                $this->received = $stageA;
                $this->finalCap = $finalCap;

                return new CandidateSet([
                    ['listing_type' => 'seller_agent', 'listing_id' => 999],
                ], false);
            }
        };
    }

    private function service($source, $pipeline): CandidateDiscoveryService
    {
        return new CandidateDiscoveryService($source, $pipeline);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'matching.candidate_discovery.cap' => 200,
            'matching.candidate_discovery.overfetch_multiplier' => 3,
            'matching.candidate_discovery.overfetch_ceiling' => 1000,
            'matching.candidate_discovery.allowed_listing_types.property' => [],
            'matching.candidate_discovery.allowed_listing_types.demand' => [],
        ]);
    }

    public function test_disabled_returns_empty_and_touches_nothing(): void
    {
        config(['matching.v2_enabled' => false]);
        $source = $this->recordingSource();
        $pipeline = $this->recordingPipeline();

        $set = $this->service($source, $pipeline)
            ->discover('seller_agent', 6001, MatchDirection::ListingToDemands);

        $this->assertTrue($set->isEmpty());
        $this->assertSame(0, $source->calls);
        $this->assertSame(0, $pipeline->calls);
    }

    public function test_listing_to_demands_asks_for_demand_side_counterparts(): void
    {
        config(['matching.v2_enabled' => true]);
        $source = $this->recordingSource();
        $pipeline = $this->recordingPipeline();

        $this->service($source, $pipeline)
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

        $this->service($source, $this->recordingPipeline())
            ->discover('buyer_agent', 8001, MatchDirection::DemandToListings);

        $this->assertSame('property', $source->lastQuery->counterpartSide);
    }

    public function test_stage_a_overfetches_pipeline_gets_final_cap(): void
    {
        config(['matching.v2_enabled' => true]);
        $source = $this->recordingSource();
        $pipeline = $this->recordingPipeline();

        // Default cap 200 × multiplier 3 = 600 over-fetch; pipeline trims to 200.
        $this->service($source, $pipeline)
            ->discover('buyer_agent', 8001, MatchDirection::DemandToListings);
        $this->assertSame(600, $source->lastQuery->cap);
        $this->assertSame(200, $pipeline->finalCap);

        // Override cap 25 → over-fetch 75, pipeline trims to 25.
        $this->service($source, $pipeline)
            ->discover('buyer_agent', 8001, MatchDirection::DemandToListings, cap: 25);
        $this->assertSame(75, $source->lastQuery->cap);
        $this->assertSame(25, $pipeline->finalCap);
    }

    public function test_overfetch_respects_ceiling(): void
    {
        config(['matching.v2_enabled' => true]);
        config(['matching.candidate_discovery.overfetch_ceiling' => 500]);
        $source = $this->recordingSource();

        // 300 × 3 = 900, clamped to ceiling 500.
        $this->service($source, $this->recordingPipeline())
            ->discover('buyer_agent', 8001, MatchDirection::DemandToListings, cap: 300);

        $this->assertSame(500, $source->lastQuery->cap);
    }

    public function test_allowed_listing_types_read_from_config_per_side(): void
    {
        config(['matching.v2_enabled' => true]);
        config(['matching.candidate_discovery.allowed_listing_types.property' => ['seller_agent']]);
        $source = $this->recordingSource();

        $this->service($source, $this->recordingPipeline())
            ->discover('buyer_agent', 8001, MatchDirection::DemandToListings);
        $this->assertSame(['seller_agent'], $source->lastQuery->allowedListingTypes);
    }

    public function test_returns_pipeline_output(): void
    {
        config(['matching.v2_enabled' => true]);

        $set = $this->service($this->recordingSource(), $this->recordingPipeline())
            ->discover('seller_agent', 6001, MatchDirection::ListingToDemands);

        $this->assertSame([
            ['listing_type' => 'seller_agent', 'listing_id' => 999],
        ], $set->toArray());
    }
}
