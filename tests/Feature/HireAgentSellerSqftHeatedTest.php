<?php

namespace Tests\Feature;

use App\Http\Livewire\TenantAgentAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A4.28 — Hire Seller's Agent income property unit configuration must expose a
 * per-unit "SqFt Heated" field, matching Create Seller Listing (same label +
 * placeholder). The Hire flow is served by the TenantAgentAuction component
 * (user_type = seller), which @includes the hire-seller property-preferences tab.
 */
class HireAgentSellerSqftHeatedTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hire_seller_income_unit_config_has_sqft_heated_per_unit(): void
    {
        $user = User::factory()->create(['user_type' => 'seller']);

        Livewire::actingAs($user)
            ->test(TenantAgentAuction::class, ['user_type' => 'seller'])
            ->set('property_type', 'Income')
            ->set('unit_type_configurations.0.unit_type', 'Studio')
            ->assertSee('SqFt Heated (per unit)')
            ->assertSee('Enter heated square footage per unit (e.g., 850)');
    }
}
