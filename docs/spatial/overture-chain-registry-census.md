# Overture chain registry — real-data validation census

**Status:** AUDIT RECORD (aggregate only). No rows, coordinates or addresses are published here;
source IDs and business names appear only where a named example is the evidence.

| | |
|---|---|
| Source | Overture Maps `2026-08-19.0`, theme `places`, type `place` — anonymous read-only DuckDB against the public `overturemaps-us-west-2` bucket |
| Region | Florida bounding box `[-87.63, 24.40, -79.97, 31.00]`, containment on the row bbox (identical to corpus-v2 PR 0) |
| Eligibility | `taxonomy.primary` in the 16 corpus-v2 import tokens · `confidence >= 0.90` · `operating_status` = `open` or NULL |
| Registry | §2–§7: `chain-registry-v1` (rule hash `09a1c24c0ace9a71…`), `chain-name-norm-v1`, `chain-match-precedence-v1`, `overture-taxonomy-v2.0` — the merged code of PR #191, unmodified. §8–§9: final `chain-registry-v2` (rule hash `b5920a1c73199a0d…`) |
| Run | 2026-09-22; final v2 and reproduction 2026-09-23 (§10) |
| Written | nothing: no table, no corpus, no production or spatial database |

Design: `overture-chain-registry-design.md`. Corpus design: `overture-corpus-v2-design.md`.

## 1. Row accounting — reconciled with PR 0

| Set | Rows | Use |
|---|--:|---|
| **Eligible (16 tokens, ≥0.90, open/NULL)** | **52,566** | the census. Equals PR 0's eligible total exactly (FL 51,610 · GA/AL 941 · other 15) |
| Supplementary: ≥0.90, open/NULL, **not** an import token, name/brand resembling a chain or carrying any brand QID | 2,800 | before-filter category census only (e.g. CVS in `shopping`) |
| Supplementary: same, `taxonomy.primary` NULL | 162 | same |
| **Total passed to the matcher** | **55,528** | every supplementary row is refused at R0 (`category_not_imported`) and produces no membership |

The 55,528 figure is **not** an eligible-row count. Every eligible-row statistic below is over the
52,566.

A second read-only extract of chain-named rows at **any** confidence, status or category (19,053
rows, 5,648 below the floor) was used only to explain orphan department and fuel rows (§5). It
changes no eligibility.

## 2. Registry v1 results (eligible rows)

Matched **10,880** rows (10,880 memberships: 10,305 storefront, 334 department, 241 fuel). Zero
ambiguous, zero co-brand, zero `conflicting_wikidata`, zero `malformed_wikidata`, zero
`exclusion_wikidata`. Row refusals: `closed_name` 31 (all genuinely closed), `exclusion_pattern` 146
(all non-chain rows — catering, ATMs, clinics). Global exclusion patterns rejected **zero**
chain-identified eligible rows: the import allowlist removes embedded services first.

| Chain | Candidates | Matched | Storefront | Dept | Fuel | Dropped (reason) |
|---|--:|--:|--:|--:|--:|---|
| shell | 1171 | 1171 | 1150 | 21 | 0 | — |
| seven_eleven | 1169 | 1143 | 1095 | 0 | 48 | closed_name 25 · category_not_allowed 1 |
| starbucks | 1050 | 1050 | 1050 | 0 | 0 | — |
| mcdonalds | 973 | 958 | 958 | 0 | 0 | category_excluded 15 |
| publix | 940 | 937 | 873 | 64 | 0 | category_not_allowed 2 · chain_exclusion 1 |
| walgreens | 855 | 854 | 854 | 0 | 0 | category_not_allowed 1 |
| cvs | 733 | 676 | 676 | 0 | 0 | category_not_allowed 41 · brand_conflict 13 · chain_exclusion 3 |
| wendys | 613 | 613 | 613 | 0 | 0 | — |
| burger_king | 599 | 557 | 557 | 0 | 0 | category_excluded 42 |
| walmart | 529 | 522 | 304 | 218 | 0 | category_not_allowed 6 · chain_exclusion 1 |
| taco_bell | 505 | 505 | 505 | 0 | 0 | — |
| winn_dixie | 353 | 344 | 313 | 31 | 0 | category_not_allowed 8 · chain_exclusion 1 |
| wawa | 318 | 318 | 318 | 0 | 0 | — |
| chick_fil_a | 309 | 309 | 309 | 0 | 0 | — |
| racetrac | 302 | 302 | 260 | 0 | 42 | — |
| aldi | 273 | 273 | 273 | 0 | 0 | — |
| speedway | 169 | 169 | 18 | 0 | 151 | — |
| target | 131 | 126 | 126 | 0 | 0 | category_excluded 3 · chain_exclusion 2 |
| whole_foods | 34 | 32 | 32 | 0 | 0 | brand_conflict 2 (corporate office, distribution centre) |
| trader_joes | 21 | 21 | 21 | 0 | 0 | — |

**Identity sources.** Own-QID identity is ≤ 13 rows per chain. Walgreens is 826 name / 27 brand /
1 QID; 7-Eleven 1,100 name / 40 brand; Speedway 161 name / 8 brand; Walmart 271 name / 251 brand.

**Separated brand counts.** Foreign STORE/CHAIN conflict: 15 (11 "CVS Photo" with brand "CVS
Health", 2 "CVS Pharmacy" with brand "Target", 2 Whole Foods offices). Expected fuel-brand
diagnostic: 0. Unexpected fuel-brand conflict: 0. The 31 Mobil and 3 Arco eligible rows carry no
chain name and correctly create nothing; the fuel-diagnostic paths were not exercised by real data.

## 3. Category × chain (before the registry's category rules)

| Chain | Allowed and matched | Rejected as wrong category | Removed by the import allowlist |
|---|---|---|---|
| CVS | pharmacy 633 · drugstore 43 | convenience_store 40 · coffee_shop 1 | shopping 166 · doctors_office 19 · atm 13 |
| McDonald's | fast_food 912 · burger 46 | coffee_shop 12 · restaurant 3 | — |
| Starbucks | coffee_shop 951 · cafe 99 | — (**zero** restaurant rows) | — |
| Burger King | burger 311 · fast_food 246 | restaurant 42 | beverage_supplier 61 |
| Walmart | pharmacy 173 (dept) · grocery 147 (~102 Neighborhood Market, 45 dept) · department_store 132 · superstore 70 | gas_station 3 · restaurant 3 | photo 121 · eyewear 117 · bakery 111 · oil change 61 · … |
| Publix | grocery 873 · pharmacy 64 (dept) | drugstore 1 · restaurant 1 | liquor 17 · beer/wine 19 |
| Winn-Dixie | grocery 313 · pharmacy 31 (dept) | drugstore 8 | shopping 32 · liquor 27 |
| Speedway | gas 151 (fuel) · convenience 18 | — | — |
| RaceTrac | convenience 260 · gas 42 (fuel) | — | — |
| Shell | gas 1150 (station) · convenience 21 (dept) | — | NULL taxonomy 112 · b2b_mining 24 |

## 4. What the lost rows are

* **CVS `shopping` (166).** 150 "CVS Pharmacy" / "CVS" / "CVS Pharmacy y más" / 3 "inside Target
  Store" — every one > 150 m from any matched CVS (median ≈ 3 km) and none within 150 m of each
  other: **real storefronts**. 13 CVS Beauty, 2 specialty pharmacy, 1 MinuteClinic. No false
  positives. (PR 0 recorded 195 with a looser name test.)
* **CVS `convenience_store` (40).** All "CVS Pharmacy"-shaped, all > 150 m from any matched CVS:
  real storefronts.
* **Burger King `restaurant` (42).** 41 distinct "Burger King" rows (one ≤ 60 m duplicate).
* **McDonald's `coffee_shop` / `restaurant` (15).** 13 distinct.
* **Walmart `grocery_store` department (45).** 40 have no Walmart storefront within 150 m; ~27 are
  plain "Walmart" / "Walmart Supercenter" / "Wal-Mart" (nearest storefront median ≈ 8 km) — real
  stores; the rest are pickup/delivery, distribution centres and three brand-field misattributions.
* **Fuel-only sites.** Fuel rows with no same-chain store within 120 m: Speedway 150 / 151,
  RaceTrac 36 / 42, 7-Eleven 47 / 48. Under v1's `fuel_only: false`, Speedway lost ≈ 89 % of its
  locations. For Speedway, 80 of those 150 have a Speedway Western Union / ATM counter nearby —
  corroborating a real store whose storefront row simply is not in Overture.
* **Department-only sites.** Publix pharmacy 48 / 64 and Winn-Dixie pharmacy 29 / 31 have no
  same-chain storefront within 150 m (most > 1 km). Nearby rows are service counters below the
  floor, not hidden storefronts: identity coverage is incomplete there.

## 5. False positives found

* **Brand-field misattribution (≈ 34 rows):** 13 "… Community Pharmacy" as Walgreens; "Victoria
  Grocery", "Sabores Market Kendall", "Asian-Mart (Edgewater)" as Walmart; "KFC" as Taco Bell; "Hot
  Chick'n" as Chick-fil-A; "Burger and Philly", "Whopper Bar" as Burger King; "Jenny's Your Friendly
  Pharmacy", "Omnicare" as CVS; "RaceWay" ×5 as RaceTrac; one "iFixandRepair … Walmart" tenant.
* **Sub-entities / offices reached by name prefix:** "CVS Photo" ×6 (no brand); "Walmart DC",
  "Walmart Warehouse Dc"; "Publix Downtown Office", "Publix Grocery Warehouse"; "Walgreens District
  Office"; "RaceTrac Support Center", "Racetrac Petroleum".
* **Shell exact-only** refuses "Shell Island", "Bombshell Fitness", "Stuffed Shell", "Halfshell
  Oyster House" (correct) and misses ≈ 9 dealer-named stations (§7).

## 6. Co-branding and dedup preview

No 7-Eleven / Speedway same-row evidence exists in eligible rows; the combined name appears only on
Western Union rows below the floor. 9 Speedway rows lie within 120 m of a 7-Eleven — proximity
only, correctly not co-branded. The only multi-chain rows are 2 "CVS Pharmacy" with brand "Target";
both candidates were dropped.

Raw pairs (counts only, nothing grouped): store + department ≤ 150 m — Walmart 137, Publix 16,
Winn-Dixie 3, Shell 2, Target 0. Store + fuel ≤ 120 m — RaceTrac 6, 7-Eleven 1, Speedway 1, Wawa 0
(Wawa has no eligible gas rows). Same chain + format ≤ 60 m — 57 pairs, 55 with matching house
number + postcode. Walmart `store` / `supercenter` storefronts at one site: 7 cross-format pairs, so
the materializer must merge storefront formats, not only identical ones.

## 7. Pre-implementation measurements for chain-registry-v2 (2026-09-22)

**Shell `<place> Shell` suffix (gas_station only) — NOT ADOPTED.** Eleven eligible gas rows end in
" shell"; two already match by brand. Of the nine a suffix rule would newly admit, two duplicate an
already-matched plain "Shell" row within 80 m (no recovery), "HAVANA Shell" carries the contradicting
Arco brand and QID, "Pumpkin Shell" is ambiguous as a business name, and five are plausible but
unverifiable without external data. The sample is not clean, so Shell stays exact-only (decision 8).

**Exclusion patterns, measured on every chain-identified row before adoption:**

| Pattern | Chain rows hit | All hits false positives? | Scope adopted |
|---|--:|---|---|
| `office(s)` | 8 | yes (corporate / district / area / dispatch offices) | global |
| `warehouse(s)` | 3 | yes | global |
| `distribution center/centre` | 7 | yes | global |
| `support center/centre` | 2 | yes (RaceTrac) | global |
| `career(s)` | 2 | yes (Whole Foods brand field) | global |
| bare `dc` | 3 | yes, all Walmart DCs | **Walmart only** ("clearly a distribution centre") |
| `photo` | CVS Photo only among CVS rows | yes | **CVS only** (Walmart Photo Center is already global) |
| `pickup` / `delivery` | 12 (11 Walmart, 1 Burger King) | yes | **Walmart only** (decision 4) |
| `specialty` | 17 | **no** — "Publix Pharmacy at Nemours Children's Specialty Care" is a real pharmacy | **CVS `shopping` rescue only** |
| `racetrac petroleum` | 1 | yes | **RaceTrac only**; no global `petroleum` (decision 5) |

## 8. v1 → v2 comparison

Same 55,528-row input, same eligibility. v1 is `chain-registry-v1` (rule hash `09a1c24c0ace9a71…`,
`chain-match-precedence-v1`); final v2 is `chain-registry-v2` (rule hash `b5920a1c73199a0d…`,
`chain-match-precedence-v2`). The only change to the input is that the raw `taxonomy.primary` token
is passed as `sourceCategory`, which the matcher reads only for a row with no canonical key.

**The 55,528 are the rows passed through the matcher, not the corpus.** PR 0's eligible corpus is
52,566 rows and stays 52,566; the other 2,962 are the supplementary rows of §1, every one refused at
R0 unless a chain's source-category rescue claims it (in v2: CVS in `shopping`, 150 rows). A
membership count below is never a statement about corpus size.

| | v1 | final v2 |
|---|--:|--:|
| Memberships | 10,880 | **11,082** |
| — from eligible rows | 10,880 | 10,932 |
| — rescued from `shopping` (CVS only) | 0 | 150 |
| Storefront / department / fuel | 10,305 / 334 / 241 | 10,533 / 310 / 239 |
| Co-brand / ambiguous | 0 / 0 | 0 / 0 |
| Fuel-brand diagnostics | 0 | 0 |

| Chain | v1 | final v2 | Gained | Lost | Reclassified |
|---|--:|--:|--:|--:|--:|
| cvs | 676 | 860 | 192 | 8 | 0 |
| burger_king | 557 | 596 | 41 | 2 | 0 |
| mcdonalds | 958 | 973 | 15 | 0 | 0 |
| winn_dixie | 344 | 352 | 8 | 0 | 0 |
| walmart | 522 | 507 | 0 | 15 | 10 |
| walgreens | 854 | 840 | 0 | 14 | 0 |
| racetrac | 302 | 294 | 0 | 8 | 0 |
| publix | 937 | 933 | 0 | 4 | 0 |
| chick_fil_a | 309 | 307 | 0 | 2 | 0 |
| taco_bell | 505 | 504 | 0 | 1 | 0 |
| the other ten chains | — | — | 0 | 0 | 0 |

The other ten (Starbucks, 7-Eleven, Wawa, Shell, Speedway, Wendy's, Aldi, Target, Trader Joe's,
Whole Foods) have identical row-level memberships — role, format, method — in v1 and v2. Speedway's
change is at the site level only (fuel-only sites, below).

**Recovered legitimate rows (256 gained + 10 reclassified), all legitimate.** CVS: 150 `shopping`
storefronts (147 `store`, 3 `store_in_target`), 40 `convenience_store` "CVS Pharmacy" rows (39
`store`, 1 "inside Target Store" as `store_in_target`), and the 2 "CVS Pharmacy" rows with brand
"Target" now `store_in_target`. Burger King: 41 `restaurant` rows. McDonald's: 12 `coffee_shop` + 3
`restaurant`. Winn-Dixie: 8 `drugstore`. Walmart: 10 "Walmart Supercenter" `grocery_store` rows
reclassified from department to `supercenter` storefront.

**CVS `shopping` accounting (166 = 150 + 15 + 1).** 150 rescued; 13 CVS Beauty and 2 specialty
pharmacy refused as sub-entities (`chain_exclusion`); 1 MinuteClinic rejected by the global clinic
pattern. No other non-imported token produced a membership.

**Removed memberships (54: 53 false positives, 1 probable real).** Brand-field misattribution
(`brand_alias_uncorroborated`, 29): 13 Walgreens "… Community Pharmacy" / "St John's Pharmacy",
5 RaceTrac "RaceWay" ×3 / "Race Way" / "Raceway 6847", 4 Walmart (Victoria Grocery, Sabores Market,
Asian-Mart, the iFixandRepair tenant), 2 CVS (Jenny's, Omnicare), 2 Burger King ("Burger and
Philly", "Whopper Bar"), 1 Taco Bell ("KFC"), 2 Chick-fil-A ("Hot Chick'n", and "Cfaleesburgfl" —
the probable real one, §9). Sub-entities and logistics (25): 6 CVS Photo, 9 Walmart pickup /
delivery, 2 Walmart DCs, 4 Publix offices / warehouses, 1 Walgreens district office, 2 RaceTrac
Support Center, 1 Racetrac Petroleum. "Burger King Capital Holdings, Llc" is not in this list
because v1 never matched it (`restaurant` was not allowed); v2 now refuses it (§9).

**Remaining false positives: none identified** among the v1 → v2 differences. Rows v2 still refuses
that may be real: "Cfaleesburgfl" (§9).

**Fuel-only sites** (site-level proxy: a fuel membership with no same-chain storefront within
120 m). Speedway 150 and RaceTrac 35 lone fuel rows now classify as `fuel_only` with the visible
qualifier "Fuel only" instead of `unsupported_format`; 1 Speedway and 5 RaceTrac fuel rows sit
beside a same-chain store (`store_with_fuel`). 7-Eleven (47 fuel-only, 1 with store) is unchanged;
Wawa still has no eligible gas rows.

**Walmart grocery rows left `storefront_unconfirmed` — intended, not a matcher failure.** Walmart
`grocery_store` rows in final v2: 102 `neighborhood_market`, 10 `supercenter`, **21
`grocery_department`** (19 of them with no Walmart storefront within 150 m: 16 plain "Walmart", 1
"Wal-Mart", 1 "Walmart Miami", 1 "Wal Mart 551 Supercenter" — whose store number precedes the word,
so the anchored `supercenter` pattern does not select it). Per v2 decision 4 a plain or ambiguous
Walmart grocery row stays a department, i.e. `storefront_unconfirmed`, until the de-duplication
materializer decides whether it is a standalone site or a department of a Supercenter it sits in.
The first v2 run described these as "about 27"; that count predates the 10 Supercenter rows'
reclassification and is superseded by the 21 above.

**Performance.** 55,528 rows in 1.4 s of matcher time (≈ 40 k rows/s), ~1 MB peak. Per-row cost is
bounded by the registry (20 chains, their aliases and formats), never by the corpus.

## 9. Residual findings of the first v2 run, resolved (2026-09-23)

The first v2 run (rule hash `4fedf3232df819da…`, never shipped) produced 11,088 memberships and left
five findings. Final v2 differs from it by exactly seven rows, and by nothing else:

| Finding | Decision | Effect |
|---|---|---|
| "Burger King Capital Holdings, Llc" (`restaurant`) matched | Burger King-only exclusion `/^burger king capital holdings\b/`; no global `holdings` / `llc` | −1 |
| 5 "RaceWay" / "Race Way" / "Raceway 6847" convenience rows with brand "RaceTrac", no QID, matched RaceTrac | RaceTrac requires corroboration of a brand alias. Every other RaceTrac membership has "RaceTrac" / "Race Trac" in its name or the own QID, so nothing legitimate is lost. No fuzzy RaceTrac ↔ RaceWay rule | −5 |
| "CVS Pharmacy inside Target Store" in `convenience_store` resolved to `store` | `store_in_target` now covers `convenience_store` for format selection; Target remains a conflict there (`host_categories` = `pharmacy`, `drugstore`) | 1 reclassified |
| "Cfaleesburgfl" refused | Row: name "Cfaleesburgfl", brand "Chick-fil-A", brand QID **none**, `chicken_restaurant`, confidence 0.963. Its only candidate evidence is the brand alias; the name is opaque and Chick-fil-A's brand field is measured to be misattributed ("Hot Chick'n"). Refused as `brand_alias_uncorroborated`, which is the rule working, not a strong-identity defect. No alias invented | 0 (stays refused) |
| Walmart plain `grocery_store` rows stay departments | No change — intended (§8) | 0 |

## 10. Reproduction

Rebuilt 2026-09-23 after the original scratch artefacts were lost with the container: one anonymous
read-only DuckDB query against `release/2026-08-19.0/theme=places/type=place` (Florida box on the row
bbox, `confidence >= 0.90`, status `open` / NULL: 743,775 rows), then the §1 selection (eligible =
the 16 tokens; supplementary = any other token or NULL whose name or brand contains a chain token, or
which carries any brand QID). It reproduced 52,566 eligible and 2,800 + 162 supplementary rows
exactly, and the v1 and first-v2 runs reproduced the recorded rule hashes and every count above.
Nothing was written anywhere but a scratch directory; no extract, CSV or database file is committed.

Every v2 chain remains `provisional`.
