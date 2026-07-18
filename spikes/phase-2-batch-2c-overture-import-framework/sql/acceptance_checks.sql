-- ─────────────────────────────────────────────────────────────────────────────
-- Spatial Intelligence Platform — Phase 2 Batch 2C (Overture import framework)
-- Step 3/5 · POST-LOAD acceptance gate (READ-ONLY — no writes, ever)
-- ─────────────────────────────────────────────────────────────────────────────
--
-- AUTHORED, NOT RUN. No PostGIS cluster / no SPATIAL_* in Batch 2C. These are the
-- live-cluster mirror of App\Services\Spatial\CorpusImportAcceptance: run them
-- against the staged partition BEFORE attach/activation. Every query is a SELECT
-- — this recipe NEVER writes, drops, or copies. A non-zero result on any "must be
-- zero" query means DO NOT ACTIVATE.
--
-- Run (later, Class-2):
--   psql "$SPATIAL_DATABASE_URL" -f acceptance_checks.sql
-- ─────────────────────────────────────────────────────────────────────────────

\set partition 'places_p_overture_2026_06_17_0_pinellas'

-- non_empty: staged at least one row.
SELECT count(*) AS staged_rows FROM :"partition";

-- source_uniform: every row is tagged 'overture' (must be 0).
SELECT count(*) AS bad_source FROM :"partition" WHERE source <> 'overture';

-- identity_present: source_ref present (must be 0).
SELECT count(*) AS missing_source_ref FROM :"partition"
  WHERE source_ref IS NULL OR btrim(source_ref) = '';

-- category_registered: every category_key is a registered canonical (must be 0).
SELECT count(*) AS unregistered_category FROM :"partition" s
  LEFT JOIN place_categories c ON c.category_key = s.category_key
  WHERE c.category_key IS NULL;

-- confidence_floor: every confidence present and >= 0.90 (must be 0).
SELECT count(*) AS below_floor FROM :"partition"
  WHERE confidence IS NULL OR confidence < 0.90;

-- coordinates_valid: geography within lon/lat range (must be 0).
SELECT count(*) AS bad_coords FROM :"partition"
  WHERE ST_X(centroid::geometry) NOT BETWEEN -180 AND 180
     OR ST_Y(centroid::geometry) NOT BETWEEN  -90 AND  90;

-- per-category distribution (informational — reconcile against the extract).
SELECT category_key, count(*) AS n FROM :"partition" GROUP BY category_key ORDER BY category_key;
