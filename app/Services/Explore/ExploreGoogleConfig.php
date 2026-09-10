<?php

namespace App\Services\Explore;

/**
 * Whether the Google Photorealistic 3D renderer can actually start, and the
 * minimum it needs to do so.
 *
 * THREE STATES, NOT TWO
 * ---------------------
 * "Explore is off" and "Explore is on but has no map credential" are different
 * situations and must look different. The first 404s — the feature does not
 * exist. The second renders the shell with a stated unavailable panel: the
 * route is live, the viewport API answers, and the map area says why it is
 * empty. A missing credential producing a blank grey rectangle is the outcome
 * this class exists to prevent, because a dead map is indistinguishable from a
 * broken one and PHP tests cannot see either.
 *
 * A SEPARATE CREDENTIAL FROM GOOGLE_PLACES_API_KEY, PERMANENTLY
 * ------------------------------------------------------------
 * `GOOGLE_PLACES_API_KEY` is a SERVER key used by address validation and POI
 * lookup. It is not referenced here and must never become a fallback: emitting
 * it into a page would publish a server credential to every visitor, and would
 * silently couple a public map to a billable API this surface does not use.
 * The browser key is its own variable and its absence is reported as absence.
 *
 * NO PLACES, NO ROUTES, NO ROADS, NO GEOCODING
 * --------------------------------------------
 * `libraries()` comes from config and contains `maps3d` alone. It is surfaced
 * as a method so a test can assert what the page actually requests rather than
 * inspecting a template. Marker positions come from MLS coordinates; nothing
 * here geocodes, and Google is never allowed to become the source of a
 * property's identity or position.
 */
class ExploreGoogleConfig
{
    /** Is there enough configuration to attempt the 3D renderer? */
    public function isReady(): bool
    {
        return $this->browserKey() !== null;
    }

    /**
     * The browser key, or null.
     *
     * Returned only to the view that must embed it. Never logged, never echoed
     * into a report, never included in a posture message.
     */
    public function browserKey(): ?string
    {
        $key = config('explore.google.browser_key');

        return is_string($key) && trim($key) !== '' ? trim($key) : null;
    }

    /**
     * The Map ID, or null.
     *
     * Optional: Map3DElement renders photorealistic tiles without one. It is
     * read so an operator who has configured a styled map gets it, and its
     * absence is not a readiness failure.
     */
    public function mapId(): ?string
    {
        $id = config('explore.google.map_id');

        return is_string($id) && trim($id) !== '' ? trim($id) : null;
    }

    public function apiVersion(): string
    {
        $version = config('explore.google.api_version', 'alpha');

        return is_string($version) && trim($version) !== '' ? trim($version) : 'alpha';
    }

    /** @return list<string> */
    public function libraries(): array
    {
        $libraries = (array) config('explore.google.libraries', ['maps3d']);

        return array_values(array_filter(
            array_map(static fn ($v) => is_string($v) ? trim($v) : '', $libraries),
            static fn (string $v): bool => $v !== '',
        ));
    }

    /**
     * An operator-facing reason the map is unavailable, or null when it is not.
     *
     * States the missing variable's NAME, never any value. A configuration
     * report says PRESENT or MISSING and nothing else.
     */
    public function unavailableReason(): ?string
    {
        return $this->isReady()
            ? null
            : 'The 3D map is unavailable because EXPLORE_GOOGLE_MAPS_BROWSER_KEY is not configured '
                . 'for this environment. Listings below are unaffected.';
    }
}
