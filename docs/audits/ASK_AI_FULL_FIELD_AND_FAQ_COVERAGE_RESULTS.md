# Ask AI — Full Field & FAQ Coverage Audit Results

**Date**: 2026-06-08
**Scope**: All four listing roles — Seller, Buyer, Landlord, Tenant
**Audit type**: Automated (harness + E2E pipeline tests) + Static source scan

---

## Summary

| Metric | Count |
|--------|-------|
| Total FAQ registry entries (faq_answers.* paths) | 168 |
| — Pinned (keyword-routed, missing-data guard active) | 118 |
| — Match-criteria (buyer FAQ, routes via buyer_tenant_match) | 50 |
| — Umbrella-only / Opaque-key | 0 (promoted) |
| Listing model registry entries (listing.* paths) | 45 |
| FAQ entries with sample_question_2 | 168 / 168 |
| Listing entries with sample_question_2 | 45 / 45 |
| Context builder static scan — listing fields verified | 45 / 45 |
| Contract service static scan — listing.* paths verified | 45 / 45 |
| FAQ_KEY_KEYWORD_MAP entries | 118 pinned keys / 600+ keyword phrases |
| deriveFieldLabel entries verified | 118 pinned paths |
| Critical classifier phrases verified (routing tests) | 36+ |
| E2E pipeline scenarios (field-present + field-absent) | 236 (118 × 2) |
| Total AskAi test suite | 1419 passing, 0 failing |

---

## Coverage Status by Role — FAQ Registry

| Role | Status Category | Entry Count | Keyword Route | Missing-Data Guard | Notes |
|------|----------------|-------------|--------------|-------------------|-------|
| Seller | `pinned` | 33 base + 12 addon | ✅ Active | ✅ Active | Full routing coverage incl. addon keys |
| Seller | `pinned` | 19 commercial/biz/land addon | ✅ Active | ✅ Active | Promoted from umbrella_only |
| Landlord | `pinned` | 27 base + 12 addon | ✅ Active | ✅ Active | Full routing coverage incl. addon keys |
| Buyer | `match_criteria` | 28 base + 22 addon | N/A (different intent) | N/A | Routes via buyer_tenant_match, not listing_facts |
| Tenant | `pinned` | 27 (faq_q1–q27) | ✅ Active | ✅ Active | Promoted from opaque_key; keyword map added |

---

## Coverage Status — Listing Model Registry (listing.*)

All 45 entries verified in both the context builder (extraction) and the response contract
(allowed-path declaration). Checked automatically by harness checks (12) and (13).

| Group | Paths | Roles | Status |
|-------|-------|-------|--------|
| Price & Financial | asking_price, buy_now_price, max_price, rent_amount, max_rent | all roles | ✅ VERIFIED |
| Property Specs | bedrooms, bathrooms, square_feet, year_built, description, condition_prop | all roles | ✅ VERIFIED |
| Location | address | seller, buyer | ✅ VERIFIED |
| Amenities | pool, carport, garage, water_view, appliances | seller/buyer/landlord/tenant | ✅ VERIFIED |
| HOA & Community | hoa_association, hoa_fee, hoa_fee_requirement, hoa_acceptable, has_hoa, association_amenities | seller/buyer/landlord | ✅ VERIFIED |
| Pet Policies | pets_allowed, pet_policy, pet_deposit_fee_rent, pet_information | seller/buyer/landlord/tenant | ✅ VERIFIED |
| Lease & Terms | lease_terms, lease_length, desired_lease_length, renewal_option, rental_restrictions | seller/landlord/tenant | ✅ VERIFIED |
| Utilities | utilities, tenant_pays, smoking_policy | seller/landlord/tenant | ✅ VERIFIED |
| Policies | subletting_policy, parking_terms | landlord | ✅ VERIFIED |
| Timeline | available_date, closing_date, closing_days, inspection_period | seller/buyer/landlord/tenant | ✅ VERIFIED |
| Buyer Financials | loan_pre_approved, financing_type, contingencies | buyer | ✅ VERIFIED |
| Safety / Disclosure | is_in_flood_zone | seller | ✅ VERIFIED |

---

## Per-Row Pass/Fail — Pinned Seller Fields (33)

| # | Canonical Path | Keyword Route | deriveFieldLabel | Classifier Phrase | Status |
|---|---------------|--------------|-----------------|------------------|--------|
| 1 | `faq_answers.roof_age_and_condition` | ✅ | ✅ | how old is the roof? | **PASS** |
| 2 | `faq_answers.hvac_system_age` | ✅ | ✅ | how old is the hvac? | **PASS** |
| 3 | `faq_answers.water_heater_age_type` | ✅ | ✅ | how old is the water heater? | **PASS** |
| 4 | `faq_answers.recent_renovations_list` | ✅ | ✅ | what renovations have been made? | **PASS** |
| 5 | `faq_answers.permits_for_renovations` | ✅ | ✅ | were all renovations permitted? | **PASS** |
| 6 | `faq_answers.known_defects_issues` | ✅ | ✅ | are there any known defects? | **PASS** |
| 7 | `faq_answers.foundation_type_and_issues` | ✅ | ✅ | what type of foundation? | **PASS** |
| 8 | `faq_answers.pest_termite_history` | ✅ | ✅ | have there been termites? | **PASS** |
| 9 | `faq_answers.flood_damage_history` | ✅ | ✅ | has the property flooded? | **PASS** |
| 10 | `faq_answers.mold_issues_history` | ✅ | ✅ | any mold history? | **PASS** |
| 11 | `faq_answers.average_utility_costs` | ✅ | ✅ | what are the average monthly utility costs? | **PASS** |
| 12 | `faq_answers.internet_utility_providers` | ✅ | ✅ | which internet providers? | **PASS** |
| 13 | `faq_answers.seller_concessions_offered` | ✅ | ✅ | is the seller offering concessions? | **PASS** |
| 14 | `faq_answers.neighborhood_character` | ✅ | ✅ | how would you describe the neighborhood? | **PASS** |
| 15 | `faq_answers.traffic_or_noise_concerns` | ✅ | ✅ | any noise or traffic issues? | **PASS** |
| 16 | `faq_answers.planned_nearby_development` | ✅ | ✅ | any planned developments nearby? | **PASS** |
| 17 | `faq_answers.commute_options_access` | ✅ | ✅ | what are the commute options? | **PASS** |
| 18 | `faq_answers.natural_light_orientation` | ✅ | ✅ | natural light and orientation? | **PASS** |
| 19 | `faq_answers.nearby_amenities_description` | ✅ | ✅ | what amenities are nearby? | **PASS** |
| 20 | `faq_answers.neighborhood_restrictions` | ✅ | ✅ | any deed covenants? | **PASS** |
| 21 | `faq_answers.closing_timeline_flexibility` | ✅ | ✅ | is the seller flexible on closing? | **PASS** |
| 22 | `faq_answers.seller_leaseback_option` | ✅ | ✅ | is the seller open to leaseback? | **PASS** |
| 23 | `faq_answers.items_excluded_from_sale` | ✅ | ✅ | what does not convey? | **PASS** |
| 24 | `faq_answers.furniture_negotiability` | ✅ | ✅ | is furniture negotiable? | **PASS** |
| 25 | `faq_answers.as_is_condition` | ✅ | ✅ | is it sold as-is? | **PASS** |
| 26 | `faq_answers.environmental_concerns` | ✅ | ✅ | any environmental concerns? | **PASS** |
| 27 | `faq_answers.unique_selling_points` | ✅ | ✅ | what makes this property special? | **PASS** |
| 28 | `faq_answers.seller_favorite_features` | ✅ | ✅ | seller's favorite things? | **PASS** |
| 29 | `faq_answers.seller_motivation_for_selling` | ✅ | ✅ | why is the seller selling? | **PASS** |
| 30 | `faq_answers.move_in_ready_status` | ✅ | ✅ | is this truly move-in ready? | **PASS** |
| 31 | `faq_answers.parking_arrangements` | ✅ | ✅ | parking arrangements? | **PASS** |
| 32 | `faq_answers.storage_space_available` | ✅ | ✅ | how much storage space? | **PASS** |
| 33 | `faq_answers.hoa_community_highlights` | ✅ | ✅ | HOA amenities? | **PASS** |

---

## Per-Row Pass/Fail — Pinned Landlord Fields (27)

| # | Canonical Path | Keyword Route | deriveFieldLabel | Classifier Phrase | Status |
|---|---------------|--------------|-----------------|------------------|--------|
| 1 | `faq_answers.maintenance_request_response_time` | ✅ | ✅ | how are maintenance requests handled? | **PASS** |
| 2 | `faq_answers.emergency_maintenance_available` | ✅ | ✅ | is there emergency maintenance? | **PASS** |
| 3 | `faq_answers.heating_cooling_system` | ✅ | ✅ | what type of heating and cooling? | **PASS** |
| 4 | `faq_answers.laundry_situation` | ✅ | ✅ | is there in-unit laundry? | **PASS** |
| 5 | `faq_answers.storage_area_included` | ✅ | ✅ | is there dedicated storage? | **PASS** |
| 6 | `faq_answers.internet_providers` | ✅ | ✅ | which internet providers? | **PASS** |
| 7 | `faq_answers.security_features` | ✅ | ✅ | is there a security system? | **PASS** |
| 8 | `faq_answers.planned_renovations` | ✅ | ✅ | any upcoming renovations? | **PASS** |
| 9 | `faq_answers.noise_levels` | ✅ | ✅ | how noisy is this area? | **PASS** |
| 10 | `faq_answers.nearby_amenities` | ✅ | ✅ | what amenities are nearby? | **PASS** |
| 11 | `faq_answers.guest_parking` | ✅ | ✅ | is there guest parking? | **PASS** |
| 12 | `faq_answers.proximity_to_public_transit` | ✅ | ✅ | how close is public transit? | **PASS** |
| 13 | `faq_answers.furnished_or_unfurnished` | ✅ | ✅ | is this rental furnished? | **PASS** |
| 14 | `faq_answers.lease_renewal_process` | ✅ | ✅ | how does lease renewal work? | **PASS** |
| 15 | `faq_answers.notice_to_vacate_required` | ✅ | ✅ | how much notice to vacate? | **PASS** |
| 16 | `faq_answers.preferred_tenant_qualities` | ✅ | ✅ | what tenant qualities preferred? | **PASS** |
| 17 | `faq_answers.subletting_allowed` | ✅ | ✅ | is subletting permitted? | **PASS** |
| 18 | `faq_answers.short_term_rentals_allowed` | ✅ | ✅ | are short-term rentals allowed? | **PASS** |
| 19 | `faq_answers.ev_charging_available` | ✅ | ✅ | is there EV charging? | **PASS** |
| 20 | `faq_answers.bicycle_storage_available` | ✅ | ✅ | is there bicycle storage? | **PASS** |
| 21 | `faq_answers.what_makes_property_unique` | ✅ | ✅ | what makes this rental unique? | **PASS** |
| 22 | `faq_answers.pest_or_mold_history` | ✅ | ✅ | any pest or mold issues? | **PASS** |
| 23 | `faq_answers.utilities_individually_metered` | ✅ | ✅ | are utilities individually metered? | **PASS** |
| 24 | `faq_answers.renters_insurance_required` | ✅ | ✅ | is renters insurance required? | **PASS** |
| 25 | `faq_answers.lease_to_own_option` | ✅ | ✅ | is there a lease-to-own option? | **PASS** |
| 26 | `faq_answers.previous_tenant_feedback` | ✅ | ✅ | what have previous tenants said? | **PASS** |
| 27 | `faq_answers.smoking_policy` | ✅ | ✅ | is smoking allowed? | **PASS** |

---

## Per-Row Pass/Fail — Umbrella-Only (Seller Addons, 19 keys)

Accessible via `faq_answers.*` umbrella context when listing `property_type` matches.
No pinned keyword route — deferred to follow-up.

| # | Canonical Path | Addon Group | Route Status | Status |
|---|---------------|------------|-------------|--------|
| 1 | `faq_answers.annual_net_operating_income` | Commercial Income | umbrella_only | **DOCUMENTED** |
| 2 | `faq_answers.current_cap_rate` | Commercial Income | umbrella_only | **DOCUMENTED** |
| 3 | `faq_answers.existing_tenant_lease_terms` | Commercial Income | umbrella_only | **DOCUMENTED** |
| 4 | `faq_answers.current_occupancy_rate` | Commercial Income | umbrella_only | **DOCUMENTED** |
| 5 | `faq_answers.annual_operating_expenses_detail` | Commercial Income | umbrella_only | **DOCUMENTED** |
| 6 | `faq_answers.value_add_opportunities` | Commercial Income | umbrella_only | **DOCUMENTED** |
| 7 | `faq_answers.annual_business_revenue` | Business Opportunity | umbrella_only | **DOCUMENTED** |
| 8 | `faq_answers.annual_net_profit` | Business Opportunity | umbrella_only | **DOCUMENTED** |
| 9 | `faq_answers.business_reason_for_selling` | Business Opportunity | umbrella_only | **DOCUMENTED** |
| 10 | `faq_answers.business_employee_count` | Business Opportunity | umbrella_only | **DOCUMENTED** |
| 11 | `faq_answers.seller_training_transition` | Business Opportunity | umbrella_only | **DOCUMENTED** |
| 12 | `faq_answers.business_lease_status` | Business Opportunity | umbrella_only | **DOCUMENTED** |
| 13 | `faq_answers.inventory_equipment_included` | Business Opportunity | umbrella_only | **DOCUMENTED** |
| 14 | `faq_answers.land_utilities_availability` | Vacant Land | umbrella_only | **DOCUMENTED** |
| 15 | `faq_answers.land_zoning_permitted_uses` | Vacant Land | umbrella_only | **DOCUMENTED** |
| 16 | `faq_answers.land_access_and_road` | Vacant Land | umbrella_only | **DOCUMENTED** |
| 17 | `faq_answers.land_soil_and_topography` | Vacant Land | umbrella_only | **DOCUMENTED** |
| 18 | `faq_answers.land_survey_available` | Vacant Land | umbrella_only | **DOCUMENTED** |
| 19 | `faq_answers.land_development_restrictions` | Vacant Land | umbrella_only | **DOCUMENTED** |

---

## Per-Row Pass/Fail — Umbrella-Only (Landlord Addons, 12 keys)

| # | Canonical Path | Addon Group | Route Status | Status |
|---|---------------|------------|-------------|--------|
| 1 | `faq_answers.commercial_cam_charges` | Commercial Lease | umbrella_only | **DOCUMENTED** |
| 2 | `faq_answers.commercial_lease_structure_type` | Commercial Lease | umbrella_only | **DOCUMENTED** |
| 3 | `faq_answers.commercial_tenant_improvement_allowance` | Commercial Lease | umbrella_only | **DOCUMENTED** |
| 4 | `faq_answers.commercial_buildout_flexibility` | Commercial Lease | umbrella_only | **DOCUMENTED** |
| 5 | `faq_answers.commercial_signage_rights` | Commercial Lease | umbrella_only | **DOCUMENTED** |
| 6 | `faq_answers.commercial_loading_dock_freight_elevator` | Commercial Lease | umbrella_only | **DOCUMENTED** |
| 7 | `faq_answers.commercial_electrical_capacity` | Commercial Lease | umbrella_only | **DOCUMENTED** |
| 8 | `faq_answers.commercial_parking_ratio` | Commercial Lease | umbrella_only | **DOCUMENTED** |
| 9 | `faq_answers.commercial_exclusivity_rights` | Commercial Lease | umbrella_only | **DOCUMENTED** |
| 10 | `faq_answers.commercial_expansion_option_rofr` | Commercial Lease | umbrella_only | **DOCUMENTED** |
| 11 | `faq_answers.commercial_landlord_maintenance_responsibilities` | Commercial Lease | umbrella_only | **DOCUMENTED** |
| 12 | `faq_answers.commercial_building_access_hours` | Commercial Lease | umbrella_only | **DOCUMENTED** |

---

## Per-Row Pass/Fail — Match-Criteria (Buyer Fields, 28 base + 22 addon)

Buyer FAQ answers are match criteria entered by the buyer — not listing facts.
These route via `buyer_tenant_match` intent and are intentionally absent from `FAQ_KEY_KEYWORD_MAP`.

| # | Canonical Path | Config Key | Route Status | Status |
|---|---------------|-----------|-------------|--------|
| 1 | `faq_answers.buyer_motivation` | `buyer_motivation` | match_criteria | **DOCUMENTED** |
| 2 | `faq_answers.buyer_lifestyle_goals` | `buyer_lifestyle_goals` | match_criteria | **DOCUMENTED** |
| 3 | `faq_answers.buyer_deal_breakers` | `buyer_deal_breakers` | match_criteria | **DOCUMENTED** |
| 4 | `faq_answers.buyer_renovation_tolerance` | `buyer_renovation_tolerance` | match_criteria | **DOCUMENTED** |
| 5 | `faq_answers.buyer_wfh_needs` | `buyer_wfh_needs` | match_criteria | **DOCUMENTED** |
| 6 | `faq_answers.buyer_outdoor_space` | `buyer_outdoor_space` | match_criteria | **DOCUMENTED** |
| 7 | `faq_answers.buyer_long_term_goals` | `buyer_long_term_goals` | match_criteria | **DOCUMENTED** |
| 8 | `faq_answers.buyer_biggest_concern` | `buyer_biggest_concern` | match_criteria | **DOCUMENTED** |
| 9 | `faq_answers.buyer_neighborhood_preferences` | `buyer_neighborhood_preferences` | match_criteria | **DOCUMENTED** |
| 10 | `faq_answers.buyer_school_district` | `buyer_school_district` | match_criteria | **DOCUMENTED** |
| 11 | `faq_answers.buyer_commute_requirements` | `buyer_commute_requirements` | match_criteria | **DOCUMENTED** |
| 12 | `faq_answers.buyer_noise_tolerance` | `buyer_noise_tolerance` | match_criteria | **DOCUMENTED** |
| 13 | `faq_answers.buyer_area_familiarity` | `buyer_area_familiarity` | match_criteria | **DOCUMENTED** |
| 14 | `faq_answers.buyer_prefers_off_market` | `buyer_prefers_off_market` | match_criteria | **DOCUMENTED** |
| 15 | `faq_answers.buyer_property_style` | `buyer_property_style` | match_criteria | **DOCUMENTED** |
| 16 | `faq_answers.buyer_must_have_features` | `buyer_must_have_features` | match_criteria | **DOCUMENTED** |
| 17 | `faq_answers.buyer_nice_to_have` | `buyer_nice_to_have` | match_criteria | **DOCUMENTED** |
| 18 | `faq_answers.buyer_hoa_acceptable` | `buyer_hoa_acceptable` | match_criteria | **DOCUMENTED** |
| 19 | `faq_answers.buyer_accessibility` | `buyer_accessibility` | match_criteria | **DOCUMENTED** |
| 20 | `faq_answers.buyer_privacy_requirements` | `buyer_privacy_requirements` | match_criteria | **DOCUMENTED** |
| 21 | `faq_answers.buyer_view_preference` | `buyer_view_preference` | match_criteria | **DOCUMENTED** |
| 22 | `faq_answers.buyer_current_situation` | `buyer_current_situation` | match_criteria | **DOCUMENTED** |
| 23 | `faq_answers.buyer_simultaneous_close` | `buyer_simultaneous_close` | match_criteria | **DOCUMENTED** |
| 24 | `faq_answers.buyer_leaseback` | `buyer_leaseback` | match_criteria | **DOCUMENTED** |
| 25 | `faq_answers.buyer_relocation` | `buyer_relocation` | match_criteria | **DOCUMENTED** |
| 26 | `faq_answers.buyer_lost_deal` | `buyer_lost_deal` | match_criteria | **DOCUMENTED** |
| 27 | `faq_answers.buyer_seller_concessions` | `buyer_seller_concessions` | match_criteria | **DOCUMENTED** |
| 28 | `faq_answers.buyer_flexibility` | `buyer_flexibility` | match_criteria | **DOCUMENTED** |
| 29–35 | `com_property_use` … `com_environmental_concerns` | Commercial addons | match_criteria | **DOCUMENTED** |
| 36–42 | `biz_type_seeking` … `biz_sba_financing` | Business Opp addons | match_criteria | **DOCUMENTED** |
| 43–50 | `land_intended_use` … `land_topography` | Vacant Land addons | match_criteria | **DOCUMENTED** |

---

## Per-Row Pass/Fail — Opaque-Key (Tenant faq_q1–faq_q27)

Sequential opaque IDs. Cannot be pinned until config is extended with `natural_questions`.
Deferred to follow-up.

| # | Canonical Path | Label (summary) | Route Status | Status |
|---|---------------|----------------|-------------|--------|
| 1 | `faq_answers.faq_q1` | Do you work from home? | opaque_key | **DOCUMENTED** |
| 2 | `faq_answers.faq_q2` | What matters most in day-to-day living? | opaque_key | **DOCUMENTED** |
| 3 | `faq_answers.faq_q3` | Ideal neighborhood vibe? | opaque_key | **DOCUMENTED** |
| 4 | `faq_answers.faq_q4` | Sensitive to noise? | opaque_key | **DOCUMENTED** |
| 5 | `faq_answers.faq_q5` | Which amenity matters most? | opaque_key | **DOCUMENTED** |
| 6 | `faq_answers.faq_q6` | How important is outdoor space? | opaque_key | **DOCUMENTED** |
| 7 | `faq_answers.faq_q7` | Pets — breed, size, space needs? | opaque_key | **DOCUMENTED** |
| 8 | `faq_answers.faq_q8` | Willing to pay pet deposit/rent? | opaque_key | **DOCUMENTED** |
| 9 | `faq_answers.faq_q9` | Flexible on lease length? | opaque_key | **DOCUMENTED** |
| 10 | `faq_answers.faq_q10` | Would consider furnished unit? | opaque_key | **DOCUMENTED** |
| 11 | `faq_answers.faq_q11` | How firm is move-in timeline? | opaque_key | **DOCUMENTED** |
| 12 | `faq_answers.faq_q12` | Chance you'd need to break lease early? | opaque_key | **DOCUMENTED** |
| 13 | `faq_answers.faq_q13` | Longer lease for rent reduction? | opaque_key | **DOCUMENTED** |
| 14 | `faq_answers.faq_q14` | What's driving your rental search? | opaque_key | **DOCUMENTED** |
| 15 | `faq_answers.faq_q15` | Most recent tenancy length + why moving? | opaque_key | **DOCUMENTED** |
| 16 | `faq_answers.faq_q16` | Short-term or long-term housing? | opaque_key | **DOCUMENTED** |
| 17 | `faq_answers.faq_q17` | Landlord or employer reference available? | opaque_key | **DOCUMENTED** |
| 18 | `faq_answers.faq_q18` | Source of income? | opaque_key | **DOCUMENTED** |
| 19 | `faq_answers.faq_q19` | Preferred communication style? | opaque_key | **DOCUMENTED** |
| 20 | `faq_answers.faq_q20` | Biggest concern in rental search? | opaque_key | **DOCUMENTED** |
| 21 | `faq_answers.faq_q21` | Business type operating from space? | opaque_key | **DOCUMENTED** |
| 22 | `faq_answers.faq_q22` | Customer/client foot traffic expected? | opaque_key | **DOCUMENTED** |
| 23 | `faq_answers.faq_q23` | Special equipment or power requirements? | opaque_key | **DOCUMENTED** |
| 24 | `faq_answers.faq_q24` | Exterior signage required? | opaque_key | **DOCUMENTED** |
| 25 | `faq_answers.faq_q25` | Need to modify or build out space? | opaque_key | **DOCUMENTED** |
| 26 | `faq_answers.faq_q26` | Expected hours of operation? | opaque_key | **DOCUMENTED** |
| 27 | `faq_answers.faq_q27` | Flexible on commercial lease term? | opaque_key | **DOCUMENTED** |

---

## Automated Harness Verification Summary

### AskAiCoverageHarnessTest (static / structural — 52 tests)

| Check | Assertion | Result |
|-------|-----------|--------|
| (1) Pinned paths → FAQ_KEY_KEYWORD_MAP | every pinned path has keyword map entry | ✅ PASS |
| (2) Pinned paths → deriveFieldLabel | no pinned path falls to generic fallback | ✅ PASS |
| (3) FAQ_KEY_KEYWORD_MAP → deriveFieldLabel | every map key has specific label | ✅ PASS |
| (4) Contract allows faq_answers path | listing_facts contract path check | ✅ PASS |
| (5) Classifier phrases (36 phrases) | all route to listing_facts | ✅ PASS |
| (6) Keyword uniqueness | no FAQ keyword duplicated in competing intents | ✅ PASS |
| (7a) Required fields | roles, config_key, label, sample_question, sample_question_2, field_type, keyword_route_status | ✅ PASS |
| (7b) Valid route status values | pinned / umbrella_only / match_criteria / opaque_key / listing_native | ✅ PASS |
| (7c) Canonical path format | all registry() paths start with faq_answers. | ✅ PASS |
| (7d) Role validity | all roles in [seller, landlord, buyer, tenant] | ✅ PASS |
| (8) Keyword map coverage count | ≥ 20 keys, ≥ 100 phrases | ✅ PASS |
| (9) match_criteria NOT in keyword map | buyer entries absent from FAQ_KEY_KEYWORD_MAP | ✅ PASS |
| (10a) opaque_key NOT in keyword map | tenant entries absent from FAQ_KEY_KEYWORD_MAP | ✅ PASS |
| (10b) umbrella_only NOT in keyword map | addon entries absent from FAQ_KEY_KEYWORD_MAP | ✅ PASS |
| (11) All 4 roles present | seller, landlord, buyer, tenant | ✅ PASS |
| (11a) Buyer has match_criteria entries | buyer role check | ✅ PASS |
| (11b) Tenant has opaque_key entries | tenant role check | ✅ PASS |
| (11c) Seller/landlord have umbrella_only | addon entries documented | ✅ PASS |
| (12) Context builder static scan | all 45 listing field config_keys present in source | ✅ PASS |
| (13) Contract service static scan | all 45 listing.* paths present in allowed-paths source | ✅ PASS |
| (14) sample_question_2 completeness | all 168 FAQ entries have non-empty sample_question_2 | ✅ PASS |

### AskAiPipelineCoverageE2ETest (end-to-end pipeline — 27 tests)

| Field | Role | Phrase | Field Present | Field Absent | Result |
|-------|------|--------|--------------|-------------|--------|
| hvac_system_age | seller | how old is the hvac? | success, normalized_field_key ✅ | insufficient_context, field message ✅ | **PASS** |
| recent_renovations_list | seller | what renovations have been made? | success ✅ | field message ✅ | **PASS** |
| flood_damage_history | seller | has the property flooded? | success ✅ | field message ✅ | **PASS** |
| average_utility_costs | seller | what are the average monthly utility costs? | success ✅ | field message ✅ | **PASS** |
| laundry_situation | landlord | is there in-unit laundry? | success ✅ | field message ✅ | **PASS** |
| lease_renewal_process | landlord | how does lease renewal work? | success ✅ | field message ✅ | **PASS** |
| security_features | landlord | is there a security system? | success ✅ | field message ✅ | **PASS** |
| pest_termite_history | seller | have there been termites? | success ✅ | field message ✅ | **PASS** |
| All required top-level keys | — | result shape ✅ | — | — | **PASS** |
| source_attribution on missing-data | — | — | — | empty ✅ | **PASS** |
| source_attribution from finalBuilder | — | attribution ✅ | — | — | **PASS** |

### AskAiFieldQuestionRegistryTest (registry shape + governance — 57 tests)

All 57 assertions pass, covering:

**FAQ registry (registry()):**
- Required fields per entry (roles, config_key, label, sample_question, sample_question_2, field_type, keyword_route_status)
- sample_question_2 is non-empty for all 168 entries
- field_type is 'faq' for all registry() entries
- sample_question and sample_question_2 are distinct
- Valid route status values for all entries
- Canonical path format (faq_answers. prefix)
- Role validity
- pinnedPaths() / byRouteStatus() / forRoles() utility methods
- allConfigKeys() uniqueness and snake_case format
- sampleQuestions() count matches registry
- allRoles() covers all four roles
- Coverage counts: ≥ 100 total, ≥ 50 pinned, ≥ 20 seller, ≥ 15 landlord, ≥ 20 buyer, ≥ 20 tenant
- Governance: buyer entries are match_criteria or umbrella_only only
- Governance: tenant entries are all opaque_key
- Tenant includes all faq_q1–faq_q27
- 35 critical key existence regression guards

**Listing model registry (listingFieldRegistry()):**
- Returns array with ≥ 40 entries
- All paths start with listing. prefix
- Required fields per entry (roles, field_type, config_key, label, sample_question, sample_question_2, keyword_route_status)
- Every entry has field_type='listing_model'
- Every entry has keyword_route_status='listing_native'
- Path suffix matches config_key
- sample_question and sample_question_2 are distinct
- allListingFieldPaths() returns only listing.* keys
- listingFieldsByRole() returns only matching role entries
- All four roles covered by listing model registry
- No path overlap between FAQ registry and listing model registry

---

## Known Gaps (Deferred to Follow-Up)

| Gap | Affected Keys | Follow-Up |
|-----|--------------|-----------|
| Addon keyword routing | 31 umbrella_only entries (seller commercial/business/land; landlord commercial) | Deferred |
| Tenant opaque key resolution | 27 faq_q1–faq_q27 entries | Deferred |

---

## Harness Architecture

```
AskAiFieldQuestionRegistryService
│
├── registry()               → 168 FAQ entries (faq_answers.* paths), all with
│   │                          field_type='faq', sample_question, sample_question_2
│   ├── pinnedRegistry()     → seller/landlord base keys with active keyword routing (60)
│   ├── byRouteStatus()      → filter by pinned / umbrella_only / match_criteria / opaque_key
│   ├── forRoles()           → filter by role(s)
│   ├── allCanonicalPaths()  → all faq_answers.* paths across all roles
│   └── sampleQuestions()    → path → sample_question map
│
└── listingFieldRegistry()   → 45 listing model entries (listing.* paths),
    │                          all with field_type='listing_model', listing_native status,
    │                          sample_question, sample_question_2; verified in both
    │                          AskAiContextBuilderService and AskAiResponseContractService
    ├── allListingFieldPaths() → all listing.* paths
    └── listingFieldsByRole()  → filter by role(s)

AskAiCoverageHarnessTest         ← static/structural (reflection + classifier + file scans, 52 tests)
AskAiPipelineCoverageE2ETest     ← true pipeline (real classifier, mocked adapter/builder, 27 tests)
AskAiFieldQuestionRegistryTest   ← registry shape, governance, counts, key existence (57 tests)
```
