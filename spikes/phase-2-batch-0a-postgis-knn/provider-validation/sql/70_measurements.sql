-- Phase 2 · Batch 0 · Stage 0b — provider validation
-- 70_measurements.sql — version capture + storage / index-size measurement helper
--
-- Self-contained and order-independent: it (re)creates the canonical composite
-- index so the bytes-per-row + 150 GB extrapolation always have `places_cat_geom`
-- present, regardless of which strategy ran last. Leaves the DB in the
-- recommended terminal state (composite index only, on the unpartitioned table).
--
-- Read-mostly: the only mutation is (re)building the composite index + ANALYZE.

\set ON_ERROR_STOP on

\echo '--- server + extension versions ---'
SELECT version();
SELECT postgis_full_version();
SELECT name, default_version, installed_version
FROM pg_available_extensions
WHERE name IN ('postgis','btree_gist','pg_trgm')
ORDER BY name;

-- Ensure the composite index exists for measurement (idempotent).
DROP INDEX IF EXISTS places_geom_only;
DROP INDEX IF EXISTS places_geom_boat_ramp;
DROP INDEX IF EXISTS places_geom_airport;
DROP INDEX IF EXISTS places_geom_marina;
DROP INDEX IF EXISTS places_geom_urgent_care;
DROP INDEX IF EXISTS places_cat_geom;
CREATE INDEX places_cat_geom ON places_spike USING gist (category_key, geom);
ANALYZE places_spike;

\echo '--- table + index totals ---'
SELECT
    c.relname,
    pg_size_pretty(pg_total_relation_size(c.oid)) AS total,
    pg_size_pretty(pg_relation_size(c.oid))       AS heap,
    pg_size_pretty(pg_indexes_size(c.oid))        AS indexes
FROM pg_class c
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'public'
  AND c.relname LIKE 'places_spike%'
  AND c.relkind IN ('r','p')
ORDER BY c.relname;

\echo '--- individual index sizes on places_spike ---'
SELECT
    i.indexrelid::regclass                      AS index,
    pg_size_pretty(pg_relation_size(i.indexrelid)) AS size,
    pg_relation_size(i.indexrelid)              AS bytes
FROM pg_index i
JOIN pg_class c ON c.oid = i.indrelid
WHERE c.relname = 'places_spike'
ORDER BY 1;

\echo '--- composite-index bytes/row + 150 GB extrapolation ---'
-- All arithmetic is explicit numeric; the 150 GB constant is numeric (150 * 2^30)
-- so it cannot overflow int4, and pg_size_pretty() receives a bigint.
WITH m AS (
    SELECT
        (SELECT count(*)                     FROM places_spike)::numeric AS rows,
        (SELECT pg_relation_size('places_cat_geom'))::numeric            AS composite_idx_bytes,
        (SELECT pg_relation_size('places_spike'))::numeric               AS heap_bytes
),
d AS (
    SELECT
        rows,
        composite_idx_bytes,
        heap_bytes,
        composite_idx_bytes / NULLIF(rows, 0) AS composite_bytes_per_row,
        heap_bytes          / NULLIF(rows, 0) AS heap_bytes_per_row,
        (150::numeric * 1073741824)           AS bytes_150gb   -- 150 GiB in bytes, numeric
    FROM m
)
SELECT
    rows::bigint                                             AS rows,
    composite_idx_bytes::bigint                              AS composite_idx_bytes,
    round(composite_bytes_per_row, 2)                        AS composite_idx_bytes_per_row,
    round(heap_bytes_per_row, 2)                             AS heap_bytes_per_row,
    floor(bytes_150gb / NULLIF(heap_bytes_per_row, 0))::bigint
                                                             AS projected_rows_at_150gb_heap,
    pg_size_pretty(
        floor(composite_bytes_per_row
              * floor(bytes_150gb / NULLIF(heap_bytes_per_row, 0)))::bigint
    )                                                        AS projected_composite_idx_at_150gb
FROM d;
