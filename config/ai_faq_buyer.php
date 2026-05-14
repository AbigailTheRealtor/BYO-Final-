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
    | 'listing_ai_faq_' prefix. Commercial add-on keys use 'com_' prefix.
    | Business Opportunity add-on keys use 'biz_' prefix.
    | Vacant Land add-on keys use 'land_' prefix.
    |
    | Each question entry uses the array shape:
    |   key => ['label' => '...', 'placeholder' => '...']
    |
    | Compatible with offer-listing/shared/ai-questions-input.blade.php
    |
    */

    'questions' => [

        'Buyer Intent & Lifestyle' => [
            'buyer_motivation' => [
                'label'       => 'What\'s driving your decision to buy right now?',
                'placeholder' => 'Enter motivation (e.g., growing family needs more space; tired of renting; relocating for new job)',
            ],
            'buyer_lifestyle_goals' => [
                'label'       => 'How do you envision using this property — quiet retreat, entertainers\' home, short-term rental, investment?',
                'placeholder' => 'Enter lifestyle vision (e.g., want a home to entertain family; plan to use as primary residence long-term)',
            ],
            'buyer_deal_breakers' => [
                'label'       => 'What are your absolute deal-breakers — things that would take a property off the list immediately?',
                'placeholder' => 'Enter deal-breakers (e.g., no HOA; must have garage; cannot be near a busy road; must be in top school zone)',
            ],
            'buyer_renovation_tolerance' => [
                'label'       => 'Would you consider a property that needs work, or are you looking for move-in ready?',
                'placeholder' => 'Enter renovation stance (e.g., open to cosmetic updates but not major systems; must be fully move-in ready)',
            ],
            'buyer_wfh_needs' => [
                'label'       => 'Do you work from home? If so, what does your ideal home office setup look like?',
                'placeholder' => 'Enter WFH requirements (e.g., need a dedicated private office; work from home 3 days a week; not applicable)',
            ],
            'buyer_outdoor_space' => [
                'label'       => 'How important is outdoor space, and what would you ideally have?',
                'placeholder' => 'Enter outdoor priorities (e.g., need a fenced yard for dogs; pool is a strong preference; patio for entertaining)',
            ],
            'buyer_long_term_goals' => [
                'label'       => 'Is this a forever home, a starter home, or an investment? What\'s your longer-term plan?',
                'placeholder' => 'Enter long-term intent (e.g., planning to stay 10+ years; starter home, will move in 5 years; buy and hold rental)',
            ],
            'buyer_biggest_concern' => [
                'label'       => 'What\'s your biggest concern or hesitation about this purchase right now?',
                'placeholder' => 'Enter biggest concern (e.g., worried about overpaying in current market; concerned about condition; timing with current home sale)',
            ],
        ],

        'Location & Community' => [
            'buyer_neighborhood_preferences' => [
                'label'       => 'What kind of neighborhood feel are you looking for — walkable, suburban, quiet, urban, rural?',
                'placeholder' => 'Enter neighborhood preference (e.g., quiet suburban with good sidewalks; walkable to shops and restaurants; rural with land)',
            ],
            'buyer_school_district' => [
                'label'       => 'Is a specific school district a hard requirement, or a preference?',
                'placeholder' => 'Enter school district stance (e.g., hard requirement — must be in Hillsborough A-rated district; prefer but flexible)',
            ],
            'buyer_commute_requirements' => [
                'label'       => 'Do you have commute distance or public transit requirements?',
                'placeholder' => 'Enter commute needs (e.g., need to be within 30 min of downtown Tampa; must have Metrobus access)',
            ],
            'buyer_noise_tolerance' => [
                'label'       => 'How sensitive are you to noise — would proximity to highways, airports, or schools be a concern?',
                'placeholder' => 'Enter noise sensitivity (e.g., very sensitive — no busy roads; fine with moderate noise; not a concern)',
            ],
            'buyer_area_familiarity' => [
                'label'       => 'How familiar are you with the neighborhoods you\'re considering?',
                'placeholder' => 'Enter familiarity level (e.g., lived in area for 10 years; relocating and relying on agent guidance; visited twice)',
            ],
            'buyer_prefers_off_market' => [
                'label'       => 'Are you open to off-market or pocket listings if the right property came along?',
                'placeholder' => 'Enter off-market openness (e.g., absolutely — please share anything; prefer MLS only; open if priced fairly)',
            ],
        ],

        'Property Preferences' => [
            'buyer_property_style' => [
                'label'       => 'Do you have a preference for architectural style or the age/era of the home?',
                'placeholder' => 'Enter style preference (e.g., love mid-century modern; prefer newer construction 2010+; no strong preference)',
            ],
            'buyer_must_have_features' => [
                'label'       => 'What are your absolute must-have property features?',
                'placeholder' => 'Enter must-haves (e.g., at least 3 beds, 2 baths, 2-car garage, pool; single-story only)',
            ],
            'buyer_nice_to_have' => [
                'label'       => 'What features are on your wish list but not deal-breakers?',
                'placeholder' => 'Enter nice-to-haves (e.g., open kitchen, updated master bath, bonus room, lake view)',
            ],
            'buyer_hoa_acceptable' => [
                'label'       => 'Are you comfortable with an HOA community? Is there a maximum monthly fee you\'d consider?',
                'placeholder' => 'Enter HOA stance (e.g., prefer no HOA; fine with HOA up to $250/mo; gated community preferred)',
            ],
            'buyer_accessibility' => [
                'label'       => 'Do you need any accessibility features — single-story, ramps, wide doorways, roll-in shower?',
                'placeholder' => 'Enter accessibility needs (e.g., must be single-story due to mobility; wheelchair-accessible entry required; none)',
            ],
            'buyer_privacy_requirements' => [
                'label'       => 'Do you have specific privacy needs — fencing, larger lot, no rear neighbors?',
                'placeholder' => 'Enter privacy requirements (e.g., need a fenced yard; prefer conservation or no rear neighbors; privacy not a priority)',
            ],
            'buyer_view_preference' => [
                'label'       => 'Is a specific view important to you — water, golf course, nature, city?',
                'placeholder' => 'Enter view preference (e.g., water view is a strong priority; golf course frontage preferred; no specific view required)',
            ],
        ],

        'Buyer Situation & Flexibility' => [
            'buyer_current_situation' => [
                'label'       => 'What\'s your current living situation — renting, owning, or relocating?',
                'placeholder' => 'Enter current situation (e.g., currently renting month-to-month; own a home that will be listed soon; relocating from Chicago)',
            ],
            'buyer_simultaneous_close' => [
                'label'       => 'Do you need to sell a current property and close simultaneously?',
                'placeholder' => 'Enter simultaneous close needs (e.g., yes — need proceeds from sale of current home; no — cash or pre-approved)',
            ],
            'buyer_leaseback' => [
                'label'       => 'Would you allow the seller to stay on a short leaseback after closing if needed?',
                'placeholder' => 'Enter leaseback flexibility (e.g., open to up to 30-day leaseback; prefer immediate possession; flexible)',
            ],
            'buyer_relocation' => [
                'label'       => 'Are you relocating from another area? Will you be making decisions remotely?',
                'placeholder' => 'Enter relocation details (e.g., relocating from Atlanta, may need to make offer sight unseen; local buyer)',
            ],
            'buyer_lost_deal' => [
                'label'       => 'Have you made offers on other properties that didn\'t work out? What happened?',
                'placeholder' => 'Enter prior deal history (e.g., lost two offers in multiple-offer situations; inspection issue killed last deal)',
            ],
            'buyer_seller_concessions' => [
                'label'       => 'Would you consider asking for seller concessions toward closing costs rather than a price reduction?',
                'placeholder' => 'Enter concession preference (e.g., yes — prefer concessions to help with closing costs; prefer clean lower price)',
            ],
            'buyer_flexibility' => [
                'label'       => 'How flexible are you on location, timing, or property type if the right deal came along?',
                'placeholder' => 'Enter flexibility level (e.g., very flexible on neighborhood, firm on school district; open to condos if priced right)',
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
                'com_property_use' => [
                    'label'       => 'What is the intended use of the commercial or income property?',
                    'placeholder' => 'Enter intended use (e.g., owner-occupied medical office; retail investment; mixed-use with 2 commercial units)',
                ],
                'com_investment_type' => [
                    'label'       => 'Is this an investment purchase or owner-occupied?',
                    'placeholder' => 'Enter investment type (e.g., pure investment — looking for cap rate; plan to operate business from space)',
                ],
                'com_cap_rate_target' => [
                    'label'       => 'What cap rate is the buyer targeting?',
                    'placeholder' => 'Enter target cap rate (e.g., minimum 6.5% cap rate; targeting 7%+ in this market)',
                ],
                'com_occupancy_rate' => [
                    'label'       => 'What minimum occupancy rate is required at the time of purchase?',
                    'placeholder' => 'Enter occupancy requirement (e.g., must be at least 80% occupied; prefer fully occupied but open to some vacancy)',
                ],
                'com_lease_terms' => [
                    'label'       => 'What lease structure is preferred — NNN, gross, modified gross?',
                    'placeholder' => 'Enter lease structure preference (e.g., prefer NNN to minimize management; open to gross if priced accordingly)',
                ],
                'com_1031_exchange' => [
                    'label'       => 'Is the buyer completing a 1031 exchange with a timing requirement?',
                    'placeholder' => 'Enter exchange details (e.g., yes — identified replacement property, need to close within 60 days; not a 1031)',
                ],
                'com_due_diligence_period' => [
                    'label'       => 'How much due diligence time will the buyer need?',
                    'placeholder' => 'Enter due diligence needs (e.g., need 30-day DD period for financials, environmental, and inspection)',
                ],
                'com_environmental_concerns' => [
                    'label'       => 'Will the buyer require Phase I or Phase II environmental studies?',
                    'placeholder' => 'Enter environmental study requirements (e.g., yes — Phase I required for lender; buyer will order at own cost)',
                ],
            ],
        ],

        'business_opportunity' => [
            'label'       => 'Business Opportunity Add-On Questions',
            'visible_for' => ['Business Opportunity'],
            'questions'   => [
                'biz_type_seeking' => [
                    'label'       => 'What type of business is the buyer seeking?',
                    'placeholder' => 'Enter business type (e.g., restaurant or food service; retail with established customer base; service business with recurring revenue)',
                ],
                'biz_revenue_required' => [
                    'label'       => 'What minimum annual revenue is required?',
                    'placeholder' => 'Enter revenue floor (e.g., minimum $500K gross; at least $1M annual revenue)',
                ],
                'biz_profit_required' => [
                    'label'       => 'What minimum net profit or owner\'s discretionary earnings (SDE) is required?',
                    'placeholder' => 'Enter profit requirement (e.g., need at least $80K SDE; minimum $150K net)',
                ],
                'biz_training_expected' => [
                    'label'       => 'How much seller training or transition support is expected?',
                    'placeholder' => 'Enter training expectations (e.g., expect 90-day training; need seller to introduce to key accounts)',
                ],
                'biz_staff_included' => [
                    'label'       => 'Will existing staff be retained as part of the purchase?',
                    'placeholder' => 'Enter staff expectations (e.g., key employees expected to stay; open to running with smaller team)',
                ],
                'biz_non_compete' => [
                    'label'       => 'Is a non-compete agreement required from the seller?',
                    'placeholder' => 'Enter non-compete needs (e.g., yes — need 3-year non-compete in same market; standard non-compete expected)',
                ],
                'biz_sba_financing' => [
                    'label'       => 'Is the buyer considering SBA financing for this acquisition?',
                    'placeholder' => 'Enter financing approach (e.g., yes — pre-qualified for SBA 7(a); cash purchase; seeking seller financing)',
                ],
            ],
        ],

        'vacant_land' => [
            'label'       => 'Vacant Land Add-On Questions',
            'visible_for' => ['Vacant Land'],
            'questions'   => [
                'land_intended_use' => [
                    'label'       => 'What is the intended use of the vacant land?',
                    'placeholder' => 'Enter intended use (e.g., build primary residence; agricultural use; commercial development; subdivide and resell)',
                ],
                'land_zoning_required' => [
                    'label'       => 'What zoning classification is required?',
                    'placeholder' => 'Enter zoning requirement (e.g., residential R-1 or R-2; agricultural; commercial C-2 or higher)',
                ],
                'land_utilities_needed' => [
                    'label'       => 'What utilities need to be available or accessible at the property?',
                    'placeholder' => 'Enter utility requirements (e.g., need electric and water at road minimum; prefer municipal sewer; off-grid is acceptable)',
                ],
                'land_soil_testing' => [
                    'label'       => 'Will the buyer require soil, perc, or environmental testing as a condition?',
                    'placeholder' => 'Enter testing requirements (e.g., yes — perc test contingency for septic; Phase I environmental required)',
                ],
                'land_build_timeline' => [
                    'label'       => 'What is the buyer\'s expected building or development timeline?',
                    'placeholder' => 'Enter timeline (e.g., plan to break ground within 12 months; long-term investment, no immediate development plans)',
                ],
                'land_access_requirements' => [
                    'label'       => 'What road access or easement requirements does the buyer have?',
                    'placeholder' => 'Enter access needs (e.g., must have direct paved road frontage; recorded easement acceptable)',
                ],
                'land_topography' => [
                    'label'       => 'Are there flood zone, elevation, or topography requirements?',
                    'placeholder' => 'Enter topography requirements (e.g., must be high and dry, not in flood zone; flat and cleared preferred)',
                ],
            ],
        ],

    ],

];
