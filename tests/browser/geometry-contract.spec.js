/*
 |-----------------------------------------------------------------------------
 | Stored-geometry compatibility — the zero-migration proof
 |-----------------------------------------------------------------------------
 |
 | The migration's central promise is that no stored Location DNA record needs
 | rewriting. These specs are that promise, executed: geometry created by the
 | Google implementation goes in, is rendered, is edited, and comes back out in
 | exactly the shape it went in — same keys, same orientation, same precision.
 |
 | The fixtures below are the persisted shape verbatim, including the awkward
 | parts: the nested `center` variant the widget's rehydration path tolerates, a
 | row with no radius at all, and the `address` / `label` split that is the only
 | thing distinguishing a radius search from a drawn circle.
 */

const { test, expect } = require('@playwright/test');
const { installNetworkGuard } = require('./support/network');

test.beforeEach(async ({ page }) => {
    await installNetworkGuard(page);
});

async function boot(page, state) {
    await page.goto('/renderer-harness.html');
    await page.click('#reveal-tab');
    await page.evaluate((s) => window.LDNA.boot({ fake: true, state: s }), state);
    await expect.poll(() => page.evaluate(() => window.LDNA.sourceFeatureCount('ldna-overlays'))).not.toBeNull();
}

test.describe('round trip', () => {
    test('a stored polygon survives hydrate -> render -> read unchanged', async ({ page }) => {
        const stored = {
            polygons: [{
                label: 'Historic district',
                path: [
                    { lat: 27.771234, lng: -82.641234 },
                    { lat: 27.789876, lng: -82.641234 },
                    { lat: 27.789876, lng: -82.612345 },
                    { lat: 27.771234, lng: -82.612345 },
                ],
            }],
            radius_searches: [],
        };

        await boot(page, stored);

        const out = await page.evaluate(() => window.LDNA.renderer.getState());

        // Byte-for-byte on the parts that are persisted. Precision matters: a
        // renderer that rounded coordinates would move every saved boundary
        // slightly on every open-and-save cycle.
        expect(out.polygons).toEqual(stored.polygons);
    });

    test('stored circles survive, including the nested-center and missing-radius variants', async ({ page }) => {
        const stored = {
            polygons: [],
            radius_searches: [
                { lat: 27.7676, lng: -82.6403, radius_miles: 5, address: '100 Central Ave' },
                { lat: 28.0, lng: -82.5, radius_miles: 2.5, label: 'Circle 1' },
                // Tolerated legacy shapes: nested center, and no radius at all.
                { center: { lat: 27.5, lng: -82.4 }, radius_miles: 1 },
                { lat: 27.4, lng: -82.3 },
            ],
        };

        await boot(page, stored);

        // Every stored row is preserved verbatim in the working set — the renderer
        // normalises for DRAWING only, never for storage.
        const out = await page.evaluate(() => window.LDNA.renderer.getState());
        expect(out.radius_searches).toEqual(stored.radius_searches);

        // All four render, including the two legacy shapes.
        const circles = (await page.evaluate(() => window.LDNA.sourceFeatures('ldna-overlays')))
            .filter((f) => f.properties.ldnaType === 'circle');
        expect(circles).toHaveLength(4);
    });

    test('a polygon edited by dragging a vertex comes back in the stored shape', async ({ page }) => {
        await boot(page, {
            polygons: [{ label: 'A', path: [{ lat: 27.70, lng: -82.60 }, { lat: 27.80, lng: -82.60 }, { lat: 27.80, lng: -82.50 }] }],
            radius_searches: [],
        });

        await page.evaluate(() => {
            const r = window.LDNA.renderer;
            r.editPolygon(0);
            const map = r.getMap();
            map._fire('mousedown', { features: [{ properties: { polygonIndex: 0, vertexIndex: 2 } }], preventDefault() {} }, 'ldna-vertices-circles');
            map._fire('mousemove', { lngLat: { lng: -82.45, lat: 27.85 } });
            map._fire('mouseup', {});
        });

        const out = await page.evaluate(() => window.LDNA.renderer.getState());

        // Same keys, same order, same `{lat,lng}` orientation — only the dragged
        // vertex differs.
        expect(out.polygons[0]).toEqual({
            label: 'A',
            path: [{ lat: 27.70, lng: -82.60 }, { lat: 27.80, lng: -82.60 }, { lat: 27.85, lng: -82.45 }],
        });
    });

    test('the reported change payload carries only the two persisted collections', async ({ page }) => {
        await boot(page, { polygons: [], radius_searches: [] });

        await page.evaluate(() => window.LDNA.renderer.addRadiusSearch({ lat: 27.9, lng: -82.4, radius_miles: 3 }));

        const event = (await page.evaluate(() => window.LDNA.events))[0];

        // No GeoJSON leaks into what the host will serialise. If a `geometry` or
        // `coordinates` key ever appeared here it would be written to storage.
        expect(Object.keys(event).sort()).toEqual(['polygons', 'radius_searches']);
        expect(JSON.stringify(event)).not.toContain('coordinates');
        expect(JSON.stringify(event)).not.toContain('Feature');
    });

    test('unusable coordinates are dropped from rendering but never from storage', async ({ page }) => {
        const stored = {
            polygons: [{ label: 'Broken', path: [{ lat: 'x', lng: 'y' }, { lat: 27.8, lng: -82.6 }] }],
            radius_searches: [{ lat: 999, lng: -82.5, radius_miles: 2 }],
        };

        await boot(page, stored);

        // Nothing renderable — a two-point path and an out-of-range latitude.
        expect(await page.evaluate(() => window.LDNA.sourceFeatureCount('ldna-overlays'))).toBe(0);

        // But the rows are still held, so a save does not silently discard data the
        // renderer merely could not draw. Dropping them here would be the same
        // class of destruction PR #124 closed.
        const out = await page.evaluate(() => window.LDNA.renderer.getState());
        expect(out.polygons).toHaveLength(1);
        expect(out.radius_searches).toHaveLength(1);
    });
});

test.describe('absent coordinates are not Null Island', () => {
    /*
     | Regression guard for a defect this suite found. `Number(null)` is 0, and so
     | is `Number('')` — both are valid, in-range coordinates in the Gulf of Guinea.
     | A validator that coerced before checking would turn "this row has no
     | coordinate" into "this row is at 0,0", render a pin there, and save it as
     | though the user had placed it.
     */
    const ABSENT = [
        { label: 'null', lat: null, lng: null },
        { label: 'undefined', lat: undefined, lng: undefined },
        { label: 'empty string', lat: '', lng: '' },
        { label: 'mixed null/number', lat: null, lng: -82.6 },
    ];

    for (const variant of ABSENT) {
        test(`does not render an Important Place whose coordinate is ${variant.label}`, async ({ page }) => {
            await boot(page, {
                important_places: [{ type: 'Work', address: 'x', lat: variant.lat, lng: variant.lng }],
            });

            expect(await page.evaluate(() => window.LDNA.placePinCount())).toBe(0);
        });
    }

    test('a genuine 0,0 coordinate is still accepted', async ({ page }) => {
        // The corollary, and the reason the check rejects absence rather than
        // falsiness: zero is a real latitude and a real longitude.
        await boot(page, { important_places: [{ type: 'Buoy', address: 'Null Island', lat: 0, lng: 0 }] });

        expect(await page.evaluate(() => window.LDNA.placePinCount())).toBe(1);
    });
});
