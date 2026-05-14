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
    |   key => ['label' => '...', 'placeholder' => '...']
    |
    | Compatible with offer-listing/shared/ai-questions-input.blade.php
    |
    */

    'questions' => [

        'Maintenance & Property Condition' => [
            'maintenance_request_response_time' => [
                'label'       => 'How are maintenance requests handled, and what\'s the typical response time?',
                'placeholder' => 'Enter maintenance process (e.g., submit via online portal, emergencies within 2 hours, routine within 48 hours)',
            ],
            'emergency_maintenance_available' => [
                'label'       => 'Is 24-hour emergency maintenance available?',
                'placeholder' => 'Enter emergency coverage (e.g., yes — 24/7 emergency line; after-hours number provided at move-in)',
            ],
            'heating_cooling_system' => [
                'label'       => 'What type of heating and cooling system does the property have?',
                'placeholder' => 'Enter HVAC details (e.g., central A/C and heat, system replaced 2021; window units; mini-split)',
            ],
            'laundry_situation' => [
                'label'       => 'Is there in-unit laundry, or shared laundry facilities on-site?',
                'placeholder' => 'Enter laundry details (e.g., in-unit washer/dryer included; shared laundry room on ground floor; hookups only)',
            ],
            'storage_area_included' => [
                'label'       => 'Is there dedicated storage space included with the rental?',
                'placeholder' => 'Enter storage details (e.g., private storage cage in garage; attic access; no additional storage)',
            ],
            'internet_providers' => [
                'label'       => 'Which internet providers are available at this property?',
                'placeholder' => 'Enter internet options (e.g., Xfinity and AT&T Fiber both available; Spectrum only; fiber coming Q3 2025)',
            ],
            'security_features' => [
                'label'       => 'What security features does the property have?',
                'placeholder' => 'Enter security details (e.g., ring doorbell, deadbolts, gated community; security cameras in common areas; no alarm)',
            ],
            'planned_renovations' => [
                'label'       => 'Are there any planned renovations or construction that could affect tenants?',
                'placeholder' => 'Enter renovation plans (e.g., exterior painting scheduled for June; no planned work; roof replacement in fall)',
            ],
        ],

        'Location & Neighborhood' => [
            'neighborhood_character' => [
                'label'       => 'How would you describe the neighborhood feel and who typically lives here?',
                'placeholder' => 'Enter neighborhood description (e.g., quiet residential street, mix of families and professionals; walkable area near downtown)',
            ],
            'noise_levels' => [
                'label'       => 'What\'s the noise level like — traffic, nearby businesses, neighboring units?',
                'placeholder' => 'Enter noise level (e.g., very quiet, backs to park; moderate street noise from nearby road; shared walls but quiet neighbors)',
            ],
            'nearby_amenities' => [
                'label'       => 'What dining, shopping, parks, or transit options are close by?',
                'placeholder' => 'Enter nearby amenities (e.g., Publix 5 min, dog park 2 blocks, bus stop at corner, multiple restaurants nearby)',
            ],
            'guest_parking' => [
                'label'       => 'Is guest parking available on the property or nearby?',
                'placeholder' => 'Enter guest parking details (e.g., 2 designated guest spaces in lot; street parking available; no dedicated guest parking)',
            ],
            'proximity_to_public_transit' => [
                'label'       => 'How close is the nearest public transit stop?',
                'placeholder' => 'Enter transit proximity (e.g., bus stop 1 block away; 10-min walk to light rail; limited transit in this area)',
            ],
        ],

        'Lifestyle & Flexibility' => [
            'furnished_or_unfurnished' => [
                'label'       => 'Is the unit available furnished, unfurnished, or is it negotiable?',
                'placeholder' => 'Enter furnishing status (e.g., unfurnished; fully furnished available at slightly higher rent; open to partial)',
            ],
            'lease_renewal_process' => [
                'label'       => 'What does the lease renewal process look like — is it automatic or does it require negotiation?',
                'placeholder' => 'Enter renewal process (e.g., offer renewal 60 days before expiration; month-to-month after initial term; automatic renewal with 60-day notice)',
            ],
            'notice_to_vacate_required' => [
                'label'       => 'How much notice is required to vacate at the end of the lease?',
                'placeholder' => 'Enter notice requirement (e.g., 60 days written notice; 30 days for month-to-month)',
            ],
            'preferred_tenant_qualities' => [
                'label'       => 'What qualities make for an ideal tenant in your experience?',
                'placeholder' => 'Enter preferred tenant qualities (e.g., long-term stable tenants, good communicators, respectful of property; professional or working couple)',
            ],
            'subletting_allowed' => [
                'label'       => 'Is subletting or a lease assignment allowed?',
                'placeholder' => 'Enter subletting policy (e.g., not permitted; allowed with prior written approval only)',
            ],
            'short_term_rentals_allowed' => [
                'label'       => 'Are short-term rentals (Airbnb, VRBO, etc.) permitted?',
                'placeholder' => 'Enter short-term rental policy (e.g., not permitted per lease; allowed with HOA approval; prohibited by city ordinance)',
            ],
            'ev_charging_available' => [
                'label'       => 'Are EV charging stations available, or is there capacity to install one?',
                'placeholder' => 'Enter EV details (e.g., 240V outlet in garage available; no charging on property; building-wide charging planned)',
            ],
            'bicycle_storage_available' => [
                'label'       => 'Is bicycle storage available on-site?',
                'placeholder' => 'Enter bike storage details (e.g., locked bike room in garage; can store in unit only; no dedicated bike storage)',
            ],
        ],

        'High-Intent Tenant Questions' => [
            'what_makes_property_unique' => [
                'label'       => 'What makes this rental stand out compared to similar properties in the area?',
                'placeholder' => 'Enter what makes this property special (e.g., private backyard, newly renovated kitchen, very low utility costs, quiet street)',
            ],
            'pest_or_mold_history' => [
                'label'       => 'Has the property ever had pest or mold issues, and how were they resolved?',
                'placeholder' => 'Enter pest/mold history (e.g., no known history; treated for roaches in 2022, issue resolved; no recurrence)',
            ],
            'utilities_individually_metered' => [
                'label'       => 'Are utilities individually metered per unit, or shared among units?',
                'placeholder' => 'Enter metering details (e.g., individually metered — tenant pays own electric and water; master-metered, split equally)',
            ],
            'renters_insurance_required' => [
                'label'       => 'Is renter\'s insurance required, and is there a minimum coverage amount?',
                'placeholder' => 'Enter renters insurance policy (e.g., required — minimum $100K liability; strongly encouraged but not required)',
            ],
            'lease_to_own_option' => [
                'label'       => 'Is there any possibility of a lease-to-own or rent credit arrangement?',
                'placeholder' => 'Enter lease-to-own stance (e.g., open to discussing for the right tenant; not interested at this time)',
            ],
            'previous_tenant_feedback' => [
                'label'       => 'What do past tenants typically say about living here?',
                'placeholder' => 'Enter tenant feedback (e.g., most tenants have renewed multiple times; praised for quick maintenance response; quiet and well-maintained)',
            ],
            'smoking_policy' => [
                'label'       => 'What is the smoking policy on the premises and outdoor areas?',
                'placeholder' => 'Enter smoking policy (e.g., no smoking anywhere on property; outdoor smoking allowed 20 ft from building)',
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
                    'placeholder' => 'Enter CAM details (e.g., ~$4.50/sqft/yr covering landscaping, parking lot, exterior maintenance)',
                ],
                'commercial_lease_structure_type' => [
                    'label'       => 'Is the lease structured as gross, net, modified gross, or triple net (NNN)?',
                    'placeholder' => 'Enter lease structure (e.g., modified gross — tenant pays electric only; NNN — tenant responsible for taxes, insurance, CAM)',
                ],
                'commercial_tenant_improvement_allowance' => [
                    'label'       => 'Is a tenant improvement (TI) allowance available, and how much?',
                    'placeholder' => 'Enter TI allowance (e.g., up to $25/sqft TI available for 5+ year leases; as-is, no TI offered)',
                ],
                'commercial_buildout_flexibility' => [
                    'label'       => 'How flexible is the landlord on buildout modifications or customizations?',
                    'placeholder' => 'Enter buildout flexibility (e.g., open to walls, plumbing changes with approval; light cosmetic changes only; shell space ready for full buildout)',
                ],
                'commercial_signage_rights' => [
                    'label'       => 'Are building-exterior signage rights included?',
                    'placeholder' => 'Enter signage details (e.g., monument sign and suite sign included; directory listing only; signage subject to city permit and landlord approval)',
                ],
                'commercial_loading_dock_freight_elevator' => [
                    'label'       => 'Are there loading dock or freight elevator facilities available?',
                    'placeholder' => 'Enter loading/freight details (e.g., grade-level loading door, 10x10 ft; freight elevator capacity 3,000 lbs; no loading dock)',
                ],
                'commercial_electrical_capacity' => [
                    'label'       => 'What is the electrical capacity of the space (amperage, voltage, 3-phase availability)?',
                    'placeholder' => 'Enter electrical specs (e.g., 200A/240V single-phase; 400A/3-phase available; standard 100A)',
                ],
                'commercial_parking_ratio' => [
                    'label'       => 'What is the parking ratio, and are spaces reserved or shared?',
                    'placeholder' => 'Enter parking details (e.g., 4 spaces per 1,000 sqft; 6 reserved spaces included; shared lot with 50 spaces)',
                ],
                'commercial_exclusivity_rights' => [
                    'label'       => 'Are exclusivity rights available to prevent a competing business in the building?',
                    'placeholder' => 'Enter exclusivity details (e.g., exclusivity available for restaurant tenants; no exclusivity in multi-tenant building)',
                ],
                'commercial_expansion_option_rofr' => [
                    'label'       => 'Is there an option to expand or right of first refusal on adjacent space?',
                    'placeholder' => 'Enter expansion rights (e.g., ROFR on adjacent 1,200 sqft unit; expansion option negotiable for 3+ year leases)',
                ],
                'commercial_landlord_maintenance_responsibilities' => [
                    'label'       => 'What does the landlord remain responsible for maintaining — roof, HVAC, structure?',
                    'placeholder' => 'Enter landlord responsibilities (e.g., roof, structure, and parking lot; tenant responsible for HVAC maintenance; NNN — tenant handles all)',
                ],
                'commercial_building_access_hours' => [
                    'label'       => 'What are the access hours for the building or suite?',
                    'placeholder' => 'Enter access hours (e.g., 24/7 keycard access; business hours 7am–10pm, after-hours by arrangement)',
                ],
            ],
        ],

    ],

];
