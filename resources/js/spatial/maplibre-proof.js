/*
 |-----------------------------------------------------------------------------
 | MapLibre + PMTiles proof-of-render (Phase 2A)
 |-----------------------------------------------------------------------------
 |
 | Isolated proof that the self-hosted Florida PMTiles archive on R2 renders in a
 | browser through MapLibre. Loaded ONLY by the internal proof page. It is not
 | imported by resources/js/app.js and shares no code with the existing Google
 | Maps components.
 |
 | Deliberate constraints, each load-bearing:
 |
 |   * The archive URL arrives from server-rendered config via a data attribute.
 |     It is never written literally in this file.
 |   * No credential is present or required. The archive is fetched credential-
 |     free over its public URL.
 |   * There is NO fallback renderer and NO second tile provider. If the archive
 |     cannot be read, the page shows an explicit error and renders nothing. A
 |     silent fallback would make a broken basemap look like a working one, which
 |     is the exact failure this proof exists to detect.
 |   * No Google Maps, Google Places, Nominatim, geocoder, listing, matching or
 |     database request is made. The only network egress is the PMTiles archive.
 |   * No glyph or sprite URL is configured, so the style carries no symbol layer.
 |     That keeps egress to the archive alone — a glyphs URL would add a request
 |     to a third-party font host.
 |
 | CORS: until the R2 bucket carries an allowed-origin policy, the browser will
 | block the archive request and the error state below is the expected outcome.
 | See docs/spatial/basemap-r2-deployment-2026-07-28.md §5.
 */

// Named imports only. maplibre-gl v6 is ESM and publishes NO default export, so
// `import maplibregl from 'maplibre-gl'` silently yields undefined and every
// `maplibregl.X` below would throw at runtime. The production build reports this
// as a warning rather than an error, so it exits 0 with a broken bundle.
import { Map as MaplibreMap, NavigationControl, ScaleControl, addProtocol, setWorkerUrl } from 'maplibre-gl';
import { Protocol } from 'pmtiles';
import 'maplibre-gl/dist/maplibre-gl.css';
import './maplibre-proof.css';

/**
 * Register the pmtiles:// protocol with MapLibre.
 *
 * Exported and guarded so the registration is idempotent and independently
 * assertable. Without this, a `pmtiles://` source URL is an unknown scheme and
 * MapLibre fails before it ever issues a range request.
 *
 * `protocol.tile` carries pmtiles' own v3/v4 compatibility wrapper: it detects
 * the AbortController that MapLibre v6 passes and returns a promise, which is
 * exactly this version's AddProtocolAction contract. It is safe to pass
 * detached — the underlying implementation is arrow-bound to the instance.
 */
/**
 * Where the bundled build must load MapLibre's worker from.
 *
 * MapLibre derives this from `import.meta.url`, which webpack resolves at build
 * time to a `file://` path. That fails MapLibre's own `^https?:` guard, leaving
 * the worker URL empty — `new Worker('')` then resolves to the HTML page, which
 * is not JavaScript, so the worker dies on an error event nobody listens for.
 * The map paints its background layer and silently never requests a tile.
 *
 * webpack.mix.js copies this asset (and the shared chunk it imports) beside the
 * bundle. Neither file is processed by webpack, so the class-expression defect
 * the resolve.alias works around cannot apply to them.
 */
const WORKER_URL = '/js/spatial/maplibre-gl-worker.mjs';

export function registerPmtilesProtocol() {
    if (registerPmtilesProtocol.registered) {
        return registerPmtilesProtocol.protocol;
    }

    setWorkerUrl(WORKER_URL);

    const protocol = new Protocol();
    addProtocol('pmtiles', protocol.tile);

    registerPmtilesProtocol.registered = true;
    registerPmtilesProtocol.protocol = protocol;

    return protocol;
}

/**
 * Minimal Protomaps-schema style built around the one configured archive.
 *
 * Fill and line layers only. Colours are inert defaults for a proof surface and
 * are not a design decision.
 */
export function buildStyle(archiveUrl, attribution, maxZoom) {
    return {
        version: 8,
        // No `glyphs` and no `sprite` key: both would point at a third-party
        // host, and neither is needed without symbol layers.
        sources: {
            protomaps: {
                type: 'vector',
                url: `pmtiles://${archiveUrl}`,
                attribution,
                maxzoom: maxZoom,
            },
        },
        layers: [
            { id: 'background', type: 'background', paint: { 'background-color': '#f3f0e9' } },
            { id: 'earth', type: 'fill', source: 'protomaps', 'source-layer': 'earth', paint: { 'fill-color': '#e8e4d8' } },
            { id: 'landuse', type: 'fill', source: 'protomaps', 'source-layer': 'landuse', paint: { 'fill-color': '#dfe6d2', 'fill-opacity': 0.6 } },
            { id: 'water', type: 'fill', source: 'protomaps', 'source-layer': 'water', paint: { 'fill-color': '#a3c9e0' } },
            { id: 'roads', type: 'line', source: 'protomaps', 'source-layer': 'roads', minzoom: 6, paint: { 'line-color': '#ffffff', 'line-width': ['interpolate', ['linear'], ['zoom'], 6, 0.4, 15, 2.5] } },
            { id: 'boundaries', type: 'line', source: 'protomaps', 'source-layer': 'boundaries', paint: { 'line-color': '#9a8f7d', 'line-width': 0.8, 'line-dasharray': [3, 2] } },
        ],
    };
}

/**
 * Boot the proof map inside `container`.
 *
 * Reads every input from data attributes written by the Blade view, so the
 * archive URL, attribution and initial view all originate in config/spatial_basemap.php.
 */
export function initMaplibreProof(container) {
    if (!container) {
        return null;
    }

    const statusEl = document.querySelector('[data-maplibre-proof-status]');
    const errorEl = document.querySelector('[data-maplibre-proof-error]');
    const errorMessageEl = document.querySelector('[data-maplibre-proof-error-message]');

    const showError = (message) => {
        if (statusEl) {
            statusEl.hidden = true;
        }
        if (errorMessageEl) {
            errorMessageEl.textContent = message;
        }
        if (errorEl) {
            errorEl.hidden = false;
        }
        // Surfaced for the operator reading the console; there is no retry and
        // no alternative source by design.
        console.error('[maplibre-proof]', message);
    };

    const archiveUrl = container.dataset.pmtilesUrl;

    // Fail closed and loudly. An unconfigured archive is a configuration error,
    // not a reason to reach for some other tile source.
    if (!archiveUrl) {
        showError(
            'No PMTiles archive is configured. Set BASEMAP_R2_PUBLIC_URL and '
            + 'BASEMAP_PMTILES_OBJECT_PATH (or SPATIAL_PMTILES_URL) in .env.'
        );
        return null;
    }

    registerPmtilesProtocol();

    const maxZoom = Number(container.dataset.maxZoom || 15);
    const style = buildStyle(archiveUrl, container.dataset.attribution || '', maxZoom);

    let map;

    try {
        map = new MaplibreMap({
            container,
            style,
            center: [Number(container.dataset.longitude), Number(container.dataset.latitude)],
            zoom: Number(container.dataset.zoom),
            maxZoom,
            attributionControl: { compact: false },
        });
    } catch (error) {
        showError(`MapLibre failed to initialise: ${error && error.message ? error.message : error}`);
        return null;
    }

    map.addControl(new NavigationControl({ showCompass: false }), 'top-right');
    map.addControl(new ScaleControl({ unit: 'imperial' }));

    map.on('load', () => {
        if (statusEl) {
            statusEl.hidden = true;
        }
    });

    // MapLibre reports source/tile failures here rather than by throwing. A CORS
    // rejection on the archive arrives as an opaque network failure, so the
    // message names it as the leading cause without asserting it.
    map.on('error', (event) => {
        const detail = event && event.error && event.error.message
            ? event.error.message
            : 'unknown error';

        showError(
            `Could not load the PMTiles archive: ${detail}. `
            + 'If this is a network or CORS failure, the R2 bucket has no allowed-origin '
            + 'policy for this origin yet — see docs/spatial/basemap-r2-deployment-2026-07-28.md §5.'
        );
    });

    return map;
}

document.addEventListener('DOMContentLoaded', () => {
    initMaplibreProof(document.querySelector('[data-maplibre-proof]'));
});
