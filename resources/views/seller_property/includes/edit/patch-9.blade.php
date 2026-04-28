<div class="wizard-step" data-step="94">
  <h4>AI Questions / Chatbot Knowledge Base</h4>
  <div class="alert alert-info mb-4">
    <strong>Internal Use Only:</strong> These answers are stored privately and will not appear publicly on your listing. They help power a property-specific chatbot that can accurately answer common Buyer questions on your behalf. All fields are optional.
  </div>

  @php
    $aiFaq = json_decode($auction->get->listing_ai_faq ?? '{}', true) ?? [];
  @endphp

  <h5 class="mt-4 fw-bold border-bottom pb-2">Pricing, Costs &amp; Financing</h5>
  <div class="form-group">
    <label class="fw-bold">Is the asking price negotiable?</label>
    <textarea name="listing_ai_faq[asking_price_negotiation]" class="form-control" rows="2">{{ $aiFaq['asking_price_negotiation'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What do recent comparable sales in the area indicate about this pricing?</label>
    <textarea name="listing_ai_faq[recent_comparable_sales]" class="form-control" rows="2">{{ $aiFaq['recent_comparable_sales'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What is the price per square foot?</label>
    <textarea name="listing_ai_faq[price_per_sqft]" class="form-control" rows="2">{{ $aiFaq['price_per_sqft'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Are you offering any seller concessions or credits?</label>
    <textarea name="listing_ai_faq[seller_concessions_offered]" class="form-control" rows="2">{{ $aiFaq['seller_concessions_offered'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What are the HOA/association fees?</label>
    <textarea name="listing_ai_faq[hoa_fees_amount]" class="form-control" rows="2">{{ $aiFaq['hoa_fees_amount'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What are the annual property taxes?</label>
    <textarea name="listing_ai_faq[annual_property_taxes]" class="form-control" rows="2">{{ $aiFaq['annual_property_taxes'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Are there any pending special assessments?</label>
    <textarea name="listing_ai_faq[special_assessments_pending]" class="form-control" rows="2">{{ $aiFaq['special_assessments_pending'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What are the estimated closing costs?</label>
    <textarea name="listing_ai_faq[estimated_closing_costs]" class="form-control" rows="2">{{ $aiFaq['estimated_closing_costs'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What are the average monthly utility costs?</label>
    <textarea name="listing_ai_faq[average_utility_costs]" class="form-control" rows="2">{{ $aiFaq['average_utility_costs'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What does property insurance typically cost?</label>
    <textarea name="listing_ai_faq[property_insurance_estimate]" class="form-control" rows="2">{{ $aiFaq['property_insurance_estimate'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Has the listing price been reduced, and if so, why?</label>
    <textarea name="listing_ai_faq[price_reduction_history]" class="form-control" rows="2">{{ $aiFaq['price_reduction_history'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Will the seller contribute to buyer closing costs?</label>
    <textarea name="listing_ai_faq[seller_paid_closing_costs]" class="form-control" rows="2">{{ $aiFaq['seller_paid_closing_costs'] ?? '' }}</textarea>
  </div>

  <h5 class="mt-4 fw-bold border-bottom pb-2">Financing &amp; Pre-Approval</h5>
  <div class="form-group">
    <label class="fw-bold">Is seller financing available?</label>
    <textarea name="listing_ai_faq[seller_financing_offered]" class="form-control" rows="2">{{ $aiFaq['seller_financing_offered'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Can the existing mortgage be assumed?</label>
    <textarea name="listing_ai_faq[existing_loan_assumable]" class="form-control" rows="2">{{ $aiFaq['existing_loan_assumable'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What types of financing do you prefer?</label>
    <textarea name="listing_ai_faq[preferred_financing_types]" class="form-control" rows="2">{{ $aiFaq['preferred_financing_types'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Do you offer a discount for cash offers?</label>
    <textarea name="listing_ai_faq[cash_offer_discount]" class="form-control" rows="2">{{ $aiFaq['cash_offer_discount'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Is a mortgage pre-approval letter required with offers?</label>
    <textarea name="listing_ai_faq[pre_approval_required]" class="form-control" rows="2">{{ $aiFaq['pre_approval_required'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What is the minimum down payment you will consider?</label>
    <textarea name="listing_ai_faq[minimum_down_payment]" class="form-control" rows="2">{{ $aiFaq['minimum_down_payment'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Will you accept FHA or VA loan offers?</label>
    <textarea name="listing_ai_faq[fha_va_loans_accepted]" class="form-control" rows="2">{{ $aiFaq['fha_va_loans_accepted'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Will you accept offers with a financing contingency?</label>
    <textarea name="listing_ai_faq[financing_contingency_accepted]" class="form-control" rows="2">{{ $aiFaq['financing_contingency_accepted'] ?? '' }}</textarea>
  </div>

  <h5 class="mt-4 fw-bold border-bottom pb-2">Property Details</h5>
  <div class="form-group">
    <label class="fw-bold">What renovations or improvements have been made, and when?</label>
    <textarea name="listing_ai_faq[recent_renovations_list]" class="form-control" rows="2">{{ $aiFaq['recent_renovations_list'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">How old is the roof and what condition is it in?</label>
    <textarea name="listing_ai_faq[roof_age_and_condition]" class="form-control" rows="2">{{ $aiFaq['roof_age_and_condition'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">How old is the HVAC system, and when was it last serviced?</label>
    <textarea name="listing_ai_faq[hvac_system_age]" class="form-control" rows="2">{{ $aiFaq['hvac_system_age'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">How old is the water heater, and what type is it?</label>
    <textarea name="listing_ai_faq[water_heater_age_type]" class="form-control" rows="2">{{ $aiFaq['water_heater_age_type'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Which appliances are included in the sale?</label>
    <textarea name="listing_ai_faq[appliances_included_list]" class="form-control" rows="2">{{ $aiFaq['appliances_included_list'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What type of foundation does the property have, and are there any known issues?</label>
    <textarea name="listing_ai_faq[foundation_type_and_issues]" class="form-control" rows="2">{{ $aiFaq['foundation_type_and_issues'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Were all renovations and additions completed with proper permits?</label>
    <textarea name="listing_ai_faq[permits_for_renovations]" class="form-control" rows="2">{{ $aiFaq['permits_for_renovations'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Are there any known defects, issues, or repairs needed?</label>
    <textarea name="listing_ai_faq[known_defects_issues]" class="form-control" rows="2">{{ $aiFaq['known_defects_issues'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Has the property ever had pest or termite issues?</label>
    <textarea name="listing_ai_faq[pest_termite_history]" class="form-control" rows="2">{{ $aiFaq['pest_termite_history'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Has the property ever flooded or experienced water damage?</label>
    <textarea name="listing_ai_faq[flood_damage_history]" class="form-control" rows="2">{{ $aiFaq['flood_damage_history'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Has the property ever had mold issues?</label>
    <textarea name="listing_ai_faq[mold_issues_history]" class="form-control" rows="2">{{ $aiFaq['mold_issues_history'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What is the breakdown of heated versus total square footage?</label>
    <textarea name="listing_ai_faq[square_footage_breakdown]" class="form-control" rows="2">{{ $aiFaq['square_footage_breakdown'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What are the parking arrangements (garage, driveway, street)?</label>
    <textarea name="listing_ai_faq[parking_arrangements]" class="form-control" rows="2">{{ $aiFaq['parking_arrangements'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What storage space is available?</label>
    <textarea name="listing_ai_faq[storage_space_available]" class="form-control" rows="2">{{ $aiFaq['storage_space_available'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What internet and utility providers serve this property?</label>
    <textarea name="listing_ai_faq[internet_utility_providers]" class="form-control" rows="2">{{ $aiFaq['internet_utility_providers'] ?? '' }}</textarea>
  </div>

  <h5 class="mt-4 fw-bold border-bottom pb-2">Offer &amp; Negotiation</h5>
  <div class="form-group">
    <label class="fw-bold">When will offers be reviewed?</label>
    <textarea name="listing_ai_faq[offer_review_timeline]" class="form-control" rows="2">{{ $aiFaq['offer_review_timeline'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">How will multiple offers be handled?</label>
    <textarea name="listing_ai_faq[multiple_offer_strategy]" class="form-control" rows="2">{{ $aiFaq['multiple_offer_strategy'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Will you consider escalation clauses in offers?</label>
    <textarea name="listing_ai_faq[escalation_clause_accepted]" class="form-control" rows="2">{{ $aiFaq['escalation_clause_accepted'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What amount of earnest money is expected?</label>
    <textarea name="listing_ai_faq[expected_earnest_money]" class="form-control" rows="2">{{ $aiFaq['expected_earnest_money'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What contingencies are you comfortable accepting?</label>
    <textarea name="listing_ai_faq[preferred_contingencies]" class="form-control" rows="2">{{ $aiFaq['preferred_contingencies'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Is this being sold as-is, or will you make repairs?</label>
    <textarea name="listing_ai_faq[as_is_condition]" class="form-control" rows="2">{{ $aiFaq['as_is_condition'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Are any seller credits available for repairs or upgrades?</label>
    <textarea name="listing_ai_faq[seller_credit_availability]" class="form-control" rows="2">{{ $aiFaq['seller_credit_availability'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">How flexible are you on the closing date?</label>
    <textarea name="listing_ai_faq[closing_timeline_flexibility]" class="form-control" rows="2">{{ $aiFaq['closing_timeline_flexibility'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Are there any fixtures, appliances, or items excluded from the sale?</label>
    <textarea name="listing_ai_faq[items_excluded_from_sale]" class="form-control" rows="2">{{ $aiFaq['items_excluded_from_sale'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Will you consider backup offers?</label>
    <textarea name="listing_ai_faq[backup_offer_accepted]" class="form-control" rows="2">{{ $aiFaq['backup_offer_accepted'] ?? '' }}</textarea>
  </div>

  <h5 class="mt-4 fw-bold border-bottom pb-2">Inspections &amp; Due Diligence</h5>
  <div class="form-group">
    <label class="fw-bold">Will you allow a buyer's inspection?</label>
    <textarea name="listing_ai_faq[buyer_inspection_allowed]" class="form-control" rows="2">{{ $aiFaq['buyer_inspection_allowed'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Are any existing inspection reports available to buyers?</label>
    <textarea name="listing_ai_faq[existing_inspection_reports]" class="form-control" rows="2">{{ $aiFaq['existing_inspection_reports'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Will you offer repair credits based on the inspection?</label>
    <textarea name="listing_ai_faq[repair_credits_after_inspection]" class="form-control" rows="2">{{ $aiFaq['repair_credits_after_inspection'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Has a pre-listing inspection been completed?</label>
    <textarea name="listing_ai_faq[pre_listing_inspection_done]" class="form-control" rows="2">{{ $aiFaq['pre_listing_inspection_done'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What inspection period length do you prefer?</label>
    <textarea name="listing_ai_faq[inspection_period_length]" class="form-control" rows="2">{{ $aiFaq['inspection_period_length'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Is a recent survey available?</label>
    <textarea name="listing_ai_faq[recent_survey_available]" class="form-control" rows="2">{{ $aiFaq['recent_survey_available'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Has a title search been completed?</label>
    <textarea name="listing_ai_faq[title_search_completed]" class="form-control" rows="2">{{ $aiFaq['title_search_completed'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What type of title insurance is available?</label>
    <textarea name="listing_ai_faq[title_insurance_available]" class="form-control" rows="2">{{ $aiFaq['title_insurance_available'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Are there any known environmental concerns or restrictions?</label>
    <textarea name="listing_ai_faq[environmental_concerns]" class="form-control" rows="2">{{ $aiFaq['environmental_concerns'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What due diligence documents will you provide?</label>
    <textarea name="listing_ai_faq[due_diligence_documents_available]" class="form-control" rows="2">{{ $aiFaq['due_diligence_documents_available'] ?? '' }}</textarea>
  </div>

  <h5 class="mt-4 fw-bold border-bottom pb-2">Closing &amp; Possession</h5>
  <div class="form-group">
    <label class="fw-bold">What is your preferred closing date?</label>
    <textarea name="listing_ai_faq[preferred_closing_date]" class="form-control" rows="2">{{ $aiFaq['preferred_closing_date'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What is the earliest possible closing date?</label>
    <textarea name="listing_ai_faq[earliest_possible_closing]" class="form-control" rows="2">{{ $aiFaq['earliest_possible_closing'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">When can the buyer take possession after closing?</label>
    <textarea name="listing_ai_faq[possession_date_after_closing]" class="form-control" rows="2">{{ $aiFaq['possession_date_after_closing'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Would you consider a seller leaseback arrangement?</label>
    <textarea name="listing_ai_faq[seller_leaseback_option]" class="form-control" rows="2">{{ $aiFaq['seller_leaseback_option'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Do you have a preferred closing attorney or title company?</label>
    <textarea name="listing_ai_faq[preferred_title_company]" class="form-control" rows="2">{{ $aiFaq['preferred_title_company'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What is your moving timeline?</label>
    <textarea name="listing_ai_faq[moving_timeline]" class="form-control" rows="2">{{ $aiFaq['moving_timeline'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Is the property currently occupied, vacant, or rented?</label>
    <textarea name="listing_ai_faq[property_occupancy_status]" class="form-control" rows="2">{{ $aiFaq['property_occupancy_status'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Are there any existing tenant leases that will transfer?</label>
    <textarea name="listing_ai_faq[existing_tenant_leases]" class="form-control" rows="2">{{ $aiFaq['existing_tenant_leases'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">How will keys and access codes be handled at closing?</label>
    <textarea name="listing_ai_faq[key_access_at_closing]" class="form-control" rows="2">{{ $aiFaq['key_access_at_closing'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Are you flexible on closing date extensions if needed?</label>
    <textarea name="listing_ai_faq[closing_extension_flexibility]" class="form-control" rows="2">{{ $aiFaq['closing_extension_flexibility'] ?? '' }}</textarea>
  </div>

  <h5 class="mt-4 fw-bold border-bottom pb-2">Location &amp; Neighborhood</h5>
  <div class="form-group">
    <label class="fw-bold">What school district serves this property?</label>
    <textarea name="listing_ai_faq[school_district_name]" class="form-control" rows="2">{{ $aiFaq['school_district_name'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What amenities, shops, and services are nearby?</label>
    <textarea name="listing_ai_faq[nearby_amenities_description]" class="form-control" rows="2">{{ $aiFaq['nearby_amenities_description'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Are there deed restrictions or neighborhood rules buyers should know?</label>
    <textarea name="listing_ai_faq[neighborhood_restrictions]" class="form-control" rows="2">{{ $aiFaq['neighborhood_restrictions'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Are there any traffic, noise, or nuisance concerns nearby?</label>
    <textarea name="listing_ai_faq[traffic_or_noise_concerns]" class="form-control" rows="2">{{ $aiFaq['traffic_or_noise_concerns'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Is the property in a FEMA flood zone, and what is the flood zone code?</label>
    <textarea name="listing_ai_faq[flood_zone_information]" class="form-control" rows="2">{{ $aiFaq['flood_zone_information'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Are there any planned developments or road projects nearby?</label>
    <textarea name="listing_ai_faq[planned_nearby_development]" class="form-control" rows="2">{{ $aiFaq['planned_nearby_development'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">How would you describe the neighborhood and community?</label>
    <textarea name="listing_ai_faq[neighborhood_character]" class="form-control" rows="2">{{ $aiFaq['neighborhood_character'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What are the typical commute options and travel times to major employment centers?</label>
    <textarea name="listing_ai_faq[commute_options_access]" class="form-control" rows="2">{{ $aiFaq['commute_options_access'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Is public transportation accessible from this location?</label>
    <textarea name="listing_ai_faq[public_transportation_access]" class="form-control" rows="2">{{ $aiFaq['public_transportation_access'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What do you value most about living in this community?</label>
    <textarea name="listing_ai_faq[hoa_community_highlights]" class="form-control" rows="2">{{ $aiFaq['hoa_community_highlights'] ?? '' }}</textarea>
  </div>

  <h5 class="mt-4 fw-bold border-bottom pb-2">High-Intent Buyer Questions</h5>
  <div class="form-group">
    <label class="fw-bold">What is your motivation for selling?</label>
    <textarea name="listing_ai_faq[seller_motivation_for_selling]" class="form-control" rows="2">{{ $aiFaq['seller_motivation_for_selling'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">How long has the property been on the market?</label>
    <textarea name="listing_ai_faq[current_days_on_market]" class="form-control" rows="2">{{ $aiFaq['current_days_on_market'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Have there been any prior offers, and if so, why did they fall through?</label>
    <textarea name="listing_ai_faq[prior_offer_activity]" class="form-control" rows="2">{{ $aiFaq['prior_offer_activity'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Do you have a preference for the type of buyer?</label>
    <textarea name="listing_ai_faq[ideal_buyer_profile]" class="form-control" rows="2">{{ $aiFaq['ideal_buyer_profile'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Is the property move-in ready, or does it need work?</label>
    <textarea name="listing_ai_faq[move_in_ready_status]" class="form-control" rows="2">{{ $aiFaq['move_in_ready_status'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">Is the property currently staged or furnished?</label>
    <textarea name="listing_ai_faq[staged_or_furnished_info]" class="form-control" rows="2">{{ $aiFaq['staged_or_furnished_info'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What are the key items on the seller disclosure?</label>
    <textarea name="listing_ai_faq[seller_disclosure_highlights]" class="form-control" rows="2">{{ $aiFaq['seller_disclosure_highlights'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What are the unique selling points of this property?</label>
    <textarea name="listing_ai_faq[unique_selling_points]" class="form-control" rows="2">{{ $aiFaq['unique_selling_points'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="fw-bold">What features or aspects of this property will you miss most?</label>
    <textarea name="listing_ai_faq[seller_favorite_features]" class="form-control" rows="2">{{ $aiFaq['seller_favorite_features'] ?? '' }}</textarea>
  </div>

  <div class="ai-faq-commercial-income-section d-none">
    <h5 class="mt-4 fw-bold border-bottom pb-2">Commercial / Income Property (5+ Units) Questions</h5>
    <div class="form-group">
      <label class="fw-bold">What is the current annual net operating income (NOI)?</label>
      <textarea name="listing_ai_faq[annual_net_operating_income]" class="form-control" rows="2">{{ $aiFaq['annual_net_operating_income'] ?? '' }}</textarea>
    </div>
    <div class="form-group">
      <label class="fw-bold">What is the current capitalization rate?</label>
      <textarea name="listing_ai_faq[current_cap_rate]" class="form-control" rows="2">{{ $aiFaq['current_cap_rate'] ?? '' }}</textarea>
    </div>
    <div class="form-group">
      <label class="fw-bold">What are the existing tenant lease terms and expiration dates?</label>
      <textarea name="listing_ai_faq[existing_tenant_lease_terms]" class="form-control" rows="2">{{ $aiFaq['existing_tenant_lease_terms'] ?? '' }}</textarea>
    </div>
    <div class="form-group">
      <label class="fw-bold">What is the current occupancy rate?</label>
      <textarea name="listing_ai_faq[current_occupancy_rate]" class="form-control" rows="2">{{ $aiFaq['current_occupancy_rate'] ?? '' }}</textarea>
    </div>
    <div class="form-group">
      <label class="fw-bold">What are the detailed annual operating expenses?</label>
      <textarea name="listing_ai_faq[annual_operating_expenses_detail]" class="form-control" rows="2">{{ $aiFaq['annual_operating_expenses_detail'] ?? '' }}</textarea>
    </div>
  </div>

  <div class="ai-faq-business-section d-none">
    <h5 class="mt-4 fw-bold border-bottom pb-2">Business Opportunity Questions</h5>
    <div class="form-group">
      <label class="fw-bold">What is the current annual gross revenue?</label>
      <textarea name="listing_ai_faq[annual_business_revenue]" class="form-control" rows="2">{{ $aiFaq['annual_business_revenue'] ?? '' }}</textarea>
    </div>
    <div class="form-group">
      <label class="fw-bold">Why is the business being sold?</label>
      <textarea name="listing_ai_faq[business_reason_for_selling]" class="form-control" rows="2">{{ $aiFaq['business_reason_for_selling'] ?? '' }}</textarea>
    </div>
    <div class="form-group">
      <label class="fw-bold">How many employees does the business have?</label>
      <textarea name="listing_ai_faq[business_employee_count]" class="form-control" rows="2">{{ $aiFaq['business_employee_count'] ?? '' }}</textarea>
    </div>
  </div>

  <div class="ai-faq-vacant-section d-none">
    <h5 class="mt-4 fw-bold border-bottom pb-2">Vacant Land Questions</h5>
    <div class="form-group">
      <label class="fw-bold">What utilities are available or accessible on the land?</label>
      <textarea name="listing_ai_faq[land_utilities_availability]" class="form-control" rows="2">{{ $aiFaq['land_utilities_availability'] ?? '' }}</textarea>
    </div>
    <div class="form-group">
      <label class="fw-bold">What is the current zoning designation and what uses are permitted?</label>
      <textarea name="listing_ai_faq[land_zoning_permitted_uses]" class="form-control" rows="2">{{ $aiFaq['land_zoning_permitted_uses'] ?? '' }}</textarea>
    </div>
  </div>
</div>
