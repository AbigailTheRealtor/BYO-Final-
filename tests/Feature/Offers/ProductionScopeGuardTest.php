<?php

namespace Tests\Feature\Offers;

use Tests\Support\ProductionScopeGuard;
use Tests\TestCase;

/**
 * Tests for the guard that guards the checkpoints.
 *
 * OfferWorkflowReadinessTest::test_no_production_files_were_modified is the mechanism that keeps a
 * task inside its declared blast radius. It was trusted for many checkpoints and it was quietly
 * broken: it asked only `git diff --name-only`, which reports unstaged changes, so the four
 * production files DELETED during the Milestone 2 retirement checkpoint — deleted with `git rm`,
 * which stages the deletion — were never once evaluated by it. It reported an empty set and
 * passed. A guard that passes because it looked in the wrong place is worse than no guard,
 * because it is credited as evidence.
 *
 * So the guard now needs its own tests, and they have to be real: a repository with actual commits
 * in each of the states the guard claims to detect. Asserting against the live repository alone
 * would only ever exercise whatever state this branch happens to be in, and could not show that a
 * STAGED DELETE is caught — the precise case that was missed — without dirtying the real tree.
 * Every structural case therefore runs against a throwaway git repository built in setUp().
 *
 * The live repository is then used for the one thing a synthetic fixture cannot prove: that the
 * four real Checkpoint 2 deletions are genuinely evaluated for the real commit range.
 *
 * @see \Tests\Support\ProductionScopeGuard
 */
class ProductionScopeGuardTest extends TestCase
{
    private string $repo;

    /** Base commit of the synthetic repository — the "before the checkpoint" state. */
    private string $baseSha;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = sys_get_temp_dir() . '/prod-scope-guard-' . bin2hex(random_bytes(8));
        mkdir($this->repo . '/app/Http', 0777, true);
        mkdir($this->repo . '/resources/views', 0777, true);
        mkdir($this->repo . '/tests', 0777, true);

        $this->git('init', '-q', '-b', 'main');
        $this->git('config', 'user.email', 'guard@test.invalid');
        $this->git('config', 'user.name', 'Guard Test');

        // ── the "before" state ───────────────────────────────────────────────
        $this->writeFile('app/Untouched.php', "<?php // never changed\n");
        $this->writeFile('app/ToModify.php', "<?php // original\n");
        $this->writeFile('app/ToDelete.php', "<?php // doomed\n");
        $this->writeFile('app/ToStage.php', "<?php // original\n");
        $this->writeFile('app/ToLeaveDirty.php', "<?php // original\n");
        $this->writeFile('resources/views/old-name.blade.php', "ORIGINAL VIEW BODY\n");
        $this->writeFile('tests/NotProduction.php', "<?php // outside the production dirs\n");
        $this->git('add', '-A');
        $this->git('commit', '-q', '-m', 'base');
        $this->baseSha = $this->gitOut('rev-parse', 'HEAD');

        // ── the checkpoint's own COMMITTED changes ───────────────────────────
        $this->writeFile('app/ToModify.php', "<?php // modified by the checkpoint\n");
        unlink($this->repo . '/app/ToDelete.php');
        $this->writeFile('app/Added.php', "<?php // added by the checkpoint\n");
        // Identical content, so git reports a rename rather than an add/delete pair.
        rename(
            $this->repo . '/resources/views/old-name.blade.php',
            $this->repo . '/resources/views/new-name.blade.php'
        );
        $this->writeFile('tests/NotProduction.php', "<?php // changed, but not production\n");
        $this->git('add', '-A');
        $this->git('commit', '-q', '-m', 'checkpoint');

        // ── STAGED, not committed. This is the bucket the old guard missed. ──
        $this->writeFile('app/ToStage.php', "<?php // staged edit\n");
        $this->git('add', 'app/ToStage.php');

        // A staged DELETE specifically — the exact Checkpoint 2 shape.
        $this->git('rm', '-q', 'app/Untouched.php');

        // ── UNSTAGED ─────────────────────────────────────────────────────────
        $this->writeFile('app/ToLeaveDirty.php', "<?php // dirty working tree\n");

        // ── UNTRACKED ────────────────────────────────────────────────────────
        $this->writeFile('app/BrandNew.php', "<?php // never added\n");
        $this->writeFile('tests/BrandNewTest.php', "<?php // untracked but not production\n");
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->repo);
        parent::tearDown();
    }

    private function guard(): ProductionScopeGuard
    {
        return new ProductionScopeGuard($this->repo);
    }

    /** @return array<string, string> path => "STATUS/origin" */
    private function collected(?string $base): array
    {
        $g = $this->guard();
        $r = $g->collect($base ?? $this->baseSha);

        $map = [];
        foreach ($r['entries'] as $e) {
            $map[$e['path']] = $e['status'] . '/' . $e['origin'];
        }

        return $map;
    }

    // ── The seven required detections ────────────────────────────────────────

    /** 1. a committed MODIFIED path */
    public function test_detects_a_committed_modification(): void
    {
        $this->assertSame('M/committed', $this->collected(null)['app/ToModify.php'] ?? null);
    }

    /**
     * 2. a committed DELETED path.
     *
     * The headline case. Once committed, a deletion is invisible to `git diff`, to
     * `git diff --cached`, and to the working tree — the file simply is not there and nothing is
     * dirty. Only a commit-range comparison can see it.
     */
    public function test_detects_a_committed_deletion(): void
    {
        $this->assertSame('D/committed', $this->collected(null)['app/ToDelete.php'] ?? null);
    }

    /** 3. a committed newly ADDED path */
    public function test_detects_a_committed_addition(): void
    {
        $this->assertSame('A/committed', $this->collected(null)['app/Added.php'] ?? null);
    }

    /** 4. a STAGED modification */
    public function test_detects_a_staged_modification(): void
    {
        $this->assertSame('M/staged', $this->collected(null)['app/ToStage.php'] ?? null);
    }

    /**
     * 4b. a STAGED DELETION — the literal Checkpoint 2 blind spot, reproduced.
     *
     * `git rm` stages the removal, so index and working tree agree and bare `git diff` says
     * nothing. This assertion is the regression test for the bug that motivated this whole file.
     */
    public function test_detects_a_staged_deletion(): void
    {
        $this->assertSame('D/staged', $this->collected(null)['app/Untouched.php'] ?? null);
    }

    /** 5. an UNSTAGED modification — the only case the old guard handled */
    public function test_detects_an_unstaged_modification(): void
    {
        $this->assertSame('M/unstaged', $this->collected(null)['app/ToLeaveDirty.php'] ?? null);
    }

    /** An untracked production file, invisible to every form of diff. */
    public function test_detects_an_untracked_production_file(): void
    {
        $this->assertSame('A/untracked', $this->collected(null)['app/BrandNew.php'] ?? null);
    }

    /**
     * A rename must surface BOTH paths.
     *
     * `git diff --name-only` reports only the destination, which would let a protected file be
     * moved out of an allow-listed location while the guard asked only about where it arrived.
     */
    public function test_detects_both_sides_of_a_rename(): void
    {
        $c = $this->collected(null);

        $this->assertArrayHasKey(
            'resources/views/old-name.blade.php',
            $c,
            'The vacated path of a rename must be reported — otherwise a move can launder a protected file.'
        );
        $this->assertArrayHasKey('resources/views/new-name.blade.php', $c);

        foreach (['old-name', 'new-name'] as $side) {
            $this->assertStringStartsWith('R', $c["resources/views/{$side}.blade.php"]);
        }
    }

    /** Non-production paths are ignored in every bucket. */
    public function test_ignores_paths_outside_the_production_directories(): void
    {
        $c = $this->collected(null);

        $this->assertArrayNotHasKey('tests/NotProduction.php', $c, 'A committed test-file change is not production.');
        $this->assertArrayNotHasKey('tests/BrandNewTest.php', $c, 'An untracked test file is not production.');
    }

    // ── Allow-list evaluation ────────────────────────────────────────────────

    /** 6. a protected path OUTSIDE the allow-list is reported */
    public function test_flags_a_production_path_outside_the_allowlist(): void
    {
        $g = $this->guard();
        $r = $g->collect($this->baseSha);

        $unexpected = $g->unexpected($r['entries'], ['app/ToModify.php']);

        $this->assertContains(
            'app/ToDelete.php',
            $unexpected,
            'A committed deletion that nobody allow-listed must be flagged.'
        );
        $this->assertContains('app/BrandNew.php', $unexpected);
    }

    /** 7. an approved path INSIDE the allow-list is not reported */
    public function test_accepts_production_paths_inside_the_allowlist(): void
    {
        $g = $this->guard();
        $r = $g->collect($this->baseSha);

        $this->assertSame(
            [],
            $g->unexpected($r['entries'], $r['paths']),
            'Allow-listing exactly the observed change set must leave nothing unexpected.'
        );
    }

    /** An allow-list entry that nothing touched is harmless — it is a permit, not a requirement. */
    public function test_unused_allowlist_entries_are_harmless(): void
    {
        $g = $this->guard();
        $r = $g->collect($this->baseSha);

        $this->assertSame(
            [],
            $g->unexpected($r['entries'], array_merge($r['paths'], ['app/NeverTouched.php']))
        );
    }

    // ── Base-ref resolution ──────────────────────────────────────────────────

    /** An explicit ref wins, and resolves to a full SHA. */
    public function test_explicit_base_ref_takes_precedence(): void
    {
        $this->assertSame($this->baseSha, $this->guard()->resolveBaseRef($this->baseSha));
    }

    /** With nothing explicit, the default is the merge base with main. */
    public function test_default_base_ref_is_the_merge_base_with_main(): void
    {
        $this->git('checkout', '-q', '-b', 'feature');
        $mergeBase = $this->gitOut('merge-base', 'main', 'HEAD');

        $this->assertSame($mergeBase, $this->guard()->resolveBaseRef());
    }

    /**
     * An unresolvable ref yields null rather than a silent fallback, and collect() then reports
     * baseRefResolved = false. The caller is expected to fail on that. Downgrading to
     * working-tree-only when the base cannot be resolved would silently reinstate the original bug.
     */
    public function test_unresolvable_base_ref_is_reported_not_swallowed(): void
    {
        $this->assertNull($this->guard()->resolveBaseRef(str_repeat('0', 40)));

        $r = $this->guard()->collect(null);
        $this->assertFalse($r['baseRefResolved']);
    }

    /** Without a base ref the committed bucket is empty — proving the bucket is what finds it. */
    public function test_committed_changes_are_invisible_without_a_base_ref(): void
    {
        $paths = $this->guard()->collect(null)['paths'];

        $this->assertNotContains(
            'app/ToDelete.php',
            $paths,
            'Control: a committed deletion is unreachable without a commit-range comparison. '
            . 'This is exactly why the old working-tree-only guard missed the Checkpoint 2 deletions.'
        );
        $this->assertContains('app/ToLeaveDirty.php', $paths, 'Unstaged changes are still seen.');
    }

    // ── The live repository: the real Checkpoint 2 range ─────────────────────

    /**
     * The proof the task actually asks for: the four production files deleted by Checkpoint 2 are
     * genuinely EVALUATED by the guard for c3672a85a...036796da2 — not merely listed in an
     * allow-list that never gets consulted.
     *
     * Skips rather than fails if those commits are absent (a squashed or shallow checkout), since
     * that is an environment fact and not a regression.
     */
    public function test_checkpoint_two_deletions_are_evaluated_for_the_real_commit_range(): void
    {
        $g    = new ProductionScopeGuard(base_path());
        $base = $g->resolveBaseRef('c3672a85a');
        $head = $g->resolveBaseRef('036796da2');

        if ($base === null || $head === null) {
            $this->markTestSkipped('Checkpoint 2 commit range not present in this checkout.');
        }

        $entries = $g->collect($base)['entries'];

        $committedDeletes = [];
        foreach ($entries as $e) {
            if ($e['origin'] === 'committed' && $e['status'] === 'D') {
                $committedDeletes[] = $e['path'];
            }
        }

        foreach ([
            'app/Http/Controllers/CompetingBidsController.php',
            'app/Services/CompetingBidsService.php',
            'app/Models/BiddingPeriodAgentMapping.php',
            'resources/views/tenant_agent/competing_bids.blade.php',
        ] as $deleted) {
            $this->assertContains(
                $deleted,
                $committedDeletes,
                "The guard must evaluate the Checkpoint 2 deletion of {$deleted}. The pre-hardening "
                . 'guard saw none of these, which is the deficiency this checkpoint fixes.'
            );
        }
    }

    /** Those four paths are gone from disk, so only a commit-range comparison could have found them. */
    public function test_the_evaluated_deletions_are_absent_from_the_working_tree(): void
    {
        foreach ([
            'app/Http/Controllers/CompetingBidsController.php',
            'app/Services/CompetingBidsService.php',
            'app/Models/BiddingPeriodAgentMapping.php',
            'resources/views/tenant_agent/competing_bids.blade.php',
        ] as $deleted) {
            $this->assertFileDoesNotExist(base_path($deleted));
        }
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function writeFile(string $rel, string $body): void
    {
        $full = $this->repo . '/' . $rel;
        if (! is_dir(dirname($full))) {
            mkdir(dirname($full), 0777, true);
        }
        file_put_contents($full, $body);
    }

    private function git(string ...$args): void
    {
        $this->gitOut(...$args);
    }

    private function gitOut(string ...$args): string
    {
        $cmd = 'git -C ' . escapeshellarg($this->repo);
        foreach ($args as $a) {
            $cmd .= ' ' . escapeshellarg($a);
        }

        $out  = [];
        $code = 0;
        exec($cmd . ' 2>&1', $out, $code);

        $this->assertSame(0, $code, 'git ' . implode(' ', $args) . ' failed: ' . implode("\n", $out));

        return trim(implode("\n", $out));
    }

    private function rmrf(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
