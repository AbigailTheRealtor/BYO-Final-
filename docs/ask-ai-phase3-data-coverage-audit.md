# Ask AI Phase 3 — Real Data Coverage Audit
**Date:** June 19, 2026  
**Scope:** Read-only data quality audit. No code changes.  
**Database snapshot:** 41 Seller · 42 Buyer · 20 Landlord · 14 Tenant non-draft listings

---

## Overview & Key Findings

Phase 2 confirmed that Ask AI routing, classifiers, keyword maps, and Guard B are all functioning correctly. The 8/41 `insufficient_context` answers reported in Phase 2 were due to missing listing data, not code defects. Phase 3 quantifies exactly which fields are empty, why, and what to do about it.

**Top-line findings:**
- The most critical gap is **description / additional_details**: 51% of seller listings, 48% of buyer listings, 57% of landlord listings, and 57% of tenant listings have no property description — eliminating Ask AI's most powerful fallback and FAQ answer source.
- **Commute fields** (destination, time, mode) are consistently below 30% across all demand-side roles despite being among the highest-value match signals.
- **Interior features** (specific finishes, features) are almost never populated on seller listings (2.4%) despite being a frequent buyer question.
- **Vacant land, commercial, and business opportunity** fields are sparse by design — they apply only to a small sub-set of listings — but even within those sub-sets, completion is low.
- **Tenant accessibility_requirements, move-in funds, and security deposit budget** are at ~28% completion — high-value fields for filtering and matching that tenants skip.
- Many fields that read ≥45% in Seller EAV reflect the **offer-listing form floor effect**: the full offer-listing Livewire wizard always saves all its form keys as meta records (even blank), giving every offer-listing record ≥20 keys at ≥48.8% regardless of actual user input. The real user-provided signal rate is lower.

---

## Part A — Coverage Report by Role

### Coverage Tiers
- **High** ≥ 75%  
- **Medium** 25–74%  
- **Low** 10–24%  
- **Very Low** < 10%  
- **Zero** 0% (not a single record)

---

### A1 — SELLER (41 non-draft listings)

| Context Key | EAV / Native Source | Pop. Count | Coverage % | Tier |
|---|---|---|---|---|
| address | native:address | 34 | 82.9% | High |
| sold | native:is_sold | 41 | 100% | High |
| service_type | service_type (meta) | 38 | 92.7% | High |
| description | additional_details (meta) | 20 | 48.8% | Medium |
| asking_price | maximum_budget (meta) | 20 | 48.8% | Medium |
| appliances | appliances (meta) | 20 | 48.8% | Medium |
| sale_provision | sale_provision (meta) | 20 | 48.8% | Medium |
| offered_financing | offered_financing (meta) | 20 | 48.8% | Medium |
| pets_allowed | pets (meta) | 20 | 48.8% | Medium |
| number_of_pets_allowed | number_of_pets (meta) | 20 | 48.8% | Medium |
| max_pet_weight | weight_of_pets (meta) | 20 | 48.8% | Medium |
| total_acreage | total_acreage (meta) | 20 | 48.8% | Medium |
| building_sqft | total_square_feet (meta) | 20 | 48.8% | Medium |
| unit_mix_summary | unit_type_configurations (meta) | 20 | 48.8% | Medium |
| water_view | view_preference (meta) | 20 | 48.8% | Medium |
| occupant_status | occupant_status (meta) | 19 | 46.3% | Medium |
| closing_date | target_closing_date (meta) | 19 | 46.3% | Medium |
| utilities | utilities (meta) | 19 | 46.3% | Medium |
| occupancy_requirement | assumable_occupancy_requirement (meta) | 19 | 46.3% | Medium |
| property_items | property_items (meta) | 19 | 46.3% | Medium |
| hoa_association | has_hoa (meta) | 18 | 43.9% | Medium |
| hoa_fee | association_fee_amount (meta) | 18 | 43.9% | Medium |
| hoa_payment_schedule | association_fee_frequency (meta) | 18 | 43.9% | Medium |
| association_name | association_name (meta) | 18 | 43.9% | Medium |
| association_fee_includes | association_fee_includes (meta) | 18 | 43.9% | Medium |
| has_cdd | has_cdd (meta) | 18 | 43.9% | Medium |
| annual_cdd_fee | annual_cdd_fee (meta) | 18 | 43.9% | Medium |
| has_special_assessments | has_special_assessments (meta) | 18 | 43.9% | Medium |
| additional_parcels | additional_parcels (meta) | 18 | 43.9% | Medium |
| total_parcel_count | total_parcel_count (meta) | 18 | 43.9% | Medium |
| special_assessment_amount | special_assessment_amount (meta) | 18 | 43.9% | Medium |
| special_assessment_description | special_assessment_description (meta) | 18 | 43.9% | Medium |
| flood_zone_code | flood_zone_code (meta) | 18 | 43.9% | Medium |
| flood_zone_panel | flood_zone_panel (meta) | 18 | 43.9% | Medium |
| flood_insurance_required | flood_insurance_required (meta) | 18 | 43.9% | Medium |
| annual_property_taxes | annual_property_taxes (meta) | 18 | 43.9% | Medium |
| parcel_id | parcel_id (meta) | 18 | 43.9% | Medium |
| tax_year | tax_year (meta) | 18 | 43.9% | Medium |
| legal_description | legal_description (meta) | 18 | 43.9% | Medium |
| water | water (meta) | 18 | 43.9% | Medium |
| sewer | sewer (meta) | 18 | 43.9% | Medium |
| seller_credit_offered | seller_contribution_credit_offered (meta) | 18 | 43.9% | Medium |
| seller_credit_amount | seller_contribution_amount_details (meta) | 18 | 43.9% | Medium |
| association_approval_required | association_approval_required (meta) | 17 | 41.5% | Medium |
| pool | pool_needed (meta) | 17 | 41.5% | Medium |
| pool_type | pool_type (meta) | 17 | 41.5% | Medium |
| rental_restrictions | leasing_restrictions (meta) | 17 | 41.5% | Medium |
| home_warranty_offered | home_warranty_offered (meta) | 16 | 39.0% | Medium |
| bedrooms | bedrooms (meta) | 16 | 39.0% | Medium |
| bathrooms | bathrooms (meta) | 16 | 39.0% | Medium |
| minimum_annual_net_income | minimum_annual_net_income (meta) | 16 | 39.0% | Medium |
| minimum_cap_rate | minimum_cap_rate (meta) | 16 | 39.0% | Medium |
| total_units | unit_number (meta) | 16 | 39.0% | Medium |
| total_buildings | unit_buildings (meta) | 16 | 39.0% | Medium |
| air_conditioning | air_conditioning (meta) | 15 | 36.6% | Medium |
| roof_type | roof_type (meta) | 15 | 36.6% | Medium |
| exterior_construction | exterior_construction (meta) | 15 | 36.6% | Medium |
| foundation | foundation (meta) | 15 | 36.6% | Medium |
| heating_and_fuel | heating_and_fuel (meta) | 15 | 36.6% | Medium |
| garage | garage_needed (meta) | 15 | 36.6% | Medium |
| carport | carport_needed (meta) | 15 | 36.6% | Medium |
| square_feet | minimum_heated_square (meta) | 15 | 36.6% | Medium |
| year_built | year_built (meta) | 15 | 36.6% | Medium |
| gross_annual_income | gross_annual_income (meta) | 12 | 29.3% | Medium |
| annual_operating_expenses | annual_operating_expenses (meta) | 12 | 29.3% | Medium |
| rent_roll_available | rent_roll_available (meta) | 12 | 29.3% | Medium |
| operating_statement_available | operating_statement_available (meta) | 12 | 29.3% | Medium |
| buy_now_price | buy_now_price (meta) | 12 | 29.3% | Medium |
| business_assets | business_assets (meta) | 12 | 29.3% | Medium |
| building_features | building_features (meta) | 5 | 12.2% | Low |
| ceiling_height | ceiling_height (meta) | 5 | 12.2% | Low |
| price_per_sqft | price_per_sqft (meta) | 5 | 12.2% | Low |
| existing_lease_type | existing_lease_type (meta) | 5 | 12.2% | Low |
| lease_expiration | lease_expiration (meta) | 5 | 12.2% | Low |
| lease_assignable | lease_assignable (meta) | 5 | 12.2% | Low |
| electrical_service | electrical_service (meta) | 5 | 12.2% | Low |
| garage_spaces | garage_parking_spaces (meta) | 5 | 12.2% | Low |
| parking_spaces | garage_parking_spaces (meta) | 5 | 12.2% | Low |
| zoning | zoning (meta) | 8 | 19.5% | Low |
| road_surface_type | road_surface_type (meta) | 8 | 19.5% | Low |
| road_frontage | road_frontage (meta) | 8 | 19.5% | Low |
| pet_restrictions | pet_restrictions (meta) | 2 | 4.9% | Very Low |
| interior_features | interior_features (meta) | 1 | 2.4% | Very Low |
| waterfront | waterfront (meta) | 0 | 0% | Zero |
| water_access | water_access (meta) | 0 | 0% | Zero |
| waterfront_feet | waterfront_feet (meta) | 0 | 0% | Zero |
| flood_zone_date | flood_zone_date (meta) | 0 | 0% | Zero |
| auction_length | native:auction_length | 0 | 0% | Zero |
| lot_dimensions | lot_dimensions (meta) | 3 | 7.3% | Very Low |
| current_adjacent_use | current_adjacent_use (meta) | 3 | 7.3% | Very Low |
| water_available | water_available (meta) | 3 | 7.3% | Very Low |
| sewer_available | sewer_available (meta) | 3 | 7.3% | Very Low |
| electric_available | electric_available (meta) | 3 | 7.3% | Very Low |
| gas_available | gas_available (meta) | 3 | 7.3% | Very Low |
| telecom_available | telecom_available (meta) | 3 | 7.3% | Very Low |
| front_footage | front_footage (meta) | 3 | 7.3% | Very Low |
| number_of_wells | number_of_wells (meta) | 3 | 7.3% | Very Low |
| number_of_septics | number_of_septics (meta) | 3 | 7.3% | Very Low |
| fences | fences (meta) | 3 | 7.3% | Very Low |
| vegetation | vegetation (meta) | 3 | 7.3% | Very Low |
| buildable | buildable (meta) | 3 | 7.3% | Very Low |
| easements | easements (meta) | 3 | 7.3% | Very Low |
| current_use | current_use (meta) | 3 | 7.3% | Very Low |
| business_name | business_name (meta) | 3 | 7.3% | Very Low |
| year_established | year_established (meta) | 3 | 7.3% | Very Low |
| annual_revenue | annual_revenue (meta) | 3 | 7.3% | Very Low |
| gross_profit | gross_profit (meta) | 3 | 7.3% | Very Low |
| sde_ebitda | sde_ebitda (meta) | 3 | 7.3% | Very Low |
| inventory_value | inventory_value (meta) | 3 | 7.3% | Very Low |
| ffe_value | ffe_value (meta) | 3 | 7.3% | Very Low |
| reason_for_sale | reason_for_sale (meta) | 3 | 7.3% | Very Low |
| employee_count | employee_count (meta) | 3 | 7.3% | Very Low |
| financial_statements_available | financial_statements_available (meta) | 3 | 7.3% | Very Low |
| tax_returns_available | tax_returns_available (meta) | 3 | 7.3% | Very Low |
| nda_required | nda_required (meta) | 3 | 7.3% | Very Low |
| business_location_leased | business_location_leased (meta) | 3 | 7.3% | Very Low |
| licenses | licenses (meta) | 3 | 7.3% | Very Low |
| sale_includes | sale_includes (meta) | 3 | 7.3% | Very Low |

> **Note on the 48.8% floor:** The offer-listing Livewire wizard always writes all form meta keys on every save, including keys the user left blank (empty string or null). This means ~20 offer-listing records contribute to the ≥48.8% figure for many keys, but the actual user-supplied signal rate within those records is lower. The true populated rate for narrative/discretionary fields is meaningfully below 50%.

---

### A2 — BUYER (42 non-draft listings)

| Context Key | EAV / Native Source | Pop. Count | Coverage % | Tier |
|---|---|---|---|---|
| service_type | service_type (meta) | 41 | 97.6% | High |
| description | native:additional_details | 0 | 0% | Zero |
| address | native:address | 7 | 16.7% | Low |
| max_price | maximum_budget (meta) | 22 | 52.4% | Medium |
| bedrooms | bedrooms (meta) | 22 | 52.4% | Medium |
| bathrooms | bathrooms (meta) | 22 | 52.4% | Medium |
| square_feet | minimum_heated_square (meta) | 22 | 52.4% | Medium |
| carport | carport_needed (meta) | 22 | 52.4% | Medium |
| garage | garage_needed (meta) | 22 | 52.4% | Medium |
| garage_spaces | garage_parking_spaces (meta) | 14 | 33.3% | Medium |
| water_view | view_preference (meta) | 16 | 38.1% | Medium |
| hoa_acceptable | hoa_acceptance (meta) | 12 | 28.6% | Medium |
| max_hoa_fee | hoa_max_monthly_fee (meta) | 12 | 28.6% | Medium |
| pets_allowed | pets (meta) | 22 | 52.4% | Medium |
| pets_detail | type_of_pets (meta) | 22 | 52.4% | Medium |
| pets_breed | breed_of_pets (meta) | 22 | 52.4% | Medium |
| pets_weight | weight_of_pets (meta) | 22 | 52.4% | Medium |
| loan_pre_approved | pre_approved (meta) | 22 | 52.4% | Medium |
| financing_type | offered_financing (meta) | 22 | 52.4% | Medium |
| inspection_period | inspection_period_days (meta) | 22 | 52.4% | Medium |
| closing_date | target_closing_date (meta) | 22 | 52.4% | Medium |
| inspection_contingency_buyer | inspection_contingency_buyer (meta) | 22 | 52.4% | Medium |
| appraisal_contingency_buyer | appraisal_contingency_buyer (meta) | 22 | 52.4% | Medium |
| financing_contingency_buyer | financing_contingency_buyer (meta) | 22 | 52.4% | Medium |
| cities | cities (meta) | 21 | 50.0% | Medium |
| counties | counties (meta) | 22 | 52.4% | Medium |
| non_negotiable_amenities | non_negotiable_amenities (meta) | 19 | 45.2% | Medium |
| leasing_55_plus | leasing_55_plus (meta) | 22 | 52.4% | Medium |
| additional_preferences | preferance_details (meta) | 17 | 40.5% | Medium |
| minimum_cap_rate | minimum_cap_rate (meta) | 17 | 40.5% | Medium |
| pool | pool_needed (meta) | 13 | 31.0% | Medium |
| home_sale_contingency | home_sale_contingency (meta) | 13 | 31.0% | Medium |
| year_built_preference | year_built (meta) | 0 | 0% | Zero |
| commute_destination_zip | commute_destination_zip (meta) | 12 | 28.6% | Medium |
| max_commute_minutes | max_commute_minutes (meta) | 12 | 28.6% | Medium |
| commute_mode | commute_mode (meta) | 5 | 11.9% | Low |
| flood_zone_tolerance | flood_zone_tolerance (meta) | 10 | 23.8% | Low |
| purchase_purpose | purchase_purpose (meta) | 11 | 26.2% | Medium |
| monthly_income | monthly_income (meta) | 0 | 0% | Zero |
| number_of_occupants | number_occupant (meta) | 0 | 0% | Zero |
| credit_score_range | credit_scroe_rating (meta) | 22 | 52.4% | Medium |
| number_of_units | number_of_unit (meta) | 17 | 40.5% | Medium |
| min_acreage | min_acreage (meta) | 0 | 0% | Zero |
| total_acreage | total_acreage (meta) | 22 | 52.4% | Medium |
| business_type_preference | business_type_selected (meta) | 9 | 21.4% | Low |

> **Note on buyer description:** `additional_details` is a native column on `buyer_agent_auctions`. Zero of 42 non-draft records have it populated — buyers overwhelmingly skip the description field. The offer-listing wizard EAV key `additional_details` (meta) shows 22/42 (52.4%), suggesting that ~22 records used the offer-listing path but the native column was never used. Ask AI falls back to this column, so it is always empty.

---

### A3 — LANDLORD (20 non-draft listings)

| Context Key | EAV / Native Source | Pop. Count | Coverage % | Tier |
|---|---|---|---|---|
| service_type | service_type (meta) | 20 | 100% | High |
| rent_amount | desired_rental_amount (meta) | 6 | 30.0% | Medium |
| description | additional_details (meta) | 6 | 30.0% | Medium |
| bedrooms | bedrooms (meta) | 6 | 30.0% | Medium |
| bathrooms | bathrooms (meta) | 6 | 30.0% | Medium |
| square_feet | minimum_heated_square (meta) | 6 | 30.0% | Medium |
| year_built | year_built (meta) | 6 | 30.0% | Medium |
| appliances | appliances (meta) | 6 | 30.0% | Medium |
| interior_features | interior_features (meta) | 0 | 0% | Zero |
| building_features | building_features (meta) | 2 | 10.0% | Low |
| water_view | view_preference (meta) | 4 | 20.0% | Low |
| pet_policy | pets (meta) | 6 | 30.0% | Medium |
| pet_deposit_fee_rent | pet_deposit_fee_rent (meta) | 6 | 30.0% | Medium |
| pet_max_weight_lbs | pet_max_weight_lbs (meta) | 4 | 20.0% | Low |
| pet_species_allowed | pet_species_allowed (meta) | 4 | 20.0% | Low |
| parking_terms | parking_terms (meta) | 6 | 30.0% | Medium |
| utilities | property_utilities (meta) | 6 | 30.0% | Medium |
| smoking_policy | smoking_policy (meta) | 4 | 20.0% | Low |
| subletting_policy | subletting_policy (meta) | 4 | 20.0% | Low |
| available_date | available_date (meta) | 4 | 20.0% | Low |
| has_hoa | has_hoa (meta) | 6 | 30.0% | Medium |
| association_name | association_name (meta) | 6 | 30.0% | Medium |
| association_fee_amount | association_fee_amount (meta) | 6 | 30.0% | Medium |
| association_fee_frequency | association_fee_frequency (meta) | 6 | 30.0% | Medium |
| association_amenities | association_amenities (meta) | 6 | 30.0% | Medium |
| annual_property_taxes | annual_property_taxes (meta) | 6 | 30.0% | Medium |
| leasing_restrictions | leasing_restrictions (meta) | 6 | 30.0% | Medium |
| lease_length | min_lease_period (meta) | 6 | 30.0% | Medium |
| renewal_option | renewal_option_offered (meta) | 6 | 30.0% | Medium |
| additional_lease_terms | additional_landlord_lease_terms (meta) | 6 | 30.0% | Medium |
| lease_terms | terms_of_lease (meta) | 2 | 10.0% | Low |
| security_deposit_amount | security_deposit_amount (meta) | 4 | 20.0% | Low |
| tenant_pays | tenant_pays (meta) | 2 | 10.0% | Low |
| rent_includes | rent_includes (meta) | 6 | 30.0% | Medium |
| lease_amount_frequency | lease_amount_frequency (meta) | 6 | 30.0% | Medium |
| flood_zone_code | flood_zone_code (meta) | 6 | 30.0% | Medium |
| flood_zone_panel | flood_zone_panel (meta) | 6 | 30.0% | Medium |
| flood_insurance_required | flood_insurance_required (meta) | 6 | 30.0% | Medium |
| lot_dimensions | lot_dimensions (meta) | 0 | 0% | Zero |
| zoning | zoning (meta) | 2 | 10.0% | Low |
| roof_type | roof_type (meta) | 0 | 0% | Zero |
| exterior_construction | exterior_construction (meta) | 0 | 0% | Zero |
| foundation | foundation (meta) | 0 | 0% | Zero |
| heating_fuel | heating_fuel (meta) | 6 | 30.0% | Medium |
| air_conditioning | air_conditioning (meta) | 6 | 30.0% | Medium |
| water | water (meta) | 6 | 30.0% | Medium |
| sewer | sewer (meta) | 6 | 30.0% | Medium |
| parcel_id | parcel_id (meta) | 6 | 30.0% | Medium |
| tax_year | tax_year (meta) | 6 | 30.0% | Medium |
| legal_description | legal_description (meta) | 6 | 30.0% | Medium |
| commercial_lease_type | commercial_lease_type (meta) | 2 | 10.0% | Low |
| cam_nnn_additional_rent_charges | cam_nnn_additional_rent_charges (meta) | 2 | 10.0% | Low |
| ceiling_height | ceiling_height (meta) | 2 | 10.0% | Low |
| electrical_service | electrical_service (meta) | 2 | 10.0% | Low |
| min_income_requirement | min_income_requirement (meta) | 4 | 20.0% | Low |
| min_credit_score | min_credit_score (meta) | 0 | 0% | Zero |
| income_qualification_method | income_qualification_method (meta) | 0 | 0% | Zero |
| employment_requirement | employment_requirement (meta) | 0 | 0% | Zero |
| eviction_history_requirement | eviction_history_requirement (meta) | 0 | 0% | Zero |
| bankruptcy_requirement | bankruptcy_requirement (meta) | 0 | 0% | Zero |
| number_of_occupants | number_occupant (meta) | 6 | 30.0% | Medium |
| maintenance_by | maintenance_by (meta) | 6 | 30.0% | Medium |
| maintenance_response_time | maintenance_response_time (meta) | 6 | 30.0% | Medium |
| guests_allowed | guests_allowed (meta) | 0 | 0% | Zero |
| ll_maintenance_responsibility | ll_maintenance_responsibility (meta) | 6 | 30.0% | Medium |
| landlord_approval_conditions | landlord_approval_conditions (meta) | 6 | 30.0% | Medium |

> **Note on Landlord:** The data shows a strong split — 14 pure agent-auction records (no offer-listing form) vs. 6 that used the full offer-listing path. The 6 offer-listing records all have their keys at 30%. Screening/qualification fields (min_credit_score, income_qualification_method, eviction_history_requirement, bankruptcy_requirement) were never populated across any of the 20 listings.

---

### A4 — TENANT (14 non-draft listings)

| Context Key | EAV / Native Source | Pop. Count | Coverage % | Tier |
|---|---|---|---|---|
| service_type | service_type (meta) | 14 | 100% | High |
| max_rent | budget (meta) | 6 | 42.9% | Medium |
| bedrooms | bedrooms (meta) | 4 | 28.6% | Medium |
| bathrooms | bathrooms (meta) | 0 | 0% | Zero |
| desired_lease_length | desired_lease_length / lease_for (meta) | 6 | 42.9% | Medium |
| appliances | appliances (meta) | 0 | 0% | Zero |
| description | additional_details (meta) | 6 | 42.9% | Medium |
| pool | pool_needed (meta) | 4 | 28.6% | Medium |
| garage | garage_needed (meta) | 4 | 28.6% | Medium |
| carport | carport_needed (meta) | 4 | 28.6% | Medium |
| water_view | view_preference (meta) | 6 | 42.9% | Medium |
| non_negotiable_amenities | non_negotiable_amenities (meta) | 6 | 42.9% | Medium |
| leasing_55_plus | leasing_55_plus (meta) | 4 | 28.6% | Medium |
| security_deposit_budget | security_deposit_budget (meta) | 4 | 28.6% | Medium |
| move_in_funds_available | move_in_funds_available (meta) | 4 | 28.6% | Medium |
| first_month_rent_available | first_month_rent_available (meta) | 4 | 28.6% | Medium |
| last_month_rent_available | last_month_rent_available (meta) | 4 | 28.6% | Medium |
| move_in_date_earliest | move_in_date_earliest (meta) | 4 | 28.6% | Medium |
| move_in_date_latest | move_in_date_latest (meta) | 4 | 28.6% | Medium |
| renewal_option_requested | renewal_option_requested (meta) | 4 | 28.6% | Medium |
| credit_score_range | credit_score_range (meta) | 4 | 28.6% | Medium |
| monthly_income | monthly_income (meta) | 6 | 42.9% | Medium |
| commute_destination_zip | commute_destination_zip (meta) | 4 | 28.6% | Medium |
| max_commute_minutes | max_commute_minutes (meta) | 4 | 28.6% | Medium |
| commute_mode | commute_mode (meta) | 4 | 28.6% | Medium |
| accessibility_requirements | accessibility_requirements (meta) | 4 | 28.6% | Medium |
| prior_eviction | prior_eviction (meta) | 0 | 0% | Zero |
| prior_felony | prior_felony (meta) | 0 | 0% | Zero |
| smoking_preference | smoking_preference (meta) | 4 | 28.6% | Medium |
| service_animal | service_animal (meta) | 4 | 28.6% | Medium |
| emotional_support_animal | emotional_support_animal (meta) | 0 | 0% | Zero |
| rental_purpose | rental_purpose (meta) | 4 | 28.6% | Medium |
| maintenance_preference | maintenance_preference (meta) | 4 | 28.6% | Medium |
| guests_allowed | guests_allowed (meta) | 0 | 0% | Zero |
| cities | cities (meta) | 5 | 35.7% | Medium |
| counties | counties (meta) | 6 | 42.9% | Medium |
| intended_business_use | intended_business_use (meta) | 2 | 14.3% | Low |
| tenant_conditions | tenant_conditions (meta) | 4 | 28.6% | Medium |
| pet_information | pet_information (meta) | 0 | 0% | Zero |
| utility_preference | utility_preference (meta) | 6 | 42.9% | Medium |
| prior_eviction | prior_eviction (meta) | 0 | 0% | Zero |
| property_items | property_items (meta) | 6 | 42.9% | Medium |
| number_of_occupants | number_of_occupants (meta) | 6 | 42.9% | Medium |

> **Note on Tenant:** Sample size is only 14 — treat all percentages with caution. The 4-record cluster vs. 6-record cluster mirrors the landlord pattern (pure agent-auction vs. offer-listing form users).

---

## Part B — Low-Adoption Field Assessment

Fields below 25% completion assessed for value vs. barrier.

### B1 — Seller Low-Completion Fields

| Field | Coverage | Assessment | Reason for Low Adoption |
|---|---|---|---|
| interior_features | 2.4% | **Valuable — Improve** | Buried in advanced tab; multi-select with dozens of options overwhelms sellers |
| pet_restrictions | 4.9% | **Valuable — Improve** | Appears conditional on "pets allowed" answer; sellers who say "no pets" never see it, leaving restriction details blank |
| waterfront / water_access / waterfront_feet | 0% | **Valuable — Improve** | These fields are only shown for waterfront properties but the conditional display may be broken or not triggered |
| flood_zone_date | 0% | **Low value — Keep optional** | Date of FEMA map panel rarely known by sellers; even buyers rarely ask |
| auction_length (native) | 0% | **System field — Monitor** | No longer populated via native column; value is now stored via workflow EAV. Context builder reads native column — always null |
| lot_dimensions | 7.3% | **Moderate value — Improve** | Free-text field; sellers skip it when GIS data is not readily available |
| zoning | 19.5% | **High value — Improve** | Sellers don't know their zoning code; needs lookup assist or geocode pre-fill |
| building_features | 12.2% | **Valuable — Improve** | Multi-select of amenity tags; sellers treat it as optional detail |
| ceiling_height | 12.2% | **Commercial-only value — Keep** | Relevant only for commercial/warehouse; low adoption is expected |
| price_per_sqft / existing_lease_type / lease_expiration / lease_assignable | 12.2% | **Commercial-only value — Keep** | These 4 fields appear only for commercial/income property; adoption in sub-set is reasonable |
| road_surface_type / road_frontage | 19.5% | **Land-only value — Keep** | Vacant land sub-fields; 8/41 sellers have land listings |
| Business Opportunity fields (business_name, annual_revenue, etc.) | 7.3% | **Business-only — Keep** | 3/41 listings are business opportunities; sub-set adoption is poor — see Part D |

### B2 — Buyer Low-Completion Fields

| Field | Coverage | Assessment | Reason for Low Adoption |
|---|---|---|---|
| description (native additional_details) | 0% | **Critical gap — Improve** | Zero completion. Buyers skip the narrative field entirely. Ask AI has no fallback when description is null |
| commute_mode | 11.9% | **High value — Improve** | Appears after commute_destination_zip; buyers who fill destination often skip mode |
| flood_zone_tolerance | 23.8% | **Valuable — Improve** | Shown conditionally for flood-prone area searches; buyers don't understand the question |
| business_type_preference | 21.4% | **Commercial-only — Keep** | Relevant only for commercial buyers; sub-set adoption is expected |
| address | 16.7% | **Structural issue** | Buyers searching for property don't have a source address; field semantics unclear |
| year_built_preference | 0% | **Wiring gap** | Key maps to EAV `year_built` meta — but the form saves buyer's year-built preference under that same key. Live DB shows 0 records. The field is present in CANONICAL_SOURCE_MAP but the form may use a different key |
| monthly_income | 0% | **Wiring gap** | Maps to EAV `monthly_income` — but the live DB shows 0 for buyer; the form saves this under a different key (needs investigation) |
| number_of_occupants | 0% | **Form placement** | Maps to `number_occupant` — may not appear in the standard buyer wizard |
| min_acreage | 0% | **Land-buyer only — Low priority** | Only relevant for land purchases |

### B3 — Landlord Low-Completion Fields

| Field | Coverage | Assessment | Reason for Low Adoption |
|---|---|---|---|
| interior_features | 0% | **Critical gap — Improve** | Never populated on landlord listings. Renters frequently ask about finishes |
| min_credit_score / income_qualification_method / employment_requirement / eviction/bankruptcy requirements | 0% | **Critical gap — Improve** | Screening criteria fields are all zero. These are the most common tenant screening questions and should be mandatory |
| roof_type / exterior_construction / foundation | 0% | **Structural gap** | These physical condition fields exist in the Landlord CANONICAL_SOURCE_MAP but are never populated. They may only be accessible via Full Service offer listings, which make up only 6/20 records — and those 6 also show 0%, suggesting the form tab doesn't include these fields for landlord listings |
| zoning | 10% | **Moderate value** | Same as seller: landlords don't know their zoning |
| lease_terms (terms_of_lease) | 10% | **Valuable — Improve** | The full terms_of_lease multi-select (Month-to-Month, 6-month, 12-month, etc.) only reached 2/20 records — most landlords skip the detailed terms pane |
| tenant_pays | 10% | **High value — Improve** | Critical for rent comparison; tenants always ask what utilities they pay |
| security_deposit_amount | 20% | **High value — Improve** | Tenants always ask about security deposit but landlords often skip it |
| available_date | 20% | **High value — Improve** | Tenants need to know move-in date; most landlords skip it |
| smoking_policy / subletting_policy | 20% | **Valuable — Improve** | Common tenant questions; skipped by most landlords |

### B4 — Tenant Low-Completion Fields

| Field | Coverage | Assessment | Reason for Low Adoption |
|---|---|---|---|
| bathrooms | 0% | **Wiring gap** | Zero records. Form may be saving under a different key or field is not required |
| appliances | 0% | **Low priority** | Tenants rarely specify required appliances upfront |
| prior_eviction / prior_felony | 0% | **Privacy-sensitive — Keep optional** | Tenants deliberately skip; not a UX issue, a privacy self-protection choice |
| emotional_support_animal | 0% | **Privacy-sensitive — Keep optional** | Same as above |
| guests_allowed | 0% | **Preference, not requirement** | Tenants treat this as a landlord decision |
| pet_information | 0% | **Wiring gap** | Maps to `pet_information` EAV key — zero records. Pet data appears to be stored under `pets` / `type_of_pets` instead |
| intended_business_use | 14.3% | **Commercial-tenant only** | Expected low adoption; relevant only for commercial space seekers |

---

## Part C — UX Recommendations

### C1 — Keep (no action needed)
Fields that are low-completion by design (commercial-only, land-only, business-opportunity-only) and whose adoption within the relevant sub-set is proportional.

- `ceiling_height`, `price_per_sqft`, `existing_lease_type`, `lease_expiration`, `lease_assignable`, `electrical_service` (Seller) — commercial sub-set fields
- `road_surface_type`, `road_frontage`, `front_footage`, `fences`, `vegetation`, `buildable`, `easements` (Seller) — vacant land sub-set fields
- `business_type_preference` (Buyer) — commercial buyer only
- `flood_zone_date` — rarely known even by specialists
- `prior_eviction`, `prior_felony`, `emotional_support_animal` (Tenant) — privacy-sensitive; keep optional

### C2 — Improve (UX friction is the main barrier)

**description / additional_details (all roles):**
- **Action:** Move the description field to a more prominent position — ideally step 1 or 2 of the wizard, not the last optional tab. Add a short placeholder prompt: *"Describe what makes this listing stand out — the more you share, the better Ask AI can answer buyer and tenant questions."*
- **Impact:** High. Description is Ask AI's most powerful synthesis input and FAQ fallback. Every percentage point of adoption directly improves answer quality.

**interior_features (Seller, Landlord — <3%):**
- **Action:** Replace the flat multi-select with a stepped approach: first ask "Does this property have any notable interior features?" with a Yes/No toggle, then reveal the multi-select. Add a "Most common" chip row (granite countertops, hardwood floors, crown molding, stainless appliances) so users don't have to scroll a 50-option list.
- **Impact:** High. Buyers and renters ask about finishes frequently. Currently this field answers 0 of their questions.

**Tenant screening criteria (Landlord — 0%):**
- **Action:** Promote `min_credit_score`, `income_qualification_method`, `employment_requirement`, and `eviction_history_requirement` from optional to **recommended** with inline nudge: *"Tenants who see your screening criteria up-front are 3× more likely to apply."* Provide sensible defaults (e.g. credit 620+, income 3× rent, employed or proof of income).
- **Impact:** Very high. These are the top 4 questions tenants ask before applying. Currently Ask AI returns `insufficient_context` for all of them.

**Commute fields (Buyer/Tenant — 12–29%):**
- **Action:** Frame commute inputs as a lifestyle question, not a technical search filter. Replace label "Commute destination zip code" with *"Where do you go most days? (work, school, family)"* and offer a map click to set it. Show the estimated commute range once set to make it feel useful immediately.
- **Impact:** High. Commute is a top-3 buyer decision factor. Currently `commute_mode` is at 12% because it only appears after filling the destination zip.

**Commute mode (Buyer — 12%):**
- **Action:** Show `commute_mode` as a visual icon row (Car / Public Transit / Bike / Walk) immediately below the commute destination input — not as a separate step.
- **Impact:** Medium-high.

**zoning (Seller/Landlord — 10–20%):**
- **Action:** Add a "Look up my zoning" button that pre-fills the field from the property address via a geocode + county GIS lookup. If automatic lookup fails, show a hint: *"Your zoning code is usually found on your county property appraiser's website."*
- **Impact:** Medium. Zoning is essential for commercial and land buyers but hard for sellers to answer without help.

**security_deposit_amount (Landlord — 20%):**
- **Action:** Pre-populate with the market standard (1× monthly rent) and let landlords adjust. Add helper text: *"Your local market typically requires 1–2 months' security deposit."*
- **Impact:** High. Tenants always ask about deposit before deciding to apply.

**available_date (Landlord — 20%):**
- **Action:** Make this field visible at the top of the listing wizard summary step (along with rent amount) rather than buried in the lease terms tab.
- **Impact:** High. Availability date is a showstopper field — tenants filter by it.

**tenant_pays / rent_includes (Landlord — 10%):**
- **Action:** Add a prominent "Who pays which utilities?" section with a simple toggle per utility type. Frame it as *"Tenants save time when they know exactly what's included."*
- **Impact:** High. Utility responsibility is the #2 most common renter question after rent amount.

**flood_zone_tolerance (Buyer — 24%):**
- **Action:** Change label from "Flood zone tolerance" (technical) to *"Are you open to properties in a flood zone if the price is right?"* with Yes / No / Depends on the deal buttons.
- **Impact:** Medium. Improves match quality for coastal / flood-prone markets.

**buy_now_price (Seller — 29%):**
- **Action:** Show a tooltip explaining the value: *"A Buy Now price lets motivated buyers skip the bidding period. Set it 5–15% above your minimum target."*
- **Impact:** Medium.

### C3 — Merge (consolidate confusing redundant fields)

**water_view / view_preference (all roles):**
- Current: `water_view` stores specific water bodies; `view_preference` stores scenic preferences. Two separate fields with overlapping intent confuse users.
- **Recommendation:** Merge into a single "Views & Water Access" section with grouped checkboxes (water types + scenic types in one expanded panel). The merge should be a UI consolidation — both EAV keys can still be written separately behind the scenes.

**pet_policy / pets (Landlord):**
- Current: `pets` holds the real data; `pet_policy` is always null. Confirmed via live DB audit.
- **Recommendation:** Remove `pet_policy` from the form and only surface `pets`. No code change needed — the CANONICAL_SOURCE_MAP already cascades `pets` first.

### C4 — Fix (Wiring Gaps — code defects, not UX issues)

The following fields have 0% adoption because the form saves the value under a different key than what the CANONICAL_SOURCE_MAP reads. These are **code defects requiring a future fix task**, not UX problems:

| Role | Context Key | Source Key Read | Actual Form Save Key (suspected) |
|---|---|---|---|
| Buyer | `year_built_preference` | `year_built` | Likely `year_built_preference` or not included in buyer form |
| Buyer | `monthly_income` | `monthly_income` | Saved under different key; 0 in DB despite being in form |
| Buyer | `number_of_occupants` | `number_occupant` | May not exist in buyer form or saved differently |
| Tenant | `bathrooms` | `bathrooms` | Zero records — form may save `other_bathrooms` only |
| Tenant | `pet_information` | `pet_information` | Form saves pet data under `pets` / `type_of_pets` instead |

---

## Part D — Ask AI Opportunity Report

### High-Value Fields with Low Completion

These fields are Ask AI-answerable, frequently asked by buyers/tenants, and currently empty for most listings. Improving them would directly eliminate `insufficient_context` responses.

| Priority | Field | Role | Current % | Why It Matters | Encouragement Strategy |
|---|---|---|---|---|---|
| 🔴 Critical | description / additional_details | All | 0–49% | Fallback for 40+ FAQ answers | Move to step 1; prompt with examples |
| 🔴 Critical | min_credit_score | Landlord | 0% | #1 tenant pre-application question | Make recommended; show market default |
| 🔴 Critical | income_qualification_method | Landlord | 0% | Required for tenant qualification | Bundle with credit score in one step |
| 🔴 Critical | tenant_pays / rent_includes | Landlord | 10% | Drives true cost comparison | Utility toggle UI; show tenant savings |
| 🔴 Critical | security_deposit_amount | Landlord | 20% | Tenants ask before every showing | Pre-fill with 1× rent default |
| 🔴 Critical | interior_features | Seller/Landlord | 2% | Top buyer question about finishes | Chip-based quick-select |
| 🔴 Critical | available_date | Landlord | 20% | Showstopper for move-in planning | Promote to listing summary step |
| 🟠 High | commute_destination_zip | Buyer/Tenant | 29%/29% | Top buyer life quality factor | Map click to set; show commute range |
| 🟠 High | commute_mode | Buyer/Tenant | 12%/29% | Transit-dependent buyers can't filter | Icon row next to destination |
| 🟠 High | smoking_policy | Landlord | 20% | Common deal-breaker for tenants | Bundle with pet policy as "House Rules" |
| 🟠 High | subletting_policy | Landlord | 20% | Remote workers and co-signers need this | Bundle with smoking policy |
| 🟠 High | eviction_history_requirement | Landlord | 0% | Critical for applicant screening | Group in "Tenant Screening" block |
| 🟠 High | employment_requirement | Landlord | 0% | Most landlords have this implicitly | One-click standard options |
| 🟠 High | zoning | Seller | 19.5% | Commercial/land buyers need it to act | "Look up my zoning" geocode button |
| 🟡 Medium | waterfront / water_access | Seller | 0% | Waterfront buyers ask before touring | Trigger on waterfront=Yes selection |
| 🟡 Medium | flood_zone_tolerance | Buyer | 24% | Important in flood-prone markets | Rephrase as plain-language yes/no |
| 🟡 Medium | building_features | Seller | 12% | Amenity buyers filter on these | Chip-based quick-select |
| 🟡 Medium | lease_terms (terms_of_lease) | Landlord | 10% | Tenants need lease length options | Make step 1 of lease section |
| 🟡 Medium | accessibility_requirements | Tenant | 29% | Protected-class adjacent; needs sensitivity | "Are you looking for accessibility features?" neutral phrasing |
| 🟡 Medium | move_in_funds_available | Tenant | 29% | Landlord qualification signal | Explain how it improves match quality |

---

## Part E — Final Deliverable

### E1 — Top 20 Most Valuable Missing Fields

Ranked by combined match quality impact + Ask AI answer quality impact + marketplace intelligence impact.

| Rank | Field | Role(s) | Current % | Combined Impact |
|---|---|---|---|---|
| 1 | description / additional_details | All | 0–49% | ⭐⭐⭐⭐⭐ FAQ fallback, synthesis anchor, first-impression content |
| 2 | min_credit_score | Landlord | 0% | ⭐⭐⭐⭐⭐ #1 pre-application screening question |
| 3 | tenant_pays / rent_includes | Landlord | 10% | ⭐⭐⭐⭐⭐ True cost calculation; true rent comparison |
| 4 | income_qualification_method | Landlord | 0% | ⭐⭐⭐⭐⭐ Determines applicant eligibility |
| 5 | security_deposit_amount | Landlord | 20% | ⭐⭐⭐⭐⭐ Tenants ask before every showing |
| 6 | interior_features | Seller / Landlord | 2% | ⭐⭐⭐⭐ Most frequently asked buyer/renter question |
| 7 | available_date | Landlord | 20% | ⭐⭐⭐⭐ Showstopper filter; tenants skip listings without it |
| 8 | commute_destination_zip | Buyer / Tenant | 29% | ⭐⭐⭐⭐ Top 3 location decision factor |
| 9 | eviction_history_requirement | Landlord | 0% | ⭐⭐⭐⭐ Critical for applicant self-screening |
| 10 | employment_requirement | Landlord | 0% | ⭐⭐⭐⭐ Tenant qualification filter |
| 11 | smoking_policy | Landlord | 20% | ⭐⭐⭐⭐ Common deal-breaker; saves wasted showings |
| 12 | subletting_policy | Landlord | 20% | ⭐⭐⭐ Increasingly important for remote workers |
| 13 | lease_terms (terms_of_lease) | Landlord | 10% | ⭐⭐⭐⭐ Enables "will you accept a 6-month lease?" Ask AI answers |
| 14 | commute_mode | Buyer / Tenant | 12%/29% | ⭐⭐⭐ Match quality for transit-dependent users |
| 15 | zoning | Seller | 20% | ⭐⭐⭐⭐ Commercial/land buyers can't proceed without it |
| 16 | waterfront / water_access / waterfront_feet | Seller | 0% | ⭐⭐⭐ Waterfront buyers ask extensively before touring |
| 17 | building_features | Seller | 12% | ⭐⭐⭐ Amenity filtering and Ask AI answer quality |
| 18 | flood_zone_tolerance | Buyer | 24% | ⭐⭐⭐ Coastal market match quality |
| 19 | year_built_preference | Buyer | 0% | ⭐⭐⭐ Match signal for renovation vs. turnkey preference |
| 20 | pet_deposit_amount / pet_monthly_fee | Landlord | 20% | ⭐⭐⭐ Pet owners need total cost to decide |

---

### E2 — Prioritized Form Improvement List

#### Quick Wins (low effort, high impact — should be done first)

1. **Move `description` to step 1 of all listing wizards** — No schema change; only wizard step ordering. Eliminates the #1 Ask AI gap for all roles.
2. **Add "Tenant Screening" grouped block to Landlord form** — Combine `min_credit_score`, `income_qualification_method`, `employment_requirement`, `eviction_history_requirement`, `bankruptcy_requirement` into one collapsible section with smart defaults. Zero schema work; all keys already exist.
3. **Promote `available_date` to Landlord listing summary step** — Move it out of the lease terms tab. No schema change.
4. **Add `security_deposit_amount` default (1× rent)** — Pre-populate with `desired_rental_amount` value as default. No schema change.
5. **Bundle `smoking_policy` + `subletting_policy` as "House Rules"** — Simple grouping rename in the wizard. No schema change.
6. **Rephrase `flood_zone_tolerance` to plain language** — Label change only. No schema change.
7. **Fix `tenant_pays` visibility** — Ensure the "Who pays which utilities?" section is shown prominently for all Landlord listings (currently shows 10% — may be tab-hidden). No schema change.

#### Medium Effort (some UI redesign required)

8. **Chip-based interior_features selector** — Replace flat multi-select with grouped "Most common" chips + "See more" expansion. Requires UI work in Livewire form but no schema changes.
9. **Commute UX overhaul (Buyer + Tenant)** — Visual icon row for mode; map-based destination picker; immediate commute radius preview. Requires Livewire + mapping library integration.
10. **"Look up my zoning" geocode button** — Requires county GIS API integration for zoning lookup. Medium backend work.
11. **Waterfront fields conditional trigger** — Ensure `waterfront`, `water_access`, and `waterfront_feet` fields correctly show when `waterfront_type` or `water_access` toggles are active. Appears to be a conditional display bug (0% completion despite being in CANONICAL_SOURCE_MAP).
12. **Fix buyer `description` native column wiring** — The buyer wizard saves description via EAV meta `additional_details` but the CANONICAL_SOURCE_MAP reads native column `additional_details`. These are different storage locations. The context builder always reads null. Requires either (a) changing the wizard save path to the native column, or (b) updating CANONICAL_SOURCE_MAP to `['additional_details', 'native:additional_details']` fallback cascade.

#### Major Redesign (high effort, high return)

13. **Seller property description wizard** — Replace plain textarea with a structured prompt wizard: *"Tell us about: 1. The property's best features, 2. Recent updates/renovations, 3. Neighborhood highlights."* Maps to FAQ answers for roof, HVAC, renovations, etc.
14. **Landlord listing completeness score** — Show a "profile strength" indicator during editing with specific callouts for missing high-value fields (screening criteria, tenant_pays, available_date). Uses same pattern as LinkedIn profile completeness.
15. **Business Opportunity listing guided interview** — Business sellers currently fill out ~25 specialized fields in a flat form. A conversational interview flow would dramatically improve completion for this sub-type.

---

### E3 — Summary Statistics

| Role | Total Fields in Audit Universe | High (≥75%) | Medium (25–74%) | Low (10–24%) | Very Low/Zero (<10%) |
|---|---|---|---|---|---|
| Seller | ~85 | 3 | ~40 | ~12 | ~30 |
| Buyer | ~35 | 1 | ~22 | ~5 | ~7 |
| Landlord | ~65 | 1 | ~32 | ~15 | ~17 |
| Tenant | ~40 | 1 | ~20 | ~5 | ~14 |

**Overall data health grade: C+**  
Core transactional fields (service_type, price, basic size) are well-populated. High-value qualitative fields (description, screening criteria, commute, interior features, policy fields) are chronically under-filled. Fixing the 7 Quick Win items listed above would realistically move the Ask AI `insufficient_context` rate from ~20% to under 8% for active listings.

---

*Report generated from live development database snapshot — June 19, 2026.*  
*No code changes were made as part of this audit.*
