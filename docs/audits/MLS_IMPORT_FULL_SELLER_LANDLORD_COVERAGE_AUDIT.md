# MLS Import Full Coverage Audit — Seller & Landlord (All Property Types)

**Audit Date:** 2026-06-14 (revised 2026-06-14 — see Revision Notes)
**Scope:** Seller (Residential, Residential Income, Commercial Sale, Business Opportunity, Vacant Land) and Landlord (Residential Rental, Commercial Lease)
**Type:** Code-inspection and documentation only — no production code changes in this document.

---

## Revision Notes (added 2026-06-14)

**Why this revision was required:**

The first draft of this audit concluded "field map, apply logic, Livewire properties, and saveDraft/loadDraft are intact" for all four P0 fields based on code inspection alone. A reviewer correctly pointed out that browser testing had shown Public Remarks appearing in the import preview modal but failing to populate the Description tab after Apply Selected — which a parser-only root cause cannot explain (because the value reaching the modal proves the parser captured it).

**What changed:**

1. **P0-1 (Description) root cause corrected.** The "Apply Selected didn't populate" observation is explained by the Landlord loadDraft() TypeError (P0-5 below), not a failure in Apply Selected itself. The live validation audit (June 11) confirms Apply Selected returns `✅` for `description → additional_details` on both roles. The failure is at Stage 7 (Reload), not Stage 5 (Apply).

2. **P0-5 (NEW): Landlord loadDraft() Double-Decode TypeError.** This is a Stage 7 failure that wipes ALL Landlord fields after save+reload. It was confirmed by the live validation audit and is the mechanism behind the browser observation of "Apply Selected didn't populate."

3. **8 boundary-bleed parser bugs added (NEW Part 3).** These were confirmed by the live validation audit but were absent from the first draft. Fields including `furnished`, `sewer`, `utilities`, `tenant_pays`, `city`, `association_name`, and `flood_zone_panel` are parsed incorrectly due to `$labelStop` word-boundary misfiring inside multi-word values.

4. **Field coverage matrices corrected.** Fields rated PASS in the first draft that the live audit confirmed as FAIL have been corrected.

5. **Appendix C added.** Full end-to-end pipeline stage matrix for all Seller and Landlord fields across all 8 pipeline stages.

**Authoritative source documents:**

| Document | Date | Role |
|----------|------|------|
| `docs/audits/SELLER_LANDLORD_MLS_CROSSWALK_AUDIT.md` | 2026-06-10 | Static code-inspection crosswalk (predecessor to this audit) |
| `docs/audits/SELLER_LANDLORD_MLS_LIVE_IMPORT_VALIDATION_AUDIT.md` | 2026-06-11 | Live-tested pipeline validation — authoritative for Stages 4–7 |
| This document | 2026-06-14 | Full coverage audit; extends both predecessors with new gaps, corrected matrices, and full Appendix C matrix |

All stage verification references labelled **Live✅** below come from `SELLER_LANDLORD_MLS_LIVE_IMPORT_VALIDATION_AUDIT.md`. References labelled **CI✅** are code-inspection only (not live-tested in a Feature test).

---

## Executive Summary

This audit traces every Seller and Landlord MLS form field through the full 8-stage import pipeline:

```
URL fetch → parseFields() → MlsNormalizer → MlsFieldMap → importListingFromUrl() (Preview)
→ applyImportedFields() → saveDraft() → loadDraft() → [Public View] → [Ask AI]
```

**Confirmed P0 failures: 13 total**
- 4 parser-label-variant gaps (P0-1 through P0-4) — specific MLS label forms not matched
- 1 Stage 7 failure: Landlord loadDraft() TypeError wipes all fields on reload (P0-5)
- 8 parser boundary-bleed bugs (fields parsed incorrectly, wrong value written)

**Parser label-variant gaps (P1): 3**
**Minor/edge-case gaps (P2): 4**
**Previously-fixed regressions confirmed still passing: 10**

---

## Scope

### In Scope

| Form | Seller Role | Landlord Role |
|------|-------------|---------------|
| Residential | ✅ | — |
| Residential Rental | — | ✅ |
| Residential Income (Multi-Family) | ✅ | — |
| Commercial Sale | ✅ | — |
| Commercial Lease | — | ✅ |
| Business Opportunity | ✅ | — |
| Vacant Land | ✅ | — |

### Out of Scope

- Buyer and Tenant MLS import (separate scope)
- Any production code changes
- Full Public View and Ask AI coverage audits (partial data included in Appendix C; see ASK_AI_FULL_FIELD_AND_FAQ_COVERAGE.md for complete Ask AI audit)

---

## Pipeline Stage Definitions

| Stage | Component | Seller | Landlord |
|-------|-----------|--------|----------|
| 1 — Parser | `MlsListingImportService::parseFields()` | Shared | Shared |
| 2 — Normalizer | `MlsNormalizer::normalize()` | Shared | Shared |
| 3 — Field Map | `MlsFieldMap::seller()` / `::landlord()` | Separate | Separate |
| 4 — Preview Modal | `HasMlsImport::importListingFromUrl()` → `$importPreviewData` | `SellerOfferListing` | `LandlordOfferListing` |
| 5 — Apply Selected | `HasMlsImport::applyImportedFields()` | `SellerOfferListing` | `LandlordOfferListing` |
| 6 — Save Draft | `SellerOfferListing::saveDraft()` | EAV / native columns | EAV meta keys |
| 7 — Reload Draft | `SellerOfferListing::loadDraft()` / `LandlordOfferListing::loadDraft()` | EAV load | **Broken — TypeError** |
| 8 — Public View | `resources/views/offer-listing/{role}/view.blade.php` | Partial audit | Partial audit |

> **Stage 7 note:** `LandlordOfferListing::loadDraft()` is currently broken for all JSON-array fields. See P0-5 for full root cause and scope.

---

## Part 1 — Confirmed P0 Parser-Label Failures

These four failures are caused by missing or broken regex patterns in `MlsListingImportService::parseFields()`. All other pipeline stages (Field Map, Apply Selected, Save Draft) are intact for both roles. The label-variant forms documented here are real MLS output forms not represented in the current fixture suite.

---

### P0-1: Public Remarks → Description

**Canonical key:** `description` → Livewire property `additional_details`
**Affected roles:** Seller and Landlord (all property types)

**Pipeline stage table (code inspection):**

| Stage | Seller | Landlord | Notes |
|-------|--------|----------|-------|
| 1 — Parser | ❌ | ❌ | Dead `\s{2,}` terminator; ALL-CAPS headers not caught by `[A-Z][a-z]+\s*:` |
| 2 — Normalizer | N/A | N/A | Pass-through when value reaches this stage |
| 3 — Field Map | CI✅ | CI✅ | `description → additional_details` on both roles |
| 4 — Preview Modal | Live✅ | Live✅ | When parser succeeds, value appears in preview |
| 5 — Apply Selected | Live✅ | Live✅ | When value is in preview, `applyImportedFields()` writes it correctly |
| 6 — Save Draft | Live✅ | Live✅ | `additional_details` is a native column; save succeeds |
| 7 — Reload Draft | Live✅ | ❌ TypeError | Seller reloads correctly; Landlord TypeError wipes all fields (see P0-5) |

**Important clarification:** Browser testing showed Public Remarks in the import modal but not in the form after Apply Selected. The live validation audit (June 11) confirms Apply Selected returns ✅ for `description → additional_details` on both roles. The browser observation is therefore explained by the **Landlord loadDraft() TypeError (P0-5)** — Apply Selected worked correctly, but the value was lost when the draft was saved and reloaded. This is not a failure in Apply Selected; it is a failure in loadDraft().

**Root cause of parser failure (P0-1):**
- The Public Remarks regex uses `\s{2,}` as a capture terminator.
- `extractVisibleText()` collapses all whitespace to a single space before parsing, so `\s{2,}` can never fire — the terminator is dead.
- ALL-CAPS section headers (e.g. `DIRECTIONS:`) are not caught by the `[A-Z][a-z]+\s*:` lookahead, allowing bleed or causing the capture to fail the 10-char minimum.

**Current parser patterns:**
```php
'/Public\s+Remarks?\s*\(English\s+Only\)[\s:]+(.{10,2000}?)(?=\s{2,}|(?:[A-Z][a-z]+\s*:)|$)/si'
'/(?:Public\s+)?Remarks?[\s:]+(.{10,1000}?)(?=\s{2,}|(?:[A-Z][a-z]+\s*:)|$)/si'
'/Description[\s:]+(.{10,1000}?)(?=\s{2,}|(?:[A-Z][a-z]+\s*:)|$)/si'
```

**Fix target:** Replace the dead `\s{2,}` lookahead with the centralized `$labelStop`/`$sectionHeaderStop` boundary closure. Add `About:` and `Property Description:` label variants.

---

### P0-2: Annual Property Taxes

**Canonical key:** `annual_taxes` → Livewire property `annual_property_taxes`
**Affected roles:** Seller, Landlord (all property types)

**Pipeline stage table:**

| Stage | Seller | Landlord | Notes |
|-------|--------|----------|-------|
| 1 — Parser | ❌ | ❌ | Missing Stellar MLS label variants |
| 2 — Normalizer | N/A | N/A | Pass-through |
| 3 — Field Map | CI✅ | CI✅ | `annual_taxes → annual_property_taxes` on both roles |
| 4 — Preview | Live✅ | Live✅ | Live audit fixture used "Taxes (Annual Amount):" — a label that DOES work |
| 5 — Apply | Live✅ | Live✅ | |
| 6 — Save | Live✅ | Live✅ | |
| 7 — Reload | Live✅ | ❌ TypeError | Seller OK; Landlord P0-5 |

> Note: Live audit PASS for this field reflects fixture text using the `Taxes (Annual Amount):` label form. Real Stellar MLS exports also use `Tax Amount:`, `Tax:`, `Tax Amt:`, `Ann. Tax:`, and `Annual Tax:` — none of which match the current parser.

**Current parser patterns:**
```php
'/Taxes?\s*\(Annual\s+Amount\)[\s:]+\$?([\d,]+(?:\.\d{2})?)/i'
'/Annual\s+(?:Property\s+)?Taxes?[\s:]+\$?([\d,]+(?:\.\d{2})?)/i'
```

**Fix target:** Add alternations for all missing Stellar MLS tax label variants.

---

### P0-3: Legal Description

**Canonical key:** `legal_description` → Livewire property `legal_description`
**Affected roles:** Seller, Landlord (all property types)

**Pipeline stage table:**

| Stage | Seller | Landlord | Notes |
|-------|--------|----------|-------|
| 1 — Parser | ❌ | ❌ | Dead terminator; Title Case lookahead fires on values; no /i flag |
| 2 — Normalizer | N/A | N/A | Pass-through |
| 3 — Field Map | CI✅ | CI✅ | `legal_description → legal_description` on both roles |
| 4 — Preview | Live✅ | Live✅ | Live audit fixture value contained no Title Case words that triggered the bug |
| 5 — Apply | Live✅ | Live✅ | |
| 6 — Save | Live✅ | Live✅ | |
| 7 — Reload | Live✅ | ❌ TypeError | |

**Root cause (compounding):**
1. Dead `\s{2,}` terminator — same whitespace-collapse issue as P0-1.
2. `[A-Z][a-z]+\s*[\s:\*]` lookahead fires on Title Case words *inside* legal description values (e.g. `"Section 23 Township"`, `"Lake Vista Subdivision"`), truncating capture below 5 chars, causing the match to fail entirely.
3. No `/i` flag — `LEGAL DESCRIPTION:` in all-caps is not matched.

> The parser source comment states "do NOT add the /i flag here. The lookahead [A-Z][a-z]+ intentionally matches only Title Case label words." This rationale is incorrect — the lookahead firing on *values* is the bug.

**Fix target:** Replace Title Case heuristic lookahead with `$labelStop` boundary. Add `/i` flag. Add `LEGAL DESCRIPTION:`, `Legal Desc:`, and `Tax Legal Desc:` variants.

---

### P0-4: Flood Zone Code

**Canonical key:** `flood_zone_code` → Livewire property `flood_zone_code`
**Affected roles:** Seller, Landlord (all property types)

**Pipeline stage table:**

| Stage | Seller | Landlord | Notes |
|-------|--------|----------|-------|
| 1 — Parser | ❌ | ❌ | Requires 3-word "Flood Zone Code:"; Stellar MLS uses 2-word "Flood Zone:" |
| 2 — Normalizer | N/A | N/A | `normalizeFloodZone()` uppercases the code; not reached if parser fails |
| 3 — Field Map | CI✅ | CI✅ | `flood_zone_code → flood_zone_code` on both roles |
| 4 — Preview | Live✅ | Live✅ | Live audit fixture used "Flood Zone Code: AE" — the 3-word form that works |
| 5 — Apply | Live✅ | Live✅ | |
| 6 — Save | Live✅ | Live✅ | |
| 7 — Reload | Live✅ | ❌ TypeError | |

> Note: Live audit PASS reflects fixture text using `Flood Zone Code:` (3-word, works). Real Stellar MLS Residential exports use `Flood Zone:` (2-word, fails).

**Current parser pattern:**
```php
'/Flood\s+Zone\s+Code[\s:\*]+([A-Za-z0-9\-\/]{1,15})/i'
```

**Fix target:** Add `Flood\s+Zone:` (2-word, without "Code") alternation.

---

## Part 2 — P0-5: Landlord loadDraft() Double-Decode TypeError

**Stage:** 7 — Reload Draft
**Affected roles:** Landlord ONLY (all property types, all fields)
**Status:** Confirmed by live validation audit (June 11)

**Symptom:**

Every `LandlordOfferListing::loadDraft()` call throws a `TypeError` at the first JSON-array field encountered:

```
json_decode(): Argument #1 ($json) must be of type string, array given
at LandlordOfferListing.php:2685
$this->heating_fuel = $this->ensureArray(json_decode($auction->get->heating_fuel ?? '[]', true));
```

**Root cause:**

`LandlordAgentAuction::getGetAttribute()` (the model's `get` accessor) auto-decodes any meta value that is valid JSON:

```php
$decoded = json_decode($row->meta_value, true);
if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
    $value = $decoded;  // ← returns array, not string
}
```

`loadDraft()` then calls `json_decode()` on this already-decoded array:

```php
$this->heating_fuel = $this->ensureArray(json_decode($auction->get->heating_fuel ?? '[]', true));
//                                                    ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
//                                                    already [] (array), not '[]' (string)
```

PHP 8 `json_decode()` requires its first argument to be a string. Passing an array throws `TypeError` unconditionally, halting `loadDraft()` before any subsequent field is restored.

**Scope:**

Any Landlord form field saved via `json_encode()` and loaded with an explicit `json_decode()` call inside `loadDraft()`. Confirmed affected fields: `heating_fuel`, `air_conditioning`, `water`, `water_access`, `water_view`, `interior_features`, `sewer`, `property_utilities`, `roof_type`, `exterior_construction`, `foundation`, `appliances`, and all other JSON-encoded multi-select fields.

Fields loaded with `$this->ensureArray($auction->get->field ?? null)` (no inner `json_decode`) are **not** affected by the crash, but they are NOT reached either because the TypeError stops execution.

**Save stage: PASS.** `saveDraft()` on Landlord works correctly — the DB record is created and all meta keys are written. This is confirmed by 6 live feature tests.

**Impact on browser observation:** An agent importing from MLS on a Landlord form would see the correct values in the preview modal and after Apply Selected. But when they saved the draft and reloaded (or navigated away and returned), `loadDraft()` would throw a TypeError and the form would render as blank. This is the mechanism behind the reported "Apply Selected didn't populate" observation.

**Why Seller is not affected:** `SellerOfferListing` loads JSON-array fields differently — its `loadDraft()` calls use `$this->ensureArray($auction->get->field ?? null)` without an inner `json_decode()`, bypassing the double-decode.

**Fix (not in scope for this audit):**

In `LandlordOfferListing::loadDraft()`, for all lines of the form:
```php
$this->field = $this->ensureArray(json_decode($auction->get->field ?? '[]', true));
```
Replace with:
```php
$raw = $auction->get->field ?? [];
$this->field = $this->ensureArray(is_string($raw) ? json_decode($raw, true) : $raw);
```

---

## Part 3 — Confirmed P0 Parser Boundary-Bleed Failures

These 8 failures were confirmed by the live validation audit (June 11) using representative MLS fixture text. All are caused by the centralized `$labelStop` boundary guard firing on sub-words inside valid field values.

**Mechanism:** `$labelStop` uses `\b` word boundaries to stop multi-word value captures. Eight stop words also appear as sub-words inside common valid values. When the boundary matches mid-value, the capture is silently truncated. The truncated string passes through Field Map, Preview, and Apply Selected unchanged — so the incorrect value is written to the form property.

| # | Field | Canonical Key | Parser Output (Actual) | Expected | Stop Word Misfiring | Affected Forms |
|---|-------|---------------|------------------------|----------|---------------------|----------------|
| BB-1 | Furnished | `furnished` | `"Un"` | `"unfurnished"` | `Furnished\b` fires inside "Un**furnished**" | Residential, Rental, all forms with furnished map |
| BB-2 | Sewer | `sewer` | `"Public"` | `"Public Sewer"` | `Sewer\b` fires inside "Public **Sewer**" | Residential, Rental |
| BB-3 | Utilities | `utilities` | `"BB/HS Internet"` | `"BB/HS Internet Available, …"` | `Available\b` fires inside "BB/HS Internet **Available**" | Residential, Rental |
| BB-4 | Tenant Pays | `tenant_pays` | `"Electri"` | `"Electricity, …"` | `City\b` fires inside "Electri**city**" | Rental, Commercial Lease |
| BB-5 | City | `city` | `"Dade"` | `"Dade City"` | `City\b` fires inside "Dade **City**" | All forms — multi-word city names |
| BB-6 | Association Name | `association_name` | `"Sunridge"` | `"Sunridge HOA"` | `HOA\b` fires inside "Sunridge **HOA**" | Residential and any form with HOA-suffix names |
| BB-7 | Association Name | `association_name` | `"Executive Commerce Park"` | `"Executive Commerce Park Association"` | `Association\b` fires inside the value | Commercial Lease and any form with Association-suffix names |
| BB-8 | Flood Zone Panel | `flood_zone_panel` | `"12057C0215G\nFlood Insurance Re…"` | `"12057C0215G"` | `\s` in char class `[A-Za-z0-9\s\-]` permits `\n`; "Flood Insurance" not in stop pattern | Residential, Rental |

**Pipeline impact of boundary-bleed failures:**

The incorrect value passes through all downstream stages. Field Map resolves the property name correctly. Apply Selected writes the truncated value to the Livewire property. Save Draft persists the wrong value to the DB. This means:
- `has_existing_value = true` on next import attempt (wrong value is "truthy") → Description row unchecked by default
- Reload restores the wrong value (Seller) or throws TypeError (Landlord P0-5)

**Fix target (all 8):** In `MlsListingImportService::parseFields()`, remove the offending stop words from the `$labelStop` compound pattern, or replace the `\b` word boundary with a `\b\s*:` label-anchor form that requires a colon immediately after the boundary.

---

## Part 4 — Additional Parser Label-Variant Gaps

### P1-1: Security Deposit — Missing 2-Word Label Variant

**Canonical key:** `minimum_security_deposit` → Landlord: `security_deposit_amount`
**Affected roles:** Landlord (Residential Rental, Commercial Lease)

**Gap:** Parser only matches `Minimum Security Deposit:` (3-word). Stellar MLS Rental form also emits bare `Security Deposit: $2,000`. The `$labelStop` correctly includes `Security\s+Deposit\b` (preventing other fields from bleeding into it), but the parser branch never matches the 2-word form.

**Fix target:** `/(?:Minimum\s+)?Security\s+Deposit[\s:]+\$?([\d,]+(?:\.\d{2})?)/i`

---

### P1-2: HOA Association Fee — Missing "HOA Dues:" Label Variant

**Canonical key:** `association_fee_amount` → Seller/Landlord: `association_fee_amount`
**Affected roles:** Seller, Landlord (all property types with HOA)

**Gap:** Parser handles `Association Fee:` and `HOA Fee:` but not `HOA Dues:`, which is a common Stellar MLS export label (e.g., `HOA Dues: $150/month`).

**Fix target:** Add `HOA\s+Dues[\s:\$]+([0-9,\.]+)` alternation.

---

### P1-3: Parcel / Tax ID — Missing Florida "Folio Number:" Variant

**Canonical key:** `tax_id` → Seller/Landlord: `parcel_id`
**Affected roles:** Seller, Landlord (all property types)

**Gap:** Parser handles `Tax ID:` and `Parcel (ID|Number):`. Florida county MLS exports (common in Stellar MLS territory) use `Folio Number:` or `Folio #:` as the parcel identifier.

**Fix target:** Add `Folio\s+(?:Number|#)` alternation to the existing pattern.

---

### P2-1: Terms of Lease — Missing "Lease Terms:" Variant

**Canonical key:** `terms_of_lease` → Landlord: `*terms_of_lease`
**Gap:** Parser only matches `Terms of Lease:`. Some commercial exports use `Lease Terms:` (reversed word order).
**Fix target:** `/(?:Terms\s+of\s+Lease|Lease\s+Terms)[\s:]+([^\|\n]{1,200})/i`

---

### P2-2: Zoning — Tight Character Class Truncates Descriptive Codes

**Canonical key:** `zoning` → Seller/Landlord: `zoning`
**Gap:** `[A-Za-z0-9\-\/]{1,30}` excludes spaces. Zoning codes like `R-1 Single Family` captured as just `R-1`. Short codes (A, RSF-4) work correctly.
**Fix target:** Expand to `[^\|\n]{1,50}` with `boundary=true`.

---

### P2-3: Flood Zone Date — Missing Text-Format Date Variants

**Canonical key:** `flood_zone_date` → Seller/Landlord: `flood_zone_date`
**Gap:** Parser only matches numeric date formats. Text-format dates like `January 15, 2020` are not captured.
**Fix target:** Add alternation for `[A-Za-z]+\.?\s+\d{1,2},?\s*\d{4}`.

---

### P2-4: water_frontage / waterfront_feet — Parser Comment vs. Field Map Discrepancy

**Canonical keys:** `water_frontage`, `waterfront_feet`
**Gap (documentation only):** Parser source (lines 694–699, 706–708) comments "No Livewire property exists on Seller/Landlord for this field yet." However, `MlsFieldMap::seller()` and `::landlord()` both map these keys to `water_frontage` and `waterfront_feet`. The `MlsCoverageReporter` resolves this at runtime via `property_exists()`.
**Fix target:** After verifying property existence via live `MlsCoverageReporter` output, remove the stale comment or remove dead field map entries.

---

## Part 5 — Field Coverage Matrix by Form

### Legend

| Rating | Meaning |
|--------|---------|
| **Live✅** | Confirmed PASS by live validation audit |
| **CI✅** | Confirmed intact by code inspection only |
| **❌ P0-N** | Confirmed failure — category reference |
| **❌ BB-N** | Parser boundary-bleed failure — live confirmed |
| **❌ TypeError** | Landlord loadDraft() TypeError (P0-5) — ALL Landlord fields at Stage 7 |
| **P1-N / P2-N** | Label-variant gap — high/low risk |
| **EXCL** | Intentionally excluded (documented in Appendix B) |
| **PREV** | Parsed but preview-only; no app destination |

> **Landlord Stage 7 note:** ALL Landlord fields have `❌ TypeError` at Stage 7 (Reload) due to P0-5. This is noted per field in Form 2 (Rental) and Form 6 (Commercial Lease) only to avoid repetition in shared-field forms.

---

### Form 1: Residential (Seller)

| MLS Field Label | Canonical Key | Seller Prop | Parser | Apply | Reload | Notes |
|-----------------|---------------|-------------|--------|-------|--------|-------|
| Address | `address` | `address` | Live✅ | Live✅ | Live✅ | |
| City (single-word) | `city` | `property_city` | Live✅ | Live✅ | Live✅ | |
| City (multi-word) | `city` | `property_city` | ❌ BB-5 | ❌ writes truncated | ❌ wrong value | "Dade City" → "Dade" |
| State, Zip, County | `state`, `zip`, `county` | respective | Live✅ | Live✅ | Live✅ | |
| List Price | `price` | `maximum_budget` | Live✅ | Live✅ | Live✅ | |
| Bedrooms | `bedrooms` | `bedrooms` | Live✅ | Live✅ | Live✅ | |
| Bathrooms | `bathrooms` | `bathrooms` | Live✅ | Live✅ | Live✅ | |
| Heated Sq Ft | `heated_sqft` | `minimum_heated_square` | Live✅ | Live✅ | Live✅ | |
| Sq Ft Source | `sqft_heated_source` | `sqft_heated_source` | Live✅ | Live✅ | Live✅ | |
| Year Built | `year_built` | `year_built` | Live✅ | Live✅ | Live✅ | |
| Pool | `pool` | `pool_needed` | Live✅ | Live✅ | Live✅ | Normalizer applied |
| Garage | `garage` | `garage_needed` | Live✅ | Live✅ | Live✅ | |
| Carport | `carport` | `carport_needed` | Live✅ | Live✅ | Live✅ | |
| Furnished | `furnished` | `building_features` | ❌ BB-1 | ❌ writes `"Un"` | ❌ wrong | Seller: merge into building_features |
| Lot Dimensions | `lot_dimensions` | `lot_dimensions` | Live✅ | Live✅ | Live✅ | |
| Lot Acreage | `lot_size_acres` | `total_acreage` | Live✅ | Live✅ | Live✅ | |
| Lot Size Sq Ft | `lot_size_sqft` | — | CI✅ parsed | EXCL | EXCL | No seller prop for sqft lot |
| Zoning | `zoning` | `zoning` | Live✅ | Live✅ | Live✅ | **P2-2**: truncates multi-word codes |
| A/C | `air_conditioning` | `*air_conditioning` | Live✅ | Live✅ | Live✅ | Comma-split ✅ |
| Heating & Fuel | `heating_fuel` | `*heating_and_fuel` | Live✅ | Live✅ | Live✅ | |
| Interior Features | `interior_features` | `*interior_features` | Live✅ | Live✅ | Live✅ | |
| Appliances | `appliances` | `*appliances` | Live✅ | Live✅ | Live✅ | |
| Roof Type | `roof_type` | `*roof_type` | Live✅ | Live✅ | Live✅ | |
| Exterior Construction | `exterior_construction` | `*exterior_construction` | Live✅ | Live✅ | Live✅ | |
| Foundation | `foundation` | `*foundation` | Live✅ | Live✅ | Live✅ | |
| Water (source) | `water` | `*water` | Live✅ | Live✅ | Live✅ | Requires colon guard |
| Sewer | `sewer` | `*sewer` | ❌ BB-2 | ❌ writes `"Public"` | ❌ wrong | "Public Sewer" → "Public" |
| Utilities | `utilities` | `*utilities` | ❌ BB-3 | ❌ writes truncated | ❌ wrong | "BB/HS Internet Available" truncated |
| Waterfront Y/N | `waterfront` | `waterfront` | Live✅ | Live✅ | Live✅ | |
| Water Access | `water_access` | `*water_access` | Live✅ | Live✅ | Live✅ | |
| Water View | `water_view` | `*water_view` | Live✅ | Live✅ | Live✅ | |
| Water Frontage | `water_frontage` | `water_frontage` | CI✅ | CI✅ | CI✅ | **P2-4**: stale parser comment vs. field map |
| Waterfront Feet | `waterfront_feet` | `waterfront_feet` | CI✅ | CI✅ | CI✅ | **P2-4** |
| Tax / Parcel ID | `tax_id` | `parcel_id` | Live✅ | Live✅ | Live✅ | **P1-3**: missing Folio label |
| Tax Year | `tax_year` | `tax_year` | Live✅ | Live✅ | Live✅ | |
| Annual Taxes | `annual_taxes` | `annual_property_taxes` | ❌ P0-2 | CI✅ | Live✅ | Fixture label worked; real label variants fail |
| Legal Description | `legal_description` | `legal_description` | ❌ P0-3 | CI✅ | Live✅ | Fixture label worked; Title Case values + no /i |
| Additional Parcels | `additional_parcels` | `additional_parcels` | Live✅ | Live✅ | Live✅ | |
| Total Parcel Count | `total_parcel_count` | `total_parcel_count` | Live✅ | Live✅ | Live✅ | |
| Flood Zone Code | `flood_zone_code` | `flood_zone_code` | ❌ P0-4 | CI✅ | Live✅ | 2-word "Flood Zone:" form fails |
| Flood Zone Date | `flood_zone_date` | `flood_zone_date` | Live✅ | Live✅ | Live✅ | **P2-3**: text-format dates not captured |
| Flood Zone Panel | `flood_zone_panel` | `flood_zone_panel` | ❌ BB-8 | ❌ polluted | ❌ wrong | `\s` in char class allows `\n` bleed |
| Flood Insurance Reqd | `flood_insurance_required` | `flood_insurance_required` | Live✅ | Live✅ | Live✅ | |
| HOA Y/N | `has_hoa` | `has_hoa` | Live✅ | Live✅ | Live✅ | |
| Association Name | `association_name` | `association_name` | ❌ BB-6 | ❌ writes truncated | ❌ wrong | "Sunridge HOA" → "Sunridge" |
| Association Fee | `association_fee_amount` | `association_fee_amount` | Live✅ | Live✅ | Live✅ | **P1-2**: missing "HOA Dues:" label |
| Association Fee Freq | `association_fee_frequency` | `association_fee_frequency` | Live✅ | Live✅ | Live✅ | |
| CDD Y/N | `has_cdd` | `has_cdd` | Live✅ | Live✅ | Live✅ | |
| Special Assess Y/N | `has_special_assessments` | `has_special_assessments` | Live✅ | Live✅ | Live✅ | |
| Public Remarks | `description` | `additional_details` | ❌ P0-1 | Live✅ | Live✅ | Parser failure for some label forms; apply/reload both PASS for Seller |
| MLS # | `mls_number` | — | CI✅ parsed | EXCL | EXCL | Intentional; no Livewire property |
| Directions | `directions` | — | CI✅ parsed | EXCL | EXCL | Intentional; no app purpose |

---

### Form 2: Rental (Landlord)

> **Landlord Stage 7 (Reload): ❌ TypeError for ALL fields** — P0-5. Each field row below omits the Stage 7 status; assume ❌ TypeError for all unless otherwise noted.

| MLS Field Label | Canonical Key | Landlord Prop | Parser | Apply | Notes |
|-----------------|---------------|---------------|--------|-------|-------|
| Address (5 fields) | `address`–`county` | respective | Live✅ (single-word city) / ❌ BB-5 (multi-word) | Live✅ / ❌ | |
| Monthly Rent | `price` | `desired_rental_amount` | Live✅ | Live✅ | |
| Bedrooms, Bathrooms | `bedrooms`, `bathrooms` | same | Live✅ | Live✅ | |
| Heated Sq Ft, Sq Ft Source | `heated_sqft`, `sqft_heated_source` | same | Live✅ | Live✅ | |
| Year Built | `year_built` | `year_built` | Live✅ | Live✅ | |
| Pool, Garage, Carport | `pool`, `garage`, `carport` | same | Live✅ | Live✅ | |
| Furnished | `furnished` | `tenant_require` | ❌ BB-1 | ❌ writes `"Un"` | |
| Lot Dimensions | `lot_dimensions` | `lot_dimensions` | Live✅ | Live✅ | Wiring gap RESOLVED |
| Lot Acreage | `lot_size_acres` | `total_acreage` | Live✅ | Live✅ | |
| Zoning | `zoning` | `zoning` | Live✅ | Live✅ | **P2-2** applies |
| A/C | `air_conditioning` | `*air_conditioning` | Live✅ | Live✅ | |
| Heating Fuel | `heating_fuel` | `*heating_fuel` | Live✅ | Live✅ | Property name differs from Seller |
| Interior Features | `interior_features` | `*interior_features` | Live✅ | Live✅ | |
| Appliances | `appliances` | `*appliances` | Live✅ | Live✅ | |
| Roof Type | `roof_type` | `*roof_type` | Live✅ | Live✅ | Wiring gap RESOLVED |
| Exterior Construction | `exterior_construction` | `*exterior_construction` | Live✅ | Live✅ | Wiring gap RESOLVED |
| Foundation | `foundation` | `*foundation` | Live✅ | Live✅ | Wiring gap RESOLVED |
| Water (source) | `water` | `*water` | Live✅ | Live✅ | |
| Sewer | `sewer` | `*sewer` | ❌ BB-2 | ❌ writes `"Public"` | |
| Utilities | `utilities` | `*property_utilities` | ❌ BB-3 | ❌ truncated | Landlord targets `property_utilities` (array), not `utilities` |
| Waterfront, Water Access, Water View | same | same | Live✅ | Live✅ | |
| Water Frontage, Waterfront Feet | same | same | CI✅ | CI✅ | P2-4 |
| Tax / Parcel ID | `tax_id` | `parcel_id` | Live✅ | Live✅ | **P1-3** |
| Tax Year | `tax_year` | `tax_year` | Live✅ | Live✅ | |
| Annual Taxes | `annual_taxes` | `annual_property_taxes` | ❌ P0-2 | CI✅ | |
| Legal Description | `legal_description` | `legal_description` | ❌ P0-3 | CI✅ | |
| Additional Parcels, Total Parcel Count | same | same | Live✅ | Live✅ | |
| Flood Zone Code | `flood_zone_code` | `flood_zone_code` | ❌ P0-4 | CI✅ | |
| Flood Zone Date | `flood_zone_date` | `flood_zone_date` | Live✅ | Live✅ | **P2-3** |
| Flood Zone Panel | `flood_zone_panel` | `flood_zone_panel` | ❌ BB-8 | ❌ polluted | |
| Flood Insurance Reqd | `flood_insurance_required` | `flood_insurance_required` | Live✅ | Live✅ | |
| HOA Y/N | `has_hoa` | `has_hoa` | Live✅ | Live✅ | |
| Association Name | `association_name` | `association_name` | ❌ BB-6 | ❌ truncated | |
| Association Fee | `association_fee_amount` | `association_fee_amount` | Live✅ | Live✅ | **P1-2** |
| Association Fee Freq | `association_fee_frequency` | `association_fee_frequency` | Live✅ | Live✅ | |
| CDD Y/N, Special Assess Y/N | same | same | Live✅ | Live✅ | |
| Available Date | `available_date` | `available_date` | Live✅ | Live✅ | |
| Min. Security Deposit | `minimum_security_deposit` | `security_deposit_amount` | Live✅ | Live✅ | **P1-1**: missing bare 2-word label |
| Lease Amount Frequency | `lease_amount_frequency` | `lease_amount_frequency` | Live✅ | Live✅ | |
| Terms of Lease | `terms_of_lease` | `*terms_of_lease` | Live✅ | Live✅ | **P2-1**: missing "Lease Terms:" |
| Rent Includes | `rent_includes` | `*rent_includes` | Live✅ | Live✅ | |
| Tenant Pays | `tenant_pays` | `*tenant_pays` | ❌ BB-4 | ❌ writes `"Electri"` | |
| Application Fee | `application_fee` | — | CI✅ parsed | EXCL | No landlord property |
| Pets Allowed | `pets_allowed` | `pet_policy` | Live✅ | Live✅ | |
| Minimum Lease (Months) | `minimum_lease_months` | `min_lease_period` | Live✅ | Live✅ | Parenthesized label alternation ✅ |
| Public Remarks | `description` | `additional_details` | ❌ P0-1 | Live✅ | Apply=✅; Reload=❌ TypeError (P0-5) |

---

### Forms 3–7: Summary (Shared Fields Only)

All five remaining forms (Vacant Land, Residential Income, Commercial Sale, Commercial Lease, Business Opportunity) share the same Tax/Legal, Flood Zone, HOA/CDD, and Public Remarks fields. Their status mirrors Forms 1–2 for those shared fields.

For completeness, confirmed per-form results:

| Form | Unique Fields | Parser Status | Notes |
|------|--------------|---------------|-------|
| Vacant Land | Zoning (P2-2), Address | City=❌ BB-5 for multi-word; all others ✅ | No residential structural fields |
| Income (Multi-Family) | `number_of_units`, `gross_annual_income`, `annual_operating_expenses`, `cap_rate` | All Live✅ | Zero parser failures in this form |
| Commercial Sale | `building_size_sqft`, `ceiling_height_ft`, `parking_spaces_count`, `building_features_list`, `current_use_list`, `net_operating_income`, `cap_rate` | All Live✅ | Association Name: ❌ BB-7 if name ends in "Association" |
| Commercial Lease | `lease_rate_type`, `minimum_lease_months`, `office_area_sqft`, `tenant_pays` | Tenant Pays=❌ BB-4; Association Name=❌ BB-7; others Live✅ | Landlord reload: ❌ TypeError (P0-5) |
| Business Opportunity | `business_type`, `annual_revenue`, `annual_net_income_business`, `employee_count` | All Live✅ | `inventory_included`, `seller_financing_yn`, `business_lease_type`: EXCL |

---

## Part 6 — Previously-Fixed Regression Verification

All 10 previously-fixed regressions are confirmed still passing by static inspection and the live validation audit.

| Regression | Fix Mechanism | Verification |
|------------|---------------|--------------|
| Carport bleed into Appliances | `boundary=true`; `Carport\b` in `$labelStop` | Live✅ |
| Interior Features bleed | `boundary=true`; `Interior\s+Features?` in `$labelStop` | Live✅ |
| Appliances bleed | `boundary=true`; both label forms in `$labelStop` | Live✅ |
| Water Frontage Y/N vs. text | Y/N variant parsed first; free-text has `(?!\s+Y\/N)` guard | Live✅ |
| Waterfront Feet zero-value loss | `!== null` guard (not falsy) | CI✅ |
| Water View bleed (Landlord) | `boundary=true` | Live✅ |
| Water Access bleed (Landlord) | `boundary=true` | Live✅ |
| Interior Features bleed (Landlord) | `boundary=true` | Live✅ |
| Appliances bleed (Landlord) | `boundary=true` | Live✅ |
| rent_includes boundary bleed | `boundary=true` | Live✅ |

---

## Part 7 — Normalizer Audit

No new normalizer failures were found. All 5 `MlsNormalizer` branches pass the 14 live test cases from the validation audit.

| Field | Normalizer | Status |
|-------|-----------|--------|
| Boolean fields (pool, garage, carport, waterfront, has_hoa, has_cdd, additional_parcels, flood_insurance_required) | `normalizeBoolean()` | Live✅ — handles Yes/No/Y/N/TRUE/FALSE; strips "Y/N:" prefix |
| Furnished | `normalizeFurnishing()` | Live✅ — handles all 5 Stellar MLS Furnishings options |
| Lease Amount Frequency | `normalizeLeaseFrequency()` | Live✅ |
| Association Fee Frequency | `normalizeHoaFeeFrequency()` | Live✅ |
| Lease Rate Type | `normalizeLeaseRateType()` | Live✅ — handles NNN/Gross/Modified Gross/Absolute NNN/Double Net/Net |
| Cap Rate | `normalizeCapRate()` | Live✅ — strips trailing % |
| Net Operating Income | `normalizeNetOperatingIncome()` | Live✅ — strips $ and commas |
| Flood Zone Code | `normalizeFloodZone()` | Live✅ — uppercases zone code; edge case: if value contains "flood insurance" text returns "yes" (extremely unlikely in practice) |
| Multi-select arrays | Pass-through | Live✅ — comma-split handled by `applyImportedFields()` for `*`-prefixed props |

---

## Part 8 — Prioritized Remediation Roadmap

### Priority Classification

**All 8 boundary-bleed bugs (BB-1 through BB-8) are P0** — they write actively wrong values to the form. They should be treated as production bugs, not cosmetic issues.

| Priority | ID | Finding | Stage | Fix Target |
|----------|----|---------|-------|------------|
| **P0** | P0-5 | Landlord loadDraft() TypeError — ALL Landlord fields fail to reload | Stage 7 | Remove redundant `json_decode()` calls from `LandlordOfferListing::loadDraft()` |
| **P0** | BB-1 | Furnished "Unfurnished" → "Un" | Stage 1 | Remove `Furnished\b` from $labelStop or use colon-anchor form |
| **P0** | BB-2 | Sewer "Public Sewer" → "Public" | Stage 1 | Remove/fix `Sewer\b` stop |
| **P0** | BB-3 | Utilities truncated at "Available" | Stage 1 | Remove/fix `Available\b` stop |
| **P0** | BB-4 | Tenant Pays "Electricity" → "Electri" | Stage 1 | Remove/fix `City\b` stop |
| **P0** | BB-5 | City multi-word names truncated | Stage 1 | Remove/fix `City\b` stop |
| **P0** | BB-6 | Association Name truncated at "HOA" | Stage 1 | Remove/fix `HOA\b` stop |
| **P0** | BB-7 | Association Name truncated at "Association" | Stage 1 | Remove/fix `Association\b` stop |
| **P0** | BB-8 | Flood Zone Panel polluted by newline bleed | Stage 1 | Change `\s` in char class to `[ \t]` or use explicit char list |
| **P0** | P0-1 | Public Remarks dead `\s{2,}` terminator | Stage 1 | Replace lookahead with `$labelStop` closure |
| **P0** | P0-2 | Annual Taxes missing label variants | Stage 1 | Add 5 label alternations |
| **P0** | P0-3 | Legal Description dead terminator + Title Case firing | Stage 1 | Replace lookahead with `$labelStop`; add `/i` |
| **P0** | P0-4 | Flood Zone Code 2-word label not matched | Stage 1 | Add `Flood Zone:` alternation |
| **P1** | P1-1 | Security Deposit missing 2-word label | Stage 1 | Add alternation |
| **P1** | P1-2 | HOA Dues label missing | Stage 1 | Add alternation |
| **P1** | P1-3 | Folio Number label missing | Stage 1 | Add alternation |
| **P2** | P2-1 | Lease Terms reversed word order | Stage 1 | Add alternation |
| **P2** | P2-2 | Zoning char class truncates multi-word codes | Stage 1 | Expand char class |
| **P2** | P2-3 | Flood Zone Date text-format dates | Stage 1 | Add text-date alternation |
| **P2** | P2-4 | water_frontage/waterfront_feet stale parser comment | Docs | Verify runtime; update comment or field map |

### Recommended Fix Order

**Phase 1 — Highest impact, single file:**
1. Fix P0-5: LandlordOfferListing::loadDraft() double-decode (removes TypeError for ALL Landlord fields)
2. Fix BB-1 through BB-8: $labelStop boundary misfires in parseFields() (8 parser patches)
3. Fix P0-1 through P0-4: Label-variant parser patches (4 parser patches)

**Phase 2 — P1 label additions:**
- Fix P1-1, P1-2, P1-3 in a single parser update

**Phase 3 — P2 cleanup:**
- Fix P2-1, P2-2, P2-3 in a single parser update
- Resolve P2-4 documentation discrepancy

---

## Appendix A — Full Pipeline Stage Table for All P0 Failures (Parser-Label Failures)

> These are the four label-variant parser failures (P0-1 through P0-4). P0-5 (Landlord loadDraft) and BB-1 through BB-8 (boundary bleed) are covered in Parts 2 and 3 above.

| Stage | P0-1 Description | P0-2 Annual Taxes | P0-3 Legal Desc | P0-4 Flood Zone Code |
|-------|-----------------|-------------------|-----------------|---------------------|
| 1 — Parser | ❌ dead terminator | ❌ missing label forms | ❌ dead terminator + Title Case | ❌ 2-word label not matched |
| 2 — Normalizer | N/A (not reached) | N/A | N/A | N/A |
| 3 — Field Map (Seller) | CI✅ | CI✅ | CI✅ | CI✅ |
| 3 — Field Map (Landlord) | CI✅ | CI✅ | CI✅ | CI✅ |
| 4 — Preview Modal | Live✅ (when parser succeeds) | Live✅ (fixture label works) | Live✅ (fixture label works) | Live✅ (3-word label works) |
| 5 — Apply Selected | Live✅ | Live✅ | Live✅ | Live✅ |
| 6 — Save Draft | Live✅ | Live✅ | Live✅ | Live✅ |
| 7 — Reload (Seller) | Live✅ | Live✅ | Live✅ | Live✅ |
| 7 — Reload (Landlord) | ❌ TypeError (P0-5) | ❌ TypeError (P0-5) | ❌ TypeError (P0-5) | ❌ TypeError (P0-5) |

---

## Appendix B — Intentionally Excluded Fields (All Roles)

| Canonical Key | Reason |
|---------------|--------|
| `mls_number` | No Livewire property named `mls_number` exists on any component |
| `directions` | No Livewire property accepts directions; field has no listing purpose |
| `lot_size_sqft` | Both Seller and Landlord use `total_acreage` (acres); no sqft lot size property exists |
| `inventory_included` | Seller `inventory_value` is a dollar field, not a boolean; no matching boolean prop |
| `seller_financing_yn` | Seller `offered_financing` is a multi-select array, not a boolean Y/N |
| `business_lease_type` | No matching Livewire property on SellerOfferListing |
| `application_fee` | Property does not exist on LandlordOfferListing |
| `price` (Tenant role) | MLS listing price is the landlord's asking rent, not a tenant's desired amount |
| `listing_type_hint` | Internal signal key; stripped from user-facing data before export |
| `rental_rate_type` | Internal signal only; unset before returning from `parseFields()` |
| `net_operating_income_raw` | Preview-only; Income form only; not mapped to a Livewire property |
| `unit_types_raw`, `occupancy_rate_raw` | Preview-only; not mapped |
| `heating` | Parser fieldLabel alias only; importer always emits `heating_fuel`; `heating` key never emitted |

---

## Appendix C — Full Pipeline Stage Matrix

This matrix covers all Seller and Landlord fields through all 8 pipeline stages. Stages 1–7 are verified; Stage 8 (Public View) shows confirmed fields from `resources/views/offer-listing/seller/view.blade.php`; Ask AI coverage is noted where known from the LISTING_KEY_KEYWORD_MAP in `AskAiRunnerV2Service.php`.

### Status codes

| Code | Meaning |
|------|---------|
| ✅ Live | Confirmed PASS by live validation audit |
| ✅ CI | Confirmed intact by code inspection |
| ❌ [type] | Confirmed failure — type abbreviated |
| ⊘ | Not reached (upstream failure) |
| N/A | Not applicable to this role/stage |
| ? | Not yet audited for this column |

### Section C.1 — Universal Fields (Seller + Landlord, all property types)

| Field | Canonical Key | Parser | Normalizer | Field Map S | Field Map L | Preview S | Preview L | Apply S | Apply L | Save S | Save L | Reload S | Reload L | Public View S | Ask AI |
|-------|---------------|--------|-----------|-------------|-------------|-----------|-----------|---------|---------|--------|--------|----------|----------|--------------|--------|
| Address | `address` | ✅ Live | — | ✅ CI | ✅ CI | ✅ Live | ✅ Live | ✅ Live | ✅ Live | ✅ Live | ✅ Live | ✅ Live | ❌ TypeError | ✅ CI | ? |
| City (single-word) | `city` | ✅ Live | — | ✅ CI | ✅ CI | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ✅ CI | ? |
| City (multi-word) | `city` | ❌ BB-5 | ⊘ | ✅ | ✅ | ✅ (wrong) | ✅ (wrong) | ❌ wrong | ❌ wrong | ⊘ | ⊘ | ⊘ | ❌ TypeError | — | — |
| State | `state` | ✅ Live | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ✅ CI | ? |
| Zip | `zip` | ✅ Live | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ✅ CI | ? |
| County | `county` | ✅ Live | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ✅ CI | ? |
| Bedrooms | `bedrooms` | ✅ Live | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ✅ CI | ✅ CI |
| Bathrooms | `bathrooms` | ✅ Live | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ✅ CI | ? |
| Heated Sq Ft | `heated_sqft` | ✅ Live | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ✅ CI | ? |
| Pool | `pool` | ✅ Live | ✅ bool | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ✅ CI | ? |
| Garage | `garage` | ✅ Live | ✅ bool | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ? | ? |
| Carport | `carport` | ✅ Live | ✅ bool | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ? | ? |
| Furnished | `furnished` | ❌ BB-1 | ⊘ | ✅ | ✅ | ✅ (wrong) | ✅ (wrong) | ❌ wrong | ❌ wrong | ⊘ | ⊘ | ⊘ | ❌ TypeError | ? | ? |
| Year Built | `year_built` | ✅ Live | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ✅ CI | ? |
| Lot Dimensions | `lot_dimensions` | ✅ Live | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ? | ? |
| Lot Acreage | `lot_size_acres` | ✅ Live | — | ✅→`total_acreage` | ✅→`total_acreage` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ? | ? |
| Zoning | `zoning` | ✅ (short codes) | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ✅ CI | ? |
| A/C | `air_conditioning` | ✅ Live | — | ✅→`*air_conditioning` | ✅→`*air_conditioning` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ? | ? |
| Heating & Fuel | `heating_fuel` | ✅ Live | — | ✅→`*heating_and_fuel` | ✅→`*heating_fuel` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ? | ? |
| Interior Features | `interior_features` | ✅ Live | — | ✅→`*interior_features` | ✅→`*interior_features` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ? | ? |
| Appliances | `appliances` | ✅ Live | — | ✅→`*appliances` | ✅→`*appliances` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ? | ? |
| Roof Type | `roof_type` | ✅ Live | — | ✅→`*roof_type` | ✅→`*roof_type` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ? | ? |
| Exterior Construction | `exterior_construction` | ✅ Live | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ? | ? |
| Foundation | `foundation` | ✅ Live | — | ✅→`*foundation` | ✅→`*foundation` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ? | ? |
| Water (source) | `water` | ✅ Live | — | ✅→`*water` | ✅→`*water` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ? | ? |
| Sewer | `sewer` | ❌ BB-2 | ⊘ | ✅ | ✅ | ✅ (wrong) | ✅ (wrong) | ❌ wrong | ❌ wrong | ⊘ | ⊘ | ⊘ | ❌ TypeError | ? | ? |
| Utilities | `utilities` | ❌ BB-3 | ⊘ | ✅→`*utilities` | ✅→`*property_utilities` | ✅ (wrong) | ✅ (wrong) | ❌ wrong | ❌ wrong | ⊘ | ⊘ | ⊘ | ❌ TypeError | ? | ? |
| Waterfront Y/N | `waterfront` | ✅ Live | ✅ bool | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ? | ? |
| Water Access | `water_access` | ✅ Live | — | ✅→`*water_access` | ✅→`*water_access` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ? | ? |
| Water View | `water_view` | ✅ Live | — | ✅→`*water_view` | ✅→`*water_view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ? | ? |
| Tax / Parcel ID | `tax_id` | ✅ Live | — | ✅→`parcel_id` | ✅→`parcel_id` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ✅ CI | ? |
| Tax Year | `tax_year` | ✅ Live | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ? | ? |
| Annual Taxes | `annual_taxes` | ❌ P0-2 | ⊘ | ✅→`annual_property_taxes` | ✅ | ✅ (fixture) | ✅ (fixture) | ✅ (fixture) | ✅ (fixture) | ✅ (fixture) | ✅ (fixture) | ✅ (fixture) | ❌ TypeError | ✅ CI | ✅ CI |
| Legal Description | `legal_description` | ❌ P0-3 | ⊘ | ✅ | ✅ | ✅ (fixture) | ✅ (fixture) | ✅ (fixture) | ✅ (fixture) | ✅ (fixture) | ✅ (fixture) | ✅ (fixture) | ❌ TypeError | ✅ CI | ✅ CI |
| Additional Parcels | `additional_parcels` | ✅ Live | ✅ bool | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ? | ? |
| Total Parcel Count | `total_parcel_count` | ✅ Live | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ? | ? |
| Flood Zone Code | `flood_zone_code` | ❌ P0-4 | ⊘ | ✅ | ✅ | ✅ (3-word) | ✅ (3-word) | ✅ (3-word) | ✅ (3-word) | ✅ | ✅ | ✅ | ❌ TypeError | ✅ CI | ? |
| Flood Zone Date | `flood_zone_date` | ✅ Live | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ? | ? |
| Flood Zone Panel | `flood_zone_panel` | ❌ BB-8 | ⊘ | ✅ | ✅ | ✅ (wrong) | ✅ (wrong) | ❌ wrong | ❌ wrong | ⊘ | ⊘ | ⊘ | ❌ TypeError | ? | ? |
| Flood Insurance Reqd | `flood_insurance_required` | ✅ Live | ✅ bool | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ? | ? |
| HOA Y/N | `has_hoa` | ✅ Live | ✅ bool | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ? | ? |
| Association Name | `association_name` | ❌ BB-6 | ⊘ | ✅ | ✅ | ✅ (wrong) | ✅ (wrong) | ❌ wrong | ❌ wrong | ⊘ | ⊘ | ⊘ | ❌ TypeError | ? | ? |
| Association Fee | `association_fee_amount` | ✅ Live | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ? | ? |
| Association Fee Freq | `association_fee_frequency` | ✅ Live | ✅ freq | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ? | ? |
| CDD Y/N | `has_cdd` | ✅ Live | ✅ bool | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ? | ? |
| Has Special Assessments | `has_special_assessments` | ✅ Live | ✅ bool | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ TypeError | ? | ? |
| Public Remarks | `description` | ❌ P0-1 | — | ✅→`additional_details` | ✅→`additional_details` | ✅ Live | ✅ Live | ✅ Live | ✅ Live | ✅ Live | ✅ Live | ✅ Live | ❌ TypeError | ✅ CI | ? |

> "`Annual Taxes`, `Legal Description`, `Flood Zone Code` (fixture)" means: the live audit used fixture text with a label form that the parser DOES match. The ❌ P0-N rating reflects OTHER label forms that real MLS exports may use (not in the fixture) which the parser misses.

### Section C.2 — Landlord-Only Fields

| Field | Canonical Key | Parser | Field Map L | Apply L | Save L | Reload L |
|-------|---------------|--------|-------------|---------|--------|----------|
| Monthly Rent | `price` | ✅ Live | ✅→`desired_rental_amount` | ✅ Live | ✅ Live | ❌ TypeError |
| Available Date | `available_date` | ✅ Live | ✅ | ✅ Live | ✅ Live | ❌ TypeError |
| Min. Security Deposit | `minimum_security_deposit` | ✅ Live | ✅→`security_deposit_amount` | ✅ Live | ✅ Live | ❌ TypeError |
| Lease Amount Frequency | `lease_amount_frequency` | ✅ Live | ✅ | ✅ Live | ✅ Live | ❌ TypeError |
| Terms of Lease | `terms_of_lease` | ✅ Live | ✅→`*terms_of_lease` | ✅ Live (comma-split) | ✅ Live | ❌ TypeError |
| Rent Includes | `rent_includes` | ✅ Live | ✅→`*rent_includes` | ✅ Live | ✅ Live | ❌ TypeError |
| Tenant Pays | `tenant_pays` | ❌ BB-4 | ✅→`*tenant_pays` | ❌ writes `"Electri"` | ⊘ | ❌ TypeError |
| Application Fee | `application_fee` | ✅ CI | EXCL | N/A | N/A | N/A |
| Pets Allowed | `pets_allowed` | ✅ Live | ✅→`pet_policy` | ✅ Live | ✅ Live | ❌ TypeError |
| Min. Lease (Months) | `minimum_lease_months` | ✅ Live | ✅→`min_lease_period` | ✅ Live | ✅ Live | ❌ TypeError |
| Lease Rate Type | `lease_rate_type` | ✅ Live | ✅→`commercial_lease_type` | ✅ Live | ✅ Live | ❌ TypeError |
| Office Area Sq Ft | `office_area_sqft` | ✅ CI | ✅→`office_retail_sqft` | ✅ CI | ✅ CI | ❌ TypeError |

### Section C.3 — Seller-Only Fields

| Field | Canonical Key | Parser | Field Map S | Apply S | Save S | Reload S |
|-------|---------------|--------|-------------|---------|--------|----------|
| List Price | `price` | ✅ Live | ✅→`maximum_budget` | ✅ Live | ✅ Live | ✅ Live |
| Number of Units | `number_of_units` | ✅ Live | ✅→`unit_number` | ✅ Live | ✅ Live | ✅ Live |
| Gross Annual Income | `gross_annual_income` | ✅ Live | ✅ | ✅ Live | ✅ Live | ✅ Live |
| Annual Operating Expenses | `annual_operating_expenses` | ✅ Live | ✅ | ✅ Live | ✅ Live | ✅ Live |
| Cap Rate | `cap_rate` | ✅ Live | ✅→`minimum_cap_rate` | ✅ Live | ✅ Live | ✅ Live |
| NOI (Income form) | `net_operating_income_raw` | ✅ CI | PREV (null) | N/A | N/A | N/A |
| NOI (Commercial Sale) | `net_operating_income` | ✅ Live | ✅→`minimum_annual_net_income` | ✅ Live | ✅ Live | ✅ Live |
| Building Size Sq Ft | `building_size_sqft` | ✅ Live | ✅→`total_square_feet` | ✅ Live | ✅ Live | ✅ Live |
| Ceiling Height Ft | `ceiling_height_ft` | ✅ Live | ✅→`ceiling_height` | ✅ Live | ✅ Live | ✅ Live |
| Parking Spaces | `parking_spaces_count` | ✅ Live | ✅→`garage_parking_spaces` | ✅ Live | ✅ Live | ✅ Live |
| Building Features | `building_features_list` | ✅ Live | ✅→`*building_features` | ✅ Live | ✅ Live | ✅ Live |
| Current Use | `current_use_list` | ✅ Live | ✅→`*current_use` | ✅ Live | ✅ Live | ✅ Live |
| Business Type | `business_type` | ✅ Live | ✅ | ✅ Live | ✅ Live | ✅ Live |
| Annual Revenue | `annual_revenue` | ✅ Live | ✅ | ✅ Live | ✅ Live | ✅ Live |
| Annual Net Income (Biz) | `annual_net_income_business` | ✅ Live | ✅→`minimum_annual_net_income` | ✅ Live | ✅ Live | ✅ Live |
| Employee Count | `employee_count` | ✅ Live | ✅ | ✅ Live | ✅ Live | ✅ Live |
| Inventory Included Y/N | `inventory_included` | ✅ CI | EXCL | N/A | N/A | N/A |
| Seller Financing Y/N | `seller_financing_yn` | ✅ CI | EXCL | N/A | N/A | N/A |
| Business Lease Type | `business_lease_type` | ✅ CI | EXCL | N/A | N/A | N/A |

### Section C.4 — Intentionally Excluded / Preview-Only Fields

| Field | Canonical Key | Why Excluded |
|-------|---------------|-------------|
| MLS Number | `mls_number` | No Livewire property on any component |
| Directions | `directions` | No app destination; parsed then discarded |
| Lot Size Sq Ft | `lot_size_sqft` | No Livewire property (acreage used instead) |
| Unit Types (raw) | `unit_types_raw` | Preview-only; no structural parse |
| Occupancy Rate (raw) | `occupancy_rate_raw` | Preview-only |
| NOI (Income form raw) | `net_operating_income_raw` | Preview-only in Income form |
| Inventory Included Y/N | `inventory_included` | Seller has dollar field, not boolean |
| Seller Financing Y/N | `seller_financing_yn` | Seller has multi-select, not boolean |
| Business Lease Type | `business_lease_type` | No matching Livewire property |
| Application Fee | `application_fee` | No property on LandlordOfferListing |
| Heating (alias) | `heating` | Parser emits `heating_fuel`; `heating` key never emitted |

> **Ask AI column:** `?` entries require a targeted scan of `AskAiRunnerV2Service::LISTING_KEY_KEYWORD_MAP`. Known confirmed entries from the registry: `listing.bedrooms`, `listing.legal_description`, `listing.annual_property_taxes`. See `docs/audits/ASK_AI_FULL_FIELD_AND_FAQ_COVERAGE.md` for the comprehensive Ask AI field coverage audit.
