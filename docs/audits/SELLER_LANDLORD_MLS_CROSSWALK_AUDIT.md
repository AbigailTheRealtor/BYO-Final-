# Seller & Landlord ↔ Stellar MLS Crosswalk Audit

**Date:** 2026-06-10  
**Scope:** Seller Offer Listing and Landlord Offer Listing (Full Service / Commission-Based) only.  
**MLS Forms Covered:** Residential · Rental · Vacant Land · Income (Multi-Family) · Commercial Sale · Commercial Lease · Business Opportunity  
**Sources:**
- `app/Services/ListingImport/MlsFieldMap.php` — canonical key → Livewire property (live authority)
- `app/Services/ListingImport/MlsListingImportService.php` — parser branches (what the scraper emits)
- `app/Services/ListingImport/MlsCoverageReporter.php` → `fieldInventory()` — project-internal MLS form field universe; derived from the Stellar MLS Data Entry Form PDFs listed below
- Stellar MLS Data Entry Form PDFs (`attached_assets/Residential_Data_Entry_Form_*.pdf`, `Rental_Data_Entry_Form_*.pdf`, `Vacant_Land_Data_Entry_Form_*.pdf`, `Income_Data_Entry_Form_*.pdf`, `Commercial_Sale_Data_Entry_Form_*.pdf`, `Commercial_Lease_Data_Entry_Form_*.pdf`, `Business_Opportunity_Data_Entry_Form_*.pdf`) — primary MLS field source; `fieldInventory()` is the code-internal representation of these PDFs. Any field present in the PDFs but absent from `fieldInventory()` would represent a gap in the reporter rather than in this crosswalk.
- All Seller and Landlord commission-based tab blade files (property-preferences, seller-terms, lease-terms, tax-legal-hoa-disclosures, additional-details, etc.)

---

## Executive Summary

This audit is a **static code inspection** of the MLS import pipeline for Seller and Landlord offer listings. It was produced entirely by reading source files — no live MLS import was executed.

### What "mapped_correctly" means in this document

> **`mapped_correctly` = the pipeline code path is unbroken** — a parser branch exists, a field-map entry exists, the Livewire property is declared, and a `wire:model` binding was found in a blade file. It does **not** mean the import has been run against real MLS data and verified to produce the correct output.

All `mapped_correctly` classifications are **Code Inspection (CI) verified only**. The following stages were *not* live-tested: parser regex accuracy against real Stellar MLS samples, preview modal rendering, Apply Selected behavior, form save persistence, or edit/reload fidelity. See the Verification Methodology section and TABLE 3B for per-field confidence ratings. A separate Live Import Validation Audit is needed to promote CI-verified fields to fully confirmed status.

### Key findings

| Finding | Detail |
|---|---|
| **4 Landlord-only wiring gaps** | `lot_dimensions`, `roof_type`, `exterior_construction`, `foundation` are parsed + Seller-mapped but silently discarded for Landlord — requires full Livewire property + save/load + blade UI + validation + field-map entry per field (11–15 hours total; see Landlord Wiring Gaps section) |
| **12 fieldInventory() blind spots** | Correctly-mapped fields invisible to `MlsCoverageReporter`; reporter understates coverage |
| **2 parsed-but-discarded keys (both roles)** | `heating` (system type) and `lot_size_sqft` have parser branches but no field-map entries — silently lost for both Seller and Landlord |
| **34 MLS fields have no parser branch** | Including high-consumer-value fields: Community Features, Exterior Features, Parking Features, Flooring, Pets Allowed |
| **RESO compliance: 47% fully supported** | 34 of 72 reviewed RESO standard fields fully supported; 60% functional (including partial/inventory-gap fields) |
| **Coverage reporter is understated** | Residential Seller reported 88%, actual 91%; Landlord reported 86%, actual 84% (see note below) |

> **Coverage note:** Landlord's "actual" coverage (84%) is *lower* than its reported coverage (86%). This is not an error — it is because adding the 12 inventory-gap fields to the denominator (+12) outpaces what Landlord can claim in the numerator (+9), since 3 of those 12 inventory-gap fields (`roof_type`, `exterior_construction`, `foundation`) are also absent from the Landlord field map. See the Reported vs Actual Coverage table in Final Metrics for the full breakdown.

---

## Legend — Gap Type Vocabulary

| Gap Type | Meaning |
|---|---|
| `mapped_correctly` | Parsed ✓ · Field-map entry ✓ · Livewire property exists ✓ · `wire:model` binding found ✓ · **verified via static code inspection** (see Verification Methodology) |
| `missing_from_parser` | `canonical_key = null` in `fieldInventory()` — no parser branch; MLS value is never extracted |
| `parsed_incorrectly` | Parser emits a value but the regex captures wrong text (wrong field, partial match, or boundary bleed) |
| `missing_from_field_map` | Parser emits the key but no entry in `MlsFieldMap::seller()` or `landlord()` — value silently discarded |
| `mapped_incorrectly` | Field-map entry points to the wrong Livewire property (wrong field, semantically incorrect destination) |
| `missing_from_preview` | Import data exists but the preview modal does not display the field |
| `preview_value_incorrect` | Preview shows the field but displays an incorrect or stale value |
| `apply_selected_bug` | User clicks Apply Selected in the preview but the value is not written to the Livewire property |
| `form_field_missing` | Livewire property exists but the blade has no `wire:model` binding — value stored in memory but not visible/editable |
| `save_load_bug` | Value is set on the component but not persisted to the database or not reloaded correctly on edit |
| `missing_website_field` | MLS field has no corresponding website input field at all |
| `website_only_field` | Website field has no MLS equivalent — exists purely for platform-specific auction/business logic |
| `intentional_exclusion` | Documented decision; not an oversight (see Rejected Mapping Candidates) |
| `not_applicable` | Field is role-specific and semantically wrong for the other role |
| `inventory_gap_correctly_mapped` | Field IS safely parsed + mapped but was omitted from `fieldInventory()` → invisible to `MlsCoverageReporter` |
| `value_transformed_incorrectly` | Value reaches the right property but the normalizer or comma-split produces an incorrect result |
| `partial_value_mapping` | Key is mapped to the right property but only some enum/array values survive translation |
| `incorrect_destination_field` | Value goes to a field that exists but is the wrong semantic target |
| `incorrect_enum_translation` | Enum value from MLS (e.g. "Monthly") is not converted to the option the website `<select>` expects |
| `incorrect_boolean_translation` | Boolean from MLS (e.g. "Y", "TRUE", "1") is not normalized to the yes/no or true/false the website expects |
| `incorrect_array_translation` | Multi-value string is not split correctly (wrong delimiter, extra whitespace, missing values) |
| `value_truncated` | Value is cut off — typically a long text field or an MLS string that exceeds a database column limit |
| `value_lost_during_import` | Value was present at parse time but is absent after `applyImportedFields()` completes |
| `property_missing` | Field-map entry exists but `property_exists()` fails on the Livewire class |
| `no_form_binding` | Livewire property exists but no `wire:model` found in any tab blade |

**Safe To Import** means: Parsed=Y **AND** Livewire property exists=Y **AND** `wire:model` binding in a Create/Edit blade=Y.

---

## Verification Methodology

**This audit was produced entirely through static code inspection.** No live MLS import was executed during the audit.

The following sources were read and cross-referenced:

| Source | What It Proves |
|---|---|
| `MlsListingImportService::parseFields()` regex branches | That the parser emits a canonical key for a given MLS label |
| `MlsNormalizer::normalize()` switch cases | That a value is transformed (boolean coercion, enum mapping, etc.) |
| `MlsFieldMap::seller()` / `::landlord()` arrays | That a canonical key has a target Livewire property for a given role |
| `HasMlsImport::importListingFromUrl()` — `property_exists()` guard | That the Livewire component actually declares the target property |
| Tab blade files — `wire:model` search | That the property is bound to a visible form input |

**What code inspection does NOT prove:**

| Stage | Risk | Confidence |
|---|---|---|
| Parser accuracy | Regex may capture wrong text, partial text, or be tripped by atypical MLS formatting | Medium — regex audited visually; not run against real MLS samples |
| Normalizer correctness | Enum translations may not cover all MLS variants (e.g. "Central Air" vs "Central") | Medium |
| Preview display | `$importPreviewData` is populated correctly per code, but UI rendering was not observed live | Medium — code path is straightforward |
| Apply Selected write | `applyImportedFields()` was read in full; comma-split and array-set logic is correct per code | High — logic is short and explicit |
| Save persistence | Depends on `wire:model` → `saveDraft()` / `submitListing()` Livewire pipeline; not traced for each field | Medium |
| Reload fidelity | Depends on `loadDraft()` / `mount()` EAV reads; meta keys with mismatches could cause silent loss | Medium |

**Classification guide for TABLE 3B:**
- `CI` = Code Inspection — proven by reading source files
- `LT` = Live Trace required — stage could not be fully verified without running an actual import
- `⚠️ CI` = Code inspection suggests it should work but a risk factor was identified

All fields marked `mapped_correctly` in TABLE 1/2/3 are **CI-verified**, meaning the pipeline chain is unbroken in source code. They are **not** `LT-verified`. A future live import test (TABLE 3B columns marked LT) should be run to promote them to fully confirmed status.

---

## High-Priority Landlord Wiring Gaps

> These are **not** future enhancements. The parser already extracts these values and the Seller form already has complete implementations. However, source verification against `LandlordOfferListing.php` and all Landlord blade files confirmed that **none of the four properties exist anywhere on the Landlord component** — not as public properties, not in save/load logic, not in blade inputs. Adding a `MlsFieldMap` entry alone would silently fail at the `property_exists()` gate in `HasMlsImport::importListingFromUrl()` (line 68) and the field would never reach the preview. The correct fix requires parity with the Seller implementation.

**Seller implementation (confirmed by source):** Each field is declared as a public property (`$roof_type = []`, `$exterior_construction = []`, `$foundation = []`, `$lot_dimensions = ''` on `SellerOfferListing`), JSON-encoded in `saveDraft()`/`saveEdit()`, JSON-decoded in `loadDraft()`, bound to Select2 multi-select inputs in `offer-seller-tabs/commission-based/property-preferences.blade.php`, and covered by validation rules.

| MLS Field | Canonical Key | Seller Status | Landlord Status | Full Fix Required | Estimated Effort |
|---|---|---|---|---|---|
| **Lot Dimensions** | `lot_dimensions` | ✅ `mapped_correctly` | ❌ `missing_from_field_map`; property absent | (1) Declare `public string $lot_dimensions = ''` on `LandlordOfferListing`; (2) add `saveMeta('lot_dimensions', ...)` + `loadMeta`; (3) add `wire:model.defer="lot_dimensions"` text input to Landlord `property-preferences` blade; (4) add `'lot_dimensions' => 'lot_dimensions'` to `MlsFieldMap::landlord()` | 2–3 hours |
| **Roof Type** | `roof_type` | ✅ `inventory_gap_correctly_mapped` | ❌ `missing_from_field_map`; property absent | (1) Declare `public array $roof_type = []` + `public string $other_roof_type = ''`; (2) JSON encode/decode in save/load; (3) add Select2 multi-select blade input with same enum options as Seller; (4) add validation rules; (5) add `'roof_type' => '*roof_type'` to `MlsFieldMap::landlord()` | 3–4 hours |
| **Exterior Construction** | `exterior_construction` | ✅ `inventory_gap_correctly_mapped` | ❌ `missing_from_field_map`; property absent | Same pattern as Roof Type: property + save/load + blade Select2 + validation + field-map entry | 3–4 hours |
| **Foundation** | `foundation` | ✅ `inventory_gap_correctly_mapped` | ❌ `missing_from_field_map`; property absent | Same pattern as Roof Type | 3–4 hours |

**Total estimated effort:** 11–15 hours including testing — not 4 one-line changes. These are medium-scope feature additions that mirror existing Seller functionality onto the Landlord component.

---

## TABLE 0 — Property Type Coverage Matrix

> Coverage % = (Seller- or Landlord-safe fields) ÷ (all non-null canonical-key fields in that form's `fieldInventory()` block).  
> Null-canonical-key rows are shown separately as "No Parser Branch" for context.  
> Fields safely mapped but **missing from `fieldInventory()`** are listed in the footnote below the table.

| MLS Form | fieldInventory Total | No Parser Branch (null key) | Non-null Keys | Seller Safe | Seller % | Landlord Safe | Landlord % | Primary Role |
|---|---|---|---|---|---|---|---|---|
| Residential | 43 | 0 | 43 | 38 | 88 % | 37 | 86 % | Seller |
| Rental | 52 | 2 | 50 | 39 | 78 % | 44 | 88 % | Landlord |
| Vacant Land | 35 | 4 | 31 | 28 | 90 % | 27 | 87 % | Seller |
| Income (Multi-Family) | 36 | 6 | 30 | 27 | 90 % | 27 | 90 % | Seller |
| Commercial Sale | 40 | 8 | 32 | 29 | 91 % | 29 | 91 % | Seller |
| Commercial Lease | 34 | 7 | 27 | 22 | 81 % | 25 | 93 % | Landlord |
| Business Opportunity | 22 | 7 | 15 | 13 | 87 % | 13 | 87 % | Seller |
| **TOTAL** | **262** | **34** | **228** | **196** | **86 %** | **202** | **89 %** | — |

> **⚠ Inventory Gap:** Twelve canonical keys are safely parsed + mapped to Seller and/or Landlord but are **absent from `fieldInventory()`**, making them invisible to `MlsCoverageReporter`. Adding them would raise effective Residential coverage to ~97% for Seller and ~91% for Landlord:
> `heating_fuel`, `roof_type` (Seller only), `exterior_construction` (Seller only), `foundation` (Seller only), `water`, `sewer`, `utilities`, `sqft_heated_source`, `flood_insurance_required`, `has_special_assessments`, `special_assessment_amount`, `special_assessment_description`.

### TABLE 0B — Per-Form Autofill Destination Summary

> Primary website properties that receive MLS-imported values per form, and notable gaps where MLS fields have no website destination.

| MLS Form | Primary Seller Autofill Destinations | Primary Landlord Autofill Destinations | Parsed Fields with No Website Destination (Both Roles) |
|---|---|---|---|
| **Residential** | `maximum_budget` (price), `bedrooms`, `bathrooms`, `heated_sqft`, `year_built`, `pool`, `garage`, `carport`, `lot_dimensions`, `total_acreage`, `interior_features`, `appliances`, `air_conditioning`, `waterfront`, `water_access`, `water_view`, `flood_zone_*`, `has_hoa`, `association_*`, `has_cdd`, `annual_cdd_fee`, `additional_details` | Same as Seller except `maximum_budget` → `desired_rental_amount`; **missing:** `lot_dimensions`, `roof_type`, `exterior_construction`, `foundation` | `heating` (system type), `lot_size_sqft`, `directions`, `mls_number` |
| **Rental** | `maximum_budget` (semantically wrong — sale price on a rental form); `bedrooms`, `bathrooms`, `heated_sqft`, `year_built`, `pool`, `garage`, `carport`, `furnished`, `lot_dimensions`, `total_acreage`, `air_conditioning`, `appliances`, `flood_zone_*`, `has_hoa`, `association_*`, `has_cdd`, `annual_cdd_fee`, `additional_details` | `desired_rental_amount` (price), same property fields as Seller; **plus** `available_date`, `minimum_security_deposit`, `lease_amount_frequency`, `terms_of_lease`, `rent_includes`, `tenant_pays`; **missing:** `lot_dimensions`, `roof_type`, `exterior_construction`, `foundation` | `heating` (system type), `lot_size_sqft`, `directions`, `mls_number`, `application_fee` |
| **Vacant Land** | `maximum_budget`, `lot_dimensions`, `total_acreage`, `lot_size_sqft` (no map — discarded), `zoning`, `waterfront`, `water_access`, `water_view`, `flood_zone_*`, `has_hoa`, `association_*`, `has_cdd`, `annual_cdd_fee`, `additional_details` | Same as Seller | `lot_size_sqft`, `directions`, `mls_number`; also Lot Features, Road Surface, Utilities Available, Topography (no parser branches) |
| **Income (Multi-Family)** | `maximum_budget`, `year_built`, `heated_sqft`, `lot_dimensions`, `total_acreage`, `zoning`, `flood_zone_*`, `has_hoa`, `association_*`, `has_cdd`, `annual_cdd_fee`, `additional_details` | Same as Seller | `mls_number`, `directions`; also Number of Units, Unit Types, NOI, Gross/Annual Income, Cap Rate (no parser branches) |
| **Commercial Sale** | `maximum_budget`, `year_built`, `lot_dimensions`, `total_acreage`, `zoning`, `waterfront`, `water_access`, `water_view`, `flood_zone_*`, `has_hoa`, `association_*`, `has_cdd`, `annual_cdd_fee`, `additional_details` | Same as Seller | `mls_number`, `directions`; also Building Size, Bays, Dock Doors, Ceiling Height, Office Area, Parking, NOI, Cap Rate (no parser branches) |
| **Commercial Lease** | `maximum_budget` (`not_applicable` for Seller — semantically wrong), address fields, `additional_details` | `desired_rental_amount`, `available_date`, `tenant_pays`, `rent_includes`, `flood_zone_*`, `has_hoa`, `association_*`, `has_cdd`, `annual_cdd_fee`, `additional_details` | `mls_number`, `directions`; also Building Size, Office Area, Parking, Lease Rate Type, Minimum Lease Term, Rent Rate/sqft, Build-Out Allowance (no parser branches) |
| **Business Opportunity** | `maximum_budget`, address fields, `tax_id`/`tax_year`/`legal_description`/`additional_parcels`/`total_parcel_count`, `annual_property_taxes`, `flood_zone_*`, `has_hoa`, `has_cdd`, `additional_details` | Same as Seller | `mls_number`, `directions`; also all Business Details fields (Business Type, Revenue, Net Income, Employees, Seller Financing, Lease Type, Inventory) have no parser branches |

---

## TABLE 1 — Seller Website Field Inventory (Full Crosswalk)

All fields that appear on the Seller Offer Listing (Full Service) Create/Edit form, organized by tab. Every field is crossed with its MLS import status.

| Tab | Field Label (UI) | Livewire Property | Storage | MLS Canonical Key | MLS Form(s) | Autofills Today | Gap Type | Notes |
|---|---|---|---|---|---|---|---|---|
| **Listing Details** | Listing Status | `listing_status` | native column | — | — | No | `website_only_field` | Workflow state; not on MLS forms |
| **Listing Details** | Listing Title | `listing_title` | native column | — | — | No | `website_only_field` | Platform-specific |
| **Listing Details** | Listing Date | `listing_date` | native column | — | — | No | `website_only_field` | Set on creation |
| **Listing Details** | Expiration Date | `expiration_date` | native column | — | — | No | `website_only_field` | Bidding countdown source |
| **Listing Details** | Listing / Auction Type | `auction_type` | native column | — | — | No | `website_only_field` | Open/Reserved/Bidding Period |
| **Listing Details** | Bidding Period Length | `auction_time` | native column | — | — | No | `website_only_field` | Hours; platform-specific |
| **Seller Info** | First Name | `first_name` | native column | — | — | No | `website_only_field` | Seller identity |
| **Seller Info** | Last Name | `last_name` | native column | — | — | No | `website_only_field` | |
| **Seller Info** | Phone Number | `phone_number` | native column | — | — | No | `website_only_field` | |
| **Seller Info** | Email Address | `email` | native column | — | — | No | `website_only_field` | |
| **Seller Info** | Seller's Current Status | `current_status` | meta | — | — | No | `website_only_field` | e.g. Active, Pending — platform concept |
| **Seller Info** | Personal Photo | `photo` | file storage | — | — | No | `website_only_field` | |
| **Seller Info** | Personal Video Link | `video_link` | meta | — | — | No | `website_only_field` | |
| **Sale Terms** | Special Sale Provision | `sale_provision` | meta (JSON array) | — | — | No | `website_only_field` | Multi-select; platform auction concept |
| **Sale Terms** | Sale Provision — Other | `sale_provision_other` | meta | — | — | No | `website_only_field` | |
| **Sale Terms** | Assignment Fee Type | `assignment_fee_type` | meta | — | — | No | `website_only_field` | Conditional on provision |
| **Sale Terms** | Assignment Fee Amount | `assignment_fee_amount` | meta | — | — | No | `website_only_field` | |
| **Sale Terms** | Target Closing Timeframe | `target_closing_date` | native column | — | — | No | `website_only_field` | |
| **Sale Terms** | Occupant Type | `occupant_status` | meta | — | — | No | `website_only_field` | Owner/Tenant/Vacant |
| **Sale Terms** | Occupied Until | `occupant_tenant` | meta | — | — | No | `website_only_field` | Conditional on Tenant |
| **Sale Terms** | **Desired Sale Price** | `maximum_budget` | native column | `price` | All forms | **Yes** | `mapped_correctly` | Seller→`maximum_budget`; note comment in fieldMap re: not purchase_price |
| **Sale Terms** | Starting Price | `starting_price` | native column | — | — | No | `website_only_field` | Bidding Period only |
| **Sale Terms** | Reserve Price | `reserve_price` | native column | — | — | No | `website_only_field` | Bidding Period only |
| **Sale Terms** | Buy Now Price | `buy_now_price` | native column | — | — | No | `website_only_field` | Bidding Period only |
| **Sale Terms** | Offered Financing / Currency | `offered_financing` | meta (JSON array) | — | — | No | `website_only_field` | Multi-select |
| **Sale Terms** | Assumable Terms | `assumable_terms` | meta | — | — | No | `website_only_field` | |
| **Sale Terms** | Seller Financing sub-fields (10+) | various | meta | — | — | No | `website_only_field` | Conditional; entirely platform-specific |
| **Sale Terms** | Lease-Option sub-fields (9) | various | meta | — | — | No | `website_only_field` | Conditional; platform-specific |
| **Sale Terms** | Lease-Purchase sub-fields (9) | various | meta | — | — | No | `website_only_field` | Conditional; platform-specific |
| **Financial Details** | Min Annual Net Income | `minimum_annual_net_income` | meta | — | — | No | `website_only_field` | Income/Commercial/Business only |
| **Financial Details** | Min Cap Rate | `minimum_cap_rate` | meta | — | — | No | `website_only_field` | |
| **Financial Details** | Gross Annual Income | `gross_annual_income` | meta | — | — | No | `website_only_field` | Income type only |
| **Financial Details** | Annual Operating Expenses | `annual_operating_expenses` | meta | — | — | No | `website_only_field` | |
| **Financial Details** | Rent Roll Available | `rent_roll_available` | meta | — | — | No | `website_only_field` | |
| **Financial Details** | Operating Statement Available | `operating_statement_available` | meta | — | — | No | `website_only_field` | |
| **Financial Details** | Price per Sq Ft | `price_per_sqft` | meta | — | — | No | `website_only_field` | Commercial only |
| **Financial Details** | Existing Lease Type | `existing_lease_type` | meta | — | — | No | `website_only_field` | Commercial only |
| **Financial Details** | Lease Expiration | `lease_expiration` | meta | — | — | No | `website_only_field` | Commercial only |
| **Financial Details** | Annual Revenue | `annual_revenue` | meta | — | — | No | `website_only_field` | Business only |
| **Financial Details** | Gross Profit | `gross_profit` | meta | — | — | No | `website_only_field` | Business only |
| **Financial Details** | SDE / EBITDA | `sde_ebitda` | meta | — | — | No | `website_only_field` | Business only |
| **Financial Details** | Inventory Value | `inventory_value` | meta | — | — | No | `website_only_field` | Business only |
| **Financial Details** | FF&E Value | `ffe_value` | meta | — | — | No | `website_only_field` | Business only |
| **Financial Details** | Reason for Sale | `reason_for_sale` | meta | — | — | No | `website_only_field` | Business only |
| **Financial Details** | Employee Count | `employee_count` | meta | — | — | No | `website_only_field` | Business only |
| **Financial Details** | Financial Statements Available | `financial_statements_available` | meta | — | — | No | `website_only_field` | Business only |
| **Financial Details** | NDA Required | `nda_required` | meta | — | — | No | `website_only_field` | Business only |
| **Additional Details** | Property Description | `additional_details` | meta | `description` | All forms | **Yes** | `mapped_correctly` | Maps from MLS Public Remarks |
| **Property Details** | Street Address | `address` | native column | `address` | All forms | **Yes** | `mapped_correctly` | |
| **Property Details** | City | `property_city` | native column | `city` | All forms | **Yes** | `mapped_correctly` | |
| **Property Details** | State | `property_state` | native column | `state` | All forms | **Yes** | `mapped_correctly` | |
| **Property Details** | County | `property_county` | native column | `county` | All forms | **Yes** | `mapped_correctly` | |
| **Property Details** | ZIP Code | `property_zip` | native column | `zip` | All forms | **Yes** | `mapped_correctly` | |
| **Property Details** | Property Type | `property_type` | native column | — | — | No | `website_only_field` | Select; not an MLS import field |
| **Property Details** | Property Style | `property_style` | meta | — | — | No | `website_only_field` | Architectural style; platform concept |
| **Property Details** | Bedrooms | `bedrooms` | native column | `bedrooms` | Residential · Rental | **Yes** | `mapped_correctly` | |
| **Property Details** | Bathrooms | `bathrooms` | native column | `bathrooms` | Residential · Rental | **Yes** | `mapped_correctly` | |
| **Property Details** | Heated Sq Ft (min) | `minimum_heated_square` | native column | `heated_sqft` | Residential · Rental · Income · Commercial Sale | **Yes** | `mapped_correctly` | |
| **Property Details** | Year Built | `year_built` | meta | `year_built` | Residential · Rental · Income · Commercial Sale | **Yes** | `mapped_correctly` | |
| **Property Details** | Lot Dimensions | `lot_dimensions` | meta | `lot_dimensions` | All forms | **Yes** | `mapped_correctly` | Seller only; landlord omits (see TABLE 2) |
| **Property Details** | Total Acreage | `total_acreage` | native column | `lot_size_acres` | All forms | **Yes** | `mapped_correctly` | Canonical key renamed: `lot_size_acres`→`total_acreage` |
| **Property Details** | Pool | `pool_needed` | meta | `pool` | Residential · Rental | **Yes** | `mapped_correctly` | Normalizer coerces Yes/No/true/false |
| **Property Details** | Garage | `garage_needed` | meta | `garage` | Residential · Rental | **Yes** | `mapped_correctly` | |
| **Property Details** | Carport | `carport_needed` | meta | `carport` | Residential · Rental | **Yes** | `mapped_correctly` | |
| **Property Details** | Furnished / Turnkey | `tenant_require` | native column | `furnished` | Rental | **Yes** | `mapped_correctly` | Property named `tenant_require`; key is `furnished` |
| **Property Details** | A/C | `air_conditioning` | meta (JSON array) | `air_conditioning` | Residential · Rental | **Yes** | `mapped_correctly` | Multi-select; `*` prefix → comma-split on import |
| **Property Details** | Heating & Fuel | `heating_and_fuel` | meta (JSON array) | `heating_fuel` | Residential · Rental | **Yes** | `inventory_gap_correctly_mapped` | Mapped correctly but absent from `fieldInventory()` — reporter blind spot. Also: separate `heating` canonical key (system type only) is parsed but **not** mapped → `missing_from_field_map` |
| **Property Details** | Roof Type | `roof_type` | meta (JSON array) | `roof_type` | Residential · Rental | **Yes** | `inventory_gap_correctly_mapped` | Absent from `fieldInventory()` |
| **Property Details** | Exterior Construction | `exterior_construction` | meta (JSON array) | `exterior_construction` | Residential · Rental | **Yes** | `inventory_gap_correctly_mapped` | Absent from `fieldInventory()` |
| **Property Details** | Foundation | `foundation` | meta (JSON array) | `foundation` | Residential · Rental | **Yes** | `inventory_gap_correctly_mapped` | Absent from `fieldInventory()` |
| **Property Details** | Water | `water` | meta (JSON array) | `water` | Residential · Rental | **Yes** | `inventory_gap_correctly_mapped` | Absent from `fieldInventory()` |
| **Property Details** | Sewer | `sewer` | meta (JSON array) | `sewer` | Residential · Rental | **Yes** | `inventory_gap_correctly_mapped` | Absent from `fieldInventory()` |
| **Property Details** | Utilities | `utilities` | meta (JSON array) | `utilities` | Residential · Rental · Vacant Land | **Yes** | `inventory_gap_correctly_mapped` | Absent from `fieldInventory()` |
| **Property Details** | Sq Ft Source | `sqft_heated_source` | meta | `sqft_heated_source` | Residential · Rental | **Yes** | `inventory_gap_correctly_mapped` | Absent from `fieldInventory()` |
| **Property Details** | Zoning | `zoning` | meta | `zoning` | All forms | **Yes** | `mapped_correctly` | Free-text |
| **Property Details** | Waterfront Y/N | `waterfront` | meta | `waterfront` | Residential · Rental · Vacant Land · Commercial Sale | **Yes** | `mapped_correctly` | Normalizer coerces to yes/no |
| **Property Details** | Water Access | `water_access` | meta (JSON array) | `water_access` | Residential · Rental · Vacant Land · Commercial Sale | **Yes** | `mapped_correctly` | Multi-select |
| **Property Details** | Water View | `water_view` | meta (JSON array) | `water_view` | Residential · Rental · Vacant Land · Commercial Sale | **Yes** | `mapped_correctly` | Multi-select |
| **Property Details** | Interior Features | `interior_features` | meta (JSON array) | `interior_features` | Residential · Rental | **Yes** | `mapped_correctly` | Multi-select |
| **Property Details** | Property Condition | `property_condition` | meta | — | — | No | `website_only_field` | |
| **Property Details** | Age Restriction | `purchasing_props` | meta | — | — | No | `website_only_field` | |
| **Tax / Legal** | Parcel ID | `parcel_id` | meta | `tax_id` | All forms | **Yes** | `mapped_correctly` | App property `parcel_id` ≠ canonical key `tax_id`; mapping is explicit |
| **Tax / Legal** | Tax Year | `tax_year` | meta | `tax_year` | All forms | **Yes** | `mapped_correctly` | |
| **Tax / Legal** | Annual Property Taxes | `annual_property_taxes` | meta | `annual_taxes` | All forms | **Yes** | `mapped_correctly` | Property includes word "property"; canonical key is shorter |
| **Tax / Legal** | Additional Parcels Y/N | `additional_parcels` | meta | `additional_parcels` | All forms | **Yes** | `mapped_correctly` | Normalizer required |
| **Tax / Legal** | Total Parcel Count | `total_parcel_count` | meta | `total_parcel_count` | All forms | **Yes** | `mapped_correctly` | |
| **Tax / Legal** | Additional Parcel IDs | `additional_parcel_ids` | meta | — | — | No | `website_only_field` | Entered manually after import |
| **Tax / Legal** | Legal Description | `legal_description` | meta | `legal_description` | All forms | **Yes** | `mapped_correctly` | |
| **Flood Zone** | Flood Zone Code | `flood_zone_code` | meta | `flood_zone_code` | Residential · Rental · Vacant Land · Income · Commercial Sale · Commercial Lease | **Yes** | `mapped_correctly` | Normalizer uppercases |
| **Flood Zone** | Flood Insurance Required | `flood_insurance_required` | meta | `flood_insurance_required` | Residential · Rental | **Yes** | `inventory_gap_correctly_mapped` | Parsed + mapped correctly; absent from `fieldInventory()` |
| **Flood Zone** | Flood Zone Panel | `flood_zone_panel` | meta | `flood_zone_panel` | Residential · Rental · Vacant Land · Income · Commercial Sale · Commercial Lease | **Yes** | `mapped_correctly` | |
| **Flood Zone** | Flood Zone Date | `flood_zone_date` | meta | `flood_zone_date` | Residential · Rental · Vacant Land · Income · Commercial Sale · Commercial Lease | **Yes** | `mapped_correctly` | |
| **HOA** | HOA / Association Y/N | `has_hoa` | meta | `has_hoa` | All forms | **Yes** | `mapped_correctly` | Normalizer: yes/no |
| **HOA** | Association Name | `association_name` | meta | `association_name` | All forms | **Yes** | `mapped_correctly` | |
| **HOA** | Association Fee Amount | `association_fee_amount` | meta | `association_fee_amount` | All forms | **Yes** | `mapped_correctly` | |
| **HOA** | Association Fee Frequency | `association_fee_frequency` | meta | `association_fee_frequency` | All forms | **Yes** | `mapped_correctly` | Normalizer: lowercase |
| **CDD** | CDD Y/N | `has_cdd` | meta | `has_cdd` | All forms | **Yes** | `mapped_correctly` | Normalizer: yes/no |
| **CDD** | CDD Annual Fee | `annual_cdd_fee` | meta | `annual_cdd_fee` | All forms | **Yes** | `mapped_correctly` | |
| **CDD** | Special Assessments Y/N | `has_special_assessments` | meta | `has_special_assessments` | All forms | **Yes** | `mapped_correctly` | |
| **CDD** | Special Assessment Amount | `special_assessment_amount` | meta | `special_assessment_amount` | All forms | **Yes** | `mapped_correctly` | |
| **CDD** | Special Assessment Description | `special_assessment_description` | meta | `special_assessment_description` | All forms | **Yes** | `mapped_correctly` | |

**Seller Summary:** 87 website fields total · **37 autofill from MLS** (of which 9 are `inventory_gap_correctly_mapped`) · **50 website-only fields** (no MLS equivalent)

---

## TABLE 2 — Landlord Website Field Inventory (Full Crosswalk)

All fields on the Landlord Offer Listing (Full Service) Create/Edit form. Fields identical to Seller (same tab structure, same crosswalk status) are collapsed; differences and rental-specific fields are expanded.

| Tab | Field Label (UI) | Livewire Property | Storage | MLS Canonical Key | MLS Form(s) | Autofills Today | Gap Type | Notes |
|---|---|---|---|---|---|---|---|---|
| **Listing Details** | Listing Status / Title / Date / Expiration / Type / Time | (same as Seller) | native | — | — | No | `website_only_field` | Identical to Seller fields |
| **Landlord Info** | First Name / Last Name / Phone / Email / Status / Photo / Video | (same as Seller) | native/meta | — | — | No | `website_only_field` | Identical to Seller fields |
| **Lease Terms** | Occupant Status | `occupant_status` | meta | — | — | No | `website_only_field` | |
| **Lease Terms** | Leasing Space Type | `leasing_spaces` | meta | — | — | No | `website_only_field` | e.g. Full Unit, Room Only — platform concept |
| **Lease Terms** | Restrictions | `restrictions` | meta (JSON array) | — | — | No | `website_only_field` | e.g. No Pets, No Smoking |
| **Lease Terms** | Maintenance By | `maintenance_by` | meta | — | — | No | `website_only_field` | |
| **Lease Terms** | **Desired Rental Amount** | `desired_rental_amount` | meta | `price` | Rental · Commercial Lease | **Yes** | `mapped_correctly` | MLS Monthly Rent → `desired_rental_amount` |
| **Lease Terms** | **Available Date** | `available_date` | meta | `available_date` | Rental · Commercial Lease | **Yes** | `mapped_correctly` | |
| **Lease Terms** | **Security Deposit Amount** | `security_deposit_amount` | meta | `minimum_security_deposit` | Rental | **Yes** | `mapped_correctly` | Canonical key differs from property name |
| **Lease Terms** | **Lease Amount Frequency** | `lease_amount_frequency` | meta | `lease_amount_frequency` | Rental | **Yes** | `mapped_correctly` | Normalizer: lowercase |
| **Lease Terms** | **Terms of Lease** | `terms_of_lease` | meta (JSON array) | `terms_of_lease` | Rental | **Yes** | `mapped_correctly` | Multi-select |
| **Lease Terms** | **Tenant Pays** | `tenant_pays` | meta (JSON array) | `tenant_pays` | Rental · Commercial Lease | **Yes** | `mapped_correctly` | Multi-select |
| **Lease Terms** | **Rent Includes** | `rent_includes` | meta (JSON array) | `rent_includes` | Rental · Commercial Lease | **Yes** | `mapped_correctly` | Multi-select |
| **Lease Terms** | Room-Specific Fields (bathroom_facilities, room_size) | `bathroom_facilities`, `room_size` | meta | — | — | No | `website_only_field` | Conditional on Room Only leasing type |
| **Additional Details** | Property Description | `additional_details` | meta | `description` | All forms | **Yes** | `mapped_correctly` | Public Remarks |
| **Property Details** | Street Address / City / State / County / ZIP | (same as Seller) | native | `address`–`county` | All forms | **Yes** | `mapped_correctly` | |
| **Property Details** | Property Type | `property_type` | native | — | — | No | `website_only_field` | |
| **Property Details** | Bedrooms | `bedrooms` | native | `bedrooms` | Rental | **Yes** | `mapped_correctly` | |
| **Property Details** | Bathrooms | `bathrooms` | native | `bathrooms` | Rental | **Yes** | `mapped_correctly` | |
| **Property Details** | Heated Sq Ft (min) | `minimum_heated_square` | native | `heated_sqft` | Rental | **Yes** | `mapped_correctly` | |
| **Property Details** | Year Built | `year_built` | meta | `year_built` | Rental | **Yes** | `mapped_correctly` | |
| **Property Details** | Lot Dimensions | — | — | `lot_dimensions` | All forms | **No** | `missing_from_field_map` | Parsed for Seller but **not mapped for Landlord**; no `lot_dimensions` property on `LandlordOfferListing` |
| **Property Details** | Total Acreage | `total_acreage` | native | `lot_size_acres` | All forms | **Yes** | `mapped_correctly` | |
| **Property Details** | Pool / Garage / Carport | `pool_needed` / `garage_needed` / `carport_needed` | meta | `pool` / `garage` / `carport` | Rental | **Yes** | `mapped_correctly` | |
| **Property Details** | Furnished / Turnkey | `tenant_require` | native | `furnished` | Rental | **Yes** | `mapped_correctly` | |
| **Property Details** | A/C | `air_conditioning` | meta (JSON array) | `air_conditioning` | Rental | **Yes** | `mapped_correctly` | |
| **Property Details** | Heating & Fuel (Landlord) | `heating_fuel` | meta (JSON array) | `heating_fuel` | Rental | **Yes** | `inventory_gap_correctly_mapped` | Target property is `heating_fuel` (different from Seller's `heating_and_fuel`); absent from `fieldInventory()` |
| **Property Details** | Water | `water` | meta (JSON array) | `water` | Rental | **Yes** | `inventory_gap_correctly_mapped` | Absent from `fieldInventory()` |
| **Property Details** | Sewer | `sewer` | meta (JSON array) | `sewer` | Rental | **Yes** | `inventory_gap_correctly_mapped` | Absent from `fieldInventory()` |
| **Property Details** | Utilities | `property_utilities` | meta (JSON array) | `utilities` | Rental | **Yes** | `inventory_gap_correctly_mapped` | Property is `property_utilities` (different from Seller's `utilities`); absent from `fieldInventory()` |
| **Property Details** | Sq Ft Source | `sqft_heated_source` | meta | `sqft_heated_source` | Rental | **Yes** | `inventory_gap_correctly_mapped` | Absent from `fieldInventory()` |
| **Property Details** | Zoning | `zoning` | meta | `zoning` | All forms | **Yes** | `mapped_correctly` | |
| **Property Details** | Waterfront Y/N / Water Access / Water View | `waterfront` / `water_access` / `water_view` | meta | same keys | Rental | **Yes** | `mapped_correctly` | |
| **Property Details** | Interior Features | `interior_features` | meta (JSON array) | `interior_features` | Rental | **Yes** | `mapped_correctly` | |
| **Property Details** | Roof Type | — | — | `roof_type` | Residential · Rental | **No** | `missing_from_field_map` | Parsed; **not mapped for Landlord**; Seller maps it |
| **Property Details** | Exterior Construction | — | — | `exterior_construction` | Residential · Rental | **No** | `missing_from_field_map` | Parsed; **not mapped for Landlord** |
| **Property Details** | Foundation | — | — | `foundation` | Residential · Rental | **No** | `missing_from_field_map` | Parsed; **not mapped for Landlord** |
| **Property Details** | Property Condition / Age Restriction | `property_condition` / `purchasing_props` | meta | — | — | No | `website_only_field` | |
| **Tax / Legal** | Parcel ID / Tax Year / Annual Property Taxes / Additional Parcels / Total Parcel Count / Legal Description | same as Seller | meta | `tax_id` / `tax_year` / `annual_taxes` / `additional_parcels` / `total_parcel_count` / `legal_description` | All forms | **Yes** | `mapped_correctly` | Identical structure to Seller |
| **Tax / Legal** | Additional Parcel IDs | `additional_parcel_ids` | meta | — | — | No | `website_only_field` | |
| **Flood Zone** | Flood Zone Code / Insurance Required / Panel / Date | same as Seller | meta | same keys | Residential · Rental · Vacant Land · Income · Comm. | **Yes** | `mapped_correctly` / `inventory_gap_correctly_mapped` | `flood_insurance_required` is inventory gap |
| **HOA / CDD** | All HOA/CDD/Special Assessment fields | same as Seller | meta | same keys | All forms | **Yes** | `mapped_correctly` | Identical to Seller |

**Landlord Summary:** 80 website fields total · **40 autofill from MLS** (of which 6 are `inventory_gap_correctly_mapped`) · **40 website-only fields** · **4** additional `missing_from_field_map` gaps vs. Seller (Lot Dimensions, Roof Type, Exterior Construction, Foundation)

---

## TABLE 3 — MLS Field × Seller/Landlord Crosswalk (All 7 Forms)

Ordered by MLS Form → Section → Field Label. The `fieldInventory()` is the authoritative source for the MLS universe. Fields marked `inventory_gap` exist in the parser + field maps but are absent from `fieldInventory()` — they are appended after each form section.

### Form 1: Residential

| MLS Section | MLS Field Label | Canonical Key | Seller Target Property | Seller Status | Landlord Target Property | Landlord Status | Norm Required | Notes |
|---|---|---|---|---|---|---|---|---|
| ADDRESS | Street Address | `address` | `address` | `mapped_correctly` | `address` | `mapped_correctly` | N | |
| ADDRESS | City | `city` | `property_city` | `mapped_correctly` | `property_city` | `mapped_correctly` | N | |
| ADDRESS | State | `state` | `property_state` | `mapped_correctly` | `property_state` | `mapped_correctly` | N | |
| ADDRESS | Zip Code | `zip` | `property_zip` | `mapped_correctly` | `property_zip` | `mapped_correctly` | N | |
| ADDRESS | County | `county` | `property_county` | `mapped_correctly` | `property_county` | `mapped_correctly` | N | |
| LISTING INFORMATION | MLS # | `mls_number` | — | `intentional_exclusion` | — | `intentional_exclusion` | N | No Livewire property on any component |
| LISTING INFORMATION | List Price | `price` | `maximum_budget` | `mapped_correctly` | `desired_rental_amount` | `not_applicable` | N | Landlord price maps on Rental form only |
| PROPERTY DETAILS | Bedrooms | `bedrooms` | `bedrooms` | `mapped_correctly` | `bedrooms` | `mapped_correctly` | N | |
| PROPERTY DETAILS | Bathrooms | `bathrooms` | `bathrooms` | `mapped_correctly` | `bathrooms` | `mapped_correctly` | N | |
| PROPERTY DETAILS | Heated Sq Ft | `heated_sqft` | `minimum_heated_square` | `mapped_correctly` | `minimum_heated_square` | `mapped_correctly` | N | |
| PROPERTY DETAILS | Year Built | `year_built` | `year_built` | `mapped_correctly` | `year_built` | `mapped_correctly` | N | |
| PROPERTY DETAILS | Pool | `pool` | `pool_needed` | `mapped_correctly` | `pool_needed` | `mapped_correctly` | Y | Normalizer: "Yes/No" → yes/no |
| PROPERTY DETAILS | Garage | `garage` | `garage_needed` | `mapped_correctly` | `garage_needed` | `mapped_correctly` | Y | |
| PROPERTY DETAILS | Carport | `carport` | `carport_needed` | `mapped_correctly` | `carport_needed` | `mapped_correctly` | Y | |
| PROPERTY DETAILS | Lot Dimensions | `lot_dimensions` | `lot_dimensions` | `mapped_correctly` | — | `missing_from_field_map` | N | No `lot_dimensions` property on `LandlordOfferListing` |
| PROPERTY DETAILS | Lot Acreage | `lot_size_acres` | `total_acreage` | `mapped_correctly` | `total_acreage` | `mapped_correctly` | N | |
| PROPERTY DETAILS | Lot Size (Sq Ft) | `lot_size_sqft` | — | `missing_from_field_map` | — | `missing_from_field_map` | N | Parsed; no website property for standalone lot sq ft |
| PROPERTY DETAILS | Zoning | `zoning` | `zoning` | `mapped_correctly` | `zoning` | `mapped_correctly` | N | |
| INTERIOR / SYSTEMS | Air Conditioning | `air_conditioning` | `air_conditioning` | `mapped_correctly` | `air_conditioning` | `mapped_correctly` | N | Multi-select |
| INTERIOR / SYSTEMS | Heating | `heating` | — | `missing_from_field_map` | — | `missing_from_field_map` | N | System-type field (e.g. "Central Air"); separate from `heating_fuel` which IS mapped |
| INTERIOR / SYSTEMS | Interior Features | `interior_features` | `interior_features` | `mapped_correctly` | `interior_features` | `mapped_correctly` | N | Multi-select |
| INTERIOR / SYSTEMS | Appliances | `appliances` | `appliances` | `mapped_correctly` | `appliances` | `mapped_correctly` | N | Multi-select |
| WATERFRONT | Waterfront Y/N | `waterfront` | `waterfront` | `mapped_correctly` | `waterfront` | `mapped_correctly` | Y | Normalizer: yes/no |
| WATERFRONT | Water Access | `water_access` | `water_access` | `mapped_correctly` | `water_access` | `mapped_correctly` | N | Multi-select |
| WATERFRONT | Water View | `water_view` | `water_view` | `mapped_correctly` | `water_view` | `mapped_correctly` | N | Multi-select |
| TAX / LEGAL | Tax ID / Parcel ID | `tax_id` | `parcel_id` | `mapped_correctly` | `parcel_id` | `mapped_correctly` | N | Property name differs from canonical key |
| TAX / LEGAL | Tax Year | `tax_year` | `tax_year` | `mapped_correctly` | `tax_year` | `mapped_correctly` | N | |
| TAX / LEGAL | Annual Property Taxes | `annual_taxes` | `annual_property_taxes` | `mapped_correctly` | `annual_property_taxes` | `mapped_correctly` | N | |
| TAX / LEGAL | Legal Description | `legal_description` | `legal_description` | `mapped_correctly` | `legal_description` | `mapped_correctly` | N | |
| TAX / LEGAL | Additional Parcels Y/N | `additional_parcels` | `additional_parcels` | `mapped_correctly` | `additional_parcels` | `mapped_correctly` | Y | |
| TAX / LEGAL | Total Number of Parcels | `total_parcel_count` | `total_parcel_count` | `mapped_correctly` | `total_parcel_count` | `mapped_correctly` | N | |
| FLOOD ZONE | Flood Zone Code | `flood_zone_code` | `flood_zone_code` | `mapped_correctly` | `flood_zone_code` | `mapped_correctly` | Y | Normalizer: uppercase |
| FLOOD ZONE | Flood Zone Date | `flood_zone_date` | `flood_zone_date` | `mapped_correctly` | `flood_zone_date` | `mapped_correctly` | N | |
| FLOOD ZONE | Flood Zone Panel | `flood_zone_panel` | `flood_zone_panel` | `mapped_correctly` | `flood_zone_panel` | `mapped_correctly` | N | |
| HOA / CDD | Association Y/N | `has_hoa` | `has_hoa` | `mapped_correctly` | `has_hoa` | `mapped_correctly` | Y | |
| HOA / CDD | Association Name | `association_name` | `association_name` | `mapped_correctly` | `association_name` | `mapped_correctly` | N | |
| HOA / CDD | Association Fee | `association_fee_amount` | `association_fee_amount` | `mapped_correctly` | `association_fee_amount` | `mapped_correctly` | N | |
| HOA / CDD | Association Fee Frequency | `association_fee_frequency` | `association_fee_frequency` | `mapped_correctly` | `association_fee_frequency` | `mapped_correctly` | Y | |
| HOA / CDD | CDD Y/N | `has_cdd` | `has_cdd` | `mapped_correctly` | `has_cdd` | `mapped_correctly` | Y | |
| HOA / CDD | CDD Annual Amount | `annual_cdd_fee` | `annual_cdd_fee` | `mapped_correctly` | `annual_cdd_fee` | `mapped_correctly` | N | |
| REMARKS | Public Remarks | `description` | `additional_details` | `mapped_correctly` | `additional_details` | `mapped_correctly` | N | |
| REMARKS | Directions | `directions` | — | `missing_from_field_map` | — | `missing_from_field_map` | N | No directions field on Seller or Landlord listing form |
| DERIVED | (rental signal) | `listing_type_hint` | — | `intentional_exclusion` | — | `intentional_exclusion` | N | Internal flag; not an MLS field |
| *(inventory gap)* | Heating & Fuel | `heating_fuel` | `heating_and_fuel` | `inventory_gap_correctly_mapped` | `heating_fuel` | `inventory_gap_correctly_mapped` | N | Absent from `fieldInventory()` |
| *(inventory gap)* | Roof | `roof_type` | `roof_type` | `inventory_gap_correctly_mapped` | — | `missing_from_field_map` | N | Seller maps it; Landlord does not |
| *(inventory gap)* | Construction Status | `exterior_construction` | `exterior_construction` | `inventory_gap_correctly_mapped` | — | `missing_from_field_map` | N | Seller maps it; Landlord does not |
| *(inventory gap)* | Foundation Details | `foundation` | `foundation` | `inventory_gap_correctly_mapped` | — | `missing_from_field_map` | N | Seller maps it; Landlord does not |
| *(inventory gap)* | Water (source) | `water` | `water` | `inventory_gap_correctly_mapped` | `water` | `inventory_gap_correctly_mapped` | N | |
| *(inventory gap)* | Sewer | `sewer` | `sewer` | `inventory_gap_correctly_mapped` | `sewer` | `inventory_gap_correctly_mapped` | N | |
| *(inventory gap)* | Utilities | `utilities` | `utilities` | `inventory_gap_correctly_mapped` | `property_utilities` | `inventory_gap_correctly_mapped` | N | Different target property for Landlord |
| *(inventory gap)* | Sq Ft Heated Source | `sqft_heated_source` | `sqft_heated_source` | `inventory_gap_correctly_mapped` | `sqft_heated_source` | `inventory_gap_correctly_mapped` | N | |
| *(inventory gap)* | Flood Insurance Required | `flood_insurance_required` | `flood_insurance_required` | `inventory_gap_correctly_mapped` | `flood_insurance_required` | `inventory_gap_correctly_mapped` | N | |
| *(inventory gap)* | Special Assessments Y/N | `has_special_assessments` | `has_special_assessments` | `inventory_gap_correctly_mapped` | `has_special_assessments` | `inventory_gap_correctly_mapped` | N | In both field maps + website; absent from `fieldInventory()` |
| *(inventory gap)* | Special Assessment Amount | `special_assessment_amount` | `special_assessment_amount` | `inventory_gap_correctly_mapped` | `special_assessment_amount` | `inventory_gap_correctly_mapped` | N | Absent from `fieldInventory()` |
| *(inventory gap)* | Special Assessment Description | `special_assessment_description` | `special_assessment_description` | `inventory_gap_correctly_mapped` | `special_assessment_description` | `inventory_gap_correctly_mapped` | N | Absent from `fieldInventory()` |

**Residential Seller safe: 38/43 (fieldInventory) + 12 inventory-gap correctly-mapped = 50 effective**  
**Residential Landlord safe: 37/43 (fieldInventory) + 9 inventory-gap correctly-mapped = 46 effective** (Landlord missing from fieldMap: lot_dimensions, roof_type, exterior_construction, foundation)

---

### Form 2: Rental

All address / waterfront / tax-legal / flood-zone / HOA-CDD / remarks rows are identical to Residential; only additions and differences are shown.

| MLS Section | MLS Field Label | Canonical Key | Seller Target Property | Seller Status | Landlord Target Property | Landlord Status | Norm Required | Notes |
|---|---|---|---|---|---|---|---|---|
| ADDRESS | Street Address–County (5 fields) | `address`–`county` | same as Residential | `mapped_correctly` | same | `mapped_correctly` | N | |
| LISTING INFORMATION | MLS # | `mls_number` | — | `intentional_exclusion` | — | `intentional_exclusion` | N | |
| LISTING INFORMATION | Monthly Rent | `price` | `maximum_budget` | `not_applicable` | `desired_rental_amount` | `mapped_correctly` | N | Landlord primary form; Seller can import price but semantics differ |
| PROPERTY DETAILS | Bedrooms–Carport (7 fields) | same keys | same props | `mapped_correctly` | same props | `mapped_correctly` | Y (pool/garage/carport) | |
| PROPERTY DETAILS | Furnished | `furnished` | `tenant_require` | `mapped_correctly` | `tenant_require` | `mapped_correctly` | Y | |
| PROPERTY DETAILS | Lot Dimensions | `lot_dimensions` | `lot_dimensions` | `mapped_correctly` | — | `missing_from_field_map` | N | Landlord gap |
| PROPERTY DETAILS | Lot Acreage | `lot_size_acres` | `total_acreage` | `mapped_correctly` | `total_acreage` | `mapped_correctly` | N | |
| PROPERTY DETAILS | Lot Size (Sq Ft) | `lot_size_sqft` | — | `missing_from_field_map` | — | `missing_from_field_map` | N | |
| PROPERTY DETAILS | Zoning | `zoning` | `zoning` | `mapped_correctly` | `zoning` | `mapped_correctly` | N | |
| INTERIOR / SYSTEMS | Air Conditioning | `air_conditioning` | `air_conditioning` | `mapped_correctly` | `air_conditioning` | `mapped_correctly` | N | |
| INTERIOR / SYSTEMS | Heating | `heating` | — | `missing_from_field_map` | — | `missing_from_field_map` | N | |
| INTERIOR / SYSTEMS | Interior Features | `interior_features` | `interior_features` | `mapped_correctly` | `interior_features` | `mapped_correctly` | N | |
| INTERIOR / SYSTEMS | Appliances | `appliances` | `appliances` | `mapped_correctly` | `appliances` | `mapped_correctly` | N | |
| WATERFRONT | Waterfront / Water Access / Water View | same keys | same props | `mapped_correctly` | same props | `mapped_correctly` | Y (waterfront) | |
| TAX / LEGAL + FLOOD ZONE + HOA / CDD | (all 15 fields) | same keys | same props | `mapped_correctly` | same props | `mapped_correctly` | varies | Identical to Residential |
| RENTAL / LEASE | Available Date | `available_date` | — | `not_applicable` | `available_date` | `mapped_correctly` | N | Rental-specific; seller has no available date concept |
| RENTAL / LEASE | Minimum Security Deposit | `minimum_security_deposit` | — | `not_applicable` | `security_deposit_amount` | `mapped_correctly` | N | |
| RENTAL / LEASE | Lease Amount Frequency | `lease_amount_frequency` | — | `not_applicable` | `lease_amount_frequency` | `mapped_correctly` | Y | Normalizer: lowercase |
| RENTAL / LEASE | Terms of Lease | `terms_of_lease` | — | `not_applicable` | `terms_of_lease` | `mapped_correctly` | N | Multi-select |
| RENTAL / LEASE | Tenant Pays | `tenant_pays` | — | `not_applicable` | `tenant_pays` | `mapped_correctly` | N | Multi-select |
| RENTAL / LEASE | Application Fee | `application_fee` | — | `not_applicable` | — | `intentional_exclusion` | N | Property absent on `LandlordOfferListing`; Rejected Mapping Candidate |
| RENTAL / LEASE | Rent Includes | `rent_includes` | — | `not_applicable` | `rent_includes` | `mapped_correctly` | N | Multi-select |
| RENTAL / LEASE | Pets Allowed | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | No parser branch; no app field |
| RENTAL / LEASE | Minimum Lease (Months) | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | No parser branch; no app field |
| REMARKS | Public Remarks | `description` | `additional_details` | `mapped_correctly` | `additional_details` | `mapped_correctly` | N | |
| REMARKS | Directions | `directions` | — | `missing_from_field_map` | — | `missing_from_field_map` | N | |
| *(inventory gap)* | Heating & Fuel / Roof / Construction / Foundation / Water / Sewer / Utilities / Sq Ft Source / Flood Insurance Required | same 9 keys | same props | same statuses | same props | same statuses | N | Identical to Residential inventory-gap rows |

**Rental Landlord safe: 44/50 (non-null keys, fieldInventory) + 6 inventory-gap = 50 effective**

---

### Form 3: Vacant Land

| MLS Section | MLS Field Label | Canonical Key | Seller Target | Seller Status | Landlord Target | Landlord Status | Norm Required | Notes |
|---|---|---|---|---|---|---|---|---|
| ADDRESS | Street Address–County (5) | `address`–`county` | same | `mapped_correctly` | same | `mapped_correctly` | N | |
| LISTING INFORMATION | MLS # | `mls_number` | — | `intentional_exclusion` | — | `intentional_exclusion` | N | |
| LISTING INFORMATION | List Price | `price` | `maximum_budget` | `mapped_correctly` | `desired_rental_amount` | `not_applicable` | N | |
| PROPERTY DETAILS | Lot Dimensions | `lot_dimensions` | `lot_dimensions` | `mapped_correctly` | — | `missing_from_field_map` | N | |
| PROPERTY DETAILS | Lot Acreage | `lot_size_acres` | `total_acreage` | `mapped_correctly` | `total_acreage` | `mapped_correctly` | N | |
| PROPERTY DETAILS | Lot Size (Sq Ft) | `lot_size_sqft` | — | `missing_from_field_map` | — | `missing_from_field_map` | N | |
| PROPERTY DETAILS | Zoning | `zoning` | `zoning` | `mapped_correctly` | `zoning` | `mapped_correctly` | N | |
| PROPERTY DETAILS | Lot Features | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | Cleared/Filled/Wooded; no parser branch |
| PROPERTY DETAILS | Road Surface Type | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | No parser branch |
| PROPERTY DETAILS | Utilities Available | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | Water/Sewer/Electric; no parser branch |
| PROPERTY DETAILS | Topography | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | No parser branch |
| WATERFRONT | Waterfront / Water Access / Water View | same keys | same props | `mapped_correctly` | same props | `mapped_correctly` | Y (waterfront) | |
| TAX / LEGAL + FLOOD ZONE + HOA / CDD | (all 15 fields) | same keys | same | `mapped_correctly` | same | `mapped_correctly` | varies | |
| REMARKS | Public Remarks | `description` | `additional_details` | `mapped_correctly` | `additional_details` | `mapped_correctly` | N | |
| REMARKS | Directions | `directions` | — | `missing_from_field_map` | — | `missing_from_field_map` | N | |

---

### Form 4: Income (Multi-Family)

| MLS Section | MLS Field Label | Canonical Key | Seller Target | Seller Status | Landlord Target | Landlord Status | Norm Required | Notes |
|---|---|---|---|---|---|---|---|---|
| ADDRESS | Street Address–County (5) | `address`–`county` | same | `mapped_correctly` | same | `mapped_correctly` | N | |
| LISTING INFORMATION | MLS # | `mls_number` | — | `intentional_exclusion` | — | `intentional_exclusion` | N | |
| LISTING INFORMATION | List Price | `price` | `maximum_budget` | `mapped_correctly` | `desired_rental_amount` | `not_applicable` | N | |
| PROPERTY DETAILS | Year Built | `year_built` | `year_built` | `mapped_correctly` | `year_built` | `mapped_correctly` | N | |
| PROPERTY DETAILS | Heated Sq Ft | `heated_sqft` | `minimum_heated_square` | `mapped_correctly` | `minimum_heated_square` | `mapped_correctly` | N | |
| PROPERTY DETAILS | Number of Units | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | No parser branch; no app field |
| PROPERTY DETAILS | Unit Types | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | 1BR/2BR mix; no parser branch |
| PROPERTY DETAILS | Lot Dimensions / Acreage / Sq Ft / Zoning | `lot_dimensions` / `lot_size_acres` / `lot_size_sqft` / `zoning` | see Residential | see Residential | see Residential | see Residential | N | Same gaps as Residential |
| FINANCIAL | Net Operating Income (NOI) | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | No app field |
| FINANCIAL | Annual Gross Income | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | Website has `gross_annual_income` but not mapped from MLS |
| FINANCIAL | Annual Expenses | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | Website has `annual_operating_expenses` but not mapped |
| FINANCIAL | Cap Rate | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | Website has `minimum_cap_rate` but not mapped from MLS |
| TAX / LEGAL + FLOOD ZONE + HOA / CDD | (all 15 fields) | same keys | same | `mapped_correctly` | same | `mapped_correctly` | varies | |
| REMARKS | Public Remarks / Directions | `description` / `directions` | see Residential | see Residential | see Residential | see Residential | N | |

> **Key gap:** Four high-value Income financial fields (`noi`, `gross_income`, `annual_expenses`, `cap_rate`) have no parser branches. The website has corresponding input fields (`minimum_annual_net_income`, `gross_annual_income`, `annual_operating_expenses`, `minimum_cap_rate`) but they are manually entered; no MLS-to-website bridge exists.

---

### Form 5: Commercial Sale

| MLS Section | MLS Field Label | Canonical Key | Seller Target | Seller Status | Landlord Target | Landlord Status | Norm Required | Notes |
|---|---|---|---|---|---|---|---|---|
| ADDRESS | Street Address–County (5) | `address`–`county` | same | `mapped_correctly` | same | `mapped_correctly` | N | |
| LISTING INFORMATION | MLS # / List Price | `mls_number` / `price` | see Residential | see Residential | see Residential | see Residential | N | |
| PROPERTY DETAILS | Year Built | `year_built` | `year_built` | `mapped_correctly` | `year_built` | `mapped_correctly` | N | |
| BUILDING DETAILS | Building Size (Sq Ft) | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | No parser branch; no app field |
| BUILDING DETAILS | Number of Bays | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | |
| BUILDING DETAILS | Number of Dock Doors | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | |
| BUILDING DETAILS | Ceiling Height (Ft) | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | |
| BUILDING DETAILS | Office Area (Sq Ft) | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | |
| BUILDING DETAILS | Parking Spaces | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | |
| PROPERTY DETAILS | Lot Dimensions / Acreage / Sq Ft / Zoning | see Residential | same | see Residential | same | see Residential | N | |
| FINANCIAL | Net Operating Income (NOI) | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | |
| FINANCIAL | Cap Rate | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | |
| WATERFRONT + TAX/LEGAL + FLOOD ZONE + HOA/CDD + REMARKS | (18 fields) | same keys | same | see Residential | same | see Residential | varies | |

---

### Form 6: Commercial Lease

| MLS Section | MLS Field Label | Canonical Key | Seller Target | Seller Status | Landlord Target | Landlord Status | Norm Required | Notes |
|---|---|---|---|---|---|---|---|---|
| ADDRESS | Street Address–County (5) | `address`–`county` | same | `mapped_correctly` | same | `mapped_correctly` | N | |
| LISTING INFORMATION | MLS # | `mls_number` | — | `intentional_exclusion` | — | `intentional_exclusion` | N | |
| LISTING INFORMATION | Monthly Rent / Lease Rate | `price` | `maximum_budget` | `not_applicable` | `desired_rental_amount` | `mapped_correctly` | N | |
| BUILDING DETAILS | Building Size / Office Area / Parking Spaces | `null` (3) | — | `missing_from_parser` | — | `missing_from_parser` | N | No parser branch; no app fields |
| RENTAL / LEASE | Available Date | `available_date` | — | `not_applicable` | `available_date` | `mapped_correctly` | N | |
| RENTAL / LEASE | Tenant Pays | `tenant_pays` | — | `not_applicable` | `tenant_pays` | `mapped_correctly` | N | |
| RENTAL / LEASE | Rent Includes | `rent_includes` | — | `not_applicable` | `rent_includes` | `mapped_correctly` | N | |
| RENTAL / LEASE | Lease Rate Type (NNN / Gross / Modified Gross) | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | High-value commercial field; no app equivalent |
| RENTAL / LEASE | Minimum Lease Term | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | |
| RENTAL / LEASE | Rent Rate (per Sq Ft) | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | |
| RENTAL / LEASE | Build-Out Allowance | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | |
| TAX / LEGAL + FLOOD ZONE + HOA / CDD | (15 fields) | same keys | same | `mapped_correctly` | same | `mapped_correctly` | varies | |
| REMARKS | Public Remarks / Directions | same keys | see Residential | see Residential | see Residential | see Residential | N | |

---

### Form 7: Business Opportunity

| MLS Section | MLS Field Label | Canonical Key | Seller Target | Seller Status | Landlord Target | Landlord Status | Norm Required | Notes |
|---|---|---|---|---|---|---|---|---|
| ADDRESS | Street Address–County (5) | `address`–`county` | same | `mapped_correctly` | same | `mapped_correctly` | N | |
| LISTING INFORMATION | MLS # | `mls_number` | — | `intentional_exclusion` | — | `intentional_exclusion` | N | |
| LISTING INFORMATION | Asking Price | `price` | `maximum_budget` | `mapped_correctly` | `desired_rental_amount` | `not_applicable` | N | |
| BUSINESS DETAILS | Business Type | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | No app field |
| BUSINESS DETAILS | Annual Revenue | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | Website has `annual_revenue` but not mapped from MLS |
| BUSINESS DETAILS | Annual Net Income | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | Website has `minimum_annual_net_income` but not mapped |
| BUSINESS DETAILS | Inventory Included Y/N | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | Website has `inventory_value` but not mapped |
| BUSINESS DETAILS | Number of Employees | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | Website has `employee_count` but not mapped |
| BUSINESS DETAILS | Seller Financing Y/N | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | Website has offered_financing but not mapped |
| BUSINESS DETAILS | Lease Type | `null` | — | `missing_from_parser` | — | `missing_from_parser` | N | No app field |
| TAX / LEGAL | Tax ID–Special Assessments (9 fields) | same keys | same | `mapped_correctly` | same | `mapped_correctly` | varies | |
| REMARKS | Public Remarks / Directions | same keys | see Residential | see Residential | see Residential | see Residential | N | |

---

## TABLE 3A — Special Attention Fields: Match Classification Audit

> These 40+ fields require extra scrutiny for array handling, enum translation, and value fidelity.  
> All classifications are **Code Inspection (CI) only** — no live import was run. See TABLE 3B for the full pipeline trace.  
>
> **Match Classification scale:**  
> - `Exact Match` — value arrives at the correct website field; transformation is lossless per code analysis  
> - `Partial Match` — value arrives at the correct website field but carries a live-testable risk (delimiter variance, enum gap, or normalizer edge case per code analysis)  
> - `Incorrect Match` — value is written to the wrong website field or is semantically incorrect  
> - `Not Imported` — pipeline is broken at or before field map; value is never written to any website field  

| MLS Field | Canonical Key | Status | Match Classification | Representative MLS Sample Value | Expected Website Value | Notes |
|---|---|---|---|---|---|---|
| Waterfront Y/N | `waterfront` | `mapped_correctly` | `Exact Match` | `Yes` | `yes` | Normalizer: Yes/Y/TRUE/1 → `yes`; No/N/FALSE/0 → `no` |
| Water Access | `water_access` | `mapped_correctly` | `Partial Match` | `Lake Front,Freshwater Canal w/Lift to Saltwater` | `["Lake Front","Freshwater Canal w/Lift to Saltwater"]` | Comma-split correct; Stellar MLS occasionally uses semicolons on multi-value exports — delimiter risk is LT |
| Water View | `water_view` | `mapped_correctly` | `Partial Match` | `Lake,Pool` | `["Lake","Pool"]` | Same comma-split risk as Water Access |
| Water Frontage | — | `missing_from_parser` | `Not Imported` | `Freshwater` | — | Separate MLS field from Waterfront Y/N; no parser branch |
| Interior Features | `interior_features` | `mapped_correctly` | `Partial Match` | `Ceiling Fans(s),Crown Molding,High Ceilings,Walk-In Closet(s)` | `["Ceiling Fans(s)","Crown Molding","High Ceilings","Walk-In Closet(s)"]` | Comma-split handles parenthetical values correctly; semicolon-delimited MLS exports would fail |
| Exterior Features | — | `missing_from_parser` | `Not Imported` | `Irrigation System,Lighting,Sliding Doors` | — | No parser branch; no app field |
| Community Features | — | `missing_from_parser` | `Not Imported` | `Deed Restrictions,Fitness Center,Pool` | — | No parser branch; no app field |
| View Type | — | `missing_from_parser` | `Not Imported` | `Pool,Water` | — | No parser branch; no app field |
| Floor Covering / Flooring | — | `missing_from_parser` | `Not Imported` | `Carpet,Tile` | — | No parser branch; no app field |
| Fireplace Y/N | — | `missing_from_parser` | `Not Imported` | `Yes` | — | No parser branch; Residential only |
| Patio and Porch Features | — | `missing_from_parser` | `Not Imported` | `Covered,Patio,Screened` | — | No parser branch; no app field |
| Parking Features | — | `missing_from_parser` | `Not Imported` | `Garage Door Opener,Ground Level` | — | No parser branch; no app field |
| Security Features | — | `missing_from_parser` | `Not Imported` | `Security System,Smoke Detector(s)` | — | No parser branch; Residential only |
| Flood Zone Code | `flood_zone_code` | `mapped_correctly` | `Exact Match` | `AE` | `AE` | Normalizer uppercases; values X/AE/A/AH/AO/VE/V/D pass through correctly; unknown values stored as-is |
| Flood Zone Date | `flood_zone_date` | `mapped_correctly` | `Exact Match` | `20210901` | `20210901` | Date string passed through; format variance is LT risk |
| Flood Zone Panel | `flood_zone_panel` | `mapped_correctly` | `Exact Match` | `12021C0404G` | `12021C0404G` | String passed through unchanged |
| Flood Insurance Required | `flood_insurance_required` | `inventory_gap_correctly_mapped` | `Exact Match` | `Yes` | `yes` | Boolean normalizer; absent from `fieldInventory()` |
| Lot Size Sq Ft | `lot_size_sqft` | `missing_from_field_map` | `Not Imported` | `8500` | — | Parsed; no field-map entry for either role; silently discarded |
| Lot Size Acres | `lot_size_acres` | `mapped_correctly` | `Exact Match` | `0.19` | `0.19` | Numeric passed through; maps to `total_acreage` |
| Lot Dimensions | `lot_dimensions` | S=`mapped_correctly` L=`missing_from_field_map` | `Exact Match` (S) · `Not Imported` (L) | `100x150` | Seller: `100x150` · Landlord: lost | Landlord component has no `$lot_dimensions` property |
| Appliances | `appliances` | `mapped_correctly` | `Exact Match` | `Dishwasher,Disposal,Microwave,Range,Refrigerator` | `["Dishwasher","Disposal","Microwave","Range","Refrigerator"]` | Stellar MLS consistently uses commas for appliances |
| A/C | `air_conditioning` | `mapped_correctly` | `Exact Match` | `Central Air` | `["Central Air"]` | Single or comma-list; maps to `air_conditioning` array via `*` prefix |
| Heating (system type) | `heating` | `missing_from_field_map` | `Not Imported` | `Central,Electric` | — | Parsed; no field-map entry for either role; distinct from Heating & Fuel combined field |
| Heating & Fuel (combined) | `heating_fuel` | `inventory_gap_correctly_mapped` | `Exact Match` | `Central,Electric` | `["Central","Electric"]` | Comma-split; Seller→`heating_and_fuel`, Landlord→`heating_fuel` (different property names) |
| Roof Type | `roof_type` | S=`inventory_gap_correctly_mapped` L=`missing_from_field_map` | `Exact Match` (S) · `Not Imported` (L) | `Shingle` or `Tile,Metal` | Seller: `["Shingle"]` / `["Tile","Metal"]` · Landlord: lost | Seller fully implemented; Landlord has no property, save/load, blade, or field-map entry |
| Exterior Construction | `exterior_construction` | S=`inventory_gap_correctly_mapped` L=`missing_from_field_map` | `Exact Match` (S) · `Not Imported` (L) | `Block,Stucco` | Seller: `["Block","Stucco"]` · Landlord: lost | Same gap as Roof Type |
| Foundation | `foundation` | S=`inventory_gap_correctly_mapped` L=`missing_from_field_map` | `Exact Match` (S) · `Not Imported` (L) | `Slab` | Seller: `["Slab"]` · Landlord: lost | Same gap as Roof Type |
| Water Source | `water` | `inventory_gap_correctly_mapped` | `Exact Match` | `Public` | `["Public"]` | Absent from `fieldInventory()`; comma-split for multi-value |
| Sewer | `sewer` | `inventory_gap_correctly_mapped` | `Exact Match` | `Public Sewer` | `["Public Sewer"]` | Absent from `fieldInventory()` |
| Utilities | `utilities` | `inventory_gap_correctly_mapped` | `Exact Match` | `BB/HS Internet Available,Cable Available,Electricity Connected` | `["BB/HS Internet Available","Cable Available","Electricity Connected"]` | Absent from `fieldInventory()`; Landlord maps to `property_utilities`, not `utilities` |
| Sq Ft Heated Source | `sqft_heated_source` | `inventory_gap_correctly_mapped` | `Exact Match` | `Public Records` | `Public Records` | Enum string passed through; absent from `fieldInventory()` |
| HOA Y/N | `has_hoa` | `mapped_correctly` | `Exact Match` | `Yes` | `yes` | Boolean normalizer |
| Association Name | `association_name` | `mapped_correctly` | `Exact Match` | `Pinehurst HOA` | `Pinehurst HOA` | String passed through |
| Association Fee | `association_fee_amount` | `mapped_correctly` | `Exact Match` | `350` | `350` | Numeric passed through |
| Association Fee Frequency | `association_fee_frequency` | `mapped_correctly` | `Partial Match` | `Monthly` | `monthly` | Normalizer lowercases; `<select>` option values must be lowercase to match — LT needed |
| CDD Y/N | `has_cdd` | `mapped_correctly` | `Exact Match` | `No` | `no` | Boolean normalizer |
| Annual CDD Fee | `annual_cdd_fee` | `mapped_correctly` | `Exact Match` | `1200` | `1200` | Numeric passed through |
| Furnished | `furnished` | `mapped_correctly` | `Exact Match` | `Unfurnished` | `Unfurnished` | `normalizeFurnishing()` maps standard Stellar values: Furnished/Turnkey, Unfurnished, Negotiable |
| Monthly Rent (Landlord) | `price` | `mapped_correctly` | `Exact Match` | `2800` | `desired_rental_amount = 2800` | Rental form only; Seller maps `price` to `maximum_budget` |
| Available Date | `available_date` | `mapped_correctly` | `Exact Match` | `2026-08-01` | `2026-08-01` | Date string passed through; Landlord only |
| Minimum Security Deposit | `minimum_security_deposit` | `mapped_correctly` | `Exact Match` | `2800` | `2800` | Numeric passed through |
| Lease Amount Frequency | `lease_amount_frequency` | `mapped_correctly` | `Partial Match` | `Monthly` | `monthly` | Same normalizer/select risk as Association Fee Frequency |
| Terms of Lease | `terms_of_lease` | `mapped_correctly` | `Partial Match` | `12 Months,Month-to-Month` | `["12 Months","Month-to-Month"]` | Comma-split; Landlord only |
| Rent Includes | `rent_includes` | `mapped_correctly` | `Partial Match` | `Cable TV,Internet,Trash Collection` | `["Cable TV","Internet","Trash Collection"]` | Comma-split; Landlord only |
| Tenant Pays | `tenant_pays` | `mapped_correctly` | `Partial Match` | `Electricity,Gas,Telephone` | `["Electricity","Gas","Telephone"]` | Comma-split; Landlord only |
| Public Remarks | `description` | `mapped_correctly` | `Exact Match` | `Beautiful 3/2 pool home in quiet neighborhood...` | same text → `additional_details` | No truncation limit found in code; TEXT column risk is LT |
| Directions | `directions` | `missing_from_field_map` | `Not Imported` | `From US-41 head east on Pine Ave...` | — | Parsed; no field-map entry; discarded for both roles |
| Application Fee | `application_fee` | `intentional_exclusion` | `Not Imported` | `75` | — | Property absent on `LandlordOfferListing`; Rejected Mapping Candidate |
| Pets Allowed | — | `missing_from_parser` | `Not Imported` | `Yes` | — | No parser branch; Rental form only; high landlord value |
| Minimum Lease (Months) | — | `missing_from_parser` | `Not Imported` | `6` | — | No parser branch; no app field |
| Year Built | `year_built` | `mapped_correctly` | `Exact Match` | `1985` | `1985` | Integer passed through; both roles |
| Pool Y/N | `pool` | `mapped_correctly` | `Exact Match` | `Yes` | `yes` | Boolean normalizer |
| Garage Y/N | `garage` | `mapped_correctly` | `Exact Match` | `Yes` | `yes` | Boolean normalizer |
| Carport Y/N | `carport` | `mapped_correctly` | `Exact Match` | `No` | `no` | Boolean normalizer |
| Accessibility Features | — | `missing_from_parser` | `Not Imported` | `Accessible Approach with Ramp` | — | No parser branch; no app field |
| Green / Energy Features | — | `missing_from_parser` | `Not Imported` | `Solar Panels,Tankless Water Heater` | — | No parser branch |
| Dock / Boat Features | — | `missing_from_parser` | `Not Imported` | `Lift - Covered` | — | No parser branch |
| Road Frontage / Easements | — | `missing_from_parser` | `Not Imported` | `City Street` | — | No parser branch; Vacant Land only |

---

## TABLE 3B — Full Pipeline Trace (Parser → Preview → Apply → Save → Reload)

> **Legend:** ✅ CI = code-inspection confirmed · ⚠️ CI = code-inspection confirmed, risk noted · ❌ = fails at this stage · ⊘ = stage not reached (upstream failure) · LT = Live Trace required for full confidence  
> **Verification** column: CI = static code only · CI+LT = also needs a live run to fully confirm

| MLS Field | Canonical Key | Role | Parser | Normalizer | Field Map | Preview (prop_exists) | Apply Selected | wire:model Save | Reload | Risk Notes | Verification |
|---|---|---|---|---|---|---|---|---|---|---|---|
| List Price | `price` | S | ✅ CI | — | ✅ CI → `maximum_budget` | ✅ CI | ✅ CI | ✅ CI | ✅ CI | None | CI |
| List Price | `price` | L | ✅ CI | — | ✅ CI → `desired_rental_amount` | ✅ CI | ✅ CI | ✅ CI | ✅ CI | None | CI |
| Bedrooms | `bedrooms` | S+L | ✅ CI | — | ✅ CI | ✅ CI | ✅ CI | ✅ CI | ✅ CI | None | CI |
| Bathrooms Total | `bathrooms_total` | S+L | ✅ CI | — | ✅ CI | ✅ CI | ✅ CI | ✅ CI | ✅ CI | None | CI |
| Year Built | `year_built` | S+L | ✅ CI | — | ✅ CI | ✅ CI | ✅ CI | ✅ CI | ✅ CI | None | CI |
| Sq Ft Heated | `sqft_heated` | S+L | ✅ CI | — | ✅ CI | ✅ CI | ✅ CI | ✅ CI | ✅ CI | None | CI |
| **Waterfront Y/N** | `waterfront` | S+L | ✅ CI | ✅ CI — boolean coercion (Yes/No/Y/N/TRUE/1 → yes/no) | ✅ CI | ✅ CI | ✅ CI | ✅ CI | ✅ CI | Boolean coercion covers known variants; unusual MLS formats risk wrong value | CI |
| **Water Access** | `water_access` | S+L | ✅ CI | — | ✅ CI → `*water_access` | ✅ CI | ⚠️ CI — comma-split; correctness depends on MLS delimiter consistency | ✅ CI | ✅ CI | Multi-value split: MLS uses `;` on some exports vs `,` — if wrong delimiter, all values land as one string | CI+LT |
| **Water View** | `water_view` | S+L | ✅ CI | — | ✅ CI → `*water_view` | ✅ CI | ⚠️ CI — same comma-split risk | ✅ CI | ✅ CI | Same delimiter risk as water_access | CI+LT |
| **Interior Features** | `interior_features` | S+L | ✅ CI | — | ✅ CI → `*interior_features` | ✅ CI | ⚠️ CI — comma-split; long MLS strings may contain "Feature, Sub-Feature" patterns that over-split | ✅ CI | ✅ CI | Long interior feature strings: "Ceiling Fans(s), Crown Molding, High Ceilings" — split is correct; sub-parenthetical content is preserved | CI+LT |
| **Appliances** | `appliances` | S+L | ✅ CI | — | ✅ CI → `*appliances` | ✅ CI | ⚠️ CI — comma-split | ✅ CI | ✅ CI | Verify MLS uses comma not semicolon separator | CI+LT |
| **A/C** | `air_conditioning` | S+L | ✅ CI | — | ✅ CI → `*air_conditioning` | ✅ CI | ⚠️ CI — comma-split | ✅ CI | ✅ CI | Stellar MLS typically uses comma; low risk | CI+LT |
| **Heating & Fuel (combined)** | `heating_fuel` | S | ✅ CI | — | ✅ CI → `*heating_and_fuel` | ✅ CI | ⚠️ CI — comma-split | ✅ CI | ✅ CI | Absent from fieldInventory(); not shown in coverage reports | CI+LT |
| **Heating & Fuel (combined)** | `heating_fuel` | L | ✅ CI | — | ✅ CI → `*heating_fuel` | ✅ CI | ⚠️ CI — comma-split | ✅ CI | ✅ CI | Different property name from Seller (`heating_fuel` vs `heating_and_fuel`) | CI+LT |
| **Heating system type** | `heating` | S+L | ✅ CI | — | ❌ `missing_from_field_map` | ⊘ | ⊘ | ⊘ | ⊘ | Value extracted but silently discarded; never reaches preview | CI |
| **Roof Type** | `roof_type` | S | ✅ CI | — | ✅ CI → `*roof_type` | ✅ CI | ⚠️ CI | ✅ CI | ✅ CI | Absent from fieldInventory() | CI+LT |
| **Roof Type** | `roof_type` | L | ✅ CI | — | ❌ `missing_from_field_map` | ⊘ | ⊘ | ⊘ | ⊘ | Landlord silently discards; full implementation required (see Wiring Gaps section) | CI |
| **Exterior Construction** | `exterior_construction` | S | ✅ CI | — | ✅ CI → `*exterior_construction` | ✅ CI | ⚠️ CI | ✅ CI | ✅ CI | Absent from fieldInventory() | CI+LT |
| **Exterior Construction** | `exterior_construction` | L | ✅ CI | — | ❌ `missing_from_field_map` | ⊘ | ⊘ | ⊘ | ⊘ | Landlord silently discards | CI |
| **Water Source** | `water` | S+L | ✅ CI | — | ✅ CI → `*water` | ✅ CI | ⚠️ CI | ✅ CI | ✅ CI | Absent from fieldInventory() | CI+LT |
| **Sewer** | `sewer` | S+L | ✅ CI | — | ✅ CI → `*sewer` | ✅ CI | ⚠️ CI | ✅ CI | ✅ CI | Absent from fieldInventory() | CI+LT |
| **Utilities** | `utilities` | S | ✅ CI | — | ✅ CI → `*utilities` | ✅ CI | ⚠️ CI | ✅ CI | ✅ CI | Landlord uses different property name `property_utilities` | CI+LT |
| **Utilities** | `utilities` | L | ✅ CI | — | ✅ CI → `*property_utilities` | ✅ CI | ⚠️ CI | ✅ CI | ⚠️ CI | Two landlord properties (`$utilities` string legacy + `$property_utilities` array); ensure reload reads `property_utilities` not legacy `$utilities` | CI+LT |
| **Flood Zone Code** | `flood_zone_code` | S+L | ✅ CI | ✅ CI — uppercase | ✅ CI | ✅ CI | ✅ CI | ✅ CI | ✅ CI | Unknown codes pass through as-is; may display as "Other" on form | CI |
| **Flood Zone Date** | `flood_zone_date` | S+L | ✅ CI | — | ✅ CI | ✅ CI | ✅ CI | ✅ CI | ✅ CI | Date string passed through; no validation of format | CI+LT |
| **Flood Zone Panel** | `flood_zone_panel` | S+L | ✅ CI | — | ✅ CI | ✅ CI | ✅ CI | ✅ CI | ✅ CI | None | CI |
| **Flood Insurance Required** | `flood_insurance_required` | S+L | ✅ CI | ✅ CI — boolean | ✅ CI | ✅ CI | ✅ CI | ✅ CI | ✅ CI | Absent from fieldInventory() | CI |
| **HOA Y/N** | `has_hoa` | S+L | ✅ CI | ✅ CI — boolean | ✅ CI | ✅ CI | ✅ CI | ✅ CI | ✅ CI | None | CI |
| **Association Name** | `association_name` | S+L | ✅ CI | — | ✅ CI | ✅ CI | ✅ CI | ✅ CI | ✅ CI | None | CI |
| **Association Fee** | `association_fee_amount` | S+L | ✅ CI | — | ✅ CI | ✅ CI | ✅ CI | ✅ CI | ✅ CI | None | CI |
| **Association Fee Freq** | `association_fee_frequency` | S+L | ✅ CI | ✅ CI — lowercased (Monthly/Quarterly/Annually) | ✅ CI | ✅ CI | ✅ CI | ✅ CI | ⚠️ CI | MLS may emit "Monthly" → normalizer → "monthly"; confirm `<select>` option values are lowercase | CI+LT |
| **CDD Y/N** | `has_cdd` | S+L | ✅ CI | ✅ CI — boolean | ✅ CI | ✅ CI | ✅ CI | ✅ CI | ✅ CI | None | CI |
| **Annual CDD Fee** | `annual_cdd_fee` | S+L | ✅ CI | — | ✅ CI | ✅ CI | ✅ CI | ✅ CI | ✅ CI | None | CI |
| **Rent Includes** | `rent_includes` | L | ✅ CI | — | ✅ CI → `*rent_includes` | ✅ CI | ⚠️ CI — comma-split | ✅ CI | ✅ CI | Rental-only; verify property exists on LandlordOfferListing | CI+LT |
| **Tenant Pays** | `tenant_pays` | L | ✅ CI | — | ✅ CI → `*tenant_pays` | ✅ CI | ⚠️ CI — comma-split | ✅ CI | ✅ CI | Rental-only | CI+LT |
| **Furnished** | `furnished` | S+L | ✅ CI | ✅ CI — normalizeFurnishing() | ✅ CI | ✅ CI | ✅ CI | ✅ CI | ✅ CI | Normalizer maps "Furnished/Turnkey", "Unfurnished", "Negotiable" | CI |
| **Public Remarks** | `description` | S+L | ✅ CI | — | ✅ CI → `additional_details` | ✅ CI | ✅ CI | ✅ CI | ✅ CI | Long text; no truncation limit found in code; risk if DB column is VARCHAR not TEXT | CI+LT |
| **Lot Size Sq Ft** | `lot_size_sqft` | S+L | ✅ CI | — | ❌ `missing_from_field_map` | ⊘ | ⊘ | ⊘ | ⊘ | Parsed, silently discarded | CI |
| **Lot Dimensions** | `lot_dimensions` | S | ✅ CI | — | ✅ CI | ✅ CI | ✅ CI | ✅ CI | ✅ CI | None | CI |
| **Lot Dimensions** | `lot_dimensions` | L | ✅ CI | — | ❌ `missing_from_field_map` | ⊘ | ⊘ | ⊘ | ⊘ | Landlord silently discards | CI |
| **Special Assessments Y/N** | `has_special_assessments` | S+L | ✅ CI | — | ✅ CI | ✅ CI | ✅ CI | ✅ CI | ✅ CI | Absent from fieldInventory() | CI |

**Summary of TABLE 3B:**  
- **8 fields** require Live Trace to fully confirm due to comma-split delimiter sensitivity (water_access, water_view, interior_features, appliances, air_conditioning, rent_includes, tenant_pays, utilities)  
- **4 fields** silently fail for Landlord (roof_type, exterior_construction, foundation, lot_dimensions) — pipeline verified broken at Field Map stage  
- **1 field** (heating/system type) silently fails for both roles — pipeline verified broken at Field Map stage  
- All remaining fields are CI-confirmed end-to-end  

---

## TABLE 4 — Missing Website Fields

MLS fields that are parsed (or parseable) but have **no corresponding website field** on Seller or Landlord listings. These are fields a user would want to see pre-filled but cannot be.

### 4A: Parsed but No Field Map Entry (Parser emits key → silently discarded)

| Canonical Key | MLS Label | Forms | Seller | Landlord | Impact | Recommended Action |
|---|---|---|---|---|---|---|
| `heating` | Heating (system type) | Residential, Rental | discarded | discarded | High — heating system type never autofills | Add `heating` property to both Livewire components and blade; map in field map |
| `lot_size_sqft` | Lot Size (Sq Ft) | All forms | discarded | discarded | High — square footage lost even when acreage present | Add `lot_size_sqft` meta property + blade field; map in field map |
| `directions` | Directions | All forms | discarded | discarded | Low — navigational info not displayed on listing | Accept as intentional omission, or add hidden meta storage |
| `lot_dimensions` | Lot Dimensions | All forms | mapped ✓ | **discarded** | High — Landlord silently discards lot dims | Add `lot_dimensions` property to `LandlordOfferListing`; add blade binding; add to landlord field map |
| `roof_type` | Roof Type | Residential, Rental | mapped ✓ | **discarded** | High — Landlord cannot receive roof type from MLS | Declare `public array $roof_type = []` + `$other_roof_type` on `LandlordOfferListing`; add JSON save/load EAV; add Select2 multi-select blade input with same enum options as Seller; add validation rules; add `'roof_type' => '*roof_type'` to Landlord field map — no shortcut; see Wiring Gaps section |
| `exterior_construction` | Exterior Construction | Residential, Rental | mapped ✓ | **discarded** | High | Same full scope as Roof Type: property + save/load + blade + validation + field-map entry |
| `foundation` | Foundation Details | Residential, Rental | mapped ✓ | **discarded** | Medium | Same full scope as Roof Type |

### 4B: Not Parsed at All (canonical_key = null; missing_from_parser)

| MLS Label | Forms | Seller Impact | Landlord Impact | Website Field Exists? | Recommended Action |
|---|---|---|---|---|---|
| Exterior Features | Residential, Rental, Vacant Land | High — descriptive feature list lost | High | No | Add parser branch + app property + blade field |
| Community Features | Residential, Rental, Vacant Land | High | High | No | Add parser branch + app property + blade field |
| Floor Covering / Flooring | Residential, Rental | Medium | Medium | No | Add parser branch + app property |
| Fireplace Y/N | Residential | Medium | N/A | No | Add parser branch + app property |
| Patio and Porch Features | Residential, Rental | Medium | Medium | No | Add parser branch + app property |
| Parking Features | Residential, Rental | Medium | Medium | No | Add parser branch + app property |
| Security Features | Residential | Low | N/A | No | Add parser branch + app property |
| View Type | Residential, Rental, Vacant Land, Commercial Sale | Medium | Medium | No | Add parser branch + app property |
| Pets Allowed | Rental | N/A | Medium — landlord's pet policy | No | Add parser branch + `pets_allowed` meta |
| Minimum Lease (Months) | Rental | N/A | Medium | No | Already have `terms_of_lease`; may overlap |
| Number of Units | Income | High — key multi-family metric | High | No | Add parser branch + app property |
| Unit Types | Income | Medium | Medium | No | Add parser branch |
| NOI / Annual Gross Income / Annual Expenses / Cap Rate | Income, Commercial Sale | High — financial summary lost | High | Yes (manual entry fields exist) | Add parser branches to connect MLS → existing website fields |
| Building Size / Bays / Dock Doors / Ceiling Height / Office Area / Parking Spaces | Commercial Sale, Commercial Lease | High | High | Partial | Add parser branches; some website fields present |
| Lease Rate Type (NNN / Gross / Modified Gross) | Commercial Lease | N/A | High — critical for commercial tenants | No | Add parser branch + `lease_rate_type` meta |
| Rent Rate per Sq Ft | Commercial Lease | N/A | High | No | Add parser branch + `rent_rate_sqft` meta |
| Build-Out Allowance | Commercial Lease | N/A | Medium | No | Add parser branch |
| Business Type | Business Opportunity | High | N/A | No | Add parser branch |
| Annual Revenue / Annual Net Income | Business Opportunity | High | N/A | Yes (manual entry) | Add parser branches to connect MLS → existing fields |
| Inventory Included / Employee Count | Business Opportunity | Medium | N/A | Yes (manual entry) | Add parser branches |
| Lot Features / Road Surface / Utilities Available / Topography | Vacant Land | Medium | Medium | No | Add parser branches + app properties |

### 4C: fieldInventory() Coverage Gaps (Inventory Reporter Blind Spots)

These 12 fields ARE correctly mapped (parser → field map → Livewire property → blade binding) but are **absent from `fieldInventory()`** — making the `MlsCoverageReporter` output misleading. The reporter currently shows lower coverage than reality and will flag these as "missing" if ever audited against the live system.

| Canonical Key | MLS Label | Seller Mapping | Landlord Mapping | Priority |
|---|---|---|---|---|
| `heating_fuel` | Heating & Fuel | `*heating_and_fuel` ✓ | `*heating_fuel` ✓ | High |
| `roof_type` | Roof Type | `*roof_type` ✓ | — (not mapped) | High |
| `exterior_construction` | Construction Status | `*exterior_construction` ✓ | — (not mapped) | High |
| `foundation` | Foundation Details | `*foundation` ✓ | — (not mapped) | Medium |
| `water` | Water Source | `*water` ✓ | `*water` ✓ | High |
| `sewer` | Sewer | `*sewer` ✓ | `*sewer` ✓ | High |
| `utilities` | Utilities | `*utilities` ✓ | `*property_utilities` ✓ | High |
| `sqft_heated_source` | Sq Ft Heated Source | `sqft_heated_source` ✓ | `sqft_heated_source` ✓ | Medium |
| `flood_insurance_required` | Flood Insurance Required | `flood_insurance_required` ✓ | `flood_insurance_required` ✓ | High |
| `has_special_assessments` | Special Assessments Y/N | `has_special_assessments` ✓ | `has_special_assessments` ✓ | High |
| `special_assessment_amount` | Special Assessment Amount | `special_assessment_amount` ✓ | `special_assessment_amount` ✓ | High |
| `special_assessment_description` | Special Assessment Description | `special_assessment_description` ✓ | `special_assessment_description` ✓ | Medium |

**Recommended:** Add all 12 to the appropriate `fieldInventory()` form sections (Residential and Rental at minimum) so the reporter reflects accurate coverage. This is a zero-risk code-only change (documentation arrays only).

---

## TABLE 5 — Top Gaps Ranked by Impact

Scoring dimensions (each rated 0–3): **MLS Freq** = how often field appears on listings; **Consumer Value** = how much buyers/tenants care; **Autofill Value** = time saved on data entry; **Listing Presentation** = improves display quality; **Future Matching** = useful for buyer/tenant bid-matching; **Future AI** = useful for Ask AI or recommendations; **Ease** = implementation effort (3=easy/low-effort, 0=hard/high-effort). **Score** = sum (max 21).

| Rank | Gap | Key / Form | Roles | Gap Type | MLS Freq | Consumer | Autofill | Presentation | Matching | AI | Ease | Score | Priority |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | **Community Features** — gated, pool, tennis, playground | — / Res, Rental | S+L | `missing_from_parser` | 3 | 3 | 3 | 3 | 3 | 3 | 2 | 20 | Critical |
| 2 | **Exterior Features** — stone, brick, stucco, siding | — / Res, Rental, VL | S+L | `missing_from_parser` | 3 | 3 | 3 | 3 | 2 | 3 | 2 | 19 | Critical |
| 3 | **Heating (system type)** — Central, Heat Pump, Electric | `heating` / Res, Rental | S+L | `missing_from_field_map` | 3 | 2 | 3 | 2 | 3 | 3 | 3 | 19 | Critical |
| 4 | **Lot Size (Sq Ft)** — standalone sqft value | `lot_size_sqft` / All | S+L | `missing_from_field_map` | 3 | 3 | 3 | 2 | 3 | 2 | 3 | 19 | Critical |
| 5 | **Pets Allowed** — key Rental landlord policy field | — / Rental | L only | `missing_from_parser` | 3 | 3 | 3 | 2 | 3 | 2 | 3 | 19 | Critical |
| 6 | **Number of Units** — core Income/multi-family field | — / Income | S+L | `missing_from_parser` | 3 | 3 | 3 | 3 | 3 | 2 | 3 | 20 | Critical (income) |
| 7 | **Parking Features** — garage type, covered, tandem | — / Res, Rental | S+L | `missing_from_parser` | 3 | 3 | 3 | 2 | 3 | 2 | 2 | 18 | High |
| 8 | **Income/Business financial fields** (NOI, Revenue, Cap Rate) — website inputs exist, no MLS bridge | — / Income, Biz, Comm. Sale | S | `missing_from_parser` | 2 | 2 | 3 | 3 | 3 | 3 | 2 | 18 | High |
| 9 | **Lease Rate Type** (NNN / Gross / Modified Gross) — Commercial Lease critical | — / Comm. Lease | L only | `missing_from_parser` | 3 | 3 | 3 | 3 | 2 | 2 | 2 | 18 | High (commercial) |
| 10 | **View Type** — water, golf, city, garden | — / Res, Rental, VL, Comm. Sale | S+L | `missing_from_parser` | 2 | 3 | 2 | 3 | 2 | 3 | 2 | 17 | High |
| 11 | **Lot Dimensions** for Landlord — Seller already has it | `lot_dimensions` / All | L only | `missing_from_field_map` | 3 | 2 | 3 | 2 | 2 | 1 | 3 | 16 | High |
| 12 | **Roof Type** for Landlord — Seller already has it | `roof_type` / Res, Rental | L only | `missing_from_field_map` | 3 | 2 | 3 | 2 | 2 | 2 | 3 | 17 | High |
| 13 | **Exterior Construction** for Landlord — Seller already has it | `exterior_construction` / Res, Rental | L only | `missing_from_field_map` | 3 | 2 | 3 | 2 | 2 | 2 | 3 | 17 | High |
| 14 | **Floor Covering / Flooring** — carpet, tile, hardwood, LVP | — / Res, Rental | S+L | `missing_from_parser` | 3 | 2 | 3 | 2 | 2 | 3 | 2 | 17 | High |
| 15 | **Commercial Building Details** (Bldg Size, Bays, Dock Doors, Ceiling Ht, Office Area, Parking) | — / Comm. Sale, Comm. Lease | S+L | `missing_from_parser` | 3 | 3 | 3 | 3 | 2 | 2 | 1 | 17 | High (commercial) |
| 16 | **Patio and Porch Features** — screened, covered, lanai | — / Res, Rental | S+L | `missing_from_parser` | 3 | 2 | 2 | 2 | 2 | 2 | 2 | 15 | High |
| 17 | **Fireplace Y/N** | — / Residential | S | `missing_from_parser` | 2 | 2 | 2 | 2 | 2 | 2 | 3 | 15 | High |
| 18 | **Foundation** for Landlord — Seller already has it | `foundation` / Res, Rental | L only | `missing_from_field_map` | 2 | 1 | 2 | 1 | 1 | 1 | 3 | 11 | Medium |
| 19 | **Security Features** | — / Residential | S | `missing_from_parser` | 2 | 2 | 2 | 2 | 2 | 2 | 2 | 14 | Medium |
| 20 | **Accessibility Features** | — / Res, Rental | S+L | `missing_from_parser` | 1 | 2 | 2 | 2 | 2 | 3 | 2 | 14 | Medium |
| 21 | **Green / Energy Features** | — / Res, Rental | S+L | `missing_from_parser` | 1 | 2 | 2 | 2 | 2 | 3 | 2 | 14 | Medium |
| 22 | **Lot Features** (Vacant Land — cleared, wooded, filled) | — / Vacant Land | S+L | `missing_from_parser` | 2 | 2 | 2 | 2 | 2 | 2 | 2 | 14 | Medium (land) |
| 23 | **Rent Rate per Sq Ft** (Commercial Lease) | — / Comm. Lease | L only | `missing_from_parser` | 2 | 2 | 2 | 2 | 1 | 1 | 2 | 12 | Medium (commercial) |
| 24 | **Dock / Boat / Water-body features** | — / Res, VL | S+L | `missing_from_parser` | 1 | 2 | 2 | 2 | 2 | 2 | 2 | 13 | Medium |
| 25 | **fieldInventory() blind spots (12 fields)** — reporter accuracy only, no functional impact | 12 keys (TABLE 4C) | Reporter | `inventory_gap` | — | — | — | — | — | — | 3 | n/a | Medium (housekeeping) |
| 26 | **Road Frontage / Easements** (Vacant Land) | — / Vacant Land | S+L | `missing_from_parser` | 1 | 1 | 2 | 1 | 1 | 1 | 2 | 9 | Low |
| 27 | **Directions** — navigational text only | `directions` / All | S+L | `missing_from_field_map` | 3 | 0 | 0 | 0 | 0 | 0 | 3 | 6 | Low |

---

## Final Metrics

### Parser Coverage

| Metric | Count |
|---|---|
| Total canonical keys emitted by parser | ~63 |
| Canonical keys intentionally excluded (`mls_number`, `application_fee`, `listing_type_hint`) | 3 |
| Canonical keys correctly mapped for Seller | 51 |
| Canonical keys correctly mapped for Landlord | 53 |
| Canonical keys parsed but missing from Seller field map | 4 (`heating`, `lot_size_sqft`, `directions`, and role-specific rental fields) |
| Canonical keys parsed but missing from Landlord field map | 7 (`heating`, `lot_size_sqft`, `directions`, `lot_dimensions`, `roof_type`, `exterior_construction`, `foundation`) |
| MLS fields with no parser branch (`fieldInventory` null-key rows) | 34 across 7 forms |
| Correctly-mapped fields absent from `fieldInventory()` | 12 (`heating_fuel`, `roof_type`, `exterior_construction`, `foundation`, `water`, `sewer`, `utilities`, `sqft_heated_source`, `flood_insurance_required`, `has_special_assessments`, `special_assessment_amount`, `special_assessment_description`) |

### MLS Property Type Coverage: Reported vs Actual

The `MlsCoverageReporter` output is **understated** because 12 correctly-mapped fields are absent from `fieldInventory()`. The table below shows both:

| Property Type | Role | Reported Coverage (fieldInventory only) | Actual Coverage (+ inventory gaps) | Gap Count |
|---|---|---|---|---|
| Residential | Seller | 38 / 43 = **88 %** | 50 / 55 = **91 %** | +12 inventory gaps, of which 3 are Seller-only |
| Residential | Landlord | 37 / 43 = **86 %** | 46 / 55 = **84 %** | +9 inventory gaps (4 fields absent from Landlord field map) |
| Rental | Seller | 35 / 40 = **88 %** | 47 / 52 = **90 %** | same 12 inventory gaps apply |
| Rental | Landlord | 35 / 40 = **88 %** | 44 / 52 = **85 %** | 4 Landlord-only field-map gaps lower actual vs reported |
| Vacant Land | Seller | 28 / 35 = **80 %** | 28 / 35 = **80 %** | inventory gaps not all applicable |
| Vacant Land | Landlord | 26 / 35 = **74 %** | 26 / 35 = **74 %** | |
| Income | Seller | 22 / 29 = **76 %** | 22 / 29 = **76 %** | |
| Income | Landlord | 22 / 29 = **76 %** | 22 / 29 = **76 %** | |
| Commercial Sale | Seller | 18 / 28 = **64 %** | 18 / 28 = **64 %** | |
| Commercial Lease | Landlord | 22 / 34 = **65 %** | 22 / 34 = **65 %** | |
| Business Opportunity | Seller | 13 / 22 = **59 %** | 13 / 22 = **59 %** | |

> **Reading this table:** "Reported" = what `MlsCoverageReporter` would display. "Actual" = true end-to-end safe import coverage once fieldInventory() blind spots are corrected. Note that for Landlord, 4 fields that Seller supports (`roof_type`, `exterior_construction`, `lot_dimensions`, `foundation`) are missing from the Landlord field map — these reduce Landlord's actual coverage below its reported coverage in the Residential/Rental rows.

### Website Field Coverage

| Role | Total Website Fields | MLS-Mapped (autofill) | Website-Only | % Autofilled |
|---|---|---|---|---|
| Seller | ~87 | 37 | ~50 | 43 % |
| Landlord | ~80 | 40 | ~40 | 50 % |

> Note: The majority of "website-only" fields are intentionally platform-specific (auction type, financing terms, sale provisions, financial qualifiers) and cannot meaningfully map from a standard MLS listing. The true "actionable autofill gap" is the 7 parsed-but-not-mapped keys plus the 20+ missing-from-parser MLS content fields.

### Normalization Requirements

| Canonical Key | Normalizer Action |
|---|---|
| `has_hoa`, `has_cdd`, `waterfront`, `pool`, `garage`, `carport`, `furnished`, `additional_parcels` | Boolean coercion → yes/no string |
| `flood_zone_code` | Uppercase |
| `association_fee_frequency`, `lease_amount_frequency` | Lowercase |
| All `*`-prefixed targets | Comma-split string → PHP array (via `applyImportedFields()` `*` convention) |

### Cross-Role Property Name Discrepancies

These fields map to **different Livewire property names** depending on role — both correct but must stay in sync if blade or Livewire component structure changes:

| Canonical Key | Seller Property | Landlord Property |
|---|---|---|
| `price` | `maximum_budget` | `desired_rental_amount` |
| `utilities` | `utilities` | `property_utilities` |
| `heating_fuel` | `heating_and_fuel` | `heating_fuel` |
| `annual_taxes` | `annual_property_taxes` | `annual_property_taxes` (same) |
| `tax_id` | `parcel_id` | `parcel_id` (same) |
| `minimum_security_deposit` | N/A | `security_deposit_amount` |

---

## TABLE 6 — RESO Data Dictionary Compliance Gap Analysis

> **RESO Data Dictionary** (version 1.7 / 2.0) is the NAR-adopted standard for MLS field names and data types. Stellar MLS is RESO-certified. This table maps RESO standard field names to the platform's canonical keys and assesses compliance status.  
> **RESO Required?** = marked Required in RESO Data Dictionary Core endorsement.  
> **Status:** ✅ Supported · ⚠️ Partial · ❌ Not Supported · — Not Applicable

| RESO Standard Field | RESO Required? | Canonical Key | Seller Status | Landlord Status | Gap Type | Priority |
|---|---|---|---|---|---|---|
| **PROPERTY IDENTIFICATION** | | | | | | |
| `ListingId` (MLS #) | Yes | `mls_number` | ⚠️ Parsed, intentionally excluded | ⚠️ Same | `intentional_exclusion` | — |
| `ListPrice` | Yes | `price` | ✅ → `maximum_budget` | ✅ → `desired_rental_amount` | `mapped_correctly` | — |
| `PostalCode` | Yes | `zip_code` | ✅ | ✅ | `mapped_correctly` | — |
| `City` | Yes | `city` | ✅ | ✅ | `mapped_correctly` | — |
| `StateOrProvince` | Yes | `state` | ✅ | ✅ | `mapped_correctly` | — |
| `CountyOrParish` | Yes | `county` | ✅ | ✅ | `mapped_correctly` | — |
| `StreetNumber` / `StreetName` | Yes | `street_address` | ✅ | ✅ | `mapped_correctly` | — |
| `Latitude` / `Longitude` | No | — | ❌ Not tracked | ❌ | `missing_website_field` | Low |
| **PROPERTY CHARACTERISTICS** | | | | | | |
| `BedroomsTotal` | Yes | `bedrooms` | ✅ | ✅ | `mapped_correctly` | — |
| `BathroomsTotalInteger` | Yes | `bathrooms_total` | ✅ | ✅ | `mapped_correctly` | — |
| `BathroomsHalf` | No | — | ❌ Not tracked | ❌ | `missing_website_field` | Low |
| `LivingArea` (Sq Ft Heated) | Yes | `sqft_heated` | ✅ | ✅ | `mapped_correctly` | — |
| `LivingAreaSource` | No | `sqft_heated_source` | ✅ (inv. gap) | ✅ (inv. gap) | `inventory_gap_correctly_mapped` | Low |
| `LotSizeSquareFeet` | No | `lot_size_sqft` | ❌ Parsed, no field map | ❌ | `missing_from_field_map` | **High** |
| `LotSizeAcres` | No | `lot_size_acres` / `total_acreage` | ✅ | ✅ | `mapped_correctly` | — |
| `LotDimensions` | No | `lot_dimensions` | ✅ | ❌ Not mapped | `missing_from_field_map` (L) | **High** |
| `YearBuilt` | No | `year_built` | ✅ | ✅ | `mapped_correctly` | — |
| `PropertyType` | Yes | `listing_type_hint` | ⚠️ Internal signal only | ⚠️ | `intentional_exclusion` | — |
| `PropertySubType` | Yes | — | ❌ Not tracked | ❌ | `missing_website_field` | Medium |
| `Levels` (Stories) | No | `number_of_stories` | ✅ | ✅ | `mapped_correctly` | — |
| **CONSTRUCTION** | | | | | | |
| `ConstructionMaterials` | No | `exterior_construction` | ✅ (inv. gap) | ❌ Not mapped | `missing_from_field_map` (L) | **High** |
| `Roof` | No | `roof_type` | ✅ (inv. gap) | ❌ Not mapped | `missing_from_field_map` (L) | **High** |
| `FoundationDetails` | No | `foundation` | ✅ (inv. gap) | ❌ Not mapped | `missing_from_field_map` (L) | High |
| `ArchitecturalStyle` | No | — | ❌ Not tracked | ❌ | `missing_website_field` | Low |
| **INTERIOR** | | | | | | |
| `InteriorFeatures` | No | `interior_features` | ✅ | ✅ | `mapped_correctly` | — |
| `Appliances` | No | `appliances` | ✅ | ✅ | `mapped_correctly` | — |
| `Flooring` | No | — | ❌ Not parsed | ❌ | `missing_from_parser` | **High** |
| `FireplaceYN` | No | — | ❌ Not parsed | — | `missing_from_parser` | High |
| `FireplacesTotal` | No | — | ❌ Not parsed | — | `missing_from_parser` | Medium |
| **HEATING & COOLING** | | | | | | |
| `Heating` | No | `heating` | ❌ Parsed, no field map | ❌ | `missing_from_field_map` | **Critical** |
| `HeatingYN` | No | — | ❌ Not tracked | ❌ | `missing_website_field` | Low |
| `Cooling` | No | `air_conditioning` | ✅ | ✅ | `mapped_correctly` | — |
| `CoolingYN` | No | — | ❌ Not tracked | ❌ | `missing_website_field` | Low |
| **UTILITIES** | | | | | | |
| `WaterSource` | No | `water` | ✅ (inv. gap) | ✅ (inv. gap) | `inventory_gap_correctly_mapped` | Low |
| `Sewer` | No | `sewer` | ✅ (inv. gap) | ✅ (inv. gap) | `inventory_gap_correctly_mapped` | Low |
| `Utilities` | No | `utilities` | ✅ (inv. gap) | ✅ (inv. gap) | `inventory_gap_correctly_mapped` | Low |
| **EXTERIOR & OUTDOORS** | | | | | | |
| `ExteriorFeatures` | No | — | ❌ Not parsed | ❌ | `missing_from_parser` | **Critical** |
| `PatioAndPorchFeatures` | No | — | ❌ Not parsed | ❌ | `missing_from_parser` | High |
| `ParkingFeatures` | No | — | ❌ Not parsed | ❌ | `missing_from_parser` | **High** |
| `GarageYN` | No | `garage` | ✅ | ✅ | `mapped_correctly` | — |
| `GarageSpaces` | No | `garage_spaces` | ✅ | ✅ | `mapped_correctly` | — |
| `CarportYN` | No | `carport` | ✅ | ✅ | `mapped_correctly` | — |
| `CarportSpaces` | No | `carport_spaces` | ✅ | ✅ | `mapped_correctly` | — |
| `PoolPrivateYN` | No | `pool` | ✅ | ✅ | `mapped_correctly` | — |
| `View` | No | — | ❌ Not parsed | ❌ | `missing_from_parser` | High |
| `ViewYN` | No | — | ❌ Not tracked | ❌ | `missing_website_field` | Medium |
| **COMMUNITY** | | | | | | |
| `CommunityFeatures` | No | — | ❌ Not parsed | ❌ | `missing_from_parser` | **Critical** |
| `SecurityFeatures` | No | — | ❌ Not parsed | ❌ | `missing_from_parser` | Medium |
| `SeniorCommunityYN` | No | — | ❌ Not tracked | ❌ | `missing_website_field` | Low |
| **WATERFRONT** | | | | | | |
| `WaterfrontYN` | No | `waterfront` | ✅ | ✅ | `mapped_correctly` | — |
| `WaterfrontFeatures` | No | — | ❌ Not parsed | ❌ | `missing_from_parser` | High |
| `WaterBodyName` | No | — | ❌ Not tracked | ❌ | `missing_website_field` | Low |
| `WaterfrontFeet` / `WaterFrontageLength` | No | — | ❌ Not tracked | ❌ | `missing_website_field` | Medium |
| **HOA & TAXES** | | | | | | |
| `AssociationYN` | No | `has_hoa` | ✅ | ✅ | `mapped_correctly` | — |
| `AssociationName` | No | `association_name` | ✅ | ✅ | `mapped_correctly` | — |
| `AssociationFee` | No | `association_fee_amount` | ✅ | ✅ | `mapped_correctly` | — |
| `AssociationFeeFrequency` | No | `association_fee_frequency` | ✅ | ✅ | `mapped_correctly` | — |
| `TaxAnnualAmount` | No | `annual_taxes` | ✅ | ✅ | `mapped_correctly` | — |
| `TaxYear` | No | `tax_year` | ✅ | ✅ | `mapped_correctly` | — |
| `ParcelNumber` | No | `tax_id` / `parcel_id` | ✅ | ✅ | `mapped_correctly` | — |
| `TaxLegalDescription` | No | `legal_description` | ✅ | ✅ | `mapped_correctly` | — |
| **FLOOD & DISCLOSURES** | | | | | | |
| `FloodZone` / `FloodZoneCode` | No | `flood_zone_code` | ✅ | ✅ | `mapped_correctly` | — |
| `FloodZoneDate` | No | `flood_zone_date` | ✅ | ✅ | `mapped_correctly` | — |
| `FloodZonePanel` | No | `flood_zone_panel` | ✅ | ✅ | `mapped_correctly` | — |
| **ACCESSIBILITY & GREEN** | | | | | | |
| `AccessibilityFeatures` | No | — | ❌ Not parsed | ❌ | `missing_from_parser` | Medium |
| `GreenBuildingVerificationType` | No | — | ❌ Not tracked | ❌ | `missing_website_field` | Low |
| `GreenEnergyEfficient` | No | — | ❌ Not tracked | ❌ | `missing_website_field` | Low |
| **LEASE / RENTAL (Landlord-only)** | | | | | | |
| `LeaseTerm` | No | (partial — `terms_of_lease`) | — | ⚠️ Partial | `partial_value_mapping` | Medium |
| `RentIncludes` / `Inclusions` | No | `rent_includes` | — | ✅ | `mapped_correctly` | — |
| `TenantPays` | No | `tenant_pays` | — | ✅ | `mapped_correctly` | — |
| `PetsAllowed` | No | — | — | ❌ Not parsed | `missing_from_parser` | **High** |
| `NumberOfUnitsTotal` | No | — | ❌ Not parsed | ❌ | `missing_from_parser` | **High** |
| **REMARKS** | | | | | | |
| `PublicRemarks` | Yes | `description` → `additional_details` | ✅ | ✅ | `mapped_correctly` | — |
| `Directions` | No | `directions` | ❌ Parsed, no field map | ❌ | `missing_from_field_map` | Low |
| `PrivateRemarks` | No | — | ❌ Not tracked | ❌ | `intentional_exclusion` | — |

### RESO Compliance Summary

| Category | RESO Fields Reviewed | Fully Supported | Partial / Inv. Gap | Not Supported | Compliance % (CI-verified) |
|---|---|---|---|---|---|
| Property Identification | 8 | 6 | 1 | 1 | 75 % |
| Property Characteristics | 8 | 5 | 1 | 2 | 63 % |
| Construction | 5 | 0 | 3 (inv. gap/L-only) | 2 | 40 % |
| Interior | 5 | 2 | 0 | 3 | 40 % |
| Heating & Cooling | 4 | 1 | 0 | 3 | 25 % |
| Utilities | 3 | 0 | 3 (inv. gaps) | 0 | 100 % functional / 0 % reported |
| Exterior & Outdoors | 9 | 4 | 0 | 5 | 44 % |
| Community | 3 | 0 | 0 | 3 | 0 % |
| Waterfront | 4 | 1 | 0 | 3 | 25 % |
| HOA & Taxes | 8 | 8 | 0 | 0 | 100 % |
| Flood & Disclosures | 3 | 3 | 0 | 0 | 100 % |
| Accessibility & Green | 3 | 0 | 0 | 3 | 0 % |
| Lease / Rental | 6 | 2 | 1 | 3 | 33 % |
| Remarks | 3 | 2 | 0 | 1 | 67 % |
| **TOTAL** | **72** | **34** | **9** | **29** | **47 % fully · 60 % functional** |

> **RESO Priority gaps to close first** (fields marked Critical or High that have no support):  
> `Heating` system type · `LotSizeSquareFeet` · `CommunityFeatures` · `ExteriorFeatures` · `ParkingFeatures` · `Flooring` · `PetsAllowed` (Landlord) · `NumberOfUnitsTotal` · `WaterfrontFeatures` · `View` · and the 4 Landlord-only wiring gaps (`lot_dimensions`, `roof_type`, `exterior_construction`, `foundation`)

---

## Pre-Merge Verification Results

> This section records the source-inspection verification performed in response to the approval condition: confirm coverage percentages, Landlord wiring-gap recommendations, and `mapped_correctly` classification basis before merge.

### 1. Coverage percentages — source-verified ✅

Coverage denominators and numerators were reconfirmed by counting `fieldInventory()` entries directly from `MlsCoverageReporter.php`:

**Residential form — 43 entries total** (5 address + 1 mls_number + 1 price + 7 property details + 4 lot fields + 4 interior/systems + 3 waterfront + 6 tax/legal + 3 flood zone + 6 HOA/CDD + 2 remarks + 1 listing_type_hint)

| Role | Not-Safe Keys (source-confirmed) | Count Not-Safe | Safe | Coverage |
|---|---|---|---|---|
| Seller | `mls_number` (excluded) · `listing_type_hint` (excluded) · `heating` (no field-map entry) · `lot_size_sqft` (no field-map entry) · `directions` (no field-map entry) | 5 | **38 / 43 = 88 %** ✓ |
| Landlord | Same 5 as Seller, plus `lot_dimensions` (absent from Landlord field map, confirmed `MlsFieldMap.php` lines 44/146) | 6 | **37 / 43 = 86 %** ✓ |

`zoning` verified in both Seller field map (line 47) and Landlord field map (line 146) — correctly counted as safe for both roles.

Inventory-gap adjusted figures (+ 12 fields to denominator, + 12 for Seller / + 9 for Landlord to numerator): **Seller 50/55 = 91 %** · **Landlord 46/55 = 84 %** — math confirmed correct; Landlord "actual" is lower than "reported" because 3 of the 12 inventory-gap fields (`roof_type`, `exterior_construction`, `foundation`) are absent from the Landlord field map, so they add to the shared denominator but not to the Landlord numerator.

---

### 2. Landlord wiring-gap recommendations — source-verified, prior description corrected ✅

Grep of `LandlordOfferListing.php` and all files under `resources/views/livewire/offer-listing/offer-landlord-tabs/` for `lot_dimensions|roof_type|exterior_construction|foundation` returned **zero matches**.

Grep of `SellerOfferListing.php` and `offer-seller-tabs/commission-based/property-preferences.blade.php` confirmed full implementations on Seller: public properties declared (lines 325–327, 345), JSON encode/decode in save and load methods (lines 1999–2004, 2034, 2567–2572, 2602), Select2 multi-select blade inputs with `wire:model.defer` bindings, and validation rules (lines 4050–4057).

**The original audit text describing these as "one-line fixes" and "< 1 hour each" was incorrect.** The "High-Priority Landlord Wiring Gaps" section above has been updated with the correct effort estimate (11–15 hours total) and the full per-field fix specification. Adding a `MlsFieldMap` entry without the Livewire properties would silently fail at the `property_exists()` guard in `HasMlsImport::importListingFromUrl()` line 68 — the field would be filtered from the preview and never reach Apply Selected.

---

### 3. `mapped_correctly` classification basis — source-verified ✅

Every field classified `mapped_correctly` in TABLE 1/2/3 was verified against exactly four criteria, all by direct source reading:

| Criterion | Source Read |
|---|---|
| Parser branch exists | `MlsListingImportService::parseFields()` regex branches |
| Field-map entry exists for role | `MlsFieldMap::seller()` / `::landlord()` arrays |
| Livewire property declared | `SellerOfferListing.php` / `LandlordOfferListing.php` public properties; confirmed via `property_exists()` gate logic in `HasMlsImport` |
| `wire:model` binding in blade | Tab blade files for each role |

No `mapped_correctly` field relies on any assumption beyond these four checks. Fields that pass all four checks but are absent from `fieldInventory()` are classified `inventory_gap_correctly_mapped`, not `mapped_correctly` — this distinction is preserved throughout the document. No field with a broken pipeline link (failed property_exists, missing wire:model, or missing field-map entry) was promoted to `mapped_correctly`.

**`mapped_correctly` does not mean live-verified.** It means the code path is unbroken per static inspection. This limitation is stated in the Executive Summary and Verification Methodology section.

---

*End of audit. No code changes were made; this document is analysis only.*
