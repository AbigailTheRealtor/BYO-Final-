SET default_transaction_read_only = on;
-- ─────────────────────────────────────────────────────────────────────────────
-- v1_snapshot.sql  —  Overture v2 operator load · READ-ONLY v1 fingerprint
-- Spatial Intelligence Platform · Phase 4 (see ../RUNBOOK.md)
-- ─────────────────────────────────────────────────────────────────────────────
--
-- Prints one `key=value` line per fact about everything the v2 load must never touch. It is taken
-- immediately BEFORE and AFTER every write stage and the two outputs must be BYTE-IDENTICAL
-- (`diff` exits 0). Content hashes cover every column of every v1 row, so an UPDATE that restores
-- a value, a DELETE + re-INSERT or a changed geometry all change the output; the table counters
-- additionally prove that no row was inserted, updated or deleted in between, even invisibly.
--
-- Every statement is a SELECT or a SET. Output is unaligned and tuples-only for diffing.
-- ─────────────────────────────────────────────────────────────────────────────

\set ON_ERROR_STOP on
\pset format unaligned
\pset tuples_only on
SET TimeZone = 'UTC';

-- v1 places: row count and a hash over every column of every row, in a stable order.
SELECT 'v1_places_rows=' || count(*) FROM places WHERE corpus_version = 'overture-2026-06-17.0-fl';
SELECT 'v1_places_md5=' || COALESCE(md5(string_agg(md5(p::text), '' ORDER BY p.place_id)), 'empty')
  FROM places p WHERE p.corpus_version = 'overture-2026-06-17.0-fl';

-- Every places partition that exists (v2 must add none).
SELECT 'places_partitions=' || COALESCE(string_agg(c.relname, ',' ORDER BY c.relname), '')
  FROM pg_inherits i JOIN pg_class c ON c.oid = i.inhrelid
 WHERE i.inhparent = 'public.places'::regclass;

-- The whole corpus ledger (v2 writes none of it) and the shared v1 taxonomy.
SELECT 'corpus_imports_rows=' || count(*) FROM corpus_imports;
SELECT 'corpus_imports_md5=' || COALESCE(md5(string_agg(md5(ci::text), '' ORDER BY ci.id)), 'empty') FROM corpus_imports ci;
SELECT 'place_categories_md5=' || COALESCE(md5(string_agg(md5(pc::text), '' ORDER BY pc::text)), 'empty') FROM place_categories pc;
SELECT 'place_category_mappings_md5=' || COALESCE(md5(string_agg(md5(m::text), '' ORDER BY m::text)), 'empty') FROM place_category_mappings m;
SELECT 'place_authority_links_rows=' || count(*) FROM place_authority_links;

-- Cumulative write counters on the v1 partition. Unchanged before/after = nothing was written.
SELECT 'v1_partition_counters=' || n_tup_ins || '/' || n_tup_upd || '/' || n_tup_del || '/' || n_tup_hot_upd
  FROM pg_stat_user_tables
 WHERE schemaname = 'public' AND relname = 'places_p_overture_2026_06_17_0_fl';

-- Location DNA reads v1 through the ledger's active row; it must still name v1 alone.
SELECT 'active_overture_places=' || COALESCE(string_agg(corpus_version, ',' ORDER BY corpus_version), '')
  FROM corpus_imports WHERE dataset = 'overture-places' AND status = 'active';
