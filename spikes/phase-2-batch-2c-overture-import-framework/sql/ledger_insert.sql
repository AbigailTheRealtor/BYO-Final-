-- ─────────────────────────────────────────────────────────────────────────────
-- Spatial Intelligence Platform — Phase 2 Batch 2C (Overture import framework)
-- Step 5/5 · WRITE the provenance ledger row (corpus_imports)
-- ─────────────────────────────────────────────────────────────────────────────
--
-- AUTHORED, NOT RUN. No PostGIS cluster / no SPATIAL_* in Batch 2C. Mirrors
-- App\Services\Spatial\CorpusImportLedger. Every import writes EXACTLY ONE row
-- (SSOT §7.2, E-32). Insert it as 'staging' at load time; step 4 flips it to
-- 'active'. `bytes` uses the accepted planning proxy (row_count × 450) unless a
-- measured size is known. id is bigserial → omitted.
--
-- Run (later, Class-2):
--   psql "$SPATIAL_DATABASE_URL" -f ledger_insert.sql
-- ─────────────────────────────────────────────────────────────────────────────

INSERT INTO corpus_imports
  (dataset, corpus_version, row_count, bytes, territory_coverage, started_at, finished_at, status, notes)
VALUES (
  'overture-places',
  'overture-2026-06-17.0-pinellas',
  :row_count,                        -- reconcile with the staged partition count
  :row_count * 450,                  -- planning proxy; replace with measured bytes if known
  '{"region":"pinellas","crs":"EPSG:4326"}'::jsonb,
  now(),
  NULL,
  'staging',
  '{"source":"overture","confidence_min":0.90}'::jsonb
);
