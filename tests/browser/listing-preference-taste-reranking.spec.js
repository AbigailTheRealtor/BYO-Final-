/*
 |-----------------------------------------------------------------------------
 | Your Home Taste reranking — Listing Preferences Phase 5, in a browser
 |-----------------------------------------------------------------------------
 |
 | Drives the REAL /stellar/buyer/results page on the isolated harness
 | (throwaway SQLite, Bridge and Google credentials blanked, network guard on).
 | The fixture is ListingPreferenceBrowserTestSeeder::seedTasteRerank(): five
 | listings the real matcher scores identically, and a customer who has Saved
 | three Condominiums elsewhere — so exactly one result can be lifted, and only
 | inside a tie.
 |
 | The PHP suite proves the bound, the gates and the invariants. What only a
 | browser shows is what the customer meets: the personalized top card, the
 | caption beside Best Match, the one-click standard order, the words on the
 | moved card — and that every listing is still there whichever order is shown.
 */

const { test, expect } = require('@playwright/test');
const { installNetworkGuard } = require('./support/network');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const APP = 'http://127.0.0.1:8932';
const OFF = 'http://127.0.0.1:8933';

const fixture = (suffix = '') =>
    JSON.parse(fs.readFileSync(path.join(ROOT, `storage/app/lp-browser-fixture${suffix}.json`), 'utf8')).rerank;

async function signIn(page, origin, email, password) {
    await installNetworkGuard(page, { allowOrigin: origin });
    await page.goto(`${origin}/login`);
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 20_000 });
}

/** The listing keys of the rendered cards, in page order, read from their detail links. */
async function order(page, keys) {
    const hrefs = await page.locator('#stellar-results-grid [data-testid="view-details-btn"]').evaluateAll(
        (links) => links.map((a) => a.getAttribute('href') || ''),
    );

    return hrefs.map((href) => keys.find((k) => href.includes(k)) || href);
}

const caption = (page) => page.locator('[data-taste-rerank]');

test.describe('Your Home Taste reranking', () => {
    test('Best Match is personalized: the tied Condominium leads, explained in words', async ({ page }) => {
        const f = fixture();
        await signIn(page, APP, f.buyer_email, f.password);
        await page.goto(`${APP}${f.results_path}`);

        const personalized = await order(page, f.keys);
        expect(personalized[0]).toBe(f.personalized_key);
        expect([...personalized].sort()).toEqual([...f.keys].sort());

        await expect(caption(page)).toHaveAttribute('data-taste-rerank', 'personalized');
        await expect(caption(page)).toContainText('Personalized with');
        await expect(caption(page)).toContainText('Your Home Taste');

        const card = page.locator('#stellar-results-grid .col').first();
        const why  = card.locator('[data-taste-rerank-explanation]');
        await expect(why).toBeVisible();
        await expect(why).toContainText('Fits things you tend to Save');
        await expect(why).toContainText(`You tend to Save homes of this type (${f.subtype_label}).`);

        // A correlation is never claimed as a reason, and nothing internal shows.
        const text = await why.innerText();
        expect(text).not.toMatch(/reason you have picked|\d|%|_|score|weight|\bAI\b/i);

        // Only the card that moved is explained.
        await expect(page.locator('[data-taste-rerank-explanation]')).toHaveCount(1);
    });

    test('the standard Best Match order is one click away, and back', async ({ page }) => {
        const f = fixture();
        await signIn(page, APP, f.buyer_email, f.password);
        await page.goto(`${APP}${f.results_path}`);
        const personalized = await order(page, f.keys);

        await page.locator('[data-taste-rerank-toggle="standard"]').click();
        await expect(page).toHaveURL(/taste=off/);
        // Read the order only once the new page has rendered its own caption.
        await expect(caption(page)).toHaveAttribute('data-taste-rerank', 'standard');

        const standard = await order(page, f.keys);
        expect(standard[0]).toBe(f.standard_first);
        expect(standard).not.toEqual(personalized);
        expect([...standard].sort()).toEqual([...personalized].sort());
        await expect(page.locator('[data-taste-rerank-explanation]')).toHaveCount(0);

        await page.locator('[data-taste-rerank-toggle="personalized"]').click();
        await expect(page).not.toHaveURL(/taste=off/);
        await expect(caption(page)).toHaveAttribute('data-taste-rerank', 'personalized');
        expect(await order(page, f.keys)).toEqual(personalized);
    });

    test('an explicit sort is never personalized', async ({ page }) => {
        const f = fixture();
        await signIn(page, APP, f.buyer_email, f.password);

        await page.goto(`${APP}${f.results_path}&taste=off`);
        const standard = await order(page, f.keys);

        await page.goto(`${APP}${f.results_path}&sort=price_asc`);
        expect(await order(page, f.keys)).toEqual(standard);
        await expect(caption(page)).toHaveCount(0);
        await expect(page.locator('[data-taste-rerank-explanation]')).toHaveCount(0);
    });

    test('with the flags off the page is exactly the standard order and says nothing', async ({ page }) => {
        const f = fixture('-off');
        await signIn(page, OFF, f.buyer_email, f.password);
        await page.goto(`${OFF}${f.results_path}`);

        const keys = await order(page, f.keys);
        expect(keys[0]).toBe(f.standard_first);
        expect([...keys].sort()).toEqual([...f.keys].sort());
        await expect(caption(page)).toHaveCount(0);
        await expect(page.locator('[data-taste-rerank-explanation]')).toHaveCount(0);
    });
});
