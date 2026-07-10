<?php

namespace Tests\Feature\Safeguards;

use Illuminate\Database\PostgresConnection;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The suite runs on an isolated SQLite `:memory:` database — asserted against the
 * **resolved connection**, not against configuration values.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THIS TEST MUST NOT BE SKIPPED, MARKED INCOMPLETE, OR WRAPPED IN A DRIVER GUARD.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * WHAT WENT WRONG, AND WHY THIS TEST IS SHAPED THIS WAY
 * -----------------------------------------------------
 * `config/database.php:38-44` declares the `sqlite` connection with
 * `'url' => env('DATABASE_URL')`. This host injects
 * `DATABASE_URL=postgresql://postgres:password@helium/heliumdb`. Laravel's
 * `ConfigurationUrlParser` gives `url` **precedence over both `driver` and `database`**,
 * so the connection *named* `sqlite` was a Postgres connection to `heliumdb` — the live
 * dev database, holding 3,079 users and 529 seller_agent_auctions.
 *
 * Every DB-backed test in this repository was running against it. Writes were rolled back
 * by `DatabaseTransactions`, so damage was bounded — but isolation was never real.
 *
 * **`TestCase::assertSafeTestDatabase()`, the guard written to prevent exactly this, passed
 * throughout.** It inspects `config('database.default')` and `config(...sqlite.database)`:
 * the two keys `url` silently overrides. A guard that reads the keys an override replaces
 * cannot detect the override. That is why every assertion below interrogates the resolved
 * connection object instead — its class, its driver, its database name.
 *
 * WHY A SKIP WOULD BE THE WORST POSSIBLE RESPONSE
 * -----------------------------------------------
 * This failure was already discovered once. `Phase1AuthorizationTest::requireIsolatedDb()`
 * checks `getDriverName() !== 'sqlite'` and calls `markTestSkipped()` with the message
 * *"Isolated SQLite test DB unavailable in this environment (pre-existing harness issue)."*
 * It fires, skipping 20 authorization tests on every run. The symptom was seen, worked
 * around, and never root-caused. **The skip is how a live-dev-database defect survived for
 * weeks.** If this test goes red, fix the environment, not the test.
 *
 * ⚠️  Do NOT repair this by editing the `database` config key while leaving `url` in place.
 *     `RefreshDatabase::usingInMemoryDatabase()` reads that same key; seeing `':memory:'` is
 *     the only reason it takes the non-destructive `migrate` branch rather than
 *     `migrate:fresh`. Neutralise `url`, or five test files start dropping every table in
 *     `heliumdb`. The fix lives in `tests/bootstrap.php` and `phpunit.xml`.
 *
 * NO QUERY IS ISSUED
 * ------------------
 * `ConnectionFactory::createSingleConnection()` builds its PDO via `createPdoResolver()`,
 * which returns a **closure** — resolving a connection opens no socket. `getDriverName()`,
 * `getDatabaseName()`, and `getConfig()` read parsed config off the constructed object, and
 * the object's *class* is selected from the post-override driver. So these assertions see
 * the connection the suite actually got, while making zero reads, zero writes, and zero DDL.
 *
 * This class deliberately does **not** use `DatabaseTransactions`: that trait is what
 * triggers `TestCase`'s `artisan migrate`, and this test runs no migrations.
 *
 * @see tests/bootstrap.php — the fix
 * @see docs/certification/TRACK-F-CHECKPOINT.md
 * @see \Tests\Feature\Security\Phase1AuthorizationTest::requireIsolatedDb() — the skip that buried this
 */
class TestDatabaseIdentityTest extends TestCase
{
    /** The dev database this suite must never reach. */
    private const FORBIDDEN_DATABASE = 'heliumdb';

    /** @test */
    public function the_resolved_connection_driver_is_sqlite(): void
    {
        $this->assertSame(
            'sqlite',
            DB::connection()->getDriverName(),
            'The resolved connection driver is not sqlite. Check that tests/bootstrap.php '
            . 'still blanks DATABASE_URL — config/database.php lets that URL override the '
            . 'declared driver. Do NOT skip this test; see the class docblock.',
        );
    }

    /** @test */
    public function the_resolved_connection_database_is_exactly_in_memory(): void
    {
        $this->assertSame(
            ':memory:',
            DB::connection()->getDatabaseName(),
            'The resolved connection is not pointed at an in-memory database.',
        );
    }

    /** @test */
    public function the_resolved_connection_is_not_postgresql(): void
    {
        $connection = DB::connection();

        // The connection's CLASS is chosen from the post-override driver, so this asserts
        // against the object the suite actually got — not the config it was promised.
        $this->assertNotInstanceOf(
            PostgresConnection::class,
            $connection,
            'The test suite resolved a PostgresConnection. Every DB-backed test is running '
            . 'against PostgreSQL, not an isolated SQLite database.',
        );

        $this->assertInstanceOf(
            SQLiteConnection::class,
            $connection,
            'Expected a SQLiteConnection. Got ' . get_class($connection) . '.',
        );
    }

    /** @test */
    public function the_resolved_connection_does_not_reach_heliumdb(): void
    {
        $connection = DB::connection();

        $this->assertNotSame(
            self::FORBIDDEN_DATABASE,
            $connection->getDatabaseName(),
            'The test suite is connected to the live dev database `heliumdb`.',
        );

        // Assert the CARRIER of the override is gone, not merely that its effects are absent
        // — otherwise a future env change silently re-arms this with nothing to catch it.
        $url = (string) $connection->getConfig('url');

        $this->assertSame(
            '',
            $url,
            'The sqlite connection still carries a `url` config key ('
            . preg_replace('#://[^@]*@#', '://***@', $url)
            . '). ConfigurationUrlParser gives it precedence over `driver` and `database`, '
            . 'which is the entire defect. The credential is masked above deliberately.',
        );

        $this->assertStringNotContainsString(
            self::FORBIDDEN_DATABASE,
            $url,
            'The sqlite connection URL still names the dev database.',
        );
    }

    /** @test */
    public function the_database_environment_is_neutralised_across_all_three_lookup_surfaces(): void
    {
        // phpdotenv's immutable repository will not overwrite a value already present in the
        // process environment, and PHPUnit's <env> only overwrites when force="true". Both
        // surfaces must agree, or a future reader of getenv() re-introduces the dev database.
        foreach (['DATABASE_URL' => '', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'] as $name => $expected) {
            $this->assertSame($expected, (string) getenv($name), "getenv('{$name}') is not neutralised.");
            $this->assertSame($expected, (string) ($_ENV[$name] ?? ''), "\$_ENV['{$name}'] is not neutralised.");
            $this->assertSame($expected, (string) ($_SERVER[$name] ?? ''), "\$_SERVER['{$name}'] is not neutralised.");
        }
    }
}
