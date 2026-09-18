/*
 |-----------------------------------------------------------------------------
 | Save | Maybe | Pass in the Virtual Drive — the REAL proof page, fake MapKit
 |-----------------------------------------------------------------------------
 |
 | Drives /dev/virtual-drive/apple on the isolated harness, so the controls in
 | the shopper card are the ones VirtualDriveProofController really renders and
 | every click posts to the real /listing-preferences/virtual-drive routes.
 |
 | NO PROVIDER IS REACHED, BY THREE MECHANISMS:
 |   · the repository's fake MapKit is injected before any page script runs, and
 |     the Apple provider ADOPTS an existing `mapkit` rather than loading one;
 |   · the network guard aborts every request that leaves the harness origin,
 |     and each test asserts nothing tried;
 |   · the harness switches Google off, keyless, with a zero launch ceiling.
 |
 | Named listing-preference-*.spec.js so it runs in the Laravel-backed suite
 | (playwright.app.config.js), never the static one.
 */

const { test, expect } = require('@playwright/test');
const { installNetworkGuard } = require('./support/network');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const APP = 'http://127.0.0.1:8932';
const OFF = 'http://127.0.0.1:8933';
const FAKE_MAPKIT = path.join(ROOT, 'tests/browser/fixtures/virtual-drive/fake-mapkit.js');

/*
 | MAPS PROVIDERS, specifically. The application's own layout (the login page,
 | the history page) asks CDNs for fonts and stylesheets — fonts.googleapis.com
 | among them. Those are not Maps requests, and the guard aborts them before they
 | leave, like every other off-origin request. What must never even be ATTEMPTED
 | is a Street View / Maps JavaScript / MapKit request.
 */
const MAPS_HOSTS = ['maps.googleapis.com', 'maps.gstatic.com', 'streetviewpixels', 'maps.google.com',
    'khms', 'apple-mapkit.com', 'cdn.apple-mapkit.com', 'apple.com'];

const fixture = (suffix = '') =>
    JSON.parse(fs.readFileSync(path.join(ROOT, `storage/app/lp-browser-fixture${suffix}.json`), 'utf8')).virtual_drive;

async function prepare(page, origin) {
    const record = await installNetworkGuard(page, { allowOrigin: origin });
    await page.addInitScript({ path: FAKE_MAPKIT });

    // Every preference request the page makes, for assertions on the surface.
    record.preferenceWrites = [];
    page.on('request', (r) => {
        if (r.url().includes('/listing-preferences')) {
            record.preferenceWrites.push({ method: r.method(), url: r.url() });
        }
    });

    return record;
}

function expectNoProviderTraffic(record) {
    // Not one Maps / Street View / MapKit request was even attempted…
    expect(record.external.filter((url) => MAPS_HOSTS.some((host) => url.includes(host)))).toEqual([]);
    // …and the only Google hosts touched at all are the layout's web fonts,
    // which the guard aborted — nothing reached the network.
    expect(record.forbidden.filter((url) => !/^https:\/\/fonts\.(googleapis|gstatic)\.com\//.test(url))).toEqual([]);
}

async function signIn(page, origin, email, password) {
    await page.goto(`${origin}/login`);
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 20_000 });
}

async function launchAt(page, origin) {
    await page.goto(`${origin}/dev/virtual-drive/apple?view=customer`);
    await expect(page.locator('#vd-launch')).toHaveText('Open Look Around');
    await page.click('#vd-launch');
    await expect.poll(() => page.evaluate(() => window.__fakeMapkit.counters().lookArounds)).toBe(1);
}

const card = (page) => page.locator('#vd-shopper');
const slot = (page) => card(page).locator('[data-lp-control]');
const stateBtn = (page, state) => slot(page).locator(`[data-lp-state="${state}"]`);

test.describe('Virtual Drive · Save | Maybe | Pass (real page, fake MapKit, no network)', () => {
    test.describe.configure({ mode: 'serial' });

    test('the selected home carries its own control, and a choice goes through the Virtual Drive routes', async ({ page }) => {
        const f = fixture();
        const record = await prepare(page, APP);
        await signIn(page, APP, f.buyer_email, f.password);
        await launchAt(page, APP);

        await expect(card(page)).toHaveAttribute('data-listing', f.keys[0]);
        await expect(slot(page)).toHaveCount(1);
        // The control carries the TRUSTED row id, never the browser's key.
        await expect(slot(page)).toHaveAttribute('data-lp-listing-type', 'bridge');
        await expect(slot(page)).toHaveAttribute('data-lp-listing-id', String(f.bridge_ids[0]));
        await expect(slot(page).locator('[data-lp-state].is-active')).toHaveCount(0);

        const write = page.waitForResponse((r) => r.url().endsWith('/listing-preferences/virtual-drive') && r.request().method() === 'POST');
        await stateBtn(page, 'save').click();
        expect((await write).status()).toBe(200);
        await expect(stateBtn(page, 'save')).toHaveClass(/is-active/);

        // Optional reasons, through the same surface.
        const chips = slot(page).locator('[data-lp-chip]');
        await expect(chips.first()).toBeVisible();
        await chips.first().click();
        const reasons = page.waitForResponse((r) => r.url().endsWith('/listing-preferences/virtual-drive/reasons'));
        await slot(page).locator('[data-lp-done]').click();
        expect((await reasons).status()).toBe(200);

        // Every write went to the Virtual Drive's routes — none to another surface's.
        const writes = record.preferenceWrites.filter((x) => x.method !== 'GET');
        for (const w of writes) {
            expect(w.url).toContain('/listing-preferences/virtual-drive');
        }

        // ONE click, ONE write. A second copy of the shared behaviour on the
        // page would handle each click again — duplicate writes and duplicate
        // history events — which is exactly what per-control rendering caused.
        expect(writes.map((w) => `${w.method} ${new URL(w.url).pathname}`)).toEqual([
            'POST /listing-preferences/virtual-drive',
            'POST /listing-preferences/virtual-drive/reasons',
        ]);

        expectNoProviderTraffic(record);
    });

    test('another home shows ITS state; returning shows the first home\'s stored choice', async ({ page }) => {
        const f = fixture();
        const record = await prepare(page, APP);
        await signIn(page, APP, f.buyer_email, f.password);
        await launchAt(page, APP);

        await expect(card(page)).toHaveAttribute('data-listing', f.keys[0]);
        await expect(stateBtn(page, 'save')).toHaveClass(/is-active/);   // stored by the previous test

        await page.click('#vd-next');
        await expect(card(page)).toHaveAttribute('data-listing', f.keys[1]);
        await expect(slot(page)).toHaveAttribute('data-lp-listing-id', String(f.bridge_ids[1]));
        await expect(slot(page).locator('[data-lp-state].is-active')).toHaveCount(0);

        await stateBtn(page, 'pass').click();
        await expect(stateBtn(page, 'pass')).toHaveClass(/is-active/);

        await page.click('#vd-prev');
        await expect(card(page)).toHaveAttribute('data-listing', f.keys[0]);
        await expect(stateBtn(page, 'save')).toHaveClass(/is-active/);
        await expect(slot(page)).toHaveCount(1);

        // Moving between homes is one Look Around per home, and nothing else.
        expect((await page.evaluate(() => window.__fakeMapkit.counters())).lookArounds).toBe(3);
        expectNoProviderTraffic(record);
    });

    test('clear/undo removes the choice, and the history records the Virtual Drive in words', async ({ page }) => {
        const f = fixture();
        const record = await prepare(page, APP);
        await signIn(page, APP, f.buyer_email, f.password);
        await launchAt(page, APP);

        await stateBtn(page, 'save').click();             // reopen the tray on the active state
        const clear = page.waitForResponse((r) => r.url().endsWith('/listing-preferences/virtual-drive') && r.request().method() === 'DELETE');
        await slot(page).locator('[data-lp-clear]').click();
        expect((await clear).status()).toBe(200);
        await expect(slot(page).locator('[data-lp-state].is-active')).toHaveCount(0);

        await page.reload();
        await page.click('#vd-launch');
        await expect.poll(() => page.evaluate(() => window.__fakeMapkit.counters().lookArounds)).toBe(1);
        await expect(slot(page).locator('[data-lp-state].is-active')).toHaveCount(0);

        await page.goto(`${APP}/my/listing-preferences/history`);
        const first = page.locator('.list-group-item').first();
        await expect(first).toContainText('Preference removed');
        await expect(first).toContainText('Virtual Drive');
        await expect(page.locator('body')).toContainText(f.addresses[0]);
        await expect(page.locator('body')).not.toContainText(f.keys[0]);

        expectNoProviderTraffic(record);
    });

    test('on a phone, the open tray scrolls inside the card and Done and Remove stay reachable', async ({ page }) => {
        const f = fixture();
        await page.setViewportSize({ width: 390, height: 844 });
        const record = await prepare(page, APP);
        await signIn(page, APP, f.buyer_email, f.password);
        await launchAt(page, APP);

        await stateBtn(page, 'maybe').click();
        await expect(slot(page).locator('[data-lp-tray]')).toBeVisible();
        await slot(page).locator('[data-lp-chip]').first().click();

        const done = slot(page).locator('[data-lp-done]');
        await done.scrollIntoViewIfNeeded();
        await expect(done).toBeInViewport();
        const reasons = page.waitForResponse((r) => r.url().endsWith('/listing-preferences/virtual-drive/reasons'));
        await done.click();
        expect((await reasons).status()).toBe(200);
        // The control's own handler closes the tray after the response; wait for
        // it, or the reopen below races that close.
        await expect(slot(page).locator('[data-lp-status]')).toContainText('Saved.');
        await expect(slot(page).locator('[data-lp-tray]')).toBeHidden();

        await stateBtn(page, 'maybe').click();              // reopen: no write
        await expect(slot(page).locator('[data-lp-tray]')).toBeVisible();
        const remove = slot(page).locator('[data-lp-clear]');
        await remove.scrollIntoViewIfNeeded();
        await expect(remove).toBeInViewport();
        const clear = page.waitForResponse((r) => r.url().endsWith('/listing-preferences/virtual-drive') && r.request().method() === 'DELETE');
        await remove.click();
        expect((await clear).status()).toBe(200);

        expectNoProviderTraffic(record);
    });

    test('a guest sees the control, is routed through sign-in, and nothing is stored', async ({ page }) => {
        const record = await prepare(page, APP);
        await launchAt(page, APP);

        await expect(slot(page)).toHaveAttribute('data-lp-guest', '1');
        await stateBtn(page, 'save').click();
        await expect(page).toHaveURL(/\/login/);

        expect(record.preferenceWrites.filter((w) => w.method !== 'GET')).toEqual([]);
        expectNoProviderTraffic(record);
    });

    test('the Google page, switched off, loads no provider script and claims no launch', async ({ page }) => {
        const record = await prepare(page, APP);
        const claims = [];
        page.on('request', (r) => { if (r.url().includes('google-launch')) { claims.push(r.url()); } });

        await page.goto(`${APP}/dev/virtual-drive/google`);
        await expect(page.locator('script[src*="google-streetview-provider"]')).toHaveCount(0);
        await expect(page.locator('#vd-launch')).toBeDisabled();
        await page.waitForTimeout(500);

        expect(claims).toEqual([]);
        expectNoProviderTraffic(record);
    });
});

test.describe('Virtual Drive · feature flag OFF', () => {
    test('no control in the card, and Save reports why it is unavailable', async ({ page }) => {
        const f = fixture('-off');
        const record = await prepare(page, OFF);
        await signIn(page, OFF, f.buyer_email, f.password);

        // Developer view: it lists every action, unavailable ones with reasons.
        await page.goto(`${OFF}/dev/virtual-drive/apple`);
        await page.click('#vd-launch');
        await expect.poll(() => page.evaluate(() => window.__fakeMapkit.counters().lookArounds)).toBe(1);

        await expect(card(page)).toBeVisible();
        await expect(card(page).locator('[data-lp-control]')).toHaveCount(0);
        await expect(page.locator('[data-vd-preference-for]')).toHaveCount(0);
        await expect(page.locator('body')).toContainText('Saving properties is not switched on in this environment.');

        expect(record.preferenceWrites).toEqual([]);
        expectNoProviderTraffic(record);
    });
});
