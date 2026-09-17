/*
 |-----------------------------------------------------------------------------
 | Ask AI typed question — matching happens in the browser, and nothing leaves it
 |-----------------------------------------------------------------------------
 |
 | PHPUnit can read the markup and the matcher's source, but it cannot press Enter.
 | It cannot see a submit handler that navigates, a keystroke listener that fires a
 | request, or an input that silently posts because something wrapped it in a form.
 | This spec types into the real matcher, against a fixture generated from the real
 | service output, and asserts on what the page ATTEMPTED to send.
 |
 | Asserting on attempts rather than on outcomes is the whole point, and the guard in
 | support/network.js proves itself in harness.spec.js by contacting a forbidden host
 | on purpose — so "no requests" here is a real absence rather than a recorder that
 | was never wired up.
 */

const { test, expect } = require('@playwright/test');
const { installNetworkGuard } = require('./support/network');

const PAGE = '/ask-ai/typed-question.html';

/** Requests the page made that were not the fixture document or the matcher itself. */
function extraRequests(record) {
    return record.all.filter(
        (url) => !url.endsWith(PAGE) && !url.includes('/ask-ai-js/deterministic-question-matcher.js')
    );
}

async function ask(page, role, text) {
    const card = page.locator(`[data-ask-ai-property-questions="${role}"]`);
    await card.locator('[data-ask-ai-ask-input]').fill(text);
    await card.locator('[data-ask-ai-ask-button]').click();
    return card;
}

/** Which question ids are currently open in a card. */
function openIds(card) {
    return card.locator('details[open]').evaluateAll(
        (els) => els.map((el) => el.getAttribute('data-property-question'))
    );
}

test.describe('typed question matching', () => {
    test('seller: "How much is the HOA?" opens the HOA answer and sends nothing', async ({ page }) => {
        const record = await installNetworkGuard(page);
        await page.goto(PAGE);

        const card = await ask(page, 'seller', 'How much is the HOA?');

        expect(await openIds(card)).toEqual(['seller_hoa_fee_coverage']);
        await expect(card.locator('[data-property-answer="seller_hoa_fee_coverage"]')).toBeVisible();
        expect(extraRequests(record)).toEqual([]);
    });

    test('landlord: "flood zone" opens the flood answer and sends nothing', async ({ page }) => {
        const record = await installNetworkGuard(page);
        await page.goto(PAGE);

        const card = await ask(page, 'landlord', 'flood zone');

        expect(await openIds(card)).toEqual(['landlord_flood_zone']);
        await expect(card.locator('[data-property-answer="landlord_flood_zone"]'))
            .toContainText('FEMA Flood Zone VE');
        expect(extraRequests(record)).toEqual([]);
    });

    test('buyer: "budget" opens the budget answer and sends nothing', async ({ page }) => {
        const record = await installNetworkGuard(page);
        await page.goto(PAGE);

        const card = await ask(page, 'buyer', 'budget');

        expect(await openIds(card)).toEqual(['buyer_budget']);
        await expect(card.locator('[data-property-answer="buyer_budget"]'))
            .toContainText('looking for a purchase price up to');
        expect(extraRequests(record)).toEqual([]);
    });

    test('tenant: "When do they want to move?" opens the move-in answer and sends nothing', async ({ page }) => {
        const record = await installNetworkGuard(page);
        await page.goto(PAGE);

        const card = await ask(page, 'tenant', 'When do they want to move?');

        expect(await openIds(card)).toEqual(['tenant_move_in']);
        expect(extraRequests(record)).toEqual([]);
    });

    test('an unknown question says so locally and sends nothing', async ({ page }) => {
        const record = await installNetworkGuard(page);
        await page.goto(PAGE);

        const card = await ask(page, 'seller', 'Who is the neighbor?');

        await expect(card.locator('[data-ask-ai-ask-status]'))
            .toHaveText("I don't have a verified answer for that yet. Choose one of the available questions below.");
        expect(await openIds(card)).toEqual([]);
        expect(extraRequests(record)).toEqual([]);
    });

    test('a question this listing cannot answer is not matchable', async ({ page }) => {
        const record = await installNetworkGuard(page);
        await page.goto(PAGE);

        // The buyer card carries no acreage question, so its vocabulary is absent — the
        // browser has nothing to match against however the words are typed.
        const card = await ask(page, 'buyer', 'acreage');

        await expect(card.locator('[data-ask-ai-ask-status]')).toContainText("don't have a verified answer");
        expect(await openIds(card)).toEqual([]);
        expect(extraRequests(record)).toEqual([]);
    });

    test('Enter submits, and typing alone does nothing', async ({ page }) => {
        const record = await installNetworkGuard(page);
        await page.goto(PAGE);

        const card = page.locator('[data-ask-ai-property-questions="seller"]');
        const input = card.locator('[data-ask-ai-ask-input]');

        // Typing must not match, submit or navigate — only explicit submission acts.
        await input.type('taxes');
        expect(await openIds(card)).toEqual([]);
        await expect(card.locator('[data-ask-ai-ask-status]')).toHaveText('');

        await input.press('Enter');
        expect(await openIds(card)).toEqual(['seller_property_taxes']);
        expect(page.url()).toContain(PAGE);
        expect(extraRequests(record)).toEqual([]);
    });

    test('punctuation, case and spacing do not change the result', async ({ page }) => {
        const record = await installNetworkGuard(page);
        await page.goto(PAGE);

        for (const text of ['taxes', '  TAXES  ', 'Taxes?', 'What are the taxes?']) {
            const card = await ask(page, 'seller', text);
            expect(await openIds(card), `query: ${text}`).toEqual(['seller_property_taxes']);
        }

        expect(extraRequests(record)).toEqual([]);
    });

    test('a successful match replaces the previous no-match message', async ({ page }) => {
        await installNetworkGuard(page);
        await page.goto(PAGE);

        const card = await ask(page, 'seller', 'Who is the neighbor?');
        await expect(card.locator('[data-ask-ai-ask-status]')).toContainText("don't have a verified answer");

        await ask(page, 'seller', 'taxes');
        await expect(card.locator('[data-ask-ai-ask-status]')).not.toContainText("don't have a verified answer");
        expect(await openIds(card)).toEqual(['seller_property_taxes']);
    });

    test('matching one card leaves the other cards alone', async ({ page }) => {
        await installNetworkGuard(page);
        await page.goto(PAGE);

        await ask(page, 'seller', 'taxes');

        for (const role of ['landlord', 'buyer', 'tenant']) {
            expect(await openIds(page.locator(`[data-ask-ai-property-questions="${role}"]`)), role).toEqual([]);
        }
    });

    test('the matched summary is focused, and details stay native', async ({ page }) => {
        await installNetworkGuard(page);
        await page.goto(PAGE);

        const card = await ask(page, 'seller', 'taxes');

        const focusedId = await page.evaluate(() => {
            const el = document.activeElement.closest('details');
            return el ? el.getAttribute('data-property-question') : null;
        });
        expect(focusedId).toBe('seller_property_taxes');

        // Still a real disclosure widget: clicking the summary collapses it again.
        await card.locator('details[data-property-question="seller_property_taxes"] summary').click();
        expect(await openIds(card)).toEqual([]);
    });

    test('the page carries no form and no named input', async ({ page }) => {
        await installNetworkGuard(page);
        await page.goto(PAGE);

        expect(await page.locator('form').count()).toBe(0);
        expect(await page.locator('input[name]').count()).toBe(0);
    });
});
