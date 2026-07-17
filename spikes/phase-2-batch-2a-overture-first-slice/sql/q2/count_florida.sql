-- Batch 2A (B4) — Q2 COUNT-ONLY · Florida
-- First-slice filtered row count (confidence >= 0.90, 8 primary categories).
-- bbox from config/overture_places.php regions.florida [-87.63,24.40,-79.97,31.00]
-- AUTHORED, NOT RUN (no DuckDB, no download in Batch 2A).
INSTALL httpfs; LOAD httpfs; SET s3_region = 'us-west-2';
SELECT count(*) AS florida_first_slice_rows
FROM read_parquet(
    's3://overturemaps-us-west-2/release/2026-06-17.0/theme=places/type=place/*.parquet',
    hive_partitioning = 1
)
WHERE confidence >= 0.90
  AND categories.primary IN (
        'grocery_store','restaurant','pharmacy','shopping_center',
        'coffee_shop','gym','fitness_center','gas_station')
  AND bbox.xmin >= -87.63 AND bbox.xmax <= -79.97
  AND bbox.ymin >=  24.40 AND bbox.ymax <=  31.00;
