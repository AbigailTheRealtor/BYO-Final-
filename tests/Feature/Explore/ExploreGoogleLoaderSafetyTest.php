<?php

namespace Tests\Feature\Explore;

use App\Services\Explore\ExploreGoogleConfig;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Explore\Concerns\MakesExploreListings;
use Tests\TestCase;

/**
 * Google 3D cannot become a recurring charge — §20 A through O.
 *
 * WHY THESE TESTS READ THE SHIPPED FILE
 * -------------------------------------
 * Google 3D is billed and browser-side. A server-side budget cannot see it, let
 * alone stop it, so the only protection is that the renderer is structurally
 * incapable of loading twice, recreating the world, or retrying in a loop.
 * There is no PHP seam to assert against — the guarantee lives in the
 * JavaScript, so that is what is inspected.
 *
 * The file is read with comments STRIPPED before any absence is asserted. The
 * header names the APIs this renderer must never call, which is precisely the
 * documentation worth keeping, and a naive substring scan would punish the
 * explanation rather than the behaviour.
 *
 * NO TEST HERE MAKES A LIVE GOOGLE REQUEST. Nothing is fetched, no script is
 * executed, and the browser key is never set to a real value.
 */
class ExploreGoogleLoaderSafetyTest extends TestCase
{
    use DatabaseTransactions;
    use MakesExploreListings;

    protected function setUp(): void
    {
        parent::setUp();
        config(['explore.enabled' => true]);
    }

    private function renderer(): string
    {
        $path = public_path('js/explore/explore-3d.js');
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /** The renderer with comments removed — see the class note. */
    private function code(): string
    {
        $source = (string) preg_replace('#/\*.*?\*/#s', '', $this->renderer());

        return (string) preg_replace('#(^|[^:])//.*$#m', '$1', $source);
    }

    /**
     * One function's body, delimited by brace matching rather than by a
     * fixed-length window.
     *
     * A `substr()` window is the wrong tool here: it silently overruns into the
     * next function, so an assertion about renderPanel() ends up reading
     * loadGoogleMaps() and fails for a reason that has nothing to do with the
     * behaviour under test. The brace walk stops exactly where the function does.
     */
    private function body(string $declaration): string
    {
        $code  = $this->code();
        $start = strpos($code, $declaration);

        $this->assertNotFalse($start, "{$declaration} not found in the renderer");

        $open = strpos($code, '{', $start);
        $this->assertNotFalse($open);

        $depth = 0;

        for ($i = $open, $len = strlen($code); $i < $len; $i++) {
            if ($code[$i] === '{') $depth++;
            if ($code[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($code, $start, $i - $start + 1);
                }
            }
        }

        $this->fail("unbalanced braces walking {$declaration}");
    }

    /* ── A / B — nothing loads when it should not ───────────────────────── */

    /**
     * §20-A. Explore disabled: the page does not exist, so no loader can run
     * and no Google URL can reach a browser.
     *
     * @test
     */
    public function google_never_loads_while_explore_is_disabled(): void
    {
        config(['explore.enabled' => false, 'explore.google.browser_key' => 'irrelevant']);

        $this->get('/explore')->assertNotFound();
    }

    /**
     * §20-B. Credential missing: a stated unavailable panel, and — the part
     * that matters — the readiness flag the renderer gates on is off, so the
     * loader is never entered and maps.googleapis.com is never contacted.
     *
     * @test
     */
    public function a_missing_credential_produces_no_google_request(): void
    {
        config(['explore.google.browser_key' => null]);

        $response = $this->get('/explore');

        $response->assertOk();
        $response->assertSee('data-google-ready="0"', false);
        $response->assertSee('The 3D neighbourhood is unavailable');
        $response->assertDontSee('maps.googleapis.com');
    }

    /**
     * The independent kill switch: Explore keeps serving, Google does not load.
     *
     * Not "load Google and then hide the map" — an expensive provider must be
     * untouched when it is switched off, or the switch protects nothing.
     *
     * @test
     */
    public function the_google_kill_switch_stops_the_loader_while_explore_keeps_serving(): void
    {
        config([
            'explore.google.enabled'     => false,
            'explore.google.browser_key' => 'a-configured-key',
        ]);

        $response = $this->get('/explore');

        $response->assertOk();
        $response->assertSee('data-google-ready="0"', false);
        $response->assertDontSee('maps.googleapis.com');
        $response->assertDontSee('a-configured-key');

        // And the reason distinguishes "switched off" from "never configured",
        // so nobody goes looking for a missing key that is not missing.
        $reason = (new ExploreGoogleConfig())->unavailableReason();
        $this->assertStringContainsString('switched off', (string) $reason);
    }

    /** @test */
    public function readiness_requires_both_the_switch_and_the_credential(): void
    {
        config(['explore.google.enabled' => true, 'explore.google.browser_key' => null]);
        $this->assertFalse((new ExploreGoogleConfig())->isReady());

        config(['explore.google.enabled' => false, 'explore.google.browser_key' => 'k']);
        $this->assertFalse((new ExploreGoogleConfig())->isReady());

        config(['explore.google.enabled' => true, 'explore.google.browser_key' => 'k']);
        $this->assertTrue((new ExploreGoogleConfig())->isReady());
    }

    /* ── C / D — one load, ever ─────────────────────────────────────────── */

    /**
     * §20-C / §20-D. The loader is latched by a memoized promise, so a repeated
     * caller and a simultaneous caller both receive the SAME promise rather
     * than inserting a second script.
     *
     * @test
     */
    public function the_google_loader_is_idempotent(): void
    {
        $code = $this->code();

        $this->assertStringContainsString('if (state.googleLoadPromise) return state.googleLoadPromise;', $code);
        $this->assertStringContainsString('state.googleLoadPromise = new Promise', $code);

        // A pre-existing tag anywhere on the page is adopted, not duplicated.
        $this->assertStringContainsString("document.querySelector('script[src^=\"' + GOOGLE_SCRIPT_PREFIX", $code);

        // Exactly one place in the whole file appends a Google script.
        $this->assertSame(
            1,
            substr_count($code, 'document.head.appendChild(script)'),
            'there must be exactly one script insertion site'
        );
    }

    /**
     * A rejected load STAYS rejected. The promise is never cleared on failure,
     * so a caller re-entering after a failure receives the rejection instead of
     * triggering a fresh attempt against a billed script.
     *
     * @test
     */
    public function a_failed_load_is_not_silently_retried(): void
    {
        $code = $this->code();

        $this->assertStringNotContainsString('state.googleLoadPromise = null', $code);
        $this->assertStringNotContainsString('googleLoadPromise = undefined', $code);
    }

    /* ── E / F / G / H / I — one world, updated in place ────────────────── */

    /**
     * §20-E / §20-F. Camera movement calls our own listings endpoint. It does
     * not reload Google and it does not construct a second world.
     *
     * @test
     */
    public function camera_movement_neither_reloads_google_nor_rebuilds_the_world(): void
    {
        $code = $this->code();

        // The only callers of the loader and the world builder are the boot
        // block. Neither appears inside an event handler.
        //
        // `loadGoogleMaps()` appears twice in the file — its declaration and
        // its single invocation — so the declaration is discounted rather than
        // matched by a looser pattern that would also miss a real second call.
        $this->assertSame(
            1,
            substr_count($code, 'loadGoogleMaps()') - substr_count($code, 'function loadGoogleMaps()'),
            'the loader is invoked exactly once'
        );
        $this->assertSame(1, substr_count($code, 'buildMap();'), 'the world is built once');

        // Camera events lead to a debounced request to OUR server, nothing else.
        $this->assertMatchesRegularExpression(
            "/gmp-centerchange.*?requestListings\(false\)/s",
            $code
        );
    }

    /** §20-F. The world builder refuses to run twice. @test */
    public function the_world_is_latched_against_a_second_construction(): void
    {
        $code = $this->code();

        $this->assertStringContainsString('if (state.worldBuilt) return;', $code);
        $this->assertStringContainsString('state.worldBuilt = true;', $code);
        $this->assertSame(1, substr_count($code, 'new Map3D('), 'one Map3DElement construction site');
    }

    /**
     * §20-G / §20-I. New markers update the marker layer against the existing
     * world. renderMarkers() must never touch the world itself.
     *
     * @test
     */
    public function marker_refresh_updates_the_layer_and_never_recreates_the_world(): void
    {
        $body = $this->body('function renderMarkers()');

        $this->assertNotEmpty($body);
        foreach (['new Map3D', 'buildMap', 'loadGoogleMaps', 'appendChild(map)'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $body, "renderMarkers must not {$forbidden}");
        }

        // It reads the existing world and returns when there is none.
        $this->assertStringContainsString('if (!state.map3d) return;', $body);
    }

    /**
     * §20-H. Selecting a property moves the existing camera. Opening and
     * closing the panel constructs nothing.
     *
     * @test
     */
    public function property_selection_moves_the_camera_rather_than_rebuilding(): void
    {
        $fly = $this->body('function flyTo(');

        $this->assertStringContainsString('flyCameraTo', $fly);
        $this->assertStringNotContainsString('new Map3D', $fly);

        foreach (['function renderPanel(', 'function closePanel(', 'function selectListing('] as $fn) {
            $chunk = $this->body($fn);
            $this->assertStringNotContainsString('new Map3D', $chunk, "{$fn} must not build a world");
            $this->assertStringNotContainsString('loadGoogleMaps', $chunk, "{$fn} must not load Google");
        }
    }

    /* ── J — no retry storm ─────────────────────────────────────────────── */

    /**
     * §20-J. A load failure sets a message and stops. No timer, no backoff, no
     * page reload — an automatic retry against a billed script is how a broken
     * page becomes a recurring charge.
     *
     * @test
     */
    public function a_google_failure_starts_no_retry_loop(): void
    {
        $code = $this->code();

        foreach (['setInterval', 'location.reload', 'window.location =', 'requestAnimationFrame'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $code, "{$forbidden} must not appear");
        }

        // The only timer in the file is the viewport debounce.
        $this->assertSame(1, substr_count($code, 'setTimeout('), 'the debounce is the only timer');
    }

    /* ── K / L / M — the APIs that must stay absent ─────────────────────── */

    /** §20-K / §20-L / §20-M. @test */
    public function no_billable_google_api_beyond_the_3d_map_is_reachable(): void
    {
        $code = strtolower($this->code());

        foreach ([
            'places', 'autocomplete', 'geocod', 'directionsservice', 'distancematrix',
            'routes.googleapis', 'roads.googleapis', 'maps/api/place', 'maps/api/geocode',
            'maps/api/directions', 'maps/api/distancematrix',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $code, "{$forbidden} must not be reachable");
        }
    }

    /** The library list is server config, so widening it is a reviewable diff. @test */
    public function the_library_list_comes_from_the_server_and_is_maps3d_only(): void
    {
        $this->assertSame(['maps3d'], (new ExploreGoogleConfig())->libraries());
        $this->assertStringContainsString('shell.dataset.googleLibraries', $this->code());
    }

    /* ── N — listeners bound once ───────────────────────────────────────── */

    /**
     * §20-N. Drive listeners bind to `document` and to the map container, both
     * of which outlive any single world, so a second binding would double every
     * keystroke and every touch move — and each of those calls requestListings().
     *
     * @test
     */
    public function drive_listeners_are_bound_exactly_once(): void
    {
        $code = $this->code();

        $this->assertStringContainsString('if (! state.driveInstalled) {', $code);
        $this->assertStringContainsString('state.driveInstalled = true;', $code);
        $this->assertSame(1, substr_count($code, 'installKeyboardDrive();'));
        $this->assertSame(1, substr_count($code, 'installTouchDrive();'));
    }

    /* ── O — bounded calls to our own server ────────────────────────────── */

    /**
     * §20-O. Rapid movement is debounced, and a superseded viewport fetch is
     * ABORTED rather than left to land. The seq guard stopped a stale response
     * repainting the map; it did not stop the server answering it — and on a
     * cold tile answering it costs provider requests.
     *
     * @test
     */
    public function rapid_viewport_movement_is_debounced_and_coalesced(): void
    {
        $code = $this->code();

        $this->assertStringContainsString('MOVE_DEBOUNCE_MS', $code);
        $this->assertStringContainsString('window.clearTimeout(state.fetchTimer)', $code);
        $this->assertStringContainsString('state.inFlight.abort()', $code);
        $this->assertStringContainsString("error.name === 'AbortError'", $code);
        $this->assertStringContainsString('state.lastRenderedSeq', $code);
    }

    /** No credential may be hard-coded into the shipped renderer. @test */
    public function no_google_key_is_hard_coded(): void
    {
        $this->assertStringContainsString('shell.dataset.googleKey', $this->code());
        $this->assertDoesNotMatchRegularExpression('/AIza[0-9A-Za-z_\-]{20,}/', $this->renderer());
    }

    /**
     * The server key must never reach a page. GOOGLE_PLACES_API_KEY is used by
     * address validation and POI lookup; emitting it would publish a server
     * credential to every visitor.
     *
     * @test
     */
    public function the_places_server_key_is_never_emitted(): void
    {
        config([
            'explore.google.browser_key' => null,
            'services.google.places_key' => 'server-side-places-key',
            'google_places.api_key'      => 'server-side-places-key',
        ]);

        $this->get('/explore')->assertOk()->assertDontSee('server-side-places-key');
    }
}
