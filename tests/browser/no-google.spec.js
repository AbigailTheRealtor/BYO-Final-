/*
 |-----------------------------------------------------------------------------
 | Zero Google, zero third-party egress
 |-----------------------------------------------------------------------------
 |
 | The migration's other central promise. Asserted on what the page ATTEMPTED, not
 | on what succeeded — a renderer that quietly fetched a Google tile and then
 | failed would otherwise pass a test that only checked the outcome.
 |
 | The guard in support/network.js proves itself in harness.spec.js by recording a
 | forbidden host on purpose, so a green result here is a real absence rather than
 | a recorder that never worked.
 */

const { test, expect } = require('@playwright/test');
const { installNetworkGuard, FORBIDDEN } = require('./support/network');

const STORED = {
    polygons: [{ label: 'A', path: [{ lat: 27.7, lng: -82.6 }, { lat: 27.8, lng: -82.6 }, { lat: 27.8, lng: -82.5 }] }],
    radius_searches: [{ lat: 27.7676, lng: -82.6403, radius_miles: 5, address: '100 Central Ave' }],
    important_places: [{ type: 'Work', address: '200 Main St', lat: 27.95, lng: -82.46, distance_preference: 'minutes', value: 25, travel_mode: 'driving' }],
};

test('a full render cycle contacts no Google host and no third-party geocoder', async ({ page }) => {
    const record = await installNetworkGuard(page);

    await page.goto('/renderer-harness.html');
    await page.click('#reveal-tab');
    await page.evaluate((s) => window.LDNA.boot({ fake: true, state: s }), STORED);
    await expect.poll(() => page.evaluate(() => window.LDNA.sourceFeatureCount('ldna-overlays'))).toBe(2);

    // Exercise every interaction that used to reach Google: drawing, editing,
    // adding a radius search, rendering place pins.
    await page.evaluate(() => {
        const r = window.LDNA.renderer;
        const map = r.getMap();
        r.startDrawPolygon();
        map._fire('click', { lngLat: { lng: -82.60, lat: 27.60 } });
        map._fire('click', { lngLat: { lng: -82.55, lat: 27.60 } });
        map._fire('click', { lngLat: { lng: -82.55, lat: 27.65 } });
        r.finishPolygon('Drawn');
        r.addRadiusSearch({ lat: 27.95, lng: -82.45, radius_miles: 2, address: 'stub' });
        r.editPolygon(0);
    });

    expect(record.forbidden).toEqual([]);

    // Belt and braces: nothing at all left the fixture origin. The renderer's only
    // legitimate external request is the PMTiles archive, and no archive is
    // configured in this fixture.
    expect(record.external).toEqual([]);
});

test('the renderer source contains no Google Maps API surface', async ({ page }) => {
    // A static assertion to complement the runtime one. A `google.maps.` reference
    // that never executes in a test would still ship, and would still require the
    // SDK to be loaded on the page.
    const sources = ['ldna-geometry.js', 'ldna-basemap.js', 'ldna-maplibre-renderer.js', 'ldna-maplibre.js'];

    for (const file of sources) {
        const body = await (await page.request.get(`/js/spatial/${file}`)).text();

        expect(body, `${file} must not reference the Google Maps SDK`).not.toMatch(/google\.maps\./);
        expect(body, `${file} must not reference a Google credential`).not.toMatch(/GOOGLE_PLACES_API_KEY/);
        expect(body, `${file} must not hardcode a maps.googleapis.com URL`).not.toContain('maps.googleapis.com');
    }
});

test('the renderer source issues no geocoding request of any kind', async ({ page }) => {
    // Phase boundary, asserted at source. Geocoding is explicitly out of scope, and
    // the easiest way for it to creep in is a helper that "just resolves" an
    // address inside the renderer.
    const body = await (await page.request.get('/js/spatial/ldna-maplibre-renderer.js')).text();

    for (const host of FORBIDDEN) {
        expect(body, `renderer must not name ${host}`).not.toContain(host);
    }

    expect(body).not.toMatch(/\bfetch\s*\(/);
    expect(body).not.toMatch(/XMLHttpRequest/);
});
