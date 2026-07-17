-- Batch 2A (B4) — Q2 COUNT-ONLY · per PRIMARY category (CONUS)
-- Row count per Overture primary source category, so the corpus-mix and the
-- gym+fitness_center collapse can be reasoned about before load.
-- Swap the bbox literals for a region (defaults to CONUS).
-- AUTHORED, NOT RUN (no DuckDB, no download in Batch 2A).
INSTALL httpfs; LOAD httpfs; SET s3_region = 'us-west-2';
SELECT
    categories.primary AS primary_category,
    count(*)           AS rows
FROM read_parquet(
    's3://overturemaps-us-west-2/release/2026-06-17.0/theme=places/type=place/*.parquet',
    hive_partitioning = 1
)
WHERE confidence >= 0.90
  AND categories.primary IN (
        'grocery_store','restaurant','pharmacy','shopping_center',
        'coffee_shop','gym','fitness_center','gas_station')
  AND bbox.xmin >= -124.85 AND bbox.xmax <= -66.88
  AND bbox.ymin >=   24.40 AND bbox.ymax <=  49.40
GROUP BY 1
ORDER BY 2 DESC;
