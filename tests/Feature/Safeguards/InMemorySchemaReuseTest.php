<?php

namespace Tests\Feature\Safeguards;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The in-memory SQLite schema is built ONCE per PHPUnit process and reused thereafter.
 *
 * WHY THIS TEST EXISTS
 * --------------------
 * A SQLite `:memory:` database belongs to its PDO handle and dies with it. PHPUnit builds a
 * fresh Application, and therefore a fresh PDO, for every test. `TestCase` keeps the migrated
 * handle in a static registry and re-attaches it to each new Application — Laravel 9's
 * `RefreshDatabaseState::$inMemoryConnections` trick, backported to 8.83.
 *
 * Two things can silently go wrong, and both are expensive:
 *
 *   1. The handle is NOT reused, so every test re-migrates. Correct, but ~716ms x 2,711
 *      DatabaseTransactions tests ≈ half an hour of wall clock. Nothing fails; the suite
 *      just quietly rots.
 *   2. The handle IS reused but the schema is not actually shared — e.g. only `setPdo()` is
 *      called and `getReadPdo()` resolves a second, empty database — so SELECTs see nothing
 *      while INSERTs appear to work.
 *
 * Neither is visible from a passing test elsewhere. This file asserts both directly.
 *
 * It uses DatabaseTransactions on purpose: that is the trait the mechanism serves, and every
 * row this test writes is rolled back with it. RefreshDatabase is deliberately excluded from
 * handle sharing (see TestCase::reuseOrBuildInMemorySchema), so it is not exercised here.
 */
class InMemorySchemaReuseTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function migrations_run_exactly_once_per_phpunit_process(): void
    {
        // By the time any DatabaseTransactions test body executes, setUpTraits() has already
        // built or reused the schema. The counter can therefore never be 0 here, and must
        // never climb above 1 no matter how many tests preceded this one in the process.
        $this->assertSame(
            1,
            static::migrationRunCount(),
            'The in-memory schema was migrated ' . static::migrationRunCount() . ' times. '
            . 'Exactly one migration per PHPUnit process is expected — the preserved PDO handle '
            . 'is not being reused across Application instances.',
        );
    }

    /** @test */
    public function the_reused_handle_serves_both_reads_and_writes_from_the_same_database(): void
    {
        // Guards failure mode 2. If getReadPdo() resolved a second :memory: database, the
        // SELECT below would find nothing despite the INSERT having succeeded.
        $connection = DB::connection();

        $this->assertSame(
            $connection->getPdo(),
            $connection->getReadPdo(),
            'The read and write handles are different PDO objects, so they own different '
            . ':memory: databases. Reads would silently miss every write.',
        );
    }

    /** @test */
    public function the_schema_survived_the_application_that_created_it(): void
    {
        // The migration ran inside a previous test's Application, which has since been torn
        // down. If the handle had not been preserved, these tables would not exist.
        foreach (['migrations', 'users', 'bridge_properties'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table '{$table}' is missing; the schema did not survive.");
        }
    }

    /** @test */
    public function writes_are_visible_within_a_test_and_rolled_back_after_it(): void
    {
        // Proves the shared handle is a real, writable database — and that DatabaseTransactions
        // still wraps it, which is what makes sharing safe between tests. The companion
        // assertion (that this row is gone) is the next test's precondition, below.
        DB::table('migrations')->insert(['migration' => '__probe_reuse__', 'batch' => 9999]);

        $this->assertSame(1, DB::table('migrations')->where('migration', '__probe_reuse__')->count());
    }

    /** @test */
    public function the_previous_tests_write_did_not_leak_into_this_one(): void
    {
        // Sharing one PDO across tests is only safe because each test is wrapped in a
        // transaction that is rolled back. If DatabaseTransactions ever stopped covering this
        // connection, the shared database would accumulate state and tests would couple.
        //
        // PHPUnit runs methods in declaration order, so the insert above has already happened
        // and been rolled back by the time this executes.
        $this->assertSame(
            0,
            DB::table('migrations')->where('migration', '__probe_reuse__')->count(),
            'A row written by a previous test survived into this one. The shared in-memory '
            . 'handle is accumulating state — DatabaseTransactions is not rolling it back.',
        );
    }

    /** @test */
    public function the_shared_connection_is_still_sqlite_in_memory(): void
    {
        // Re-attaching a PDO must not be able to smuggle in a different database. The safety
        // guard runs every setUp, but pin it here too: this is the one place that deliberately
        // hands a connection a handle it did not open itself.
        $resolved = static::resolvedConnection();

        $this->assertSame('sqlite', $resolved['driver']);
        $this->assertSame(':memory:', $resolved['database']);
        $this->assertSame([], static::databaseSafetyViolations());

        $this->assertSame(
            'sqlite',
            DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME),
            'The preserved PDO handle is not a SQLite driver.',
        );
    }
}
