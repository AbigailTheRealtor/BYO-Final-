/*
 |-----------------------------------------------------------------------------
 | Save | Maybe | Pass on RESULT CARDS — the real search page, in a real browser
 |-----------------------------------------------------------------------------
 |
 | WHY THIS IS SEPARATE FROM listing-preference.spec.js
 | ---------------------------------------------------
 | That file proves the control works on a detail page: one listing, one
 | control, a tray in normal flow. The card surface has properties a
 | single-control page cannot have at all —
 |
 |   • many independent controls on one document,
 |   • exactly ONE stylesheet, ONE behaviour block and ONE chip catalog between
 |     them,
 |   • one delegated handler that must address the control that was clicked and
 |     not the first one on the page,
 |   • a tray that floats instead of growing its card,
 |   • and, on the landlord grid, a card that is itself a link — where a button
 |     in the wrong place navigates away instead of saving.
 |
 | The PHP suite can count markup, and does. What it cannot do is press a button
 | on card three and watch the right row change, which is exactly where a
 | delegated handler goes wrong.
 |
 | SAME APPLICATION, SAME HARNESS. These run against the `app` project's server
 | on an isolated SQLite database — see tests/browser/support/app-server.js. No
 | Google, no provider, no production data.
 */

const { test, expect } = require('@playwright/test');
const { installNetworkGuard } = require('./support/network');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const APP = 'http://127.0.0.1:8932';

async function guard(page, origin = APP) {
    await installNetworkGuard(page, { allowOrigin: origin });
}

/** Written by ListingPreferenceBrowserTestSeeder when the harness boots. */
function fixture() {
    return JSON.parse(fs.readFileSync(path.join(ROOT, 'storage/app/lp-browser-fixture.json'), 'utf8'));
}

async function signIn(page) {
    const { email, password } = fixture();

    await page.goto('/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 20_000 });
}

/** Every control on the page, in document order. */
const controls = (page) => page.locator('[data-lp-control]');

/** The control belonging to one listing, addressed by the id it carries. */
const controlFor = (page, id) => page.locator(`[data-lp-control][data-lp-listing-id="${id}"]`);

async function openSearch(page) {
    await page.goto(fixture().search_path);
    await expect(controls(page).first()).toBeVisible({ timeout: 20_000 });
}

/*
 | Start every test from "this customer has decided nothing".
 |
 | These tests WRITE, against one shared fixture and one database, so a test
 | that inherits the previous one's Save reads a stale card as a bug in the one
 | under way. Clearing through the control rather than the database keeps the
 | reset on the same path the product uses — a reset that bypassed the endpoint
 | could hide a broken clear.
 */
async function resetCards(page) {
    for (const id of fixture().card_listing_ids) {
        const card = controlFor(page, id);
        const active = card.locator('[data-lp-state][aria-pressed="true"]');

        if (await active.count() === 0) { continue; }

        // The tray's Remove is the only clear affordance, and it is inside the
        // tray — so open it first, exactly as a customer would.
        await active.first().click();
        await expect(card.locator('[data-lp-tray]')).toBeVisible();
        await card.locator('[data-lp-clear]').click();
        await expect(card.locator('[data-lp-status]')).toContainText('Removed.');
    }
}

test.describe('Save | Maybe | Pass — result cards', () => {
    /*
     | SERIAL, because these share one fixture and one database. Run in
     | parallel they interleave writes on the same four listings and fail in
     | each other's cleanup — a flake indistinguishable from a real defect.
     */
    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async ({ page }) => {
        await guard(page);
        await signIn(page);
        await openSearch(page);
        await resetCards(page);
    });

    test('every card on the page carries its own control', async ({ page }) => {
        const { card_listing_ids: ids } = fixture();

        expect(ids.length, 'the fixture must supply a real card page').toBeGreaterThan(1);

        for (const id of ids) {
            await expect(controlFor(page, id)).toBeVisible();
            await expect(controlFor(page, id).locator('[data-lp-state="save"]')).toBeVisible();
            await expect(controlFor(page, id).locator('[data-lp-state="maybe"]')).toBeVisible();
            await expect(controlFor(page, id).locator('[data-lp-state="pass"]')).toBeVisible();
        }
    });

    /*
     | THE PAGE PAYS FOR ITS ASSETS ONCE.
     |
     | The component is emitted per card; its stylesheet and behaviour are
     | wrapped in @once and its chip catalog is written per CONTEXT. A
     | regression here is invisible to a user and expensive on a long page, so
     | it is counted rather than trusted.
     */
    test('the stylesheet, the behaviour and the chip catalog are emitted once', async ({ page }) => {
        const counts = await page.evaluate(() => ({
            controls: document.querySelectorAll('[data-lp-control]').length,
            catalogs: document.querySelectorAll('[data-lp-chip-catalog]').length,
            // The component's own style block, identified by a rule only it has.
            styles: Array.from(document.querySelectorAll('style'))
                .filter((el) => el.textContent.includes('.lp-btn.is-active')).length,
        }));

        expect(counts.controls).toBeGreaterThan(1);
        expect(counts.catalogs, 'one chip catalog per context, not per card').toBe(1);
        expect(counts.styles, 'one stylesheet for the whole page').toBe(1);
    });

    /*
     | THE DELEGATED HANDLER ADDRESSES THE CARD THAT WAS CLICKED.
     |
     | One listener serves every control, so the bug this guards against is not
     | "nothing happens" — it is "the first card changes". A page with one
     | control cannot detect it.
     */
    test('pressing Save on the second card changes only the second card', async ({ page }) => {
        const [first, second] = fixture().card_listing_ids;

        await controlFor(page, second).locator('[data-lp-state="save"]').click();

        await expect(controlFor(page, second).locator('[data-lp-state="save"]'))
            .toHaveAttribute('aria-pressed', 'true');

        await expect(controlFor(page, first).locator('[data-lp-state="save"]'))
            .toHaveAttribute('aria-pressed', 'false');
    });

    test('a card choice survives a reload, on the card it was made on', async ({ page }) => {
        const [, second] = fixture().card_listing_ids;

        await controlFor(page, second).locator('[data-lp-state="pass"]').click();
        await expect(controlFor(page, second).locator('[data-lp-state="pass"]'))
            .toHaveAttribute('aria-pressed', 'true');

        await openSearch(page);

        await expect(controlFor(page, second).locator('[data-lp-state="pass"]'))
            .toHaveAttribute('aria-pressed', 'true');
    });

    /*
     | A card page must not open every tray on load. Twelve decided listings
     | would mean twelve overlapping panels covering the cards beneath them.
     */
    test('no reason tray is open when the page loads', async ({ page }) => {
        const [first] = fixture().card_listing_ids;

        // Give one card a stored choice, then come back to the page fresh.
        await controlFor(page, first).locator('[data-lp-state="save"]').click();
        await expect(controlFor(page, first).locator('[data-lp-tray]')).toBeVisible();

        await openSearch(page);

        const open = await page.locator('[data-lp-tray]:not([hidden])').count();
        expect(open, 'a results page must not open trays on load').toBe(0);

        // …and the choice itself is still there.
        await expect(controlFor(page, first).locator('[data-lp-state="save"]'))
            .toHaveAttribute('aria-pressed', 'true');
    });

    /* The tray opens on demand, with the server's chips, and Done saves once. */
    test('the tray opens on a card and reasons save through the shared endpoint', async ({ page }) => {
        const [first] = fixture().card_listing_ids;
        const card = controlFor(page, first);

        let writes = 0;
        page.on('request', (r) => {
            if (r.method() !== 'GET' && r.url().includes('/listing-preferences')) { writes++; }
        });

        await card.locator('[data-lp-state="maybe"]').click();
        await expect(card.locator('[data-lp-tray]')).toBeVisible();

        const chips = card.locator('[data-lp-chip]');
        expect(await chips.count(), 'chips come from the catalog, not the template').toBeGreaterThan(0);

        const before = writes;
        await chips.nth(0).click();
        await chips.nth(1).click();
        expect(writes, 'selecting chips is browsing, not deciding').toBe(before);

        await card.locator('[data-lp-done]').click();
        await expect(card.locator('[data-lp-status]')).toContainText('Saved.');
        expect(writes - before, 'one Done is one write').toBe(1);
    });

    /* Clear/undo works from a card, not only from a detail page. */
    test('a card choice can be removed', async ({ page }) => {
        const [first] = fixture().card_listing_ids;
        const card = controlFor(page, first);

        await card.locator('[data-lp-state="pass"]').click();
        await expect(card.locator('[data-lp-state="pass"]')).toHaveAttribute('aria-pressed', 'true');

        await card.locator('[data-lp-clear]').click();
        await expect(card.locator('[data-lp-status]')).toContainText('Removed.');

        for (const state of ['save', 'maybe', 'pass']) {
            await expect(card.locator(`[data-lp-state="${state}"]`)).toHaveAttribute('aria-pressed', 'false');
        }
    });

    /*
     | ONE OPEN TRAY. Card trays float over their neighbours, so two at once
     | overlap into an unreadable stack.
     */
    test('opening a tray on one card closes the tray on another', async ({ page }) => {
        const [first, second] = fixture().card_listing_ids;

        await controlFor(page, first).locator('[data-lp-state="save"]').click();
        await expect(controlFor(page, first).locator('[data-lp-tray]')).toBeVisible();

        await controlFor(page, second).locator('[data-lp-state="save"]').click();
        await expect(controlFor(page, second).locator('[data-lp-tray]')).toBeVisible();
        await expect(controlFor(page, first).locator('[data-lp-tray]')).toBeHidden();
    });

    /*
     | THE TRAY DOES NOT GROW THE CARD.
     |
     | In normal flow an open tray pushes the card taller and re-flows the whole
     | grid around it. Measured rather than asserted from CSS text, because what
     | matters is the rendered box.
     */
    /*
     | THE TRAY MUST BE REACHABLE, NOT MERELY PRESENT.
     |
     | This replaces an assertion that the tray floats without growing its card.
     | It did neither: it was absolutely positioned inside a `.card` that sets
     | `overflow: hidden`, so the browser clipped it — laid out, painted nowhere,
     | 236px past the card's own bottom edge.
     |
     | Nothing caught it. `getBoundingClientRect()` returned a 240px box and
     | `toBeVisible()` passed, because the element really was laid out. The only
     | question that distinguishes a usable panel from a clipped one is whether
     | the browser will hand you the chip when you point at it — so that is what
     | is asked here, with `elementFromPoint` and with a real click.
     */
    test('the open tray is actually reachable, not clipped by the card', async ({ page }) => {
        const [first] = fixture().card_listing_ids;
        const card = controlFor(page, first);

        await card.locator('[data-lp-state="save"]').click();
        await expect(card.locator('[data-lp-tray]')).toBeVisible();

        const probe = await page.evaluate((id) => {
            const ctl = document.querySelector(`[data-lp-control][data-lp-listing-id="${id}"]`);
            const tray = ctl.querySelector('[data-lp-tray]');
            const chip = tray.querySelector('[data-lp-chip]');
            const box = chip.getBoundingClientRect();
            const hit = document.elementFromPoint(
                Math.round(box.left + box.width / 2),
                Math.round(box.top + box.height / 2)
            );
            const host = ctl.closest('.card') || ctl.parentElement;
            const tb = tray.getBoundingClientRect();
            return {
                chipIsTheTopElement: hit === chip || chip.contains(hit),
                trayWithinItsCard: tb.bottom <= host.getBoundingClientRect().bottom + 1,
                clippedAncestor: (() => {
                    let n = tray.parentElement;
                    while (n && n !== document.body) {
                        if (getComputedStyle(n).overflow !== 'visible'
                            && tb.bottom > n.getBoundingClientRect().bottom + 1) {
                            return n.className.toString().slice(0, 40);
                        }
                        n = n.parentElement;
                    }
                    return null;
                })(),
            };
        }, first);

        expect(probe.clippedAncestor, 'the tray must not overflow a clipping ancestor').toBeNull();
        expect(probe.trayWithinItsCard, 'the tray must render inside its own card').toBe(true);
        expect(probe.chipIsTheTopElement, 'a chip must be the element at its own coordinates').toBe(true);

        // And it must genuinely accept the click.
        await card.locator('[data-lp-chip]').first().click();
        await expect(card.locator('[data-lp-chip].is-selected')).toHaveCount(1);
    });

    /*
     | The control must not be inside the card's own link. The seller grid links
     | only its title, but the landlord grid wrapped the whole card — this
     | asserts the structural rule on whichever grid is under test, from the
     | rendered DOM rather than from the template.
     */
    test('no preference control is nested inside a card link', async ({ page }) => {
        const nested = await page.evaluate(() =>
            Array.from(document.querySelectorAll('[data-lp-control]'))
                .filter((el) => el.closest('a') !== null).length
        );

        expect(nested, 'a control inside an anchor navigates instead of saving').toBe(0);
    });

    /* Pressing a state on a card must not navigate anywhere. */
    test('pressing a state on a card stays on the results page', async ({ page }) => {
        const [first] = fixture().card_listing_ids;
        const before = page.url();

        await controlFor(page, first).locator('[data-lp-state="save"]').click();
        await expect(controlFor(page, first).locator('[data-lp-state="save"]'))
            .toHaveAttribute('aria-pressed', 'true');

        expect(page.url(), 'a card press must not follow the card link').toBe(before);
    });
});

test.describe('Save | Maybe | Pass — result cards, signed out', () => {
    test('a guest sees the control and is routed to login, storing nothing', async ({ page }) => {
        await guard(page);
        await page.goto(fixture().search_path);

        const card = controls(page).first();
        await expect(card).toBeVisible({ timeout: 20_000 });
        await expect(card).toHaveAttribute('data-lp-guest', '1');

        await card.locator('[data-lp-state="save"]').click();
        await page.waitForURL(/\/login/);
    });
});
