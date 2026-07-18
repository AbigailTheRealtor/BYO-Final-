-- ─────────────────────────────────────────────────────────────────────────────
-- Spatial Intelligence Platform — Phase 2 Batch 2C (Overture import framework)
-- Step 2/5 · COPY the materialized corpus into the staging table
-- ─────────────────────────────────────────────────────────────────────────────
--
-- AUTHORED, NOT RUN. No PostGIS cluster, no SPATIAL_* secret in Batch 2C. The
-- COPY payload is produced OFFLINE by App\Services\Spatial\CorpusCopyLoader
-- (PostgreSQL COPY text format: tab-delimited, \N for NULL). The geom/centroid
-- columns arrive as `SRID=4326;POINT(lon lat)` EWKT — the geography input
-- function parses that directly on COPY, so no ST_GeogFromText wrapper is needed.
--
-- Column order MUST equal PlaceRowMaterializer::COLUMNS. place_id (bigserial) is
-- intentionally absent — PostgreSQL assigns it.
--
-- Run (later, Class-2): `\copy` streams a client-side file without server FS access.
--   psql "$SPATIAL_DATABASE_URL" -f load_copy.sql
-- ─────────────────────────────────────────────────────────────────────────────

\set partition 'places_p_overture_2026_06_17_0_pinellas'
\set payload   'copy_payload.txt'

\copy :"partition" (corpus_version, source, source_ref, gers_id, geom, centroid, category_key, name, brand, confidence, source_count, authority_metric, attrs, first_seen, last_seen) FROM :'payload' WITH (FORMAT text)

-- Sanity (optional): the staging row count must equal the payload line count and
-- the ledger row_count before you attach (step 4) or activate (step 5).
--   SELECT count(*) FROM :"partition";
