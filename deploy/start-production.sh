#!/usr/bin/env bash
#
# G4.5 — production web start sequence for the Replit VM deployment.
#
# WHY THIS SCRIPT EXISTS
# ----------------------
# The deployment used to start the web server directly and nothing ever applied
# pending migrations. Merging a migration therefore shipped the *code* that
# expects a column without the *column*, and the first write would fail at
# runtime with "undefined column" — inside whatever request happened to trigger
# it, which for a listing save is a user-facing 500.
#
# That gap was not obvious, because a migration hook does exist:
# `scripts/post-merge.sh` runs `php artisan migrate --force`. But it is wired to
# the Replit `[postMerge]` hook, so it fires when a merge happens *in the
# workspace* — not on deploy, and not when a pull request is merged on GitHub.
# The one mechanism that migrated was the one that did not run on the path we
# actually use to ship.
#
# ORDER MATTERS, AND SO DOES FAILING
# ----------------------------------
# Migrations run BEFORE the server binds a port, and the server starts only if
# they succeeded. `set -euo pipefail` plus explicit `&&` chaining means a failed
# migration exits non-zero without ever reaching `serve`, so the deployment
# fails its health check instead of quietly serving new code against an old
# schema. A half-migrated deployment that answers requests is worse than one
# that never starts: it looks healthy while corrupting or rejecting writes.
#
# WHY MIGRATIONS ARE HERE AND NOT IN THE BUILD PHASE
# --------------------------------------------------
# The build phase runs before the runtime environment is assembled and is not
# guaranteed to hold database credentials. It also runs at image-build time,
# which is the wrong moment: the schema must be current when *this release*
# starts serving, not when its artifact was compiled.
#
# WHY ONLY THIS PROCESS MIGRATES
# ------------------------------
# Production runs more than one process — this web server, and a separate
# scheduler (see deploy/SCHEDULER.md). Exactly one of them may own migrations.
#
# This matters more than it usually would, because **this application is on
# Laravel 8, which has no `migrate --isolated`** — the built-in migration lock
# arrived in Laravel 9. There is no mutex available, so concurrency safety comes
# entirely from single ownership. `deploy/scheduler.sh` must never call migrate,
# and DeploymentMigrationReadinessTest asserts that it does not.
#
# Re-running is harmless: Laravel skips migrations already recorded in the
# `migrations` table, so a crash-loop or a restart replays nothing.
#
set -euo pipefail

cd "$(dirname "$0")/.."

# Mirror the PHP ini scan dir used by the workflow and the scheduler so every
# process loads the same PHP configuration.
export PHP_INI_SCAN_DIR="$PWD/deploy/php"

# Report what we are about to migrate against. Informational only — it never
# fails the deploy — but it puts APP_ENV, APP_DEBUG and the pending-migration
# count in the deployment log, which is the only place some of those questions
# can currently be answered. See deploy/DEPLOYMENT.md.
php artisan deploy:preflight || true

# Apply pending migrations. --force because a production APP_ENV would otherwise
# prompt; --no-interaction because there is no terminal attached.
#
# Never migrate:fresh / migrate:reset / migrate:refresh / db:wipe here. Those
# destroy data and have no place in a start command.
php artisan migrate --force --no-interaction

# Only now serve traffic.
exec php artisan serve --host=0.0.0.0 --port=5000
