<?php

namespace Tests\Feature\Dna;

use App\Models\BuyerAgentAuction;
use App\Models\DnaScore;
use App\Models\PropertyLocationDna;
use App\Models\SellerAgentAuction;
use App\Services\Dna\Relevance\CandidateDiscoveryService;
use App\Services\Dna\Relevance\MatchDirection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Matching V2 — slice 2B end-to-end. The mandatory eligibility + 55+ gates run
 * whenever V2 is on (regardless of hard_filters_enabled); optional geo narrowing
 * runs only when hard filters are on. Everything read-only.
 */
class CandidateNarrowingComplianceTest extends TestCase
{
    use DatabaseTransactions;

    private function score(string $type, int $id, string $side, int $value): void
    {
        DnaScore::create([
            'listing_type'      => $type,
            'listing_id'        => $id,
            'score_key'         => 'pet_friendliness',
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

    private function seedSeller(int $id, array $meta, ?array $geo = null): void
    {
        $auction = SellerAgentAuction::create(['id' => $id, 'user_id' => 1, 'is_approved' => true, 'is_sold' => false]);
        foreach ($meta as $k => $v) {
            $auction->saveMeta($k, $v);
        }
        $this->score('seller_agent', $id, 'property', 100);

        if ($geo !== null) {
            PropertyLocationDna::create(array_merge([
                'listing_type' => 'seller_agent',
                'listing_id'   => $id,
            ], $geo));
        }
    }

    private function seedBuyerSubject(int $id, array $meta): void
    {
        $auction = BuyerAgentAuction::create(['id' => $id, 'user_id' => 2, 'is_approved' => true, 'is_sold' => false]);
        foreach ($meta as $k => $v) {
            $auction->saveMeta($k, $v);
        }
        $this->score('buyer_agent', $id, 'demand', 80);
    }

    public function test_non_eligible_seeker_never_gets_senior_restricted_listing(): void
    {
        config(['matching.v2_enabled' => true]);
        config(['matching.candidate_discovery.hard_filters_enabled' => false]);
        config(['matching.candidate_discovery.senior_unknown_policy' => 'open']);

        // Seeker is NOT 55+ eligible.
        $this->seedBuyerSubject(8001, ['workflow_type' => 'offer_listing', 'leasing_55_plus' => 'No']);

        $this->seedSeller(9001, ['workflow_type' => 'offer_listing', 'leasing_55_plus' => 'Yes']); // senior-restricted
        $this->seedSeller(9002, ['workflow_type' => 'offer_listing', 'leasing_55_plus' => 'No']);  // open

        $out = app(CandidateDiscoveryService::class)
            ->discover('buyer_agent', 8001, MatchDirection::DemandToListings)
            ->toArray();

        $this->assertSame([['listing_type' => 'seller_agent', 'listing_id' => 9002]], $out);
    }

    public function test_eligible_seeker_gets_senior_listing(): void
    {
        config(['matching.v2_enabled' => true]);
        config(['matching.candidate_discovery.hard_filters_enabled' => false]);

        $this->seedBuyerSubject(8001, ['workflow_type' => 'offer_listing', 'leasing_55_plus' => 'Yes']);
        $this->seedSeller(9001, ['workflow_type' => 'offer_listing', 'leasing_55_plus' => 'Yes']);
        $this->seedSeller(9002, ['workflow_type' => 'offer_listing', 'leasing_55_plus' => 'No']);

        $out = app(CandidateDiscoveryService::class)
            ->discover('buyer_agent', 8001, MatchDirection::DemandToListings)
            ->toArray();

        $this->assertEqualsCanonicalizing([
            ['listing_type' => 'seller_agent', 'listing_id' => 9001],
            ['listing_type' => 'seller_agent', 'listing_id' => 9002],
        ], $out);
    }

    public function test_ineligible_listings_are_excluded(): void
    {
        config(['matching.v2_enabled' => true]);
        config(['matching.candidate_discovery.hard_filters_enabled' => false]);

        $this->seedBuyerSubject(8001, ['workflow_type' => 'offer_listing', 'leasing_55_plus' => 'No']);
        $this->seedSeller(9002, ['workflow_type' => 'offer_listing', 'leasing_55_plus' => 'No']); // eligible
        // Hire-an-Agent record (no workflow_type) — scored but not a marketplace listing.
        $hire = SellerAgentAuction::create(['id' => 9003, 'user_id' => 1, 'is_approved' => true, 'is_sold' => false]);
        $this->score('seller_agent', 9003, 'property', 100);

        $out = app(CandidateDiscoveryService::class)
            ->discover('buyer_agent', 8001, MatchDirection::DemandToListings)
            ->toArray();

        $this->assertSame([['listing_type' => 'seller_agent', 'listing_id' => 9002]], $out);
    }

    public function test_hard_filter_geo_narrows_by_radius(): void
    {
        config(['matching.v2_enabled' => true]);
        config(['matching.candidate_discovery.hard_filters_enabled' => true]);
        config(['matching.candidate_discovery.senior_unknown_policy' => 'open']);

        // Buyer offer-listing with a 10-mile radius around Tampa + residential type.
        $this->seedBuyerSubject(8001, [
            'workflow_type'             => 'offer_listing',
            'leasing_55_plus'           => 'No',
            'property_type'             => 'residential',
            'location_dna_preferences'  => json_encode([
                'radius_searches' => [['lat' => 27.95, 'lng' => -82.46, 'radius_miles' => 10]],
            ]),
        ]);

        // Near Tampa — inside the radius.
        $this->seedSeller(9001, ['workflow_type' => 'offer_listing', 'property_type' => 'residential'], [
            'geocoded_lat' => 27.96, 'geocoded_lng' => -82.47,
        ]);
        // Naples — ~130 miles away, outside the radius.
        $this->seedSeller(9002, ['workflow_type' => 'offer_listing', 'property_type' => 'residential'], [
            'geocoded_lat' => 26.14, 'geocoded_lng' => -81.79,
        ]);

        $out = app(CandidateDiscoveryService::class)
            ->discover('buyer_agent', 8001, MatchDirection::DemandToListings)
            ->toArray();

        $this->assertSame([['listing_type' => 'seller_agent', 'listing_id' => 9001]], $out);
    }

    public function test_discovery_with_narrowing_is_read_only(): void
    {
        config(['matching.v2_enabled' => true]);
        config(['matching.candidate_discovery.hard_filters_enabled' => true]);

        $this->seedBuyerSubject(8001, ['workflow_type' => 'offer_listing', 'leasing_55_plus' => 'No', 'property_type' => 'residential']);
        $this->seedSeller(9001, ['workflow_type' => 'offer_listing', 'leasing_55_plus' => 'No', 'property_type' => 'residential'], [
            'geocoded_lat' => 27.96, 'geocoded_lng' => -82.47, 'source_city' => 'Tampa',
        ]);

        $counts = fn () => [
            DnaScore::count(),
            SellerAgentAuction::count(),
            PropertyLocationDna::count(),
        ];
        $before = $counts();

        app(CandidateDiscoveryService::class)
            ->discover('buyer_agent', 8001, MatchDirection::DemandToListings);

        $this->assertSame($before, $counts());
    }
}
