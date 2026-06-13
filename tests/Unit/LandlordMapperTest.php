<?php

namespace Tests\Unit;

use Tests\TestCase;
use ReflectionClass;
use ReflectionProperty;
use App\Http\Livewire\Landlord\LandlordAgentAuctionBid;
use App\Services\AgentBidMapperService;

/**
 * P1A — Safe Mapping Defect Fix: Landlord role mapper ↔ component alignment.
 *
 * Each test asserts that a key the Landlord bid form reads from $mapped:
 *   (a) exists in AgentBidMapperService::mapFromProfile() output, AND
 *   (b) is declared as a public property on LandlordAgentAuctionBid.
 *
 * The regression test specifically guards the P1A fix:
 *   renewal_fee_flat_fee  must be aligned (mapper emits it, component has it).
 *   renewal_fee_flat_free must not exist on the component (old misspelling).
 *
 * All tests are pure unit tests — no database required.
 */
class LandlordMapperTest extends TestCase
{
    private array $mapped;
    private array $componentProps;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapped = AgentBidMapperService::mapFromProfile([]);

        $rc = new ReflectionClass(LandlordAgentAuctionBid::class);
        $this->componentProps = array_map(
            fn(ReflectionProperty $p) => $p->getName(),
            $rc->getProperties(ReflectionProperty::IS_PUBLIC)
        );
    }

    private function assertKeyAligns(string $key): void
    {
        $this->assertArrayHasKey($key, $this->mapped,
            "AgentBidMapperService must emit '{$key}' for Landlord bid form");
        $this->assertContains($key, $this->componentProps,
            "LandlordAgentAuctionBid must declare public \${$key}");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // P1A Regression — renewal_fee_flat_fee / renewal_fee_flat_free
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function renewal_fee_flat_fee_is_aligned_after_p1a_fix(): void
    {
        $this->assertKeyAligns('renewal_fee_flat_fee');
    }

    /** @test */
    public function renewal_fee_flat_free_is_absent_from_component(): void
    {
        $this->assertNotContains('renewal_fee_flat_free', $this->componentProps,
            'LandlordAgentAuctionBid must not declare the misspelled property $renewal_fee_flat_free');
    }

    /** @test */
    public function renewal_fee_flat_free_is_absent_from_mapper_output(): void
    {
        $this->assertArrayNotHasKey('renewal_fee_flat_free', $this->mapped,
            'Mapper must not emit the misspelled key renewal_fee_flat_free');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Shared keys — present in all four role components
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function shared_agent_overview_and_credential_keys_are_aligned(): void
    {
        foreach ([
            'bio', 'why_hire_you', 'what_sets_you_apart', 'marketing_plan',
            'year_licensed', 'additional_details',
            'first_name', 'last_name', 'phone', 'email',
            'brokerage', 'license_no', 'nar_id',
        ] as $key) {
            $this->assertKeyAligns($key);
        }
    }

    /** @test */
    public function shared_media_and_services_keys_are_aligned(): void
    {
        foreach ([
            'presentation_link', 'business_card_link', 'business_card_stored_path',
            'promoMaterials', 'reviews_links', 'website_link', 'social_media',
            'services', 'other_services',
        ] as $key) {
            $this->assertKeyAligns($key);
        }
    }

    /** @test */
    public function shared_agency_agreement_and_fee_keys_are_aligned(): void
    {
        foreach ([
            'protection_period',
            'early_termination_fee_option', 'early_termination_fee_amount',
            'agency_agreement_timeframe', 'agency_agreement_custom',
            'interested_lease_option_agreement',
            'lease_type', 'lease_value', 'purchase_type', 'purchase_value',
            'brokerage_relationship', 'additional_details_broker',
        ] as $key) {
            $this->assertKeyAligns($key);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Landlord-specific keys
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function landlord_purchase_fee_structure_keys_are_aligned(): void
    {
        foreach ([
            'purchase_fee_type', 'purchase_fee_flat', 'purchase_fee_rental_period',
            'purchase_fee_percentage_combo', 'purchase_fee_flat_combo',
            'purchase_fee_net_aggregate', 'purchase_fee_gross_rent',
            'purchase_fee_monthly_percentage', 'purchase_fee_months',
            'sales_tax_option_monthly', 'purchase_fee_flat_commercial',
            'sales_tax_option_flat', 'purchase_fee_other_commercial',
            'purchase_fee_purchase_price',
        ] as $key) {
            $this->assertKeyAligns($key);
        }
    }

    /** @test */
    public function landlord_tenant_broker_commission_keys_are_aligned(): void
    {
        foreach ([
            'tenant_broker_commission_structure', 'tenant_broker_fee_structure',
            'tenant_broker_percentage', 'tenant_broker_gross_lease',
            'tenant_broker_first_month_rent', 'tenant_broker_flat_fee', 'tenant_broker_other',
        ] as $key) {
            $this->assertKeyAligns($key);
        }
    }

    /** @test */
    public function landlord_broker_fee_timing_keys_are_aligned(): void
    {
        foreach ([
            'broker_fee_timing', 'broker_fee_days_from_rent',
            'broker_fee_days_after_lease', 'broker_fee_days_after_rent', 'broker_fee_timing_other',
        ] as $key) {
            $this->assertKeyAligns($key);
        }
    }

    /** @test */
    public function landlord_renewal_fee_all_sub_fields_are_aligned(): void
    {
        foreach ([
            'renewal_fee_type', 'renewal_fee_percentage', 'renewal_fee_lease_value',
            'renewal_fee_first_month', 'renewal_fee_flat_fee', 'renewal_fee_custom',
            'renewal_fee_sales_tax_lease_value', 'renewal_fee_no_of_months',
            'renewal_fee_sales_tax_first_month', 'renewal_fee_sales_tax_flat_fee',
        ] as $key) {
            $this->assertKeyAligns($key);
        }
    }

    /** @test */
    public function landlord_property_management_keys_are_aligned(): void
    {
        foreach ([
            'interested_in_property_management', 'interested_in_property_management_fee',
            'interested_in_property_management_fee_gross_lease',
            'interested_in_property_management_fee_rental_periord',
            'interested_in_property_management_fee_flate_free',
            'interested_in_property_management_fee_other',
        ] as $key) {
            $this->assertKeyAligns($key);
        }
    }

    /** @test */
    public function landlord_interested_in_selling_keys_are_aligned(): void
    {
        foreach ([
            'interested_in_selling', 'interested_in_selling_type',
            'landlord_broker_purchase_price', 'landlord_broker_percentage_price',
            'landlord_broker_dollar_price', 'landlord_broker_flate_fee', 'landlord_broker_other',
        ] as $key) {
            $this->assertKeyAligns($key);
        }
    }
}
