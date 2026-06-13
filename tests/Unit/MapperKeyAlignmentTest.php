<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\AgentBidMapperService;

/**
 * P1A — Safe Mapping Defect Fix
 *
 * Verifies that AgentBidMapperService::mapFromProfile() emits the canonical
 * key names that match the declared public properties of each of the four bid
 * Livewire components.  These tests are the mechanical safety net that ensures
 * the mapper surface stays aligned with the bid form properties over time.
 *
 * Source of truth: docs/audits/AGENT_OFFER_PRESET_BID_CROSSWALK_AUDIT.md
 * Sections C.2–C.10 (EXACT, MAPPED, MUST_REVIEW, ROLE_INCONSISTENT keys).
 *
 * All tests are pure unit tests — no database required.
 */
class MapperKeyAlignmentTest extends TestCase
{
    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function mapped(): array
    {
        return AgentBidMapperService::mapFromProfile([]);
    }

    private function assertMapperKey(string $key): void
    {
        $this->assertArrayHasKey(
            $key,
            $this->mapped(),
            "AgentBidMapperService must emit '{$key}' (EXACT/MAPPED in crosswalk audit)"
        );
    }

    private function assertMapperKeyAbsent(string $key): void
    {
        $this->assertArrayNotHasKey(
            $key,
            $this->mapped(),
            "AgentBidMapperService must NOT emit '{$key}'"
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Regression: renewal_fee_flat_fee / renewal_fee_flat_free (P1A defect fix)
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function mapper_emits_renewal_fee_flat_fee_not_renewal_fee_flat_free(): void
    {
        $mapped = $this->mapped();

        $this->assertArrayHasKey('renewal_fee_flat_fee', $mapped,
            'Mapper must emit canonical key renewal_fee_flat_fee after the P1A fix');

        $this->assertArrayNotHasKey('renewal_fee_flat_free', $mapped,
            'Mapper must no longer emit the misspelled key renewal_fee_flat_free');
    }

    /** @test */
    public function renewal_fee_flat_fee_value_round_trips_through_mapper(): void
    {
        $mapped = AgentBidMapperService::mapFromProfile([
            'renewal_fee_flat_fee' => '1500',
        ]);

        $this->assertSame('1500', $mapped['renewal_fee_flat_fee'],
            'A renewal_fee_flat_fee value stored in profile_data must survive mapFromProfile()');

        $this->assertArrayNotHasKey('renewal_fee_flat_free', $mapped,
            'The old misspelled key must not appear in mapper output even when profile_data contains renewal_fee_flat_fee');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // All Roles — Shared EXACT keys (Sections C.2, C.3, C.4, C.5, C.6)
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function mapper_emits_all_agent_overview_and_credential_keys(): void
    {
        $keys = [
            'bio', 'why_hire_you', 'what_sets_you_apart', 'marketing_plan',
            'year_licensed', 'additional_details',
            'first_name', 'last_name', 'phone', 'email',
            'brokerage', 'license_no', 'nar_id',
        ];

        foreach ($keys as $key) {
            $this->assertMapperKey($key);
        }
    }

    /** @test */
    public function mapper_emits_all_media_links_and_services_keys(): void
    {
        $keys = [
            'presentation_link', 'presentation_upload_path',
            'business_card_link', 'business_card_stored_path', 'business_card_upload_path',
            'promoMaterials', 'reviews_links', 'website_link', 'social_media',
            'services', 'other_services',
        ];

        foreach ($keys as $key) {
            $this->assertMapperKey($key);
        }
    }

    /** @test */
    public function mapper_emits_all_shared_agency_agreement_keys(): void
    {
        $keys = [
            'protection_period',
            'early_termination_fee_option', 'early_termination_fee_amount',
            'agency_agreement_timeframe', 'agency_agreement_custom',
            'interested_lease_option_agreement',
            'lease_type', 'lease_value',
            'purchase_type', 'purchase_value',
        ];

        foreach ($keys as $key) {
            $this->assertMapperKey($key);
        }
    }

    /** @test */
    public function mapper_emits_all_shared_compensation_keys(): void
    {
        $keys = [
            'commission_structure',
            'purchase_fee_type', 'purchase_fee_flat', 'purchase_fee_percentage',
            'purchase_fee_percentage_combo', 'purchase_fee_flat_combo', 'purchase_fee_other',
            'retainer_fee_option', 'retainer_fee_amount', 'retainer_fee_application',
        ];

        foreach ($keys as $key) {
            $this->assertMapperKey($key);
        }
    }

    /** @test */
    public function mapper_emits_all_shared_tail_keys(): void
    {
        foreach (['brokerage_relationship', 'additional_details_broker', 'referral_fee_percent'] as $key) {
            $this->assertMapperKey($key);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Buyer / Tenant — Lease Fee Sub-fields (Section C.8)
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function mapper_emits_all_buyer_tenant_lease_fee_keys(): void
    {
        $keys = [
            'interested_lease_option',
            'lease_fee_type', 'lease_fee_flat', 'lease_fee_percentage',
            'lease_fee_percentage_monthly_rent', 'lease_fee_percentage_monthly_number',
            'lease_fee_flat_combo', 'lease_fee_percentage_combo',
            'lease_fee_percentage_net', 'lease_fee_flat_combo_net', 'lease_fee_percentage_combo_net',
            'lease_fee_other',
        ];

        foreach ($keys as $key) {
            $this->assertMapperKey($key);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Seller-Specific Keys (Section C.9)
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function mapper_emits_all_seller_specific_keys(): void
    {
        $keys = [
            'nominal', 'commission_structure_type',
            'commission_structure_type_fee_flat', 'commission_structure_type_fee_flat_combo',
            'commission_structure_type_fee_percentage', 'commission_structure_type_fee_percentage_combo',
            'commission_structure_type_fee_other',
            'interested_purchase_fee_type',
            'seller_leasing_fee_type',
            'seller_leasing_gross', 'seller_leasing_gross_rental', 'seller_leasing_gross_month_rent',
            'seller_leasing_gross_other', 'seller_leasing_gross_percentage',
            'seller_leasing_gross_purchase_fee_flat_amount', 'seller_leasing_gross_purchase_fee_other',
            'seller_leasing_each_rental', 'seller_leasing_gross_no_of_months',
            'seller_leasing_gross_flat_combo', 'seller_leasing_gross_percentage_combo',
            'seller_leasing_gross_flat_net_combo', 'seller_leasing_gross_percentage_net_combo',
            'seller_leasing_gross_sales_tax_option_gross', 'seller_leasing_gross_sales_tax_first_month',
            'seller_leasing_gross_sales_tax_flat_free_gross',
            'retained_deposits', 'sales_tax_option_gross',
        ];

        foreach ($keys as $key) {
            $this->assertMapperKey($key);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Landlord-Specific Keys (Section C.10)
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function mapper_emits_all_landlord_specific_keys(): void
    {
        $keys = [
            // Residential lease fee
            'purchase_fee_rental_period',
            // Commercial lease fee
            'purchase_fee_net_aggregate', 'purchase_fee_gross_rent',
            'purchase_fee_monthly_percentage', 'purchase_fee_months',
            'sales_tax_option_monthly', 'purchase_fee_flat_commercial',
            'sales_tax_option_flat', 'purchase_fee_other_commercial', 'purchase_fee_purchase_price',
            // Tenant broker commission (residential)
            'tenant_broker_commission_structure', 'tenant_broker_fee_structure',
            'tenant_broker_percentage', 'tenant_broker_gross_lease',
            'tenant_broker_first_month_rent', 'tenant_broker_flat_fee', 'tenant_broker_other',
            // Broker fee timing (shared with Tenant)
            'broker_fee_timing', 'broker_fee_days_from_rent',
            'broker_fee_days_after_lease', 'broker_fee_days_after_rent', 'broker_fee_timing_other',
            // Split payment
            'split_payment_due', 'split_payment_due_other', 'broker_fee_days_after_due_event',
            // Renewal/extension fee — canonical key after P1A fix
            'renewal_fee_type', 'renewal_fee_percentage', 'renewal_fee_lease_value',
            'renewal_fee_first_month', 'renewal_fee_flat_fee', 'renewal_fee_custom',
            'renewal_fee_sales_tax_lease_value', 'renewal_fee_no_of_months',
            'renewal_fee_sales_tax_first_month', 'renewal_fee_sales_tax_flat_fee',
            // Expansion commission
            'expansion_commission_percentage',
            // Property management
            'interested_in_property_management', 'interested_in_property_management_fee',
            'interested_in_property_management_fee_gross_lease',
            'interested_in_property_management_fee_rental_periord',
            'interested_in_property_management_fee_flate_free',
            'interested_in_property_management_fee_other',
            // Interested in selling
            'interested_in_selling', 'interested_in_selling_type',
            'landlord_broker_purchase_price', 'landlord_broker_percentage_price',
            'landlord_broker_dollar_price', 'landlord_broker_flate_fee', 'landlord_broker_other',
        ];

        foreach ($keys as $key) {
            $this->assertMapperKey($key);
        }
    }

    /** @test */
    public function mapper_landlord_section_does_not_emit_misspelled_renewal_flat_free(): void
    {
        $this->assertMapperKeyAbsent('renewal_fee_flat_free');
    }
}
