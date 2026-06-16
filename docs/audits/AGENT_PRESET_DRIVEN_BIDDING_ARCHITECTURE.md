# Agent Preset-Driven Bidding Architecture Audit

**Date:** June 16, 2026  
**Scope:** All four agent roles — Seller, Buyer, Landlord, Tenant  
**Status:** Documentation only — no code changes

---

## Table of Contents

1. [Current State Audit](#1-current-state-audit)
2. [Property Type Strategy](#2-property-type-strategy)
3. [Auto-Population Blueprint](#3-auto-population-blueprint)
4. [Counter Workflow Blueprint](#4-counter-workflow-blueprint)
5. [Services Architecture](#5-services-architecture)
6. [Compensation Architecture](#6-compensation-architecture)
7. [Ask AI Integration Readiness](#7-ask-ai-integration-readiness)
8. [Recommended Final UX](#8-recommended-final-ux)
9. [Gap Analysis](#9-gap-analysis)
10. [Implementation Roadmap](#10-implementation-roadmap)
11. [Proof Section](#11-proof-section)

---

## 1. Current State Audit

### 1.1 Preset Field Catalogue

All fields are stored in `agent_default_profiles.profile_data` (JSON). The controller's
`PROFILE_FIELDS` constant governs the "public profile" slice (43 keys); the `save()` method
also persists services and all compensation keys.

#### 1.1.A — Public Profile Fields (`PROFILE_FIELDS`, 43 keys)

| # | Key | Category | Mapped in `mapFromProfile()`? |
|---|-----|----------|-------------------------------|
| 1 | `bio` | Overview | **Yes** |
| 2 | `why_hire_you` | Overview | **Yes** |
| 3 | `what_sets_you_apart` | Overview | **Yes** |
| 4 | `marketing_plan` | Overview | **Yes** |
| 5 | `additional_details` | Overview | **Yes** |
| 6 | `year_licensed` | Credentials | **Yes** |
| 7 | `first_name` | Credentials | **Yes** |
| 8 | `last_name` | Credentials | **Yes** |
| 9 | `phone` | Credentials | **Yes** |
| 10 | `email` | Credentials | **Yes** |
| 11 | `brokerage` | Credentials | **Yes** |
| 12 | `license_no` | Credentials | **Yes** |
| 13 | `nar_id` | Credentials | **Yes** |
| 14 | `brokerage_relationship` | Broker Terms | **Yes** |
| 15 | `presentation_link` | Media / Links | **Yes** |
| 16 | `presentation_upload_path` | Media / Links | **Yes** |
| 17 | `business_card_link` | Media / Links | **Yes** |
| 18 | `business_card_upload_path` | Media / Links | **Yes** |
| 19 | `reviews_links` | Social Proof | **Yes** |
| 20 | `website_link` | Social Proof | **Yes** |
| 21 | `social_media` | Social Proof | **Yes** |
| 22 | `years_experience` | Credentials | **No** |
| 23 | `transactions_last_12_months` | Credentials | **No** |
| 24 | `primary_areas_served` | Service Areas | **No** |
| 25 | `avg_response_time` | Availability | **No** |
| 26 | `is_full_time` | Availability | **No** |
| 27 | `cities_served` | Service Areas | **No** |
| 28 | `counties_served` | Service Areas | **No** |
| 29 | `neighborhoods_served` | Service Areas | **No** |
| 30 | `areas_notes` | Service Areas | **No** |
| 31 | `review_1` | Social Proof | **No** |
| 32 | `review_2` | Social Proof | **No** |
| 33 | `review_3` | Social Proof | **No** |
| 34 | `awards_recognition` | Social Proof | **No** |
| 35 | `intro_video_url` | Media / Links | **No** |
| 36 | `video_caption` | Media / Links | **No** |
| 37 | `availability_status` | Availability | **No** |
| 38 | `evenings_available` | Availability | **No** |
| 39 | `weekends_available` | Availability | **No** |
| 40 | `communication_style` | Communication | **No** |
| 41 | `preferred_contact_method` | Communication | **No** |

**Summary:** 21 of 43 PROFILE_FIELDS are mapped; 20 are unmapped (see §9 for actions).  
Note: `brokerage_relationship` is in PROFILE_FIELDS and in `mapFromProfile()`.

#### 1.1.B — Additional `profile_data` Fields (Services + Compensation, 97 keys)

These are persisted by `AgentPresetController::save()` but are NOT in `PROFILE_FIELDS`. All are
handled by `mapFromProfile()`.

**Services (2 keys):** `services`, `other_services`

**Compatibility Preferences (1 composite key):** `compatibility_preferences`
— Handled by the separate `mapCompatibilityFromProfile()` method; contains 7 section arrays:
`communication_preferences`, `negotiation_approach`, `guidance_style`, `collaboration_preferences`,
`transaction_strategy`, `representation_philosophy`, `representation_priorities`

**Shared Compensation (10 keys):** `protection_period`, `early_termination_fee_option`,
`early_termination_fee_amount`, `agency_agreement_timeframe`, `agency_agreement_custom`,
`interested_lease_option_agreement`, `lease_type`, `lease_value`, `purchase_type`, `purchase_value`

**Purchase Commission — All Roles (7 keys):** `commission_structure`, `purchase_fee_type`,
`purchase_fee_flat`, `purchase_fee_percentage`, `purchase_fee_percentage_combo`,
`purchase_fee_flat_combo`, `purchase_fee_other`

**Retainer (3 keys):** `retainer_fee_option`, `retainer_fee_amount`, `retainer_fee_application`

**Lease Fee — Buyer/Tenant (12 keys):** `interested_lease_option`, `lease_fee_type`,
`lease_fee_flat`, `lease_fee_percentage`, `lease_fee_percentage_monthly_rent`,
`lease_fee_percentage_monthly_number`, `lease_fee_flat_combo`, `lease_fee_percentage_combo`,
`lease_fee_percentage_net`, `lease_fee_flat_combo_net`, `lease_fee_percentage_combo_net`,
`lease_fee_other`

**Seller-Specific Compensation (13 keys):** `nominal`, `commission_structure_type`,
`commission_structure_type_fee_flat`, `commission_structure_type_fee_flat_combo`,
`commission_structure_type_fee_percentage`, `commission_structure_type_fee_percentage_combo`,
`commission_structure_type_fee_other`, `interested_purchase_fee_type`, `seller_leasing_fee_type`,
`seller_leasing_gross`, `seller_leasing_gross_rental`, `seller_leasing_gross_month_rent`,
`sales_tax_option_gross`

**Seller Leasing Sub-fields (13 keys):** `seller_leasing_gross_other`,
`seller_leasing_gross_percentage`, `seller_leasing_gross_purchase_fee_flat_amount`,
`seller_leasing_gross_purchase_fee_other`, `seller_leasing_each_rental`,
`seller_leasing_gross_no_of_months`, `seller_leasing_gross_flat_combo`,
`seller_leasing_gross_percentage_combo`, `seller_leasing_gross_flat_net_combo`,
`seller_leasing_gross_percentage_net_combo`, `seller_leasing_gross_sales_tax_first_month`,
`seller_leasing_gross_sales_tax_option_gross`, `seller_leasing_gross_sales_tax_flat_free_gross`

**Landlord Residential Lease (1 key):** `purchase_fee_rental_period`

**Landlord Commercial Lease (8 keys):** `purchase_fee_net_aggregate`, `purchase_fee_gross_rent`,
`purchase_fee_monthly_percentage`, `purchase_fee_months`, `sales_tax_option_monthly`,
`purchase_fee_flat_commercial`, `sales_tax_option_flat`, `purchase_fee_other_commercial`

**Landlord Commercial Additional (1 key):** `purchase_fee_purchase_price`

**Landlord Tenant Broker Commission (7 keys):** `tenant_broker_commission_structure`,
`tenant_broker_fee_structure`, `tenant_broker_percentage`, `tenant_broker_gross_lease`,
`tenant_broker_first_month_rent`, `tenant_broker_flat_fee`, `tenant_broker_other`

**Broker Fee Timing — Landlord + Tenant (5 keys):** `broker_fee_timing`,
`broker_fee_days_from_rent`, `broker_fee_days_after_lease`, `broker_fee_days_after_rent`,
`broker_fee_timing_other`

**Split Payment — Landlord (3 keys):** `split_payment_due`, `split_payment_due_other`,
`broker_fee_days_after_due_event`

**Renewal/Extension Fee — Landlord (10 keys):** `renewal_fee_type`, `renewal_fee_percentage`,
`renewal_fee_lease_value`, `renewal_fee_first_month`, `renewal_fee_flat_fee`, `renewal_fee_custom`,
`renewal_fee_sales_tax_lease_value`, `renewal_fee_no_of_months`, `renewal_fee_sales_tax_first_month`,
`renewal_fee_sales_tax_flat_fee`

**Landlord Commercial Expansion (1 key):** `expansion_commission_percentage`

**Property Management — Landlord (6 keys):** `interested_in_property_management`,
`interested_in_property_management_fee`, `interested_in_property_management_fee_gross_lease`,
`interested_in_property_management_fee_rental_periord` *(typo preserved — DB column name)*,
`interested_in_property_management_fee_flate_free` *(typo preserved)*,
`interested_in_property_management_fee_other`

**Interested in Selling — Landlord (7 keys):** `interested_in_selling`,
`interested_in_selling_type`, `landlord_broker_purchase_price`, `landlord_broker_percentage_price`,
`landlord_broker_dollar_price`, `landlord_broker_flate_fee` *(typo preserved)*,
`landlord_broker_other`

**All Roles Broker Terms (3 keys):** `additional_details_broker`, `retained_deposits`,
`referral_fee_percent`

**Promotional Materials (1 composite key):** `promoMaterials`

**Total profile_data compensation + services keys audited: 113**  
**Grand total profile_data keys: 43 (PROFILE_FIELDS) + 113 (compensation/services) + 1 (compatibility_preferences) = 157**

---

### 1.2 Bid Field Catalogue by Role

All four bid models use EAV (`*_bid_metas` table). The `getGetAttribute()` accessor decodes
JSON meta values; the `saveMeta($key, $val)` method persists them. Properties declared `public`
in each Livewire component correspond directly to meta keys persisted on submit.

#### 1.2.A — Seller Bid (`SellerAgentAuctionBid` Livewire component)

| Category | Meta Keys |
|----------|-----------|
| Credentials | `first_name`, `last_name`, `phone`, `email`, `brokerage`, `license_no`, `year_licensed`, `nar_id` |
| Overview | `bio`, `why_hire_you`, `what_sets_you_apart`, `marketing_plan`, `additional_details` |
| Media | `reviews_links`, `website_link`, `social_media`, `presentation_link`, `business_card_link`, `business_card_stored_path`, `promoMaterials` |
| Services | `services`, `other_services`, `photo_enhancements`, `custom_enhancement` |
| Seller Listing Commission | `purchase_fee_type`, `purchase_fee_flat`, `purchase_fee_percentage`, `purchase_fee_percentage_combo`, `purchase_fee_flat_combo`, `purchase_fee_other`, `nominal` |
| Buyer Broker Commission | `commission_structure`, `commission_structure_type`, `commission_structure_type_fee_flat`, `commission_structure_type_fee_flat_combo`, `commission_structure_type_fee_percentage`, `commission_structure_type_fee_percentage_combo`, `commission_structure_type_fee_other` |
| Seller Leasing | `interested_purchase_fee_type`, `seller_leasing_fee_type`, `seller_leasing_gross`, `seller_leasing_each_rental`, `seller_leasing_gross_rental`, `seller_leasing_gross_month_rent`, `seller_leasing_gross_no_of_months`, `seller_leasing_gross_percentage`, `seller_leasing_gross_flat_combo`, `seller_leasing_gross_percentage_combo`, `seller_leasing_gross_flat_net_combo`, `seller_leasing_gross_percentage_net_combo`, `sales_tax_option_gross`, `seller_leasing_gross_sales_tax_first_month`, `seller_leasing_gross_sales_tax_option_gross`, `seller_leasing_gross_sales_tax_flat_free_gross`, `seller_leasing_gross_purchase_fee_flat_amount`, `seller_leasing_gross_purchase_fee_other`, `seller_leasing_gross_other` |
| Lease-Option Agreement | `interested_lease_option_agreement`, `lease_type`, `lease_value`, `purchase_type`, `purchase_value` |
| Protection & Fees | `protection_period`, `early_termination_fee_option`, `early_termination_fee_amount`, `retainer_fee_option`, `retainer_fee_amount`, `retainer_fee_application`, `retained_deposits`, `referral_fee_percent` |
| Agency Agreement | `agency_agreement_timeframe`, `agency_agreement_custom`, `brokerage_relationship`, `additional_details_broker` |
| Compatibility | `compatibility_agent_response` (7 section sub-arrays) |

**Seller bid total distinct meta keys: ~73**

#### 1.2.B — Buyer Bid (`BuyerAgentAuctionBid` Livewire component)

| Category | Meta Keys |
|----------|-----------|
| Credentials | `first_name`, `last_name`, `phone`, `email`, `brokerage`, `license_no`, `year_licensed`, `nar_id` |
| Overview | `bio`, `why_hire_you`, `what_sets_you_apart`, `marketing_plan`, `additional_details` |
| Media | `reviews_links`, `website_link`, `social_media`, `presentation_link`, `business_card_link`, `business_card_stored_path`, `promoMaterials` |
| Services | `services`, `other_services` |
| Purchase Commission | `commission_structure`, `purchase_fee_type`, `purchase_fee_flat`, `purchase_fee_percentage`, `purchase_fee_percentage_combo`, `purchase_fee_flat_combo`, `purchase_fee_other` |
| Lease Fee | `interested_lease_option`, `lease_fee_type`, `lease_fee_flat`, `lease_fee_percentage`, `lease_fee_percentage_monthly_rent`, `lease_fee_percentage_monthly_number`, `lease_fee_flat_combo`, `lease_fee_percentage_combo`, `lease_fee_percentage_net`, `lease_fee_flat_combo_net`, `lease_fee_percentage_combo_net`, `lease_fee_other` |
| Lease-Option Agreement | `interested_lease_option_agreement`, `lease_type`, `lease_value`, `purchase_type`, `purchase_value` |
| Protection & Fees | `protection_period`, `early_termination_fee_option`, `early_termination_fee_amount`, `retainer_fee_option`, `retainer_fee_amount`, `retainer_fee_application`, `referral_fee_percent` |
| Agency Agreement | `agency_agreement_timeframe`, `agency_agreement_custom`, `brokerage_relationship`, `additional_details_broker` |
| Compatibility | `compatibility_agent_response` (7 section sub-arrays) |

**Buyer bid total distinct meta keys: ~54**

#### 1.2.C — Landlord Bid (`LandlordAgentAuctionBid` Livewire component)

| Category | Meta Keys |
|----------|-----------|
| Credentials | `first_name`, `last_name`, `phone`, `email`, `brokerage`, `license_no`, `year_licensed`, `nar_id` |
| Overview | `bio`, `why_hire_you`, `what_sets_you_apart`, `marketing_plan`, `additional_details` |
| Media | `reviews_links`, `website_link`, `social_media`, `presentation_link`, `business_card_link`, `business_card_stored_path`, `promoMaterials` |
| Services | `services`, `other_services`, `photo_enhancements`, `custom_enhancement` |
| Broker Commission Structure | `commission_structure` |
| Residential Lease Fee | `purchase_fee_type`, `purchase_fee_flat`, `purchase_fee_rental_period`, `purchase_fee_percentage_combo`, `purchase_fee_flat_combo`, `purchase_fee_other` |
| Commercial Lease Fee | `purchase_fee_net_aggregate`, `purchase_fee_gross_rent`, `sales_tax_option_gross`, `purchase_fee_monthly_percentage`, `purchase_fee_months`, `sales_tax_option_monthly`, `purchase_fee_flat_commercial`, `sales_tax_option_flat`, `purchase_fee_purchase_price`, `purchase_fee_other_commercial` |
| Tenant Broker Commission | `tenant_broker_commission_structure`, `tenant_broker_fee_structure`, `tenant_broker_percentage`, `tenant_broker_gross_lease`, `tenant_broker_first_month_rent`, `tenant_broker_flat_fee`, `tenant_broker_other` |
| Payment Timing | `broker_fee_timing`, `broker_fee_days_from_rent`, `broker_fee_days_after_lease`, `broker_fee_days_after_rent`, `broker_fee_timing_other`, `split_payment_due`, `split_payment_due_other`, `broker_fee_days_after_due_event` |
| Renewal/Extension Fees | `renewal_fee_type`, `renewal_fee_percentage`, `renewal_fee_lease_value`, `renewal_fee_first_month`, `renewal_fee_flat_fee`, `renewal_fee_custom`, `renewal_fee_sales_tax_lease_value`, `renewal_fee_no_of_months`, `renewal_fee_sales_tax_first_month`, `renewal_fee_sales_tax_flat_fee` |
| Commercial Expansion | `expansion_commission_percentage` |
| Property Management | `interested_in_property_management`, `interested_in_property_management_fee`, `interested_in_property_management_fee_gross_lease`, `interested_in_property_management_fee_rental_periord`, `interested_in_property_management_fee_flate_free`, `interested_in_property_management_fee_other` |
| Interested in Selling | `interested_in_selling`, `interested_in_selling_type`, `landlord_broker_purchase_price`, `landlord_broker_percentage_price`, `landlord_broker_dollar_price`, `landlord_broker_flate_fee`, `landlord_broker_other` |
| Lease-Option Agreement | `interested_lease_option_agreement`, `lease_type`, `lease_value`, `purchase_type`, `purchase_value` |
| Protection & Fees | `protection_period`, `early_termination_fee_option`, `early_termination_fee_amount`, `retainer_fee_option`, `retainer_fee_amount`, `retainer_fee_application`, `referral_fee_percent` |
| Agency Agreement | `agency_agreement_timeframe`, `agency_agreement_custom`, `brokerage_relationship`, `additional_details_broker` |
| Compatibility | `compatibility_agent_response` (7 section sub-arrays) |

**Landlord bid total distinct meta keys: ~93**

#### 1.2.D — Tenant Bid (`TenantAgentAuctionBid` Livewire component)

| Category | Meta Keys |
|----------|-----------|
| Credentials | `first_name`, `last_name`, `phone`, `email`, `brokerage`, `license_no`, `year_licensed`, `nar_id` |
| Overview | `bio`, `why_hire_you`, `what_sets_you_apart`, `marketing_plan`, `additional_details` |
| Media | `reviews_links`, `website_link`, `social_media`, `presentation_link`, `business_card_link`, `business_card_stored_path`, `promoMaterials` |
| Services | `services`, `other_services` |
| Lease Fee (Primary) | `commission_structure`, `lease_fee_type`, `lease_fee_flat`, `lease_fee_percentage`, `lease_fee_percentage_monthly_rent`, `lease_fee_percentage_monthly_number`, `lease_fee_flat_combo`, `lease_fee_percentage_combo`, `lease_fee_percentage_net`, `lease_fee_flat_combo_net`, `lease_fee_percentage_combo_net`, `lease_fee_other` |
| Purchase Fee (Optional) | `interested_purchase_fee_type`, `purchase_fee_type`, `purchase_fee_flat`, `purchase_fee_percentage`, `purchase_fee_percentage_combo`, `purchase_fee_flat_combo`, `purchase_fee_other` |
| Lease-Option Agreement | `interested_lease_option_agreement`, `lease_type`, `lease_value`, `purchase_type`, `purchase_value` |
| Broker Fee Timing | `broker_fee_timing`, `broker_fee_days_from_rent`, `broker_fee_days_after_lease`, `broker_fee_days_after_rent`, `broker_fee_timing_other` |
| Protection & Fees | `protection_period`, `early_termination_fee_option`, `early_termination_fee_amount`, `retainer_fee_option`, `retainer_fee_amount`, `retainer_fee_application`, `referral_fee_percent` |
| Agency Agreement | `agency_agreement_timeframe`, `agency_agreement_custom`, `brokerage_relationship`, `additional_details_broker` |
| Compatibility | `compatibility_agent_response` (7 section sub-arrays) |

**Tenant bid total distinct meta keys: ~57**

---

### 1.3 Mapper Audit — `AgentBidMapperService`

Two methods exist:

**`mapFromProfile(array $profileData): array`** — maps 135 profile_data keys to a flat bid-field
array. Called by all four bid components on new-bid `mount()`. Used by the Hire-Me auto-bid flow.

**`mapCompatibilityFromProfile(array $profileData): array`** — extracts the 7 compatibility
section sub-arrays from `profile_data['compatibility_preferences']`. Returns empty array when
the key is absent. Called by all four bid components.

**`findAndMap(int $userId, string $role, string $propertyType): ?array`** — the primary entry
point. Calls `findForAgentWithFallback()` (exact match, then role-default fallback), then
`mapFromProfile()`.

#### 1.3.A — Preset-to-Bid Application Status by Field and Role

The table below tracks each category of preset fields and whether each bid component's `mount()`
actually applies values from `$mapped` to `$this->*`. A field can be **Mapped** (in
`mapFromProfile()` and applied by the component), **Partial** (in `mapFromProfile()` but only
applied by some roles), **Pipeline Only** (in `mapFromProfile()` but not applied in any component
`mount()` — only available for the auto-bid flow), or **Unmapped** (not in `mapFromProfile()`).

| Preset Category | Preset Key(s) | In `mapFromProfile()`? | Applied: Seller | Applied: Buyer | Applied: Landlord | Applied: Tenant |
|----------------|---------------|------------------------|-----------------|----------------|-------------------|-----------------|
| Overview | `bio`, `why_hire_you`, `what_sets_you_apart`, `marketing_plan`, `additional_details` | Yes | **Yes** | **Yes** | **Yes** | **Yes** |
| Year Licensed | `year_licensed` | Yes | **Yes** | **Yes** | **Yes** | **Yes** |
| Credentials | `first_name`, `last_name`, `phone`, `email`, `brokerage`, `license_no`, `nar_id` | Yes | **Yes** | **Yes** | **Yes** | **Yes** |
| Media Links | `presentation_link`, `business_card_link`, `business_card_stored_path` | Yes | **Yes** | **Yes** | **Yes** | **Yes** |
| Array Media | `reviews_links`, `website_link`, `social_media`, `promoMaterials` | Yes | **Yes** | **Yes** | **Yes** | **Yes** |
| Services | `services`, `other_services` | Yes | **No** *(listing-seeded)* | **No** *(listing-seeded)* | **Yes** | **Yes** |
| Compatibility Preferences | `compatibility_preferences` (7 sections) | Via separate method | **Yes** | **Yes** | **Yes** | **Yes** |
| Purchase Commission | `commission_structure`, `purchase_fee_type`, `purchase_fee_flat`, `purchase_fee_percentage`, `purchase_fee_percentage_combo`, `purchase_fee_flat_combo`, `purchase_fee_other` | Yes | **Yes** | **Yes** | **Yes** | **Yes** |
| Retainer | `retainer_fee_option`, `retainer_fee_amount`, `retainer_fee_application` | Yes | **Yes** | **Yes** | **Yes** | **Yes** |
| Lease Fee | `interested_lease_option`, `lease_fee_type` + 10 sub-fields | Yes | **No** *(N/A role)* | **Yes** | **No** *(N/A role — uses purchase_fee)* | **Yes** |
| Lease-Option Agreement | `interested_lease_option_agreement`, `lease_type`, `lease_value`, `purchase_type`, `purchase_value` | Yes | **Yes** | **Yes** | **Yes** | **Yes** |
| Protection Period | `protection_period`, `early_termination_fee_option`, `early_termination_fee_amount` | Yes | **Yes** | **Yes** | **Yes** | **Yes** |
| Agency Agreement | `agency_agreement_timeframe`, `agency_agreement_custom` | Yes | **Yes** | **Yes** | **Yes** | **Yes** |
| Broker Relationship | `brokerage_relationship`, `additional_details_broker` | Yes | **Yes** | **Yes** | **Yes** | **Yes** |
| Retained Deposits | `retained_deposits` | Yes | **Yes** | **No** | **No** | **No** |
| Referral Fee | `referral_fee_percent` | Yes | **No** *(listing-seeded)* | **No** *(listing-seeded)* | **No** *(listing-seeded)* | **No** *(listing-seeded)* |
| Seller Compensation | `nominal`, `commission_structure_type`, `seller_leasing_*` (26 keys) | Yes | **Yes** | **No** *(N/A role)* | **No** *(N/A role)* | **No** *(N/A role)* |
| Landlord Residential Lease | `purchase_fee_rental_period` | Yes | **No** *(N/A role)* | **No** *(N/A role)* | **Yes** | **No** *(N/A role)* |
| Landlord Commercial Lease | 8 keys incl. `purchase_fee_net_aggregate` | Yes | **No** *(N/A role)* | **No** *(N/A role)* | **Yes** | **No** *(N/A role)* |
| Landlord Tenant Broker Commission | 7 keys incl. `tenant_broker_commission_structure` | Yes | **No** *(N/A role)* | **No** *(N/A role)* | **Yes** | **No** *(N/A role)* |
| Broker Fee Timing | `broker_fee_timing` + 4 sub-fields | Yes | **No** *(N/A role)* | **No** *(N/A role)* | **Yes** | **Yes** |
| Split Payment | `split_payment_due`, `split_payment_due_other`, `broker_fee_days_after_due_event` | Yes | **No** *(N/A role)* | **No** *(N/A role)* | **Yes** | **No** *(N/A role)* |
| Renewal/Extension Fees | 10 keys incl. `renewal_fee_type` | Yes | **No** *(N/A role)* | **No** *(N/A role)* | **Yes** | **No** *(N/A role)* |
| Expansion Commission | `expansion_commission_percentage` | Yes | **No** *(N/A role)* | **No** *(N/A role)* | **Yes** | **No** *(N/A role)* |
| Property Management | 6 keys incl. `interested_in_property_management` | Yes | **No** *(N/A role)* | **No** *(N/A role)* | **Yes** | **No** *(N/A role)* |
| Interested in Selling | 7 keys incl. `interested_in_selling` | Yes | **No** *(N/A role)* | **No** *(N/A role)* | **Yes** | **No** *(N/A role)* |
| **Years Experience** | `years_experience` | **No** | No | No | No | No |
| **Transactions (12 mo)** | `transactions_last_12_months` | **No** | No | No | No | No |
| **Primary Areas Served** | `primary_areas_served` | **No** | No | No | No | No |
| **Avg Response Time** | `avg_response_time` | **No** | No | No | No | No |
| **Full-Time Status** | `is_full_time` | **No** | No | No | No | No |
| **Cities Served** | `cities_served` | **No** | No | No | No | No |
| **Counties Served** | `counties_served` | **No** | No | No | No | No |
| **Neighborhoods Served** | `neighborhoods_served` | **No** | No | No | No | No |
| **Area Notes** | `areas_notes` | **No** | No | No | No | No |
| **Reviews (text)** | `review_1`, `review_2`, `review_3` | **No** | No | No | No | No |
| **Awards/Recognition** | `awards_recognition` | **No** | No | No | No | No |
| **Intro Video URL** | `intro_video_url` | **No** | No | No | No | No |
| **Video Caption** | `video_caption` | **No** | No | No | No | No |
| **Availability Status** | `availability_status` | **No** | No | No | No | No |
| **Evenings Available** | `evenings_available` | **No** | No | No | No | No |
| **Weekends Available** | `weekends_available` | **No** | No | No | No | No |
| **Communication Style** | `communication_style` | **No** | No | No | No | No |
| **Preferred Contact Method** | `preferred_contact_method` | **No** | No | No | No | No |

> **Bold** rows indicate true gaps — fields stored in the preset but not available in bids at all.

---

## 2. Property Type Strategy

### 2.1 Supported Role × Property Type Combinations

| Role | Residential | Income | Commercial | Business | Vacant Land |
|------|-------------|--------|------------|----------|-------------|
| Seller | ✓ | ✓ | ✓ | ✓ | ✓ |
| Buyer | ✓ | ✓ | ✓ | ✓ | ✓ |
| Landlord | ✓ | — | ✓ | — | — |
| Tenant | ✓ | — | ✓ | — | — |

**Total active combinations: 5 (Seller) + 5 (Buyer) + 2 (Landlord) + 2 (Tenant) = 14**

Presets can also be saved as a role-default (`property_type = '__default__'`) which serves as a
fallback for any property type within that role that has no specific preset. This yields up to
15 records per agent (14 specific + 4 role-defaults).

### 2.2 Shared vs. Type-Specific Preset Fields

#### Fields Shared Across All Property Types (All Roles)

These fields have identical structure and semantics regardless of role or property type:

- **Credentials:** `bio`, `why_hire_you`, `what_sets_you_apart`, `marketing_plan`,
  `additional_details`, `year_licensed`, `first_name`, `last_name`, `phone`, `email`,
  `brokerage`, `license_no`, `nar_id`
- **Profile Identity:** `years_experience`, `transactions_last_12_months`, `is_full_time`,
  `availability_status`, `avg_response_time`
- **Media:** `presentation_link`, `presentation_upload_path`, `business_card_link`,
  `business_card_upload_path`, `reviews_links`, `website_link`, `social_media`, `promoMaterials`
- **Service Areas:** `primary_areas_served`, `cities_served`, `counties_served`,
  `neighborhoods_served`, `areas_notes`
- **Social Proof:** `review_1`, `review_2`, `review_3`, `awards_recognition`
- **Availability:** `evenings_available`, `weekends_available`
- **Communication:** `communication_style`, `preferred_contact_method`
- **Intro Video:** `intro_video_url`, `video_caption`
- **Agency Terms:** `protection_period`, `early_termination_fee_option`,
  `early_termination_fee_amount`, `agency_agreement_timeframe`, `agency_agreement_custom`,
  `brokerage_relationship`, `additional_details_broker`, `retained_deposits`
- **Retainer:** `retainer_fee_option`, `retainer_fee_amount`, `retainer_fee_application`
- **Compatibility Preferences:** All 7 sections of `compatibility_preferences`

#### Fields Shared Across Property Types Within a Role

**Seller (all 5 property types):**
- All compensation fields remain structurally identical across residential, income, commercial,
  business, and vacant land. Only the **service catalog** differs per type.
- The buyer broker commission section (`commission_structure`, `commission_structure_type`, etc.)
  applies to all seller property types.

**Buyer (all 5 property types):**
- Commission structure and purchase fee fields are identical across property types.
- Lease fee fields apply only when the buyer is interested in leasing as well (Income, Commercial).
- Services differ significantly per type — the catalog is type-specific.

**Landlord (Residential vs. Commercial):**
- Residential: uses `purchase_fee_type` → flat/percentage/combo structure.
- Commercial: uses `purchase_fee_type` → net-aggregate, gross-rent, monthly-percentage, flat,
  or other structures. Completely different sub-field tree.
- **Tenant broker commission** section applies to Residential only.
- **Expansion commission** applies to Commercial only.
- Common to both: broker fee timing, renewal/extension fees, property management, interested
  in selling, lease-option agreement.

**Tenant (Residential vs. Commercial):**
- Residential: lease fee → percentage of one month's rent, or flat.
- Commercial: lease fee → gross lease percentage, NNN percentage, flat, or other. Sub-field
  structure differs.
- Both share: purchase fee (if interested), lease-option agreement, broker fee timing,
  retainer, agency agreement.

### 2.3 Recommended Preset Structure per Role

| Role | Property Type | Unique Compensation Shape | Service Catalog Size |
|------|---------------|--------------------------|----------------------|
| Seller | Residential | Full listing commission + buyer broker commission + optional leasing | ~68 services |
| Seller | Income | Same shape, investor context | ~58 services |
| Seller | Commercial | Same shape, LOI context | ~55 services |
| Seller | Business | Same shape, business broker context | ~57 services |
| Seller | Vacant Land | Same shape, land context | ~46 services |
| Buyer | Residential | Purchase commission + optional lease commission | ~36 services |
| Buyer | Income | Same shape + income analysis context | ~36 services |
| Buyer | Commercial | Same shape + LOI context | ~35 services |
| Buyer | Business | Same shape + business acquisition context | ~37 services |
| Buyer | Vacant Land | Same shape + land assessment context | ~34 services |
| Landlord | Residential | Residential lease fee + tenant broker commission | ~52 services |
| Landlord | Commercial | Commercial multi-type lease fee + expansion commission | ~48 services |
| Tenant | Residential | Lease fee (rent-based) + optional purchase fee | ~30 services |
| Tenant | Commercial | Lease fee (gross/NNN/flat) + optional purchase fee | ~30 services |

> **Recommendation:** Agents who operate across multiple property types should be encouraged to
> complete the role-default preset first (providing common identity, overview, and default
> compensation terms), then create property-type-specific presets that override only the fields
> that differ (services and compensation sub-type).

---

## 3. Auto-Population Blueprint

The current auto-population path:

```
AgentDefaultProfile::findForAgentWithFallback()
  → AgentBidMapperService::mapFromProfile()
  → Component mount(): applyPresetField($key, $value, &$count)
```

`applyPresetField()` uses a blank-value guard: it only writes the field if `$value` is non-empty
(string: non-empty string; array: non-empty array; integer/bool: not null). This prevents preset
fields from overwriting listing-seeded defaults with blank.

### 3.1 Services Auto-Population

| Role | Current Behavior | Recommended Final Behavior |
|------|-----------------|---------------------------|
| Seller | Services seeded from **listing** `services` + `other_services`; preset services **ignored** | Keep listing-seeded as base; allow preset to supplement if listing services are empty |
| Buyer | Services seeded from **listing** `services` + `other_services`; preset services **ignored** | Same as Seller |
| Landlord | Services seeded from **listing** first, then **overridden by preset** if preset has non-empty services | Current behavior is correct |
| Tenant | Services seeded from **listing** first, then **overridden by preset** if preset has non-empty services | Current behavior is correct |

**Filter Rule:** Each component applies `filterServicesToCurrentCatalog()` before assigning
preset services. This guards against cross-role contamination (e.g., Buyer services appearing in
a Tenant bid). The catalog is sourced from the appropriate `*BidMatchScoreHelper::getCatalog()`.

### 3.2 Compensation Auto-Population Blueprint

The following table defines the recommended field-by-field mapping for each compensation category.
Fields already mapped are marked **[MAPPED]**; fields needing mapper additions are marked
**[ADD TO MAPPER]**.

#### 3.2.A — Services Category

| Preset Field | Bid Field (all roles) | Status |
|---|---|---|
| `services` | `services` (filtered) | **[MAPPED]** — Landlord + Tenant only; Seller + Buyer listing-seeded |
| `other_services` | `other_services` | **[MAPPED]** — same caveat |

#### 3.2.B — Compensation: Purchase Commission

| Preset Field | Seller | Buyer | Landlord | Tenant |
|---|---|---|---|---|
| `commission_structure` | `commission_structure` [MAPPED] | `commission_structure` [MAPPED] | `commission_structure` [MAPPED] | `commission_structure` [MAPPED] |
| `purchase_fee_type` | `purchase_fee_type` [MAPPED] | `purchase_fee_type` [MAPPED] | `purchase_fee_type` [MAPPED] | `purchase_fee_type` [MAPPED] |
| `purchase_fee_flat` | `purchase_fee_flat` [MAPPED] | `purchase_fee_flat` [MAPPED] | `purchase_fee_flat` [MAPPED] | `purchase_fee_flat` [MAPPED] |
| `purchase_fee_percentage` | `purchase_fee_percentage` [MAPPED] | `purchase_fee_percentage` [MAPPED] | — | `purchase_fee_percentage` [MAPPED] |
| `purchase_fee_percentage_combo` | `purchase_fee_percentage_combo` [MAPPED] | `purchase_fee_percentage_combo` [MAPPED] | `purchase_fee_percentage_combo` [MAPPED] | `purchase_fee_percentage_combo` [MAPPED] |
| `purchase_fee_flat_combo` | `purchase_fee_flat_combo` [MAPPED] | `purchase_fee_flat_combo` [MAPPED] | `purchase_fee_flat_combo` [MAPPED] | `purchase_fee_flat_combo` [MAPPED] |
| `purchase_fee_other` | `purchase_fee_other` [MAPPED] | `purchase_fee_other` [MAPPED] | `purchase_fee_other` [MAPPED] | `purchase_fee_other` [MAPPED] |

#### 3.2.C — Compensation: Lease Fee (Buyer + Tenant)

| Preset Field | Buyer | Tenant |
|---|---|---|
| `interested_lease_option` | [MAPPED] | — |
| `lease_fee_type` | [MAPPED] | [MAPPED] |
| `lease_fee_flat` | [MAPPED] | [MAPPED] |
| `lease_fee_percentage` | [MAPPED] | [MAPPED] |
| `lease_fee_percentage_monthly_rent` | [MAPPED] | [MAPPED] |
| `lease_fee_percentage_monthly_number` | [MAPPED] | [MAPPED] |
| `lease_fee_flat_combo` | [MAPPED] | [MAPPED] |
| `lease_fee_percentage_combo` | [MAPPED] | [MAPPED] |
| `lease_fee_percentage_net` | [MAPPED] | [MAPPED] |
| `lease_fee_flat_combo_net` | [MAPPED] | [MAPPED] |
| `lease_fee_percentage_combo_net` | [MAPPED] | [MAPPED] |
| `lease_fee_other` | [MAPPED] | [MAPPED] |

#### 3.2.D — Compensation: Broker Terms (All Roles)

| Preset Field | All Roles |
|---|---|
| `protection_period` | [MAPPED] |
| `early_termination_fee_option` | [MAPPED] |
| `early_termination_fee_amount` | [MAPPED] |
| `agency_agreement_timeframe` | [MAPPED] |
| `agency_agreement_custom` | [MAPPED] |
| `retainer_fee_option` | [MAPPED] |
| `retainer_fee_amount` | [MAPPED] |
| `retainer_fee_application` | [MAPPED] |
| `brokerage_relationship` | [MAPPED] |
| `additional_details_broker` | [MAPPED] |
| `retained_deposits` | Seller [MAPPED]; Buyer/Landlord/Tenant **[ADD TO MAPPER]** |
| `referral_fee_percent` | All roles: listing-seeded; preset value available in `$mapped` but not applied to component. **[ADD TO MAPPER]** as optional override when listing value is blank |

#### 3.2.E — Credentials

| Preset Field | All Roles | Status |
|---|---|---|
| `years_experience` | — (no bid field exists) | **[ADD TO BID FORM]** — Phase 2 |
| `transactions_last_12_months` | — (no bid field exists) | **[ADD TO BID FORM]** — Phase 2 |
| `is_full_time` | — (no bid field exists) | **[ADD TO BID FORM]** — Phase 2 |
| `year_licensed` | [MAPPED] | Already done |

#### 3.2.F — Availability

| Preset Field | All Roles | Status |
|---|---|---|
| `availability_status` | — (no bid field exists) | **[ADD TO BID FORM]** — Phase 2 |
| `evenings_available` | — (no bid field exists) | **[ADD TO BID FORM]** — Phase 2 |
| `weekends_available` | — (no bid field exists) | **[ADD TO BID FORM]** — Phase 2 |
| `avg_response_time` | — (no bid field exists) | **[ADD TO BID FORM]** — Phase 2 |

#### 3.2.G — Service Areas

| Preset Field | All Roles | Status |
|---|---|---|
| `primary_areas_served` | — (no bid field exists) | **[ADD TO BID FORM]** — Phase 2 |
| `cities_served` | — (no bid field exists) | **[ADD TO BID FORM]** — Phase 2 |
| `counties_served` | — (no bid field exists) | **[ADD TO BID FORM]** — Phase 2 |
| `neighborhoods_served` | — (no bid field exists) | **[ADD TO BID FORM]** — Phase 2 |
| `areas_notes` | — (no bid field exists) | **[ADD TO BID FORM]** — Phase 2 |

#### 3.2.H — Marketing / Showing Strategy / Open House

The current `marketing_plan` field (free-text textarea) is the only marketing-related field in
bids. The following preset fields exist but have no corresponding bid fields:

| Preset Field | Category | Status |
|---|---|---|
| `marketing_plan` | Marketing | [MAPPED] |
| `intro_video_url` | Media / Marketing | **[ADD TO BID FORM]** — Phase 2 |
| `video_caption` | Media / Marketing | **[ADD TO BID FORM]** — Phase 2 |

Note: `showOpenHouseCount` / `openHouseCount` (Seller component) is populated from listing
data, not from the preset. Open house strategy currently lives in services selection.

#### 3.2.I — Communication Preferences

The `compatibility_preferences` composite key contains 7 sections including
`communication_preferences`. These are **already mapped and applied to all roles** via
`mapCompatibilityFromProfile()`. The standalone scalar preset fields (`communication_style`,
`preferred_contact_method`) have no corresponding bid fields.

| Preset Field | All Roles | Status |
|---|---|---|
| `compatibility_preferences` (7 sections) | [MAPPED] via separate method | Already done |
| `communication_style` | — (no bid field exists) | **[ADD TO BID FORM]** — Phase 3 |
| `preferred_contact_method` | — (no bid field exists) | **[ADD TO BID FORM]** — Phase 3 |

#### 3.2.J — Social Proof

| Preset Field | All Roles | Status |
|---|---|---|
| `reviews_links` (array) | [MAPPED] | Already done |
| `website_link` (array) | [MAPPED] | Already done |
| `social_media` (array) | [MAPPED] | Already done |
| `review_1`, `review_2`, `review_3` (text) | — (no bid field exists) | **[ADD TO BID FORM]** — Phase 2 |
| `awards_recognition` (text) | — (no bid field exists) | **[ADD TO BID FORM]** — Phase 2 |

---

## 4. Counter Workflow Blueprint

### 4.1 Current Counter System

Three counter term models exist:
- `SellerCounterTerm` — `hasMany` via `seller_agent_auction_bid_id`
- `LandlordCounterTerm` — `hasMany` via `landlord_agent_auction_id` *(note: FK references
  auction, not bid — a known asymmetry)*
- `BuyerCounterBidding` / `TenantCounterBidding` — `hasMany` via `*_agent_auction_bid_id`

The `getBidStatusAttribute()` on each bid model checks `counterTerms()` to determine
`'Countered'` status.

### 4.2 Recommended Counter Rules per Field Category

#### Fields the Listing Owner (Client) MAY Counter

These are negotiating-surface fields where the client has a legitimate interest in proposing
different terms:

| Field Category | Fields | Counter Type |
|---|---|---|
| Commission / Fees | `purchase_fee_type`, `purchase_fee_flat`, `purchase_fee_percentage`, `purchase_fee_percentage_combo`, `purchase_fee_flat_combo`, `commission_structure`, `commission_structure_type` | Client may propose lower rate or different structure |
| Lease Fee (Buyer/Tenant roles) | `lease_fee_type`, `lease_fee_flat`, `lease_fee_percentage`, `lease_fee_percentage_combo` | Client may counter fee amount |
| Retainer | `retainer_fee_option`, `retainer_fee_amount`, `retainer_fee_application` | Client may decline or lower retainer |
| Services Scope | `services` (array) | Client may request fewer or additional services |
| Protection Period | `protection_period` | Client may request shorter period |
| Agency Agreement | `agency_agreement_timeframe` | Client may request shorter duration |
| Early Termination | `early_termination_fee_option`, `early_termination_fee_amount` | Client may request waiver or lower fee |
| Lease-Option Terms | `lease_type`, `lease_value`, `purchase_type`, `purchase_value` | Client may counter fee values |
| Additional Broker Details | `additional_details_broker` | Client may request specific modifications |

#### Fields the Agent MAY Counter-Back

After a client counter, the agent may accept, reject, or modify:

| Field Category | Notes |
|---|---|
| All compensation fields | Agent may accept client's rate or propose a middle rate |
| Services scope | Agent may accept reduced scope or restate minimum services |
| Agency timeframe | Agent may accept shorter or propose a minimum duration |
| Retainer terms | Agent may accept or renegotiate application terms |

#### Fields That Are IMMUTABLE Post-Acceptance

Once a bid is accepted and an `AcceptedBidSummary` record is created, the following fields
should not be modifiable:

| Field Category | Fields | Reason |
|---|---|---|
| Agent Identity | `first_name`, `last_name`, `license_no`, `nar_id`, `brokerage` | Legal identity on the executed agreement |
| Accepted Fee | `purchase_fee_type` + active amount sub-field | Agreed compensation term |
| Services (accepted) | `services` at time of acceptance | Contract scope |
| Agency Duration | `agency_agreement_timeframe` at acceptance | Bound period |

**Implementation Note:** The platform enforces immutability currently by detecting
`accepted === 'accepted'` in edit-mode guards on bid components. PDF cache invalidation via
`AcceptedBidSummary` also signals that fields are fixed at acceptance time.

### 4.3 Asymmetry Notes

- **Seller** counter system uses `SellerCounterTerm` with `status` column (1=active, 0=rejected).
  A bid can be re-activated after a counter is rejected.
- **Buyer** / **Tenant** use `BuyerCounterBidding` / `TenantCounterBidding` — `latest()` wins;
  no status column, so any counter record marks the bid as "Countered."
- **Landlord** counter FK points to `landlord_agent_auction_id` (the listing), not
  `landlord_agent_auction_bid_id` — meaning all bids on a listing share counter records.
  This is a known architectural asymmetry and should be addressed before building counter
  UI for Landlord.

---

## 5. Services Architecture

### 5.1 Service List Source of Truth

`AgentPresetCatalog::getServices(string $role, string $propertyType): array` is the single
source of truth for all service strings, organized into 14 catalogs:

| Role × Type | Approx. Service Count |
|-------------|----------------------|
| Buyer / Residential | 36 |
| Buyer / Income | 36 |
| Buyer / Commercial | 35 |
| Buyer / Business | 37 |
| Buyer / Vacant Land | 34 |
| Seller / Residential | ~68 |
| Seller / Income | ~58 |
| Seller / Commercial | ~55 |
| Seller / Business | ~57 |
| Seller / Vacant Land | ~46 |
| Landlord / Residential | ~52 |
| Landlord / Commercial | ~48 |
| Tenant / Residential | ~30 |
| Tenant / Commercial | ~30 |

### 5.2 Preset Services Filter Rule

When a preset is applied to a bid, services go through `filterServicesToCurrentCatalog()` which:
1. Gets the catalog for the current role + property type from the appropriate
   `*BidMatchScoreHelper::getCatalog()`.
2. Normalizes both preset services and catalog services (Unicode quote normalization,
   trim, lowercase).
3. Retains only services that appear in the current catalog.

This prevents cross-contamination (e.g., Seller residential services appearing in a Landlord
commercial bid).

### 5.3 Recommended Services Filter Enforcement

The **full enforcement model** (Phase 3 goal) has two parts:

**Agent side (preset → bid):**
- Preset services for a role + property type are the starting set.
- The agent may add/remove during bid submission.
- Services not in the catalog for this role + property type are stripped at save time.

**Client side (listing → bid acceptance):**
- If the listing owner specified required services, those should be pre-checked and
  locked against removal.
- If the listing specifies optional services, those appear as suggestions.
- Currently: listings carry `services` + `other_services` meta keys that seed the bid form,
  but there is no enforcement that the agent must include all listing services.

### 5.4 Custom Services

Both listings and bids support `other_services` — an array of free-text strings. The
`client_custom_services` meta key pattern (referenced in `replit.md`) stores client-side
custom service requests separately from standard catalog services.

**Recommended workflow for custom services:**
1. Client adds custom service request in the listing creation form →
   saved to `client_custom_services` meta key on the listing.
2. When an agent submits a bid, the listing's `client_custom_services` are displayed
   (read-only) as requested services.
3. Agent may accept (include in `other_services` on the bid) or decline (leave out).
4. Declined custom services can trigger a counter from the client.
5. Accepted custom services persist in the bid's `other_services` array.

---

## 6. Compensation Architecture

### 6.1 Seller Compensation

| Field | Origin | Editable at Bid | Derived |
|---|---|---|---|
| `purchase_fee_type` | Preset + listing seed | Yes | No |
| `purchase_fee_flat` | Preset + listing seed | Yes | No |
| `purchase_fee_percentage` | Preset + listing seed | Yes | No |
| `purchase_fee_percentage_combo` | Preset + listing seed | Yes | No |
| `purchase_fee_flat_combo` | Preset + listing seed | Yes | No |
| `purchase_fee_other` | Preset + listing seed | Yes | No |
| `nominal` | Preset | Yes | No |
| `commission_structure` | Preset + listing seed | Yes | No |
| `commission_structure_type` | Preset + listing seed | Yes | No |
| `commission_structure_type_fee_*` (5 sub-fields) | Preset + listing seed | Yes | No |
| `interested_purchase_fee_type` | Preset + listing seed | Yes | No |
| `seller_leasing_fee_type` | Preset + listing seed | Yes | No |
| `seller_leasing_*` (17 sub-fields) | Preset | Yes | No |
| `retainer_fee_option/amount/application` | Preset + listing seed | Yes | No |
| `protection_period` | Preset + listing seed | Yes | No |
| `early_termination_fee_option/amount` | Preset + listing seed | Yes | No |
| `retained_deposits` | Preset | Yes | No |
| `agency_agreement_timeframe/custom` | Preset + listing seed | Yes | No |
| `referral_fee_percent` | Listing seed (`referral_percentage`) | Yes | No — listing controls initial value |
| `interested_lease_option_agreement` + lease terms | Preset + listing seed | Yes | No |

### 6.2 Buyer Compensation

| Field | Origin | Editable at Bid | Derived |
|---|---|---|---|
| `commission_structure` | Preset + listing seed | Yes | No |
| `purchase_fee_type` + sub-fields (6) | Preset + listing seed | Yes | No |
| `interested_lease_option` | Preset + listing seed | Yes | No |
| `lease_fee_type` + sub-fields (11) | Preset + listing seed | Yes | No |
| `retainer_fee_option/amount/application` | Preset + listing seed | Yes | No |
| `protection_period` | Preset + listing seed | Yes | No |
| `early_termination_fee_option/amount` | Preset + listing seed | Yes | No |
| `agency_agreement_timeframe/custom` | Preset + listing seed | Yes | No |
| `referral_fee_percent` | Listing seed | Yes | No |
| `interested_lease_option_agreement` + terms | Preset + listing seed | Yes | No |

### 6.3 Landlord Compensation

| Field | Origin | Editable at Bid | Derived |
|---|---|---|---|
| `purchase_fee_type` (residential) | Preset + listing seed | Yes | No |
| `purchase_fee_flat` + `purchase_fee_rental_period` | Preset + listing seed | Yes | No |
| `purchase_fee_percentage_combo` / `flat_combo` / `other` | Preset + listing seed | Yes | No |
| Commercial lease fee (8 sub-fields) | Preset + listing seed | Yes | No |
| `tenant_broker_commission_structure` + fee structure (6 sub-fields) | Preset + listing seed | Yes | No |
| `broker_fee_timing` + sub-fields (7) | Preset + listing seed | Yes | No |
| `renewal_fee_type` + sub-fields (9) | Preset + listing seed | Yes | No |
| `expansion_commission_percentage` | Preset | Yes | No |
| Property management interest + fee (6 keys) | Preset + listing seed | Yes | No |
| Interested in selling + selling type + fee (7 keys) | Preset + listing seed | Yes | No |
| `retainer_fee_option/amount/application` | Preset + listing seed | Yes | No |
| `protection_period` | Preset + listing seed | Yes | No |
| `early_termination_fee_option/amount` | Preset + listing seed | Yes | No |
| `agency_agreement_timeframe/custom` | Preset + listing seed | Yes | No |
| `referral_fee_percent` | Listing seed | Yes | No |

### 6.4 Tenant Compensation

| Field | Origin | Editable at Bid | Derived |
|---|---|---|---|
| `commission_structure` | Preset + listing seed | Yes | No |
| `lease_fee_type` + sub-fields (11) | Preset + listing seed | Yes | No |
| `interested_purchase_fee_type` | Preset + listing seed | Yes | No |
| `purchase_fee_type` + sub-fields (6) | Preset + listing seed | Yes | No |
| `interested_lease_option_agreement` + terms | Preset + listing seed | Yes | No |
| `broker_fee_timing` + sub-fields (4) | Preset + listing seed | Yes | No |
| `retainer_fee_option/amount/application` | Preset + listing seed | Yes | No |
| `protection_period` | Preset + listing seed | Yes | No |
| `early_termination_fee_option/amount` | Preset + listing seed | Yes | No |
| `agency_agreement_timeframe/custom` | Preset + listing seed | Yes | No |
| `referral_fee_percent` | Listing seed | Yes | No |

### 6.5 Compensation Priority Order

When both a listing seed value and a preset value exist for the same field, the current
`mount()` logic applies compensation from the **listing first**, then the preset's
`applyPresetField()` call **overwrites with the preset value** (because it runs after the
listing-seed block). This means:

> **The preset wins over the listing seed for all compensation fields.**

The listing seed acts as a default-of-last-resort only when no preset exists. This is the
intended behavior — agents should have their standard rates as the authoritative source.

**Exception:** `referral_fee_percent` is seeded from `$l->referral_percentage` and the preset
application block does NOT include it, so the listing seed always controls this field.

---

## 7. Ask AI Integration Readiness

### 7.1 AgentPresetLoader — Current Bridge

`AgentPresetLoader` (source key: `agent_presets`, priority: 70, cache TTL: 30 min) is the
existing bridge between preset data and the Ask AI chat context. It loads all
`AgentDefaultProfile` records for the agent and calls `summarizePreset()` on each.

**Registration:** All five `AgentAiContextScope` values are covered.

### 7.2 Fields Currently Exposed to Ask AI (Public-Safe)

The loader exposes only a curated subset of preset data, filtered through two layers:

**Explicit `PRIVATE_KEYS` exclusion list (36 keys):**
- Contact info: `email`, `phone`
- Specific fee amounts: `purchase_fee_percentage`, `purchase_fee_flat`, `lease_fee_percentage`,
  `lease_fee_flat`, `retainer_fee_amount`, `referral_fee_percent`, `early_termination_fee_amount`,
  `nominal`
- Personal identity: `first_name`, `last_name`, `bio`, `awards_recognition`,
  `what_sets_you_apart`, `why_hire_you`, `review_1/2/3`, `reviews_links`, `intro_video_url`,
  `website_link`, `presentation_link`, `social_media`, `marketing_plan`
- Geographic detail: `cities_served`, `counties_served`, `neighborhoods_served`,
  `primary_areas_served`, `areas_notes`
- License/credentials: `license_no`, `nar_id`, `year_licensed`, `years_experience`,
  `is_full_time`, `transactions_last_12_months`
- Availability: `availability_status`, `avg_response_time`, `communication_style`,
  `preferred_contact_method`, `evenings_available`, `weekends_available`
- Uploads: `business_card_upload_path`, `business_card_link`

**Pattern-based suffix exclusion:**
Any key ending in `_flat_fee`, `_percentage`, `_price`, or `_amount` (except
`retainer_fee_application`) is additionally stripped.

**Fields retained and sent to AI (per preset summary):**
- `services` (joined string)
- `other_services` (joined string)
- `commission_structure`
- `commission_structure_type`
- `purchase_fee_type`
- `lease_fee_type`
- `retainer_fee_option`
- `retainer_fee_application`
- `protection_period`
- `early_termination_fee_option`
- `interested_in_selling` (boolean → Yes/No label)
- `interested_in_property_management` (boolean → Yes/No label)
- `interested_in_property_management_fee`

### 7.3 Fields NOT Yet Exposed But AI-Valuable

The following preset fields are not currently in the AI context but could enrich responses
to prospective clients without compromising privacy:

| Preset Field | Suggested AI Context Value |
|---|---|
| `brokerage_relationship` | Explains representation type (e.g., "Single Agent," "Transaction Broker") |
| `avg_response_time` | Answers "How quickly does this agent respond?" |
| `availability_status` | Answers "Is this agent currently taking new clients?" |
| `evenings_available` / `weekends_available` | Answers "Can I meet evenings/weekends?" |
| `communication_style` | Explains how the agent prefers to communicate |
| `agency_agreement_timeframe` | Explains typical contract length |
| `protection_period` | Explains protection period standard |
| `renewal_fee_type` | Explains lease renewal fee approach (Landlord role) |
| `tenant_broker_commission_structure` | Explains co-brokerage policy (Landlord role) |
| `interested_in_property_management` | Already exposed via boolLabel |
| `years_experience` | High-value trust signal — currently excluded |
| `transactions_last_12_months` | High-value trust signal — currently excluded |
| `is_full_time` | Answers "Is this a full-time agent?" |

### 7.4 Future Prompt Patterns

**Services explanation prompt:**
> "Based on the agent's selected services for [role] / [property type], explain in plain language
> what services this agent includes and what it might mean for the client."

**Compensation explanation prompt:**
> "The agent's standard fee structure is [commission_structure] with a [purchase_fee_type] rate.
> Explain in plain language what this means and when it applies."

**Credentials trust prompt:**
> "The agent has been licensed since [year_licensed] and has completed [transactions_last_12_months]
> transactions in the last year. How does that compare to typical agents?"

**Availability prompt:**
> "The agent's availability status is [availability_status]. They are [evenings/weekends available].
> What should a client expect when working with them?"

### 7.5 Governance Rules (Must Preserve)

- Specific dollar amounts and percentage rates must never be exposed via Ask AI.
- Contact details (`email`, `phone`) must never be exposed.
- License numbers and NAR IDs must never be exposed.
- The `PRIVATE_KEYS` exclusion list and the suffix-pattern filter must be applied to any
  new field added to the loader summary.

---

## 8. Recommended Final UX

### 8.1 End-to-End Flow: Preset → Accepted Agreement

```
┌─────────────────────────────────────────────────────────────────┐
│  STEP 1: Agent creates / updates Preset                         │
│  Route: /agent/presets/{role}/{propertyType}/edit               │
│  • Sets services, compensation defaults, overview, availability  │
│  • Saves to agent_default_profiles.profile_data (JSON)          │
│  • Preset is keyed by role + property_type                       │
│  • Role-default (__default__) serves as fallback                 │
└────────────────────────┬────────────────────────────────────────┘
                         │ mount() calls findAndMap()
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  STEP 2: Agent opens Bid Form                                    │
│  Route: /seller|buyer|landlord|tenant/auction/{id}/bid          │
│  • AgentBidMapperService::findAndMap() is called                 │
│  • All mapped preset fields populate form via applyPresetField() │
│  • Listing-seeded compensation is overwritten by preset values   │
│  • Services filtered to current property-type catalog            │
│  • Compatibility preferences pre-filled from preset              │
│  • Agent reviews and adjusts before submitting                   │
└────────────────────────┬────────────────────────────────────────┘
                         │ Agent submits bid
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  STEP 3: Client Reviews Bid (Pre-filled from Agent's Preset)    │
│  Route: /{role}/auction/{id}/view                               │
│  • Bid accordion card shows all services and compensation        │
│  • Private data modal shows credentials and media                │
│  • Client has three options:                                     │
│    [Accept]  [Reject]  [Counter Offer]                          │
└────────────────────────┬────────────────────────────────────────┘
                         │ Client selects Counter
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  STEP 4: Client Submits Counter Offer                           │
│  • Client may modify: services scope, compensation type/amount,  │
│    retainer, protection period, agency duration                  │
│  • Counter record created in SellerCounterTerm /                 │
│    BuyerCounterBidding / LandlordCounterTerm /                   │
│    TenantCounterBidding                                          │
│  • Bid status becomes "Countered"                                │
│  • Agent is notified                                             │
└────────────────────────┬────────────────────────────────────────┘
                         │ Agent receives counter
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  STEP 5: Agent Counter-Response                                  │
│  • Agent may: Accept counter / Reject counter / Counter-back     │
│  • Counter-back allows modifying countered fields                │
│  • Fields immutable at this stage: license_no, nar_id,          │
│    first_name, last_name, brokerage                              │
│  • Repeat steps 4–5 until one party accepts or rejects           │
└────────────────────────┬────────────────────────────────────────┘
                         │ One party accepts
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  STEP 6: Bid Accepted — AcceptedBidSummary Created              │
│  • AcceptedBidSummary record generated from bid + counter state  │
│  • All compensation fields frozen at accepted values             │
│  • PDF listing packet generated (barryvdh/laravel-dompdf)        │
│  • Both parties receive notification with summary                │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  STEP 7: PDF / Signed Agreement                                  │
│  • x-bid-detail-layout Blade component renders accepted terms    │
│  • PDF available for download by both parties                    │
│  • AcceptedBidSummary cache invalidated on any field change      │
│  • Listing marked is_sold if applicable                          │
└─────────────────────────────────────────────────────────────────┘
```

### 8.2 Hire Me Direct-Entry Flow

This is an existing supplementary path where the preset auto-generates both a listing and a bid:

```
Client visits: /hire/{agentShortId}/{role}/{propertyType}
        │
        ▼
Client clicks "Hire This Agent"
        │
        ▼
AgentDefaultProfile::findForAgentWithFallback()
        │
        ▼
Listing auto-created + bid auto-created from preset via AgentBidMapperService::findAndMap()
        │
        ▼
Client is redirected to the auto-created listing view
        │
        ▼
Client may immediately Accept the auto-bid (no counter needed) or proceed through Step 3+
```

### 8.3 UX Enhancements Recommended

1. **Preset completeness indicator** on the bid form: Show a banner if the agent has no preset
   for this role + property type, with a direct link to create one.
2. **"Loaded from preset" badge** on pre-filled fields: Already partially implemented via
   `defaultProfileLoaded` flag; extend to field-level indicators.
3. **Service scope diff**: When the preset's services differ from the listing's requested
   services, highlight the difference so the agent can reconcile.
4. **Counter preview**: Before submitting a counter, show a diff view of changed fields.
5. **Preset suggestion after first bid**: After an agent submits their first bid for a role +
   property type without a preset, prompt them to save those values as a preset.

---

## 9. Gap Analysis

The following table catalogues every missing field preventing full preset-driven bidding, with
a recommended action.

### 9.1 Mapper Gaps (Field exists in preset; not in `mapFromProfile()`)

| # | Preset Field | Category | Recommended Action |
|---|---|---|---|
| 1 | `years_experience` | Credentials | **ADD TO MAPPER** → new meta key on all 4 bid models |
| 2 | `transactions_last_12_months` | Credentials | **ADD TO MAPPER** → new meta key on all 4 bid models |
| 3 | `is_full_time` | Availability | **ADD TO MAPPER** → new meta key on all 4 bid models |
| 4 | `primary_areas_served` | Service Areas | **ADD TO MAPPER** → new meta key on all 4 bid models |
| 5 | `avg_response_time` | Availability | **ADD TO MAPPER** → new meta key on all 4 bid models |
| 6 | `cities_served` | Service Areas | **ADD TO MAPPER** → new meta key on all 4 bid models |
| 7 | `counties_served` | Service Areas | **ADD TO MAPPER** → new meta key on all 4 bid models |
| 8 | `neighborhoods_served` | Service Areas | **ADD TO MAPPER** → new meta key on all 4 bid models |
| 9 | `areas_notes` | Service Areas | **ADD TO MAPPER** → new meta key on all 4 bid models |
| 10 | `review_1` | Social Proof | **ADD TO MAPPER** → new meta key on all 4 bid models |
| 11 | `review_2` | Social Proof | **ADD TO MAPPER** → new meta key on all 4 bid models |
| 12 | `review_3` | Social Proof | **ADD TO MAPPER** → new meta key on all 4 bid models |
| 13 | `awards_recognition` | Social Proof | **ADD TO MAPPER** → new meta key on all 4 bid models |
| 14 | `intro_video_url` | Media | **ADD TO MAPPER** → new meta key on all 4 bid models |
| 15 | `video_caption` | Media | **ADD TO MAPPER** → new meta key on all 4 bid models |
| 16 | `availability_status` | Availability | **ADD TO MAPPER** → new meta key on all 4 bid models |
| 17 | `evenings_available` | Availability | **ADD TO MAPPER** → new meta key on all 4 bid models |
| 18 | `weekends_available` | Availability | **ADD TO MAPPER** → new meta key on all 4 bid models |
| 19 | `communication_style` | Communication | **DEFER TO PHASE 3** — overlaps with compatibility_preferences |
| 20 | `preferred_contact_method` | Communication | **DEFER TO PHASE 3** — overlaps with compatibility_preferences |

### 9.2 Partial Application Gaps (Field in mapper but not applied by all relevant roles)

| # | Preset Field | Missing Role(s) | Recommended Action |
|---|---|---|---|
| 21 | `retained_deposits` | Buyer, Landlord, Tenant | **ADD TO MAPPER APPLICATION** in 3 bid components |
| 22 | `referral_fee_percent` | All 4 roles (listing-seeded, not preset-seeded) | **ADD TO MAPPER APPLICATION** as optional override when listing value is blank |
| 23 | `services` / `other_services` | Seller (listing-seeded), Buyer (listing-seeded) | **ADD TO MAPPER APPLICATION** for Seller + Buyer when listing services are empty |

### 9.3 Bid Form Fields Without Preset Origin (Bid-Only Fields)

These bid fields have no corresponding preset field. They are set by listing seed only and
cannot be preset-driven by design:

| # | Bid Field | Role(s) | Why No Preset |
|---|---|---|---|
| — | `referral_fee_percent` | All | Controlled by listing's `referral_percentage`; varies per listing |
| — | `photo_enhancements` | Seller, Landlord | Property-specific; inherits from listing |
| — | `custom_enhancement` | Seller, Landlord | Property-specific text; inherits from listing |
| — | `showOpenHouseInput` / `openHouseCount` | Seller | Listing-specific scheduling |

### 9.4 Deprecated / Retire Candidates

No preset fields are recommended for retirement at this time. All 20 unmapped `PROFILE_FIELDS`
have valid use cases in the bid context — they are display/trust fields that belong in the bid
view and PDF. The fields that are purely preset-UI scaffolding (e.g., `video_caption` as a
label for `intro_video_url`) should remain linked as a pair.

---

## 10. Implementation Roadmap

### Phase 1 — Mapper-Only Additions (No UI Changes)
**Effort: Low (1–2 days)**  
**Scope:** Add missing fields to `AgentBidMapperService::mapFromProfile()` only. No bid form
UI changes. Fields become available in the auto-bid flow (Hire Me) and for future Phase 2
bid form display.

**Tasks:**
1. Add the 20 unmapped `PROFILE_FIELDS` to `mapFromProfile()`:
   `years_experience`, `transactions_last_12_months`, `is_full_time`, `primary_areas_served`,
   `avg_response_time`, `cities_served`, `counties_served`, `neighborhoods_served`,
   `areas_notes`, `review_1`, `review_2`, `review_3`, `awards_recognition`,
   `intro_video_url`, `video_caption`, `availability_status`, `evenings_available`,
   `weekends_available`, `communication_style`, `preferred_contact_method`

2. Fix partial application gaps:
   - Add `retained_deposits` application to Buyer, Landlord, and Tenant `mount()` methods.
   - Add `referral_fee_percent` application to all four `mount()` methods, with a
     `$this->referral_fee_percent = $this->referral_fee_percent ?: $mapped['referral_fee_percent']`
     guard (listing value wins; preset fills only when listing provides blank).

3. Add `services` / `other_services` preset application to Seller and Buyer `mount()` methods,
   guarded by `empty($this->services)` (listing-seeded services take priority; preset only fills
   when listing provided none).

**Acceptance:** `AgentBidMapperService::mapFromProfile()` returns all 155 profile_data fields.
Auto-bid (Hire Me) flow populates the new fields into bid meta.

---

### Phase 2 — Bid Form Additions for High-Value Missing Fields
**Effort: Medium (3–5 days per role, 10–15 days total)**  
**Scope:** Add new fields to bid form Livewire components and their corresponding view blade
partials, so agents can see and edit these values when submitting bids.

**Priority order (highest user value first):**

| Priority | Fields | Display Location in Bid Form | Roles |
|---|---|---|---|
| High | `years_experience`, `transactions_last_12_months`, `is_full_time` | Agent Information / Credentials tab | All 4 |
| High | `availability_status`, `evenings_available`, `weekends_available`, `avg_response_time` | Agent Information tab | All 4 |
| High | `review_1`, `review_2`, `review_3` | Agent Overview tab (near reviews_links) | All 4 |
| High | `awards_recognition` | Agent Overview tab | All 4 |
| Medium | `primary_areas_served`, `cities_served`, `counties_served`, `neighborhoods_served`, `areas_notes` | New "Service Areas" tab or Agent Overview tab | All 4 |
| Medium | `intro_video_url`, `video_caption` | Media / Presentation tab | All 4 |

**Tasks per role:**
1. Add `public $field_name;` declarations to the Livewire component.
2. Add `applyPresetField('field_name', ...)` call in `mount()`.
3. Add the field to the `buildProfileData()` / `saveMeta()` call in `submit()`.
4. Add the field to the `$strFields` / scalar fields array in edit-mode load.
5. Add display in the appropriate blade partial.
6. Add display in the bid card accordion / private data modal for listing owners.
7. Update the PDF template to include new fields in the accepted bid summary.

**Acceptance:** New fields appear on the bid form pre-filled from preset; they persist on save;
they appear in bid view cards and PDF.

---

### Phase 3 — Full Services Filter Enforcement, Counter Workflow UI, and Ask AI Integration
**Effort: High (3–6 weeks)**  
**Scope:** Complete the end-to-end preset-driven bidding vision.

**Task 3.1 — Services Filter Enforcement (1 week):**
- Enforce that listing-required services cannot be removed by the agent in the bid form.
- Build a UI diff between listing requested services and preset services.
- Add the `client_custom_services` display panel to all bid form views.
- Allow clients to flag custom service requests during listing creation for all four roles.

**Task 3.2 — Counter Workflow UI (2–3 weeks):**
- Build counter offer form for listing owners (fields: services, compensation type/amount,
  retainer, protection period, agency duration).
- Fix Landlord counter FK asymmetry (currently points to auction, not bid).
- Standardize counter status model across all four roles (Seller has `status` column; others
  rely on `latest()` only).
- Build agent counter-response form (accept / reject / counter-back).
- Add email notifications for counter events.
- Add bid status badge updates for countered state in bid accordion cards.

**Task 3.3 — Ask AI Integration Extensions (1 week):**
- Add `brokerage_relationship`, `avg_response_time`, `availability_status`,
  `evenings_available`, `weekends_available`, `agency_agreement_timeframe`,
  `protection_period`, `renewal_fee_type`, `tenant_broker_commission_structure` to
  `AgentPresetLoader::summarizePreset()` (subject to privacy audit review).
- Add `years_experience`, `transactions_last_12_months`, `is_full_time` to the loader summary
  after confirming they do not constitute private financial information.
- Write prompt templates for services explanation, compensation explanation, and credentials
  trust prompts (see §7.4).

**Task 3.4 — Communication Preferences (0.5 week):**
- Add `communication_style` and `preferred_contact_method` to the bid form under the
  Compatibility / Communication tab, avoiding redundancy with `compatibility_preferences`
  sections.

**Acceptance criteria for Phase 3:**
- A listing owner can counter specific fields and the agent can respond.
- Services that the listing requires cannot be removed from a bid without triggering an
  explicit "not offering this service" action.
- Ask AI can explain an agent's availability and communication approach when asked.

---

## 11. Proof Section

### 11.1 Field Count Totals

| Metric | Count |
|--------|-------|
| Total `PROFILE_FIELDS` audited (`AgentPresetController`) | 43 |
| Total compensation + services + compatibility keys in `profile_data` | 114 |
| **Total `profile_data` fields audited** | **157** |
| Fields in `mapFromProfile()` (current) | 135 |
| Fields in `mapCompatibilityFromProfile()` (current, composite) | 7 sections |
| **Unmapped PROFILE_FIELDS (not in any mapper)** | **20** |
| Partial application gaps (in mapper but not applied by all roles) | 3 field groups |

### 11.2 Bid Meta Key Totals by Role

| Role | Distinct Meta Keys |
|------|--------------------|
| Seller | ~73 |
| Buyer | ~54 |
| Landlord | ~93 |
| Tenant | ~57 |
| **Total unique bid meta keys across all 4 roles** | **~155** (many shared) |

### 11.3 Gap Summary

| Category | Count |
|----------|-------|
| Fields missing from `mapFromProfile()` (true mapper gaps) | 20 |
| Fields in mapper but not applied by all applicable roles | 3 groups (~6 keys) |
| Bid fields with no preset origin (by design) | 4 groups |
| Preset fields recommended for retirement | 0 |
| **Recommended additions to mapper (Phase 1)** | **20 fields + 3 application fixes** |
| **Recommended new bid form fields (Phase 2)** | **~15 fields** |
| **Phase 3 items (counter UI, services enforcement, Ask AI)** | **3 major feature areas** |

### 11.4 Services Catalog Totals

| Metric | Count |
|--------|-------|
| Distinct role × property type catalogs | 14 |
| Approximate total service strings across all catalogs | ~591 |
| Roles where preset services are applied to bids | 2 (Landlord, Tenant) |
| Roles where listing services seed bids (preset not applied) | 2 (Seller, Buyer) |

### 11.5 Source Files Referenced

| File | Purpose |
|------|---------|
| `app/Http/Controllers/AgentPresetController.php` | `PROFILE_FIELDS` constant; `save()` method; all validated compensation keys |
| `app/Models/AgentDefaultProfile.php` | Profile storage model; `findForAgentWithFallback()`; `profile_data` JSON cast |
| `app/Services/AgentBidMapperService.php` | `mapFromProfile()` (135 keys); `mapCompatibilityFromProfile()` (7 sections); `findAndMap()` |
| `app/Services/AgentPresetCatalog.php` | 14 service catalogs; role + property type validation |
| `app/Http/Livewire/Seller/SellerAgentAuctionBid.php` | Seller bid component; preset application block; ~73 meta keys |
| `app/Http/Livewire/Buyer/BuyerAgentAuctionBid.php` | Buyer bid component; preset application block; ~54 meta keys |
| `app/Http/Livewire/Landlord/LandlordAgentAuctionBid.php` | Landlord bid component; preset application block; ~93 meta keys |
| `app/Http/Livewire/Tenant/TenantAgentAuctionBid.php` | Tenant bid component; preset application block; ~57 meta keys |
| `app/Models/SellerAgentAuctionBid.php` | EAV model; counter terms; accepted bid summary |
| `app/Models/BuyerAgentAuctionBid.php` | EAV model; counter terms; accepted bid summary |
| `app/Models/LandlordAgentAuctionBid.php` | EAV model; counter terms (FK asymmetry noted); accepted bid summary |
| `app/Models/TenantAgentAuctionBid.php` | EAV model; counter terms; rejection via meta key `is_rejected` |
| `app/Services/AgentAi/Loaders/AgentPresetLoader.php` | Ask AI bridge; `PRIVATE_KEYS` exclusion; summarizePreset() |

---

## 12. Field Ownership Audit

> **Purpose of this section:** Answer the pending architectural question — *which fields belong to
> the listing creator and which belong to the agent?* — before committing to a final implementation
> plan. This audit is the prerequisite to §10 Phase 3.

### 12.1 Ownership Model

Four ownership classes are defined below. Every bid field across all four roles is classified in
§12.8. The classification drives three decisions: (1) which fields can be preset-driven, (2) which
fields require a counter mechanism, and (3) which fields the listing creator can lock.

| Class | Label | Definition |
|-------|-------|------------|
| **L** | Listing Creator Exclusive | Client fills this in during listing creation. It defines *what* is being sold/rented. Agents cannot set it in a bid. |
| **A** | Agent Exclusive | Only the agent fills this in. The listing creator has no corresponding field in the listing form and forms no expectation about it. |
| **D** | Dual-Entry | **Both** the listing creator (in the listing form) AND the agent (in the bid form) fill in a value for a field with the same key name. Semantic ownership is ambiguous under the current system. This is the critical discovery zone. |
| **P** | Property-Delegated | Not a listing-form input, but auto-seeded from listing context into the bid. The client implicitly owns the context; the agent may refine the value. |

---

### 12.2 Listing Creator Exclusive Fields (Class L)

These fields appear exclusively in the listing creation forms and never in bid forms. They define
the property, transaction parameters, and auction rules. No preset logic touches them.

| Field / Group | Seller | Buyer | Landlord | Tenant | Notes |
|---|:---:|:---:|:---:|:---:|---|
| `address`, `property_city`, `property_state`, `property_zip`, `property_county` | L | L | L | L | Location — defines the subject property |
| `property_lat`, `property_lng`, `google_place_id` | L | L | L | L | Geocoding metadata |
| `property_type` | L | L | L | L | Role-specific: Residential / Income / Commercial / Business / Vacant Land |
| `bedrooms`, `bathrooms`, `total_square_feet`, `year_built`, `zoning`, `lot_dimensions` | L | — | L | — | Physical attributes |
| `starting_price`, `reserve_price`, `buy_now_price` | L | — | L | — | Auction pricing |
| `maximum_budget`, `purchase_price`, `pre_approval_amount` | — | L | — | L | Buyer/Tenant financial capacity |
| `down_payment_type`, `down_payment_amount` | — | L | — | — | Buyer financing |
| `seller_financing_type`, `seller_financing_amount` | — | L | — | — | Buyer financing terms |
| `gross_annual_income`, `annual_revenue` | L | — | L | — | Income property financials |
| `auction_type`, `listing_date`, `expiration_date`, `auction_time`, `end_date`, `end_time` | L | L | L | L | Bidding period controls |
| `listing_status`, `workflow_type`, `is_sold` | L | L | L | L | Platform lifecycle fields |
| `location_dna_preferences` (JSON) | — | L | — | L | Buyer/Tenant geographic criteria |
| `min_acreage`, `total_acreage`, `minimum_heated_square` | — | L | — | — | Buyer property size criteria |
| `property_items` (JSON) | — | L | — | — | Buyer desired property features |
| `bedrooms` (criteria) | — | L | — | L | Buyer/Tenant occupancy criteria |
| `parcel_id`, `annual_property_taxes`, `flood_zone_code` | L | — | L | — | Legal/tax metadata |
| `association_name`, `association_fee_amount`, `association_fee_frequency` | L | — | L | — | HOA details |
| `seller_disclosure_available`, `survey_available`, `lead_based_paint_disclosure` | L | — | — | — | Seller disclosure flags |
| `property_photos` (JSON array of filenames) | L | — | L | — | Property photography |
| `video_link`, `virtual_tour_url` | L | — | L | — | Property tours |
| `description` (native column) | L | — | — | — | Seller property description |
| `additional_details` (native column, Buyer) | — | L | — | — | Buyer listing narrative |
| `working_with_agent` | L | L | L | L | Whether a listing agent is already involved |
| `custom_services` (JSON array with fee) | L | L | L | L | Client-defined bespoke service requests with flat or marketing fees |

---

### 12.3 Agent Exclusive Fields (Class A)

These fields appear **only** in bid forms. Listing creators have no corresponding field in the
listing creation form and express no expectation about them. They are pure agent supply-side data.

| Field / Group | Seller | Buyer | Landlord | Tenant | Notes |
|---|:---:|:---:|:---:|:---:|---|
| `bio` | A | A | A | A | Agent narrative — listing creator never proposes this |
| `why_hire_you` | A | A | A | A | Agent pitch |
| `what_sets_you_apart` | A | A | A | A | Agent differentiator |
| `marketing_plan` | A | A | A | A | Agent's marketing approach |
| `additional_details` (bid meta key) | A | A | A | A | Agent's supplemental notes |
| `year_licensed` | A | A | A | A | Credentials |
| `first_name`, `last_name`, `phone`, `email` | A | A | A | A | Agent contact identity |
| `brokerage`, `license_no`, `nar_id` | A | A | A | A | Agent licensing |
| `reviews_links`, `website_link`, `social_media` | A | A | A | A | Agent social proof |
| `presentation_link`, `business_card_link`, `business_card_stored_path` | A | A | A | A | Agent marketing materials |
| `promoMaterials` (JSON array) | A | A | A | A | Agent promo type/link/file entries |
| `compatibility_agent_response` (7 sections) | A | A | A | A | Agent's compatibility self-assessment |
| `additional_details_broker` | A | A | A | A | Agent's free-text broker notes |
| `renewal_fee_type` + 9 sub-fields | — | — | A | — | Agent-proposed terms for lease renewals; Landlord listing form has no renewal fee inputs |
| `expansion_commission_percentage` | — | — | A | — | Agent-proposed commercial expansion fee; not in Landlord listing form |
| `interested_in_property_management` + 5 fee sub-fields | — | — | A | — | Agent-proposed add-on; not in Landlord listing form |
| `interested_in_selling` + 6 sub-fields | — | — | A | — | Agent-proposed future sale commission; not in Landlord listing form |
| `split_payment_due`, `split_payment_due_other`, `broker_fee_days_after_due_event` | — | — | A | — | Agent's preferred payment timing split |
| `broker_fee_timing` + 4 timing sub-fields | — | — | A | A | *When* the agent wants to be paid; no client input in listing form |
| `nominal` (Seller bid) | A | — | — | — | Seller agent's nominal-fee designation; no listing form field |
| **20 unmapped PROFILE_FIELDS** (see §9.1) | A | A | A | A | `years_experience`, `transactions_last_12_months`, `is_full_time`, availability fields, service area fields, text reviews, `awards_recognition`, `intro_video_url`, `video_caption`, communication fields — none have listing form equivalents |

---

### 12.4 Property-Delegated Fields (Class P)

Not entered by the client in the listing form as a deliberate compensation or service choice, but
auto-seeded from listing context into the bid. The listing creator implicitly supplies these
through their property setup.

| Field | Role(s) | Source | Notes |
|---|---|---|---|
| `photo_enhancements` (array) | Seller, Landlord | Listing `photo_enhancements` meta key | Client checked specific enhancement options when setting up the listing; agent inherits and may refine |
| `custom_enhancement` (text) | Seller, Landlord | Listing `custom_enhancement` meta key | Client's free-text enhancement note; agent inherits |
| `service_type` | All | Listing `service_type` meta key | Full Service vs. Limited Service — set at listing creation |
| `user_type` | All | Listing `user_type` meta key | Role identifier; agent cannot change |
| `isBiddingPeriodListing` | All | Listing `auction_type` | Determines bid display behavior |
| `isListingCreatedByAgent` | All | `$auction->isCreatedByAgent()` | Governs certain UI conditional paths |

---

### 12.5 Dual-Entry Fields (Class D) — The Critical Discovery

**This is the central finding of the ownership audit.**

The following fields appear as inputs in **both** the listing creation form (filled in by the listing
creator) AND the bid form (filled in by the agent). The same meta key name carries two different
values from two different parties. Under the current system, only the agent's bid value is stored in
the bid record — the listing creator's original proposal is silently overwritten by the preset (§6.5).

#### 12.5.A — Compensation Terms (Class D, all four roles)

These fields appear in the listing form because the listing creator expresses an **expectation**
about the fee structure they are willing to accept. The same fields appear in the bid because the
agent is making an **offer** of their actual rate. The current system treats the listing creator's
values as seed defaults — the agent's preset overwrites them without surfacing any difference.

| Field | Seller Listing | Buyer Listing | Landlord Listing | Tenant Listing | In All Bids |
|---|:---:|:---:|:---:|:---:|:---:|
| `commission_structure` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `commission_structure_type` | ✓ | — | — | ✓ | ✓ |
| `commission_structure_type_fee_flat` | ✓ | — | — | ✓ | ✓ (Seller, Tenant) |
| `commission_structure_type_fee_flat_combo` | ✓ | — | — | ✓ | ✓ (Seller, Tenant) |
| `commission_structure_type_fee_percentage` | ✓ | — | — | ✓ | ✓ (Seller, Tenant) |
| `commission_structure_type_fee_percentage_combo` | ✓ | — | — | ✓ | ✓ (Seller, Tenant) |
| `commission_structure_type_fee_other` | ✓ | — | — | — | ✓ (Seller) |
| `purchase_fee_type` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `purchase_fee_flat` | ✓* | ✓* | ✓* | ✓* | ✓ |
| `purchase_fee_percentage` | ✓* | ✓* | — | ✓* | ✓ |
| `purchase_fee_percentage_combo` | ✓* | ✓* | ✓* | ✓* | ✓ |
| `purchase_fee_flat_combo` | ✓* | ✓* | ✓* | ✓* | ✓ |
| `purchase_fee_other` | ✓* | ✓* | ✓* | ✓* | ✓ |
| `interested_purchase_fee_type` | ✓ | — | — | ✓ | ✓ (Seller, Tenant) |
| `seller_leasing_fee_type` + 13 sub-fields | ✓ | — | — | — | ✓ (Seller) |
| `interested_lease_option` (Buyer) | — | ✓ | — | — | ✓ (Buyer) |
| `lease_fee_type` + 11 sub-fields | — | ✓* | — | — | ✓ (Buyer, Tenant) |
| `interested_lease_option_agreement` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `lease_type`, `lease_value` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `purchase_type`, `purchase_value` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `protection_period` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `early_termination_fee_option` | ✓ | ✓ | — | ✓ | ✓ |
| `early_termination_fee_amount` | ✓ | ✓ | — | ✓ | ✓ |
| `retainer_fee_option` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `retainer_fee_amount` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `retainer_fee_application` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `agency_agreement_timeframe` | ✓ | ✓ | — | ✓ | ✓ |
| `agency_agreement_custom` | ✓ | ✓ | — | ✓ | ✓ |
| `brokerage_relationship` | ✓ | ✓ | — | ✓ | ✓ |
| `tenant_broker_commission_structure` | — | — | ✓ | ✓ | ✓ (Landlord, Tenant) |

> `✓*` = sub-field present in listing form only when the corresponding `_type` selector is chosen.
> Not all listing forms surface every amount sub-field — some are bid-form-only.

**Count of dual-entry compensation fields: ~40 distinct meta keys shared between listing forms
and bid forms across one or more roles.**

#### 12.5.B — Services (Class D, special sub-case)

`services` (the catalog service array) appears in listing forms for all four roles and in all four
bid forms. However the ownership semantics differ by role under the current system:

| Role | Listing has `services`? | Bid seeded from listing? | Preset overrides? | Net effect |
|------|:-----------------------:|:------------------------:|:-----------------:|------------|
| Seller | Yes | **Yes** | No | Listing creator's selection controls bid |
| Buyer | Yes | **Yes** | No | Listing creator's selection controls bid |
| Landlord | Yes | Yes | **Yes** (if preset non-empty) | Agent's preset controls bid |
| Tenant | Yes | Yes | **Yes** (if preset non-empty) | Agent's preset controls bid |

This is an **ownership inconsistency within the same field across roles**. There is no design
rationale documented for why Seller/Buyer services are listing-owned while Landlord/Tenant
services are preset-owned.

`other_services` (free-text custom services array) has identical inconsistency.

#### 12.5.C — `referral_percentage` / `referral_fee_percent` — The One Correct Example

This is the single field in the system where the platform currently enforces client ownership:

- The listing creator sets `referral_percentage` in the listing form.
- The bid seeds `referral_fee_percent` from `$auction->get->referral_percentage`.
- The preset application block explicitly excludes `referral_fee_percent`.
- The agent can still edit it in the bid form, but the preset never overwrites it.

**This field is the correct model for client-proposed terms.** All Class D compensation fields
should follow the same pattern: listing value seeds the bid field; preset does not override it;
the agent's edit of the field in the bid form is their explicit response to the client's proposal.

---

### 12.6 The Dual-Entry Architectural Problem

#### 12.6.A — What the current system actually does

When a listing creator opens the listing form and enters `commission_structure = "Seller's Broker
to Compensate Buyer's Broker"` with `purchase_fee_type = "Percentage"` and
`purchase_fee_percentage = "3"`, they are expressing: *"I expect to pay my agent 3% in this
compensation structure."*

When an agent opens the bid form, the following sequence fires:

```
1. mount() seeds all compensation fields from $auction->get
   → purchase_fee_percentage = "3"   (from listing)

2. Preset application block fires (if agent has preset)
   → applyPresetField('purchase_fee_percentage', '2.5', $count)
   → purchase_fee_percentage = "2.5"  (preset overwrites silently)

3. Agent submits bid with purchase_fee_percentage = "2.5"
   → Bid record stores "2.5"; listing's "3" is never recorded in the bid
```

The listing creator sees the bid showing 2.5%, with no indication that they proposed 3%. There is
no diff, no notification, no counter prompt. The client's expression of intent has been silently
discarded.

#### 12.6.B — Current system classification

Under the current system, all Class D compensation fields behave as if they are **Agent Exclusive**
(Class A) whenever the agent has a preset. The listing creator's values function only as
last-resort defaults for agents without a preset.

#### 12.6.C — Why this matters for the implementation plan

The original audit (§4, §10 Phase 3) described a counter workflow but did not address the question
of **what triggers a counter**. Without resolving ownership, there is no trigger condition: if the
agent's preset can silently set any rate without flagging a difference, the counter system is only
reachable by clients who manually notice the discrepancy on the bid card.

---

### 12.7 Three Architectural Options

Three options exist for resolving dual-entry ownership. The choice determines the entire
implementation scope of Phase 3.

#### Option 1 — Preserve Preset Supremacy (Status Quo)

**How it works:** Agent preset silently overwrites all listing-creator compensation proposals.
The listing creator's values are advisory defaults with no enforcement.

**Pros:** No additional storage, no UI changes, simplest implementation.

**Cons:**
- The listing form's compensation tab becomes misleading — clients enter values that have no
  downstream effect when the agent has a preset.
- Clients who read the bid card and see a different rate than what they entered have no
  platform-provided explanation.
- The counter system (§4) must be triggered manually with no systemic prompt.
- `referral_percentage` is inconsistently client-protected while all other compensation fields
  are not.

**Verdict:** Not recommended. Creates a platform trust problem.

#### Option 2 — Client Binds (Hard Lock)

**How it works:** Compensation fields entered in the listing form are locked in the bid form.
Agents cannot change them — they can only accept or not bid.

**Pros:** Eliminates ambiguity. Client is clearly in control of terms.

**Cons:**
- Removes the agent's ability to offer better or different terms.
- Prevents presets from functioning for compensation at all.
- Makes the auction model adversarial — agents who disagree with the client's proposed rate
  cannot participate without a counter mechanism.
- Nullifies the entire preset-driven compensation architecture.

**Verdict:** Not recommended. Undermines the auction/bidding value proposition.

#### Option 3 — Transparent Negotiation (Recommended)

**How it works:**
1. Listing creator's compensation values become the **client's proposal** — stored on the listing.
2. Agent's bid compensation values become the **agent's offer** — stored on the bid.
3. When client_proposal ≠ agent_offer for any field, the system flags the delta.
4. The client sees both values in a side-by-side "You proposed / Agent offers" display.
5. The counter workflow (§4) becomes the natural resolution path for any delta.
6. The preset populates the agent's offer side — it no longer "overwrites" the client's
   proposal because the two values are stored and displayed separately.

**Pros:**
- Makes the auction model genuinely transparent.
- Legitimizes the listing form's compensation tab — clients now know their input matters.
- Makes the counter system purposeful and discoverable.
- `referral_percentage` handling becomes consistent with all other compensation fields.
- Presets remain fully functional — they populate the agent's side of the negotiation.

**Cons:**
- Requires storing a comparison snapshot (listing's compensation values are already stored
  on the listing model, so no new storage is needed — only the comparison UI is new).
- Adds UI complexity to the bid review card (side-by-side display).

**Verdict:** Recommended. This is the correct model for an auction-based platform.

---

### 12.8 Recommended Ownership Assignment (Master Classification Table)

The table below assigns the recommended final ownership class to every bid field group across
all four roles. "Recommended Class" reflects the Option 3 (Transparent Negotiation) model.

#### 12.8.A — Agent Overview, Credentials, and Media

| Field Group | Current Class | Recommended Class | Notes |
|---|---|---|---|
| `bio`, `why_hire_you`, `what_sets_you_apart`, `marketing_plan`, `additional_details` | A | **A** | Pure agent narrative |
| `year_licensed`, `first_name`, `last_name`, `phone`, `email`, `brokerage`, `license_no`, `nar_id` | A | **A** | Agent identity |
| `reviews_links`, `website_link`, `social_media`, `promoMaterials` | A | **A** | Agent social proof |
| `presentation_link`, `business_card_link`, `business_card_stored_path` | A | **A** | Agent materials |
| `compatibility_agent_response` (7 sections) | A | **A** | Agent self-assessment |
| 20 unmapped PROFILE_FIELDS | A | **A** | All agent-supply-side |

#### 12.8.B — Services

| Field | Current Class | Recommended Class | Notes |
|---|---|---|---|
| `services` (Seller, Buyer) | D → listing wins | **D → Transparent** | Client proposes requested services; agent offers services they'll provide; diff displayed |
| `services` (Landlord, Tenant) | D → preset wins | **D → Transparent** | Same model — inconsistency removed |
| `other_services` (all roles) | D → mixed | **D → Transparent** | Same transparency model applies |
| `photo_enhancements`, `custom_enhancement` | P | **P → Agent-Editable** | Client-selected in listing; agent may add/remove options in bid |
| `custom_services` (client-originated) | L | **L → Read-Only in bid** | Client's custom service requests visible in bid form as read-only reference panel; agent acknowledges or declines each item |

**Recommended services model (Option 3 applied):**
- Store two distinct service sets: `listing.services` (client's desired list) and `bid.services`
  (agent's offered list).
- Display a diff UI on the bid card: services the client wanted but agent didn't offer = "Gap"
  items; services the agent offered that the client didn't request = "Bonus" items.
- Client can accept the gap (downgrade expectations) or trigger a counter.

#### 12.8.C — Compensation Terms

| Field Group | Current Class | Recommended Class | Notes |
|---|---|---|---|
| `commission_structure` | D → preset wins | **D → Transparent** | Client proposed; agent offers; diff flagged |
| `commission_structure_type` + 5 sub-fields | D → preset wins | **D → Transparent** | Same |
| `purchase_fee_type` + amount sub-fields | D → preset wins | **D → Transparent** | Core listing commission rate |
| `interested_purchase_fee_type` | D → preset wins | **D → Transparent** | Whether agent is interested in leasing commission |
| `seller_leasing_fee_type` + 13 sub-fields | D → preset wins | **D → Transparent** | Seller listing-specific leasing commission |
| `interested_lease_option` (Buyer) | D → preset wins | **D → Transparent** | Buyer's preferred lease option |
| `lease_fee_type` + 11 sub-fields (Buyer/Tenant) | D → preset wins | **D → Transparent** | Lease commission rate |
| `interested_lease_option_agreement` | D → preset wins | **D → Transparent** | Lease-option agreement willingness |
| `lease_type`, `lease_value`, `purchase_type`, `purchase_value` | D → preset wins | **D → Transparent** | Lease-option fee amounts |
| `protection_period` | D → preset wins | **D → Transparent** | How long the agent's exclusivity lasts |
| `early_termination_fee_option` + amount | D → preset wins (Seller/Buyer/Tenant); absent (Landlord) | **D → Transparent** | Early exit fee |
| `retainer_fee_option` + amount + application | D → preset wins | **D → Transparent** | Upfront retainer terms |
| `agency_agreement_timeframe` + custom | D → preset wins | **D → Transparent** | Contract duration |
| `brokerage_relationship` | D → preset wins | **D → Transparent** | Representation type |
| `tenant_broker_commission_structure` (Landlord, Tenant) | D → preset wins | **D → Transparent** | Co-brokerage structure |
| `referral_fee_percent` | D → **listing wins** *(already correct)* | **D → Transparent (keep current behavior)** | Only currently correct D field |

#### 12.8.D — Agent-Exclusive Compensation (not in listing forms)

| Field Group | Current Class | Recommended Class | Notes |
|---|---|---|---|
| `nominal` (Seller) | A | **A** | Agent's nominal-fee designation |
| `retained_deposits` (Seller) | A | **A** | Agent's deposit retention terms |
| `additional_details_broker` | A | **A** | Agent's free-text broker notes |
| `renewal_fee_type` + 9 sub-fields (Landlord) | A | **A** | No listing-form equivalent |
| `expansion_commission_percentage` (Landlord) | A | **A** | No listing-form equivalent |
| `interested_in_property_management` + 5 fee sub-fields (Landlord) | A | **A** | No listing-form equivalent |
| `interested_in_selling` + 6 sub-fields (Landlord) | A | **A** | No listing-form equivalent |
| `broker_fee_timing` + 4 sub-fields (Landlord, Tenant) | A | **A** | No listing-form equivalent |
| `split_payment_due` + 2 sub-fields (Landlord) | A | **A** | No listing-form equivalent |
| `purchase_fee_rental_period` (Landlord residential) | A | **A** | No listing-form equivalent |
| `purchase_fee_net_aggregate` + 7 commercial sub-fields (Landlord) | A | **A** | No listing-form equivalent |
| `tenant_broker_percentage` + 5 structure sub-fields (Landlord) | A | **A** | No listing-form equivalent |
| `landlord_broker_*` + 5 selling sub-fields (Landlord) | A | **A** | No listing-form equivalent |

---

### 12.9 Implementation Impact of Ownership Resolution

The table below shows exactly what must change in each layer of the stack to implement Option 3
(Transparent Negotiation) for the Class D fields.

#### 12.9.A — No New Storage Required

The listing's compensation values already persist on the listing model (EAV metas for Landlord/
Tenant, native columns for Seller/Buyer where applicable). The bid's compensation values persist
on the bid model's EAV metas. Both datasets already exist in the database — they have never been
compared.

The only new data needed: a **comparison snapshot** captured at bid submission time, stored as a
single JSON column or EAV key on the bid (e.g., `client_proposed_terms` JSON). This records the
listing's compensation values at the moment the bid was submitted, enabling historical comparison
even if the listing is later edited.

#### 12.9.B — Changes to `mount()` Logic

**Current behavior for all compensation Class D fields:**
```
listing_seed → write to $this->field
preset.applyPresetField → overwrite $this->field  (silent)
```

**Recommended behavior:**
```
listing_seed → write to $this->field  (unchanged)
preset.applyPresetField → overwrite $this->field  (unchanged)
listing_seed → ALSO write to $this->client_proposed_[field]  (new read-only shadow copy)
```

The shadow copy (`client_proposed_*`) is read-only in the bid form — the agent cannot edit it.
It is displayed alongside the agent's editable field as "Client's request: X / Your offer: Y."

Alternative lighter implementation: do not add shadow properties to the component; instead,
compute the diff at view/display time by re-reading `$auction->get->field` against
`$bid->get->field`. This avoids modifying all four bid components and instead puts the diff
logic in the bid card view.

#### 12.9.C — Changes to Bid Card (View Layer)

For each Class D compensation field that is rendered on the bid accordion card, the view should
show two values when they differ:

```
Commission Rate:   [Client's request: 3%]   [Agent's offer: 2.5%]   [Counter] [Accept]
```

When client_proposal == agent_offer: display only the agreed value (no noise for the happy path).

This affects: bid accordion card Blade partials, private data modal, and the
`x-bid-detail-layout` accepted bid summary component.

#### 12.9.D — Changes to Services Display

For services, the diff model renders:

```
Requested services (client):   [✓ List on MLS] [✓ Professional photography] [✓ 3D virtual tour] [✗ Drone photography]
Offered services (agent):      [✓ List on MLS] [✓ Professional photography] [✗ 3D virtual tour] [✓ Drone photography]

Gaps (client wants but agent didn't offer):  "Provide a 3D virtual tour"
Extras (agent offers beyond request):        "Provide aerial (drone) photography"
```

#### 12.9.E — Counter Trigger Logic

Under Option 3, the counter workflow (§4) is triggered automatically when:
- Any Class D compensation field value in the bid differs from the listing's value by more than
  a configurable tolerance (e.g., rate differs by > 0.1%).
- OR: the bid's service set has any Gap items (client-requested services not included by agent).

This makes countering purposeful and eliminates the need for clients to manually scrutinize
every field.

#### 12.9.F — Preset Application Behavior Under Option 3

Presets remain fully functional. The only change: instead of the preset "overwriting" the client's
value, the preset is now understood as the agent's **offer** — a distinct value from the client's
**proposal**. The system stores both. The UX surfacing of the difference is new; the underlying
preset-application code in `mount()` does not change.

This is architecturally clean: `AgentBidMapperService::mapFromProfile()` continues to map preset
fields exactly as it does today. No changes to `applyPresetField()`. The diff comparison logic
lives entirely in the view layer and optionally in a lightweight snapshot service.

#### 12.9.G — Listing Form Guidance Update

Under Option 3, the listing form's compensation tab needs updated helper text to explain that
client-entered values are a **proposal that agents may respond to**, not a fixed price:

> *"Enter your preferred compensation terms. Agents will see your preferences when they submit a
> bid and may offer different rates. You can accept their terms or use the counter offer tool to
> negotiate."*

This one-line change to the listing form's compensation tab guidance prevents client confusion
and validates the field's purpose.

---

### 12.10 Ownership Audit Summary

| Class | Field Count (across all roles) | Key Fields | Action Required |
|-------|-------------------------------|-----------|----------------|
| **L — Listing Creator Exclusive** | ~30 field groups | Property details, pricing, auction settings, legal/disclosures | None — already correctly isolated from bid/preset system |
| **A — Agent Exclusive** | ~60 field groups | Identity, overview, media, compatibility, unmapped PROFILE_FIELDS, agent-only compensation terms | Continue current preset-auto-population behavior |
| **D — Dual-Entry** | ~45 field groups | All listing-form compensation terms, `services`, `other_services`, `brokerage_relationship` | Implement Option 3: store both values, surface diff, link to counter workflow |
| **P — Property-Delegated** | ~6 field groups | `photo_enhancements`, `custom_enhancement`, `service_type`, `user_type`, auction metadata | No preset-override; read-only seed from listing |

**The single most important finding:** Approximately 45 compensation and services field groups are
currently treated as agent-exclusive (preset wins) even though the listing creator enters the same
fields in the listing form with the intent of proposing terms. Resolving this with Option 3 makes
the platform architecturally honest, makes the counter workflow purposeful, and eliminates the
silent discard of client intent that currently occurs when an agent has a preset.

**The one field already correct:** `referral_fee_percent` / `referral_percentage` is the platform's
sole existing example of correct client-owned compensation handling. It is the reference
implementation for how all Class D fields should behave under Option 3.

---

*End of Agent Preset-Driven Bidding Architecture Audit*
