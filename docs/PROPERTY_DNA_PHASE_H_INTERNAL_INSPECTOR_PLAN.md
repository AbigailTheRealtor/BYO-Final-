# Property DNA Phase H — Internal Read-Only Inspector Planning Document

**Document Type:** Planning and Governance Only — No Implementation
**Date:** 2026-05-28
**Status:** Pre-implementation design specification
**Scope:** Internal admin inspector covering `property_dna_profiles`, `buyer_tenant_dna_profiles`, and `listing_compatibility_scores`

---

## Section 1 — Purpose

This document is a **planning and governance document only**. It defines the full design intent for an internal, read-only, admin-only diagnostic inspector for the Property DNA system. No code, routes, controllers, Blade files, Livewire components, schema changes, or migrations are produced by this document.

### Target Tables

| Table | Description |
|-------|-------------|
| `property_dna_profiles` | Supply-side DNA profiles for seller and landlord listings |
| `buyer_tenant_dna_profiles` | Demand-side DNA profiles for buyer and tenant listings |
| `listing_compatibility_scores` | Append-only compatibility computation records pairing supply and demand profiles |

### Inspector Characteristics

The inspector is:

- **Diagnostic only** — it surfaces raw stored data for internal audit and debugging purposes.
- **Read-only** — no mutations of any kind are permitted from within the inspector.
- **Admin-only** — accessible exclusively to users with `user_type = 'admin'`, enforced by the existing `AdminAuth` middleware.
- **Non-inferential** — it produces no recommendations, rankings, match suggestions, AI output, narrative descriptions, or compatibility conclusions of any kind.

---

## Section 2 — Access Control

### Authorized Roles

Access to the DNA inspector is restricted to **admin users only**, defined as authenticated users with `user_type = 'admin'` in the `users` table. All inspector routes must be placed inside the existing `adminAuth` middleware group (the `AdminAuth` middleware at `app/Http/Middleware/AdminAuth.php`) and the existing `admin` prefix route group in `routes/web.php`. No additional or custom middleware may be created for this purpose.

### Unauthorized Roles — Permanently Excluded

The following roles must **never** have access to any DNA inspector route, view, or data:

| Role | Status |
|------|--------|
| Agents (`user_type = 'agent'`) | Permanently excluded |
| Clients / non-agent users | Permanently excluded |
| Unauthenticated visitors | Permanently excluded |
| Public (no session) | Permanently excluded |

### Permanent Data Isolation Rule

No DNA data from any of the three target tables may appear in any of the following surfaces under any circumstances:

- Any agent-facing view, dashboard, or page
- Any client-facing view, dashboard, or page
- Any PDF listing packet or email template
- Any webhook payload or API response
- Any public route, including `/widget/hire/...` and `/hire/...`
- Any Livewire component accessible to agents or clients
- Any queue broadcast, websocket event, or telemetry payload

This rule applies regardless of the content of the data and regardless of future phases. Visibility of DNA data requires an explicit separate governance-approved phase for each new surface.

---

## Section 3 — Data That May Be Displayed

The following per-table field allowlists define all columns that may appear in the inspector. Only these fields may be selected at the query level. No `SELECT *` is permitted.

### 3a. `property_dna_profiles` — Allowed Fields

| Column | Display Label | Notes |
|--------|---------------|-------|
| `id` | ID | |
| `listing_type` | Listing Type | |
| `listing_id` | Listing ID | |
| `version` | Version | |
| `source_listing_updated_at` | Source Listing Updated At | |
| `computed_at` | Computed At | |
| `archived_at` | Archived At | Null = current version |
| `physical_score` | Physical Score | |
| `financial_score` | Financial Score | |
| `flexibility_score` | Flexibility Score | |
| `occupant_qualification_score` | Occupant Qualification Score | |
| `marketing_score` | Marketing Score | |
| `commercial_score` | Commercial Score | |
| `overall_dna_completeness` | Overall DNA Completeness | |
| `location_score` | Location Score | Display with "Reserved — Not Yet Populated" badge; always null |
| `condition_score` | Condition Score | Display with "Reserved — Not Yet Populated" badge; always null |
| `legal_score` | Legal Score | Display with "Reserved — Not Yet Populated" badge; always null |
| `compatibility_score` | Compatibility Score | Display with "Reserved — Not Yet Populated" badge; always null |
| `walk_score` | Walk Score | Display with "Reserved — Not Yet Populated" badge; external data source not yet integrated (F-01) |
| `transit_score` | Transit Score | Display with "Reserved — Not Yet Populated" badge; external data source not yet integrated (F-01) |
| `bike_score` | Bike Score | Display with "Reserved — Not Yet Populated" badge; external data source not yet integrated (F-01) |
| `school_rating` | School Rating | Display with "Reserved — Not Yet Populated" badge; external data source not yet integrated (F-02) |
| `flood_zone_verified` | Flood Zone Verified | Display with "Reserved — Not Yet Populated" badge; external data source not yet integrated (F-03) |
| `estimated_monthly_utilities` | Estimated Monthly Utilities | Display with "Reserved — Not Yet Populated" badge; external data source not yet integrated (F-05) |

**Excluded from allowlist:** `ai_buyer_archetype_tags`, `ai_marketing_hooks` — see Section 4.

### 3b. `buyer_tenant_dna_profiles` — Allowed Fields

| Column | Display Label | Notes |
|--------|---------------|-------|
| `id` | ID | |
| `listing_type` | Listing Type | |
| `listing_id` | Listing ID | |
| `version` | Version | |
| `source_listing_updated_at` | Source Listing Updated At | |
| `computed_at` | Computed At | |
| `archived_at` | Archived At | Null = current version |
| `preference_completeness` | Preference Completeness | |
| `lifestyle_tags` | Lifestyle Tags (raw JSON) | Display raw JSON only; no interpretation or labelling |
| `deal_breaker_flags` | Conflict Dimensions (metadata) | See Section 11 for null display rule |
| `commute_polygon_cache` | Commute Polygon Cache | Display with "Reserved — Geospatial / Future Phase" badge; always null per Phase G audit |

**Excluded from allowlist:** `archetype_label` — see Section 4.

### 3c. `listing_compatibility_scores` — Allowed Fields

| Column | Display Label | Notes |
|--------|---------------|-------|
| `id` | ID | |
| `demand_listing_type` | Demand Listing Type | |
| `demand_listing_id` | Demand Listing ID | |
| `supply_listing_type` | Supply Listing Type | |
| `supply_listing_id` | Supply Listing ID | |
| `version` | Version | |
| `scoring_framework_version` | Scoring Framework Version | |
| `demand_listing_updated_at_snapshot` | Demand Listing Snapshot At | |
| `supply_listing_updated_at_snapshot` | Supply Listing Snapshot At | |
| `computed_at` | Computed At | |
| `archived_at` | Archived At | Null = current version |
| `overall_score` | Coverage Score | See Section 10 for full labelling rules |
| `physical_match_score` | Physical Dimension Coverage | |
| `financial_match_score` | Financial Dimension Coverage | |
| `location_match_score` | Location Dimension Coverage | |
| `terms_match_score` | Terms Dimension Coverage | |
| `deal_breaker_triggered` | Conflict Signal Present / No Conflict Signal | See Section 11 for labelling rules |
| `deal_breaker_flags` | Conflict Dimensions (metadata) | See Section 11 for null display rule; currently always null per Phase G audit |

**Excluded from allowlist:** `score_explanation` — see Section 4.

---

## Section 4 — Data That Must Remain Hidden

The following fields are permanently excluded from the inspector and must never appear in any query result, view, API response, log entry at the row-field level, or export of any kind.

### 4a. `property_dna_profiles` — Hidden Fields

| Column | Reason |
|--------|--------|
| `ai_buyer_archetype_tags` | AI-generated content. Must never be surfaced in any UI, admin tool, or export under any circumstances. |
| `ai_marketing_hooks` | AI-generated content. Must never be surfaced in any UI, admin tool, or export under any circumstances. |

### 4b. `buyer_tenant_dna_profiles` — Hidden Fields

| Column | Reason |
|--------|--------|
| `archetype_label` | AI-assigned classification label. Must never appear in any UI, admin tool, log, or export. |

### 4c. `listing_compatibility_scores` — Hidden Fields

| Column | Reason |
|--------|--------|
| `score_explanation` | Contains internal rationale text, dimension arrays, and structured metadata that could be misread as a recommendation, ranking, or compatibility conclusion. Must never appear in any UI, API response, log row-value, or export. |

### 4d. Permanent Cross-Table Exclusions

The following categories are permanently excluded regardless of which table they appear in or what values are stored:

| Category | Exclusion Basis |
|----------|----------------|
| Protected class characteristics | Fair Housing Act; applicable state law |
| Behavioral predictions | Governance prohibition — Phase F Section 3 |
| Demographic estimates | Governance prohibition — Phase F Section 3 |
| Creditworthiness signals | Governance prohibition — Phase F Section 3 |
| `accessibility_requirements` field | Fair Housing permanent hard exclusion — Phase F Section 4 |

---

## Section 5 — Required Safeguards

All of the following safeguards must be implemented in full before any inspector route is accessible.

### 5a. Authentication Gate

All six inspector routes must be declared **inside** the existing `adminAuth` middleware group. The middleware must not be declared individually on each route. Removing the group middleware key must disable all six inspector routes simultaneously.

### 5b. Read-Only Enforcement

The inspector may only issue SELECT queries. The following calls are **permanently prohibited** within any inspector controller method, view, or helper:

- `->save()`
- `->update()`
- `->delete()`
- `->create()`
- `->dispatch()` (any job dispatch)
- Any method call into `app/Services/Dna/` of any kind

### 5c. Field Allowlist at the Query Level

All queries must use an explicit `->select([...])` call listing only the columns from the per-table allowlists in Section 3. `SELECT *` is prohibited. This applies regardless of how many columns are in the allowlist. The enforcement must be at the Eloquent query level, not filtered in the view layer.

### 5d. No Export or Download

The inspector provides no data export mechanism of any kind:

- No CSV export
- No PDF export or download
- No JSON API endpoint
- No copy-to-clipboard bulk data action

### 5e. Filter Input Restrictions

Filter inputs accepted by inspector index views are restricted to the following fields only:

| Filter Field | Applicable Tables |
|---|---|
| `listing_type` | `property_dna_profiles`, `buyer_tenant_dna_profiles` |
| `listing_id` | `property_dna_profiles`, `buyer_tenant_dna_profiles` |
| `demand_listing_type` / `supply_listing_type` | `listing_compatibility_scores` |
| `demand_listing_id` / `supply_listing_id` | `listing_compatibility_scores` |
| `version` | All three tables |
| `archived_at` toggle (current / archived / all) | All three tables |
| `computed_at` date range (from / to) | All three tables |
| `deal_breaker_triggered` boolean | `listing_compatibility_scores` |

No PII fields, user identity fields, or fields not in the above list may be accepted as filter parameters.

### 5f. Audit Logging

Every inspector page load must write a `Log::info()` entry containing:

- Admin user ID (from `Auth::id()`)
- Table being inspected
- Active filter parameters (keys and values only)

The log entry must **never** contain row-level field values from the inspected records. Log entries record who looked at what filters, not what the data contained.

### 5g. Cache-Control Header

All inspector responses must set the `Cache-Control: no-store` header. No inspector response may be stored in any application cache layer (Redis, file cache, session cache, or compiled view cache).

---

## Section 6 — Routes, Views, and Controllers for Future Implementation

This section defines the intended surface area. No files are created by this document.

### 6a. Route Structure

Six routes under `Route::prefix('admin')->middleware('adminAuth')`, sub-prefixed as `dna`, with named prefix `admin.dna.*` and URL prefix `/admin/dna/`:

| Method | URL | Named Route | Description |
|--------|-----|-------------|-------------|
| GET | `/admin/dna/property` | `admin.dna.property.index` | Property DNA profile index |
| GET | `/admin/dna/property/{id}` | `admin.dna.property.show` | Property DNA profile detail |
| GET | `/admin/dna/demand` | `admin.dna.demand.index` | Buyer/Tenant DNA profile index |
| GET | `/admin/dna/demand/{id}` | `admin.dna.demand.show` | Buyer/Tenant DNA profile detail |
| GET | `/admin/dna/scores` | `admin.dna.scores.index` | Compatibility scores index |
| GET | `/admin/dna/scores/{id}` | `admin.dna.scores.show` | Compatibility score detail |

All six routes must be declared inside a single `Route::prefix('admin')->middleware('adminAuth')` group, not individually. No DNA routes may appear in `routes/api.php`.

### 6b. Controller

One file: `app/Http/Controllers/Admin/DnaInspectorController.php`

Six methods:

| Method | Route Binding |
|--------|--------------|
| `propertyIndex` | `admin.dna.property.index` |
| `propertyShow` | `admin.dna.property.show` |
| `demandIndex` | `admin.dna.demand.index` |
| `demandShow` | `admin.dna.demand.show` |
| `scoresIndex` | `admin.dna.scores.index` |
| `scoresShow` | `admin.dna.scores.show` |

The controller must import `PropertyDnaProfile`, `BuyerTenantDnaProfile`, and `ListingCompatibilityScore` only. It must not import any service class from `app/Services/Dna/`, any observer, or any queue job.

### 6c. Views

Six Blade files inside `resources/views/admin/dna/`:

| File | Route |
|------|-------|
| `property/index.blade.php` | `admin.dna.property.index` |
| `property/show.blade.php` | `admin.dna.property.show` |
| `demand/index.blade.php` | `admin.dna.demand.index` |
| `demand/show.blade.php` | `admin.dna.demand.show` |
| `scores/index.blade.php` | `admin.dna.scores.index` |
| `scores/show.blade.php` | `admin.dna.scores.show` |

All six views must extend the existing admin layout. None may extend any agent or client layout.

### 6d. Admin Sidenav Entry

A "DNA Inspector" entry linking to the three index views (`admin.dna.property.index`, `admin.dna.demand.index`, `admin.dna.scores.index`) must be added to the admin sidenav only. It must not appear in any agent sidenav or client sidenav, and it must not be rendered for any non-admin user type.

---

## Section 7 — How to Prevent Public Exposure

### 7a. Group-Level Middleware Only

All six DNA inspector routes inherit `adminAuth` from the group declaration. This means a single removal of the group middleware key disables all six simultaneously. Individual per-route middleware declarations are prohibited — they would allow accidental partial exposure if the group is refactored.

### 7b. Controller Import Restriction

No DNA model (`PropertyDnaProfile`, `BuyerTenantDnaProfile`, `ListingCompatibilityScore`) may be imported outside `app/Http/Controllers/Admin/`. If a grep scan finds any of these three model names imported in any other controller, Livewire component, mail class, or route closure, it is a governance violation.

### 7c. No API Routes

No DNA inspector endpoint may appear in `routes/api.php`. The DNA inspector is web-only, admin-only, and session-authenticated. No token-authenticated or stateless API access to DNA data is permitted.

### 7d. Grep Verification Protocol

Before merging any implementation of the inspector, re-run the eight-symbol grep check defined in the Phase G readiness audit (Section 3) across all routes, controllers, Livewire components, views, mail classes, and PDF/API files. The eight symbols are:

1. `PropertyDnaProfile`
2. `BuyerTenantDnaProfile`
3. `ListingCompatibilityScore`
4. `CompatibilityEngine`
5. `ComputeCompatibilityScore`
6. `score_explanation`
7. `overall_score`
8. `deal_breaker_triggered`

After the inspector is implemented, symbols 1–3 are expected to appear in `routes/web.php` (inside the admin group) and `app/Http/Controllers/Admin/DnaInspectorController.php` only. Any match outside those two locations is a governance violation and must block the merge.

### 7e. Widget and Hire Route Isolation

`/widget/hire/...` and `/hire/...` routes must never query any DNA table. This rule is established in Phase F (Section 8) and reaffirmed here. It applies to all future phases unconditionally.

---

## Section 8 — Version History Display

All three target tables use append-only persistence. The inspector must surface version history clearly without permitting any modification.

### 8a. Current Version Display

The current row for any given key group is identified by `archived_at IS NULL`. It must be labelled:

> **Current Version (v{version})** — Active

An "Active" badge (e.g., green label) must distinguish the current row from archived rows.

### 8b. Archived Version Display

Archived rows (`archived_at IS NOT NULL`) for the same key group must be shown in a collapsed accordion below the current row panel. They must be ordered descending by `version` (most recent archive first). Each archived row must be labelled:

> **Version {version} — Archived {archived_at}**

An "Archived" badge (e.g., grey label) must distinguish archived rows from the current row.

### 8c. Prohibited History Controls

- No diff view between any two versions is permitted.
- No delete control on any version row.
- No rollback control on any version row.
- No recompute control of any kind.

### 8d. Version Count Column on Index Views

Index views for all three tables must include a "Version Count" column displaying `COUNT(*)` of all rows (current + archived) for each key group. This confirms that append-only semantics are functioning correctly.

### 8e. Key Groups for Version History

| Table | Version History Key |
|-------|-------------------|
| `property_dna_profiles` | `(listing_type, listing_id)` pair |
| `buyer_tenant_dna_profiles` | `(listing_type, listing_id)` pair |
| `listing_compatibility_scores` | `(demand_listing_type, demand_listing_id, supply_listing_type, supply_listing_id)` quad |

---

## Section 9 — Compatibility Dimension Display Rules

### 9a. `score_explanation` Is Hidden

The `score_explanation` column is excluded from all queries and views (Section 4). Only the four dimension-group score columns are shown: `physical_match_score`, `financial_match_score`, `location_match_score`, `terms_match_score`.

### 9b. Required Column Header Terminology

All dimension score column headers must use "Coverage" or "Dimension Coverage". The following terms are **prohibited** in any column header, label, tooltip, or badge for dimension score columns:

- "Match"
- "Fit"
- "Score" (as a standalone label implying quality)
- "Rank"
- "Strength"
- "Suitability"
- "Quality"

### 9c. Null and Edge-Case Display Values

| Value | Required Display | Prohibited Display |
|-------|----------------|--------------------|
| `NULL` | "No data" or "Unresolved" | "Unknown fit", "N/A (no match)" |
| `0.00` | "0.00 — No resolved dimensions" | "No match", "Incompatible", "0%" |
| `1.00` | "1.00 — All dimensions resolved" | "Perfect match", "Fully compatible", "100%" |

### 9d. Per-Dimension State Labels (Future Extension)

If per-dimension `aligned`/`conflicting`/`unresolved` states are surfaced in any future extension of the inspector, the following labelling rules apply:

| Internal State | Required Label | Prohibited Labels |
|----------------|---------------|-------------------|
| `aligned` | "Field Present on Both Sides" | "Match", "Compatible", "Aligned", "Fit", "Pass" |
| `conflicting` | "Conflict Signal Present" | "Incompatible", "Mismatch", "Failed", "Rejected", "Blocked" |
| `unresolved` | "Insufficient Data" | "Unknown fit", "No match", "Unmatched", "Missing" |

### 9e. No Aggregated Labels

No aggregated recommendation sentence, ranking badge, or "good/poor fit" label of any kind may be produced from dimension data.

---

## Section 10 — `overall_score` Labelling

### 10a. Required Labels

| Context | Required Text |
|---------|--------------|
| Column header | "Coverage Score" |
| Detail label | "Dimension Coverage Score" |
| Tooltip | "Proportion of the 14 compatibility dimensions for which both listings provided resolvable signal. This is a data completeness indicator only." |

### 10b. Prohibited Labels

The following labels are permanently prohibited for `overall_score` in any context:

- "Match Score"
- "Compatibility Score"
- "Fit Score"
- "Similarity Score"
- "Recommendation Score"
- "Overall Match"
- Any label containing "match", "fit", "compatible", "rank", or "recommend"

### 10c. Display Format

`overall_score` must be displayed as a plain decimal to two decimal places (e.g., `0.71`). The following display formats are prohibited:

- Percentage (e.g., "71%")
- Star rating
- Progress bar
- Color-coded gauge
- Any visual quality indicator

---

## Section 11 — `deal_breaker_triggered` and `deal_breaker_flags` Labelling

### 11a. `deal_breaker_triggered` Labels

| Database Value | Required Display Label |
|---------------|----------------------|
| `true` | "Conflict Signal Present" |
| `false` | "No Conflict Signal" |

### 11b. `deal_breaker_flags` Labels

| Database State | Required Display Label |
|----------------|----------------------|
| Non-null value | "Conflict Dimensions (metadata)" |
| `NULL` | "Conflict Dimensions — Not yet populated" |

Note: Per the Phase G readiness audit (Section 7c), `deal_breaker_flags` on `listing_compatibility_scores` is currently always persisted as `null`. The "Not yet populated" label applies to all current rows.

### 11c. Prohibited Labels

The following labels are permanently prohibited for both `deal_breaker_triggered` and `deal_breaker_flags` in any context:

- "Deal Breaker"
- "Disqualified"
- "Incompatible"
- "Failed"
- "Blocked"
- "Rejected"
- "Flagged"

### 11d. Required Tooltips

Both fields must include a help tooltip on every view where they appear. The tooltip text must convey:

> These are structural metadata flags only. They record whether a deterministic field conflict was detected during compatibility computation. They do not constitute a recommendation, disqualification, or decision of any kind.

---

## Section 12 — Recommended Future Implementation Sequence

The following nine steps define the safe implementation order. Each step must be fully verified before the next begins.

### Step 1 — Controller Skeleton, No Routes

Create `app/Http/Controllers/Admin/DnaInspectorController.php` with all six methods stubbed to return `abort(404)`. No routes are registered yet. Verify that no DNA model leaks to any non-admin controller by running the grep verification protocol (Section 7d).

### Step 2 — Add Six Routes Inside Admin Group

Register all six routes inside `Route::prefix('admin')->middleware('adminAuth')`. Verify with:

```
php artisan route:list | grep dna
```

Confirm that `adminAuth` appears on all six routes. Verify that an unauthenticated request and an agent-authenticated request to any `/admin/dna/` URL are both redirected (not served).

### Step 3 — Property DNA Index View

Implement `propertyIndex` with an explicit `->select([...])` using only the Section 3a allowlist. Show current rows (`archived_at IS NULL`) with the "Version Count" column. Verify:

- Hidden fields (`ai_buyer_archetype_tags`, `ai_marketing_hooks`) are absent from the query result at the Eloquent level.
- All dimension score columns use "Coverage" labelling.
- Reserved columns display with "Reserved — Not Yet Populated" badge.
- `Cache-Control: no-store` header is set.
- `Log::info()` is written on each page load.

### Step 4 — Property DNA Show View

Implement `propertyShow` with the accordion version history panel. Verify:

- Archived rows are ordered descending by `version`.
- No diff view is rendered.
- No edit, delete, or rollback controls are present.
- Current version displays "Active" badge; archived versions display "Archived" badge.

### Step 5 — Demand DNA Index and Show Views

Implement `demandIndex` and `demandShow`. Verify:

- `archetype_label` is excluded at the query level (not merely hidden in the view).
- `commute_polygon_cache` displays with "Reserved — Geospatial / Future Phase" badge.
- `deal_breaker_flags` uses Section 11 labelling rules.
- Version history accordion follows the same rules as Step 4.

### Step 6 — Compatibility Scores Index and Show Views

Implement `scoresIndex` and `scoresShow`. Verify:

- `score_explanation` is excluded at the query level.
- `overall_score` column header reads "Coverage Score" with the Section 10 tooltip.
- `deal_breaker_triggered` and `deal_breaker_flags` use Section 11 labels and tooltips.
- All four dimension-group score columns use "Dimension Coverage" labelling.
- Version history accordion uses the four-column key quad `(demand_listing_type, demand_listing_id, supply_listing_type, supply_listing_id)`.

### Step 7 — Admin Sidenav Entry

Add the "DNA Inspector" entry to the admin sidenav only, linking to all three index routes. Verify:

- The entry does not appear in any agent sidenav layout.
- The entry does not appear in any client sidenav layout.
- The entry is not rendered for any `user_type` other than `admin`.

### Step 8 — Full Grep Verification

Re-run all eight-symbol grep checks from Section 7d. After implementation, the expected state is:

- Symbols 1–3 (`PropertyDnaProfile`, `BuyerTenantDnaProfile`, `ListingCompatibilityScore`) appear only in `routes/web.php` (inside the admin group) and `app/Http/Controllers/Admin/DnaInspectorController.php`.
- Symbols 4–8 must still have zero matches outside the DNA system's internal layers.
- `DnaInspectorController` must appear only in `routes/web.php` and `app/Http/Controllers/Admin/`.

Any match outside the expected locations must block the merge.

### Step 9 — Logging Verification

Confirm that `Log::info()` entries are written on each page load for all six routes. Confirm that no log entry contains row-level field values from the inspected records. Confirm that log entries contain only: admin user ID, table name, and active filter parameters.

---

## Section 13 — Out of Scope

The following items are explicitly excluded from Phase H and from the inspector at any phase:

| Item | Status |
|------|--------|
| Edit, delete, or recompute actions from the inspector | Permanently out of scope |
| Any export format (CSV, PDF, JSON API) | Permanently out of scope |
| Any API endpoint for DNA data | Permanently out of scope |
| AI narrative generation from any DNA column | Permanently out of scope |
| `ai_buyer_archetype_tags` display | Permanently out of scope |
| `ai_marketing_hooks` display | Permanently out of scope |
| `archetype_label` display | Permanently out of scope |
| Match suggestions or rankings of any kind | Permanently out of scope |
| `score_explanation` surfacing in any view or export | Permanently out of scope |
| `accessibility_requirements` surfacing | Permanently out of scope (Fair Housing — Phase F Section 4) |
| Protected characteristic inference | Permanently out of scope |
| Behavioral prediction | Permanently out of scope |
| Creditworthiness signals | Permanently out of scope |
| Commute polygon computation | Out of scope; reserved for future geospatial phase |
| Population of reserved columns (`walk_score`, `transit_score`, `bike_score`, `school_rating`, `flood_zone_verified`, `estimated_monthly_utilities`) | Out of scope; requires external data sources |
| Population of `deal_breaker_flags` on `listing_compatibility_scores` | Out of scope; deferred per Phase G audit R-04 |
| Resolution of R-03 (`hoa_alignment` eligible/ineligible discrepancy) | Out of scope for Phase H |
| Resolution of R-01 / R-02 (missing `$tries` / `$timeout` on DNA generation jobs) | Out of scope for Phase H |
| `DnaMarketingOutput` generator, job, or observer | Out of scope; scaffolded stub only |

---

## Section 14 — Open Items from Phase G

The following 11 open risk items were documented in the Phase G Readiness Audit (Section 8). None of these items are addressed by Phase H. They remain open and must be addressed in future phases.

| Ref | Description | Severity |
|-----|-------------|----------|
| R-01 | `ComputePropertyDnaProfile` has no explicit `$tries` or `$timeout` declared. Queue driver defaults apply; a hung advisory lock wait can stall a queue worker indefinitely. | Medium |
| R-02 | `ComputeBuyerTenantDnaProfile` has no explicit `$tries` or `$timeout` declared. Same risk as R-01. | Medium |
| R-03 | `hoa_alignment` is listed in `STRUCTURALLY_ELIGIBLE_DIMENSIONS` but always resolves to `'unresolved'` because no demand-side HOA preference field exists in `BuyerTenantDnaGenerator`. This silently deflates the `compatibility_coverage_metric` denominator accuracy. | Low |
| R-04 | `deal_breaker_flags` on `listing_compatibility_scores` is always persisted as `null`. The conflicting dimension detail is not written, making per-flag audit of conflicts impossible without parsing `score_explanation`. | Low |
| R-05 | `FANOUT_CAP` of 500 is prototype-scale only. No chunked cursor pagination or batch dispatch pattern exists. Not suitable for production-scale listing volumes. | Medium |
| R-06 | `DnaMarketingOutput` table and model exist and are inert. No generator, job, or observer targets this table. | Low |
| R-07 | Six reserved columns on `property_dna_profiles` (`walk_score`, `transit_score`, `bike_score`, `school_rating`, `flood_zone_verified`, `estimated_monthly_utilities`) are schema-present but never populated. External data source integrations required. | Low |
| R-08 | `commute_polygon_cache` on `buyer_tenant_dna_profiles` is reserved and prohibited from population by migration comment. Geospatial commute radius computation architecture required. | Low |
| R-09 | `location_score`, `condition_score`, `legal_score`, `compatibility_score` on `property_dna_profiles` are always persisted as `null` by `PropertyDnaGenerator`. Scoring rules and data sources not yet defined. | Low |
| R-10 | Advisory locks use `crc32()` with a 32-bit output space. Collision probability is negligible at prototype scale but non-zero at high listing volume. | Low |
| R-11 | Non-PostgreSQL drivers receive no advisory lock; `acquireListingLock()` is a no-op on other drivers. Project uses PostgreSQL exclusively; this is a deployment constraint only. | Informational |

**Phase H does not address any of the above items.** Each requires a separate future phase with its own scope, governance approval, and implementation plan.

---

*End of Document — Property DNA Phase H Internal Inspector Planning*
