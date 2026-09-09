/*
 |-----------------------------------------------------------------------------
 | The MapLibre renderer's behaviour, exercised in a real browser
 |-----------------------------------------------------------------------------
 |
 | These specs drive the REAL renderer from resources/js/spatial. Only maplibre-gl
 | itself is substituted, so that the suite still runs in a container with no
 | WebGL — see tests/browser/fixtures/fake-maplibre.js for why that substitution
 | is legitimate and what it faithfully models.
 |
 | Everything asserted here is a fact about OUR code: what reaches a GeoJSON
 | source, when a change may be reported, what survives a delete, and — most
 | importantly — what happens to stored geometry when the map does not work.
 */

const { test, expect } = require('@playwright/test');
const { installNetworkGuard } = require('./support/network');

/** Two stored polygons and two stored circles, in the exact persisted shape. */
const STORED = {
    polygons: [
        { label: 'Downtown', path: [{ lat: 27.77, lng: -82.64 }, { lat: 27.79, lng: -82.64 }, { lat: 27.79, lng: -82.61 }, { lat: 27.77, lng: -82.61 }] },
        { label: 'Beach', path: [{ lat: 27.90, lng: -82.85 }, { lat: 27.93, lng: -82.85 }, { lat: 27.93, lng: -82.80 }] },
    ],
    radius_searches: [
        { lat: 27.7676, lng: -82.6403, radius_miles: 5, address: '100 Central Ave, St Petersburg, FL' },
        { lat: 28.0, lng: -82.5, radius_miles: 2.5, label: 'Circle 1' },
    ],
    important_places: [
        { type: 'Work', address: '200 Main St', lat: 27.95, lng: -82.46, distance_preference: 'minutes', value: 25, travel_mode: 'driving' },
        { type: 'Gym', address: '5 Beach Dr', lat: 27.77, lng: -82.63, distance_preference: 'miles', value: 3, travel_mode: 'walking' },
    ],
};

async function boot(page, opts = {}) {
    await page.goto('/renderer-harness.html');
    await page.click('#reveal-tab');
    await page.evaluate((o) => window.LDNA.boot({ fake: true, ...o }), opts);
    // The fake fires `load` on a later task, exactly as MapLibre does; sources are
    // created in that handler, so wait for them rather than assuming.
    await expect.poll(() => page.evaluate(() => window.LDNA.sourceFeatureCount('ldna-overlays'))).not.toBeNull();
}

test.beforeEach(async ({ page }) => {
    await installNetworkGuard(page);
});

test.describe('basemap lifecycle', () => {
    test('initialises without a PMTiles archive and says so, instead of refusing', async ({ page }) => {
        await boot(page, { state: {} });

        const status = await page.evaluate(() => window.LDNA.status);
        expect(status.some((s) => s.state === 'degraded')).toBe(true);

        // The point of the degraded path: the editor still exists.
        expect(await page.evaluate(() => !!window.LDNA.renderer.getMap())).toBe(true);
    });

    test('resize() is available for a tab reveal and reaches the map', async ({ page }) => {
        await boot(page, { state: {} });

        const before = await page.evaluate(() => window.LDNA.renderer.getMap().resizeCount);
        await page.evaluate(() => window.LDNA.renderer.resize());
        const after = await page.evaluate(() => window.LDNA.renderer.getMap().resizeCount);

        expect(after).toBe(before + 1);
    });

    test('registers the pmtiles protocol and sets a worker URL when an archive is configured', async ({ page }) => {
        await page.goto('/renderer-harness.html');
        await page.click('#reveal-tab');
        await page.evaluate(() => window.LDNA.boot({ fake: true, pmtilesUrl: 'https://example.invalid/fl.pmtiles', state: {} }));

        const protocols = await page.evaluate(() => window.LDNA.lib.__protocols.map((p) => p.name));
        const workerUrl = await page.evaluate(() => window.LDNA.lib.__workerUrl());

        expect(protocols).toContain('pmtiles');
        expect(workerUrl).toBeTruthy();
    });
});

test.describe('geometry safety — the PR #124 contract', () => {
    test('reports no change before hydration, however many times the map loads', async ({ page }) => {
        await page.goto('/renderer-harness.html');
        await page.click('#reveal-tab');
        await page.evaluate(() => window.LDNA.boot({ fake: true, hydrate: false, state: {} }));
        await page.waitForTimeout(300);

        expect(await page.evaluate(() => window.LDNA.renderer.isHydrated())).toBe(false);
        expect(await page.evaluate(() => window.LDNA.events.length)).toBe(0);
    });

    test('editing an unrelated field does not erase stored geometry', async ({ page }) => {
        await boot(page, { state: STORED });

        // Exactly the interaction that destroyed geometry under the old serializer:
        // touch a dimension that has nothing to do with the map.
        await page.fill('#ldna-state-input', 'GA');
        await page.fill('#ldna-notes', 'Near the water, quiet street.');
        await page.waitForTimeout(200);

        const state = await page.evaluate(() => window.LDNA.renderer.getState());
        expect(state.polygons).toHaveLength(2);
        expect(state.radius_searches).toHaveLength(2);

        // And nothing was reported as a change, because nothing about the geometry
        // changed. A change event here would be the bug.
        expect(await page.evaluate(() => window.LDNA.events.length)).toBe(0);
    });

    test('a map that never initialises cannot empty the working set', async ({ page }) => {
        await page.goto('/renderer-harness.html');
        // Never reveal the tab and never init: the renderer holds state without a map.
        await page.evaluate((stored) => window.LDNA.boot({ fake: true, autoInit: false, state: stored }), STORED);

        const state = await page.evaluate(() => window.LDNA.renderer.getState());
        expect(state.polygons).toHaveLength(2);
        expect(state.radius_searches).toHaveLength(2);
        expect(await page.evaluate(() => window.LDNA.renderer.getMap())).toBeNull();
    });
});

test.describe('stored geometry rendering', () => {
    test('renders stored polygons and circles as GeoJSON features', async ({ page }) => {
        await boot(page, { state: STORED });

        const features = await page.evaluate(() => window.LDNA.sourceFeatures('ldna-overlays'));
        expect(features).toHaveLength(4);

        const polygons = features.filter((f) => f.properties.ldnaType === 'polygon');
        const circles = features.filter((f) => f.properties.ldnaType === 'circle');
        expect(polygons).toHaveLength(2);
        expect(circles).toHaveLength(2);

        // The stored/GeoJSON transposition, asserted on a real coordinate. If lat
        // and lng were ever swapped this is the assertion that catches it.
        const downtown = polygons.find((f) => f.properties.label === 'Downtown');
        expect(downtown.geometry.coordinates[0][0]).toEqual([-82.64, 27.77]);

        // A ring must be closed; the stored path is not.
        const ring = downtown.geometry.coordinates[0];
        expect(ring[0]).toEqual(ring[ring.length - 1]);
        expect(ring).toHaveLength(5); // 4 stored vertices + closing position
    });

    test('distinguishes a radius search from a drawn circle by the stored key', async ({ page }) => {
        await boot(page, { state: STORED });

        const circles = (await page.evaluate(() => window.LDNA.sourceFeatures('ldna-overlays')))
            .filter((f) => f.properties.ldnaType === 'circle');

        expect(circles.map((c) => c.properties.kind).sort()).toEqual(['circle', 'radius_search']);
    });

    test('draws circles as ground-distance polygons, not screen-pixel circles', async ({ page }) => {
        await boot(page, { state: STORED });

        const circle = (await page.evaluate(() => window.LDNA.sourceFeatures('ldna-overlays')))
            .find((f) => f.properties.kind === 'radius_search');

        // A geodesic ring, not a point with a paint radius.
        expect(circle.geometry.type).toBe('Polygon');
        expect(circle.geometry.coordinates[0].length).toBe(65); // 64 segments, closed

        // 5 miles ≈ 0.0724° of latitude. Check the northern extreme is about right,
        // which is what proves the radius is in ground units rather than pixels.
        const lats = circle.geometry.coordinates[0].map((c) => c[1]);
        expect(Math.max(...lats) - 27.7676).toBeGreaterThan(0.068);
        expect(Math.max(...lats) - 27.7676).toBeLessThan(0.077);
    });
});

test.describe('drawing, editing, deleting', () => {
    test('creates a polygon, reports it, and preserves the stored shape', async ({ page }) => {
        await boot(page, { state: { polygons: [], radius_searches: [] } });

        const created = await page.evaluate(() => {
            const r = window.LDNA.renderer;
            const map = r.getMap();
            r.startDrawPolygon();
            map._fire('click', { lngLat: { lng: -82.60, lat: 27.70 } });
            map._fire('click', { lngLat: { lng: -82.55, lat: 27.70 } });
            map._fire('click', { lngLat: { lng: -82.55, lat: 27.75 } });
            return r.finishPolygon('Test area');
        });

        expect(created.label).toBe('Test area');
        expect(created.path).toHaveLength(3);
        expect(created.path[0]).toEqual({ lat: 27.70, lng: -82.60 });

        const events = await page.evaluate(() => window.LDNA.events);
        expect(events).toHaveLength(1);
        expect(events[0].polygons).toHaveLength(1);
    });

    test('discards a polygon with fewer than three vertices', async ({ page }) => {
        await boot(page, { state: { polygons: [], radius_searches: [] } });

        const result = await page.evaluate(() => {
            const r = window.LDNA.renderer;
            r.startDrawPolygon();
            r.getMap()._fire('click', { lngLat: { lng: -82.6, lat: 27.7 } });
            r.getMap()._fire('click', { lngLat: { lng: -82.5, lat: 27.7 } });
            return r.finishPolygon('Too small');
        });

        expect(result).toBeNull();
        expect(await page.evaluate(() => window.LDNA.renderer.getState().polygons)).toHaveLength(0);
        expect(await page.evaluate(() => window.LDNA.events.length)).toBe(0);
    });

    test('draws a circle from two clicks using ground distance', async ({ page }) => {
        await boot(page, { state: { polygons: [], radius_searches: [] } });

        await page.evaluate(() => {
            const r = window.LDNA.renderer;
            r.startDrawCircle();
            r.getMap()._fire('click', { lngLat: { lng: -82.64, lat: 27.77 } });
            // ~0.0724° north ≈ 5 miles
            r.getMap()._fire('click', { lngLat: { lng: -82.64, lat: 27.8424 } });
        });

        const circles = await page.evaluate(() => window.LDNA.renderer.getState().radius_searches);
        expect(circles).toHaveLength(1);
        expect(circles[0].radius_miles).toBeGreaterThan(4.8);
        expect(circles[0].radius_miles).toBeLessThan(5.2);
        // A drawn circle carries a label, never an address — that is the whole
        // discriminator between the two kinds.
        expect(circles[0].label).toBeTruthy();
        expect(circles[0].address).toBeUndefined();
    });

    test('shows draggable vertex handles and moves a vertex', async ({ page }) => {
        await boot(page, { state: STORED });

        await page.evaluate(() => window.LDNA.renderer.editPolygon(0));
        expect(await page.evaluate(() => window.LDNA.sourceFeatureCount('ldna-vertices'))).toBe(4);

        const moved = await page.evaluate(() => {
            const r = window.LDNA.renderer;
            const map = r.getMap();
            // press the second handle, drag, release
            map._fire('mousedown', { features: [{ properties: { polygonIndex: 0, vertexIndex: 1 } }], preventDefault() {} }, 'ldna-vertices-circles');
            map._fire('mousemove', { lngLat: { lng: -82.50, lat: 27.99 } });
            map._fire('mouseup', {});
            return r.getState().polygons[0].path[1];
        });

        expect(moved).toEqual({ lat: 27.99, lng: -82.50 });

        // Panning is restored after the drag, or the map would be stuck.
        expect(await page.evaluate(() => window.LDNA.renderer.getMap().dragPan.enabled)).toBe(true);

        // One change event, on release — not one per mousemove frame.
        expect(await page.evaluate(() => window.LDNA.events.length)).toBe(1);
    });

    test('deletes a polygon and a circle without disturbing the others', async ({ page }) => {
        await boot(page, { state: STORED });

        await page.evaluate(() => window.LDNA.renderer.deletePolygon(0));
        await page.evaluate(() => window.LDNA.renderer.deleteCircle(1));

        const state = await page.evaluate(() => window.LDNA.renderer.getState());
        expect(state.polygons).toHaveLength(1);
        expect(state.polygons[0].label).toBe('Beach');
        expect(state.radius_searches).toHaveLength(1);
        expect(state.radius_searches[0].address).toContain('Central Ave');
    });

    test('adds a radius search from a coordinate the caller resolved — never an address', async ({ page }) => {
        await boot(page, { state: { polygons: [], radius_searches: [] } });

        // Deterministic stub coordinate. This phase does no geocoding, and the
        // renderer offers no entry point that would accept an address.
        const entry = await page.evaluate(() => window.LDNA.renderer.addRadiusSearch({
            lat: 27.9506, lng: -82.4572, radius_miles: 3, address: '400 N Tampa St, Tampa, FL',
        }));

        expect(entry).toEqual({ lat: 27.9506, lng: -82.4572, radius_miles: 3, address: '400 N Tampa St, Tampa, FL' });
        expect(await page.evaluate(() => window.LDNA.renderer.getState().radius_searches)).toHaveLength(1);
    });
});

test.describe('boundaries', () => {
    const ZIP = {
        type: 'Feature',
        properties: { name: '33701' },
        geometry: { type: 'Polygon', coordinates: [[[-82.66, 27.75], [-82.61, 27.75], [-82.61, 27.79], [-82.66, 27.79], [-82.66, 27.75]]] },
    };

    test('renders city, county and ZIP boundaries supplied by the caller', async ({ page }) => {
        await boot(page, { state: {} });

        await page.evaluate((zip) => {
            window.LDNA.renderer.setBoundary('zip:33701', zip);
            window.LDNA.renderer.setBoundary('city:St Petersburg', zip);
            window.LDNA.renderer.setBoundary('county:Pinellas', zip);
        }, ZIP);

        expect(await page.evaluate(() => window.LDNA.sourceFeatureCount('ldna-boundaries'))).toBe(3);

        const keys = (await page.evaluate(() => window.LDNA.sourceFeatures('ldna-boundaries')))
            .map((f) => f.properties.ldnaBoundaryKey).sort();
        expect(keys).toEqual(['city:St Petersburg', 'county:Pinellas', 'zip:33701']);
    });

    test('clears one boundary without touching the rest', async ({ page }) => {
        await boot(page, { state: {} });
        await page.evaluate((zip) => {
            window.LDNA.renderer.setBoundary('zip:33701', zip);
            window.LDNA.renderer.setBoundary('county:Pinellas', zip);
            window.LDNA.renderer.clearBoundary('zip:33701');
        }, ZIP);

        const keys = (await page.evaluate(() => window.LDNA.sourceFeatures('ldna-boundaries')))
            .map((f) => f.properties.ldnaBoundaryKey);
        expect(keys).toEqual(['county:Pinellas']);
    });

    test('the renderer never fetches a boundary itself', async ({ page }) => {
        const record = await installNetworkGuard(page);
        await boot(page, { state: STORED });
        await page.evaluate((zip) => window.LDNA.renderer.setBoundary('zip:33701', zip), ZIP);

        // Boundary retrieval is the caller's job. This is what keeps the inherited
        // browser-direct Nominatim problem out of the new renderer.
        expect(record.external.filter((u) => u.includes('nominatim') || u.includes('tigerweb'))).toEqual([]);
    });
});

test.describe('Important Places — render only', () => {
    test('pins places that already carry coordinates, preserving their attributes', async ({ page }) => {
        await boot(page, { state: STORED });

        expect(await page.evaluate(() => window.LDNA.placePinCount())).toBe(2);

        const pins = await page.evaluate(() => Array.from(document.querySelectorAll('.ldna-place-pin')).map((el) => ({
            type: el.dataset.placeType,
            mode: el.dataset.travelMode,
            pref: el.dataset.distancePreference,
            value: el.dataset.distanceValue,
        })));

        expect(pins).toEqual([
            { type: 'Work', mode: 'driving', pref: 'minutes', value: '25' },
            { type: 'Gym', mode: 'walking', pref: 'miles', value: '3' },
        ]);
    });

    test('a minutes-based place is a pin and never becomes a radius', async ({ page }) => {
        await boot(page, { state: STORED });

        // The minutes place must add NO geometry to the overlay source. Converting
        // a travel time into a circle would assert a reachable area no routing
        // engine here has computed.
        const circles = (await page.evaluate(() => window.LDNA.sourceFeatures('ldna-overlays')))
            .filter((f) => f.properties.ldnaType === 'circle');

        expect(circles).toHaveLength(2); // the two stored circles, and no more
        expect(await page.evaluate(() => window.LDNA.renderer.getState().radius_searches)).toHaveLength(2);
    });

    test('skips a place with no usable coordinate rather than guessing one', async ({ page }) => {
        await boot(page, {
            state: {
                important_places: [
                    { type: 'Work', address: 'Somewhere unresolvable' },
                    { type: 'Gym', address: '5 Beach Dr', lat: 27.77, lng: -82.63 },
                ],
            },
        });

        expect(await page.evaluate(() => window.LDNA.placePinCount())).toBe(1);
    });
});

test.describe('read-only display surface support', () => {
    /*
     | The read-only component (components/location-dna-map.blade.php) shows one
     | property plus its saved geometry and boundaries. It shares this renderer
     | rather than getting a second MapLibre implementation, so the capability it
     | needs beyond the editing surface — the property's own pin — is proven here.
     */
    test('places the subject property pin, separately from Important Places', async ({ page }) => {
        await boot(page, { state: STORED });

        await page.evaluate(() => window.LDNA.renderer.setPropertyPin({ lat: 27.7676, lng: -82.6403 }));

        expect(await page.evaluate(() => window.LDNA.propertyPinCount())).toBe(1);
        // The property is NOT an important place; conflating them would put the
        // listing itself into a client's `important_places` on any write path.
        expect(await page.evaluate(() => window.LDNA.placePinCount())).toBe(2);
    });

    test('replaces rather than accumulates property pins, and ignores a bad coordinate', async ({ page }) => {
        await boot(page, { state: {} });

        await page.evaluate(() => {
            window.LDNA.renderer.setPropertyPin({ lat: 27.7, lng: -82.6 });
            window.LDNA.renderer.setPropertyPin({ lat: 28.1, lng: -82.4 });
        });
        expect(await page.evaluate(() => window.LDNA.propertyPinCount())).toBe(1);

        // An unusable coordinate clears the pin instead of dropping it somewhere.
        await page.evaluate(() => window.LDNA.renderer.setPropertyPin({ lat: null, lng: null }));
        expect(await page.evaluate(() => window.LDNA.propertyPinCount())).toBe(0);
    });
});
