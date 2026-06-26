# Census Intelligence — Phase 5.3: Governance & Architecture Plan

**Document Status:** Planning / Governance Only — **no production code to be written under this document**
**Phase:** 5.3 (Documentation & Design)
**Date:** 2026-06-25
**Author:** Platform Architecture
**Related:** `LOCATION_DNA_PHASE_A_GOVERNANCE_AND_DATA_SOURCE_PLAN.md`, `PROPERTY_DNA_AUDIT.md`, `BUYER_TENANT_DNA_COMPATIBILITY_AUDIT.md`, `ask-ai-context-source-matrix.md`

---

## 0. Read This First — The Governance Conflict

Phase 5.3 proposes adding U.S. Census demographic context to the property experience. **This directly contradicts the existing, in-force Location DNA governance document.** Before any code is written, this conflict must be resolved by the product owner (and ideally reviewed by counsel), because it is a Fair Housing Act (FHA) exposure question, not an engineering question.

`LOCATION_DNA_PHASE_A_GOVERNANCE_AND_DATA_SOURCE_PLAN.md` — Section 4, "Prohibited Inputs" — currently states verbatim:

> | Census demographic statistics by tract or ZIP | Proxy for protected-class data |
> | Neighborhood demographic composition | Proxy for protected-class data |
> | Any data used to recommend or discourage a property based on who lives nearby | Core FHA violation |

And Section 1 states Location DNA "**does not incorporate, reference, or infer information about the population characteristics** of any neighborhood, census tract, ZIP code, or geographic area."

**Therefore Census Intelligence cannot be a feature of Location DNA.** It must be a **separate, independently-governed module** with its own boundaries. The two systems may render adjacently on the property page, but they must remain architecturally and governance-wise distinct, and the Location DNA Phase A document must be amended to acknowledge the sibling module rather than be silently violated.

The single most important design decision in this plan is this distinction:

| Posture | FHA Risk | Recommendation |
|---|---|---|
| **Display** neutral factual census data to a consumer who is already viewing a specific property, framed as objective context with county/state comparison | Lower (but non-zero) | Permitted **only** with neutral-language guardrails + owner sign-off |
| **Score / rank / recommend / discourage** a property using census demographics (Match Score integration) | **High — textbook steering** | **Not recommended. Excluded from this plan's build scope.** Requires explicit legal approval to even prototype. |
| **Target / segment** an audience by demographics ("Target Market DNA") | **High — textbook steering & disparate-impact** | **Not recommended.** Re-scope to housing-stock facts only, or decline. |

Two of the six requested integration points (Match Score, Target Market DNA) are the high-risk ones. This document **plans the safe display/Ask-AI path in full** and **explicitly gates the scoring/targeting paths behind a legal decision**, recommending against them. See §11.

---

## 1. Purpose & Scope

### 1.1 What Census Intelligence Is

A **deterministic, read-only contextual layer** that retrieves published U.S. Census Bureau American Community Survey (ACS) statistics for the geography a property sits in, and presents them as **neutral, factual community context** — the same numbers any member of the public can pull from data.census.gov, surfaced in-app with consistent neutral framing and county/state comparison.

### 1.2 What Census Intelligence Is NOT

- It is **not** part of Location DNA (see §0).
- It is **not** a scoring, ranking, or recommendation input (see §11.4).
- It is **not** an audience-targeting or segmentation tool (see §11.3).
- It does **not** fetch, store, infer, or display any protected-class attribute (race, color, national origin, religion, sex, disability; see §10.2 for the familial-status edge case).
- It does **not** label any area as good/bad/safe/unsafe/desirable/wealthy/poor/up-and-coming.

### 1.3 Phase 5.3 Deliverables (this document)

Architecture plan · proposed tables/fields · ACS variable list · UI component plan · integration plan · phased implementation tasks · governance guardrails. **No PHP, migrations, routes, views, or config files are created under Phase 5.3.**

---

## 2. Data Source Selection

### 2.1 Decision: U.S. Census Bureau ACS 5-Year Detailed Tables, via the Census Data API

| Candidate | Verdict | Reason |
|---|---|---|
| **ACS 5-Year (`acs/acs5`)** | **Selected** | Full table availability down to **census tract**; smallest geography with reliable, low-margin estimates; free; no auth required (API key recommended for rate limits). Updated annually. |
| ACS 1-Year (`acs/acs1`) | Rejected | Only published for geographies ≥ 65,000 population. Most tracts/ZCTAs unavailable. |
| Decennial Census (`dec/*`) | Supplementary only | Only every 10 years; limited socioeconomic detail. Useful only for raw population/housing counts. |
| Census Data Profiles (`acs/acs5/profile`, DP0x) | **Selected as a convenience layer** | Pre-computed percentages (education %, owner/renter %, commute mean) reduce client-side math. Use alongside detailed `B` tables. |
| Third-party (Esri, ATTOM, etc.) | Rejected | Paid; redistribution constraints; we only need free, citable, public figures. |

**Authoritativeness / citation:** Every figure is directly attributable to "U.S. Census Bureau, American Community Survey 5-Year Estimates, [vintage]" — important for the neutral-context framing and the Ask AI source-attribution contract.

### 2.2 API Endpoints

All endpoints are HTTPS GET, JSON, no body. A free API key (`CENSUS_API_KEY`) is appended as `&key=...`.

**(a) ACS detailed tables — the data fetch**
```
https://api.census.gov/data/{vintage}/acs/acs5
  ?get={comma-separated variable codes,NAME}
  &for=tract:{tract}&in=state:{ss}+county:{ccc}
  &key={CENSUS_API_KEY}
```
Examples of `for`/`in` by geography level:
- Tract:        `for=tract:013300&in=state:12+county:086`
- County:       `for=county:086&in=state:12`
- State:        `for=state:12`
- ZCTA:         `for=zip%20code%20tabulation%20area:33139`  (note: ZCTA cannot be nested in county)

**(b) ACS data profile (pre-computed %)** — same shape, different path:
```
https://api.census.gov/data/{vintage}/acs/acs5/profile?get={DP codes}&for=...&in=...
```

**(c) Geography resolution — lat/lng → tract/county/state GEOID**
```
https://geocoding.geo.census.gov/geocoder/geographies/coordinates
  ?x={lng}&y={lat}
  &benchmark=Public_AR_Current
  &vintage=Census2020_Current
  &format=json
```
Returns the containing Census Tract (with `GEOID`, `STATE`, `COUNTY`, `TRACT`), County, and the tract's land area `AREALAND` (sq meters) — used for population density without a second call. This complements the existing `CensusTigerBoundaryAdapter` already in `app/Services/LocationDna/` and uses the same Census infrastructure the app already trusts.

**(d) Variable/metadata discovery (build-time only, not runtime):**
`https://api.census.gov/data/{vintage}/acs/acs5/variables.json` — used once to verify variable codes per vintage; cached as a fixture, never called in the request path.

### 2.3 Geographic Level Decision

| Level | Use in this module | Rationale |
|---|---|---|
| **Census Tract** | **Primary unit** for the property's "community" figures | Full ACS table availability; ~1,200–8,000 residents — fine-grained enough to be meaningful, coarse enough for low margins of error. Resolved deterministically from the property's geocoded lat/lng. |
| **County** | **Comparison baseline** (shown alongside every tract figure) | Provides the neutral "vs. surrounding area" framing that keeps figures factual rather than evaluative. |
| **State** | **Secondary comparison baseline** | Broadest factual anchor; used in Ask AI context and optionally in UI. |
| Block Group | **Not used** | Many ACS tables are suppressed or carry very high margins of error at block-group level. Adds precision risk without benefit. |
| ZCTA (ZIP) | **Optional fallback only** | Use only when lat/lng geocoding fails and we can resolve a ZCTA from the listing ZIP. ZCTAs do not nest in counties and align imperfectly with postal ZIPs; treat as lower-fidelity fallback, label accordingly. |

**Rule:** Every tract figure is always presented next to its county figure (and optionally state). A bare neighborhood number with no comparison invites an evaluative reading; the comparison is what keeps it factual.

---

## 3. Metrics Catalog & ACS Variable List

Variable codes below are ACS 5-Year. Confirm exact codes against `variables.json` for the chosen `{vintage}` at build time (codes are stable across recent vintages but must be pinned). Two sourcing options are shown: detailed `B` tables (compute the % ourselves) or data-profile `DP` codes (pre-computed). Recommend **detailed B tables** as the source of truth (auditable, we control rounding) and DP codes only as a cross-check.

| # | Metric | Primary ACS variables (B-tables) | Derivation | DP cross-check |
|---|---|---|---|---|
| 1 | **Total population** | `B01003_001E` | direct | `DP05_0001E` |
| 2 | **Median age** | `B01002_001E` | direct | `DP05_0018E` |
| 3 | **Median household income** | `B19013_001E` | direct (USD) | `DP03_0062E` |
| 4 | **Educational attainment** (% bachelor's or higher, 25+) | `B15003_001E` (denom, pop 25+), `B15003_022E`+`023E`+`024E`+`025E` (bachelor's, master's, professional, doctorate) | sum(num)/denom × 100 | `DP02_0068PE` |
| 5 | **Population density** (people / sq mi) | `B01003_001E` + tract land area `AREALAND` (sq m, from geocoder/TIGER) | pop / (AREALAND × 3.861e-7) | — |
| 6 | **Owner vs renter %** | `B25003_001E` (occupied units), `B25003_002E` (owner), `B25003_003E` (renter) | each / total × 100 | `DP04_0046PE` / `DP04_0047PE` |
| 7 | **Family households %** | `B11001_001E` (total households), `B11001_002E` (family households) | family / total × 100 | `DP02_0002PE` |
| 8 | **Average household size** | `B25010_001E` | direct | `DP02_0016E` |
| 9 | **Median home value** (owner-occupied) | `B25077_001E` | direct (USD) | `DP04_0089E` |
| 10 | **Median gross rent** | `B25064_001E` | direct (USD/mo) | `DP04_0134E` |
| 11 | **Mean commute time to work** (min) | `B08303_001E` (workers) + bucket midpoints, **or** profile mean | use profile mean for simplicity | `DP03_0025E` |
| 12 | **Employment** (labor-force participation %, unemployment %) | `B23025_*` (in labor force / employed / unemployed) | derived % | `DP03_0004PE` / `DP03_0009PE` |

### 3.1 Derived UI metric: Housing Affordability (factual ratios only)
Computed from already-fetched figures — **no new data, no evaluative labels:**
- **Price-to-income ratio** = `B25077_001E` / `B19013_001E` (median home value ÷ median household income).
- **Rent-to-income ratio** = (`B25064_001E` × 12) / `B19013_001E`.
- Presented as a number with its county/state counterpart ("Tract 4.8× · County 4.2×"), never as "affordable"/"unaffordable."

### 3.2 Explicitly EXCLUDED variables (never fetched, never stored)
Race/ethnicity (`B02*`, `B03*`, `DP05` race lines), ancestry (`B04*`), national origin / nativity (`B05*`), language (`B16*`), religion (not in ACS), disability (`B18*`), sex-by-* tables, and any child-presence detail beyond the single "family households %" aggregate (see §10.2 for that one's caveat). The fetch layer will fetch **only the allowlisted codes in §3** — there is no path to request an excluded table.

---

## 4. Database & Cache Design

### 4.1 Refresh cadence reality
ACS 5-Year is published **once per year** (typically December). A tract's figures do not change between releases. This drives the whole storage strategy: **fetch rarely, persist permanently, serve from our DB.** External API calls should happen at most once per (geography, vintage), not per page view.

### 4.2 Proposed tables

**Table A — `census_area_profiles`** (canonical per-geography snapshot; the source of truth)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `geoid` | string, indexed | full GEOID (tract = 11 digits, county = 5, state = 2) |
| `summary_level` | string | `tract` \| `county` \| `state` \| `zcta` |
| `vintage` | string | e.g. `2023` (ACS 5-yr ending year) |
| `state_fips` / `county_fips` / `tract_code` | string nullable | parsed components |
| `name` | string | Census `NAME` field (e.g. "Census Tract 133, Miami-Dade County, Florida") |
| `land_area_sq_meters` | bigint nullable | from geocoder/TIGER; for density |
| `population` | integer nullable | metric 1 |
| `median_age` | decimal(5,1) nullable | metric 2 |
| `median_household_income` | integer nullable | metric 3 (USD) |
| `pct_bachelors_or_higher` | decimal(5,2) nullable | metric 4 |
| `population_density_sq_mi` | decimal(10,2) nullable | metric 5 (derived) |
| `owner_occupied_pct` / `renter_occupied_pct` | decimal(5,2) nullable | metric 6 |
| `family_household_pct` | decimal(5,2) nullable | metric 7 (see §10.2) |
| `avg_household_size` | decimal(4,2) nullable | metric 8 |
| `median_home_value` | integer nullable | metric 9 |
| `median_gross_rent` | integer nullable | metric 10 |
| `mean_commute_minutes` | decimal(5,1) nullable | metric 11 |
| `labor_force_participation_pct` / `unemployment_pct` | decimal(5,2) nullable | metric 12 |
| `margins_json` | json nullable | margins of error (the `M` variants) for transparency |
| `raw_json` | json | full API response for audit/reprocessing |
| `fetched_at` | timestamp | |
| `created_at` / `updated_at` | timestamps | |

**Unique constraint:** `(geoid, summary_level, vintage)`. One immutable row per geography per ACS vintage. New vintage → new row; old rows retained for reproducibility.

**Table B — `property_census_links`** (attaches a listing to its geographies; mirrors the `property_location_dna` (listing_type, listing_id) pattern exactly for consistency)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `listing_type` | string | `seller` \| `landlord` \| `bridge` \| etc. — same vocabulary as `property_location_dna` |
| `listing_id` | bigint | |
| `tract_geoid` | string nullable | resolved tract |
| `county_geoid` | string nullable | resolved county (comparison) |
| `state_geoid` | string nullable | resolved state (comparison) |
| `resolution_source` | string | `geocoder` \| `zcta_fallback` |
| `resolution_status` | string | `pending` \| `resolved` \| `failed` |
| `vintage` | string | vintage the link was resolved against |
| `resolved_at` | timestamp nullable | |
| `error` | text nullable | |
| `created_at` / `updated_at` | timestamps | |

**Unique constraint:** `(listing_type, listing_id)`.

**Table C — `census_profile_audits`** (append-only, mirrors `property_location_dna_audits`)
`listing_type, listing_id, event_type, status, source, input_snapshot(json), output_snapshot(json), error, created_at`. Model blocks `update()`/`delete()` like `PropertyLocationDnaAudit`. Gives us the same immutable provenance trail Location DNA already has.

### 4.3 Cache strategy (two layers)
1. **Persistence layer (source of truth):** `census_area_profiles`. A property page reads from here via the `property_census_links` join. **Zero external calls on render.**
2. **HTTP-fetch dedup layer:** when a fetch *is* needed, wrap the adapter in the existing `Cache` pattern (same as `FemaFloodZoneAdapter`/`CensusTigerBoundaryAdapter`, 24h TTL) keyed by `geoid+summary_level+vintage`. This only matters during backfill/refresh storms, not steady state.

### 4.4 Refresh frequency & attaching data to a property
- **Geography resolution** (lat/lng → GEOID) runs **once per listing** when the listing is geocoded — reuse `property_location_dna.geocoded_lat/lng` so we never geocode twice. Re-resolve only if the listing address changes.
- **ACS figure fetch** runs **once per (geography, vintage)**, shared across every property in that tract.
- **Annual refresh:** a `census:refresh-vintage {year}` command run after each December ACS release inserts new-vintage rows and re-points active links. Old vintages stay for audit. No per-listing churn.

---

## 5. Service Architecture

Mirrors the existing Location DNA adapter/pipeline/job/command shape so it slots into patterns the team already maintains.

```
app/Contracts/
  CensusDataAdapterInterface.php          fetchAcsProfile(geoid, level, variables[], vintage): array
app/Services/Census/
  CensusAcsApiAdapter.php                 implements interface; calls api.census.gov; allowlist-only var codes
  CensusGeographyResolver.php             lat/lng → tract/county/state GEOID (Census geocoder; reuses LDNA coords)
  CensusVariableMap.php                   the §3 allowlist (code → normalized field); single source of truth
  CensusProfileService.php                orchestrates resolve → fetch(tract,county,state) → normalize → persist
  CensusNeutralLanguageService.php        formats figures into neutral factual strings; BANNED-TERM guard
  CensusViewMapper.php                    persisted rows → Blade-safe arrays for UI components
app/Jobs/
  ComputeCensusProfile.php                queued (listing_type, listing_id); mirrors ComputeLocationDna
app/Console/Commands/
  CensusGenerate.php                      census:generate {listing_type} {listing_id}
  CensusRefreshVintage.php                census:refresh-vintage {year} [--dry-run]
  CensusAuditListing.php                  census:audit-listing {id} [--listing-type=]
config/
  census.php                              api key, base url, vintage, var map ref, cache ttl, FEATURE FLAGS
```

### 5.1 Feature-flag gate (reuse the proven kill-switch pattern)
`config/census.php` mirrors `config/bya_compatibility.php`:
```php
'kill_switch'        => (bool) env('CENSUS_INTELLIGENCE_KILL_SWITCH', true),   // default = OFF
'ga_enabled'         => (bool) env('CENSUS_INTELLIGENCE_GA_ENABLED', false),
'rollout_percentage' => (int)  env('CENSUS_INTELLIGENCE_ROLLOUT_PERCENTAGE', 0),
'allowed_user_ids'   => json_decode(env('CENSUS_INTELLIGENCE_ALLOWED_USER_IDS', '[]'), true) ?? [],
'vintage'            => env('CENSUS_ACS_VINTAGE', '2023'),
'api_key'            => env('CENSUS_API_KEY'),
```
A `CensusAccessResolver` modeled on `ByaCompatibilityAccessResolver::resolve()` gates **all** consumer-facing rendering and Ask AI inclusion. Default state is fully OFF.

---

## 6. UI Component Plan

New Blade components under `resources/views/components/stellar/`, following the existing `matchmaker-*` naming, all reading from `CensusViewMapper`. **All rendered inside a single, clearly-labeled card: "Community Context — U.S. Census (ACS [vintage])," visually and structurally separate from Location DNA.**

| Component | Requested section | Content (all with tract + county comparison) |
|---|---|---|
| `matchmaker-census-snapshot` | **Community Snapshot** | population, density, median age — top-line factual header |
| `matchmaker-census-housing` | **Housing Profile** | owner/renter %, median home value, median gross rent |
| `matchmaker-census-age` | **Age Distribution** | median age + (optional) coarse age-band bars from `B01001` aggregates |
| `matchmaker-census-education` | **Education** | % bachelor's or higher (25+) |
| `matchmaker-census-household` | **Household Profile** | avg household size, family-household % *(flag — §10.2)* |
| `matchmaker-census-income` | **Income** | median household income |
| `matchmaker-census-commute` | **Employment / Commute** | mean commute minutes, labor-force participation, unemployment % |
| `matchmaker-census-affordability` | **Housing Affordability** | price-to-income & rent-to-income ratios (factual; §3.1) |
| `matchmaker-census-insights` | **Neutral Community Insights** | auto-generated **neutral factual sentences only** from `CensusNeutralLanguageService` |
| (data only) | **Target Market DNA support** | **See §11.3 — re-scoped to housing-stock facts or declined; NOT a UI demographic targeter** |

Each component renders: the figure, the comparison figure(s), the source citation, and the ACS margin-of-error note where the margin is material. Reuse the existing `matchmaker-category-bars` bar pattern for distributions. The pre-existing `matchmaker-commute` (Location DNA commute stub) is **left untouched**; the census commute component is separately named to avoid collision.

---

## 7. Neutral-Language & Presentation Rules

`CensusNeutralLanguageService` is the only component allowed to turn figures into sentences. It enforces:

**Banned-term guard (hard fail in tests + runtime assertion):** the strings `good`, `bad`, `safe`, `unsafe`, `dangerous`, `wealthy`, `affluent`, `poor`, `desirable`, `prestigious`, `up-and-coming`, `better`, `worse`, `nice`, `bad area`, `family-friendly`, `working-class`, `elite` (and similar) may never appear in generated output. A unit test asserts the generator's full output vocabulary against an allowlist.

**Mandatory framing rules:**
1. Every figure states the source and vintage ("ACS 2023 5-year estimate").
2. Every neighborhood figure is paired with the county figure and stated comparatively in **directional, non-evaluative** language: "higher than," "lower than," "about the same as" — never "better/worse."
3. No figure is ever used to characterize the *people* — only the *area's published statistics*. "The tract's median household income is $X" not "residents here are well-off."
4. Margins of error shown when the estimate is imprecise; suppressed figures shown as "not published for this area," never imputed.
5. No ranking, percentile, grade, star, or color-coded good/bad scale.

---

## 8. Compliance Summary (Fair Housing)

| Rule | Implementation |
|---|---|
| Present as neutral factual context | §7 neutral-language service; banned-term guard |
| No good/bad/safe/unsafe/wealthy/poor labels | §7 banned-term hard fail |
| Avoid protected-class targeting | §3.2 excluded-variable allowlist (race, ethnicity, origin, religion, disability, sex never fetched); §11.3/§11.4 keep demographics out of scoring & targeting |
| County/state comparison only as factual context | §2.3 mandatory comparison framing |
| Familial-status edge | §10.2 — "family households %" flagged for owner decision |
| Provenance / auditability | §4.2 Table C append-only audit; raw_json retained |
| Reversibility | §5.1 kill switch defaults ON (feature OFF) |

---

## 9. Integration Plan

### 9.1 Location DNA (display adjacency only)
Render the Census card **on the same property detail page** (`resources/views/stellar/property/detail.blade.php`) but in its **own section**, never merged into Location DNA's POI/flood/commute blocks. Add to `StellarPropertyDetailController::show()` a separate `CensusViewMapper::map($listingType, $listingId)` call producing a separate `$census` view payload, gated by `CensusAccessResolver`. **No change to `LocationDnaSummaryService` or any LDNA service.** Amend the Location DNA Phase A doc to record that Census Intelligence is a separate sibling module (closing the §0 conflict on paper).

### 9.2 Property DNA (neutral context, not scoring)
`PropertyDnaGenerator` produces *coverage* metrics and marketing tags from the listing's own attributes. Census data may be exposed to the property-detail/marketing **display** layer as neutral area context, but **must not** feed `ai_buyer_archetype_tags`, `ai_marketing_hooks`, or any `*_score`. Marketing copy that characterizes the neighborhood's people is an FHA risk; keep census strictly as cited factual context.

### 9.3 Target Market DNA — **re-scope or decline (HIGH RISK)**
There is no existing "Target Market DNA" entity; the buyer/tenant DNA profile is the demand side. A demographic "target market" built from census data — "market this to [age/income/family] profiles" — is **textbook FHA steering and disparate-impact exposure.** Recommendation:
- **Do not** build demographic audience targeting.
- If a "market context" feature is desired, **restrict it to housing-stock facts** (owner/renter mix, median value, median rent, vacancy) framed neutrally as "the local housing market," with **no person-level demographics** (age, income-of-residents, education, family composition).
- This re-scope requires explicit owner sign-off; absent that, **decline** the item.

### 9.4 Buyer/Tenant Match Score — **excluded (HIGH RISK)**
The four `*BidMatchScoreHelper` classes + `config/match_scoring.php` (enabled weights must sum to 100) drive consumer-facing match scores. **Injecting census demographics into match scoring means the platform numerically recommends/discourages properties based on who lives nearby — the exact "Core FHA violation" the Location DNA doc names.** Recommendation:
- **No `census_*` dimension is added to `config/match_scoring.php`.**
- Census figures may appear in the **explanation/context** surrounding a score (clearly labeled neutral context) but **never as a weighted input** to the score.
- Building any scoring integration requires written legal approval and is **out of scope for Phase 5.3 implementation.**

### 9.5 Ask AI (context with strict refusal rules)
Extend the Ask AI context assembly (`PropertyMatchContextService` / the `AskAiRunnerV2Service` context layer; see `ask-ai-context-source-matrix.md`) to optionally include the property's persisted census figures **as a cited, neutral source block**, gated by `CensusAccessResolver`. Required guardrails in the Ask AI prompt/refusal contract:
- System prompt instructs: present census figures only as cited facts with comparison; never evaluate, rank, or characterize residents.
- **Refusal rules** for steering-shaped questions: "is this a good/safe area?", "what kind of people live here?", "is this a family neighborhood?", "is this area going up/down?" → refuse with the existing refusal-message mechanism + offer the neutral factual figures instead.
- Source attribution must cite "U.S. Census Bureau ACS [vintage]."
- Add Ask AI test cases (extend `tests/Feature/AskAiListingQuestionTest.php`) asserting steering questions are refused and neutral figures are sourced.

---

## 10. Open Decisions Requiring Owner / Legal Sign-Off

1. **§0 core question:** Approve a *separate, neutrally-governed* Census Intelligence module despite Location DNA Phase A prohibiting census data within Location DNA? (Blocks everything.)
2. **§9.4:** Confirm Match Score integration is **excluded** (recommended) — or escalate to legal if desired.
3. **§9.3:** Decline demographic "Target Market DNA," or approve a housing-stock-only re-scope?
4. **§10.2 below:** Keep or drop the "family households %" metric.

### 10.2 The "family households %" caveat
"Family households %" and, to a lesser degree, "average household size" are the closest of the requested metrics to a **familial-status** proxy (a protected class under FHA). They are published ACS aggregates and are defensible as neutral facts, but they carry more risk than the others. **Recommendation:** ship the first release **without** family-household %; add it later only if the owner explicitly approves and the neutral-language framing is reviewed. Average household size is lower risk and can stay.

---

## 11. Risk Register (condensed)

| Risk | Severity | Mitigation |
|---|---|---|
| Census in Match Score → steering | **Critical** | §9.4 exclusion; no config dimension |
| Demographic Target Market DNA → steering/disparate impact | **Critical** | §9.3 decline/re-scope |
| Evaluative language leaks ("nice area") | High | §7 banned-term hard-fail test |
| Protected-class variables fetched | High | §3.2 fetch allowlist; no path to excluded tables |
| Bare neighborhood figure read as a verdict | Medium | §2.3 mandatory county/state comparison |
| Stale ACS vintage | Low | §4.4 annual refresh command; vintage stamped on every figure |
| External API in render path | Low | §4.3 persist-first; zero calls on render |

---

## 12. Phased Implementation Task List (for a FUTURE, separately-approved build)

**No work below begins without resolving §10.1.** Kill switch ships ON (feature OFF) throughout.

**Phase 5.3.A — Governance close-out**
1. Owner/legal decision on §10 open questions; record outcome.
2. Amend `LOCATION_DNA_PHASE_A...` doc to reference Census Intelligence as a separate sibling module.

**Phase 5.3.B — Config & contracts (no behavior)**
3. Add `config/census.php` (flags default OFF, vintage, var-map reference).
4. Add `CensusDataAdapterInterface` + `CensusVariableMap` (the §3 allowlist).
5. Pin variable codes against `variables.json` for the chosen vintage (fixture).

**Phase 5.3.C — Data layer**
6. Migrations: `census_area_profiles`, `property_census_links`, `census_profile_audits` (run `migrate --pretend` first).
7. Models incl. append-only audit model (block update/delete).

**Phase 5.3.D — Fetch & resolve (read-only, no UI)**
8. `CensusGeographyResolver` (lat/lng → GEOID, reuse LDNA coords).
9. `CensusAcsApiAdapter` (allowlist-only fetch, 24h HTTP cache).
10. `CensusProfileService` (resolve → fetch tract+county+state → normalize → persist).
11. `ComputeCensusProfile` job + `census:generate` / `census:audit-listing` commands.
12. Unit tests: normalization, density derivation, suppression handling, margins.

**Phase 5.3.E — Neutral language & guardrails**
13. `CensusNeutralLanguageService` + banned-term hard-fail test (vocabulary allowlist).
14. `CensusViewMapper`.

**Phase 5.3.F — UI (behind flag)**
15. The `matchmaker-census-*` components (§6), single labeled card, comparisons + citations.
16. Wire into `StellarPropertyDetailController::show()` behind `CensusAccessResolver`.

**Phase 5.3.G — Ask AI (behind flag)**
17. Add census source block to Ask AI context; add steering-question refusal rules.
18. Extend `AskAiListingQuestionTest` with steering-refusal + neutral-source assertions.

**Phase 5.3.H — Refresh ops**
19. `census:refresh-vintage {year}` annual command + runbook entry.

**Phase 5.3.X — GATED, NOT in default scope**
20. *(Only with written legal approval)* any Match Score / Target Market DNA integration — **recommended: do not build.** See §9.3, §9.4.

---

## 13. Summary

Census Intelligence is buildable as a **safe, neutral, factual community-context layer** for the property detail page and Ask AI — but only as a module that is **governance-separate from Location DNA**, fetches **only non-protected ACS aggregates**, presents every figure with **mandatory county/state comparison and neutral language**, and **stays out of match scoring and demographic targeting.** The two highest-value-sounding asks (Match Score and Target Market DNA) are the two highest-FHA-risk asks; this plan recommends excluding them and routes the value instead into neutral display + carefully-guarded Ask AI context. Nothing here is built until the §0 governance conflict is resolved by the owner.
