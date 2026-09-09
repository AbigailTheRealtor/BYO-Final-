/*
 |-----------------------------------------------------------------------------
 | Location DNA MapLibre renderer (Phase 1)
 |-----------------------------------------------------------------------------
 |
 | The renderer half of the Location DNA search-areas widget, as a MapLibre
 | implementation of the same contract the Google implementation satisfies.
 |
 | WHAT THIS OWNS
 |   * the basemap, including lazy initialisation inside a hidden tab
 |   * boundary GeoJSON overlays (city / county / ZIP / any renderer-neutral source)
 |   * stored polygons: render, draw, edit vertices, delete
 |   * stored circles and radius searches: render, draw, delete
 |   * Important Places that ALREADY carry coordinates: pins only
 |
 | WHAT THIS DOES NOT OWN, AND MUST NOT ACQUIRE
 |   * geocoding of any kind. No address becomes a coordinate in this file. The
 |     radius-search box and the Important Places address field are fed by a
 |     resolver injected from outside, and in this phase the only implementation
 |     is a deterministic stub used by tests.
 |   * boundary RETRIEVAL. Callers hand this renderer GeoJSON they already have;
 |     it never issues the request itself. That keeps the inherited browser-direct
 |     Nominatim problem out of the new renderer instead of porting it forward.
 |   * anything that writes storage. `onChange` reports; the host serialises.
 |
 | GEOMETRY SAFETY — THE PR #124 CONTRACT, PRESERVED
 | -------------------------------------------------
 | The widget's serialiser rebuilds `polygons` and `radius_searches` from its
 | working set only once that set is known to mirror stored state. This renderer
 | upholds the same rule from the other side: it does not emit a single change
 | event until `hydrate()` has completed, so a host wired to `onChange` can never
 | be told "there are zero overlays" merely because the map has not finished
 | loading. `isHydrated()` exposes the same fact for assertion.
 */

import {
    overlaysToFeatureCollection,
    importantPlacesToFeatureCollection,
    polygonToFeature,
    circleToFeature,
    featureToPolygon,
    haversineMetres,
    positionToPoint,
    pointToPosition,
    METRES_PER_MILE,
    boundsOf,
} from './ldna-geometry.js';

import { registerPmtilesProtocol, buildBasemapStyle, buildBlankStyle } from './ldna-basemap.js';

const SRC_OVERLAYS = 'ldna-overlays';
const SRC_BOUNDARIES = 'ldna-boundaries';
const SRC_DRAFT = 'ldna-draft';
const SRC_VERTICES = 'ldna-vertices';

const LYR_OVERLAY_FILL = 'ldna-overlay-fill';
const LYR_OVERLAY_LINE = 'ldna-overlay-line';
const LYR_BOUNDARY_FILL = 'ldna-boundary-fill';
const LYR_BOUNDARY_LINE = 'ldna-boundary-line';
const LYR_DRAFT_LINE = 'ldna-draft-line';
const LYR_DRAFT_FILL = 'ldna-draft-fill';
const LYR_VERTICES = 'ldna-vertices-circles';

const EMPTY = { type: 'FeatureCollection', features: [] };

/**
 * Create a renderer bound to one container.
 *
 * @param {object}   deps
 * @param {object}   deps.maplibregl  the MapLibre namespace (injected — see ldna-basemap)
 * @param {object}   deps.pmtiles     the pmtiles namespace (injected)
 * @param {Element}  deps.container   the map container element
 * @param {object}   deps.config      { pmtilesUrl, attribution, longitude, latitude, zoom, maxZoom }
 * @param {Function} [deps.onChange]  called with the current { polygons, radius_searches } after any edit
 * @param {Function} [deps.onStatus]  called with ('ready'|'error'|'degraded', message)
 */
export function createLdnaRenderer({ maplibregl, pmtiles, container, config = {}, onChange, onStatus }) {
    let map = null;
    let hydrated = false;
    let initialised = false;
    let destroyed = false;

    // The working set. Mirrors what the host has stored; the single source of
    // truth for everything this renderer draws and reports.
    let polygons = [];
    let circles = [];
    let places = [];

    const boundaries = new Map(); // key -> GeoJSON feature/collection
    const placeMarkers = [];
    let propertyMarker = null;   // the subject property's own pin (read-only surface)

    // Drawing state.
    let mode = null;          // null | 'polygon' | 'circle'
    let draftPoints = [];     // [{lat,lng}]
    let circleCentre = null;

    // Vertex editing state.
    let editing = null;       // { index } — which stored polygon has handles shown
    let dragVertex = null;    // { polygonIndex, vertexIndex }

    const emitStatus = (state, message) => {
        if (typeof onStatus === 'function') {
            onStatus(state, message);
        }
    };

    /**
     * Report the current geometry to the host.
     *
     * Silent until hydration completes — see the module header. This is the
     * renderer-side half of the PR #124 guarantee.
     */
    const emitChange = () => {
        if (!hydrated || typeof onChange !== 'function') {
            return;
        }
        onChange({
            polygons: polygons.map((p) => ({ label: p.label, path: p.path.map((pt) => ({ lat: pt.lat, lng: pt.lng })) })),
            radius_searches: circles.map((c) => ({ ...c })),
        });
    };

    const setData = (sourceId, data) => {
        if (!map) return;
        const source = map.getSource(sourceId);
        if (source) {
            source.setData(data);
        }
    };

    const refreshOverlays = () => {
        setData(SRC_OVERLAYS, overlaysToFeatureCollection({ polygons, radius_searches: circles }));
        refreshVertices();
    };

    const refreshBoundaries = () => {
        const features = [];
        boundaries.forEach((value, key) => {
            const list = value && value.type === 'FeatureCollection' ? value.features : [value];
            (list || []).forEach((feature) => {
                if (feature && feature.geometry) {
                    features.push({ ...feature, properties: { ...(feature.properties || {}), ldnaBoundaryKey: key } });
                }
            });
        });
        setData(SRC_BOUNDARIES, { type: 'FeatureCollection', features });
    };

    const refreshDraft = () => {
        if (!draftPoints.length && !circleCentre) {
            setData(SRC_DRAFT, EMPTY);
            return;
        }

        if (mode === 'circle' && circleCentre) {
            setData(SRC_DRAFT, EMPTY);
            return;
        }

        const ring = draftPoints.map(pointToPosition).filter(Boolean);
        const features = [];

        if (ring.length >= 2) {
            features.push({
                type: 'Feature',
                properties: { ldnaType: 'draft' },
                geometry: { type: 'LineString', coordinates: ring },
            });
        }

        if (ring.length >= 3) {
            features.push({
                type: 'Feature',
                properties: { ldnaType: 'draft' },
                geometry: { type: 'Polygon', coordinates: [[...ring, ring[0]]] },
            });
        }

        setData(SRC_DRAFT, { type: 'FeatureCollection', features });
    };

    /**
     * Vertex handles for the polygon currently being edited.
     *
     * Rendered as a circle layer because these ARE screen-space objects — a grab
     * handle should stay the same size at every zoom, which is exactly the
     * property that made a circle layer wrong for a search radius and right here.
     */
    const refreshVertices = () => {
        if (editing === null || !polygons[editing.index]) {
            setData(SRC_VERTICES, EMPTY);
            return;
        }

        const features = polygons[editing.index].path
            .map((point, i) => {
                const position = pointToPosition(point);
                return position
                    ? {
                        type: 'Feature',
                        id: i,
                        properties: { vertexIndex: i, polygonIndex: editing.index },
                        geometry: { type: 'Point', coordinates: position },
                    }
                    : null;
            })
            .filter(Boolean);

        setData(SRC_VERTICES, { type: 'FeatureCollection', features });
    };

    const clearPlaceMarkers = () => {
        placeMarkers.forEach((marker) => marker.remove());
        placeMarkers.length = 0;
    };

    /**
     * Important Places pins.
     *
     * DOM markers rather than a symbol layer, because the style carries no glyph
     * source (see ldna-basemap) and a symbol layer therefore cannot render text.
     * These pins are few — a handful per listing — so the DOM cost is irrelevant.
     */
    const refreshPlaces = () => {
        if (!map) return;
        clearPlaceMarkers();

        const collection = importantPlacesToFeatureCollection(places);

        collection.features.forEach((feature) => {
            const el = document.createElement('div');
            el.className = 'ldna-place-pin';
            el.setAttribute('data-ldna-place', feature.properties.ldnaId);
            el.setAttribute('data-place-type', feature.properties.placeType || '');
            el.setAttribute('data-travel-mode', feature.properties.travelMode || '');
            el.setAttribute('data-distance-preference', feature.properties.distancePreference || '');
            el.setAttribute('data-distance-value', String(feature.properties.distanceValue ?? ''));
            el.title = feature.properties.address || feature.properties.placeType || 'Important place';

            const marker = new maplibregl.Marker({ element: el })
                .setLngLat(feature.geometry.coordinates)
                .addTo(map);

            placeMarkers.push(marker);
        });
    };

    const addSourcesAndLayers = () => {
        map.addSource(SRC_BOUNDARIES, { type: 'geojson', data: EMPTY });
        map.addSource(SRC_OVERLAYS, { type: 'geojson', data: EMPTY });
        map.addSource(SRC_DRAFT, { type: 'geojson', data: EMPTY });
        map.addSource(SRC_VERTICES, { type: 'geojson', data: EMPTY });

        // Boundaries sit UNDER user geometry: they are context, and a ZIP outline
        // covering a drawn polygon would hide the thing the user is editing.
        map.addLayer({
            id: LYR_BOUNDARY_FILL,
            type: 'fill',
            source: SRC_BOUNDARIES,
            paint: { 'fill-color': '#0e7361', 'fill-opacity': 0.08 },
        });
        map.addLayer({
            id: LYR_BOUNDARY_LINE,
            type: 'line',
            source: SRC_BOUNDARIES,
            paint: { 'line-color': '#0e7361', 'line-width': 1.5, 'line-dasharray': [2, 1] },
        });

        map.addLayer({
            id: LYR_OVERLAY_FILL,
            type: 'fill',
            source: SRC_OVERLAYS,
            paint: {
                'fill-color': ['case', ['==', ['get', 'ldnaType'], 'polygon'], '#0369a1', '#6b7280'],
                'fill-opacity': ['case', ['==', ['get', 'ldnaType'], 'polygon'], 0.15, 0.12],
            },
        });
        map.addLayer({
            id: LYR_OVERLAY_LINE,
            type: 'line',
            source: SRC_OVERLAYS,
            paint: {
                'line-color': ['case', ['==', ['get', 'ldnaType'], 'polygon'], '#0369a1', '#6b7280'],
                'line-width': 2,
            },
        });

        map.addLayer({
            id: LYR_DRAFT_FILL,
            type: 'fill',
            source: SRC_DRAFT,
            paint: { 'fill-color': '#0369a1', 'fill-opacity': 0.1 },
        });
        map.addLayer({
            id: LYR_DRAFT_LINE,
            type: 'line',
            source: SRC_DRAFT,
            paint: { 'line-color': '#0369a1', 'line-width': 2, 'line-dasharray': [2, 1] },
        });

        map.addLayer({
            id: LYR_VERTICES,
            type: 'circle',
            source: SRC_VERTICES,
            paint: {
                'circle-radius': 6,
                'circle-color': '#ffffff',
                'circle-stroke-color': '#0369a1',
                'circle-stroke-width': 2,
            },
        });
    };

    // ── drawing interaction ──────────────────────────────────────────────────

    const onMapClick = (event) => {
        if (!mode) return;

        const point = positionToPoint([event.lngLat.lng, event.lngLat.lat]);
        if (!point) return;

        if (mode === 'polygon') {
            draftPoints.push(point);
            refreshDraft();
            return;
        }

        if (mode === 'circle') {
            if (!circleCentre) {
                circleCentre = point;
                return;
            }

            const metres = haversineMetres(circleCentre, point);
            const miles = Number.isFinite(metres) ? parseFloat((metres / METRES_PER_MILE).toFixed(2)) : 0;

            if (miles > 0) {
                circles.push({
                    lat: circleCentre.lat,
                    lng: circleCentre.lng,
                    radius_miles: miles,
                    label: `Circle ${circles.length + 1}`,
                });
                refreshOverlays();
                emitChange();
            }

            circleCentre = null;
            mode = null;
            refreshDraft();
        }
    };

    // ── vertex dragging ──────────────────────────────────────────────────────
    //
    // MapLibre has no editable-geometry primitive, so this is the smallest honest
    // equivalent of Google's `editable: true`: press a handle, drag, release. The
    // map's own pan is disabled for the duration, or the whole viewport would
    // travel with the vertex.

    const onVertexDown = (event) => {
        const feature = event.features && event.features[0];
        if (!feature) return;

        event.preventDefault();
        dragVertex = {
            polygonIndex: feature.properties.polygonIndex,
            vertexIndex: feature.properties.vertexIndex,
        };
        map.dragPan.disable();
        map.getCanvas().style.cursor = 'grabbing';
    };

    const onMouseMove = (event) => {
        if (!dragVertex) return;

        const polygon = polygons[dragVertex.polygonIndex];
        if (!polygon) return;

        polygon.path[dragVertex.vertexIndex] = { lat: event.lngLat.lat, lng: event.lngLat.lng };
        refreshOverlays();
    };

    const onMouseUp = () => {
        if (!dragVertex) return;
        dragVertex = null;
        map.dragPan.enable();
        map.getCanvas().style.cursor = '';
        // Emitted on release, not on every mousemove frame: the host serialises
        // on change, and a drag would otherwise write dozens of times per second.
        emitChange();
    };

    // ── public surface ───────────────────────────────────────────────────────

    return {
        /**
         * Boot the map. Safe to call repeatedly; only the first call initialises.
         *
         * Deliberately does NOT hydrate — the caller hydrates once it has state,
         * which keeps "the map exists" and "the map mirrors storage" separate
         * facts. Conflating them is what the PR #124 regression was.
         */
        init() {
            if (initialised || destroyed) {
                return map;
            }
            initialised = true;

            const archive = config.pmtilesUrl;
            let style;

            if (archive) {
                registerPmtilesProtocol(maplibregl, pmtiles);
                style = buildBasemapStyle(archive, config.attribution || '', Number(config.maxZoom) || 15);
            } else {
                // No archive configured: initialise anyway, without a backdrop.
                style = buildBlankStyle();
                emitStatus('degraded', 'No basemap archive configured; geometry editing remains available.');
            }

            try {
                map = new maplibregl.Map({
                    container,
                    style,
                    center: [Number(config.longitude) || -83.804601, Number(config.latitude) || 27.698638],
                    zoom: Number(config.zoom) || 6,
                    maxZoom: Number(config.maxZoom) || 15,
                    attributionControl: { compact: false },
                });
            } catch (error) {
                emitStatus('error', `MapLibre failed to initialise: ${error && error.message ? error.message : error}`);
                return null;
            }

            map.on('load', () => {
                addSourcesAndLayers();
                refreshBoundaries();
                refreshOverlays();
                refreshPlaces();
                emitStatus('ready', '');
            });

            // A tile or source failure must NOT take the editor down with it. The
            // basemap is the optional part; the geometry is not.
            map.on('error', (event) => {
                const detail = event && event.error && event.error.message ? event.error.message : 'unknown error';
                emitStatus('degraded', `Basemap tiles unavailable (${detail}). Geometry editing remains available.`);
            });

            map.on('click', onMapClick);
            map.on('mousedown', LYR_VERTICES, onVertexDown);
            map.on('mousemove', onMouseMove);
            map.on('mouseup', onMouseUp);

            return map;
        },

        /**
         * Adopt stored state as the working set, then unlock change reporting.
         *
         * The order is the safety property, and it is the same order the Google
         * implementation now uses: adopt everything first, flip the flag last.
         */
        hydrate(state = {}) {
            polygons = (state.polygons || []).map((p) => ({
                label: p.label || '',
                path: (p.path || []).map((pt) => ({ lat: Number(pt.lat), lng: Number(pt.lng) })),
            }));
            circles = (state.radius_searches || []).map((c) => ({ ...c }));
            places = state.important_places || [];

            refreshOverlays();
            refreshPlaces();

            hydrated = true;
            return this;
        },

        /** Has hydration completed? The renderer-side PR #124 assertion. */
        isHydrated: () => hydrated,

        /** The live working set, for assertion and for the host's serialiser. */
        getState: () => ({
            polygons: polygons.map((p) => ({ label: p.label, path: p.path.map((pt) => ({ ...pt })) })),
            radius_searches: circles.map((c) => ({ ...c })),
        }),

        /**
         * Reveal handler for a widget inside a tab.
         *
         * A map measured while its container is display:none gets a zero-sized
         * canvas and stays that size after the tab opens. Google needed a resize
         * trigger here and MapLibre needs the identical treatment.
         */
        resize() {
            if (map) {
                map.resize();
            }
            return this;
        },

        /** Boundary GeoJSON, supplied by the caller. This never fetches. */
        setBoundary(key, geojson) {
            if (geojson) {
                boundaries.set(key, geojson);
            } else {
                boundaries.delete(key);
            }
            refreshBoundaries();
            return this;
        },

        clearBoundary(key) {
            boundaries.delete(key);
            refreshBoundaries();
            return this;
        },

        /** Fit the viewport to a boundary already supplied. */
        fitToBoundary(key) {
            const geojson = boundaries.get(key);
            const bounds = geojson ? boundsOf(geojson.type === 'FeatureCollection' ? geojson : { features: [geojson] }) : null;
            if (map && bounds) {
                map.fitBounds(bounds, { padding: 24, duration: 0 });
            }
            return this;
        },

        startDrawPolygon() {
            mode = 'polygon';
            draftPoints = [];
            editing = null;
            refreshDraft();
            refreshVertices();
            return this;
        },

        /** Commit the draft. Fewer than three vertices is not an area — discard. */
        finishPolygon(label) {
            if (mode !== 'polygon' || draftPoints.length < 3) {
                mode = null;
                draftPoints = [];
                refreshDraft();
                return null;
            }

            const polygon = { label: label || `Polygon ${polygons.length + 1}`, path: draftPoints.slice() };
            polygons.push(polygon);

            mode = null;
            draftPoints = [];
            refreshDraft();
            refreshOverlays();
            emitChange();

            return polygon;
        },

        cancelDrawing() {
            mode = null;
            draftPoints = [];
            circleCentre = null;
            refreshDraft();
            return this;
        },

        startDrawCircle() {
            mode = 'circle';
            circleCentre = null;
            editing = null;
            refreshVertices();
            return this;
        },

        /**
         * Add a radius search at a coordinate the CALLER resolved.
         *
         * Takes a coordinate, never an address — geocoding is out of scope for
         * this phase and must not sneak in through this door.
         */
        addRadiusSearch({ lat, lng, radius_miles: radiusMiles, address }) {
            const point = pointToPosition({ lat, lng });
            if (!point) {
                return null;
            }

            const entry = { lat: Number(lat), lng: Number(lng), radius_miles: Number(radiusMiles) || 5 };
            if (address) {
                entry.address = address;
            }

            circles.push(entry);
            refreshOverlays();
            emitChange();

            return entry;
        },

        /** Show draggable vertex handles for one stored polygon. */
        editPolygon(index) {
            editing = polygons[index] ? { index } : null;
            refreshVertices();
            return this;
        },

        stopEditing() {
            editing = null;
            refreshVertices();
            return this;
        },

        isEditing: () => (editing ? editing.index : null),

        deletePolygon(index) {
            if (!polygons[index]) return this;
            polygons.splice(index, 1);
            if (editing && editing.index === index) {
                editing = null;
            }
            refreshOverlays();
            emitChange();
            return this;
        },

        deleteCircle(index) {
            if (!circles[index]) return this;
            circles.splice(index, 1);
            refreshOverlays();
            emitChange();
            return this;
        },

        /** Render Important Places that already carry coordinates. Never geocodes. */
        setImportantPlaces(list) {
            places = list || [];
            refreshPlaces();
            return this;
        },

        /**
         * The subject property's own pin.
         *
         * Exists for the READ-ONLY display surface
         * (components/location-dna-map.blade.php), which shows one property plus
         * its saved geometry. Kept separate from Important Places because it is a
         * different thing: the listing itself, not somewhere the client wants to be
         * near, and it must not appear in `important_places` on any write path.
         *
         * Like everything else here it takes a COORDINATE. The property's
         * coordinate is resolved server-side by the coordinate ladder long before
         * this renderer sees it; nothing in the browser geocodes it.
         */
        setPropertyPin(point) {
            if (propertyMarker) {
                propertyMarker.remove();
                propertyMarker = null;
            }

            const position = pointToPosition(point);

            if (!position || !map) {
                return this;
            }

            const el = document.createElement('div');
            el.className = 'ldna-property-pin';
            el.setAttribute('data-ldna-property-pin', '1');

            propertyMarker = new maplibregl.Marker({ element: el }).setLngLat(position).addTo(map);

            return this;
        },

        getMap: () => map,

        destroy() {
            destroyed = true;
            clearPlaceMarkers();
            if (propertyMarker) {
                propertyMarker.remove();
                propertyMarker = null;
            }
            if (map) {
                map.remove();
                map = null;
            }
            return this;
        },
    };
}
