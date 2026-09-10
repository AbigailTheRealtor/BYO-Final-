/*
 |-----------------------------------------------------------------------------
 | Basemap labels: the style asks for them, and the map survives without them
 |-----------------------------------------------------------------------------
 |
 | Two separate questions, and conflating them is how a labelled map becomes a
 | dead one.
 |
 |   1. Does the style actually declare glyphs and symbol layers over the source
 |      layers this archive carries? A style that does not is not a rendering
 |      problem, it is a map with no text, and it looks identical to a map whose
 |      fonts failed to load.
 |
 |   2. Does the renderer still work when the glyphs are NOT there? Labels are an
 |      enhancement to a widget whose real job is editing a listing's geography.
 |      The whole reason this renderer exists is that a basemap failure once took
 |      stored geometry down with it, and a font 404 must not be a new way to do
 |      the same thing.
 |
 | Question 1 is answered against the style object rather than against pixels:
 | asserting that text was PAINTED needs WebGL and a real archive, neither of
 | which CI has, and a screenshot diff of cartography is a test nobody can
 | maintain. What CI can prove is that the map was told to draw labels from
 | layers that exist, and that is where the defects actually live.
 |
 | Nothing here reaches the network — R2, Census and every other external host
 | are blocked by the guard, so the archive is never fetched and the label layers
 | are inspected as declarations.
 */

const { test, expect } = require('@playwright/test');
const { installNetworkGuard } = require('./support/network');
const { hasWebgl2 } = require('./support/capabilities');

/** The style, built by the real module, without booting a map. */
async function buildStyle(page, opts = {}) {
    await page.goto('/renderer-harness.html');

    return page.evaluate(async (o) => {
        const mod = await import('/js/spatial/ldna-basemap.js');
        return mod.buildBasemapStyle(
            'https://example.invalid/basemap.pmtiles',
            '© OpenStreetMap contributors',
            15,
            o
        );
    }, opts);
}

test.beforeEach(async ({ page }) => {
    await installNetworkGuard(page);
});

test.describe('the style declares labels', () => {
    test('glyphs are configured, and from this application only', async ({ page }) => {
        const style = await buildStyle(page);

        expect(style.glyphs).toBe('/fonts/{fontstack}/{range}.pbf');

        // Root-relative. A protocol or a host here would mean a third party can
        // take the labels away, which is the dependency this whole renderer
        // exists to be free of.
        expect(style.glyphs.startsWith('/')).toBe(true);
        expect(style.glyphs).not.toContain('//');
    });

    test('symbol layers exist for every label class we promised', async ({ page }) => {
        const style = await buildStyle(page);
        const ids = style.layers.filter((l) => l.type === 'symbol').map((l) => l.id);

        for (const id of [
            'place-label-locality',
            'place-label-locality-minor',
            'place-label-neighbourhood',
            'road-label-major',
            'road-label-minor',
            'road-label-shield',
            'water-label',
            'poi-label',
        ]) {
            expect(ids).toContain(id);
        }
    });

    test('every symbol layer carries a text-field and a shipped font', async ({ page }) => {
        const style = await buildStyle(page);
        const symbols = style.layers.filter((l) => l.type === 'symbol');

        expect(symbols.length).toBeGreaterThan(0);

        for (const layer of symbols) {
            expect(layer.layout['text-field'], `${layer.id} has no text-field`).toBeTruthy();
            expect(['Noto Sans Regular', 'Noto Sans Medium']).toContain(layer.layout['text-font'][0]);
        }
    });

    test('labels read the source layers the archive publishes', async ({ page }) => {
        const style = await buildStyle(page);
        const archive = ['boundaries', 'buildings', 'earth', 'landcover', 'landuse', 'places', 'pois', 'roads', 'water'];

        for (const layer of style.layers.filter((l) => l.type === 'symbol')) {
            expect(archive, `${layer.id} reads a layer the archive lacks`).toContain(layer['source-layer']);
        }
    });

    test('street names come from roads and place names from places', async ({ page }) => {
        const style = await buildStyle(page);
        const byId = Object.fromEntries(style.layers.map((l) => [l.id, l]));

        expect(byId['road-label-minor']['source-layer']).toBe('roads');
        expect(byId['road-label-minor'].layout['symbol-placement']).toBe('line');

        expect(byId['place-label-locality']['source-layer']).toBe('places');
        expect(byId['place-label-locality'].minzoom).toBeLessThanOrEqual(6);
    });

    test('no sprite and no third-party URL anywhere in the style', async ({ page }) => {
        const style = await buildStyle(page);
        const serialised = JSON.stringify(style);

        expect(style.sprite).toBeUndefined();
        expect(serialised).not.toContain('protomaps.github.io');
        expect(serialised).not.toContain('googleapis');
        expect(serialised).not.toContain('mapbox');
    });

    test('the label-free style is still available and carries no glyph request', async ({ page }) => {
        const style = await buildStyle(page, { labels: false });

        expect(style.layers.some((l) => l.type === 'symbol')).toBe(false);

        // The geometry layers are untouched by the labels decision.
        expect(style.layers.map((l) => l.id)).toEqual(
            expect.arrayContaining(['background', 'earth', 'landuse', 'water', 'roads', 'boundaries'])
        );
    });
});

test.describe('the renderer survives labels being unavailable', () => {
    /**
     * Boot with an archive URL and glyphs that cannot be served.
     *
     * Both are refused by the network guard, which is the realistic version of
     * the failure: R2 without CORS, or a deploy that shipped the style and not
     * the fonts.
     */
    async function bootWithBrokenGlyphs(page) {
        await page.goto('/renderer-harness.html');
        await page.click('#reveal-tab');
        await page.evaluate(() => window.LDNA.boot({
            fake: true,
            pmtilesUrl: 'https://example.invalid/basemap.pmtiles',
            state: {
                polygons: [{ label: 'Downtown', path: [{ lat: 27.77, lng: -82.64 }, { lat: 27.79, lng: -82.64 }, { lat: 27.79, lng: -82.61 }] }],
                radius_searches: [{ lat: 27.7676, lng: -82.6403, radius_miles: 5, address: '100 Central Ave' }],
                important_places: [{ type: 'School', address: 'x', lat: 27.9, lng: -82.79, distance_pref: 'miles', distance_value: 1 }],
            },
        }));
        await expect.poll(() => page.evaluate(() => window.LDNA.sourceFeatureCount('ldna-overlays'))).not.toBeNull();
    }

    test('a 404 on every glyph range does not stop the map initialising', async ({ page }) => {
        // Fonts refused outright.
        await page.route('**/fonts/**', (route) => route.fulfill({ status: 404, body: '' }));

        await bootWithBrokenGlyphs(page);

        expect(await page.evaluate(() => !!window.LDNA.renderer.getMap())).toBe(true);
        expect(await page.evaluate(() => window.LDNA.renderer.isHydrated())).toBe(true);
    });

    test('geometry still paints when glyphs are missing', async ({ page }) => {
        await page.route('**/fonts/**', (route) => route.fulfill({ status: 404, body: '' }));

        await bootWithBrokenGlyphs(page);

        // The stored polygon and circle are on the map, and the place pin and its
        // ring are too. Labels are the optional part; this is not.
        expect(await page.evaluate(() => window.LDNA.sourceFeatureCount('ldna-overlays'))).toBe(2);
        expect(await page.evaluate(() => window.LDNA.sourceFeatureCount('ldna-place-rings'))).toBe(1);
        await expect(page.locator('.ldna-place-pin')).toHaveCount(1);
    });

    test('geometry is never reported as empty because of a font failure', async ({ page }) => {
        await page.route('**/fonts/**', (route) => route.fulfill({ status: 404, body: '' }));

        await bootWithBrokenGlyphs(page);

        const state = await page.evaluate(() => window.LDNA.renderer.getState());

        // The PR #124 contract, restated for this failure mode: nothing about a
        // missing font may make the host serialise `"polygons":[]` over stored
        // shapes.
        expect(state.polygons).toHaveLength(1);
        expect(state.radius_searches).toHaveLength(1);
    });

    test('the map is still editable with no fonts', async ({ page }) => {
        await page.route('**/fonts/**', (route) => route.fulfill({ status: 404, body: '' }));

        await bootWithBrokenGlyphs(page);

        await page.evaluate(() => window.LDNA.renderer.addRadiusSearch({
            lat: 27.8, lng: -82.7, radius_miles: 2, address: 'Added while unlabelled',
        }));

        const state = await page.evaluate(() => window.LDNA.renderer.getState());
        expect(state.radius_searches).toHaveLength(2);
    });
});

test.describe('labels on a real GL map', () => {
    test('the style loads into real MapLibre without being rejected', async ({ page }) => {
        await page.goto('/renderer-harness.html');

        // MapLibre validates a style when it accepts one: an unknown layer type,
        // a malformed filter or an expression that cannot be parsed is an error
        // at load, not a blank label. That validation is the part worth running
        // against the real library, and it needs WebGL.
        test.skip(!(await hasWebgl2(page)), 'no WebGL2 in this container — real MapLibre cannot be instantiated');

        await page.click('#reveal-tab');

        const errors = await page.evaluate(async () => {
            const mod = await import('/js/spatial/ldna-basemap.js');
            const maplibregl = await import('/vendor/maplibre-gl/dist/maplibre-gl.mjs');
            const style = mod.buildBasemapStyle('https://example.invalid/x.pmtiles', 'attr', 15);

            return new Promise((resolve) => {
                const collected = [];
                const map = new maplibregl.Map({
                    container: document.getElementById('ldna-map'),
                    style: { ...style, sources: {}, layers: style.layers.filter((l) => l.type === 'background') },
                });
                map.on('error', (e) => collected.push(String(e && e.error ? e.error.message : e)));
                map.once('style.load', () => resolve(collected));
                setTimeout(() => resolve(collected), 4000);
            });
        });

        expect(errors).toEqual([]);
    });
});
