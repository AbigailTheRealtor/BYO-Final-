#!/usr/bin/env bash
# ============================================================================
# spatial_psql.sh  —  Overture v2 operator load · psql against SPATIAL_DATABASE_URL, URL never in argv
# Spatial Intelligence Platform · Phase 4 — see ../RUNBOOK.md
# ----------------------------------------------------------------------------
# The ONLY way this procedure runs psql against the target. Handing psql the URL as its
# connection argument put the whole connection string — password included — in psql's argv,
# which every user on the host (and the host of a container) can read with `ps` while psql runs.
#
# Instead: the same checks bin/preflight.sh applies (bin/spatial_target.py), then an ephemeral
# libpq SERVICE FILE, mode 0600, in a fresh 0700 directory under /tmp; psql is started with
# PGSERVICEFILE / PGSERVICE set in ITS environment only, and the directory is removed on exit.
# psql's argv carries psql options and file paths, nothing else.
#
#   Usage:  spatial_psql.sh [-X] [-q] [-A] [-t] [-At] [-v NAME=VALUE]... [-f FILE | -c SQL]...
#
# HARD boundaries:
#   • Refuses APP_ENV=production, a Replit deployment, an application-database DATABASE_URL /
#     DB_*, and ANY ambient libpq routing variable — checked BEFORE the helper sets its own
#     PGSERVICE / PGSERVICEFILE, which exist only in the child psql's environment.
#   • Refuses every psql option outside the list above: no -h / -p / -U / -d, no positional
#     database or conninfo, no service= — the target is SPATIAL_DATABASE_URL alone. -X is
#     REQUIRED, so no psqlrc can run against the target.
#   • Prints nothing of its own except a refusal, which never carries a value.
#   • Exit status is psql's; 1 for a refusal before psql starts.
# ============================================================================

set -euo pipefail

die() { printf '[spatial-psql] REFUSING: %s\n' "$1" >&2; exit 1; }

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# --- the shell: exactly bin/preflight.sh's guards ------------------------------------
[ "${APP_ENV:-}" != "production" ] || die "APP_ENV=production — this operator tool must not run in production."
[ -z "${REPLIT_DEPLOYMENT:-}" ] || die "REPLIT_DEPLOYMENT is set — this is a deployment, not an operator shell."
for var in DATABASE_URL DB_HOST DB_DATABASE; do
  if printf '%s' "${!var:-}" | grep -qi 'helium'; then
    die "${var} names the application database — use a clean operator shell."
  fi
done
for var in PGHOST PGHOSTADDR PGDATABASE PGSERVICE PGSERVICEFILE PGOPTIONS PGPORT PGUSER; do
  [ -z "${!var:-}" ] || die "${var} is set in this shell — use a clean operator shell (the target comes from SPATIAL_DATABASE_URL alone)."
done
[ -n "${SPATIAL_DATABASE_URL:-}" ] || die "SPATIAL_DATABASE_URL is not set."
command -v python3 >/dev/null 2>&1 || die "python3 is required to parse the target."
command -v psql >/dev/null 2>&1 || die "psql is required."

# --- psql options: an allowlist -------------------------------------------------------
args=("$@")
saw_x=0
i=0
while [ "$i" -lt "${#args[@]}" ]; do
  case "${args[$i]}" in
    -X) saw_x=1 ;;
    -q|-A|-t|-At|-tA) ;;
    -v|-f|-c)
      i=$((i + 1))
      [ "$i" -lt "${#args[@]}" ] || die "option ${args[$((i - 1))]} needs a value."
      case "${args[$i]}" in
        *postgres://*|*postgresql://*) die "a connection string may not be passed as an argument." ;;
      esac ;;
    *) die "psql argument not allowed here (only -X -q -A -t -At -v -f -c; the target comes from SPATIAL_DATABASE_URL)." ;;
  esac
  i=$((i + 1))
done
# Without -X psql reads ~/.psqlrc / $PSQLRC, which could \connect somewhere else.
[ "$saw_x" -eq 1 ] || die "-X is required (no psqlrc may run against the target)."

# --- the ephemeral service file -----------------------------------------------------------
umask 077
SVC_DIR="$(mktemp -d /tmp/ov2-pgsvc.XXXXXXXX)"
trap 'rm -rf -- "$SVC_DIR"' EXIT
trap 'exit 129' HUP
trap 'exit 130' INT
trap 'exit 143' TERM
result="$(python3 "${HERE}/spatial_target.py" service "${SVC_DIR}/pg_service.conf")"
case "$result" in
  OK) ;;
  ERR\ *) die "SPATIAL_DATABASE_URL: ${result#ERR }." ;;
  *) die "SPATIAL_DATABASE_URL could not be checked." ;;
esac

set +e
PGSERVICEFILE="${SVC_DIR}/pg_service.conf" PGSERVICE=ov2_spatial psql "$@"
status=$?
set -e
exit "$status"
