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
# That gap was not obvious, because a migration hook USED TO exist:
# `scripts/post-merge.sh` ran `php artisan migrate --force`. It was wired to the
# Replit `[postMerge]` hook, so it fired when a merge happened *in the workspace*
# — not on deploy, and not when a pull request was merged on GitHub. The one
# mechanism that migrated was the one that did not run on the path we actually
# use to ship.
#
# That call has since been removed: `scripts/post-merge.sh` no longer migrates at
# all, so this script is the only production migration owner. See
# `tests/Feature/Deployment/PostMergeMigrationOwnershipTest.php`.
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
# arrived in Laravel 9. There is no mutex available, so single ownership is what
# stops a SECOND script migrating. `deploy/scheduler.sh` must never call migrate,
# and DeploymentMigrationReadinessTest asserts that it does not.
#
# Single ownership does NOT stop THIS script running twice — a redeploy
# overlapping a restart, or two deploys triggered close together. That is what
# the deploy lock below is for: the mechanism the framework does not provide.
#
# Re-running is harmless: Laravel skips migrations already recorded in the
# `migrations` table, so a crash-loop or a restart replays nothing.
#
set -euo pipefail

cd "$(dirname "$0")/.."

# Mirror the PHP ini scan dir used by the workflow and the scheduler so every
# process loads the same PHP configuration.
export PHP_INI_SCAN_DIR="$PWD/deploy/php"

# shellcheck source=deploy/lib/deploy-state.sh
. "$PWD/deploy/lib/deploy-state.sh"

# ── exclusive deploy lock ───────────────────────────────────────────────────
# Covers the whole critical section: preflight, migration, startup preparation.
# Fails closed rather than queueing forever — a deploy stuck behind another
# deploy is an outage with extra steps, and a bounded refusal is easier to see in
# a deployment log than a hang.
if ! acquire_deploy_lock; then
    echo "start-production: another deployment holds the deploy lock; refusing to start." >&2
    exit 1
fi

# Report what we are about to migrate against. Informational only — it never
# fails the deploy, because `deploy:preflight` always exits 0 by design (it
# reports; `migrate` gates). The `|| true` is therefore belt-and-braces against a
# future non-zero exit, NOT a suppressed gate. See deploy/DEPLOYMENT.md.
php artisan deploy:preflight || true

# Apply pending migrations. --force because a production APP_ENV would otherwise
# prompt; --no-interaction because there is no terminal attached.
#
# Never migrate:fresh / migrate:reset / migrate:refresh / db:wipe here. Those
# destroy data and have no place in a start command.
php artisan migrate --force --no-interaction

# ── record what this release is serving ─────────────────────────────────────
# Only now, after migrations succeeded. Recording earlier would name a SHA that
# never actually reached a healthy schema, which is worse than no record: it
# would be a rollback target that was never good.
if ! record_deploy_sha; then
    echo "start-production: could not determine the deploy SHA; refusing to start." >&2
    exit 1
fi

# Release before the server takes over. Holding the lock across `exec` would
# leave the serving process owning it for its entire life, and no future deploy
# could ever acquire it.
release_deploy_lock

# Only now serve traffic. `exec` on purpose: the server replaces this shell, so
# it stays attached to the supervisor and receives SIGTERM/SIGINT directly.
# Backgrounding it to run a post-start smoke check would orphan it from process
# supervision — see deploy/DEPLOYMENT.md for why that check is still pending.
exec php artisan serve --host=0.0.0.0 --port=5000
