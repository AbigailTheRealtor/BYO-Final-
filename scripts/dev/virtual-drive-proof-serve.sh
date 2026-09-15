#!/usr/bin/env bash
# scripts/dev/virtual-drive-proof-serve.sh
#
# Serve the Virtual Drive provider proof from THIS worktree for the one controlled,
# credentialed Apple vs Google comparison. Development only — no deploy path calls it.
#
# WHAT IT GUARANTEES
#   - APP_ENV=local and VIRTUAL_DRIVE_PROOF_ENABLED=true for this process only.
#     Nothing is written to .env, and the production gate is untouched.
#   - PERSISTENT cache and session stores. Both were `array` and both had to
#     change; see "WHY NOT array" below before changing either back.
#   - The database session is READ-ONLY (default_transaction_read_only=on). The
#     proof reads stored MLS rows; anything that tried to write would fail loudly.
#   - Credentials come from the environment (Replit Secrets) and are never printed —
#     only whether each one is present.
#   - Nothing street-level loads until a launch button is pressed on a provider page,
#     and a Google page constructs at most one Street View panorama per load.
#
# WHY NOT array, FOR EITHER STORE
# -------------------------------
# `php artisan serve` bootstraps a fresh PHP process per request, so an `array`
# store is empty again by the next one. Two things break, and neither announces
# itself:
#
#   CACHE — the daily launch ledger lives in the cache. A write is readable
#   inside the same request, so the ledger's own readback guard passes and the
#   claim is granted; the next request reads 0 again. The ceiling would report
#   itself enforced and enforce nothing, which is the exact failure the readback
#   exists to prevent.
#
#   SESSION — the launch claim is a POST through the normal `web` middleware, so
#   VerifyCsrfToken compares the token in the page against the one in the
#   session. With no session carried between the GET and the POST, every press
#   is HTTP 419 "CSRF token mismatch". The fix is a persistent session, never a
#   CSRF exemption: the claim endpoint is the one route here that spends money
#   and issues the browser key, so it is the last route that should be exempt.
#
# THE GOOGLE KILL SWITCH IS NOT SET HERE, ON PURPOSE. VIRTUAL_DRIVE_GOOGLE_ENABLED
# is inherited from the environment so this script cannot switch the billed
# provider on by being run. Export it for the one comparison that needs it:
#
#   VIRTUAL_DRIVE_GOOGLE_ENABLED=true bash scripts/dev/virtual-drive-proof-serve.sh
#
# STOP IT WHEN THE SESSION ENDS. Bound to 0.0.0.0 so the Replit dev URL can reach
# it, and the proof has no login of its own.
#
# REACHING IT FROM A BROWSER. On Replit only ports declared in `.replit` are
# published, and a non-default one is reached with the port in the URL
# (https://<dev-domain>:<port>/dev/virtual-drive). The proof's own pages emit
# same-origin relative URLs precisely so that origin survives; do not "fix" them
# back to asset()/route() absolutes, which drop the port behind the TLS proxy
# and send every asset to whatever answers on the bare domain.
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

# Both file stores need their directories to exist. A fresh worktree checkout has
# storage/ but not always these, and a missing one surfaces as an unreadable
# ledger (a refused launch) or an unwritable session (a CSRF mismatch) rather
# than as anything that mentions a directory.
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views

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
    SESSION_DRIVER=file \
    CACHE_DRIVER=file \
    QUEUE_CONNECTION=sync \
    BROADCAST_DRIVER=log \
    LOG_CHANNEL=stderr \
    PGOPTIONS='-c default_transaction_read_only=on' \
    php artisan serve --host="${HOST}" --port="${PORT}"
