<?php

/*
|--------------------------------------------------------------------------
| Ask AI — question PRESENTATION (featured subset, display wording)
|--------------------------------------------------------------------------
|
| Read ONLY by App\Support\AskAi\AskAiQuestionPresentation. This file decides how the
| questions a listing can ALREADY answer are shown. It decides nothing about WHICH
| questions exist: AskAiPublicPropertyQuestionService builds that set (visibility,
| property type, ConditionalTerms, Fair Housing), and presentation only orders, subsets
| and rewords it. An id listed here that the listing cannot answer is simply absent —
| a featured question can never be one the full list does not contain.
|
| Three concepts, kept apart:
|   ANSWERABLE  every question the service returns (universal coverage);
|   FEATURED    `featured` below — at most `featured_max` of them, on the listing page and
|               at the top of the modal;
|   ALL         the whole answerable set, searchable, behind "View all questions".
| Leaving a question out of `featured` never removes it from Ask AI.
|
*/

return [

    'featured_max' => 6,

    // Below this many priority hits, the list is topped up from the answerable set in its
    // own order (curated registry questions first) — never from outside it.
    'featured_min' => 4,

    /*
    | role => property-type token (AskAiPropertyTypeResolver) => question ids, most useful
    | first. `default` applies when the listing's type is unknown or has no list.
    */
    'featured' => [
        'seller' => [
            'residential' => ['seller_asking_price', 'seller_bedrooms', 'seller_bathrooms', 'seller_heated_square_feet', 'seller_property_taxes', 'seller_hoa_fee', 'seller_flood_zone', 'seller_roof_type', 'seller_climate_control', 'seller_year_built'],
            'income'      => ['seller_asking_price', 'mls_seller_numberofunitstotal', 'seller_field_unit_mix_summary', 'seller_field_gross_annual_income', 'seller_field_annual_operating_expenses', 'seller_field_building_sqft', 'seller_heated_square_feet', 'seller_occupancy', 'seller_field_total_buildings', 'seller_property_taxes', 'seller_flood_zone'],
            'commercial'  => ['seller_asking_price', 'seller_field_building_sqft', 'seller_heated_square_feet', 'seller_zoning', 'seller_parking', 'mls_seller_parkingfeatures', 'seller_field_front_footage', 'mls_seller_roadfrontagetype', 'seller_property_taxes', 'seller_association_details'],
            'business'    => ['seller_asking_price', 'seller_field_business_type', 'seller_field_real_estate_purchase', 'mls_seller_stellar_businessopportunitywithrealestateyn', 'seller_field_business_assets', 'seller_included_items', 'seller_field_annual_revenue', 'seller_field_employee_count', 'seller_field_business_location_leased', 'seller_field_building_sqft', 'seller_zoning', 'seller_property_taxes'],
            'vacant_land' => ['seller_asking_price', 'seller_total_acreage', 'seller_zoning', 'seller_utilities', 'seller_field_water_available', 'seller_flood_zone', 'seller_field_front_footage', 'mls_seller_roadfrontagetype', 'seller_field_buildable'],
            'default'     => ['seller_asking_price', 'seller_bedrooms', 'seller_bathrooms', 'seller_heated_square_feet', 'seller_property_taxes', 'seller_flood_zone'],
        ],
        'landlord' => [
            'residential' => ['landlord_rent', 'landlord_available_date', 'landlord_bedrooms', 'landlord_bathrooms', 'landlord_heated_square_feet', 'landlord_pets_allowed', 'landlord_lease_terms', 'landlord_field_lease_amount_frequency', 'landlord_field_furnishings'],
            'commercial'  => ['landlord_rent', 'landlord_heated_square_feet', 'landlord_field_minimum_leaseable', 'landlord_zoning', 'landlord_field_commercial_lease_type', 'landlord_field_cam_nnn_additional_rent_charges', 'landlord_parking_terms', 'landlord_available_date', 'landlord_field_signage_rights', 'landlord_property_taxes', 'landlord_flood_zone', 'landlord_total_acreage'],
            'default'     => ['landlord_rent', 'landlord_available_date', 'landlord_bedrooms', 'landlord_bathrooms', 'landlord_heated_square_feet', 'landlord_pets_allowed'],
        ],
        'buyer' => [
            'income'      => ['buyer_budget', 'buyer_search_areas', 'buyer_property_type', 'buyer_meta_unit_size', 'buyer_field_minimum_cap_rate', 'buyer_timeframe'],
            'business'    => ['buyer_budget', 'buyer_search_areas', 'buyer_property_type', 'buyer_meta_real_estate_purchase', 'buyer_square_feet', 'buyer_timeframe'],
            'vacant_land' => ['buyer_budget', 'buyer_search_areas', 'buyer_property_type', 'buyer_acreage', 'buyer_field_flood_zone_tolerance', 'buyer_field_hoa_acceptable', 'buyer_timeframe'],
            'commercial'  => ['buyer_budget', 'buyer_search_areas', 'buyer_property_type', 'buyer_square_feet', 'buyer_field_garage_spaces', 'buyer_timeframe'],
            'default'     => ['buyer_budget', 'buyer_search_areas', 'buyer_property_type', 'buyer_bedrooms', 'buyer_bathrooms', 'buyer_square_feet', 'buyer_timeframe'],
        ],
        'tenant' => [
            'commercial' => ['tenant_max_rent', 'tenant_search_areas', 'tenant_property_type', 'tenant_square_feet', 'tenant_field_intended_business_use', 'tenant_move_in', 'tenant_lease_term', 'tenant_field_cam_nnn_preference'],
            'default'    => ['tenant_max_rent', 'tenant_search_areas', 'tenant_move_in', 'tenant_bedrooms', 'tenant_bathrooms', 'tenant_pets', 'tenant_lease_term', 'tenant_property_type'],
        ],
    ],

    /*
    | Consumer wording for the generic "What does the listing state for <Label>?" question.
    | DISPLAY ONLY: the answer, the question id and its deterministic mapping are unchanged,
    | and the original wording stays searchable. Only labels whose meaning is plain are
    | listed — an obscure field keeps its literal wording rather than an invented reading.
    */
    'property_display' => [
        'Total Sq Ft'                        => 'What is the total square footage?',
        'Total Building Area'                => 'What is the total building area?',
        'Utilities'                          => 'What utilities are available?',
        'Subdivision'                        => 'What subdivision is the property in?',
        'Parking Features'                   => 'What parking features does the property have?',
        'Garage Spaces'                      => 'How many garage spaces are there?',
        'Road Frontage'                      => 'What type of road frontage does the property have?',
        'Road Surface'                       => 'What is the road surface?',
        'Lot Features'                       => 'What are the lot features?',
        'Total Units'                        => 'How many units are there in total?',
        'Number of Buildings'                => 'How many buildings are there?',
        'Total Parcel Count'                 => 'How many parcels are there?',
        'Stories'                            => 'How many stories does the property have?',
        'Full Bathrooms'                     => 'How many full bathrooms are there?',
        'Half Bathrooms'                     => 'How many half bathrooms are there?',
        'Flooring'                           => 'What type of flooring does the property have?',
        'Cooling'                            => 'What type of cooling does the property have?',
        'Heating'                            => 'What type of heating does the property have?',
        'Laundry'                            => 'What laundry features does the property have?',
        'Windows'                            => 'What kind of windows does the property have?',
        'Security Features'                  => 'What security features does the property have?',
        'Interior Features'                  => 'What are the interior features?',
        'Exterior Features'                  => 'What are the exterior features?',
        'Community Features'                 => 'What community features are there?',
        'Pool Features'                      => 'What are the pool features?',
        'Waterfront Features'                => 'What are the waterfront features?',
        'View'                               => 'What is the view?',
        'Water Source'                       => 'What is the water source?',
        'Sewer'                              => 'What sewer service does the property have?',
        'Water Available to Site'            => 'Is water available to the site?',
        'Buildable'                          => 'Is the land buildable?',
        'Front Footage'                      => 'How much front footage is there?',
        'Ceiling Height'                     => 'What is the ceiling height?',
        'Price Per Square Foot'              => 'What is the price per square foot?',
        'Flood Zone Panel'                   => 'What is the flood zone panel number?',
        'Flood Insurance Required'           => 'Is flood insurance required?',
        'Excluded Items'                     => 'What items are excluded from the sale?',
        'Acceptable Financing'               => 'What financing is acceptable?',
        'Currently Occupied By'              => 'Who currently occupies the property?',
        'Gross Scheduled Income'             => 'What is the gross scheduled income?',
        'Net Operating Income'               => 'What is the net operating income?',
        'Annual Operating Expenses'          => 'What are the annual operating expenses?',
        'Cap Rate'                           => 'What is the cap rate?',
        'Rent Roll Available'                => 'Is a rent roll available?',
        'Number of Water Meters'             => 'How many water meters are there?',
        'Existing Lease Type'                => 'What is the existing lease type?',
        'Business Name'                      => 'What is the name of the business?',
        'Business Type'                      => 'What type of business is it?',
        'Year Established'                   => 'When was the business established?',
        'Business & Real Estate Purchase'    => 'Is the real estate included in the sale?',
        'Sold With Real Estate'              => 'Is the business sold with the real estate?',
        'Assignment Fee Amount'              => 'What is the assignment fee?',
        'Fee Includes'                       => 'What do the association fees include?',
        'Amenities'                          => 'What amenities are available?',
        'Pets Allowed'                       => 'Are pets allowed?',
        'Number of Pets Allowed'             => 'How many pets are allowed?',
        'Lease Amount Frequency'             => 'How often is rent paid?',
        'Furnishings'                        => 'Is the rental furnished?',
        'Commercial Lease Type'              => 'What type of commercial lease is it?',
        'CAM / NNN Additional Rent Charges'  => 'What are the CAM / NNN charges?',
        'Signage Rights'                     => 'What signage rights are included?',
        'Leaseable Sq Ft'                    => 'How much space is leasable?',
        'Leasable Area'                      => 'What is the leasable area?',
        'Tenant Pays'                        => 'What does the tenant pay?',
        'Owner Pays'                         => 'What does the owner pay?',
        'Minimum Lease'                      => 'What is the minimum lease?',
    ],

    /*
    | Extra SEARCH-ONLY keywords, question id => words. Discovery, never answering: they are
    | added to a question's search terms in the modal and nowhere else — the answer service,
    | its aliases and the Ask AI runner never read this list. Needed for the owner's
    | knowledge-base questions, which deliberately carry no answering aliases (see
    | AskAiPublicPropertyQuestionService), so "age of roof" can still find the roof question.
    | An id the listing cannot answer is simply absent, so nothing here can list a question.
    */
    'search_keywords' => [
        'kb_seller_roof_age_and_condition'          => ['roof age', 'age of roof', 'how old is the roof', 'roof condition', 'new roof'],
        'kb_seller_hvac_system_age'                 => ['hvac age', 'age of hvac', 'ac age', 'age of ac', 'air conditioner'],
        'kb_seller_water_heater_age_type'           => ['water heater age', 'age of water heater'],
        'kb_seller_average_utility_costs'           => ['utilities', 'utility costs', 'utility bills', 'electric bill'],
        'kb_seller_internet_utility_providers'      => ['utilities', 'internet', 'utility providers'],
        'kb_seller_income_utilities_split'          => ['utilities', 'who pays utilities'],
        'kb_seller_land_utilities_available'        => ['utilities', 'utilities available'],
        'kb_landlord_utilities_individually_metered' => ['utilities', 'meters', 'separately metered'],
        'kb_landlord_internet_providers'            => ['internet', 'internet providers'],
    ],

    /*
    | The same rewording for a buyer's or tenant's criteria ("What does the buyer's listing
    | state for <Label>?"). `{party}` is the role noun: buyer / tenant.
    */
    'criteria_display' => [
        'Carport Needed'                              => 'Does the {party} need a carport?',
        'Garage Needed'                               => 'Does the {party} need a garage?',
        'Garage/Parking Features Needed'              => 'What parking does the {party} need?',
        'Possession Preference'                       => 'When does the {party} want possession?',
        'Possession Details'                          => 'What are the possession details?',
        'Inspection Contingency'                      => 'Does the {party} want an inspection contingency?',
        'Inspection Contingency Period'               => 'How long is the inspection contingency period?',
        'Appraisal Contingency'                       => 'Does the {party} want an appraisal contingency?',
        'Appraisal Contingency Period'                => 'How long is the appraisal contingency period?',
        'Financing Contingency'                       => 'Does the {party} want a financing contingency?',
        'Home Sale Contingency'                       => 'Is the purchase contingent on selling a home?',
        'Home Sale Contingency Period'                => 'How long is the home sale contingency period?',
        'Min. Cap Rate'                               => 'What minimum cap rate does the {party} want?',
        'Acceptable Number of Units'                  => 'How many units does the {party} want?',
        'Flood Zone Preference'                       => 'What flood zones will the {party} consider?',
        'HOA Acceptance'                              => 'Will the {party} accept an HOA?',
        'Intended Business Use'                       => 'What will the {party} use the space for?',
        'Cam Nnn Preference'                          => 'What CAM / NNN arrangement does the {party} prefer?',
        'Business & Real Estate Purchase Requirements' => 'Does the {party} want the real estate included?',
    ],
];
