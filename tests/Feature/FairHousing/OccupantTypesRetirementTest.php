<?php

namespace Tests\Feature\FairHousing;

use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing;
use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListingEdit;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListingEdit;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListingEdit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Livewire;
use ReflectionClass;
use Tests\TestCase;

/**
 * Fair Housing P0-E — the control-less `occupant_types` write path is retired.
 *
 * WHAT THE DEFECT WAS. `occupant_types` and `occupant_types_tenant` had NO input control
 * anywhere in resources/ — a codebase-wide search for a `wire:model` binding returns
 * nothing. They were nonetheless public Livewire properties on Buyer and Landlord Create
 * and Edit, unconditionally persisted, hydrated back, RENDERED PUBLICLY on the landlord
 * detail page as "Occupant Type(s)" under a "Desired Tenant Criteria" heading, exported by
 * LandlordFieldMap, and surfaced through AgentController::offerListingView().
 *
 * That is the shape of the already-retired `tenant_type_preference`: a question about WHO
 * occupies a property rather than what happens there, reachable by any client, published
 * on a route with no auth. Removing the visible control had not retired it, because state
 * and persistence remained — the distinction this suite exists to keep enforcing.
 *
 * THE NAME COLLISION THAT MUST NOT BE "FIXED". Several Blades declare a LOCAL variable
 * `$occupant_types = [['name' => 'Tenant'], ['name' => 'Vacant'], ['name' => 'Occupied']]`.
 * That is an option list for `occupant_status` / `occupant_tenant` — whether the property
 * is currently occupied — which is an objective property fact and entirely legitimate.
 * Likewise `number_of_occupants`, `number_occupant` and `number_occupied` are real fields
 * with real controls. None of those were touched, and the last test here pins that so a
 * future cleanup pass does not delete a live field because it reads similarly.
 */
class OccupantTypesRetirementTest extends TestCase
{
    use DatabaseTransactions;

    private const RETIRED_KEYS = ['occupant_types', 'occupant_types_tenant'];

    private const CREATE_OFFER_COMPONENTS = [
        BuyerOfferListing::class,
        BuyerOfferListingEdit::class,
        LandlordOfferListing::class,
        LandlordOfferListingEdit::class,
        TenantOfferListing::class,
        TenantOfferListingEdit::class,
    ];

    // =====================================================================
    // Unbindable on every Create Offer component
    // =====================================================================

    /** @test */
    public function no_create_offer_component_declares_the_retired_occupant_properties(): void
    {
        foreach (self::CREATE_OFFER_COMPONENTS as $component) {
            $properties = array_map(
                fn ($p) => $p->getName(),
                (new ReflectionClass($component))->getProperties()
            );

            foreach (self::RETIRED_KEYS as $key) {
                $this->assertNotContains($key, $properties, "{$component} still declares \${$key}.");
            }
        }
    }

    /** @test */
    public function crafted_payload_cannot_set_occupant_types_on_buyer_create(): void
    {
        $this->assertCraftedSetIsRejected(BuyerOfferListing::class, 'occupant_types');
    }

    /** @test */
    public function crafted_payload_cannot_set_occupant_types_on_buyer_edit(): void
    {
        $this->assertCraftedSetIsRejected(BuyerOfferListingEdit::class, 'occupant_types');
    }

    /** @test */
    public function crafted_payload_cannot_set_occupant_types_on_landlord_create(): void
    {
        $this->assertCraftedSetIsRejected(LandlordOfferListing::class, 'occupant_types');
    }

    /** @test */
    public function crafted_payload_cannot_set_occupant_types_on_landlord_edit(): void
    {
        $this->assertCraftedSetIsRejected(LandlordOfferListingEdit::class, 'occupant_types');
    }

    /** @test */
    public function crafted_payload_cannot_set_occupant_types_tenant_on_landlord_create(): void
    {
        $this->assertCraftedSetIsRejected(LandlordOfferListing::class, 'occupant_types_tenant');
    }

    /** @test */
    public function crafted_payload_cannot_set_occupant_types_tenant_on_landlord_edit(): void
    {
        $this->assertCraftedSetIsRejected(LandlordOfferListingEdit::class, 'occupant_types_tenant');
    }

    private function assertCraftedSetIsRejected(string $component, string $property): void
    {
        $user = User::factory()->create();

        try {
            Livewire::actingAs($user)->test($component)->set($property, 'Young professionals only');
        } catch (PublicPropertyNotFoundException $e) {
            $this->assertStringContainsString($property, $e->getMessage());
            return;
        }

        $this->fail("{$component} accepted a crafted value for \${$property}.");
    }

    // =====================================================================
    // A stale stored value is inert: no persistence, render, export or read-back
    // =====================================================================

    /** @test */
    public function no_create_offer_component_persists_or_hydrates_the_retired_keys(): void
    {
        foreach (self::CREATE_OFFER_COMPONENTS as $component) {
            $file   = (new ReflectionClass($component))->getFileName();
            $source = file_get_contents($file);

            foreach (self::RETIRED_KEYS as $key) {
                $this->assertStringNotContainsString("saveMeta('{$key}'", $source);
                $this->assertStringNotContainsString("get->{$key}", $source);
            }
        }
    }

    /** @test */
    public function landlord_public_detail_view_no_longer_renders_occupant_types(): void
    {
        $blade = file_get_contents(base_path('resources/views/offer-listing/landlord/view.blade.php'));

        $this->assertStringNotContainsString("\$str('occupant_types')", $blade);
        $this->assertStringNotContainsString('Occupant Type(s)', $blade);
    }

    /** @test */
    public function buyer_public_detail_view_no_longer_falls_back_to_occupant_types(): void
    {
        // The buyer view read `$str('number_occupant') ?: $str('occupant_types')`, so a stale
        // retired value surfaced publicly whenever the legitimate field happened to be blank.
        $blade = file_get_contents(base_path('resources/views/offer-listing/buyer/view.blade.php'));

        $this->assertStringNotContainsString('occupant_types', $blade);
        $this->assertStringContainsString("\$row('Number of Occupants', \$str('number_occupant'))", $blade);
    }

    /** @test */
    public function landlord_export_map_no_longer_exposes_occupant_types_columns(): void
    {
        $source = file_get_contents(base_path('app/Exports/ListingFieldMaps/LandlordFieldMap.php'));

        $this->assertStringNotContainsString('occupant_types', $source);
    }

    /** @test */
    public function agent_controller_and_agent_view_no_longer_surface_occupant_types(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/AgentController.php'));
        $agentView  = file_get_contents(base_path('resources/views/agent/offer-listing-view.blade.php'));

        $this->assertStringNotContainsString('occupant_types', $controller);
        $this->assertStringNotContainsString('occupant_types', $agentView);
    }

    // =====================================================================
    // Adjacent, legitimate occupancy concepts are untouched
    // =====================================================================

    /** @test */
    public function legitimate_property_occupancy_fields_are_preserved(): void
    {
        // These describe the PROPERTY (is it occupied, by how many people), not who is
        // wanted in it, and each has a real control. Retiring them would be a regression.
        $landlordProperties = array_map(
            fn ($p) => $p->getName(),
            (new ReflectionClass(LandlordOfferListing::class))->getProperties()
        );

        foreach (['occupant_status', 'occupant_tenant'] as $key) {
            $this->assertContains(
                $key,
                $landlordProperties,
                "LandlordOfferListing lost \${$key}, which is an objective occupancy fact, not an occupant-type preference."
            );
        }

        $agentController = file_get_contents(base_path('app/Http/Controllers/AgentController.php'));
        foreach (['occupant_status', 'occupant_tenant', 'occupancy_status', 'occupied_until'] as $key) {
            $this->assertStringContainsString("'{$key}'", $agentController);
        }
    }
}
