<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Landlord Property AI / Chatbot Knowledge Base Questions
    |--------------------------------------------------------------------------
    |
    | Two-axis architecture (docs/ask-ai-kb-replacement-spec.md Part A): each
    | knowledge base = 'universal' + the group matching the rental type, resolved
    | via 'gating'. Landlord listings are 'Residential Property' or
    | 'Commercial Property'. Residential questions never leak into Commercial.
    |
    | Audience: tenants / tenant agents asking about the rental.
    | Compliance: educational/neutral/factual; no advice, steering, or superlatives.
    | Entry shape: key => [label, placeholder, tooltip, category_type, source].
    | Compatible with offer-listing/shared/ai-questions-input.blade.php
    |
    */

    'gating' => [
        'Residential Property' => ['universal', 'residential'],
        'Commercial Property'  => ['universal', 'commercial'],
    ],

    'groups' => [

        // =====================================================================
        // UNIVERSAL — both rental types
        // =====================================================================
        'universal' => [
            'Tenancy & Maintenance' => [
                'maintenance_request_response_time' => [
                    'label'         => 'How are maintenance requests handled, including emergencies and response times?',
                    'placeholder'   => 'Enter maintenance process (e.g., Online portal, Emergencies within 2 hours, Routine within 48 hours)',
                    'tooltip'       => 'Lets the AI describe the full maintenance process, including emergency response.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'planned_renovations' => [
                    'label'         => 'Are there planned renovations or construction that could affect tenants?',
                    'placeholder'   => 'Enter renovation plans (e.g., Exterior painting in June, No planned work, Roof replacement in fall)',
                    'tooltip'       => 'Helps the AI inform tenants about upcoming construction or disruption.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'notice_to_vacate_required' => [
                    'label'         => 'How much notice is required to vacate at lease end?',
                    'placeholder'   => 'Enter notice requirement (e.g., 60 days written notice, 30 days for month-to-month, Per lease and state statute)',
                    'tooltip'       => 'Helps the AI answer questions about lease-exit requirements.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'lease_to_own_option' => [
                    'label'         => 'Is a lease-to-own or rent-credit arrangement possible?',
                    'placeholder'   => 'Enter stance (e.g., Open to discussing for a qualified tenant, Not at this time, Rent-credit option available)',
                    'tooltip'       => 'Factual disclosure of the landlord\'s stance only.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
            ],
            'Rental Insights' => [
                'nearby_amenities' => [
                    'label'         => 'What location features are nearby?',
                    'placeholder'   => 'Enter nearby features (e.g., Publix 5 min away, Dog park 2 blocks away, Bus stop at the corner)',
                    'tooltip'       => 'Lets the AI describe nearby objective features; no steering.',
                    'category_type' => 'insight',
                    'source'        => 'LocDNA',
                ],
                'what_makes_property_unique' => [
                    'label'         => 'What makes this rental stand out?',
                    'placeholder'   => 'Enter notable features (e.g., Private backyard, Newly renovated kitchen, Quiet street)',
                    'tooltip'       => 'Objective, listing-sourced features only; no superlatives or comparisons.',
                    'category_type' => 'insight',
                    'source'        => 'PropDNA+Desc',
                ],
                'rental_lifestyle_support' => [
                    'label'         => 'What lifestyle does this rental appear to support?',
                    'placeholder'   => 'Enter feature-based notes (e.g., Open layout suits entertaining, Near trails for outdoor recreation)',
                    'tooltip'       => 'The AI describes lifestyle by features and nearby amenities only — never by the type of person.',
                    'category_type' => 'insight',
                    'source'        => 'PropDNA+LocDNA',
                ],
                'rental_disclosed_information' => [
                    'label'         => 'What has been disclosed about this rental?',
                    'placeholder'   => 'Optional — the AI summarizes disclosed details about the rental',
                    'tooltip'       => 'Summarizes disclosed rental facts in one place.',
                    'category_type' => 'insight',
                    'source'        => 'Field+KB',
                ],
            ],
        ],

        // =====================================================================
        // RESIDENTIAL RENTAL
        // =====================================================================
        'residential' => [
            'Systems & Amenities' => [
                'internet_providers' => [
                    'label'         => 'Which internet providers and speeds are available?',
                    'placeholder'   => 'Enter internet options (e.g., Xfinity and AT&T Fiber up to 1 Gbps, Spectrum only, Fiber coming Q3)',
                    'tooltip'       => 'Helps the AI answer connectivity questions for tenants who work from home or stream.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'furnished_or_unfurnished' => [
                    'label'         => 'Is the unit furnished, unfurnished, or negotiable?',
                    'placeholder'   => 'Enter furnishing status (e.g., Unfurnished, Fully furnished at higher rent, Open to partial furnishing)',
                    'tooltip'       => 'Helps the AI clearly communicate furnishing options (a native gap).',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'ev_charging_available' => [
                    'label'         => 'Is EV charging available or installable?',
                    'placeholder'   => 'Enter EV details (e.g., 240V outlet in garage, No charging on property, Building-wide charging planned)',
                    'tooltip'       => 'Lets the AI answer EV-infrastructure questions.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
            ],
            'Policies & Costs' => [
                'short_term_rentals_allowed' => [
                    'label'         => 'Are short-term rentals permitted?',
                    'placeholder'   => 'Enter short-term rental policy (e.g., Not permitted per lease, Allowed with HOA approval, Prohibited by city ordinance)',
                    'tooltip'       => 'Helps the AI address questions about short-term rental platforms.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'pest_or_mold_history' => [
                    'label'         => 'Any pest or mold history, and how was it resolved?',
                    'placeholder'   => 'Enter pest/mold history (e.g., No known history, Treated for roaches 2022 fully resolved, No recurrence)',
                    'tooltip'       => 'Helps the AI answer common health and safety questions.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'utilities_individually_metered' => [
                    'label'         => 'How are utilities metered and billed?',
                    'placeholder'   => 'Enter metering details (e.g., Individually metered tenant pays own, Master-metered split equally, Electric included)',
                    'tooltip'       => 'Lets the AI explain how utility costs are split or billed.',
                    'category_type' => 'common',
                    'source'        => 'KB+Field',
                ],
                'renters_insurance_required' => [
                    'label'         => 'Is renter\'s insurance required, and at what coverage?',
                    'placeholder'   => 'Enter policy (e.g., Required minimum $100K liability, Strongly encouraged not required, Landlord as additional interest)',
                    'tooltip'       => 'Factual requirement; the AI states it and gives no insurance advice.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'application_process' => [
                    'label'         => 'What\'s the application process, fee, and timeline?',
                    'placeholder'   => 'Enter application details (e.g., Online application, $50 fee per adult, Decision within 2–3 business days)',
                    'tooltip'       => 'Helps the AI answer questions about how to apply.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'lawn_landscaping_responsibility' => [
                    'label'         => 'Who is responsible for lawn/landscaping?',
                    'placeholder'   => 'Enter responsibility (e.g., Landlord-provided lawn service, Tenant maintains yard, HOA handles common areas)',
                    'tooltip'       => 'Lets the AI clarify lawn and landscaping responsibility.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'typical_tenancy_length' => [
                    'label'         => 'According to the landlord, what is the typical length of tenancy?',
                    'placeholder'   => 'Enter objective tenancy length (e.g., Most tenants stay 2–3 years, Typical first lease is 12 months)',
                    'tooltip'       => 'Landlord-attributed, objective statement only — no tenant opinions, anecdotes, or testimonials.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
            ],
            'Location & Neighborhood' => [
                'neighborhood_character' => [
                    'label'         => 'What can you share about the area\'s setting and nearby amenities?',
                    'placeholder'   => 'Enter objective setting details (e.g., Near a park, Two blocks from Main Street shops, Quiet residential street)',
                    'tooltip'       => 'Objective attributes only — no demographic descriptions of who lives here.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'noise_levels' => [
                    'label'         => 'What\'s the noise level like?',
                    'placeholder'   => 'Enter noise level (e.g., Very quiet backs to a park, Moderate street noise, Shared walls but quiet neighbors)',
                    'tooltip'       => 'Helps the AI give honest answers to noise-sensitive tenants.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'proximity_to_public_transit' => [
                    'label'         => 'How close is public transit?',
                    'placeholder'   => 'Enter transit proximity (e.g., Bus stop 1 block away, 10-min walk to light rail, Limited transit)',
                    'tooltip'       => 'Lets the AI answer commute and transit questions.',
                    'category_type' => 'common',
                    'source'        => 'KB+LocDNA',
                ],
                'guest_parking' => [
                    'label'         => 'Is guest/visitor parking available?',
                    'placeholder'   => 'Enter guest parking details (e.g., Two designated guest spaces, Street parking on both sides, No dedicated guest parking)',
                    'tooltip'       => 'Answers a common practical question beyond the native parking counts.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'school_district_assignment' => [
                    'label'         => 'Which school district serves this rental?',
                    'placeholder'   => 'Enter assigned district if known (e.g., Pinellas County Schools; verify boundaries with the district)',
                    'tooltip'       => 'Objective district/boundary information only — no ratings, rankings, or quality opinions.',
                    'category_type' => 'common',
                    'source'        => 'LocDNA',
                ],
            ],
        ],

        // =====================================================================
        // COMMERCIAL RENTAL
        // =====================================================================
        'commercial' => [
            'Commercial Lease & Space' => [
                'commercial_loading_dock_freight_elevator' => [
                    'label'         => 'Is there a loading dock or freight elevator?',
                    'placeholder'   => 'Enter loading/freight details (e.g., Grade-level loading door 10x10, Freight elevator 3,000 lbs, No loading dock)',
                    'tooltip'       => 'Helps the AI answer logistics and operations questions.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'commercial_electrical_capacity' => [
                    'label'         => 'What is the electrical capacity (amperage/voltage/3-phase)?',
                    'placeholder'   => 'Enter electrical specs (e.g., 200A/240V single-phase, 400A/3-phase available, Standard 100A panel)',
                    'tooltip'       => 'Lets the AI answer power-availability questions for equipment-heavy tenants.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'commercial_exclusivity_rights' => [
                    'label'         => 'Are exclusivity rights available?',
                    'placeholder'   => 'Enter exclusivity details (e.g., Available for restaurant tenants, No exclusivity in multi-tenant building, Negotiable for anchors)',
                    'tooltip'       => 'Factual disclosure; the AI restates it and gives no legal advice.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'commercial_expansion_option_rofr' => [
                    'label'         => 'Is there an expansion option or right of first refusal?',
                    'placeholder'   => 'Enter expansion rights (e.g., ROFR on adjacent 1,200 sqft, Expansion option for 3+ year leases, None available)',
                    'tooltip'       => 'Helps the AI answer questions about future growth within the building.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'commercial_buildout_ti' => [
                    'label'         => 'What build-out or tenant-improvement support is available beyond what\'s listed?',
                    'placeholder'   => 'Enter TI/build-out details (e.g., Up to $25/sqft TI for 5+ year leases, Open to wall/plumbing changes with approval, As-is)',
                    'tooltip'       => 'Factual disclosure of build-out support; the AI gives no negotiation coaching.',
                    'category_type' => 'common',
                    'source'        => 'KB+Field',
                ],
                'commercial_ada_accessibility' => [
                    'label'         => 'Is the space ADA accessible?',
                    'placeholder'   => 'Enter accessibility details (e.g., ADA-compliant entrance and restrooms, Ramp at rear entrance)',
                    'tooltip'       => 'Lets the AI answer accessibility questions factually.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'commercial_hvac_zones' => [
                    'label'         => 'What is the HVAC type, the zoning of zones, and after-hours HVAC availability?',
                    'placeholder'   => 'Enter HVAC details (e.g., Two rooftop units, Separately zoned per suite, After-hours HVAC by request)',
                    'tooltip'       => 'Helps the AI answer comfort-system and after-hours HVAC questions.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'commercial_co_tenancy' => [
                    'label'         => 'What is the co-tenancy / anchor-tenant situation in the building?',
                    'placeholder'   => 'Enter co-tenancy (e.g., Anchored by a national grocer, Two other suites occupied by offices, Standalone building)',
                    'tooltip'       => 'Helps the AI answer questions about neighboring and anchor tenants.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'commercial_zoning_context' => [
                    'label'         => 'Beyond the permitted-use field, are there variances, conditional uses, or restrictions tenants should know about?',
                    'placeholder'   => 'Enter zoning context (e.g., Conditional-use permit for food service, No auto uses per deed restriction, Signage variance in place; verify with the jurisdiction)',
                    'tooltip'       => 'Captures owner knowledge the structured permitted-use field cannot — variances, conditional uses, and use restrictions. Factual restatement only; not legal advice.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
            ],
        ],

    ],

];
