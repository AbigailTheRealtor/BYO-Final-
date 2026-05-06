# Create Offer Listing — Full Audit
**Audit Date:** 2026-05-06  
**Scope:** All four Create Offer Listing flows — Seller, Landlord, Buyer, Tenant (Full Service / commission-based path only)  
**Auditor:** Read-only static analysis of blade partials, Livewire components, and EAV persistence layer.

---

## Table of Contents

1. [File Map & Line Counts](#1-file-map--line-counts)
2. [Shared Architectural Patterns](#2-shared-architectural-patterns)
3. [Role: Seller](#3-role-seller)
4. [Role: Landlord](#4-role-landlord)
5. [Role: Buyer](#5-role-buyer)
6. [Role: Tenant](#6-role-tenant)
7. [Cross-Role Comparison Matrix](#7-cross-role-comparison-matrix)
8. [Findings & Issues](#8-findings--issues)

---

## 1. File Map & Line Counts

### Livewire Components (PHP)

| Role | Create Component | Edit Component | Create Lines | Edit Lines |
|------|-----------------|----------------|-------------|------------|
| Seller | `app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php` | `SellerOfferListingEdit.php` | ~3,636 | — |
| Landlord | `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php` | `LandlordOfferListingEdit.php` | ~3,352 | — |
| Buyer | `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php` | `BuyerOfferListingEdit.php` | ~2,327 | — |
| Tenant | `app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php` | `TenantOfferListingEdit.php` | ~4,637 | — |

### Blade Files (Root Views)

| Role | Blade File | Lines |
|------|-----------|-------|
| Seller | `resources/views/livewire/offer-listing/seller/offer-seller-listing.blade.php` | ~3,211 |
| Landlord | `resources/views/livewire/offer-listing/offer-landlord-listing.blade.php` | ~3,497 |
| Buyer | `resources/views/livewire/offer-listing/buyer/offer-buyer-listing.blade.php` | ~3,176 |
| Tenant | `resources/views/livewire/offer-listing/tenant/offer-tenant-listing.blade.php` | ~6,149 |

### Tab Partials Directories

```
resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/
resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/
resources/views/livewire/offer-listing/offer-buyer-tabs/commission-based/
resources/views/livewire/offer-listing/offer-tenant-tabs/commission-based/
```

---

## 2. Shared Architectural Patterns

### 2.1 Wizard Navigation (Tab Delegation Pattern)

All four flows use a multi-tab wizard. Each role's root blade renders tab panels conditionally. Navigation between tabs uses a delegation pattern: "Next / Back" buttons call Livewire methods (e.g., `nextStep`, `prevStep`) which trigger client-side JS tab transitions. The active tab is tracked by an integer `$currentStep` (or equivalent) in the component.

### 2.2 Data Persistence — EAV `saveMeta()` / `loadMeta()`

**All four roles store every field via the EAV meta system** (`saveMeta($key, $value)` / `loadMeta()`) against their respective model row (e.g., `SellerAgentAuction`, `LandlordAgentAuction`, etc.). There are no per-tab native columns for form fields; the database row contains identity columns only, and all content lives in the `*_metas` key-value table.

JSON encoding is applied before `saveMeta()` for all array-valued fields (multi-selects, photo arrays, doc-row arrays).

### 2.3 Draft System

All four components expose a `saveDraft()` method and a `$hasDrafts` / `$isDraft` boolean flag. Drafts use the same model row with `listing_status = 'draft'`. Loading a draft populates all public properties from `loadMeta()` calls. An append-only versioning approach is used — each Save Draft call upserts the same row.

### 2.4 Validation Strategy

Validation is split into two tiers:

| Tier | Trigger | Scope |
|------|---------|-------|
| **Partial / Save Draft** | `saveDraft()` | Minimal required fields only (varies per role) |
| **Full Submit** | `submitForm()` or `submit()` | Full rule set built dynamically in `getValidationRules()` |

Seller and Landlord build rules via a `getValidationRules()` method that returns a dynamic PHP array. Buyer and Tenant use a similar pattern inside their submit method (inline dynamic `$rules` array). All four roles use `getValidationMessages()` or an inline messages array.

### 2.5 Service Type

All four roles support a `$service_type` field (`full_service` / `limited_service`). The service-type picker card UI exists in the blade for all roles, but it is hidden (`d-none`) or fully commented out in most cases (see §8 for details). The `initializeLimitedService()` JS function exists in all four create blades and is **frozen/legacy** — never modified.

### 2.6 File Uploads

- Seller and Landlord use the Livewire `WithFileUploads` trait extensively (multi-photo, disclosure files, repeatable doc rows).
- Buyer and Tenant use `WithFileUploads` only for a single personal photo (`$photo`).

### 2.7 Phone Number Formatting

Each role implements its own `formatXxxPhone(input)` JS function inline in the `xxx-info.blade.php` partial. All use identical numeric-only masking logic producing `(NNN) NNN-NNNN`. The function names differ per role to avoid global scope collisions:

- Seller → `formatSellerPhone()` (in `seller-info.blade.php`)
- Landlord → `formatLandlordPhone()`
- Buyer → `formatBuyerPhone()`
- Tenant → `formatPhoneNumber()`

Re-initialization is handled on `livewire:load` (v2) and `livewire:init` (v3 compat fallback) for all four.

### 2.8 Numeric Input Sanitization

Dollar-amount fields across all roles share three global JS helpers called inline via `oninput` / `onblur` / `onpaste`:

- `validateInput(this)` — strips non-numeric characters as user types
- `reformatNumber(this)` — adds comma formatting on blur
- `handlePaste(event)` — strips non-numeric content on paste

These helpers are expected to be globally available (loaded in the main layout).

### 2.9 Select2 Multi-Select (JSON Bridge Pattern)

Multi-select fields (e.g., `offered_financing`, `sale_provision`, `services`, `lease_for`) use Select2 for the dropdown UI, bridged to Livewire via a JSON hidden-input pattern. Wire.ignore is applied to the `<select>` wrapper, and a JavaScript listener pushes changes to the Livewire component via `@this.set(...)` or a hidden input dispatch. This pattern is used consistently across Seller, Landlord, Buyer, and Tenant.

### 2.10 City/County/State Auto-Population

Buyer and Tenant (search-based roles) use a multi-tag city/county system backed by `UsCity`, `UsCounty`, `UsState`, `UsZipCode` models. Users type a city name; a Livewire autocomplete dropdown appears (debounced 300ms); selecting a suggestion populates county and state automatically.

Seller and Landlord (property-specific roles) collect a single `address`, `property_city`, `property_state`, `property_zip`, `property_county` for the specific property. The city field uses `wire:model.live.debounce.300ms` with a suggestion dropdown that auto-fills county and state.

---

## 3. Role: Seller

### 3.1 Tab Structure (11 tabs)

| # | Tab File | Blade Title | Notes |
|---|----------|------------|-------|
| 1 | `listing-details.blade.php` | Listing Details | Service type picker (hidden `d-none`); auction type, listing title, dates |
| 2 | `property-preferences.blade.php` | Property Details | Address, city/county/state/zip; property type; subtype; beds/baths; sq ft; lot size; year built; structural features (roof, foundation, exterior, HVAC, water, sewer, electrical, utilities); zoning; business-specific fields (revenue, NDA, etc.); income property fields |
| 3 | `financial-details.blade.php` | Financial Details | Asking price, starting price, reserve price, buy-now price; financing options (multi-select with deep conditional sub-fields for each type: cash, mortgage, seller financing, assumable, exchange, lease option, lease purchase, cryptocurrency, NFT, etc.) |
| 4 | `seller-terms.blade.php` | Seller Terms | Special sale provisions; occupant status/occupied-until; NDA; assignment; seller lease option / lease purchase details |
| 5 | `additional-details.blade.php` | Additional Details | Single `$additional_details` textarea — property description |
| 6 | `services.blade.php` | Services | Residential/Commercial/Income/Business/Land conditional service checkboxes; property type-scoped section headers; photo enhancement sub-options; open house count; `$other_services` custom service |
| 7 | `broker-compensation.blade.php` | Broker Compensation | Seller's Broker Purchase Fee (type + value); Seller's Broker Leasing Fee (if leasing offered); Buyer's Broker commission; protection period; early termination fee; retainer fee; brokerage relationship; agency agreement timeframe |
| 8 | `seller-info.blade.php` | Agent Credentials & Contact Info | First name, last name, phone (masked), email; personal photo upload; personal video link (YouTube/Vimeo embed preview); privacy notice; agent brokerage / license / NAR fields |
| 9 | `photos-tours-documents.blade.php` | Photos & Tours | Multi-photo upload (up to 50); drag-and-drop reorder (SortableJS); cover photo designation; video tour link; 3D tour link; `$listing_ai_faq` |
| 10 | `documents-disclosures.blade.php` | Documents & Disclosures | Seller disclosure (available + file upload); flood disclosure (available + file upload); lead-based paint disclosure flag; repeatable doc row uploader |
| 11 | `tax-legal-hoa-disclosures.blade.php` | Tax, Legal & HOA | 5 card groups: Tax/Legal/Parcel; Flood Zone; CDD/Special Assessments; HOA/Association; (Documents & Disclosures note on next tab) |

> **Full Service only:** Tabs 9, 10, 11 are only rendered when `$service_type === 'full_service'`.

### 3.2 Key Public Properties (selected)

**Listing / Identity:**
`$auction_type`, `$listing_title`, `$working_with_agent`, `$listing_date`, `$desired_agent_hire_date`, `$expiration_date`, `$auction_time`, `$service_type`, `$listing_status`

**Location:**
`$address`, `$property_city`, `$property_county`, `$property_state`, `$property_zip`, `$state`

**Property:**
`$property_type`, `$property_items[]`, `$other_property_items`, `$condition_prop`, `$other_property_condition`, `$bathrooms`, `$other_bathrooms`, `$bedrooms`, `$other_bedrooms`, `$minimum_heated_square`, `$minimum_leaseable`, `$min_acreage`, `$total_acreage`, `$total_square_feet`, `$sqft_heated_source`, `$year_built`, `$zoning`, `$leasing_space`

**Structural (Seller-only depth):**
`$roof_type[]`, `$exterior_construction[]`, `$foundation[]`, `$heating_and_fuel[]`, `$air_conditioning[]`, `$water[]`, `$sewer[]`, `$utilities[]`, `$road_frontage[]`, `$road_surface_type[]`, `$electrical_service[]`, `$ceiling_height`, `$building_features[]`, `$number_water_meters`, `$number_electric_meters`

**Business-specific:**
`$business_name`, `$year_established`, `$licenses[]`, `$sale_includes[]`, `$annual_revenue`, `$gross_profit`, `$sde_ebitda`, `$inventory_value`, `$ffe_value`, `$reason_for_sale`, `$employee_count`, `$financial_statements_available`, `$tax_returns_available`, `$nda_required`

**Income Property:**
`$number_of_units`, `$number_occupied`, `$expected_rent`, `$gross_annual_income`, `$annual_operating_expenses`, `$rent_roll_available`, `$operating_statement_available`, `$price_per_sqft`, `$existing_lease_type`, `$unit_type_configurations[]`

**Financial / Terms:**
`$starting_price`, `$reserve_price`, `$buy_now_price`, `$offered_financing[]`, `$other_financing`, `$pre_approved`, `$pre_approval_amount`, `$purchase_price`, `$down_payment_amount`, `$seller_financing_amount`, `$interest_rate`, `$loan_duration`, `$prepayment_penalty`, `$prepayment_penalty_amount`, `$balloon_payment`, `$balloon_payment_amount`, `$balloon_payment_date`, `$assumable_terms`, `$assumable_loan_type`, `$outstanding_balance`, `$lender_approval_required`, `$max_assumable_rate`, `$assumable_monthly_escrow`, `$assumable_loan_term_remaining`, `$assumable_loan_origination_date`, `$assumable_loan_servicer`, `$assumable_fee_amount`, `$assumable_occupancy_requirement`

**Exchange:**
`$exchange_item[]`, `$other_exchange_item`, `$exchange_item_value`, `$exchange_item_condition`, `$additional_cash`, `$value_determination`, `$exchange_transfer_method`, `$exchange_liens`, `$exchange_liens_disclosure`, `$exchange_liens_details`, `$exchange_inspection_rights`

**Lease Option / Lease Purchase:** (extensive sub-fields for both)

**Crypto / NFT:** `$cryptocurrency_type`, `$crypto_percentage`, `$cash_percentage_crypto`

**Seller Terms:**
`$sale_provision[]`, `$sale_provision_other`, `$occupant_status`, `$occupant_tenant`, `$nda_required`

**Tax/Legal:**
`$parcel_id`, `$tax_year`, `$annual_property_taxes`, `$additional_parcels`, `$zoning`, `$deed_restrictions`

**Flood Zone:**
`$flood_zone`, `$flood_zone_designation`, `$flood_insurance_required`, `$flood_insurance_amount`

**CDD / Special Assessments:**
`$cdd_fee`, `$special_assessment`, `$special_assessment_details`

**HOA / Association:**
`$hoa_name`, `$association_fee`, `$association_fee_frequency`, `$association_fee_frequency_other`, `$association_approval_required`, `$association_approval_process`, `$association_fee_includes[]`, `$association_contact`

**Disclosures (Files):**
`$seller_disclosure_available`, `$seller_disclosure_file`, `$seller_disclosure_file_path`, `$flood_disclosure_available`, `$flood_disclosure_file`, `$flood_disclosure_file_path`, `$lead_based_paint_disclosure`

**Photos:**
`$newPropertyPhotos[]`, `$propertyPhotos[]` (stored JSON array in `property_photos` native column)

**Broker Compensation:**
`$commission_structure_type`, `$purchase_fee_type`, `$purchase_fee_flat`, `$purchase_fee_percentage`, `$purchase_fee_percentage_combo`, `$purchase_fee_flat_combo`, `$purchase_fee_other`, `$seller_leasing_fee_type`, `$seller_leasing_gross`, `$seller_leasing_gross_flat`, `$seller_leasing_gross_other`, `$seller_leasing_gross_rental`, `$protection_period`, `$early_termination_fee_option`, `$early_termination_fee_amount`, `$retainer_fee_option`, `$retainer_fee_amount`, `$brokerage_relationship`, `$agency_agreement_timeframe`

**Contact:**
`$first_name`, `$last_name`, `$phone_number`, `$email`, `$photo`, `$video_link`, `$embedUrl`

### 3.3 Required Fields (Full Submit)

Always required:
- `listing_title` (string, max 255)
- `property_type` (string)
- `first_name`, `last_name`, `phone_number`, `email`

Conditionally required:
- `auction_time` — if `$auction_type === 'Bidding Period'`
- `seller_leasing_fee_type` — if seller is offering leasing
- `seller_leasing_gross_flat` / `seller_leasing_gross` / `seller_leasing_gross_other` — based on fee type selected
- `commission_structure_type` — always after base set
- `commission_structure_type_fee_flat` / `_fee_percentage` / `_fee_other` — based on commission type
- `purchase_fee_flat` / `purchase_fee_percentage` / `purchase_fee_percentage_combo` / `purchase_fee_flat_combo` / `purchase_fee_other` — based on purchase fee type

### 3.4 File Upload Storage

| Asset | Property | Storage Path | Disk |
|-------|----------|-------------|------|
| Property photos | `$newPropertyPhotos[]` / `$propertyPhotos[]` | `storage/auction/images/` | public |
| Seller disclosure | `$seller_disclosure_file` | `seller-disclosures/{id}/seller-disclosure/` | public |
| Flood disclosure | `$flood_disclosure_file` | `seller-disclosures/{id}/flood-disclosure/` | public |
| Repeatable docs | `$landlord_doc_rows[]` (Seller uses same EAV key `seller_doc_rows`) | `seller-disclosures/{id}/...` | public |
| Personal photo | `$photo` | `storage/auction/images/` | public |

### 3.5 Loading Guard Flag

`$isLoadingData = false` — set to `true` before draft/edit population, back to `false` after. Guards `updatedPropertyType()`, `updatedOfferedFinancing()`, and related reactive hooks from resetting downstream fields during load.

---

## 4. Role: Landlord

### 4.1 Tab Structure (10 tabs)

| # | Tab File | Blade Title | Notes |
|---|----------|------------|-------|
| 1 | `landlord-info.blade.php` | Agent Credentials & Contact Info | First/last name, phone (masked), email; personal photo upload (Full Service only); video link with embed preview; privacy notice |
| 2 | `listing-details.blade.php` | Listing Details | Service type picker (hidden `d-none`, single full-service card only); auction type; listing title; dates; `$working_with_agent` |
| 3 | `property-preferences.blade.php` | Property Details | Street address (hidden on listing); city (with autocomplete); state; county (read-only, auto-filled); zip; privacy notice |
| 4 | `lease-terms.blade.php` | Leasing Terms | Occupant type + occupied-until date (conditional); leasing space (Residential/Commercial); property type (Residential/Commercial); subtype; beds/baths; sq ft; lease length (multi-select); rent amount + frequency; starting/reserve/lease-now rent; rent includes (Residential); lease type (Commercial); who pays expenses (owner_pays, tenant_pays); security deposit; first/last month rent; total move-in funds; lease available date; pet policy; renewal option; additional lease terms |
| 5 | `additional-details.blade.php` | Describe the property… | Single `$additional_details` textarea — property description |
| 6 | `services.blade.php` | Services the Landlord Requests | Residential/Commercial conditional service sections: Rental Marketing, Listing Presentation, Photography/Video, Showings, Tenant Screening, Lease & Legal, Property Management, After Move-In; `$other_services` |
| 7 | `broker-compensation.blade.php` | Broker Compensation | Landlord Broker Lease Fee (Residential vs Commercial options); protection period; early termination fee; retainer fee; brokerage relationship; agency agreement timeframe |
| 8 | `photos-tours-documents.blade.php` | Photos & Tours | Same as Seller: multi-photo (up to 50), SortableJS drag-drop, cover photo, video tour link, 3D tour link |
| 9 | `documents-disclosures.blade.php` | Documents & Disclosures | Landlord disclosure (available + file upload); flood disclosure (available + file upload); lead-based paint flag; Alpine repeatable doc-row uploader (`$landlord_doc_rows`) |
| 10 | `tax-legal-hoa-disclosures.blade.php` | Tax, Legal & HOA | Same 5-group structure as Seller: Tax/Legal/Parcel, Flood Zone, CDD, HOA/Association (includes `association_fee_includes` Select2) |

> **Full Service only:** Tabs 8, 9, 10 only rendered when `$service_type === 'full_service'`.

### 4.2 Key Public Properties (Landlord-specific or different from Seller)

**Location (property-specific like Seller):**
`$address`, `$property_city`, `$property_county`, `$property_state`, `$property_zip`

**Lease Terms:**
`$occupant_status`, `$occupant_tenant`, `$leasing_space`, `$leasing_spaces`, `$desired_rental_amount`, `$desired_rental_amount_tenant`, `$lease_amount_frequency`, `$starting_rent`, `$reserve_rent`, `$lease_now_price`, `$desired_lease_length[]`, `$rent_includes[]`, `$terms_of_lease[]`, `$owner_pays[]`, `$tenant_pays[]`, `$other_owner_pays`, `$other_tenant_pay`, `$other_rent_include`

**Move-In Funds (native columns → also saved via meta):**
`$security_deposit_required`, `$first_month_rent_required`, `$last_month_rent_required`, `$total_move_in_funds_required`

**Pet Policy:**
`$pet_policy`, `$pet_deposit_fee_rent`, `$number_of_occupants_allowed`, `$parking_terms`, `$utility_responsibility`, `$ll_maintenance_responsibility`, `$renewal_option_offered`, `$renewal_option_details`, `$landlord_approval_conditions`, `$additional_landlord_lease_terms`

**Commercial Lease:**
`$commercial_lease_type`, `$commercial_lease_type_other`, `$cam_nnn_additional_rent_charges`, `$rent_escalation_terms`, `$tenant_improvement_buildout_terms`, `$permitted_use_restrictions`, `$signage_rights`, `$commercial_parking_terms`, `$personal_guarantee_requirement`, `$commercial_approval_conditions`

**Disclosure Files:**
`$landlord_disclosure_available`, `$landlord_disclosure_file`, `$landlord_disclosure_file_path`, `$flood_disclosure_available`, `$flood_disclosure_file`, `$flood_disclosure_file_path`, `$lead_based_paint_disclosure`

**Repeatable Doc Rows (Alpine):**
`$landlord_doc_rows[]` — each row: `{type, custom_type, description, stored_path, original_name}`  
Meta key: `landlord_doc_rows` (JSON-encoded array)

**Photos:**
`$newPropertyPhotos[]`, `$propertyPhotos[]` (same as Seller)

### 4.3 Required Fields (Full Submit)

Always required:
- `first_name`, `last_name`, `phone_number`, `email`
- `desired_lease_length` (array, min:1)

Conditionally required:
- `property_zip`, `property_type` — always for full submit
- `bedrooms`, `bathrooms`, `minimum_heated_square` — if Residential Property
- `occupant_status` — always
- `leasing_spaces` — always
- `desired_rental_amount`, `lease_amount_frequency` — always
- `auction_time` — if Bidding Period

### 4.4 Loading Guard Flag

`$isLoadingDraft = false` — analogous to Seller's `$isLoadingData`. Set to `true` during draft/edit load to suppress reactive hooks.

---

## 5. Role: Buyer

### 5.1 Tab Structure (7 tabs)

| # | Tab File | Blade Title | Notes |
|---|----------|------------|-------|
| 1 | `buyer-info.blade.php` | Agent Credentials & Contact Info | First/last name, phone (masked, `formatBuyerPhone`), email; Buyer's Current Status dropdown; personal photo upload (Full Service only); personal video link with embed preview; privacy notice |
| 2 | `listing-details.blade.php` | Listing Details | Service type picker (both cards visible, unlike Landlord/Tenant); auction type; listing title; dates; `$working_with_agent` |
| 3 | `property-preferences.blade.php` | Property Preferences | State (required); multi-select city tags (optional); multi-select county tags (required); multi-select zip codes; property type; subtype (filtered by type); beds/baths (Residential required); sq ft range; lot size; garaging; pool; view preference; amenities; non-negotiable amenities; leasing 55+ |
| 4 | `purchasing-terms.blade.php` | Purchasing Terms | Acceptable special sale provisions (Select2 multi); sale provision other; occupant status; target closing date (required); maximum budget (required); `$buyer_sell_contract`; financing types (offered_financing Select2 multi); deep conditional sub-fields per financing type (same depth as Seller): cash, mortgage, seller financing, assumable, exchange, lease option, lease purchase, crypto |
| 5 | `additional-details.blade.php` | Describe your criteria… | Single `$additional_details` textarea — criteria & preferences |
| 6 | `services.blade.php` | Services the Buyer Requests | Residential/Commercial/Income/Business/Land conditional sections: Buyer Criteria Marketing, Property Search & Alerts, Showings & Virtual Tours, Offer Preparation & Negotiation, Transaction Management, After Closing; `$other_services` |
| 7 | `broker-compensation.blade.php` | Broker Compensation | Buyer Broker Commission Structure (Buyer Pays Out-of-Pocket vs Requested From Seller); Buyer Broker Purchase Fee type + conditional amounts; protection period; early termination fee; retainer fee; brokerage relationship; agency agreement timeframe |

> **No photos/tours tab, no documents/disclosures tab, no tax/legal/HOA tab** — these do not apply to the buyer search role.

### 5.2 Key Public Properties (Buyer-specific)

**Search Geography (multi-tag, unlike Seller/Landlord single address):**
`$state`, `$cities[]`, `$counties[]`, `$zipCodes[]`, `$newCity`, `$newCounty`, `$newZip`, `$citySuggestions[]`, `$countySuggestions[]`

**Property Preferences:**
`$property_type`, `$property_items[]`, `$other_property_items`, `$condition_prop` (string), `$condition_prop_buyer[]` (array — Buyer has both), `$bathrooms`, `$bedrooms`, `$minimum_heated_square`, `$min_acreage`, `$garage_needed`, `$other_garage_needed`, `$garage_parking_spaces`, `$garage_parking_spaces_option[]`, `$pool_needed`, `$pool_type[]`, `$view_preference[]`, `$non_negotiable_amenities[]`, `$leasing_55_plus`

**Purchasing Terms:**
`$maximum_budget`, `$target_closing_date`, `$occupant_status`, `$sale_provision[]`, `$sale_provision_other`, `$sale_provision_assignment`, `$assignment_fee_type`, `$assignment_fee_amount`, `$buyer_sell_contract`, `$offered_financing[]`, `$other_financing`, `$pre_approved`, `$pre_approval_amount`, `$purchase_price`, `$down_payment_amount`, `$seller_financing_amount`, `$interest_rate`, `$loan_duration`, `$prepayment_penalty`, `$prepayment_penalty_amount`, `$balloon_payment`, `$balloon_payment_amount`, `$balloon_payment_date`, `$assumable_terms`, `$max_assumable_rate`, `$max_monthly_payment`

**Buyer Status:**
`$current_status` (Currently Renting / First-Time Buyer / Homeowner – Selling to Buy / Homeowner – Keeping / Relocating / Investor)

**Buyer-specific flags:**
`$real_estate_purchase`, `$interested_lease_option_agreement`, `$previousOfferedFinancing[]` (tracks previous financing types for smart reset logic)

**Income / Business sub-fields:**
`$minimum_annual_net_income`, `$minimum_cap_rate`, `$unit_size`, `$number_of_unit`, `$business_assets[]`, `$appliances[]`, `$assets[]`

**Broker Compensation:**
`$commission_structure`, `$purchase_fee_type`, `$purchase_fee_flat`, `$purchase_fee_percentage`, `$purchase_fee_percentage_combo`, `$purchase_fee_flat_combo`, `$purchase_fee_other`, `$protection_period`, `$early_termination_fee_option`, `$early_termination_fee_amount`, `$retainer_fee_option`, `$retainer_fee_amount`, `$brokerage_relationship`, `$agency_agreement_timeframe`

### 5.3 Required Fields (Full Submit)

Always required:
- `counties` (array, min:1)
- `state` (string)

Conditionally required:
- `auction_time` — if Bidding Period
- `property_type` — always for full submit
- `bedrooms`, `bathrooms` — if Residential
- `target_closing_date` — always for full submit
- `maximum_budget` — always for full submit
- `phone_number` — always for full submit

> **Note:** Cities are explicitly optional in Buyer (`// Cities are optional - no validation required` comment in component).

### 5.4 File Upload

Only `$photo` (single personal photo, `WithFileUploads` trait still present but limited). Stored at `storage/auction/images/`. No video file upload — video link only.

### 5.5 Loading Guard Flag

`$isLoadingData = false` — same as Seller, different from Landlord's `$isLoadingDraft`. (Naming inconsistency across roles — see §8.)

---

## 6. Role: Tenant

### 6.1 Tab Structure (8 tabs)

| # | Tab File | Blade Title | Notes |
|---|----------|------------|-------|
| 1 | `tenant-info.blade.php` | Agent Credentials & Contact Info | First/last name, phone (masked, `formatPhoneNumber`), email; personal photo upload (Full Service only); personal video link with embed preview; privacy notice; identical structure to buyer-info |
| 2 | `listing-details.blade.php` | Listing Details | Service type picker fully commented out (both cards in `{{-- ... --}}`); auction type; listing title; dates; `$working_with_agent`; `$referral_percentage`; `$agent_bid_visibility`; `$meeting_Preference`; `$unit_types` |
| 3 | `property-details.blade.php` | Property Preferences *(h3 title mismatch — see §8)* | Acceptable cities (autocomplete multi-tag); counties (autocomplete multi-tag, required); state; zip codes; property type; property subtype; beds/baths (Residential required); minimum sq ft; lot size; garage; pool; view preferences; non-negotiable amenities; leasing 55+; appliances; property condition |
| 4 | `leasing-terms.blade.php` | Leasing Terms | Maximum Monthly Lease Price (required, `$budget`); Offered Lease Term — Residential (Select2 multi from `$lease_for_res`); Offered Lease Term — Commercial (Select2 multi from `$lease_for_com`); lease by date; move-in date; leasing space (multi, `$leasing_spaces_tenant`); acceptable leasing space |
| 5 | `pre-screening.blade.php` | Pre-Screening | Number of occupants (required); Estimated Monthly Net Income (required); Pets — Yes/No (Residential only); pet detail sub-fields (# of pets, type, breed, weight, service/ESA flags) (Residential + Pets=Yes); credit score rating (multi-select, `$credit_scroe_rating`); prior eviction flag + explanation; prior felony flag + explanation; other screening concerns (`$screening_concerns` required, `$screening_concerns_explanation` optional) |
| 6 | `additional-details.blade.php` | Describe your criteria… | Single `$additional_details` textarea — identical to Buyer |
| 7 | `services.blade.php` | Services the Tenant Requests | Residential/Commercial conditional sections: Tenant Criteria Marketing, Property Search & Alerts, Showings & Virtual Tours, Application & Lease Negotiation, After Move-In; `$other_services` |
| 8 | `broker-compensation.blade.php` | Broker Compensation | Tenant Broker Commission Structure (Out-of-Pocket vs Requested From Landlord); Tenant Broker Lease Fee — different options by property type: Residential (Flat Fee, % of Monthly Rent, % of Gross Lease Value, Flat + % Gross); Commercial (Flat Fee, % Net Aggregate Rent, Flat + % Net Aggregate); protection period; early termination; retainer; brokerage relationship; agency agreement timeframe |

> **No photos/tours, no disclosure, no tax/legal/HOA tabs** — same as Buyer.

### 6.2 Key Public Properties (Tenant-specific)

**Search Geography (same multi-tag pattern as Buyer):**
`$state`, `$cities[]`, `$counties[]`, `$zipCodes[]`

**Listing-specific extras not in Buyer:**
`$referral_percentage`, `$agent_bid_visibility`, `$unit_types`, `$meeting_Preference`

**Leasing Terms:**
`$budget` (max monthly rent — required), `$lease_for[]`, `$other_lease_for`, `$lease_by`, `$lease_date`, `$leasing_spaces`, `$leasing_spaces_tenant[]`, `$lease_amount_frequency`, `$desired_rental_amount_tenant`

**Pre-Screening (Tenant-unique tab):**
`$number_occupant` (required), `$monthly_income` (required), `$pets`, `$number_of_pets`, `$breed_of_pets`, `$type_of_pets`, `$weight_of_pets`, `$credit_scroe_rating[]` *(typo — see §8)*, `$prior_eviction`, `$eviction_explanation`, `$prior_felony`, `$prior_felony_explanation`, `$screening_concerns` (required), `$screening_concerns_explanation`

**Property Details:**
`$property_type`, `$property_items[]`, `$condition_prop`, `$condition_prop_buyer[]`, `$bathrooms`, `$bedrooms`, `$minimum_heated_square`, `$min_acreage`, `$garage_needed`, `$garage_parking_spaces`, `$garage_parking_spaces_option[]`, `$pool_needed`, `$pool_type[]`, `$view_preference[]`, `$non_negotiable_amenities[]`, `$leasing_55_plus`, `$appliances[]`, `$other_appliances`

**Broker Compensation:**
`$commission_structure`, `$lease_fee_type`, `$lease_fee_flat`, `$lease_fee_percentage`, `$lease_fee_months`, `$lease_fee_percentage_monthly_rent`, `$lease_fee_flat_combo`, `$lease_fee_percentage_combo`, `$lease_fee_other`

### 6.3 Required Fields (Full Submit)

The Tenant component has the most comprehensive dynamic validation block among the four roles (defined inline in the submit method, ~lines 3435–3603):

Always required:
- `service_type`, `user_type`, `expiration_date`, `auction_type`

Conditionally required (Tenant-specific block):
- `auction_time` — if Bidding Period
- `state`, `property_type` — always for full submit
- `bedrooms`, `bathrooms` — if Residential
- `pets` — if Residential *(note: `real_estate_purchase` also required for Residential per rules block)*
- `budget` — always (max monthly lease price)
- `number_occupant`, `monthly_income`, `screening_concerns` — always (pre-screening core)
- `phone_number` — always

Tenant also has a second validation block for the property-specific case (zip/county/address vs multi-city search), conditionally switching between geographic strategies.

### 6.4 Loading Guard Flag

Uses `$isLoadingDraft = false` (same naming as Landlord). Different name from Seller/Buyer's `$isLoadingData`.

---

## 7. Cross-Role Comparison Matrix

### 7.1 Tab / Feature Presence

| Feature | Seller | Landlord | Buyer | Tenant |
|---------|:------:|:--------:|:-----:|:------:|
| Contact / Info tab | ✓ (seller-info) | ✓ (landlord-info) | ✓ (buyer-info) | ✓ (tenant-info) |
| Listing Details tab | ✓ | ✓ | ✓ | ✓ |
| Property / Preferences tab | ✓ | ✓ | ✓ | ✓ |
| Financial / Purchasing / Lease Terms tab | ✓ (financial-details) | ✓ (lease-terms) | ✓ (purchasing-terms) | ✓ (leasing-terms) |
| Seller-specific Terms tab | ✓ (seller-terms) | ✗ | ✗ | ✗ |
| Pre-Screening tab | ✗ | ✗ | ✗ | ✓ |
| Additional Details tab | ✓ | ✓ | ✓ | ✓ |
| Services tab | ✓ | ✓ | ✓ | ✓ |
| Broker Compensation tab | ✓ | ✓ | ✓ | ✓ |
| Photos & Tours tab | ✓ (FS only) | ✓ (FS only) | ✗ | ✗ |
| Documents & Disclosures tab | ✓ (FS only) | ✓ (FS only) | ✗ | ✗ |
| Tax / Legal / HOA tab | ✓ (FS only) | ✓ (FS only) | ✗ | ✗ |

### 7.2 Service Type Picker UI Visibility

| Role | UI State |
|------|----------|
| Seller | Hidden (`d-none`) — full service is the only rendered card |
| Landlord | Hidden (`d-none`) — full service card only, limited service commented out |
| Buyer | Both cards rendered and visible (no `d-none`) |
| Tenant | Entire picker block commented out (`{{-- ... --}}`) |

### 7.3 Data Storage: Native Columns vs EAV

| Field Category | Seller | Landlord | Buyer | Tenant |
|----------------|--------|----------|-------|--------|
| All form fields | EAV only | EAV only | EAV only | EAV only |
| `property_photos` | Native column (JSON) | Native column (JSON) | — | — |
| Move-in funds | — | Native columns + EAV | — | — |
| Disclosure file paths | EAV | EAV | — | — |
| `screening_concerns` | — | — | — | EAV |

### 7.4 File Upload Capabilities

| Asset Type | Seller | Landlord | Buyer | Tenant |
|-----------|:------:|:--------:|:-----:|:------:|
| Multi-photo (up to 50) | ✓ | ✓ | ✗ | ✗ |
| Drag-drop reorder (SortableJS) | ✓ | ✓ | ✗ | ✗ |
| Cover photo designation | ✓ | ✓ | ✗ | ✗ |
| Disclosure file upload | ✓ (2 types) | ✓ (2 types) | ✗ | ✗ |
| Repeatable doc row upload | ✓ | ✓ | ✗ | ✗ |
| Personal photo | ✓ | ✓ | ✓ | ✓ |
| Video file upload | ✗ (removed) | ✗ (removed, commented) | ✗ | ✗ |
| Video link (YouTube/Vimeo) | ✓ | ✓ | ✓ | ✓ |

### 7.5 Geographic Input Strategy

| Strategy | Seller | Landlord | Buyer | Tenant |
|----------|:------:|:--------:|:-----:|:------:|
| Single property address | ✓ | ✓ | ✗ | ✗ |
| Multi-city tag (optional) | ✗ | ✗ | ✓ | ✓ |
| Multi-county tag (required) | ✗ | ✗ | ✓ | ✓ |
| Multi-zip tag | ✗ | ✗ | ✓ | ✓ |
| Auto-fill county from city | ✓ | ✓ | ✓ | ✓ |

### 7.6 Loading Guard Flag Naming

| Role | Property Name |
|------|--------------|
| Seller | `$isLoadingData` |
| Buyer | `$isLoadingData` |
| Landlord | `$isLoadingDraft` |
| Tenant | `$isLoadingDraft` |

### 7.7 Broker Compensation Fee Terminology

| Role | Fee Name | Commission Event |
|------|----------|-----------------|
| Seller | Seller's Broker Purchase Fee / Leasing Fee | Property closing / lease signing |
| Landlord | Landlord's Broker Lease Fee | Lease execution or Tenant move-in |
| Buyer | Buyer's Broker Purchase Fee | Property closing |
| Tenant | Tenant's Broker Lease Fee | Lease execution or Tenant move-in |

---

## 8. Findings & Issues

### F-01 — **Typo: `$credit_scroe_rating` (Tenant)**
**Severity:** Low (cosmetic + data key consistency)  
**File:** `app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php` (line 247), `TenantOfferListingEdit.php`, and `offer-tenant-listing.blade.php`  
**Detail:** The property is declared as `$credit_scroe_rating` (transposition of `score` → `scroe`). The same misspelling is used consistently throughout the component and blade, so there is no immediate runtime bug — load and save both use the same key. However, the EAV meta key `credit_scroe_rating` is now permanently misspelled in the database. Any new code referencing this concept must replicate the typo or risk a key mismatch.  
**Recommendation:** To fix cleanly, a data migration would need to rename the meta key to `credit_score_rating`, and all component references updated. Low priority but worth scheduling.

### F-02 — **`<h3>` / `<h4>` Mismatched Tags in Tenant Leasing Terms**
**Severity:** Low (HTML validity)  
**File:** `resources/views/livewire/offer-listing/offer-tenant-tabs/commission-based/leasing-terms.blade.php` (line 1)  
**Detail:** The heading opens as `<h3>` and closes as `</h4>`:
```html
<h3>Leasing Terms </h4>
```
This is invalid HTML and may cause rendering or accessibility issues. Browsers tolerate it but screen readers and validators will flag it.  
**Recommendation:** Change `</h4>` to `</h3>`.

### F-03 — **Blade File Name vs Heading Title Mismatch (Tenant Tab 3)**
**Severity:** Low (developer confusion)  
**File:** `resources/views/livewire/offer-listing/offer-tenant-tabs/commission-based/property-details.blade.php`  
**Detail:** The file is named `property-details.blade.php` but the `<h3>` inside reads `Property Preferences`. The Buyer role's equivalent tab is named `property-preferences.blade.php` and also reads `Property Preferences`. The mismatch between filename and displayed title can confuse developers navigating the codebase.  
**Recommendation:** Either rename the file to `property-preferences.blade.php` (matching Buyer) or update the heading to `Property Details` — whichever better reflects the intent.

### F-04 — **Service Type Picker UI State Inconsistency**
**Severity:** Low (UX inconsistency)  
**Files:** Listing-details partials for all four roles  
**Detail:** The service type picker (Full Service vs Limited Service cards) is rendered differently across roles:
- Buyer: Both cards visible and selectable
- Seller/Landlord: Full service card hidden with `d-none` (single-card layout visible)
- Tenant: Entire picker block commented out

This means Buyer users see a two-card choice that other roles do not. If this is intentional (Buyer supports limited service, others do not), it should be documented. If unintentional, the Buyer's picker should also be hidden.

### F-05 — **Loading Guard Flag Naming Inconsistency**
**Severity:** Low (code consistency)  
**Detail:** Seller and Buyer use `$isLoadingData`; Landlord and Tenant use `$isLoadingDraft`. Both serve the same purpose (prevent reactive hooks from resetting dependent fields during draft/edit population). The naming inconsistency makes the codebase harder to navigate and means the Edit components must be checked individually to confirm they use the matching name.  
**Recommendation:** Standardize to one name across all four roles (suggestion: `$isLoadingData` as it is more descriptive of intent).

### F-06 — **Buyer `services` Uses `wire:model` While Tenant Uses `wire:model.defer`**
**Severity:** Low (potential UX inconsistency)  
**Files:** `offer-buyer-tabs/.../services.blade.php` vs `offer-tenant-tabs/.../services.blade.php`  
**Detail:** Buyer service checkboxes use `wire:model="services"` (immediate sync on each checkbox change), while Tenant service checkboxes use `wire:model.defer="services"` (deferred until next Livewire action). This means Buyer triggers a round-trip for each checkbox tick; Tenant batches them. Neither is wrong, but the inconsistency is unexplained.  
**Recommendation:** Standardize to `wire:model.defer` across both to reduce unnecessary round-trips, unless the immediate sync is intentionally required for Buyer.

### F-07 — **Video File Upload Removed but Commented Code Remains (Landlord Info)**
**Severity:** Low (dead code)  
**File:** `resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/landlord-info.blade.php`  
**Detail:** The personal video file upload (`<input type="file" wire:model="video" ...>`) block is commented out with `<!-- ... -->` (lines 106–176). The associated Livewire properties (`$video`, `$video_link`) and methods likely still exist in the component. The component comment says "Video Upload removed from UI per requirements - DB/storage preserved."  
This is acceptable as a deliberate preservation decision, but the commented block is 70+ lines and adds noise to the partial.

### F-08 — **Landlord Listing-Details Has Only One Service Type Card (Full Service)**
**Severity:** Informational  
**File:** `resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/listing-details.blade.php`  
**Detail:** The Limited Service card is commented out in the blade (lines 61–90 in `{{-- ... --}}`), leaving only the Full Service card. The single remaining card is still hidden with `d-none` on the outer wrapper. This means the service type UI is completely invisible in Landlord, and the value defaults to `full_service` from the component initialization. This is presumably intentional (Landlord is Full Service only) but differs from how Seller handles it (same hidden approach) vs Buyer (visible choice).

### F-09 — **`$isLoadingDraft` Not Present in Seller Component**
**Severity:** Low  
**Detail:** Seller uses `$isLoadingData` (line 29 of `SellerOfferListing.php`). This is functionally equivalent but the name diverges from Landlord and Tenant. The Edit counterparts should be independently verified to confirm they use the same variable name as their Create counterpart.

### F-10 — **Move-In Fund Fields Stored as Both Native Columns and EAV (Landlord)**
**Severity:** Informational (potential confusion)  
**File:** `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php`  
**Detail:** `$security_deposit_required`, `$first_month_rent_required`, `$last_month_rent_required`, `$total_move_in_funds_required` are loaded from native columns (`$auction->get->security_deposit_required ?? ''`) but also saved via `saveMeta()`. This creates two sources of truth. Ensure that reads in the Edit component and the view page both use a consistent source (either always from native columns, or always from meta — not mixed).

### F-11 — **`$screening_concerns` Declared Without Default Value (Tenant)**
**Severity:** Low  
**File:** `app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php` (line 261)  
**Detail:** `public $screening_concerns;` — no `= ''` initializer. Livewire components should initialize all public properties to avoid type coercion warnings on PHP 8.x and null-comparison issues in blade conditionals. All other properties in this file use `= ''` or `= []`.  
**Recommendation:** Change to `public $screening_concerns = '';` and `public $screening_concerns_explanation = '';`.

### F-12 — **Tenant Listing-Details Has Extensive Landlord-Scoped Content (Legacy Cut-and-Paste)**
**Severity:** Low (dead code / developer confusion)  
**File:** `resources/views/livewire/offer-listing/offer-tenant-tabs/commission-based/listing-details.blade.php`  
**Detail:** The service type picker block is fully commented out via `{{-- --}}` but the comment block retains identical landlord-facing copy ("Hire a Commission-Based Agent…", "Hire a Flat Fee Agent…"). This suggests the Tenant listing-details was cloned from Landlord and the picker was then commented rather than removed. The `$referral_percentage`, `$agent_bid_visibility`, and `$unit_types` fields are present in this tab but absent from the other three roles — these appear to be Tenant-specific additions.

### F-13 — **`additional-details.blade.php` Is Identical for Buyer and Tenant**
**Severity:** Informational  
**Detail:** Both buyer and tenant `additional-details.blade.php` files contain literally identical content — same heading, same alert text, same `wire:model="additional_details"` textarea, same placeholder. They could be extracted to a shared partial if desired. Similarly, Seller and Landlord `additional-details.blade.php` files are near-identical (both are a single-textarea partial).

### F-14 — **Buyer `listing-details` Renders Both Service Type Cards (Potential UX Issue)**
**Severity:** Low  
**File:** `resources/views/livewire/offer-listing/offer-buyer-tabs/commission-based/listing-details.blade.php`  
**Detail:** Unlike Seller and Landlord (which hide the picker entirely), Buyer shows both Full Service and Limited Service cards. If limited service is not yet fully implemented for Buyer, selecting it could lead to an incomplete flow. Confirm whether `limited_service` is a supported path for Buyer, and if not, apply `d-none` or a `@if` guard.

---

## Appendix A — EAV Meta Keys by Role

All four roles store all form data via `saveMeta($key, $value)`. The key names are largely consistent across roles for shared concepts. Below are role-specific or noteworthy keys:

| Meta Key | Seller | Landlord | Buyer | Tenant | Notes |
|----------|:------:|:--------:|:-----:|:------:|-------|
| `property_photos` | ✓ | ✓ | — | — | JSON-encoded filename array; also stored in native column |
| `landlord_doc_rows` | — | ✓ | — | — | Alpine repeatable doc rows (JSON) |
| `seller_disclosure_available` | ✓ | — | — | — | |
| `landlord_disclosure_available` | — | ✓ | — | — | |
| `flood_disclosure_available` | ✓ | ✓ | — | — | |
| `lead_based_paint_disclosure` | ✓ | ✓ | — | — | |
| `credit_scroe_rating` | — | — | — | ✓ | Misspelled (see F-01) |
| `screening_concerns` | — | — | — | ✓ | Required for Tenant |
| `desired_lease_length` | — | ✓ | — | — | Required for Landlord submit |
| `security_deposit_required` | — | ✓ | — | — | Also native column |
| `referral_percentage` | — | — | — | ✓ | Agent-specific field |
| `agent_bid_visibility` | — | — | — | ✓ | Tenant-only |
| `parcel_id`, `tax_year`, `annual_property_taxes` | ✓ | ✓ | — | — | Tax/Legal tab |
| `listing_ai_faq` | ✓ | ✓ | — | — | JSON array, photos/tours tab |

---

## Appendix B — Tab Count Summary

| Role | Total Tabs | Full-Service-Only Tabs | Tabs |
|------|:----------:|:---------------------:|------|
| Seller | 11 | 3 (Photos, Docs, Tax/HOA) | listing-details, property-preferences, financial-details, seller-terms, additional-details, services, broker-compensation, seller-info, photos-tours-documents, documents-disclosures, tax-legal-hoa-disclosures |
| Landlord | 10 | 3 (Photos, Docs, Tax/HOA) | landlord-info, listing-details, property-preferences, lease-terms, additional-details, services, broker-compensation, photos-tours-documents, documents-disclosures, tax-legal-hoa-disclosures |
| Buyer | 7 | 0 | buyer-info, listing-details, property-preferences, purchasing-terms, additional-details, services, broker-compensation |
| Tenant | 8 | 0 | tenant-info, listing-details, property-details, leasing-terms, pre-screening, additional-details, services, broker-compensation |

---

*End of audit. Total partials examined: 36 blade files + 4 root blades + 4 Livewire Create components.*
