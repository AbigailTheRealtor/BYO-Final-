-- ─────────────────────────────────────────────────────────────────────────────
-- Spatial Intelligence Platform — Phase 2 Batch 2C (Overture import framework)
-- Step 1/5 · CREATE the corpus_version partition of `places`
-- ─────────────────────────────────────────────────────────────────────────────
--
-- AUTHORED, NOT RUN. No PostGIS cluster is provisioned in Batch 2C and no
-- SPATIAL_* secret is configured — this recipe connects to nothing. It is the
-- Class-2 live-run template; the runnable authoring lives in
-- App\Services\Spatial\CorpusPartitionManager (the SSOT this file mirrors).
--
-- `places` is PARTITION BY LIST (corpus_version) (B1.2 migration 04). Partitions
-- are created at IMPORT time — here. Two flows; the STAGING flow is preferred
-- (load off the parent, then attach as an O(1) metadata flip — see step 4).
--
-- Run (later, Class-2, against the spatial cluster):
--   psql "$SPATIAL_DATABASE_URL" -f create_partition.sql
-- ─────────────────────────────────────────────────────────────────────────────

\set corpus_version 'overture-2026-06-17.0-pinellas'
\set partition      'places_p_overture_2026_06_17_0_pinellas'

-- Preferred: DETACHED staging table, structurally identical to places.
CREATE TABLE IF NOT EXISTS :"partition"
  (LIKE places INCLUDING DEFAULTS INCLUDING CONSTRAINTS INCLUDING INDEXES);

-- Alternative (single-shot): a directly attached partition. Rows become visible
-- in `places` as they land — use only when the version is not yet queried.
--   CREATE TABLE IF NOT EXISTS :"partition"
--     PARTITION OF places FOR VALUES IN (:'corpus_version');
