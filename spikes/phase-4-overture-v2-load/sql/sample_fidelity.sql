SET default_transaction_read_only = on;
-- ─────────────────────────────────────────────────────────────────────────────
-- sample_fidelity.sql  —  Overture v2 operator load · READ-ONLY deterministic sample
-- Spatial Intelligence Platform · Phase 4 (see ../RUNBOOK.md)
-- ─────────────────────────────────────────────────────────────────────────────
--
-- Emits one JSON object per line for a DETERMINISTIC sample of loaded rows (the smallest
-- source_ref in each stratum), so ../bin/compare_sample.py can compare every field back to the
-- normalized extraction row in base.ndjson / supplementary.ndjson. This is import FIDELITY only:
-- no fuzzy matching, no de-duplication, no interpretation.
--
-- Strata: grocery, coffee, pharmacy, convenience, fast food, gas, department store, superstore,
-- a rescued CVS row, a fuel-only membership, a store_in_target membership, a
-- storefront_unconfirmed (department) membership. Every statement is a SELECT or a SET.
-- ─────────────────────────────────────────────────────────────────────────────

\set ON_ERROR_STOP on
\set cv 'overture-2026-08-19.0-fl-r2'
\pset format unaligned
\pset tuples_only on

WITH strata(stratum, source_ref) AS (
  SELECT 'category:' || k, (SELECT min(source_ref) FROM overture_v2_places
                             WHERE corpus_version = :'cv' AND lane = 'base' AND category_key = k)
    FROM unnest(ARRAY['grocery_store', 'coffee_shop', 'pharmacy', 'convenience_store',
                      'fast_food_restaurant', 'gas_station', 'department_store', 'superstore']) AS k
  UNION ALL
  SELECT 'rescued_cvs', (SELECT min(source_ref) FROM overture_v2_places
                          WHERE corpus_version = :'cv' AND lane = 'supplementary' AND rescued_chain = 'cvs')
  UNION ALL
  SELECT 'membership:fuel', (SELECT min(p.source_ref) FROM overture_v2_places p
                              JOIN overture_v2_chain_memberships m ON m.place_id = p.id AND m.corpus_version = p.corpus_version
                             WHERE p.corpus_version = :'cv' AND m.role = 'fuel')
  UNION ALL
  SELECT 'membership:store_in_target', (SELECT min(p.source_ref) FROM overture_v2_places p
                              JOIN overture_v2_chain_memberships m ON m.place_id = p.id AND m.corpus_version = p.corpus_version
                             WHERE p.corpus_version = :'cv' AND m.format_key = 'store_in_target')
  UNION ALL
  SELECT 'membership:storefront_unconfirmed', (SELECT min(p.source_ref) FROM overture_v2_places p
                              JOIN overture_v2_chain_memberships m ON m.place_id = p.id AND m.corpus_version = p.corpus_version
                             WHERE p.corpus_version = :'cv' AND m.storefront_status = 'storefront_unconfirmed')
)
SELECT json_build_object(
         'stratum', s.stratum,
         'source_ref', p.source_ref,
         'lane', p.lane,
         'materialization_policy', p.materialization_policy,
         'source', p.source,
         'source_release', p.source_release,
         'extract_recipe_version', p.extract_recipe_version,
         'taxonomy_map_version', p.taxonomy_map_version,
         'name', p.name,
         'category_key', p.category_key,
         'source_category', p.source_category,
         'brand_name', p.brand_name,
         'brand_wikidata', p.brand_wikidata,
         'confidence', p.confidence,
         'operating_status', p.operating_status,
         'rescued_chain', p.rescued_chain,
         'rescued_format', p.rescued_format,
         'lon', ST_X(p.geom::geometry),
         'lat', ST_Y(p.geom::geometry),
         'address', json_build_object('freeform', p.address_freeform, 'locality', p.address_locality,
                                      'postcode', p.address_postcode, 'region', p.address_region,
                                      'country', p.address_country),
         'memberships', COALESCE((SELECT json_agg(json_build_object(
                                   'brand_key', m.brand_key, 'role', m.role, 'format_key', m.format_key,
                                   'storefront_status', m.storefront_status,
                                   'registry_version', m.registry_version,
                                   'registry_rule_hash', m.registry_rule_hash) ORDER BY m.brand_key)
                                   FROM overture_v2_chain_memberships m
                                  WHERE m.place_id = p.id AND m.corpus_version = p.corpus_version), '[]'::json)
       )
  FROM strata s
  LEFT JOIN overture_v2_places p ON p.corpus_version = :'cv' AND p.source_ref = s.source_ref
 ORDER BY s.stratum;
