/*
 |-----------------------------------------------------------------------------
 | Playwright — the first browser-level coverage this repository has ever had
 |-----------------------------------------------------------------------------
 |
 | WHY THIS EXISTS
 | ---------------
 | The Location DNA map is JavaScript. The PHP suite renders the Blade partial and
 | asserts on the TEXT of that JavaScript — it cannot execute it, so it cannot see
 | a map that fails to initialise, a serializer that writes an empty array, or a
 | request going somewhere it should not. That gap is not hypothetical: it is how
 | a dead map and a geometry-destroying serializer both reached production while
 | every test stayed green.
 |
 | Playwright rather than Dusk. Dusk drives a PHP application through a browser
 | and needs ChromeDriver and a booted Laravel app; the risk here lives in the
 | JavaScript itself, and Playwright tests that directly, against static fixtures,
 | with no database and no authentication.
 |
 | THIS CONFIGURATION BOOTS NO LARAVEL, AND THAT IS A HARD PROPERTY
 | ---------------------------------------------------------------
 | Its only web server is `tests/browser/support/static-server.js`, a pure-Node
 | process. No `php`, no `artisan`, no `vendor/autoload.php`, no database. The CI
 | job that runs it (.github/workflows/browser-tests.yml) therefore installs Node
 | and nothing else — no PHP, no Composer — deliberately, because the risk under
 | test is JavaScript and a PHP toolchain would be pure cost.
 |
 | That is not a preference, it is a scar. The Listing Preferences work briefly
 | added Laravel-backed `webServer` entries HERE, and the Location DNA job died on
 | `Failed opening required 'vendor/autoload.php'` before one browser spec ran.
 | The Laravel-backed suite now lives in playwright.app.config.js with its own
 | job. `ListingPreferenceArchitectureGuardTest` fails the build if an app server
 | ever reappears in this file.
 |
 | NO BILLABLE CALLS, EVER
 | -----------------------
 | `tests/browser/support/network.js` installs a route interceptor that ABORTS
 | every request leaving the fixture origin, and the suite asserts on what was
 | attempted. Nothing reaches Google, Nominatim, Census or R2 from CI. That is
 | enforced per test rather than trusted, in the same spirit as the PHP suite's
 | credential blanking in tests/bootstrap.php.
 |
 | THE BROWSER IS PRE-PROVISIONED
 | ------------------------------
 | See playwright.shared.js — `REPLIT_PLAYWRIGHT_CHROMIUM_EXECUTABLE` supplies a
 | chromium here, so `playwright install` is never run in this container.
 */

const { defineConfig, devices } = require('@playwright/test');
const { APP_SPEC, diagnosticDefaults, launchOptions, runnerDefaults } = require('./playwright.shared');

module.exports = defineConfig({
    ...runnerDefaults,

    /*
     | The Laravel-backed Listing Preferences specs are NOT part of this suite.
     | They need a booted application on a different origin; run them with
     | `npm run test:browser:app`, which uses playwright.app.config.js.
     |
     | Ignored here rather than left to the projects below, so adding a second
     | static project cannot accidentally pick them up.
     */
    testIgnore: APP_SPEC,

    webServer: {
        command: 'node tests/browser/support/static-server.js',
        url: 'http://127.0.0.1:8931/health',
        reuseExistingServer: !process.env.CI,
        timeout: 20_000,
    },

    use: {
        ...diagnosticDefaults,
        baseURL: 'http://127.0.0.1:8931',
    },

    projects: [
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                launchOptions: launchOptions({
                    /*
                     | MapLibre requires WebGL2, and a headless CI container has no
                     | GPU. Without these the map never initialises and every
                     | rendering assertion fails for a reason that has nothing to do
                     | with the code under test.
                     |
                     | SwiftShader is Chromium's software GL implementation.
                     | `--enable-unsafe-swiftshader` is required because recent
                     | Chromium refuses to expose WebGL over SwiftShader by default
                     | — "unsafe" here means slow and unaccelerated, not insecure in
                     | any sense that matters to a test runner rendering fixtures.
                     */
                    args: [
                        '--use-gl=angle',
                        '--use-angle=swiftshader',
                        '--enable-unsafe-swiftshader',
                    ],
                }),
            },
        },
    ],
});
