<?php

namespace Tests\Feature\ListingImport;

use App\Http\Livewire\OfferListing\QuickImport\LandlordMlsQuickImport;
use App\Http\Livewire\OfferListing\QuickImport\SellerMlsQuickImport;
use App\Models\BridgeProperty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Quick Import renders the SAME field environment as Seller/Landlord Create.
 *
 * WHAT WENT WRONG THREE TIMES
 * ---------------------------
 * The canonical terms partials are shared by Create, Edit and MLS Quick Import,
 * but they depend on an environment each PAGE WRAPPER used to provide privately.
 * Quick Import provided none of it, and the same defect surfaced three ways:
 *
 *   PR #139  behaviour   — the conditional-reveal JS sat in each page's
 *                          @push('scripts'); Quick Import had no such block, so
 *                          sections could never open.
 *   currency fix         — the input styling was scoped to each page's
 *                          #wizard-form-container; Quick Import was not inside
 *                          one, so 27 `$`/`%` prefixes stacked above their inputs.
 *   this change          — the icon RENDERER, the selection-grid CSS and the
 *                          Select2 INITIALISATION lived in the wrappers; Quick
 *                          Import had none of them, so every icon was invisible,
 *                          Tenant Pays / Rent Includes rendered as an unstyled
 *                          vertical list, and Offered Financing depended on a
 *                          generic 200 ms sweep instead of the form's initializer.
 *
 * These tests pin the shared architecture rather than the symptoms, because the
 * symptom is different every time and the cause is always the same.
 *
 * WHAT THESE TESTS CAN AND CANNOT PROVE
 * -------------------------------------
 * There is no browser in this suite. What IS decidable here, and what actually
 * broke, is whether the canonical environment is wired to every consumer:
 * whether each renderer/initializer exists once, whether each page receives it,
 * whether the icon a field declares is the same for every entry path, and
 * whether answers survive Review → Back on the server. A test that claimed a
 * Select2 dropdown opened, or that a checklist card turned blue on click, would
 * be a lie about what PHPUnit did. That is manual browser verification, and is
 * stated as such.
 */
class MlsQuickImportFormParityTest extends TestCase
{
    use RefreshDatabase;

    private const SHARED_ICONS = 'resources/views/livewire/offer-listing/shared/_field-icons.blade.php';
    private const SHARED_GRID  = 'resources/views/livewire/offer-listing/shared/_selection-grid-styles.blade.php';

    private const QUICK_IMPORT = 'resources/views/livewire/offer-listing/quick-import/mls-quick-import.blade.php';
    private const SELLER_TERMS = 'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/seller-terms.blade.php';
    private const LEASE_TERMS  = 'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/lease-terms.blade.php';

    private const SELLER_BEHAVIOUR   = 'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/_seller-terms-behaviour.blade.php';
    private const LANDLORD_BEHAVIOUR = 'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/_lease-terms-behaviour.blade.php';

    /** The form's single Select2 definition. */
    private const SELECT2_STABLE = 'public/js/select2-stable.js';

    private const SELLER_WRAPPERS = [
        'resources/views/livewire/offer-listing/seller/offer-seller-listing.blade.php',
        'resources/views/livewire/offer-listing/seller/offer-seller-listing-edit.blade.php',
    ];

    private const LANDLORD_WRAPPERS = [
        'resources/views/livewire/offer-listing/landlord/offer-landlord-listing.blade.php',
        'resources/views/livewire/offer-listing/landlord/offer-landlord-listing-edit.blade.php',
    ];

    /** The four regular Create/Edit wrappers that were de-duplicated. */
    private const WRAPPERS = [
        'resources/views/livewire/offer-listing/seller/offer-seller-listing.blade.php',
        'resources/views/livewire/offer-listing/seller/offer-seller-listing-edit.blade.php',
        'resources/views/livewire/offer-listing/landlord/offer-landlord-listing.blade.php',
        'resources/views/livewire/offer-listing/landlord/offer-landlord-listing-edit.blade.php',
    ];

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function src(string $relative): string
    {
        return file_get_contents(base_path($relative));
    }

    /** Template markup with Blade comments stripped — prose is not a declaration. */
    private function markup(string $relative): string
    {
        return preg_replace('/\{\{--.*?--\}\}/s', '', $this->src($relative)) ?? '';
    }

    /**
     * The body of every callback that follows `$needle` — the text between the
     * first `{` after it and its matching `}` — with `//` comments removed, so a
     * comment explaining a removed call is not mistaken for the call.
     *
     * @return list<string>
     */
    private function callbackBodies(string $source, string $needle): array
    {
        $bodies = [];
        $offset = 0;

        while (($at = strpos($source, $needle, $offset)) !== false) {
            $open = strpos($source, '{', $at + strlen($needle));

            if ($open === false) {
                break;
            }

            $depth = 0;
            $len   = strlen($source);

            for ($i = $open; $i < $len; $i++) {
                if ($source[$i] === '{') {
                    $depth++;
                } elseif ($source[$i] === '}' && --$depth === 0) {
                    break;
                }
            }

            $bodies[] = preg_replace('~//[^\n]*~', '', substr($source, $open + 1, $i - $open - 1)) ?? '';
            $offset   = $at + strlen($needle);
        }

        return $bodies;
    }

    /**
     * `$body` with every nested `function (…) { … }` DEFINITION blanked out,
     * except a callback handed straight to setTimeout — that one runs as part of
     * the same update, so it stays visible.
     */
    private function withoutNestedHandlers(string $body): string
    {
        while (preg_match('/(?<!setTimeout\()\bfunction\s*\([^)]*\)\s*\{/', $body, $m, PREG_OFFSET_CAPTURE)) {
            $start = $m[0][1];
            $open  = $start + strlen($m[0][0]) - 1;
            $depth = 0;
            $len   = strlen($body);

            for ($i = $open; $i < $len; $i++) {
                if ($body[$i] === '{') {
                    $depth++;
                } elseif ($body[$i] === '}' && --$depth === 0) {
                    break;
                }
            }

            $body = substr($body, 0, $start) . substr($body, $i + 1);
        }

        return $body;
    }

    private function seedRecord(string $mls, string $propertyType, int $price): void
    {
        BridgeProperty::create([
            'listing_key'       => $mls . '-KEY',
            'listing_id'        => $mls,
            'standard_status'   => 'Active',
            'mls_status'        => 'Active',
            'property_type'     => $propertyType,
            'list_price'        => $price,
            'unparsed_address'  => '1 Test Street',
            'city'              => 'TAMPA',
            'state_or_province' => 'FL',
            'postal_code'       => '33601',
            'raw_json'          => json_encode([
                'ListingKey'      => $mls . '-KEY',
                'ListingId'       => $mls,
                'PropertyType'    => $propertyType,
                'UnparsedAddress' => '1 Test Street',
            ]),
        ]);
    }

    private function sellerToTerms(string $mls): \Livewire\Testing\TestableLivewire
    {
        $this->seedRecord($mls, 'Residential', 500000);

        return Livewire::actingAs(User::factory()->create())
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', $mls)
            ->call('findListing')
            ->call('acceptProperty')
            ->call('chooseMethod', 'Traditional')
            ->call('continueToTerms');
    }

    private function landlordToTerms(string $mls, string $type): \Livewire\Testing\TestableLivewire
    {
        $this->seedRecord($mls, $type, 3200);

        return Livewire::actingAs(User::factory()->create())
            ->test(LandlordMlsQuickImport::class)
            ->set('mlsNumber', $mls)
            ->call('findListing')
            ->call('acceptProperty')
            ->call('chooseMethod', 'Traditional')
            ->call('continueToTerms');
    }

    private function quickImportPage(string $role): string
    {
        return $this->actingAs(User::factory()->create())
            ->get(route("offer.listing.{$role}.quick-import"))
            ->assertOk()
            ->getContent();
    }

    // ─── A. The icon renderer exists exactly once ────────────────────────────

    /**
     * @test
     *
     * The renderer was defined nineteen times across the app and zero times in
     * Quick Import. The Offer Listing Seller/Landlord family now has ONE
     * definition. This asserts the definition moved rather than being copied a
     * twentieth time.
     */
    public function the_icon_renderer_is_defined_once_for_the_offer_listing_family(): void
    {
        $this->assertStringContainsString(
            'window.addIconsToInputs = function addIconsToInputs()',
            $this->src(self::SHARED_ICONS),
            'the canonical partial must publish the renderer'
        );

        foreach (self::WRAPPERS as $wrapper) {
            $this->assertStringNotContainsString(
                'function addIconsToInputs()',
                $this->markup($wrapper),
                $wrapper . ' must not define its own renderer any more'
            );
        }

        $this->assertStringNotContainsString(
            'function addIconsToInputs()',
            $this->markup(self::QUICK_IMPORT),
            'Quick Import must inherit the renderer, never declare one'
        );
    }

    /** @test */
    public function every_consumer_includes_the_shared_icon_renderer(): void
    {
        $include = "@include('livewire.offer-listing.shared._field-icons')";

        foreach (array_merge(self::WRAPPERS, [self::QUICK_IMPORT]) as $consumer) {
            $this->assertSame(
                1,
                substr_count($this->markup($consumer), $include),
                $consumer . ' must include the shared icon renderer exactly once'
            );
        }
    }

    /**
     * @test
     *
     * The real Quick Import pages carry the renderer, once. @push renders into
     * the layout's stack, so only the full HTTP response can show this.
     */
    public function both_quick_import_pages_carry_the_renderer_exactly_once(): void
    {
        foreach (['seller', 'landlord'] as $role) {
            $this->assertSame(
                1,
                substr_count($this->quickImportPage($role), 'window.addIconsToInputs = function addIconsToInputs()'),
                "{$role} Quick Import must deliver the shared icon renderer exactly once"
            );
        }
    }

    /**
     * @test
     *
     * The guard is the LANDLORD's, and that is load-bearing rather than
     * arbitrary. Landlord property-preferences has `.input-cover` blocks carrying
     * both a hand-authored `<i class="input-icon">` and a `data-icon` input;
     * skipping on `.input-icon` is what stops a second icon being stacked on
     * them. Seller has none, so the stricter guard changes nothing there.
     */
    public function the_shared_renderer_keeps_the_guard_that_prevents_double_icons(): void
    {
        $shared = $this->src(self::SHARED_ICONS);

        $this->assertStringContainsString("if (wrapper.querySelector('.input-icon')) return;", $shared);
        $this->assertStringContainsString('input-icon ${iconClass} data-icon-rendered', $shared);

        $this->assertGreaterThan(
            0,
            substr_count(
                $this->markup('resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/property-preferences.blade.php'),
                'class="input-icon'
            ),
            'landlord property-preferences still authors icons the guard must respect'
        );
    }

    // ─── A2. One icon lifecycle ─────────────────────────────────────────────

    /**
     * @test
     *
     * The shared partial owns the passes every consumer needs identically: the
     * page-load schedule and the immediate + 0 ms passes after every update.
     */
    public function the_shared_renderer_owns_the_page_load_and_post_update_passes(): void
    {
        $shared = $this->src(self::SHARED_ICONS);

        $this->assertStringContainsString("window.Livewire.hook('message.processed'", $shared);
        $this->assertStringContainsString('setTimeout(window.addIconsToInputs, 0);', $shared);
        $this->assertStringContainsString('requestAnimationFrame(window.addIconsToInputs);', $shared);
        $this->assertStringContainsString('[100, 300, 700, 1500, 2500].forEach', $shared);
    }

    /**
     * @test
     *
     * …so no Create/Edit wrapper repeats them. A copy that comes back is a second
     * lifecycle path for the same pass — which is how nineteen renderers happened.
     *
     * What a wrapper MAY still do is call the renderer after its own page steps
     * (asserted separately below); what it may not do is run the generic passes
     * the shared partial already runs.
     */
    public function no_wrapper_repeats_the_shared_icon_lifecycle(): void
    {
        foreach (self::WRAPPERS as $wrapper) {
            $src = $this->markup($wrapper);

            foreach ($this->callbackBodies($src, "addEventListener('DOMContentLoaded'") as $body) {
                $this->assertStringNotContainsString('addIconsToInputs(', $body,
                    "{$wrapper}: a DOMContentLoaded listener still runs its own page-load icon pass");
            }

            foreach ($this->callbackBodies($src, "addEventListener('livewire:load'") as $body) {
                $this->assertStringNotContainsString('addIconsToInputs(', $body,
                    "{$wrapper}: a livewire:load listener still runs its own page-load icon passes");
            }

            foreach ($this->callbackBodies($src, "Livewire.hook('message.processed'") as $body) {
                // Only what the hook itself runs. A handler it DEFINES for a later
                // event (Landlord Edit re-registers its tab-switch handler here) is
                // page orchestration, not a post-update pass.
                $body = $this->withoutNestedHandlers($body);

                $this->assertDoesNotMatchRegularExpression(
                    '/^\s*(?:var\s+_scrollY[^;]*;\s*)?addIconsToInputs\(\)/',
                    $body,
                    "{$wrapper}: a message.processed hook still opens with the immediate icon pass the shared hook runs"
                );

                $this->assertDoesNotMatchRegularExpression(
                    '/setTimeout\(\s*(?:function\s*\(\)\s*\{\s*addIconsToInputs\(\);?\s*\}|addIconsToInputs)\s*,\s*0\s*\)/',
                    $body,
                    "{$wrapper}: a message.processed hook still runs the 0 ms icon pass the shared hook runs"
                );
            }
        }
    }

    /**
     * @test
     *
     * The converse: the cleanup did not take page orchestration with it. A tab
     * switch is not a Livewire update, so the shared hook never sees it; each
     * wrapper must still re-run the renderer there. Seller Create's schedule
     * after its own initializeFullService() re-run is likewise its own.
     */
    public function page_specific_icon_passes_are_kept(): void
    {
        foreach (self::WRAPPERS as $wrapper) {
            $tabBodies = array_filter(
                $this->callbackBodies($this->markup($wrapper), "'shown.bs.tab'"),
                static fn (string $body) => str_contains($body, 'addIconsToInputs')
            );

            $this->assertNotEmpty($tabBodies, "{$wrapper} must still re-render icons on a tab switch");
        }

        $create = $this->markup(self::SELLER_WRAPPERS[0]);
        $this->assertStringContainsString('function runSellerInitialIconPasses()', $create);
        $this->assertGreaterThanOrEqual(2, substr_count($create, 'runSellerInitialIconPasses();'));
    }

    // ─── B. Exact icon parity, field by field ───────────────────────────────

    /**
     * @dataProvider iconFields
     * @test
     *
     * The strong form of icon parity. Quick Import and Create do not merely both
     * "have an icon" — they render the SAME canonical partial, so the icon for a
     * given question is one declaration and cannot differ between entry paths.
     */
    public function a_field_declares_one_icon_for_every_entry_path(string $partial, string $field, string $iconClass): void
    {
        $this->assertMatchesRegularExpression(
            $this->iconPattern($field, $iconClass),
            $this->markup($partial),
            "{$field} must declare data-icon=\"{$iconClass}\" in the canonical partial, "
            . 'which is the single source Create, Edit and Quick Import all render'
        );
    }

    /**
     * Every pair was READ OFF the canonical partial, not chosen by meaning.
     * Structurally distinct controls on purpose: text, select, date, number,
     * textarea — the shapes the renderer has to handle. Currency inputs are
     * absent deliberately: a money field shows the `$` prefix, not an icon.
     */
    public function iconFields(): array
    {
        return [
            'seller text (sale provision other)' => [self::SELLER_TERMS, 'sale_provision_other', 'fa-solid fa-screwdriver-wrench'],
            'seller select (assignment)'         => [self::SELLER_TERMS, 'sale_provision_assignment', 'fa-solid fa-file-contract'],
            'seller date (occupied until)'       => [self::SELLER_TERMS, 'occupant_tenant', 'fa-regular fa-clock'],
            'seller number (lease duration)'     => [self::SELLER_TERMS, 'lease_option_duration', 'fa-regular fa-calendar-days'],
            'seller textarea (additional terms)' => [self::SELLER_TERMS, 'additional_seller_sale_terms', 'fa-solid fa-file-lines'],
            'landlord select (occupant status)'  => [self::LEASE_TERMS, 'occupant_status', 'fa-solid fa-screwdriver-wrench'],
            'landlord text (restrictions)'       => [self::LEASE_TERMS, 'restrictions', 'fa-solid fa-ban'],
            'landlord textarea (maintenance)'    => [self::LEASE_TERMS, 'll_maintenance_responsibility', 'fa-solid fa-screwdriver-wrench'],
        ];
    }

    private function iconPattern(string $field, string $iconClass): string
    {
        return '/wire:model[^"]*="' . preg_quote($field, '/') . '"[^>]*?data-icon="' . preg_quote($iconClass, '/') . '"'
            . '|data-icon="' . preg_quote($iconClass, '/') . '"[^>]*?wire:model[^"]*="' . preg_quote($field, '/') . '"/s';
    }

    /**
     * @test
     *
     * …and the Quick Import terms step, as actually rendered, carries those same
     * declarations, together with the three Select2 controls' icons. What reaches
     * the browser is the canonical partial's markup, not a lookalike.
     */
    public function the_rendered_quick_import_terms_step_carries_the_canonical_icons(): void
    {
        $seller = $this->sellerToTerms('QI-ICON-S')
            ->set('occupant_status', 'Tenant')
            ->set('sale_provision', ['Assignment Contract'])
            ->lastRenderedDom;

        foreach ($this->iconFields() as [$partial, $field, $icon]) {
            if ($partial !== self::SELLER_TERMS) {
                continue;
            }
            $this->assertMatchesRegularExpression($this->iconPattern($field, $icon), $seller,
                "Quick Import rendered {$field} without its canonical icon {$icon}");
        }

        foreach ([
            'sale_provision'    => 'fa-solid fa-screwdriver-wrench',
            'offered_financing' => 'fa-solid fa-money-bill-wave',
            'exchange_item'     => 'fa-solid fa-right-left',
        ] as $id => $icon) {
            $this->assertMatchesRegularExpression(
                '/<select id="' . $id . '"[^>]*data-icon="' . preg_quote($icon, '/') . '"/',
                $seller,
                "#{$id} must reach Quick Import with its canonical icon"
            );
        }

        $landlord = $this->landlordToTerms('QI-ICON-L', 'Residential Lease')
            ->set('leasing_spaces', 'Entire Property')
            ->lastRenderedDom;

        foreach ($this->iconFields() as [$partial, $field, $icon]) {
            if ($partial !== self::LEASE_TERMS) {
                continue;
            }
            $this->assertMatchesRegularExpression($this->iconPattern($field, $icon), $landlord,
                "Quick Import rendered {$field} without its canonical icon {$icon}");
        }
    }

    /** @test Currency controls carry no icon by design — the `$` prefix is their marker. */
    public function currency_controls_use_the_prefix_rather_than_an_icon(): void
    {
        foreach ([[self::SELLER_TERMS, 'lease_option_price'], [self::LEASE_TERMS, 'security_deposit_amount']] as [$partial, $field]) {
            preg_match('/<input\b[^>]*wire:model[^"]*="' . preg_quote($field, '/') . '"[^>]*>/s', $this->markup($partial), $m);

            $this->assertNotEmpty($m, "{$field} not found in {$partial}");
            $this->assertStringNotContainsString('data-icon=', $m[0], "{$field} is a currency input and must not declare an icon");
        }
    }

    /**
     * @test
     *
     * The canonical partials carry icons as attributes and author no <i> of their
     * own — precisely why a consumer without the renderer showed none of them.
     */
    public function the_canonical_terms_partials_declare_icons_only_as_data_attributes(): void
    {
        foreach ([self::SELLER_TERMS, self::LEASE_TERMS] as $partial) {
            $markup = $this->markup($partial);

            $this->assertGreaterThan(50, substr_count($markup, 'data-icon='));
            $this->assertSame(0, substr_count($markup, 'class="input-icon'),
                $partial . ' must not hand-author an <i class="input-icon"> — the shared renderer creates it');
        }
    }

    // ─── C. Tenant Pays / Rent Includes ─────────────────────────────────────

    /**
     * @test
     *
     * Both are Alpine checklist grids in the canonical partial. They were never
     * static markup — they were unstyled, which is a different defect with a
     * different fix. This pins the control type so nobody "fixes" the appearance
     * by replacing them with a dropdown or with Select2.
     */
    public function tenant_pays_and_rent_includes_are_alpine_checklists_bound_to_livewire(): void
    {
        $markup = $this->markup(self::LEASE_TERMS);

        foreach (['tenant_pays', 'rent_includes'] as $field) {
            $this->assertStringContainsString("\$wire.entangle('{$field}').defer", $markup);
            $this->assertDoesNotMatchRegularExpression('/<select[^>]*(?:id|wire:model)="' . $field . '"/', $markup,
                "{$field} must stay a checklist grid, not a select");
        }

        $this->assertGreaterThanOrEqual(2, substr_count($markup, 'utility-checklist-grid'));
        $this->assertGreaterThanOrEqual(2, substr_count($markup, "@click=\"toggle('"));
        $this->assertGreaterThanOrEqual(2, substr_count($markup, "'utility-selected': isSelected("));

        $this->assertStringContainsString('other_tenant_pays', $markup);
        $this->assertStringContainsString('other_rent_include', $markup);
        $this->assertStringContainsString("x-show=\"isSelected('Other')\"", $markup);
    }

    /**
     * @test
     *
     * The styling those grids need is shared, defined once, and reaches Quick
     * Import. Without it the cards have no `display: grid`, no border, no cursor
     * and no visible selected state.
     */
    public function the_selection_grid_styles_are_shared_and_reach_quick_import(): void
    {
        $shared = $this->src(self::SHARED_GRID);

        foreach ([
            '.utility-checklist-grid',
            '.utility-checklist-card',
            '.utility-checklist-card.utility-selected',
            '.utility-checklist-icon',
            '.utility-checklist-label',
        ] as $selector) {
            $this->assertStringContainsString($selector, $shared, "{$selector} must be defined once, here");
        }

        $this->assertStringContainsString('display: grid;', $shared);

        $include = "@include('livewire.offer-listing.shared._selection-grid-styles')";

        foreach (self::LANDLORD_WRAPPERS as $wrapper) {
            $this->assertSame(1, substr_count($this->markup($wrapper), $include), $wrapper);
        }

        $this->assertStringContainsString(
            "@includeWhen(\$role === 'landlord', 'livewire.offer-listing.shared._selection-grid-styles')",
            $this->markup(self::QUICK_IMPORT)
        );

        foreach (self::WRAPPERS as $wrapper) {
            $this->assertStringNotContainsString('.utility-checklist-grid {', $this->markup($wrapper),
                $wrapper . ' must not redefine the grid styles');
        }
    }

    /**
     * @test
     *
     * The real pages: the landlord Quick Import response carries the selected-state
     * rule exactly once, and the seller one — which has no grids — carries none.
     */
    public function the_grid_styles_reach_the_landlord_quick_import_page_only(): void
    {
        $this->assertSame(1, substr_count($this->quickImportPage('landlord'), '.utility-checklist-card.utility-selected {'));
        $this->assertSame(0, substr_count($this->quickImportPage('seller'), '.utility-checklist-card.utility-selected {'));
    }

    /** @test The de-duplication must not have taken unrelated landlord Edit CSS with it. */
    public function the_landlord_edit_wrapper_keeps_its_unrelated_styles(): void
    {
        $edit = $this->src(self::LANDLORD_WRAPPERS[1]);

        foreach (['.status-text', '.status-icon', '.user-selected'] as $unrelated) {
            $this->assertStringContainsString($unrelated, $edit, "{$unrelated} belongs to this page and must survive");
        }
    }

    /**
     * @test
     *
     * Commercial: Tenant Pays renders as the canonical checklist on Quick Import,
     * its "Other" companion is wired, and the answers — including the free text —
     * survive Review → Back and are what the review publishes.
     */
    public function tenant_pays_is_the_checklist_on_quick_import_and_survives_review_and_back(): void
    {
        $component = $this->landlordToTerms('QI-TP-L', 'Commercial Lease');

        $html = $component->lastRenderedDom;
        $this->assertStringContainsString("\$wire.entangle('tenant_pays').defer", $html);
        $this->assertStringContainsString('utility-checklist-card', $html);
        $this->assertStringContainsString("toggle('Electricity')", $html);
        $this->assertStringContainsString('id="other_tenant_pays_wrapper" x-show="isSelected(\'Other\')"', $html);

        $component->set('tenant_pays', ['Electricity', 'Other'])
            ->set('other_tenant_pays', 'Solar lease')
            ->call('continueToReview')
            ->assertSet('step', 'review');

        $this->assertSame('Electricity, Solar lease', $component->viewData('termsReview')['Tenant pays'] ?? null);

        $component->call('backToTerms')
            ->assertSet('step', 'terms')
            ->assertSet('tenant_pays', ['Electricity', 'Other'])
            ->assertSet('other_tenant_pays', 'Solar lease');

        $this->assertStringContainsString("\$wire.entangle('tenant_pays').defer", $component->lastRenderedDom);
    }

    /** @test Residential: the same for Rent Includes. */
    public function rent_includes_is_the_checklist_on_quick_import_and_survives_review_and_back(): void
    {
        $component = $this->landlordToTerms('QI-RI-L', 'Residential Lease');

        $html = $component->lastRenderedDom;
        $this->assertStringContainsString("\$wire.entangle('rent_includes').defer", $html);
        $this->assertStringContainsString('utility-checklist-card', $html);
        $this->assertStringContainsString("toggle('Internet')", $html);
        $this->assertStringContainsString('id="other_rent_includes_wrapper" x-show="isSelected(\'Other\')"', $html);

        $component->set('rent_includes', ['Internet', 'Other'])
            ->set('other_rent_include', 'Lawn care')
            ->call('continueToReview')
            ->assertSet('step', 'review');

        $this->assertSame('Internet, Lawn care', $component->viewData('termsReview')['Rent includes'] ?? null);

        $component->call('backToTerms')
            ->assertSet('rent_includes', ['Internet', 'Other'])
            ->assertSet('other_rent_include', 'Lawn care');

        $this->assertStringContainsString("\$wire.entangle('rent_includes').defer", $component->lastRenderedDom);
    }

    // ─── D. Select2 — one initializer path ──────────────────────────────────

    /**
     * @test
     *
     * There is one Select2 definition for this form, in select2-stable.js, and
     * it is the configuration Create always used: placeholder from
     * data-placeholder, allowClear, full width, the menu kept open for multiple
     * picks, and a guard that makes a second call a no-op.
     */
    public function the_form_has_exactly_one_select2_definition(): void
    {
        $definers = [];

        foreach (['resources/views', 'public/js'] as $root) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($root), \FilesystemIterator::SKIP_DOTS));

            foreach ($it as $file) {
                if (! preg_match('/\.(php|js)$/', $file->getFilename())) {
                    continue;
                }
                $count = substr_count(file_get_contents($file->getPathname()), 'initFullServiceSelect2Multiple = function');
                if ($count > 0) {
                    $definers[str_replace(base_path() . '/', '', $file->getPathname())] = $count;
                }
            }
        }

        $this->assertSame([self::SELECT2_STABLE => 1], $definers);

        $definition = $this->callbackBodies($this->src(self::SELECT2_STABLE), 'window.initFullServiceSelect2Multiple = function')[0];
        $this->assertStringContainsString("if (\$el.hasClass('select2-hidden-accessible')) return;", $definition);
        $this->assertStringContainsString("\$el.data('placeholder') || 'Select'", $definition);
        $this->assertStringContainsString("allowClear: true, width: '100%', closeOnSelect: false", $definition);
    }

    /**
     * @test
     *
     * The canonical Sale Terms initializer covers the tab's three Select2
     * controls and configures nothing itself.
     */
    public function the_canonical_sale_terms_initializer_uses_the_single_definition(): void
    {
        $body = $this->callbackBodies($this->markup(self::SELLER_BEHAVIOUR), 'window.initSellerTermsSelect2 = function')[0] ?? '';

        $this->assertNotSame('', $body, 'the Sale Terms behaviour partial must define window.initSellerTermsSelect2');
        $this->assertStringContainsString("['#sale_provision', '#offered_financing']", $body);
        $this->assertStringContainsString("\$('#exchange_item')", $body);
        $this->assertStringContainsString('window.initFullServiceSelect2Multiple($el)', $body);
        $this->assertStringContainsString('window.initFullServiceSelect2Multiple($ex)', $body);
        $this->assertStringNotContainsString('.select2(', $body, 'the initializer must not carry a second Select2 configuration');
    }

    /** @test The Leasing Terms counterpart, for the landlord tab's three Select2 controls. */
    public function the_canonical_leasing_terms_initializer_uses_the_single_definition(): void
    {
        $body = $this->callbackBodies($this->markup(self::LANDLORD_BEHAVIOUR), 'window.initLandlordLeaseTermsSelect2 = function')[0] ?? '';

        $this->assertNotSame('', $body);
        $this->assertStringContainsString("['.lease_term_options', '#owner_pays', '#terms_of_lease']", $body);
        $this->assertStringContainsString('window.initFullServiceSelect2Multiple($el)', $body);
        $this->assertStringNotContainsString('.select2(', $body);
        $this->assertStringNotContainsString('tenant_pays', $body, 'Tenant Pays is an Alpine checklist, never Select2');
        $this->assertStringNotContainsString('rent_includes', $body, 'Rent Includes is an Alpine checklist, never Select2');
        $this->assertStringNotContainsString('.on(', $body, 'the landlord controls are synchronised by the delegated handlers; this binds nothing');
    }

    /**
     * @test
     *
     * Seller Create and Seller Edit reach the three controls ONLY through the
     * canonical initializer. Create used to bind its own element-level change
     * handler to both parents on top of the shared delegated one — one change,
     * two @this.set() round trips — and both pages kept their own Exchange Item
     * block. None of that may come back.
     */
    public function seller_create_and_edit_initialise_the_sale_terms_select2_only_through_it(): void
    {
        foreach (self::SELLER_WRAPPERS as $wrapper) {
            $src = $this->markup($wrapper);

            $this->assertGreaterThanOrEqual(1, substr_count($src, 'window.initSellerTermsSelect2({ ownsBinding: true })'),
                "{$wrapper} must initialise the Sale Terms Select2 controls through the canonical initializer");

            foreach ([
                "/\\\$\\('#offered_financing'\\)\\.(?:select2|on)\\(/"                   => 'initialises or binds #offered_financing itself',
                "/\\\$\\('#sale_provision'\\)\\.(?:select2|on)\\(/"                      => 'initialises or binds #sale_provision itself',
                "/initFullServiceSelect2Multiple\\(\\\$\\('#(?:offered_financing|sale_provision|exchange_item)'\\)\\)/" => 'calls the definition directly for a Sale Terms control',
                "/(?:\\\$exEl|\\\$\\('#exchange_item'\\))\\.(?:select2|on)\\(/"          => 'initialises or binds #exchange_item itself',
            ] as $pattern => $what) {
                $this->assertDoesNotMatchRegularExpression($pattern, $src, "{$wrapper} {$what}");
            }

            $this->assertStringNotContainsString("'of-change-bound'", $src);
            $this->assertStringNotContainsString("'sp-change-bound'", $src);
        }
    }

    /**
     * @test
     *
     * Exchange Item is the one control the initializer binds, and it binds it
     * only where it owns the binding — so a page that initialised the control
     * itself (Hire Seller Agent, the tenant screens) never gains a second handler.
     */
    public function the_exchange_item_binding_is_added_only_where_the_initializer_owns_it(): void
    {
        $body = $this->callbackBodies($this->markup(self::SELLER_BEHAVIOUR), 'window.initSellerTermsSelect2 = function')[0];

        $this->assertStringContainsString("(initialisedHere || ownsBinding) && !\$ex.data('exchange-change-bound')", $body);
        $this->assertSame(1, substr_count($body, "\$ex.on('change'"));
        $this->assertStringContainsString("\$ex.data('exchange-change-bound', true)", $body);
        $this->assertStringContainsString("@this.set('exchange_item', selectedValues, false)", $body);
    }

    /**
     * @test
     *
     * Quick Import receives the initialisation after an update — which is how
     * its terms step arrives — 0 ms after the update's synchronous page hooks,
     * and never on livewire:load, which fires before the pages' own
     * DOMContentLoaded initialisation and would pre-empt it.
     */
    public function the_initializers_run_after_an_update_and_never_on_load(): void
    {
        foreach ([
            self::SELLER_BEHAVIOUR   => 'window.initSellerTermsSelect2();',
            self::LANDLORD_BEHAVIOUR => 'window.initLandlordLeaseTermsSelect2();',
        ] as $partial => $call) {
            $load = $this->callbackBodies($this->markup($partial), "document.addEventListener('livewire:load'")[0];

            [$beforeHook] = explode("Livewire.hook('message.processed'", $load, 2);
            $this->assertStringNotContainsString($call, $beforeHook, "{$partial} must not initialise on livewire:load");

            $hook = $this->callbackBodies($load, "Livewire.hook('message.processed'")[0];
            $this->assertStringContainsString("setTimeout(function () { {$call} }, 0);", $hook);
        }
    }

    /**
     * @test
     *
     * Quick Import declares no Select2 of its own — no script, no configuration,
     * no call. It receives the canonical initializer with the canonical partial.
     */
    public function quick_import_declares_no_select2_of_its_own(): void
    {
        $view = $this->markup(self::QUICK_IMPORT);

        foreach (['<script', 'select2(', 'initFullServiceSelect2Multiple', 'initSellerTermsSelect2', 'initLandlordLeaseTermsSelect2'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $view, "mls-quick-import.blade.php must not contain {$forbidden}");
        }

        $this->assertStringContainsString('_seller-terms-behaviour', $view);
        $this->assertStringContainsString('_lease-terms-behaviour', $view);
    }

    /** @test The real pages deliver the one definition and the one initializer, once each. */
    public function both_quick_import_pages_carry_the_shared_initializer_exactly_once(): void
    {
        $seller = $this->quickImportPage('seller');
        $this->assertStringContainsString('js/select2-stable.js', $seller);
        $this->assertSame(1, substr_count($seller, 'window.initSellerTermsSelect2 = function'));
        $this->assertSame(1, substr_count($seller, 'setTimeout(function () { window.initSellerTermsSelect2(); }, 0);'));

        $landlord = $this->quickImportPage('landlord');
        $this->assertStringContainsString('js/select2-stable.js', $landlord);
        $this->assertSame(1, substr_count($landlord, 'window.initLandlordLeaseTermsSelect2 = function'));
        $this->assertSame(1, substr_count($landlord, 'setTimeout(function () { window.initLandlordLeaseTermsSelect2(); }, 0);'));
    }

    /**
     * @test
     *
     * What the initializer finds on Quick Import is the canonical control: a
     * `select2-multiple` select inside a wire:ignore cover (so Livewire never
     * morphs the widget away), with its placeholder and icon.
     */
    public function offered_financing_is_the_canonical_select2_control_on_quick_import(): void
    {
        $html = $this->sellerToTerms('QI-S2-S')->lastRenderedDom;

        foreach (['sale_provision', 'offered_financing', 'exchange_item'] as $id) {
            $this->assertMatchesRegularExpression(
                '/<div class="input-cover has-select-icon" wire:ignore[^>]*>\s*<select id="' . $id . '" class="form-control has-icon select2-multiple"[^>]*data-placeholder="Select"[^>]*multiple/',
                $html,
                "#{$id} must reach Quick Import as the canonical wire:ignore Select2 multi-select"
            );
        }
    }

    /**
     * @test
     *
     * Review → Back re-renders the terms step. The widget is rebuilt on a fresh
     * select, so the answers must come back as `selected` options for Select2 to
     * pick up — and the branch the parent opens must still be open.
     */
    public function offered_financing_answers_survive_review_and_back(): void
    {
        $component = $this->sellerToTerms('QI-S2-BACK')
            ->set('offered_financing', ['Cash', 'Assumable'])
            ->call('continueToReview')
            ->assertSet('step', 'review')
            ->call('backToTerms')
            ->assertSet('offered_financing', ['Cash', 'Assumable']);

        $html = $component->lastRenderedDom;

        foreach (['Cash', 'Assumable'] as $option) {
            $this->assertMatchesRegularExpression(
                '/<select id="offered_financing"[^>]*>.*?<option value="' . $option . '"[^>]*selected/s',
                $html,
                "{$option} must come back selected after Review → Back"
            );
        }

        $this->assertMatchesRegularExpression('/id="seller-financing-assumable-section"[^>]*display:\s*block/', $html);
    }

    /** @test The landlord Select2 controls come back with their answers too. */
    public function landlord_select2_answers_survive_review_and_back(): void
    {
        $component = $this->landlordToTerms('QI-S2-L', 'Commercial Lease')
            ->set('owner_pays', ['Gas'])
            ->call('continueToReview')
            ->assertSet('step', 'review')
            ->call('backToTerms')
            ->assertSet('owner_pays', ['Gas']);

        $this->assertMatchesRegularExpression(
            '/<select id="owner_pays"[^>]*>.*?<option value="Gas"[^>]*selected/s',
            $component->lastRenderedDom
        );
    }
}
