<?php

namespace Tests\Feature\Safeguards;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
use Tests\TestCase;

/**
 * Proves `TestCase::assertSafeTestDatabase()` is NON-VACUOUS — that it actually rejects the
 * configurations it claims to reject.
 *
 * WHY THIS TEST HAD TO EXIST
 * --------------------------
 * The previous guard read `config('database.default')` and
 * `config("database.connections.{$default}.database")` — values `setUpTraits()` had assigned
 * a few lines earlier. It passed unconditionally. It passed while the suite was connected to
 * PostgreSQL against `heliumdb`, the live dev database. A green guard proved only that PHP
 * can read back an array it just wrote.
 *
 * That is the failure mode this file exists to prevent recurring. A safety guard nobody has
 * ever *seen fail* is indistinguishable from `return;`. Each test below poisons the database
 * configuration and proves the guard rejects it — including the exact historical poisoning,
 * where every config key the old guard inspected read correctly.
 *
 * NO CONNECTION IS EVER OPENED
 * ----------------------------
 * `ConnectionFactory::createSingleConnection()` builds its PDO from `createPdoResolver()`,
 * which returns a closure. Resolving a connection — even one addressed at `heliumdb` — opens
 * no socket. The guard reads only the resolved object's class, driver, database name, and
 * parsed config array. No test here issues a query, a write, or DDL. The poisoned Postgres
 * URL below is never dialled.
 *
 * This class does not use `DatabaseTransactions`, so it triggers no `migrate`.
 *
 * @see \Tests\Feature\Safeguards\TestDatabaseIdentityTest — asserts the real connection is clean
 * @see docs/certification/TRACK-F-CHECKPOINT.md
 */
class DatabaseGuardPoisoningTest extends TestCase
{
    /** The credentialed URL this host injects, reproduced verbatim as the poison. */
    private const HELIUMDB_URL = 'postgresql://postgres:password@helium/heliumdb?sslmode=disable';

    /**
     * Repoint the default connection at a poisoned config and force re-resolution.
     * `DB::purge()` drops the cached connection object; it does not disconnect a socket,
     * because none was ever opened.
     */
    private function poison(string $name, array $config): void
    {
        config(["database.connections.{$name}" => $config, 'database.default' => $name]);

        DB::purge($name);
    }

    /** Run the guard and return the failure message, or fail this test if the guard passed. */
    private function captureGuardRejection(string $because): string
    {
        try {
            $this->assertSafeTestDatabase();
        } catch (AssertionFailedError $e) {
            return $e->getMessage();
        }

        $this->fail("assertSafeTestDatabase() accepted a poisoned configuration: {$because}");
    }

    /** @test */
    public function the_guard_accepts_the_real_isolated_connection(): void
    {
        // Positive control. Without this, every rejection below could be a guard that fails
        // on everything — equally useless, in the opposite direction.
        $this->assertSame([], $this->databaseIsolationViolations());
        $this->assertTrue($this->resolvedConnectionIsIsolatedSqlite());

        $this->assertSafeTestDatabase(); // must not throw
    }

    /** @test */
    public function the_guard_rejects_the_exact_historical_poisoning_that_the_old_guard_passed(): void
    {
        // ─────────────────────────────────────────────────────────────────────────────
        // THE LOAD-BEARING TEST OF THIS FILE.
        //
        // Note what is true here: the connection is NAMED `sqlite`, its `driver` key says
        // `sqlite`, and its `database` key says `:memory:`. Those are precisely the values
        // the old guard inspected, and it accepted them. But `url` overrides both, so the
        // resolved connection is PostgreSQL against the live dev database.
        //
        // This is not a hypothetical. It is the state of this repository until c14c94bb8.
        // ─────────────────────────────────────────────────────────────────────────────
        $this->poison('sqlite', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'url'      => self::HELIUMDB_URL,
            'prefix'   => '',
        ]);

        // Prove the trap is real before proving the guard springs it: the config still reads
        // clean, and the resolved connection is nonetheless Postgres on heliumdb.
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('heliumdb', DB::connection()->getDatabaseName());

        $message = $this->captureGuardRejection('config keys read sqlite/:memory: but url overrides them to heliumdb');

        $this->assertStringContainsString('SAFETY ABORT', $message);
        $this->assertStringContainsString('PostgresConnection', $message);
        $this->assertStringContainsString('heliumdb', $message);
    }

    /** @test */
    public function the_guard_never_leaks_the_database_password_into_the_failure_message(): void
    {
        // Guard failures land in CI logs. The URL is worth printing; the credential is not.
        $this->poison('sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'url' => self::HELIUMDB_URL, 'prefix' => '',
        ]);

        $message = $this->captureGuardRejection('poisoned url');

        $this->assertStringNotContainsString('password', $message);
        $this->assertStringContainsString('://***@', $message);
    }

    /** @test */
    public function the_guard_rejects_a_postgresql_connection(): void
    {
        $this->poison('pgsql', [
            'driver' => 'pgsql', 'host' => 'helium', 'port' => '5432',
            'database' => 'heliumdb', 'username' => 'postgres', 'password' => 'password', 'prefix' => '',
        ]);

        $message = $this->captureGuardRejection('a plain PostgreSQL connection');

        $this->assertStringContainsString('PostgresConnection', $message);
    }

    /** @test */
    public function the_guard_rejects_a_mysql_connection(): void
    {
        $this->poison('mysql', [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3306',
            'database' => 'byo', 'username' => 'root', 'password' => '', 'prefix' => '',
        ]);

        $message = $this->captureGuardRejection('a plain MySQL connection');

        $this->assertStringContainsString('MySqlConnection', $message);
    }

    /** @test */
    public function the_guard_rejects_a_database_whose_name_merely_contains_the_word_test(): void
    {
        // The removed escape hatch, pinned. `str_contains($database, 'test')` accepted this —
        // and `contest`, `latest_backup`, and `heliumdb_test` alike. A shared MySQL server is
        // not isolated no matter what its schema is called, and its DDL survives rollback.
        $this->poison('mysql', [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3306',
            'database' => 'byo_test', 'username' => 'root', 'password' => '', 'prefix' => '',
        ]);

        $message = $this->captureGuardRejection("database named 'byo_test' — the old escape hatch");

        $this->assertStringContainsString('MySqlConnection', $message);
        $this->assertFalse($this->resolvedConnectionIsIsolatedSqlite());
    }

    /** @test */
    public function the_guard_rejects_a_file_backed_sqlite_database(): void
    {
        // Right driver, wrong database. A file persists between runs; `:memory:` cannot.
        $this->poison('sqlite', [
            'driver' => 'sqlite', 'database' => database_path('database.sqlite'), 'prefix' => '',
        ]);

        $message = $this->captureGuardRejection('file-backed sqlite');

        $this->assertStringContainsString("expected ':memory:'", $message);
    }

    /** @test */
    public function the_guard_rejects_an_in_memory_sqlite_connection_that_carries_a_host(): void
    {
        // An isolated SQLite database has no server. A host means shared, persistent state,
        // and is a signal that some other config surface is still in play.
        $this->poison('sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'host' => 'helium', 'prefix' => '',
        ]);

        $message = $this->captureGuardRejection('sqlite with a host');

        $this->assertStringContainsString('has a host', $message);
    }

    /** @test */
    public function the_guard_rejects_heliumdb_smuggled_through_any_config_key(): void
    {
        // Belt and braces: the dev database's name must not survive anywhere in the resolved
        // config, whatever key carries it.
        $this->poison('sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'heliumdb_',
        ]);

        $message = $this->captureGuardRejection('heliumdb smuggled through the prefix key');

        $this->assertStringContainsString('names the live dev database', $message);
    }

    /** @test */
    public function the_migrate_gate_uses_the_resolved_connection_and_refuses_a_poisoned_one(): void
    {
        // `migrate --force` emits DDL, and DDL is the one thing DatabaseTransactions cannot
        // roll back. Its gate must consult the resolved connection, not the config values
        // setUpTraits() just assigned to itself.
        $this->assertTrue($this->resolvedConnectionIsIsolatedSqlite(), 'Precondition: the real connection is isolated.');

        $this->poison('sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'url' => self::HELIUMDB_URL, 'prefix' => '',
        ]);

        $this->assertFalse(
            $this->resolvedConnectionIsIsolatedSqlite(),
            'The migrate gate would have run DDL against heliumdb: it accepted a connection '
            . 'whose config keys read sqlite/:memory: while resolving to PostgreSQL.',
        );
    }
}
