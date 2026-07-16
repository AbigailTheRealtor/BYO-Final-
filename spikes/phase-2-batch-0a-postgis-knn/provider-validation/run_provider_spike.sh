#!/usr/bin/env bash
#
# Stage 0b provider-neutral spike wrapper.
#
# Runs the COMMITTED Stage 0a spike SQL against a managed PostgreSQL provider over
# a DIRECT (session-mode) connection, capturing evidence under
#   results/<provider>/tier<N>/
#
# It does NOT: provision infrastructure, create accounts, spend money, or handle
# secrets. The database password is supplied ONLY via ~/.pgpass (psql reads it) —
# this script never reads, echoes, exports, or forwards PGPASSWORD.
#
# Tier 1 reuses the committed Stage 0a generator read-only; Tier 2 uses the
# deterministic scaled generator added in this directory. Every other SQL step
# (schema, index strategies, KNN correctness, distribution) is the committed
# Stage 0a SQL, reused read-only.
#
# Usage:
#   run_provider_spike.sh [--help] [--dry-run]
# Configuration is via environment (see env.example):
#   PROVIDER   crunchy|digitalocean|neon|rds   (required)
#   TIER       1|2                             (required)
#   PGHOST PGPORT PGUSER PGDATABASE            (required)
#   PGSSLMODE  (default: require)   PSQL_BIN (default: psql)
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SPIKE_SQL="$HERE/../sql"      # committed Stage 0a SQL — reused READ-ONLY
LOCAL_SQL="$HERE/sql"         # Stage 0b additions (tier-2 generator, measurements)
RESULTS_ROOT="$HERE/results"

ALLOWED_PROVIDERS="crunchy digitalocean neon rds"

usage() {
    sed -n '3,24p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
}

die() { echo "ERROR: $*" >&2; exit 2; }

# ---------------------------------------------------------------------------
# Argument parsing (offline; no env or connection required for --help/--dry-run)
# ---------------------------------------------------------------------------
DRY_RUN=0
while [ $# -gt 0 ]; do
    case "$1" in
        -h|--help)     usage; exit 0 ;;
        -n|--dry-run)  DRY_RUN=1; shift ;;
        *)             echo "unknown argument: $1" >&2; usage >&2; exit 2 ;;
    esac
done

# ---------------------------------------------------------------------------
# Fail-closed environment validation
# ---------------------------------------------------------------------------
missing=()
for v in PROVIDER TIER PGHOST PGPORT PGUSER PGDATABASE; do
    if [ -z "${!v:-}" ]; then missing+=("$v"); fi
done
if [ "${#missing[@]}" -gt 0 ]; then
    die "missing required environment variable(s): ${missing[*]} (see env.example; password goes in ~/.pgpass, never in env)"
fi

case " $ALLOWED_PROVIDERS " in
    *" $PROVIDER "*) : ;;
    *) die "PROVIDER='$PROVIDER' invalid; must be one of: $ALLOWED_PROVIDERS" ;;
esac
case "$TIER" in
    1|2) : ;;
    *)   die "TIER='$TIER' invalid; must be 1 or 2" ;;
esac

PGSSLMODE="${PGSSLMODE:-require}"
PSQL_BIN="${PSQL_BIN:-psql}"
export PGSSLMODE
# NOTE: PGPASSWORD is intentionally never set/read here — psql uses ~/.pgpass.

RESULTS_DIR="$RESULTS_ROOT/$PROVIDER/tier$TIER"

# ---------------------------------------------------------------------------
# Build the ordered step list for this tier.
# Only the data-generator step differs between tiers; everything else is the
# committed Stage 0a SQL.
# ---------------------------------------------------------------------------
if [ "$TIER" = "1" ]; then
    GENERATOR="$SPIKE_SQL/10_generate_data.sql"          # committed Stage 0a, read-only
else
    GENERATOR="$LOCAL_SQL/10_generate_data_tier2.sql"    # Stage 0b scaled generator
fi

# step label -> sql file (ordered)
STEPS=(
    "00_setup:$SPIKE_SQL/00_setup.sql"
    "10_generate_data:$GENERATOR"
    "60_distribution:$SPIKE_SQL/60_distribution.sql"
    "20_strategy_a_composite:$SPIKE_SQL/20_strategy_a_composite.sql"
    "50_knn_correctness:$SPIKE_SQL/50_knn_correctness.sql"
    "30_strategy_b_geography_only:$SPIKE_SQL/30_strategy_b_geography_only.sql"
    "40_strategy_c_partial:$SPIKE_SQL/40_strategy_c_partial.sql"
    "70_measurements:$LOCAL_SQL/70_measurements.sql"
)

# psql invocation — password comes from ~/.pgpass, not from any variable here.
PSQL=("$PSQL_BIN" -X -v ON_ERROR_STOP=1 --no-psqlrc
      -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d "$PGDATABASE")

echo "provider   : $PROVIDER"
echo "tier       : $TIER"
echo "target     : ${PGUSER}@${PGHOST}:${PGPORT}/${PGDATABASE} (sslmode=$PGSSLMODE, direct/session)"
echo "results dir : $RESULTS_DIR"
echo "psql        : $PSQL_BIN (password source: ~/.pgpass)"
echo "steps       :"
for s in "${STEPS[@]}"; do echo "   - ${s%%:*}  <-  ${s#*:}"; done

if [ "$DRY_RUN" = "1" ]; then
    echo
    echo "[--dry-run] no connection made, no files written, no secrets read."
    exit 0
fi

# ---------------------------------------------------------------------------
# Real run (connects to the provider). Writes ONLY under RESULTS_DIR.
# ---------------------------------------------------------------------------
mkdir -p "$RESULTS_DIR"
for s in "${STEPS[@]}"; do
    label="${s%%:*}"; file="${s#*:}"
    [ -f "$file" ] || die "SQL step not found: $file"
    echo ">>> $label"
    "${PSQL[@]}" -f "$file" > "$RESULTS_DIR/$label.out" 2>&1
done
echo "OK — evidence captured under $RESULTS_DIR"
