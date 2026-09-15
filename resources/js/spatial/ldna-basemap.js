/*
 |-----------------------------------------------------------------------------
 | Location DNA basemap — MapLibre + PMTiles (Phase 1B salvage)
 |-----------------------------------------------------------------------------
 |
 | Derived from the Phase 2A proof on `phase-2-spatial/ui-repair-maplibre-basemap`
 | (b12d06233, a78486b60). Generalised from a standalone proof page into something
 | the Location DNA widget can mount, with every hard-won detail from that work
 | carried across deliberately rather than rediscovered.
 |
 | THE LIBRARY IS INJECTED, NOT IMPORTED
 | -------------------------------------
 | Every function here takes `maplibregl` and `pmtiles` as arguments. That is not
 | ceremony: the production bundle resolves them through webpack (named imports —
 | see the entry point), while the browser test harness loads the UMD builds as
 | globals from a static server. Injection is what lets the SAME renderer code be
 | exercised by a browser test without a build step, which matters a great deal on
 | a subsystem whose last outage was invisible to every non-browser test.
 |
 | GLYPHS ARE OURS; THERE IS STILL NO SPRITE
 | -----------------------------------------
 | This file used to declare neither, with the reasoning that both would point at
 | a third-party host and the only egress may be our own archive. The reasoning
 | was right and the conclusion was too strong: what the rule forbids is a
 | THIRD-PARTY host, not text. `GLYPH_URL` below is served by this application,
 | from `public/fonts`, on the page's own origin — no CDN, no CORS, no
 | protomaps.github.io, and nothing to be unavailable independently of the site
 | itself.
 |
 | THE FONTS, AND WHERE THEY CAME FROM
 |   Noto Sans Regular and Noto Sans Medium, as PBF glyph ranges, taken from
 |   github.com/protomaps/basemaps-assets (fonts/, commit 83bc11ea49e5, the same
 |   assets the Protomaps documentation points a style at) and committed here
 |   instead of linked. Licence: SIL Open Font License 1.1 — the upstream
 |   `fonts/OFL.txt` is committed verbatim as `public/fonts/OFL.txt`, which is
 |   the licence's own condition for redistributing them. Copyright 2022 The
 |   Noto Project Authors. Retrieved 2026-09-10.
 |
 |   Only the Latin ranges US labels request are shipped — 0-255, 256-511 and
 |   8192-8447, about 0.5 MB across both stacks, against 9.4 MB for the complete
 |   256-range set. A range that is not shipped 404s and its glyphs do not draw;
 |   it is not an error and it does not stop the map.
 |
 | There is still NO SPRITE, and no layer below asks for one. A sprite is an
 | icon sheet, the label layers are text-only, and adding one would put a second
 | asset family on the critical path for no gain that house-hunting context
 | needs.
 |
 | Place pins remain DOM markers. That was never only about glyphs — a marker is
 | a clickable element the widget owns, and moving it into a symbol layer would
 | trade that for a rendered picture of one.
 */

/**
 * Where the bundled build loads MapLibre's worker from.
 *
 * MapLibre derives this from `import.meta.url`, which webpack resolves at BUILD
 * time to a `file://` path. That fails MapLibre's own `^https?:` guard, so the
 * worker URL ends up empty, `new Worker('')` resolves to the HTML page — which is
 * not JavaScript — and the worker dies on an error event nobody listens for. The
 * map then paints its background layer and silently never requests a tile.
 *
 * That failure mode is worth stating plainly because it looks like success: a map
 * appears, it is just empty. webpack.mix.js copies the worker asset (and the
 * shared chunk it imports by relative path) beside the bundle.
 */
export const WORKER_URL = '/js/spatial/maplibre-gl-worker.mjs';

/**
 * Register the `pmtiles://` protocol, once.
 *
 * Idempotent and separately callable so a page mounting two maps does not
 * register twice, and so a test can assert registration without booting a map.
 * Without it a `pmtiles://` source URL is an unknown scheme and MapLibre fails
 * before issuing a single range request.
 */
export function registerPmtilesProtocol(maplibregl, pmtiles, { workerUrl = WORKER_URL } = {}) {
    if (registerPmtilesProtocol.registered) {
        return registerPmtilesProtocol.protocol;
    }

    if (typeof maplibregl.setWorkerUrl === 'function' && workerUrl) {
        maplibregl.setWorkerUrl(workerUrl);
    }

    const protocol = new pmtiles.Protocol();

    // `protocol.tile` carries pmtiles' own v3/v4 compatibility wrapper: it detects
    // the AbortController MapLibre v6 passes and returns a promise, which is this
    // version's AddProtocolAction contract. Safe to pass detached — the underlying
    // implementation is arrow-bound to the instance.
    maplibregl.addProtocol('pmtiles', protocol.tile);

    registerPmtilesProtocol.registered = true;
    registerPmtilesProtocol.protocol = protocol;

    return protocol;
}

/** Test seam: forget the registration so a fresh page can register again. */
export function resetPmtilesProtocol() {
    registerPmtilesProtocol.registered = false;
    registerPmtilesProtocol.protocol = undefined;
}

/**
 * Where MapLibre fetches glyph ranges.
 *
 * SAME ORIGIN, ALWAYS. Root-relative rather than absolute so it follows the
 * host the page was served from, and so nothing here has to know what that host
 * is. The two directories it resolves to are committed to the repository.
 *
 * MapLibre URL-encodes `{fontstack}`, so "Noto Sans Regular" is requested as
 * `Noto%20Sans%20Regular` and served from the directory of that literal name.
 */
export const GLYPH_URL = '/fonts/{fontstack}/{range}.pbf';

/** The two stacks shipped in public/fonts. Nothing may name a third. */
export const FONT_REGULAR = ['Noto Sans Regular'];
export const FONT_MEDIUM = ['Noto Sans Medium'];

/** A white casing behind every label, so text stays legible over any fill. */
const HALO = { 'text-halo-color': 'rgba(255,255,255,0.9)', 'text-halo-width': 1.4 };

/**
 * The label layers, in draw order.
 *
 * WHY THE FILTERS USE `kind` AND THE ZOOMS ARE EXPLICIT
 * ----------------------------------------------------
 * The archive also carries a `min_zoom` attribute on places, roads, POIs and
 * water, and it is tempting to drive visibility from it. It is not a map zoom:
 * Tampa — a city that should be labelled from about z6 — carries `min_zoom` 10,
 * and the neighbourhoods around it carry 26, in a z14 tile. Whatever that scale
 * is, reading it as a zoom level would hide every city until z10 and every
 * neighbourhood never. So visibility is decided by `kind`, `kind_detail` and
 * `population_rank`, which are self-describing, plus a `minzoom` this file
 * states outright.
 *
 * Every value below was read from the archive itself rather than assumed —
 * `places.kind` is `locality` or `neighbourhood`, `roads.kind` is `highway`,
 * `major_road`, `minor_road`, `path`, `rail` or `ferry`, and the POI kinds are
 * the ones the tiles actually carry.
 *
 * WHAT IS DELIBERATELY NOT LABELLED
 * ---------------------------------
 *   * house numbers (`buildings.addr_housenumber`) — sparse in OSM and visually
 *     noisy at exactly the zoom a listing is being looked at;
 *   * everyday commercial POIs — `parking` alone is 67 of the 153 POIs in one
 *     downtown tile, and a map buried in parking labels tells a house hunter
 *     less than one with none;
 *   * paths, rail and ferry names, for the same reason.
 *
 * The goal is orientation — which street, which neighbourhood, which town —
 * not a reproduction of a general-purpose map.
 */
function labelLayers() {
    const src = { source: 'protomaps' };

    return [
        // ── water ───────────────────────────────────────────────────────────
        {
            id: 'water-label',
            type: 'symbol',
            ...src,
            'source-layer': 'water',
            minzoom: 10,
            filter: ['all', ['has', 'name'], ['in', ['get', 'kind'], ['literal', ['ocean', 'water', 'river', 'lake', 'bay', 'canal']]]],
            layout: {
                'text-field': ['get', 'name'],
                'text-font': FONT_REGULAR,
                'text-size': ['interpolate', ['linear'], ['zoom'], 10, 10, 15, 13],
                'text-max-width': 8,
            },
            paint: { 'text-color': '#3d6f96', ...HALO },
        },

        // ── roads ───────────────────────────────────────────────────────────
        //
        // Line placement, so a name follows the street rather than sitting in a
        // block beside it. Major roads earn a label well before minor ones; the
        // archive stops at z15 and the map's ceiling matches, so minor street
        // names arrive at z14 rather than being held back to the last zoom
        // available.
        {
            id: 'road-label-major',
            type: 'symbol',
            ...src,
            'source-layer': 'roads',
            minzoom: 11,
            filter: ['all', ['has', 'name'], ['in', ['get', 'kind'], ['literal', ['highway', 'major_road']]]],
            layout: {
                'symbol-placement': 'line',
                'text-field': ['get', 'name'],
                'text-font': FONT_MEDIUM,
                'text-size': ['interpolate', ['linear'], ['zoom'], 11, 10, 15, 12.5],
                'symbol-spacing': 260,
                'text-max-angle': 35,
            },
            paint: { 'text-color': '#4a4438', ...HALO },
        },
        {
            id: 'road-label-minor',
            type: 'symbol',
            ...src,
            'source-layer': 'roads',
            minzoom: 14,
            filter: ['all', ['has', 'name'], ['==', ['get', 'kind'], 'minor_road']],
            layout: {
                'symbol-placement': 'line',
                'text-field': ['get', 'name'],
                'text-font': FONT_REGULAR,
                'text-size': ['interpolate', ['linear'], ['zoom'], 14, 9.5, 15, 11],
                'symbol-spacing': 220,
                'text-max-angle': 35,
            },
            paint: { 'text-color': '#6b6353', ...HALO },
        },
        // Route numbers. `shield_text` is the archive's own rendering of the
        // number where it has one; `ref` is the raw tag. No sprite means no
        // shield graphic, so this is the number itself, boxed only by its halo.
        {
            id: 'road-label-shield',
            type: 'symbol',
            ...src,
            'source-layer': 'roads',
            minzoom: 10,
            filter: ['all', ['==', ['get', 'kind'], 'highway'], ['any', ['has', 'shield_text'], ['has', 'ref']]],
            layout: {
                'symbol-placement': 'line',
                'text-field': ['coalesce', ['get', 'shield_text'], ['get', 'ref']],
                'text-font': FONT_MEDIUM,
                'text-size': 11,
                'symbol-spacing': 320,
            },
            paint: { 'text-color': '#8a5a2b', 'text-halo-color': '#ffffff', 'text-halo-width': 1.8 },
        },

        // ── points of interest ──────────────────────────────────────────────
        //
        // An allow-list, not a filter of exclusions: a POI is labelled because
        // somebody deciding where to live would look for it, and everything not
        // named here stays off the map.
        {
            id: 'poi-label',
            type: 'symbol',
            ...src,
            'source-layer': 'pois',
            minzoom: 14,
            filter: ['all', ['has', 'name'], ['in', ['get', 'kind'], ['literal', [
                'park', 'school', 'university', 'college', 'hospital', 'library',
                'museum', 'stadium', 'marina', 'golf_course', 'playground', 'garden',
                'nature_reserve', 'beach', 'theatre',
            ]]]],
            layout: {
                'text-field': ['get', 'name'],
                'text-font': FONT_REGULAR,
                'text-size': ['interpolate', ['linear'], ['zoom'], 14, 10, 15, 11.5],
                'text-max-width': 9,
                'text-anchor': 'top',
                'text-padding': 4,
            },
            paint: { 'text-color': '#4f6b45', ...HALO },
        },

        // ── places ──────────────────────────────────────────────────────────
        //
        // Three tiers by what the archive says the place IS, and neighbourhoods
        // last so they draw above the town they sit in when both are on screen.
        {
            id: 'place-label-neighbourhood',
            type: 'symbol',
            ...src,
            'source-layer': 'places',
            minzoom: 13,
            filter: ['all', ['has', 'name'], ['==', ['get', 'kind'], 'neighbourhood']],
            layout: {
                'text-field': ['get', 'name'],
                'text-font': FONT_REGULAR,
                'text-size': ['interpolate', ['linear'], ['zoom'], 13, 10, 15, 12],
                'text-letter-spacing': 0.08,
                'text-transform': 'uppercase',
                'text-max-width': 8,
            },
            paint: { 'text-color': '#7a6f5d', ...HALO },
        },
        // Small localities: real places, but there are a great many of them and
        // at a state-wide zoom they would bury the towns. Held to z11.
        {
            id: 'place-label-locality-minor',
            type: 'symbol',
            ...src,
            'source-layer': 'places',
            minzoom: 11,
            filter: ['all',
                ['has', 'name'],
                ['==', ['get', 'kind'], 'locality'],
                ['<', ['coalesce', ['get', 'population_rank'], 0], 11],
            ],
            layout: {
                'text-field': ['get', 'name'],
                'text-font': FONT_REGULAR,
                'text-size': ['interpolate', ['linear'], ['zoom'], 11, 10.5, 15, 13],
                'text-max-width': 9,
            },
            paint: { 'text-color': '#4a4438', ...HALO },
        },
        // Towns and cities: the orientation layer, present from the initial
        // state-wide view onwards and sized by how large the place actually is.
        {
            id: 'place-label-locality',
            type: 'symbol',
            ...src,
            'source-layer': 'places',
            minzoom: 6,
            filter: ['all',
                ['has', 'name'],
                ['==', ['get', 'kind'], 'locality'],
                ['>=', ['coalesce', ['get', 'population_rank'], 0], 11],
            ],
            layout: {
                'text-field': ['get', 'name'],
                'text-font': FONT_MEDIUM,
                'text-size': [
                    'interpolate', ['linear'], ['zoom'],
                    6, ['interpolate', ['linear'], ['coalesce', ['get', 'population_rank'], 11], 11, 10, 24, 15],
                    12, ['interpolate', ['linear'], ['coalesce', ['get', 'population_rank'], 11], 11, 13, 24, 20],
                ],
                'text-max-width': 9,
            },
            paint: { 'text-color': '#332f27', ...HALO },
        },
    ];
}

/**
 * Minimal Protomaps-schema style around one archive.
 *
 * Fill and line layers for the ground, then text on top. The colours are a quiet
 * cartographic base chosen so user geometry — which is the point of this map —
 * reads clearly over it.
 *
 * LABEL LAYERS COME LAST IN THIS ARRAY AND STILL SIT UNDER USER GEOMETRY. The
 * renderer adds its own sources and layers after the style loads, so polygons,
 * radius circles and place rings are appended above these. That is the right
 * order: the user's own shapes are translucent fills, so a street name beneath
 * one is tinted but readable, whereas a shape hidden behind a label would be the
 * thing the map exists to show, obscured by its background. Property and place
 * pins are DOM markers and are above everything regardless.
 *
 * @param {string} archiveUrl  the PMTiles archive
 * @param {string} attribution required by the basemap licence
 * @param {number} maxZoom     the archive's own maximum zoom
 * @param {object} [options]   `glyphs` overrides the glyph URL (tests only);
 *                             `labels: false` builds the pre-label style, which
 *                             is what a caller wants when it must be certain no
 *                             glyph request is issued at all.
 */
export function buildBasemapStyle(archiveUrl, attribution, maxZoom = 15, { glyphs = GLYPH_URL, labels = true } = {}) {
    return {
        version: 8,
        glyphs,
        sources: {
            protomaps: {
                type: 'vector',
                url: `pmtiles://${archiveUrl}`,
                attribution,
                maxzoom: maxZoom,
            },
        },
        layers: [
            { id: 'background', type: 'background', paint: { 'background-color': '#f4f1ea' } },
            { id: 'earth', type: 'fill', source: 'protomaps', 'source-layer': 'earth', paint: { 'fill-color': '#e9e5d9' } },
            { id: 'landuse', type: 'fill', source: 'protomaps', 'source-layer': 'landuse', paint: { 'fill-color': '#dfe6d2', 'fill-opacity': 0.55 } },
            { id: 'water', type: 'fill', source: 'protomaps', 'source-layer': 'water', paint: { 'fill-color': '#a7cbe3' } },
            { id: 'roads', type: 'line', source: 'protomaps', 'source-layer': 'roads', minzoom: 6, paint: { 'line-color': '#ffffff', 'line-width': ['interpolate', ['linear'], ['zoom'], 6, 0.4, 15, 2.5] } },
            { id: 'boundaries', type: 'line', source: 'protomaps', 'source-layer': 'boundaries', paint: { 'line-color': '#9a8f7d', 'line-width': 0.8, 'line-dasharray': [3, 2] } },
            ...(labels ? labelLayers() : []),
        ],
    };
}

/**
 * The source layers the label style reads, and the fonts it names.
 *
 * Exported so a test can compare them against the archive's own metadata and
 * against what is on disk in public/fonts, rather than restating either list in
 * an assertion where it would quietly go stale.
 */
export const LABEL_SOURCE_LAYERS = ['water', 'roads', 'pois', 'places'];
export const LABEL_FONT_STACKS = [FONT_REGULAR[0], FONT_MEDIUM[0]];

/**
 * A style with no tile source at all.
 *
 * Used when no archive is configured. The map still initialises, still accepts
 * geometry, and still lets a user draw and save — it simply has no cartographic
 * backdrop.
 *
 * THIS IS THE POINT, not a consolation prize. The whole regression this work
 * follows from was geometry being destroyed because the map failed to load. A
 * renderer that refuses to initialise without tiles reproduces exactly that
 * hazard; one that initialises without them keeps the editor hydrated and the
 * user's stored shapes safe. The backdrop is the optional part.
 */
export function buildBlankStyle() {
    return {
        version: 8,
        sources: {},
        layers: [
            { id: 'background', type: 'background', paint: { 'background-color': '#eceff2' } },
        ],
    };
}
