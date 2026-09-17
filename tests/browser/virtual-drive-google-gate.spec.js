/*
 |-----------------------------------------------------------------------------
 | Virtual Drive — the Google kill switch and the daily launch ceiling
 |-----------------------------------------------------------------------------
 |
 | VIRTUAL_DRIVE_GOOGLE_ENABLED and VIRTUAL_DRIVE_GOOGLE_DAILY_LAUNCH_LIMIT are
 | server-side decisions, and the PHP suite proves what the server does with them
 | (VirtualDriveGoogleKillSwitchTest, VirtualDriveGoogleDailyLaunchLimitTest).
 | What it cannot see is the browser. These specs run the REAL shell and the REAL
 | Google provider against a fake Maps API and a faked claim endpoint, and assert
 | the thing that actually costs money: whether a StreetViewPanorama was ever
 | constructed.
 |
 | THE FIXTURE IS HARDER THAN THE PAGE, DELIBERATELY. The real page omits
 | google-streetview-provider.js entirely when the switch is off, so nothing there
 | could construct a panorama. The fixture keeps the provider loaded and
 | registered, so these specs prove the shell refuses even when the means IS
 | present — belt as well as braces.
 |
 | TWO WITNESSES, as in virtual-drive-launch-guard.spec.js: fake-google-maps.js
 | counts constructor calls itself, and the provider keeps its own counters in
 | window.VirtualDriveDiagnostics. Every spec asserts both.
 |
 | NO GOOGLE, NO APPLE, EVER. installNetworkGuard aborts everything leaving the
 | fixture origin and records the attempt; every spec asserts nothing was even
 | attempted against either provider.
 */

const { test, expect } = require('@playwright/test');
const { installNetworkGuard } = require('./support/network');

const PROVIDER_HOSTS = ['googleapis.com', 'gstatic.com', 'google.com', 'apple-mapkit.com', 'apple.com'];

const CLAIM_PATH = '/fake-claim';

const fakeGoogle = (page) => page.evaluate(() => window.__fakeGoogle.counters());
const diagnostics = (page) => page.evaluate(() => JSON.parse(JSON.stringify(window.VirtualDriveDiagnostics)));

function expectNoProviderTraffic(record) {
    expect(record.forbidden).toEqual([]);
    expect(record.external.filter((url) => PROVIDER_HOSTS.some((host) => url.includes(host)))).toEqual([]);
}

/** Nothing billable happened: no library, no metadata lookup, no panorama. */
async function expectNothingLoaded(page) {
    expect(await fakeGoogle(page)).toMatchObject({
        importLibrary: 0,
        getPanorama: 0,
        panoramaConstructorCalls: 0,
        panoramas: 0,
        markers: 0,
    });

    const diag = await diagnostics(page);

    expect(diag.google.libraryRequested).toBe(0);
    expect(diag.google.panoramaConstructions).toBe(0);
    expect(diag.google.streetViewInitialized).toBe(false);
    expect(diag.shell.providerLoaded).toBe(false);
}

/**
 * Stand in for dev.virtual-drive.api.google-launch.
 *
 * `answers` is consumed one press at a time, so a spec can say "grant, then
 * refuse" and see what the page does on each. Every request is recorded, which
 * is how "one claim per page" is asserted.
 */
async function fakeClaimEndpoint(page, answers) {
    const calls = [];
    const queue = answers.slice();

    await page.route('**' + CLAIM_PATH, async (route) => {
        const request = route.request();

        calls.push({ method: request.method(), csrf: request.headers()['x-csrf-token'] || null });

        const answer = queue.length > 1 ? queue.shift() : queue[0];

        if (answer === 'abort') {
            await route.abort();

            return;
        }

        await route.fulfill({
            status: answer.status,
            contentType: 'application/json',
            body: JSON.stringify(answer.body),
        });
    });

    return calls;
}

const granted = (used, limit) => ({
    status: 200,
    body: {
        granted: true,
        reason: null,
        day: '2026-09-12',
        limit: limit,
        used: used,
        remaining: limit - used,
        message: 'Launch ' + used + ' of ' + limit + ' for 2026-09-12.',
        // The fake Maps API is already on the page and is adopted rather than
        // loaded, so this is never sent anywhere. It is here because the real
        // grant carries the key and the shell must accept it from the response.
        credential: 'CLAIM-DELIVERED-NOT-A-CREDENTIAL',
    },
});

const refused = (limit) => ({
    status: 429,
    body: {
        granted: false,
        reason: 'daily_limit_reached',
        day: '2026-09-12',
        limit: limit,
        used: limit,
        remaining: 0,
        message: 'Daily Google Street View limit reached: all ' + limit + ' launches for 2026-09-12 have been '
            + 'used across this proof environment. Nothing was requested from Google.',
    },
});

test.describe('Virtual Drive · the Google kill switch (fake Maps API, no network)', () => {
    test('switched off: the page works, the button refuses, and nothing Google-shaped initializes', async ({ page }) => {
        const record = await installNetworkGuard(page);

        await page.goto('/virtual-drive/google.html?google=off');

        const launch = page.locator('#vd-launch');

        await expect(launch).toHaveText('Switched off');
        await expect(launch).toBeDisabled();
        await expect(page.locator('#vd-launch-note')).toContainText('VIRTUAL_DRIVE_GOOGLE_ENABLED=false');
        await expect(page.locator('#vd-launch-panel')).toHaveClass(/is-refused/);

        // The rest of the page is not Google's and must still work.
        await expect(page.locator('#vd-card-body')).not.toBeEmpty();
        await page.click('#vd-next');
        await page.click('.vd-action-photos');
        await page.click('#vd-lightbox-close');

        await expectNothingLoaded(page);
        expectNoProviderTraffic(record);
    });

    test('switched off: calling the launch handler directly still loads nothing', async ({ page }) => {
        const record = await installNetworkGuard(page);

        await page.goto('/virtual-drive/google.html?google=off');
        await expect(page.locator('#vd-launch')).toHaveText('Switched off');

        // The disabled attribute is a UI nicety; this is the guard.
        await page.evaluate(() => { for (let i = 0; i < 5; i += 1) window.VirtualDrive.launch(); });
        await page.waitForTimeout(500);

        const diag = await diagnostics(page);

        expect(diag.shell.launchClicks).toBe(5);
        expect(diag.shell.launchesStarted).toBe(0);
        expect(diag.shell.launchState).toBe('locked');
        expect(diag.shell.providerEnabled).toBe(false);

        await expectNothingLoaded(page);
        expectNoProviderTraffic(record);
    });
});

test.describe('Virtual Drive · the daily launch ceiling (fake Maps API, faked claim endpoint)', () => {
    test('a granted claim precedes the library and yields exactly one panorama', async ({ page }) => {
        const record = await installNetworkGuard(page);
        const calls = await fakeClaimEndpoint(page, [granted(1, 10)]);

        await page.goto('/virtual-drive/google.html?claim=' + CLAIM_PATH + '&limit=10');
        await expect(page.locator('#vd-launch')).toBeEnabled();

        // The ceiling is announced before anybody presses anything.
        await expect(page.locator('#vd-counters')).toContainText('10 across this proof environment');
        await expectNothingLoaded(page);

        await page.click('#vd-launch');
        await expect.poll(async () => (await fakeGoogle(page)).panoramas).toBe(1);

        expect(calls).toEqual([{ method: 'POST', csrf: 'FIXTURE-NOT-A-TOKEN' }]);

        const diag = await diagnostics(page);

        expect(diag.shell.allowanceClaims).toBe(1);
        expect(diag.shell.allowanceGranted).toBe(1);
        expect(diag.shell.allowanceRefused).toBe(0);
        expect(diag.shell.allowanceRemaining).toBe(9);
        expect(diag.google.panoramaConstructions).toBe(1);
        await expect(page.locator('#vd-counters')).toContainText('1 of 10');
        expectNoProviderTraffic(record);
    });

    test('a refused claim loads nothing at all and says why', async ({ page }) => {
        const record = await installNetworkGuard(page);
        const calls = await fakeClaimEndpoint(page, [refused(10)]);

        await page.goto('/virtual-drive/google.html?claim=' + CLAIM_PATH + '&limit=10');
        await expect(page.locator('#vd-launch')).toBeEnabled();

        await page.click('#vd-launch');

        const launch = page.locator('#vd-launch');

        await expect(launch).toHaveText('Daily limit reached');
        await expect(launch).toBeDisabled();
        await expect(page.locator('#vd-launch-note')).toContainText('Daily Google Street View limit reached');
        await expect(page.locator('#vd-launch-note')).toContainText('Nothing was requested from Google');
        await expect(page.locator('#vd-launch-panel')).toHaveClass(/is-refused/);

        expect(calls).toHaveLength(1);

        // THE POINT: refused before the library, so there is nothing to bill for.
        await expectNothingLoaded(page);

        const diag = await diagnostics(page);

        expect(diag.shell.allowanceRefused).toBe(1);
        expect(diag.shell.allowanceReason).toBe('daily_limit_reached');
        expectNoProviderTraffic(record);
    });

    test('a refusal is never retried, by the button or by the handler', async ({ page }) => {
        const record = await installNetworkGuard(page);
        const calls = await fakeClaimEndpoint(page, [refused(1)]);

        await page.goto('/virtual-drive/google.html?claim=' + CLAIM_PATH + '&limit=1');
        await page.click('#vd-launch');
        await expect(page.locator('#vd-launch')).toHaveText('Daily limit reached');

        await page.evaluate(() => { for (let i = 0; i < 4; i += 1) window.VirtualDrive.launch(); });
        await page.waitForTimeout(800);

        // One claim for one press. A ceiling that re-asks is not a ceiling.
        expect(calls).toHaveLength(1);
        expect((await diagnostics(page)).shell.allowanceClaims).toBe(1);
        await expectNothingLoaded(page);
        expectNoProviderTraffic(record);
    });

    test('one claim per page: a second press on an already-loaded page claims nothing more', async ({ page }) => {
        const record = await installNetworkGuard(page);
        // Answers beyond the first would be REFUSALS, so a second claim would be
        // visible as a locked button rather than having to be inferred.
        const calls = await fakeClaimEndpoint(page, [granted(1, 10), refused(10)]);

        // No coverage at the first home: the library loads, no panorama is built,
        // and the page waits for a deliberate second press.
        await page.goto('/virtual-drive/google.html?claim=' + CLAIM_PATH + '&limit=10&fake=nocoverage');
        await page.click('#vd-launch');
        await expect(page.locator('#vd-launch')).toHaveText('Try again');

        expect(calls).toHaveLength(1);

        await page.click('#vd-launch');
        await expect.poll(async () => (await fakeGoogle(page)).getPanorama).toBe(4);

        // The page already holds its one permitted panorama's worth of allowance.
        expect(calls).toHaveLength(1);

        const diag = await diagnostics(page);

        expect(diag.shell.allowanceClaims).toBe(1);
        expect(diag.shell.launchesStarted).toBe(2);
        expect(diag.google.panoramaConstructions).toBe(0);
        expectNoProviderTraffic(record);
    });

    test('reloading re-claims, because a reload is a new billable panorama', async ({ page }) => {
        const record = await installNetworkGuard(page);
        const calls = await fakeClaimEndpoint(page, [granted(1, 10)]);

        await page.goto('/virtual-drive/google.html?claim=' + CLAIM_PATH + '&limit=10');
        await page.click('#vd-launch');
        await expect.poll(async () => (await fakeGoogle(page)).panoramas).toBe(1);
        expect(calls).toHaveLength(1);

        await page.reload();
        await expect(page.locator('#vd-launch')).toBeEnabled();

        // A reload alone claims nothing — only a press does.
        expect(calls).toHaveLength(1);
        await expectNothingLoaded(page);

        await page.click('#vd-launch');
        await expect.poll(async () => (await fakeGoogle(page)).panoramas).toBe(1);
        expect(calls).toHaveLength(2);
        expectNoProviderTraffic(record);
    });

    test('a claim that cannot be made is a refusal, not a launch', async ({ page }) => {
        const record = await installNetworkGuard(page);
        const calls = await fakeClaimEndpoint(page, ['abort']);

        await page.goto('/virtual-drive/google.html?claim=' + CLAIM_PATH + '&limit=10');
        await page.click('#vd-launch');

        await expect(page.locator('#vd-launch')).toHaveText('Unavailable');
        await expect(page.locator('#vd-launch')).toBeDisabled();
        await expect(page.locator('#vd-launch-note')).toContainText('nothing was loaded');

        expect(calls).toHaveLength(1);
        expect((await diagnostics(page)).shell.allowanceReason).toBe('request_failed');
        await expectNothingLoaded(page);
        expectNoProviderTraffic(record);
    });
});
