<?php

namespace Tests\Feature\Deployment;

use Illuminate\Routing\UrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Tests\TestCase;

/**
 * Tracked configuration must never pin the application to a development host.
 *
 * THE INCIDENT THIS PREVENTS
 * --------------------------
 * `.replit` declared, under `[userenv.shared]`:
 *
 *     APP_URL   = "https://<uuid>-00-<slug>.spock.replit.dev"
 *     ASSET_URL = "https://<uuid>-00-<slug>.spock.replit.dev"
 *
 * — the DEVELOPMENT workspace hostname. Shared userenv reaches deployments too,
 * and `Illuminate\Support\Env` builds an IMMUTABLE repository, so a value already
 * in the process environment beats the same key in `.env`. The production entry
 * points export `APP_ENV` and `APP_DEBUG` and stop there, so nothing overrode
 * these two.
 *
 * The consequence is not subtle. `ASSET_URL` becomes `app.asset_url`, which
 * `RoutingServiceProvider` hands to `UrlGenerator` as `$assetRoot`, and
 * `UrlGenerator::asset()` reads:
 *
 *     $root = $this->assetRoot ?: $this->formatRoot($this->formatScheme($secure));
 *
 * A non-empty `assetRoot` short-circuits the request root entirely. Every
 * stylesheet, script and image on the PRODUCTION site would therefore be fetched
 * from the development workspace — a host that is reachable only while that
 * workspace is awake, and which is nobody's idea of a CDN. `APP_URL` is the same
 * story for console-generated absolute URLs, via
 * `Illuminate\Foundation\Bootstrap\SetRequestForConsole`, which builds its
 * Request from `config('app.url')`.
 *
 * WHY ABSENT IS THE FIX, AND NOT A PLACEHOLDER
 * --------------------------------------------
 * `config/app.php` is already defensive — `'asset_url' => env('ASSET_URL', null)`
 * — and a null asset root is exactly what makes `asset()` fall through to the
 * live request root. Combined with `TrustProxies` (`$proxies = '*'`, honouring
 * X-Forwarded-Host/Proto) the generated URL follows whatever host actually served
 * the page. So removal is not "leaving it unset until we know the real value";
 * removal IS the correct production configuration, and it is also the only one
 * that is right before the first deploy has assigned a hostname.
 *
 * The guard below matches `replit.dev` generically rather than today's workspace
 * UUID, because the next workspace will have a different UUID and the same
 * problem.
 */
class ProductionUrlIsolationTest extends TestCase
{
    /** The tracked `.replit`, read from the repository root. */
    private function replit(): string
    {
        $path = base_path('.replit');

        $this->assertFileExists($path, '.replit must exist at the repository root');

        return (string) file_get_contents($path);
    }

    /** The `[userenv.shared]` table only, up to the next section header. */
    private function sharedUserenvBlock(): string
    {
        $replit = $this->replit();

        $this->assertStringContainsString('[userenv.shared]', $replit);

        $start = strpos($replit, '[userenv.shared]');
        $rest  = substr($replit, $start + strlen('[userenv.shared]'));

        if (preg_match('/\R\[/', $rest, $m, PREG_OFFSET_CAPTURE) === 1) {
            $rest = substr($rest, 0, $m[0][1]);
        }

        return $rest;
    }

    /**
     * Only real TOML assignments count — a commented explanation mentioning the
     * key must not read as a declaration, or the fix's own comment would trip
     * the guard it exists to describe.
     */
    private function assertKeyNotDeclared(string $key, string $block): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/^\s*' . preg_quote($key, '/') . '\s*=/m',
            $block,
            "[userenv.shared] must not declare {$key} — it reaches deployments and pins them to one host"
        );
    }

    public function test_shared_userenv_does_not_declare_app_url(): void
    {
        $this->assertKeyNotDeclared('APP_URL', $this->sharedUserenvBlock());
    }

    public function test_shared_userenv_does_not_declare_asset_url(): void
    {
        $this->assertKeyNotDeclared('ASSET_URL', $this->sharedUserenvBlock());
    }

    /**
     * The generic guard. Not scoped to `[userenv.shared]`, and not scoped to the
     * current workspace UUID: no tracked Replit configuration may hardcode a
     * development workspace host anywhere, under any key.
     */
    public function test_no_replit_dev_hostname_is_hardcoded_in_tracked_replit_config(): void
    {
        $replit = $this->replit();

        $lines = preg_split('/\R/', $replit) ?: [];

        foreach ($lines as $number => $line) {
            // Comments are prose about the rule, not configuration.
            if (preg_match('/^\s*#/', $line) === 1) {
                continue;
            }

            $this->assertStringNotContainsString(
                'replit.dev',
                $line,
                "Tracked .replit must not hardcode a development workspace host (line "
                . ($number + 1) . '): a *.replit.dev value reaches deployments through shared '
                . 'userenv and forces production onto the development workspace.'
            );
        }
    }

    /** The config defaults that make an absent ASSET_URL safe. */
    public function test_config_defaults_leave_asset_url_null_and_do_not_invent_a_host(): void
    {
        $app = (string) file_get_contents(base_path('config/app.php'));

        $this->assertMatchesRegularExpression(
            "/'asset_url'\s*=>\s*env\(\s*'ASSET_URL'\s*,\s*null\s*\)/",
            $app,
            "config/app.php must default asset_url to null so asset() falls back to the request root"
        );
    }

    /**
     * The behaviour itself, proven against the real UrlGenerator rather than
     * asserted about it: with no asset root configured, an asset URL is built
     * from the request that is actually being served.
     */
    public function test_asset_urls_follow_the_request_host_when_no_asset_url_is_configured(): void
    {
        $request = Request::create('https://bidyouroffer.example/listings/1', 'GET');

        $generator = new UrlGenerator(new RouteCollection(), $request, null);

        $url = $generator->asset('css/app.css');

        $this->assertSame('https://bidyouroffer.example/css/app.css', $url);
        $this->assertStringNotContainsString('replit.dev', $url);
    }

    /**
     * The negative control: this is what the removed configuration did, and it is
     * why absence is load-bearing rather than cosmetic. If this ever stops
     * demonstrating the override, the test above has stopped proving anything.
     */
    public function test_a_configured_asset_root_would_override_the_request_host(): void
    {
        $request = Request::create('https://bidyouroffer.example/listings/1', 'GET');

        $generator = new UrlGenerator(
            new RouteCollection(),
            $request,
            'https://workspace-uuid-00-slug.spock.replit.dev'
        );

        $this->assertSame(
            'https://workspace-uuid-00-slug.spock.replit.dev/css/app.css',
            $generator->asset('css/app.css'),
            'Control: a non-empty asset root must be observed replacing the request host'
        );
    }

    /**
     * The production entry point must not reintroduce the problem by exporting a
     * host of its own. It owns APP_ENV and APP_DEBUG deliberately; URLs belong to
     * the request.
     */
    public function test_the_production_entrypoint_does_not_export_a_url(): void
    {
        $script = (string) file_get_contents(base_path('deploy/start-production.sh'));

        foreach (['APP_URL', 'ASSET_URL'] as $key) {
            $this->assertDoesNotMatchRegularExpression(
                '/^\s*export\s+' . $key . '=/m',
                $script,
                "deploy/start-production.sh must not export {$key}"
            );
        }
    }
}
