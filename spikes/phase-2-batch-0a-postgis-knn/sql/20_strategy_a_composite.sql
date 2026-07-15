-- Phase 2 · Batch 0 · Stage 0a — PostGIS/KNN feasibility spike
-- 20_strategy_a_composite.sql — STRATEGY A: composite GiST (category_key, geom)
--
-- This is the SSOT candidate. btree_gist lets the scalar category_key sit ahead
-- of the geography column in one GiST index, so category equality becomes an
-- Index Cond and the KNN <-> ordering happens INSIDE the same index scan.
--
-- Isolation: drop every competing index first so the planner has exactly one
-- relevant index to choose. ANALYZE before every plan capture.

\set ON_ERROR_STOP on

-- deterministic, readable plans
SET jit = off;
SET max_parallel_workers_per_gather = 0;

-- fixed reference point (Miami harbor area — plausible for marinas/boat ramps)
\set ref 'ST_SetSRID(ST_MakePoint(-80.19, 25.76), 4326)::geography'

-- --- isolate strategy A -----------------------------------------------------
DROP INDEX IF EXISTS places_geom_only;
DROP INDEX IF EXISTS places_geom_boat_ramp;
DROP INDEX IF EXISTS places_geom_airport;
DROP INDEX IF EXISTS places_geom_marina;
DROP INDEX IF EXISTS places_geom_urgent_care;
DROP INDEX IF EXISTS places_cat_geom;
DROP INDEX IF EXISTS places_part_cat_geom;

CREATE INDEX places_cat_geom
    ON places_spike
    USING gist (category_key, geom);

CREATE INDEX places_part_cat_geom
    ON places_spike_part
    USING gist (category_key, geom);

ANALYZE places_spike;
ANALYZE places_spike_part;
ANALYZE places_spike_part_v1;
ANALYZE places_spike_part_v2;

\echo '=== STRATEGY A / composite gist (category_key, geom) — UNPARTITIONED ==='

\echo '--- A: boat_ramp ---'
EXPLAIN (ANALYZE, BUFFERS, VERBOSE)
SELECT id, category_key, ST_Distance(geom, :ref) AS meters
FROM places_spike
WHERE category_key = 'boat_ramp'
ORDER BY geom <-> :ref
LIMIT 10;

\echo '--- A: airport ---'
EXPLAIN (ANALYZE, BUFFERS, VERBOSE)
SELECT id, category_key, ST_Distance(geom, :ref) AS meters
FROM places_spike
WHERE category_key = 'airport'
ORDER BY geom <-> :ref
LIMIT 10;

\echo '--- A: marina ---'
EXPLAIN (ANALYZE, BUFFERS, VERBOSE)
SELECT id, category_key, ST_Distance(geom, :ref) AS meters
FROM places_spike
WHERE category_key = 'marina'
ORDER BY geom <-> :ref
LIMIT 10;

\echo '--- A: urgent_care ---'
EXPLAIN (ANALYZE, BUFFERS, VERBOSE)
SELECT id, category_key, ST_Distance(geom, :ref) AS meters
FROM places_spike
WHERE category_key = 'urgent_care'
ORDER BY geom <-> :ref
LIMIT 10;

\echo '=== STRATEGY A / composite gist — PARTITIONED (corpus_version = v2) ==='

\echo '--- A(part): boat_ramp @ v2 ---'
EXPLAIN (ANALYZE, BUFFERS, VERBOSE)
SELECT id, category_key, ST_Distance(geom, :ref) AS meters
FROM places_spike_part
WHERE category_key = 'boat_ramp' AND corpus_version = 'v2'
ORDER BY geom <-> :ref
LIMIT 10;

\echo '--- A(part): urgent_care @ v2 ---'
EXPLAIN (ANALYZE, BUFFERS, VERBOSE)
SELECT id, category_key, ST_Distance(geom, :ref) AS meters
FROM places_spike_part
WHERE category_key = 'urgent_care' AND corpus_version = 'v2'
ORDER BY geom <-> :ref
LIMIT 10;

\echo '--- index inventory after strategy A ---'
SELECT indexname, indexdef FROM pg_indexes
WHERE tablename LIKE 'places_spike%' ORDER BY indexname;
