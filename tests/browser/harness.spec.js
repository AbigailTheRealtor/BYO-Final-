/*
 |-----------------------------------------------------------------------------
 | Phase 1A — the harness proves itself before it is trusted
 |-----------------------------------------------------------------------------
 |
 | A test harness that has never been shown to FAIL is not evidence of anything.
 | The suite this repository lacked would have caught a dead map; a suite that
 | reports green against a dead map is worse than none, because it converts an
 | unknown into a false assurance.
 |
 | So this file does two things: it establishes the four baseline capabilities the
 | harness must have, and then it deliberately breaks each contract those
 | capabilities rest on and proves the harness notices.
 |
 | The negative proofs are written to be environment-independent, because the one
 | that needs a GPU cannot run in this container (see renderer-gl.spec.js). A proof
 | that only holds where WebGL exists would be absent exactly where it is most
 | needed.
 */

const { test, expect } = require('@playwright/test');
const { installNetworkGuard } = require('./support/network');

test.describe('browser harness baseline', () => {
    test('loads a Location DNA host fixture and evaluates its module graph', async ({ page }) => {
        await installNetworkGuard(page);
        await page.goto('/renderer-harness.html');

        // Capability 1: the fixture loads and its ES modules evaluate. This is not
        // trivial — the first version of this harness failed here, because the
        // pmtiles ESM build imports a bare specifier a browser cannot resolve.
        await expect.poll(() => page.evaluate(() => window.__ldnaHarnessReady === true)).toBe(true);
    });

    test('executes the shared renderer source as written', async ({ page }) => {
        await installNetworkGuard(page);
        await page.goto('/renderer-harness.html');

        // Capability 2: the renderer under test is the real module served from
        // resources/js/spatial, and its factory is callable from the page.
        const booted = await page.evaluate(() => window.LDNA.boot({ fake: true, state: {} }));
        expect(booted).toBe(true);
        expect(await page.evaluate(() => typeof window.LDNA.renderer.init)).toBe('function');
    });

    test('detects whether the map initialises', async ({ page }) => {
        await installNetworkGuard(page);
        await page.goto('/renderer-harness.html');
        await page.click('#reveal-tab');
        await page.evaluate(() => window.LDNA.boot({ fake: true, state: {} }));

        // Capability 3: initialisation is OBSERVABLE. A map object exists and the
        // renderer's own sources have been created on it — which is what "the map
        // came up" actually means for this widget.
        await expect.poll(() => page.evaluate(() => window.LDNA.sourceFeatureCount('ldna-overlays'))).not.toBeNull();
        expect(await page.evaluate(() => !!window.LDNA.renderer.getMap())).toBe(true);
    });

    test('intercepts external network requests and blocks forbidden hosts', async ({ page }) => {
        const record = await installNetworkGuard(page);
        await page.goto('/renderer-harness.html');

        // Capability 4: interception is real. Proven by attempting a request the
        // guard must stop, rather than by trusting that none occurs.
        const outcome = await page.evaluate(async () => {
            try {
                await fetch('https://maps.googleapis.com/maps/api/js?key=probe');
                return 'reached';
            } catch (e) {
                return 'blocked';
            }
        });

        expect(outcome).toBe('blocked');
        expect(record.forbidden.some((u) => u.includes('googleapis.com'))).toBe(true);
    });
});

test.describe('deliberate-negative proofs', () => {
    /*
     | Each of these breaks one contract the baseline above depends on. If the
     | harness cannot tell the difference, the corresponding green result upstairs
     | means nothing.
     */

    test('FAILS to see an initialised map when initialisation never runs', async ({ page }) => {
        await installNetworkGuard(page);
        await page.goto('/renderer-harness.html');
        await page.click('#reveal-tab');

        // Break it: the renderer is constructed but init() is never called — the
        // shape of a widget whose container stayed hidden forever.
        await page.evaluate(() => window.LDNA.boot({ fake: true, autoInit: false, state: {} }));
        await page.waitForTimeout(300);

        expect(await page.evaluate(() => window.LDNA.renderer.getMap())).toBeNull();
        expect(await page.evaluate(() => window.LDNA.sourceFeatureCount('ldna-overlays'))).toBeNull();
    });

    test('FAILS to see geometry when hydration never runs — the PR #124 boundary', async ({ page }) => {
        await installNetworkGuard(page);
        await page.goto('/renderer-harness.html');
        await page.click('#reveal-tab');

        // Boot WITHOUT hydrating. "No overlays yet" and "the user has no overlays"
        // are different facts, and only one of them is safe to serialise.
        await page.evaluate(() => window.LDNA.boot({ fake: true, hydrate: false, state: {} }));
        await page.waitForTimeout(300);

        expect(await page.evaluate(() => window.LDNA.renderer.isHydrated())).toBe(false);
        expect(await page.evaluate(() => window.LDNA.events.length)).toBe(0);
    });

    test('FAILS to see rendered geometry when the overlay source is not populated', async ({ page }) => {
        await installNetworkGuard(page);
        await page.goto('/renderer-harness.html');
        await page.click('#reveal-tab');
        await page.evaluate(() => window.LDNA.boot({ fake: true, state: { polygons: [], radius_searches: [] } }));
        await expect.poll(() => page.evaluate(() => window.LDNA.sourceFeatureCount('ldna-overlays'))).not.toBeNull();

        // The rendering assertions elsewhere count features in this source. Prove
        // that count actually tracks reality by asserting the empty case, rather
        // than only ever asserting non-zero.
        expect(await page.evaluate(() => window.LDNA.sourceFeatureCount('ldna-overlays'))).toBe(0);
    });

    test('the network guard would REPORT a forbidden host if one were contacted', async ({ page }) => {
        const record = await installNetworkGuard(page);
        await page.goto('/renderer-harness.html');

        // The "no Google requests" assertion elsewhere is only meaningful if the
        // recorder can see one. Contact a forbidden host on purpose and confirm it
        // is recorded, so the negative assertion is not vacuously true.
        await page.evaluate(() => fetch('https://nominatim.openstreetmap.org/search?q=probe').catch(() => {}));
        await expect.poll(() => record.forbidden.length).toBeGreaterThan(0);
    });
});
