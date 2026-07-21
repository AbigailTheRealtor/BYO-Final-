-- ============================================================================
-- load_usgs_boat_ramps.sql  —  AUTHORED, NOT RUN
-- Spatial Intelligence Platform · Phase 2 Batch 2D Part C2
-- BASE-source load: USGS Boat Ramps (target='place') -> places
-- ============================================================================
--
-- This is the CLASS-2 recipe. It requires the pgsql_spatial PostGIS cluster
-- (SPATIAL_DATABASE_URL / SPATIAL_PGHOST), PostGIS, the `places` table (B1.2
-- migration 04) loaded/partitioned for the target corpus_version, and the USGS
-- rows already staged by stage_authority_overlay.sql. It is NEVER executed
-- offline.
--
-- Boat ramps have no Overture counterpart, so USGS rows become `places` rows
-- DIRECTLY (source='usgs', category_key='boat_ramp'); ranking is by membership,
-- so authority_metric is carried through as-is (NULL for this source). This is a
-- single append (one INSERT statement below), no mutation of any existing row.
--
-- Run (later, Class-2): psql "$SPATIAL_DATABASE_URL" \
--   -v corpus_version=overture-<release>-<region> -f load_usgs_boat_ramps.sql
-- ----------------------------------------------------------------------------

WITH staged AS (
    SELECT authority_ref, name, lon, lat, authority_metric
    FROM authority_staging
    WHERE authority_source = 'usgs'
)
INSERT INTO places
    (corpus_version, source, source_ref, gers_id, geom, centroid, category_key,
     name, brand, confidence, source_count, authority_metric, attrs, first_seen, last_seen)
SELECT
    :'corpus_version',
    'usgs',
    s.authority_ref,
    NULL,
    ST_SetSRID(ST_MakePoint(s.lon, s.lat), 4326)::geography,
    ST_SetSRID(ST_MakePoint(s.lon, s.lat), 4326)::geography,
    'boat_ramp',
    s.name,
    NULL,
    NULL,
    1,
    s.authority_metric,
    jsonb_build_object('geometry_type', 'Point'),
    now(),
    now()
FROM staged s;
