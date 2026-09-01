<?php

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * The port-5000 workflow must serve from ONE durable, canonical worktree.
 *
 * WHY THIS EXISTS
 * ---------------
 * The committed workflow served from `$PWD` — whatever directory the supervisor
 * happened to start in, i.e. the repository root, which is a development
 * workspace that routinely sits on a feature branch. Meanwhile the live `.replit`
 * on disk had been hand-edited to `cd` into
 * `.worktrees/postmerge-validate-current-main`, a worktree whose NAME declares it
 * a transient post-merge validation tree.
 *
 * Neither is a production serving target:
 *
 *   - `$PWD` follows whatever branch someone last checked out at the root.
 *   - A validation worktree is, by its name and purpose, disposable. It was not
 *     git-locked, and it sits among dozens of sibling worktrees; one cleanup pass
 *     over stale validation trees would have deleted production.
 *   - The hand edit lived only on disk. It was in no commit, so nothing reviewed
 *     it and nothing would restore it.
 *
 * This pins the canonical path in git, where it is reviewable and durable.
 *
 * WHAT THE WORKFLOW NOW INVOKES
 * -----------------------------
 * Pointing the workflow at a durable path was only half the problem. The other
 * half is what it RUNS there: a bare `php artisan serve` serves whatever schema
 * it finds, so a supervisor restart could bring the application up against a
 * schema its code no longer matches. The workflow therefore goes through
 * `deploy/start-serving.sh`, which verifies readiness and refuses rather than
 * improvises. Its behaviour is covered by DeployStartServingTest; what is
 * asserted HERE is the wiring — that the committed `.replit` actually routes
 * through it, and that no direct server command remains to bypass it.
 *
 * WHAT THIS DOES NOT DO
 * ---------------------
 * It does not create `production-serve`, promote anything, or switch live
 * traffic. It asserts what the committed configuration TARGETS. The live switch
 * is a separate, controlled deployment event.
 *
 * NOT A COMMENT SCAN
 * ------------------
 * Every assertion reads the real `.replit` and, where structure matters, the
 * parsed TOML rather than a substring of the file.
 */
class CanonicalServingWorktreeTest extends TestCase
{
    /** The one worktree production may serve from. */
    private const CANONICAL_PATH = '/home/runner/workspace/.worktrees/production-serve';

    /**
     * Serving targets that must never appear in the port-5000 workflow again.
     * Each is a real path this workflow has pointed at or could plausibly be
     * repointed at: a transient validation tree, and two stale runtime trees.
     */
    private const FORBIDDEN_PATHS = [
        'postmerge-validate-current-main',
        'integration-v2-serve',
        'runtime-main',
    ];

    private function replit(): string
    {
        $path = base_path('.replit');

        $this->assertFileExists($path, '.replit must be readable');

        return (string) file_get_contents($path);
    }

    /**
     * The `args` of the shell task in the workflow that binds port 5000.
     *
     * Located by `waitForPort = 5000` rather than by workflow name, so renaming
     * "Laravel Server" cannot make this test silently inspect nothing.
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

    // ── the regression ──────────────────────────────────────────────────────

    public function test_the_port_5000_workflow_serves_from_the_canonical_worktree(): void
    {
        $args = $this->portFiveThousandWorkflowArgs();

        $this->assertStringContainsString(
            self::CANONICAL_PATH,
            $args,
            'The port-5000 workflow must serve from the canonical production worktree'
        );
    }

    public function test_the_port_5000_workflow_references_no_legacy_serving_path(): void
    {
        $args = $this->portFiveThousandWorkflowArgs();

        foreach (self::FORBIDDEN_PATHS as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $args,
                "The port-5000 workflow must not serve from '{$forbidden}' — it is not a durable production target"
            );
        }
    }

    public function test_the_port_5000_workflow_goes_through_the_serve_only_script(): void
    {
        $args = $this->portFiveThousandWorkflowArgs();

        $this->assertStringContainsString(
            'deploy/start-serving.sh',
            $args,
            'The port-5000 workflow must start the server through the serve-only script'
        );

        $this->assertFileExists(
            base_path('deploy/start-serving.sh'),
            'The script the workflow names must actually exist in the repository'
        );
    }

    /**
     * Nothing in the workflow may start a server directly.
     *
     * A direct command is not merely redundant — it is a way past the readiness
     * gate, which is the entire reason the workflow was rewired.
     */
    public function test_the_port_5000_workflow_starts_no_server_directly(): void
    {
        $args = $this->portFiveThousandWorkflowArgs();

        $this->assertDoesNotMatchRegularExpression(
            '/artisan\s+serve/',
            $args,
            'The workflow must not invoke the server directly and bypass the readiness gate'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/php\s+-S\b/',
            $args,
            'The workflow must not use the PHP built-in server directly'
        );
    }

    /**
     * The live-only preamble must stay out of git.
     *
     * The hand-edited file on disk cleared port 5000 with `kill $(lsof -ti :5000)`
     * and then slept. That races the supervisor, which is itself responsible for
     * stopping the previous process, and a sleep is a race waiting to be lost
     * rather than a synchronisation primitive.
     */
    public function test_the_workflow_carries_no_live_only_process_preamble(): void
    {
        $args = $this->portFiveThousandWorkflowArgs();

        foreach (['kill ', 'lsof', 'pkill', 'fuser', 'sleep '] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $args,
                "The live-only '{$forbidden}' preamble must not be committed"
            );
        }
    }

    // ── things this PR must not disturb ─────────────────────────────────────

    public function test_the_deployment_still_runs_the_production_start_script(): void
    {
        $this->assertMatchesRegularExpression(
            '/^run\s*=\s*\[\s*"bash"\s*,\s*"deploy\/start-production\.sh"\s*\]\s*$/m',
            $this->replit(),
            'The [deployment] run command must remain the production start script'
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

    public function test_install_before_run_stays_disabled_as_a_boolean(): void
    {
        // A quoted "true" is a string to the platform and would silently re-enable
        // installs, so the literal must stay unquoted.
        $this->assertMatchesRegularExpression(
            '/^disableInstallBeforeRun\s*=\s*true\s*$/m',
            $this->replit(),
            'disableInstallBeforeRun must remain an unquoted boolean true'
        );
    }

    public function test_the_production_port_mapping_is_intact(): void
    {
        $replit = $this->replit();

        $this->assertMatchesRegularExpression(
            '/\[\[ports\]\]\s*\R\s*localPort\s*=\s*5000\s*\R\s*externalPort\s*=\s*80\s*$/m',
            $replit,
            'Port 5000 must remain mapped to external port 80'
        );
    }

    public function test_no_stray_8080_port_mapping_is_introduced(): void
    {
        // The committed configuration has no 8080 mapping. The hand-edited live
        // file did. This keeps that local-only artefact out of git.
        $this->assertDoesNotMatchRegularExpression(
            '/^\s*localPort\s*=\s*8080\s*$/m',
            $this->replit(),
            'No 8080 port mapping belongs in the committed configuration'
        );
    }
}
