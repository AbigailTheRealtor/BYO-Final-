-- overture-extract-v2 — whole-release row count (OFFLINE, count only; writes nothing).
-- Feeds `corpus:extract-overture-v2 --release-row-count=N`, so rows outside the bounding box are
-- measured rather than assumed. Release pin must agree with config/overture_extract_v2.php.
-- 2026-09-23: 73,631,092 rows.
INSTALL httpfs; LOAD httpfs;
SET s3_region = 'us-west-2';
SET s3_access_key_id = '';
SET s3_secret_access_key = '';
SET s3_session_token = '';
SELECT count(*) AS release_rows
FROM read_parquet(
    's3://overturemaps-us-west-2/release/2026-08-19.0/theme=places/type=place/*.parquet',
    hive_partitioning = 1
);
