<?php

namespace Tests\Unit\Spatial;

use Tests\TestCase;

/**
 * G0 — the geometry-preservation guard in the shared Search Areas serializer.
 *
 * WHAT G0 FIXES
 * -------------
 * `ldnaSerialize()` rebuilt `polygons` and `radius_searches` from `ldnaOverlays`
 * — the geometry editor's working set — on EVERY call. When the editor never
 * hydrated (dead Google credential, tile failure, hidden container), that set is
 * empty, so editing any unrelated dimension (a city, the notes field) serialized
 * `polygons: []` and `radius_searches: []` and the next save destroyed saved
 * geometry. An unhydrated editor is not a user intent to clear.
 *
 * The guard makes the rebuild conditional on `ldnaGeometryHydrated`.
 *
 * ⚠️  COVERAGE LIMITATION — READ BEFORE TRUSTING A GREEN RUN  ⚠️
 * -------------------------------------------------------------------------
 * The guard is JavaScript. This project has no JavaScript test runner, and
 * browser automation has NOT been authorised. Every assertion below is
 * STRUCTURAL — it reads source text. None of them executes `ldnaSerialize()`,
 * renders a page, or opens a browser.
 *
 * A green run proves the guard is present and correctly positioned. It does NOT
 * prove the wipe is prevented at runtime. That proof requires the browser gate,
 * and until then G0 must not be described as behaviourally verified.
 *
 * These tests are deliberately POSITIONAL as well as textual, because the defect
 * this guard closes is a race: the flag must be set AFTER hydration, not on
 * entry to `ldnaInitMap()`. A test that only checked "the flag exists" would
 * pass against the broken ordering.
 */
class SearchAreasGeometryGuardTest extends TestCase
{
    private const WIDGET = 'resources/views/partials/location-dna/map-input.blade.php';

    private function source(): string
    {
        $path = base_path(self::WIDGET);
        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    /** 1-indexed line number of the first line containing $needle. */
    private function lineOf(string $needle): int
    {
        foreach (explode("\n", $this->source()) as $i => $line) {
            if (str_contains($line, $needle)) {
                return $i + 1;
            }
        }

        $this->fail("Expected to find '{$needle}' in " . self::WIDGET);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // The flag itself
    // ─────────────────────────────────────────────────────────────────────────

    /** The flag is declared and starts false — fail-closed. */
    public function test_hydration_flag_is_declared_and_defaults_to_false(): void
    {
        $this->assertStringContainsString(
            'var ldnaGeometryHydrated = false;',
            $this->source(),
            'The guard flag must exist and default to false, so an editor that never '
            . 'hydrates cannot be mistaken for one that did.'
        );
    }

    /**
     * The name is renderer-independent.
     *
     * The serializer must not care whether geometry came from Google, MapLibre,
     * server hydration, cache or a future provider — only whether the editor's
     * working set is authoritative. A renderer-specific name would invite
     * renderer-specific branching later.
     */
    public function test_flag_name_is_renderer_independent(): void
    {
        $source = $this->source();

        foreach (['ldnaGeometryRestored', 'ldnaGoogleReady', 'ldnaMapLibreReady'] as $rejected) {
            $this->assertStringNotContainsString(
                $rejected,
                $source,
                "'{$rejected}' couples the guard to a renderer or to a restore action."
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // The guard
    // ─────────────────────────────────────────────────────────────────────────

    /** The rebuild is gated on the flag. */
    public function test_geometry_rebuild_is_gated_on_the_hydration_flag(): void
    {
        $this->assertStringContainsString(
            'if (ldnaGeometryHydrated) {',
            $this->source(),
            'The geometry rebuild must be conditional on editor hydration.'
        );
    }

    /**
     * BOTH destructive resets sit inside the guard.
     *
     * Guarding only one would still destroy the other dimension — the exact
     * class of bug G0 exists to close.
     */
    public function test_both_geometry_resets_are_inside_the_guard(): void
    {
        $guard    = $this->lineOf('if (ldnaGeometryHydrated) {');
        $polygons = $this->lineOf('ldnaState.polygons        = [];');
        $radii    = $this->lineOf('ldnaState.radius_searches = [];');

        $this->assertGreaterThan($guard, $polygons, 'polygons reset must be inside the guard.');
        $this->assertGreaterThan($guard, $radii, 'radius_searches reset must be inside the guard.');
    }

    /** The overlay rebuild loop is also inside the guard, not merely the resets. */
    public function test_overlay_rebuild_loop_is_inside_the_guard(): void
    {
        $this->assertGreaterThan(
            $this->lineOf('if (ldnaGeometryHydrated) {'),
            $this->lineOf('ldnaOverlays.forEach(function (item) {'),
            'The rebuild loop must sit inside the guard.'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // The race fix — the positional assertions
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * The flag is set AFTER both hydration loops.
     *
     * This is the race fix. `ldnaMapInitialized` is set on ENTRY to
     * `ldnaInitMap()`, roughly seventy lines before geometry hydration finishes.
     * Gating on that flag — or setting this one that early — leaves a window in
     * which the overlays are empty while the renderer reports itself ready, and
     * an interaction during that window still destroys geometry.
     */
    public function test_flag_is_set_after_both_hydration_loops(): void
    {
        $polygonLoop = $this->lineOf('ldnaState.polygons.forEach(function (poly, i) {');
        $radiusLoop  = $this->lineOf('ldnaState.radius_searches.forEach(function (r, i) {');
        $flagSet     = $this->lineOf('ldnaGeometryHydrated = true;');

        $this->assertGreaterThan(
            $polygonLoop,
            $flagSet,
            'The flag must be set after the polygon hydration loop.'
        );
        $this->assertGreaterThan(
            $radiusLoop,
            $flagSet,
            'The flag must be set after the radius_searches hydration loop.'
        );
    }

    /** The flag is set exactly once, so no early path can shortcut it. */
    public function test_flag_is_set_exactly_once(): void
    {
        $this->assertSame(
            1,
            substr_count($this->source(), 'ldnaGeometryHydrated = true;'),
            'Multiple assignments would make the hydration point ambiguous.'
        );
    }

    /**
     * The map-initialised flag is NOT reused as the geometry guard.
     *
     * Pins the corrected design against a well-intentioned "simplification"
     * back to the racy version.
     */
    public function test_map_initialised_flag_is_not_used_as_the_geometry_guard(): void
    {
        $source = $this->source();

        foreach ([
            'if (ldnaMapInitialized) {\n      ldnaState.polygons',
            'if (ldnaMapInitialized) { ldnaState.polygons',
        ] as $racy) {
            $this->assertStringNotContainsString(
                str_replace('\n', "\n", $racy),
                $source,
                'ldnaMapInitialized is set on entry to ldnaInitMap() and must never gate geometry.'
            );
        }

        // And the guard line itself names the hydration flag, not the init flag.
        $this->assertStringContainsString('if (ldnaGeometryHydrated) {', $source);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Explicit clear must still be reachable when hydrated
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * The explicit clear paths still call the serializer.
     *
     * They run only from the overlay-list UI, which exists only once hydration
     * completed — so the guard is open and the clear persists. If either stopped
     * calling `ldnaSerialize()`, clearing would silently stop persisting.
     */
    public function test_explicit_clear_paths_still_invoke_the_serializer(): void
    {
        $source = $this->source();

        $this->assertStringContainsString('window.ldnaClearAllOverlays = function () {', $source);
        $this->assertGreaterThan(
            $this->lineOf('window.ldnaClearAllOverlays = function () {'),
            $this->lineOf('ldnaOverlays = [];'),
            'Clear-all must empty the working set before serializing.'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // The temporary nature of G0 must stay documented in the code
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * The interim limitation is recorded at the guard.
     *
     * G0 knowingly makes intentional geometry clearing impossible while the
     * editor is unavailable. That trade-off must not become silent folklore:
     * whoever reads this code next has to learn it is temporary and that G1
     * replaces it.
     */
    public function test_interim_limitation_is_documented_at_the_guard(): void
    {
        $source = $this->source();

        $this->assertStringContainsString('TEMPORARY SAFETY LIMITATION', $source);
        $this->assertStringContainsString('NOT THE PERMANENT DESIGN', $source);
        $this->assertStringContainsString('phase-2b-geometry-contract.md', $source);
    }

    /** Non-geometry dimensions are still serialized unconditionally. */
    public function test_non_geometry_dimensions_remain_outside_the_guard(): void
    {
        $guard = $this->lineOf('if (ldnaGeometryHydrated) {');

        foreach ([
            'ldnaState.flexible_location = flexEl.checked;',
            'ldnaState.location_notes    = notesEl.value.trim();',
            'ldnaState.state = stateEl.value.trim();',
        ] as $assignment) {
            $this->assertLessThan(
                $guard,
                $this->lineOf($assignment),
                'Non-geometry dimensions must serialize regardless of editor hydration: '
                . $assignment
            );
        }
    }
}
