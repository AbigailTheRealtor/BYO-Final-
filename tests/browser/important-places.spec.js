/*
 |-----------------------------------------------------------------------------
 | Important Places: a pin each, and a ring only where a ring is honest
 |-----------------------------------------------------------------------------
 |
 | The rule under test is one sentence and it is a promise the widget makes on
 | screen: a "within N miles" place gets a pin AND a radius ring; a "within N
 | minutes" place gets a pin and nothing else.
 |
 | That distinction cannot be checked by reading the source. Both rows produce a
 | pin, both carry a number, and the only difference is one string — so the
 | failure mode is a minutes row silently drawing a ten-mile circle that looks
 | entirely plausible and asserts a reachable area no routing engine here has
 | ever computed. This suite watches what actually lands on the map.
 |
 | It also pins the key that decides it. `distance_pref` is canonical;
 | `distance_preference` and `distpref` are legacy spellings kept as fallbacks.
 | A row carrying the canonical key must never be read through a fallback.
 */

const { test, expect } = require('@playwright/test');
const { installNetworkGuard } = require('./support/network');

const PLACES = [
    // Canonical key, miles → pin + ring.
    { type: 'School', address: '11687 Oxford St N', lat: 27.90, lng: -82.79, distance_pref: 'miles', distance_value: 1, travel_mode: 'driving' },
    // Canonical key, minutes → pin only.
    { type: 'Work', address: '200 Central Ave', lat: 27.77, lng: -82.64, distance_pref: 'minutes', distance_value: 25, travel_mode: 'driving' },
    // Legacy spelling, miles → still a ring; the fallback is deliberate.
    { type: 'Gym', address: '5 Beach Dr', lat: 27.78, lng: -82.63, distance_preference: 'miles', distance_value: 3, travel_mode: 'walking' },
    // Miles with no usable figure → pin, no ring. A ring needs a radius, and
    // zero is not one.
    { type: 'Family', address: '9 Elsewhere Rd', lat: 27.60, lng: -82.70, distance_pref: 'miles', distance_value: 0, travel_mode: 'driving' },
    // No coordinate at all → nothing. Never a pin at 0,0.
    { type: 'Other', address: 'Not located yet', distance_pref: 'miles', distance_value: 2, travel_mode: 'driving' },
];

async function boot(page, places) {
    await page.goto('/renderer-harness.html');
    await page.click('#reveal-tab');
    await page.evaluate((p) => window.LDNA.boot({ fake: true, state: { important_places: p } }), places);
    await expect.poll(() => page.evaluate(() => window.LDNA.sourceFeatureCount('ldna-overlays'))).not.toBeNull();
}

test.beforeEach(async ({ page }) => {
    await installNetworkGuard(page);
});

test.describe('important place pins', () => {
    test('every located place gets a pin, and an unlocated one gets none', async ({ page }) => {
        await boot(page, PLACES);

        // Four of the five rows carry coordinates. The fifth is a row the user
        // has typed but not yet located; it is saved, and it has no pin.
        await expect(page.locator('.ldna-place-pin')).toHaveCount(4);
    });

    test('a pin carries its place type and distance preference for styling', async ({ page }) => {
        await boot(page, PLACES);

        const first = page.locator('.ldna-place-pin').first();

        await expect(first).toHaveAttribute('data-place-type', 'School');
        await expect(first).toHaveAttribute('data-distance-preference', 'miles');
    });

    test('a minutes row is still a pin, with its preference reported honestly', async ({ page }) => {
        await boot(page, PLACES);

        const minutes = page.locator('.ldna-place-pin[data-distance-preference="minutes"]');

        await expect(minutes).toHaveCount(1);
        await expect(minutes).toHaveAttribute('data-place-type', 'Work');
    });
});

test.describe('important place rings', () => {
    test('a miles row draws a ring', async ({ page }) => {
        await boot(page, PLACES);

        const rings = await page.evaluate(() => window.LDNA.sourceFeatures('ldna-place-rings'));

        // Two: the canonical-key miles row and the legacy-spelling miles row.
        expect(rings).toHaveLength(2);
        expect(rings.every((f) => f.properties.ldnaType === 'important_place_ring')).toBe(true);
        expect(rings.every((f) => f.geometry.type === 'Polygon')).toBe(true);
    });

    test('a minutes row draws NO ring', async ({ page }) => {
        await boot(page, [PLACES[1]]);

        const rings = await page.evaluate(() => window.LDNA.sourceFeatures('ldna-place-rings'));

        // The whole point. Twenty-five minutes is not a distance, and drawing it
        // as one would be a claim nothing here can support.
        expect(rings).toHaveLength(0);

        // But the place is still on the map.
        await expect(page.locator('.ldna-place-pin')).toHaveCount(1);
    });

    test('a zero or missing radius draws no ring', async ({ page }) => {
        await boot(page, [PLACES[3]]);

        expect(await page.evaluate(() => window.LDNA.sourceFeatures('ldna-place-rings'))).toHaveLength(0);
        await expect(page.locator('.ldna-place-pin')).toHaveCount(1);
    });

    test('the ring is sized from the stored miles figure', async ({ page }) => {
        await boot(page, [PLACES[0]]);

        const ring = (await page.evaluate(() => window.LDNA.sourceFeatures('ldna-place-rings')))[0];
        expect(ring.properties.distanceValue).toBe(1);

        // One mile is about 0.0145 degrees of latitude. Asserting the extent
        // rather than the property is what proves the geometry was actually
        // projected rather than merely labelled.
        const lats = ring.geometry.coordinates[0].map((c) => c[1]);
        const spanDegrees = Math.max(...lats) - Math.min(...lats);

        expect(spanDegrees).toBeGreaterThan(0.024);
        expect(spanDegrees).toBeLessThan(0.036);
    });
});

test.describe('rings never become search areas', () => {
    test('a place ring is not reported as a radius search', async ({ page }) => {
        await boot(page, PLACES);

        // THE STORAGE-SAFETY ASSERTION. `radius_searches` is what the host
        // serialises into the listing. A ring that leaked into it would be
        // written back as a search area the user never drew and would then be
        // indistinguishable from one they did.
        const state = await page.evaluate(() => window.LDNA.renderer.getState());

        expect(state.radius_searches).toHaveLength(0);
        expect(state.polygons).toHaveLength(0);
    });

    test('rings live in their own source, separate from user geometry', async ({ page }) => {
        await boot(page, PLACES);

        const overlays = await page.evaluate(() => window.LDNA.sourceFeatures('ldna-overlays'));

        expect(overlays.every((f) => f.properties.ldnaType !== 'important_place_ring')).toBe(true);
    });
});

test.describe('regression: the rest of the map still works', () => {
    test('places do not disturb stored polygons and circles', async ({ page }) => {
        await page.goto('/renderer-harness.html');
        await page.click('#reveal-tab');
        await page.evaluate((p) => window.LDNA.boot({
            fake: true,
            state: {
                polygons: [{ label: 'Downtown', path: [{ lat: 27.77, lng: -82.64 }, { lat: 27.79, lng: -82.64 }, { lat: 27.79, lng: -82.61 }] }],
                radius_searches: [{ lat: 27.7676, lng: -82.6403, radius_miles: 5, address: '100 Central Ave' }],
                important_places: p,
            },
        }), PLACES);
        await expect.poll(() => page.evaluate(() => window.LDNA.sourceFeatureCount('ldna-overlays'))).not.toBeNull();

        const state = await page.evaluate(() => window.LDNA.renderer.getState());

        expect(state.polygons).toHaveLength(1);
        expect(state.radius_searches).toHaveLength(1);
        expect(state.radius_searches[0].address).toBe('100 Central Ave');
    });
});
