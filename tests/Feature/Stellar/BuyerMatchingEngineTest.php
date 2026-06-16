<?php

namespace Tests\Feature\Stellar;

use App\Models\BridgeProperty;
use App\Services\Stellar\Matching\BuyerMatchQueryBuilder;
use App\Services\Stellar\Matching\BuyerMatchResultBuilder;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\Matching\BuyerMatchService;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\Matching\DTO\BuyerMatchResult;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Buyer Matching Engine — Phase 1 feature tests.
 *
 * TC-01 through TC-25 as specified in
 * docs/audits/STELLAR_BUYER_MATCHING_IMPLEMENTATION_PLAN.md Section 10.
 *
 * Uses DatabaseTransactions (not RefreshDatabase) per project convention.
 * Seeds via DB::table('bridge_properties')->insertGetId() with controlled raw_json.
 */
class BuyerMatchingEngineTest extends TestCase
{
    use DatabaseTransactions;

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeService(): BuyerMatchService
    {
        return new BuyerMatchService(
            new BuyerMatchQueryBuilder(),
            new BuyerMatchScorer(),
            new BuyerMatchResultBuilder()
        );
    }

    private function makeCriteria(array $overrides = []): BuyerCriteriaPayload
    {
        return new BuyerCriteriaPayload(array_merge([
            'property_types'     => ['Residential'],
            'is_55_plus_eligible' => false,
        ], $overrides));
    }

    private function insertListing(array $overrides = []): int
    {
        $base = [
            'listing_key'             => 'TEST-' . uniqid(),
            'listing_id'              => 'LID-' . uniqid(),
            'standard_status'         => 'Active',
            'property_type'           => 'Residential',
            'list_price'              => 400000,
            'city'                    => 'Orlando',
            'state_or_province'       => 'FL',
            'postal_code'             => '32801',
            'bedrooms_total'          => 3,
            'bathrooms_total_integer' => 2,
            'living_area'             => 1800,
            'senior_community_yn'     => false,
            'raw_json'                => json_encode(['IDXParticipationYN' => true]),
            'created_at'              => now(),
            'updated_at'              => now(),
        ];

        return DB::table('bridge_properties')->insertGetId(array_merge($base, $overrides));
    }

    private function skipIfTableMissing(): void
    {
        if (!Schema::hasTable('bridge_properties')) {
            $this->markTestSkipped('bridge_properties table does not exist in this environment.');
        }
    }

    // =========================================================================
    // TC-01: Active status hard filter
    // =========================================================================

    public function test_tc01_active_status_filter_excludes_non_active(): void
    {
        $this->skipIfTableMissing();

        $activeKey  = 'TC01-ACTIVE-' . uniqid();
        $closedKey  = 'TC01-CLOSED-' . uniqid();

        $this->insertListing(['listing_key' => $activeKey, 'standard_status' => 'Active']);
        $this->insertListing(['listing_key' => $closedKey, 'standard_status' => 'Closed']);

        $results = $this->makeService()->match($this->makeCriteria());

        $keys = $results->pluck('listingKey')->all();

        $this->assertContains($activeKey, $keys, 'Active listing should be in results');
        $this->assertNotContains($closedKey, $keys, 'Closed listing must not be in results');
    }

    // =========================================================================
    // TC-02: Price ceiling hard filter
    // =========================================================================

    public function test_tc02_price_ceiling_excludes_over_budget(): void
    {
        $this->skipIfTableMissing();

        $underKey = 'TC02-UNDER-' . uniqid();
        $overKey  = 'TC02-OVER-'  . uniqid();

        $this->insertListing(['listing_key' => $underKey, 'list_price' => 350000]);
        $this->insertListing(['listing_key' => $overKey,  'list_price' => 450001]);

        $results = $this->makeService()->match($this->makeCriteria(['max_price' => 450000]));

        $keys = $results->pluck('listingKey')->all();

        $this->assertContains($underKey, $keys);
        $this->assertNotContains($overKey, $keys);
    }

    // =========================================================================
    // TC-03: Senior community gate — excludes 55+ for non-eligible buyer
    // =========================================================================

    public function test_tc03_senior_gate_excludes_55plus_for_non_eligible_buyer(): void
    {
        $this->skipIfTableMissing();

        $seniorKey    = 'TC03-SENIOR-'    . uniqid();
        $nonSeniorKey = 'TC03-NONSENIOR-' . uniqid();

        $this->insertListing(['listing_key' => $seniorKey,    'senior_community_yn' => true]);
        $this->insertListing(['listing_key' => $nonSeniorKey, 'senior_community_yn' => false]);

        $results = $this->makeService()->match($this->makeCriteria(['is_55_plus_eligible' => false]));

        $keys = $results->pluck('listingKey')->all();

        $this->assertNotContains($seniorKey,    $keys, '55+ listing must be excluded for non-eligible buyer');
        $this->assertContains($nonSeniorKey,    $keys, 'Non-senior listing must be included');
    }

    // =========================================================================
    // TC-04: Senior community gate — allows 55+ listings for eligible buyer
    // =========================================================================

    public function test_tc04_senior_gate_allows_55plus_for_eligible_buyer(): void
    {
        $this->skipIfTableMissing();

        $seniorKey    = 'TC04-SENIOR-'    . uniqid();
        $nonSeniorKey = 'TC04-NONSENIOR-' . uniqid();

        $this->insertListing(['listing_key' => $seniorKey,    'senior_community_yn' => true]);
        $this->insertListing(['listing_key' => $nonSeniorKey, 'senior_community_yn' => false]);

        $results = $this->makeService()->match($this->makeCriteria(['is_55_plus_eligible' => true]));

        $keys = $results->pluck('listingKey')->all();

        $this->assertContains($seniorKey,    $keys, '55+ listing must be included for eligible buyer');
        $this->assertContains($nonSeniorKey, $keys, 'Non-senior listing must be included');
    }

    // =========================================================================
    // TC-05: Senior community gate — NULL senior_community_yn treated as non-senior (included)
    // =========================================================================

    public function test_tc05_senior_gate_null_treated_as_non_senior(): void
    {
        $this->skipIfTableMissing();

        $nullKey = 'TC05-NULL-' . uniqid();

        $this->insertListing(['listing_key' => $nullKey, 'senior_community_yn' => null]);

        $results = $this->makeService()->match($this->makeCriteria(['is_55_plus_eligible' => false]));

        $keys = $results->pluck('listingKey')->all();

        $this->assertContains($nullKey, $keys, 'NULL senior_community_yn must not be treated as age-restricted');
    }

    // =========================================================================
    // TC-06: IDX gate — excludes non-IDX listings
    // =========================================================================

    public function test_tc06_idx_gate_excludes_non_idx_listings(): void
    {
        $this->skipIfTableMissing();

        $idxKey    = 'TC06-IDX-'    . uniqid();
        $noIdxKey  = 'TC06-NOIDX-'  . uniqid();

        $this->insertListing([
            'listing_key' => $idxKey,
            'raw_json'    => json_encode(['IDXParticipationYN' => true]),
        ]);
        $this->insertListing([
            'listing_key' => $noIdxKey,
            'raw_json'    => json_encode(['IDXParticipationYN' => false]),
        ]);

        $results = $this->makeService()->match($this->makeCriteria());

        $keys = $results->pluck('listingKey')->all();

        $this->assertContains($idxKey,   $keys, 'IDX-eligible listing must appear');
        $this->assertNotContains($noIdxKey, $keys, 'Non-IDX listing must be excluded');
    }

    // =========================================================================
    // TC-07: Bedroom minimum hard filter
    // =========================================================================

    public function test_tc07_bedroom_minimum_hard_filter(): void
    {
        $this->skipIfTableMissing();

        $threeBedroomKey = 'TC07-3BR-' . uniqid();
        $twoBedroomKey   = 'TC07-2BR-' . uniqid();

        $this->insertListing(['listing_key' => $threeBedroomKey, 'bedrooms_total' => 3]);
        $this->insertListing(['listing_key' => $twoBedroomKey,   'bedrooms_total' => 2]);

        $results = $this->makeService()->match($this->makeCriteria(['min_bedrooms' => 3]));

        $keys = $results->pluck('listingKey')->all();

        $this->assertContains($threeBedroomKey,    $keys, '3-bedroom listing must appear');
        $this->assertNotContains($twoBedroomKey,   $keys, '2-bedroom listing must be excluded');
    }

    // =========================================================================
    // TC-08: Bathroom minimum hard filter
    // =========================================================================

    public function test_tc08_bathroom_minimum_hard_filter(): void
    {
        $this->skipIfTableMissing();

        $twoBathKey = 'TC08-2BATH-' . uniqid();
        $oneBathKey = 'TC08-1BATH-' . uniqid();

        $this->insertListing(['listing_key' => $twoBathKey, 'bathrooms_total_integer' => 2]);
        $this->insertListing(['listing_key' => $oneBathKey, 'bathrooms_total_integer' => 1]);

        $results = $this->makeService()->match($this->makeCriteria(['min_bathrooms' => 2]));

        $keys = $results->pluck('listingKey')->all();

        $this->assertContains($twoBathKey,    $keys, '2-bath listing must appear');
        $this->assertNotContains($oneBathKey, $keys, '1-bath listing must be excluded');
    }

    // =========================================================================
    // TC-09: Pool scoring — buyer prefers pool, listing has pool → amenity score = 10
    // =========================================================================

    public function test_tc09_pool_scoring_match_gives_full_normalized_score(): void
    {
        $this->skipIfTableMissing();

        $key = 'TC09-POOL-' . uniqid();
        $this->insertListing([
            'listing_key'     => $key,
            'pool_private_yn' => true,
        ]);

        $results = $this->makeService()->match($this->makeCriteria(['wants_pool' => true]));

        $match = $results->first(fn(BuyerMatchResult $r) => $r->listingKey === $key);
        $this->assertNotNull($match);
        $this->assertEquals(10, $match->categoryScores['amenities'],
            'Pool match with only pool preference expressed should normalize to 10');
    }

    // =========================================================================
    // TC-10: Pool scoring — buyer prefers pool, listing has no pool → score 0, tradeoff entry
    // =========================================================================

    public function test_tc10_pool_scoring_mismatch_gives_zero_and_tradeoff(): void
    {
        $this->skipIfTableMissing();

        $key = 'TC10-NOPOOL-' . uniqid();
        $this->insertListing([
            'listing_key'     => $key,
            'pool_private_yn' => false,
        ]);

        $results = $this->makeService()->match($this->makeCriteria(['wants_pool' => true]));

        $match = $results->first(fn(BuyerMatchResult $r) => $r->listingKey === $key);
        $this->assertNotNull($match);
        $this->assertEquals(0, $match->categoryScores['amenities'],
            'No pool when pool is preferred should give 0 amenity score');

        $tradeoffDims = array_column($match->tradeoffs, 'dimension');
        $this->assertContains('amenities', $tradeoffDims,
            'Tradeoffs must contain an amenities entry');

        $poolTradeoff = array_filter($match->tradeoffs, fn($t) => $t['dimension'] === 'amenities');
        $poolTradeoff = array_values($poolTradeoff)[0] ?? null;
        $this->assertNotNull($poolTradeoff);
        $this->assertEquals('pool_absent', $poolTradeoff['deviation']);
    }

    // =========================================================================
    // TC-11: Pool scoring — buyer has no pool preference → amenity score = 10
    // =========================================================================

    public function test_tc11_no_pool_preference_gives_full_amenity_score(): void
    {
        $this->skipIfTableMissing();

        $key = 'TC11-NOPREF-' . uniqid();
        $this->insertListing([
            'listing_key'     => $key,
            'pool_private_yn' => false,
        ]);

        $results = $this->makeService()->match($this->makeCriteria(['wants_pool' => null]));

        $match = $results->first(fn(BuyerMatchResult $r) => $r->listingKey === $key);
        $this->assertNotNull($match);
        $this->assertEquals(10, $match->categoryScores['amenities'],
            'No amenity preferences expressed → full 10 points');
    }

    // =========================================================================
    // TC-12: CDD caution flag — cdd_yn = true
    // =========================================================================

    public function test_tc12_cdd_present_adds_caution_flag_without_exclusion(): void
    {
        $this->skipIfTableMissing();

        $cddKey    = 'TC12-CDD-'    . uniqid();
        $noCddKey  = 'TC12-NOCDD-'  . uniqid();

        $this->insertListing(['listing_key' => $cddKey,   'cdd_yn' => true]);
        $this->insertListing(['listing_key' => $noCddKey, 'cdd_yn' => false]);

        $results = $this->makeService()->match($this->makeCriteria());

        $cddMatch   = $results->first(fn(BuyerMatchResult $r) => $r->listingKey === $cddKey);
        $noCddMatch = $results->first(fn(BuyerMatchResult $r) => $r->listingKey === $noCddKey);

        $this->assertNotNull($cddMatch,   'CDD listing must appear in results');
        $this->assertNotNull($noCddMatch, 'Non-CDD listing must appear in results');

        $cddFlagTypes   = array_column($cddMatch->cautionFlags,   'type');
        $noCddFlagTypes = array_column($noCddMatch->cautionFlags, 'type');

        $this->assertContains('cdd_present',    $cddFlagTypes,   'cdd_yn=true must produce cdd_present caution flag');
        $this->assertNotContains('cdd_present', $noCddFlagTypes, 'Non-CDD listing must not have cdd_present flag');

        // Score must not differ purely due to CDD presence
        $this->assertEquals($cddMatch->totalScore, $noCddMatch->totalScore,
            'CDD flag must not affect total score');
    }

    // =========================================================================
    // TC-13: CDD null handling — included with cdd_status_unknown flag
    // =========================================================================

    public function test_tc13_cdd_null_included_with_cdd_status_unknown_flag(): void
    {
        $this->skipIfTableMissing();

        $key = 'TC13-CDDNULL-' . uniqid();
        $this->insertListing(['listing_key' => $key, 'cdd_yn' => null]);

        $results = $this->makeService()->match($this->makeCriteria(['cdd_preference' => 'none']));

        $match = $results->first(fn(BuyerMatchResult $r) => $r->listingKey === $key);
        $this->assertNotNull($match, 'Listing with null cdd_yn must appear in results');

        $flagTypes = array_column($match->cautionFlags, 'type');
        $this->assertContains('cdd_status_unknown', $flagTypes,
            'null cdd_yn must produce cdd_status_unknown caution flag');
    }

    // =========================================================================
    // TC-14: association_fee null — included with neutral score and missing_data entry
    // =========================================================================

    public function test_tc14_association_fee_null_included_with_missing_data_entry(): void
    {
        $this->skipIfTableMissing();

        $key = 'TC14-HOAFEE-' . uniqid();
        $this->insertListing([
            'listing_key'     => $key,
            'association_fee' => null,
            'association_yn'  => true,
        ]);

        $results = $this->makeService()->match($this->makeCriteria(['max_monthly_hoa' => 200]));

        $match = $results->first(fn(BuyerMatchResult $r) => $r->listingKey === $key);
        $this->assertNotNull($match, 'Listing with null association_fee must appear in results');

        $missingFields = array_column($match->missingData, 'field');
        $this->assertContains('AssociationFee', $missingFields,
            'missing_data must include AssociationFee when null and association_yn=true');
    }

    // =========================================================================
    // TC-15: Haversine proximity — closer listing scores higher
    // =========================================================================

    public function test_tc15_haversine_closer_listing_scores_higher(): void
    {
        $this->skipIfTableMissing();

        $nearKey = 'TC15-NEAR-' . uniqid();
        $farKey  = 'TC15-FAR-'  . uniqid();

        // Near: exactly at center
        $this->insertListing([
            'listing_key' => $nearKey,
            'latitude'    => 28.35,
            'longitude'   => -81.24,
        ]);

        // Far: ~45 miles away (within 50-mile radius)
        $this->insertListing([
            'listing_key' => $farKey,
            'latitude'    => 28.90,
            'longitude'   => -81.60,
        ]);

        $results = $this->makeService()->match($this->makeCriteria([
            'radius_searches' => [[
                'center'       => ['lat' => 28.35, 'lng' => -81.24],
                'radius_miles' => 50,
            ]],
        ]));

        $near = $results->first(fn(BuyerMatchResult $r) => $r->listingKey === $nearKey);
        $far  = $results->first(fn(BuyerMatchResult $r) => $r->listingKey === $farKey);

        $this->assertNotNull($near, 'Near listing must be in results');
        $this->assertNotNull($far,  'Far listing must be in results');

        // Explicit form: near location score (actual) must be strictly greater than
        // far location score (expected lower bound). This is equivalent to
        // assertGreaterThan($lowerBound, $actual) but written in a way that cannot
        // be silently satisfied if the two values happen to be equal or reversed.
        $nearLocationScore = $near->categoryScores['location'];
        $farLocationScore  = $far->categoryScores['location'];

        $this->assertGreaterThan(
            $farLocationScore,
            $nearLocationScore,
            sprintf(
                'Near listing (location=%d) must outscore far listing (location=%d)',
                $nearLocationScore,
                $farLocationScore
            )
        );
    }

    // =========================================================================
    // TC-16: Radius hard filter — listing outside radius is excluded
    // =========================================================================

    public function test_tc16_listing_outside_radius_excluded(): void
    {
        $this->skipIfTableMissing();

        $outsideKey = 'TC16-OUTSIDE-' . uniqid();

        // ~150 miles from center (28.35, -81.24) — clearly outside 50-mile radius
        $this->insertListing([
            'listing_key' => $outsideKey,
            'latitude'    => 29.50,
            'longitude'   => -80.50,
        ]);

        $results = $this->makeService()->match($this->makeCriteria([
            'radius_searches' => [[
                'center'       => ['lat' => 28.35, 'lng' => -81.24],
                'radius_miles' => 50,
            ]],
        ]));

        $keys = $results->pluck('listingKey')->all();
        $this->assertNotContains($outsideKey, $keys,
            'Listing outside radius bounding box must be excluded');
    }

    // =========================================================================
    // TC-17: Missing latitude — ZIP fallback and reduced_confidence_geo_match flag
    // =========================================================================

    public function test_tc17_missing_lat_lng_zip_fallback_and_reduced_confidence_flag(): void
    {
        $this->skipIfTableMissing();

        $key = 'TC17-NOLAT-' . uniqid();
        $this->insertListing([
            'listing_key' => $key,
            'latitude'    => null,
            'longitude'   => null,
            'postal_code' => '32827',
        ]);

        $results = $this->makeService()->match($this->makeCriteria([
            'preferred_zip_codes' => ['32827'],
        ]));

        $match = $results->first(fn(BuyerMatchResult $r) => $r->listingKey === $key);
        $this->assertNotNull($match, 'ZIP-matched listing with null lat/lng must appear in results');

        // Proximity sub-score is 0 (no Haversine possible); city/ZIP exact match sub-score = 6.
        // Total location score = 6 (only ZIP match; no radius proximity).
        $this->assertEquals(0, $match->categoryScores['location'] - 6 + 6 - 6,
            'Null lat/lng → location proximity score is 0; only ZIP-match sub-score (6) applies');
        // Assert the proximity portion is 0 by checking that total <= 6
        $this->assertLessThanOrEqual(6, $match->categoryScores['location'],
            'Without lat/lng, location score must not include any Haversine proximity points');

        $flagTypes = array_column($match->cautionFlags, 'type');
        $this->assertContains('reduced_confidence_geo_match', $flagTypes,
            'null lat/lng must produce reduced_confidence_geo_match caution flag');
    }

    // =========================================================================
    // TC-18: Price scoring — ideal price proximity decay
    // =========================================================================

    public function test_tc18_price_proximity_decay_with_ideal_price(): void
    {
        $this->skipIfTableMissing();

        $key = 'TC18-PRICE-' . uniqid();
        $this->insertListing([
            'listing_key' => $key,
            'list_price'  => 380000,
        ]);

        $results = $this->makeService()->match($this->makeCriteria([
            'ideal_price' => 400000,
            'max_price'   => 450000,
        ]));

        $match = $results->first(fn(BuyerMatchResult $r) => $r->listingKey === $key);
        $this->assertNotNull($match);

        // 5% below ideal → score = 20 × (1 - 0.05) = 19
        $this->assertEquals(19, $match->categoryScores['price'],
            'Listing 5% below ideal should score approximately 19 price points');
    }

    // =========================================================================
    // TC-19: total_score does not exceed 100
    // =========================================================================

    public function test_tc19_total_score_within_bounds(): void
    {
        $this->skipIfTableMissing();

        $key = 'TC19-MAXSCORE-' . uniqid();
        $this->insertListing([
            'listing_key'         => $key,
            'list_price'          => 300000,
            'city'                => 'Orlando',
            'postal_code'         => '32801',
            'latitude'            => 28.35,
            'longitude'           => -81.24,
            'bedrooms_total'      => 4,
            'bathrooms_total_integer' => 3,
            'living_area'         => 2500,
            'lot_size_sqft'       => 8000,
            'year_built'          => 2020,
            'pool_private_yn'     => true,
            'garage_yn'           => true,
            'waterfront_yn'       => true,
            'water_view_yn'       => true,
            'view_yn'             => true,
            'association_fee'     => 0,
            'tax_annual_amount'   => 2400,
            'new_construction_yn' => true,
            'pets_allowed'        => 'Yes',
            'property_sub_type'   => 'Single Family Residence',
            'raw_json'            => json_encode([
                'IDXParticipationYN'  => true,
                'CommunityFeatures'   => ['Golf Course', 'Tennis Court', 'Pool', 'Playground'],
                'GreenEnergyEfficient' => ['Solar'],
            ]),
        ]);

        $results = $this->makeService()->match($this->makeCriteria([
            'ideal_price'             => 300000,
            'max_price'               => 500000,
            'preferred_cities'        => ['Orlando'],
            'preferred_zip_codes'     => ['32801'],
            'radius_searches'         => [['center' => ['lat' => 28.35, 'lng' => -81.24], 'radius_miles' => 50]],
            'property_sub_types'      => ['Single Family Residence'],
            'min_sqft'                => 2000,
            'max_sqft'                => 3000,
            'min_lot_sqft'            => 6000,
            'max_lot_sqft'            => 10000,
            'year_built_min'          => 2015,
            'year_built_max'          => 2025,
            'wants_pool'              => true,
            'wants_garage'            => true,
            'wants_waterfront'        => true,
            'wants_any_view'          => true,
            'max_monthly_total_burden' => 2000,
            'wants_pet_friendly'      => true,
            'wants_new_construction'  => true,
            'community_feature_keywords' => ['Golf', 'Tennis'],
            'wants_energy_efficient'  => true,
        ]));

        $match = $results->first(fn(BuyerMatchResult $r) => $r->listingKey === $key);
        $this->assertNotNull($match);
        $this->assertLessThanOrEqual(100, $match->totalScore, 'total_score must never exceed 100');
        $this->assertGreaterThanOrEqual(0, $match->totalScore, 'total_score must never be negative');
    }

    // =========================================================================
    // TC-20: Results sorted by total_score descending
    // =========================================================================

    public function test_tc20_results_sorted_by_total_score_descending(): void
    {
        $this->skipIfTableMissing();

        // No geographic filter — all three pass hard filters.
        // Scoring differentiation: ideal_price=300000, wants_pool=true.
        //   best:  price=300000 (at ideal → 20 pts), pool=true  (amenity=10) → highest
        //   mid:   price=400000 (33% over ideal → ~13 pts), pool=false (amenity=0) → middle
        //   worst: price=450000 (50% over ideal → 10 pts),  pool=false (amenity=0) → lowest

        $bestKey  = 'TC20-BEST-'  . uniqid();
        $midKey   = 'TC20-MID-'   . uniqid();
        $worstKey = 'TC20-WORST-' . uniqid();

        $this->insertListing([
            'listing_key'     => $bestKey,
            'list_price'      => 300000,
            'pool_private_yn' => true,
        ]);
        $this->insertListing([
            'listing_key'     => $midKey,
            'list_price'      => 400000,
            'pool_private_yn' => false,
        ]);
        $this->insertListing([
            'listing_key'     => $worstKey,
            'list_price'      => 450000,
            'pool_private_yn' => false,
        ]);

        $results = $this->makeService()->match($this->makeCriteria([
            'ideal_price' => 300000,
            'max_price'   => 500000,
            'wants_pool'  => true,
        ]));

        $resultKeys = $results->pluck('listingKey')->all();

        $bestPos  = array_search($bestKey,  $resultKeys);
        $midPos   = array_search($midKey,   $resultKeys);
        $worstPos = array_search($worstKey, $resultKeys);

        $this->assertNotFalse($bestPos,  'Best listing must be in results');
        $this->assertNotFalse($midPos,   'Mid listing must be in results');
        $this->assertNotFalse($worstPos, 'Worst listing must be in results');

        $this->assertLessThan($midPos,   $bestPos,  'Best listing must appear before mid');
        $this->assertLessThan($worstPos, $midPos,   'Mid listing must appear before worst');
    }

    // =========================================================================
    // TC-21: candidate_cap limits PHP scoring layer input
    // =========================================================================

    public function test_tc21_candidate_cap_limits_scoring_input(): void
    {
        $this->skipIfTableMissing();

        // Insert 220 active Residential listings with the same city to ensure they all match
        $prefix = 'TC21-CAP-' . uniqid() . '-';
        for ($i = 0; $i < 220; $i++) {
            DB::table('bridge_properties')->insert([
                'listing_key'             => $prefix . $i,
                'listing_id'              => 'LID-' . $prefix . $i,
                'standard_status'         => 'Active',
                'property_type'           => 'Residential',
                'list_price'              => 300000 + ($i * 100),
                'city'                    => 'CapCity',
                'state_or_province'       => 'FL',
                'postal_code'             => '99999',
                'bedrooms_total'          => 3,
                'bathrooms_total_integer' => 2,
                'living_area'             => 1800,
                'senior_community_yn'     => false,
                'raw_json'                => json_encode(['IDXParticipationYN' => true]),
                'created_at'              => now(),
                'updated_at'              => now(),
            ]);
        }

        $service = new BuyerMatchService(
            new BuyerMatchQueryBuilder(),
            new BuyerMatchScorer(),
            new BuyerMatchResultBuilder()
        );

        $results = $service->match(
            $this->makeCriteria(['preferred_cities' => ['CapCity']]),
            200
        );

        $this->assertLessThanOrEqual(200, $results->count(),
            'At most 200 results should be returned when candidate_cap is 200');
    }

    // =========================================================================
    // TC-22: why_this_matches only contains entries with score_contribution > 0
    // =========================================================================

    public function test_tc22_why_this_matches_only_positive_contributions(): void
    {
        $this->skipIfTableMissing();

        $key = 'TC22-WHY-' . uniqid();
        $this->insertListing([
            'listing_key'     => $key,
            'pool_private_yn' => false,
            'city'            => 'Orlando',
        ]);

        $results = $this->makeService()->match($this->makeCriteria([
            'wants_pool'      => true,
            'preferred_cities' => ['Orlando'],
        ]));

        $match = $results->first(fn(BuyerMatchResult $r) => $r->listingKey === $key);
        $this->assertNotNull($match);

        // Amenities score = 0 (pool wanted, not present)
        $this->assertEquals(0, $match->categoryScores['amenities']);

        // why_this_matches must NOT contain amenities
        $whyDimensions = array_column($match->whyThisMatches, 'dimension');
        $this->assertNotContains('amenities', $whyDimensions,
            'why_this_matches must not include dimensions with score_contribution = 0');

        // tradeoffs must contain amenities entry
        $tradeoffDimensions = array_column($match->tradeoffs, 'dimension');
        $this->assertContains('amenities', $tradeoffDimensions,
            'tradeoffs must include an amenities entry when pool is preferred but absent');
    }

    // =========================================================================
    // TC-23: Lifestyle community feature keyword overlap
    // =========================================================================

    public function test_tc23_lifestyle_community_feature_keyword_overlap(): void
    {
        $this->skipIfTableMissing();

        $key = 'TC23-LIFESTYLE-' . uniqid();
        $this->insertListing([
            'listing_key' => $key,
            'raw_json'    => json_encode([
                'IDXParticipationYN' => true,
                'CommunityFeatures'  => ['Golf Course', 'Tennis Court', 'Pool', 'Playground'],
            ]),
        ]);

        $results = $this->makeService()->match($this->makeCriteria([
            'community_feature_keywords' => ['Golf', 'Tennis'],
        ]));

        $match = $results->first(fn(BuyerMatchResult $r) => $r->listingKey === $key);
        $this->assertNotNull($match);

        $this->assertGreaterThanOrEqual(2, $match->categoryScores['lifestyle'],
            'At least 2 community feature keyword matches must award >= 2 lifestyle points');
    }

    // =========================================================================
    // TC-24: Stale listing caution flag
    // =========================================================================

    public function test_tc24_stale_listing_caution_flag(): void
    {
        $this->skipIfTableMissing();

        $key = 'TC24-STALE-' . uniqid();
        $this->insertListing([
            'listing_key' => $key,
            'raw_json'    => json_encode([
                'IDXParticipationYN' => true,
                'DaysOnMarket'       => 90,
            ]),
        ]);

        $results = $this->makeService()->match($this->makeCriteria());

        $match = $results->first(fn(BuyerMatchResult $r) => $r->listingKey === $key);
        $this->assertNotNull($match, 'Stale listing must appear in results');

        $flagTypes = array_column($match->cautionFlags, 'type');
        $this->assertContains('listing_stale', $flagTypes,
            'DaysOnMarket >= 60 must produce listing_stale caution flag');

        $staleFlag = array_values(array_filter($match->cautionFlags, fn($f) => $f['type'] === 'listing_stale'))[0];
        $this->assertEquals('warning', $staleFlag['severity'],
            'listing_stale flag severity must be warning');
    }

    // =========================================================================
    // TC-25: Multiple property sub-types — both sub-types matched
    // =========================================================================

    public function test_tc25_multiple_property_sub_types_both_matched(): void
    {
        $this->skipIfTableMissing();

        $sfrKey  = 'TC25-SFR-'  . uniqid();
        $condoKey = 'TC25-CONDO-' . uniqid();

        $this->insertListing([
            'listing_key'       => $sfrKey,
            'property_type'     => 'Residential',
            'property_sub_type' => 'Single Family Residence',
        ]);
        $this->insertListing([
            'listing_key'       => $condoKey,
            'property_type'     => 'Residential',
            'property_sub_type' => 'Condominium',
        ]);

        $results = $this->makeService()->match($this->makeCriteria([
            'property_sub_types' => ['Single Family Residence', 'Condominium'],
        ]));

        $keys = $results->pluck('listingKey')->all();
        $this->assertContains($sfrKey,   $keys, 'SFR listing must appear');
        $this->assertContains($condoKey, $keys, 'Condominium listing must appear');

        $sfr   = $results->first(fn(BuyerMatchResult $r) => $r->listingKey === $sfrKey);
        $condo = $results->first(fn(BuyerMatchResult $r) => $r->listingKey === $condoKey);

        $this->assertEquals(
            $sfr->categoryScores['property_type'],
            $condo->categoryScores['property_type'],
            'SFR and Condominium with matching sub-types must receive equal property_type scores'
        );
    }
}
