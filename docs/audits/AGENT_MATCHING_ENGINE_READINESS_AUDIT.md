# Agent Matching Engine Readiness Audit

**Date:** June 16, 2026
**Scope:** All four hire-agent flows — Seller, Buyer, Landlord, Tenant
**Purpose:** Determine which fields can be scored, which cannot, and which require normalization before scoring is possible. Identify the gap between the current engine and a full-fidelity matching engine.

---

## 1. Executive Summary

The platform already has a working two-dimensional match engine for all four roles. It scores **Services** (50%) and **Broker Compensation / Agreement Terms** (50%) using logical field groups with cascade-deactivation logic and string-normalization. This is a solid foundation.

The engine is **blind to the largest half of the data.** Every field a client fills out to describe *what they want in a property or agent* — location, budget, timeline, property features, financing preferences, personality fit, and client qualifications — is collected, stored, and then never consulted during scoring. These fields represent the highest-signal opportunity to differentiate match quality.

**Headline numbers (fields collected vs. fields scored):**

| Role | Fields Collected | Fields Currently Scored | Scoring Coverage |
|---|---|---|---|
| Seller | ~55 | 16 term groups + services | ~30% |
| Buyer | ~45 | 16 term groups + services | ~35% |
| Landlord | ~65 | 17 term groups + services | ~25% |
| Tenant | ~50 | 17 term groups + services | ~30% |
| Agent (all roles) | ~40 | services + terms (via bid) | Agent side fully wired |

---

## 2. Current Engine Architecture

### 2.1 Scoring Formula (All Four Roles)

```
Overall Match % = 50% × Services Score + 50% × Terms Score
(only non-zero components averaged)
```

**Services Score** — baseline client's requested services are filtered against the role+property-type catalog. Each requested service the agent also offers counts as matched. Extra agent services don't inflate the denominator.

**Terms Score** — each `LOGICAL_FIELD_GROUPS` entry = 1 logical decision. Sub-inputs for one concept (e.g., `lease_fee_type` + `lease_fee_flat` + `lease_fee_percentage`) are concatenated into one composite value, so no UI field inflation. Conditional groups (parent = "No") are cascade-excluded from the denominator.

### 2.2 Normalization Already in Place

| Normalizer | What It Handles |
|---|---|
| `normalizeForMatch($v)` | Strips whitespace, `$`, `%`, `,`; lowercases; normalizes smart quotes |
| `normalizeService($s)` | Smart quotes → straight; lowercase; trim |
| Catalog filter on services | Prevents wrong-role services from contaminating scores |
| Cascade deactivation | Excludes child groups when parent = "No" in bid |

### 2.3 Helpers & Models Involved

| Component | Path |
|---|---|
| `SellerBidMatchScoreHelper` | `app/Helpers/SellerBidMatchScoreHelper.php` |
| `BuyerBidMatchScoreHelper` | `app/Helpers/BuyerBidMatchScoreHelper.php` |
| `LandlordBidMatchScoreHelper` | `app/Helpers/LandlordBidMatchScoreHelper.php` |
| `TenantBidMatchScoreHelper` | `app/Helpers/TenantBidMatchScoreHelper.php` |
| `HasCompatibilityPreferences` | `app/Models/Concerns/HasCompatibilityPreferences.php` |
| `AgentBidMapperService` | `app/Services/AgentBidMapperService.php` |

---

## 3. Field Inventory by Role

### Legend

| Symbol | Meaning |
|---|---|
| ✅ **Scored** | Currently inside a `LOGICAL_FIELD_GROUPS` entry |
| 🟡 **Scorable** | Can be scored today with minor work; data exists and is structured |
| 🔴 **Needs Normalization** | Data exists but format variation, range values, or multi-value arrays require a normalization layer before scoring |
| ⬜ **Not Scorable** | Free-text narrative, credential links, photos — no meaningful scalar comparison |
| ❓ **No Agent Mirror** | Client field exists but agent never enters an equivalent value |

---

## 4. Seller Fields

### 4.1 Seller Preference Fields (What the Seller Collects)

#### Broker Compensation & Agreement Terms
*Storage: EAV meta on `seller_agent_auction_metas`*

| Field | Type | Scored? | Notes |
|---|---|---|---|
| `purchase_fee_type` | enum | ✅ Scored | Group 1 |
| `purchase_fee_flat` | decimal | ✅ Scored | Group 1 sub-field |
| `purchase_fee_percentage` | decimal | ✅ Scored | Group 1 sub-field |
| `purchase_fee_flat_combo` | decimal | ✅ Scored | Group 1 sub-field |
| `purchase_fee_percentage_combo` | decimal | ✅ Scored | Group 1 sub-field |
| `purchase_fee_other` | string | ✅ Scored | Group 1 sub-field |
| `nominal` | decimal | ✅ Scored | Group 2 |
| `commission_structure` | enum | ✅ Scored | Group 3 |
| `commission_structure_type` | enum | ✅ Scored | Group 4 |
| `interested_purchase_fee_type` | Yes/No | ✅ Scored | Group 5 parent |
| `seller_leasing_fee_type` + sub-fields | enum + decimals | ✅ Scored | Group 5a (14 sub-fields) |
| `interested_lease_option_agreement` | Yes/No | ✅ Scored | Group 6 parent |
| `lease_type` / `lease_value` | enum + decimal | ✅ Scored | Group 6a |
| `purchase_type` / `purchase_value` | enum + decimal | ✅ Scored | Group 6b |
| `early_termination_fee_option` | Yes/No | ✅ Scored | Group 7 |
| `early_termination_fee_amount` | decimal | ✅ Scored | Group 8 |
| `retainer_fee_option` | Yes/No | ✅ Scored | Group 9 |
| `retainer_fee_amount` | decimal | ✅ Scored | Group 10 |
| `retainer_fee_application` | enum | ✅ Scored | Group 11 |
| `retained_deposits` | string | ✅ Scored | Group 12 |
| `protection_period` | string | ✅ Scored | Group 13 |
| `agency_agreement_timeframe` | string | ✅ Scored | Group 14 |
| `brokerage_relationship` | enum | ✅ Scored | Group 15 |
| `referral_fee_percent` | decimal | ✅ Scored | Group 16 |

#### Seller Sale Terms (Desired Purchase Conditions)
*Storage: EAV meta*

| Field | Type | Scored? | Agent Mirror? | Notes |
|---|---|---|---|---|
| `maximum_budget` / `min_price` | decimal | 🟡 Scorable | ❓ No | Agent does not enter a sale price estimate |
| `initial_deposit_requested` | decimal | 🟡 Scorable | ❓ No | |
| `initial_deposit_timeframe` | string | 🟡 Scorable | ❓ No | |
| `preferred_inspection_period` | string/days | 🔴 Needs Norm | ❓ No | "10 days", "14 days" — needs days integer extraction |
| `appraisal_contingency_preference` | enum | 🟡 Scorable | ❓ No | |
| `financing_contingency_preference` | enum | 🟡 Scorable | ❓ No | |
| `sale_of_buyer_property_contingency` | Yes/No | 🟡 Scorable | ❓ No | |
| `seller_contribution_credit_offered` | decimal/% | 🔴 Needs Norm | ❓ No | May be flat or percent |
| `possession_preference` | string | 🔴 Needs Norm | ❓ No | Free text ("60 days", "At closing") |
| `home_warranty_offered` | Yes/No | 🟡 Scorable | ❓ No | |
| `hoa_condo_association_terms` | string | ⬜ Not Scorable | ❓ No | Free-text |

#### Property Information
*Storage: native columns + EAV meta*

| Field | Type | Scored? | Agent Mirror? | Notes |
|---|---|---|---|---|
| `address` / `city_id` / `county_id` / `state_id` | FK/string | 🟡 Scorable | ❓ No | Geo-match potential |
| `bedroom_id` / `bathroom_id` | FK enum | 🟡 Scorable | ❓ No | Enums — exact match |
| `sqft` | integer | 🔴 Needs Norm | ❓ No | Range/min needed |
| `property_items` | JSON array | 🔴 Needs Norm | ❓ No | Multi-select, needs set-intersection |
| `appliances` | JSON array | 🔴 Needs Norm | ❓ No | Multi-select |
| `view_preference` | JSON array | 🔴 Needs Norm | ❓ No | Multi-select |
| `financings` | JSON array | 🔴 Needs Norm | ❓ No | Accepted financing types |
| `pool_needed` / `garage_needed` | string | 🟡 Scorable | ❓ No | |

#### Representation & Compatibility (7 Sections)
*Storage: `compatibility_preferences.agent_response.{section}` in meta*

| Section | Type | Scored? | Agent Mirror? | Notes |
|---|---|---|---|---|
| `communication_preferences` | JSON assoc | ⬜ Not Scorable | Partial | Agent stores free-form; no structured scoring schema |
| `negotiation_approach` | JSON assoc | ⬜ Not Scorable | Partial | |
| `guidance_style` | JSON assoc | ⬜ Not Scorable | Partial | |
| `collaboration_preferences` | JSON assoc | ⬜ Not Scorable | Partial | |
| `transaction_strategy` | JSON assoc | ⬜ Not Scorable | Partial | |
| `representation_philosophy` | JSON assoc | ⬜ Not Scorable | Partial | |
| `representation_priorities` | JSON assoc | ⬜ Not Scorable | Partial | |

> **Note:** The 7 compatibility sections are stored via `HasCompatibilityPreferences` on the *listing* model (client-side) and also on the *bid* model (agent-side via `AgentBidMapperService`). Infrastructure for bi-directional storage exists. Scoring is absent. The internal schema is currently arbitrary JSON — a question-answer schema would be required before these can be scored.

---

## 5. Buyer Fields

### 5.1 Broker Compensation & Agreement Terms

| Field | Scored? | Notes |
|---|---|---|
| `commission_structure` | ✅ Scored | Group 1 |
| `purchase_fee_type` + 5 sub-fields | ✅ Scored | Group 2 |
| `interested_lease_option` | ✅ Scored | Group 3 |
| `lease_fee_type` + 10 sub-fields | ✅ Scored | Group 4 |
| `interested_lease_option_agreement` | ✅ Scored | Group 5 |
| `lease_type` / `lease_value` | ✅ Scored | Group 6 |
| `purchase_type` / `purchase_value` | ✅ Scored | Group 7 |
| `protection_period` | ✅ Scored | Group 8 |
| `early_termination_fee_option` | ✅ Scored | Group 9 |
| `early_termination_fee_amount` | ✅ Scored | Group 10 |
| `retainer_fee_option` | ✅ Scored | Group 11 |
| `retainer_fee_amount` | ✅ Scored | Group 12 |
| `retainer_fee_application` | ✅ Scored | Group 13 |
| `agency_agreement_timeframe` | ✅ Scored | Group 14 |
| `brokerage_relationship` | ✅ Scored | Group 15 |
| `referral_fee_percent` | ✅ Scored | Group 16 |

### 5.2 Buyer Property Preferences (Unscored)

| Field | Type | Scored? | Agent Mirror? | Notes |
|---|---|---|---|---|
| `maximum_budget` | decimal | 🔴 Needs Norm | ❓ No | Budget ceiling — range overlap logic needed |
| `cities` | JSON array | 🔴 Needs Norm | ❓ No | Multi-select; agent doesn't enter target zones |
| `counties` | JSON array | 🔴 Needs Norm | ❓ No | |
| `property_type` | enum | 🟡 Scorable | ✅ Yes | Agent fills property_type on bid |
| `property_items` | JSON array | 🔴 Needs Norm | ❓ No | Set-intersection needed |
| `bedrooms` / `bathrooms` | string/int | 🔴 Needs Norm | ❓ No | "3+" vs "3" — needs min-value normalization |
| `min_acreage` | decimal | 🟡 Scorable | ❓ No | |
| `minimum_cap_rate` | decimal | 🟡 Scorable | ❓ No | Income property only |
| `offered_financing` | JSON array | 🔴 Needs Norm | ❓ No | Multi-select; agent side has no mirror |
| `purchase_price` | decimal | 🟡 Scorable | ❓ No | |
| `down_payment_amount` | decimal | 🟡 Scorable | ❓ No | |
| `down_payment_type` | `$`/`%` | 🔴 Needs Norm | ❓ No | Unit disambiguation needed |
| `earnest_money_amount` | decimal | 🟡 Scorable | ❓ No | |
| `inspection_period_days` | integer | 🟡 Scorable | ❓ No | |
| `possession_preference` | string | 🔴 Needs Norm | ❓ No | Free text |
| `interested_lease_option` | Yes/No | ✅ Scored | ✅ Yes | Already in terms engine |

### 5.3 Buyer Agent Hire Preferences (Unscored)

| Field | Type | Scored? | Agent Mirror? | Notes |
|---|---|---|---|---|
| `service_type` | Full/Limited | 🟡 Scorable | ✅ Yes | Agent bids on a specific service_type listing |
| `services` | JSON array | ✅ Scored | ✅ Yes | Already in services engine |
| `commission_structure` | enum | ✅ Scored | ✅ Yes | |
| `brokerage_relationship` | enum | ✅ Scored | ✅ Yes | |
| `compatibility_preferences` (7) | JSON | ⬜ Not Scorable | Partial | Same gap as Seller |

---

## 6. Landlord Fields

### 6.1 Broker Compensation & Agreement Terms

| Field | Scored? | Notes |
|---|---|---|
| `purchase_fee_type` + 12 sub-fields | ✅ Scored | Group 1 (13 sub-inputs) |
| `broker_fee_timing` + 7 sub-fields | ✅ Scored | Group 2 |
| `renewal_fee_type` + 6 sub-fields | ✅ Scored | Group 3 |
| `expansion_commission_percentage` | ✅ Scored | Group 4 |
| `tenant_broker_commission_structure` | ✅ Scored | Group 5a |
| `tenant_broker_fee_structure` + 5 sub-fields | ✅ Scored | Group 5b |
| `interested_lease_option_agreement` | ✅ Scored | Group 6 |
| `lease_type` / `lease_value` | ✅ Scored | Group 7 |
| `purchase_type` / `purchase_value` | ✅ Scored | Group 8 |
| `interested_in_selling` | ✅ Scored | Group 9 |
| `interested_in_selling_type` | ✅ Scored | Group 10 |
| `protection_period` | ✅ Scored | Group 11 |
| `early_termination_fee_option` | ✅ Scored | Group 12 |
| `early_termination_fee_amount` | ✅ Scored | Group 13 |
| `agency_agreement_timeframe` | ✅ Scored | Group 14 (+ custom) |
| `brokerage_relationship` | ✅ Scored | Group 15 |
| `interested_in_property_management` | ✅ Scored | Group 16 |
| `referral_fee_percent` | ✅ Scored | Group 17 |

### 6.2 Landlord Property & Lease Preferences (Unscored)
*Storage: EAV meta in `landlord_agent_auction_metas`*

| Field | Type | Scored? | Agent Mirror? | Notes |
|---|---|---|---|---|
| `property_type` | enum | 🟡 Scorable | ✅ Yes | Residential vs Commercial |
| `bedroom` / `bathrooms` | string | 🟡 Scorable | ❓ No | |
| `heated_sqft` | integer | 🟡 Scorable | ❓ No | |
| `yearBuilt` | integer | 🟡 Scorable | ❓ No | |
| `lotSize` | decimal | 🟡 Scorable | ❓ No | |
| `leaseDate` | date | 🟡 Scorable | ❓ No | Availability date |
| `leaseTime` | integer/string | 🔴 Needs Norm | ❓ No | Lease duration — months vs year forms |
| `leaseTerms` | enum | 🟡 Scorable | ❓ No | Month-to-month, Annual, etc. |
| `rent_include` | JSON array | 🔴 Needs Norm | ❓ No | Multi-select utilities included |
| `tenant_pays` | JSON array | 🔴 Needs Norm | ❓ No | Multi-select |
| `ownerPays` | JSON array | 🔴 Needs Norm | ❓ No | Multi-select |
| `required_at_move_in` | JSON/string | 🔴 Needs Norm | ❓ No | |
| `securityDeposit` | decimal | 🟡 Scorable | ❓ No | |
| `firstMonthDeposit` / `lastMonthDeposit` | Yes/No | 🟡 Scorable | ❓ No | |
| `applicationFee` | decimal | 🟡 Scorable | ❓ No | |
| `petsOpt` | Yes/No | 🟡 Scorable | ❓ No | |
| `petsNumber` / `petsWeight` | integer | 🟡 Scorable | ❓ No | |
| `petsType` | string | 🔴 Needs Norm | ❓ No | Free text |
| `petsFee` | decimal | 🟡 Scorable | ❓ No | |
| `offer_allowed_occupants` | integer | 🟡 Scorable | ❓ No | |
| `creditScore` | string | 🔴 Needs Norm | ❓ No | "650+", ranges — needs integer floor |
| `offer_min_net_income` | decimal | 🟡 Scorable | ❓ No | |
| `eviction` | Yes/No/string | 🟡 Scorable | ❓ No | |
| `compatibility_preferences` (7) | JSON | ⬜ Not Scorable | Partial | Same gap as other roles |

---

## 7. Tenant Fields

### 7.1 Broker Compensation & Agreement Terms

| Field | Scored? | Notes |
|---|---|---|
| `commission_structure` | ✅ Scored | Group 1 |
| `lease_fee_type` + 10 sub-fields | ✅ Scored | Group 2 |
| `payment_timing` / `days_to_pay` | ✅ Scored | Group 3 |
| `interested_purchase_fee_type` | ✅ Scored | Group 4 parent |
| `purchase_fee_type` + 5 sub-fields | ✅ Scored | Group 5 |
| `interested_lease_option_agreement` | ✅ Scored | Group 6 |
| `lease_type` / `lease_value` | ✅ Scored | Group 7 |
| `purchase_type` / `purchase_value` | ✅ Scored | Group 8 |
| `protection_period` | ✅ Scored | Group 9 |
| `early_termination_fee_option` | ✅ Scored | Group 10 |
| `early_termination_fee_amount` | ✅ Scored | Group 11 |
| `retainer_fee_option` | ✅ Scored | Group 12 |
| `retainer_fee_amount` | ✅ Scored | Group 13 |
| `retainer_fee_application` | ✅ Scored | Group 14 |
| `agency_agreement_timeframe` | ✅ Scored | Group 15 |
| `brokerage_relationship` | ✅ Scored | Group 16 |
| `referral_fee_percent` | ✅ Scored | Group 17 |

### 7.2 Tenant Property & Leasing Preferences (Unscored)
*Storage: EAV meta in `tenant_agent_auction_metas` / `tenant_criteria_auction_metas`*

| Field | Type | Scored? | Agent Mirror? | Notes |
|---|---|---|---|---|
| `cities` | JSON array | 🔴 Needs Norm | ❓ No | Multi-select; no agent location field |
| `counties` | JSON array | 🔴 Needs Norm | ❓ No | |
| `property_type` | enum | 🟡 Scorable | ✅ Yes | |
| `property_items` | JSON array | 🔴 Needs Norm | ❓ No | Property styles |
| `prop_condition` | JSON array | 🔴 Needs Norm | ❓ No | Updated/Older — multi-select |
| `bedrooms` / `bathrooms` | string | 🔴 Needs Norm | ❓ No | "2+" — min-value parsing needed |
| `minimum_sqft_needed` | string | 🔴 Needs Norm | ❓ No | |
| `monthly_price` / `budget` (max_rent) | decimal | 🔴 Needs Norm | ❓ No | Budget ceiling vs listing rent |
| `leaseLength` / `lease_for` | JSON array | 🔴 Needs Norm | ❓ No | Multi-select durations |
| `idealDate` / `lease_date` | date | 🟡 Scorable | ❓ No | |
| `leaseProp` / `leasing_spaces` | enum | 🟡 Scorable | ❓ No | Entire property vs room |
| `how_many_occupying` | integer | 🟡 Scorable | ❓ No | |
| `monthly_household_income` | decimal | 🟡 Scorable | ❓ No | Qualification field |
| `tenant_credit_score` | string | 🔴 Needs Norm | ❓ No | Range strings — needs floor integer |
| `has_water_view` | string | 🟡 Scorable | ❓ No | |
| `pool` / `poolNeededOpt` | string | 🟡 Scorable | ❓ No | |
| `has_pets` / `totalPets` | string/int | 🟡 Scorable | ❓ No | |
| `location_dna_preferences` | JSON | 🔴 Needs Norm | ❓ No | Structured but complex; AI scoring path |
| `compatibility_preferences` (7) | JSON | ⬜ Not Scorable | Partial | Same gap as other roles |

---

## 8. Agent Compatibility Fields (Supply Side)

These are the fields agents fill in when they submit a bid. Fields marked ✅ are already consumed by the scoring engine. Fields marked 🟡 / 🔴 are collected but unused.

### 8.1 Broker Compensation Fields (Fully Wired — All Roles)

All fields in `LOGICAL_FIELD_GROUPS` for each role helper are consumed. No gaps here.

### 8.2 Services (Fully Wired — All Roles)

`services` (JSON array) + `other_services` (custom text array) feed `parseServices()` with catalog filtering. Fully wired.

### 8.3 Agent Profile & Credentials (Unscored)

| Field | Type | Scorable? | Notes |
|---|---|---|---|
| `bio` | text | ⬜ Not Scorable | Narrative — sentiment analysis only |
| `why_hire_you` | text | ⬜ Not Scorable | Narrative |
| `what_sets_you_apart` | text | ⬜ Not Scorable | Narrative |
| `marketing_plan` | text | ⬜ Not Scorable | Narrative |
| `year_licensed` | integer | 🟡 Scorable | Years of experience = `current_year - year_licensed` |
| `additional_details` | text | ⬜ Not Scorable | Per business rule: "Additional Details NEVER counted" |
| `presentation_link` | url | ⬜ Not Scorable | Presence check only |
| `website_link` | array of urls | ⬜ Not Scorable | |
| `social_media` | array | ⬜ Not Scorable | |
| `reviews_links` | array | ⬜ Not Scorable | |
| `business_card_link` | url | ⬜ Not Scorable | |
| `promoMaterials` | array | ⬜ Not Scorable | |

### 8.4 Compatibility Preferences — Agent Side (7 Sections, Unscored)

All 7 sections from `HasCompatibilityPreferences::compatibilitySections()` are stored on bid models via `AgentBidMapperService`. But since the client-side compatibility sections are also unstructured JSON, no scoring is possible without first standardizing the question/answer schema.

| Section | Client Stored? | Agent Stored? | Score Ready? |
|---|---|---|---|
| `communication_preferences` | ✅ Yes | ✅ Yes | ❌ No — schema undefined |
| `negotiation_approach` | ✅ Yes | ✅ Yes | ❌ No — schema undefined |
| `guidance_style` | ✅ Yes | ✅ Yes | ❌ No — schema undefined |
| `collaboration_preferences` | ✅ Yes | ✅ Yes | ❌ No — schema undefined |
| `transaction_strategy` | ✅ Yes | ✅ Yes | ❌ No — schema undefined |
| `representation_philosophy` | ✅ Yes | ✅ Yes | ❌ No — schema undefined |
| `representation_priorities` | ✅ Yes | ✅ Yes | ❌ No — schema undefined |

---

## 9. Normalization Gaps — Detail

Fields that exist on both sides but cannot be scored yet due to data format issues:

### 9.1 Numeric Ranges

| Field(s) | Problem | Fix Required |
|---|---|---|
| `bedrooms`, `bathrooms` | Stored as "2+", "3", "Any" | Extract integer floor; treat "Any" as 0 |
| `minimum_sqft_needed` | Stored as "1200", "1,200" | Strip commas; cast integer |
| `creditScore` | "650+", "700-750", "Excellent" | Map to integer floor via lookup table |
| `tenant_credit_score` | Same as above | |
| `maximum_budget` / `monthly_price` | Stored cleanly as decimal — but no agent mirror | Agent mirror field needed |

### 9.2 Multi-Select Arrays

| Field(s) | Problem | Fix Required |
|---|---|---|
| `cities`, `counties` | Client has JSON array; agent has no mirror field | Agent needs a `service_areas` field |
| `property_items` | JSON arrays on both sides; use set-intersection scoring | Parse both sides as arrays; score as `matched / client_total` |
| `financings` / `offered_financing` | JSON array; agent has no mirror | Agent needs `accepted_financing_types` |
| `rent_include`, `tenant_pays`, `ownerPays` | JSON arrays | Set-intersection; agent needs mirror |
| `leaseLength` / `lease_for` | JSON array of durations | Agent needs `available_lease_lengths` |

### 9.3 Unit Disambiguation

| Field | Problem | Fix Required |
|---|---|---|
| `down_payment_type` | "$" vs "%" stored as raw char | Cast before comparison; normalize to enum |
| `seller_contribution_credit_offered` | May be flat or percent | Store with type indicator |
| `possession_preference` | Free text: "60 days", "At closing", "Flexible" | Enum + days normalization |

### 9.4 Date / Timeline Normalization

| Field | Problem | Fix Required |
|---|---|---|
| `preferred_inspection_period` | "10 days", "10" — mixed | Extract integer days |
| `leaseTime` / `agency_agreement_timeframe` | "6 months", "180 days", "1 year" — mixed | Normalize to days |
| `idealDate` / `lease_date` | ISO date — clean, but no agent mirror | Agent needs `earliest_available_date` |

---

## 10. Scoring Opportunity Matrix

Summary of all field categories across all roles, with recommended scoring approach.

| Category | Roles | Current | Recommended Approach | Effort |
|---|---|---|---|---|
| Broker compensation terms | All 4 | ✅ Scored | Already complete | — |
| Agent services | All 4 | ✅ Scored | Already complete | — |
| Property type | All 4 | 🟡 Scorable | Exact enum match | Low |
| Years licensed | All 4 | 🟡 Scorable | Client sets minimum; agent meets threshold | Low |
| Service type (Full/Limited) | All 4 | 🟡 Scorable | Exact enum match | Low |
| Bedrooms / bathrooms | Seller, Buyer, Landlord, Tenant | 🔴 Needs Norm | Parse "3+" → integer floor; min-threshold match | Medium |
| Budget ceiling vs rent/price | Buyer, Tenant | 🔴 Needs Norm | No agent mirror today — add agent field first | High |
| Location / service areas | Buyer, Tenant | 🔴 Needs Norm | Client city/county array vs agent `service_areas` | High |
| Property feature arrays | All 4 | 🔴 Needs Norm | Set-intersection: `matched / client_total × 100` | Medium |
| Financing type arrays | Buyer, Seller | 🔴 Needs Norm | Set-intersection; needs agent mirror | High |
| Lease length preferences | Landlord, Tenant | 🔴 Needs Norm | Set-intersection; needs agent mirror | Medium |
| Move-in / availability date | Landlord, Tenant | 🟡 Scorable | Overlap window; needs agent mirror | Medium |
| Pet policy | Landlord | 🟡 Scorable | Landlord allows pets vs tenant has pets | Medium |
| Credit score threshold | Landlord | 🔴 Needs Norm | Parse floor; client min vs tenant floor | Medium |
| Compatibility preferences (7) | All 4 | ⬜ Not Scorable | Define question/answer schema first; then cosine or overlap scoring | Very High |
| Agent bio / narrative | All 4 | ⬜ Not Scorable | Embedding similarity (AI) — optional future path | Very High |

---

## 11. Fields With No Agent Mirror (Highest Priority Gap)

These client-side fields currently have **no corresponding agent field to match against**, making scoring impossible regardless of normalization. Adding the agent mirror is the prerequisite.

| Client Field | Role(s) | What Agent Mirror Would Look Like |
|---|---|---|
| `cities` / `counties` (target areas) | Buyer, Tenant | Agent profile field: `service_areas` (JSON array of city/county IDs) |
| `maximum_budget` / `monthly_price` | Buyer, Tenant | Agent bid field: `quoted_price_range` or simply accept that agent matches if they bid |
| `offered_financing` / `financings` | Buyer, Seller | Agent bid field: `accepted_financing_types` (JSON array) |
| `leaseLength` / `lease_for` | Landlord, Tenant | Agent bid field: `offered_lease_lengths` (JSON array) |
| `idealDate` / `lease_date` | Landlord, Tenant | Agent bid/profile field: `earliest_availability_date` |
| `tenant_credit_score` requirement | Landlord | Tenant qualification — agent doesn't control; used to filter tenants, not agents |
| `creditScore` minimum | Landlord | Same — tenant qualification, not agent |
| Preferred inspection period | Seller, Buyer | Agent doesn't set this; belongs in offer terms, not agent matching |

---

## 12. Recommended Phased Roadmap

### Phase 1 — Low-Hanging Fruit (No Agent Mirror Needed, Minimal Normalization)

These can be wired into the existing `LOGICAL_FIELD_GROUPS` pattern immediately.

1. `property_type` — add to each role's field groups as exact-match (both sides already store it)
2. `service_type` (Full/Limited) — exact match; already present on both sides
3. `year_licensed` — add minimum-years field to hire forms; threshold match in scoring
4. `petsOpt` (Landlord) — exact Yes/No; agent already stores `interested_in_property_management`; pet policy is implicit in property management interest

### Phase 2 — Normalization Layer (Medium Effort)

Build a `FieldNormalizer` utility (mirrors `normalizeForMatch()` pattern) for:

- Integer-floor parsing for bedroom/bathroom "3+" strings
- Duration-to-days normalization for lease and agreement timeframes
- Property feature array set-intersection scorer
- Credit score string → integer floor lookup

Wire results into a new **"Property Fit"** score component (third pillar alongside Services + Terms).

### Phase 3 — Agent Mirror Fields (High Effort)

Add `service_areas` (city/county JSON array) to agent bid and preset forms for Buyer and Tenant roles. Score location compatibility as set-intersection. This is the single highest-signal unscored dimension.

### Phase 4 — Compatibility Preferences Scoring (Strategic)

Define a canonical question/answer schema for each of the 7 compatibility sections — i.e., specific radio/checkbox questions rather than freeform JSON. Then score via answer-overlap or weighted keyword match. This is the "personality fit" moat dimension.

---

## 13. Summary Scorecard

| Dimension | Seller | Buyer | Landlord | Tenant | Engine Support |
|---|---|---|---|---|---|
| Broker terms | ✅ 16 groups | ✅ 16 groups | ✅ 17 groups | ✅ 17 groups | Full |
| Agent services | ✅ Full catalog | ✅ Full catalog | ✅ Full catalog | ✅ Full catalog | Full |
| Property type | 🟡 Ready | 🟡 Ready | 🟡 Ready | 🟡 Ready | Phase 1 |
| Bedrooms / sqft | 🔴 Norm needed | 🔴 Norm needed | 🔴 Norm needed | 🔴 Norm needed | Phase 2 |
| Location / service area | ❓ No mirror | ❓ No mirror | n/a | ❓ No mirror | Phase 3 |
| Budget / price range | ❓ No mirror | ❓ No mirror | ❓ No mirror | ❓ No mirror | Phase 3 |
| Property features (multi-select) | 🔴 Norm needed | 🔴 Norm needed | 🔴 Norm needed | 🔴 Norm needed | Phase 2 |
| Lease / timing preferences | n/a | 🔴 Norm needed | 🔴 Norm needed | 🔴 Norm needed | Phase 2–3 |
| Compatibility (7 sections) | ⬜ Schema TBD | ⬜ Schema TBD | ⬜ Schema TBD | ⬜ Schema TBD | Phase 4 |
| Agent credentials / bio | ⬜ Narrative | ⬜ Narrative | ⬜ Narrative | ⬜ Narrative | AI path only |

**Current engine utilizes ~30% of collected fields. A full Phase 1–3 implementation would raise utilization to ~75–80% and introduce a third scoring pillar (Property Fit), enabling a more differentiated overall match percentage.**
