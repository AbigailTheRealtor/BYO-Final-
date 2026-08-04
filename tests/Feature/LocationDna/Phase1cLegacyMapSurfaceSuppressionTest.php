<?php

namespace Tests\Feature\LocationDna;

use App\Http\Livewire\HireBuyerAgent\BuyerAgentAuction as HireBuyerCreate;
use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing as CreateBuyer;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing as CreateTenant;
use App\Http\Livewire\TenantAgentAuction as HireCatchAll;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 1c — suppressing the legacy map surface where the cascade is active.
 *
 * WHAT THIS COVERS AND WHY IT IS SEPARATE
 * ---------------------------------------
 * The per-workflow suites prove each workflow renders the cascade. This proves the other half of
 * that swap, which is shared by all four and belongs in one place: the drawing tools, the map
 * canvas and the radius search are GONE where the cascade is on, still THERE where it is off, and
 * the geometry those tools produced is neither lost nor left invisible.
 *
 * THE THREE FAILURES THIS IS BUILT AROUND
 * ---------------------------------------
 *   1. AN UNBOUNDED RETRY LOOP. `ldnaTryInit()` polls every 200ms for the map container, and that
 *      branch has no timeout — unlike the Google-not-loaded branch above it. Removing the panel
 *      without a cascade-mode guard leaves a timer running forever on every migrated page.
 *   2. IMPORTANT PLACES SILENTLY BREAKING. Its geocode path used to wait for a map that, in
 *      cascade mode, never arrives — so a typed address would never resolve to coordinates.
 *   3. ORPHANED GEOMETRY. Polygons and radius searches saved by the old editor still count toward
 *      matching. They must survive a save, must be visible, and must be removable only on purpose.
 */
class Phase1cLegacyMapSurfaceSuppressionTest extends TestCase
{
    use DatabaseTransactions;

    /** Markers for the surfaces being suppressed. Asserted as MARKUP, not bare ids: the widget's */
    /** script block names every one of these, so a bare-string probe would assert nothing. */
    private const DRAW_POLYGON = 'id="ldna-draw-btn-polygon"';
    private const DRAW_CIRCLE  = 'id="ldna-draw-btn-circle"';
    private const RADIUS_ADDR  = 'id="ldna-radius-address"';
    private const RADIUS_MILES = 'id="ldna-radius-miles"';
    private const OVERLAY_LIST = 'id="ldna-overlay-list"';

    private int $florida;

    protected function setUp(): void
    {
        parent::setUp();

        $this->florida = (int) DB::table('us_states')->insertGetId([
            'name' => 'Florida', 'abbreviation' => 'FL', 'fips_code' => '12',
        ]);
        DB::table('us_counties')->insert([
            'name' => 'Pinellas County', 'state_id' => $this->florida, 'fips_code' => '12103',
        ]);
    }

    private function enableAll(): void
    {
        config([
            'criteria_location_dna.geography_cascade_enabled'   => true,
            'criteria_location_dna.geography_cascade_workflows' => [
                'hire_buyer', 'hire_tenant', 'create_tenant', 'create_buyer',
            ],
        ]);
    }

    private function user(string $type): User
    {
        return User::factory()->create(['user_type' => $type]);
    }

    /** Every migrated surface, as (label, callable returning a mounted component). */
    private function migratedSurfaces(): array
    {
        return [
            'Hire Buyer (dedicated)' => fn () => Livewire::actingAs($this->user('buyer'))
                ->test(HireBuyerCreate::class),
            'Hire Buyer (catch-all)' => fn () => Livewire::actingAs($this->user('buyer'))
                ->test(HireCatchAll::class, ['user_type' => 'buyer']),
            'Hire Tenant' => fn () => Livewire::actingAs($this->user('tenant'))
                ->test(HireCatchAll::class, ['user_type' => 'tenant']),
            'Create Tenant' => fn () => Livewire::actingAs($this->user('tenant'))
                ->test(CreateTenant::class, ['user_type' => 'tenant']),
            'Create Buyer' => fn () => Livewire::actingAs($this->user('buyer'))
                ->test(CreateBuyer::class),
        ];
    }

    private function buyerDraft(User $owner, array $meta = []): BuyerAgentAuction
    {
        $auction = (new BuyerAgentAuction())->forceFill([
            'user_id' => $owner->id, 'address' => '', 'title' => 'Geometry fixture',
            'is_draft' => true, 'is_approved' => false, 'is_sold' => false,
        ]);
        $auction->save();

        // `service_type` is load-bearing for these assertions: loadDraft() reads it from meta and
        // blanks it when absent, and the whole property tab renders behind
        // `@if ($service_type === 'full_service')`. Omit it and every assertion below runs against
        // an empty tab — passing the "must not see" checks for entirely the wrong reason.
        $meta = array_merge([
            'user_type' => 'buyer', 'workflow_type' => 'hire_agent', 'service_type' => 'full_service',
            'property_items' => '[]', 'condition_prop_buyer' => '[]',
            'garage_parking_spaces_option' => '[]', 'assets' => '[]',
        ], $meta);

        $rows = [];
        foreach ($meta as $k => $v) {
            $rows[] = ['buyer_agent_auction_id' => $auction->id, 'meta_key' => $k, 'meta_value' => $v];
        }
        BuyerAgentAuctionMeta::insert($rows);

        return $auction;
    }

    /** A blob carrying geometry the old editor produced. */
    private function blobWithGeometry(): string
    {
        return json_encode([
            'state'           => 'Florida',
            'counties'        => ['Pinellas County, FL'],
            'polygons'        => [['label' => 'Waterfront', 'path' => [
                ['lat' => 27.77, 'lng' => -82.64], ['lat' => 27.78, 'lng' => -82.63],
                ['lat' => 27.79, 'lng' => -82.65],
            ]]],
            'radius_searches' => [['address' => '100 Main St', 'lat' => 27.77, 'lng' => -82.64, 'radius_miles' => 5]],
        ]);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · THE DRAW AND RADIUS SURFACES ARE GONE WHERE THE CASCADE IS ON
    // ═════════════════════════════════════════════════════════════════════════

    public function test_every_migrated_surface_drops_the_draw_and_radius_controls(): void
    {
        $this->enableAll();

        foreach ($this->migratedSurfaces() as $label => $mount) {
            $component = $mount();

            foreach ([
                self::DRAW_POLYGON, self::DRAW_CIRCLE,
                self::RADIUS_ADDR, self::RADIUS_MILES, self::OVERLAY_LIST,
            ] as $marker) {
                $component->assertDontSee($marker, false);
            }

            // The cascade is what stands in their place — asserted so a blank tab cannot pass.
            $component->assertSee('id="geo-cascade-state"', false);
        }
    }

    /** The map canvas itself is gone too, not merely hidden. */
    public function test_the_map_canvas_is_not_rendered_on_a_migrated_surface(): void
    {
        $this->enableAll();

        Livewire::actingAs($this->user('buyer'))
            ->test(HireBuyerCreate::class)
            ->assertDontSee('id="ldna-map-hire-buyer"', false)
            ->assertDontSee('id="ldna-map-hire-buyer-placeholder"', false);
    }

    /** With the flag off, every one of those controls is still there. */
    public function test_the_draw_and_radius_controls_survive_with_the_flag_off(): void
    {
        $component = Livewire::actingAs($this->user('buyer'))->test(HireBuyerCreate::class);

        foreach ([
            self::DRAW_POLYGON, self::DRAW_CIRCLE,
            self::RADIUS_ADDR, self::RADIUS_MILES, self::OVERLAY_LIST,
        ] as $marker) {
            $component->assertSee($marker, false);
        }

        $component->assertSee('id="ldna-map-hire-buyer"', false);
    }

    /** Important Places is NOT collateral damage — it stays on every migrated surface. */
    public function test_important_places_survives_on_every_migrated_surface(): void
    {
        $this->enableAll();

        foreach ($this->migratedSurfaces() as $label => $mount) {
            $mount()
                ->assertSee('id="ldna-ip-rows"', false)
                ->assertSee('id="ldna-important-places-field"', false);
        }
    }

    /** The blob bridge stays: without it nothing round-trips at all. */
    public function test_the_blob_field_survives_on_every_migrated_surface(): void
    {
        $this->enableAll();

        foreach ($this->migratedSurfaces() as $label => $mount) {
            $mount()->assertSee('id="ldna-json-field"', false);
        }
    }

    /** Flexible location and notes are not map surfaces and were not in scope. */
    public function test_flexible_location_and_notes_survive(): void
    {
        $this->enableAll();

        Livewire::actingAs($this->user('buyer'))
            ->test(HireBuyerCreate::class)
            ->assertSee('id="ldna-flexible"', false)
            ->assertSee('id="ldna-location-notes"', false);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · THE JS CANNOT SPIN, AND GEOCODING NO LONGER NEEDS A MAP
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * `ldnaTryInit()` must return before it can reach the container poll.
     *
     * That poll has no timeout — the guard at the top of the function is the only thing standing
     * between a migrated page and a 200ms timer that never stops.
     */
    public function test_map_initialisation_is_short_circuited_in_cascade_mode(): void
    {
        $this->enableAll();

        Livewire::actingAs($this->user('buyer'))
            ->test(HireBuyerCreate::class)
            ->assertSee('var ldnaCascadeMode = true;', false)
            ->assertSee('if (ldnaCascadeMode) return;', false);
    }

    /** Off, the flag is emitted as false so the legacy hosts initialise exactly as before. */
    public function test_map_initialisation_is_untouched_with_the_flag_off(): void
    {
        Livewire::actingAs($this->user('buyer'))
            ->test(HireBuyerCreate::class)
            ->assertSee('var ldnaCascadeMode = false;', false);
    }

    /**
     * Important Places geocoding no longer waits for a map.
     *
     * Asserted on the source because the behaviour is browser-side: the retry that made an address
     * unresolvable in cascade mode is gone, and the bare `ldnaRequestInit()` that remains is a
     * no-op there while still bringing a legacy host's map up.
     */
    public function test_important_places_geocoding_no_longer_waits_for_a_map(): void
    {
        $widget = file_get_contents(base_path('resources/views/partials/location-dna/map-input.blade.php'));

        $this->assertStringNotContainsString(
            'if (!ldnaMap) { ldnaRequestInit(); setTimeout(function () { window.ldnaIpGeocodeRow(el); }, 600); return; }',
            $widget,
            'the map-dependent retry must be gone — in cascade mode it never resolves'
        );
        $this->assertStringContainsString(
            'if (!ldnaMap) { ldnaRequestInit(); }',
            $widget,
            'a legacy host must still have its map requested'
        );
        $this->assertStringContainsString(
            'ldnaIpRenderAllOverlays();',
            $widget,
            'map init must still back-fill pins for rows geocoded before it was ready'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · SAVED GEOMETRY: VISIBLE, PRESERVED, REMOVABLE ONLY ON PURPOSE
    // ═════════════════════════════════════════════════════════════════════════

    public function test_saved_geometry_is_announced_rather_than_left_invisible(): void
    {
        $this->enableAll();

        $owner   = $this->user('buyer');
        $auction = $this->buyerDraft($owner, ['location_dna_preferences' => $this->blobWithGeometry()]);

        Livewire::actingAs($owner)
            ->test(HireBuyerCreate::class, ['listingId' => $auction->id])
            ->assertSee('id="ldna-retained-geometry"', false)
            ->assertSee('Custom map areas saved earlier are still active.')
            ->assertSee('ldnaClearRetainedGeometry()', false);
    }

    /** No geometry, no notice — it must not appear on a listing that never had any. */
    public function test_no_notice_when_there_is_no_saved_geometry(): void
    {
        $this->enableAll();

        Livewire::actingAs($this->user('buyer'))
            ->test(HireBuyerCreate::class)
            ->assertDontSee('id="ldna-retained-geometry"', false);
    }

    /** The notice belongs to cascade mode; with the flag off the real tools are there instead. */
    public function test_no_notice_when_the_cascade_is_off(): void
    {
        $owner   = $this->user('buyer');
        $auction = $this->buyerDraft($owner, ['location_dna_preferences' => $this->blobWithGeometry()]);

        Livewire::actingAs($owner)
            ->test(HireBuyerCreate::class, ['listingId' => $auction->id])
            ->assertDontSee('id="ldna-retained-geometry"', false)
            ->assertSee(self::DRAW_POLYGON, false);
    }

    /**
     * THE DATA-SAFETY GUARANTEE. Saving a migrated listing must not drop its geometry.
     *
     * The map never initialises, so `ldnaGeometryHydrated` stays false and the serializer leaves
     * both keys at the values the server seeded. This asserts that end to end, through the real
     * save path, because it is the outcome — not the mechanism — that matters to the record.
     */
    public function test_saved_geometry_survives_a_save_on_a_migrated_surface(): void
    {
        $this->enableAll();

        $owner   = $this->user('buyer');
        $auction = $this->buyerDraft($owner, ['location_dna_preferences' => $this->blobWithGeometry()]);

        $component = Livewire::actingAs($owner)
            ->test(HireBuyerCreate::class, ['listingId' => $auction->id]);

        // The browser bridge is what carries the blob back; a Livewire test has no browser, so the
        // stored document is set as the payload exactly as the bridge would.
        $component->set('location_dna_preferences_json', $this->blobWithGeometry())
            ->set('geoStateId', (string) $this->florida)
            ->call('saveDraft');

        $saved = BuyerAgentAuction::findOrFail($component->instance()->listingId);
        $canonical = json_decode((string) $saved->fresh()->info('location_dna_preferences'), true);

        $this->assertCount(1, $canonical['polygons'], 'the saved polygon must survive');
        $this->assertSame('Waterfront', $canonical['polygons'][0]['label']);
        $this->assertCount(1, $canonical['radius_searches'], 'the saved radius search must survive');
        $this->assertSame(5, $canonical['radius_searches'][0]['radius_miles']);
    }

    /**
     * The Remove control writes only the two existing blob keys.
     *
     * No new meta key, no new endpoint, no new persistence path — present-but-empty is the
     * canonical cleared state the command builder already understands.
     */
    public function test_the_remove_control_uses_only_the_existing_blob_keys(): void
    {
        $widget = file_get_contents(base_path('resources/views/partials/location-dna/map-input.blade.php'));

        $this->assertStringContainsString('window.ldnaClearRetainedGeometry = function () {', $widget);

        // SCOPED TO THE FUNCTION BODY, NOT THE FILE. `ldnaSerialize()` assigns these same two
        // lines when it rebuilds geometry, so asserting against the whole widget matched that
        // copy instead — a mutation probe that deleted the radius clear from this control
        // survived until the window below was introduced.
        $clear = substr($widget, strpos($widget, 'window.ldnaClearRetainedGeometry'), 700);

        $this->assertStringContainsString('ldnaState.polygons        = [];', $clear);
        $this->assertStringContainsString('ldnaState.radius_searches = [];', $clear);
        $this->assertStringContainsString('ldnaSerialize();', $clear);

        // It must not reach for a route, a fetch, or a Livewire action of its own.

        foreach (['fetch(', 'axios', '@this', 'Livewire.emit', 'route('] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $clear,
                "the Remove control must not introduce a new write path ({$forbidden})"
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 4 · NOTHING OUTSIDE THE MIGRATED WORKFLOWS MOVED
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The four legacy criteria forms keep the whole widget.
     *
     * They pass no cascade opt-in, so the gate defaults closed for them. The Phase 1a guard also
     * requires they still include the widget at all, which is asserted there.
     */
    public function test_the_legacy_criteria_forms_keep_the_full_widget(): void
    {
        foreach ([
            'buyer_criteria/add.blade.php',
            'buyer_criteria/edit.blade.php',
            'tenant_criteria/add.blade.php',
            'tenant_criteria/edit.blade.php',
        ] as $relative) {
            $source = file_get_contents(base_path('resources/views/'.$relative));

            $this->assertStringNotContainsString(
                'ldnaGeographyCascade',
                $source,
                "{$relative} must not opt into cascade mode — it would lose its draw tools."
            );
        }
    }

    /** Seller and Landlord render no part of this widget, so nothing here can reach them. */
    public function test_seller_and_landlord_tabs_do_not_include_the_widget_at_all(): void
    {
        foreach ([
            'livewire/offer-listing/offer-seller-tabs',
            'livewire/offer-listing/offer-landlord-tabs',
            'livewire/hire-seller-agent',
            'livewire/hire-landlord-agent',
        ] as $dir) {
            $base = base_path('resources/views/'.$dir);

            if (! is_dir($base)) {
                continue;
            }

            $hits = [];

            /** @var iterable<\SplFileInfo> $it */
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));

            foreach ($it as $file) {
                if ($file->isFile()
                    && str_contains((string) file_get_contents($file->getPathname()), 'location-dna.map-input')) {
                    $hits[] = $file->getPathname();
                }
            }

            $this->assertSame([], $hits, "{$dir} must include no map widget at all");
        }
    }

    /** The drawing infrastructure is suppressed per surface, never deleted from the widget. */
    public function test_the_map_infrastructure_is_not_removed_globally(): void
    {
        $widget = file_get_contents(base_path('resources/views/partials/location-dna/map-input.blade.php'));

        foreach ([
            'ldnaSetDrawMode', 'ldnaStartDrawPolygon', 'ldnaStartDrawCircle', 'ldnaFinishDrawing', 'ldnaAddRadiusSearch',
            'ldnaInitMap', 'ldna-draw-btn-polygon', 'ldna-radius-address',
        ] as $symbol) {
            $this->assertStringContainsString(
                $symbol,
                $widget,
                "{$symbol} must survive — four legacy hosts still render it."
            );
        }
    }
}
