<?php

namespace Tests\Feature\LocationDna;

use App\Support\Spatial\LdnaBasemapSurface;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * What each surface actually EMITS, on both sides of the gate.
 *
 * The defect this whole phase exists to fix was invisible to every PHP test in the suite:
 * the renderer was merged, built by Mix and covered by Playwright, and no Blade template
 * referenced it, so "the map works" and "the map is on the page" were never the same
 * assertion. These tests are the second one.
 *
 * Geometry is asserted through the payload the browser will actually receive
 * (`data-ldna-state`), not through the presence of a container, because a panel that
 * mounts and hydrates from an empty blob is exactly the failure that destroys stored
 * polygons on the next save.
 */
class LdnaMapRendererSurfaceTest extends TestCase
{
    /** Stored geometry with values chosen so a mutation shows up as a diff, not a rounding. */
    private const STORED = [
        'cities'            => ['St. Petersburg, FL'],
        'zip_codes'         => ['33708'],
        'counties'          => ['Pinellas County, FL'],
        'neighborhoods'     => ['Historic Kenwood'],
        'state'             => 'Florida',
        'flexible_location' => true,
        'location_notes'    => 'Within 10 minutes of I-275.',
        'polygons'          => [[
            'label' => 'North of Central',
            'path'  => [
                ['lat' => 27.7731, 'lng' => -82.6390],
                ['lat' => 27.7801, 'lng' => -82.6301],
                ['lat' => 27.7688, 'lng' => -82.6244],
            ],
        ]],
        'radius_searches'   => [
            ['address' => '100 2nd Ave S, St. Petersburg, FL', 'lat' => 27.7705, 'lng' => -82.6367, 'radius_miles' => 3.5],
        ],
    ];

    private const PLACES = [
        ['type' => 'Work', 'address' => '200 Central Ave', 'lat' => 27.7712, 'lng' => -82.6390, 'distance_pref' => 'miles', 'distance_value' => 5, 'travel_mode' => 'driving'],
    ];

    private function enable(array $surfaces): void
    {
        config([
            'spatial_basemap.maplibre_renderer_enabled'  => true,
            'spatial_basemap.maplibre_renderer_surfaces' => $surfaces,
            'spatial_basemap.pmtiles_url'                => 'https://example.invalid/basemap.pmtiles',
        ]);
    }

    private function renderMapInput(array $data = []): string
    {
        return view('partials.location-dna.map-input', array_merge([
            'existingLocationDna' => self::STORED,
            'errors'              => new ViewErrorBag(),
        ], $data))->render();
    }

    private function renderDisplay(array $data = []): string
    {
        return view('components.location-dna-map', array_merge([
            'preferences'        => null,
            'legacyLocation'     => [],
            'boundaryData'       => null,
            'floodZoneData'      => null,
            'schoolDistrictData' => null,
        ], $data))->render();
    }

    /** The JSON the browser will hydrate from, decoded back out of the rendered markup. */
    private function hydrationPayload(string $html): array
    {
        $this->assertMatchesRegularExpression(
            '/data-ldna-state="([^"]*)"/',
            $html,
            'the MapLibre panel must carry a hydration payload — a container without one mounts empty'
        );

        preg_match('/data-ldna-state="([^"]*)"/', $html, $m);

        return json_decode(html_entity_decode($m[1], ENT_QUOTES), true) ?? [];
    }

    // ─── The gate, at the markup level ───────────────────────────────────────────

    public function test_create_buyer_emits_the_maplibre_container_and_bundle_when_enabled(): void
    {
        $this->enable([LdnaBasemapSurface::CREATE_BUYER]);

        $html = $this->renderMapInput(['ldnaSurface' => LdnaBasemapSurface::CREATE_BUYER]);

        $this->assertStringContainsString('data-ldna-maplibre', $html);
        $this->assertStringContainsString('/js/spatial/ldna-maplibre.js', $html);
        $this->assertStringContainsString('data-ldna-mode="edit"', $html);
    }

    public function test_a_surface_that_is_not_enabled_keeps_the_google_panel_untouched(): void
    {
        $this->enable([LdnaBasemapSurface::CREATE_BUYER]);

        // create_tenant is NOT named, so it must be byte-for-byte the incumbent.
        $html = $this->renderMapInput([
            'ldnaSurface' => LdnaBasemapSurface::CREATE_TENANT,
            'mapPanelId'  => 'ldna-map-tenant',
        ]);

        $this->assertStringNotContainsString('data-ldna-maplibre', $html);
        $this->assertStringNotContainsString('/js/spatial/ldna-maplibre.js', $html);
        $this->assertStringContainsString('id="ldna-map-tenant"', $html);
        $this->assertStringContainsString('ldna-map-tenant-placeholder', $html);
    }

    public function test_a_host_that_names_no_surface_keeps_the_google_panel(): void
    {
        $this->enable(LdnaBasemapSurface::SURFACES);

        $html = $this->renderMapInput();   // no ldnaSurface at all

        $this->assertStringNotContainsString('data-ldna-maplibre', $html);
        $this->assertStringContainsString('ldna-map-panel-placeholder', $html);
    }

    public function test_the_master_switch_alone_changes_nothing(): void
    {
        config([
            'spatial_basemap.maplibre_renderer_enabled'  => true,
            'spatial_basemap.maplibre_renderer_surfaces' => [],
        ]);

        $html = $this->renderMapInput(['ldnaSurface' => LdnaBasemapSurface::CREATE_BUYER]);

        $this->assertStringNotContainsString('data-ldna-maplibre', $html);
    }

    public function test_the_bundle_is_emitted_once_for_two_panels_on_one_page(): void
    {
        $this->enable([LdnaBasemapSurface::DISPLAY]);

        $html = Blade::render(
            '@include("partials.location-dna._maplibre-panel", ["ldnaMaplibrePanelId" => "a"])'
            . '@include("partials.location-dna._maplibre-panel", ["ldnaMaplibrePanelId" => "b"])'
        );

        $this->assertSame(2, substr_count($html, 'data-ldna-maplibre'), 'both panels must render');
        $this->assertSame(
            1,
            substr_count($html, '/js/spatial/ldna-maplibre.js'),
            'the 1.1MB renderer bundle must be requested once per page, not once per panel'
        );
    }

    // ─── Hydration: the geometry the browser receives ────────────────────────────

    public function test_stored_polygons_reach_the_browser_unmutated(): void
    {
        $this->enable([LdnaBasemapSurface::CREATE_BUYER]);

        $payload = $this->hydrationPayload(
            $this->renderMapInput(['ldnaSurface' => LdnaBasemapSurface::CREATE_BUYER])
        );

        $this->assertEquals(
            self::STORED['polygons'],
            $payload['polygons'],
            'a polygon must reach the renderer exactly as stored — every vertex, in order, '
            . 'with its label. A renderer swap is not a data migration.'
        );
    }

    public function test_stored_radius_searches_reach_the_browser_unmutated(): void
    {
        $this->enable([LdnaBasemapSurface::CREATE_BUYER]);

        $payload = $this->hydrationPayload(
            $this->renderMapInput(['ldnaSurface' => LdnaBasemapSurface::CREATE_BUYER])
        );

        $this->assertEquals(self::STORED['radius_searches'], $payload['radius_searches']);
    }

    public function test_important_places_reach_the_browser_when_the_host_enables_them(): void
    {
        $this->enable([LdnaBasemapSurface::CREATE_BUYER]);

        $payload = $this->hydrationPayload($this->renderMapInput([
            'ldnaSurface'             => LdnaBasemapSurface::CREATE_BUYER,
            'enableImportantPlaces'   => true,
            'existingImportantPlaces' => self::PLACES,
        ]));

        $this->assertEquals(self::PLACES, $payload['important_places']);
    }

    public function test_the_hydration_payload_carries_geometry_only(): void
    {
        $this->enable([LdnaBasemapSurface::CREATE_BUYER]);

        $payload = $this->hydrationPayload(
            $this->renderMapInput(['ldnaSurface' => LdnaBasemapSurface::CREATE_BUYER])
        );

        // Cities, ZIPs, counties, state, notes and the flexible flag are the HOST's to
        // serialise; the renderer neither reads nor reports them, and handing them over
        // would invite a second writer for the same keys.
        $this->assertSame(
            ['polygons', 'radius_searches', 'important_places'],
            array_keys($payload)
        );
    }

    public function test_a_listing_with_no_geometry_renders_a_safe_empty_state(): void
    {
        $this->enable([LdnaBasemapSurface::CREATE_BUYER]);

        $html = $this->renderMapInput([
            'ldnaSurface'         => LdnaBasemapSurface::CREATE_BUYER,
            'existingLocationDna' => [],
        ]);

        $payload = $this->hydrationPayload($html);

        $this->assertSame([], $payload['polygons']);
        $this->assertSame([], $payload['radius_searches']);
        $this->assertStringNotContainsString('data-ldna-fit="1"', $html, 'nothing to fit to');
        $this->assertStringContainsString('No saved areas yet', $html);
    }

    public function test_a_listing_with_geometry_asks_for_a_fit(): void
    {
        $this->enable([LdnaBasemapSurface::CREATE_BUYER]);

        $html = $this->renderMapInput(['ldnaSurface' => LdnaBasemapSurface::CREATE_BUYER]);

        $this->assertStringContainsString('data-ldna-fit="1"', $html);
        $this->assertStringNotContainsString('No saved areas yet', $html);
    }

    // ─── Seller / Landlord: a pin, and deliberately no search geometry ───────────

    public function test_seller_and_landlord_render_a_property_pin_with_no_invented_geometry(): void
    {
        $this->enable([LdnaBasemapSurface::DISPLAY]);

        $html = $this->renderDisplay([
            'propertyPin' => ['lat' => 27.7676, 'lng' => -82.6403, 'label' => '123 Central Ave'],
        ]);

        $this->assertStringContainsString('data-ldna-maplibre', $html);
        $this->assertStringContainsString('data-ldna-property-pin', $html);

        $payload = $this->hydrationPayload($html);

        // The point of the whole role split: a listing is one property, not a search area.
        $this->assertSame([], $payload['polygons'], 'a seller/landlord listing has no search polygons');
        $this->assertSame([], $payload['radius_searches'], 'and no radius circles must be synthesised around its pin');
        $this->assertSame([], $payload['important_places']);
    }

    public function test_a_seller_listing_without_coordinates_renders_no_map_and_does_not_fail(): void
    {
        $this->enable([LdnaBasemapSurface::DISPLAY]);

        $html = $this->renderDisplay(['propertyPin' => null]);

        $this->assertStringNotContainsString('data-ldna-property-pin', $html);
        $this->assertStringContainsString('No location preferences have been specified', $html);
    }

    // ─── Buyer / Tenant detail ──────────────────────────────────────────────────

    public function test_buyer_detail_renders_saved_search_geometry(): void
    {
        $this->enable([LdnaBasemapSurface::DISPLAY]);

        $html = $this->renderDisplay([
            'preferences'     => self::STORED,
            'importantPlaces' => self::PLACES,
        ]);

        $payload = $this->hydrationPayload($html);

        $this->assertEquals(self::STORED['polygons'], $payload['polygons']);
        $this->assertEquals(self::STORED['radius_searches'], $payload['radius_searches']);
        $this->assertEquals(self::PLACES, $payload['important_places']);
        $this->assertStringContainsString('data-ldna-mode="display"', $html);
    }

    public function test_boundary_geojson_is_passed_through_rather_than_fetched(): void
    {
        $this->enable([LdnaBasemapSurface::DISPLAY]);

        // Tier 5: cities only, with resolved TIGER rings. [boundary][piece][ring][lng,lat].
        $html = $this->renderDisplay([
            'preferences'  => ['cities' => ['St. Petersburg, FL']],
            'boundaryData' => ['fallback' => false, 'geojson_polygons' => [
                [[[[-82.70, 27.70], [-82.60, 27.70], [-82.60, 27.80], [-82.70, 27.80], [-82.70, 27.70]]]],
            ]],
        ]);

        $this->assertStringContainsString('data-ldna-boundaries', $html);
        $this->assertStringContainsString('MultiPolygon', $html);
    }

    public function test_flood_and_school_legends_are_suppressed_rather_than_drawn_wrong(): void
    {
        $overlays = [
            'floodZoneData'      => ['available' => true, 'flood_zones' => [
                ['zone_designation' => 'AE', 'rings' => [[[-82.7, 27.7], [-82.6, 27.7], [-82.6, 27.8], [-82.7, 27.7]]]],
            ]],
            'schoolDistrictData' => ['available' => true, 'school_districts' => [
                ['district_name' => 'Pinellas County Schools', 'rings' => [[[-82.7, 27.7], [-82.6, 27.7], [-82.6, 27.8], [-82.7, 27.7]]]],
            ]],
        ];

        // Google draws them, so the legends belong on the page.
        config(['spatial_basemap.maplibre_renderer_enabled' => false]);
        $google = $this->renderDisplay(array_merge($overlays, ['preferences' => self::STORED]));
        $this->assertStringContainsString('AE', $google);
        $this->assertStringContainsString('Pinellas County Schools', $google);

        // MapLibre does not draw them in this phase. A colour key for shapes that are not
        // on the map reads as data the listing does not have, so it goes with them.
        $this->enable([LdnaBasemapSurface::DISPLAY]);
        $maplibre = $this->renderDisplay(array_merge($overlays, ['preferences' => self::STORED]));
        $this->assertStringContainsString('data-ldna-maplibre', $maplibre);
        $this->assertStringNotContainsString('Pinellas County Schools', $maplibre);
        $this->assertStringNotContainsString('ldna-flood-legend', $maplibre);
    }

    public function test_the_display_surface_falls_back_to_chips_when_maplibre_is_off(): void
    {
        config(['spatial_basemap.maplibre_renderer_enabled' => false]);

        $html = $this->renderDisplay(['preferences' => ['cities' => ['St. Petersburg, FL']]]);

        $this->assertStringNotContainsString('data-ldna-maplibre', $html);
        $this->assertStringContainsString('ldna-area-chip', $html);
    }

    // ─── Renderer exclusivity, and the bounded Google fallback ───────────────────

    public function test_the_two_renderers_are_never_both_live_on_one_panel(): void
    {
        $this->enable([LdnaBasemapSurface::DISPLAY]);
        config(['services.google.places_key' => 'test-key-not-a-real-credential']);

        $html = $this->renderDisplay(['preferences' => self::STORED]);

        $this->assertStringContainsString('data-ldna-maplibre', $html);
        $this->assertStringNotContainsString(
            'maps.googleapis.com',
            $html,
            'under MapLibre the Google SDK must not even be requested — its renderer resolves '
            . 'the same element id, and new google.maps.Map(null) throws on a page with a key'
        );
    }

    public function test_the_google_poll_is_bounded_and_states_what_happened(): void
    {
        // The original defect: setTimeout(ldnaTryInit, 200) with no ceiling, so a missing or
        // rejected credential left a grey box reading "Loading map..." for the life of the page.
        $html = $this->renderMapInput();

        $this->assertStringContainsString('LDNA_GOOGLE_MAX_ATTEMPTS', $html);
        $this->assertStringContainsString('ldnaGoogleDegrade', $html);
        $this->assertStringContainsString('The map could not load', $html);
        $this->assertStringContainsString(
            'Your saved search areas are safe',
            $html,
            'the degraded panel must say the geometry survived, because the user cannot see it'
        );
    }

    public function test_the_draw_toolbar_is_bound_to_whichever_renderer_is_live(): void
    {
        // The toolbar, HUD and overlay list are shared chrome. Under MapLibre the five
        // public entry points they call are re-pointed at the renderer; leaving them on the
        // Google implementations would give the surface a map you can look at and not draw
        // on, which is not what "the map works" means to anyone using it.
        $this->enable([LdnaBasemapSurface::CREATE_BUYER]);
        $html = $this->renderMapInput(['ldnaSurface' => LdnaBasemapSurface::CREATE_BUYER]);

        foreach ([
            'r.startDrawPolygon()',
            'r.startDrawCircle()',
            'r.finishPolygon(',
            'r.cancelDrawing()',
            'r.deletePolygon(idx)',
            'r.deleteCircle(idx)',
            'ldnaMlRefreshOverlayList',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html, "toolbar binding missing: {$needle}");
        }

        // Address-based radius needs a geocoder the renderer deliberately does not have.
        // It must say so and name the alternative, not fail silently.
        $this->assertStringContainsString('Address lookup is not available on this map', $html);
        $this->assertStringContainsString('Use the Circle tool', $html);

        // The Important Places "Map" button is the SAME hazard: its Google branch retries
        // every 600ms forever when there is no Google map, which is the unbounded poll all
        // over again. It must return with an explanation instead of falling through.
        $ipHtml = $this->renderMapInput([
            'ldnaSurface'           => LdnaBasemapSurface::CREATE_BUYER,
            'enableImportantPlaces' => true,
        ]);
        $this->assertStringContainsString('ldna-ip-geocode-hint', $ipHtml);
        $this->assertStringContainsString('It is still saved with the listing', $ipHtml);

        // And none of that reaches a surface still on Google.
        $google = $this->renderMapInput();
        $this->assertStringNotContainsString('ldnaMlRefreshOverlayList', $google);
        $this->assertStringNotContainsString('Address lookup is not available', $google);
    }

    public function test_city_zip_and_county_outlines_are_wired_on_the_edit_surface(): void
    {
        $this->enable([LdnaBasemapSurface::CREATE_BUYER]);
        $html = $this->renderMapInput(['ldnaSurface' => LdnaBasemapSurface::CREATE_BUYER]);

        // The HOST keeps fetching; the renderer is only handed the result. That split is
        // what keeps the browser-direct Nominatim/TIGER traffic out of the new renderer.
        $this->assertStringContainsString('_blRenderer.setBoundary(key, feature)', $html);
        $this->assertStringContainsString('_clRenderer.clearBoundary(key)', $html);

        // And the stored tags get their outlines back on reopen. Under Google this happens
        // inside ldnaInitMap(), which never runs here.
        $this->assertStringContainsString("ldnaEnqueueBoundary('city__'", $html);
        $this->assertStringContainsString('ldnaBoundaryProcess();', $html);
    }

    public function test_the_serialiser_reads_whichever_renderer_is_live(): void
    {
        $this->enable([LdnaBasemapSurface::CREATE_BUYER]);

        $ml = $this->renderMapInput(['ldnaSurface' => LdnaBasemapSurface::CREATE_BUYER]);
        $this->assertStringContainsString('var ldnaUseMaplibre = true;', $ml);
        $this->assertStringContainsString('_mlRenderer.isHydrated()', $ml);

        $google = $this->renderMapInput();
        $this->assertStringContainsString('var ldnaUseMaplibre = false;', $google);
        $this->assertStringContainsString('ldnaOverlaysAuthoritative', $google);
    }

    public function test_every_host_surface_passes_a_recognised_surface_key(): void
    {
        // A host that forgets the key silently keeps Google forever, which is the quiet
        // half of the defect this phase fixes: no error, just a feature that never arrives.
        $hosts = [
            'resources/views/livewire/offer-listing/offer-buyer-tabs/commission-based/property-preferences.blade.php',
            'resources/views/livewire/offer-listing/offer-tenant-tabs/commission-based/property-details.blade.php',
            'resources/views/livewire/hire-buyer-agent/buyer-agent-auction-tabs/commission-based/property-preferences.blade.php',
            'resources/views/livewire/tenant-agent-auction-tabs/commission-based/property-details.blade.php',
            'resources/views/buyer_criteria/add.blade.php',
            'resources/views/buyer_criteria/edit.blade.php',
            'resources/views/tenant_criteria/add.blade.php',
            'resources/views/tenant_criteria/edit.blade.php',
        ];

        foreach ($hosts as $host) {
            $path = base_path($host);
            $this->assertFileExists($path);
            $this->assertStringContainsString(
                'LdnaBasemapSurface::',
                file_get_contents($path),
                "{$host} includes the Search Areas widget but names no renderer surface"
            );
        }
    }
}
