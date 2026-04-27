#!/usr/bin/env bash
# tests/hooks/test-pre-commit.sh
#
# Plain-bash test suite for .githooks/pre-commit.
#
# Usage:
#   bash tests/hooks/test-pre-commit.sh
#
# Exit code:
#   0  all tests passed
#   1  one or more tests failed
#
# How it works
# ────────────
# Each test case spins up its own isolated, throwaway git repository inside
# $TMPDIR so nothing in the real working tree is touched.  The hook is copied
# in, a file is staged, and we assert the expected exit code (0 = pass, 1 = blocked).

set -euo pipefail

HOOK_SRC="$(cd "$(dirname "$0")/../.." && pwd)/.githooks/pre-commit"

if [[ ! -f "$HOOK_SRC" ]]; then
    echo "FATAL: hook not found at $HOOK_SRC" >&2
    exit 1
fi

# ── Counters ──────────────────────────────────────────────────────────────────
PASS=0
FAIL=0

# ── Helpers ───────────────────────────────────────────────────────────────────

# make_repo <dir>  – initialise a bare-minimum git repo and install the hook.
make_repo() {
    local dir="$1"
    mkdir -p "$dir"
    git -C "$dir" init -q
    git -C "$dir" config user.email "test@example.com"
    git -C "$dir" config user.name  "Test"
    # Create an initial empty commit so HEAD exists (needed for git show)
    git -C "$dir" commit -q --allow-empty -m "init"
    cp "$HOOK_SRC" "$dir/.git/hooks/pre-commit"
    chmod +x "$dir/.git/hooks/pre-commit"
}

# stage_file <repo> <relative-path> <content>
stage_file() {
    local repo="$1" path="$2" content="$3"
    local abs="$repo/$path"
    mkdir -p "$(dirname "$abs")"
    printf '%s\n' "$content" > "$abs"
    git -C "$repo" add "$path"
}

# assert_blocked <description> <repo> [expected_output_text]
# Runs the hook from inside the repo and expects it to exit non-zero (blocked).
# Optional third argument: a string that must appear in the hook's stdout/stderr
# output, confirming the block came from intended detection rather than an
# unexpected internal hook error.
assert_blocked() {
    local desc="$1" repo="$2" expected_text="${3:-ERROR:}"
    local out
    out="$(cd "$repo" && bash .git/hooks/pre-commit 2>&1)" && {
        echo "  FAIL (should have been blocked but was not): $desc"
        FAIL=$((FAIL + 1))
        return
    }
    if [[ -n "$expected_text" ]] && ! echo "$out" | grep -qF "$expected_text"; then
        echo "  FAIL (blocked but output did not contain '$expected_text'): $desc"
        FAIL=$((FAIL + 1))
    else
        echo "  PASS (blocked as expected): $desc"
        PASS=$((PASS + 1))
    fi
}

# assert_allowed <description> <repo>
# Runs the hook from inside the repo and expects it to exit zero (passes).
assert_allowed() {
    local desc="$1" repo="$2"
    if (cd "$repo" && bash .git/hooks/pre-commit > /dev/null 2>&1); then
        echo "  PASS (allowed as expected): $desc"
        PASS=$((PASS + 1))
    else
        echo "  FAIL (should have been allowed but was blocked): $desc"
        FAIL=$((FAIL + 1))
    fi
}

# ── Test runner ───────────────────────────────────────────────────────────────

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

run_test() {
    local id="$1"
    local description="$2"
    local file_path="$3"
    local content="$4"
    local expect="$5"   # "blocked" | "allowed"

    local repo="$TMP/$id"
    make_repo "$repo"
    stage_file "$repo" "$file_path" "$content"

    if [[ "$expect" == "blocked" ]]; then
        assert_blocked "$description" "$repo"
    else
        assert_allowed "$description" "$repo"
    fi
}

echo ""
echo "Running pre-commit hook tests …"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "── PHP debug statements ─────────────────────────────────────"

run_test t01 "PHP: dd( is blocked" \
    "app/Http/Controllers/Foo.php" \
    '<?php dd($user);' \
    blocked

run_test t02 "PHP: var_dump( is blocked" \
    "app/Http/Controllers/Foo.php" \
    '<?php var_dump($user);' \
    blocked

run_test t03 "PHP: clean file passes" \
    "app/Http/Controllers/Foo.php" \
    '<?php echo "hello";' \
    allowed

run_test t04 "PHP (Blade): dd( in blade file is blocked" \
    "resources/views/home.blade.php" \
    '@php dd($data); @endphp' \
    blocked

run_test t05 "PHP (routes): dd( in routes file is blocked" \
    "routes/web.php" \
    '<?php Route::get("/", function() { dd("debug"); });' \
    blocked

run_test t06 "PHP (tests): var_dump( in test file is blocked (hook covers all .php)" \
    "tests/Feature/ExampleTest.php" \
    '<?php class ExampleTest { public function test_example() { var_dump($foo); } }' \
    blocked

echo ""
echo "── JS / MJS / JSX debug statements ─────────────────────────"

run_test t07 "JS: console.log( is blocked" \
    "resources/js/app.js" \
    'console.log("debug");' \
    blocked

run_test t08 "MJS: console.log( is blocked" \
    "resources/js/helpers.mjs" \
    'export function foo() { console.log("x"); }' \
    blocked

run_test t09 "JSX: console.log( is blocked" \
    "resources/js/Component.jsx" \
    'function App() { console.log("render"); return null; }' \
    blocked

run_test t10 "JS: debugger; is blocked" \
    "resources/js/app.js" \
    'function boot() { debugger; }' \
    blocked

run_test t11 "MJS: debugger; is blocked" \
    "resources/js/helpers.mjs" \
    'export function foo() { debugger; }' \
    blocked

run_test t12 "JS: clean file passes" \
    "resources/js/app.js" \
    'const x = 1; export default x;' \
    allowed

run_test t12b "MJS: clean file passes" \
    "resources/js/helpers.mjs" \
    'export const add = (a, b) => a + b;' \
    allowed

run_test t12c "JSX: clean file passes" \
    "resources/js/Component.jsx" \
    'function App() { return <div>Hello</div>; }' \
    allowed

echo ""
echo "── TS / TSX debug statements ────────────────────────────────"

run_test t13 "TS: console.log( is blocked" \
    "resources/ts/service.ts" \
    'const fn = (): void => { console.log("debug"); };' \
    blocked

run_test t14 "TS: debugger; is blocked" \
    "resources/ts/service.ts" \
    'const fn = (): void => { debugger; };' \
    blocked

run_test t15 "TSX: console.log( is blocked" \
    "resources/ts/App.tsx" \
    'function App() { console.log("render"); return <div/>; }' \
    blocked

run_test t16 "TS: clean file passes" \
    "resources/ts/service.ts" \
    'export const greet = (name: string): string => `Hello ${name}`;' \
    allowed

run_test t16b "TSX: clean file passes" \
    "resources/ts/App.tsx" \
    'function App() { return <div>Hello</div>; }' \
    allowed

echo ""
echo "── Vue / Svelte debug statements ────────────────────────────"

run_test t17 "Vue: console.log( is blocked" \
    "resources/js/MyComponent.vue" \
    '<script setup> console.log("init"); </script>' \
    blocked

run_test t18 "Vue: debugger; is blocked" \
    "resources/js/MyComponent.vue" \
    '<script setup> function foo() { debugger; } </script>' \
    blocked

run_test t19 "Vue: clean file passes" \
    "resources/js/MyComponent.vue" \
    '<template><div>Hello</div></template>' \
    allowed

run_test t20 "Svelte: console.log( is blocked" \
    "resources/js/Widget.svelte" \
    '<script> console.log("debug"); </script>' \
    blocked

run_test t21 "Svelte: debugger; is blocked" \
    "resources/js/Widget.svelte" \
    '<script> function go() { debugger; } </script>' \
    blocked

run_test t22 "Svelte: clean file passes" \
    "resources/js/Widget.svelte" \
    '<script> let count = 0; </script>' \
    allowed

echo ""
echo "── Twig debug statements ────────────────────────────────────"

run_test t23 "Twig: dump( is blocked" \
    "templates/home.twig" \
    '{{ dump(variable) }}' \
    blocked

run_test t24 "Twig: clean file passes" \
    "templates/home.twig" \
    '{{ variable }}' \
    allowed

echo ""
echo "── Backup-file detection ────────────────────────────────────"

run_test t25 "Backup (.bak) in app/ is blocked" \
    "app/Models/User.bak" \
    'backup content' \
    blocked

run_test t26 "Backup (.orig) in app/ is blocked" \
    "app/Models/User.orig" \
    'backup content' \
    blocked

run_test t27 "Backup (.tmp) in resources/ is blocked" \
    "resources/js/app.tmp" \
    'backup content' \
    blocked

run_test t28 "Backup (.bak) in public/ is blocked" \
    "public/build/asset.bak" \
    'backup content' \
    blocked

run_test t29 "Backup (.bak) in database/ is blocked" \
    "database/migrations/2024_01_01_create_users.bak" \
    'backup content' \
    blocked

run_test t30 "Clean .php file in app/ passes (no false positive on .bak pattern)" \
    "app/Models/User.php" \
    '<?php class User {}' \
    allowed

run_test t31 "Backup (.bak) outside monitored dirs passes (scope is intentionally limited)" \
    "storage/logs/laravel.bak" \
    'log content' \
    allowed

echo ""
echo "── Edge cases ───────────────────────────────────────────────"

run_test t32 "PHP: dd( in a comment is still caught (grep does not parse PHP)" \
    "app/Helpers/Util.php" \
    '<?php // dd($x) left here by mistake' \
    blocked

run_test t33 "JS: console.log( inside a string literal is still caught" \
    "resources/js/app.js" \
    "const msg = 'console.log(\"debug\")';" \
    blocked

run_test t34 "Twig: dump( appearing inside a string is still caught" \
    "templates/home.twig" \
    '{% set s = "dump(x)" %}' \
    blocked

run_test t35 "Empty PHP file passes" \
    "app/Helpers/Empty.php" \
    '' \
    allowed

run_test t36 "PHP: var_dump( with no closing paren in same line still blocked" \
    "app/Services/Svc.php" \
    '<?php var_dump(' \
    blocked

# ── Summary ───────────────────────────────────────────────────────────────────
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
TOTAL=$((PASS + FAIL))
echo "Results: $PASS passed, $FAIL failed, $TOTAL total"
echo ""

if [[ "$FAIL" -gt 0 ]]; then
    exit 1
fi

exit 0
