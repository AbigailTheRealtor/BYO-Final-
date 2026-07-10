<?php

namespace Tests\Feature\Safeguards;

use PHPUnit\Framework\AssertionFailedError;
use Tests\TestCase;

/**
 * Prove the SAFETY ABORT itself fires — not merely that the enumerator behind it can count.
 *
 * WHY THIS EXISTS, SEPARATELY FROM TestDatabaseIdentityTest
 * --------------------------------------------------------
 * TestDatabaseIdentityTest exercises `databaseSafetyViolations()`: it poisons the config and
 * asserts the returned list is non-empty. That proves the *enumerator* is not vacuous. It does
 * NOT prove that `assertSafeTestDatabase()` — the method `setUpTraits()` actually calls to abort
 * the run — consumes that list and fails the test. Those are two different guarantees, and only
 * the second one stands between the suite and irreversible DDL on the shared development
 * database. In normal operation `assertSafeTestDatabase()` is only ever reached on already-safe
 * config, so its failure branch has no coverage at all: it has never been observed to fail.
 *
 * This branch is about to add the `jobs` table — the first migration this codebase ships — and
 * `DatabaseTransactions` cannot roll DDL back. So before that DDL lands, the abort path is
 * exercised directly: poison a surface, invoke the guard, and require it to throw. That is why
 * `assertSafeTestDatabase()` was widened from private to protected (tests/TestCase.php).
 *
 * DISCIPLINE (inherited from TestDatabaseIdentityTest)
 * ---------------------------------------------------
 * Uses NEITHER DatabaseTransactions NOR RefreshDatabase, opens no connection, and issues no
 * query or DDL — those traits are the very mechanisms this guard exists to make safe, so leaning
 * on them would only let the check run after the unsafe thing had already happened. Every
 * poisoning is reverted in a `finally`, and each helper re-asserts the guard is clean afterwards,
 * so no vector can bleed into a later test sharing the PHP process. `$_SERVER` is process-global
 * and is restored explicitly; `config()` lives on the per-test Application but is restored anyway.
 *
 * @see \Tests\TestCase::assertSafeTestDatabase()
 * @see \Tests\Feature\Safeguards\TestDatabaseIdentityTest
 */
class DatabaseGuardPoisoningTest extends TestCase
{
    /** The DSN that historically turned the connection named `sqlite` into pgsql/heliumdb. */
    private const POISON_DSN = 'postgresql://user:pw@helium/heliumdb?sslmode=disable';

    /** @test */
    public function the_guard_passes_silently_on_the_real_resolved_connection(): void
    {
        // The baseline the whole suite depends on: on the real database the abort must NOT fire.
        // This is the same call setUpTraits() already made before this body ran; asserting it
        // returns cleanly turns "never seen to fail" into a checked property. If the resolved
        // connection were unsafe this line would throw AssertionFailedError and fail the test.
        $this->assertSafeTestDatabase();

        // A counted companion assertion so the guard's silence cannot masquerade as a risky,
        // assertion-free test.
        $this->assertSame(
            [],
            static::databaseSafetyViolations(),
            'The real test database reports safety violations.',
        );
    }

    /** @test */
    public function it_aborts_when_the_sqlite_connection_is_handed_a_postgres_dsn(): void
    {
        $original = config('database.connections.sqlite.url');

        $caught = $this->captureGuardAbort(
            fn () => config(['database.connections.sqlite.url' => self::POISON_DSN]),
            fn () => config(['database.connections.sqlite.url' => $original]),
        );

        // The exact trap from the original incident: a `url` overrides the connection's driver
        // and database at construction time.
        $this->assertGuardAborted($caught, 'sqlite url = postgres DSN', ['safety abort', 'heliumdb', 'pgsql', 'url']);
    }

    /** @test */
    public function it_aborts_when_a_live_database_url_leaks_into_the_process_environment(): void
    {
        // Layer 1: config() is left exactly as the bootstrap produced it; only the process
        // environment is poisoned. The guard must catch a bootstrap regression on its own,
        // without config() vouching for it.
        $original = $_SERVER['DATABASE_URL'] ?? null;

        $caught = $this->captureGuardAbort(
            fn () => $_SERVER['DATABASE_URL'] = self::POISON_DSN,
            function () use ($original): void {
                if ($original === null) {
                    unset($_SERVER['DATABASE_URL']);
                } else {
                    $_SERVER['DATABASE_URL'] = $original;
                }
            },
        );

        $this->assertGuardAborted($caught, 'live DATABASE_URL in $_SERVER', ['safety abort', 'did not neutralise database_url']);
    }

    /** @test */
    public function it_aborts_on_a_shared_database_name_with_no_test_suffix_escape_hatch(): void
    {
        // `heliumdb_test` would have satisfied the removed `str_contains($database, 'test')`
        // rule. Proven here at the abort layer, not just the enumerator layer: a shared remote
        // database is not made safe by its name.
        $original = config('database.connections.sqlite.database');

        $caught = $this->captureGuardAbort(
            fn () => config(['database.connections.sqlite.database' => 'heliumdb_test']),
            fn () => config(['database.connections.sqlite.database' => $original]),
        );

        $this->assertGuardAborted($caught, "database = 'heliumdb_test'", ['safety abort', 'heliumdb']);
    }

    /** @test */
    public function it_aborts_when_the_driver_is_flipped_to_a_networked_engine(): void
    {
        // A poisoning with no `url` and no heliumdb reference: purely the resolved driver. Proves
        // the abort does not depend on the DSN-shaped vectors above to notice a networked engine.
        $original = config('database.connections.sqlite.driver');

        $caught = $this->captureGuardAbort(
            fn () => config(['database.connections.sqlite.driver' => 'pgsql']),
            fn () => config(['database.connections.sqlite.driver' => $original]),
        );

        $this->assertGuardAborted($caught, 'driver = pgsql', ['safety abort', 'networked database engine']);
    }

    /** @test */
    public function a_poisoned_connection_fails_the_test_rather_than_erroring_it(): void
    {
        // The distinction is not cosmetic. `$this->fail()` throws AssertionFailedError, which
        // PHPUnit renders as a FAILURE (F). A RuntimeException would surface as an ERROR (E) and
        // is treated differently by risky-test and stop-on-failure configurations. The seatbelt
        // must fail the run, in the loudest category available.
        $original = config('database.connections.sqlite.url');

        $caught = $this->captureGuardAbort(
            fn () => config(['database.connections.sqlite.url' => self::POISON_DSN]),
            fn () => config(['database.connections.sqlite.url' => $original]),
        );

        $this->assertInstanceOf(
            AssertionFailedError::class,
            $caught,
            'The abort did not use the fail-the-test path.',
        );
    }

    /**
     * Poison a surface, invoke the guard, revert, and return whatever the guard threw (null if
     * it did not abort). The revert runs in `finally`, so a poisoning cannot outlive this call
     * even when the guard throws; the post-condition asserts the guard is clean again.
     */
    private function captureGuardAbort(callable $poison, callable $restore): ?AssertionFailedError
    {
        $poison();

        $caught = null;

        try {
            $this->assertSafeTestDatabase();
        } catch (AssertionFailedError $e) {
            $caught = $e;
        } finally {
            $restore();
        }

        // Whatever happened above, the restore has run: the guard must report clean again so
        // this vector cannot bleed into the next test sharing the process.
        $this->assertSame(
            [],
            static::databaseSafetyViolations(),
            'A poisoning vector leaked past its restore; the guard still reports violations.',
        );

        return $caught;
    }

    /**
     * Assert the guard aborted (threw) and that its message names every expected reason, so a
     * future edit that keeps the abort but loses a violation from the report is still caught.
     *
     * @param list<string> $needles
     */
    private function assertGuardAborted(?AssertionFailedError $caught, string $label, array $needles): void
    {
        $this->assertNotNull($caught, "assertSafeTestDatabase() did not abort under: {$label}.");

        $message = strtolower($caught->getMessage());

        foreach ($needles as $needle) {
            $this->assertStringContainsString(
                strtolower($needle),
                $message,
                "The abort message under '{$label}' did not mention '{$needle}'.",
            );
        }
    }
}
