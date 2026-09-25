SET default_transaction_read_only = on;
-- ─────────────────────────────────────────────────────────────────────────────
-- preflight.sql  —  Overture v2 operator load · READ-ONLY target preflight
-- Spatial Intelligence Platform · Phase 4 (see ../RUNBOOK.md)
-- ─────────────────────────────────────────────────────────────────────────────
--
-- Proves, before anything is written, that the target is the Crunchy Bridge spatial cluster that
-- holds v1 (by fingerprint, not by name), that v1 is healthy, and what state v2 is in. Every
-- statement is a SELECT or a SET. It is run by ../bin/preflight.sh, never by hand without it:
-- the script supplies the connection-side facts (host, port) that the fingerprint is taken over,
-- and refuses a URL with no host (which libpq would otherwise complete from an ambient PGHOST).
--
-- Required psql variables (the script sets all of them):
--   v2_state              absent | empty | loaded | recoverable-failed-import
--                         (the last is ONLY for a separately approved retry after a failed
--                         import — RUNBOOK §8a; it never widens absent, empty or loaded)
--   target_host           lowercase host from SPATIAL_DATABASE_URL
--   target_port           port from SPATIAL_DATABASE_URL (default 5432)
--   expected_fingerprint  sha256 hex of  host|port|current_database()|v1 ledger started_at (UTC)
--
-- A failed check prints `FAIL <id>` and stops psql with an error (ON_ERROR_STOP), so the caller
-- exits non-zero. A passing run prints one `PASS <id>` line per check and nothing secret.
-- ─────────────────────────────────────────────────────────────────────────────

\set ON_ERROR_STOP on
SET TimeZone = 'UTC';

\if :{?v2_state}
\else
\echo 'FAIL P-- v2_state is not set (run through bin/preflight.sh)'
SELECT 1 / 0;
\endif
-- psql's \if takes a boolean, not a comparison, so the state becomes booleans first.
SELECT :'v2_state' IN ('absent', 'empty', 'loaded', 'recoverable-failed-import') AS state_known,
       :'v2_state' = 'absent' AS v2_absent,
       :'v2_state' = 'empty' AS v2_empty,
       :'v2_state' = 'recoverable-failed-import' AS v2_recovery \gset
\if :state_known
\else
\echo 'FAIL P-- v2_state must be absent, empty, loaded or recoverable-failed-import'
SELECT 1 / 0;
\endif

-- P00 — the session really is read-only (every later statement inherits it).
SELECT current_setting('default_transaction_read_only') = 'on' AS ok \gset
\if :ok
\echo 'PASS P00 session is read-only'
\else
\echo 'FAIL P00 session is not read-only'
SELECT 1 / 0;
\endif

-- P01 — target identity. The fingerprint binds host, port, database and the v1 ledger row's own
-- creation time, so a different cluster, a restored copy or a renamed database all fail here.
SELECT COALESCE(encode(sha256(convert_to(
         :'target_host' || '|' || :'target_port' || '|' || current_database() || '|' ||
         (SELECT to_char(started_at AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"')
            FROM corpus_imports
           WHERE dataset = 'overture-places' AND corpus_version = 'overture-2026-06-17.0-fl'),
         'UTF8')), 'hex') = :'expected_fingerprint', false) AS ok \gset
\if :ok
\echo 'PASS P01 target fingerprint matches the recorded Crunchy spatial cluster'
\else
\echo 'FAIL P01 target fingerprint does NOT match — wrong cluster, restored copy or changed v1 ledger'
SELECT 1 / 0;
\endif

-- P02 — never the application database.
SELECT current_database() NOT ILIKE '%heliumdb%' AND :'target_host' NOT ILIKE 'helium%' AS ok \gset
\if :ok
\echo 'PASS P02 target is not the application database'
\else
\echo 'FAIL P02 target resolves to the application database'
SELECT 1 / 0;
\endif

-- P03 / P04 — versions, reported and pinned. PostGIS must be 3.6.x: the full-size rehearsal is
-- only evidence for the PostGIS line it ran on.
SELECT current_setting('server_version') AS server_version,
       (SELECT extversion FROM pg_extension WHERE extname = 'postgis') AS postgis_version;
SELECT current_setting('server_version_num')::int / 10000 = 16 AS ok \gset
\if :ok
\echo 'PASS P03 PostgreSQL major version is 16'
\else
\echo 'FAIL P03 PostgreSQL major version is not 16'
SELECT 1 / 0;
\endif
SELECT COALESCE((SELECT extversion LIKE '3.6.%' FROM pg_extension WHERE extname = 'postgis'), false) AS ok \gset
\if :ok
\echo 'PASS P04 PostGIS is 3.6.x'
\else
\echo 'FAIL P04 PostGIS is missing or not 3.6.x — the rehearsal evidence does not cover it'
SELECT 1 / 0;
\endif

-- P05 — v1 ledger: exactly one active overture-places row, and it is v1 with 29,434 rows.
SELECT (SELECT count(*) FROM corpus_imports
         WHERE dataset = 'overture-places' AND corpus_version = 'overture-2026-06-17.0-fl'
           AND status = 'active' AND row_count = 29434) = 1
   AND (SELECT count(*) FROM corpus_imports
         WHERE dataset = 'overture-places' AND status = 'active') = 1 AS ok \gset
\if :ok
\echo 'PASS P05 v1 overture-2026-06-17.0-fl is the one active overture-places corpus (29,434)'
\else
\echo 'FAIL P05 v1 ledger is not exactly one active overture-2026-06-17.0-fl row of 29,434'
SELECT 1 / 0;
\endif

-- P06 — v1 rows actually present.
SELECT (SELECT count(*) FROM places WHERE corpus_version = 'overture-2026-06-17.0-fl') = 29434 AS ok \gset
\if :ok
\echo 'PASS P06 v1 places hold 29,434 rows'
\else
\echo 'FAIL P06 v1 places do not hold 29,434 rows'
SELECT 1 / 0;
\endif

-- P07 — v2 table state.
SELECT count(*) AS v2_relations
  FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
 WHERE n.nspname = 'public' AND c.relname LIKE 'overture\_v2\_%' AND c.relkind IN ('r', 'p') \gset
\if :v2_absent
SELECT :v2_relations = 0 AS ok \gset
\if :ok
\echo 'PASS P07 no overture_v2_* table exists'
\else
\echo 'FAIL P07 overture_v2_* tables already exist (expected absent)'
SELECT 1 / 0;
\endif
\else
SELECT :v2_relations = 3
   AND to_regclass('public.overture_v2_corpora') IS NOT NULL
   AND to_regclass('public.overture_v2_places') IS NOT NULL
   AND to_regclass('public.overture_v2_chain_memberships') IS NOT NULL AS ok \gset
\if :ok
\echo 'PASS P07 exactly the three overture_v2_* tables exist'
\else
\echo 'FAIL P07 the three overture_v2_* tables are not all present (or others exist)'
SELECT 1 / 0;
\endif
\if :v2_empty
SELECT count(*) = 0 AS ok FROM overture_v2_corpora \gset
\if :ok
SELECT (SELECT count(*) FROM overture_v2_places) = 0
   AND (SELECT count(*) FROM overture_v2_chain_memberships) = 0 AS ok \gset
\endif
\if :ok
\echo 'PASS P07 the v2 tables are empty'
\else
\echo 'FAIL P07 the v2 tables are not empty (expected empty)'
SELECT 1 / 0;
\endif
\endif
\if :v2_recovery
-- P07R — RECOVERY ONLY (RUNBOOK §8a). The exact state a failed import leaves, and nothing else:
-- OvertureV2CorpusImporter marks the row `preparing`, writes every place and membership in ONE
-- transaction, and on failure marks the row `failed` (or leaves it `preparing` if that update
-- itself could not run). The transaction rolled back, so no place or membership is committed. A
-- retry re-arms any non-ready row and proves zero places inside its own transaction; a `ready`
-- row is never overwritten. Every condition below must hold, or the retry is not eligible.

-- P07R1 — exactly one corpus row: this corpus, failed or preparing; no ready row anywhere.
SELECT count(*) = 1
   AND count(*) FILTER (WHERE corpus_version = 'overture-2026-08-19.0-fl-r2' AND status IN ('failed', 'preparing')) = 1
   AND count(*) FILTER (WHERE status = 'ready') = 0 AS ok
  FROM overture_v2_corpora \gset
\if :ok
\echo 'PASS P07R1 exactly one v2 corpus row: overture-2026-08-19.0-fl-r2, failed or preparing; none ready'
\else
\echo 'FAIL P07R1 the v2 corpus rows are not exactly one failed/preparing overture-2026-08-19.0-fl-r2 row'
SELECT 1 / 0;
\endif

-- P07R2 — it is THIS contract's attempt: every pin, checksum and expected count equals the
-- contract (the values verify_v2_load.sql V01 requires of a ready row), no imported count is
-- recorded, and the status is internally consistent.
SELECT count(*) = 1 AS ok FROM overture_v2_corpora
 WHERE corpus_version = 'overture-2026-08-19.0-fl-r2'
   AND source = 'overture' AND source_release = '2026-08-19.0'
   AND extract_recipe_version = 'overture-extract-v2' AND taxonomy_map_version = 'overture-taxonomy-v2.0'
   AND registry_version = 'chain-registry-v2'
   AND registry_rule_hash = 'b5920a1c73199a0d8e030e5281018baa763438eb7ad02aee76f7ba3cbc0b151f'
   AND base_sha256 = 'bb9e77c13790897155f847fa3a7ed48067930cf08257e227bee60bf96c91a168'
   AND supplementary_sha256 = 'edeed1435d707912dedd08d642cf1088e669cf4dc329ea3248a289be3e91c8e4'
   AND base_rows = 52566 AND supplementary_rows = 2962 AND matcher_analysis_rows = 55528
   AND diagnostic_rows = 2734 AND rescue_admitted_rows = 150 AND rescue_refused_rows = 78
   AND expected_memberships = 11082
   AND imported_base_rows IS NULL AND imported_rescued_rows IS NULL AND imported_memberships IS NULL
   AND ((status = 'failed' AND failure_reason IS NOT NULL AND finished_at IS NOT NULL)
     OR (status = 'preparing' AND failure_reason IS NULL AND finished_at IS NULL)) \gset
\if :ok
\echo 'PASS P07R2 the row carries this contract''s pins, checksums and counts; no imported count; status consistent'
\else
\echo 'FAIL P07R2 the row does not match the contract, records an imported count, or has an inconsistent status'
SELECT 1 / 0;
\endif

-- P07R3 — nothing committed: zero places and zero memberships, in ANY corpus. Committed data is
-- not a failed import; it is outside this recovery path and needs manual review.
SELECT (SELECT count(*) FROM overture_v2_places) = 0
   AND (SELECT count(*) FROM overture_v2_chain_memberships) = 0 AS ok \gset
\if :ok
\echo 'PASS P07R3 zero committed v2 places and memberships'
\else
\echo 'FAIL P07R3 committed v2 places or memberships exist — not a recoverable failed import; manual review'
SELECT 1 / 0;
\endif

-- P07R4 — no importer is still running: no other session holds any lock on an overture_v2_*
-- relation in this database. (A running import is also `preparing` with zero VISIBLE rows; its
-- uncommitted rows are invisible here, which is why this check exists.) IS DISTINCT FROM, not <>,
-- so a prepared transaction's lock (NULL pid) is counted rather than silently skipped.
SELECT count(*) = 0 AS ok
  FROM pg_locks l JOIN pg_class c ON c.oid = l.relation JOIN pg_namespace n ON n.oid = c.relnamespace
 WHERE n.nspname = 'public' AND c.relname LIKE 'overture\_v2\_%'
   AND l.database = (SELECT oid FROM pg_database WHERE datname = current_database())
   AND l.pid IS DISTINCT FROM pg_backend_pid() \gset
\if :ok
\echo 'PASS P07R4 no other session holds a lock on any overture_v2_* relation'
\else
\echo 'FAIL P07R4 another session holds a lock on an overture_v2_* relation — an import may still be running'
SELECT 1 / 0;
\endif
\endif
\endif

-- P08 — the spatial migration ledger holds EXACTLY the expected set. The two August address
-- migrations must never be applied by this procedure; the three v2 migrations are applied only
-- by the migrate stage. Any other row means the cluster moved and this runbook is stale.
-- `migrations.migration` is varchar (Laravel's repository); compare as text[].
SELECT (SELECT array_agg(migration::text ORDER BY migration::text) FROM migrations)
     = (SELECT array_agg(m ORDER BY m) FROM unnest(
         ARRAY[
           '2026_07_16_000001_spatial_core_enable_extensions',
           '2026_07_16_000002_spatial_core_create_place_categories',
           '2026_07_16_000003_spatial_core_create_place_category_mappings',
           '2026_07_16_000004_spatial_core_create_places',
           '2026_07_16_000005_spatial_core_create_place_authority_links',
           '2026_07_16_000006_spatial_core_create_boundaries',
           '2026_07_16_000007_spatial_core_create_boundaries_parts',
           '2026_07_16_000008_spatial_core_create_listing_locations',
           '2026_07_16_000009_spatial_core_create_addresses',
           '2026_07_16_000010_spatial_core_create_isochrone_cache',
           '2026_07_16_000011_spatial_core_create_corpus_imports'
         ] || CASE WHEN :'v2_state' = 'absent' THEN ARRAY[]::text[] ELSE ARRAY[
           '2026_09_24_000001_spatial_overture_v2_create_corpora',
           '2026_09_24_000002_spatial_overture_v2_create_places',
           '2026_09_24_000003_spatial_overture_v2_create_chain_memberships'
         ] END) AS m) AS ok \gset
\if :ok
\echo 'PASS P08 migration ledger is exactly the expected set (August address migrations still pending)'
\else
\echo 'FAIL P08 migration ledger differs from the expected set'
SELECT 1 / 0;
\endif

\echo 'PREFLIGHT OK'
