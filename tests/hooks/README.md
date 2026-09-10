# Pre-commit Hook Tests

Automated tests for `.githooks/pre-commit` and for the shared debug-statement
gate it delegates to, `scripts/ci/check-added-debug-statements.sh`.

## Run locally

```bash
bash tests/hooks/test-pre-commit.sh            # the installed hook, end to end
bash tests/hooks/test-debug-statement-gate.sh  # the shared gate, both modes
```

Exit code `0` means all tests passed; exit code `1` means one or more tests failed.

## The two suites

| Suite | Subject |
|---|---|
| `test-pre-commit.sh` | The hook as a whole — backup-file detection, every file type in scope, and that the installed hook honours the added-lines rule. |
| `test-debug-statement-gate.sh` | The shared checker directly — both modes, all three profiles, patch-parsing edge cases, and its failure modes. |

They are separate because they answer different questions. The first asks
whether a commit is blocked; the second asks whether the gate read the change
correctly. A bug in the parser is much easier to see stated in its own terms
than inferred from a hook's exit code.

## Added-lines semantics

The gate judges **only the lines a change adds**, not the whole content of the
files it touches. Editing one line in a file that already contains a debug
statement is not an offence; adding a debug statement is, wherever it lands.

This is the behaviour the suites exist to pin. The old whole-file scan made the
gate a function of a file's history rather than of the change under review — the
only way to go green was to clean up code the change never touched, and a file
carrying dozens of legacy `console.log` calls made every future edit to it fail.

Both modes are covered with equivalent scenarios, because they reach the shared
parser through different git plumbing:

- **`--staged`** — index vs `HEAD`, what the pre-commit hook runs. Falls back to
  the empty tree on an initial commit, where there is no `HEAD` yet.
- **`--range <BASE> <HEAD>`** — `merge-base(BASE, HEAD)` vs `HEAD`, what CI runs.
  The merge base is computed explicitly rather than comparing the two tips, so
  commits that landed on the base branch *after* this one forked are not
  attributed to it, and a debug statement added in any commit of a multi-commit
  branch is still caught.

## Profiles are deliberately not identical

The three gates keep their own extension lists and pattern sets, selected by
`--profile`. They differ — `check-debug-statements` covers neither `.mjs`,
`.svelte` nor `.twig`, and only the `pre-commit` profile prohibits PHP `dump(`.
`test-debug-statement-gate.sh` pins each difference, so a future change that
harmonises them fails loudly here rather than silently widening or narrowing
what a gate blocks. Changing *what* a gate prohibits is a separate decision from
changing *which lines* it reads.

## How it works

`test-pre-commit.sh` — each case creates a throwaway git repository in
`$TMPDIR`, installs the hook and the shared checker at their real repo-relative
paths, stages content, and asserts the outcome. Cases that need a *pre-existing*
debug statement commit their baseline with `--no-verify`: that baseline is
fixture setup, not a change under test.

`test-debug-statement-gate.sh` — each case builds a repository with real history
(branches, multiple commits, renames, deletions) and invokes the checker
directly, asserting its exit code and, where it matters, the text it reported.

Exit codes the gate uses: `0` clean, `1` debug statements were added, `2` the
check could not be performed (bad usage, unresolvable revision, no merge base).
`2` is distinct on purpose — an unrunnable gate must not read as a passing one.

## CI

Both suites run on every pull request via
`.github/workflows/pre-commit-hook-tests.yml`.

## Debugging a failure

Re-run the relevant suite and read the FAIL lines; `test-debug-statement-gate.sh`
prints the expected and actual exit codes plus a truncated copy of the gate's
output for any case that fails.

To inspect what the hook outputs for a specific `test-pre-commit.sh` case,
temporarily replace `> /dev/null 2>&1` with `/dev/stderr` in the
`assert_blocked` / `assert_allowed` helpers inside the script.
