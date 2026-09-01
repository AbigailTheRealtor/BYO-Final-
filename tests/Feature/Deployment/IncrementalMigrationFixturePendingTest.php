<?php

namespace Tests\Feature\Deployment;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `migrate:incremental-fixture verify` is the assertion half of the
 * incremental-migration CI gate, and its FIRST check is the deployment's own
 * success condition: nothing may remain pending after the workflow migrates
 * forward from the previous release.
 *
 * WHY THIS TEST HAD TO BE ADDED
 * -----------------------------
 * That check was vacuous. It resolved its migration paths as:
 *
 *     $migrator->getMigrationFiles($migrator->paths() ?: [database_path('migrations')])
 *
 * `Migrator::paths()` contains ONLY directories registered through
 * `loadMigrationsFrom()` — never `database/migrations`. Laravel Sanctum registers
 * one, so `paths()` is never empty in this application, the `?:` fallback never
 * fired, and the scan covered exactly one vendor migration. That migration is
 * already applied by the time `verify` runs, so the check printed
 * "✓ zero pending migrations" every time, whatever the application's schema
 * actually looked like.
 *
 * The consequence is worse here than in a report. This is a CI GATE. A green
 * incremental-migration job was being read as evidence that a release's
 * migrations apply cleanly on top of the previous release — and the part of the
 * job that certified "and none are left pending" could not fail. The gate would
 * have passed a release that shipped an unapplied schema, which is precisely the
 * failure it was built to prevent.
 *
 * WHY THIS TEST IS NOT VACUOUS
 * ----------------------------
 * Asserting "verify passes on a current schema" would pass against the bug — the
 * bug's whole nature is that it always passes. So the test below:
 *
 *   1. proves `Migrator::paths()` is non-empty, so the `?:` fallback is provably
 *      suppressed and the old code could not have read database/migrations by
 *      accident;
 *   2. registers only an EMPTY extra path, so the registered paths can never
 *      supply the pending migration the assertion looks for;
 *   3. makes a real APPLICATION migration pending — never the vendor migration a
 *      package-only scan could already see;
 *   4. requires `verify` to FAIL, and to fail naming the pending migration.
 *
 * Against the old expression, step 4 does not happen: verify reports zero pending
 * and exits SUCCESS.
 */
class IncrementalMigrationFixturePendingTest extends TestCase
{
    use DatabaseTransactions;

    /** The one migration a package-only scan can already see. Never use it as the fixture. */
    private const VENDOR_VISIBLE_MIGRATION = '2019_12_14_000001_create_personal_access_tokens_table';

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

    /** An extra migration path with nothing in it — see point 2 in the class docblock. */
    private function registerEmptyExtraMigrationPath(): string
    {
        $dir = sys_get_temp_dir() . '/incremental-fixture-empty-' . getmypid() . '-' . $this->getName();

        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $this->temporaryMigrationDirs[] = $dir;

        $this->app->make('migrator')->path($dir);

        return $dir;
    }

    private function anApplicationMigrationName(): string
    {
        $names = collect(glob(database_path('migrations') . '/*_*.php'))
            ->map(static fn (string $file): string => str_replace('.php', '', basename($file)))
            ->reject(static fn (string $name): bool => $name === self::VENDOR_VISIBLE_MIGRATION)
            ->values();

        $this->assertNotEmpty($names, 'The application must ship migrations for this test to mean anything.');

        return (string) $names->last();
    }

    /** @return array{code: int, output: string} */
    private function fixture(string $action): array
    {
        $this->withoutMockingConsoleOutput();

        $code = Artisan::call('migrate:incremental-fixture', ['action' => $action]);

        return ['code' => $code, 'output' => Artisan::output()];
    }

    // ── preconditions ───────────────────────────────────────────────────────

    public function test_the_fixture_makes_the_old_fallback_unreachable(): void
    {
        $dir   = $this->registerEmptyExtraMigrationPath();
        $paths = $this->app->make('migrator')->paths();

        $this->assertNotEmpty(
            $paths,
            'Migrator::paths() must be non-empty, otherwise the old `?:` would read the app path anyway'
        );
        $this->assertContains($dir, $paths);
        $this->assertSame(
            [],
            glob($dir . '/*_*.php') ?: [],
            'The extra path must stay empty so it can never supply the pending migration'
        );
    }

    // ── baseline ────────────────────────────────────────────────────────────

    public function test_verify_passes_on_a_fully_migrated_schema(): void
    {
        $this->registerEmptyExtraMigrationPath();

        $this->assertSame(0, $this->fixture('seed')['code'], 'seed must succeed against the current schema');

        $result = $this->fixture('verify');

        $this->assertSame(0, $result['code'], 'verify must pass when nothing is pending');
        $this->assertStringContainsString('zero pending migrations', $result['output']);
    }

    // ── the regression ──────────────────────────────────────────────────────

    /**
     * Against `$migrator->paths() ?: [database_path('migrations')]` this test fails:
     * verify prints "✓ zero pending migrations" and exits 0 while an application
     * migration is genuinely unapplied.
     */
    public function test_verify_fails_when_an_application_migration_is_pending(): void
    {
        $this->registerEmptyExtraMigrationPath();

        $this->assertSame(0, $this->fixture('seed')['code']);

        $pending = $this->anApplicationMigrationName();

        $deleted = DB::table('migrations')->where('migration', $pending)->delete();
        $this->assertSame(1, $deleted, "Expected to un-record exactly one migration ({$pending})");

        $result = $this->fixture('verify');

        $this->assertNotSame(
            0,
            $result['code'],
            'verify must FAIL while an application migration is pending — this is the CI gate\'s success condition'
        );

        $this->assertStringContainsString('Migrations still pending', $result['output']);
        $this->assertStringContainsString(
            $pending,
            $result['output'],
            'The failure must name the migration it found pending'
        );
        $this->assertStringNotContainsString(
            'zero pending migrations',
            $result['output'],
            'verify must not claim zero pending while a migration is unapplied'
        );
    }

    /** The fix must ADD the application path, not replace the registered ones. */
    public function test_verify_still_sees_a_pending_migration_in_a_package_path(): void
    {
        $this->assertNotEmpty($this->app->make('migrator')->paths());

        $this->assertSame(0, $this->fixture('seed')['code']);

        DB::table('migrations')->where('migration', self::VENDOR_VISIBLE_MIGRATION)->delete();

        $result = $this->fixture('verify');

        $this->assertNotSame(0, $result['code']);
        $this->assertStringContainsString(self::VENDOR_VISIBLE_MIGRATION, $result['output']);
    }
}
