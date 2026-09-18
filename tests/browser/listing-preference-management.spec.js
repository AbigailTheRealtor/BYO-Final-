/*
 |-----------------------------------------------------------------------------
 | Saved | Maybe | Passed — the customer's own management area, in a browser
 |-----------------------------------------------------------------------------
 |
 | Drives the REAL /my/listing-preferences pages on the isolated harness
 | (throwaway SQLite, Google blanked, network guard on). The fixture is seeded by
 | ListingPreferenceBrowserTestSeeder::seedManagement() THROUGH the one writer,
 | so the history rows read here are what real clicks produce.
 |
 | SERIAL, because one buyer's list is the subject: a recovered Pass and a
 | cleared Save are observed on later tabs and in the history, in order.
 |
 | The PHP suite already proves scoping, hydration and wording at the HTTP
 | layer. What only a browser can show is that the shared control works INSIDE
 | these cards — that a click here really moves a listing between tabs, and that
 | the history then says, in words, what happened.
 */

const { test, expect } = require('@playwright/test');
const { installNetworkGuard } = require('./support/network');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const APP = 'http://127.0.0.1:8932';
const OFF = 'http://127.0.0.1:8933';

const fixture = (suffix = '') =>
    JSON.parse(fs.readFileSync(path.join(ROOT, `storage/app/lp-browser-fixture${suffix}.json`), 'utf8')).manage;

async function signIn(page, origin, email, password) {
    await installNetworkGuard(page, { allowOrigin: origin });
    await page.goto(`${origin}/login`);
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 20_000 });
}

const tab = (page, state) => page.goto(`${APP}/my/listing-preferences?state=${state}`);
const cardFor = (page, text) => page.locator('.card', { hasText: text }).first();

/** Nothing internal may reach a customer's page. */
async function expectNoInternals(page) {
    const text = await page.locator('main, .container').first().innerText();

    for (const leak of ['seller_agent', 'landlord_agent', 'byo:', 'mls:', 'LP-MANAGE-MLS', 'LP-HIDDEN-HARBOR',
        'subject_key', 'listing_id', 'null', 'undefined', 'virtual_drive', 'to_state']) {
        expect(text, `page text must not contain ${leak}`).not.toContain(leak);
    }

    // Storage values never appear as labels.
    for (const raw of ['save', 'maybe', 'pass']) {
        expect(text.split(/\s+/)).not.toContain(raw);
    }
}

test.describe('Saved | Maybe | Passed — management area', () => {
    test.describe.configure({ mode: 'serial' });

    test('a buyer opens the page from the account menu and sees Saved, with reasons and counts', async ({ page }) => {
        const f = fixture();
        await signIn(page, APP, f.buyer_email, f.password);

        await page.goto(`${APP}/`);
        const link = page.locator('a.dropdown-item', { hasText: 'Saved Properties' });
        await expect(link).toHaveAttribute('href', /\/my\/listing-preferences$/);

        await tab(page, 'save');
        await expect(page.locator('h4', { hasText: 'Your properties' })).toBeVisible();

        const tabs = page.locator('.nav-tabs .nav-link');
        await expect(tabs.filter({ hasText: 'Saved' })).toContainText('(2)');
        await expect(tabs.filter({ hasText: 'Maybe' })).toContainText('(2)');
        await expect(tabs.filter({ hasText: 'Passed' })).toContainText('(2)');
        await expect(tabs.filter({ hasText: 'Saved' })).toHaveClass(/active/);

        const saved = cardFor(page, f.saved_address);
        await expect(saved).toBeVisible();
        // The CURRENT state is shown on the card's own control.
        await expect(saved.locator('[data-lp-state="save"]')).toHaveClass(/is-active/);
        // The stored reasons are VISIBLE on the card, as catalog labels, without
        // opening anything.
        for (const label of f.save_reason_labels) {
            await expect(saved.locator('[data-lp-card-reasons]')).toContainText(label);
        }
        await expect(saved.locator('[data-lp-tray]')).toBeHidden();

        await expect(page.locator('body')).not.toContainText(f.maybe_address);
        await expectNoInternals(page);
    });

    test('an MLS listing whose feed withholds the street shows only its locality', async ({ page }) => {
        const f = fixture();
        await signIn(page, APP, f.buyer_email, f.password);
        await tab(page, 'save');

        await expect(page.locator('body')).not.toContainText(f.hidden_street);
        const card = cardFor(page, f.hidden_locality);
        await expect(card).toBeVisible();
        await expect(card).toContainText('Seller Offer Listing');
    });

    test('Maybe lists BYO and MLS listings alike; Passed keeps a removed listing, safely', async ({ page }) => {
        const f = fixture();
        await signIn(page, APP, f.buyer_email, f.password);

        await tab(page, 'maybe');
        await expect(cardFor(page, f.maybe_address)).toBeVisible();
        await expect(cardFor(page, f.bridge_address)).toBeVisible();
        await expect(cardFor(page, f.bridge_address)).toContainText('MLS listing');
        await expect(cardFor(page, f.bridge_address).locator('[data-lp-state="maybe"]')).toHaveClass(/is-active/);

        await tab(page, 'pass');
        await expect(page.locator('body')).toContainText('Passing hides nothing permanently');
        await expect(cardFor(page, f.passed_address)).toBeVisible();
        for (const label of f.pass_reason_labels) {
            await expect(cardFor(page, f.passed_address)).toContainText(label);
        }

        // The listing deleted after it was passed: a card that says so, invents
        // nothing, and still offers the customer their own choice back.
        const gone = cardFor(page, 'This listing is no longer available');
        await expect(gone).toBeVisible();
        await expect(gone).not.toContainText('Gone Street');
        await expect(gone.locator('[data-lp-state]')).toHaveCount(3);

        await expectNoInternals(page);
    });

    test('a Passed property is recovered to Maybe, and moves tabs', async ({ page }) => {
        const f = fixture();
        await signIn(page, APP, f.buyer_email, f.password);
        await tab(page, 'pass');

        const card = cardFor(page, f.passed_address);
        const write = page.waitForResponse((r) =>
            r.url().includes('/listing-preferences/account') && r.request().method() === 'POST');
        await card.locator('[data-lp-state="maybe"]').click();
        expect((await write).status()).toBe(200);
        await expect(card.locator('[data-lp-state="maybe"]')).toHaveClass(/is-active/);

        await tab(page, 'pass');
        await expect(page.locator('body')).not.toContainText(f.passed_address);

        await tab(page, 'maybe');
        await expect(cardFor(page, f.passed_address)).toBeVisible();
        await expect(page.locator('.nav-tabs .nav-link', { hasText: 'Maybe' })).toContainText('(3)');
    });

    test('a Saved property is cleared, and leaves every tab', async ({ page }) => {
        const f = fixture();
        await signIn(page, APP, f.buyer_email, f.password);
        await tab(page, 'save');

        const card = cardFor(page, f.saved_address);

        // Reopening the tray on the CURRENT state reviews the reasons and writes
        // nothing — it used to post an empty reason set, which wiped them and
        // appended a history event the customer never made.
        const writes = [];
        page.on('request', (r) => { if (r.url().includes('/listing-preferences/') && r.method() === 'POST') { writes.push(r.url()); } });
        await card.locator('[data-lp-state="save"]').click();   // opens the tray
        await expect(card.locator('[data-lp-tray]')).toBeVisible();
        await expect(card.locator('[data-lp-chip].is-selected')).toHaveCount(f.save_reason_labels.length);
        expect(writes).toEqual([]);

        const clear = page.waitForResponse((r) =>
            r.url().includes('/listing-preferences/account') && r.request().method() === 'DELETE');
        await card.locator('[data-lp-clear]').click();
        expect((await clear).status()).toBe(200);
        await expect(card.locator('[data-lp-state].is-active')).toHaveCount(0);

        for (const state of ['save', 'maybe', 'pass']) {
            await tab(page, state);
            await expect(page.locator('body')).not.toContainText(f.saved_address);
        }
    });

    test('the history tells the story in words, including where each choice was made', async ({ page }) => {
        const f = fixture();
        await signIn(page, APP, f.buyer_email, f.password);
        await page.goto(`${APP}/my/listing-preferences/history`);

        const rows = page.locator('.list-group-item');

        // Newest first: the clear made above, then the recovery.
        await expect(rows.nth(0)).toContainText(f.saved_address);
        await expect(rows.nth(0)).toContainText('Preference removed');
        await expect(rows.nth(0)).toContainText('Your preferences');
        await expect(rows.nth(1)).toContainText(f.passed_address);
        await expect(rows.nth(1)).toContainText('Changed from Passed to Maybe');
        await expect(rows.nth(1)).toContainText('Your preferences');

        // The seeded withdrawal reads as a withdrawal, never as null.
        await expect(page.locator('.list-group-item', { hasText: f.cleared_address }).first())
            .toContainText('Preference removed');
        await expect(page.locator('body')).toContainText('Marked Saved');
        await expect(page.locator('body')).toContainText('A listing that is no longer available');

        await expectNoInternals(page);
    });

    test('another customer\'s choices, and the other market\'s, are never shown', async ({ page }) => {
        const f = fixture();
        await signIn(page, APP, f.buyer_email, f.password);

        for (const url of ['?state=save', '?state=maybe', '?state=pass', '/history']) {
            await page.goto(`${APP}/my/listing-preferences${url}`);
            await expect(page.locator('body')).not.toContainText(f.secret_address);
            await expect(page.locator('body')).not.toContainText(f.landlord_title);
        }

        // There is no id in the URL to tamper with: asking for someone else is
        // simply not expressible, and extra parameters change nothing.
        await page.goto(`${APP}/my/listing-preferences?state=save&user_id=1&user=2`);
        await expect(page.locator('body')).not.toContainText(f.secret_address);
    });

    test('a tenant sees their own rental choices and none of the buyer\'s', async ({ page }) => {
        const f = fixture();
        await signIn(page, APP, f.tenant_email, f.password);
        await tab(page, 'save');

        await expect(cardFor(page, f.landlord_title)).toBeVisible();
        await expect(cardFor(page, f.landlord_title)).toContainText('Rental listing');
        await expect(page.locator('body')).not.toContainText(f.hidden_locality);
        await expect(page.locator('body')).not.toContainText(f.bridge_address);
    });

    test('a guest is sent to sign in and sees nothing', async ({ page }) => {
        await installNetworkGuard(page, { allowOrigin: APP });
        await page.goto(`${APP}/my/listing-preferences`);
        await expect(page).toHaveURL(/\/login/);
    });
});

test.describe('Saved | Maybe | Passed — feature flag OFF', () => {
    test('no menu entry, and the pages do not exist', async ({ page }) => {
        const f = fixture('-off');
        await signIn(page, OFF, f.buyer_email, f.password);

        await page.goto(`${OFF}/`);
        await expect(page.locator('a.dropdown-item', { hasText: 'Saved Properties' })).toHaveCount(0);

        for (const url of ['/my/listing-preferences', '/my/listing-preferences/history']) {
            const response = await page.goto(`${OFF}${url}`);
            expect(response.status()).toBe(404);
        }
    });
});
