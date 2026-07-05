<?php

namespace Tests\Unit\Dna;

use App\Services\Dna\Relevance\CandidateAttributeProfile;
use App\Services\Dna\Relevance\MatchDirection;
use App\Services\Dna\Relevance\NarrowingContext;
use App\Services\Dna\Relevance\Narrowers\AttributeNarrower;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use Tests\TestCase;

/**
 * Matching V2 — slice 2B optional attribute narrowing: property-type only,
 * fail-open on unknowns, no-op for demand-side candidates.
 */
class AttributeNarrowerTest extends TestCase
{
    private function payload(array $types): BuyerCriteriaPayload
    {
        return new BuyerCriteriaPayload([
            'property_types'      => $types,
            'is_55_plus_eligible' => false,
        ]);
    }

    private function propProfile(int $id, ?string $propertyType): CandidateAttributeProfile
    {
        return new CandidateAttributeProfile('seller_agent', $id, 'property', true, null, $propertyType, null, null, null, null, null);
    }

    private function context(string $counterpartSide, ?BuyerCriteriaPayload $criteria, array $profiles): NarrowingContext
    {
        $map = [];
        foreach ($profiles as $p) {
            $map[$p->keyString()] = $p;
        }
        $direction = $counterpartSide === 'property' ? MatchDirection::DemandToListings : MatchDirection::ListingToDemands;

        return new NarrowingContext('buyer_agent', 8001, $direction, $counterpartSide, null, $criteria, $map, 'open');
    }

    private function tuple(int $id): array
    {
        return ['listing_type' => 'seller_agent', 'listing_id' => $id];
    }

    public function test_keeps_matching_drops_mismatching_keeps_unknown(): void
    {
        $ctx = $this->context('property', $this->payload(['Residential']), [
            $this->propProfile(1, 'residential'), // match (canonicalized)
            $this->propProfile(2, 'commercial'),  // → 'commercial sale' ≠ residential → drop
            $this->propProfile(3, null),          // unknown → keep (fail-open)
        ]);

        $out = (new AttributeNarrower())->narrow([$this->tuple(1), $this->tuple(2), $this->tuple(3)], $ctx);

        $this->assertSame([$this->tuple(1), $this->tuple(3)], $out);
    }

    public function test_noop_for_demand_side_candidates(): void
    {
        $ctx = $this->context('demand', $this->payload(['Residential']), [
            $this->propProfile(1, 'commercial'), // would be dropped if it ran
        ]);

        $out = (new AttributeNarrower())->narrow([$this->tuple(1)], $ctx);

        $this->assertCount(1, $out);
    }

    public function test_noop_when_no_subject_criteria(): void
    {
        $ctx = $this->context('property', null, [$this->propProfile(1, 'commercial')]);

        $out = (new AttributeNarrower())->narrow([$this->tuple(1)], $ctx);

        $this->assertCount(1, $out);
    }
}
