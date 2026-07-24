-- ============================================================================
-- verify_boundaries.sql  —  AUTHORED, NOT RUN · READ-ONLY
-- Spatial Intelligence Platform · Phase 2 Batch 2D Part C3d-c (G6)
-- Post-load proofs for the Florida TIGER county load
-- ============================================================================
--
-- This is the CLASS-2 recipe. It requires the pgsql_spatial PostGIS cluster
-- (SPATIAL_DATABASE_URL / SPATIAL_PGHOST) and a completed load. It is NEVER
-- executed offline / in CI.
--
-- STRICTLY READ-ONLY: every statement is a SELECT. It never writes, mutates,
-- or remediates — no INSERT / UPDATE / DELETE / DROP / TRUNCATE / ALTER /
-- CREATE, and it never calls ST_MakeValid (validity is verification-only; any
-- remediation is a separate, evidence-gated Class-2 decision).
--
-- Run (later, Class-2):
--   psql "$SPATIAL_DATABASE_URL" -v corpus_version=tiger-2024 -f verify_boundaries.sql
-- ----------------------------------------------------------------------------

-- 1. Exactly 67 county boundaries for this corpus_version.
SELECT '01_county_count' AS check,
       count(*) AS actual, 67 AS expected,
       (count(*) = 67) AS pass
FROM boundaries
WHERE kind = 'county' AND corpus_version = :'corpus_version';

-- 2. boundaries_parts derived (>= 67; ST_Subdivide fans complex counties into more).
SELECT '02_parts_count_ge_67' AS check,
       count(*) AS actual, 67 AS min_expected,
       (count(*) >= 67) AS pass
FROM boundaries_parts p
JOIN boundaries b ON b.id = p.boundary_id
WHERE b.corpus_version = :'corpus_version' AND b.kind = 'county';

-- 3. No external_ref (GEOID) outside the Florida county pattern ^12[0-9]{3}$.
SELECT '03_no_non_fl_geoid' AS check,
       count(*) AS offending, 0 AS expected,
       (count(*) = 0) AS pass
FROM boundaries
WHERE kind = 'county' AND corpus_version = :'corpus_version'
  AND external_ref !~ '^12[0-9]{3}$';

-- 4. No attrs.state_fips other than '12'.
SELECT '04_state_fips_all_12' AS check,
       count(*) AS offending, 0 AS expected,
       (count(*) = 0) AS pass
FROM boundaries
WHERE kind = 'county' AND corpus_version = :'corpus_version'
  AND (attrs->>'state_fips') IS DISTINCT FROM '12';

-- 5. No invalid geometries (verification only — never auto-remediated).
SELECT '05_no_invalid_geometry' AS check,
       count(*) AS offending, 0 AS expected,
       (count(*) = 0) AS pass
FROM boundaries
WHERE kind = 'county' AND corpus_version = :'corpus_version'
  AND NOT ST_IsValid(geom::geometry);

-- 5b. Detail rows for any invalid geometry (expected: zero rows).
SELECT b.id, b.external_ref, ST_IsValidReason(b.geom::geometry) AS reason
FROM boundaries b
WHERE b.kind = 'county' AND b.corpus_version = :'corpus_version'
  AND NOT ST_IsValid(b.geom::geometry);

-- 6. Every subdivided part is <= 256 vertices (ST_Subdivide cap, E-24).
SELECT '06_max_part_vertices_le_256' AS check,
       coalesce(max(ST_NPoints(p.geom::geometry)), 0) AS max_vertices, 256 AS cap,
       (coalesce(max(ST_NPoints(p.geom::geometry)), 0) <= 256) AS pass
FROM boundaries_parts p
JOIN boundaries b ON b.id = p.boundary_id
WHERE b.corpus_version = :'corpus_version' AND b.kind = 'county';

-- 7. Exactly one active ledger row for this import (SSOT E-32).
SELECT '07_ledger_active_row' AS check,
       count(*) AS actual, 1 AS expected,
       (count(*) = 1) AS pass
FROM corpus_imports
WHERE dataset = 'census-tiger-county-fl'
  AND corpus_version = :'corpus_version'
  AND status = 'active';

-- 8. Sibling Spatial tables remain at their documented baseline (this county-only
--    load writes ONLY boundaries + boundaries_parts + one corpus_imports row).
--    places / addresses / place_* / listing_locations / isochrone_cache stay 0.
SELECT '08_sibling_tables_zero' AS check,
       ( (SELECT count(*) FROM places)
       + (SELECT count(*) FROM addresses)
       + (SELECT count(*) FROM place_categories)
       + (SELECT count(*) FROM place_category_mappings)
       + (SELECT count(*) FROM place_authority_links)
       + (SELECT count(*) FROM listing_locations)
       + (SELECT count(*) FROM isochrone_cache) ) AS total, 0 AS expected,
       ( ( (SELECT count(*) FROM places)
         + (SELECT count(*) FROM addresses)
         + (SELECT count(*) FROM place_categories)
         + (SELECT count(*) FROM place_category_mappings)
         + (SELECT count(*) FROM place_authority_links)
         + (SELECT count(*) FROM listing_locations)
         + (SELECT count(*) FROM isochrone_cache) ) = 0 ) AS pass;
