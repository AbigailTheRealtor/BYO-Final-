<?php

namespace App\Support\Spatial;

/**
 * The one place the application asks "does this surface render Location DNA with MapLibre?"
 *
 * Phase 2 of the MapLibre migration. Phase 1 (7258703e2) shipped the renderer, the Mix
 * entry, the Playwright suite and `config/spatial_basemap.php` — and **nothing read that
 * config**. `grep -rn "spatial_basemap" --include='*.php'` returned zero hits, so the two
 * flags governed nothing and no Blade template emitted the renderer. This class is the
 * reader that was missing.
 *
 * WHY A CLASS RATHER THAN `config()` CALLS IN BLADE
 * -------------------------------------------------
 * The same mistake the Hire Agent redesign made and then fixed: when views gate themselves,
 * the page body and the shared shell can disagree, and a body renders redesign markup
 * without the stylesheet that lays it out. Here the failure would be worse — the widget
 * WRITES a listing's geography, so a surface that renders the MapLibre container while the
 * host's serialiser still reads Google overlays would serialise an empty geometry set over
 * stored polygons. One reader, one answer, and `enabledFor()` is the only gate.
 *
 * FAIL CLOSED, IN THREE DIRECTIONS
 * --------------------------------
 *   1. A missing or unreadable `config/spatial_basemap.php` reads as OFF. A config that did
 *      not load is indistinguishable from one that requires nothing, and the safe reading of
 *      that ambiguity on a renderer swap is "keep the incumbent".
 *   2. An UNRECOGNISED surface key can never be enabled, even by naming it in the
 *      environment variable. `LOCATION_DNA_MAPLIBRE_SURFACES=create_buyerr` is a typo, and a
 *      typo that silently enables nothing is correct; a typo that silently enables
 *      EVERYTHING is how a rollout becomes an outage.
 *   3. Both gates must agree — the master switch AND the surface allowlist. Two switches in
 *      two variables is what stops one environment edit widening the renderer across all
 *      eight host includes at once.
 *
 * `enabled()` answers the master switch alone and is deliberately NOT a gate; nothing may
 * render from it. It exists so a diagnostic can report the posture honestly.
 *
 * @see config/spatial_basemap.php
 * @see resources/views/partials/location-dna/_maplibre-panel.blade.php
 */
final class LdnaBasemapSurface
{
    /** Buyer "Hire an Agent" Search Areas tab. */
    public const HIRE_BUYER = 'hire_buyer';

    /** Tenant "Hire an Agent" Search Areas tab. */
    public const HIRE_TENANT = 'hire_tenant';

    /** Buyer Create Offer Listing Search Areas tab. */
    public const CREATE_BUYER = 'create_buyer';

    /** Tenant Create Offer Listing Search Areas tab. */
    public const CREATE_TENANT = 'create_tenant';

    /** Legacy buyer_criteria add/edit forms. */
    public const BUYER_CRITERIA = 'buyer_criteria';

    /** Legacy tenant_criteria add/edit forms. */
    public const TENANT_CRITERIA = 'tenant_criteria';

    /**
     * The read-only display component, on every listing detail page.
     *
     * One key for all four roles on purpose. What differs between them is the CONTENT
     * (Buyer/Tenant carry search geometry, Seller/Landlord carry one property pin), not
     * whether the renderer is available — and a per-role split here would invite somebody
     * to "enable the map for sellers", which is not a thing that can be true while it is
     * false for the buyer looking at the same page.
     */
    public const DISPLAY = 'display';

    /**
     * Every recognised surface. An environment variable naming anything else is ignored.
     *
     * These match the host keys the widget's own includes pass, and the list in
     * config/spatial_basemap.php's docblock. Kept as a const rather than derived from
     * config so the config cannot widen its own allowlist.
     */
    public const SURFACES = [
        self::HIRE_BUYER,
        self::HIRE_TENANT,
        self::CREATE_BUYER,
        self::CREATE_TENANT,
        self::BUYER_CRITERIA,
        self::TENANT_CRITERIA,
        self::DISPLAY,
    ];

    /** Where the Mix build publishes the renderer bundle. */
    public const BUNDLE_PATH = '/js/spatial/ldna-maplibre.js';

    /**
     * Does this surface render with MapLibre right now?
     *
     * The only gate. Both the master switch and the surface allowlist must agree, and an
     * unrecognised surface is refused before either is consulted.
     */
    public static function enabledFor(?string $surface): bool
    {
        if ($surface === null || ! in_array($surface, self::SURFACES, true)) {
            return false;
        }

        if (! self::enabled()) {
            return false;
        }

        return in_array($surface, self::surfaces(), true);
    }

    /**
     * The master switch alone.
     *
     * NOT a gate — no view may render from this. With the master switch on and no surface
     * named, the correct behaviour is "no change anywhere", and only `enabledFor()` knows
     * that.
     */
    public static function enabled(): bool
    {
        return (bool) self::conf('maplibre_renderer_enabled', false);
    }

    /**
     * The surfaces named in the environment, narrowed to the ones that exist.
     *
     * The intersection is what makes an unrecognised key inert rather than load-bearing.
     */
    public static function surfaces(): array
    {
        $configured = self::conf('maplibre_renderer_surfaces', []);

        if (! is_array($configured)) {
            return [];
        }

        return array_values(array_intersect(
            array_map(static fn ($s) => is_string($s) ? trim($s) : '', $configured),
            self::SURFACES
        ));
    }

    /**
     * The PMTiles archive URL, or null when none is configured.
     *
     * A NULL IS A SUPPORTED STATE. The renderer initialises without a backdrop and keeps
     * geometry editable — see the reasoning in config/spatial_basemap.php. A renderer that
     * refuses to start without tiles reproduces the exact hazard this workstream closes.
     */
    public static function pmtilesUrl(): ?string
    {
        $url = self::conf('pmtiles_url');

        return is_string($url) && trim($url) !== '' ? trim($url) : null;
    }

    /** True when an archive is configured. Says nothing about whether the browser can read it. */
    public static function hasBasemapArchive(): bool
    {
        return self::pmtilesUrl() !== null;
    }

    /**
     * Attribution for the basemap.
     *
     * Required, not decorative: OpenStreetMap under ODbL plus a Protomaps-built archive is a
     * Produced Work and attribution is the licence condition for displaying it. Falls back to
     * the OSM/Protomaps line rather than to an empty string, because an unreadable config
     * must not silently strip a licence notice.
     */
    public static function attribution(): string
    {
        $attribution = self::conf('attribution');

        if (is_string($attribution) && trim($attribution) !== '') {
            return trim($attribution);
        }

        return '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors '
            . '· <a href="https://protomaps.com">Protomaps</a>';
    }

    /**
     * The data attributes the panel partial writes onto the container.
     *
     * Every value the browser receives originates here, so no Blade file writes a coordinate,
     * a zoom or an archive URL literally. Values are plain scalars — the container is public
     * markup and no credential may pass through it (the archive is public and read-only).
     */
    public static function containerAttributes(): array
    {
        $view = self::conf('initial_view', []);
        $view = is_array($view) ? $view : [];

        return [
            'pmtiles-url'  => self::pmtilesUrl() ?? '',
            'attribution'  => self::attribution(),
            'longitude'    => (string) ($view['longitude'] ?? -83.804601),
            'latitude'     => (string) ($view['latitude'] ?? 27.698638),
            'zoom'         => (string) ($view['zoom'] ?? 6),
            'max-zoom'     => (string) (self::conf('max_zoom', 15) ?: 15),
        ];
    }

    /**
     * The URL for the renderer bundle, cache-busted when the Mix manifest can supply it.
     *
     * `mix()` is preferred because the bundle is a versioned build artefact and a browser
     * holding yesterday's copy after a deploy is a real failure. But `mix()` THROWS when the
     * manifest is missing or lacks the entry — on a listing page that is a 500, and a
     * missing build artefact must never take a listing down. So the fallback is `asset()`,
     * whose worst case is a 404 on one script: the container renders, reports its empty
     * state, and every other thing on the page still works.
     */
    public static function bundleUrl(): string
    {
        try {
            return mix(self::BUNDLE_PATH);
        } catch (\Throwable $e) {
            return asset(ltrim(self::BUNDLE_PATH, '/'));
        }
    }

    /**
     * Read one key, treating an absent config file as an absent value.
     *
     * `config('spatial_basemap.x')` on an unpublished or unreadable file returns null, which
     * `enabled()` casts to false and `surfaces()` reads as []. That is the fail-closed
     * behaviour; this wrapper exists so it is stated once rather than assumed at six call
     * sites.
     */
    private static function conf(string $key, $default = null)
    {
        return config('spatial_basemap.' . $key, $default);
    }
}
