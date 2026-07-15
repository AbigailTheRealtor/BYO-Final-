-- Phase 2 · Batch 0 · Stage 0a — PostGIS/KNN feasibility spike
-- 40_strategy_c_partial.sql — STRATEGY C (fallback): partial GiST per sparse cat
--
-- Fallback if the composite index fails the success criteria. One partial
-- gist(geom) index per sparse category, each carrying its own
--   WHERE category_key = '<cat>'
-- predicate. A query for that category matches the partial index's predicate, so
-- the whole index already contains only that category — no post-filter walk.
--
-- Trade-off (documented, not tested here): N indexes to maintain, one per sparse
-- category, versus a single composite index that covers all categories.

\set ON_ERROR_STOP on

SET jit = off;
SET max_parallel_workers_per_gather = 0;

\set ref 'ST_SetSRID(ST_MakePoint(-80.19, 25.76), 4326)::geography'

-- --- isolate strategy C -----------------------------------------------------
DROP INDEX IF EXISTS places_cat_geom;
DROP INDEX IF EXISTS places_part_cat_geom;
DROP INDEX IF EXISTS places_geom_only;
DROP INDEX IF EXISTS places_geom_boat_ramp;
DROP INDEX IF EXISTS places_geom_airport;
DROP INDEX IF EXISTS places_geom_marina;
DROP INDEX IF EXISTS places_geom_urgent_care;

CREATE INDEX places_geom_boat_ramp
    ON places_spike USING gist (geom) WHERE category_key = 'boat_ramp';
CREATE INDEX places_geom_airport
    ON places_spike USING gist (geom) WHERE category_key = 'airport';
CREATE INDEX places_geom_marina
    ON places_spike USING gist (geom) WHERE category_key = 'marina';
CREATE INDEX places_geom_urgent_care
    ON places_spike USING gist (geom) WHERE category_key = 'urgent_care';

ANALYZE places_spike;

\echo '=== STRATEGY C / partial gist(geom) per sparse category ==='

\echo '--- C: boat_ramp ---'
EXPLAIN (ANALYZE, BUFFERS, VERBOSE)
SELECT id, category_key, ST_Distance(geom, :ref) AS meters
FROM places_spike
WHERE category_key = 'boat_ramp'
ORDER BY geom <-> :ref
LIMIT 10;

\echo '--- C: airport ---'
EXPLAIN (ANALYZE, BUFFERS, VERBOSE)
SELECT id, category_key, ST_Distance(geom, :ref) AS meters
FROM places_spike
WHERE category_key = 'airport'
ORDER BY geom <-> :ref
LIMIT 10;

\echo '--- C: marina ---'
EXPLAIN (ANALYZE, BUFFERS, VERBOSE)
SELECT id, category_key, ST_Distance(geom, :ref) AS meters
FROM places_spike
WHERE category_key = 'marina'
ORDER BY geom <-> :ref
LIMIT 10;

\echo '--- C: urgent_care ---'
EXPLAIN (ANALYZE, BUFFERS, VERBOSE)
SELECT id, category_key, ST_Distance(geom, :ref) AS meters
FROM places_spike
WHERE category_key = 'urgent_care'
ORDER BY geom <-> :ref
LIMIT 10;

\echo '--- index inventory after strategy C ---'
SELECT indexname, indexdef FROM pg_indexes
WHERE tablename = 'places_spike' ORDER BY indexname;
