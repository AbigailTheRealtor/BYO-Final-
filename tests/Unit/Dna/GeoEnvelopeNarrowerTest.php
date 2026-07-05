<?php

namespace Tests\Unit\Dna;

use App\Services\Dna\Relevance\CandidateAttributeProfile;
use App\Services\Dna\Relevance\MatchDirection;
use App\Services\Dna\Relevance\NarrowingContext;
use App\Services\Dna\Relevance\Narrowers\GeoEnvelopeNarrower;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use Tests\TestCase;

/**
 * Matching V2 — slice 2B optional geo narrowing. DemandToListings only; exact
 * Haversine/PIP + textual city/zip/county; fail-open on missing data.
 */
class GeoEnvelopeNarrowerTest extends TestCase
{
    private function payload(array $overrides = []): BuyerCriteriaPayload
    {
        return new BuyerCriteriaPayload(array_merge([
            'property_types'      => ['Residential'],
            'is_55_plus_eligible' => false,
        ], $overrides));
    }

    private function propProfile(int $id, ?float $lat, ?float $lng, ?string $city = null, ?string $zip = null, ?string $county = null): CandidateAttributeProfile
    {
        return new CandidateAttributeProfile('seller_agent', $id, 'property', true, null, 'residential', $lat, $lng, $city, $zip, $county);
    }

    private function context(MatchDirection $direction, ?BuyerCriteriaPayload $criteria, array $profiles): NarrowingContext
    {
        $map = [];
        foreach ($profiles as $p) {
            $map[$p->keyString()] = $p;
        }

        return new NarrowingContext('buyer_agent', 8001, $direction, 'property', null, $criteria, $map, 'open');
    }

    private function tuple(int $id): array
    {
        return ['listing_type' => 'seller_agent', 'listing_id' => $id];
    }

    public function test_noop_for_listing_to_demands_direction(): void
    {
        $ctx = $this->context(MatchDirection::ListingToDemands, $this->payload([
            'radius_searches' => [['lat' => 27.0, 'lng' => -82.0, 'radius_miles' => 5]],
        ]), [$this->propProfile(1, 40.0, -100.0)]); // far away, would be dropped if it ran

        $out = (new GeoEnvelopeNarrower())->narrow([$this->tuple(1)], $ctx);

        $this->assertCount(1, $out);
    }

    public function test_noop_when_subject_declared_no_geography(): void
    {
        $ctx = $this->context(MatchDirection::DemandToListings, $this->payload(), [$this->propProfile(1, 40.0, -100.0)]);

        $out = (new GeoEnvelopeNarrower())->narrow([$this->tuple(1)], $ctx);

        $this->assertCount(1, $out);
    }

    public function test_radius_keeps_inside_drops_outside(): void
    {
        $criteria = $this->payload([
            'radius_searches' => [['lat' => 27.95, 'lng' => -82.46, 'radius_miles' => 10]], // Tampa-ish
        ]);
        $ctx = $this->context(MatchDirection::DemandToListings, $criteria, [
            $this->propProfile(1, 27.96, -82.47),  // ~1 mile — inside
            $this->propProfile(2, 26.14, -81.79),  // Naples — ~130 miles — outside
        ]);

        $out = (new GeoEnvelopeNarrower())->narrow([$this->tuple(1), $this->tuple(2)], $ctx);

        $this->assertSame([$this->tuple(1)], $out);
    }

    public function test_candidate_without_geo_is_kept_fail_open(): void
    {
        $criteria = $this->payload([
            'radius_searches' => [['lat' => 27.95, 'lng' => -82.46, 'radius_miles' => 10]],
        ]);
        $ctx = $this->context(MatchDirection::DemandToListings, $criteria, [
            $this->propProfile(1, null, null), // no geo signal at all
        ]);

        $out = (new GeoEnvelopeNarrower())->narrow([$this->tuple(1)], $ctx);

        $this->assertSame([$this->tuple(1)], $out);
    }

    public function test_textual_city_match_keeps_candidate(): void
    {
        $criteria = $this->payload(['preferred_cities' => ['Tampa']]);
        $ctx = $this->context(MatchDirection::DemandToListings, $criteria, [
            $this->propProfile(1, null, null, 'tampa'),   // city matches (case-insensitive)
            $this->propProfile(2, 40.0, -100.0, 'Denver'), // has geo point but no city/geo match
        ]);

        $out = (new GeoEnvelopeNarrower())->narrow([$this->tuple(1), $this->tuple(2)], $ctx);

        $this->assertSame([$this->tuple(1)], $out);
    }
}
