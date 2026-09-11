<?php

namespace App\Services\Explore;

/**
 * Whether the Google Photorealistic 3D renderer can actually start, and the
 * minimum it needs to do so.
 *
 * THE STATES MUST LOOK DIFFERENT
 * ------------------------------
 * "Explore is off" and "Explore is on but the map cannot start" are different
 * situations and must look different. The first 404s — the feature does not
 * exist. The second renders the shell with a stated unavailable panel: the
 * route is live, the viewport API answers, and the map area says why it is
 * empty — switched off, or no credential, or both. A blank grey rectangle is
 * the outcome this class exists to prevent, because a dead map is
 * indistinguishable from a broken one and PHP tests cannot see either.
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
    /**
     * May the 3D renderer be started at all?
     *
     * TWO INDEPENDENT REQUIREMENTS, on top of EXPLORE_ENABLED (which the route
     * middleware enforces before this is ever asked): the switch must be
     * explicitly ON, and a browser key must be configured. A key alone is not
     * enough — it can be provisioned and verified ahead of a launch without
     * anything loading.
     *
     * When this is false the loader NEVER RUNS — no <script> is inserted and
     * maps.googleapis.com is never contacted. Not "load it and hide the map":
     * an expensive provider must be untouched when it is switched off, or the
     * switch protects nothing.
     */
    public function isReady(): bool
    {
        return $this->enabled() && $this->browserKey() !== null;
    }

    /**
     * Is the 3D renderer explicitly switched on?
     *
     * DEFAULT OFF, AND ONLY AN EXPLICIT ON COUNTS. config/explore.php parses
     * EXPLORE_GOOGLE_3D_ENABLED strictly — `true`, `1`, `on` or `yes`, nothing
     * else — and this re-asserts it for a value set any other way: only a real
     * boolean true enables.
     *
     * Separate from EXPLORE_ENABLED so Explore can keep serving listings while
     * Google is stopped — a billing incident, a quota exhaustion, an
     * unexplained usage spike — without deleting the browser key, which is a
     * secret change rather than an operational one and leaves no record of the
     * decision.
     */
    public function enabled(): bool
    {
        return config('explore.google.enabled', false) === true;
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
     * States the missing variables' NAMES, never any value. A configuration
     * report says PRESENT or MISSING and nothing else. When both requirements
     * are unmet it names both, so switching the renderer on does not reveal a
     * second, previously unmentioned blocker.
     */
    public function unavailableReason(): ?string
    {
        if ($this->isReady()) {
            return null;
        }

        $missingKey = $this->browserKey() === null;

        if (! $this->enabled()) {
            return 'The 3D map is currently switched off for this environment (EXPLORE_GOOGLE_3D_ENABLED)'
                . ($missingKey ? ' and EXPLORE_GOOGLE_MAPS_BROWSER_KEY is not configured' : '')
                . '. Listings below are unaffected.';
        }

        return 'The 3D map is unavailable because EXPLORE_GOOGLE_MAPS_BROWSER_KEY is not configured '
            . 'for this environment. Listings below are unaffected.';
    }
}
