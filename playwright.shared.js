/*
 |-----------------------------------------------------------------------------
 | Settings both Playwright configurations share
 |-----------------------------------------------------------------------------
 |
 | THERE ARE TWO CONFIGURATIONS, AND THE SEPARATION IS THE POINT.
 |
 |   playwright.config.js      the DEFAULT. Location DNA / Virtual Drive, driven
 |                             against static fixtures by a pure-Node server.
 |                             No Laravel, no PHP, no Composer, no vendor/.
 |
 |   playwright.app.config.js  the Laravel-backed Listing Preferences suite,
 |                             which boots `php artisan serve` against a
 |                             throwaway SQLite database.
 |
 | They ran as one configuration once, and it cost a required check: the default
 | config gained the Laravel `webServer` entries, and the Location DNA CI job —
 | which installs Node and nothing else, deliberately — died on
 | `Failed opening required 'vendor/autoload.php'` before a single browser spec
 | executed. The suites have different dependencies, so they get different
 | configurations and different jobs.
 |
 | WHAT LIVES HERE is only what must never drift between them: how a run decides
 | it is CI, how many times it retries, and which Chromium binary it launches.
 | Servers, ports, projects and baseURLs deliberately do NOT live here — those
 | are exactly the things the two suites must be free to disagree about.
 */

/*
 | This environment supplies a Playwright chromium through
 | `REPLIT_PLAYWRIGHT_CHROMIUM_EXECUTABLE`, so `playwright install` — which would
 | download a browser — is never run. When that variable is absent Playwright
 | falls back to its own managed browser, which is the correct behaviour on a
 | developer machine or in GitHub Actions.
 */
const chromiumExecutable = process.env.REPLIT_PLAYWRIGHT_CHROMIUM_EXECUTABLE || undefined;

/** Launch options for a project, with the pre-provisioned browser when there is one. */
function launchOptions(extra = {}) {
    return {
        ...(chromiumExecutable ? { executablePath: chromiumExecutable } : {}),
        ...extra,
    };
}

/**
 | Runner-level settings.
 |
 | `forbidOnly` fails the run if a `test.only` is committed: a focused test that
 | reaches CI silently disables its siblings, which is the failure mode this
 | whole suite exists to prevent.
 */
const runnerDefaults = {
    testDir: './tests/browser',
    timeout: 30_000,
    expect: { timeout: 5_000 },
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: process.env.CI ? 2 : undefined,
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : [['list']],
};

/** `use` settings that describe how a failure is recorded, not where it happens. */
const diagnosticDefaults = {
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'off',
};

/*
 | THE ONE FILE THAT SEPARATES THE TWO SUITES, named once.
 |
 | The default config IGNORES it; the app config MATCHES it. Both read this
 | constant, so the two halves of that split cannot drift apart into a spec that
 | runs in neither configuration — or, worse, in both.
 */
const APP_SPEC = /listing-preference\.spec\.js/;

module.exports = {
    APP_SPEC,
    chromiumExecutable,
    diagnosticDefaults,
    launchOptions,
    runnerDefaults,
};
