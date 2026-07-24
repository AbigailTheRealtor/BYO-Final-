-- ─────────────────────────────────────────────────────────────────────────────
-- verify_overture_fl.sql  —  AUTHORED, NOT RUN · READ-ONLY
-- Spatial Intelligence Platform · Phase 2 Batch 2D Part C3d-d
-- Post-load proofs for the Florida Overture places load
-- ─────────────────────────────────────────────────────────────────────────────
--
-- CLASS-2 recipe: requires the pgsql_spatial cluster (SPATIAL_DATABASE_URL) and a
-- completed Florida Overture load. NEVER executed offline / in CI.
--
-- STRICTLY READ-ONLY — every statement is a SELECT. No INSERT / UPDATE / DELETE /
-- DROP / TRUNCATE / ALTER / CREATE. It attributes Overture places to Florida via
-- the loaded tiger-2024 county boundaries (the Gate 2 attribution layer).
--
-- corpus_version defaults to the Florida pilot version; override with
--   psql "$SPATIAL_DATABASE_URL" -v corpus_version=overture-2026-06-17.0-fl -f verify_overture_fl.sql
-- ─────────────────────────────────────────────────────────────────────────────

\if :{?corpus_version}
\else
\set corpus_version 'overture-2026-06-17.0-fl'
\endif

-- 1. Taxonomy seeded: the 7 canonical Gate-2 categories present.
SELECT '01_place_categories_7' AS check,
       count(*) AS actual, 7 AS expected,
       (count(*) = 7) AS pass
FROM place_categories
WHERE category_key IN ('grocery_store','restaurant','pharmacy','shopping_center','coffee_shop','gym','gas_station');

-- 2. Taxonomy seeded: the 8 Overture source-category mappings present.
SELECT '02_overture_mappings_8' AS check,
       count(*) AS actual, 8 AS expected,
       (count(*) = 8) AS pass
FROM place_category_mappings
WHERE source = 'overture';

-- 3. Places loaded for this corpus_version (informational — reconcile vs extract).
SELECT '03_places_total' AS check,
       count(*) AS places
FROM places
WHERE corpus_version = :'corpus_version';

-- 4. Per-category distribution (the 7 Gate-2 keys — informational).
SELECT category_key, count(*) AS n
FROM places
WHERE corpus_version = :'corpus_version'
GROUP BY category_key
ORDER BY category_key;

-- 5. Every category_key is a registered canonical (must be 0).
SELECT '05_no_unregistered_category' AS check,
       count(*) AS offending, 0 AS expected,
       (count(*) = 0) AS pass
FROM places p
LEFT JOIN place_categories c ON c.category_key = p.category_key
WHERE p.corpus_version = :'corpus_version' AND c.category_key IS NULL;

-- 6. Confidence floor honored — every row present and >= 0.90 (must be 0).
SELECT '06_confidence_floor' AS check,
       count(*) AS offending, 0 AS expected,
       (count(*) = 0) AS pass
FROM places
WHERE corpus_version = :'corpus_version'
  AND (confidence IS NULL OR confidence < 0.90);

-- 7. Geography SRID is 4326 on both geom and centroid (must be 0 offending).
SELECT '07_srid_4326' AS check,
       count(*) AS offending, 0 AS expected,
       (count(*) = 0) AS pass
FROM places
WHERE corpus_version = :'corpus_version'
  AND (ST_SRID(geom::geometry) <> 4326 OR ST_SRID(centroid::geometry) <> 4326);

-- 8. Florida attribution: places spatially covered by a tiger-2024 FL county.
--    (Bbox-filtered extract may include coastal/water points outside a county
--    polygon; this is the Gate-2-eligible count, reported — not hard-failed.)
SELECT '08_places_in_fl_counties' AS check,
       count(*) AS in_fl_counties
FROM places p
WHERE p.corpus_version = :'corpus_version'
  AND EXISTS (
    SELECT 1 FROM boundaries b
    WHERE b.kind = 'county' AND b.corpus_version = 'tiger-2024'
      AND ST_Covers(b.geom, p.geom)
  );

-- 9. The corpus_version partition is attached to the `places` parent (must be 1).
SELECT '09_partition_attached' AS check,
       count(*) AS actual, 1 AS expected,
       (count(*) = 1) AS pass
FROM pg_inherits i
JOIN pg_class child  ON child.oid  = i.inhrelid
JOIN pg_class parent ON parent.oid = i.inhparent
WHERE parent.relname = 'places'
  AND child.relname  = 'places_p_' || trim(both '_' from regexp_replace(lower(:'corpus_version'), '[^a-z0-9]+', '_', 'g'));

-- 10. Exactly one ACTIVE ledger row for this import (SSOT E-32).
SELECT '10_ledger_active_row' AS check,
       count(*) AS actual, 1 AS expected,
       (count(*) = 1) AS pass
FROM corpus_imports
WHERE dataset = 'overture-places'
  AND corpus_version = :'corpus_version'
  AND status = 'active';
