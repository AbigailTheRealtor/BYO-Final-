/*
 |-----------------------------------------------------------------------------
 | Virtual Drive — the launch guard, proven against fakes before any real key
 |-----------------------------------------------------------------------------
 |
 | Google bills Dynamic Street View per panorama OBJECT instantiated. The proof's
 | promise is: zero panoramas before a deliberate "Drive with Google", exactly one
 | after it, and still one however the page is then used. These specs hold it to
 | that with the REAL shell and provider scripts and a fake Maps API.
 |
 | TWO WITNESSES. fake-google-maps.js counts constructor calls itself; the
 | provider keeps its own counters in window.VirtualDriveDiagnostics. Each spec
 | asserts both, so a provider that under-reported would still be caught.
 |
 | NO GOOGLE, NO APPLE, EVER. installNetworkGuard aborts every request leaving
 | the fixture origin and records the attempt; every spec asserts that nothing
 | was even attempted against Google or Apple. The fixture's credential is a
 | placeholder, and no real key exists in any test run (phpunit.xml and
 | tests/bootstrap.php blank it; see VirtualDriveCredentialTestEnvGuardTest).
 */

const { test, expect } = require('@playwright/test');
const { installNetworkGuard } = require('./support/network');

const PROVIDER_HOSTS = ['googleapis.com', 'gstatic.com', 'google.com', 'apple-mapkit.com', 'apple.com'];

const fakeGoogle = (page) => page.evaluate(() => window.__fakeGoogle.counters());
const fakeMapkit = (page) => page.evaluate(() => window.__fakeMapkit.counters());
const diagnostics = (page) => page.evaluate(() => JSON.parse(JSON.stringify(window.VirtualDriveDiagnostics)));

function expectNoProviderTraffic(record) {
    expect(record.forbidden).toEqual([]);
    expect(record.external.filter((url) => PROVIDER_HOSTS.some((host) => url.includes(host)))).toEqual([]);
}

async function openGoogle(page, query = '') {
    const record = await installNetworkGuard(page);

    await page.goto('/virtual-drive/google.html' + query);
    await expect(page.locator('#vd-launch')).toBeEnabled();
    await expect(page.locator('#vd-launch')).toHaveText('Drive with Google');

    return record;
}

async function launchGoogle(page) {
    await page.click('#vd-launch');
    await expect.poll(async () => (await fakeGoogle(page)).panoramas).toBe(1);
    await expect(page.locator('#vd-launch-panel')).toBeHidden();
}

test.describe('Virtual Drive · Google launch guard (fake Maps API, no network)', () => {
    test('opening the page, browsing homes, cards, photos, tours and hovering construct nothing', async ({ page }) => {
        const record = await openGoogle(page);

        await page.hover('#vd-launch');
        await page.click('#vd-next');
        await page.click('#vd-next');
        await page.click('#vd-prev');
        await page.locator('.vd-nearby-item').nth(2).click();
        await page.click('.vd-action-photos');
        await page.click('#vd-lightbox-close');
        await page.locator('#vd-next').click();
        await page.waitForTimeout(400);

        expect(await fakeGoogle(page)).toMatchObject({
            importLibrary: 0,
            panoramaConstructorCalls: 0,
            markers: 0,
            getPanorama: 0,
        });

        const diag = await diagnostics(page);

        expect(diag.google.libraryRequested).toBe(0);
        expect(diag.google.panoramaConstructions).toBe(0);
        expect(diag.shell.launchesStarted).toBe(0);
        expect(diag.shell.launchState).toBe('idle');
        expectNoProviderTraffic(record);
    });

    test('double-clicks and rapid presses start exactly one session and lock the button', async ({ page }) => {
        const record = await openGoogle(page, '?delay=400');
        const launch = page.locator('#vd-launch');

        await launch.dblclick();
        await expect(launch).toBeDisabled();
        await expect(launch).toHaveText('Opening Virtual Drive…');

        // The lock itself, not just the disabled attribute: call the handler while opening.
        await page.evaluate(() => { for (let i = 0; i < 5; i += 1) window.VirtualDrive.launch(); });

        await expect.poll(async () => (await fakeGoogle(page)).panoramas).toBe(1);

        // …and again once open.
        await page.evaluate(() => { for (let i = 0; i < 5; i += 1) window.VirtualDrive.launch(); });
        await page.waitForTimeout(300);

        expect((await fakeGoogle(page)).panoramaConstructorCalls).toBe(1);

        const diag = await diagnostics(page);

        expect(diag.shell.launchesStarted).toBe(1);
        expect(diag.shell.launchClicks).toBeGreaterThanOrEqual(11);
        expect(diag.google.panoramaConstructions).toBe(1);
        expect(diag.google.refusedConstructions).toBe(0);
        expect(diag.google.libraryRequested).toBe(0);
        expect(diag.google.adoptedExistingApi).toBe(true);
        expectNoProviderTraffic(record);
    });

    test('rotating, walking, travelling, changing homes, markers, cards, photos, tours and route changes reuse the one panorama', async ({ page }) => {
        const record = await openGoogle(page);

        await launchGoogle(page);

        await page.evaluate(() => {
            const panorama = window.__fakeGoogle.lastPanorama();

            panorama.__userRotate(90);
            panorama.__userRotate(90);
            panorama.__userRotate(180);
            panorama.__userWalk(0.0002, 0);
            panorama.__userWalk(-0.0002, 0);

            // ~200 m down the street, one panorama step at a time.
            for (let i = 0; i < 10; i += 1) panorama.__userWalk(0.00018, 0);
        });

        for (let i = 0; i < 6; i += 1) {
            await page.click('#vd-next');
            await page.waitForTimeout(150);
        }

        await page.click('#vd-prev');
        await page.evaluate(() => window.__fakeGoogle.clickMarker(0));
        await page.evaluate(() => window.__fakeGoogle.clickMarker(3));
        await page.click('.vd-action-photos');
        await page.click('#vd-lightbox-next');
        await page.click('#vd-lightbox-close');
        await page.locator('.vd-nearby-item').first().click();
        await page.waitForTimeout(200);
        await page.getByRole('button', { name: 'Face the selected home' }).click();
        await page.evaluate(() => window.history.replaceState(null, '', window.location.pathname + '?listing=FX-RENT-MANASOTA-B'));
        await page.waitForTimeout(400);

        const counters = await fakeGoogle(page);

        expect(counters.panoramaConstructorCalls).toBe(1);
        expect(counters.userMoves).toBeGreaterThanOrEqual(15);
        expect(counters.setPano).toBeGreaterThanOrEqual(5);

        const diag = await diagnostics(page);

        expect(diag.google.panoramaConstructions).toBe(1);
        expect(diag.google.refusedConstructions).toBe(0);
        expect(diag.shell.launchesStarted).toBe(1);
        expectNoProviderTraffic(record);
    });

    test('every marker sits at its own MLS coordinate and opens only its own listing', async ({ page }) => {
        const record = await openGoogle(page);

        await launchGoogle(page);

        const listings = await page.evaluate(() => fetch('/virtual-drive/listings.json').then((r) => r.json()).then((j) => j.listings));
        const markers = await page.evaluate(() => window.__fakeGoogle.markers());

        expect(markers).toHaveLength(listings.length);

        for (let i = 0; i < markers.length; i += 1) {
            const own = listings.find((listing) => markers[i].title.endsWith('— ' + listing.address));

            expect(own, `marker ${i} (${markers[i].title}) matches a listing`).toBeTruthy();
            expect(markers[i].onPanorama).toBe(true);
            expect(markers[i].lat).toBeCloseTo(own.latitude, 6);
            expect(markers[i].lng).toBeCloseTo(own.longitude, 6);

            await page.evaluate((index) => window.__fakeGoogle.clickMarker(index), i);
            await expect(page.locator('.vd-verify')).toContainText('Listing key ' + own.id + ' ');
        }

        // The two Manasota Key homes, 33 m apart, are two markers at two points.
        const a = markers.find((m) => m.title.includes('Manasota Key A'));
        const b = markers.find((m) => m.title.includes('Manasota Key B'));

        expect(a.lat === b.lat && a.lng === b.lng).toBe(false);

        // The condo problem, stated as a fact: three units, one point, three stacked signs.
        const stacked = markers.filter((m) => m.title.includes('Siesta Bayside'));

        expect(stacked).toHaveLength(3);
        expect(new Set(stacked.map((m) => m.lat + ',' + m.lng)).size).toBe(1);

        expect((await fakeGoogle(page)).panoramaConstructorCalls).toBe(1);
        expectNoProviderTraffic(record);
    });

    test('no coverage: nothing retries by itself, and only a deliberate press tries again', async ({ page }) => {
        const record = await openGoogle(page, '?fake=nocoverage');

        await page.click('#vd-launch');
        await expect(page.locator('#vd-launch')).toHaveText('Try again');

        expect(await fakeGoogle(page)).toMatchObject({ getPanorama: 2, panoramaConstructorCalls: 0 });

        await page.waitForTimeout(1500);

        expect(await fakeGoogle(page)).toMatchObject({ getPanorama: 2, panoramaConstructorCalls: 0 });

        await page.click('#vd-launch');
        await expect.poll(async () => (await fakeGoogle(page)).getPanorama).toBe(4);

        const counters = await fakeGoogle(page);

        expect(counters.panoramaConstructorCalls).toBe(0);
        expect(counters.importLibrary).toBe(4); // the library was loaded once, not per attempt
        expect((await diagnostics(page)).shell.launchesStarted).toBe(2);
        expectNoProviderTraffic(record);
    });

    test('a rejected key locks the page and nothing is retried', async ({ page }) => {
        const record = await openGoogle(page, '?fake=authfail');

        await page.click('#vd-launch');
        await expect(page.locator('#vd-launch')).toHaveText('Unavailable');
        await expect(page.locator('#vd-launch')).toBeDisabled();

        await page.evaluate(() => { for (let i = 0; i < 3; i += 1) window.VirtualDrive.launch(); });
        await page.waitForTimeout(500);

        const counters = await fakeGoogle(page);

        expect(counters.panoramaConstructorCalls).toBe(0);
        expect(counters.importLibrary).toBe(4);
        expect((await diagnostics(page)).google.authFailed).toBe(true);
        expectNoProviderTraffic(record);
    });

    test('a failed construction is never repeated: the retry is refused and the page stops', async ({ page }) => {
        const record = await openGoogle(page, '?fake=constructorThrows');

        await page.click('#vd-launch');
        await expect(page.locator('#vd-launch')).toHaveText('Try again');
        await page.click('#vd-launch');
        await expect(page.locator('#vd-launch')).toHaveText('Unavailable');

        const diag = await diagnostics(page);

        expect((await fakeGoogle(page)).panoramaConstructorCalls).toBe(1);
        expect(diag.google.panoramaConstructions).toBe(1);
        expect(diag.google.refusedConstructions).toBe(1);
        await expect(page.locator('#vd-events')).toContainText('STOP');
        expectNoProviderTraffic(record);
    });

    test('reloading keeps the selected home but never starts a session', async ({ page }) => {
        const record = await openGoogle(page);

        await page.click('#vd-next');
        await launchGoogle(page);
        expect(page.url()).toContain('listing=');

        await page.reload();
        await expect(page.locator('#vd-launch')).toBeEnabled();
        await page.waitForTimeout(500);

        expect(await fakeGoogle(page)).toMatchObject({ importLibrary: 0, panoramaConstructorCalls: 0 });
        expect((await diagnostics(page)).shell.launchesStarted).toBe(0);
        expectNoProviderTraffic(record);
    });
});

test.describe('Virtual Drive · Apple launch guard (fake MapKit JS, no network)', () => {
    test('nothing loads before the press; the sign is screen-fixed; each home is a new Look Around', async ({ page }) => {
        const record = await installNetworkGuard(page);

        await page.goto('/virtual-drive/apple.html');
        await expect(page.locator('#vd-launch')).toHaveText('Open Look Around');
        await page.click('#vd-next');
        await page.click('#vd-prev');
        await page.waitForTimeout(300);

        expect(await fakeMapkit(page)).toMatchObject({ inits: 0, lookArounds: 0 });

        await page.locator('#vd-launch').dblclick();
        await expect.poll(async () => (await fakeMapkit(page)).lookArounds).toBe(1);
        expect((await fakeMapkit(page)).inits).toBe(1);

        await expect(page.locator('#vd-sign')).toBeVisible();
        await expect(page.locator('#vd-sign-caveat')).toContainText('Screen-fixed');

        await page.click('#vd-next');
        await expect.poll(async () => (await fakeMapkit(page)).lookArounds).toBe(2);
        expect((await fakeMapkit(page)).destroys).toBe(1);

        const diag = await diagnostics(page);

        expect(diag.apple.libraryRequested).toBe(0);
        expect(diag.apple.adoptedExistingApi).toBe(true);
        expect(diag.shell.launchesStarted).toBe(1);
        expectNoProviderTraffic(record);
    });
});
