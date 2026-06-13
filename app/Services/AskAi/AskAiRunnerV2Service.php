<?php

namespace App\Services\AskAi;

use Illuminate\Support\Facades\Log;

/**
 * AskAiRunnerV2Service — End-to-End Ask AI Pipeline Runner
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * ROLE: End-to-end orchestrator for the Ask AI pipeline.
 * Chains four already-built pipeline services in sequence:
 *   1. AskAiQuestionClassifierService  — classify the user question
 *   2. AskAiInternalRunnerService      — build context, contract, and prompt package
 *   3. AskAiOpenAiAdapterService       — call OpenAI with the prompt package
 *   4. AskAiFinalResponseBuilderService — normalise the raw adapter output
 *   5. AskAiFollowUpQuestionService    — append follow-up chips to the final response
 *
 * Optional normalizer step (feature-flagged, step 1a):
 *   When the classifier returns 'unsupported' and AskAiIntentNormalizerService
 *   is injected and its flag is enabled, the normalizer maps the question to a
 *   canonical listing_facts field key. The pipeline then re-enters listing_facts
 *   through the normal contract and prompt-builder governance — no facts are invented.
 *   Layer 1 'prohibited' questions always block before the normalizer is reached.
 *
 * Deterministic FAQ key detection (always runs, step 1b):
 *   When the classifier routes to listing_facts, a keyword-based detector maps
 *   the question to a specific faq_answers.* path (e.g. roof-related phrases →
 *   faq_answers.roof_age_and_condition). This pins the allowed context to that
 *   field so the missing-data guard fires correctly if the listing has no answer.
 *   No external I/O; runs regardless of the normalizer feature flag.
 *
 * This service MUST NEVER:
 *   - Call any external LLM, language model, or external HTTP service directly.
 *   - Execute any database write (save, update, create, delete).
 *   - Infer, estimate, or invent values for missing data.
 *   - Hardcode or embed any OpenAI API key.
 *   - Create routes, controllers, Blade views, or database migrations.
 *   - Rank, sort, or recommend any listing, offer, buyer, or agent.
 *   - Reference or infer protected class characteristics.
 *   - Maintain conversation history or stateful session data.
 * ==================================================================================
 */
class AskAiRunnerV2Service
{
    /**
     * Keyword → canonical faq_answers.* path map used by detectFaqFieldKey().
     *
     * Each entry maps a set of lower-case sub-string keywords to the canonical
     * FAQ field path they address. The detector iterates entries in order and
     * returns the FIRST matching path.
     *
     * Must be kept in sync with the FAQ config files (config/ai_faq_*.php).
     * Only include keys whose intent is unambiguously one specific FAQ field.
     */
    private const FAQ_KEY_KEYWORD_MAP = [
        // ---- Seller: Property Condition & Maintenance -------------------------
        'faq_answers.roof_age_and_condition' => [
            'roof age',
            'age of roof',
            'age of the roof',
            'what is the age of the roof',
            'condition of the roof',
            'condition is the roof',
            'what condition is the roof',
            'when was the roof',
            'how old is the roof',
            "what's the roof situation",
            'what is the roof situation',
            'tell me about the roof',
            'roof condition',
        ],
        'faq_answers.hvac_system_age' => [
            'how old is the hvac',
            'hvac age',
            'age of the hvac',
            'when was the hvac',
            'when was the air conditioning replaced',
            'hvac last serviced',
            'hvac service history',
            'when was the ac replaced',
            'how old is the ac unit',
        ],
        'faq_answers.water_heater_age_type' => [
            'water heater age',
            'how old is the water heater',
            'age of the water heater',
            'when was the water heater',
            'what type of water heater',
            'tankless water heater',
            'water heater type',
            'is there a tankless',
        ],
        'faq_answers.recent_renovations_list' => [
            'recent renovations',
            'what renovations',
            'what upgrades have',
            'has it been renovated',
            'renovation history',
            'what has been updated',
            'recent upgrades',
            'improvements made',
            'what was recently updated',
        ],
        'faq_answers.permits_for_renovations' => [
            'permits for renovations',
            'were renovations permitted',
            'renovation permits',
            'was it permitted',
            'permits pulled',
            'work permitted',
        ],
        'faq_answers.known_defects_issues' => [
            'known defects',
            'known issues',
            'any known problems',
            'deferred repairs',
            'any defects',
            'what are the issues with',
            'any problems with the property',
            'any issues with the home',
        ],
        'faq_answers.foundation_type_and_issues' => [
            'foundation type',
            'what type of foundation',
            'slab foundation',
            'block foundation',
            'any foundation issues',
            'foundation condition',
            'foundation problems',
        ],
        'faq_answers.pest_termite_history' => [
            'pest history',
            'termite history',
            'termite damage',
            'any pest issues',
            'pest treatment history',
            'have there been termites',
            'any termite problems',
            'pest problems',
        ],
        'faq_answers.flood_damage_history' => [
            'ever flooded',
            'flood history',
            'has it flooded',
            'flooding history',
            'water damage history',
            'flood damage',
            'has the property flooded',
        ],
        'faq_answers.mold_issues_history' => [
            'mold history',
            'any mold issues',
            'has there been mold',
            'mold remediation',
            'mold problems',
            'any mold in the property',
        ],
        // ---- Seller: Financial & Utility Insights ----------------------------
        'faq_answers.average_utility_costs' => [
            'average utility',
            'utility costs',
            'monthly utility bill',
            'how much are utilities',
            'what are the utility costs',
            'average electric bill',
            'average gas bill',
            'utility bills',
            'average monthly bills',
        ],
        'faq_answers.internet_utility_providers' => [
            'internet providers',
            'internet available',
            'which internet',
            'what internet providers',
            'cable internet available',
            'fiber internet available',
            'utility providers',
        ],
        'faq_answers.seller_concessions_offered' => [
            'seller concessions',
            'is the seller offering concessions',
            'closing cost credits',
            'repair credits offered',
            'seller open to concessions',
            'concessions available',
        ],
        // ---- Seller: Flexibility & Negotiation -------------------------------
        'faq_answers.closing_timeline_flexibility' => [
            'closing timeline flexibility',
            'flexible on closing',
            'how flexible is the closing',
            'when can we close',
            'how soon can we close',
            'closing date flexibility',
        ],
        'faq_answers.items_excluded_from_sale' => [
            'items excluded',
            'what is excluded',
            'what does not convey',
            'not included in sale',
            'excluded from sale',
            'what stays',
            'what conveys',
            'excluded items',
        ],
        'faq_answers.as_is_condition' => [
            'sold as-is',
            'is it as-is',
            'being sold as is',
            'as is sale',
            'seller open to repairs',
            'as-is condition',
        ],
        // ---- Seller: Hidden Selling Points -----------------------------------
        'faq_answers.unique_selling_points' => [
            'hidden features',
            'non-obvious features',
            'what is special about this property',
            'unique qualities',
            'what makes this property special',
            'what will i miss about',
            'sprinkler system in place',
            'is there an irrigation system',
            'does it have a sprinkler system',
            'in-ground sprinkler system',
            'lawn irrigation available',
            'automated irrigation',
        ],
        'faq_answers.parking_arrangements' => [
            'parking arrangements',
            'how many cars can',
            'driveway space',
            'parking details',
            'how long is the driveway',
        ],
        'faq_answers.hoa_community_highlights' => [
            'hoa amenities',
            'community amenities',
            'what does the hoa offer',
            'what does the community have',
            'community highlights',
            'what does the hoa fee cover',
            'hoa fee breakdown',
            'what is included in the hoa dues',
            'hoa dues include what',
            'hoa services included',
            'does the hoa cover landscaping',
            'hoa includes pool maintenance',
            'what amenities does the hoa include',
        ],
        // ---- Landlord: Maintenance & Property Condition ----------------------
        'faq_answers.heating_cooling_system' => [
            'heating and cooling system',
            'heat and air',
            'heating/cooling',
            'what type of heating',
            'what kind of heating',
            'heating or cooling system',
            'cooling system type',
            'hvac type',
            'hvac system type',
        ],
        'faq_answers.laundry_situation' => [
            'in-unit laundry',
            'in unit laundry',
            'laundry situation',
            'laundry in unit',
            'washer and dryer in',
            'washer/dryer in',
            'laundry facilities',
        ],
        'faq_answers.maintenance_request_response_time' => [
            'maintenance requests',
            'how are repairs handled',
            'maintenance response',
            'how do i submit a maintenance',
            'property management response',
            'how do i report a repair',
            'maintenance process',
        ],
        'faq_answers.emergency_maintenance_available' => [
            'emergency maintenance',
            '24 hour maintenance',
            'after hours emergency',
            'emergency repairs available',
            'after-hours maintenance',
        ],
        'faq_answers.security_features' => [
            'security features',
            'what security does',
            'is there a security system',
            'security cameras',
            'cameras on property',
            'gated community access',
            'building security',
            'smart home features',
            'smart locks available',
            'does it have smart home technology',
            'ring doorbell installed',
            'smart home devices included',
        ],
        'faq_answers.pest_or_mold_history' => [
            'bugs or mold',
            'any mold or pest issues',
            'has there been pest in this rental',
            'mold or pest problems',
            'pest or mold history',
        ],
        // ---- Landlord: Lifestyle & Flexibility -------------------------------
        'faq_answers.lease_renewal_process' => [
            'lease renewal process',
            'how does lease renewal work',
            'can i renew the lease',
            'automatic renewal',
            'renewal process',
            'renewing the lease',
            'minimum lease term',
            'what is the minimum lease length',
            'shortest lease available',
            'can i do a 6 month lease',
            'maximum lease term',
            'longest lease offered',
            'month to month available',
            'is month to month an option',
        ],
        'faq_answers.subletting_allowed' => [
            'subletting allowed',
            'can i sublet',
            'sublease allowed',
            'is subletting permitted',
            'sublet policy',
        ],
        'faq_answers.smoking_policy' => [
            'smoking policy',
            'smoking allowed',
            'is smoking allowed',
            'can i smoke',
            'vaping policy',
            'no smoking policy',
        ],
        'faq_answers.what_makes_property_unique' => [
            'why is this rental special',
            'what sets this rental apart',
            'what makes this property stand out',
            'what makes this rental',
        ],
        // ---- Shared / generic FAQ answer keys (deriveFieldLabel canonical) ---
        'faq_answers.appliances_included' => [
            'appliances included in the',
            'what appliances come with the rental',
            'appliances provided',
            'is there a dishwasher included',
            'refrigerator included',
        ],
        'faq_answers.hoa_rules_restrictions' => [
            'hoa rules',
            'hoa restrictions',
            'what are the hoa rules',
            'community deed restrictions',
            'hoa community rules',
        ],
        'faq_answers.neighborhood_highlights' => [
            'best things about neighborhood',
            'neighborhood description',
            'what are the neighborhood highlights',
            'neighborhood highlights',
            'school district for this property',
            'what school district is this in',
            'nearby schools',
            'what schools are near this property',
            'school zone for this home',
            'which school district',
            'elementary school near this property',
            'middle school zone',
            'high school district',
            'are there good schools nearby',
            'school ratings for this area',
        ],
        'faq_answers.showing_tips_seller' => [
            'showing tips',
            'best time to show',
            'tips for showing',
            'showing instructions seller',
        ],
        'faq_answers.property_unique_features' => [
            'most unique thing about this property',
            'distinctive features',
            'what unique features',
            'property unique features',
        ],
        'faq_answers.landlord_responsibilities' => [
            'landlord responsibilities',
            'what is landlord responsible for',
            'what does landlord maintain',
            'who pays for what repairs',
            'landlord vs tenant repairs',
        ],
        'faq_answers.tenant_rules_regulations' => [
            'tenant rules',
            'rules for tenants',
            'house rules',
            'community rules for tenants',
            'tenant regulations',
        ],
        'faq_answers.lease_renewal_terms' => [
            'what are the renewal terms',
            'terms for lease renewal',
            'renewal conditions',
        ],
        'faq_answers.utility_setup_instructions' => [
            'how to set up utilities',
            'utility setup instructions',
            'who do i call to set up utilities',
            'utility transfer instructions',
        ],
        'faq_answers.pet_policy_details' => [
            'detailed pet policy',
            'what are the pet rules',
            'pet rules and restrictions',
            'full pet policy',
        ],
        'faq_answers.parking_instructions' => [
            'parking instructions',
            'assigned parking',
            'where do i park',
            'parking spot assignment',
            'is parking assigned',
            'where is parking',
        ],
        // ---- Seller: Location & Lifestyle ------------------------------------
        'faq_answers.neighborhood_character' => [
            'neighborhood vibe',
            'describe the neighborhood',
            'how is the neighborhood',
            'what is the neighborhood like',
            'community feel',
            'what kind of neighborhood',
        ],
        'faq_answers.traffic_or_noise_concerns' => [
            'traffic near the property',
            'any noise concerns',
            'how noisy is the area',
            'traffic or noise',
            'noise nuisance',
            'nearby noise',
        ],
        'faq_answers.planned_nearby_development' => [
            'planned developments nearby',
            'any construction nearby',
            'new construction near the property',
            'nearby development',
            'upcoming development',
        ],
        'faq_answers.commute_options_access' => [
            'commute options',
            'how far is the commute',
            'travel time to downtown',
            'commute to work',
            'commute access',
        ],
        'faq_answers.natural_light_orientation' => [
            'natural light',
            'which direction does the home face',
            'morning sun',
            'home orientation',
            'how is the light',
        ],
        'faq_answers.nearby_amenities_description' => [
            'nearby amenities',
            'what is nearby',
            'restaurants near the property',
            'shops nearby',
            'what is close to the property',
        ],
        'faq_answers.neighborhood_restrictions' => [
            'deed restrictions',
            'neighborhood covenants',
            'community deed restrictions',
            'neighborhood rules',
            'any covenants on the property',
        ],
        // ---- Seller: Flexibility & Negotiation (remaining) ------------------
        'faq_answers.seller_leaseback_option' => [
            'leaseback option',
            'can the seller stay after closing',
            'rent back after closing',
            'seller leaseback',
            'post-closing occupancy',
        ],
        'faq_answers.furniture_negotiability' => [
            'furniture negotiable',
            'any furniture included',
            'is the furniture staying',
            'furniture come with',
            'can we negotiate furniture',
        ],
        'faq_answers.environmental_concerns' => [
            'environmental concerns',
            'any contamination on the property',
            'environmental issues',
            'soil contamination',
            'environmental hazards',
        ],
        // ---- Seller: Hidden Selling Points (remaining) ----------------------
        'faq_answers.seller_favorite_features' => [
            "seller's favorite features",
            'what does the seller love about this home',
            'what will the seller miss about this property',
            'favorite things about this home',
        ],
        'faq_answers.seller_motivation_for_selling' => [
            'why is the seller selling',
            'reason for selling',
            'motivation for selling',
            "seller's reason for moving",
            'why are they selling',
        ],
        'faq_answers.move_in_ready_status' => [
            'is it move-in ready',
            'move in ready',
            'ready to move in',
            'is the home move-in ready',
            'move-in condition',
            'does it come with a home warranty',
            'is there a home warranty',
            'home warranty included',
            'seller providing home warranty',
            'what warranty comes with the home',
        ],
        'faq_answers.storage_space_available' => [
            'storage space',
            'how much storage does',
            'attic storage',
            'extra storage',
            'garage storage',
        ],
        // ---- Landlord: Maintenance (remaining) ------------------------------
        'faq_answers.storage_area_included' => [
            'storage area included',
            'is there a storage unit',
            'dedicated storage',
            'extra storage with rental',
            'storage cage',
        ],
        'faq_answers.internet_providers' => [
            'cable providers here',
            'fiber available at this rental',
            'which internet providers serve this rental',
            'internet providers available',
        ],
        'faq_answers.planned_renovations' => [
            'planned renovations',
            'upcoming construction at the rental',
            'any planned work',
            'scheduled renovations',
        ],
        // ---- Landlord: Location & Neighborhood (remaining) ------------------
        'faq_answers.noise_levels' => [
            'noise levels',
            'how noisy is this rental',
            'traffic noise at the rental',
            'neighboring unit noise',
            'noise from neighbors',
        ],
        'faq_answers.nearby_amenities' => [
            'what is close by the rental',
            'nearby amenities for renters',
            'dining near the rental',
            'parks near the rental',
        ],
        'faq_answers.guest_parking' => [
            'guest parking available',
            'where can guests park',
            'visitor parking',
            'guest parking policy',
        ],
        'faq_answers.proximity_to_public_transit' => [
            'how close to public transit',
            'near a bus stop',
            'public transport nearby',
            'transit distance',
            'closest transit stop',
        ],
        // ---- Landlord: Lifestyle & Flexibility (remaining) ------------------
        'faq_answers.furnished_or_unfurnished' => [
            'is it furnished',
            'furnished apartment',
            'comes furnished',
            'furnished or unfurnished',
            'does it come with furniture',
        ],
        'faq_answers.notice_to_vacate_required' => [
            'notice to vacate',
            'how much notice to leave',
            'notice required to move out',
            'required notice period',
        ],
        'faq_answers.preferred_tenant_qualities' => [
            'what kind of tenant is preferred',
            'what you look for in a tenant',
            'preferred tenant profile',
            'ideal tenant',
        ],
        'faq_answers.short_term_rentals_allowed' => [
            'airbnb allowed',
            'short-term rental permitted',
            'vrbo allowed',
            'is airbnb allowed',
            'short term rental policy',
        ],
        'faq_answers.ev_charging_available' => [
            'ev charging available',
            'electric vehicle charging',
            'can i install a charger',
            'ev outlet available',
        ],
        'faq_answers.bicycle_storage_available' => [
            'bike storage',
            'bicycle storage',
            'secure bike parking',
            'is there bike storage',
        ],
        // ---- Landlord: High-Intent (remaining) ------------------------------
        'faq_answers.utilities_individually_metered' => [
            'individually metered',
            'separate utility meters',
            'is gas separately metered',
            'separate electric meter',
        ],
        'faq_answers.renters_insurance_required' => [
            'renters insurance required',
            'do i need renters insurance',
            'is renters insurance mandatory',
        ],
        'faq_answers.lease_to_own_option' => [
            'lease to own',
            'rent to own option',
            'option to purchase the rental',
            'lease with purchase option',
        ],
        'faq_answers.previous_tenant_feedback' => [
            'previous tenant feedback',
            'what do past tenants say',
            'previous tenant reviews',
            'what have other tenants said',
        ],
        // ---- Seller Addon: Commercial Income --------------------------------
        'faq_answers.annual_net_operating_income' => [
            'net operating income',
            'annual noi',
            'what is the noi',
            'property noi',
            'noi for this property',
        ],
        'faq_answers.current_cap_rate' => [
            'current cap rate',
            'capitalization rate',
            'property cap rate',
        ],
        'faq_answers.existing_tenant_lease_terms' => [
            'existing tenant lease terms',
            'current tenant lease terms',
            'tenant lease expiration',
        ],
        'faq_answers.current_occupancy_rate' => [
            'occupancy rate',
            'what is the occupancy rate',
            'how occupied is the property',
        ],
        'faq_answers.annual_operating_expenses_detail' => [
            'operating expense breakdown',
            'property operating expenses',
        ],
        'faq_answers.value_add_opportunities' => [
            'value add opportunities',
            'value-add potential',
            'upside potential for the property',
        ],
        // ---- Seller Addon: Business Opportunity -----------------------------
        'faq_answers.annual_business_revenue' => [
            'annual business revenue',
            'gross revenue of the business',
            'annual gross revenue',
        ],
        'faq_answers.annual_net_profit' => [
            'annual net profit',
            'owner discretionary earnings',
            'business net profit',
        ],
        'faq_answers.business_reason_for_selling' => [
            'why is the business being sold',
            'reason for selling the business',
            'why is the owner selling the business',
        ],
        'faq_answers.business_employee_count' => [
            'how many employees does the business have',
            'business employee count',
            'number of employees in this business',
        ],
        'faq_answers.seller_training_transition' => [
            'seller training after sale',
            'transition support from seller',
            'will the seller provide training',
        ],
        'faq_answers.business_lease_status' => [
            'business location lease status',
            'is the business location leased',
            'lease for the business location',
        ],
        'faq_answers.inventory_equipment_included' => [
            'inventory included in business sale',
            'equipment included in the sale',
            'what is included in the business sale price',
        ],
        // ---- Seller Addon: Vacant Land --------------------------------------
        'faq_answers.land_utilities_availability' => [
            'utilities available on this land',
            'land utilities',
            'utilities on the land parcel',
        ],
        'faq_answers.land_zoning_permitted_uses' => [
            'land zoning designation',
            'what is the zoning for this land',
            'permitted uses for the land',
        ],
        'faq_answers.land_access_and_road' => [
            'road access to the land',
            'land road access',
            'parcel road access',
        ],
        'faq_answers.land_soil_and_topography' => [
            'soil conditions for this land',
            'land topography conditions',
            'soil and topography of the land',
        ],
        'faq_answers.land_survey_available' => [
            'land survey available',
            'is a survey available for the land',
            'land survey on file',
        ],
        'faq_answers.land_development_restrictions' => [
            'development restrictions on this land',
            'deed restrictions on the land',
            'easements on the parcel',
        ],
        // ---- Landlord Addon: Commercial Lease -------------------------------
        'faq_answers.commercial_cam_charges' => [
            'cam charges',
            'common area maintenance charges',
            'what are the cam charges',
        ],
        'faq_answers.commercial_lease_structure_type' => [
            'commercial lease structure',
            'is this a nnn lease',
            'triple net lease',
            'gross lease type',
        ],
        'faq_answers.commercial_tenant_improvement_allowance' => [
            'tenant improvement allowance',
            'ti allowance',
            'buildout allowance for this space',
        ],
        'faq_answers.commercial_buildout_flexibility' => [
            'buildout flexibility',
            'can the tenant modify the space',
            'tenant buildout modifications',
        ],
        'faq_answers.commercial_signage_rights' => [
            'commercial signage rights',
            'exterior signage rights for this lease',
            'signage rights included',
        ],
        'faq_answers.commercial_loading_dock_freight_elevator' => [
            'loading dock available',
            'freight elevator available',
            'loading dock for this commercial space',
        ],
        'faq_answers.commercial_electrical_capacity' => [
            'electrical capacity of the space',
            'power capacity of the commercial space',
            'electrical service capacity',
        ],
        'faq_answers.commercial_parking_ratio' => [
            'commercial parking ratio',
            'parking ratio for this space',
            'parking spaces per square foot for this commercial property',
        ],
        'faq_answers.commercial_exclusivity_rights' => [
            'commercial exclusivity rights',
            'exclusive use rights for this space',
            'exclusivity clause available',
        ],
        'faq_answers.commercial_expansion_option_rofr' => [
            'expansion option for this space',
            'right of first refusal on adjacent space',
            'rofr on adjacent space',
        ],
        'faq_answers.commercial_landlord_maintenance_responsibilities' => [
            'landlord maintenance responsibilities commercial',
            'what does the landlord maintain in commercial space',
            'commercial landlord responsibilities',
        ],
        'faq_answers.commercial_building_access_hours' => [
            'building access hours commercial',
            'what are the building access hours',
            'suite access hours',
        ],
        // ---- Tenant FAQ: Residential (faq_q1–faq_q20) ----------------------
        'faq_answers.faq_q1' => [
            'does the tenant work from home',
            'tenant work from home',
            'remote work setup for tenant',
        ],
        'faq_answers.faq_q2' => [
            'what matters most in day to day living for the tenant',
            'tenant daily living priorities',
        ],
        'faq_answers.faq_q3' => [
            'ideal neighborhood for the tenant',
            'tenant neighborhood preference description',
        ],
        'faq_answers.faq_q4' => [
            'tenant noise sensitivity',
            'how sensitive is the tenant to noise',
        ],
        'faq_answers.faq_q5' => [
            'which amenity matters most to the tenant',
            'tenant top amenity priority',
        ],
        'faq_answers.faq_q6' => [
            'how important is outdoor space to the tenant',
            'tenant outdoor space importance',
        ],
        'faq_answers.faq_q7' => [
            'does the tenant have pets',
            'tenant pet breed and size',
        ],
        'faq_answers.faq_q8' => [
            'is the tenant willing to pay pet deposit',
            'tenant pet deposit willingness',
        ],
        'faq_answers.faq_q9' => [
            'is the tenant flexible on lease length',
            'tenant lease length flexibility preference',
        ],
        'faq_answers.faq_q10' => [
            'would the tenant consider a furnished unit',
            'tenant furnished unit preference',
        ],
        'faq_answers.faq_q11' => [
            'how firm is the tenant move-in timeline',
            'tenant move-in timeline firmness',
        ],
        'faq_answers.faq_q12' => [
            'could the tenant need to break the lease early',
            'tenant early lease termination risk',
        ],
        'faq_answers.faq_q13' => [
            'would the tenant take a longer lease for rent reduction',
            'tenant lease term rent reduction preference',
        ],
        'faq_answers.faq_q14' => [
            'what is driving the tenant rental search',
            'tenant rental search motivation',
        ],
        'faq_answers.faq_q15' => [
            'how long was the tenant most recent tenancy',
            'tenant previous tenancy length',
        ],
        'faq_answers.faq_q16' => [
            'is the tenant looking for short term or long term housing',
            'tenant short term or long term housing preference',
        ],
        'faq_answers.faq_q17' => [
            'does the tenant have landlord or employer reference',
            'tenant reference availability',
        ],
        'faq_answers.faq_q18' => [
            'what is the tenant source of income',
            'tenant income source type',
        ],
        'faq_answers.faq_q19' => [
            'how does the tenant prefer to communicate with the landlord',
            'tenant landlord communication preference',
        ],
        'faq_answers.faq_q20' => [
            'what is the tenant biggest concern in rental search',
            'tenant rental search concern',
        ],
        // ---- Tenant FAQ: Commercial (faq_q21–faq_q27) ----------------------
        'faq_answers.faq_q21' => [
            'what type of business will the tenant operate from this space',
            'tenant business type for commercial space',
        ],
        'faq_answers.faq_q22' => [
            'does the tenant expect customer foot traffic',
            'tenant expected foot traffic at location',
        ],
        'faq_answers.faq_q23' => [
            'does the tenant have special equipment or power requirements',
            'tenant equipment power needs',
        ],
        'faq_answers.faq_q24' => [
            'does the tenant require exterior signage',
            'tenant exterior signage requirement',
        ],
        'faq_answers.faq_q25' => [
            'will the tenant need to build out or modify the space',
            'tenant space buildout requirement',
        ],
        'faq_answers.faq_q26' => [
            'what are the tenant expected hours of operation',
            'tenant operating hours for business',
        ],
        'faq_answers.faq_q27' => [
            'is the tenant flexible on commercial lease term length',
            'tenant commercial lease term flexibility',
        ],
    ];

    /**
     * Keyword → canonical listing.* path map used by detectListingFieldKey().
     *
     * Each entry maps natural-language phrases to a native or EAV listing field
     * path. When a question matches one of these entries AND the resolved field
     * is null in the assembled context, the listing.* null-field guard fires
     * before OpenAI is called, returning a grounded "not provided" message.
     *
     * Only include keys whose intent is unambiguously one specific listing field.
     */
    private const LISTING_KEY_KEYWORD_MAP = [
        // ---- Tax ----
        'listing.annual_property_taxes' => [
            'property tax',
            'property taxes',
            'annual taxes',
            'annual tax',
            'annual property tax',
            'real estate tax',
            'real estate taxes',
            'tax amount',
            'what are the taxes',
            'what is the taxes',
            'what is the tax',
            "what's the taxes",
            "what's the tax",
            'taxes on this property',
            'tax on this property',
            'how much are the taxes',
            'how much are property taxes',
        ],
        // ---- Price & Financial ----
        'listing.asking_price' => [
            'asking price',
            'list price',
            'listed price',
            'starting price',
            'starting bid',
            'how much is this property',
            'what is the sale price',
            'what is the listing price',
            'original list price',
        ],
        'listing.buy_now_price' => [
            'buy now price',
            'buy it now price',
            'buy-it-now price',
            'fixed buy-now price',
            'what is the buy now',
        ],
        'listing.max_price' => [
            'buyer maximum budget',
            'buyer max budget',
            'maximum price buyer',
            'highest price the buyer',
            'buyer top price',
            'maximum budget',
            'how much can they spend',
            'what is their max price',
            'buyer maximum price',
        ],
        'listing.rent_amount' => [
            'monthly rent',
            'how much is rent',
            'rent amount',
            'how much does it cost to rent',
            'rental price',
            'how much is the rent',
            'what is the monthly rent',
        ],
        'listing.max_rent' => [
            'tenant max rent',
            'maximum rent budget',
            'how much can the tenant afford',
            'how much can the tenant pay',
            'tenant rent budget',
            'highest rent the tenant',
            // Previously in listing.rental_budget — merged here because the context
            // builder stores the tenant's budget as 'max_rent', not 'rental_budget'.
            "tenant's rental budget",
            'tenant rental budget',
            'what is your budget for rent',
            'maximum rental budget',
            'how much can the tenant pay per month',
            'tenant monthly budget',
            'budget for rent',
        ],
        // ---- Property Specifications ----
        'listing.bedrooms' => [
            'how many bedrooms',
            'number of bedrooms',
            'bedroom count',
            'how many bedroom',
            'bedrooms in this',
            'bedrooms does',
            'how many rooms',
        ],
        'listing.bathrooms' => [
            'how many bathrooms',
            'number of bathrooms',
            'bathroom count',
            'how many baths',
            'bath count',
            'bathrooms in this',
            'how many full baths',
            'half bath',
        ],
        'listing.square_feet' => [
            'home square footage',
            'living area square footage',
            'how big is the property',
            'how large is the property',
            'total square feet',
            'home size in square',
            'living area size',
            'square foot of the home',
            'how big is the home',
            'how large is the home',
        ],
        'listing.year_built' => [
            'year built',
            'when was this built',
            'when was this home built',
            'when was the home built',
            'how old is this home',
            'how old is this building',
            'age of the home',
            'age of this building',
            'how old is the property',
            'how old is this property',
            'when was it built',
        ],
        'listing.description' => [
            'property description',
            'listing description',
            'describe this listing',
            'what does the listing description say',
            'general overview of the listing',
        ],
        'listing.condition_prop' => [
            'condition of the rental',
            'what condition is this rental',
            'rental property condition',
            'property overall condition',
        ],
        // ---- Location ----
        'listing.address' => [
            'what is the property address',
            'property address',
            'where is this property located',
            'address of this property',
            'what is the street address',
        ],
        // ---- Amenities & Features ----
        'listing.pool' => [
            'does it have a pool',
            'is there a pool',
            'swimming pool',
            'does this property have a pool',
            'pool on property',
        ],
        'listing.carport' => [
            'does it have a carport',
            'is there a carport',
            'covered parking carport',
            'carport available',
        ],
        'listing.garage' => [
            'does it have a garage',
            'is there a garage',
            'garage included with',
            'is there an attached garage',
            'detached garage',
            'garage situation',
            'what is the garage',
            'tell me about the garage',
            'garage type',
        ],
        // ---- Property Items (multifamily type) — ordered BEFORE property_type so that
        //      duplex/triplex/quadplex questions route here, not to the more generic
        //      property_type entry.
        'listing.property_items' => [
            'duplex',
            'triplex',
            'quadplex',
            'multifamily type',
            'property type mix',
        ],
        // ---- Property Type ----
        'listing.property_type' => [
            'property type',
            'what type of property',
            'type of property',
            'what kind of property',
            'what property type',
            'what is the property type',
            'kind of property',
        ],
        // ---- View ----
        'listing.water_view' => [
            'water view',
            'lake view',
            'ocean view',
            'river view',
            'does it have a water view',
            'waterfront view',
            'what is the view',
            'what view',
            'what view does it have',
            'scenic view',
            'is there a water view',
            'does it have a view',
        ],
        // ---- Credit Score (Tenant) ----
        'listing.credit_score_range' => [
            'credit score range',
            'tenant credit score',
            'what is the credit score',
            'credit range',
            'what credit score',
            'required credit score',
            'credit score',
        ],
        'listing.appliances' => [
            'what appliances are included',
            'which appliances are included in the rental',
            'list of appliances in the unit',
            'appliances that come with the unit',
            'what appliances are in this unit',
            'appliances included in this unit',
        ],
        // ---- HOA & Community ----
        'listing.hoa_association' => [
            'is there an hoa for this property',
            'does this property have an hoa',
            'homeowners association details',
            'hoa association for this listing',
            'is there an hoa',
            'does it have an hoa',
            'does the property have an hoa',
            'does this property have an association',
            'hoa or association',
        ],
        'listing.hoa_fee' => [
            'hoa fee',
            'monthly hoa dues',
            'how much are the hoa dues',
            'hoa cost',
            'how much is the hoa',
        ],
        'listing.hoa_acceptable' => [
            'is the buyer okay with hoa',
            'buyer hoa preference',
            'would the buyer accept an hoa',
            'is the buyer open to hoa',
            'buyer open to hoa properties',
            'is the buyer open to hoa properties',
        ],
        'listing.has_hoa' => [
            'does this rental have an hoa',
            'hoa for this rental property',
            'rental property hoa status',
        ],
        'listing.association_amenities' => [
            'association amenities',
            'what does the community association offer',
            'community facilities included with association',
        ],
        // ---- Pet Policies ----
        'listing.pets_allowed' => [
            'are pets allowed',
            'is this pet-friendly',
            'pet friendly property',
            'can i have a dog here',
            'can i have a cat here',
            'pets welcome',
        ],
        'listing.pet_policy' => [
            'what is the pet policy for this rental',
            'pet policy for the unit',
            'pet rules for this rental',
        ],
        'listing.pet_deposit_fee_rent' => [
            'pet deposit',
            'pet fee amount',
            'monthly pet rent',
            'how much is the pet fee',
            'pet additional cost',
        ],
        'listing.pet_information' => [
            'what pets does the tenant have',
            'tenant pet details',
            'does the tenant have pets',
            'tenant pet type and size',
        ],
        // ---- Lease & Rental Terms ----
        'listing.lease_terms' => [
            'existing lease terms on this property',
            'current tenant lease on property',
            'is there a tenant currently leasing',
            'inherited lease terms',
        ],
        'listing.lease_length' => [
            'minimum lease term',
            'shortest lease available',
            'how long is the minimum lease',
            'what lease lengths are available',
            'lease length options',
            'how long is the lease',
            'available lease terms for this rental',
            'lease duration options',
        ],
        'listing.desired_lease_length' => [
            'desired lease length',
            'lease length is desired',
            'what lease length is desired',
            'how long a lease does the tenant want',
            'how long of a lease does this tenant want',
            'tenant preferred lease duration',
            'tenant desired lease term',
            "tenant's desired lease",
        ],
        'listing.renewal_option' => [
            'renewal option available',
            'is lease renewal an option',
            'can the lease be extended after initial term',
            'extension option for the lease',
        ],
        'listing.rental_restrictions' => [
            'rental restrictions',
            'are there any rental restrictions',
            'are there restrictions on renting',
            'rental restrictions on this property',
            'can this property be used as a rental investment',
            'property rental restriction rules',
        ],
        // ---- Utilities & Services ----
        'listing.utilities' => [
            'what utilities are included',
            'which utilities are included in the rent',
            'utilities included with rent',
            'what utilities are included in rent',
            'included utilities for this rental',
            'does rent include utilities',
            'utilities included in this rental',
            'utilities available',
            'what utilities are available',
            'available utilities for this property',
            'utilities for this home',
            'what utilities does the property have',
        ],
        'listing.tenant_pays' => [
            'what utilities does the tenant pay',
            'tenant utility responsibilities',
            'which utilities are the tenant responsibility',
        ],
        'listing.smoking_policy' => [
            'smoking policy',
            'is smoking allowed',
            'smoking policy for this rental unit',
            'does this unit allow smoking',
            'smoke-free unit status',
        ],
        'listing.subletting_policy' => [
            'subletting policy for this unit',
            'sublet policy listed for this rental',
            'subletting rules for this unit',
        ],
        // ---- Parking & Availability ----
        'listing.parking_terms' => [
            'parking terms for this rental',
            'is parking included in rent',
            'how many parking spots are included',
            'parking spot included in lease',
        ],
        'listing.available_date' => [
            'move-in date',
            'what is the move-in date',
            'when is this unit available',
            'availability date',
            'when can i move in',
            'move-in date available',
            'earliest available move-in date',
            'when is it available for rent',
        ],
        'listing.closing_date' => [
            'closing date',
            'preferred closing date',
            'when does the seller want to close',
            'what is the closing date',
            'target closing date',
        ],
        // ---- Buyer Financials & Criteria ----
        'listing.loan_pre_approved' => [
            'buyer pre-approved for a loan',
            'is the buyer pre-approved',
            'loan pre-approval status',
            'has the buyer been pre-approved',
        ],
        'listing.financing_type' => [
            'financing type',
            'what type of financing is the buyer using',
            'how is the buyer financing this purchase',
            'buyer loan type',
        ],
        'listing.inspection_period' => [
            'inspection period',
            'how many days for inspection',
            'buyer inspection contingency days',
            'inspection contingency timeline',
        ],
        'listing.inspection_contingency_buyer' => [
            'inspection contingency',
            'does the buyer need an inspection contingency',
            'is the offer contingent on inspection',
            'home inspection contingency',
        ],
        'listing.appraisal_contingency_buyer' => [
            'appraisal contingency',
            'is there an appraisal contingency',
            'does the buyer need the property to appraise',
            'appraisal contingency buyer',
        ],
        'listing.financing_contingency_buyer' => [
            'financing contingency',
            'is the offer contingent on financing',
            'does the buyer have a financing contingency',
            'mortgage contingency',
        ],
        // ---- Vacant Land — shared lot fields ----
        'listing.total_acreage' => [
            'how many acres',
            'total acreage',
            'size in acres',
            'acreage of this property',
            'how large is this lot in acres',
        ],
        // ---- Vacant Land — VL-only fields ----
        'listing.current_use' => [
            'what is the current use of the land',
            'how is the land currently used',
            'current land use',
            'what is the land used for now',
            'current use',
            'what is the current use',
            'how is this property currently being used',
            'property current use',
            'present use of the property',
        ],
        'listing.current_adjacent_use' => [
            'adjacent land use',
            'what is the adjacent property used for',
            'surrounding land use',
            'adjacent use of the land',
            'what is next to this property',
        ],
        'listing.water_available' => [
            'water available',
            'is water available',
            'is water available on this land',
            'water availability at site',
            'is there water service to this lot',
            'water to the site',
        ],
        'listing.sewer_available' => [
            'sewer available',
            'is sewer available',
            'sewer connection available',
            'does this land have sewer',
            'is sewer available on this land',
            'sewer availability at site',
            'is there sewer service to this lot',
            'sewer to the site',
        ],
        'listing.electric_available' => [
            'is electric available on this land',
            'electric availability at site',
            'is electricity available to the lot',
            'power to the site',
        ],
        'listing.gas_available' => [
            'is gas available on this land',
            'natural gas availability',
            'is gas service available to this lot',
        ],
        'listing.telecom_available' => [
            'telecom available',
            'is internet available on this land',
            'telecom availability on this land',
            'is there internet or cable service available',
            'broadband availability at this lot',
        ],
        'listing.road_frontage' => [
            'road frontage',
            'what is the road frontage',
            'road frontage type',
            'what type of road frontage',
            'does this lot have road frontage',
            'access road type',
        ],
        'listing.road_surface_type' => [
            'what is the road surface',
            'how is the road paved',
            'road surface type',
            'is the road paved or dirt',
            'type of road access',
        ],
        'listing.front_footage' => [
            'front footage',
            'how many feet of frontage',
            'feet of road frontage',
            'linear feet of frontage',
        ],
        'listing.number_of_wells' => [
            'how many wells',
            'number of wells on the land',
            'are there any wells on this property',
        ],
        'listing.number_of_septics' => [
            'how many septic systems',
            'number of septics',
            'are there any septic systems on this land',
            'septic system count',
        ],
        'listing.fences' => [
            'are there fences on this land',
            'what type of fencing is there',
            'fencing on the property',
            'is the property fenced',
        ],
        'listing.vegetation' => [
            'vegetation',
            'vegetation on the land',
            'what vegetation is on the land',
            'type of vegetation',
            'is there vegetation on this lot',
            'trees or plants on the land',
        ],
        'listing.buildable' => [
            'is this buildable',
            'is this land buildable',
            'is this property buildable',
            'can you build on this lot',
            'can i build on this property',
            'is it possible to build on this land',
            'buildable land status',
        ],
        'listing.easements' => [
            'easements',
            'easements on the property',
            'are there any easements',
            'what easements are on the property',
            'what easements are on this land',
            'easement details',
            'does this land have any easements',
        ],
        // listing.rental_budget was removed — keywords merged into listing.max_rent above.
        // The context builder stores the tenant's max budget under ctx['listing']['max_rent']
        // (cascade: EAV 'budget' → 'maximum_budget').  A separate rental_budget context key
        // does not exist, so a separate LISTING_KEY_KEYWORD_MAP entry would always miss.

        // ---- Lot / Land ----
        'listing.lot_size' => [
            'lot size',
            'how big is the lot',
            'acreage',
            'how many acres',
            'lot acreage',
            'lot area',
        ],
        // ---- Structural & Property Facts (Residential Rental + Commercial Sale audits) ----
        // Keys shared by both audits have merged phrase sets covering rental and commercial contexts.
        // Note: listing.year_built, listing.tenant_pays, and listing.flood_zone_code
        // already exist above — they are not duplicated here.
        // Note: listing.annual_property_taxes already exists earlier in this array.

        // ── Tax / Legal / Parcel ────────────────────────────────────────────
        'listing.parcel_id' => [
            'parcel id',
            'parcel identification number',
            'tax parcel id',
            'folio number',
            'what is the parcel id',
            'what is the parcel id for this rental',
            'parcel id for this property',
            'parcel number for this rental',
            'folio number for this property',
            'tax parcel',
            'property parcel identification',
        ],
        'listing.tax_year' => [
            'tax year',
            'what year are the taxes from',
            'taxes for which year',
            'property tax year',
            'what tax year is used for this rental',
            'tax year for this property',
            'which year are the property taxes based on',
        ],
        'listing.legal_description' => [
            'legal description',
            'what is the legal description',
            'property legal description',
            'the legal description',
            'subdivision legal description',
            'what is the legal description for this rental',
            'legal description of this property',
        ],
        'listing.additional_parcels' => [
            'additional parcels',
            'are there additional parcels',
            'does this property have additional parcels',
            'more than one parcel',
            'does this rental have additional parcels',
            'are there additional parcels included',
            'additional parcels for this property',
        ],
        'listing.total_parcel_count' => [
            'total number of parcels',
            'how many parcels',
            'parcel count',
            'total parcels for this property',
            'how many parcels does this rental have',
            'total parcel count for this property',
            'number of parcels in this listing',
        ],
        'listing.additional_parcel_ids' => [
            'what are the additional parcel ids for this rental',
            'additional parcel numbers for this property',
            'list the parcel ids for this rental',
        ],

        // ── Lot / Zoning ─────────────────────────────────────────────────────
        'listing.lot_dimensions' => [
            'lot dimensions',
            'what are the lot dimensions',
            'size of the lot',
            'lot size in feet',
            'dimensions of the lot',
            'lot width and depth',
            'lot size dimensions',
            'what is the lot size',
            'lot dimensions for this rental',
            'size of the lot for this rental',
            'how wide is the lot',
            'depth of the lot',
        ],
        'listing.lot_acreage' => [
            'lot acreage',
            'how many acres',
            'total acreage',
            'lot size in acres',
            'acreage of the property',
            'how big is the lot in acres',
        ],
        'listing.zoning' => [
            'zoning',
            'what is the zoning',
            'what is this property zoned',
            'zoning classification',
            'zoning designation',
            'is it zoned residential',
            'zoning for this property',
            'property zoning',
            'zoning code',
            'what zone is this property in',
            'what is the zoning for this rental',
            'how is this property zoned',
            'how is this land zoned',
            'property zoning classification',
            'what can this land be used for',
            'permitted uses for this property',
        ],
        // ── Commercial Building ───────────────────────────────────────────────
        'listing.building_sqft' => [
            'building size',
            'total building square footage',
            'how big is the building',
            'building square footage',
            'total sq ft of the building',
            'gross building area',
        ],
        'listing.ceiling_height' => [
            'ceiling height',
            'how high are the ceilings',
            'clearance height',
            'interior ceiling height',
            'what is the ceiling height',
            'ceiling clearance',
            'clear height',
        ],
        'listing.parking_spaces' => [
            'parking spaces',
            'how many parking spaces',
            'number of parking spaces',
            'total parking available',
            'parking count',
        ],
        'listing.annual_noi' => [
            'net operating income',
            'annual noi',
            'what is the noi',
            'what is the net operating income',
            'annual net operating income',
        ],
        'listing.cap_rate' => [
            'cap rate',
            'what is the cap rate',
            'capitalization rate',
            'capitalization rate for this property',
            'return rate on this property',
            'what is the capitalization rate',
            'what return does this investment yield',
            'investment yield rate',
            'property investment return',
        ],
        'listing.price_per_sqft' => [
            'price per square foot',
            'cost per sq ft',
            'price per sq ft',
            'what is the price per square foot',
        ],
        'listing.existing_lease_type' => [
            'existing lease type',
            'what type of lease is in place',
            'current lease structure',
            'lease type on this property',
            'what is the existing lease',
        ],
        'listing.lease_expiration' => [
            'lease expiration',
            'when does the lease expire',
            'lease end date',
            'expiration of the current lease',
            'when is the lease up',
        ],
        'listing.lease_assignable' => [
            'is the lease assignable',
            'can the lease be assigned',
            'lease assignment',
            'is this lease transferable',
        ],
        'listing.building_features' => [
            'building features',
            'what building features are included',
            'building amenities',
            'what are the building features',
            'commercial building features',
        ],
        // ── Landlord Approval ─────────────────────────────────────────────────
        'listing.landlord_approval_conditions' => [
            'landlord approval conditions',
            'approval requirements',
            'what are the landlord approval requirements',
            'tenant approval criteria',
            'credit requirements to rent',
            'what does the landlord require from tenants',
            'landlord requirements for tenants',
            'qualifying conditions for this rental',
        ],

        // ── Water / Waterfront ────────────────────────────────────────────────
        'listing.waterfront' => [
            'is it on the waterfront',
            'waterfront property',
            'is this a waterfront home',
            'does it have waterfront access',
            'is the property waterfront',
            'is this property on the waterfront',
            'is this waterfront',
            'does this have waterfront access',
            'is this rental on the waterfront',
            'is this a waterfront property',
            'waterfront status for this rental',
            'does this property have waterfront access',
            'is it on the water',
            'is this property waterfront',
            'does it have waterfront',
            'waterfront access',
        ],
        'listing.water_access' => [
            'water access',
            'lake access',
            'river access',
            'does it have water access',
            'what water bodies are accessible',
            'water access type',
            'what type of water access',
            'water access for this property',
            'what is the water access',
            'water access at this rental',
            'what type of water access does this property have',
            'water access options for this rental',
            'is there water access',
            'creek access',
            'what water body is accessible',
        ],
        // ── Structural Characteristics ────────────────────────────────────────
        'listing.interior_features' => [
            'interior features',
            'what are the interior features',
            'inside features of the home',
            'home interior details',
            'interior amenities',
            'what features are inside',
            'interior features of this rental',
            'what interior features does this property have',
            'interior amenities in this rental',
            'special interior features',
        ],
        // ---- Structural ----
        'listing.roof_type' => [
            'roof type',
            'what kind of roof',
            'what type of roof',
            'roof material',
            'roofing material',
            'type of roofing',
            'what is the roof made of',
            'what type of roof does this rental have',
            'roof type for this rental property',
            'roof material for this rental',
        ],
        'listing.exterior_construction' => [
            'exterior construction',
            'what is the exterior made of',
            'exterior material',
            'home exterior type',
            'what is the house built with',
            'building exterior type',
            'what type of exterior construction',
            'exterior construction of this rental',
            'exterior material for this rental',
        ],
        'listing.foundation' => [
            'foundation type',
            'what type of foundation',
            'foundation material',
            'what is the foundation',
            'slab foundation',
            'foundation of this building',
            'foundation type of this rental',
            'what type of foundation does this rental have',
            'foundation for this rental property',
            'is this on a slab or block foundation',
        ],
        'listing.heating_and_fuel' => [
            'heating and fuel',
            'what type of heating',
            'how is the home heated',
            'heating system',
            'type of heat',
            'heating fuel',
        ],
        'listing.heating_fuel' => [
            'heating type',
            'what type of heating',
            'heating system',
            'how is this property heated',
            'heat source',
            'fuel type for heating',
            'what type of heating fuel is used',
            'heating fuel type for this rental',
            'is it gas or electric heat',
            'what fuel does the heater use',
        ],
        'listing.air_conditioning' => [
            'air conditioning',
            'air conditioning type',
            'what type of air conditioning',
            'how is it cooled',
            'how is this property cooled',
            'does it have ac',
            'cooling system',
            'a/c type',
            'what type of air conditioning does this rental have',
            'air conditioning type for this rental',
            'is there central air conditioning',
            'what kind of ac does this rental have',
        ],
        // ---- Utilities ----
        // listing.water_source: seller/commercial context key (EAV key: 'water').
        // listing.water: landlord/rental context key (same underlying data, different context alias).
        // Both are retained so Guard B works for both rental and commercial questions.
        // Note: listing.utilities is defined once above (rental + availability phrases merged).
        'listing.water_source' => [
            'water source',
            'what is the water source',
            'where does the water come from',
            'public water or well',
            'type of water supply',
        ],
        'listing.water' => [
            'is it on public water',
            'well water',
            'water supply',
            'what is the water source for this rental',
            'water source type for this property',
            'is there city water or well water',
            'water supply for this rental',
        ],
        'listing.sewer' => [
            'sewer type',
            'what type of sewer',
            'is it on public sewer',
            'is this on public sewer',
            'septic system',
            'septic or sewer',
            'sewage system',
            'sewer system',
            'what type of sewer system does this rental use',
            'sewer system for this rental',
            'is there public sewer or septic',
            'sewer type for this property',
        ],
        // ---- Transaction / Occupancy ----
        'listing.sale_provision' => [
            'sale provision',
            'what are the sale provisions',
            'sale contingency type',
            'how is this sale structured',
            'sale type provision',
        ],
        'listing.offered_financing' => [
            'financing options',
            'what financing is offered',
            'seller financing',
            'offered financing types',
            'what types of financing does the seller accept',
        ],
        'listing.occupant_status' => [
            'is the property occupied',
            'occupant status',
            'who is occupying the property',
            'is the home vacant',
            'current occupant',
            'is this property owner-occupied',
        ],
        'listing.furnished' => [
            'is the property furnished',
            'does it come furnished',
            'furnished or unfurnished',
            'is the home furnished',
            'what is the furnishing status',
        ],
        // ── Flood Zone (supplemental) ─────────────────────────────────────────
        'listing.flood_insurance_required' => [
            'is flood insurance required',
            'flood insurance required',
            'required to have flood insurance',
            'required flood insurance',
            'do i need flood insurance',
            'do i need flood insurance for this property',
            'flood insurance requirement',
            'is flood insurance mandatory for this property',
            'is flood insurance required for this rental',
            'flood insurance requirement for this rental',
            'does this rental require flood insurance',
        ],
        'listing.flood_zone_panel' => [
            'flood zone panel',
            'fema map panel number',
            'fema flood map panel',
            'flood panel number',
            'what is the flood zone panel',
        ],
        'listing.flood_zone_date' => [
            'flood zone date',
            'when was the flood zone last updated',
            'when was the flood map updated',
            'fema map date',
            'fema flood map date',
            'flood map date',
            'flood zone effective date',
        ],
        // ---- Safety & Disclosure — Flood Zone Code (must come after panel/date so bare 'flood zone'
        //      falls through specific panel/date matches first and only then routes here)
        'listing.flood_zone_code' => [
            'flood zone',
            'flood zone code',
            'flood zone status',
            'is this in a flood zone',
            'is this property in a flood zone',
            'is the property in a flood zone',
            'in a flood zone',
            'fema flood zone',
            'flood zone designation',
        ],
        // ── CDD / Special Assessments ─────────────────────────────────────────
        'listing.has_cdd' => [
            'is there a cdd',
            'cdd status',
            'does this property have a cdd',
            'community development district',
            'what is the cdd',
            'does this rental have a cdd',
            'community development district for this rental',
            'is there a cdd fee for this rental',
            'cdd status for this property',
        ],
        'listing.annual_cdd_fee' => [
            'cdd fee',
            'annual cdd fee',
            'how much is the cdd fee',
            'annual cdd fee amount',
            'cdd fee amount',
            'cdd assessment amount',
            'cost of the cdd',
            'annual cdd fee for this rental',
            'what is the cdd fee amount',
            'cdd fee per year',
        ],
        'listing.has_special_assessments' => [
            'special assessments',
            'are there special assessments',
            'does it have special assessments',
            'does this property have special assessments',
            'special assessment on this property',
            'any special assessments',
            'special assessment status',
        ],
        'listing.special_assessment_amount' => [
            'special assessment amount',
            'how much are the special assessments',
            'what is the special assessment fee',
            'special assessment fee',
            'cost of special assessments',
            'total special assessment',
        ],
        // ── HOA ───────────────────────────────────────────────────────────────
        'listing.hoa_name' => [
            'hoa name',
            'name of the hoa',
            'what is the name of the homeowners association',
            'association name',
            'name of the association',
        ],
        // ── Residential Rental-specific ───────────────────────────────────────
        'listing.security_deposit_amount' => [
            'how much is the security deposit',
            'security deposit amount for this rental',
            'what is the security deposit',
            'deposit amount required',
            'how much is the deposit',
            'security deposit',
            'deposit amount',
            'required security deposit',
        ],
        'listing.terms_of_lease' => [
            'what are the terms of the lease',
            'lease terms for this rental',
            'rental lease terms listed',
            'what terms apply to this lease',
            'lease terms available',
            'what lease terms are offered',
            'available lease term lengths',
        ],
        'listing.rent_includes' => [
            'what is included in the rent',
            'what does rent include',
            'what is covered by the rent',
            'what services are included in rent',
            'utilities included in rent',
            'what comes with rent',
            'what is covered in the lease',
            'included in lease payment',
        ],
        'listing.lease_amount_frequency' => [
            'how often is rent paid',
            'rent payment frequency',
            'is rent monthly or weekly',
            'lease amount frequency for this rental',
            'how often is rent due',
            'lease payment frequency',
            'rent payment schedule',
        ],
        // ---- HOA sub-fields (rental context) ----
        'listing.association_name' => [
            'name of the hoa',
            'what is the association name',
            'homeowners association name',
            'name of the homeowners association',
        ],
        'listing.hoa_payment_schedule' => [
            'hoa payment schedule',
            'how often is the hoa fee',
            'hoa fee frequency',
            'is the hoa fee monthly or annual',
        ],
        'listing.association_fee_includes' => [
            'what does the hoa fee include',
            'what is included in the hoa fee',
            'hoa fee covers',
            'association fee includes',
            'what services does the hoa cover',
        ],
        'listing.special_assessment_description' => [
            'what are the special assessments for',
            'describe the special assessments',
            'what do the special assessments cover',
            'special assessment details',
        ],
        // Note: listing.tenant_pays already exists earlier in this array (residential / utilities section).

        // ---- Commercial Lease Type ----
        'listing.commercial_lease_type' => [
            'lease type',
            'what type of lease',
            'nnn lease',
            'triple net lease',
            'gross lease',
            'modified gross lease',
            'what is the lease type',
            'what kind of lease',
            'commercial lease structure',
        ],
        // ---- CAM / NNN Charges ----
        'listing.cam_nnn_additional_rent_charges' => [
            'cam charges',
            'common area maintenance charges',
            'what are the cam charges',
            'nnn charges',
            'triple net charges',
            'additional rent charges',
        ],
        // ---- First / Last Month Rent ----
        'listing.first_month_rent_required' => [
            'first month rent required',
            'is first month rent required',
            'first month payment required',
        ],
        'listing.last_month_rent_required' => [
            'last month rent required',
            'is last month rent required',
            'last month deposit',
        ],
        // ---- Total Move-In Funds ----
        'listing.total_move_in_funds_required' => [
            'total move-in costs',
            'how much is needed to move in',
            'total move in amount',
            'upfront costs to move in',
            'what is the total move-in',
        ],
        // ---- Commercial Building Details ----
        'listing.number_of_restrooms' => [
            'how many restrooms',
            'number of restrooms',
            'restroom count',
            'how many bathrooms in the commercial space',
        ],
        'listing.office_retail_sqft' => [
            'office square footage',
            'office area sq ft',
            'how big is the office area',
            'office space size',
            'retail square footage',
        ],
        // ---- Pet Deposit / Monthly Fee ----
        'listing.pet_deposit_amount' => [
            'pet deposit amount',
            'how much is the pet deposit',
            'pet security deposit',
        ],
        'listing.pet_monthly_fee' => [
            'monthly pet fee',
            'pet monthly rent',
            'pet rent amount',
            'how much is the monthly pet fee',
        ],
        // ---- Occupancy ----
        'listing.number_of_occupants_allowed' => [
            'number of occupants',
            'occupant limit',
            'maximum number of occupants',
            'how many people can live here',
            'occupancy limit',
            'max occupants allowed',
        ],
        // ---- Income Requirement ----
        'listing.min_income_requirement' => [
            'income requirement',
            'minimum income required',
            'income qualification',
            'what income is required',
        ],
        // ---- Signage ----
        'listing.signage_rights' => [
            'signage rights',
            'exterior signage',
            'can i put up a sign',
            'commercial signage rights',
            'exterior signage rights for this lease',
        ],
        // ---- Building Hours / Access ----
        'listing.building_hours' => [
            'building hours',
            'building access hours',
            'what are the building access hours',
            'suite access hours',
        ],
        'listing.access_24_7' => [
            '24/7 access',
            'is the building accessible 24 hours',
            'round the clock access',
            'always accessible',
        ],
        // ---- Shared Amenities ----
        'listing.shared_amenities' => [
            'shared amenities',
            'what amenities are shared',
            'common amenities for tenants',
            'building amenities',
        ],
        // ---- Renewal Option Details ----
        'listing.renewal_option_details' => [
            'renewal option details',
            'lease renewal option terms',
            'what are the renewal option terms',
            'can the lease be renewed',
        ],
        // ── Income / Multifamily ─────────────────────────────────────────────
        'listing.gross_annual_income' => [
            'gross annual income',
            'total annual rent collected',
            'what is the gross annual income',
            'annual gross rental income',
            'how much revenue does this property generate',
            'annual gross income',
            'total revenue this property generates',
        ],
        'listing.annual_net_income' => [
            'annual net income',
            'net operating income amount',
            'what is the annual net income',
            'noi amount',
        ],
        'listing.annual_operating_expenses' => [
            'annual operating expenses',
            'total annual expenses',
            'what are the annual operating expenses',
            'operating expenses for this property',
        ],
        'listing.total_units' => [
            'how many units',
            'unit count',
            'total units in this property',
            'number of units',
            'multiple units',
            'multi-unit property',
            'is this multi-unit',
            'how many rental units',
            'separate living units',
            'number of units in this building',
        ],
        'listing.total_buildings' => [
            'how many buildings',
            'total buildings',
            'number of buildings',
            'how many buildings are on this property',
            'total building count',
        ],
        'listing.unit_mix_summary' => [
            'unit mix',
            'bedroom mix',
            'what is the unit mix',
            'unit configuration',
            'what is the unit type breakdown',
            'unit type breakdown',
            'bedroom and bath mix',
        ],
        'listing.rent_roll_available' => [
            'rent roll',
            'rent roll available',
            'is a rent roll available',
            'is there a rent roll',
            'rent roll provided',
            'can i see the rent roll',
            'request rent roll',
        ],
        'listing.operating_statement_available' => [
            'operating statement available',
            'income statement available',
            'is an operating statement available',
            'operating statement provided',
            'do you have an operating statement',
            'income and expense statement',
        ],
        'listing.occupancy_requirement' => [
            'occupancy requirement',
            'what occupancy is required',
            'minimum occupancy',
            'what occupancy requirements exist',
        ],
        'listing.income_requirement' => [
            'property monthly income from rent',
            'monthly income this property generates',
            'how much monthly income does this property generate',
            'investment property monthly income',
        ],
        // ── Business Opportunity Fields ───────────────────────────────────────
        'listing.annual_revenue' => [
            'annual revenue',
            'business annual revenue',
            'how much revenue does this business generate',
            'business revenue',
            'total annual revenue',
            'what is the annual revenue of this business',
        ],
        'listing.employee_count' => [
            'employee count',
            'how many employees does this business have',
            'number of employees',
            'staff count',
            'full-time employees in this business',
        ],
        'listing.year_established' => [
            'year established',
            'when was this business established',
            'how long has this business been operating',
            'business founding year',
            'how old is this business',
        ],
        'listing.business_name' => [
            'business name',
            'name of the business',
            'what is the name of this business',
            'trading name of the business',
        ],
        'listing.business_location_leased' => [
            'is the business location leased',
            'business location lease status',
            'does the business own or lease the location',
            'is the property leased or owned by the business',
        ],
        'listing.nda_required' => [
            'nda required',
            'is an nda required',
            'non-disclosure agreement required',
            'does this listing require an nda',
            'confidentiality agreement required',
        ],
        'listing.financial_statements_available' => [
            'financial statements available for this business',
            'are financial statements available',
            'financial records available',
            'can i see the financial statements',
            'business financial documents available',
        ],
        'listing.reason_for_sale' => [
            'reason for sale',
            'why is this business for sale',
            'reason for selling this business',
            'seller motivation for selling this business',
            'what is the reason for selling this business',
        ],
        'listing.sale_includes' => [
            'what is included in the sale',
            'sale includes',
            'what does the sale include',
            'what assets are included in this business sale',
            'included in the business sale',
        ],
        'listing.business_assets' => [
            'business assets',
            'what assets does the business have',
            'list of business assets',
            'assets included with business',
        ],
        'listing.business_lease_monthly_rent' => [
            'how much does the business pay in rent',
            'business location rent',
            'commercial space rent for this business',
            'lease payment for the business location',
            'business lease payment amount',
        ],
        'listing.ffe_value' => [
            'ffe value',
            'furniture fixtures and equipment value',
            'what is the ffe worth',
            'value of furniture fixtures and equipment',
        ],
        'listing.gross_profit' => [
            'gross profit',
            'business gross profit',
            'what is the gross profit',
            'gross profit margin of this business',
            'total gross profit of this business',
        ],
        'listing.sde_ebitda' => [
            'sde',
            'ebitda',
            'seller discretionary earnings',
            'what is the sde',
            'what is the ebitda',
            'owner earnings',
            'discretionary earnings',
        ],
        'listing.inventory_value' => [
            'inventory value',
            'value of the inventory',
            'how much is the inventory worth',
            'current inventory value',
            'business inventory value',
        ],
        'listing.licenses' => [
            'business licenses',
            'licenses required for this business',
            'what licenses does this business have',
            'required licenses',
            'permits and licenses',
        ],
        'listing.business_lease_assignable' => [
            'is the business lease assignable',
            'can the business lease be transferred',
            'business lease transferability',
            'is the commercial lease assignable for this business',
            'assignable business lease',
        ],
    ];

    private AskAiQuestionClassifierService $classifier;
    private AskAiInternalRunnerService $internalRunner;
    private AskAiOpenAiAdapterService $adapter;
    private AskAiFinalResponseBuilderService $finalResponseBuilder;
    private AskAiFollowUpQuestionService $followUpService;
    private ?AskAiIntentNormalizerService $normalizer;
    private ?AskAiKnowledgeSearchService $knowledgeSearch;

    public function __construct(
        AskAiQuestionClassifierService $classifier,
        AskAiInternalRunnerService $internalRunner,
        AskAiOpenAiAdapterService $adapter,
        AskAiFinalResponseBuilderService $finalResponseBuilder,
        AskAiFollowUpQuestionService $followUpService,
        ?AskAiIntentNormalizerService $normalizer = null,
        ?AskAiKnowledgeSearchService $knowledgeSearch = null
    ) {
        $this->classifier           = $classifier;
        $this->internalRunner       = $internalRunner;
        $this->adapter              = $adapter;
        $this->finalResponseBuilder = $finalResponseBuilder;
        $this->followUpService      = $followUpService;
        $this->normalizer           = $normalizer;
        $this->knowledgeSearch      = $knowledgeSearch;
    }

    /**
     * Execute the full Ask AI pipeline and return a structured result.
     *
     * Pipeline stages:
     *   1. Classify the question (always runs)
     *   1a. [Optional] Intent normalization: if classifier returns 'unsupported'
     *       AND the normalizer is injected AND its feature flag is enabled,
     *       attempt to map the question to a known listing_facts field key.
     *       Layer 1 'prohibited' questions always block before this step.
     *   2. Run internal pipeline: context → contract → prompt package
     *   3. Guard: if no prompt_package returned, skip OpenAI and return safe failed result
     *   4. Generate via OpenAI adapter
     *   5. Build the final normalised response
     *   6. Append follow-up question chips to final_response['follow_up_questions']
     *
     * Output contract — always returns exactly these nine keys:
     *   success        bool        — mirrors final_response['success']; false on guard/exception paths
     *   status         string      — mirrors final_response['status']; 'failed' on guard/exception paths
     *   classification array|null  — output of AskAiQuestionClassifierService::classify(); null on early exception
     *                                When normalization fires, carries 'normalized_field_key' for QA.
     *   context        array|null  — output of AskAiInternalRunnerService context stage
     *   contract       array|null  — output of AskAiInternalRunnerService contract stage
     *   prompt_package array|null  — output of AskAiInternalRunnerService prompt stage
     *   adapter_result array|null  — output of AskAiOpenAiAdapterService::generate(); null if skipped
     *   final_response array|null  — output of AskAiFinalResponseBuilderService::build() plus
     *                                follow_up_questions key from AskAiFollowUpQuestionService; null if skipped
     *   error          string|null — null unless final_response['error'] is set or a Throwable is caught
     *
     * The trace array also carries two normalizer observability fields:
     *   normalizer_status  string|null — outcome of the normalizer step:
     *     'not_applicable' — question type is not 'unsupported' (normalizer cannot help)
     *     'not_called'     — question is unsupported but normalizer is off or not injected
     *     'matched'        — normalizer called and returned a canonical key
     *     'unknown'        — normalizer called; OpenAI returned "unknown"
     *     'failed'         — normalizer called; operational failure (see normalizer_error)
     *   normalizer_error   string|null — structured error code when normalizer_status='failed':
     *     rate_limited | timeout | api_error | invalid_json | invalid_key | empty_response
     *
     * @param  string $listingType  Canonical or aliased listing type string.
     * @param  int    $listingId    Primary key of the listing record.
     * @param  string $question     The raw user question string.
     * @param  array  $options      Optional pair options forwarded to the internal runner.
     * @return array
     */
    public function run(string $listingType, int $listingId, string $question, array $options = []): array
    {
        // Declared before try so the catch block can always report whatever
        // normalizer state was reached before the exception fired.
        $normalizerStatus = null;
        $normalizerError  = null;

        try {
            $classification = $this->classifier->classify($question);
            $questionType   = $classification['question_type'];

            // Determine normalizer_status before building the trace so every
            // exit path (including early returns) carries the correct value.
            // not_applicable — question type is deterministic; normalizer not relevant.
            // not_called     — question is 'unsupported' but flag is off or service missing.
            // (updated below when normalizer is actually called)
            if ($questionType !== 'unsupported') {
                $normalizerStatus = 'not_applicable';
                $normalizerError  = null;
            } elseif ($this->normalizer === null || !$this->normalizer->isEnabled()) {
                $normalizerStatus = 'not_called';
                $normalizerError  = null;
            } else {
                // Will be overwritten after the normalizer call in step 1a.
                $normalizerStatus = 'not_called';
                $normalizerError  = null;
            }

            $trace = [
                'question'                    => $question,
                'classifier_result'           => $questionType,
                'deterministic_question_type' => $questionType,
                'normalizer_called'           => 'N',
                'normalizer_status'           => $normalizerStatus,
                'normalizer_error'            => $normalizerError,
                'router_called'               => 'N',
                'router_status'               => null,
                'router_context_path'         => null,
                'normalized_field_key'        => null,
                'faq_key_detected'            => null,
                'final_question_type'         => $questionType,
                'final_status'                => null,
                'source_attribution'          => null,
            ];

            // ----------------------------------------------------------------
            // Step 1a — Optional intent normalization (feature-flagged).
            // Fires only when:
            //   (a) classifier returned 'unsupported' (not 'prohibited' or any
            //       other type — Layer 1 refusals always win)
            //   (b) the normalizer service is injected
            //   (c) the normalizer's feature flag is enabled
            // When a canonical path is returned, the pipeline re-enters
            // listing_facts through the normal contract + prompt-builder
            // governance. The normalized_field_key is attached to classification
            // for QA/debug visibility but does not bypass any governance layer.
            //
            // Trace fields set here:
            //   normalizer_status = 'not_applicable' when question type cannot use normalizer
            //   normalizer_status = 'not_called'     when flag is off or service not injected
            //   normalizer_status = <from getLastStatus()> when normalizer is called
            //   normalizer_error  = <from getLastError()>  when normalizer is called
            // ----------------------------------------------------------------
            if ($questionType === 'unsupported'
                && $this->normalizer !== null
                && $this->normalizer->isEnabled()
            ) {
                $trace['normalizer_called'] = 'Y';
                $trace['router_called']     = 'Y';

                $knownFieldKeys = $this->normalizer->buildKnownFieldKeys();
                $normalizedKey  = $this->normalizer->normalize($question, $knownFieldKeys, $listingType);

                $normalizerStatus  = $this->normalizer->getLastStatus();
                $normalizerError   = $this->normalizer->getLastError();
                $routerContextPath = $this->normalizer->getLastContextPath();

                $trace['normalizer_status']   = $normalizerStatus;
                $trace['normalizer_error']    = $normalizerError;
                $trace['router_context_path'] = $routerContextPath;

                // Map normalizer status to the router_status vocabulary.
                // 'unknown' (legacy "no match") surfaces as 'unsupported' in the router trace.
                $trace['router_status'] = match ($normalizerStatus) {
                    'matched'    => 'matched',
                    'unknown'    => 'unsupported',
                    'prohibited' => 'prohibited',
                    'failed'     => 'failed',
                    default      => 'not_called',
                };

                // When the router flagged the question as prohibited, re-classify so the
                // internal runner applies the same fair-housing refusal it would for a
                // classifier-blocked question.
                if ($normalizerStatus === 'prohibited') {
                    $questionType   = 'prohibited';
                    $classification = [
                        'question_type' => 'prohibited',
                        'confidence'    => 1.0,
                        'reason'        => 'OpenAI router flagged this question as touching a prohibited topic.',
                    ];
                } elseif ($normalizedKey !== null) {
                    $trace['normalized_field_key'] = $normalizedKey;
                    $classification = [
                        'question_type'        => 'listing_facts',
                        'confidence'           => 0.70,
                        'reason'               => 'OpenAI intent normalization resolved this question to a known field key.',
                        'normalized_field_key' => $normalizedKey,
                    ];
                    $questionType = 'listing_facts';

                    // Propagate the canonical path into $options so the internal runner
                    // can narrow the contract's allowed_context to only that field,
                    // ensuring the prompt package is grounded in exactly the right data.
                    $options = array_merge($options, ['normalized_field_key' => $normalizedKey]);
                }
            } elseif ($questionType === 'unsupported') {
                // Unsupported question but normalizer not called (flag off or service missing).
                $normalizerStatus           = 'not_called';
                $normalizerError            = null;
                $trace['normalizer_status'] = $normalizerStatus;
                $trace['normalizer_error']  = $normalizerError;
                $trace['router_status']     = 'not_called';
            } else {
                // Non-unsupported question type: normalizer is not applicable.
                $normalizerStatus           = 'not_applicable';
                $normalizerError            = null;
                $trace['normalizer_status'] = $normalizerStatus;
                $trace['normalizer_error']  = $normalizerError;
                $trace['router_status']     = 'not_called';
            }

            // ----------------------------------------------------------------
            // Step 1b — Deterministic FAQ key detection for listing_facts.
            // Runs when the classifier (or step 1a normalization) routed the
            // question to listing_facts AND no normalized_field_key has been
            // set yet. A keyword lookup maps well-known factual intents to
            // their canonical faq_answers.* path — e.g. all six roof-condition
            // phrasings resolve to faq_answers.roof_age_and_condition.
            //
            // Runs regardless of the OpenAI normalizer flag because it is purely
            // deterministic (no external I/O). When a key is detected the prompt
            // package is narrowed to that field alone, and the missing-data guard
            // below fires correctly when the listing has no answer for that key.
            // ----------------------------------------------------------------
            if ($questionType === 'listing_facts' && !isset($options['normalized_field_key'])) {
                $detectedKey = $this->detectFaqFieldKey($question);
                if ($detectedKey !== null) {
                    $trace['faq_key_detected'] = $detectedKey;
                    $classification['normalized_field_key'] = $detectedKey;
                    $options = array_merge($options, ['normalized_field_key' => $detectedKey]);
                } else {
                    $detectedListingKey = $this->detectListingFieldKey($question);
                    if ($detectedListingKey !== null) {
                        $trace['listing_key_detected'] = $detectedListingKey;
                        $classification['normalized_field_key'] = $detectedListingKey;
                        $options = array_merge($options, ['normalized_field_key' => $detectedListingKey]);
                    }
                }
            }

            $trace['final_question_type'] = $questionType;

            $internalResult = $this->internalRunner->run(
                $listingType,
                $listingId,
                $questionType,
                $question,
                $options
            );

            $context       = $internalResult['context']        ?? null;
            $contract      = $internalResult['contract']       ?? null;
            $promptPackage = $internalResult['prompt_package'] ?? null;

            if ($promptPackage === null) {
                $trace['final_status'] = 'failed';
                $this->emitTrace($trace);
                return [
                    'success'        => false,
                    'status'         => 'failed',
                    'classification' => $classification,
                    'context'        => $context,
                    'contract'       => $contract,
                    'prompt_package' => null,
                    'adapter_result' => null,
                    'final_response' => null,
                    'error'          => 'Internal runner returned no prompt_package; OpenAI call skipped.',
                    'trace'          => $trace,
                ];
            }

            // ----------------------------------------------------------------
            // Field-specific missing-data guards.
            //
            // Guard A — faq_answers.* keys:
            // Fires when the normalizer resolved a faq_answers.* key but the
            // listing has no answer for that key — i.e. filterAllowedContext()
            // returned an empty array because the FAQ entry is absent.
            //
            // Guard B — listing.* null fields:
            // Fires when detectListingFieldKey() resolved a native/EAV listing
            // field path (e.g. listing.bedrooms, listing.annual_property_taxes)
            // and filterAllowedContext() included that field with a null value.
            // Without either guard, OpenAI would be called with zero or null
            // context and could hallucinate. Both guards return a grounded
            // "not provided" message instead.
            // ----------------------------------------------------------------
            $normalizedFieldKey = $options['normalized_field_key'] ?? null;

            if (
                $normalizedFieldKey !== null
                && str_starts_with($normalizedFieldKey, 'faq_answers.')
                && ($promptPackage['status'] ?? '') === 'prompt_ready'
                && array_key_exists('allowed_context', $promptPackage)
                && empty($promptPackage['allowed_context'])
            ) {
                $faqFieldLabel = $this->deriveFieldLabel($normalizedFieldKey);
                $missingFinalResponse = [
                    'success'            => false,
                    'status'             => 'insufficient_context',
                    'answer'             => $faqFieldLabel . ' has not been provided for this listing.',
                    'disclosures'        => $promptPackage['required_disclosures'] ?? [],
                    'source_attribution' => $promptPackage['source_attribution'] ?? [],
                    'refusal_message'    => null,
                    'error'              => null,
                    'source'             => ['answer_source' => 'openai', 'snapshot_id' => null, 'canonical_key' => null, 'match_type' => null, 'snapshot_version' => null],
                ];
                $missingFinalResponse['follow_up_questions'] = $this->followUpService->forResult(
                    $missingFinalResponse,
                    $classification
                );

                $trace['final_status']       = 'insufficient_context';
                $trace['source_attribution'] = $missingFinalResponse['source_attribution'] ?? null;
                $this->emitTrace($trace);

                return [
                    'success'          => false,
                    'status'           => 'insufficient_context',
                    'classification'   => $classification,
                    'context'          => $context,
                    'contract'         => $contract,
                    'prompt_package'   => $promptPackage,
                    'adapter_result'   => null,
                    'final_response'   => $missingFinalResponse,
                    'error'            => null,
                    'trace'            => $trace,
                    'outcome_category' => 'blank_information_not_provided',
                ];
            }

            if (
                $normalizedFieldKey !== null
                && str_starts_with($normalizedFieldKey, 'listing.')
                && ($promptPackage['status'] ?? '') === 'prompt_ready'
            ) {
                $listingField = substr($normalizedFieldKey, strlen('listing.'));
                $listingData  = $promptPackage['allowed_context']['listing'] ?? null;

                if (
                    is_array($listingData)
                    && array_key_exists($listingField, $listingData)
                    && ($listingData[$listingField] === null || $listingData[$listingField] === '')
                ) {
                    $listingFieldLabel = $this->deriveFieldLabel($normalizedFieldKey);
                    $missingListingResponse = [
                        'success'            => false,
                        'status'             => 'insufficient_context',
                        'answer'             => $listingFieldLabel . ' has not been provided for this listing.',
                        'disclosures'        => $promptPackage['required_disclosures'] ?? [],
                        'source_attribution' => $promptPackage['source_attribution'] ?? [],
                        'refusal_message'    => null,
                        'error'              => null,
                        'source'             => ['answer_source' => 'openai', 'snapshot_id' => null, 'canonical_key' => null, 'match_type' => null, 'snapshot_version' => null],
                    ];
                    $missingListingResponse['follow_up_questions'] = $this->followUpService->forResult(
                        $missingListingResponse,
                        $classification
                    );

                    $trace['final_status']       = 'insufficient_context';
                    $trace['source_attribution'] = $missingListingResponse['source_attribution'] ?? null;
                    $this->emitTrace($trace);

                    return [
                        'success'          => false,
                        'status'           => 'insufficient_context',
                        'classification'   => $classification,
                        'context'          => $context,
                        'contract'         => $contract,
                        'prompt_package'   => $promptPackage,
                        'adapter_result'   => null,
                        'final_response'   => $missingListingResponse,
                        'error'            => null,
                        'trace'            => $trace,
                        'outcome_category' => 'blank_information_not_provided',
                    ];
                }
            }

            // ----------------------------------------------------------------
            // Phase 4: Database-first knowledge search.
            //
            // Before calling OpenAI, search the latest ready snapshot for a
            // stored answer. Only fires when prompt_package is 'prompt_ready'
            // (all governance gates passed) and the search service is injected.
            //
            // Short-circuit outcomes:
            //   database_hit                 — return stored answer; skip OpenAI
            //   blank_information_not_provided — return "not provided"; skip OpenAI
            //   restricted                   — return blocked; skip OpenAI
            //   not_found                    — fall through to OpenAI unchanged
            // ----------------------------------------------------------------
            // Initialized null; set explicitly by the Phase 4 block on short-circuit
            // paths, or derived from the final response status at the end of the
            // main path so that blocked/unsupported packages are never mis-labeled
            // as 'openai_fallback'.
            $outcomeCategory = null;

            if (
                $this->knowledgeSearch !== null
                && ($promptPackage['status'] ?? '') === 'prompt_ready'
            ) {
                $knowledgeSearchResult = $this->knowledgeSearch->search(
                    $listingType,
                    $listingId,
                    $question,
                    $options
                );

                $searchOutcome = $knowledgeSearchResult['outcome'] ?? 'not_found';

                if ($searchOutcome === 'database_hit') {
                    $outcomeCategory = 'database_hit';
                    $dbHitResponse = [
                        'success'            => true,
                        'status'             => 'ready',
                        'answer'             => $knowledgeSearchResult['answer'],
                        'disclosures'        => $promptPackage['required_disclosures'] ?? [],
                        'source_attribution' => $promptPackage['source_attribution'] ?? [],
                        'refusal_message'    => null,
                        'error'              => null,
                        'source'             => $knowledgeSearchResult['source'],
                    ];
                    $dbHitResponse['follow_up_questions'] = $this->followUpService->forResult(
                        $dbHitResponse,
                        $classification
                    );

                    $trace['final_status']       = 'ready';
                    $trace['source_attribution'] = $dbHitResponse['source_attribution'] ?? null;
                    $this->emitTrace($trace);

                    return [
                        'success'          => true,
                        'status'           => 'ready',
                        'classification'   => $classification,
                        'context'          => $context,
                        'contract'         => $contract,
                        'prompt_package'   => $promptPackage,
                        'adapter_result'   => null,
                        'final_response'   => $dbHitResponse,
                        'error'            => null,
                        'trace'            => $trace,
                        'outcome_category' => $outcomeCategory,
                    ];
                }

                if ($searchOutcome === 'blank_information_not_provided') {
                    $outcomeCategory = 'blank_information_not_provided';
                    $dbBlankResponse = [
                        'success'            => false,
                        'status'             => 'insufficient_context',
                        'answer'             => $knowledgeSearchResult['answer'],
                        'disclosures'        => $promptPackage['required_disclosures'] ?? [],
                        'source_attribution' => $promptPackage['source_attribution'] ?? [],
                        'refusal_message'    => null,
                        'error'              => null,
                        'source'             => $knowledgeSearchResult['source'],
                    ];
                    $dbBlankResponse['follow_up_questions'] = $this->followUpService->forResult(
                        $dbBlankResponse,
                        $classification
                    );

                    $trace['final_status']       = 'insufficient_context';
                    $trace['source_attribution'] = $dbBlankResponse['source_attribution'] ?? null;
                    $this->emitTrace($trace);

                    return [
                        'success'          => false,
                        'status'           => 'insufficient_context',
                        'classification'   => $classification,
                        'context'          => $context,
                        'contract'         => $contract,
                        'prompt_package'   => $promptPackage,
                        'adapter_result'   => null,
                        'final_response'   => $dbBlankResponse,
                        'error'            => null,
                        'trace'            => $trace,
                        'outcome_category' => $outcomeCategory,
                    ];
                }

                if ($searchOutcome === 'restricted') {
                    $outcomeCategory = 'blocked_restricted';
                    $dbRestrictedResponse = [
                        'success'            => false,
                        'status'             => 'blocked',
                        'answer'             => null,
                        'disclosures'        => $promptPackage['required_disclosures'] ?? [],
                        'source_attribution' => $promptPackage['source_attribution'] ?? [],
                        'refusal_message'    => $promptPackage['refusal_template'] ?? null,
                        'error'              => null,
                        'source'             => $knowledgeSearchResult['source'],
                    ];
                    $dbRestrictedResponse['follow_up_questions'] = $this->followUpService->forResult(
                        $dbRestrictedResponse,
                        $classification
                    );

                    $trace['final_status']       = 'blocked';
                    $trace['source_attribution'] = $dbRestrictedResponse['source_attribution'] ?? null;
                    $this->emitTrace($trace);

                    return [
                        'success'          => false,
                        'status'           => 'blocked',
                        'classification'   => $classification,
                        'context'          => $context,
                        'contract'         => $contract,
                        'prompt_package'   => $promptPackage,
                        'adapter_result'   => null,
                        'final_response'   => $dbRestrictedResponse,
                        'error'            => null,
                        'trace'            => $trace,
                        'outcome_category' => $outcomeCategory,
                    ];
                }

                // 'not_found' — fall through to OpenAI.
            }

            $adapterResult = $this->adapter->generate($promptPackage);

            // ----------------------------------------------------------------
            // FAQ direct-return fallback.
            // When the adapter fails (e.g. OpenAI unconfigured / transient error)
            // but the listing already has the answer stored in the prompt package's
            // allowed_context, return the raw FAQ answer text directly rather than
            // propagating the generic "could not generate" error.  This ensures
            // listing_facts questions grounded in a specific faq_answers.* field
            // always return either a grounded answer or a clean insufficient_context
            // message — never the generic failed status.
            //
            // Only fires when all four conditions hold:
            //   1. Adapter call failed (success = false).
            //   2. A specific faq_answers.* field was resolved for this question.
            //   3. The prompt package was prompt_ready (all governance gates passed).
            //   4. The FAQ answer is present in allowed_context (non-empty).
            // ----------------------------------------------------------------
            if (
                !($adapterResult['success'] ?? false)
                && $normalizedFieldKey !== null
                && str_starts_with($normalizedFieldKey, 'faq_answers.')
                && ($promptPackage['status'] ?? '') === 'prompt_ready'
                && !empty($promptPackage['allowed_context'])
            ) {
                $faqSegments = explode('.', $normalizedFieldKey, 2);
                $faqTopKey   = $faqSegments[0] ?? '';
                $faqChildKey = $faqSegments[1] ?? '';
                $faqEntry    = $promptPackage['allowed_context'][$faqTopKey][$faqChildKey] ?? null;
                $faqText     = is_array($faqEntry) ? ($faqEntry['answer_text'] ?? null) : null;

                if ($faqText !== null && $faqText !== '') {
                    $faqFinalResponse = [
                        'success'            => true,
                        'status'             => 'ready',
                        'answer'             => $faqText,
                        'disclosures'        => $promptPackage['required_disclosures'] ?? [],
                        'source_attribution' => $promptPackage['source_attribution'] ?? [],
                        'refusal_message'    => null,
                        'error'              => null,
                        'source'             => ['answer_source' => 'openai', 'snapshot_id' => null, 'canonical_key' => null, 'match_type' => null, 'snapshot_version' => null],
                    ];
                    $faqFinalResponse['follow_up_questions'] = $this->followUpService->forResult(
                        $faqFinalResponse,
                        $classification
                    );

                    $trace['final_status']       = 'ready';
                    $trace['source_attribution'] = $faqFinalResponse['source_attribution'] ?? null;
                    $this->emitTrace($trace);

                    return [
                        'success'          => true,
                        'status'           => 'ready',
                        'classification'   => $classification,
                        'context'          => $context,
                        'contract'         => $contract,
                        'prompt_package'   => $promptPackage,
                        'adapter_result'   => $adapterResult,
                        'final_response'   => $faqFinalResponse,
                        'error'            => null,
                        'trace'            => $trace,
                        'outcome_category' => 'openai_fallback',
                    ];
                }
            }

            // ----------------------------------------------------------------
            // listing.* direct-return fallback.
            // When the adapter fails but the listing field IS populated with a
            // real value, return that value directly rather than propagating the
            // generic "could not generate" error.  This mirrors the FAQ
            // direct-return fallback above and closes the second gap for the
            // listing.* path.
            //
            // Only fires when all four conditions hold:
            //   1. Adapter call failed (success = false).
            //   2. A specific listing.* field was resolved for this question.
            //   3. The prompt package was prompt_ready (all governance gates passed).
            //   4. The field value in allowed_context is non-null and non-empty.
            // ----------------------------------------------------------------
            if (
                !($adapterResult['success'] ?? false)
                && $normalizedFieldKey !== null
                && str_starts_with($normalizedFieldKey, 'listing.')
                && ($promptPackage['status'] ?? '') === 'prompt_ready'
                && !empty($promptPackage['allowed_context'])
            ) {
                $listingFieldFallback = substr($normalizedFieldKey, strlen('listing.'));
                $listingDataFallback  = $promptPackage['allowed_context']['listing'] ?? null;
                $listingFieldValue    = is_array($listingDataFallback)
                    ? ($listingDataFallback[$listingFieldFallback] ?? null)
                    : null;

                if ($listingFieldValue !== null && $listingFieldValue !== '') {
                    $listingFallbackResponse = [
                        'success'            => true,
                        'status'             => 'ready',
                        'answer'             => (string) $listingFieldValue,
                        'disclosures'        => $promptPackage['required_disclosures'] ?? [],
                        'source_attribution' => $promptPackage['source_attribution'] ?? [],
                        'refusal_message'    => null,
                        'error'              => null,
                        'source'             => ['answer_source' => 'openai', 'snapshot_id' => null, 'canonical_key' => null, 'match_type' => null, 'snapshot_version' => null],
                    ];
                    $listingFallbackResponse['follow_up_questions'] = $this->followUpService->forResult(
                        $listingFallbackResponse,
                        $classification
                    );

                    $trace['final_status']       = 'ready';
                    $trace['source_attribution'] = $listingFallbackResponse['source_attribution'] ?? null;
                    $this->emitTrace($trace);

                    return [
                        'success'          => true,
                        'status'           => 'ready',
                        'classification'   => $classification,
                        'context'          => $context,
                        'contract'         => $contract,
                        'prompt_package'   => $promptPackage,
                        'adapter_result'   => $adapterResult,
                        'final_response'   => $listingFallbackResponse,
                        'error'            => null,
                        'trace'            => $trace,
                        'outcome_category' => 'openai_fallback',
                    ];
                }
            }

            // ----------------------------------------------------------------
            // Universal prompt-ready adapter-failed fallback.
            //
            // Fires when ALL of the following are true:
            //   1. Adapter call failed (success = false).
            //   2. The prompt package was 'prompt_ready' — i.e. ALL governance
            //      gates (context assembly, response contract, prompt builder)
            //      passed and the only failure is the external OpenAI call.
            //   3. Neither the faq_answers.* nor the listing.* specific fallbacks
            //      handled the failure (they return early before reaching here).
            //
            // This is intentionally NOT gated on question_type so that it covers:
            //   - listing_facts questions with no specific field key pinned
            //     (e.g. "What are the seller financing terms?", "What are the
            //     lease option terms?", "What is the garage situation?")
            //   - property_standout questions (e.g. "What are the key features
            //     of this property?") — these classify as 'property_standout',
            //     not 'listing_facts', so a listing_facts-only gate would miss them
            //   - any other question type where the prompt was ready but the
            //     external LLM call failed transiently
            //
            // Purpose: prevent the generic "Ask AI could not generate a response
            // right now" error banner for ANY prompt-ready question when OpenAI is
            // temporarily unavailable.  Returns a clean 'insufficient_context'
            // status with a user-friendly "try again" message instead.
            // ----------------------------------------------------------------
            if (
                !($adapterResult['success'] ?? false)
                && ($promptPackage['status'] ?? '') === 'prompt_ready'
            ) {
                $unavailableFallbackResponse = [
                    'success'            => false,
                    'status'             => 'insufficient_context',
                    'answer'             => 'A response could not be generated right now. Please try again shortly.',
                    'disclosures'        => $promptPackage['required_disclosures'] ?? [],
                    'source_attribution' => $promptPackage['source_attribution'] ?? [],
                    'refusal_message'    => null,
                    'error'              => null,
                    'source'             => ['answer_source' => 'openai', 'snapshot_id' => null, 'canonical_key' => null, 'match_type' => null, 'snapshot_version' => null],
                ];
                $unavailableFallbackResponse['follow_up_questions'] = $this->followUpService->forResult(
                    $unavailableFallbackResponse,
                    $classification
                );

                $trace['final_status']       = 'insufficient_context';
                $trace['source_attribution'] = $unavailableFallbackResponse['source_attribution'] ?? null;
                $this->emitTrace($trace);

                return [
                    'success'          => false,
                    'status'           => 'insufficient_context',
                    'classification'   => $classification,
                    'context'          => $context,
                    'contract'         => $contract,
                    'prompt_package'   => $promptPackage,
                    'adapter_result'   => $adapterResult,
                    'final_response'   => $unavailableFallbackResponse,
                    'error'            => null,
                    'trace'            => $trace,
                    'outcome_category' => 'openai_fallback',
                ];
            }

            $finalResponse = $this->finalResponseBuilder->build($promptPackage, $adapterResult);

            $finalResponse['follow_up_questions'] = $this->followUpService->forResult(
                $finalResponse,
                $classification
            );

            $error = ($finalResponse['error'] ?? null) ?: null;

            // Derive outcome_category for paths that did not short-circuit (Phase 4
            // not_found fall-through, or non-prompt_ready packages like blocked/unsupported).
            if ($outcomeCategory === null) {
                $outcomeCategory = match ($finalResponse['status'] ?? '') {
                    'blocked'     => 'blocked_restricted',
                    'unsupported' => 'unsupported',
                    default       => 'openai_fallback',
                };
            }

            $trace['final_status']       = $finalResponse['status'];
            $trace['source_attribution'] = $finalResponse['source_attribution'] ?? null;
            $this->emitTrace($trace);

            // Attach OpenAI source metadata to the final response.
            $finalResponse['source'] = [
                'answer_source'    => 'openai',
                'snapshot_id'      => null,
                'canonical_key'    => null,
                'match_type'       => null,
                'snapshot_version' => null,
            ];

            return [
                'success'          => $finalResponse['success'],
                'status'           => $finalResponse['status'],
                'classification'   => $classification,
                'context'          => $context,
                'contract'         => $contract,
                'prompt_package'   => $promptPackage,
                'adapter_result'   => $adapterResult,
                'final_response'   => $finalResponse,
                'error'            => $error,
                'trace'            => $trace,
                'outcome_category' => $outcomeCategory,
            ];

        } catch (\Throwable $e) {
            $exceptionTrace = [
                'question'                    => $question ?? null,
                'classifier_result'           => null,
                'deterministic_question_type' => null,
                'normalizer_called'           => null,
                'normalizer_status'           => $normalizerStatus,
                'normalizer_error'            => $normalizerError,
                'router_called'               => null,
                'router_status'               => null,
                'router_context_path'         => null,
                'normalized_field_key'        => null,
                'faq_key_detected'            => null,
                'final_question_type'         => null,
                'final_status'                => 'failed',
                'source_attribution'          => null,
            ];
            $this->emitTrace($exceptionTrace);
            return [
                'success'        => false,
                'status'         => 'failed',
                'classification' => null,
                'context'        => null,
                'contract'       => null,
                'prompt_package' => null,
                'adapter_result' => null,
                'final_response' => null,
                'error'          => $e->getMessage(),
                'trace'          => $exceptionTrace,
            ];
        }
    }

    /**
     * Emit a debug trace log entry. Silently skips when the Log facade is
     * unavailable (e.g. pure PHPUnit tests without a booted Laravel app).
     *
     * @param  array<string, mixed> $trace
     */
    private function emitTrace(array $trace): void
    {
        try {
            Log::debug('AskAiRunnerV2 trace', $trace);
        } catch (\Throwable $ignored) {
            // Log facade unavailable in unit test contexts without a booted app.
        }
    }

    /**
     * Deterministically detect a specific faq_answers.* field key from the
     * question text.
     *
     * Iterates FAQ_KEY_KEYWORD_MAP and returns the canonical path for the first
     * matching keyword. The check is case-insensitive sub-string matching,
     * consistent with AskAiQuestionClassifierService::findFirstMatch().
     *
     * Returns null when no keyword matches — the caller leaves allowed_context
     * unnarrowed and the full listing_facts data set is available to the LLM.
     *
     * @param  string $question  Raw user question string.
     * @return string|null       Canonical faq_answers.* path, or null.
     */
    private function detectFaqFieldKey(string $question): ?string
    {
        $lower = mb_strtolower(trim($question));

        foreach (self::FAQ_KEY_KEYWORD_MAP as $faqKey => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, mb_strtolower($keyword))) {
                    return $faqKey;
                }
            }
        }

        return null;
    }

    /**
     * Detect a canonical listing.* field path from the question text.
     *
     * Iterates LISTING_KEY_KEYWORD_MAP in order and returns the FIRST match,
     * or null when no unambiguous listing field is identified.
     *
     * @param  string $question  The user's raw question text.
     * @return string|null  e.g. 'listing.bedrooms', 'listing.annual_property_taxes'
     */
    private function detectListingFieldKey(string $question): ?string
    {
        $lower = mb_strtolower(trim($question));

        foreach (self::LISTING_KEY_KEYWORD_MAP as $listingKey => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, mb_strtolower($keyword))) {
                    return $listingKey;
                }
            }
        }

        return null;
    }

    /**
     * Derive a human-readable field label from a normalized faq_answers.* key.
     *
     * Used to compose field-specific missing-data messages such as
     * "Roof information has not been provided for this listing."
     *
     * Known FAQ keys are mapped to their subject-area label. Any key that
     * falls outside the known set gets the safe generic label "The requested
     * information" so the message remains grammatically correct and grounded.
     *
     * @param  string $normalizedFieldKey  Canonical path e.g. 'faq_answers.roof_age_and_condition'.
     * @return string
     */
    private function deriveFieldLabel(string $normalizedFieldKey): string
    {
        $labelMap = [
            // Seller: Property Condition & Maintenance
            'faq_answers.roof_age_and_condition'         => 'Roof information',
            'faq_answers.hvac_system_age'                => 'HVAC system information',
            'faq_answers.water_heater_age_type'          => 'Water heater information',
            'faq_answers.recent_renovations_list'        => 'Recent renovation information',
            'faq_answers.recent_renovations'             => 'Recent renovation information',
            'faq_answers.permits_for_renovations'        => 'Renovation permit information',
            'faq_answers.known_defects_issues'           => 'Known defects/issues information',
            'faq_answers.foundation_type_and_issues'     => 'Foundation information',
            'faq_answers.pest_termite_history'           => 'Pest and termite history information',
            'faq_answers.pest_treatment_history'         => 'Pest treatment information',
            'faq_answers.flood_damage_history'           => 'Flood and water damage history information',
            'faq_answers.mold_issues_history'            => 'Mold history information',
            // Listing.* fields — Tax
            'listing.annual_property_taxes'              => 'Annual property tax information',
            // Listing.* fields — Price & Financial
            'listing.asking_price'                       => 'Asking price information',
            'listing.buy_now_price'                      => 'Buy-now price information',
            'listing.max_price'                          => 'Buyer maximum price information',
            'listing.rent_amount'                        => 'Monthly rent information',
            'listing.max_rent'                           => 'Tenant maximum rent budget information',
            // Listing.* fields — Property Specifications
            'listing.bedrooms'                           => 'Bedroom information',
            'listing.bathrooms'                          => 'Bathroom information',
            'listing.square_feet'                        => 'Square footage information',
            'listing.year_built'                         => 'Year built information',
            'listing.description'                        => 'Listing description information',
            'listing.condition_prop'                     => 'Property condition information',
            // Listing.* fields — Location
            'listing.address'                            => 'Property address information',
            // Listing.* fields — Amenities & Features
            'listing.property_type'                      => 'Property type information',
            'listing.pool'                               => 'Pool information',
            'listing.carport'                            => 'Carport information',
            'listing.garage'                             => 'Garage information',
            'listing.water_view'                         => 'View / water view information',
            'listing.view'                               => 'View information',
            'listing.credit_score_range'                 => 'Credit score range information',
            'listing.appliances'                         => 'Included appliances information',
            // Listing.* fields — HOA & Community
            'listing.hoa_association'                    => 'HOA association information',
            'listing.hoa_fee'                            => 'HOA fee information',
            'listing.hoa_acceptable'                     => 'Buyer HOA acceptability information',
            'listing.has_hoa'                            => 'HOA status information',
            'listing.association_amenities'              => 'Association amenities information',
            // Listing.* fields — Pet Policies
            'listing.pets_allowed'                       => 'Pet policy information',
            'listing.pet_policy'                         => 'Pet policy details information',
            'listing.pet_deposit_fee_rent'               => 'Pet deposit and fee information',
            'listing.pet_information'                    => 'Tenant pet information',
            // Listing.* fields — Lease & Rental Terms
            'listing.lease_terms'                        => 'Existing lease terms information',
            'listing.lease_length'                       => 'Lease length information',
            'listing.desired_lease_length'               => 'Tenant desired lease length information',
            'listing.renewal_option'                     => 'Lease renewal option information',
            'listing.rental_restrictions'                => 'Rental restrictions information',
            // Listing.* fields — Utilities & Services
            'listing.utilities'                          => 'Included utilities information',
            'listing.tenant_pays'                        => 'Tenant utility responsibility information',
            'listing.smoking_policy'                     => 'Smoking policy information',
            'listing.subletting_policy'                  => 'Subletting policy information',
            // Listing.* fields — Parking & Availability
            'listing.parking_terms'                      => 'Parking terms information',
            'listing.available_date'                     => 'Available date information',
            'listing.closing_date'                       => 'Preferred closing date information',
            // Listing.* fields — Buyer Financials & Criteria
            'listing.loan_pre_approved'                  => 'Loan pre-approval information',
            'listing.financing_type'                     => 'Financing type information',
            'listing.inspection_period'                  => 'Inspection period information',
            'listing.inspection_contingency_buyer'       => 'Inspection contingency information',
            'listing.appraisal_contingency_buyer'        => 'Appraisal contingency information',
            'listing.financing_contingency_buyer'        => 'Financing contingency information',
            // Listing.* fields — Safety & Disclosure
            'listing.flood_zone_code'                    => 'Flood zone status information',
            'listing.flood_insurance_required'           => 'Flood insurance requirement information',
            // Listing.* fields — Tax, Legal & Parcel (Landlord)
            'listing.parcel_id'                          => 'Parcel ID information',
            'listing.tax_year'                           => 'Tax year information',
            'listing.legal_description'                  => 'Legal description information',
            'listing.additional_parcels'                 => 'Additional parcels information',
            'listing.total_parcel_count'                 => 'Total parcel count information',
            'listing.additional_parcel_ids'              => 'Additional parcel IDs information',
            // Listing.* fields — Structural & Exterior (Landlord)
            'listing.lot_dimensions'                     => 'Lot dimensions information',
            'listing.zoning'                             => 'Zoning information',
            'listing.waterfront'                         => 'Waterfront information',
            'listing.water_access'                       => 'Water access information',
            'listing.interior_features'                  => 'Interior features information',
            'listing.roof_type'                          => 'Roof type information',
            'listing.exterior_construction'              => 'Exterior construction information',
            'listing.foundation'                         => 'Foundation information',
            // Listing.* fields — Rental Financial & Terms (Landlord)
            'listing.security_deposit_amount'            => 'Security deposit information',
            'listing.terms_of_lease'                     => 'Terms of lease information',
            'listing.rent_includes'                      => 'Included rent information',
            'listing.heating_fuel'                       => 'Heating fuel information',
            'listing.air_conditioning'                   => 'Air conditioning information',
            'listing.water'                              => 'Water source information',
            'listing.sewer'                              => 'Sewer information',
            'listing.lease_amount_frequency'             => 'Lease payment frequency information',
            'listing.has_cdd'                            => 'CDD status information',
            'listing.annual_cdd_fee'                     => 'Annual CDD fee information',
            // Listing.* fields — Commercial Sale: Building & Site
            'listing.lot_acreage'                        => 'Lot acreage information',
            'listing.building_sqft'                      => 'Building square footage information',
            'listing.ceiling_height'                     => 'Ceiling height information',
            'listing.parking_spaces'                     => 'Parking spaces information',
            'listing.building_features'                  => 'Building features information',
            // current_use covers both commercial and Vacant Land contexts
            'listing.current_use'                        => 'Current land use information',
            // Listing.* fields — Commercial Sale: Financial
            'listing.annual_noi'                         => 'Annual net operating income information',
            'listing.cap_rate'                           => 'Cap rate information',
            'listing.price_per_sqft'                     => 'Price per square foot information',
            // Listing.* fields — Commercial Sale: Lease
            'listing.existing_lease_type'                => 'Existing lease type information',
            'listing.lease_expiration'                   => 'Lease expiration information',
            'listing.lease_assignable'                   => 'Lease assignability information',
            // Listing.* fields — Commercial Sale: Utilities
            'listing.water_source'                       => 'Water source information',
            // Listing.* fields — Commercial Sale: Flood Zone
            'listing.flood_zone_panel'                   => 'Flood zone panel information',
            'listing.flood_zone_date'                    => 'Flood zone map date information',
            // Listing.* fields — Commercial Sale: Assessments & HOA
            'listing.has_special_assessments'            => 'Special assessments status information',
            'listing.special_assessment_amount'          => 'Special assessment amount information',
            'listing.hoa_name'                           => 'HOA name information',
            // Listing.* fields — Land & Lot (Seller / Vacant Land)
            'listing.total_acreage'                      => 'Acreage information',
            // Listing.* fields — Vacant Land specific
            'listing.current_adjacent_use'               => 'Adjacent land use information',
            'listing.water_available'                    => 'Water availability information',
            'listing.sewer_available'                    => 'Sewer availability information',
            'listing.electric_available'                 => 'Electric availability information',
            'listing.gas_available'                      => 'Gas availability information',
            'listing.telecom_available'                  => 'Telecom and internet availability information',
            'listing.road_frontage'                      => 'Road frontage information',
            'listing.road_surface_type'                  => 'Road surface type information',
            'listing.front_footage'                      => 'Front footage information',
            'listing.number_of_wells'                    => 'Well information',
            'listing.number_of_septics'                  => 'Septic system information',
            'listing.fences'                             => 'Fence information',
            'listing.vegetation'                         => 'Vegetation information',
            'listing.buildable'                          => 'Buildability information',
            'listing.easements'                          => 'Easement information',
            // Listing.* fields — Shared / Structural details (deriveFieldLabel gap coverage)
            'listing.lot_size'                           => 'Lot size information',
            'listing.heating_and_fuel'                   => 'Heating and fuel type information',
            // Listing.* fields — Commercial / Seller Transaction Terms
            'listing.sale_provision'                     => 'Sale provision information',
            'listing.offered_financing'                  => 'Offered financing information',
            'listing.occupant_status'                    => 'Occupant status information',
            'listing.furnished'                          => 'Furnished status information',
            // Listing.* fields — HOA sub-fields (rental context)
            'listing.association_name'                   => 'Association name information',
            'listing.hoa_payment_schedule'               => 'HOA payment schedule information',
            'listing.association_fee_includes'           => 'Association fee inclusion information',
            // Listing.* fields — Special Assessments (description text)
            'listing.special_assessment_description'     => 'Special assessment description information',
            // Listing.* fields — Income / Multifamily
            'listing.gross_annual_income'                => 'Gross annual rental income information',
            'listing.annual_net_income'                  => 'Annual net operating income information',
            'listing.annual_operating_expenses'          => 'Annual operating expenses information',
            'listing.total_units'                        => 'Total unit count information',
            'listing.total_buildings'                    => 'Total building count information',
            'listing.unit_mix_summary'                   => 'Unit mix and configuration information',
            'listing.rent_roll_available'                => 'Rent roll availability information',
            'listing.operating_statement_available'      => 'Operating statement availability information',
            'listing.property_items'                     => 'Property type mix information',
            'listing.occupancy_requirement'              => 'Occupancy requirement information',
            'listing.income_requirement'                 => 'Income requirement information',
            // Landlord: Commercial Lease Terms
            'listing.commercial_lease_type'              => 'Commercial lease type information',
            'listing.cam_nnn_additional_rent_charges'    => 'CAM / NNN additional rent charge information',
            'listing.first_month_rent_required'          => 'First month rent requirement information',
            'listing.last_month_rent_required'           => 'Last month rent requirement information',
            'listing.total_move_in_funds_required'       => 'Total move-in funds information',
            // Landlord: Commercial Building Details
            'listing.number_of_restrooms'                => 'Number of restrooms information',
            'listing.office_retail_sqft'                 => 'Office / retail square footage information',
            // Landlord: Additional Terms
            'listing.pet_deposit_amount'                 => 'Pet deposit information',
            'listing.pet_monthly_fee'                    => 'Monthly pet fee information',
            'listing.number_of_occupants_allowed'        => 'Maximum occupants allowed information',
            'listing.min_income_requirement'             => 'Minimum income requirement information',
            'listing.signage_rights'                     => 'Signage rights information',
            'listing.building_hours'                     => 'Building access hours information',
            'listing.access_24_7'                        => '24/7 building access information',
            'listing.shared_amenities'                   => 'Shared amenities information',
            'listing.renewal_option_details'             => 'Lease renewal option details information',
            // Seller: Financial & Utility Insights
            'faq_answers.average_utility_costs'          => 'Utility cost information',
            'faq_answers.internet_utility_providers'     => 'Internet and utility provider information',
            'faq_answers.seller_concessions_offered'     => 'Seller concession information',
            // Seller: Flexibility & Negotiation
            'faq_answers.closing_timeline_flexibility'   => 'Closing timeline information',
            'faq_answers.items_excluded_from_sale'       => 'Excluded items information',
            'faq_answers.as_is_condition'                => 'As-is condition information',
            // Seller: Hidden Selling Points
            'faq_answers.unique_selling_points'          => 'Unique selling point information',
            'faq_answers.parking_arrangements'           => 'Parking arrangement information',
            'faq_answers.hoa_community_highlights'       => 'HOA and community amenity information',
            // Landlord: Maintenance & Property Condition
            'faq_answers.heating_cooling_system'         => 'Heating and cooling system information',
            'faq_answers.laundry_situation'              => 'Laundry information',
            'faq_answers.maintenance_request_response_time' => 'Maintenance request information',
            'faq_answers.emergency_maintenance_available'   => 'Emergency maintenance information',
            'faq_answers.security_features'              => 'Security feature information',
            'faq_answers.pest_or_mold_history'           => 'Pest and mold history information',
            // Landlord: Lifestyle & Flexibility
            'faq_answers.lease_renewal_process'          => 'Lease renewal process information',
            'faq_answers.subletting_allowed'             => 'Subletting policy information',
            'faq_answers.smoking_policy'                 => 'Smoking policy information',
            'faq_answers.what_makes_property_unique'     => 'Unique property information',
            // Shared / generic canonical keys
            'faq_answers.appliances_included'            => 'Appliance information',
            'faq_answers.hoa_rules_restrictions'         => 'HOA rules information',
            'faq_answers.neighborhood_highlights'        => 'Neighborhood information',
            'faq_answers.showing_tips_seller'            => 'Showing information',
            'faq_answers.property_unique_features'       => 'Unique feature information',
            'faq_answers.landlord_responsibilities'      => 'Landlord responsibility information',
            'faq_answers.tenant_rules_regulations'       => 'Tenant rules information',
            'faq_answers.lease_renewal_terms'            => 'Lease renewal information',
            'faq_answers.utility_setup_instructions'     => 'Utility setup information',
            'faq_answers.pet_policy_details'             => 'Pet policy information',
            'faq_answers.parking_instructions'           => 'Parking information',
            // Seller: Location & Lifestyle
            'faq_answers.neighborhood_character'         => 'Neighborhood character information',
            'faq_answers.traffic_or_noise_concerns'      => 'Traffic and noise concern information',
            'faq_answers.planned_nearby_development'     => 'Planned nearby development information',
            'faq_answers.commute_options_access'         => 'Commute options information',
            'faq_answers.natural_light_orientation'      => 'Natural light and orientation information',
            'faq_answers.nearby_amenities_description'   => 'Nearby amenities information',
            'faq_answers.neighborhood_restrictions'      => 'Neighborhood restriction information',
            // Seller: Flexibility & Negotiation (remaining)
            'faq_answers.seller_leaseback_option'        => 'Seller leaseback option information',
            'faq_answers.furniture_negotiability'        => 'Furniture negotiability information',
            'faq_answers.environmental_concerns'         => 'Environmental concern information',
            // Seller: Hidden Selling Points (remaining)
            'faq_answers.seller_favorite_features'       => 'Seller favorite feature information',
            'faq_answers.seller_motivation_for_selling'  => 'Seller motivation information',
            'faq_answers.move_in_ready_status'           => 'Move-in ready status information',
            'faq_answers.storage_space_available'        => 'Storage space information',
            // Landlord: Maintenance (remaining)
            'faq_answers.storage_area_included'          => 'Storage area information',
            'faq_answers.internet_providers'             => 'Internet provider information',
            'faq_answers.planned_renovations'            => 'Planned renovation information',
            // Landlord: Location & Neighborhood (remaining)
            'faq_answers.noise_levels'                   => 'Noise level information',
            'faq_answers.nearby_amenities'               => 'Nearby amenities information',
            'faq_answers.guest_parking'                  => 'Guest parking information',
            'faq_answers.proximity_to_public_transit'    => 'Public transit proximity information',
            // Landlord: Lifestyle & Flexibility (remaining)
            'faq_answers.furnished_or_unfurnished'       => 'Furnishing status information',
            'faq_answers.notice_to_vacate_required'      => 'Notice to vacate information',
            'faq_answers.preferred_tenant_qualities'     => 'Preferred tenant qualities information',
            'faq_answers.short_term_rentals_allowed'     => 'Short-term rental policy information',
            'faq_answers.ev_charging_available'          => 'EV charging information',
            'faq_answers.bicycle_storage_available'      => 'Bicycle storage information',
            // Landlord: High-Intent (remaining)
            'faq_answers.utilities_individually_metered' => 'Utility metering information',
            'faq_answers.renters_insurance_required'     => 'Renters insurance requirement information',
            'faq_answers.lease_to_own_option'            => 'Lease-to-own option information',
            'faq_answers.previous_tenant_feedback'       => 'Previous tenant feedback information',
            // Seller Addon: Commercial Income
            'faq_answers.annual_net_operating_income'            => 'Net operating income (NOI) information',
            'faq_answers.current_cap_rate'                       => 'Capitalization rate information',
            'faq_answers.existing_tenant_lease_terms'            => 'Existing tenant lease terms information',
            'faq_answers.current_occupancy_rate'                 => 'Current occupancy rate information',
            'faq_answers.annual_operating_expenses_detail'       => 'Annual operating expenses information',
            'faq_answers.value_add_opportunities'                => 'Value-add opportunity information',
            // Seller Addon: Business Opportunity
            'faq_answers.annual_business_revenue'                => 'Annual business revenue information',
            'faq_answers.annual_net_profit'                      => 'Annual net profit information',
            'faq_answers.business_reason_for_selling'            => 'Business reason for selling information',
            'faq_answers.business_employee_count'                => 'Business employee count information',
            'faq_answers.seller_training_transition'             => 'Seller training and transition information',
            'faq_answers.business_lease_status'                  => 'Business location lease status information',
            'faq_answers.inventory_equipment_included'           => 'Inventory and equipment included information',
            // Seller Addon: Vacant Land
            'faq_answers.land_utilities_availability'            => 'Land utilities availability information',
            'faq_answers.land_zoning_permitted_uses'             => 'Land zoning and permitted uses information',
            'faq_answers.land_access_and_road'                   => 'Land road access information',
            'faq_answers.land_soil_and_topography'               => 'Land soil and topography information',
            'faq_answers.land_survey_available'                  => 'Land survey availability information',
            'faq_answers.land_development_restrictions'          => 'Land development restriction information',
            // Landlord Addon: Commercial Lease
            'faq_answers.commercial_cam_charges'                          => 'CAM charges information',
            'faq_answers.commercial_lease_structure_type'                 => 'Commercial lease structure information',
            'faq_answers.commercial_tenant_improvement_allowance'         => 'Tenant improvement allowance information',
            'faq_answers.commercial_buildout_flexibility'                 => 'Commercial buildout flexibility information',
            'faq_answers.commercial_signage_rights'                       => 'Commercial signage rights information',
            'faq_answers.commercial_loading_dock_freight_elevator'        => 'Loading dock and freight elevator information',
            'faq_answers.commercial_electrical_capacity'                  => 'Commercial electrical capacity information',
            'faq_answers.commercial_parking_ratio'                        => 'Commercial parking ratio information',
            'faq_answers.commercial_exclusivity_rights'                   => 'Commercial exclusivity rights information',
            'faq_answers.commercial_expansion_option_rofr'                => 'Commercial expansion option and ROFR information',
            'faq_answers.commercial_landlord_maintenance_responsibilities' => 'Commercial landlord maintenance responsibility information',
            'faq_answers.commercial_building_access_hours'                => 'Commercial building access hours information',
            // Tenant FAQ (faq_q1–faq_q27)
            'faq_answers.faq_q1'  => 'Tenant work-from-home information',
            'faq_answers.faq_q2'  => 'Tenant day-to-day living priorities',
            'faq_answers.faq_q3'  => 'Tenant neighborhood preference information',
            'faq_answers.faq_q4'  => 'Tenant noise sensitivity information',
            'faq_answers.faq_q5'  => 'Tenant amenity priority information',
            'faq_answers.faq_q6'  => 'Tenant outdoor space preference information',
            'faq_answers.faq_q7'  => 'Tenant pet information',
            'faq_answers.faq_q8'  => 'Tenant pet deposit willingness information',
            'faq_answers.faq_q9'  => 'Tenant lease length flexibility information',
            'faq_answers.faq_q10' => 'Tenant furnished unit preference information',
            'faq_answers.faq_q11' => 'Tenant move-in timeline information',
            'faq_answers.faq_q12' => 'Tenant early lease termination information',
            'faq_answers.faq_q13' => 'Tenant lease term and rent reduction preference information',
            'faq_answers.faq_q14' => 'Tenant rental search motivation information',
            'faq_answers.faq_q15' => 'Tenant tenancy history information',
            'faq_answers.faq_q16' => 'Tenant short-term or long-term housing preference information',
            'faq_answers.faq_q17' => 'Tenant reference availability information',
            'faq_answers.faq_q18' => 'Tenant income source information',
            'faq_answers.faq_q19' => 'Tenant communication preference information',
            'faq_answers.faq_q20' => 'Tenant rental search concern information',
            'faq_answers.faq_q21' => 'Tenant commercial business type information',
            'faq_answers.faq_q22' => 'Tenant expected foot traffic information',
            'faq_answers.faq_q23' => 'Tenant equipment and power requirements information',
            'faq_answers.faq_q24' => 'Tenant exterior signage requirement information',
            'faq_answers.faq_q25' => 'Tenant space buildout requirement information',
            'faq_answers.faq_q26' => 'Tenant operating hours information',
            'faq_answers.faq_q27' => 'Tenant commercial lease term flexibility information',
        ];

        return $labelMap[$normalizedFieldKey] ?? 'The requested information';
    }
}
