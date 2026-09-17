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
 | This environment supplies a Playwright chromium through
 | `REPLIT_PLAYWRIGHT_CHROMIUM_EXECUTABLE`, so `playwright install` — which would
 | download a browser — is never run. When that variable is absent Playwright
 | falls back to its own managed browser, which is the correct behaviour on a
 | developer machine or in GitHub Actions.
 */

const { defineConfig, devices } = require('@playwright/test');

const chromiumExecutable = process.env.REPLIT_PLAYWRIGHT_CHROMIUM_EXECUTABLE || undefined;

module.exports = defineConfig({
    testDir: './tests/browser',
    // Fixtures are static and deterministic; a slow default hides real hangs.
    timeout: 30_000,
    expect: { timeout: 5_000 },

    // Fail the run if a `test.only` is committed. A focused test that reaches CI
    // silently disables its siblings, which is the failure mode this whole suite
    // exists to prevent.
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: process.env.CI ? 2 : undefined,
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : [['list']],

    /*
     | TWO SERVERS, BECAUSE THERE ARE TWO KINDS OF RISK.
     |
     | 8931 serves STATIC fixtures for the Location DNA renderer, where the risk
     | is in the JavaScript and a booted Laravel would be pure overhead.
     |
     | 8932 serves the REAL application for the listing-preference specs, where
     | the risk is the opposite shape: persistence, authentication, CSRF and the
     | feature flag, none of which a static fixture can have. It builds its own
     | isolated SQLite environment — see tests/browser/support/app-server.js —
     | and never touches a developer's .env or any shared database.
     |
     | Its timeout is generous because it migrates the full chain before serving.
     */
    webServer: [
        {
            command: 'node tests/browser/support/static-server.js',
            url: 'http://127.0.0.1:8931/health',
            reuseExistingServer: !process.env.CI,
            timeout: 20_000,
        },
        {
            command: 'node tests/browser/support/app-server.js',
            url: 'http://127.0.0.1:8932/login',
            reuseExistingServer: !process.env.CI,
            timeout: 180_000,
        },
        /*
         | The same application with the feature flag OFF, on its own port and
         | its own database. A real server rather than a config poke, because
         | what is being proven is that a deployment which sets nothing serves no
         | control and no endpoint.
         */
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
        baseURL: 'http://127.0.0.1:8931',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'off',
    },

    projects: [
        /*
         | The real-application specs. Separate project rather than a different
         | baseURL inside one, so a spec cannot accidentally drive the wrong
         | origin and so `--project=app` runs them alone.
         */
        {
            name: 'app',
            testMatch: /listing-preference\.spec\.js/,
            use: {
                ...devices['Desktop Chrome'],
                baseURL: 'http://127.0.0.1:8932',
                launchOptions: {
                    ...(chromiumExecutable ? { executablePath: chromiumExecutable } : {}),
                },
            },
        },
        {
            name: 'chromium',
            testIgnore: /listing-preference\.spec\.js/,
            use: {
                ...devices['Desktop Chrome'],
                launchOptions: {
                    ...(chromiumExecutable ? { executablePath: chromiumExecutable } : {}),
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
                },
            },
        },
    ],
});
