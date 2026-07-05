<?php

namespace Tests\Unit\Dna;

use App\Services\Dna\Relevance\CandidateAttributeProfile;
use App\Services\Dna\Relevance\MatchDirection;
use App\Services\Dna\Relevance\NarrowingContext;
use App\Services\Dna\Relevance\Narrowers\ListingEligibilityGate;
use Tests\TestCase;

/**
 * Matching V2 — slice 2B mandatory eligibility gate. Keeps only approved, active,
 * offer-listing candidates; drops drafts, sold, Hire-an-Agent, and unresolved rows.
 */
class ListingEligibilityGateTest extends TestCase
{
    private function profile(int $id, bool $eligible): CandidateAttributeProfile
    {
        return new CandidateAttributeProfile('seller_agent', $id, 'property', $eligible, null, null, null, null, null, null, null);
    }

    private function context(array $profiles): NarrowingContext
    {
        $map = [];
        foreach ($profiles as $p) {
            $map[$p->keyString()] = $p;
        }

        return new NarrowingContext(
            'buyer_agent', 8001, MatchDirection::DemandToListings, 'property',
            null, null, $map, 'open',
        );
    }

    public function test_keeps_only_eligible_listings(): void
    {
        $ctx = $this->context([
            $this->profile(1, true),
            $this->profile(2, false), // draft/sold/hire-agent
        ]);

        $tuples = [
            ['listing_type' => 'seller_agent', 'listing_id' => 1],
            ['listing_type' => 'seller_agent', 'listing_id' => 2],
        ];

        $out = (new ListingEligibilityGate())->narrow($tuples, $ctx);

        $this->assertSame([
            ['listing_type' => 'seller_agent', 'listing_id' => 1],
        ], $out);
    }

    public function test_drops_candidate_with_no_resolved_profile(): void
    {
        $ctx = $this->context([$this->profile(1, true)]); // id 2 has no profile

        $tuples = [
            ['listing_type' => 'seller_agent', 'listing_id' => 1],
            ['listing_type' => 'seller_agent', 'listing_id' => 2],
        ];

        $out = (new ListingEligibilityGate())->narrow($tuples, $ctx);

        $this->assertSame([
            ['listing_type' => 'seller_agent', 'listing_id' => 1],
        ], $out);
    }
}
