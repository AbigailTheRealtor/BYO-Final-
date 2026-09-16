/*
 |-----------------------------------------------------------------------------
 | Virtual Drive — our overlays must stay ABOVE the provider's imagery
 |-----------------------------------------------------------------------------
 |
 | In the one live Google session the HUD ("Selected home: 35 m away …") and the
 | "Face the selected home" control were invisible and unclickable: Google's
 | panorama puts large z-indexes on its internal layers, and #vd-street created
 | no stacking context, so those layers painted over every later sibling.
 |
 | The fake Maps API draws no DOM, which is why the launch-guard specs could not
 | see it. Here a layer shaped like Google's (full cover, huge z-index) is put
 | inside #vd-street after launch, and each overlay must be what the browser
 | actually hits at its own centre. No network: the guard aborts anything
 | leaving the fixture origin.
 */

const { test, expect } = require('@playwright/test');
const { installNetworkGuard } = require('./support/network');

async function hitTarget(page, selector) {
    return page.evaluate((sel) => {
        const el = document.querySelector(sel);
        const r = el.getBoundingClientRect();
        const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);

        return { visibleBox: r.width > 0 && r.height > 0, onTop: !!hit && (hit === el || el.contains(hit)), hit: hit ? (hit.id || hit.className || hit.tagName) : null };
    }, selector);
}

test('HUD, imagery status and "Face the selected home" stay above a provider layer with a huge z-index', async ({ page }) => {
    const record = await installNetworkGuard(page);

    await page.goto('/virtual-drive/google.html');
    await expect(page.locator('#vd-launch')).toBeEnabled();
    await page.click('#vd-launch');
    await expect(page.locator('#vd-launch-panel')).toBeHidden();
    await expect(page.locator('#vd-hud')).toBeVisible();

    // What Google's panorama does to the page: a full-cover layer, z-index far above ours.
    await page.evaluate(() => {
        const layer = document.createElement('div');

        layer.id = 'provider-layer';
        layer.style.cssText = 'position:absolute;inset:0;z-index:1000000;background:#000';
        document.getElementById('vd-street').appendChild(layer);
    });

    for (const selector of ['#vd-provider-controls button.vd-control', '#vd-hud', '#vd-imagery-status']) {
        const result = await hitTarget(page, selector);

        expect(result.visibleBox, `${selector} has a box`).toBe(true);
        expect(result.onTop, `${selector} is on top (hit: ${result.hit})`).toBe(true);
    }

    // And it is really clickable: a real click reaches the control's handler.
    await page.click('text=Face the selected home');
    await expect(page.locator('#vd-events')).toContainText('Camera aimed at the selected home');

    // The provider layer is still what receives clicks on the open imagery.
    const street = await hitTarget(page, '#vd-street');

    expect(street.hit).toBe('provider-layer');
    expect(record.forbidden).toEqual([]);
});
