<?php

namespace Tests\Feature\Spatial;

use Illuminate\Foundation\Mix;
use Tests\TestCase;

/**
 * Phase 2A — internal MapLibre + PMTiles proof route.
 *
 * Deliberately does NOT use DatabaseTransactions. The route reads no model and
 * opens no connection, so no schema is built for these tests and any database
 * access introduced later would surface here as a failure rather than pass
 * silently against a migrated fixture.
 */
class MaplibreProofRouteTest extends TestCase
{
    private const URI = '/internal/spatial/maplibre-proof';

    protected function setUp(): void
    {
        parent::setUp();

        // The Blade view resolves its asset URLs through mix(), which reads
        // public/mix-manifest.json and throws when the entry is absent. Binding
        // the resolver to an identity function keeps these tests about routing
        // and markup rather than about whether someone has run `npm run production`
        // in this checkout. The real manifest lookup is covered by the build step.
        $this->app->bind(Mix::class, fn () => fn ($path) => $path);

        // A configured archive by default. Individual tests override it.
        config(['spatial_basemap.pmtiles_url' => 'https://basemap.example.invalid/florida.pmtiles']);
    }

    /** @test */
    public function the_proof_route_is_unavailable_when_the_feature_flag_is_disabled(): void
    {
        config(['spatial_basemap.proof_enabled' => false]);

        $this->get(self::URI)->assertNotFound();
    }

    /** @test */
    public function the_feature_flag_defaults_to_disabled(): void
    {
        // Read from the config file itself, not from the runtime value, so this
        // fails if the shipped default is ever flipped to true.
        $default = require base_path('config/spatial_basemap.php');

        $this->assertFalse(
            $default['proof_enabled'],
            'The MapLibre proof flag must ship disabled by default.'
        );
    }

    /** @test */
    public function the_proof_route_is_available_when_the_feature_flag_is_enabled(): void
    {
        config(['spatial_basemap.proof_enabled' => true]);

        $this->get(self::URI)
            ->assertOk()
            ->assertSee('MapLibre + PMTiles proof-of-render', false);
    }

    /** @test */
    public function the_page_loads_the_isolated_proof_bundle_and_not_the_application_bundle(): void
    {
        config(['spatial_basemap.proof_enabled' => true]);

        $response = $this->get(self::URI)->assertOk();

        $response->assertSee('js/spatial/maplibre-proof.js', false);

        // No stylesheet link: Mix inlines JS-imported CSS into the bundle rather
        // than emitting a sibling .css, so linking one would throw at render time.
        $response->assertDontSee('js/spatial/maplibre-proof.css', false);

        // MapLibre must never be pulled into the bundle every consumer page loads.
        $response->assertDontSee('js/app.js', false);
    }

    /** @test */
    public function the_archive_url_is_driven_by_configuration(): void
    {
        config([
            'spatial_basemap.proof_enabled' => true,
            'spatial_basemap.pmtiles_url'   => 'https://configured.example.invalid/archive.pmtiles',
        ]);

        $this->get(self::URI)
            ->assertOk()
            ->assertSee('https://configured.example.invalid/archive.pmtiles', false);
    }

    /** @test */
    public function the_page_renders_required_attribution(): void
    {
        config([
            'spatial_basemap.proof_enabled' => true,
            'spatial_basemap.attribution'   => '© OpenStreetMap contributors',
        ]);

        $this->get(self::URI)
            ->assertOk()
            ->assertSee('OpenStreetMap', false);
    }

    /** @test */
    public function the_page_carries_an_explicit_error_state_and_a_loading_state(): void
    {
        config(['spatial_basemap.proof_enabled' => true]);

        $response = $this->get(self::URI)->assertOk();

        // Both panels are server-rendered so the error path needs no injection.
        $response->assertSee('data-maplibre-proof-status', false);
        $response->assertSee('data-maplibre-proof-error', false);
        $response->assertSee('Basemap failed to load', false);
    }

    /** @test */
    public function an_unconfigured_archive_fails_visibly_and_initialises_no_map(): void
    {
        config([
            'spatial_basemap.proof_enabled' => true,
            'spatial_basemap.pmtiles_url'   => null,
        ]);

        $response = $this->get(self::URI)->assertOk();

        $response->assertSee('No PMTiles archive configured', false);
        $response->assertDontSee('data-maplibre-proof=', false);
    }

    /** @test */
    public function the_page_references_no_google_or_nominatim_endpoint(): void
    {
        config(['spatial_basemap.proof_enabled' => true]);

        $html = $this->get(self::URI)->assertOk()->getContent();

        foreach ([
            'maps.googleapis.com',
            'places.googleapis.com',
            'google.maps',
            'nominatim.openstreetmap.org',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $html,
                "The proof page must not reference {$forbidden}."
            );
        }
    }
}
