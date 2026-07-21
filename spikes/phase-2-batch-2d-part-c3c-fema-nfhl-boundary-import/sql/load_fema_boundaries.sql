-- ============================================================================
-- load_fema_boundaries.sql  —  AUTHORED, NOT RUN
-- Spatial Intelligence Platform · Phase 2 Batch 2D Part C3c
-- FEMA NFHL load: boundaries_staging -> boundaries + boundaries_parts
-- ============================================================================
--
-- This is the CLASS-2 recipe. It requires the pgsql_spatial PostGIS cluster
-- (SPATIAL_DATABASE_URL / SPATIAL_PGHOST), PostGIS, the `boundaries` (migration
-- 06) and `boundaries_parts` (migration 07) tables, and rows already placed by
-- stage_boundaries.sql. It is NEVER executed offline.
--
-- Flow: append the two FEMA kinds to `boundaries`, then derive `boundaries_parts`
-- via ST_Subdivide. No existing row is mutated.
--
-- Why both kinds (SSOT §10, E-11, INV-5): `flood_zone` alone cannot distinguish
-- "outside the SFHA" from "FEMA never mapped here". `flood_coverage` — the
-- effective FIRM panel footprint — is what makes "not mapped" honest. Loading the
-- hazard areas WITHOUT the coverage footprint is therefore not a partial success;
-- it is the failure mode the invariant exists to prevent.
--
-- Subdivision matters most here: NFHL is the largest single import in the
-- programme (SSOT §10 sizing: 10-40 GB), and coastal flood_zone polygons carry
-- far more vertices than PAD-US units or TIGER counties.
--
-- Run (later, Class-2): psql "$SPATIAL_DATABASE_URL" \
--   -v corpus_version=fema-nfhl-<vintage> -f load_fema_boundaries.sql
-- ----------------------------------------------------------------------------

-- 1. Append FEMA boundaries (id bigserial is assigned here).
INSERT INTO boundaries (kind, external_ref, attrs, geom, corpus_version)
SELECT s.kind, s.external_ref, s.attrs, s.geom, s.corpus_version
FROM boundaries_staging s
WHERE s.kind IN ('flood_zone', 'flood_coverage');

-- 2. Subdivide into boundaries_parts (E-24). ST_Subdivide does NOT accept
--    geography, so cast to geometry, subdivide at <=256 vertices, ST_Dump the
--    pieces, and cast each part back to geography(Polygon). Built at IMPORT time,
--    never at query time.
INSERT INTO boundaries_parts (boundary_id, geom)
SELECT b.id, (ST_Dump(ST_Subdivide(b.geom::geometry, 256))).geom::geography
FROM boundaries b
WHERE b.corpus_version = :'corpus_version'
  AND b.kind IN ('flood_zone', 'flood_coverage');

-- ----------------------------------------------------------------------------
-- Geometry validity — VERIFICATION ONLY (owner decision). Offline authoring is
-- structural only; this is the first place ST_IsValid runs. It writes nothing.
-- Any row it flags is remediated by a LATER Class-2 operational decision with
-- evidence — e.g. ST_MakeValid(geom::geometry)::geography — which this recipe
-- deliberately does NOT apply automatically (never silently alter geometry).
-- FEMA hazard polygons are digitized from panel-scale cartography and are a
-- realistic source of ring self-intersection, so expect this query to return rows.
-- ----------------------------------------------------------------------------
SELECT b.id, b.kind, b.external_ref, ST_IsValidReason(b.geom::geometry) AS reason
FROM boundaries b
WHERE b.corpus_version = :'corpus_version'
  AND b.kind IN ('flood_zone', 'flood_coverage')
  AND NOT ST_IsValid(b.geom::geometry);

-- ----------------------------------------------------------------------------
-- Coverage co-presence — VERIFICATION ONLY. Reports the per-kind row counts so an
-- operator can confirm the coverage footprint actually landed alongside the hazard
-- areas before anything downstream reads either. Writes nothing; decides nothing.
-- ----------------------------------------------------------------------------
SELECT b.kind, count(*) AS boundary_rows
FROM boundaries b
WHERE b.corpus_version = :'corpus_version'
  AND b.kind IN ('flood_zone', 'flood_coverage')
GROUP BY b.kind
ORDER BY b.kind;
