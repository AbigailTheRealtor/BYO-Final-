# Stellar Buyer Results Page Architecture

> **Document type:** Architecture plan — documentation only  
> **Date:** 2026-06-16  
> **Status:** Documentation only — no code, routes, controllers, Livewire components, Blade views, or migrations are included in or implied by this document  
> **Source documents:**  
> - `docs/audits/STELLAR_BUYER_MATCHING_IMPLEMENTATION_PLAN.md`  
> - `docs/audits/STELLAR_BUYER_MATCHING_ENGINE_ARCHITECTURE.md`  
> - `docs/audits/STELLAR_FINAL_LAUNCH_ARCHITECTURE_AND_IMPLEMENTATION_PLAN.md`  
> - `app/Services/Stellar/Matching/BuyerMatchService.php`  
> - `app/Services/Stellar/Matching/DTO/BuyerCriteriaPayload.php`  
> - `app/Services/Stellar/Matching/DTO/BuyerMatchResult.php`  
> - `routes/web.php`

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Route and Access Strategy](#2-route-and-access-strategy)
3. [Data Flow](#3-data-flow)
4. [Criteria Source Mapping](#4-criteria-source-mapping)
5. [Results Card Layout](#5-results-card-layout)
6. [Match Explanation UI](#6-match-explanation-ui)
7. [Sorting and Filtering](#7-sorting-and-filtering)
8. [Pagination and Performance](#8-pagination-and-performance)
9. [Empty States](#9-empty-states)
10. [Compliance and Data Boundaries](#10-compliance-and-data-boundaries)
11. [Mobile Layout](#11-mobile-layout)
12. [Rollout Plan](#12-rollout-plan)
13. [Implementation Checklist](#13-implementation-checklist)

---

## 1. Executive Summary

### Purpose

The Stellar Buyer Results Page is the first buyer-facing surface that exposes live Stellar MLS listing data. It consumes `BuyerMatchService::match()` results and renders them as ranked property cards with per-listing match scores, category breakdowns, human-readable match explanations, caution flags, and tradeoff disclosures.

This page is the **Phase B delivery** in the six-phase rollout plan (see Section 12). It is the gateway through which buyers first interact with the Stellar MLS matching engine as a product feature.

### What This Page Is

- A scored, ranked display of active Stellar MLS listings matched against a buyer's saved criteria
- A consumer of the existing `BuyerMatchService` + `BuyerMatchResult` DTO pipeline
- The first buyer-facing MLS matching experience in the platform

### What This Page Is Not

- **Not** the recommendation engine (adjacent/stretch listings outside strict criteria — that is Phase F)
- **Not** the alert / notification system (new-match email delivery — that is Phase E)
- **Not** the Ask AI layer (natural-language Q&A about individual listings — that is Phase F)
- **Not** a replacement for the admin diagnostic route, which remains a separate, admin-only tool

### Scoring Model Summary

The results page displays scores produced by a 100-point model across 7 categories:

| Category | Weight |
|---|---|
| Location | 30 |
| Price | 25 |
| Size | 15 |
| Property Type | 10 |
| Amenities | 10 |
| Financial / Fees | 5 |
| Lifestyle / Context | 5 |
| **Total** | **100** |

Listings are sorted by `total_score DESC` by default.

**Phase 1 scoring cap — 89 / 100:** The live engine in Phase 1 can score a maximum of 89 out of 100 points. Two sub-dimensions are unavailable until Phase 2 column promotions land: the Location sub-market and subdivision bonuses (6 points combined, requiring `mls_area_major` and `subdivision_name` native columns) and the Price reduction signal (5 points, requiring `original_list_price`). The results page architecture is designed around the eventual full 100-point model; during Phase B and Phase C the displayed score will reflect the 89-point Phase 1 ceiling, and relative ranking between listings remains valid within that constraint.

---

## 2. Route and Access Strategy

### 2.1 — Recommended Future Routes

The following routes are proposed for implementation. None exist in `routes/web.php` today.

| Route | Method | Name | Auth | Description |
|---|---|---|---|---|
| `/stellar/buyer/results` | GET | `stellar.buyer.results` | Required (`auth` middleware) | Primary buyer-facing results page — shows ranked Stellar listings matched against the authenticated buyer's active criteria record |
| `/stellar/buyer/results/{criteriaId}` | GET | `stellar.buyer.results.criteria` | Required (`auth` middleware) | Results scoped to a specific saved buyer criteria record (for buyers with multiple criteria sets) |
| `/stellar/buyer/results/preview` | GET | `stellar.buyer.results.preview` | Admin only | Admin preview route — wraps the same view with a test criteria payload; allows admin to verify scoring output before Phase B goes live |

The existing admin diagnostic route (registered separately in a prior task) is **not replaced** by these routes. The admin diagnostic route is a developer/QA tool; the routes above are the buyer-facing product surface.

### 2.2 — Controller Strategy

Two implementation options are acceptable:

**Option A — Standard Laravel Controller**  
A `StellarBuyerResultsController` handles the GET request, loads the buyer's criteria record, constructs a `BuyerCriteriaPayload`, invokes `BuyerMatchService::match()`, and passes the `Collection<BuyerMatchResult>` to a Blade view.

**Option B — Livewire Component**  
A `StellarBuyerResults` Livewire component owns the page, handles sort/filter state reactively without full-page reloads, and supports inline pagination. This is recommended for Phase C onward when live filter toggling and save/favorite actions are added.

**Recommendation:** Start with Option A (plain controller + Blade) for Phase B to unblock user testing quickly. Refactor to Livewire in Phase C when interactive filtering is added.

### 2.3 — First Launch Strategy

Phase A (admin preview) must be complete and signed off before Phase B (authenticated buyer preview) ships. The sequence is:

1. Admin previews results via the admin diagnostic route (already exists)
2. Admin approves scoring quality and card layout via Phase A proof
3. Phase B routes are registered and buyer-facing view is deployed
4. Phase C connects saved buyer criteria records from the buyer's wizard

---

## 3. Data Flow

The full chain from buyer criteria to page render:

```
Authenticated buyer visits /stellar/buyer/results
  │
  ├── Controller / Livewire: Load buyer's active BuyerCriteriaAuction record
  │     → Query buyer_criteria_auctions WHERE user_id = auth()->id()
  │       AND status = 'active' (or equivalent)
  │
  ├── Criteria Loader: Map BuyerCriteriaAuction fields to BuyerCriteriaPayload array
  │     → Explicit boolean required: is_55_plus_eligible
  │     → Explicit array required: property_types (non-empty)
  │     → All other fields: null if not set by buyer
  │
  ├── BuyerCriteriaPayload::__construct($data)
  │     → Validates required fields (throws InvalidArgumentException if invalid)
  │     → Casts and normalizes all inputs to typed readonly properties
  │
  ├── BuyerMatchService::match($criteria, candidateCap=200)
  │     │
  │     ├── BuyerMatchQueryBuilder::build($criteria, candidateCap)
  │     │     → SQL WHERE on native columns only (no raw_json in WHERE)
  │     │     → Hard filters: standard_status, property_type, list_price,
  │     │       bedrooms_total, bathrooms_total_integer, senior_community_yn
  │     │     → Geographic filter: city/ZIP/county OR lat/lng bounding box
  │     │     → LIMIT = candidateCap × 1.25 (over-fetch buffer for IDX removal)
  │     │     → Returns Eloquent Builder
  │     │
  │     ├── $query->get()  (Layer 1 DB query → BridgeProperty collection)
  │     │
  │     ├── IDX post-filter (PHP): exclude IDXParticipationYN = false listings
  │     │     → Reads raw_json per record (O(n) on small candidate set)
  │     │     → Silent exclusion — not surfaced to buyer
  │     │
  │     ├── $candidates->take($candidateCap)
  │     │
  │     ├── BuyerMatchScorer::scoreAll($candidates, $criteria)
  │     │     → Per-record raw_json extraction for Tier 2 fields
  │     │     → Haversine distance per record
  │     │     → 7-category scoring (100 points total)
  │     │     → Returns BuyerMatchResult[] (unsorted)
  │     │
  │     ├── BuyerMatchResultBuilder::buildAll($results, $criteria)
  │     │     → Assembles 4 explanation blocks per result:
  │     │       why_this_matches, tradeoffs, caution_flags, missing_data
  │     │
  │     └── sort by total_score DESC → collect() → return Collection<BuyerMatchResult>
  │
  ├── View Model Mapper: Transform Collection<BuyerMatchResult> for Blade
  │     → Extract display fields from BridgeProperty (address, city, price, beds, etc.)
  │     → Apply compliance rules (suppress Tier 6 fields)
  │     → Format currency, score display, category breakdowns
  │
  └── Blade View: Render result cards + explanation sections
        → Paginated result list
        → Per-card: photo placeholder, address, score, category bar, CTA buttons
        → Expandable explanation accordion per card
```

### 3.1 — Criteria Loader Responsibility

The criteria loader sits between the controller and `BuyerCriteriaPayload`. Its job is to translate the platform's `BuyerCriteriaAuction` (and associated EAV meta keys) into the flat array that `BuyerCriteriaPayload::__construct()` accepts.

The loader must:
- Always set `is_55_plus_eligible` to an explicit `bool` (default `false` if not found)
- Always provide `property_types` as a non-empty array (abort with an empty-state page if not set)
- Translate city/county/ZIP multi-value meta fields into arrays
- Translate Location DNA radius/polygon data if the buyer has a saved DNA profile

### 3.2 — View Model Mapper Responsibility

The view model mapper sits between `Collection<BuyerMatchResult>` and the Blade view. Its job is display formatting:
- Format `list_price` as currency (`$360,000`)
- Format `total_score` as a percentage display (`72 / 100` or `72%`)
- Clamp category scores to `[0, max_weight]` for display bar width calculations
- Strip `fields_used` arrays from explanation entries (internal metadata — not rendered in UI)
- Apply compliance rules: verify no Tier 6 field value is exposed in any label or description

---

## 4. Criteria Source Mapping

This section documents how each buyer criteria field maps from the platform's `BuyerCriteriaAuction` record into `BuyerCriteriaPayload` properties. The DTO field names are the canonical source of truth; see `BuyerCriteriaPayload.php` for types.

### 4.1 — Location Criteria

| `BuyerCriteriaPayload` Field | Source | Notes |
|---|---|---|
| `preferred_cities` | Buyer criteria wizard — city selection step | Multi-value; stored as comma-separated or JSON in meta; loader must produce `string[]` |
| `preferred_zip_codes` | Buyer criteria wizard — ZIP code step | Multi-value; loader must produce `string[]` |
| `preferred_counties` | Buyer criteria wizard — county selection | Multi-value; loader must produce `string[]` |
| `radius_searches` | Location DNA profile — radius entries | Each entry: `{center: {lat, lng}, radius_miles: float}`; loaded from buyer's DNA profile if present |
| `polygons` | Location DNA profile — polygon entries | Each entry: `{path: [{lat, lng}, ...]}`; loaded from buyer's DNA profile if present |
| `preferred_subdivisions` | Buyer criteria wizard — subdivision field | Phase 2 only; `string[]`; award 0 points if `subdivision_name` native column not yet present |
| `preferred_mls_areas` | Buyer criteria wizard — MLS area field | Phase 2 only; `string[]`; award 0 points if `mls_area_major` native column not yet present |

### 4.2 — Price Criteria

| `BuyerCriteriaPayload` Field | Source | Notes |
|---|---|---|
| `max_price` | Buyer criteria wizard — purchase price / max budget step | Hard filter ceiling; `null` means no ceiling (all prices pass) |
| `ideal_price` | Buyer criteria wizard — ideal/target price | Optional; used for proximity decay in price scoring |

### 4.3 — Property Type Criteria

| `BuyerCriteriaPayload` Field | Source | Notes |
|---|---|---|
| `property_types` | Buyer criteria wizard — property type step | Required non-empty `string[]`; e.g. `["Residential"]`; RESO standard values |
| `property_sub_types` | Buyer criteria wizard — subtype step | `string[]`; e.g. `["Single Family Residence", "Condominium"]`; soft scoring only |

### 4.4 — Size Criteria

| `BuyerCriteriaPayload` Field | Source | Notes |
|---|---|---|
| `min_bedrooms` | Buyer criteria wizard — bedrooms step | Hard filter; `null` if buyer has no minimum |
| `min_bathrooms` | Buyer criteria wizard — bathrooms step | Hard filter; `null` if buyer has no minimum |
| `min_sqft` | Buyer criteria wizard — living area step | Soft scoring lower bound |
| `max_sqft` | Buyer criteria wizard — living area step | Soft scoring upper bound |
| `min_lot_sqft` | Buyer criteria wizard — lot size step | Soft scoring lower bound |
| `max_lot_sqft` | Buyer criteria wizard — lot size step | Soft scoring upper bound |
| `year_built_min` | Buyer criteria wizard — year built / era step | Soft scoring lower bound |
| `year_built_max` | Buyer criteria wizard — year built / era step | Soft scoring upper bound |

### 4.5 — Amenity Preferences

| `BuyerCriteriaPayload` Field | Source | Notes |
|---|---|---|
| `wants_pool` | Buyer criteria wizard — pool preference step | `true` = prefers pool; `false` = does not want pool; `null` = no preference |
| `wants_garage` | Buyer criteria wizard — garage preference step | `null` = no preference |
| `min_garage_spaces` | Buyer criteria wizard — garage spaces field | Integer; only relevant when `wants_garage = true` |
| `wants_waterfront` | Buyer criteria wizard — waterfront step | `null` = no preference |
| `wants_water_view` | Buyer criteria wizard — water view step | `null` = no preference |
| `wants_any_view` | Buyer criteria wizard — view preference | `null` = no preference |

### 4.6 — Financial / HOA Criteria

| `BuyerCriteriaPayload` Field | Source | Notes |
|---|---|---|
| `max_monthly_hoa` | Buyer criteria wizard — HOA tolerance step | Soft scoring ceiling; `null` means no preference |
| `hoa_preference` | Buyer criteria wizard — HOA preference | `"required"`, `"none"`, or `null` |
| `cdd_preference` | Buyer criteria wizard — CDD preference | `"none"` = prefers no CDD; `null` = no preference; **never a scoring penalty** — CDD is a caution flag only |
| `max_monthly_total_burden` | Buyer criteria wizard — total monthly cost tolerance | Combined HOA + tax ceiling; soft scoring |

### 4.7 — Eligibility and Community Criteria

| `BuyerCriteriaPayload` Field | Source | Notes |
|---|---|---|
| `is_55_plus_eligible` | Buyer profile or criteria wizard | **Required `bool`** — must be explicit; **default `false`** if absent; drives the senior community hard filter (legal compliance gate) |
| `wants_pet_friendly` | Buyer criteria wizard — pet preference step | `null` = no preference |
| `wants_new_construction` | Buyer criteria wizard — construction preference | `null` = no preference |

### 4.8 — Lifestyle Criteria

| `BuyerCriteriaPayload` Field | Source | Notes |
|---|---|---|
| `community_feature_keywords` | Buyer criteria wizard — desired features step | `string[]`; e.g. `["Golf", "Tennis", "Clubhouse"]`; matched against `CommunityFeatures` and `AssociationAmenities` in `raw_json` |
| `wants_energy_efficient` | Buyer criteria wizard — energy efficiency preference | `null` = no preference |

---

## 5. Results Card Layout

Each matched listing is rendered as a card in the results list. Cards are ordered by `total_score DESC` (default).

### 5.1 — Card Structure (Per Listing)

```
┌─────────────────────────────────────────────────────────────┐
│ [Photo Placeholder / MLS Photo if IDX-compliant display      │
│  rules permit — Phase D; Phase B uses placeholder]           │
├─────────────────────────────────────────────────────────────┤
│ MATCH SCORE: 72 / 100           [Score badge — prominent]   │
│                                                              │
│ [Category score bar — 7 colored segments]                   │
│  Loc ████████░░  Price ██████░░  Size ████░░  ...           │
├─────────────────────────────────────────────────────────────┤
│ $360,000                        3 bed / 2 bath / 1,850 sqft │
│ 123 Main Street                                             │
│ Lake Nona, FL 32832             [Property Type / Subtype]   │
├─────────────────────────────────────────────────────────────┤
│ [Explanation accordion — collapsed by default]              │
│ ▶ Why this matches   ▶ Tradeoffs   ▶ Caution flags          │
├─────────────────────────────────────────────────────────────┤
│ [CTA Buttons]                                               │
│  [View Details]  [Save]  [Request Showing]  [Ask a Question]│
└─────────────────────────────────────────────────────────────┘
```

### 5.2 — Required Card Fields

| Field | Source | Display Format | Compliance |
|---|---|---|---|
| Photo placeholder | Static placeholder image | Displayed until Phase D MLS photo integration | Do not use agent/brokerage photo URLs — IDX photo rights must be confirmed before any MLS image is rendered |
| Address (street) | `BridgeProperty::unparsed_address` | Full street address | See safe location display rules in Section 5.3 |
| City, State, ZIP | `BridgeProperty::city`, `state_or_province`, `postal_code` | "Lake Nona, FL 32832" | Always display; never suppress |
| List price | `BridgeProperty::list_price` | Currency: "$360,000" | Always display; never suppress |
| Bedrooms | `BridgeProperty::bedrooms_total` | "3 bed" | Display "—" if null |
| Bathrooms | `BridgeProperty::bathrooms_total_integer` | "2 bath" | Display "—" if null |
| Living area | `BridgeProperty::living_area` | "1,850 sqft" | Display "—" if null |
| Property type | `BridgeProperty::property_type` | "Residential" | Always display |
| Property subtype | `BridgeProperty::property_sub_type` | "Single Family Residence" | Display only if populated |
| Total match score | `BuyerMatchResult::totalScore` | "72 / 100" or "72%" | Always display |
| Category score breakdown | `BuyerMatchResult::categoryScores` | Proportional bar or numeric array | Display all 7 category scores |
| Listing key / ID | `BuyerMatchResult::listingKey` | Internal only — used for detail page linking; never rendered in UI | Do not expose `listing_key` or `listing_id` as visible text |

### 5.3 — Compliance-Safe Address Display Rules

- The street address (`unparsed_address`) is IDX-displayable for all listings where `IDXParticipationYN = true`. All listings reaching the results page have passed the IDX gate, so street addresses are safe to display.
- Never append agent name, brokerage name, agent phone, or agent email to the address — all are Tier 6 fields and must never appear in any buyer-facing surface.
- Listings where `unparsed_address` is null should display "Address not available — contact listing agent."

### 5.4 — CTA Buttons

| Button | Phase Available | Action |
|---|---|---|
| View Details | Phase B | Navigate to a per-listing detail page (URL TBD; may link to external IDX detail or an internal proxy page — to be determined in Phase D implementation task) |
| Save / Unsave | Phase D | Toggle the listing into the buyer's saved/favorited listings list |
| Request Showing | Phase D | Open a showing request form (integrates with existing `ShowingController`) |
| Ask a Question | Phase F | Opens the Ask AI interface pre-seeded with the listing's context |

In Phase B, only the **View Details** button is functional. Save, Request Showing, and Ask a Question may be rendered as disabled or hidden pending their respective phase implementations.

---

## 6. Match Explanation UI

Each card carries an expandable explanation section. The four blocks from `BuyerMatchResult` map to distinct UI accordion panels.

### 6.1 — Block 1: "Why This Matches" Panel

**Data source:** `BuyerMatchResult::whyThisMatches` — array of `{dimension, label, fields_used, score_contribution}` objects

**Display rules:**
- Render only entries where `score_contribution > 0`
- Display in descending order by `score_contribution` (strongest signal first)
- Show the `label` string only — never render `fields_used` or `dimension` keys as visible text
- Each entry is a bullet or icon row: `✓ In Lake Nona, your preferred area (+24 pts)`
- Show the score contribution as a secondary annotation in muted text

**Panel header:** "Why this matches" with a green checkmark icon

**Empty state:** Hide the panel if `whyThisMatches` is empty (no positive signals — unlikely but possible for a very low-scoring listing that passed hard filters).

### 6.2 — Block 2: "Tradeoffs" Panel

**Data source:** `BuyerMatchResult::tradeoffs` — array of `{dimension, label, fields_used, deviation}` objects

**Display rules:**
- Render only where a buyer preference was expressed and the listing scores below maximum for that dimension
- Show the `label` string only — never render `deviation` machine codes as visible text
- Each entry is a bullet: `~ Price is 5% above your ideal — at the upper end of your range`
- Use a neutral (amber/yellow) icon to distinguish from positive signals

**Panel header:** "Tradeoffs" with an amber adjustment icon

**Empty state:** Hide the panel if `tradeoffs` is empty (listing met all buyer preferences).

### 6.3 — Block 3: "Caution Flags" Panel

**Data source:** `BuyerMatchResult::cautionFlags` — array of `{type, severity, label}` objects

**Severity styling:**

| Severity Value | Visual Treatment | Icon |
|---|---|---|
| `"info"` | Blue info banner / muted text | ℹ️ info circle |
| `"warning"` | Amber warning banner | ⚠️ warning triangle |
| `"critical"` (reserved for future use) | Red banner | 🚫 stop circle |

**Display rules:**
- Always render caution flags if present — they must never be hidden
- Show the `label` string only — never render the `type` machine code as visible text to buyers
- CDD caution flags must use neutral language: "This community has a Community Development District (CDD). Annual CDD fees apply in addition to HOA and property taxes." — never characterize CDDs as negative or inferior
- Flood zone caution flags must not speculate on risk — only state that data is absent and recommend the buyer verify with the listing agent

**Panel header:** "Things to know" with an info icon (avoid "Warning" or "Caution" as the label — neutral framing reduces buyer anxiety from informational flags)

**Empty state:** Hide the panel if `cautionFlags` is empty.

### 6.4 — Block 4: "Missing Information" Panel

**Data source:** `BuyerMatchResult::missingData` — array of `{field, label}` objects

**Display rules:**
- Render as a compact list below the caution flags panel or inline within the relevant score category breakdown
- Use the `label` string only — never the `field` Stellar/column name
- Example: "HOA fee amount not listed — verify with listing agent"
- Frame as informational, not as a score penalty explanation

**Panel header:** "Missing listing data" with a neutral icon

**Empty state:** Hide the panel if `missingData` is empty.

### 6.5 — Default Accordion State

On initial page load, all four explanation panels are **collapsed**. The buyer sees only the card header (photo placeholder, score badge, category bar, address, price, beds/baths/sqft, CTA buttons). Expanding a card's explanation section reveals the four panels.

Only the highest-scoring result card (rank #1) may optionally be auto-expanded on first load to demonstrate the explanation feature.

---

## 7. Sorting and Filtering

### 7.1 — Default Sort

Results are displayed sorted by `total_score DESC` on every page load. This is the only sort order available in Phase B.

### 7.2 — Future Sort Options (Phase C+)

| Sort Label | Sort Field | Direction | Notes |
|---|---|---|---|
| Best Match (default) | `total_score` | DESC | Always the default |
| Price: Low to High | `list_price` | ASC | |
| Price: High to Low | `list_price` | DESC | |
| Newest Listings | `modification_timestamp` | DESC | Uses the existing native column |
| Closest to Me | Location score category | DESC | Requires buyer's preferred center point |
| Best Price Fit | Price category score | DESC | |
| Largest Living Area | `living_area` | DESC | |
| Year Built: Newest | `year_built` | DESC | Requires Phase 1 column |

Sort changes must re-sort the already-retrieved `Collection<BuyerMatchResult>` in PHP — they must not trigger a new `BuyerMatchService::match()` call. The match pipeline is expensive and is run once per page load; sort/filter state is applied to the in-memory result set.

### 7.3 — Future Filters (Phase C+)

Filters narrow the already-retrieved result set in PHP (not via a new DB query):

| Filter | Type | Field(s) |
|---|---|---|
| Minimum match score | Range slider | `total_score` |
| Price range | Min/Max inputs | `list_price` |
| City | Multi-select | `city` |
| County | Multi-select | `county_or_parish` |
| ZIP code | Multi-select | `postal_code` |
| Has private pool | Toggle | `pool_private_yn` |
| Has garage | Toggle | `garage_yn` |
| Waterfront | Toggle | `waterfront_yn` |
| Any view | Toggle | `view_yn` |
| Max HOA fee | Slider | `association_fee` |
| No CDD | Toggle | `cdd_yn` (filter to `cdd_yn = false OR cdd_yn IS NULL`) |
| New construction only | Toggle | `new_construction_yn` |
| Property subtype | Multi-select | `property_sub_type` |

**Implementation note for CDD filter:** The "No CDD" toggle must filter to listings where `cdd_yn = false OR cdd_yn IS NULL` — it must never be presented as "Filter out CDD communities" with negative framing. Neutral label: "Exclude CDD communities."

### 7.4 — Filter State Persistence

Sort and filter state is ephemeral in Phase B (lost on page reload). Phase C may introduce URL query string persistence (`?sort=price_asc&filter[pool]=1`) for shareable filtered views.

---

## 8. Pagination and Performance

### 8.1 — Candidate Cap

`BuyerMatchService::match()` accepts a `candidateCap` argument (default: 200). The query builder fetches `candidateCap × 1.25` records from the database so that IDX post-filtering does not shrink the scoring pool below the requested cap.

**The results page must not increase `candidateCap` beyond 200** for Phase B and C. Scoring 200 listings in PHP per request is the designed maximum for synchronous, per-request matching. If buyer criteria produce more than 200 candidates that pass all hard filters, the query builder's `ORDER BY ABS(list_price - :buyer_ideal_price) ASC LIMIT 200` ensures the 200 most price-relevant candidates are scored first.

### 8.2 — Page Size

Display 20 result cards per page. With a maximum of 200 scored results, this yields a maximum of 10 pages. Pagination is server-side (Laravel `Paginator`) — the full 200 results are scored once, paginated in PHP, and only the current page's 20 cards are rendered per request.

### 8.3 — Server-Side Pagination

For Phase B (plain controller + Blade), use Laravel's `LengthAwarePaginator` on the scored `Collection`. The full match pipeline runs on every page load in Phase B — this is acceptable for the preview phase. Phase C should introduce caching (see Section 8.4).

For Phase C+ (Livewire), pagination state is held in Livewire component state. Sort/filter changes reset to page 1. The match pipeline result is re-used from cache.

### 8.4 — Cache Considerations

**Phase B:** No caching. The match pipeline runs on every page load. Acceptable for admin preview and initial buyer preview with a small user base.

**Phase C:** Cache the `Collection<BuyerMatchResult>` per buyer criteria record in the application cache (e.g., `Cache::remember("stellar_buyer_results_{$criteriaId}", 300, fn() => $service->match($criteria))`). A 5-minute TTL balances freshness with performance. Cache must be invalidated when:
- The buyer's criteria record is updated
- A new import batch adds or modifies listings that match the buyer's geographic area

**Critical rule:** `raw_json` must never be queried across the full `bridge_properties` table in any WHERE clause or ORDER BY at any time. All cross-table operations (match scoring, alert detection, sort operations) use native columns only for indexed operations. `raw_json` is read only O(1) per individual record after the candidate set is already retrieved.

### 8.5 — Performance Budget

| Operation | Acceptable Latency | Notes |
|---|---|---|
| Layer 1 SQL query (hard filters, native columns only) | < 100ms | Depends on Phase 1 indexes being present |
| IDX post-filter (PHP, per candidate) | < 20ms | O(n) on ≤ 250 records |
| Scorer (PHP, per candidate) | < 300ms | O(n) on ≤ 200 records; Haversine per record |
| Result builder (PHP, explanation assembly) | < 100ms | |
| Total pipeline (Phase B, no cache) | < 1.5s | Acceptable for preview; optimize in Phase C |
| Total pipeline (Phase C+, cached) | < 100ms | Cache hit path only |

---

## 9. Empty States

The results page must handle all failure and absence conditions gracefully. Each empty state has a specific message.

### 9.1 — No Matching Listings

**Trigger:** `BuyerMatchService::match()` returns an empty collection after all hard filters and IDX filtering.

**Message:**
> "No active listings match your current search criteria. Try widening your price range, expanding your location preferences, or relaxing your minimum bedroom or bathroom requirements."

**Actions:** Show "Edit My Criteria" button linking to the buyer criteria wizard.

### 9.2 — No Active Residential Inventory Imported

**Trigger:** The `bridge_properties` table has no rows with `standard_status = 'Active'` and `property_type = 'Residential'`.

**Message:**
> "Stellar MLS listing data is not yet available. Check back soon — our data import runs regularly and new listings will appear here shortly."

**Actions:** No CTA needed. Do not suggest editing criteria — the problem is the data layer, not the buyer's preferences.

### 9.3 — Buyer Criteria Incomplete

**Trigger:** The criteria loader finds no active `BuyerCriteriaAuction` record for the authenticated buyer, or `property_types` is empty (which would throw in `BuyerCriteriaPayload`).

**Message:**
> "Your buyer profile isn't complete yet. Set up your home criteria to see matched listings."

**Actions:** Show "Set Up My Criteria" button linking to the buyer criteria wizard's first step.

### 9.4 — Location DNA Missing (for Radius/Polygon Search)

**Trigger:** Buyer has no saved radius or polygon searches in their Location DNA profile, and `preferred_cities`, `preferred_zip_codes`, and `preferred_counties` are all empty — meaning the engine has no geographic constraint to apply.

**Message:**
> "Your search doesn't include a location yet. Add your preferred cities, ZIP codes, or draw a custom search area to see matched listings near you."

**Actions:** Show "Add My Preferred Locations" button linking to the location step of the buyer criteria wizard.

### 9.5 — Bridge API / Import Unavailable

**Trigger:** The `bridge_properties` table exists but no records have been imported (row count = 0), indicating the import pipeline has never run.

**Message:**
> "Listing data is being set up. Please check back shortly."

**Actions:** No CTA. This is an infrastructure state, not a user-correctable condition.

---

## 10. Compliance and Data Boundaries

These rules are non-negotiable. They apply to every layer of the results page — scoring, display, explanation text, and caching.

### 10.1 — Tier 6 Field Exclusion (Hard Rule)

No Tier 6 field may appear in any form on the results page or any downstream surface served by this pipeline. This includes:

- All agent PII: `ListAgentEmail`, `ListAgentPreferredPhone`, `ListAgentMlsId`, agent license numbers
- All brokerage information: `ListOfficeName`, `ListOfficePhone`, `ListOfficeEmail`, `ListOfficeMlsId`
- Lockbox fields: `LockBoxLocation`, `LockBoxType`, `LockBoxSerialNumber`
- Showing instructions: `ShowingInstructions`, `ShowingContactName`, `ShowingContactPhone`
- Internal MLS fields: `OriginalEntryTimestamp` (when used to infer listing age), all ~200 admin/commercial/internal fields classified Tier 6 in `STELLAR_MATCHING_READINESS_AUDIT.md`

The view model mapper must strip all Tier 6 fields before the Blade view receives the data. The Blade view must never access `BridgeProperty::raw_json` directly — it receives only the mapped view model array.

### 10.2 — IDX Participation Gate

Listings where `IDXParticipationYN = false` in `raw_json` must never appear in results. The IDX gate is already applied inside `BuyerMatchService::match()` before scoring. The results page must not bypass or re-filter this — if a listing is in the scored result set, it has already passed the IDX gate.

No "IDX-excluded listing" error or placeholder card should be rendered. Silent exclusion is correct.

### 10.3 — Senior Community Gate

Listings where `senior_community_yn = true` must only appear in results for buyers where `is_55_plus_eligible = true`. This gate is enforced at the SQL WHERE level by `BuyerMatchQueryBuilder`. The results page does not re-apply this gate — it trusts the match pipeline output.

`senior_community_yn` must never be used as a scoring input. It must never appear in any explanation block label. The senior community gate is a binary eligibility filter only.

### 10.4 — CDD Is Informational Only

CDD presence (`cdd_yn = true`) is a caution flag — it must never be treated as a scoring penalty or presented as a negative quality signal. Labels must be neutral:

- **Correct:** "This community has a Community Development District (CDD). Annual CDD fees apply in addition to HOA and property taxes."
- **Incorrect:** "CDD reduces your monthly affordability" / "Avoid CDD communities"

The `cdd_preference = "none"` buyer input does not result in a score penalty for CDD-present listings. A buyer who prefers no CDD may see CDD-present listings ranked lower (due to total financial burden scoring), but only because their `max_monthly_total_burden` tolerance is exceeded — not because CDD itself is penalized.

### 10.5 — No Protected Class Language

No explanation label, caution flag, tradeoff description, or empty state message may reference, imply, or be derived from:
- Race, color, national origin, religion, sex, familial status, or disability
- School demographics or neighborhood racial composition
- Proximity to houses of worship, ethnic markets, or culturally specific institutions

School information from `ElementarySchool`, `HighSchool`, or related `raw_json` fields is passed to Ask AI context only — it is not scored, not used in match explanations, and not rendered on the results page.

### 10.6 — HOA and CDD Are Informational Financial Data Only

HOA fee amounts and CDD information are disclosed as informational cost context. They are never presented as reasons to avoid a listing. The scoring model uses HOA/CDD data to evaluate financial burden fit against buyer tolerance — a factual, neutral calculation.

### 10.7 — No Private Agent or Brokerage Branding

The results page must not display listing agent names, brokerage logos, brokerage names, or agent photos from MLS data. IDX display rules for branding attribution vary by MLS agreement. Until the platform's IDX display agreement is confirmed and reviewed by legal counsel, all agent and brokerage attribution is suppressed. This applies to both the card layout and the detail page.

---

## 11. Mobile Layout

The results page must be usable on mobile viewports (min-width: 320px) without horizontal scrolling.

### 11.1 — Responsive Card Layout

**Desktop (≥ 1024px):** Two-column card grid. Each card occupies approximately 50% of the content area. Score badge and category bar are displayed inline with address/price.

**Tablet (768px – 1023px):** Single-column card list. Full-width cards. Photo placeholder takes 30% card height.

**Mobile (< 768px):** Stacked single-column card list. Photo placeholder takes 40% card height. Score badge is prominently displayed at top-right. Category bar collapses to a single horizontal strip with no individual labels. CTA buttons stack vertically.

### 11.2 — Sticky Score Header

On mobile, a sticky summary header at the top of the viewport displays:
- Result count: "28 listings match your criteria"
- Active sort label: "Sorted by: Best Match"
- Filter indicator: "Filters: 2 active" (Phase C+)

This header does not scroll with the page, allowing the buyer to maintain context while scrolling through results.

### 11.3 — Collapsible Explanation Sections

On mobile, the explanation accordion must be touch-friendly:
- Tap targets for accordion headers must be at least 44px tall
- Expanding one explanation panel does not auto-collapse others
- The "Why This Matches" panel is collapsed by default on mobile (to maximize visible card count)

### 11.4 — Future Map / List Toggle Hook

Phase C+ will add an option to toggle between list view and map view. The map view will render result cards on an interactive map using listing latitude/longitude coordinates.

The toggle button placeholder must be reserved in the page layout from Phase B onward to avoid a layout-breaking refactor in Phase C. It may be rendered as a disabled "Map View" button in Phase B.

---

## 12. Rollout Plan

Six sequential phases. Each phase is a separate implementation task. No phase begins before the previous phase is complete and signed off.

### Phase A — Admin Preview (via Diagnostic Route)

**Status:** Available now (admin diagnostic route exists from a prior task)

**Scope:**
- Admin visits the existing diagnostic route
- Verifies scoring quality, hard filter behavior, IDX gate, explanation block structure
- Confirms caution flag generation
- Signs off that the engine produces correct, compliant output before any buyer-facing surface is built

**Done looks like:** Admin can view raw `BuyerMatchResult` data for a test criteria payload and confirms scores, explanations, and caution flags are correct.

### Phase B — Authenticated Buyer Preview

**Scope:**
- Register `/stellar/buyer/results` route with `auth` middleware
- Create controller or Livewire component stub
- Create criteria loader (maps `BuyerCriteriaAuction` → `BuyerCriteriaPayload`)
- Create view model mapper (maps `Collection<BuyerMatchResult>` → Blade-safe array)
- Create Blade view: result card layout, score badge, category bar, explanation accordion, empty states
- Implement server-side pagination (20 per page)
- Default sort: `total_score DESC`
- Phase B CTA: "View Details" only (Save, Request Showing, Ask a Question are disabled/hidden)
- All compliance rules applied (Tier 6 suppression, no agent/brokerage branding)

**Done looks like:** An authenticated buyer with an active criteria record can navigate to `/stellar/buyer/results` and see a ranked list of matched Stellar listings.

### Phase C — Connect Saved Buyer Criteria Records

**Scope:**
- Support multiple saved criteria records per buyer (criteria selector / switcher)
- Register `/stellar/buyer/results/{criteriaId}` route
- Introduce result caching (5-minute TTL per criteria record)
- Add sort options (price, newest, sqft)
- Add filter panel (score threshold, city, pool, garage, waterfront, HOA max, CDD toggle)
- URL query string persistence for sort/filter state
- Refactor to Livewire if Option A was chosen in Phase B

**Done looks like:** Buyer can switch between saved criteria sets and filter/sort results without full-page reloads.

### Phase D — Save / Favorite / Listing Detail Actions

**Scope:**
- "Save" / "Unsave" listing toggle (persists to a `stellar_saved_listings` table or equivalent)
- "Request Showing" integrates with existing `ShowingController`
- Listing detail page or detail drawer showing full IDX-compliant listing data
- MLS photo display (requires IDX display rights confirmation)

**Done looks like:** Buyer can save a listing and request a showing directly from the results page.

### Phase E — New Match Alerts

**Scope:**
- Alert subscription infrastructure (`mls_alerts`, `alert_subscriptions` tables — per Stellar Final Launch Architecture)
- `new_match` alert type triggered after each import batch
- Email delivery to buyer when new listings score above threshold against their saved criteria

**Done looks like:** Buyer receives an email notification when a new Stellar listing matches their criteria above a configurable score threshold.

### Phase F — Recommendation Engine and AI Explanations

**Scope:**
- Recommendation engine (9 types, adjacent/stretch listings outside strict criteria)
- Ask AI integration: "Ask a Question" CTA activates Ask AI pre-seeded with the listing's context
- Natural-language explanation generation for match cards (Phase 3 Ask AI copy)
- Location DNA neighborhood signals integrated into recommendation engine's location adjacency score

**Done looks like:** Buyer can ask natural-language questions about a specific listing and sees AI-generated match explanation copy on result cards.

---

## 13. Implementation Checklist

This section enumerates every future implementation task required to bring the results page to production. Tasks are grouped by phase.

### Phase B Tasks

- [ ] **Route registration** — Register `GET /stellar/buyer/results` with `auth` middleware in `routes/web.php`
- [ ] **Admin preview route** — Register `GET /stellar/buyer/results/preview` with admin middleware
- [ ] **Controller** — Create `StellarBuyerResultsController` (or Livewire component `StellarBuyerResults`)
- [ ] **Criteria loader** — Create a `BuyerCriteriaLoader` service that reads a `BuyerCriteriaAuction` record (and its EAV meta) and constructs the `BuyerCriteriaPayload` array. Must handle: empty property_types, missing is_55_plus_eligible (default false), Location DNA profile lookup, all 28 DTO fields
- [ ] **View model mapper** — Create a mapper that transforms `Collection<BuyerMatchResult>` into a Blade-safe array, applies compliance rules (strips fields_used, strips Tier 6 values), formats currency, formats scores
- [ ] **Blade result card component** — `resources/views/components/stellar/buyer-result-card.blade.php`; includes: photo placeholder, score badge, category bar, address block, price/beds/baths/sqft, explanation accordion (4 panels), CTA buttons
- [ ] **Blade results page** — `resources/views/stellar/buyer/results.blade.php`; includes: page header with result count, sort selector (Phase B: default only), filter panel placeholder (hidden in Phase B), paginated card list, all empty states
- [ ] **Server-side pagination** — Use `LengthAwarePaginator` on the scored collection; 20 per page
- [ ] **Empty state views** — Implement all 5 empty states (Section 9) as inline Blade conditionals or sub-components
- [ ] **Compliance audit** — Manual review: confirm no Tier 6 field appears in any rendered output; confirm IDX gate is respected; confirm senior community gate is respected; confirm CDD is informational only
- [ ] **Admin proof** — Admin verifies Phase B output before buyer access is enabled

### Phase C Tasks

- [ ] **Multi-criteria route** — Register `GET /stellar/buyer/results/{criteriaId}`
- [ ] **Criteria switcher UI** — Dropdown or tab bar if buyer has multiple saved criteria records
- [ ] **Result caching** — Implement `Cache::remember()` per criteria record (5-minute TTL); implement cache invalidation on criteria update and on new import batch for buyer's geographic area
- [ ] **Sort options** — Implement all sort options from Section 7.2 as in-memory Collection sorts
- [ ] **Filter panel** — Implement all filters from Section 7.3 as in-memory Collection filters
- [ ] **URL query string persistence** — Encode sort/filter state in query string for shareable URLs
- [ ] **Livewire refactor** (if Phase B used plain controller) — Extract to `StellarBuyerResults` Livewire component for reactive sort/filter without full-page reloads
- [ ] **Map toggle hook** — Add disabled "Map View" button to layout (functional toggle deferred to a later sub-task)

### Phase D Tasks

- [ ] **Saved listings table** — Create `stellar_saved_listings` migration (or determine if an existing favorites mechanism can be extended)
- [ ] **Save/Unsave API endpoint** — `POST /stellar/buyer/listings/{listingKey}/save` and `DELETE /...`
- [ ] **Save toggle UI** — Integrate into result card; optimistic UI update
- [ ] **Request Showing integration** — Wire "Request Showing" CTA into existing `ShowingController::store()`; pre-fill listing address from `BridgeProperty`
- [ ] **Listing detail page** — Determine URL strategy; confirm IDX display rights; implement detail view
- [ ] **MLS photo display** — Confirm IDX photo rights with legal; implement photo URL extraction from `raw_json`; add to card and detail page

### Phase E Tasks

- [ ] **Alert tables** — Create `mls_alerts`, `alert_subscriptions`, `alert_dedup_log` migrations (per Stellar Final Launch Architecture Decision 6)
- [ ] **Alert subscription management UI** — Allow buyer to opt in/out of new match alerts per criteria record
- [ ] **New match detection** — Post-import job that re-runs match queries for all active subscriptions and queues alerts for new matches above threshold
- [ ] **Email delivery** — Implement `new_match` alert email (Mailable + queued job); respect CAN-SPAM unsubscribe

### Phase F Tasks

- [ ] **Recommendation engine** — Implement all 9 recommendation types (per Stellar Final Launch Architecture Decision 5); gated on Phase 2 native column promotions
- [ ] **Ask AI integration** — Wire "Ask a Question" CTA to Ask AI with listing context pre-seeded; confirm Phase 3 Ask AI context builder supports `BridgeProperty` records
- [ ] **Natural-language match explanations** — Generate Phase 3 Ask AI copy for `why_this_matches` labels on result cards
- [ ] **Location DNA integration** — Connect Location DNA neighborhood signals to recommendation engine's location adjacency scoring sub-dimension

### Cross-Phase Infrastructure Tasks

- [ ] **Phase 1 native column migration** — Must be complete before Phase B ships (19 columns documented in `STELLAR_PHASE1_NATIVE_COLUMN_MIGRATION_PLAN.md`; `furnished` was deferred to Phase 2R per Phase 0 validation)
- [ ] **Index verification** — Confirm all Phase 1 indexes are present and query planner is using them; run `EXPLAIN ANALYZE` on the Layer 1 query
- [ ] **PHPUnit test: criteria loader** — Unit test that each buyer criteria form field maps correctly to the corresponding `BuyerCriteriaPayload` property
- [ ] **PHPUnit test: view model mapper** — Unit test that no Tier 6 field appears in mapper output; unit test currency and score formatting
- [ ] **PHPUnit test: empty states** — Feature test that each empty state condition renders the correct message
- [ ] **PHPUnit test: IDX gate** — Integration test that listings with `IDXParticipationYN = false` do not appear in page output
- [ ] **PHPUnit test: senior community gate** — Integration test that 55+ listings are hidden for non-eligible buyers and visible for eligible buyers
- [ ] **Playwright / UI test** — E2E test: authenticated buyer with active criteria record navigates to results page and sees ≥ 1 result card with a score badge

---

*This document is documentation only. No routes, controllers, Livewire components, Blade views, migrations, or service layer changes are introduced by this document.*
