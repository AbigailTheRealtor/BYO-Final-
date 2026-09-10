#!/usr/bin/env bash
# scripts/ci/check-added-debug-statements.sh
#
# Shared, diff-aware debug-statement gate.
#
# WHY THIS EXISTS
# ───────────────
# The three debug-statement gates (two CI workflows and the pre-commit hook)
# used to grep the ENTIRE CONTENT of every changed file.  That makes the gate a
# function of the file's history rather than of the change under review: editing
# one unrelated line in a file that already contained debug statements failed the
# build, and the only way to go green was to clean up code the PR never touched.
# This script inspects ONLY LINES THE CHANGE ADDS, so a gate fires when a debug
# statement is introduced and stays quiet when one is merely nearby.
#
# WHAT IT DELIBERATELY DOES NOT DO
# ────────────────────────────────
# It does not harmonise the three gates.  Each caller selects a --profile whose
# extension list, prohibited patterns and human-facing messages reproduce that
# gate's existing behaviour exactly.  The profiles differ from one another on
# purpose; changing what a gate prohibits is a separate decision from changing
# which lines it looks at.
#
# USAGE
#   check-added-debug-statements.sh --profile <name> --staged
#   check-added-debug-statements.sh --profile <name> --range <BASE> <HEAD>
#
# PROFILES
#   check-debug-statements   mirrors .github/workflows/check-debug-statements.yml
#   no-debug-statements      mirrors .github/workflows/no-debug-statements.yml
#   pre-commit               mirrors .githooks/pre-commit
#
# EXIT CODES
#   0  no debug statements were ADDED
#   1  one or more debug statements were ADDED (the gate fails)
#   2  the check could not be performed (bad usage, unresolvable revisions,
#      missing merge base).  Never confused with 0 — an unrunnable gate must
#      not read as a passing gate.

set -euo pipefail

PROG="$(basename "$0")"

# The well-known empty tree.  Used as the base in --staged mode when HEAD does
# not exist yet (the very first commit in a repository).
EMPTY_TREE='4b825dc642cb6eb9a060e54bf8d69288fbee4904'

die() {
    echo "" >&2
    echo "$PROG: FATAL: $*" >&2
    echo "" >&2
    exit 2
}

usage() {
    cat >&2 <<'USAGE'
Usage:
  check-added-debug-statements.sh --profile <name> --staged
  check-added-debug-statements.sh --profile <name> --range <BASE> <HEAD>

Profiles:
  check-debug-statements   PHP: dd( var_dump(          | JS(.js .ts .jsx .tsx .vue)
  no-debug-statements      PHP: dd( var_dump(          | JS(+ .mjs .svelte) | Twig: dump(
  pre-commit               PHP: dd( var_dump( dump(    | JS(+ .mjs .svelte) | Twig: dump(

Modes:
  --staged                 inspect lines added by the staged change (index vs HEAD)
  --range <BASE> <HEAD>    inspect lines added between merge-base(BASE,HEAD) and HEAD
USAGE
}

# ── Argument parsing ──────────────────────────────────────────────────────────

PROFILE=""
MODE=""
RANGE_BASE=""
RANGE_HEAD=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --profile)
            [[ $# -ge 2 ]] || die "--profile requires a value"
            PROFILE="$2"
            shift 2
            ;;
        --profile=*)
            PROFILE="${1#*=}"
            shift
            ;;
        --staged)
            [[ -z "$MODE" ]] || die "--staged and --range are mutually exclusive"
            MODE="staged"
            shift
            ;;
        --range)
            [[ -z "$MODE" ]] || die "--staged and --range are mutually exclusive"
            [[ $# -ge 3 ]] || die "--range requires <BASE> <HEAD>"
            MODE="range"
            RANGE_BASE="$2"
            RANGE_HEAD="$3"
            shift 3
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            usage
            die "unknown argument: $1"
            ;;
    esac
done

[[ -n "$PROFILE" ]] || { usage; die "--profile is required"; }
[[ -n "$MODE" ]] || { usage; die "one of --staged or --range is required"; }

# ── Profiles ──────────────────────────────────────────────────────────────────
#
# GROUP_EXT[i] / GROUP_PAT[i] are parallel arrays: an extension alternation and
# the prohibited-pattern ERE applied to files with those extensions.  A file
# belongs to at most one group; extension lists within a profile are disjoint,
# which reproduces the if/elif structure the gates used.
#
# These values are transcribed from the gates as they stood at b4c6103bb and
# must not be "tidied" — each gate keeps its own set on purpose.

declare -a GROUP_EXT=()
declare -a GROUP_PAT=()

case "$PROFILE" in
    check-debug-statements)
        GROUP_EXT+=( 'php' );                       GROUP_PAT+=( '\bdd\(|\bvar_dump\(' )
        GROUP_EXT+=( 'js|ts|jsx|tsx|vue' );         GROUP_PAT+=( 'console\.log\(|debugger;' )
        ;;
    no-debug-statements)
        GROUP_EXT+=( 'php' );                             GROUP_PAT+=( '\bdd\(|\bvar_dump\(' )
        GROUP_EXT+=( 'js|mjs|jsx|ts|tsx|vue|svelte' );    GROUP_PAT+=( 'console\.log\(|debugger;' )
        GROUP_EXT+=( 'twig' );                            GROUP_PAT+=( 'dump\(' )
        ;;
    pre-commit)
        GROUP_EXT+=( 'php' );                             GROUP_PAT+=( '\bdd\(|\bvar_dump\(|\bdump\(' )
        GROUP_EXT+=( 'js|mjs|jsx|ts|tsx|vue|svelte' );    GROUP_PAT+=( 'console\.log\(|debugger;' )
        GROUP_EXT+=( 'twig' );                            GROUP_PAT+=( 'dump\(' )
        ;;
    *)
        usage
        die "unknown profile: $PROFILE"
        ;;
esac

# Human-facing text, reproduced verbatim from each gate so that a failing build
# reads exactly as it did before.  (The pre-commit "Patterns checked" block does
# not mention dump( even though the hook's PHP pattern includes it; that wording
# predates this change and is preserved rather than corrected here.)
print_header() {
    case "$PROFILE" in
        check-debug-statements)
            echo "ERROR: Debug statements detected in changed files (application and test files are both checked):"
            ;;
        no-debug-statements)
            echo "ERROR: Debug statements detected in changed files:"
            ;;
        pre-commit)
            echo "ERROR: Debug statements detected in staged files:"
            ;;
    esac
}

print_footer() {
    case "$PROFILE" in
        check-debug-statements)
            echo "Please remove these debug statements before merging."
            echo "Patterns checked — PHP: dd(, var_dump(  |  JS/TS: console.log(, debugger;"
            ;;
        no-debug-statements)
            echo "Please remove these debug statements before merging."
            echo "Patterns checked:"
            echo "  PHP (*.php)                  : dd(, var_dump("
            echo "  JS/TS (*.js *.mjs *.jsx etc) : console.log(, debugger;"
            echo "  Twig (*.twig)                : dump("
            ;;
        pre-commit)
            echo "Please remove these debug statements before committing."
            echo "Patterns checked:"
            echo "  PHP (*.php)                  : dd(, var_dump("
            echo "  JS/TS (*.js *.mjs *.jsx etc) : console.log(, debugger;"
            echo "  Twig (*.twig)                : dump("
            echo "If this is intentional, bypass with: git commit --no-verify"
            ;;
    esac
}

print_success() {
    case "$PROFILE" in
        check-debug-statements) echo "No debug statements found." ;;
        no-debug-statements)    echo "No debug statements found in changed files." ;;
        pre-commit)             : ;;  # the hook was silent on success; stay silent
    esac
}

# ── Mode setup: build the git diff argument vector ────────────────────────────
#
# DIFF_ARGS is the leading part of every git invocation below.  Everything after
# it (--raw, --unified=0, pathspecs) is appended per call.

declare -a DIFF_ARGS=()

git rev-parse --git-dir >/dev/null 2>&1 || die "not inside a git repository"

# Anchor at the top level.  Every path handled below is repo-relative (that is
# what --raw -z emits), and git interprets a pathspec relative to the CURRENT
# directory — so running this from a subdirectory would match nothing and report
# a clean result.  A gate that scans nothing must not be able to look like a
# gate that found nothing.
TOPLEVEL="$(git rev-parse --show-toplevel)" || die "cannot determine repository root"
cd "$TOPLEVEL" || die "cannot enter repository root: $TOPLEVEL"

if [[ "$MODE" == "staged" ]]; then
    if git rev-parse --verify --quiet HEAD >/dev/null 2>&1; then
        DIFF_ARGS=( diff --cached )
    else
        # Initial commit: there is no HEAD to diff against, so compare the index
        # to the empty tree.  Every staged line is then an added line, which is
        # exactly right for a first commit.
        DIFF_ARGS=( diff --cached "$EMPTY_TREE" )
    fi
else
    git rev-parse --verify --quiet "$RANGE_BASE^{commit}" >/dev/null 2>&1 \
        || die "cannot resolve BASE revision: $RANGE_BASE"
    git rev-parse --verify --quiet "$RANGE_HEAD^{commit}" >/dev/null 2>&1 \
        || die "cannot resolve HEAD revision: $RANGE_HEAD"

    # The merge base is computed EXPLICITLY rather than relying on two-dot
    # "BASE HEAD" semantics.  Two-dot compares the tips, so commits that landed
    # on the base branch after this branch forked show up as if the branch had
    # reverted them — the gate would then read someone else's lines as this
    # change's added lines.  merge-base -> HEAD is the set of lines this branch
    # is actually responsible for, across however many commits it took.
    if ! MERGE_BASE="$(git merge-base "$RANGE_BASE" "$RANGE_HEAD" 2>/dev/null)" \
       || [[ -z "$MERGE_BASE" ]]; then
        die "no merge base between '$RANGE_BASE' and '$RANGE_HEAD'.
This usually means the checkout is shallow or the histories are unrelated.
CI must check out with fetch-depth: 0 so both commits and their common
ancestor are present.  Refusing to guess — an unrunnable gate is not a
passing gate."
    fi

    DIFF_ARGS=( diff "$MERGE_BASE" "$RANGE_HEAD" )
fi

# ── Helpers ───────────────────────────────────────────────────────────────────

# git_diff <extra args...>
# --literal-pathspecs: a file literally named "weird*.js" is a literal path, not
#   a glob.  Without this, such a filename silently matches other files or none.
# --no-ext-diff / --no-textconv: never let a configured external differ or
#   textconv filter reshape the patch we are parsing.
git_diff() {
    git --literal-pathspecs "${DIFF_ARGS[@]}" --no-ext-diff --no-textconv --no-color "$@"
}

# group_for_file <path> -> prints group index, or returns 1 if out of scope
group_for_file() {
    local f="$1" i re
    for (( i = 0; i < ${#GROUP_EXT[@]}; i++ )); do
        re="\.(${GROUP_EXT[i]})\$"
        if [[ "$f" =~ $re ]]; then
            printf '%s' "$i"
            return 0
        fi
    done
    return 1
}

TMPDIR_LOCAL="$(mktemp -d)"
trap 'rm -rf "$TMPDIR_LOCAL"' EXIT
CONTENT_FILE="$TMPDIR_LOCAL/added-content"
LINENO_FILE="$TMPDIR_LOCAL/added-linenos"

# extract_added <pathspec...>
# Writes the added lines' CONTENT to $CONTENT_FILE and their NEW-FILE LINE
# NUMBERS to $LINENO_FILE, one per line, in the same order.
#
# Splitting content from line numbers keeps the two concerns apart: the content
# file is fed to grep untouched, so a source line is matched exactly as it will
# appear in the file — no line-number prefix for a pattern to accidentally match,
# and no quoting layer for a pattern to be defeated by.
extract_added() {
    : > "$CONTENT_FILE"
    : > "$LINENO_FILE"
    git_diff --unified=0 --find-renames -- "$@" 2>/dev/null |
    LC_ALL=C awk -v lf="$LINENO_FILE" '
        # Hunk header: @@ -old,cnt +new,cnt @@ optional section heading
        # The first "+<digits>" is the new-file start line.
        /^@@/ {
            if (match($0, /\+[0-9]+/)) {
                ln = substr($0, RSTART + 1, RLENGTH - 1) + 0
                inh = 1
            }
            next
        }
        # Added line.  Strip exactly one leading "+".  A source line that itself
        # begins with "+++" arrives here as "++++" and correctly yields "+++".
        # File headers ("+++ b/path") never reach this rule: they appear before
        # the first @@, where inh is still 0.
        inh && /^\+/ { print ln > lf; print substr($0, 2); ln++; next }
        # Deleted line: consumes an OLD-file line, so it does not advance ln.
        inh && /^-/  { next }
        # "\ No newline at end of file" marker.
        inh && /^\\/ { next }
        # Context line.  --unified=0 emits none, but advance ln if one appears.
        inh && /^ /  { ln++; next }
        # Start of the next file section (reached only when inh was already
        # cleared, or when a rename pairs two paths in one patch).
        /^diff --git / { inh = 0; next }
        { next }
    ' > "$CONTENT_FILE"
}

# ── Enumerate changed files ───────────────────────────────────────────────────
#
# --raw -z gives, per entry, a NUL-terminated metadata record followed by one
# path (or two, for a rename/copy).  -z means paths are emitted raw: no quoting,
# so spaces, quotes, globs and newlines survive intact.

declare -a hits=()
scanned=0

state="meta"
status=""
npaths=0
p1=""
p2=""

process_entry() {
    local target group

    # A 100%-similarity rename or copy has no content change at all, so there
    # are no added lines to judge.  Moving a file that already contained debug
    # statements is not introducing them.
    if [[ "$status" == "R100" || "$status" == "C100" ]]; then
        return 0
    fi

    if [[ "$npaths" -eq 2 ]]; then
        target="$p2"
    else
        target="$p1"
    fi

    group="$(group_for_file "$target")" || return 0
    scanned=$(( scanned + 1 ))

    # For a rename/copy, BOTH paths go into the pathspec.  Restricting the diff
    # to the destination alone would hide the source side from git's rename
    # detection, and the file would be reported as a wholesale addition — every
    # pre-existing line counted as added.
    if [[ "$npaths" -eq 2 ]]; then
        extract_added "$p1" "$p2"
    else
        extract_added "$p1"
    fi

    [[ -s "$CONTENT_FILE" ]] || return 0

    local pattern="${GROUP_PAT[$group]}"
    local matches idx text real
    matches="$(LC_ALL=C grep -a -n -E -e "$pattern" -- "$CONTENT_FILE" || true)"
    [[ -n "$matches" ]] || return 0

    while IFS= read -r m; do
        [[ -n "$m" ]] || continue
        idx="${m%%:*}"
        text="${m#*:}"
        real="$(sed -n "${idx}p" "$LINENO_FILE")"
        hits+=("  $target:$real: $text")
    done <<< "$matches"
}

while IFS= read -r -d '' tok; do
    if [[ "$state" == "meta" ]]; then
        # ":100644 100644 <sha> <sha> <status>"
        status="${tok##* }"
        case "$status" in
            R*|C*) npaths=2 ;;
            *)     npaths=1 ;;
        esac
        p1=""
        p2=""
        state="paths"
        continue
    fi

    if [[ -z "$p1" ]]; then
        p1="$tok"
    else
        p2="$tok"
    fi

    if [[ "$npaths" -eq 1 || -n "$p2" ]]; then
        process_entry
        state="meta"
    fi
done < <(git_diff --raw -z --diff-filter=ACMR --find-renames || true)

# ── Report ────────────────────────────────────────────────────────────────────

if [[ ${#hits[@]} -gt 0 ]]; then
    echo ""
    print_header
    echo ""
    for hit in "${hits[@]}"; do
        echo "$hit"
    done
    echo ""
    print_footer
    echo ""
    exit 1
fi

print_success
exit 0
