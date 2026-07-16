-- ============================================================================
-- B1.2 — mixed-geometry KNN EXPLAIN harness  (RUN LATER, on the cluster)
-- E-50 acceptance gate · plan §6.
--
-- Two distinct checks:
--   A. PLAN SHAPE — of the composite-GiST CANDIDATE RETRIEVAL (the §7.5
--      over-fetch inner query). This is what SIA-D41 pins: Index Scan using
--      places_cat_geom · Index Cond on category_key · in-scan `<->` Order By ·
--      0 rows removed · 0 Sort · 0 Seq Scan. Fed to
--      Tests\Support\Spatial\ExplainPlanShape.  (The OUTER spheroid re-rank
--      legitimately adds a WindowAgg/Sort over only the 20 over-fetched rows —
--      that is NOT part of the plan-shape gate.)
--   B. SET-EXACTNESS + ORDER — the over-fetch(20) + spheroidal re-rank returns
--      the exact nearest SET, order-corrected (E-50: `<->` sphere order can swap
--      near-equidistant neighbours; the re-rank fixes it).
--
-- Precondition: generate_tier2_fixture.sql has loaded 'fixture-tier2-v1' + ANALYZE.
-- Over-fetch = 20 (K=3, factor≥2, floor≥20 — SIA-D40; unchanged).
-- Reference point: a fixed interior US coordinate.
-- ============================================================================

\set ref 'ST_SetSRID(ST_MakePoint(-95.0, 38.0), 4326)::geography'
\set cv  '''fixture-tier2-v1'''

-- ─────────────────────────────────────────────────────────────────────────
-- A. PLAN SHAPE — candidate retrieval, one EXPLAIN per sparse category + park.
--    Capture each JSON block and feed to ExplainPlanShape->evaluate($json, $cat).
-- ─────────────────────────────────────────────────────────────────────────

-- airport  (sparse Polygon — the E-50 order-swap category at Tier-2)
EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON)
SELECT p.place_id, p.name
FROM   places p
WHERE  p.category_key = 'airport' AND p.corpus_version = :cv
ORDER BY p.geom <-> :ref
LIMIT  20;

-- boat_ramp  (sparse LineString)
EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON)
SELECT p.place_id, p.name
FROM   places p
WHERE  p.category_key = 'boat_ramp' AND p.corpus_version = :cv
ORDER BY p.geom <-> :ref
LIMIT  20;

-- marina  (sparse Point)
EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON)
SELECT p.place_id, p.name
FROM   places p
WHERE  p.category_key = 'marina' AND p.corpus_version = :cv
ORDER BY p.geom <-> :ref
LIMIT  20;

-- urgent_care  (sparse Point)
EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON)
SELECT p.place_id, p.name
FROM   places p
WHERE  p.category_key = 'urgent_care' AND p.corpus_version = :cv
ORDER BY p.geom <-> :ref
LIMIT  20;

-- park  (dense Polygon — exercises polygon geometry under the composite index)
EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON)
SELECT p.place_id, p.name
FROM   places p
WHERE  p.category_key = 'park' AND p.corpus_version = :cv
ORDER BY p.geom <-> :ref
LIMIT  20;

-- ─────────────────────────────────────────────────────────────────────────
-- B. SET-EXACTNESS + ORDER — full §7.5 over-fetch + spheroid re-rank vs
--    brute-force spheroid ground truth, per sparse category.
--    same_set MUST be true; exact_order_after_rerank MUST be true.
-- ─────────────────────────────────────────────────────────────────────────
WITH cats AS (
  SELECT unnest(ARRAY['airport','boat_ramp','marina','urgent_care']) AS category_key
),
overfetch AS (
  SELECT c.category_key, p.place_id, p.geom
  FROM   cats c
  CROSS JOIN LATERAL (
    SELECT p.place_id, p.geom FROM places p
    WHERE  p.category_key = c.category_key AND p.corpus_version = :cv
    ORDER BY p.geom <-> :ref
    LIMIT  20                                   -- SIA-D40 over-fetch
  ) p
),
reranked AS (
  SELECT category_key, place_id,
         row_number() OVER (PARTITION BY category_key
                            ORDER BY ST_Distance(:ref, geom, true)) AS rn
  FROM overfetch
),
top3_rerank AS (
  SELECT category_key, array_agg(place_id ORDER BY rn) AS ids
  FROM reranked WHERE rn <= 3 GROUP BY category_key
),
ground_truth AS (
  SELECT c.category_key, array_agg(t.place_id ORDER BY t.rn) AS ids
  FROM cats c
  CROSS JOIN LATERAL (
    SELECT p.place_id,
           row_number() OVER (ORDER BY ST_Distance(:ref, p.geom, true)) AS rn
    FROM places p
    WHERE p.category_key = c.category_key AND p.corpus_version = :cv
    ORDER BY ST_Distance(:ref, p.geom, true)
    LIMIT 3
  ) t
  GROUP BY c.category_key
)
SELECT r.category_key,
       (r.ids <@ g.ids AND g.ids <@ r.ids) AS same_set,
       (r.ids = g.ids)                     AS exact_order_after_rerank
FROM top3_rerank r JOIN ground_truth g USING (category_key)
ORDER BY r.category_key;
