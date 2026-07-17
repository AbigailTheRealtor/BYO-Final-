-- ─────────────────────────────────────────────────────────────────────────────
-- Spatial Intelligence Platform — Phase 2 Batch 2A (B2)
-- Overture Places · FIRST SLICE · OFFLINE extraction (DuckDB, GeoParquet → NDJSON)
-- ─────────────────────────────────────────────────────────────────────────────
--
-- AUTHORED, NOT RUN. DuckDB is intentionally NOT installed in Batch 2A and no
-- Overture data is downloaded. This script is the live-run recipe for the later
-- Class-2 phase. It connects to NO PostGIS and needs NO SPATIAL_* secrets — it
-- reads public Overture GeoParquet from S3 and writes a local NDJSON file.
--
-- The pins below MUST equal config/overture_places.php (asserted by
-- tests/Unit/Spatial/OvertureExtractSqlManifestTest):
--   • release        2026-06-17.0   (latest stable monthly at 2026-07-17)
--   • confidence_min  0.90
--   • the 8 first-slice PRIMARY categories (source tokens)
--
-- Schema note: in release 2026-06-17.0 the `categories` struct (with
-- `categories.primary`) is DEPRECATED (removed Sept 2026 in favour of
-- `basic_category`/`taxonomy`) but still present. This slice reads
-- `categories.primary` ONLY — alternate categories are never inspected. Category
-- → canonical MAPPING is NOT done here; it stays in OvertureCategoryMap (PHP
-- SSOT). This script filters to the 8 primary source categories and projects the
-- raw fields the PHP normalizer consumes.
--
-- Run (later, when a workstation has DuckDB + network):
--   duckdb -c ".read spikes/phase-2-batch-2a-overture-first-slice/sql/extract_places.sql"
-- ─────────────────────────────────────────────────────────────────────────────

INSTALL httpfs;  LOAD httpfs;
INSTALL spatial; LOAD spatial;
SET s3_region = 'us-west-2';

-- Pins (keep in lockstep with config/overture_places.php).
SET VARIABLE release        = '2026-06-17.0';
SET VARIABLE confidence_min = 0.90;

-- Bounding box — CONUS default [west, south, east, north].
-- Swap these four literals for a region (see config regions):
--   pinellas -82.90 27.55 -82.53 28.17   florida -87.63 24.40 -79.97 31.00
SET VARIABLE bbox_west  = -124.85;
SET VARIABLE bbox_south =   24.40;
SET VARIABLE bbox_east  =  -66.88;
SET VARIABLE bbox_north =   49.40;

COPY (
    SELECT
        id                                   AS source_ref,
        id                                   AS gers_id,
        categories.primary                   AS primary_category,   -- PRIMARY ONLY
        confidence                           AS confidence,
        names.primary                        AS name,
        brand.names.primary                  AS brand,
        length(sources)                      AS source_count,
        -- With `LOAD spatial` active, read_parquet auto-decodes the GeoParquet
        -- geometry column to native GEOMETRY (not a WKB blob), so ST_X/ST_Y read
        -- it directly — wrapping it in a WKB-blob decoder would be a type error.
        -- Verified by the live Pinellas smoke run (Batch 2B): 1,675 rows, valid lon/lat.
        ST_X(geometry)                       AS lon,
        ST_Y(geometry)                       AS lat
    FROM read_parquet(
        's3://overturemaps-us-west-2/release/' || getvariable('release')
        || '/theme=places/type=place/*.parquet',
        filename = true, hive_partitioning = 1
    )
    WHERE confidence >= getvariable('confidence_min')
      -- PRIMARY-category-only filter: the 8 first-slice Overture source tokens.
      AND categories.primary IN (
            'grocery_store',
            'restaurant',
            'pharmacy',
            'shopping_center',
            'coffee_shop',
            'gym',
            'fitness_center',
            'gas_station'
      )
      -- bbox pushdown against Overture's precomputed bbox struct.
      AND bbox.xmin >= getvariable('bbox_west')
      AND bbox.xmax <= getvariable('bbox_east')
      AND bbox.ymin >= getvariable('bbox_south')
      AND bbox.ymax <= getvariable('bbox_north')
)
TO 'overture_first_slice_raw.ndjson' (FORMAT JSON, ARRAY false);

-- The emitted NDJSON is the raw projection; feed it to the PHP normalizer to
-- apply the canonical mapping + confidence/unmapped accounting:
--   php artisan corpus:extract-overture --input=overture_first_slice_raw.ndjson --output=normalized.ndjson
