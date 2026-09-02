<?php

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * Every production process must establish its own environment before Laravel boots.
 *
 * THE INCIDENT THIS PREVENTS
 * --------------------------
 * `.replit` declares `APP_ENV = "local"` and `APP_DEBUG = "true"` under
 * `[userenv.shared]`, with no production override, and the live web process was
 * verified running with exactly those values. `APP_DEBUG=true` renders full stack
 * traces to visitors — including database credentials and environment values. So
 * the production safety flags were being supplied by an ambient, shared,
 * development-oriented setting that nothing in the deployment path overrode.
 *
 * Note that `config/app.php` is already written defensively:
 *
 *     'env'   => env('APP_ENV', 'production'),
 *     'debug' => (bool) env('APP_DEBUG', false),
 *
 * Absent those variables the framework defaults to production/false. The danger
 * is not a missing default — it is an ambient value that is present and wrong.
 *
 * WHY EXPORTING IS SUFFICIENT — AND WHY IT WOULD NOT HAVE BEEN
 * ------------------------------------------------------------
 * `Illuminate\Support\Env` builds its repository with `$builder->immutable()`,
 * and on phpdotenv v5 an immutable repository will not overwrite a variable that
 * is already present in the process environment. So a variable exported by the
 * start script beats the same key in `.env`. That is verified here rather than
 * assumed (see test_a_fresh_laravel_process_resolves_the_exported_values).
 *
 * It would NOT have been sufficient while the deployment build ran
 * `php artisan config:cache`. With a cached config, Laravel skips `.env` loading
 * entirely (`LoadEnvironmentVariables::bootstrap()` returns early when
 * `configurationIsCached()`) and reads a frozen array in which every `env()` call
 * was already resolved at BUILD time. Exporting at run time then changes nothing
 * at all. That is why this change removes `config:cache` from the deployment
 * build, and why the danger is proven below rather than described.
 *
 * WHAT THESE TESTS DO
 * -------------------
 * They RUN the real scripts with a hostile parent environment
 * (`APP_ENV=local APP_DEBUG=true`) and a fake `php` on `PATH` that records the
 * environment each invocation actually observed. Reading the scripts as text
 * could show an `export` line; only running them shows that every Artisan call
 * downstream of it saw the safe values.
 *
 * Nothing here migrates, touches heliumdb, binds a port, reaches the network, or
 * writes into `bootstrap/cache/`.
 */
class ProductionRuntimeEnvironmentTest extends TestCase
{
    /** The values a production process must run with, whatever its parent says. */
    private const REQUIRED_ENV   = 'production';
    private const REQUIRED_DEBUG = 'false';

    /** The hostile ambient values `.replit` supplied and the live process was verified running with. */
    private const HOSTILE_ENV   = 'local';
    private const HOSTILE_DEBUG = 'true';

    /** Every script that boots Laravel as a production process. */
    private const PRODUCTION_ENTRYPOINTS = [
        'deploy/start-production.sh',
        'deploy/start-serving.sh',
        'deploy/scheduler.sh',
    ];

    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            if (is_dir($dir)) {
                exec('rm -rf ' . escapeshellarg($dir));
            }
        }

        $this->tempDirs = [];

        parent::tearDown();
    }

    private function tempDir(string $prefix): string
    {
        $dir = sys_get_temp_dir() . '/' . $prefix . '-' . getmypid() . '-' . count($this->tempDirs);

        exec('rm -rf ' . escapeshellarg($dir));
        mkdir($dir, 0700, true);

        $this->tempDirs[] = $dir;

        return $dir;
    }

    private function replit(): string
    {
        $path = base_path('.replit');

        $this->assertFileExists($path, '.replit must be readable');

        return (string) file_get_contents($path);
    }

    /** Executable lines only — full-line comments and blanks removed. */
    private function executableLines(string $source): array
    {
        $lines = [];

        foreach (preg_split('/\R/', $source) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $lines[] = $trimmed;
        }

        return $lines;
    }

    private function executableSource(string $relative): string
    {
        $path = base_path($relative);

        $this->assertFileExists($path, "{$relative} must exist");

        return implode("\n", $this->executableLines((string) file_get_contents($path)));
    }

    // ── the runtime harness ─────────────────────────────────────────────────

    /**
     * A fake `php` that records, for every invocation, which Artisan command was
     * asked for and what APP_ENV / APP_DEBUG that process actually saw.
     *
     * This is the whole point: the assertion is about what the CHILD observed,
     * not about what the parent script's source says it exports.
     */
    private function fakePhpBin(): string
    {
        $dir = $this->tempDir('prod-env-bin');

        file_put_contents($dir . '/php', <<<'SH'
#!/usr/bin/env bash
subcommand="(none)"
for arg in "$@"; do
    case "$arg" in
        artisan|-*) continue ;;
        *) subcommand="$arg"; break ;;
    esac
done

printf '%s APP_ENV=%s APP_DEBUG=%s\n' \
    "$subcommand" "${APP_ENV-<unset>}" "${APP_DEBUG-<unset>}" >> "$FAKE_LOG"

case "$subcommand" in
    deploy:migrations-pending) exit "${FAKE_READINESS_EXIT:-0}" ;;
esac

exit 0
SH);

        chmod($dir . '/php', 0700);

        return $dir;
    }

    /**
     * Run a production entrypoint with a deliberately hostile parent environment.
     *
     * @return array{code: int, output: string, log: string, observations: list<array{command: string, env: string, debug: string}>}
     */
    private function runEntrypoint(string $relativeScript, string $parentEnv, string $parentDebug): array
    {
        $bin   = $this->fakePhpBin();
        $state = $this->tempDir('prod-env-state');
        $log   = $this->tempDir('prod-env-log') . '/calls.log';

        touch($log);

        $cmd = sprintf(
            'env PATH=%s FAKE_LOG=%s DEPLOY_STATE_DIR=%s APP_ENV=%s APP_DEBUG=%s bash %s 2>&1',
            escapeshellarg($bin . ':' . (string) getenv('PATH')),
            escapeshellarg($log),
            escapeshellarg($state),
            escapeshellarg($parentEnv),
            escapeshellarg($parentDebug),
            escapeshellarg(base_path($relativeScript))
        );

        $output = [];
        $code   = 0;
        exec($cmd, $output, $code);

        $raw          = is_file($log) ? (string) file_get_contents($log) : '';
        $observations = [];

        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            if (preg_match('/^(\S+) APP_ENV=(\S*) APP_DEBUG=(\S*)$/', trim($line), $m) === 1) {
                $observations[] = ['command' => $m[1], 'env' => $m[2], 'debug' => $m[3]];
            }
        }

        return [
            'code'         => $code,
            'output'       => implode("\n", $output),
            'log'          => $raw,
            'observations' => $observations,
        ];
    }

    /** Assert every recorded invocation ran with the production values. */
    private function assertAllObservationsAreProduction(array $result, string $script): void
    {
        $this->assertNotEmpty(
            $result['observations'],
            "{$script} invoked php at least once, otherwise this test proves nothing. Output:\n{$result['output']}"
        );

        foreach ($result['observations'] as $seen) {
            $this->assertSame(
                self::REQUIRED_ENV,
                $seen['env'],
                "{$script}: `{$seen['command']}` observed APP_ENV={$seen['env']}, expected production.\nLog:\n{$result['log']}"
            );

            $this->assertSame(
                self::REQUIRED_DEBUG,
                $seen['debug'],
                "{$script}: `{$seen['command']}` observed APP_DEBUG={$seen['debug']}, expected false.\nLog:\n{$result['log']}"
            );
        }
    }

    private function commandsObserved(array $result): array
    {
        return array_map(static fn (array $o): string => $o['command'], $result['observations']);
    }

    // ══ A. start-production.sh ══════════════════════════════════════════════

    public function test_start_production_overrides_a_hostile_parent_environment(): void
    {
        $result = $this->runEntrypoint('deploy/start-production.sh', self::HOSTILE_ENV, self::HOSTILE_DEBUG);

        $observed = $this->commandsObserved($result);

        // The whole startup sequence must be covered, not just the first call.
        foreach (['deploy:preflight', 'migrate', 'serve'] as $stage) {
            $this->assertContains(
                $stage,
                $observed,
                "start-production.sh must reach `{$stage}`.\nLog:\n{$result['log']}"
            );
        }

        $this->assertAllObservationsAreProduction($result, 'start-production.sh');
    }

    // ══ B. start-serving.sh ═════════════════════════════════════════════════

    public function test_start_serving_overrides_a_hostile_parent_environment(): void
    {
        $result = $this->runEntrypoint('deploy/start-serving.sh', self::HOSTILE_ENV, self::HOSTILE_DEBUG);

        $observed = $this->commandsObserved($result);

        foreach (['deploy:migrations-pending', 'serve'] as $stage) {
            $this->assertContains(
                $stage,
                $observed,
                "start-serving.sh must reach `{$stage}`.\nLog:\n{$result['log']}"
            );
        }

        $this->assertAllObservationsAreProduction($result, 'start-serving.sh');
    }

    // ══ C. scheduler.sh ═════════════════════════════════════════════════════

    /**
     * The scheduler is a second production Laravel process, configured separately
     * and started separately (deploy/SCHEDULER.md). Hardening only the web path
     * would leave every scheduled command running in debug mode.
     */
    public function test_scheduler_overrides_a_hostile_parent_environment(): void
    {
        $result = $this->runEntrypoint('deploy/scheduler.sh', self::HOSTILE_ENV, self::HOSTILE_DEBUG);

        $this->assertContains(
            'schedule:work',
            $this->commandsObserved($result),
            "scheduler.sh must reach `schedule:work`.\nLog:\n{$result['log']}"
        );

        $this->assertAllObservationsAreProduction($result, 'scheduler.sh');
    }

    // ══ the security regression ═════════════════════════════════════════════

    /**
     * The narrow security claim, stated once over every production entrypoint:
     * setting APP_DEBUG=true (or APP_ENV=local) in the parent environment cannot
     * make a production process run that way.
     */
    public function test_no_production_entrypoint_can_be_forced_into_debug_mode_by_its_parent(): void
    {
        foreach (self::PRODUCTION_ENTRYPOINTS as $script) {
            $result = $this->runEntrypoint($script, self::HOSTILE_ENV, self::HOSTILE_DEBUG);

            $this->assertNotEmpty($result['observations'], "{$script} must invoke php for this to prove anything");

            $this->assertStringNotContainsString(
                'APP_DEBUG=true',
                $result['log'],
                "{$script} allowed APP_DEBUG=true to reach a production process.\nLog:\n{$result['log']}"
            );

            $this->assertStringNotContainsString(
                'APP_ENV=local',
                $result['log'],
                "{$script} allowed APP_ENV=local to reach a production process.\nLog:\n{$result['log']}"
            );
        }
    }

    /**
     * The values must be set unconditionally.
     *
     * `${APP_ENV:-production}` reads as a safe default but does the opposite of
     * what is needed here: it keeps the ambient value whenever one is present,
     * and an ambient value being present and wrong is precisely the situation.
     */
    public function test_no_entrypoint_defers_to_an_ambient_value(): void
    {
        foreach (self::PRODUCTION_ENTRYPOINTS as $script) {
            $executable = $this->executableSource($script);

            foreach (['APP_ENV', 'APP_DEBUG'] as $key) {
                $this->assertDoesNotMatchRegularExpression(
                    '/\$\{' . $key . '(:-|-)/',
                    $executable,
                    "{$script} must set {$key} unconditionally, never fall back to the ambient value"
                );
            }

            $this->assertMatchesRegularExpression(
                '/^export APP_ENV=production$/m',
                $executable,
                "{$script} must export APP_ENV=production"
            );

            $this->assertMatchesRegularExpression(
                '/^export APP_DEBUG=false$/m',
                $executable,
                "{$script} must export APP_DEBUG=false"
            );
        }
    }

    /** The export must precede every Laravel invocation in the file, not merely exist. */
    public function test_the_environment_is_established_before_any_php_invocation(): void
    {
        foreach (self::PRODUCTION_ENTRYPOINTS as $script) {
            $lines      = $this->executableLines((string) file_get_contents(base_path($script)));
            $exportedAt = null;
            $firstPhpAt = null;

            foreach ($lines as $index => $line) {
                if ($exportedAt === null && preg_match('/^export APP_DEBUG=false$/', $line) === 1) {
                    $exportedAt = $index;
                }

                if ($firstPhpAt === null && preg_match('/(^|\s)php\s/', $line) === 1) {
                    $firstPhpAt = $index;
                }
            }

            $this->assertNotNull($exportedAt, "{$script} must export APP_DEBUG=false");
            $this->assertNotNull($firstPhpAt, "{$script} must invoke php");

            $this->assertLessThan(
                $firstPhpAt,
                $exportedAt,
                "{$script} exports the production environment AFTER it has already invoked php"
            );
        }
    }

    // ══ D. the shared userenv no longer supplies them ═══════════════════════

    /**
     * `[userenv.shared]` is shared with development and workspace shells, so it is
     * the wrong authority for production safety flags in either direction: setting
     * local/true endangers production, and setting production/false would push
     * every development shell into production mode. The keys belong to neither —
     * the entrypoints own them, and development keeps using its own `.env`.
     */
    public function test_the_shared_userenv_declares_neither_app_env_nor_app_debug(): void
    {
        $shared = $this->sharedUserenvBlock();

        foreach (['APP_ENV', 'APP_DEBUG'] as $key) {
            $this->assertDoesNotMatchRegularExpression(
                '/^\s*' . $key . '\s*=/m',
                $shared,
                "[userenv.shared] must not declare {$key} — production entrypoints own it"
            );
        }
    }

    /** Unrelated shared settings must survive the removal untouched. */
    public function test_unrelated_shared_userenv_values_are_preserved(): void
    {
        $shared = $this->sharedUserenvBlock();

        foreach (['APP_NAME', 'APP_URL', 'DB_CONNECTION', 'DB_DATABASE', 'CACHE_DRIVER', 'SESSION_DRIVER', 'QUEUE_CONNECTION', 'ASSET_URL'] as $key) {
            $this->assertMatchesRegularExpression(
                '/^\s*' . $key . '\s*=/m',
                $shared,
                "[userenv.shared] must keep its unrelated {$key} entry"
            );
        }
    }

    /** The text of the `[userenv.shared]` table only, up to the next section header. */
    private function sharedUserenvBlock(): string
    {
        $replit = $this->replit();

        $this->assertStringContainsString('[userenv.shared]', $replit, '.replit must still declare [userenv.shared]');

        $start = strpos($replit, '[userenv.shared]');
        $rest  = substr($replit, $start + strlen('[userenv.shared]'));

        // Stop at the next table header so a later section's keys are never read
        // as if they belonged to this one.
        if (preg_match('/\R\[/', $rest, $m, PREG_OFFSET_CAPTURE) === 1) {
            $rest = substr($rest, 0, $m[0][1]);
        }

        return $rest;
    }

    // ══ E. the deployment build must not cache configuration ════════════════

    /**
     * THE REASON THE EXPORTS WORK AT ALL.
     *
     * A cached config file freezes every `env()` result at build time and makes
     * `LoadEnvironmentVariables` skip `.env` entirely, so a run-time export is
     * inert. The build must therefore not produce one.
     */
    public function test_the_deployment_build_never_caches_configuration(): void
    {
        $build = $this->deploymentBuildCommand();

        $this->assertNotSame('', $build, 'The [deployment] build command must be present to be checked');

        $this->assertStringNotContainsString(
            'config:cache',
            $build,
            'The deployment build must not cache configuration — it would freeze build-time APP_DEBUG into production'
        );

        // Non-vacuity: the matcher must catch the command it is looking for.
        $this->assertStringContainsString(
            'config:cache',
            'php artisan config:cache',
            'The matcher must be able to detect a real config:cache invocation'
        );
    }

    /** Removing the cache step must not have quietly removed the rest of the build. */
    public function test_the_rest_of_the_deployment_build_is_preserved(): void
    {
        $build = $this->deploymentBuildCommand();

        foreach (['composer', 'install', 'npm', 'route:cache', 'view:cache'] as $fragment) {
            $this->assertStringContainsString(
                $fragment,
                $build,
                "The deployment build must keep its unrelated `{$fragment}` step"
            );
        }
    }

    private function deploymentBuildCommand(): string
    {
        $replit = $this->replit();

        $this->assertMatchesRegularExpression('/^build\s*=\s*\[/m', $replit, '.replit must declare a [deployment] build');

        preg_match('/^build\s*=\s*\[(.*)$/m', $replit, $m);

        return (string) ($m[1] ?? '');
    }

    /** No production execution path anywhere may create a config cache. */
    public function test_no_production_path_creates_a_configuration_cache(): void
    {
        $offenders = [];

        $candidates = [
            '.replit',
            'deploy/start-production.sh',
            'deploy/start-serving.sh',
            'deploy/scheduler.sh',
            'composer.json',
            'package.json',
        ];

        foreach ($candidates as $relative) {
            $path = base_path($relative);

            if (! is_file($path)) {
                continue;
            }

            if (str_contains(implode("\n", $this->executableLines((string) file_get_contents($path))), 'config:cache')) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'No production path may create bootstrap/cache/config.php: ' . implode(', ', $offenders)
        );
    }

    // ══ Laravel-level proof, in a fresh process ═════════════════════════════

    /**
     * Shell strings are not the claim; `config('app.debug')` is.
     *
     * Runs a real, separate Laravel process — hostile values in the parent, the
     * entrypoints' contract applied on top — and reads the resolved config back.
     */
    public function test_a_fresh_laravel_process_resolves_the_exported_values(): void
    {
        $this->assertFileDoesNotExist(
            base_path('bootstrap/cache/config.php'),
            'This proof is only meaningful with no configuration cache present'
        );

        $resolved = $this->resolveConfigInFreshProcess(
            ['APP_ENV' => self::HOSTILE_ENV, 'APP_DEBUG' => self::HOSTILE_DEBUG],
            ['APP_ENV' => 'production', 'APP_DEBUG' => 'false']
        );

        $this->assertSame('production', $resolved['env'], "Fresh process resolved app.env={$resolved['env']}");
        $this->assertSame('false', $resolved['debug'], "Fresh process resolved app.debug={$resolved['debug']}");
    }

    /** The control: without the contract, the hostile values are what Laravel resolves. */
    public function test_without_the_contract_the_hostile_values_survive(): void
    {
        $resolved = $this->resolveConfigInFreshProcess(
            ['APP_ENV' => self::HOSTILE_ENV, 'APP_DEBUG' => self::HOSTILE_DEBUG],
            []
        );

        $this->assertSame(
            'local',
            $resolved['env'],
            'Control: with no production contract applied, the ambient value must win — otherwise the test above proves nothing'
        );

        $this->assertSame('true', $resolved['debug'], 'Control: ambient APP_DEBUG=true must survive without the contract');
    }

    /**
     * WHY `config:cache` HAD TO GO, demonstrated rather than argued.
     *
     * A cache built with hostile values makes the exports inert. The cache is
     * written to a temporary path via APP_CONFIG_CACHE, so this never creates a
     * file under bootstrap/cache/ that a later test could accidentally boot from.
     */
    public function test_a_configuration_cache_would_defeat_the_exports(): void
    {
        $cache = $this->tempDir('prod-env-cache') . '/config.php';

        // Build the cache the way a deployment build would, with hostile ambient values.
        $build = [];
        $code  = 0;
        exec(sprintf(
            'cd %s && env APP_CONFIG_CACHE=%s APP_ENV=%s APP_DEBUG=%s php artisan config:cache 2>&1',
            escapeshellarg(base_path()),
            escapeshellarg($cache),
            escapeshellarg(self::HOSTILE_ENV),
            escapeshellarg(self::HOSTILE_DEBUG)
        ), $build, $code);

        $this->assertFileExists($cache, "The probe cache must be built. Output:\n" . implode("\n", $build));

        $resolved = $this->resolveConfigInFreshProcess(
            ['APP_ENV' => self::HOSTILE_ENV, 'APP_DEBUG' => self::HOSTILE_DEBUG],
            ['APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_CONFIG_CACHE' => $cache]
        );

        $this->assertSame(
            'true',
            $resolved['debug'],
            'A cached config must be shown to defeat the run-time export — otherwise removing config:cache is unjustified'
        );

        $this->assertSame('local', $resolved['env']);

        $this->assertFileDoesNotExist(
            base_path('bootstrap/cache/config.php'),
            'The probe must never create a real configuration cache in the repository'
        );
    }

    /**
     * Boot Laravel in a separate process and read back app.env / app.debug.
     *
     * @param  array<string, string>  $parent    hostile ambient values
     * @param  array<string, string>  $contract  what the entrypoint establishes on top
     * @return array{env: string, debug: string}
     */
    private function resolveConfigInFreshProcess(array $parent, array $contract): array
    {
        $assignments = '';

        foreach (array_merge($parent, $contract) as $key => $value) {
            $assignments .= ' ' . $key . '=' . escapeshellarg($value);
        }

        $script = 'require "vendor/autoload.php";'
            . ' $app = require "bootstrap/app.php";'
            . ' $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();'
            . ' echo "RESOLVED env=", config("app.env"), " debug=", var_export(config("app.debug"), true), "\n";';

        $output = [];
        $code   = 0;

        exec(sprintf(
            'cd %s && env%s php -r %s 2>&1',
            escapeshellarg(base_path()),
            $assignments,
            escapeshellarg($script)
        ), $output, $code);

        $joined = implode("\n", $output);

        $this->assertMatchesRegularExpression(
            '/RESOLVED env=(\S+) debug=(\S+)/',
            $joined,
            "The fresh Laravel process must report its resolved config. Output:\n{$joined}"
        );

        preg_match('/RESOLVED env=(\S+) debug=(\S+)/', $joined, $m);

        return ['env' => $m[1], 'debug' => $m[2]];
    }

    // ══ nothing else may have moved ═════════════════════════════════════════

    public function test_migration_ownership_is_unchanged(): void
    {
        $owners = [];

        foreach ([
            'deploy/start-production.sh',
            'deploy/start-serving.sh',
            'deploy/scheduler.sh',
            'scripts/post-merge.sh',
        ] as $relative) {
            if (preg_match('/artisan\s+migrate(\s|:|$)/m', $this->executableSource($relative)) === 1) {
                $owners[] = $relative;
            }
        }

        $this->assertSame(
            ['deploy/start-production.sh'],
            $owners,
            'Exactly one script may run production migrations'
        );
    }

    public function test_the_deploy_sha_writer_is_unchanged(): void
    {
        $writers = [];

        foreach (self::PRODUCTION_ENTRYPOINTS as $relative) {
            if (str_contains($this->executableSource($relative), 'record_deploy_sha')) {
                $writers[] = $relative;
            }
        }

        $this->assertSame(['deploy/start-production.sh'], $writers, 'Exactly one script may write the deploy SHA');
    }
}
