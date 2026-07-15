-- Phase 2 · Batch 0 · Stage 0a — PostGIS/KNN feasibility spike
-- 50_knn_correctness.sql — index KNN (<->) vs brute-force ST_Distance
--
-- For each sparse category, compare the top-10 nearest ids computed two ways:
--   knn_ids   : ORDER BY geom <-> ref        (index-assisted operator distance)
--   brute_ids : ORDER BY ST_Distance(geom,ref) (exhaustive geodesic distance)
-- Both are geodesic metres on geography, so a correct index must return the
-- same ids in the same order.
--
-- Run with any index present (the operator falls back to a scan if none exists);
-- correctness is index-independent by construction.

\set ON_ERROR_STOP on

SET jit = off;
SET max_parallel_workers_per_gather = 0;

WITH ref AS (
    SELECT ST_SetSRID(ST_MakePoint(-80.19, 25.76), 4326)::geography AS g
),
cats(category_key) AS (
    VALUES ('boat_ramp'), ('airport'), ('marina'), ('urgent_care')
)
SELECT
    c.category_key,
    knn.ids                                            AS knn_ids,
    bru.ids                                            AS brute_ids,
    (knn.ids = bru.ids)                                AS exact_order_match,
    (knn.ids @> bru.ids AND knn.ids <@ bru.ids)        AS same_set
FROM cats c
CROSS JOIN ref
CROSS JOIN LATERAL (
    SELECT array_agg(id ORDER BY d) AS ids
    FROM (
        SELECT id, geom <-> ref.g AS d
        FROM places_spike
        WHERE category_key = c.category_key
        ORDER BY geom <-> ref.g
        LIMIT 10
    ) k
) knn
CROSS JOIN LATERAL (
    SELECT array_agg(id ORDER BY d) AS ids
    FROM (
        SELECT id, ST_Distance(geom, ref.g) AS d
        FROM places_spike
        WHERE category_key = c.category_key
        ORDER BY ST_Distance(geom, ref.g)
        LIMIT 10
    ) b
) bru
ORDER BY c.category_key;
