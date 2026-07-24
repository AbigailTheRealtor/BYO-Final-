#!/usr/bin/env bash
# ============================================================================
# load_florida_overture_places.sh  —  Class-2 operator orchestrator (guarded)
# Spatial Intelligence Platform · Phase 2 Batch 2D Part C3d-d
# ----------------------------------------------------------------------------
# Loads the Florida Overture places corpus into the partitioned `places` table
# from artifacts already authored OFFLINE by:
#   php artisan corpus:import-overture --region=florida \
#     --corpus-version=overture-2026-06-17.0-fl --out-dir=<IMPORT_DIR>
# (which emits partition_load.sql + copy_payload.txt). This script performs no
# extraction and no download — it consumes a pre-made COPY payload.
#
#   Usage:
#     load_florida_overture_places.sh --i-understand-live \
#       --import-dir=/abs/path/to/import/places_p_overture_2026_06_17_0_fl \
#       [--corpus-version=overture-2026-06-17.0-fl]
#
# HARD boundaries (owner decisions):
#   • Refuses when APP_ENV=production.
#   • Refuses unless --i-understand-live is passed.
#   • Requires SPATIAL_DATABASE_URL; NEVER prints it (handed only to psql).
#   • Seeds the taxonomy (place_categories, place_category_mappings) BEFORE load.
#   • Order: seed → create+COPY → acceptance (read-only, aborts on violation) →
#     ledger staging row → attach+activate (one txn) → read-only verify.
#   • Every psql step uses ON_ERROR_STOP=1; the script stops on first failure.
#   • Writes only to places / its partition and corpus_imports. Fetches nothing.
# ============================================================================

set -euo pipefail

die() { printf '[load_florida_overture_places] REFUSING: %s\n' "$1" >&2; exit 1; }

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SQL_DIR="$(cd "${HERE}/../sql" && pwd)"

# --- parse args -------------------------------------------------------------
UNDERSTAND_LIVE=0
CORPUS_VERSION="overture-2026-06-17.0-fl"
IMPORT_DIR=""
for arg in "$@"; do
  case "$arg" in
    --i-understand-live) UNDERSTAND_LIVE=1 ;;
    --corpus-version=*)  CORPUS_VERSION="${arg#*=}" ;;
    --import-dir=*)      IMPORT_DIR="${arg#*=}" ;;
    *) die "unknown argument: ${arg}" ;;
  esac
done

# --- guards (order matters: each independently reachable) --------------------
[ "$UNDERSTAND_LIVE" -eq 1 ] || die "live run requires the explicit --i-understand-live flag."
[ "${APP_ENV:-}" != "production" ] || die "APP_ENV=production — this operator tool must not run in production."
[ -n "${SPATIAL_DATABASE_URL:-}" ] || die "SPATIAL_DATABASE_URL is not set."
[ -n "$CORPUS_VERSION" ] || die "--corpus-version must not be empty."
[ -n "$IMPORT_DIR" ] || die "--import-dir is required (the corpus:import-overture --out-dir)."
[ -f "$IMPORT_DIR/partition_load.sql" ] || die "missing $IMPORT_DIR/partition_load.sql (run corpus:import-overture first)."
[ -f "$IMPORT_DIR/copy_payload.txt" ] || die "missing $IMPORT_DIR/copy_payload.txt (run corpus:import-overture first)."
command -v psql >/dev/null 2>&1 || die "psql is required but not installed."
command -v php  >/dev/null 2>&1 || die "php is required for the taxonomy seeders."

# Derive the partition name exactly as CorpusPartitionManager does:
#   lowercase, every run of non [a-z0-9] → '_', trim leading/trailing '_'.
SLUG="$(printf '%s' "$CORPUS_VERSION" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9]+/_/g; s/^_+//; s/_+$//')"
PART="places_p_${SLUG}"
IMPORT_ABS="$(cd "$IMPORT_DIR" && pwd)"

printf '[load_florida_overture_places] live load — corpus_version=%s partition=%s\n' "$CORPUS_VERSION" "$PART"

# --- 1. seed taxonomy (place_categories, then place_category_mappings) -------
php artisan db:seed --force --database=pgsql_spatial --class='Database\Seeders\SpatialFirstSliceCategorySeeder'
php artisan db:seed --force --database=pgsql_spatial --class='Database\Seeders\SpatialOvertureCategoryMappingSeeder'

# --- 2. create staging partition + COPY (generated, FL-parameterized) --------
# partition_load.sql's \copy reads copy_payload.txt relative to the CWD.
( cd "$IMPORT_ABS" && psql "$SPATIAL_DATABASE_URL" -v ON_ERROR_STOP=1 -f "$IMPORT_ABS/partition_load.sql" )

# --- 3. acceptance gate (READ-ONLY; aborts before attach on any violation) ---
psql "$SPATIAL_DATABASE_URL" -v ON_ERROR_STOP=1 <<SQL
SELECT (
     count(*) = 0
  OR count(*) FILTER (WHERE source <> 'overture') > 0
  OR count(*) FILTER (WHERE source_ref IS NULL OR btrim(source_ref) = '') > 0
  OR count(*) FILTER (WHERE confidence IS NULL OR confidence < 0.90) > 0
  OR count(*) FILTER (WHERE ST_X(centroid::geometry) NOT BETWEEN -180 AND 180
                         OR ST_Y(centroid::geometry) NOT BETWEEN  -90 AND  90) > 0
) AS acceptance_failed
FROM ${PART} \gset
SELECT (count(*) > 0) AS has_unregistered
FROM ${PART} s LEFT JOIN place_categories c ON c.category_key = s.category_key
WHERE c.category_key IS NULL \gset
\if :acceptance_failed
\echo '  ACCEPTANCE FAILED: staged partition violates source/ref/floor/coords/empty — NOT attaching.'
SELECT 1/0;
\endif
\if :has_unregistered
\echo '  ACCEPTANCE FAILED: staged rows carry an unregistered category_key — seed taxonomy first.'
SELECT 1/0;
\endif
\echo '  acceptance: PASS'
SQL

# --- 4. ledger staging row (idempotent on dataset+corpus_version) ------------
STAGED="$(psql "$SPATIAL_DATABASE_URL" -tAqc "SELECT count(*) FROM ${PART};")"
printf '[load_florida_overture_places] staged rows: %s\n' "$STAGED"
psql "$SPATIAL_DATABASE_URL" -v ON_ERROR_STOP=1 <<SQL
INSERT INTO corpus_imports
  (dataset, corpus_version, row_count, bytes, territory_coverage, started_at, finished_at, status, notes)
SELECT 'overture-places', '${CORPUS_VERSION}', ${STAGED}, ${STAGED} * 450,
       '{"region":"florida","state_fips":"12","crs":"EPSG:4326"}'::jsonb,
       now(), NULL, 'staging',
       '{"source":"overture","confidence_min":0.90}'::jsonb
WHERE NOT EXISTS (
  SELECT 1 FROM corpus_imports
  WHERE dataset = 'overture-places' AND corpus_version = '${CORPUS_VERSION}'
);
SQL

# --- 5. attach + activate (one transaction; O(1) via the CHECK) --------------
psql "$SPATIAL_DATABASE_URL" -v ON_ERROR_STOP=1 <<SQL
BEGIN;
  ALTER TABLE ${PART} ADD CONSTRAINT ${PART}_ck CHECK (corpus_version = '${CORPUS_VERSION}');
  ALTER TABLE places ATTACH PARTITION ${PART} FOR VALUES IN ('${CORPUS_VERSION}');
  UPDATE corpus_imports SET status = 'active', finished_at = now()
   WHERE dataset = 'overture-places' AND corpus_version = '${CORPUS_VERSION}' AND status = 'staging';
COMMIT;
SQL

# --- 6. read-only verification ----------------------------------------------
psql "$SPATIAL_DATABASE_URL" -v ON_ERROR_STOP=1 -v corpus_version="$CORPUS_VERSION" -f "${SQL_DIR}/verify_overture_fl.sql"

printf '[load_florida_overture_places] done. Florida Overture places load complete for %s.\n' "$CORPUS_VERSION"
