# Property DNA & Buyer/Tenant DNA — Phase 1 Architecture Audit

**Document version:** 1.1  
**Audit date:** May 27, 2026  
**Scope:** Offer Listing workflows — Seller, Landlord, Buyer, Tenant (commission-based / full-service path)  
**Constraint:** Every field, question, and gap cited herein was derived exclusively from reading the actual source files listed in Section 2. No hypothetical or placeholder content is included.

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Current Workflow Inventory & Source Files](#2-current-workflow-inventory--source-files)
3. [Seller Listing Audit](#3-seller-listing-audit)
4. [Landlord Listing Audit](#4-landlord-listing-audit)
5. [Buyer Criteria Listing Audit](#5-buyer-criteria-listing-audit)
6. [Tenant Criteria Listing Audit](#6-tenant-criteria-listing-audit)
7. [Recommended New Questions by Workflow](#7-recommended-new-questions-by-workflow)
8. [Recommended New Questions by Intelligence Category](#8-recommended-new-questions-by-intelligence-category)
9. [Required vs Optional vs Premium vs Future Matrix](#9-required-vs-optional-vs-premium-vs-future-matrix)
10. [Suggested Database Architecture Additions](#10-suggested-database-architecture-additions)
11. [Suggested AI Architecture Direction](#11-suggested-ai-architecture-direction)
12. [Suggested Compatibility / Scoring Direction](#12-suggested-compatibility--scoring-direction)
13. [Suggested Marketing Campaign Output Structure](#13-suggested-marketing-campaign-output-structure)
14. [Compliance and Fair Housing Guardrails](#14-compliance-and-fair-housing-guardrails)
15. [Phased Implementation Roadmap (Phases 1–7)](#15-phased-implementation-roadmap-phases-17)
16. [Final Recommendations](#16-final-recommendations)

---

## 1. Executive Summary

The Bid Your Offer platform currently collects a substantial volume of structured data across four offer listing workflows (Seller, Landlord, Buyer, Tenant). However, the platform's intelligence layer — the AI FAQ system (`config/ai_faq_*.php`) — was designed primarily as a conversational showcase tool rather than a structured intelligence feed. The two systems (structured form fields vs. AI free-text answers) currently exist in parallel silos with no integration layer connecting them.

### Key Findings

**Strengths of the current system:**
- Seller property-preferences collects extensive physical and structural data: property type, subtype, bedrooms, bathrooms, heated sq ft, total sq ft, sq ft source, acreage, year built (Residential/Income/Business), roof type, exterior construction, carport, garage (with space count), pool (with type), view, appliances, amenities, property condition, and age-restricted community designation
- The Tax/Legal/HOA tab (Seller, full-service) contains a comprehensive structured intelligence section: parcel ID, annual property taxes, legal description, flood zone designation (FEMA select), flood insurance required, FEMA panel/map number, CDD / special assessments, and a full HOA block (type, name, fee amount, fee frequency, approval process, fee inclusions, association amenities, leasing restrictions)
- Good financial data collection for Income, Commercial, and Business property types (cap rate, NOI, revenue, SDE/EBITDA, lease type, etc.)
- Solid transactional terms data (sale provisions, financing types, closing timeframe, occupancy status)
- Lease-specific data for landlord and tenant workflows (lease term, leasing spaces, maintenance, utilities, storage)
- Tenant pre-screening basics (occupants, income, pets, screening concerns), with additional screening fields commented out pending compliance review
- 27–39 AI FAQ questions per workflow covering lifestyle, condition, location, flexibility, and commercial add-ons

**Critical gaps identified:**
1. **No Property DNA score or profile exists** — there is no computed intelligence layer that synthesizes the structured fields + AI answers into a shareable "DNA profile."
2. **No Buyer/Tenant DNA profile** — buyer and tenant form data is captured but never synthesized into a scored preference profile.
3. **No compatibility scoring** — there is no mechanism to compare a buyer's preferences against a seller's listing, or a tenant's criteria against a landlord's terms.
4. **AI FAQ answers are not tagged by intelligence category** — the `ai_faq_*.php` configs hold good questions but the answers land in unstructured text with no EAV category tagging.
5. **Missing commute intelligence** — commute destination, maximum commute time, and preferred transit mode are absent from all four structured workflows. They exist only as open-ended AI FAQ questions for Buyer (faq_q9) and not at all for Tenant.
6. **Missing lifestyle signals on the demand side** — buyer and tenant archetype/purchase-purpose are not collected as structured fields. Sellers have extensive condition/physical data but buyers collect no structured fixer-upper tolerance or HOA fee ceiling.
7. **Missing investment intelligence** — for buyer workflows seeking income or commercial property, no structured cap rate target, desired occupancy rate, or hold period is collected in form fields.
8. **No marketing signal extraction** — there is no pipeline that reads the AI FAQ answers and produces listing headlines, social copy, or targeted buyer/tenant outreach copy.
9. **No target-audience fingerprint** — nothing maps listing attributes to demand-side archetypes (first-time buyer, investor, remote worker, etc.).
10. **Tenant screening data is partially commented out** — credit score rating, prior eviction, and prior felony fields exist in `pre-screening.blade.php` but are wrapped in `{{-- ... --}}` comment blocks and are not currently active.

### Intelligence Categories Used Throughout This Document

Throughout this audit, fields and questions are classified into the following 10 intelligence categories:

| # | Category | Description |
|---|----------|-------------|
| C1 | Physical Attributes | Structure, size, layout, physical features |
| C2 | Financial Intelligence | Price, income, expenses, investment metrics, financing |
| C3 | Location & Lifestyle | Neighborhood type, commute, walkability, proximity, community |
| C4 | Condition & Maintenance | Age of systems, recent repairs, known issues, warranty status |
| C5 | Legal & Compliance | Zoning, HOA, disclosures, deed restrictions, permits |
| C6 | Flexibility & Negotiation | Closing timeline, contingencies, motivation, concessions |
| C7 | Occupant / Tenant Qualification | Income, pets, screening history, occupant count |
| C8 | Marketing & Uniqueness | Hidden selling points, curb appeal, seasonal features, story |
| C9 | Compatibility & Matching | Signals that link supply to demand profiles |
| C10 | Commercial & Investment | NOI, cap rate, lease structure, tenant mix, value-add |

---

## 2. Current Workflow Inventory & Source Files

### Source Files Read for This Audit

**Livewire Components (PHP):**
- `app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php`
- `app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php`
- `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php`
- `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php`
- `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php`
- `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php`
- `app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php`
- `app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php`

**Blade Tab Partials — Seller (`offer-seller-tabs/commission-based/`):**
- `listing-details.blade.php`
- `property-preferences.blade.php`
- `seller-terms.blade.php`
- `financial-details.blade.php`
- `additional-details.blade.php`
- `documents-disclosures.blade.php`
- `tax-legal-hoa-disclosures.blade.php`
- `photos-tours-documents.blade.php`

**Blade Tab Partials — Landlord (`offer-landlord-tabs/commission-based/`):**
- `listing-details.blade.php`
- `property-preferences.blade.php`
- `lease-terms.blade.php`
- `additional-details.blade.php`

**Blade Tab Partials — Buyer (`offer-buyer-tabs/commission-based/`):**
- `listing-details.blade.php`
- `property-preferences.blade.php`
- `purchasing-terms.blade.php`
- `additional-details.blade.php`
- `buyer-info.blade.php`

**Blade Tab Partials — Tenant (`offer-tenant-tabs/commission-based/`):**
- `listing-details.blade.php`
- `property-details.blade.php`
- `leasing-terms.blade.php`
- `pre-screening.blade.php`
- `additional-details.blade.php`
- `tenant-info.blade.php`

**AI FAQ Config Files:**
- `config/ai_faq_seller.php`
- `config/ai_faq_landlord.php`
- `config/ai_faq_buyer.php`
- `config/tenant_ai_faq.php`

### Tab Structure Overview

| Workflow | Core Data Tabs | Terms Tab | Financial/Screening Tab | Documents / Disclosures |
|----------|---------------|-----------|------------------------|------------------------|
| Seller | Listing Details, Property Preferences | Seller Terms | Financial Details | Documents & Disclosures, **Tax/Legal/HOA & Disclosures**, Photos/Tours |
| Landlord | Listing Details, Property Preferences | Lease Terms | — | Documents & Disclosures, Photos/Tours |
| Buyer | Listing Details, Property Preferences | Purchasing Terms | — | — |
| Tenant | Listing Details, Property Details | Leasing Terms | Pre-Screening | — |

---

## 3. Seller Listing Audit

### 3.1 Listing Details Tab — Structured Fields

| Field (wire:model) | Label | Type | Intel Category |
|-------------------|-------|------|---------------|
| `listing_title` | Listing Title | text | C8 |
| `date_of_listing` | Listing Date | date | — |
| `expiration_date` | Expiration Date | date | — |
| `auction_type` | Auction Type (Traditional / Bidding Period) | select | C6 |
| `auction_time` | Bidding Period Length (1/3/5/7/10/14 days) | select | C6 |
| `listing_status` | Listing Status (Active/Draft) | select | — |
| `service_type` | Service Type (full_service) | hidden | — |

### 3.2 Property Preferences Tab — Structured Fields

| Field (wire:model) | Label | Type | Applies To | Intel Category |
|-------------------|-------|------|-----------|---------------|
| `address` | Street Address | text | All | C1, C3 |
| `property_city` | City (autocomplete) | autocomplete | All | C3 |
| `property_state` | State (auto-fill) | text | All | C3 |
| `property_county` | County (auto-fill) | text | All | C3 |
| `property_zip` | ZIP Code | text | All | C3 |
| `property_type` | Property Type | select (Residential / Income / Commercial / Business / Vacant Land) | All | C1 |
| `property_items` | Property Style / Subtype | select (varies by property_type) | All | C1 |
| `other_property_items` | Other Property Style | text | All | C1 |
| `business_type` | Business Type | select | Business | C10 |
| `other_business_type` | Other Business Type | text | Business | C10 |
| `condition_prop` | Property Condition | select | Non-Vacant Land | C4 |
| `bedrooms` | Bedrooms | select (1–10+) | Residential | C1 |
| `other_bedrooms` | Other Bedrooms | number | Residential | C1 |
| `bathrooms` | Bathrooms | select (1–10+, half baths) | Residential, Business, Commercial | C1 |
| `other_bathrooms` | Other Bathrooms | number | Residential, Business, Commercial | C1 |
| `minimum_heated_square` | Heated SqFt | text/number | Residential, Business, Commercial | C1 |
| `total_square_feet` | Total SqFt | text/number | Non-Vacant Land | C1 |
| `sqft_heated_source` | SqFt Heated Source | select (Appraisal / Builder / Measured / Owner Provided / Public Records) | Non-Vacant Land | C1 |
| `total_acreage` | Total Acreage | select | All | C1 |
| `appliances` | Appliances Included | multi-select | Residential, Income, Business, Commercial | C1 |
| `other_appliances` | Other Appliances | text | | C1 |
| `carport_needed` | Carport | select (Yes/No) | Residential | C1 |
| `other_carport_needed` | Number of Carport Spaces | number | Residential | C1 |
| `garage_needed` | Garage | select (Yes/No) | Residential | C1 |
| `other_garage_needed` | Number of Garage Spaces | number | Residential | C1 |
| `garage_parking_spaces_option` | Garage/Parking Features | multi-select | Business, Commercial | C1 |
| `other_parking_space_wrapper` | Other Parking Features | text | Business, Commercial | C1 |
| `pool_needed` | Pool | select (Yes/No) | Residential, Income | C1 |
| `pool_type.private` | Pool Type — Private | checkbox | Residential, Income | C1 |
| `pool_type.community` | Pool Type — Community | checkbox | Residential, Income | C1 |
| `view_preference` | View | multi-select | All | C1, C3 |
| `other_preferences` | Other View | text | All | C1 |
| `leasing_55_plus` | Age-Restricted Community | select | Residential | C5 |
| `non_negotiable_amenities` | Amenities and Property Features | multi-select | Non-Vacant Land | C1, C9 |
| `other_non_negotiable_amenities` | Other Amenities / Features | text | Non-Vacant Land | C1 |
| `year_built` | Year Built | number | Residential, Income, Commercial, Business | C1, C4 |
| `roof_type` | Roof Type | multi-select (Built-Up / Cement / Concrete / Metal / Shingle / Tile / Other…) | Residential, Income | C1, C4 |
| `other_roof_type` | Other Roof Type | text | Residential, Income | C1, C4 |
| `exterior_construction` | Exterior Construction | multi-select (Block / Brick / Concrete / Stucco / Vinyl Siding / Wood Frame…) | Residential, Income | C1, C4 |
| `other_exterior_construction` | Other Exterior Construction | text | Residential, Income | C1, C4 |
| `number_of_units` | Number of Units | number | Income | C1, C10 |
| `unit_configurations` | Unit Configurations | multi-select | Income | C1, C10 |

**Property Style subtypes observed (Residential):** ½ Duplex, Condo-Hotel, Condominium, Dock-Rackominium, Farm, Garage Condo, Manufactured Home, Mobile Home, Modular Home, Single Family Residence, Townhouse, Villa  
**Property Style subtypes observed (Income):** Duplex, Five or More, Quadplex, Triplex  
**Property Style subtypes observed (Business):** Agriculture, Assembly Building, Business, Five or More, Hotel/Motel, Industrial, Mixed Use, Office, Restaurant, Retail, Warehouse  
**Property Style subtypes observed (Vacant Land):** Agricultural, Billboard Site, Business, Cattle, Commercial, Farm, Fisher, Highway Frontage, Horses, Industrial, Land Fill, Livestock, Mixed Use, Multi Family, Nursery Orchard, Pasture, Poultry, Ranch, Residential, Retail, Row Crops, Sod Farm, Subdivision, Timber, Tracts, Trans/Cell Tower, Tree Farm, Unimproved Land, Well Field, Other

### 3.3 Seller Terms Tab — Structured Fields

| Field (wire:model) | Label | Type | Intel Category |
|-------------------|-------|------|---------------|
| `sale_provision` | Special Sale Provision | multi-select | C5, C6 |
| `sale_provision_other` | Other Sale Provision | text | C5, C6 |
| `sale_provision_assignment` | Seller Under Contract for Assignment | select (Yes/No) | C6 |
| `assignment_fee_type` | Assignment Fee Type ($/%) | select | C2, C6 |
| `assignment_fee_amount` | Assignment Fee Amount | text/number | C2 |
| `target_closing_date` | Target Closing Timeframe | select (ASAP / 1–6 months / Over 6 months / Flexible) | C6 |
| `occupant_status` | Occupant Type (Owner/Tenant/Vacant) | select | C6 |
| `occupant_tenant` | Occupied Until Date | date | C6 |
| `maximum_budget` | Desired Sale Price | text (currency) | C2 |
| `starting_price` | Starting Price / Opening Bid | text (Bidding Period) | C2 |
| `reserve_price` | Reserve Price | text (Bidding Period) | C2 |
| `buy_now_price` | Buy Now Price | text (Bidding Period) | C2 |
| `offered_financing` | Offered Financing / Currency | multi-select | C2, C6 |
| `other_financing` | Other Financing Description | text | C2 |
| *Crypto sub-fields* | Cryptocurrency specifics | text | C2 |
| *Exchange sub-fields* | Exchange / Trade specifics | text | C2 |
| *Lease Option sub-fields* | Lease Option terms | various | C2, C6 |
| *Lease Purchase sub-fields* | Lease Purchase terms | various | C2, C6 |
| *Seller Financing sub-fields* | Seller Financing terms | various | C2, C6 |

**Sale Provision options observed:** Assignment Contract, Auction, Bank Owned/REO, Government Owned, Probate Listing, Short Sale, None, Other  
**Offered Financing options observed:** Assumable, Cash, Conventional, FHA, Jumbo, VA, No-Doc, Non-QM, USDA, Cryptocurrency, Exchange/Trade, Lease Option, Lease Purchase, Non-Fungible Token (NFT), Seller Financing, Other

### 3.4 Financial Details Tab — Structured Fields

**Income / Commercial property fields:**

| Field (wire:model) | Label | Type | Intel Category |
|-------------------|-------|------|---------------|
| `minimum_annual_net_income` | Annual Net Income | text (currency) | C2, C10 |
| `minimum_cap_rate` | Cap Rate | text (%) | C2, C10 |
| `gross_annual_income` | Gross Annual Income | text (currency, Income only) | C2, C10 |
| `annual_operating_expenses` | Annual Operating Expenses | text (currency, Income only) | C2, C10 |
| `rent_roll_available` | Rent Roll Available | select (Yes/No, Income only) | C10 |
| `operating_statement_available` | Operating Statement Available | select (Yes/No, Income only) | C10 |
| `price_per_sqft` | Price Per Square Foot | text (currency, Commercial) | C2, C10 |
| `existing_lease_type` | Existing Lease Type | select (NNN, NN, Net, Gross, Modified Gross, Absolute Net, Ground Lease, etc.) | C5, C10 |
| `other_lease_type` | Other Lease Type Description | text | C5, C10 |
| `lease_expiration` | Lease Expiration Date | date (Commercial) | C10 |
| `lease_assignable` | Lease Assignable to Buyer | select (Yes/No/Negotiable) | C10 |

**Business property fields:**

| Field (wire:model) | Label | Type | Intel Category |
|-------------------|-------|------|---------------|
| `minimum_annual_net_income` | Annual Net Income | text (currency) | C2, C10 |
| `minimum_cap_rate` | Cap Rate | text (%) | C2, C10 |
| `annual_revenue` | Annual Revenue | text (currency) | C2, C10 |
| `gross_profit` | Gross Profit | text (currency) | C2, C10 |
| `sde_ebitda` | SDE / EBITDA | text (currency) | C2, C10 |
| `inventory_value` | Inventory Value | text (currency) | C2, C10 |
| `ffe_value` | FF&E Value | text (currency) | C2, C10 |
| `reason_for_sale` | Reason for Sale | select (Retirement / Relocation / Health / Other Opportunities / Partnership Dissolution / Financial / Other) | C6, C8 |
| `other_reason_for_sale` | Other Reason for Sale | text | C6 |
| `employee_count` | Number of Employees | number | C10 |
| `financial_statements_available` | Financial Statements Available | select (Yes/No) | C10 |
| `tax_returns_available` | Tax Returns Available | select (Yes/No) | C10 |
| `nda_required` | NDA Required to Access Financials | select (Yes/No) | C10 |
| `business_location_leased` | Is Business Location Leased? | select (Yes/No/Not Applicable) | C5, C10 |
| `business_lease_monthly_rent` | Current Monthly Rent | text (currency) | C2, C10 |
| `business_lease_expiration` | Lease Expiration Date | date | C10 |
| `business_lease_renewal_options` | Renewal Options Available | select (Yes/No/Unknown) | C10 |
| `business_lease_assignable` | Lease Assignable to Buyer | select (Yes/No/Subject to Landlord Approval/Unknown) | C10 |
| `business_lease_additional_terms` | Additional Lease Terms | text | C10 |

### 3.5 Additional Details Tab

| Field (wire:model) | Label | Type | Intel Category |
|-------------------|-------|------|---------------|
| `additional_details` | Property Description | textarea | C8 |

### 3.6 Documents & Disclosures Tab

| Field | Label | Type | Intel Category |
|-------|-------|------|---------------|
| `doc_rows` (repeatable) | Document Type | select | C5 |
| `doc_rows[].custom_type` | Custom Document Name | text | C5 |
| `doc_rows[].description` | Document Description | textarea | C5 |
| `doc_rows[].file_path` | Uploaded File | file upload | C5 |

**Document types observed:** Appraisal Report, As-Built Plans / Floor Plans, Certificate of Occupancy, Drainage / Stormwater Report, Elevation Certificate, Energy Audit Report, Environmental Report, Flood Disclosure, Geotechnical / Soil Report, Hazardous Materials Report, Historic Designation Documents, HOA/Condo Documents, Inspection Report, Lead-Based Paint Disclosure, Lease Agreements (Existing Tenants), Maintenance Records, Permits & Permit History, Roof Certification, Seller Disclosure, Septic Inspection Report, Survey, Title Insurance Commitment, Utility Bills / History, Warranty Documents, Well Water Test Report, Other

### 3.7 Tax, Legal & HOA Tab — Structured Fields

This tab (`tax-legal-hoa-disclosures.blade.php`) is the most data-rich structured intelligence source in the entire platform. It is displayed for Full Service Seller listings only.

**Group 1: Tax / Legal / Parcel Information**

| Field (wire:model) | Label | Type | Intel Category |
|-------------------|-------|------|---------------|
| `parcel_id` | Parcel ID / Folio Number | text | C5 |
| `tax_year` | Tax Year | text (year) | C2 |
| `annual_property_taxes` | Annual Property Taxes | text (currency) | C2 |
| `additional_parcels` | Additional Parcels Included in Sale | select (Yes/No/Unknown) | C5 |
| `total_parcel_count` | Total Number of Parcels | number | C5 |
| `additional_parcel_ids` | Additional Parcel IDs | textarea | C5 |
| `legal_description` | Legal Description | textarea | C5 |

**Group 2: Flood Zone**

| Field (wire:model) | Label | Type | Intel Category |
|-------------------|-------|------|---------------|
| `flood_zone_code` | Flood Zone Designation | select (X / AE / A / AH / AO / VE / V / D / Unknown / Other) | C5 |
| `flood_zone_code_other` | Other Flood Zone Code | text | C5 |
| `flood_insurance_required` | Flood Insurance Required | select (Yes/No/Unknown) | C5 |
| `flood_zone_panel` | FEMA Flood Zone Panel / Map Number | text | C5 |

**Group 3: CDD / Special Assessments**

| Field (wire:model) | Label | Type | Intel Category |
|-------------------|-------|------|---------------|
| `has_cdd` | Community Development District (CDD) | select (Yes/No/Unknown) | C5 |
| `annual_cdd_fee` | Annual CDD Fee | text (currency) | C2, C5 |
| `has_special_assessments` | Special Assessments | select (Yes/No/Unknown) | C5 |
| `special_assessment_amount` | Special Assessment Amount | text (currency) | C2, C5 |
| `special_assessment_description` | Special Assessment Description | textarea | C5 |

**Group 4: HOA / Association**

| Field (wire:model) | Label | Type | Intel Category |
|-------------------|-------|------|---------------|
| `has_hoa` | HOA / Community Association Present | select (Yes/No/Unknown) | C5 |
| `association_type` | Association Type | select (HOA / Condo Assoc. / POA / Co-op / Community Assoc. / Master Assoc. / Commercial Assoc. / Other) | C5 |
| `association_type_other` | Other Association Type | text | C5 |
| `association_name` | Association Name | text | C5 |
| `association_fee_amount` | Association Fee Amount | text (currency) | C2, C5 |
| `association_fee_frequency` | Fee Frequency | select (Monthly / Bi-Monthly / Quarterly / Semi-Annually / Annually / One-Time / Other) | C2, C5 |
| `association_fee_frequency_other` | Other Fee Frequency | text | C2, C5 |
| `association_approval_required` | Association Approval Required for Purchase | select (Yes/No/Unknown) | C5 |
| `association_approval_process` | Approval Process Details | textarea | C5 |
| `association_application_fee` | Association Application Fee | text (currency) | C2, C5 |
| `association_fee_includes` | What Does the Association Fee Include? | multi-select (Cable TV / Common Area Maintenance / Community Pool / Exterior Maintenance / Flood Insurance / Gas / Grounds Maintenance / Insurance / Internet / Pest Control / Private Road Maintenance / Recreational Facilities / Roof Maintenance / Security / Sewer / Trash / Water / Other) | C2, C5 |
| `association_amenities` | Association Amenities | multi-select (Basketball Court / Boat Slip-Marina / Clubhouse / Dog Park / Fitness Center / Gated Entry / Golf Course / Jogging Trail / Pickleball Court / Playground / Pool / Recreation Center / Sauna-Spa / Tennis Court / Waterfront Access / Other) | C1, C5 |
| `association_amenities_other` | Other Association Amenities | text | C5 |
| `leasing_restrictions` | Leasing / Rental Restrictions | select (Yes / No / Not Applicable / Unknown) | C5 |
| `min_lease_period` | Minimum Lease Period | select (1 Week through 2 Years / Other) | C5 |
| `max_leases_per_year` | Max Leases Per Year | number | C5 |
| `additional_lease_restrictions` | Additional Leasing Restrictions | textarea | C5 |

### 3.8 AI FAQ Questions — Seller

**Base Group 1: Property Condition & Maintenance (10 questions)**

| Key | Label / Topic | Intel Category |
|-----|--------------|---------------|
| `faq_q1` | Roof age and condition | C4 |
| `faq_q2` | HVAC system age and service history | C4 |
| `faq_q3` | Water heater age | C4 |
| `faq_q4` | Plumbing and electrical system status | C4 |
| `faq_q5` | Foundation issues or past repairs | C4 |
| `faq_q6` | Major renovations or upgrades completed | C4 |
| `faq_q7` | Pest or termite history | C4 |
| `faq_q8` | HOA rules, fees, or restrictions | C5 |
| `faq_q9` | Septic system or sewer details | C4, C5 |
| `faq_q10` | Any known defects or disclosure items | C4, C5 |

**Base Group 2: Financial & Utility Insights (3 questions)**

| Key | Label / Topic | Intel Category |
|-----|--------------|---------------|
| `faq_q11` | Average monthly utility costs | C2 |
| `faq_q12` | HOA fee amount and what it covers | C2, C5 |
| `faq_q13` | Annual property tax amount | C2 |

> **Note:** faq_q11 (utility costs) and faq_q13 (property tax) overlap with structured fields `annual_property_taxes` (Tax/Legal/HOA tab) and `association_fee_amount` (HOA section). The AI FAQ versions capture richer narrative context (e.g., seasonal variation, what's included in utilities). The structured fields provide the machine-readable values. Both are valuable; the duplication is intentional from the user's perspective but neither is currently connected to the other in the data model.

**Base Group 3: Location & Lifestyle (7 questions)**

| Key | Label / Topic | Intel Category |
|-----|--------------|---------------|
| `faq_q14` | Neighborhood feel and character | C3 |
| `faq_q15` | Walkability and nearby shops / restaurants | C3 |
| `faq_q16` | School district quality | C3 |
| `faq_q17` | Nearby dining and entertainment | C3 |
| `faq_q18` | Commute and transportation options | C3 |
| `faq_q19` | Noise level and traffic | C3 |
| `faq_q20` | Community events or HOA activities | C3 |

**Base Group 4: Flexibility & Negotiation (6 questions)**

| Key | Label / Topic | Intel Category |
|-----|--------------|---------------|
| `faq_q21` | Seller's primary motivation for selling | C6 |
| `faq_q22` | Closing date flexibility | C6 |
| `faq_q23` | Items included or excluded from sale | C6 |
| `faq_q24` | Contingencies seller will accept | C6 |
| `faq_q25` | Back-up offer policy | C6 |
| `faq_q26` | Price flexibility or negotiating room | C6 |

**Base Group 5: Hidden Selling Points (7 questions)**

| Key | Label / Topic | Intel Category |
|-----|--------------|---------------|
| `faq_q27` | Best feature of the property | C8 |
| `faq_q28` | Recent upgrades that add value | C8 |
| `faq_q29` | Seasonal highlights | C8 |
| `faq_q30` | Technology / smart home features | C8 |
| `faq_q31` | Outdoor living highlights | C8 |
| `faq_q32` | Neighbor quality or community feel | C8, C3 |
| `faq_q33` | What the seller will miss most | C8 |

**Add-on Group: Commercial Income (6 questions, Income/Commercial property types)**

| Key | Label / Topic | Intel Category |
|-----|--------------|---------------|
| `faq_ci_q1` | Current occupancy rate | C10 |
| `faq_ci_q2` | Anchor or notable tenants | C10 |
| `faq_ci_q3` | NOI trend over past 3 years | C10 |
| `faq_ci_q4` | Operating hours / business activity | C10 |
| `faq_ci_q5` | Current zoning and permitted uses | C5, C10 |
| `faq_ci_q6` | Redevelopment or value-add potential | C10 |

**Add-on Group: Business Opportunity (7 questions, Business property type)**

| Key | Label / Topic | Intel Category |
|-----|--------------|---------------|
| `faq_bo_q1` | Years in business | C10 |
| `faq_bo_q2` | Reason for selling the business | C6, C10 |
| `faq_bo_q3` | Customer / client base characteristics | C10 |
| `faq_bo_q4` | Staff transition plan | C10 |
| `faq_bo_q5` | Franchised or independent | C10 |
| `faq_bo_q6` | Competitive landscape | C10 |
| `faq_bo_q7` | Growth opportunities for new owner | C10 |

**Add-on Group: Vacant Land (6 questions, Vacant Land property type)**

| Key | Label / Topic | Intel Category |
|-----|--------------|---------------|
| `faq_vl_q1` | Utilities available at property | C4, C5 |
| `faq_vl_q2` | Road access and easements | C5 |
| `faq_vl_q3` | Current zoning and use restrictions | C5 |
| `faq_vl_q4` | Topography and soil characteristics | C1, C4 |
| `faq_vl_q5` | Environmental concerns or restrictions | C5 |
| `faq_vl_q6` | Seller's timeline for development | C6 |

### 3.9 Seller Gaps by Intelligence Category

| Category | Present (Structured) | Missing / Underserved |
|----------|---------------------|----------------------|
| C1 Physical | Type, subtype, condition, beds, baths, heated sqft, total sqft, sqft source, acreage (select), garage Y/N + spaces, carport Y/N + spaces, pool Y/N + type, view, appliances, amenities, roof type, exterior construction, year built (Residential/Income/Business), unit count + configs (Income), parking features (Business/Commercial), age-restricted community | Year built for Vacant Land; lot dimensions / lot size in sq ft (only acreage select exists); number of stories; waterfront linear footage |
| C2 Financial | Sale price, income metrics, cap rate, revenue, SDE/EBITDA, financing types, annual property taxes, association fee + frequency + inclusions, CDD fee, special assessment amount | Average monthly utility cost (structured field — currently AI FAQ only, faq_q11) |
| C3 Location | City, county, state, ZIP | School district name (structured); Walk Score; distance to transit stop (structured) |
| C4 Condition | Property condition (select), year built, roof type, exterior construction, AI FAQ groups (roof age, HVAC, water heater, foundation, renovations, pests, plumbing) | Roof year / age (structured — currently AI FAQ only faq_q1); HVAC year (structured — currently AI FAQ only faq_q2); water heater year (structured — currently AI FAQ only faq_q3) |
| C5 Legal | Flood zone designation (full FEMA select), flood insurance required, FEMA panel number, has_hoa + full HOA block (type / name / fee / frequency / approval / inclusions / amenities / leasing restrictions), CDD, special assessments, parcel ID, legal description, sale provisions, document uploads, age-restricted community | Deed restrictions Y/N (structured); known litigation Y/N (structured) |
| C6 Flexibility | Sale provision, closing timeframe, occupancy status, occupancy date, financing types, reason for sale (Business) | Seller motivation category (structured, agent-visible only); inspection contingency acceptance (structured); appraisal contingency acceptance (structured); leaseback required (structured) |
| C7 Occupant | Occupant status (Owner/Tenant/Vacant) | — (Not applicable for supply side) |
| C8 Marketing | Property description (textarea), AI FAQ hidden selling points group (q27–q33), reason for sale (Business) | No structured headline generator; no style-tag extractor |
| C9 Compatibility | Non-negotiable amenities multi-select | No explicit buyer-match signal fields; no deal-breaker flags; no structured contingency match signals |
| C10 Commercial | Cap rate, NOI, gross income, operating expenses, lease type, SDE/EBITDA, inventory, FF&E, employees, NDA, rent roll, operating statement, occupancy rate (AI FAQ faq_ci_q1) | Occupancy rate (structured for Income — currently AI FAQ only faq_ci_q1); anchor tenant count (structured) |

---

## 4. Landlord Listing Audit

### 4.1 Listing Details Tab — Structured Fields

Same core fields as Seller: `listing_title`, `date_of_listing`, `expiration_date`, `auction_type` (Traditional/Bidding Period), `auction_time`, `listing_status`, `service_type`.

### 4.2 Property Preferences Tab — Structured Fields

| Field (wire:model) | Label | Type | Intel Category |
|-------------------|-------|------|---------------|
| `address` | Street Address | text | C1, C3 |
| `property_city` | City (autocomplete) | autocomplete | C3 |
| `property_state` | State (auto-fill) | text | C3 |
| `property_county` | County (auto-fill) | text | C3 |
| `property_zip` | ZIP Code | text | C3 |
| `cities` (badges) | Acceptable Cities | multi-entry | C3 |
| `counties` (badges) | Acceptable Counties | multi-entry | C3 |
| `property_type` | Property Type | select (Residential Property / Commercial Property) | C1 |
| `property_items` | Property Style / Subtype | multi-select (varies by type) | C1 |
| `bedrooms` | Minimum Bedrooms | select | C1 |
| `bathrooms` | Minimum Bathrooms | select | C1 |
| `minimum_sqft` | Minimum Square Footage | text/number | C1 |
| `acreage` | Acreage | text/number | C1 |
| `garage` | Garage | Yes/No | C1 |
| `pool` | Pool | Yes/No | C1 |
| `view_preferences` | View Preferences | multi-select | C1, C3 |
| `appliances` | Appliances Included | multi-select | C1 |
| `non_negotiable_amenities` | Non-Negotiable Amenities | multi-select | C1, C9 |

### 4.3 Lease Terms Tab — Structured Fields

| Field (wire:model) | Label | Type | Intel Category |
|-------------------|-------|------|---------------|
| `occupant_status` | Occupant Type (Owner/Tenant/Vacant) | select | C6 |
| `occupant_tenant` | Occupied Until Date | date | C6 |
| `leasing_spaces` | Leasing Space | select (Entire Property / Single Room / ADU / Single Office/Suite) | C1 |
| `restrictions` | Restrictions Include | text | C5, C7 |
| `maintenance_by` | Maintenance Handled By | select (Landlord/Property Manager/Real Estate Agent/Tenant) | C4 |
| `maintenance_response_time` | Maintenance Response Time | text | C4 |
| `included_storage_space_res_both` | Included Storage Space (Entire Property / ADU) | select (Yes/No) | C1 |
| `storage_space_res_both` | Storage Space Size | text | C1 |
| `guests_allowed` | Guests Are (Single Room) | select (Allowed/Not Allowed) | C5 |
| `common_areas_access` | Shared Areas Available (Single Room) | text | C1 |
| `common_areas_cleaning` | Common Area Maintenance | text | C4 |
| `included_storage_space_res_single` | Included Storage Space (Single Room) | select (Yes/No) | C1 |
| `storage_space_res_single` | Storage Space Size (Single Room) | text | C1 |
| `bathroom_facilities` | Bathroom Facilities (Single Room) | select (Private/Shared) | C1 |
| `room_size` | Approximate Room Size | text | C1 |
| `utilities` | Utilities | select (Included in Rent / Split Among Tenants / Individually Metered) | C2 |
| `tenant_pays` | Tenant Pays (Commercial) | multi-select | C2, C10 |
| `other_tenant_pays` | Other Tenant Pays | text | C2, C10 |
| `owner_pays` | Owner Pays (Commercial) | multi-select | C2, C10 |
| `other_owner_pays` | Other Owner Pays | text | C2, C10 |
| `terms_of_lease` | Terms of Lease (Commercial) | multi-select (NNN / NN / Net / Gross / Modified Gross / Ground Lease / Lease Option / etc.) | C5, C10 |
| `custom_lease_term` | Custom Lease Term | text | C10 |
| `desired_lease_length` | Desired Lease Length | multi-select (Residential: 3/6/9 months, 1/2 years, Month-to-Month; Commercial: 6 months–6+ years) | C10 |
| `desired_lease_price` | Desired Monthly Lease Price | text (currency) | C2 |
| `starting_price` | Starting Lease Price / Opening Bid | text (Bidding Period) | C2 |
| `reserve_price` | Reserve Lease Price | text (Bidding Period) | C2 |
| `buy_now_price` | Buy Now (Commit) Price | text (Bidding Period) | C2 |

**Lease type options observed (Commercial):** Absolute (Triple) Net, Gross Lease, Gross Percentages, Ground Lease, Lease Option, Modified Gross, Net Lease, Net Net, Pass Throughs, Purchase Option, Renewal Option, Sale-Leaseback, Seasonal, Special Available (CLO), Varied Terms, Other

### 4.4 Additional Details Tab

| Field (wire:model) | Label | Type | Intel Category |
|-------------------|-------|------|---------------|
| `additional_details` | Property Description | textarea | C8 |

### 4.5 AI FAQ Questions — Landlord

**Base Group 1: Maintenance & Property Condition (8 questions)**

| Key | Label / Topic | Intel Category |
|-----|--------------|---------------|
| `faq_q1` | AC / HVAC age and service history | C4 |
| `faq_q2` | Recent renovations or upgrades | C4 |
| `faq_q3` | Pest control frequency and history | C4 |
| `faq_q4` | Age of major appliances included | C4 |
| `faq_q5` | Plumbing and electrical status | C4 |
| `faq_q6` | Landscaping and exterior maintenance | C4 |
| `faq_q7` | Any known issues or required disclosures | C4, C5 |
| `faq_q8` | History of maintenance responsiveness | C4 |

**Base Group 2: Location & Neighborhood (5 questions)**

| Key | Label / Topic | Intel Category |
|-----|--------------|---------------|
| `faq_q9` | Neighborhood safety and character | C3 |
| `faq_q10` | Proximity to public transit | C3 |
| `faq_q11` | Nearby shopping, grocery, dining | C3 |
| `faq_q12` | School district quality | C3 |
| `faq_q13` | Noise level (traffic, neighbors, airport) | C3 |

**Base Group 3: Lifestyle & Flexibility (8 questions)**

| Key | Label / Topic | Intel Category |
|-----|--------------|---------------|
| `faq_q14` | Pet policy details | C7 |
| `faq_q15` | Smoking policy | C5 |
| `faq_q16` | Guest policy and overnight stay rules | C5 |
| `faq_q17` | Parking situation (spaces, assigned, etc.) | C1 |
| `faq_q18` | Tenant screening criteria (income, credit) | C7 |
| `faq_q19` | Lease renewal likelihood / policy | C6 |
| `faq_q20` | Subletting policy | C5 |
| `faq_q21` | Move-in specials or incentives offered | C6, C8 |

**Base Group 4: High-Intent Tenant Questions (6 questions)**

| Key | Label / Topic | Intel Category |
|-----|--------------|---------------|
| `faq_q22` | Showing availability and scheduling | — |
| `faq_q23` | Application process and steps | C7 |
| `faq_q24` | Required documents to apply | C7 |
| `faq_q25` | Security deposit amount and structure | C2 |
| `faq_q26` | Utilities included in rent | C2 |
| `faq_q27` | When unit will be available | C6 |

**Add-on Group: Commercial (12 questions)**

| Key | Label / Topic | Intel Category |
|-----|--------------|---------------|
| `faq_c_q1` | Zoning classification and permitted uses | C5, C10 |
| `faq_c_q2` | Tenant improvement / build-out allowance | C2, C10 |
| `faq_c_q3` | Signage rights and restrictions | C5, C10 |
| `faq_c_q4` | Neighboring / anchor tenants | C10 |
| `faq_c_q5` | Parking spaces and ratio | C1 |
| `faq_c_q6` | Building access hours | C10 |
| `faq_c_q7` | Loading dock or freight access | C1, C10 |
| `faq_c_q8` | HVAC zone control | C1, C4 |
| `faq_c_q9` | Internet / fiber availability and speed | C1 |
| `faq_c_q10` | Lease structure type preference | C10 |
| `faq_c_q11` | Common area maintenance (CAM) charges | C2, C10 |
| `faq_c_q12` | Lease renewal options | C10 |

### 4.6 Landlord Gaps by Intelligence Category

| Category | Present (Structured) | Missing / Underserved |
|----------|---------------------|----------------------|
| C1 Physical | Type, subtype, beds, baths, sqft, acreage, garage, pool, views, appliances, amenities, leasing spaces, room size, storage, bathroom type | Year built (no structured field on landlord form); parking space count (structured — currently AI FAQ only faq_c_q5); floor number / total floors; ADA accessible Y/N |
| C2 Financial | Desired monthly rent, bidding period pricing, utilities inclusion, tenant/owner pays (commercial) | Security deposit amount (structured field — currently AI FAQ only faq_q25); application/admin fee (structured); HOA fee passthrough (structured) |
| C3 Location | City, county, state, ZIP, city/county multi-select | School district name (structured); transit stop distance (structured); noise zoning (structured) |
| C4 Condition | Maintenance handler, response time, AI FAQ group (HVAC, renovations, appliances, plumbing, pests) | Year built (structured — entirely absent on landlord form); HVAC year (structured — currently AI FAQ only faq_q1); appliance ages (structured — currently AI FAQ only faq_q4) |
| C5 Legal | Lease type (Commercial), restrictions text, AI FAQ (pet policy, smoking, guest policy, subletting) | Pet policy structured (Y/N + fee + weight limit — currently AI FAQ only faq_q14); smoking policy (Y/N structured — currently AI FAQ only faq_q15); subletting policy (Y/N structured — currently AI FAQ only faq_q20); no Tax/Legal/HOA tab for Landlord |
| C6 Flexibility | Occupancy status, occupied-until date, desired lease length | Available date (structured — currently AI FAQ only faq_q27); move-in special type (structured) |
| C7 Occupant | Tenant screening via restrictions text | Minimum income requirement (structured, agent-visible); credit score minimum (structured, agent-visible) |
| C8 Marketing | Property description textarea | No structured "best feature" flag |
| C9 Compatibility | Non-negotiable amenities | No structured match signals between landlord listing and tenant criteria |
| C10 Commercial | Lease type, tenant/owner pays, AI FAQ commercial group | Available square footage (structured, separate from minimum preference); occupancy rate (structured); TI allowance (structured — currently AI FAQ only faq_c_q2) |

---

## 5. Buyer Criteria Listing Audit

### 5.1 Listing Details Tab — Structured Fields

Same core fields as other workflows: `listing_title`, `date_of_listing`, `expiration_date`, `auction_type`, `auction_time`, `listing_status`, `service_type`.

### 5.2 Property Preferences Tab — Structured Fields

| Field (wire:model) | Label | Type | Intel Category |
|-------------------|-------|------|---------------|
| `cities` (badges) | Acceptable Cities | multi-entry (autocomplete) | C3 |
| `counties` (badges) | Acceptable Counties (required) | multi-entry (autocomplete) | C3 |
| `state` | Acceptable State (required) | autocomplete text | C3 |
| `property_type` | Acceptable Property Type (required) | select (Residential / Income / Commercial / Business / Vacant Land) | C1 |
| `property_items` | Acceptable Property Style (required) | multi-select (varies by type) | C1 |
| `business_type` | Business Type (Business only) | multi-select | C10 |
| `other_business_type` | Other Business Type | text | C10 |
| `condition_prop_buyer_json` | Property Condition Acceptable | hidden (JSON) | C4, C9 |
| `bedrooms` | Minimum Bedrooms | select | C1 |
| `bathrooms` | Minimum Bathrooms | select | C1 |
| `minimum_sqft` | Minimum Square Footage | text/number | C1 |
| `acreage` | Acreage | text/number | C1 |
| `garage` | Garage | Yes/No/Either | C1 |
| `pool` | Pool | Yes/No/Either | C1 |
| `view_preferences` | View Preferences | multi-select | C1, C3 |
| `appliances` | Appliances Required | multi-select | C1 |
| `non_negotiable_amenities` | Non-Negotiable Amenities | multi-select | C1, C9 |
| `number_of_units` | Number of Units (Income) | number | C1, C10 |
| `number_of_unit_type_json` | Unit Type Configuration (Income) | hidden (JSON) | C1, C10 |

### 5.3 Purchasing Terms Tab — Structured Fields

| Field (wire:model) | Label | Type | Intel Category |
|-------------------|-------|------|---------------|
| `sale_provision` | Acceptable Special Sale Provisions | multi-select (same options as Seller) | C5, C6 |
| `sale_provision_other` | Other Sale Provision | text | C5, C6 |
| `sale_provision_assignment` | Buyer Open to Purchasing Assignment Contract | select (Yes/No) | C6 |
| `assignment_fee_type` | Assignment Fee Type ($/%) | select | C2 |
| `assignment_fee_amount` | Assignment Fee Amount | text/number | C2 |
| `target_closing_date` | Target Closing Timeframe (required) | select (ASAP / 1–6 months / Over 6 months / Flexible) | C6 |
| `maximum_budget` | Maximum Budget (required) | text (currency) | C2 |
| `offered_financing` | Offered Financing / Currency (required) | multi-select (same options as Seller) | C2, C6 |
| `other_financing` | Other Financing | text | C2 |
| *Crypto sub-fields* | Cryptocurrency specifics | text | C2 |
| *Exchange sub-fields* | Exchange / Trade specifics | text | C2 |
| *Lease Option sub-fields* | Lease Option specifics | text | C2, C6 |
| *Lease Purchase sub-fields* | Lease Purchase specifics | text | C2, C6 |
| *Pre-approval sub-fields* | Mortgage pre-approval status/amount | various | C2 |
| *Down payment sub-fields* | Down payment amount and source | various | C2 |
| *Seller concessions sub-fields* | Concessions requested | various | C2, C6 |

### 5.4 Additional Details Tab

| Field (wire:model) | Label | Type | Intel Category |
|-------------------|-------|------|---------------|
| `additional_details` | Buyer Notes / Description | textarea | C8 |

### 5.5 AI FAQ Questions — Buyer

**Base Group 1: Buyer Intent & Lifestyle (8 questions)**

| Key | Label / Topic | Intel Category |
|-----|--------------|---------------|
| `faq_q1` | Primary use / purpose for purchase | C9 |
| `faq_q2` | Household / family size | C1, C9 |
| `faq_q3` | Work-from-home needs | C1, C3, C9 |
| `faq_q4` | Lifestyle priorities (outdoor, urban, quiet, etc.) | C3, C9 |
| `faq_q5` | Deal-breakers (what they absolutely cannot accept) | C9 |
| `faq_q6` | School district importance | C3 |
| `faq_q7` | Pet ownership and needs | C1, C9 |
| `faq_q8` | Outdoor / yard space importance | C1, C9 |

**Base Group 2: Location & Community (6 questions)**

| Key | Label / Topic | Intel Category |
|-----|--------------|---------------|
| `faq_q9` | Commute destination and acceptable time | C3, C9 |
| `faq_q10` | Preferred neighborhood vibe | C3, C9 |
| `faq_q11` | Walkability importance | C3, C9 |
| `faq_q12` | Proximity to amenities needed | C3 |
| `faq_q13` | HOA acceptance level | C5, C9 |
| `faq_q14` | Flood zone concern / tolerance | C5, C9 |

**Base Group 3: Property Preferences (7 questions)**

| Key | Label / Topic | Intel Category |
|-----|--------------|---------------|
| `faq_q15` | Architectural style preference | C1, C9 |
| `faq_q16` | New construction vs existing preference | C4, C9 |
| `faq_q17` | Tolerance for fixer-upper / renovation | C4, C9 |
| `faq_q18` | Smart home / tech feature interest | C1, C9 |
| `faq_q19` | Outdoor features priority | C1 |
| `faq_q20` | Garage importance | C1 |
| `faq_q21` | Storage / closet needs | C1 |

**Base Group 4: Buyer Situation & Flexibility (7 questions)**

| Key | Label / Topic | Intel Category |
|-----|--------------|---------------|
| `faq_q22` | First-time buyer status | C6, C9 |
| `faq_q23` | Pre-approval and financial readiness | C2, C6 |
| `faq_q24` | Current home situation (own, rent, moving) | C6 |
| `faq_q25` | Timeline flexibility | C6 |
| `faq_q26` | Contingency plans (inspection, appraisal) | C6 |
| `faq_q27` | Relocation motivation | C3, C9 |
| `faq_q28` | Investment horizon (long-term vs. flip) | C9, C10 |

**Add-on Group: Commercial Income (7 questions, Income/Commercial property)**

| Key | Label / Topic | Intel Category |
|-----|--------------|---------------|
| `faq_ci_q1` | Intended use of investment property | C10 |
| `faq_ci_q2` | Investment goals (cash flow, appreciation, both) | C10 |
| `faq_ci_q3` | Current property management experience | C10 |
| `faq_ci_q4` | Desired occupancy rate | C10 |
| `faq_ci_q5` | Target cap rate | C10 |
| `faq_ci_q6` | Preferred financing type for investment | C2, C10 |
| `faq_ci_q7` | Desired hold period | C10 |

**Add-on Group: Business Opportunity (6 questions, Business property)**

| Key | Label / Topic | Intel Category |
|-----|--------------|---------------|
| `faq_bo_q1` | Industry experience | C10 |
| `faq_bo_q2` | Owner-operated or absentee managed preference | C10 |
| `faq_bo_q3` | Growth plans for business | C10 |
| `faq_bo_q4` | Importance of staff retention | C10 |
| `faq_bo_q5` | Need for seller transition / training support | C10 |
| `faq_bo_q6` | Franchise interest | C10 |

**Add-on Group: Vacant Land (5+ questions, Vacant Land property)**

| Key | Label / Topic | Intel Category |
|-----|--------------|---------------|
| `faq_vl_q1` | Intended development purpose | C10 |
| `faq_vl_q2` | Development timeline | C10 |
| `faq_vl_q3` | Permits or entitlements already obtained | C5 |
| `faq_vl_q4` | Utilities needed on site | C4, C5 |
| `faq_vl_q5` | Topography / terrain preference | C1 |

### 5.6 Buyer Gaps by Intelligence Category

| Category | Present (Structured) | Missing / Underserved |
|----------|---------------------|----------------------|
| C1 Physical | Type, subtype, beds, baths, sqft, acreage, garage (Y/N/Either), pool (Y/N/Either), views, appliances, non-negotiable amenities, unit count + type (Income), condition JSON | Maximum square footage (ceiling preference); preferred stories (one-story); basement requirement |
| C2 Financial | Maximum budget, financing type, pre-approval sub-fields, down payment sub-fields, concessions | Minimum acceptable cap rate (structured field — currently AI FAQ only faq_ci_q5 for Income/Commercial); maximum allowable HOA fee (structured) |
| C3 Location | Acceptable cities, counties, state | Commute destination address or ZIP (structured); maximum commute time (structured); transit mode preference (structured); school district name preference (structured) |
| C4 Condition | Condition acceptance JSON (hidden) | Fixer-upper tolerance (structured select — currently AI FAQ only faq_q17); minimum year-built acceptance (structured) |
| C5 Legal | Acceptable sale provisions (multi-select) | HOA acceptance structured (Y/N — currently AI FAQ only faq_q13); maximum HOA fee tolerance (structured); flood zone tolerance (structured — currently AI FAQ only faq_q14) |
| C6 Flexibility | Target closing timeframe, acceptable sale provisions, assignment contract willingness | Inspection contingency required Y/N (structured); appraisal contingency required Y/N (structured); sale of current home contingency Y/N (structured) |
| C7 Occupant | — | — (Not applicable for buy-side) |
| C8 Marketing | Additional details textarea | — |
| C9 Compatibility | Non-negotiable amenities, condition JSON, AI FAQ deal-breakers, lifestyle questions | No structured buyer archetype / purchase-purpose tag; no structured "must-have vs nice-to-have" distinction |
| C10 Commercial | Business type, unit count, AI FAQ commercial add-ons | Target cap rate floor (structured — currently AI FAQ only faq_ci_q5); target occupancy rate (structured — currently AI FAQ only faq_ci_q4); desired hold period (structured) |

---

## 6. Tenant Criteria Listing Audit

### 6.1 Listing Details Tab — Structured Fields

Same core fields as other workflows: `listing_title`, `date_of_listing`, `expiration_date`, `auction_type`, `auction_time`, `listing_status`, `service_type`.

### 6.2 Property Details Tab — Structured Fields

| Field (wire:model) | Label | Type | Intel Category |
|-------------------|-------|------|---------------|
| `cities` (badges) | Acceptable Cities | multi-entry (autocomplete) | C3 |
| `counties` (badges) | Acceptable Counties (required) | multi-entry (autocomplete) | C3 |
| `state` | Acceptable State (required) | autocomplete text | C3 |
| `property_type` | Property Type | select (Residential Property / Commercial Property) | C1 |
| `property_items` | Property Style / Subtype | multi-select (varies by type) | C1 |
| `bedrooms` | Minimum Bedrooms | select (Residential) | C1 |
| `bathrooms` | Minimum Bathrooms | select (Residential) | C1 |
| `minimum_sqft` | Minimum Square Footage | text/number | C1 |
| `acreage` | Acreage | text/number | C1 |
| `garage` | Garage | Yes/No/Either | C1 |
| `pool` | Pool | Yes/No/Either | C1 |
| `view_preferences` | View Preferences | multi-select | C1, C3 |
| `appliances` | Appliances Required | multi-select | C1 |
| `non_negotiable_amenities` | Non-Negotiable Amenities | multi-select | C1, C9 |

### 6.3 Leasing Terms Tab — Structured Fields

| Field (wire:model) | Label | Type | Intel Category |
|-------------------|-------|------|---------------|
| `budget` | Maximum Monthly Lease Price (required) | text (currency) | C2 |
| `lease_for` | Offered Lease Term (required) | multi-select | C10 |
| `other_lease_for` | Other Lease Term | text | C10 |
| `lease_date` | Proposed Lease Start Date (required) | date | C6 |
| `leasing_spaces_tenant` | Acceptable Leasing Spaces (required) | multi-select (Entire Property / Single Room / ADU — Residential; Single Office/Suite / Entire Property — Commercial) | C1 |

**Residential lease term options observed:** 3 Months, 6 Months, 9 Months, 1 Year, 2 Years, Month-to-Month, Other  
**Commercial lease term options observed:** 6 Months, 1 Year, 2 Years, 3-5 Years, 6+ Years, Month-to-Month, Other

### 6.4 Pre-Screening Tab — Structured Fields

| Field (wire:model) | Label | Type | Status | Intel Category |
|-------------------|-------|------|--------|---------------|
| `number_occupant` | Number of Occupants (required) | number | Active | C7 |
| `monthly_income` | Estimated Monthly Net Household Income (required) | text (currency) | Active | C7 |
| `pets` | Pets (Residential only) | select (Yes/No) | Active | C7 |
| `number_of_pets` | Number of Pets | number | Active if pets=Yes | C7 |
| `type_of_pets` | Pet Types | text | Active if pets=Yes | C7 |
| `breed_of_pets` | Breed of Pets | text | Active if pets=Yes | C7 |
| `weight_of_pets` | Pet Weight (lbs) | text | Active if pets=Yes | C7 |
| `service_animal` | Service Animal | select (Yes/No) | Active if pets=Yes | C7 |
| `support_animal` | Emotional Support Animal | select (Yes/No) | Active if pets=Yes | C7 |
| `screening_concerns` | Screening Concerns (required) | select (Yes/No) | Active | C7 |
| `screening_concerns_explanation` | Screening Concerns Explanation | text | Active if Yes | C7 |
| `credit_score_rating` | Credit Score Rating | multi-select | **Commented out** | C7 |
| `prior_eviction` | Prior Eviction(s) in Last 7 Years | select (Yes/No) | **Commented out** | C7 |
| `eviction_explanation` | Eviction Explanation | textarea | **Commented out** | C7 |
| `prior_felony` | Prior Felony Conviction(s) in Last 7 Years | select (Yes/No) | **Commented out** | C7 |
| `prior_felony_explanation` | Felony Explanation | textarea | **Commented out** | C7 |

> **Note:** The commented-out fields (`credit_score_rating`, `prior_eviction`, `prior_felony`) exist in `pre-screening.blade.php` inside a `{{-- ... --}}` block. They were intentionally disabled, presumably pending legal/compliance review. See Section 14 for Fair Housing guidance on re-enabling these.

### 6.5 Additional Details Tab

| Field (wire:model) | Label | Type | Intel Category |
|-------------------|-------|------|---------------|
| `additional_details` | Tenant Notes / Description | textarea | C8 |

### 6.6 AI FAQ Questions — Tenant

The tenant AI FAQ uses a flat array of 27 questions (not keyed groups like the other three workflows). All 27 keys (faq_q1 through faq_q27) are present with no gaps in numbering.

**Category 1: Lifestyle & Priorities (6 questions — q1–q6)**

| Key | Label / Topic | Intel Category |
|-----|--------------|---------------|
| `faq_q1` | Work from home needs and ideal home office setup | C1, C3, C9 |
| `faq_q2` | What matters most in day-to-day living (quiet, walkability, community, outdoor access) | C3, C9 |
| `faq_q3` | Ideal neighborhood vibe | C3, C9 |
| `faq_q4` | Noise sensitivity from neighbors, traffic, or businesses | C3, C9 |
| `faq_q5` | Top amenity priority (laundry, parking, outdoor space, storage) | C1, C9 |
| `faq_q6` | Outdoor space importance and ideal configuration | C1, C9 |

**Category 2: Pet Details (2 questions — q7–q8)**

| Key | Label / Topic | Intel Category |
|-----|--------------|---------------|
| `faq_q7` | Pet breed, size, and special space or yard needs | C7 |
| `faq_q8` | Willingness to pay pet deposit or monthly pet rent | C7 |

**Category 3: Flexibility & Lease Intent (5 questions — q9–q13)**

| Key | Label / Topic | Intel Category |
|-----|--------------|---------------|
| `faq_q9` | Flexibility on lease length if great property has different terms | C10 |
| `faq_q10` | Openness to furnished unit | C1, C9 |
| `faq_q11` | Firmness of move-in timeline / flexibility window | C6 |
| `faq_q12` | Risk of needing to break lease early (job relocation, life changes) | C10 |
| `faq_q13` | Interest in longer lease term in exchange for rent reduction | C10 |

**Category 4: Background & Motivation (5 questions — q14–q18)**

| Key | Label / Topic | Intel Category |
|-----|--------------|---------------|
| `faq_q14` | What is driving the rental search right now | C3, C9 |
| `faq_q15` | Most recent tenancy length and reason for moving | C7 |
| `faq_q16` | Short-term solution or long-term home intent | C9, C10 |
| `faq_q17` | Landlord or employer reference availability | C7 |
| `faq_q18` | Source of income (employment, self-employment, retirement, other) | C7 |

**Category 5: Communication & Preferences (2 questions — q19–q20)**

| Key | Label / Topic | Intel Category |
|-----|--------------|---------------|
| `faq_q19` | Preferred communication method with landlord | — |
| `faq_q20` | Biggest concern or hesitation in the rental search | C9 |

**Category 6: Commercial — Business Use (7 questions — q21–q27, shown when property_type = Commercial Property)**

| Key | Label / Topic | Intel Category |
|-----|--------------|---------------|
| `faq_q21` | Type of business operating from the space | C10 |
| `faq_q22` | Expected customer or client foot traffic | C10 |
| `faq_q23` | Special equipment or power requirements | C1, C10 |
| `faq_q24` | Exterior building signage requirements | C5, C10 |
| `faq_q25` | Need to modify or build out the space | C10 |
| `faq_q26` | Expected hours of operation | C10 |
| `faq_q27` | Flexibility on commercial lease term length and structure | C10 |

### 6.7 Tenant Gaps by Intelligence Category

| Category | Present (Structured) | Missing / Underserved |
|----------|---------------------|----------------------|
| C1 Physical | Property type, subtype, beds, baths, sqft, acreage, garage (Y/N/Either), pool (Y/N/Either), views, appliances, non-negotiable amenities, leasing spaces | Maximum square footage (ceiling preference); accessibility needs (ADA/wheelchair structured field); laundry in-unit (currently only via amenities multi-select) |
| C2 Financial | Maximum monthly budget | Move-in budget for upfront costs (first/last/deposit ceiling); budget for utilities on top of rent |
| C3 Location | Acceptable cities, counties, state | Commute destination (structured — entirely absent from tenant form and tenant AI FAQ); maximum commute time (structured); school district preference (Residential) |
| C4 Condition | — (not collected anywhere in tenant form) | Minimum acceptable property condition (structured); minimum appliance age tolerance |
| C5 Legal | — | Smoking policy preference (structured — absent from both tenant form and tenant AI FAQ); HOA community acceptance |
| C6 Flexibility | Proposed lease start date, lease term multi-select | Move-in date range (earliest + latest, vs. single date); notice period remaining at current address |
| C7 Occupant | Number of occupants, monthly income, pets (type / breed / weight / service / ESA), screening concerns | Credit score range (commented-out field — see Section 14); income source (AI FAQ only faq_q18) |
| C8 Marketing | Additional details textarea | — |
| C9 Compatibility | Non-negotiable amenities, AI FAQ lifestyle / deal-breaker questions | No structured tenant archetype / rental-purpose tag; no structured deal-breaker flags |
| C10 Commercial | Leasing spaces, lease term, AI commercial group (q21–q27) | Business type (structured — currently AI FAQ only faq_q21); required sq ft (structured — currently AI FAQ only, overlaps with min_sqft); zoning requirement (structured — currently AI FAQ only faq_q24) |

---

## 7. Recommended New Questions by Workflow

### 7.1 Seller Workflow — Recommended New Questions

Each entry includes: **Label | Purpose | Input Type | Tier | Intelligence Pillars | Placement | Compliance Notes**

The following fields were confirmed absent from Seller structured tabs after a thorough read of all files listed in Section 2.

---

**S-01: Average Monthly Utility Cost (Structured)**  
*Purpose:* Enables AI to compute total cost of ownership and communicate it to buyers. Currently only collected in AI FAQ Group 2 (faq_q11) as open narrative text with no machine-readable value.  
*Input type:* Text (currency, approximate monthly average)  
*Tier:* Optional  
*Intelligence pillars:* C2  
*Placement:* Financial Details tab (Residential/Income section)  
*Compliance notes:* None

---

**S-02: Year of Last Significant Renovation**  
*Purpose:* Captures effective age separate from chronological age. The `year_built` field is already collected; renovation year provides the "effective age" signal. A 1965 home renovated in 2022 competes differently from a 1965 home in original condition.  
*Input type:* Number (4-digit year) or select "Not Renovated / Original Condition"  
*Tier:* Optional  
*Intelligence pillars:* C4, C8  
*Placement:* Property Preferences tab, after Year Built  
*Compliance notes:* None

---

**S-03: Seller Motivation Category (Agent-Visible Only)**  
*Purpose:* Motivation signals (Relocating, Downsizing, Estate, Financial, etc.) feed the AI negotiation coaching layer and help agents understand urgency without publicly disclosing sensitive context. AI FAQ Group 4 (faq_q21) asks this as open text, but structured capture enables categorical analysis.  
*Input type:* Select (Relocating / Downsizing / Upsizing / Estate / Inherited / Financial Pressure / Retirement / Divorce / Separation / Job Change / Investment Exit / Other) — visible to listing agent only  
*Tier:* Optional (agent-visible only)  
*Intelligence pillars:* C6, C8  
*Placement:* Seller Terms tab, after Target Closing Timeframe — marked "Shared with your Agent only"  
*Compliance notes:* Must not be exposed on public listing card. Divorce/financial pressure disclosures could enable exploitative targeting if exposed to opposing parties.

---

**S-04: Inspection Contingency Acceptance (Structured)**  
*Purpose:* Many sellers in competitive markets decline inspection contingencies. Structured capture enables buyer-side matching (see B-03) and filters incompatible bids before submission. AI FAQ Group 4 (faq_q24) asks about contingencies in narrative form.  
*Input type:* Select (Yes — will accept / No — will not accept / Negotiable)  
*Tier:* Optional  
*Intelligence pillars:* C6, C9  
*Placement:* Seller Terms tab  
*Compliance notes:* None

---

**S-05: Appraisal Contingency Acceptance (Structured)**  
*Purpose:* Appraisal gap coverage is increasingly common in competitive markets. Structured capture mirrors S-04.  
*Input type:* Select (Yes — will accept / No — will not accept / Negotiable)  
*Tier:* Optional  
*Intelligence pillars:* C6, C9  
*Placement:* Seller Terms tab  
*Compliance notes:* None

---

**S-06: Leaseback Required (Structured)**  
*Purpose:* Post-close leaseback is a common seller need currently negotiated ad hoc. Capturing it structurally enables automatic bid filtering.  
*Input type:* Select (Yes — leaseback needed / No / Negotiable) + conditional number field for days needed  
*Tier:* Optional  
*Intelligence pillars:* C6  
*Placement:* Seller Terms tab  
*Compliance notes:* None

---

**S-07: Occupancy Rate (Income Property, Structured)**  
*Purpose:* AI FAQ faq_ci_q1 asks about occupancy rate as narrative text. A structured field with a percentage value enables accurate NOI verification and cap rate validation for the compatibility scoring layer.  
*Input type:* Text (percentage) or select (100% / 75–99% / 50–74% / Under 50%)  
*Tier:* Optional (Income/Commercial property types only)  
*Intelligence pillars:* C10  
*Placement:* Financial Details tab (Income/Commercial section)  
*Compliance notes:* None

---

### 7.2 Landlord Workflow — Recommended New Questions

---

**L-01: Year Built (Structured)**  
*Purpose:* The seller form collects `year_built` for Residential, Income, Commercial, and Business property types. The landlord form has no equivalent. Year built is the foundational condition-intelligence data point for any rental property.  
*Input type:* Number (4-digit year)  
*Tier:* Optional  
*Intelligence pillars:* C1, C4  
*Placement:* Property Preferences tab, after Minimum Square Footage  
*Compliance notes:* None

---

**L-02: Available Date (Structured)**  
*Purpose:* AI FAQ Group 4 (faq_q27) asks "When will the unit be available?" as narrative text. A structured date field is essential for move-in date matching between landlord listings and tenant criteria.  
*Input type:* Date  
*Tier:* Required  
*Intelligence pillars:* C6, C9  
*Placement:* Lease Terms tab  
*Compliance notes:* None

---

**L-03: Pet Policy (Structured Fields)**  
*Purpose:* AI FAQ Group 3 (faq_q14) collects pet policy as open text. A structured set of fields enables automated tenant-landlord matching on this common deal-breaker.  
*Input type:* Select (Yes — Allowed / No — Not Allowed / Case by Case) + conditional sub-fields: max weight (lbs), species allowed (multi-select: Dog / Cat / Bird / Small caged animal), pet deposit ($), monthly pet fee ($)  
*Tier:* Optional  
*Intelligence pillars:* C5, C7, C9  
*Placement:* Lease Terms tab  
*Compliance notes:* Service animals and emotional support animals cannot be restricted under the Fair Housing Act / ADA. The structured pet policy field must include a visible note: "Service Animals and ESAs are not pets and cannot be restricted under federal law."

---

**L-04: Smoking Policy (Structured)**  
*Purpose:* AI FAQ Group 3 (faq_q15) collects smoking policy as open text. Structured capture enables automatic filtering.  
*Input type:* Select (No smoking anywhere / Outdoor / patio only / Allowed)  
*Tier:* Optional  
*Intelligence pillars:* C5, C9  
*Placement:* Lease Terms tab  
*Compliance notes:* Smoking status is not a protected class (behavior, not characteristic). Safe to collect.

---

**L-05: Security Deposit Amount (Structured)**  
*Purpose:* AI FAQ Group 4 (faq_q25) collects deposit information as open text. A structured field enables budget-based tenant filtering.  
*Input type:* Text (currency) or select (1 month's rent / 1.5 months / 2 months / No deposit / Negotiable)  
*Tier:* Optional  
*Intelligence pillars:* C2  
*Placement:* Lease Terms tab, after desired lease price  
*Compliance notes:* Security deposit limits vary by state. No Fair Housing concerns.

---

**L-06: Minimum Income Requirement (Structured, Agent-Visible Only)**  
*Purpose:* AI FAQ Group 3 (faq_q18) addresses tenant screening criteria in open text. Structuring the income requirement enables pre-qualification scoring without public disclosure.  
*Input type:* Select (2x monthly rent / 2.5x / 3x / No minimum / Other) or text  
*Tier:* Optional (agent-visible only in bid match scoring; not displayed publicly)  
*Intelligence pillars:* C7, C9  
*Placement:* Lease Terms tab  
*Compliance notes:* Income requirements must be applied uniformly. Do not expose on the public listing card. Framing: "required to qualify" criterion visible only to the agent.

---

**L-07: Subletting Policy (Structured)**  
*Purpose:* AI FAQ Group 3 (faq_q20) collects subletting policy as open text. Structured capture enables filtering for tenants who require subletting flexibility.  
*Input type:* Select (Not Allowed / Allowed with Landlord Approval / Allowed)  
*Tier:* Optional  
*Intelligence pillars:* C5  
*Placement:* Lease Terms tab  
*Compliance notes:* None

---

### 7.3 Buyer Workflow — Recommended New Questions

---

**B-01: Commute Destination + Max Time + Mode (Structured)**  
*Purpose:* AI FAQ Group 2 (faq_q9) asks about commute as narrative text. Commute destination ZIP/city is the single most actionable location signal for geographic matching. A structured field enables drive-time polygon scoring in Phase 7.  
*Input type:* Text (ZIP code or city name) + select for maximum commute time (15 / 20 / 30 / 45 / 60+ minutes) + select for mode (Drive / Transit / Walk / Bike / Remote — no commute)  
*Tier:* Optional  
*Intelligence pillars:* C3, C9  
*Placement:* Property Preferences tab, after State field  
*Compliance notes:* Commute is not a protected class characteristic. Safe to collect.

---

**B-02: Buyer Archetype / Purchase Purpose (Structured)**  
*Purpose:* AI FAQ Group 1 (faq_q1) asks about primary use as narrative text. A structured field enables demographic segmentation for marketing and matching without Fair Housing violations.  
*Input type:* Select (Primary Residence / Vacation Home / Second Home / Investment / Business Use / Development / Other)  
*Tier:* Required  
*Intelligence pillars:* C9, C10  
*Placement:* Property Preferences tab (top)  
*Compliance notes:* This is a purpose-of-purchase question, not a demographic question. Do not include options implying familial status (e.g., "starter home for family").

---

**B-03: Inspection Contingency Required (Structured)**  
*Purpose:* Mirrors Seller field S-04. Enables compatibility matching between buyer contingency posture and seller contingency acceptance. AI FAQ Group 4 (faq_q26) touches this as narrative.  
*Input type:* Select (Yes — required / No — will waive / Negotiable)  
*Tier:* Optional  
*Intelligence pillars:* C6, C9  
*Placement:* Purchasing Terms tab  
*Compliance notes:* None

---

**B-04: Appraisal Contingency Required (Structured)**  
*Purpose:* Mirrors Seller field S-05.  
*Input type:* Select (Yes — required / No — will waive / Negotiable)  
*Tier:* Optional  
*Intelligence pillars:* C6, C9  
*Placement:* Purchasing Terms tab  
*Compliance notes:* None

---

**B-05: Sale of Current Home Contingency (Structured)**  
*Purpose:* Buyer's need to sell their current property before closing is a major dealbreaker for many sellers. AI FAQ Group 4 (faq_q24) addresses this in narrative form.  
*Input type:* Select (Yes — need to sell current home first / No — already sold or renting / No — cash or bridge loan available)  
*Tier:* Optional  
*Intelligence pillars:* C6, C9  
*Placement:* Purchasing Terms tab  
*Compliance notes:* None

---

**B-06: HOA Acceptance + Maximum HOA Fee Tolerance (Structured)**  
*Purpose:* AI FAQ Group 2 (faq_q13) captures HOA acceptance as narrative text. A structured Y/N + fee ceiling enables automatic listing filtering against the seller's `association_fee_amount` field.  
*Input type:* Select (Yes — will accept HOA / No — HOA-free only / Flexible) + conditional text field for maximum monthly HOA fee tolerated  
*Tier:* Optional  
*Intelligence pillars:* C5, C9  
*Placement:* Property Preferences tab  
*Compliance notes:* None

---

**B-07: Fixer-Upper Tolerance (Structured)**  
*Purpose:* AI FAQ Group 3 (faq_q17) captures renovation tolerance as narrative text. Structured capture enables matching buyers with distressed or as-is listings.  
*Input type:* Select (Move-in ready only / Light cosmetic work acceptable / Moderate renovation acceptable / Full renovation / Investment-grade fixer acceptable)  
*Tier:* Optional  
*Intelligence pillars:* C4, C9  
*Placement:* Property Preferences tab  
*Compliance notes:* None

---

**B-08: Flood Zone Tolerance (Structured)**  
*Purpose:* AI FAQ Group 2 (faq_q14) captures flood zone concern as narrative text. Structured capture enables filtering against the seller's `flood_zone_code` field (which already exists with full FEMA designations).  
*Input type:* Select (No flood zone preferred / Minimal risk only — Zone X / Will accept moderate risk / Will accept any zone)  
*Tier:* Optional  
*Intelligence pillars:* C5, C9  
*Placement:* Property Preferences tab  
*Compliance notes:* None

---

**B-09: Minimum Cap Rate Target (Structured, Income/Commercial)**  
*Purpose:* AI FAQ commercial add-on (faq_ci_q5) captures target cap rate as narrative text. A structured field enables automated matching with seller income property listings that carry a `minimum_cap_rate` field.  
*Input type:* Text (percentage — minimum cap rate required by buyer)  
*Tier:* Optional (shown only for Income/Commercial property type selection)  
*Intelligence pillars:* C10, C9  
*Placement:* Purchasing Terms tab (Income/Commercial section)  
*Compliance notes:* None

---

### 7.4 Tenant Workflow — Recommended New Questions

---

**T-01: Commute Destination + Max Time + Mode (Structured)**  
*Purpose:* Unlike the Buyer AI FAQ (which has faq_q9 on commute), the tenant AI FAQ has no commute question at all across all 27 questions. This is the most critical missing signal for tenant geographic matching.  
*Input type:* Text (ZIP code or city name) + select for maximum commute time + select for mode  
*Tier:* Optional  
*Intelligence pillars:* C3, C9  
*Placement:* Property Details tab, after State field  
*Compliance notes:* None

---

**T-02: Rental Purpose / Tenant Archetype (Structured)**  
*Purpose:* No structured purpose-of-rental field exists for tenants. Enables marketing segmentation and matching.  
*Input type:* Select (Personal residence / Student / Corporate relocation / Vacation / Short-term / Business use / Other)  
*Tier:* Optional  
*Intelligence pillars:* C9  
*Placement:* Listing Details tab or Property Details tab  
*Compliance notes:* Must not include options that reveal familial status (e.g., "family with children"). "Personal residence" is the appropriate non-discriminatory framing.

---

**T-03: Move-In Budget for Upfront Costs (Structured)**  
*Purpose:* Many tenants are budget-constrained on first month + last month + deposit in addition to monthly rent. Currently not collected anywhere.  
*Input type:* Text (currency) or select (Up to 1 month total / Up to 2 months / Up to 3 months / Flexible)  
*Tier:* Optional  
*Intelligence pillars:* C2, C7  
*Placement:* Leasing Terms tab, after maximum monthly budget  
*Compliance notes:* None

---

**T-04: Move-In Date Range — Earliest + Latest (Structured)**  
*Purpose:* `lease_date` captures a single exact desired start date. A date range makes matching more flexible and realistic.  
*Input type:* Date (earliest acceptable) + Date (latest acceptable)  
*Tier:* Optional  
*Intelligence pillars:* C6, C9  
*Placement:* Leasing Terms tab, supplementing `lease_date`  
*Compliance notes:* None

---

**T-05: Accessibility Requirements (Structured)**  
*Purpose:* Currently no field captures ADA / wheelchair needs, grab bars, ramp access, or ground-floor requirements. A critical matching signal for landlords with accessible units.  
*Input type:* Select (No special requirements / Ground floor or elevator required / Wheelchair accessible required / ADA compliant features required) + optional details text  
*Tier:* Optional  
*Intelligence pillars:* C1, C9  
*Placement:* Property Details tab  
*Compliance notes:* Disability is a protected class under the Fair Housing Act. These fields must be collected ONLY to show accessible listings to the tenant and NEVER used to screen tenants out. The field must be presented as a preference filter (what listings to show me), not a qualification criterion.

---

**T-06: Credit Score Range — Reinstatement with Compliance Framing**  
*Purpose:* The `credit_score_rating` field exists in `pre-screening.blade.php` inside a `{{-- ... --}}` comment block. With proper compliance framing, structured credit self-disclosure helps agents set expectations, enables pre-screening transparency, and reduces failed applications.  
*Input type:* Select (Excellent — 750+ / Good — 700–749 / Fair — 650–699 / Below 650 / Prefer not to disclose)  
*Tier:* Optional  
*Intelligence pillars:* C7  
*Placement:* Pre-Screening tab (reinstate the commented-out field with updated label and compliance note)  
*Compliance notes:* Credit score is not a protected class. However, credit criteria must be applied uniformly by landlords and cannot serve as a proxy for protected-class discrimination. The field must be labeled "Self-disclosed for matching purposes — landlords must apply credit standards uniformly per Fair Housing guidelines."

---

**T-07: Smoking Preference (Structured)**  
*Purpose:* No tenant smoking preference is collected anywhere in the tenant form or the tenant AI FAQ. Tenants who smoke need to see whether a property allows it. Mismatches are a common early move-out cause.  
*Input type:* Select (Non-smoker / Smoker — need outdoor smoking allowed / Smoker — need indoor smoking allowed)  
*Tier:* Optional  
*Intelligence pillars:* C9  
*Placement:* Pre-Screening tab  
*Compliance notes:* Smoking status is not a protected class. Safe to collect.

---

## 8. Recommended New Questions by Intelligence Category

### C1 — Physical Attributes

| Question | Workflow(s) | Tier |
|----------|-------------|------|
| Year Built | Landlord | Optional |
| Year of Last Significant Renovation | Seller | Optional |
| Accessibility Requirements | Tenant | Optional |

> Note: Year Built is already collected for Seller (Residential/Income/Commercial/Business). It is missing only from the Landlord workflow.

### C2 — Financial Intelligence

| Question | Workflow(s) | Tier |
|----------|-------------|------|
| Average Monthly Utility Cost (Structured) | Seller | Optional |
| Security Deposit Amount (Structured) | Landlord | Optional |
| Maximum HOA Fee Tolerance | Buyer | Optional |
| Minimum Cap Rate Target | Buyer (Income/Commercial) | Optional |
| Move-In Budget for Upfront Costs | Tenant | Optional |

### C3 — Location & Lifestyle

| Question | Workflow(s) | Tier |
|----------|-------------|------|
| Commute Destination + Max Time + Mode | Buyer, Tenant | Optional |

### C4 — Condition & Maintenance

| Question | Workflow(s) | Tier |
|----------|-------------|------|
| Year Built | Landlord | Optional |
| Year of Last Renovation | Seller | Optional |
| Fixer-Upper Tolerance | Buyer | Optional |

### C5 — Legal & Compliance

| Question | Workflow(s) | Tier |
|----------|-------------|------|
| Pet Policy (Structured) | Landlord | Optional |
| Smoking Policy (Structured) | Landlord | Optional |
| Subletting Policy (Structured) | Landlord | Optional |
| HOA Acceptance + Max Fee | Buyer | Optional |
| Flood Zone Tolerance | Buyer | Optional |

> Note: Flood zone code and HOA structured fields already exist in the Seller Tax/Legal/HOA tab. The gap is on the buyer/demand side — tolerance fields for matching against the seller's existing structured data.

### C6 — Flexibility & Negotiation

| Question | Workflow(s) | Tier |
|----------|-------------|------|
| Seller Motivation Category (Agent-Visible) | Seller | Optional |
| Inspection Contingency Acceptance | Seller | Optional |
| Appraisal Contingency Acceptance | Seller | Optional |
| Leaseback Required | Seller | Optional |
| Available Date (Structured) | Landlord | Required |
| Inspection Contingency Required | Buyer | Optional |
| Appraisal Contingency Required | Buyer | Optional |
| Sale of Current Home Contingency | Buyer | Optional |
| Move-In Date Range (Earliest + Latest) | Tenant | Optional |

### C7 — Occupant / Tenant Qualification

| Question | Workflow(s) | Tier |
|----------|-------------|------|
| Minimum Income Requirement (Agent-Visible) | Landlord | Optional |
| Pet Policy Structured Fields | Landlord | Optional |
| Credit Score Range (Reinstate) | Tenant | Optional |
| Smoking Preference | Tenant | Optional |

### C8 — Marketing & Uniqueness

| Question | Workflow(s) | Tier |
|----------|-------------|------|
| Year of Last Renovation (feeds "updated" narrative) | Seller | Optional |
| Seller Motivation (feeds narrative) | Seller | Optional |

### C9 — Compatibility & Matching

| Question | Workflow(s) | Tier |
|----------|-------------|------|
| Buyer Archetype / Purchase Purpose | Buyer | Required |
| Tenant Rental Purpose | Tenant | Optional |
| Commute Destination | Buyer, Tenant | Optional |
| Inspection Contingency Match | Seller ↔ Buyer | Optional |
| HOA Acceptance / Fee Match | Seller (existing) ↔ Buyer (new) | Optional |
| Flood Zone Designation Match | Seller (existing) ↔ Buyer (new) | Optional |
| Fixer-Upper Tolerance Match | Seller (existing condition_prop) ↔ Buyer (new) | Optional |
| Pet Policy Match | Landlord (new) ↔ Tenant (existing) | Optional |
| Available Date Match | Landlord (new) ↔ Tenant (existing lease_date) | Required |

### C10 — Commercial & Investment Intelligence

| Question | Workflow(s) | Tier |
|----------|-------------|------|
| Occupancy Rate (Income, Structured) | Seller | Optional |
| Minimum Cap Rate Target | Buyer | Optional |

---

## 9. Required vs Optional vs Premium vs Future Matrix

**Tier definitions:**
- **Required** — Must be answered to submit/save. Gates core bid-matching.
- **Optional** — Enriches the profile but submission is not blocked. Used for scoring and AI narrative.
- **Premium** — Available only on a paid tier or after agent engagement. Could unlock "DNA Pro" features.
- **Future** — Requires third-party data (Walk Score API, school ratings, drive-time API) or ML modeling before implementation.

| # | Question / Field | Workflow | Current Status | Recommended Tier |
|---|-----------------|----------|---------------|-----------------|
| S-01 | Avg Monthly Utility Cost | Seller | Missing (structured) | Optional |
| S-02 | Year of Last Renovation | Seller | Missing | Optional |
| S-03 | Seller Motivation Category (agent-only) | Seller | Missing | Optional |
| S-04 | Inspection Contingency Acceptance | Seller | Missing | Optional |
| S-05 | Appraisal Contingency Acceptance | Seller | Missing | Optional |
| S-06 | Leaseback Required | Seller | Missing | Optional |
| S-07 | Occupancy Rate (Income/Commercial) | Seller | Missing (structured) | Optional |
| L-01 | Year Built | Landlord | Missing entirely | Optional |
| L-02 | Available Date | Landlord | Missing | Required |
| L-03 | Pet Policy (Structured) | Landlord | Missing (structured) | Optional |
| L-04 | Smoking Policy | Landlord | Missing (structured) | Optional |
| L-05 | Security Deposit Amount | Landlord | Missing (structured) | Optional |
| L-06 | Minimum Income Requirement (agent-only) | Landlord | Missing | Optional |
| L-07 | Subletting Policy | Landlord | Missing (structured) | Optional |
| B-01 | Commute Destination + Time + Mode | Buyer | Missing entirely | Optional |
| B-02 | Buyer Archetype / Purchase Purpose | Buyer | Missing (structured) | Required |
| B-03 | Inspection Contingency Required | Buyer | Missing | Optional |
| B-04 | Appraisal Contingency Required | Buyer | Missing | Optional |
| B-05 | Sale of Current Home Contingency | Buyer | Missing | Optional |
| B-06 | HOA Acceptance + Max Fee | Buyer | Missing (structured) | Optional |
| B-07 | Fixer-Upper Tolerance | Buyer | Missing (structured) | Optional |
| B-08 | Flood Zone Tolerance | Buyer | Missing (structured) | Optional |
| B-09 | Min Cap Rate Target (Income/Comm) | Buyer | Missing (structured) | Optional |
| T-01 | Commute Destination + Time + Mode | Tenant | Missing entirely | Optional |
| T-02 | Rental Purpose / Tenant Archetype | Tenant | Missing | Optional |
| T-03 | Move-In Budget (Upfront) | Tenant | Missing | Optional |
| T-04 | Move-In Date Range | Tenant | Missing | Optional |
| T-05 | Accessibility Requirements | Tenant | Missing | Optional |
| T-06 | Credit Score Range (Reinstate) | Tenant | Commented-out | Optional |
| T-07 | Smoking Preference | Tenant | Missing | Optional |
| F-01 | Walk Score Integration | All | Missing | Future |
| F-02 | School Rating Integration | Seller, Landlord | Missing | Future |
| F-03 | Drive-Time API (Commute) | All | Missing | Future |
| F-04 | Property DNA Compatibility Score | All | Missing | Future (Phase 5) |
| P-01 | Investment Portfolio Sync | Buyer | Missing | Premium |
| P-02 | Lifestyle Profile Quiz (Extended) | All | Missing | Premium |

---

## 10. Suggested Database Architecture Additions

> **Important:** This section describes schema additions only. No migration or schema change is part of this document's deliverable. These are planning-level recommendations.

### 10.1 New EAV Meta Keys — Seller Offer Listings (`seller_agent_auction_metas`)

The following meta keys are confirmed absent after reading all seller Blade tabs. The `annual_property_taxes`, `has_hoa`, `association_fee_amount`, `flood_zone_code`, and `year_built` fields ALREADY EXIST as structured fields and do NOT need new meta keys.

| Meta Key | Data Type | Notes |
|----------|-----------|-------|
| `avg_monthly_utility_cost` | decimal | Estimated monthly average (S-01) |
| `year_last_renovated` | integer or null | 4-digit year (S-02) |
| `seller_motivation_category` | string | Agent-visible only; encrypted recommended (S-03) |
| `inspection_contingency_acceptance` | string | Yes / No / Negotiable (S-04) |
| `appraisal_contingency_acceptance` | string | Yes / No / Negotiable (S-05) |
| `leaseback_required` | string | Yes / No / Negotiable (S-06) |
| `leaseback_days_needed` | integer | Days needed if leaseback = Yes (S-06) |
| `occupancy_rate` | decimal | Percentage, Income/Commercial only (S-07) |

### 10.2 New EAV Meta Keys — Landlord Offer Listings (`landlord_agent_auction_metas`)

| Meta Key | Data Type | Notes |
|----------|-----------|-------|
| `year_built` | integer | 4-digit year — entirely absent from landlord form (L-01) |
| `available_date` | date | When unit becomes available (L-02) |
| `pet_policy` | string | Allowed / Not Allowed / Case by Case (L-03) |
| `pet_max_weight_lbs` | integer | Pounds (L-03) |
| `pet_species_allowed` | JSON array | Dog, Cat, Bird, etc. (L-03) |
| `pet_deposit_amount` | decimal | One-time pet deposit (L-03) |
| `pet_monthly_fee` | decimal | Monthly pet fee (L-03) |
| `smoking_policy` | string | No Smoking / Outdoor Only / Allowed (L-04) |
| `security_deposit_amount` | decimal | Dollar amount or "N months" equivalent (L-05) |
| `min_income_requirement` | string | Agent-visible: 2x, 2.5x, 3x, etc. (L-06) |
| `subletting_policy` | string | Not Allowed / With Approval / Allowed (L-07) |

### 10.3 New EAV Meta Keys (or Native Columns) — Buyer Criteria

The `buyer_agent_auctions` table has native columns per the replit.md architecture note. New buyer fields should be evaluated for native column vs. EAV depending on query frequency. Fields used in listing search/filter (commute destination, archetype) are candidates for native columns.

| Meta Key / Column | Data Type | Notes |
|------------------|-----------|-------|
| `purchase_purpose` | string | Primary Residence / Investment / Vacation / etc. (B-02) |
| `commute_destination_zip` | string | Preferred commute origin ZIP (B-01) |
| `max_commute_minutes` | integer | Maximum acceptable commute time (B-01) |
| `commute_mode` | string | Drive / Transit / Walk / Bike / Remote (B-01) |
| `inspection_contingency_required` | string | Yes / No / Negotiable (B-03) |
| `appraisal_contingency_required` | string | Yes / No / Negotiable (B-04) |
| `home_sale_contingency` | string | Yes / No / Bridge Loan Available (B-05) |
| `hoa_acceptance` | string | Yes / No / Flexible (B-06) |
| `hoa_max_monthly_fee` | decimal | Maximum acceptable HOA fee (B-06) |
| `fixer_upper_tolerance` | string | Move-in ready / Light / Moderate / Full / Investment (B-07) |
| `flood_zone_tolerance` | string | No flood zone / Zone X / Any (B-08) |
| `min_cap_rate_target` | decimal | Percentage (Income/Commercial buyers) (B-09) |

### 10.4 New EAV Meta Keys — Tenant Criteria (`tenant_agent_auction_metas`)

The `tenant_agent_auctions` table uses EAV (meta tables added per replit.md). New fields follow the same pattern.

| Meta Key | Data Type | Notes |
|----------|-----------|-------|
| `rental_purpose` | string | Personal residence / Student / Corporate / etc. (T-02) |
| `commute_destination_zip` | string | (T-01) |
| `max_commute_minutes` | integer | (T-01) |
| `commute_mode` | string | (T-01) |
| `move_in_budget_upfront` | decimal | Total upfront budget (T-03) |
| `move_in_date_earliest` | date | Earliest acceptable move-in (T-04) |
| `move_in_date_latest` | date | Latest acceptable move-in (T-04) |
| `accessibility_requirements` | string | None / Ground floor / Wheelchair / ADA (T-05) |
| `credit_score_range` | string | Excellent / Good / Fair / Below 650 / Not disclosed (T-06) |
| `smoking_preference` | string | Non-smoker / Outdoor / Indoor (T-07) |

### 10.5 New Table: `property_dna_profiles`

A computed intelligence profile table for supply-side listings (Seller, Landlord). Populated asynchronously after listing creation/edit by a DNA computation job.

```
property_dna_profiles
─────────────────────
id                          bigint PK
listing_type                enum('seller', 'landlord')
listing_id                  bigint (FK to respective auction table)
version                     integer (incremented on recalculation)
physical_score              decimal(5,2)  -- C1 completeness score
financial_score             decimal(5,2)  -- C2 completeness score
location_score              decimal(5,2)  -- C3 completeness score
condition_score             decimal(5,2)  -- C4 completeness score
legal_score                 decimal(5,2)  -- C5 completeness score
flexibility_score           decimal(5,2)  -- C6 completeness score
marketing_score             decimal(5,2)  -- C8 completeness score
commercial_score            decimal(5,2)  -- C10 completeness score
overall_dna_completeness    decimal(5,2)  -- weighted composite
ai_narrative_headline       text          -- AI-generated headline
ai_narrative_summary        text          -- AI-generated property summary
ai_buyer_archetype_tags     JSON          -- suggested buyer archetypes
ai_marketing_hooks          JSON          -- array of marketing hook strings
last_computed_at            timestamp
created_at                  timestamp
updated_at                  timestamp
```

### 10.6 New Table: `buyer_tenant_dna_profiles`

A computed intelligence profile for demand-side listings (Buyer, Tenant).

```
buyer_tenant_dna_profiles
──────────────────────────
id                          bigint PK
listing_type                enum('buyer', 'tenant')
listing_id                  bigint (FK to respective auction table)
version                     integer
preference_completeness     decimal(5,2)  -- % of optional fields filled
lifestyle_tags              JSON          -- inferred from AI FAQ answers
deal_breaker_flags          JSON          -- structured deal-breakers
archetype_label             string        -- first-time buyer, investor, etc.
commute_polygon_cache       text          -- cached drive-time polygon (future)
last_computed_at            timestamp
created_at                  timestamp
updated_at                  timestamp
```

### 10.7 New Table: `listing_compatibility_scores`

A junction table recording the computed compatibility score between a buyer/tenant listing and a seller/landlord listing.

```
listing_compatibility_scores
────────────────────────────
id                          bigint PK
demand_listing_type         enum('buyer', 'tenant')
demand_listing_id           bigint
supply_listing_type         enum('seller', 'landlord')
supply_listing_id           bigint
overall_score               decimal(5,2)  -- 0-100
physical_match_score        decimal(5,2)
financial_match_score       decimal(5,2)
location_match_score        decimal(5,2)
terms_match_score           decimal(5,2)
deal_breaker_flags          JSON          -- reasons for zero sub-scores
score_explanation           JSON          -- human-readable per-dimension reasons
computed_at                 timestamp
```

---

## 11. Suggested AI Architecture Direction

### 11.1 Current State

The existing AI system consists of four PHP config files that define question arrays. These questions are surfaced in a shared `ai-questions-input.blade.php` partial. Answers are stored — presumably as unstructured text via EAV or a JSON column — but are not connected to any downstream intelligence pipeline. There is currently no:
- Categorization / tagging of answers by intelligence category
- Extraction of structured signals from free text
- Generation of listing summaries, headlines, or marketing copy
- Matching or scoring between AI answers on the demand side and supply side

### 11.2 Recommended AI Architecture (Planning Layer)

**Phase A — Structured Signal Extraction (no LLM required):**  
Tag each existing AI FAQ answer by its intelligence category (C1–C10) at storage time. Each `ai_question_answer` record should store `question_key`, `question_group`, `intelligence_category`, and `answer_text`. The category mappings are fully defined in Sections 3.8, 4.5, 5.5, and 6.6 of this document.

**Phase B — Answer Normalization:**  
For high-value questions, use regex and lookups against the existing `UsCity`, `UsZipCode` models to normalize free-text answers into structured values. Targets: commute destination text → ZIP/city; utility costs → dollar amount; HOA fee (in AI text) → validates against existing `association_fee_amount` structured field; property system ages → year integers.

**Phase C — LLM-Powered DNA Narrative Generation:**  
Given the structured field data + AI FAQ answers, an LLM generates:
- A 3–5 sentence property narrative headline
- A bulleted "Top 5 Selling Points" list
- A "Best matched buyer type" inference
- A "Potential concern flags" list (e.g., "Seller noted foundation repairs in faq_q5 — may require inspection contingency")

**Phase D — Compatibility Vector Matching:**  
Buyer/Tenant structured preferences are converted to a preference vector. Seller/Landlord structured attributes are converted to a supply vector. Weighted scoring produces a compatibility score per dimension (see Section 12).

**Phase E — Marketing Copy Generation:**  
Using the DNA profile + buyer archetype tags, generate Social media caption variants, email subject lines, MLS-quality description paragraphs, and "Ideal for..." copy with Fair Housing compliant framing (see Section 14).

### 11.3 AI Answer Storage Schema (Recommendation)

The recommended answer storage structure adds intelligence category tagging and normalized values to however answers are currently stored:

```
ai_faq_answers
──────────────
id                     bigint PK
listing_type           enum('seller','landlord','buyer','tenant')
listing_id             bigint
question_key           string         -- e.g., 'faq_q1', 'faq_bo_q3'
question_group         string         -- e.g., 'condition_maintenance'
intelligence_category  string         -- C1 through C10
answer_text            text
answer_normalized      JSON           -- extracted structured values (Phase B)
created_at             timestamp
updated_at             timestamp
```

---

## 12. Suggested Compatibility / Scoring Direction

### 12.1 Scoring Framework

The Bid Your Offer compatibility score compares a demand profile (Buyer or Tenant) to a supply profile (Seller or Landlord) across the following weighted dimensions. Weights are initial proposals to be refined with real match data.

| Dimension | Weight | Key Inputs |
|-----------|--------|-----------|
| Physical Match | 30% | Property type match, subtype overlap, beds ≥ minimum, baths ≥ minimum, sqft ≥ minimum, garage match, pool match, view overlap, amenity overlap |
| Financial Match | 25% | Buyer budget ≥ seller price, financing type compatibility, cap rate supply ≥ buyer target (Income/Comm), HOA fee ≤ buyer max (using existing `association_fee_amount` vs. new B-06 field) |
| Location Match | 20% | County/city overlap, state match, commute destination within acceptable drive time (Phase D/future) |
| Terms Match | 15% | Closing timeframe compatible, contingency acceptance aligns (S-04/S-05 ↔ B-03/B-04), lease term compatibility (Landlord/Tenant), available date compatible (new L-02 ↔ existing `lease_date`) |
| Deal-Breaker Flags | 10% (hard gates) | Any deal-breaker mismatch zeroes the overall score — e.g., buyer requires Zone X but property `flood_zone_code` = AE; or tenant has pets but landlord pet_policy = Not Allowed |

### 12.2 Score Display

- Overall score: 0–100
- Color bands: Green (80–100 "Strong Match"), Yellow (60–79 "Possible Match"), Orange (40–59 "Partial Match"), Red (0–39 "Low Match")
- Per-dimension breakdown displayed as an expandable card on the bid view page
- Deal-breaker flags displayed as red badges with explanatory text

### 12.3 Match Explanation (AI-Enhanced)

For each match, the AI layer generates a 2–3 sentence human-readable match explanation:
> "This property strongly aligns with the buyer's preferences for a 3-bedroom Residential property in Orange County with garage. The seller accepts Conventional financing, which matches the buyer's pre-approval type. Note: The property's flood zone designation (AE — High Risk) may conflict with the buyer's indicated preference for Zone X or minimal risk."

---

## 13. Suggested Marketing Campaign Output Structure

### 13.1 Output Types by Role

| Role | Output Type | Description |
|------|-------------|-------------|
| Seller Agent | Listing Narrative | AI-generated property description for MLS, PDFs, and listing cards |
| Seller Agent | Buyer Archetype Targeting Brief | "This property appeals most to: [archetypes]" with supporting reasons |
| Seller Agent | Social Media Caption Pack | 3–5 caption variants for Instagram, Facebook, LinkedIn |
| Seller Agent | Email Subject Lines | 5 A/B-testable email subjects for buyer outreach |
| Landlord Agent | Rental Listing Narrative | Property description optimized for rental platforms |
| Landlord Agent | Tenant Persona Brief | "Ideal tenant profile" based on landlord criteria |
| Buyer Agent | Buyer Brief Summary | Formatted buyer preference summary for seller agents |
| Tenant Agent | Tenant Brief Summary | Formatted tenant profile for landlord agents |
| Platform | Match Alert | Automated notification when new supply matches demand profile above threshold |

### 13.2 Buyer Archetype Tags (Fair Housing Compliant)

Archetypes must be derived from **use-case and financial signals only**, never from protected class characteristics (race, national origin, religion, sex, familial status, disability). Safe archetypes:

| Archetype Tag | Derived From |
|--------------|-------------|
| Remote Worker | WFH response in AI FAQ (faq_q3 Buyer, faq_q1 Tenant) |
| Investor — Cash Flow | Purchase purpose = Investment + cap rate target + financing = Cash or Conventional |
| Investor — Value-Add | Fixer-upper tolerance = Moderate/Full + purchase purpose = Investment |
| Business Owner | Purchase purpose = Business Use + property type = Commercial/Business |
| Land Developer | Purchase purpose = Development + property type = Vacant Land |
| First-Time Buyer | AI FAQ faq_q22 indicates first-time buyer |
| Relocating Professional | Commute destination in different state/region + relocation reason |
| Vacation / Second Home | Purchase purpose = Vacation or Second Home |
| Short-Term Rental Investor | Purchase purpose = Investment + property type = Residential |

### 13.3 Fair Housing Compliant Marketing Language Rules

All AI-generated marketing copy must:
1. Describe **property features and location facts** only — not the type of person the property "suits"
2. Never reference protected class characteristics in copy, archetype labels, or targeting tags
3. Use "ideal for those who..." framing only with non-protected characteristics (e.g., "ideal for remote workers" — not "ideal for families" or "ideal for young couples")
4. Submit all generated copy through a Fair Housing word-filter before display (see Section 14)

---

## 14. Compliance and Fair Housing Guardrails

### 14.1 Protected Classes Under the Fair Housing Act

The Fair Housing Act (42 U.S.C. §§ 3601–3619) prohibits discrimination based on:
1. Race
2. Color
3. National Origin
4. Religion
5. Sex (including gender identity and sexual orientation in many jurisdictions)
6. Familial Status (presence of children under 18, pregnancy, pending adoption)
7. Disability (physical or mental)

Many state and local laws add additional protected classes (e.g., source of income, marital status, age, veteran status, LGBTQ+ identity, immigration status).

### 14.2 Field-Level Compliance Review

| Field or Feature | Compliance Risk | Mitigation |
|-----------------|----------------|-----------|
| Buyer/Tenant Archetype Tags | Medium — "family" language could violate familial status | Only use use-case archetypes (Remote Worker, Investor, etc.), never family-size or composition archetypes |
| Pet Policy Structured Fields (L-03) | Low — pets are not a protected class | Must include a visible note: "Service Animals and Emotional Support Animals are not pets and cannot be restricted under the FHA and ADA" |
| Minimum Income Requirement (L-06) | Medium — source of income is protected in many jurisdictions | Must be applied uniformly; agent-visible only; not surfaced on public listing |
| Credit Score Range — Tenant (T-06) | Low-Medium — credit is not a protected class but has documented disparate impact | Must be applied uniformly; labeled "self-disclosed for matching purposes"; include "Prefer not to disclose" option |
| Seller Motivation Category (S-03) | Medium — divorce/financial pressure disclosures could enable exploitative targeting | Agent-visible only; excluded from all API responses to opposing parties |
| Accessibility Requirements (T-05) | Low — collecting disability-related preferences for matching is FHA-permitted when done to provide accommodations | Must be framed as a preference filter for what listings to show — NEVER as a screening criterion shown to landlords |
| AI-Generated Marketing Copy | Medium — hallucinations could produce discriminatory language | Implement a pre-display Fair Housing word filter flagging protected-class adjacent language before storage or display |

### 14.3 Recommended Fair Housing Word Filter (Blocklist Seeds)

The following terms should trigger a review flag when generated by AI copy engines. This is a seed list and should be expanded with legal counsel.

**Familial status triggers:** family, children, kids, nursery, school-age, expecting, pregnant, growing family, young couple, newlyweds  
**Religion triggers:** church, synagogue, mosque, temple, Christian, Jewish, Muslim, Catholic, near the [religious institution]  
**National origin / Race triggers:** terminology historically associated with redlining (consult a Fair Housing attorney for jurisdiction-specific list)  
**Disability triggers:** "perfect for healthy/active persons," "no disabled access," "stairs only" (acceptable as factual feature; unacceptable as a qualifier for who should live there)  
**Age triggers:** "ideal for young professionals" (age-adjacent — acceptable if not excluding older persons); senior community framing when the property is not a legally age-restricted community

### 14.4 Data Retention and Access Controls

- Seller motivation categories, minimum income requirements, and agent-only screening criteria must be stored in encrypted columns or in a separate access-controlled meta table
- These fields must be excluded from any API responses delivered to buyer-facing or tenant-facing interfaces
- AI training data pipelines must exclude personally-identifying screening data

### 14.5 Tenant Pre-Screening — Commented-Out Fields

The `credit_score_rating`, `prior_eviction`, and `prior_felony` fields were commented out of `pre-screening.blade.php`. Before reinstatement:

1. **Credit Score (T-06 above):** Credit score is not a protected class. Recommending reinstatement with a "Prefer not to disclose" option and a uniform-application compliance note.
2. **Prior Eviction:** Legal guidance varies by jurisdiction. Cities including Seattle, Portland, and Minneapolis restrict use of eviction records in tenant screening. A disclosure label and jurisdiction-awareness flag are required before reinstatement.
3. **Prior Felony:** HUD guidance (2016) warns that blanket criminal history denials may violate Fair Housing due to disparate impact. If reinstated, the field must be labeled "Voluntary Disclosure" and no automated denial logic may be based on this field.

---

## 15. Phased Implementation Roadmap (Phases 1–7)

### Phase 1 — Structured Data Enrichment (Foundation)
**Scope:** Add the new structured fields identified in Sections 7 and 9 to all four Offer Listing forms.  
**Estimated effort:** Medium (new fields per workflow, EAV meta or native column additions, Livewire wiring)  
**No AI required**  
**Deliverables:**
- S-01 through S-07: Seller new fields (utility cost, renovation year, seller motivation, contingency acceptance, leaseback, occupancy rate)
- L-01 through L-07: Landlord new fields (year built, available date, pet policy, smoking policy, security deposit, income requirement, subletting policy)
- B-01 through B-09: Buyer new fields (commute destination, purchase purpose, contingency fields, HOA acceptance, fixer-upper tolerance, flood zone tolerance, cap rate target)
- T-01 through T-07: Tenant new fields (commute, rental purpose, upfront budget, date range, accessibility, credit score reinstatement, smoking preference)
- All new EAV meta keys per Sections 10.1–10.4

**Value:** Immediately improves data quality for manual agent matching with no AI dependency.

---

### Phase 2 — AI Answer Tagging & Storage Normalization
**Scope:** Restructure AI FAQ answer storage to tag every answer with its intelligence category (C1–C10). Extract structured signals from high-value text answers using regex + lookup.  
**Estimated effort:** Medium  
**Deliverables:**
- Updated answer storage schema per Section 11.3
- Category tagging for all 27–39 questions across all four config files (mappings in Sections 3.8, 4.5, 5.5, 6.6)
- Regex/lookup extraction: commute destination text → ZIP/city; utility costs → dollar amount; system ages → year integers
- Bridge logic: AI text answer for HOA fee validates against existing `association_fee_amount`; AI text for property taxes validates against existing `annual_property_taxes`

**Value:** Enables downstream aggregation and scoring without changing the user-facing form.

---

### Phase 3 — Property DNA Profile Computation
**Scope:** Build the `property_dna_profiles` and `buyer_tenant_dna_profiles` tables and a background job that computes completeness scores after each listing save.  
**Estimated effort:** Medium-High  
**Deliverables:**
- `property_dna_profiles` table (Section 10.5)
- `buyer_tenant_dna_profiles` table (Section 10.6)
- DNA Completeness Score algorithm (per-category field fill rate + AI FAQ answer rate)
- Dashboard "Profile Completeness" indicator for listing owners
- "Improve your DNA" prompt linking to unfilled optional fields

**Value:** Users see how complete their profile is. Agents can filter by DNA completeness.

---

### Phase 4 — AI Narrative Generation (LLM Integration)
**Scope:** Use the computed DNA profile + tagged AI FAQ answers to generate property narratives, marketing hooks, and buyer archetype tags.  
**Estimated effort:** Medium (prompt engineering, OpenAI integration, output storage, Fair Housing word filter)  
**Deliverables:**
- AI-generated property headline (3 variants)
- AI-generated "Top 5 Selling Points" bulleted list
- AI-generated buyer/tenant archetype tags (from Section 13.2)
- Fair Housing word-filter pre-display check (Section 14.3)
- PDF listing packet integration (append AI narrative to existing barryvdh/laravel-dompdf packet)

**Value:** Agents receive polished marketing copy automatically.

---

### Phase 5 — Compatibility Scoring Engine
**Scope:** Build the `listing_compatibility_scores` table and the scoring algorithm from Section 12. Critically, this phase can immediately leverage already-existing structured Seller data (`flood_zone_code`, `association_fee_amount`, `year_built`, `condition_prop`) as matching inputs even before Phase 1 adds the corresponding buyer-side tolerance fields.  
**Estimated effort:** High  
**Deliverables:**
- `listing_compatibility_scores` table (Section 10.7)
- Background job to compute compatibility scores when a new buyer/tenant listing is saved
- Score display on bid comparison views (colored badge + dimension breakdown)
- AI-generated match explanation (2–3 sentences per Section 12.3)
- Deal-breaker flag display

**Value:** Agents can instantly see which buyer/tenant listings have the strongest compatibility with a given property.

---

### Phase 6 — Automated Match Alerts & Marketing Campaign Outputs
**Scope:** Trigger notifications and export marketing-ready copy when compatibility scores exceed thresholds.  
**Estimated effort:** Medium  
**Deliverables:**
- Match alert notification system (email/in-app) when new listing scores >75 compatibility with existing demand profiles
- Social media caption pack generator (per listing, per archetype)
- Email subject line generator (A/B variants)
- Buyer Brief PDF / Tenant Brief PDF export (formatted preference summary for inter-agent sharing)

**Value:** Proactive buyer-seller introductions without manual search.

---

### Phase 7 — External Data Integrations & Advanced Intelligence
**Scope:** Integrate third-party data sources to enrich DNA profiles with authoritative data.  
**Estimated effort:** High  
**Deliverables:**
- Walk Score API → Walk/Transit/Bike scores per listing
- School Ratings API (GreatSchools or similar) → school quality score per listing
- Drive-Time API (Google Maps or HERE) → commute polygon for buyer/tenant commute destination matching (replaces ZIP-code approximation)
- FEMA Flood Map Service → auto-populate `flood_zone_code` from address (cross-validates seller-provided value)
- Historical utility cost estimates (EIA regional data or Arcadia API) → estimate when seller doesn't fill S-01

**Value:** DNA profiles become authoritative data products, not just self-reported claims.

---

## 16. Final Recommendations

### Priority 1 — Do First (High Impact, Low Risk)

1. **Add Available Date to Landlord workflow (L-02)** — This is the only Recommended tier field flagged Required. Tenant move-in date matching without a landlord available date is impossible. Immediate fix.

2. **Add Buyer Purchase Purpose / Archetype (Structured, B-02)** — One required select field on the Buyer form unlocks all downstream demand-side segmentation, marketing archetypes, and compatibility scoring.

3. **Add Commute Destination to Buyer and Tenant workflows (B-01, T-01)** — The single most actionable location signal for matching on both the buyer and tenant side. The tenant AI FAQ has no commute question at all. Even a ZIP code entry provides immediate geographic filtering value.

4. **Reinstate Tenant Credit Score with Compliance Framing (T-06)** — The field already exists in commented-out code. A compliance-label update is all that's needed.

5. **Tag existing AI FAQ questions with intelligence categories** — Zero UI change required. A backend update to the answer storage schema (Section 11.3) creates the foundation for the entire intelligence pipeline.

### Priority 2 — Do Next (High Impact, Moderate Effort)

6. **Add Pet Policy structured fields to Landlord (L-03)** — Pet-related mismatches are among the most common early tenancy failures. Three new fields (allowed Y/N, weight limit, deposit) prevent the majority of them, and enable direct matching against the existing pet fields in the tenant pre-screening tab.

7. **Add Seller contingency acceptance fields (S-04, S-05)** — The seller Tax/Legal/HOA tab already captures rich structured data. Adding inspection and appraisal contingency acceptance to Seller Terms enables direct compatibility matching against the corresponding buyer fields (B-03, B-04).

8. **Add HOA Acceptance + Max Fee to Buyer (B-06)** — The Seller already provides `has_hoa`, `association_fee_amount`, and `association_fee_frequency` in full structured detail. Adding the buyer's tolerance fields creates an immediate, high-value compatibility matching signal.

9. **Add Flood Zone Tolerance to Buyer (B-08)** — The Seller already provides `flood_zone_code` as a full FEMA-designation select. Adding the buyer's tolerance preference enables immediate automated filtering — no new data collection needed on the supply side.

10. **Add Year Built to Landlord (L-01)** — The Seller form collects year built for four property types. Its absence from the Landlord form is an anomaly. One number field anchors all subsequent condition scoring for rental properties.

### Priority 3 — Plan for Later (Strategic, Higher Effort)

11. **Build the DNA Completeness Score (Phase 3)** — A visible profile completeness indicator motivates users to fill optional fields organically, improving data quality without coercion.

12. **Implement the Compatibility Scoring Engine (Phase 5)** — The data foundation from Phases 1–4 is a prerequisite. Phase 5 transforms the platform from a listing marketplace into an intelligent matching engine. Note that the Seller's existing Tax/Legal/HOA fields (`flood_zone_code`, `association_fee_amount`, `has_hoa`, `condition_prop`) can immediately serve as supply-side inputs even before Phase 1 adds buyer-side tolerance fields.

13. **Build the AI Narrative Generation pipeline (Phase 4)** — Once DNA profiles are computed and AI answers are tagged, LLM-generated copy can be produced at listing-save time. Directly reduces agent workload and improves listing quality.

14. **Plan the External Data Integration roadmap (Phase 7)** — Walk Scores, school ratings, and drive-time polygons make compatibility scores defensible and trustworthy to sophisticated buyers and investors.

### Architectural Cautions

- **Do not store buyer/tenant demographic characteristics** (age, race, religion, familial status, disability status) in any field, tag, or computed profile. The archetype system uses only behavioral and use-case signals.
- **Seller motivation and landlord income requirements must be agent-visible only** — never displayed on public listing cards or included in API responses accessible to opposing parties.
- **Phase the AI integration carefully** — deploy Fair Housing word-filter before AI narrative generation goes live. A single AI hallucination producing discriminatory copy in a listing description creates serious legal exposure.
- **The Seller Tax/Legal/HOA tab is the most data-rich structured intelligence source on the platform** — its comprehensive HOA, flood zone, CDD, parcel, and assessment data should be the first inputs fed into the compatibility scoring engine on the supply side.
- **EAV meta keys scale horizontally** — the existing `saveMeta`/`loadDraft` pattern is well-suited to the optional enrichment fields recommended in this audit. New optional fields should default to EAV rather than native columns unless they are queried frequently in listing search/filter operations.

---

*End of Phase 1 Architecture Audit*  
*Document: `docs/PROPERTY_DNA_BUYER_TENANT_DNA_PHASE_1_AUDIT.md`*  
*Generated: May 27, 2026*  
*Constraint verified: No application code was modified in producing this document.*
