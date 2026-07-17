-- Batch 2B — DIAGNOSTIC (LIVE): per-category counts WITH and WITHOUT the 0.90
-- floor, CONUS. Added to resolve the fitness_center anomaly surfaced by
-- count_per_category.sql (only 7 categories returned, not 8).
--
-- Finding: categories.primary = 'fitness_center' yields 0 rows even pre-floor in
-- release 2026-06-17.0 — a true taxonomy absence, not a confidence-floor artifact.
-- All gym-type places carry categories.primary = 'gym'. Pre-floor sum = 796,811
-- (matches the confidence histogram total); post-floor sum = 479,042 (headline).
--
-- Run: duckdb -c ".read sql/diagnostic_per_category_prefloor.sql"
LOAD httpfs; SET s3_region='us-west-2';
SELECT
    categories.primary AS primary_category,
    count(*)                                        AS rows_all_conf,
    count(*) FILTER (WHERE confidence >= 0.90)      AS rows_kept
FROM read_parquet(
    's3://overturemaps-us-west-2/release/2026-06-17.0/theme=places/type=place/*.parquet',
    hive_partitioning = 1
)
WHERE categories.primary IN (
        'grocery_store','restaurant','pharmacy','shopping_center',
        'coffee_shop','gym','fitness_center','gas_station')
  AND bbox.xmin >= -124.85 AND bbox.xmax <= -66.88
  AND bbox.ymin >=   24.40 AND bbox.ymax <=  49.40
GROUP BY 1
ORDER BY 2 DESC;
