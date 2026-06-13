<?php

namespace Tests\Unit;

use Tests\TestCase;
use ReflectionClass;
use ReflectionProperty;
use App\Http\Livewire\Seller\SellerAgentAuctionBid;
use App\Services\AgentBidMapperService;

/**
 * P1A — Seller role mapper ↔ component alignment.
 *
 * Each test asserts that a key the Seller bid form reads from $mapped:
 *   (a) exists in AgentBidMapperService::mapFromProfile() output, AND
 *   (b) is declared as a public property on SellerAgentAuctionBid.
 *
 * All tests are pure unit tests — no database required.
 */
class SellerMapperTest extends TestCase
{
    private array $mapped;
    private array $componentProps;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapped = AgentBidMapperService::mapFromProfile([]);

        $rc = new ReflectionClass(SellerAgentAuctionBid::class);
        $this->componentProps = array_map(
            fn(ReflectionProperty $p) => $p->getName(),
            $rc->getProperties(ReflectionProperty::IS_PUBLIC)
        );
    }

    private function assertKeyAligns(string $key): void
    {
        $this->assertArrayHasKey($key, $this->mapped,
            "AgentBidMapperService must emit '{$key}' for Seller bid form");
        $this->assertContains($key, $this->componentProps,
            "SellerAgentAuctionBid must declare public \${$key}");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Shared keys
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
    public function shared_agency_and_tail_keys_are_aligned(): void
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
    // Seller-specific keys
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function seller_purchase_fee_and_commission_keys_are_aligned(): void
    {
        foreach ([
            'purchase_fee_type', 'purchase_fee_flat', 'purchase_fee_percentage',
            'purchase_fee_percentage_combo', 'purchase_fee_flat_combo', 'purchase_fee_other',
            'nominal',
            'commission_structure', 'commission_structure_type',
            'commission_structure_type_fee_flat', 'commission_structure_type_fee_flat_combo',
            'commission_structure_type_fee_percentage', 'commission_structure_type_fee_percentage_combo',
            'commission_structure_type_fee_other',
        ] as $key) {
            $this->assertKeyAligns($key);
        }
    }

    /** @test */
    public function seller_leasing_fee_keys_are_aligned(): void
    {
        foreach ([
            'interested_purchase_fee_type',
            'seller_leasing_fee_type',
            'seller_leasing_gross', 'seller_leasing_gross_rental', 'seller_leasing_gross_month_rent',
            'seller_leasing_gross_other', 'seller_leasing_gross_percentage',
            'seller_leasing_gross_purchase_fee_flat_amount', 'seller_leasing_gross_purchase_fee_other',
            'seller_leasing_each_rental', 'seller_leasing_gross_no_of_months',
            'seller_leasing_gross_flat_combo', 'seller_leasing_gross_percentage_combo',
            'seller_leasing_gross_flat_net_combo', 'seller_leasing_gross_percentage_net_combo',
            'seller_leasing_gross_sales_tax_first_month',
            'retained_deposits',
        ] as $key) {
            $this->assertKeyAligns($key);
        }
    }

    /** @test */
    public function referral_fee_percent_is_aligned(): void
    {
        $this->assertKeyAligns('referral_fee_percent');
    }
}
