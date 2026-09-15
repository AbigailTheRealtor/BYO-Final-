/*
 |-----------------------------------------------------------------------------
 | Virtual Drive — one live distance for shoppers, two labelled ones for developers
 |-----------------------------------------------------------------------------
 |
 | The live Google session showed, side by side:
 |
 |   "Selected home: 116 m away, straight ahead."
 |   "Street View imagery is 35 m from the home. Camera turned to heading N° to face it."
 |
 | Both were true, and they were different measurements. The first is LIVE:
 | panorama.getPosition() → the selected listing's MLS coordinate, recomputed on
 | position_changed / pov_changed. The second was a SNAPSHOT of the panorama
 | matched when the home was opened, never refreshed once the camera walked.
 | Unlabelled, the snapshot read as a contradiction.
 |
 | These specs hold the fix against the fake Maps API with every request leaving
 | the fixture origin aborted: no Street View session, no launch claim, no key.
 |
 | Fixture walk order: 0 Stones sale · 1 Stones rent · 2 Manasota A (6590) ·
 | 3 Manasota B (6580, 33 m away) · 4-6 Siesta Bayside units.
 */

const { test, expect } = require('@playwright/test');
const { installNetworkGuard } = require('./support/network');

const PROVIDER_HOSTS = ['googleapis.com', 'gstatic.com', 'google.com', 'apple-mapkit.com', 'apple.com'];

// The fixture's MLS coordinates. The signs spec pins the same values.
const MANASOTA_A = { lat: 26.960258, lng: -82.38292 };
const MANASOTA_B = { lat: 26.959994, lng: -82.382761 };

// Wording that belongs to the developer view only.
const DEVELOPER_ONLY = [
    'Street View imagery is',
    'Initial Street View match',
    'Camera turned to heading',
    'Requested initial heading',
    'Current panorama distance',
    'bearing to listing',
    'last requested',
];

const fakeGoogle = (page) => page.evaluate(() => window.__fakeGoogle.counters());

function expectNoProviderTraffic(record) {
    expect(record.forbidden).toEqual([]);
    expect(record.external.filter((url) => PROVIDER_HOSTS.some((host) => url.includes(host)))).toEqual([]);
}

async function openOnManasotaA(page, query) {
    const record = await installNetworkGuard(page);

    await page.goto('/virtual-drive/google.html' + query);
    await expect(page.locator('#vd-launch')).toBeEnabled();

    for (let i = 0; i < 2; i += 1) {
        await page.click('#vd-next');
    }

    await page.click('#vd-launch');
    await expect.poll(async () => (await fakeGoogle(page)).panoramas).toBe(1);
    await expect(page.locator('#vd-hud')).toBeVisible();

    return record;
}

// The live figure, computed independently of the code under test.
async function liveMeters(page, target) {
    return page.evaluate((t) => {
        const here = window.__fakeGoogle.lastPanorama().getPosition();

        return Math.round(window.VirtualDriveSigns.meters({ lat: here.lat(), lng: here.lng() }, t));
    }, target);
}

const cameraPosition = (page) => page.evaluate(() => {
    const p = window.__fakeGoogle.lastPanorama().getPosition();

    return { lat: p.lat(), lng: p.lng() };
});

const walkNorth = (page, degreesLat) => page.evaluate((d) => window.__fakeGoogle.lastPanorama().__userWalk(d, 0), degreesLat);

// What a person can actually read: innerText skips display:none (the hidden log).
const visibleText = (page) => page.evaluate(() => document.body.innerText);

test.describe('Virtual Drive · status messaging (fake Maps API, no network)', () => {
    test('customer preview: live selected-home distance only — no initial-match figure, no headings', async ({ page }) => {
        const record = await openOnManasotaA(page, '?offset=35&view=customer');
        const hud = page.locator('#vd-hud');
        const status = page.locator('#vd-imagery-status');

        // Near imagery: nothing technical to say, so the status says nothing.
        await expect(hud).toHaveText(`Selected home: ${await liveMeters(page, MANASOTA_A)} m away, straight ahead.`);
        await expect(status).toHaveText('');
        await expect(status).toBeHidden();

        for (const phrase of DEVELOPER_ONLY) {
            expect(await visibleText(page), `customer preview shows "${phrase}"`).not.toContain(phrase);
        }

        // Walk ~80 m — the live session's situation. The HUD follows the camera.
        await walkNorth(page, 0.00072);
        const walked = await liveMeters(page, MANASOTA_A);

        expect(walked).toBeGreaterThan(100);
        await expect(hud).toHaveText(`Selected home: ${walked} m away, straight ahead.`);

        // Turning is reported as a direction, live.
        await page.evaluate(() => window.__fakeGoogle.lastPanorama().__userRotate(90));
        await expect(hud).toHaveText(`Selected home: ${walked} m away, 90° to your left.`);

        // "Face the selected home" turns the camera and does not move it.
        const before = await cameraPosition(page);
        const setPanoBefore = (await fakeGoogle(page)).setPano;

        await page.getByRole('button', { name: 'Face the selected home' }).click();
        await expect(hud).toHaveText(`Selected home: ${walked} m away, straight ahead.`);
        expect(await cameraPosition(page)).toEqual(before);
        expect((await fakeGoogle(page)).setPano).toBe(setPanoBefore);

        for (const phrase of DEVELOPER_ONLY) {
            expect(await visibleText(page), `customer preview shows "${phrase}" after moving`).not.toContain(phrase);
        }

        expect((await fakeGoogle(page)).panoramaConstructorCalls).toBe(1);
        expectNoProviderTraffic(record);
    });

    test('customer preview: far imagery keeps the honest warning, without the stale figure', async ({ page }) => {
        const record = await installNetworkGuard(page);

        await page.goto('/virtual-drive/google.html?offset=132&view=customer'); // opens on Stones Throw
        await expect(page.locator('#vd-launch')).toBeEnabled();
        await page.click('#vd-launch');
        await expect.poll(async () => (await fakeGoogle(page)).panoramas).toBe(1);

        const status = page.locator('#vd-imagery-status');

        const warning = 'Street View is nearby, but this view is not directly in front of the property.';

        await expect(status).toHaveClass(/is-far/);
        await expect(status).toHaveText(warning);
        await expect(page.locator('.vd-shopper-coverage')).toHaveText(warning);

        for (const phrase of DEVELOPER_ONLY) {
            expect(await visibleText(page), `customer preview shows "${phrase}"`).not.toContain(phrase);
        }

        expectNoProviderTraffic(record);
    });

    test('developer view: the initial match is labelled as such and stays put while the current distance moves', async ({ page }) => {
        const record = await openOnManasotaA(page, '?offset=35');
        const hud = page.locator('#vd-hud');
        const status = page.locator('#vd-imagery-status');
        const initial = "Initial Street View match: 35 m from the selected listing's MLS coordinate.";

        await expect(status).toContainText(initial);
        await expect(status).toContainText('Requested initial heading 180°.');
        await expect(status).toContainText('Imagery captured 2024-03.');
        await expect(status).toContainText('Current panorama distance: 35 m from selected listing.');
        await expect(status).toContainText('Heading: camera 180°, bearing to listing 180°, last requested 180°.');
        await expect(status).not.toContainText('Street View imagery is');
        await expect(status).not.toContainText('Camera turned to heading');

        // Walk: the initial match is unchanged, the current distance and HUD agree.
        await walkNorth(page, 0.00072);
        const walked = await liveMeters(page, MANASOTA_A);

        await expect(status).toContainText(initial);
        await expect(status).toContainText(`Current panorama distance: ${walked} m from selected listing.`);
        await expect(hud).toHaveText(`Selected home: ${walked} m away, straight ahead.`);

        // A person turns away: the actual heading moves, the requested one does not.
        await page.evaluate(() => window.__fakeGoogle.lastPanorama().__userRotate(90));
        await expect(status).toContainText('Heading: camera 270°, bearing to listing 180°, last requested 180°.');

        // Face the selected home: requested and actual meet the bearing; nothing moves.
        const before = await cameraPosition(page);

        await page.getByRole('button', { name: 'Face the selected home' }).click();
        await expect(status).toContainText('Heading: camera 180°, bearing to listing 180°, last requested 180°.');
        await expect(status).toContainText(`Current panorama distance: ${walked} m from selected listing.`);
        expect(await cameraPosition(page)).toEqual(before);

        // Selecting the neighbour by its sign does not move the camera, so there is
        // no initial match for it — and the snapshot for 6590 is not passed off as one.
        const markers = await page.evaluate(() => window.__fakeGoogle.markers());

        await page.evaluate((i) => window.__fakeGoogle.clickMarker(i), markers.findIndex((m) => (m.title || '').includes('Manasota Key B')));
        await expect(page.locator('#vd-shopper')).toHaveAttribute('data-listing', 'FX-RENT-MANASOTA-B');
        await expect(status).toContainText('Initial Street View match: not measured for this listing');
        await expect(status).not.toContainText(initial);

        const toB = await liveMeters(page, MANASOTA_B);

        await expect(status).toContainText(`Current panorama distance: ${toB} m from selected listing.`);
        await expect(hud).toContainText(`Selected home: ${toB} m away`);

        const counters = await fakeGoogle(page);

        expect(counters.panoramaConstructorCalls).toBe(1);
        expect(counters.setPano).toBe(0);
        expectNoProviderTraffic(record);
    });

    test('developer view: far imagery is labelled as the initial match and as nearby only', async ({ page }) => {
        const record = await installNetworkGuard(page);

        await page.goto('/virtual-drive/google.html?offset=132');
        await expect(page.locator('#vd-launch')).toBeEnabled();
        await page.click('#vd-launch');
        await expect.poll(async () => (await fakeGoogle(page)).panoramas).toBe(1);

        const status = page.locator('#vd-imagery-status');

        await expect(status).toHaveClass(/is-far/);
        await expect(status).toContainText("Initial Street View match: 132 m from the selected listing's MLS coordinate. Nearby only — not directly at this property.");
        await expect(status).toContainText('Current panorama distance: 132 m from selected listing.');
        expectNoProviderTraffic(record);
    });

    test('position_changed still drives the HUD, the sign distances and the nearby list from getPosition()', async ({ page }) => {
        const record = await openOnManasotaA(page, '?offset=35');

        const signDistance = () => page.evaluate(() => {
            const google = window.VirtualDriveDiagnostics.google;
            const entry = google.signs.find((s) => s.id === 'FX-RENT-MANASOTA-A');

            return entry ? entry.distance : null;
        });

        expect(await signDistance()).toBe(await liveMeters(page, MANASOTA_A));

        const listingCallsBefore = await page.evaluate(() => Number(
            Array.from(document.querySelectorAll('#vd-counters dt'))
                .find((dt) => dt.textContent === 'Listing API calls (our stored MLS data)').nextElementSibling.textContent,
        ));

        for (let i = 0; i < 4; i += 1) {
            await walkNorth(page, 0.0003);
        }

        const walked = await liveMeters(page, MANASOTA_A);

        await expect(page.locator('#vd-hud')).toHaveText(`Selected home: ${walked} m away, straight ahead.`);
        expect(await signDistance()).toBe(walked);
        await expect(page.locator('#vd-nearby-note')).toHaveText('(distance from the camera)');
        await expect.poll(() => page.evaluate(() => Number(
            Array.from(document.querySelectorAll('#vd-counters dt'))
                .find((dt) => dt.textContent === 'Listing API calls (our stored MLS data)').nextElementSibling.textContent,
        ))).toBeGreaterThan(listingCallsBefore);

        // The signs are still at the MLS coordinates, unmoved by any of this.
        const markers = await page.evaluate(() => window.__fakeGoogle.markers());
        const a = markers.find((m) => (m.title || '').includes('Manasota Key A'));
        const b = markers.find((m) => (m.title || '').includes('Manasota Key B'));

        expect({ lat: a.lat, lng: a.lng }).toEqual(MANASOTA_A);
        expect({ lat: b.lat, lng: b.lng }).toEqual(MANASOTA_B);
        expect((await fakeGoogle(page)).panoramaConstructorCalls).toBe(1);
        expectNoProviderTraffic(record);
    });
});
