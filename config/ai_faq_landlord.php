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
                'placeholder' => 'Enter maintenance process (e.g., Submit Via Online Portal, Emergencies Within 2 Hours, Routine Within 48 Hours)',
            ],
            'emergency_maintenance_available' => [
                'label'       => 'Is 24-hour emergency maintenance available?',
                'placeholder' => 'Enter emergency coverage (e.g., Yes — 24/7 Emergency Line; After-Hours Number Provided at Move-In)',
            ],
            'heating_cooling_system' => [
                'label'       => 'What type of heating and cooling system does the property have?',
                'placeholder' => 'Enter HVAC details (e.g., Central A/C and Heat, System Replaced 2021; Window Units; Mini-Split)',
            ],
            'laundry_situation' => [
                'label'       => 'Is there in-unit laundry, or shared laundry facilities on-site?',
                'placeholder' => 'Enter laundry details (e.g., In-Unit Washer/Dryer Included; Shared Laundry Room on Ground Floor; Hookups Only)',
            ],
            'storage_area_included' => [
                'label'       => 'Is there dedicated storage space included with the rental?',
                'placeholder' => 'Enter storage details (e.g., Private Storage Cage in Garage; Attic Access; No Additional Storage)',
            ],
            'internet_providers' => [
                'label'       => 'Which internet providers are available at this property?',
                'placeholder' => 'Enter internet options (e.g., Xfinity and AT&T Fiber Both Available; Spectrum Only; Fiber Coming Q3 2025)',
            ],
            'security_features' => [
                'label'       => 'What security features does the property have?',
                'placeholder' => 'Enter security details (e.g., Ring Doorbell, Deadbolts, Gated Community; Security Cameras in Common Areas; No Alarm)',
            ],
            'planned_renovations' => [
                'label'       => 'Are there any planned renovations or construction that could affect tenants?',
                'placeholder' => 'Enter renovation plans (e.g., Exterior Painting Scheduled for June; No Planned Work; Roof Replacement in Fall)',
            ],
        ],

        'Location & Neighborhood' => [
            'neighborhood_character' => [
                'label'       => 'How would you describe the neighborhood feel and who typically lives here?',
                'placeholder' => 'Enter neighborhood description (e.g., Quiet Residential Street, Mix of Families and Professionals; Walkable Area Near Downtown)',
            ],
            'noise_levels' => [
                'label'       => 'What\'s the noise level like — traffic, nearby businesses, neighboring units?',
                'placeholder' => 'Enter noise level (e.g., Very Quiet, Backs to Park; Moderate Street Noise From Nearby Road; Shared Walls but Quiet Neighbors)',
            ],
            'nearby_amenities' => [
                'label'       => 'What dining, shopping, parks, or transit options are close by?',
                'placeholder' => 'Enter nearby amenities (e.g., Publix 5 Min, Dog Park 2 Blocks, Bus Stop at Corner, Multiple Restaurants Nearby)',
            ],
            'guest_parking' => [
                'label'       => 'Is guest parking available on the property or nearby?',
                'placeholder' => 'Enter guest parking details (e.g., 2 Designated Guest Spaces in Lot; Street Parking Available; No Dedicated Guest Parking)',
            ],
            'proximity_to_public_transit' => [
                'label'       => 'How close is the nearest public transit stop?',
                'placeholder' => 'Enter transit proximity (e.g., Bus Stop 1 Block Away; 10-Min Walk to Light Rail; Limited Transit in This Area)',
            ],
        ],

        'Lifestyle & Flexibility' => [
            'furnished_or_unfurnished' => [
                'label'       => 'Is the unit available furnished, unfurnished, or is it negotiable?',
                'placeholder' => 'Enter furnishing status (e.g., Unfurnished; Fully Furnished Available at Slightly Higher Rent; Open to Partial)',
            ],
            'lease_renewal_process' => [
                'label'       => 'What does the lease renewal process look like — is it automatic or does it require negotiation?',
                'placeholder' => 'Enter renewal process (e.g., Offer Renewal 60 Days Before Expiration; Month-to-Month After Initial Term; Automatic Renewal With 60-Day Notice)',
            ],
            'notice_to_vacate_required' => [
                'label'       => 'How much notice is required to vacate at the end of the lease?',
                'placeholder' => 'Enter notice requirement (e.g., 60 Days Written Notice; 30 Days for Month-to-Month)',
            ],
            'preferred_tenant_qualities' => [
                'label'       => 'What qualities make for an ideal tenant in your experience?',
                'placeholder' => 'Enter preferred tenant qualities (e.g., Long-Term Stable Tenants, Good Communicators, Respectful of Property; Professional or Working Couple)',
            ],
            'subletting_allowed' => [
                'label'       => 'Is subletting or a lease assignment allowed?',
                'placeholder' => 'Enter subletting policy (e.g., Not Permitted; Allowed With Prior Written Approval Only)',
            ],
            'short_term_rentals_allowed' => [
                'label'       => 'Are short-term rentals (Airbnb, VRBO, etc.) permitted?',
                'placeholder' => 'Enter short-term rental policy (e.g., Not Permitted Per Lease; Allowed With HOA Approval; Prohibited by City Ordinance)',
            ],
            'ev_charging_available' => [
                'label'       => 'Are EV charging stations available, or is there capacity to install one?',
                'placeholder' => 'Enter EV details (e.g., 240V Outlet in Garage Available; No Charging on Property; Building-Wide Charging Planned)',
            ],
            'bicycle_storage_available' => [
                'label'       => 'Is bicycle storage available on-site?',
                'placeholder' => 'Enter bike storage details (e.g., Locked Bike Room in Garage; Can Store in Unit Only; No Dedicated Bike Storage)',
            ],
        ],

        'High-Intent Tenant Questions' => [
            'what_makes_property_unique' => [
                'label'       => 'What makes this rental stand out compared to similar properties in the area?',
                'placeholder' => 'Enter what makes this property special (e.g., Private Backyard, Newly Renovated Kitchen, Very Low Utility Costs, Quiet Street)',
            ],
            'pest_or_mold_history' => [
                'label'       => 'Has the property ever had pest or mold issues, and how were they resolved?',
                'placeholder' => 'Enter pest/mold history (e.g., No Known History; Treated for Roaches in 2022, Issue Resolved; No Recurrence)',
            ],
            'utilities_individually_metered' => [
                'label'       => 'Are utilities individually metered per unit, or shared among units?',
                'placeholder' => 'Enter metering details (e.g., Individually Metered — Tenant Pays Own Electric and Water; Master-Metered, Split Equally)',
            ],
            'renters_insurance_required' => [
                'label'       => 'Is renter\'s insurance required, and is there a minimum coverage amount?',
                'placeholder' => 'Enter renters insurance policy (e.g., Required — Minimum $100K Liability; Strongly Encouraged but Not Required)',
            ],
            'lease_to_own_option' => [
                'label'       => 'Is there any possibility of a lease-to-own or rent credit arrangement?',
                'placeholder' => 'Enter lease-to-own stance (e.g., Open to Discussing for the Right Tenant; Not Interested at This Time)',
            ],
            'previous_tenant_feedback' => [
                'label'       => 'What do past tenants typically say about living here?',
                'placeholder' => 'Enter tenant feedback (e.g., Most Tenants Have Renewed Multiple Times; Praised for Quick Maintenance Response; Quiet and Well-Maintained)',
            ],
            'smoking_policy' => [
                'label'       => 'What is the smoking policy on the premises and outdoor areas?',
                'placeholder' => 'Enter smoking policy (e.g., No Smoking Anywhere on Property; Outdoor Smoking Allowed 20 Ft From Building)',
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
                    'placeholder' => 'Enter CAM details (e.g., ~$4.50/sqft/yr Covering Landscaping, Parking Lot, Exterior Maintenance)',
                ],
                'commercial_lease_structure_type' => [
                    'label'       => 'Is the lease structured as gross, net, modified gross, or triple net (NNN)?',
                    'placeholder' => 'Enter lease structure (e.g., Modified Gross — Tenant Pays Electric Only; NNN — Tenant Responsible for Taxes, Insurance, CAM)',
                ],
                'commercial_tenant_improvement_allowance' => [
                    'label'       => 'Is a tenant improvement (TI) allowance available, and how much?',
                    'placeholder' => 'Enter TI allowance (e.g., Up to $25/sqft TI Available for 5+ Year Leases; As-Is, No TI Offered)',
                ],
                'commercial_buildout_flexibility' => [
                    'label'       => 'How flexible is the landlord on buildout modifications or customizations?',
                    'placeholder' => 'Enter buildout flexibility (e.g., Open to Walls, Plumbing Changes With Approval; Light Cosmetic Changes Only; Shell Space Ready for Full Buildout)',
                ],
                'commercial_signage_rights' => [
                    'label'       => 'Are building-exterior signage rights included?',
                    'placeholder' => 'Enter signage details (e.g., Monument Sign and Suite Sign Included; Directory Listing Only; Signage Subject to City Permit and Landlord Approval)',
                ],
                'commercial_loading_dock_freight_elevator' => [
                    'label'       => 'Are there loading dock or freight elevator facilities available?',
                    'placeholder' => 'Enter loading/freight details (e.g., Grade-Level Loading Door, 10x10 Ft; Freight Elevator Capacity 3,000 Lbs; No Loading Dock)',
                ],
                'commercial_electrical_capacity' => [
                    'label'       => 'What is the electrical capacity of the space (amperage, voltage, 3-phase availability)?',
                    'placeholder' => 'Enter electrical specs (e.g., 200A/240V Single-Phase; 400A/3-Phase Available; Standard 100A)',
                ],
                'commercial_parking_ratio' => [
                    'label'       => 'What is the parking ratio, and are spaces reserved or shared?',
                    'placeholder' => 'Enter parking details (e.g., 4 Spaces per 1,000 Sqft; 6 Reserved Spaces Included; Shared Lot With 50 Spaces)',
                ],
                'commercial_exclusivity_rights' => [
                    'label'       => 'Are exclusivity rights available to prevent a competing business in the building?',
                    'placeholder' => 'Enter exclusivity details (e.g., Exclusivity Available for Restaurant Tenants; No Exclusivity in Multi-Tenant Building)',
                ],
                'commercial_expansion_option_rofr' => [
                    'label'       => 'Is there an option to expand or right of first refusal on adjacent space?',
                    'placeholder' => 'Enter expansion rights (e.g., ROFR on Adjacent 1,200 Sqft Unit; Expansion Option Negotiable for 3+ Year Leases)',
                ],
                'commercial_landlord_maintenance_responsibilities' => [
                    'label'       => 'What does the landlord remain responsible for maintaining — roof, HVAC, structure?',
                    'placeholder' => 'Enter landlord responsibilities (e.g., Roof, Structure, and Parking Lot; Tenant Responsible for HVAC Maintenance; NNN — Tenant Handles All)',
                ],
                'commercial_building_access_hours' => [
                    'label'       => 'What are the access hours for the building or suite?',
                    'placeholder' => 'Enter access hours (e.g., 24/7 Keycard Access; Business Hours 7am–10pm, After-Hours by Arrangement)',
                ],
            ],
        ],

    ],

];
