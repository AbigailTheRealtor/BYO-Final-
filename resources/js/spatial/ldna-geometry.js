/*
 |-----------------------------------------------------------------------------
 | Location DNA geometry — renderer-neutral (Phase 1)
 |-----------------------------------------------------------------------------
 |
 | Pure functions. No MapLibre, no Google, no DOM, no network. This module is the
 | ONLY place that knows how stored Location DNA geometry is shaped, and the only
 | place that converts between that shape and GeoJSON.
 |
 | WHY THE CONVERSION LIVES HERE AND NOWHERE ELSE
 | ----------------------------------------------
 | Stored geometry is `{lat, lng}`. GeoJSON positions are `[lng, lat]` — the same
 | two numbers in the opposite order, with no type difference to catch a mistake.
 | A transposition bug does not throw; it silently relocates a Florida polygon to
 | somewhere off the coast of Somalia. Keeping both directions in one file, beside
 | each other, is what makes that mistake reviewable.
 |
 | THE STORED CONTRACT IS FROZEN. This module converts at the renderer boundary
 | and never rewrites what is persisted:
 |
 |     polygons[]        { label, path: [{lat, lng}, ...] }
 |     radius_searches[] { lat, lng, radius_miles, address? | label? }
 |
 | `radius_searches` deliberately carries BOTH drawn circles and address radius
 | searches, discriminated only by which optional key is present — `address` for a
 | radius search, `label` for a drawn circle. That conflation is pre-existing and
 | is preserved exactly; separating them is a storage change and is not this
 | phase's work. See `circleKind()`.
 */

/** Metres in one statute mile. The widget's own constant, kept identical. */
export const METRES_PER_MILE = 1609.34;

/** Mean Earth radius in metres (WGS-84 authalic). */
const EARTH_RADIUS_M = 6371008.8;

const toRad = (deg) => (deg * Math.PI) / 180;
const toDeg = (rad) => (rad * 180) / Math.PI;

/**
 * Is this a usable coordinate pair?
 *
 * Rejects null, undefined, NaN and out-of-range values. `0` is a legitimate
 * latitude and longitude, so every check here is explicit rather than truthy —
 * a `!lat` test would discard the Gulf of Guinea and, more usefully here, would
 * discard a parsing bug that produced 0 by treating it as "absent".
 */
export function isFiniteCoord(lat, lng) {
    // Reject the absent values BEFORE coercing. `Number(null)`, `Number('')`,
    // `Number(false)` and `Number([])` are all 0 — a real, in-range coordinate off
    // the coast of Africa. Coercing first would therefore turn "this row has no
    // coordinate" into "this row is at Null Island", and the pin would be rendered
    // and saved as though the user had placed it.
    for (const value of [lat, lng]) {
        if (value === null || value === undefined || value === '' || typeof value === 'boolean') {
            return false;
        }
    }

    const a = Number(lat);
    const b = Number(lng);

    return Number.isFinite(a) && Number.isFinite(b)
        && a >= -90 && a <= 90
        && b >= -180 && b <= 180;
}

/**
 * Stored point -> GeoJSON position.
 *
 * @param  {{lat: number|string, lng: number|string}} point
 * @return {[number, number]|null} `[lng, lat]`, or null when unusable
 */
export function pointToPosition(point) {
    if (!point) {
        return null;
    }

    // Accept the nested `center` shape the widget's rehydration path already
    // tolerates, so this function accepts everything the stored data can hold.
    const lat = point.lat !== undefined ? point.lat : (point.center ? point.center.lat : undefined);
    const lng = point.lng !== undefined ? point.lng : (point.center ? point.center.lng : undefined);

    return isFiniteCoord(lat, lng) ? [Number(lng), Number(lat)] : null;
}

/**
 * GeoJSON position -> stored point.
 *
 * @param  {[number, number]} position `[lng, lat]`
 * @return {{lat: number, lng: number}|null}
 */
export function positionToPoint(position) {
    if (!Array.isArray(position) || position.length < 2) {
        return null;
    }

    const [lng, lat] = position;

    return isFiniteCoord(lat, lng) ? { lat: Number(lat), lng: Number(lng) } : null;
}

/**
 * Stored polygon -> GeoJSON Feature.
 *
 * The ring is closed if the stored path does not already close it. GeoJSON
 * requires a closed linear ring; the widget's stored paths are open, because
 * Google's Polygon closes implicitly. Closing on read and dropping the closing
 * position on write keeps the stored bytes unchanged.
 *
 * Returns null for a path too short to be an area — the widget stores such rows
 * only transiently while drawing, and rendering a two-point "polygon" produces
 * an invisible artefact that is impossible to select or delete.
 */
export function polygonToFeature(polygon, id) {
    if (!polygon || !Array.isArray(polygon.path)) {
        return null;
    }

    const ring = polygon.path.map(pointToPosition).filter(Boolean);

    if (ring.length < 3) {
        return null;
    }

    const first = ring[0];
    const last = ring[ring.length - 1];

    if (first[0] !== last[0] || first[1] !== last[1]) {
        ring.push([first[0], first[1]]);
    }

    return {
        type: 'Feature',
        id,
        properties: { ldnaId: id, ldnaType: 'polygon', label: polygon.label || '' },
        geometry: { type: 'Polygon', coordinates: [ring] },
    };
}

/**
 * GeoJSON Feature -> stored polygon.
 *
 * Drops the closing position so the stored path stays open, matching what the
 * Google implementation wrote. Round-tripping a stored polygon through
 * `polygonToFeature` and back must return the same `path`.
 */
export function featureToPolygon(feature, label) {
    const ring = feature
        && feature.geometry
        && feature.geometry.type === 'Polygon'
        && Array.isArray(feature.geometry.coordinates)
            ? feature.geometry.coordinates[0]
            : null;

    if (!Array.isArray(ring) || ring.length < 4) {
        return null;
    }

    const open = ring.slice();
    const first = open[0];
    const last = open[open.length - 1];

    if (first[0] === last[0] && first[1] === last[1]) {
        open.pop();
    }

    const path = open.map(positionToPoint).filter(Boolean);

    if (path.length < 3) {
        return null;
    }

    return { label: label || (feature.properties && feature.properties.label) || '', path };
}

/**
 * Great-circle distance in metres.
 *
 * The widget's `ldnaDistanceMeters`, moved here unchanged in behaviour so the
 * two-click circle tool measures identically under either renderer.
 */
export function haversineMetres(a, b) {
    const p1 = pointToPosition(a);
    const p2 = pointToPosition(b);

    if (!p1 || !p2) {
        return NaN;
    }

    const [lng1, lat1] = p1;
    const [lng2, lat2] = p2;

    const dLat = toRad(lat2 - lat1);
    const dLng = toRad(lng2 - lng1);

    const h = Math.sin(dLat / 2) ** 2
        + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;

    return 2 * EARTH_RADIUS_M * Math.asin(Math.min(1, Math.sqrt(h)));
}

/**
 * Which kind of entry is this `radius_searches` row?
 *
 * The discriminator is which optional key is present, and this is the ONLY
 * reader of that rule. `address` means the row came from the Radius Search box;
 * anything else is a circle drawn on the map.
 *
 * Both render identically. The distinction survives only in the label the
 * overlay list shows, exactly as it did under Google.
 */
export function circleKind(entry) {
    return entry && entry.address ? 'radius_search' : 'circle';
}

/**
 * A stored circle rendered as a geodesic polygon.
 *
 * WHY A POLYGON AND NOT A CIRCLE LAYER
 * ------------------------------------
 * MapLibre's `circle` layer sizes its radius in SCREEN PIXELS, not ground units.
 * A five-mile radius drawn that way is five miles at exactly one zoom level and
 * wrong at every other, and it is wrong in a way that looks plausible — the
 * circle is still round and still centred correctly. Since these circles express
 * a search area the user will be matched against, a shape that silently changes
 * size with zoom is worse than no shape.
 *
 * So the radius is projected on the sphere and emitted as a closed ring. The
 * destination-point formula accounts for latitude, so a circle in Miami and one
 * in Seattle both cover their stated mileage.
 *
 * `steps` is the segment count. 64 is visually indistinguishable from a curve at
 * every zoom this basemap reaches (max 15) and keeps the feature small.
 */
export function circleToFeature(entry, id, steps = 64) {
    const centre = pointToPosition(entry);

    if (!centre) {
        return null;
    }

    const miles = Number(entry.radius_miles);
    // The widget's own fallback when a stored row carries no usable radius.
    const radiusM = (Number.isFinite(miles) && miles > 0 ? miles : 5) * METRES_PER_MILE;

    const [lng, lat] = centre;
    const angular = radiusM / EARTH_RADIUS_M;
    const latRad = toRad(lat);
    const lngRad = toRad(lng);

    const ring = [];

    for (let i = 0; i <= steps; i += 1) {
        const bearing = toRad((i * 360) / steps);

        const pointLat = Math.asin(
            Math.sin(latRad) * Math.cos(angular)
            + Math.cos(latRad) * Math.sin(angular) * Math.cos(bearing)
        );

        const pointLng = lngRad + Math.atan2(
            Math.sin(bearing) * Math.sin(angular) * Math.cos(latRad),
            Math.cos(angular) - Math.sin(latRad) * Math.sin(pointLat)
        );

        // Normalise into [-180, 180] so a circle straddling the antimeridian does
        // not emit positions MapLibre will refuse.
        ring.push([((toDeg(pointLng) + 540) % 360) - 180, toDeg(pointLat)]);
    }

    return {
        type: 'Feature',
        id,
        properties: {
            ldnaId: id,
            ldnaType: 'circle',
            kind: circleKind(entry),
            label: entry.label || entry.address || '',
        },
        geometry: { type: 'Polygon', coordinates: [ring] },
    };
}

/**
 * Every stored overlay as one FeatureCollection.
 *
 * Ids are assigned by kind and index — `polygon:0`, `circle:3` — so a feature can
 * be traced back to the row it came from without carrying an index that shifts
 * when a sibling is deleted.
 */
export function overlaysToFeatureCollection(state) {
    const features = [];
    const polygons = (state && state.polygons) || [];
    const circles = (state && state.radius_searches) || [];

    polygons.forEach((polygon, i) => {
        const feature = polygonToFeature(polygon, `polygon:${i}`);
        if (feature) {
            features.push(feature);
        }
    });

    circles.forEach((circle, i) => {
        const feature = circleToFeature(circle, `circle:${i}`);
        if (feature) {
            features.push(feature);
        }
    });

    return { type: 'FeatureCollection', features };
}

/**
 * Important Places that already carry coordinates, as point features.
 *
 * RENDER ONLY. This function never geocodes and never invents a coordinate — a
 * row without a usable lat/lng is skipped, not placed at a centroid or a city
 * centre. A pin the user did not put there is indistinguishable, once saved,
 * from one they did.
 *
 * A minutes-based row is a PIN, exactly like a miles-based one. Nothing here
 * converts a travel time into a radius: minutes describe a drive along a road
 * network, and drawing them as a circle would assert a reachable area that no
 * routing engine here has computed.
 */
export function importantPlacesToFeatureCollection(places) {
    const features = [];

    (places || []).forEach((place, i) => {
        const position = pointToPosition(place);

        if (!position) {
            return;
        }

        features.push({
            type: 'Feature',
            id: `place:${i}`,
            properties: {
                ldnaId: `place:${i}`,
                ldnaType: 'important_place',
                placeType: place.type || '',
                address: place.address || '',
                distancePreference: place.distance_preference || place.distpref || '',
                distanceValue: place.distance_value !== undefined ? place.distance_value : (place.value ?? ''),
                travelMode: place.travel_mode || place.mode || '',
            },
            geometry: { type: 'Point', coordinates: position },
        });
    });

    return { type: 'FeatureCollection', features };
}

/**
 * Bounding box of a FeatureCollection, as `[[west, south], [east, north]]`.
 *
 * Returns null for an empty collection so the caller can leave the configured
 * initial view alone rather than fitting to a degenerate box.
 */
export function boundsOf(collection) {
    let west = Infinity;
    let south = Infinity;
    let east = -Infinity;
    let north = -Infinity;

    const visit = (position) => {
        if (!Array.isArray(position) || !Number.isFinite(position[0]) || !Number.isFinite(position[1])) {
            return;
        }
        west = Math.min(west, position[0]);
        east = Math.max(east, position[0]);
        south = Math.min(south, position[1]);
        north = Math.max(north, position[1]);
    };

    const walk = (coords) => {
        if (!Array.isArray(coords)) {
            return;
        }
        if (typeof coords[0] === 'number') {
            visit(coords);
            return;
        }
        coords.forEach(walk);
    };

    ((collection && collection.features) || []).forEach((feature) => {
        if (feature && feature.geometry) {
            walk(feature.geometry.coordinates);
        }
    });

    return Number.isFinite(west) && Number.isFinite(south) ? [[west, south], [east, north]] : null;
}
