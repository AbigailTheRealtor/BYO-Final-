<div class="wizard-step" data-step='47'>
    @php
        $listing_ai_faq_saved = json_decode($auction->get->listing_ai_faq ?? '{}', true) ?? [];

        $listing_ai_faq_questions = [
            'pricing_costs_fees' => [
                'label' => 'Pricing, Costs &amp; Fees',
                'questions' => [
                    'what_is_monthly_rent' => 'What is the monthly rent?',
                    'is_rent_negotiable' => 'Is the rent negotiable?',
                    'which_utilities_included' => 'Which utilities, if any, are included in the rent?',
                    'security_deposit_amount' => 'What is the security deposit amount, and is it refundable?',
                    'pet_deposit_details' => 'Is there a pet deposit, and how much is it?',
                    'additional_monthly_fees' => 'Are there additional monthly fees such as HOA, parking, or trash?',
                    'accepted_payment_methods' => 'What payment methods are accepted for rent?',
                    'late_fee_policy' => 'Is there a late fee, and when does it apply?',
                ],
            ],
            'application_process_requirements' => [
                'label' => 'Application Process &amp; Requirements',
                'questions' => [
                    'minimum_credit_score' => 'What is the minimum credit score required?',
                    'income_requirement' => 'What is the income requirement (e.g., 3x monthly rent)?',
                    'background_check_required' => 'Is a background check required?',
                    'required_application_documents' => 'What documents are needed to apply?',
                    'application_fee_amount' => 'What is the application fee?',
                    'approval_process_timeline' => 'How long does the approval process typically take?',
                    'cosigners_accepted' => 'Are co-signers or guarantors accepted?',
                    'prior_evictions_considered' => 'Are applicants with prior evictions considered?',
                ],
            ],
            'move_in_lease_terms' => [
                'label' => 'Move-In &amp; Lease Terms',
                'questions' => [
                    'available_move_in_date' => 'When is the unit available for move-in?',
                    'minimum_lease_length' => 'What is the minimum lease length?',
                    'month_to_month_available' => 'Is a month-to-month option available after the initial lease?',
                    'lease_renewal_process' => 'What is the lease renewal process?',
                    'notice_to_vacate_required' => 'How much notice is required to vacate?',
                    'move_in_costs_due' => 'What costs are due at move-in (first month, last month, deposit)?',
                    'move_in_inspection_process' => 'Is there a move-in inspection process?',
                    'move_in_fees_separate' => 'Are there any move-in fees separate from the security deposit?',
                ],
            ],
            'property_details' => [
                'label' => 'Property Details',
                'questions' => [
                    'sqft_and_room_dimensions' => 'What are the square footage and approximate room dimensions?',
                    'furnished_or_unfurnished' => 'Is the unit furnished or unfurnished?',
                    'appliances_included' => 'Which appliances are included with the rental?',
                    'laundry_situation' => 'Is there in-unit laundry, or are shared laundry facilities available?',
                    'heating_cooling_system' => 'What type of heating and cooling system does the property have?',
                    'parking_spaces_included' => 'How many parking spaces are included with the rental?',
                    'storage_area_included' => 'Is there a storage area included with the unit?',
                    'floor_and_elevator_access' => 'What floor is the unit on, and is there elevator access?',
                ],
            ],
            'pets' => [
                'label' => 'Pets',
                'questions' => [
                    'pets_allowed' => 'Are pets allowed?',
                    'permitted_pet_types' => 'What types of pets are permitted?',
                    'breed_or_weight_restrictions' => 'Are there breed or weight restrictions for pets?',
                    'max_number_of_pets' => 'How many pets are allowed?',
                    'non_refundable_pet_fee' => 'Is there a non-refundable pet fee, and how much is it?',
                    'monthly_pet_rent' => 'Is there a monthly pet rent charge?',
                    'refundable_pet_deposit' => 'Is there a refundable pet deposit?',
                    'nearby_pet_friendly_amenities' => 'Are there outdoor areas or pet-friendly amenities nearby?',
                ],
            ],
            'parking_transportation' => [
                'label' => 'Parking &amp; Transportation',
                'questions' => [
                    'parking_included_in_rent' => 'Is parking included with the rent?',
                    'number_of_parking_spaces' => 'How many parking spaces are available?',
                    'covered_or_uncovered_parking' => 'Is the parking covered or uncovered?',
                    'additional_parking_fee' => 'Is there a fee for additional parking?',
                    'street_parking_availability' => 'Is street parking available nearby?',
                    'proximity_to_public_transit' => 'How close is the property to public transportation?',
                    'ev_charging_stations' => 'Are EV charging stations available on the property?',
                    'bicycle_storage_available' => 'Is bicycle storage available?',
                ],
            ],
            'maintenance_management' => [
                'label' => 'Maintenance &amp; Management',
                'questions' => [
                    'lawn_exterior_maintenance_responsibility' => 'Who is responsible for lawn care and exterior maintenance?',
                    'maintenance_request_response_time' => 'Who handles maintenance requests, and what is the typical response time?',
                    'emergency_maintenance_available' => 'Is 24-hour emergency maintenance available?',
                    'maintenance_request_process' => 'What is the process for submitting maintenance requests?',
                    'tenant_maintenance_responsibilities' => 'Are tenants responsible for any specific maintenance tasks?',
                    'property_manager_contact' => 'Who is the property management company or manager?',
                    'best_contact_method' => 'What is the best way to contact the landlord or property manager?',
                    'planned_renovations' => 'Are there any planned renovations or construction that could affect tenants?',
                ],
            ],
            'location_neighborhood' => [
                'label' => 'Location &amp; Neighborhood',
                'questions' => [
                    'school_district' => 'What school district is the property in?',
                    'distance_to_grocery_shopping' => 'How far is the property from major grocery stores and shopping?',
                    'nearby_parks_recreation' => 'Are there parks or recreational facilities nearby?',
                    'walkability_transit_score' => 'What is the neighborhood\'s walkability or transit score?',
                    'distance_to_hospital_urgent_care' => 'How far is the property from the nearest hospital or urgent care?',
                    'noise_levels' => 'What is the area like in terms of noise levels?',
                    'flood_zone_environmental_concerns' => 'Are there any known flood zones or environmental concerns in the area?',
                    'nearby_dining_entertainment' => 'What dining and entertainment options are nearby?',
                ],
            ],
            'policies_rules' => [
                'label' => 'Policies &amp; Rules',
                'questions' => [
                    'smoking_policy' => 'Is smoking allowed on the premises?',
                    'quiet_hours' => 'Are quiet hours enforced, and what are they?',
                    'guest_policy' => 'What is the guest policy?',
                    'short_term_rentals_allowed' => 'Are short-term rentals (e.g., Airbnb) permitted?',
                    'decorating_modification_restrictions' => 'Are there any restrictions on decorating or modifying the unit?',
                    'subletting_allowed' => 'Is subletting allowed?',
                    'garbage_recycling_rules' => 'What are the rules regarding garbage and recycling?',
                    'hoa_community_rules' => 'Are there HOA or community rules tenants must follow?',
                ],
            ],
            'high_intent_tenant_questions' => [
                'label' => 'High-Intent Tenant Questions',
                'questions' => [
                    'what_makes_property_unique' => 'What makes this property unique compared to similar rentals?',
                    'how_long_current_ownership' => 'How long has the property been managed by the current owner or manager?',
                    'previous_tenant_feedback' => 'What do current or previous tenants say about living here?',
                    'pest_or_mold_history' => 'Have there ever been any pest or mold issues, and how were they resolved?',
                    'renters_insurance_required' => 'Is renter\'s insurance required?',
                    'utilities_individually_metered' => 'Are utilities individually metered, or shared among units?',
                    'security_features' => 'What security features does the property have (cameras, locks, alarm system)?',
                    'lease_to_own_option' => 'Is the landlord open to lease-to-own or rent credit arrangements?',
                ],
            ],
        ];

        $commercial_faq_questions = [
            'commercial_zoning_classification' => 'What is the zoning classification of the property?',
            'commercial_permitted_use' => 'What is the permitted use of the space?',
            'commercial_lease_structure_type' => 'Is the lease gross, net, modified gross, or triple net (NNN)?',
            'commercial_tenant_operating_expenses' => 'What operating expenses is the tenant responsible for?',
            'commercial_cam_charges' => 'What are the CAM (Common Area Maintenance) charges?',
            'commercial_rent_escalation_clauses' => 'Are there annual rent escalation clauses?',
            'commercial_tenant_improvement_allowance' => 'Is a tenant improvement (TI) allowance available?',
            'commercial_base_rent_per_sqft' => 'What is the base rent per square foot per year?',
            'commercial_signage_rights' => 'Are signage rights included?',
            'commercial_building_access_hours' => 'What are the hours of access to the building or suite?',
            'commercial_shared_conference_lobby' => 'Is there shared conference room or lobby space?',
            'commercial_parking_ratio' => 'What is the parking ratio (spaces per 1,000 sq ft)?',
            'commercial_loading_dock_freight_elevator' => 'Are there loading dock or freight elevator facilities?',
            'commercial_electrical_capacity' => 'What is the electrical capacity of the space (amperage/voltage)?',
            'commercial_exclusivity_rights' => 'Are there exclusivity rights preventing competing businesses in the building?',
            'commercial_sublease_assignment_policy' => 'What is the sublease and assignment policy?',
            'commercial_expansion_option_rofr' => 'Is there an option to expand or right of first refusal on adjacent space?',
            'commercial_lease_renewal_options' => 'What are the lease renewal options and terms?',
            'commercial_cotenancy_kickout_clauses' => 'Are there any co-tenancy clauses or kick-out clauses?',
            'commercial_hvac_responsibility' => 'Who is responsible for HVAC maintenance and replacement?',
        ];
    @endphp

    <div class="alert alert-info mb-4">
        <strong><i class="fa-solid fa-robot me-2"></i>AI Questions / Chatbot Knowledge Base</strong>
        <p class="mb-0 mt-1">These answers are for <strong>internal use only</strong> and will <strong>not appear publicly</strong> on the listing page. They will be used to train the listing chatbot so it can accurately respond to tenant inquiries on your behalf. All questions are optional.</p>
    </div>

    @foreach ($listing_ai_faq_questions as $categoryKey => $category)
        <h5 class="fw-bold mt-4 mb-2 border-bottom pb-1">{{ $category['label'] }}</h5>
        @foreach ($category['questions'] as $questionKey => $questionText)
            <div class="form-group">
                <label class="fw-bold">{{ $questionText }}</label>
                <textarea name="listing_ai_faq[{{ $questionKey }}]" class="form-control" rows="2" placeholder="Optional — enter your answer here">{{ $listing_ai_faq_saved[$questionKey] ?? '' }}</textarea>
            </div>
        @endforeach
    @endforeach

    <div class="commercial_show d-none">
        <h5 class="fw-bold mt-4 mb-2 border-bottom pb-1">Commercial Lease Add-On</h5>
        <p class="text-muted mb-3">The following questions apply to commercial property listings only.</p>

        @foreach ($commercial_faq_questions as $questionKey => $questionText)
            <div class="form-group">
                <label class="fw-bold">{{ $questionText }}</label>
                <textarea name="listing_ai_faq[{{ $questionKey }}]" class="form-control" rows="2" placeholder="Optional — enter your answer here">{{ $listing_ai_faq_saved[$questionKey] ?? '' }}</textarea>
            </div>
        @endforeach
    </div>
</div>

<div class="d-flex justify-content-between form-group mt-4">
    <div>
        <a class="wizard-step-back btn btn-success btn-lg text-600" style="display: none;">Back</a>
    </div>
    <div>
        <a class="wizard-step-next btn btn-success btn-lg text-600" style="display: none;">Next</a>
        <button type="button" class="wizard-step-finish btn btn-success btn-lg text-600"
            style="display: none;">Save</button>
    </div>
</div>
<template class="roomDimensionTemp">
    <input type="text" name="roomDimensions[]" data-type="" class="form-control mt-2"
        data-msg-required="">
</template>
