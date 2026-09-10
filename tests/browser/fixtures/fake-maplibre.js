/*
 |-----------------------------------------------------------------------------
 | A stand-in for the maplibre-gl namespace
 |-----------------------------------------------------------------------------
 |
 | WHAT THIS IS, AND WHAT IT IS NOT
 | --------------------------------
 | This replaces the THIRD-PARTY LIBRARY, never the code under test. Every spec
 | that uses it drives the real `createLdnaRenderer` from the real
 | resources/js/spatial source; only MapLibre itself is substituted. That is the
 | seam the renderer was built around — see the injection note in ldna-basemap.js.
 |
 | WHY IT IS NEEDED AT ALL
 | -----------------------
 | MapLibre requires WebGL2. This container's Chromium exposes no WebGL at all
 | (SwiftShader is present but never initialises), so a real Map cannot be
 | constructed here. Rather than let that silence the entire suite, the renderer's
 | LOGIC — hydration gating, source contents, draw/edit/delete, marker lifecycle —
 | is exercised against this, and the handful of assertions that genuinely need
 | pixels live in renderer-gl.spec.js and skip when WebGL is absent.
 |
 | IT MODELS ONLY WHAT THE RENDERER USES, and it models it faithfully: a source
 | remembers the last data set on it, `on('load')` fires asynchronously exactly as
 | MapLibre's does, and layer/source registration is order-sensitive in the same
 | way — `addLayer` before `addSource` throws here too, so a real ordering bug in
 | the renderer would surface rather than be absorbed.
 */

class FakeSource {
    constructor(data) {
        this._data = data || { type: 'FeatureCollection', features: [] };
    }

    setData(data) {
        this._data = data;
    }

    /**
     * MapLibre's PUBLIC accessor, and the only way the harness reads a source.
     *
     * Real MapLibre does not keep the collection bare on `_data` — it keeps a
     * tagged wrapper and hands the GeoJSON back through this async method. The
     * fake once modelled only the private field, so the harness read a shape
     * that existed here and nowhere else: fake-backed specs agreed with every
     * assertion while the real library silently reported zero features. Modelling
     * the public method instead is what keeps the two libraries answering the
     * same question.
     */
    async getData() {
        return this._data;
    }
}

class FakeMap {
    constructor(options) {
        this.options = options;
        this.container = options.container;
        this._sources = new Map();
        this._layers = new Map();
        this._handlers = [];
        this.resizeCount = 0;
        this.removed = false;
        this.fitted = null;
        this.dragPan = { enabled: true, enable() { this.enabled = true; }, disable() { this.enabled = false; } };

        this._canvas = document.createElement('canvas');
        this._canvas.width = 640;
        this._canvas.height = 360;
        if (this.container && this.container.appendChild) {
            this.container.appendChild(this._canvas);
        }

        // MapLibre fires `load` on a later task, never synchronously. The renderer
        // adds its sources and layers in that handler, so firing it synchronously
        // here would hide ordering mistakes that bite in production.
        // BOTH, in the real library's order. Real MapLibre fires `style.load` once the
        // style object is in place and `load` once the map is fully loaded, and the
        // renderer binds its paint to both — `style.load` because it does not wait for a
        // source to resolve, which is the whole point when the basemap archive is
        // unreachable. A double that fired only `load` would let a renderer that depends
        // on `style.load` pass here and paint nothing in a browser.
        setTimeout(() => {
            this._fire('style.load', {});
            this._fire('load', {});
        }, 0);
    }

    addSource(id, spec) {
        if (this._sources.has(id)) {
            throw new Error(`source ${id} already exists`);
        }
        this._sources.set(id, new FakeSource(spec.data));
    }

    getSource(id) {
        return this._sources.get(id);
    }

    addLayer(spec) {
        if (spec.source && !this._sources.has(spec.source)) {
            throw new Error(`layer ${spec.id} references missing source ${spec.source}`);
        }
        this._layers.set(spec.id, spec);
    }

    getLayer(id) {
        return this._layers.get(id);
    }

    /** `on(type, fn)` and `on(type, layerId, fn)`, as MapLibre overloads it. */
    on(type, layerOrFn, maybeFn) {
        const layer = typeof layerOrFn === 'string' ? layerOrFn : null;
        const fn = typeof layerOrFn === 'function' ? layerOrFn : maybeFn;
        this._handlers.push({ type, layer, fn });
        return this;
    }

    off(type, layerOrFn, maybeFn) {
        const fn = typeof layerOrFn === 'function' ? layerOrFn : maybeFn;
        this._handlers = this._handlers.filter((h) => !(h.type === type && h.fn === fn));
        return this;
    }

    _fire(type, event, layer = null) {
        this._handlers
            .filter((h) => h.type === type && h.layer === layer)
            .forEach((h) => h.fn(event));
    }

    getCanvas() {
        return this._canvas;
    }

    resize() {
        this.resizeCount += 1;
    }

    fitBounds(bounds, opts) {
        this.fitted = { bounds, opts };
    }

    /**
     * Centre at an explicit zoom.
     *
     * The renderer reaches for this when the extent it computed is DEGENERATE — a single
     * property pin has zero area, and `fitBounds` on a zero-area box snaps to maxZoom,
     * which is the right answer only by accident.
     */
    jumpTo(opts) {
        this.jumped = opts;
    }

    /**
     * Swap the style, as MapLibre does — DISCARDING every source and layer the old one
     * carried.
     *
     * Modelling the discard is the whole reason this exists. The renderer swaps to the
     * blank style when the basemap archive cannot be read, and it repaints afterwards
     * because it knows the sources are gone. A double that kept them would let the
     * renderer's `addSource` throw "source already exists" in a real browser while
     * passing here, which is the exact shape of bug this suite exists to catch.
     */
    setStyle(style) {
        this.style = style;
        this.styleSwaps = (this.styleSwaps || 0) + 1;
        this._sources = new Map();
        this._layers = new Map();
        setTimeout(() => this._fire('style.load', {}), 0);
        return this;
    }

    /** Raise a source/tile failure, the way an unreachable archive does. */
    simulateError(message) {
        this._fire('error', { error: new Error(message) });
    }

    remove() {
        this.removed = true;
    }
}

class FakeMarker {
    constructor(options = {}) {
        this.element = options.element || document.createElement('div');
        this.lngLat = null;
        this.map = null;
    }

    setLngLat(lngLat) {
        this.lngLat = lngLat;
        return this;
    }

    /** MapLibre returns a LngLat object, not the array it was given. */
    getLngLat() {
        if (Array.isArray(this.lngLat)) {
            return { lng: this.lngLat[0], lat: this.lngLat[1] };
        }
        return this.lngLat;
    }

    addTo(map) {
        this.map = map;
        if (map && map.container) {
            map.container.appendChild(this.element);
        }
        return this;
    }

    remove() {
        if (this.element && this.element.parentNode) {
            this.element.parentNode.removeChild(this.element);
        }
        this.map = null;
        return this;
    }
}

export function createFakeMaplibre() {
    const protocols = [];
    let workerUrl = null;

    return {
        Map: FakeMap,
        Marker: FakeMarker,
        NavigationControl: class {},
        ScaleControl: class {},
        addProtocol: (name, fn) => protocols.push({ name, fn }),
        setWorkerUrl: (url) => { workerUrl = url; },
        __protocols: protocols,
        __workerUrl: () => workerUrl,
    };
}

export function createFakePmtiles() {
    return { Protocol: class { constructor() { this.tile = () => {}; } } };
}
