#!/usr/bin/env bash
#
# The PHP runtime configuration shared by every production entrypoint.
#
# WHY THIS FILE EXISTS
# --------------------
# `deploy/php/uploads.ini` raises the upload limits for the request-handling PHP
# process, and `PHP_INI_SCAN_DIR` is the only vector that reaches it: the app is
# served by `php artisan serve` (the built-in `cli-server` SAPI), which ignores
# `.user.ini`, and Laravel 8's ServeCommand does not forward `-d` flags to the
# worker it spawns. The environment variable does survive, so that is what all
# three entrypoints use.
#
# WHAT WENT WRONG WITH THE OBVIOUS FORM
# -------------------------------------
# All three did this:
#
#     export PHP_INI_SCAN_DIR="$PWD/deploy/php"
#
# `PHP_INI_SCAN_DIR` REPLACES the interpreter's scan directory. It does not add
# to it. On this platform that directory is where every extension is declared,
# so the assignment above loaded `uploads.ini` exactly as intended and unloaded
# everything else: 54 extensions became 12, with no PDO, no pdo_pgsql and no
# tokenizer. Nothing announced it. The limits it was written to apply really did
# apply, which is why it read as a working configuration change for months.
#
# The bill came due at the readiness gate. `deploy:migrations-pending` calls
# `Migrator::repositoryExists()`; with no PDO that call throws before reaching a
# database, and the fail-closed gate reported `repository_unreadable` and exit 2.
# The database, the credentials, the migrations table and the schema were all
# healthy. The process asking the question simply had no way to ask it.
#
# WHY NOT THE DOCUMENTED ONE-CHARACTER FIX
# ----------------------------------------
# PHP documents an empty path element as meaning "the compiled-in default", so
# `":$PWD/deploy/php"` ought to be the whole repair. It is not, here. The value
# substituted for an empty element is `PHP_CONFIG_FILE_SCAN_DIR`, and on this
# build that constant is DEFINED BUT EMPTY — the real default arrives by another
# route and is only ever visible by asking the interpreter. Leading, trailing and
# doubled colon forms were all measured on this platform; every one of them loses
# PDO. So the default has to be discovered at run time and named explicitly.
#
# Discovered, never hardcoded: the path is a Nix store entry whose hash changes
# whenever the channel moves, so pinning today's value would reintroduce this
# outage on the next upgrade with no diff to point at.
#
# WHY A SOURCED HELPER RATHER THAN THREE INLINE COPIES
# ----------------------------------------------------
# Three inline copies is how the broken one-liner came to be in all three files.
# One definition means one place to be right, and — more usefully — one place a
# test can execute rather than pattern-match. See
# tests/Feature/Deployment/PhpIniScanDirTest.php, which runs the real
# entrypoints, reads back the value they actually exported, and starts a real PHP
# with it to ask what survived.
#
# ORDERING REQUIREMENT
# --------------------
# Resolving the default starts a PHP process, so every caller must already have
# exported its production APP_ENV / APP_DEBUG before calling in. Otherwise that
# one process observes the parent's environment, which for this application has
# meant APP_ENV=local and APP_DEBUG=true. The tests assert the ordering in each
# entrypoint.
#
# shellcheck shell=bash

# The scan directory this interpreter uses when nothing overrides it.
#
# Printed empty when the interpreter reports none, so callers can distinguish
# "nothing to preserve" from a lookup that failed. `env -u` matters: without it
# an already-exported (possibly already-broken) value would be echoed straight
# back and the repair would preserve the damage.
php_default_ini_scan_dir() {
    local reported=''

    reported="$(env -u PHP_INI_SCAN_DIR php --ini 2>/dev/null \
        | sed -n 's/^Scan for additional \.ini files in:[[:space:]]*//p' \
        | head -n 1)" || true

    # Trim trailing whitespace; PHP pads this line on some builds.
    reported="${reported%"${reported##*[![:space:]]}"}"

    case "$reported" in
        ''|'(none)') printf '' ;;
        *)           printf '%s' "$reported" ;;
    esac
}

# Export PHP_INI_SCAN_DIR as the interpreter's own directory PLUS an overlay.
#
# Usage: configure_php_ini_scan_dir "$PWD/deploy/php"
#
# The overlay goes last so its directives win on a conflict — which is the point
# of an overlay — while the defaults it does not mention stay loaded. Assigned
# unconditionally, not with `:-`: a parent value that is present and wrong is the
# situation this whole file exists to correct.
configure_php_ini_scan_dir() {
    local overlay="${1:?configure_php_ini_scan_dir requires an overlay directory}"
    local default=''

    default="$(php_default_ini_scan_dir)"

    if [ -n "$default" ]; then
        export PHP_INI_SCAN_DIR="${default}:${overlay}"
    else
        # No default to preserve. The overlay alone is then correct rather than
        # destructive, and is also what the fake interpreters in the deployment
        # tests produce.
        export PHP_INI_SCAN_DIR="${overlay}"
    fi
}
