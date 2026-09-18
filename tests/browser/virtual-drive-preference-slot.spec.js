/*
 |-----------------------------------------------------------------------------
 | Virtual Drive · the shopper card's Save | Maybe | Pass slot (fake Maps API)
 |-----------------------------------------------------------------------------
 |
 | WHAT THIS PROVES, AND WHAT IT DELIBERATELY DOES NOT
 | ---------------------------------------------------
 | The proof page renders one hidden, server-built preference control per
 | listing; the shell's only job is to MOVE the one matching the selected home
 | into the shopper card and show it. This spec proves that job in a browser:
 | the node that appears is the SAME node the server rendered (not markup rebuilt
 | from a string), the right one follows the selected home, and it survives every
 | re-render the card performs — switching homes, paging photos, closing and
 | reopening. A node the card rebuilt over would be detached and lost for good.
 |
 | The preference BEHAVIOUR (state, reasons, clear, guest, flag) is the shared
 | control's, and is proven against the real application in
 | listing-preference-virtual-drive.spec.js. Here the nodes are inert stand-ins,
 | which is exactly the point: the shell must work without knowing what they are.
 |
 | NO NETWORK. The fake Maps API answers everything; the guard aborts anything
 | that leaves the fixture origin and the test asserts nothing tried.
 */

const { test, expect } = require('@playwright/test');
const { installNetworkGuard } = require('./support/network');

const PROVIDER_HOSTS = ['googleapis.com', 'gstatic.com', 'google.com', 'apple-mapkit.com', 'apple.com'];

const fakeGoogle = (page) => page.evaluate(() => window.__fakeGoogle.counters());
const markers = (page) => page.evaluate(() => window.__fakeGoogle.markers());

function expectNoProviderTraffic(record) {
    expect(record.forbidden).toEqual([]);
    expect(record.external.filter((url) => PROVIDER_HOSTS.some((host) => url.includes(host)))).toEqual([]);
}

/*
 | Stand-ins for what VirtualDriveProofController renders: one hidden wrapper
 | per listing key, holding the shared control. Each carries an identity token
 | set on the JS OBJECT (not an attribute), so a node rebuilt from markup — even
 | an identical-looking one — is told apart from the one the server rendered.
 */
async function renderServerControls(page, keys) {
    await page.evaluate((listingKeys) => {
        window.__serverNodes = {};

        listingKeys.forEach((key, i) => {
            const wrapper = document.createElement('div');
            wrapper.setAttribute('data-vd-preference-for', key);
            wrapper.hidden = true;

            const control = document.createElement('div');
            control.setAttribute('data-lp-control', '');
            control.setAttribute('data-lp-listing-id', String(9000 + i));
            control.textContent = 'control for ' + key;
            wrapper.appendChild(control);

            wrapper.__serverIdentity = 'server:' + key;
            window.__serverNodes[key] = wrapper;
            document.body.appendChild(wrapper);
        });
    }, keys);
}

/** The slot currently shown in the card, and whether it is the server's own node. */
function slotState(page) {
    return page.evaluate(() => {
        const inCard = Array.from(document.querySelectorAll('#vd-shopper [data-vd-preference-for]'));

        return inCard.map((node) => ({
            key: node.getAttribute('data-vd-preference-for'),
            hidden: node.hidden,
            sameNode: window.__serverNodes[node.getAttribute('data-vd-preference-for')] === node,
            attached: document.body.contains(node),
        }));
    });
}

/** Every server node still exists in the document — none was destroyed. */
function allAttached(page) {
    return page.evaluate(() => Object.values(window.__serverNodes).every((n) => document.body.contains(n)));
}

async function openAt(page, steps) {
    const record = await installNetworkGuard(page);

    await page.goto('/virtual-drive/google.html?offset=35');
    await expect(page.locator('#vd-launch')).toBeEnabled();

    for (let i = 0; i < steps; i += 1) {
        await page.click('#vd-next');
    }

    await page.click('#vd-launch');
    await expect.poll(async () => (await fakeGoogle(page)).panoramas).toBe(1);
    await page.waitForTimeout(200);

    return record;
}

async function clickSign(page, fragment) {
    const list = await markers(page);
    const index = list.findIndex((m) => (m.title || '').includes(fragment));

    expect(index).toBeGreaterThanOrEqual(0);
    await page.evaluate((i) => window.__fakeGoogle.clickMarker(i), index);
}

test.describe('Virtual Drive · Save | Maybe | Pass slot in the shopper card (fake Maps API, no network)', () => {
    test('the card shows the server-rendered control for the selected home — the same node, not a rebuilt one', async ({ page }) => {
        const record = await openAt(page, 2); // Manasota Key A
        await renderServerControls(page, ['FX-RENT-MANASOTA-A', 'FX-RENT-MANASOTA-B']);

        const card = page.locator('#vd-shopper');

        await clickSign(page, 'Manasota Key A');
        await expect(card).toHaveAttribute('data-listing', 'FX-RENT-MANASOTA-A');

        expect(await slotState(page)).toEqual([
            { key: 'FX-RENT-MANASOTA-A', hidden: false, sameNode: true, attached: true },
        ]);

        // The Save ACTION is not a button of its own: the shared control is.
        await expect(card.locator('.vd-shopper-action-save')).toHaveCount(0);
        await expect(card.locator('[data-lp-control]')).toHaveCount(1);

        expect((await fakeGoogle(page)).panoramaConstructorCalls).toBe(1);
        expectNoProviderTraffic(record);
    });

    test('selecting another home shows THAT home\'s control, and going back restores the first', async ({ page }) => {
        const record = await openAt(page, 2);
        await renderServerControls(page, ['FX-RENT-MANASOTA-A', 'FX-RENT-MANASOTA-B']);

        const card = page.locator('#vd-shopper');

        await clickSign(page, 'Manasota Key A');
        await expect(card).toHaveAttribute('data-listing', 'FX-RENT-MANASOTA-A');

        await clickSign(page, 'Manasota Key B');
        await expect(card).toHaveAttribute('data-listing', 'FX-RENT-MANASOTA-B');
        expect(await slotState(page)).toEqual([
            { key: 'FX-RENT-MANASOTA-B', hidden: false, sameNode: true, attached: true },
        ]);

        // The first home's control was parked, not destroyed…
        expect(await allAttached(page)).toBe(true);

        // …so returning to it shows it again.
        await clickSign(page, 'Manasota Key A');
        await expect(card).toHaveAttribute('data-listing', 'FX-RENT-MANASOTA-A');
        expect(await slotState(page)).toEqual([
            { key: 'FX-RENT-MANASOTA-A', hidden: false, sameNode: true, attached: true },
        ]);

        const counters = await fakeGoogle(page);
        expect(counters.panoramaConstructorCalls).toBe(1);
        expect(counters.setPano).toBe(0);
        expectNoProviderTraffic(record);
    });

    test('paging photos, and closing and reopening the card, never lose the control', async ({ page }) => {
        const record = await openAt(page, 2);
        await renderServerControls(page, ['FX-RENT-MANASOTA-B']);

        const card = page.locator('#vd-shopper');

        await clickSign(page, 'Manasota Key B');
        await expect(card).toHaveAttribute('data-listing', 'FX-RENT-MANASOTA-B');

        // Photo paging re-renders the whole card.
        const next = card.locator('.vd-shopper-photo-next');

        if (await next.count()) {
            await next.click();
            await next.click();
        }

        expect(await slotState(page)).toEqual([
            { key: 'FX-RENT-MANASOTA-B', hidden: false, sameNode: true, attached: true },
        ]);

        await page.locator('.vd-shopper-close').click();
        await expect(card).toBeHidden();
        expect(await allAttached(page)).toBe(true);

        // A closed card's control is hidden again, not left visible on the page.
        expect(await page.evaluate(() => window.__serverNodes['FX-RENT-MANASOTA-B'].hidden)).toBe(true);

        await clickSign(page, 'Manasota Key B');
        expect(await slotState(page)).toEqual([
            { key: 'FX-RENT-MANASOTA-B', hidden: false, sameNode: true, attached: true },
        ]);

        expect((await fakeGoogle(page)).panoramaConstructorCalls).toBe(1);
        expectNoProviderTraffic(record);
    });

    test('a home with no server control (feature off, guest-ineligible, no identity) gets a card with no slot', async ({ page }) => {
        const record = await openAt(page, 2);
        // No controls rendered at all — what the page emits with the feature OFF.

        const card = page.locator('#vd-shopper');

        await clickSign(page, 'Manasota Key A');
        await expect(card).toHaveAttribute('data-listing', 'FX-RENT-MANASOTA-A');
        expect(await slotState(page)).toEqual([]);
        await expect(card.locator('[data-lp-control]')).toHaveCount(0);
        await expect(card.locator('.vd-shopper-action-save')).toHaveCount(0);

        // The rest of the card is intact.
        await expect(card).toContainText('6590 FIXTURE Manasota Key A');

        expectNoProviderTraffic(record);
    });
});
