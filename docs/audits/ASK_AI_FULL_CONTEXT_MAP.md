# Ask AI Full Context Map

**Date:** 2026-06-05  
**Scope:** Field-by-field coverage table for all four listing roles (Seller, Buyer, Landlord, Tenant). Documents every native column, EAV/meta field, FAQ config key, and DNA/intelligence output. Serves as the permanent master reference for all future Ask AI development.

**Classification legend:**
- **Public-Factual** — Factual data safe to surface in AI responses; already shown publicly on the listing page
- **Compliance-Sensitive** — Requires explicit governance approval before exposure (financial docs, legal disclosures, PII-adjacent)
- **Internal-Only** — Platform operations data; must never be surfaced to users via AI
- **Prohibited** — Protected-class or fair housing sensitive; hard blocked from AI pipeline

**Current exposure legend:**
- ✅ Yes — field is currently assembled by `AskAiContextBuilderService` and passed to the prompt builder
- ❌ No — field exists in the database/config but is NOT currently visible to any Ask AI service
- N/A — not applicable (the field does not exist for this role)

---

## Section 1 — Seller Role (`property_auctions` table + EAV via `property_auction_metas`)

### 1.1 Native Columns — `property_auctions`

| Field | Human Label | Data Type | Classification | Currently on Public Listing Page | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|---|---|---|
| `id` | Listing ID | int | Internal-Only | No | ✅ Yes (listing.listing_id) | Keep — admin/attribution only |
| `user_id` | Owner User ID | int | Internal-Only | No | ❌ No | Exclude — PII-adjacent |
| `seller_id` | Seller ID | int | Internal-Only | No | ❌ No | Exclude — PII-adjacent |
| `title` | Listing Title | string | Public-Factual | Yes | ✅ Yes (listing.listing_title) | Keep |
| `address` | Property Address | string | Public-Factual | Yes | ❌ No | Include in `listing_facts` context |
| `city_id` | City (FK) | int | Internal-Only | Resolved to name | ✅ Yes (listing.city via info/resolve) | Keep (resolved name only) |
| `state_id` | State (FK) | int | Internal-Only | Resolved to name | ✅ Yes (listing.state) | Keep (resolved name only) |
| `county_id` | County (FK) | int | Internal-Only | Resolved to name | ✅ Yes (listing.county) | Keep (resolved name only) |
| `description` | Listing Description | text | Public-Factual | Yes | ❌ No | Include — strong factual context |
| `bedroom_id` | Bedrooms (FK) | int | Public-Factual | Yes | ❌ No | Include — resolve to bedroom count |
| `bathroom_id` | Bathrooms (FK) | int | Public-Factual | Yes | ❌ No | Include — resolve to bathroom count |
| `heated_sqft` | Heated Square Feet | string | Public-Factual | Yes | ❌ No | Include |
| `year_built` | Year Built | string | Public-Factual | Yes | ❌ No | Include |
| `starting_price` | Asking Price / Starting Price | float | Public-Factual | Yes | ❌ No | Include — top factual ask |
| `buy_now_price` | Buy Now Price | float | Public-Factual | Conditional | ❌ No | Include |
| `reserve_price` | Reserve Price | float | Compliance-Sensitive | Sometimes hidden | ❌ No | Exclude by default; seller controls visibility |
| `pool` | Pool | enum (Yes/No) | Public-Factual | Yes | ❌ No | Include |
| `pool_type` | Pool Type | string | Public-Factual | Yes | ❌ No | Include |
| `carport` | Carport | enum (Yes/No) | Public-Factual | Yes | ❌ No | Include |
| `garage` | Garage | enum (Yes/No) | Public-Factual | Yes | ❌ No | Include |
| `garage_spaces` | Garage Spaces | enum | Public-Factual | Yes | ❌ No | Include |
| `water_view` | Water View | enum (Yes/No) | Public-Factual | Yes | ❌ No | Include |
| `water_extras` | Water Extras | enum (Yes/No) | Public-Factual | Yes | ❌ No | Include |
| `hoa_association` | HOA Association | enum (Yes/No) | Public-Factual | Yes | ❌ No | Include |
| `hoa_fee` | HOA Monthly Fee | string | Public-Factual | Yes | ❌ No | Include — frequent user ask |
| `hoa_fee_requirement` | HOA Fee Requirement | enum | Public-Factual | Yes | ❌ No | Include |
| `hoa_payment_schedule` | HOA Payment Schedule | string | Public-Factual | Yes | ❌ No | Include |
| `hoa_manager_contact` | HOA Manager Contact | text | Compliance-Sensitive | No | ❌ No | Exclude — PII |
| `condo_fee` | Condo Fee | string | Public-Factual | Yes | ❌ No | Include |
| `condo_fee_schedule` | Condo Fee Schedule | string | Public-Factual | Yes | ❌ No | Include |
| `pets_allowed` | Pets Allowed | enum (Yes/No) | Public-Factual | Yes | ❌ No | Include |
| `number_of_pets_allowed` | Max Pets | enum | Public-Factual | Yes | ❌ No | Include |
| `max_pet_weight` | Max Pet Weight | string | Public-Factual | Yes | ❌ No | Include |
| `pet_restrictions` | Pet Restrictions | text | Public-Factual | Yes | ❌ No | Include |
| `rental_restrictions` | Rental Restrictions | enum (Yes/No) | Public-Factual | Yes | ❌ No | Include |
| `rental_restrictions_desription` | Rental Restrictions Detail | text | Public-Factual | Yes | ❌ No | Include |
| `is_in_flood_zone` | In Flood Zone | enum (Yes/No) | Compliance-Sensitive | Yes | ❌ No | Include with disclosure |
| `flood_zone_code` | Flood Zone Code | string | Compliance-Sensitive | Yes | ❌ No | Include with disclosure |
| `lease_terms` | Lease Terms | string | Public-Factual | Yes (income props) | ❌ No | Include |
| `tenant_pays` | Tenant Pays | string | Public-Factual | Yes (income props) | ❌ No | Include |
| `landlord_pays` | Landlord Pays | string | Public-Factual | Yes (income props) | ❌ No | Include |
| `closing_date` | Preferred Closing Date | date | Public-Factual | Yes | ❌ No | Include |
| `looking_another_property` | Looking for Another Property | enum | Public-Factual | No | ❌ No | Exclude — internal workflow |
| `mls_id` | MLS ID | string | Public-Factual | Yes | ❌ No | Include — agent reference |
| `is_approved` | Approval Status | bool | Internal-Only | Indirectly | ✅ Yes (listing.listing_status) | Keep (as status label) |
| `sold` | Sold Status | bool | Public-Factual | Yes | ❌ No | Include |
| `auction_length` | Auction Duration | string | Public-Factual | Yes | ❌ No | Include |
| `ai_share_token` | AI Share Token | string | Internal-Only | No | ❌ No | Exclude — internal key |
| `seller_name` | Seller Name | string | Compliance-Sensitive | No | ❌ No | Exclude — PII |
| `brokerage` | Brokerage | string | Compliance-Sensitive | Partial | ❌ No | Exclude from AI — compliance boundary |
| `phone_number` | Phone Number | string | Compliance-Sensitive | No | ❌ No | Exclude — PII |
| `email` | Email | string | Compliance-Sensitive | No | ❌ No | Exclude — PII |
| `created_at` | Created Date | timestamp | Internal-Only | No | ✅ Yes (listing.created_at) | Keep |
| `updated_at` | Last Updated | timestamp | Internal-Only | No | ✅ Yes (listing.updated_at) | Keep |

### 1.2 EAV Meta Keys — `property_auction_metas` (accessed via `$listing->info($key)`)

| Meta Key | Human Label | Classification | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|---|
| `property_type` | Property Type | Public-Factual | ✅ Yes (listing.property_type) | Keep |
| `listing_title` | Listing Title | Public-Factual | ✅ Yes (listing.listing_title) | Keep |
| `listing_ai_faq` | FAQ Answers (JSON blob) | Public-Factual | ❌ No | **Include as `faq_answers` source (Task C)** |
| `showing_instructions` | Showing Instructions | Public-Factual | ❌ No | Include in listing context |
| `service_type` | Service Type (Full/Limited) | Internal-Only | ❌ No | Include for context |
| All `ai_faq_seller.php` keys (see 1.3) | Seller FAQ Answers | Public-Factual | ❌ No | **Include via faq_answers (Task C)** |

### 1.3 FAQ Config Keys — `config/ai_faq_seller.php`

All keys below are stored in `listing_ai_faq` JSON meta when answered by the seller.

**Group: Property Condition & Maintenance**

| Config Key | Question Label | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|
| `roof_age_and_condition` | How old is the roof, and what condition is it in? | ❌ No | **Include via faq_answers** |
| `hvac_system_age` | How old is the HVAC system, and when was it last serviced? | ❌ No | **Include via faq_answers** |
| `water_heater_age_type` | How old is the water heater, and what type is it? | ❌ No | **Include via faq_answers** |
| `recent_renovations_list` | What renovations or upgrades have been made, and when? | ❌ No | **Include via faq_answers** |
| `permits_for_renovations` | Were all renovations completed with proper permits? | ❌ No | **Include via faq_answers** |
| `known_defects_issues` | Are there any known defects, issues, or deferred repairs? | ❌ No | **Include via faq_answers** |
| `foundation_type_and_issues` | What type of foundation does the property have? | ❌ No | **Include via faq_answers** |
| `pest_termite_history` | Has the property ever had pest or termite issues? | ❌ No | **Include via faq_answers** |
| `flood_damage_history` | Has the property ever flooded or experienced water damage? | ❌ No | **Include via faq_answers** |
| `mold_issues_history` | Has the property ever had mold issues? | ❌ No | **Include via faq_answers** |

**Group: Financial & Utility Insights**

| Config Key | Question Label | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|
| `average_utility_costs` | What are the average monthly utility costs? | ❌ No | **Include via faq_answers** |
| `internet_utility_providers` | Which internet and utility providers serve this property? | ❌ No | **Include via faq_answers** |
| `seller_concessions_offered` | Are you open to offering seller concessions or repair credits? | ❌ No | **Include via faq_answers** |

**Group: Location & Lifestyle**

| Config Key | Question Label | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|
| `neighborhood_character` | How would you describe the neighborhood vibe? | ❌ No | **Include via faq_answers** |
| `traffic_or_noise_concerns` | Are there any notable traffic, noise, or nuisance concerns? | ❌ No | **Include via faq_answers** |
| `planned_nearby_development` | Are there any planned developments nearby? | ❌ No | **Include via faq_answers** |
| `commute_options_access` | What are typical commute options and travel times? | ❌ No | **Include via faq_answers** |
| `natural_light_orientation` | How is the natural light, and which direction does the home face? | ❌ No | **Include via faq_answers** |
| `nearby_amenities_description` | What nearby amenities do you value most? | ❌ No | **Include via faq_answers** |
| `neighborhood_restrictions` | Are there deed restrictions or neighborhood rules? | ❌ No | **Include via faq_answers** |

**Group: Flexibility & Negotiation**

| Config Key | Question Label | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|
| `closing_timeline_flexibility` | How flexible are you on the closing date? | ❌ No | **Include via faq_answers** |
| `seller_leaseback_option` | Would you consider a seller leaseback arrangement? | ❌ No | **Include via faq_answers** |
| `items_excluded_from_sale` | Are there any fixtures or items excluded from the sale? | ❌ No | **Include via faq_answers** |
| `furniture_negotiability` | Is any furniture negotiable as part of the sale? | ❌ No | **Include via faq_answers** |
| `as_is_condition` | Is this being sold as-is, or are you open to repairs? | ❌ No | **Include via faq_answers** |
| `environmental_concerns` | Are there any known environmental concerns? | ❌ No | **Include via faq_answers (with compliance disclosure)** |

**Group: Hidden Selling Points**

| Config Key | Question Label | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|
| `unique_selling_points` | What features won't be obvious from listing photos? | ❌ No | **Include via faq_answers** |
| `seller_favorite_features` | What aspects of this home will you miss the most? | ❌ No | **Include via faq_answers** |
| `seller_motivation_for_selling` | Is there anything about your reason for selling buyers should know? | ❌ No | Include via faq_answers (optional; seller may leave blank) |
| `move_in_ready_status` | Is the property move-in ready? | ❌ No | **Include via faq_answers** |
| `parking_arrangements` | What parking is available? | ❌ No | **Include via faq_answers** |
| `storage_space_available` | What storage options are available? | ❌ No | **Include via faq_answers** |
| `hoa_community_highlights` | What do you love most about the HOA amenities? | ❌ No | **Include via faq_answers** |

**Add-On Keys (Income/Commercial — `commercial_income` group):**
`annual_net_operating_income`, `current_cap_rate`, `existing_tenant_lease_terms`, `current_occupancy_rate`, `annual_operating_expenses_detail`, `value_add_opportunities` — all ❌ No, recommend **Include via faq_answers for income/commercial listings**.

**Add-On Keys (Business Opportunity — `business_opportunity` group):**
`annual_business_revenue`, `annual_net_profit`, `business_reason_for_selling`, `business_employee_count`, `seller_training_transition`, `business_lease_status`, `inventory_equipment_included` — all ❌ No, recommend **Include via faq_answers for business opportunity listings**.

**Add-On Keys (Vacant Land — `vacant_land` group):**
`land_utilities_availability`, `land_zoning_permitted_uses`, `land_access_and_road`, `land_soil_and_topography`, `land_survey_available`, `land_development_restrictions` — all ❌ No, recommend **Include via faq_answers for vacant land listings**.

### 1.4 DNA / Intelligence Outputs (Seller)

| Source | Fields | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|
| `PropertyDnaProfile` | `property_strengths`, `property_highlights`, `property_positioning`, `property_target_audiences`, `property_personality_tags`, `property_story` | ✅ Yes (property_intelligence block) | Keep |
| `PropertyLocationDna` | `lifestyle_scores`, `lifestyle_categories`, `location_narrative`, `lifestyle_version`, `geocode_status` | ✅ Yes (location_intelligence block) | Keep |
| `LocationDnaIntelligenceContextService` | `nearest_highlights`, `available_categories`, `missing_categories`, `coastal_features`, `daily_convenience`, `outdoor_recreation`, `transportation` | ✅ Yes (merged into location_intelligence) | Keep |
| `LocationDnaMarketingContextService` | `marketing_context` | ✅ Yes (location_intelligence.marketing_context) | Keep |
| `AcceptedBidSummary` | `accepted_bid_id`, `summary_html`, `summary_pdf_path` | ✅ Yes (offer_analysis block) | Keep (PII fields excluded) |

---

## Section 2 — Buyer Role (`buyer_criteria_auctions` table + EAV via `buyer_criteria_auction_metas`)

### 2.1 Native Columns — `buyer_criteria_auctions`

| Field | Human Label | Data Type | Classification | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|---|---|
| `id` | Listing ID | int | Internal-Only | ✅ Yes (listing.listing_id) | Keep |
| `user_id` | Owner User ID | int | Internal-Only | ❌ No | Exclude — PII |
| `title` | Listing Title | string | Public-Factual | ✅ Yes (listing.listing_title) | Keep |
| `description` | Description | string | Public-Factual | ❌ No | Include |
| `max_price` | Maximum Purchase Budget | float | Public-Factual | ❌ No | Include — key buyer criterion |
| `bedrooms` | Desired Bedrooms | string | Public-Factual | ❌ No | Include in listing_facts context |
| `bathrooms` | Desired Bathrooms | string | Public-Factual | ❌ No | Include in listing_facts context |
| `sqft` | Desired Square Footage | string | Public-Factual | ❌ No | Include |
| `pool` | Pool Required | string | Public-Factual | ❌ No | Include |
| `carport` | Carport Required | string | Public-Factual | ❌ No | Include |
| `garage` | Garage Required | string | Public-Factual | ❌ No | Include |
| `garage_spaces` | Garage Spaces Required | string | Public-Factual | ❌ No | Include |
| `water_view` | Water View Required | string | Public-Factual | ❌ No | Include |
| `hoa` | HOA Acceptable | string | Public-Factual | ❌ No | Include |
| `hoa_fee_requirement` | HOA Fee Stance | string | Public-Factual | ❌ No | Include |
| `max_hoa_fee` | Maximum HOA Fee Acceptable | string | Public-Factual | ❌ No | Include |
| `pets_allowed` | Buyer Has Pets | string | Public-Factual | ❌ No | Include |
| `pets_detail` | Pet Details | text | Public-Factual | ❌ No | Include |
| `pets_breed` | Pet Breed(s) | string | Public-Factual | ❌ No | Include |
| `pets_weight` | Pet Weight | string | Public-Factual | ❌ No | Include |
| `loan_pre_approved` | Pre-Approved for Loan | string | Public-Factual | ❌ No | Include |
| `preapproval_amount` | Pre-Approval Amount | string | Compliance-Sensitive | ❌ No | Include with disclosure |
| `financing_id` | Financing Type (FK) | int | Public-Factual | ❌ No | Include (resolved label) |
| `inspection_period` | Inspection Period | string | Public-Factual | ❌ No | Include |
| `closing_days` | Desired Closing Timeline | string | Public-Factual | ❌ No | Include |
| `contingencies` | Contingencies | string | Public-Factual | ❌ No | Include |
| `escrow_amount` | Escrow Amount | float | Compliance-Sensitive | ❌ No | Include with disclosure |
| `seller_premium` / `buyer_premium` | Premium Fields | float | Compliance-Sensitive | ❌ No | Include with disclosure |
| `is_approved` | Approval Status | bool | Internal-Only | ✅ Yes (listing.listing_status) | Keep |
| `ai_share_token` | AI Share Token | string | Internal-Only | ❌ No | Exclude |
| `buyer_name` | Buyer Name | string | Compliance-Sensitive | ❌ No | Exclude — PII |
| `buyer_phone` | Buyer Phone | string | Compliance-Sensitive | ❌ No | Exclude — PII |
| `buyer_email` | Buyer Email | string | Compliance-Sensitive | ❌ No | Exclude — PII |
| `created_at` / `updated_at` | Timestamps | timestamp | Internal-Only | ✅ Yes | Keep |

### 2.2 FAQ Config Keys — `config/ai_faq_buyer.php`

All keys stored in `listing_ai_faq` meta JSON when answered by the buyer.

**Group: Buyer Intent & Lifestyle**

| Config Key | Question Label | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|
| `buyer_motivation` | What's driving your decision to buy right now? | ❌ No | **Include via faq_answers** |
| `buyer_lifestyle_goals` | How do you envision using this property? | ❌ No | **Include via faq_answers** |
| `buyer_deal_breakers` | What are your absolute deal-breakers? | ❌ No | **Include via faq_answers** |
| `buyer_renovation_tolerance` | Would you consider a property that needs work? | ❌ No | **Include via faq_answers** |
| `buyer_wfh_needs` | Do you work from home? What's your ideal setup? | ❌ No | **Include via faq_answers** |
| `buyer_outdoor_space` | How important is outdoor space? | ❌ No | **Include via faq_answers** |
| `buyer_long_term_goals` | Is this a forever home, starter home, or investment? | ❌ No | **Include via faq_answers** |
| `buyer_biggest_concern` | What's your biggest concern about this purchase? | ❌ No | **Include via faq_answers** |

**Group: Location & Community**

| Config Key | Question Label | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|
| `buyer_neighborhood_preferences` | What kind of neighborhood feel are you looking for? | ❌ No | **Include via faq_answers** |
| `buyer_school_district` | Is a specific school district a hard requirement? | ❌ No | Include via faq_answers — *note: school district preference is the buyer's stated criterion, not a demographic description; this is permissible when framed as buyer's own requirement* |
| `buyer_commute_requirements` | Do you have commute or transit requirements? | ❌ No | **Include via faq_answers** |
| `buyer_noise_tolerance` | How sensitive are you to noise? | ❌ No | **Include via faq_answers** |
| `buyer_area_familiarity` | How familiar are you with the neighborhoods you're considering? | ❌ No | **Include via faq_answers** |
| `buyer_prefers_off_market` | Are you open to off-market listings? | ❌ No | **Include via faq_answers** |

**Group: Property Preferences**

| Config Key | Question Label | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|
| `buyer_property_style` | Do you have a preference for architectural style? | ❌ No | **Include via faq_answers** |
| `buyer_must_have_features` | What are your absolute must-have property features? | ❌ No | **Include via faq_answers** |
| `buyer_nice_to_have` | What features are on your wish list? | ❌ No | **Include via faq_answers** |
| `buyer_hoa_acceptable` | Are you comfortable with an HOA? | ❌ No | **Include via faq_answers** |
| `buyer_accessibility` | Do you need any accessibility features? | ❌ No | **Include via faq_answers** |
| `buyer_privacy_requirements` | Do you have specific privacy needs? | ❌ No | **Include via faq_answers** |
| `buyer_view_preference` | Is a specific view important to you? | ❌ No | **Include via faq_answers** |

**Group: Buyer Situation & Flexibility**

| Config Key | Question Label | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|
| `buyer_current_situation` | What's your current living situation? | ❌ No | **Include via faq_answers** |
| `buyer_simultaneous_close` | Do you need to sell a current property and close simultaneously? | ❌ No | **Include via faq_answers** |
| `buyer_leaseback` | Would you allow the seller to stay on a short leaseback? | ❌ No | **Include via faq_answers** |
| `buyer_relocation` | Are you relocating from another area? | ❌ No | **Include via faq_answers** |
| `buyer_lost_deal` | Have you made offers on other properties that didn't work out? | ❌ No | **Include via faq_answers** |
| `buyer_seller_concessions` | Would you consider asking for seller concessions? | ❌ No | **Include via faq_answers** |
| `buyer_flexibility` | How flexible are you on location, timing, or property type? | ❌ No | **Include via faq_answers** |

**Add-On Keys (Commercial/Income, Business Opportunity, Vacant Land):**
`com_property_use`, `com_investment_type`, `com_cap_rate_target`, `com_occupancy_rate`, `com_lease_terms`, `com_1031_exchange`, `com_due_diligence_period`, `com_environmental_concerns`, `biz_type_seeking`, `biz_revenue_required`, `biz_profit_required`, `biz_training_expected`, `biz_staff_included`, `biz_non_compete`, `biz_sba_financing`, `land_intended_use`, `land_zoning_required`, `land_utilities_needed`, `land_soil_testing`, `land_build_timeline`, `land_access_requirements` — all ❌ No, recommend **Include via faq_answers for relevant property types**.

### 2.3 DNA / Intelligence Outputs (Buyer)

| Source | Fields | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|
| `BuyerTenantDnaProfile` (buyer) | `avatar_type`, `primary_motivation`, `secondary_motivation`, `buyer_narrative`, `buyer_preference_summary`, `buyer_personality_tags`, `buyer_match_preferences`, `avatar_confidence_score`, `buyer_readiness_score`, `buyer_avatar_version` | ✅ Yes (buyer_avatar block) | Keep |
| `PropertyLocationDna` | lifestyle data | ✅ Yes (location_intelligence block) | Keep |

---

## Section 3 — Landlord Role (`landlord_auctions` table + EAV via `landlord_auction_metas`)

### 3.1 Native Columns — `landlord_auctions`

| Field | Human Label | Data Type | Classification | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|---|---|
| `id` | Listing ID | int | Internal-Only | ✅ Yes (listing.listing_id) | Keep |
| `user_id` | Owner User ID | int | Internal-Only | ❌ No | Exclude — PII |
| `auction_type` | Auction/Listing Type | string | Internal-Only | ❌ No | Include for context |
| `is_approved` | Approval Status | bool | Internal-Only | ✅ Yes (listing.listing_status) | Keep |
| `ai_share_token` | AI Share Token | string | Internal-Only | ❌ No | Exclude |
| `created_at` / `updated_at` | Timestamps | timestamp | Internal-Only | ✅ Yes | Keep |

**Note:** The `landlord_auctions` table has very few native columns. Almost all factual rental listing data (rent amount, bedrooms, bathrooms, property type, pets policy, lease terms, etc.) is stored in EAV meta via `landlord_auction_metas`. The `info()` method is used to retrieve all factual fields.

### 3.2 EAV Meta Keys — `landlord_auction_metas` (via `$listing->info($key)`)

The `landlord_auctions` table has 8 native columns. All factual listing data is stored as key–value rows in `landlord_auction_metas`. The following is the complete enumeration of EAV meta keys written by `LandlordOfferListing.php`.

**Group A: Workflow & Admin (Internal-Only — exclude from Ask AI)**

| Meta Key | Human Label | Classification | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|---|
| `workflow_type` | Workflow Type | Internal-Only | ❌ No | Exclude |
| `user_type` | User Type | Internal-Only | ❌ No | Exclude |
| `listing_status` | Listing Status | Internal-Only | ✅ Yes (listing.listing_status) | Keep |
| `auction_type` | Auction Type | Internal-Only | ❌ No | Exclude |
| `working_with_agent` | Working With Agent | Internal-Only | ❌ No | Exclude |
| `listing_date` | Listing Date | Internal-Only | ❌ No | Exclude |
| `desired_agent_hire_date` | Desired Agent Hire Date | Internal-Only | ❌ No | Exclude |
| `expiration_date` | Bid Expiration Date | Internal-Only | ❌ No | Exclude |
| `auction_time` | Auction Duration | Internal-Only | ❌ No | Exclude |
| `referral_percentage` | Referral Percentage | Compliance-Sensitive | ❌ No | Exclude |
| `agent_bid_visibility` | Agent Bid Visibility | Internal-Only | ❌ No | Exclude |
| `meeting_Preference` | Meeting Preference | Internal-Only | ❌ No | Exclude |
| `number_of_unit` | Number of Units | Public-Factual | ❌ No | Include |
| `draft_version` | Draft Version | Internal-Only | ❌ No | Exclude |
| `parent_draft_id` | Parent Draft ID | Internal-Only | ❌ No | Exclude |
| `draft_payload_hash` | Draft Hash | Internal-Only | ❌ No | Exclude |

**Group B: Location (Public-Factual — partially exposed)**

| Meta Key | Human Label | Classification | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|---|
| `title` / `listing_title` | Listing Title | Public-Factual | ✅ Yes | Keep |
| `property_city` | Property City | Public-Factual | ✅ Yes (listing.city) | Keep |
| `property_state` | Property State | Public-Factual | ✅ Yes (listing.state) | Keep |
| `property_county` | Property County | Public-Factual | ✅ Yes (listing.county) | Keep |
| `property_zip` | Property ZIP | Public-Factual | ❌ No | Include |
| `state` | Target State (search area) | Public-Factual | ❌ No | Include |
| `zip_code` | Target ZIP | Public-Factual | ❌ No | Include |
| `cities` | Target Cities (JSON array) | Public-Factual | ❌ No | Include (decoded) |
| `counties` | Target Counties (JSON array) | Public-Factual | ❌ No | Include (decoded) |
| `zipCodes` | Target ZIPs (JSON array) | Public-Factual | ❌ No | Include (decoded) |

**Group C: Property Details (Public-Factual — all excluded)**

| Meta Key | Human Label | Classification | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|---|
| `property_type` | Property Type | Public-Factual | ✅ Yes | Keep |
| `property_items` | Property Sub-Types (JSON) | Public-Factual | ❌ No | Include (decoded) |
| `leasing_space` | Leasing Space Category | Public-Factual | ❌ No | Include |
| `condition_prop` | Property Condition | Public-Factual | ❌ No | **Include** |
| `bedrooms` | Bedrooms | Public-Factual | ❌ No | **Include** |
| `bathrooms` | Bathrooms | Public-Factual | ❌ No | **Include** |
| `minimum_heated_square` | Minimum Heated Square Feet | Public-Factual | ❌ No | **Include** |
| `minimum_leaseable` | Minimum Leaseable Sq Ft | Public-Factual | ❌ No | Include |
| `min_acreage` | Minimum Acreage | Public-Factual | ❌ No | Include |
| `total_acreage` | Total Acreage | Public-Factual | ❌ No | Include |
| `unit_size` | Unit Size | Public-Factual | ❌ No | Include |
| `appliances` | Included Appliances (JSON) | Public-Factual | ❌ No | **Include (decoded)** |
| `preferance_details` | Additional Preference Details | Public-Factual | ❌ No | Include |

**Group D: Financial / Lease Terms (Public-Factual or Compliance-Sensitive)**

| Meta Key | Human Label | Classification | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|---|
| `maximum_budget` | Monthly Rent (Max) | Public-Factual | ❌ No | **Include — top factual ask** |
| `last_month_rent_required` | Last Month Rent Required | Public-Factual | ❌ No | Include |
| `total_move_in_funds_required` | Total Move-In Funds Required | Compliance-Sensitive | ❌ No | Include with disclosure |
| `security_deposit_amount` | Security Deposit Amount | Compliance-Sensitive | ❌ No | Include with disclosure |
| `min_income_requirement` | Minimum Income Requirement | Compliance-Sensitive | ❌ No | Include with disclosure |
| `pre_approved` | Financing Pre-Approved | Public-Factual | ❌ No | Include |
| `pre_approval_amount` | Pre-Approval Amount | Compliance-Sensitive | ❌ No | Include with disclosure |
| `offered_financing` | Financing Options (JSON) | Public-Factual | ❌ No | Include (decoded) |
| `down_payment_type` | Down Payment Type | Public-Factual | ❌ No | Include |
| `down_payment_amount` | Down Payment Amount | Compliance-Sensitive | ❌ No | Include with disclosure |
| `sale_provision` | Sale Provision | Public-Factual | ❌ No | Include |
| `assignment_fee_type` | Assignment Fee Type | Compliance-Sensitive | ❌ No | Exclude |
| `assignment_fee_amount` | Assignment Fee Amount | Compliance-Sensitive | ❌ No | Exclude |

**Group E: Pet Policy & Tenant Terms (Public-Factual — all excluded)**

| Meta Key | Human Label | Classification | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|---|
| `pet_policy` | Pet Policy | Public-Factual | ❌ No | **Include** |
| `pet_deposit_fee_rent` | Pet Deposit / Fee / Rent | Public-Factual | ❌ No | Include |
| `pet_max_weight_lbs` | Max Pet Weight (lbs) | Public-Factual | ❌ No | Include |
| `pet_species_allowed` | Pet Species Allowed (JSON) | Public-Factual | ❌ No | **Include (decoded)** |
| `pet_deposit_amount` | Pet Deposit Amount | Compliance-Sensitive | ❌ No | Include with disclosure |
| `pet_monthly_fee` | Monthly Pet Fee | Public-Factual | ❌ No | Include |
| `available_date` | Available Move-In Date | Public-Factual | ❌ No | **Include** |
| `number_of_occupants_allowed` | Max Occupants Allowed | Public-Factual | ❌ No | Include |
| `parking_terms` | Parking Terms | Public-Factual | ❌ No | **Include** |
| `ll_maintenance_responsibility` | Landlord Maintenance Scope | Public-Factual | ❌ No | Include |
| `renewal_option_offered` | Renewal Option Offered | Public-Factual | ❌ No | Include |
| `renewal_option_details` | Renewal Option Details | Public-Factual | ❌ No | Include |
| `landlord_approval_conditions` | Approval Conditions | Public-Factual | ❌ No | Include |
| `additional_landlord_lease_terms` | Additional Lease Terms | Public-Factual | ❌ No | Include |
| `subletting_policy` | Subletting Policy | Public-Factual | ❌ No | **Include** |
| `smoking_policy` | Smoking Policy | Public-Factual | ❌ No | **Include** |
| `utilities` | Utilities Included/Excluded | Public-Factual | ❌ No | **Include** |

**Group F: Commercial Lease Terms (Public-Factual — all excluded)**

| Meta Key | Human Label | Classification | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|---|
| `commercial_lease_type` | Commercial Lease Type | Public-Factual | ❌ No | Include (commercial only) |
| `cam_nnn_additional_rent_charges` | CAM/NNN Additional Charges | Public-Factual | ❌ No | Include (commercial only) |
| `rent_escalation_terms` | Rent Escalation Terms | Public-Factual | ❌ No | Include (commercial only) |
| `tenant_improvement_buildout_terms` | Tenant Improvement / Buildout Terms | Public-Factual | ❌ No | Include (commercial only) |
| `permitted_use_restrictions` | Permitted Use Restrictions | Public-Factual | ❌ No | Include (commercial only) |
| `signage_rights` | Signage Rights | Public-Factual | ❌ No | Include (commercial only) |
| `commercial_parking_terms` | Commercial Parking Terms | Public-Factual | ❌ No | Include (commercial only) |
| `personal_guarantee_requirement` | Personal Guarantee Required | Compliance-Sensitive | ❌ No | Include with disclosure (commercial only) |
| `minimum_cap_rate` | Minimum Cap Rate | Public-Factual | ❌ No | Include (income properties) |

**Group G: Tax, Legal, Flood, HOA (Compliance-Sensitive — all excluded)**

| Meta Key | Human Label | Classification | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|---|
| `parcel_id` | Parcel ID | Public-Factual | ❌ No | Include |
| `tax_year` | Tax Year | Public-Factual | ❌ No | Include |
| `annual_property_taxes` | Annual Property Taxes | Public-Factual | ❌ No | Include with disclosure |
| `legal_description` | Legal Description | Compliance-Sensitive | ❌ No | Exclude from AI |
| `flood_zone_code` | Flood Zone Code | Compliance-Sensitive | ❌ No | Include with disclosure |
| `flood_insurance_required` | Flood Insurance Required | Compliance-Sensitive | ❌ No | Include with disclosure |
| `has_cdd` | CDD Present | Public-Factual | ❌ No | Include |
| `annual_cdd_fee` | Annual CDD Fee | Public-Factual | ❌ No | Include |
| `has_special_assessments` | Special Assessments | Public-Factual | ❌ No | Include |
| `special_assessment_amount` | Special Assessment Amount | Public-Factual | ❌ No | Include |
| `has_hoa` | HOA Present | Public-Factual | ❌ No | **Include** |
| `association_name` | Association Name | Public-Factual | ❌ No | Include |
| `association_fee_amount` | Association Fee Amount | Public-Factual | ❌ No | **Include** |
| `association_fee_frequency` | Association Fee Frequency | Public-Factual | ❌ No | Include |
| `association_approval_required` | Association Approval Required | Public-Factual | ❌ No | Include |
| `association_application_fee` | Association Application Fee | Public-Factual | ❌ No | Include |
| `association_fee_includes` | What Association Fee Includes (JSON) | Public-Factual | ❌ No | Include (decoded) |
| `association_amenities` | Association Amenities (JSON) | Public-Factual | ❌ No | Include (decoded) |
| `leasing_restrictions` | Leasing Restrictions | Public-Factual | ❌ No | Include |
| `min_lease_period` | Minimum Lease Period | Public-Factual | ❌ No | Include |
| `max_leases_per_year` | Max Leases Per Year | Public-Factual | ❌ No | Include |

**Group H: Disclosures (Compliance-Sensitive — exclude raw paths)**

| Meta Key | Human Label | Classification | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|---|
| `landlord_disclosure_available` | Landlord Disclosure Available | Compliance-Sensitive | ❌ No | Include boolean only |
| `survey_available` | Survey Available | Public-Factual | ❌ No | Include boolean only |
| `inspection_report_available` | Inspection Report Available | Public-Factual | ❌ No | Include boolean only |
| `flood_disclosure_available` | Flood Disclosure Available | Compliance-Sensitive | ❌ No | Include boolean only |
| `lead_based_paint_disclosure` | Lead-Based Paint Disclosure | Compliance-Sensitive | ❌ No | Include boolean only |
| `environmental_report_available` | Environmental Report Available | Compliance-Sensitive | ❌ No | Include boolean only |

**Group I: Contact / PII (Prohibited from Ask AI)**

| Meta Key | Human Label | Classification | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|---|
| `first_name` | First Name | Compliance-Sensitive | ❌ No | Exclude — PII |
| `last_name` | Last Name | Compliance-Sensitive | ❌ No | Exclude — PII |
| `phone_number` | Phone Number | Compliance-Sensitive | ❌ No | Exclude — PII |
| `email` | Email Address | Compliance-Sensitive | ❌ No | Exclude — PII |
| `agent_brokerage` | Agent Brokerage | Compliance-Sensitive | ❌ No | Exclude — compliance boundary |
| `agent_license_number` | Agent License Number | Compliance-Sensitive | ❌ No | Exclude — compliance boundary |

**Group J: AI / Media**

| Meta Key | Human Label | Classification | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|---|
| `listing_ai_faq` | FAQ Answers (JSON blob) | Public-Factual | ❌ No | **Include as `faq_answers` source (Task C)** |
| `video_link` | Video Tour Link | Public-Factual | ❌ No | Include (URL only) |
| `video_tour_url` | Hosted Video Tour URL | Public-Factual | ❌ No | Include (URL only) |
| `virtual_tour_url` | Virtual Tour URL | Public-Factual | ❌ No | Include (URL only) |

### 3.3 FAQ Config Keys — `config/ai_faq_landlord.php`

All keys stored in `listing_ai_faq` JSON meta when answered by the landlord.

**Group: Maintenance & Property Condition**

| Config Key | Question Label | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|
| `maintenance_request_response_time` | How are maintenance requests handled? | ❌ No | **Include via faq_answers** |
| `emergency_maintenance_available` | Is 24-hour emergency maintenance available? | ❌ No | **Include via faq_answers** |
| `heating_cooling_system` | What type of heating and cooling system does the property have? | ❌ No | **Include via faq_answers** |
| `laundry_situation` | Is there in-unit laundry or shared laundry? | ❌ No | **Include via faq_answers** |
| `storage_area_included` | Is there dedicated storage space? | ❌ No | **Include via faq_answers** |
| `internet_providers` | Which internet providers are available? | ❌ No | **Include via faq_answers** |
| `security_features` | What security features does the property have? | ❌ No | **Include via faq_answers** |
| `planned_renovations` | Are there any planned renovations that could affect tenants? | ❌ No | **Include via faq_answers** |

**Group: Location & Neighborhood**

| Config Key | Question Label | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|
| `neighborhood_character` | How would you describe the neighborhood feel? | ❌ No | **Include via faq_answers** |
| `noise_levels` | What's the noise level like? | ❌ No | **Include via faq_answers** |
| `nearby_amenities` | What dining, shopping, parks, or transit options are close by? | ❌ No | **Include via faq_answers** |
| `guest_parking` | Is guest parking available? | ❌ No | **Include via faq_answers** |
| `proximity_to_public_transit` | How close is the nearest public transit stop? | ❌ No | **Include via faq_answers** |

**Group: Lifestyle & Flexibility**

| Config Key | Question Label | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|
| `furnished_or_unfurnished` | Is the unit available furnished, unfurnished, or negotiable? | ❌ No | **Include via faq_answers** |
| `lease_renewal_process` | What does the lease renewal process look like? | ❌ No | **Include via faq_answers** |
| `notice_to_vacate_required` | How much notice is required to vacate? | ❌ No | **Include via faq_answers** |
| `preferred_tenant_qualities` | What qualities make for an ideal tenant? | ❌ No | Include via faq_answers |
| `subletting_allowed` | Is subletting or lease assignment allowed? | ❌ No | **Include via faq_answers** |
| `short_term_rentals_allowed` | Are short-term rentals permitted? | ❌ No | **Include via faq_answers** |
| `ev_charging_available` | Are EV charging stations available? | ❌ No | **Include via faq_answers** |
| `bicycle_storage_available` | Is bicycle storage available on-site? | ❌ No | **Include via faq_answers** |

**Group: High-Intent Tenant Questions**

| Config Key | Question Label | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|
| `what_makes_property_unique` | What makes this rental stand out? | ❌ No | **Include via faq_answers** |
| `pest_or_mold_history` | Has the property ever had pest or mold issues? | ❌ No | **Include via faq_answers** |
| `utilities_individually_metered` | Are utilities individually metered per unit? | ❌ No | **Include via faq_answers** |
| `renters_insurance_required` | Is renter's insurance required? | ❌ No | **Include via faq_answers** |
| `lease_to_own_option` | Is there any possibility of a lease-to-own arrangement? | ❌ No | **Include via faq_answers** |
| `previous_tenant_feedback` | What do past tenants typically say about living here? | ❌ No | **Include via faq_answers** |
| `smoking_policy` | What is the smoking policy? | ❌ No | **Include via faq_answers** |

**Add-On Keys (Commercial — `commercial` group):**
`commercial_cam_charges`, `commercial_lease_structure_type`, `commercial_tenant_improvement_allowance`, `commercial_buildout_flexibility`, `commercial_signage_rights`, `commercial_loading_dock_freight_elevator`, `commercial_electrical_capacity`, `commercial_parking_ratio`, `commercial_exclusivity_rights`, `commercial_expansion_option_rofr`, `commercial_landlord_maintenance_responsibilities`, `commercial_building_access_hours` — all ❌ No, recommend **Include via faq_answers for commercial property type**.

### 3.4 DNA / Intelligence Outputs (Landlord)

| Source | Fields | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|
| `PropertyDnaProfile` | Same as Seller (strengths, highlights, positioning, story) | ✅ Yes (property_intelligence block) | Keep |
| `PropertyLocationDna` | Lifestyle scores, location narrative, POI data | ✅ Yes (location_intelligence block) | Keep |

---

## Section 4 — Tenant Role (`tenant_criteria_auctions` table + EAV via `tenant_criteria_auction_metas`)

### 4.1 Native Columns — `tenant_criteria_auctions`

| Field | Human Label | Data Type | Classification | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|---|---|
| `id` | Listing ID | int | Internal-Only | ✅ Yes (listing.listing_id) | Keep |
| `user_id` | Owner User ID | int | Internal-Only | ❌ No | Exclude — PII |
| `listing_ai_faq` | FAQ Answers (native JSON column) | Public-Factual | ❌ No | **Include as faq_answers source (Task C)** |
| `display_bids` | Display Bids Flag | bool | Internal-Only | ❌ No | Exclude |
| `ai_share_token` | AI Share Token | string | Internal-Only | ❌ No | Exclude |
| `is_approved` | Approval Status | bool | Internal-Only | ✅ Yes (listing.listing_status) | Keep |
| `created_at` / `updated_at` | Timestamps | timestamp | Internal-Only | ✅ Yes | Keep |

**Note:** Like `landlord_auctions`, most tenant criteria data is stored in EAV meta via `tenant_criteria_auction_metas`. The `listing_ai_faq` column is a native JSON column (added by migration `2026_04_28_045541`).

### 4.1b EAV Meta Keys — `tenant_criteria_auction_metas` (via `$listing->info($key)`)

The following is the complete enumeration of EAV meta keys written by `TenantOfferListing.php`.

**Group A: Workflow & Admin (Internal-Only — exclude from Ask AI)**

| Meta Key | Human Label | Classification | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|---|
| `workflow_type` | Workflow Type | Internal-Only | ❌ No | Exclude |
| `user_type` | User Type | Internal-Only | ❌ No | Exclude |
| `listing_status` | Listing Status | Internal-Only | ✅ Yes (listing.listing_status) | Keep |
| `auction_type` | Auction Type | Internal-Only | ❌ No | Exclude |
| `working_with_agent` | Working With Agent | Internal-Only | ❌ No | Exclude |
| `referral_percentage` | Referral Percentage | Compliance-Sensitive | ❌ No | Exclude |
| `listing_date` | Listing Date | Internal-Only | ❌ No | Exclude |
| `desired_agent_hire_date` | Desired Agent Hire Date | Internal-Only | ❌ No | Exclude |
| `expiration_date` | Bid Expiration Date | Internal-Only | ❌ No | Exclude |
| `auction_time` | Auction Duration | Internal-Only | ❌ No | Exclude |
| `agent_bid_visibility` | Agent Bid Visibility | Internal-Only | ❌ No | Exclude |
| `meeting_Preference` | Meeting Preference | Internal-Only | ❌ No | Exclude |
| `number_of_unit` | Number of Units Needed | Public-Factual | ❌ No | Include |
| `current_status` | Current Housing Status | Public-Factual | ❌ No | Include |
| `draft_version` | Draft Version | Internal-Only | ❌ No | Exclude |
| `parent_draft_id` | Parent Draft ID | Internal-Only | ❌ No | Exclude |
| `draft_payload_hash` | Draft Hash | Internal-Only | ❌ No | Exclude |

**Group B: Location Criteria (Public-Factual — all excluded)**

| Meta Key | Human Label | Classification | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|---|
| `property_city` | Target City | Public-Factual | ✅ Yes (listing.city) | Keep |
| `property_state` | Target State | Public-Factual | ✅ Yes (listing.state) | Keep |
| `property_county` | Target County | Public-Factual | ✅ Yes (listing.county) | Keep |
| `property_zip` | Target ZIP | Public-Factual | ❌ No | Include |
| `state` | State (search area) | Public-Factual | ❌ No | Include |
| `zip_code` | ZIP Code | Public-Factual | ❌ No | Include |
| `cities` | Target Cities (JSON array) | Public-Factual | ❌ No | Include (decoded) |
| `counties` | Target Counties (JSON array) | Public-Factual | ❌ No | Include (decoded) |
| `zipCodes` | Target ZIPs (JSON array) | Public-Factual | ❌ No | Include (decoded) |

**Group C: Property Criteria (Public-Factual — all excluded)**

| Meta Key | Human Label | Classification | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|---|
| `property_type` | Property Type Wanted | Public-Factual | ✅ Yes | Keep |
| `property_items` | Property Sub-Types (JSON) | Public-Factual | ❌ No | Include (decoded) |
| `leasing_space` | Leasing Space Category | Public-Factual | ❌ No | Include |
| `condition_prop` | Acceptable Property Condition | Public-Factual | ❌ No | Include |
| `condition_prop_buyer` | Acceptable Conditions (JSON multi-select) | Public-Factual | ❌ No | Include (decoded) |
| `bedrooms` | Bedrooms Needed | Public-Factual | ❌ No | **Include** |
| `bathrooms` | Bathrooms Needed | Public-Factual | ❌ No | **Include** |
| `leasing_spaces_tenant` | Leaseable Space Types (JSON) | Public-Factual | ❌ No | Include (decoded) |
| `appliances` | Required/Desired Appliances (JSON) | Public-Factual | ❌ No | Include (decoded) |
| `restrictions` | Acceptable Restrictions | Public-Factual | ❌ No | Include |
| `common_areas_access` | Common Area Access Needed | Public-Factual | ❌ No | Include |
| `utilities` | Utility Preference | Public-Factual | ❌ No | **Include** |
| `bathroom_facilities` | Bathroom Facility Type | Public-Factual | ❌ No | Include |
| `room_size` | Room Size Requirement | Public-Factual | ❌ No | Include |
| `building_hours` | Building Hours Needed | Public-Factual | ❌ No | Include |
| `access_24_7` | 24/7 Access Required | Public-Factual | ❌ No | Include |
| `zoning_allows` | Zoning Requirements | Public-Factual | ❌ No | Include |
| `space_features` | Space Features Required | Public-Factual | ❌ No | Include |
| `guests_allowed` | Guests Allowed Preference | Public-Factual | ❌ No | Include |
| `shared_amenities` | Shared Amenities Desired | Public-Factual | ❌ No | Include |
| `storage_space` | Storage Space Required | Public-Factual | ❌ No | Include |
| `maintenance_by` | Who Handles Maintenance | Public-Factual | ❌ No | Include |
| `maintenance_response_time` | Expected Maintenance Response | Public-Factual | ❌ No | Include |

**Group D: Lease / Financial Preferences (Public-Factual or Compliance-Sensitive)**

| Meta Key | Human Label | Classification | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|---|
| `tenant_desired_lease_length` | Desired Lease Length | Public-Factual | ❌ No | **Include** |
| `security_deposit_budget` | Security Deposit Budget | Compliance-Sensitive | ❌ No | Include with disclosure |
| `move_in_funds_available` | Move-In Funds Available | Compliance-Sensitive | ❌ No | Include with disclosure |
| `first_month_rent_available` | First Month Rent Available | Compliance-Sensitive | ❌ No | Include with disclosure |
| `last_month_rent_available` | Last Month Rent Available | Compliance-Sensitive | ❌ No | Include with disclosure |
| `tenant_pays` | Utilities Tenant Is Willing To Pay (JSON) | Public-Factual | ❌ No | **Include (decoded)** |
| `utility_preference` | Utility Coverage Preference | Public-Factual | ❌ No | Include |
| `pet_information` | Pet Information | Public-Factual | ❌ No | **Include** |
| `number_of_occupants` | Number of Occupants | Public-Factual | ❌ No | Include |
| `parking_needed` | Parking Required | Public-Factual | ❌ No | **Include** |
| `maintenance_preference` | Maintenance Preference | Public-Factual | ❌ No | Include |
| `renewal_option_requested` | Renewal Option Requested | Public-Factual | ❌ No | Include |
| `renewal_option_details` | Renewal Option Details | Public-Factual | ❌ No | Include |
| `tenant_conditions` | Tenant Conditions | Public-Factual | ❌ No | Include |
| `additional_tenant_lease_terms` | Additional Lease Terms | Public-Factual | ❌ No | Include |
| `maximum_budget` | Maximum Rent Budget | Public-Factual | ❌ No | **Include — top factual ask** |
| `pre_approved` | Financing Pre-Approved | Public-Factual | ❌ No | Include |
| `offered_financing` | Financing Options (JSON) | Public-Factual | ❌ No | Include (decoded) |
| `sale_provision` | Sale Provision | Public-Factual | ❌ No | Include |

**Group E: Commercial Criteria (Public-Factual — all excluded)**

| Meta Key | Human Label | Classification | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|---|
| `commercial_lease_type_preference` | Commercial Lease Type Preference | Public-Factual | ❌ No | Include (commercial only) |
| `cam_nnn_preference` | CAM/NNN Preference | Public-Factual | ❌ No | Include (commercial only) |
| `rent_escalation_preference` | Rent Escalation Preference | Public-Factual | ❌ No | Include (commercial only) |
| `buildout_tenant_improvement_request` | Buildout / TI Request | Public-Factual | ❌ No | Include (commercial only) |
| `intended_business_use` | Intended Business Use | Public-Factual | ❌ No | Include (commercial only) |
| `signage_request` | Signage Request | Public-Factual | ❌ No | Include (commercial only) |
| `commercial_parking_access_needs` | Commercial Parking Access Needs | Public-Factual | ❌ No | Include (commercial only) |
| `personal_guarantee_preference` | Personal Guarantee Stance | Compliance-Sensitive | ❌ No | Include with disclosure (commercial only) |
| `business_type` | Business Type | Public-Factual | ❌ No | Include (commercial only) |
| `zoning_allows` | Zoning Requirement | Public-Factual | ❌ No | Include (commercial only) |
| `building_hours` | Required Building Hours | Public-Factual | ❌ No | Include (commercial only) |
| `access_24_7` | 24/7 Access Required | Public-Factual | ❌ No | Include (commercial only) |

**Group F: Contact / PII (Prohibited from Ask AI)**

| Meta Key | Human Label | Classification | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|---|
| `first_name` | First Name | Compliance-Sensitive | ❌ No | Exclude — PII |
| `last_name` | Last Name | Compliance-Sensitive | ❌ No | Exclude — PII |
| `phone_number` | Phone Number | Compliance-Sensitive | ❌ No | Exclude — PII |
| `email` | Email Address | Compliance-Sensitive | ❌ No | Exclude — PII |
| `agent_brokerage` | Agent Brokerage | Compliance-Sensitive | ❌ No | Exclude — compliance boundary |
| `agent_license_number` | Agent License Number | Compliance-Sensitive | ❌ No | Exclude — compliance boundary |

**Group G: AI / FAQ**

| Meta Key | Human Label | Classification | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|---|
| `listing_ai_faq` (EAV mirror) | FAQ Answers JSON (EAV copy) | Public-Factual | ❌ No | Include via `faq_answers` — prefer native column |
| `video_link` | Video Tour Link | Public-Factual | ❌ No | Include (URL only) |

### 4.2 FAQ Config Keys — `config/tenant_ai_faq.php`

Tenant uses a flat list structure (not grouped by section key). Stored in the native `listing_ai_faq` column. Total: 27 questions across 6 categories.

**Category: Lifestyle & Priorities (keys: faq_q1–faq_q6)**

| Config Key | Question Label | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|
| `faq_q1` | Do you work from home? | ❌ No | **Include via faq_answers** |
| `faq_q2` | What matters most in day-to-day living? | ❌ No | **Include via faq_answers** |
| `faq_q3` | How would you describe your ideal neighborhood vibe? | ❌ No | **Include via faq_answers** |
| `faq_q4` | Are you sensitive to noise from neighbors, traffic? | ❌ No | **Include via faq_answers** |
| `faq_q5` | Which amenity matters most to you? | ❌ No | **Include via faq_answers** |
| `faq_q6` | How important is outdoor space? | ❌ No | **Include via faq_answers** |

**Category: Pet Details (keys: faq_q7–faq_q8)**

| Config Key | Question Label | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|
| `faq_q7` | If you have pets, what are their breed(s), size, and space needs? | ❌ No | **Include via faq_answers** |
| `faq_q8` | Are you willing to pay a pet deposit or monthly pet rent? | ❌ No | **Include via faq_answers** |

**Category: Flexibility & Lease Intent (keys: faq_q9–faq_q13)**

| Config Key | Question Label | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|
| `faq_q9` | Are you flexible on lease length? | ❌ No | **Include via faq_answers** |
| `faq_q10` | Would you consider a furnished unit? | ❌ No | **Include via faq_answers** |
| `faq_q11` | How firm is your move-in timeline? | ❌ No | **Include via faq_answers** |
| `faq_q12` | Is there any chance you'd need to break the lease early? | ❌ No | **Include via faq_answers** |
| `faq_q13` | Would you consider a longer lease for a rent reduction? | ❌ No | **Include via faq_answers** |

**Category: Background & Motivation (keys: faq_q14–faq_q18)**

| Config Key | Question Label | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|
| `faq_q14` | What's driving your rental search right now? | ❌ No | **Include via faq_answers** |
| `faq_q15` | How long was your most recent tenancy, and why are you moving? | ❌ No | **Include via faq_answers** |
| `faq_q16` | Are you looking for a short-term solution or a long-term home? | ❌ No | **Include via faq_answers** |
| `faq_q17` | Do you have a landlord or employer reference available? | ❌ No | **Include via faq_answers** |
| `faq_q18` | What is the source of your income? | ❌ No | Include via faq_answers — *income source is general context; do not include specific amounts or documents* |

**Category: Communication & Preferences (keys: faq_q19–faq_q20)**

| Config Key | Question Label | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|
| `faq_q19` | How do you prefer to communicate with a landlord? | ❌ No | **Include via faq_answers** |
| `faq_q20` | What's your biggest concern in this rental search? | ❌ No | **Include via faq_answers** |

**Category: Commercial – Business Use (keys: faq_q21–faq_q27, commercial_only: true)**

| Config Key | Question Label | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|
| `faq_q21` | What type of business will be operating from this space? | ❌ No | **Include via faq_answers (commercial only)** |
| `faq_q22` | Do you expect customer or client foot traffic? | ❌ No | **Include via faq_answers (commercial only)** |
| `faq_q23` | Do you have special equipment or power requirements? | ❌ No | **Include via faq_answers (commercial only)** |
| `faq_q24` | Do you require exterior building signage? | ❌ No | **Include via faq_answers (commercial only)** |
| `faq_q25` | Will you need to modify or build out the space? | ❌ No | **Include via faq_answers (commercial only)** |
| `faq_q26` | What are your expected hours of operation? | ❌ No | **Include via faq_answers (commercial only)** |
| `faq_q27` | Are you flexible on commercial lease term length and structure? | ❌ No | **Include via faq_answers (commercial only)** |

### 4.3 DNA / Intelligence Outputs (Tenant)

| Source | Fields | Currently Exposed to Ask AI | Recommended Exposure |
|---|---|---|---|
| `BuyerTenantDnaProfile` (tenant) | `avatar_type`, `primary_motivation`, `secondary_motivation`, `tenant_narrative`, `tenant_preference_summary`, `tenant_personality_tags`, `tenant_match_preferences`, `avatar_confidence_score`, `tenant_avatar_version` | ✅ Yes (tenant_avatar block) | Keep |
| `PropertyLocationDna` | Lifestyle scores, location narrative | ✅ Yes (location_intelligence block) | Keep |

---

## Section 5 — Cross-Role Coverage Summary

### 5.1 Overall Coverage Gap

| Coverage Category | Fields Currently in Ask AI Context | Fields Currently Excluded | Gap % |
|---|---|---|---|
| Seller native columns | 10 (metadata only) | ~45 (factual columns) | ~82% excluded |
| Seller FAQ keys | 0 | 35+ base + 20+ add-on | 100% excluded |
| Buyer native columns | 10 (metadata only) | ~25 (criteria columns) | ~71% excluded |
| Buyer FAQ keys | 0 | 27 base + 21 add-on | 100% excluded |
| Landlord native columns | 10 (metadata only) | 15+ (EAV factual keys) | ~60% excluded |
| Landlord FAQ keys | 0 | 32 base + 12 add-on | 100% excluded |
| Tenant native columns | 10 (metadata only) | `listing_ai_faq` column | ~50% excluded |
| Tenant FAQ keys | 0 | 27 | 100% excluded |
| DNA / Intelligence sources | 100% wired | — | 0% excluded |

### 5.2 Priority Fields — All Roles

The following fields represent the highest-impact quick wins. These are the fields users most commonly ask about, are safe to expose without compliance review, and are directly available from existing database columns or EAV meta.

| Priority | Field | Seller | Buyer | Landlord | Tenant | Storage Location |
|---|---|---|---|---|---|---|
| 1 | Bedrooms | `bedroom_id` (FK) | `bedrooms` (native) | EAV meta | EAV meta | Native / EAV |
| 2 | Bathrooms | `bathroom_id` (FK) | `bathrooms` (native) | EAV meta | EAV meta | Native / EAV |
| 3 | Price / Rent | `starting_price` | `max_price` | EAV `rent_amount` | N/A | Native / EAV |
| 4 | Pets Allowed | `pets_allowed` (native) | `pets_allowed` (native) | EAV meta | FAQ `faq_q7` | Native / EAV / FAQ |
| 5 | Pool | `pool` (native) | `pool` (native) | EAV meta | N/A | Native / EAV |
| 6 | HOA Fee | `hoa_fee` (native) | `max_hoa_fee` (native) | EAV meta | N/A | Native |
| 7 | Garage / Parking | `garage_spaces` (native) | `garage_spaces` (native) | EAV / FAQ | N/A | Native / FAQ |
| 8 | Lease Term | `lease_terms` (native) | N/A | EAV meta | FAQ `faq_q9` | Native / EAV / FAQ |
| 9 | Year Built | `year_built` (native) | N/A | EAV meta | N/A | Native / EAV |
| 10 | Square Footage | `heated_sqft` (native) | `sqft` (native) | EAV meta | N/A | Native / EAV |

### 5.3 Fields to Never Expose

The following fields exist in the database but must never be passed to any Ask AI service for any reason:

| Field | Reason for Exclusion |
|---|---|
| `user_id`, `seller_id`, `buyer_id` | PII — internal user identifier |
| `seller_name`, `buyer_name` | PII — personal name |
| `phone_number`, `buyer_phone` | PII — personal contact |
| `email`, `buyer_email` | PII — personal contact |
| `brokerage`, `license_number` | Compliance boundary — broker compensation |
| `hoa_manager_contact` | PII — third-party contact |
| `reserve_price` | Compliance-sensitive — seller may choose to keep hidden |
| `ai_share_token` | Internal key — must never be exposed in AI responses |
| Any signature field | PII — legal signature metadata |
| Any IP address / user-agent | PII — legal and security |

---

## Section 6 — AiFaqAnswer Table Coverage

The `ai_faq_answers` table (`app/Models/AiFaqAnswer.php`) provides a structured, queryable alternative to the raw `listing_ai_faq` JSON blob.

| Column | Purpose | Currently Used by Ask AI |
|---|---|---|
| `listing_type` | Canonical role | ❌ No — table never queried by any Ask AI service |
| `listing_id` | Listing PK | ❌ No |
| `question_key` | Config key (e.g., `roof_age_and_condition`) | ❌ No |
| `question_group` | Category label (e.g., "Property Condition & Maintenance") | ❌ No |
| `intelligence_category` | AI topic classification | ❌ No |
| `answer_text` | Plain-text answer from seller/landlord | ❌ No |
| `answer_normalized` | JSON-structured normalized answer | ❌ No |

**Impact:** Even if the classifier and context assembly were fixed, the `ai_faq_answers` table is currently completely invisible to the Ask AI pipeline. Task C (wire faq_answers) and Task I (persistent FAQ enrichment) together close this gap.

---

*This document is the permanent master reference for Ask AI field coverage. Update it whenever new columns, EAV meta keys, or FAQ config keys are added to any of the four listing roles.*
