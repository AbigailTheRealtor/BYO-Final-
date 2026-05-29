# Property DNA Phase L — Reserved Field Governance + External Data Readiness Audit

**Audit Date:** 2026-05-29
**Auditor:** Task Agent (read-only; no implementation changes made)
**Scope:** Audit of all reserved fields in `property_dna_profiles`. Classification of each field by readiness category. Verification that no reserved field is accidentally populated or exposed in any public/agent/client-facing surface. Documentation of recommended future integration order.

---

## 1. Files Reviewed

| File | Role |
|------|------|
| `app/Models/PropertyDnaProfile.php` | Supply-side DNA model — `$fillable` and `$casts` definitions |
| `app/Services/Dna/PropertyDnaGenerator.php` | Supply-side DNA generation service — `persist()` payload |
| `database/migrations/2026_05_27_000001_create_property_dna_profiles_table.php` | Schema definition with migration-level `comment()` annotations |
| `app/Http/Controllers/Admin/DnaInspectorController.php` | Admin-only read-only inspector (Phase H implementation) |
| `resources/views/admin/dna/property/show.blade.php` | Admin inspector view — reserved field badge rendering |
| `resources/views/admin/dna/property/index.blade.php` | Admin inspector index view |
| `docs/PROPERTY_DNA_PHASE_G_READINESS_AUDIT.md` | Phase G post-audit — prior reserved field documentation (R-07, R-08, R-09) |
| `docs/PROPERTY_DNA_PHASE_H_INTERNAL_INSPECTOR_PLAN.md` | Phase H inspector plan — inspector field allowlists and badge rules |

---

## 2. Reserved Fields Identified

### 2a. `property_dna_profiles` — Reserved Columns

The following ten columns are defined in the `property_dna_profiles` table but are never populated by any current service, job, or generator. Each has been confirmed null in every active and archived row.

#### Group 1 — External API Fields (Migration comment: F-01, F-02, F-03, F-05)

These six columns carry explicit `Reserved / Future Use Only — Not Implemented` comments at the migration schema level.

| Column | Schema Type | Migration Comment | Generator Behavior |
|--------|-------------|-------------------|--------------------|
| `walk_score` | `integer, nullable` | Reserved / Future Use Only — Not Implemented (F-01) | Not present in `persist()` payload; never written |
| `transit_score` | `integer, nullable` | Reserved / Future Use Only — Not Implemented (F-01) | Not present in `persist()` payload; never written |
| `bike_score` | `integer, nullable` | Reserved / Future Use Only — Not Implemented (F-01) | Not present in `persist()` payload; never written |
| `school_rating` | `decimal(5,2), nullable` | Reserved / Future Use Only — Not Implemented (F-02) | Not present in `persist()` payload; never written |
| `flood_zone_verified` | `string, nullable` | Reserved / Future Use Only — Not Implemented (F-03) | Not present in `persist()` payload; never written |
| `estimated_monthly_utilities` | `decimal(10,2), nullable` | Reserved / Future Use Only — Not Implemented (F-05) | Not present in `persist()` payload; never written |

Note: All six are present in `PropertyDnaProfile::$fillable` and `$casts` for future readiness only. Presence in `$fillable` does not cause population — it is a prerequisite for future mass-assignment when integration is implemented.

#### Group 2 — Deterministic / Governance-Controlled Scores (No migration comment)

These four columns have no migration-level `comment()` annotation but are explicitly set to `null` in every call to `PropertyDnaGenerator::persist()` via the hardcoded payload line:

```php
'location_score'    => null,
'condition_score'   => null,
'legal_score'       => null,
'compatibility_score' => null,
```

| Column | Schema Type | Explicit Null in Generator | Notes |
|--------|-------------|---------------------------|-------|
| `location_score` | `decimal(5,2), nullable` | Yes — hardcoded `null` in `persist()` payload | No source field or computation rule defined |
| `condition_score` | `decimal(5,2), nullable` | Yes — hardcoded `null` in `persist()` payload | No source field or computation rule defined |
| `legal_score` | `decimal(5,2), nullable` | Yes — hardcoded `null` in `persist()` payload | No source field or computation rule defined |
| `compatibility_score` | `decimal(5,2), nullable` | Yes — hardcoded `null` in `persist()` payload | Separate from `ListingCompatibilityScore` table; definition not yet approved |

---

## 3. Classification of Each Reserved Field

Each field is assigned one of four classifications:

- **External API Required** — Requires integration of a third-party API or external data provider before any population is possible. No internal derivation is appropriate.
- **Future Deterministic Rules Required** — Can eventually be computed from internal data sources (listing fields, meta, schema columns) once the computation rules are formally defined and approved. No external API required.
- **Reserved / Do Not Populate Yet** — Present in the schema as a placeholder. The specific definition, formula, or scope has not been approved. Population is blocked until a formal governance decision is recorded.
- **Governance-Blocked** — Population would require inference of protected class characteristics, demographic proxies, creditworthiness signals, or other categories prohibited by Fair Housing law or platform governance policy. Must never be populated.

| Field | Classification | Reason |
|-------|---------------|--------|
| `walk_score` | **External API Required** | Walk Score® or equivalent pedestrian infrastructure API. Cannot be derived internally from current schema. |
| `transit_score` | **External API Required** | Transit score from Walk Score® or equivalent public transit API. Cannot be derived internally. |
| `bike_score` | **External API Required** | Bike Score® or equivalent cycling infrastructure API. Cannot be derived internally. |
| `school_rating` | **External API Required** | GreatSchools API or equivalent school quality/proximity API. Cannot be derived from listing data alone. |
| `flood_zone_verified` | **External API Required** | FEMA National Flood Insurance Program (NFIP) API or equivalent. Requires address-level flood zone lookup. |
| `estimated_monthly_utilities` | **External API Required** | Requires an external utility cost estimation API or published rate data. Cannot be safely estimated from property size alone without validated rate tables. If eventually computed from internal data + published rates, the methodology must be documented and approved before any population. |
| `location_score` | **Future Deterministic Rules Required** | Can be derived from existing structured fields (zip code, city, county) combined with internal thresholds once computation rules are defined. No external API required if rules are bounded to schema-present data. |
| `condition_score` | **Future Deterministic Rules Required** | Can be derived from `condition_prop` meta key, `year_built` (where available), and other condition-related fields once scoring rules are formally defined. |
| `legal_score` | **Future Deterministic Rules Required** | Can be derived from legal/disclosure meta keys (HOA, flood zone disclosure, CDD/special assessments, title status) once a scoring rubric is approved. |
| `compatibility_score` | **Reserved / Do Not Populate Yet** | The `listing_compatibility_scores` table already implements supply/demand compatibility computation. A separate `compatibility_score` column on `property_dna_profiles` represents a different, undefined concept (possibly a listing-level self-compatibility indicator). Its definition, formula, and relationship to `ListingCompatibilityScore` has not been approved. Population is blocked pending a formal governance decision. |

### Governance-Blocked Fields — None Found

No reserved field in `property_dna_profiles` falls into the Governance-Blocked category. Specifically:

- None of the ten reserved fields requires race, national origin, familial status, disability status, religion, sex, or color inference.
- None requires creditworthiness prediction.
- `school_rating` is bounded to an objective third-party rating of school district quality (not a proxy for demographic composition). It must be sourced exclusively from an accredited third-party API (e.g., GreatSchools) and must never be derived from census demographics, income data, or neighborhood composition. Any future implementation must document this boundary explicitly.
- `flood_zone_verified` is bounded to FEMA-designated flood zone classification — an objective regulatory designation, not a demographic proxy.

---

## 4. Population Verification — Grep Results

Grep was run across `app/`, `database/`, `resources/`, and `routes/` for all ten reserved field names. Results are documented below.

### 4a. External API Reserved Fields (walk_score, transit_score, bike_score, school_rating, flood_zone_verified, estimated_monthly_utilities)

| Location | File | Nature of Reference | Verdict |
|----------|------|---------------------|---------|
| `app/Models/PropertyDnaProfile.php` | `$fillable` array, `$casts` array | Model readiness declarations only — no write operation | **PASS** |
| `database/migrations/2026_05_27_000001_create_property_dna_profiles_table.php` | Schema column definitions with `comment()` | Schema definition only — no data write | **PASS** |
| `app/Http/Controllers/Admin/DnaInspectorController.php` | `->select([...])` allowlist in `propertyIndex()` and `propertyShow()` | Read-only SELECT query, admin-only, never written | **PASS** |
| `resources/views/admin/dna/property/show.blade.php` | Display with "Reserved — Not Yet Populated" badge | Admin-only read display; renders badge only, no value output | **PASS** |

**No match found** in: `routes/`, any non-admin controller, any Livewire component, any non-admin Blade view, any mail class, any PDF template, any observer, any queue job, any service class other than `PropertyDnaGenerator`.

### 4b. Deterministic Score Reserved Fields (location_score, condition_score, legal_score, compatibility_score)

| Location | File | Nature of Reference | Verdict |
|----------|------|---------------------|---------|
| `app/Models/PropertyDnaProfile.php` | `$fillable` array, `$casts` array | Model readiness declarations only | **PASS** |
| `database/migrations/2026_05_27_000001_create_property_dna_profiles_table.php` | Schema column definitions | Schema definition only | **PASS** |
| `app/Services/Dna/PropertyDnaGenerator.php` | Lines 88–91 in `generate()`: `'location_score' => null`, etc. | Explicit null assignment — intentional governance enforcement | **PASS** |
| `app/Http/Controllers/Admin/DnaInspectorController.php` | `->select([...])` allowlist in `propertyIndex()` and `propertyShow()` | Read-only SELECT, admin-only, always null in practice | **PASS** |
| `resources/views/admin/dna/property/show.blade.php` | Display with "Reserved — Not Yet Populated" badge | Admin-only read display; renders badge only | **PASS** |
| `app/Models/ListingCompatibilityScore.php` | Class name match only (no `compatibility_score` column on this model) | String match on class name — unrelated to the DNA profile column | **NOT A VIOLATION** |
| `app/Jobs/ComputeCompatibilityScore.php` | References `listing_compatibility_scores` table only | Unrelated table; no reference to DNA profile's `compatibility_score` column | **NOT A VIOLATION** |

**No match found** in: `routes/`, any non-admin controller, any Livewire component, any non-admin Blade view, any mail class, any PDF template.

### 4c. Related-but-Distinct Reference — `walkability_transit_score`

Grep identified the key `walkability_transit_score` in two landlord listing Blade files:

- `resources/views/landlord_auction/includes/patch-5.blade.php` (line 101)
- `resources/views/landlord_auction/includes/edit/patch-5.blade.php` (line 103)

**This is not a DNA field.** `walkability_transit_score` is an AI FAQ question key for landlord listings, stored as a user-authored text response via EAV meta (the `listing_ai_faq` meta key). It is an entirely separate data path from the DNA profile columns `walk_score` and `transit_score`. No DNA column is referenced or populated by these views. This reference requires no remediation.

### 4d. Public/Agent/Client Exposure Verification

| Surface | Result |
|---------|--------|
| `routes/web.php` / `routes/api.php` | No reserved field names found — PASS |
| Non-admin controllers (`app/Http/Controllers/` excluding `Admin/`) | No reserved field names found — PASS |
| Livewire components (`app/Livewire/`) | No reserved field names found — PASS |
| Non-admin Blade views (`resources/views/` excluding `admin/dna/`) | No reserved field names found (walkability_transit_score is a different key — see 4c) — PASS |
| Mail classes (`app/Mail/`) | No reserved field names found — PASS |
| PDF templates | No reserved field names found — PASS |

**Summary: All ten reserved fields are fully confined to their approved locations. No accidental population exists. No public, agent, or client-facing surface exposes any reserved field.**

---

## 5. Admin Inspector Reserved Badge Verification

The Phase H DNA inspector is fully implemented and confirmed to display all reserved fields with "Reserved — Not Yet Populated" badges. Verified in `resources/views/admin/dna/property/show.blade.php`:

| Field | Badge Text Confirmed |
|-------|---------------------|
| `location_score` | "Reserved — Not Yet Populated" |
| `condition_score` | "Reserved — Not Yet Populated" |
| `legal_score` | "Reserved — Not Yet Populated" |
| `compatibility_score` | "Reserved — Not Yet Populated" (displayed as "Reserved Coverage Field") |
| `walk_score` | "Reserved — Not Yet Populated" |
| `transit_score` | "Reserved — Not Yet Populated" |
| `bike_score` | "Reserved — Not Yet Populated" |
| `school_rating` | "Reserved — Not Yet Populated" |
| `flood_zone_verified` | "Reserved — Not Yet Populated" |
| `estimated_monthly_utilities` | "Reserved — Not Yet Populated" |

A section header above the reserved block reads: "The following fields are reserved and not yet populated by any data source." This is consistent with the Phase H inspector plan (Section 3a).

The inspector controller selects these columns in its `->select([...])` calls (satisfying the Phase H field allowlist requirement) but never writes to them. Reserved field values will always render as null and display the badge.

**All existing DNA inspector reserved badges are valid. No correction is required.**

---

## 6. Reserved Field Detail Records

The following table provides the full governance record for each reserved field, as required by this phase.

| Field | Table | Current Population Status | Classification | Required Future Data Source or Rule | Governance Restrictions | User-Facing Display Allowed? | Recommended Future Phase |
|-------|-------|--------------------------|---------------|--------------------------------------|------------------------|------------------------------|--------------------------|
| `walk_score` | `property_dna_profiles` | Always null; never written by any service | External API Required | Walk Score® API (walkscore.com) or equivalent pedestrian infrastructure scoring provider | Must be sourced from accredited external API only; must not be inferred, estimated, or derived from neighborhood demographics | No (admin inspector only, with reserved badge) | Phase M or dedicated external-data integration phase |
| `transit_score` | `property_dna_profiles` | Always null; never written by any service | External API Required | Walk Score® Transit Score or equivalent public transit scoring API | Must be sourced from accredited external API only; must not be inferred from census commute data | No (admin inspector only, with reserved badge) | Phase M or dedicated external-data integration phase |
| `bike_score` | `property_dna_profiles` | Always null; never written by any service | External API Required | Walk Score® Bike Score or equivalent cycling infrastructure scoring API | Must be sourced from accredited external API only; must not be inferred from geographic data alone | No (admin inspector only, with reserved badge) | Phase M or dedicated external-data integration phase |
| `school_rating` | `property_dna_profiles` | Always null; never written by any service | External API Required | GreatSchools API or equivalent accredited school quality/proximity provider | Must be sourced from accredited third-party only; must never be derived from census demographics, income levels, or neighborhood racial/ethnic composition — doing so would constitute a Fair Housing proxy violation | No (admin inspector only, with reserved badge) | Phase N or dedicated school-data integration phase |
| `flood_zone_verified` | `property_dna_profiles` | Always null; never written by any service | External API Required | FEMA National Flood Insurance Program (NFIP) API or FEMA Flood Map Service Center; requires address-level geocoding prior to lookup | Must use official FEMA designation only; must not be inferred from property elevation, neighborhood demographics, or adjacent property attributes | No (admin inspector only, with reserved badge) | Phase N or dedicated flood-zone integration phase |
| `estimated_monthly_utilities` | `property_dna_profiles` | Always null; never written by any service | External API Required | External utility cost estimation API or validated published rate tables by utility provider and property type; requires methodology documentation and approval before any population | Must not be estimated using AI inference, demographic proxies, or neighborhood income data; any computation methodology must be formally documented and approved before implementation | No (admin inspector only, with reserved badge) | Phase O or dedicated utility-estimation phase |
| `location_score` | `property_dna_profiles` | Always null; explicitly set to `null` in `PropertyDnaGenerator::generate()` lines 88 | Future Deterministic Rules Required | Internal schema fields: zip code, city, county (via `UsZipCode`, `UsCity`, `UsCounty` models); scoring rubric to be formally defined | Must not incorporate neighborhood demographics, school composition, income levels, or any Fair Housing proxy; must be bounded to objective geographic/infrastructure data only | No (admin inspector only, with reserved badge) | Phase P or dedicated deterministic-scores phase |
| `condition_score` | `property_dna_profiles` | Always null; explicitly set to `null` in `PropertyDnaGenerator::generate()` line 89 | Future Deterministic Rules Required | Internal schema fields: `condition_prop` meta key, `year_built` (where available), renovation/update meta keys; scoring rubric to be formally defined | Must reflect only objective physical condition indicators; must not incorporate property value, neighborhood desirability, or buyer demand signals | No (admin inspector only, with reserved badge) | Phase P or dedicated deterministic-scores phase |
| `legal_score` | `property_dna_profiles` | Always null; explicitly set to `null` in `PropertyDnaGenerator::generate()` line 90 | Future Deterministic Rules Required | Internal schema fields: HOA disclosure meta keys, flood zone disclosure, CDD/special assessments, title status fields, legal/parcel meta keys; scoring rubric to be formally defined | Must reflect only objective legal/disclosure completeness; must not incorporate assessed value, marketability opinions, or buyer demand proxies | No (admin inspector only, with reserved badge) | Phase P or dedicated deterministic-scores phase |
| `compatibility_score` | `property_dna_profiles` | Always null; explicitly set to `null` in `PropertyDnaGenerator::generate()` line 91 | Reserved / Do Not Populate Yet | Undefined. The `listing_compatibility_scores` table (a separate system) already provides supply/demand compatibility metrics. The `property_dna_profiles.compatibility_score` column represents a different, as-yet undefined concept. Its formula, scope, and relationship to `ListingCompatibilityScore` must be formally approved before any population | Must not duplicate or conflict with `listing_compatibility_scores` coverage metrics; must not be presented as a ranking, recommendation, or suitability score; definition requires a formal governance decision | No (admin inspector only, with reserved badge) | Post-Phase P; requires formal definition phase first |

---

## 7. Recommended Future Integration Order

The following sequence represents the safest and most value-effective order for implementing reserved field integrations. Each phase should be fully verified and documented before the next begins.

### Step 1 — Walk / Transit / Bike Scores (External API)

**Fields:** `walk_score`, `transit_score`, `bike_score`

**Why first:** These three fields share a single API provider (Walk Score® or equivalent), minimizing integration overhead for three columns. They are purely additive — no existing scoring logic is affected. They are the most commonly expected external data points in real estate listings and provide immediate value to the DNA completeness signal.

**Prerequisites:**
- API provider contract and key management
- Address geocoding pipeline (street address → lat/lng) for all seller and landlord listings
- A new queue job (e.g., `FetchWalkScoreData`) dispatched from the DNA observer chain after `PropertyDnaGenerator` completes
- Rate limiting and caching strategy for API calls
- A schema migration is NOT required (columns already exist)

**Governance gate:** API provider terms of service must be reviewed. Walk Score® data carries specific display requirements; compliance must be confirmed before any user-facing display is enabled.

---

### Step 2 — Flood Zone Verification (External API)

**Fields:** `flood_zone_verified`

**Why second:** Flood zone status is a material disclosure field in real estate transactions (required by law in many states). It has significant practical value for sellers, landlords, and agents. The FEMA NFIP API is publicly available. Implementation requires address geocoding (established in Step 1).

**Prerequisites:**
- Address geocoding pipeline (from Step 1)
- FEMA NFIP API or FEMA Flood Map Service Center integration
- A new queue job (e.g., `FetchFloodZoneData`)
- Decision on `flood_zone_verified` value format (e.g., FEMA zone code: "AE", "X", "AH") — a format governance decision is required before schema population
- A schema migration is NOT required

**Governance gate:** Must use official FEMA designation codes only. Must not derive flood risk from elevation, demographics, or adjacent-property heuristics.

---

### Step 3 — School Rating / Proximity (External API)

**Fields:** `school_rating`

**Why third:** Depends on Step 1 (geocoding pipeline). School ratings carry Fair Housing sensitivity that requires careful methodology review before implementation. Ordering after flood zone allows that review period while flood zone integration is underway.

**Prerequisites:**
- Address geocoding pipeline (from Step 1)
- GreatSchools API key or equivalent accredited provider
- A formal governance review confirming the data source does not use demographic composition as a scoring input
- A new queue job (e.g., `FetchSchoolRatingData`)
- Decision on whether `school_rating` stores a district-level aggregate, the closest elementary school rating, or a weighted multi-school score — this format decision is required before population

**Governance gate (mandatory):** The data source must be reviewed to confirm it produces ratings from school performance and resource data only, not from student demographic composition. This review must be documented in a successor governance document before any population begins.

---

### Step 4 — Utility Estimate Logic (External API or Deterministic)

**Fields:** `estimated_monthly_utilities`

**Why fourth:** Utility estimation methodology is the most complex of the external data fields because it may combine internal property data (square footage, property type) with external rate tables. The methodology requires the most extensive pre-implementation design and approval.

**Prerequisites:**
- A formally documented and approved computation methodology (e.g., [utility rate per sqft by property type and state] × [listing square footage])
- External rate table source identified and validated (e.g., EIA residential energy cost data or utility provider rate APIs)
- Governance approval of the methodology document
- A new job or extension to `PropertyDnaGenerator` once methodology is approved
- A schema migration is NOT required

**Governance gate:** Methodology must be deterministic and reproducible. Must not use AI estimation, neighborhood income proxies, or demographic weighting. The approved methodology document must be committed to `docs/` before any population begins.

---

### Step 5 — Deterministic Location Score (Internal Rules)

**Fields:** `location_score`

**Why fifth:** Depends on the geocoding infrastructure established in Steps 1–3 and on a formally defined scoring rubric. Location score computation can proceed entirely from internal data (zip code, city, county, U.S. Census Gazetteer data already seeded) once rules are approved.

**Prerequisites:**
- Formal definition of what constitutes a location score (e.g., urban density tier, proximity to metro area, geographic region weighting)
- A governance document defining the scoring rubric — must be committed to `docs/` before implementation
- Confirmation that the rubric contains no Fair Housing proxy inputs
- Implementation within `PropertyDnaGenerator::computeScores()` as a new field group

---

### Step 6 — Deterministic Condition Score (Internal Rules)

**Fields:** `condition_score`

**Why sixth:** Depends entirely on internal meta fields that already exist. Implementation is straightforward once a scoring rubric is approved. No external dependencies.

**Prerequisites:**
- Formal scoring rubric: e.g., `condition_prop` enum values mapped to score ranges, `year_built` decade brackets as modifiers, presence of renovation/update meta keys as positive modifiers
- A governance document defining the rubric
- Implementation within `PropertyDnaGenerator::computeScores()`

---

### Step 7 — Deterministic Legal Score (Internal Rules)

**Fields:** `legal_score`

**Why seventh:** Depends on the full disclosure/legal meta key set (HOA, CDD, flood disclosure, title status, parcel data). These fields were expanded in earlier phases. Implementation is straightforward once a scoring rubric is approved.

**Prerequisites:**
- Formal scoring rubric: e.g., a completeness-weighted score across required legal/disclosure fields
- A governance document defining the rubric
- Confirmation that the rubric measures disclosure completeness only, not legal risk or title quality
- Implementation within `PropertyDnaGenerator::computeScores()`

---

### Step 8 — Compatibility Coverage Field Strategy (Formal Definition Required)

**Fields:** `compatibility_score`

**Why last:** This column's purpose overlaps conceptually with `listing_compatibility_scores` (already implemented). Its definition requires a formal architecture decision that resolves the relationship between the two systems. Implementing without that decision risks creating a conflicting or redundant metric.

**Prerequisites:**
- A formal governance document defining exactly what `property_dna_profiles.compatibility_score` represents and how it differs from `listing_compatibility_scores.overall_score`
- Architecture approval for how the two metrics coexist
- Implementation plan that does not modify the existing compatibility scoring system

---

## 8. Overall Governance Status

| Reserve Category | Count | Status |
|-----------------|-------|--------|
| External API Required | 6 (`walk_score`, `transit_score`, `bike_score`, `school_rating`, `flood_zone_verified`, `estimated_monthly_utilities`) | All confirmed null; no accidental population |
| Future Deterministic Rules Required | 3 (`location_score`, `condition_score`, `legal_score`) | All explicitly set to null in generator; no accidental population |
| Reserved / Do Not Populate Yet | 1 (`compatibility_score`) | Explicitly set to null in generator; definition blocked pending governance decision |
| Governance-Blocked | 0 | No reserved field falls into this category |
| **Total Reserved** | **10** | **All null, verified clean, admin inspector badges confirmed valid** |

### Governance Compliance Summary

- No reserved field is populated with placeholder, fake, inferred, or estimated data. **PASS**
- No reserved field is exposed in any public, client, or agent-facing view. **PASS**
- Admin inspector reserved badges are correctly rendered for all ten fields. **PASS**
- No scoring or compatibility formula is affected by this phase. **PASS**
- No routes, migrations, schema, API, PDF, email, or export changes were made. **PASS**
- No PHP files were modified; no syntax check is required. **PASS**

---

## 9. PHP Syntax Verification

No PHP files were modified in this phase. This audit is documentation-only. The syntax check requirement is satisfied by the absence of any PHP file changes.

---

*End of audit. This document supersedes the reserved-field remarks in `docs/PROPERTY_DNA_PHASE_G_READINESS_AUDIT.md` (Section 7a, R-07, R-09) and is consistent with the Phase H inspector plan (Section 3a field allowlists). Future phases implementing any reserved field must reference this document and update it or create a successor governance record.*
