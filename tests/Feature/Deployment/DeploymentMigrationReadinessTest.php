<?php

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * The deployment applies migrations before it serves traffic, and exactly one
 * process owns that job.
 *
 * WHY THESE ARE STATIC ASSERTIONS
 * -------------------------------
 * None of this can be executed here: booting a Replit VM deployment is not
 * something a PHPUnit suite can do. But the failure being guarded against is
 * not subtle behaviour — it is a line going missing from a config file. A test
 * that reads the config catches exactly that, which is the whole risk.
 *
 * The alternative was a comment asking people not to remove it. That is what
 * the repository had before, in effect, and the deployment shipped for months
 * with no migration step at all because nothing failed when it was absent.
 */
class DeploymentMigrationReadinessTest extends TestCase
{
    private function replit(): string
    {
        $contents = file_get_contents(base_path('.replit'));

        $this->assertIsString($contents, '.replit must be readable');

        return $contents;
    }

    private function startScript(): string
    {
        $path = base_path('deploy/start-production.sh');

        $this->assertFileExists($path, 'The production start script must exist');

        $contents = file_get_contents($path);

        $this->assertIsString($contents);

        return $contents;
    }

    // ── the deployment runs the start script ────────────────────────────────

    public function test_the_deployment_run_command_uses_the_production_start_script(): void
    {
        $this->assertStringContainsString(
            'deploy/start-production.sh',
            $this->replit(),
            'The [deployment] run command must go through the start script that migrates'
        );
    }

    public function test_the_deployment_does_not_serve_directly_without_migrating(): void
    {
        // The old command. If it comes back, migrations stop running and nothing
        // else in this suite would notice.
        $this->assertStringNotContainsString(
            'run = ["bash", "-c", "PHP_INI_SCAN_DIR=\"$PWD/deploy/php\" php artisan serve',
            $this->replit(),
            'The deployment must not start the web server without migrating first'
        );
    }

    // ── the start script migrates, then serves, and fails fast ──────────────

    public function test_the_start_script_migrates_before_serving(): void
    {
        $script = $this->startScript();

        $migratePos = strpos($script, 'artisan migrate --force');
        $servePos   = strpos($script, 'artisan serve');

        $this->assertNotFalse($migratePos, 'The start script must run migrations');
        $this->assertNotFalse($servePos, 'The start script must start the web server');
        $this->assertLessThan(
            $servePos,
            $migratePos,
            'Migrations must run BEFORE the server binds a port'
        );
    }

    public function test_the_start_script_fails_fast(): void
    {
        // `set -e` is what makes a failed migration stop the script instead of
        // falling through to serve. Without it the sequencing above proves
        // nothing — the server would still start, against the old schema.
        $script = $this->startScript();

        $this->assertMatchesRegularExpression(
            '/^set -euo pipefail$/m',
            $script,
            'The start script must abort on the first failing command'
        );
    }

    public function test_the_migration_is_not_swallowed(): void
    {
        // `|| true` on the migrate line would defeat the entire change: the
        // deploy would report success and serve against an unmigrated schema.
        $script = $this->startScript();

        foreach (preg_split('/\R/', $script) ?: [] as $line) {
            if (str_contains($line, 'artisan migrate')) {
                $this->assertStringNotContainsString('|| true', $line, 'A failed migration must not be ignored');
                $this->assertStringNotContainsString('2>/dev/null', $line, 'A failed migration must not be silenced');
            }
        }
    }

    public function test_the_start_script_runs_migrations_non_interactively_and_forced(): void
    {
        // No terminal is attached, and a production APP_ENV would otherwise
        // prompt for confirmation and hang the deploy.
        $this->assertStringContainsString('artisan migrate --force --no-interaction', $this->startScript());
    }

    /**
     * The executable lines of the start script, with comments and blanks
     * removed.
     *
     * The destructive-command scan below has to run against what actually
     * executes. Scanning the raw file failed on the script's own comment
     * explaining that `migrate:fresh` must never appear — a test that cannot
     * tell a prohibition from an instance of the thing prohibited would force
     * the documentation to be deleted to make it pass.
     */
    private function executableLines(): string
    {
        $lines = array_filter(
            preg_split('/\R/', $this->startScript()) ?: [],
            static function (string $line): bool {
                $trimmed = trim($line);

                return $trimmed !== '' && ! str_starts_with($trimmed, '#');
            }
        );

        return implode("\n", $lines);
    }

    /**
     * @dataProvider destructiveCommands
     */
    public function test_the_start_script_contains_no_destructive_migration_command(string $command): void
    {
        $this->assertStringNotContainsString(
            $command,
            $this->executableLines(),
            "{$command} destroys data and must never appear in a start command"
        );
    }

    public function test_the_destructive_scan_examines_the_real_commands(): void
    {
        // Guards the comment-stripping above from stripping everything: if the
        // executable body ever came back empty, every destructive-command
        // assertion would pass vacuously.
        $executable = $this->executableLines();

        $this->assertStringContainsString('artisan migrate --force', $executable);
        $this->assertStringContainsString('artisan serve', $executable);
    }

    public static function destructiveCommands(): array
    {
        return [
            'migrate:fresh'   => ['migrate:fresh'],
            'migrate:reset'   => ['migrate:reset'],
            'migrate:refresh' => ['migrate:refresh'],
            'db:wipe'         => ['db:wipe'],
        ];
    }

    // ── exactly one process owns migrations ─────────────────────────────────

    public function test_the_scheduler_never_runs_migrations(): void
    {
        // The reason this matters more than usual: this application is on
        // Laravel 8, which has no `migrate --isolated` (the built-in migration
        // lock arrived in Laravel 9). There is no mutex, so concurrency safety
        // rests entirely on a single process owning the job.
        $scheduler = file_get_contents(base_path('deploy/scheduler.sh'));

        $this->assertIsString($scheduler);
        $this->assertStringNotContainsString(
            'artisan migrate',
            $scheduler,
            'The scheduler must never migrate — the web start script owns migrations'
        );
    }

    public function test_no_other_deploy_script_migrates(): void
    {
        $offenders = [];

        foreach (glob(base_path('deploy/*.sh')) ?: [] as $script) {
            if (basename($script) === 'start-production.sh') {
                continue;
            }

            if (str_contains((string) file_get_contents($script), 'artisan migrate')) {
                $offenders[] = basename($script);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Only deploy/start-production.sh may run migrations: ' . implode(', ', $offenders)
        );
    }

    public function test_the_build_phase_does_not_migrate(): void
    {
        // The build phase runs before the runtime environment is assembled and
        // is not guaranteed to hold database credentials. It is also the wrong
        // moment — the schema must be current when this release starts serving,
        // not when its artifact was compiled.
        $replit = $this->replit();

        $buildLine = '';

        foreach (preg_split('/\R/', $replit) ?: [] as $line) {
            if (str_starts_with(trim($line), 'build = ')) {
                $buildLine = $line;
            }
        }

        $this->assertNotSame('', $buildLine, 'The [deployment] build command must be present');
        $this->assertStringNotContainsString('migrate', $buildLine, 'Migrations do not belong in the build phase');
    }

    // ── the preflight report ────────────────────────────────────────────────

    public function test_the_preflight_command_exists_and_never_fails_the_deploy(): void
    {
        // It reports; `migrate` gates. A preflight that can refuse to start the
        // application is a new way for deploys to fail, and the conditions it
        // reports on are exactly the ones whose semantics are unverified.
        $this->withoutMockingConsoleOutput();

        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('deploy:preflight'));
    }

    public function test_the_preflight_reports_the_environment_mode(): void
    {
        $this->withoutMockingConsoleOutput();

        \Illuminate\Support\Facades\Artisan::call('deploy:preflight');
        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertStringContainsString('APP_ENV', $output);
        $this->assertStringContainsString('APP_DEBUG', $output);
        $this->assertStringContainsString('pending migrs', $output);
    }

    public function test_the_preflight_never_prints_credentials(): void
    {
        $this->withoutMockingConsoleOutput();

        \Illuminate\Support\Facades\Artisan::call('deploy:preflight');
        $output = \Illuminate\Support\Facades\Artisan::output();

        foreach (['password', 'DB_PASSWORD', 'DB_USERNAME', 'secret'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase(
                $forbidden,
                $output,
                'The preflight report must never print credentials'
            );
        }
    }
}
