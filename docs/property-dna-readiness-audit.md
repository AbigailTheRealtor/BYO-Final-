# Property DNA Readiness Audit

**Audit Date:** 2026-06-23
**Auditor:** Task Agent (read-only; no code changes made)
**Scope:** Full readiness assessment covering all seven listing types, Location DNA outputs, Property DNA services, Ask AI property intelligence questions, commercial DNA inputs, buyer/tenant matching, and Target Market Intelligence. Incorporates prior audit documents without re-auditing already-closed gaps.

**Prior audits incorporated:**
- `PROPERTY_DNA_PHASE_G_READINESS_AUDIT.md` — Phase G post-audit (2026-05-28)
- `PROPERTY_DNA_MASTER_AUDIT_AND_GAP_ANALYSIS.md` — Full master audit (2026-06-01)
- `PROPERTY_DNA_RESERVED_FIELDS_READINESS_AUDIT.md` — Reserved field classification (2026-05-29)
- `PROPERTY_INTELLIGENCE_PLATFORM_AUDIT.md` — Consolidated platform audit (2026-06-06)
- `BUYER_TENANT_DNA_COMPATIBILITY_AUDIT.md` — Buyer/tenant compatibility audit
- `LOCATION_DNA_AUDIT.md` — Location DNA pipeline audit

---

## Table of Contents

1. [Property Data Inputs by Listing Type](#1-property-data-inputs-by-listing-type)
2. [Location DNA Outputs Available](#2-location-dna-outputs-available)
3. [Property DNA and Dna Service Status](#3-property-dna-and-dna-service-status)
4. [Target Market Intelligence Readiness](#4-target-market-intelligence-readiness)
5. [Commercial Property DNA Readiness](#5-commercial-property-dna-readiness)
6. [Buyer/Tenant Lifestyle Matching Readiness](#6-buyertenant-lifestyle-matching-readiness)
7. [Ask AI Readiness for Property Intelligence Questions](#7-ask-ai-readiness-for-property-intelligence-questions)
8. [Phase 1 Recommendation](#8-phase-1-recommendation)
9. [Final Verdict](#9-final-verdict)

---

## 1. Property Data Inputs by Listing Type

The platform stores all seven conceptual listing types across two supply-side models differentiated by `property_type` EAV meta. The seven types and their model homes are:

| Listing Type | Model | Table |
|---|---|---|
| Residential Sale | `PropertyAuction` (seller) | `seller_agent_auctions` + `seller_agent_auction_metas` |
| Income Properties | `PropertyAuction` (seller) | same as above, `property_type = Income Property` |
| Commercial Sale | `PropertyAuction` (seller) | same as above, `property_type = Commercial` |
| Business Opportunity | `PropertyAuction` (seller) | same as above, `property_type = Business Opportunity` |
| Vacant Land | `PropertyAuction` (seller) | same as above, `property_type = Vacant Land` |
| Residential Rental | `LandlordAuction` (landlord) | `landlord_agent_auctions` + `landlord_agent_auction_metas` |
| Commercial Lease/Rental | `LandlordAuction` (landlord) | same as above, `property_type = Commercial Property` |

Source: `PropertyDnaGenerator::DIMENSION_SLOTS`, `AskAiContextBuilderService::CANONICAL_SOURCE_MAP`.

---

### 1.1 Residential Sale (Seller / PropertyAuction)

| Data Category | Fields Available | Source | Status |
|---|---|---|---|
| Beds / Baths | bedrooms, bathrooms | EAV + native fallback | ✓ |
| Square Footage | minimum_heated_square, heated_square_footage | EAV | ✓ |
| Lot Size | total_acreage, min_acreage, lot_dimensions | EAV | ✓ |
| Year Built | year_built | EAV | ✓ |
| Property Type | property_type | EAV | ✓ |
| Pool | pool_needed, pool_type | EAV | ✓ |
| Garage / Parking | garage_needed, garage_parking_spaces, carport_needed | EAV | ✓ |
| Amenities | interior_features, appliances, building_features | EAV (JSON multiselect) | ✓ |
| Waterfront | waterfront, waterfront_feet, water_access, water_view | EAV | ✓ |
| HOA | has_hoa, association_fee_amount, association_fee_frequency, association_name, association_fee_includes | EAV | ✓ |
| CDD / Assessments | has_cdd, annual_cdd_fee, has_special_assessments, special_assessment_amount | EAV | ✓ |
| Zoning | zoning | EAV | ✓ |
| Flood Zone | flood_zone_code, flood_zone_panel, flood_zone_date, flood_insurance_required | EAV | ✓ |
| Tax / Legal | annual_property_taxes, parcel_id, tax_year, legal_description | EAV | ✓ |
| Structural | roof_type, exterior_construction, foundation, heating_and_fuel, air_conditioning | EAV | ✓ |
| Utilities | utilities, water, sewer | EAV | ✓ |
| Transaction | sale_provision, offered_financing, occupant_status, target_closing_date | EAV | ✓ |
| Seller Credit | seller_contribution_credit_offered, seller_contribution_amount_details | EAV | ✓ |
| Pets / Restrictions | pets, number_of_pets, weight_of_pets, pet_restrictions, leasing_restrictions | EAV | ✓ |
| Furnishing | building_features (contains furnished values) | EAV | ✓ |
| Video Tour | EAV meta (has_video_tour dimension in PropertyDnaGenerator) | EAV | ✓ |

**Summary:** Residential sale inputs are comprehensive. All major DNA dimension categories are covered. No critical gaps.

---

### 1.2 Residential Rental (Landlord / LandlordAuction)

| Data Category | Fields Available | Source | Status |
|---|---|---|---|
| Beds / Baths | bedrooms, bathrooms | EAV | ✓ |
| Square Footage | minimum_heated_square, heated_square_footage, heated_square | EAV | ✓ |
| Year Built | year_built | EAV | ✓ |
| Property Type | property_type | EAV | ✓ |
| Amenities | property_items, appliances, interior_features, building_features | EAV (JSON) | ✓ |
| Rent Amount | desired_rental_amount, starting_rent, lease_now_price | EAV (cascade) | ✓ |
| Lease Terms | desired_lease_length, lease_now_price | EAV | ✓ |
| HOA | has_hoa, association_fee_amount, association_fee_frequency | EAV | ✓ |
| Pets | pets (EAV key), pet_policy | EAV | ✓ |
| Utilities | utilities | EAV | ✓ |
| Pool | via property_items / building_features | EAV (indirect) | ⚠ Indirect |
| Garage / Parking | via property_items / building_features | EAV (indirect) | ⚠ Indirect |
| Waterfront | water_view, view_preference | EAV | ⚠ View preference only; no native waterfront column confirmed for landlord |
| Number of Units | number_of_unit | EAV | ✓ |
| Condition | condition_prop, other_property_condition | EAV | ✓ |
| Flood Zone | flood_zone_code et al. | EAV | ✓ |
| Zoning | Not explicitly in CANONICAL_SOURCE_MAP for landlord | — | ✗ Gap |
| Commercial subtype fields | total_sqft, ceiling_height, use-type | — | ✗ Gap (see §1.7) |

**Summary:** Core rental fields are fully covered. Pool and parking are collectible but only as indirect signals through `property_items` / `building_features` JSON rather than dedicated boolean fields. Zoning not documented for landlord in the canonical source map.

---

### 1.3 Income Properties (Seller / PropertyAuction, property_type = Income Property)

All Residential Sale fields apply, plus:

| Data Category | Fields Available | Source | Status |
|---|---|---|---|
| Gross Annual Income | gross_annual_income | EAV | ✓ |
| Annual Operating Expenses | annual_operating_expenses | EAV | ✓ |
| Annual NOI / Net Income | minimum_annual_net_income | EAV | ✓ |
| Cap Rate | minimum_cap_rate | EAV | ✓ |
| Total Units | unit_number | EAV | ✓ |
| Total Buildings | unit_buildings | EAV | ✓ |
| Unit Mix / Type Configurations | unit_type_configurations | EAV | ✓ |
| Rent Roll Available | rent_roll_available | EAV | ✓ |
| Operating Statement Available | operating_statement_available | EAV | ✓ |
| Occupancy Requirement | assumable_occupancy_requirement, assumable_occupancy_other | EAV | ✓ |

**Summary:** Income property data inputs are comprehensive. Cap rate, NOI, unit mix, and supporting document availability are all captured.

---

### 1.4 Commercial Sale (Seller / PropertyAuction, property_type = Commercial)

All Residential Sale fields apply, plus:

| Data Category | Fields Available | Source | Status |
|---|---|---|---|
| Building Square Footage | total_square_feet | EAV | ✓ |
| Ceiling Height | ceiling_height | EAV | ✓ |
| Parking Spaces | garage_parking_spaces | EAV | ✓ |
| Price Per Sqft | price_per_sqft | EAV | ✓ |
| Existing Lease Type | existing_lease_type | EAV | ✓ |
| Lease Expiration | lease_expiration | EAV | ✓ |
| Lease Assignable | lease_assignable | EAV | ✓ |
| Zoning | zoning | EAV | ✓ |
| Annual NOI | minimum_annual_net_income | EAV | ✓ |
| Commercial Sub-Type (retail/office/medical/etc.) | Not differentiated beyond `property_type` meta | — | ✗ Gap |
| Traffic Count / Access Analysis | Not collected | — | ✗ Gap |
| ADA / Accessibility Status | Not collected | — | ✗ Gap |

**Summary:** Basic commercial inputs are present. Sub-type differentiation (retail vs. office vs. medical) is not structured beyond the general `property_type` field. No traffic or access data is collected.

---

### 1.5 Business Opportunity (Seller / PropertyAuction, property_type = Business Opportunity)

| Data Category | Fields Available | Source | Status |
|---|---|---|---|
| Business Type | business_type, other_business_type | EAV | ✓ |
| Business Name | business_name | EAV | ✓ |
| Year Established | year_established | EAV | ✓ |
| Annual Revenue | annual_revenue | EAV | ✓ |
| Gross Profit | gross_profit | EAV | ✓ |
| SDE / EBITDA | sde_ebitda | EAV | ✓ |
| Inventory Value | inventory_value | EAV | ✓ |
| FF&E Value | ffe_value | EAV | ✓ |
| Reason for Sale | reason_for_sale, other_reason_for_sale | EAV | ✓ |
| Employee Count | employee_count | EAV | ✓ |
| Financial Statements Available | financial_statements_available | EAV | ✓ |
| Tax Returns Available | tax_returns_available | EAV | ✓ |
| NDA Required | nda_required | EAV | ✓ |
| Business Lease Terms | business_lease_monthly_rent, business_lease_expiration, business_lease_renewal_options, business_lease_assignable, business_lease_additional_terms | EAV | ✓ |
| Licenses | licenses | EAV | ✓ |
| Sale Includes | sale_includes | EAV | ✓ |
| Business Assets | business_assets | EAV | ✓ |
| Electrical Service | electrical_service | EAV | ✓ |
| Business Location Leased | business_location_leased | EAV | ✓ |

**Summary:** Business opportunity inputs are extensive and well-structured. Financial metrics, operational details, and sale structure fields are all captured.

---

### 1.6 Vacant Land (Seller / PropertyAuction, property_type = Vacant Land)

| Data Category | Fields Available | Source | Status |
|---|---|---|---|
| Lot Size / Acreage | total_acreage, min_acreage | EAV | ✓ |
| Lot Dimensions | lot_dimensions | EAV | ✓ |
| Zoning | zoning | EAV | ✓ |
| Current Adjacent Use | current_adjacent_use | EAV | ✓ |
| Current Use | current_use | EAV | ✓ |
| Road Frontage | road_frontage | EAV | ✓ |
| Front Footage | front_footage | EAV | ✓ |
| Road Surface Type | road_surface_type | EAV | ✓ |
| Utilities Available | water_available, sewer_available, electric_available, gas_available, telecom_available | EAV | ✓ |
| Buildable | buildable | EAV | ✓ |
| Easements | easements | EAV | ✓ |
| Fences | fences | EAV | ✓ |
| Vegetation | vegetation | EAV | ✓ |
| Number of Wells / Septics | number_of_wells, number_of_septics | EAV | ✓ |
| Flood Zone | flood_zone_code et al. | EAV | ✓ |
| Waterfront | waterfront, water_access | EAV | ✓ |

**Summary:** Vacant land inputs are comprehensive. All key land characteristics are captured via the EAV system.

---

### 1.7 Commercial Lease/Rental (Landlord / LandlordAuction, property_type = Commercial Property)

All Residential Rental fields apply, but commercial-specific fields are poorly differentiated:

| Data Category | Fields Available | Source | Status |
|---|---|---|---|
| Rent Amount | desired_rental_amount, starting_rent, lease_now_price | EAV | ✓ |
| Lease Terms | desired_lease_length | EAV | ✓ |
| Square Footage | minimum_heated_square, heated_square_footage | EAV | ✓ |
| Building Square Footage | Not mapped for landlord in CANONICAL_SOURCE_MAP | — | ✗ Gap |
| Ceiling Height | Not mapped for landlord | — | ✗ Gap |
| Parking Spaces | Not explicitly mapped (indirect via property_items) | — | ⚠ Indirect |
| Commercial Sub-Type | Not differentiated | — | ✗ Gap |
| Zoning | Not in landlord CANONICAL_SOURCE_MAP | — | ✗ Gap |
| Triple-Net / Modified Gross / Gross Lease Type | Not collected | — | ✗ Gap |
| Traffic Count / Access Analysis | Not collected | — | ✗ Gap |
| Tenant Improvement Allowance | Not collected | — | ✗ Gap |

**Summary:** Commercial lease/rental has the weakest input coverage of all seven types. The landlord form and EAV system were designed for residential rental. Commercial-specific fields (ceiling height, lease type structures, TI allowance, traffic access) are not collected.

---

## 2. Location DNA Outputs Available

The Location DNA pipeline is fully built and tested in isolation across four service phases. All outputs below are accessible **if and only if the pipeline has been invoked for a given listing**. No automatic trigger exists — this is the single most critical gap in the system.

### 2.1 Pipeline Services and Their Outputs

| Service | Phase | Output | Storage |
|---|---|---|---|
| `LocationDnaGeocodeService` | B — Geocode | `geocoded_lat`, `geocoded_lng`, `geocode_status`, `geocode_source`, `geocoded_at` | `property_location_dna` |
| `LocationDnaPoiDistanceService` | C — POI Fetch | 19 POI category rows with distance_miles, status, poi_name, poi_subtype | `property_location_pois` |
| `LocationDnaRankingEngine` | C.5 — Ranking | `ranking_score`, `ranking_reasons_json` per candidate POI | `property_location_pois` |
| `LocationDnaSummaryService` | D — Summary | `summary_json`: nearest_by_category map, 4 thematic blocks, category_counts, geocode block | `property_location_dna.summary_json` |
| `LocationDnaLifestyleScoreService` | 2 — Lifestyle | `lifestyle_json`: 5 scores (0–100), 8 category labels, location narrative | `property_location_dna.lifestyle_json` |

### 2.2 Confirmed Available Outputs

**Lifestyle Scores (5):** coastal_score, walkability_score, convenience_score, commuter_score, family_score — integer 0–100.

**Lifestyle Category Labels (8):** Beach Lovers, Boaters, Families, Retirees, Remote Workers, Commuters, Outdoor Enthusiasts, Convenience Seekers — derived deterministically from scores.

**Location Narrative (1):** A single plain-English sentence summarizing the property's lifestyle profile. Deterministic — no AI.

**POI Thematic Blocks (4, via `LocationDnaIntelligenceContextService`):**
- `coastal_features`: nearest_beach_miles, nearest_beach_access_miles, nearest_boat_ramp_miles, nearest_marina_miles
- `daily_convenience`: nearest_grocery_miles, nearest_pharmacy_miles, nearest_coffee_miles, nearest_restaurant_miles, nearest_top_rated_dining_miles
- `outdoor_recreation`: nearest_park_miles, nearest_dog_park_miles, nearest_golf_course_miles, nearest_waterfront_park_miles
- `transportation`: nearest_transit_miles, nearest_gas_station_miles

**Nearest Highlights (5 canonical keys):** nearest_beach_miles, nearest_marina_miles, nearest_grocery_miles, nearest_park_miles, nearest_transit_miles.

**Ranking Scores / Reasons (per POI):** `ranking_score` (0–100 composite), `ranking_reasons_json` (array of positive/negative signal strings) — available in `property_location_pois` for each stored POI candidate.

**Available Categories / Missing Categories:** Lists of thematic blocks with and without non-null distance values — available in all three integration context services.

### 2.3 POI Category Coverage (19 categories)

| Category | Thematic Block | Lifestyle Score Impact |
|---|---|---|
| beach | coastal | coastal_score (weight 1.0) |
| beach_access | coastal | coastal_score (weight 0.5 fallback) |
| boat_ramp | coastal | — (thematic only) |
| marina | coastal | coastal_score (weight 0.5) |
| grocery_store | daily_convenience | walkability, convenience, family |
| pharmacy | daily_convenience | walkability, convenience |
| coffee_shop | daily_convenience | walkability |
| restaurant | daily_convenience | walkability |
| top_rated_dining | daily_convenience | — (thematic only, v2 addition) |
| park | outdoor_recreation | family_score (weight 1.0) |
| dog_park | outdoor_recreation | family_score (weight 0.6) |
| waterfront_park | outdoor_recreation | family_score (weight 0.5) |
| golf_course | outdoor_recreation | — (narrative gate; Outdoor Enthusiasts label) |
| transit_station | transportation | commuter_score (weight 1.0) |
| gas_station | transportation | commuter_score (weight 0.6) |
| hospital | fetched; not in thematic blocks | — (no score impact) |
| gym / fitness_center | fetched; not in thematic blocks | — (no score impact) |
| shopping_center | fetched; not in thematic blocks | — (no score impact) |
| school | fetched; not in thematic blocks | — (governance-restricted from scoring) |

**Gap:** hospital, gym, fitness_center, shopping_center, and school are fetched and stored in `property_location_pois` but are not wired into any thematic block or lifestyle score. Airport and Downtown/City Center categories were planned (Phase A governance doc) but never added to the service.

### 2.4 Integration Service Layer (Read-Only)

Three read-only integration services wrap `property_location_dna.summary_json` for downstream consumers:

| Service | Purpose | Output Key |
|---|---|---|
| `LocationDnaPropertyContextService` | Basic context block for Property DNA layer | `location_dna` |
| `LocationDnaMarketingContextService` | 4 thematic blocks for marketing pipeline | `marketing_location_context` |
| `LocationDnaIntelligenceContextService` | 4 thematic blocks + nearest_highlights for AI/intelligence consumers | `location_intelligence_context` |

All three return the same six-key status contract (`success`, `status`, `listing_type`, `listing_id`, data_key, `error`) with statuses: `available` / `missing` / `not_generated` / `failed`.

`LocationDnaMarketingContextService` is explicitly deferred from the AI marketing report pipeline (governance block documents a pending hook phase approval). `LocationDnaIntelligenceContextService` feeds `AskAiContextBuilderService` via `PropertyIntelligenceProfileService` — this path is active.

### 2.5 Pipeline Trigger — Wired and Operational

The Location DNA pipeline is automatically triggered on every seller and landlord listing save:

- `PropertyAuctionDnaObserver::saved()` dispatches `ComputeLocationDna::dispatch('seller', $listing->id)`
- `LandlordAuctionDnaObserver::saved()` dispatches `ComputeLocationDna::dispatch('landlord', $listing->id)`
- `ComputeLocationDna` job (`app/Jobs/ComputeLocationDna.php`) delegates to `LocationDnaPipelineRunner::run()`, which chains all four services in order with guard checks between steps.
- `QUEUE_CONNECTION=sync` — both observers and jobs execute synchronously on the request thread, identical to the Property DNA observer pattern.
- An agent-facing manual re-trigger is also available at `POST /agent/location-dna/{listingType}/{listingId}/generate` (`AgentLocationDnaController@generate`).

**Conditional factor:** Pipeline step 2 (POI fetch) calls the Google Places API via `GooglePlacesPoiAdapter`. If the `GOOGLE_MAPS_API_KEY` environment variable is absent or the API call fails, the POI step returns `status=failed` and the pipeline stops, leaving `summary_json` and `lifestyle_json` unpopulated. Listings in environments without a configured Google Places API key will have geocode data only.

### 2.6 Enrichment Services (LDNA Buyer/Tenant Criteria Flow)

A parallel set of Location DNA services serves the buyer/tenant criteria search system rather than the supply-side pipeline:

| Service | Purpose |
|---|---|
| `BoundaryLookupService` | Resolves GeoJSON polygons for city/ZIP/county boundaries from Census TIGERweb (Nominatim fallback) |
| `FloodZoneLookupService` | Resolves flood zone data within a buyer/tenant boundary |
| `SchoolDistrictLookupService` | Resolves school district data within a boundary |
| `PoiDistanceLookupService` | Distance lookups scoped to buyer/tenant preference polygons |
| `CommuteTimeLookupService` | Commute time estimates from commute origin to destinations |
| `LocationDnaEnrichmentRunner` | Fan-out runner for the four enrichment services above |
| `LocationIntelligenceSummaryService` | Stateless formatter converting enrichment payloads to human-readable summary lines |
| `LocationPreferenceAnalyzer` | Stateless formatter converting `location_dna_preferences` arrays to preference summary lines |
| `LocationIntelligenceComposer` | Orchestrates EnrichmentRunner + SummaryService + PreferenceAnalyzer |

These services operate on buyer/tenant `location_dna_preferences` (drawn polygons, radius searches, city/ZIP selections) rather than on a property's physical address. They are distinct from and do not overlap with the supply-side `LocationDnaPipelineRunner`.

---

## 3. Property DNA and Dna Service Status

### 3.1 PropertyDnaGenerator — Supply Side

**Trigger:** `PropertyAuctionDnaObserver` and `LandlordAuctionDnaObserver` fire on every `saved` event. `QUEUE_CONNECTION=sync` — synchronous execution.

**Listing types supported:** `seller` and `landlord` only. `PropertyDnaGenerator::generate()` returns early with a warning log for any other type string.

**29 Dimension Slots mapped:**

| Dimension | Source Field | Notes |
|---|---|---|
| property_type | `property_type` EAV | ✓ |
| property_style | `property_items` EAV | ✓ |
| property_condition | `condition_prop` EAV | ✓ |
| bedrooms | `bedrooms` EAV | ✓ |
| bathrooms | `bathrooms` EAV | ✓ |
| minimum_sqft | `minimum_heated_square` EAV | ✓ |
| total_acreage | `total_acreage` EAV | ✓ |
| has_pool | `pool_needed` EAV → boolean | ✓ |
| has_garage | `garage_needed` EAV → boolean | ✓ |
| has_carport | `carport_needed` EAV → boolean | ✓ |
| has_storage | `storage_space` EAV → boolean | ✓ |
| pets_allowed | `pets` EAV → boolean | ✓ |
| is_55_plus | `leasing_55_plus` EAV | ✓ |
| is_commercial | `property_type` (Commercial match) | ✓ |
| smoking_policy_specified | `smoking_restrictions` EAV | ✓ |
| has_hoa | `has_hoa` EAV | ✓ |
| furnishing_indicator | `building_features` EAV (furnished keywords) | ✓ |
| move_in_timing | `target_closing_date` / `available_date` | ✓ |
| occupant_status | `occupant_status` EAV | ✓ |
| lease_length_flexibility | `desired_lease_length` EAV | ✓ |
| has_lease_option | `interested_lease_option` EAV | ✓ |
| has_lease_purchase | `lease_purchase_price` EAV | ✓ |
| has_seller_financing | `offered_financing` EAV | ✓ |
| has_assumable_loan | `assumable_loan` EAV | ✓ |
| sale_provision_type | `sale_provision` EAV | ✓ |
| offered_financing_types | `offered_financing` EAV | ✓ |
| interested_in_selling | inferred from listing existence | ✓ |
| has_video_tour | `video_tour_link` EAV | ✓ |
| view_preference | `view_preference` EAV | ✓ |

**6 Coverage Scores produced:** `physical_score`, `financial_score`, `flexibility_score`, `occupant_qualification_score`, `marketing_score`, `commercial_score`.

**4 Always-null fields (reserved):** `location_score`, `condition_score`, `legal_score`, `compatibility_score`.

**6 External-API-reserved fields (never populated):** `walk_score`, `transit_score`, `bike_score`, `school_rating`, `flood_zone_verified`, `estimated_monthly_utilities`.

### 3.2 PropertyIntelligenceProfileService

Assembles a unified Property Intelligence Profile from a persisted `PropertyDnaProfile` and `LocationDnaIntelligenceContextService` output. Produces:
- Strength labels, highlight tags, positioning label
- `coverage_blocks` (completeness by group)
- `location_intelligence_context` (written back to `PropertyDnaProfile` row as the one permitted side-effect write)

Called by `AskAiContextBuilderService::buildPayloadReadOnly()`. NOT called on listing save — only invoked on-demand when Ask AI or admin inspection requests the profile.

### 3.3 BuyerTenantDnaGenerator — Demand Side

**Trigger:** `BuyerCriteriaAuctionDnaObserver` and `TenantCriteriaAuctionDnaObserver` fire on `saved`.

**26 Dimension Slots mapped:** property_type_preference, property_style_preference, property_condition_preference, bedroom_preference, bathroom_preference, minimum_sqft_preference, budget, has_preapproval, financing_preference, down_payment_type, has_seller_financing_interest, has_assumable_loan_interest, has_lease_option_interest, has_lease_purchase_interest, has_pets, is_55_plus_preference, pool_preference, garage_preference, carport_preference, desired_lease_length, occupant_status_preference, sale_provision_interest, view_preference, timeline_flexibility, smoking_preference_specified, hoa_preference_specified.

**Explicitly skipped dimensions:** `commute_preferences` (no structured source field; `commute_polygon_cache` reserved for future Location DNA Phase 2).

**Post-generation enrichment:** `BuyerAvatarService` (buyer) / `TenantAvatarService` (tenant) add avatar narrative, preference_summary, personality_tags, match_preferences, motivation, readiness_score, confidence_score.

### 3.4 CompatibilityEngine

**8 of 14 dimensions active in Phase H:**
property_type, price_budget, bedrooms, bathrooms, square_footage, features_amenities, parking, budget_flexibility.

**6 dimensions ineligible (Phase H governance decision):**
occupancy (no demand field), furnishing (no demand field), timeline (timeline_flexibility too sparse), lease_term (no supply signal differentiated by landlord vs. seller), hoa_fees (hoa_preference_specified not reliable), location (location_match_score always null).

**`location_match_score`:** Always null. Geospatial radius matching depends on both `commute_polygon_cache` (buyer/tenant, always null) and `property_location_dna.geocoded_lat/lng` (not auto-populated). Blocked until Location DNA trigger exists.

### 3.5 Marketing Intelligence Services

| Service | Status | Connected To AI Report |
|---|---|---|
| `PropertyMarketingContextService` | Built, operational | Yes — feeds PropertyMarketingBriefService |
| `PropertyMarketingBriefService` | Built, operational (9-section, never persisted) | Yes — feeds AI report generator |
| `PropertyMarketingReadinessService` | Built, operational (3 required groups gate) | Yes — gates AI generation |
| `LocationDnaMarketingContextService` | Built, tested | NOT connected — governance deferred |
| `BuyerTenantMarketingContextService` | Built, tested | NOT connected |
| AI report pipeline (4 services) | Built, operational | Self-contained |

---

## 4. Target Market Intelligence Readiness

Target Market Intelligence (TMI) requires the ability to characterize who a property appeals to, why, and from what marketing angles. Assessment per sub-component:

### 4.1 Buyer/Tenant Persona Generation

| Capability | Source | Status |
|---|---|---|
| 11 buyer archetypes | `PropertyDnaGenerator` → `ai_buyer_archetype_tags` | ✓ READY — computed on every listing save |
| 8 tenant archetypes | Same | ✓ READY |
| Buyer archetype alignment (ranked map) | `SellerDnaReportService::buyer_archetype_alignment()` | ✓ READY — admin view renders it; needs seller/agent exposure |
| Demand-side archetype | `BuyerTenantDnaGenerator` → `archetype_label` | ✓ READY |

### 4.2 Lifestyle Appeal

| Capability | Source | Status |
|---|---|---|
| Supply-side lifestyle scores (5 dimensions) | Location DNA `lifestyle_json` | ⚠ CONDITIONAL — data only present if Location DNA trigger built |
| Lifestyle category labels (8 labels) | Location DNA `lifestyle_json` | ⚠ CONDITIONAL |
| Location narrative | Location DNA `lifestyle_json` | ⚠ CONDITIONAL |
| Demand-side lifestyle tags | `BuyerTenantDnaGenerator` → `lifestyle_tags` | ✓ READY |
| Demand-side deal-breaker flags | `BuyerTenantDnaGenerator` → `deal_breaker_flags` | ✓ READY |

### 4.3 Marketing Angles

| Capability | Source | Status |
|---|---|---|
| Marketing hook pairs (trait/value) | `PropertyDnaGenerator` → `ai_marketing_hooks` | ✓ READY |
| 9-section deterministic brief (Phase R) | `PropertyMarketingBriefService::build()` | ✓ READY — on-demand, no persistence needed |
| AI-authored marketing report sections (5) | `marketing_reports` | ✓ READY — for listings that have generated a report |
| Location-enhanced marketing context | `LocationDnaMarketingContextService` | ⚠ CONDITIONAL — data source dependent + pipeline not connected to AI report |

### 4.4 Audience Fit

| Capability | Source | Status |
|---|---|---|
| Property positioning label | `PropertyIntelligenceProfileService` | ✓ READY |
| Strength labels / highlight tags | `PropertyIntelligenceProfileService` | ✓ READY |
| `suited_audience` Ask AI question type | `AskAiQuestionClassifierService` | ✓ READY — supported question type with full context |

### 4.5 Marketability Score / Completeness

| Capability | Source | Status |
|---|---|---|
| `overall_dna_completeness` (% of 29 dimensions) | `PropertyDnaGenerator` | ✓ READY |
| 6 coverage scores | `PropertyDnaGenerator` | ✓ READY |
| Missing information checklist | `PropertyMarketingBriefService` → `missing_information_checklist` | ✓ READY |
| `seller_landlord_questions` (pre-written clarifying questions) | `PropertyMarketingBriefService` | ✓ READY |
| Formal "marketability score" metric | Not built | ✗ Missing |

### 4.6 Strengths and Objections

| Capability | Source | Status |
|---|---|---|
| Strength labels | `PropertyIntelligenceProfileService::STRENGTH_MAP` | ✓ READY |
| Highlight tags | `PropertyIntelligenceProfileService::HIGHLIGHT_MAP` | ✓ READY |
| Buyer objections | No dedicated service, pipeline, or structured output | ✗ Missing |
| Objection-handling marketing copy | Not built | ✗ Missing |

---

## 5. Commercial Property DNA Readiness

### 5.1 Retail / Office / Medical / Restaurant / Warehouse / Industrial Appeal

**Status: NOT BUILT.** Commercial sub-type appeal analysis does not exist. `PropertyDnaGenerator` maps `is_commercial = true` when `property_type` indicates commercial, and the `commercial_score` dimension is computed, but there is no sub-type-specific scoring, positioning, or intelligence layer. The generator does not differentiate between retail, office, medical, restaurant, warehouse, or industrial property types.

Fields collected that could support sub-type analysis in the future: `building_features` (JSON multiselect includes commercial-relevant values), `existing_lease_type`, `ceiling_height`, `total_square_feet`, `zoning`, `current_use`.

### 5.2 Traffic / Access Fit

**Status: NOT BUILT.** No traffic count data is collected. No access/visibility analysis exists. The Location DNA pipeline provides nearby transit station distances but does not address vehicular traffic, ingress/egress, or commercial visibility — these require entirely different data sources (traffic APIs, address geocoding with street-network analysis).

### 5.3 Lease / Sale Positioning

**Status: PARTIAL.** The following fields are available:
- `existing_lease_type` (seller) → maps to CompatibilityEngine `existing_lease_type` signal
- `lease_expiration`, `lease_assignable` → collected
- Commercial `commercial_score` dimension in DNA

However, no service synthesizes these into a "lease positioning" or "sale positioning" narrative or recommendation. The marketing brief and AI report do not contain a commercial-specific lease positioning section.

### 5.4 Commercial DNA Summary

| Capability | Status |
|---|---|
| Basic commercial field collection (sqft, ceiling, lease type, NOI, cap rate) | ✓ Seller only |
| Commercial sub-type differentiation (retail vs. office vs. medical etc.) | ✗ Not built |
| Traffic/access scoring | ✗ Not built |
| Commercial DNA positioning intelligence layer | ✗ Not built |
| Commercial lease/rental (landlord) EAV field coverage | ⚠ Significant gaps (see §1.7) |
| `commercial_score` dimension in PropertyDnaGenerator | ✓ Computed |
| `is_commercial` archetype signal | ✓ Present in archetype tags |

---

## 6. Buyer/Tenant Lifestyle Matching Readiness

### 6.1 Commute Preferences

| Capability | Field Available? | Matching Operational? |
|---|---|---|
| Commute destination ZIP | `commute_destination_zip` (buyer EAV) | ✓ Collected — ✗ Not used in matching |
| Max commute minutes | `max_commute_minutes` (buyer EAV) | ✓ Collected — ✗ Not used in matching |
| Commute mode | `commute_mode` (buyer EAV) | ✓ Collected — ✗ Not used in matching |
| Geospatial radius matching | `commute_polygon_cache` — always null | ✗ Not implemented |
| `location_match_score` on CompatibilityScore | Always null | ✗ Not implemented |

**Summary:** Commute preference fields are collected for buyer listings. Zero commute-based matching is operational. A full Location DNA Phase 2 (polygon or point-radius computation) is required before these fields become useful for matching.

### 6.2 Amenity Preferences

| Capability | Status |
|---|---|
| Pool preference | ✓ Mapped in BuyerTenantDnaGenerator (pool_preference); CompatibilityEngine `features_amenities` dimension active |
| Garage preference | ✓ Mapped; active in CompatibilityEngine |
| Carport preference | ✓ Mapped; active in CompatibilityEngine |
| Non-negotiable amenities | `non_negotiable_amenities` collected (buyer EAV); not wired to CompatibilityEngine |
| General features matching | ✓ `features_amenities` dimension active in CompatibilityEngine (8 active dimensions) |

### 6.3 Beach / Golf / Boating Preferences

| Capability | Status |
|---|---|
| Beach preference field | ✗ Not collected in buyer/tenant listing forms |
| Golf preference field | ✗ Not collected |
| Boating / marina preference field | ✗ Not collected |
| View preference | `view_preference` EAV collected; mapped as `view_preference` dimension in BuyerTenantDnaGenerator |
| Beach/golf/boating as matching signal | ✗ No CompatibilityEngine dimension covers these |

**Note:** `view_preference` captures scenic preference (lake/ocean view, golf course view etc.) but is a free-form or select field — it is mapped in the BuyerTenantDnaGenerator as a dimension but is not used in any active CompatibilityEngine dimension. Beach, golf, and boating as explicit buyer preference fields are not collected.

### 6.4 School Preferences

| Capability | Status |
|---|---|
| School preference field | ✗ Not collected (governance-restricted) |
| School rating input | `school_rating` reserved column on `property_dna_profiles` — External API Required; never populated |
| School distance from Location DNA | `school` POI fetched and stored in `property_location_pois` but not in any thematic block, lifestyle score, or intelligence context |

### 6.5 Walkability / Lifestyle Score Matching

| Capability | Status |
|---|---|
| Supply-side walkability_score | ✓ Computed by LocationDnaLifestyleScoreService — CONDITIONAL on trigger |
| Demand-side walkability preference | ✗ No buyer/tenant form field for walkability preference |
| Lifestyle score cross-matching | ✗ No matching logic between supply lifestyle scores and demand preferences |

### 6.6 Custom-Location Distance Preferences

| Capability | Status |
|---|---|
| Custom POI distance preference | ✗ No buyer/tenant field for "I want to be within X miles of Y" |
| Distance preference matching logic | ✗ Not built |

### 6.7 Operational Matching Summary (What Works Today)

**Active CompatibilityEngine dimensions (8 of 14):**

| Dimension | Supply Source | Demand Source |
|---|---|---|
| property_type | `property_type` DNA dimension | `property_type_preference` |
| price_budget | `financial_score` signal | `budget` dimension |
| bedrooms | `bedrooms` DNA dimension | `bedroom_preference` |
| bathrooms | `bathrooms` DNA dimension | `bathroom_preference` |
| square_footage | `minimum_sqft` DNA dimension | `minimum_sqft_preference` |
| features_amenities | pool/garage/carport tags | pool/garage/carport preferences |
| parking | `has_garage` / `has_carport` tags | `garage_preference` / `carport_preference` |
| budget_flexibility | financial signals | budget + financing dimensions |

**Ineligible dimensions (6 — Phase H governance decision):**
occupancy, furnishing, timeline, lease_term, hoa_fees, location.

---

## 7. Ask AI Readiness for Property Intelligence Questions

The task specifies five key property intelligence questions (the audit spec labels them "six" but lists five distinct categories). All five are evaluated here, along with one additional question type that is operational:

### 7.1 Best-For Persona

**Question examples:** "Who is this property best for?", "Who is this ideal for?", "What type of buyer is this suited for?"

**Ask AI type:** `suited_audience`

**Status: SUPPORTED.** The `suited_audience` question type is a dedicated classifier section in `AskAiQuestionClassifierService`. Keywords include: 'suited for', 'suitable for', 'ideal for', 'ideal buyer', 'ideal tenant', 'target audience', 'best fit for', 'good fit for', 'type of buyer', 'type of tenant', 'lifestyle', 'best suited for', 'who would enjoy'.

**Context available to the AI:** `PropertyIntelligenceProfileService` output (archetype tags, marketing hooks, positioning label, strength labels, highlight tags) + location_intelligence_context (when Location DNA has run). Governance restriction: Must NOT reference demographics, protected classes, neighborhood composition. Bounded to property features and transactional signals only.

**Gap:** No dedicated buyer-objections counterpart. Persona answers are feature-driven; no structured "buyer fit score" or per-archetype scoring is surfaced through this path.

### 7.2 Lifestyle Fit

**Question examples:** "What lifestyle does this property support?", "What is this property's lifestyle profile?"

**Ask AI type:** `suited_audience` (via 'lifestyle' keyword)

**Status: SUPPORTED with conditional depth.** 'lifestyle' is a keyword in the `suited_audience` section. When Location DNA has been generated, `location_intelligence_context` provides the 5 lifestyle scores and 8 category labels to the AI. When Location DNA has NOT been generated, the AI must answer from property feature signals alone (archetype tags, marketing hooks) with no lifestyle score data.

**Gap:** Lifestyle score quality depends on the Google Places API being configured and the POI pipeline completing successfully. For listings where the pipeline ran but POI data was unavailable, the AI answers from property feature signals alone.

### 7.3 Marketing Angles

**Question examples:** "What are the best marketing angles?", "How should this be marketed?", "Write a listing description."

**Ask AI type:** `marketing_angles`

**Status: FULLY SUPPORTED.** Dedicated classifier section with keywords: 'marketing angle', 'marketing strategy', 'how to market', 'best way to market', 'listing description', 'tagline', 'ad copy', 'marketing idea', 'listing pitch', 'positioning for this'.

**Context available:** Full listing facts context + DNA hooks. The AI marketing report pipeline (separate, admin-triggered) also produces 5 structured sections for listings that have gone through the full pipeline.

### 7.4 Location Advantages

**Question examples:** "What are the location advantages?", "What is the neighborhood like?", "Tell me about the area."

**Ask AI type:** `property_standout` (neighborhood/area keywords route here)

**Status: SUPPORTED with conditional depth.** Keywords 'what is the neighborhood like', 'neighborhood like', 'what is the area like', 'about the area', 'surrounding area', 'local area' are all in `property_standout`. This type is critical because `property_standout` explicitly includes `location_intelligence` in its context payload (comment in classifier: "Must live here (not listing_facts) so location_intelligence payload is included").

**Gap:** Quality is conditional on the Google Places API being configured so that POI data is available. For listings whose pipeline ran but POI fetch failed, Ask AI can speak only to address-level location (city, state) without POI distances or lifestyle scores.

### 7.5 Buyer Objections

**Question examples:** "What objections might buyers have?", "What are the weaknesses of this property?", "What might turn buyers off?"

**Ask AI type:** No dedicated type exists.

**Status: NOT DIRECTLY SUPPORTED.** There is no `buyer_objections` question type in the classifier. Questions phrased around objections would likely route to `property_standout` (via 'what makes it' / 'stand out'), `marketing_angles` (via 'selling points'), or `missing_data` (via 'what's missing'). None of these types are primed with objection-handling context or specific instructions to address buyer hesitations.

**No structured objection data exists anywhere in the DNA pipeline.** The Phase R brief produces a `missing_information_checklist` (empty fields that could be objections) but this is a different concept. There is no "anticipated objections" or "common hesitations" field in any service.

### 7.6 Ask AI Summary

| Question | Classifier Type | Status | Notes |
|---|---|---|---|
| Best-for persona | `suited_audience` | ✓ SUPPORTED | Feature-bounded; no demographic inference |
| Lifestyle fit | `suited_audience` | ⚠ CONDITIONAL | Full depth requires Google Places API / POI data |
| Marketing angles | `marketing_angles` | ✓ FULLY SUPPORTED | — |
| Location advantages | `property_standout` | ⚠ CONDITIONAL | Full depth requires Google Places API / POI data |
| Buyer objections | None | ✗ NOT SUPPORTED | No dedicated type, no structured output |

---

## 8. Phase 1 Recommendation

Phase 1 is defined as the scope of features that can be built now using existing data and services, without requiring schema changes, new external API integrations, or new pipeline infrastructure.

### 8.1 Property DNA Phase 1 — Included

These are ready to ship with new Blade views and routes only. No new service logic is required.

**Surface existing DNA data to sellers and agents:**
1. Seller listing intelligence page (`/seller/listings/{id}/intelligence` or injected into existing dashboard). Renders: `overall_dna_completeness` progress bar, 6 coverage scores, archetype tags via `SellerDnaReportService::buyer_archetype_alignment()`, marketing hooks as bullet list, property positioning label, strength labels.
2. Seller "missing information" action panel: read `missing_information_checklist` from `PropertyMarketingBriefService::build()` and render as guided checklist on seller listing edit page.
3. Agent marketing brief navigation link: the agent route `/agent/property-dna/{profile}/marketing-brief-review` already exists. Add a navigation link from the listing dashboard.
4. Published AI report delivery: for listings with a `published` AI marketing report, add a conditional block to the public listing page rendering the 4 non-internal sections.
5. Consumer compatibility report template: create `resources/views/consumer/compatibility_report.blade.php` — the controller, privacy filter, and BYA pipeline are already built.

### 8.2 Property DNA Phase 1 — Excluded / Deferred

| Feature | Reason for Deferral |
|---|---|
| Lifestyle scores on listing pages | Requires Google Places API configured and POI pipeline succeeding |
| Location intelligence in AI report | Requires governance approval for LocationDnaMarketingContextService hook |
| Commercial sub-type DNA analysis | Requires new service layer and field collection |
| Commercial lease/rental form improvements | Requires form and EAV schema changes |
| Buyer objections feature | Requires new classifier type + structured output pipeline |
| `location_match_score` matching | Requires Location DNA Phase 2 + geospatial computation |
| Reserved field population (walk_score, school_rating, etc.) | Requires external API integrations |

### 8.3 Target Market Intelligence Phase 1 — Included

These are ready using existing DNA data:
1. Buyer archetype alignment display (which of 11 archetypes this listing suits) — `SellerDnaReportService` output, admin-only today.
2. Property positioning label and strength highlights — `PropertyIntelligenceProfileService` output.
3. Marketing hooks as bullet-point marketing angle suggestions — `property_dna_profiles.ai_marketing_hooks`.
4. `suited_audience` Ask AI question type for persona and lifestyle questions — already operational.
5. `marketing_angles` Ask AI question type — already operational.

**Deferred:** Lifestyle-based TMI with POI depth (requires Google Places API configured for pipeline to complete), buyer objection analysis (no structured output exists), formal marketability score metric.

### 8.4 Buyer/Tenant Lifestyle Matching Phase 1 — Included

These are ready using existing compatibility scores:
1. Consumer compatibility report Blade template — controller exists; template missing.
2. 8-dimension compatibility score display per buyer-seller or tenant-landlord pair.
3. Compatibility narrative display.
4. Buyer/tenant archetype and avatar display (for admin and consumer paths).

**Deferred:** All geospatial/commute-based matching (requires Location DNA Phase 2 polygon computation for commute radius; supply-side geocoding already fires on save), beach/golf/boating preference matching (requires new form fields + new CompatibilityEngine dimension), lifestyle score cross-matching (requires new demand-side preference fields to match against supply-side scores).

### 8.5 Required Follow-Up Tasks (in dependency order)

| Priority | Task | Dependency |
|---|---|---|
| 1 | Wire Location DNA context into AI marketing report (approve deferred governance hook) | Google Places API must be configured |
| 2 | Add buyer objections classifier type and structured output in Ask AI | Independent |
| 3 | Build consumer compatibility report Blade template | Independent |
| 4 | Surface Property DNA data in seller/agent views (Phase 1 scope above) | Independent |
| 5 | Commercial lease/rental form improvements for landlord EAV gaps | Independent |
| 6 | Commute polygon / location_match_score implementation (Location DNA Phase 2) | Independent (supply-side geocoding already fires) |
| 7 | Activate 5 ineligible CompatibilityEngine dimensions (occupancy, furnishing, timeline, lease_term, hoa_fees) | Requires new form fields |

---

## 9. Final Verdict

```
PASS WITH GAPS
```

**Rationale:**

**What is ready now:**
- Property DNA generation (seller + landlord) is fully operational, fires on every listing save, and produces 29 dimension values, 6 coverage scores, archetype tags, and marketing hooks.
- Location DNA pipeline is fully wired: `ComputeLocationDna` job dispatched synchronously by both observer classes on every listing save; `LocationDnaPipelineRunner` chains all four services with guard checks.
- Demand DNA generation (buyer + tenant) is fully operational. 8-dimension compatibility scoring is active.
- The Ask AI pipeline supports four of five target property intelligence question types (marketing angles, best-for persona, lifestyle fit, location advantages). Depth for the latter three is conditional on the Google Places API being configured so POI data is available.
- Business opportunity, income property, and vacant land input fields are comprehensive.
- Target Market Intelligence personas (archetype tags, buyer alignment, marketing hooks) are already computed and storable; they need only view-layer exposure.
- All seven conceptual listing types have at least basic field coverage in the EAV system.
- A second Location DNA layer (enrichment services for buyer/tenant criteria boundaries) is also fully built: `BoundaryLookupService`, `LocationDnaEnrichmentRunner`, `LocationIntelligenceComposer`, `LocationPreferenceAnalyzer`, and `LocationIntelligenceSummaryService`.

**Critical gaps that block Phase 1 features:**
1. **No buyer objections support.** There is no question classifier type, no structured output, and no service that addresses what objections or hesitations prospective buyers might have.
2. **Commercial lease/rental (landlord) has significant EAV gaps.** Ceiling height, zoning, building sqft, and lease structure fields are not collected for the landlord form variant.
3. **Consumer compatibility report Blade template is missing.** The backend controller, privacy filter, and BYA pipeline are built; the template alone is absent.
4. **Location DNA POI depth is conditional on Google Places API.** The trigger fires, but if `GOOGLE_MAPS_API_KEY` is not configured, the pipeline stops at geocode and leaves `summary_json` and `lifestyle_json` empty.

**What should be deferred:**
- Location DNA context in AI marketing report: requires a separately approved governance hook phase.
- Commercial sub-type intelligence analysis (retail vs. office vs. medical): requires new service layer.
- Traffic/access fit analysis: requires external data source.
- Beach/golf/boating preference matching: requires new buyer/tenant form fields and a new CompatibilityEngine dimension.
- External API reserved fields (walk_score, school_rating, flood_zone_verified): require third-party integrations beyond Google Places.
- Commute polygon / location_match_score: requires Location DNA Phase 2 polygon computation for geospatial radius matching.

This report becomes the authoritative blueprint for Property DNA Phase 1, Target Market Intelligence Phase 1, and Buyer/Tenant Lifestyle Matching Phase 1 build planning.
