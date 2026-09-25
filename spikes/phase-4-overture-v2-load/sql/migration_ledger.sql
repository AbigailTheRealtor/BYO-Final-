SET default_transaction_read_only = on;
-- ─────────────────────────────────────────────────────────────────────────────
-- migration_ledger.sql  —  Overture v2 operator load · READ-ONLY migration ledger + batch check
-- Spatial Intelligence Platform · Phase 4 (see ../RUNBOOK.md §7)
-- ─────────────────────────────────────────────────────────────────────────────
--
-- The preflight's P08 proves the ledger holds the right migration NAMES. This proves the BATCH
-- contract of the migrate stage, which P08 does not see: taken immediately before and after the
-- write, it shows the three v2 migrations went in as ONE new batch, numbered previous maximum + 1,
-- and that nothing else moved.
--
--   ledger_phase=before   the ledger is exactly the eleven core migrations; prints every row and
--                         PREVIOUS_MAX_BATCH=<n>.
--   ledger_phase=after    requires previous_max_batch=<n> (from the before run); the ledger is the
--                         eleven plus exactly the three v2 migrations, all three in batch n + 1,
--                         no other row in a batch above n, neither August migration present.
--
-- Every row is printed as `LEDGER_ROW <migration> batch=<n>`, ordered by name, so the caller can
-- diff the before and after listings (the eleven core rows must be byte-identical). Every statement
-- is a SELECT or a SET. A failed check prints `FAIL <id>` and stops psql with an error.
--
--   bin/spatial_psql.sh -X -q -v ON_ERROR_STOP=1 -v ledger_phase=before -f sql/migration_ledger.sql
-- ─────────────────────────────────────────────────────────────────────────────

\set ON_ERROR_STOP on
\pset format unaligned
\pset tuples_only on

\if :{?ledger_phase}
\else
\echo 'FAIL L-- ledger_phase is not set (before | after)'
SELECT 1 / 0;
\endif
SELECT :'ledger_phase' IN ('before', 'after') AS phase_known,
       :'ledger_phase' = 'before' AS phase_before \gset
\if :phase_known
\else
\echo 'FAIL L-- ledger_phase must be before or after'
SELECT 1 / 0;
\endif

SELECT 'LEDGER_ROW ' || migration::text || ' batch=' || batch FROM migrations ORDER BY migration::text;

-- L01 — never an August address migration, in either phase.
SELECT count(*) = 0 AS ok FROM migrations
 WHERE migration::text IN ('2026_08_11_000001_spatial_core_version_address_corpus',
                           '2026_08_12_000001_spatial_core_index_address_lookup') \gset
\if :ok
\echo 'PASS L01 neither August address migration is applied'
\else
\echo 'FAIL L01 an August address migration is applied'
SELECT 1 / 0;
\endif

\if :phase_before
-- L02 — before the write: exactly the eleven core migrations, and no v2 row.
SELECT count(*) = 11
   AND count(*) FILTER (WHERE migration::text LIKE '2026\_07\_16\_0000%\_spatial\_core\_%') = 11 AS ok
  FROM migrations \gset
\if :ok
\echo 'PASS L02 ledger is exactly the eleven core migrations'
\else
\echo 'FAIL L02 ledger is not exactly the eleven core migrations'
SELECT 1 / 0;
\endif
SELECT 'PREVIOUS_MAX_BATCH=' || max(batch) FROM migrations;
\else
\if :{?previous_max_batch}
\else
\echo 'FAIL L-- previous_max_batch is not set (take it from the before run)'
SELECT 1 / 0;
\endif
SELECT :'previous_max_batch' ~ '^[0-9]+$' AS ok \gset
\if :ok
\else
\echo 'FAIL L-- previous_max_batch is not a non-negative integer'
SELECT 1 / 0;
\endif

-- L03 — after the write: fourteen rows, the eleven core plus exactly the three v2 migrations.
SELECT count(*) = 14
   AND count(*) FILTER (WHERE migration::text LIKE '2026\_07\_16\_0000%\_spatial\_core\_%') = 11
   AND count(*) FILTER (WHERE migration::text IN (
         '2026_09_24_000001_spatial_overture_v2_create_corpora',
         '2026_09_24_000002_spatial_overture_v2_create_places',
         '2026_09_24_000003_spatial_overture_v2_create_chain_memberships')) = 3 AS ok
  FROM migrations \gset
\if :ok
\echo 'PASS L03 ledger is the eleven core migrations plus exactly the three v2 migrations'
\else
\echo 'FAIL L03 ledger is not the eleven core plus exactly the three v2 migrations'
SELECT 1 / 0;
\endif

-- L04 — the three v2 migrations share ONE batch, and it is previous maximum + 1.
SELECT count(*) = 3 AS ok FROM migrations
 WHERE migration::text IN ('2026_09_24_000001_spatial_overture_v2_create_corpora',
                           '2026_09_24_000002_spatial_overture_v2_create_places',
                           '2026_09_24_000003_spatial_overture_v2_create_chain_memberships')
   AND batch = :'previous_max_batch'::int + 1 \gset
\if :ok
\echo 'PASS L04 the three v2 migrations share one batch = previous maximum + 1'
\else
\echo 'FAIL L04 the three v2 migrations are not all in batch previous maximum + 1'
SELECT 1 / 0;
\endif

-- L05 — nothing else entered or reached that batch; no row sits above it.
SELECT count(*) FILTER (WHERE batch > :'previous_max_batch'::int) = 3
   AND max(batch) = :'previous_max_batch'::int + 1 AS ok
  FROM migrations \gset
\if :ok
\echo 'PASS L05 no other migration is in the new batch or above it'
\else
\echo 'FAIL L05 another migration is in the new batch or above it'
SELECT 1 / 0;
\endif
\endif

\echo 'LEDGER OK'
