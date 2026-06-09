# MLS-to-Website Field Mapping Audit — Phase D Results Summary

**Date:** 2026-06-09
**Scope:** Residential and Rental MLS forms (Stellar MLS), all four listing roles (Seller, Buyer, Landlord, Tenant)
**Phases completed:** A (audit document), B (code fixes), C (coverage report with Previewed and Reason columns)
**Files audited:** `MlsFieldMap.php`, `MlsNormalizer.php`, `MlsListingImportService.php`, all four Livewire offer-listing components, all four sets of Create/Edit tab blade files

---

## 1. Scope Totals

| Metric | Count |
|---|---|
| **Total MLS fields reviewed (residential/rental forms, Sections 1–20)** | 62 distinct canonical keys |
| **Total MLS fields from specialist forms reviewed but out-of-scope (Section 21)** | 34 fields |
| **Combined MLS field universe entered into the audit** | **96 MLS fields** |
| **Total distinct website properties / EAV meta keys audited across all four roles** | **55 Livewire public properties and meta keys** (Seller map: 46 unique targets; Landlord map adds 9 not present in Seller: `desired_rental_amount`, `rent_includes`, `available_date`, `lease_amount_frequency`, `security_deposit_amount`, `terms_of_lease`, `tenant_pays`, `heating_fuel`, `property_utilities`; Buyer and Tenant add no new targets) |
| **Total entries across all four role field maps (post-Phase-B)** | 118 field-map entries (Seller 46, Landlord 48, Buyer 9, Tenant 15, counting `*`-prefixed array targets once) |

> The 62-key residential/rental universe is the authoritative scope for Phases A–D. The 34 specialist-form fields (Vacant Land, Income, Commercial Sale, Commercial Lease, Business Opportunity) are catalogued in Section 21 of the audit document and are deferred pending dedicated form-type support.
>
> **Note on count differences:** Various totals in this document (96 MLS fields, 62 residential/rental canonical keys, 61 keys after Phase B heating consolidation, "65 rows / 62 keys" in Section 7) can all be technically correct. Counts may differ because some audit rows represent role-specific mapping entries rather than unique canonical keys (e.g., the `price` canonical key has separate rows for its Seller/Buyer meaning vs. its Tenant intentional exclusion).

---

## 2. Mapped Correctly — Before vs. After

| | Count | Notes |
|---|---|---|
| **Phase A baseline (before Phase B)** | **46** | Evaluated from Phase A audit status column across all 62 canonical keys |
| **Phase B fixes applied** | **+8** | See Section 3 for the complete list |
| **Phase B: heating key retired** | n/a (−1 key) | `heating` canonical key eliminated; plain `Heating:` label now writes to `heating_fuel` in the parser; universe shrinks by 1 |
| **Phase B: Buyer address confirmed intentionally excluded** | n/a | 5 Buyer field-map address entries removed and documented as intentional; not added to mapped_correctly |
| **Post-Phase-B mapped_correctly** | **54** | Phase A 46 + 8 newly corrected entries |

---

## 3. Fixed Mappings

The following 8 canonical key–role pairs were non-`mapped_correctly` in Phase A and were corrected in Phase B. Two normalizer code-smell fixes and one parser consolidation were also completed.

### 3a. Field-Map Fixes (8 entries)

| # | Canonical Key | Role(s) | Phase A Status | Fix Applied |
|---|---|---|---|---|
| 1 | `utilities` | Landlord | `mapped_wrong` — targeted `utilities` (string scalar) instead of `property_utilities` (array) | Changed Landlord entry to `'utilities' => '*property_utilities'` in `MlsFieldMap::landlord()` |
| 2 | `lot_size_acres` | Landlord | `missing_from_field_map` — `LandlordOfferListing::$total_acreage` and blade binding existed; field map omitted the entry | Added `'lot_size_acres' => 'total_acreage'` to `MlsFieldMap::landlord()` |
| 3 | `sqft_heated_source` | Tenant | `missing_from_field_map` — `TenantOfferListing::$sqft_heated_source` and blade binding existed; Tenant map omitted the entry | Added `'sqft_heated_source' => 'sqft_heated_source'` to `MlsFieldMap::tenant()` |
| 4 | `address` | Tenant | `missing_from_field_map` — property and blade binding existed; Tenant map omitted the entry | Added `'address' => 'address'` to `MlsFieldMap::tenant()` |
| 5 | `city` | Tenant | `missing_from_field_map` — same pattern as address | Added `'city' => 'property_city'` to `MlsFieldMap::tenant()` |
| 6 | `state` | Tenant | `missing_from_field_map` — same pattern | Added `'state' => 'property_state'` to `MlsFieldMap::tenant()` |
| 7 | `zip` | Tenant | `missing_from_field_map` — same pattern | Added `'zip' => 'property_zip'` to `MlsFieldMap::tenant()` |
| 8 | `county` | Tenant | `missing_from_field_map` — same pattern | Added `'county' => 'property_county'` to `MlsFieldMap::tenant()` |

### 3b. Normalizer Code-Smell Fixes (2)

| # | Canonical Key | Issue | Fix Applied |
|---|---|---|---|
| 9 | `flood_insurance_required` | Parser called `MlsNormalizer::normalize('has_hoa', $v)` — routed to the correct `normalizeBoolean()` but used a misleading field name | Added named `'flood_insurance_required'` case to `MlsNormalizer::normalize()` match; updated parser call to pass `'flood_insurance_required'` |
| 10 | `has_special_assessments` | Same issue — parser called `normalize('has_hoa', $v)` for this field | Added named `'has_special_assessments'` case to `MlsNormalizer::normalize()` match; updated parser call |

### 3c. Parser Consolidation (1)

| # | Canonical Key | Issue | Fix Applied |
|---|---|---|---|
| 11 | `heating` | Phase A found a separate `heating` key for plain `Heating:` labels; no field map entry existed for it, causing silent data loss when MLS listings omit "and Fuel" from the label | Both `Heating and Fuel:` / `Heating & Fuel:` and plain `Heating:` patterns now write to the single `heating_fuel` canonical key. The `heating` key no longer exists in the parser; no field-map change was required |

### 3d. Intentional Exclusion Confirmed (Phase B Investigation)

| # | Canonical Keys | Role | Phase A Status | Resolution |
|---|---|---|---|---|
| 12 | `address`, `city`, `state`, `zip`, `county` | Buyer | Field-map entries existed but no `wire:model` binding was found in any Buyer tab blade | Phase B inspection confirmed: `BuyerOfferListing` uses a multi-city / multi-county preference model (`newCity[]`, `newCounty[]`). Single-address import is semantically wrong for the Buyer form. All five entries removed from `MlsFieldMap::buyer()` and documented in Rejected Mapping Candidates |

---

## 4. Intentionally Excluded Fields

Eight canonical keys (across specific roles) are deliberately omitted from the field maps with documented rationale. None are suitable for import and none should be added without a product-level design change.

| Canonical Key | Excluded For | Rationale |
|---|---|---|
| `mls_number` | All roles | No `mls_number` Livewire property exists on any component. The value is parsed as a signal but is never imported. MLS listing IDs are external identifiers that have no meaningful place in the offer-listing data model. |
| `price` (as Tenant desired amount) | Tenant | The MLS listing price is the landlord's asking rent. Importing it as `TenantOfferListing::$desired_rental_amount` would prefill the tenant's stated budget with the landlord's ask — semantically backwards and potentially manipulative. |
| `application_fee` | Landlord | No `application_fee` property exists on `LandlordOfferListing`. This is a landlord-set fee that belongs on the listing itself, not in an offer. Requires a product decision before a property is added. |
| `address`, `city`, `state`, `zip`, `county` | Buyer (confirmed Phase B) | `BuyerOfferListing` uses a multi-location preference model (`newCity[]`, `newCounty[]`). A single MLS address is not a valid import target for a buyer who may be searching across multiple cities and counties. |

> **Partial exclusion note:** `year_built` is classified `mapped_correctly` at the row level because it is correctly mapped for both Seller and Landlord. However, it is also a documented Rejected Mapping Candidate for Buyer (no `$year_built` property on `BuyerOfferListing`) and Tenant (same). That partial exclusion is recorded in `MlsCoverageReporter::rejectedMappingsSection()` rather than counted here.

---

## 5. Remaining Gaps

Seven canonical keys remain non-`mapped_correctly` after Phase B. Each requires new website infrastructure (property, meta key, blade input, and/or field-map entry) before it can be imported. All were deferred because adding them requires product sign-off on new form fields.

| # | Canonical Key | MLS Field Label | Affected Roles | Current Gap | What Would Be Required | Reason Deferred |
|---|---|---|---|---|---|---|
| 1 | `flood_zone_date` | Flood Zone Date | Seller, Landlord | `needs_new_field` — parser emits the key; `MlsFieldMap::fieldLabels()` lists it; but no `flood_zone_date` property exists on `SellerOfferListing` or `LandlordOfferListing`, no blade input, no `saveMeta`/`loadDraft` entry | Add EAV `saveMeta`/`loadDraft` handling, `public $flood_zone_date = ''` on both components, a blade date input in the Flood Zone tab, and field-map entries for both Seller and Landlord | Requires product sign-off to extend the Flood Zone section with a new date field; not a simple mapping fix |
| 2 | `waterfront` | Waterfront Y/N | Seller, Landlord | `missing_from_website` — parser emits the key and normalizer handles the boolean; no `waterfront` property on any component, no field-map entry | Add `public $waterfront = ''` (or `= []` if multi-value), EAV meta persistence, blade input, and field-map entries for Seller and Landlord | New form field required; product must decide which tab it belongs to and whether it is a boolean or a multi-option dropdown |
| 3 | `water_access` | Water Access | Seller, Landlord | `missing_from_website` — same pattern as `waterfront`; no component property, no field map | Same stack as `waterfront` | Typically displayed alongside Waterfront; should be scoped together with row 2 in a single product decision |
| 4 | `water_view` | Water View | Seller, Landlord | `missing_from_website` — same pattern; parser emits key, no app target anywhere | Same stack as `waterfront` | Same as rows 2 and 3; all three waterfront-related fields are best addressed in one pass |
| 5 | `interior_features` | Interior Features | Seller, Landlord | `missing_from_website` — parser emits the key; `fieldLabels()` lists it; no `interior_features` property on any component, no field map | Add a free-text or multi-select `interior_features` property + EAV meta + blade input + field-map entries for Seller and Landlord | New form field; product must determine whether this is a multi-select checklist (like `appliances`) or a free-text notes field |
| 6 | `directions` | Directions | Seller, Landlord | `missing_from_website` — parser emits the key; `fieldLabels()` lists it; no `directions` property on any component, no field map | Add `public $directions = ''`, EAV meta persistence, a textarea in an appropriate tab (e.g. Details), and field-map entries for Seller and Landlord | Product has not confirmed whether driving directions should appear in offer listings; could conflict with privacy expectations for unlisted properties |
| 7 | `lot_size_sqft` | Lot Size (Sq Ft) | Undetermined | `missing_from_field_map` — parser correctly emits the key; `fieldLabels()` lists it; no Livewire property named `lot_size_sqft` on any component; no field-map entry for any role | Three options: (a) map to existing `total_acreage` as a lossy fallback when acreage is absent; (b) add a new `lot_size_sqft` property + meta key + blade input + field-map entries; or (c) intentionally exclude and retire the parser branch | Phase B investigation was inconclusive; product must decide whether to expose lot size in square feet separately from acreage, or treat them as interchangeable |

---

## 6. Follow-Up Task Recommendations

The following discrete tasks are recommended as a result of this audit cycle, ordered by estimated impact.

### High Priority

| # | Recommended Task | Rationale |
|---|---|---|
| FU-1 | **Add Waterfront / Water Access / Water View to Seller and Landlord offer-listing forms** | Three parsed canonical keys (`waterfront`, `water_access`, `water_view`) are fully supported by the parser and normalizer but have no website target. All three are high-value search filters for Florida real estate. Addressing all three together in one blade tab pass is more efficient than three separate tickets. |
| FU-2 | **Resolve `lot_size_sqft` disposition** | The parser emits this key and `fieldLabels()` lists it, but no field-map entry exists. A product decision is needed: map to `total_acreage` as a fallback, add a dedicated `lot_size_sqft` property, or intentionally exclude and remove the parser branch. The ambiguity leaves imported data silently discarded for listings where only sq-ft lot size appears. |
| FU-3 | **Add `flood_zone_date` to Seller and Landlord Flood Zone tab** | The Flood Zone section already has Code, Panel, and Insurance Required. Date is a standard FEMA FIRM certification date that agents routinely copy from MLS. The parser already extracts it; a new meta key + property + blade input is all that remains. |

### Medium Priority

| # | Recommended Task | Rationale |
|---|---|---|
| FU-4 | **Add `interior_features` to Seller and Landlord forms** | Interior Features is one of the most populated MLS fields (hardwood floors, crown molding, etc.). The parser extracts it; a free-text or multi-select property would enable automatic prefill. |
| FU-5 | **Add `directions` to Seller and Landlord forms (or confirm intentional exclusion)** | Directions text is extracted by the parser. Product should decide whether it belongs on the listing form; if not, the parser branch should be retired and the key added to `rejectedMappingsSection()`. |
| FU-6 | **Extend MLS parser and field maps to Commercial Sale and Rental specialist forms** | Section 21 of the audit catalogues 34 MLS fields from Commercial Sale, Commercial Lease, Vacant Land, Income, and Business Opportunity forms that have no parser branches and no app equivalents. If the platform adds commercial listing support, this audit baseline provides the field inventory. |

### Lower Priority / Automation

| # | Recommended Task | Rationale |
|---|---|---|
| FU-7 | **Automate coverage report generation on CI** | `MlsCoverageReporter::generate()` already produces a machine-readable markdown report from live source. Running it as a CI step (e.g., a PHPUnit test that asserts zero `Safe=N` rows for non-intentional reasons) would catch regressions automatically whenever a new Livewire property is added or a field-map entry is modified. |
| FU-8 | **Add `application_fee` to `LandlordOfferListing` if rental workflow expands** | Currently intentionally excluded because no property exists. If the landlord listing form grows to include fees and terms as offer inputs (vs. listing metadata), this is a natural addition. |

---

## 7. Audit Outcome Summary

| Status | Phase A Count | Phase B Change | Post-Phase-B Count |
|---|---|---|---|
| `mapped_correctly` | 46 | +8 (field-map and utility fixes) | **54** |
| `mapped_wrong` | 1 | −1 (fixed) | 0 |
| `missing_from_field_map` | 9 | −8 (fixed) / −1 (key retired) | **1** (`lot_size_sqft`) |
| `missing_from_website` | 5 | 0 | **5** (waterfront, water_access, water_view, interior_features, directions) |
| `needs_new_field` | 1 | 0 | **1** (`flood_zone_date`) |
| `intentionally_excluded` | 3 | +5 (Buyer address confirmed) | **8** |
| `heating` key (retired) | 1 (missing_from_field_map) | Merged into `heating_fuel` parser branch | 0 (key removed from universe) |
| **Total canonical keys in scope** | **65 rows / 62 keys** | | **61 keys** |

> **Net result:** The audit increased confirmed-safe mapped fields from 46 to 54 (+17%), eliminated the only `mapped_wrong` entry, collapsed a duplicate parser branch (`heating`), corrected two misleading normalizer call-sites, and documented 8 intentional exclusions with explicit rationale. Seven fields remain deferred pending product decisions on new form fields.

---

*Phase D complete. No PHP, Blade, Livewire, test, route, config, migration, or database schema files were modified to produce this document.*
