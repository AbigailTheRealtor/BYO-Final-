<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Helpers\SellerBidMatchScoreHelper;
use App\Helpers\BuyerBidMatchScoreHelper;
use App\Helpers\LandlordBidMatchScoreHelper;
use App\Helpers\TenantBidMatchScoreHelper;
use App\Services\AgentMatchExplanationBuilder;

/**
 * Build 4 / Phase 1 — Matching Engine Expansion Tests
 *
 * Covers:
 *   1. Config weight integrity (all six weights must sum to 100)
 *   2. Backward-compatibility: null agentProfileData produces same overall
 *      score as the legacy 50/50 formula (all four helpers)
 *   3. computeWeightedOverall — correctly applies config-driven weights
 *   4. scoreServiceArea — overlap, no-data neutral, agent-no-areas zero
 *   5. scoreExperience — cap enforcement, null fields, full-data case
 *   6. scoreAvailability — comm method match/any/mismatch, scheduling sub-component
 *   7. scoreCompatibility — always 0 (deferred)
 *   8. AgentMatchExplanationBuilder — label format and reason strings
 *   9. New return keys present on all four helpers
 */
class AgentMatchPhase1Test extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // 1. Config weight integrity
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function config_dimension_weights_sum_to_100(): void
    {
        $dims = config('match_scoring.dimensions', []);
        $this->assertNotEmpty($dims, 'match_scoring.dimensions must not be empty');

        $total = array_sum(array_column($dims, 'weight'));
        $this->assertSame(100, $total,
            "Dimension weights must sum to 100; got {$total}. " .
            "Breakdown: " . implode(', ', array_map(
                fn($k, $v) => "{$k}={$v['weight']}",
                array_keys($dims), $dims
            ))
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Backward-compatibility: null agentProfileData
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function seller_null_profile_data_produces_same_overall_as_legacy_formula(): void
    {
        $baseline = [
            'commission_structure' => 'Standard',
            'purchase_fee_type'    => 'Percentage',
            'purchase_fee_percentage' => '3',
        ];
        $bid = $baseline;

        $result = SellerBidMatchScoreHelper::calculate($baseline, $bid, null, null, null);

        // With all terms matching and no services, legacy was 100 for terms, 100 overall
        $this->assertSame(100, $result['overall_percent']);
    }

    /** @test */
    public function buyer_null_profile_data_does_not_change_overall_score(): void
    {
        $baseline = [
            'commission_structure' => 'Standard',
            'purchase_fee_type'    => 'Flat Fee',
            'purchase_fee_flat'    => '5000',
        ];
        $bid = $baseline;

        $withNull    = BuyerBidMatchScoreHelper::calculate($baseline, $bid, null, null, null);
        $withoutFifth = BuyerBidMatchScoreHelper::calculate($baseline, $bid, null, null);

        $this->assertSame($withNull['overall_percent'], $withoutFifth['overall_percent'],
            'Passing null explicitly must equal omitting the 5th argument entirely'
        );
    }

    /** @test */
    public function landlord_null_profile_data_produces_same_overall_as_legacy_formula(): void
    {
        $baseline = [
            'purchase_fee_type'       => 'Percentage',
            'purchase_fee_percentage' => '8',
        ];
        $bid = $baseline;

        $withNull = LandlordBidMatchScoreHelper::calculate($baseline, $bid, null, null, null);
        $without  = LandlordBidMatchScoreHelper::calculate($baseline, $bid, null, null);

        $this->assertSame($withNull['overall_percent'], $without['overall_percent']);
    }

    /** @test */
    public function tenant_null_profile_data_produces_same_overall_as_legacy_formula(): void
    {
        $baseline = [
            'commission_structure' => 'Standard',
            'lease_fee_type'       => 'Flat Fee',
            'lease_fee_flat'       => '2000',
        ];
        $bid = $baseline;

        $withNull = TenantBidMatchScoreHelper::calculate($baseline, $bid, null, null, null);
        $without  = TenantBidMatchScoreHelper::calculate($baseline, $bid, null, null);

        $this->assertSame($withNull['overall_percent'], $without['overall_percent']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. computeWeightedOverall — formula correctness
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function weighted_overall_with_all_new_dims_disabled_equals_50_50_split(): void
    {
        // With config services=35% enabled, terms=35% enabled, all others disabled:
        // overall = (35 * svc + 35 * terms) / 70 = (svc + terms) / 2
        $baseline = ['commission_structure' => 'Standard'];
        $bid      = ['commission_structure' => 'Standard', 'services' => ['List property on MLS']];

        $withProfile = SellerBidMatchScoreHelper::calculate($baseline, $bid, null, 'Residential Property', []);
        $withoutProfile = SellerBidMatchScoreHelper::calculate($baseline, $bid, null, 'Residential Property');

        // Both should agree since all new dims are disabled
        $this->assertSame($withProfile['overall_percent'], $withoutProfile['overall_percent']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. scoreServiceArea
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function service_area_returns_neutral_when_no_client_location_data(): void
    {
        $profileData = ['cities_served' => 'Tampa, St. Pete', 'counties_served' => 'Pinellas'];
        // Tenant with no cities/counties in baseline → neutral (50)
        $baseline = ['other_field' => 'x'];

        $result = TenantBidMatchScoreHelper::calculate($baseline, $baseline, null, null, $profileData);
        // Service area is disabled so doesn't affect overall, but sub-score should be neutral
        $this->assertSame(
            (int) config('match_scoring.service_area.no_client_location_default_score', 50),
            $result['service_area_score']
        );
    }

    /** @test */
    public function service_area_returns_zero_when_agent_has_no_served_areas(): void
    {
        $profileData = ['cities_served' => '', 'counties_served' => ''];
        // Buyer with client cities that agent doesn't serve
        $baseline = ['cities' => json_encode(['Tampa, FL']), 'counties' => json_encode(['Hillsborough County'])];

        $result = BuyerBidMatchScoreHelper::calculate($baseline, $baseline, null, null, $profileData);
        $this->assertSame(0, $result['service_area_score']);
    }

    /** @test */
    public function service_area_returns_100_when_agent_city_exactly_matches_client_city(): void
    {
        $profileData = ['cities_served' => 'Tampa', 'counties_served' => ''];
        $baseline = ['cities' => json_encode(['Tampa, FL'])];

        $result = BuyerBidMatchScoreHelper::calculate($baseline, $baseline, null, null, $profileData);
        // Client has 1 city (Tampa), agent serves Tampa → 100% overlap
        $this->assertSame(100, $result['service_area_score']);
    }

    /** @test */
    public function service_area_seller_native_columns_returns_neutral_without_resolved_names(): void
    {
        // Seller uses city_id / county_id (integer FKs — cannot resolve without DB)
        $profileData = ['cities_served' => 'Tampa', 'counties_served' => 'Hillsborough'];
        $baseline = ['city_id' => 42, 'county_id' => 7]; // integer IDs, no name strings

        $result = SellerBidMatchScoreHelper::calculate($baseline, $baseline, null, null, $profileData);
        // No city_name / county_name keys → empty client lists → neutral
        $this->assertSame(
            (int) config('match_scoring.service_area.no_client_location_default_score', 50),
            $result['service_area_score']
        );
    }

    /** @test */
    public function service_area_seller_always_returns_neutral_because_role_is_inactive(): void
    {
        // Seller is in config('match_scoring.service_area.inactive_for_roles').
        // city_id/county_id are integer FKs; resolving them requires a DB join
        // that helpers must not perform. Until an enrichment path is built, seller
        // always returns the neutral score regardless of what keys are present.
        $profileData = ['cities_served' => 'Tampa', 'counties_served' => 'Hillsborough'];
        $baseline    = ['city_name' => 'Tampa, FL', 'county_name' => 'Hillsborough County, FL'];

        $result       = SellerBidMatchScoreHelper::calculate($baseline, $baseline, null, null, $profileData);
        $neutralScore = (int) config('match_scoring.service_area.no_client_location_default_score', 50);
        $this->assertSame($neutralScore, $result['service_area_score'],
            'Seller service-area scoring is explicitly inactive — must always return neutral'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. scoreExperience
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function experience_score_is_zero_when_profile_has_no_year_licensed(): void
    {
        $profileData = [];
        $result = SellerBidMatchScoreHelper::calculate([], [], null, null, $profileData);
        $this->assertSame(0, $result['experience_score']);
    }

    /** @test */
    public function experience_score_reaches_70_percent_with_20_plus_years_no_transactions(): void
    {
        // 20+ years → yearsScore = 1.0 × 0.70 weight; 0 transactions → txnScore = 0
        // experience_score = round(1.0 * 0.70 * 100) = 70
        $profileData = ['year_licensed' => (string)(date('Y') - 25)]; // 25 years ago → capped at 20
        $result = SellerBidMatchScoreHelper::calculate([], [], null, null, $profileData);
        $this->assertSame(70, $result['experience_score']);
    }

    /** @test */
    public function experience_score_reaches_30_percent_with_full_transactions_no_years(): void
    {
        // 0 years (license in current year) + 30+ transactions → txnScore = 1.0 × 0.30 = 30
        $profileData = [
            'year_licensed'                => (string) date('Y'),
            'transactions_last_12_months'  => '35', // capped at 30
        ];
        $result = SellerBidMatchScoreHelper::calculate([], [], null, null, $profileData);
        $this->assertSame(30, $result['experience_score']);
    }

    /** @test */
    public function experience_score_is_100_with_max_years_and_max_transactions(): void
    {
        $profileData = [
            'year_licensed'               => (string)(date('Y') - 20),
            'transactions_last_12_months' => '30',
        ];
        $result = TenantBidMatchScoreHelper::calculate([], [], null, null, $profileData);
        $this->assertSame(100, $result['experience_score']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. scoreAvailability
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function availability_comm_method_exact_match_awards_full_comm_score(): void
    {
        $profileData = [
            'preferred_contact_method' => 'Email',
            'evenings_available'       => 'Yes',
            'weekends_available'       => 'Yes',
            'availability_status'      => 'Actively Taking New Clients',
        ];
        $baseline = ['client_preferred_comm_method' => 'Email'];
        $result   = TenantBidMatchScoreHelper::calculate($baseline, $baseline, null, null, $profileData);

        // commScore=100, schedScore=33+33+34=100
        // availability = 100 * 0.5 + 100 * 0.5 = 100
        $this->assertSame(100, $result['availability_score']);
    }

    /** @test */
    public function availability_comm_method_mismatch_awards_zero_comm_score(): void
    {
        $profileData = [
            'preferred_contact_method' => 'Phone Call',
            'evenings_available'       => 'No',
            'weekends_available'       => 'No',
            'availability_status'      => '',
        ];
        $baseline = ['client_preferred_comm_method' => 'Email'];
        $result   = TenantBidMatchScoreHelper::calculate($baseline, $baseline, null, null, $profileData);

        // commScore=0 (mismatch), schedScore=0
        // availability = 0 * 0.5 + 0 * 0.5 = 0
        $this->assertSame(0, $result['availability_score']);
    }

    /** @test */
    public function availability_agent_any_method_awards_80_comm_score(): void
    {
        $profileData = [
            'preferred_contact_method' => 'Any',
            'evenings_available'       => 'No',
            'weekends_available'       => 'No',
            'availability_status'      => '',
        ];
        $baseline = ['client_preferred_comm_method' => 'Email'];
        $result   = TenantBidMatchScoreHelper::calculate($baseline, $baseline, null, null, $profileData);

        // commScore=80, schedScore=0 → availability = 80 * 0.5 + 0 * 0.5 = 40
        $this->assertSame(40, $result['availability_score']);
    }

    /** @test */
    public function availability_missing_client_method_awards_neutral_comm_score(): void
    {
        $profileData = [
            'preferred_contact_method' => 'Email',
            'evenings_available'       => 'Yes',
            'weekends_available'       => 'Yes',
            'availability_status'      => 'Actively Taking New Clients',
        ];
        $baseline = []; // no client_preferred_comm_method
        $result   = TenantBidMatchScoreHelper::calculate($baseline, $baseline, null, null, $profileData);

        // commScore=80 (neutral when client missing), schedScore=100
        // availability = 80 * 0.5 + 100 * 0.5 = 90
        $this->assertSame(90, $result['availability_score']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 7. scoreCompatibility — always 0 (deferred)
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function compatibility_score_is_always_zero(): void
    {
        $profileData = ['any_data' => 'x'];
        $baseline    = ['any_data' => 'x'];

        foreach ([
            SellerBidMatchScoreHelper::class,
            BuyerBidMatchScoreHelper::class,
            LandlordBidMatchScoreHelper::class,
            TenantBidMatchScoreHelper::class,
        ] as $helperClass) {
            $result = $helperClass::calculate($baseline, $baseline, null, null, $profileData);
            $this->assertSame(0, $result['compatibility_score'],
                "{$helperClass}: compatibility_score must always be 0"
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 8. AgentMatchExplanationBuilder
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function explanation_builder_formats_label_correctly(): void
    {
        $scoreResult = ['overall_percent' => 78];
        $explanation = AgentMatchExplanationBuilder::build($scoreResult);
        $this->assertSame('78% Match', $explanation['label']);
    }

    /** @test */
    public function explanation_builder_includes_services_reason(): void
    {
        $scoreResult = [
            'overall_percent'         => 85,
            'services_baseline_total' => 3,
            'services_matched_count'  => 2,
            'services_extra_count'    => 1,
            'services_missing_count'  => 1,
            'terms_baseline_total'    => 0,
            'terms_matched_count'     => 0,
            'terms_changed_count'     => 0,
            'terms_added_count'       => 0,
        ];

        $explanation = AgentMatchExplanationBuilder::build($scoreResult);

        $this->assertIsArray($explanation['reasons']);
        $serviceReason = $explanation['reasons'][0] ?? '';
        $this->assertStringContainsString('Services:', $serviceReason);
        // Qualitative language — no raw fractions like "2/3" must appear
        $this->assertStringNotContainsString('2/3', $serviceReason);
        $this->assertStringContainsString('services', strtolower($serviceReason));
    }

    /** @test */
    public function explanation_builder_includes_experience_reason_when_dimension_enabled(): void
    {
        // Enable the experience dimension for this test only.
        config(['match_scoring.dimensions.experience.enabled' => true]);

        $scoreResult = [
            'overall_percent'         => 90,
            'services_baseline_total' => 0,
            'services_matched_count'  => 0,
            'services_extra_count'    => 0,
            'services_missing_count'  => 0,
            'terms_baseline_total'    => 0,
            'terms_matched_count'     => 0,
            'terms_changed_count'     => 0,
            'terms_added_count'       => 0,
        ];
        $profileData = ['year_licensed' => (string)(date('Y') - 10)];

        $explanation = AgentMatchExplanationBuilder::build($scoreResult, $profileData);

        $reasons   = $explanation['reasons'];
        $expReason = collect($reasons)->first(fn($r) => str_starts_with($r, 'Experience:'));
        $this->assertNotNull($expReason, 'Experience reason must appear when dimension is enabled and year_licensed is set');
        $this->assertStringContainsString('10 years as a licensed agent', $expReason);

        // Restore
        config(['match_scoring.dimensions.experience.enabled' => false]);
    }

    /** @test */
    public function explanation_builder_includes_availability_reason_when_dimension_enabled(): void
    {
        // Enable the availability dimension for this test only.
        config(['match_scoring.dimensions.availability.enabled' => true]);

        $scoreResult = [
            'overall_percent'         => 90,
            'services_baseline_total' => 0,
            'services_matched_count'  => 0,
            'services_extra_count'    => 0,
            'services_missing_count'  => 0,
            'terms_baseline_total'    => 0,
            'terms_matched_count'     => 0,
            'terms_changed_count'     => 0,
            'terms_added_count'       => 0,
        ];
        $profileData = [
            'availability_status' => 'Actively Taking New Clients',
            'evenings_available'  => 'Yes',
            'weekends_available'  => 'No',
        ];

        $explanation  = AgentMatchExplanationBuilder::build($scoreResult, $profileData);
        $reasons      = $explanation['reasons'];
        $availReason  = collect($reasons)->first(fn($r) => str_starts_with($r, 'Availability:'));

        $this->assertNotNull($availReason, 'Availability reason must appear when dimension is enabled and profile has data');
        $this->assertStringContainsString('available evenings', $availReason);

        // Restore
        config(['match_scoring.dimensions.availability.enabled' => false]);
    }

    /** @test */
    public function explanation_builder_omits_new_dimension_reasons_when_profile_is_null(): void
    {
        $scoreResult = [
            'overall_percent'         => 90,
            'services_baseline_total' => 0,
            'services_matched_count'  => 0,
            'services_extra_count'    => 0,
            'services_missing_count'  => 0,
            'terms_baseline_total'    => 0,
            'terms_matched_count'     => 0,
            'terms_changed_count'     => 0,
            'terms_added_count'       => 0,
        ];

        $explanation = AgentMatchExplanationBuilder::build($scoreResult, null);
        $reasons = $explanation['reasons'];

        foreach ($reasons as $reason) {
            $this->assertStringNotContainsString('Experience:', $reason);
            $this->assertStringNotContainsString('Availability:', $reason);
            $this->assertStringNotContainsString('Service area:', $reason);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 9. New return keys present on all four helpers
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function all_four_helpers_return_new_phase1_keys(): void
    {
        $expectedKeys = ['service_area_score', 'experience_score', 'availability_score', 'compatibility_score'];

        $helpers = [
            SellerBidMatchScoreHelper::class   => 'seller',
            BuyerBidMatchScoreHelper::class    => 'buyer',
            LandlordBidMatchScoreHelper::class => 'landlord',
            TenantBidMatchScoreHelper::class   => 'tenant',
        ];

        foreach ($helpers as $helperClass => $role) {
            $result = $helperClass::calculate([], [], null, null, null);
            foreach ($expectedKeys as $key) {
                $this->assertArrayHasKey($key, $result,
                    "{$helperClass}: missing key '{$key}' in calculate() result"
                );
                $this->assertIsInt($result[$key],
                    "{$helperClass}: '{$key}' must be an integer"
                );
            }
        }
    }

    /** @test */
    public function all_four_helpers_accept_five_arguments_without_error(): void
    {
        $profileData = [
            'year_licensed'               => (string)(date('Y') - 5),
            'transactions_last_12_months' => '10',
            'cities_served'               => 'Tampa',
            'counties_served'             => 'Hillsborough',
            'availability_status'         => 'Actively Taking New Clients',
            'evenings_available'          => 'Yes',
            'weekends_available'          => 'Yes',
            'preferred_contact_method'    => 'Email',
        ];

        $baseline = ['client_preferred_comm_method' => 'Email'];

        $this->assertIsArray(
            SellerBidMatchScoreHelper::calculate($baseline, $baseline, null, null, $profileData)
        );
        $this->assertIsArray(
            BuyerBidMatchScoreHelper::calculate($baseline, $baseline, null, null, $profileData)
        );
        $this->assertIsArray(
            LandlordBidMatchScoreHelper::calculate($baseline, $baseline, null, null, $profileData)
        );
        $this->assertIsArray(
            TenantBidMatchScoreHelper::calculate($baseline, $baseline, null, null, $profileData)
        );
    }
}
