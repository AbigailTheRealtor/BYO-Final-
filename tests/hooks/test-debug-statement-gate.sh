#!/usr/bin/env bash
# tests/hooks/test-debug-statement-gate.sh
#
# Test suite for scripts/ci/check-added-debug-statements.sh — the shared,
# diff-aware debug-statement gate used by both CI workflows and the pre-commit
# hook.
#
# Usage:
#   bash tests/hooks/test-debug-statement-gate.sh
#
# Exit code:
#   0  all tests passed
#   1  one or more tests failed
#
# How it works
# ────────────
# Every case builds a throwaway git repository in $TMPDIR with a real history,
# then runs the checker in one of its two modes.  Nothing in the working tree is
# touched.  The central property under test is that the gate judges LINES THE
# CHANGE ADDS, not the contents of the files the change happens to touch — so a
# file that already contained debug statements is not a liability for the next
# person who edits an unrelated line in it.
#
# Both modes are exercised with equivalent scenarios: --staged (what the
# pre-commit hook does, index vs HEAD) and --range (what CI does, merge-base vs
# head).  They share a parser, but they reach it through different git plumbing,
# so a fix in one is not evidence about the other.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
CHECKER="$ROOT/scripts/ci/check-added-debug-statements.sh"

if [[ ! -f "$CHECKER" ]]; then
    echo "FATAL: checker not found at $CHECKER" >&2
    exit 1
fi

PASS=0
FAIL=0

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

# ── Repo helpers ──────────────────────────────────────────────────────────────

# repo_new <path> — fresh repo on branch "main", no commits yet.
repo_new() {
    local d="$1"
    mkdir -p "$d"
    git -C "$d" init -q
    git -C "$d" checkout -q -b main 2>/dev/null || true
    git -C "$d" config user.email "test@example.com"
    git -C "$d" config user.name "Test"
    git -C "$d" config commit.gpgsign false
}

# write_file <repo> <relpath> — content on stdin.
write_file() {
    local r="$1" p="$2" abs
    abs="$r/$p"
    mkdir -p "$(dirname "$abs")"
    cat > "$abs"
}

# commit_all <repo> <message>
commit_all() {
    local r="$1" m="$2"
    git -C "$r" add -A
    git -C "$r" commit -q -m "$m"
}

# ── Assertion helpers ─────────────────────────────────────────────────────────

# run_checker <repo> <expected_exit> <desc> [<must_contain>] -- <checker args...>
run_checker() {
    local repo="$1" expected="$2" desc="$3" needle="$4"
    shift 4
    [[ "$1" == "--" ]] && shift

    local out rc
    set +e
    out="$(cd "$repo" && bash "$CHECKER" "$@" 2>&1)"
    rc=$?
    set -e

    if [[ "$rc" -ne "$expected" ]]; then
        echo "  FAIL: $desc"
        echo "        expected exit $expected, got $rc"
        echo "        output: $(echo "$out" | tr '\n' '|' | cut -c1-300)"
        FAIL=$((FAIL + 1))
        return
    fi

    if [[ -n "$needle" ]] && ! printf '%s' "$out" | grep -qF -- "$needle"; then
        echo "  FAIL: $desc"
        echo "        exit $rc as expected, but output lacked: $needle"
        echo "        output: $(echo "$out" | tr '\n' '|' | cut -c1-300)"
        FAIL=$((FAIL + 1))
        return
    fi

    echo "  PASS: $desc"
    PASS=$((PASS + 1))
}

# A file full of pre-existing debug statements — the shape that made the old
# whole-file gate unusable (see public/js/app.js on the realtime branch).
legacy_js() {
    local n="$1" i
    echo "// legacy bootstrap"
    for (( i = 1; i <= n; i++ )); do
        echo "console.log('legacy trace $i');"
    done
    echo "const version = 1;"
    echo "export default version;"
}

echo ""
echo "Running diff-aware debug-statement gate tests …"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# ══════════════════════════════════════════════════════════════════════════════
echo ""
echo "── STAGED MODE ──────────────────────────────────────────────"

# 1. newly added console.log → FAIL
r="$TMP/s01"; repo_new "$r"
write_file "$r" "resources/js/app.js" <<'EOF'
const x = 1;
EOF
commit_all "$r" base
write_file "$r" "resources/js/app.js" <<'EOF'
const x = 1;
console.log('debug');
EOF
git -C "$r" add -A
run_checker "$r" 1 "s01 newly added console.log is blocked" "resources/js/app.js" \
    -- --profile pre-commit --staged

# 2. newly added prohibited PHP debug statement → FAIL
r="$TMP/s02"; repo_new "$r"
write_file "$r" "app/Foo.php" <<'EOF'
<?php
class Foo {}
EOF
commit_all "$r" base
write_file "$r" "app/Foo.php" <<'EOF'
<?php
class Foo {}
dd($user);
EOF
git -C "$r" add -A
run_checker "$r" 1 "s02 newly added PHP dd( is blocked" "app/Foo.php" \
    -- --profile pre-commit --staged

# 3. pre-existing console.log + unrelated edit → PASS
r="$TMP/s03"; repo_new "$r"
legacy_js 4 | write_file "$r" "resources/js/app.js"
commit_all "$r" base
{ legacy_js 4 | sed 's/const version = 1;/const version = 2;/'; } | write_file "$r" "resources/js/app.js"
git -C "$r" add -A
run_checker "$r" 0 "s03 pre-existing console.log + unrelated edit passes" "" \
    -- --profile pre-commit --staged

# 4. removing a console.log → PASS
r="$TMP/s04"; repo_new "$r"
legacy_js 4 | write_file "$r" "resources/js/app.js"
commit_all "$r" base
{ legacy_js 4 | grep -v "legacy trace 2"; } | write_file "$r" "resources/js/app.js"
git -C "$r" add -A
run_checker "$r" 0 "s04 removing a console.log passes" "" \
    -- --profile pre-commit --staged

# 5. vendor/generated-like file with old console.log + unrelated edit → PASS
#    (no directory exclusions exist; it passes because the lines are not new)
r="$TMP/s05"; repo_new "$r"
legacy_js 30 | write_file "$r" "public/js/app.js"
commit_all "$r" base
{ legacy_js 30 | sed 's/const version = 1;/const version = 2;/'; } | write_file "$r" "public/js/app.js"
git -C "$r" add -A
run_checker "$r" 0 "s05 generated-like file, unrelated edit, no exclusions needed" "" \
    -- --profile pre-commit --staged

# 6. same file + NEW console.log → FAIL
r="$TMP/s06"; repo_new "$r"
legacy_js 30 | write_file "$r" "public/js/app.js"
commit_all "$r" base
{ legacy_js 30; echo "console.log('freshly added');"; } | write_file "$r" "public/js/app.js"
git -C "$r" add -A
run_checker "$r" 1 "s06 same generated-like file, one NEW console.log, is blocked" "freshly added" \
    -- --profile pre-commit --staged

# 9. filename containing spaces
r="$TMP/s09"; repo_new "$r"
write_file "$r" "resources/js/my widget file.js" <<'EOF'
const a = 1;
EOF
commit_all "$r" base
write_file "$r" "resources/js/my widget file.js" <<'EOF'
const a = 1;
console.log('spaced');
EOF
git -C "$r" add -A
run_checker "$r" 1 "s09 filename with spaces is scanned and blocked" "my widget file.js" \
    -- --profile pre-commit --staged

# 9b. filename with spaces, unrelated edit only → PASS
r="$TMP/s09b"; repo_new "$r"
legacy_js 3 | write_file "$r" "resources/js/my widget file.js"
commit_all "$r" base
{ legacy_js 3 | sed 's/const version = 1;/const version = 9;/'; } | write_file "$r" "resources/js/my widget file.js"
git -C "$r" add -A
run_checker "$r" 0 "s09b filename with spaces, unrelated edit, passes" "" \
    -- --profile pre-commit --staged

# 10. filename containing *
r="$TMP/s10"; repo_new "$r"
write_file "$r" 'resources/js/star*name.js' <<'EOF'
const a = 1;
EOF
write_file "$r" 'resources/js/decoy.js' <<'EOF'
console.log('decoy pre-existing');
EOF
commit_all "$r" base
write_file "$r" 'resources/js/star*name.js' <<'EOF'
const a = 1;
console.log('globbed');
EOF
git -C "$r" add -A
run_checker "$r" 1 "s10 filename containing * is treated literally and blocked" "globbed" \
    -- --profile pre-commit --staged

# 10b. * filename, unrelated edit → PASS (decoy's old console.log must not leak in)
r="$TMP/s10b"; repo_new "$r"
write_file "$r" 'resources/js/star*name.js' <<'EOF'
const a = 1;
EOF
write_file "$r" 'resources/js/decoy.js' <<'EOF'
console.log('decoy pre-existing');
EOF
commit_all "$r" base
write_file "$r" 'resources/js/star*name.js' <<'EOF'
const a = 2;
EOF
git -C "$r" add -A
run_checker "$r" 0 "s10b * filename, unrelated edit, does not pull in other files" "" \
    -- --profile pre-commit --staged

# 11. source line literally beginning +++
r="$TMP/s11"; repo_new "$r"
write_file "$r" "resources/js/diffy.js" <<'EOF'
const a = 1;
EOF
commit_all "$r" base
write_file "$r" "resources/js/diffy.js" <<'EOF'
const a = 1;
+++ this line literally starts with plus signs
EOF
git -C "$r" add -A
run_checker "$r" 0 "s11 added line starting with +++ is not mistaken for a diff header" "" \
    -- --profile pre-commit --staged

# 11b. a +++ line that IS a violation must be caught and reported with its text
r="$TMP/s11b"; repo_new "$r"
write_file "$r" "resources/js/diffy.js" <<'EOF'
const a = 1;
EOF
commit_all "$r" base
write_file "$r" "resources/js/diffy.js" <<'EOF'
const a = 1;
+++ console.log('hidden behind plus signs');
EOF
git -C "$r" add -A
run_checker "$r" 1 "s11b +++ line containing console.log is blocked, one + stripped" \
    "+++ console.log('hidden behind plus signs');" \
    -- --profile pre-commit --staged

# 12. pure rename carrying old debug content → PASS
r="$TMP/s12"; repo_new "$r"
legacy_js 20 | write_file "$r" "resources/js/old-name.js"
commit_all "$r" base
git -C "$r" mv "resources/js/old-name.js" "resources/js/new-name.js"
git -C "$r" add -A
run_checker "$r" 0 "s12 pure rename of a debug-laden file passes" "" \
    -- --profile pre-commit --staged

# 13. deleted file → ignored
r="$TMP/s13"; repo_new "$r"
legacy_js 10 | write_file "$r" "resources/js/doomed.js"
write_file "$r" "resources/js/keep.js" <<'EOF'
const k = 1;
EOF
commit_all "$r" base
rm "$r/resources/js/doomed.js"
git -C "$r" add -A
run_checker "$r" 0 "s13 deleting a debug-laden file passes" "" \
    -- --profile pre-commit --staged

# initial commit support (no HEAD yet)
r="$TMP/s14i"; repo_new "$r"
write_file "$r" "resources/js/first.js" <<'EOF'
console.log('in the very first commit');
EOF
git -C "$r" add -A
run_checker "$r" 1 "s14i initial commit (no HEAD) still blocks an added console.log" "first.js" \
    -- --profile pre-commit --staged

r="$TMP/s14j"; repo_new "$r"
write_file "$r" "resources/js/first.js" <<'EOF'
const clean = 1;
EOF
git -C "$r" add -A
run_checker "$r" 0 "s14j initial commit (no HEAD) with clean content passes" "" \
    -- --profile pre-commit --staged

# unstaged changes must be invisible to --staged
r="$TMP/s15u"; repo_new "$r"
write_file "$r" "resources/js/app.js" <<'EOF'
const a = 1;
EOF
commit_all "$r" base
write_file "$r" "resources/js/app.js" <<'EOF'
const a = 2;
EOF
git -C "$r" add -A
printf "console.log('unstaged only');\n" >> "$r/resources/js/app.js"
run_checker "$r" 0 "s15u unstaged console.log is not judged by --staged" "" \
    -- --profile pre-commit --staged

# ══════════════════════════════════════════════════════════════════════════════
echo ""
echo "── RANGE MODE ───────────────────────────────────────────────"

# 1r. newly added console.log → FAIL
r="$TMP/r01"; repo_new "$r"
write_file "$r" "resources/js/app.js" <<'EOF'
const x = 1;
EOF
commit_all "$r" base
git -C "$r" checkout -q -b feature
write_file "$r" "resources/js/app.js" <<'EOF'
const x = 1;
console.log('debug');
EOF
commit_all "$r" "add debug"
run_checker "$r" 1 "r01 newly added console.log is blocked" "resources/js/app.js" \
    -- --profile no-debug-statements --range main feature

# 2r. newly added PHP debug statement → FAIL
r="$TMP/r02"; repo_new "$r"
write_file "$r" "app/Foo.php" <<'EOF'
<?php
class Foo {}
EOF
commit_all "$r" base
git -C "$r" checkout -q -b feature
write_file "$r" "app/Foo.php" <<'EOF'
<?php
class Foo {}
var_dump($user);
EOF
commit_all "$r" "add debug"
run_checker "$r" 1 "r02 newly added PHP var_dump( is blocked" "app/Foo.php" \
    -- --profile no-debug-statements --range main feature

# 3r. pre-existing console.log + unrelated edit → PASS
r="$TMP/r03"; repo_new "$r"
legacy_js 4 | write_file "$r" "resources/js/app.js"
commit_all "$r" base
git -C "$r" checkout -q -b feature
{ legacy_js 4 | sed 's/const version = 1;/const version = 2;/'; } | write_file "$r" "resources/js/app.js"
commit_all "$r" "unrelated edit"
run_checker "$r" 0 "r03 pre-existing console.log + unrelated edit passes" "" \
    -- --profile no-debug-statements --range main feature

# 4r. removing a console.log → PASS
r="$TMP/r04"; repo_new "$r"
legacy_js 4 | write_file "$r" "resources/js/app.js"
commit_all "$r" base
git -C "$r" checkout -q -b feature
{ legacy_js 4 | grep -v "legacy trace 2"; } | write_file "$r" "resources/js/app.js"
commit_all "$r" "remove one"
run_checker "$r" 0 "r04 removing a console.log passes" "" \
    -- --profile no-debug-statements --range main feature

# 5r / 6r. generated-like file: unrelated edit passes, new statement fails
r="$TMP/r05"; repo_new "$r"
legacy_js 52 | write_file "$r" "public/js/app.js"
commit_all "$r" base
git -C "$r" checkout -q -b feature
{ legacy_js 52 | sed 's/const version = 1;/const version = 2;/'; } | write_file "$r" "public/js/app.js"
commit_all "$r" "unrelated edit"
run_checker "$r" 0 "r05 generated-like file with 52 old console.log, unrelated edit, passes" "" \
    -- --profile no-debug-statements --range main feature
{ legacy_js 52 | sed 's/const version = 1;/const version = 2;/'; echo "console.log('freshly added');"; } \
    | write_file "$r" "public/js/app.js"
commit_all "$r" "add one debug"
run_checker "$r" 1 "r06 same file, one NEW console.log, is blocked" "freshly added" \
    -- --profile no-debug-statements --range main feature

# 7r / 8r. PR #135 shape: many old console.log; branch removes one and edits
#          unrelated content → PASS; then adds one → FAIL.
r="$TMP/r07"; repo_new "$r"
legacy_js 52 | write_file "$r" "public/js/app.js"
write_file "$r" "resources/js/realtime.js" <<'EOF'
export function connect() {
    return null;
}
EOF
commit_all "$r" base
git -C "$r" checkout -q -b feature
{ legacy_js 52 | grep -v "legacy trace 7"; } | write_file "$r" "public/js/app.js"
write_file "$r" "resources/js/realtime.js" <<'EOF'
export function connect() {
    if (!window.Echo) {
        return null;
    }
    return window.Echo;
}
EOF
commit_all "$r" "guard realtime client"
run_checker "$r" 0 "r07 PR#135 shape (remove one, edit unrelated) passes" "" \
    -- --profile no-debug-statements --range main feature
run_checker "$r" 0 "r07b same, under the check-debug-statements profile" "" \
    -- --profile check-debug-statements --range main feature
write_file "$r" "resources/js/realtime.js" <<'EOF'
export function connect() {
    console.log('connecting');
    return window.Echo;
}
EOF
commit_all "$r" "add debug"
run_checker "$r" 1 "r08 then adding one new console.log is blocked" "realtime.js" \
    -- --profile no-debug-statements --range main feature

# 9r. filename containing spaces
r="$TMP/r09"; repo_new "$r"
legacy_js 3 | write_file "$r" "resources/js/my widget file.js"
commit_all "$r" base
git -C "$r" checkout -q -b feature
{ legacy_js 3; echo "console.log('added in spaced file');"; } | write_file "$r" "resources/js/my widget file.js"
commit_all "$r" "add debug"
run_checker "$r" 1 "r09 filename with spaces is scanned and blocked" "my widget file.js" \
    -- --profile no-debug-statements --range main feature

# 10r. filename containing *
r="$TMP/r10"; repo_new "$r"
write_file "$r" 'resources/js/star*name.js' <<'EOF'
const a = 1;
EOF
write_file "$r" 'resources/js/decoy.js' <<'EOF'
console.log('decoy pre-existing');
EOF
commit_all "$r" base
git -C "$r" checkout -q -b feature
write_file "$r" 'resources/js/star*name.js' <<'EOF'
const a = 2;
EOF
commit_all "$r" "unrelated edit"
run_checker "$r" 0 "r10 * filename, unrelated edit, passes without pulling in decoy" "" \
    -- --profile no-debug-statements --range main feature
write_file "$r" 'resources/js/star*name.js' <<'EOF'
const a = 2;
console.log('globbed');
EOF
commit_all "$r" "add debug"
run_checker "$r" 1 "r10b * filename with new console.log is blocked" "globbed" \
    -- --profile no-debug-statements --range main feature

# 11r. source line literally beginning +++
r="$TMP/r11"; repo_new "$r"
write_file "$r" "resources/js/diffy.js" <<'EOF'
const a = 1;
EOF
commit_all "$r" base
git -C "$r" checkout -q -b feature
write_file "$r" "resources/js/diffy.js" <<'EOF'
const a = 1;
+++ this line literally starts with plus signs
EOF
commit_all "$r" "plus line"
run_checker "$r" 0 "r11 added line starting with +++ is not mistaken for a diff header" "" \
    -- --profile no-debug-statements --range main feature

# 12r. pure rename carrying old debug content → PASS
r="$TMP/r12"; repo_new "$r"
legacy_js 20 | write_file "$r" "resources/js/old-name.js"
commit_all "$r" base
git -C "$r" checkout -q -b feature
git -C "$r" mv "resources/js/old-name.js" "resources/js/new-name.js"
commit_all "$r" "rename"
run_checker "$r" 0 "r12 pure rename of a debug-laden file passes" "" \
    -- --profile no-debug-statements --range main feature

# 12rb. rename PLUS a newly added console.log → FAIL
r="$TMP/r12b"; repo_new "$r"
legacy_js 20 | write_file "$r" "resources/js/old-name.js"
commit_all "$r" base
git -C "$r" checkout -q -b feature
git -C "$r" mv "resources/js/old-name.js" "resources/js/new-name.js"
{ legacy_js 20; echo "console.log('added while renaming');"; } | write_file "$r" "resources/js/new-name.js"
commit_all "$r" "rename and add debug"
run_checker "$r" 1 "r12b rename that also adds a console.log is blocked" "added while renaming" \
    -- --profile no-debug-statements --range main feature

# 13r. deleted file → ignored
r="$TMP/r13"; repo_new "$r"
legacy_js 10 | write_file "$r" "resources/js/doomed.js"
write_file "$r" "resources/js/keep.js" <<'EOF'
const k = 1;
EOF
commit_all "$r" base
git -C "$r" checkout -q -b feature
rm "$r/resources/js/doomed.js"
commit_all "$r" "delete"
run_checker "$r" 0 "r13 deleting a debug-laden file passes" "" \
    -- --profile no-debug-statements --range main feature

# 14r. multi-commit branch, debug added in an EARLIER branch commit → FAIL
r="$TMP/r14"; repo_new "$r"
write_file "$r" "resources/js/app.js" <<'EOF'
const x = 1;
EOF
commit_all "$r" base
git -C "$r" checkout -q -b feature
write_file "$r" "resources/js/app.js" <<'EOF'
const x = 1;
console.log('added in commit 1 of the branch');
EOF
commit_all "$r" "branch commit 1 (adds debug)"
write_file "$r" "resources/js/other.js" <<'EOF'
export const y = 2;
EOF
commit_all "$r" "branch commit 2 (unrelated)"
write_file "$r" "resources/js/other.js" <<'EOF'
export const y = 3;
EOF
commit_all "$r" "branch commit 3 (unrelated)"
run_checker "$r" 1 "r14 debug added in an earlier branch commit is still caught" \
    "added in commit 1 of the branch" \
    -- --profile no-debug-statements --range main feature

# 15r. base branch advanced after the branch point → no false positive.
#      main gains a console.log of its own; the feature branch must not own it.
r="$TMP/r15"; repo_new "$r"
write_file "$r" "resources/js/app.js" <<'EOF'
const x = 1;
EOF
commit_all "$r" base
git -C "$r" checkout -q -b feature
write_file "$r" "resources/js/feature.js" <<'EOF'
export const f = 1;
EOF
commit_all "$r" "feature work"
git -C "$r" checkout -q main
write_file "$r" "resources/js/mainline.js" <<'EOF'
console.log('added on main after the branch point');
EOF
commit_all "$r" "mainline debug"
git -C "$r" checkout -q feature
run_checker "$r" 0 "r15 base advancing with its own console.log is not the branch's fault" "" \
    -- --profile no-debug-statements --range main feature

# 15rb. proof the two-dot semantics we replaced WOULD have been wrong here:
#       the checker must still fail when the branch itself adds one.
write_file "$r" "resources/js/feature.js" <<'EOF'
export const f = 1;
console.log('this one really is ours');
EOF
commit_all "$r" "feature debug"
run_checker "$r" 1 "r15b the branch's own console.log is still caught after base moved" \
    "this one really is ours" \
    -- --profile no-debug-statements --range main feature

# ══════════════════════════════════════════════════════════════════════════════
echo ""
echo "── PROFILE PRESERVATION ─────────────────────────────────────"
#
# Each gate keeps its own extension list and pattern set.  These cases pin the
# differences so a future "tidy-up" that harmonises them fails loudly here.

# .mjs and .svelte: in scope for no-debug-statements and pre-commit, NOT for
# check-debug-statements (whose JS list is js|ts|jsx|tsx|vue).
r="$TMP/p01"; repo_new "$r"
write_file "$r" "resources/js/helper.mjs" <<'EOF'
export const a = 1;
EOF
commit_all "$r" base
git -C "$r" checkout -q -b feature
write_file "$r" "resources/js/helper.mjs" <<'EOF'
export const a = 1;
console.log('mjs debug');
EOF
commit_all "$r" "add debug"
run_checker "$r" 1 "p01 .mjs is in scope for no-debug-statements" "helper.mjs" \
    -- --profile no-debug-statements --range main feature
run_checker "$r" 0 "p02 .mjs is NOT in scope for check-debug-statements (preserved as-is)" "" \
    -- --profile check-debug-statements --range main feature
run_checker "$r" 1 "p03 .mjs is in scope for pre-commit" "helper.mjs" \
    -- --profile pre-commit --range main feature

r="$TMP/p04"; repo_new "$r"
write_file "$r" "resources/js/w.svelte" <<'EOF'
<script> let c = 0; </script>
EOF
commit_all "$r" base
git -C "$r" checkout -q -b feature
write_file "$r" "resources/js/w.svelte" <<'EOF'
<script> let c = 0; console.log('svelte'); </script>
EOF
commit_all "$r" "add debug"
run_checker "$r" 1 "p04 .svelte is in scope for no-debug-statements" "w.svelte" \
    -- --profile no-debug-statements --range main feature
run_checker "$r" 0 "p05 .svelte is NOT in scope for check-debug-statements (preserved as-is)" "" \
    -- --profile check-debug-statements --range main feature

# Twig: only the no-debug-statements and pre-commit profiles have a twig group.
r="$TMP/p06"; repo_new "$r"
write_file "$r" "templates/home.twig" <<'EOF'
{{ variable }}
EOF
commit_all "$r" base
git -C "$r" checkout -q -b feature
write_file "$r" "templates/home.twig" <<'EOF'
{{ variable }}
{{ dump(variable) }}
EOF
commit_all "$r" "add dump"
run_checker "$r" 1 "p06 twig dump( is blocked by no-debug-statements" "home.twig" \
    -- --profile no-debug-statements --range main feature
run_checker "$r" 1 "p07 twig dump( is blocked by pre-commit" "home.twig" \
    -- --profile pre-commit --range main feature
run_checker "$r" 0 "p08 twig is NOT in scope for check-debug-statements (preserved as-is)" "" \
    -- --profile check-debug-statements --range main feature

# PHP dump(: prohibited by the pre-commit profile only.
r="$TMP/p09"; repo_new "$r"
write_file "$r" "app/Foo.php" <<'EOF'
<?php
class Foo {}
EOF
commit_all "$r" base
git -C "$r" checkout -q -b feature
write_file "$r" "app/Foo.php" <<'EOF'
<?php
class Foo {}
dump($user);
EOF
commit_all "$r" "add dump"
run_checker "$r" 1 "p09 PHP dump( is blocked by the pre-commit profile" "Foo.php" \
    -- --profile pre-commit --range main feature
run_checker "$r" 0 "p10 PHP dump( is NOT blocked by no-debug-statements (preserved as-is)" "" \
    -- --profile no-debug-statements --range main feature
run_checker "$r" 0 "p11 PHP dump( is NOT blocked by check-debug-statements (preserved as-is)" "" \
    -- --profile check-debug-statements --range main feature

# \b anchoring must survive: ->add( and ->odd( are not dd(.
r="$TMP/p12"; repo_new "$r"
write_file "$r" "app/Bar.php" <<'EOF'
<?php
class Bar {}
EOF
commit_all "$r" base
git -C "$r" checkout -q -b feature
write_file "$r" "app/Bar.php" <<'EOF'
<?php
class Bar {}
$collection->add($item);
$n = $this->odd($value);
EOF
commit_all "$r" "legit calls"
run_checker "$r" 0 "p12 ->add( and ->odd( are not treated as dd( (word anchoring kept)" "" \
    -- --profile pre-commit --range main feature

# Out-of-scope extensions are ignored entirely.
r="$TMP/p13"; repo_new "$r"
write_file "$r" "notes.md" <<'EOF'
# notes
EOF
commit_all "$r" base
git -C "$r" checkout -q -b feature
write_file "$r" "notes.md" <<'EOF'
# notes
console.log('in markdown, not code');
EOF
commit_all "$r" "md"
run_checker "$r" 0 "p13 non-source extensions are out of scope" "" \
    -- --profile no-debug-statements --range main feature

# ══════════════════════════════════════════════════════════════════════════════
echo ""
echo "── FAILING LOUDLY ───────────────────────────────────────────"
#
# An unrunnable gate must not read as a passing gate.  Exit 2 is reserved for
# "could not perform the check" and is distinct from 1 ("found violations").

# Unrelated histories → no merge base → exit 2.
r="$TMP/e01"; repo_new "$r"
write_file "$r" "a.js" <<'EOF'
const a = 1;
EOF
commit_all "$r" base
git -C "$r" checkout -q --orphan orphaned
git -C "$r" rm -rq --cached . 2>/dev/null || true
rm -f "$r/a.js"
write_file "$r" "b.js" <<'EOF'
const b = 1;
EOF
commit_all "$r" "orphan root"
run_checker "$r" 2 "e01 unrelated histories fail loudly with exit 2, not 0" "no merge base" \
    -- --profile no-debug-statements --range main orphaned

# Unresolvable revision → exit 2.
r="$TMP/e02"; repo_new "$r"
write_file "$r" "a.js" <<'EOF'
const a = 1;
EOF
commit_all "$r" base
run_checker "$r" 2 "e02 unresolvable BASE fails loudly with exit 2" "cannot resolve BASE" \
    -- --profile no-debug-statements --range does-not-exist main

# Unknown profile → exit 2.
r="$TMP/e03"; repo_new "$r"
write_file "$r" "a.js" <<'EOF'
const a = 1;
EOF
commit_all "$r" base
run_checker "$r" 2 "e03 unknown profile fails loudly with exit 2" "unknown profile" \
    -- --profile made-up-profile --staged

# Missing mode → exit 2.
run_checker "$r" 2 "e04 missing mode fails loudly with exit 2" "one of --staged or --range" \
    -- --profile pre-commit

# Missing profile → exit 2.
run_checker "$r" 2 "e05 missing profile fails loudly with exit 2" "--profile is required" \
    -- --staged

# ══════════════════════════════════════════════════════════════════════════════
echo ""
echo "── VENDORED THIRD-PARTY EXCEPTIONS ──────────────────────────"

# The one case diff-awareness does not cover: a file the change ADDS. Every line
# of a new file is an added line, so a vendored artefact committed for the first
# time would otherwise be judged on its upstream author's console.log calls.
#
# v01 pins that the two MapLibre chunks are skipped ON ADDITION — the exact
# situation, not a proxy for it.
r="$TMP/v01"; repo_new "$r"
write_file "$r" "README.md" <<'EOF'
base
EOF
commit_all "$r" base
v01_base="$(git -C "$r" rev-parse HEAD)"
write_file "$r" "public/js/spatial/maplibre-gl-shared.mjs" <<'EOF'
export function q(e){console.log("upstream trace",e);}
EOF
write_file "$r" "public/js/spatial/maplibre-gl-worker.mjs" <<'EOF'
import {q} from "./maplibre-gl-shared.mjs";console.log("worker boot");q(1);
EOF
git -C "$r" add -A
run_checker "$r" 0 "v01 newly ADDED vendored maplibre chunks are skipped (staged)" "Skipping vendored" \
    -- --profile no-debug-statements --staged
commit_all "$r" "add vendored chunks"
run_checker "$r" 0 "v01b same addition, judged as a range" "Skipping vendored" \
    -- --profile no-debug-statements --range "$v01_base" HEAD

# v02 is the half that makes v01 meaningful: OUR files in the SAME directory are
# still scanned. An exception that quietly covered its neighbours would pass v01
# and leave the gate blind to our own bundle.
r="$TMP/v02"; repo_new "$r"
write_file "$r" "README.md" <<'EOF'
base
EOF
commit_all "$r" base
write_file "$r" "public/js/spatial/ldna-maplibre.js" <<'EOF'
const r = 1;console.log("ours, in the bundle");
EOF
git -C "$r" add -A
run_checker "$r" 1 "v02 our generated bundle beside them is still scanned" "ldna-maplibre.js" \
    -- --profile no-debug-statements --staged

r="$TMP/v03"; repo_new "$r"
write_file "$r" "README.md" <<'EOF'
base
EOF
commit_all "$r" base
write_file "$r" "resources/js/spatial/ldna-basemap.js" <<'EOF'
export const a = 1;
console.log('ours, in source');
EOF
git -C "$r" add -A
run_checker "$r" 1 "v03 our authored spatial source is still scanned" "ldna-basemap.js" \
    -- --profile no-debug-statements --staged

# v04 — the exception is by EXACT path, so a lookalike name does not inherit it.
r="$TMP/v04"; repo_new "$r"
write_file "$r" "README.md" <<'EOF'
base
EOF
commit_all "$r" base
write_file "$r" "public/js/spatial/maplibre-gl-shared.min.mjs" <<'EOF'
console.log("not the vendored path");
EOF
git -C "$r" add -A
run_checker "$r" 1 "v04 a lookalike path does not inherit the exception" "maplibre-gl-shared.min.mjs" \
    -- --profile no-debug-statements --staged

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
