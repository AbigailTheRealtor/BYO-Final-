#!/usr/bin/env bash
# scripts/dev/virtual-drive-proof-serve.sh
#
# Serve the Virtual Drive provider proof from THIS worktree for the one controlled,
# credentialed Apple vs Google comparison. Development only — no deploy path calls it.
#
# WHAT IT GUARANTEES
#   - APP_ENV=local and VIRTUAL_DRIVE_PROOF_ENABLED=true for this process only.
#     Nothing is written to .env, and the production gate is untouched.
#   - The database session is READ-ONLY (default_transaction_read_only=on). The
#     proof reads stored MLS rows; anything that tried to write would fail loudly.
#   - Credentials come from the environment (Replit Secrets) and are never printed —
#     only whether each one is present.
#   - Nothing street-level loads until a launch button is pressed on a provider page,
#     and a Google page constructs at most one Street View panorama per load.
#
# STOP IT WHEN THE SESSION ENDS. Bound to 0.0.0.0 so the Replit dev URL can reach
# it, and the proof has no login of its own.
#
# Usage (from the worktree):  bash scripts/dev/virtual-drive-proof-serve.sh
# Optional:                   VIRTUAL_DRIVE_PROOF_PORT=8790 VIRTUAL_DRIVE_PROOF_HOST=0.0.0.0

set -euo pipefail

cd "$(dirname "$0")/../.."

PORT="${VIRTUAL_DRIVE_PROOF_PORT:-8790}"
HOST="${VIRTUAL_DRIVE_PROOF_HOST:-0.0.0.0}"

for name in VIRTUAL_DRIVE_GOOGLE_MAPS_BROWSER_KEY VIRTUAL_DRIVE_MAPKIT_JS_TOKEN; do
    if [ -n "${!name:-}" ]; then
        echo "${name}: present"
    else
        echo "${name}: absent — that provider's page will say so and load nothing"
    fi
done

# The worktree has no .env; an ephemeral key keeps sessions and cookies working.
if [ -z "${APP_KEY:-}" ]; then
    APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
fi

echo "Virtual Drive proof on ${HOST}:${PORT} — open /dev/virtual-drive. Ctrl+C to stop."

exec env \
    APP_KEY="${APP_KEY}" \
    APP_ENV=local \
    APP_DEBUG=false \
    VIRTUAL_DRIVE_PROOF_ENABLED=true \
    SESSION_DRIVER=array \
    CACHE_DRIVER=array \
    QUEUE_CONNECTION=sync \
    BROADCAST_DRIVER=log \
    LOG_CHANNEL=stderr \
    PGOPTIONS='-c default_transaction_read_only=on' \
    php artisan serve --host="${HOST}" --port="${PORT}"
