-- Batch 2A (B4) — Q2 COUNT-ONLY · Pinellas County, FL
-- First-slice filtered row count (confidence >= 0.90, 8 primary categories).
-- bbox from config/overture_places.php regions.pinellas [-82.90,27.55,-82.53,28.17]
-- AUTHORED, NOT RUN (no DuckDB, no download in Batch 2A).
INSTALL httpfs; LOAD httpfs; SET s3_region = 'us-west-2';
SELECT count(*) AS pinellas_first_slice_rows
FROM read_parquet(
    's3://overturemaps-us-west-2/release/2026-06-17.0/theme=places/type=place/*.parquet',
    hive_partitioning = 1
)
WHERE confidence >= 0.90
  AND categories.primary IN (
        'grocery_store','restaurant','pharmacy','shopping_center',
        'coffee_shop','gym','fitness_center','gas_station')
  AND bbox.xmin >= -82.90 AND bbox.xmax <= -82.53
  AND bbox.ymin >=  27.55 AND bbox.ymax <=  28.17;
