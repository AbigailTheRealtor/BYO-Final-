/*
 |-----------------------------------------------------------------------------
 | Phase 2 — the behaviour the HOST wiring depends on
 |-----------------------------------------------------------------------------
 |
 | Phase 1 proved the renderer works. This file proves the three things the Blade
 | wiring now relies on, each of which is a live production condition rather than a
 | hypothetical:
 |
 |   1. Geometry paints even when the basemap archive cannot be read. The R2 bucket
 |      that hosts the PMTiles archive currently serves NO CORS headers, so a browser
 |      refuses every tile. A renderer that waits for `load` never adds its geometry
 |      sources in that state, and the user's stored polygons vanish onto a map that
 |      is otherwise alive. This is the single most important spec in the file.
 |
 |   2. fitToGeometry frames what is actually stored, and does not snap to maxZoom on
 |      a lone property pin, whose extent has zero area.
 |
 |   3. A second mount on the same container is a no-op, so a Livewire re-render
 |      cannot produce two maps fighting over one element.
 |
 | Only maplibre-gl is substituted; the renderer is the real module. See
 | tests/browser/fixtures/fake-maplibre.js.
 */

const { test, expect } = require('@playwright/test');
const { installNetworkGuard } = require('./support/network');

const STORED = {
    polygons: [
        { label: 'Downtown', path: [{ lat: 27.77, lng: -82.64 }, { lat: 27.79, lng: -82.64 }, { lat: 27.79, lng: -82.61 }] },
    ],
    radius_searches: [
        { lat: 27.7676, lng: -82.6403, radius_miles: 5, address: '100 Central Ave' },
    ],
    important_places: [
        { type: 'Work', address: '200 Main St', lat: 27.95, lng: -82.46, distance_preference: 'miles', value: 3, travel_mode: 'driving' },
    ],
};

async function boot(page, opts = {}) {
    await page.goto('/renderer-harness.html');
    await page.click('#reveal-tab');
    await page.evaluate((o) => window.LDNA.boot({ fake: true, ...o }), opts);
    await expect.poll(() => page.evaluate(() => window.LDNA.sourceFeatureCount('ldna-overlays'))).not.toBeNull();
}

test.beforeEach(async ({ page }) => {
    await installNetworkGuard(page);
});

test.describe('geometry survives a basemap that cannot load', () => {
    test('stored geometry is still painted when the archive fails — the live CORS case', async ({ page }) => {
        await boot(page, { state: STORED, pmtilesUrl: 'pmtiles://https://archive.invalid/fl.pmtiles' });

        // Everything is on the map to begin with.
        expect(await page.evaluate(() => window.LDNA.sourceFeatureCount('ldna-overlays'))).toBe(2);

        // Now the archive refuses, exactly as an R2 bucket with no CORS policy does.
        await page.evaluate(() => window.LDNA.renderer.getMap().simulateError('Failed to fetch'));

        // The style is swapped for the blank one and the geometry is REPAINTED onto it.
        await expect
            .poll(() => page.evaluate(() => window.LDNA.sourceFeatureCount('ldna-overlays')))
            .toBe(2);

        const status = await page.evaluate(() => window.LDNA.status);
        const degraded = status.filter((s) => s.state === 'degraded');
        expect(degraded.length).toBeGreaterThan(0);
        expect(degraded[degraded.length - 1].message).toContain('your saved areas are shown');
    });

    test('the style is swapped once, however many errors the dead archive raises', async ({ page }) => {
        await boot(page, { state: STORED, pmtilesUrl: 'pmtiles://https://archive.invalid/fl.pmtiles' });

        await page.evaluate(() => {
            const map = window.LDNA.renderer.getMap();
            map.simulateError('Failed to fetch');
            map.simulateError('Failed to fetch');
            map.simulateError('Failed to fetch');
        });

        await expect
            .poll(() => page.evaluate(() => window.LDNA.renderer.getMap().styleSwaps || 0))
            .toBe(1);
    });

    test('a degraded map is still editable — the point of degrading rather than refusing', async ({ page }) => {
        await boot(page, { state: STORED, pmtilesUrl: 'pmtiles://https://archive.invalid/fl.pmtiles' });

        await page.evaluate(() => window.LDNA.renderer.getMap().simulateError('Failed to fetch'));
        await expect.poll(() => page.evaluate(() => window.LDNA.sourceFeatureCount('ldna-overlays'))).toBe(2);

        const state = await page.evaluate(() => {
            window.LDNA.renderer.addRadiusSearch({ lat: 27.5, lng: -82.5, radius_miles: 2 });
            return window.LDNA.renderer.getState();
        });

        expect(state.radius_searches).toHaveLength(2);
        expect(state.polygons).toHaveLength(1);
    });
});

test.describe('fitting the viewport to what is stored', () => {
    test('frames the stored geometry rather than the configured initial view', async ({ page }) => {
        await boot(page, { state: STORED });

        const fitted = await page.evaluate(() => {
            window.LDNA.renderer.fitToGeometry();
            return window.LDNA.renderer.getMap().fitted;
        });

        expect(fitted).not.toBeNull();
        const [[west, south], [east, north]] = fitted.bounds;

        // The extent must contain every stored shape: the polygon, the 5-mile circle
        // around Central Ave, and the Important Place pin out at -82.46.
        expect(west).toBeLessThanOrEqual(-82.64);
        expect(east).toBeGreaterThanOrEqual(-82.46);
        expect(south).toBeLessThanOrEqual(27.7);
        expect(north).toBeGreaterThanOrEqual(27.95);
        expect(fitted.opts.duration).toBe(0);
    });

    test('a lone property pin is centred at a sane zoom, not fitted to a zero-area box', async ({ page }) => {
        await boot(page, { state: {} });

        const result = await page.evaluate(() => {
            window.LDNA.renderer.setPropertyPin({ lat: 27.7676, lng: -82.6403 });
            window.LDNA.renderer.fitToGeometry();
            const map = window.LDNA.renderer.getMap();
            return { fitted: map.fitted, jumped: map.jumped };
        });

        expect(result.fitted).toBeNull();
        expect(result.jumped).not.toBeUndefined();
        expect(result.jumped.center).toEqual([-82.6403, 27.7676]);
        expect(result.jumped.zoom).toBeGreaterThan(10);
    });

    test('a listing with nothing stored leaves the configured initial view alone', async ({ page }) => {
        await boot(page, { state: {} });

        const result = await page.evaluate(() => {
            window.LDNA.renderer.fitToGeometry();
            const map = window.LDNA.renderer.getMap();
            return { fitted: map.fitted, jumped: map.jumped };
        });

        // Not "fitted to nothing" and certainly not Null Island — untouched.
        expect(result.fitted).toBeNull();
        expect(result.jumped).toBeUndefined();
    });
});

test.describe('one map per container', () => {
    test('a second init() is a no-op rather than a second map', async ({ page }) => {
        // A Livewire re-render, a tab reveal and the bundle's own mount pass can all reach
        // init(). Two maps on one element means two canvases, doubled tile requests and a
        // second renderer whose getState() the host never reads — so the stored geometry
        // would follow whichever one happened to answer.
        await boot(page, { state: STORED });

        const same = await page.evaluate(() => {
            const first = window.LDNA.renderer.getMap();
            const second = window.LDNA.renderer.init();
            return first === second;
        });

        expect(same).toBe(true);
        expect(await page.evaluate(() => document.querySelectorAll('#ldna-map canvas').length)).toBe(1);
    });
});

test.describe('the round trip the host serialises through', () => {
    test('hydrate -> getState returns the stored geometry unchanged', async ({ page }) => {
        await boot(page, { state: STORED });

        const state = await page.evaluate(() => window.LDNA.renderer.getState());

        expect(state.polygons).toEqual(STORED.polygons);
        expect(state.radius_searches).toEqual(STORED.radius_searches);
    });

    test('isHydrated is false until storage has been read — what the host branches on', async ({ page }) => {
        // The MapLibre half of the PR #124 contract. map-input's serialiser rebuilds the
        // two geometry keys ONLY when this is true; while it is false the server-seeded
        // values are left alone and the blob round-trips byte-for-byte.
        await page.goto('/renderer-harness.html');
        await page.click('#reveal-tab');
        await page.evaluate(() => window.LDNA.boot({ fake: true, hydrate: false }));

        expect(await page.evaluate(() => window.LDNA.renderer.isHydrated())).toBe(false);

        await page.evaluate((s) => window.LDNA.renderer.hydrate(s), STORED);

        expect(await page.evaluate(() => window.LDNA.renderer.isHydrated())).toBe(true);
        expect(await page.evaluate(() => window.LDNA.renderer.getState().polygons)).toEqual(STORED.polygons);
    });

    test('an edit after hydration is reported, and a pre-hydration edit is not', async ({ page }) => {
        await page.goto('/renderer-harness.html');
        await page.click('#reveal-tab');
        await page.evaluate(() => window.LDNA.boot({ fake: true, hydrate: false }));

        await page.evaluate(() => window.LDNA.renderer.addRadiusSearch({ lat: 27.5, lng: -82.5, radius_miles: 1 }));
        expect(await page.evaluate(() => window.LDNA.events.length)).toBe(0);

        await page.evaluate((s) => window.LDNA.renderer.hydrate(s), STORED);
        await page.evaluate(() => window.LDNA.renderer.addRadiusSearch({ lat: 27.6, lng: -82.6, radius_miles: 1 }));

        const events = await page.evaluate(() => window.LDNA.events);
        expect(events.length).toBe(1);
        expect(events[0].polygons).toEqual(STORED.polygons);
    });
});
