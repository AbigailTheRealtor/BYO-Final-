<?php

namespace Tests\Feature\LocationDna;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * T3 — the Create Tenant geography surface: rendered, suppressing its legacy twin, still gated.
 *
 * WHY THIS SUITE RENDERS RATHER THAN READS SOURCE
 * -----------------------------------------------
 * The rest of the cascade suites assert suppression by locating `@if (! $ldnaGeographyCascade)` in
 * the widget's source and checking what falls inside it. That proves the block exists; it cannot
 * prove the block is EFFECTIVE, and the distinction is not academic — the widget's JavaScript
 * references `ldna-cities-input` and `ldna-counties-input` by id in `getElementById` calls that sit
 * OUTSIDE the suppressed region, so a naive source or substring check reports those ids present in
 * both states and tells you nothing.
 *
 * So this suite renders the partial in both states and asserts on the output: zero live `<input>`
 * elements for the legacy tiers when the cascade owns geography, and the map, draw tools and
 * Important Places present either way. The surviving id references are guarded
 * (`if (!input || …) return;`) and no-op against an absent element.
 *
 * WHAT IS DELIBERATELY NOT ASSERTED HERE
 * --------------------------------------
 * That the cascade is ON. It is not: `create_tenant` is absent from the shipped scope list, so
 * `$geoCascadeEnabled` is false on every path a user can reach and this tab renders exactly what
 * it rendered before. {@see self::the_workflow_is_rendered_but_still_not_enabled()} pins that, and
 * it is the assertion that expires when the workflow is enabled.
 */
class CreateTenantGeographySurfaceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_TAB = 'resources/views/livewire/offer-listing/offer-tenant-tabs/commission-based/property-details.blade.php';
    private const BUYER_TAB  = 'resources/views/livewire/offer-listing/offer-buyer-tabs/commission-based/property-preferences.blade.php';
    private const WIDGET     = 'resources/views/partials/location-dna/map-input.blade.php';

    protected function setUp(): void
    {
        parent::setUp();

        // The widget reads `$errors` via @error directives. Outside a request nothing shares the
        // bag, and the partial fatals on `has()` against null before any assertion is reached.
        View::share('errors', new ViewErrorBag());
    }

    private function read(string $relative): string
    {
        return (string) file_get_contents(base_path($relative));
    }

    /** Render the shared widget as the Create Tenant tab renders it. */
    private function renderWidget(bool $cascadeActive): string
    {
        return view('partials.location-dna.map-input', [
            'existingLocationDna'     => [],
            'mapPanelId'              => 'ldna-map-tenant',
            'enableImportantPlaces'   => true,
            'existingImportantPlaces' => [],
            'ldnaGeographyCascade'    => $cascadeActive,
        ])->render();
    }

    /** Live `<input>` elements for the legacy geography tiers in rendered output. */
    private function liveLegacyTierInputs(string $html): array
    {
        preg_match_all('/<input[^>]*ldna-(cities|zips|counties)-input[^>]*>/i', $html, $matches);

        return $matches[0];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · THE TAB RENDERS THE CASCADE, BEHIND THE FLAG
    // ═════════════════════════════════════════════════════════════════════════

    /** @test */
    public function the_tenant_tab_includes_the_cascade_partial_behind_the_flag(): void
    {
        $tab = $this->read(self::TENANT_TAB);

        $this->assertStringContainsString(
            "@if (\$geoCascadeEnabled ?? false)\n    @include('partials.location-dna.geography-cascade')\n@endif",
            $tab,
            'The cascade include must be present and guarded exactly as the Create Buyer tab guards it.'
        );
    }

    /**
     * The guard is null-coalesced, which is what keeps four other components rendering.
     *
     * This tab is included by five root blades. `SellerOfferListing` and `LandlordOfferListing`
     * declare no `$geoCascadeEnabled` at all, so an unguarded reference fatals on an undefined
     * variable rather than degrading.
     *
     * @test
     */
    public function every_cascade_reference_in_the_tab_is_null_coalesced(): void
    {
        $tab = $this->read(self::TENANT_TAB);

        // Strip Blade comments — the explanatory block names the variable repeatedly.
        $code = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $tab);

        preg_match_all('/\$geoCascadeEnabled(?!\s*\?\?)/', $code, $unguarded);

        $this->assertSame(
            [],
            $unguarded[0],
            'Every $geoCascadeEnabled reference outside comments must carry `?? false`.'
        );
    }

    /** The widget receives the cascade state, which is what drives suppression. @test */
    public function the_map_widget_receives_the_cascade_state(): void
    {
        $this->assertStringContainsString(
            "'ldnaGeographyCascade'   => \$geoCascadeEnabled ?? false,",
            $this->read(self::TENANT_TAB),
            'Without this the widget would render its own tier inputs beside the cascade.'
        );
    }

    /** The tenant tab matches the shipped Create Buyer tab in shape. @test */
    public function the_tenant_tab_matches_the_create_buyer_pattern(): void
    {
        $tenant = $this->read(self::TENANT_TAB);
        $buyer  = $this->read(self::BUYER_TAB);

        foreach ([
            "@if (\$geoCascadeEnabled ?? false)",
            "@include('partials.location-dna.geography-cascade')",
            "'ldnaGeographyCascade'   => \$geoCascadeEnabled ?? false,",
            "@include('partials.location-dna.search-areas-bridge')",
        ] as $marker) {
            $this->assertStringContainsString($marker, $buyer, "Create Buyer lost: {$marker}");
            $this->assertStringContainsString($marker, $tenant, "Create Tenant missing: {$marker}");
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · LEGACY INPUTS ARE SUPPRESSED ONLY WHEN THE CASCADE IS ACTIVE
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * With the cascade active, not one legacy tier input renders.
     *
     * Asserted on rendered elements rather than on ids appearing in the markup, because the
     * widget's JS references two of those ids outside the suppressed block — a substring check
     * finds them in both states and proves nothing.
     *
     * @test
     */
    public function the_legacy_tier_inputs_do_not_render_when_the_cascade_is_active(): void
    {
        $this->assertSame(
            [],
            $this->liveLegacyTierInputs($this->renderWidget(true)),
            'The cascade owns geography — no second editor may render beside it.'
        );

        foreach (['Preferred Cities', 'Preferred ZIP'] as $label) {
            $this->assertStringNotContainsString($label, $this->renderWidget(true), $label);
        }
    }

    /** With the cascade inactive — every path today — they render exactly as before. @test */
    public function the_legacy_tier_inputs_still_render_when_the_cascade_is_inactive(): void
    {
        $html = $this->renderWidget(false);

        $this->assertNotEmpty(
            $this->liveLegacyTierInputs($html),
            'With the cascade off the widget must remain the geography editor.'
        );

        foreach (['Preferred Cities', 'Preferred ZIP'] as $label) {
            $this->assertStringContainsString($label, $html, $label);
        }
    }

    /**
     * Suppression is driven by the flag and by nothing else.
     *
     * Both renders come from one partial with one differing argument, so a difference in output
     * can only be attributable to that argument.
     *
     * @test
     */
    public function the_two_renders_differ_only_by_the_flag(): void
    {
        $off = $this->renderWidget(false);
        $on  = $this->renderWidget(true);

        $this->assertNotSame($off, $on, 'The flag must change what renders.');
        $this->assertLessThan(
            strlen($off),
            strlen($on),
            'The cascade render must be the smaller one — it removes inputs, it does not add any.'
        );
    }

    /** The header copy follows the flag, so the surface explains itself. @test */
    public function the_widget_header_reflects_which_editor_owns_geography(): void
    {
        $this->assertStringContainsString('Refine the areas you chose above', $this->renderWidget(true));
        $this->assertStringNotContainsString('Refine the areas you chose above', $this->renderWidget(false));
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · THE MAP, DRAW TOOLS AND IMPORTANT PLACES SURVIVE
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The cascade replaces the tier inputs only — never the drawing surface.
     *
     * Polygons, radius searches and Important Places belong to the widget and are keys the cascade
     * never states. Suppressing them would silently drop stored data the projection preserves.
     *
     * @test
     */
    public function the_map_draw_tools_and_important_places_render_in_both_states(): void
    {
        foreach ([false, true] as $cascadeActive) {
            $html  = $this->renderWidget($cascadeActive);
            $label = $cascadeActive ? 'cascade active' : 'cascade inactive';

            $this->assertNotSame('', $html, "{$label}: the widget rendered nothing at all");

            foreach ([
                'ldna-map-tenant',            // the map panel itself
                'Draw Custom Areas on Map',   // the draw toolbar
                'Important Places',           // the additive rows
            ] as $marker) {
                $this->assertStringContainsString($marker, $html, "{$label}: {$marker} must survive");
            }
        }
    }

    /** The suppressed region ends before the map toolbar begins. @test */
    public function the_suppressed_region_stops_short_of_the_map(): void
    {
        $widget = $this->read(self::WIDGET);

        $open  = strpos($widget, '@if (! $ldnaGeographyCascade)');
        $close = strpos($widget, '@endif', (int) $open);
        $map   = strpos($widget, 'Draw Custom Areas on Map');

        $this->assertNotFalse($open, 'the tier inputs must be conditional');
        $this->assertNotFalse($close);
        $this->assertNotFalse($map);
        $this->assertLessThan($map, $close, 'The map toolbar must fall OUTSIDE the suppressed block.');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 4 · EVERY OTHER SURFACE IS UNAFFECTED
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The three non-tenant root blades include this tab behind an unreachable role branch.
     *
     * Each of those components pins `$user_type` to its own role, so the branch never runs — the
     * role guard is already upstream and the coalesce guards the other axis.
     *
     * @test
     */
    public function the_non_tenant_root_blades_guard_the_include_by_role(): void
    {
        foreach ([
            'resources/views/livewire/offer-listing/seller/offer-seller-listing.blade.php',
            'resources/views/livewire/offer-listing/buyer/offer-buyer-listing.blade.php',
            'resources/views/livewire/offer-listing/landlord/offer-landlord-listing.blade.php',
        ] as $relative) {
            $source  = $this->read($relative);
            $include = strpos($source, "@include('livewire.offer-listing.offer-tenant-tabs.commission-based.property-details')");

            $this->assertNotFalse($include, "{$relative}: include not found");

            $guard = strrpos(substr($source, 0, $include), "@if (\$user_type === 'tenant')");
            $this->assertNotFalse($guard, "{$relative}: the include must sit behind a tenant role guard");
        }
    }

    /** Seller and Landlord Offer components declare no cascade state at all. @test */
    public function the_seller_and_landlord_components_declare_no_cascade_state(): void
    {
        foreach ([
            'app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php',
            'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php',
        ] as $relative) {
            $source = $this->read($relative);

            $this->assertStringNotContainsString('HasGeographyCascade', $source, $relative);
            $this->assertStringNotContainsString('geoCascadeEnabled', $source, $relative);
        }
    }

    /** T3 touched no Seller, Landlord or Buyer view. @test */
    public function no_seller_landlord_or_buyer_view_gained_a_cascade_surface(): void
    {
        foreach ([
            'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/property-preferences.blade.php',
            'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/property-preferences.blade.php',
        ] as $relative) {
            if (! file_exists(base_path($relative))) {
                continue;
            }

            $this->assertStringNotContainsString(
                'geography-cascade',
                $this->read($relative),
                "{$relative}: Seller and Landlord have no geography surface"
            );
        }

        // The Buyer tab keeps its own cascade block, unchanged by T3.
        $this->assertStringContainsString(
            "@include('partials.location-dna.geography-cascade')",
            $this->read(self::BUYER_TAB),
            'Create Buyer must keep the surface it shipped with.'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 5 · RENDERED, BUT NOT ENABLED
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The view opt-in landed; the workflow did not.
     *
     * This is the safe half of the pair. The reverse — a workflow listed in config whose tab has
     * not opted in — states four empty geography keys over stored data, which is why the view has
     * to come first and why this assertion exists to prove it did not come with the other half.
     *
     * @test
     */
    public function the_workflow_is_rendered_but_still_not_enabled(): void
    {
        $config = require base_path('config/criteria_location_dna.php');

        $this->assertNotContains(
            'create_tenant',
            $config['geography_cascade_workflows'],
            'T3 renders the surface; it must not enable the workflow.'
        );
        $this->assertFalse($config['geography_cascade_enabled'], 'The master gate must still ship off.');

        // Both halves of the safety rule, stated together: the tab HAS opted in, and the workflow
        // is NOT listed. That combination is inert and reversible; the opposite is data loss.
        $this->assertStringContainsString('ldnaGeographyCascade', $this->read(self::TENANT_TAB));
    }
}
