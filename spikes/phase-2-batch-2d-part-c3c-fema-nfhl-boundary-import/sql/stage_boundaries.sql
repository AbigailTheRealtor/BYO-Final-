-- ============================================================================
-- stage_boundaries.sql  —  AUTHORED, NOT RUN
-- Spatial Intelligence Platform · Phase 2 Batch 2D Part C3c
-- Boundary staging (boundaries_staging) — FEMA NFHL
-- ============================================================================
--
-- This is the CLASS-2 recipe. It requires the pgsql_spatial PostGIS cluster
-- (SPATIAL_DATABASE_URL / SPATIAL_PGHOST) with PostGIS installed. It is NEVER
-- executed offline; the offline importer (App\Console\Commands\
-- CorpusImportBoundaries + App\Services\Spatial\Boundary\FemaNfhlBoundarySource)
-- is the deterministic dry-run counterpart that emits the boundaries.ndjson this
-- recipe stages.
--
--   • boundaries_staging is a transient STAGING table (identical to C3a/C3b — the
--     staging shape is source-neutral). After staging it is loaded into
--     `boundaries` (migration 06) by load_fema_boundaries.sql, then removed.
--   • The COPY column list mirrors
--     App\Services\Spatial\BoundaryRowMaterializer::COLUMNS exactly (the
--     SqlManifest drift test asserts this). geom arrives as SRID=4326 MultiPolygon
--     EWKT and parses directly into geography on COPY.
--   • BOTH FEMA kinds (flood_zone, flood_coverage) stage through this one table;
--     they are separated by `kind` at load time, not by separate staging tables.
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS boundaries_staging (
  kind           text NOT NULL,
  external_ref   text,
  attrs          jsonb,
  geom           geography(MultiPolygon,4326) NOT NULL,
  corpus_version text NOT NULL
);

-- Stage the offline FEMA boundary rows. Column list == BoundaryRowMaterializer::COLUMNS.
\copy boundaries_staging (kind, external_ref, attrs, geom, corpus_version) FROM 'boundaries_payload.txt' WITH (FORMAT text, NULL '\N');

ANALYZE boundaries_staging;
