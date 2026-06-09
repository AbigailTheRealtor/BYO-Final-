# Ask AI Complete Source Map
**Generated:** 2026-06-09  
**Scope:** All four listing roles — seller, buyer, landlord, tenant  
**Purpose:** Authoritative inventory of every approved Ask AI source: native columns, EAV meta keys, FAQ keys, and intelligence outputs.

---

## Overview

| Source Type | Count | Roles |
|---|---|---|
| Listing model fields (`listing.*`) | 46 | seller, buyer, landlord, tenant |
| FAQ keys (`faq_answers.*`) | 168 | seller, buyer, landlord, tenant |
| Property DNA intelligence | 6 fields | seller, landlord |
| Location DNA intelligence | 7 fields | seller, landlord |
| Buyer/Tenant DNA avatar | 9 fields | buyer, tenant |
| Compatibility intelligence | via contract | buyer, tenant |

---

## 1. Listing Model Fields (`listing.*`)

All fields extracted by `AskAiContextBuilderService`, allowed in `AskAiResponseContractService::getListingFactsAllowedPaths()`, registered in `AskAiFieldQuestionRegistryService::listingFieldRegistry()`, and keyword-mapped in `AskAiRunnerV2Service::LISTING_KEY_KEYWORD_MAP`.

### 1.1 Tax

| Field Key | Label | Roles | Source | Context Path | Question Type | Missing-Data Message |
|---|---|---|---|---|---|---|
| `annual_property_taxes` | Annual Property Taxes | seller, landlord | native column | `listing.annual_property_taxes` | listing_facts | "Annual property tax information has not been provided for this listing." |

### 1.2 Price & Financial

| Field Key | Label | Roles | Source | Context Path | Question Type | Missing-Data Message |
|---|---|---|---|---|---|---|
| `asking_price` | Asking / Starting Price | seller | native column | `listing.asking_price` | listing_facts | "Asking price information has not been provided for this listing." |
| `buy_now_price` | Buy Now / Fixed Price | seller | native column | `listing.buy_now_price` | listing_facts | "Buy-now price information has not been provided for this listing." |
| `max_price` | Buyer Maximum Price | buyer | native column | `listing.max_price` | listing_facts | "Buyer maximum price information has not been provided for this listing." |
| `rent_amount` | Monthly Rent | landlord | native column | `listing.rent_amount` | listing_facts | "Monthly rent information has not been provided for this listing." |
| `max_rent` | Tenant Maximum Rent Budget | tenant | native column | `listing.max_rent` | listing_facts | "Tenant maximum rent budget information has not been provided for this listing." |

### 1.3 Property Specifications

| Field Key | Label | Roles | Source | Context Path | Question Type | Missing-Data Message |
|---|---|---|---|---|---|---|
| `bedrooms` | Number of Bedrooms | seller, buyer, landlord, tenant | native column | `listing.bedrooms` | listing_facts | "Bedroom information has not been provided for this listing." |
| `bathrooms` | Number of Bathrooms | seller, buyer, landlord, tenant | native column | `listing.bathrooms` | listing_facts | "Bathroom information has not been provided for this listing." |
| `square_feet` | Square Footage | seller, buyer, landlord, tenant | native column | `listing.square_feet` | listing_facts | "Square footage information has not been provided for this listing." |
| `year_built` | Year Built | seller | native column | `listing.year_built` | listing_facts | "Year built information has not been provided for this listing." |
| `description` | Listing Description | seller, buyer, landlord, tenant | native column | `listing.description` | listing_facts | "Listing description information has not been provided for this listing." |
| `condition_prop` | Property Condition | landlord, tenant | native column | `listing.condition_prop` | listing_facts | "Property condition information has not been provided for this listing." |

### 1.4 Location

| Field Key | Label | Roles | Source | Context Path | Question Type | Missing-Data Message |
|---|---|---|---|---|---|---|
| `address` | Property Address | seller, buyer | native column | `listing.address` | listing_facts | "Property address information has not been provided for this listing." |

### 1.5 Amenities & Features

| Field Key | Label | Roles | Source | Context Path | Question Type | Missing-Data Message |
|---|---|---|---|---|---|---|
| `pool` | Pool | seller, buyer | native column | `listing.pool` | listing_facts | "Pool information has not been provided for this listing." |
| `carport` | Carport | seller, buyer | native column | `listing.carport` | listing_facts | "Carport information has not been provided for this listing." |
| `garage` | Garage | seller, buyer | native column | `listing.garage` | listing_facts | "Garage information has not been provided for this listing." |
| `water_view` | Water View | seller, buyer | native column | `listing.water_view` | listing_facts | "Water view information has not been provided for this listing." |
| `appliances` | Appliances Included | landlord, tenant | native column | `listing.appliances` | listing_facts | "Included appliances information has not been provided for this listing." |

### 1.6 HOA & Community

| Field Key | Label | Roles | Source | Context Path | Question Type | Missing-Data Message |
|---|---|---|---|---|---|---|
| `hoa_association` | HOA / Association | seller | native column | `listing.hoa_association` | listing_facts | "HOA association information has not been provided for this listing." |
| `hoa_fee` | HOA Fee Amount | seller | native column | `listing.hoa_fee` | listing_facts | "HOA fee information has not been provided for this listing." |
| `hoa_fee_requirement` | HOA Fee Requirement | seller, buyer | native column | `listing.hoa_fee_requirement` | listing_facts | "HOA fee requirement information has not been provided for this listing." |
| `hoa_acceptable` | Buyer HOA Acceptability | buyer | native column | `listing.hoa_acceptable` | listing_facts | "Buyer HOA acceptability information has not been provided for this listing." |
| `has_hoa` | Has HOA | landlord | native column | `listing.has_hoa` | listing_facts | "HOA status information has not been provided for this listing." |
| `association_amenities` | Association Amenities | landlord | native column | `listing.association_amenities` | listing_facts | "Association amenities information has not been provided for this listing." |

### 1.7 Pet Policies

| Field Key | Label | Roles | Source | Context Path | Question Type | Missing-Data Message |
|---|---|---|---|---|---|---|
| `pets_allowed` | Pets Allowed | seller, buyer | native column | `listing.pets_allowed` | listing_facts | "Pet policy information has not been provided for this listing." |
| `pet_policy` | Pet Policy | landlord | native column | `listing.pet_policy` | listing_facts | "Pet policy details information has not been provided for this listing." |
| `pet_deposit_fee_rent` | Pet Deposit / Fee / Rent | landlord | native column | `listing.pet_deposit_fee_rent` | listing_facts | "Pet deposit and fee information has not been provided for this listing." |
| `pet_information` | Tenant Pet Information | tenant | native column | `listing.pet_information` | listing_facts | "Tenant pet information has not been provided for this listing." |

### 1.8 Lease & Rental Terms

| Field Key | Label | Roles | Source | Context Path | Question Type | Missing-Data Message |
|---|---|---|---|---|---|---|
| `lease_terms` | Lease Terms (Seller) | seller | native column | `listing.lease_terms` | listing_facts | "Existing lease terms information has not been provided for this listing." |
| `lease_length` | Lease Length | landlord | native column | `listing.lease_length` | listing_facts | "Lease length information has not been provided for this listing." |
| `desired_lease_length` | Tenant Desired Lease Length | tenant | native column | `listing.desired_lease_length` | listing_facts | "Tenant desired lease length information has not been provided for this listing." |
| `renewal_option` | Renewal Option | landlord | native column | `listing.renewal_option` | listing_facts | "Lease renewal option information has not been provided for this listing." |
| `rental_restrictions` | Rental Restrictions | seller | native column | `listing.rental_restrictions` | listing_facts | "Rental restrictions information has not been provided for this listing." |

### 1.9 Utilities & Services

| Field Key | Label | Roles | Source | Context Path | Question Type | Missing-Data Message |
|---|---|---|---|---|---|---|
| `utilities` | Utilities Included | landlord, tenant | native column | `listing.utilities` | listing_facts | "Included utilities information has not been provided for this listing." |
| `tenant_pays` | Tenant Pays (Utilities) | seller, tenant | native column | `listing.tenant_pays` | listing_facts | "Tenant utility responsibility information has not been provided for this listing." |
| `smoking_policy` | Smoking Policy | landlord | native column | `listing.smoking_policy` | listing_facts | "Smoking policy information has not been provided for this listing." |
| `subletting_policy` | Subletting Policy | landlord | native column | `listing.subletting_policy` | listing_facts | "Subletting policy information has not been provided for this listing." |

### 1.10 Parking & Availability

| Field Key | Label | Roles | Source | Context Path | Question Type | Missing-Data Message |
|---|---|---|---|---|---|---|
| `parking_terms` | Parking Terms | landlord | native column | `listing.parking_terms` | listing_facts | "Parking terms information has not been provided for this listing." |
| `available_date` | Available Date | landlord, tenant | native column | `listing.available_date` | listing_facts | "Available date information has not been provided for this listing." |
| `closing_date` | Preferred Closing Date | seller | native column | `listing.closing_date` | listing_facts | "Preferred closing date information has not been provided for this listing." |

### 1.11 Buyer Financials & Criteria

| Field Key | Label | Roles | Source | Context Path | Question Type | Missing-Data Message |
|---|---|---|---|---|---|---|
| `loan_pre_approved` | Loan Pre-Approval Status | buyer | native column | `listing.loan_pre_approved` | listing_facts | "Loan pre-approval information has not been provided for this listing." |
| `financing_type` | Financing Type | buyer | native column | `listing.financing_type` | listing_facts | "Financing type information has not been provided for this listing." |
| `inspection_period` | Inspection Period | buyer | native column | `listing.inspection_period` | listing_facts | "Inspection period information has not been provided for this listing." |
| `closing_days` | Days to Close | buyer | native column | `listing.closing_days` | listing_facts | "Days to close information has not been provided for this listing." |
| `contingencies` | Contingencies | buyer | native column | `listing.contingencies` | listing_facts | "Contingencies information has not been provided for this listing." |

### 1.12 Safety & Disclosure

| Field Key | Label | Roles | Source | Context Path | Question Type | Missing-Data Message |
|---|---|---|---|---|---|---|
| `is_in_flood_zone` | Flood Zone Status | seller | native column | `listing.is_in_flood_zone` | listing_facts | "Flood zone status information has not been provided for this listing." |

---

## 2. FAQ Keys (`faq_answers.*`)

All 168 FAQ keys loaded from `AskAiContextBuilderService::buildFaqAnswers()` and stored inline as `listing_ai_faq` (EAV meta or native column for tenant). Registered in `AskAiFieldQuestionRegistryService::registry()` and keyword-mapped in `AskAiRunnerV2Service::FAQ_KEY_KEYWORD_MAP`.

### 2.1 Seller — Property Condition & Maintenance (12 keys)

| FAQ Key | Label | Roles | Source | keyword_route_status |
|---|---|---|---|---|
| `faq_answers.roof_age_and_condition` | Roof Age & Condition | seller | EAV `listing_ai_faq` | pinned |
| `faq_answers.hvac_system_age` | HVAC System Age | seller | EAV `listing_ai_faq` | pinned |
| `faq_answers.water_heater_age_type` | Water Heater Age & Type | seller | EAV `listing_ai_faq` | pinned |
| `faq_answers.recent_renovations_list` | Recent Renovations | seller | EAV `listing_ai_faq` | pinned |
| `faq_answers.permits_for_renovations` | Renovation Permits | seller | EAV `listing_ai_faq` | pinned |
| `faq_answers.known_defects_issues` | Known Defects / Issues | seller | EAV `listing_ai_faq` | pinned |
| `faq_answers.foundation_type_and_issues` | Foundation Type & Issues | seller | EAV `listing_ai_faq` | pinned |
| `faq_answers.pest_termite_history` | Pest / Termite History | seller | EAV `listing_ai_faq` | pinned |
| `faq_answers.flood_damage_history` | Flood / Water Damage History | seller | EAV `listing_ai_faq` | pinned |
| `faq_answers.mold_issues_history` | Mold History | seller | EAV `listing_ai_faq` | pinned |
| `faq_answers.average_utility_costs` | Average Utility Costs | seller | EAV `listing_ai_faq` | pinned |
| `faq_answers.internet_utility_providers` | Internet & Utility Providers | seller | EAV `listing_ai_faq` | pinned |

### 2.2 Seller — Flexibility & Negotiation (7 keys)

| FAQ Key | Label | Roles | keyword_route_status |
|---|---|---|---|
| `faq_answers.seller_concessions_offered` | Seller Concessions Offered | seller | pinned |
| `faq_answers.closing_timeline_flexibility` | Closing Timeline Flexibility | seller | pinned |
| `faq_answers.items_excluded_from_sale` | Items Excluded from Sale | seller | pinned |
| `faq_answers.as_is_condition` | As-Is Condition | seller | pinned |
| `faq_answers.seller_leaseback_option` | Seller Leaseback Option | seller | pinned |
| `faq_answers.furniture_negotiability` | Furniture Negotiability | seller | pinned |
| `faq_answers.environmental_concerns` | Environmental Concerns | seller | pinned |

### 2.3 Seller — Hidden Selling Points (7 keys)

| FAQ Key | Label | Roles | keyword_route_status |
|---|---|---|---|
| `faq_answers.unique_selling_points` | Unique Selling Points | seller | pinned |
| `faq_answers.seller_favorite_features` | Seller Favorite Features | seller | pinned |
| `faq_answers.seller_motivation_for_selling` | Seller Motivation for Selling | seller | pinned |
| `faq_answers.move_in_ready_status` | Move-In Ready Status | seller | pinned |
| `faq_answers.parking_arrangements` | Parking Arrangements | seller | pinned |
| `faq_answers.storage_space_available` | Storage Space Available | seller | pinned |
| `faq_answers.hoa_community_highlights` | HOA / Community Highlights | seller | pinned |

### 2.4 Seller — Location & Lifestyle (7 keys)

| FAQ Key | Label | Roles | keyword_route_status |
|---|---|---|---|
| `faq_answers.neighborhood_character` | Neighborhood Character | seller | pinned |
| `faq_answers.traffic_or_noise_concerns` | Traffic / Noise Concerns | seller | pinned |
| `faq_answers.planned_nearby_development` | Planned Nearby Development | seller | pinned |
| `faq_answers.commute_options_access` | Commute Options / Access | seller | pinned |
| `faq_answers.natural_light_orientation` | Natural Light & Orientation | seller | pinned |
| `faq_answers.nearby_amenities_description` | Nearby Amenities | seller | pinned |
| `faq_answers.neighborhood_restrictions` | Neighborhood Restrictions | seller | pinned |

### 2.5 Seller Addons — Commercial Income (6 keys)

| FAQ Key | Label | Roles | keyword_route_status |
|---|---|---|---|
| `faq_answers.annual_net_operating_income` | Annual NOI | seller | pinned |
| `faq_answers.current_cap_rate` | Cap Rate | seller | pinned |
| `faq_answers.existing_tenant_lease_terms` | Existing Tenant Lease Terms | seller | pinned |
| `faq_answers.current_occupancy_rate` | Occupancy Rate | seller | pinned |
| `faq_answers.annual_operating_expenses_detail` | Annual Operating Expenses | seller | pinned |
| `faq_answers.value_add_opportunities` | Value-Add Opportunities | seller | pinned |

### 2.6 Seller Addons — Business Opportunity (7 keys)

| FAQ Key | Label | Roles | keyword_route_status |
|---|---|---|---|
| `faq_answers.annual_business_revenue` | Annual Business Revenue | seller | pinned |
| `faq_answers.annual_net_profit` | Annual Net Profit | seller | pinned |
| `faq_answers.business_reason_for_selling` | Business Reason for Selling | seller | pinned |
| `faq_answers.business_employee_count` | Business Employee Count | seller | pinned |
| `faq_answers.seller_training_transition` | Seller Training / Transition | seller | pinned |
| `faq_answers.business_lease_status` | Business Location Lease Status | seller | pinned |
| `faq_answers.inventory_equipment_included` | Inventory / Equipment Included | seller | pinned |

### 2.7 Seller Addons — Vacant Land (6 keys)

| FAQ Key | Label | Roles | keyword_route_status |
|---|---|---|---|
| `faq_answers.land_utilities_availability` | Land Utilities Availability | seller | pinned |
| `faq_answers.land_zoning_permitted_uses` | Land Zoning / Permitted Uses | seller | pinned |
| `faq_answers.land_access_and_road` | Land Road Access | seller | pinned |
| `faq_answers.land_soil_and_topography` | Land Soil & Topography | seller | pinned |
| `faq_answers.land_survey_available` | Land Survey Available | seller | pinned |
| `faq_answers.land_development_restrictions` | Land Development Restrictions | seller | pinned |

### 2.8 Landlord — Maintenance & Property Condition (6 keys)

| FAQ Key | Label | Roles | keyword_route_status |
|---|---|---|---|
| `faq_answers.maintenance_request_response_time` | Maintenance Request Response Time | landlord | pinned |
| `faq_answers.emergency_maintenance_available` | Emergency Maintenance Available | landlord | pinned |
| `faq_answers.heating_cooling_system` | Heating / Cooling System | landlord | pinned |
| `faq_answers.laundry_situation` | Laundry Situation | landlord | pinned |
| `faq_answers.storage_area_included` | Storage Area Included | landlord | pinned |
| `faq_answers.internet_providers` | Internet Providers | landlord | pinned |

### 2.9 Landlord — Security & Policy (2 keys)

| FAQ Key | Label | Roles | keyword_route_status |
|---|---|---|---|
| `faq_answers.security_features` | Security Features | landlord | pinned |
| `faq_answers.planned_renovations` | Planned Renovations | landlord | pinned |

### 2.10 Landlord — Location & Neighborhood (6 keys)

| FAQ Key | Label | Roles | keyword_route_status |
|---|---|---|---|
| `faq_answers.noise_levels` | Noise Levels | landlord | pinned |
| `faq_answers.nearby_amenities` | Nearby Amenities | landlord | pinned |
| `faq_answers.guest_parking` | Guest Parking | landlord | pinned |
| `faq_answers.proximity_to_public_transit` | Proximity to Public Transit | landlord | pinned |
| `faq_answers.pest_or_mold_history` | Pest / Mold History | landlord | pinned |
| `faq_answers.what_makes_property_unique` | What Makes Property Unique | landlord | pinned |

### 2.11 Landlord — Lifestyle & Flexibility (12 keys)

| FAQ Key | Label | Roles | keyword_route_status |
|---|---|---|---|
| `faq_answers.furnished_or_unfurnished` | Furnished or Unfurnished | landlord | pinned |
| `faq_answers.lease_renewal_process` | Lease Renewal Process | landlord | pinned |
| `faq_answers.notice_to_vacate_required` | Notice to Vacate Required | landlord | pinned |
| `faq_answers.preferred_tenant_qualities` | Preferred Tenant Qualities | landlord | pinned |
| `faq_answers.subletting_allowed` | Subletting Allowed | landlord | pinned |
| `faq_answers.short_term_rentals_allowed` | Short-Term Rentals Allowed | landlord | pinned |
| `faq_answers.ev_charging_available` | EV Charging Available | landlord | pinned |
| `faq_answers.bicycle_storage_available` | Bicycle Storage Available | landlord | pinned |
| `faq_answers.smoking_policy` | Smoking Policy | landlord | pinned |
| `faq_answers.utilities_individually_metered` | Utilities Individually Metered | landlord | pinned |
| `faq_answers.renters_insurance_required` | Renters Insurance Required | landlord | pinned |
| `faq_answers.lease_to_own_option` | Lease-to-Own Option | landlord | pinned |

### 2.12 Landlord — High-Intent (2 keys)

| FAQ Key | Label | Roles | keyword_route_status |
|---|---|---|---|
| `faq_answers.previous_tenant_feedback` | Previous Tenant Feedback | landlord | pinned |
| `faq_answers.smoking_policy` | Smoking Policy | landlord | pinned |

### 2.13 Landlord Addons — Commercial Lease (12 keys)

| FAQ Key | Label | Roles | keyword_route_status |
|---|---|---|---|
| `faq_answers.commercial_cam_charges` | CAM Charges | landlord | pinned |
| `faq_answers.commercial_lease_structure_type` | Commercial Lease Structure | landlord | pinned |
| `faq_answers.commercial_tenant_improvement_allowance` | Tenant Improvement Allowance | landlord | pinned |
| `faq_answers.commercial_buildout_flexibility` | Commercial Buildout Flexibility | landlord | pinned |
| `faq_answers.commercial_signage_rights` | Commercial Signage Rights | landlord | pinned |
| `faq_answers.commercial_loading_dock_freight_elevator` | Loading Dock / Freight Elevator | landlord | pinned |
| `faq_answers.commercial_electrical_capacity` | Commercial Electrical Capacity | landlord | pinned |
| `faq_answers.commercial_parking_ratio` | Commercial Parking Ratio | landlord | pinned |
| `faq_answers.commercial_exclusivity_rights` | Commercial Exclusivity Rights | landlord | pinned |
| `faq_answers.commercial_expansion_option_rofr` | Expansion Option / ROFR | landlord | pinned |
| `faq_answers.commercial_landlord_maintenance_responsibilities` | Landlord Maintenance Responsibilities | landlord | pinned |
| `faq_answers.commercial_building_access_hours` | Building Access Hours | landlord | pinned |

### 2.14 Buyer — Match Criteria (27 keys, `match_criteria`)

Buyer FAQ keys are intentionally `match_criteria` — they describe the buyer's own preferences and route via `buyer_tenant_match`, not `listing_facts`. They do NOT appear in `FAQ_KEY_KEYWORD_MAP` by design. Includes keys `buyer_motivation` through `buyer_flexibility` plus commercial/business/land addon sets.

### 2.15 Tenant — Residential & Commercial (27 keys, `pinned`)

All `faq_q1` through `faq_q27` were promoted from `opaque_key` to `pinned`. Each has entries in `FAQ_KEY_KEYWORD_MAP` and `deriveFieldLabel`.

---

## 3. AI Intelligence Sources

### 3.1 Property DNA (seller, landlord)

Sourced from `PropertyDnaProfile` via `AskAiContextBuilderService::buildPropertyIntelligence()`. Allowed in `property_standout` and `marketing_angles` contracts.

| Field | Context Path | Question Type |
|---|---|---|
| `property_strengths` | `property_intelligence.property_strengths` | property_standout, marketing_angles |
| `property_highlights` | `property_intelligence.property_highlights` | property_standout, marketing_angles |
| `property_positioning` | `property_intelligence.property_positioning` | marketing_angles |
| `property_target_audiences` | `property_intelligence.property_target_audiences` | property_standout |
| `property_personality_tags` | `property_intelligence.property_personality_tags` | property_standout |
| `property_story` | `property_intelligence.property_story` | marketing_angles |

**Missing-data behaviour:** When no `PropertyDnaProfile` exists, `property_intelligence` added to `missing_sources`; contract returns `insufficient_context`.

### 3.2 Location DNA (seller, landlord — optional)

Sourced from `PropertyLocationDna` via `AskAiContextBuilderService::buildLocationIntelligence()`. Optional for all question types — absence appends a warning, not a missing_source.

| Field | Context Path |
|---|---|
| `lifestyle_scores` | `location_intelligence.lifestyle_scores` |
| `lifestyle_categories` | `location_intelligence.lifestyle_categories` |
| `location_narrative` | `location_intelligence.location_narrative` |
| `nearest_highlights` | `location_intelligence.nearest_highlights` |
| `marketing_context` | `location_intelligence.marketing_context` |
| `daily_convenience` | `location_intelligence.daily_convenience` |
| `transportation` | `location_intelligence.transportation` |

### 3.3 Buyer / Tenant DNA Avatar

Sourced from `BuyerTenantDnaProfile` via `buildBuyerAvatar()` / `buildTenantAvatar()`. Required for `buyer_tenant_match` and `compatibility_signals` contracts.

| Field | Context Path | Roles |
|---|---|---|
| `primary_motivation` | `buyer_avatar.primary_motivation` | buyer |
| `buyer_narrative` | `buyer_avatar.buyer_narrative` | buyer |
| `buyer_preference_summary` | `buyer_avatar.buyer_preference_summary` | buyer |
| `buyer_personality_tags` | `buyer_avatar.buyer_personality_tags` | buyer |
| `tenant_narrative` | `tenant_avatar.tenant_narrative` | tenant |
| `tenant_preference_summary` | `tenant_avatar.tenant_preference_summary` | tenant |
| `avatar_confidence_score` | `buyer_avatar.avatar_confidence_score` | buyer, tenant |

---

## 4. Pipeline Architecture

```
User Question
    ↓
AskAiQuestionClassifierService (listing_facts / prohibited / property_standout / ...)
    ↓ (if unsupported + flag on)
AskAiIntentNormalizerService (OpenAI intent → canonical field key)
    ↓
detectFaqFieldKey()  → FAQ_KEY_KEYWORD_MAP → faq_answers.* key
detectListingFieldKey() → LISTING_KEY_KEYWORD_MAP → listing.* key
    ↓
AskAiInternalRunnerService
  → AskAiContextBuilderService  (assembles listing, faq_answers, property_intelligence, ...)
  → AskAiResponseContractService (allowed_context, required_sources, missing_sources)
  → AskAiPromptBuilderService   (filters allowed_context, builds prompt package)
    ↓
Guard A: faq_answers.* field absent → "X has not been provided" (insufficient_context)
Guard B: listing.* field null → "X has not been provided" (insufficient_context)
    ↓
AskAiOpenAiAdapterService (OpenAI gpt-4o-mini)
    ↓
Direct-return fallback: if OpenAI fails AND grounded data present → return raw value (ready)
    ↓
AskAiFinalResponseBuilderService (normalise response)
    ↓
AskAiFollowUpQuestionService (append chip suggestions)
```

---

## 5. Governance Notes

- **Buyer FAQ keys (`match_criteria`)**: Not in `FAQ_KEY_KEYWORD_MAP`. Route via `buyer_tenant_match` question type, not `listing_facts`. Intentional by design.
- **Fair-housing / prohibited questions**: Always blocked at Layer 1 (classifier). No OpenAI call. No data returned.
- **Direct-return fallback**: Only fires for `faq_answers.*` and `listing.*` paths. Never fires for prohibited or blocked questions.
- **Missing-data guard**: Returns `insufficient_context` + field-specific message. Never returns `unsupported`, `failed`, `null`, or a hallucinated answer.
- **`listing.smoking_policy` / `listing.subletting_policy`**: Both exist as listing model fields AND as FAQ answer keys (`faq_answers.smoking_policy`, `faq_answers.subletting_allowed`). `detectFaqFieldKey()` runs before `detectListingFieldKey()`, so FAQ answers take priority when present — this is correct because FAQ answers are more detailed/authored.
