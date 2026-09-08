<?php

namespace Tests\Feature\LocationDna;

use Illuminate\Support\ViewErrorBag;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * The Search Areas serialiser must never turn "we have not read the stored geometry yet"
 * into "this listing has no geometry".
 *
 * WHAT BROKE, AND WHY NO EXISTING TEST COULD SEE IT
 * -------------------------------------------------
 * `ldnaSerialize()` rebuilt `polygons` and `radius_searches` from `ldnaOverlays` on every
 * call. That array is populated in exactly one place — the two hydration loops inside
 * `ldnaInitMap()` — so before the map initialises it is empty, and serialising through it
 * wrote `[]` over stored geometry. `ldnaSerialize()` is reachable from four controls that
 * need no map at all (Preferred State, ZIP tags, Flexible Location, Location Notes), so a
 * user editing a note on a listing carrying five polygons saved `"polygons":[]` with no
 * error and no map on which to notice.
 *
 * Every Location DNA test in this suite asserts either server-rendered markup presence or
 * server-side matching. Neither can observe a client-side serialiser, so the defect was
 * invisible to all of them. `tests/bootstrap.php` additionally blanks
 * `GOOGLE_PLACES_API_KEY` suite-wide (INV-11), which means the suite has only ever run in
 * the exact degraded state that triggers this — and passed.
 *
 * HOW THIS TEST WORKS, AND WHY IT IS NOT A MOCK OF ITSELF
 * -------------------------------------------------------
 * It renders the REAL partial, extracts its ONE `<script>` block verbatim, and executes
 * that exact source in node against a small DOM/SDK shim. The code under test is the
 * shipped code, byte for byte, with Blade's `@json` seeding already applied — not a
 * transcription of it. Only the browser around it is stubbed, which is the part that is
 * genuinely incidental to the question being asked.
 *
 * NETWORK-FREE BY CONSTRUCTION. `fetch` is shimmed to a never-settling thenable and
 * `setTimeout` to a no-op, so no request is issued, no timer fires, and the boundary queue
 * (Nominatim / Census TIGER) cannot run. The run is fully deterministic.
 *
 * @see \Tests\Feature\LocationDna\SearchAreasSerializerSourceContractTest for the
 *      node-free tripwire that guards the same rule when no JS runtime is available.
 */
class SearchAreasSerializerGeometryGuardTest extends TestCase
{
    /** A stored blob carrying every geometry shape the widget can hold. */
    private const STORED = [
        'cities'            => [],
        'zip_codes'         => [],
        'neighborhoods'     => [],
        'counties'          => [],
        'state'             => 'Florida',
        'polygons'          => [[
            'label' => 'Beach block',
            'path'  => [
                ['lat' => 27.80, 'lng' => -82.78],
                ['lat' => 27.82, 'lng' => -82.78],
                ['lat' => 27.82, 'lng' => -82.75],
            ],
        ]],
        'radius_searches'   => [
            ['address' => '100 Central Ave, St. Petersburg, FL', 'lat' => 27.7709, 'lng' => -82.6403, 'radius_miles' => 5],
            ['label' => 'Circle 1', 'lat' => 27.9000, 'lng' => -82.5000, 'radius_miles' => 3],
        ],
        'flexible_location' => true,
        'location_notes'    => 'Prefer walkable blocks.',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // 1–3: the map never initialises (no Google credential / blocked SDK)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_existing_polygon_survives_serialization_when_map_is_unavailable(): void
    {
        $blob = $this->serializeWithoutMap();

        $this->assertCount(1, $blob['polygons'], 'the stored polygon was dropped');
        $this->assertSame('Beach block', $blob['polygons'][0]['label']);
        $this->assertCount(3, $blob['polygons'][0]['path']);
        $this->assertEqualsWithDelta(27.80, $blob['polygons'][0]['path'][0]['lat'], 0.00001);
        $this->assertEqualsWithDelta(-82.78, $blob['polygons'][0]['path'][0]['lng'], 0.00001);
    }

    public function test_existing_radius_search_survives_serialization_when_map_is_unavailable(): void
    {
        $blob = $this->serializeWithoutMap();

        $this->assertCount(2, $blob['radius_searches'], 'stored radius/circle entries were dropped');

        // The addressed radius search keeps its address — the Radius Search contract.
        $this->assertSame('100 Central Ave, St. Petersburg, FL', $blob['radius_searches'][0]['address']);
        $this->assertSame(5, $blob['radius_searches'][0]['radius_miles']);

        // The drawn circle keeps its label and no address — a DISTINCT payload shape that
        // must not be collapsed into the radius-search one.
        $this->assertSame('Circle 1', $blob['radius_searches'][1]['label']);
        $this->assertArrayNotHasKey('address', $blob['radius_searches'][1]);
        $this->assertSame(3, $blob['radius_searches'][1]['radius_miles']);
    }

    public function test_editing_another_field_does_not_delete_geometry(): void
    {
        // Exactly the production path: type in Location Notes and Preferred State, both of
        // which call ldnaSerialize() directly and neither of which needs a map.
        $blob = $this->serialize(self::STORED, mapAvailable: false, ops: <<<'JS'
            document.getElementById('ldna-location-notes').value = 'Now near the water.';
            document.getElementById('ldna-state-input').value    = 'Georgia';
            window.ldnaSerialize();
        JS);

        $this->assertSame('Now near the water.', $blob['location_notes'], 'the edit itself did not serialise');
        $this->assertSame('Georgia', $blob['state'], 'the edit itself did not serialise');

        $this->assertCount(1, $blob['polygons'], 'editing an unrelated field destroyed the polygon');
        $this->assertCount(2, $blob['radius_searches'], 'editing an unrelated field destroyed the radius searches');
        $this->assertTrue($blob['flexible_location']);
    }

    public function test_a_full_save_reload_edit_save_reload_cycle_loses_no_geometry(): void
    {
        // SAVE — a real edit to a map-independent field, with no map available.
        $afterFirstSave = $this->serialize(self::STORED, mapAvailable: false, ops: <<<'JS'
            document.getElementById('ldna-location-notes').value = 'Walkable, near the water.';
            window.ldnaSerialize();
        JS);

        // RELOAD — the partial is re-rendered from what was just persisted, which is what
        // the Livewire host hands back as $existingLocationDna on the edit screen.
        // EDIT AN UNRELATED FIELD, SAVE AGAIN.
        $afterSecondSave = $this->serialize($afterFirstSave, mapAvailable: false, ops: <<<'JS'
            document.getElementById('ldna-flexible').checked = false;
            window.ldnaSerialize();
        JS);

        // RELOAD once more and serialise with no further edit — the resting state.
        $afterReload = $this->serialize($afterSecondSave, mapAvailable: false);

        foreach ([
            'after first save'  => $afterFirstSave,
            'after second save' => $afterSecondSave,
            'after reload'      => $afterReload,
        ] as $stage => $blob) {
            $this->assertCount(1, $blob['polygons'], "polygon lost {$stage}");
            $this->assertSame('Beach block', $blob['polygons'][0]['label'], "polygon label lost {$stage}");
            $this->assertCount(3, $blob['polygons'][0]['path'], "polygon vertices lost {$stage}");
            $this->assertCount(2, $blob['radius_searches'], "radius/circle entries lost {$stage}");
            $this->assertSame(
                '100 Central Ave, St. Petersburg, FL',
                $blob['radius_searches'][0]['address'],
                "radius search address lost {$stage}"
            );
            $this->assertSame('Circle 1', $blob['radius_searches'][1]['label'], "drawn circle lost {$stage}");
        }

        // Both edits landed and neither was undone by a later hop.
        $this->assertSame('Walkable, near the water.', $afterReload['location_notes']);
        $this->assertFalse($afterReload['flexible_location']);
        $this->assertSame('Florida', $afterReload['state']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4: the map DID initialise — overlays are authoritative and still rebuild
    // ─────────────────────────────────────────────────────────────────────────

    public function test_overlay_serialization_still_works_once_map_state_is_authoritative(): void
    {
        $blob = $this->serialize(self::STORED, mapAvailable: true);

        // Round-tripped THROUGH the live overlays, not passed through untouched: the
        // hydration loops built Polygon/Circle objects and the serialiser read them back.
        $this->assertCount(1, $blob['polygons']);
        $this->assertSame('Beach block', $blob['polygons'][0]['label']);
        $this->assertCount(3, $blob['polygons'][0]['path']);

        $this->assertCount(2, $blob['radius_searches']);
        $this->assertSame('100 Central Ave, St. Petersburg, FL', $blob['radius_searches'][0]['address']);
        $this->assertEqualsWithDelta(5.0, $blob['radius_searches'][0]['radius_miles'], 0.01);
        $this->assertSame('Circle 1', $blob['radius_searches'][1]['label']);
        $this->assertEqualsWithDelta(3.0, $blob['radius_searches'][1]['radius_miles'], 0.01);
    }

    public function test_removing_an_overlay_still_persists_once_map_state_is_authoritative(): void
    {
        // The guard must not make deletion impossible: with a live map, removing the
        // polygon must still produce an empty polygons array.
        $blob = $this->serialize(self::STORED, mapAvailable: true, ops: <<<'JS'
            window.ldnaRemoveOverlay(0);   /* index 0 is the hydrated polygon */
        JS);

        $this->assertSame([], $blob['polygons'], 'a real deletion no longer persists');
        $this->assertCount(2, $blob['radius_searches'], 'deleting the polygon disturbed the circles');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Harness
    // ─────────────────────────────────────────────────────────────────────────

    private function serializeWithoutMap(): array
    {
        return $this->serialize(self::STORED, mapAvailable: false);
    }

    /**
     * Render the partial, execute its real script in node, return the decoded contents of
     * `#ldna-json-field` — i.e. exactly the string the Livewire bridge would submit.
     */
    private function serialize(array $stored, bool $mapAvailable, string $ops = 'window.ldnaSerialize();'): array
    {
        $node = $this->nodeBinary();

        $html    = $this->renderPartial($stored);
        $script  = $this->widgetScript($html);
        $seeded  = $this->seededControlState($html);
        $dir     = sys_get_temp_dir() . '/ldna-serializer-' . bin2hex(random_bytes(6));
        mkdir($dir, 0700, true);

        try {
            file_put_contents("{$dir}/run.js", $this->harness($script, $seeded, $mapAvailable, $ops));

            $process = new Process([$node, "{$dir}/run.js"], $dir, null, null, 30);
            $process->run();

            $this->assertSame(
                0,
                $process->getExitCode(),
                "node harness failed:\n" . $process->getErrorOutput() . $process->getOutput()
            );

            $decoded = json_decode(trim($process->getOutput()), true);
            $this->assertIsArray($decoded, 'harness did not emit a JSON blob: ' . $process->getOutput());

            return $decoded;
        } finally {
            @unlink("{$dir}/run.js");
            @rmdir($dir);
        }
    }

    private function renderPartial(array $stored): string
    {
        return view('partials.location-dna.map-input', [
            'existingLocationDna' => $stored,
            'errors'              => new ViewErrorBag(),
        ])->render();
    }

    /**
     * The state the SERVER put on the three map-independent controls the serialiser reads.
     *
     * Read out of the rendered HTML rather than restated from the fixture, so the harness
     * reflects what Blade actually emitted. It also means these assertions incidentally
     * prove the server seeds those controls correctly — a hand-written stub would have
     * asserted only that the harness agrees with itself.
     */
    private function seededControlState(string $html): array
    {
        preg_match('#<input[^>]*id="ldna-flexible"[^>]*>#s', $html, $flex);
        preg_match('#<textarea[^>]*id="ldna-location-notes"[^>]*>(.*?)</textarea>#s', $html, $notes);
        preg_match('#<input[^>]*id="ldna-state-input"[^>]*value="([^"]*)"#s', $html, $state);

        $this->assertNotEmpty($flex[0] ?? '', 'the Flexible Location control is no longer rendered');
        $this->assertNotEmpty($notes[0] ?? '', 'the Location Notes control is no longer rendered');

        return [
            'ldna-flexible'       => ['checked' => str_contains($flex[0], 'checked')],
            'ldna-location-notes' => ['value' => html_entity_decode($notes[1] ?? '', ENT_QUOTES)],
            'ldna-state-input'    => ['value' => html_entity_decode($state[1] ?? '', ENT_QUOTES)],
        ];
    }

    /** The partial's one and only `<script>` body, rendered with real stored data. */
    private function widgetScript(string $html): string
    {
        $this->assertSame(
            1,
            substr_count($html, '<script>'),
            'the partial no longer has exactly one script block — update this harness rather than loosening it'
        );

        preg_match('#<script>(.*)</script>#s', $html, $m);
        $this->assertNotEmpty($m[1] ?? '', 'could not extract the widget script');

        return $m[1];
    }

    /**
     * A DOM + Maps-SDK shim just large enough to run the real widget.
     *
     * `fetch` never settles and `setTimeout` never fires, so the run issues no request and
     * schedules no work — that is what keeps this test network-free and deterministic, and
     * it also means `ldnaTryInit`'s retry loop cannot spin.
     */
    private function harness(string $script, array $seeded, bool $mapAvailable, string $ops): string
    {
        $google = $mapAvailable ? $this->googleShim() : 'var google = undefined;';
        $seedJs = json_encode($seeded, JSON_THROW_ON_ERROR);

        return <<<JS
        'use strict';

        /* ── DOM shim ────────────────────────────────────────────────────────── */
        function El(id) {
          this.id = id;
          this.value = '';
          this.checked = false;
          this.style = {};
          this.dataset = {};
          this.className = '';
          this.innerHTML = '';
          this.content = { cloneNode: function () { return new El('frag'); } };
        }
        El.prototype.addEventListener = function () {};
        El.prototype.removeEventListener = function () {};
        El.prototype.appendChild = function () {};
        El.prototype.remove = function () {};
        El.prototype.closest = function () { return null; };
        El.prototype.querySelector = function () { return null; };
        El.prototype.querySelectorAll = function () { return []; };
        El.prototype.getBoundingClientRect = function () {
          return { width: {$this->widthLiteral($mapAvailable)}, height: {$this->widthLiteral($mapAvailable)} };
        };
        Object.defineProperty(El.prototype, 'parentElement', {
          get: function () { return new El('parent'); }
        });

        /* Controls the server rendered with state on them, seeded from the real HTML. */
        var __els = {};
        var __seed = {$seedJs};
        Object.keys(__seed).forEach(function (id) {
          var el = new El(id);
          Object.keys(__seed[id]).forEach(function (k) { el[k] = __seed[id][k]; });
          __els[id] = el;
        });

        var document = {
          readyState: 'complete',
          getElementById: function (id) {
            if (!__els[id]) { __els[id] = new El(id); }
            return __els[id];
          },
          createElement: function (t) { return new El(t); },
          querySelectorAll: function () { return []; },
          addEventListener: function () {}
        };

        var window = globalThis;
        globalThis.document = document;

        /* No timers, no network. */
        var setTimeout = function () { return 0; };
        globalThis.setTimeout = setTimeout;
        var never = { then: function () { return never; },
                      catch: function () { return never; },
                      finally: function () { return never; } };
        globalThis.fetch = function () { return never; };

        function Observer() {}
        Observer.prototype.observe = function () {};
        Observer.prototype.disconnect = function () {};
        globalThis.MutationObserver = Observer;
        globalThis.ResizeObserver = Observer;
        globalThis.Event = function () {};

        /* ── Google Maps SDK shim (present or absent) ────────────────────────── */
        {$google}
        globalThis.google = google;

        /* ── The real, unmodified widget source ──────────────────────────────── */
        {$script}

        /* ── Operations under test ───────────────────────────────────────────── */
        {$ops}

        process.stdout.write(document.getElementById('ldna-json-field').value);
        JS;
    }

    /** Container must measure non-zero for ldnaTryInit() to proceed to ldnaInitMap(). */
    private function widthLiteral(bool $mapAvailable): string
    {
        return $mapAvailable ? '800' : '0';
    }

    private function googleShim(): string
    {
        return <<<'JS'
        function LatLng(lat, lng) { this._lat = lat; this._lng = lng; }
        LatLng.prototype.lat = function () { return this._lat; };
        LatLng.prototype.lng = function () { return this._lng; };

        function Polygon(opts) { this._paths = (opts && opts.paths) || []; }
        Polygon.prototype.getPath = function () {
          var pts = this._paths.map(function (p) { return new LatLng(p.lat, p.lng); });
          return { forEach: function (cb) { pts.forEach(cb); } };
        };
        Polygon.prototype.setMap = function () {};

        function Circle(opts) {
          opts = opts || {};
          this._center = opts.center || { lat: 0, lng: 0 };
          this._radius = opts.radius || 0;
        }
        Circle.prototype.getCenter = function () { return new LatLng(this._center.lat, this._center.lng); };
        Circle.prototype.getRadius = function () { return this._radius; };
        Circle.prototype.getBounds  = function () { return {}; };
        Circle.prototype.setMap = function () {};

        function Marker() {}
        Marker.prototype.setMap = function () {};

        function GMap() {
          this.data = {
            setStyle: function () {},
            addGeoJson: function () { return []; },
            remove: function () {}
          };
        }
        GMap.prototype.fitBounds = function () {};
        GMap.prototype.setCenter = function () {};
        GMap.prototype.setZoom   = function () {};
        GMap.prototype.panTo     = function () {};

        function Bounds() {}
        Bounds.prototype.extend = function () {};
        Bounds.prototype.isEmpty = function () { return true; };
        Bounds.prototype.union = function () {};

        function Autocomplete() {}
        Autocomplete.prototype.addListener = function () {};
        Autocomplete.prototype.getPlace = function () { return null; };

        function Geocoder() {}
        Geocoder.prototype.geocode = function () {};

        var google = {
          maps: {
            Map: GMap,
            Polygon: Polygon,
            Circle: Circle,
            Marker: Marker,
            Polyline: function () { this.setMap = function () {}; },
            LatLngBounds: Bounds,
            Geocoder: Geocoder,
            SymbolPath: { CIRCLE: 0, BACKWARD_CLOSED_ARROW: 1 },
            places: { Autocomplete: Autocomplete },
            event: {
              addListener: function () { return {}; },
              removeListener: function () {},
              trigger: function () {}
            }
          }
        };
        JS;
    }

    private function nodeBinary(): string
    {
        $which = trim((string) shell_exec('command -v node 2>/dev/null'));

        if ($which === '' || ! is_executable($which)) {
            $this->markTestSkipped(
                'node is not available; the JS behaviour of ldnaSerialize() cannot be executed here. '
                . 'SearchAreasSerializerSourceContractTest still guards the same rule without node.'
            );
        }

        return $which;
    }
}
