# MLS Import Field-Mapping Failure Report
**Roles audited**: Seller, Landlord  
**Date**: 2026-06-15  
**Task**: #2838  

This document traces each of the six failure categories end-to-end:  
`Parser → canonical key → MlsFieldMap → Livewire property → form select option value`

---

## Category 1 — Address Fields

### Original analysis (raw-text fixtures only)

| Parser key | MlsFieldMap (Seller) | MlsFieldMap (Landlord) | Livewire property | Status |
|---|---|---|---|---|
| `address` | `address` | `address` | `$address` | ✅ Field map correct |
| `city` | `property_city` | `property_city` | `$property_city` | ✅ Field map correct |
| `state` | `property_state` | `property_state` | `$property_state` | ✅ Field map correct |
| `zip` | `property_zip` | `property_zip` | `$property_zip` | ✅ Field map correct |
| `county` | `property_county` | `property_county` | `$property_county` | ✅ Field map correct |

**Original conclusion**: No mismatch found in field map or Livewire properties.

### Live URL audit findings (revised)

Live testing against the two Stellar MLS Matrix URLs revealed **two additional parser bugs** not visible from raw-text fixtures:

#### Bug 1 — County: no-space-after-colon format (BUG-06)

Stellar MLS Matrix summary lines concatenate fields with **no spaces**:
```
ActiveCounty:PinellasList Price:$345,000ADOM:136Beds:3...
```

The county regex was `/County\s*:[\s]+/i` — `[\s]+` required **at least one space** after the colon. With `County:Pinellas` (no space), the regex produced no match and county was blank.

**Fix applied**: changed `[\s]+` → `[\s]*` in the county regex. The existing `$boundary=true` flag stops the capture at the next label (`List Price:`), so `PinellasList Price:$345,000` correctly trims to `Pinellas`.

**Regression tests**: `test_bug_06_county_no_space_colon_stellar_mls_format`, `test_bug_06_county_no_space_does_not_bleed_into_list_price` (MlsGapFixesRegressionTest)

#### Bug 2 — Address/City/State/Zip: unlabeled "About" header (BUG-07)

Stellar MLS Matrix public shared pages do **not** include `Address:`, `City:`, `State:`, or `Zip:` labeled fields. The full address appears only in an unlabeled section header:
```
About 828 89TH AVENUE N, ST PETERSBURG, Florida 33702
Welcome to this beautifully updated...
```

The standard labeled parsers all returned `null`. Address, city, state, and zip were all blank after import from a live Stellar URL.

**Fix applied**: new parser block added after the standard labeled parsers — fires only when `address`/`city` is not yet set. Pattern: `/\bAbout\s+([^\n,]{5,100}),\s*([^\n,]{2,60}),\s*([A-Z]{2}|[A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)\s+(\d{5}(?:-\d{4})?)/i`. Full state names (`Florida`, `Texas`, etc.) are converted to 2-letter abbreviations via a static lookup table.

**Live verification**:
- Seller (828 89TH AVE N): `address=828 89TH AVENUE N`, `city=ST PETERSBURG`, `state=FL`, `zip=33702` ✅
- Landlord (8535 BLIND PASS DRIVE Unit#202): `address=8535 BLIND PASS DRIVE Unit#202`, `city=TREASURE ISLAND`, `state=FL`, `zip=33706` ✅

**Regression tests**: `test_bug_07_stellar_about_header_extracts_all_address_fields`, `test_bug_07_stellar_about_header_preserves_unit_number`, `test_bug_07_labeled_address_takes_precedence_over_about` (MlsGapFixesRegressionTest)

---

## Category 2 — Property Type

### Field map chain

| Role | MLS raw value | Parser canonical key | normalizePropertyTypeForRole() output | Form `<option value>` | Status |
|---|---|---|---|---|---|
| Seller | `"Residential Property"` | `property_type` | `"Residential"` | `"Residential"` | ✅ Correct |
| Seller | `"Commercial Property"` | `property_type` | `"Commercial"` | `"Commercial"` | ✅ Correct |
| Seller | `"Business Opportunity"` | `property_type` | `"Business"` | `"Business"` | ✅ Correct |
| Seller | `"Income/Multifamily"` | `property_type` | `"Income"` | `"Income"` | ✅ Correct |
| Seller | `"Vacant Land Sale"` | `property_type` | `"Vacant Land"` | `"Vacant Land"` | ✅ Correct |
| Seller | `"Single Family Residence"` | `property_type` | `"Residential"` | `"Residential"` | ✅ Correct |
| Landlord | `"Residential Property"` | `property_type` | `"Residential Property"` | `"Residential Property"` | ✅ Correct |
| Landlord | `"Commercial Property"` | `property_type` | `"Commercial Property"` | `"Commercial Property"` | ✅ Correct |
| Landlord | `"Residential"` (short-form) | `property_type` | `"Residential Property"` | `"Residential Property"` | ✅ Correct |

**Root cause**: Seller blade uses short-form option values (`"Residential"`, not `"Residential Property"`),  
while Landlord blade uses long-form (`"Residential Property"`). Without per-role normalization,  
Landlord received `"Residential"` from MLS which didn't match its `<option>`.  
**Fix applied**: `HasMlsImport::normalizePropertyTypeForRole()` — strips `" Property"` suffix for  
Seller/Buyer/Tenant; ensures `" Property"` suffix for Landlord (wired in prior task).  
**Regression tests**: 8 tests in `MlsGapFixesRegressionTest::test_property_type_*`

### Live URL finding — property_type NOT on Stellar MLS Matrix pages

Live audit of both Stellar MLS Matrix shared URLs confirmed that the public share page format **does not include a `Property Type:` labeled field** anywhere in the rendered HTML. The `property_type` canonical key will always be `null` / `(missing)` when importing from a Stellar Matrix public share URL.

**Action required**: Users must manually select Property Type after importing from a Stellar MLS URL. This is not a parser bug — the field is simply absent from the page.

---

## Category 3 — Description Bleed

**Symptom**: MLS pages prepend a property address/city/state/ZIP header block before the  
actual narrative in the Public Remarks field. Without a strip step, this header bleeds into  
the `description` / `additional_details` field on the form.

**Standard (all-caps) case** — already in place:
```
Public Remarks: 123 MAIN ST TAMPA FL 33601 Beautiful 3/2 pool home...
→ Beautiful 3/2 pool home...
```

### Live URL finding — Stellar MLS mixed-case full state name (BUG-08)

Stellar MLS Matrix's `About {HEADER}` pattern produces descriptions starting with a **mixed-case address header** using the full state name:
```
828 89TH AVENUE N, ST PETERSBURG, Florida 33702
Welcome to this beautifully updated...
```

The primary strip regex (`[^a-z]{0,250}?`) requires all-caps text before the state. "Florida" contains lowercase letters, so the regex did not fire — the address header bled into the description field.

**Fix applied**: fallback strip added after the primary strip. Pattern:
`/^(\d[^\n]{3,200}),\s*([A-Z][a-z]{2,}(?:\s+[A-Z][a-z]+)?)\s+(\d{5}(?:-\d{4})?(?:-\d+)?)[\s\n]+([\s\S]+)/su`  
Only fires when:
- The description starts with a digit (street number), AND
- The matched state token is in the 51-entry `$stripUsStateFullNames` list ("Florida", etc.), AND
- Non-empty prose remains after stripping.

**Live verification**:
- Seller: description → `Welcome to this beautifully updated 3-bedroom, 1-bath home...` ✅
- Landlord: description → `Coastal Living Just Steps from Sunset Beach!...` ✅

**Regression tests**: `test_bug_08_description_strip_stellar_mls_full_state_name`, `test_bug_08_description_strip_unit_address_full_state_name`, `test_bug_08_description_non_digit_start_not_stripped` (MlsGapFixesRegressionTest)

---

## Category 4 — Landlord Date Fields

| Parser canonical key | Was in MlsFieldMap::landlord()? | Livewire public property | Status |
|---|---|---|---|
| `available_date` | ✅ yes | `$available_date` (line 326) | ✅ Already wired |
| `lease_available_date` | ❌ missing | `$lease_available_date` (line 318) | 🔧 Fixed |

**Root cause**: The Landlord Livewire component has **two** date properties for availability:  
`$available_date` (EAV meta key `available_date`) and `$lease_available_date` (EAV meta key  
`lease_available_date`). Only `available_date` was in the field map. The parser also only  
emitted one canonical key (`available_date`), so `$lease_available_date` was never populated  
from an MLS import.

**Fix applied**:  
1. `MlsListingImportService` parser now emits **both** `available_date` and `lease_available_date`  
   from the same "Available:" source label.  
2. `MlsFieldMap::landlord()` now includes `'lease_available_date' => 'lease_available_date'`.

**Live verification**: Landlord URL → `available_date=06/11/2026`, `lease_available_date=06/11/2026` ✅

**Regression tests**:  
- `test_available_date_emits_lease_available_date` (MlsListingImportServiceTest)  
- `test_landlord_field_map_includes_lease_available_date` (MlsListingImportServiceTest)

---

## Category 5 — Dropdown Value Mismatches

### 5a — Pool / Garage / Carport (Seller + Landlord)

| Field | Form `<option value>` | Old normalizer output | New normalizer output | Status |
|---|---|---|---|---|
| `pool_needed` | `"Yes"` / `"No"` | `"yes"` / `"no"` (lowercase) | `"Yes"` / `"No"` (Title Case) | 🔧 Fixed |
| `garage_needed` | `"Yes"` / `"No"` | `"yes"` / `"no"` (lowercase) | `"Yes"` / `"No"` (Title Case) | 🔧 Fixed |
| `carport_needed` | `"Yes"` / `"No"` | `"yes"` / `"no"` (lowercase) | `"Yes"` / `"No"` (Title Case) | 🔧 Fixed |

**Special cases handled by new `normalizeFormYesNo()`**:  
- `"None"` → `"No"` (MLS "Carport: None" / "Pool: None" → form "No")  
- `"In Ground"` or other multi-word non-boolean values → passed through unchanged  
- Multi-value strings like `"Yes, Attached, 1 Spaces"` → `"Yes"` (extracts leading boolean)  

**Live verification**: Seller garage=`Yes`, carport=`No`, pool=`No` ✅; Landlord garage=`No`, carport=`No`, pool=`No` ✅

**Note**: `waterfront`, `has_hoa`, `has_cdd`, `additional_parcels`, etc. are storage fields  
(boolean columns), not form selects — they continue to use `normalizeBoolean()` which returns  
lowercase `"yes"`/`"no"`.

### 5b — Furnishing / tenant_require (Landlord)

| Field | Form `<option value>` | Old normalizer output | New normalizer output | Status |
|---|---|---|---|---|
| `tenant_require` / `furnished` | `"Furnished"`, `"Unfurnished"`, `"Negotiable"`, `"Partial"`, `"Turnkey"` | lowercase (`"furnished"`, etc.) | Title Case (exact match) | 🔧 Fixed |

**Root cause**: `normalizeFurnishing()` used `strtolower()` which produced lowercase values  
that didn't match the Title Case `<option value>` strings on the Landlord blade.  
**Fix applied**: `normalizeFurnishing()` now uses `ucfirst(strtolower($v))` → Title Case.

**Live verification**: Landlord `furnished=Unfurnished` ✅

### 5c — Sewer (Seller + Landlord)

| MLS raw value | Old output | New normalizer output | Form `<option value>` |
|---|---|---|---|
| `"Connected"` | `"Connected"` | `"Public Sewer"` | `"Public Sewer"` |
| `"Water Connected"` | `"Water Connected"` | `"Public Sewer"` | `"Public Sewer"` |
| `"Sewer Connected, Water Connected"` | `"Sewer Connected, Water Connected"` | `"Public Sewer"` | `"Public Sewer"` |
| `"Public"` | `"Public"` | `"Public Sewer"` | `"Public Sewer"` |
| `"Septic Tank"` | `"Septic Tank"` | `"Septic Tank"` | `"Septic Tank"` |
| `"Septic"` | `"Septic"` | `"Septic Tank"` | `"Septic Tank"` |
| `"None"` | `"None"` | `"None"` | `"None"` |
| `"Private"` | `"Private"` | `"Private Sewer"` | `"Private Sewer"` |

**Root cause**: No sewer normalizer existed; raw parser tokens (e.g. `"Connected"`, `"Public"`)  
did not match any form `<option value>`.  
**Fix applied**: New `MlsNormalizer::normalizeSewer()` + `normalizeSewerToken()` crosswalk;  
wired into `normalize()` dispatch and into the parser's sewer emit branch.

**Live verification**: Seller `sewer=Public Sewer` (from `Utilities: Sewer Connected, Water Connected`) ✅

**Regression tests**:  
- `test_normalizer_sewer_crosswalk` (MlsListingImportServiceTest — data provider)  
- `test_sewer_connected_water_connected_maps_to_public_sewer` (MlsListingImportServiceTest)  
- `test_pool_none_normalizes_to_no`, `test_garage_multivalue_extracts_yes`,  
  `test_pool_garage_carport_are_title_case`, `test_normalizer_form_yes_no_title_case`,  
  `test_furnished_normalizes_to_title_case_for_landlord_form` (MlsListingImportServiceTest)

---

## Category 6 — Property-Name Mismatches

This category covers cases where `MlsFieldMap` points to a Livewire public property name  
that doesn't exist on the target component.

**Audit method**: `test_all_mapped_seller_properties_exist_on_component` and  
`test_all_mapped_landlord_properties_exist_on_component` in `MlsListingImportServiceTest`  
use PHP reflection to confirm every mapped property name resolves to an actual public  
`$property` declaration on the respective `CreateOfferListing` Livewire component.

**Result**: ✅ All mapped properties confirmed to exist on both components. No dangling  
references found after all other fixes in this task were applied.

---

## Live URL Verification Summary

Both Stellar MLS Matrix public share URLs were verified live:

| URL | Role | Fields extracted |
|---|---|---|
| `https://stellar.mlsmatrix.com/matrix/shared/k1nNtfh9Skf/82889THAVENUEN` | Seller | address ✅, city ✅, state ✅, zip ✅, county ✅, garage ✅, carport ✅, pool ✅, sewer ✅, description (clean) ✅ |
| `https://stellar.mlsmatrix.com/matrix/shared/g3dTp3yYqlf/8535BLINDPASSDRIVE` | Landlord | address ✅, city ✅, state ✅, zip ✅, county ✅, furnished ✅, pool ✅, available_date ✅, lease_available_date ✅, description (clean) ✅ |

**property_type**: Not present on either Stellar MLS Matrix public share page — requires manual selection.

---

## Summary

| # | Category | Root Cause | Fix | Test Coverage |
|---|---|---|---|---|
| 1a | Address fields — field map | None — already correct | No change | `test_address_import_from_raw_text` |
| 1b | Address/city/state/zip — parser (Stellar live) | No labeled fields on Stellar MLS pages; unlabeled "About" header | New "About" header parser block (BUG-07) | 3 tests in MlsGapFixesRegressionTest BUG-07 |
| 1c | County — parser (Stellar live) | `County:Pinellas` no-space colon; regex required `[\s]+` | `[\s]+` → `[\s]*` (BUG-06) | 2 tests in MlsGapFixesRegressionTest BUG-06 |
| 2 | Property type | Seller/Landlord use different option value conventions | `normalizePropertyTypeForRole()` (prior task) | 8 tests in MlsGapFixesRegressionTest |
| 2b | Property type — live | Not on Stellar MLS Matrix pages at all | Documented; manual entry required | — |
| 3 | Description bleed — all-caps | MLS prepends address header to remarks | Address-strip regex post-capture | 3 tests in MlsGapFixesRegressionTest |
| 3b | Description bleed — Stellar live | Strip regex requires all-caps; "Florida" has lowercase | Fallback strip for full state names (BUG-08) | 3 tests in MlsGapFixesRegressionTest BUG-08 |
| 4 | Landlord date fields | `lease_available_date` missing from parser + field map | Emit both date keys; add to field map | 2 new tests in MlsListingImportServiceTest |
| 5 | Dropdown mismatches | Normalizer returned wrong case for select option values | `normalizeFormYesNo()`, `normalizeFurnishing()` Title Case, `normalizeSewer()` crosswalk | 9 new tests in MlsListingImportServiceTest |
| 6 | Property-name mismatches | — | Confirmed clean via reflection tests | `test_all_mapped_*_properties_exist_on_component` |

**Test totals after all fixes**: 199 tests in MlsListingImportServiceTest · 32 in MlsParserBleedRegressionTest · 68 in MlsGapFixesRegressionTest — all passing.
