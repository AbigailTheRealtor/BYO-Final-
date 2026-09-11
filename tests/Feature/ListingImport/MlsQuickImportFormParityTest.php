<?php

namespace Tests\Feature\ListingImport;

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
 *   this change          — the icon RENDERER and the selection-grid CSS lived in
 *                          the wrappers; Quick Import had neither, so every icon
 *                          was invisible and Tenant Pays / Rent Includes rendered
 *                          as an unstyled vertical list.
 *
 * These tests pin the shared architecture rather than the symptoms, because the
 * symptom is different every time and the cause is always the same.
 *
 * WHY THE ASSERTIONS ARE STRUCTURAL
 * ---------------------------------
 * There is no browser in this suite. What IS decidable here, and what actually
 * broke, is whether the canonical environment is wired to every consumer: whether
 * the renderer exists once, whether each page includes it, whether the icon a
 * field declares is the same one for every entry path. A test that clicked a
 * checklist card would be a lie about what PHPUnit did; final confirmation that
 * the cards respond is manual browser verification, and is stated as such.
 */
class MlsQuickImportFormParityTest extends TestCase
{
    private const SHARED_ICONS = 'resources/views/livewire/offer-listing/shared/_field-icons.blade.php';
    private const SHARED_GRID  = 'resources/views/livewire/offer-listing/shared/_selection-grid-styles.blade.php';

    private const QUICK_IMPORT = 'resources/views/livewire/offer-listing/quick-import/mls-quick-import.blade.php';
    private const SELLER_TERMS = 'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/seller-terms.blade.php';
    private const LEASE_TERMS  = 'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/lease-terms.blade.php';

    /** The four regular Create/Edit wrappers that were de-duplicated. */
    private const WRAPPERS = [
        'resources/views/livewire/offer-listing/seller/offer-seller-listing.blade.php',
        'resources/views/livewire/offer-listing/seller/offer-seller-listing-edit.blade.php',
        'resources/views/livewire/offer-listing/landlord/offer-landlord-listing.blade.php',
        'resources/views/livewire/offer-listing/landlord/offer-landlord-listing-edit.blade.php',
    ];

    private function src(string $relative): string
    {
        return file_get_contents(base_path($relative));
    }

    /** Template markup with Blade comments stripped — prose is not a declaration. */
    private function markup(string $relative): string
    {
        return preg_replace('/\{\{--.*?--\}\}/s', '', $this->src($relative)) ?? '';
    }

    // ─── A. The icon renderer exists exactly once ────────────────────────────

    /**
     * @test
     *
     * The renderer was defined nineteen times across the app and zero times in
     * Quick Import. The Offer Listing family now has ONE definition. This asserts
     * the definition has actually moved rather than been copied a twentieth time.
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
     * The guard is the LANDLORD's, and that is load-bearing rather than
     * arbitrary. Landlord property-preferences has 81 `.input-cover` blocks
     * carrying both a hand-authored `<i class="input-icon">` and a `data-icon`
     * input; skipping on `.input-icon` is what stops a second icon being stacked
     * on all 81. Seller has zero such blocks, so the stricter guard changes
     * nothing there.
     */
    public function the_shared_renderer_keeps_the_guard_that_prevents_double_icons(): void
    {
        $shared = $this->src(self::SHARED_ICONS);

        $this->assertStringContainsString(
            "if (wrapper.querySelector('.input-icon')) return;",
            $shared,
            'the superset guard must be preserved or landlord fields gain a second icon'
        );

        $this->assertStringContainsString(
            'input-icon ${iconClass} data-icon-rendered',
            $shared,
            "the seller's marker class is kept as a test hook"
        );

        // The case the guard protects still exists in the landlord markup, so the
        // guard is not dead code.
        $landlordPrefs = $this->markup(
            'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/property-preferences.blade.php'
        );

        $this->assertGreaterThan(
            0,
            substr_count($landlordPrefs, 'class="input-icon'),
            'landlord property-preferences still authors icons the guard must respect'
        );
    }

    // ─── B. Exact icon parity, field by field ───────────────────────────────

    /**
     * @dataProvider iconFields
     * @test
     *
     * The strong form of icon parity. Quick Import and Create do not merely both
     * "have an icon" — they render the SAME canonical partial, so the icon for a
     * given question is one declaration and cannot differ between entry paths.
     * This asserts the declaration exists, is on a `.has-icon` control, and is
     * the exact class named.
     */
    public function a_field_declares_one_icon_for_every_entry_path(
        string $partial,
        string $field,
        string $iconClass
    ): void {
        $markup = $this->markup($partial);

        $this->assertMatchesRegularExpression(
            '/wire:model[^"]*="' . preg_quote($field, '/') . '"[^>]*?data-icon="' . preg_quote($iconClass, '/') . '"'
            . '|data-icon="' . preg_quote($iconClass, '/') . '"[^>]*?wire:model[^"]*="' . preg_quote($field, '/') . '"/s',
            $markup,
            "{$field} must declare data-icon=\"{$iconClass}\" in the canonical partial, "
            . 'which is the single source Create, Edit and Quick Import all render'
        );
    }

    /**
     * Structurally distinct controls, deliberately: a currency input, a
     * percentage input, a date control, a plain select, a free-text input and a
     * landlord lease control. Icon classes are read OFF the canonical partial —
     * none is chosen here by meaning.
     */
    public function iconFields(): array
    {
        // Every pair below was READ OFF the canonical partial, not chosen by
        // meaning. Structurally distinct controls on purpose: text, select, date,
        // number, textarea — the shapes the renderer has to handle.
        //
        // Currency inputs are deliberately absent: they carry no data-icon at all,
        // because a money field shows the `$` prefix span instead of an icon. A
        // test demanding an icon there would be demanding a design change.
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

    /**
     * @test
     *
     * Currency controls carry no icon by design — the `$` prefix is their marker.
     * Asserted so a future reader does not "restore a missing icon" onto them.
     */
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
     * The canonical partials carry icons as attributes and author no <i> element
     * of their own — which is precisely why a consumer without the renderer
     * showed none of them. If someone "fixes" a missing icon by hand-authoring an
     * <i> into the terms partial, this fails.
     */
    public function the_canonical_terms_partials_declare_icons_only_as_data_attributes(): void
    {
        foreach ([self::SELLER_TERMS, self::LEASE_TERMS] as $partial) {
            $markup = $this->markup($partial);

            $this->assertGreaterThan(
                50,
                substr_count($markup, 'data-icon='),
                $partial . ' is expected to carry its icons as data-icon attributes'
            );

            $this->assertSame(
                0,
                substr_count($markup, 'class="input-icon'),
                $partial . ' must not hand-author an <i class="input-icon"> — the shared '
                . 'renderer creates it from data-icon, and an authored one would be skipped '
                . 'by the guard on some pages and duplicated on others'
            );
        }
    }

    // ─── C. Tenant Pays / Rent Includes ─────────────────────────────────────

    /**
     * @test
     *
     * Both are Alpine checklist grids in the canonical partial. They were never
     * static markup — they were unstyled, which is a different defect with a
     * different fix. This pins the control type so nobody "fixes" the appearance
     * by replacing them with a dropdown.
     */
    public function tenant_pays_and_rent_includes_are_alpine_checklists_bound_to_livewire(): void
    {
        $markup = $this->markup(self::LEASE_TERMS);

        foreach (['tenant_pays', 'rent_includes'] as $field) {
            $this->assertStringContainsString(
                "\$wire.entangle('{$field}').defer",
                $markup,
                "{$field} must stay entangled with the Livewire property"
            );
        }

        // The grid, the click target and the selected-state binding.
        $this->assertGreaterThanOrEqual(2, substr_count($markup, 'utility-checklist-grid'));
        $this->assertGreaterThanOrEqual(2, substr_count($markup, "@click=\"toggle('"));
        $this->assertGreaterThanOrEqual(2, substr_count($markup, "'utility-selected': isSelected("));

        // "Other" still reveals its companion free-text field.
        $this->assertStringContainsString('other_tenant_pays', $markup);
        $this->assertStringContainsString('other_rent_include', $markup);
        $this->assertStringContainsString("x-show=\"isSelected('Other')\"", $markup);
    }

    /**
     * @test
     *
     * The styling those grids need is now shared, defined once, and reaches Quick
     * Import. Without it the cards have no `display: grid`, no border, no cursor
     * and no visible selected state — which is exactly what "a static-looking
     * vertical list" was.
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

        // Landlord Create, Edit and Quick Import all consume it…
        $include = "@include('livewire.offer-listing.shared._selection-grid-styles')";

        foreach ([
            'resources/views/livewire/offer-listing/landlord/offer-landlord-listing.blade.php',
            'resources/views/livewire/offer-listing/landlord/offer-landlord-listing-edit.blade.php',
        ] as $wrapper) {
            $this->assertSame(1, substr_count($this->markup($wrapper), $include), $wrapper);
        }

        // Quick Import includes it conditionally: the grids are landlord-only, so
        // a seller import has nothing to style and should not carry the CSS.
        $this->assertStringContainsString(
            "@includeWhen(\$role === 'landlord', 'livewire.offer-listing.shared._selection-grid-styles')",
            $this->markup(self::QUICK_IMPORT),
            'Quick Import must pull the shared grid styles in for the landlord role'
        );

        // …and nobody keeps a private copy.
        foreach (self::WRAPPERS as $wrapper) {
            $this->assertStringNotContainsString(
                '.utility-checklist-grid {',
                $this->markup($wrapper),
                $wrapper . ' must not redefine the grid styles'
            );
        }
    }

    /**
     * @test
     *
     * The de-duplication must not have taken unrelated CSS with it. The landlord
     * Edit wrapper's <style> continued past the checklist rules into
     * `.status-text`, `.status-icon` and `.user-selected`, which belong to that
     * page.
     */
    public function the_landlord_edit_wrapper_keeps_its_unrelated_styles(): void
    {
        $edit = $this->src('resources/views/livewire/offer-listing/landlord/offer-landlord-listing-edit.blade.php');

        foreach (['.status-text', '.status-icon', '.user-selected'] as $unrelated) {
            $this->assertStringContainsString($unrelated, $edit, "{$unrelated} belongs to this page and must survive");
        }
    }

    // ─── D. Select2 / initializer parity ────────────────────────────────────

    /**
     * @test
     *
     * Offered Financing is a Select2 multi-select on Create and a native
     * multi-select on Quick Import. Adding the shared icon renderer and the
     * container fixes CSS and icons; it does NOT initialise Select2, and this
     * change deliberately does not convert the control.
     *
     * Recorded here rather than left implicit so the gap is a known, asserted
     * state instead of something a future reader assumes was handled.
     */
    public function offered_financing_select2_initialisation_remains_a_create_only_concern(): void
    {
        $create = $this->markup('resources/views/livewire/offer-listing/seller/offer-seller-listing.blade.php');

        $this->assertStringContainsString(
            'initFullServiceSelect2Multiple',
            $create,
            'Create still owns the Select2 initialisation for #offered_financing'
        );

        $this->assertStringNotContainsString(
            'initFullServiceSelect2Multiple',
            $this->markup(self::QUICK_IMPORT),
            'Quick Import must not grow its own Select2 initialisation — if this control '
            . 'is to be shared, it goes through a canonical initializer, not a copy here'
        );

        // The conditional behaviour Quick Import DOES need already travels with
        // the markup, and still does.
        $this->assertStringContainsString(
            '_seller-terms-behaviour',
            $this->markup(self::QUICK_IMPORT)
        );
    }
}
