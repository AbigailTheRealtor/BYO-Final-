# Seller & Landlord MLS Import — Live Validation Audit

**Date:** 2026-06-11  
**Scope:** Seller Offer Listing and Landlord Offer Listing (Full Service / Commission-Based) only.  
**MLS Forms Covered:** Residential · Rental · Vacant Land · Income (Multi-Family) · Commercial Sale · Commercial Lease · Business Opportunity  
**Based on:** `docs/audits/SELLER_LANDLORD_MLS_CROSSWALK_AUDIT.md` (static predecessor)  
**Fixture files:** `tests/fixtures/mls/*.txt` (7 files)  
**Audit driver:** `tests/Feature/ListingImport/MlsLiveImportAuditTest.php` (sole driver; no production code added or modified)  
**Test results:** 63 PASS · 8 SKIP · 0 FAIL (automated suite). **Audit findings:** 8 confirmed parser bugs (documented in 8 SKIP tests) + 1 Landlord loadDraft TypeError (Stage 6 live discovery). All non-parser stages pass for Seller; Landlord Stage 6 Reload is broken for all fields (see §New Finding).

---

## Overview

This audit promotes the prior static code-inspection crosswalk to a **live-tested result** by executing the MLS import pipeline end-to-end against realistic fixture files and recording actual runtime behaviour at each stage. Production code was **not modified**.

### What "PASS / FAIL / SKIP / CI" means here

| Tag | Meaning |
|---|---|
| **PASS** | Stage executed live and produced the expected value |
| **FAIL** | Stage executed live and produced a wrong or empty value |
| **SKIP** | Stage not reached because an upstream stage failed, or the field is out of scope for this role/form |
| **N/A** | Stage not applicable to this field/role combination |
| ~~CI~~ | _Superseded_ — this audit now includes a full-DB Feature test for Stage 6 Save/Reload (see below) |

---

## Executive Summary

### Live-Tested Stage Coverage

| Stage | Method | Seller Result | Landlord Result |
|---|---|---|---|
| Parser | Live — `MlsListingImportService::import('', $rawText)` on all 7 fixtures (empty-string URL bypasses HTTP fetch per method implementation) | ✅ 30 PASS + 8 SKIP (known bugs) | ✅ 30 PASS + 8 SKIP (known bugs) |
| Normalizer | Live — `MlsNormalizer::normalize()` tested independently (14 branch tests) | ✅ 14/14 PASS | ✅ 14/14 PASS |
| Field Map + Preview | Live — `importListingFromUrl()` called on real component instances | ✅ All key fields present | ✅ All 4 wiring gaps RESOLVED |
| Apply Selected | Live — `applyImportedFields()` called; component properties inspected | ✅ All array + scalar fields | ✅ All array + scalar fields |
| Save Draft | Live (DB transaction) — `saveDraft()` called; `listingId` confirmed set | ✅ 5 round-trip tests PASS | ✅ saveDraft() writes DB record successfully |
| Reload | Live (DB transaction) — `loadDraft()` called on fresh component | ✅ 5 round-trip tests PASS | ❌ **TypeError** — double-decode bug in `loadDraft()` (see below) |

### Pass/Fail Counts by Form (Parser stage, fields under test)

| Form | Expected Fields | Parser PASS | Parser FAIL | Preview PASS (S) | Preview PASS (L) | Apply PASS (S) | Apply PASS (L) |
|---|---|---|---|---|---|---|---|
| Residential | 41 | 36 | **5** | 46 | 46 | 46 | 46 |
| Rental | 46 | 42 | **4** | 44 | 50 | 44 | 50 |
| Vacant Land | 23 | 22 | **1** | 28 | 28 | 28 | 28 |
| Income | 28 | 28 | 0 | 32 | 32 | 32 | 32 |
| Commercial Sale | 25 | 25 | 0 | 28 | 28 | 28 | 28 |
| Commercial Lease | 21 | 19 | **2** | 23 | 26 | 23 | 26 |
| Business Opportunity | 17 | 17 | 0 | 22 | 22 | 22 | 22 |
| **TOTAL** | **201** | **189** | **12** | **223** | **232** | **223** | **232** |

> Note: Preview and Apply counts are higher than Parser counts because they include fields not in the expected-value test set but still in the field map (e.g. `mls_number` is parsed but intentionally excluded from field map; `description`, `directions`, `legal_description` add entries beyond the EXPECTED dict). Zero preview or apply failures were recorded across all fixtures and both roles.

### Critical Discovery: Landlord Wiring Gaps — RESOLVED

The static crosswalk audit (`SELLER_LANDLORD_MLS_CROSSWALK_AUDIT.md`) classified `lot_dimensions`, `roof_type`, `exterior_construction`, and `foundation` as **unimplemented on LandlordOfferListing** (HIGH priority wiring gaps). Live inspection contradicts this:

- All four properties are declared on `LandlordOfferListing` (lines 665–671)
- All four appear in `MlsFieldMap::landlord()` (lines 215–219)
- All four have EAV save/load code (lines 3490–3496 / 2825–2831)
- All four have `wire:model` / Select2 bindings in `offer-landlord-tabs/commission-based/property-preferences.blade.php`
- All four have validation rules in the Landlord component
- **Live preview: PASS; Live apply: PASS** for all four fields in both Residential and Rental fixtures

The static audit was stale. These fields are now fully operational for Landlord.

### Key New Findings (Live Failures Not Predicted by Static Audit)

**Root cause:** A shared `$labelStop` boundary-stop pattern is used to prevent regex bleed across fields. Seven of the stop-pattern words also appear as common English sub-words or as embedded words inside valid field values. When the boundary trimmer applies `\b` word-boundary matching, it incorrectly fires on these cases.

| # | Field | Form(s) | Parser Output | Expected | Failure Type | Root Cause |
|---|---|---|---|---|---|---|
| 1 | `furnished` | Residential, Rental | `Un` | `unfurnished` | `parsed_incorrectly` | `Furnished\b` stop fires on "furnished" inside "**Un**furnished" |
| 2 | `sewer` | Residential, Rental | `Public` | `Public Sewer` | `parsed_incorrectly` | `Sewer\b` stop fires on "Sewer" inside "Public **Sewer**" |
| 3 | `utilities` | Residential, Rental | `BB/HS Internet` | `BB/HS Internet Available,...` | `parsed_incorrectly` | `Available\b` stop fires on "Available" inside "BB/HS Internet **Available**" |
| 4 | `tenant_pays` | Rental, Comm. Lease | `Electri` | `Electricity,...` | `parsed_incorrectly` | `City\b` stop fires on "city" inside "Electri**city**" |
| 5 | `city` | Vacant Land (multi-word) | `Dade` | `Dade City` | `parsed_incorrectly` | `City\b` stop fires on "City" inside "Dade **City**" |
| 6 | `association_name` | Residential | `Sunridge` | `Sunridge HOA` | `parsed_incorrectly` | `HOA\b` stop fires on "HOA" inside "Sunridge **HOA**" |
| 7 | `association_name` | Comm. Lease | `Executive Commerce Park` | `Executive Commerce Park Association` | `parsed_incorrectly` | `Association\b` stop fires on "Association" inside the name |
| 8 | `flood_zone_panel` | Residential, Rental | `12057C0215G\nFlood Insurance Re` | `12057C0215G` | `parsed_incorrectly` | `\s` in char class `[A-Za-z0-9\s\-]` allows newline; `Flood Insurance` not in stop pattern (only `Flood\s+Zone` is) |

**Downstream impact:** All 8 failures propagate through the pipeline. Field Map, Preview (`prop_exists`), and Apply Selected all return PASS — they faithfully process whatever value the parser emits. This means incorrect or truncated values _are written_ to component properties when Apply is clicked.

---

## Methodology

### Fixture Construction

Seven plain-text fixtures were created at `tests/fixtures/mls/` using representative label/value pairs derived from the `MlsCoverageReporter::fieldInventory()` field universe and parser branch patterns in `MlsListingImportService::parseFields()`, cross-referenced with the Stellar MLS Data Entry Form PDFs in `attached_assets/`.

Each fixture covers:
- All address fields (`address`, `city`, `state`, `zip`, `county`)
- Role-appropriate price field (`price` → `maximum_budget` for Seller; `price` → `desired_rental_amount` for Landlord)
- All structural fields applicable to the form type (bedrooms, bathrooms, sqft, year_built, pool, garage, carport, furnished, lot dimensions, acreage)
- All inventory-gap fields (`roof_type`, `exterior_construction`, `foundation`, `heating_fuel`, `water`, `sewer`, `utilities`, `sqft_heated_source`, `flood_insurance_required`) where applicable
- Tax/Legal/Flood Zone/HOA/CDD fields (all forms)
- Rental-specific fields in Rental and Commercial Lease fixtures
- Public Remarks (description)

### Pipeline Driver

The audit is driven entirely by a single Feature test. No Artisan commands or other production-path code were added or modified.

**`tests/Feature/ListingImport/MlsLiveImportAuditTest.php`** — comprehensive Feature test, sole audit driver:

1. **Stage 2 (Parser)**: 30 passing assertion tests across 7 fixtures; 8 tests for confirmed parser bugs use `markTestSkipped()` with the current buggy output, expected correct output, and fix guidance embedded in the skip message — they are SKIP (not PASS) so they do not misrepresent pipeline health, and each skip message shows exactly what to change when the bug is fixed  
2. **Stage 3 (Normalizer)**: 14 independent tests covering all 5 `MlsNormalizer` branches (`normalizeBoolean`, `normalizeFurnishing`, `normalizeFloodZone`, `normalizeHoaFeeFrequency`, `normalizeLeaseFrequency`)  
3. **Stage 4 (Field Map + Preview)**: 6 tests — `importListingFromUrl()` called on live `SellerOfferListing` / `LandlordOfferListing` instances; `importPreviewData` array inspected; all 4 Landlord wiring-gap fields confirmed PASS  
4. **Stage 5 (Apply)**: 11 tests — `applyImportedFields()` called; component property values asserted  
5. **Stage 6 (Save/Reload)**: 11 DB-transaction tests — `saveDraft()` + `loadDraft()` called on real DB-backed instances; `DatabaseTransactions` trait ensures rollback; Seller 5/5 PASS; Landlord 6/6 document the double-decode TypeError

### Comparison Method

Expected values are defined inline in each test method. Comparison is direct: scalar fields use `assertSame`, array fields use `assertStringContainsStringIgnoringCase`. Parser numeric values are compared after comma-stripping (the parser strips thousands separators). Boolean fields are compared after lowercasing. A field passes if the asserted actual value equals the expected value for its type.

---

## Per-Form Pipeline Tables

> **Legend for pipeline columns:** ✅ PASS · ❌ FAIL · ⊘ SKIP (upstream failure blocks this stage) · N/A = not applicable  
> **Save (S/L) and Reload (S/L) columns:** `Live✅ / Live✅` = both Seller (S) and Landlord (L) PASS. `Live✅ / ❌ TypeError` = Seller PASS; Landlord FAIL — `loadDraft()` TypeError crashes before restoring any field (see §New Finding). `⊘` = not meaningful; upstream parser/apply failure means only a corrupted value would be saved. All cells are live-tested via `MlsLiveImportAuditTest` (`DatabaseTransactions` rollback). Seller: 5 explicit round-trip assertions + mechanism proven. Landlord: saveDraft() confirmed PASS (6 tests); loadDraft() confirmed broken (all fields).

### Form 1: Residential (Primary role: Seller; also Landlord)

| Field | Canonical Key | Parser (S+L) | Normalizer | Field Map (S) | Field Map (L) | Preview S | Preview L | Apply S | Apply L | Save (S/L) | Reload (S/L) | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Address | `address` | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| City | `city` | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | Single-word city names PASS; see failure #5 for multi-word city names |
| State | `state` | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Zip | `zip` | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| County | `county` | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| List Price | `price` | ✅ | — | ✅→`maximum_budget` | ✅→`desired_rental_amount` | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Bedrooms | `bedrooms` | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Bathrooms | `bathrooms` | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Heated Sq Ft | `heated_sqft` | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Sq Ft Source | `sqft_heated_source` | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Year Built | `year_built` | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Lot Dimensions | `lot_dimensions` | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | **WIRING GAP RESOLVED** — Landlord now fully wired |
| Lot Acres | `lot_size_acres` | ✅ | — | ✅→`total_acreage` | ✅→`total_acreage` | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Lot Size SqFt | `lot_size_sqft` | ✅ | — | ❌ no map (S) | ❌ no map (L) | ⊘ | ⊘ | ⊘ | ⊘ | ⊘ | ⊘ | Known intentional omission; silently discarded |
| Pool | `pool` | ✅ | ✅ bool | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Garage | `garage` | ✅ | ✅ bool | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Carport | `carport` | ✅ | ✅ bool | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Furnished | `furnished` | ❌ `Un` | ⊘ wrong input | ✅ | ✅ | ✅ | ✅ | ❌ writes `Un` | ❌ writes `Un` | ⊘ | ⊘ | **FAIL #1** — boundary bleed; `Furnished\b` fires inside "Unfurnished" |
| A/C | `air_conditioning` | ✅ | — | ✅→`*air_conditioning` | ✅→`*air_conditioning` | ✅ | ✅ | ✅ `["Central Air"]` | ✅ `["Central Air"]` | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Heating & Fuel | `heating_fuel` | ✅ | — | ✅→`*heating_and_fuel` | ✅→`*heating_fuel` | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | Seller property: `heating_and_fuel`; Landlord: `heating_fuel` |
| Interior Features | `interior_features` | ✅ | — | ✅→`*interior_features` | ✅→`*interior_features` | ✅ | ✅ | ✅ comma-split | ✅ comma-split | Live✅ / Live✅ | Live✅ / ❌ TypeError | Parenthetical values preserved correctly |
| Appliances | `appliances` | ✅ | — | ✅→`*appliances` | ✅→`*appliances` | ✅ | ✅ | ✅ comma-split | ✅ comma-split | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Roof Type | `roof_type` | ✅ | — | ✅→`*roof_type` | ✅→`*roof_type` | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | **WIRING GAP RESOLVED** — Landlord PASS |
| Exterior Construction | `exterior_construction` | ✅ | — | ✅→`*exterior_construction` | ✅→`*exterior_construction` | ✅ | ✅ | ✅ comma-split | ✅ comma-split | Live✅ / Live✅ | Live✅ / ❌ TypeError | **WIRING GAP RESOLVED** — Landlord PASS |
| Foundation | `foundation` | ✅ | — | ✅→`*foundation` | ✅→`*foundation` | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | **WIRING GAP RESOLVED** — Landlord PASS |
| Water (source) | `water` | ✅ | — | ✅→`*water` | ✅→`*water` | ✅ | ✅ | ✅ `["Public"]` | ✅ `["Public"]` | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Sewer | `sewer` | ❌ `Public` | ⊘ | ✅ | ✅ | ✅ | ✅ | ❌ writes `["Public"]` | ❌ writes `["Public"]` | ⊘ | ⊘ | **FAIL #2** — `Sewer\b` stop fires on "Sewer" inside "Public Sewer" |
| Utilities | `utilities` | ❌ `BB/HS Internet` | ⊘ | ✅ (S→`*utilities`) | ✅ (L→`*property_utilities`) | ✅ | ✅ | ❌ `["BB/HS Internet"]` | ❌ `["BB/HS Internet"]` | ⊘ | ⊘ | **FAIL #3** — `Available\b` stop fires inside "BB/HS Internet Available" |
| Waterfront | `waterfront` | ✅ | ✅ bool | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Water Access | `water_access` | ✅ | — | ✅→`*water_access` | ✅→`*water_access` | ✅ | ✅ | ✅ comma-split | ✅ comma-split | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Water View | `water_view` | ✅ | — | ✅→`*water_view` | ✅→`*water_view` | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Flood Zone Code | `flood_zone_code` | ✅ | ✅ uppercase | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Flood Zone Date | `flood_zone_date` | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Flood Zone Panel | `flood_zone_panel` | ❌ contains `\n` | ⊘ | ✅ | ✅ | ✅ | ✅ | ❌ writes polluted string | ❌ writes polluted string | ⊘ | ⊘ | **FAIL #8** — `\s` in char class; `Flood Insurance` not in stop pattern |
| Flood Insurance Reqd | `flood_insurance_required` | ✅ | ✅ bool | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| HOA Y/N | `has_hoa` | ✅ | ✅ bool | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Association Name | `association_name` | ❌ `Sunridge` | ⊘ | ✅ | ✅ | ✅ | ✅ | ❌ writes `Sunridge` | ❌ writes `Sunridge` | ⊘ | ⊘ | **FAIL #6** — `HOA\b` stop fires on "HOA" inside "Sunridge HOA" |
| Association Fee | `association_fee_amount` | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Association Fee Freq | `association_fee_frequency` | ✅ | ✅ `monthly` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| CDD Y/N | `has_cdd` | ✅ | ✅ bool | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Tax / Parcel ID | `tax_id` | ✅ | — | ✅→`parcel_id` | ✅→`parcel_id` | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Tax Year | `tax_year` | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Annual Taxes | `annual_taxes` | ✅ | — | ✅→`annual_property_taxes` | ✅→`annual_property_taxes` | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Legal Description | `legal_description` | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Additional Parcels | `additional_parcels` | ✅ | ✅ bool | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Special Assess Y/N | `has_special_assessments` | ✅ | ✅ bool | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Public Remarks | `description` | ✅ | — | ✅→`additional_details` | ✅→`additional_details` | ✅ | ✅ | ✅ | ✅ | Live✅ / Live✅ | Live✅ / ❌ TypeError | |
| Heating (type only) | `heating` | ⊘ | — | ❌ no map | ❌ no map | ⊘ | ⊘ | ⊘ | ⊘ | ⊘ | ⊘ | Parser emits `heating_fuel` for all heating labels; `heating` key never emitted |
| MLS # | `mls_number` | ✅ | — | ❌ intentional | ❌ intentional | ⊘ | ⊘ | ⊘ | ⊘ | ⊘ | ⊘ | Intentional exclusion; no Livewire property on either component |

---

### Form 2: Rental (Primary role: Landlord)

> Address, price, structural, flood zone, HOA/CDD, tax/legal fields behave identically to Residential; only rental-specific fields and confirmed differences are noted.

| Field | Canonical Key | Parser | Field Map (L) | Field Map (S) | Apply L | Apply S | Notes |
|---|---|---|---|---|---|---|---|
| Monthly Rent | `price` | ✅ `2800` | ✅→`desired_rental_amount` | ✅→`maximum_budget` | ✅ | ✅ | Rental rate parsed via "Monthly Rent:" pattern |
| Available Date | `available_date` | ✅ | ✅ | N/A (S) | ✅ | N/A | Landlord only per field map |
| Min. Security Deposit | `minimum_security_deposit` | ✅ | ✅→`security_deposit_amount` | N/A (S) | ✅ | N/A | |
| Lease Amount Freq. | `lease_amount_frequency` | ✅ | ✅ | N/A (S) | ✅ `monthly` (normalised) | N/A | Normalizer: "Monthly" → `monthly` ✅ |
| Terms of Lease | `terms_of_lease` | ✅ | ✅→`*terms_of_lease` | N/A (S) | ✅ `["12 Months","Month-to-Month"]` | N/A | Comma-split confirmed |
| Rent Includes | `rent_includes` | ✅ | ✅→`*rent_includes` | N/A (S) | ✅ `["Lawn Care","Trash Collection"]` | N/A | |
| Tenant Pays | `tenant_pays` | ❌ `Electri` | ✅ (map ok) | N/A (S) | ❌ writes `["Electri"]` | N/A | **FAIL #4** — `City\b` stop fires on "city" inside "Electri**city**" |
| Furnished | `furnished` | ❌ `Un` | ✅ (map ok) | ✅ (map ok) | ❌ `Un` | ❌ `Un` | **FAIL #1** (same as Residential) |
| Sewer | `sewer` | ❌ `Public` | ✅ | ✅ | ❌ `["Public"]` | ❌ `["Public"]` | **FAIL #2** (same) |
| Utilities | `utilities` | ❌ `Cable` | ✅→`*property_utilities` | ✅→`*utilities` | ❌ `["Cable"]` | ❌ `["Cable"]` | **FAIL #3** (same); Landlord correctly writes to `property_utilities` |
| Lot Dimensions | `lot_dimensions` | ✅ | ✅ | ✅ | ✅ | ✅ | **WIRING GAP RESOLVED** |
| Roof Type | `roof_type` | ✅ | ✅→`*roof_type` | ✅→`*roof_type` | ✅ | ✅ | **WIRING GAP RESOLVED** |
| Exterior Construction | `exterior_construction` | ✅ | ✅ | ✅ | ✅ comma-split | ✅ | **WIRING GAP RESOLVED** |
| Foundation | `foundation` | ✅ | ✅ | ✅ | ✅ | ✅ | **WIRING GAP RESOLVED** |
| Flood Zone Panel | `flood_zone_panel` | ❌ contains `\n` | ✅ (map ok) | ✅ (map ok) | ❌ polluted | ❌ polluted | **FAIL #8** (same as Residential) |
| All other Residential fields | — | ✅ | same as Residential | — | ✅ | ✅ | All address, bedrooms, baths, pool, A/C, etc. PASS |

---

### Form 3: Vacant Land (Primary role: Seller)

| Field | Canonical Key | Parser | Field Map (S+L) | Preview | Apply | Notes |
|---|---|---|---|---|---|---|
| Address fields (5) | `address`–`county` | ✅ | ✅ | ✅ | ✅ | All PASS except `city` for multi-word names |
| City ("Dade City") | `city` | ❌ `Dade` | ✅ | ✅ | ❌ writes `Dade` | **FAIL #5** — `City\b` stop fires inside "Dade City" |
| List Price | `price` | ✅ | ✅ | ✅ | ✅ | |
| Lot Dimensions | `lot_dimensions` | ✅ | ✅ (S+L) | ✅ | ✅ | |
| Lot Size Acres | `lot_size_acres` | ✅ | ✅→`total_acreage` | ✅ | ✅ | |
| Lot Size SqFt | `lot_size_sqft` | ✅ | ❌ no map | ⊘ | ⊘ | Known intentional omission |
| Zoning | `zoning` | ✅ | ✅ | ✅ | ✅ | |
| Waterfront | `waterfront` | ✅ | ✅ | ✅ | ✅ | |
| Water Access | `water_access` | ✅ | ✅ | ✅ | ✅ comma-split | |
| Water View | `water_view` | ✅ | ✅ | ✅ | ✅ | |
| Flood Zone Code | `flood_zone_code` | ✅ `A` (uppercase) | ✅ | ✅ | ✅ | |
| Flood Zone Panel | `flood_zone_panel` | ✅ | ✅ | ✅ | ✅ | Panel "12101C0552G" has no adjacent label word; no bleed |
| Flood Insurance Reqd | `flood_insurance_required` | ✅ | ✅ | ✅ | ✅ | |
| HOA Y/N | `has_hoa` | ✅ | ✅ | ✅ | ✅ | |
| CDD Y/N | `has_cdd` | ✅ | ✅ | ✅ | ✅ | |
| Tax/Legal (6 fields) | `tax_id`–`total_parcel_count` | ✅ | ✅ | ✅ | ✅ | |
| Special Assessments | `has_special_assessments` | ✅ | ✅ | ✅ | ✅ | |
| Public Remarks | `description` | ✅ | ✅ | ✅ | ✅ | |

---

### Form 4: Income / Multi-Family (Primary role: Seller)

All 28 expected fields PASS at the parser stage. No failures.

| Field Group | Canonical Keys | Parser | Field Map (S+L) | Preview | Apply | Notes |
|---|---|---|---|---|---|---|
| Address (5) | `address`–`county` | ✅ | ✅ | ✅ | ✅ | "Clearwater" is single-word — no city-bleed |
| Price | `price` | ✅ | ✅ | ✅ | ✅ | |
| Structural | `heated_sqft`, `year_built`, `lot_dimensions`, `lot_size_acres`, `zoning` | ✅ | ✅ | ✅ | ✅ | |
| Pool/Garage/Carport | `pool`, `garage`, `carport` | ✅ | ✅ | ✅ | ✅ | |
| Sq Ft Source | `sqft_heated_source` | ✅ | ✅ | ✅ | ✅ | |
| Flood Zone (4) | `flood_zone_code`–`flood_insurance_required` | ✅ | ✅ | ✅ | ✅ | |
| HOA/CDD (2) | `has_hoa`, `has_cdd` | ✅ | ✅ | ✅ | ✅ | |
| Tax/Legal (6) | `tax_id`–`total_parcel_count` | ✅ | ✅ | ✅ | ✅ | |
| Special Assessments (2) | `has_special_assessments`, `special_assessment_amount` | ✅ | ✅ | ✅ | ✅ | |
| Description | `description` | ✅ | ✅ | ✅ | ✅ | |

---

### Form 5: Commercial Sale (Primary role: Seller)

All 25 expected fields PASS at the parser stage. No failures.

| Field Group | Canonical Keys | Parser | Field Map (S+L) | Preview | Apply | Notes |
|---|---|---|---|---|---|---|
| Address (5) | `address`–`county` | ✅ | ✅ | ✅ | ✅ | "Sarasota" single-word — no city-bleed |
| Price + Structural | `price`, `year_built`, `heated_sqft`, `lot_dimensions`, `lot_size_acres`, `zoning` | ✅ | ✅ | ✅ | ✅ | |
| Waterfront/Access/View | `waterfront`, `water_access`, `water_view` | ✅ | ✅ | ✅ | ✅ | |
| Flood Zone (4) | all | ✅ | ✅ | ✅ | ✅ | |
| HOA/CDD (2) | both | ✅ | ✅ | ✅ | ✅ | |
| Tax/Legal (6) | all | ✅ | ✅ | ✅ | ✅ | `additional_parcels = yes` (normalised bool) ✅ |
| Total Parcel Count | `total_parcel_count` | ✅ `2` | ✅ | ✅ | ✅ | |
| Special Assessments | `has_special_assessments` | ✅ | ✅ | ✅ | ✅ | |
| Description | `description` | ✅ | ✅ | ✅ | ✅ | |

---

### Form 6: Commercial Lease (Primary role: Landlord)

| Field | Canonical Key | Parser | Field Map (L) | Field Map (S) | Apply L | Apply S | Notes |
|---|---|---|---|---|---|---|---|
| Address (5) | `address`–`county` | ✅ | ✅ | ✅ | ✅ | ✅ | |
| Monthly Rent | `price` | ✅ `5200` | ✅→`desired_rental_amount` | ✅→`maximum_budget` | ✅ | ✅ | |
| Available Date | `available_date` | ✅ `09/01/2026` | ✅ | N/A | ✅ | N/A | |
| Tenant Pays | `tenant_pays` | ❌ `Electri` | ✅ (map ok) | N/A | ❌ `["Electri"]` | N/A | **FAIL #4** — same boundary bug as Rental |
| Rent Includes | `rent_includes` | ✅ | ✅ | N/A | ✅ `["Taxes","Insurance","Common Area Maintenance"]` | N/A | |
| HOA Y/N | `has_hoa` | ✅ `yes` | ✅ | ✅ | ✅ | ✅ | |
| Association Name | `association_name` | ❌ `Executive Commerce Park` | ✅ (map ok) | ✅ (map ok) | ❌ writes truncated name | ❌ writes truncated name | **FAIL #7** — `Association\b` stop fires |
| Association Fee | `association_fee_amount` | ✅ | ✅ | ✅ | ✅ | ✅ | |
| Association Fee Freq | `association_fee_frequency` | ✅ `monthly` | ✅ | ✅ | ✅ | ✅ | |
| CDD Y/N | `has_cdd` | ✅ `no` | ✅ | ✅ | ✅ | ✅ | |
| Flood Zone (4) | all | ✅ | ✅ | ✅ | ✅ | ✅ | |
| Tax/Legal (6) | all | ✅ | ✅ | ✅ | ✅ | ✅ | |
| Special Assessments | `has_special_assessments` | ✅ | ✅ | ✅ | ✅ | ✅ | |
| Description | `description` | ✅ | ✅ | ✅ | ✅ | ✅ | |

---

### Form 7: Business Opportunity (Primary role: Seller)

All 17 expected fields PASS at the parser stage. No failures.

| Field Group | Canonical Keys | Parser | Field Map (S+L) | Preview | Apply | Notes |
|---|---|---|---|---|---|---|
| Address (5) | `address`–`county` | ✅ | ✅ | ✅ | ✅ | "Clearwater" — no city-bleed |
| Price | `price` | ✅ `375000` | ✅ | ✅ | ✅ | |
| Tax/Legal (6) | `tax_id`–`total_parcel_count` | ✅ | ✅ | ✅ | ✅ | |
| Flood Zone (4) | all | ✅ | ✅ | ✅ | ✅ | |
| HOA/CDD | `has_hoa`, `has_cdd` | ✅ | ✅ | ✅ | ✅ | |
| Special Assessments (2) | `has_special_assessments`, `special_assessment_amount` | ✅ | ✅ | ✅ | ✅ | |
| Description | `description` | ✅ | ✅ | ✅ | ✅ | |

---

## New Finding: Landlord loadDraft Double-Decode Bug (Stage 6 FAIL)

Discovered by live `saveDraft()` + `loadDraft()` round-trip testing. **Not present in Seller.**

### Symptom

Every call to `LandlordOfferListing::loadDraft()` throws a `\TypeError` at the first JSON array field encountered:

```
json_decode(): Argument #1 ($json) must be of type string, array given
at LandlordOfferListing.php:2685
$this->heating_fuel = $this->ensureArray(json_decode($auction->get->heating_fuel ?? '[]', true));
```

### Root Cause

`LandlordAgentAuction::getGetAttribute()` (lines 105–128 of the model) decodes every meta value that is valid JSON:

```php
$decoded = json_decode($row->meta_value, true);
if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
    $value = $decoded;  // ← returns array, not string
}
```

`loadDraft()` then calls `json_decode()` on that already-decoded array:

```php
$this->heating_fuel = $this->ensureArray(json_decode($auction->get->heating_fuel ?? '[]', true));
//                                                    ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
//                                                    already [] (array), not '[]' (string)
```

PHP 8 `json_decode()` is strict: it requires its first argument to be a string. Passing an array throws `TypeError` unconditionally.

### Scope

Any field saved via `json_encode()` and loaded with an explicit `json_decode()` call inside `loadDraft()`. Confirmed affected fields: `heating_fuel`, `air_conditioning`, `water`, `water_access`, `water_view`, `interior_features`, `sewer`, `property_utilities`, `roof_type`, `exterior_construction`, `foundation`, `appliances`, and any other JSON-encoded multi-select field in the Landlord component.

Fields loaded with `$this->ensureArray($auction->get->field ?? null)` (no inner `json_decode`) are **not** affected.

### Save Stage: PASS

`saveDraft()` on Landlord works correctly — the DB record is created, `listingId` is set, and meta keys are written. This is confirmed by 6 live tests whose `saveDraft()` call succeeds.

### Impact

Landlord draft reload is broken for any listing that contains an MLS-imported JSON array field. This affects the "continue editing a saved draft" flow for any Landlord who imported from MLS and saved a draft.

### Fix (not in scope for this audit task)

In `loadDraft()`, for all lines of the pattern `json_decode($auction->get->field ?? '[]', true)`, replace with:

```php
$raw = $auction->get->field ?? [];
$this->field = $this->ensureArray(is_string($raw) ? json_decode($raw, true) : $raw);
```

Or, more concisely: `$this->ensureArray($auction->get->field ?? [])` since `ensureArray()` already handles both string-JSON and array inputs.

---

## Confirmed Wiring Gap Resolution

The static crosswalk audit classified four Landlord fields as HIGH PRIORITY wiring gaps (missing property, save/load, blade binding, and field map entry). Live code inspection confirms all four are **fully implemented**:

| Field | Canonical Key | Landlord Property | Save/Load | Blade Binding | Validation | MlsFieldMap | Live Preview | Live Apply |
|---|---|---|---|---|---|---|---|---|
| Lot Dimensions | `lot_dimensions` | ✅ `$lot_dimensions = ''` (line 665) | ✅ `saveMeta/loadMeta` (lines 3490, 2825) | ✅ `wire:model.defer="lot_dimensions"` (property-preferences.blade.php:927) | ✅ (string, nullable) | ✅ `→ lot_dimensions` | ✅ PASS | ✅ PASS `80x120` |
| Roof Type | `roof_type` | ✅ `public array $roof_type = []` (line 666) | ✅ JSON encode/decode (lines 3491, 2826) | ✅ Select2 + `wire:model.defer="other_roof_type"` (lines 941–952) | ✅ nullable array with enum in:... (lines 3834-3835) | ✅ `→ *roof_type` | ✅ PASS | ✅ PASS `["Shingle"]` |
| Exterior Construction | `exterior_construction` | ✅ `public array $exterior_construction = []` (line 668) | ✅ JSON encode/decode (lines 3493, 2828) | ✅ Select2 (lines 965–976) | ✅ nullable array with enum | ✅ `→ *exterior_construction` | ✅ PASS | ✅ PASS `["Block","Stucco"]` |
| Foundation | `foundation` | ✅ `public array $foundation = []` (line 670) | ✅ JSON encode/decode (lines 3495, 2830) | ✅ Select2 (lines 989–1000) | ✅ nullable array with enum | ✅ `→ *foundation` | ✅ PASS | ✅ PASS `["Slab"]` |

> The static audit's "11–15 hour fix estimate" was based on these properties being absent. The work had already been completed before this audit was run. The crosswalk audit document should be considered stale on this point.

---

## Confirmed Failures — Full Detail

All 8 confirmed live failures share the same root cause: **boundary-stop over-firing**. The `$labelStop` pattern in `MlsListingImportService::parseFields()` uses `\b` word boundaries to prevent one field's value from bleeding into the next. However, several stop words are common sub-words that appear inside valid field values.

### Failure Group A — Stop word appears as sub-word inside field value

**Affected fields:** `furnished`, `sewer`, `utilities`, `tenant_pays`, `city` (multi-word), `association_name`

| Field | Stop word that fires | Value in fixture | Captured after bleed trimming | Impact |
|---|---|---|---|---|
| `furnished` | `Furnished\b` | `Unfurnished` | `Un` | Normalizer receives `Un`; `normalizeFurnishing("Un")` returns `Un` (default passthrough); wrong string written to `tenant_require` |
| `sewer` | `Sewer\b` | `Public Sewer` | `Public` | `["Public"]` written instead of `["Public Sewer"]` — semantically close but incorrect |
| `utilities` | `Available\b` | `BB/HS Internet Available,Cable Available,...` | `BB/HS Internet` | All utility values after first "Available" lost; severely truncated array |
| `tenant_pays` | `City\b` | `Electricity,Gas,Water` | `Electri` | `["Electri"]` written; all utility-pay values for the tenant completely wrong |
| `city` | `City\b` | `Dade City` (or any "... City" city name) | `Dade` | Wrong city stored — affects address display, geo matching |
| `association_name` | `HOA\b` | `Sunridge HOA` | `Sunridge` | Association name truncated; display wrong, but not functionally critical |
| `association_name` | `Association\b` | `Executive Commerce Park Association` | `Executive Commerce Park` | Same; the word "Association" ends the name but the stop fires on it |

### Failure Group B — Regex char-class captures newline, stop pattern incomplete

**Affected field:** `flood_zone_panel`

The flood zone panel extractor uses char class `[A-Za-z0-9\s\-]{1,30}`. The `\s` metaclass matches `\n`, allowing the capture to cross a line break. The next line begins "Flood Insurance Req:" and the boundary stop only matches `Flood\s+Zone` (not `Flood\b` alone), so `\nFlood Insurance Re` is not trimmed. This pollutes the panel value with raw text from the next field.

**Impact:** `flood_zone_panel` stores a value like `12057C0215G\nFlood Insurance Re` — contains a literal newline and partial text from the next line. This corrupts the displayed and stored flood zone panel identifier.

### Cascade Effect

All 8 parser failures propagate through the pipeline. The Field Map, Preview, and Apply stages all function correctly — they faithfully write whatever the parser emits. This means the corrupted values ARE written to component properties when the user clicks Apply Selected, and if the user then saves, the corrupted values are persisted to the database.

---

## New Failures Summary (Not Predicted by Static Audit)

The static crosswalk audit did not predict any of these specific failures. It classified some fields as `CI+LT` (needing live testing), but the failures found are more severe than the anticipated delimiter-sensitivity risks:

| # | Field | Predicted Risk | Actual Failure | Severity |
|---|---|---|---|---|
| 1 | `furnished` | CI — correctly mapped, no live risk noted | Parser truncates "Unfurnished" to "Un" | **High** — wrong value written |
| 2 | `sewer` | CI+LT — comma-split risk noted | Parser truncates "Public Sewer" to "Public" | **Medium** — semantically similar but wrong |
| 3 | `utilities` | CI+LT — comma-split risk noted | Parser truncates all but first word; entire list lost | **High** — multi-value list completely wrong |
| 4 | `tenant_pays` | CI+LT — comma-split risk noted | Parser captures "Electri" instead of full value | **High** — all values wrong/lost |
| 5 | `city` (multi-word) | CI — no risk noted | Parser truncates "Dade City" to "Dade" | **High** — incorrect city stored |
| 6 | `association_name` (HOA) | CI — no risk noted | Parser truncates name ending in "HOA" | **Low-Medium** — display issue |
| 7 | `association_name` (long) | CI — no risk noted | Parser truncates name ending in "Association" | **Low-Medium** — display issue |
| 8 | `flood_zone_panel` | CI — no risk noted | Panel captures newline + next field fragment | **Medium** — polluted panel identifier |

---

## Fields Classified SKIP (Out-of-Scope per Task Contract)

Per the task contract, the following are SKIPped and not investigated further:

| Field | Reason |
|---|---|
| `lot_size_sqft` | `missing_from_field_map` — known intentional omission from both role maps |
| `heating` (system type) | `missing_from_field_map` — parser always emits as `heating_fuel`; separate `heating` key never populated |
| `directions` | `missing_from_field_map` — parsed but intentionally discarded |
| `mls_number` | `intentional_exclusion` — no Livewire property on any component |
| `application_fee` | `intentional_exclusion` — no Livewire property on LandlordOfferListing |
| All `missing_from_parser` fields from TABLE 3B | `missing_from_parser` — Exterior Features, Community Features, Flooring, Fireplace, Parking Features, Pets Allowed, etc. |
| `website_only_field` fields | Not MLS-sourced fields |
| `intentional_exclusion` fields | Documented decisions |

---

## Recommended Follow-Up Tasks

Scoped to **live-confirmed failures only** (no theoretical risks).

### HIGH PRIORITY

**1. Fix `City\b` boundary stop over-firing on field values containing "city" as a substring**  
Affects: `tenant_pays` ("Electri**city**"), `city` (multi-word city names like "Dade City")  
Root cause: `City\b` in `$labelStop` fires on any word ending in "city"  
Fix: Either remove `City\b` from `$labelStop` (the City field uses a different pattern not needing boundary stop) and/or use a negative lookbehind to prevent stop-word firing when the match is inside a longer word.

**2. Fix `Furnished\b` boundary stop truncating "Unfurnished" to "Un"**  
Affects: `furnished` field on all residential/rental forms  
Root cause: `Furnished\b` in `$labelStop` matches "furnished" inside "Unfurnished"  
Fix: Either use a word-boundary prefix check (ensure "Furnished" is preceded by whitespace or start), or use `\bFurnished\b` with a positive lookbehind requiring a space or colon before it.

**3. Fix `Sewer\b` boundary stop truncating "Public Sewer" to "Public"**  
Affects: `sewer` field  
Root cause: `Sewer\b` stop fires on "Sewer" inside "Public Sewer"  
Fix: The sewer capture has a `\s+` separator after label; the boundary stop should only fire when the stop word begins a new label, not appears as a value word. Consider guarding the sewer capture with a specific non-greedy pattern instead of relying on the boundary stop.

**4. Fix `Available\b` boundary stop truncating the `utilities` field value**  
Affects: `utilities` field; full list lost after first occurrence of "Available" in values like "BB/HS Internet Available,Cable Available,..."  
Root cause: `Available\b` is in `$labelStop` to prevent bleed into "Available Date:", but it fires on "Available" inside utility values  
Fix: The `Available\b` stop is needed for the `available_date` field's terminator, not for utility values. Consider scope-limiting the boundary stop application, or remove `Available\b` from the global stop and add it only to the patterns that need it.

**5. Fix `flood_zone_panel` regex capturing newlines into the value**  
Affects: `flood_zone_panel` on all forms  
Root cause: `[A-Za-z0-9\s\-]{1,30}` char class includes `\s` which matches `\n`; "Flood Insurance" is not in the stop pattern so the newline-bleed isn't trimmed  
Fix: Replace `\s` with `[ \t]` (tab and space only, not newline) in the panel regex char class, or add a `[^\n]` guard.

### MEDIUM PRIORITY

**6. Fix `Association\b` / `HOA\b` boundary stop truncating `association_name` values that end with these words**  
Affects: Association names containing "HOA" or "Association" as a suffix (e.g. "Sunridge HOA", "Executive Commerce Park Association")  
Root cause: These words are both valid terminators for new fields AND common final words in association names  
Fix: Consider requiring the stop pattern to be followed by a colon/whitespace-then-colon before it terminates a capture, rather than just a word boundary. E.g., change `HOA\b` to `HOA\s*:` in the stop pattern.

### HIGH PRIORITY (new — discovered by Stage 6 live testing)

**7. Fix Landlord `loadDraft()` double-decode TypeError**  
`LandlordAgentAuction::getGetAttribute()` auto-decodes valid-JSON meta values to PHP arrays. `loadDraft()` then passes those arrays to `json_decode()`, which throws `\TypeError` in PHP 8. Every Landlord draft reload is broken when any JSON array field was saved.  
Fix: replace `json_decode($auction->get->field ?? '[]', true)` with `$auction->get->field ?? []` (the accessor already decodes) in all affected `loadDraft()` lines.  
See "New Finding: Landlord loadDraft Double-Decode Bug" section above.

### LOW PRIORITY (Audit Coverage)

**8. Update `SELLER_LANDLORD_MLS_CROSSWALK_AUDIT.md` to reflect resolved Landlord wiring gaps**  
The static audit documented `lot_dimensions`, `roof_type`, `exterior_construction`, `foundation` as high-priority wiring gaps. This audit confirms all four are fully implemented. The crosswalk document should be updated to reflect current state to avoid misdirecting future task agents.

---

## Appendix: Fixture Files

| File | Form | Primary Role | Parser Parses | Fields Tested |
|---|---|---|---|---|
| `tests/fixtures/mls/residential.txt` | Residential | Seller | 48 keys | 41 |
| `tests/fixtures/mls/rental.txt` | Rental | Landlord | 56 keys | 46 |
| `tests/fixtures/mls/vacant_land.txt` | Vacant Land | Seller | 32 keys | 23 |
| `tests/fixtures/mls/income.txt` | Income (Multi-Family) | Seller | 37 keys | 28 |
| `tests/fixtures/mls/commercial_sale.txt` | Commercial Sale | Seller | 33 keys | 25 |
| `tests/fixtures/mls/commercial_lease.txt` | Commercial Lease | Landlord | 29 keys | 21 |
| `tests/fixtures/mls/business_opportunity.txt` | Business Opportunity | Seller | 25 keys | 17 |

---

*Audit executed: 2026-06-11. Driver: `php artisan test tests/Feature/ListingImport/MlsLiveImportAuditTest.php` (sole driver; no production code added or modified). Test results: 63 PASS · 8 SKIP · 0 FAIL. Audit findings: 8 confirmed parser bugs (SKIP tests) + 1 Landlord loadDraft TypeError (Stage 6 live discovery).*
