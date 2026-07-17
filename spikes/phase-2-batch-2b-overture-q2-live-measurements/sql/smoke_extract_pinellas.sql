-- Batch 2B — Pinellas smoke extraction (LIVE), adapted from the committed 2A
-- recipe spikes/phase-2-batch-2a-overture-first-slice/sql/extract_places.sql.
--
-- ONLY change vs 2A: geometry is native GEOMETRY('OGC:CRS84') under `LOAD spatial`
-- (GeoParquet auto-decode in DuckDB 1.5), so ST_X(geometry)/ST_Y(geometry) are
-- used directly instead of ST_X(ST_GeomFromWKB(geometry)). Pins/filters unchanged:
--   release 2026-06-17.0 · confidence >= 0.90 · 8 first-slice primary tokens.
--
-- Run: duckdb -c ".read sql/smoke_extract_pinellas.sql"
-- Produces a raw NDJSON (feed to `php artisan corpus:extract-overture`).
-- Result: 1,675 rows (== count_pinellas.sql).
LOAD httpfs; LOAD spatial;
SET s3_region='us-west-2';
COPY (
    SELECT
        id                            AS source_ref,
        id                            AS gers_id,
        categories.primary            AS primary_category,
        confidence                    AS confidence,
        names.primary                 AS name,
        brand.names.primary           AS brand,
        length(sources)               AS source_count,
        ST_X(geometry)                AS lon,
        ST_Y(geometry)                AS lat
    FROM read_parquet(
        's3://overturemaps-us-west-2/release/2026-06-17.0/theme=places/type=place/*.parquet',
        hive_partitioning = 1
    )
    WHERE confidence >= 0.90
      AND categories.primary IN (
            'grocery_store','restaurant','pharmacy','shopping_center',
            'coffee_shop','gym','fitness_center','gas_station')
      AND bbox.xmin >= -82.90 AND bbox.xmax <= -82.53
      AND bbox.ymin >=  27.55 AND bbox.ymax <=  28.17
)
TO 'pinellas_first_slice_raw.ndjson' (FORMAT JSON, ARRAY false);
