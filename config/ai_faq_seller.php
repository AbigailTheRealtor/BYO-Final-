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
    | Each question entry uses the array shape:
    |   key => ['label' => '...', 'placeholder' => '...', 'tooltip' => '...']
    |
    | Compatible with offer-listing/shared/ai-questions-input.blade.php
    |
    */

    'questions' => [

        'Property Condition & Maintenance' => [
            'roof_age_and_condition' => [
                'label'       => 'How old is the roof, and what condition is it in?',
                'placeholder' => 'Enter roof age and condition (e.g., 8-year-old architectural shingles, Replaced 2019 no known issues, Original 1998 roof showing minor wear)',
                'tooltip'     => 'Helps the AI answer buyer questions about the property\'s age, condition, and potential near-term expenses.',
            ],
            'hvac_system_age' => [
                'label'       => 'How old is the HVAC system, and when was it last serviced?',
                'placeholder' => 'Enter HVAC age and last service date (e.g., 5 years old serviced annually, Last serviced March 2024, New mini-split installed 2023)',
                'tooltip'     => 'Lets the AI accurately answer questions about comfort systems and service history.',
            ],
            'water_heater_age_type' => [
                'label'       => 'How old is the water heater, and what type is it?',
                'placeholder' => 'Enter water heater type and age (e.g., 50-gallon electric 4 years old, Tankless gas unit installed 2022, Original to home needs replacement)',
                'tooltip'     => 'Helps the AI answer questions about utility costs and expected equipment lifespan.',
            ],
            'recent_renovations_list' => [
                'label'       => 'What renovations or upgrades have been made, and when?',
                'placeholder' => 'Enter renovations with approximate dates (e.g., Kitchen remodel 2021, New flooring 2022, Roof replaced 2019)',
                'tooltip'     => 'Gives the AI context to highlight improvements and accurately describe the home\'s current condition.',
            ],
            'permits_for_renovations' => [
                'label'       => 'Were all renovations and additions completed with proper permits?',
                'placeholder' => 'Enter permit details (e.g., All work permitted and closed, Addition permitted in 2020, No unpermitted work)',
                'tooltip'     => 'Helps the AI address buyer questions about legal compliance and renovation history.',
            ],
            'known_defects_issues' => [
                'label'       => 'Are there any known defects, issues, or deferred repairs?',
                'placeholder' => 'Enter any known issues (e.g., Minor crack in driveway, Guest bath faucet drips, No known issues)',
                'tooltip'     => 'Allows the AI to provide accurate, transparent answers about the property\'s current condition.',
            ],
            'foundation_type_and_issues' => [
                'label'       => 'What type of foundation does the property have, and are there any known issues?',
                'placeholder' => 'Enter foundation type and condition (e.g., Concrete slab no known issues, Block foundation minor settling, Poured concrete in excellent condition)',
                'tooltip'     => 'Gives the AI context to answer structural questions buyers typically ask.',
            ],
            'pest_termite_history' => [
                'label'       => 'Has the property ever had pest or termite issues, and how were they resolved?',
                'placeholder' => 'Enter pest history (e.g., Treated for termites in 2018 no recurrence, Annual preventive treatment in place, No known pest history)',
                'tooltip'     => 'Helps the AI address common buyer concerns about pest history and remediation.',
            ],
            'flood_damage_history' => [
                'label'       => 'Has the property ever flooded or experienced water damage?',
                'placeholder' => 'Enter flood or water damage history (e.g., No flood history, Minor garage water intrusion 2020 remediated, No insurance claims filed)',
                'tooltip'     => 'Lets the AI accurately answer questions about water damage history and insurance considerations.',
            ],
            'mold_issues_history' => [
                'label'       => 'Has the property ever had mold issues, and how were they addressed?',
                'placeholder' => 'Enter mold history (e.g., No known mold history, Bathroom mold remediated 2019 no recurrence, Air quality tested and clear)',
                'tooltip'     => 'Allows the AI to address buyer concerns about mold and air quality history.',
            ],
        ],

        'Financial & Utility Insights' => [
            'average_utility_costs' => [
                'label'       => 'What are the average monthly utility costs (electric, gas, water)?',
                'placeholder' => 'Enter average monthly costs (e.g., Electric ~$120, Water ~$40, Gas ~$30 in summer)',
                'tooltip'     => 'Helps the AI provide realistic cost-of-living estimates buyers commonly ask about.',
            ],
            'internet_utility_providers' => [
                'label'       => 'Which internet and utility providers serve this property?',
                'placeholder' => 'Enter providers (e.g., Xfinity and AT&T Fiber available, FPL for electric, TECO for gas)',
                'tooltip'     => 'Lets the AI answer questions about service availability for buyers evaluating connectivity.',
            ],
            'seller_concessions_offered' => [
                'label'       => 'Are you open to offering seller concessions or repair credits?',
                'placeholder' => 'Enter concession flexibility (e.g., Open to closing cost credits up to 2%, Prefer as-is but will consider, Open to repair credits for major systems)',
                'tooltip'     => 'Helps the AI guide buyers on what negotiation flexibility exists before an offer is made.',
            ],
        ],

        'Location & Lifestyle' => [
            'neighborhood_character' => [
                'label'       => 'How would you describe the neighborhood vibe and community feel?',
                'placeholder' => 'Enter neighborhood description (e.g., Quiet family-friendly cul-de-sac, Active HOA with community events, Mix of retirees and young families)',
                'tooltip'     => 'Gives the AI context to describe the community feel to buyers unfamiliar with the area.',
            ],
            'traffic_or_noise_concerns' => [
                'label'       => 'Are there any notable traffic, noise, or nuisance concerns nearby?',
                'placeholder' => 'Enter any concerns (e.g., Occasional train noise at night, Light traffic even on weekends, No notable concerns)',
                'tooltip'     => 'Helps the AI give honest, accurate answers to buyers sensitive to noise and traffic.',
            ],
            'planned_nearby_development' => [
                'label'       => 'Are there any planned developments, road projects, or zoning changes nearby?',
                'placeholder' => 'Enter known developments (e.g., New shopping center planned 1 mile away, Road widening on main street, None known)',
                'tooltip'     => 'Lets the AI answer questions about future changes that may affect property value or neighborhood character.',
            ],
            'commute_options_access' => [
                'label'       => 'What are typical commute options and travel times to major employment centers?',
                'placeholder' => 'Enter commute details (e.g., 20 min to downtown, 10 min to I-275 on-ramp, Near Park-n-Ride)',
                'tooltip'     => 'Helps the AI respond to buyer questions about travel time and transportation options.',
            ],
            'natural_light_orientation' => [
                'label'       => 'How is the natural light throughout the day, and which direction does the home face?',
                'placeholder' => 'Enter light and orientation details (e.g., South-facing rear with afternoon sun in pool area, Bright open kitchen all day, East-facing front yard)',
                'tooltip'     => 'Gives the AI details to describe daily living experience beyond what listing photos show.',
            ],
            'nearby_amenities_description' => [
                'label'       => 'What nearby amenities, shops, or dining do you value most about this location?',
                'placeholder' => 'Enter nearby amenities (e.g., Publix 5 min away, Restaurants on Main Street, Dog park 2 blocks away)',
                'tooltip'     => 'Lets the AI highlight the lifestyle benefits of the location that photos and descriptions may not capture.',
            ],
            'neighborhood_restrictions' => [
                'label'       => 'Are there deed restrictions or neighborhood rules buyers should know about?',
                'placeholder' => 'Enter restrictions (e.g., No short-term rentals per HOA, Deed-restricted community no RV parking, No restrictions)',
                'tooltip'     => 'Helps the AI accurately inform buyers about community rules before they make an offer.',
            ],
        ],

        'Flexibility & Negotiation' => [
            'closing_timeline_flexibility' => [
                'label'       => 'How flexible are you on the closing date?',
                'placeholder' => 'Enter closing flexibility (e.g., Need at least 45 days, Can close in 21–90 days, Prefer 60 days but negotiable)',
                'tooltip'     => 'Lets the AI set accurate expectations when buyers ask about timing.',
            ],
            'seller_leaseback_option' => [
                'label'       => 'Would you consider a seller leaseback arrangement after closing?',
                'placeholder' => 'Enter leaseback preference (e.g., Would consider up to 30 days at market rent, Not interested, Open to short leaseback if needed)',
                'tooltip'     => 'Helps the AI answer questions about post-closing arrangements before buyers bring them up.',
            ],
            'items_excluded_from_sale' => [
                'label'       => 'Are there any fixtures, appliances, or personal items excluded from the sale?',
                'placeholder' => 'Enter excluded items (e.g., Dining room chandelier excluded, Garage fridge excluded all else conveys, Nothing excluded everything stays)',
                'tooltip'     => 'Prevents buyer confusion by letting the AI clarify exactly what does and does not convey.',
            ],
            'furniture_negotiability' => [
                'label'       => 'Is any furniture or staging negotiable as part of the sale?',
                'placeholder' => 'Enter furniture details (e.g., Outdoor patio set available separately, Sectional sofa negotiable, Staged furniture not included)',
                'tooltip'     => 'Gives the AI context to answer questions about staging and negotiable personal property.',
            ],
            'as_is_condition' => [
                'label'       => 'Is this being sold as-is, or are you open to repairs based on inspection findings?',
                'placeholder' => 'Enter as-is stance (e.g., Selling as-is open to credits for major systems, Open to reasonable repairs up to $5K, Strictly as-is no credits)',
                'tooltip'     => 'Helps the AI accurately frame the seller\'s stance on repairs and inspection negotiations.',
            ],
            'environmental_concerns' => [
                'label'       => 'Are there any known environmental concerns or use restrictions on the property?',
                'placeholder' => 'Enter environmental details (e.g., No known concerns, Property in AE flood zone insurance required, Conservation easement on rear portion)',
                'tooltip'     => 'Lets the AI provide complete, accurate answers to buyers asking about environmental considerations.',
            ],
        ],

        'Hidden Selling Points' => [
            'unique_selling_points' => [
                'label'       => 'What features or qualities won\'t be obvious from the listing photos alone?',
                'placeholder' => 'Enter hidden highlights (e.g., Extremely low utility bills due to solar, Neighborhood is incredibly quiet at night, Backs to conservation with no rear neighbors)',
                'tooltip'     => 'Gives the AI information to highlight this property\'s best qualities that aren\'t visible in photos.',
            ],
            'seller_favorite_features' => [
                'label'       => 'What aspects of this home will you miss the most after selling?',
                'placeholder' => 'Enter favorite features (e.g., Morning light in the kitchen, Backyard privacy, Proximity to the trail)',
                'tooltip'     => 'Helps the AI describe the day-to-day lifestyle benefits in a way that resonates with buyers.',
            ],
            'seller_motivation_for_selling' => [
                'label'       => 'Is there anything about your reason for selling you\'d like buyers to know?',
                'placeholder' => 'Enter context if desired (e.g., Relocating for work, Downsizing after kids left, Leave blank if preferred)',
                'tooltip'     => 'Provides optional context that can help the AI address buyer questions about the seller\'s situation.',
            ],
            'move_in_ready_status' => [
                'label'       => 'Is the property move-in ready, or would buyers want to plan for any updates?',
                'placeholder' => 'Enter move-in status (e.g., Fully move-in ready, Cosmetically dated but all systems solid, Buyers may want to update bathrooms)',
                'tooltip'     => 'Helps the AI set accurate expectations and match buyers who are or aren\'t open to updates.',
            ],
            'parking_arrangements' => [
                'label'       => 'What parking is available — garage spaces, driveway length, street access?',
                'placeholder' => 'Enter parking details (e.g., Two-car garage plus four-car driveway, Tandem garage with extra storage, On-street parking only)',
                'tooltip'     => 'Lets the AI accurately answer one of the most common practical questions buyers have.',
            ],
            'storage_space_available' => [
                'label'       => 'What storage options are available — attic, garage, shed, extra closets?',
                'placeholder' => 'Enter storage details (e.g., Walk-up attic with flooring, Oversized garage with built-in shelving, Large shed in backyard)',
                'tooltip'     => 'Helps the AI address buyer questions about storage — a frequently overlooked factor in purchase decisions.',
            ],
            'hoa_community_highlights' => [
                'label'       => 'What do you love most about the community or HOA amenities here?',
                'placeholder' => 'Enter community highlights (e.g., Resort-style pool, Low monthly HOA, Active social calendar, Great neighbors)',
                'tooltip'     => 'Gives the AI context to describe community benefits and help buyers evaluate the HOA.',
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

        'commercial_income' => [
            'label'       => 'Income / Commercial Property Questions',
            'visible_for' => ['Commercial Property', 'Income Property'],
            'questions'   => [
                'annual_net_operating_income' => [
                    'label'       => 'What is the current annual net operating income (NOI)?',
                    'placeholder' => 'Enter NOI (e.g., $85,000/year based on current rents and expenses, $120K NOI stabilized, Proforma NOI available upon request)',
                    'tooltip'     => 'Helps the AI answer investor questions about income potential and return calculations.',
                ],
                'current_cap_rate' => [
                    'label'       => 'What is the current capitalization rate?',
                    'placeholder' => 'Enter cap rate (e.g., Approximately 6.2% at asking price, 7.1% trailing 12 months, Available in offering memorandum)',
                    'tooltip'     => 'Lets the AI provide investors with a quick performance benchmark at asking price.',
                ],
                'existing_tenant_lease_terms' => [
                    'label'       => 'What are the existing tenant lease terms, rents, and expiration dates?',
                    'placeholder' => 'Enter lease summary (e.g., 4 units with 2 annual leases expiring Dec 2025, 2 month-to-month at $1,400/mo, NNN lease through 2027)',
                    'tooltip'     => 'Gives the AI the details needed to answer questions about current income stability.',
                ],
                'current_occupancy_rate' => [
                    'label'       => 'What is the current occupancy rate?',
                    'placeholder' => 'Enter occupancy (e.g., 100% occupied, 1 of 4 units currently vacant, 92% occupied with lease-up in progress)',
                    'tooltip'     => 'Helps the AI answer buyer questions about cash flow and vacancy risk.',
                ],
                'annual_operating_expenses_detail' => [
                    'label'       => 'What are the main annual operating expenses (taxes, insurance, management, maintenance)?',
                    'placeholder' => 'Enter expense breakdown (e.g., Taxes $6K insurance $3.2K management 8%, Maintenance avg $4K/yr, Full expense schedule available)',
                    'tooltip'     => 'Lets the AI provide a complete picture of the property\'s cost structure.',
                ],
                'value_add_opportunities' => [
                    'label'       => 'Are there value-add opportunities — below-market rents, unused units, or renovation upside?',
                    'placeholder' => 'Enter upside details (e.g., Rents 10–15% below market, Unit 3 could be renovated for premium rent, Additional parking lot could be leased)',
                    'tooltip'     => 'Gives the AI context to highlight upside potential for investors evaluating this acquisition.',
                ],
            ],
        ],

        'business_opportunity' => [
            'label'       => 'Business Opportunity Questions',
            'visible_for' => ['Business Opportunity'],
            'questions'   => [
                'annual_business_revenue' => [
                    'label'       => 'What is the current annual gross revenue?',
                    'placeholder' => 'Enter revenue (e.g., $420,000 gross last fiscal year, Revenue trending up 12% YOY, Details available with NDA)',
                    'tooltip'     => 'Helps the AI answer buyer questions about top-line business performance.',
                ],
                'annual_net_profit' => [
                    'label'       => 'What is the approximate annual net profit or owner\'s discretionary earnings?',
                    'placeholder' => 'Enter profit (e.g., ~$95,000 SDE, $120K net before owner comp, Details available with NDA)',
                    'tooltip'     => 'Lets the AI provide a clear picture of the business\'s earning power to interested buyers.',
                ],
                'business_reason_for_selling' => [
                    'label'       => 'Why is the business being sold?',
                    'placeholder' => 'Enter reason (e.g., Owner retiring after 20 years, Relocating out of state, Pursuing other ventures)',
                    'tooltip'     => 'Helps the AI address one of the most common questions buyers ask about any business sale.',
                ],
                'business_employee_count' => [
                    'label'       => 'How many employees does the business have, and will they stay on?',
                    'placeholder' => 'Enter employee details (e.g., 6 full-time all willing to stay, 2 part-time staff, Key manager committed to staying)',
                    'tooltip'     => 'Gives the AI context to answer questions about staffing continuity after a sale.',
                ],
                'seller_training_transition' => [
                    'label'       => 'How much training or transition support will the seller provide?',
                    'placeholder' => 'Enter transition terms (e.g., Seller will train 60 days post-close, Open to extended consulting, Two-week handover included)',
                    'tooltip'     => 'Lets the AI set expectations around the seller\'s involvement during the handover period.',
                ],
                'business_lease_status' => [
                    'label'       => 'What is the status of the business location lease — term remaining and assignability?',
                    'placeholder' => 'Enter lease details (e.g., 3 years remaining assignable with landlord approval, Month-to-month with renewal option, Owned building included)',
                    'tooltip'     => 'Helps the AI answer questions about location security and lease continuity.',
                ],
                'inventory_equipment_included' => [
                    'label'       => 'What inventory and equipment are included in the sale price?',
                    'placeholder' => 'Enter included assets (e.g., All equipment and ~$30K in inventory included, FF&E valued at $85K, Equipment list available on request)',
                    'tooltip'     => 'Gives the AI an accurate picture of what is included in the asking price.',
                ],
            ],
        ],

        'vacant_land' => [
            'label'       => 'Vacant Land Questions',
            'visible_for' => ['Vacant Land'],
            'questions'   => [
                'land_utilities_availability' => [
                    'label'       => 'What utilities are available or accessible on the land?',
                    'placeholder' => 'Enter utility details (e.g., Electric and water at road, Septic required well needed, No utilities off-grid setup needed)',
                    'tooltip'     => 'Helps the AI answer the most common buyer question about land development feasibility.',
                ],
                'land_zoning_permitted_uses' => [
                    'label'       => 'What is the current zoning designation and what uses are permitted?',
                    'placeholder' => 'Enter zoning (e.g., Zoned R-1 single family, Agricultural with residential allowed, C-2 commercial zoning)',
                    'tooltip'     => 'Lets the AI accurately answer questions about what buyers can legally build or operate.',
                ],
                'land_access_and_road' => [
                    'label'       => 'What road access does the parcel have — paved road frontage, dirt road, or easement?',
                    'placeholder' => 'Enter access details (e.g., Paved county road frontage, Access via recorded easement through neighbor, Private unpaved road)',
                    'tooltip'     => 'Helps the AI address questions about practicality and access rights before buyers visit.',
                ],
                'land_soil_and_topography' => [
                    'label'       => 'Are there any known soil, topography, flood zone, or wetland considerations?',
                    'placeholder' => 'Enter land conditions (e.g., Flat high and dry, Northeast corner in AE flood zone, Soil perked for septic)',
                    'tooltip'     => 'Gives the AI context to answer buyer questions about buildability and flood risk.',
                ],
                'land_survey_available' => [
                    'label'       => 'Is a current survey available, and has the land been cleared or improved?',
                    'placeholder' => 'Enter survey and clearing details (e.g., Survey from 2022 available, Partially cleared and rough-graded, No survey raw land)',
                    'tooltip'     => 'Lets the AI inform buyers about boundary clarity and any site preparation already done.',
                ],
                'land_development_restrictions' => [
                    'label'       => 'Are there any deed restrictions, easements, or development restrictions buyers should know?',
                    'placeholder' => 'Enter restrictions (e.g., 25-ft conservation easement along eastern edge, No deed restrictions, HOA approval required for structures)',
                    'tooltip'     => 'Helps the AI accurately answer questions about what limitations exist on the land.',
                ],
            ],
        ],

    ],

];
