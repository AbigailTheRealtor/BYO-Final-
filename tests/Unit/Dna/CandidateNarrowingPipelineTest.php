<?php

namespace Tests\Unit\Dna;

use App\Services\Dna\Relevance\CandidateAttributeProfile;
use App\Services\Dna\Relevance\CandidateAttributeResolverInterface;
use App\Services\Dna\Relevance\CandidateNarrowingPipeline;
use App\Services\Dna\Relevance\CandidateSet;
use App\Services\Dna\Relevance\MatchDirection;
use App\Services\Stellar\BuyerOfferListingCriteriaLoader;
use App\Services\Stellar\TenantOfferListingCriteriaLoader;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Matching V2 — slice 2B. The pipeline runs the mandatory gates always, the
 * optional narrowers only when hard filters are on, and trims to the final cap.
 */
class CandidateNarrowingPipelineTest extends TestCase
{
    use DatabaseTransactions;

    private function fakeResolver(array $profiles): CandidateAttributeResolverInterface
    {
        $map = [];
        foreach ($profiles as $p) {
            $map[$p->keyString()] = $p;
        }

        return new class($map) implements CandidateAttributeResolverInterface {
            public function __construct(private array $map)
            {
            }

            public function resolveMany(string $side, array $tuples): array
            {
                $out = [];
                foreach ($tuples as $t) {
                    $k = CandidateAttributeProfile::key($t['listing_type'], (int) $t['listing_id']);
                    if (isset($this->map[$k])) {
                        $out[$k] = $this->map[$k];
                    }
                }
                return $out;
            }
        };
    }

    private function pipeline(CandidateAttributeResolverInterface $resolver): CandidateNarrowingPipeline
    {
        return new CandidateNarrowingPipeline(
            $resolver,
            app(BuyerOfferListingCriteriaLoader::class),
            app(TenantOfferListingCriteriaLoader::class),
        );
    }

    private function seller(int $id, bool $eligible, ?bool $age55): CandidateAttributeProfile
    {
        return new CandidateAttributeProfile('seller_agent', $id, 'property', $eligible, $age55, null, null, null, null, null, null);
    }

    private function subjectDemand(?bool $age55): CandidateAttributeProfile
    {
        return new CandidateAttributeProfile('buyer_agent', 8001, 'demand', false, $age55, null, null, null, null, null, null);
    }

    private function tuple(int $id): array
    {
        return ['listing_type' => 'seller_agent', 'listing_id' => $id];
    }

    public function test_mandatory_gates_drop_ineligible_and_senior_mismatch(): void
    {
        config(['matching.candidate_discovery.hard_filters_enabled' => false]);
        config(['matching.candidate_discovery.senior_unknown_policy' => 'open']);

        $resolver = $this->fakeResolver([
            $this->subjectDemand(false),      // seeker NOT 55+ eligible
            $this->seller(1, true, null),     // eligible, senior unknown → keep
            $this->seller(2, false, null),    // ineligible → dropped by eligibility gate
            $this->seller(3, true, true),     // eligible but senior-restricted → dropped by 55+ gate
        ]);

        $stageA = new CandidateSet([$this->tuple(1), $this->tuple(2), $this->tuple(3)], false);

        $out = $this->pipeline($resolver)
            ->narrow($stageA, 'buyer_agent', 8001, MatchDirection::DemandToListings, 200);

        $this->assertSame([$this->tuple(1)], $out->toArray());
    }

    public function test_trims_to_final_cap_and_reports_truncation(): void
    {
        config(['matching.candidate_discovery.hard_filters_enabled' => false]);

        $profiles = [$this->subjectDemand(true)];
        $tuples = [];
        foreach (range(1, 5) as $id) {
            $profiles[] = $this->seller($id, true, null);
            $tuples[] = $this->tuple($id);
        }

        $out = $this->pipeline($this->fakeResolver($profiles))
            ->narrow(new CandidateSet($tuples, false), 'buyer_agent', 8001, MatchDirection::DemandToListings, 2);

        $this->assertCount(2, $out->toArray());
        $this->assertTrue($out->wasTruncated());
    }

    public function test_stage_a_truncation_propagates(): void
    {
        config(['matching.candidate_discovery.hard_filters_enabled' => false]);

        $out = $this->pipeline($this->fakeResolver([
            $this->subjectDemand(true),
            $this->seller(1, true, null),
        ]))->narrow(new CandidateSet([$this->tuple(1)], true), 'buyer_agent', 8001, MatchDirection::DemandToListings, 200);

        $this->assertCount(1, $out->toArray());
        $this->assertTrue($out->wasTruncated());
    }

    public function test_hard_filters_on_without_subject_criteria_is_noop_geo_attr(): void
    {
        // hard filters on, but subject has no seeded offer-listing → criteria null →
        // geo/attribute narrowers are no-ops; mandatory gates still apply.
        config(['matching.candidate_discovery.hard_filters_enabled' => true]);
        config(['matching.candidate_discovery.senior_unknown_policy' => 'open']);

        $out = $this->pipeline($this->fakeResolver([
            $this->subjectDemand(false),
            $this->seller(1, true, null),
            $this->seller(2, true, true), // senior-restricted → dropped
        ]))->narrow(new CandidateSet([$this->tuple(1), $this->tuple(2)], false), 'buyer_agent', 8001, MatchDirection::DemandToListings, 200);

        $this->assertSame([$this->tuple(1)], $out->toArray());
    }

    public function test_empty_stage_a_returns_empty(): void
    {
        $out = $this->pipeline($this->fakeResolver([]))
            ->narrow(new CandidateSet([], false), 'buyer_agent', 8001, MatchDirection::DemandToListings, 200);

        $this->assertTrue($out->isEmpty());
    }
}
