<?php

namespace Tests\Feature\Explore;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Explore\Concerns\MakesExploreListings;
use Tests\TestCase;

/**
 * §49-A / §49-B — Explore fails closed, and a missing Google credential is a
 * stated state rather than a broken map.
 */
class ExploreFeatureGateTest extends TestCase
{
    use DatabaseTransactions;
    use MakesExploreListings;

    /**
     * With the master flag off the feature is INVISIBLE, not disabled: the shell
     * and both data endpoints 404. A disabled feature that answers 403 tells the
     * caller it exists.
     *
     * @test
     */
    public function every_explore_route_404s_while_the_feature_is_disabled(): void
    {
        config(['explore.enabled' => false]);

        $this->makeListing();

        $this->get('/explore')->assertNotFound();
        $this->getJson('/api/explore/listings?bbox=' . $this->bboxAroundDefault())->assertNotFound();
        $this->getJson('/api/explore/listings/LK00000001')->assertNotFound();
    }

    /** @test */
    public function the_default_shipped_state_is_off(): void
    {
        $this->assertFalse(
            (bool) config('explore.enabled'),
            'Explore must ship disabled.'
        );

        $this->assertFalse(
            (bool) config('explore.vow_enabled'),
            'The VOW tier must ship disabled.'
        );
    }

    /**
     * §49-B. Explore enabled + Google credential absent is a THIRD state,
     * distinct from "Explore is off". The route serves, the listings endpoint
     * answers, and the map area explains itself — because a blank grey
     * rectangle is indistinguishable from a bug and no PHP test can see one.
     *
     * @test
     */
    public function a_missing_google_credential_renders_a_stated_unavailable_state(): void
    {
        config(['explore.enabled' => true, 'explore.google.browser_key' => null]);

        $response = $this->get('/explore');

        $response->assertOk();
        $response->assertSee('The 3D neighbourhood is unavailable');
        $response->assertSee('EXPLORE_GOOGLE_MAPS_BROWSER_KEY');
        $response->assertSee('data-google-ready="0"', false);

        // No Google request is even attempted without a key.
        $response->assertDontSee('maps.googleapis.com');
    }

    /**
     * The shell publishes NO listing data. Every property fact arrives from an
     * endpoint that re-decides eligibility; a template that pre-rendered
     * listings would put a licensing decision in a Blade file.
     *
     * @test
     */
    public function the_shell_renders_no_listing_data(): void
    {
        config(['explore.enabled' => true]);

        $listing = $this->makeListing(['unparsed_address' => '999 Secret Lane']);

        $response = $this->get('/explore');

        $response->assertOk();
        $response->assertDontSee('999 Secret Lane');
        $response->assertDontSee($listing->listing_key);
    }

    /**
     * With a credential present the shell hands the renderer everything it
     * needs and nothing it does not — no listing data, and the library list
     * from server config rather than a string in a bundle.
     *
     * @test
     */
    public function a_configured_credential_produces_a_ready_shell(): void
    {
        config([
            'explore.enabled'            => true,
            'explore.google.browser_key' => 'browser-key-for-this-environment',
            'explore.google.map_id'      => 'map-id-for-this-environment',
        ]);

        $response = $this->get('/explore');

        $response->assertOk();
        $response->assertSee('data-google-ready="1"', false);
        $response->assertSee('data-google-libraries="maps3d"', false);
        $response->assertDontSee('The 3D neighbourhood is unavailable');

        // Only maps3d. A Places library reaching this attribute is the failure
        // this asserts against.
        $response->assertDontSee('places', false);
    }

    /**
     * The credential is a browser key of its own. GOOGLE_PLACES_API_KEY is a
     * SERVER key for address validation and POI lookup; if it ever became a
     * fallback, every visitor would receive a server credential.
     *
     * @test
     */
    public function the_places_server_key_is_never_used_as_the_map_credential(): void
    {
        config([
            'explore.enabled'            => true,
            'explore.google.browser_key' => null,
            'services.google.places_key' => 'server-side-places-key',
            'google_places.api_key'      => 'server-side-places-key',
        ]);

        $response = $this->get('/explore');

        $response->assertOk();
        $response->assertSee('data-google-ready="0"', false);
        $response->assertDontSee('server-side-places-key');
    }
}
