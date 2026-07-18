-- ─────────────────────────────────────────────────────────────────────────────
-- Spatial Intelligence Platform — Phase 2 Batch 2C (Overture import framework)
-- Step 4/5 · ATTACH the staged partition and ACTIVATE the version (transactional)
-- ─────────────────────────────────────────────────────────────────────────────
--
-- AUTHORED, NOT RUN. No PostGIS cluster / no SPATIAL_* in Batch 2C. Mirrors
-- App\Services\Spatial\CorpusActivationService. All-or-nothing: attach + ledger
-- flip happen in one transaction. The CHECK constraint makes ATTACH an O(1)
-- metadata operation (no full-table validation scan). The PREVIOUS version's
-- partition is LEFT ATTACHED for instant rollback — physical removal is the
-- separate, explicit retirement below.
--
-- Run (later, Class-2):
--   psql "$SPATIAL_DATABASE_URL" -f attach_activate.sql
-- ─────────────────────────────────────────────────────────────────────────────

\set corpus_version 'overture-2026-06-17.0-pinellas'
\set partition      'places_p_overture_2026_06_17_0_pinellas'
-- \set previous_version 'overture-2026-05-21.0-pinellas'   -- uncomment to supersede

BEGIN;

  ALTER TABLE :"partition"
    ADD CONSTRAINT :"partition"_ck CHECK (corpus_version = :'corpus_version');

  ALTER TABLE places ATTACH PARTITION :"partition"
    FOR VALUES IN (:'corpus_version');

  UPDATE corpus_imports SET status = 'active', finished_at = now()
    WHERE corpus_version = :'corpus_version' AND status = 'staging';

  -- Retire the prior active version (only if :previous_version is set):
  -- UPDATE corpus_imports SET status = 'superseded'
  --   WHERE corpus_version = :'previous_version' AND status = 'active';

COMMIT;

-- ── RETIREMENT (destructive, separate run — only after the new version is proven)
-- ALTER TABLE places DETACH PARTITION :"old_partition";
-- DROP TABLE IF EXISTS :"old_partition";
