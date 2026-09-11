<?php

namespace Tests\Feature\Explore;

use App\Services\Explore\ExploreGoogleConfig;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Explore\Concerns\MakesExploreListings;
use Tests\TestCase;

/**
 * §25 / §49-S — which Google APIs Explore is allowed to touch.
 *
 * Asserted against the shipped renderer source rather than against a
 * description of it, because the failure mode is a line of JavaScript nobody
 * re-read. `libraries=places` is one word.
 */
class ExploreGoogleApiSurfaceTest extends TestCase
{
    use DatabaseTransactions;
    use MakesExploreListings;

    private function renderer(): string
    {
        $path = public_path('js/explore/explore-3d.js');

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * The renderer with its comments removed.
     *
     * The header comment NAMES the APIs this file must never call, which is
     * exactly the documentation worth having and exactly what a naive substring
     * scan would trip over. Executable code is what the assertions are about.
     */
    private function executableRenderer(): string
    {
        $source = preg_replace('#/\\*.*?\\*/#s', '', $this->renderer());
        $source = preg_replace('#(^|[^:])//.*$#m', '$1', (string) $source);

        return (string) $source;
    }

    /** @test */
    public function only_the_maps3d_library_is_requested(): void
    {
        $this->assertSame(['maps3d'], (new ExploreGoogleConfig())->libraries());
    }

    /**
     * The library list is server config, so adding one is a diff a reviewer
     * sees rather than a string edited in a bundle.
     *
     * @test
     */
    public function the_renderer_takes_its_library_list_from_the_server(): void
    {
        $renderer = $this->executableRenderer();

        $this->assertStringContainsString('shell.dataset.googleLibraries', $renderer);
        $this->assertStringNotContainsString('libraries=places', $renderer);
        $this->assertStringNotContainsString("'places'", $renderer);
    }

    /** @test */
    public function no_billable_google_api_beyond_the_3d_map_is_referenced(): void
    {
        $renderer = strtolower($this->executableRenderer());

        foreach ([
            'placesservice',
            'autocomplete',
            'directionsservice',
            'distancematrix',
            'geocoder',
            'routes.googleapis.com',
            'roads.googleapis.com',
            'maps/api/place',
            'maps/api/directions',
            'maps/api/geocode',
            'maps/api/distancematrix',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $renderer, "{$forbidden} must not be referenced");
        }
    }

    /**
     * Marker positions are MLS coordinates. Nothing geocodes, so Google never
     * becomes the source of a property's position or identity — and no request
     * is spent per listing.
     *
     * @test
     */
    public function markers_are_placed_from_the_coordinates_the_server_sends(): void
    {
        $renderer = $this->renderer();

        $this->assertStringContainsString('lat: listing.latitude', $renderer);
        $this->assertStringContainsString('lng: listing.longitude', $renderer);
    }

    /**
     * Fetching is debounced, not per-frame. A camera fires continuously while a
     * user moves; one gesture must not become hundreds of requests against a
     * public endpoint.
     *
     * @test
     */
    public function viewport_fetching_is_debounced_and_sequenced(): void
    {
        $renderer = $this->renderer();

        $this->assertStringContainsString('MOVE_DEBOUNCE_MS', $renderer);
        $this->assertStringContainsString('clearTimeout', $renderer);
        $this->assertStringContainsString('state.lastRenderedSeq', $renderer);
    }

    /** @test */
    public function no_api_key_is_hard_coded_in_the_renderer(): void
    {
        $renderer = $this->renderer();

        $this->assertStringContainsString('shell.dataset.googleKey', $renderer);
        // Google browser keys are 39 characters beginning AIza.
        $this->assertDoesNotMatchRegularExpression('/AIza[0-9A-Za-z_\-]{20,}/', $renderer);
    }

    /**
     * The page requests Google only when the server said a credential exists,
     * so a misconfigured environment issues zero Google requests rather than
     * failing ones.
     *
     * @test
     */
    public function the_google_script_is_requested_only_when_a_key_is_present(): void
    {
        $renderer = $this->renderer();

        $this->assertStringContainsString("if (!key) {", $renderer);
        $this->assertStringContainsString('if (googleReady)', $renderer);
    }

    /** @test */
    public function readiness_reports_the_variable_name_and_never_a_value(): void
    {
        config(['explore.google.browser_key' => null]);
        $config = new ExploreGoogleConfig();

        $this->assertFalse($config->isReady());
        $this->assertStringContainsString('EXPLORE_GOOGLE_MAPS_BROWSER_KEY', (string) $config->unavailableReason());

        // Ready needs the key AND the renderer explicitly switched on.
        config(['explore.google.enabled' => true, 'explore.google.browser_key' => 'a-real-looking-key']);
        $config = new ExploreGoogleConfig();

        $this->assertTrue($config->isReady());
        $this->assertNull($config->unavailableReason());
    }

    /**
     * Explore ships no npm dependency. The Google Maps JS API is a runtime
     * script tag, and the renderer is a plain IIFE with nothing to bundle.
     *
     * @test
     */
    public function explore_adds_no_javascript_dependency(): void
    {
        $package = json_decode((string) file_get_contents(base_path('package.json')), true);

        $declared = array_merge(
            array_keys($package['dependencies'] ?? []),
            array_keys($package['devDependencies'] ?? []),
        );

        foreach ($declared as $name) {
            $this->assertStringNotContainsString('google', strtolower($name));
        }

        $this->assertStringNotContainsString('require(', $this->executableRenderer());
        $this->assertStringNotContainsString('import ', $this->executableRenderer());
    }
}
