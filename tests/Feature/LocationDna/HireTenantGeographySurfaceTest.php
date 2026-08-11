<?php

namespace Tests\Feature\LocationDna;

use App\Http\Livewire\TenantAgentAuction;
use App\Http\Livewire\TenantAgentAuctionEdit;
use ReflectionClass;
use Tests\TestCase;

/**
 * Step 3 — the Hire Tenant tab renders the geography cascade, and STILL DOES NOT RUN IT.
 *
 * WHY THE VIEW LANDS BEFORE THE WORKFLOW KEY
 * ------------------------------------------
 * {@see SellerLandlordCascadeExclusionTest::every_configured_workflow_already_renders_the_cascade}
 * forbids the reverse order: a workflow in `geography_cascade_workflows` whose tab has not opted
 * in writes four empty geography keys over stored data. So the view opt-in has to come first, and
 * the gap between the two commits is exactly the window in which this suite is the only thing
 * asserting that the surface is inert.
 *
 * That is what the three sections below are for, one per claim:
 *
 *   1. the cascade surface is present on the Tenant tab, on create AND edit;
 *   2. Tenant still resolves to NO workflow, so nothing renders and nothing writes;
 *   3. with the cascade on, the shared widget is no longer the geography editor — its four tier
 *      inputs and the Google place autocomplete bound to them are gone, while the map, the draw
 *      tools and Important Places stay.
 *
 * Section 3 RENDERS the partial rather than reading it. The suppression is a Blade conditional
 * around ~90 lines of inputs; a source scan would assert the conditional exists and prove nothing
 * about what comes out of it.
 */
class HireTenantGeographySurfaceTest extends TestCase
{
    /** The Tenant property-preferences tab — the one `SellerLandlordCascadeExclusionTest` maps. */
    private const TENANT_TAB = 'resources/views/livewire/tenant-agent-auction-tabs/commission-based/property-details.blade.php';

    private const MAP_INPUT = 'resources/views/partials/location-dna/map-input.blade.php';

    /**
     * Every view that routes a Tenant to the tab above — the create hosts, plus the edit host.
     *
     * All five gate the include behind `$user_type === 'tenant'`, which is what keeps a shared tab
     * from becoming a shared surface. Listed rather than globbed so a new host that forgets the
     * guard fails here instead of shipping.
     *
     * @var list<string>
     */
    private const HOST_VIEWS = [
        'resources/views/livewire/tenant-agent-auction.blade.php',
        'resources/views/livewire/tenant-agent-auction-edit.blade.php',
        'resources/views/livewire/hire-buyer-agent/hire-buyer-agent.blade.php',
        'resources/views/livewire/hire-seller-agent/hire-seller-agent.blade.php',
        'resources/views/livewire/hire-landlord-agent/hire-landlord-agent.blade.php',
    ];

    private function tab(): string
    {
        return (string) file_get_contents(base_path(self::TENANT_TAB));
    }

    /**
     * Render the shared widget alone.
     *
     * Every variable it reads of its own is null-coalesced. `$errors` is the exception: the
     * partial uses `@error`, and the ViewErrorBag is normally shared by the middleware that no
     * standalone render runs. Supplying an empty one is what a request with no validation failures
     * would supply.
     */
    private function renderMapInput(bool $cascade): string
    {
        return (string) view('partials.location-dna.map-input', [
            'existingLocationDna'     => ['state' => 'Florida', 'counties' => ['Pinellas County, FL']],
            'mapPanelId'              => 'ldna-map-hire-tenant',
            'enableImportantPlaces'   => true,
            'existingImportantPlaces' => [],
            'ldnaGeographyCascade'    => $cascade,
            'errors'                  => new \Illuminate\Support\ViewErrorBag(),
        ])->render();
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · THE TENANT VIEW INCLUDES THE CASCADE SURFACE
    // ═════════════════════════════════════════════════════════════════════════

    /** @test */
    public function the_tenant_tab_includes_the_cascade_partial(): void
    {
        $this->assertStringContainsString('partials.location-dna.geography-cascade', $this->tab());
    }

    /** @test */
    public function the_cascade_partial_exists(): void
    {
        $this->assertFileExists(base_path('resources/views/partials/location-dna/geography-cascade.blade.php'));
    }

    /**
     * GUARDED, NOT UNCONDITIONAL. The include has to sit behind the host's own flag, or the tab
     * would render a cascade for Seller and Landlord — who reach this file through the catch-all
     * and declare no such property.
     *
     * @test
     */
    public function the_cascade_include_is_guarded_by_the_host_flag(): void
    {
        $tab = $this->tab();

        $this->assertMatchesRegularExpression(
            '/@if\s*\(\s*\$geoCascadeEnabled\s*\?\?\s*false\s*\)\s*\R\s*@include\(\'partials\.location-dna\.geography-cascade\'\)/',
            $tab,
            'the cascade include must be guarded by `$geoCascadeEnabled ?? false`'
        );
    }

    /** The cascade renders ABOVE the widget it supersedes, as it does on the Buyer tab. */
    /** @test */
    public function the_cascade_precedes_the_shared_widget(): void
    {
        $tab = $this->tab();

        $cascade = strpos($tab, 'partials.location-dna.geography-cascade');
        $widget  = strpos($tab, 'partials.location-dna.map-input');

        // Both looked up before either is compared: a missing include yields `false`, which
        // coerces to 0 and would satisfy the ordering assertion by being absent.
        $this->assertNotFalse($cascade, 'the tab does not include the cascade at all');
        $this->assertNotFalse($widget, 'the tab does not include the shared widget at all');

        $this->assertLessThan($widget, $cascade);
    }

    /**
     * BOTH the create and the edit host reach this tab, so the surface cannot appear on one and
     * not the other — the divergence that made the Buyer edit path miss the search box.
     *
     * @test
     */
    public function every_host_view_routes_tenants_to_this_tab_behind_a_role_guard(): void
    {
        foreach (self::HOST_VIEWS as $relative) {
            $source = (string) file_get_contents(base_path($relative));

            $this->assertStringContainsString(
                'livewire.tenant-agent-auction-tabs.commission-based.property-details',
                $source,
                "{$relative} no longer routes to the Tenant tab"
            );

            // Two spellings of the same guard: the three hire hosts branch with `@if`, the two
            // tenant hosts with `@switch($user_type)`. Both are accepted; an UNGUARDED include is
            // what must fail, because that is what would put the tab in front of another role.
            $this->assertMatchesRegularExpression(
                '/(@if\s*\(\s*\$user_type\s*===\s*\'tenant\'\s*\)|@case\(\'tenant\'\))\s*\R\s*'
                .'@include\(\'livewire\.tenant-agent-auction-tabs\.commission-based\.property-details\'\)/',
                $source,
                "{$relative}: the Tenant tab must stay behind a tenant role guard"
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · TENANT STILL DOES NOT ACTIVATE THE WORKFLOW
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * @return list<class-string>
     */
    public static function catchAllComponents(): array
    {
        return [[TenantAgentAuction::class], [TenantAgentAuctionEdit::class]];
    }

    /**
     * The behavioural claim, with EVERY GATE FORCED OPEN — deliberately more permissive than any
     * environment will be. Tenant maps to a null workflow, so the cascade is off no matter what
     * the configuration says, and the view opt-in above changes nothing about that.
     *
     * @dataProvider catchAllComponents
     * @test
     */
    public function a_tenant_resolves_to_no_workflow_even_with_every_gate_open(string $class): void
    {
        config([
            'criteria_location_dna.geography_cascade_enabled'   => true,
            'criteria_location_dna.geography_cascade_workflows' => ['hire_buyer', 'hire_tenant'],
            'criteria_location_dna.geography_source'            => 'census',
            'criteria_location_dna.neighborhood_tier_enabled'   => true,
            'criteria_location_dna.geography_search_enabled'    => true,
        ]);

        $component = (new ReflectionClass($class))->newInstanceWithoutConstructor();
        $component->user_type = 'tenant';

        $workflow = new \ReflectionMethod($class, 'geographyCascadeWorkflow');
        $workflow->setAccessible(true);

        $this->assertNull($workflow->invoke($component), "{$class} must not map tenant to a workflow");

        $boot = new \ReflectionMethod($class, 'bootGeographyCascade');
        $boot->setAccessible(true);
        $boot->invoke($component, $workflow->invoke($component));

        $this->assertFalse($component->geoCascadeEnabled, "{$class}: the cascade must stay off for tenant");
        $this->assertFalse($component->geoNeighborhoodTierEnabled);
    }

    /** The mapping itself, pinned in source — `hire_tenant` is a later, separate decision. */
    /** @test */
    public function the_workflow_map_still_names_buyer_only(): void
    {
        foreach (['app/Http/Livewire/TenantAgentAuction.php', 'app/Http/Livewire/TenantAgentAuctionEdit.php'] as $relative) {
            $source = (string) file_get_contents(base_path($relative));
            $start  = strpos($source, 'protected function geographyCascadeWorkflow(): ?string');
            $body   = substr($source, (int) $start, (int) strpos($source, "\n    }", (int) $start) - (int) $start);

            $this->assertStringContainsString("'buyer' => 'hire_buyer',", $body);
            $this->assertStringContainsString('default => null,', $body);
            $this->assertStringNotContainsString("'tenant' =>", $body, 'this step renders the surface; it does not enable it');
        }
    }

    /** @test */
    public function the_shipped_config_scope_is_still_buyer_only(): void
    {
        $config = require base_path('config/criteria_location_dna.php');

        $this->assertSame(['hire_buyer'], $config['geography_cascade_workflows']);
        $this->assertFalse($config['geography_search_enabled']);
    }

    /** The ZIP mirror allowlist is not this step's business and must be untouched. */
    /** @test */
    public function the_zip_mirror_allowlist_is_unchanged(): void
    {
        $this->assertStringContainsString(
            "private const ZIP_MIRROR_WORKFLOWS = ['hire_tenant'];",
            (string) file_get_contents(base_path('app/Http/Livewire/Concerns/HasGeographyCascade.php')),
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · THE SHARED WIDGET IS NO LONGER THE GEOGRAPHY EDITOR
    // ═════════════════════════════════════════════════════════════════════════

    /** The tab opts in, and wires the opt-in to the host flag rather than hardcoding it. */
    /** @test */
    public function the_tab_hands_the_cascade_flag_to_the_shared_widget(): void
    {
        $this->assertMatchesRegularExpression(
            '/\'ldnaGeographyCascade\'\s*=>\s*\$geoCascadeEnabled\s*\?\?\s*false,/',
            $this->tab(),
        );
    }

    /**
     * The four controls that write a tier key, matched as MARKUP (`id="…"`) rather than as bare
     * strings: the widget's script block references the same ids in its `getElementById` lookups
     * and is emitted either way, so a bare-substring assertion would be testing the JavaScript
     * instead of the DOM.
     *
     * @var list<string>
     */
    private const TIER_CONTROLS = [
        'id="ldna-cities-input"',
        'id="ldna-zips-input"',
        'id="ldna-counties-input"',
        'id="ldna-state-input"',
    ];

    /**
     * THE CLAIM: with the cascade active the widget renders no control that writes a tier key.
     *
     * @test
     */
    public function the_widget_renders_no_tier_inputs_when_the_cascade_is_active(): void
    {
        $html = $this->renderMapInput(true);

        foreach (self::TIER_CONTROLS as $control) {
            $this->assertStringNotContainsString($control, $html, "{$control} still renders beside the cascade");
        }
    }

    /**
     * The counterfactual, so the assertion above cannot pass because the ids were renamed.
     *
     * @test
     */
    public function the_widget_still_renders_its_tier_inputs_when_the_cascade_is_off(): void
    {
        $html = $this->renderMapInput(false);

        foreach (self::TIER_CONTROLS as $control) {
            $this->assertStringContainsString($control, $html, "{$control} vanished from the legacy surface");
        }
    }

    /**
     * SUPPRESSING THE INPUTS IS WHAT DEACTIVATES GOOGLE, and it does so without an error.
     *
     * The tier autocompletes attach by `getElementById`, so with the inputs absent they find
     * nothing. Both initialisers return early on a missing element rather than constructing an
     * `Autocomplete` against null — which is why removing the inputs is sufficient, and why the
     * page does not throw where the Google tier lookup used to run.
     *
     * @test
     */
    public function the_google_tier_autocomplete_cannot_attach_when_the_cascade_is_active(): void
    {
        $html   = $this->renderMapInput(true);
        $source = (string) file_get_contents(base_path(self::MAP_INPUT));

        foreach (['ldna-cities-input', 'ldna-counties-input'] as $id) {
            $this->assertStringNotContainsString("id=\"{$id}\"", $html, "{$id} is still in the DOM for Google to bind");

            $this->assertMatchesRegularExpression(
                '/getElementById\(\''.preg_quote($id, '/').'\'\);\s*\R\s*if \(!input/',
                $source,
                "the initialiser for {$id} must return early when the element is absent"
            );
        }
    }

    /**
     * The cascade replaces the TIER INPUTS and nothing else. Conflating the two would turn a
     * contained increment into a rewrite of the whole editor.
     *
     * @test
     */
    public function the_map_draw_tools_and_important_places_survive_the_cascade(): void
    {
        $html = $this->renderMapInput(true);

        foreach ([
            'ldna-map-hire-tenant',   // the map panel itself
            'ldna-map-toolbar',       // draw tools
            'ldna-ip-rows',           // Important Places
        ] as $marker) {
            $this->assertStringContainsString($marker, $html, "{$marker} must survive the cascade");
        }
    }

    /**
     * TODAY'S SHIPPED BEHAVIOUR, asserted end to end: Tenant is off, so the tab renders the legacy
     * widget exactly as it did before this change. This is the test that says the step is inert.
     *
     * @test
     */
    public function the_tenant_surface_is_unchanged_while_the_workflow_stays_unwired(): void
    {
        $config = require base_path('config/criteria_location_dna.php');

        $this->assertNotContains('hire_tenant', $config['geography_cascade_workflows']);

        $component = (new ReflectionClass(TenantAgentAuction::class))->newInstanceWithoutConstructor();
        $component->user_type = 'tenant';

        $workflow = new \ReflectionMethod(TenantAgentAuction::class, 'geographyCascadeWorkflow');
        $workflow->setAccessible(true);

        $boot = new \ReflectionMethod(TenantAgentAuction::class, 'bootGeographyCascade');
        $boot->setAccessible(true);
        $boot->invoke($component, $workflow->invoke($component));

        // `$geoCascadeEnabled` false is what the tab's guard reads, so the cascade does not render
        // and the widget receives false — the legacy tier inputs.
        $this->assertFalse($component->geoCascadeEnabled);

        $html = $this->renderMapInput($component->geoCascadeEnabled);

        foreach (self::TIER_CONTROLS as $control) {
            $this->assertStringContainsString($control, $html, "{$control} must still render for Tenant today");
        }
    }
}
