/*
 |-----------------------------------------------------------------------------
 | "Your Home Taste" — Listing Preferences Phase 4, in a browser
 |-----------------------------------------------------------------------------
 |
 | Drives the REAL /my/listing-preferences/taste page on the isolated harness
 | (throwaway SQLite, Google blanked, network guard on). The fixture is seeded by
 | ListingPreferenceBrowserTestSeeder::seedHomeTaste() THROUGH the one writer.
 |
 | The PHP suite proves the rules, the gates and the wording. What only a browser
 | shows is the whole loop: a choice made with the real control on a real listing
 | page becomes a pattern on the customer's own page, in words, with no score and
 | no internal key anywhere on it — and that a deployment which sets nothing has
 | no such page at all.
 |
 | SERIAL, because the newcomer's second Save is observed on the page afterwards.
 */

const { test, expect } = require('@playwright/test');
const { installNetworkGuard } = require('./support/network');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const APP = 'http://127.0.0.1:8932';
const OFF = 'http://127.0.0.1:8933';

const fixture = (suffix = '') =>
    JSON.parse(fs.readFileSync(path.join(ROOT, `storage/app/lp-browser-fixture${suffix}.json`), 'utf8')).home_taste;

async function signIn(page, origin, email, password) {
    await installNetworkGuard(page, { allowOrigin: origin });
    await page.goto(`${origin}/login`);
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 20_000 });
}

const taste = (page) => page.locator('[data-home-taste]');

/** Nothing internal may reach the customer's page. */
async function expectNoInternals(page, f) {
    const text = await taste(page).innerText();

    for (const leak of [...f.internal_keys, 'smart_tag', 'seller_agent', 'byo:', 'mls:', 'strength',
        'agreement', 'established', 'emerging', 'positive', 'negative', '%', 'null', 'undefined']) {
        expect(text, `page text must not contain ${leak}`).not.toContain(leak);
    }
}

test.describe('Your Home Taste', () => {
    test.describe.configure({ mode: 'serial' });

    test('a buyer reaches it from Saved properties and reads their patterns in words', async ({ page }) => {
        const f = fixture();
        await signIn(page, APP, f.buyer_email, f.password);

        await page.goto(`${APP}/my/listing-preferences`);
        const link = page.locator('.nav-tabs a', { hasText: 'Your Home Taste' });
        await expect(link).toBeVisible();
        await link.click();

        await expect(page).toHaveURL(/\/my\/listing-preferences\/taste$/);
        await expect(page.locator('h4', { hasText: 'Your Home Taste' })).toBeVisible();
        await expect(taste(page)).toContainText('does not change which homes you are shown');

        const saved = page.locator('[data-home-taste-group="save"]');
        await expect(saved.locator('h5')).toHaveText('What you tend to Save');
        const liked = saved.locator('[data-home-taste-observation]', { hasText: f.liked_label });
        await expect(liked).toContainText('You often Save homes with this.');
        await expect(liked).toContainText('From 3 homes you chose: 3 Saved.');
        await expect(liked).toContainText('Based on reasons you picked.');
        // A reason they picked is never worded as a mere correlation, and the
        // page tells them the two kinds of pattern are labelled apart.
        await expect(liked).not.toContainText('not a reason you picked');
        await expect(taste(page)).toContainText('each one says which it is');

        const passed = page.locator('[data-home-taste-group="pass"]');
        await expect(passed.locator('h5')).toHaveText('What you tend to Pass on');
        const disliked = passed.locator('[data-home-taste-observation]', { hasText: f.disliked_label });
        await expect(disliked).toContainText('You tend to Pass on homes with this.');
        await expect(disliked).toContainText('From 2 homes you chose: 2 Passed.');

        await expectNoInternals(page, f);
    });

    test('one choice is not a taste: a newcomer sees the empty state', async ({ page }) => {
        const f = fixture();
        await signIn(page, APP, f.newcomer_email, f.password);

        await page.goto(`${APP}/my/listing-preferences/taste`);
        await expect(page.locator('[data-home-taste-empty]')).toContainText('No patterns yet.');
        await expect(page.locator('[data-home-taste-observation]')).toHaveCount(0);
        await expect(taste(page)).not.toContainText(f.liked_label);
    });

    test('a second agreeing Save made with the real control becomes a pattern', async ({ page }) => {
        const f = fixture();
        await signIn(page, APP, f.newcomer_email, f.password);

        await page.goto(`${APP}/offer-listing/seller/view/${f.live_listing_id}`);
        const control = page.locator('[data-lp-control]').first();
        await control.locator('[data-lp-state="save"]').click();
        await expect(control.locator('[data-lp-tray]')).toBeVisible();
        await control.locator(`[data-lp-chip="${f.internal_keys[0]}"]`).click();
        await control.locator('[data-lp-done]').click();
        await expect(control.locator('[data-lp-status]')).toContainText('Saved.');

        await page.goto(`${APP}/my/listing-preferences/taste`);
        const liked = page.locator('[data-home-taste-group="save"] [data-home-taste-observation]', { hasText: f.liked_label });
        await expect(liked).toContainText('You tend to Save homes with this.');
        await expect(liked).toContainText('From 2 homes you chose: 2 Saved.');
        await expectNoInternals(page, f);
    });
});

test.describe('Your Home Taste — feature flag OFF', () => {
    test('a deployment that sets nothing has no page and no link', async ({ page }) => {
        const f = fixture('-off');
        await signIn(page, OFF, f.buyer_email, f.password);

        const response = await page.goto(`${OFF}/my/listing-preferences/taste`);
        expect(response.status()).toBe(404);

        await page.goto(`${OFF}/`);
        await expect(page.locator('a', { hasText: 'Your Home Taste' })).toHaveCount(0);
    });
});
