<?php

namespace Tests\Feature\Deployment;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `deploy:preflight` must count pending migrations across the application's own
 * migration directory, not only the paths packages register.
 *
 * WHAT WENT WRONG
 * ---------------
 * The command resolved its migration paths as:
 *
 *     $migrator->getMigrationFiles($migrator->paths() ?: [database_path('migrations')])
 *
 * `Migrator::paths()` returns ONLY the extra paths registered through
 * `loadMigrationsFrom()`. Laravel Sanctum registers one, so `paths()` is never
 * empty in this application — the `?:` fallback therefore never fired and
 * `database_path('migrations')` was excluded from the scan entirely. The command
 * counted "pending" across a single vendor migration and reported 0 forever,
 * including on the deploy where three application migrations were genuinely
 * pending. `migrate` and `migrate:status` were unaffected because Laravel's own
 * BaseCommand::getMigrationPaths() MERGES the two rather than treating one as a
 * fallback for the other.
 *
 * WHY THIS TEST IS NOT VACUOUS
 * ----------------------------
 * A test that merely asserts "preflight prints a number" passes against the bug —
 * that is exactly what the existing coverage did, and why this shipped. This test
 * fails against the old expression for the intended reason, because it:
 *
 *   1. proves `paths()` is non-empty, so the `?:` fallback is provably suppressed
 *      (without that, the old code would read database/migrations anyway and the
 *      test would pass for the wrong reason);
 *   2. registers only an EMPTY extra path, so the extra path can never supply the
 *      pending migration the assertion is looking for — the only way to see it is
 *      to scan database/migrations;
 *   3. makes a real APPLICATION migration pending, and never the vendor migration
 *      that the old expression could already see;
 *   4. pins the count to an exact number rather than ">= 0".
 *
 * The mutation is rolled back with the surrounding transaction.
 */
class DeployPreflightPendingMigrationsTest extends TestCase
{
    use DatabaseTransactions;

    /** The one migration the buggy expression could already see. Never use it as the fixture. */
    private const VENDOR_VISIBLE_MIGRATION = '2019_12_14_000001_create_personal_access_tokens_table';

    /** Directories created by registerEmptyExtraMigrationPath(), removed in tearDown. */
    private array $temporaryMigrationDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryMigrationDirs as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }

        $this->temporaryMigrationDirs = [];

        parent::tearDown();
    }

    /**
     * Register an extra migration path that contains no migrations.
     *
     * Empty on purpose. Its only job is to make `Migrator::paths()` truthy, which
     * is the precondition that suppresses the old `?:` fallback. If it contained a
     * migration file, the old code could count that file and the assertion below
     * would pass without database/migrations ever being read.
     */
    private function registerEmptyExtraMigrationPath(): string
    {
        $dir = sys_get_temp_dir() . '/preflight-empty-migrations-' . getmypid() . '-' . $this->getName();

        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $this->temporaryMigrationDirs[] = $dir;

        $this->app->make('migrator')->path($dir);

        return $dir;
    }

    /** The migration name to make pending: a real application migration, never the vendor one. */
    private function anApplicationMigrationName(): string
    {
        $names = collect(glob(database_path('migrations') . '/*_*.php'))
            ->map(static fn (string $file): string => str_replace('.php', '', basename($file)))
            ->reject(static fn (string $name): bool => $name === self::VENDOR_VISIBLE_MIGRATION)
            ->values();

        $this->assertNotEmpty($names, 'The application must ship migrations for this test to mean anything.');

        return (string) $names->last();
    }

    /** Parse the `pending migrs` figure out of the preflight report. */
    private function reportedPendingCount(): string
    {
        $this->withoutMockingConsoleOutput();

        Artisan::call('deploy:preflight');

        $output = Artisan::output();

        $this->assertMatchesRegularExpression(
            '/pending migrs\s+(.+)/',
            $output,
            'The preflight report must contain a pending-migration line'
        );

        preg_match('/pending migrs\s+(.+)/', $output, $m);

        return trim($m[1]);
    }

    /** What Laravel's own command would compute, using BaseCommand::getMigrationPaths() semantics. */
    private function frameworkEquivalentPendingCount(): int
    {
        $migrator = $this->app->make('migrator');

        $files = $migrator->getMigrationFiles(
            array_merge($migrator->paths(), [database_path('migrations')])
        );

        return count(array_diff(array_keys($files), $migrator->getRepository()->getRan()));
    }

    // ── the fixture's own preconditions ─────────────────────────────────────

    public function test_the_fixture_makes_the_buggy_fallback_unreachable(): void
    {
        $dir = $this->registerEmptyExtraMigrationPath();

        $paths = $this->app->make('migrator')->paths();

        // Asserted directly: if paths() were empty the old `?:` would fall back to
        // database_path('migrations') and the regression test below would pass
        // against the very bug it exists to catch.
        $this->assertNotEmpty(
            $paths,
            'Migrator::paths() must be non-empty, otherwise the old fallback would read the app path anyway'
        );

        $this->assertContains($dir, $paths, 'The extra path must actually be registered');

        $this->assertSame(
            [],
            glob($dir . '/*_*.php') ?: [],
            'The extra path must stay empty so it can never supply the pending migration'
        );
    }

    // ── the regression ──────────────────────────────────────────────────────

    public function test_preflight_counts_a_pending_application_migration(): void
    {
        $this->registerEmptyExtraMigrationPath();

        $this->assertSame(
            '0',
            $this->reportedPendingCount(),
            'Baseline: with the schema fully migrated the report must read 0'
        );

        $pending = $this->anApplicationMigrationName();

        // Make exactly one APPLICATION migration genuinely pending by forgetting it.
        $deleted = DB::table('migrations')->where('migration', $pending)->delete();

        $this->assertSame(1, $deleted, "Expected to un-record exactly one migration ({$pending})");

        // Against the old expression this reads '0': paths() is non-empty, so
        // database/migrations was never scanned and this migration is invisible.
        $this->assertSame(
            '1',
            $this->reportedPendingCount(),
            'preflight must see a pending migration in database/migrations even when a package registered its own path'
        );
    }

    public function test_preflight_agrees_with_the_frameworks_own_path_resolution(): void
    {
        $this->registerEmptyExtraMigrationPath();

        DB::table('migrations')->where('migration', $this->anApplicationMigrationName())->delete();

        $this->assertSame(
            (string) $this->frameworkEquivalentPendingCount(),
            $this->reportedPendingCount(),
            'preflight must agree with array_merge(migrator->paths(), [database_path(migrations)])'
        );
    }

    public function test_package_migration_paths_are_still_scanned(): void
    {
        // The fix must ADD the application path, not replace the registered ones.
        $migrator = $this->app->make('migrator');

        $this->assertNotEmpty($migrator->paths());

        DB::table('migrations')->where('migration', self::VENDOR_VISIBLE_MIGRATION)->delete();

        $this->assertSame(
            '1',
            $this->reportedPendingCount(),
            'A pending migration in a package-registered path must still be counted'
        );
    }
}
