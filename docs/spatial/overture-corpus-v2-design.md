# Overture Florida corpus v2 — design (Phase 1, PR 0 findings incorporated)

**Status:** DESIGN. PR 1 adds only the pure crosswalk (`App\Services\Spatial\OvertureTaxonomyMapV2`)
and the explicit Location DNA boundary (`CorpusPoiCategoryMap::LOCATION_DNA_KEYS`); nothing has been
extracted, imported, migrated or wired, and no runtime behaviour changes.
The active corpus remains `overture-2026-06-17.0-fl` (29,434 rows) and is never modified.
Source release for v2: Overture `2026-08-19.0`. Evidence: read-only in-memory DuckDB census
of that release, Florida bounding box `[-87.63, 24.40, -79.97, 31.00]` (containment on the
row bbox, identical to v1), 1,245,925 rows, run 2026-09-18.

Two premises corrected by measurement:

* `categories.primary` is **not** less complete than `taxonomy.primary` — in 2026-08-19.0 both
  are null on exactly the same 58,954 rows (4.7%). The difference is vocabulary (35% of rows
  renamed), so a `categories.primary` fallback recovers nothing.
* v1's "0 rejected, fully accounted" was an artefact: DuckDB filtered category and confidence
  before the normalizer saw a row. v2 must account at extraction time.

---

## 1. Locked product decisions

| # | Decision |
|---|---|
| 1 | `cafe` = its **own** canonical corpus category. Not folded into `coffee_shop`. Location DNA stays at exactly its current 7 keys. |
| 2 | `open` eligible · NULL eligible, status retained and reported as unknown · `permanently_closed` excluded · any other value excluded (fail closed) until explicitly mapped. |
| 3a | Walmart Neighborhood Market answers `walmart`; format preserved as `neighborhood_market`. |
| 3b | CVS inside Target answers `cvs`. Target and CVS are two chain identities at one address; different chains are never merged on shared coordinates/address. |
| 3c | 7-Eleven / Speedway co-brand: a site with genuine evidence of both is discoverable under either key; the relationship is recorded explicitly; a unique-site count never double-counts it. |
| 3d | Fuel-only 7-Eleven: registry member with `format = fuel_only`; returned by a generic brand query only with the format visible; excluded by any query requiring a storefront. |
| 4 | `drugstore` exists in the corpus and brand search; it is **not** folded into Location DNA `pharmacy`. |
| 5 | Excluded from v2: liquor-store tokens, truck-stop tokens, `mexican_restaurant`. |
| 6 | Every chain starts `provisional`; promotion to `supported` requires a recorded validation packet (§9). |
| 7 | Overture addresses retained for de-duplication, identity resolution and internal validation only; never displayed by this feature. |
| 8 | GA/AL spill-over from the bounding box is kept and tallied by region. |
| 9 | Chains' own Wikidata IDs come from measured data only (§6); own / exclusion / provisional IDs are distinguished. |

## 2. Final import list and canonical crosswalk

Classifier: `taxonomy.primary`. `categories.primary` kept as provenance only. No fallback when
`taxonomy.primary` is null. Counts: 2026-08-19.0, Florida box.
*Eligible* = `confidence >= 0.90` AND (`open` OR NULL).

| Source token (`taxonomy.primary`) | `categories.primary` | Canonical key | In corpus | Brand search | Location DNA | Eligible | of which status NULL | Branded |
|---|---|---|---|---|---|---|---|---|
| `restaurant` | same | `restaurant` | ✅ | ✅ | ✅ | 10,998 | 325 | 1.8% |
| `gas_station` | same | `gas_station` | ✅ | ✅ | ✅ | 6,160 | 1,306 | 48.4% |
| `gym` | same | `gym` | ✅ | ✅ | ✅ | 5,171 | 386 | 14.7% |
| `grocery_store` | same | `grocery_store` | ✅ | ✅ | ✅ | 4,826 | 461 | 29.3% |
| `coffee_shop` | same | `coffee_shop` | ✅ | ✅ | ✅ | 3,347 | 243 | 33.5% |
| `pharmacy` | same | `pharmacy` | ✅ | ✅ | ✅ | 2,914 | 468 | 17.6% |
| `shopping_mall` | `shopping_center` | `shopping_center` | ✅ | ✅ | ✅ | 1,115 | 140 | 5.7% |
| `convenience_store` | same | `convenience_store` | ✅ | ✅ | ❌ | 5,400 | 1,014 | 21.9% |
| `fast_food_restaurant` | same | `fast_food_restaurant` | ✅ | ✅ | ❌ | 5,204 | 186 | 65.1% |
| `cafe` | same | `cafe` | ✅ | ✅ | ❌ | 2,232 | 79 | 9.6% |
| `burger_restaurant` | same | `burger_restaurant` | ✅ | ✅ | ❌ | 1,714 | 71 | 36.5% |
| `department_store` | same | `department_store` | ✅ | ✅ | ❌ | 1,173 | 115 | 50.8% |
| `chicken_restaurant` | same | `chicken_restaurant` | ✅ | ✅ | ❌ | 1,096 | 49 | 37.0% |
| `drugstore` | same | `drugstore` | ✅ | ✅ | ❌ | 605 | 162 | 0.0% |
| `taco_restaurant` | same | `taco_restaurant` | ✅ | ✅ | ❌ | 467 | 17 | 15.5% |
| `superstore` | same | `superstore` | ✅ | ✅ | ❌ | 144 | 22 | 78.7% |

* **Total eligible: 52,566** — FL 51,610 · GA/AL 941 · other/null region 15.
* `fitness_center` is **absent** from both fields in this release (0 rows) and is dropped from
  the v2 import list. v1's `OvertureCategoryMap` is untouched and keeps its own mapping.
  Other gym-adjacent tokens exist and are **not** imported (they would change the `gym`
  section): `fitness_trainer` 1,880, `sport_or_fitness_facility` 1,615, `gymnastics_center` 420,
  `boxing_gym` 107, `fitness_exercise_store` 91, `gymnastics_club` 6, `aerial_fitness_center` 5.
* Rows with null `taxonomy.primary` (58,954) are rejected and tallied under `(none)`, sub-tallied
  by `basic_category`. No rescue in v2.
* Anything not in this table is excluded by default (allowlist).

## 3. Never-import list (evidence)

The allowlist already excludes these; they are listed because they masquerade as the parent
chain and must also be rejected by the chain matcher.

| `taxonomy.primary` | Rows | ≥0.90 | Named-chain rows | Why / contamination |
|---|---|---|---|---|
| `money_transfer_service` | 8,923 | 5,056 | 2,062 | Western Union counters named for the host: Publix 734, Walgreens 555, Walmart 354, 7-Eleven 253 ("PUBLIX #1029", "7-ELEVEN/SPEEDWAY #46807", "Walmart Money Center") |
| `atm` | 8,432 | 1,792 | 520 | "ATM Walgreens" (Citibank) 198, "ATM 7ELEVEN-FCTI" 54; Wawa 52, Shell 44 |
| `check_cashing_payday_loans` | 379 | 344 | — | financial counter family |
| `package_locker` | 971 | 15 | 0 | locker at host store |
| `ev_charging_station` | 748 | 68 | 24 | Walmart 13 (Electrify America), "Shell Recharge" 10 |
| `shopping` | 6,742 | 3,176 | 370 | generic; "CVS Pharmacy" 131, "CVS Beauty" 64 (see §10 open item) |
| `bakery` | 4,175 | 2,661 | 228 | "Walmart Bakery" 217 |
| `camera_and_photography_store` | 630 | 352 | 143 | "Walmart Photo Center" 138 |
| `eyewear_store` | 1,396 | 899 | 137 | "Walmart Vision & Glasses" 125, "Target Optical" 8 |
| `laboratory_testing` | 1,466 | 916 | 59 | "Labcorp at Walgreens" |
| `b2b_clinical_lab` | 198 | 161 | 46 | "Labcorp at Walgreens" |
| `outpatient_care_facility` | 5,951 | 4,073 | 49 | "Village Medical at Walgreens" 30, "Walmart Health" 12 |
| `walk_in_clinic` | 230 | 133 | 42 | "MinuteClinic at CVS" 23, "UHealth Clinic at Walgreens" 13 |
| `doctors_office` | 11,813 | 6,942 | 41 | "Minute Clinic" (brand CVS Pharmacy) 33 |
| `liquor_store` | 2,068 | 1,583 | 65 | "Winn-Dixie Liquor" 24, Publix 22 (decision 5) |
| `beer_wine_spirits_store` | 387 | 198 | 44 | "Winn-Dixie Wine & Spirits", Publix 28 (decision 5) |
| `truck_gas_station` | 104 | 67 | 19 | RaceTrac truck lanes (decision 5) |
| `truck_stop` | 23 | 16 | 0 | (decision 5) |
| `mexican_restaurant` | 3,952 | 3,188 | 10 | Taco Bell 10 — covered by fast_food/taco (decision 5) |
| `race_track` | 378 | 165 | 75 | "Daytona International Speedway"; Speedway 68, RaceTrac 7 |
| `beverage_supplier` | 151 | 117 | 85 | "Burger King" 85 |
| `real_estate_agent` | 36,062 | 15,652 | 52 | "Royal Shell Real Estate" |
| `charity_organization` | 4,172 | 1,690 | 10 | "Ronald McDonald House Charities" |
| `caterer` | 1,366 | 828 | 6 | "Publix Catering", Chick-fil-A catering |
| `internet_cafe`, `cafeteria`, `coffee_roastery` | 42 / 55 / 75 | | | not `cafe` / `coffee_shop`; not imported |

## 4. Operating-status mapping

Measured values in the Florida box — **only three exist**: `open` 829,356 · NULL 379,822 ·
`permanently_closed` 36,747. Within the import tokens at ≥0.90: `open` 47,522 · NULL 5,044 ·
`permanently_closed` 423.

| Value | Rule |
|---|---|
| `open` | eligible |
| NULL | eligible; stored; surfaced as `status_unknown` |
| `permanently_closed` | excluded, tallied |
| anything else (unseen) | excluded, tallied (fail closed) — no inferred semantics |

The chain matcher additionally rejects names asserting closure (e.g. "7-Eleven - Closed").

## 5. Corpus row contract (proposed schema diff — no migration yet)

`places` — three new nullable columns (metadata-only on the partitioned parent; v1 reads null):
`source_category text` (`taxonomy.primary`), `brand_wikidata text`, `operating_status text`.

`places.attrs` — `legacy_category` (`categories.primary`), `basic_category`,
`taxonomy_hierarchy`, `address {freeform, locality, postcode, region}` (internal only, decision 7),
`extract_rule_version`.

Unchanged: `source_ref`/`gers_id`, `name` (`names.primary`), `brand` (`brand.names.primary`),
`confidence`, `geom`, `centroid`. bbox not retained. Release recorded in the ledger.

New derived tables (re-buildable per registry version, `place_authority_links` precedent):
`poi_chain_sites (corpus_version, registry_version, site_id, brand_key, format_key,
category_key, representative_place_id, geom geography(Point), member_count, confidence,
operating_status)` PK `(corpus_version, registry_version, site_id)`, gist `(brand_key, geom)`;
`poi_chain_site_members (corpus_version, registry_version, place_id, site_id, brand_key,
role, match_method)` PK `(corpus_version, registry_version, place_id, brand_key)` — a place may
hold more than one chain membership (co-brand, decision 3c).

Data (not schema): seed `place_categories` rows for the new keys; `place_category_mappings`
rows under a new source tag `overture_taxonomy`; ledger status `validated`.

## 6. Chain identity — measured Wikidata evidence

"Own QID" = a QID whose Florida-box rows carry the chain's own `brand.names.primary` on
≥90% of rows **and** a storefront category. Fill is measured on the chain's *eligible* rows.

| brand_key | Own QID (measured) | Rows with QID | Eligible rows | Own-QID fill | Status |
|---|---|---|---|---|---|
| publix | Q672170 | 36 | 941 | 1.3% | observed |
| starbucks | Q37158 | 41 | 1,051 | 1.0% | observed |
| walgreens | Q1591889 | 2 | 855 | 0.0% | observed, thin |
| cvs | Q2078880 | 5 | 735 | 0.3% | observed |
| walmart | — | 0 | 530 | 0% | **none observed** |
| target | Q1046951 | 1 | 134 | 0% | observed, thin |
| aldi | Q41171672 | 59 | 273 | 4.8% | observed |
| winn_dixie | Q1264366 | 1 | 353 | 0% | observed, thin |
| whole_foods | — | 0 | 36 | 0% | **none observed** |
| trader_joes | Q688825 | 6 | 21 | 9.5% | observed |
| seven_eleven | Q259340 | 12 | 1,169 | 0.3% | observed |
| wawa | Q5936320 | 326 | 318 | 0.0% | observed — all 326 QID rows are <0.90 |
| racetrac | Q735942 | 224 | 303 | 1.3% | observed — mostly <0.90 gas rows |
| speedway | — | 0 | 172 | 0% | **none observed** |
| shell | Q110716465 | 22 | 1,200 | 0.3% | observed |
| mcdonalds | Q38076 | 31 | 976 | 0.3% | observed |
| taco_bell | Q752941 | 6 | 505 | 0.8% | observed |
| chick_fil_a | Q491516 | 27 | 309 | 2.9% | observed |
| wendys | Q550258 | 9 | 606 | 0.8% | observed |
| burger_king | Q177054 | 9 | 599 | 0.5% | observed |

Rule: an own QID is **positive evidence only** and never required (fill ≤ 9.5% everywhere).
Every observed own QID had zero rows carrying another chain's brand name.

**Department / sub-brand QIDs (not storefront identity):** Target Optical Q19903688 (4 rows,
`eyewear_store`). Would pass a naive "brand matches" test — the storefront-category condition
is what rejects it.

**Exclusion / service QIDs found on name-matched rows:** Western Union Q861042 (2,948 rows),
Citibank Q857063 (410, ATM), BMO Q4835981 (158, ATM), PNC Bank Q38928 (29, ATM), Santander
Q5835668 (ATM), Electrify America Q59773555 (EV), Quest Diagnostics Q7271456 (lab).

**Co-located fuel brand — neither identity nor exclusion:** Mobil Q109676002 on 73 "7-Eleven"
gas rows (all <0.90); Arco Q304769 on 1 Shell-named row. Registry field `fuel_brand_wikidata`
records this; it does not assign or remove chain membership.

**Unrelated tenant QIDs on name-substring matches** (rejected by category/pattern): Dutch Bros,
CubeSmart, Hertz, Banfield, Econo Lodge, Wetzel's Pretzels.

After the never-import filter, only two non-own QIDs remain on eligible chain rows (Arco on one
Shell row; one Walgreens row) — the category allowlist does most of the exclusion work.

## 7. Chain registry structure

Config (versioned) read by one pure reader usable without a booted container:
`brand_key, display_name, registry_status (provisional|supported|unsupported),
name_patterns[], brand_name_aliases[], own_wikidata[] (+evidence), department_wikidata[],
formats{format_key: {name_patterns, categories, storefront: bool}},
roles{category_key: storefront|department|fuel}, exclude_name_patterns[],
co_brands[] (brand_key pairs with evidence rule)`; global `exclude_wikidata[]`,
`global_exclude_name_patterns[]`.

Match order: reject (exclusion QID / never-import token / exclusion pattern / closed name) →
own QID (storefront category only) → brand-name alias → name pattern; the row's category must
be in `roles`. A name containing the chain's name is never sufficient alone.

Registry notes from measurement:

* **Weak identity (name-only in practice):** Speedway (brand name on 9/172 eligible rows, no own
  QID), Walgreens (brand name on 28/855), Walmart (no own QID; departments dominate), Whole
  Foods (no QID; 36 eligible rows).
* **Wawa:** eligible rows (318) carry no QID; the 326 QID rows are the <0.90 duplicate
  representation, already removed by the floor.
* **RaceTrac:** rows named only by location ("Polo", "Lake Mary") must match on brand name.
* **Target formats:** department_store / superstore storefronts; Target Optical is a department.
* **Walmart formats:** `supercenter` (department_store / superstore), `neighborhood_market`
  (grocery_store, storefront); pharmacy/grocery rows inside a Supercenter are departments.
* **7-Eleven formats:** `store` (convenience_store), `fuel_only` (gas_station "7-Eleven Fuel").
* **Co-brand 7-Eleven/Speedway:** in this release the combined name was observed only on Western
  Union rows; storefront co-brand evidence is **unmeasured** (§10).

## 8. De-duplication, confidence, query service, coverage, versioning

Unchanged from the Phase 1 design, amended by the locked decisions:

* **De-duplication:** same chain + same format; pair-specific radii (store+department 150 m,
  store+fuel 120 m, same-category duplicate 60 m with matching normalised address, fast food /
  coffee 30 m with matching address); deterministic union order; diameter guard; representative
  = storefront > department > fuel, then confidence, source count, `source_ref`. Different
  chains are never merged (decision 3b). A co-branded site produces one site with two
  memberships (decision 3c). Fuel-only sites are their own format (decision 3d).
* **Confidence:** extraction floor stays `>= 0.90` (`overture_places.confidence_min`, recorded
  in the ledger). No branded lower threshold.
* **Query service:** `nearest / within / count / list (brandKey, lat, lng, float radius ≤ 25,
  ?categoryKey, ?requireStorefront)`; geodesic; reads de-duplicated sites only; deterministic
  ordering; own default-off gate `POI_BRAND_SEARCH_ENABLED` and own pin
  `POI_BRAND_SEARCH_CORPUS_VERSION`; results expose `format_key` and `status_unknown`; no Google
  path; not in `required_production_flags`.
* **Coverage:** `supported` · `unsupported_by_corpus` · `unknown_chain` · `unknown_coverage`
  (provisional chain, or point outside region) · `unavailable`. Only `supported` may produce
  "none within X".
* **Versioning:** `overture-2026-08-19.0-fl-r2`, partition `places_p_overture_2026_08_19_0_fl_r2`;
  ledger notes carry release, `taxonomy_map_version`, `extract_rule_version`, floor, status rule,
  category set, bbox, rejection tallies; loaded as `validated`, never `active`, v1 not
  superseded. Re-pinning Location DNA later rotates `CorpusSurface::token()` → `fetchVersion` →
  every row's `pois_fetch_version` reads stale and every POI tile key rotates; rollback is a
  re-pin to v1.
* **Backward compatibility:** `CorpusPoiCategoryMap` gets an explicit 7-key Location DNA
  constant (test-pinned) instead of deriving from the whole corpus taxonomy; `OvertureCategoryMap`
  (v1) unchanged; Buyer/Tenant matching, Ask AI, Smart Tags untouched.

## 9. Chain promotion — validation packet (decision 6)

A chain moves `provisional → supported` only with a recorded packet containing:

* **A. Corpus evidence** — identity rules validated; allowed categories confirmed; exclusion
  rules exercised; de-duplication complete; no known systematic sub-entity contamination.
* **B. Coverage evidence** — where a trustworthy reference count exists for the **same
  geographic scope**: reference count, corpus de-duplicated count, difference, reference
  source and date, explanation of any material difference. No universal percentage. An
  unexplained substantial gap keeps the chain provisional.
* **C. Fixture evidence** — St. Pete (27.788945, −82.735144) and Tampa (27.9506, −82.4572):
  nearest results verified manually for chains expected there.
* **D. Sampling** — manual sample across several regions sufficient to detect wrong-chain
  aliases, embedded-service false positives, duplicates, wrong formats, closed locations.

Ambiguous evidence → remains `provisional`. Never promoted to make "none nearby" available.

## 10. Open items carried into validation (not blockers for PR 1)

1. **CVS in `shopping`:** 131 "CVS Pharmacy" + 64 "CVS Beauty" rows are classified `shopping`,
   which is never-import. Whether they duplicate `pharmacy` storefronts or are real stores
   lost to misclassification is unmeasured (coordinates not retained in PR 0). Resolve in the
   CVS validation packet; a narrow brand-scoped rescue would be a separate decision.
2. **7-Eleven/Speedway storefront co-branding:** unmeasured in storefront categories.
3. **Weak-identity chains** (Speedway, Walgreens, Walmart, Whole Foods) depend on name patterns
   and category roles; their packets need heavier sampling.

## 11. PR sequence

PR 1 taxonomy map v2 (pure) + explicit 7-key Location DNA constant · PR 2 chain registry +
matcher (pure) · PR 3 v2 extraction recipe, normalizer, accounting (offline, refuses
production) · PR 4 spatial migrations, guarded seeders, attach/activate split (approval) ·
PR 5 de-duplication / site builder · PR 6 brand query service (default-off) · Ops: extract r2,
load `validated`, validation packets (approval) · Later: Location DNA re-pin, new sections,
Ask AI / UI consumers — each a separate decision.
