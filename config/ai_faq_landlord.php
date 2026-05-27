<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Landlord Property AI / Chatbot Knowledge Base Questions
    |--------------------------------------------------------------------------
    |
    | Base questions apply to all property types (Residential and Commercial).
    |
    | The commercial add-on is in the 'addons' key and applies only when
    | property_type is 'Commercial Property'.
    |
    | Each question entry uses the array shape:
    |   key => ['label' => '...', 'placeholder' => '...', 'tooltip' => '...']
    |
    | Compatible with offer-listing/shared/ai-questions-input.blade.php
    |
    */

    'questions' => [

        'Maintenance & Property Condition' => [
            'maintenance_request_response_time' => [
                'label'       => 'How are maintenance requests handled, and what\'s the typical response time?',
                'placeholder' => 'Enter maintenance process (e.g., Submit via online portal, Emergencies within 2 hours, Routine requests within 48 hours)',
                'tooltip'     => 'Lets the AI accurately describe the maintenance process to tenants asking how repairs are handled.',
            ],
            'emergency_maintenance_available' => [
                'label'       => 'Is 24-hour emergency maintenance available?',
                'placeholder' => 'Enter emergency coverage (e.g., Yes — 24/7 emergency line available, After-hours number provided at move-in, On-call vendor handles after-hours calls)',
                'tooltip'     => 'Helps the AI answer one of the most common tenant questions about safety and responsiveness.',
            ],
            'heating_cooling_system' => [
                'label'       => 'What type of heating and cooling system does the property have?',
                'placeholder' => 'Enter HVAC details (e.g., Central A/C and heat system replaced 2021, Window units provided, Mini-split system throughout)',
                'tooltip'     => 'Lets the AI provide accurate details about comfort systems when tenants ask.',
            ],
            'laundry_situation' => [
                'label'       => 'Is there in-unit laundry, or shared laundry facilities on-site?',
                'placeholder' => 'Enter laundry details (e.g., In-unit washer/dryer included, Shared laundry room on ground floor, Hookups only — tenant provides appliances)',
                'tooltip'     => 'Helps the AI answer questions about one of the most frequently searched rental amenities.',
            ],
            'storage_area_included' => [
                'label'       => 'Is there dedicated storage space included with the rental?',
                'placeholder' => 'Enter storage details (e.g., Private storage cage in garage, Attic access included, No additional storage beyond the unit)',
                'tooltip'     => 'Lets the AI accurately describe storage options to tenants evaluating the unit.',
            ],
            'internet_providers' => [
                'label'       => 'Which internet providers are available at this property?',
                'placeholder' => 'Enter internet options (e.g., Xfinity and AT&T Fiber both available, Spectrum only, Fiber infrastructure coming Q3 2025)',
                'tooltip'     => 'Helps the AI answer connectivity questions for tenants who work from home or stream frequently.',
            ],
            'security_features' => [
                'label'       => 'What security features does the property have?',
                'placeholder' => 'Enter security details (e.g., Ring doorbell and deadbolts throughout, Gated community with keypad access, Security cameras in common areas)',
                'tooltip'     => 'Lets the AI describe the property\'s safety features to tenants who prioritize security.',
            ],
            'planned_renovations' => [
                'label'       => 'Are there any planned renovations or construction that could affect tenants?',
                'placeholder' => 'Enter renovation plans (e.g., Exterior painting scheduled for June, No planned work at this time, Roof replacement planned for fall)',
                'tooltip'     => 'Helps the AI accurately inform tenants about any upcoming construction or disruption.',
            ],
        ],

        'Location & Neighborhood' => [
            'neighborhood_character' => [
                'label'       => 'How would you describe the neighborhood feel and who typically lives here?',
                'placeholder' => 'Enter neighborhood description (e.g., Quiet residential street, Mix of families and working professionals, Walkable area near downtown dining)',
                'tooltip'     => 'Gives the AI context to describe the community feel to prospective tenants unfamiliar with the area.',
            ],
            'noise_levels' => [
                'label'       => 'What\'s the noise level like — traffic, nearby businesses, neighboring units?',
                'placeholder' => 'Enter noise level (e.g., Very quiet — backs to a park, Moderate street noise from the nearby road, Shared walls but neighbors are quiet)',
                'tooltip'     => 'Helps the AI give honest answers to tenants who are sensitive to noise from traffic or neighbors.',
            ],
            'nearby_amenities' => [
                'label'       => 'What dining, shopping, parks, or transit options are close by?',
                'placeholder' => 'Enter nearby amenities (e.g., Publix 5 min away, Dog park 2 blocks away, Bus stop at the corner, Multiple restaurants within walking distance)',
                'tooltip'     => 'Lets the AI highlight the lifestyle benefits of this location when tenants ask what\'s nearby.',
            ],
            'guest_parking' => [
                'label'       => 'Is guest parking available on the property or nearby?',
                'placeholder' => 'Enter guest parking details (e.g., Two designated guest spaces in lot, Street parking available on both sides, No dedicated guest parking)',
                'tooltip'     => 'Helps the AI answer one of the most common practical questions prospective tenants ask.',
            ],
            'proximity_to_public_transit' => [
                'label'       => 'How close is the nearest public transit stop?',
                'placeholder' => 'Enter transit proximity (e.g., Bus stop 1 block away, 10-min walk to light rail station, Limited transit options in this area)',
                'tooltip'     => 'Lets the AI accurately answer commute and transit questions for car-free tenants.',
            ],
        ],

        'Lifestyle & Flexibility' => [
            'furnished_or_unfurnished' => [
                'label'       => 'Is the unit available furnished, unfurnished, or is it negotiable?',
                'placeholder' => 'Enter furnishing status (e.g., Unfurnished — tenant provides everything, Fully furnished available at slightly higher rent, Open to partial furnishing)',
                'tooltip'     => 'Helps the AI clearly communicate furnishing options to tenants filtering by this criteria.',
            ],
            'lease_renewal_process' => [
                'label'       => 'What does the lease renewal process look like — is it automatic or does it require negotiation?',
                'placeholder' => 'Enter renewal process (e.g., Renewal offer sent 60 days before expiration, Goes month-to-month after initial term, Automatic renewal with 60-day written notice)',
                'tooltip'     => 'Lets the AI explain renewal terms to tenants evaluating long-term stability.',
            ],
            'notice_to_vacate_required' => [
                'label'       => 'How much notice is required to vacate at the end of the lease?',
                'placeholder' => 'Enter notice requirement (e.g., 60 days written notice required, 30 days for month-to-month tenants, Covered in lease per Florida statute)',
                'tooltip'     => 'Helps the AI accurately answer questions about lease exit requirements.',
            ],
            'preferred_tenant_qualities' => [
                'label'       => 'What qualities make for an ideal tenant in your experience?',
                'placeholder' => 'Enter preferred tenant qualities (e.g., Long-term stable tenants, Good communicators who are respectful of the property, Working professionals or small families)',
                'tooltip'     => 'Gives the AI context to describe the type of tenancy the landlord is looking for.',
            ],
            'subletting_allowed' => [
                'label'       => 'Is subletting or a lease assignment allowed?',
                'placeholder' => 'Enter subletting policy (e.g., Not permitted under any circumstances, Allowed with prior written approval only, Case-by-case with landlord consent)',
                'tooltip'     => 'Lets the AI accurately answer subletting questions before tenants ask during a showing.',
            ],
            'short_term_rentals_allowed' => [
                'label'       => 'Are short-term rentals (Airbnb, VRBO, etc.) permitted?',
                'placeholder' => 'Enter short-term rental policy (e.g., Not permitted per lease terms, Allowed only with HOA approval, Prohibited by local city ordinance)',
                'tooltip'     => 'Helps the AI address questions about platforms like Airbnb that tenants sometimes ask about.',
            ],
            'ev_charging_available' => [
                'label'       => 'Are EV charging stations available, or is there capacity to install one?',
                'placeholder' => 'Enter EV details (e.g., 240V outlet in the garage available, No charging infrastructure on property, Building-wide charging stations planned)',
                'tooltip'     => 'Lets the AI answer questions about EV infrastructure for tenants with electric vehicles.',
            ],
            'bicycle_storage_available' => [
                'label'       => 'Is bicycle storage available on-site?',
                'placeholder' => 'Enter bike storage details (e.g., Locked bike room in the garage, Bikes can be stored inside the unit only, No dedicated bike storage on property)',
                'tooltip'     => 'Helps the AI answer storage questions for tenants who commute or recreate by bicycle.',
            ],
        ],

        'High-Intent Tenant Questions' => [
            'what_makes_property_unique' => [
                'label'       => 'What makes this rental stand out compared to similar properties in the area?',
                'placeholder' => 'Enter what makes this property special (e.g., Private backyard with no rear neighbors, Newly renovated kitchen, Very low average utility costs, Exceptionally quiet street)',
                'tooltip'     => 'Gives the AI material to differentiate this property when tenants ask what makes it stand out.',
            ],
            'pest_or_mold_history' => [
                'label'       => 'Has the property ever had pest or mold issues, and how were they resolved?',
                'placeholder' => 'Enter pest/mold history (e.g., No known history, Treated for roaches in 2022 issue fully resolved, No recurrence since remediation)',
                'tooltip'     => 'Helps the AI answer one of the most common health and safety questions tenants ask.',
            ],
            'utilities_individually_metered' => [
                'label'       => 'Are utilities individually metered per unit, or shared among units?',
                'placeholder' => 'Enter metering details (e.g., Individually metered — tenant pays own electric and water, Master-metered and split equally among units, Electric included landlord pays)',
                'tooltip'     => 'Lets the AI accurately explain how utility costs are split or billed.',
            ],
            'renters_insurance_required' => [
                'label'       => 'Is renter\'s insurance required, and is there a minimum coverage amount?',
                'placeholder' => 'Enter renters insurance policy (e.g., Required — minimum $100K liability coverage, Strongly encouraged but not required, Must be listed as additional interested party)',
                'tooltip'     => 'Helps the AI communicate insurance requirements clearly to tenants before they apply.',
            ],
            'lease_to_own_option' => [
                'label'       => 'Is there any possibility of a lease-to-own or rent credit arrangement?',
                'placeholder' => 'Enter lease-to-own stance (e.g., Open to discussing for the right tenant, Not interested at this time, Rent credit option available for qualified tenants)',
                'tooltip'     => 'Lets the AI accurately answer questions about rent-to-own or lease credit arrangements.',
            ],
            'previous_tenant_feedback' => [
                'label'       => 'What do past tenants typically say about living here?',
                'placeholder' => 'Enter tenant feedback (e.g., Most tenants have renewed multiple times, Praised consistently for quick maintenance response, Quiet building and well-maintained common areas)',
                'tooltip'     => 'Gives the AI social proof to share when tenants ask what it\'s like to live here.',
            ],
            'smoking_policy' => [
                'label'       => 'What is the smoking policy on the premises and outdoor areas?',
                'placeholder' => 'Enter smoking policy (e.g., No smoking anywhere on the property, Outdoor smoking allowed 20 ft from any building entrance, Designated smoking area in parking lot)',
                'tooltip'     => 'Helps the AI provide a clear answer to a question many tenants ask early in their search.',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Property-Type Add-On Question Groups
    |--------------------------------------------------------------------------
    |
    | Shown only when listing property_type matches visible_for.
    |
    */

    'addons' => [

        'commercial' => [
            'label'       => 'Commercial Lease Add-On',
            'visible_for' => ['Commercial Property'],
            'questions'   => [
                'commercial_cam_charges' => [
                    'label'       => 'What are the CAM (Common Area Maintenance) charges, and what do they cover?',
                    'placeholder' => 'Enter CAM details (e.g., ~$4.50/sqft/yr covering landscaping and parking lot, Exterior maintenance and snow removal included, No CAM — gross lease)',
                    'tooltip'     => 'Lets the AI accurately answer questions about total occupancy cost beyond base rent.',
                ],
                'commercial_lease_structure_type' => [
                    'label'       => 'Is the lease structured as gross, net, modified gross, or triple net (NNN)?',
                    'placeholder' => 'Enter lease structure (e.g., Modified gross — tenant pays electric only, NNN — tenant responsible for taxes, insurance, and CAM, Full-service gross lease)',
                    'tooltip'     => 'Helps the AI explain the lease structure and tenant financial responsibilities.',
                ],
                'commercial_tenant_improvement_allowance' => [
                    'label'       => 'Is a tenant improvement (TI) allowance available, and how much?',
                    'placeholder' => 'Enter TI allowance (e.g., Up to $25/sqft TI available for 5+ year leases, As-is no TI offered, TI negotiable based on lease term)',
                    'tooltip'     => 'Lets the AI address questions about buildout assistance and upfit costs.',
                ],
                'commercial_buildout_flexibility' => [
                    'label'       => 'How flexible is the landlord on buildout modifications or customizations?',
                    'placeholder' => 'Enter buildout flexibility (e.g., Open to walls and plumbing changes with approval, Light cosmetic changes only, Shell space ready for full buildout)',
                    'tooltip'     => 'Helps the AI communicate how much customization the landlord will allow.',
                ],
                'commercial_signage_rights' => [
                    'label'       => 'Are building-exterior signage rights included?',
                    'placeholder' => 'Enter signage details (e.g., Monument sign and suite sign included, Directory listing in lobby only, Signage subject to city permit and landlord approval)',
                    'tooltip'     => 'Lets the AI answer visibility and branding questions for commercial tenants.',
                ],
                'commercial_loading_dock_freight_elevator' => [
                    'label'       => 'Are there loading dock or freight elevator facilities available?',
                    'placeholder' => 'Enter loading/freight details (e.g., Grade-level loading door 10x10 ft, Freight elevator rated at 3,000 lbs, No loading dock on this property)',
                    'tooltip'     => 'Helps the AI accurately answer logistics and operations questions.',
                ],
                'commercial_electrical_capacity' => [
                    'label'       => 'What is the electrical capacity of the space (amperage, voltage, 3-phase availability)?',
                    'placeholder' => 'Enter electrical specs (e.g., 200A/240V single-phase, 400A/3-phase available upon request, Standard 100A panel)',
                    'tooltip'     => 'Lets the AI answer questions about power availability for equipment-heavy tenants.',
                ],
                'commercial_parking_ratio' => [
                    'label'       => 'What is the parking ratio, and are spaces reserved or shared?',
                    'placeholder' => 'Enter parking details (e.g., 4 spaces per 1,000 sqft leased, 6 reserved spaces included in lease, Shared surface lot with 50 total spaces)',
                    'tooltip'     => 'Helps the AI address parking questions for businesses with employees and customers.',
                ],
                'commercial_exclusivity_rights' => [
                    'label'       => 'Are exclusivity rights available to prevent a competing business in the building?',
                    'placeholder' => 'Enter exclusivity details (e.g., Exclusivity available for restaurant tenants, No exclusivity in multi-tenant building, Negotiable for anchor tenants)',
                    'tooltip'     => 'Lets the AI communicate competitive protections available to tenants in multi-tenant buildings.',
                ],
                'commercial_expansion_option_rofr' => [
                    'label'       => 'Is there an option to expand or right of first refusal on adjacent space?',
                    'placeholder' => 'Enter expansion rights (e.g., ROFR on the adjacent 1,200 sqft unit, Expansion option negotiable for 3+ year leases, No expansion rights available)',
                    'tooltip'     => 'Helps the AI answer questions about future growth potential within the building.',
                ],
                'commercial_landlord_maintenance_responsibilities' => [
                    'label'       => 'What does the landlord remain responsible for maintaining — roof, HVAC, structure?',
                    'placeholder' => 'Enter landlord responsibilities (e.g., Roof, structure, and parking lot, Tenant responsible for own HVAC maintenance, NNN — tenant handles all maintenance)',
                    'tooltip'     => 'Lets the AI clarify what the landlord vs. tenant is responsible for maintaining.',
                ],
                'commercial_building_access_hours' => [
                    'label'       => 'What are the access hours for the building or suite?',
                    'placeholder' => 'Enter access hours (e.g., 24/7 keycard access for tenants, Business hours 7am–10pm with after-hours by arrangement, Standard M–F 8am–6pm)',
                    'tooltip'     => 'Helps the AI accurately answer questions about after-hours access and operations.',
                ],
            ],
        ],

    ],

];
