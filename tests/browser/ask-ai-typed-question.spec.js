/*
 |-----------------------------------------------------------------------------
 | Ask AI question picker — search FILTERS, selection answers, nothing leaves the page
 |-----------------------------------------------------------------------------
 |
 | The file name is historical (Batch 3's typed-question box, now retired). Ask AI is
 | SELECTION-BASED: the modal lists every verified question with its precomputed answer,
 | "Search questions..." only filters that list, and the viewer then selects one.
 |
 | PHPUnit can read the markup and the picker's source, but it cannot type or press Enter.
 | It cannot see a keystroke listener that fires a request, an Enter handler that submits,
 | or a filter that hides the wrong questions. This spec drives the real
 | public/js/ask-ai/question-picker.js against a fixture that TypedQuestionBrowserFixtureParityTest
 | requires to be byte-for-byte what the seller and landlord pages render, and asserts on what
 | the page ATTEMPTED to send — the guard in support/network.js records attempts, not outcomes.
 */

const { test, expect } = require('@playwright/test');
const { installNetworkGuard } = require('./support/network');

const PAGE = '/ask-ai/typed-question.html';

/** Requests the page made that were not the fixture document or the picker itself. */
function extraRequests(record) {
    return record.all.filter(
        (url) => !url.endsWith(PAGE) && !url.includes('/ask-ai-js/question-picker.js')
    );
}

function picker(page, role) {
    return page.locator(`[data-ask-ai-picker="${role}"]`);
}

/** Ids of the View-all entries currently visible (the list itself must be shown too). */
async function visibleIds(root) {
    const list = root.locator('[data-ask-ai-picker-all]');
    if (!(await list.isVisible())) {
        return [];
    }
    return list.locator('[data-ask-ai-pick]').evaluateAll(
        (els) => els.filter((el) => !el.hidden).map((el) => el.getAttribute('data-ask-ai-pick'))
    );
}

async function allIds(root) {
    return root.locator('[data-ask-ai-picker-all] [data-ask-ai-pick]').evaluateAll(
        (els) => els.map((el) => el.getAttribute('data-ask-ai-pick'))
    );
}

async function search(root, text) {
    await root.locator('[data-ask-ai-picker-search]').fill(text);
    return visibleIds(root);
}

test.describe('Ask AI question picker', () => {
    test('starts with recommended questions only; View all reveals every question and Hide all collapses', async ({ page }) => {
        const record = await installNetworkGuard(page);
        await page.goto(PAGE);
        const root = picker(page, 'seller');

        const recommended = await root.locator('.ask-ai-picker-recommended [data-ask-ai-pick]').count();
        expect(recommended).toBeGreaterThanOrEqual(4);
        expect(recommended).toBeLessThanOrEqual(6);
        await expect(root.locator('[data-ask-ai-picker-all]')).toBeHidden();
        await expect(root.locator('[data-ask-ai-picker-answer]')).toBeHidden();

        const toggle = root.locator('[data-ask-ai-picker-toggle]');
        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        await expect(toggle).toHaveText('Hide all questions');
        expect(await visibleIds(root)).toEqual(await allIds(root));
        expect((await allIds(root)).length).toBeGreaterThan(recommended);

        // Capped and scrolling rather than growing the modal.
        const overflow = await root.locator('[data-ask-ai-picker-all]').evaluate((el) => getComputedStyle(el).overflowY);
        expect(overflow).toBe('auto');

        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-expanded', 'false');
        await expect(root.locator('[data-ask-ai-picker-all]')).toBeHidden();
        expect(extraRequests(record)).toEqual([]);
    });

    test('search filters through the deterministic aliases', async ({ page }) => {
        const record = await installNetworkGuard(page);
        await page.goto(PAGE);
        const root = picker(page, 'seller');

        const cases = {
            roof: ['seller_roof_type', 'kb_seller_roof_age_and_condition'],
            'age of roof': ['kb_seller_roof_age_and_condition'],
            taxes: ['seller_property_taxes'],
            utilities: ['seller_utilities'],
            baths: ['seller_bathrooms'],
            sqft: ['seller_heated_square_feet'],
        };
        for (const [query, expected] of Object.entries(cases)) {
            const ids = await search(root, query);
            for (const id of expected) {
                expect(ids, `"${query}" should surface ${id}`).toContain(id);
            }
            expect(ids.length, `"${query}" should narrow the list`).toBeLessThan((await allIds(root)).length);
        }

        // No match says so and shows no list.
        expect(await search(root, 'zzqx nothing')).toEqual([]);
        await expect(root.locator('[data-ask-ai-picker-empty]')).toBeVisible();

        // Clearing returns to the collapsed state.
        expect(await search(root, '')).toEqual([]);
        await expect(root.locator('[data-ask-ai-picker-empty]')).toBeHidden();
        expect(extraRequests(record)).toEqual([]);
    });

    test('typing and pressing Enter send nothing and answer nothing', async ({ page }) => {
        const record = await installNetworkGuard(page);
        await page.goto(PAGE);
        const root = picker(page, 'seller');
        const input = root.locator('[data-ask-ai-picker-search]');

        await input.type('How much are the taxes?');
        await input.press('Enter');
        await input.type(' roof');
        await input.press('Enter');

        await expect(root.locator('[data-ask-ai-picker-answer]')).toBeHidden();
        expect(await root.locator('[aria-pressed="true"]').count()).toBe(0);
        expect(page.url()).toContain(PAGE);
        expect(extraRequests(record)).toEqual([]);
    });

    test('selecting a question — featured or not — shows its precomputed answer', async ({ page }) => {
        const record = await installNetworkGuard(page);
        await page.goto(PAGE);
        const root = picker(page, 'seller');
        const featured = await root.locator('.ask-ai-picker-recommended [data-ask-ai-pick]').evaluateAll(
            (els) => els.map((el) => el.getAttribute('data-ask-ai-pick'))
        );

        async function expectAnswerFor(id) {
            const expected = (await root.locator(`[data-ask-ai-answer-for="${id}"]`).textContent()).trim();
            const panel = root.locator('[data-ask-ai-picker-answer]');
            await expect(panel).toBeVisible();
            await expect(panel).toHaveAttribute('data-ask-ai-selected', id);
            await expect(root.locator('[data-ask-ai-picker-answer-text]')).toHaveText(expected);
        }

        // A featured question, from the recommended row.
        await root.locator(`.ask-ai-picker-recommended [data-ask-ai-pick="${featured[0]}"]`).click();
        await expectAnswerFor(featured[0]);

        // A NON-featured question, found by search and selected from the list.
        for (const id of ['seller_utilities', 'kb_seller_roof_age_and_condition']) {
            expect(featured).not.toContain(id);
            await search(root, id === 'seller_utilities' ? 'utilities' : 'age of roof');
            await root.locator(`[data-ask-ai-picker-all] [data-ask-ai-pick="${id}"]`).click();
            await expectAnswerFor(id);
            await expect(root.locator(`[data-ask-ai-picker-all] [data-ask-ai-pick="${id}"]`)).toHaveAttribute('aria-pressed', 'true');
        }
        expect(extraRequests(record)).toEqual([]);
    });

    test('one modal leaves the other alone', async ({ page }) => {
        await installNetworkGuard(page);
        await page.goto(PAGE);

        await search(picker(page, 'seller'), 'taxes');
        await expect(picker(page, 'landlord').locator('[data-ask-ai-picker-all]')).toBeHidden();
        expect(await search(picker(page, 'landlord'), 'flood zone')).toContain('landlord_flood_zone');
    });

    test('the page carries no form and no named input', async ({ page }) => {
        await installNetworkGuard(page);
        await page.goto(PAGE);

        expect(await page.locator('form').count()).toBe(0);
        expect(await page.locator('input[name]').count()).toBe(0);
        expect(await page.locator('textarea').count()).toBe(0);
    });
});
