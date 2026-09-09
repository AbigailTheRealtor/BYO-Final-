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
 | NO GLYPHS, NO SPRITE — LOAD-BEARING
 | -----------------------------------
 | The style below declares neither. Both would point at a third-party host, and
 | the whole point of this migration is that the only egress is our own archive.
 | It also means no symbol layer can carry a text label, which is why place pins
 | are DOM markers rather than a symbol layer.
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
 * Minimal Protomaps-schema style around one archive.
 *
 * Fill and line layers only. The colours are a quiet cartographic base chosen so
 * user geometry — which is the point of this map — reads clearly on top of it.
 */
export function buildBasemapStyle(archiveUrl, attribution, maxZoom = 15) {
    return {
        version: 8,
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
        ],
    };
}

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
