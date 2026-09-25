SET default_transaction_read_only = on;
-- ─────────────────────────────────────────────────────────────────────────────
-- verify_v2_load.sql  —  Overture v2 operator load · READ-ONLY post-load proofs
-- Spatial Intelligence Platform · Phase 4 (see ../RUNBOOK.md)
-- ─────────────────────────────────────────────────────────────────────────────
--
-- Reconciles the loaded v2 corpus against the VALIDATED import plan for
-- overture-2026-08-19.0-fl-r2 (reproduced 2026-09-24, byte-identical to the contract in
-- config/overture_v2_corpus.php). Every expected number below is that plan's, not a tolerance:
-- a load that differs by one row fails. Every statement is a SELECT or a SET.
--
-- Each check prints `PASS <id>` or prints `FAIL <id>` and stops psql with an error.
--
--   bin/spatial_psql.sh -X -q -v ON_ERROR_STOP=1 -f sql/verify_v2_load.sql   (never the URL as an argument)
-- ─────────────────────────────────────────────────────────────────────────────

\set ON_ERROR_STOP on
\set cv 'overture-2026-08-19.0-fl-r2'

-- V01 — exactly one ledger row, `ready`, with every pin, checksum and count of the contract.
SELECT count(*) = 1 AS ok FROM overture_v2_corpora
 WHERE corpus_version = :'cv'
   AND status = 'ready' AND finished_at IS NOT NULL AND failure_reason IS NULL
   AND source = 'overture' AND source_release = '2026-08-19.0'
   AND extract_recipe_version = 'overture-extract-v2' AND taxonomy_map_version = 'overture-taxonomy-v2.0'
   AND registry_version = 'chain-registry-v2'
   AND registry_rule_hash = 'b5920a1c73199a0d8e030e5281018baa763438eb7ad02aee76f7ba3cbc0b151f'
   AND base_sha256 = 'bb9e77c13790897155f847fa3a7ed48067930cf08257e227bee60bf96c91a168'
   AND supplementary_sha256 = 'edeed1435d707912dedd08d642cf1088e669cf4dc329ea3248a289be3e91c8e4'
   AND base_rows = 52566 AND supplementary_rows = 2962 AND matcher_analysis_rows = 55528
   AND diagnostic_rows = 2734 AND rescue_admitted_rows = 150 AND rescue_refused_rows = 78
   AND expected_memberships = 11082
   AND imported_base_rows = 52566 AND imported_rescued_rows = 150 AND imported_memberships = 11082 \gset
\if :ok
\echo 'PASS V01 ledger: one ready row with the contract pins, checksums and counts'
\else
\echo 'FAIL V01 ledger row missing, not ready, or differs from the contract'
SELECT 1 / 0;
\endif

-- V02 — no other v2 corpus exists: this procedure loads exactly one version.
SELECT (SELECT count(*) FROM overture_v2_corpora) = 1 AS ok \gset
\if :ok
\echo 'PASS V02 exactly one v2 corpus row exists'
\else
\echo 'FAIL V02 unexpected additional v2 corpus rows'
SELECT 1 / 0;
\endif

-- V03 — places: 52,716 = 52,566 base (corpus) + 150 rescued supplementary.
SELECT count(*) = 52716
   AND count(*) FILTER (WHERE lane = 'base' AND materialization_policy = 'corpus') = 52566
   AND count(*) FILTER (WHERE lane = 'supplementary' AND materialization_policy = 'rescued') = 150 AS ok
  FROM overture_v2_places WHERE corpus_version = :'cv' \gset
\if :ok
\echo 'PASS V03 places 52,716 (base 52,566 + rescued 150)'
\else
\echo 'FAIL V03 place counts differ from the plan'
SELECT 1 / 0;
\endif

-- V04 — nothing that must never be stored was stored (diagnostic / matcher_only / refused).
SELECT count(*) = 0 AS ok FROM overture_v2_places
 WHERE corpus_version = :'cv'
   AND (materialization_policy NOT IN ('corpus', 'rescued')
        OR supplementary_role IS DISTINCT FROM CASE WHEN lane = 'base' THEN NULL ELSE 'rescue_candidate' END
        OR (lane = 'supplementary' AND rescue_verdict IS DISTINCT FROM 'admitted')) \gset
\if :ok
\echo 'PASS V04 no diagnostic, matcher_only or refused row was stored'
\else
\echo 'FAIL V04 a row that must never be stored is present'
SELECT 1 / 0;
\endif

-- V05 — operating status: 47,639 open, 5,077 unknown (NULL), nothing else.
SELECT count(*) FILTER (WHERE operating_status = 'open') = 47639
   AND count(*) FILTER (WHERE operating_status IS NULL) = 5077
   AND count(*) FILTER (WHERE operating_status IS NOT NULL AND operating_status <> 'open') = 0 AS ok
  FROM overture_v2_places WHERE corpus_version = :'cv' \gset
\if :ok
\echo 'PASS V05 operating status 47,639 open / 5,077 unknown'
\else
\echo 'FAIL V05 operating status counts differ from the plan'
SELECT 1 / 0;
\endif

-- V06 — base places per canonical category, exactly the plan's sixteen counts.
WITH expected(category_key, n) AS (VALUES
  ('burger_restaurant', 1714), ('cafe', 2232), ('chicken_restaurant', 1096), ('coffee_shop', 3347),
  ('convenience_store', 5400), ('department_store', 1173), ('drugstore', 605),
  ('fast_food_restaurant', 5204), ('gas_station', 6160), ('grocery_store', 4826), ('gym', 5171),
  ('pharmacy', 2914), ('restaurant', 10998), ('shopping_center', 1115), ('superstore', 144),
  ('taco_restaurant', 467)),
actual AS (SELECT category_key, count(*) AS n FROM overture_v2_places
            WHERE corpus_version = :'cv' AND lane = 'base' GROUP BY category_key)
SELECT count(*) = 0 AS ok FROM expected e FULL JOIN actual a USING (category_key)
 WHERE e.n IS DISTINCT FROM a.n \gset
\if :ok
\echo 'PASS V06 base per-category counts match the plan (16 categories)'
\else
\echo 'FAIL V06 base per-category counts differ from the plan'
SELECT 1 / 0;
\endif

-- V07 — integrity: unique source_ref per corpus, valid points, inside the extraction bbox.
SELECT (SELECT count(*) - count(DISTINCT source_ref) FROM overture_v2_places WHERE corpus_version = :'cv') = 0
   AND (SELECT count(*) FROM overture_v2_places WHERE corpus_version = :'cv'
         AND (NOT ST_IsValid(geom::geometry) OR GeometryType(geom::geometry) <> 'POINT')) = 0
   AND (SELECT count(*) FROM overture_v2_places WHERE corpus_version = :'cv'
         AND (ST_X(geom::geometry) NOT BETWEEN -87.63 AND -79.97
              OR ST_Y(geom::geometry) NOT BETWEEN 24.40 AND 31.00)) = 0 AS ok \gset
\if :ok
\echo 'PASS V07 no duplicate source_ref, no invalid geometry, no out-of-bbox coordinate'
\else
\echo 'FAIL V07 duplicate source_ref, invalid geometry or out-of-bbox coordinate'
SELECT 1 / 0;
\endif

-- V08 — memberships: 11,082 total; 10,533 storefront / 239 fuel / 310 department; every
-- department membership storefront_unconfirmed.
SELECT count(*) = 11082
   AND count(*) FILTER (WHERE role = 'storefront' AND storefront_status = 'storefront') = 10533
   AND count(*) FILTER (WHERE role = 'fuel' AND storefront_status = 'fuel') = 239
   AND count(*) FILTER (WHERE role = 'department' AND storefront_status = 'storefront_unconfirmed') = 310 AS ok
  FROM overture_v2_chain_memberships WHERE corpus_version = :'cv' \gset
\if :ok
\echo 'PASS V08 memberships 11,082 (storefront 10,533 / fuel 239 / department 310 unconfirmed)'
\else
\echo 'FAIL V08 membership role/status counts differ from the plan'
SELECT 1 / 0;
\endif

-- V09 — memberships per chain:format, exactly the plan's thirty-one pairs.
WITH expected(brand_key, format_key, n) AS (VALUES
  ('aldi', 'store', 273), ('burger_king', 'restaurant', 596), ('chick_fil_a', 'restaurant', 307),
  ('cvs', 'store', 853), ('cvs', 'store_in_target', 7), ('mcdonalds', 'restaurant', 973),
  ('publix', 'pharmacy_department', 64), ('publix', 'supermarket', 869),
  ('racetrac', 'fuel', 40), ('racetrac', 'store', 254), ('seven_eleven', 'fuel', 48),
  ('seven_eleven', 'store', 1095), ('shell', 'station', 1150), ('shell', 'station_store', 21),
  ('speedway', 'fuel', 151), ('speedway', 'store', 18), ('starbucks', 'store', 1050),
  ('taco_bell', 'restaurant', 504), ('target', 'store', 126), ('trader_joes', 'store', 21),
  ('walgreens', 'store', 840), ('walmart', 'grocery_department', 21),
  ('walmart', 'neighborhood_market', 102), ('walmart', 'pharmacy_department', 173),
  ('walmart', 'store', 93), ('walmart', 'supercenter', 118), ('wawa', 'store', 318),
  ('wendys', 'restaurant', 613), ('whole_foods', 'store', 32),
  ('winn_dixie', 'pharmacy_department', 31), ('winn_dixie', 'store', 321)),
actual AS (SELECT brand_key, format_key, count(*) AS n FROM overture_v2_chain_memberships
            WHERE corpus_version = :'cv' GROUP BY brand_key, format_key)
SELECT count(*) = 0 AS ok FROM expected e FULL JOIN actual a USING (brand_key, format_key)
 WHERE e.n IS DISTINCT FROM a.n \gset
\if :ok
\echo 'PASS V09 memberships per chain:format match the plan (31 pairs, 20 chains)'
\else
\echo 'FAIL V09 memberships per chain:format differ from the plan'
SELECT 1 / 0;
\endif

-- V10 — the 150 rescued places carry exactly 150 CVS memberships (147 store, 3 store_in_target).
SELECT count(*) = 150
   AND count(*) FILTER (WHERE m.brand_key = 'cvs' AND m.format_key = 'store') = 147
   AND count(*) FILTER (WHERE m.brand_key = 'cvs' AND m.format_key = 'store_in_target') = 3 AS ok
  FROM overture_v2_chain_memberships m
  JOIN overture_v2_places p ON p.id = m.place_id AND p.corpus_version = m.corpus_version
 WHERE m.corpus_version = :'cv' AND p.lane = 'supplementary' \gset
\if :ok
\echo 'PASS V10 rescued memberships 150 CVS (147 store, 3 store_in_target)'
\else
\echo 'FAIL V10 rescued memberships differ from the plan'
SELECT 1 / 0;
\endif

-- V11 — membership integrity: no duplicate (place, brand), no orphan, registry = the ledger's.
SELECT (SELECT count(*) - count(DISTINCT (place_id, brand_key)) FROM overture_v2_chain_memberships
         WHERE corpus_version = :'cv') = 0
   AND (SELECT count(*) FROM overture_v2_chain_memberships m
         WHERE m.corpus_version = :'cv' AND NOT EXISTS (
               SELECT 1 FROM overture_v2_places p WHERE p.id = m.place_id AND p.corpus_version = m.corpus_version)) = 0
   AND (SELECT count(*) FROM overture_v2_chain_memberships m JOIN overture_v2_corpora c USING (corpus_version)
         WHERE m.corpus_version = :'cv'
           AND (m.registry_version <> c.registry_version OR m.registry_rule_hash <> c.registry_rule_hash)) = 0 AS ok \gset
\if :ok
\echo 'PASS V11 no duplicate membership, no orphan, registry hash equals the ledger'
\else
\echo 'FAIL V11 duplicate membership, orphan, or registry mismatch'
SELECT 1 / 0;
\endif

-- V12 — v2 is not active and cannot be: the schema has no such state, and the v1 ledger still
-- names v1 as the one active overture-places corpus.
SELECT (SELECT count(*) FROM overture_v2_corpora WHERE status NOT IN ('preparing', 'ready', 'failed')) = 0
   AND (SELECT string_agg(corpus_version, ',') FROM corpus_imports
         WHERE dataset = 'overture-places' AND status = 'active') = 'overture-2026-06-17.0-fl' AS ok \gset
\if :ok
\echo 'PASS V12 v2 is NOT active; v1 remains the one active overture-places corpus'
\else
\echo 'FAIL V12 activation state is not as required'
SELECT 1 / 0;
\endif

-- The ledger row's state, as one line, so a second (no-op) import can be proven to have changed
-- nothing — not even a timestamp — by comparing this line before and after it.
\pset format unaligned
\pset tuples_only on
SELECT 'LEDGER ' || status || ' ' || import_run || ' '
       || to_char(started_at AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') || ' '
       || to_char(finished_at AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"')
  FROM overture_v2_corpora WHERE corpus_version = :'cv';

\echo 'VERIFY OK'
