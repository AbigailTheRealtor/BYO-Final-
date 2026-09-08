<?php

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * `deploy/start-serving.sh` — the NON-MUTATING half of the two entry points.
 *
 * THE TWO ENTRY POINTS
 * --------------------
 *   EXPLICIT DEPLOYMENT   .replit [deployment] -> deploy/start-production.sh
 *       lock, preflight, MIGRATE, record deploy SHA, release, exec server.
 *       Remains the sole migration owner.
 *
 *   SUPERVISOR RESTART    port-5000 workflow  -> deploy/start-serving.sh
 *       same lock, longer bounded wait, READ-ONLY readiness check, never
 *       migrate, never record a SHA, release, exec server.
 *
 * WHY THE SECOND ONE HAD TO EXIST
 * -------------------------------
 * The workspace supervisor restarts the port-5000 process for reasons that have
 * nothing to do with shipping: a container restart, a workflow re-run, someone
 * pressing the button. Until now that path either ran a bare `php artisan serve`
 * — which will happily serve new code against an old schema — or, if it had been
 * pointed at the deployment script, would have MIGRATED PRODUCTION as a side
 * effect of a restart. Both are wrong, and they are wrong in opposite directions.
 *
 * A restart is not a deployment. It must serve the already-pinned, already-
 * migrated commit, and it must refuse rather than improvise if the schema is not
 * ready. Migrating is a deployment event; only the explicit path may do it.
 *
 * WHY THE READINESS CHECK IS AN EXIT CODE
 * ---------------------------------------
 * `migrate:status` cannot be used: on Laravel 8 it exits 0 whether or not
 * anything is pending (see DeployMigrationsPendingTest). `deploy:preflight`
 * always exits 0 by design. `deploy:migrations-pending` was merged in #113
 * precisely so this script has something to branch on:
 *
 *     0 ready   1 pending   2 undetermined
 *
 * Fail-closed on everything that is not 0, including exit codes nobody has
 * defined yet — a restart that serves an unknown schema state is the failure
 * this whole arrangement exists to prevent.
 *
 * WHAT THESE TESTS DO
 * -------------------
 * The static half reads the committed script and `.replit`. The runtime half
 * actually RUNS `deploy/start-serving.sh` against a fake `php` on PATH and a
 * temporary DEPLOY_STATE_DIR, because asserting on a script as text cannot prove
 * that a non-zero readiness result stops it reaching `serve`.
 *
 * Nothing here migrates, touches heliumdb, binds port 5000, or reaches the
 * network.
 */
class DeployStartServingTest extends TestCase
{
    /** Bounded poll: 200 iterations x 50ms = 10s ceiling, exits as soon as the condition holds. */
    private const POLL_ITERATIONS = 200;

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

    // ── source access ───────────────────────────────────────────────────────

    private function servingScriptPath(): string
    {
        return base_path('deploy/start-serving.sh');
    }

    private function servingScript(): string
    {
        $path = $this->servingScriptPath();

        $this->assertFileExists($path, 'deploy/start-serving.sh must exist');

        return (string) file_get_contents($path);
    }

    private function replit(): string
    {
        $path = base_path('.replit');

        $this->assertFileExists($path, '.replit must be readable');

        return (string) file_get_contents($path);
    }

    /** Executable lines only — full-line comments and blanks removed. */
    private function executableLines(string $script): array
    {
        $lines = [];

        foreach (preg_split('/\R/', $script) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $lines[] = $trimmed;
        }

        return $lines;
    }

    private function executableSource(string $script): string
    {
        return implode("\n", $this->executableLines($script));
    }

    /**
     * The `args` of the workflow task that binds port 5000.
     *
     * Located by `waitForPort = 5000` rather than by workflow name, so renaming
     * the workflow cannot make this test silently inspect nothing.
     */
    private function portFiveThousandWorkflowArgs(): string
    {
        $lines   = preg_split('/\R/', $this->replit()) ?: [];
        $pending = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (preg_match('/^args\s*=\s*"(.*)"\s*$/', $trimmed, $m) === 1) {
                $pending = $m[1];

                continue;
            }

            if (preg_match('/^waitForPort\s*=\s*5000\s*$/', $trimmed) === 1 && $pending !== null) {
                return $pending;
            }
        }

        $this->fail('No workflow task binds port 5000 — this test cannot verify a serving path it cannot find');
    }

    // ── runtime harness ─────────────────────────────────────────────────────

    /**
     * A fake `php` that records what it was asked to do and answers with a
     * configured readiness exit code.
     *
     * This is how a non-zero readiness result can be proven to STOP the script:
     * the real command would need a database and would always say "ready" here.
     * The fake also records any migrate invocation, so "never migrates" is
     * observed rather than assumed from reading the source.
     */
    private function fakePhpBin(): string
    {
        $dir = $this->tempDir('start-serving-bin');

        file_put_contents($dir . '/php', <<<'SH'
#!/usr/bin/env bash
printf 'ARGS %s\n' "$*" >> "$FAKE_LOG"
printf 'PHP_INI_SCAN_DIR %s\n' "${PHP_INI_SCAN_DIR:-<unset>}" >> "$FAKE_LOG"

for arg in "$@"; do
    case "$arg" in
        deploy:migrations-pending)
            printf 'READINESS_INVOKED\n' >> "$FAKE_LOG"
            exit "${FAKE_READINESS_EXIT:-0}"
            ;;
        migrate|migrate:*|db:wipe|schema:load)
            printf 'MIGRATE_INVOKED %s\n' "$arg" >> "$FAKE_LOG"
            exit 0
            ;;
        serve)
            printf 'SERVE_INVOKED\n' >> "$FAKE_LOG"
            if [ -n "${FAKE_SERVE_EXEC:-}" ]; then
                exec ${FAKE_SERVE_EXEC}
            fi
            exit 0
            ;;
    esac
done

exit 0
SH);

        chmod($dir . '/php', 0700);

        return $dir;
    }

    /**
     * Run the REAL deploy/start-serving.sh with a fake php and a temporary
     * deploy-state directory.
     *
     * @return array{code: int, output: string, log: string, state: string}
     */
    private function runStartServing(int $readinessExit, ?string $scriptPath = null): array
    {
        $bin   = $this->fakePhpBin();
        $state = $this->tempDir('start-serving-state');
        $log   = $this->tempDir('start-serving-log') . '/calls.log';

        touch($log);

        $script = $scriptPath ?? $this->servingScriptPath();

        $cmd = sprintf(
            'env PATH=%s FAKE_LOG=%s FAKE_READINESS_EXIT=%d DEPLOY_STATE_DIR=%s bash %s 2>&1',
            escapeshellarg($bin . ':' . (string) getenv('PATH')),
            escapeshellarg($log),
            $readinessExit,
            escapeshellarg($state),
            escapeshellarg($script)
        );

        $output = [];
        $code   = 0;
        exec($cmd, $output, $code);

        return [
            'code'   => $code,
            'output' => implode("\n", $output),
            'log'    => is_file($log) ? (string) file_get_contents($log) : '',
            'state'  => $state,
        ];
    }

    /** Run a bash snippet and return [exitCode, output]. */
    private function bash(string $script): array
    {
        $file = $this->tempDir('start-serving-bash') . '/run.sh';
        file_put_contents($file, $script);

        $output = [];
        $code   = 0;
        exec('bash ' . escapeshellarg($file) . ' 2>&1', $output, $code);

        return [$code, implode("\n", $output)];
    }

    // ══ A. the script exists ════════════════════════════════════════════════

    public function test_the_serving_script_exists_and_is_bash(): void
    {
        $script = $this->servingScript();

        $this->assertStringStartsWith(
            '#!/usr/bin/env bash',
            $script,
            'start-serving.sh must be a bash script'
        );

        $this->assertStringContainsString(
            'set -euo pipefail',
            $script,
            'start-serving.sh must fail closed on unset variables and pipeline errors'
        );
    }

    // ══ B / C / D. the SHARED lock ══════════════════════════════════════════

    public function test_it_sources_the_same_deploy_state_helper_as_start_production(): void
    {
        $serving    = $this->executableSource($this->servingScript());
        $production = $this->executableSource((string) file_get_contents(base_path('deploy/start-production.sh')));

        $this->assertStringContainsString(
            'deploy/lib/deploy-state.sh',
            $serving,
            'start-serving.sh must source the shared deploy-state helper'
        );

        $this->assertStringContainsString(
            'deploy/lib/deploy-state.sh',
            $production,
            'Precondition: start-production.sh sources the same helper'
        );
    }

    public function test_it_acquires_the_deploy_lock(): void
    {
        $this->assertStringContainsString(
            'acquire_deploy_lock',
            $this->executableSource($this->servingScript()),
            'start-serving.sh must acquire the deploy lock before doing anything'
        );
    }

    /**
     * The lock PATH must come from the shared helper, never be restated here.
     *
     * A second literal path is how two scripts end up locking two different
     * files while both believing they are excluding each other.
     */
    public function test_it_never_names_a_lock_path_of_its_own(): void
    {
        $executable = $this->executableSource($this->servingScript());

        $this->assertStringNotContainsString(
            'deploy.lock',
            $executable,
            'The lock file path must come from deploy_lock_file(), not be restated'
        );

        $this->assertStringNotContainsString(
            '.ops-backups',
            $executable,
            'The state directory must come from the shared helper'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/^\s*(deploy_lock_file|deploy_state_dir)\s*\(\)/m',
            $executable,
            'start-serving.sh must not define its own copy of the shared helper functions'
        );
    }

    /** Runtime proof that it really is the SAME lock file the helper manages. */
    public function test_it_locks_the_file_the_shared_helper_resolves(): void
    {
        $result = $this->runStartServing(0);

        $this->assertFileExists(
            $result['state'] . '/deploy.lock',
            'start-serving.sh must take the lock the shared helper resolves inside DEPLOY_STATE_DIR'
        );
    }

    // ══ E. bounded, longer, serve-side timeout ══════════════════════════════

    public function test_the_serve_side_lock_timeout_defaults_to_180_seconds(): void
    {
        $executable = $this->executableSource($this->servingScript());

        $this->assertMatchesRegularExpression(
            '/DEPLOY_LOCK_TIMEOUT="\$\{DEPLOY_LOCK_TIMEOUT:-180\}"/',
            $executable,
            'A restart should wait out a deployment rather than fail instantly — but still bounded, at 180s'
        );

        $this->assertStringContainsString(
            'export DEPLOY_LOCK_TIMEOUT',
            $executable,
            'The timeout must be exported so the sourced helper sees it'
        );
    }

    // ══ F. the readiness gate ═══════════════════════════════════════════════

    public function test_it_invokes_the_readiness_command_before_serving(): void
    {
        $executable = $this->executableSource($this->servingScript());

        $this->assertStringContainsString(
            'deploy:migrations-pending',
            $executable,
            'start-serving.sh must verify schema readiness before serving'
        );

        $readinessAt = strpos($executable, 'deploy:migrations-pending');
        $serveAt     = strpos($executable, 'artisan serve');

        $this->assertIsInt($readinessAt);
        $this->assertIsInt($serveAt);
        $this->assertLessThan(
            $serveAt,
            $readinessAt,
            'The readiness check must come before the server invocation'
        );
    }

    /**
     * The exit code is the contract — never a parsed table.
     *
     * Matched in COMMAND POSITION, not as a bare substring. A substring check
     * reads the British spelling in "unrecognised status" as the `sed` command
     * and fails a script that pipes nothing anywhere; a test that cannot tell an
     * English word from an invocation is not testing what it claims to.
     */
    public function test_it_branches_on_the_exit_code_and_never_parses_output(): void
    {
        $executable = $this->executableSource($this->servingScript());

        foreach (['grep', 'awk', 'sed', 'cut', 'head', 'tail'] as $tool) {
            $this->assertDoesNotMatchRegularExpression(
                '/(?:^|[|;&(]|\s)' . $tool . '\s/m',
                $executable,
                "start-serving.sh must not pipe command output through {$tool} — the exit code is the contract"
            );
        }

        $this->assertStringNotContainsString(
            'migrate:status',
            $executable,
            'migrate:status cannot gate anything on Laravel 8 — it exits 0 whether or not migrations are pending'
        );

        // Non-vacuity: the regex must actually catch a real invocation.
        $this->assertMatchesRegularExpression(
            '/(?:^|[|;&(]|\s)grep\s/m',
            'php artisan deploy:migrations-pending | grep pending=0',
            'The command-position matcher must detect a genuine pipe into grep'
        );
    }

    /**
     * No DECISION in this script may suppress its own exit code.
     *
     * This assertion used to take a blanket form — "nothing in a fail-closed script may be
     * suppressed with || true" — and that was exactly right while every executable line in the
     * script was a gate. It stopped being right when the script gained a line that is
     * informational BY DESIGN: it prints the required-production-flag contract, and it must be
     * incapable of refusing anything. A workspace that would not start until the modern platform
     * was fully enabled would make testing the legacy path impossible, and would turn a
     * visibility line into a deployment-grade constraint on the one machine where experimenting
     * is the point.
     *
     * SO THE EXCEPTION IS NAMED, SINGULAR AND PINNED, and the assertion around it is STRICTER
     * than the blanket one it replaces on every other count:
     *
     *   - it now rejects ANY `||` short-circuit, not only the literal `|| true`, so `|| :` and
     *     `|| echo …` can no longer slip a suppression past a string match;
     *   - the one permitted line must match the informational call BYTE FOR BYTE, so it cannot
     *     drift into a different command or lose `--report`;
     *   - `--report` is what makes that line unable to gate. Without it the line would be a gate
     *     whose refusal had been silenced, which is the precise thing this test exists to stop.
     *
     * The readiness check keeps its own explicit assertion below, unchanged.
     */
    public function test_the_readiness_result_is_never_suppressed(): void
    {
        // The only line in this script permitted to swallow its exit code.
        $informational = 'php artisan deploy:require-flags --report || true';

        foreach ($this->executableLines($this->servingScript()) as $line) {
            if (str_contains($line, 'deploy:migrations-pending')) {
                $this->assertStringNotContainsString(
                    '|| true',
                    $line,
                    'The readiness check must never be suppressed with || true'
                );
            }

            if (! str_contains($line, '||')) {
                continue;
            }

            $this->assertSame(
                $informational,
                $line,
                'Only the informational required-flag report may suppress its exit code; '
                . 'everything else in a fail-closed script must be able to refuse.'
            );
        }

        // And that permitted line must actually be present as written — an exception nobody
        // uses is fine, but an exception that has quietly become something else is not.
        $this->assertStringContainsString(
            $informational,
            $this->executableSource($this->servingScript()),
            'The one suppressed line must remain the --report call, exactly as written.'
        );
    }

    // ══ G. never migrates ═══════════════════════════════════════════════════

    public function test_it_contains_no_executable_migration_command(): void
    {
        $executable = $this->executableSource($this->servingScript());

        // `migrate` followed by whitespace, end, or a colon. Deliberately does not
        // match `deploy:migrations-pending`, whose next character is `s`.
        $this->assertDoesNotMatchRegularExpression(
            '/artisan\s+migrate(\s|:|$)/m',
            $executable,
            'start-serving.sh must never run migrations — that is start-production.sh\'s job alone'
        );

        foreach (['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'db:wipe', 'schema:load'] as $destructive) {
            $this->assertStringNotContainsString(
                $destructive,
                $executable,
                "start-serving.sh must never contain {$destructive}"
            );
        }
    }

    // ══ H / I. never records a deploy SHA ═══════════════════════════════════

    public function test_it_never_records_a_deploy_sha(): void
    {
        $executable = $this->executableSource($this->servingScript());

        foreach (['record_deploy_sha', 'resolve_deploy_sha', 'deploy_sha_file', 'current-deploy-sha', 'DEPLOY_SHA'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $executable,
                "start-serving.sh must not touch the deploy SHA record ('{$forbidden}') — repinning is a deployment event"
            );
        }
    }

    public function test_start_production_remains_the_only_deploy_sha_writer(): void
    {
        $production = $this->executableSource((string) file_get_contents(base_path('deploy/start-production.sh')));

        $this->assertStringContainsString(
            'record_deploy_sha',
            $production,
            'Precondition: the explicit deployment path is the SHA writer'
        );

        $writers = [];

        foreach (['deploy/start-production.sh', 'deploy/start-serving.sh', 'deploy/scheduler.sh', 'scripts/post-merge.sh'] as $relative) {
            $path = base_path($relative);

            if (! is_file($path)) {
                continue;
            }

            if (str_contains($this->executableSource((string) file_get_contents($path)), 'record_deploy_sha')) {
                $writers[] = $relative;
            }
        }

        $this->assertSame(
            ['deploy/start-production.sh'],
            $writers,
            'Exactly one script may write the deploy SHA record'
        );
    }

    // ══ J. ready -> serves ══════════════════════════════════════════════════

    public function test_a_ready_schema_reaches_the_server_exactly_once(): void
    {
        $result = $this->runStartServing(0);

        $this->assertSame(0, $result['code'], "start-serving.sh must succeed when ready. Output:\n{$result['output']}");

        $this->assertStringContainsString('READINESS_INVOKED', $result['log']);
        $this->assertStringContainsString('SERVE_INVOKED', $result['log']);
        $this->assertSame(
            1,
            substr_count($result['log'], 'SERVE_INVOKED'),
            'The server must be invoked exactly once'
        );

        $this->assertStringNotContainsString(
            'MIGRATE_INVOKED',
            $result['log'],
            'A restart must never migrate'
        );

        // Order: readiness strictly before serve.
        $this->assertLessThan(
            strpos($result['log'], 'SERVE_INVOKED'),
            strpos($result['log'], 'READINESS_INVOKED'),
            'Readiness must be checked before the server is started'
        );
    }

    // ══ K / L / M / N. every non-zero readiness refuses to serve ════════════

    /**
     * @dataProvider refusingReadinessCodes
     */
    public function test_a_non_ready_schema_refuses_to_serve(int $readinessExit, string $label): void
    {
        $result = $this->runStartServing($readinessExit);

        $this->assertNotSame(
            0,
            $result['code'],
            "start-serving.sh must exit non-zero when readiness is {$label}. Output:\n{$result['output']}"
        );

        $this->assertStringContainsString('READINESS_INVOKED', $result['log'], 'The readiness check must have run');

        $this->assertStringNotContainsString(
            'SERVE_INVOKED',
            $result['log'],
            "The server must NOT be started when readiness is {$label}"
        );

        $this->assertStringNotContainsString(
            'MIGRATE_INVOKED',
            $result['log'],
            'Refusing to serve must never escalate into migrating'
        );

        $this->assertFileDoesNotExist(
            $result['state'] . '/current-deploy-sha',
            'A refused restart must not write a deploy SHA'
        );

        $this->assertNotSame(
            '',
            trim($result['output']),
            'A refusal must say why'
        );
    }

    public static function refusingReadinessCodes(): array
    {
        return [
            'pending (1)'         => [1, 'pending'],
            'undetermined (2)'    => [2, 'undetermined'],
            'undefined code (7)'  => [7, 'an undefined code'],
            'signal-ish code (99)'=> [99, 'an unexpected code'],
        ];
    }

    public function test_a_ready_restart_also_writes_no_deploy_sha(): void
    {
        $result = $this->runStartServing(0);

        $this->assertFileDoesNotExist(
            $result['state'] . '/current-deploy-sha',
            'Even a successful restart must not repin the recorded deploy SHA'
        );
    }

    // ══ O / P / Q / R. process shape ════════════════════════════════════════

    public function test_the_server_is_exec_and_never_backgrounded(): void
    {
        $lines      = $this->executableLines($this->servingScript());
        $serveLines = array_values(array_filter($lines, static fn (string $l): bool => str_contains($l, 'artisan serve')));

        $this->assertCount(1, $serveLines, 'There must be exactly one server invocation');

        $this->assertStringStartsWith(
            'exec ',
            $serveLines[0],
            'The server must replace this shell so it receives signals from the supervisor directly'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/&\s*$/',
            $serveLines[0],
            'The server must never be backgrounded'
        );
    }

    public function test_it_contains_no_process_killing_preamble_and_no_sleep(): void
    {
        $executable = $this->executableSource($this->servingScript());

        foreach (['kill ', 'pkill', 'lsof', 'fuser', 'killall'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $executable,
                "start-serving.sh must not try to clear port 5000 itself ('{$forbidden}') — the supervisor owns the process"
            );
        }

        $this->assertDoesNotMatchRegularExpression(
            '/(^|\s)sleep\s/m',
            $executable,
            'A sleep is a race waiting to be lost, not a synchronisation primitive'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/(^|\s)nohup(\s|$)/m',
            $executable,
            'The server must not be detached from the supervisor'
        );
    }

    // ══ S. PHP_INI_SCAN_DIR follows the script, not the caller ══════════════

    public function test_php_ini_scan_dir_resolves_under_the_worktree_holding_the_script(): void
    {
        // A copy of deploy/ placed in a directory named like the canonical
        // production worktree. Running the copy from an unrelated cwd proves the
        // path is derived from the SCRIPT's location, not from $PWD.
        $root = $this->tempDir('canonical') . '/production-serve';
        mkdir($root, 0700, true);

        exec(sprintf('cp -R %s %s', escapeshellarg(base_path('deploy')), escapeshellarg($root . '/deploy')));

        $this->assertFileExists($root . '/deploy/start-serving.sh');

        $result = $this->runStartServing(0, $root . '/deploy/start-serving.sh');

        $this->assertStringContainsString(
            'PHP_INI_SCAN_DIR ' . $root . '/deploy/php',
            $result['log'],
            'PHP_INI_SCAN_DIR must resolve to <worktree>/deploy/php regardless of the caller\'s cwd'
        );
    }

    // ══ T / U / V / W. the .replit wiring ═══════════════════════════════════

    public function test_the_port_5000_workflow_invokes_the_serving_script(): void
    {
        $args = $this->portFiveThousandWorkflowArgs();

        $this->assertStringContainsString(
            'deploy/start-serving.sh',
            $args,
            'The port-5000 workflow must go through the serve-only script'
        );
    }

    public function test_the_port_5000_workflow_no_longer_starts_a_server_directly(): void
    {
        $args = $this->portFiveThousandWorkflowArgs();

        $this->assertDoesNotMatchRegularExpression(
            '/artisan\s+serve/',
            $args,
            'The workflow must not bypass the readiness gate with a direct server command'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/php\s+-S\b/',
            $args,
            'The workflow must not use the PHP built-in server directly'
        );

        foreach (['kill ', 'lsof', 'sleep '] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $args,
                "The live-only '{$forbidden}' preamble must not be committed"
            );
        }
    }

    public function test_the_deployment_entry_point_is_still_the_production_script(): void
    {
        $this->assertMatchesRegularExpression(
            '/^run\s*=\s*\[\s*"bash"\s*,\s*"deploy\/start-production\.sh"\s*\]\s*$/m',
            $this->replit(),
            'The [deployment] run command must remain deploy/start-production.sh'
        );
    }

    public function test_the_post_merge_hook_target_is_unchanged(): void
    {
        $this->assertMatchesRegularExpression(
            '/^path\s*=\s*"scripts\/post-merge\.sh"\s*$/m',
            $this->replit(),
            'The [postMerge] target must remain scripts/post-merge.sh'
        );
    }

    public function test_start_production_remains_the_sole_migration_owner(): void
    {
        $owners = [];

        foreach ([
            'deploy/start-production.sh',
            'deploy/start-serving.sh',
            'deploy/scheduler.sh',
            'scripts/post-merge.sh',
        ] as $relative) {
            $path = base_path($relative);

            if (! is_file($path)) {
                continue;
            }

            if (preg_match('/artisan\s+migrate(\s|:|$)/m', $this->executableSource((string) file_get_contents($path))) === 1) {
                $owners[] = $relative;
            }
        }

        $this->assertSame(
            ['deploy/start-production.sh'],
            $owners,
            'Exactly one script may run production migrations'
        );
    }

    // ══ shared-lock contention ══════════════════════════════════════════════

    /**
     * Scenario A: a deployment holds the lock; a restart must not enter the
     * readiness/serve section until it is released.
     *
     * Deterministic — the holder is a `flock` on the same file with no timing
     * assumptions, and the restart is given a zero-second timeout so it either
     * enters immediately or is refused.
     */
    public function test_a_restart_cannot_enter_while_a_deployment_holds_the_lock(): void
    {
        $bin   = $this->fakePhpBin();
        $state = $this->tempDir('contend-state');
        $log   = $this->tempDir('contend-log') . '/calls.log';
        touch($log);

        $script = $this->servingScriptPath();
        $path   = $bin . ':' . (string) getenv('PATH');

        [$code, $out] = $this->bash(<<<SH
        set -uo pipefail
        export PATH="{$path}"
        export FAKE_LOG="{$log}"
        export FAKE_READINESS_EXIT=0
        export DEPLOY_STATE_DIR="{$state}"

        mkdir -p "{$state}"

        # A holder that owns the lock for the duration of the blocked attempt.
        flock "{$state}/deploy.lock" -c 'echo HOLDER_IN; while [ ! -f "{$state}/go" ]; do sleep 0.02; done; echo HOLDER_OUT' &
        HOLDER=\$!

        # Wait until the holder actually owns it — no fixed sleep.
        for _ in \$(seq 1 200); do
            if flock -n "{$state}/deploy.lock" -c true; then sleep 0.02; else break; fi
        done

        # Zero-second timeout: refused immediately if the lock is genuinely held.
        DEPLOY_LOCK_TIMEOUT=0 bash "{$script}" > "{$state}/blocked.out" 2>&1
        echo "BLOCKED_EXIT=\$?"

        touch "{$state}/go"
        wait \$HOLDER

        # Now the lock is free, the same script must proceed.
        bash "{$script}" > "{$state}/free.out" 2>&1
        echo "FREE_EXIT=\$?"
        SH);

        $this->assertStringContainsString('HOLDER_IN', $out, "The holder must take the lock. Output:\n{$out}");
        $this->assertMatchesRegularExpression('/BLOCKED_EXIT=[1-9]/', $out, "A restart must be refused while the lock is held. Output:\n{$out}");
        $this->assertStringContainsString('FREE_EXIT=0', $out, "The restart must proceed once the lock is released. Output:\n{$out}");

        $log = (string) file_get_contents($log);

        $this->assertSame(
            1,
            substr_count($log, 'SERVE_INVOKED'),
            'Only the unblocked attempt may reach the server — the blocked one must not have served'
        );
    }

    /** Scenario B: two restarts cannot both be inside the protected section. */
    public function test_two_restarts_cannot_both_enter_the_protected_section(): void
    {
        $state = $this->tempDir('contend2-state');

        [$code, $out] = $this->bash(<<<SH
        set -uo pipefail
        export DEPLOY_STATE_DIR="{$state}"
        export DEPLOY_LOCK_TIMEOUT=0
        source "{$this->helperPath()}"

        if acquire_deploy_lock; then echo "A_ACQUIRED"; else echo "A_FAILED"; exit 1; fi

        if flock -n "\$(deploy_lock_file)" -c 'echo B_ENTERED'; then
            echo "B_ACQUIRED_UNEXPECTEDLY"
        else
            echo "B_REFUSED"
        fi

        release_deploy_lock

        if flock -n "\$(deploy_lock_file)" -c 'echo C_ENTERED'; then
            echo "C_ACQUIRED_AFTER_RELEASE"
        else
            echo "C_STILL_BLOCKED"
        fi
        SH);

        $this->assertStringContainsString('A_ACQUIRED', $out, "Output:\n{$out}");
        $this->assertStringContainsString('B_REFUSED', $out, "Two restarts must not share the protected section. Output:\n{$out}");
        $this->assertStringNotContainsString('B_ENTERED', $out);
        $this->assertStringContainsString('C_ACQUIRED_AFTER_RELEASE', $out, 'The lock must be released, not leaked');
        $this->assertSame(0, $code, "Output:\n{$out}");
    }

    private function helperPath(): string
    {
        $path = base_path('deploy/lib/deploy-state.sh');

        $this->assertFileExists($path, 'The deploy-state helper must exist');

        return $path;
    }

    /**
     * The lock must be RELEASED before the server takes over.
     *
     * Held across `exec`, the serving process would own the deploy lock for its
     * entire life and no future deployment could ever acquire it — the restart
     * path would have permanently locked out the deployment path.
     */
    public function test_the_lock_is_released_before_the_server_takes_over(): void
    {
        $lines    = $this->executableLines($this->servingScript());
        $release  = null;
        $serve    = null;

        foreach ($lines as $index => $line) {
            if (str_contains($line, 'release_deploy_lock') && $release === null) {
                $release = $index;
            }

            if (str_contains($line, 'artisan serve')) {
                $serve = $index;
            }
        }

        $this->assertNotNull($release, 'start-serving.sh must release the deploy lock');
        $this->assertNotNull($serve, 'start-serving.sh must invoke the server');
        $this->assertLessThan($serve, $release, 'The lock must be released before exec');
    }

    // ══ process topology / signals ══════════════════════════════════════════

    /**
     * After `exec`, no wrapper shell may remain between the supervisor and the
     * server: the launched PID must BE the final process, with no children. A
     * surviving wrapper is what swallows SIGTERM and leaves orphans behind.
     */
    public function test_the_launched_pid_becomes_the_final_process_and_dies_on_sigterm(): void
    {
        $bin   = $this->fakePhpBin();
        $state = $this->tempDir('signal-state');
        $log   = $this->tempDir('signal-log') . '/calls.log';
        touch($log);

        $script = $this->servingScriptPath();
        $path   = $bin . ':' . (string) getenv('PATH');
        $marker = $state . '/serving.marker';

        [$code, $out] = $this->bash(<<<SH
        set -uo pipefail
        export PATH="{$path}"
        export FAKE_LOG="{$log}"
        export FAKE_READINESS_EXIT=0
        export DEPLOY_STATE_DIR="{$state}"
        # Stand in for the real server: a harmless long-running process, exec'd
        # so the chain is identical in shape to production.
        export FAKE_SERVE_EXEC="sleep 120"

        bash "{$script}" &
        TOP=\$!

        for _ in \$(seq 1 200); do
            if grep -q SERVE_INVOKED "{$log}" 2>/dev/null; then break; fi
            sleep 0.05
        done

        # Give the final exec a moment to replace the image, bounded.
        for _ in \$(seq 1 200); do
            COMM="\$(cat /proc/\$TOP/comm 2>/dev/null || echo GONE)"
            if [ "\$COMM" = "sleep" ]; then break; fi
            sleep 0.05
        done

        echo "TOP_COMM=\$(cat /proc/\$TOP/comm 2>/dev/null || echo GONE)"
        echo "TOP_CHILDREN=\$(pgrep -P \$TOP 2>/dev/null | wc -l)"

        kill -TERM \$TOP 2>/dev/null || true

        for _ in \$(seq 1 200); do
            if ! kill -0 \$TOP 2>/dev/null; then break; fi
            sleep 0.05
        done

        if kill -0 \$TOP 2>/dev/null; then echo "STILL_ALIVE"; else echo "EXITED"; fi
        echo "ORPHANS=\$(pgrep -f 'sleep 120' 2>/dev/null | wc -l)"
        SH);

        $this->assertStringContainsString(
            'TOP_COMM=sleep',
            $out,
            "After exec the launched PID must BE the server process — no wrapper shell may remain. Output:\n{$out}"
        );

        $this->assertStringContainsString(
            'TOP_CHILDREN=0',
            $out,
            "The final process must have no background children. Output:\n{$out}"
        );

        $this->assertStringContainsString(
            'EXITED',
            $out,
            "SIGTERM to the launched PID must terminate the server. Output:\n{$out}"
        );

        $this->assertStringContainsString(
            'ORPHANS=0',
            $out,
            "No orphaned server process may survive. Output:\n{$out}"
        );
    }
}
