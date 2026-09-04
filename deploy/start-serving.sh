#!/usr/bin/env bash
#
# Supervisor restart — serve the already-deployed commit, and NEVER change it.
#
# WHY A SECOND ENTRY POINT
# ------------------------
# Two different events start a web server here, and they are not the same event:
#
#   EXPLICIT DEPLOYMENT   .replit [deployment] -> deploy/start-production.sh
#       Ships a new release. Takes the deploy lock, reports preflight, applies
#       migrations, records the SHA it is serving, then serves.
#
#   SUPERVISOR RESTART    port-5000 workflow  -> this script
#       Ships nothing. A container restart, a workflow re-run, or someone
#       pressing the button. The commit is already chosen and already migrated.
#
# Until now the restart path ran a bare `php artisan serve`. That will happily
# serve new code against an old schema, which is the failure mode the whole
# deployment-hardening effort exists to close: the application answers requests
# while every write that touches a missing column returns a 500.
#
# The obvious fix — point the restart at start-production.sh — is worse. It would
# make MIGRATING PRODUCTION a side effect of a container restart, at a moment
# nobody chose, with no deploy log anyone is reading. Migration is a deployment
# decision; a restart is not entitled to make it.
#
# So this script is the third option: verify, then serve, or refuse.
#
# WHY THE CHECK IS AN EXIT CODE AND NOT A PARSED TABLE
# ----------------------------------------------------
# `migrate:status` cannot gate anything on Laravel 8 — it renders a table and
# returns null, so it exits 0 whether nothing is pending or everything is.
# `deploy:preflight` always exits 0 by design; it reports, it does not gate.
# `deploy:migrations-pending` (PR #113) exists for exactly this call site:
#
#     0  ready         every migration this code ships is applied
#     1  pending       at least one is not
#     2  undetermined  the answer could not be established
#
# It is read-only: two SELECTs, no writes, no migration, no filesystem change.
#
# FAIL CLOSED, INCLUDING ON CODES THAT DO NOT EXIST YET
# -----------------------------------------------------
# Anything that is not 0 refuses to serve. That deliberately includes exit codes
# nobody has defined. A restart that serves while the schema state is unknown is
# indistinguishable, from the outside, from one that serves while the schema is
# known-bad — and the second is what this is here to prevent.
#
# WHY IT TAKES THE SAME LOCK
# --------------------------
# A restart landing in the middle of a deployment's migration would read a schema
# that is half-applied and get an answer that was true for neither the old
# release nor the new one. The lock is the same `flock` the deployment takes, via
# the same helper, so the two paths genuinely exclude each other rather than
# merely intending to.
#
# The timeout is longer here (180s vs the deployment's 30s) and that asymmetry is
# the point. A deployment queued behind another deployment is an outage with
# extra steps, so it should fail fast and be seen. A restart queued behind a
# deployment is just a restart that ought to wait for the deploy to finish —
# waiting is the correct answer, and 180s covers a normal migration run while
# staying bounded.
#
# WHAT THIS SCRIPT MUST NEVER DO
# ------------------------------
#   * migrate, in any form
#   * write the recorded deploy SHA — repinning production to a new commit is a
#     deployment event, and a restart that could repin would make "what is
#     running?" unanswerable again
#   * terminate whatever holds port 5000 — the supervisor owns that process, and
#     a script that reaches for its own predecessor races the supervisor's own
#     restart logic
#   * background the server, which would orphan it from process supervision
#
# See deploy/DEPLOYMENT.md and tests/Feature/Deployment/DeployStartServingTest.php.
#
set -euo pipefail

# Resolve the repository from the SCRIPT's location, never the caller's cwd.
# The supervisor's working directory is not something this script may assume.
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# ── production runtime environment ──────────────────────────────────────────
# Set unconditionally, and BEFORE any Laravel process starts.
#
# NOT `${APP_ENV:-production}`. A `:-` default keeps whatever the parent already
# supplied, and a parent value being present and WRONG is exactly the situation:
# `.replit` used to declare APP_ENV=local and APP_DEBUG=true under
# `[userenv.shared]`, and the live web process was verified running with both.
# APP_DEBUG=true renders full stack traces — including database credentials and
# environment values — to anyone who triggers an error.
#
# `config/app.php` already defaults safely (`env('APP_ENV', 'production')`,
# `env('APP_DEBUG', false)`), so the danger was never a missing default; it was
# an ambient value that was present and wrong. Overriding is what is required.
#
# This works because Illuminate\Support\Env builds an IMMUTABLE phpdotenv
# repository, which will not overwrite a variable already in the process
# environment — so what is exported here beats the same key in `.env`.
#
# It only works while no configuration cache exists. A cached config freezes
# every env() result at build time and makes Laravel skip `.env` entirely, so a
# run-time export would be inert. That is why `config:cache` was removed from the
# `[deployment] build`. See deploy/DEPLOYMENT.md ("Configuration cache policy").
export APP_ENV=production
export APP_DEBUG=false

# ── PHP runtime ─────────────────────────────────────────────────────────────
# The same PHP configuration the deployment and the scheduler load.
#
# AFTER the exports above, deliberately: resolving the interpreter's own scan
# directory starts a PHP process, and that process must not be the one that sees
# a hostile parent APP_ENV / APP_DEBUG.
#
# shellcheck source=deploy/lib/php-runtime.sh
. "$PWD/deploy/lib/php-runtime.sh"

configure_php_ini_scan_dir "$PWD/deploy/php"

# shellcheck source=deploy/lib/deploy-state.sh
. "$PWD/deploy/lib/deploy-state.sh"

# Longer than the deployment's bound, for the reason in the header. Still
# bounded: a restart that queues forever is a process the supervisor waits on
# with nothing to show for it.
DEPLOY_LOCK_TIMEOUT="${DEPLOY_LOCK_TIMEOUT:-180}"
export DEPLOY_LOCK_TIMEOUT

if ! acquire_deploy_lock; then
    echo "start-serving: a deployment holds the deploy lock; refusing to start." >&2
    exit 1
fi

# ── read-only readiness gate ────────────────────────────────────────────────
# The exit code is the entire contract. Nothing here reads the command's text.
if php artisan deploy:migrations-pending; then
    readiness=0
else
    readiness=$?
fi

case "$readiness" in
    0)
        ;;
    1)
        echo "start-serving: production schema is not ready — migrations are pending." >&2
        echo "start-serving: run the explicit production deployment path before serving." >&2
        release_deploy_lock
        exit 1
        ;;
    2)
        echo "start-serving: production schema readiness could not be determined." >&2
        echo "start-serving: refusing to serve." >&2
        release_deploy_lock
        exit 1
        ;;
    *)
        echo "start-serving: readiness check returned an unrecognised status ${readiness}." >&2
        echo "start-serving: refusing to serve." >&2
        release_deploy_lock
        exit 1
        ;;
esac

# ── required product flags: INFORMATIONAL ONLY ──────────────────────────────
# `--report` plus `|| true`, and both are load-bearing.
#
# THIS IS NOT THE PRODUCTION GATE. deploy/start-production.sh calls the same
# command WITHOUT `--report` and WITHOUT `|| true`, and that call can refuse a
# deployment. This one can refuse nothing: `--report` makes the command exit 0
# whatever it finds, and `|| true` means even an unexpected non-zero — a fatal
# error, a missing command after a bad checkout — cannot stop this script.
# Belt and braces on purpose: it must be impossible for a visibility line to cost
# somebody their local server.
#
# WHY IT IS HERE AT ALL. The workspace is where a flag gets flipped to try
# something and then forgotten. Printing the contract on every start means the
# answer to "why does my listing page look old" is already in the terminal
# scrollback, rather than being rediscovered by reading config files.
#
# WHY IT IS NOT A GATE HERE. Turning a flag off locally to test the legacy path
# is legitimate work. A workspace that refused to start until the modern platform
# was fully enabled would make that work impossible, and would convert a
# development convenience into a deployment-grade constraint on the one machine
# where experimenting is the point.
#
# Read-only, like every other call to this command: it compares and prints.
php artisan deploy:require-flags --report || true

# Hand the lock back before the server takes over. Holding it across `exec` would
# leave the serving process owning it for its whole life, and no future
# deployment could ever acquire it.
release_deploy_lock

# `exec` on purpose: the server replaces this shell, so the supervisor's SIGTERM
# reaches the server directly rather than a wrapper that may not forward it.
#
# ── why --no-reload is load-bearing, not a tuning flag ──────────────────────
# `artisan serve` does not handle requests. It spawns a SECOND process
# (`php -S … server.php`) and that child is the web server. Everything this
# script has established so far — the exports above, and the scan dir
# `configure_php_ini_scan_dir` just composed — lands in THIS shell, which is only
# the launcher. What reaches the child is decided by
# Illuminate\Foundation\Console\ServeCommand::startProcess(), and when a `.env`
# file exists (ours does) that method filters the child's environment down to a
# fixed allowlist and DELETES everything else:
#
#     APP_ENV, LARAVEL_SAIL, PHP_CLI_SERVER_WORKERS, PHP_IDE_CONFIG,
#     SYSTEMROOT, XDEBUG_CONFIG, XDEBUG_MODE, XDEBUG_SESSION
#
# `APP_ENV` is on that list. `APP_DEBUG` is not, and neither is
# `PHP_INI_SCAN_DIR`. Measured on the tip that shipped the scan-dir repair: the
# worker held APP_ENV=production, no APP_DEBUG at all, and a PHP_INI_SCAN_DIR
# reset to the interpreter's own default. It fell through to `.env`, where
# APP_DEBUG=true, and a single bad route parameter returned 659,514 bytes across
# 168 stack frames — SQL text and absolute paths included.
#
# So the launcher was correct and the server was not. That is also why
# deploy/php/uploads.ini never reached the process handling uploads, however
# carefully the scan dir was composed upstream of this line.
#
# `--no-reload` takes the other branch of that same conditional and passes the
# launcher's environment through unfiltered. It additionally switches off the
# `.env`-mtime watcher that restarts the server when that file changes, which
# production has no use for: a release changes code, not `.env` beneath a running
# server, and an unannounced self-restart mid-request is a liability.
#
# Nothing else changes. The process topology is identical with and without it
# (launcher plus one `php -S` child binding the port), so `exec`, SIGTERM
# delivery and supervisor restart behaviour are untouched.
#
# Proven by tests/Feature/Deployment/ServeWorkerRuntimeEnvironmentTest.php, which
# boots a real server from this line and reads the worker's own environment back.
exec php artisan serve --host=0.0.0.0 --port=5000 --no-reload
