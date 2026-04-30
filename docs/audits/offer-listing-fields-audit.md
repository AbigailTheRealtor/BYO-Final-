# Offer Listing Intake Fields Audit
## Seller & Landlord — Commission-Based Form — All Property-Type Combinations

**Audit Date:** 2026-04-30
**Audit Type:** Read-only static source analysis (no runtime execution)
**Scope:** Full-service, commission-based wizard only — Seller and Landlord roles

---

## 1. Executive Summary

This report audits every user-facing field in the commission-based Offer Listing wizard for Seller and Landlord roles, measures each combination against ten MLS-standard field group categories, and identifies gaps with prioritized recommendations for new field groups.

**Overall finding:** All seven combinations provide strong coverage for Photos/Tours/Marketing, Financial Terms, and Broker Compensation. All seven combinations are completely absent for three categories — Legal/Tax/Parcel, CDD/Special Assessments/Exemptions, and HOA/Association (except a single free-text field for Seller types). Showing/Occupancy/Access and Documents/Disclosures are partially covered across all combinations but lack structured fields for lockbox, showing instructions, and disclosure types.

---

## 2. Methodology and Source Files

Every `wire:model` attribute was read directly from Blade source files. Property-type conditional branches (`@if`/`@elseif`) were traced in the same files. Component-class public property declarations were read from both Livewire PHP files to cross-check fields declared in the backend against those actually rendered.

| Source File | Lines |
|---|---|
| `app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php` | 3 121 |
| `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php` | 2 918 |
| `offer-seller-tabs/commission-based/listing-details.blade.php` | — |
| `offer-seller-tabs/commission-based/property-preferences.blade.php` | 3 028 |
| `offer-seller-tabs/commission-based/seller-terms.blade.php` | 1 978 |
| `offer-seller-tabs/commission-based/additional-details.blade.php` | — |
| `offer-seller-tabs/commission-based/photos-tours-documents.blade.php` | — |
| `offer-seller-tabs/commission-based/seller-info.blade.php` | — |
| `offer-seller-tabs/commission-based/services.blade.php` | — |
| `offer-seller-tabs/commission-based/broker-compensation.blade.php` | — |
| `offer-landlord-tabs/commission-based/listing-details.blade.php` | — |
| `offer-landlord-tabs/commission-based/property-preferences.blade.php` | 1 609 |
| `offer-landlord-tabs/commission-based/lease-terms.blade.php` | 1 484 |
| `offer-landlord-tabs/commission-based/additional-details.blade.php` | — |
| `offer-landlord-tabs/commission-based/photos-tours-documents.blade.php` | — |
| `offer-landlord-tabs/commission-based/landlord-info.blade.php` | — |
| `offer-landlord-tabs/commission-based/services.blade.php` | — |
| `offer-landlord-tabs/commission-based/broker-compensation.blade.php` + partials/ | — |
| `offer-listing/seller/offer-seller-listing.blade.php` | 2 988 |
| `offer-listing/shared/ai-questions-input.blade.php` | — |

All paths are relative to `resources/views/livewire/`.

---

## 3. Combination Codes and Wizard Structure

### 3.1 Combination Codes

| Code | Role | Property Type (`property_type` stored value) |
|---|---|---|
| **S-RES** | Seller | `Residential` |
| **S-INC** | Seller | `Income` |
| **S-COM** | Seller | `Commercial` |
| **S-BIZ** | Seller | `Business` |
| **S-VL** | Seller | `Vacant Land` |
| **L-RES** | Landlord | `Residential Property` |
| **L-COM** | Landlord | `Commercial Property` |

> Seller values omit "Property"; Landlord values include it. Both are stored as-is and used verbatim in blade conditionals.

### 3.2 Wizard Step Structure (Full-Service)

| Step | Tab Label | Seller Blade | Landlord Blade |
|---|---|---|---|
| 1 | Listing Details | `listing-details.blade.php` | `listing-details.blade.php` |
| 2 | Property Details | `property-preferences.blade.php` | `property-preferences.blade.php` |
| 3 | Sale Terms / Lease Terms | `seller-terms.blade.php` | `lease-terms.blade.php` |
| 4 | Additional Details | `additional-details.blade.php` | `additional-details.blade.php` |
| 5 | Photos, Tours & Documents | `photos-tours-documents.blade.php` | `photos-tours-documents.blade.php` |
| 6 | Seller / Landlord Information | `seller-info.blade.php` | `landlord-info.blade.php` |
| 7 | AI Questions | `shared/ai-questions-input.blade.php` | same |
| 8 | Services (wizard step) | `services.blade.php` | `services.blade.php` |
| 9 | Broker Compensation (wizard step) | `broker-compensation.blade.php` | `broker-compensation.blade.php` + 11 partials |

### 3.3 The Ten Audit Categories

| # | Category |
|:---:|---|
| 1 | Legal / Tax / Parcel |
| 2 | HOA / Association / Condo / Community |
| 3 | CDD / Special Assessments / Exemptions |
| 4 | Flood / Zoning / Land Use |
| 5 | Financial / Income / Commercial |
| 6 | Leasing Restrictions / Existing Lease |
| 7 | Pets |
| 8 | Showing / Occupancy / Access |
| 9 | Documents / Disclosures |
| 10 | Photos / Tours / Marketing |

---

## 4. Per-Combination Audit

---

### 4.1 S-RES — Seller / Residential

#### Existing Fields by Tab (Summary)

| Tab | Key Fields Present |
|---|---|
| 1 — Listing Details | `listing_status`, `listing_title`, `working_with_agent`, `listing_date`, `expiration_date`, `auction_type`, `auction_time`, `meeting_Preference` |
| 2 — Property Details | `address`, `property_city`, `property_state`, `property_county`, `property_zip`, `property_type`, `property_items`, `condition_prop`, `bedrooms`, `bathrooms`, `minimum_heated_square`, `total_square_feet`, `sqft_heated_source`, `total_acreage`, `year_built`, `appliances`, `carport_needed`, `garage_needed`, `pool_needed`, `pool_type`, `view_preference`, `non_negotiable_amenities`, `tenant_require`, `leasing_55_plus`, `pets`, `number_of_pets`, `type_of_pets`, `breed_of_pets`, `weight_of_pets`, `breed_restrictions`, `roof_type`, `exterior_construction`, `foundation`, `heating_and_fuel`, `air_conditioning`, `water`, `sewer`, `utilities` |
| 3 — Sale Terms | `sale_provision`, `target_closing_date`, `occupant_status`, `occupant_tenant`, `offered_financing`, `maximum_budget`, `pre_approved`, + all financing sub-sections (assumable, crypto, exchange, lease-option, lease-purchase, NFT, seller-financing), `hoa_condo_association_terms`, `initial_deposit_requested`, `escrow_agent_preference`, `preferred_inspection_period`, `appraisal_contingency_preference`, `financing_contingency_preference`, `sale_of_buyer_property_contingency`, `seller_contribution_credit_offered`, `possession_preference`, `included_personal_property`, `excluded_items`, `home_warranty_offered`, `additional_seller_sale_terms` |
| 4 — Additional Details | `additional_details` |
| 5 — Photos/Tours/Docs | `newPropertyPhotos`, `videoTourUrl`, `virtualTourUrl`, `listingDocuments` |
| 6 — Seller Info | `first_name`, `last_name`, `phone_number`, `email`, `current_status`, `photo`, `video_link` |
| 7 — AI Questions | `listing_ai_faq.{key}` (dynamic) |
| 8 — Services | `services`, `photo_enhancements`, `custom_enhancement`, `openHouseCount` |
| 9 — Broker Compensation | `commission_structure`, `purchase_fee_type`, all seller leasing/purchase fee fields, `protection_period`, `agency_agreement_timeframe`, `brokerage_relationship` |

#### Gap Analysis Against Ten MLS Categories

| # | Category | Status | Fields Present | Fields Missing |
|:---:|---|:---:|---|---|
| 1 | Legal / Tax / Parcel | **Missing** | None | Parcel ID / folio number, legal description, county records link, annual tax amount, tax year, tax exemptions in place |
| 2 | HOA / Association / Condo / Community | **Partial** | `hoa_condo_association_terms` (free-text in Sale Terms) | HOA name, monthly/annual HOA fee, HOA fee frequency, HOA approval required (Y/N), HOA contact info, condo association name, pet restrictions per HOA rules |
| 3 | CDD / Special Assessments / Exemptions | **Missing** | None | CDD district name, CDD annual amount, special assessment balance, homestead exemption status, other exemptions (widow, veteran, disability) |
| 4 | Flood / Zoning / Land Use | **Missing** | None | FEMA flood zone designation, flood insurance required (Y/N), FEMA map number, zoning classification (residential has no `zoning` field) |
| 5 | Financial / Income / Commercial | **Not Applicable** | Standard purchase financial terms present (budget, financing, deposits, contingencies) | N/A for standard residential sale |
| 6 | Leasing Restrictions / Existing Lease | **Partial** | `occupant_status`, `occupant_tenant` (tenant occupancy date) | Existing lease expiration date, current monthly rent, lease type (month-to-month vs fixed), minimum lease term from HOA/association |
| 7 | Pets | **Full** | `pets`, `number_of_pets`, `type_of_pets`, `breed_of_pets`, `weight_of_pets`, `breed_restrictions` | — |
| 8 | Showing / Occupancy / Access | **Partial** | `occupant_status`, `meeting_Preference` | Lockbox type/location, showing notice required (hours), showing contact person, showing hours restrictions, access code/entry instructions |
| 9 | Documents / Disclosures | **Partial** | `listingDocuments` (single file upload) | Seller's property disclosure form (structured), lead paint disclosure, mold disclosure, radon disclosure, survey available (Y/N), title search/insurance available, inspection report available |
| 10 | Photos / Tours / Marketing | **Full** | `newPropertyPhotos`, `videoTourUrl`, `virtualTourUrl`, `services` checklist, `photo_enhancements`, `openHouseCount` | — |

#### Recommended New Field Groups

| Field Group | Suggested Fields | Priority | Suggested Tab |
|---|---|---|---|
| Legal / Tax / Parcel | Parcel ID / folio number (text), legal description (textarea), annual property tax (number), tax year (year), tax exemptions in place (multi-select: Homestead, Veteran, Widow, Disability) | **Launch Critical** | Tab 2 — Property Details |
| HOA / Association | HOA name (text), HOA fee amount (number), HOA fee frequency (select: Monthly/Quarterly/Annually), HOA approval required (yes/no), condo association name (text) | **Launch Critical** | Tab 3 — Sale Terms (after `hoa_condo_association_terms`) |
| CDD / Special Assessments | CDD district (text), CDD annual amount (number), special assessment outstanding balance (number) | **Important** | Tab 3 — Sale Terms |
| Flood / Zoning | FEMA flood zone (select: A/AE/X/Other/Unknown), flood insurance required (yes/no), zoning classification (text) | **Launch Critical** | Tab 2 — Property Details |
| Existing Lease | Existing lease type (select: None/Month-to-Month/Fixed-Term), lease expiration date (date, conditional), current monthly rent (number, conditional), HOA minimum lease term (text) | **Important** | Tab 3 — Sale Terms (near `occupant_status`) |
| Showing / Access | Lockbox type (select: Combo/Electronic/Agent Accompanied/No Lockbox), showing notice required (select: 0h/1h/2h/24h/48h), showing contact name (text), showing hours restrictions (text), gate/access code (text) | **Launch Critical** | Tab 3 — Sale Terms or Tab 1 — Listing Details |
| Disclosures | Seller disclosure form uploaded (yes/no + file), lead paint disclosure (yes/no, pre-1978 properties), mold disclosure (yes/no), radon disclosure (yes/no), survey available (yes/no), title insurance available (yes/no) | **Important** | Tab 5 — Photos/Tours/Docs (add disclosure section) |

---

### 4.2 S-INC — Seller / Income

#### Existing Fields by Tab (Summary)

| Tab | Key Fields Present |
|---|---|
| 2 — Property Details | All S-RES address + classification fields; additionally: `number_of_units`, `number_occupied`, `expected_rent`, `unit_number`, `unit_buildings`, `number_of_unit`, `beds_unit`, `baths_unit`, `garage_spaces`, `carport_spaces`, `minimum_annual_net_income`, `minimum_cap_rate`, `assets`, `real_estate_purchase`, `unit_size`, `property_criteria`, `zoning`, `pool_needed`, `pool_type`, `pets`, pet detail fields; MLS systems: `roof_type`, `exterior_construction`, `foundation`, `heating_and_fuel`, `air_conditioning`, `water`, `sewer`, `utilities` |
| 3 — Sale Terms | All S-RES sale terms fields including `hoa_condo_association_terms`, occupant status |
| 5 — Photos/Tours/Docs | Same as S-RES |
| 9 — Broker Compensation | Same as S-RES |

#### Gap Analysis Against Ten MLS Categories

| # | Category | Status | Fields Present | Fields Missing |
|:---:|---|:---:|---|---|
| 1 | Legal / Tax / Parcel | **Missing** | None | Parcel ID(s) per building, legal description, annual taxes per unit or total, tax year |
| 2 | HOA / Association / Condo / Community | **Partial** | `hoa_condo_association_terms` (free-text) | HOA name, HOA fee per unit, HOA approval required, leasing restrictions from HOA |
| 3 | CDD / Special Assessments / Exemptions | **Missing** | None | CDD amount per unit, special assessment balance, investment property tax exemptions |
| 4 | Flood / Zoning / Land Use | **Partial** | `zoning` | FEMA flood zone, flood insurance required, environmental restrictions |
| 5 | Financial / Income / Commercial | **Partial** | `minimum_annual_net_income`, `minimum_cap_rate`, `expected_rent`, `number_of_units`, `number_occupied`, `unit_size` | Gross annual income, gross operating expenses, NOI, price per unit, DSCR, rent roll available (Y/N), current lease expiration dates per unit |
| 6 | Leasing Restrictions / Existing Lease | **Partial** | `occupant_status`, `occupant_tenant` | Existing lease expiration dates per unit, current monthly rent per unit, lease type per unit, assignment / subletting restrictions |
| 7 | Pets | **Partial** | `pets`, `number_of_pets`, `type_of_pets`, `breed_of_pets`, `weight_of_pets`, `breed_restrictions` | These fields describe the buyer's pet criteria, not the property's existing pet policy. A separate property-level pet policy field is absent. |
| 8 | Showing / Occupancy / Access | **Partial** | `occupant_status` | Lockbox type, showing notice required, showing contact, occupied-unit showing coordination instructions |
| 9 | Documents / Disclosures | **Partial** | `listingDocuments` | Rent roll document, operating statement / P&L, seller disclosure, environmental report, survey |
| 10 | Photos / Tours / Marketing | **Full** | Same as S-RES | — |

#### Recommended New Field Groups

| Field Group | Suggested Fields | Priority | Suggested Tab |
|---|---|---|---|
| Legal / Tax / Parcel | Parcel ID (text), legal description (textarea), annual taxes total (number), tax year (year) | **Launch Critical** | Tab 2 — Property Details |
| Financial / Income | Gross annual income (number), gross annual operating expenses (number), NOI (calculated or manual), price per unit (number), rent roll document upload (file), operating statement upload (file) | **Launch Critical** | Tab 2 — Property Details (Income section) |
| HOA / Association | HOA name, HOA fee per unit, HOA leasing restrictions (text) | **Important** | Tab 3 — Sale Terms |
| Flood / Zoning | FEMA flood zone (select), flood insurance required (yes/no) | **Launch Critical** | Tab 2 — Property Details |
| Existing Lease Details | Lease type per unit (select), lease expiration (date), current monthly rent per unit (number) | **Important** | Tab 3 — Sale Terms |
| Showing / Access | Lockbox type, showing notice required, showing contact, occupied-unit coordination note (textarea) | **Launch Critical** | Tab 3 — Sale Terms |
| Disclosures | Seller disclosure (file), environmental report (file), inspection report (file), survey available (yes/no) | **Important** | Tab 5 — Photos/Tours/Docs |

---

### 4.3 S-COM — Seller / Commercial

#### Existing Fields by Tab (Summary)

| Tab | Key Fields Present |
|---|---|
| 2 — Property Details | Address fields; `property_type`, `property_items`, `condition_prop`, `bathrooms`, `minimum_heated_square`, `total_square_feet`, `sqft_heated_source`, `total_acreage`, `year_built`, `business_type`, `leasing_space`, `garage_parking_spaces`, `non_negotiable_amenities`, `assets`, `minimum_annual_net_income`, `minimum_cap_rate`, `zoning`, `road_frontage`, `road_surface_type`, `electrical_service`, `ceiling_height`, `building_features`, `number_water_meters`, `number_electric_meters`, `heating_and_fuel`, `air_conditioning`, `sewer`, `utilities` |
| 3 — Sale Terms | All Seller sale terms; `hoa_condo_association_terms`, `occupant_status` |
| 5 — Photos/Tours/Docs | Same as S-RES |
| 9 — Broker Compensation | Same as S-RES |

#### Gap Analysis Against Ten MLS Categories

| # | Category | Status | Fields Present | Fields Missing |
|:---:|---|:---:|---|---|
| 1 | Legal / Tax / Parcel | **Missing** | None | Parcel ID, legal description, annual commercial taxes, tax year |
| 2 | HOA / Association / Condo / Community | **Partial** | `hoa_condo_association_terms` (free-text, rarely applicable to commercial) | Commercial association name, CAM administrative fee, association assessment |
| 3 | CDD / Special Assessments / Exemptions | **Missing** | None | CDD district, special assessment balance, business tax receipt / BTR |
| 4 | Flood / Zoning / Land Use | **Partial** | `zoning`, `road_frontage`, `road_surface_type` | FEMA flood zone, flood insurance required, land use designation, environmental restrictions, wetlands |
| 5 | Financial / Income / Commercial | **Partial** | `minimum_annual_net_income`, `minimum_cap_rate`, `assets` | Gross income, operating expenses, NOI, price per sq ft, existing tenant NNN details, DSCR |
| 6 | Leasing Restrictions / Existing Lease | **Partial** | `occupant_status`, `leasing_space` | Existing tenant name, lease type (NNN/Gross/Modified Gross), lease expiration, current rent, assignability of existing leases |
| 7 | Pets | **Missing** | None | N/A for commercial (correct to be absent in most cases; service animal policy may be relevant) |
| 8 | Showing / Occupancy / Access | **Partial** | `occupant_status` | Showing contact, showing notice, access instructions, tenant right-to-refuse showing |
| 9 | Documents / Disclosures | **Partial** | `listingDocuments` | Seller disclosure, environmental site assessment (Phase I/II), survey, title insurance, operating statements |
| 10 | Photos / Tours / Marketing | **Full** | Same as S-RES | — |

#### Recommended New Field Groups

| Field Group | Suggested Fields | Priority | Suggested Tab |
|---|---|---|---|
| Legal / Tax / Parcel | Parcel ID, legal description, annual commercial taxes, tax year | **Launch Critical** | Tab 2 — Property Details |
| Financial / Income | Gross annual income, operating expenses, NOI, price per sq ft, existing tenant lease type (NNN/Gross/Modified Gross) | **Launch Critical** | Tab 2 — Property Details |
| Flood / Zoning / Environmental | FEMA flood zone, flood insurance required, environmental restrictions (yes/no), Phase I assessment available (yes/no) | **Launch Critical** | Tab 2 — Property Details |
| Existing Tenant Details | Existing tenant name (text), lease expiration (date), current rent (number), lease assignable (yes/no) | **Important** | Tab 3 — Sale Terms |
| Showing / Access | Showing contact, showing notice, tenant right-to-refuse (yes/no), access instructions | **Important** | Tab 3 — Sale Terms |
| Documents / Disclosures | Environmental assessment upload, operating statements upload, survey available (yes/no), title insurance (yes/no) | **Important** | Tab 5 — Photos/Tours/Docs |

---

### 4.4 S-BIZ — Seller / Business

#### Existing Fields by Tab (Summary)

| Tab | Key Fields Present |
|---|---|
| 2 — Property Details | Address fields; `property_type`, `property_items`, `condition_prop`, `bathrooms`, `minimum_heated_square`, `total_square_feet`, `sqft_heated_source`, `total_acreage`, `year_built`, `business_type`, `business_name`, `year_established`, `licenses`, `sale_includes`, `assets`, `real_estate_purchase`, `minimum_annual_net_income`, `minimum_cap_rate`, `garage_parking_spaces`, `non_negotiable_amenities` |
| 3 — Sale Terms | All Seller sale terms; `hoa_condo_association_terms`, `occupant_status` |
| 5 — Photos/Tours/Docs | Same as S-RES |
| 9 — Broker Compensation | Same as S-RES |

Note: HVAC, roof type, foundation, water, sewer, and full mechanical MLS multi-selects are not rendered in the Business branch of `property-preferences.blade.php`; `zoning` is also absent for Business.

#### Gap Analysis Against Ten MLS Categories

| # | Category | Status | Fields Present | Fields Missing |
|:---:|---|:---:|---|---|
| 1 | Legal / Tax / Parcel | **Missing** | None | Parcel ID, legal description, annual taxes, tax year, business tax receipt number |
| 2 | HOA / Association / Condo / Community | **Partial** | `hoa_condo_association_terms` (free-text, rarely applicable for business sales) | Shared building association fees, shared-space administrative fees |
| 3 | CDD / Special Assessments / Exemptions | **Missing** | None | Special assessment balance, business tax receipt, CDD |
| 4 | Flood / Zoning / Land Use | **Missing** | None | Zoning classification, FEMA flood zone, land use designation (BIZ has no `zoning` field unlike COM) |
| 5 | Financial / Income / Commercial | **Partial** | `business_name`, `year_established`, `licenses`, `sale_includes`, `assets`, `minimum_annual_net_income`, `minimum_cap_rate`, `real_estate_purchase` | Annual revenue, gross profit, asking multiple, EBITDA, SDE (Seller's Discretionary Earnings), reason for sale, employee count |
| 6 | Leasing Restrictions / Existing Lease | **Partial** | `occupant_status` | Property lease expiration, current rent, lease assignability, personal guarantee on existing lease |
| 7 | Pets | **Missing** | None | Not typically applicable for business sale; service animal policy may be relevant |
| 8 | Showing / Occupancy / Access | **Partial** | `occupant_status` | Showing contact, showing notice, NDA required before showing (yes/no), employee awareness of sale (yes/no) |
| 9 | Documents / Disclosures | **Partial** | `listingDocuments` | Seller disclosure, tax returns available (yes/no), financials available (yes/no), NDA template upload |
| 10 | Photos / Tours / Marketing | **Full** | Same as S-RES | — |

#### Recommended New Field Groups

| Field Group | Suggested Fields | Priority | Suggested Tab |
|---|---|---|---|
| Legal / Tax / Parcel | Parcel ID (if real estate), legal description, business tax receipt number | **Launch Critical** | Tab 2 — Property Details |
| Business Financials | Annual revenue (number), gross profit (number), SDE/EBITDA (number), asking multiple (text), reason for sale (select/text), employee count (number) | **Launch Critical** | Tab 2 — Property Details (Business section) |
| Flood / Zoning | Zoning classification (text), FEMA flood zone (select) | **Important** | Tab 2 — Property Details |
| Existing Lease | Property lease expiration (date), current monthly rent (number), lease assignable (yes/no) | **Launch Critical** | Tab 3 — Sale Terms |
| Showing Conditions | NDA required before showing (yes/no), employees aware of sale (yes/no), showing contact (text) | **Launch Critical** | Tab 3 — Sale Terms |
| Disclosures | Tax returns available (yes/no), financial statements available (yes/no), NDA template (file upload) | **Important** | Tab 5 — Photos/Tours/Docs |

---

### 4.5 S-VL — Seller / Vacant Land

#### Existing Fields by Tab (Summary)

| Tab | Key Fields Present |
|---|---|
| 2 — Property Details | Address fields; `property_type`, `property_items`, `total_acreage`, `lot_dimensions`, `front_footage`, `number_of_wells`, `number_of_septics`, `buildable`, `easements`, `fences`, `vegetation`, `current_use`, `current_adjacent_use`, `view_preference` |
| 3 — Sale Terms | All Seller sale terms (occupant status suppressed); `hoa_condo_association_terms` present |
| 5 — Photos/Tours/Docs | Same as S-RES |
| 9 — Broker Compensation | Same as S-RES |

Note: Bedrooms, bathrooms, appliances, HVAC, pool, carport/garage, `leasing_55_plus`, pets, and occupant status are all absent for Vacant Land.

#### Gap Analysis Against Ten MLS Categories

| # | Category | Status | Fields Present | Fields Missing |
|:---:|---|:---:|---|---|
| 1 | Legal / Tax / Parcel | **Missing** | None | Parcel ID / folio number, legal description, section/township/range, annual taxes, tax year |
| 2 | HOA / Association / Condo / Community | **Partial** | `hoa_condo_association_terms` (free-text) | HOA name, HOA fee, deed restrictions (critical for VL), community development controls |
| 3 | CDD / Special Assessments / Exemptions | **Missing** | None | CDD district, special assessment, agricultural exemption status, greenbelt classification |
| 4 | Flood / Zoning / Land Use | **Partial** | `buildable`, `current_use`, `current_adjacent_use`, `easements`, `vegetation` | FEMA flood zone (critical for land), flood insurance required, zoning classification, land use designation, wetlands presence, environmental restrictions, minimum lot size requirements |
| 5 | Financial / Income / Commercial | **Not Applicable** | Standard purchase financial terms | N/A for vacant land |
| 6 | Leasing Restrictions / Existing Lease | **Missing** | None | Leasing / grazing / mineral rights / timber rights in place, right-of-way agreements |
| 7 | Pets | **Missing** | None | N/A for vacant land |
| 8 | Showing / Occupancy / Access | **Partial** | `meeting_Preference` | Showing / access instructions, gate code, road access type (paved/dirt/none), GPS coordinates |
| 9 | Documents / Disclosures | **Partial** | `listingDocuments` | Seller disclosure, survey available (Y/N), soil test available, percolation test available, environmental study, title insurance |
| 10 | Photos / Tours / Marketing | **Full** | Same as S-RES | — |

#### Recommended New Field Groups

| Field Group | Suggested Fields | Priority | Suggested Tab |
|---|---|---|---|
| Legal / Tax / Parcel | Parcel ID / folio number, legal description (section/township/range), annual taxes, tax year, agricultural exemption status | **Launch Critical** | Tab 2 — Property Details |
| Flood / Zoning / Environmental | FEMA flood zone (select), zoning classification (text), land use designation (text), wetlands present (yes/no), environmental restrictions (textarea) | **Launch Critical** | Tab 2 — Property Details |
| HOA / Deed Restrictions | HOA name (text), HOA fee (number), deed restrictions (textarea) | **Important** | Tab 3 — Sale Terms |
| Existing Rights | Mineral rights (select: Included/Excluded/Severed), timber rights (select), grazing lease in place (yes/no) | **Important** | Tab 3 — Sale Terms |
| Access / Location | Road access type (select: Paved/Unpaved/Easement/None), GPS coordinates (text), gate code (text) | **Important** | Tab 3 — Sale Terms |
| Disclosures / Studies | Survey available (yes/no), soil test available (yes/no), percolation test available (yes/no), environmental study (file upload) | **Launch Critical** | Tab 5 — Photos/Tours/Docs |

---

### 4.6 L-RES — Landlord / Residential Property

#### Existing Fields by Tab (Summary)

| Tab | Key Fields Present |
|---|---|
| 2 — Property Details | Address fields; `property_type` (Residential Property), `property_items`, `condition_prop`, `bedrooms`, `bathrooms`, `minimum_heated_square`, `total_square_feet`, `sqft_heated_source`, `total_acreage`, `year_built`, `appliances`, `carport_needed`, `garage_needed`, `pool_needed`, `pool_type`, `leasing_55_plus`, `non_negotiable_amenities`, `tenant_require`, `view_preference`, `heating_and_fuel`, `air_conditioning`, `sewer`, `utilities`, `laundry_features`, `floor_covering` |
| 3 — Lease Terms | `occupant_status`, `occupant_tenant`, `leasing_spaces`, `restrictions`, `maintenance_by`, `maintenance_response_time`, storage-space fields, `guests_allowed`, `common_areas_access`, `utilities`, `common_areas_cleaning`, `bathroom_facilities`, `room_size`, `rent_includes`, `desired_rental_amount`, `lease_amount_frequency`, `desired_lease_length`, `lease_available_date`, `security_deposit_required`, `first_month_rent_required`, `last_month_rent_required`, `total_move_in_funds_required`, `pet_policy`, `pet_deposit_fee_rent`, `number_of_occupants_allowed`, `parking_terms`, `utility_responsibility`, `ll_maintenance_responsibility`, `renewal_option_offered`, `renewal_option_details`, `landlord_approval_conditions`, `additional_landlord_lease_terms` |
| 5 — Photos/Tours/Docs | Same as Seller |
| 9 — Broker Compensation | Residential lease fee fields, protection period, renewal fee, interested_lease_option_agreement |

#### Gap Analysis Against Ten MLS Categories

| # | Category | Status | Fields Present | Fields Missing |
|:---:|---|:---:|---|---|
| 1 | Legal / Tax / Parcel | **Missing** | None | Parcel ID / folio number, legal description, annual taxes (less critical for rental but relevant for disclosure) |
| 2 | HOA / Association / Condo / Community | **Missing** | None | HOA name, HOA rules on leasing (minimum term, approval required), HOA contact, condo association pet restrictions, HOA leasing fee |
| 3 | CDD / Special Assessments / Exemptions | **Missing** | None | CDD annual amount passed through to tenant, special assessments |
| 4 | Flood / Zoning / Land Use | **Missing** | None | FEMA flood zone (disclosure required in many states), flood insurance required |
| 5 | Financial / Income / Commercial | **Full** | `desired_rental_amount`, `lease_amount_frequency`, `desired_lease_length`, `security_deposit_required`, `first_month_rent_required`, `last_month_rent_required`, `total_move_in_funds_required` | — |
| 6 | Leasing Restrictions / Existing Lease | **Full** | `desired_lease_length`, `renewal_option_offered`, `renewal_option_details`, `ll_maintenance_responsibility`, `landlord_approval_conditions`, `additional_landlord_lease_terms`, `restrictions` (leasing space section) | — |
| 7 | Pets | **Full** | `pet_policy`, `pet_deposit_fee_rent` (plus property-preference side: `leasing_55_plus`, `non_negotiable_amenities`) | — |
| 8 | Showing / Occupancy / Access | **Partial** | `occupant_status`, `occupant_tenant` | Lockbox type, showing notice required, showing contact, access/gate code, occupied-unit tenant consent required |
| 9 | Documents / Disclosures | **Partial** | `listingDocuments` | Landlord disclosure form, lead paint disclosure (for pre-1978 properties), mold disclosure, move-in inspection form, HOA documents |
| 10 | Photos / Tours / Marketing | **Full** | `newPropertyPhotos`, `videoTourUrl`, `virtualTourUrl`, `listingDocuments`, `services` | — |

#### Recommended New Field Groups

| Field Group | Suggested Fields | Priority | Suggested Tab |
|---|---|---|---|
| HOA / Association | HOA name (text), HOA leasing rules — minimum term (number, months), HOA approval required (yes/no), HOA contact (text), condo association name (text), HOA move-in/move-out fees (number) | **Launch Critical** | Tab 3 — Lease Terms |
| Flood / Zoning | FEMA flood zone (select: X/AE/A/Other/Unknown), flood insurance required (yes/no) | **Launch Critical** | Tab 2 — Property Details |
| Showing / Access | Lockbox type (select: Combo/Electronic/Agent Accompanied/None), showing notice required (select: 0h/1h/2h/24h/48h), showing contact (text), gate/access code (text) | **Launch Critical** | Tab 3 — Lease Terms or Tab 1 — Listing Details |
| Disclosures | Lead paint disclosure (yes/no, pre-1978 required), mold disclosure (yes/no), landlord disclosure form (file), HOA documents (file) | **Important** | Tab 5 — Photos/Tours/Docs |
| Legal / Tax | Parcel ID (text) | **Later Enhancement** | Tab 2 — Property Details |

---

### 4.7 L-COM — Landlord / Commercial Property

#### Existing Fields by Tab (Summary)

| Tab | Key Fields Present |
|---|---|
| 2 — Property Details | Address fields; `property_type` (Commercial Property), `property_items`, `condition_prop`, `bathrooms`, `minimum_leaseable`, `total_square_feet`, `sqft_heated_source`, `total_acreage`, `year_built`, `garage_parking_spaces`, `non_negotiable_amenities`, `zoning`, `total_buildings`, `total_units_on_property`, `office_retail_sqft`, `flex_space_sqft`, `road_surface_type`, `utilities`, `sewer`, `heating_and_fuel`, `air_conditioning`, `electrical_service`, `ceiling_height`, `building_features`, `number_electric_meters`, `number_water_meters`, `number_gas_meters`, `number_of_restrooms` |
| 3 — Lease Terms | `occupant_status`, `occupant_tenant`, `leasing_spaces`, commercial sub-fields (`restrictions`, `maintenance_by`, `shared_amenities`, `building_hours`, `access_24_7`, `zoning_allows`, `space_features`, `neighboring_tenants`, storage-space fields, `bathroom_facilities`), `tenant_pays`, `owner_pays`, `terms_of_lease`, `desired_rental_amount`, `lease_amount_frequency`, `desired_lease_length`, `commercial_lease_type`, `cam_nnn_additional_rent_charges`, `rent_escalation_terms`, `tenant_improvement_buildout_terms`, `permitted_use_restrictions`, `signage_rights`, `commercial_parking_terms`, `personal_guarantee_requirement`, `commercial_approval_conditions` |
| 5 — Photos/Tours/Docs | Same as Seller |
| 9 — Broker Compensation | Full commercial lease fee fields, tenant broker commission, expansion commission, property management interest, renewal fee |

#### Gap Analysis Against Ten MLS Categories

| # | Category | Status | Fields Present | Fields Missing |
|:---:|---|:---:|---|---|
| 1 | Legal / Tax / Parcel | **Missing** | None | Parcel ID, legal description, annual commercial taxes, business tax receipt, tax year |
| 2 | HOA / Association / Condo / Community | **Missing** | None | Commercial condominium association name, condo association fees, association leasing rules |
| 3 | CDD / Special Assessments / Exemptions | **Missing** | None | CDD district, CDD annual charge, special assessment balance, business tax receipt number |
| 4 | Flood / Zoning / Land Use | **Partial** | `zoning`, `zoning_allows`, `road_surface_type` | FEMA flood zone, flood insurance required, environmental restrictions, land use designation |
| 5 | Financial / Income / Commercial | **Full** | `desired_rental_amount`, `lease_amount_frequency`, `cam_nnn_additional_rent_charges`, `rent_escalation_terms`, `commercial_lease_type`, `tenant_pays`, `owner_pays`, `tenant_improvement_buildout_terms` | — |
| 6 | Leasing Restrictions / Existing Lease | **Full** | `permitted_use_restrictions`, `terms_of_lease`, `commercial_lease_type`, `commercial_approval_conditions`, `personal_guarantee_requirement`, `desired_lease_length`, `signage_rights` | — |
| 7 | Pets | **Missing** | None | Service animal policy (relevant for ADA compliance in commercial) |
| 8 | Showing / Occupancy / Access | **Partial** | `occupant_status`, `access_24_7`, `building_hours` | Showing contact person, showing notice required, lockbox / key control, occupied-tenant consent, security clearance requirements |
| 9 | Documents / Disclosures | **Partial** | `listingDocuments` | Environmental Site Assessment (Phase I) available (yes/no), survey available (yes/no), title insurance, certificate of occupancy, operating statements |
| 10 | Photos / Tours / Marketing | **Full** | Same as Seller | — |

#### Recommended New Field Groups

| Field Group | Suggested Fields | Priority | Suggested Tab |
|---|---|---|---|
| Legal / Tax / Parcel | Parcel ID (text), legal description (textarea), annual commercial taxes (number), tax year (year), business tax receipt number (text) | **Launch Critical** | Tab 2 — Property Details |
| Flood / Zoning / Environmental | FEMA flood zone (select), flood insurance required (yes/no), environmental restrictions (textarea), Phase I assessment available (yes/no) | **Launch Critical** | Tab 2 — Property Details |
| Showing / Access | Showing contact person (text), showing notice required (select), security clearance requirements (textarea), occupied-tenant consent required (yes/no) | **Important** | Tab 3 — Lease Terms |
| Documents / Disclosures | Phase I ESA available (yes/no), operating statements (file), certificate of occupancy (file), survey available (yes/no) | **Important** | Tab 5 — Photos/Tours/Docs |
| CDD / Special Assessments | CDD district (text), CDD annual charge (number), special assessment balance (number) | **Important** | Tab 2 — Property Details |

---

## 5. Cross-Reference Matrix — Ten MLS Categories × Seven Combinations

**Legend:** Full = all standard fields present | Partial = some fields present, specifics in gaps column | Missing = no fields of this category rendered

| # | MLS Category | S-RES | S-INC | S-COM | S-BIZ | S-VL | L-RES | L-COM |
|:---:|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| 1 | Legal / Tax / Parcel | **Missing** | **Missing** | **Missing** | **Missing** | **Missing** | **Missing** | **Missing** |
| 2 | HOA / Association / Condo / Community | **Partial** ¹ | **Partial** ¹ | **Partial** ¹ | **Partial** ¹ | **Partial** ¹ | **Missing** | **Missing** |
| 3 | CDD / Special Assessments / Exemptions | **Missing** | **Missing** | **Missing** | **Missing** | **Missing** | **Missing** | **Missing** |
| 4 | Flood / Zoning / Land Use | **Missing** | **Partial** ² | **Partial** ³ | **Missing** | **Partial** ⁴ | **Missing** | **Partial** ⁵ |
| 5 | Financial / Income / Commercial | N/A ⁶ | **Partial** ⁷ | **Partial** ⁸ | **Partial** ⁹ | N/A | **Full** | **Full** |
| 6 | Leasing Restrictions / Existing Lease | **Partial** ¹⁰ | **Partial** ¹⁰ | **Partial** ¹⁰ | **Partial** ¹⁰ | **Missing** | **Full** | **Full** |
| 7 | Pets | **Full** | **Partial** ¹¹ | **Missing** | **Missing** | **Missing** | **Full** | **Missing** |
| 8 | Showing / Occupancy / Access | **Partial** ¹² | **Partial** ¹² | **Partial** ¹² | **Partial** ¹² | **Partial** ¹³ | **Partial** ¹² | **Partial** ¹² |
| 9 | Documents / Disclosures | **Partial** ¹⁴ | **Partial** ¹⁴ | **Partial** ¹⁴ | **Partial** ¹⁴ | **Partial** ¹⁴ | **Partial** ¹⁴ | **Partial** ¹⁴ |
| 10 | Photos / Tours / Marketing | **Full** | **Full** | **Full** | **Full** | **Full** | **Full** | **Full** |

**Footnotes:**

1. **HOA (Seller combinations):** A single free-text field `hoa_condo_association_terms` exists in the Sale Terms tab. No structured HOA name, fee amount, fee frequency, or approval-required fields are present.
2. **S-INC Flood/Zoning:** `zoning` field present. No FEMA flood zone, flood insurance, or environmental fields.
3. **S-COM Flood/Zoning:** `zoning`, `road_frontage`, `road_surface_type` present. No FEMA flood zone, flood insurance, land use designation, or environmental fields.
4. **S-VL Flood/Zoning:** `buildable`, `current_use`, `current_adjacent_use`, `easements`, `vegetation` present. No FEMA flood zone, zoning classification, or environmental restriction fields.
5. **L-COM Flood/Zoning:** `zoning` and `zoning_allows` present. No FEMA flood zone, flood insurance, or environmental restriction fields.
6. **S-RES Financial:** Standard purchase financial terms are complete (budget, contingencies, financing types, deposits). Income/commercial financial metrics are not applicable.
7. **S-INC Financial:** `minimum_annual_net_income`, `minimum_cap_rate`, `expected_rent`, `number_of_units` present. Missing gross income, operating expenses, NOI, price per unit, rent roll.
8. **S-COM Financial:** `minimum_annual_net_income`, `minimum_cap_rate`, `assets` present. Missing gross income, operating expenses, NOI, price per sq ft, tenant NNN details.
9. **S-BIZ Financial:** `business_name`, `year_established`, `licenses`, `sale_includes`, `assets`, `minimum_annual_net_income`, `minimum_cap_rate`, `real_estate_purchase` present. Missing annual revenue, gross profit, SDE/EBITDA, asking multiple, employee count, reason for sale.
10. **Leasing Restrictions (Seller):** `occupant_status` and `occupant_tenant` date present. Missing existing lease expiration, current rent, lease type, and subletting/assignment restrictions.
11. **S-INC Pets:** Buyer-preference pet fields are present (`pets`, pet detail fields). A property-level pet policy field (e.g., "property allows pets: yes/no") is not rendered for the Seller/Income combination.
12. **Showing/Access (most combinations):** `occupant_status` present and `meeting_Preference` present (Seller). Missing: lockbox type, showing notice required, showing contact, access/gate code, showing hours restrictions.
13. **S-VL Showing:** Only `meeting_Preference` present. `occupant_status` is suppressed for Vacant Land. No access instructions, road access type, or GPS coordinates.
14. **Documents/Disclosures (all combinations):** `listingDocuments` (single-file upload) is present. No structured disclosure type selection, no multiple categorized document uploads, no disclosure checklist.

---

## 6. Consolidated Recommendations by Priority

### Launch Critical

These gaps should be addressed before the forms can be considered MLS-equivalent for professional listings.

| # | Field Group | Combinations Affected | Suggested Tab | New Fields |
|---|---|---|---|---|
| 1 | Parcel / Legal / Tax | All 7 | Tab 2 — Property Details | Parcel ID / folio number (text), legal description (textarea), annual taxes (number), tax year (year) |
| 2 | FEMA Flood Zone | All 7 | Tab 2 — Property Details | FEMA flood zone (select: X/AE/A/AO/VE/Unknown), flood insurance required (yes/no), flood map number (text) |
| 3 | Zoning Classification | S-RES, S-BIZ, L-RES | Tab 2 — Property Details | Zoning classification (text) — already present for S-INC, S-COM, S-VL, L-COM |
| 4 | Showing / Access | All 7 | Tab 3 or Tab 1 | Lockbox type (select), showing notice required (select), showing contact (text), access/gate code (text) |
| 5 | HOA / Association | S-RES, S-INC, L-RES | Tab 3 (Sale Terms / Lease Terms) | HOA name (text), HOA fee (number), HOA fee frequency (select), HOA approval required (yes/no), HOA minimum lease term (number, months) |
| 6 | Financial / Income (S-INC) | S-INC | Tab 2 — Property Details | Gross annual income (number), gross operating expenses (number), NOI (number), price per unit (number), rent roll upload (file) |
| 7 | Business Financials (S-BIZ) | S-BIZ | Tab 2 — Property Details | Annual revenue (number), SDE/EBITDA (number), reason for sale (text), employee count (number) |
| 8 | Existing Lease / Lease Terms (Seller) | S-RES, S-INC, S-COM, S-BIZ | Tab 3 — Sale Terms | Existing lease type (select), lease expiration (date, conditional), current monthly rent (number, conditional) |
| 9 | Showing Conditions (S-BIZ) | S-BIZ | Tab 3 — Sale Terms | NDA required before showing (yes/no), employees aware of sale (yes/no) |
| 10 | Land Access (S-VL) | S-VL | Tab 3 — Sale Terms | Road access type (select: Paved/Unpaved/Easement/None), GPS coordinates (text) |
| 11 | Disclosures — S-VL | S-VL | Tab 5 — Photos/Tours/Docs | Survey available (yes/no), soil test available (yes/no), percolation test (yes/no), environmental study (file) |

### Important

These gaps would meaningfully improve form completeness and agent confidence.

| # | Field Group | Combinations Affected | Suggested Tab | New Fields |
|---|---|---|---|---|
| 1 | CDD / Special Assessments | All 7 | Tab 2 or Tab 3 | CDD district name (text), CDD annual amount (number), special assessment outstanding balance (number) |
| 2 | HOA — Commercial | S-COM, L-COM | Tab 3 | Commercial association name (text), CAM admin fee (number) |
| 3 | Disclosures (structured) | All 7 | Tab 5 — Photos/Tours/Docs | Structured disclosure checklist: lead paint (yes/no), mold (yes/no), radon (yes/no), seller's disclosure (file), survey available (yes/no), title insurance (yes/no) |
| 4 | S-INC Existing Lease Detail | S-INC | Tab 3 — Sale Terms | Lease type per unit (select), lease expiration (date), current rent per unit (number) |
| 5 | S-COM Existing Tenant | S-COM | Tab 3 — Sale Terms | Existing tenant name (text), lease type (NNN/Gross/Modified Gross), lease assignable (yes/no) |
| 6 | L-RES HOA Leasing Rules | L-RES | Tab 3 — Lease Terms | HOA minimum lease term (number), HOA approval required (yes/no), HOA move-in fees (number) |
| 7 | L-COM Showing | L-COM | Tab 3 — Lease Terms | Showing contact (text), occupied-tenant consent (yes/no), security clearance note (textarea) |
| 8 | L-COM Phase I / Documents | L-COM | Tab 5 | Phase I ESA available (yes/no), operating statements (file), certificate of occupancy (file) |
| 9 | S-VL Deed Restrictions | S-VL | Tab 3 — Sale Terms | HOA name (text), HOA fee (number), deed restrictions (textarea) |
| 10 | S-VL Existing Rights | S-VL | Tab 3 — Sale Terms | Mineral rights (select: Included/Excluded/Severed), timber rights (select), grazing lease (yes/no) |

### Later Enhancement

Desirable for full MLS parity but not blocking launch.

| # | Field Group | Combinations Affected | Suggested Tab | New Fields |
|---|---|---|---|---|
| 1 | Tax Exemptions | S-RES, S-INC, L-RES | Tab 2 or Tab 3 | Tax exemptions in place (multi-select: Homestead/Veteran/Widow/Disability/None) |
| 2 | S-RES Disclosure Types | S-RES | Tab 5 | Radon disclosure (yes/no), radon level (text, conditional), mold history (yes/no) |
| 3 | L-RES Parcel ID | L-RES | Tab 2 | Parcel ID (text) |
| 4 | Service Animal Policy (L-COM) | L-COM | Tab 3 | Service animal accommodation policy (textarea) |
| 5 | Environmental (S-COM, L-COM) | S-COM, L-COM | Tab 2 | Environmental restrictions (textarea), wetlands present (yes/no) |

---

## 7. Appendix: Complete Wire:Model Field Inventory by Tab

This appendix lists every `wire:model` field read directly from blade source, organized by tab and combination.

### Tab 1 — Listing Details (Both Roles)

| `wire:model` | Label | Roles |
|---|---|---|
| `listing_status` | Listing Status | Both |
| `listing_title` | Listing Title | Both |
| `working_with_agent` | Representation Status | Both |
| `listing_date` | Listing Date | Both |
| `expiration_date` | Expiration Date | Both |
| `auction_type` | Listing Type | Both |
| `auction_time` | Bidding Period Length | Both |
| `meeting_Preference` | Meeting Preference | Seller only |

### Tab 2 — Property Details: Address (All 7)

| `wire:model` | Label |
|---|---|
| `address` | Street Address |
| `property_city` | City |
| `property_state` | State |
| `property_county` | County |
| `property_zip` | ZIP Code |
| `property_type` | Property Type |
| `property_items` | Property Style / Subtype |
| `other_property_items` | Other Style |

### Tab 2 — Property Details: Structural (by combination)

| `wire:model` | S-RES | S-INC | S-COM | S-BIZ | S-VL | L-RES | L-COM |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `condition_prop` / `other_property_condition` | ✓ | ✓ | ✓ | ✓ | — | ✓ | ✓ |
| `bedrooms` / `other_bedrooms` | ✓ | — | — | — | — | ✓ | — |
| `bathrooms` / `other_bathrooms` | ✓ | ✓ | ✓ | ✓ | — | ✓ | ✓ |
| `minimum_heated_square` | ✓ | ✓ | ✓ | ✓ | — | ✓ | — |
| `minimum_leaseable` | — | — | — | — | — | — | ✓ |
| `total_square_feet` | ✓ | ✓ | ✓ | ✓ | — | ✓ | ✓ |
| `sqft_heated_source` | ✓ | ✓ | ✓ | ✓ | — | ✓ | ✓ |
| `total_acreage` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `year_built` | ✓ | ✓ | ✓ | ✓ | — | ✓ | ✓ |
| `appliances` / `other_appliances` | ✓ | ✓ | ✓ | ✓ | — | ✓ | — |
| `carport_needed` / `other_carport_needed` | ✓ | — | — | — | — | ✓ | — |
| `garage_needed` / `other_garage_needed` | ✓ | — | — | — | — | ✓ | — |
| `garage_parking_spaces` / `garage_parking_spaces_option` / `other_parking_space_wrapper` | — | — | ✓ | ✓ | — | — | ✓ |
| `pool_needed` / `pool_type` | ✓ | ✓ | — | — | — | ✓ | — |
| `view_preference` / `other_preferences` | ✓ | ✓ | ✓ | ✓ | — | ✓ | — |
| `non_negotiable_amenities` / `other_non_negotiable_amenities` | ✓ | ✓ | ✓ | ✓ | — | ✓ | ✓ |
| `tenant_require` | ✓ | ✓ | — | — | — | ✓ | — |
| `leasing_55_plus` | ✓ | — | — | — | — | ✓ | — |
| `pets` / `number_of_pets` / `type_of_pets` / `breed_of_pets` / `weight_of_pets` / `breed_restrictions` | ✓ | ✓ | — | — | — | — | — |
| `business_type` / `other_business_type` | — | — | ✓ | ✓ | — | — | — |
| `business_name` | — | — | — | ✓ | — | — | — |
| `year_established` | — | — | — | ✓ | — | — | — |
| `licenses` / `other_licenses` | — | — | — | ✓ | — | — | — |
| `sale_includes` / `other_sale_includes` | — | — | — | ✓ | — | — | — |
| `assets` / `assets_other` | — | ✓ | ✓ | ✓ | — | — | — |
| `real_estate_purchase` | — | ✓ | — | ✓ | — | — | — |
| `leasing_space` | — | — | ✓ | — | — | — | — |
| `minimum_annual_net_income` | — | ✓ | ✓ | ✓ | — | — | — |
| `minimum_cap_rate` | — | ✓ | ✓ | ✓ | — | — | — |
| `zoning` | — | ✓ | ✓ | — | — | — | ✓ |
| `lot_dimensions` / `front_footage` / `number_of_wells` / `number_of_septics` / `buildable` | — | — | — | — | ✓ | — | — |
| `easements` / `fences` / `vegetation` / `current_use` / `current_adjacent_use` (+ other_ variants) | — | — | — | — | ✓ | — | — |
| `unit_number` / `unit_buildings` / `number_of_unit` / `beds_unit` / `baths_unit` / `garage_spaces` / `carport_spaces` / `number_of_units` / `number_occupied` / `expected_rent` / `unit_size` | — | ✓ | — | — | — | — | — |
| `zoning_allows` (L-COM lease terms) | — | — | — | — | — | — | ✓ |
| `total_buildings` / `total_units_on_property` / `office_retail_sqft` / `flex_space_sqft` | — | — | — | — | — | — | ✓ |
| `number_gas_meters` / `number_of_restrooms` | — | — | — | — | — | — | ✓ |

### Tab 3 — Sale Terms: Seller (Key Fields, all S-* combinations unless noted)

`sale_provision`, `sale_provision_other`, `sale_provision_assignment`, `assignment_fee_type`, `assignment_fee_amount`, `buyer_sell_contract`, `target_closing_date`, `occupant_status` (not S-VL), `occupant_tenant`, `maximum_budget`, `offered_financing`, `pre_approved`, `pre_approval_amount`, `hoa_condo_association_terms`

Financing sub-sections (conditional): `assumable_terms`, `assumable_loan_type`, `max_assumable_rate`, `max_monthly_payment`, `assumable_monthly_escrow`, `outstanding_balance`, `gap_payment_type`, `gap_payment_amount`, `assumable_loan_term_remaining`, `assumable_loan_origination_date`, `assumable_loan_servicer`, `assumable_fee_type`, `assumable_fee_amount`, `assumable_occupancy_requirement`, `assumable_occupancy_other` | `cryptocurrency_type`, `crypto_percentage`, `cash_percentage_crypto`, `crypto_exchange_method`, `crypto_custodian_wallet`, `crypto_transaction_fees`, `crypto_transfer_timing`, `crypto_transfer_timing_other` | `exchange_item`, `exchange_item_value`, `exchange_item_condition`, `additional_cash`, `value_determination`, `exchange_transfer_method`, `exchange_liens`, `exchange_liens_details`, `exchange_inspection_rights` | `lease_option_price`, `lease_option_payment`, `lease_option_duration`, `has_option_fee`, `option_fee_amount`, `lease_option_fee_credit`, `lease_option_fee_credit_percentage`, `lease_option_conditions`, `lease_option_terms`, `lease_option_maintenance`, `lease_option_extension_terms` | `lease_purchase_price`, `lease_purchase_payment`, `lease_purchase_duration`, `lease_purchase_rent_credit`, `lease_purchase_rent_credit_amount`, `lease_purchase_deposit`, `lease_purchase_conditions`, `lease_purchase_terms`, `lease_purchase_maintenance`, `lease_purchase_extension_terms` | `nft_description`, `nft_percentage`, `cash_percentage_nft`, `nft_valuation_method`, `nft_transfer_method`, `nft_gas_fees` | `purchase_price`, `down_payment_type`, `down_payment_amount`, `seller_financing_type`, `seller_down_payment_amount`, `interest_rate`, `loan_duration`, `prepayment_penalty`, `prepayment_penalty_amount`, `balloon_payment`, `balloon_payment_amount`, `balloon_payment_date`, `seller_amortization_type`, `seller_amortization_other`, `seller_payment_frequency`, `seller_payment_frequency_other`, `seller_late_fee_amount`

Sale terms questions (always shown): `initial_deposit_requested`, `initial_deposit_timeframe`, `additional_deposit_requested`, `additional_deposit_timeframe`, `escrow_agent_preference`, `preferred_inspection_period`, `appraisal_contingency_preference`, `financing_contingency_preference`, `sale_of_buyer_property_contingency`, `seller_contribution_credit_offered`, `seller_contribution_amount_details`, `possession_preference`, `possession_details`, `included_personal_property`, `excluded_items`, `home_warranty_offered`, `home_warranty_amount_details`, `hoa_condo_association_terms`, `additional_seller_sale_terms`

### Tab 3 — Lease Terms: Landlord (Key Fields by Combination)

**L-RES:** `occupant_status`, `occupant_tenant`, `leasing_spaces`, `restrictions`, `maintenance_by`, `maintenance_response_time`, `included_storage_space_res_both`, `storage_space_res_both`, `guests_allowed`, `common_areas_access`, `utilities`, `common_areas_cleaning`, `included_storage_space_res_single`, `storage_space_res_single`, `bathroom_facilities`, `room_size`, `rent_includes`, `other_rent_include`, `desired_rental_amount`, `lease_amount_frequency`, `desired_lease_length`, `lease_available_date`, `security_deposit_required`, `first_month_rent_required`, `last_month_rent_required`, `total_move_in_funds_required`, `pet_policy`, `pet_deposit_fee_rent`, `number_of_occupants_allowed`, `parking_terms`, `utility_responsibility`, `ll_maintenance_responsibility`, `renewal_option_offered`, `renewal_option_details`, `landlord_approval_conditions`, `additional_landlord_lease_terms`

**L-COM (additional):** `included_storage_space_com_entire`, `storage_space_com_entire`, `shared_amenities`, `building_hours`, `access_24_7`, `zoning_allows`, `space_features`, `neighboring_tenants`, `included_storage_space_com_single`, `storage_space_com_single`, `tenant_pays`, `other_tenant_pays`, `owner_pays`, `other_owner_pays`, `terms_of_lease`, `custom_lease_term`, `commercial_lease_type`, `cam_nnn_additional_rent_charges`, `rent_escalation_terms`, `tenant_improvement_buildout_terms`, `permitted_use_restrictions`, `signage_rights`, `commercial_parking_terms`, `personal_guarantee_requirement`, `commercial_approval_conditions`

### Tabs 4–7 (Both Roles, All 7 Combinations)

| Tab | `wire:model` fields |
|---|---|
| 4 — Additional Details | `additional_details` |
| 5 — Photos/Tours/Docs | `newPropertyPhotos`, `videoTourUrl`, `virtualTourUrl`, `listingDocuments` |
| 6 — Info | `first_name`, `last_name`, `phone_number`, `email`, `current_status` (Seller), `photo`, `video` (Landlord), `video_link` |
| 7 — AI Questions | `listing_ai_faq.{key}` (dynamic per role/type) |
| 8 — Services | `services`, `photo_enhancements`, `custom_enhancement`, `openHouseCount` |
