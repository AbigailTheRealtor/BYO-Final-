/*
 |-----------------------------------------------------------------------------
 | Location DNA MapLibre entry point (Phase 1)
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

/** Reassembled namespaces, shaped exactly as the renderer expects to receive them. */
const maplibregl = { Map: MaplibreMap, Marker, NavigationControl, ScaleControl, addProtocol, setWorkerUrl };
const pmtiles = { Protocol };

/**
 * Mount the renderer on a container carrying `data-ldna-maplibre`.
 *
 * Configuration arrives through data attributes written by Blade, so every value
 * originates in config/spatial_basemap.php and none is written literally here.
 *
 * LAZY BY DEFAULT. The widget lives inside inactive tab panes on all eight host
 * surfaces, and a map measured while its container is hidden gets a zero-sized
 * canvas that never recovers. An IntersectionObserver defers `init()` until the
 * container has real dimensions, and a resize follows the reveal.
 */
export function mountLdnaMaplibre(container) {
    if (!container || container.dataset.ldnaMaplibreMounted === '1') {
        return null;
    }
    container.dataset.ldnaMaplibreMounted = '1';

    const statusEl = document.querySelector('[data-ldna-map-status]');

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
        onStatus: (state, message) => {
            container.setAttribute('data-ldna-map-state', state);
            if (statusEl) {
                statusEl.textContent = message || '';
                statusEl.hidden = state === 'ready';
            }
        },
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
    // phase — can hydrate and read back without importing anything.
    window.ldnaMaplibreRenderer = renderer;

    return renderer;
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-ldna-maplibre]').forEach(mountLdnaMaplibre);
});

export { createLdnaRenderer };
