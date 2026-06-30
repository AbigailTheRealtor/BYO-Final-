<?php

namespace Tests\Feature;

use App\Http\Livewire\TenantAgentAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A3.20–A3.25 — Hire Agent flows use the shared, map-integrated address
 * component (<x-byo-address-autocomplete>) for Seller + Landlord. The component
 * renders the Street Address autocomplete input + Unit/Apt/Suite field, and the
 * Google Places place_changed handler calls fillFromGooglePlaces() (provided by
 * the HandlesGooglePlacesAddress trait) to populate City / County / State / ZIP.
 *
 * The live Hire flow is served by TenantAgentAuction (user_type = seller|landlord).
 */
class HireAddressAutocompleteTest extends TestCase
{
    use DatabaseTransactions;

    /** A3.21/A3.22: Hire Seller renders the shared map-integrated address (street autocomplete + Unit). */
    public function test_hire_seller_renders_shared_address_component(): void
    {
        $user = User::factory()->create(['user_type' => 'seller']);

        Livewire::actingAs($user)
            ->test(TenantAgentAuction::class, ['user_type' => 'seller'])
            ->assertSee('id="hire-seller-street-address"', false)
            ->assertSee('Unit / Apt / Suite');
    }

    /** A3.21/A3.22: Hire Landlord renders the shared map-integrated address (street autocomplete + Unit). */
    public function test_hire_landlord_renders_shared_address_component(): void
    {
        // The component's user_type mount param selects the landlord partial; it is
        // independent of the auth user's user_type (constrained by the users table).
        $user = User::factory()->create(['user_type' => 'seller']);

        Livewire::actingAs($user)
            ->test(TenantAgentAuction::class, ['user_type' => 'landlord'])
            ->assertSee('id="hire-landlord-street-address"', false)
            ->assertSee('Unit / Apt / Suite');
    }

    /** A3.23: fillFromGooglePlaces() auto-populates all address parts from a selected place. */
    public function test_fill_from_google_places_populates_address_parts(): void
    {
        $user = User::factory()->create(['user_type' => 'seller']);

        Livewire::actingAs($user)
            ->test(TenantAgentAuction::class, ['user_type' => 'seller'])
            ->call('fillFromGooglePlaces', '123 Main Street', 'Miami', 'Miami-Dade', 'FL', '33101', '25.76', '-80.19', 'place_abc')
            ->assertSet('address', '123 Main Street')
            ->assertSet('property_city', 'Miami')
            ->assertSet('property_county', 'Miami-Dade')
            ->assertSet('property_state', 'FL')
            ->assertSet('property_zip', '33101');
    }

    /** A3.21: Unit/Apt/Suite is a wired, settable property on the Hire flow. */
    public function test_unit_address_is_wired(): void
    {
        $user = User::factory()->create(['user_type' => 'seller']);

        Livewire::actingAs($user)
            ->test(TenantAgentAuction::class, ['user_type' => 'seller'])
            ->set('unit_address', 'Apt 4B')
            ->assertSet('unit_address', 'Apt 4B');
    }
}
