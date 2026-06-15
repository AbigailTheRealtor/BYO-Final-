# MLS Import Full Pipeline Coverage Matrix

**Generated:** 2026-06-15  
**Parser source:** `app/Services/ListingImport/MlsListingImportService.php`  
**Field map source:** `app/Services/ListingImport/MlsFieldMap.php`  
**Fixtures:** `tests/fixtures/mls/`  
**Scope:** All 7 Stellar MLS property type × role combinations

---

## Summary Table

| # | Role | Property Type | Fixture File | Mapped | Skipped | Notes |
|---|------|--------------|-------------|--------|---------|-------|
| 1 | Seller | Residential (Single Family) | `residential.txt` | 48 | 4 | All core + structural + HOA/CDD/flood/waterfront |
| 2 | Landlord | Residential Rental | `rental.txt` | 50 | 4 | All core + rental terms + structural + waterfront |
| 3 | Seller | Income / Multi-Family | `income.txt` | 36 | 4 | Core + income metrics (units, gross income, expenses, cap rate) |
| 4 | Seller | Commercial Sale | `commercial_sale.txt` | 36 | 4 | Core + commercial metrics (NOI, cap rate, building size, current use) |
| 5 | Landlord | Commercial Lease | `commercial_lease.txt` | 30 | 3 | Core + lease type, tenant pays, office sqft, min lease |
| 6 | Seller | Business Opportunity | `business_opportunity.txt` | 26 | 5 | Core + business type, revenue, net income, employee count |
| 7 | Seller | Vacant Land | `vacant_land.txt` | 27 | 4 | Location + land metrics + flood/HOA/waterfront (no heated sqft) |

**Regression fixtures verified:**
- `seller_regression.txt` — 40 mapped, 3 skipped ✓
- `landlord_regression.txt` — 49 mapped, 4 skipped ✓

---

## Bugs Fixed in This Audit

### BUG-01 — Vacant Land: `heated_sqft` false positive from Lot Sq. Ft.

**Symptom:** Parser captured `100,188` (the lot's square footage) as `heated_sqft` on vacant-land
exports because the pattern `Sq\.?\s*Ft\.?` matched the `Sq. Ft.` substring inside `Lot Sq. Ft.: 100,188`.

**Root cause:** `MlsListingImportService.php` — first `heated_sqft` pattern allowed bare
`Sq\.?\s*Ft\.?` as a standalone alternative, not requiring `Heated` or `Living` adjacency.

**Fix:** Removed the bare `Sq. Ft.` alternative from the labeled pattern; all labeled alternatives
now require explicit `Heated` or `Living` adjacency. The natural-language fallback
`/(\d[\d,]+)\s*sq\.?\s*ft\.?/i` is retained for prose descriptions but fires only when no
labeled field matches first.

```
Before: '/(?:Heated|Living|Sq\.?\s*Ft\.?|Square\s+Feet)[\s:]+(\d[\d,]*)/i'
After:  '/(?:Heated|Living)\s+Sq\.?\s*Ft\.?[\s:]+(\d[\d,]*)/i'          (pattern 1)
        '/Sq\.?\s*Ft\.?\s+Heated[\s:]+(\d[\d,]*)/i'                      (pattern 2)
        '/(?:Heated|Living)\s+(?:Area|Square\s+Feet)[\s:]+(\d[\d,]*)/i'  (pattern 3)
        '/(\d[\d,]+)\s*(?:sq\.?\s*ft\.?|square\s+feet)/i'               (fallback)
```

**Verified:** Vacant land now produces no `heated_sqft` output (correct — no such field exists
for land parcels). Residential/rental/income/commercial fixtures still capture heated sqft correctly.  
**Regression tests:** `MlsGapFixesRegressionTest::test_bug_01_lot_sqft_does_not_false_positive_as_heated_sqft`,
`test_bug_01_heated_sqft_label_still_captured`

---

### BUG-02 — Vacant Land / Commercial: `utilities` bleed from Public Remarks

**Symptom:** "Utilities available at road. Great opportunity for residential development..."
from the Public Remarks of a vacant-land export was captured as the `utilities` field value.

**Root cause:** Pattern `'/Utilities[\s:]+([^\|\n]{1,120})/i'` used `[\s:]+` which matched
a plain space after `Utilities` — allowing `Utilities available` (no colon) to fire.

**Fix:** Changed to require an explicit colon: `'/Utilities\s*:[\s]*([^\|\n]{1,120})/i'`.
A structured MLS export always uses `Utilities:` with a colon; prose mentions of the word
never have a colon immediately following.

**Verified:** Vacant land no longer captures utilities from remarks. Residential/rental/commercial
fixtures still capture utilities correctly (`Utilities: Cable Available,...`).  
**Regression tests:** `MlsGapFixesRegressionTest::test_bug_02_utilities_in_remarks_without_colon_not_captured`,
`test_bug_02_utilities_with_colon_still_captured`

---

### BUG-03 — Seller Regression: `county` false positive ("Unified")

**Symptom:** In the seller regression fixture, `county` was captured as `Unified` instead of
`Pinellas`. The fixture embeds a school-district label inline without a colon:
`City: SEMINOLE County Unified State: FL` — then on a separate line: `County: Pinellas`.

**Root cause:** Pattern `'/County[\s:]+([^\|\n,]{2,50})/i'` used `[\s:]+` which matched
a plain space, so `County Unified` (no colon) was captured before `County: Pinellas`.

**Fix:** Changed to require a colon: `'/County\s*:[\s]+([^\|\n,]{2,50})/i'`. Stellar MLS
structured field exports always write `County: <value>` with a colon. The inline school-district
text never has one.

**Verified:** Seller regression now captures `county = Pinellas`. Landlord regression also
unaffected (its format `County: Pinellas` always had a colon).  
**Regression tests:** `MlsGapFixesRegressionTest::test_bug_03_county_without_colon_does_not_capture_unified`,
`test_bug_03_county_with_colon_still_captured`

---

### BUG-04 — All Roles: `legal_description` bleed past "Additional Parcels Y/N:"

**Symptom:** Legal description values captured `Additional Parcels Y/N: No` (and sometimes
`Special Assessments Y/N: No`) as trailing text appended to the actual legal description.

**Root cause:** `Additional Parcels` was absent from the `$labelStop` boundary pattern.
Additionally, the `Y/N` sub-label (which appears between the label word and the colon in
`Additional Parcels Y/N: No`) means a bare `Additional\s+Parcels\b\s*:` would not match
because the colon follows `Y/N`, not `Parcels` directly.

**Fix:** Added `|Additional\s+Parcels(?:\s+Y\/N)?\b` to `$labelStop`, following the same
optional-Y/N pattern already used for `Special\s+Assessment(?:\s+Y\/N)?\b`.

**Verified:** All three affected fixtures (residential, landlord regression, vacant land) now
show clean legal descriptions terminated before `Additional Parcels Y/N:`.  
**Regression tests:** `MlsGapFixesRegressionTest::test_bug_04_legal_description_stops_at_additional_parcels_yn_label`,
`test_bug_04_additional_parcels_still_captured_after_legal_desc`

---

### BUG-05 — Landlord: `property_type` missing from field map

**Symptom:** On landlord role imports, the parsed `property_type` (e.g. `Residential Rental`,
`Commercial`) was always discarded — the landlord field map had no entry for it. The form
property `$property_type` exists on `LandlordOfferListing` at line 49 but was never populated.

**Root cause:** `MlsFieldMap::landlord()` did not include `'property_type' => 'property_type'`.

**Fix:** Added `'property_type' => 'property_type'` as the first entry in the landlord map.
`normalizePropertyTypeForRole()` already handles `Residential Rental → Residential Property`
and `Commercial → Commercial Property` at apply-time, so no normalizer change was needed.

**Verified:** Landlord regression now maps `property_type = Residential Rental` (previewed
as `Residential Property` after normalization). Commercial lease fixture unaffected (no
property_type field in that fixture; form defaults to `Commercial Property`).  
**Regression tests:** `MlsGapFixesRegressionTest::test_bug_05_landlord_field_map_includes_property_type`,
`test_bug_05_seller_field_map_still_has_property_type`

---

### BUG-06 — Income Fixture: Missing income-specific fields

**Symptom:** Parser branches for `number_of_units`, `gross_annual_income`,
`annual_operating_expenses`, and `cap_rate` existed in the parser but were untestable
because `income.txt` contained none of those labeled fields.

**Fix:** Rewrote `tests/fixtures/mls/income.txt` to include:
- `Number of Units: 8`
- `Annual Gross Income: $96,000`
- `Annual Operating Expenses: $28,000`
- `Cap Rate: 7.2%`

**Verified:** All four fields now parse and map correctly for the income combination.  
**Regression tests:** `MlsIncomeFieldsImportTest` (16 tests covering all income-specific parser branches);
`MlsLiveImportAuditTest::test_seller_save_reload_income_gross_annual_income_roundtrip`,
`test_seller_save_reload_income_annual_operating_expenses_roundtrip`,
`test_seller_save_reload_income_unit_number_roundtrip`

---

### BUG-07 — Business Opportunity Fixture: Missing business-specific fields

**Symptom:** Parser branches for `business_type`, `annual_revenue`, `annual_net_income_business`,
and `employee_count` existed but were untestable because `business_opportunity.txt` was minimal.

**Fix:** Rewrote `tests/fixtures/mls/business_opportunity.txt` to include:
- `Business Type: Retail/Service`
- `Annual Revenue: $520,000`
- `Annual Net Income: $87,000`
- `Number of Employees: 6`

**Verified:** All four fields now parse and map correctly for the business opportunity combination.  
**Regression tests:** `MlsLiveImportAuditTest::test_parser_business_opportunity_fixture_has_business_specific_fields`
(asserts all five business-specific fields parse correctly; also verifies `business_lease_type` is absent
since the fixture has no Lease Type label)

---

## Per-Role Detailed Coverage

### 1. Seller / Residential

**Fixture:** `tests/fixtures/mls/residential.txt`  
**Result:** 52 parsed → 48 mapped → 4 intentionally skipped

| Canonical Key | Livewire Property | Notes |
|--------------|------------------|-------|
| property_type | property_type | |
| garage_spaces | garage_parking_spaces | |
| address | address | |
| city | property_city | |
| state | property_state | |
| zip | property_zip | |
| county | property_county | |
| price | maximum_budget | "Desired Sale Price" field |
| bedrooms | bedrooms | |
| bathrooms | bathrooms | |
| heated_sqft | minimum_heated_square | |
| lot_dimensions | lot_dimensions | |
| lot_size_acres | total_acreage | |
| year_built | year_built | |
| pool | pool_needed | |
| garage | garage_needed | |
| carport | carport_needed | |
| furnished | building_features | |
| tax_id | parcel_id | |
| tax_year | tax_year | |
| annual_taxes | annual_property_taxes | |
| legal_description | legal_description | |
| flood_zone_code | flood_zone_code | |
| flood_zone_date | flood_zone_date | |
| flood_zone_panel | flood_zone_panel | |
| additional_parcels | additional_parcels | |
| has_hoa | has_hoa | |
| association_name | association_name | |
| association_fee_amount | association_fee_amount | |
| association_fee_frequency | association_fee_frequency | |
| has_cdd | has_cdd | |
| air_conditioning | *air_conditioning | multi-select |
| heating_fuel | *heating_and_fuel | multi-select |
| interior_features | *interior_features | multi-select |
| roof_type | *roof_type | multi-select |
| exterior_construction | *exterior_construction | multi-select |
| foundation | *foundation | multi-select |
| water | *water | multi-select |
| sewer | *sewer | multi-select |
| utilities | *utilities | multi-select |
| sqft_heated_source | sqft_heated_source | |
| flood_insurance_required | flood_insurance_required | |
| has_special_assessments | has_special_assessments | |
| appliances | *appliances | multi-select |
| waterfront | waterfront | |
| water_access | *water_access | multi-select |
| water_view | *water_view | multi-select |
| description | additional_details | |

**Intentionally skipped:**
- `mls_number` — no Livewire property on SellerOfferListing
- `lot_size_sqft` — form uses acres (total_acreage); no sqft property exists
- `directions` — navigation text, no listing purpose
- `listing_type_hint` — internal signal only, removed after detection

---

### 2. Landlord / Residential Rental

**Fixture:** `tests/fixtures/mls/rental.txt`  
**Result:** 54 parsed → 50 mapped → 4 intentionally skipped

All fields from Seller/Residential plus:

| Canonical Key | Livewire Property | Notes |
|--------------|------------------|-------|
| property_type | property_type | ✅ Fixed BUG-05 |
| available_date | available_date | |
| minimum_security_deposit | security_deposit_amount | |
| lease_amount_frequency | lease_amount_frequency | |
| terms_of_lease | *terms_of_lease | multi-select; normalizer re-routes Month-to-Month |
| tenant_pays | *tenant_pays | multi-select |
| rent_includes | *rent_includes | multi-select |
| water_frontage | water_frontage | |
| waterfront_feet | waterfront_feet | |

Seller-specific fields not in landlord map: `garage_spaces`, `furnished→building_features`,
`interior_features`, `heating_and_fuel` naming differences noted (landlord uses `*heating_fuel`).

**Intentionally skipped:**
- `mls_number` — no Livewire property on LandlordOfferListing
- `lot_size_sqft` — form uses acres (total_acreage); no sqft property exists
- `directions` — navigation text, no listing purpose
- `listing_type_hint` — internal signal only

---

### 3. Seller / Income (Multi-Family)

**Fixture:** `tests/fixtures/mls/income.txt`  
**Result:** 40 parsed → 36 mapped → 4 intentionally skipped

Core residential fields (no pool/carport/furnished/a-c/appliances/structural in fixture) plus:

| Canonical Key | Livewire Property | Notes |
|--------------|------------------|-------|
| number_of_units | unit_number | ✅ Fixed BUG-06 (fixture) |
| gross_annual_income | gross_annual_income | ✅ Fixed BUG-06 (fixture) |
| annual_operating_expenses | annual_operating_expenses | ✅ Fixed BUG-06 (fixture) |
| cap_rate | minimum_cap_rate | ✅ Fixed BUG-06 (fixture) |
| zoning | zoning | |
| has_special_assessments | has_special_assessments | |
| special_assessment_amount | special_assessment_amount | |
| special_assessment_description | special_assessment_description | |

**Preview-only (parsed but null-mapped — visible in modal, not applied to form):**
- `net_operating_income_raw` — raw NOI label captured for display only
- `unit_types_raw` — raw unit type text for display only
- `occupancy_rate_raw` — raw occupancy percentage for display only

**Intentionally skipped:**
- `mls_number`, `lot_size_sqft`, `directions`, `listing_type_hint`

---

### 4. Seller / Commercial Sale

**Fixture:** `tests/fixtures/mls/commercial_sale.txt`  
**Result:** 40 parsed → 36 mapped → 4 intentionally skipped

Core fields plus commercial-specific:

| Canonical Key | Livewire Property | Notes |
|--------------|------------------|-------|
| building_size_sqft | total_square_feet | |
| ceiling_height_ft | ceiling_height | |
| parking_spaces_count | garage_parking_spaces | |
| net_operating_income | minimum_annual_net_income | |
| cap_rate | minimum_cap_rate | |
| building_features_list | *building_features | multi-select |
| current_use_list | *current_use | multi-select |
| zoning | zoning | |
| additional_parcels | additional_parcels | |
| total_parcel_count | total_parcel_count | |
| water_access | *water_access | |
| water_view | *water_view | |

**Preview-only:**
- `net_operating_income_raw` — raw "NOI: $185,000" text for modal display only

**Intentionally skipped:**
- `mls_number`, `lot_size_sqft`, `directions`, `listing_type_hint`

---

### 5. Landlord / Commercial Lease

**Fixture:** `tests/fixtures/mls/commercial_lease.txt`  
**Result:** 33 parsed → 30 mapped → 3 intentionally skipped

Core location/price/tax/flood/HOA fields plus commercial-lease-specific:

| Canonical Key | Livewire Property | Notes |
|--------------|------------------|-------|
| available_date | available_date | |
| lease_rate_type | commercial_lease_type | NNN/MG/Gross normalization |
| minimum_lease_months | min_lease_period | |
| office_area_sqft | office_retail_sqft | |
| pets_allowed | pet_policy | |
| tenant_pays | *tenant_pays | multi-select |
| rent_includes | *rent_includes | multi-select |
| association_name | association_name | |
| association_fee_amount | association_fee_amount | |
| association_fee_frequency | association_fee_frequency | |

**Intentionally skipped:**
- `mls_number`, `directions`, `listing_type_hint`
- Note: no `lot_size_sqft` in commercial lease fixture

---

### 6. Seller / Business Opportunity

**Fixture:** `tests/fixtures/mls/business_opportunity.txt`  
**Result:** 31 parsed → 26 mapped → 5 intentionally skipped

Core location/price/tax/flood/HOA/special-assessment fields plus:

| Canonical Key | Livewire Property | Notes |
|--------------|------------------|-------|
| business_type | business_type | ✅ Fixed BUG-07 (fixture) |
| annual_revenue | annual_revenue | ✅ Fixed BUG-07 (fixture) |
| annual_net_income_business | minimum_annual_net_income | ✅ Fixed BUG-07 (fixture) |
| employee_count | employee_count | ✅ Fixed BUG-07 (fixture) |
| special_assessment_amount | special_assessment_amount | |
| special_assessment_description | special_assessment_description | |

**Intentionally skipped:**
- `mls_number`, `directions`, `listing_type_hint`
- `inventory_included` — no boolean property on SellerOfferListing; `inventory_value` is
  a dollar-amount field, not a Y/N flag
- `seller_financing_yn` — `offered_financing` is an array multi-select, not a Y/N boolean;
  cannot map a boolean import to a multi-select without business-logic translation

---

### 7. Seller / Vacant Land

**Fixture:** `tests/fixtures/mls/vacant_land.txt`  
**Result:** 31 parsed → 27 mapped → 4 intentionally skipped

| Canonical Key | Livewire Property | Notes |
|--------------|------------------|-------|
| address | address | |
| city | property_city | |
| state | property_state | |
| zip | property_zip | |
| county | property_county | |
| price | maximum_budget | |
| lot_dimensions | lot_dimensions | |
| lot_size_acres | total_acreage | |
| tax_id | parcel_id | |
| tax_year | tax_year | |
| annual_taxes | annual_property_taxes | |
| legal_description | legal_description | ✅ Fixed BUG-04 |
| flood_zone_code | flood_zone_code | |
| flood_zone_date | flood_zone_date | |
| flood_zone_panel | flood_zone_panel | |
| zoning | zoning | |
| additional_parcels | additional_parcels | |
| total_parcel_count | total_parcel_count | |
| has_hoa | has_hoa | |
| has_cdd | has_cdd | |
| flood_insurance_required | flood_insurance_required | |
| has_special_assessments | has_special_assessments | |
| special_assessment_description | special_assessment_description | |
| waterfront | waterfront | |
| water_access | *water_access | |
| water_view | *water_view | |
| description | additional_details | |

**Not applicable for vacant land (correctly absent):**
- `heated_sqft` — ✅ Fixed BUG-01: no longer false-captured from Lot Sq. Ft.
- `utilities` — ✅ Fixed BUG-02: no longer false-captured from description prose
- `bedrooms`, `bathrooms`, `year_built`, `pool`, `garage`, `carport` — land parcel only
- `a/c`, `heating`, `appliances`, `interior_features`, `roof_type`, etc. — structural fields,
  not applicable

**Intentionally skipped:**
- `mls_number`, `lot_size_sqft`, `directions`, `listing_type_hint`

---

## Regression Fixtures

### Seller Regression (`seller_regression.txt`)

Tests edge-case parser patterns in a single-family residential export:
- `county` — ✅ Fixed BUG-03: now captures `Pinellas` (was `Unified`)
- `legal_description` — ✅ Fixed BUG-04: clean, no bleed
- All 40 mapped fields verified correct

### Landlord Regression (`landlord_regression.txt`)

Tests edge-case patterns in a residential rental export:
- `property_type` — ✅ Fixed BUG-05: now mapped (`Residential Rental → Residential Property`)
- `legal_description` — ✅ Fixed BUG-04: clean, no bleed
- `waterfront_feet` — correctly captures `0` (zero value, not falsy-skipped)
- All 49 mapped fields verified correct

---

## MLS Import Snapshot Storage

After the user clicks **Apply Selected**, the import pipeline stores two meta keys on the listing:

### `mls_import_snapshot` (JSON)

Stored on every listing after any MLS import. Contains:

```json
{
  "imported_at": "2026-06-15T12:00:00+00:00",
  "source": "raw_text | https://...",
  "raw_fields": { ... all canonical key → value pairs parsed from MLS ... },
  "normalized_fields": { ... TEMPORARY: currently identical to raw_fields — see TODO in HasMlsImport.php ... },
  "mapped_form_fields": { ... subset with a matching Livewire property ... },
  "unsupported_fields": { ... subset with NO Livewire property or field map entry ... },
  "mls_address_raw": "4521 Sunridge Drive, Tampa, FL 33610"
}
```

**When it is saved:**
- Immediately in `applyImportedFields()` if `listingId` is already set (listing exists).
- At the next `saveDraft()` / `store()` call via `saveSnapshotMeta($auction)` (for brand-new listings where no DB row exists at apply time).

**Purpose:** Preserves ALL parsed MLS data regardless of whether a form field exists for it.
Ask AI and future features can read `mls_import_snapshot.unsupported_fields` (normalized tier)
and `mls_import_snapshot.raw_fields` as fallback sources before AI inference.

### `mls_address_raw` (string)

Assembled from parsed address components: `"Street, City, State ZIP"`.
Stored alongside the snapshot. Serves as the unverified MLS source address.
Google Places confirmation will use this as the input when that feature is implemented.

### Field Classification in the Snapshot

| Classification | Where stored | Example fields |
|---------------|-------------|----------------|
| Mapped to form | `mapped_form_fields` | price, bedrooms, address, city, state, etc. |
| Unsupported by current form | `unsupported_fields` | mls_number, directions, inventory_included, seller_financing_yn |
| Both (all parsed) | `raw_fields` | Every field the parser extracted |

Unsupported fields are **not discarded** — they are preserved in `unsupported_fields` inside the snapshot for Ask AI and future form expansions.

**Regression tests:** `MlsLiveImportAuditTest::test_snapshot_json_populated_after_apply`,
`test_snapshot_contains_required_structure_keys`, `test_snapshot_separates_mapped_from_unsupported_fields`,
`test_snapshot_mls_address_raw_assembled_from_address_fields`, `test_snapshot_source_is_raw_text_for_pasted_input`,
`test_snapshot_persisted_to_meta_on_save_reload`, `test_mls_address_raw_persisted_to_meta_on_save`,
`test_snapshot_and_address_raw_persisted_for_landlord`, `test_snapshot_unsupported_fields_are_retained_not_discarded`

---

## Known Intentionally Excluded Fields (All Roles)

Fields below have no current form property. They are **not silently dropped** — they are
stored in `mls_import_snapshot.unsupported_fields` for future use by Ask AI and form expansion.

| Canonical Key | Reason No Form Field Exists | Stored in Snapshot |
|--------------|----------------------------|--------------------|
| `mls_number` | No Livewire property on any OfferListing component | ✅ unsupported_fields |
| `lot_size_sqft` | Forms use `total_acreage` (acres); no sqft property exists | ✅ unsupported_fields |
| `directions` | Navigation text with no listing form purpose | ✅ unsupported_fields |
| `listing_type_hint` | Internal rental/sale signal only; consumed before field mapping | ❌ stripped pre-snapshot |
| `rental_rate_type` | Signal-only field used to detect listing type; stripped after use | ❌ stripped pre-snapshot |
| `inventory_included` | No boolean property on SellerOfferListing | ✅ unsupported_fields |
| `seller_financing_yn` | `offered_financing` is a multi-select array, not a Y/N boolean | ✅ unsupported_fields |
| `business_lease_type` | No matching Livewire property on SellerOfferListing | ✅ unsupported_fields |
| `net_operating_income_raw` | Preview-only display text; null-mapped in field map | ✅ unsupported_fields |
| `unit_types_raw` | Preview-only display text; null-mapped in field map | ✅ unsupported_fields |
| `occupancy_rate_raw` | Preview-only display text; null-mapped in field map | ✅ unsupported_fields |

---

## Parser Safety Notes

### Patterns Requiring Colon (Anti-bleed)
After this audit, the following parsers now require an explicit colon (preventing
accidental prose matches):

| Field | Pattern Change |
|-------|---------------|
| `county` | `County[\s:]+` → `County\s*:[\s]+` |
| `utilities` | `Utilities[\s:]+` → `Utilities\s*:[\s]*` |

### labelStop Additions
`Additional\s+Parcels(?:\s+Y\/N)?\b` added to prevent legal_description bleed.

### normalizePropertyTypeForRole() Crosswalk

| MLS Raw Value | Role | Normalized Value |
|--------------|------|-----------------|
| `Single Family Residence` | seller | `Residential` (no suffix) |
| `Single Family Residence` | landlord | `Residential Property` |
| `Residential Rental` | landlord | `Residential Property` |
| `Commercial` / `Commercial Sale` | seller | `Commercial` |
| `Commercial` | landlord | `Commercial Property` |
| `Vacant Land` | seller | `Vacant Land` |
| `Business Opportunity` | seller | `Business Opportunity` |
| `Income/Multi-Family` | seller | `Income` |

---

## Files Changed in This Audit

| File | Change Type | Description |
|------|------------|-------------|
| `app/Services/ListingImport/MlsListingImportService.php` | Bug fix | 4 parser fixes (heated_sqft, utilities, county, labelStop) |
| `app/Services/ListingImport/MlsFieldMap.php` | Bug fix | Added `property_type` to landlord field map |
| `app/Http/Livewire/OfferListing/Concerns/HasMlsImport.php` | Feature | mls_import_snapshot + mls_address_raw storage; mlsParsedDataJson + mlsImportSnapshotJson props; saveSnapshotMeta() helper; resolveMlsModel() helper |
| `app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php` | Feature | `$this->saveSnapshotMeta($auction)` added at end of saveAllMetadata() |
| `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php` | Feature | `$this->saveSnapshotMeta($auction)` added at end of saveAllMetadata() |
| `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php` | Feature | `$this->saveSnapshotMeta($auction)` added at end of saveAllMetadata() |
| `app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php` | Feature | `$this->saveSnapshotMeta($auction)` added at end of saveAllMetadata() |
| `tests/fixtures/mls/income.txt` | Enhancement | Added Number of Units, Gross Income, Expenses, Cap Rate |
| `tests/fixtures/mls/business_opportunity.txt` | Enhancement | Added Business Type, Revenue, Net Income, Employees |
