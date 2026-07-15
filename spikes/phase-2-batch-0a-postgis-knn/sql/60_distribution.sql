-- Phase 2 · Batch 0 · Stage 0a — PostGIS/KNN feasibility spike
-- 60_distribution.sql — dataset size + category/corpus distribution (evidence)

\set ON_ERROR_STOP on

\echo '--- total rows ---'
SELECT count(*) AS total_rows FROM places_spike;

\echo '--- rows per category (with sparse/dense class) ---'
SELECT
    category_key,
    count(*) AS n,
    CASE WHEN category_key IN ('urgent_care','marina','boat_ramp','airport')
         THEN 'sparse' ELSE 'dense' END AS class
FROM places_spike
GROUP BY category_key
ORDER BY n DESC;

\echo '--- rows per category x corpus_version ---'
SELECT category_key, corpus_version, count(*) AS n
FROM places_spike
GROUP BY category_key, corpus_version
ORDER BY category_key, corpus_version;

\echo '--- partitioned table row counts per partition ---'
SELECT 'v1' AS partition, count(*) FROM places_spike_part_v1
UNION ALL
SELECT 'v2' AS partition, count(*) FROM places_spike_part_v2
ORDER BY partition;

\echo '--- parity check: unpartitioned vs partitioned total ---'
SELECT
    (SELECT count(*) FROM places_spike)      AS unpartitioned,
    (SELECT count(*) FROM places_spike_part) AS partitioned,
    (SELECT count(*) FROM places_spike)
      = (SELECT count(*) FROM places_spike_part) AS equal;
