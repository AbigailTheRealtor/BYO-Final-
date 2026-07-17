-- Batch 2A (B4) — Q2 COUNT-ONLY · CONUS
-- First-slice filtered row count (confidence >= 0.90, 8 primary categories).
-- bbox from config/overture_places.php regions.conus [-124.85,24.40,-66.88,49.40]
-- This is the headline Q2 number (Appendix E, Q2): CONUS first-slice row count.
-- AUTHORED, NOT RUN (no DuckDB, no download in Batch 2A).
INSTALL httpfs; LOAD httpfs; SET s3_region = 'us-west-2';
SELECT count(*) AS conus_first_slice_rows
FROM read_parquet(
    's3://overturemaps-us-west-2/release/2026-06-17.0/theme=places/type=place/*.parquet',
    hive_partitioning = 1
)
WHERE confidence >= 0.90
  AND categories.primary IN (
        'grocery_store','restaurant','pharmacy','shopping_center',
        'coffee_shop','gym','fitness_center','gas_station')
  AND bbox.xmin >= -124.85 AND bbox.xmax <= -66.88
  AND bbox.ymin >=   24.40 AND bbox.ymax <=  49.40;
