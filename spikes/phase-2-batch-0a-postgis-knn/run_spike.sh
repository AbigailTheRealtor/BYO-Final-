#!/usr/bin/env bash
# Phase 2 · Batch 0 · Stage 0a — PostGIS/KNN feasibility spike runner
#
# Executes the spike SQL in order and captures every output under results/.
# Read-only against the repo; the only side effects are inside the disposable
# `spike` database in the byo-batch0-spike container.
#
# Connection is configurable via env. Defaults target the local spike container
# over TCP (docker exec is unavailable in some sandboxes; TCP always works):
#
#   PGHOST      (default 172.17.0.2)   container IP  — `docker inspect` to confirm
#   PGPORT      (default 5432)
#   PGUSER      (default postgres)
#   PGDATABASE  (default spike)
#   PGPASSWORD  (default spike)
#   PSQL_BIN    (default: nix psql path; override with `psql` if on PATH)
#
# On a standard Docker host you may instead run each file with:
#   docker exec -i byo-batch0-spike psql -U postgres -d spike -f - < sql/00_setup.sql
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SQL="$HERE/sql"
OUT="$HERE/results"
mkdir -p "$OUT"

export PGHOST="${PGHOST:-172.17.0.2}"
export PGPORT="${PGPORT:-5432}"
export PGUSER="${PGUSER:-postgres}"
export PGDATABASE="${PGDATABASE:-spike}"
export PGPASSWORD="${PGPASSWORD:-spike}"
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
