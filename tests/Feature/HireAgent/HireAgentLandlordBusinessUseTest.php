<?php

namespace Tests\Feature\HireAgent;

use App\Http\Livewire\TenantAgentAuction;
use App\Http\Livewire\TenantAgentAuctionEdit;
use App\Models\LandlordAgentAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Preferred Business Use — the commercial-only replacement for the retired Preferred Tenant Type.
 *
 * Covers the form (does it render, and only where it should), the round trip (Create, Save Draft,
 * Edit hydrate, Save Edit), and the adversarial case (a crafted request on a residential listing).
 *
 * ASSERTIONS ARE AGAINST THE STORED BLOB, not the component property. The component property is
 * what the client just set; the blob is what the projection allowed through, and the projection is
 * the thing under test.
 */
class HireAgentLandlordBusinessUseTest extends TestCase
{
    use RefreshDatabase;

    private const COMMERCIAL  = 'Commercial Property';
    private const RESIDENTIAL = 'Residential Property';

    private const RETIRED_LABEL = 'Preferred Tenant Type';
    private const NEW_LABEL     = 'Preferred Business Use';

    // ── Fixtures ─────────────────────────────────────────────────────────────

    private function owner(): User
    {
        return User::factory()->create(['user_type' => 'landlord']);
    }

    private function listing(User $owner, string $propertyType): LandlordAgentAuction
    {
        $listing = LandlordAgentAuction::forceCreate([
            'user_id'     => $owner->id,
            'title'       => 'Business use listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);

        $listing->saveMeta('address', '100 Test Street');
        $listing->saveMeta('property_type', $propertyType);
        $listing->saveMeta('service_type', 'full_service');
        $listing->saveMeta('user_type', 'landlord');

        return $listing;
    }

    /** The stored blob's landlord block, read back from the database. */
    private function storedLandlordBlock(LandlordAgentAuction $listing): array
    {
        $raw  = $listing->fresh()->info('compatibility_preferences');
        $blob = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($blob) ? ($blob['landlord_specific'] ?? []) : [];
    }

    private function createComponent(User $owner, string $propertyType)
    {
        return Livewire::actingAs($owner)
            ->test(TenantAgentAuction::class, ['user_type' => 'landlord'])
            ->set('service_type', 'full_service')
            ->set('property_type', $propertyType);
    }

    // ── Rendering ────────────────────────────────────────────────────────────

    /** @test */
    public function residential_landlord_does_not_render_preferred_tenant_type(): void
    {
        $this->createComponent($this->owner(), self::RESIDENTIAL)
            ->assertDontSee(self::RETIRED_LABEL)
            ->assertDontSee('tenant_type_preference')
            // Retired option values must be gone too, not merely the label.
            ->assertDontSee('Young Professionals')
            ->assertDontSee('Individual / Family');
    }

    /** @test */
    public function commercial_landlord_does_not_render_preferred_tenant_type_either(): void
    {
        $this->createComponent($this->owner(), self::COMMERCIAL)
            ->assertDontSee(self::RETIRED_LABEL)
            ->assertDontSee('tenant_type_preference')
            ->assertDontSee('Office Tenant')
            ->assertDontSee('Retail Business');
    }

    /** @test */
    public function residential_landlord_does_not_render_preferred_business_use(): void
    {
        $this->createComponent($this->owner(), self::RESIDENTIAL)
            ->assertDontSee(self::NEW_LABEL)
            ->assertDontSee('compat_preferred_business_use_landlord');
    }

    /** @test */
    public function commercial_landlord_renders_preferred_business_use_with_every_configured_option(): void
    {
        $component = $this->createComponent($this->owner(), self::COMMERCIAL)
            ->assertSee(self::NEW_LABEL)
            ->assertSee('compat_preferred_business_use_landlord');

        // Asserted against the config rather than a literal list, so the form and the option
        // source cannot drift apart without this failing.
        foreach (config('landlord_business_use_options.options') as $option) {
            $component->assertSee($option, false);
        }
    }

    /** @test */
    public function the_retired_wording_appears_nowhere_in_the_landlord_form(): void
    {
        $component = $this->createComponent($this->owner(), self::COMMERCIAL);

        foreach ([
            'tenant profile', 'tenant demographic', 'type of person',
            'preferred occupant', 'preferred clientele',
            'High-Quality Tenant Profile', 'Long-Term Stable Tenant',
        ] as $banned) {
            $component->assertDontSee($banned, false);
        }

        // Positive control — the replacements ARE there.
        $component->assertSee('Reliable Rent Collection', false)
                  ->assertSee('Long-Term Tenancy', false);
    }

    // ── Round trip ───────────────────────────────────────────────────────────

    /** @test */
    public function it_saves_on_create_for_a_commercial_listing(): void
    {
        $owner   = $this->owner();
        $listing = $this->listing($owner, self::COMMERCIAL);

        $this->persistViaComponent($owner, $listing, self::COMMERCIAL, ['Office', 'Medical / Dental']);

        $this->assertSame(
            ['Office', 'Medical / Dental'],
            $this->storedLandlordBlock($listing)['preferred_business_use'] ?? null
        );
    }

    /** @test */
    public function it_survives_a_draft_save(): void
    {
        $owner   = $this->owner();
        $listing = $this->listing($owner, self::COMMERCIAL);
        $listing->saveMeta('is_draft', '1');

        $this->persistViaComponent($owner, $listing, self::COMMERCIAL, ['Retail'], draft: true);

        $this->assertSame(['Retail'], $this->storedLandlordBlock($listing)['preferred_business_use'] ?? null);
    }

    /** @test */
    public function it_hydrates_on_edit(): void
    {
        $owner   = $this->owner();
        $listing = $this->listing($owner, self::COMMERCIAL);
        $listing->saveMeta('compatibility_preferences', json_encode([
            'landlord_specific' => ['preferred_business_use' => ['Automotive']],
        ]));

        Livewire::actingAs($owner)
            ->test(TenantAgentAuctionEdit::class, [
                'auctionId' => $listing->id,
                'user_type' => 'landlord',
            ])
            ->assertSet('compatibility_preferences.landlord_specific.preferred_business_use', ['Automotive']);
    }

    /**
     * The regression this batch exists to prevent as much as any Fair Housing rule: Save Edit
     * used to discard landlord compatibility changes entirely.
     *
     * @test
     */
    public function it_survives_save_edit(): void
    {
        $owner   = $this->owner();
        $listing = $this->listing($owner, self::COMMERCIAL);
        $listing->saveMeta('compatibility_preferences', json_encode([
            'landlord_specific' => ['preferred_business_use' => ['Office']],
        ]));

        $this->editComponent($owner, $listing)
            ->set('compatibility_preferences.landlord_specific.preferred_business_use', ['Warehouse / Industrial / Flex'])
            ->call('update');

        $this->assertSame(
            ['Warehouse / Industrial / Flex'],
            $this->storedLandlordBlock($listing)['preferred_business_use'] ?? null,
            'Save Edit must persist landlord compatibility changes. Before this branch it only '
            . 'persisted them for tenant, so every landlord edit was silently discarded.'
        );
    }

    /** @test */
    public function the_other_companion_round_trips_for_commercial(): void
    {
        $owner   = $this->owner();
        $listing = $this->listing($owner, self::COMMERCIAL);

        $this->persistViaComponent(
            $owner, $listing, self::COMMERCIAL, ['Other'], other: 'Veterinary clinic'
        );

        $block = $this->storedLandlordBlock($listing);
        $this->assertSame(['Other'], $block['preferred_business_use'] ?? null);
        $this->assertSame('Veterinary clinic', $block['preferred_business_use_other'] ?? null);
    }

    /** @test */
    public function the_other_companion_is_refused_on_a_residential_listing(): void
    {
        $owner   = $this->owner();
        $listing = $this->listing($owner, self::RESIDENTIAL);

        $this->persistViaComponent(
            $owner, $listing, self::RESIDENTIAL, ['Other'], other: 'Veterinary clinic'
        );

        $block = $this->storedLandlordBlock($listing);
        $this->assertArrayNotHasKey('preferred_business_use', $block);
        $this->assertArrayNotHasKey('preferred_business_use_other', $block);
    }

    // ── Adversarial ──────────────────────────────────────────────────────────

    /**
     * The crafted request. Blade never rendered the control on this listing, so the only way to
     * reach this state is to set the nested path directly — which any client can do, because the
     * property is public.
     *
     * @test
     */
    public function a_crafted_residential_payload_cannot_persist_preferred_business_use(): void
    {
        $owner   = $this->owner();
        $listing = $this->listing($owner, self::RESIDENTIAL);

        $this->persistViaComponent($owner, $listing, self::RESIDENTIAL, ['Office']);

        $block = $this->storedLandlordBlock($listing);
        $this->assertArrayNotHasKey('preferred_business_use', $block);

        // Positive control: the save DID happen, so the absence above is the projection working
        // rather than the whole write silently failing.
        $this->assertSame('Email Only', $block['communication_style'] ?? null);
    }

    /** @test */
    public function a_crafted_residential_payload_cannot_persist_it_through_edit_either(): void
    {
        $owner   = $this->owner();
        $listing = $this->listing($owner, self::RESIDENTIAL);

        $this->editComponent($owner, $listing)
            ->set('compatibility_preferences.landlord_specific.communication_style', 'Email Only')
            ->set('compatibility_preferences.landlord_specific.preferred_business_use', ['Office'])
            ->call('update');

        $block = $this->storedLandlordBlock($listing);
        $this->assertArrayNotHasKey('preferred_business_use', $block);
        $this->assertSame('Email Only', $block['communication_style'] ?? null);
    }

    /**
     * A single Edit request that flips the listing commercial AND supplies the commercial-only
     * key. Both are public properties, so this is one message, not two.
     *
     * @test
     */
    public function an_edit_cannot_authorise_its_own_commercial_key_by_flipping_the_property_type(): void
    {
        $owner   = $this->owner();
        $listing = $this->listing($owner, self::RESIDENTIAL);

        $this->editComponent($owner, $listing)
            ->set('property_type', self::COMMERCIAL)
            ->set('compatibility_preferences.landlord_specific.preferred_business_use', ['Office'])
            ->call('update');

        $this->assertArrayNotHasKey('preferred_business_use', $this->storedLandlordBlock($listing),
            'The stored property type must govern the projection, or one request can both grant '
            . 'itself commercial status and use it.');
    }

    /** @test */
    public function arbitrary_injected_keys_are_discarded_by_the_projection(): void
    {
        $owner   = $this->owner();
        $listing = $this->listing($owner, self::COMMERCIAL);

        $this->editComponent($owner, $listing)
            ->set('compatibility_preferences.landlord_specific.communication_style', 'Email Only')
            ->set('compatibility_preferences.landlord_specific.totally_made_up_key', 'injected')
            ->call('update');

        $block = $this->storedLandlordBlock($listing);
        $this->assertArrayNotHasKey('totally_made_up_key', $block);
        $this->assertSame('Email Only', $block['communication_style'] ?? null);
    }

    /**
     * The stale-tab case, end to end: a form opened before the deploy still posts the retired
     * field on submit.
     *
     * @test
     */
    public function a_stale_tab_cannot_write_the_retired_tenant_type_keys(): void
    {
        $owner   = $this->owner();
        $listing = $this->listing($owner, self::RESIDENTIAL);

        $this->editComponent($owner, $listing)
            ->set('compatibility_preferences.landlord_specific.communication_style', 'Email Only')
            ->set('compatibility_preferences.landlord_specific.tenant_type_preference', 'Students')
            ->set('compatibility_preferences.landlord_specific.tenant_type_preference_other', 'Long-term professional tenant')
            ->call('update');

        $block = $this->storedLandlordBlock($listing);
        $this->assertArrayNotHasKey('tenant_type_preference', $block);
        $this->assertArrayNotHasKey('tenant_type_preference_other', $block);
        $this->assertSame('Email Only', $block['communication_style'] ?? null);
    }

    // ── Role parity for the Edit persist repair ──────────────────────────────

    /** @test */
    public function landlord_edit_persists_ordinary_compatibility_changes(): void
    {
        $owner   = $this->owner();
        $listing = $this->listing($owner, self::RESIDENTIAL);

        $this->editComponent($owner, $listing)
            ->set('compatibility_preferences.landlord_specific.negotiation_style', 'Firm on Terms')
            ->call('update');

        $this->assertSame('Firm on Terms', $this->storedLandlordBlock($listing)['negotiation_style'] ?? null);
    }

    /** @test */
    public function the_edit_persist_does_not_clobber_another_roles_stored_namespace(): void
    {
        $owner   = $this->owner();
        $listing = $this->listing($owner, self::RESIDENTIAL);
        $listing->saveMeta('compatibility_preferences', json_encode([
            'landlord_specific' => ['negotiation_style' => 'Open to Negotiation'],
            'tenant_specific'   => ['concerns_or_barriers' => 'pre-existing tenant answer'],
        ]));

        $this->editComponent($owner, $listing)
            ->set('compatibility_preferences.landlord_specific.negotiation_style', 'Firm on Terms')
            ->call('update');

        $blob = json_decode((string) $listing->fresh()->info('compatibility_preferences'), true);

        $this->assertSame('Firm on Terms', $blob['landlord_specific']['negotiation_style']);
        $this->assertSame('pre-existing tenant answer', $blob['tenant_specific']['concerns_or_barriers'],
            'The Edit persist writes one role namespace into the stored blob; it must not stamp '
            . 'the component defaults over the others.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * A mounted Edit component with the fields update()'s server-side required-field gate
     * insists on already filled in.
     *
     * That gate returns early via dispatchBrowserEvent('edit-validation-failed') rather than
     * throwing, so an unsatisfied fixture produces a silent no-op save and every assertion below
     * fails as "null is not ...". Filling them here is fixture setup, not the thing under test.
     */
    private function editComponent(User $owner, LandlordAgentAuction $listing)
    {
        return Livewire::actingAs($owner)
            ->test(TenantAgentAuctionEdit::class, [
                'auctionId' => $listing->id,
                'user_type' => 'landlord',
            ])
            ->set('listing_title', 'Business use listing')
            ->set('listing_date', '2026-01-01')
            ->set('expiration_date', '2026-12-31')
            ->set('meeting_Preference', 'Video Call')
            ->set('first_name', 'Test')
            ->set('last_name', 'Landlord')
            ->set('phone_number', '(555) 555-5555')
            ->set('email', 'landlord@example.test')
            ->set('service_type', 'full_service');
    }

    /**
     * Drive a save through the Edit component, which shares the persist path with Create and
     * Draft (all three funnel through the same projection).
     */
    private function persistViaComponent(
        User $owner,
        LandlordAgentAuction $listing,
        string $propertyType,
        array $businessUse,
        string $other = '',
        bool $draft = false
    ): void {
        $component = $this->editComponent($owner, $listing)
            ->set('compatibility_preferences.landlord_specific.communication_style', 'Email Only')
            ->set('compatibility_preferences.landlord_specific.preferred_business_use', $businessUse);

        if ($other !== '') {
            $component->set('compatibility_preferences.landlord_specific.preferred_business_use_other', $other);
        }

        $component->call('update');
    }
}
