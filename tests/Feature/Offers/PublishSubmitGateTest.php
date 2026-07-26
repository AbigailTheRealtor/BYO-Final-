<?php

namespace Tests\Feature\Offers;

use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListingEdit;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListingEdit;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Submit-button publish gate — Seller + Landlord, Create + Edit.
 *
 * THE DEFECT THESE GUARD
 * ----------------------
 * All four views carried a legacy client gate that scanned every DOM [required]
 * field on the wizard — 15-26 of them, against the ~7 the server actually
 * requires — and applied `.disabled` to #save-button when any one was empty.
 * With `#save-button.disabled { pointer-events: none }` (and Bootstrap's own
 * `.btn.disabled` rule) the click was swallowed before wire:submit could fire:
 * no submit event, no Livewire request, no error. Submit did nothing, and a
 * resumed draft — part-filled by definition — could never be published.
 *
 * WHAT IS ASSERTED
 * ----------------
 *   1. The authoritative required set comes from the role's own publish rules,
 *      and excludes fields the server accepts empty.
 *   2. Publish succeeds when only the server-required fields are filled, with
 *      unrelated DOM-required fields left empty.
 *   3. A genuinely missing server-required field still fails, and now dispatches
 *      the guided-correction event instead of failing silently.
 *   4. The markup can no longer strand the button in a disabled state, and the
 *      legacy gate is gone from all four views.
 *   5. The server remains authoritative — ValidStreetAddress still rejects, and
 *      ownership checks are unchanged.
 *
 * Browser QA is still required for the click itself; PHP cannot observe
 * pointer-events. Assertion 4 pins the CSS/markup preconditions that made the
 * click impossible.
 */
class PublishSubmitGateTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW_ROOT = 'resources/views/livewire/offer-listing';

    /** Fields the server accepts empty — none of these may gate the button. */
    private const NOT_PUBLISH_BLOCKING = [
        'year_built',
        'zoning',
        'front_footage',
        'roof_type',
        'foundation',
        'ceiling_height',
        'number_of_wells',
    ];

    private function agent(): User
    {
        return User::factory()->create(['user_type' => 'agent']);
    }

    private function makeSellerAuction(User $user, bool $isDraft = false): SellerAgentAuction
    {
        $auction = SellerAgentAuction::create([
            'user_id'     => $user->id,
            'title'       => 'Publish Gate Seller Listing',
            'is_draft'    => $isDraft,
            'is_approved' => ! $isDraft,
            'is_sold'     => false,
        ]);

        SellerAgentAuctionMeta::insert([
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'workflow_type', 'meta_value' => 'offer_listing'],
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'property_type', 'meta_value' => 'Residential'],
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'first_name',    'meta_value' => 'Test'],
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'last_name',     'meta_value' => 'Agent'],
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'phone_number',  'meta_value' => '5551234567'],
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'email',         'meta_value' => 'agent@example.com'],
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'address',       'meta_value' => '100 2nd Ave N'],
        ]);

        $offerAuction = OfferAuction::create(['user_id' => $user->id]);
        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'linked_offer_auction_id',
            'meta_value'              => (string) $offerAuction->id,
        ]);

        return $auction;
    }

    private function makeLandlordAuction(User $user, bool $isDraft = false): LandlordAgentAuction
    {
        $auction = LandlordAgentAuction::create([
            'user_id'     => $user->id,
            'title'       => 'Publish Gate Landlord Listing',
            'is_draft'    => $isDraft,
            'is_approved' => ! $isDraft,
            'is_sold'     => false,
        ]);

        LandlordAgentAuctionMeta::insert([
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'workflow_type',        'meta_value' => 'offer_listing'],
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'first_name',           'meta_value' => 'Test'],
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'last_name',            'meta_value' => 'Agent'],
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'phone_number',         'meta_value' => '5551234567'],
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'email',                'meta_value' => 'agent@example.com'],
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'property_type',        'meta_value' => 'Residential Property'],
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'desired_lease_length', 'meta_value' => json_encode(['12 Months'])],
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'address',              'meta_value' => '100 2nd Ave N'],
        ]);

        $offerAuction = OfferAuction::create(['user_id' => $user->id]);
        LandlordAgentAuctionMeta::create([
            'landlord_agent_auction_id' => $auction->id,
            'meta_key'                  => 'linked_offer_auction_id',
            'meta_value'                => (string) $offerAuction->id,
        ]);

        return $auction;
    }

    private function viewSource(string $relative): string
    {
        $full = base_path(self::VIEW_ROOT . '/' . $relative);
        $this->assertFileExists($full, "Expected view missing: {$relative}");

        return (string) file_get_contents($full);
    }

    /**
     * Source reduced to executable code, for the "this pattern must be gone"
     * assertions.
     *
     * Two things are removed:
     *
     *   1. Comments. The fix documents the defect it removes, and those comments
     *      quote the very tokens being asserted against. Matching prose would make
     *      the guard fire on its own explanation.
     *   2. The body of initializeLimitedService(). That function is frozen legacy
     *      code for the Limited Service flow (CLAUDE.md) — never modified, never
     *      cleaned up. Landlord create keeps its own copy of the old gate in there,
     *      and that copy is intentionally left as-is, so it must not be asserted
     *      against either.
     */
    private function assertableCode(string $relative): string
    {
        $src = $this->viewSource($relative);

        $src = $this->removeFrozenLimitedService($src);

        $src = preg_replace('/\{\{--.*?--\}\}/s', '', $src);   // Blade comments
        $src = preg_replace('!/\*.*?\*/!s', '', $src);          // CSS + JS block comments
        $src = preg_replace('!^\s*//.*$!m', '', $src);          // whole-line JS comments

        return (string) $src;
    }

    /** Excise initializeLimitedService()'s body by brace matching. */
    private function removeFrozenLimitedService(string $src): string
    {
        $needle = 'function initializeLimitedService() {';
        $start  = strpos($src, $needle);

        if ($start === false) {
            return $src;
        }

        $depth = 0;
        $i     = $start + strlen($needle) - 1;
        $len   = strlen($src);

        for (; $i < $len; $i++) {
            if ($src[$i] === '{') {
                $depth++;
            } elseif ($src[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($src, 0, $start) . substr($src, $i + 1);
                }
            }
        }

        return $src;
    }

    public static function viewProvider(): array
    {
        return [
            'seller-create'   => ['seller/offer-seller-listing.blade.php'],
            'seller-edit'     => ['seller/offer-seller-listing-edit.blade.php'],
            'landlord-create' => ['landlord/offer-landlord-listing.blade.php'],
            'landlord-edit'   => ['landlord/offer-landlord-listing-edit.blade.php'],
        ];
    }

    // ─── 1 · The authoritative required set ───────────────────────────────────

    /**
     * All four components expose the same contract, derived from their own
     * getConditionalRules(). Create and Edit therefore cannot drift.
     */
    public function test_all_four_components_expose_the_server_required_field_contract(): void
    {
        $user            = $this->agent();
        $sellerAuction   = $this->makeSellerAuction($user, true);
        $landlordAuction = $this->makeLandlordAuction($user, true);

        $components = [
            'seller-create'   => Livewire::actingAs($user)->test(SellerOfferListing::class),
            'seller-edit'     => Livewire::actingAs($user)->test(SellerOfferListingEdit::class, ['auctionId' => $sellerAuction->id]),
            'landlord-create' => Livewire::actingAs($user)->test(LandlordOfferListing::class),
            'landlord-edit'   => Livewire::actingAs($user)->test(LandlordOfferListingEdit::class, ['auctionId' => $landlordAuction->id]),
        ];

        foreach ($components as $label => $component) {
            $required = $component->instance()->publishRequiredFieldNames();

            $this->assertIsArray($required, "[{$label}] publishRequiredFieldNames() must return an array.");
            $this->assertNotEmpty($required, "[{$label}] publish-required set must not be empty.");

            $this->assertContains('address', $required,
                "[{$label}] street address is a publish-required field (Phase 0).");

            foreach (self::NOT_PUBLISH_BLOCKING as $optional) {
                $this->assertNotContains($optional, $required,
                    "[{$label}] '{$optional}' is accepted empty by the server and must not gate Submit.");
            }

            // Element rules such as roof_type.* constrain members of an optional
            // array and have no single DOM field to focus.
            foreach ($required as $field) {
                $this->assertStringNotContainsString('.', $field,
                    "[{$label}] element rules must not appear in the gate list.");
            }
        }
    }

    // ─── 2 · Publish succeeds with unrelated fields empty ─────────────────────

    /**
     * The whole point: every server-required field is present, every unrelated
     * DOM-required field is empty, and the publish goes through.
     */
    public function test_seller_edit_draft_publishes_with_unrelated_required_fields_empty(): void
    {
        $user    = $this->agent();
        $auction = $this->makeSellerAuction($user, true);

        Livewire::actingAs($user)
            ->test(SellerOfferListingEdit::class, ['auctionId' => $auction->id])
            ->set('address', '100 2nd Ave N')
            // Deliberately blank — DOM-required, server-optional.
            ->set('year_built', '')
            ->set('zoning', '')
            ->call('update')
            ->assertHasNoErrors();

        $this->assertSame(0, (int) $auction->fresh()->is_draft,
            'Publishing a Seller draft must clear is_draft.');
    }

    public function test_seller_edit_published_listing_still_updates(): void
    {
        $user    = $this->agent();
        $auction = $this->makeSellerAuction($user, false);

        Livewire::actingAs($user)
            ->test(SellerOfferListingEdit::class, ['auctionId' => $auction->id])
            ->set('address', '100 2nd Ave N')
            ->call('update')
            ->assertHasNoErrors();
    }

    public function test_landlord_edit_draft_publishes_with_unrelated_required_fields_empty(): void
    {
        $user    = $this->agent();
        $auction = $this->makeLandlordAuction($user, true);

        Livewire::actingAs($user)
            ->test(LandlordOfferListingEdit::class, ['auctionId' => $auction->id])
            ->set('address', '100 2nd Ave N')
            ->set('desired_lease_length', ['12 Months'])
            ->set('year_built', '')
            ->set('zoning', '')
            ->call('update')
            ->assertHasNoErrors();

        $this->assertSame(0, (int) $auction->fresh()->is_draft,
            'Publishing a Landlord draft must clear is_draft.');
    }

    /**
     * Create-side equivalent: the rule set a fresh wizard evaluates must not make
     * an unrelated DOM-required field mandatory.
     */
    public function test_create_components_do_not_require_unrelated_dom_fields(): void
    {
        $user = $this->agent();

        foreach ([
            'seller-create'   => Livewire::actingAs($user)->test(SellerOfferListing::class),
            'landlord-create' => Livewire::actingAs($user)->test(LandlordOfferListing::class),
        ] as $label => $component) {
            $required = $component->instance()->publishRequiredFieldNames();

            foreach (self::NOT_PUBLISH_BLOCKING as $optional) {
                $this->assertNotContains($optional, $required,
                    "[{$label}] '{$optional}' must not block publishing.");
            }
        }
    }

    // ─── 3 · A real missing field still fails, and is now explained ───────────

    public function test_seller_edit_missing_required_field_fails_and_dispatches_guidance(): void
    {
        $user    = $this->agent();
        $auction = $this->makeSellerAuction($user, true);

        Livewire::actingAs($user)
            ->test(SellerOfferListingEdit::class, ['auctionId' => $auction->id])
            ->set('address', '100 2nd Ave N')
            ->set('listing_title', '')
            ->call('update')
            ->assertHasErrors(['listing_title'])
            ->assertDispatchedBrowserEvent('publish-validation-failed');
    }

    public function test_landlord_edit_missing_required_field_fails_and_dispatches_guidance(): void
    {
        $user    = $this->agent();
        $auction = $this->makeLandlordAuction($user, true);

        Livewire::actingAs($user)
            ->test(LandlordOfferListingEdit::class, ['auctionId' => $auction->id])
            ->set('address', '100 2nd Ave N')
            ->set('email', '')
            ->call('update')
            ->assertHasErrors(['email'])
            ->assertDispatchedBrowserEvent('publish-validation-failed');
    }

    // ─── 4 · The markup can no longer strand the button ──────────────────────

    /**
     * @dataProvider viewProvider
     */
    public function test_view_never_swallows_the_submit_click(string $view): void
    {
        $src = $this->assertableCode($view);

        $this->assertStringNotContainsString('#save-button.disabled', $src,
            "[{$view}] the .disabled class rule on #save-button is what applied pointer-events: none.");

        $this->assertDoesNotMatchRegularExpression(
            '/#save-button[^{]*\{[^}]*pointer-events\s*:\s*none/s',
            $src,
            "[{$view}] #save-button must never set pointer-events: none — it swallows the click before wire:submit."
        );

        $this->assertStringNotContainsString('wizard-step-finish disabled', $src,
            "[{$view}] Submit must not ship with the disabled class; Bootstrap's .btn.disabled also sets pointer-events: none.");
    }

    /**
     * @dataProvider viewProvider
     */
    public function test_legacy_dom_wide_completeness_gate_is_gone(string $view): void
    {
        $src = $this->assertableCode($view);

        $this->assertStringNotContainsString('function validateAllTabsStrictly', $src,
            "[{$view}] the legacy DOM-wide completeness gate must be removed — it was the root cause.");

        $this->assertDoesNotMatchRegularExpression(
            "/saveButton\.setAttribute\(\s*'disabled'/",
            $src,
            "[{$view}] Submit must never be attribute-disabled for form completeness."
        );

        $this->assertDoesNotMatchRegularExpression(
            "/saveButton\.classList\.add\(\s*'disabled'\s*\)/",
            $src,
            "[{$view}] Submit must never be class-disabled for form completeness."
        );
    }

    /**
     * Requirement 8: a disabled state is legitimate ONLY for duplicate-click
     * prevention while a submit is in flight.
     *
     * @dataProvider viewProvider
     */
    public function test_double_submit_is_still_prevented_while_loading(string $view): void
    {
        $src = $this->viewSource($view);

        $this->assertMatchesRegularExpression(
            '/id="save-button"[^>]*wire:loading\.attr="disabled"|wire:loading\.attr="disabled"[^>]*id="save-button"/s',
            $src,
            "[{$view}] Submit must keep wire:loading.attr=\"disabled\" so a second click cannot double-submit."
        );
    }

    /** Both edit views must wire in the shared authoritative gate. */
    public function test_edit_views_include_the_shared_publish_gate(): void
    {
        foreach ([
            'seller/offer-seller-listing-edit.blade.php',
            'landlord/offer-landlord-listing-edit.blade.php',
        ] as $view) {
            $src = $this->viewSource($view);

            $this->assertStringContainsString('partials.offer-listing.publish-submit-gate', $src,
                "[{$view}] must include the shared publish gate.");
            $this->assertStringContainsString('publishRequiredFieldNames()', $src,
                "[{$view}] the gate must be fed from the server's own required-field list.");
            $this->assertStringContainsString('id="edit-auction-form"', $src,
                "[{$view}] the gate intercepts submit by form id.");
            $this->assertStringContainsString('id="submit-error-banner"', $src,
                "[{$view}] guided correction needs the error banner.");
        }

        $this->assertFileExists(
            base_path('resources/views/partials/offer-listing/publish-submit-gate.blade.php'),
            'The shared publish gate partial must exist.'
        );
    }

    // ─── 5 · The server stays authoritative ──────────────────────────────────

    /**
     * The client gate is advisory. A ZIP typed into the street field must still be
     * rejected server-side, whatever the browser allowed through.
     */
    public function test_server_still_rejects_an_invalid_street_address_on_publish(): void
    {
        $user    = $this->agent();
        $auction = $this->makeSellerAuction($user, true);

        Livewire::actingAs($user)
            ->test(SellerOfferListingEdit::class, ['auctionId' => $auction->id])
            ->set('address', '33708')
            ->call('update')
            ->assertHasErrors(['address']);

        $this->assertSame(1, (int) $auction->fresh()->is_draft,
            'A rejected publish must leave the draft intact.');
    }

    public function test_landlord_server_still_rejects_an_invalid_street_address_on_publish(): void
    {
        $user    = $this->agent();
        $auction = $this->makeLandlordAuction($user, true);

        Livewire::actingAs($user)
            ->test(LandlordOfferListingEdit::class, ['auctionId' => $auction->id])
            ->set('address', '43434')
            ->call('update')
            ->assertHasErrors(['address']);
    }

    /** Object-level authorization is untouched by the gate work. */
    public function test_cross_user_ownership_protection_is_unchanged(): void
    {
        $owner     = $this->agent();
        $intruder  = $this->agent();
        $seller    = $this->makeSellerAuction($owner, true);
        $landlord  = $this->makeLandlordAuction($owner, true);

        foreach ([
            [SellerOfferListingEdit::class, $seller->id],
            [LandlordOfferListingEdit::class, $landlord->id],
        ] as [$component, $id]) {
            Livewire::actingAs($intruder)
                ->test($component, ['auctionId' => $id])
                ->assertForbidden();
        }

        // And the owner is still let through, so the guard is not simply refusing
        // everyone.
        Livewire::actingAs($owner)
            ->test(SellerOfferListingEdit::class, ['auctionId' => $seller->id])
            ->assertOk();
    }
}
