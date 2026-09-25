#!/usr/bin/env bash
# ============================================================================
# preflight.sh  —  Overture v2 operator load · READ-ONLY target preflight (Class-2)
# Spatial Intelligence Platform · Phase 4 — see ../RUNBOOK.md
# ----------------------------------------------------------------------------
# Proves, before any write stage, that SPATIAL_DATABASE_URL reaches the Crunchy Bridge spatial
# cluster that holds v1 — by FINGERPRINT, not by name — that v1 is healthy, what state v2 is in,
# and that the spatial migration ledger is exactly as expected. It runs ../sql/preflight.sql in a
# read-only session and writes nothing.
#
#   Usage:
#     preflight.sh --v2-state=absent|empty|loaded|recoverable-failed-import
#
#   `recoverable-failed-import` is ONLY for the separately approved retry after a failed import
#   (RUNBOOK §8a). It accepts exactly the state a failed import leaves and nothing else; it
#   never widens `absent`, `empty` or `loaded`, and it is never chosen automatically.
#
# Run from the protected GitHub Environment (the primary path) or from the dedicated operator
# container (the fallback). It REFUSES to run from a shell that carries the application's
# production database signals — the Replit workspace included. It never unsets them to get past
# that: a shell that has them is the wrong shell.
#
# HARD boundaries:
#   • Refuses APP_ENV=production and any Replit deployment.
#   • Refuses when DATABASE_URL / DB_* / PG* name the application database (helium / heliumdb).
#   • Requires SPATIAL_DATABASE_URL with an EXPLICIT host on *.db.postgresbridge.com — a URL with
#     no host would let libpq fall back to an ambient PGHOST.
#   • NEVER prints the secret, and never puts it in any process's argv: psql is reached only
#     through bin/spatial_psql.sh, which hands libpq an ephemeral 0600 service file.
#   • Read-only: the SQL sets default_transaction_read_only = on before anything else.
#   • Exit 4 = cannot connect (STOP; see RUNBOOK "Connectivity failure"). Exit 5 = connected,
#     a check failed. Exit 1 = refused before connecting.
# ============================================================================

set -euo pipefail

die() { printf '[overture-v2-preflight] REFUSING: %s\n' "$1" >&2; exit 1; }

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SQL_DIR="$(cd "${HERE}/../sql" && pwd)"
REPO_ROOT="$(cd "${HERE}/../../.." && pwd)"

# The recorded identity of the target: sha256 of
#   <host>|<port>|<current_database()>|<v1 overture-places ledger row started_at, UTC, µs>
# taken from the 2026-09-24 read-only audit. A hash of non-secret identity only: rotating the
# password does not change it, and no credential or hostname is committed.
EXPECTED_FINGERPRINT="835261fd2b754974525e2963cd97471776a2a2d45e8062374ae4a17ece965b5f"

# --- args ---------------------------------------------------------------------
V2_STATE=""
for arg in "$@"; do
  case "$arg" in
    --v2-state=*) V2_STATE="${arg#*=}" ;;
    *) die "unknown argument: ${arg}" ;;
  esac
done
case "$V2_STATE" in
  absent|empty|loaded|recoverable-failed-import) ;;
  *) die "--v2-state must be absent, empty, loaded or recoverable-failed-import." ;;
esac

# --- guard 1: not production, not a deployment ------------------------------------
[ "${APP_ENV:-}" != "production" ] || die "APP_ENV=production — this operator tool must not run in production."
[ -z "${REPLIT_DEPLOYMENT:-}" ] || die "REPLIT_DEPLOYMENT is set — this is a deployment, not an operator shell."

# --- guard 2: this shell must not carry the application database ------------------
for var in DATABASE_URL DB_HOST DB_DATABASE; do
  value="${!var:-}"
  if printf '%s' "$value" | grep -qi 'helium'; then
    die "${var} names the application database — run from the protected GitHub Environment or the operator container, never this shell."
  fi
done
# Any ambient libpq routing variable can redirect psql or PDO away from the host checked below
# (PGHOSTADDR overrides a host; PGSERVICE / PGOPTIONS reach a file or a session setting this
# script cannot see). An operator shell has none of them, so any one is a refusal — never unset.
for var in PGHOST PGHOSTADDR PGDATABASE PGSERVICE PGSERVICEFILE PGOPTIONS PGPORT PGUSER; do
  [ -z "${!var:-}" ] || die "${var} is set in this shell — use a clean operator shell (the target comes from SPATIAL_DATABASE_URL alone)."
done

# --- guard 3: the target, parsed without printing it --------------------------------
[ -n "${SPATIAL_DATABASE_URL:-}" ] || die "SPATIAL_DATABASE_URL is not set."
command -v python3 >/dev/null 2>&1 || die "python3 is required to parse the target."
command -v psql >/dev/null 2>&1 || die "psql is required."

# The one reading of the URL (bin/spatial_target.py), shared with bin/spatial_psql.sh so the two
# can never disagree about which target is acceptable.
PARSED="$(python3 "${HERE}/spatial_target.py" check)"
case "$PARSED" in
  OK\ *) read -r _ TARGET_HOST TARGET_PORT <<<"$PARSED" ;;
  ERR\ *) die "SPATIAL_DATABASE_URL: ${PARSED#ERR }." ;;
  *) die "SPATIAL_DATABASE_URL could not be checked." ;;
esac

# --- guard 4: the repository's spatial migrations are exactly the known sixteen -------
# Eleven applied on the cluster, two August address migrations that this procedure never
# applies, and the three v2 migrations that are its only candidates. A new spatial migration
# means this runbook is stale, so it refuses rather than guessing.
EXPECTED_MIGRATIONS="2026_07_16_000001_spatial_core_enable_extensions.php
2026_07_16_000002_spatial_core_create_place_categories.php
2026_07_16_000003_spatial_core_create_place_category_mappings.php
2026_07_16_000004_spatial_core_create_places.php
2026_07_16_000005_spatial_core_create_place_authority_links.php
2026_07_16_000006_spatial_core_create_boundaries.php
2026_07_16_000007_spatial_core_create_boundaries_parts.php
2026_07_16_000008_spatial_core_create_listing_locations.php
2026_07_16_000009_spatial_core_create_addresses.php
2026_07_16_000010_spatial_core_create_isochrone_cache.php
2026_07_16_000011_spatial_core_create_corpus_imports.php
2026_08_11_000001_spatial_core_version_address_corpus.php
2026_08_12_000001_spatial_core_index_address_lookup.php
2026_09_24_000001_spatial_overture_v2_create_corpora.php
2026_09_24_000002_spatial_overture_v2_create_places.php
2026_09_24_000003_spatial_overture_v2_create_chain_memberships.php"
ACTUAL_MIGRATIONS="$(cd "${REPO_ROOT}/database/migrations/spatial" && ls -1 -- *_*.php | LC_ALL=C sort)"
[ "$ACTUAL_MIGRATIONS" = "$(printf '%s\n' "$EXPECTED_MIGRATIONS" | LC_ALL=C sort)" ] \
  || die "database/migrations/spatial is not exactly the sixteen known migrations — the runbook is stale."
printf '[overture-v2-preflight] write-phase migration candidates: 2026_09_24_000001, 2026_09_24_000002, 2026_09_24_000003 (August address migrations excluded)\n'

# --- connect, read-only ---------------------------------------------------------------
printf '[overture-v2-preflight] target host suffix ok (*.db.postgresbridge.com), v2 state expected: %s\n' "$V2_STATE"
export PGCONNECT_TIMEOUT="${PGCONNECT_TIMEOUT:-15}"
export PGSSLMODE=require   # the URL may strengthen it (verify-ca/verify-full); nothing may weaken it
export PGAPPNAME="overture-v2-preflight"
# psql's own error text is NEVER printed raw. A connection error names the host, the resolved
# address and the user ("connection to server at ... failed ... for user ..."), and this
# repository's Actions logs are public. stdout (the PASS/FAIL lines) passes through; from stderr
# only the committed script's own error lines ("psql:<...>/preflight.sql:<line>: ...") and the
# helper's own value-free refusals are shown. psql is reached only through bin/spatial_psql.sh,
# so the connection string is never in psql's argv (visible to `ps`).
ERR_FILE="$(mktemp)"
trap 'rm -f "$ERR_FILE"' EXIT
set +e
bash "${HERE}/spatial_psql.sh" -X -q -v ON_ERROR_STOP=1 \
  -v v2_state="$V2_STATE" -v target_host="$TARGET_HOST" -v target_port="$TARGET_PORT" \
  -v expected_fingerprint="$EXPECTED_FINGERPRINT" \
  -f "${SQL_DIR}/preflight.sql" 2>"$ERR_FILE"
status=$?
set -e
if [ "$status" -ne 0 ] && [ "$status" -ne 2 ]; then
  grep -E '^psql:[^[:space:]]*preflight\.sql:[0-9]+: |^\[spatial-psql\] REFUSING: ' "$ERR_FILE" >&2 || true
fi

case "$status" in
  0) printf '[overture-v2-preflight] PREFLIGHT PASSED (read-only; nothing was written)\n' ;;
  2) # A fixed category, never the text: tells a rotated credential from an allowlist block.
     category="other"
     if grep -qiE 'authentication failed|no pg_hba|role .* does not exist' "$ERR_FILE"; then category="authentication (credential rejected or rotated)"
     elif grep -qiE 'could not translate host name|Name or service not known|nodename nor servname' "$ERR_FILE"; then category="DNS (host name did not resolve)"
     elif grep -qiE 'SSL|TLS|certificate' "$ERR_FILE"; then category="TLS"
     elif grep -qiE 'timeout expired|timed out|Connection refused|No route to host|Network is unreachable' "$ERR_FILE"; then category="network (refused or timed out, e.g. an allowlist)"
     fi
     printf '[overture-v2-preflight] connection failure category: %s\n' "$category" >&2
     printf '[overture-v2-preflight] CONNECTIVITY FAILED (details withheld: they name the host and user) — STOP. Do not change the Crunchy allowlist from here and do not try another credential path. Follow RUNBOOK "Connectivity failure" (operator-container fallback).\n' >&2
     exit 4 ;;
  *) printf '[overture-v2-preflight] PREFLIGHT FAILED (exit %s) — STOP. Nothing was written.\n' "$status" >&2
     exit 5 ;;
esac
