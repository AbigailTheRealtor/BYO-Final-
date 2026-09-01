#!/usr/bin/env bash
#
# Run the Spatial / Location DNA PHPUnit suite from the committed manifest.
#
# ONE FILE PER PHPUNIT INVOCATION — this is the whole point of the script.
# PHPUnit 9 takes a SINGLE test target from the command line and silently ignores any
# further paths, so `phpunit a.php b.php` runs only a.php and still exits 0. Passing the
# manifest to one invocation would therefore report a confident green having executed one
# file out of 241. The same false-green is documented in .github/workflows/migration-tests.yml.
#
# Nothing here is suppressed: no `|| true`, no continue-on-error, no grep-based masking.
# The script exits non-zero if any listed file fails, errors, is missing, or cannot execute,
# and it preserves the FIRST failing exit code rather than the last one.
#
# Usage:  scripts/run-spatial-tests.sh [manifest]
# Env:    PHPUNIT_BIN  (default: php vendor/bin/phpunit)

set -uo pipefail

MANIFEST="${1:-tests/spatial-ci-files.txt}"
PHPUNIT_BIN="${PHPUNIT_BIN:-php vendor/bin/phpunit}"

if [ ! -f "$MANIFEST" ]; then
    echo "FATAL: manifest not found: $MANIFEST" >&2
    exit 1
fi

# ── Read the manifest ────────────────────────────────────────────────────────────────────
# Strips `#` comments and blank lines. Everything else must be a real file.
FILES=()
while IFS= read -r line; do
    line="${line%%#*}"                       # drop trailing/whole-line comments
    line="$(printf '%s' "$line" | tr -d '[:space:]')"
    [ -z "$line" ] && continue
    FILES+=("$line")
done < "$MANIFEST"

if [ "${#FILES[@]}" -eq 0 ]; then
    # A manifest that selects nothing must not read as success.
    echo "FATAL: manifest '$MANIFEST' lists no test files — refusing to report success." >&2
    exit 1
fi

# ── Validate every path BEFORE running anything ──────────────────────────────────────────
# A renamed or deleted test must fail the gate loudly, not vanish from it. Checking up front
# means the failure is one clear list rather than a surprise 200 files into the run.
missing=()
for f in "${FILES[@]}"; do
    [ -f "$f" ] || missing+=("$f")
done

if [ "${#missing[@]}" -ne 0 ]; then
    echo "FATAL: ${#missing[@]} manifest path(s) do not exist:" >&2
    printf '  %s\n' "${missing[@]}" >&2
    echo "Update $MANIFEST (a renamed or deleted spatial test must not silently leave the gate)." >&2
    exit 1
fi

# Duplicates would inflate the counts and hide a copy-paste slip in the manifest.
dupes="$(printf '%s\n' "${FILES[@]}" | sort | uniq -d)"
if [ -n "$dupes" ]; then
    echo "FATAL: duplicate manifest entries:" >&2
    printf '  %s\n' $dupes >&2
    exit 1
fi

echo "Spatial / Location DNA suite"
echo "  manifest:        $MANIFEST"
echo "  files listed:    ${#FILES[@]}"
echo "  runner:          $PHPUNIT_BIN (one invocation per file)"
echo

# ── Run, one invocation per file ─────────────────────────────────────────────────────────
executed=0
passed=0
failed_files=()
first_rc=0
skipped_total=0
skipped_files=()

for f in "${FILES[@]}"; do
    out="$($PHPUNIT_BIN "$f" 2>&1)"
    rc=$?
    executed=$((executed + 1))

    summary="$(printf '%s\n' "$out" | grep -E '^(OK|OK, but|Tests:|No tests executed)' | tail -1)"

    # Count skips so "green" cannot quietly mean "skipped".
    s="$(printf '%s' "$summary" | sed -n 's/.*Skipped: \([0-9]*\).*/\1/p')"
    if [ -n "${s:-}" ] && [ "$s" -gt 0 ]; then
        skipped_total=$((skipped_total + s))
        skipped_files+=("$f ($s)")
    fi

    if [ "$rc" -eq 0 ]; then
        passed=$((passed + 1))
        printf '  ok    %-92s %s\n' "$f" "$summary"
    else
        failed_files+=("$f")
        [ "$first_rc" -eq 0 ] && first_rc="$rc"
        printf '  FAIL  %-92s %s\n' "$f" "$summary"
        # Full output for the failing file, so CI logs are actionable without a re-run.
        printf '%s\n' "$out" | sed 's/^/        | /'
    fi
done

# ── Summary ──────────────────────────────────────────────────────────────────────────────
echo
echo "──────────────────────────────────────────────────────────────────"
echo "  files listed:    ${#FILES[@]}"
echo "  files executed:  $executed"
echo "  files passed:    $passed"
echo "  files failed:    ${#failed_files[@]}"
echo "  tests skipped:   $skipped_total"
if [ "${#skipped_files[@]}" -ne 0 ]; then
    echo "  skipped in:"
    printf '    %s\n' "${skipped_files[@]}"
fi
echo "──────────────────────────────────────────────────────────────────"

# Executing fewer files than the manifest lists means the loop itself broke.
if [ "$executed" -ne "${#FILES[@]}" ]; then
    echo "FATAL: executed $executed of ${#FILES[@]} files — the runner did not complete." >&2
    exit 1
fi

if [ "${#failed_files[@]}" -ne 0 ]; then
    echo
    echo "FAILED (${#failed_files[@]}):" >&2
    printf '  %s\n' "${failed_files[@]}" >&2
    exit "$first_rc"
fi

echo "All ${#FILES[@]} spatial test files passed."
