# Pre-commit Hook Tests

Automated tests for `.githooks/pre-commit`.

## Run locally

```bash
bash tests/hooks/test-pre-commit.sh
```

Exit code `0` means all tests passed; exit code `1` means one or more tests failed.

## How it works

Each test case:
1. Creates a throwaway git repository in `$TMPDIR`
2. Stages a file with specific content
3. Runs the hook (as if a real commit were in progress)
4. Asserts the expected outcome — blocked (exit 1) or allowed (exit 0)

The hook's `git diff --cached` / `git show ":$file"` commands run against real
git index state, so the tests exercise the same code paths as an actual commit.

## CI

Tests run automatically on every pull request via
`.github/workflows/pre-commit-hook-tests.yml`.

## Debugging a failure

Re-run the suite directly and read the FAIL lines:

```bash
bash tests/hooks/test-pre-commit.sh
```

To inspect what the hook outputs for a specific case, temporarily replace
`> /dev/null 2>&1` with `/dev/stderr` in the `assert_blocked` /
`assert_allowed` helpers inside the script.
