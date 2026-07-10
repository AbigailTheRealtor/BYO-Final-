<?php

namespace Tests;

use App\Contracts\CommuteTimeAdapterInterface;
use App\Contracts\PoiLookupAdapterInterface;
use App\Services\LocationDna\CommuteTimeStubAdapter;
use App\Services\LocationDna\StubPoiLookupAdapter;
use GuzzleHttp\ClientInterface;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
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
        //
        // Runs BEFORE parent::setUpTraits(), which is where the framework invokes
        // RefreshDatabase::refreshDatabase() (potentially `migrate:fresh`) and
        // DatabaseTransactions::beginDatabaseTransaction(). It also runs before this
        // method's own `artisan migrate` below. Nothing may touch a database until the
        // resolved connection has been proven isolated.
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
        //    The gate below asks the RESOLVED CONNECTION whether it is isolated, not the
        //    config values this very method just assigned. Reading back your own writes
        //    proves nothing: `config(...sqlite.database) === ':memory:'` was true
        //    throughout the period when the connection was PostgreSQL against `heliumdb`,
        //    because `url` overrode it. `migrate --force` is DDL, and DDL is the one thing
        //    DatabaseTransactions cannot roll back. It must never run against a database
        //    whose identity has not been established.
        $uses = array_flip(class_uses_recursive(static::class));

        if (
            isset($uses[DatabaseTransactions::class]) &&
            $this->resolvedConnectionIsIsolatedSqlite() &&
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
     * The one database identity this suite may ever run against.
     */
    private const REQUIRED_DRIVER   = 'sqlite';
    private const REQUIRED_DATABASE = ':memory:';

    /** Substring that unambiguously names the live dev database, wherever it appears. */
    private const FORBIDDEN_DATABASE_NAME = 'heliumdb';

    /**
     * Abort immediately unless the RESOLVED CONNECTION is an isolated SQLite `:memory:`
     * database.
     *
     * WHY THIS INSPECTS THE CONNECTION AND NOT THE CONFIG
     * ---------------------------------------------------
     * The previous implementation read `config('database.default')` and
     * `config("database.connections.{$default}.database")` — values `setUpTraits()` had
     * assigned moments earlier. It therefore verified that this class can write to an
     * array, and nothing else.
     *
     * Those two keys were `sqlite` and `:memory:` throughout the entire period in which
     * the suite was in fact connected to PostgreSQL against `heliumdb`, the live dev
     * database. `config/database.php` declares the `sqlite` connection with
     * `'url' => env('DATABASE_URL')`, and Laravel's `ConfigurationUrlParser` gives `url`
     * precedence over BOTH `driver` and `database`. **A guard that reads the keys an
     * override replaces cannot detect the override.** This guard passed for the whole
     * incident. See `tests/Feature/Safeguards/TestDatabaseIdentityTest.php`.
     *
     * So: resolve the connection, then interrogate the object. Its class is chosen from
     * the post-override driver; its `getDatabaseName()` and `getConfig()` reflect parsed,
     * post-override configuration. Resolution costs nothing — `createPdoResolver()` returns
     * a closure, so no socket opens and no query runs.
     *
     * THE `test`-IN-THE-NAME ESCAPE HATCH IS GONE
     * -------------------------------------------
     * It previously accepted any database whose name contained the substring `test`. That
     * admits `heliumdb_test`, `latest_backup`, `contest`, and a MySQL or Postgres server on
     * any host — none of which is isolated, and all of which persist DDL that
     * `DatabaseTransactions` cannot roll back. There is exactly one safe configuration and
     * it is named above. Do not reintroduce a substring match.
     *
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    protected function assertSafeTestDatabase(): void
    {
        $violations = $this->databaseIsolationViolations();

        if ($violations === []) {
            return;
        }

        $this->fail(
            "\n\n" .
            "  ╔══════════════════════════════════════════════════════════╗\n" .
            "  ║      SAFETY ABORT — NON-ISOLATED DATABASE DETECTED        ║\n" .
            "  ╚══════════════════════════════════════════════════════════╝\n\n" .
            '  ' . implode("\n  ", $violations) . "\n\n" .
            "  APP_ENV : " . config('app.env') . "\n\n" .
            "  Tests must run against an isolated SQLite :memory: database.\n" .
            "  This is enforced in tests/bootstrap.php, which blanks DATABASE_URL and\n" .
            "  forces DB_CONNECTION=sqlite / DB_DATABASE=:memory: before Laravel boots.\n\n" .
            "  Do NOT relax this guard. A `url` key silently overrides both `driver` and\n" .
            "  `database`, which is how the suite came to run against the dev database.\n" .
            "  See docs/certification/TRACK-F-CHECKPOINT.md.\n"
        );
    }

    /**
     * True when the resolved connection is a genuinely isolated SQLite `:memory:` database.
     *
     * Used to gate `migrate --force`, which emits DDL that no transaction can undo.
     */
    protected function resolvedConnectionIsIsolatedSqlite(): bool
    {
        return $this->databaseIsolationViolations() === [];
    }

    /**
     * Every reason the resolved connection is unsafe. Empty array means safe.
     *
     * Issues no query: only the resolved object's class, driver, database name, and parsed
     * config array are read.
     *
     * @return list<string>
     */
    protected function databaseIsolationViolations(): array
    {
        $connection = DB::connection();

        $driver     = (string) $connection->getDriverName();
        $database   = (string) $connection->getDatabaseName();
        $config     = $connection->getConfig();
        $violations = [];

        if ($driver !== self::REQUIRED_DRIVER) {
            $violations[] = "Resolved driver is '{$driver}', expected '" . self::REQUIRED_DRIVER . "'.";
        }

        if ($database !== self::REQUIRED_DATABASE) {
            $violations[] = "Resolved database is '{$database}', expected '" . self::REQUIRED_DATABASE . "'.";
        }

        // Assert against the concrete class, not merely the driver string: the class is what
        // the framework selected after the url override was applied.
        if ($connection instanceof PostgresConnection) {
            $violations[] = 'Resolved a PostgresConnection. The suite is pointed at PostgreSQL.';
        }

        if ($connection instanceof MySqlConnection) {
            $violations[] = 'Resolved a MySqlConnection. The suite is pointed at MySQL.';
        }

        // A `url` is the carrier of the override that caused the incident, so any non-empty
        // value is disqualifying — an isolated SQLite connection has no use for one.
        //
        // It must be read from the RAW config, not from `$connection->getConfig()`.
        // `ConfigurationUrlParser::parseConfiguration()` does `Arr::pull($config, 'url')`,
        // which *removes* the key once it has been applied. A resolved connection therefore
        // never carries a `url`, and a check against the resolved config would be dead code
        // in exactly the case it exists to catch. (Discovered by
        // DatabaseGuardPoisoningTest::the_guard_never_leaks_the_database_password... — the
        // poisoning test finding a real hole in the guard, which is what it is for.)
        //
        // Reading raw config here is safe in a way the OLD guard's config reads were not:
        // this can only ADD a violation. Safety is still granted solely by the resolved
        // connection above. A config check may never be the reason we proceed.
        $rawUrl = (string) (config('database.connections.' . config('database.default') . '.url')
            ?? $config['url'] ?? '');

        if (trim($rawUrl) !== '') {
            $violations[] = 'Connection carries a non-empty `url` (' . $this->maskCredentials($rawUrl)
                . '). ConfigurationUrlParser gives it precedence over `driver` and `database`.';
        }

        // An in-memory SQLite database has no host. A host means a server, and a server
        // means shared, persistent state.
        if (trim((string) ($config['host'] ?? '')) !== '') {
            $violations[] = "Connection has a host ('" . $config['host'] . "'). An isolated SQLite database has none.";
        }

        // Belt and braces: the dev database's name must not appear anywhere in the resolved
        // config, whatever key smuggled it in.
        foreach ($config as $key => $value) {
            if (is_scalar($value) && stripos((string) $value, self::FORBIDDEN_DATABASE_NAME) !== false) {
                $violations[] = "Config key '{$key}' names the live dev database ('"
                    . self::FORBIDDEN_DATABASE_NAME . "'): " . $this->maskCredentials((string) $value);
            }
        }

        return $violations;
    }

    /** Never let a password reach a failure message or CI log. */
    private function maskCredentials(string $value): string
    {
        return (string) preg_replace('#://[^@/]*@#', '://***@', $value);
    }
}
