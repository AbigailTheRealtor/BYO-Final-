<?php

namespace Tests\Feature\Safeguards;

use Illuminate\Database\ConfigurationUrlParser;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The test suite must be physically incapable of reaching the shared development database.
 *
 * WHAT WENT WRONG
 * ---------------
 * `config/database.php` contains two lines that, together, defeat every attempt to force
 * SQLite from inside Laravel:
 *
 *     'default' => env('DATABASE_URL') ? 'pgsql' : env('DB_CONNECTION', 'mysql'),
 *     'sqlite'  => ['driver' => 'sqlite', 'url' => env('DATABASE_URL'), ...],
 *
 * Replit injects `DATABASE_URL=postgresql://…/heliumdb` as a system environment variable.
 * phpdotenv's immutable repository will not overwrite it, so `.env.testing` and phpunit.xml's
 * unforced `<env>` entries lose. The first line then selects `pgsql` outright. Worse, the
 * second line hands the *sqlite* connection a PostgreSQL DSN, and `ConfigurationUrlParser` —
 * which `ConnectionFactory` runs on every connection before construction — lets a `url`
 * override that connection's `driver` and `database`.
 *
 * The consequence: `config(['database.default' => 'sqlite'])` in `TestCase` forced a
 * connection that still resolved to `pgsql`/`heliumdb`. The old guard then read
 * `config('database.connections.sqlite.database')`, saw the `:memory:` it had itself just
 * written, and passed — while `DatabaseTransactions` opened transactions on the dev database
 * and `RefreshDatabase` stood one `migrate:fresh` away from dropping every table in it.
 *
 * WHAT THIS TEST DOES
 * -------------------
 * Inspects the RESOLVED connection — what PDO would actually open — rather than the config
 * values the bootstrap wrote. It uses the same `ConfigurationUrlParser` the factory uses, so
 * there is no second implementation to drift out of sync.
 *
 * It deliberately uses NEITHER `DatabaseTransactions` NOR `RefreshDatabase`, and issues no
 * DDL and no query. Those traits are precisely the mechanisms this test exists to make safe;
 * depending on them would mean the safety check could only run once the unsafe thing had
 * already happened. It never calls `markTestSkipped()` either — a skipped safety test reads
 * as green in CI, which is the failure mode that let this survive.
 *
 * @see tests/bootstrap.php
 * @see tests/TestCase::resolvedConnection()
 */
class TestDatabaseIdentityTest extends TestCase
{
    /** @test */
    public function database_url_is_blank_on_every_environment_surface(): void
    {
        // Blank, not absent: unsetting it would let `.env` repopulate the value. All three
        // surfaces are checked because Laravel's Env reads $_SERVER, $_ENV and putenv().
        $this->assertSame('', getenv('DATABASE_URL'), 'getenv(DATABASE_URL) is not blank.');

        $this->assertArrayHasKey('DATABASE_URL', $_ENV);
        $this->assertSame('', $_ENV['DATABASE_URL'], '$_ENV[DATABASE_URL] is not blank.');

        $this->assertArrayHasKey('DATABASE_URL', $_SERVER);
        $this->assertSame('', $_SERVER['DATABASE_URL'], '$_SERVER[DATABASE_URL] is not blank.');

        // And the thing that actually matters: env() must see it as falsy, because
        // config/database.php branches on `env('DATABASE_URL') ? 'pgsql' : ...`.
        $this->assertFalse((bool) env('DATABASE_URL'), 'env(DATABASE_URL) is truthy; database.default would be pgsql.');
    }

    /** @test */
    public function sqlite_is_forced_on_every_environment_surface(): void
    {
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'] as $var => $expected) {
            $this->assertSame($expected, getenv($var), "getenv({$var}) is not '{$expected}'.");
            $this->assertSame($expected, $_ENV[$var] ?? null, "\$_ENV[{$var}] is not '{$expected}'.");
            $this->assertSame($expected, $_SERVER[$var] ?? null, "\$_SERVER[{$var}] is not '{$expected}'.");
        }
    }

    /** @test */
    public function the_resolved_connection_is_sqlite_in_memory(): void
    {
        // The headline assertion. `resolvedConnection()` runs ConfigurationUrlParser, so this
        // is what ConnectionFactory would build — not what config() was told to say.
        $resolved = static::resolvedConnection();

        $this->assertSame('sqlite', $resolved['driver'], 'Resolved driver is not sqlite.');
        $this->assertSame(':memory:', $resolved['database'], 'Resolved database is not :memory:.');
        $this->assertNull($resolved['host'], 'A resolved SQLite connection must have no host.');
    }

    /** @test */
    public function the_resolved_connection_is_neither_postgresql_nor_mysql(): void
    {
        $this->assertNotContains(
            static::resolvedConnection()['driver'],
            ['pgsql', 'mysql', 'sqlsrv'],
            'Tests resolved to a networked database engine.',
        );
    }

    /** @test */
    public function nothing_in_the_resolved_connection_references_heliumdb(): void
    {
        $resolved = static::resolvedConnection();

        $this->assertStringNotContainsStringIgnoringCase(
            'heliumdb',
            json_encode($resolved) ?: '',
            'The resolved connection references the shared development database.',
        );

        // Belt and braces: the sqlite connection's own config, url included.
        $this->assertStringNotContainsStringIgnoringCase(
            'heliumdb',
            json_encode(config('database.connections.sqlite')) ?: '',
            "The sqlite connection's config references heliumdb.",
        );
    }

    /** @test */
    public function the_sqlite_connection_carries_no_url(): void
    {
        // This single value is what turned a connection named `sqlite` into pgsql/heliumdb.
        $url = config('database.connections.sqlite.url');

        $this->assertTrue(
            $url === null || trim((string) $url) === '',
            'The sqlite connection has a non-empty `url`, which overrides its driver and database.',
        );
    }

    /** @test */
    public function the_connection_named_sqlite_cannot_resolve_to_anything_else(): void
    {
        // `database.default` could be pointed elsewhere by a future edit. Pin the named
        // connection independently, through the same parser the factory uses.
        $parsed = (new ConfigurationUrlParser())->parseConfiguration(config('database.connections.sqlite'));

        $this->assertSame('sqlite', $parsed['driver'] ?? null);
        $this->assertSame(':memory:', $parsed['database'] ?? null);
    }

    /** @test */
    public function the_safety_guard_reports_no_violations(): void
    {
        $this->assertSame(
            [],
            static::databaseSafetyViolations(),
            'The test-database safety guard reports violations.',
        );
    }

    /** @test */
    public function the_safety_guard_rejects_a_poisoned_sqlite_url(): void
    {
        // Prove the guard is not vacuous. Re-poison the config exactly as the environment
        // used to, and confirm the guard catches every facet of it. Pure config mutation:
        // no connection is resolved, no query runs, and the value is restored afterwards.
        $original = config('database.connections.sqlite.url');

        try {
            config(['database.connections.sqlite.url' => 'postgresql://user:pw@helium/heliumdb?sslmode=disable']);

            $violations = static::databaseSafetyViolations();

            $this->assertNotEmpty($violations, 'A postgres DSN on the sqlite connection was not rejected.');

            $joined = strtolower(implode(' | ', $violations));
            $this->assertStringContainsString('heliumdb', $joined);
            $this->assertStringContainsString('url', $joined);
            $this->assertStringContainsString("driver is 'pgsql'", $joined);
        } finally {
            config(['database.connections.sqlite.url' => $original]);
        }

        // Restored, and safe again — otherwise this test would leak an unsafe config into
        // every test that follows it in the same process.
        $this->assertSame([], static::databaseSafetyViolations());
    }

    /** @test */
    public function the_safety_guard_has_no_database_name_escape_hatch(): void
    {
        // The removed rule was `str_contains($database, 'test')`. A shared remote database is
        // not made safe by its name, and `heliumdb_test` would have satisfied it.
        $original = config('database.connections.sqlite.database');

        try {
            config(['database.connections.sqlite.database' => 'heliumdb_test']);

            $this->assertNotEmpty(
                static::databaseSafetyViolations(),
                "A database named 'heliumdb_test' was accepted. The escape hatch is still present.",
            );
        } finally {
            config(['database.connections.sqlite.database' => $original]);
        }

        $this->assertSame([], static::databaseSafetyViolations());
    }

    /** @test */
    public function the_safety_guard_detects_a_bootstrap_regression_independently_of_config(): void
    {
        // Layer independence, asserted directly.
        //
        // TestCase::setUpTraits() no longer overwrites database.default / sqlite.database /
        // sqlite.url before the guard reads them, because a guard that validates values it
        // just wrote proves nothing. This test poisons ONLY the process environment — config()
        // is left exactly as the bootstrap produced it — and requires the guard to notice.
        //
        // If someone reinstates the config() forcing block in setUpTraits(), this test still
        // passes (config is untouched here) while the DATABASE_URL surface check fires. If
        // someone instead deletes the surface check, the poisoned-url test above still covers
        // layer 2. Neither layer can vouch for the other.
        $originalServer = $_SERVER['DATABASE_URL'] ?? null;

        try {
            $_SERVER['DATABASE_URL'] = 'postgresql://user:pw@helium/heliumdb?sslmode=disable';

            $violations = static::databaseSafetyViolations();

            $this->assertNotEmpty($violations, 'A live DATABASE_URL in $_SERVER was not detected.');
            $this->assertStringContainsString(
                'did not neutralise DATABASE_URL',
                implode(' | ', $violations),
                'The guard did not attribute the failure to the bootstrap layer.',
            );
        } finally {
            if ($originalServer === null) {
                unset($_SERVER['DATABASE_URL']);
            } else {
                $_SERVER['DATABASE_URL'] = $originalServer;
            }
        }

        // Restored. A safety test that leaks an unsafe environment into the rest of the
        // process would be worse than no test at all.
        $this->assertSame([], static::databaseSafetyViolations());
    }

    /** @test */
    public function no_connection_has_been_opened_against_a_networked_database(): void
    {
        // getDriverName()/getDatabaseName() read the resolved connection config; neither
        // opens a PDO handle. Any Connection already instantiated in this process — by an
        // earlier test in the same run, or by migrations — must be SQLite :memory:.
        //
        // Built as a map and asserted in one go rather than assert-inside-loop: with zero
        // connections resolved, a loop body would run zero assertions and PHPUnit would mark
        // this "risky" — a safety test that quietly verified nothing.
        $connections = [];

        foreach (DB::getConnections() as $name => $connection) {
            $connections[$name] = $connection->getDriverName() . '|' . $connection->getDatabaseName();
        }

        $unsafe = array_filter($connections, static fn (string $id): bool => $id !== 'sqlite|:memory:');

        $this->assertSame([], $unsafe, 'A connection resolved to something other than SQLite :memory:.');
    }
}
