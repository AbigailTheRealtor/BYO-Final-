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

    webServer: {
        command: 'node tests/browser/support/static-server.js',
        url: 'http://127.0.0.1:8931/health',
        reuseExistingServer: !process.env.CI,
        timeout: 20_000,
    },

    use: {
        baseURL: 'http://127.0.0.1:8931',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'off',
    },

    projects: [
        {
            name: 'chromium',
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
