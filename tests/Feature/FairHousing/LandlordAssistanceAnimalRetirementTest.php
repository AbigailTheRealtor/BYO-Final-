<?php

namespace Tests\Feature\FairHousing;

use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListingEdit;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Livewire;
use ReflectionClass;
use Tests\TestCase;

/**
 * Fair Housing P0-A — the landlord may not express a preference about assistance animals.
 *
 * WHAT THE DEFECT WAS. The Blade controls for "Service Animal" and "Emotional Support
 * Animal" on the Landlord Create Offer flow were commented out, so nobody could see them.
 * The state behind them was untouched: `public $service_animal` / `public $support_animal`
 * were still declared, still hydrated from meta, and still written by an unconditional
 * `saveMeta()` on BOTH Create and Edit. Commenting out a control removes it from the page,
 * not from the component — a Livewire public property is settable by any client that can
 * reach the component, because that is the entire mechanism by which Livewire binds input.
 * So a landlord refusal to accommodate an assistance animal remained one crafted request
 * away from being persisted, exported, and read back.
 *
 * WHY THE PROPERTY MUST BE GONE RATHER THAN GUARDED. A validation rule, a `prohibited`
 * rule, or a sanitising hook would each have to be correct on every path that saves —
 * Create, Save Draft, Save Edit, and any future one. Deleting the public property makes
 * the binding itself impossible: Livewire throws before any application code runs, and
 * there is no save path left to forget. That is the same reasoning the Hire Agent
 * compatibility work used when it moved the gate to the write instead of to validation.
 *
 * ROLE SPECIFICITY IS THE POINT. A tenant or buyer saying "I have a service animal" is a
 * consumer disclosing their own accommodation need, which is legitimate and which the
 * product needs. A landlord saying "service animals: no" is a provider screening on
 * disability. The two are the same words and opposite acts, so these tests assert the
 * removal is landlord-only and would fail if someone "cleaned up" the consumer fields too.
 */
class LandlordAssistanceAnimalRetirementTest extends TestCase
{
    use DatabaseTransactions;

    /** The provider-side keys retired from the Landlord Offer Listing flow. */
    private const RETIRED_LANDLORD_KEYS = ['service_animal', 'support_animal'];

    private const LANDLORD_COMPONENTS = [
        LandlordOfferListing::class,
        LandlordOfferListingEdit::class,
    ];

    // =====================================================================
    // The property no longer exists — so no crafted payload can bind to it
    // =====================================================================

    /** @test */
    public function landlord_create_and_edit_declare_no_assistance_animal_property(): void
    {
        foreach (self::LANDLORD_COMPONENTS as $component) {
            $properties = array_map(
                fn ($p) => $p->getName(),
                (new ReflectionClass($component))->getProperties()
            );

            foreach (self::RETIRED_LANDLORD_KEYS as $key) {
                $this->assertNotContains(
                    $key,
                    $properties,
                    "{$component} still declares \${$key}. While the property exists, Livewire "
                    . 'will bind a client-supplied value to it regardless of whether any Blade renders a control.'
                );
            }
        }
    }

    /** @test */
    public function crafted_livewire_payload_cannot_set_service_animal_on_landlord_create(): void
    {
        $this->assertCraftedSetIsRejected(LandlordOfferListing::class, 'service_animal');
    }

    /** @test */
    public function crafted_livewire_payload_cannot_set_service_animal_on_landlord_edit(): void
    {
        $this->assertCraftedSetIsRejected(LandlordOfferListingEdit::class, 'service_animal');
    }

    /** @test */
    public function crafted_livewire_payload_cannot_set_support_animal_on_landlord_create(): void
    {
        $this->assertCraftedSetIsRejected(LandlordOfferListing::class, 'support_animal');
    }

    /** @test */
    public function crafted_livewire_payload_cannot_set_support_animal_on_landlord_edit(): void
    {
        $this->assertCraftedSetIsRejected(LandlordOfferListingEdit::class, 'support_animal');
    }

    /**
     * Drive the real component the way a crafted request would, and require Livewire to
     * refuse. This is the assertion that a source-level grep cannot make: it proves the
     * runtime rejects the binding, not merely that a string is absent from a file.
     */
    private function assertCraftedSetIsRejected(string $component, string $property): void
    {
        $user = User::factory()->create();

        try {
            Livewire::actingAs($user)->test($component)->set($property, 'No');
        } catch (PublicPropertyNotFoundException $e) {
            $this->assertStringContainsString($property, $e->getMessage());
            return;
        }

        $this->fail(
            "{$component} accepted a crafted value for \${$property}. A landlord-side "
            . 'assistance-animal preference must be unbindable, not merely unrendered.'
        );
    }

    // =====================================================================
    // Nothing writes, reads back, or exports the landlord-side values
    // =====================================================================

    /** @test */
    public function landlord_components_contain_no_persistence_or_hydration_for_retired_keys(): void
    {
        $files = [
            'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php',
            'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php',
        ];

        foreach ($files as $file) {
            $source = file_get_contents(base_path($file));

            foreach (self::RETIRED_LANDLORD_KEYS as $key) {
                $this->assertStringNotContainsString(
                    "saveMeta('{$key}'",
                    $source,
                    "{$file} still persists {$key}."
                );
                $this->assertStringNotContainsString(
                    "get->{$key}",
                    $source,
                    "{$file} still hydrates {$key} back out of meta."
                );
            }
        }
    }

    /** @test */
    public function landlord_export_map_no_longer_exposes_assistance_animal_columns(): void
    {
        $source = file_get_contents(base_path('app/Exports/ListingFieldMaps/LandlordFieldMap.php'));

        $this->assertStringNotContainsString("'Service Animal' => 'service_animal'", $source);
        $this->assertStringNotContainsString("'Support Animal' => 'support_animal'", $source);
    }

    /** @test */
    public function landlord_blade_contains_no_assistance_animal_control_even_commented_out(): void
    {
        // The commented-out block is deleted rather than left in place. A commented control
        // is a standing invitation to uncomment, and it was what made the live state behind
        // it look intentional.
        $blade = file_get_contents(base_path(
            'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/property-preferences.blade.php'
        ));

        foreach (self::RETIRED_LANDLORD_KEYS as $key) {
            $this->assertStringNotContainsString($key, $blade);
        }
    }

    // =====================================================================
    // The consumer-side disclosures must survive untouched
    // =====================================================================

    /** @test */
    public function tenant_keeps_its_first_person_assistance_animal_disclosures(): void
    {
        $properties = array_map(
            fn ($p) => $p->getName(),
            (new ReflectionClass(TenantOfferListing::class))->getProperties()
        );

        foreach (['service_animal', 'support_animal', 'emotional_support_animal'] as $key) {
            $this->assertContains(
                $key,
                $properties,
                "TenantOfferListing lost \${$key}. A tenant declaring their own accommodation "
                . 'need is legitimate and must keep working; only the provider-side preference was retired.'
            );
        }
    }

    /** @test */
    public function buyer_keeps_its_first_person_assistance_animal_disclosures(): void
    {
        $properties = array_map(
            fn ($p) => $p->getName(),
            (new ReflectionClass(BuyerOfferListing::class))->getProperties()
        );

        foreach (['service_animal', 'emotional_support_animal'] as $key) {
            $this->assertContains($key, $properties, "BuyerOfferListing lost \${$key}.");
        }
    }

    /** @test */
    public function tenant_can_still_set_and_keep_its_own_assistance_animal_disclosure(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(TenantOfferListing::class)
            ->set('service_animal', 'Yes');

        $this->assertSame('Yes', $component->get('service_animal'));
    }
}
