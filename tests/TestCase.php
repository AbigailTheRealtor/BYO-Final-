<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

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
