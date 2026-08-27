<?php

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * Production startup hardening: an exclusive deploy lock and a recorded deploy SHA.
 *
 * WHY A LOCK
 * ----------
 * This application is on Laravel 8, which has no `migrate --isolated` — the
 * built-in migration lock arrived in Laravel 9. Until now, concurrency safety
 * rested entirely on exactly one script owning migrations. That is a rule, not a
 * mechanism: it stops a SECOND script from migrating, but nothing stops the SAME
 * script running twice — a redeploy overlapping a restart, or two deploys
 * triggered close together. `flock` is the missing mechanism.
 *
 * WHY RECORD THE SHA
 * ------------------
 * "What is actually running in production?" had no answer that did not involve
 * inspecting a live process. Recording it makes rollback a lookup rather than an
 * archaeology exercise.
 *
 * WHY A SOURCEABLE HELPER
 * -----------------------
 * The lock and SHA logic lives in `deploy/lib/deploy-state.sh` so it can be
 * exercised directly. The alternative — asserting on `start-production.sh` as
 * text — cannot prove a lock actually excludes anything, and running the start
 * script itself would migrate a database and bind port 5000. The helper lets
 * these tests run the real code against temporary directories and throwaway git
 * repositories.
 *
 * Nothing here migrates, touches heliumdb, binds a port, or reaches the network.
 */
class DeployStartupHardeningTest extends TestCase
{
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

    private function startScript(): string
    {
        $path = base_path('deploy/start-production.sh');

        $this->assertFileExists($path, 'The production start script must exist');

        return (string) file_get_contents($path);
    }

    private function helperPath(): string
    {
        $path = base_path('deploy/lib/deploy-state.sh');

        $this->assertFileExists($path, 'The deploy-state helper must exist');

        return $path;
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

    /** Run a bash snippet and return [exitCode, output]. */
    private function bash(string $script): array
    {
        $file = $this->tempDir('deploy-bash') . '/run.sh';
        file_put_contents($file, $script);

        $output = [];
        $code   = 0;
        exec('bash ' . escapeshellarg($file) . ' 2>&1', $output, $code);

        return [$code, implode("\n", $output)];
    }

    // ── A / B: the lock exists and is persistent ────────────────────────────

    public function test_the_start_script_acquires_an_exclusive_deploy_lock(): void
    {
        $executable = implode("\n", $this->executableLines($this->startScript()));

        $this->assertStringContainsString(
            'acquire_deploy_lock',
            $executable,
            'The start script must acquire the deploy lock before its critical section'
        );
    }

    public function test_the_deploy_lock_and_sha_record_live_outside_tmp(): void
    {
        $helper = (string) file_get_contents($this->helperPath());

        $this->assertStringContainsString(
            '/home/runner/workspace/.ops-backups',
            $helper,
            'Deploy state must default to a persistent location'
        );

        foreach ($this->executableLines($helper) as $line) {
            $this->assertDoesNotMatchRegularExpression(
                '#(?<![\w/])/tmp/#',
                $line,
                "Deploy state must never live under /tmp — it does not survive a container restart: {$line}"
            );
        }
    }

    // ── C: a second deployment is genuinely excluded ────────────────────────

    public function test_a_second_deployment_cannot_enter_the_critical_section(): void
    {
        $state = $this->tempDir('deploy-lock-state');

        // Deterministic, no sleeps: process 1 holds the lock on fd 9 for the
        // lifetime of the shell; process 2 attempts the same lock non-blocking.
        [$code, $out] = $this->bash(<<<SH
        set -uo pipefail
        export DEPLOY_STATE_DIR=" {$state}"
        export DEPLOY_STATE_DIR="{$state}"
        export DEPLOY_LOCK_TIMEOUT=0
        source {$this->helperPath()}

        if acquire_deploy_lock; then echo "P1_ACQUIRED"; else echo "P1_FAILED"; exit 1; fi

        # A second, independent process must be refused while P1 holds it.
        if flock -n "\$(deploy_lock_file)" -c 'echo P2_ENTERED'; then
            echo "P2_ACQUIRED_UNEXPECTEDLY"
        else
            echo "P2_REFUSED"
        fi

        release_deploy_lock

        if flock -n "\$(deploy_lock_file)" -c 'echo P3_ENTERED'; then
            echo "P3_ACQUIRED_AFTER_RELEASE"
        else
            echo "P3_STILL_BLOCKED"
        fi
        SH);

        $this->assertStringContainsString('P1_ACQUIRED', $out, "Process 1 must acquire the lock. Output:\n{$out}");
        $this->assertStringContainsString('P2_REFUSED', $out, "A second deploy must be refused while the lock is held. Output:\n{$out}");
        $this->assertStringNotContainsString('P2_ENTERED', $out, 'A second deploy must never enter the critical section');
        $this->assertStringContainsString('P3_ACQUIRED_AFTER_RELEASE', $out, 'The lock must be released, not leaked');
        $this->assertSame(0, $code, "Lock probe failed. Output:\n{$out}");
    }

    public function test_the_lock_fails_closed_rather_than_waiting_forever(): void
    {
        $helper = (string) file_get_contents($this->helperPath());

        // `flock -w <timeout>` bounds the wait. An unbounded `flock` (no -w/-n)
        // would hang a deploy indefinitely behind a stuck one.
        $this->assertMatchesRegularExpression(
            '/flock\s+(-w\s+"?\$?\{?DEPLOY_LOCK_TIMEOUT|-n)/',
            $helper,
            'The lock must be bounded — never an indefinite wait'
        );
    }

    // ── D / E / F: the SHA record ───────────────────────────────────────────

    public function test_the_deploy_sha_comes_from_git_rev_parse_head(): void
    {
        $this->assertStringContainsString(
            'git rev-parse HEAD',
            (string) file_get_contents($this->helperPath()),
            'The recorded SHA must come from the repository, not be hand-supplied'
        );
    }

    public function test_a_temporary_repository_head_is_recorded_exactly(): void
    {
        $state = $this->tempDir('deploy-sha-state');
        $repo  = $this->tempDir('deploy-sha-repo');

        [$code, $out] = $this->bash(<<<SH
        set -uo pipefail
        cd {$repo}
        git init -q .
        git config user.email t@example.com
        git config user.name Test
        echo x > f.txt
        git add f.txt
        git commit -qm "first"
        EXPECTED="\$(git rev-parse HEAD)"

        export DEPLOY_STATE_DIR="{$state}"
        source {$this->helperPath()}

        record_deploy_sha || { echo "RECORD_FAILED"; exit 1; }

        echo "EXPECTED=\$EXPECTED"
        echo "RECORDED=\$(cat "\$(deploy_sha_file)")"
        echo "MODE=\$(stat -c%a "\$(deploy_sha_file)")"
        SH);

        $this->assertSame(0, $code, "SHA record run failed. Output:\n{$out}");

        preg_match('/EXPECTED=([0-9a-f]{40})/', $out, $expected);
        preg_match('/RECORDED=([0-9a-f]{40})/', $out, $recorded);

        $this->assertNotEmpty($expected, "No expected SHA captured. Output:\n{$out}");
        $this->assertNotEmpty($recorded, "No SHA was recorded. Output:\n{$out}");
        $this->assertSame($expected[1], $recorded[1], 'The recorded SHA must match the repository HEAD exactly');

        $this->assertMatchesRegularExpression('/MODE=600/', $out, 'The SHA record must be private (0600)');
    }

    public function test_recording_fails_closed_when_git_state_is_unavailable(): void
    {
        $state  = $this->tempDir('deploy-sha-nogit-state');
        $nonGit = $this->tempDir('deploy-sha-nogit');

        [$code, $out] = $this->bash(<<<SH
        set -uo pipefail
        cd {$nonGit}
        export DEPLOY_STATE_DIR="{$state}"
        export GIT_CEILING_DIRECTORIES="{$nonGit}"
        source {$this->helperPath()}

        if record_deploy_sha; then echo "RECORDED_WITHOUT_GIT"; else echo "FAILED_CLOSED"; fi
        SH);

        $this->assertStringContainsString(
            'FAILED_CLOSED',
            $out,
            "Recording must fail closed when no SHA can be determined. Output:\n{$out}"
        );
        $this->assertStringNotContainsString('RECORDED_WITHOUT_GIT', $out);
    }

    public function test_the_sha_write_is_atomic(): void
    {
        $helper = (string) file_get_contents($this->helperPath());

        // Write to a temp file in the same directory, then rename. A partial
        // read of the record must be impossible.
        $this->assertMatchesRegularExpression(
            '/\bmv\b/',
            $helper,
            'The SHA record must be written atomically via rename, not appended in place'
        );
    }

    // ── ordering: the SHA is not recorded before the deploy has earned it ───

    public function test_the_sha_is_recorded_only_after_migrations_succeed(): void
    {
        $lines = $this->executableLines($this->startScript());

        $migrateAt = null;
        $recordAt  = null;

        foreach ($lines as $i => $line) {
            if ($migrateAt === null && str_contains($line, 'artisan migrate')) {
                $migrateAt = $i;
            }
            if ($recordAt === null && str_contains($line, 'record_deploy_sha')) {
                $recordAt = $i;
            }
        }

        $this->assertNotNull($migrateAt, 'The start script must migrate');
        $this->assertNotNull($recordAt, 'The start script must record the deploy SHA');
        $this->assertGreaterThan(
            $migrateAt,
            $recordAt,
            'The SHA must be recorded only after migrations succeeded — never before'
        );
    }

    // ── the lock must not be held for the life of the server ────────────────

    public function test_the_lock_is_released_before_the_server_takes_over(): void
    {
        $lines = $this->executableLines($this->startScript());

        $releaseAt = null;
        $execAt    = null;

        foreach ($lines as $i => $line) {
            if ($releaseAt === null && str_contains($line, 'release_deploy_lock')) {
                $releaseAt = $i;
            }
            if ($execAt === null && str_starts_with($line, 'exec ')) {
                $execAt = $i;
            }
        }

        $this->assertNotNull($releaseAt, 'The start script must release the deploy lock');
        $this->assertNotNull($execAt, 'The start script must exec the server');
        $this->assertLessThan(
            $execAt,
            $releaseAt,
            'The lock must be released before exec — otherwise the serving process holds it for its whole life '
            . 'and no future deploy could ever acquire it'
        );
    }

    // ── process-supervision semantics must not change ───────────────────────

    public function test_the_server_is_still_exec_and_never_backgrounded(): void
    {
        $lines = $this->executableLines($this->startScript());

        $execServe = array_filter($lines, static fn (string $l): bool => str_starts_with($l, 'exec ') && str_contains($l, 'artisan serve'));

        $this->assertNotEmpty(
            $execServe,
            'The server must remain exec\'d so it stays attached to the supervisor and receives SIGTERM'
        );

        foreach ($lines as $line) {
            if (str_contains($line, 'artisan serve')) {
                $this->assertStringEndsNotWith(
                    '&',
                    $line,
                    'The production server must never be backgrounded — that orphans it from the supervisor'
                );
            }
        }
    }

    // ── invariants this PR must not break ───────────────────────────────────

    public function test_start_production_remains_the_sole_migration_owner(): void
    {
        $this->assertNotEmpty(
            array_filter(
                $this->executableLines($this->startScript()),
                static fn (string $l): bool => (bool) preg_match('/\bartisan\s+migrate(?![\w:-])/', $l)
            ),
            'deploy/start-production.sh must remain the one place production migrations run'
        );
    }

    public function test_the_post_merge_hook_still_does_not_migrate(): void
    {
        $offenders = array_filter(
            $this->executableLines((string) file_get_contents(base_path('scripts/post-merge.sh'))),
            static fn (string $l): bool => (bool) preg_match('/\bartisan\s+migrate(?![\w:-])/', $l)
        );

        $this->assertSame([], array_values($offenders), 'The postMerge hook must still never migrate');
    }

    public function test_every_deploy_shell_script_has_valid_syntax(): void
    {
        foreach (['deploy/start-production.sh', 'deploy/lib/deploy-state.sh', 'deploy/scheduler.sh', 'scripts/post-merge.sh'] as $rel) {
            $out  = [];
            $code = 0;
            exec('bash -n ' . escapeshellarg(base_path($rel)) . ' 2>&1', $out, $code);

            $this->assertSame(0, $code, "{$rel} must be valid bash: " . implode("\n", $out));
        }
    }
}
