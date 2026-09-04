<?php

namespace Tests\Feature\LocationDna;

use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * A node-free tripwire for the same rule
 * {@see \Tests\Feature\LocationDna\SearchAreasSerializerGeometryGuardTest} proves
 * behaviourally.
 *
 * WHY BOTH EXIST
 * --------------
 * The behavioural test is the real one: it executes the shipped serialiser and asserts on
 * the blob it produces. But it needs a JS runtime, and it skips where none is installed —
 * and a data-loss guard that can silently skip is a guard that can silently be removed.
 * This test needs nothing but Blade, so the rule always has at least one assertion standing
 * behind it in every environment.
 *
 * It asserts STRUCTURE, not wording: that the geometry rebuild is nested inside the
 * authority guard, and that the guard is armed only from the map-hydration path. That is
 * narrow enough to catch the regression re-appearing and loose enough to survive ordinary
 * edits to the surrounding code.
 */
class SearchAreasSerializerSourceContractTest extends TestCase
{
    private function script(): string
    {
        $html = view('partials.location-dna.map-input', [
            'existingLocationDna' => [],
            'errors'              => new ViewErrorBag(),
        ])->render();

        preg_match('#<script>(.*)</script>#s', $html, $m);
        $this->assertNotEmpty($m[1] ?? '', 'could not extract the widget script');

        return $m[1];
    }

    public function test_the_overlay_authority_flag_exists_and_starts_false(): void
    {
        $this->assertMatchesRegularExpression(
            '/var\s+ldnaOverlaysAuthoritative\s*=\s*false\s*;/',
            $this->script(),
            'ldnaOverlaysAuthoritative must be declared false — an empty overlay array is not '
            . 'evidence that the listing has no geometry.'
        );
    }

    public function test_the_geometry_rebuild_is_nested_inside_the_authority_guard(): void
    {
        $script = $this->script();

        $guardAt   = strpos($script, 'if (ldnaOverlaysAuthoritative) {');
        $resetAt   = strpos($script, 'ldnaState.polygons        = [];');
        $rebuildAt = strpos($script, 'ldnaState.radius_searches.push(');

        $this->assertNotFalse($guardAt, 'the authority guard is gone from ldnaSerialize()');
        $this->assertNotFalse($resetAt, 'the polygons reset could not be located');
        $this->assertNotFalse($rebuildAt, 'the radius_searches rebuild could not be located');

        $this->assertGreaterThan(
            $guardAt,
            $resetAt,
            'ldnaSerialize() clears stored polygons OUTSIDE the authority guard — this is the '
            . 'exact defect that erased saved geometry whenever the map had not initialised.'
        );
        $this->assertGreaterThan(
            $guardAt,
            $rebuildAt,
            'the radius_searches rebuild sits outside the authority guard.'
        );
    }

    public function test_authority_is_armed_only_after_map_hydration(): void
    {
        $script = $this->script();

        $this->assertSame(
            1,
            preg_match_all('/ldnaOverlaysAuthoritative\s*=\s*true\s*;/', $script),
            'ldnaOverlaysAuthoritative must be armed in exactly one place — the map hydration path.'
        );

        $armAt      = strpos($script, 'ldnaOverlaysAuthoritative = true;');
        $initAt     = strpos($script, 'function ldnaInitMap()');
        $polygonsAt = strpos($script, 'ldnaState.polygons.forEach(function (poly, i)');
        $radiiAt    = strpos($script, 'ldnaState.radius_searches.forEach(function (r, i)');

        $this->assertNotFalse($armAt);
        $this->assertNotFalse($initAt);
        $this->assertNotFalse($polygonsAt);
        $this->assertNotFalse($radiiAt);

        $this->assertGreaterThan($initAt, $armAt, 'authority is armed outside ldnaInitMap()');
        $this->assertGreaterThan(
            $polygonsAt,
            $armAt,
            'authority is armed before the stored polygons have been mirrored into ldnaOverlays.'
        );
        $this->assertGreaterThan(
            $radiiAt,
            $armAt,
            'authority is armed before the stored radius searches have been mirrored into ldnaOverlays.'
        );
    }

    public function test_the_json_schema_is_unchanged(): void
    {
        // The fix must not touch the stored document's shape. All nine canonical keys are
        // still seeded into ldnaState, in the same names the writer and matching read.
        $script = $this->script();

        foreach ([
            'cities:', 'zip_codes:', 'neighborhoods:', 'counties:', 'polygons:',
            'radius_searches:', 'flexible_location:', 'location_notes:', 'state:',
        ] as $key) {
            $this->assertStringContainsString(
                $key,
                $script,
                "canonical Location DNA key `{$key}` disappeared from ldnaState"
            );
        }
    }
}
