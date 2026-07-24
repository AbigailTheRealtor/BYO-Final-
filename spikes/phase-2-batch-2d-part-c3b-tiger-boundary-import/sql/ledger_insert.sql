-- ============================================================================
-- ledger_insert.sql  —  AUTHORED, NOT RUN
-- Spatial Intelligence Platform · Phase 2 Batch 2D Part C3d-c (G4)
-- Provenance ledger row for the Florida TIGER county load (corpus_imports)
-- ============================================================================
--
-- This is the CLASS-2 recipe. It requires the pgsql_spatial PostGIS cluster
-- (SPATIAL_DATABASE_URL / SPATIAL_PGHOST) and the `corpus_imports` table
-- (B1.2 migration 11). It is NEVER executed offline / in CI.
--
-- Every import writes EXACTLY ONE row (SSOT §7.2, E-32). Mirrors
-- App\Services\Spatial\CorpusImportLedger — column list and order match
-- CorpusImportLedger::COLUMNS exactly:
--   (dataset, corpus_version, row_count, bytes, territory_coverage,
--    started_at, finished_at, status, notes)
-- id is bigserial → omitted. Inserted as 'staging'; ledger_activate.sql flips it
-- to 'active' after the load + verify succeed. `bytes` uses the accepted planning
-- proxy (row_count × 450, CorpusSizingProjector) unless a measured size is known.
--
-- The WHERE NOT EXISTS guard makes the insert idempotent on
-- (dataset, corpus_version): re-running never creates a second ledger row for
-- the same import.
--
-- Run (later, Class-2):
--   psql "$SPATIAL_DATABASE_URL" -v ON_ERROR_STOP=1 \
--     -v corpus_version=tiger-2024 -v row_count=67 -f ledger_insert.sql
-- ----------------------------------------------------------------------------

INSERT INTO corpus_imports
  (dataset, corpus_version, row_count, bytes, territory_coverage, started_at, finished_at, status, notes)
SELECT
  'census-tiger-county-fl',
  :'corpus_version',                 -- MUST equal the load's -v corpus_version
  :row_count,                        -- reconcile with the 67 Florida counties
  :row_count * 450,                  -- planning proxy; replace with measured bytes if known
  '{"region":"florida","state_fips":"12","crs":"EPSG:4326"}'::jsonb,
  now(),
  NULL,
  'staging',
  jsonb_build_object(
    'source',  'census_tiger',
    'layer',   'county',
    -- vintage is documented against corpus_version (e.g. corpus_version=tiger-2024 → vintage 2024).
    'vintage', :'corpus_version'
  )
WHERE NOT EXISTS (
  SELECT 1 FROM corpus_imports
  WHERE dataset = 'census-tiger-county-fl'
    AND corpus_version = :'corpus_version'
);
