/*
 |-----------------------------------------------------------------------------
 | The renderer against REAL maplibre-gl — the pixel-level half
 |-----------------------------------------------------------------------------
 |
 | Everything here needs a real WebGL2 context, which is the one thing the rest of
 | the suite deliberately avoids depending on. These are the assertions that no
 | substitute can make: that MapLibre itself accepts our style, builds a canvas
 | with real dimensions, and recovers from being measured inside a hidden tab.
 |
 | THEY SKIP WHERE WEBGL IS ABSENT, LOUDLY. This project's Replit container
 | exposes no WebGL — SwiftShader ships with the bundled Chromium but never
 | initialises, under every documented flag combination. A skip states that
 | plainly; it does not quietly shrink the green number.
 |
 | On a GitHub Actions ubuntu runner, and on any developer machine with a GPU,
 | these run. Treat a run where they skipped as INCOMPLETE verification of the
 | basemap, and say so before enabling the flag anywhere.
 */

const { test, expect } = require('@playwright/test');
const { installNetworkGuard } = require('./support/network');
const { hasWebgl2 } = require('./support/capabilities');

test.describe('real MapLibre rendering', () => {
    test.beforeEach(async ({ page }) => {
        await installNetworkGuard(page);
        await page.goto('/renderer-harness.html');

        test.skip(
            !(await hasWebgl2(page)),
            'No WebGL2 in this container — MapLibre cannot construct a map. '
            + 'This is an environment limitation, not a passing test: basemap rendering is UNVERIFIED here.'
        );
    });

    test('builds a canvas with real dimensions once the tab is revealed', async ({ page }) => {
        await page.click('#reveal-tab');
        await page.evaluate(() => window.LDNA.boot({ state: {} }));

        await expect.poll(async () => {
            const size = await page.evaluate(() => window.LDNA.canvasSize());
            return !!(size && size.width > 0 && size.height > 0);
        }, { timeout: 15_000 }).toBe(true);
    });

    test('recovers from being initialised inside a hidden tab', async ({ page }) => {
        // Initialise while the pane is still display:none — the exact condition
        // that leaves a zero-sized canvas that never recovers on its own.
        await page.evaluate(() => window.LDNA.boot({ state: {} }));
        await page.click('#reveal-tab');
        await page.evaluate(() => window.LDNA.renderer.resize());

        await expect.poll(async () => {
            const size = await page.evaluate(() => window.LDNA.canvasSize());
            return !!(size && size.width > 100 && size.height > 100);
        }, { timeout: 15_000 }).toBe(true);
    });

    test('accepts the blank style and stays usable with no archive configured', async ({ page }) => {
        await page.click('#reveal-tab');
        await page.evaluate(() => window.LDNA.boot({ state: { polygons: [{ label: 'A', path: [{ lat: 27.7, lng: -82.6 }, { lat: 27.8, lng: -82.6 }, { lat: 27.8, lng: -82.5 }] }] } }));

        await expect.poll(() => page.evaluate(() => window.LDNA.sourceFeatureCount('ldna-overlays'))).toBe(1);
        expect(await page.evaluate(() => window.LDNA.renderer.isHydrated())).toBe(true);
    });
});
