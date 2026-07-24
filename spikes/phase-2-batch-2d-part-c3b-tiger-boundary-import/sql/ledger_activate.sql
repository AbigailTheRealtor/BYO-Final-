-- ============================================================================
-- ledger_activate.sql  —  AUTHORED, NOT RUN
-- Spatial Intelligence Platform · Phase 2 Batch 2D Part C3d-c (G4)
-- Flip the Florida TIGER county ledger row from 'staging' to 'active'
-- ============================================================================
--
-- This is the CLASS-2 recipe. It requires the pgsql_spatial PostGIS cluster
-- (SPATIAL_DATABASE_URL / SPATIAL_PGHOST) and the `corpus_imports` row inserted
-- by ledger_insert.sql. It is NEVER executed offline / in CI.
--
-- Mirrors App\Services\Spatial\CorpusImportLedger::activateSql (status flip +
-- server now() for finished_at). Tightly scoped: it targets ONLY this import's
-- staging row and never touches an unrelated corpus_imports row (a different
-- dataset — e.g. the Gate 2 coverage row — or a different corpus_version, or a
-- row already in another status).
--
-- Run (later, Class-2, after load + verify pass):
--   psql "$SPATIAL_DATABASE_URL" -v ON_ERROR_STOP=1 \
--     -v corpus_version=tiger-2024 -f ledger_activate.sql
-- ----------------------------------------------------------------------------

UPDATE corpus_imports
SET status = 'active',
    finished_at = now()
WHERE dataset = 'census-tiger-county-fl'
  AND corpus_version = :'corpus_version'
  AND status = 'staging';
