-- Phase 2 · Batch 0 · Stage 0b — provider validation
-- 10_generate_data_tier2.sql — deterministic ~5,000,200-row SCALE generator
--
-- Tier-2 companion to the committed Stage 0a `10_generate_data.sql`. Same schema
-- (populates `places_spike` created by the committed `00_setup.sql`), same
-- deterministic seed, same continental-US extent, same dense/sparse structure —
-- scaled ~28x so the planner's cost model is exercised at realistic corpus scale
-- while the four sparse categories stay genuinely sparse (< 0.6% each).
--
-- This file is NOT executed by the scaffolding commit. It runs only during a
-- Stage 0b provider run, against a provider chosen by the operator.

\set ON_ERROR_STOP on

SELECT setseed(0.20260715);

WITH cats(category_key, n, v2_frac) AS (
    VALUES
        -- dense filler (make filter-walk expensive at scale)
        ('restaurant',   2265000, 0.60),
        ('retail_store', 1700000, 0.60),
        ('school',        566000, 0.60),
        ('park',          425000, 0.60),
        -- sparse realistic categories (kept genuinely sparse at 5M scale)
        ('urgent_care',    25500, 0.80),
        ('marina',          8500, 0.80),
        ('boat_ramp',       6200, 0.80),
        ('airport',         4000, 0.80)
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

-- Mirror the identical corpus into the partitioned table.
INSERT INTO places_spike_part (id, category_key, corpus_version, name, geom)
SELECT id, category_key, corpus_version, name, geom FROM places_spike;

\echo '10_generate_data_tier2.sql complete: ~5,000,200-row scaled corpus loaded'
