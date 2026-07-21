-- ============================================================================
-- stage_authority_overlay.sql  —  AUTHORED, NOT RUN
-- Spatial Intelligence Platform · Phase 2 Batch 2D Part C2
-- Authority-overlay staging (authority_staging)
-- ============================================================================
--
-- This is the CLASS-2 recipe. It requires the pgsql_spatial PostGIS cluster
-- (SPATIAL_DATABASE_URL / SPATIAL_PGHOST) with PostGIS installed. It is NEVER
-- executed offline; the offline importer (App\Console\Commands\
-- CorpusImportAuthorityOverlay + App\Services\Spatial\AuthorityOverlaySource) is
-- the deterministic dry-run counterpart that emits the overlay.ndjson this recipe
-- stages.
--
--   • authority_staging is a STAGING table authored HERE — the SSOT defines no
--     such table. It is transient: after staging, an OVERLAY source (target=link,
--     e.g. cms) is resolved by the Batch 2D Part C1 recipe (link_authority.sql),
--     and a BASE source (target=place, e.g. usgs) is loaded by
--     load_usgs_boat_ramps.sql. Remove it after the flip.
--   • The COPY column list mirrors
--     App\Services\Spatial\AuthorityStagingMaterializer::COLUMNS exactly (the
--     SqlManifest drift test asserts this).
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS authority_staging (
  authority_source text NOT NULL,
  authority_ref    text NOT NULL,
  name             text,
  lon              double precision NOT NULL,
  lat              double precision NOT NULL,
  authority_metric numeric,
  geom             geography(Point,4326)
    GENERATED ALWAYS AS (ST_SetSRID(ST_MakePoint(lon, lat), 4326)::geography) STORED,
  PRIMARY KEY (authority_source, authority_ref)
);

-- Stage the offline overlay rows. Column list == AuthorityStagingMaterializer::COLUMNS.
\copy authority_staging (authority_source, authority_ref, name, lon, lat, authority_metric) FROM 'overlay_payload.txt' WITH (FORMAT text, NULL '\N');

ANALYZE authority_staging;
