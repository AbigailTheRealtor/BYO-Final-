<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Seller Property AI / Chatbot Knowledge Base Questions
    |--------------------------------------------------------------------------
    |
    | Base questions apply to all property types (Residential, Income,
    | Commercial, Business Opportunity, Vacant Land).
    |
    | Property-type add-ons are in the 'addons' key. Each addon lists
    | which property types trigger its display.
    |
    | Compatible with listing-ai-knowledge-base.blade.php component.
    | Source: seller_property/add.blade.php Step 94 (lines 8394–8803)
    |
    */

    'questions' => [

        'Pricing, Costs & Financing' => [
            'asking_price_negotiation'    => 'Is the asking price negotiable?',
            'recent_comparable_sales'     => 'What do recent comparable sales in the area indicate about this pricing?',
            'price_per_sqft'              => 'What is the price per square foot?',
            'seller_concessions_offered'  => 'Are you offering any seller concessions or credits?',
            'hoa_fees_amount'             => 'What are the HOA/association fees?',
            'annual_property_taxes'       => 'What are the annual property taxes?',
            'special_assessments_pending' => 'Are there any pending special assessments?',
            'estimated_closing_costs'     => 'What are the estimated closing costs?',
            'average_utility_costs'       => 'What are the average monthly utility costs?',
            'property_insurance_estimate' => 'What does property insurance typically cost?',
            'price_reduction_history'     => 'Has the listing price been reduced, and if so, why?',
            'seller_paid_closing_costs'   => 'Will the seller contribute to buyer closing costs?',
        ],

        'Financing & Pre-Approval' => [
            'seller_financing_offered'       => 'Is seller financing available?',
            'existing_loan_assumable'        => 'Can the existing mortgage be assumed?',
            'preferred_financing_types'      => 'What types of financing do you prefer?',
            'cash_offer_discount'            => 'Do you offer a discount for cash offers?',
            'pre_approval_required'          => 'Is a mortgage pre-approval letter required with offers?',
            'minimum_down_payment'           => 'What is the minimum down payment you will consider?',
            'fha_va_loans_accepted'          => 'Will you accept FHA or VA loan offers?',
            'financing_contingency_accepted' => 'Will you accept offers with a financing contingency?',
        ],

        'Property Details' => [
            'recent_renovations_list'         => 'What renovations or improvements have been made, and when?',
            'roof_age_and_condition'          => 'How old is the roof and what condition is it in?',
            'hvac_system_age'                 => 'How old is the HVAC system, and when was it last serviced?',
            'water_heater_age_type'           => 'How old is the water heater, and what type is it?',
            'appliances_included_list'        => 'Which appliances are included in the sale?',
            'foundation_type_and_issues'      => 'What type of foundation does the property have, and are there any known issues?',
            'permits_for_renovations'         => 'Were all renovations and additions completed with proper permits?',
            'known_defects_issues'            => 'Are there any known defects, issues, or repairs needed?',
            'pest_termite_history'            => 'Has the property ever had pest or termite issues?',
            'flood_damage_history'            => 'Has the property ever flooded or experienced water damage?',
            'mold_issues_history'             => 'Has the property ever had mold issues?',
            'square_footage_breakdown'        => 'What is the breakdown of heated versus total square footage?',
            'parking_arrangements'            => 'What are the parking arrangements (garage, driveway, street)?',
            'storage_space_available'         => 'What storage space is available?',
            'internet_utility_providers'      => 'What internet and utility providers serve this property?',
        ],

        'Offer & Negotiation' => [
            'offer_review_timeline'        => 'When will offers be reviewed?',
            'multiple_offer_strategy'      => 'How will multiple offers be handled?',
            'escalation_clause_accepted'   => 'Will you consider escalation clauses in offers?',
            'expected_earnest_money'       => 'What amount of earnest money is expected?',
            'preferred_contingencies'      => 'What contingencies are you comfortable accepting?',
            'as_is_condition'              => 'Is this being sold as-is, or will you make repairs?',
            'seller_credit_availability'   => 'Are any seller credits available for repairs or upgrades?',
            'closing_timeline_flexibility' => 'How flexible are you on the closing date?',
            'items_excluded_from_sale'     => 'Are there any fixtures, appliances, or items excluded from the sale?',
            'backup_offer_accepted'        => 'Will you consider backup offers?',
        ],

        'Inspections & Due Diligence' => [
            'buyer_inspection_allowed'          => 'Will you allow a buyer\'s inspection?',
            'existing_inspection_reports'       => 'Are any existing inspection reports available to buyers?',
            'repair_credits_after_inspection'   => 'Will you offer repair credits based on the inspection?',
            'pre_listing_inspection_done'       => 'Has a pre-listing inspection been completed?',
            'inspection_period_length'          => 'What inspection period length do you prefer?',
            'recent_survey_available'           => 'Is a recent survey available?',
            'title_search_completed'            => 'Has a title search been completed?',
            'title_insurance_available'         => 'What type of title insurance is available?',
            'environmental_concerns'            => 'Are there any known environmental concerns or restrictions?',
            'due_diligence_documents_available' => 'What due diligence documents will you provide?',
        ],

        'Closing & Possession' => [
            'preferred_closing_date'        => 'What is your preferred closing date?',
            'earliest_possible_closing'     => 'What is the earliest possible closing date?',
            'possession_date_after_closing' => 'When can the buyer take possession after closing?',
            'seller_leaseback_option'       => 'Would you consider a seller leaseback arrangement?',
            'preferred_title_company'       => 'Do you have a preferred closing attorney or title company?',
            'moving_timeline'               => 'What is your moving timeline?',
            'property_occupancy_status'     => 'Is the property currently occupied, vacant, or rented?',
            'existing_tenant_leases'        => 'Are there any existing tenant leases that will transfer?',
            'key_access_at_closing'         => 'How will keys and access codes be handled at closing?',
            'closing_extension_flexibility' => 'Are you flexible on closing date extensions if needed?',
        ],

        'Location & Neighborhood' => [
            'school_district_name'         => 'What school district serves this property?',
            'nearby_amenities_description' => 'What amenities, shops, and services are nearby?',
            'neighborhood_restrictions'    => 'Are there deed restrictions or neighborhood rules buyers should know?',
            'traffic_or_noise_concerns'    => 'Are there any traffic, noise, or nuisance concerns nearby?',
            'flood_zone_information'       => 'Is the property in a FEMA flood zone, and what is the flood zone code?',
            'planned_nearby_development'   => 'Are there any planned developments or road projects nearby?',
            'neighborhood_character'       => 'How would you describe the neighborhood and community?',
            'commute_options_access'       => 'What are the typical commute options and travel times to major employment centers?',
            'public_transportation_access' => 'Is public transportation accessible from this location?',
            'hoa_community_highlights'     => 'What do you value most about living in this community?',
        ],

        'High-Intent Buyer Questions' => [
            'seller_motivation_for_selling' => 'What is your motivation for selling?',
            'current_days_on_market'        => 'How long has the property been on the market?',
            'prior_offer_activity'          => 'Have there been any prior offers, and if so, why did they fall through?',
            'ideal_buyer_profile'           => 'Do you have a preference for the type of buyer?',
            'move_in_ready_status'          => 'Is the property move-in ready, or does it need work?',
            'staged_or_furnished_info'      => 'Is the property currently staged or furnished?',
            'seller_disclosure_highlights'  => 'What are the key items on the seller disclosure?',
            'unique_selling_points'         => 'What are the unique selling points of this property?',
            'seller_favorite_features'      => 'What features or aspects of this property will you miss most?',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Property-Type Add-On Question Groups
    |--------------------------------------------------------------------------
    |
    | Shown only when listing property_type matches visible_for.
    |
    | Source CSS classes in seller_property/add.blade.php:
    |   .ai-faq-commercial-income-section  (d-none, shown via JS)
    |   .ai-faq-business-section           (d-none, shown via JS)
    |   .ai-faq-vacant-section             (d-none, shown via JS)
    |
    */

    'addons' => [

        'commercial_income' => [
            'label'       => 'Commercial / Income Property (5+ Units) Questions',
            'visible_for' => ['Commercial Property', 'Income Property'],
            'questions'   => [
                'annual_net_operating_income'      => 'What is the current annual net operating income (NOI)?',
                'current_cap_rate'                 => 'What is the current capitalization rate?',
                'existing_tenant_lease_terms'      => 'What are the existing tenant lease terms and expiration dates?',
                'current_occupancy_rate'           => 'What is the current occupancy rate?',
                'annual_operating_expenses_detail' => 'What are the detailed annual operating expenses?',
            ],
        ],

        'business_opportunity' => [
            'label'       => 'Business Opportunity Questions',
            'visible_for' => ['Business Opportunity'],
            'questions'   => [
                'annual_business_revenue'     => 'What is the current annual gross revenue?',
                'business_reason_for_selling' => 'Why is the business being sold?',
                'business_employee_count'     => 'How many employees does the business have?',
            ],
        ],

        'vacant_land' => [
            'label'       => 'Vacant Land Questions',
            'visible_for' => ['Vacant Land'],
            'questions'   => [
                'land_utilities_availability' => 'What utilities are available or accessible on the land?',
                'land_zoning_permitted_uses'  => 'What is the current zoning designation and what uses are permitted?',
            ],
        ],

    ],

];
