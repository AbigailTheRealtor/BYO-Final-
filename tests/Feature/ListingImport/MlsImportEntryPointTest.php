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
 * ONE BUTTON, TWO DESTINATIONS, DECIDED SERVER-SIDE.
 * --------------------------------------------------
 * The button predates the quick-import flow and used to have exactly one
 * behaviour: `$set('showImportModal', true)` — the legacy field-selection modal
 * (Imported Field / Form Field / Imported Value / Apply Selected) that prefills
 * the giant manual form. With quick import live that is no longer what "import
 * from MLS" means for Seller and Landlord, so the same button now routes to the
 * shortened path instead. These tests pin which destination each flag
 * combination produces.
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
     * The button sends the user to the NEW flow, not the legacy modal.
     *
     * @dataProvider quickImportRoleProvider
     */
    public function test_button_redirects_to_quick_import_when_enabled(
        string $component,
        string $routeName,
        string $path
    ): void {
        $this->flags(prefill: true, quickImport: true);

        // The route named is the URL the brief specifies.
        $this->assertSame($path, parse_url(route($routeName), PHP_URL_PATH));

        Livewire::actingAs($this->user)
            ->test($component)
            ->call('startMlsImport')
            ->assertRedirect(route($routeName))
            // The legacy modal must not also open behind the redirect.
            ->assertSet('showImportModal', false);
    }

    /**
     * The rendered page's entry point no longer opens the legacy modal — the
     * specific regression this change fixes.
     *
     * @dataProvider quickImportRoleProvider
     */
    public function test_rendered_entry_point_does_not_open_the_legacy_modal_when_quick_import_is_on(
        string $component
    ): void {
        $this->flags(prefill: true, quickImport: true);

        $test = Livewire::actingAs($this->user)->test($component);

        // The button is wired to the server-side decision, not to the modal.
        $test->assertSeeHtml('wire:click="startMlsImport"')
            ->assertDontSeeHtml("\$set('showImportModal', true)");

        // Clicking it leaves the page rather than opening the modal, so none of
        // the modal's own markup appears afterwards either.
        $test->call('startMlsImport')
            ->assertDontSee('Import by MLS #')
            ->assertDontSeeHtml('wire:click="importListingByMlsNumber"')
            ->assertDontSeeHtml('wire:click="importListingFromUrl"')
            ->assertDontSee('Apply Selected')
            ->assertDontSee('Imported Field');

        $this->assertTrue($test->instance()->mlsQuickImportAvailable());
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
