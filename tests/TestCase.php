<?php

namespace Tests;

use App\Contracts\CommuteTimeAdapterInterface;
use App\Contracts\PoiLookupAdapterInterface;
use App\Services\LocationDna\CommuteTimeStubAdapter;
use App\Services\LocationDna\StubPoiLookupAdapter;
use GuzzleHttp\ClientInterface;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;
use Tests\Support\BlocksGooglePlacesHttpClient;
use Tests\Support\Http\StrayRequestGuardFactory;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Tracks whether the in-memory SQLite schema has been built during this
     * PHP process.  Static so it persists across all test class instances.
     */
    private static bool $sqliteSchemaBuilt = false;

    /**
     * Called by BaseTestCase::setUp() after the application is created but
     * BEFORE DatabaseTransactions::beginDatabaseTransaction() opens its
     * wrapping transaction.  This is the correct hook for:
     *
     *  1. Asserting we are not pointed at the dev database.
     *  2. Building the SQLite in-memory schema once per test-runner process.
     */
    protected function setUpTraits(): array
    {
        // ── Force SQLite :memory: regardless of system env vars ───────────────
        //
        // Replit injects DB_CONNECTION=pgsql / DB_DATABASE=heliumdb as system-
        // level environment variables that phpdotenv's ImmutableStringRepository
        // will not override, so phpunit.xml <env> and .env.testing cannot win.
        // Calling config() here — after the app is created but before any trait
        // or query runs — is the authoritative way to force the test database.
        config([
            'database.default'                       => 'sqlite',
            'database.connections.sqlite.database'   => ':memory:',
        ]);

        // ── Rule 4: pre-test safety guard ────────────────────────────────────
        $this->assertSafeTestDatabase();

        // ── Google Places hard block (2026-07-05 NearbySearch incident) ──────
        //    (a) Refuse to run if a live GOOGLE_PLACES_API_KEY leaked in as a
        //        system env var (dotenv cannot override it — this is exactly how
        //        the billable test-suite calls happened).
        //    (b) Force the key blank in config regardless, so no code path can
        //        authenticate a NearbySearch call.
        //    (c) Bind a fail-loud HTTP client so any un-mocked outbound Google
        //        request throws instead of reaching the live network.
        //    See docs/investigations/Google-Places-Root-Cause-Analysis.md.
        $this->guardAgainstLiveGooglePlacesKey();
        config(['services.google.places_key' => null]);
        $this->app->instance(ClientInterface::class, new BlocksGooglePlacesHttpClient());

        // ── Phase 0 / S1c: default the provider adapters to their stubs ──────
        //    The AppServiceProvider binding already degrades to StubPoiLookupAdapter
        //    when the Places key is blank, which it always is above. Binding the stub
        //    explicitly means test isolation no longer *depends* on that config
        //    coincidence: no reachable configuration resolves a live provider adapter.
        //
        //    Registered with instance() rather than bind() deliberately. Tests that
        //    assert the production binding itself (PoiLookupAdapterBindingTest) call
        //    $this->app->forgetInstance(...) first, which drops these instances and
        //    falls through to the provider's closure. bind() would shadow it instead.
        $this->app->instance(PoiLookupAdapterInterface::class, new StubPoiLookupAdapter());
        $this->app->instance(CommuteTimeAdapterInterface::class, new CommuteTimeStubAdapter());

        // ── Phase 0 / S1d: stray-request guard for the Http facade ───────────
        //    BlocksGooglePlacesHttpClient only guards the container-bound Guzzle
        //    ClientInterface. The Http facade constructs its own Guzzle client and
        //    never touches the container, so every Http::get() in app/ — Bridge,
        //    FEMA, Census, MLS import, and GeocodeSelleryLandlordListings' *Google
        //    geocode call* — could reach the live network from a test.
        //
        //    Laravel 9's Http::preventStrayRequests() would close this. This app runs
        //    Laravel 8.83, which has no such method (erratum E-39), so we bind a
        //    backported factory that throws on any unstubbed request.
        $this->app->instance(HttpFactory::class, new StrayRequestGuardFactory($this->app['events']));

        // ── Rule 1 / Rule 3: one-time schema setup for SQLite :memory: ───────
        //    Run BEFORE parent::setUpTraits() so migrations execute OUTSIDE the
        //    DatabaseTransactions wrapping transaction (DDL in SQLite is
        //    transactional; committing outside keeps the schema between tests).
        $uses = array_flip(class_uses_recursive(static::class));

        if (
            isset($uses[DatabaseTransactions::class]) &&
            config('database.default') === 'sqlite' &&
            config('database.connections.sqlite.database') === ':memory:' &&
            ! static::$sqliteSchemaBuilt
        ) {
            $this->artisan('migrate', ['--force' => true]);
            static::$sqliteSchemaBuilt = true;
        }

        return parent::setUpTraits();
    }

    /**
     * Detect a non-blank GOOGLE_PLACES_API_KEY present in the real process
     * environment. Checks $_SERVER / $_ENV / getenv so a system-level secret
     * (which phpdotenv's immutable repository will not override) is caught even
     * though phpunit.xml sets the value to an empty string.
     *
     * Returns the leaked value (for length reporting) or null when clean. The
     * value is never logged or asserted on — only its presence matters.
     */
    public static function detectLiveGooglePlacesKey(): ?string
    {
        $var = 'GOOGLE_PLACES_API_KEY';

        foreach ([$_SERVER, $_ENV] as $bag) {
            if (array_key_exists($var, $bag) && trim((string) $bag[$var]) !== '') {
                return trim((string) $bag[$var]);
            }
        }

        $raw = getenv($var);
        if ($raw !== false && trim($raw) !== '') {
            return trim($raw);
        }

        return null;
    }

    /**
     * Throw immediately when APP_ENV=testing and a real Google Places key is
     * present. Fail-closed: a leaked key is exactly what let the test suite make
     * ~21,702 billable NearbySearch calls on 2026-07-05.
     *
     * @throws RuntimeException when a live key is detected under APP_ENV=testing
     */
    protected function guardAgainstLiveGooglePlacesKey(): void
    {
        if (config('app.env') !== 'testing') {
            return;
        }

        $leaked = static::detectLiveGooglePlacesKey();

        if ($leaked !== null) {
            throw new RuntimeException(
                'A live GOOGLE_PLACES_API_KEY (length ' . strlen($leaked) . ') is present in the '
                . 'testing environment. Refusing to run: unset it so no billable Google Places '
                . 'NearbySearch call can occur. The key value is intentionally not shown. See '
                . 'docs/investigations/Google-Places-Root-Cause-Analysis.md.'
            );
        }
    }

    /**
     * Abort immediately if the test suite is aimed at the live dev database.
     *
     * Safe configurations:
     *   - SQLite :memory:                       (isolated, ephemeral)
     *   - Any DB whose name contains "test"     (explicit test database)
     *
     * Everything else is assumed to be the dev/production database and will
     * cause an immediate failure rather than silently destroying data.
     *
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    private function assertSafeTestDatabase(): void
    {
        $connection = config('database.default');
        $database   = config("database.connections.{$connection}.database");

        // SQLite in-memory is always safe
        if ($connection === 'sqlite' && $database === ':memory:') {
            return;
        }

        // A database whose name explicitly contains "test" is safe
        if (str_contains((string) $database, 'test')) {
            return;
        }

        // Anything else is the dev/production database — refuse to run
        $this->fail(
            "\n\n" .
            "  ╔══════════════════════════════════════════════════════════╗\n" .
            "  ║          SAFETY ABORT — DEV DATABASE DETECTED           ║\n" .
            "  ╚══════════════════════════════════════════════════════════╝\n\n" .
            "  Connection : {$connection}\n" .
            "  Database   : {$database}\n" .
            "  APP_ENV    : " . config('app.env') . "\n\n" .
            "  Tests must not run against the development database.\n" .
            "  phpunit.xml must configure:\n" .
            "    <server name=\"DB_CONNECTION\" value=\"sqlite\"/>\n" .
            "    <server name=\"DB_DATABASE\"   value=\":memory:\"/>\n" .
            "  Or point DB_DATABASE to a name containing 'test'.\n"
        );
    }
}
