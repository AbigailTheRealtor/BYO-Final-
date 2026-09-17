/*
 |-----------------------------------------------------------------------------
 | Playwright — the LARAVEL-BACKED suite (Listing Preferences)
 |-----------------------------------------------------------------------------
 |
 | WHY THIS IS A SEPARATE CONFIGURATION
 | ------------------------------------
 | playwright.config.js drives STATIC fixtures with a pure-Node server, because
 | the Location DNA and Virtual Drive risk lives in JavaScript. The Listing
 | Preferences risk is the opposite shape: the failures that matter are
 | persistence, authentication, CSRF and the feature flag, none of which a static
 | fixture can have. So these specs drive the REAL application — the real Blade
 | component on the real /offer-listing/seller/view page, posting through the real
 | `web` middleware to the real controller and database.
 |
 | THE TWO SUITES HAVE DIFFERENT DEPENDENCIES, SO THEY HAVE DIFFERENT CONFIGS.
 | This one needs PHP, Composer and `vendor/`; the static one needs none of them.
 | They were briefly merged into one configuration, and the consequence was
 | immediate and total: the Location DNA CI job installs Node and nothing else, so
 | the moment it inherited these `webServer` entries it died on
 | `Failed opening required 'vendor/autoload.php'` before a single browser spec
 | executed. Merging them again reproduces that exactly.
 |
 | Run with `npm run test:browser:app`. CI runs it in its own job,
 | .github/workflows/browser-tests-app.yml, which is the only browser job that
 | provisions a PHP toolchain.
 |
 | NOTHING PRODUCTION IS REACHABLE FROM HERE
 | -----------------------------------------
 | tests/browser/support/app-server.js builds its own environment from scratch
 | rather than inheriting one: DATABASE_URL, PGHOST and PGDATABASE blanked, a
 | throwaway SQLite file under storage/, APP_ENV=local, and every Google
 | credential blanked. Its seeder refuses independently on any production signal
 | (ProductionDatabaseRefused), so isolation is asserted twice by two mechanisms.
 | Ports 8932/8933 are the harness's own; nothing here touches port 5000 or any
 | shared database.
 */

const { defineConfig, devices } = require('@playwright/test');
const { APP_SPEC, diagnosticDefaults, launchOptions, runnerDefaults } = require('./playwright.shared');

module.exports = defineConfig({
    ...runnerDefaults,

    /*
     | The mirror image of playwright.config.js's `testIgnore`. Both read the one
     | APP_SPEC constant, so a spec cannot end up in neither suite — or in both.
     */
    testMatch: APP_SPEC,

    /*
     | TWO SERVERS, BECAUSE BOTH POSTURES OF THE FEATURE FLAG ARE UNDER TEST.
     |
     | 8932 runs the application with LISTING_PREFERENCES_ENABLED on — the
     | interaction specs.
     |
     | 8933 runs the same application with it OFF, on its own port and its own
     | database. A real server rather than a config poke, because what is being
     | proven is that a deployment which sets nothing serves no control and no
     | endpoint.
     |
     | The timeouts are generous because each instance migrates the full chain
     | into a fresh SQLite file before serving.
     */
    webServer: [
        {
            command: 'node tests/browser/support/app-server.js',
            url: 'http://127.0.0.1:8932/login',
            reuseExistingServer: !process.env.CI,
            timeout: 180_000,
        },
        {
            command: 'node tests/browser/support/app-server.js',
            url: 'http://127.0.0.1:8933/login',
            // ...process.env is REQUIRED: Playwright REPLACES the child
            // environment with this object rather than merging it, so without
            // the spread the server started with no PATH and exited 1 before
            // printing a single line.
            env: { ...process.env, LP_APP_PORT: '8933', LP_FEATURE_ENABLED: 'false' },
            reuseExistingServer: !process.env.CI,
            timeout: 180_000,
        },
    ],

    use: {
        ...diagnosticDefaults,
        baseURL: 'http://127.0.0.1:8932',
    },

    projects: [
        {
            /*
             | Named `app` so `--project=app` remains meaningful, and so a trace
             | or report makes clear which suite produced it.
             |
             | No SwiftShader/ANGLE flags here: these specs render Blade and click
             | buttons. WebGL belongs to the map suite, and asking for software GL
             | that nothing uses would only slow every browser launch down.
             */
            name: 'app',
            use: {
                ...devices['Desktop Chrome'],
                baseURL: 'http://127.0.0.1:8932',
                launchOptions: launchOptions(),
            },
        },
    ],
});
