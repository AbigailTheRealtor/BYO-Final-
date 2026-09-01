<?php

namespace Tests\Feature\Deployment;

use App\Console\Commands\DeployMigrationsPending;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * `deploy:migrations-pending` is the machine-readable answer to "is this code's
 * migration set applied?". Everything below exists because a caller is going to
 * write `php artisan deploy:migrations-pending || <refuse to serve>` and has to
 * be right.
 *
 * WHAT THESE TESTS ARE GUARDING AGAINST
 * -------------------------------------
 * Two distinct failures, both of which have already happened in this repository:
 *
 *   1. A readiness check that reports zero pending while application migrations
 *      are genuinely pending, because it resolved its migration paths with
 *      `$migrator->paths() ?: [database_path('migrations')]`. Sanctum registers a
 *      path, so the fallback never fires and database/migrations is never
 *      scanned. See test_detects_a_pending_application_migration_despite_a_registered_package_path.
 *
 *   2. A gate that exits zero when it could not actually determine the answer.
 *      Every undetermined branch is exercised below and every one must be
 *      non-zero — "I don't know" must never be served as "ready".
 *
 * A note on why several tests assert an EXACT exit code rather than "non-zero":
 * the command separates pending (1) from undetermined (2) so a caller can tell
 * "migrate and retry" from "the environment is broken". Asserting the exact code
 * is what keeps that distinction real.
 */
class DeployMigrationsPendingTest extends TestCase
{
    use DatabaseTransactions;

    /** The single migration a package-only path scan can already see. Never use it as the app fixture. */
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

        Mockery::close();

        parent::tearDown();
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    /**
     * Register an extra migration path containing no migrations.
     *
     * Empty on purpose, for the same reason as in DeployPreflightPendingMigrationsTest:
     * its only job is to make `Migrator::paths()` truthy, which is the precondition
     * that suppresses the old `?:` fallback. If it held a migration file, a
     * package-only scan could count that file and the assertions below would pass
     * without database/migrations ever being read.
     */
    private function registerEmptyExtraMigrationPath(): string
    {
        $dir = sys_get_temp_dir() . '/migrations-pending-empty-' . getmypid() . '-' . $this->getName();

        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $this->temporaryMigrationDirs[] = $dir;

        $this->app->make('migrator')->path($dir);

        return $dir;
    }

    /** A real application migration name — never the one a package also ships. */
    private function anApplicationMigrationName(): string
    {
        $names = collect(glob(database_path('migrations') . '/*_*.php'))
            ->map(static fn (string $file): string => str_replace('.php', '', basename($file)))
            ->reject(static fn (string $name): bool => $name === self::VENDOR_VISIBLE_MIGRATION)
            ->values();

        $this->assertNotEmpty($names, 'The application must ship migrations for this test to mean anything.');

        return (string) $names->last();
    }

    /**
     * Run the command, returning its exit code and its stdout.
     *
     * @return array{code: int, output: string}
     */
    private function invokeCommand(array $parameters = []): array
    {
        $this->withoutMockingConsoleOutput();

        $code = Artisan::call('deploy:migrations-pending', $parameters);

        return ['code' => $code, 'output' => Artisan::output()];
    }

    /**
     * Run the command without going through Artisan's command registry.
     *
     * Needed for exactly one branch. `migrator_unavailable` requires the container
     * to hold something that is not a Migrator, and Laravel's own MigrateCommand
     * takes a `Migrator` as a constructor type-hint — so booting the Artisan
     * application with that binding in place throws inside the FRAMEWORK before
     * this command is ever reached. Instantiating directly keeps the test about
     * the branch it names instead of about console bootstrapping order.
     *
     * @return array{code: int, output: string}
     */
    private function invokeCommandDirectly(): array
    {
        $command = new DeployMigrationsPending();
        $command->setLaravel($this->app);

        $output = new BufferedOutput();
        $code   = $command->run(new ArrayInput([]), $output);

        return ['code' => $code, 'output' => $output->fetch()];
    }

    /** Replace the container's migrator with a mock, so failure branches can be reached without breaking the schema. */
    private function bindMigratorMock(): Mockery\MockInterface
    {
        $mock = Mockery::mock(Migrator::class);

        $this->app->instance('migrator', $mock);

        return $mock;
    }

    /** Everything about the database that a write would change: schema plus the migrations ledger. */
    private function databaseFingerprint(): array
    {
        $tables = DB::select("select name, sql from sqlite_master order by name");

        return [
            'schema'     => json_encode($tables),
            'migrations' => json_encode(
                DB::table('migrations')->orderBy('id')->get()->toArray()
            ),
        ];
    }

    // ── 1. the ready case ───────────────────────────────────────────────────

    public function test_exits_zero_and_reports_zero_when_fully_migrated(): void
    {
        $result = $this->invokeCommand();

        $this->assertSame(
            DeployMigrationsPending::EXIT_READY,
            $result['code'],
            'A fully migrated schema must exit 0 — this is the only condition that may.'
        );

        $this->assertSame('pending=0', trim($result['output']));
    }

    // ── 2. a genuinely pending application migration ────────────────────────

    public function test_exits_non_zero_when_an_application_migration_is_pending(): void
    {
        $pending = $this->anApplicationMigrationName();

        $deleted = DB::table('migrations')->where('migration', $pending)->delete();
        $this->assertSame(1, $deleted, "Expected to un-record exactly one migration ({$pending})");

        $result = $this->invokeCommand();

        $this->assertSame(
            DeployMigrationsPending::EXIT_PENDING,
            $result['code'],
            'A pending migration must fail the gate.'
        );

        $this->assertNotSame(0, $result['code'], 'Fail-closed: pending is never exit 0.');
        $this->assertSame('pending=1', trim($result['output']));
    }

    // ── 3. the anti-vacuity test: registered package path + pending app migration ──

    /**
     * THE REGRESSION THIS COMMAND EXISTS FOR.
     *
     * With `$migrator->paths() ?: [database_path('migrations')]` this test fails,
     * for the intended reason: paths() is non-empty (proved below), so the scan
     * covers only the registered paths, the pending application migration is
     * invisible, and the command reports pending=0 and exits 0.
     */
    public function test_detects_a_pending_application_migration_despite_a_registered_package_path(): void
    {
        $dir      = $this->registerEmptyExtraMigrationPath();
        $migrator = $this->app->make('migrator');

        // Precondition, asserted rather than assumed: without a non-empty paths()
        // the old `?:` would read database/migrations anyway and this test would
        // pass against the very bug it exists to catch.
        $this->assertNotEmpty(
            $migrator->paths(),
            'Migrator::paths() must be non-empty or this test proves nothing'
        );
        $this->assertContains($dir, $migrator->paths(), 'The extra path must actually be registered');
        $this->assertSame(
            [],
            glob($dir . '/*_*.php') ?: [],
            'The extra path must stay empty so it can never supply the pending migration'
        );

        // Baseline with the extra path registered: still ready.
        $this->assertSame(DeployMigrationsPending::EXIT_READY, $this->invokeCommand()['code']);

        DB::table('migrations')->where('migration', $this->anApplicationMigrationName())->delete();

        $result = $this->invokeCommand();

        $this->assertSame(
            DeployMigrationsPending::EXIT_PENDING,
            $result['code'],
            'A pending migration in database/migrations must be seen even when a package registered its own path'
        );
        $this->assertSame('pending=1', trim($result['output']));
    }

    public function test_a_pending_package_migration_is_still_counted(): void
    {
        // The fix must ADD the application path, not replace the registered ones.
        $this->assertNotEmpty($this->app->make('migrator')->paths());

        DB::table('migrations')->where('migration', self::VENDOR_VISIBLE_MIGRATION)->delete();

        $result = $this->invokeCommand();

        $this->assertSame(DeployMigrationsPending::EXIT_PENDING, $result['code']);
        $this->assertSame('pending=1', trim($result['output']));
    }

    public function test_agrees_with_the_frameworks_own_path_resolution(): void
    {
        $this->registerEmptyExtraMigrationPath();

        DB::table('migrations')->where('migration', $this->anApplicationMigrationName())->delete();

        $migrator = $this->app->make('migrator');

        $frameworkCount = count(array_diff(
            array_keys($migrator->getMigrationFiles(
                array_merge($migrator->paths(), [database_path('migrations')])
            )),
            $migrator->getRepository()->getRan()
        ));

        $this->assertSame(
            'pending=' . $frameworkCount,
            trim($this->invokeCommand()['output']),
            'The count must equal array_merge(migrator->paths(), [database_path(migrations)])'
        );
    }

    // ── 4. repository missing ───────────────────────────────────────────────

    public function test_exits_non_zero_when_the_migrations_repository_is_missing(): void
    {
        $this->bindMigratorMock()
            ->shouldReceive('repositoryExists')->once()->andReturn(false);

        $result = $this->invokeCommand();

        $this->assertSame(
            DeployMigrationsPending::EXIT_UNDETERMINED,
            $result['code'],
            'An absent migrations table is undetermined, never ready.'
        );
        $this->assertSame('error=repository_missing', trim($result['output']));
    }

    // ── 5. every throwing branch ────────────────────────────────────────────

    public function test_exits_non_zero_when_the_repository_check_throws(): void
    {
        $this->bindMigratorMock()
            ->shouldReceive('repositoryExists')->once()->andThrow(new RuntimeException('boom'));

        $result = $this->invokeCommand();

        $this->assertSame(DeployMigrationsPending::EXIT_UNDETERMINED, $result['code']);
        $this->assertSame('error=repository_unreadable', trim($result['output']));
    }

    public function test_exits_non_zero_when_reading_the_ran_migrations_throws(): void
    {
        $migrator = $this->bindMigratorMock();
        $migrator->shouldReceive('repositoryExists')->once()->andReturn(true);
        $migrator->shouldReceive('getRepository')->once()->andThrow(new RuntimeException('boom'));

        $result = $this->invokeCommand();

        $this->assertSame(DeployMigrationsPending::EXIT_UNDETERMINED, $result['code']);
        $this->assertSame('error=repository_read_failed', trim($result['output']));
    }

    public function test_exits_non_zero_when_migration_discovery_throws(): void
    {
        $repository = Mockery::mock();
        $repository->shouldReceive('getRan')->once()->andReturn([]);

        $migrator = $this->bindMigratorMock();
        $migrator->shouldReceive('repositoryExists')->once()->andReturn(true);
        $migrator->shouldReceive('getRepository')->once()->andReturn($repository);
        $migrator->shouldReceive('paths')->andReturn([]);
        $migrator->shouldReceive('getMigrationFiles')->once()->andThrow(new RuntimeException('boom'));

        $result = $this->invokeCommand();

        $this->assertSame(DeployMigrationsPending::EXIT_UNDETERMINED, $result['code']);
        $this->assertSame('error=discovery_failed', trim($result['output']));
    }

    public function test_exits_non_zero_when_the_container_does_not_resolve_a_migrator(): void
    {
        $this->app->instance('migrator', new \stdClass());

        $result = $this->invokeCommandDirectly();

        $this->assertSame(DeployMigrationsPending::EXIT_UNDETERMINED, $result['code']);
        $this->assertSame('error=migrator_unavailable', trim($result['output']));
    }

    /**
     * Discovering nothing is not the same as nothing being pending.
     *
     * `pending=0` over an empty file set is arithmetically true and exactly what
     * the path-resolution bug produced. It must be refused, not reported.
     */
    public function test_exits_non_zero_when_discovery_finds_no_migrations_at_all(): void
    {
        $repository = Mockery::mock();
        $repository->shouldReceive('getRan')->once()->andReturn([]);

        $migrator = $this->bindMigratorMock();
        $migrator->shouldReceive('repositoryExists')->once()->andReturn(true);
        $migrator->shouldReceive('getRepository')->once()->andReturn($repository);
        $migrator->shouldReceive('paths')->andReturn([]);
        $migrator->shouldReceive('getMigrationFiles')->once()->andReturn([]);

        $result = $this->invokeCommand();

        $this->assertSame(DeployMigrationsPending::EXIT_UNDETERMINED, $result['code']);
        $this->assertSame('error=no_migrations_discovered', trim($result['output']));
    }

    // ── 6. read-only ────────────────────────────────────────────────────────

    public function test_issues_only_read_queries(): void
    {
        $seen = [];

        DB::listen(static function ($query) use (&$seen): void {
            $seen[] = $query->sql;
        });

        $this->invokeCommand();

        $this->assertNotEmpty(
            $seen,
            'The command must actually query the database, otherwise this proves nothing'
        );

        foreach ($seen as $sql) {
            $this->assertMatchesRegularExpression(
                '/^\s*(select|pragma)\b/i',
                $sql,
                'deploy:migrations-pending issued a non-read statement: ' . $sql
            );

            $this->assertDoesNotMatchRegularExpression(
                '/\b(insert|update|delete|drop|alter|create|truncate|replace)\b/i',
                $sql,
                'deploy:migrations-pending issued a mutating statement: ' . $sql
            );
        }
    }

    public function test_leaves_the_schema_and_the_migrations_ledger_untouched(): void
    {
        $before = $this->databaseFingerprint();

        $this->invokeCommand();

        $this->assertSame($before, $this->databaseFingerprint(), 'The command changed database state.');
    }

    // ── 7. repeatable ───────────────────────────────────────────────────────

    public function test_repeated_invocation_is_idempotent_in_state_and_in_answer(): void
    {
        $before = $this->databaseFingerprint();

        $first  = $this->invokeCommand();
        $middle = $this->databaseFingerprint();
        $second = $this->invokeCommand();
        $after  = $this->databaseFingerprint();

        $this->assertSame($before, $middle);
        $this->assertSame($before, $after);

        $this->assertSame($first['code'], $second['code'], 'The answer must not depend on how often it is asked');
        $this->assertSame(trim($first['output']), trim($second['output']));
    }

    // ── 8. no secrets ───────────────────────────────────────────────────────

    /**
     * A PDO exception raised while reading the migrations table routinely carries
     * the DSN — host, database name, user, and on this project's configuration the
     * password too. This command is designed to run inside a deployment log, so it
     * must be structurally incapable of putting any of that there.
     *
     * The mock throws a message shaped exactly like a real one. Nothing from it may
     * appear in the output.
     */
    public function test_never_prints_an_exception_message_or_connection_details(): void
    {
        $secret = 'SQLSTATE[08006] pgsql:host=helium;port=5432;dbname=heliumdb;user=postgres;password=hunter2';

        $this->bindMigratorMock()
            ->shouldReceive('repositoryExists')->once()->andThrow(new RuntimeException($secret));

        $output = $this->invokeCommand()['output'];

        foreach (['helium', 'heliumdb', 'postgres', 'hunter2', 'password', 'SQLSTATE', '5432'] as $fragment) {
            $this->assertStringNotContainsStringIgnoringCase(
                $fragment,
                $output,
                "deploy:migrations-pending leaked '{$fragment}' into its output"
            );
        }

        $this->assertSame('error=repository_unreadable', trim($output));
    }

    /** Every branch's output is one line from a closed vocabulary — that is the contract callers parse. */
    public function test_output_is_always_a_single_stable_machine_line(): void
    {
        $this->assertMatchesRegularExpression(
            '/^(pending=\d+|error=[a-z_]+)$/',
            trim($this->invokeCommand()['output'])
        );

        $this->app->instance('migrator', new \stdClass());

        $this->assertMatchesRegularExpression(
            '/^(pending=\d+|error=[a-z_]+)$/',
            trim($this->invokeCommandDirectly()['output'])
        );
    }
}
