<?php

namespace Tests\Unit\Dna;

use App\Services\Dna\Relevance\CandidateAttributeProfile;
use App\Services\Dna\Relevance\MatchDirection;
use App\Services\Dna\Relevance\NarrowingContext;
use App\Services\Dna\Relevance\Narrowers\SeniorCommunityComplianceGate;
use Tests\TestCase;

/**
 * Matching V2 — slice 2B mandatory 55+/senior-community legal gate. Drops a
 * pairing only on a confident mismatch (property age-restricted AND seeker not
 * 55+ eligible); unknowns resolve per policy (fail-open by default).
 */
class SeniorCommunityComplianceGateTest extends TestCase
{
    private function prop(string $type, int $id, string $side, ?bool $age55): CandidateAttributeProfile
    {
        return new CandidateAttributeProfile($type, $id, $side, true, $age55, null, null, null, null, null, null);
    }

    /**
     * @return bool whether the single candidate survives the gate
     */
    private function survives(
        MatchDirection $direction,
        ?bool $subjectAge55,
        ?bool $candidateAge55,
        string $policy = 'open',
    ): bool {
        $counterpartSide = $direction->counterpartSide();
        $subjectSide     = $counterpartSide === 'property' ? 'demand' : 'property';

        $subject   = $this->prop($subjectSide === 'demand' ? 'buyer_agent' : 'seller_agent', 1, $subjectSide, $subjectAge55);
        $candidate = $this->prop($counterpartSide === 'property' ? 'seller_agent' : 'buyer_agent', 2, $counterpartSide, $candidateAge55);

        $ctx = new NarrowingContext(
            $subject->listingType, 1, $direction, $counterpartSide,
            $subject, null, [$candidate->keyString() => $candidate], $policy,
        );

        $tuple = ['listing_type' => $candidate->listingType, 'listing_id' => 2];
        $out = (new SeniorCommunityComplianceGate())->narrow([$tuple], $ctx);

        return $out !== [];
    }

    public function test_demand_to_listings_open_policy_matrix(): void
    {
        $d = MatchDirection::DemandToListings;

        // subject = seeker, candidate = property
        $this->assertFalse($this->survives($d, false, true),  'not-eligible seeker + restricted listing → drop');
        $this->assertTrue($this->survives($d, false, false),  'not-eligible seeker + open listing → keep');
        $this->assertTrue($this->survives($d, false, null),   'unknown listing → fail-open keep');
        $this->assertTrue($this->survives($d, true, true),    'eligible seeker + restricted listing → keep');
        $this->assertTrue($this->survives($d, null, true),    'unknown seeker → fail-open keep');
    }

    public function test_demand_to_listings_closed_policy_excludes_unknowns(): void
    {
        $d = MatchDirection::DemandToListings;

        $this->assertFalse($this->survives($d, null, true, 'closed'),  'unknown seeker + restricted → drop under closed');
        $this->assertFalse($this->survives($d, false, null, 'closed'), 'not-eligible seeker + unknown listing → drop under closed');
        $this->assertTrue($this->survives($d, true, true, 'closed'),   'eligible seeker still keeps');
    }

    public function test_listing_to_demands_direction(): void
    {
        $d = MatchDirection::ListingToDemands;

        // subject = property, candidate = seeker
        $this->assertFalse($this->survives($d, true, false), 'restricted listing + not-eligible seeker → drop');
        $this->assertTrue($this->survives($d, true, true),   'restricted listing + eligible seeker → keep');
        $this->assertTrue($this->survives($d, false, false), 'open listing → keep regardless');
        $this->assertTrue($this->survives($d, true, null),   'unknown seeker → fail-open keep');
    }
}
