<?php

namespace Tests;

use App\Contracts\CommuteTimeAdapterInterface;
use App\Contracts\PoiLookupAdapterInterface;
use App\Services\LocationDna\CommuteTimeStubAdapter;
use App\Services\LocationDna\StubPoiLookupAdapter;
use GuzzleHttp\ClientInterface;
use Illuminate\Database\ConfigurationUrlParser;
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
        //
        // `sqlite.url` is the third line here and the one that used to be missing.
        // config/database.php gives EVERY connection `'url' => env('DATABASE_URL')`,
        // and Illuminate\Database\ConfigurationUrlParser lets that url override the
        // connection's own driver and database. So a connection named `sqlite`,
        // carrying a postgres DSN, resolves to pgsql/heliumdb — and the two lines
        // above would have "forced" nothing. tests/bootstrap.php already blanks
        // DATABASE_URL before Dotenv boots; this nulls the derived config value too,
        // so the guard below cannot be satisfied by a stale url.
        config([
            'database.default'                     => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url'      => null,
        ]);

        // ── Rule 4: pre-test safety guard ────────────────────────────────────
        //    Runs before parent::setUpTraits(), so it fires ahead of both
        //    DatabaseTransactions::beginDatabaseTransaction() and
        //    RefreshDatabase::refreshDatabase() — i.e. before anything can open a
        //    transaction or drop a table on the wrong database.
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
        //    The gate reads the RESOLVED connection, not the config values written
        //    above. Gating on `config('database.connections.sqlite.database')` was how
        //    `migrate --force` came to run against heliumdb: the raw value said
        //    ':memory:' while ConfigurationUrlParser resolved the same connection to
        //    pgsql. assertSafeTestDatabase() has already failed the test if resolution
        //    is unsafe; this repeats the check because a migration is irreversible and
        //    must never depend on an earlier line still being there.
        $uses     = array_flip(class_uses_recursive(static::class));
        $resolved = static::resolvedConnection();

        if (
            isset($uses[DatabaseTransactions::class]) &&
            $resolved['driver'] === 'sqlite' &&
            $resolved['database'] === ':memory:' &&
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
     * Resolve the default connection the way Illuminate's ConnectionFactory will.
     *
     * This is the whole point of the guard. `config('database.connections.sqlite.database')`
     * reports what someone wrote into the config array; it does NOT report what PDO will
     * open. ConnectionFactory passes every connection through ConfigurationUrlParser first,
     * and a non-empty `url` overrides that connection's `driver` and `database`. Because
     * config/database.php assigns `'url' => env('DATABASE_URL')` to all four connections,
     * a connection named `sqlite` will happily resolve to pgsql/heliumdb.
     *
     * Uses the same parser as the factory, so there is no second implementation to drift.
     * Constructs no Connection, opens no PDO handle, issues no query — safe to call before
     * we know whether the target is safe, which is exactly when we need it.
     *
     * @return array{name: string, driver: ?string, database: ?string, host: ?string, url: mixed}
     */
    public static function resolvedConnection(): array
    {
        $name   = (string) config('database.default');
        $config = config("database.connections.{$name}") ?? [];
        $parsed = (new ConfigurationUrlParser())->parseConfiguration($config);

        return [
            'name'     => $name,
            'driver'   => $parsed['driver'] ?? null,
            'database' => $parsed['database'] ?? null,
            'host'     => $parsed['host'] ?? null,
            'url'      => $config['url'] ?? null,
        ];
    }

    /**
     * Every reason the resolved connection is unsafe to run tests against. Empty means safe.
     *
     * Deliberately enumerates ALL violations rather than returning on the first, so a failure
     * message names every problem instead of sending the reader round the loop once per fix.
     *
     * There is no "database name contains 'test'" escape hatch any more. It was never proof of
     * isolation — `heliumdb_test`, `latest`, and `contest` all satisfy it, and a shared remote
     * database is not made safe by its name. The only safe target is SQLite `:memory:`, which
     * is per-process and cannot outlive the run.
     *
     * @return list<string>
     */
    public static function databaseSafetyViolations(): array
    {
        $resolved   = static::resolvedConnection();
        $driver     = $resolved['driver'];
        $database   = (string) $resolved['database'];
        $violations = [];

        if ($driver !== 'sqlite') {
            $violations[] = "Resolved driver is '{$driver}', expected 'sqlite'.";
        }

        if ($database !== ':memory:') {
            $violations[] = "Resolved database is '{$database}', expected ':memory:'.";
        }

        // Named explicitly. A generic driver check would catch these, but naming the two
        // engines that can actually reach a shared server makes the failure unambiguous.
        if (in_array($driver, ['pgsql', 'mysql'], true)) {
            $violations[] = "Resolved driver '{$driver}' is a networked database engine. Tests must never touch one.";
        }

        // The Replit dev database. Checked by name across the whole resolved config, because a
        // DSN can smuggle it in through `url` even when `database` looks innocent.
        if (str_contains(strtolower(json_encode($resolved) ?: ''), 'heliumdb')) {
            $violations[] = "The resolved connection references 'heliumdb', the shared development database.";
        }

        // The trap that made every earlier guard useless: a `url` on the sqlite connection
        // silently overrides its driver and database at construction time.
        $sqliteUrl = config('database.connections.sqlite.url');
        if ($sqliteUrl !== null && trim((string) $sqliteUrl) !== '') {
            $violations[] = 'The sqlite connection carries a non-empty `url`, which overrides its driver and database.';
        }

        return $violations;
    }

    /**
     * Abort immediately unless the resolved connection is SQLite `:memory:`.
     *
     * Fails closed and fails loudly. Never skips: a skipped safety test is indistinguishable
     * from a passing one in CI output, and this is the assertion standing between the suite
     * and `migrate:fresh` on the shared development database.
     *
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    private function assertSafeTestDatabase(): void
    {
        $violations = static::databaseSafetyViolations();

        if ($violations === []) {
            return;
        }

        $resolved = static::resolvedConnection();

        $this->fail(
            "\n\n" .
            "  ╔══════════════════════════════════════════════════════════╗\n" .
            "  ║        SAFETY ABORT — UNSAFE TEST DATABASE             ║\n" .
            "  ╚══════════════════════════════════════════════════════════╝\n\n" .
            "  The RESOLVED connection — what PDO would actually open, after\n" .
            "  ConfigurationUrlParser applies any `url` — is not SQLite :memory:.\n\n" .
            "  Connection name : {$resolved['name']}\n" .
            '  Resolved driver : ' . var_export($resolved['driver'], true) . "\n" .
            '  Resolved database: ' . var_export($resolved['database'], true) . "\n" .
            '  Resolved host   : ' . var_export($resolved['host'], true) . "\n" .
            '  APP_ENV         : ' . config('app.env') . "\n\n" .
            "  Violations:\n    - " . implode("\n    - ", $violations) . "\n\n" .
            "  Fix: tests/bootstrap.php must blank DATABASE_URL and force\n" .
            "  DB_CONNECTION=sqlite / DB_DATABASE=:memory: across putenv(), \$_ENV and\n" .
            "  \$_SERVER before Dotenv boots. phpunit.xml repeats them with force=\"true\".\n" .
            "  See tests/Feature/Safeguards/TestDatabaseIdentityTest.php.\n"
        );
    }
}
