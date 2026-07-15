-- Phase 2 · Batch 0 · Stage 0a — PostGIS/KNN feasibility spike
-- 10_generate_data.sql — deterministic synthetic corpus loader
--
-- Determinism: setseed() fixes the PRNG for this session. Running this file as a
-- single unit against a freshly-created table reproduces the identical corpus.
--
-- Category mix is deliberately skewed so a "filter-walk" failure is visible:
--   * DENSE filler categories (restaurant/retail_store/school/park) dominate the
--     table, so an index that orders by distance FIRST and filters category AFTER
--     must walk through many dense rows before finding sparse-category hits.
--   * SPARSE realistic categories (urgent_care/marina/boat_ramp/airport) are the
--     ones we actually query; they are the stress case.
--
-- Geographic extent: continental-US bounding box.

\set ON_ERROR_STOP on

SELECT setseed(0.20260715);

WITH cats(category_key, n, v2_frac) AS (
    VALUES
        -- dense filler (make filter-walk expensive)
        ('restaurant',   80000, 0.60),
        ('retail_store', 60000, 0.60),
        ('school',       20000, 0.60),
        ('park',         15000, 0.60),
        -- sparse realistic categories (the query stress case)
        ('urgent_care',    900, 0.80),
        ('marina',         300, 0.80),
        ('boat_ramp',      220, 0.80),
        ('airport',        140, 0.80)
),
expanded AS (
    SELECT c.category_key, c.v2_frac, gs AS seq
    FROM cats c
    CROSS JOIN LATERAL generate_series(1, c.n) AS gs
)
INSERT INTO places_spike (id, category_key, corpus_version, name, geom)
SELECT
    row_number() OVER ()                                   AS id,
    category_key,
    CASE WHEN random() < v2_frac THEN 'v2' ELSE 'v1' END   AS corpus_version,
    category_key || '_' || seq                             AS name,
    ST_SetSRID(
        ST_MakePoint(
            -124.7 + random() * (124.7 - 66.9),   -- lon in [-124.7, -66.9]
            24.5   + random() * (49.4  - 24.5)     -- lat in [ 24.5,  49.4]
        ),
        4326
    )::geography                                           AS geom
FROM expanded;

-- Mirror the identical corpus into the partitioned table so plan comparisons
-- between the two physical layouts are apples-to-apples.
INSERT INTO places_spike_part (id, category_key, corpus_version, name, geom)
SELECT id, category_key, corpus_version, name, geom FROM places_spike;

\echo '10_generate_data.sql complete: corpus loaded into both tables'
