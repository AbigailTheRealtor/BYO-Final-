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
    |   key => ['label' => '...', 'placeholder' => '...']
    |
    | Compatible with offer-listing/shared/ai-questions-input.blade.php
    |
    */

    'questions' => [

        'Property Condition & Maintenance' => [
            'roof_age_and_condition' => [
                'label'       => 'How old is the roof, and what condition is it in?',
                'placeholder' => 'Enter roof age and condition (e.g., 8-year-old architectural shingles, no known issues)',
            ],
            'hvac_system_age' => [
                'label'       => 'How old is the HVAC system, and when was it last serviced?',
                'placeholder' => 'Enter HVAC age and last service date (e.g., 5 years old, serviced annually, last done March 2024)',
            ],
            'water_heater_age_type' => [
                'label'       => 'How old is the water heater, and what type is it?',
                'placeholder' => 'Enter water heater type and age (e.g., 50-gallon electric, 4 years old)',
            ],
            'recent_renovations_list' => [
                'label'       => 'What renovations or upgrades have been made, and when?',
                'placeholder' => 'Enter renovations with approximate dates (e.g., kitchen remodel 2021, new flooring 2022, roof replaced 2019)',
            ],
            'permits_for_renovations' => [
                'label'       => 'Were all renovations and additions completed with proper permits?',
                'placeholder' => 'Enter permit details (e.g., all work permitted and closed; addition permitted in 2020)',
            ],
            'known_defects_issues' => [
                'label'       => 'Are there any known defects, issues, or deferred repairs?',
                'placeholder' => 'Enter any known issues (e.g., minor crack in driveway, guest bath faucet drips)',
            ],
            'foundation_type_and_issues' => [
                'label'       => 'What type of foundation does the property have, and are there any known issues?',
                'placeholder' => 'Enter foundation type and condition (e.g., concrete slab, no known issues; block foundation, minor settling)',
            ],
            'pest_termite_history' => [
                'label'       => 'Has the property ever had pest or termite issues, and how were they resolved?',
                'placeholder' => 'Enter pest history (e.g., treated for termites in 2018, no recurrence; no known history)',
            ],
            'flood_damage_history' => [
                'label'       => 'Has the property ever flooded or experienced water damage?',
                'placeholder' => 'Enter flood or water damage history (e.g., no flood history; minor water intrusion in garage 2020, remediated)',
            ],
            'mold_issues_history' => [
                'label'       => 'Has the property ever had mold issues, and how were they addressed?',
                'placeholder' => 'Enter mold history (e.g., no known mold history; bathroom mold found and remediated 2019)',
            ],
        ],

        'Financial & Utility Insights' => [
            'average_utility_costs' => [
                'label'       => 'What are the average monthly utility costs (electric, gas, water)?',
                'placeholder' => 'Enter average monthly costs (e.g., electric ~$120, water ~$40, gas ~$30 in summer)',
            ],
            'internet_utility_providers' => [
                'label'       => 'Which internet and utility providers serve this property?',
                'placeholder' => 'Enter providers (e.g., Xfinity and AT&T Fiber available; FPL for electric, TECO for gas)',
            ],
            'seller_concessions_offered' => [
                'label'       => 'Are you open to offering seller concessions or repair credits?',
                'placeholder' => 'Enter concession flexibility (e.g., open to closing cost credits up to 2%; prefer as-is but will consider)',
            ],
            'price_reduction_history' => [
                'label'       => 'Has the listing price been adjusted since going to market, and if so, why?',
                'placeholder' => 'Enter price history context (e.g., reduced $10K after first month to reflect updated comps)',
            ],
        ],

        'Location & Lifestyle' => [
            'neighborhood_character' => [
                'label'       => 'How would you describe the neighborhood vibe and community feel?',
                'placeholder' => 'Enter neighborhood description (e.g., quiet, family-friendly cul-de-sac; active HOA with events; mix of retirees and young families)',
            ],
            'traffic_or_noise_concerns' => [
                'label'       => 'Are there any notable traffic, noise, or nuisance concerns nearby?',
                'placeholder' => 'Enter any concerns (e.g., occasional train noise at night; light traffic even on weekends; none)',
            ],
            'planned_nearby_development' => [
                'label'       => 'Are there any planned developments, road projects, or zoning changes nearby?',
                'placeholder' => 'Enter known developments (e.g., new shopping center planned 1 mile away; road widening on main street; none known)',
            ],
            'commute_options_access' => [
                'label'       => 'What are typical commute options and travel times to major employment centers?',
                'placeholder' => 'Enter commute details (e.g., 20 min to downtown, 10 min to I-275 on-ramp; near Park-n-Ride)',
            ],
            'natural_light_orientation' => [
                'label'       => 'How is the natural light throughout the day, and which direction does the home face?',
                'placeholder' => 'Enter light and orientation details (e.g., south-facing rear with afternoon sun in pool area; bright open kitchen all day)',
            ],
            'nearby_amenities_description' => [
                'label'       => 'What nearby amenities, shops, or dining do you value most about this location?',
                'placeholder' => 'Enter nearby amenities (e.g., Publix 5 min away, restaurants on Main Street, dog park 2 blocks away)',
            ],
            'neighborhood_restrictions' => [
                'label'       => 'Are there deed restrictions or neighborhood rules buyers should know about?',
                'placeholder' => 'Enter restrictions (e.g., no short-term rentals per HOA; deed-restricted community, no RV parking)',
            ],
        ],

        'Flexibility & Negotiation' => [
            'closing_timeline_flexibility' => [
                'label'       => 'How flexible are you on the closing date?',
                'placeholder' => 'Enter closing flexibility (e.g., need at least 45 days; can close in 21–90 days; prefer 60 days)',
            ],
            'seller_leaseback_option' => [
                'label'       => 'Would you consider a seller leaseback arrangement after closing?',
                'placeholder' => 'Enter leaseback preference (e.g., would consider up to 30 days at market rent; not interested)',
            ],
            'items_excluded_from_sale' => [
                'label'       => 'Are there any fixtures, appliances, or personal items excluded from the sale?',
                'placeholder' => 'Enter excluded items (e.g., dining room chandelier and garage fridge are excluded; all else conveys)',
            ],
            'furniture_negotiability' => [
                'label'       => 'Is any furniture or staging negotiable as part of the sale?',
                'placeholder' => 'Enter furniture details (e.g., outdoor patio set and sectional sofa available separately; staged furniture not included)',
            ],
            'as_is_condition' => [
                'label'       => 'Is this being sold as-is, or are you open to repairs based on inspection findings?',
                'placeholder' => 'Enter as-is stance (e.g., selling as-is but open to credits for major systems; open to reasonable repairs up to $5K)',
            ],
            'environmental_concerns' => [
                'label'       => 'Are there any known environmental concerns or use restrictions on the property?',
                'placeholder' => 'Enter environmental details (e.g., no known concerns; property in AE flood zone, flood insurance required)',
            ],
        ],

        'Hidden Selling Points' => [
            'unique_selling_points' => [
                'label'       => 'What features or qualities won\'t be obvious from the listing photos alone?',
                'placeholder' => 'Enter hidden highlights (e.g., extremely low utility bills due to solar; neighborhood is incredibly quiet at night; backs to conservation — no rear neighbors)',
            ],
            'seller_favorite_features' => [
                'label'       => 'What aspects of this home will you miss the most after selling?',
                'placeholder' => 'Enter favorite features (e.g., the morning light in the kitchen, the backyard privacy, proximity to the trail)',
            ],
            'seller_motivation_for_selling' => [
                'label'       => 'Is there anything about your reason for selling you\'d like buyers to know?',
                'placeholder' => 'Enter context if desired (e.g., relocating for work; downsizing after kids left; optional — leave blank if preferred)',
            ],
            'move_in_ready_status' => [
                'label'       => 'Is the property move-in ready, or would buyers want to plan for any updates?',
                'placeholder' => 'Enter move-in status (e.g., fully move-in ready; cosmetically dated but all systems solid; buyers may want to update bathrooms)',
            ],
            'parking_arrangements' => [
                'label'       => 'What parking is available — garage spaces, driveway length, street access?',
                'placeholder' => 'Enter parking details (e.g., 2-car garage plus 4-car driveway; tandem garage with extra storage)',
            ],
            'storage_space_available' => [
                'label'       => 'What storage options are available — attic, garage, shed, extra closets?',
                'placeholder' => 'Enter storage details (e.g., walk-up attic with flooring, oversized garage with built-in shelving, large shed)',
            ],
            'hoa_community_highlights' => [
                'label'       => 'What do you love most about the community or HOA amenities here?',
                'placeholder' => 'Enter community highlights (e.g., resort pool, low HOA, great neighbors, active social events)',
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
                    'placeholder' => 'Enter NOI (e.g., $85,000/year based on current rents and expenses)',
                ],
                'current_cap_rate' => [
                    'label'       => 'What is the current capitalization rate?',
                    'placeholder' => 'Enter cap rate (e.g., approximately 6.2% at asking price)',
                ],
                'existing_tenant_lease_terms' => [
                    'label'       => 'What are the existing tenant lease terms, rents, and expiration dates?',
                    'placeholder' => 'Enter lease summary (e.g., 4 units: 2 on annual leases expiring Dec 2025, 2 month-to-month at $1,400/mo)',
                ],
                'current_occupancy_rate' => [
                    'label'       => 'What is the current occupancy rate?',
                    'placeholder' => 'Enter occupancy (e.g., 100% occupied; 1 of 4 units currently vacant)',
                ],
                'annual_operating_expenses_detail' => [
                    'label'       => 'What are the main annual operating expenses (taxes, insurance, management, maintenance)?',
                    'placeholder' => 'Enter expense breakdown (e.g., taxes $6K, insurance $3.2K, management 8%, maintenance avg $4K/yr)',
                ],
                'value_add_opportunities' => [
                    'label'       => 'Are there value-add opportunities — below-market rents, unused units, or renovation upside?',
                    'placeholder' => 'Enter upside details (e.g., rents are 10–15% below market; unit 3 could be renovated for premium rent)',
                ],
            ],
        ],

        'business_opportunity' => [
            'label'       => 'Business Opportunity Questions',
            'visible_for' => ['Business Opportunity'],
            'questions'   => [
                'annual_business_revenue' => [
                    'label'       => 'What is the current annual gross revenue?',
                    'placeholder' => 'Enter revenue (e.g., $420,000 gross revenue last fiscal year)',
                ],
                'annual_net_profit' => [
                    'label'       => 'What is the approximate annual net profit or owner\'s discretionary earnings?',
                    'placeholder' => 'Enter profit (e.g., ~$95,000 SDE; details available with NDA)',
                ],
                'business_reason_for_selling' => [
                    'label'       => 'Why is the business being sold?',
                    'placeholder' => 'Enter reason (e.g., owner retiring after 20 years; relocating out of state)',
                ],
                'business_employee_count' => [
                    'label'       => 'How many employees does the business have, and will they stay on?',
                    'placeholder' => 'Enter employee details (e.g., 6 FT employees, all willing to stay; 2 PT staff)',
                ],
                'seller_training_transition' => [
                    'label'       => 'How much training or transition support will the seller provide?',
                    'placeholder' => 'Enter transition terms (e.g., seller will train for 60 days post-close; open to extended consulting)',
                ],
                'business_lease_status' => [
                    'label'       => 'What is the status of the business location lease — term remaining and assignability?',
                    'placeholder' => 'Enter lease details (e.g., 3 years remaining, assignable with landlord approval; month-to-month)',
                ],
                'inventory_equipment_included' => [
                    'label'       => 'What inventory and equipment are included in the sale price?',
                    'placeholder' => 'Enter included assets (e.g., all equipment and ~$30K in inventory included; FF&E valued at $85K)',
                ],
            ],
        ],

        'vacant_land' => [
            'label'       => 'Vacant Land Questions',
            'visible_for' => ['Vacant Land'],
            'questions'   => [
                'land_utilities_availability' => [
                    'label'       => 'What utilities are available or accessible on the land?',
                    'placeholder' => 'Enter utility details (e.g., electric and water at road; septic required; no utilities — well and septic needed)',
                ],
                'land_zoning_permitted_uses' => [
                    'label'       => 'What is the current zoning designation and what uses are permitted?',
                    'placeholder' => 'Enter zoning (e.g., zoned R-1 single family; agricultural with residential allowed; C-2 commercial)',
                ],
                'land_access_and_road' => [
                    'label'       => 'What road access does the parcel have — paved road frontage, dirt road, or easement?',
                    'placeholder' => 'Enter access details (e.g., paved county road frontage; access via recorded easement through neighbor)',
                ],
                'land_soil_and_topography' => [
                    'label'       => 'Are there any known soil, topography, flood zone, or wetland considerations?',
                    'placeholder' => 'Enter land conditions (e.g., flat, high and dry; northeast corner in AE flood zone; soil perked for septic)',
                ],
                'land_survey_available' => [
                    'label'       => 'Is a current survey available, and has the land been cleared or improved?',
                    'placeholder' => 'Enter survey and clearing details (e.g., survey from 2022 available; partially cleared and rough-graded)',
                ],
                'land_development_restrictions' => [
                    'label'       => 'Are there any deed restrictions, easements, or development restrictions buyers should know?',
                    'placeholder' => 'Enter restrictions (e.g., 25-ft conservation easement along eastern edge; no deed restrictions; HOA approval required for structures)',
                ],
            ],
        ],

    ],

];
