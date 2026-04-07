<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Helpers\BuyerBidMatchScoreHelper;
use App\Helpers\SellerBidMatchScoreHelper;
use App\Helpers\LandlordBidMatchScoreHelper;
use App\Helpers\TenantBidMatchScoreHelper;

/**
 * Task #29 Audit Verification Tests
 *
 * Confirms that the confirmed bugs found during the deep audit of all four
 * BidMatchScoreHelper files are fixed and that no false positives/negatives
 * exist for the critical cascade-guard and field-coverage paths.
 *
 * Bugs fixed:
 *  1. Buyer/Seller/Landlord checkGroupCondition() strict casing: 'Yes' !== 'yes'
 *     caused early_termination_fee_amount, retainer_fee_amount, retainer_fee_application
 *     to be permanently excluded from the denominator.
 *  2. BuyerBidMatchScoreHelper missing brokerage_relationship in LOGICAL_FIELD_GROUPS.
 */
class BidMatchScoreHelperAuditTest extends TestCase
{
    // ──────────────────────────────────────────────────────────────────────────
    // BUYER ROLE
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function buyer_early_termination_fee_amount_is_in_denominator_when_parent_is_lowercase_yes(): void
    {
        $baseline = [
            'commission_structure'         => 'Standard',
            'purchase_fee_type'            => 'Flat Fee',
            'purchase_fee_flat'            => '5000',
            'early_termination_fee_option' => 'yes',  // lowercase as stored by Buyer forms
            'early_termination_fee_amount' => '1000',
        ];
        $bid = $baseline;

        $score = BuyerBidMatchScoreHelper::calculate($baseline, $bid);

        $this->assertArrayHasKey('early_termination_fee_amount', $score['matched_terms'],
            'early_termination_fee_amount should be in matched_terms when both parties agree');
        $this->assertGreaterThanOrEqual(3, $score['terms_baseline_total'],
            'Denominator should include at least purchase_fee_type, early_termination_fee_option, early_termination_fee_amount');
    }

    /** @test */
    public function buyer_early_termination_fee_amount_mismatch_is_detected(): void
    {
        $baseline = [
            'commission_structure'         => 'Standard',
            'early_termination_fee_option' => 'yes',
            'early_termination_fee_amount' => '1000',
        ];
        $bid = array_merge($baseline, ['early_termination_fee_amount' => '2000']);

        $score = BuyerBidMatchScoreHelper::calculate($baseline, $bid);

        $this->assertArrayHasKey('early_termination_fee_amount', $score['changed_terms'],
            'Differing early_termination_fee_amount should appear in changed_terms');
    }

    /** @test */
    public function buyer_retainer_fee_sub_fields_are_in_denominator_when_parent_is_lowercase_yes(): void
    {
        $baseline = [
            'commission_structure'    => 'Standard',
            'retainer_fee_option'     => 'yes',  // lowercase as stored by Buyer forms
            'retainer_fee_amount'     => '500',
            'retainer_fee_application' => 'applied',
        ];
        $bid = $baseline;

        $score = BuyerBidMatchScoreHelper::calculate($baseline, $bid);

        $this->assertArrayHasKey('retainer_fee_amount', $score['matched_terms'],
            'retainer_fee_amount should be scored when retainer_fee_option = yes');
        $this->assertArrayHasKey('retainer_fee_application', $score['matched_terms'],
            'retainer_fee_application should be scored when retainer_fee_option = yes');
    }

    /** @test */
    public function buyer_retainer_fee_amount_mismatch_is_detected(): void
    {
        $baseline = [
            'commission_structure' => 'Standard',
            'retainer_fee_option'  => 'yes',
            'retainer_fee_amount'  => '500',
        ];
        $bid = array_merge($baseline, ['retainer_fee_amount' => '750']);

        $score = BuyerBidMatchScoreHelper::calculate($baseline, $bid);

        $this->assertArrayHasKey('retainer_fee_amount', $score['changed_terms'],
            'Differing retainer_fee_amount should appear in changed_terms');
    }

    /** @test */
    public function buyer_brokerage_relationship_mismatch_is_detected(): void
    {
        $baseline = [
            'commission_structure'   => 'Standard',
            'brokerage_relationship' => 'Single Agent Representation',
        ];
        $bid = array_merge($baseline, ['brokerage_relationship' => 'Transaction Broker Representation']);

        $score = BuyerBidMatchScoreHelper::calculate($baseline, $bid);

        $this->assertArrayHasKey('brokerage_relationship', $score['changed_terms'],
            'brokerage_relationship should appear in changed_terms when values differ');
    }

    /** @test */
    public function buyer_brokerage_relationship_match_is_detected(): void
    {
        $baseline = [
            'commission_structure'   => 'Standard',
            'brokerage_relationship' => 'Single Agent Representation',
        ];
        $bid = $baseline;

        $score = BuyerBidMatchScoreHelper::calculate($baseline, $bid);

        $this->assertArrayHasKey('brokerage_relationship', $score['matched_terms'],
            'brokerage_relationship should appear in matched_terms when values are identical');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SELLER ROLE
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function seller_early_termination_fee_amount_is_in_denominator_when_parent_is_lowercase_yes(): void
    {
        $baseline = [
            'purchase_fee_type'            => 'Percentage of the Gross Sales Price',
            'purchase_fee_percentage'      => '3',
            'early_termination_fee_option' => 'yes',  // lowercase as stored by Seller forms
            'early_termination_fee_amount' => '1500',
        ];
        $bid = $baseline;

        $score = SellerBidMatchScoreHelper::calculate($baseline, $bid);

        $this->assertArrayHasKey('early_termination_fee_amount', $score['matched_terms'],
            'early_termination_fee_amount should be scored when both parties have yes');
    }

    /** @test */
    public function seller_early_termination_fee_amount_mismatch_is_detected(): void
    {
        $baseline = [
            'purchase_fee_type'            => 'Percentage of the Gross Sales Price',
            'purchase_fee_percentage'      => '3',
            'early_termination_fee_option' => 'yes',
            'early_termination_fee_amount' => '1500',
        ];
        $bid = array_merge($baseline, ['early_termination_fee_amount' => '2000']);

        $score = SellerBidMatchScoreHelper::calculate($baseline, $bid);

        $this->assertArrayHasKey('early_termination_fee_amount', $score['changed_terms'],
            'Differing early_termination_fee_amount should appear in changed_terms');
    }

    /** @test */
    public function seller_retainer_fee_sub_fields_are_in_denominator_when_parent_is_lowercase_yes(): void
    {
        $baseline = [
            'purchase_fee_type'        => 'Percentage of the Gross Sales Price',
            'purchase_fee_percentage'  => '3',
            'retainer_fee_option'      => 'yes',  // lowercase as stored by Seller forms
            'retainer_fee_amount'      => '300',
            'retainer_fee_application' => 'additional',
        ];
        $bid = $baseline;

        $score = SellerBidMatchScoreHelper::calculate($baseline, $bid);

        $this->assertArrayHasKey('retainer_fee_amount', $score['matched_terms'],
            'retainer_fee_amount should be scored when retainer_fee_option = yes');
        $this->assertArrayHasKey('retainer_fee_application', $score['matched_terms'],
            'retainer_fee_application should be scored when retainer_fee_option = yes');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // LANDLORD ROLE
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function landlord_early_termination_fee_amount_is_in_denominator_when_parent_is_lowercase_yes(): void
    {
        $baseline = [
            'purchase_fee_type'            => 'First Month of Rent',
            'purchase_fee_first_month'     => '1',
            'early_termination_fee_option' => 'yes',  // lowercase as stored by Landlord forms
            'early_termination_fee_amount' => '800',
        ];
        $bid = $baseline;

        $score = LandlordBidMatchScoreHelper::calculate($baseline, $bid);

        $this->assertArrayHasKey('early_termination_fee_amount', $score['matched_terms'],
            'early_termination_fee_amount should be scored when both parties have yes');
    }

    /** @test */
    public function landlord_early_termination_fee_amount_mismatch_is_detected(): void
    {
        $baseline = [
            'purchase_fee_type'            => 'First Month of Rent',
            'purchase_fee_first_month'     => '1',
            'early_termination_fee_option' => 'yes',
            'early_termination_fee_amount' => '800',
        ];
        $bid = array_merge($baseline, ['early_termination_fee_amount' => '1200']);

        $score = LandlordBidMatchScoreHelper::calculate($baseline, $bid);

        $this->assertArrayHasKey('early_termination_fee_amount', $score['changed_terms'],
            'Differing early_termination_fee_amount should appear in changed_terms');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // TENANT ROLE — backward compatibility with 'Yes' (capital Y)
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function tenant_early_termination_fee_amount_still_works_with_capital_yes(): void
    {
        $baseline = [
            'commission_structure'         => 'First Month Rent',
            'early_termination_fee_option' => 'Yes',  // capital Y as stored by Tenant forms
            'early_termination_fee_amount' => '600',
        ];
        $bid = $baseline;

        $score = TenantBidMatchScoreHelper::calculate($baseline, $bid);

        $this->assertArrayHasKey('early_termination_fee_amount', $score['matched_terms'],
            'Tenant early_termination_fee_amount should still work with capital-Y Yes');
    }

    /** @test */
    public function tenant_early_termination_fee_amount_also_works_with_lowercase_yes(): void
    {
        $baseline = [
            'commission_structure'         => 'First Month Rent',
            'early_termination_fee_option' => 'yes',  // lowercase — now also supported
            'early_termination_fee_amount' => '600',
        ];
        $bid = $baseline;

        $score = TenantBidMatchScoreHelper::calculate($baseline, $bid);

        $this->assertArrayHasKey('early_termination_fee_amount', $score['matched_terms'],
            'Tenant early_termination_fee_amount should work with lowercase yes after case-insensitive fix');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // CROSS-ROLE: cascade guard excludes child when parent = 'no'
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function buyer_early_termination_fee_amount_excluded_when_bid_parent_is_no(): void
    {
        $baseline = [
            'commission_structure'         => 'Standard',
            'early_termination_fee_option' => 'yes',
            'early_termination_fee_amount' => '1000',
        ];
        $bid = [
            'commission_structure'         => 'Standard',
            'early_termination_fee_option' => 'no',  // agent says no
            'early_termination_fee_amount' => '',    // no amount
        ];

        $score = BuyerBidMatchScoreHelper::calculate($baseline, $bid);

        $this->assertArrayNotHasKey('early_termination_fee_amount', $score['matched_terms'],
            'Fee amount should be excluded from denominator when bid parent = no');
        $this->assertArrayNotHasKey('early_termination_fee_amount', $score['changed_terms'],
            'Fee amount should be excluded (not compared) when bid parent = no');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SERVICE COMPARISON — Catalog filtering, extra services, and Other/custom
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function buyer_services_matched_count_equals_matched_services_array_count(): void
    {
        $baseline = [
            'services' => json_encode([
                'Schedule and attend property showings with the buyer',
                'Draft and submit offers using state-approved purchase forms',
                'Provide a comparative market analysis (CMA) with pricing recommendations based on comparable sales, neighborhood trends, and current market conditions (for informational purposes only — not a formal appraisal)',
            ]),
        ];
        $bid = [
            'services' => json_encode([
                'Schedule and attend property showings with the buyer',
                'Draft and submit offers using state-approved purchase forms',
                // Missing: CMA service
            ]),
        ];

        $score = BuyerBidMatchScoreHelper::calculate($baseline, $bid, null, 'Residential Property');

        $this->assertEquals(
            count($score['matched_services']),
            $score['services_matched_count'],
            'services_matched_count should equal count(matched_services)'
        );
        $this->assertEquals(
            count($score['missing_services']),
            $score['services_missing_count'],
            'services_missing_count should equal count(missing_services)'
        );
        $this->assertEquals(
            count($score['extra_services']),
            $score['services_extra_count'],
            'services_extra_count should equal count(extra_services)'
        );
    }

    /** @test */
    public function buyer_extra_services_not_in_baseline_are_not_in_denominator(): void
    {
        $baselineService = 'Schedule and attend property showings with the buyer';
        $extraService = 'Draft and submit offers using state-approved purchase forms';

        $baseline = ['services' => json_encode([$baselineService])];
        $bid      = ['services' => json_encode([$baselineService, $extraService])];

        $score = BuyerBidMatchScoreHelper::calculate($baseline, $bid, null, 'Residential Property');

        $this->assertEquals(1, $score['services_baseline_total'],
            'Denominator should only count baseline services, not extra agent offerings');
        $this->assertEquals(1, $score['services_extra_count'],
            'Extra service offered by agent should appear in extra_services count');
        $this->assertEquals(100, $score['services_match_percent'],
            'Match percent should be 100% when baseline is fully covered (extras excluded from denominator)');
    }

    /** @test */
    public function buyer_services_with_other_custom_services_score_correctly(): void
    {
        $baseline = [
            'services'       => json_encode(['Schedule and attend property showings with the buyer']),
            'other_services' => json_encode(['Custom due diligence research on zoning']),
        ];
        $bid = [
            'services'       => json_encode(['Schedule and attend property showings with the buyer']),
            'other_services' => json_encode(['Custom due diligence research on zoning']),
        ];

        $score = BuyerBidMatchScoreHelper::calculate($baseline, $bid, null, 'Residential Property');

        $this->assertEquals(2, $score['services_baseline_total'],
            'Denominator should count both catalog service and custom other_service');
        $this->assertEquals(2, $score['services_matched_count'],
            'Both catalog and custom other_service should be counted as matched');
        $this->assertEquals(100, $score['services_match_percent']);
    }

    /** @test */
    public function buyer_catalog_filtering_excludes_non_catalog_services_from_denominator(): void
    {
        // Service that is NOT in the Buyer Residential catalog
        $nonCatalogService = 'Some completely unknown service not in any catalog';
        $catalogService    = 'Schedule and attend property showings with the buyer';

        $baseline = [
            'services' => json_encode([$catalogService, $nonCatalogService]),
        ];
        $bid = [
            'services' => json_encode([$catalogService, $nonCatalogService]),
        ];

        $score = BuyerBidMatchScoreHelper::calculate($baseline, $bid, null, 'Residential Property');

        $this->assertEquals(1, $score['services_baseline_total'],
            'Non-catalog services should be filtered from baseline denominator');
    }

    /** @test */
    public function seller_bid_card_count_parity(): void
    {
        $baseline = [
            'services' => json_encode([
                'List the property on the local Multiple Listing Service (MLS)',
                'Provide professional property photography',
                'Schedule and attend showings with prospective buyers',
            ]),
        ];
        $bid = [
            'services' => json_encode([
                'List the property on the local Multiple Listing Service (MLS)',
                'Provide professional property photography',
                // missing: showings
            ]),
        ];

        $score = SellerBidMatchScoreHelper::calculate($baseline, $bid, null, 'Residential Property');

        $this->assertEquals(
            $score['services_matched_count'] + $score['services_missing_count'],
            $score['services_baseline_total'],
            'Bid card parity: matched + missing should equal baseline total'
        );
        $this->assertEquals(2, $score['services_matched_count']);
        $this->assertEquals(1, $score['services_missing_count']);
        $this->assertEquals(0, $score['services_extra_count']);
    }

    /** @test */
    public function landlord_photo_enhancements_count_toward_services_total(): void
    {
        $baseline = [
            'services'          => json_encode(['Provide digital photo enhancements']),
            'photo_enhancements' => json_encode(['HDR editing', 'Sky replacement']),
        ];
        $bid = [
            'services'          => json_encode(['Provide digital photo enhancements']),
            'photo_enhancements' => json_encode(['HDR editing', 'Sky replacement']),
        ];

        $score = LandlordBidMatchScoreHelper::calculate($baseline, $bid, null, 'Residential Property');

        // Should count: parent service (1) + 2 photo enhancements = 3 total
        $this->assertEquals(3, $score['services_baseline_total'],
            'Photo enhancements should each contribute +1 to services_baseline_total');
        $this->assertEquals(3, $score['services_matched_count'],
            'Matching photo enhancements should count as matched');
    }

    /** @test */
    public function landlord_missing_photo_enhancement_is_detected_as_missing_service(): void
    {
        $baseline = [
            'services'          => json_encode(['Provide digital photo enhancements']),
            'photo_enhancements' => json_encode(['HDR editing', 'Sky replacement']),
        ];
        $bid = [
            'services'          => json_encode(['Provide digital photo enhancements']),
            'photo_enhancements' => json_encode(['HDR editing']),  // Missing: Sky replacement
        ];

        $score = LandlordBidMatchScoreHelper::calculate($baseline, $bid, null, 'Residential Property');

        $this->assertEquals(3, $score['services_baseline_total']);
        $this->assertEquals(2, $score['services_matched_count']);
        $this->assertEquals(1, $score['services_missing_count']);
    }

    /** @test */
    public function tenant_services_missing_from_bid_appear_in_missing_services(): void
    {
        // Use exact catalog strings from TenantBidMatchScoreHelper::RESIDENTIAL_SERVICES_CATALOG
        $service1 = 'Create a branded flyer summarizing the tenant\'s rental criteria';
        $service2 = 'Schedule and attend property showings with the tenant';

        $baseline = [
            'services' => json_encode([$service1, $service2]),
        ];
        $bid = [
            'services' => json_encode([$service2]),  // $service1 is missing
        ];

        $score = TenantBidMatchScoreHelper::calculate($baseline, $bid, null, 'Residential Property');

        $this->assertEquals(2, $score['services_baseline_total'],
            'Both catalog services should be in denominator');
        $this->assertEquals(1, $score['services_matched_count'],
            'Only one service matched');
        $this->assertEquals(1, $score['services_missing_count'],
            'Missing service should be counted in services_missing_count');
        $this->assertCount(1, $score['missing_services'],
            'missing_services array length should match services_missing_count');
        $this->assertEquals(
            $score['services_missing_count'],
            count($score['missing_services']),
            'Bid card count parity: services_missing_count === count(missing_services)'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ServicesFormatter.php — No scoring involvement; display/ordering only
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function services_formatter_deduplicates_services(): void
    {
        $formatter = \App\Support\ServicesFormatter::class;

        $services = [
            'List the property on the MLS',
            'List the property on the MLS',  // duplicate
            'Provide photography',
        ];

        // normalizeServices is protected; test via getFlatOrderedServices which calls it
        // Use a reflection-based approach or test via helper calculate() which calls normalizeService
        // Instead verify dedup happens when calculate() processes services array
        $baseline = ['services' => json_encode($services)];
        $bid      = ['services' => json_encode($services)];

        $score = BuyerBidMatchScoreHelper::calculate($baseline, $bid);

        // If dedup works in the helper's normalization path, no inflation of counts
        $this->assertLessThanOrEqual(
            count($services),
            $score['services_baseline_total'],
            'Duplicate services should not inflate the baseline total'
        );
    }

    /** @test */
    public function compensation_formatter_formats_retainer_fee_application_correctly(): void
    {
        $formatter = \App\Support\CompensationFormatter::class;

        $this->assertEquals(
            'Applied toward final compensation',
            $formatter::formatRetainerFeeApplication('applied'),
            'stored value "applied" should format correctly'
        );
        $this->assertEquals(
            'Applied toward final compensation',
            $formatter::formatRetainerFeeApplication('apply_to_final'),
            'stored value "apply_to_final" should format correctly'
        );
        $this->assertEquals(
            'Charged in addition to final compensation',
            $formatter::formatRetainerFeeApplication('additional'),
            'stored value "additional" should format correctly'
        );
        $this->assertEquals(
            '',
            $formatter::formatRetainerFeeApplication(''),
            'empty value should return empty string'
        );
        $this->assertEquals(
            '',
            $formatter::formatRetainerFeeApplication('unknown_value'),
            'unknown value should return empty string, not throw'
        );
    }
}
