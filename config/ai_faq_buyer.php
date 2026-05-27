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
    |   key => ['label' => '...', 'placeholder' => '...', 'tooltip' => '...']
    |
    | Compatible with offer-listing/shared/ai-questions-input.blade.php
    |
    */

    'questions' => [

        'Buyer Intent & Lifestyle' => [
            'buyer_motivation' => [
                'label'       => 'What\'s driving your decision to buy right now?',
                'placeholder' => 'Enter motivation (e.g., Growing family needs more space, Tired of renting, Relocating for a new job)',
                'tooltip'     => 'Helps the AI understand the urgency and context behind the search to match suitable listings.',
            ],
            'buyer_lifestyle_goals' => [
                'label'       => 'How do you envision using this property — quiet retreat, entertainers\' home, short-term rental, investment?',
                'placeholder' => 'Enter lifestyle vision (e.g., Want to entertain and host guests often, Plan to use as primary residence long-term, Looking for a quiet retreat from a busy schedule)',
                'tooltip'     => 'Lets the AI recommend properties and answer questions based on how the buyer plans to use the home.',
            ],
            'buyer_deal_breakers' => [
                'label'       => 'What are your absolute deal-breakers — things that would take a property off the list immediately?',
                'placeholder' => 'Enter deal-breakers (e.g., No HOA whatsoever, Must have a garage, Cannot be near a busy road, Top-rated school zone required)',
                'tooltip'     => 'Helps the AI filter out unsuitable properties and accurately communicate non-negotiable criteria.',
            ],
            'buyer_renovation_tolerance' => [
                'label'       => 'Would you consider a property that needs work, or are you looking for move-in ready?',
                'placeholder' => 'Enter renovation stance (e.g., Open to cosmetic updates but not major systems, Must be fully move-in ready, Happy to take on a project for the right price)',
                'tooltip'     => 'Lets the AI prioritize move-in ready versus fixer-upper options when answering listing questions.',
            ],
            'buyer_wfh_needs' => [
                'label'       => 'Do you work from home? If so, what does your ideal home office setup look like?',
                'placeholder' => 'Enter WFH requirements (e.g., Need a dedicated private office, Work from home 3 days a week, Not applicable)',
                'tooltip'     => 'Helps the AI evaluate whether a given property\'s layout supports the buyer\'s remote work requirements.',
            ],
            'buyer_outdoor_space' => [
                'label'       => 'How important is outdoor space, and what would you ideally have?',
                'placeholder' => 'Enter outdoor priorities (e.g., Need a fenced yard for dogs, Pool is a strong preference, Covered patio for entertaining)',
                'tooltip'     => 'Lets the AI factor outdoor priorities into property recommendations and answers.',
            ],
            'buyer_long_term_goals' => [
                'label'       => 'Is this a forever home, a starter home, or an investment? What\'s your longer-term plan?',
                'placeholder' => 'Enter long-term intent (e.g., Planning to stay 10+ years, Starter home will likely move in 5 years, Buy-and-hold investment)',
                'tooltip'     => 'Helps the AI contextualize the buyer\'s criteria against their intended ownership timeline.',
            ],
            'buyer_biggest_concern' => [
                'label'       => 'What\'s your biggest concern or hesitation about this purchase right now?',
                'placeholder' => 'Enter biggest concern (e.g., Worried about overpaying in this market, Concerned about overall property condition, Timing with current home sale)',
                'tooltip'     => 'Lets the AI address the buyer\'s top hesitation proactively in property comparisons.',
            ],
        ],

        'Location & Community' => [
            'buyer_neighborhood_preferences' => [
                'label'       => 'What kind of neighborhood feel are you looking for — walkable, suburban, quiet, urban, rural?',
                'placeholder' => 'Enter neighborhood preference (e.g., Quiet suburban with good sidewalks, Walkable to shops and restaurants, Rural with some land)',
                'tooltip'     => 'Helps the AI match neighborhoods to the buyer\'s lifestyle and commute preferences.',
            ],
            'buyer_school_district' => [
                'label'       => 'Is a specific school district a hard requirement, or a preference?',
                'placeholder' => 'Enter school district stance (e.g., Hard requirement — must be in an A-rated district, Prefer strong schools but flexible, Not a factor in our decision)',
                'tooltip'     => 'Lets the AI filter or flag listings based on school district when answering buyer questions.',
            ],
            'buyer_commute_requirements' => [
                'label'       => 'Do you have commute distance or public transit requirements?',
                'placeholder' => 'Enter commute needs (e.g., Need to be within 30 min of downtown, Must have public transit access, No commute constraints)',
                'tooltip'     => 'Helps the AI evaluate whether a property\'s location meets the buyer\'s travel needs.',
            ],
            'buyer_noise_tolerance' => [
                'label'       => 'How sensitive are you to noise — would proximity to highways, airports, or schools be a concern?',
                'placeholder' => 'Enter noise sensitivity (e.g., Very sensitive — no busy roads or flight paths, Fine with moderate neighborhood noise, Not a concern at all)',
                'tooltip'     => 'Lets the AI factor proximity to highways, airports, or busy roads into property assessments.',
            ],
            'buyer_area_familiarity' => [
                'label'       => 'How familiar are you with the neighborhoods you\'re considering?',
                'placeholder' => 'Enter familiarity level (e.g., Lived here 10 years and know every neighborhood, Relocating and relying entirely on agent guidance, Visited the area twice)',
                'tooltip'     => 'Helps the AI calibrate how much context and neighborhood detail to include in answers.',
            ],
            'buyer_prefers_off_market' => [
                'label'       => 'Are you open to off-market or pocket listings if the right property came along?',
                'placeholder' => 'Enter off-market openness (e.g., Absolutely — please share anything available, Prefer MLS listings only, Open to it if the price is right)',
                'tooltip'     => 'Lets the AI accurately represent whether the buyer is open to non-MLS opportunities.',
            ],
        ],

        'Property Preferences' => [
            'buyer_property_style' => [
                'label'       => 'Do you have a preference for architectural style or the age/era of the home?',
                'placeholder' => 'Enter style preference (e.g., Love mid-century modern character, Prefer newer builds from 2010 onward, No strong preference on style or era)',
                'tooltip'     => 'Helps the AI match architectural and construction era preferences when discussing specific listings.',
            ],
            'buyer_must_have_features' => [
                'label'       => 'What are your absolute must-have property features?',
                'placeholder' => 'Enter must-haves (e.g., Single-story layout required, Must have a garage, Private pool essential, Home office separate from main living areas)',
                'tooltip'     => 'Lets the AI immediately identify whether a given property meets the buyer\'s baseline requirements.',
            ],
            'buyer_nice_to_have' => [
                'label'       => 'What features are on your wish list but not deal-breakers?',
                'placeholder' => 'Enter nice-to-haves (e.g., Open concept kitchen, Updated primary bath, Bonus room or flex space, Water view)',
                'tooltip'     => 'Helps the AI score and rank listings based on bonus features the buyer values.',
            ],
            'buyer_hoa_acceptable' => [
                'label'       => 'Are you comfortable with an HOA community? Is there a maximum monthly fee you\'d consider?',
                'placeholder' => 'Enter HOA stance (e.g., Prefer no HOA, Comfortable with a modest monthly fee, Gated community is actually preferred)',
                'tooltip'     => 'Lets the AI flag HOA fees and restrictions when answering questions about a specific property.',
            ],
            'buyer_accessibility' => [
                'label'       => 'Do you need any accessibility features — single-story, ramps, wide doorways, roll-in shower?',
                'placeholder' => 'Enter accessibility needs (e.g., Must be single-story due to mobility needs, Wheelchair-accessible entry required, No accessibility needs)',
                'tooltip'     => 'Helps the AI identify properties that meet any accessibility requirements the buyer has.',
            ],
            'buyer_privacy_requirements' => [
                'label'       => 'Do you have specific privacy needs — fencing, larger lot, no rear neighbors?',
                'placeholder' => 'Enter privacy requirements (e.g., Need a fenced yard, Prefer conservation or no rear neighbors, Privacy not a major priority)',
                'tooltip'     => 'Lets the AI factor lot characteristics and surrounding land use into property answers.',
            ],
            'buyer_view_preference' => [
                'label'       => 'Is a specific view important to you — water, golf course, nature, city?',
                'placeholder' => 'Enter view preference (e.g., Water view is a strong priority, Golf course frontage preferred, No specific view required)',
                'tooltip'     => 'Helps the AI highlight or filter for view-related features when comparing listings.',
            ],
        ],

        'Buyer Situation & Flexibility' => [
            'buyer_current_situation' => [
                'label'       => 'What\'s your current living situation — renting, owning, or relocating?',
                'placeholder' => 'Enter current situation (e.g., Currently renting month-to-month, Own a home that will be listed soon, Relocating from out of state)',
                'tooltip'     => 'Helps the AI understand the buyer\'s starting point and timing constraints.',
            ],
            'buyer_simultaneous_close' => [
                'label'       => 'Do you need to sell a current property and close simultaneously?',
                'placeholder' => 'Enter simultaneous close needs (e.g., Yes — need proceeds from sale of current home, No — cash buyer already approved, Selling first then buying)',
                'tooltip'     => 'Lets the AI accurately answer questions about the buyer\'s ability to close if a sale contingency applies.',
            ],
            'buyer_leaseback' => [
                'label'       => 'Would you allow the seller to stay on a short leaseback after closing if needed?',
                'placeholder' => 'Enter leaseback flexibility (e.g., Open to up to 30-day leaseback, Prefer immediate possession, Flexible on the timing)',
                'tooltip'     => 'Helps the AI set expectations with sellers about post-closing occupancy flexibility.',
            ],
            'buyer_relocation' => [
                'label'       => 'Are you relocating from another area? Will you be making decisions remotely?',
                'placeholder' => 'Enter relocation details (e.g., Relocating from Atlanta and may offer sight unseen, Local buyer familiar with the market, Temporarily working remotely)',
                'tooltip'     => 'Lets the AI provide additional context when the buyer may need to make decisions remotely.',
            ],
            'buyer_lost_deal' => [
                'label'       => 'Have you made offers on other properties that didn\'t work out? What happened?',
                'placeholder' => 'Enter prior deal history (e.g., Lost two offers in bidding wars, Inspection issues ended our last deal, First time submitting an offer)',
                'tooltip'     => 'Helps the AI understand what went wrong before and tailor property match expectations.',
            ],
            'buyer_seller_concessions' => [
                'label'       => 'Would you consider asking for seller concessions toward closing costs rather than a price reduction?',
                'placeholder' => 'Enter concession preference (e.g., Yes — prefer concessions toward closing costs, Prefer a clean lower purchase price, Open to either depending on the deal)',
                'tooltip'     => 'Lets the AI address negotiation strategy questions around closing costs and net price.',
            ],
            'buyer_flexibility' => [
                'label'       => 'How flexible are you on location, timing, or property type if the right deal came along?',
                'placeholder' => 'Enter flexibility level (e.g., Very flexible on neighborhood but firm on school district, Open to condos if the value is there, Mostly firm but will consider the right opportunity)',
                'tooltip'     => 'Helps the AI expand or refine the property search when a good opportunity falls slightly outside criteria.',
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
                    'placeholder' => 'Enter intended use (e.g., Owner-occupied medical office, Retail investment property, Mixed-use retail and residential)',
                    'tooltip'     => 'Lets the AI accurately answer questions about how the buyer plans to use the property.',
                ],
                'com_investment_type' => [
                    'label'       => 'Is this an investment purchase or owner-occupied?',
                    'placeholder' => 'Enter investment type (e.g., Pure investment seeking strong cap rate, Plan to operate our business from the space, Owner-occupy one unit and lease the rest)',
                    'tooltip'     => 'Helps the AI frame property analysis as an investment return or owner-occupancy evaluation.',
                ],
                'com_cap_rate_target' => [
                    'label'       => 'What cap rate is the buyer targeting?',
                    'placeholder' => 'Enter target cap rate (e.g., Minimum 6.5% cap rate required, Targeting 7%+ in this market, Flexible on rate for the right location and quality)',
                    'tooltip'     => 'Lets the AI evaluate whether a given property meets the buyer\'s return threshold.',
                ],
                'com_occupancy_rate' => [
                    'label'       => 'What minimum occupancy rate is required at the time of purchase?',
                    'placeholder' => 'Enter occupancy requirement (e.g., Must be at least 80% occupied, Prefer fully stabilized, Open to some vacancy if priced accordingly)',
                    'tooltip'     => 'Helps the AI flag listings that fall below the buyer\'s occupancy floor.',
                ],
                'com_lease_terms' => [
                    'label'       => 'What lease structure is preferred — NNN, gross, modified gross?',
                    'placeholder' => 'Enter lease structure preference (e.g., Prefer NNN to minimize management burden, Open to gross or modified gross if priced right, Flexible depending on quality of tenants)',
                    'tooltip'     => 'Lets the AI match the buyer\'s preferred lease structure to available listings.',
                ],
                'com_1031_exchange' => [
                    'label'       => 'Is the buyer completing a 1031 exchange with a timing requirement?',
                    'placeholder' => 'Enter exchange details (e.g., Yes — identified replacement and must close within 60 days, Not a 1031 exchange, Exchange window closing soon)',
                    'tooltip'     => 'Helps the AI flag timing constraints and qualify listings against 1031 exchange deadlines.',
                ],
                'com_due_diligence_period' => [
                    'label'       => 'How much due diligence time will the buyer need?',
                    'placeholder' => 'Enter due diligence needs (e.g., Need 30-day DD for financials, environmental, and inspection, Standard 15 days is acceptable, Extended period required for complex assets)',
                    'tooltip'     => 'Lets the AI set expectations with sellers about how long the buyer requires for inspections and review.',
                ],
                'com_environmental_concerns' => [
                    'label'       => 'Will the buyer require Phase I or Phase II environmental studies?',
                    'placeholder' => 'Enter environmental study requirements (e.g., Yes — Phase I required by lender, Buyer will order Phase I at own cost, Phase II may be needed depending on site history)',
                    'tooltip'     => 'Helps the AI communicate environmental study requirements to sellers upfront.',
                ],
            ],
        ],

        'business_opportunity' => [
            'label'       => 'Business Opportunity Add-On Questions',
            'visible_for' => ['Business Opportunity'],
            'questions'   => [
                'biz_type_seeking' => [
                    'label'       => 'What type of business is the buyer seeking?',
                    'placeholder' => 'Enter business type (e.g., Restaurant or food service concept, Retail with an established customer base, Service business with recurring revenue)',
                    'tooltip'     => 'Lets the AI match the buyer\'s business interest to available opportunities.',
                ],
                'biz_revenue_required' => [
                    'label'       => 'What minimum annual revenue is required?',
                    'placeholder' => 'Enter revenue floor (e.g., Minimum $500K gross revenue, At least $1M annual revenue, Growth-stage business welcome if trend is strong)',
                    'tooltip'     => 'Helps the AI filter out businesses that fall below the buyer\'s revenue threshold.',
                ],
                'biz_profit_required' => [
                    'label'       => 'What minimum net profit or owner\'s discretionary earnings (SDE) is required?',
                    'placeholder' => 'Enter profit requirement (e.g., Need at least $80K in seller discretionary earnings, Minimum $150K net profit required, Flexible if the brand and growth story are compelling)',
                    'tooltip'     => 'Lets the AI evaluate whether a business meets the buyer\'s income requirements.',
                ],
                'biz_training_expected' => [
                    'label'       => 'How much seller training or transition support is expected?',
                    'placeholder' => 'Enter training expectations (e.g., Expect 90 days of hands-on training, Need seller to personally introduce key accounts, Two-week overlap acceptable for simple operations)',
                    'tooltip'     => 'Helps the AI set expectations with sellers about transition support requirements.',
                ],
                'biz_staff_included' => [
                    'label'       => 'Will existing staff be retained as part of the purchase?',
                    'placeholder' => 'Enter staff expectations (e.g., Key employees expected to stay on, Open to running with a leaner team, Staff continuity is critical to our acquisition)',
                    'tooltip'     => 'Lets the AI address staffing continuity questions when evaluating a business acquisition.',
                ],
                'biz_non_compete' => [
                    'label'       => 'Is a non-compete agreement required from the seller?',
                    'placeholder' => 'Enter non-compete needs (e.g., Yes — need 3-year non-compete in same market, Standard non-compete is expected, Willing to negotiate scope and duration)',
                    'tooltip'     => 'Helps the AI communicate competitive protection requirements to sellers upfront.',
                ],
                'biz_sba_financing' => [
                    'label'       => 'Is the buyer considering SBA financing for this acquisition?',
                    'placeholder' => 'Enter financing approach (e.g., Yes — pre-qualified for SBA 7(a) financing, Cash purchase, Seeking partial seller financing)',
                    'tooltip'     => 'Lets the AI flag any SBA eligibility or documentation requirements that may affect the deal.',
                ],
            ],
        ],

        'vacant_land' => [
            'label'       => 'Vacant Land Add-On Questions',
            'visible_for' => ['Vacant Land'],
            'questions'   => [
                'land_intended_use' => [
                    'label'       => 'What is the intended use of the vacant land?',
                    'placeholder' => 'Enter intended use (e.g., Build our primary residence, Agricultural and farming use, Commercial or light industrial development, Subdivide and resell individual lots)',
                    'tooltip'     => 'Helps the AI evaluate whether a parcel\'s zoning and characteristics match the buyer\'s plans.',
                ],
                'land_zoning_required' => [
                    'label'       => 'What zoning classification is required?',
                    'placeholder' => 'Enter zoning requirement (e.g., Residential R-1 or R-2 required, Agricultural zoning acceptable, Commercial C-2 or higher needed)',
                    'tooltip'     => 'Lets the AI filter land listings by zoning to match what the buyer legally needs.',
                ],
                'land_utilities_needed' => [
                    'label'       => 'What utilities need to be available or accessible at the property?',
                    'placeholder' => 'Enter utility requirements (e.g., Need electric and water at road minimum, Prefer municipal sewer connection, Off-grid setup is acceptable)',
                    'tooltip'     => 'Helps the AI assess whether a parcel\'s utility situation meets the buyer\'s development needs.',
                ],
                'land_soil_testing' => [
                    'label'       => 'Will the buyer require soil, perc, or environmental testing as a condition?',
                    'placeholder' => 'Enter testing requirements (e.g., Yes — perc test contingency required for septic, Phase I environmental review required, Soil testing for structural feasibility needed)',
                    'tooltip'     => 'Lets the AI communicate testing contingencies to sellers when evaluating land offers.',
                ],
                'land_build_timeline' => [
                    'label'       => 'What is the buyer\'s expected building or development timeline?',
                    'placeholder' => 'Enter timeline (e.g., Plan to break ground within 12 months, Long-term investment with no immediate plans, Begin permitting as soon as possible)',
                    'tooltip'     => 'Helps the AI understand urgency and match the buyer to listings with appropriate access and readiness.',
                ],
                'land_access_requirements' => [
                    'label'       => 'What road access or easement requirements does the buyer have?',
                    'placeholder' => 'Enter access needs (e.g., Must have direct paved road frontage, Recorded access easement is acceptable, Gated or private road is fine)',
                    'tooltip'     => 'Lets the AI flag parcels with access limitations that would not meet the buyer\'s requirements.',
                ],
                'land_topography' => [
                    'label'       => 'Are there flood zone, elevation, or topography requirements?',
                    'placeholder' => 'Enter topography requirements (e.g., Must be high and dry — no flood zone, Flat and cleared land preferred, Gentle slope acceptable for hillside build)',
                    'tooltip'     => 'Helps the AI filter out parcels with flood zone or terrain issues that conflict with the buyer\'s plans.',
                ],
            ],
        ],

    ],

];
