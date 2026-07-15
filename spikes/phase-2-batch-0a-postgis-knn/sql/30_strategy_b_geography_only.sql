-- Phase 2 · Batch 0 · Stage 0a — PostGIS/KNN feasibility spike
-- 30_strategy_b_geography_only.sql — STRATEGY B (control): geography-only GiST
--
-- Control experiment. A single gist(geom) index with NO category column. The
-- category predicate cannot be an Index Cond, so the planner must either:
--   (a) KNN-walk the geom index ordered by distance and DISCARD every row whose
--       category_key != target  (visible as "Rows Removed by Filter"), or
--   (b) fall back to a seq scan + sort.
-- For a sparse target sitting behind dense filler, (a) walks a large number of
-- wrong-category rows. This is the failure mode the composite index avoids.

\set ON_ERROR_STOP on

SET jit = off;
SET max_parallel_workers_per_gather = 0;

\set ref 'ST_SetSRID(ST_MakePoint(-80.19, 25.76), 4326)::geography'

-- --- isolate strategy B -----------------------------------------------------
DROP INDEX IF EXISTS places_cat_geom;
DROP INDEX IF EXISTS places_part_cat_geom;
DROP INDEX IF EXISTS places_geom_boat_ramp;
DROP INDEX IF EXISTS places_geom_airport;
DROP INDEX IF EXISTS places_geom_marina;
DROP INDEX IF EXISTS places_geom_urgent_care;
DROP INDEX IF EXISTS places_geom_only;

CREATE INDEX places_geom_only
    ON places_spike
    USING gist (geom);

ANALYZE places_spike;

\echo '=== STRATEGY B / geography-only gist(geom), category as post-filter ==='

\echo '--- B: boat_ramp ---'
EXPLAIN (ANALYZE, BUFFERS, VERBOSE)
SELECT id, category_key, ST_Distance(geom, :ref) AS meters
FROM places_spike
WHERE category_key = 'boat_ramp'
ORDER BY geom <-> :ref
LIMIT 10;

\echo '--- B: airport ---'
EXPLAIN (ANALYZE, BUFFERS, VERBOSE)
SELECT id, category_key, ST_Distance(geom, :ref) AS meters
FROM places_spike
WHERE category_key = 'airport'
ORDER BY geom <-> :ref
LIMIT 10;

\echo '--- B: marina ---'
EXPLAIN (ANALYZE, BUFFERS, VERBOSE)
SELECT id, category_key, ST_Distance(geom, :ref) AS meters
FROM places_spike
WHERE category_key = 'marina'
ORDER BY geom <-> :ref
LIMIT 10;

\echo '--- B: urgent_care ---'
EXPLAIN (ANALYZE, BUFFERS, VERBOSE)
SELECT id, category_key, ST_Distance(geom, :ref) AS meters
FROM places_spike
WHERE category_key = 'urgent_care'
ORDER BY geom <-> :ref
LIMIT 10;
