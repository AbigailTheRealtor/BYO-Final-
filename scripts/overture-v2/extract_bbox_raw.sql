-- overture-extract-v2 — raw bounding-box extract (OFFLINE, run by hand; never by the application).
--
-- Pins (must agree with config/overture_extract_v2.php; OvertureExtractV2SqlManifestTest checks):
--   release   2026-08-19.0
--   bbox      west -87.63 · south 24.40 · east -79.97 · north 31.00, applied as CONTAINMENT of
--             each row's own bbox (identical to v1 and PR 0). A rectangle, not the state line:
--             the measured GA/AL spill-over is kept.
--
-- The bounding box is the ONLY filter. Confidence, operating status, taxonomy and the
-- supplementary selector are decided by `corpus:extract-overture-v2`, so every rejection is
-- counted there instead of disappearing here (v2 design §2: "v2 must account at extraction time").
--
-- Anonymous read of the public bucket. Blank any ambient AWS credentials, or DuckDB presents them
-- and the anonymous read is refused.
--
-- Run:  duckdb -c ".read scripts/overture-v2/extract_bbox_raw.sql"
-- Out:  overture_v2_raw_bbox.ndjson  (scratch only — never committed)
-- 2026-09-23: 1,245,925 rows.
INSTALL httpfs; LOAD httpfs;
INSTALL spatial; LOAD spatial;
SET s3_region = 'us-west-2';
SET s3_access_key_id = '';
SET s3_secret_access_key = '';
SET s3_session_token = '';
COPY (
    SELECT
        id                            AS id,
        names.primary                 AS name,
        brand.names.primary           AS brand_name,
        brand.wikidata                AS brand_wikidata,
        taxonomy.primary              AS taxonomy_primary,
        categories.primary            AS categories_primary,
        basic_category                AS basic_category,
        operating_status              AS operating_status,
        confidence                    AS confidence,
        ST_GeometryType(geometry)     AS geometry_type,
        ST_X(geometry)                AS lon,
        ST_Y(geometry)                AS lat,
        addresses[1].freeform         AS address_freeform,
        addresses[1].locality         AS address_locality,
        addresses[1].postcode         AS address_postcode,
        addresses[1].region           AS address_region,
        addresses[1].country          AS address_country
    FROM read_parquet(
        's3://overturemaps-us-west-2/release/2026-08-19.0/theme=places/type=place/*.parquet',
        hive_partitioning = 1
    )
    WHERE bbox.xmin >= -87.63 AND bbox.xmax <= -79.97
      AND bbox.ymin >=  24.40 AND bbox.ymax <=  31.00
    ORDER BY id
)
TO 'overture_v2_raw_bbox.ndjson' (FORMAT JSON, ARRAY false);
