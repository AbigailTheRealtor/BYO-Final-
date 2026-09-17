/*
 |-----------------------------------------------------------------------------
 | Virtual Drive — a rejected Google key: exact error, one report, no retry
 |-----------------------------------------------------------------------------
 |
 | On 2026-09-15 a browser key Google kept rejecting spent six launches in
 | eleven minutes, one reload and press at a time, and the page said only
 | "Google rejected the browser key (referrer or API restriction)". These specs
 | pin what replaced that, against the REAL shell and provider:
 |
 |   • Google's own error code, its "Your site URL to be authorized", and this
 |     page's origin are shown — and the key never is;
 |   • the page stops, reports ONCE, and never retries;
 |   • a reloaded page that the server marks as blocked, or whose claim the
 |     server refuses as blocked, loads nothing and claims nothing more.
 |
 | The server half — the block surviving reloads, sessions and days, and only
 | the reset command clearing it — is VirtualDriveGoogleAuthFailureStopLossTest.
 |
 | HOW GOOGLE'S CONSOLE MESSAGE IS PRODUCED HERE. fake-google-maps.js calls
 | gm_authFailure but prints nothing, and it belongs to other work in progress,
 | so it is not edited. An init script instead wraps the page's gm_authFailure
 | (or google.maps.importLibrary) so the real provider sees Google's documented
 | console format, in the order a spec chooses.
 |
 | NO GOOGLE, NO APPLE, EVER. installNetworkGuard aborts everything leaving the
 | fixture origin; every spec asserts nothing was attempted against either.
 */

const { test, expect } = require('@playwright/test');
const { installNetworkGuard } = require('./support/network');

const PROVIDER_HOSTS = ['googleapis.com', 'gstatic.com', 'google.com', 'apple-mapkit.com', 'apple.com'];

const CLAIM_PATH = '/fake-claim';
const REPORT_PATH = '/fake-auth-report';

// What a grant hands the page. Also planted inside Google's message below, so a
// spec can prove the exact credential is removed, not merely key-shaped text.
const DELIVERED = 'CLAIM-DELIVERED-NOT-A-CREDENTIAL';
// The SHAPE of a Google API key, for proving the redaction. Built at runtime so no
// Google-key-shaped literal exists in the source for secret scanning to mistake
// for a real credential; it says what it is.
const KEY_SHAPED = ['AI', 'za', '_TEST_ONLY_NOT_A_REAL_KEY_', '0'.repeat(9)].join('');
const KEY_PATTERN = /AIza[0-9A-Za-z_-]{10,}/;

const ORIGIN = 'http://127.0.0.1:8931';

const fakeGoogle = (page) => page.evaluate(() => window.__fakeGoogle.counters());
const diagnostics = (page) => page.evaluate(() => JSON.parse(JSON.stringify(window.VirtualDriveDiagnostics)));

function expectNoProviderTraffic(record) {
    expect(record.forbidden).toEqual([]);
    expect(record.external.filter((url) => PROVIDER_HOSTS.some((host) => url.includes(host)))).toEqual([]);
}

function googleMessage(code) {
    return 'Google Maps JavaScript API error: ' + code + '\n'
        + 'https://developers.google.com/maps/documentation/javascript/error-messages#' + code + '\n'
        + 'Your site URL to be authorized: ' + ORIGIN + '/virtual-drive/google.html?key=' + KEY_SHAPED
        + ' (loaded with ' + DELIVERED + ')';
}

/** Google prints its message, then calls gm_authFailure — or the reverse, 50 ms apart. */
async function googleRejectsTheKey(page, code, order) {
    await page.addInitScript(({ message, order }) => {
        let handler;

        Object.defineProperty(window, 'gm_authFailure', {
            configurable: true,
            get() {
                if (!handler) {
                    return undefined;
                }

                return function () {
                    if (order === 'console-first') {
                        console.error(message);
                        handler();
                    } else {
                        handler();
                        setTimeout(() => console.error(message), 50);
                    }
                };
            },
            set(fn) { handler = fn; },
        });
    }, { message: googleMessage(code), order });
}

/** A message in the console with NO gm_authFailure call, on the first importLibrary. */
async function googlePrintsOnLoad(page, text, level) {
    await page.addInitScript(({ text, level }) => {
        let google;
        let printed = false;

        Object.defineProperty(window, 'google', {
            configurable: true,
            get() { return google; },
            set(value) {
                const importLibrary = value.maps.importLibrary;

                value.maps.importLibrary = function (name) {
                    if (!printed) {
                        printed = true;
                        console[level](text);
                    }

                    return importLibrary.call(this, name);
                };
                google = value;
            },
        });
    }, { text, level });
}

async function fakeClaimEndpoint(page, answer) {
    const calls = [];

    await page.route((url) => url.pathname === CLAIM_PATH, async (route) => {
        calls.push(route.request().method());
        await route.fulfill({ status: answer.status, contentType: 'application/json', body: JSON.stringify(answer.body) });
    });

    return calls;
}

async function fakeReportEndpoint(page, mode = 'ok') {
    const bodies = [];

    await page.route((url) => url.pathname === REPORT_PATH, async (route) => {
        const body = route.request().postDataJSON();

        bodies.push({ body, csrf: route.request().headers()['x-csrf-token'] || null });

        if (mode === 'abort') {
            await route.abort();

            return;
        }

        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                blocked: true,
                message: 'Google Street View launches are blocked in this proof environment: Google rejected the browser key ('
                    + body.code + '). Clear with: php artisan virtual-drive:google-auth-block --reset',
                auth_failure: {
                    code: body.code,
                    request_origin: ORIGIN,
                    reported_at: '2026-09-15T17:00:00Z',
                    ledger_used: 1,
                    ledger_limit: 1,
                },
            }),
        });
    });

    return bodies;
}

const granted = {
    status: 200,
    body: {
        granted: true, reason: null, day: '2026-09-15', limit: 1, used: 1, remaining: 0,
        message: 'Launch 1 of 1 for 2026-09-15.',
        credential: DELIVERED,
    },
};

const blockedRefusal = {
    status: 423,
    body: {
        granted: false, reason: 'auth_failure_blocked', day: '2026-09-15', limit: 1, used: 1, remaining: 0,
        message: 'Google Street View launches are blocked in this proof environment: Google rejected the browser key '
            + '(RefererNotAllowedMapError). No launch was granted. Clear with: php artisan virtual-drive:google-auth-block --reset',
        auth_failure: {
            code: 'RefererNotAllowedMapError',
            authorized_url: 'https://proof.example.test:8000/dev/virtual-drive/google',
            request_origin: 'https://proof.example.test:8000',
            reported_at: '2026-09-15T04:39:18Z',
            ledger_used: 10,
            ledger_limit: 10,
        },
    },
};

const claimQuery = '&claim=' + CLAIM_PATH + '&limit=1&authreport=' + REPORT_PATH;

/** Everything a person or a later script could read on this page, as one string. */
async function everythingVisible(page, extra = []) {
    const html = await page.evaluate(() => document.documentElement.outerHTML);
    const diag = JSON.stringify(await diagnostics(page));

    return [html, diag].concat(extra.map((x) => JSON.stringify(x))).join('\n');
}

function expectNoKey(text) {
    // The sentinel must still look like a key to the redaction, or this proves nothing.
    expect(KEY_SHAPED).toMatch(/^AIza[0-9A-Za-z_-]{35}$/);
    expect(text).not.toContain(DELIVERED);
    expect(text).not.toContain(KEY_SHAPED);
    expect(text).not.toMatch(KEY_PATTERN);
}

async function expectNothingMoreLoaded(page, importLibraryCalls) {
    const counters = await fakeGoogle(page);

    expect(counters.panoramaConstructorCalls).toBe(0);
    expect(counters.panoramas).toBe(0);
    expect(counters.importLibrary).toBe(importLibraryCalls);
}

test.describe('Virtual Drive · a rejected Google key (fake Maps API, no network)', () => {
    test('shows Google\'s exact error and the page origin, stops, reports once, and never shows the key', async ({ page }) => {
        const record = await installNetworkGuard(page);
        const claims = await fakeClaimEndpoint(page, granted);
        const reports = await fakeReportEndpoint(page);

        await googleRejectsTheKey(page, 'RefererNotAllowedMapError', 'console-first');
        await page.goto('/virtual-drive/google.html?fake=authfail' + claimQuery);
        await expect(page.locator('#vd-launch')).toHaveText('Drive with Google');

        await page.click('#vd-launch');

        const launch = page.locator('#vd-launch');
        const detail = page.locator('#vd-auth-failure');

        await expect(launch).toHaveText('Blocked: key rejected');
        await expect(launch).toBeDisabled();
        await expect(detail).toBeVisible();
        await expect(detail).toContainText('RefererNotAllowedMapError');
        await expect(detail).toContainText('Site URL Google asked to authorize');
        await expect(detail).toContainText(ORIGIN + '/virtual-drive/google.html');
        await expect(detail).toContainText('This page\'s origin' + ORIGIN);
        await expect(detail).toContainText('Referrer sent to Google' + ORIGIN + '/');
        await expect(detail).toContainText(ORIGIN + '/*');
        await expect(detail).toContainText('php artisan virtual-drive:google-auth-block --reset');

        // ONE report, carrying the code, after the settle window.
        await expect.poll(() => reports.length).toBe(1);
        await expect(page.locator('#vd-launch-note')).toContainText('launches are blocked in this proof environment');
        await expect(detail).toContainText('Origin the server received' + ORIGIN);

        const report = reports[0];

        expect(report.csrf).not.toBeNull();
        expect(report.body).toMatchObject({
            code: 'RefererNotAllowedMapError',
            source: 'gm_authFailure+console',
            page_origin: ORIGIN,
            referrer_policy: 'browser-default',
            referrer_sent: ORIGIN + '/',
        });
        expect(report.body.authorized_url).toContain(ORIGIN + '/virtual-drive/google.html');

        // NO AUTOMATIC RETRY: time passes, the button and the handler are pressed.
        const loadedOnce = (await fakeGoogle(page)).importLibrary;

        await page.waitForTimeout(900);
        await page.evaluate(() => { for (let i = 0; i < 3; i += 1) window.VirtualDrive.launch(); });
        await launch.click({ force: true });
        await page.waitForTimeout(600);

        expect(claims).toHaveLength(1);
        expect(reports).toHaveLength(1);
        await expectNothingMoreLoaded(page, loadedOnce);

        const diag = await diagnostics(page);

        expect(diag.google.authFailed).toBe(true);
        expect(diag.google.authError.code).toBe('RefererNotAllowedMapError');
        expect(diag.shell.allowanceClaims).toBe(1);
        expect(diag.shell.authReportsSent).toBe(1);
        expect(diag.shell.authReportState).toBe('recorded');

        // NEVER THE KEY — not in the DOM, the event log, the diagnostics or the report.
        expectNoKey(await everythingVisible(page, reports));
        expectNoProviderTraffic(record);
    });

    test('Google\'s message arriving after gm_authFailure still reaches the one report', async ({ page }) => {
        const record = await installNetworkGuard(page);
        const claims = await fakeClaimEndpoint(page, granted);
        const reports = await fakeReportEndpoint(page);

        await googleRejectsTheKey(page, 'ApiTargetBlockedMapError', 'callback-first');
        await page.goto('/virtual-drive/google.html?fake=authfail' + claimQuery);
        await expect(page.locator('#vd-launch')).toHaveText('Drive with Google');
        await page.click('#vd-launch');

        await expect(page.locator('#vd-launch')).toHaveText('Blocked: key rejected');
        await expect(page.locator('#vd-auth-failure')).toContainText('ApiTargetBlockedMapError');
        await expect.poll(() => reports.length).toBe(1);
        await page.waitForTimeout(700);

        expect(reports).toHaveLength(1);
        expect(reports[0].body.code).toBe('ApiTargetBlockedMapError');
        expect(reports[0].body.source).toBe('gm_authFailure+console');
        expect(claims).toHaveLength(1);
        expect((await fakeGoogle(page)).panoramaConstructorCalls).toBe(0);
        expectNoKey(await everythingVisible(page, reports));
        expectNoProviderTraffic(record);
    });

    test('a Maps authorization error in the console alone stops the page before any panorama', async ({ page }) => {
        const record = await installNetworkGuard(page);
        const claims = await fakeClaimEndpoint(page, granted);
        const reports = await fakeReportEndpoint(page);

        await googlePrintsOnLoad(page, googleMessage('InvalidKeyMapError'), 'error');
        await page.goto('/virtual-drive/google.html?fake=ok' + claimQuery);
        await expect(page.locator('#vd-launch')).toHaveText('Drive with Google');
        await page.click('#vd-launch');

        await expect(page.locator('#vd-launch')).toHaveText('Blocked: key rejected');
        await expect(page.locator('#vd-auth-failure')).toContainText('InvalidKeyMapError');
        await expect.poll(() => reports.length).toBe(1);
        await page.waitForTimeout(500);

        const counters = await fakeGoogle(page);

        expect(counters.getPanorama).toBe(0);
        expect(counters.panoramaConstructorCalls).toBe(0);
        expect(claims).toHaveLength(1);
        expect(reports[0].body.source).toBe('console');
        expectNoKey(await everythingVisible(page, reports));
        expectNoProviderTraffic(record);
    });

    test('a Maps console message that is not an authorization failure is logged, not treated as one', async ({ page }) => {
        const record = await installNetworkGuard(page);
        const claims = await fakeClaimEndpoint(page, granted);
        const reports = await fakeReportEndpoint(page);

        await googlePrintsOnLoad(page, 'Google Maps JavaScript API warning: RetiredVersion https://developers.google.com/maps/documentation/javascript/error-messages#retired-version', 'warn');
        await page.goto('/virtual-drive/google.html?fake=ok' + claimQuery);
        await expect(page.locator('#vd-launch')).toHaveText('Drive with Google');
        await page.click('#vd-launch');

        await expect.poll(async () => (await fakeGoogle(page)).panoramas).toBe(1);
        await page.waitForTimeout(600);

        expect(reports).toHaveLength(0);
        expect(claims).toHaveLength(1);
        await expect(page.locator('#vd-auth-failure')).toBeHidden();
        await expect(page.locator('#vd-events')).toContainText('Google console warn');
        expect((await diagnostics(page)).google.authFailed).toBe(false);
        expectNoProviderTraffic(record);
    });

    test('a reload the server marks as blocked starts locked, shows the recorded cause, and claims nothing', async ({ page }) => {
        const record = await installNetworkGuard(page);
        const claims = await fakeClaimEndpoint(page, granted);
        const reports = await fakeReportEndpoint(page);
        const block = JSON.stringify(blockedRefusal.body.auth_failure);

        await page.goto('/virtual-drive/google.html?fake=authfail' + claimQuery + '&authblock=' + encodeURIComponent(block));

        const launch = page.locator('#vd-launch');

        await expect(launch).toHaveText('Blocked: key rejected');
        await expect(launch).toBeDisabled();
        await expect(page.locator('#vd-launch-note')).toContainText('launches are blocked');
        await expect(page.locator('#vd-auth-failure')).toContainText('RefererNotAllowedMapError');
        await expect(page.locator('#vd-auth-failure')).toContainText('https://proof.example.test:8000');
        await expect(page.locator('#vd-auth-failure')).toContainText('Launches used when it failed10 of 10');

        await launch.click({ force: true });
        await page.evaluate(() => { for (let i = 0; i < 3; i += 1) window.VirtualDrive.launch(); });

        // The page is still usable; it simply never reaches Google.
        await page.click('#vd-next');
        await page.waitForTimeout(700);

        expect(claims).toHaveLength(0);
        expect(reports).toHaveLength(0);
        await expectNothingMoreLoaded(page, 0);
        expect((await diagnostics(page)).google.libraryRequested).toBe(0);
        expectNoProviderTraffic(record);
    });

    test('a reload the server refuses as blocked locks with the recorded cause and is never retried', async ({ page }) => {
        const record = await installNetworkGuard(page);
        const claims = await fakeClaimEndpoint(page, blockedRefusal);
        const reports = await fakeReportEndpoint(page);

        await page.goto('/virtual-drive/google.html?fake=authfail' + claimQuery);
        await expect(page.locator('#vd-launch')).toHaveText('Drive with Google');
        await page.click('#vd-launch');

        await expect(page.locator('#vd-launch')).toHaveText('Blocked: key rejected');
        await expect(page.locator('#vd-launch-note')).toContainText('RefererNotAllowedMapError');
        await expect(page.locator('#vd-auth-failure')).toContainText('https://proof.example.test:8000/dev/virtual-drive/google');

        await page.evaluate(() => { for (let i = 0; i < 3; i += 1) window.VirtualDrive.launch(); });
        await page.waitForTimeout(700);

        expect(claims).toHaveLength(1);
        expect(reports).toHaveLength(0);
        await expectNothingMoreLoaded(page, 0);
        expectNoProviderTraffic(record);
    });

    test('a report that cannot be recorded leaves the page stopped, says so, and is not retried', async ({ page }) => {
        const record = await installNetworkGuard(page);
        const claims = await fakeClaimEndpoint(page, granted);
        const reports = await fakeReportEndpoint(page, 'abort');

        await googleRejectsTheKey(page, 'RefererNotAllowedMapError', 'console-first');
        await page.goto('/virtual-drive/google.html?fake=authfail' + claimQuery);
        await expect(page.locator('#vd-launch')).toHaveText('Drive with Google');
        await page.click('#vd-launch');

        await expect(page.locator('#vd-launch-note')).toContainText('could not be recorded on the server');
        await page.waitForTimeout(900);

        expect(reports).toHaveLength(1);
        expect(claims).toHaveLength(1);
        await expect(page.locator('#vd-launch')).toHaveText('Blocked: key rejected');
        expect((await diagnostics(page)).shell.authReportState).toBe('failed');
        expect((await fakeGoogle(page)).panoramaConstructorCalls).toBe(0);
        expectNoProviderTraffic(record);
    });
});
