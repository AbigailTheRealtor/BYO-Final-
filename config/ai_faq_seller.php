<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Seller Property AI / Chatbot Knowledge Base Questions
    |--------------------------------------------------------------------------
    |
    | Two-axis architecture (docs/ask-ai-kb-replacement-spec.md Part A): each
    | knowledge base = the 'universal' group + the one group matching the listing's
    | property type, resolved via 'gating'. Residential questions never leak into
    | Income, Commercial, Business, or Vacant Land.
    |
    | Each question entry:
    |   key => [
    |     'label'         => question text (the form field the creator fills in),
    |     'placeholder'   => neutral example text (no demographic/steering phrasing),
    |     'tooltip'       => why the AI needs this answer,
    |     'category_type' => 'common' | 'insight' (Part D — Common Questions vs AI Insights),
    |     'source'        => where Ask AI draws the answer (KB / Field / PropDNA / LocDNA / Match / Desc),
    |   ]
    |
    | Audience: buyers / buyers' agents asking about the property.
    | Compliance: educational/neutral/factual only; no advice, steering, or superlatives.
    | Compatible with offer-listing/shared/ai-questions-input.blade.php
    |
    */

    'gating' => [
        'Residential Property' => ['universal', 'residential'],
        'Income Property'      => ['universal', 'income'],
        'Commercial Property'  => ['universal', 'commercial'],
        'Business Opportunity' => ['universal', 'business'],
        'Vacant Land'          => ['universal', 'land'],
    ],

    'groups' => [

        // =====================================================================
        // UNIVERSAL — renders for all five property types
        // =====================================================================
        'universal' => [
            'About the Sale' => [
                'seller_motivation_for_selling' => [
                    'label'         => 'Why is the owner selling the property?',
                    'placeholder'   => 'Enter context if desired (e.g., Relocating for work, Downsizing, Leave blank if preferred)',
                    'tooltip'       => 'Optional factual context the AI can share when buyers ask about the reason for selling.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'items_excluded_from_sale' => [
                    'label'         => 'What is included in the sale, and is anything excluded?',
                    'placeholder'   => 'Enter inclusions/exclusions (e.g., All appliances convey, Dining room chandelier excluded, Nothing excluded)',
                    'tooltip'       => 'Lets the AI clarify exactly what does and does not convey.',
                    'category_type' => 'common',
                    'source'        => 'KB+Field',
                ],
                'furniture_negotiability' => [
                    'label'         => 'Is any furniture or staging negotiable?',
                    'placeholder'   => 'Enter furniture details (e.g., Patio set available separately, Staged furniture not included)',
                    'tooltip'       => 'Factual disclosure of what furniture is available; the AI states availability only and gives no negotiation advice.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'closing_timeline_flexibility' => [
                    'label'         => 'How flexible is the timing for closing or possession?',
                    'placeholder'   => 'Enter closing flexibility (e.g., Can close in 21–90 days, Prefer 60 days, Need at least 45 days)',
                    'tooltip'       => 'Lets the AI set accurate expectations when buyers ask about timing.',
                    'category_type' => 'common',
                    'source'        => 'KB+Field',
                ],
                'seller_leaseback_option' => [
                    'label'         => 'Would the owner consider a short post-closing leaseback?',
                    'placeholder'   => 'Enter leaseback stance (e.g., Would consider up to 30 days, Not interested, Open if needed)',
                    'tooltip'       => 'Factual stance only; the AI states the position without advising on terms.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'known_defects_issues' => [
                    'label'         => 'Are there any known issues or disclosures the owner has shared?',
                    'placeholder'   => 'Enter known issues/disclosures (e.g., Minor driveway crack, Guest bath faucet drips, No known issues)',
                    'tooltip'       => 'Allows the AI to give accurate, transparent answers about disclosed condition.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'planned_nearby_development' => [
                    'label'         => 'Are there planned developments, road projects, or zoning changes nearby?',
                    'placeholder'   => 'Enter known developments (e.g., New shopping center planned 1 mile away, Road widening on main street, None known)',
                    'tooltip'       => 'Lets the AI answer questions about disclosed nearby changes using objective information.',
                    'category_type' => 'common',
                    'source'        => 'KB+LocDNA',
                ],
                'as_is_condition' => [
                    'label'         => 'Is the property being sold as-is, or is the owner open to repairs based on inspection?',
                    'placeholder'   => 'Enter as-is stance (e.g., Selling as-is, Open to reasonable repairs, Strictly as-is)',
                    'tooltip'       => 'Factual disclosure of the owner\'s stance; the AI must not recommend a repair or credit strategy.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'seller_concessions_offered' => [
                    'label'         => 'Has the owner indicated openness to concessions or credits?',
                    'placeholder'   => 'Enter concession stance (e.g., Open to closing-cost credits, Prefer as-is, Open to credits for major systems)',
                    'tooltip'       => 'Factual disclosure only; the AI conveys the stated position and gives no negotiation strategy.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
            ],
            'Property Insights' => [
                'unique_selling_points' => [
                    'label'         => 'What features make this property stand out?',
                    'placeholder'   => 'Enter notable features (e.g., Backs to conservation with no rear neighbors, Owned solar, Quiet street)',
                    'tooltip'       => 'Gives the AI objective, listing-sourced features to describe; no superlatives or comparisons.',
                    'category_type' => 'insight',
                    'source'        => 'PropDNA+Desc',
                ],
                'nearby_amenities_description' => [
                    'label'         => 'What location features are nearby?',
                    'placeholder'   => 'Enter nearby features (e.g., Publix 5 min away, Restaurants on Main Street, Dog park 2 blocks away)',
                    'tooltip'       => 'Lets the AI describe nearby parks, dining, shopping, and transit using objective data only — no steering.',
                    'category_type' => 'insight',
                    'source'        => 'LocDNA',
                ],
                'property_lifestyle_support' => [
                    'label'         => 'What lifestyle does this property appear to support?',
                    'placeholder'   => 'Enter feature-based lifestyle notes (e.g., Open layout and large yard suit entertaining, Near trails for outdoor recreation)',
                    'tooltip'       => 'The AI describes lifestyle by layout, features, and nearby amenities only — never by the type of person.',
                    'category_type' => 'insight',
                    'source'        => 'PropDNA+LocDNA',
                ],
                'disclosed_property_information' => [
                    'label'         => 'What property information has been disclosed?',
                    'placeholder'   => 'Optional — the AI summarizes the structured details and disclosures already provided',
                    'tooltip'       => 'Lets the AI summarize disclosed listing facts in one place for buyers.',
                    'category_type' => 'insight',
                    'source'        => 'Field+KB',
                ],
                'property_features_buyer_appeal' => [
                    'label'         => 'What property features may appeal to different buyers?',
                    'placeholder'   => 'Optional — the AI explains features and uses (e.g., single-story layout, home office, large garage)',
                    'tooltip'       => 'The AI describes appeal by property features and uses only — never by demographics or "perfect for ___."',
                    'category_type' => 'insight',
                    'source'        => 'PropDNA+Match',
                ],
            ],
        ],

        // =====================================================================
        // RESIDENTIAL
        // =====================================================================
        'residential' => [
            'Property Condition & Systems' => [
                'roof_age_and_condition' => [
                    'label'         => 'How old is the roof, and what condition is it in?',
                    'placeholder'   => 'Enter roof age and condition (e.g., 8-year-old architectural shingles, Replaced 2019, Original 1998 showing minor wear)',
                    'tooltip'       => 'Helps the AI answer buyer questions about the roof\'s age and condition.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'hvac_system_age' => [
                    'label'         => 'How old is the HVAC system, and when was it last serviced?',
                    'placeholder'   => 'Enter HVAC age and last service date (e.g., 5 years old serviced annually, Mini-split installed 2023)',
                    'tooltip'       => 'Lets the AI answer questions about comfort systems and service history.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'water_heater_age_type' => [
                    'label'         => 'How old is the water heater, and what type is it?',
                    'placeholder'   => 'Enter water heater type and age (e.g., 50-gallon electric 4 years old, Tankless gas 2022)',
                    'tooltip'       => 'Helps the AI answer questions about equipment age and expected lifespan.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'recent_renovations_list' => [
                    'label'         => 'What renovations or upgrades have been made, and when?',
                    'placeholder'   => 'Enter renovations with dates (e.g., Kitchen remodel 2021, New flooring 2022, Roof replaced 2019)',
                    'tooltip'       => 'Gives the AI context to describe the home\'s current condition accurately.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'permits_for_renovations' => [
                    'label'         => 'Were renovations completed with proper permits?',
                    'placeholder'   => 'Enter permit details (e.g., All work permitted and closed, Addition permitted 2020, No unpermitted work)',
                    'tooltip'       => 'Helps the AI address buyer questions about permit history.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'foundation_type_and_issues' => [
                    'label'         => 'Are there any known foundation or structural issues?',
                    'placeholder'   => 'Enter foundation condition (e.g., No known issues, Minor settling addressed 2019, In good condition)',
                    'tooltip'       => 'Gives the AI context to answer structural questions buyers commonly ask.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'pest_termite_history' => [
                    'label'         => 'Any pest or termite history, and how was it resolved?',
                    'placeholder'   => 'Enter pest history (e.g., Treated for termites 2018 no recurrence, Annual preventive treatment, No known history)',
                    'tooltip'       => 'Helps the AI address common buyer concerns about pest history and remediation.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'flood_damage_history' => [
                    'label'         => 'Has the property ever flooded or had water damage?',
                    'placeholder'   => 'Enter flood/water history (e.g., No flood history, Minor garage intrusion 2020 remediated, No claims filed)',
                    'tooltip'       => 'Lets the AI answer questions about water-damage history (distinct from the native flood-zone field).',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'mold_issues_history' => [
                    'label'         => 'Any mold history, and how was it addressed?',
                    'placeholder'   => 'Enter mold history (e.g., No known history, Bathroom mold remediated 2019 no recurrence, Air quality tested clear)',
                    'tooltip'       => 'Allows the AI to address buyer concerns about mold and air-quality history.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'solar_panels_owned_leased' => [
                    'label'         => 'Are solar panels present, and are they owned or leased?',
                    'placeholder'   => 'Enter solar details (e.g., Owned panels installed 2021, Leased — agreement transfers, No solar)',
                    'tooltip'       => 'Lets the AI clarify solar ownership and transfer for buyers.',
                    'category_type' => 'common',
                    'source'        => 'KB+Field',
                ],
                'smart_home_ev_features' => [
                    'label'         => 'Are there smart-home or EV-charging features?',
                    'placeholder'   => 'Enter features (e.g., Smart thermostat and locks, 240V EV outlet in garage, None)',
                    'tooltip'       => 'Helps the AI answer questions about smart-home and EV-charging features.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'pool_spa_equipment_condition' => [
                    'label'         => 'If there is a pool or spa, how old is the equipment and what is its condition?',
                    'placeholder'   => 'Enter pool/spa equipment details (e.g., Pump and heater replaced 2022, Saltwater system, Screen enclosure resealed 2023, No pool)',
                    'tooltip'       => 'The listing captures whether a pool exists; this adds the equipment age/condition detail buyers ask about, which is not a structured field.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
            ],
            'Costs & Utilities' => [
                'average_utility_costs' => [
                    'label'         => 'What are the average monthly utility costs?',
                    'placeholder'   => 'Enter average monthly costs (e.g., Electric ~$120, Water ~$40, Gas ~$30 in summer)',
                    'tooltip'       => 'Helps the AI provide realistic cost-of-living estimates buyers commonly ask about.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'internet_utility_providers' => [
                    'label'         => 'Which internet/utility providers serve the property, and what speeds are available?',
                    'placeholder'   => 'Enter providers and speeds (e.g., Xfinity and AT&T Fiber up to 1 Gbps, FPL electric, TECO gas)',
                    'tooltip'       => 'Lets the AI answer connectivity and service-availability questions.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'insurance_claims_history' => [
                    'label'         => 'Has the owner disclosed any insurance claims history for the property?',
                    'placeholder'   => 'Enter disclosed claims history (e.g., No claims in last 5 years, One wind claim 2021 repaired)',
                    'tooltip'       => 'Factual disclosure only; the AI shares what was disclosed and gives no insurance advice.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
            ],
            'Space & Light' => [
                'storage_space_available' => [
                    'label'         => 'What storage options are available?',
                    'placeholder'   => 'Enter storage details (e.g., Walk-up attic with flooring, Oversized garage shelving, Large backyard shed)',
                    'tooltip'       => 'Helps the AI address buyer questions about storage.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'natural_light_orientation' => [
                    'label'         => 'How is the natural light, and which way does the home face?',
                    'placeholder'   => 'Enter light and orientation (e.g., South-facing rear with afternoon sun, Bright open kitchen, East-facing front)',
                    'tooltip'       => 'Gives the AI details about daily light beyond what photos show.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
            ],
            'Location & Neighborhood' => [
                'neighborhood_character' => [
                    'label'         => 'What can you share about the area\'s setting and nearby amenities?',
                    'placeholder'   => 'Enter objective setting details (e.g., Quiet cul-de-sac, Near a park, Two blocks from Main Street shops)',
                    'tooltip'       => 'The AI answers with objective attributes only — no demographic descriptions or "good area" language.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'traffic_or_noise_concerns' => [
                    'label'         => 'Are there notable traffic or noise considerations nearby?',
                    'placeholder'   => 'Enter any considerations (e.g., Occasional train noise at night, Light traffic on weekends, None notable)',
                    'tooltip'       => 'Helps the AI give honest, accurate answers to buyers sensitive to noise and traffic.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'commute_options_access' => [
                    'label'         => 'What are typical commute options and travel times?',
                    'placeholder'   => 'Enter commute details (e.g., 20 min to downtown, 10 min to I-275 on-ramp, Near Park-n-Ride)',
                    'tooltip'       => 'Helps the AI respond to questions about travel time and transportation options.',
                    'category_type' => 'common',
                    'source'        => 'KB+LocDNA',
                ],
                'school_district_assignment' => [
                    'label'         => 'Which school district is this property assigned to?',
                    'placeholder'   => 'Enter assigned district/schools if known (e.g., Pinellas County Schools; verify boundaries with the district)',
                    'tooltip'       => 'The AI returns assigned district/boundary information only — no ratings, rankings, or quality opinions.',
                    'category_type' => 'common',
                    'source'        => 'LocDNA',
                ],
            ],
        ],

        // =====================================================================
        // INCOME PROPERTY — factual only; the AI gives no investment/ROI advice
        // =====================================================================
        'income' => [
            'Operations & Financials' => [
                'annual_operating_expenses_detail' => [
                    'label'         => 'What expenses are included in the operating costs?',
                    'placeholder'   => 'Enter expense breakdown (e.g., Taxes $6K, insurance $3.2K, management 8%, maintenance ~$4K/yr)',
                    'tooltip'       => 'Lets the AI explain the disclosed cost structure (beyond the native total). No benchmarking or opinions.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'existing_tenant_lease_terms' => [
                    'label'         => 'What are the current lease terms and escalations for existing tenants?',
                    'placeholder'   => 'Enter lease summary (e.g., 4 units, 2 annual leases expiring Dec 2025, 2 month-to-month, 3% annual escalations)',
                    'tooltip'       => 'Gives the AI detail to answer questions about current income stability.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'income_rent_roll_context' => [
                    'label'         => 'How do current rents compare to what the owner believes is market, and are any units below market?',
                    'placeholder'   => 'Enter rent context (e.g., Units 1–2 at market, Unit 3 long-term tenant ~15% under market, All renew within 12 months)',
                    'tooltip'       => 'The listing captures per-unit rent as a structured field; this adds the owner\'s factual market-context narrative. The AI restates it and gives no investment/upside advice.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'value_add_opportunities' => [
                    'label'         => 'What recent improvements or income changes has the owner disclosed?',
                    'placeholder'   => 'Enter disclosed changes (e.g., Rents updated to market in 2024, Unit 3 renovated 2023, New roof 2022)',
                    'tooltip'       => 'Factual, neutral restatement of disclosed changes; no investment framing or upside projections.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'tenant_payment_history' => [
                    'label'         => 'What has the owner disclosed about tenant payment history?',
                    'placeholder'   => 'Enter disclosed payment history (e.g., All tenants current, One unit historically pays late, No disclosed issues)',
                    'tooltip'       => 'Factual disclosure only; the AI restates what was disclosed.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'deferred_maintenance_disclosed' => [
                    'label'         => 'Is there any deferred maintenance or near-term capital work disclosed?',
                    'placeholder'   => 'Enter disclosed items (e.g., Roof at end of life, Parking lot needs resurfacing, None disclosed)',
                    'tooltip'       => 'Lets the AI restate disclosed maintenance and capital items factually.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'professional_management' => [
                    'label'         => 'Is the property professionally managed?',
                    'placeholder'   => 'Enter management details (e.g., Third-party PM at 8%, Self-managed by owner, On-site manager)',
                    'tooltip'       => 'Helps the AI answer questions about how the property is managed.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'income_utilities_split' => [
                    'label'         => 'How are utilities split between owner and tenants?',
                    'placeholder'   => 'Enter utility responsibility (e.g., Tenants pay electric, owner pays water/trash, All separately metered)',
                    'tooltip'       => 'Lets the AI explain who pays which utilities.',
                    'category_type' => 'common',
                    'source'        => 'KB+Field',
                ],
                'income_building_systems_age' => [
                    'label'         => 'How old are the roof and major building systems?',
                    'placeholder'   => 'Enter systems age (e.g., Roof 2019, two HVAC units 2018 and 2021, Boiler 2015)',
                    'tooltip'       => 'Helps the AI answer questions about building-systems age.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
            ],
            'Property Insights' => [
                'income_property_standout' => [
                    'label'         => 'What features make this property stand out to operators?',
                    'placeholder'   => 'Optional — the AI describes objective features (e.g., separately metered units, on-site parking)',
                    'tooltip'       => 'Neutral, feature-based description; no investment framing or superlatives.',
                    'category_type' => 'insight',
                    'source'        => 'PropDNA+Desc',
                ],
                'income_operations_disclosed' => [
                    'label'         => 'What has been disclosed about this property\'s operations?',
                    'placeholder'   => 'Optional — the AI summarizes disclosed operational details',
                    'tooltip'       => 'Lets the AI summarize disclosed operational facts in one place.',
                    'category_type' => 'insight',
                    'source'        => 'Field+KB',
                ],
                'income_location_features' => [
                    'label'         => 'What location features are nearby?',
                    'placeholder'   => 'Optional — the AI describes nearby objective features (transit, retail, employment centers)',
                    'tooltip'       => 'Objective location data only; no steering.',
                    'category_type' => 'insight',
                    'source'        => 'LocDNA',
                ],
            ],
        ],

        // =====================================================================
        // COMMERCIAL PROPERTY
        // =====================================================================
        'commercial' => [
            'Building & Use' => [
                'commercial_building_systems' => [
                    'label'         => 'What are the building systems (HVAC, electrical capacity)?',
                    'placeholder'   => 'Enter systems (e.g., Two rooftop HVAC units, 400A 3-phase power, LED lighting)',
                    'tooltip'       => 'Lets the AI answer questions about building systems and capacity.',
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
                'commercial_restroom_count' => [
                    'label'         => 'How many restrooms are there?',
                    'placeholder'   => 'Enter restroom count (e.g., Two ADA restrooms, Men\'s and women\'s with 3 fixtures each)',
                    'tooltip'       => 'Helps the AI answer restroom-count questions.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'commercial_parking_loading' => [
                    'label'         => 'What parking, access, and loading are available?',
                    'placeholder'   => 'Enter parking/loading (e.g., 20 surface spaces, Grade-level loading door, Rear alley access)',
                    'tooltip'       => 'Lets the AI answer logistics and access questions.',
                    'category_type' => 'common',
                    'source'        => 'KB+Field',
                ],
                'commercial_systems_age' => [
                    'label'         => 'How old are the roof and major systems?',
                    'placeholder'   => 'Enter systems age (e.g., Roof 2020, HVAC 2018, Electrical panel updated 2019)',
                    'tooltip'       => 'Helps the AI answer questions about systems age.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'commercial_recent_improvements' => [
                    'label'         => 'What recent improvements have been made?',
                    'placeholder'   => 'Enter improvements (e.g., New storefront 2022, Restrooms remodeled 2021, Parking lot resurfaced 2023)',
                    'tooltip'       => 'Gives the AI context to describe recent improvements.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'commercial_zoning_context' => [
                    'label'         => 'Beyond the zoning code, are there variances, special-use permits, conditional uses, or grandfathered uses in place?',
                    'placeholder'   => 'Enter zoning context (e.g., Conditional-use permit for restaurant, Grandfathered nonconforming parking, Variance for signage; verify with the jurisdiction)',
                    'tooltip'       => 'Captures owner knowledge the structured zoning field cannot — variances, special-use permits, and grandfathered uses. Factual restatement only; not legal advice.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
            ],
            'Property Insights' => [
                'commercial_space_standout' => [
                    'label'         => 'What features make this space stand out?',
                    'placeholder'   => 'Optional — the AI describes objective features (frontage, ceiling height, power)',
                    'tooltip'       => 'Neutral, feature-based description; no superlatives.',
                    'category_type' => 'insight',
                    'source'        => 'PropDNA+Desc',
                ],
                'commercial_location_features' => [
                    'label'         => 'What location features are nearby?',
                    'placeholder'   => 'Optional — the AI describes nearby objective features (traffic counts, retail, highways)',
                    'tooltip'       => 'Objective location data only; no steering.',
                    'category_type' => 'insight',
                    'source'        => 'LocDNA',
                ],
            ],
        ],

        // =====================================================================
        // BUSINESS OPPORTUNITY — renders this group only (no income/commercial)
        // =====================================================================
        'business' => [
            'Business Details' => [
                'business_reason_for_selling' => [
                    'label'         => 'Why is the business being sold?',
                    'placeholder'   => 'Enter reason (e.g., Owner retiring after 20 years, Relocating, Pursuing other ventures)',
                    'tooltip'       => 'Helps the AI address one of the most common questions buyers ask about a business sale.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'seller_training_transition' => [
                    'label'         => 'How much training/transition support will the seller provide?',
                    'placeholder'   => 'Enter transition terms (e.g., 60 days hands-on training, Open to consulting, Two-week handover)',
                    'tooltip'       => 'Lets the AI set expectations around the handover period.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'business_staff_retention' => [
                    'label'         => 'Will existing staff stay on after the sale?',
                    'placeholder'   => 'Enter staffing continuity (e.g., All staff willing to stay, Key manager committed, Some turnover expected)',
                    'tooltip'       => 'Gives the AI context to answer staffing-continuity questions.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'business_customer_concentration' => [
                    'label'         => 'How concentrated is the customer base?',
                    'placeholder'   => 'Enter concentration (e.g., No customer over 5% of revenue, Top client ~30%, Hundreds of small accounts)',
                    'tooltip'       => 'Lets the AI restate disclosed customer-concentration facts.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'business_vendor_contracts' => [
                    'label'         => 'What vendor or supplier contracts are in place?',
                    'placeholder'   => 'Enter contracts (e.g., 3-year supplier agreement transferable, Month-to-month vendors, Exclusive distributor)',
                    'tooltip'       => 'Helps the AI answer questions about vendor and supplier arrangements.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'business_licenses_transferable' => [
                    'label'         => 'Are licenses, permits, or franchise rights transferable?',
                    'placeholder'   => 'Enter transferability (e.g., Liquor license transferable with approval, Franchise rights assignable, Permits convey)',
                    'tooltip'       => 'Factual restatement of disclosed transferability; not legal advice.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'business_online_presence' => [
                    'label'         => 'What is the business\'s online presence and review profile?',
                    'placeholder'   => 'Enter online presence (e.g., 4.6 stars across 300 Google reviews, Active social accounts, Established website)',
                    'tooltip'       => 'Helps the AI answer questions about the business\'s online footprint.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'business_seasonality' => [
                    'label'         => 'Is the business seasonal?',
                    'placeholder'   => 'Enter seasonality (e.g., Peak Nov–Jan, Steady year-round, Summer-heavy tourist trade)',
                    'tooltip'       => 'Lets the AI answer questions about seasonal patterns.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'business_owner_involvement' => [
                    'label'         => 'How involved is the current owner day-to-day?',
                    'placeholder'   => 'Enter owner involvement (e.g., Absentee with full management team, Owner works 40 hrs/wk, Part-time oversight)',
                    'tooltip'       => 'Helps the AI answer questions about the owner\'s day-to-day role.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
            ],
            'Business Insights' => [
                'business_information_disclosed' => [
                    'label'         => 'What information has been disclosed about this business?',
                    'placeholder'   => 'Optional — the AI summarizes disclosed business details',
                    'tooltip'       => 'Summarizes disclosed facts; no financial or investment advice.',
                    'category_type' => 'insight',
                    'source'        => 'Field+KB',
                ],
                'business_sale_includes' => [
                    'label'         => 'What does the sale appear to include?',
                    'placeholder'   => 'Optional — the AI restates disclosed inclusions (FF&E, inventory, sale-includes fields)',
                    'tooltip'       => 'Restates disclosed inclusions factually.',
                    'category_type' => 'insight',
                    'source'        => 'Field',
                ],
            ],
        ],

        // =====================================================================
        // VACANT LAND — factual/objective; no engineering or due-diligence advice
        // =====================================================================
        'land' => [
            'Site & Access' => [
                'land_soil_and_topography' => [
                    'label'         => 'Are there known soil, perc, or topography considerations?',
                    'placeholder'   => 'Enter land conditions (e.g., Flat high and dry, NE corner in AE flood zone, Soil perked for septic)',
                    'tooltip'       => 'Gives the AI context to answer buildability questions factually; defers to professionals.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'land_survey_available' => [
                    'label'         => 'Is a current survey available, and has the land been cleared/improved?',
                    'placeholder'   => 'Enter survey/clearing details (e.g., 2022 survey available, Partially cleared and rough-graded, Raw land no survey)',
                    'tooltip'       => 'Lets the AI inform buyers about boundary clarity and any site work done.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'land_zoning_permitted_uses' => [
                    'label'         => 'What uses are permitted under current zoning?',
                    'placeholder'   => 'Enter permitted uses (e.g., R-1 single family, Agricultural with residential allowed; verify with the jurisdiction)',
                    'tooltip'       => 'Factual restatement of disclosed zoning and permitted uses; not legal advice.',
                    'category_type' => 'common',
                    'source'        => 'Field+KB',
                ],
                'land_development_restrictions' => [
                    'label'         => 'Are there deed restrictions beyond recorded easements?',
                    'placeholder'   => 'Enter restrictions (e.g., 25-ft conservation easement on east edge, No deed restrictions, HOA approval for structures)',
                    'tooltip'       => 'Helps the AI answer questions about disclosed restrictions (the native fields cover easements).',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'land_access_and_road' => [
                    'label'         => 'Are there access limitations or shared-road maintenance obligations?',
                    'placeholder'   => 'Enter access details (e.g., Access via recorded easement, Shared private road maintenance agreement, Paved county frontage)',
                    'tooltip'       => 'Helps the AI address access limitations beyond the native road-frontage field.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'land_wetlands_environmental' => [
                    'label'         => 'Are there wetlands or environmental designations on the parcel?',
                    'placeholder'   => 'Enter designations (e.g., No known wetlands, Rear portion in conservation, Partial AE flood zone)',
                    'tooltip'       => 'Objective restatement of disclosed designations; the AI defers to professionals.',
                    'category_type' => 'common',
                    'source'        => 'KB+LocDNA',
                ],
                'land_prior_use' => [
                    'label'         => 'What was the land\'s prior use?',
                    'placeholder'   => 'Enter prior use (e.g., Former pasture, Previously a single-family lot, Never developed)',
                    'tooltip'       => 'Lets the AI answer questions about the parcel\'s prior use.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
            ],
            'Property Insights' => [
                'land_location_features' => [
                    'label'         => 'What location features are nearby?',
                    'placeholder'   => 'Optional — the AI describes nearby objective features (roads, utilities at the road, services)',
                    'tooltip'       => 'Objective location data only; no steering.',
                    'category_type' => 'insight',
                    'source'        => 'LocDNA',
                ],
                'land_site_characteristics_disclosed' => [
                    'label'         => 'What objective site characteristics has the listing disclosed?',
                    'placeholder'   => 'Optional — the AI summarizes disclosed site characteristics',
                    'tooltip'       => 'Summarizes disclosed, objective site facts.',
                    'category_type' => 'insight',
                    'source'        => 'Field+KB',
                ],
            ],
        ],

    ],

];
