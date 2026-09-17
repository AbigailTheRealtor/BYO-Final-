/*
 |-----------------------------------------------------------------------------
 | Save | Maybe | Pass — the real interaction, in a real browser
 |-----------------------------------------------------------------------------
 |
 | WHY THESE DRIVE THE REAL APPLICATION
 | ------------------------------------
 | Every other spec in this directory drives static fixtures, because the Location
 | DNA risk lives in JavaScript. The listing-preference risk is the opposite
 | shape: the failures that matter are persistence, authentication, CSRF and the
 | feature flag, and a static fixture cannot have any of them. So these run
 | against `php artisan serve` on an isolated SQLite database — the real Blade
 | component, on the real /offer-listing/seller/view page, posting through the
 | real `web` middleware to the real controller.
 |
 | The PHP suite already proves the write service and the HTTP boundary. What it
 | CANNOT see is the half that only exists in a browser: that clicking Save
 | actually activates it, that the tray opens with the server's chips, that Done
 | sends one request, that a reopened page shows what was stored, and that the
 | POST carries the page's own CSRF token through the session.
 |
 | CSRF IS EXERCISED, NOT BYPASSED. Every write here is a real fetch from a real
 | page with a real session cookie and the token the server rendered. A test that
 | disabled CSRF to make itself pass would be removing the thing worth proving.
 | See the note on the 419-negative case at the bottom of this file.
 */

const { test, expect } = require('@playwright/test');
const { installNetworkGuard } = require('./support/network');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const APP = 'http://127.0.0.1:8932';

/*
 | THE NETWORK GUARD IS LOAD-BEARING HERE, not just hygiene.
 |
 | The real Blade layout pulls fonts and scripts from off-origin CDNs. This
 | sandbox has no outbound network, so those requests HANG rather than fail —
 | `page.goto()` waits for `load`, `load` never fires, and every spec died at the
 | 30s test timeout having proven nothing. Aborting everything that leaves the
 | app origin makes the page load deterministically, and gives the same
 | guarantee the rest of this suite has: a browser run can make no billable or
 | rate-limited call however the code under test behaves.
 */
async function guard(page, origin = APP) {
    await installNetworkGuard(page, { allowOrigin: origin });
}

/** Written by ListingPreferenceBrowserTestSeeder when the harness boots. */
function fixture() {
    return JSON.parse(fs.readFileSync(path.join(ROOT, 'storage/app/lp-browser-fixture.json'), 'utf8'));
}

const listingUrl = () => `/offer-listing/seller/view/${fixture().listing_id}`;

/*
 | NEVER waitForLoadState('networkidle') HERE.
 |
 | Login redirects to /dashboard, which holds connections open, so networkidle
 | never arrives and every authenticated spec times out at 30s having proven
 | nothing. The URL LEAVING /login is the actual signal that authentication
 | succeeded, and it is the one this helper waits for.
 */
async function signIn(page) {
    const { email, password } = fixture();

    await page.goto('/login');
    // Selected by NAME, not id: this application serves its own login view
    // (ids `user_login` / `upassword`), not the framework's scaffold, and the
    // field names are what both have in common.
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 20_000 });
}

/** The control, scoped so a stray match elsewhere on a 3,500-line page cannot pass. */
const control = (page) => page.locator('[data-lp-control]').first();
const stateBtn = (page, state) => control(page).locator(`[data-lp-state="${state}"]`);
const tray = (page) => control(page).locator('[data-lp-tray]');
const chip = (page, key) => control(page).locator(`[data-lp-chip="${key}"]`);

test.describe('Save | Maybe | Pass — authenticated', () => {
    test.beforeEach(async ({ page }) => {
        await guard(page);
        await signIn(page);
        await page.goto(listingUrl());
        // Start each test from a known-neutral state.
        const clear = control(page).locator('[data-lp-clear]');
        if (await tray(page).isVisible().catch(() => false)) {
            await clear.click();
            await expect(control(page).locator('[data-lp-status]')).toContainText('Removed.');
        }
    });

    test('an eligible user sees all three states', async ({ page }) => {
        await expect(control(page)).toBeVisible();
        for (const state of ['save', 'maybe', 'pass']) {
            await expect(stateBtn(page, state)).toBeVisible();
        }
        // Nothing is active before a choice.
        await expect(control(page).locator('[data-lp-state].is-active')).toHaveCount(0);
    });

    test('clicking Save activates it and opens the reason tray', async ({ page }) => {
        await stateBtn(page, 'save').click();

        await expect(stateBtn(page, 'save')).toHaveClass(/is-active/);
        await expect(stateBtn(page, 'save')).toHaveAttribute('aria-pressed', 'true');
        await expect(stateBtn(page, 'maybe')).not.toHaveClass(/is-active/);

        await expect(tray(page)).toBeVisible();
        await expect(control(page).locator('[data-lp-prompt]'))
            .toHaveText('What do you like about this property?');
    });

    test('the tray renders chips from the server catalog, not a hard-coded list', async ({ page }) => {
        await stateBtn(page, 'save').click();
        await expect(tray(page)).toBeVisible();

        // Present because the catalog offers them for Save on a residential sale.
        await expect(chip(page, 'updated_kitchen')).toBeVisible();
        await expect(chip(page, 'natural_light')).toBeVisible();

        // Absent because the catalog does not offer them for Save…
        await expect(chip(page, 'too_expensive')).toHaveCount(0);
        // …and absent because they are excluded as seeker preferences entirely.
        await expect(chip(page, 'accessible_features')).toHaveCount(0);
        await expect(chip(page, 'playground')).toHaveCount(0);

        // The prompt changes with the state, from the same payload.
        await stateBtn(page, 'pass').click();
        await expect(control(page).locator('[data-lp-prompt]'))
            .toHaveText("Why isn't this one for you?");
        await expect(chip(page, 'too_expensive')).toBeVisible();
    });

    test('multiple reasons persist, and are shown again when the page is reopened', async ({ page }) => {
        await stateBtn(page, 'save').click();
        await expect(tray(page)).toBeVisible();

        await chip(page, 'updated_kitchen').click();
        await chip(page, 'natural_light').click();
        await control(page).locator('[data-lp-done]').click();

        await expect(control(page).locator('[data-lp-status]')).toContainText('Saved.');

        // A FULL RELOAD: the state and the reasons must come from the database,
        // not from anything still alive in the page.
        await page.reload();

        await expect(stateBtn(page, 'save')).toHaveClass(/is-active/);
        await expect(tray(page)).toBeVisible();
        await expect(chip(page, 'updated_kitchen')).toHaveClass(/is-selected/);
        await expect(chip(page, 'natural_light')).toHaveClass(/is-selected/);
        await expect(chip(page, 'move_in_ready')).not.toHaveClass(/is-selected/);
    });

    test('one Done sends exactly one write, not one per chip', async ({ page }) => {
        await stateBtn(page, 'save').click();
        await expect(tray(page)).toBeVisible();

        const writes = [];
        page.on('request', (r) => {
            if (r.method() !== 'GET' && r.url().includes('/listing-preferences')) {
                writes.push(r.url());
            }
        });

        await chip(page, 'updated_kitchen').click();
        await chip(page, 'natural_light').click();
        await chip(page, 'move_in_ready').click();
        await chip(page, 'move_in_ready').click(); // selected then deselected

        expect(writes, 'selecting chips must send nothing').toHaveLength(0);

        await control(page).locator('[data-lp-done]').click();
        await expect(control(page).locator('[data-lp-status]')).toContainText('Saved.');

        expect(writes, 'Done sends one request').toHaveLength(1);
        expect(writes[0]).toContain('/listing-preferences/reasons');
    });

    test('Save to Maybe changes the active state without duplicating it', async ({ page }) => {
        await stateBtn(page, 'save').click();
        await expect(stateBtn(page, 'save')).toHaveClass(/is-active/);

        await stateBtn(page, 'maybe').click();

        await expect(stateBtn(page, 'maybe')).toHaveClass(/is-active/);
        await expect(stateBtn(page, 'save')).not.toHaveClass(/is-active/);
        await expect(control(page).locator('[data-lp-state].is-active')).toHaveCount(1);

        await page.reload();
        await expect(stateBtn(page, 'maybe')).toHaveClass(/is-active/);
        await expect(control(page).locator('[data-lp-state].is-active')).toHaveCount(1);
    });

    test('a state change does not carry the previous state\'s reasons', async ({ page }) => {
        await stateBtn(page, 'save').click();
        await chip(page, 'updated_kitchen').click();
        await control(page).locator('[data-lp-done]').click();
        await expect(control(page).locator('[data-lp-status]')).toContainText('Saved.');

        await stateBtn(page, 'pass').click();
        await page.reload();

        await expect(stateBtn(page, 'pass')).toHaveClass(/is-active/);
        // "Updated kitchen" is not an answer to "Why isn't this one for you?",
        // and is not offered for Pass at all.
        await expect(chip(page, 'updated_kitchen')).toHaveCount(0);
        await expect(control(page).locator('[data-lp-chip].is-selected')).toHaveCount(0);
    });

    test('clear removes the choice and the control returns to neutral', async ({ page }) => {
        await stateBtn(page, 'save').click();
        await chip(page, 'updated_kitchen').click();
        await control(page).locator('[data-lp-done]').click();
        await expect(control(page).locator('[data-lp-status]')).toContainText('Saved.');

        await stateBtn(page, 'save').click();      // reopen the tray
        await control(page).locator('[data-lp-clear]').click();

        await expect(control(page).locator('[data-lp-status]')).toContainText('Removed.');
        await expect(control(page).locator('[data-lp-state].is-active')).toHaveCount(0);
        await expect(tray(page)).toBeHidden();

        await page.reload();
        await expect(control(page).locator('[data-lp-state].is-active')).toHaveCount(0);
        await expect(tray(page)).toBeHidden();
    });

    test('Pass is preference data only and does not alter the listing', async ({ page }) => {
        const before = await page.locator('body').innerText();

        await stateBtn(page, 'pass').click();
        await expect(stateBtn(page, 'pass')).toHaveClass(/is-active/);

        await page.reload();

        // The listing still renders, still says the same things, and is still
        // reachable — Pass hides nothing and withdraws nothing.
        await expect(page).toHaveURL(new RegExp(String(fixture().listing_id) + '$'));
        const after = await page.locator('body').innerText();

        const strip = (t) => t.replace(/\s+/g, ' ').replace(/Save Maybe Pass.*$/s, '').trim();
        expect(strip(after)).toBe(strip(before));
    });

    /*
     | CSRF, exercised end to end.
     |
     | The page's own token, its own session cookie, the ordinary `web` stack.
     | Proving the write SUCCEEDS this way is the positive half of the CSRF
     | contract and is what the PHP suite cannot reach.
     */
    test('a write carries the page CSRF token through the normal web middleware', async ({ page }) => {
        let sentToken = null;
        page.on('request', (r) => {
            if (r.method() === 'POST' && r.url().includes('/listing-preferences')) {
                sentToken = r.headers()['x-csrf-token'] || null;
            }
        });

        await stateBtn(page, 'save').click();
        await expect(stateBtn(page, 'save')).toHaveClass(/is-active/);

        const pageToken = await control(page).getAttribute('data-lp-csrf');

        expect(pageToken, 'the server rendered a token').toBeTruthy();
        expect(sentToken, 'the write sent the page token').toBe(pageToken);
    });
});

test.describe('Save | Maybe | Pass — guest', () => {
    test('a guest is routed through the existing login flow and stores nothing', async ({ page }) => {
        await guard(page);
        await page.context().clearCookies();
        await page.goto(listingUrl());

        await expect(control(page)).toBeVisible();
        await expect(control(page)).toHaveAttribute('data-lp-guest', '1');

        const writes = [];
        page.on('request', (r) => {
            if (r.method() !== 'GET' && r.url().includes('/listing-preferences')) {
                writes.push(r.url());
            }
        });

        await stateBtn(page, 'save').click();
        await page.waitForURL(/\/login/);

        expect(writes, 'a guest click must write nothing').toHaveLength(0);
        // The listing is preserved so they return to it.
        expect(page.url()).toContain(encodeURIComponent(listingUrl()));
    });
});

/*
 | THE 419-NEGATIVE CASE IS DELIBERATELY NOT HERE.
 |
 | Forcing a genuine CSRF rejection would mean posting a wrong token from a page
 | that legitimately holds a right one — achievable only by tampering with the
 | page or by disabling protection, and this file is not going to weaken CSRF to
 | demonstrate CSRF. The POSITIVE path above proves the token is required,
 | rendered and sent; the structural assertions in
 | tests/Feature/ListingPreferences/ListingPreferenceHttpTest.php prove every
 | write route sits in the `web` group behind `auth` and is absent from
 | VerifyCsrfToken::$except.
 |
 | An in-process PHP 419 test is impossible for an unrelated reason:
 | VerifyCsrfToken short-circuits whenever runningUnitTests() is true, so such a
 | test would assert on the harness rather than the route. This limitation is
 | documented rather than worked around.
 */

/*
 | THE FEATURE FLAG, PROVEN AGAINST A REAL SERVER THAT HAS IT OFF.
 |
 | Port 8933 is the same application with LISTING_PREFERENCES_ENABLED=false and
 | its own database — the posture a deployment that sets nothing actually gets.
 | Both halves are asserted together, because a rendered control whose endpoint
 | 404s is worse than no control at all.
 */
test.describe('feature flag OFF', () => {
    const OFF = 'http://127.0.0.1:8933';

    function offFixture() {
        return JSON.parse(
            fs.readFileSync(path.join(ROOT, 'storage/app/lp-browser-fixture-off.json'), 'utf8')
        );
    }

    test('no control renders and the write endpoint does not exist', async ({ page, request }) => {
        const { listing_id: id, email, password } = offFixture();

        await guard(page, OFF);

        // Signed in, on the real listing page, with the feature off.
        await page.goto(`${OFF}/login`);
        await page.fill('input[name="email"]', email);
        await page.fill('input[name="password"]', password);
        await page.click('button[type="submit"], input[type="submit"]');
        await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 20_000 });

        await page.goto(`${OFF}/offer-listing/seller/view/${id}`);

        // The page itself still works…
        await expect(page.locator('body')).toBeVisible();
        // …and the control is absent, not merely disabled.
        await expect(page.locator('[data-lp-control]')).toHaveCount(0);
        await expect(page.locator('[data-lp-state]')).toHaveCount(0);

        /*
         | The endpoint 404s rather than refusing, so the feature is INVISIBLE
         | rather than advertised.
         |
         | Posted from inside the page, with the page's own session cookie and
         | the token from its <meta name="csrf-token">, because that is what the
         | real component does. An out-of-band request without a token returns
         | 419 — CSRF sits in front of the feature gate — which proves CSRF is
         | enforced but says nothing about whether the route exists. Only a
         | properly credentialed request can ask that question.
         */
        const status = await page.evaluate(async ({ listingId }) => {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const res = await fetch('/listing-preferences', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ listing_type: 'seller_agent', listing_id: listingId, state: 'save' }),
            });

            return res.status;
        }, { listingId: id });

        expect(status, 'a disabled feature must not expose an endpoint').toBe(404);
    });
});
