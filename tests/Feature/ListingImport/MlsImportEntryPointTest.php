<?php

namespace Tests\Feature\ListingImport;

use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Where the "Import from MLS Listing" button on Create Offer Listing goes.
 *
 * ONE BUTTON, THEN THE USER CHOOSES.
 * ----------------------------------
 * The button predates the quick-import flow and used to have exactly one
 * behaviour: `$set('showImportModal', true)` — the legacy field-selection modal
 * (Imported Field / Form Field / Imported Value / Apply Selected) that prefills
 * the giant manual form.
 *
 * When quick import arrived the button was changed to redirect straight into
 * it, and that went too far: the legacy modal is also the ONLY entry point to
 * the Listing Link / raw-text importer, so Seller and Landlord lost that
 * importer entirely. Stellar MLS is an additional door, not a replacement one —
 * anyone without Stellar credentials still needs the link importer, and it is
 * deliberately not gated on Bridge.
 *
 * So the button now opens a chooser (Stellar MLS # / Listing Link / Create
 * Manually) wherever there is more than one mechanism, and opens directly onto
 * the link importer wherever there is only one. These tests pin which screen
 * each flag combination produces and that every path stays reachable.
 *
 * WHAT IS NOT TESTED HERE, ON PURPOSE
 * -----------------------------------
 * Nothing about what quick import imports — the field mapping, the permitted
 * MLS record, the media policy. That is {@see MlsQuickImportFlowTest}'s job and
 * it is untouched by this change. This file is only about which door the button
 * opens.
 */
class MlsImportEntryPointTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();

        config([
            'mls_direct_import.prefill_roles' => ['seller', 'landlord'],
            'bridge.dataset'                  => 'phpunit_dataset',
            'bridge.token'                    => 'phpunit-token',
        ]);

        $this->user = User::factory()->create(['user_type' => 'seller']);
    }

    private function flags(bool $prefill, bool $quickImport): void
    {
        config([
            'mls_direct_import.prefill_enabled'      => $prefill,
            'mls_direct_import.quick_import_enabled' => $quickImport,
        ]);
    }

    /** @return array<string, array{0: class-string, 1: string, 2: string}> */
    public function quickImportRoleProvider(): array
    {
        return [
            'seller'   => [SellerOfferListing::class, 'offer.listing.seller.quick-import', '/offer-listing/seller/import-mls'],
            'landlord' => [LandlordOfferListing::class, 'offer.listing.landlord.quick-import', '/offer-listing/landlord/import-mls'],
        ];
    }

    // ── Quick import ON ──────────────────────────────────────────────────────

    /**
     * The button opens the CHOOSER — it does not pick an importer for the user.
     *
     * This is the corrected behaviour. The button used to redirect straight into
     * quick import whenever the flow was available, which left the Listing Link
     * importer with no entry point on Seller and Landlord at all.
     *
     * @dataProvider quickImportRoleProvider
     */
    public function test_button_opens_the_import_method_chooser_when_quick_import_is_on(
        string $component,
        string $routeName,
        string $path
    ): void {
        $this->flags(prefill: true, quickImport: true);

        // The route the Stellar card leads to is the URL the brief specifies.
        $this->assertSame($path, parse_url(route($routeName), PHP_URL_PATH));

        $test = Livewire::actingAs($this->user)
            ->test($component)
            ->call('startMlsImport')
            // No importer is chosen for the user…
            ->assertNoRedirect()
            // …the modal opens on the chooser instead.
            ->assertSet('showImportModal', true)
            ->assertSet('importMethod', '');

        $this->assertTrue($test->instance()->importMethodChoiceAvailable());
    }

    /**
     * All three creation paths are offered, and each is distinguishable.
     *
     * The regression this pins: a user without Stellar MLS access must be able
     * to see and reach the Listing Link importer from this screen.
     *
     * @dataProvider quickImportRoleProvider
     */
    public function test_the_chooser_offers_stellar_link_and_manual(string $component): void
    {
        $this->flags(prefill: true, quickImport: true);

        Livewire::actingAs($this->user)
            ->test($component)
            ->call('startMlsImport')
            ->assertSee('Import from Stellar MLS')
            ->assertSee('Import from Listing Link')
            ->assertSee('Create Manually')
            // Each card is wired to its own action.
            ->assertSeeHtml('wire:click="chooseStellarMlsImport"')
            ->assertSeeHtml('wire:click="chooseListingLinkImport"')
            ->assertSeeHtml('wire:click="chooseManualListing"')
            // The chooser is a choice, not an importer: neither importer's own
            // submit action is on screen yet.
            ->assertDontSeeHtml('wire:click="importListingByMlsNumber"')
            ->assertDontSeeHtml('wire:click="importListingFromUrl"');
    }

    /**
     * Choosing Stellar MLS is what navigates to the quick-import flow.
     *
     * @dataProvider quickImportRoleProvider
     */
    public function test_choosing_stellar_mls_redirects_to_quick_import(
        string $component,
        string $routeName
    ): void {
        $this->flags(prefill: true, quickImport: true);

        Livewire::actingAs($this->user)
            ->test($component)
            ->call('startMlsImport')
            ->call('chooseStellarMlsImport')
            ->assertRedirect(route($routeName));
    }

    /**
     * Choosing Listing Link reveals the URL / raw-text importer, unchanged.
     *
     * @dataProvider quickImportRoleProvider
     */
    public function test_choosing_listing_link_reveals_the_url_importer(string $component): void
    {
        $this->flags(prefill: true, quickImport: true);

        Livewire::actingAs($this->user)
            ->test($component)
            ->call('startMlsImport')
            ->call('chooseListingLinkImport')
            ->assertSet('importMethod', 'link')
            ->assertNoRedirect()
            ->assertSee('Public MLS / Matrix Listing URL')
            ->assertSeeHtml('wire:model.defer="importUrlInput"')
            ->assertSeeHtml('wire:model.defer="importRawText"')
            ->assertSeeHtml('wire:click="importListingFromUrl"')
            // The Stellar mechanism is not duplicated onto this screen; the
            // user reaches it by going back to the chooser.
            ->assertDontSee('Import by MLS #')
            ->assertSeeHtml('wire:click="backToImportMethodChoice"');
    }

    /**
     * The Listing Link importer does not depend on Bridge credentials.
     *
     * It reads a public web page. Someone with no Stellar MLS access — and an
     * environment with no Bridge configuration at all — must still get the
     * importer, because that is the entire reason it is offered beside Stellar.
     *
     * @dataProvider quickImportRoleProvider
     */
    public function test_listing_link_importer_needs_no_bridge_credentials(string $component): void
    {
        $this->flags(prefill: true, quickImport: true);
        config(['bridge.dataset' => null, 'bridge.token' => null]);

        Livewire::actingAs($this->user)
            ->test($component)
            ->call('startMlsImport')
            ->call('chooseListingLinkImport')
            ->assertSet('importMethod', 'link')
            ->assertSeeHtml('wire:click="importListingFromUrl"')
            ->assertSet('importError', '');
    }

    /**
     * Choosing Create Manually simply closes the modal and leaves the form.
     *
     * @dataProvider quickImportRoleProvider
     */
    public function test_choosing_manual_closes_the_modal(string $component): void
    {
        $this->flags(prefill: true, quickImport: true);

        Livewire::actingAs($this->user)
            ->test($component)
            ->call('startMlsImport')
            ->call('chooseManualListing')
            ->assertNoRedirect()
            ->assertSet('showImportModal', false)
            ->assertSet('importMethod', '');
    }

    /**
     * Back returns to the chooser and does not leave the abandoned method's
     * input or preview lying around for the next one.
     *
     * @dataProvider quickImportRoleProvider
     */
    public function test_back_returns_to_the_chooser_and_clears_the_abandoned_input(
        string $component
    ): void {
        $this->flags(prefill: true, quickImport: true);

        Livewire::actingAs($this->user)
            ->test($component)
            ->call('startMlsImport')
            ->call('chooseListingLinkImport')
            ->set('importUrlInput', 'https://example.test/listing/1')
            ->set('importRawText', 'pasted listing text')
            ->call('backToImportMethodChoice')
            ->assertSet('importMethod', '')
            ->assertSet('importUrlInput', '')
            ->assertSet('importRawText', '')
            ->assertSet('importPreviewData', [])
            ->assertSee('Import from Stellar MLS')
            ->assertSee('Import from Listing Link');
    }

    /**
     * A flag that goes away between render and click does not produce a 404.
     *
     * The card was drawn while the flow was available; by the time it is clicked
     * it is not. The user is put on the link importer with an explanation, which
     * is a working screen, rather than sent to a route that no longer resolves.
     *
     * @dataProvider quickImportRoleProvider
     */
    public function test_stellar_choice_falls_back_when_the_flow_disappears_after_render(
        string $component
    ): void {
        $this->flags(prefill: true, quickImport: true);

        $test = Livewire::actingAs($this->user)
            ->test($component)
            ->call('startMlsImport');

        // The flow is switched off after the chooser was rendered.
        $this->flags(prefill: true, quickImport: false);

        $test->call('chooseStellarMlsImport')
            ->assertNoRedirect()
            ->assertSet('importMethod', 'link')
            ->assertSet('showImportModal', true);

        $this->assertStringContainsString(
            'not available',
            $test->instance()->importError
        );
    }

    /**
     * The destination actually serves a page.
     *
     * Closes the loop the redirect opens: a button that navigates to a 404 is
     * worse than no button, and the entry point's gate and the destination's
     * gate are two separate reads of the same flags. The companion case — the
     * route 404s with the flag off — is already pinned by
     * MlsQuickImportFlowTest::the_flow_is_unreachable_when_the_feature_is_off().
     *
     * @dataProvider quickImportRoleProvider
     */
    public function test_the_redirect_destination_serves_the_quick_import_page(
        string $component,
        string $routeName
    ): void {
        $this->flags(prefill: true, quickImport: true);

        $this->actingAs($this->user)
            ->get(route($routeName))
            ->assertOk()
            // The first step of the NEW flow, not a field-selection table.
            ->assertDontSee('Apply Selected')
            ->assertDontSee('Imported Field');
    }

    // ── Quick import OFF, legacy prefill ON ──────────────────────────────────

    /**
     * The legacy modal is preserved exactly as it was — this change removes no
     * behaviour, it only adds a second destination.
     *
     * @dataProvider quickImportRoleProvider
     */
    public function test_button_opens_the_legacy_modal_when_quick_import_is_off(string $component): void
    {
        $this->flags(prefill: true, quickImport: false);

        $test = Livewire::actingAs($this->user)
            ->test($component)
            ->call('startMlsImport')
            ->assertNoRedirect()
            ->assertSet('showImportModal', true);

        // And the modal that opens is the real legacy one, MLS # section
        // included. "Apply Selected" belongs to the modal's step 2 and only
        // appears once a preview exists, so step 1's own markers are what is
        // asserted here.
        $test->assertSee('Import from MLS Listing')
            ->assertSee('Import by MLS #')
            ->assertSeeHtml('wire:click="importListingByMlsNumber"')
            ->assertSeeHtml('wire:click="importListingFromUrl"');

        $this->assertFalse($test->instance()->mlsQuickImportAvailable());
    }

    // ── Both flags OFF ───────────────────────────────────────────────────────

    /**
     * No dead MLS action is exposed.
     *
     * The button still renders, because it also fronts the URL / raw-text
     * importer (`MlsListingImportService`), which is ungated by design and works
     * in every environment — see the note in config/mls_direct_import.php. What
     * must not be offered is an MLS-flag-dependent action that cannot work, and
     * neither is: the "Import by MLS #" section is not rendered, and the action
     * behind it refuses server-side even when called directly.
     *
     * @dataProvider quickImportRoleProvider
     */
    public function test_no_dead_mls_action_is_exposed_when_both_flags_are_off(string $component): void
    {
        $this->flags(prefill: false, quickImport: false);

        $test = Livewire::actingAs($this->user)
            ->test($component)
            ->call('startMlsImport')
            ->assertNoRedirect()
            ->assertSet('showImportModal', true);

        // No Bridge MLS # lookup offered…
        $test->assertDontSeeHtml('importListingByMlsNumber')
            ->assertDontSee('Import by MLS #');

        // …and none reachable by a replayed call either.
        $test->set('importMlsNumber', 'A4567890')
            ->call('importListingByMlsNumber')
            ->assertSet('importError', 'MLS # import is not available.')
            ->assertSet('importPreviewData', []);

        $this->assertFalse($test->instance()->mlsQuickImportAvailable());
    }

    // ── Roles the quick-import flow does not exist for ───────────────────────

    /**
     * Buyer and Tenant have no quick-import route and never gain one from a
     * flag: their listings describe search criteria across many areas, not one
     * property. The button keeps its original single behaviour there.
     *
     * @dataProvider nonPropertyRoleProvider
     */
    public function test_buyer_and_tenant_keep_the_legacy_modal_regardless_of_flags(
        string $component,
        string $userType
    ): void {
        $this->flags(prefill: true, quickImport: true);
        config(['mls_direct_import.prefill_roles' => ['seller', 'landlord', 'buyer', 'tenant']]);

        $user = User::factory()->create(['user_type' => $userType]);

        $test = Livewire::actingAs($user)
            ->test($component)
            ->call('startMlsImport')
            ->assertNoRedirect()
            ->assertSet('showImportModal', true);

        $this->assertNull($test->instance()->mlsQuickImportRouteName());
        $this->assertFalse($test->instance()->mlsQuickImportAvailable());

        // No chooser is inserted in front of them, and the importer they have
        // always had opens directly.
        $this->assertFalse($test->instance()->importMethodChoiceAvailable());

        $test->assertSet('importMethod', 'link')
            ->assertDontSee('Create Manually')
            ->assertSeeHtml('wire:click="importListingFromUrl"')
            ->assertSeeHtml('wire:model.defer="importUrlInput"');
    }

    /**
     * Buyer and Tenant open the modal with $set('showImportModal', true) and
     * never call startMlsImport(), so they reach the blade with importMethod at
     * its '' default. That must render the importer, not an empty screen.
     *
     * @dataProvider nonPropertyRoleProvider
     */
    public function test_the_direct_set_entry_point_still_renders_the_importer(
        string $component,
        string $userType
    ): void {
        $this->flags(prefill: true, quickImport: true);

        $user = User::factory()->create(['user_type' => $userType]);

        Livewire::actingAs($user)
            ->test($component)
            ->set('showImportModal', true)
            ->assertSet('importMethod', '')
            ->assertSeeHtml('wire:click="importListingFromUrl"')
            ->assertSeeHtml('wire:model.defer="importUrlInput"')
            ->assertDontSeeHtml('wire:click="chooseStellarMlsImport"');
    }

    /** @return array<string, array{0: class-string, 1: string}> */
    public function nonPropertyRoleProvider(): array
    {
        return [
            'buyer'  => [BuyerOfferListing::class, 'buyer'],
            'tenant' => [TenantOfferListing::class, 'tenant'],
        ];
    }
}
