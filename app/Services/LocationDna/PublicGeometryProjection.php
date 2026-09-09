<?php

namespace App\Services\LocationDna;

/**
 * PublicGeometryProjection — public Location DNA geometry containment.
 *
 * Purpose: remove sensitive, user-authored location detail from a decoded
 * `location_dna_preferences` array BEFORE it is handed to a public view.
 *
 * WHY THIS EXISTS
 * ---------------
 * Four Buyer/Tenant viewer routes render the shared
 * `components/location-dna-map.blade.php`, which serialises `polygons` and
 * `radius_searches` into page JavaScript (`@json(...)`) and prints
 * `location_notes` as visible text. All four carry middleware ["web"] only, and
 * two of them perform no approval or authorisation check at all. The exact
 * vertices of a buyer's drawn search area, the street address at the centre of
 * a radius search, and free-text notes ("close to my mother on Elm Street")
 * were therefore readable from page source by anyone with the URL.
 *
 * THE AUDIENCE RULE
 * -----------------
 * These routes are the same page for everyone, so this projection does NOT vary
 * by session, user or login state — there is no parameter by which it could.
 * Authentication is not authorization: a logged-in stranger is still the public.
 * The owner obtains exact geometry through the dedicated private editor
 * (`partials/location-dna/map-input.blade.php`), which this class does not touch.
 *
 * This service MUST NEVER:
 *   - produce a value that is persisted, merged, or written anywhere;
 *   - be applied to the array passed to the enrichment services (boundary,
 *     flood zone, school district, intelligence composer) — those legitimately
 *     require full geometry and run server-side only, never reaching the browser;
 *   - inspect, truncate, summarise or heuristically sanitise free text;
 *   - vary its output based on the session, the current user, or login state;
 *   - make any database, network, cache or provider call.
 *
 * It is pure, stateless, deterministic and idempotent.
 *
 * IMPORTANT — this returns a PRESENTATION PROJECTION, not canonical state.
 * A stored dimension that is present-but-empty means "intentionally cleared".
 * The empty arrays this projection emits carry NO such meaning — they are a
 * display artefact of withholding. The MARKER key exists so a reader can tell
 * the difference, and so any future writer handed this array can refuse it
 * outright. Never feed this output back into stored state.
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
     * character as `location_notes`. Emitting a per-entry remnant would also
     * leave the renderer with a centre-less circle to draw.
     */
    private const WITHHELD_GEOMETRY_KEYS = ['polygons', 'radius_searches'];

    /** Free-text dimensions withheld on public surfaces. */
    private const WITHHELD_TEXT_KEYS = ['location_notes'];

    /** The meta key holding the blob. */
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
     * Remove the blob from a generic meta bag before it reaches a public view.
     *
     * WHY THIS EXISTS — a decoded meta bag is a second, independent route to the
     * browser that never names the geometry keys. The tenant offer-listing view
     * carries an "Additional Information" section that renders meta keys it has
     * no named section for, json_encoding array values straight into the page.
     * That section is allowlist-driven today and does not include this key, so
     * the blob does not reach it — but the allowlist is a different file with a
     * different owner, and the map component is fed the projected array
     * separately, so nothing is lost by removing the raw blob outright here.
     *
     * Projecting the value instead would still print the projection's
     * scaffolding as a stray "Location Dna Preferences" row. No view reads
     * `$meta['location_dna_preferences']`.
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
