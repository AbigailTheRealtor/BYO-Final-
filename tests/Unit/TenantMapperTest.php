<?php

namespace Tests\Unit;

use Tests\TestCase;
use ReflectionClass;
use ReflectionProperty;
use App\Http\Livewire\Tenant\TenantAgentAuctionBid;
use App\Services\AgentBidMapperService;

/**
 * P1A — Tenant role mapper ↔ component alignment.
 *
 * Each test asserts that a key the Tenant bid form reads from $mapped:
 *   (a) exists in AgentBidMapperService::mapFromProfile() output, AND
 *   (b) is declared as a public property on TenantAgentAuctionBid.
 *
 * All tests are pure unit tests — no database required.
 */
class TenantMapperTest extends TestCase
{
    private array $mapped;
    private array $componentProps;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapped = AgentBidMapperService::mapFromProfile([]);

        $rc = new ReflectionClass(TenantAgentAuctionBid::class);
        $this->componentProps = array_map(
            fn(ReflectionProperty $p) => $p->getName(),
            $rc->getProperties(ReflectionProperty::IS_PUBLIC)
        );
    }

    private function assertKeyAligns(string $key): void
    {
        $this->assertArrayHasKey($key, $this->mapped,
            "AgentBidMapperService must emit '{$key}' for Tenant bid form");
        $this->assertContains($key, $this->componentProps,
            "TenantAgentAuctionBid must declare public \${$key}");
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
            'retainer_fee_option', 'retainer_fee_amount', 'retainer_fee_application',
            'interested_lease_option_agreement',
            'lease_type', 'lease_value', 'purchase_type', 'purchase_value',
            'brokerage_relationship', 'additional_details_broker',
        ] as $key) {
            $this->assertKeyAligns($key);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Tenant-specific keys
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function tenant_lease_fee_keys_are_aligned(): void
    {
        foreach ([
            'commission_structure',
            'lease_fee_type', 'lease_fee_flat', 'lease_fee_percentage',
            'lease_fee_percentage_monthly_rent', 'lease_fee_percentage_monthly_number',
            'lease_fee_flat_combo', 'lease_fee_percentage_combo',
            'lease_fee_percentage_net', 'lease_fee_flat_combo_net', 'lease_fee_percentage_combo_net',
            'lease_fee_other',
        ] as $key) {
            $this->assertKeyAligns($key);
        }
    }

    /** @test */
    public function tenant_purchase_fee_keys_are_aligned(): void
    {
        foreach ([
            'interested_purchase_fee_type',
            'purchase_fee_type', 'purchase_fee_flat', 'purchase_fee_percentage',
            'purchase_fee_percentage_combo', 'purchase_fee_flat_combo', 'purchase_fee_other',
        ] as $key) {
            $this->assertKeyAligns($key);
        }
    }

    /** @test */
    public function tenant_broker_fee_timing_keys_are_aligned(): void
    {
        foreach ([
            'broker_fee_timing', 'broker_fee_days_from_rent',
            'broker_fee_days_after_lease', 'broker_fee_days_after_rent', 'broker_fee_timing_other',
        ] as $key) {
            $this->assertKeyAligns($key);
        }
    }
}
