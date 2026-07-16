-- ============================================================================
-- B1.2 — Tier-2 mixed-geometry fixture generator  (RUN LATER, on the cluster)
-- Spatial Intelligence Platform · Phase 2 Batch 1b
--
-- Builds a ~5,000,200-row synthetic `places` corpus with a TRUE geography
-- (Geometry,4326) mix (Point + Polygon + LineString), reproducing the exact
-- Stage 0b Tier-2 category distribution, into a throwaway partition. It exists
-- to feed the E-50 mixed-KNN EXPLAIN acceptance gate (plan §6) against a
-- realistically sparse corpus — a near-empty table would let the planner pick a
-- seq scan and yield a misleading EXPLAIN.
--
-- Precondition: the 11 B1.2 migrations have been applied on pgsql_spatial.
-- Run:  psql "$SPATIAL_DATABASE_URL" -v ON_ERROR_STOP=1 -f generate_tier2_fixture.sql
-- Teardown afterwards:  DROP TABLE IF EXISTS places_fixture_tier2;   -- (see README §4)
--
-- The per-category counts below are the SPEC in Tests\Support\Spatial\FixtureCorpusPlan;
-- FixtureCorpusPlanTest asserts this manifest and the generate_series() calls agree.
--
-- @fixture-manifest
--   corpus_version = fixture-tier2-v1
--   restaurant   = 2265000  Point
--   retail_store = 1700000  Point
--   school       = 566000   Point
--   park         = 425000   Polygon
--   urgent_care  = 25500    Point
--   marina       = 8500     Point
--   boat_ramp    = 6200     LineString
--   airport      = 4000     Polygon
-- @end-manifest
-- ============================================================================

BEGIN;

-- 1. Category parents (FK for places.category_key). Idempotent.
INSERT INTO place_categories (category_key, label, base_source, rank_strategy, enabled) VALUES
  ('restaurant',   'Restaurant',    'overture', 'brand',    true),
  ('retail_store', 'Retail Store',  'overture', 'brand',    true),
  ('school',       'School',        'nces',     'authority',true),
  ('park',         'Park',          'padus',    'area',     true),
  ('urgent_care',  'Urgent Care',   'cms',      'authority',true),
  ('marina',       'Marina',        'overture', 'distance', true),
  ('boat_ramp',    'Boat Ramp',     'osm',      'distance', true),
  ('airport',      'Airport',       'faa',      'authority',true)
ON CONFLICT (category_key) DO NOTHING;

-- 2. Throwaway fixture partition (never a production corpus_version).
CREATE TABLE IF NOT EXISTS places_fixture_tier2
  PARTITION OF places FOR VALUES IN ('fixture-tier2-v1');

-- Bounding box ~ US + territories longitudes/latitudes, for plausible spread.
-- lon = -125 + random()*58   (-125 .. -67)
-- lat =   24 + random()*25   (  24 ..  49)

-- 3a. POINT categories -------------------------------------------------------
INSERT INTO places (corpus_version, source, source_ref, geom, centroid, category_key, name, confidence)
SELECT 'fixture-tier2-v1', 'fixture', 'restaurant-'||g, q.pt::geography, q.pt::geography, 'restaurant', 'restaurant #'||g, 0.95
FROM generate_series(1, 2265000) g
CROSS JOIN LATERAL (SELECT ST_SetSRID(ST_MakePoint(-125+random()*58, 24+random()*25), 4326) AS pt) q;

INSERT INTO places (corpus_version, source, source_ref, geom, centroid, category_key, name, confidence)
SELECT 'fixture-tier2-v1', 'fixture', 'retail_store-'||g, q.pt::geography, q.pt::geography, 'retail_store', 'retail_store #'||g, 0.95
FROM generate_series(1, 1700000) g
CROSS JOIN LATERAL (SELECT ST_SetSRID(ST_MakePoint(-125+random()*58, 24+random()*25), 4326) AS pt) q;

INSERT INTO places (corpus_version, source, source_ref, geom, centroid, category_key, name, confidence)
SELECT 'fixture-tier2-v1', 'fixture', 'school-'||g, q.pt::geography, q.pt::geography, 'school', 'school #'||g, 0.95
FROM generate_series(1, 566000) g
CROSS JOIN LATERAL (SELECT ST_SetSRID(ST_MakePoint(-125+random()*58, 24+random()*25), 4326) AS pt) q;

INSERT INTO places (corpus_version, source, source_ref, geom, centroid, category_key, name, confidence)
SELECT 'fixture-tier2-v1', 'fixture', 'urgent_care-'||g, q.pt::geography, q.pt::geography, 'urgent_care', 'urgent_care #'||g, 0.95
FROM generate_series(1, 25500) g
CROSS JOIN LATERAL (SELECT ST_SetSRID(ST_MakePoint(-125+random()*58, 24+random()*25), 4326) AS pt) q;

INSERT INTO places (corpus_version, source, source_ref, geom, centroid, category_key, name, confidence)
SELECT 'fixture-tier2-v1', 'fixture', 'marina-'||g, q.pt::geography, q.pt::geography, 'marina', 'marina #'||g, 0.95
FROM generate_series(1, 8500) g
CROSS JOIN LATERAL (SELECT ST_SetSRID(ST_MakePoint(-125+random()*58, 24+random()*25), 4326) AS pt) q;

-- 3b. POLYGON categories (areas — SSOT §7.4) --------------------------------
INSERT INTO places (corpus_version, source, source_ref, geom, centroid, category_key, name, confidence)
SELECT 'fixture-tier2-v1', 'fixture', 'park-'||g, q.poly::geography, ST_Centroid(q.poly)::geography, 'park', 'park #'||g, 0.95
FROM generate_series(1, 425000) g
CROSS JOIN LATERAL (
  SELECT ST_MakeEnvelope(c.lon, c.lat, c.lon+0.02, c.lat+0.02, 4326) AS poly
  FROM (SELECT -125+random()*58 AS lon, 24+random()*25 AS lat) c
) q;

INSERT INTO places (corpus_version, source, source_ref, geom, centroid, category_key, name, confidence)
SELECT 'fixture-tier2-v1', 'fixture', 'airport-'||g, q.poly::geography, ST_Centroid(q.poly)::geography, 'airport', 'airport #'||g, 0.95
FROM generate_series(1, 4000) g
CROSS JOIN LATERAL (
  SELECT ST_MakeEnvelope(c.lon, c.lat, c.lon+0.03, c.lat+0.03, 4326) AS poly
  FROM (SELECT -125+random()*58 AS lon, 24+random()*25 AS lat) c
) q;

-- 3c. LINESTRING category ----------------------------------------------------
INSERT INTO places (corpus_version, source, source_ref, geom, centroid, category_key, name, confidence)
SELECT 'fixture-tier2-v1', 'fixture', 'boat_ramp-'||g, q.ln::geography, ST_Centroid(q.ln)::geography, 'boat_ramp', 'boat_ramp #'||g, 0.95
FROM generate_series(1, 6200) g
CROSS JOIN LATERAL (
  SELECT ST_SetSRID(ST_MakeLine(ST_MakePoint(c.lon, c.lat), ST_MakePoint(c.lon+0.01, c.lat+0.01)), 4326) AS ln
  FROM (SELECT -125+random()*58 AS lon, 24+random()*25 AS lat) c
) q;

COMMIT;

-- 4. Planner stats are MANDATORY before EXPLAIN — without them the planner may
--    misjudge selectivity and pick a seq scan regardless of the index.
ANALYZE places;

-- Sanity: expect total = 5,000,200 for corpus_version 'fixture-tier2-v1'.
SELECT category_key, count(*) AS rows
FROM places WHERE corpus_version = 'fixture-tier2-v1'
GROUP BY category_key ORDER BY rows DESC;
