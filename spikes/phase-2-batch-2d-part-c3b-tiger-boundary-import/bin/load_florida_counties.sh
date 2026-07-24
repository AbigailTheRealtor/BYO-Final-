#!/usr/bin/env bash
# ============================================================================
# load_florida_counties.sh  —  Class-2 operator orchestrator (guarded)
# Spatial Intelligence Platform · Phase 2 Batch 2D Part C3d-c (G5)
# ----------------------------------------------------------------------------
# Runs ONLY the five committed boundary SQL recipes, in transaction-safe order,
# against the live pgsql_spatial cluster, to load the 67 Florida TIGER counties
# already materialized into a boundaries_payload.txt by the offline
# `corpus:import-boundaries` command.
#
# It is an OPERATOR tool. It never runs in CI. Hard guards below refuse to do
# anything unless the operator has explicitly opted into a live run.
#
#   Usage:
#     load_florida_counties.sh --i-understand-live \
#       --corpus-version=tiger-2024 \
#       --payload=/abs/path/boundaries_payload.txt \
#       [--row-count=67]
#
# HARD boundaries (owner decisions):
#   • Refuses when APP_ENV=production.
#   • Refuses unless --i-understand-live is passed.
#   • Requires SPATIAL_DATABASE_URL, a corpus_version, and an existing payload.
#   • NEVER prints the secret (it is only handed to psql, never echoed).
#   • Every psql step uses ON_ERROR_STOP=1 and the script stops on first failure.
#   • Executes ONLY the committed sql/ recipes; fetches nothing; converts no
#     shapefiles; writes to no table other than boundaries / boundaries_parts /
#     corpus_imports.
# ============================================================================

set -euo pipefail

die() { printf '[load_florida_counties] REFUSING: %s\n' "$1" >&2; exit 1; }

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SQL_DIR="$(cd "${HERE}/../sql" && pwd)"

# --- parse args -------------------------------------------------------------
UNDERSTAND_LIVE=0
CORPUS_VERSION=""
PAYLOAD=""
ROW_COUNT=67
for arg in "$@"; do
  case "$arg" in
    --i-understand-live)   UNDERSTAND_LIVE=1 ;;
    --corpus-version=*)    CORPUS_VERSION="${arg#*=}" ;;
    --payload=*)           PAYLOAD="${arg#*=}" ;;
    --row-count=*)         ROW_COUNT="${arg#*=}" ;;
    *) die "unknown argument: ${arg}" ;;
  esac
done

# --- guard 1: explicit live opt-in ------------------------------------------
[ "$UNDERSTAND_LIVE" -eq 1 ] || die "live run requires the explicit --i-understand-live flag."

# --- guard 2: never in production -------------------------------------------
[ "${APP_ENV:-}" != "production" ] || die "APP_ENV=production — this operator tool must not run in production."

# --- guard 3: required inputs -----------------------------------------------
[ -n "$CORPUS_VERSION" ] || die "--corpus-version is required (must match the payload's corpus_version)."
[ -n "${SPATIAL_DATABASE_URL:-}" ] || die "SPATIAL_DATABASE_URL is not set."
[ -n "$PAYLOAD" ] || die "--payload is required."
[ -f "$PAYLOAD" ] || die "payload file not found: ${PAYLOAD}"
[ "$(basename "$PAYLOAD")" = "boundaries_payload.txt" ] || die "payload must be named boundaries_payload.txt (stage recipe reads that name)."
[ "$ROW_COUNT" = "67" ] || printf '[load_florida_counties] NOTE: row_count=%s (expected 67 for Florida counties)\n' "$ROW_COUNT" >&2

# --- dependency -------------------------------------------------------------
command -v psql >/dev/null 2>&1 || die "psql is required but not installed."

PAYLOAD_DIR="$(cd "$(dirname "$PAYLOAD")" && pwd)"

printf '[load_florida_counties] live load — corpus_version=%s, row_count=%s\n' "$CORPUS_VERSION" "$ROW_COUNT"

# --- 1. stage (\copy reads boundaries_payload.txt from the CWD) -------------
( cd "$PAYLOAD_DIR" && psql "$SPATIAL_DATABASE_URL" -v ON_ERROR_STOP=1 -f "${SQL_DIR}/stage_boundaries.sql" )

# --- 2. load boundaries + derive boundaries_parts ---------------------------
psql "$SPATIAL_DATABASE_URL" -v ON_ERROR_STOP=1 -v corpus_version="$CORPUS_VERSION" -f "${SQL_DIR}/load_tiger_boundaries.sql"

# --- 3. ledger: insert staging row, then activate ---------------------------
psql "$SPATIAL_DATABASE_URL" -v ON_ERROR_STOP=1 -v corpus_version="$CORPUS_VERSION" -v row_count="$ROW_COUNT" -f "${SQL_DIR}/ledger_insert.sql"
psql "$SPATIAL_DATABASE_URL" -v ON_ERROR_STOP=1 -v corpus_version="$CORPUS_VERSION" -f "${SQL_DIR}/ledger_activate.sql"

# --- 4. read-only verification ----------------------------------------------
psql "$SPATIAL_DATABASE_URL" -v ON_ERROR_STOP=1 -v corpus_version="$CORPUS_VERSION" -f "${SQL_DIR}/verify_boundaries.sql"

printf '[load_florida_counties] done. Drop boundaries_staging manually per RUNBOOK step 18.\n'
