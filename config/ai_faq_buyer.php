<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Buyer Criteria AI / Chatbot Knowledge Base Questions
    |--------------------------------------------------------------------------
    |
    | Base questions apply to all property types.
    |
    | Property-type add-ons are in the 'addons' key. Each addon lists
    | which property types trigger its display.
    |
    | Key naming convention: stored key = field name after stripping
    | 'listing_ai_faq_' prefix (e.g. 'listing_ai_faq_buyer_active_now' → key is
    | 'buyer_active_now'). Commercial add-on keys use 'com_' prefix.
    | Business Opportunity add-on keys use 'biz_' prefix.
    | Vacant Land add-on keys use 'land_' prefix.
    |
    | Compatible with listing-ai-knowledge-base.blade.php component.
    | Source: buyer_criteria/add.blade.php lines 6607–6922
    |
    */

    'questions' => [

        'Buyer Readiness & Motivation' => [
            'buyer_active_now'        => 'Is the buyer actively looking to purchase now, or is this exploratory?',
            'buyer_timeline'          => 'What is the buyer\'s target timeline to purchase?',
            'buyer_motivation'        => 'What is the buyer\'s primary motivation for purchasing?',
            'buyer_current_situation' => 'What is the buyer\'s current living or business situation (renting, owning, etc.)?',
            'buyer_area_familiarity'  => 'How familiar is the buyer with the target area?',
            'buyer_flexibility'       => 'How flexible is the buyer on location or timing?',
            'buyer_deal_breakers'     => 'What are the buyer\'s absolute deal-breakers?',
            'buyer_lost_deal'         => 'Has the buyer previously lost a deal? If so, what happened?',
        ],

        'Budget' => [
            'buyer_budget_confirmed'      => 'Has the buyer confirmed their total budget?',
            'buyer_down_payment'          => 'How much is the buyer planning to put as a down payment?',
            'buyer_cash_reserves'         => 'Does the buyer have cash reserves remaining after the down payment?',
            'buyer_seller_concessions'    => 'Would the buyer consider seller concessions in lieu of a price reduction?',
            'buyer_monthly_payment_limit' => 'What is the buyer\'s maximum acceptable monthly payment?',
            'buyer_budget_other'          => 'Any other budget-related details?',
        ],

        'Financing' => [
            'buyer_preapproval_status'    => 'Has the buyer been pre-approved or pre-qualified for a loan?',
            'buyer_preapproval_amount'    => 'What is the pre-approval amount?',
            'buyer_lender_name'           => 'Who is the buyer\'s lender or bank?',
            'buyer_loan_type'             => 'What type of loan is the buyer using (e.g., Conventional, FHA, VA, Cash)?',
            'buyer_cash_buyer'            => 'Is the buyer a cash buyer? Do they have proof of funds?',
            'buyer_financing_contingency' => 'Will the buyer include a financing contingency in the offer?',
            'buyer_bridge_loan'           => 'Is the buyer using a bridge loan or any interim financing?',
            'buyer_co_borrower'           => 'Is there a co-borrower on the loan?',
        ],

        'Desired Property Criteria' => [
            'buyer_property_style'     => 'What property style or architecture is preferred?',
            'buyer_age_preference'     => 'Does the buyer prefer new construction, older homes, or no preference?',
            'buyer_must_have_features' => 'What are the buyer\'s absolute must-have property features?',
            'buyer_nice_to_have'       => 'What property features are nice-to-have but not required?',
            'buyer_school_district'    => 'Is a specific school district required?',
            'buyer_hoa_acceptable'     => 'Will the buyer consider HOA communities? What is the maximum monthly HOA fee?',
            'buyer_accessibility'      => 'Does the buyer need any accessibility features?',
            'buyer_home_office'        => 'Does the buyer require a dedicated home office or workspace?',
        ],

        'Offer Terms' => [
            'buyer_earnest_money'          => 'How much earnest money deposit can the buyer offer?',
            'buyer_inspection_contingency' => 'Will the buyer include an inspection contingency?',
            'buyer_appraisal_contingency'  => 'Will the buyer waive or keep the appraisal contingency?',
            'buyer_escalation_clause'      => 'Is the buyer willing to use an escalation clause in a competitive situation?',
            'buyer_as_is'                  => 'Would the buyer consider purchasing a property as-is?',
            'buyer_repairs_limit'          => 'What is the maximum repair credit the buyer would accept instead of repairs?',
            'buyer_leaseback'              => 'Would the buyer allow a seller leaseback after closing?',
            'buyer_multiple_offers'        => 'How does the buyer handle multiple-offer situations?',
            'buyer_offer_expiration'       => 'How long will the buyer keep an offer open?',
        ],

        'Showing & Access' => [
            'buyer_showing_availability' => 'What are the buyer\'s preferred showing hours and availability?',
            'buyer_virtual_tour'         => 'Would the buyer consider making an offer based on a virtual tour only?',
            'buyer_relocation'           => 'Is the buyer relocating from another city, state, or country?',
            'buyer_relocation_timeline'  => 'If relocating, what is the buyer\'s relocation timeline?',
            'buyer_agent_present'        => 'Does the buyer require their agent to be present at all showings?',
            'buyer_travel_distance'      => 'How far is the buyer willing to travel to view properties?',
        ],

        'Closing Readiness' => [
            'buyer_close_timeline'       => 'What is the buyer\'s ideal closing timeline?',
            'buyer_flexible_close'       => 'Is the buyer flexible on the closing date if the seller needs more time?',
            'buyer_simultaneous_close'   => 'Does the buyer need a simultaneous close on a property they are selling?',
            'buyer_post_close_occupancy' => 'Does the buyer need post-close occupancy or early possession?',
            'buyer_move_in_ready'        => 'Does the buyer require a move-in-ready property or can they handle renovations?',
        ],

        'Seller High-Intent' => [
            'buyer_communication_preference' => 'How does the buyer prefer to communicate (phone, email, text)?',
            'buyer_decision_makers'          => 'Who else is involved in the final buying decision?',
            'buyer_ready_to_offer'           => 'Is the buyer ready to make an offer if the right property is found today?',
            'buyer_agent_loyalty'            => 'Is the buyer working exclusively with one agent or interviewing multiple agents?',
            'buyer_prefers_off_market'       => 'Is the buyer open to off-market or pocket listings?',
            'buyer_additional_criteria'      => 'Any additional criteria or preferences the buyer wants sellers to know?',
            'buyer_neighborhood_preferences' => 'Does the buyer have specific neighborhood or community preferences?',
            'buyer_commute_requirements'     => 'Does the buyer have commute distance or public transit requirements?',
            'buyer_noise_tolerance'          => 'What is the buyer\'s tolerance for noise (e.g., near highways, airports, schools)?',
            'buyer_privacy_requirements'     => 'Does the buyer have specific privacy requirements (e.g., fencing, lot size)?',
            'buyer_outdoor_space'            => 'What outdoor space requirements does the buyer have?',
            'buyer_storage_needs'            => 'What storage needs does the buyer have (garage, basement, attic, etc.)?',
            'buyer_parking_needs'            => 'What are the buyer\'s parking requirements?',
            'buyer_view_preference'          => 'Does the buyer have a preference for a specific view (water, mountain, city, etc.)?',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Property-Type Add-On Question Groups
    |--------------------------------------------------------------------------
    |
    | Shown only when listing property_type matches visible_for.
    |
    | Source IDs in buyer_criteria/add.blade.php:
    |   #ai_faq_commercial_section  (display:none, shown via JS for Commercial + Business Opp)
    |   #ai_faq_business_section    (display:none, shown via JS for Business Opportunity)
    |   #ai_faq_vacant_section      (display:none, shown via JS for Vacant Land)
    |
    | NOTE: The legacy blade shows #ai_faq_commercial_section for both
    | "Commercial Property" and "Business Opportunity" property types.
    | Business Opportunity also gets its own dedicated #ai_faq_business_section.
    | This is preserved faithfully here.
    |
    */

    'addons' => [

        'commercial_income' => [
            'label'       => 'Commercial / Income Property Add-On Questions',
            'visible_for' => ['Commercial Property', 'Income Property', 'Business Opportunity'],
            'questions'   => [
                'com_property_use'           => 'What is the intended use of the commercial property?',
                'com_investment_type'        => 'Is this an investment or owner-occupied purchase?',
                'com_cap_rate_target'        => 'What cap rate is the buyer targeting?',
                'com_noi_required'           => 'What minimum Net Operating Income (NOI) is required?',
                'com_occupancy_rate'         => 'What minimum occupancy rate is required?',
                'com_lease_terms'            => 'What lease terms are preferred (NNN, gross, modified gross, etc.)?',
                'com_tenant_type'            => 'What type of tenants are preferred?',
                'com_zoning'                 => 'What zoning classifications are acceptable?',
                'com_buildout_allowance'     => 'Is a tenant buildout allowance expected?',
                'com_due_diligence_period'   => 'How long does the buyer need for due diligence?',
                'com_environmental_concerns' => 'Does the buyer have environmental or Phase I/II study requirements?',
                'com_parking_requirements'   => 'What are the parking requirements for the commercial property?',
                'com_1031_exchange'          => 'Is the buyer doing a 1031 exchange?',
            ],
        ],

        'business_opportunity' => [
            'label'       => 'Business Opportunity Add-On Questions',
            'visible_for' => ['Business Opportunity'],
            'questions'   => [
                'biz_type_seeking'       => 'What type of business is the buyer seeking?',
                'biz_revenue_required'   => 'What minimum annual revenue is required?',
                'biz_profit_required'    => 'What minimum net profit is required?',
                'biz_staff_included'     => 'Will existing staff be retained as part of the purchase?',
                'biz_training_expected'  => 'How much seller training or transition support is expected?',
                'biz_lease_status'       => 'What is the status of the business lease (length remaining, assignable, etc.)?',
                'biz_inventory_included' => 'Is inventory included in the purchase price?',
                'biz_non_compete'        => 'Is a non-compete agreement required from the seller?',
                'biz_sba_financing'      => 'Is the buyer considering SBA financing for the business purchase?',
            ],
        ],

        'vacant_land' => [
            'label'       => 'Vacant Land Add-On Questions',
            'visible_for' => ['Vacant Land'],
            'questions'   => [
                'land_intended_use'        => 'What is the intended use of the vacant land (residential build, agricultural, commercial, etc.)?',
                'land_zoning_required'     => 'What zoning classification is required for the land?',
                'land_utilities_needed'    => 'What utilities need to be available or accessible at the land?',
                'land_soil_testing'        => 'Will the buyer require soil or environmental testing as a contingency?',
                'land_subdivision_plans'   => 'Does the buyer plan to subdivide the land?',
                'land_build_timeline'      => 'What is the buyer\'s expected building or development timeline?',
                'land_survey_required'     => 'Is a current survey required as part of the transaction?',
                'land_access_requirements' => 'What road access or easement requirements does the buyer have?',
                'land_topography'          => 'Are there topography, flood zone, or elevation requirements?',
            ],
        ],

    ],

];
