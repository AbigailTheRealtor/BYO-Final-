# Matching Engine — Build 4 / Matching Expansion Phase 1 Audit

**Date:** June 16, 2026
**Scope:** All four hire-agent roles — Seller, Buyer, Landlord, Tenant
**Purpose:** Document the before-state of the scoring engine, inspect real compatibility and availability data in the database, verify service area field shapes for all four roles, and produce the final recommended weight table for Build 4 / Matching Expansion Phase 1 implementation.
**Output:** This document + `config/match_scoring.php`
**Status:** COMPLETE — ready for Build 4 implementation.

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Current Engine Architecture](#2-current-engine-architecture)
3. [Before-Scores — Per-Role Baselines](#3-before-scores--per-role-baselines)
4. [Compatibility JSON Inspection](#4-compatibility-json-inspection)
5. [Client-Side Communication & Availability Preference Fields](#5-client-side-communication--availability-preference-fields)
6. [Service Area Field Shape Verification — All Four Roles](#6-service-area-field-shape-verification--all-four-roles)
7. [Recommended Weight Table](#7-recommended-weight-table)
8. [Implementation Guidance for Build 4](#8-implementation-guidance-for-build-4)

---

## 1. Executive Summary

The platform has a working two-dimensional match engine for all four roles scoring **Services** and **Broker Compensation Terms**, each at 50%. The Build 4 / Matching Expansion Phase 1 audit determines which additional scoring dimensions are feasible, and at what weights.

**Headline findings:**

| Dimension | Feasible in Build 4? | Key Finding |
|---|---|---|
| Service Area | ✅ Yes — all four roles confirmed | All four roles have client-side location fields; landlord uses `property_city` / `property_county` meta keys; normalization fully specified in §6 |
| Experience | ✅ Yes — agent side only | `year_licensed` and `transactions_last_12_months` in `profile_data`; no client minimum field yet |
| Availability & Communication | ✅ Partially two-sided | `client_preferred_comm_method` exists in all four listing meta tables and is populated; agent has `preferred_contact_method` in `profile_data`; scheduling availability (evenings/weekends) remains one-sided |
| Compatibility | ❌ No — agent side absent | Client data exists (3/4 roles); agent data is entirely absent; schema mismatch between client and agent storage formats; full detail in §4 |

**Final recommended weights** (sum to 100%):

| Dimension | Weight | Active? |
|---|---|---|
| Services | 35% | ✅ Active (existing engine) |
| Terms | 35% | ✅ Active (existing engine) |
| Service Area | 15% | Build 4 |
| Experience | 10% | Build 4 |
| Availability & Communication | 5% | Build 4 |
| Compatibility | 0% | Deferred — agent side not yet collected |

---

## 2. Current Engine Architecture

### 2.1 Formula (All Four Roles, Identical)

```
Overall Match % = 50% × Services Score + 50% × Terms Score
(only non-zero components counted — if one side has no data, the other carries 100%)
```

**Services Score:**
```
services_match_percent = matched_services / baseline_services_total × 100

Where:
  baseline_services_total = client's requested services filtered to the role+property-type catalog
  matched_services        = baseline services the agent also offers
  extra_agent_services    = agent offers these but client didn't request — NOT in denominator
```

**Terms Score (Logical Field Groups):**
```
terms_match_percent = matched_groups / baseline_groups_total × 100

Where:
  baseline_groups_total = LOGICAL_FIELD_GROUPS entries with a non-empty baseline composite
  matched_groups        = groups where baseline composite == agent composite (after normalizeForMatch())
  changed_groups        = composites differ
  added_by_agent_groups = baseline blank, agent filled — NOT in denominator
  cascade_excluded      = child groups skipped when bid's parent field = "No"
```

Sub-inputs for one logical concept (e.g., `lease_fee_type` + `lease_fee_flat` + `lease_fee_percentage`) are concatenated with `|` and compared as a single composite string via `normalizeForMatch()` — preventing UI field inflation.

### 2.2 Normalization Already in Place

| Normalizer | What It Does |
|---|---|
| `normalizeForMatch($v)` | Strips whitespace, `$`, `%`, `,`; lowercases; smart quotes → straight |
| `normalizeService($s)` | Smart quotes → straight; lowercase; trim |
| Catalog filter | Rejects wrong-role services stored accidentally in a bid |
| Cascade deactivation | When bid parent = "No", child groups excluded from denominator entirely |

### 2.3 Role-Specific Group Counts

| Role | Max Active Logical Groups | Service Catalog Variants |
|---|---|---|
| Seller | 16 | Residential, Commercial, Income |
| Buyer | 16 | Residential, Income, Commercial, Business, Vacant Land |
| Landlord | 17 | Residential, Commercial |
| Tenant | 17 | Residential, Commercial |

---

## 3. Before-Scores — Per-Role Baselines

The following representative fixtures illustrate how the current engine scores a bid, using catalog sizes and field patterns typical of `agent_default_profiles` records currently in the database.

### 3.1 Seller — Before-Score Baseline

**Fixture:** Residential seller requests 20 services; agent offers 17 + 4 extra. Client fills 12 of 16 term groups. Agent matches 9, changes 2, cascade-excludes 1 (lease-option parent = No on both sides).

```
Services: 17 / 20 = 85.0%
Terms:     9 / 12 = 75.0%
Overall:  (85.0 + 75.0) / 2 = 80.0%
```

**Active LOGICAL_FIELD_GROUPS (Seller, 16 groups):**
`purchase_fee_type` (+5 sub-fields), `nominal`, `commission_structure`, `commission_structure_type`, `interested_purchase_fee_type`, `seller_leasing_fee_type` (+13 sub-fields, conditional on parent=Yes), `interested_lease_option_agreement`, `lease_type`/`lease_value` (conditional), `purchase_type`/`purchase_value` (conditional), `early_termination_fee_option`, `early_termination_fee_amount` (conditional), `retainer_fee_option`, `retainer_fee_amount` (conditional), `retainer_fee_application` (conditional), `retained_deposits`, `protection_period`, `agency_agreement_timeframe`, `brokerage_relationship`, `referral_fee_percent`

**Collected but not currently scored (Seller):**
Sale terms: `maximum_budget`, `min_price`, `initial_deposit_requested`, `initial_deposit_timeframe`, `preferred_inspection_period`, `appraisal_contingency_preference`, `financing_contingency_preference`, `sale_of_buyer_property_contingency`, `seller_contribution_credit_offered`, `possession_preference`, `home_warranty_offered`
Property info: `city_id`, `county_id`, `state_id`, `bedroom_id`, `bathroom_id`, `sqft`, `property_items`, `appliances`, `view_preference`, `financings`
Communication/compatibility: `client_preferred_comm_method`, `compatibility_preferences` — see §4–5

---

### 3.2 Buyer — Before-Score Baseline

**Fixture:** Residential buyer requests 18 services; agent offers 15 + 5 extra. Client fills 10 of 16 term groups. Agent matches 8, changes 1, cascade-excludes 1.

```
Services: 15 / 18 = 83.3%
Terms:     8 / 10 = 80.0%
Overall:  (83.3 + 80.0) / 2 = 81.7%
```

**Active LOGICAL_FIELD_GROUPS (Buyer, 16 groups):**
`commission_structure`, `purchase_fee_type` (+5 sub-fields), `interested_lease_option`, `lease_fee_type` (+10 sub-fields, conditional), `interested_lease_option_agreement`, `lease_type`/`lease_value` (conditional), `purchase_type`/`purchase_value` (conditional), `protection_period`, `early_termination_fee_option`, `early_termination_fee_amount` (conditional), `retainer_fee_option`, `retainer_fee_amount` (conditional), `retainer_fee_application` (conditional), `agency_agreement_timeframe`, `brokerage_relationship`, `referral_fee_percent`

**Collected but not currently scored (Buyer):**
Property preferences: `maximum_budget`, `cities`, `counties`, `property_items`, `bedrooms`, `bathrooms`, `min_acreage`, `minimum_cap_rate`, `offered_financing`, `purchase_price`, `down_payment_amount`, `down_payment_type`, `earnest_money_amount`, `inspection_period_days`, `possession_preference`
Communication/compatibility: `client_preferred_comm_method`, `compatibility_preferences` — see §4–5

---

### 3.3 Landlord — Before-Score Baseline

**Fixture:** Residential landlord requests 22 services; agent offers 19 + 6 extra. Client fills 13 of 17 term groups. Agent matches 10, changes 2, cascade-excludes 1.

```
Services: 19 / 22 = 86.4%
Terms:    10 / 13 = 76.9%
Overall:  (86.4 + 76.9) / 2 = 81.7%
```

**Active LOGICAL_FIELD_GROUPS (Landlord, 17 groups):**
`purchase_fee_type` (+12 sub-fields), `broker_fee_timing` (+7 sub-fields), `renewal_fee_type` (+6 sub-fields), `expansion_commission_percentage`, `tenant_broker_commission_structure`, `tenant_broker_fee_structure` (+5 sub-fields), `interested_lease_option_agreement`, `lease_type`/`lease_value` (conditional), `purchase_type`/`purchase_value` (conditional), `interested_in_selling`, `interested_in_selling_type` (conditional), `protection_period`, `early_termination_fee_option`, `early_termination_fee_amount` (conditional), `agency_agreement_timeframe` (+custom), `brokerage_relationship`, `interested_in_property_management`, `referral_fee_percent`

**Collected but not currently scored (Landlord):**
Property/lease: `property_city`, `property_county`, `bedroom`, `bathrooms`, `heated_sqft`, `yearBuilt`, `lotSize`, `leaseDate`, `leaseTime`, `leaseTerms`, `rent_include`, `tenant_pays`, `ownerPays`, `securityDeposit`, `firstMonthDeposit`, `lastMonthDeposit`, `applicationFee`, `petsOpt`, `petsNumber`, `petsWeight`, `petsFee`, `offer_allowed_occupants`, `creditScore`, `offer_min_net_income`, `eviction`
Communication: `client_preferred_comm_method` — see §5
Note: `compatibility_preferences` meta key has zero rows for landlord — see §4

---

### 3.4 Tenant — Before-Score Baseline

**Fixture:** Residential tenant requests 16 services; agent offers 14 + 3 extra. Client fills 11 of 17 term groups. Agent matches 9, changes 1, cascade-excludes 1.

```
Services: 14 / 16 = 87.5%
Terms:     9 / 11 = 81.8%
Overall:  (87.5 + 81.8) / 2 = 84.7%
```

**Active LOGICAL_FIELD_GROUPS (Tenant, 17 groups):**
`commission_structure`, `lease_fee_type` (+10 sub-fields), `payment_timing`/`days_to_pay`, `interested_purchase_fee_type`, `purchase_fee_type` (+5 sub-fields, conditional), `interested_lease_option_agreement`, `lease_type`/`lease_value` (conditional), `purchase_type`/`purchase_value` (conditional), `protection_period`, `early_termination_fee_option`, `early_termination_fee_amount` (conditional), `retainer_fee_option`, `retainer_fee_amount` (conditional), `retainer_fee_application` (conditional), `agency_agreement_timeframe`, `brokerage_relationship`, `referral_fee_percent`

**Collected but not currently scored (Tenant):**
Property/leasing preferences: `cities`, `counties`, `property_items`, `prop_condition`, `bedrooms`, `bathrooms`, `minimum_sqft_needed`, `monthly_price`/`budget`/`max_rent`, `leaseLength`/`lease_for`, `idealDate`/`lease_date`, `leaseProp`/`leasing_spaces`, `how_many_occupying`, `monthly_household_income`, `tenant_credit_score`, `has_water_view`, `pool`/`poolNeededOpt`, `has_pets`/`totalPets`, `location_dna_preferences`
Communication/compatibility: `client_preferred_comm_method`, `compatibility_preferences` — see §4–5

**Note (intentional exclusion, all roles):** `additional_details_broker` is explicitly excluded from all four roles. Business rule: "Additional Details NEVER counted."

---

## 4. Compatibility JSON Inspection

### 4.1 Query Methodology

Two storage locations were queried:

**Location A — Listing-side dot-notation meta keys** (the `HasCompatibilityPreferences` schema):
All eight tables queried for `meta_key LIKE 'compatibility_preferences.agent_response.%'`:
- `seller_agent_auction_metas`, `buyer_agent_auction_metas`, `landlord_agent_auction_metas`, `tenant_agent_auction_metas`
- `seller_agent_auction_bid_metas`, `buyer_agent_auction_bid_metas`, `landlord_agent_auction_bid_metas`, `tenant_agent_auction_bid_metas`

**Location B — Flat `compatibility_preferences` meta key** (the listing-creation UI schema):
All four listing meta tables queried for `meta_key = 'compatibility_preferences'`.

**Location C — Agent preset JSON** (`agent_default_profiles.profile_data`):
All profiles queried for the 7 HasCompatibilityPreferences section keys as JSONB paths:
`communication_preferences`, `negotiation_approach`, `guidance_style`, `collaboration_preferences`, `transaction_strategy`, `representation_philosophy`, `representation_priorities`

### 4.2 Finding A: Dot-Notation Keys — Zero Rows in All Eight Tables

```sql
-- Representative query:
SELECT meta_key, meta_value
FROM seller_agent_auction_metas
WHERE meta_key LIKE 'compatibility_preferences.agent_response.%';
-- Result: 0 rows (same for all 8 tables)
```

No data has ever been written to the `HasCompatibilityPreferences` dot-notation keys in any meta table across any role, listing-side or bid-side.

### 4.3 Finding B: Flat Meta Key — Populated for Seller, Buyer, Tenant; Empty for Landlord

The flat `compatibility_preferences` meta key **does have populated data** in three of the four listing meta tables:

**Seller** (`seller_agent_auction_metas`) — Rich structured data confirmed. Example schema:
```json
{
  "seller_specific": {
    "communication_style": "Frequent & Proactive",
    "negotiation_style": "Aggressive — Push for Maximum Profit",
    "primary_transaction_goal": "Other",
    "representation_priorities": ["Market Expertise", "Strong Negotiator", "High Communication", ...],
    "preferred_agent_working_style": "Proactive & Takes Initiative",
    "preferred_contact_method": ["Phone Call", "Text/SMS", "Email", "Video Call", "In-Person Meeting"],
    "response_time_expectation": "Within 1 Hour",
    "willing_to_negotiate_on": ["Price Reductions", "Closing Costs", "Repairs / Credits", ...],
    "firm_on_price": "Flexible — Willing to Negotiate Significantly",
    "target_sale_timeline": "32 days",
    "flexibility_on_timeline": "Somewhat Flexible",
    "post_sale_plan": "Renting",
    "qualities_most_important": ["Honesty & Transparency", "Patience", "Assertiveness", ...],
    "past_agent_experience": "Positive Experience",
    "decision_making_style": "Collaborative — I Value Agent Input",
    "involvement_level": "Very Involved — I want to be part of every decision",
    "showing_availability": ["Weekday Mornings", "Weekend Evenings", "Flexible / Anytime"],
    "open_house_preference": "Strongly Prefer Open Houses",
    "additional_compatibility_notes": "..."
  }
}
```

**Buyer** (`buyer_agent_auction_metas`) — Populated. Schema uses `buyer_specific` key with fields: `primary_transaction_goal`, `representation_priorities`, `communication_style`, `negotiation_style`, `preferred_agent_working_style`, `risk_tolerance`, `decision_making_style`, `timeline_flexibility`, `preferred_contact_method`, `availability_windows`, `communication_frequency`, `support_level`, `deal_breakers`, `additional_compatibility_notes`. Some rows have all-blank values (form shown but not filled).

**Tenant** (`tenant_agent_auction_metas`) — Populated. Schema uses `tenant_specific` key with fields: `primary_rental_goal`, `representation_priorities`, `timeline_urgency`, `budget_flexibility`, `communication_style`, `contact_frequency`, `preferred_contact_method`, `preferred_agent_working_style`, `negotiation_style`, `decision_making_style`, `concerns_or_barriers`, `most_important_agent_traits`, `desired_level_of_agent_involvement`, `additional_compatibility_notes`.

**Landlord** (`landlord_agent_auction_metas`) — **Zero rows.** The `compatibility_preferences` meta key exists in the schema (present in table) but no landlord listing has ever populated it.

### 4.4 Finding C: Agent Presets — All Null for All Seven Sections

Querying `agent_default_profiles.profile_data` for all 10 records in the database confirms every 7-section path is null across all records and all roles:

```
id  role_type  comm_prefs  neg_approach  guidance  collab  tx_strategy  rep_phil  rep_prio
51  buyer      null        null          null       null    null         null      null
53  tenant     null        null          null       null    null         null      null
54  landlord   null        null          null       null    null         null      null
... (all 10 rows the same)
```

No agent has ever filled in any compatibility preference section in their preset editor.

### 4.5 Schema Mismatch

Client-side and agent-side compatibility data use incompatible schemas:

| Aspect | Client Side (listing meta) | Agent Side (HasCompatibilityPreferences) |
|---|---|---|
| Meta key | `compatibility_preferences` (flat) | `compatibility_preferences.agent_response.{section}` (dot-notation) |
| Structure | Role-scoped JSON blob: `seller_specific`, `buyer_specific`, `tenant_specific` | 7 canonical sections: `communication_preferences`, `negotiation_approach`, etc. |
| Question sets | Role-specific questions per blob | Generic cross-role sections |
| Population | Populated for Seller, Buyer, Tenant | Empty for all roles, all agents |

These schemas do not map to each other directly. A reconciliation step is required before any scoring can compare a client's `seller_specific.communication_style` against an agent's `communication_preferences` section.

### 4.6 Compatibility Scoring Verdict

**Not feasible in Build 4.** The client-side data infrastructure is further along than previously understood (rich structured data for 3/4 roles), but the agent side is entirely absent. Prerequisites:

1. Build agent-side compatibility collection UI in the preset editor.
2. Reconcile the schema — either adopt the role-scoped JSON blob approach used on the client side, or define a mapping between the two schemas so scoring functions can compare them field-by-field.
3. Add landlord client-side compatibility collection (currently zero rows).
4. Implement set-intersection scoring for radio/multi-select fields once both sides are populated.

**Weight: 0%. Enabled: false.**

> **Future weight reservation:** Compatibility is the platform's strongest long-term differentiator — it is the only dimension that captures working-style fit, not just transactional alignment. When agent-side compatibility collection exists and the schema is reconciled, the recommended future target weight range is **10–15%**, redistributed from Services and Terms (e.g., Services 25–28%, Terms 25–28%, Compatibility 10–15%). Implementation teams should not treat the current 0% as an indication that this dimension is trivial.

---

## 5. Client-Side Communication & Availability Preference Fields

### 5.1 Full Audit of Communication and Availability Meta Keys

All four listing meta tables were queried for keys matching patterns: `communication`, `contact`, `prefer`, `avail`, `evening`, `weekend`, `response`, `schedule`, `hours`, `time_zone`.

**Complete results per table:**

| Meta Key | Seller | Buyer | Landlord | Tenant | Notes |
|---|---|---|---|---|---|
| `client_preferred_comm_method` | ✅ | ✅ | ✅ | ✅ | Agent communication method preference — **core two-sided field** |
| `client_preferred_comm_method_other` | ✅ | ✅ | ✅ | ✅ | Free text for "Other"; not directly scorable |
| `meeting_details_time_zone` | ✅ | ✅ | ✅ | ✅ | Client's time zone — present but **all current values empty** |
| `service_time_zone` | — | ✅ | ✅ | ✅ | Showing/scheduling time zone — **all current values empty** |
| `schedule_showings` | — | ✅ | ✅ | ✅ | Whether client wants agent to schedule showings (0/1 boolean) |
| `schedule_showings_fee` | — | ✅ | ✅ | ✅ | Fee preference for showing scheduling — mostly empty |
| `other_preferences` | ✅ | ✅ | ✅ | ✅ | Free text; mostly empty; not scorable |
| `preferance_details` | — | ✅ | ✅ | ✅ | Note: misspelled key. Free text; mostly empty; not scorable |
| `maintenance_preference` | ✅ | ✅ | ✅ | ✅ | Property maintenance preferences — NOT agent schedule |
| `maintenance_response_time` | ✅ | ✅ | ✅ | ✅ | Landlord maintenance SLA — NOT agent availability |
| `number_of_showings_to_schedule` | ✅ | ✅ | ✅ | ✅ | Showing coordination — NOT agent availability |

### 5.2 `client_preferred_comm_method` — Two-Sided Scoring Field

This is the most important finding of §5. It is present in **all four listing meta tables** and contains populated values:

**Observed values in client listings:**
- `"Phone Call"`, `"Text/SMS"`, `"Email"`, `"Video Call"`, `"In-Person Meeting"`, `"Other"`
- When `"Other"` is selected, `client_preferred_comm_method_other` contains free text (e.g., `"In person"`)

**Agent-side mirror field:**
`preferred_contact_method` in `profile_data` of `agent_default_profiles`.
- Observed value: `"Any"` (only non-null value found in current data)

**Scoring approach:** exact method match → 100; agent says "Any" → 80 (willing but not specific); no match → 0. Client's "Other" choice treated as "Any" (neutral) since the free-text value is not machine-comparable.

### 5.3 Scheduling Availability — Still One-Sided

`meeting_details_time_zone` and `service_time_zone` are present in listing meta tables but **all current values are empty or `0`** across every queried record. These keys exist in the schema but no listing UI currently populates them with meaningful data.

There are no meta keys equivalent to `client_evenings_available`, `client_weekends_required`, or `client_preferred_hours` in any of the four listing meta tables.

**Conclusion:** The client's scheduling availability window preference is not collected in any listing creation form. The agent's `evenings_available`, `weekends_available`, and `availability_status` remain one-sided for this sub-component.

### 5.4 Availability & Communication Dimension Breakdown

The Build 4 dimension splits into two sub-components weighted equally (50/50):

| Sub-Component | Sided | Source Fields |
|---|---|---|
| Communication method match | **Two-sided** | Client: `client_preferred_comm_method`; Agent: `preferred_contact_method` |
| Scheduling availability | **One-sided (agent only)** | Agent: `evenings_available`, `weekends_available`, `availability_status` |

Overall dimension weight: **5%** — reflects the partially one-sided nature and advisory (non-disqualifying) character of both sub-components.

> **Config key note:** The dimension is labeled "Availability & Communication" throughout this audit to accurately reflect its two sub-components. The config key in `config/match_scoring.php` is `'availability'` (kept short for code clarity). The `'availability'` key covers both scheduling availability (evenings/weekends/status) and communication method matching. These two concepts are intentionally bundled — they share the 5% weight and are evaluated together in the `availability_score` sub-routine.

---

## 6. Service Area Field Shape Verification — All Four Roles

### 6.1 Agent-Side Storage (All Roles)

All service area fields are in `agent_default_profiles.profile_data` (JSONB) as **plain comma-separated strings** — not arrays, not IDs. Verified from all database records:

| Field | Format | Observed Values |
|---|---|---|
| `cities_served` | Comma-separated string | `"St. Pete"`, `"Seminole, St. Pete, Treasure Island"` |
| `counties_served` | Comma-separated string | `"Pinellas"` |
| `neighborhoods_served` | Plain string | `"Sheraton Shores"`, `"All Pinellas County neighborhoods"` (supplemental; not used for geo-match) |
| `primary_areas_served` | Plain string | `"Tampa Bay Area"` (supplemental; not used for geo-match) |

**Parsing:** split `cities_served` on `,`, trim each token, lowercase. Apply same to `counties_served`.

### 6.2 Client-Side Storage — Seller

Location stored as **native FK columns** on `seller_agent_auctions`:

```
city_id   bigint  → us_cities.id
county_id bigint  → us_counties.id
```

No seller listings in the database currently have `city_id` populated (all null in live data). The column exists and is schema-wired.

**Normalization:** Join `us_cities` on `city_id` and `us_counties` on `county_id` to resolve the FK integers to name strings, then lowercase.

> **Architectural constraint:** This join **must not happen inside a `*BidMatchScoreHelper`**. All four helper `calculate()` methods are pure transformations — they accept already-resolved data and must make no database calls. The caller layer (`CompetingBidsService`, `ScoreBreakdownService`, or `AgentBidMapperService`) is responsible for resolving `city_id` / `county_id` to name strings before invoking the helper. For Seller, the caller passes a pre-built `$clientLocations` array of normalized strings, identical in shape to what Buyer/Tenant/Landlord produce from their meta keys.

### 6.3 Client-Side Storage — Buyer and Tenant

Location stored as **JSON arrays of name strings** in listing meta tables:

```
meta_key: cities    → ["St. Petersburg, FL"]
meta_key: counties  → ["Pinellas County, FL"]
```

Empty arrays (`[]`) present where client left field blank.

**Normalization:** `json_decode($meta_value)` → strip `", FL"` from city names → strip `" County, FL"` and `" County"` from county names → lowercase.

### 6.4 Client-Side Storage — Landlord (RESOLVED)

The landlord listing **does have location data** in EAV meta keys. Confirmed by querying `landlord_agent_auction_metas` for location-pattern keys:

| Meta Key | Values Observed | Notes |
|---|---|---|
| `property_city` | `"St. Petersburg, FL"`, `"Seminole"` | Primary city field — **use this** |
| `property_county` | `"Pinellas County, FL"` | Primary county field — **use this** |
| `client_property_city` | Present but less consistently populated | Secondary; prefer `property_city` |
| `client_property_state` | Present but sparse | Not needed for scoring |
| `zip_code` | Present but mostly empty | Not used for scoring |

Format matches Buyer/Tenant: name strings with optional state suffix (`"Pinellas County, FL"` or bare `"Pinellas"`). **Same normalization pipeline applies.**

**Landlord is fully included in service area scoring.** The earlier audit's "gap" is resolved.

### 6.5 Cross-Role Normalization Summary

| Role | Client Location Source | Client Format | Normalization Needed | Who Normalizes |
|---|---|---|---|---|
| Seller | `seller_agent_auctions.city_id`, `county_id` | FK integers | Join `us_cities` / `us_counties` → name string → lowercase | **Caller layer** (not the helper) |
| Buyer | `buyer_agent_auction_metas` keys `cities`, `counties` | JSON array, `"Name, FL"` | Parse JSON → strip state suffix → lowercase | Helper receives raw meta value |
| Tenant | `tenant_agent_auction_metas` keys `cities`, `counties` | JSON array, `"Name, FL"` | Parse JSON → strip state suffix → lowercase | Helper receives raw meta value |
| Landlord | `landlord_agent_auction_metas` keys `property_city`, `property_county` | Single string, may include `", FL"` | Strip state suffix → lowercase | Helper receives raw meta value |
| Agent (all) | `profile_data['cities_served']`, `profile_data['counties_served']` | Comma-separated string | `explode(',', ...)` → trim → lowercase | Helper receives raw `profile_data` |

### 6.6 Name-Form Mismatch Risk

`"St. Pete"` (agent) vs. `"St. Petersburg"` (client) will not match with exact string comparison. The Build 4 implementation task must resolve this:

- **Option A** (recommended for v1): Require agents to enter canonical city names matching `us_cities.city_name`. Display a note in the preset editor.
- **Option B** (higher quality): Build a city-alias lookup table (`us_city_aliases`) keyed on normalized variations.
- **Option C** (fallback): Accept substring matching — `strpos("st. petersburg", "st. pete")` — as a partial-match heuristic.

### 6.7 County-to-City Containment Logic

An agent who lists a county in `counties_served` must receive credit for every city within that county that the client has specified. Requiring exact city-name overlap only would systematically penalize agents who correctly advertise their territory at the county level rather than listing every individual city.

**Containment rule:** When comparing client cities against agent locations, a client city is considered matched if:
1. The agent's `cities_served` contains the city name (direct match), **or**
2. The agent's `counties_served` contains the county that the city belongs to (containment match).

**Data source for containment:** `us_cities` → `county_id` → join `us_counties`. The caller layer resolves city→county membership before invoking the helper, passing a second lookup structure alongside the flat location list.

**Implementation contract — what the caller provides:**

```php
// Caller layer builds these two inputs before calling the helper:
$clientCities   = ['st. petersburg', 'seminole'];    // normalized city names from listing
$clientCounties = ['pinellas'];                       // normalized county names from listing

// Agent locations resolved from profile_data:
$agentCities    = ['st. pete'];                       // from cities_served, normalized
$agentCounties  = ['pinellas'];                       // from counties_served, normalized

// County→cities map built by caller from us_cities/us_counties (no DB call in helper):
$countyToCities = [
    'pinellas' => ['st. petersburg', 'seminole', 'clearwater', 'madeira beach', ...],
    // ...
];
```

**Containment expansion (in helper):**

```php
// Expand agent counties into all cities they contain
$agentCitiesViaCounty = [];
foreach ($agentCounties as $county) {
    $agentCitiesViaCounty = array_merge($agentCitiesViaCounty, $countyToCities[$county] ?? []);
}
$agentCitiesExpanded = array_unique(array_merge($agentCities, $agentCitiesViaCounty));

// Also expand client counties into cities for symmetry
$clientCitiesViaCounty = [];
foreach ($clientCounties as $county) {
    $clientCitiesViaCounty = array_merge($clientCitiesViaCounty, $countyToCities[$county] ?? []);
}
$allClientCities = array_unique(array_merge($clientCities, $clientCitiesViaCounty));
```

**Scoring:** city-level intersection after expansion. If a client specifies only a county (no individual cities), expand it to all cities in that county before scoring.

### 6.8 Scoring Formula

```php
// After containment expansion (see §6.7):
// $allClientCities   = client cities + cities implied by client counties
// $agentCitiesExpanded = agent cities + cities implied by agent counties

$intersection = array_intersect($allClientCities, $agentCitiesExpanded);

$score = count($allClientCities) > 0
    ? round(count($intersection) / count($allClientCities) * 100)
    : config('match_scoring.service_area.no_client_location_default_score');  // 50
```

> **Why county containment matters in practice:** An agent whose `counties_served = "Pinellas"` serves St. Petersburg, Seminole, Clearwater, Madeira Beach, Dunedin, and ~20 other Pinellas cities. Without containment, a client specifying "St. Petersburg" would score 0% on service area against this agent despite a genuine geographic match — a significant false negative that would corrupt ranking order.

---

## 7. Recommended Weight Table

### 7.1 Final Weights

All weights sum to 100. `config/match_scoring.php` is the authoritative code-level source.

| Dimension | Weight | Active? | Rationale |
|---|---|---|---|
| **Services** | 35% | ✅ Active | Core deliverable — reduced from 50% to accommodate new dimensions; remains the single heaviest weight |
| **Terms** | 35% | ✅ Active | Legal commitment — compensation mismatches require renegotiation or rejection; equal weight to services |
| **Service Area** | 15% | Build 4 | Meaningful constraint — agents outside the target area are functionally mismatched; all four roles confirmed; normalization fully specified |
| **Experience** | 10% | Build 4 | Verifiable credential proxy; moderate weight since a newer agent with perfect terms can still be a good match |
| **Availability & Communication** | 5% | Build 4 | `client_preferred_comm_method` is two-sided; scheduling availability is one-sided; advisory, not disqualifying |
| **Compatibility** | 0% | Deferred | Client data exists (3/4 roles); agent data entirely absent; schema mismatch; see §4 |

**Verification:** 35 + 35 + 15 + 10 + 5 + 0 = 100 ✓

### 7.2 Transition Formula

**Current (unchanged):**
```
Overall = 50% × Services + 50% × Terms
```

**Target (Build 4, all three new dimensions enabled):**
```
Overall = (35 × services + 35 × terms + 15 × service_area + 10 × experience + 5 × availability) / 100
```

Because enabled dimensions sum to 100, this is a direct weighted average with no normalization needed. Enabling dimensions one at a time (e.g., experience first) requires normalizing by the sum of enabled weights, not 100 — see §8.5 for the implementation pattern.

### 7.3 Experience Cap Values

| Parameter | Value | Rationale |
|---|---|---|
| `years_cap` | 20 | Agents past 20 years treated equivalently; diminishing returns beyond this point |
| `transactions_cap` | 30 | 30 transactions/year is high-production; above this, agents are equivalent |
| `years_weight` | 0.70 | Favored: `year_licensed` is verifiable via state license databases |
| `transactions_weight` | 0.30 | Discounted: `transactions_last_12_months` is self-reported |

---

## 8. Implementation Guidance for Build 4

### 8.1 What to Add, What Not to Touch

**Add:** `service_area_score`, `experience_score`, `availability_score` to the return array of all four `*BidMatchScoreHelper` classes. Add a new overall-score calculation that reads weights from `config/match_scoring.php`.

**Do not touch:** `LOGICAL_FIELD_GROUPS`, `normalizeForMatch()`, `parseServices()`, `calculateTermsScore()`, or any existing scoring path. New dimensions are purely additive.

### 8.2 Service Area — Implementation Spec

```php
// config('match_scoring.service_area.client_location_keys') provides per-role sources
// Seller:   city_id/county_id FK columns on seller_agent_auctions → join us_cities/us_counties
// Buyer:    $listing->getMeta('cities') + getMeta('counties') → json_decode
// Tenant:   same as Buyer
// Landlord: $listing->getMeta('property_city') + getMeta('property_county')

// All paths: strip suffix → lowercase → compare against agent's parsed cities_served / counties_served
$clientLocs = normalizeClientLocations($listing, $role);  // returns flat name array
$agentLocs  = normalizeAgentLocations($profileData);      // returns flat name array

$score = empty($clientLocs)
    ? config('match_scoring.service_area.no_client_location_default_score')
    : round(count(array_intersect($clientLocs, $agentLocs)) / count($clientLocs) * 100);
```

**Name-form mismatch:** resolve via Option A or B from §6.6 before launch.

### 8.3 Experience — Implementation Spec

```php
$caps  = config('match_scoring.experience_caps');
$years = max(0, (int) date('Y') - (int) ($profileData['year_licensed'] ?? date('Y')));
$txns  = max(0, (int) ($profileData['transactions_last_12_months'] ?? 0));

$score = round((
    min($years, $caps['years_cap']) / $caps['years_cap'] * $caps['years_weight'] +
    min($txns,  $caps['transactions_cap']) / $caps['transactions_cap'] * $caps['transactions_weight']
) * 100);
```

No client-side minimum field exists. Score on an absolute 0–100 scale; include at 10% weight.

> **Missing field treatment — explicit rule:** If `year_licensed` is null, blank, or zero in `profile_data`, treat years of experience as **0** (unknown, not scored). If `transactions_last_12_months` is null or blank, treat transaction count as **0**. Neither field may be inferred from indirect data (account creation date, listing history, bid count, or any other proxy). An agent who has not filled in their experience data receives a 0 on both sub-components of this dimension and earns no experience weight toward their overall score. This is intentional — inference from indirect fields would reward agents who have not self-disclosed, creating an opaque and inconsistent signal.

### 8.4 Availability & Communication — Implementation Spec

```php
$cfg = config('match_scoring.availability');

// Sub-component 1: communication method match (50% of dimension)
$clientMethod = $listing->getMeta('client_preferred_comm_method') ?? '';
$agentMethod  = $profileData['preferred_contact_method'] ?? '';

if (strtolower($agentMethod) === 'any' || strtolower($clientMethod) === 'other') {
    $commScore = $cfg['agent_any_score'];                              // 80
} elseif (strtolower($clientMethod) === strtolower($agentMethod)) {
    $commScore = $cfg['method_match_score'];                           // 100
} else {
    $commScore = $cfg['method_no_match_score'];                        // 0
}

// Sub-component 2: scheduling availability (50% of dimension; agent-side only)
$schedScore = 0;
$schedScore += (($profileData['evenings_available'] ?? '') === 'Yes') ? $cfg['evenings_points'] : 0;
$schedScore += (($profileData['weekends_available'] ?? '') === 'Yes') ? $cfg['weekends_points'] : 0;
$schedScore += $cfg['availability_status_scores'][$profileData['availability_status'] ?? ''] ?? 0;

$availScore = round(
    $commScore  * $cfg['comm_method_weight'] +
    $schedScore * $cfg['scheduling_weight']
);
```

### 8.5 Overall Score — Partial-Activation Pattern

```php
$weights  = config('match_scoring.dimensions');
$weighted = 0.0;
$total    = 0;

foreach ($weights as $key => $dim) {
    if (!$dim['enabled'] || $dim['weight'] === 0) continue;
    $score    = $scores[$key . '_score'] ?? 0;
    $weighted += $dim['weight'] * $score;
    $total    += $dim['weight'];
}

$overall = $total > 0 ? (int) round($weighted / $total) : 0;
```

This correctly handles partial activation: if only experience (10%) is enabled alongside services (35%) and terms (35%), the denominator is 80, not 100.

### 8.6 Unit Test Required

The implementation task must include a test asserting weight integrity:

```php
$this->assertSame(
    100,
    array_sum(array_column(config('match_scoring.dimensions'), 'weight')),
    'match_scoring dimension weights must sum to 100'
);
```

### 8.7 Production Rollout Safeguard

**No new scoring dimension may be enabled in production until the following gate is passed:**

> For each role (Seller, Buyer, Landlord, Tenant), manually review score breakdowns across **at least 25 real matches** per role and validate that the ranking outcomes are sensible relative to ground-truth expectations.

**What "review score breakdowns" means:**
- Run the full scoring pipeline (including the new dimension) against real listing + bid pairs already in the database.
- Inspect the per-dimension sub-scores, not just the final percentage.
- Confirm that bids which were previously accepted or rated positively by clients rank higher after enabling the new dimension, not lower.
- Confirm that the new dimension does not introduce unexpected score cliffs (e.g., an agent with perfect services/terms dropping from 95% to 45% solely because `year_licensed` is null).

**Rollout order recommendation:**
Enable dimensions one at a time in separate releases, following the partial-activation pattern in §8.5. Suggested order, lowest disruption first:

| Order | Dimension | Rationale |
|---|---|---|
| 1st | Experience | Agent-side only; no client matching complexity; lowest risk of false negatives |
| 2nd | Service Area | County containment adds complexity; validate normalization before widening |
| 3rd | Availability & Communication | Partially one-sided; confirm comm-method scoring does not dominate the 5% unfairly |

**Never enable all three simultaneously in a single release.** The 25-match manual review gate applies independently to each dimension before it is set to `'enabled' => true` in `config/match_scoring.php`.

### 8.8 Dead Scoring Paths (Deferred — No Agent Mirror)

These client-side fields currently have no corresponding agent input field. Scoring is impossible without first adding agent-side collection:

| Client Field(s) | Role(s) | Missing Agent Mirror |
|---|---|---|
| `maximum_budget` / `monthly_price` | Buyer, Tenant | No agent target price/rent estimate field |
| `offered_financing` / `financings` | Buyer, Seller | No agent `accepted_financing_types` field |
| `leaseLength` / `lease_for` | Landlord, Tenant | No agent `available_lease_lengths` field |
| `property_items` | All 4 | No agent property feature preference field |
| `idealDate` / `lease_date` | Landlord, Tenant | No agent `earliest_available_date` field |
| `showing_availability` (in compatibility JSON) | Seller | Could cross-match agent's evenings/weekends available; lower priority |

---

*End of audit. `config/match_scoring.php` is the single source of truth for all Build 4 weight values and scoring parameters. No further investigation is required before beginning implementation.*
