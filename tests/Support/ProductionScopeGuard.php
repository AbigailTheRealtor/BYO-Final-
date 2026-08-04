<?php

namespace Tests\Support;

/**
 * Collects every production-path change a checkpoint is responsible for, from ALL the places a
 * change can hide.
 *
 * WHY THIS EXISTS. The original guard asked git one question:
 *
 *     git diff --name-only -- app/ config/ routes/ database/ resources/
 *
 * Bare `git diff` compares the WORKING TREE against the INDEX. It therefore reports only
 * *unstaged* changes. During the Milestone 2 retirement checkpoint four production files were
 * deleted with `git rm`, which stages the deletion — so the file was gone from both the index and
 * the working tree, the two sides agreed, and `git diff` reported nothing. Four deleted production
 * files were invisible to a guard whose entire job is to notice production files changing. Worse,
 * once a checkpoint is committed the working tree is clean by definition, so the guard's answer
 * decays to the empty set and it passes trivially — it was strongest before the work landed and
 * useless afterwards.
 *
 * The fix is to stop asking one question. A production path can be touched in four distinct
 * places, and a guard that misses any of them can be walked past:
 *
 *   COMMITTED  `git diff <base>...HEAD`  — the checkpoint's own commits. The bucket the old guard
 *                                          could never see, because committing emptied it.
 *   STAGED     `git diff --cached`       — in the index, not yet committed. Where `git rm` and
 *                                          `git add` land, and the exact blind spot hit above.
 *   UNSTAGED   `git diff`                — working tree vs index. All the old guard ever saw.
 *   UNTRACKED  `git ls-files --others`   — brand-new files, invisible to every diff.
 *
 * The union of those four is the honest answer, and it is the same answer whether the checkpoint
 * is uncommitted, half-staged, or fully committed. That stability is the point: the guard must not
 * grade differently depending on when it happens to be run.
 *
 * RENAMES ARE TWO PATHS, NOT ONE. Rename detection is on (`-M`), and a rename emits an entry for
 * BOTH sides. `git diff --name-only` reports only a rename's destination, which means a protected
 * file could be moved out from under an allow-list and the guard would only ever ask about where
 * it landed, never about what it vacated. Both ends of a move are production-path changes and both
 * must be accounted for.
 *
 * NUL-separated output (`-z`) is used throughout rather than line splitting: git quotes and
 * escapes paths containing non-ASCII or unusual characters in its default output, and a guard that
 * mis-parses a path silently under-reports.
 */
final class ProductionScopeGuard
{
    /** Directories whose contents are "production" for guard purposes. */
    public const PRODUCTION_DIRS = ['app/', 'config/', 'routes/', 'database/', 'resources/'];

    /** Overrides the computed base ref. Set this to audit an arbitrary range. */
    public const BASE_REF_ENV = 'PROD_SCOPE_GUARD_BASE_REF';

    /** Fallback branch used to derive a base ref when nothing explicit is given. */
    public const DEFAULT_BASE_BRANCH = 'main';

    public function __construct(
        private string $repoRoot,
        private array $productionDirs = self::PRODUCTION_DIRS,
    ) {
    }

    /**
     * Decide which commit the committed-range comparison starts from.
     *
     * Resolution order, most explicit first:
     *   1. an argument passed by the caller (a test auditing a specific checkpoint range),
     *   2. the PROD_SCOPE_GUARD_BASE_REF environment variable,
     *   3. the merge base with `main` — "everything this branch changed", which is the right
     *      default for a task branch and does not need updating each checkpoint,
     *   4. null.
     *
     * Returning null means the committed bucket cannot be computed. That is NOT treated as
     * "nothing was committed" — collect() records baseRefResolved = false so the caller can fail
     * loudly. A guard that silently downgrades to working-tree-only when it cannot resolve a base
     * is back to the bug this class exists to fix.
     */
    public function resolveBaseRef(?string $explicit = null): ?string
    {
        foreach ([$explicit, getenv(self::BASE_REF_ENV) ?: null] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $this->verifyCommit($candidate);
            }
        }

        [$ok, $out] = $this->git(['merge-base', self::DEFAULT_BASE_BRANCH, 'HEAD']);

        return $ok && isset($out[0]) && $out[0] !== '' ? $out[0] : null;
    }

    /** @return string|null the resolved SHA, or null when the ref does not name a commit */
    private function verifyCommit(string $ref): ?string
    {
        [$ok, $out] = $this->git(['rev-parse', '--verify', '--quiet', $ref . '^{commit}']);

        return $ok && isset($out[0]) && $out[0] !== '' ? $out[0] : null;
    }

    /**
     * The union of all four buckets.
     *
     * @return array{
     *     baseRef: string|null,
     *     baseRefResolved: bool,
     *     entries: array<int, array{path: string, status: string, origin: string}>,
     *     paths: array<int, string>
     * }
     */
    public function collect(?string $baseRef): array
    {
        $entries = [];

        if ($baseRef !== null) {
            // Three-dot: changes on HEAD's side since the merge base. When the base is an ancestor
            // (a checkpoint's parent) this is identical to two-dot; when it is a branch name it
            // correctly excludes work done on that branch after we forked.
            $entries = array_merge($entries, $this->nameStatus(
                ['diff', '--name-status', '-M', '-z', $baseRef . '...HEAD'],
                'committed'
            ));
        }

        $entries = array_merge($entries, $this->nameStatus(
            ['diff', '--name-status', '-M', '-z', '--cached'],
            'staged'
        ));

        $entries = array_merge($entries, $this->nameStatus(
            ['diff', '--name-status', '-M', '-z'],
            'unstaged'
        ));

        foreach ($this->nulList(['ls-files', '--others', '--exclude-standard', '-z']) as $path) {
            if ($this->isProductionPath($path)) {
                $entries[] = ['path' => $path, 'status' => 'A', 'origin' => 'untracked'];
            }
        }

        $paths = array_values(array_unique(array_map(fn ($e) => $e['path'], $entries)));
        sort($paths);

        return [
            'baseRef'         => $baseRef,
            'baseRefResolved' => $baseRef !== null,
            'entries'         => $entries,
            'paths'           => $paths,
        ];
    }

    /**
     * Production paths in the change set that the allow-list does not permit.
     *
     * @param array<int, array{path: string, status: string, origin: string}> $entries
     * @param array<int, string>                                              $allowlist
     * @return array<int, string>
     */
    public function unexpected(array $entries, array $allowlist): array
    {
        $permitted = array_map('trim', $allowlist);

        $bad = [];
        foreach ($entries as $entry) {
            if (! in_array(trim($entry['path']), $permitted, true)) {
                $bad[$entry['path']] = true;
            }
        }

        $out = array_keys($bad);
        sort($out);

        return $out;
    }

    /** Human-readable "path (status, origin)" lines, for assertion messages. */
    public function describe(array $entries, array $paths): array
    {
        $byPath = [];
        foreach ($entries as $e) {
            $byPath[$e['path']][] = "{$e['status']}/{$e['origin']}";
        }

        return array_map(
            fn ($p) => $p . ' [' . implode(' ', array_unique($byPath[$p] ?? ['?'])) . ']',
            $paths
        );
    }

    // ── git plumbing ─────────────────────────────────────────────────────────

    /**
     * Parse `--name-status -z`. Records are NUL-separated tokens: a status token followed by one
     * path, except renames and copies (`R090`, `C075`) which are followed by TWO paths — source
     * then destination. Both are emitted, so a move can never launder a protected file.
     *
     * @return array<int, array{path: string, status: string, origin: string}>
     */
    private function nameStatus(array $args, string $origin): array
    {
        $tokens = $this->nulList($args);
        $out    = [];

        for ($i = 0; $i < count($tokens); $i++) {
            $status = $tokens[$i];
            if ($status === '') {
                continue;
            }

            $isMove = (bool) preg_match('/^[RC]\d*$/', $status);

            $paths = [];
            if ($isMove) {
                $paths[] = $tokens[++$i] ?? '';   // source — the path being vacated
                $paths[] = $tokens[++$i] ?? '';   // destination
            } else {
                $paths[] = $tokens[++$i] ?? '';
            }

            foreach ($paths as $path) {
                if ($path !== '' && $this->isProductionPath($path)) {
                    $out[] = ['path' => $path, 'status' => $status, 'origin' => $origin];
                }
            }
        }

        return $out;
    }

    /**
     * Filtering happens in PHP rather than via git pathspecs.
     *
     * A pathspec on a rename-detecting diff can turn a rename into an add/delete pair when only
     * one side matches the spec, which changes the reported status. Asking git for everything and
     * filtering afterwards keeps statuses honest and keeps both halves of a cross-directory move.
     */
    private function isProductionPath(string $path): bool
    {
        foreach ($this->productionDirs as $dir) {
            if (str_starts_with($path, $dir)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, string> NUL-separated tokens, empties stripped from the tail */
    private function nulList(array $args): array
    {
        [$ok, $lines] = $this->git($args, raw: true);
        if (! $ok) {
            return [];
        }

        $tokens = explode("\0", implode("\n", $lines));

        while ($tokens !== [] && end($tokens) === '') {
            array_pop($tokens);
        }

        return $tokens;
    }

    /**
     * @return array{0: bool, 1: array<int, string>}
     */
    private function git(array $args, bool $raw = false): array
    {
        $cmd = 'git --no-optional-locks -C ' . escapeshellarg($this->repoRoot);
        foreach ($args as $arg) {
            $cmd .= ' ' . escapeshellarg($arg);
        }
        $cmd .= ' 2>/dev/null';

        $output = [];
        $code   = 0;
        exec($cmd, $output, $code);

        if ($raw) {
            return [$code === 0, $output];
        }

        return [$code === 0, array_map('trim', $output)];
    }
}
