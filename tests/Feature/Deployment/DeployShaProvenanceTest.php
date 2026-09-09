<?php

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * The recorded release SHA must be PROVEN or ABSENT — never invented.
 *
 * THE PROBLEM THIS ADDRESSES
 * --------------------------
 * `record_deploy_sha` resolves the running revision from `DEPLOY_SHA`, else from
 * `git rev-parse HEAD`. `deploy/start-production.sh` then treated ANY failure as
 * fatal:
 *
 *     if ! record_deploy_sha; then ... exit 1; fi
 *
 * That conflates two unrelated situations. "This environment is broken" deserves
 * a refusal. "This snapshot shipped without `.git`, so I cannot name the release"
 * does not — by that point `deploy:preflight`, `deploy:require-flags` and
 * `migrate` have all already run and passed, so the release is healthy and the
 * only thing missing is a breadcrumb. Refusing there turns a missing note into an
 * outage, and it would do so on the FIRST publish, after migrations had run.
 *
 * Whether a Reserved VM deployment snapshot contains `.git` is a platform detail
 * we do not control, and Replit exposes no commit SHA to the runtime — `REPL_ID`
 * identifies the repl, not the revision, so reaching for it would record a
 * fabrication. `DEPLOY_SHA` remains the way to make this deterministic.
 *
 * WHAT IS PINNED HERE
 * -------------------
 * The three-way exit status, and the invariant that matters more than any of it:
 * a SHA is written only when it was proven. Nothing in this file relaxes the
 * refusal for a genuine write failure.
 *
 * Nothing here migrates, touches heliumdb, binds a port, or reaches the network.
 */
class DeployShaProvenanceTest extends TestCase
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

    private function helperPath(): string
    {
        $path = base_path('deploy/lib/deploy-state.sh');

        $this->assertFileExists($path, 'The deploy-state helper must exist');

        return $path;
    }

    private function bash(string $script): array
    {
        $file = $this->tempDir('deploy-sha-bash') . '/run.sh';
        file_put_contents($file, $script);

        $output = [];
        $code   = 0;
        exec('bash ' . escapeshellarg($file) . ' 2>&1', $output, $code);

        return [$code, implode("\n", $output)];
    }

    // ── the three-way status ────────────────────────────────────────────────

    /** A snapshot without git metadata reports 2 — "unknowable", not "broken". */
    public function test_a_snapshot_without_git_reports_the_unprovable_status(): void
    {
        $state  = $this->tempDir('sha-nogit-state');
        $nonGit = $this->tempDir('sha-nogit');

        [, $out] = $this->bash(<<<SH
        set -uo pipefail
        cd {$nonGit}
        export DEPLOY_STATE_DIR="{$state}"
        export GIT_CEILING_DIRECTORIES="{$nonGit}"
        unset DEPLOY_SHA
        source {$this->helperPath()}

        record_deploy_sha
        echo "STATUS=\$?"
        SH);

        $this->assertStringContainsString('STATUS=2', $out, "Expected the unprovable status. Output:\n{$out}");
    }

    /** And it writes nothing at all — no placeholder, no empty file. */
    public function test_nothing_is_written_when_the_sha_cannot_be_proven(): void
    {
        $state  = $this->tempDir('sha-nowrite-state');
        $nonGit = $this->tempDir('sha-nowrite');

        [, $out] = $this->bash(<<<SH
        set -uo pipefail
        cd {$nonGit}
        export DEPLOY_STATE_DIR="{$state}"
        export GIT_CEILING_DIRECTORIES="{$nonGit}"
        unset DEPLOY_SHA
        source {$this->helperPath()}

        record_deploy_sha || true
        if [ -e "{$state}/current-deploy-sha" ]; then echo "FILE_EXISTS"; else echo "NO_FILE"; fi
        SH);

        $this->assertStringContainsString('NO_FILE', $out, "A SHA file must not be created. Output:\n{$out}");
        $this->assertStringNotContainsString('FILE_EXISTS', $out);
    }

    /** An explicit DEPLOY_SHA is trusted and recorded verbatim. */
    public function test_an_explicit_deploy_sha_is_recorded_exactly(): void
    {
        $state  = $this->tempDir('sha-explicit-state');
        $nonGit = $this->tempDir('sha-explicit');
        $sha    = '0123456789abcdef0123456789abcdef01234567';

        [, $out] = $this->bash(<<<SH
        set -uo pipefail
        cd {$nonGit}
        export DEPLOY_STATE_DIR="{$state}"
        export GIT_CEILING_DIRECTORIES="{$nonGit}"
        export DEPLOY_SHA="{$sha}"
        source {$this->helperPath()}

        record_deploy_sha
        echo "STATUS=\$?"
        echo "RECORDED=\$(cat "{$state}/current-deploy-sha")"
        SH);

        $this->assertStringContainsString('STATUS=0', $out, "Output:\n{$out}");
        $this->assertStringContainsString("RECORDED={$sha}", $out, "Output:\n{$out}");
    }

    /** A malformed DEPLOY_SHA is refused rather than recorded. */
    public function test_a_malformed_deploy_sha_is_not_recorded(): void
    {
        $state  = $this->tempDir('sha-bad-state');
        $nonGit = $this->tempDir('sha-bad');

        [, $out] = $this->bash(<<<SH
        set -uo pipefail
        cd {$nonGit}
        export DEPLOY_STATE_DIR="{$state}"
        export GIT_CEILING_DIRECTORIES="{$nonGit}"
        export DEPLOY_SHA="not-a-sha"
        source {$this->helperPath()}

        record_deploy_sha
        echo "STATUS=\$?"
        if [ -e "{$state}/current-deploy-sha" ]; then echo "FILE_EXISTS"; else echo "NO_FILE"; fi
        SH);

        $this->assertStringContainsString('STATUS=2', $out, "Output:\n{$out}");
        $this->assertStringContainsString('NO_FILE', $out, "A malformed SHA must never be written. Output:\n{$out}");
    }

    // ── the caller's behaviour ──────────────────────────────────────────────

    /**
     * The start script must distinguish the two statuses. Asserted as structure,
     * because running the real script would migrate a database and bind a port.
     */
    public function test_the_start_script_separates_unprovable_from_broken(): void
    {
        $script = (string) file_get_contents(base_path('deploy/start-production.sh'));

        $this->assertStringContainsString(
            'record_deploy_sha',
            $script,
            'The start script must still record the deploy SHA'
        );

        $this->assertMatchesRegularExpression(
            '/case\s+"\$deploy_sha_status"\s+in/',
            $script,
            'The start script must branch on the record_deploy_sha status rather than treating every failure alike'
        );

        $this->assertMatchesRegularExpression(
            '/refusing to start/',
            $script,
            'A genuine write failure must still refuse to start'
        );
    }

    /** The unprovable path must be loud: serving unrecorded cannot be silent. */
    public function test_the_unprovable_path_warns_explicitly(): void
    {
        $script = (string) file_get_contents(base_path('deploy/start-production.sh'));

        $this->assertMatchesRegularExpression(
            '/NO ROLLBACK POINT WAS RECORDED/',
            $script,
            'Serving without a recorded release SHA must say so unmistakably'
        );

        $this->assertMatchesRegularExpression(
            '/DEPLOY_SHA/',
            $script,
            'The warning must name the variable that fixes it permanently'
        );
    }

    /** No fabricated fallback may creep into the resolver. */
    public function test_no_fabricated_revision_source_is_used(): void
    {
        $helper = (string) file_get_contents($this->helperPath());

        foreach (['REPL_ID', 'REPLIT_DEPLOYMENT', 'unknown', 'HEAD-unknown'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $helper,
                "deploy-state.sh must not fall back to {$forbidden} — it would record a revision that was never proven"
            );
        }
    }

    // ── the state directory ─────────────────────────────────────────────────

    /**
     * The default state location must be derived from the release on disk, not
     * hardcoded to the workspace checkout — a Reserved VM unpacks elsewhere, and
     * `deploy_state_dir` is reached by `acquire_deploy_lock` before anything else.
     */
    public function test_the_default_state_dir_is_not_a_hardcoded_workspace_path(): void
    {
        $helper = (string) file_get_contents($this->helperPath());

        $this->assertDoesNotMatchRegularExpression(
            '/DEPLOY_STATE_DIR:-\/home\/runner\/workspace/',
            $helper,
            'The default deploy-state directory must not hardcode the workspace path'
        );

        $this->assertStringContainsString(
            'BASH_SOURCE',
            $helper,
            'The default must be resolved from the helper\'s own location'
        );
    }

    /** And it must still resolve to the repository root beside the release. */
    public function test_the_default_state_dir_resolves_beside_the_release(): void
    {
        [, $out] = $this->bash(<<<SH
        set -uo pipefail
        unset DEPLOY_STATE_DIR
        source {$this->helperPath()}
        deploy_repo_root
        SH);

        $this->assertSame(
            base_path(),
            trim($out),
            "deploy_repo_root must resolve to the repository root. Output:\n{$out}"
        );
    }
}
