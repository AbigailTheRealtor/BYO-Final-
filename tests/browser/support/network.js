/*
 |-----------------------------------------------------------------------------
 | Network interception for the browser suite
 |-----------------------------------------------------------------------------
 |
 | Two jobs, and they are separate on purpose:
 |
 |   1. RECORD every request the page attempts, so a spec can assert on where the
 |      renderer tried to go — not merely on whether it succeeded.
 |   2. ABORT anything leaving the fixture origin, so CI can never make a billable
 |      or rate-limited call however the code under test is broken.
 |
 | Asserting on ATTEMPTS rather than on responses is the important half. A test
 | that only checked "the map rendered" would pass just as happily if the renderer
 | had quietly fetched a Google tile to do it.
 */

/** Hosts that must never be contacted from a test run, whatever the outcome. */
const FORBIDDEN = [
    'googleapis.com',
    'google.com',
    'gstatic.com',
    'nominatim.openstreetmap.org',
    'tigerweb.geo.census.gov',
    'geocoding.geo.census.gov',
    'r2.cloudflarestorage.com',
];

/**
 * Install the recorder + blocker on a page.
 *
 * @returns {{ all: string[], external: string[], forbidden: string[] }} live arrays
 */
async function installNetworkGuard(page, { allowOrigin = 'http://127.0.0.1:8931' } = {}) {
    const record = { all: [], external: [], forbidden: [] };

    await page.route('**/*', async (route) => {
        const url = route.request().url();
        record.all.push(url);

        const isLocal = url.startsWith(allowOrigin) || url.startsWith('data:') || url.startsWith('blob:');

        if (!isLocal) {
            record.external.push(url);

            if (FORBIDDEN.some((host) => url.includes(host))) {
                record.forbidden.push(url);
            }

            // Abort rather than fulfil: an aborted request is indistinguishable
            // from an offline CI runner, which is the condition the renderer's
            // degraded path is supposed to survive.
            await route.abort();
            return;
        }

        await route.continue();
    });

    return record;
}

module.exports = { installNetworkGuard, FORBIDDEN };
