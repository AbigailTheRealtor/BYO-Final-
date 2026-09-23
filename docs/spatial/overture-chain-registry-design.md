# Overture chain registry — design (PR 2)

**Status:** APPROVED. Product decisions locked 2026-09-22 (§17); the real-data census decisions of
the same date are implemented as **`chain-registry-v2`** (§19), which supersedes decisions 3, 4, 11
and — for RaceTrac and Speedway — 6. Implemented by this PR as a
**pure, unreferenced** layer: the config, its reader/validator, the name normaliser, the matcher,
the site-classification contract and their tests (§18). No migration, table, import, extraction,
de-duplication, query service or activation. No runtime code calls any of it.

**Factual basis — the only one:** `docs/spatial/overture-corpus-v2-design.md` (PR 0 measured
findings, Overture `2026-08-19.0`, Florida box, 1,245,925 rows, run 2026-09-18) and the PR 1
crosswalk `App\Services\Spatial\OvertureTaxonomyMapV2` (`overture-taxonomy-v2.0`, 16 canonical
keys). Section references `v2§n` point into the v2 design.

**Evidence markers used throughout** (and carried by every rule in the config):

| Marker | Meaning |
|---|---|
| **M** | Measured in PR 0 and recorded in the v2 design (a count, QID or observed name). |
| **D** | A locked decision: v2§1 / v2§7, or the chain-registry decisions of 2026-09-22 (§17). |
| **P** | Proposed. Not measured. Must be confirmed by PR 3 extraction accounting before the chain can be promoted. Fail-closed until then. |

**A limit on the evidence, stated once.** PR 0 measured each chain's eligible-row total, own-QID
fill, contamination names and per-category *branded share*. It did **not** record a
chain × category matrix (for example, how many McDonald's rows sit in `restaurant` rather than
`fast_food_restaurant`). So most per-chain category allowances are **P**, set to the narrowest set
that is correct for the business. A named-chain row in any other imported category is reported as
`category_not_allowed` / `category_excluded` against that candidate chain, never dropped silently.
That report is how PR 3 tells us whether to widen, which would be a registry-version bump.

**Code map** (all in `app/Services/Spatial/ChainRegistry/`): `ChainRegistry` (the only reader of
`config/poi_chain_registry.php`, and its validator), `ChainNameNormalizer`, `ChainMatcher`,
`ChainMatchInput`, `ChainMatchResult`, `ChainMembership`, `ChainMatchReason`, `ChainRole`,
`ChainDefinition`, `ChainFormat`, `ChainSiteClassifier`, `ChainSiteClassification`,
`InvalidChainRegistry`, `UnknownChainKey`.

---

## 1. Registry structure

One versioned, declarative, `env()`-free config: `config/poi_chain_registry.php`. It is read only
by `ChainRegistry`, which works without a booted container (container config when bound, else the
file — the `LandlordScreeningPolicy::conf()` pattern), because the offline PR 3 extraction tooling
runs outside the app.

```
registry_version:     'chain-registry-v1'
normalizer_version:   'chain-name-norm-v1'        # must equal ChainNameNormalizer::VERSION
taxonomy_map_version: 'overture-taxonomy-v2.0'    # must equal OvertureTaxonomyMapV2::VERSION

global:
  excluded_categories:     { <category_key>: evidence }                 # shopping_center (decision 1)
  exclusion_wikidata_ids:  { <QID>: {label, evidence} }                 # SERVICE identities: reject the row
  exclusion_name_patterns: { <reason_key>: {pattern, evidence} }        # reject the row
  closed_name_patterns:    { <reason_key>: {pattern, evidence} }        # reject the row
  fuel_brands:             { <fuel_key>: {label, wikidata_ids[], names[], evidence} }   # DIAGNOSTIC ONLY

chains:
  <brand_key>:
    display_name
    aliases:                 [ {value, match: exact|prefix, evidence} ]  # names.primary, stored normalised
    brand_aliases:           [ {value, evidence} ]                       # brand.names.primary, exact only
    own_wikidata_ids:        { <QID>: evidence }                         # positive evidence only
    department_wikidata_ids: { <QID>: {label, evidence} }                # identity that is only ever a department
    exclusion_wikidata_ids:  { <QID>: {label, evidence} }                # chain-local; drops this candidate
    exclusion_name_patterns: { <reason_key>: {pattern, evidence} }       # chain-local; drops this candidate
    fuel_brands:             [ <fuel_key> ]                              # EXPECTED co-located fuel (diagnostic)
    allowed_categories:      { <category_key>: {role, evidence} }        # role ∈ storefront|department|fuel
    excluded_categories:     { <category_key>: evidence }                # chain-local; global list also applies
    formats:
      <format_key>: {categories[], name_patterns[]?, role, user_visible?, label?, evidence}
    default_format:          <format_key>                                # the chain's primary default format
    fuel_sites:              {store_with_fuel: bool, fuel_only: bool, evidence} | absent
    co_brands:               { <partner_key>: {evidence_rule: co_brand_evidence, compound_names[], evidence} }
    validation_status:       provisional                                 # the ONLY value config may hold
    notes:                   [ ... ]                                     # never read by the matcher
```

**Why `aliases` and `brand_aliases` are separate lists.** They are compared against two Overture
fields of different reliability. `brand.names.primary` is a curated brand field and is matched by
exact normalised equality only. `names.primary` is free text and is where the contamination lives
("PUBLIX #1029" on a Western Union counter), so it gets exact/prefix modes and is read only after
every row-level exclusion.

**Why fuel brands are a separate catalogue and not chain entries.** A fuel brand's identity (Mobil,
Arco) is not a store chain's identity. Keeping them in `global.fuel_brands` — never in any chain's
own / department / exclusion IDs, which the loader enforces — is what lets the matcher treat them as
context rather than as either identity or conflict (§7).

**`validation_status` is not a rule.** The config can only declare `provisional`. Promotion lives
in a separate validation record (§14), so a promotion never changes a membership, and a rule change
never carries an old promotion forward.

## 2. Required vs optional

Unknown fields anywhere are a load error, so a typo can never silently disable a rule.

| Field | Req. | Rule |
|---|---|---|
| `brand_key` | ✅ | `^[a-z][a-z0-9_]*$`, stable, never a display name, never reused after removal. |
| `display_name` | ✅ | Presentation only. Never matched against. |
| `aliases` | ✅ | ≥1 entry, unique. Each has `match` and `evidence`. Stored already normalised; never QID-shaped. |
| `brand_aliases` | ✅ | May be `[]`, but must be present, so an absent key cannot be mistaken for "not thought about". |
| `own_wikidata_ids` | ✅ | May be `{}` (Walmart, Whole Foods, Speedway). Positive evidence only; never required to match. |
| `allowed_categories` | ✅ | ≥1 entry. Keys ⊂ the 16 canonical v2 keys. Roles ⊂ storefront/department/fuel. |
| `formats` | ✅ | ≥1. Every format's categories ⊂ `allowed_categories`. The `fuel` role may only sit on categories whose own role is `fuel` — a name pattern never creates fuel identity. **Every allowed category has exactly one format without name patterns** (its default), and that default's role **equals the category's role**: a category alone can never upgrade a department into a storefront. Only a format with an explicit name pattern may carry another role (Walmart `neighborhood_market`). |
| `default_format` | ✅ | Names a format without name patterns. |
| `validation_status` | ✅ | Must equal `provisional`. |
| format `user_visible` / `label` | optional | Default `false` / `null`. A label is required exactly when `user_visible` is true. |
| `excluded_categories` | optional | Defaults `{}`. Allowed ∩ (chain-excluded ∪ global-excluded) must be empty. |
| `department_wikidata_ids`, `exclusion_wikidata_ids` | optional | QID roles are exclusive across the whole registry (§4.2). |
| `exclusion_name_patterns` | optional | `/…/` with no modifiers; must compile; must **not** match the empty string or any neutral probe (`a`, `z`, `1`, `store`, `zzz 1`, `the store 12`, `abcdefghijklmnop`, `grocery`) — a pattern that does matches almost everything. The same rule applies to closed-name and format patterns. No exclusion or closed-name pattern may match a fuel-brand name (§7). |
| `fuel_brands` | optional | Keys of `global.fuel_brands`. |
| `fuel_sites` | conditional | Required **exactly when** a category has the `fuel` role. |
| `co_brands` | optional | Symmetric: the partner must declare the same pair with identical `compound_names`. |
| `notes` | optional | Free text. Never read by the matcher. |

Every evidence value is `M`, `D` or `P`, optionally followed by `: <note>`. An entry without
evidence is a load-time error.

## 3. The 20 initial chains

Registry-wide:

* Every chain starts `provisional` (v2 decision 6); status is per chain (decision 12).
* No chain allows `shopping_center` (decision 1; it is in `global.excluded_categories`).
* Categories are canonical v2 keys only. A never-import token such as `bakery`, `atm` or
  `eyewear_store` can never appear here; a row carrying one is refused as `category_not_imported`.

| brand_key | display_name | Eligible rows (M) | Own QID (M) | Identity strength |
|---|---|---|---|---|
| `publix` | Publix | 941 | Q672170 | name + QID (1.3%) |
| `starbucks` | Starbucks | 1,051 | Q37158 | name + QID (1.0%) |
| `walgreens` | Walgreens | 855 | Q1591889 (thin) | **weak** — brand name on 28/855 |
| `cvs` | CVS | 735 | Q2078880 | name + QID (0.3%) |
| `walmart` | Walmart | 530 | — | **weak** — no QID; departments dominate |
| `target` | Target | 134 | Q1046951 (thin) | name |
| `aldi` | Aldi | 273 | Q41171672 | name + QID (4.8%) |
| `winn_dixie` | Winn-Dixie | 353 | Q1264366 (thin) | name |
| `whole_foods` | Whole Foods Market | 36 | — | **weak** — no QID |
| `trader_joes` | Trader Joe's | 21 | Q688825 | name + QID (9.5%) |
| `seven_eleven` | 7-Eleven | 1,169 | Q259340 | name + QID (0.3%) |
| `wawa` | Wawa | 318 | Q5936320 (0% on eligible rows) | name |
| `racetrac` | RaceTrac | 303 | Q735942 | name + **brand** (location-only names) |
| `speedway` | Speedway | 172 | — | **weak** — brand name on 9/172 |
| `shell` | Shell | 1,200 | Q110716465 | name + QID (0.3%) |
| `mcdonalds` | McDonald's | 976 | Q38076 | name + QID (0.3%) |
| `taco_bell` | Taco Bell | 505 | Q752941 | name + QID (0.8%) |
| `chick_fil_a` | Chick-fil-A | 309 | Q491516 | name + QID (2.9%) |
| `wendys` | Wendy's | 606 | Q550258 | name + QID (0.8%) |
| `burger_king` | Burger King | 599 | Q177054 | name + QID (0.5%) |

### 3.1 Per-chain entries

Alias values are shown **normalised** (§4.1). `x` = exact, `p` = prefix (§12.3). S = storefront,
Dp = department, F = fuel. The config is the authority; this is its readable summary.

**publix** — aliases `publix` p (M: "PUBLIX #1029" seen, on WU rows) · brand `publix` (P) · own
Q672170 (M, 36) · chain pattern `liquors?` (M: 22 liquor rows; decision v2-5) · categories
grocery_store S, pharmacy Dp · formats `supermarket` (grocery_store, S, default),
`pharmacy_department` (pharmacy, Dp) · GreenWise not aliased (decision 9).

**aldi** — `aldi` p · brand `aldi` · own Q41171672 (M, 59) · grocery_store S · format `store`.

**winn_dixie** — `winn dixie` p · brand `winn dixie` · own Q1264366 (M, 1 — thin) · chain patterns
`liquors?`, `wine and spirits` (M) · grocery_store S, pharmacy Dp · formats `store`,
`pharmacy_department`.

**whole_foods** — `whole foods` p · brands `whole foods market`, `whole foods` · no own QID (M) ·
grocery_store S · format `store`.

**trader_joes** — `trader joes` p · brand `trader joes` · own Q688825 (M, 6) · grocery_store S ·
format `store`.

**walmart** — aliases `walmart` p (M), `wal mart` p (P) · brand `walmart` · no own QID (M) · chain
patterns `vision`, `bakery`, `health` (M), `auto care`, `tires?` (P) · categories superstore S,
department_store S, **grocery_store Dp**, pharmacy Dp (all D: v2§7 — "pharmacy/grocery rows inside
a Supercenter are departments") · formats: `neighborhood_market` (grocery_store, pattern
`^neighborhood market`, **S**, visible "Neighborhood Market"; v2 decision 3a), `supercenter`
(superstore|department_store, pattern `^supercenter`, S, visible "Supercenter"), `store`
(superstore|department_store, S, default), `grocery_department` (grocery_store, Dp),
`pharmacy_department` (pharmacy, Dp). A bare "Walmart" grocery row is therefore a **department**,
never a Neighborhood Market by category alone. No gas_station (decision 7).

**target** — aliases `target` **x**, `target store` **x** · brand `target` · own Q1046951 (M, 1 —
thin) · department QID Q19903688 Target Optical (M, 4) · chain pattern `pharmacy` (P: a pharmacy in
Target is CVS) · categories department_store S, superstore S · excluded pharmacy, drugstore (D: v2
decision 3b), grocery_store (P) · format `store`.

**walgreens** — `walgreens` p (M: "ATM Walgreens" / "Labcorp at Walgreens" seen; both must fail) ·
brand `walgreens` (M: 28/855) · own Q1591889 (M, 2 — thin) · pharmacy S, drugstore S (drugstore is
0.0% branded, so name-only) · format `store`.

**cvs** — `cvs` p · brands `cvs pharmacy` (M, on "Minute Clinic" rows), `cvs` · own Q2078880 (M, 5)
· chain pattern `beauty` (M: "CVS Beauty" 64) · pharmacy S, drugstore S · formats `store` (default),
`store_in_target` (pattern `(inside|at|in) target`, S; v2 decision 3b). The 195 CVS `shopping` rows
are not rescued (decision 11).

**starbucks** — `starbucks` p · brand `starbucks` · own Q37158 (M, 41) · coffee_shop S, cafe S
(`cafe` is its own key, v2 decision 1) · excluded restaurant (decision 4) · format `store`. Licensed
counters inside Target/Publix are Starbucks memberships at their own site, never merged with the
host (v2 decision 3b); licensed-store variants are not aliased (decision 9).

**seven_eleven** — aliases `7 eleven` p (M), `7eleven` p (M: "ATM 7ELEVEN-FCTI" seen; must fail),
`seven eleven` p (P) · brand `7 eleven` · own Q259340 (M, 12) · **fuel_brands [mobil]** (M: Mobil
Q109676002 on 73 "7-Eleven" gas rows, all <0.90) · convenience_store S, gas_station F (D: v2§7) ·
formats `store` (S, default), `fuel` (F) · fuel_sites store_with_fuel ✅, **fuel_only ✅** (v2
decision 3d) · co_brands `speedway`, compounds `7 eleven speedway`, `speedway 7 eleven` (D: v2
decision 3c; the combined name was observed only on WU rows).

**wawa** — `wawa` p · brand `wawa` · own Q5936320 (M, 326 rows, all <0.90, so 0 eligible) ·
convenience_store S, gas_station F · formats `store`, `fuel` · fuel_sites store_with_fuel ✅,
fuel_only ❌ (decision 6).

**racetrac** — `racetrac` p, `race trac` p · brand `racetrac` (M: identity for location-only names
"Polo", "Lake Mary" — in v2 only with corroboration, §19.1) · own Q735942 (M, 224) · chain pattern
`truck` (M) · convenience_store S, gas_station F · formats `store`, `fuel` · fuel_sites
store_with_fuel ✅, fuel_only ❌ in v1 (✅ in v2, §19 decision 3).

**speedway** — `speedway` **x** (M: "Daytona International Speedway" and 68 race-track rows must
fail) · brand `speedway` (M: 9/172) · no own QID · convenience_store S, gas_station F · formats
`store`, `fuel` · fuel_sites store_with_fuel ✅, fuel_only ❌ · co_brands `seven_eleven` (symmetric).

**shell** — `shell` **x** · brand `shell` · own Q110716465 (M, 22) · **fuel_brands []** · gas_station
**S** (for Shell the station is the storefront), convenience_store Dp (the station shop) · formats
`station` (S, default), `station_store` (Dp). An Arco QID on a Shell row (M, 1 row) is a
`fuel_brand_conflict` **diagnostic** and changes nothing (§7, decision 5).

**mcdonalds** — `mcdonalds` p · brand `mcdonalds` · own Q38076 (M, 31) · fast_food_restaurant S,
burger_restaurant S · excluded coffee_shop, cafe (decision 3), restaurant (decision 4) · format
`restaurant`.

**taco_bell** — `taco bell` p · own Q752941 (M, 6) · fast_food_restaurant S, taco_restaurant S (the
10 `mexican_restaurant` rows are not imported, v2 decision 5) · excluded restaurant · `restaurant`.

**chick_fil_a** — `chick fil a` p · own Q491516 (M, 27) · fast_food_restaurant S, chicken_restaurant
S · excluded restaurant · `restaurant`.

**wendys** — `wendys` p · own Q550258 (M, 9) · fast_food_restaurant S, burger_restaurant S ·
excluded restaurant · `restaurant`.

**burger_king** — `burger king` p · own Q177054 (M, 9) · fast_food_restaurant S, burger_restaurant
S (the 85 `beverage_supplier` rows are not imported) · excluded restaurant · `restaurant`.

## 4. Aliases

### 4.1 Normalisation (`chain-name-norm-v1`), applied identically to aliases and to row values

1. Unicode NFKC. Lower-case.
2. Strip one trailing store number of the measured shape `\s*#\s*\d+$` (before step 5, while the
   `#` is still visible): `publix #1029` → `publix`, `7-eleven/speedway #46807` → `7-eleven/speedway`.
3. Delete `’` `‘` `'`: `wendy's` → `wendys`.
4. `&` → ` and `.
5. Every other non-letter, non-digit character → a space: `7-eleven` → `7 eleven`.
6. Collapse whitespace and trim; nothing left → no name.

Nothing else: no stemming, no stop-word removal, no edit distance, no transliteration beyond NFKC.
`7eleven` and `7 eleven` stay distinct strings, which is why both are aliases. NFKC needs PHP
`intl`; without it the normaliser **throws** rather than silently behaving differently (the spatial
CI workflow installs `intl`).

### 4.2 Integrity rules (load-time, tested in `ChainRegistryValidationTest`)

* No identity string (name alias or brand alias) belongs to two chains — alias collision.
* No `prefix` alias of one chain word-prefixes another chain's identity string, or a compound name
  of a pair it is not part of.
* Compound names are unique to one pair and never equal a single chain's alias.
* No alias is QID-shaped. The v1 place normaliser writes the brand **QID** into `brand` when
  `brand.names` is absent, so the matcher reads a QID-shaped brand *name* as the row's **brand
  QID** (never as a name): it then meets R1 and R5 like any other QID. If the row's QID field
  holds a different QID, the row is refused as `conflicting_wikidata` — unless exactly one of the
  two is a fuel brand, in which case the other is the row's QID and the fuel brand is a diagnostic
  (§7: a fuel ID never removes store identity). A malformed one is `malformed_wikidata`. (Discarding it instead — the first implementation — let a Western Union
  counter named "PUBLIX #1029" with its QID in `brand` match Publix; the reviewer caught it.)
* Every alias equals its own normalisation (stored already normalised).
* Wikidata roles are exclusive across the whole registry: one QID is a global exclusion, a fuel
  brand, one chain's own ID or one chain's department ID — never two of those. A chain-local
  exclusion QID may not be any of them either.
* A fuel-brand name never equals any chain identity string, and belongs to one fuel brand.
* No global or chain exclusion pattern, and no closed-name pattern, matches a fuel-brand name.

Consequence worth stating: in a valid registry, **two undeclared chains cannot both hold name-only
identity on one row**, and two chains with name + brand evidence on one row meet R5 (a foreign
brand). So an undeclared multi-chain match cannot survive to R8; R8 ambiguity arises only between
declared co-brand partners the row does not evidence (§11).

### 4.3 How aliases grow

PR 3's accounting emits, per chain, the top normalised names that matched and a **near-miss
report**: rows whose normalised name *contains* an alias but did not match. Near misses are for
human review only. They are never a match route. A new alias is a registry-version bump.

## 5. Own Wikidata IDs

As in §3, all measured (v2§6). An own QID is **positive evidence only**, never required (fill
≤ 9.5% everywhere), and never sufficient on its own: the category gate (R6) and format resolution
(R7) still apply. Thin QIDs (Walgreens, Target, Winn-Dixie: 1–2 rows) are listed; no rule depends
on them.

`department_wikidata_ids` (Target Optical Q19903688): a row carrying one can only ever produce a
**department** membership for that chain. Target declares no department format, so such a row ends
as `unsupported_format` — never a storefront.

## 6. Exclusion IDs and patterns

**Global service-exclusion QIDs** — applied before any identity rule; the row is rejected. All
measured (v2§6):

| QID | Entity | Measured on |
|---|---|---|
| Q861042 | Western Union | 2,948 rows ("PUBLIX #1029", "7-ELEVEN/SPEEDWAY #46807", "Walmart Money Center") |
| Q857063 | Citibank | 410 (ATM, "ATM Walgreens") |
| Q4835981 | BMO | 158 (ATM) |
| Q38928 | PNC Bank | 29 (ATM) |
| Q5835668 | Santander | ATM |
| Q59773555 | Electrify America | EV, Walmart 13 |
| Q7271456 | Quest Diagnostics | lab |

The unrelated-tenant QIDs PR 0 saw (Dutch Bros, CubeSmart, Hertz, Banfield, Econo Lodge, Wetzel's
Pretzels) were **not recorded as IDs**, so they are not listed. They are caught by the foreign
STORE/CHAIN rule (R5), which drops a candidate carrying any brand identity that is not its own.

**Global exclusion name patterns** — normalised text, matched **anywhere** in the name or the brand
(rejecting on an infix is the safe direction; it is never a match route): `atm`, `western union`,
`money center`, `money transfer`, `fcti`, `ev charg…`, `recharge`, `lockers?`, `clinics?`,
`minute ?clinic`, `labcorp`, `village medical`, `quest diagnostics`, `optical`, `photo center`,
`catering`, `real estate`, `charities`, `ronald mcdonald`. Closed names: `(permanently )?closed`
(v2§4). Chain-local patterns are listed per chain in §3.1.

## 7. Fuel-brand handling (decision 5 — corrected)

**Fuel identity is not store-chain identity.** A fuel-brand QID or name (the `global.fuel_brands`
catalogue: Mobil Q109676002, Arco Q304769) is **diagnostic context only**:

* It **never creates** a membership. A row named "Mobil" with Mobil's QID matches no chain
  (`no_chain`).
* It **never removes** one. It is not a service exclusion (R1), not a foreign STORE/CHAIN identity
  (R5), and the loader refuses any exclusion or closed-name pattern that would match a fuel-brand
  name — patterns also read the brand field, so this is what stops a pattern becoming a back door. "7-Eleven" + Mobil stays a 7-Eleven membership; "Shell" + Arco
  stays a Shell membership.
* Other chain evidence and the category / format rules still decide membership exactly as they
  would without it. A fuel brand cannot rescue a row those rules refuse.
* It is **recorded** per candidate chain as a diagnostic: `fuel_brand_expected` when the chain lists
  that fuel brand in `fuel_brands` (Mobil on 7-Eleven), `fuel_brand_conflict` when it does not
  (Arco on Shell). Diagnostics go into validation evidence (§14) and PR 3 accounting.

One deliberate exception, unreachable on real rows (both brand fields are filled from the same
brand QID): if the two brand fields carry two *different fuel* QIDs, neither is store identity and
the row is refused as `conflicting_wikidata` rather than one being picked.

This replaces the earlier draft recommendation that Arco on a Shell row be a `brand_conflict`.
That would have let a fuel ID destroy store identity, contradicting the approved separation.

**What remains strict.** A **non-fuel** foreign STORE/CHAIN identity — another registry chain's
own QID, an unrecognised QID, or a brand name that is not this chain's (or its declared co-brand
partner's) — still drops the candidate as `brand_conflict` (R5, decision 8). Shell's own QID on a
"7-Eleven" row is exactly that: Shell is a chain in this registry, its QID is store identity, and
7-Eleven's candidate is dropped while Shell's survives.

Measured context: all 73 Mobil/7-Eleven rows are below 0.90, so none reach corpus v2 today. The
rule exists so that a later release does not change the answer.

## 8. Allowed categories per chain

The canonical v2 keys are the 16 in `OvertureTaxonomyMapV2`. S = storefront, Dp = department,
F = fuel, ✗ = excluded (chain-local or global), blank = not allowed (`category_not_allowed`).

| chain | grocery | pharmacy | drugstore | coffee | cafe | conv. | gas | fast food | burger | chicken | taco | dept | superstore | restaurant | shop ctr |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| publix | S | Dp | | | | | | | | | | | | | ✗ |
| starbucks | | | | S | S | | | | | | | | | ✗ | ✗ |
| walgreens | | S | S | | | | | | | | | | | | ✗ |
| cvs | | S | S | | | | | | | | | | | | ✗ |
| walmart | Dp¹ | Dp | | | | | | | | | | S | S | | ✗ |
| target | ✗ | ✗ | ✗ | | | | | | | | | S | S | | ✗ |
| aldi | S | | | | | | | | | | | | | | ✗ |
| winn_dixie | S | Dp | | | | | | | | | | | | | ✗ |
| whole_foods | S | | | | | | | | | | | | | | ✗ |
| trader_joes | S | | | | | | | | | | | | | | ✗ |
| seven_eleven | | | | | | S | F | | | | | | | | ✗ |
| wawa | | | | | | S | F | | | | | | | | ✗ |
| racetrac | | | | | | S | F | | | | | | | | ✗ |
| speedway | | | | | | S | F | | | | | | | | ✗ |
| shell | | | | | | Dp | S | | | | | | | | ✗ |
| mcdonalds | | | | ✗ | ✗ | | | S | S | | | | | ✗ | ✗ |
| taco_bell | | | | | | | | S | | | S | | | ✗ | ✗ |
| chick_fil_a | | | | | | | | S | | S | | | | ✗ | ✗ |
| wendys | | | | | | | | S | S | | | | | ✗ | ✗ |
| burger_king | | | | | | | | S | S | | | | | ✗ | ✗ |

¹ Department by default. A storefront only through the `neighborhood_market` format (a name
pattern; format role overrides category role).

`gym` is allowed for no chain. `restaurant` is allowed for no chain (decision 4). Walmart has no
`gas_station` (decision 7).

## 9. Format rules

**Row formats** are decided by the matcher from one row: its category and its name *remainder*
(the text after the longest matched name alias or compound; the whole name when identity came from
a QID or brand alias, as with a RaceTrac row named "Polo").

1. Only formats whose `categories` contain the row's category are eligible.
2. If exactly one eligible format's name pattern matches the remainder, it wins. **Two hits are
   refused** (`unsupported_format`), never ordered — so config order cannot change an answer.
3. Otherwise the category's single default format (no patterns) applies.
4. The membership's role is the **format's** role.
5. Department identity (a department QID) with a non-department format → `unsupported_format`.

**Site classification** (`ChainSiteClassifier`) is the contract the PR 5 site builder must honour.
It takes one chain's memberships that the caller has **already grouped** into one physical site. It
never groups, measures distance or reads coordinates. Each member's role is read from the
registry's format and must agree with the membership's stored role; a disagreement (a stored
"storefront" `grocery_department`) is refused, never resolved — trusting the stored field is how a
department would be promoted. `ChainMembership` refuses a role outside the vocabulary.

| Members present | Status | Site format | Visible qualifier | Storefront query | Generic brand query |
|---|---|---|---|---|---|
| any storefront + fuel, chain permits `store_with_fuel` | `storefront` | `store_with_fuel` (internal) | the representative format's label, else none — **never "with fuel"** | ✅ | ✅ |
| any storefront | `storefront` | representative storefront format | its label if visible (e.g. "Supercenter") | ✅ | ✅ |
| departments only | `storefront_unconfirmed` | representative department format | none | ❌ | ❌ (internal only) |
| fuel only, chain permits `fuel_only` (7-Eleven) | `fuel_only` | `fuel_only` | **"Fuel only"** | ❌ | ✅ |
| fuel only, otherwise (Wawa, RaceTrac, Speedway) | `unsupported_format` | — | none | ❌ | ❌ |

The representative format is chosen without reference to input order: a format with a name
pattern (the more specific claim) before a default, then by format key. A department is never a
separate storefront result; it folds into a storefront's site when the site builder groups it
there (v2§8 order: storefront > department > fuel).

**User-visible formats:** Walmart `supercenter` ("Supercenter") and `neighborhood_market`
("Neighborhood Market"); the site-level "Fuel only" qualifier. Everything else — including
`store_with_fuel`, every department format and every `fuel` row format — is internal structured
metadata (decision 10). A liquor department is not modelled at all: liquor is excluded from v2 and
rejected by chain pattern.

## 10. Sub-entity rules

Each embedded service is stopped by **at least two independent gates**, so a gap in one (a new
Overture token, a missing QID) does not let it through.

| Sub-entity | Gate 1: category | Gate 2: identity | Gate 3: name | Outcome |
|---|---|---|---|---|
| Pharmacy department | `pharmacy` allowed as **Dp** only (Publix, Walmart, Winn-Dixie) | — | none needed: the category selects the department format | membership, role department, `storefront_unconfirmed` until grouped with a storefront |
| Optical / vision | `eyewear_store` never imported | Target Optical Q19903688 → department only | `optical` (global), `vision` (Walmart) | reject |
| Bakery | `bakery` never imported | — | `bakery` (Walmart) | reject |
| Fuel | `gas_station` = **F** for c-store chains | a fuel-brand QID is never identity (§7) | — | membership, role fuel; site status derived (§9) |
| ATM | `atm` never imported | Citibank, BMO, PNC, Santander | `atm`, `fcti` | reject |
| Money transfer | `money_transfer_service`, `check_cashing…` never imported | Western Union | `western union`, `money center`, `money transfer` | reject |
| Labs / clinics | lab, clinic, outpatient and doctor tokens never imported | Quest Diagnostics | `clinics?`, `minute ?clinic`, `labcorp`, `village medical`, `quest diagnostics`, `health` (Walmart) | reject; "X at Walgreens" also fails prefix matching |
| Package locker | `package_locker` never imported | — | `lockers?` | reject |
| EV charger | `ev_charging_station` never imported | Electrify America | `recharge`, `ev charg…` | reject |

No sub-entity rule uses fuzzy matching or coordinates. Merging a department into its storefront is
a site-builder decision (PR 5) and never crosses chains.

## 11. Co-branding

* **Membership is many-to-many.** One row may hold 0, 1 or several memberships, each with its own
  role, format and match method; each names the others in `coBrandWith`. v2§5's
  `poi_chain_site_members` key `(place_id, brand_key)` already allows this.
* **Co-branding is declared, symmetric and evidenced on the row.** A pair survives R8 only if both
  chains declare each other and **this row** satisfies `co_brand_evidence` by either route:
  (a) its whole normalised name (after store-number stripping) **equals** one of the pair's
  declared `compound_names` (`7 eleven speedway`, `speedway 7 eleven`) — whole-name equality, not a
  word prefix, so "7-Eleven Speedway Blvd" is an address, not a co-brand; or
  (b) one chain has name-alias identity and the other has brand identity (brand alias or own QID)
  that the row's *other* brand field does not contradict. A brand name naming Speedway beside a
  QID naming 7-Eleven is a contradiction, not two brands, and stays `ambiguous`.
  Route (a) exists because Speedway is exact-only: without a declared compound, "7-Eleven /
  Speedway" would match 7-Eleven alone. A compound name is curated text like any alias, never a
  substring search.
* **Never proximity.** `ChainMatchInput` carries no coordinate and no address; two rows at one
  address are matched independently and never gain a membership from each other. Two chains at one
  address stay two sites (v2 decision 3b).
* **A declared pair without row evidence is `ambiguous`** — both candidates dropped, nothing guessed.
  Example: brand "Speedway" + 7-Eleven's own QID with no name. An undeclared pair cannot reach R8
  in a valid registry (§4.2).
* **Two identities per site** for PR 4/5: a `physical_site_id` (one per place cluster,
  chain-independent) and a chain site per `(physical_site_id, brand_key)`. A chain-specific query
  reads chain sites, so a co-branded site answers both keys (v2 decision 3c); a unique-location
  count groups by `physical_site_id`, so it is counted once. This adds one column to the v2§5
  `poi_chain_sites` proposal; nothing is created now.
* **Measured reality:** the combined "7-Eleven/Speedway" name was observed only on Western Union
  rows, which R1 rejects. Storefront co-brand evidence is **unmeasured** (v2§10-2).

## 12. Matcher precedence (`chain-match-precedence-v2`; v1 was the same without the v2 rows)

### 12.1 Rules, in order

Input: `ChainMatchInput {name, brandName, brandWikidata, categoryKey, operatingStatus}` — raw
values; the matcher normalises them itself. Output: `ChainMatchResult` — memberships, or one
refusal reason, plus per-candidate drop reasons and diagnostics. Deterministic: the same row and
the same registry version always give the same answer, whatever order the config was written in.

| # | Rule | Effect |
|---|---|---|
| R0 | `categoryKey` ∉ the 16 keys — unless the key is empty and a chain declares a **source-category rescue** for the raw `sourceCategory` token (v2, §19), which restricts R3 to those chains; status not `open`/NULL; brand QID present but malformed; a QID-shaped brand name is read as the brand QID and must agree with the QID field (§4.2) | reject: `category_not_imported` / `status_excluded` / `malformed_wikidata` / `conflicting_wikidata` |
| R1 | brand QID ∈ global **service** exclusion IDs | reject: `exclusion_wikidata` |
| R2 | name or brand matches a global exclusion / closed-name pattern | reject: `exclusion_pattern` / `closed_name` |
| R3 | per chain, **candidate identity**, strongest first: own QID → brand alias (exact) → name alias (exact / word-prefix, longest wins) → declared co-brand compound name (whole name) → department QID. **A fuel-brand QID or name is never identity.** | no candidate at all → `no_chain` |
| R4 | chain-local exclusion QID or pattern (name or brand); for a rescued row, the rescue's own sub-entity patterns | drop candidate: `chain_exclusion` |
| R5 | foreign **STORE/CHAIN** identity: a brand QID that is not this chain's own or department ID, not a fuel brand, and not a declared partner's own ID; or a brand name that is not this chain's brand alias, not a fuel brand name, and not a declared partner's brand alias. **v2 host exception:** a foreign QID / brand owned by a format's declared `host_chains`, on a row whose NAME identifies this chain and whose category is one of that format's host categories (`host_categories`, default all of its categories), is not a conflict — and that format is then forced | drop candidate: `brand_conflict` |
| R6 | category (a rescued row: the rescue's `as_category`) in global or chain `excluded_categories` / not in `allowed_categories` | drop: `category_excluded` / `category_not_allowed` |
| R6b | **v2:** the category is marked `identity: strong`, or the row is rescued, and identity is neither the chain's own QID nor its name alias | drop: `strong_identity_required` |
| R7 | format and role resolution (§9) | drop: `unsupported_format` |
| R7b | **v2:** the chain sets `brand_alias_requires_corroboration`, identity is a brand alias only (no own / department QID, no name alias, no compound) and the resolved format is a default | drop: `brand_alias_uncorroborated` |
| R8 | more than one survivor: every pair must be a declared co-brand evidenced on this row (§11) | otherwise `ambiguous`, zero memberships |

Every candidate chain also gets fuel diagnostics (`fuel_brand_expected` / `fuel_brand_conflict`)
when the row carries a fuel brand; they never affect R4–R8. When every candidate is dropped, the
result is `no_match` / `all_candidates_rejected` with each chain's reason, so PR 3 can report
rejections **by candidate chain** (decision 8).

### 12.2 Differences from the originally proposed order, and why

1. **Exclusions run before identity** (R1/R2 row-level, R4 per candidate). Invariant: **no positive
   evidence can override any exclusion.** If patterns ran after identity, an exact alias hit could
   be read as a reason to stop, and "Publix Liquors" mis-tagged `grocery_store` would become Publix.
2. **The category gate sits after identity (R6), not first.** Nothing is weakened: every rule is a
   hard drop, and ordering only chooses which *reason* is reported. Reporting
   `category_not_allowed` for a Publix-named row, instead of `no_chain`, is what makes the PR 3
   chain × category matrix possible.
3. **R5 is added** for STORE/CHAIN identity only. It covers the unrecorded tenant QIDs and any brand
   PR 0 did not list. Fuel brands are carved out of it by construction (§7).

### 12.3 Substring matching

* **Infix / contains: never a match route.** Used only to *reject* (R2/R4) and in the near-miss
  review report.
* **Prefix (`p`)**: the normalised name equals the alias, or starts with the alias followed by a
  space. Allowed only for aliases that are not ordinary words or common surnames / place words.
* **Exact (`x`)**: whole-name equality after normalisation. Required for `target`, `shell` and
  `speedway`, the three chain names that are ordinary English words.
* Fuzzy, similarity or edit-distance matching is never an identity source; a structural test
  forbids the functions in the namespace.

## 13. False-positive handling

Rows marked † were named in the PR 2 brief and were **not** observed in PR 0. They are kept as
defensive fixtures; their exact source category is unknown.

| Row | Stopped by |
|---|---|
| `Shell Island` † | exact-only `shell` (R3 no candidate), in any category |
| `Ronald McDonald House Charities` | `charity_organization` not imported (R0); `ronald mcdonald` / `charities` (R2) even if re-classified |
| `Royal Shell Real Estate` | category (R0); `real estate` (R2); exact-only `shell` (R3) |
| `PUBLIX #1029` + Western Union QID | R1 `exclusion_wikidata` — the alias would match, but R1 runs first |
| `7-ELEVEN/SPEEDWAY #46807` + WU | R1; it never becomes co-brand evidence |
| `Walmart Money Center` | category (R0); `money center` (R2) |
| `Target Optical` (Q19903688, `eyewear_store`) | category (R0); `optical` (R2); the department QID can never make a storefront (R7) |
| `Target Specialty Products` † | `target` exact-only (R3 no candidate) |
| `CVS Pharmacy` inside Target | a `cvs` membership (`store_in_target`); Target is never a candidate (`target` is exact); different chains are never merged |
| `Target Pharmacy`, brand Target | Target's chain pattern `pharmacy` (R4) and excluded category (R6) |
| RaceTrac row named `Polo` / `Lake Mary`, brand "RaceTrac" | v1: R3 via **brand alias** → `racetrac`. v2: refused unless the row carries RaceTrac's own QID — `brand_alias_uncorroborated` (R7b) in `convenience_store`, `strong_identity_required` (R6b) in `gas_station` (§19.1) |
| `7-Eleven` + Mobil Q109676002 | `seven_eleven` membership; `fuel_brand_expected` diagnostic |
| `Shell` + Arco Q304769 | `shell` membership; `fuel_brand_conflict` **diagnostic only** (§7) |
| `7-Eleven` + Shell's own QID | 7-Eleven dropped `brand_conflict` (STORE identity); Shell survives |
| `Publix`, brand "Starbucks", `coffee_shop` | Starbucks (brand alias); Publix dropped `brand_conflict` |
| `ATM Walgreens` (Citibank) | category (R0), R1, `atm` (R2); also not a prefix hit |
| `Labcorp at Walgreens` | category (R0), `labcorp` (R2), not a prefix hit |
| `Daytona International Speedway` | `race_track` not imported; `speedway` exact-only |
| `7-Eleven - Closed` | R2 `closed_name` |
| `Walmart` in `grocery_store`, no "Neighborhood Market" | walmart **department** (`grocery_department`, `storefront_unconfirmed`) |

## 14. Validation-state model

**Status is per chain** (decision 12): `provisional` (default) → `supported`, with `unsupported`
available for a chain the corpus cannot answer. It maps onto v2§8's coverage outcomes: only
`supported` may produce "none within X"; `provisional` reports `unknown_coverage`. Format-level
evidence may be recorded in a packet, but there is **no per-format status** in v1.

**Where it lives:** a separate validation record (a later file or table, not built now), never the
registry. The config can only say `provisional` (load error otherwise), and no code path in this
layer can write a status. Each record:

| Field | Content |
|---|---|
| `brand_key` | |
| `status` | provisional / supported / unsupported |
| `status_date` | ISO date |
| `decided_by` | a role, not a name |
| `registry_version` | e.g. `chain-registry-v1` |
| `chain_rule_hash` | `ChainRegistry::chainRuleHash($brandKey)` |
| `corpus_version` | e.g. `overture-2026-08-19.0-fl-r2` |
| `taxonomy_map_version` | `overture-taxonomy-v2.0` |
| `reference` | `{count, source, date, geographic_scope}` or `null` with a reason (v2§9-B) |
| `dedup_site_count` | corpus de-duplicated chain sites in the same scope |
| `difference` | absolute and relative, plus a `difference_explanation` |
| `category_matrix` | chain × category counts, including `category_not_allowed` / `category_excluded` |
| `rejection_tallies` | by reason code, including `brand_conflict` for this candidate chain |
| `fuel_diagnostics` | `fuel_brand_expected` / `fuel_brand_conflict` counts by fuel brand |
| `format_evidence` | per-format counts (recorded, not promoted separately) |
| `fixture_results` | `[{fixture: st_pete\|tampa, expected, observed_nearest, distance_m, verdict}]` |
| `sampling` | `{regions[], sample_size, wrong_chain, embedded_service_fp, duplicates, wrong_format, closed, notes}` |

**Binding rule:** a `supported` record is valid only while its `chain_rule_hash` and
`corpus_version` match the live values. Any rule change or corpus re-pin makes the chain read as
`provisional` again, with no manual step.

**The chain rule hash is derived from the whole registry** (`sha256("chain:<key>\n" + ruleHash)`).
The earlier draft said adding a different chain would not demote this one. That is false: R5 and
R8 read other chains' rules, and a new chain's alias or chain-local exclusion can change which rows
this chain keeps. Since no edit anywhere can be proven not to move a chain's matches, every
identity-affecting edit demotes every chain. This costs nothing extra in practice: every such edit
is a registry-version bump anyway.

## 15. Registry versioning

`registry_version = 'chain-registry-vN'` is independent of the Overture release, the corpus version
and `OvertureTaxonomyMapV2::VERSION` (the loader refuses a version that looks like a release). v2§5's
derived tables are keyed by `(corpus_version, registry_version)`, so memberships are rebuilt per
version and never mutated in place.

`ChainRegistry::ruleHash()` is a SHA-256 over the canonicalised rules: lists sorted, maps
key-sorted, so **reordering the config does not change it**. It includes the registry, normaliser,
taxonomy-map and match-precedence versions.

**Bump required** (anything that can change what a row matches or how a site is classified):
aliases, brand aliases, match modes · own, department, exclusion or fuel-brand QIDs and names ·
global or chain exclusion / closed patterns · allowed or excluded categories or roles · formats,
format patterns, format roles, default formats · `fuel_sites` · a chain's expected `fuel_brands` ·
co-brand declarations or compound names · the normaliser (`normalizer_version` bumps with it) ·
the precedence (`MATCH_PRECEDENCE_VERSION`) · adding or removing a chain · a new
`taxonomy_map_version`.

**No bump:** `display_name`, `notes`, format `label` / `user_visible` (presentation), evidence
text, and validation records (§14).

`ChainRegistryConfigTest` pins the rule hash per registry version. A rule edit without a bump and a
new pin fails the build.

## 16. Test plan (implemented)

All pure unit tests: no database, no network, no container. All six files are in
`tests/spatial-ci-files.txt`.

| File | Covers |
|---|---|
| `ChainNameNormalizerTest` | the §4.1 contract, idempotence, no fuzzy folding, QID shape, malformed QIDs |
| `ChainRegistryConfigTest` | exactly the 20 chains; measured own / exclusion QIDs verbatim; fuel catalogue separate; all provisional; unknown chain refused; decisions 1, 3, 4, 6, 7, 9, 10, 11; exact-only ordinary words; the one symmetric co-brand; rule-hash pin; every identity-affecting edit changes every chain hash; presentation edits and reordering do not |
| `ChainRegistryValidationTest` | every §2 / §4.2 load rule refuses a broken config, including `supported` in config, alias collision, prefix shadowing (of an alias and of a compound), QID role clashes, fuel QID or name as chain identity, shopping_center allowed, a default format upgrading a department, a regex matching the empty string, asymmetric co-brands |
| `ChainMatcherTest` | identity (own QID, brand alias, exact / prefix name alias, store number, sparse/no-QID chains, QID-shaped brand, punctuation, no substring, no fuzzy); exclusions (Western Union, ATM operator, EV, Quest, wrong / excluded category, closed, chain-local after identity, foreign STORE identity, invalid input); false positives (Shell Island †, Ronald McDonald House, Target Specialty Products †, Target Optical, Royal Shell Real Estate, Labcorp, Daytona); **fuel separation** (7-Eleven + Mobil matches with `fuel_brand_expected`; Mobil alone creates nothing; Shell + Arco is not rejected, `fuel_brand_conflict` only; a fuel brand never rescues a refused row; Shell's own QID on 7-Eleven stays a conflict); **department vs storefront** (Walmart grocery ≠ Neighborhood Market, Publix / Winn-Dixie / Walmart pharmacy departments, Shell station shop); formats (Walmart, 7-Eleven, Wawa, CVS inside Target, Target Pharmacy, no Walmart fuel); **co-branding** (compound names, name + partner brand, Western Union "7-ELEVEN/SPEEDWAY" produces neither, plain Speedway, no proximity path, declared pair without evidence is ambiguous, undeclared pair cannot both survive); determinism under a reordered registry |
| `ChainSiteClassifierTest` | store + fuel is `store_with_fuel` with no visible suffix; 7-Eleven fuel-only carries "Fuel only" and fails storefront queries; lone fuel is unsupported for Wawa / RaceTrac / Speedway; department-only is `storefront_unconfirmed` and excluded from storefront (and generic) queries; departments fold into a storefront site; Supercenter label; order independence; co-branded row yields a site under each key; mixed chains, empty sites and unknown chains refused |
| `ChainRegistryStructuralGuardTest` | nothing outside the namespace references it; the config has exactly one reader; no DB / HTTP / cache / log / env / Google path; no similarity function; config is data only; not a required production flag; every test is in the spatial manifest |

## 17. Locked product decisions (2026-09-22)

| # | Decision | Where it lives |
|---|---|---|
| 1 | `shopping_center` is never a chain identity category (the corpus keeps it for Location DNA). | `global.excluded_categories` |
| 2 | Department-only sites are retained internally as `storefront_unconfirmed`; never in storefront-only queries (nor, in v1, generic queries). They may later help de-duplication, identity resolution and coverage analysis. | `ChainRole`, `ChainSiteClassification` |
| 3 | No McDonald's identity from `coffee_shop` / `cafe` in v1; revisit McCafé after PR 3 evidence. **Superseded for `coffee_shop` by v2 decision 2 (§19); `cafe` stays excluded.** | `mcdonalds.excluded_categories` |
| 4 | No generic `restaurant` for the fast-food chains or Starbucks in v1; PR 3 reports chain × category misses. **Superseded for Burger King and McDonald's by v2 decision 2; unchanged for the rest.** | per-chain `excluded_categories` |
| 5 | **A fuel-brand ID alone never creates or removes store-chain membership.** Recorded as `fuel_brand_expected` / `fuel_brand_conflict` diagnostics. A non-fuel foreign STORE/CHAIN identity remains a conflict. | `global.fuel_brands`, §7, R5 |
| 6 | Wawa / RaceTrac / Speedway: a fuel-only site is not a confirmed store (`unsupported_format`). 7-Eleven keeps `fuel_only`, not equivalent to a storefront. **Superseded for RaceTrac and Speedway by v2 decision 3; unchanged for Wawa.** | `fuel_sites` |
| 7 | No Walmart fuel in v1 (unmeasured). | `walmart.allowed_categories` |
| 8 | The foreign STORE/CHAIN rule stays strict; PR 3 reports `brand_conflict` by candidate chain. | R5, `candidateRejections` |
| 9 | No unmeasured sub-brand aliases (GreenWise, Starbucks licensed variants, …). | aliases |
| 10 | No "· with fuel" display suffix; `store_with_fuel` is internal metadata. A genuine fuel-only site shows "Fuel only". | `ChainSiteClassification::displayQualifier` |
| 11 | CVS `shopping` rows are not rescued; PR 3 measures the 195 rows (duplicates, departments, real storefronts or bad classifications). **Superseded by v2 decision 1: the census measured them (150 real storefronts).** | `cvs.allowed_categories` |
| 12 | Validation status is per chain in v1; format evidence may be recorded, not promoted separately. | §14 |

## 18. Implementation (this PR) and what follows

**Shipped here — pure and unreferenced:**

1. `config/poi_chain_registry.php` — the 20 entries and the global block.
2. `ChainRegistry` — container-or-file reader with every §2 / §4.2 load-time rule, the rule hash
   and per-chain hash.
3. `ChainNameNormalizer` (`chain-name-norm-v1`).
4. Value types: `ChainMatchInput`, `ChainMembership`, `ChainMatchResult`, `ChainMatchReason`,
   `ChainRole`, `ChainDefinition`, `ChainFormat`, `ChainSiteClassification`.
5. `ChainMatcher` (R0–R8) and `ChainSiteClassifier` (the §9 site contract).
6. The §16 tests and the structural guards; `intl` added to the spatial CI PHP extensions.

**Not here, deliberately:** schema (PR 4), extraction and accounting (PR 3), the site builder and
de-duplication (PR 5), the validation record, the brand query service (PR 6), and any caller.
The matcher ships unreferenced, as `OvertureTaxonomyMapV2` did.

**Ordering note:** the v2§11 sequence puts extraction (PR 3) after the matcher. The matcher is pure
and every chain stays provisional until PR 3's accounting (chain × category matrix, reason tallies
by candidate chain, fuel diagnostics, near-miss report) exists, so shipping it first is safe. Every
**P** marker in the config is a question that accounting answers.

## 19. chain-registry-v2 — the census decisions (2026-09-22)

The real-data census (`overture-chain-registry-census.md`) ran every eligible `2026-08-19.0`
Florida-box row through v1 unmodified. Its findings were decided as follows and implemented as
`chain-registry-v2` with `chain-match-precedence-v2`. **Every chain stays `provisional`.**

| v2 # | Decision | Config | Matcher |
|---|---|---|---|
| 1 | CVS `convenience_store` on strong identity. CVS `shopping` via a **chain-scoped rescue**, not the import list; Beauty, MinuteClinic and specialty pharmacy excluded. | `cvs.allowed_categories.convenience_store.identity`, `cvs.source_category_rescues.shopping` (→ `drugstore`, never Location DNA `pharmacy`) | R0, R4, R6b |
| 2 | Burger King `restaurant`; McDonald's `restaurant` + `coffee_shop`; strong identity only; no McDonald's `cafe` | `allowed_categories.*.identity` | R6b |
| 3 | Speedway / RaceTrac: a strongly identified gas row may stand alone as `fuel_only`, labelled "Fuel only"; no store claimed. 7-Eleven and Wawa unchanged | `fuel_sites.fuel_only`, `gas_station.identity` | R6b; `ChainSiteClassifier` |
| 4 | Walmart grocery: "Neighborhood Market" and "Supercenter" names → storefront; plain "Walmart" → department (so `storefront_unconfirmed` until de-duplication); pickup / delivery excluded | `supercenter.categories` += `grocery_store`; `walmart.exclusion_name_patterns.pickup_delivery` | R4, R7 |
| 5 | Measured office / warehouse / distribution-centre / support-centre / careers exclusions (global); Walmart-only `dc`; CVS-only `photo`; RaceTrac-only "racetrac petroleum"; Burger King-only `^burger king capital holdings` (§19.1). **No global `petroleum`, `holdings` or `llc`** | `global.exclusion_name_patterns`, chain `exclusion_name_patterns` | R2, R4 |
| 6 | A brand alias alone is not identity at Walgreens, Walmart, Taco Bell, Chick-fil-A, Burger King, CVS and RaceTrac (§19.1); own QIDs untouched | `brand_alias_requires_corroboration` | R7b |
| 7 | CVS inside Target: Target identity coexists with CVS only with CVS in the name and on the `store_in_target` format's **host categories** (`pharmacy`, `drugstore`) — which includes a CVS row rescued from `shopping`, since the rescue files it as `drugstore`. An "inside Target" CVS name selects `store_in_target` in `convenience_store` too, but Target is not a host there (§19.1) | `cvs.formats.store_in_target.host_chains`, `.host_categories` | R5, R7 |
| 8 | Winn-Dixie `drugstore` on strong identity (provisional). Shell suffix **not** adopted: the 9-row gas-station sample was not clean | `winn_dixie.allowed_categories.drugstore` | R6b |

Locked non-changes: Starbucks gets no `restaurant`; 7-Eleven, Wawa and co-branding are unchanged;
fuel-brand identity stays diagnostic only.

**New load-time rules** (`ChainRegistryValidationTest`): `identity` may only be `strong`;
`brand_alias_requires_corroboration` is a boolean and needs brand aliases; a rescue token must match
`^[a-z][a-z0-9_]*$`, must **not** be an imported token or a canonical key, and must name one of the
chain's **storefront** categories as `as_category` (a rescue can never create a department or fuel
membership); rescue patterns pass every pattern rule, including the fuel-name guard; `host_chains`
only on a storefront format with name patterns, naming an existing chain other than itself that is
not also its co-brand partner; `host_categories` only beside `host_chains`, non-empty, unique, and a
subset of the format's own categories. Strong identity for a rescue is code, not config. All new fields
are in the rule hash; their evidence text is not.

**Strong identity** means the chain's own QID, or the chain's name alias in the place's own name. A
brand alias, even corroborated, is not strong. In the census every eligible Speedway / RaceTrac gas
row already had one of the two, so requiring it there costs no eligible row in this release.

**What a later PR must carry:** the extraction recipe (PR 3) must admit a non-imported row only when
`ChainRegistry::rescueChainsFor()` names a chain for its token, and pass the raw token as
`ChainMatchInput::$sourceCategory`; such a row must never enter a Location DNA category. A rescued
membership says so in `rescued_from_source_category`.

Measured effect (census §8, final v2 rule hash `b5920a1c7319…`): 10,880 → 11,082 memberships;
256 legitimate rows gained plus 10 Walmart Supercenter rows reclassified to storefront; 54 removed
(53 false positives, 1 probable real); 150 Speedway and 35 RaceTrac lone fuel rows become "Fuel
only" sites.

### 19.1 Residual findings of the v2 census, resolved (2026-09-23)

The first v2 run (rule hash `4fedf3232df8…`, never shipped) left five findings. Four are rule
changes inside `chain-registry-v2` — v2 was never released, so its version string stays and only
its rule-hash pin moves; the fifth is intended behaviour.

| Finding | Decision | Rule | Census effect |
|---|---|---|---|
| "Burger King Capital Holdings, Llc" (`restaurant`) matched as a storefront | A Burger King-only, anchored corporate exclusion for the measured shape. No global `holdings` / `llc` | `burger_king.exclusion_name_patterns.capital_holdings` = `/^burger king capital holdings\b/` | −1 |
| 5 RaceTrac-branded "RaceWay" / "Race Way" / "Raceway 6847" rows matched RaceTrac | RaceTrac joins decision 6. (RaceWay is RaceTrac's franchise banner, so these may be real RaceWay sites; they are refused as RaceTrac **stores**, which is what the membership would claim.) Its own QID or "RaceTrac" in the name is identity; the brand field alone is not. No fuzzy RaceTrac ↔ RaceWay match | `racetrac.brand_alias_requires_corroboration` | −5; every other RaceTrac row carries name identity or the QID |
| "CVS Pharmacy inside Target Store" in `convenience_store` got the generic `store` format | The name pattern selects `store_in_target` there too. Target stays a **conflict** in `convenience_store` (no measured Target-branded row) via the new `host_categories` | `store_in_target.categories` += `convenience_store`; `host_categories` = `pharmacy`, `drugstore` | 1 reclassified |
| "Cfaleesburgfl" (brand "Chick-fil-A", **no brand QID**, `chicken_restaurant`) refused as uncorroborated | Evidence is an opaque name plus a brand field measured to be misattributed at this chain. Stays refused; no alias invented. The own QID would admit it (tested) | none | 0 |
| ~27 plain Walmart `grocery_store` rows stay departments | **No change.** Intended per v2 decision 4: plain / ambiguous Walmart grocery rows are `storefront_unconfirmed` until de-duplication places them. Measured precisely: 21 `grocery_department` memberships, 19 with no Walmart storefront within 150 m | none | 0 |
