<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\MatchReadinessService;

/**
 * P3 — MatchReadinessService unit tests.
 *
 * Covers:
 *   - Not Ready detection (all four roles)
 *   - Quick Match Ready detection
 *   - Full Match Ready detection
 *   - Full Match supersedes Quick Match (only one state returned)
 *   - Whitespace-only and empty-array values treated as not populated
 *   - Role-specific field sets (lease_fee_type only for Buyer/Tenant)
 *   - Configuration-driven behaviour (unknown role returns not_ready gracefully)
 *   - missing_fields populated correctly in the structured result
 */
class MatchReadinessServiceTest extends TestCase
{
    // ── Helpers ──────────────────────────────────────────────────────────────

    private function sellerQuickBid(): array
    {
        return [
            'services'                => ['List on MLS'],
            'commission_structure'    => 'Percentage',
            'purchase_fee_type'       => 'percentage',
            'purchase_fee_percentage' => '3',
            'protection_period'       => '90',
            'agency_agreement_timeframe' => '6 Months',
            'brokerage_relationship'  => 'Transaction Broker',
        ];
    }

    private function sellerFullBid(): array
    {
        return array_merge($this->sellerQuickBid(), [
            'purchase_fee_flat'           => '5000',
            'early_termination_fee_option'=> 'yes',
            'retainer_fee_option'         => 'yes',
            'nominal'                     => '1',
            'commission_structure_type'   => 'Percentage',
            'seller_leasing_fee_type'     => 'Percentage of Gross Rent',
        ]);
    }

    private function buyerQuickBid(): array
    {
        return [
            'services'                => ['Search for Properties'],
            'commission_structure'    => 'Percentage',
            'purchase_fee_type'       => 'Percentage of the Total Purchase Price',
            'purchase_fee_percentage' => '3',
            'lease_fee_type'          => 'Flat Fee',
            'protection_period'       => '90',
            'agency_agreement_timeframe' => '6 Months',
            'brokerage_relationship'  => 'Single Agent',
        ];
    }

    private function buyerFullBid(): array
    {
        return array_merge($this->buyerQuickBid(), [
            'purchase_fee_flat'            => '5000',
            'lease_fee_percentage'         => '3',
            'early_termination_fee_option' => 'yes',
            'retainer_fee_option'          => 'yes',
        ]);
    }

    private function landlordQuickBid(): array
    {
        return [
            'services'                => ['Manage showings'],
            'commission_structure'    => 'Percentage',
            'purchase_fee_type'       => 'Percentage of Each Rental Period',
            'purchase_fee_percentage' => '8',
            'protection_period'       => '90',
            'agency_agreement_timeframe' => '6 Months',
            'brokerage_relationship'  => 'Transaction Broker',
        ];
    }

    private function landlordFullBid(): array
    {
        return array_merge($this->landlordQuickBid(), [
            'purchase_fee_flat'                  => '1500',
            'early_termination_fee_option'       => 'yes',
            'renewal_fee_type'                   => 'Percentage of Gross Lease',
            'broker_fee_timing'                  => 'Upon execution',
            'tenant_broker_commission_structure' => 'yes',
            'expansion_commission_percentage'    => '2',
            'interested_in_property_management'  => 'Yes',
            'interested_in_selling'              => 'No',
        ]);
    }

    private function tenantQuickBid(): array
    {
        return [
            'services'                => ['Find rental properties'],
            'commission_structure'    => 'Gross Lease Percentage',
            'purchase_fee_type'       => 'Percentage of the Total Purchase Price',
            'purchase_fee_percentage' => '3',
            'lease_fee_type'          => 'Flat Fee',
            'protection_period'       => '60',
            'agency_agreement_timeframe' => '3 Months',
            'brokerage_relationship'  => 'Single Agent',
        ];
    }

    private function tenantFullBid(): array
    {
        return array_merge($this->tenantQuickBid(), [
            'purchase_fee_flat'            => '2000',
            'lease_fee_percentage'         => '5',
            'early_termination_fee_option' => 'Yes',
            'retainer_fee_option'          => 'Yes',
            'broker_fee_timing'            => 'Upon execution of lease',
        ]);
    }

    // ── Not Ready — Seller ───────────────────────────────────────────────────

    /** @test */
    public function seller_empty_bid_is_not_ready(): void
    {
        $result = MatchReadinessService::evaluate([], 'seller');

        $this->assertSame('not_ready', $result['state']);
    }

    /** @test */
    public function seller_missing_one_quick_field_is_not_ready(): void
    {
        $bid = $this->sellerQuickBid();
        unset($bid['brokerage_relationship']);

        $result = MatchReadinessService::evaluate($bid, 'seller');

        $this->assertSame('not_ready', $result['state']);
        $this->assertContains('brokerage_relationship', $result['missing_quick']);
    }

    // ── Quick Match Ready — Seller ───────────────────────────────────────────

    /** @test */
    public function seller_with_all_quick_fields_is_quick_match_ready(): void
    {
        $result = MatchReadinessService::evaluate($this->sellerQuickBid(), 'seller');

        $this->assertSame('quick_match_ready', $result['state']);
        $this->assertEmpty($result['missing_quick']);
    }

    // ── Full Match Ready — Seller ────────────────────────────────────────────

    /** @test */
    public function seller_with_all_full_fields_is_full_match_ready(): void
    {
        $result = MatchReadinessService::evaluate($this->sellerFullBid(), 'seller');

        $this->assertSame('full_match_ready', $result['state']);
        $this->assertEmpty($result['missing_full']);
    }

    /** @test */
    public function full_match_supersedes_quick_match_for_seller(): void
    {
        $result = MatchReadinessService::evaluate($this->sellerFullBid(), 'seller');

        $this->assertSame('full_match_ready', $result['state']);
        // Quick Match fields are a subset of Full Match — missing_quick must also be empty
        $this->assertEmpty($result['missing_quick']);
    }

    // ── Not Ready — Buyer ────────────────────────────────────────────────────

    /** @test */
    public function buyer_missing_lease_fee_type_is_not_ready(): void
    {
        $bid = $this->buyerQuickBid();
        unset($bid['lease_fee_type']);

        $result = MatchReadinessService::evaluate($bid, 'buyer');

        $this->assertSame('not_ready', $result['state']);
        $this->assertContains('lease_fee_type', $result['missing_quick']);
    }

    // ── Quick Match Ready — Buyer ────────────────────────────────────────────

    /** @test */
    public function buyer_with_all_quick_fields_is_quick_match_ready(): void
    {
        $result = MatchReadinessService::evaluate($this->buyerQuickBid(), 'buyer');

        $this->assertSame('quick_match_ready', $result['state']);
    }

    // ── Full Match Ready — Buyer ─────────────────────────────────────────────

    /** @test */
    public function buyer_with_all_full_fields_is_full_match_ready(): void
    {
        $result = MatchReadinessService::evaluate($this->buyerFullBid(), 'buyer');

        $this->assertSame('full_match_ready', $result['state']);
        $this->assertEmpty($result['missing_full']);
    }

    // ── Landlord ─────────────────────────────────────────────────────────────

    /** @test */
    public function landlord_empty_bid_is_not_ready(): void
    {
        $result = MatchReadinessService::evaluate([], 'landlord');

        $this->assertSame('not_ready', $result['state']);
    }

    /** @test */
    public function landlord_quick_match_does_not_require_lease_fee_type(): void
    {
        $bid = $this->landlordQuickBid();

        $result = MatchReadinessService::evaluate($bid, 'landlord');

        // Landlord Quick Match does not include lease_fee_type
        $this->assertSame('quick_match_ready', $result['state']);
        $this->assertNotContains('lease_fee_type', $result['missing_quick']);
    }

    /** @test */
    public function landlord_with_all_full_fields_is_full_match_ready(): void
    {
        $result = MatchReadinessService::evaluate($this->landlordFullBid(), 'landlord');

        $this->assertSame('full_match_ready', $result['state']);
    }

    /** @test */
    public function landlord_missing_renewal_fee_type_is_not_full_match_ready(): void
    {
        $bid = $this->landlordFullBid();
        unset($bid['renewal_fee_type']);

        $result = MatchReadinessService::evaluate($bid, 'landlord');

        $this->assertNotSame('full_match_ready', $result['state']);
        $this->assertContains('renewal_fee_type', $result['missing_full']);
    }

    // ── Tenant ───────────────────────────────────────────────────────────────

    /** @test */
    public function tenant_with_all_quick_fields_is_quick_match_ready(): void
    {
        $result = MatchReadinessService::evaluate($this->tenantQuickBid(), 'tenant');

        $this->assertSame('quick_match_ready', $result['state']);
    }

    /** @test */
    public function tenant_with_all_full_fields_is_full_match_ready(): void
    {
        $result = MatchReadinessService::evaluate($this->tenantFullBid(), 'tenant');

        $this->assertSame('full_match_ready', $result['state']);
    }

    // ── Population normalisation ─────────────────────────────────────────────

    /** @test */
    public function whitespace_only_value_is_treated_as_not_populated(): void
    {
        $bid = $this->sellerQuickBid();
        $bid['brokerage_relationship'] = '   ';

        $result = MatchReadinessService::evaluate($bid, 'seller');

        $this->assertSame('not_ready', $result['state']);
        $this->assertContains('brokerage_relationship', $result['missing_quick']);
    }

    /** @test */
    public function empty_string_value_is_treated_as_not_populated(): void
    {
        $bid = $this->sellerQuickBid();
        $bid['commission_structure'] = '';

        $result = MatchReadinessService::evaluate($bid, 'seller');

        $this->assertSame('not_ready', $result['state']);
        $this->assertContains('commission_structure', $result['missing_quick']);
    }

    /** @test */
    public function null_value_is_treated_as_not_populated(): void
    {
        $bid = $this->sellerQuickBid();
        $bid['purchase_fee_type'] = null;

        $result = MatchReadinessService::evaluate($bid, 'seller');

        $this->assertSame('not_ready', $result['state']);
        $this->assertContains('purchase_fee_type', $result['missing_quick']);
    }

    /** @test */
    public function empty_array_services_is_treated_as_not_populated(): void
    {
        $bid = $this->sellerQuickBid();
        $bid['services'] = [];

        $result = MatchReadinessService::evaluate($bid, 'seller');

        $this->assertSame('not_ready', $result['state']);
        $this->assertContains('services', $result['missing_quick']);
    }

    /** @test */
    public function json_encoded_services_array_is_treated_as_populated(): void
    {
        $bid = $this->sellerQuickBid();
        $bid['services'] = json_encode(['List on MLS']);

        $result = MatchReadinessService::evaluate($bid, 'seller');

        $this->assertNotContains('services', $result['missing_quick']);
    }

    /** @test */
    public function json_encoded_empty_services_array_is_treated_as_not_populated(): void
    {
        $bid = $this->sellerQuickBid();
        $bid['services'] = json_encode([]);

        $result = MatchReadinessService::evaluate($bid, 'seller');

        $this->assertSame('not_ready', $result['state']);
        $this->assertContains('services', $result['missing_quick']);
    }

    // ── Role-specific field sets ─────────────────────────────────────────────

    /** @test */
    public function seller_quick_match_does_not_require_lease_fee_type(): void
    {
        $bid = $this->sellerQuickBid();
        // lease_fee_type is not a Seller Quick Match field — should not appear in missing
        $result = MatchReadinessService::evaluate($bid, 'seller');

        $this->assertNotContains('lease_fee_type', $result['missing_quick']);
    }

    /** @test */
    public function seller_full_match_requires_retainer_fee_option(): void
    {
        $bid = $this->sellerFullBid();
        unset($bid['retainer_fee_option']);

        $result = MatchReadinessService::evaluate($bid, 'seller');

        $this->assertNotSame('full_match_ready', $result['state']);
        $this->assertContains('retainer_fee_option', $result['missing_full']);
    }

    /** @test */
    public function landlord_full_match_requires_interested_in_selling(): void
    {
        $bid = $this->landlordFullBid();
        unset($bid['interested_in_selling']);

        $result = MatchReadinessService::evaluate($bid, 'landlord');

        $this->assertNotSame('full_match_ready', $result['state']);
        $this->assertContains('interested_in_selling', $result['missing_full']);
    }

    // ── Global placeholder/default normalization ─────────────────────────────

    /** @test */
    public function string_zero_purchase_fee_percentage_is_treated_as_not_populated(): void
    {
        $bid = $this->sellerQuickBid();
        $bid['purchase_fee_percentage'] = '0';

        $result = MatchReadinessService::evaluate($bid, 'seller');

        $this->assertSame('not_ready', $result['state']);
        $this->assertContains('purchase_fee_percentage', $result['missing_quick']);
    }

    /** @test */
    public function decimal_zero_purchase_fee_percentage_is_treated_as_not_populated(): void
    {
        $bid = $this->sellerQuickBid();
        $bid['purchase_fee_percentage'] = '0.00';

        $result = MatchReadinessService::evaluate($bid, 'seller');

        $this->assertSame('not_ready', $result['state']);
        $this->assertContains('purchase_fee_percentage', $result['missing_quick']);
    }

    /** @test */
    public function integer_zero_purchase_fee_percentage_is_treated_as_not_populated(): void
    {
        $bid = $this->sellerQuickBid();
        $bid['purchase_fee_percentage'] = 0;

        $result = MatchReadinessService::evaluate($bid, 'seller');

        $this->assertSame('not_ready', $result['state']);
        $this->assertContains('purchase_fee_percentage', $result['missing_quick']);
    }

    /** @test */
    public function nonzero_numeric_value_is_treated_as_populated(): void
    {
        $bid = $this->sellerQuickBid();
        $bid['purchase_fee_percentage'] = '3';

        $result = MatchReadinessService::evaluate($bid, 'seller');

        $this->assertNotContains('purchase_fee_percentage', $result['missing_quick']);
    }

    /** @test */
    public function string_zero_in_full_match_field_is_treated_as_not_populated(): void
    {
        $bid = $this->sellerFullBid();
        $bid['nominal'] = '0';

        $result = MatchReadinessService::evaluate($bid, 'seller');

        $this->assertNotSame('full_match_ready', $result['state']);
        $this->assertContains('nominal', $result['missing_full']);
    }

    // ── Configuration-driven behaviour ───────────────────────────────────────

    /** @test */
    public function unknown_role_returns_not_ready_gracefully(): void
    {
        $result = MatchReadinessService::evaluate(['services' => ['X']], 'unknown_role');

        $this->assertSame('not_ready', $result['state']);
        $this->assertEmpty($result['missing_quick']);
        $this->assertEmpty($result['missing_full']);
    }

    // ── Conditional groups ───────────────────────────────────────────────────

    /**
     * Landlord: interested_in_selling = 'Yes' → interested_in_selling_type required.
     * @test
     */
    public function landlord_interested_in_selling_yes_requires_selling_type(): void
    {
        $bid = $this->landlordFullBid();
        $bid['interested_in_selling']      = 'Yes';
        $bid['interested_in_selling_type'] = '';  // not populated

        $result = MatchReadinessService::evaluate($bid, 'landlord');

        $this->assertNotSame('full_match_ready', $result['state']);
        $this->assertContains('interested_in_selling_type', $result['missing_full']);
    }

    /**
     * Landlord: interested_in_selling = 'No' → interested_in_selling_type is NOT required.
     * @test
     */
    public function landlord_interested_in_selling_no_does_not_require_selling_type(): void
    {
        $bid = $this->landlordFullBid();
        $bid['interested_in_selling'] = 'No';
        unset($bid['interested_in_selling_type']);

        $result = MatchReadinessService::evaluate($bid, 'landlord');

        $this->assertNotContains('interested_in_selling_type', $result['missing_full']);
    }

    /**
     * Landlord: interested_in_selling = 'Yes' + type populated → no missing child.
     * @test
     */
    public function landlord_interested_in_selling_yes_with_type_is_full_match_ready(): void
    {
        $bid = $this->landlordFullBid();
        $bid['interested_in_selling']      = 'Yes';
        $bid['interested_in_selling_type'] = 'Percentage';

        $result = MatchReadinessService::evaluate($bid, 'landlord');

        $this->assertNotContains('interested_in_selling_type', $result['missing_full']);
    }

    /**
     * Landlord: broker_fee_timing = 'other' → broker_fee_timing_other required.
     * @test
     */
    public function landlord_broker_fee_timing_other_requires_timing_other_text(): void
    {
        $bid = $this->landlordFullBid();
        $bid['broker_fee_timing']       = 'other';
        $bid['broker_fee_timing_other'] = '';

        $result = MatchReadinessService::evaluate($bid, 'landlord');

        $this->assertNotSame('full_match_ready', $result['state']);
        $this->assertContains('broker_fee_timing_other', $result['missing_full']);
    }

    /**
     * Landlord: broker_fee_timing = 'other' + other text populated → no missing child.
     * @test
     */
    public function landlord_broker_fee_timing_other_populated_does_not_block_full_match(): void
    {
        $bid = $this->landlordFullBid();
        $bid['broker_fee_timing']       = 'other';
        $bid['broker_fee_timing_other'] = 'Invoice 30 days after signed lease';

        $result = MatchReadinessService::evaluate($bid, 'landlord');

        $this->assertNotContains('broker_fee_timing_other', $result['missing_full']);
    }

    /**
     * Landlord: broker_fee_timing = non-'other' → broker_fee_timing_other NOT required.
     * @test
     */
    public function landlord_broker_fee_timing_non_other_does_not_require_timing_other(): void
    {
        $bid = $this->landlordFullBid();
        $bid['broker_fee_timing'] = 'Upon execution';
        unset($bid['broker_fee_timing_other']);

        $result = MatchReadinessService::evaluate($bid, 'landlord');

        $this->assertNotContains('broker_fee_timing_other', $result['missing_full']);
    }

    /**
     * Tenant: broker_fee_timing = 'other' → broker_fee_timing_other required.
     * @test
     */
    public function tenant_broker_fee_timing_other_requires_timing_other_text(): void
    {
        $bid = $this->tenantFullBid();
        $bid['broker_fee_timing']       = 'other';
        $bid['broker_fee_timing_other'] = '';

        $result = MatchReadinessService::evaluate($bid, 'tenant');

        $this->assertNotSame('full_match_ready', $result['state']);
        $this->assertContains('broker_fee_timing_other', $result['missing_full']);
    }

    /**
     * Tenant: broker_fee_timing = 'other' + other text populated → no missing child.
     * @test
     */
    public function tenant_broker_fee_timing_other_populated_does_not_block_full_match(): void
    {
        $bid = $this->tenantFullBid();
        $bid['broker_fee_timing']       = 'other';
        $bid['broker_fee_timing_other'] = 'Custom arrangement';

        $result = MatchReadinessService::evaluate($bid, 'tenant');

        $this->assertNotContains('broker_fee_timing_other', $result['missing_full']);
    }

    /**
     * Seller has no conditional groups — conditional-group infrastructure must not
     * affect roles that don't define it.
     * @test
     */
    public function seller_has_no_conditional_groups_and_is_not_affected(): void
    {
        $result = MatchReadinessService::evaluate($this->sellerFullBid(), 'seller');

        $this->assertSame('full_match_ready', $result['state']);
    }

    // ── missing_fields return value ──────────────────────────────────────────

    /** @test */
    public function missing_fields_is_non_empty_for_not_ready_state(): void
    {
        // A completely empty bid is not_ready; missing_fields must be non-empty
        // so callers can surface actionable guidance.
        $result = MatchReadinessService::evaluate([], 'seller');

        $this->assertSame('not_ready', $result['state']);
        $this->assertNotEmpty($result['missing_fields'],
            'missing_fields must be non-empty when state is not_ready');
    }

    /** @test */
    public function missing_fields_is_non_empty_for_quick_match_ready_state(): void
    {
        // A bid at Quick Match Ready is missing Full Match fields;
        // missing_fields must be non-empty so callers know what remains.
        $result = MatchReadinessService::evaluate($this->sellerQuickBid(), 'seller');

        $this->assertSame('quick_match_ready', $result['state']);
        $this->assertNotEmpty($result['missing_fields'],
            'missing_fields must be non-empty when state is quick_match_ready');
    }

    /** @test */
    public function missing_fields_equals_missing_full_in_result(): void
    {
        $bid    = $this->sellerQuickBid(); // Quick Match only
        $result = MatchReadinessService::evaluate($bid, 'seller');

        $this->assertSame($result['missing_full'], $result['missing_fields'],
            'missing_fields must be an alias for missing_full');
    }

    /** @test */
    public function missing_fields_is_empty_when_full_match_ready(): void
    {
        $result = MatchReadinessService::evaluate($this->sellerFullBid(), 'seller');

        $this->assertEmpty($result['missing_fields']);
    }

    /** @test */
    public function missing_fields_lists_absent_full_match_fields(): void
    {
        $bid = $this->sellerQuickBid(); // no full-match-only fields

        $result = MatchReadinessService::evaluate($bid, 'seller');

        foreach (['purchase_fee_flat', 'early_termination_fee_option', 'retainer_fee_option',
                  'nominal', 'commission_structure_type', 'seller_leasing_fee_type'] as $field) {
            $this->assertContains($field, $result['missing_fields'],
                "Expected $field to appear in missing_fields for a Quick-Match-only bid");
        }
    }
}
