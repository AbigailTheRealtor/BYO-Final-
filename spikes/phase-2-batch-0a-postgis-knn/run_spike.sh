#!/usr/bin/env bash
# Phase 2 · Batch 0 · Stage 0a — PostGIS/KNN feasibility spike runner
#
# Executes the spike SQL in order and captures every output under results/.
# Read-only against the repo; the only side effects are inside the disposable
# `spike` database in the byo-batch0-spike container.
#
# The target MUST be named explicitly in SPIKE_* variables. The ambient PG* variables are
# never used: in the Replit workspace they point at the production database, and step 00
# drops and creates tables. See lib/require-isolated-target.sh.
#
#   SPIKE_PGHOST      (required, e.g. 172.17.0.2)  container IP — `docker inspect` to confirm
#   SPIKE_PGDATABASE  (required, e.g. spike)
#   SPIKE_PGPORT      (default 5432)
#   SPIKE_PGUSER      (default postgres)
#   SPIKE_PGPASSWORD  (default spike)
#   PSQL_BIN          (default: nix psql path; override with `psql` if on PATH)
#
#   SPIKE_PGHOST=172.17.0.2 SPIKE_PGDATABASE=spike bash run_spike.sh
#
# On a standard Docker host you may instead run each file with:
#   docker exec -i byo-batch0-spike psql -U postgres -d spike -f - < sql/00_setup.sql
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Refuses production, a Replit deployment, or an unnamed target, before anything else runs.
# shellcheck source=lib/require-isolated-target.sh
. "$HERE/lib/require-isolated-target.sh"
stage0_require_isolated_target

SQL="$HERE/sql"
OUT="$HERE/results"
mkdir -p "$OUT"

export PGPORT="${SPIKE_PGPORT:-5432}"
export PGUSER="${SPIKE_PGUSER:-postgres}"
export PGPASSWORD="${SPIKE_PGPASSWORD:-spike}"
PSQL_BIN="${PSQL_BIN:-/nix/store/bgwr5i8jf8jpg75rr53rz3fqv5k8yrwp-postgresql-16.10/bin/psql}"

PSQL=("$PSQL_BIN" -X -v ON_ERROR_STOP=1 --no-psqlrc)

run() {  # run <sqlfile> <outfile>
    local sqlfile="$1" outfile="$2"
    echo ">>> $(basename "$sqlfile")  ->  results/$(basename "$outfile")"
    "${PSQL[@]}" -f "$sqlfile" > "$OUT/$outfile" 2>&1
}

run "$SQL/00_setup.sql"                  "00_setup.out"
run "$SQL/10_generate_data.sql"          "10_generate_data.out"
run "$SQL/60_distribution.sql"           "60_distribution.out"
run "$SQL/20_strategy_a_composite.sql"   "20_strategy_a_composite.out"
run "$SQL/50_knn_correctness.sql"        "50_knn_correctness.out"
run "$SQL/30_strategy_b_geography_only.sql" "30_strategy_b_geography_only.out"
run "$SQL/40_strategy_c_partial.sql"     "40_strategy_c_partial.out"

echo "OK — all stages captured under results/"
