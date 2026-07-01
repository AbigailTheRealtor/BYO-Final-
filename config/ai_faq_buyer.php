<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Buyer Criteria AI / Chatbot Knowledge Base Questions
    |--------------------------------------------------------------------------
    |
    | Two-axis architecture (docs/ask-ai-kb-replacement-spec.md Part A): each
    | knowledge base = 'universal' + the group matching the buyer's target property
    | type, resolved via 'gating'. Residential criteria never leak into Income,
    | Commercial, Business, or Vacant Land.
    |
    | Audience: sellers / listing agents asking about the buyer. There is no subject
    | property — AI Insights educate about the buyer's stated profile/criteria
    | (Buyer DNA + criteria + Match), framed by needs/uses, never the person.
    | Compliance: the AI restates disclosed stances factually and performs NO
    | negotiating-position, leverage, or offer-strategy analysis for any party.
    |
    | Entry shape: key => [label, placeholder, tooltip, category_type, source].
    | Commercial keys use 'com_', Business 'biz_', Vacant Land 'land_'.
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
        // UNIVERSAL — all five property types
        // =====================================================================
        'universal' => [
            'Buyer Background' => [
                'buyer_motivation' => [
                    'label'         => 'What\'s driving the buyer\'s search right now?',
                    'placeholder'   => 'Enter motivation (e.g., Growing family needs more space, Tired of renting, Relocating for a job)',
                    'tooltip'       => 'Optional factual context the AI can restate; it performs no negotiating-position analysis.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'buyer_current_situation' => [
                    'label'         => 'What\'s the buyer\'s current living/ownership situation?',
                    'placeholder'   => 'Enter current situation (e.g., Renting month-to-month, Own a home to be listed soon, Relocating from out of state)',
                    'tooltip'       => 'Helps the AI restate the buyer\'s starting point factually.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'buyer_flexibility' => [
                    'label'         => 'How flexible is the buyer on timing or terms if the right property comes along?',
                    'placeholder'   => 'Enter flexibility (e.g., Flexible on neighborhood but firm on school district, Open to condos, Mostly firm)',
                    'tooltip'       => 'Factual disclosure of flexibility; the AI gives no negotiation coaching.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'buyer_biggest_concern' => [
                    'label'         => 'What\'s the buyer\'s biggest concern or hesitation?',
                    'placeholder'   => 'Enter biggest concern (e.g., Overall property condition, Timing with current home sale, Inspection findings)',
                    'tooltip'       => 'Lets the AI restate the buyer\'s top concern factually.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'buyer_relocation' => [
                    'label'         => 'Is the buyer relocating or making decisions remotely?',
                    'placeholder'   => 'Enter relocation details (e.g., Relocating from Atlanta, may decide remotely, Local buyer, Temporarily remote)',
                    'tooltip'       => 'Lets the AI provide context when the buyer may decide remotely.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'buyer_leaseback' => [
                    'label'         => 'Would the buyer allow a short seller leaseback after closing?',
                    'placeholder'   => 'Enter leaseback stance (e.g., Open to up to 30-day leaseback, Prefer immediate possession, Flexible)',
                    'tooltip'       => 'Factual stance only; the AI states the position and gives no negotiation advice.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'buyer_financing_context' => [
                    'label'         => 'Is there anything about how the buyer is financing the purchase worth knowing?',
                    'placeholder'   => 'Enter financing context (e.g., Cash reserves for a fast close, Gift funds documented, Self-employed with 2 years of returns, Proceeds from a home under contract)',
                    'tooltip'       => 'The listing captures the financing type and pre-approval status as structured fields; this adds factual context (funds source, documentation) the AI can restate. It performs no negotiating-position or leverage analysis.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
            ],
            'Buyer Insights' => [
                'buyer_property_needs' => [
                    'label'         => 'What property needs and uses has this buyer described?',
                    'placeholder'   => 'Optional — the AI describes the buyer\'s stated property needs and intended uses',
                    'tooltip'       => 'The AI describes needs/uses only — never demographics or "type of person."',
                    'category_type' => 'insight',
                    'source'        => 'BuyerDNA+Match',
                ],
                'buyer_disclosed_summary' => [
                    'label'         => 'What has this buyer disclosed about their needs and timeline?',
                    'placeholder'   => 'Optional — the AI summarizes the buyer\'s disclosed needs and timeline',
                    'tooltip'       => 'Summarizes disclosed buyer details factually.',
                    'category_type' => 'insight',
                    'source'        => 'Field+KB',
                ],
                'buyer_location_factors' => [
                    'label'         => 'What location features matter to this buyer?',
                    'placeholder'   => 'Optional — the AI describes the buyer\'s stated location criteria (commute, preferred areas) objectively',
                    'tooltip'       => 'Objective location criteria only; no steering.',
                    'category_type' => 'insight',
                    'source'        => 'BuyerDNA+LocDNA',
                ],
            ],
        ],

        // =====================================================================
        // RESIDENTIAL
        // =====================================================================
        'residential' => [
            'Residential Preferences' => [
                'buyer_neighborhood_preferences' => [
                    'label'         => 'What kind of area setting is the buyer looking for?',
                    'placeholder'   => 'Enter objective setting preference (e.g., Walkable to shops, Quiet suburban with sidewalks, Rural with some land)',
                    'tooltip'       => 'Objective setting categories only — no demographic descriptors or "good area" language.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'buyer_school_district' => [
                    'label'         => 'Is a specific school district a requirement or preference?',
                    'placeholder'   => 'Enter school stance (e.g., Must be in a specific district, Prefer a particular district but flexible, Not a factor)',
                    'tooltip'       => 'Objective requirement only; the AI states the named district/boundary and gives no quality ranking.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'buyer_noise_tolerance' => [
                    'label'         => 'How sensitive is the buyer to noise?',
                    'placeholder'   => 'Enter noise sensitivity (e.g., No busy roads or flight paths, Fine with moderate noise, Not a concern)',
                    'tooltip'       => 'Lets the AI factor noise sensitivity into property answers.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'buyer_area_familiarity' => [
                    'label'         => 'How familiar is the buyer with the area?',
                    'placeholder'   => 'Enter familiarity (e.g., Lived here 10 years, Relocating and relying on agent guidance, Visited a few times)',
                    'tooltip'       => 'Helps the AI calibrate how much area context to include.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'buyer_prefers_off_market' => [
                    'label'         => 'Is the buyer open to off-market/pocket listings?',
                    'placeholder'   => 'Enter off-market openness (e.g., Yes please share anything, MLS only, Open if the price is right)',
                    'tooltip'       => 'Lets the AI represent whether the buyer is open to non-MLS opportunities.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'buyer_property_style' => [
                    'label'         => 'Does the buyer prefer a particular architectural style or era?',
                    'placeholder'   => 'Enter style preference (e.g., Mid-century character, Newer builds 2010+, No strong preference)',
                    'tooltip'       => 'Helps the AI match style and construction-era preferences.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'buyer_nice_to_have' => [
                    'label'         => 'What\'s on the buyer\'s wish list (nice-to-haves)?',
                    'placeholder'   => 'Enter nice-to-haves (e.g., Open-concept kitchen, Updated primary bath, Bonus/flex room, Water view)',
                    'tooltip'       => 'Distinct from native non-negotiables; helps the AI describe bonus features the buyer values.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'buyer_accessibility' => [
                    'label'         => 'Does the buyer need any accessibility features?',
                    'placeholder'   => 'Enter accessibility needs (e.g., Single-story, Wheelchair-accessible entry, No accessibility needs)',
                    'tooltip'       => 'Helps the AI identify properties that meet the buyer\'s accessibility requirements.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'buyer_privacy_requirements' => [
                    'label'         => 'What are the buyer\'s privacy preferences?',
                    'placeholder'   => 'Enter privacy needs (e.g., Fenced yard, No rear neighbors/conservation, Privacy not a priority)',
                    'tooltip'       => 'Lets the AI factor lot and surrounding land use into answers.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'buyer_lifestyle_goals' => [
                    'label'         => 'How does the buyer envision using the home?',
                    'placeholder'   => 'Enter intended use (e.g., Primary residence long-term, Entertain and host often, Quiet retreat)',
                    'tooltip'       => 'Lets the AI describe how the buyer plans to use the home (by use, not demographics).',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'buyer_outdoor_space' => [
                    'label'         => 'How important is outdoor space to the buyer?',
                    'placeholder'   => 'Enter outdoor priorities (e.g., Fenced yard for dogs, Pool preferred, Covered patio for entertaining)',
                    'tooltip'       => 'Lets the AI factor outdoor priorities into answers.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
            ],
        ],

        // =====================================================================
        // INCOME PROPERTY — factual; no investment advice
        // =====================================================================
        'income' => [
            'Investment Criteria' => [
                'buyer_income_intended_use' => [
                    'label'         => 'What\'s the buyer\'s intended hold strategy for the property?',
                    'placeholder'   => 'Enter strategy (e.g., Long-term buy-and-hold, Owner-occupy one unit and lease the rest, Reposition and re-lease)',
                    'tooltip'       => 'Lets the AI restate how the buyer plans to operate the income property.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'buyer_income_occupancy_requirement' => [
                    'label'         => 'What minimum in-place occupancy does the buyer require at purchase?',
                    'placeholder'   => 'Enter occupancy requirement (e.g., At least 80% occupied, Prefer fully stabilized, Open to value-add with vacancy)',
                    'tooltip'       => 'Helps the AI restate the buyer\'s occupancy requirement factually.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'buyer_income_rent_roll_expectations' => [
                    'label'         => 'What does the buyer expect from the existing rent roll and leases (in-place rents, term, escalations)?',
                    'placeholder'   => 'Enter rent-roll expectations (e.g., Wants current leases and estoppels, At/near-market rents, Some upside acceptable, Flexible on lease term)',
                    'tooltip'       => 'Income-specific diligence: the AI restates what the buyer wants to see in the rent roll; it gives no valuation or return advice.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'buyer_income_1031_exchange' => [
                    'label'         => 'Is the buyer completing a 1031 exchange with a timing requirement?',
                    'placeholder'   => 'Enter exchange details (e.g., Yes — must close within 60 days, Not a 1031 exchange, Window closing soon)',
                    'tooltip'       => 'Factual timing disclosure; the AI may define a 1031 exchange but gives no tax advice.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'buyer_income_environmental' => [
                    'label'         => 'Will the buyer require environmental or property-condition studies (Phase I/II, PCA)?',
                    'placeholder'   => 'Enter study requirements (e.g., Phase I required by lender, Buyer will order Phase I, Property-condition assessment planned)',
                    'tooltip'       => 'Helps the AI communicate diligence-study requirements factually.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
            ],
            'Buyer Insights' => [
                'buyer_income_property_fit' => [
                    'label'         => 'What type of income property fits this buyer\'s criteria?',
                    'placeholder'   => 'Optional — the AI describes the buyer\'s stated income-property criteria',
                    'tooltip'       => 'Neutral, criteria-based description; no investment framing.',
                    'category_type' => 'insight',
                    'source'        => 'BuyerDNA+Match',
                ],
            ],
        ],

        // =====================================================================
        // COMMERCIAL PROPERTY
        // =====================================================================
        'commercial' => [
            'Commercial Criteria' => [
                'buyer_commercial_intended_use' => [
                    'label'         => 'What\'s the buyer\'s intended use for the space, and will they owner-occupy?',
                    'placeholder'   => 'Enter intended use (e.g., Owner-occupied medical office, Retail with lease-back to seller, Light industrial for own operations)',
                    'tooltip'       => 'Lets the AI restate how the buyer plans to use the space and whether they intend to occupy it.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'buyer_commercial_space_requirements' => [
                    'label'         => 'What space, layout, or build-out characteristics does the buyer\'s use require?',
                    'placeholder'   => 'Enter requirements (e.g., Drive-through capability, 16-ft clear height, 3-phase power, Grade-level loading, Existing restaurant build-out)',
                    'tooltip'       => 'Owner-occupant diligence: the AI restates the physical characteristics the buyer\'s business needs.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'buyer_commercial_tenancy_preference' => [
                    'label'         => 'If leased-investment, what lease structure and tenancy does the buyer prefer?',
                    'placeholder'   => 'Enter preference (e.g., Prefer NNN with credit tenant, Open to owner-occupy vacant, Single-tenant preferred over multi-tenant)',
                    'tooltip'       => 'Lets the AI explain the buyer\'s preferred lease/tenancy structure; it may define terms but not advise.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'buyer_commercial_1031_exchange' => [
                    'label'         => 'Is the buyer completing a 1031 exchange with a timing requirement?',
                    'placeholder'   => 'Enter exchange details (e.g., Yes — must close within 60 days, Not a 1031 exchange, Window closing soon)',
                    'tooltip'       => 'Factual timing disclosure; the AI may define a 1031 exchange but gives no tax advice.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'buyer_commercial_environmental' => [
                    'label'         => 'Will the buyer require environmental or property-condition studies (Phase I/II, PCA)?',
                    'placeholder'   => 'Enter study requirements (e.g., Phase I required by lender, Buyer will order Phase I, Property-condition assessment planned)',
                    'tooltip'       => 'Helps the AI communicate diligence-study requirements factually.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
            ],
            'Buyer Insights' => [
                'buyer_commercial_space_fit' => [
                    'label'         => 'What type of commercial space fits this buyer\'s intended use?',
                    'placeholder'   => 'Optional — the AI describes the buyer\'s stated commercial-space criteria',
                    'tooltip'       => 'Neutral, use-based description.',
                    'category_type' => 'insight',
                    'source'        => 'BuyerDNA+Match',
                ],
            ],
        ],

        // =====================================================================
        // BUSINESS OPPORTUNITY — renders this group only (F6)
        // =====================================================================
        'business' => [
            'Business Criteria' => [
                'biz_revenue_required' => [
                    'label'         => 'What minimum revenue does the buyer require?',
                    'placeholder'   => 'Enter revenue floor (e.g., Minimum $500K gross, At least $1M annual, Growth-stage welcome if trend is strong)',
                    'tooltip'       => 'Helps the AI restate the buyer\'s revenue requirement factually.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'biz_training_expected' => [
                    'label'         => 'How much seller training/transition does the buyer expect?',
                    'placeholder'   => 'Enter training expectations (e.g., 90 days hands-on, Introduce key accounts, Two-week overlap)',
                    'tooltip'       => 'Helps the AI set expectations about transition support.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'biz_staff_included' => [
                    'label'         => 'Does the buyer want existing staff retained?',
                    'placeholder'   => 'Enter staff expectations (e.g., Key employees expected to stay, Open to a leaner team, Continuity is critical)',
                    'tooltip'       => 'Lets the AI address staffing-continuity expectations.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'biz_non_compete' => [
                    'label'         => 'Does the buyer require a non-compete from the seller?',
                    'placeholder'   => 'Enter non-compete needs (e.g., 3-year non-compete in same market, Standard expected, Willing to negotiate scope)',
                    'tooltip'       => 'Factual requirement; the AI restates it and gives no legal advice.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'biz_sba_financing' => [
                    'label'         => 'Is the buyer using SBA or seller financing?',
                    'placeholder'   => 'Enter financing approach (e.g., Pre-qualified for SBA 7(a), Cash purchase, Seeking partial seller financing)',
                    'tooltip'       => 'Factual disclosure; the AI may define SBA financing but gives no lending advice.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
            ],
            'Buyer Insights' => [
                'buyer_business_type_fit' => [
                    'label'         => 'What type of business is this buyer seeking?',
                    'placeholder'   => 'Optional — the AI describes the buyer\'s stated business-type criteria',
                    'tooltip'       => 'Neutral, criteria-based description from disclosed business-type selections.',
                    'category_type' => 'insight',
                    'source'        => 'BuyerDNA+Match',
                ],
            ],
        ],

        // =====================================================================
        // VACANT LAND
        // =====================================================================
        'land' => [
            'Land Criteria' => [
                'land_intended_use' => [
                    'label'         => 'What\'s the buyer\'s intended use for the land?',
                    'placeholder'   => 'Enter intended use (e.g., Build primary residence, Agricultural, Subdivide and resell)',
                    'tooltip'       => 'Helps the AI restate the buyer\'s intended use for the land.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'land_zoning_required' => [
                    'label'         => 'What zoning classification does the buyer require?',
                    'placeholder'   => 'Enter zoning requirement (e.g., R-1 or R-2 required, Agricultural acceptable, C-2 or higher needed)',
                    'tooltip'       => 'Lets the AI restate the buyer\'s zoning requirement factually.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'land_utilities_needed' => [
                    'label'         => 'What utilities does the buyer need available?',
                    'placeholder'   => 'Enter utility requirements (e.g., Electric and water at road minimum, Municipal sewer preferred, Off-grid acceptable)',
                    'tooltip'       => 'Helps the AI restate the buyer\'s utility requirements.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'land_soil_testing' => [
                    'label'         => 'Will the buyer require soil/perc/environmental testing?',
                    'placeholder'   => 'Enter testing requirements (e.g., Perc test contingency for septic, Phase I review, Soil testing for structural feasibility)',
                    'tooltip'       => 'Factual requirement; the AI restates it and gives no engineering advice.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'land_build_timeline' => [
                    'label'         => 'What\'s the buyer\'s build/development timeline?',
                    'placeholder'   => 'Enter timeline (e.g., Break ground within 12 months, Long-term hold, Permit as soon as possible)',
                    'tooltip'       => 'Helps the AI restate the buyer\'s development timeline.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'land_access_requirements' => [
                    'label'         => 'What road access or easement does the buyer require?',
                    'placeholder'   => 'Enter access needs (e.g., Direct paved road frontage, Recorded access easement acceptable, Private road is fine)',
                    'tooltip'       => 'Lets the AI restate the buyer\'s access requirements.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
                'land_topography' => [
                    'label'         => 'Does the buyer have flood/elevation/topography requirements?',
                    'placeholder'   => 'Enter topography requirements (e.g., No flood zone, Flat and cleared preferred, Gentle slope acceptable)',
                    'tooltip'       => 'Helps the AI restate the buyer\'s topography requirements.',
                    'category_type' => 'common',
                    'source'        => 'KB',
                ],
            ],
            'Buyer Insights' => [
                'buyer_land_characteristics_fit' => [
                    'label'         => 'What land characteristics matter most to this buyer?',
                    'placeholder'   => 'Optional — the AI describes the buyer\'s stated land criteria',
                    'tooltip'       => 'Neutral, criteria-based description.',
                    'category_type' => 'insight',
                    'source'        => 'BuyerDNA+Match',
                ],
            ],
        ],

    ],

];
