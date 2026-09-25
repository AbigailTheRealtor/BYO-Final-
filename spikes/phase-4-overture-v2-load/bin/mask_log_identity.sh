#!/usr/bin/env bash
# ============================================================================
# mask_log_identity.sh  —  Overture v2 operator load · mask the target in GitHub Actions logs
# Spatial Intelligence Platform · Phase 4 — see ../RUNBOOK.md
# ----------------------------------------------------------------------------
# This repository is PUBLIC, so its Actions logs are world-readable. GitHub masks a secret's whole
# value, but NOT the pieces a failed connection prints on their own: psql, PDO and Laravel all put
# the host, the resolved address and the username into a connection error
# ("connection to server at \"<host>\" (<ip>), port 5432 failed ... for user \"<user>\"").
#
# Run as the FIRST step of every job that reads SPATIAL_DATABASE_URL. It registers the target's
# host, its resolved addresses, the username and the password with the runner's
# `::add-mask::` command, so every later line of that job's log — psql, php artisan, anything —
# shows *** instead. Workflow commands are consumed by the runner and never displayed.
#
# Outside GitHub Actions (the operator-container fallback) it does NOTHING and prints nothing:
# printing `::add-mask::<host>` to an ordinary terminal would display exactly what it hides.
# ============================================================================

set -euo pipefail

# Only on a real Actions runner: a stray GITHUB_ACTIONS=true in a terminal must not be enough to
# print these values where no runner consumes them.
if [ "${GITHUB_ACTIONS:-}" != "true" ] || [ -z "${GITHUB_RUN_ID:-}" ] || [ -z "${RUNNER_TEMP:-}" ]; then
  exit 0
fi
[ -n "${SPATIAL_DATABASE_URL:-}" ] || { echo "[mask_log_identity] SPATIAL_DATABASE_URL is not set" >&2; exit 1; }

python3 - <<'PY'
import os, socket, urllib.parse

u = urllib.parse.urlsplit(os.environ["SPATIAL_DATABASE_URL"])
values = set()
# The host exactly as written: urlsplit's .hostname lowercases, and masking is case-sensitive.
netloc_host = u.netloc.rsplit("@", 1)[-1]
netloc_host = netloc_host[1:].split("]", 1)[0] if netloc_host.startswith("[") else netloc_host.split(":", 1)[0]
values.add(netloc_host)
if u.hostname:
    values.add(u.hostname)
    try:
        for info in socket.getaddrinfo(u.hostname, u.port or 5432):
            values.add(info[4][0])
    except OSError:
        pass  # unresolvable: nothing to mask beyond the name; the preflight reports the failure
if u.username:
    values.add(u.username)
    values.add(urllib.parse.unquote(u.username))
if u.password:
    values.add(u.password)
    values.add(urllib.parse.unquote(u.password))

for v in sorted(v for v in values if v):
    print(f"::add-mask::{v}")
PY
echo "[mask_log_identity] target host, addresses and user are masked in this job's log"
