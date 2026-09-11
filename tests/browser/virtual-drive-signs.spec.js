/*
 |-----------------------------------------------------------------------------
 | Virtual Drive — the sign-shopping experience, proven against fakes
 |-----------------------------------------------------------------------------
 |
 | The live session of 2026-09-11 showed the mechanics working and the EXPERIENCE
 | failing: a sign 132 m away rendered 25 px wide, neighbouring homes carried
 | identical wording, units sharing a condo coordinate stacked into one another,
 | and a click led to a developer panel rather than a listing card.
 |
 | These specs hold the fix without spending a Street View session: the real
 | shell, signs module and provider run against the fake Maps API, with every
 | request leaving the fixture origin aborted. The panorama ceiling is asserted
 | in every test that touches a sign, because a shopper card that rebuilt the
 | panorama would be a billing regression, not a UI one.
 */

const { test, expect } = require('@playwright/test');
const { installNetworkGuard } = require('./support/network');

const PROVIDER_HOSTS = ['googleapis.com', 'gstatic.com', 'google.com', 'apple-mapkit.com', 'apple.com'];

const fakeGoogle = (page) => page.evaluate(() => window.__fakeGoogle.counters());
const markers = (page) => page.evaluate(() => window.__fakeGoogle.markers());
const diagnostics = (page) => page.evaluate(() => JSON.parse(JSON.stringify(window.VirtualDriveDiagnostics)));

function expectNoProviderTraffic(record) {
    expect(record.forbidden).toEqual([]);
    expect(record.external.filter((url) => PROVIDER_HOSTS.some((host) => url.includes(host)))).toEqual([]);
}

async function open(page, query = '') {
    const record = await installNetworkGuard(page);

    await page.goto('/virtual-drive/google.html' + query);
    await expect(page.locator('#vd-launch')).toBeEnabled();

    return record;
}

async function launch(page) {
    await page.click('#vd-launch');
    await expect.poll(async () => (await fakeGoogle(page)).panoramas).toBe(1);
}

/*
 * Pick a home the way a person does, BEFORE launching. `?listing=` is applied by
 * the Blade template server-side (and validated there), so a static fixture page
 * ignores it; Previous / Next is the fixture's way to the same place, and the
 * launch guard already proves that browsing costs nothing.
 *
 * Fixture walk order: 0 Stones sale · 1 Stones rent · 2 Manasota A · 3 Manasota B
 * · 4-6 the three Siesta Bayside units on one coordinate.
 */
async function selectHome(page, steps) {
    for (let i = 0; i < steps; i += 1) {
        await page.click('#vd-next');
    }

    await expect.poll(async () => (await fakeGoogle(page)).panoramaConstructorCalls).toBe(0);
}

const signOf = (list, fragment) => list.find((m) => (m.title || '').includes(fragment));

test.describe('Virtual Drive · signs a shopper can read and click (fake Maps API, no network)', () => {
    test('the size model matches the measured live scaling and keeps every sign readable', async ({ page }) => {
        const record = await open(page);

        const result = await page.evaluate(() => {
            const S = window.VirtualDriveSigns;
            const viewport = 960; // the live session's panorama width
            const at = (d) => {
                const icon = S.iconWidth(d, viewport);

                return { d: d, icon: icon, onScreen: icon ? Math.round(S.modelledScreenWidth(icon, d, viewport)) : 0, want: S.screenWidth(d) };
            };

            return {
                sizes: [19, 35, 36, 49, 132, 160].map(at),
                hiddenTooFar: S.iconWidth(200, viewport),
                hiddenTooClose: S.iconWidth(3, viewport),
                // The live session measured a 168 px icon rendering at these widths.
                calibration: [[19, 165], [36, 91], [49, 67], [132, 25]]
                    .map(([d, measured]) => ({ d, measured, modelled: Math.round(S.modelledScreenWidth(168, d, viewport)) })),
                defaults: S.DEFAULTS,
            };
        });

        // Every sign inside useful range lands between the floor and the ceiling.
        for (const size of result.sizes) {
            expect(size.icon, `an icon is chosen at ${size.d} m`).toBeGreaterThan(0);
            expect(size.onScreen, `sign at ${size.d} m is at least the minimum`).toBeGreaterThanOrEqual(result.defaults.farWidth - 1);
            expect(size.onScreen, `sign at ${size.d} m is no larger than the maximum`).toBeLessThanOrEqual(result.defaults.nearWidth + 1);
            expect(Math.abs(size.onScreen - size.want), `sign at ${size.d} m matches its target`).toBeLessThanOrEqual(2);
        }

        // Closer is bigger, all the way out.
        const widths = result.sizes.map((s) => s.onScreen);

        expect(widths[0]).toBeGreaterThan(widths[widths.length - 1]);

        // Out of range: hidden, never a dot.
        expect(result.hiddenTooFar).toBe(0);
        expect(result.hiddenTooClose).toBe(0);

        // The model still describes what Google actually did in the live session.
        for (const point of result.calibration) {
            expect(Math.abs(point.modelled - point.measured) / point.measured, `${point.d} m`).toBeLessThan(0.12);
        }

        expectNoProviderTraffic(record);
    });

    test('each home carries its own number, label and price, and far signs are hidden rather than shrunk', async ({ page }) => {
        const record = await open(page, '?offset=35');

        await selectHome(page, 2); // Manasota Key A
        await launch(page);
        await page.waitForTimeout(200);

        const list = await markers(page);
        const a = signOf(list, 'Manasota Key A');
        const b = signOf(list, 'Manasota Key B');

        // One sign per place: two houses, two condo-free homes far away, one building.
        expect(list).toHaveLength(5);

        expect(a.text).toEqual(['6590', 'FOR RENT', '$14,000/mo']);
        expect(b.text).toEqual(['6580', 'FOR RENT', '$14,000/mo']);

        // Neighbours 33 m apart are told apart by their numbers, not by an outline.
        expect(a.text[0]).not.toBe(b.text[0]);
        expect(a.visible).toBe(true);
        expect(b.visible).toBe(true);

        // The sizes are the ones the model asks for at those distances.
        const expected = await page.evaluate(() => {
            const S = window.VirtualDriveSigns;
            const width = document.getElementById('vd-street').clientWidth;
            const here = window.__fakeGoogle.lastPanorama().getPosition();
            const at = (lat, lng) => S.iconWidth(S.meters({ lat: here.lat(), lng: here.lng() }, { lat, lng }), width);

            return { a: at(26.960258, -82.38292), b: at(26.959994, -82.382761), width };
        });

        expect(a.iconWidth).toBe(expected.a);
        expect(b.iconWidth).toBe(expected.b);
        expect(a.iconHeight).toBe(Math.round(a.iconWidth * 124 / 200));

        // The homes 98 km away are hidden, not drawn as dots.
        expect(signOf(list, 'Stones Throw sale').visible).toBe(false);
        expect(signOf(list, 'Siesta Bayside').visible).toBe(false);

        // Walk away: the near signs go out of range and disappear.
        await page.evaluate(() => {
            for (let i = 0; i < 12; i += 1) window.__fakeGoogle.lastPanorama().__userWalk(0.00018, 0);
        });
        await page.waitForTimeout(200);

        const afterWalk = await markers(page);

        expect(signOf(afterWalk, 'Manasota Key A').visible).toBe(false);
        expect((await fakeGoogle(page)).panoramaConstructorCalls).toBe(1);
        expectNoProviderTraffic(record);
    });

    test('clicking a sign opens that listing as a shopper card, and another sign switches it — one panorama throughout', async ({ page }) => {
        const record = await open(page, '?offset=35');

        await selectHome(page, 2); // Manasota Key A
        await launch(page);
        await page.waitForTimeout(200);

        const card = page.locator('#vd-shopper');

        await expect(card).toBeVisible();

        const list = await markers(page);
        const indexOf = (fragment) => list.findIndex((m) => (m.title || '').includes(fragment));

        await page.evaluate((i) => window.__fakeGoogle.clickMarker(i), indexOf('Manasota Key B'));
        await expect(card).toHaveAttribute('data-listing', 'FX-RENT-MANASOTA-B');
        await expect(card).toContainText('$14,000/mo');
        await expect(card).toContainText('6580 FIXTURE Manasota Key B');
        await expect(card).toContainText('2 bd · 3 ba');
        await expect(card.locator('.vd-shopper-photo img')).toBeVisible();

        // Only actions that exist: this listing has photos and a tour, no Details.
        await expect(card.locator('.vd-shopper-action-tour')).toBeVisible();
        await expect(card.locator('.vd-shopper-action-details')).toHaveCount(0);
        await expect(card.locator('.vd-shopper-action-save')).toHaveCount(0);

        // Photos open from the card.
        await card.locator('.vd-shopper-action-photos').click();
        await expect(page.locator('#vd-lightbox')).toBeVisible();
        await page.click('#vd-lightbox-close');

        await page.evaluate((i) => window.__fakeGoogle.clickMarker(i), indexOf('Manasota Key A'));
        await expect(card).toHaveAttribute('data-listing', 'FX-RENT-MANASOTA-A');
        await expect(card).toContainText('6590 FIXTURE Manasota Key A');

        // The selected sign is the one the camera is aimed at, and it is outlined.
        const selected = (await markers(page)).filter((m) => m.selectedOutline).map((m) => m.title);

        expect(selected).toHaveLength(1);
        expect(selected[0]).toContain('Manasota Key A');

        const counters = await fakeGoogle(page);

        expect(counters.panoramaConstructorCalls).toBe(1);
        expect(counters.setPano).toBe(0); // a card is DOM: it never moves the camera

        await page.locator('.vd-shopper-close').click();
        await expect(card).toBeHidden();
        expect((await diagnostics(page)).google.panoramaConstructions).toBe(1);
        expectNoProviderTraffic(record);
    });

    test('units sharing a building coordinate get one building sign and a unit chooser', async ({ page }) => {
        const record = await open(page, '?offset=25');

        await selectHome(page, 4); // the first Siesta Bayside unit
        await launch(page);
        await page.waitForTimeout(200);

        const list = await markers(page);
        const building = list.filter((m) => (m.title || '').includes('Siesta Bayside'));

        // Three units, one sign — not three stacked on one point.
        expect(building).toHaveLength(1);
        expect(building[0].text).toEqual(['FOR RENT', '3 UNITS']);
        expect(building[0].visible).toBe(true);
        expect(building[0].title).toContain('3 units');

        const card = page.locator('#vd-shopper');
        const index = list.findIndex((m) => (m.title || '').includes('Siesta Bayside'));

        await page.evaluate((i) => window.__fakeGoogle.clickMarker(i), index);
        await expect(card).toContainText('3 units in this building');
        await expect(card.locator('.vd-shopper-unit')).toHaveCount(3);
        await expect(card.locator('.vd-shopper-unit').first()).toContainText('Unit 1226-C');

        await card.locator('.vd-shopper-unit').nth(1).click();
        await expect(card).toHaveAttribute('data-listing', 'FX-CONDO-2');
        await expect(card).toContainText('$6,300/mo');

        // …and back to the other units in the same building.
        await card.locator('.vd-shopper-back').click();
        await expect(card.locator('.vd-shopper-unit')).toHaveCount(3);

        const counters = await fakeGoogle(page);

        expect(counters.panoramaConstructorCalls).toBe(1);
        expect(counters.setPano).toBe(0);
        expectNoProviderTraffic(record);
    });

    test('imagery that is only near the home says so, and near imagery does not', async ({ page }) => {
        const record = await open(page, '?offset=132'); // the fixture opens on Stones Throw

        await launch(page);

        const status = page.locator('#vd-imagery-status');

        await expect(status).toHaveClass(/is-far/);
        await expect(status).toContainText('not directly at this property');
        await expect(status).toContainText('132 m away');
        await expect(page.locator('.vd-shopper-coverage')).toContainText('not directly at this property');

        await page.goto('/virtual-drive/google.html?offset=20');
        await expect(page.locator('#vd-launch')).toBeEnabled();
        await launch(page);

        await expect(page.locator('#vd-imagery-status')).not.toHaveClass(/is-far/);
        await expect(page.locator('.vd-shopper-coverage')).toHaveCount(0);
        expectNoProviderTraffic(record);
    });

    test('customer preview shows the experience and hides the instrumentation', async ({ page }) => {
        const record = await open(page, '?offset=35&view=customer');

        await selectHome(page, 2); // Manasota Key A
        await launch(page);
        await page.waitForTimeout(200);

        await expect(page.locator('#vd-shopper')).toBeVisible();
        await expect(page.locator('.vd-log')).toBeHidden();
        await expect(page.locator('#vd-observations')).toBeHidden();
        await expect(page.locator('#vd-card-body')).toBeHidden();
        await expect(page.locator('.vd-nearby')).toBeHidden();

        // What a shopper still needs: the homes walk, the attribution, the aim control.
        await expect(page.locator('#vd-next')).toBeVisible();
        await expect(page.locator('#vd-attribution')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Face the selected home' })).toBeVisible();
        await expect(page.locator('#vd-shopper .vd-shopper-attribution')).toContainText('FIXTURE DATA');

        expect((await fakeGoogle(page)).panoramaConstructorCalls).toBe(1);
        expectNoProviderTraffic(record);
    });

    test('driving re-asks OUR endpoint for nearby homes and never a provider', async ({ page }) => {
        const record = await open(page, '?offset=35');

        await selectHome(page, 2); // Manasota Key A
        await launch(page);

        await page.evaluate(() => {
            for (let i = 0; i < 8; i += 1) window.__fakeGoogle.lastPanorama().__userWalk(0.0003, 0);
        });
        await page.waitForTimeout(500);

        const counters = await page.evaluate(() => {
            const out = {};

            document.querySelectorAll('#vd-counters dt').forEach((dt) => { out[dt.textContent] = dt.nextElementSibling.textContent; });

            return out;
        });

        expect(Number(counters['Listing API calls (our stored MLS data)'])).toBeGreaterThan(1);
        expect(counters['Bridge/Stellar requests caused']).toBe('0');
        expect((await fakeGoogle(page)).panoramaConstructorCalls).toBe(1);
        expect(record.external.filter((url) => !url.startsWith('http://127.0.0.1:8931'))).toEqual([]);
        expectNoProviderTraffic(record);
    });
});
