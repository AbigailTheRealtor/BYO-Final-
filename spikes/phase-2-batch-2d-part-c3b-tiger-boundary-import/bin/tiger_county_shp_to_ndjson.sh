#!/usr/bin/env bash
# ============================================================================
# tiger_county_shp_to_ndjson.sh  —  Class-2 operator tool (offline data-prep)
# Spatial Intelligence Platform · Phase 2 Batch 2D Part C3d-c (G1)
# ----------------------------------------------------------------------------
# Convert a Census TIGER/Line COUNTY shapefile into the raw NDJSON that
# `corpus:import-boundaries --source=tiger_county` consumes: one JSON object per
# line, filtered to a single state, reprojected to EPSG:4326 (WGS84), carrying
# ONLY the keys the CensusCountyBoundarySource adapter reads.
#
#   Usage:  tiger_county_shp_to_ndjson.sh <input.shp> <out.ndjson> [state_fips]
#           state_fips defaults to 12 (Florida).
#
# HARD boundaries (owner decisions):
#   • Requires ogr2ogr (GDAL) and jq; exits non-zero if either is missing.
#   • Reprojects the source CRS (TIGER is NAD83/EPSG:4269) to EPSG:4326.
#   • Filters STATEFP to the requested value.
#   • Preserves GEOID and STATEFP as STRINGS (leading zeros intact).
#   • Emits ONLY: geoid, name, namelsad, statefp, geometry.
#   • Fails if the input is missing or the output would be empty (0 rows).
#   • Downloads NOTHING, reads NO SPATIAL_* secret, opens NO database, and
#     writes ONLY the requested output file.
# ============================================================================

set -euo pipefail

die() { printf '[tiger_county_shp_to_ndjson] ERROR: %s\n' "$1" >&2; exit 1; }

# --- args -------------------------------------------------------------------
[ "$#" -ge 2 ] || die "usage: $(basename "$0") <input.shp> <out.ndjson> [state_fips=12]"
INPUT_SHP="$1"
OUT_NDJSON="$2"
STATE_FIPS="${3:-12}"

# --- dependencies -----------------------------------------------------------
command -v ogr2ogr >/dev/null 2>&1 || die "ogr2ogr (GDAL) is required but not installed."
command -v jq      >/dev/null 2>&1 || die "jq is required but not installed."

# --- input ------------------------------------------------------------------
[ -f "$INPUT_SHP" ] || die "input shapefile not found: ${INPUT_SHP}"

# --- convert ----------------------------------------------------------------
# ogr2ogr streams GeoJSONSeq (one Feature per line) to stdout, filtered to the
# requested STATEFP and reprojected to 4326. jq narrows each feature to the
# adapter's key set; `// empty`-free strict mapping keeps GEOID/STATEFP as the
# strings ogr2ogr already emits (TIGER stores them as text fields).
ogr2ogr -f GeoJSONSeq /vsistdout/ \
  -where "STATEFP='${STATE_FIPS}'" \
  -t_srs EPSG:4326 \
  "$INPUT_SHP" \
| jq -c '{geoid: .properties.GEOID, name: .properties.NAME, namelsad: .properties.NAMELSAD, statefp: .properties.STATEFP, geometry: .geometry}' \
> "$OUT_NDJSON"

# --- verify non-empty -------------------------------------------------------
ROW_COUNT="$(grep -c '^' "$OUT_NDJSON" || true)"
if [ "${ROW_COUNT:-0}" -eq 0 ]; then
  rm -f "$OUT_NDJSON"
  die "conversion produced 0 rows for STATEFP=${STATE_FIPS} — nothing written."
fi

printf '[tiger_county_shp_to_ndjson] wrote %s rows to %s (STATEFP=%s, EPSG:4326)\n' \
  "$ROW_COUNT" "$OUT_NDJSON" "$STATE_FIPS"
