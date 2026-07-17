-- Batch 2A (B4) — Q2 COUNT-ONLY · confidence histogram (CONUS, first-slice cats)
-- Distribution of Overture `confidence` in 0.05-wide buckets for the 8 primary
-- categories, BEFORE the >=0.90 floor is applied — so the cost of the floor is
-- visible (how many rows the 0.90 cut drops). Swap the bbox for a region.
-- AUTHORED, NOT RUN (no DuckDB, no download in Batch 2A).
INSTALL httpfs; LOAD httpfs; SET s3_region = 'us-west-2';
SELECT
    floor(confidence / 0.05) * 0.05 AS bucket_low,
    count(*)                        AS rows,
    (confidence >= 0.90)            AS kept_by_floor
FROM read_parquet(
    's3://overturemaps-us-west-2/release/2026-06-17.0/theme=places/type=place/*.parquet',
    hive_partitioning = 1
)
WHERE categories.primary IN (
        'grocery_store','restaurant','pharmacy','shopping_center',
        'coffee_shop','gym','fitness_center','gas_station')
  AND confidence IS NOT NULL
  AND bbox.xmin >= -124.85 AND bbox.xmax <= -66.88
  AND bbox.ymin >=   24.40 AND bbox.ymax <=  49.40
GROUP BY 1, 3
ORDER BY 1;
