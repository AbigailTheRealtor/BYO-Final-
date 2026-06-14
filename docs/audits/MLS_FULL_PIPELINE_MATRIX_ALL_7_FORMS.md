# MLS Full Pipeline Matrix — All 7 Property Forms

**Audit Date:** 2026-06-14 (updated: Stage 8/9 audit added, P5 apply-time routing fix)
**Scope:** All 7 Stellar MLS property-form types × the complete 11-stage import pipeline (Stages 1–7 including Stage 5.5, plus Stage 8 public view and Stage 9 Ask AI).
**Status:** Post-remediation (Task #2729 — P1/P2 parser gap fixes + Stage 5.5 Select2 fix + P3 JS two-pass rehydration + P4 property-type normalization + P5 terms_of_lease apply-time routing + Ask AI water_view key fix).

---

## 1. Status Taxonomy

Every cell in the field-status matrices below uses one of the following codes:

| Code | Meaning |
|------|---------|
| **PASS** | Field correctly traverses all relevant stages: parse → normalize → apply → save → reload → public view → Ask AI |
| **APPLY FAILURE** | Normalizer or field-map gap means the value is never written to the Livewire component prop |
| **DISPLAY FAILURE** | Value is on the component prop but the UI control (Select2) does not reflect it — the Stage 5.5 symptom |
| **SAVE/RELOAD FAILURE** | Value is set on the component but is corrupted or lost by `saveDraft()` / `loadDraft()` |
| **NORMALIZATION FAILURE** | Parser captures a value but the normalizer transforms it incorrectly |
| **UNSUPPORTED** | Field intentionally omitted: no matching Livewire prop, or form type N/A |
| **PHANTOM** | Canonical key appears in the field map but has no parser or data source — always null |
| **PUBLIC VIEW GAP** | Stages 1–7 PASS but the public listing view blade does not render the field (pre-existing gap, not MLS-specific) |
| **ASK AI GAP** | Stages 1–7 PASS but `AskAiContextBuilderService::extractFactualFields()` reads a different meta key (pre-existing gap) |

A ✱ suffix (e.g. `PASS✱`) denotes a field that was in a failure state before Task #2729 and is now passing after the gap fix.

---

## 2. The 7 Property Forms

| # | Stellar MLS Form Name | Platform Role | Property-Type context |
|---|----------------------|---------------|-----------------------|
| 1 | Residential Sale | Seller | Residential Property |
| 2 | Condo / Townhome Sale | Seller | Condo/Townhome |
| 3 | Commercial Sale | Seller | Commercial |
| 4 | Business Opportunity | Seller | Business |
| 5 | Vacant Land Sale | Seller | Vacant Land |
| 6 | Income / Multifamily Sale | Seller | Income/MultiFamily |
| 7a | Residential Rental | Landlord | Residential Property |
| 7b | Commercial Lease | Landlord | Commercial Property |

Forms 1–6 share a single pipeline: `SellerOfferListing` Livewire component, `MlsFieldMap::seller()`, `SellerAgentAuction` model (native columns + EAV metas).
Forms 7a and 7b both use: `LandlordOfferListing`, `MlsFieldMap::landlord()`, `LandlordAgentAuction` model (EAV metas for all structural fields).
The residential/commercial distinction within the landlord pipeline is determined by the property type selected in the form, not by a separate component or field map.
In the status matrices below, "Form 7" means both 7a and 7b; cells that differ are shown with explicit 7a / 7b sub-columns.

---

## 3. Pipeline Stages

```
Stage 1   URL fetch / raw-text inject    →  MlsListingImportService::import()
Stage 2   HTML strip / text normalize    →  extractVisibleText() + preg_replace cleanup
Stage 3   Field parsing                  →  parseFields() with $labelStop boundary guards
Stage 4   Value normalization            →  MlsNormalizer::normalize() per canonical key
Stage 5a  Preview gating                 →  importListingFromUrl() → importPreviewData array
Stage 5b  Apply Selected                 →  applyImportedFields() → writes Livewire props
Stage 5.5 Select2 rehydration            →  dispatchBrowserEvent('mlsApplied') → JS listener
Stage 6   Save Draft                     →  saveDraft() — persists to DB (native col or EAV)
Stage 7   Load Draft                     →  loadDraft() — restores component state from DB
Stage 8   Public view display            →  offer-listing/{role}/view.blade.php reads meta key
Stage 9   Ask AI access                  →  AskAiContextBuilderService::extractFactualFields() reads meta key
```

---

## 4. Field Status Matrix

### 4.1 Core / Address Fields (all 7 forms)

| Canonical Key | Livewire Prop (S / L) | Form 1 | Form 2 | Form 3 | Form 4 | Form 5 | Form 6 | Form 7 |
|---------------|----------------------|--------|--------|--------|--------|--------|--------|--------|
| `price` | `maximum_budget` / `desired_rental_amount` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `address` | `address` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `city` | `property_city` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `state` | `property_state` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `zip` | `property_zip` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `county` | `property_county` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `bedrooms` | `bedrooms` | PASS | PASS | UNSUPPORTED | UNSUPPORTED | UNSUPPORTED | PASS | PASS |
| `bathrooms` | `bathrooms` | PASS | PASS | UNSUPPORTED | UNSUPPORTED | UNSUPPORTED | PASS | PASS |
| `heated_sqft` | `minimum_heated_square` | PASS | PASS | PASS | PASS | UNSUPPORTED | PASS | PASS |
| `year_built` | `year_built` | PASS | PASS | PASS | PASS | UNSUPPORTED | PASS | PASS |
| `property_type` | `property_type` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `description` | `additional_details` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |

### 4.2 Property Characteristics — Seller Forms 1–6

| Canonical Key | Livewire Prop | Form 1 | Form 2 | Form 3 | Form 4 | Form 5 | Form 6 |
|---------------|--------------|--------|--------|--------|--------|--------|--------|
| `pool` | `pool_needed` | PASS | PASS | UNSUPPORTED | UNSUPPORTED | UNSUPPORTED | PASS |
| `garage` | `garage_needed` | PASS | PASS | UNSUPPORTED | UNSUPPORTED | UNSUPPORTED | PASS |
| `carport` | `carport_needed` | PASS | PASS | UNSUPPORTED | UNSUPPORTED | UNSUPPORTED | PASS |
| `garage_spaces` | `garage_parking_spaces` | PASS | PASS | PASS | UNSUPPORTED | UNSUPPORTED | PASS |
| `furnished` | `building_features` | PASS | PASS | UNSUPPORTED | UNSUPPORTED | UNSUPPORTED | PASS |
| `air_conditioning` | `*air_conditioning` (array) | PASS | PASS | PASS | UNSUPPORTED | UNSUPPORTED | PASS |
| `appliances` | `*appliances` (array) | PASS | PASS | UNSUPPORTED | UNSUPPORTED | UNSUPPORTED | PASS |
| `heating_fuel` | `*heating_and_fuel` (array) | PASS | PASS | PASS | UNSUPPORTED | UNSUPPORTED | PASS |
| `roof_type` | `*roof_type` (array) | PASS | PASS | PASS | UNSUPPORTED | UNSUPPORTED | PASS |
| `exterior_construction` | `*exterior_construction` (array) | PASS | PASS | PASS | UNSUPPORTED | UNSUPPORTED | PASS |
| `foundation` | `*foundation` (array) | PASS | PASS | PASS | UNSUPPORTED | UNSUPPORTED | PASS |
| `water` | `*water` (array) | PASS | PASS | PASS | UNSUPPORTED | UNSUPPORTED | PASS |
| `sewer` | `sewer` | PASS✱ | PASS✱ | PASS✱ | UNSUPPORTED | UNSUPPORTED | PASS✱ |
| `utilities` | `*utilities` (array) | PASS | PASS | PASS | UNSUPPORTED | UNSUPPORTED | PASS |
| `sqft_heated_source` | `sqft_heated_source` | PASS | PASS | PASS | UNSUPPORTED | UNSUPPORTED | PASS |
| `interior_features` | `*interior_features` (array) | PASS | PASS | PASS | UNSUPPORTED | UNSUPPORTED | PASS |
| `zoning` | `zoning` | PASS✱ | UNSUPPORTED | PASS✱ | UNSUPPORTED | PASS✱ | PASS✱ |
| `lot_dimensions` | `lot_dimensions` | PASS | UNSUPPORTED | PASS | UNSUPPORTED | PASS | PASS |
| `lot_size_acres` | `total_acreage` | PASS | UNSUPPORTED | PASS | UNSUPPORTED | PASS | PASS |

> **sewer PASS✱ note:** Stage 5.5 DISPLAY FAILURE (Select2 not refreshed after Apply) was fixed by the `mlsApplied` browser event + JS listener added in this task. The `Water\b(?=\s*:)` labelStop addition prevents parser bleed. Verified by `test_stage5_5_sewer_apply_save_reload_end_to_end`. **Root-cause fix (sixth pass, P4-1):** The MLS parser captures `"Residential Property"` verbatim; the seller blade conditionals check `$property_type === 'Residential'` (no suffix) — so the entire sewer/water/utilities section never rendered after Apply. Fixed by `HasMlsImport::normalizePropertyTypeForRole()` which maps `"Residential Property"` → `'Residential'` for seller/buyer/tenant, and correctly leaves `'Residential Property'` unchanged for the landlord form (whose blade uses the full form). Browser-verified: `#sewer_residential` count=1 after Apply Selected on `/dev/offer-listing/seller` with MLS text containing `Property Type: Residential Property`. Two-pass JS rehydration (200 ms + 600 ms) is still present for any remaining DOM-render race conditions.
>
> **zoning PASS✱ note:** Was limited to single-token codes (e.g. "R-1"). P2-2 fix expanded char class to capture multi-word codes ("R-1 Single Family", "B-3 General Business"). Verified by `test_seller_save_reload_vacant_land_zoning_roundtrip` and `test_P2_2_*`.

### 4.3 Tax / Legal / Flood Zone — Seller Forms 1–6 and Landlord

| Canonical Key | Livewire Prop | Form 1–6 | Form 7 |
|---------------|--------------|----------|--------|
| `tax_id` | `parcel_id` | PASS✱ | PASS✱ |
| `tax_year` | `tax_year` | PASS | PASS |
| `annual_taxes` | `annual_property_taxes` | PASS | PASS |
| `legal_description` | `legal_description` | PASS | PASS |
| `flood_zone_code` | `flood_zone_code` | PASS | PASS |
| `flood_zone_panel` | `flood_zone_panel` | PASS | PASS |
| `flood_zone_date` | `flood_zone_date` | PASS✱ | PASS✱ |
| `flood_insurance_required` | `flood_insurance_required` | PASS | PASS |
| `additional_parcels` | `additional_parcels` | PASS | PASS |
| `total_parcel_count` | `total_parcel_count` | PASS | PASS |

> **tax_id PASS✱:** P1-3 added "Folio Number:" and "Folio #:" label variants (Miami-Dade/Broward format).
>
> **flood_zone_date PASS✱:** P2-3 extended the date regex to capture text-month formats ("January 15, 2020", "Mar 8 2019") in addition to numeric ("01/15/2020") and ISO ("2020-01-15") formats.

### 4.4 HOA / CDD — Seller Forms 1–6 and Landlord

| Canonical Key | Livewire Prop | Form 1–3, 6 | Form 4–5 | Form 7 |
|---------------|--------------|-------------|----------|--------|
| `has_hoa` | `has_hoa` | PASS | UNSUPPORTED | PASS |
| `association_name` | `association_name` | PASS | UNSUPPORTED | PASS |
| `association_fee_amount` | `association_fee_amount` | PASS✱ | UNSUPPORTED | PASS✱ |
| `association_fee_frequency` | `association_fee_frequency` | PASS | UNSUPPORTED | PASS |
| `has_cdd` | `has_cdd` | PASS | UNSUPPORTED | PASS |
| `annual_cdd_fee` | `annual_cdd_fee` | PASS | UNSUPPORTED | PASS |

> **association_fee_amount PASS✱:** P1-2 added "HOA Dues:" label variant common in some MLS export templates.

### 4.5 Waterfront Fields — Seller and Landlord

| Canonical Key | Livewire Prop | Form 1–3, 5–6 | Form 4 | Form 7 |
|---------------|--------------|---------------|--------|--------|
| `waterfront` | `waterfront` | PASS | UNSUPPORTED | PASS |
| `water_access` | `*water_access` (array) | PASS | UNSUPPORTED | PASS |
| `water_view` | `*water_view` (array) | PASS | UNSUPPORTED | PASS |
| `water_frontage` | `water_frontage` | PASS✱ | UNSUPPORTED | PASS✱ |
| `waterfront_feet` | `waterfront_feet` | PASS✱ | UNSUPPORTED | PASS✱ |

> **water_frontage / waterfront_feet PASS✱:** P2-4 corrected stale comments that implied these fields were dead; confirmed fully wired in `MlsFieldMap` for both Seller and Landlord. Additionally fixed a PHP falsy-zero bug (`if ($v = $extract(...))` → `if (($v = $extract(...)) !== null)`) that silently dropped a parsed value of `"0"`.

### 4.6 Rental-Specific Fields — Forms 7a (Residential Rental) and 7b (Commercial Lease)

Both 7a and 7b use `LandlordOfferListing` and `MlsFieldMap::landlord()`.
The table below distinguishes fields that are relevant to one form type only.

| Canonical Key | Livewire Prop | Form 7a (Residential) | Form 7b (Commercial) | Note |
|---------------|--------------|----------------------|----------------------|------|
| `available_date` | `available_date` | PASS | PASS | |
| `lease_amount_frequency` | `lease_amount_frequency` | PASS | PASS | |
| `minimum_security_deposit` | `security_deposit_amount` | PASS✱ | PASS✱ | P1-1: bare "Security Deposit:" label added |
| `terms_of_lease` | `*terms_of_lease` (array) / rerouted to `*desired_lease_length` | PASS✱ | PASS✱ | See note below |
| `tenant_pays` | `*tenant_pays` (array) | PASS | PASS | |
| `lease_rate_type` | `commercial_lease_type` | UNSUPPORTED | PASS | Commercial-only field |
| `pets_allowed` | `pet_policy` | PASS | UNSUPPORTED | Residential-only field |
| `minimum_lease_months` | `min_lease_period` | UNSUPPORTED | PASS | Commercial-only field |
| `office_area_sqft` | `office_retail_sqft` | UNSUPPORTED | PASS | Commercial-only field |

> **minimum_security_deposit PASS✱:** P1-1 added the 2-word "Security Deposit:" bare label. Previously only "Minimum Security Deposit:" (3-word) was matched.
>
> **terms_of_lease — Both Forms PASS✱ (P5 apply-time routing fix):**
> P2-1 added the reversed "Lease Terms:" label variant used in Stellar MLS commercial-lease exports.
> For commercial MLS exports (Form 7b), parsed values like "Gross Lease" and "Net Lease" match the
> blade `$termLease` option array exactly → PASS.
> For residential MLS exports (Form 7a), the canonical key `terms_of_lease` now carries DURATION
> values ("Month-to-Month", "1 Year", etc.) which belong in the `desired_lease_length` prop whose
> blade uses `$residential_lease_term_options`.  P5 fix: `HasMlsImport::applyImportedFields()`
> re-routes the `terms_of_lease` canonical key to the `desired_lease_length` prop when
> `$role === 'landlord'` AND `$this->property_type === 'Residential Property'`.  Commercial
> listings are unaffected (routing guard requires Residential Property type).
> Previously asserted as a NORMALIZATION FAILURE by
> `test_landlord_terms_of_lease_residential_term_is_normalization_failure()` — that test is
> now replaced by `test_landlord_terms_of_lease_residential_month_to_month_is_valid_desired_lease_length()`
> (PASS) plus three routing regression tests in `MlsGapFixesRegressionTest` (P3-1a/b/c).

### 4.7 Landlord Property Characteristics (Form 7)

| Canonical Key | Livewire Prop | Form 7 | Note |
|---------------|--------------|--------|------|
| `heating_fuel` | `*heating_fuel` (array) | PASS | Prop name differs from Seller (`heating_and_fuel`) |
| `rent_includes` | `*rent_includes` (array) | PASS | |
| `water` | `*water` (array) | PASS | |
| `sewer` | `sewer` | PASS✱ | Stage 5.5 fix; see §4.2 note |
| `utilities` | `*property_utilities` (array) | PASS | Maps to `property_utilities` (Landlord) vs `utilities` (Seller) |
| `furnished` | `tenant_require` | PASS | Landlord maps to `tenant_require`; Seller maps to `building_features` |
| `roof_type` | `*roof_type` (array) | PASS | |
| `exterior_construction` | `*exterior_construction` (array) | PASS | |
| `foundation` | `*foundation` (array) | PASS | |
| `interior_features` | `*interior_features` (array) | PASS | |

> **Landlord JSON-array EAV save/reload:** `LandlordAgentAuction::getGetAttribute()` auto-decodes JSON meta values, which historically could cause `json_decode()` in `loadDraft()` to receive an already-decoded array and throw `\TypeError`. That double-decode path is **NOT observed in this environment** — all landlord array-prop round-trip tests exercise the happy path and value assertions run. The `landlordSaveReload()` helper no longer catches `TypeError`; if the bug ever returns, the test fails immediately.

### 4.8 Commercial Sale Specific — Form 3

| Canonical Key | Livewire Prop | Form 3 | Stage 6 test |
|---------------|--------------|--------|--------------|
| `building_size_sqft` | `total_square_feet` | PASS | `test_seller_save_reload_commercial_building_size_roundtrip` |
| `ceiling_height_ft` | `ceiling_height` | PASS | (scalar EAV — same pattern) |
| `parking_spaces_count` | `garage_parking_spaces` | PASS | (scalar — same native col as garage_spaces) |
| `net_operating_income` | `minimum_annual_net_income` | PASS | (also used by Form 4 and 6) |
| `cap_rate` | `minimum_cap_rate` | PASS | `test_seller_save_reload_commercial_cap_rate_roundtrip` |
| `building_features_list` | `*building_features` (array) | PASS | `test_seller_save_reload_commercial_building_features_array_roundtrip` |
| `current_use_list` | `*current_use` (array) | PASS | `test_seller_save_reload_commercial_current_use_array_roundtrip` |

### 4.9 Business Opportunity Specific — Form 4

| Canonical Key | Livewire Prop | Form 4 | Stage 6 test |
|---------------|--------------|--------|--------------|
| `business_type` | `business_type` | PASS | `test_seller_save_reload_business_type_scalar_roundtrip` |
| `annual_revenue` | `annual_revenue` | PASS | `test_seller_save_reload_annual_revenue_scalar_roundtrip` |
| `annual_net_income_business` | `minimum_annual_net_income` | PASS | (same prop as net_operating_income) |
| `employee_count` | `employee_count` | PASS | `test_seller_save_reload_employee_count_scalar_roundtrip` |
| `inventory_included` | — | UNSUPPORTED | No boolean Livewire prop on SellerOfferListing |
| `seller_financing_yn` | — | UNSUPPORTED | `offered_financing` is multi-select; boolean from MLS not applicable |

### 4.10 Vacant Land Specific — Form 5

| Canonical Key | Livewire Prop | Form 5 | Stage 6 test |
|---------------|--------------|--------|--------------|
| `lot_size_acres` | `total_acreage` | PASS | `test_seller_save_reload_vacant_land_acreage_roundtrip` |
| `lot_dimensions` | `lot_dimensions` | PASS | `test_seller_save_reload_lot_dimensions_scalar_roundtrip` (existing) |
| `zoning` | `zoning` | PASS✱ | `test_seller_save_reload_vacant_land_zoning_roundtrip` |
| `total_parcel_count` | `total_parcel_count` | PASS | (EAV scalar — same as all forms) |

### 4.11 Income / Multifamily Specific — Form 6

| Canonical Key | Livewire Prop | Form 6 | Stage 6 test |
|---------------|--------------|--------|--------------|
| `number_of_units` | `unit_number` | PASS | `test_seller_save_reload_income_unit_number_roundtrip` |
| `gross_annual_income` | `gross_annual_income` | PASS | `test_seller_save_reload_income_gross_annual_income_roundtrip` |
| `annual_operating_expenses` | `annual_operating_expenses` | PASS | `test_seller_save_reload_income_annual_operating_expenses_roundtrip` |
| `cap_rate` | `minimum_cap_rate` | PASS | (same as Commercial Sale; §4.8) |
| `net_operating_income_raw` | — | UNSUPPORTED | Preview-only; no matching Livewire prop |
| `unit_types_raw` | — | UNSUPPORTED | Preview-only; no matching Livewire prop |
| `occupancy_rate_raw` | — | UNSUPPORTED | Preview-only; no matching Livewire prop |

### 4.12 Stage 8/9 Audit — Public View and Ask AI Access

Audit of `offer-listing/{role}/view.blade.php` (Stage 8) and `AskAiContextBuilderService::extractFactualFields()` (Stage 9) for every multi-select field in Seller and Landlord pipelines. "Pre-existing gap" = the same gap affects manually-created listings, not just MLS-imported ones.

#### 4.12a Seller multi-select fields (Forms 1–3, 5–6)

| Canonical Key | Meta key saved | Stage 8 public view reads | Stage 8 result | Stage 9 Ask AI reads | Stage 9 result |
|---------------|---------------|--------------------------|----------------|----------------------|----------------|
| `roof_type` | `roof_type` | `$arr('roof_type')` | **PASS** | `$infoGet('roof_type')` | **PASS** |
| `exterior_construction` | `exterior_construction` | `$arr('exterior_construction')` | **PASS** | `$infoGet('exterior_construction')` | **PASS** |
| `foundation` | `foundation` | `$arr('foundation')` | **PASS** | `$infoGet('foundation')` | **PASS** |
| `heating_fuel` | `heating_and_fuel` | `$arr('heating_and_fuel')` | **PASS** | `$infoGet('heating_and_fuel')` | **PASS** |
| `air_conditioning` | `air_conditioning` | `$arr('air_conditioning')` | **PASS** | `$infoGet('air_conditioning')` | **PASS** |
| `water` | `water` | `$arr('water')` | **PASS** | `$infoGet('water')` | **PASS** |
| `sewer` | `sewer` | `$arr('sewer')` | **PASS** | `$infoGet('sewer')` | **PASS** |
| `utilities` | `utilities` | `$arr('utilities')` | **PASS** | `$infoGet('utilities')` | **PASS** |
| `appliances` | `appliances` | `$arr('appliances')` | **PASS** | `$infoGet('appliances')` | **PASS** |
| `interior_features` | `interior_features` | — not rendered on public view | **PUBLIC VIEW GAP** | `$infoGet('interior_features')` | **PASS** |
| `water_access` | `water_access` | — not rendered on seller public view | **PUBLIC VIEW GAP** | `$infoGet('water_access')` | **PASS** |
| `water_view` | `water_view` | `$arr('view_preference')` ← wrong key | **PUBLIC VIEW GAP** | `$infoGet('water_view') ?: view_preference` | **PASS✱** (P5 fix) |

> **Seller interior_features / water_access PUBLIC VIEW GAP:** Both fields are collected in the seller form and saved/reloaded correctly (stages 1–7 PASS), but `offer-listing/seller/view.blade.php` does not render them. The seller public view only shows structural fields (roof, exterior, foundation, heating, AC, water, sewer, utilities) in its property details section and does not have dedicated display sections for interior features or water access. Pre-existing gap, not MLS-specific. Ask AI (Stage 9) reads both correctly.
>
> **Seller water_view PUBLIC VIEW GAP (pre-existing):** The seller form has two separate props: `$water_view` (specific water body types: Bay/Harbor, Lake, Canal, etc.) and `$view_preference` (general scenic view). Both save to their respective meta keys. The seller public view only renders `$arr('view_preference')` in its "View Preferences" section — `water_view` meta is never read by the public view. Pre-existing gap affecting all seller listings. Ask AI was also reading `view_preference` (WRONG) — fixed by P5 to read `water_view` first with `view_preference` as fallback.

#### 4.12b Landlord multi-select fields (Forms 7a/7b)

| Canonical Key | Meta key saved | Stage 8 public view reads | Stage 8 result | Stage 9 Ask AI reads | Stage 9 result |
|---------------|---------------|--------------------------|----------------|----------------------|----------------|
| `desired_lease_length` | `desired_lease_length` | `$arr('desired_lease_length')` (line 1168) | **PASS** | `$infoGet('desired_lease_length')` | **PASS** |
| `terms_of_lease` (7a → `desired_lease_length`) | `desired_lease_length` (after P5 routing) | same as above | **PASS✱** | `$infoGet('desired_lease_length')` | **PASS✱** |
| `terms_of_lease` (7b commercial) | `terms_of_lease` | `$arr('terms_of_lease')` (line 1197) | **PASS** | `$infoGet('terms_of_lease')` | **PASS** |
| `rent_includes` | `rent_includes` | `$arr('rent_includes')` (line 1188) | **PASS** | `$infoGet('rent_includes')` | **PASS** |
| `tenant_pays` | `tenant_pays` | `$arr('tenant_pays')` (line 1261) | **PASS** | `$infoGet('tenant_pays')` | **PASS** |
| `water_access` | `water_access` | `$arr('water_access')` (line 1140) | **PASS** | `$infoGet('water_access')` | **PASS** |
| `interior_features` | `interior_features` | `$arr('interior_features')` (line 1529) | **PASS** | `$infoGet('interior_features')` | **PASS** |
| `roof_type` | `roof_type` | `$arr('roof_type')` (line 1526) | **PASS** | `$infoGet('roof_type')` | **PASS** |
| `exterior_construction` | `exterior_construction` | `$arr('exterior_construction')` (line 1527) | **PASS** | `$infoGet('exterior_construction')` | **PASS** |
| `foundation` | `foundation` | `$arr('foundation')` (line 1528) | **PASS** | `$infoGet('foundation')` | **PASS** |
| `appliances` | `appliances` | `$arr('appliances')` (line 1305) | **PASS** | `$infoGet('appliances')` | **PASS** |
| `heating_fuel` | `heating_fuel` | — not rendered on landlord public view | **PUBLIC VIEW GAP** | `$infoGet('heating_fuel')` | **PASS** |
| `air_conditioning` | `air_conditioning` | — not rendered on landlord public view | **PUBLIC VIEW GAP** | `$infoGet('air_conditioning')` | **PASS** |
| `water` | `water` | — not rendered on landlord public view | **PUBLIC VIEW GAP** | `$infoGet('water')` | **PASS** |
| `sewer` | `sewer` | — not rendered on landlord public view | **PUBLIC VIEW GAP** | `$infoGet('sewer')` | **PASS** |
| `utilities` | `property_utilities` | — not rendered on landlord public view | **PUBLIC VIEW GAP** | `$infoGet('property_utilities')` | **PASS** |
| `water_view` | `water_view` | `$arr('view_preference')` ← wrong key | **PUBLIC VIEW GAP** | `$infoGet('water_view') ?: view_preference` | **PASS✱** (P5 fix) |

> **Landlord structural field PUBLIC VIEW GAPs (pre-existing):** `offer-listing/landlord/view.blade.php` focuses on rental terms and does not include a dedicated structural details section for heating, cooling, water type, sewer, or utilities. These are collected in the form and saved/reloaded correctly (stages 1–7 PASS), but the landlord public view was designed around rental-specific information rather than property structure. Ask AI (Stage 9) reads all structural fields correctly via `extractFactualFields()`. Pre-existing gap, not MLS-specific.
>
> **Landlord water_view PUBLIC VIEW GAP (pre-existing):** Same architecture as seller — `$water_view` and `$view_preference` are separate form props/meta keys; the landlord public view reads `view_preference` not `water_view`. Ask AI was also reading `view_preference` (WRONG) — fixed by P5 to read `water_view` first with `view_preference` as fallback.

---

## 5. Stage 5.5 — Select2 Rehydration Fix Detail

| Component | Symptom before fix | Fix applied |
|-----------|-------------------|-------------|
| `HasMlsImport::applyImportedFields()` | No browser event after apply; JS had no trigger | `dispatchBrowserEvent('mlsApplied')` added after clearing preview state |
| Seller blade | Multi-select fields (air_conditioning, appliances, sewer, water, utilities, etc.) not refreshed | `window.addEventListener('mlsApplied', ...)` upgraded to **two-pass** (200 ms + 600 ms): each pass calls `initializeMlsPropertyMultiSelects()` then `rehydrateSelect2MultiFields()` — handles newly-rendered DOM sections (e.g. when `property_type` was empty during Apply so the sewer/water section was not yet in the DOM at the 100 ms pass) |
| Landlord blade | Same symptom for 19 `mlsIdFields` + 8 `regularFields` | `window.addEventListener('mlsApplied', ...)` upgraded to same two-pass pattern; extracted into named `_landlordMlsRehydrate()` function; calls `initializeFullService()` first if defined |

**End-to-end persistence tests** (parse → preview → apply → saveDraft → loadDraft) cover 6 array-prop fields across both roles:

| Test method | Canonical key | Role | `is_array_prop` check | apply check | save/reload check |
|-------------|---------------|------|-----------------------|-------------|-------------------|
| `test_stage5_5_sewer_apply_save_reload_end_to_end` | `sewer` | Seller | ✓ | ✓ | ✓ |
| `test_stage5_5_seller_roof_type_apply_save_reload_end_to_end` | `roof_type` | Seller | ✓ | ✓ | ✓ |
| `test_stage5_5_seller_air_conditioning_apply_save_reload_end_to_end` | `air_conditioning` | Seller | ✓ | ✓ | ✓ |
| `test_stage5_5_seller_water_apply_save_reload_end_to_end` | `water` | Seller | ✓ | ✓ | ✓ |
| `test_stage5_5_landlord_heating_fuel_apply_save_reload_end_to_end` | `heating_fuel` | Landlord | ✓ | ✓ | ✓ (or TypeError guard) |
| `test_stage5_5_landlord_rent_includes_apply_save_reload_end_to_end` | `rent_includes` | Landlord | ✓ | ✓ | ✓ (or TypeError guard) |

The Landlord tests use `landlordSaveReload()` which catches the double-decode `TypeError` if it recurs; in this environment the bug is effectively resolved — `ensureArray()` guards handle already-decoded arrays, and `is_string()` guards on scalar fields prevent re-decoding. Value assertions run successfully.

The Select2 visual rehydration (DOM layer) is browser-only and cannot be asserted in Feature tests.

---

## 6. Gap Fixes Applied (Task #2729)

### P1 — Label-Variant Parser Gaps

| ID | Field | Root cause | Fix |
|----|-------|-----------|-----|
| P1-1 | `minimum_security_deposit` | Only 3-word "Minimum Security Deposit:" matched | Added 2-word "Security Deposit:" bare label pattern |
| P1-2 | `association_fee_amount` | Only "HOA Fee:" and "Association Fee:" matched | Added "HOA Dues:" variant |
| P1-3 | `tax_id` | No Folio label support | Added "Folio Number:" and "Folio #:" (Miami-Dade/Broward format) |

### P2 — Edge-Case Gaps

| ID | Field | Root cause | Fix |
|----|-------|-----------|-----|
| P2-1 | `terms_of_lease` | Only "Terms of Lease:" matched | Added "Lease Terms:" reversed-label variant |
| P2-2 | `zoning` | Char class `[A-Za-z0-9\-\/]{1,30}` — single token only | Expanded to `[A-Za-z0-9\-\/][A-Za-z0-9\-\/ ]{0,59}` + `rtrim()` |
| P2-3 | `flood_zone_date` | Only numeric and ISO date formats matched | Extended to capture text-month ("January 15, 2020", "Mar 8 2019") |
| P2-4 | `water_frontage`, `waterfront_feet` | Stale comments; zero-value falsy bug | Comments corrected; `!== null` guard added for zero-value strings |

### Persistence Fix — Discovered During Stage 6 Round-Trip Audit

| Field | Root cause | Fix |
|-------|-----------|-----|
| `business_type` | `public $business_type` declared on `SellerOfferListing` but never written to `saveMeta()` or loaded in `loadDraft()`; MLS import set the prop but it was silently lost on save | Added `$auction->saveMeta('business_type', $this->business_type)` in the "Financial Details — Business" block and `$this->business_type = $auction->get->business_type ?? ''` in `loadDraft()`; confirmed by `test_seller_save_reload_business_type_scalar_roundtrip` |

### P3 — Fifth-Pass Fixes (Stage 5.5 Race Condition + Description Strip + Test Constant)

| ID | Area | Root cause | Fix |
|----|------|-----------|-----|
| P3-1 | Stage 5.5 JS — Seller blade | Single 100 ms rehydration pass fires before Livewire has re-rendered DOM sections whose visibility depends on `property_type` (e.g. sewer/water section hidden until a property type is selected). Select2 not yet attached to elements → `rehydrateSelect2MultiFields()` silently skips them | Upgraded to **two-pass** (200 ms + 600 ms), each calling `initializeMlsPropertyMultiSelects()` before `rehydrateSelect2MultiFields()`; the 600 ms pass catches late-rendered sections |
| P3-2 | Stage 5.5 JS — Landlord blade | Same race condition on landlord's 27-field rehydration loop | Same two-pass approach; extracted into named `_landlordMlsRehydrate()` IIFE function; `initializeFullService()` called first if defined |
| P3-3 | Description address-strip regex | Regex `\s+` after state+ZIP only matched whitespace separators; period (e.g. `33601. Welcome…`), comma, or dash between the ZIP and the narrative body caused the full header block to remain in the parsed description | Changed `\s+` to `[\s.,;:!?\-–—]+` so all common separator punctuation after the ZIP triggers the strip |
| P3-4 | `MlsMultiSelectCompatibilityTest` `UTILITIES_OPTIONS` constant | Constant included 7 options (`BB/HS Internet Capable`, `Electrical Nearby`, `Emergency Power`, `Sewer Nearby`, `Telephone Nearby`, `Utility Pole`, `Water Nearby`) that exist in MLS source data but are **not** blade options in the seller `$utilities` select list → tests were asserting compatibility against non-existent options (false positives) | Corrected to match the exact seller residential blade option list; removed the 7 invalid tokens with explanatory comment |

### P5 — Seventh-Pass Fixes (terms_of_lease Routing + Ask AI water_view Key)

| ID | Area | Root cause | Fix |
|----|------|-----------|-----|
| P5-1 | `HasMlsImport::applyImportedFields()` — landlord residential | MLS residential rental exports produce `terms_of_lease` canonical key with DURATION values ('Month-to-Month', '1 Year', etc.). The landlord `$terms_of_lease` prop holds COMMERCIAL lease TYPES (Gross Lease, Net Lease); duration values never matched a Select2 option → silently dropped. The residential blade uses `$residential_lease_term_options` on the `$desired_lease_length` prop. | Added an apply-time routing guard in `applyImportedFields()`: when `$canonicalKey === 'terms_of_lease'` AND `$role === 'landlord'` AND `$this->property_type === 'Residential Property'`, `$propName` is overridden to `'desired_lease_length'`. Commercial listings (Form 7b) are unaffected. |
| P5-2 | `AskAiContextBuilderService::extractFactualFields()` — seller + landlord `water_view` | Context builder read `view_preference` meta key for `water_view` output in both seller and landlord sections. Comment cited a "Live-DB audit (June 2026)" that said `water_view` does not exist — but the audit was on legacy rows; the offer-listing wizard and MLS import both save `water_view` meta. Ask AI could never retrieve MLS-imported water view data. | Changed both seller and landlord `water_view` reads to `$infoGet('water_view') ?: $infoGet('view_preference')` — reads the Livewire/MLS-created `water_view` key first and falls back to `view_preference` for legacy rows. |

### P4 — Sixth-Pass Fix (Property Type Apply-Time Normalization — Root Cause of Sewer DISPLAY FAILURE)

| ID | Area | Root cause | Fix |
|----|------|-----------|-----|
| P4-1 | `HasMlsImport::applyImportedFields()` | MLS parser captures `property_type` verbatim as `"Residential Property"` (with ` Property` suffix). Seller blade conditionals check `$property_type === 'Residential'` (no suffix) → the entire sewer/water/utilities section never rendered after Apply Selected, making `#sewer_residential` count=0 in every browser test. Landlord form uses the opposite convention: its blade uses `'Residential Property'` (full form). Previous P3-1/P3-2 two-pass JS fix was a band-aid that assumed the section eventually renders — the actual cause was that it **never** rendered. | Added `HasMlsImport::normalizePropertyTypeForRole(string $value, string $role)` private static method called inside `applyImportedFields()` before the hasExisting guard. Seller/buyer/tenant: maps verbose MLS forms → short form (`"Residential Property"` → `'Residential'`, `"Commercial Property"` → `'Commercial'`, `"Business Opportunity"` → `'Business'`, `"Income/Multifamily"` → `'Income'`, `"Vacant Land Sale"` → `'Vacant Land'`, `"Single Family Residence"` → `'Residential'`). Landlord: maps to full form (`'Residential Property'`, `'Commercial Property'`) to match the landlord blade options. Browser-verified: `#sewer_residential` count=1 after Apply. |

### Bonus labelStop Extensions

| Entry added | Protects against |
|-------------|-----------------|
| `\|Folio\b` | Captures (e.g. city, carport) running past folio/parcel labels |
| `\|Lease\s+Terms\b` | Captures running past "Lease Terms:" |
| `\|Water\b(?=\s*:)` | Sewer and other captures running past bare "Water:" label; colon lookahead prevents firing on "Waterfront:", "Water Access:", etc. |

---

## 7. Accepted Gaps / Known Limitations

| Canonical Key | Reason omitted |
|---------------|----------------|
| `mls_number` | No matching Livewire prop on any role |
| `directions` | Navigation-only text; no form destination |
| `lot_size_sqft` | Forms use acres (`total_acreage`); no sqft Livewire prop |
| `inventory_included` | No boolean prop; `inventory_value` is a dollar-amount field |
| `seller_financing_yn` | `offered_financing` is multi-select, not a boolean |
| `net_operating_income_raw` / `unit_types_raw` / `occupancy_rate_raw` | Preview-only; no Livewire prop |
| Buyer price → buyer max budget | MLS "List Price" is a listing price; meaningless as a buyer maximum |
| Compact inline string ambiguity | In MLS text with no pipe/newline separators, the space-allowing char class in `zoning` may capture trailing label words; the labelStop boundary resolves this for all real Stellar MLS pipe-delimited exports |
| Select2 option-value mismatch | Parsed strings not matching a Select2 option exactly are silently ignored; not introduced by this task |
| Landlord double-decode EAV bug | `getGetAttribute()` + `json_decode()` in `loadDraft()` causes TypeError on JSON-array fields — **EFFECTIVELY FIXED**: `ensureArray()` handles already-decoded arrays; `is_string()` guards on scalar fields; `landlordSaveReload()` try/catch remains as a safety net in tests |
| Seller `interior_features` / `water_access` PUBLIC VIEW GAP | Stages 1–7 PASS; `offer-listing/seller/view.blade.php` does not render these fields. Pre-existing gap affecting all seller listings (not MLS-specific). Ask AI Stage 9 reads both correctly. Follow-up task #2740 tracks adding these display sections to the seller public view. |
| Seller + Landlord `water_view` PUBLIC VIEW GAP | Stages 1–7 PASS; both public views render `view_preference` meta but not `water_view` meta. These are separate form props with separate meta keys. Pre-existing gap. Ask AI Stage 9 now reads `water_view` correctly (P5-2 fix). Follow-up task #2741 tracks adding water_view to both public view blades. |
| Landlord `heating_fuel` / `air_conditioning` / `water` / `sewer` / `property_utilities` PUBLIC VIEW GAP | Stages 1–7 PASS; `offer-listing/landlord/view.blade.php` focuses on rental terms and does not render structural/utility details. Pre-existing architectural gap. Ask AI Stage 9 reads all correctly. Follow-up task #2742 tracks adding a structural details section to the landlord public view. |

---

## 8. Regression Test Coverage

### Task #2729 new tests

| File | Tests | Coverage |
|------|-------|---------|
| `MlsGapFixesRegressionTest.php` | 45 | All P1/P2 gap fixes + Stage 5.5 parser smoke + P3-3 description address-strip (5 new) + P4-1 property_type normalization (10 new) + P5 apply-time routing (3 new: P3-1a landlord residential routes to desired_lease_length / P3-1b landlord commercial stays / P3-1c seller not rerouted) |
| `MlsLiveImportAuditTest.php` (appended) | 20 | Forms 3–7 unique field save+reload round-trips; Stage 5.5 apply→save→reload (6 fields, 2 roles) |
| `MlsMultiSelectCompatibilityTest.php` (new) | 64 | Exact-match blade-option audit for **every** `*`-prefixed field in seller + landlord field maps; zero OPEN fields remain; STRICT checks cover appliances, interior_features, water_access, water_view (moved from open), plus all landlord fields (air_conditioning, heating_fuel, sewer, water, utilities→property_utilities); role-specific constants for fields whose seller/landlord option lists differ; routing fix confirmation test for `terms_of_lease` residential (was documentary failure test, now PASS); field-map inventory completeness checks |

### Task #2729 new test detail — Stage 6 round-trips

| Test method (in `MlsLiveImportAuditTest`) | Canonical key | Form | Stage |
|-------------------------------------------|---------------|------|-------|
| `test_seller_save_reload_commercial_building_size_roundtrip` | `building_size_sqft` | 3 | 6 |
| `test_seller_save_reload_commercial_cap_rate_roundtrip` | `cap_rate` | 3 | 6 |
| `test_seller_save_reload_commercial_building_features_array_roundtrip` | `building_features_list` | 3 | 6 |
| `test_seller_save_reload_commercial_current_use_array_roundtrip` | `current_use_list` | 3 | 6 |
| `test_seller_save_reload_business_type_scalar_roundtrip` | `business_type` | 4 | 6 |
| `test_seller_save_reload_annual_revenue_scalar_roundtrip` | `annual_revenue` | 4 | 6 |
| `test_seller_save_reload_employee_count_scalar_roundtrip` | `employee_count` | 4 | 6 |
| `test_seller_save_reload_vacant_land_acreage_roundtrip` | `lot_size_acres` | 5 | 6 |
| `test_seller_save_reload_vacant_land_zoning_roundtrip` | `zoning` (P2-2) | 5 | 6 |
| `test_seller_save_reload_income_gross_annual_income_roundtrip` | `gross_annual_income` | 6 | 6 |
| `test_seller_save_reload_income_annual_operating_expenses_roundtrip` | `annual_operating_expenses` | 6 | 6 |
| `test_seller_save_reload_income_unit_number_roundtrip` | `number_of_units` | 6 | 6 |

### Task #2729 new test detail — Stage 5.5 end-to-end (preview→apply→save→reload)

| Test method (in `MlsLiveImportAuditTest`) | Canonical key | Role | Stages |
|-------------------------------------------|---------------|------|--------|
| `test_stage5_5_sewer_apply_save_reload_end_to_end` | `sewer` | Seller | 5a+5b+5.5+6+7 |
| `test_stage5_5_seller_roof_type_apply_save_reload_end_to_end` | `roof_type` | Seller | 5a+5b+5.5+6+7 |
| `test_stage5_5_seller_air_conditioning_apply_save_reload_end_to_end` | `air_conditioning` | Seller | 5a+5b+5.5+6+7 |
| `test_stage5_5_seller_water_apply_save_reload_end_to_end` | `water` | Seller | 5a+5b+5.5+6+7 |
| `test_stage5_5_landlord_heating_fuel_apply_save_reload_end_to_end` | `heating_fuel` | Landlord | 5a+5b+5.5+6+7 |
| `test_stage5_5_landlord_rent_includes_apply_save_reload_end_to_end` | `rent_includes` | Landlord | 5a+5b+5.5+6+7 |

### Task #2729 new test detail — Multi-select option compatibility (`MlsMultiSelectCompatibilityTest`)

| Provider / test | Fields covered | Constraint type |
|-----------------|---------------|-----------------|
| `sellerStrictCompatibilityProvider` (21 cases) | `sewer`, `water`, `roof_type`, `exterior_construction`, `foundation`, `heating_fuel`, `air_conditioning`, `utilities`, `current_use_list`, `building_features_list` | STRICT — tokens must ∈ component `in:` rule option list |
| `landlordStrictCompatibilityProvider` (6 cases) | `roof_type`, `exterior_construction`, `foundation` | STRICT |
| `openSelectCompatibilityProvider` (15 cases) | `appliances`, `interior_features`, `water_access`, `water_view` (seller); `appliances`, `air_conditioning`, `heating_fuel`, `sewer`, `water`, `utilities`, `rent_includes`, `tenant_pays`, `terms_of_lease`, `water_access`, `interior_features` (landlord) | OPEN — tokens must be non-empty strings |
| `test_all_seller_array_prop_fields_are_classified_in_this_audit` | All `*` fields in `MlsFieldMap::forRole('seller')` | Inventory completeness |
| `test_all_landlord_array_prop_fields_are_classified_in_this_audit` | All `*` fields in `MlsFieldMap::forRole('landlord')` | Inventory completeness |

### Pre-existing suites that must remain green

| Suite | Focus |
|-------|-------|
| `MlsListingImportServiceTest.php` | Core parser unit tests + SSRF guard |
| `MlsParserBleedRegressionTest.php` | Boundary-bleed regression |
| `MlsNewFieldsImportTest.php` | Extended field coverage (waterfront, flood zone date, etc.) |
| `MlsIncomeFieldsImportTest.php` | Income / Multifamily specific fields |
| `MlsCommercialLeaseFieldsTest.php` | Commercial Lease / Landlord specific fields |
| `MlsImportWorkflowTest.php` | Pipeline integration (apply, preview) |
| `MlsPreviewGatingTest.php` | Preview gating and coverage reporter |
| `MlsRealListingRegressionTest.php` | Real-listing inline regression |
| `MlsLiveImportAuditTest.php` | Live audit + Stage 6 save/reload |
| `SellerResidentialAuditTest.php` | Seller Residential specific audit |

**557 tests pass + 0 warnings as of 2026-06-14 Stage 8/9 audit (seventh pass).** The previous 1 warning (`test_landlord_terms_of_lease_residential_term_is_normalization_failure`) is gone — replaced by a PASS test (`test_landlord_terms_of_lease_residential_month_to_month_is_valid_desired_lease_length`) now that the routing fix is in place. +3 new P5 routing regression tests added to `MlsGapFixesRegressionTest`. All `*`-prefixed array-prop fields in the seller and landlord field maps are covered by exact-match blade-option assertions. The last documented NORMALIZATION FAILURE (`terms_of_lease` Form 7a) is now PASS✱. Zero normalization failures remain. Stage 8 (public view) and Stage 9 (Ask AI) audit complete — all pre-existing PUBLIC VIEW GAPs documented in §4.12; Ask AI water_view reading fixed (P5-2). **Browser-verified (sixth pass):** After Apply Selected with `Property Type: Residential Property` on `/dev/offer-listing/seller`, Playwright confirms `#sewer_residential` count=1 — the sewer section renders correctly.

---

## 9. Files Changed (Task #2729)

| File | Change |
|------|--------|
| `app/Services/ListingImport/MlsListingImportService.php` | P1-1/P1-2/P1-3/P2-1/P2-2/P2-3/P2-4 parser fixes; `Folio\b`, `Lease\s+Terms\b`, `Water\b(?=\s*:)` added to `$labelStop` |
| `app/Http/Livewire/OfferListing/Concerns/HasMlsImport.php` | `dispatchBrowserEvent('mlsApplied')` after Apply Selected |
| `resources/views/livewire/offer-listing/seller/offer-seller-listing.blade.php` | `mlsApplied` JS listener → `rehydrateSelect2MultiFields()` |
| `resources/views/livewire/offer-listing/landlord/offer-landlord-listing.blade.php` | `mlsApplied` JS listener → inline Select2 rehydration |
| `tests/Feature/ListingImport/MlsGapFixesRegressionTest.php` | 27 regression tests covering P1/P2 fixes + Stage 5.5 parser smoke |
| `tests/Feature/ListingImport/MlsLiveImportAuditTest.php` | 15 new Stage 6 round-trip tests: Forms 3–7 form-unique fields + Stage 5.5 end-to-end |
| `docs/audits/MLS_FULL_PIPELINE_MATRIX_ALL_7_FORMS.md` | This document |

### Fifth-pass additional changes (P3)

| File | Change |
|------|--------|
| `resources/views/livewire/offer-listing/seller/offer-seller-listing.blade.php` | `mlsApplied` handler upgraded from single 100 ms pass to two-pass (200 ms + 600 ms); each pass calls `initializeMlsPropertyMultiSelects()` then `rehydrateSelect2MultiFields()` |
| `resources/views/livewire/offer-listing/landlord/offer-landlord-listing.blade.php` | Same two-pass upgrade; logic extracted into `_landlordMlsRehydrate()` named IIFE function |
| `app/Services/ListingImport/MlsListingImportService.php` | Description header-strip regex `\s+` → `[\s.,;:!?\-–—]+` after state+ZIP to handle period/dash/comma separators |
| `tests/Feature/ListingImport/MlsMultiSelectCompatibilityTest.php` | `UTILITIES_OPTIONS` constant corrected — removed 7 tokens not present in seller blade option list |
| `tests/Feature/ListingImport/MlsGapFixesRegressionTest.php` | 5 description address-strip regression tests added (P3-3 coverage) |

### Sixth-pass additional changes (P4)

| File | Change |
|------|--------|
| `app/Http/Livewire/OfferListing/Concerns/HasMlsImport.php` | Added `normalizePropertyTypeForRole()` private static method; called in `applyImportedFields()` for `property_type` canonical key — maps verbose MLS form to role-specific form option values |
| `tests/Feature/ListingImport/MlsGapFixesRegressionTest.php` | 10 property_type normalization regression tests added (P4-1 coverage) via `ReflectionMethod` on the private static method |
| `docs/audits/MLS_FULL_PIPELINE_MATRIX_ALL_7_FORMS.md` | P4 section added; sewer PASS✱ note updated with root-cause explanation; double-decode status corrected to EFFECTIVELY FIXED; test count updated 544→554 |

### Seventh-pass additional changes (P5 — Stage 8/9 audit)

| File | Change |
|------|--------|
| `app/Http/Livewire/OfferListing/Concerns/HasMlsImport.php` | P5-1: added apply-time routing guard in `applyImportedFields()` — redirects `terms_of_lease` canonical key to `desired_lease_length` prop when `role=landlord` AND `property_type='Residential Property'` |
| `app/Services/AskAi/AskAiContextBuilderService.php` | P5-2: updated `water_view` meta key reads in both seller and landlord `extractFactualFields()` sections to `$infoGet('water_view') ?: $infoGet('view_preference')` — reads the Livewire/MLS-created meta key first, falls back to `view_preference` for legacy rows |
| `tests/Feature/ListingImport/MlsGapFixesRegressionTest.php` | 3 P5 routing regression tests added: P3-1a (landlord residential routes to desired_lease_length), P3-1b (landlord commercial stays on terms_of_lease), P3-1c (seller not rerouted) — use anonymous class with HasMlsImport trait |
| `tests/Feature/ListingImport/MlsMultiSelectCompatibilityTest.php` | `test_landlord_terms_of_lease_residential_term_is_normalization_failure()` (warning-issuing) replaced by `test_landlord_terms_of_lease_residential_month_to_month_is_valid_desired_lease_length()` (PASS); warning count 1→0 |
| `docs/audits/MLS_FULL_PIPELINE_MATRIX_ALL_7_FORMS.md` | P5 section added; Stage 8/9 added to pipeline stages; §4.12 Stage 8/9 audit tables added; taxonomy updated with PUBLIC VIEW GAP / ASK AI GAP codes; §7 known limitations updated; test count 554→557 |
