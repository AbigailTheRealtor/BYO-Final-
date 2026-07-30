<?php

namespace App\Services\LocationDna;

/**
 * PublicGeometryProjection — G0.1 Public Geometry Containment
 *
 * GOVERNANCE BLOCK
 * ==================================================================================
 * Gate:      G0.1  — docs/architecture/LOCATION-DNA-ENGINE-V1.2-G0.1-PLAN.md
 * Governed:  docs/architecture/LOCATION-DNA-ENGINE-V1.2.md
 *            principles L1, L2, L5, L7, L14
 * Decisions: D4 — public viewer routes must never receive exact user-authored
 *                 search geometry, regardless of whether the visitor is
 *                 authenticated. Authentication alone is not authorization.
 *            D5 — `location_notes` is potentially sensitive free text and is not
 *                 emitted on public surfaces; it is not truncated or sanitised.
 *
 * Purpose: remove sensitive, user-authored location detail from a decoded
 * `location_dna_preferences` array BEFORE it is handed to a public view.
 *
 * This service MUST NEVER:
 *   - produce a value that is persisted, merged, or written anywhere;
 *   - be applied to the array passed to the enrichment services (boundary,
 *     flood zone, school district, intelligence composer) — those legitimately
 *     require full geometry and run server-side only, never reaching the browser;
 *   - inspect, truncate, summarise or heuristically sanitise free text (D5);
 *   - vary its output based on the session, the current user, or login state (D4);
 *   - make any database, network, cache or provider call.
 *
 * It is pure, stateless, deterministic and idempotent.
 *
 * ---------------------------------------------------------------------------------
 * IMPORTANT — this returns a PRESENTATION PROJECTION, not canonical state.
 *
 * Under v1.2 §5.2 a canonical dimension that is present-but-empty means
 * "intentionally cleared". The empty arrays this projection emits carry NO such
 * meaning — they are a display artefact of withholding. The MARKER key exists so a
 * reader can tell the difference, and so any future writer handed this array can
 * refuse it outright. Never feed this output back into canonical state.
 * ==================================================================================
 */
class PublicGeometryProjection
{
    /** Set on any array that has passed through this projection. */
    public const MARKER = '__public_view_projection';

    /** True when exact search geometry existed and was withheld. */
    public const WITHHELD_GEOMETRY = '__withheld_search_geometry';

    /** True when location notes existed and were withheld. */
    public const WITHHELD_NOTES = '__withheld_location_notes';

    /**
     * Dimensions withheld wholesale on public surfaces.
     *
     * Withheld in full rather than partially redacted: `polygons[].path` and
     * `radius_searches[].lat/lng` are the exact geometry, and the sibling
     * `label` / `address` fields are user-authored free text of the same
     * character as `location_notes` (D5). Emitting a per-entry remnant would
     * also leave the renderer with a centre-less circle to draw.
     */
    private const WITHHELD_GEOMETRY_KEYS = ['polygons', 'radius_searches'];

    /** Free-text dimensions withheld on public surfaces (D5). */
    private const WITHHELD_TEXT_KEYS = ['location_notes'];

    /** The canonical meta key holding the blob. */
    public const CANONICAL_META_KEY = 'location_dna_preferences';

    /**
     * Project a decoded preferences array for a public surface.
     *
     * @param  array|null  $preferences  Decoded `location_dna_preferences`, or null.
     * @return array|null               Projected copy, or null when given null.
     */
    public function project(?array $preferences): ?array
    {
        if ($preferences === null) {
            return null;
        }

        // Idempotent: re-projecting must not clear the withheld-flags by
        // re-measuring an already-emptied array.
        if (! empty($preferences[self::MARKER])) {
            return $preferences;
        }

        $withheldGeometry = false;
        foreach (self::WITHHELD_GEOMETRY_KEYS as $key) {
            $value = $preferences[$key] ?? null;
            if (is_array($value) && count($value) > 0) {
                $withheldGeometry = true;
                break;
            }
        }

        $withheldNotes = false;
        foreach (self::WITHHELD_TEXT_KEYS as $key) {
            $value = $preferences[$key] ?? null;
            if (is_string($value) ? trim($value) !== '' : ! empty($value)) {
                $withheldNotes = true;
                break;
            }
        }

        // Copy-on-write: the caller's array is never mutated.
        $projected = $preferences;

        foreach (self::WITHHELD_GEOMETRY_KEYS as $key) {
            if (array_key_exists($key, $projected)) {
                $projected[$key] = [];
            }
        }

        foreach (self::WITHHELD_TEXT_KEYS as $key) {
            if (array_key_exists($key, $projected)) {
                $projected[$key] = '';
            }
        }

        $projected[self::MARKER]            = true;
        $projected[self::WITHHELD_GEOMETRY] = $withheldGeometry;
        $projected[self::WITHHELD_NOTES]    = $withheldNotes;

        return $projected;
    }

    /**
     * Remove the canonical blob from a generic meta bag before it reaches a
     * public view.
     *
     * WHY THIS EXISTS — a decoded meta bag is a second, independent route to the
     * browser that never names the geometry keys. `offer-listing/tenant/view`
     * carries an "Additional Information" section that iterates EVERY meta key
     * it does not explicitly know about and json_encodes array values straight
     * into the page. That printed the entire raw blob — vertices, radius
     * centres, addresses and notes — on a public page, and it would do the same
     * for any sensitive meta key added in future.
     *
     * Projecting the value would still print the projection's scaffolding as a
     * stray "Location Dna Preferences" row, so the blob is removed outright.
     * No view reads `$meta['location_dna_preferences']`; the map component is
     * fed the projected array separately.
     *
     * @param  array<string,mixed>  $meta
     * @return array<string,mixed>
     */
    public function stripFromMetaBag(array $meta): array
    {
        unset($meta[self::CANONICAL_META_KEY]);

        return $meta;
    }
}
