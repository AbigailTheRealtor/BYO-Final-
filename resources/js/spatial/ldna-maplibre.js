/*
 |-----------------------------------------------------------------------------
 | Location DNA MapLibre entry point (Phase 1 renderer, Phase 2 host wiring)
 |-----------------------------------------------------------------------------
 |
 | The production bundle's entry. A SEPARATE Mix entry from app.js on purpose:
 | MapLibre is a large dependency and this renderer is behind a flag that ships
 | off, so none of it may reach the bundle every consumer page already loads.
 |
 | NAMED IMPORTS ONLY. maplibre-gl v6 is ESM and publishes NO default export, so
 | `import maplibregl from 'maplibre-gl'` silently yields undefined and every
 | `maplibregl.X` throws at runtime. The production build reports that as a
 | warning rather than an error, so it exits 0 with a broken bundle — which is
 | why this is stated here rather than left to be rediscovered.
 |
 | This file is the ONLY place that binds the renderer to a real library. The
 | renderer itself takes both namespaces as arguments, which is what lets the
 | browser suite exercise the identical code against UMD globals with no build
 | step. See resources/js/spatial/ldna-basemap.js.
 */

import {
    Map as MaplibreMap,
    Marker,
    NavigationControl,
    ScaleControl,
    addProtocol,
    setWorkerUrl,
} from 'maplibre-gl';
import { Protocol } from 'pmtiles';
import 'maplibre-gl/dist/maplibre-gl.css';

import { createLdnaRenderer } from './ldna-maplibre-renderer.js';
import { lookupAddress, lookupWithButton } from './ldna-address-lookup.js';

/** Reassembled namespaces, shaped exactly as the renderer expects to receive them. */
const maplibregl = { Map: MaplibreMap, Marker, NavigationControl, ScaleControl, addProtocol, setWorkerUrl };
const pmtiles = { Protocol };

/** Parse a data attribute that carries JSON, treating anything malformed as absent. */
function readJson(container, key, fallback) {
    const raw = container.dataset[key];

    if (!raw) {
        return fallback;
    }

    try {
        const parsed = JSON.parse(raw);
        return parsed === null || parsed === undefined ? fallback : parsed;
    } catch (error) {
        // A malformed blob must not take the map down with it. The renderer still
        // initialises and the user can still draw; what it must never do is guess.
        return fallback;
    }
}

/**
 * Mount the renderer on a container carrying `data-ldna-maplibre`.
 *
 * Configuration arrives through data attributes written by Blade, so every value
 * originates in config/spatial_basemap.php (via App\Support\Spatial\LdnaBasemapSurface)
 * and none is written literally here.
 *
 * LAZY BY DEFAULT. The widget lives inside inactive tab panes on all eight host
 * surfaces, and a map measured while its container is hidden gets a zero-sized
 * canvas that never recovers. An IntersectionObserver defers `init()` until the
 * container has real dimensions, and a resize follows the reveal.
 */
export function mountLdnaMaplibre(container) {
    if (!container || container.dataset.ldnaMaplibreMounted === '1') {
        return container ? container._ldnaRenderer || null : null;
    }
    container.dataset.ldnaMaplibreMounted = '1';

    // SCOPED, not page-global. Two panels can share a page — a Buyer detail view
    // renders the display surface while nothing else does today, but a page that
    // grew a second map would otherwise have both renderers writing their status
    // into whichever box the document happened to list first.
    const statusEl = (container.closest('.ldna-maplibre-wrap') || document)
        .querySelector('[data-ldna-map-status]');

    const mode = container.dataset.ldnaMode === 'edit' ? 'edit' : 'display';
    const state = readJson(container, 'ldnaState', {});
    const propertyPin = readJson(container, 'ldnaPropertyPin', null);
    const boundaries = readJson(container, 'ldnaBoundaries', null);
    const shouldFit = container.dataset.ldnaFit === '1';

    const renderer = createLdnaRenderer({
        maplibregl,
        pmtiles,
        container,
        config: {
            pmtilesUrl: container.dataset.pmtilesUrl || '',
            attribution: container.dataset.attribution || '',
            longitude: container.dataset.longitude,
            latitude: container.dataset.latitude,
            zoom: container.dataset.zoom,
            maxZoom: container.dataset.maxZoom,
        },
        onStatus: (state_, message) => {
            container.setAttribute('data-ldna-map-state', state_);
            if (statusEl) {
                statusEl.textContent = message || '';
                statusEl.hidden = state_ === 'ready';
            }
        },
        /*
         * Report every geometry edit to the host's own serialiser.
         *
         * The host owns storage; this only tells it something changed. `ldnaSerialize`
         * reads the renderer back through `getState()` rather than taking this payload,
         * so there is exactly one path from renderer to stored blob and no chance of the
         * two disagreeing. Display-mode panels never edit, so they never wire this.
         */
        onChange: mode === 'edit'
            ? () => {
                if (typeof window.ldnaSerialize === 'function') {
                    window.ldnaSerialize();
                }
            }
            : undefined,
        /*
         * Fires once the geometry layers are on the map, whether or not the backdrop
         * loaded. Fitting here rather than on 'ready' is deliberate: with the basemap
         * archive unreachable the renderer reports 'degraded' and never 'ready', and a
         * listing's saved polygons must still be framed on the screen.
         */
        onReady: () => {
            if (boundaries) {
                renderer.setBoundary('display', boundaries);
            }
            if (propertyPin) {
                renderer.setPropertyPin(propertyPin);
            }

            /* Fit to the user's OWN geometry when there is any, and only fall back to the
               boundary extent when there is not. A listing whose tier is a city outline has
               nothing else to frame; one that carries polygons wants those framed, not the
               county they happen to sit in. */
            if (shouldFit) {
                renderer.fitToGeometry();
            } else if (boundaries) {
                renderer.fitToBoundary('display');
            }
        },
    });

    // HYDRATE BEFORE INIT, ALWAYS.
    //
    // hydrate() adopts stored geometry into the working set and only then unlocks
    // change reporting; every refresh it triggers is a no-op until a map exists, and
    // the style-load paint draws the adopted geometry. Doing it the other way round
    // opens exactly the window the PR #124 contract exists to close: a map that is
    // alive and empty, whose first serialise writes `"polygons":[]` over stored shapes.
    renderer.hydrate({
        polygons: state.polygons || [],
        radius_searches: state.radius_searches || [],
        important_places: state.important_places || [],
    });

    const boot = () => {
        renderer.init();
        // Two frames after reveal: MapLibre reads the container's box during
        // init, and a tab transition may still be interpolating height.
        requestAnimationFrame(() => requestAnimationFrame(() => renderer.resize()));
    };

    if (container.offsetParent !== null && container.clientHeight > 0) {
        boot();
    } else if (typeof IntersectionObserver === 'function') {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting && entry.boundingClientRect.height > 0) {
                    observer.disconnect();
                    boot();
                }
            });
        });
        observer.observe(container);
    } else {
        boot();
    }

    // Exposed so the host widget — still the incumbent Blade partial in this
    // phase — can hydrate and read back without importing anything. Also kept on
    // the element, because `window.ldnaMaplibreRenderer` names only the last one
    // mounted and a second panel would otherwise silently steal the reference.
    container._ldnaRenderer = renderer;
    window.ldnaMaplibreRenderer = renderer;

    return renderer;
}

/**
 * Mount every unmounted panel on the page. Idempotent.
 *
 * Exposed as `window.ldnaMaplibreMount` so a host can re-scan after a Livewire
 * morphdom update introduces a panel that was not in the initial document.
 */
export function mountAllLdnaMaplibre() {
    const mounted = [];
    document.querySelectorAll('[data-ldna-maplibre]').forEach((el) => {
        const renderer = mountLdnaMaplibre(el);
        if (renderer) {
            mounted.push(renderer);
        }
    });
    return mounted;
}

/*
 * READY-STATE AWARE, NOT `DOMContentLoaded`-ONLY.
 *
 * A bundle appended to the document after DOMContentLoaded has already fired — a
 * dynamic injector, a `defer` script racing a cached parse, a Livewire-driven
 * insertion — would never see that event, and the failure is indistinguishable
 * from a dead map. Checking readyState first costs nothing and removes the class.
 */
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountAllLdnaMaplibre);
} else {
    mountAllLdnaMaplibre();
}

window.ldnaMaplibreMount = mountAllLdnaMaplibre;

/*
 * ADDRESS LOOKUP, EXPOSED FOR THE HOST WIDGET.
 *
 * The host is still the incumbent Blade partial — inline, non-module script — so
 * it cannot import anything. It reaches the lookup the same way it reaches the
 * renderer: through a global this entry point publishes.
 *
 * Attached here rather than in ldna-address-lookup.js so that module stays pure
 * and importable by a test with no page, and so there is exactly one file that
 * decides what this bundle puts on `window`.
 *
 * It is published unconditionally alongside the renderer, which is what makes
 * "MapLibre is on for this surface" and "typed addresses resolve on this
 * surface" the same fact rather than two flags that can disagree.
 */
window.ldnaAddressLookup = lookupAddress;
window.ldnaAddressLookupWithButton = lookupWithButton;

export { createLdnaRenderer };
