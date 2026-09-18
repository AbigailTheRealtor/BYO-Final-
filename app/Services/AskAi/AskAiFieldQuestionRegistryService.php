<?php

namespace App\Services\AskAi;

use App\Support\AskAi\AskAiPropertyTypeResolver as PT;

/**
 * AskAiFieldQuestionRegistryService
 *
 * Comprehensive registry of every field and FAQ config key across all four listing
 * roles (seller, buyer, landlord, tenant), documenting each key's canonical context
 * path, human label, two representative sample questions, field type, and keyword
 * route status.
 *
 * Two registries are maintained:
 *  1. registry()          — FAQ config keys (faq_answers.* canonical paths, 168 entries)
 *  2. listingFieldRegistry() — Native listing model fields (listing.* paths, ~45 entries)
 *
 * Purpose:
 *  1. Single source of truth for coverage analysis and harness tests.
 *  2. Powers AskAiCoverageHarnessTest — structural/static coverage assertions.
 *  3. Powers AskAiPipelineCoverageE2ETest — pipeline execution coverage tests.
 *  4. Documents which FAQ config keys exist per role and why some are not pinned.
 *  5. Documents all approved listing.* context paths verified in context builder
 *     and response contract service.
 *
 * Structure of each FAQ registry entry (registry()):
 *  'faq_answers.<config_key>' => [
 *      'roles'                => ['seller', 'landlord', ...],
 *      'field_type'           => 'faq',
 *      'config_key'           => '<raw config key>',
 *      'label'                => '<human readable question label>',
 *      'sample_question'      => '<first natural-language question a user might ask>',
 *      'sample_question_2'    => '<second distinct natural-language question>',
 *      'keyword_route_status' => '<see values below>',
 *      'governance_note'      => '<optional — explains deferred or excluded routing>',
 *  ]
 *
 * Structure of each listing model registry entry (listingFieldRegistry()):
 *  'listing.<field_name>' => [
 *      'roles'                => ['seller', 'landlord', ...],
 *      'field_type'           => 'listing_model',
 *      'config_key'           => '<listing model attribute name>',
 *      'label'                => '<human readable field label>',
 *      'sample_question'      => '<first natural-language question>',
 *      'sample_question_2'    => '<second distinct natural-language question>',
 *      'keyword_route_status' => 'listing_native',
 *  ]
 *
 * keyword_route_status values:
 *  'pinned'          — Has FAQ_KEY_KEYWORD_MAP entry + deriveFieldLabel entry.
 *                      Keyword detection fires and missing-data guard works.
 *  'umbrella_only'   — Accessible via faq_answers umbrella context but no pinned
 *                      keyword route. Applies to seller/landlord addon keys (commercial,
 *                      business opportunity, vacant land). Deferred to follow-up work.
 *  'match_criteria'  — Buyer/tenant match criteria answered by the listing party.
 *                      These route as 'buyer_tenant_match', not 'listing_facts'.
 *                      Intentionally not in FAQ_KEY_KEYWORD_MAP.
 *  'opaque_key'      — Tenant FAQ keys faq_q1–faq_q27 use sequential opaque IDs.
 *                      Cannot be pinned until config is extended with natural_questions.
 *                      Deferred to follow-up work.
 *  'listing_native'  — Native listing model attribute. Always available from the
 *                      listing model; routes via listing_facts classifier but does
 *                      not require FAQ keyword detection or missing-data guard.
 */
class AskAiFieldQuestionRegistryService
{
    /**
     * Returns the complete FAQ field question registry across all four roles.
     * All entries include field_type='faq' and a sample_question_2.
     *
     * @return array<string, array{roles: string[], field_type: string, config_key: string, label: string, sample_question: string, sample_question_2: string, keyword_route_status: string}>
     */
    public static function registry(): array
    {
        return static::withSecondQuestions(array_merge(
            static::sellerBaseRegistry(),
            static::landlordBaseRegistry(),
            static::sellerAddonRegistry(),
            static::landlordAddonRegistry(),
            static::buyerBaseRegistry(),
            static::buyerAddonRegistry(),
            static::tenantRegistry()
        ));
    }

    // =========================================================================
    // SELLER — Base FAQ Keys (pinned)
    // =========================================================================

    private static function sellerBaseRegistry(): array
    {
        return [
            // ----------------------------------------------------------------
            // SELLER — Property Condition & Maintenance
            // ----------------------------------------------------------------
            'faq_answers.roof_age_and_condition' => [
                'roles'                => ['seller'],
                'config_key'           => 'roof_age_and_condition',
                'label'                => 'How old is the roof, and what condition is it in?',
                'sample_question'      => 'How old is the roof?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.hvac_system_age' => [
                'roles'                => ['seller'],
                'config_key'           => 'hvac_system_age',
                'label'                => 'How old is the HVAC system, and when was it last serviced?',
                'sample_question'      => 'How old is the HVAC system?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.water_heater_age_type' => [
                'roles'                => ['seller'],
                'config_key'           => 'water_heater_age_type',
                'label'                => 'How old is the water heater, and what type is it?',
                'sample_question'      => 'What type of water heater does this property have?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.recent_renovations_list' => [
                'roles'                => ['seller'],
                'config_key'           => 'recent_renovations_list',
                'label'                => 'What renovations or upgrades have been made, and when?',
                'sample_question'      => 'What renovations have been made to this property?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.permits_for_renovations' => [
                'roles'                => ['seller'],
                'config_key'           => 'permits_for_renovations',
                'label'                => 'Were all renovations and additions completed with proper permits?',
                'sample_question'      => 'Were all renovations permitted?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.known_defects_issues' => [
                'roles'                => ['seller'],
                'config_key'           => 'known_defects_issues',
                'label'                => 'Are there any known defects, issues, or deferred repairs?',
                'sample_question'      => 'Are there any known issues or defects with this property?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.foundation_type_and_issues' => [
                'roles'                => ['seller'],
                'config_key'           => 'foundation_type_and_issues',
                'label'                => 'What type of foundation does the property have, and are there any known issues?',
                'sample_question'      => 'What type of foundation does this property have?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.pest_termite_history' => [
                'roles'                => ['seller'],
                'config_key'           => 'pest_termite_history',
                'label'                => 'Has the property ever had pest or termite issues, and how were they resolved?',
                'sample_question'      => 'Has there been any termite damage or pest history?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.flood_damage_history' => [
                'roles'                => ['seller'],
                'config_key'           => 'flood_damage_history',
                'label'                => 'Has the property ever flooded or experienced water damage?',
                'sample_question'      => 'Has this property ever flooded or had water damage?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.mold_issues_history' => [
                'roles'                => ['seller'],
                'config_key'           => 'mold_issues_history',
                'label'                => 'Has the property ever had mold issues, and how were they addressed?',
                'sample_question'      => 'Has there been any mold history in this property?',
                'keyword_route_status' => 'pinned',
            ],
            // ----------------------------------------------------------------
            // SELLER — Financial & Utility Insights
            // ----------------------------------------------------------------
            'faq_answers.average_utility_costs' => [
                'roles'                => ['seller'],
                'config_key'           => 'average_utility_costs',
                'label'                => 'What are the average monthly utility costs (electric, gas, water)?',
                'sample_question'      => 'What are the average monthly utility costs?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.internet_utility_providers' => [
                'roles'                => ['seller'],
                'config_key'           => 'internet_utility_providers',
                'label'                => 'Which internet and utility providers serve this property?',
                'sample_question'      => 'Which internet providers are available at this property?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.seller_concessions_offered' => [
                'roles'                => ['seller'],
                'config_key'           => 'seller_concessions_offered',
                'label'                => 'Are you open to offering seller concessions or repair credits?',
                'sample_question'      => 'Is the seller offering any concessions or closing cost credits?',
                'keyword_route_status' => 'pinned',
            ],
            // ----------------------------------------------------------------
            // SELLER — Location & Lifestyle
            // ----------------------------------------------------------------
            'faq_answers.neighborhood_character' => [
                'roles'                => ['seller', 'landlord'],
                'config_key'           => 'neighborhood_character',
                'label'                => 'How would you describe the neighborhood vibe and community feel?',
                'sample_question'      => 'How would you describe the neighborhood?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.traffic_or_noise_concerns' => [
                'roles'                => ['seller'],
                'config_key'           => 'traffic_or_noise_concerns',
                'label'                => 'Are there any notable traffic, noise, or nuisance concerns nearby?',
                'sample_question'      => 'Are there any noise or traffic issues near this property?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.planned_nearby_development' => [
                'roles'                => ['seller'],
                'config_key'           => 'planned_nearby_development',
                'label'                => 'Are there any planned developments, road projects, or zoning changes nearby?',
                'sample_question'      => 'Are there any planned developments nearby?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.commute_options_access' => [
                'roles'                => ['seller'],
                'config_key'           => 'commute_options_access',
                'label'                => 'What are typical commute options and travel times to major employment centers?',
                'sample_question'      => 'What are the commute options from this property?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.natural_light_orientation' => [
                'roles'                => ['seller'],
                'config_key'           => 'natural_light_orientation',
                'label'                => 'How is the natural light throughout the day, and which direction does the home face?',
                'sample_question'      => 'How does this home get natural light throughout the day?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.nearby_amenities_description' => [
                'roles'                => ['seller'],
                'config_key'           => 'nearby_amenities_description',
                'label'                => 'What nearby amenities, shops, or dining do you value most about this location?',
                'sample_question'      => 'What amenities are nearby this property?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.neighborhood_restrictions' => [
                'roles'                => ['seller'],
                'config_key'           => 'neighborhood_restrictions',
                'label'                => 'Are there any deed restrictions, neighborhood covenants, or community rules?',
                'sample_question'      => 'Are there any neighborhood restrictions or deed covenants?',
                'keyword_route_status' => 'pinned',
            ],
            // ----------------------------------------------------------------
            // SELLER — Flexibility & Negotiation
            // ----------------------------------------------------------------
            'faq_answers.closing_timeline_flexibility' => [
                'roles'                => ['seller'],
                'config_key'           => 'closing_timeline_flexibility',
                'label'                => 'How flexible is the seller on the closing timeline?',
                'sample_question'      => 'Is the seller flexible on the closing timeline?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.seller_leaseback_option' => [
                'roles'                => ['seller'],
                'config_key'           => 'seller_leaseback_option',
                'label'                => 'Is the seller open to a leaseback arrangement after closing?',
                'sample_question'      => 'Is the seller open to a leaseback?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.items_excluded_from_sale' => [
                'roles'                => ['seller'],
                'config_key'           => 'items_excluded_from_sale',
                'label'                => 'Are there any items that will NOT convey with the property?',
                'sample_question'      => 'Are there any items excluded from the sale, or what does not convey?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.furniture_negotiability' => [
                'roles'                => ['seller'],
                'config_key'           => 'furniture_negotiability',
                'label'                => 'Is any furniture negotiable or available for purchase?',
                'sample_question'      => 'Is any of the furniture negotiable?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.as_is_condition' => [
                'roles'                => ['seller'],
                'config_key'           => 'as_is_condition',
                'label'                => 'Is the property being sold as-is, or are repairs/credits negotiable?',
                'sample_question'      => 'Is this property being sold as-is?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.environmental_concerns' => [
                'roles'                => ['seller'],
                'config_key'           => 'environmental_concerns',
                'label'                => 'Are there any known environmental concerns with the property?',
                'sample_question'      => 'Are there any environmental concerns with this property?',
                'keyword_route_status' => 'pinned',
            ],
            // ----------------------------------------------------------------
            // SELLER — Hidden Selling Points
            // ----------------------------------------------------------------
            'faq_answers.unique_selling_points' => [
                'roles'                => ['seller'],
                'config_key'           => 'unique_selling_points',
                'label'                => 'What are the most unique or non-obvious selling points of this property?',
                'sample_question'      => 'What makes this property special that I might not see in photos?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.seller_favorite_features' => [
                'roles'                => ['seller'],
                'config_key'           => 'seller_favorite_features',
                'label'                => 'What are the seller\'s favorite features of this home?',
                'sample_question'      => 'What are the seller\'s favorite things about this home?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.seller_motivation_for_selling' => [
                'roles'                => ['seller'],
                'config_key'           => 'seller_motivation_for_selling',
                'label'                => 'What is the seller\'s motivation for selling?',
                'sample_question'      => 'Why is the seller selling this property?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.move_in_ready_status' => [
                'roles'                => ['seller'],
                'config_key'           => 'move_in_ready_status',
                'label'                => 'Is the home truly move-in ready, or are there items a buyer should plan for?',
                'sample_question'      => 'Is this home truly move-in ready?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.parking_arrangements' => [
                'roles'                => ['seller'],
                'config_key'           => 'parking_arrangements',
                'label'                => 'What are the parking arrangements — driveway, tandem, RV access?',
                'sample_question'      => 'What are the parking arrangements for this property?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.storage_space_available' => [
                'roles'                => ['seller'],
                'config_key'           => 'storage_space_available',
                'label'                => 'Is there ample storage space, and where is it located?',
                'sample_question'      => 'How much storage space does this property have?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.hoa_community_highlights' => [
                'roles'                => ['seller'],
                'config_key'           => 'hoa_community_highlights',
                'label'                => 'What are the HOA or community amenities and highlights?',
                'sample_question'      => 'What amenities does the HOA or community offer?',
                'keyword_route_status' => 'pinned',
            ],
        ];
    }

    // =========================================================================
    // LANDLORD — Base FAQ Keys (pinned)
    // =========================================================================

    private static function landlordBaseRegistry(): array
    {
        return [
            // ----------------------------------------------------------------
            // LANDLORD — Maintenance & Property Condition
            // ----------------------------------------------------------------
            'faq_answers.maintenance_request_response_time' => [
                'roles'                => ['landlord'],
                'config_key'           => 'maintenance_request_response_time',
                'label'                => 'How are maintenance requests handled, and what\'s the typical response time?',
                'sample_question'      => 'How do I submit a maintenance request, and how quickly are repairs handled?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.emergency_maintenance_available' => [
                'roles'                => ['landlord'],
                'config_key'           => 'emergency_maintenance_available',
                'label'                => 'Is 24-hour emergency maintenance available?',
                'sample_question'      => 'Is there 24-hour emergency maintenance available?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.heating_cooling_system' => [
                'roles'                => ['landlord'],
                'config_key'           => 'heating_cooling_system',
                'label'                => 'What type of heating and cooling system does the property have?',
                'sample_question'      => 'What type of heating and cooling system does this rental have?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.laundry_situation' => [
                'roles'                => ['landlord'],
                'config_key'           => 'laundry_situation',
                'label'                => 'Is there in-unit laundry, or shared laundry facilities on-site?',
                'sample_question'      => 'Is there in-unit laundry?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.storage_area_included' => [
                'roles'                => ['landlord'],
                'config_key'           => 'storage_area_included',
                'label'                => 'Is there dedicated storage space included with the rental?',
                'sample_question'      => 'Is there a dedicated storage space included with this unit?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.internet_providers' => [
                'roles'                => ['landlord'],
                'config_key'           => 'internet_providers',
                'label'                => 'Which internet providers are available at this property?',
                'sample_question'      => 'Which internet providers serve this property?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.security_features' => [
                'roles'                => ['landlord'],
                'config_key'           => 'security_features',
                'label'                => 'What security features does the property have?',
                'sample_question'      => 'What security features does this rental have?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.planned_renovations' => [
                'roles'                => ['landlord'],
                'config_key'           => 'planned_renovations',
                'label'                => 'Are there any planned renovations or construction that could affect tenants?',
                'sample_question'      => 'Are there any upcoming renovations that could disrupt tenants?',
                'keyword_route_status' => 'pinned',
            ],
            // ----------------------------------------------------------------
            // LANDLORD — Location & Neighborhood
            // ----------------------------------------------------------------
            'faq_answers.noise_levels' => [
                'roles'                => ['landlord'],
                'config_key'           => 'noise_levels',
                'label'                => 'What\'s the noise level like — traffic, nearby businesses, neighboring units?',
                'sample_question'      => 'How noisy is this area?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.nearby_amenities' => [
                'roles'                => ['landlord'],
                'config_key'           => 'nearby_amenities',
                'label'                => 'What dining, shopping, parks, or transit options are close by?',
                'sample_question'      => 'What amenities or transit options are nearby?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.guest_parking' => [
                'roles'                => ['landlord'],
                'config_key'           => 'guest_parking',
                'label'                => 'Is guest parking available on the property or nearby?',
                'sample_question'      => 'Is there guest parking available?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.proximity_to_public_transit' => [
                'roles'                => ['landlord'],
                'config_key'           => 'proximity_to_public_transit',
                'label'                => 'How close is the nearest public transit stop?',
                'sample_question'      => 'How close is this rental to public transit?',
                'keyword_route_status' => 'pinned',
            ],
            // ----------------------------------------------------------------
            // LANDLORD — Lifestyle & Flexibility
            // ----------------------------------------------------------------
            'faq_answers.furnished_or_unfurnished' => [
                'roles'                => ['landlord'],
                'config_key'           => 'furnished_or_unfurnished',
                'label'                => 'Is the unit available furnished, unfurnished, or is it negotiable?',
                'sample_question'      => 'Is this rental available furnished?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.lease_renewal_process' => [
                'roles'                => ['landlord'],
                'config_key'           => 'lease_renewal_process',
                'label'                => 'How does the lease renewal process work?',
                'sample_question'      => 'How does lease renewal work for this property?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.notice_to_vacate_required' => [
                'roles'                => ['landlord'],
                'config_key'           => 'notice_to_vacate_required',
                'label'                => 'How much notice is required to vacate at the end of a lease?',
                'sample_question'      => 'How much notice is required to vacate?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.preferred_tenant_qualities' => [
                'roles'                => ['landlord'],
                'config_key'           => 'preferred_tenant_qualities',
                'label'                => 'What qualities does the landlord look for in an ideal tenant?',
                'sample_question'      => 'What kind of tenant is the landlord looking for?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.subletting_allowed' => [
                'roles'                => ['landlord'],
                'config_key'           => 'subletting_allowed',
                'label'                => 'Is subletting or subleasing permitted?',
                'sample_question'      => 'Is subletting allowed?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.short_term_rentals_allowed' => [
                'roles'                => ['landlord'],
                'config_key'           => 'short_term_rentals_allowed',
                'label'                => 'Are short-term rentals (Airbnb, VRBO) permitted?',
                'sample_question'      => 'Are short-term rentals like Airbnb allowed?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.ev_charging_available' => [
                'roles'                => ['landlord'],
                'config_key'           => 'ev_charging_available',
                'label'                => 'Is EV charging available or permitted to install?',
                'sample_question'      => 'Is there EV charging available at this property?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.bicycle_storage_available' => [
                'roles'                => ['landlord'],
                'config_key'           => 'bicycle_storage_available',
                'label'                => 'Is there secure bicycle storage available?',
                'sample_question'      => 'Is there secure bicycle storage at this property?',
                'keyword_route_status' => 'pinned',
            ],
            // ----------------------------------------------------------------
            // LANDLORD — High-Intent Tenant Questions
            // ----------------------------------------------------------------
            'faq_answers.what_makes_property_unique' => [
                'roles'                => ['landlord'],
                'config_key'           => 'what_makes_property_unique',
                'label'                => 'What makes this rental property stand out from comparable units?',
                'sample_question'      => 'What makes this rental unique or stand out?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.pest_or_mold_history' => [
                'roles'                => ['landlord'],
                'config_key'           => 'pest_or_mold_history',
                'label'                => 'Has this property had any pest or mold issues, and how were they addressed?',
                'sample_question'      => 'Has this rental ever had pest or mold issues?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.utilities_individually_metered' => [
                'roles'                => ['landlord'],
                'config_key'           => 'utilities_individually_metered',
                'label'                => 'Are utilities individually metered or shared?',
                'sample_question'      => 'Are utilities individually metered for this unit?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.renters_insurance_required' => [
                'roles'                => ['landlord'],
                'config_key'           => 'renters_insurance_required',
                'label'                => 'Is renters insurance required, and what is the minimum coverage amount?',
                'sample_question'      => 'Is renters insurance required?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.lease_to_own_option' => [
                'roles'                => ['landlord'],
                'config_key'           => 'lease_to_own_option',
                'label'                => 'Is a lease-to-own or rent-to-own option available?',
                'sample_question'      => 'Is there a lease-to-own option for this rental?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.previous_tenant_feedback' => [
                'roles'                => ['landlord'],
                'config_key'           => 'previous_tenant_feedback',
                'label'                => 'What do previous tenants commonly say about living here?',
                'sample_question'      => 'What have previous tenants said about living here?',
                'keyword_route_status' => 'pinned',
            ],
            'faq_answers.smoking_policy' => [
                'roles'                => ['landlord'],
                'config_key'           => 'smoking_policy',
                'label'                => 'Is smoking allowed inside the unit or anywhere on the property?',
                'sample_question'      => 'Is smoking allowed at this property?',
                'keyword_route_status' => 'pinned',
            ],
        ];
    }

    // =========================================================================
    // SELLER — Addon FAQ Keys (pinned)
    //
    // These keys are accessible via faq_answers context when the listing has
    // an addon property type (Commercial Income, Business Opportunity, Vacant Land).
    // All entries are pinned: keyword phrases added to FAQ_KEY_KEYWORD_MAP,
    // deriveFieldLabel, and AskAiQuestionClassifierService listing_facts.
    // =========================================================================

    private static function sellerAddonRegistry(): array
    {
        $note = 'Addon key — commercial, business opportunity, or vacant land listing type.';

        return [
            // ----------------------------------------------------------------
            // Seller Addon: Commercial Income
            // ----------------------------------------------------------------
            'faq_answers.annual_net_operating_income' => [
                'roles'                => ['seller'],
                'config_key'           => 'annual_net_operating_income',
                'label'                => 'What is the current annual net operating income (NOI)?',
                'sample_question'      => 'What is the NOI for this property?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.current_cap_rate' => [
                'roles'                => ['seller'],
                'config_key'           => 'current_cap_rate',
                'label'                => 'What is the current capitalization rate?',
                'sample_question'      => 'What is the cap rate for this property?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.existing_tenant_lease_terms' => [
                'roles'                => ['seller'],
                'config_key'           => 'existing_tenant_lease_terms',
                'label'                => 'What are the existing tenant lease terms, rents, and expiration dates?',
                'sample_question'      => 'What are the current tenant lease terms?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.current_occupancy_rate' => [
                'roles'                => ['seller'],
                'config_key'           => 'current_occupancy_rate',
                'label'                => 'What is the current occupancy rate?',
                'sample_question'      => 'What is the current occupancy rate?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.annual_operating_expenses_detail' => [
                'roles'                => ['seller'],
                'config_key'           => 'annual_operating_expenses_detail',
                'label'                => 'What are the main annual operating expenses?',
                'sample_question'      => 'What are the annual operating expenses for this property?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.value_add_opportunities' => [
                'roles'                => ['seller'],
                'config_key'           => 'value_add_opportunities',
                'label'                => 'Are there value-add opportunities with this property?',
                'sample_question'      => 'Are there any value-add opportunities for this property?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            // ----------------------------------------------------------------
            // Seller Addon: Business Opportunity
            // ----------------------------------------------------------------
            'faq_answers.annual_business_revenue' => [
                'roles'                => ['seller'],
                'config_key'           => 'annual_business_revenue',
                'label'                => 'What is the current annual gross revenue?',
                'sample_question'      => 'What is the annual revenue for this business?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.annual_net_profit' => [
                'roles'                => ['seller'],
                'config_key'           => 'annual_net_profit',
                'label'                => 'What is the approximate annual net profit or owner\'s discretionary earnings?',
                'sample_question'      => 'What is the net profit for this business?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.business_reason_for_selling' => [
                'roles'                => ['seller'],
                'config_key'           => 'business_reason_for_selling',
                'label'                => 'Why is the business being sold?',
                'sample_question'      => 'Why is the business owner selling?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.business_employee_count' => [
                'roles'                => ['seller'],
                'config_key'           => 'business_employee_count',
                'label'                => 'How many employees does the business have, and will they stay on?',
                'sample_question'      => 'How many employees does this business have?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.seller_training_transition' => [
                'roles'                => ['seller'],
                'config_key'           => 'seller_training_transition',
                'label'                => 'How much training or transition support will the seller provide?',
                'sample_question'      => 'Will the seller provide training after the sale?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.business_lease_status' => [
                'roles'                => ['seller'],
                'config_key'           => 'business_lease_status',
                'label'                => 'What is the status of the business location lease?',
                'sample_question'      => 'What is the lease status for the business location?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.inventory_equipment_included' => [
                'roles'                => ['seller'],
                'config_key'           => 'inventory_equipment_included',
                'label'                => 'What inventory and equipment are included in the sale price?',
                'sample_question'      => 'What is included in the business sale price?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            // ----------------------------------------------------------------
            // Seller Addon: Vacant Land
            // ----------------------------------------------------------------
            'faq_answers.land_utilities_availability' => [
                'roles'                => ['seller'],
                'config_key'           => 'land_utilities_availability',
                'label'                => 'What utilities are available or accessible on the land?',
                'sample_question'      => 'What utilities are available on this land?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.land_zoning_permitted_uses' => [
                'roles'                => ['seller'],
                'config_key'           => 'land_zoning_permitted_uses',
                'label'                => 'What is the current zoning designation and what uses are permitted?',
                'sample_question'      => 'What is the zoning for this land?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.land_access_and_road' => [
                'roles'                => ['seller'],
                'config_key'           => 'land_access_and_road',
                'label'                => 'What road access does the parcel have?',
                'sample_question'      => 'What road access does this land have?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.land_soil_and_topography' => [
                'roles'                => ['seller'],
                'config_key'           => 'land_soil_and_topography',
                'label'                => 'Are there any known soil, topography, flood zone, or wetland considerations?',
                'sample_question'      => 'What are the soil and topography conditions for this land?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.land_survey_available' => [
                'roles'                => ['seller'],
                'config_key'           => 'land_survey_available',
                'label'                => 'Is a current survey available, and has the land been cleared or improved?',
                'sample_question'      => 'Is a survey available for this land?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.land_development_restrictions' => [
                'roles'                => ['seller'],
                'config_key'           => 'land_development_restrictions',
                'label'                => 'Are there any deed restrictions, easements, or development restrictions?',
                'sample_question'      => 'Are there any development restrictions on this land?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
        ];
    }

    // =========================================================================
    // LANDLORD — Addon FAQ Keys (pinned)
    //
    // Commercial lease addon keys. All entries are pinned: keyword phrases added
    // to FAQ_KEY_KEYWORD_MAP, deriveFieldLabel, and classifier listing_facts.
    // =========================================================================

    private static function landlordAddonRegistry(): array
    {
        $note = 'Commercial addon key — commercial lease listing type.';

        return [
            'faq_answers.commercial_cam_charges' => [
                'roles'                => ['landlord'],
                'config_key'           => 'commercial_cam_charges',
                'label'                => 'What are the CAM charges, and what do they cover?',
                'sample_question'      => 'What are the CAM charges for this space?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.commercial_lease_structure_type' => [
                'roles'                => ['landlord'],
                'config_key'           => 'commercial_lease_structure_type',
                'label'                => 'Is the lease structured as gross, net, modified gross, or triple net (NNN)?',
                'sample_question'      => 'What type of lease structure is this commercial space?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.commercial_tenant_improvement_allowance' => [
                'roles'                => ['landlord'],
                'config_key'           => 'commercial_tenant_improvement_allowance',
                'label'                => 'Is a tenant improvement allowance available, and how much?',
                'sample_question'      => 'Is there a tenant improvement allowance for this space?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.commercial_buildout_flexibility' => [
                'roles'                => ['landlord'],
                'config_key'           => 'commercial_buildout_flexibility',
                'label'                => 'How flexible is the landlord on buildout modifications?',
                'sample_question'      => 'Can the tenant modify or build out this commercial space?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.commercial_signage_rights' => [
                'roles'                => ['landlord'],
                'config_key'           => 'commercial_signage_rights',
                'label'                => 'Are building-exterior signage rights included?',
                'sample_question'      => 'Are exterior signage rights included with this lease?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.commercial_loading_dock_freight_elevator' => [
                'roles'                => ['landlord'],
                'config_key'           => 'commercial_loading_dock_freight_elevator',
                'label'                => 'Are there loading dock or freight elevator facilities available?',
                'sample_question'      => 'Does this commercial space have a loading dock?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.commercial_electrical_capacity' => [
                'roles'                => ['landlord'],
                'config_key'           => 'commercial_electrical_capacity',
                'label'                => 'What is the electrical capacity of the space?',
                'sample_question'      => 'What is the electrical capacity of this commercial space?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.commercial_parking_ratio' => [
                'roles'                => ['landlord'],
                'config_key'           => 'commercial_parking_ratio',
                'label'                => 'What is the parking ratio, and are spaces reserved or shared?',
                'sample_question'      => 'What is the parking ratio for this commercial space?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.commercial_exclusivity_rights' => [
                'roles'                => ['landlord'],
                'config_key'           => 'commercial_exclusivity_rights',
                'label'                => 'Are exclusivity rights available to prevent a competing business in the building?',
                'sample_question'      => 'Are exclusivity rights available for this commercial space?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.commercial_expansion_option_rofr' => [
                'roles'                => ['landlord'],
                'config_key'           => 'commercial_expansion_option_rofr',
                'label'                => 'Is there an option to expand or right of first refusal on adjacent space?',
                'sample_question'      => 'Is there an expansion option or ROFR for this commercial space?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.commercial_landlord_maintenance_responsibilities' => [
                'roles'                => ['landlord'],
                'config_key'           => 'commercial_landlord_maintenance_responsibilities',
                'label'                => 'What does the landlord remain responsible for maintaining?',
                'sample_question'      => 'What maintenance is the landlord responsible for in this commercial space?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.commercial_building_access_hours' => [
                'roles'                => ['landlord'],
                'config_key'           => 'commercial_building_access_hours',
                'label'                => 'What are the access hours for the building or suite?',
                'sample_question'      => 'What are the building access hours for this commercial space?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
        ];
    }

    // =========================================================================
    // BUYER — Base FAQ Keys (match_criteria)
    //
    // Buyer FAQ answers are match criteria entered by the buyer, not factual
    // listing data answered by the seller. They route via 'buyer_tenant_match',
    // not 'listing_facts'. Intentionally NOT in FAQ_KEY_KEYWORD_MAP.
    // =========================================================================

    private static function buyerBaseRegistry(): array
    {
        $note = 'Buyer match criteria — routes via buyer_tenant_match intent, not listing_facts.';

        return [
            'faq_answers.buyer_motivation' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_motivation',
                'label'                => 'What\'s driving the buyer\'s decision to buy right now?',
                'sample_question'      => 'What is driving your decision to buy right now?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_lifestyle_goals' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_lifestyle_goals',
                'label'                => 'How does the buyer envision using this property?',
                'sample_question'      => 'How do you envision using this property?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_deal_breakers' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_deal_breakers',
                'label'                => 'What are the buyer\'s absolute deal-breakers?',
                'sample_question'      => 'What are your absolute deal-breakers?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_renovation_tolerance' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_renovation_tolerance',
                'label'                => 'Would the buyer consider a property that needs work?',
                'sample_question'      => 'Would you consider a property that needs work?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_wfh_needs' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_wfh_needs',
                'label'                => 'Does the buyer work from home, and what are their home office needs?',
                'sample_question'      => 'Do you work from home? What is your ideal home office setup?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_outdoor_space' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_outdoor_space',
                'label'                => 'How important is outdoor space to the buyer?',
                'sample_question'      => 'How important is outdoor space, and what would you ideally have?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_long_term_goals' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_long_term_goals',
                'label'                => 'Is this a forever home, starter home, or investment?',
                'sample_question'      => 'Is this a forever home, starter home, or investment?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_biggest_concern' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_biggest_concern',
                'label'                => 'What is the buyer\'s biggest concern about this purchase?',
                'sample_question'      => 'What\'s your biggest concern or hesitation about this purchase?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_neighborhood_preferences' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_neighborhood_preferences',
                'label'                => 'What kind of neighborhood feel is the buyer looking for?',
                'sample_question'      => 'What kind of neighborhood feel are you looking for?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_school_district' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_school_district',
                'label'                => 'Is a specific school district a hard requirement?',
                'sample_question'      => 'Is a specific school district a hard requirement or preference?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_commute_requirements' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_commute_requirements',
                'label'                => 'Does the buyer have commute distance or transit requirements?',
                'sample_question'      => 'Do you have commute distance or public transit requirements?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_noise_tolerance' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_noise_tolerance',
                'label'                => 'How sensitive is the buyer to noise?',
                'sample_question'      => 'How sensitive are you to noise?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_area_familiarity' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_area_familiarity',
                'label'                => 'How familiar is the buyer with the neighborhoods being considered?',
                'sample_question'      => 'How familiar are you with the neighborhoods you\'re considering?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_prefers_off_market' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_prefers_off_market',
                'label'                => 'Is the buyer open to off-market or pocket listings?',
                'sample_question'      => 'Are you open to off-market or pocket listings?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_property_style' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_property_style',
                'label'                => 'Does the buyer have an architectural style preference?',
                'sample_question'      => 'Do you have a preference for architectural style or the age of the home?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_must_have_features' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_must_have_features',
                'label'                => 'What are the buyer\'s absolute must-have property features?',
                'sample_question'      => 'What are your absolute must-have property features?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_nice_to_have' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_nice_to_have',
                'label'                => 'What features are on the buyer\'s wish list but not deal-breakers?',
                'sample_question'      => 'What features are on your wish list but not deal-breakers?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_hoa_acceptable' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_hoa_acceptable',
                'label'                => 'Is the buyer comfortable with an HOA community?',
                'sample_question'      => 'Are you comfortable with an HOA community?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_accessibility' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_accessibility',
                'label'                => 'Does the buyer need any accessibility features?',
                'sample_question'      => 'Do you need any accessibility features?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_privacy_requirements' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_privacy_requirements',
                'label'                => 'Does the buyer have specific privacy needs?',
                'sample_question'      => 'Do you have specific privacy needs?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_view_preference' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_view_preference',
                'label'                => 'Is a specific view important to the buyer?',
                'sample_question'      => 'Is a specific view important to you?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_current_situation' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_current_situation',
                'label'                => 'What is the buyer\'s current living situation?',
                'sample_question'      => 'What\'s your current living situation?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_simultaneous_close' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_simultaneous_close',
                'label'                => 'Does the buyer need to sell a current property simultaneously?',
                'sample_question'      => 'Do you need to sell a current property and close simultaneously?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_leaseback' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_leaseback',
                'label'                => 'Would the buyer allow the seller to stay on a short leaseback?',
                'sample_question'      => 'Would you allow the seller to stay on a short leaseback after closing?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_relocation' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_relocation',
                'label'                => 'Is the buyer relocating from another area?',
                'sample_question'      => 'Are you relocating from another area?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_lost_deal' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_lost_deal',
                'label'                => 'Has the buyer made offers that didn\'t work out?',
                'sample_question'      => 'Have you made offers on other properties that didn\'t work out?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_seller_concessions' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_seller_concessions',
                'label'                => 'Would the buyer consider asking for seller concessions?',
                'sample_question'      => 'Would you consider asking for seller concessions toward closing costs?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.buyer_flexibility' => [
                'roles'                => ['buyer'],
                'config_key'           => 'buyer_flexibility',
                'label'                => 'How flexible is the buyer on location, timing, or property type?',
                'sample_question'      => 'How flexible are you on location, timing, or property type?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
        ];
    }

    // =========================================================================
    // BUYER — Addon FAQ Keys (match_criteria)
    // =========================================================================

    private static function buyerAddonRegistry(): array
    {
        $note = 'Buyer match criteria (addon) — routes via buyer_tenant_match intent, not listing_facts.';

        return [
            // Commercial / Income
            'faq_answers.com_property_use' => [
                'roles'                => ['buyer'],
                'config_key'           => 'com_property_use',
                'label'                => 'What is the buyer\'s intended use of the commercial property?',
                'sample_question'      => 'What is the intended use of the commercial property?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.com_investment_type' => [
                'roles'                => ['buyer'],
                'config_key'           => 'com_investment_type',
                'label'                => 'Is this an investment purchase or owner-occupied?',
                'sample_question'      => 'Is this an investment purchase or owner-occupied?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.com_cap_rate_target' => [
                'roles'                => ['buyer'],
                'config_key'           => 'com_cap_rate_target',
                'label'                => 'What cap rate is the buyer targeting?',
                'sample_question'      => 'What cap rate are you targeting for this investment?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.com_occupancy_rate' => [
                'roles'                => ['buyer'],
                'config_key'           => 'com_occupancy_rate',
                'label'                => 'What minimum occupancy rate is required at purchase?',
                'sample_question'      => 'What minimum occupancy rate do you require at purchase?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.com_lease_terms' => [
                'roles'                => ['buyer'],
                'config_key'           => 'com_lease_terms',
                'label'                => 'What lease structure is preferred?',
                'sample_question'      => 'What lease structure do you prefer for this investment?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.com_1031_exchange' => [
                'roles'                => ['buyer'],
                'config_key'           => 'com_1031_exchange',
                'label'                => 'Is this a 1031 exchange purchase?',
                'sample_question'      => 'Is this purchase part of a 1031 exchange?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.com_due_diligence_period' => [
                'roles'                => ['buyer'],
                'config_key'           => 'com_due_diligence_period',
                'label'                => 'What due diligence period does the buyer require?',
                'sample_question'      => 'What due diligence period do you require?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.com_environmental_concerns' => [
                'roles'                => ['buyer'],
                'config_key'           => 'com_environmental_concerns',
                'label'                => 'Does the buyer have environmental concerns?',
                'sample_question'      => 'Do you have any environmental concerns about this purchase?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            // Business Opportunity
            'faq_answers.biz_type_seeking' => [
                'roles'                => ['buyer'],
                'config_key'           => 'biz_type_seeking',
                'label'                => 'What type of business is the buyer seeking?',
                'sample_question'      => 'What type of business are you looking to acquire?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.biz_revenue_required' => [
                'roles'                => ['buyer'],
                'config_key'           => 'biz_revenue_required',
                'label'                => 'What minimum annual revenue does the buyer require?',
                'sample_question'      => 'What minimum annual revenue do you require?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.biz_profit_required' => [
                'roles'                => ['buyer'],
                'config_key'           => 'biz_profit_required',
                'label'                => 'What minimum net profit does the buyer require?',
                'sample_question'      => 'What minimum net profit do you require?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.biz_training_expected' => [
                'roles'                => ['buyer'],
                'config_key'           => 'biz_training_expected',
                'label'                => 'What training does the buyer expect from the seller?',
                'sample_question'      => 'How much training do you expect from the seller after the sale?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.biz_staff_included' => [
                'roles'                => ['buyer'],
                'config_key'           => 'biz_staff_included',
                'label'                => 'Is retaining staff important to the buyer?',
                'sample_question'      => 'Is it important that existing staff stay on after the sale?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.biz_non_compete' => [
                'roles'                => ['buyer'],
                'config_key'           => 'biz_non_compete',
                'label'                => 'Does the buyer require a non-compete from the seller?',
                'sample_question'      => 'Do you require a non-compete agreement from the seller?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.biz_sba_financing' => [
                'roles'                => ['buyer'],
                'config_key'           => 'biz_sba_financing',
                'label'                => 'Is the buyer seeking SBA financing?',
                'sample_question'      => 'Are you planning to use SBA financing?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            // Vacant Land
            'faq_answers.land_intended_use' => [
                'roles'                => ['buyer'],
                'config_key'           => 'land_intended_use',
                'label'                => 'What is the buyer\'s intended use for the land?',
                'sample_question'      => 'What is your intended use for this land?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.land_zoning_required' => [
                'roles'                => ['buyer'],
                'config_key'           => 'land_zoning_required',
                'label'                => 'What zoning does the buyer require?',
                'sample_question'      => 'What zoning do you require for this land?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.land_utilities_needed' => [
                'roles'                => ['buyer'],
                'config_key'           => 'land_utilities_needed',
                'label'                => 'What utility access does the buyer need?',
                'sample_question'      => 'What utilities do you need on this land?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.land_soil_testing' => [
                'roles'                => ['buyer'],
                'config_key'           => 'land_soil_testing',
                'label'                => 'Does the buyer require soil testing?',
                'sample_question'      => 'Do you require soil testing before purchase?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.land_build_timeline' => [
                'roles'                => ['buyer'],
                'config_key'           => 'land_build_timeline',
                'label'                => 'What is the buyer\'s intended build timeline?',
                'sample_question'      => 'What is your intended build timeline for this land?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.land_access_requirements' => [
                'roles'                => ['buyer'],
                'config_key'           => 'land_access_requirements',
                'label'                => 'What road access does the buyer require?',
                'sample_question'      => 'What road access do you require for this land?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
            'faq_answers.land_topography' => [
                'roles'                => ['buyer'],
                'config_key'           => 'land_topography',
                'label'                => 'Does the buyer have topography requirements?',
                'sample_question'      => 'Do you have any topography requirements for this land?',
                'keyword_route_status' => 'match_criteria',
                'governance_note'      => $note,
            ],
        ];
    }

    // =========================================================================
    // TENANT — FAQ Keys (pinned)
    //
    // Tenant FAQ uses sequential keys (faq_q1–faq_q27). All entries are pinned:
    // natural-language keyword phrases added to FAQ_KEY_KEYWORD_MAP,
    // deriveFieldLabel, and AskAiQuestionClassifierService listing_facts.
    //
    // faq_q1–faq_q20: Residential tenant criteria
    // faq_q21–faq_q27: Commercial tenant criteria
    // =========================================================================

    private static function tenantRegistry(): array
    {
        $note = 'Tenant sequential FAQ key — pinned via natural-language keyword phrases.';

        return [
            'faq_answers.faq_q1' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q1',
                'label'                => 'Do you work from home? What does your ideal home setup look like?',
                'sample_question'      => 'Do you work from home?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.faq_q2' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q2',
                'label'                => 'What matters most to you in day-to-day living?',
                'sample_question'      => 'What matters most to you in day-to-day living?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.faq_q3' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q3',
                'label'                => 'How would you describe your ideal neighborhood vibe?',
                'sample_question'      => 'How would you describe your ideal neighborhood?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.faq_q4' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q4',
                'label'                => 'Are you sensitive to noise from neighbors, traffic, or nearby businesses?',
                'sample_question'      => 'How sensitive are you to noise?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.faq_q5' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q5',
                'label'                => 'Which amenity matters most to you — laundry, parking, outdoor space, storage?',
                'sample_question'      => 'Which amenity matters most to you in a rental?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.faq_q6' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q6',
                'label'                => 'How important is outdoor space to you?',
                'sample_question'      => 'How important is outdoor space to you?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.faq_q7' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q7',
                'label'                => 'If you have pets, what are their breed(s), size, and space needs?',
                'sample_question'      => 'Do you have pets? What are their needs?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.faq_q8' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q8',
                'label'                => 'Are you willing to pay a pet deposit or monthly pet rent if required?',
                'sample_question'      => 'Are you willing to pay a pet deposit or pet rent?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.faq_q9' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q9',
                'label'                => 'Are you flexible on lease length if a great property came along?',
                'sample_question'      => 'Are you flexible on lease length?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.faq_q10' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q10',
                'label'                => 'Would you consider a furnished unit?',
                'sample_question'      => 'Would you consider a furnished rental?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.faq_q11' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q11',
                'label'                => 'How firm is your move-in timeline?',
                'sample_question'      => 'How firm is your move-in timeline?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.faq_q12' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q12',
                'label'                => 'Is there any chance you\'d need to break the lease early?',
                'sample_question'      => 'Could you need to break the lease early?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.faq_q13' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q13',
                'label'                => 'Would you consider a longer lease term in exchange for a rent reduction?',
                'sample_question'      => 'Would you consider a longer lease for a rent reduction?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.faq_q14' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q14',
                'label'                => 'What\'s driving your rental search right now?',
                'sample_question'      => 'What\'s driving your rental search right now?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.faq_q15' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q15',
                'label'                => 'How long was your most recent tenancy, and why are you moving?',
                'sample_question'      => 'How long was your most recent tenancy?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.faq_q16' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q16',
                'label'                => 'Are you looking for a short-term solution or a long-term home?',
                'sample_question'      => 'Are you looking for short-term or long-term housing?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.faq_q17' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q17',
                'label'                => 'Do you have a landlord or employer reference available?',
                'sample_question'      => 'Do you have a landlord or employer reference available?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.faq_q18' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q18',
                'label'                => 'What is the source of your income?',
                'sample_question'      => 'What is the source of your income?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.faq_q19' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q19',
                'label'                => 'How do you prefer to communicate with a landlord?',
                'sample_question'      => 'How do you prefer to communicate with a landlord?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.faq_q20' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q20',
                'label'                => 'What\'s your biggest concern or hesitation in this rental search?',
                'sample_question'      => 'What\'s your biggest concern in this rental search?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            // Tenant commercial add-on (faq_q21–faq_q27)
            'faq_answers.faq_q21' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q21',
                'label'                => 'What type of business will be operating from this space?',
                'sample_question'      => 'What type of business will you operate from this space?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.faq_q22' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q22',
                'label'                => 'Do you expect customer or client foot traffic at this location?',
                'sample_question'      => 'Do you expect customer foot traffic at this location?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.faq_q23' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q23',
                'label'                => 'Do you have any special equipment or power requirements?',
                'sample_question'      => 'Do you have special equipment or power requirements?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.faq_q24' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q24',
                'label'                => 'Do you require exterior building signage?',
                'sample_question'      => 'Do you require exterior signage?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.faq_q25' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q25',
                'label'                => 'Will you need to modify or build out the space?',
                'sample_question'      => 'Will you need to build out or modify this commercial space?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.faq_q26' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q26',
                'label'                => 'What are your expected hours of operation?',
                'sample_question'      => 'What are your expected hours of operation?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
            'faq_answers.faq_q27' => [
                'roles'                => ['tenant'],
                'config_key'           => 'faq_q27',
                'label'                => 'Are you flexible on commercial lease term length and structure?',
                'sample_question'      => 'Are you flexible on commercial lease term length?',
                'keyword_route_status' => 'pinned',
                'governance_note'      => $note,
            ],
        ];
    }

    // =========================================================================
    // Utility methods
    // =========================================================================

    /**
     * Return only registry entries with keyword_route_status === 'pinned'.
     * These are the entries verified by the coverage harness for full routing coverage.
     *
     * @return array
     */
    public static function pinnedRegistry(): array
    {
        return array_filter(
            static::registry(),
            fn (array $entry) => ($entry['keyword_route_status'] ?? '') === 'pinned'
        );
    }

    /**
     * Return canonical faq_answers paths for all pinned entries only.
     * Used by the coverage harness to assert FAQ_KEY_KEYWORD_MAP completeness.
     *
     * @return string[]
     */
    public static function pinnedPaths(): array
    {
        return array_keys(static::pinnedRegistry());
    }

    /**
     * Return only the registry entries with a given keyword_route_status.
     *
     * @return array
     */
    public static function byRouteStatus(string $status): array
    {
        return array_filter(
            static::registry(),
            fn (array $entry) => ($entry['keyword_route_status'] ?? '') === $status
        );
    }

    /**
     * Return only the registry entries for the given role(s).
     *
     * @param  string|string[] $roles
     * @return array
     */
    public static function forRoles($roles): array
    {
        $roles = (array) $roles;
        return array_filter(
            static::registry(),
            fn (array $entry) => count(array_intersect($entry['roles'], $roles)) > 0
        );
    }

    /**
     * Return all canonical faq_answers paths (the registry keys), all statuses.
     *
     * @return string[]
     */
    public static function allCanonicalPaths(): array
    {
        return array_keys(static::registry());
    }

    /**
     * Return all raw config_key values across all roles (deduplicated).
     *
     * @return string[]
     */
    public static function allConfigKeys(): array
    {
        $keys = array_map(fn (array $e) => $e['config_key'], static::registry());
        return array_values(array_unique($keys));
    }

    /**
     * Return a flat list of sample questions keyed by canonical path.
     *
     * @return array<string, string>
     */
    public static function sampleQuestions(): array
    {
        return array_map(fn (array $e) => $e['sample_question'], static::registry());
    }

    /**
     * Return an array of all roles that appear in the registry.
     *
     * @return string[]
     */
    public static function allRoles(): array
    {
        $roles = [];
        foreach (static::registry() as $entry) {
            foreach ($entry['roles'] as $role) {
                $roles[$role] = true;
            }
        }
        return array_keys($roles);
    }

    // =========================================================================
    // ROUTER ENTRY PROVIDER
    //
    // Returns structured registry metadata for the OpenAI natural-language
    // field router. Combines FAQ entries (registry()) and listing-model entries
    // (listingFieldRegistry()), filtered to the current listing type role.
    //
    // Excluded from the router:
    //   match_criteria — buyer/tenant match-criteria fields answered by the
    //                    listing party; not surfaced as listing_facts paths.
    //   umbrella_only  — high-level umbrella paths with no specific field key.
    //
    // Each returned entry includes path, label, description, and up to two
    // natural-language sample questions so OpenAI can perform semantic matching
    // without hardcoding any keyword phrases in the router prompt.
    // =========================================================================

    /**
     * Return role-filtered router entries combining FAQ and listing-model registry data.
     *
     * Used by AskAiIntentNormalizerService to build the enriched router prompt.
     * Only entries whose roles array includes $role are returned. Entries with
     * keyword_route_status of 'match_criteria' or 'umbrella_only' are excluded.
     *
     * @param  string  $role  Listing-type role: 'seller', 'buyer', 'landlord', or 'tenant'.
     * @return array<string, array{path: string, label: string, description: string, sample_questions: string[]}>
     */
    public static function routerEntries(string $role): array
    {
        $excluded = ['match_criteria', 'umbrella_only'];
        $entries  = [];

        $all = array_merge(static::registry(), static::listingFieldRegistry());

        foreach ($all as $path => $entry) {
            if (in_array($entry['keyword_route_status'] ?? '', $excluded, true)) {
                continue;
            }

            if (!in_array($role, $entry['roles'] ?? [], true)) {
                continue;
            }

            $sq1 = $entry['sample_question']   ?? '';
            $sq2 = $entry['sample_question_2'] ?? '';

            $entries[$path] = [
                'path'             => $path,
                'label'            => $entry['label'] ?? '',
                'description'      => $entry['label'] ?? '',
                'sample_questions' => array_values(array_filter([$sq1, $sq2])),
            ];
        }

        return $entries;
    }

    // =========================================================================
    // FAQ SECOND-QUESTION ENRICHMENT
    //
    // Applied by registry() to add field_type='faq' and sample_question_2 to
    // every FAQ entry, satisfying the requirement for ≥2 natural-language
    // questions per field.
    // =========================================================================

    private static function withSecondQuestions(array $entries): array
    {
        $q2 = static::secondSampleQuestionsMap();
        foreach ($entries as $path => &$entry) {
            $entry['field_type']       = 'faq';
            $entry['sample_question_2'] = $q2[$path] ?? '';
        }
        unset($entry);
        return $entries;
    }

    private static function secondSampleQuestionsMap(): array
    {
        return [
            // ---- Seller base ----
            'faq_answers.roof_age_and_condition'          => 'Has the roof been replaced recently?',
            'faq_answers.hvac_system_age'                 => 'When was the HVAC system last serviced?',
            'faq_answers.water_heater_age_type'           => 'How old is the water heater?',
            'faq_answers.recent_renovations_list'         => 'When was the last major update to this home?',
            'faq_answers.permits_for_renovations'         => 'Did the additions have proper permits?',
            'faq_answers.known_defects_issues'            => 'What deferred repairs should a buyer budget for?',
            'faq_answers.foundation_type_and_issues'      => 'Have there been any foundation problems?',
            'faq_answers.pest_termite_history'            => 'Has there been a recent pest inspection?',
            'faq_answers.flood_damage_history'            => 'Is this property in a flood zone?',
            'faq_answers.mold_issues_history'             => 'Has there been any water intrusion or mold?',
            'faq_answers.average_utility_costs'           => 'What are the typical electric and water bills?',
            'faq_answers.internet_utility_providers'      => 'Is fiber internet available at this address?',
            'faq_answers.seller_concessions_offered'      => 'Is the seller willing to cover closing costs?',
            'faq_answers.neighborhood_character'          => 'Is this a quiet, established neighborhood?',
            'faq_answers.traffic_or_noise_concerns'       => 'Is there highway or airport noise nearby?',
            'faq_answers.planned_nearby_development'      => 'Are there commercial projects being built nearby?',
            'faq_answers.commute_options_access'          => 'How far is the nearest highway on-ramp?',
            'faq_answers.natural_light_orientation'       => 'Which direction does the backyard face?',
            'faq_answers.nearby_amenities_description'    => 'How far is the nearest grocery store?',
            'faq_answers.neighborhood_restrictions'       => 'Can I park an RV or boat on the property?',
            'faq_answers.closing_timeline_flexibility'    => 'Could the seller close in 30 days?',
            'faq_answers.seller_leaseback_option'         => 'Would the seller consider a rent-back period?',
            'faq_answers.items_excluded_from_sale'        => 'Do the appliances convey with the property?',
            'faq_answers.furniture_negotiability'         => 'Would the seller sell furniture separately?',
            'faq_answers.as_is_condition'                 => 'Will the seller make any repairs before closing?',
            'faq_answers.environmental_concerns'          => 'Is there lead paint or asbestos in this home?',
            'faq_answers.unique_selling_points'           => 'What does this home offer that photos don\'t show?',
            'faq_answers.seller_favorite_features'        => 'What do you love most about living in this home?',
            'faq_answers.seller_motivation_for_selling'   => 'How motivated is the seller to close quickly?',
            'faq_answers.move_in_ready_status'            => 'Are there repairs a buyer should plan for?',
            'faq_answers.parking_arrangements'            => 'Is there space for an RV or multiple vehicles?',
            'faq_answers.storage_space_available'         => 'Is there a garage, attic, or storage unit?',
            'faq_answers.hoa_community_highlights'        => 'Are there pools or gyms in the community?',
            // ---- Landlord base ----
            'faq_answers.maintenance_request_response_time' => 'How do I report a maintenance issue?',
            'faq_answers.emergency_maintenance_available'   => 'Who do I call for an after-hours repair?',
            'faq_answers.heating_cooling_system'            => 'Is the HVAC tenant-controlled or central?',
            'faq_answers.laundry_situation'                 => 'Where are the laundry facilities located?',
            'faq_answers.storage_area_included'             => 'Is there a storage locker included with the unit?',
            'faq_answers.internet_providers'                => 'Is fiber internet available in this building?',
            'faq_answers.security_features'                 => 'Is there a keypad entry or doorman?',
            'faq_answers.planned_renovations'               => 'Will there be construction during my lease?',
            'faq_answers.noise_levels'                      => 'Is there noise from the street or other units?',
            'faq_answers.nearby_amenities'                  => 'How far is the nearest bus stop or train?',
            'faq_answers.guest_parking'                     => 'Where can guests park when visiting?',
            'faq_answers.proximity_to_public_transit'       => 'Is this walkable or does it require a car?',
            'faq_answers.furnished_or_unfurnished'          => 'Would the landlord consider a furnished option?',
            'faq_answers.lease_renewal_process'             => 'How far in advance must I notify about renewal?',
            'faq_answers.notice_to_vacate_required'         => 'How much notice do I need to give to move out?',
            'faq_answers.preferred_tenant_qualities'        => 'What does an ideal tenant look like to you?',
            'faq_answers.subletting_allowed'                => 'Can I sublet a room or the entire unit?',
            'faq_answers.short_term_rentals_allowed'        => 'Can I use this unit as an Airbnb listing?',
            'faq_answers.ev_charging_available'             => 'Can I install an EV charger if one isn\'t provided?',
            'faq_answers.bicycle_storage_available'         => 'Is there a locked area to store bicycles?',
            'faq_answers.what_makes_property_unique'        => 'What do tenants typically love most about this unit?',
            'faq_answers.pest_or_mold_history'              => 'Has this unit ever had moisture or mold problems?',
            'faq_answers.utilities_individually_metered'    => 'Are utility costs split between units?',
            'faq_answers.renters_insurance_required'        => 'Is proof of renters insurance required at move-in?',
            'faq_answers.lease_to_own_option'               => 'Is any lease-to-own arrangement possible?',
            'faq_answers.previous_tenant_feedback'          => 'How long did the last tenant stay here?',
            'faq_answers.smoking_policy'                    => 'Is vaping or smoking allowed outside the unit?',
            // ---- Seller addons ----
            'faq_answers.annual_net_operating_income'            => 'What is the current NOI for this property?',
            'faq_answers.current_cap_rate'                       => 'How is the cap rate calculated here?',
            'faq_answers.existing_tenant_lease_terms'            => 'When do the existing tenant leases expire?',
            'faq_answers.current_occupancy_rate'                 => 'Is the property currently fully occupied?',
            'faq_answers.annual_operating_expenses_detail'       => 'What are the major expense categories?',
            'faq_answers.value_add_opportunities'                => 'What could a new owner do to increase NOI?',
            'faq_answers.annual_business_revenue'                => 'What was last year\'s gross revenue?',
            'faq_answers.annual_net_profit'                      => 'What is the profit margin on this business?',
            'faq_answers.business_reason_for_selling'            => 'Is the owner retiring or just moving on?',
            'faq_answers.business_employee_count'                => 'Are employees included in the sale?',
            'faq_answers.seller_training_transition'             => 'How long would the seller train a new owner?',
            'faq_answers.business_lease_status'                  => 'Is the commercial lease assignable?',
            'faq_answers.inventory_equipment_included'           => 'What equipment conveys with the sale?',
            'faq_answers.land_utilities_availability'            => 'Is this land on the utility grid or off-grid?',
            'faq_answers.land_zoning_permitted_uses'             => 'What can this land legally be used for?',
            'faq_answers.land_access_and_road'                   => 'Is there direct road access or an easement?',
            'faq_answers.land_soil_and_topography'               => 'Is the land flat or sloped?',
            'faq_answers.land_survey_available'                  => 'Has the land been recently surveyed?',
            'faq_answers.land_development_restrictions'          => 'Are there deed restrictions on this land?',
            // ---- Landlord addons ----
            'faq_answers.commercial_cam_charges'                          => 'How are CAM charges calculated?',
            'faq_answers.commercial_lease_structure_type'                 => 'Is this a NNN, gross, or modified lease?',
            'faq_answers.commercial_tenant_improvement_allowance'         => 'How much is the TI allowance?',
            'faq_answers.commercial_buildout_flexibility'                 => 'Can the landlord assist with build-out?',
            'faq_answers.commercial_signage_rights'                       => 'Is there space for exterior building signage?',
            'faq_answers.commercial_loading_dock_freight_elevator'        => 'Can large deliveries be made to this space?',
            'faq_answers.commercial_electrical_capacity'                  => 'What is the amperage capacity here?',
            'faq_answers.commercial_parking_ratio'                        => 'How many spaces per 1,000 sqft?',
            'faq_answers.commercial_exclusivity_rights'                   => 'Can I negotiate an exclusivity clause?',
            'faq_answers.commercial_expansion_option_rofr'                => 'Can I get ROFR on adjacent space?',
            'faq_answers.commercial_landlord_maintenance_responsibilities' => 'Who handles HVAC maintenance?',
            'faq_answers.commercial_building_access_hours'                => 'Is there 24-hour keycard access?',
            // ---- Buyer base ----
            'faq_answers.buyer_motivation'               => 'What is the most important factor in your search?',
            'faq_answers.buyer_lifestyle_goals'          => 'Are you looking for proximity to schools or work?',
            'faq_answers.buyer_deal_breakers'            => 'What features would make you walk away?',
            'faq_answers.buyer_renovation_tolerance'     => 'Are you open to a fixer-upper?',
            'faq_answers.buyer_wfh_needs'                => 'Do you need a dedicated home office space?',
            'faq_answers.buyer_outdoor_space'            => 'How important is a yard or garden to you?',
            'faq_answers.buyer_long_term_goals'          => 'Are you planning to hold long-term or flip?',
            'faq_answers.buyer_biggest_concern'          => 'What is your biggest worry about this purchase?',
            'faq_answers.buyer_neighborhood_preferences' => 'Do you prefer urban, suburban, or rural?',
            'faq_answers.buyer_school_district'          => 'Do you need to be in a specific school district?',
            'faq_answers.buyer_commute_requirements'     => 'What is your maximum acceptable commute time?',
            'faq_answers.buyer_noise_tolerance'          => 'Can you handle road noise or train tracks?',
            'faq_answers.buyer_area_familiarity'         => 'Have you ever lived in this area before?',
            'faq_answers.buyer_prefers_off_market'       => 'Are you open to off-market opportunities?',
            'faq_answers.buyer_property_style'           => 'Do you prefer single story or two-story?',
            'faq_answers.buyer_must_have_features'       => 'Are there specific must-have rooms or features?',
            'faq_answers.buyer_nice_to_have'             => 'What features would be a bonus but not required?',
            'faq_answers.buyer_hoa_acceptable'           => 'What is your maximum acceptable HOA fee?',
            'faq_answers.buyer_accessibility'            => 'Do you require ADA-compliant features?',
            'faq_answers.buyer_privacy_requirements'     => 'Do you need a private yard or large lot?',
            'faq_answers.buyer_view_preference'          => 'Do you prefer a water, golf, or city view?',
            'faq_answers.buyer_current_situation'        => 'Are you currently renting or selling a home?',
            'faq_answers.buyer_simultaneous_close'       => 'Do you need to sell a home to close simultaneously?',
            'faq_answers.buyer_leaseback'                => 'Are you open to the seller staying temporarily?',
            'faq_answers.buyer_relocation'               => 'Is this a corporate relocation purchase?',
            'faq_answers.buyer_lost_deal'                => 'Have you lost a home to a higher offer recently?',
            'faq_answers.buyer_seller_concessions'       => 'Are you counting on concessions for closing costs?',
            'faq_answers.buyer_flexibility'              => 'Can you adjust timeline if the right home appears?',
            // ---- Buyer addons ----
            'faq_answers.com_property_use'               => 'Will this be owner-occupied or leased out?',
            'faq_answers.com_investment_type'            => 'Is this a short-term or long-term hold?',
            'faq_answers.com_cap_rate_target'            => 'What yield do you expect from this investment?',
            'faq_answers.com_occupancy_rate'             => 'Can you tolerate some vacancy at purchase?',
            'faq_answers.com_lease_terms'                => 'Do you prefer triple net leases?',
            'faq_answers.com_1031_exchange'              => 'Are you on a 1031 exchange timeline?',
            'faq_answers.com_due_diligence_period'       => 'How much time do you need for due diligence?',
            'faq_answers.com_environmental_concerns'     => 'Has a Phase 1 environmental study been done?',
            'faq_answers.biz_type_seeking'               => 'Is a franchise or independent business preferred?',
            'faq_answers.biz_revenue_required'           => 'What is the minimum gross revenue you require?',
            'faq_answers.biz_profit_required'            => 'What EBITDA threshold are you targeting?',
            'faq_answers.biz_training_expected'          => 'Do you want full-time training or a quick handoff?',
            'faq_answers.biz_staff_included'             => 'Do you plan to retain current management?',
            'faq_answers.biz_non_compete'                => 'What non-compete radius and duration is required?',
            'faq_answers.biz_sba_financing'              => 'Have you been pre-approved for an SBA loan?',
            'faq_answers.land_intended_use'              => 'Will you build residential or commercial here?',
            'faq_answers.land_zoning_required'           => 'Do you need specific zoning for your project?',
            'faq_answers.land_utilities_needed'          => 'Do you need water and sewer on-site?',
            'faq_answers.land_soil_testing'              => 'Has any environmental testing been done here?',
            'faq_answers.land_build_timeline'            => 'Are you planning to build immediately or hold?',
            'faq_answers.land_access_requirements'       => 'Do you need paved road frontage?',
            'faq_answers.land_topography'                => 'Do you need flat land for a specific structure?',
            // ---- Tenant opaque keys ----
            'faq_answers.faq_q1'  => 'Do you need a home office or quiet work space?',
            'faq_answers.faq_q2'  => 'What is your daily routine at home?',
            'faq_answers.faq_q3'  => 'Do you prefer a walkable area or a quiet neighborhood?',
            'faq_answers.faq_q4'  => 'Would you rent near a busy street or intersection?',
            'faq_answers.faq_q5'  => 'Would you pay more for in-unit laundry or parking?',
            'faq_answers.faq_q6'  => 'Do you want a patio, balcony, or backyard?',
            'faq_answers.faq_q7'  => 'Do you have any pets that need outdoor access?',
            'faq_answers.faq_q8'  => 'Would a no-pet-deposit policy change your decision?',
            'faq_answers.faq_q9'  => 'Would you sign a 2-year lease for a better rate?',
            'faq_answers.faq_q10' => 'Does the unit need to include furniture?',
            'faq_answers.faq_q11' => 'Is your move-in date firm or flexible by a few weeks?',
            'faq_answers.faq_q12' => 'How would you handle a lease break if needed?',
            'faq_answers.faq_q13' => 'How many months would you commit to for a discount?',
            'faq_answers.faq_q14' => 'Is your search driven by a job change or life event?',
            'faq_answers.faq_q15' => 'Did you leave your last rental on good terms?',
            'faq_answers.faq_q16' => 'Are you looking to settle in this area long-term?',
            'faq_answers.faq_q17' => 'Can you provide a reference from a recent landlord?',
            'faq_answers.faq_q18' => 'Is your income from employment or self-employment?',
            'faq_answers.faq_q19' => 'Do you prefer text, email, or phone calls?',
            'faq_answers.faq_q20' => 'What matters most in your final rental decision?',
            'faq_answers.faq_q21' => 'How many employees will regularly use this space?',
            'faq_answers.faq_q22' => 'Will clients or customers visit this location?',
            'faq_answers.faq_q23' => 'Do you need three-phase power or high-voltage capacity?',
            'faq_answers.faq_q24' => 'Is building-facing signage a requirement?',
            'faq_answers.faq_q25' => 'Do you need to install partitions or custom flooring?',
            'faq_answers.faq_q26' => 'Will you operate during evenings or weekends?',
            'faq_answers.faq_q27' => 'Is a short-term commercial lease acceptable?',
        ];
    }

    // =========================================================================
    // LISTING MODEL FIELD REGISTRY
    //
    // Native listing model attributes extracted by AskAiContextBuilderService
    // and declared as approved paths in AskAiResponseContractService.
    // These use listing.* canonical paths and keyword_route_status='listing_native'.
    //
    // All fields verified present in both:
    //   - AskAiContextBuilderService (extraction)
    //   - AskAiResponseContractService::getListingFactsAllowedPaths()
    // =========================================================================

    /**
     * Returns the listing model field registry (~45 entries covering all approved
     * listing.* context paths that are user-facing).
     *
     * @return array<string, array{roles: string[], field_type: string, config_key: string, label: string, sample_question: string, sample_question_2: string, keyword_route_status: string}>
     */
    public static function listingFieldRegistry(): array
    {
        return [
            // ---- Tax ----
            'listing.annual_property_taxes' => [
                'roles'                => ['seller', 'landlord'],
                'field_type'           => 'listing_model',
                'config_key'           => 'annual_property_taxes',
                'label'                => 'Annual Property Taxes',
                'sample_question'      => 'What are the annual property taxes for this property?',
                'sample_question_2'    => 'How much are the property taxes per year?',
                'keyword_route_status' => 'listing_native',
            ],
            // ---- Price & Financial ----
            'listing.asking_price' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'asking_price',
                'label'                => 'Asking / Starting Price',
                'sample_question'      => 'What is the asking price for this property?',
                'sample_question_2'    => 'How much is this property listed for?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.max_price' => [
                'roles'                => ['buyer'],
                'field_type'           => 'listing_model',
                'config_key'           => 'max_price',
                'label'                => 'Buyer Maximum Price',
                'sample_question'      => 'What is the buyer\'s maximum budget?',
                'sample_question_2'    => 'What is the highest price this buyer will pay?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.rent_amount' => [
                'roles'                => ['landlord'],
                'field_type'           => 'listing_model',
                'config_key'           => 'rent_amount',
                'label'                => 'Monthly Rent',
                'sample_question'      => 'What is the monthly rent for this unit?',
                'sample_question_2'    => 'How much does this rental cost per month?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.max_rent' => [
                'roles'                => ['tenant'],
                'field_type'           => 'listing_model',
                'config_key'           => 'max_rent',
                'label'                => 'Tenant Maximum Rent Budget',
                'sample_question'      => 'What is the tenant\'s maximum monthly rent budget?',
                'sample_question_2'    => 'How much can this tenant afford per month?',
                'keyword_route_status' => 'listing_native',
            ],
            // ---- Property Specifications ----
            'listing.bedrooms' => [
                'roles'                => ['seller', 'buyer', 'landlord', 'tenant'],
                'field_type'           => 'listing_model',
                'config_key'           => 'bedrooms',
                'label'                => 'Number of Bedrooms',
                'sample_question'      => 'How many bedrooms does this property have?',
                'sample_question_2'    => 'What is the bedroom count?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.bathrooms' => [
                'roles'                => ['seller', 'buyer', 'landlord', 'tenant'],
                'field_type'           => 'listing_model',
                'config_key'           => 'bathrooms',
                'label'                => 'Number of Bathrooms',
                'sample_question'      => 'How many bathrooms are there?',
                'sample_question_2'    => 'What is the bath count for this property?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.square_feet' => [
                'roles'                => ['seller', 'buyer', 'landlord', 'tenant'],
                'field_type'           => 'listing_model',
                'config_key'           => 'square_feet',
                'label'                => 'Square Footage',
                'sample_question'      => 'How large is this property in square feet?',
                'sample_question_2'    => 'What is the total square footage?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.year_built' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'year_built',
                'label'                => 'Year Built',
                'sample_question'      => 'When was this property built?',
                'sample_question_2'    => 'How old is this home?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.description' => [
                'roles'                => ['seller', 'buyer', 'landlord', 'tenant'],
                'field_type'           => 'listing_model',
                'config_key'           => 'description',
                'label'                => 'Listing Description',
                'sample_question'      => 'Can you describe this property?',
                'sample_question_2'    => 'What is the general overview of this listing?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.condition_prop' => [
                'roles'                => ['landlord', 'tenant'],
                'field_type'           => 'listing_model',
                'config_key'           => 'condition_prop',
                'label'                => 'Property Condition',
                'sample_question'      => 'What condition is this rental in?',
                'sample_question_2'    => 'Is this property move-in ready?',
                'keyword_route_status' => 'listing_native',
            ],
            // ---- Location ----
            'listing.address' => [
                'roles'                => ['seller', 'buyer', 'landlord', 'tenant'],
                'field_type'           => 'listing_model',
                'config_key'           => 'address',
                'label'                => 'Property Address',
                'sample_question'      => 'What is the address of this property?',
                'sample_question_2'    => 'Where is this property located?',
                'keyword_route_status' => 'listing_native',
            ],
            // ---- Amenities & Features ----
            // ---- Property Type ----
            'listing.property_type' => [
                'roles'                => ['seller', 'buyer', 'landlord', 'tenant'],
                'field_type'           => 'listing_model',
                'config_key'           => 'property_type',
                'label'                => 'Property Type',
                'sample_question'      => 'What type of property is this?',
                'sample_question_2'    => 'What kind of property is listed here?',
                'keyword_route_status' => 'listing_native',
            ],
            // ---- View ----
            'listing.water_view' => [
                'roles'                => ['seller', 'buyer', 'landlord', 'tenant'],
                'field_type'           => 'listing_model',
                'config_key'           => 'water_view',
                'label'                => 'View / Water View',
                'sample_question'      => 'What is the view from this property?',
                'sample_question_2'    => 'Does this property have a water view or scenic view?',
                'keyword_route_status' => 'listing_native',
            ],
            // ---- Credit Score (Tenant) ----
            'listing.credit_score_range' => [
                'roles'                => ['tenant'],
                'field_type'           => 'listing_model',
                'config_key'           => 'credit_score_range',
                'label'                => 'Credit Score Range',
                'sample_question'      => 'What is the tenant\'s credit score range?',
                'sample_question_2'    => 'What credit score range does the tenant have?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.pool' => [
                'roles'                => ['seller', 'buyer'],
                'field_type'           => 'listing_model',
                'config_key'           => 'pool',
                'label'                => 'Pool',
                'sample_question'      => 'Does this property have a pool?',
                'sample_question_2'    => 'Is there a swimming pool on the property?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.carport' => [
                'roles'                => ['seller', 'buyer'],
                'field_type'           => 'listing_model',
                'config_key'           => 'carport',
                'label'                => 'Carport',
                'sample_question'      => 'Does this property have a carport?',
                'sample_question_2'    => 'Is there covered carport parking?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.garage' => [
                'roles'                => ['seller', 'buyer'],
                'field_type'           => 'listing_model',
                'config_key'           => 'garage',
                'label'                => 'Garage',
                'sample_question'      => 'Does this property have a garage?',
                'sample_question_2'    => 'Is there an attached or detached garage?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.appliances' => [
                'roles'                => ['landlord', 'tenant'],
                'field_type'           => 'listing_model',
                'config_key'           => 'appliances',
                'label'                => 'Appliances Included',
                'sample_question'      => 'Which appliances are included in this rental?',
                'sample_question_2'    => 'Does this unit come with a washer, dryer, or dishwasher?',
                'keyword_route_status' => 'listing_native',
            ],
            // ---- HOA & Community ----
            'listing.hoa_association' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'hoa_association',
                'label'                => 'HOA / Association',
                'sample_question'      => 'Is there an HOA for this property?',
                'sample_question_2'    => 'Does this property belong to an HOA or condo association?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.hoa_fee' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'hoa_fee',
                'label'                => 'HOA Fee Amount',
                'sample_question'      => 'What is the HOA fee for this property?',
                'sample_question_2'    => 'How much are the monthly HOA dues?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.hoa_acceptable' => [
                'roles'                => ['buyer'],
                'field_type'           => 'listing_model',
                'config_key'           => 'hoa_acceptable',
                'label'                => 'Buyer HOA Acceptability',
                'sample_question'      => 'Is the buyer open to HOA properties?',
                'sample_question_2'    => 'Would the buyer accept a property with an HOA?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.has_hoa' => [
                'roles'                => ['landlord'],
                'field_type'           => 'listing_model',
                'config_key'           => 'has_hoa',
                'label'                => 'Has HOA',
                'sample_question'      => 'Does this rental property have an HOA?',
                'sample_question_2'    => 'Is there an association governing this property?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.association_amenities' => [
                'roles'                => ['landlord'],
                'field_type'           => 'listing_model',
                'config_key'           => 'association_amenities',
                'label'                => 'Association Amenities',
                'sample_question'      => 'What amenities does the association provide?',
                'sample_question_2'    => 'Are there community pools or fitness facilities?',
                'keyword_route_status' => 'listing_native',
            ],
            // ---- Pet Policies ----
            'listing.pets_allowed' => [
                'roles'                => ['seller', 'buyer'],
                'field_type'           => 'listing_model',
                'config_key'           => 'pets_allowed',
                'label'                => 'Pets Allowed',
                'sample_question'      => 'Are pets allowed at this property?',
                'sample_question_2'    => 'Is this a pet-friendly listing?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.pet_policy' => [
                'roles'                => ['landlord'],
                'field_type'           => 'listing_model',
                'config_key'           => 'pet_policy',
                'label'                => 'Pet Policy',
                'sample_question'      => 'What is the pet policy for this rental?',
                'sample_question_2'    => 'Are pets allowed, and what breeds or sizes are permitted?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.pet_deposit_fee_rent' => [
                'roles'                => ['landlord'],
                'field_type'           => 'listing_model',
                'config_key'           => 'pet_deposit_fee_rent',
                'label'                => 'Pet Deposit / Fee / Rent',
                'sample_question'      => 'Is there a pet deposit or monthly pet rent?',
                'sample_question_2'    => 'How much is the pet fee or pet rent for this unit?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.pet_information' => [
                'roles'                => ['tenant'],
                'field_type'           => 'listing_model',
                'config_key'           => 'pet_information',
                'label'                => 'Tenant Pet Information',
                'sample_question'      => 'What pets does this tenant have?',
                'sample_question_2'    => 'Does the tenant need a pet-friendly unit?',
                'keyword_route_status' => 'listing_native',
            ],
            // ---- Lease & Rental Terms ----
            'listing.lease_length' => [
                'roles'                => ['landlord'],
                'field_type'           => 'listing_model',
                'config_key'           => 'lease_length',
                'label'                => 'Lease Length',
                'sample_question'      => 'What lease lengths are available?',
                'sample_question_2'    => 'Is a month-to-month lease an option?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.desired_lease_length' => [
                'roles'                => ['tenant'],
                'field_type'           => 'listing_model',
                'config_key'           => 'desired_lease_length',
                'label'                => 'Tenant Desired Lease Length',
                'sample_question'      => 'How long of a lease does this tenant want?',
                'sample_question_2'    => 'What is the tenant\'s preferred lease term?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.renewal_option' => [
                'roles'                => ['landlord'],
                'field_type'           => 'listing_model',
                'config_key'           => 'renewal_option',
                'label'                => 'Renewal Option',
                'sample_question'      => 'Is lease renewal an option after the initial term?',
                'sample_question_2'    => 'Can the lease be extended or renewed?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.rental_restrictions' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'rental_restrictions',
                'label'                => 'Rental Restrictions',
                'sample_question'      => 'Are there rental restrictions on this property?',
                'sample_question_2'    => 'Can this property be used as a rental investment?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.lease_terms' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'lease_terms',
                'label'                => 'Existing Lease Terms',
                'sample_question'      => 'What lease terms has the seller specified for this property?',
                'sample_question_2'    => 'Are there any inherited tenant leases on this property?',
                'keyword_route_status' => 'listing_native',
            ],
            // ---- Utilities & Services ----
            'listing.utilities' => [
                'roles'                => ['landlord', 'tenant'],
                'field_type'           => 'listing_model',
                'config_key'           => 'utilities',
                'label'                => 'Utilities Included',
                'sample_question'      => 'Which utilities are included in the rent?',
                'sample_question_2'    => 'Does the rent include water, trash, or electric?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.tenant_pays' => [
                'roles'                => ['seller', 'tenant'],
                'field_type'           => 'listing_model',
                'config_key'           => 'tenant_pays',
                'label'                => 'Tenant Pays (Utilities)',
                'sample_question'      => 'Which utilities does the tenant pay for?',
                'sample_question_2'    => 'What costs are the tenant\'s responsibility?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.smoking_policy' => [
                'roles'                => ['landlord'],
                'field_type'           => 'listing_model',
                'config_key'           => 'smoking_policy',
                'label'                => 'Smoking Policy',
                'sample_question'      => 'Is smoking allowed in this rental?',
                'sample_question_2'    => 'What is the smoking policy for this unit?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.subletting_policy' => [
                'roles'                => ['landlord'],
                'field_type'           => 'listing_model',
                'config_key'           => 'subletting_policy',
                'label'                => 'Subletting Policy',
                'sample_question'      => 'Is subletting allowed in this rental?',
                'sample_question_2'    => 'Can tenants sublet or Airbnb this unit?',
                'keyword_route_status' => 'listing_native',
            ],
            // ---- Parking & Availability ----
            'listing.parking_terms' => [
                'roles'                => ['landlord'],
                'field_type'           => 'listing_model',
                'config_key'           => 'parking_terms',
                'label'                => 'Parking Terms',
                'sample_question'      => 'What are the parking arrangements for tenants?',
                'sample_question_2'    => 'Is parking included in the rent or separate?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.available_date' => [
                'roles'                => ['landlord', 'tenant'],
                'field_type'           => 'listing_model',
                'config_key'           => 'available_date',
                'label'                => 'Available Date',
                'sample_question'      => 'When is this unit available for move-in?',
                'sample_question_2'    => 'What is the earliest available move-in date?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.closing_date' => [
                'roles'                => ['seller', 'buyer'],
                'field_type'           => 'listing_model',
                'config_key'           => 'closing_date',
                'label'                => 'Preferred Closing Date',
                'sample_question'      => 'What is the preferred closing date?',
                'sample_question_2'    => 'When does the seller want to close?',
                'keyword_route_status' => 'listing_native',
            ],
            // ---- Buyer Financials & Criteria ----
            'listing.loan_pre_approved' => [
                'roles'                => ['buyer'],
                'field_type'           => 'listing_model',
                'config_key'           => 'loan_pre_approved',
                'label'                => 'Loan Pre-Approval Status',
                'sample_question'      => 'Has this buyer been pre-approved for a loan?',
                'sample_question_2'    => 'Is the buyer\'s financing already in place?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.financing_type' => [
                'roles'                => ['buyer'],
                'field_type'           => 'listing_model',
                'config_key'           => 'financing_type',
                'label'                => 'Financing Type',
                'sample_question'      => 'What type of financing is the buyer using?',
                'sample_question_2'    => 'Is this a cash offer or conventional loan?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.inspection_period' => [
                'roles'                => ['buyer'],
                'field_type'           => 'listing_model',
                'config_key'           => 'inspection_period',
                'label'                => 'Inspection Period',
                'sample_question'      => 'How many days is the buyer\'s inspection period?',
                'sample_question_2'    => 'What inspection contingency does this buyer need?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.inspection_contingency_buyer' => [
                'roles'                => ['buyer'],
                'field_type'           => 'listing_model',
                'config_key'           => 'inspection_contingency_buyer',
                'label'                => 'Inspection Contingency',
                'sample_question'      => 'Does this buyer need an inspection contingency?',
                'sample_question_2'    => 'Is the offer contingent on a home inspection?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.appraisal_contingency_buyer' => [
                'roles'                => ['buyer'],
                'field_type'           => 'listing_model',
                'config_key'           => 'appraisal_contingency_buyer',
                'label'                => 'Appraisal Contingency',
                'sample_question'      => 'Is there an appraisal contingency for this buyer?',
                'sample_question_2'    => 'Does the buyer need the property to appraise at value?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.financing_contingency_buyer' => [
                'roles'                => ['buyer'],
                'field_type'           => 'listing_model',
                'config_key'           => 'financing_contingency_buyer',
                'label'                => 'Financing Contingency',
                'sample_question'      => 'Does this buyer have a financing contingency?',
                'sample_question_2'    => 'Is the offer contingent on the buyer securing a mortgage?',
                'keyword_route_status' => 'listing_native',
            ],
            // ---- Safety & Disclosure ----
            'listing.flood_zone_code' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'flood_zone_code',
                'label'                => 'Flood Zone Status',
                'sample_question'      => 'Is this property in a flood zone?',
                'sample_question_2'    => 'What is the flood zone designation for this property?',
                'keyword_route_status' => 'listing_native',
            ],
            // ---- Landlord Approval (P1-4) ------------------------------------
            'listing.landlord_approval_conditions' => [
                'roles'                => ['landlord'],
                'field_type'           => 'listing_model',
                'config_key'           => 'landlord_approval_conditions',
                'label'                => 'Landlord Approval Conditions',
                'sample_question'      => 'What are the approval conditions for this rental?',
                'sample_question_2'    => 'What credit or income requirements must tenants meet?',
                'keyword_route_status' => 'listing_native',
            ],
            // ---- Multifamily / Investment Fields (P3) -----------------------
            'listing.total_units' => [
                'roles'                => ['seller', 'landlord'],
                'field_type'           => 'listing_model',
                'config_key'           => 'total_units',
                'label'                => 'Total Units',
                'sample_question'      => 'How many units does this property have?',
                'sample_question_2'    => 'Is this a multi-unit property, and if so, how many units?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.unit_mix_summary' => [
                'roles'                => ['seller', 'landlord'],
                'field_type'           => 'listing_model',
                'config_key'           => 'unit_mix_summary',
                'label'                => 'Unit Mix Summary',
                'sample_question'      => 'What is the unit mix for this property?',
                'sample_question_2'    => 'How many 1-bedroom, 2-bedroom, and 3-bedroom units are there?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.total_buildings' => [
                'roles'                => ['seller', 'landlord'],
                'field_type'           => 'listing_model',
                'config_key'           => 'total_buildings',
                'label'                => 'Total Buildings',
                'sample_question'      => 'How many buildings are on this property?',
                'sample_question_2'    => 'Is this a single-building or multi-building investment?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.annual_cdd_fee' => [
                'roles'                => ['seller', 'landlord'],
                'field_type'           => 'listing_model',
                'config_key'           => 'annual_cdd_fee',
                'label'                => 'Annual CDD Fee',
                'sample_question'      => 'What is the annual CDD fee for this property?',
                'sample_question_2'    => 'How much is the community development district fee per year?',
                'keyword_route_status' => 'listing_native',
            ],
            // P0.2 — 'listing.annual_noi' is REMOVED. Its context alias (onto the seller's
            // desired minimum_annual_net_income) and its keyword route were removed in P0; this
            // entry was left behind, still claiming 'listing_native'. There is no actual-NOI
            // listing field, so there is nothing for a listing path to declare.
            'listing.gross_annual_income' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'gross_annual_income',
                'label'                => 'Gross Annual Income',
                'sample_question'      => 'What is the gross annual rental income for this property?',
                'sample_question_2'    => 'How much annual revenue does this investment property generate?',
                'keyword_route_status' => 'listing_native',
            ],
            // ---- Business Opportunity Fields (P0-1) -------------------------
            'listing.annual_revenue' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'annual_revenue',
                'label'                => 'Annual Revenue',
                'sample_question'      => 'What is the annual revenue of this business?',
                'sample_question_2'    => 'How much revenue did this business generate last year?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.employee_count' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'employee_count',
                'label'                => 'Employee Count',
                'sample_question'      => 'How many employees does this business have?',
                'sample_question_2'    => 'What is the current staff size of this business?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.year_established' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'year_established',
                'label'                => 'Year Established',
                'sample_question'      => 'When was this business established?',
                'sample_question_2'    => 'How long has this business been in operation?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.business_name' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'business_name',
                'label'                => 'Business Name',
                'sample_question'      => 'What is the name of this business?',
                'sample_question_2'    => 'What is the trading name of this business?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.business_location_leased' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'business_location_leased',
                'label'                => 'Business Location Leased',
                'sample_question'      => 'Is the business location leased or owned?',
                'sample_question_2'    => 'Does the business own its location or operate from a leased space?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.nda_required' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'nda_required',
                'label'                => 'NDA Required',
                'sample_question'      => 'Is an NDA required to view full details of this business?',
                'sample_question_2'    => 'Does this listing require a confidentiality agreement?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.financial_statements_available' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'financial_statements_available',
                'label'                => 'Financial Statements Available',
                'sample_question'      => 'Are financial statements available for this business?',
                'sample_question_2'    => 'Can I review the business financial records?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.reason_for_sale' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'reason_for_sale',
                'label'                => 'Reason for Sale',
                'sample_question'      => 'Why is this business being sold?',
                'sample_question_2'    => 'What is the seller\'s reason for selling this business?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.sale_includes' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'sale_includes',
                'label'                => 'What the Sale Includes',
                'sample_question'      => 'What is included in the sale of this business?',
                'sample_question_2'    => 'What assets and rights come with the purchase of this business?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.business_assets' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'business_assets',
                'label'                => 'Business Assets',
                'sample_question'      => 'What assets does this business have?',
                'sample_question_2'    => 'What physical assets are included with this business?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.business_lease_monthly_rent' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'business_lease_monthly_rent',
                'label'                => 'Business Location Monthly Rent',
                'sample_question'      => 'What is the monthly rent for the business location?',
                'sample_question_2'    => 'How much does the business pay in rent each month?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.ffe_value' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'ffe_value',
                'label'                => 'FF&E Value (Furniture, Fixtures & Equipment)',
                'sample_question'      => 'What is the value of the furniture, fixtures, and equipment?',
                'sample_question_2'    => 'What is the FF&E included in this business sale?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.gross_profit' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'gross_profit',
                'label'                => 'Gross Profit',
                'sample_question'      => 'What is the gross profit for this business?',
                'sample_question_2'    => 'What is this business\'s gross profit margin?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.sde_ebitda' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'sde_ebitda',
                'label'                => 'SDE / EBITDA',
                'sample_question'      => 'What are the seller\'s discretionary earnings (SDE) for this business?',
                'sample_question_2'    => 'What is the EBITDA for this business?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.inventory_value' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'inventory_value',
                'label'                => 'Inventory Value',
                'sample_question'      => 'What is the current value of the business inventory?',
                'sample_question_2'    => 'How much is the inventory worth for this business?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.licenses' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'licenses',
                'label'                => 'Business Licenses & Permits',
                'sample_question'      => 'What licenses are required to operate this business?',
                'sample_question_2'    => 'What permits and licenses does this business currently hold?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.business_lease_assignable' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'business_lease_assignable',
                'label'                => 'Business Lease Assignable',
                'sample_question'      => 'Is the business location lease assignable to a new owner?',
                'sample_question_2'    => 'Can the commercial lease be transferred to the buyer?',
                'keyword_route_status' => 'listing_native',
            ],

            // ---- Phase 2: Seller new fields ----
            'listing.waterfront_feet' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'waterfront_feet',
                'label'                => 'Waterfront Footage',
                'sample_question'      => 'How many feet of waterfront does this property have?',
                'sample_question_2'    => 'What is the waterfront linear footage for this property?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.home_warranty_offered' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'home_warranty_offered',
                'label'                => 'Home Warranty Offered',
                'sample_question'      => 'Is a home warranty included with this property?',
                'sample_question_2'    => 'Does this home come with a home warranty?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.association_approval_required' => [
                'roles'                => ['seller'],
                'field_type'           => 'listing_model',
                'config_key'           => 'association_approval_required',
                'label'                => 'HOA Approval Required',
                'sample_question'      => 'Does the HOA need to approve the buyer for this property?',
                'sample_question_2'    => 'Is association board approval required to purchase this home?',
                'keyword_route_status' => 'listing_native',
            ],

            // ---- Phase 2: Landlord screening / policies ----
            'listing.min_credit_score' => [
                'roles'                => ['landlord'],
                'field_type'           => 'listing_model',
                'config_key'           => 'min_credit_score',
                'label'                => 'Minimum Credit Score',
                'sample_question'      => 'What is the minimum credit score required to rent this property?',
                'sample_question_2'    => 'What credit score does a tenant need to qualify for this rental?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.income_qualification_method' => [
                'roles'                => ['landlord'],
                'field_type'           => 'listing_model',
                'config_key'           => 'income_qualification_method',
                'label'                => 'Income Qualification Method',
                'sample_question'      => 'How is income verified for this rental?',
                'sample_question_2'    => 'What income qualification method does the landlord use?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.eviction_history_requirement' => [
                'roles'                => ['landlord'],
                'field_type'           => 'listing_model',
                'config_key'           => 'eviction_history_requirement',
                'label'                => 'Eviction History Policy',
                'sample_question'      => 'How does eviction history affect eligibility for this rental?',
                'sample_question_2'    => 'Will a prior eviction disqualify an applicant for this rental?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.security_deposit_required' => [
                'roles'                => ['landlord'],
                'field_type'           => 'listing_model',
                'config_key'           => 'security_deposit_required',
                'label'                => 'Security Deposit Required',
                'sample_question'      => 'Is a security deposit required for this rental?',
                'sample_question_2'    => 'Does this landlord require a security deposit?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.leasing_55_plus' => [
                'roles'                => ['landlord', 'buyer'],
                'field_type'           => 'listing_model',
                'config_key'           => 'leasing_55_plus',
                'label'                => '55+ Community',
                'sample_question'      => 'Is this property in a 55-and-over community?',
                'sample_question_2'    => 'Does this rental have a 55+ age restriction?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.guests_allowed' => [
                'roles'                => ['landlord'],
                'field_type'           => 'listing_model',
                'config_key'           => 'guests_allowed',
                'label'                => 'Guest Policy',
                'sample_question'      => 'Are overnight guests allowed at this rental?',
                'sample_question_2'    => 'What is the guest policy for tenants at this property?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.maintenance_by' => [
                'roles'                => ['landlord'],
                'field_type'           => 'listing_model',
                'config_key'           => 'maintenance_by',
                'label'                => 'Maintenance Responsibility',
                'sample_question'      => 'Who handles maintenance for this rental property?',
                'sample_question_2'    => 'Is the landlord or tenant responsible for maintenance?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.est_electric' => [
                'roles'                => ['landlord'],
                'field_type'           => 'listing_model',
                'config_key'           => 'est_electric',
                'label'                => 'Estimated Electric Bill',
                'sample_question'      => 'What is the estimated monthly electric bill for this rental?',
                'sample_question_2'    => 'How much does electricity typically cost for this unit?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.est_water_sewer_trash' => [
                'roles'                => ['landlord'],
                'field_type'           => 'listing_model',
                'config_key'           => 'est_water_sewer_trash',
                'label'                => 'Estimated Water/Sewer/Trash',
                'sample_question'      => 'What is the estimated water, sewer, and trash cost?',
                'sample_question_2'    => 'How much is the water bill for this rental?',
                'keyword_route_status' => 'listing_native',
            ],

            // ---- Phase 2: Buyer expansion fields ----
            'listing.purchase_purpose' => [
                'roles'                => ['buyer'],
                'field_type'           => 'listing_model',
                'config_key'           => 'purchase_purpose',
                'label'                => 'Purchase Purpose',
                'sample_question'      => 'Is this buyer purchasing for investment or personal use?',
                'sample_question_2'    => 'What is the buyer\'s intended purpose for this property?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.commute_destination_zip' => [
                'roles'                => ['buyer', 'tenant'],
                'field_type'           => 'listing_model',
                'config_key'           => 'commute_destination_zip',
                'label'                => 'Commute Destination',
                'sample_question'      => 'Where does this buyer commute to?',
                'sample_question_2'    => 'What zip code does this tenant commute to for work?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.max_commute_minutes' => [
                'roles'                => ['buyer', 'tenant'],
                'field_type'           => 'listing_model',
                'config_key'           => 'max_commute_minutes',
                'label'                => 'Max Commute Time',
                'sample_question'      => 'What is the maximum commute time this buyer will accept?',
                'sample_question_2'    => 'How far is this tenant willing to commute?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.flood_zone_tolerance' => [
                'roles'                => ['buyer'],
                'field_type'           => 'listing_model',
                'config_key'           => 'flood_zone_tolerance',
                'label'                => 'Flood Zone Tolerance',
                'sample_question'      => 'Is this buyer open to flood zone properties?',
                'sample_question_2'    => 'Will this buyer accept a property in a flood zone?',
                'keyword_route_status' => 'listing_native',
            ],

            // ---- Phase 2: Tenant expansion fields ----
            'listing.move_in_date_earliest' => [
                'roles'                => ['tenant'],
                'field_type'           => 'listing_model',
                'config_key'           => 'move_in_date_earliest',
                'label'                => 'Earliest Move-In Date',
                'sample_question'      => 'What is the earliest date this tenant can move in?',
                'sample_question_2'    => 'When is this tenant first available to move in?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.move_in_date_latest' => [
                'roles'                => ['tenant'],
                'field_type'           => 'listing_model',
                'config_key'           => 'move_in_date_latest',
                'label'                => 'Latest Move-In Date',
                'sample_question'      => 'What is the latest this tenant needs to move in by?',
                'sample_question_2'    => 'What is the tenant\'s move-in deadline?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.security_deposit_budget' => [
                'roles'                => ['tenant'],
                'field_type'           => 'listing_model',
                'config_key'           => 'security_deposit_budget',
                'label'                => 'Security Deposit Budget',
                'sample_question'      => 'How much can this tenant budget for a security deposit?',
                'sample_question_2'    => 'What deposit amount can this tenant afford?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.rental_purpose' => [
                'roles'                => ['tenant'],
                'field_type'           => 'listing_model',
                'config_key'           => 'rental_purpose',
                'label'                => 'Rental Purpose',
                'sample_question'      => 'What is this tenant\'s intended use for the rental?',
                'sample_question_2'    => 'Is this tenant renting for personal or business use?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.prior_eviction' => [
                'roles'                => ['tenant'],
                'field_type'           => 'listing_model',
                'config_key'           => 'prior_eviction',
                'label'                => 'Prior Eviction',
                'sample_question'      => 'Does this tenant have a prior eviction on record?',
                'sample_question_2'    => 'Has this tenant ever been evicted before?',
                'keyword_route_status' => 'listing_native',
            ],
            'listing.accessibility_requirements' => [
                'roles'                => ['tenant'],
                'field_type'           => 'listing_model',
                'config_key'           => 'accessibility_requirements',
                'label'                => 'Accessibility Requirements',
                'sample_question'      => 'Does this tenant have accessibility needs?',
                'sample_question_2'    => 'What accessibility accommodations does this tenant require?',
                'keyword_route_status' => 'listing_native',
            ],
        ];
    }

    /**
     * Registry of all approved suggested-question chips for the Ask AI panel.
     *
     * Used exclusively by AskAiSuggestedQuestionsService::forListing() to generate
     * context-aware, role-scoped, auth-gated chip suggestions. This registry is the
     * single source of truth for chip content; the legacy POOLS constant in
     * AskAiSuggestedQuestionsService is deprecated and must not be used by forListing().
     *
     * Entry schema (each key is a unique field_id string):
     *  'field_id' => [
     *      'canonical_key'       — matching key in the listing field or FAQ registry (or null for static chips)
     *      'roles'               — listing roles this chip applies to
     *      'category'            — one of: Property | Financial | Match | Lifestyle | Marketing | Education
     *      'question_type'       — legacy 8-category type kept for backward-compat sort/label/icon mapping
     *      'label'               — short display label (≤ 80 chars; shown on the chip surface)
     *      'primary_question'    — full question text populated into the textarea on chip click
     *      'alternate_questions' — additional phrasings (reserved for future rotation; not surfaced yet)
     *      'source_path'         — dot-path resolved against context (listing.* or faq_answers.*); null for static
     *      'requires_data'       — true = chip is suppressed when source_path resolves to null/empty
     *      'public_allowed'      — false = chip is hidden for unauthenticated (guest) viewers
     *  ]
     *
     * @return array<string, array{canonical_key: string|null, roles: string[], category: string, question_type: string, label: string, primary_question: string, alternate_questions: string[], source_path: string|null, requires_data: bool, public_allowed: bool}>
     */
    public static function suggestedQuestionRegistry(): array
    {
        return [

            // ================================================================
            // SELLER — Listing Facts (data-aware, requires_data=true)
            // ================================================================

            'seller_address' => [
                'canonical_key'       => 'listing.address',
                'roles'               => ['seller'],
                'category'            => 'Property',
                'question_type'       => 'listing_facts',
                'label'               => "What's the address?",
                'primary_question'    => "What's the address of this property?",
                'alternate_questions' => ["Where is this property located?"],
                'source_path'         => 'listing.address',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            'seller_asking_price' => [
                'canonical_key'       => 'listing.asking_price',
                'roles'               => ['seller'],
                'category'            => 'Financial',
                'question_type'       => 'listing_facts',
                'label'               => 'What is the asking price?',
                'primary_question'    => 'What is the asking price for this property?',
                'alternate_questions' => ['How much is this property listed for?'],
                'source_path'         => 'listing.asking_price',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            'seller_bedrooms' => [
                'canonical_key'       => 'listing.bedrooms',
                'roles'               => ['seller'],
                'category'            => 'Property',
                'question_type'       => 'listing_facts',
                'label'               => 'How many bedrooms?',
                'primary_question'    => 'How many bedrooms does this property have?',
                'alternate_questions' => ['What is the bedroom count for this listing?'],
                'source_path'         => 'listing.bedrooms',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            'seller_rental_restrictions' => [
                'canonical_key'       => 'listing.rental_restrictions',
                'roles'               => ['seller'],
                'category'            => 'Lifestyle',
                'question_type'       => 'listing_facts',
                'label'               => 'Are there rental restrictions?',
                'primary_question'    => 'Are there any rental restrictions on this property?',
                'alternate_questions' => ['Can this property be used as a short-term rental?'],
                'source_path'         => 'listing.rental_restrictions',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            'seller_lease_terms' => [
                'canonical_key'       => 'listing.lease_terms',
                'roles'               => ['seller'],
                'category'            => 'Lifestyle',
                'question_type'       => 'listing_facts',
                'label'               => 'What are the lease terms?',
                'primary_question'    => 'What lease terms has the seller specified for this property?',
                'alternate_questions' => ['What are the seller\'s leasing requirements?'],
                'source_path'         => 'listing.lease_terms',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            'seller_roof_age' => [
                'canonical_key'       => 'faq_answers.roof_age_and_condition',
                'roles'               => ['seller'],
                'category'            => 'Property',
                'question_type'       => 'listing_facts',
                'label'               => 'How old is the roof?',
                'primary_question'    => 'How old is the roof and what condition is it in?',
                'alternate_questions' => ['When was the roof replaced?'],
                'source_path'         => 'faq_answers.roof_age_and_condition',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            'seller_avg_utility_costs' => [
                'canonical_key'       => 'faq_answers.average_utility_costs',
                'roles'               => ['seller'],
                'category'            => 'Financial',
                'question_type'       => 'listing_facts',
                'label'               => 'What are the average utility costs?',
                'primary_question'    => 'What are the average monthly utility costs for this property?',
                'alternate_questions' => ['What is the average electric bill for this home?'],
                'source_path'         => 'faq_answers.average_utility_costs',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            'seller_hvac' => [
                'canonical_key'       => 'faq_answers.heating_cooling_system',
                'roles'               => ['seller'],
                'category'            => 'Property',
                'question_type'       => 'listing_facts',
                'label'               => "What's the heating/cooling system?",
                'primary_question'    => "What type of heating and cooling system does this property have?",
                'alternate_questions' => ['Is there central air conditioning?'],
                'source_path'         => 'faq_answers.heating_cooling_system',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            // ----------------------------------------------------------------
            // SELLER — Static chips (requires_data=false)
            // ----------------------------------------------------------------

            'seller_key_features' => [
                'canonical_key'       => null,
                'roles'               => ['seller'],
                'category'            => 'Property',
                'question_type'       => 'property_standout',
                'label'               => 'What are the key features of this property?',
                'primary_question'    => 'What are the key features of this property?',
                'alternate_questions' => [],
                'source_path'         => null,
                'requires_data'       => false,
                'public_allowed'      => true,
            ],

            'seller_compare_similar' => [
                'canonical_key'       => null,
                'roles'               => ['seller'],
                'category'            => 'Property',
                'question_type'       => 'property_standout',
                'label'               => 'How does this home compare to similar listings on the platform?',
                'primary_question'    => 'How does this home compare to similar listings on the platform?',
                'alternate_questions' => [],
                'source_path'         => null,
                'requires_data'       => false,
                'public_allowed'      => true,
            ],

            'seller_sale_terms' => [
                'canonical_key'       => null,
                'roles'               => ['seller'],
                'category'            => 'Property',
                'question_type'       => 'property_standout',
                'label'               => 'What sale terms has the seller specified?',
                'primary_question'    => 'What sale terms has the seller specified?',
                'alternate_questions' => [],
                'source_path'         => null,
                'requires_data'       => false,
                'public_allowed'      => true,
            ],

            'seller_suited_buyer' => [
                'canonical_key'       => null,
                'roles'               => ['seller'],
                'category'            => 'Match',
                'question_type'       => 'suited_audience',
                'label'               => 'What type of buyer might find this property a practical fit?',
                'primary_question'    => 'What type of buyer might find this property a practical fit?',
                'alternate_questions' => [],
                'source_path'         => null,
                'requires_data'       => false,
                'public_allowed'      => true,
            ],

            'seller_missing_info' => [
                'canonical_key'       => null,
                'roles'               => ['seller'],
                'category'            => 'Property',
                'question_type'       => 'missing_data',
                'label'               => 'What information is missing from this listing?',
                'primary_question'    => 'What information is missing from this listing?',
                'alternate_questions' => [],
                'source_path'         => null,
                'requires_data'       => false,
                'public_allowed'      => false,
            ],

            'seller_marketing' => [
                'canonical_key'       => null,
                'roles'               => ['seller'],
                'category'            => 'Marketing',
                'question_type'       => 'marketing_angles',
                'label'               => 'What marketing angles could work for this property?',
                'primary_question'    => 'What marketing angles could work for this property?',
                'alternate_questions' => [],
                'source_path'         => null,
                'requires_data'       => false,
                'public_allowed'      => false,
            ],

            'seller_education' => [
                'canonical_key'       => null,
                'roles'               => ['seller'],
                'category'            => 'Education',
                'question_type'       => 'educational',
                'label'               => 'How does the seller agent auction process work?',
                'primary_question'    => 'How does the seller agent auction process work?',
                'alternate_questions' => [],
                'source_path'         => null,
                'requires_data'       => false,
                'public_allowed'      => true,
            ],

            // ================================================================
            // BUYER — Listing Facts (data-aware, requires_data=true)
            // ================================================================

            'buyer_max_price' => [
                'canonical_key'       => 'listing.max_price',
                'roles'               => ['buyer'],
                'category'            => 'Financial',
                'question_type'       => 'listing_facts',
                'label'               => "What's the max budget?",
                'primary_question'    => "What is the maximum budget stated in this buyer listing?",
                'alternate_questions' => ['What is the highest price this buyer will pay?'],
                'source_path'         => 'listing.max_price',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            'buyer_financing_type' => [
                'canonical_key'       => 'listing.financing_type',
                'roles'               => ['buyer'],
                'category'            => 'Financial',
                'question_type'       => 'listing_facts',
                'label'               => 'What financing is accepted?',
                'primary_question'    => 'What type of financing has this buyer indicated they will use?',
                'alternate_questions' => ['Is this a cash offer or a financed purchase?'],
                'source_path'         => 'listing.financing_type',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            'buyer_bedrooms' => [
                'canonical_key'       => 'listing.bedrooms',
                'roles'               => ['buyer'],
                'category'            => 'Property',
                'question_type'       => 'listing_facts',
                'label'               => 'How many bedrooms?',
                'primary_question'    => 'How many bedrooms is this buyer looking for?',
                'alternate_questions' => ['What bedroom count is this buyer targeting?'],
                'source_path'         => 'listing.bedrooms',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            // ----------------------------------------------------------------
            // BUYER — Static chips (requires_data=false)
            // ----------------------------------------------------------------

            'buyer_strongest_criteria' => [
                'canonical_key'       => null,
                'roles'               => ['buyer'],
                'category'            => 'Match',
                'question_type'       => 'buyer_tenant_match',
                'label'               => "What are the strongest criteria I've stated in this listing?",
                'primary_question'    => "What are the strongest criteria I've stated in this listing?",
                'alternate_questions' => [],
                'source_path'         => null,
                'requires_data'       => false,
                'public_allowed'      => false,
            ],

            'buyer_how_complete' => [
                'canonical_key'       => null,
                'roles'               => ['buyer'],
                'category'            => 'Property',
                'question_type'       => 'missing_data',
                'label'               => 'How complete is my buyer criteria listing?',
                'primary_question'    => 'How complete is my buyer criteria listing?',
                'alternate_questions' => [],
                'source_path'         => null,
                'requires_data'       => false,
                'public_allowed'      => false,
            ],

            'buyer_what_to_add' => [
                'canonical_key'       => null,
                'roles'               => ['buyer'],
                'category'            => 'Property',
                'question_type'       => 'missing_data',
                'label'               => "What should I add to help agents better understand what I'm looking for?",
                'primary_question'    => "What should I add to help agents better understand what I'm looking for?",
                'alternate_questions' => [],
                'source_path'         => null,
                'requires_data'       => false,
                'public_allowed'      => false,
            ],

            'buyer_education_auction' => [
                'canonical_key'       => null,
                'roles'               => ['buyer'],
                'category'            => 'Education',
                'question_type'       => 'educational',
                'label'               => 'How does the buyer agent auction process work?',
                'primary_question'    => 'How does the buyer agent auction process work?',
                'alternate_questions' => [],
                'source_path'         => null,
                'requires_data'       => false,
                'public_allowed'      => true,
            ],

            'buyer_education_factors' => [
                'canonical_key'       => null,
                'roles'               => ['buyer'],
                'category'            => 'Education',
                'question_type'       => 'educational',
                'label'               => 'What factors do agents consider most important in a buyer criteria listing?',
                'primary_question'    => 'What factors do agents consider most important in a buyer criteria listing?',
                'alternate_questions' => [],
                'source_path'         => null,
                'requires_data'       => false,
                'public_allowed'      => true,
            ],

            // ================================================================
            // LANDLORD — Listing Facts (data-aware, requires_data=true)
            // ================================================================

            'landlord_rent_amount' => [
                'canonical_key'       => 'listing.rent_amount',
                'roles'               => ['landlord'],
                'category'            => 'Financial',
                'question_type'       => 'listing_facts',
                'label'               => 'What is the asking rent?',
                'primary_question'    => 'What is the asking rent for this property?',
                'alternate_questions' => ['How much does this rental cost per month?'],
                'source_path'         => 'listing.rent_amount',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            'landlord_bedrooms' => [
                'canonical_key'       => 'listing.bedrooms',
                'roles'               => ['landlord'],
                'category'            => 'Property',
                'question_type'       => 'listing_facts',
                'label'               => 'How many bedrooms?',
                'primary_question'    => 'How many bedrooms does this rental property have?',
                'alternate_questions' => ['What is the bedroom count for this rental?'],
                'source_path'         => 'listing.bedrooms',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            'landlord_pet_policy' => [
                'canonical_key'       => 'listing.pet_policy',
                'roles'               => ['landlord'],
                'category'            => 'Lifestyle',
                'question_type'       => 'listing_facts',
                'label'               => "What's the pet policy?",
                'primary_question'    => "What is the pet policy for this rental property?",
                'alternate_questions' => ['Are pets allowed, and what are the restrictions?'],
                'source_path'         => 'listing.pet_policy',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            'landlord_available_date' => [
                'canonical_key'       => 'listing.available_date',
                'roles'               => ['landlord'],
                'category'            => 'Lifestyle',
                'question_type'       => 'listing_facts',
                'label'               => 'When is it available?',
                'primary_question'    => 'When is this rental property available?',
                'alternate_questions' => ['What is the earliest available move-in date?'],
                'source_path'         => 'listing.available_date',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            'landlord_utilities' => [
                'canonical_key'       => 'listing.utilities',
                'roles'               => ['landlord'],
                'category'            => 'Financial',
                'question_type'       => 'listing_facts',
                'label'               => 'What utilities are included?',
                'primary_question'    => 'What utilities are included in the rent for this property?',
                'alternate_questions' => ['Does the rent cover water, electric, or other utilities?'],
                'source_path'         => 'listing.utilities',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            'landlord_smoking_policy' => [
                'canonical_key'       => 'listing.smoking_policy',
                'roles'               => ['landlord'],
                'category'            => 'Lifestyle',
                'question_type'       => 'listing_facts',
                'label'               => 'Is smoking allowed?',
                'primary_question'    => 'What is the smoking policy for this rental property?',
                'alternate_questions' => ['Is vaping or smoking permitted in or around the unit?'],
                'source_path'         => 'listing.smoking_policy',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            'landlord_laundry' => [
                'canonical_key'       => 'faq_answers.laundry_situation',
                'roles'               => ['landlord'],
                'category'            => 'Property',
                'question_type'       => 'listing_facts',
                'label'               => 'Is there in-unit laundry?',
                'primary_question'    => 'Is there in-unit laundry at this rental property?',
                'alternate_questions' => ['Is a washer and dryer included or available on-site?'],
                'source_path'         => 'faq_answers.laundry_situation',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            'landlord_hvac' => [
                'canonical_key'       => 'faq_answers.heating_cooling_system',
                'roles'               => ['landlord'],
                'category'            => 'Property',
                'question_type'       => 'listing_facts',
                'label'               => "What's the heating/cooling system?",
                'primary_question'    => "What type of heating and cooling system does this rental have?",
                'alternate_questions' => ['Is there central air conditioning in this unit?'],
                'source_path'         => 'faq_answers.heating_cooling_system',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            // ---- Phase 2: Landlord new chips ----

            'landlord_min_credit_score' => [
                'canonical_key'       => 'listing.min_credit_score',
                'roles'               => ['landlord'],
                'category'            => 'Financial',
                'question_type'       => 'listing_facts',
                'label'               => 'Minimum credit score?',
                'primary_question'    => 'What is the minimum credit score required to rent this property?',
                'alternate_questions' => ['What credit score does a tenant need to qualify here?'],
                'source_path'         => 'listing.min_credit_score',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            'landlord_est_electric' => [
                'canonical_key'       => 'listing.est_electric',
                'roles'               => ['landlord'],
                'category'            => 'Financial',
                'question_type'       => 'listing_facts',
                'label'               => 'Estimated electric bill?',
                'primary_question'    => 'What is the estimated monthly electric bill for this rental?',
                'alternate_questions' => ['How much does electricity typically cost for this unit?'],
                'source_path'         => 'listing.est_electric',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            'landlord_security_deposit' => [
                'canonical_key'       => 'listing.security_deposit_required',
                'roles'               => ['landlord'],
                'category'            => 'Financial',
                'question_type'       => 'listing_facts',
                'label'               => 'Security deposit required?',
                'primary_question'    => 'Is a security deposit required for this rental?',
                'alternate_questions' => ['How much is the security deposit for this property?'],
                'source_path'         => 'listing.security_deposit_required',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            'landlord_guests_policy' => [
                'canonical_key'       => 'listing.guests_allowed',
                'roles'               => ['landlord'],
                'category'            => 'Lifestyle',
                'question_type'       => 'listing_facts',
                'label'               => 'Are overnight guests allowed?',
                'primary_question'    => 'Are overnight guests allowed at this rental property?',
                'alternate_questions' => ['What is the guest policy for tenants?'],
                'source_path'         => 'listing.guests_allowed',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            'landlord_maintenance_by' => [
                'canonical_key'       => 'listing.maintenance_by',
                'roles'               => ['landlord'],
                'category'            => 'Lifestyle',
                'question_type'       => 'listing_facts',
                'label'               => 'Who handles maintenance?',
                'primary_question'    => 'Who is responsible for maintenance at this rental property?',
                'alternate_questions' => ['Does the landlord handle all repairs and maintenance?'],
                'source_path'         => 'listing.maintenance_by',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            // ----------------------------------------------------------------
            // LANDLORD — Static chips (requires_data=false)
            // ----------------------------------------------------------------

            'landlord_key_features' => [
                'canonical_key'       => null,
                'roles'               => ['landlord'],
                'category'            => 'Property',
                'question_type'       => 'property_standout',
                'label'               => 'What are the key features of this rental property?',
                'primary_question'    => 'What are the key features of this rental property?',
                'alternate_questions' => [],
                'source_path'         => null,
                'requires_data'       => false,
                'public_allowed'      => true,
            ],

            'landlord_compare_similar' => [
                'canonical_key'       => null,
                'roles'               => ['landlord'],
                'category'            => 'Property',
                'question_type'       => 'property_standout',
                'label'               => 'How does this rental compare to similar listings in the area?',
                'primary_question'    => 'How does this rental compare to similar listings in the area?',
                'alternate_questions' => [],
                'source_path'         => null,
                'requires_data'       => false,
                'public_allowed'      => true,
            ],

            'landlord_lease_terms' => [
                'canonical_key'       => null,
                'roles'               => ['landlord'],
                'category'            => 'Lifestyle',
                'question_type'       => 'property_standout',
                'label'               => 'What lease terms has the landlord specified?',
                'primary_question'    => 'What lease terms has the landlord specified?',
                'alternate_questions' => [],
                'source_path'         => null,
                'requires_data'       => false,
                'public_allowed'      => true,
            ],

            'landlord_suited_renter' => [
                'canonical_key'       => null,
                'roles'               => ['landlord'],
                'category'            => 'Match',
                'question_type'       => 'suited_audience',
                'label'               => 'What type of renter might find this rental a practical fit?',
                'primary_question'    => 'What type of renter might find this rental a practical fit?',
                'alternate_questions' => [],
                'source_path'         => null,
                'requires_data'       => false,
                'public_allowed'      => true,
            ],

            'landlord_missing_info' => [
                'canonical_key'       => null,
                'roles'               => ['landlord'],
                'category'            => 'Property',
                'question_type'       => 'missing_data',
                'label'               => 'What information is missing from this listing?',
                'primary_question'    => 'What information is missing from this listing?',
                'alternate_questions' => [],
                'source_path'         => null,
                'requires_data'       => false,
                'public_allowed'      => false,
            ],

            'landlord_marketing' => [
                'canonical_key'       => null,
                'roles'               => ['landlord'],
                'category'            => 'Marketing',
                'question_type'       => 'marketing_angles',
                'label'               => 'What marketing angles could work for this rental?',
                'primary_question'    => 'What marketing angles could work for this rental?',
                'alternate_questions' => [],
                'source_path'         => null,
                'requires_data'       => false,
                'public_allowed'      => false,
            ],

            'landlord_education' => [
                'canonical_key'       => null,
                'roles'               => ['landlord'],
                'category'            => 'Education',
                'question_type'       => 'educational',
                'label'               => 'How does the landlord auction process work on this platform?',
                'primary_question'    => 'How does the landlord auction process work on this platform?',
                'alternate_questions' => [],
                'source_path'         => null,
                'requires_data'       => false,
                'public_allowed'      => true,
            ],

            // ================================================================
            // TENANT — Listing Facts (data-aware, requires_data=true)
            // ================================================================

            'tenant_max_rent' => [
                'canonical_key'       => 'listing.max_rent',
                'roles'               => ['tenant'],
                'category'            => 'Financial',
                'question_type'       => 'listing_facts',
                'label'               => "What's the max rent?",
                'primary_question'    => "What is the maximum rent this tenant is willing to pay?",
                'alternate_questions' => ['What is the tenant\'s monthly rent budget?'],
                'source_path'         => 'listing.max_rent',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            'tenant_appliances' => [
                'canonical_key'       => 'listing.appliances',
                'roles'               => ['tenant'],
                'category'            => 'Lifestyle',
                'question_type'       => 'listing_facts',
                'label'               => 'What appliances are required?',
                'primary_question'    => 'What appliances has this tenant listed as required?',
                'alternate_questions' => ['Does this tenant need a washer, dryer, or dishwasher?'],
                'source_path'         => 'listing.appliances',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            'tenant_pet_info' => [
                'canonical_key'       => 'listing.pet_information',
                'roles'               => ['tenant'],
                'category'            => 'Lifestyle',
                'question_type'       => 'listing_facts',
                'label'               => 'Does the tenant have pets?',
                'primary_question'    => 'Does this tenant have pets that need to be accommodated?',
                'alternate_questions' => ['What type of pets does this tenant have?'],
                'source_path'         => 'listing.pet_information',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            'tenant_laundry' => [
                'canonical_key'       => 'faq_answers.laundry_situation',
                'roles'               => ['tenant'],
                'category'            => 'Property',
                'question_type'       => 'listing_facts',
                'label'               => 'Is there in-unit laundry?',
                'primary_question'    => 'Has this tenant specified a need for in-unit laundry?',
                'alternate_questions' => ['Is washer/dryer access a requirement for this tenant?'],
                'source_path'         => 'faq_answers.laundry_situation',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            // ---- Phase 2: Tenant new chips ----

            'tenant_move_in_date' => [
                'canonical_key'       => 'listing.move_in_date_earliest',
                'roles'               => ['tenant'],
                'category'            => 'Lifestyle',
                'question_type'       => 'listing_facts',
                'label'               => "When can the tenant move in?",
                'primary_question'    => "What is the earliest date this tenant is available to move in?",
                'alternate_questions' => ['What is the tenant\'s preferred move-in date?'],
                'source_path'         => 'listing.move_in_date_earliest',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            'tenant_security_deposit_budget' => [
                'canonical_key'       => 'listing.security_deposit_budget',
                'roles'               => ['tenant'],
                'category'            => 'Financial',
                'question_type'       => 'listing_facts',
                'label'               => 'Security deposit budget?',
                'primary_question'    => 'How much can this tenant afford for a security deposit?',
                'alternate_questions' => ['What is the tenant\'s security deposit budget?'],
                'source_path'         => 'listing.security_deposit_budget',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            'tenant_rental_purpose' => [
                'canonical_key'       => 'listing.rental_purpose',
                'roles'               => ['tenant'],
                'category'            => 'Lifestyle',
                'question_type'       => 'listing_facts',
                'label'               => 'Rental purpose?',
                'primary_question'    => 'What is this tenant\'s intended use for the rental?',
                'alternate_questions' => ['Is this tenant renting for personal or business use?'],
                'source_path'         => 'listing.rental_purpose',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            'tenant_commute_destination' => [
                'canonical_key'       => 'listing.commute_destination_zip',
                'roles'               => ['tenant'],
                'category'            => 'Lifestyle',
                'question_type'       => 'listing_facts',
                'label'               => 'Tenant commute destination?',
                'primary_question'    => 'Where does this tenant commute to for work?',
                'alternate_questions' => ['What zip code does this tenant commute to?'],
                'source_path'         => 'listing.commute_destination_zip',
                'requires_data'       => true,
                'public_allowed'      => true,
            ],

            // ----------------------------------------------------------------
            // TENANT — Static chips (requires_data=false)
            // ----------------------------------------------------------------

            'tenant_strongest_criteria' => [
                'canonical_key'       => null,
                'roles'               => ['tenant'],
                'category'            => 'Match',
                'question_type'       => 'buyer_tenant_match',
                'label'               => "What are the strongest lease requirements I've stated in this listing?",
                'primary_question'    => "What are the strongest lease requirements I've stated in this listing?",
                'alternate_questions' => [],
                'source_path'         => null,
                'requires_data'       => false,
                'public_allowed'      => false,
            ],

            'tenant_how_complete' => [
                'canonical_key'       => null,
                'roles'               => ['tenant'],
                'category'            => 'Property',
                'question_type'       => 'missing_data',
                'label'               => 'How complete is my tenant criteria listing?',
                'primary_question'    => 'How complete is my tenant criteria listing?',
                'alternate_questions' => [],
                'source_path'         => null,
                'requires_data'       => false,
                'public_allowed'      => false,
            ],

            'tenant_what_to_add' => [
                'canonical_key'       => null,
                'roles'               => ['tenant'],
                'category'            => 'Property',
                'question_type'       => 'missing_data',
                'label'               => 'What should I add to help landlords better understand what I need?',
                'primary_question'    => 'What should I add to help landlords better understand what I need?',
                'alternate_questions' => [],
                'source_path'         => null,
                'requires_data'       => false,
                'public_allowed'      => false,
            ],

            'tenant_education_auction' => [
                'canonical_key'       => null,
                'roles'               => ['tenant'],
                'category'            => 'Education',
                'question_type'       => 'educational',
                'label'               => 'How does the tenant agent auction process work?',
                'primary_question'    => 'How does the tenant agent auction process work?',
                'alternate_questions' => [],
                'source_path'         => null,
                'requires_data'       => false,
                'public_allowed'      => true,
            ],

            'tenant_education_factors' => [
                'canonical_key'       => null,
                'roles'               => ['tenant'],
                'category'            => 'Education',
                'question_type'       => 'educational',
                'label'               => 'What compatibility factors matter most when landlords evaluate tenant criteria?',
                'primary_question'    => 'What compatibility factors matter most when landlords evaluate tenant criteria?',
                'alternate_questions' => [],
                'source_path'         => null,
                'requires_data'       => false,
                'public_allowed'      => true,
            ],
        ];
    }

    /**
     * Approved catalog for the public "Questions About This Property" shown in the Ask AI
     * card on the Seller and Landlord listing pages.
     *
     * This is NOT another question engine. It adds no routing, no classification and no
     * visibility tier: each entry names one context path this registry and
     * AskAiContextBuilderService::CANONICAL_SOURCE_MAP already define, plus the identity
     * of a deterministic formatter. Whether an entry is actually shown is decided in one
     * place, AskAiPublicPropertyQuestionService::evaluate(), which requires every
     * source_path and supporting_path to classify 'public_allowed' in
     * SnapshotFactVisibility for the entry's role — the single exception being a
     * source_path an 'admitted_listing' entry names and that service's
     * PUBLIC_QUESTION_ADMISSIONS lists. Nothing here can make a fact public.
     *
     * Deliberately kept out of registry(), listingFieldRegistry() and
     * suggestedQuestionRegistry(): those feed the free-form Ask AI router, the knowledge
     * snapshot builders and the Ask AI chips.
     *
     * Only seller and landlord appear. Buyer and tenant listings are search criteria and
     * stay owner-only (SnapshotFactVisibility decision D2); the evaluator refuses those
     * roles even if an entry for them were added here.
     *
     * Entry schema (keyed by a stable question id):
     *   'role'             — exactly one of 'seller' | 'landlord'
     *   'question'         — the shopper-facing question text
     *   'source_kind'      — 'listing' (a public_allowed context key) or 'admitted_listing'
     *                        (a key the public question service explicitly admits)
     *   'source_path'      — REQUIRED source: the one listing.* context path the answer is
     *                        read from; its absence hides the question
     *   'supporting_paths' — OPTIONAL sources: further listing.* paths the formatter reads
     *                        (each must also be public_allowed, or the question is hidden)
     *   'other_companion'  — optional: ['selected_in' => meta key of the stored selections,
     *                        'meta_key' => meta key of the "Other" text, 'reject_figures' =>
     *                        bool]; the only meta values an answer may contain
     *   'other_companions' — optional, instead of other_companion, for a composite that reads
     *                        several such fields: [name => the same spec]
     *   'formatter'        — identity of a formatter in AskAiPublicPropertyQuestionService
     *   'guards'           — identities of hide-only ambiguity checks; never reveal anything
     *   'category'         — display grouping for later batches
     *   'order'            — display order within the role (ascending)
     *   'aliases'          — lower-case phrasings for the later typed-question matcher; they
     *                        can only ever point at an AVAILABLE question
     *
     *   'narrower_of'      — optional: the id of a richer composite entry of the same role.
     *                        When that composite is available for a listing it replaces this
     *                        entry, so the two near-identical questions never both show; when
     *                        it is not, this entry stands in as the complete narrower answer.
     *                        One level only — a composite itself declares no narrower_of.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function publicPropertyQuestionRegistry(): array
    {
        return [
            // ---- Seller ----
            'seller_asking_price' => [
                'role'             => 'seller',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What is the asking price?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.asking_price',
                'supporting_paths' => [],
                'formatter'        => 'asking_price',
                // The page hides Desired Sale Price on a Bidding Period listing, and shows
                // two separately labelled prices when the MLS list price differs.
                'guards'           => ['not_bidding_period', 'mls_price_not_divergent'],
                'category'         => 'price',
                'order'            => 10,
                'aliases'          => ['asking price', 'list price', 'price', 'how much', 'how much is it', 'what is the price'],
            ],
            'seller_bedrooms' => [
                'role'             => 'seller',
                'property_types'   => [PT::RESIDENTIAL],
                'question'         => 'How many bedrooms are there?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.bedrooms',
                'supporting_paths' => [],
                'formatter'        => 'bedroom_count',
                // Without the meta row the context falls back to the native bedroom_id
                // column, which is a foreign key, not a count.
                'guards'           => ['meta_present:bedrooms'],
                'category'         => 'size',
                'order'            => 20,
                'aliases'          => ['bedrooms', 'beds', 'how many bedrooms'],
            ],
            'seller_bathrooms' => [
                'role'             => 'seller',
                'property_types'   => [PT::RESIDENTIAL, PT::BUSINESS, PT::COMMERCIAL],
                'question'         => 'How many bathrooms are there?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.bathrooms',
                'supporting_paths' => [],
                'formatter'        => 'bathroom_count',
                'guards'           => ['meta_present:bathrooms'],
                'category'         => 'size',
                'order'            => 30,
                'aliases'          => ['bathrooms', 'baths', 'how many bathrooms'],
            ],
            'seller_heated_square_feet' => [
                'role'             => 'seller',
                'property_types'   => [PT::RESIDENTIAL, PT::BUSINESS, PT::COMMERCIAL],
                'question'         => 'What is the heated square footage?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.square_feet',
                'supporting_paths' => [],
                'formatter'        => 'heated_square_feet',
                'guards'           => [],
                'category'         => 'size',
                'order'            => 40,
                'aliases'          => ['square footage', 'square feet', 'sq ft', 'how big'],
            ],
            'seller_year_built' => [
                'role'             => 'seller',
                'property_types'   => [PT::RESIDENTIAL, PT::INCOME, PT::COMMERCIAL, PT::BUSINESS],
                'question'         => 'What year was the property built?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.year_built',
                'supporting_paths' => [],
                'formatter'        => 'year_built',
                'guards'           => [],
                'category'         => 'construction',
                'order'            => 50,
                'aliases'          => ['year built', 'when was it built', 'how old is the property'],
            ],
            'seller_property_taxes' => [
                'role'             => 'seller',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What are the property taxes?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.annual_property_taxes',
                'supporting_paths' => ['listing.tax_year'],
                'formatter'        => 'annual_property_taxes',
                'guards'           => [],
                'category'         => 'costs',
                'order'            => 60,
                'aliases'          => ['taxes', 'property tax', 'property taxes', 'tax amount', 'how much are taxes', 'how much are the taxes', 'what are the taxes'],
            ],
            'seller_hoa_fee' => [
                'role'             => 'seller',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What are the HOA fees?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.hoa_fee',
                'supporting_paths' => ['listing.hoa_association', 'listing.hoa_payment_schedule'],
                'other_companion'  => ['selected_in' => 'association_fee_frequency', 'meta_key' => 'association_fee_frequency_other'],
                'formatter'        => 'hoa_fee',
                'guards'           => [],
                'category'         => 'hoa',
                'order'            => 70,
                'aliases'          => ['hoa', 'hoa fee', 'hoa fees', 'association fee', 'association fees', 'hoa dues', 'how much is the hoa', 'how much are the hoa fees'],
                'narrower_of'      => 'seller_hoa_fee_coverage',
            ],
            'seller_hoa_fee_coverage' => [
                'role'             => 'seller',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'HOA fees & what do they cover?',
                'source_kind'      => 'listing',
                // Composite (Batch 2c): the fee statement of seller_hoa_fee plus what the fee
                // covers, from the structured fee-includes selections. Needs a valid fee AND at
                // least one covered item; otherwise seller_hoa_fee answers instead.
                'source_path'      => 'listing.hoa_fee',
                'supporting_paths' => ['listing.hoa_association', 'listing.hoa_payment_schedule', 'listing.association_fee_includes'],
                'other_companions' => [
                    'frequency' => ['selected_in' => 'association_fee_frequency', 'meta_key' => 'association_fee_frequency_other'],
                    'includes'  => ['selected_in' => 'association_fee_includes', 'meta_key' => 'association_fee_includes_other'],
                ],
                'formatter'        => 'hoa_fee_coverage',
                'guards'           => [],
                'category'         => 'hoa',
                'order'            => 69,
                'aliases'          => ['hoa', 'hoa fee', 'hoa fees', 'association fee', 'association fees', 'hoa dues', 'how much is the hoa', 'how much are the hoa fees', 'what does the hoa cover', 'what does the hoa fee include', 'hoa includes'],
            ],
            'seller_cdd_fee' => [
                'role'             => 'seller',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'Is there a CDD fee?',
                'source_kind'      => 'listing',
                // has_cdd (Yes / No / Unknown) with the annual amount when one is stated.
                'source_path'      => 'listing.has_cdd',
                'supporting_paths' => ['listing.annual_cdd_fee'],
                'formatter'        => 'cdd_fee',
                'guards'           => [],
                'category'         => 'costs',
                'order'            => 75,
                'aliases'          => ['cdd', 'cdd fee', 'community development district'],
            ],
            'seller_total_acreage' => [
                'role'             => 'seller',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What is the lot size / acreage?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.total_acreage',
                'supporting_paths' => [],
                'formatter'        => 'acreage_band',
                // The page prints a legacy min_acreage in preference to total_acreage.
                'guards'           => ['acreage_not_overridden'],
                'category'         => 'size',
                'order'            => 80,
                'aliases'          => ['lot size', 'acreage', 'acres', 'how big is the lot', 'what is the lot size'],
            ],
            'seller_appliances' => [
                'role'             => 'seller',
                'property_types'   => [PT::RESIDENTIAL, PT::INCOME, PT::COMMERCIAL, PT::BUSINESS],
                'question'         => 'What appliances are included?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.appliances',
                'supporting_paths' => [],
                'formatter'        => 'appliance_list',
                'guards'           => [],
                'category'         => 'features',
                'order'            => 90,
                'aliases'          => ['appliances', 'what appliances'],
            ],
            'seller_utilities' => [
                'role'             => 'seller',
                'property_types'   => PT::ALL_TYPES,
                // The seller field records utilities AVAILABLE or CONNECTED at the property
                // ("Electricity Connected"), not utilities included in a price, so the
                // question says what the field holds.
                'question'         => 'What utilities are listed for this property?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.utilities',
                'supporting_paths' => [],
                'formatter'        => 'utility_list',
                'guards'           => [],
                'category'         => 'features',
                'order'            => 100,
                'aliases'          => ['utilities'],
            ],

            // ---- Seller — Batch 2b ----
            'seller_pets_allowed' => [
                'role'             => 'seller',
                'property_types'   => [PT::RESIDENTIAL, PT::INCOME],
                'question'         => 'Are pets allowed?',
                'source_kind'      => 'listing',
                // context pets_allowed ← meta 'pets' (Yes / No). Batch 2c adds the listing's own
                // numeric pet-policy limits (number_of_pets, weight_of_pets) to a "Yes" answer.
                // Pet types, breeds and restriction text are not read.
                'source_path'      => 'listing.pets_allowed',
                'supporting_paths' => ['listing.number_of_pets_allowed', 'listing.max_pet_weight'],
                // `pet_restrictions` is DELIBERATELY NOT COVERED. The decision predates this
                // batch (see the note above) and is a Fair Housing one: restriction prose is
                // where breed limits live, and a breed limit is a recognised proxy. Publishing
                // it as an Ask AI answer would re-open a surface the product closed. Recorded
                // as an explicit exclusion in the disposition contract, not left silent.
                'covers'           => ['listing.pets_allowed', 'listing.number_of_pets_allowed',
                                       'listing.max_pet_weight'],
                'formatter'        => 'pets_allowed',
                'guards'           => [],
                'category'         => 'policies',
                'order'            => 110,
                'aliases'          => ['pets', 'pets allowed', 'dogs', 'cats', 'pet policy'],
            ],
            'seller_pool' => [
                'role'             => 'seller',
                'property_types'   => [PT::RESIDENTIAL, PT::INCOME],
                'question'         => 'Does the property have a pool?',
                'source_kind'      => 'listing',
                // context pool ← meta 'pool_needed' (Yes / No). pool_type is not read: its
                // stored object decodes to a bare "1".
                'source_path'      => 'listing.pool',
                'supporting_paths' => ['listing.pool_type'],
                'covers'           => ['listing.pool', 'listing.pool_type'],
                'formatter'        => 'pool_composite',
                'guards'           => [],
                'category'         => 'features',
                'order'            => 120,
                'aliases'          => ['pool', 'swimming pool', 'is there a pool', 'does it have a pool'],
            ],
            'seller_garage' => [
                'role'             => 'seller',
                'property_types'   => [PT::RESIDENTIAL],
                // Suppressed whenever the parking composite is available, which answers
                // the same question with the space count and the carport as well.
                'narrower_of'      => 'seller_parking',
                'question'         => 'Does the property have a garage?',
                'source_kind'      => 'listing',
                // context garage ← meta 'garage_needed' (Yes / No). Only Yes / No is stated;
                // no space count is derived from anything.
                'source_path'      => 'listing.garage',
                'supporting_paths' => [],
                'formatter'        => 'has_garage',
                'guards'           => [],
                'category'         => 'features',
                'order'            => 130,
                'aliases'          => ['garage', 'is there a garage', 'does it have a garage'],
            ],
            'seller_zoning' => [
                'role'             => 'seller',
                'property_types'   => [PT::COMMERCIAL, PT::BUSINESS, PT::VACANT_LAND],
                'question'         => 'What is the zoning?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.zoning',
                'supporting_paths' => [],
                'formatter'        => 'zoning',
                'guards'           => [],
                'category'         => 'construction',
                'order'            => 140,
                'aliases'          => ['zoning', 'zoned', 'zoning code'],
            ],
            'seller_roof_type' => [
                'role'             => 'seller',
                'property_types'   => [PT::RESIDENTIAL, PT::INCOME, PT::COMMERCIAL, PT::BUSINESS],
                'question'         => 'What type of roof does it have?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.roof_type',
                'supporting_paths' => [],
                // The answer is built from the stored selections, not the context string,
                // which appends other_roof_type even when "Other" is no longer selected.
                'other_companion'  => ['selected_in' => 'roof_type', 'meta_key' => 'other_roof_type'],
                'formatter'        => 'roof_type_list',
                'guards'           => [],
                'category'         => 'construction',
                'order'            => 150,
                'aliases'          => ['roof', 'roof type', 'type of roof'],
            ],
            'seller_leasing_restrictions' => [
                'role'             => 'seller',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'Are there leasing restrictions?',
                'source_kind'      => 'listing',
                // context rental_restrictions ← meta 'leasing_restrictions' (Yes / No /
                // Not Applicable / Unknown). Published only while the listing has an HOA:
                // the page shows this row inside its HOA / Association block.
                'source_path'      => 'listing.rental_restrictions',
                'supporting_paths' => ['listing.hoa_association'],
                'formatter'        => 'leasing_restrictions',
                'guards'           => [],
                'category'         => 'hoa',
                'order'            => 160,
                'aliases'          => ['leasing restrictions', 'rental restrictions', 'can i rent it out'],
            ],
            'seller_offered_financing' => [
                'role'             => 'seller',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What financing types will the seller consider?',
                // offered_financing is owner_only for the AI context; this surface restates
                // it under AskAiPublicPropertyQuestionService::PUBLIC_QUESTION_ADMISSIONS.
                'source_kind'      => 'admitted_listing',
                'source_path'      => 'listing.offered_financing',
                'supporting_paths' => [],
                // The typed "Other" text may not carry figures: a rate, amount or percentage
                // is a financing TERM, and hides the question.
                'other_companion'  => ['selected_in' => 'offered_financing', 'meta_key' => 'other_financing', 'reject_figures' => true],
                'formatter'        => 'financing_types',
                'guards'           => [],
                'category'         => 'financing',
                'order'            => 170,
                'aliases'          => ['financing', 'financing options', 'cash offer', 'all cash', 'fha', 'va', 'conventional', 'seller financing'],
            ],

            // ---- Landlord ----
            'landlord_bedrooms' => [
                'role'             => 'landlord',
                'property_types'   => [PT::RESIDENTIAL],
                'question'         => 'How many bedrooms are there?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.bedrooms',
                'supporting_paths' => [],
                'formatter'        => 'bedroom_count',
                'guards'           => [],
                'category'         => 'size',
                'order'            => 20,
                'aliases'          => ['bedrooms', 'beds', 'how many bedrooms'],
            ],
            'landlord_bathrooms' => [
                'role'             => 'landlord',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'How many bathrooms are there?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.bathrooms',
                'supporting_paths' => [],
                'formatter'        => 'bathroom_count',
                'guards'           => [],
                'category'         => 'size',
                'order'            => 30,
                'aliases'          => ['bathrooms', 'baths', 'how many bathrooms'],
            ],
            'landlord_heated_square_feet' => [
                'role'             => 'landlord',
                'property_types'   => [PT::RESIDENTIAL],
                'question'         => 'What is the heated square footage?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.square_feet',
                'supporting_paths' => [],
                'formatter'        => 'heated_square_feet',
                'guards'           => [],
                'category'         => 'size',
                'order'            => 40,
                'aliases'          => ['square footage', 'square feet', 'sq ft', 'how big'],
            ],
            'landlord_appliances' => [
                'role'             => 'landlord',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What appliances are included?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.appliances',
                'supporting_paths' => [],
                'formatter'        => 'appliance_list',
                'guards'           => [],
                'category'         => 'features',
                'order'            => 90,
                'aliases'          => ['appliances', 'what appliances'],
            ],
            'landlord_pets_allowed' => [
                'role'             => 'landlord',
                'property_types'   => [PT::RESIDENTIAL],
                'question'         => 'Are pets allowed?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.pet_policy',
                // The whole pet answer in one row. A renter asks "can I bring my dog?" and
                // needs the species, the weight cap and the money in the same breath; six
                // separate rows would be the same fact said six times.
                // SPECIES and the money, never restriction prose: `pet_species_allowed` is
                // dog/cat, which is an ordinary occupancy limit, while free-text restriction
                // and "other" fields are where breed limits live and are excluded for the same
                // Fair Housing reason the seller entry excludes them.
                'supporting_paths' => ['listing.pet_species_allowed', 'listing.pet_max_weight_lbs',
                                       'listing.pet_fee_type', 'listing.pet_fee_amount',
                                       'listing.pet_deposit_fee_rent'],
                'covers'           => ['listing.pet_policy', 'listing.pet_species_allowed',
                                       'listing.pet_max_weight_lbs', 'listing.pet_fee_type',
                                       'listing.pet_fee_amount', 'listing.pet_deposit_fee_rent'],
                'formatter'        => 'pet_policy_composite',
                'guards'           => [],
                'category'         => 'policies',
                'order'            => 110,
                'aliases'          => ['pets', 'pets allowed', 'dogs', 'cats', 'pet policy', 'are pets allowed'],
            ],

            // ---- Landlord — Batch 2b ----
            'landlord_year_built' => [
                'role'             => 'landlord',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What year was the property built?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.year_built',
                'supporting_paths' => [],
                'formatter'        => 'year_built',
                'guards'           => [],
                'category'         => 'construction',
                'order'            => 50,
                'aliases'          => ['year built', 'when was it built', 'how old is the property'],
            ],
            'landlord_property_taxes' => [
                'role'             => 'landlord',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What are the property taxes?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.annual_property_taxes',
                'supporting_paths' => ['listing.tax_year'],
                'formatter'        => 'annual_property_taxes',
                'guards'           => [],
                'category'         => 'costs',
                'order'            => 60,
                'aliases'          => ['taxes', 'property tax', 'property taxes', 'tax amount', 'how much are taxes', 'how much are the taxes', 'what are the taxes'],
            ],
            'landlord_hoa_fee' => [
                'role'             => 'landlord',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What are the HOA fees?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.association_fee_amount',
                'supporting_paths' => ['listing.has_hoa', 'listing.association_fee_frequency'],
                'other_companion'  => ['selected_in' => 'association_fee_frequency', 'meta_key' => 'association_fee_frequency_other'],
                'formatter'        => 'hoa_fee',
                'guards'           => [],
                'category'         => 'hoa',
                'order'            => 70,
                'aliases'          => ['hoa', 'hoa fee', 'hoa fees', 'association fee', 'association fees', 'hoa dues', 'how much is the hoa', 'how much are the hoa fees'],
                'narrower_of'      => 'landlord_hoa_fee_coverage',
            ],
            'landlord_hoa_fee_coverage' => [
                'role'             => 'landlord',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'HOA fees & what do they cover?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.association_fee_amount',
                'supporting_paths' => ['listing.has_hoa', 'listing.association_fee_frequency', 'listing.association_fee_includes'],
                'other_companions' => [
                    'frequency' => ['selected_in' => 'association_fee_frequency', 'meta_key' => 'association_fee_frequency_other'],
                    'includes'  => ['selected_in' => 'association_fee_includes', 'meta_key' => 'association_fee_includes_other'],
                ],
                'formatter'        => 'hoa_fee_coverage',
                'guards'           => [],
                'category'         => 'hoa',
                'order'            => 69,
                'aliases'          => ['hoa', 'hoa fee', 'hoa fees', 'association fee', 'association fees', 'hoa dues', 'how much is the hoa', 'how much are the hoa fees', 'what does the hoa cover', 'what does the hoa fee include', 'hoa includes'],
            ],
            'landlord_zoning' => [
                'role'             => 'landlord',
                'property_types'   => [PT::COMMERCIAL],
                'question'         => 'What is the zoning?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.zoning',
                'supporting_paths' => [],
                'formatter'        => 'zoning',
                'guards'           => [],
                'category'         => 'construction',
                'order'            => 140,
                'aliases'          => ['zoning', 'zoned', 'zoning code'],
            ],
            'landlord_roof_type' => [
                'role'             => 'landlord',
                'property_types'   => [PT::RESIDENTIAL],
                'question'         => 'What type of roof does it have?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.roof_type',
                'supporting_paths' => [],
                'other_companion'  => ['selected_in' => 'roof_type', 'meta_key' => 'other_roof_type'],
                'formatter'        => 'roof_type_list',
                'guards'           => [],
                'category'         => 'construction',
                'order'            => 150,
                'aliases'          => ['roof', 'roof type', 'type of roof'],
            ],
            'landlord_leasing_restrictions' => [
                'role'             => 'landlord',
                'property_types'   => PT::ALL_TYPES,
                // The HOA's leasing-restriction answer. It is NOT the offered lease term:
                // min_lease_period and the lease-length cascade are never read here.
                'question'         => 'Are there leasing restrictions?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.leasing_restrictions',
                'supporting_paths' => ['listing.has_hoa'],
                'formatter'        => 'leasing_restrictions',
                'guards'           => [],
                'category'         => 'hoa',
                'order'            => 160,
                'aliases'          => ['leasing restrictions', 'rental restrictions', 'hoa restrictions'],
            ],
            'landlord_association_amenities' => [
                'role'             => 'landlord',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What community amenities are listed?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.association_amenities',
                'supporting_paths' => ['listing.has_hoa'],
                'other_companion'  => ['selected_in' => 'association_amenities', 'meta_key' => 'association_amenities_other'],
                'formatter'        => 'community_amenity_list',
                'guards'           => [],
                'category'         => 'hoa',
                'order'            => 165,
                'aliases'          => ['amenities', 'community amenities', 'clubhouse', 'community pool'],
            ],

            'seller_flood_zone' => [
                'role'             => 'seller',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What flood zone is the property in?',
                'source_kind'      => 'listing',
                // The canonical stored designation, and the only flood source this surface
                // reads. NOT flood_zone_designation / flood_zone_description (free-text
                // narrative, still RESTRICTED), NOT is_in_flood_zone (a boolean, still
                // RESTRICTED), NOT flood_insurance_required (a lender/insurer requirement,
                // a different claim), and NOT the FEMA map lookup in Location DNA, which is
                // a separate system that never writes this field.
                'source_path'      => 'listing.flood_zone_code',
                'supporting_paths' => [],
                'formatter'        => 'flood_zone',
                'guards'           => [],
                'category'         => 'property',
                'order'            => 180,
                'aliases'          => ['flood zone', 'flood', 'fema', 'fema zone', 'flood risk', 'what flood zone', 'what flood zone is it in', 'is it in a flood zone'],
            ],
            'landlord_flood_zone' => [
                'role'             => 'landlord',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What flood zone is the property in?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.flood_zone_code',
                'supporting_paths' => [],
                'formatter'        => 'flood_zone',
                'guards'           => [],
                'category'         => 'property',
                'order'            => 170,
                'aliases'          => ['flood zone', 'flood', 'fema', 'fema zone', 'flood risk', 'what flood zone', 'what flood zone is it in', 'is it in a flood zone'],
            ],

            // ================================================================
            // Buyer — search criteria (Batch 2d)
            //
            // A buyer listing is a SEARCH REQUEST, not a property. Every question below
            // asks what this buyer is looking for and every answer says so; none states a
            // fact about a property, because no property is being described.
            //
            // Every source_path names a key in
            // AskAiPublicPropertyQuestionService::PUBLIC_BUYER_CRITERIA. That catalog is
            // the only admission these roles have — SnapshotFactVisibility answers
            // OWNER_ONLY for every buyer key (D2, unchanged) — so a path added here that is
            // not in it resolves to 'not_public_allowed' and the question never renders.
            // Pre-approval, cash, down payment, credit, lender, occupants and the buyer's
            // own address are absent from that catalog and unreachable from here.
            // ================================================================
            'buyer_budget' => [
                'role'             => 'buyer',
                'property_types'   => PT::ALL_TYPES,
                'question'         => "What is the buyer's budget?",
                'source_kind'      => 'listing',
                'source_path'      => 'listing.max_price',
                'supporting_paths' => [],
                'formatter'        => 'criteria_max_purchase_budget',
                // The page prints Max Purchase Budget and Max Purchase Price as separate
                // rows from four keys. When they disagree it shows both and lets a reader
                // judge; one sentence cannot, so the question hides rather than choosing.
                'guards'           => ['buyer_budget_not_divergent'],
                'category'         => 'price',
                'order'            => 10,
                'aliases'          => ['budget', 'buyer budget', 'purchase budget', 'price range', 'max budget', 'how much can they spend', 'how much does the buyer want to spend'],
            ],
            'buyer_search_areas' => [
                'role'             => 'buyer',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What areas is the buyer looking in?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.cities',
                'supporting_paths' => ['listing.counties'],
                'formatter'        => 'criteria_search_areas',
                'guards'           => [],
                'category'         => 'location',
                'order'            => 20,
                'aliases'          => ['areas', 'where', 'locations', 'cities', 'neighborhoods'],
            ],
            'buyer_search_areas_counties' => [
                'role'             => 'buyer',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What areas is the buyer looking in?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.counties',
                'supporting_paths' => [],
                'formatter'        => 'criteria_search_areas_counties',
                'guards'           => [],
                // Stands in when no cities are named; suppressed when the fuller entry above
                // is available, so the same question never appears twice.
                'narrower_of'      => 'buyer_search_areas',
                'category'         => 'location',
                'order'            => 21,
                'aliases'          => ['areas', 'where', 'counties'],
            ],
            'buyer_property_type' => [
                'role'             => 'buyer',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What property type is the buyer interested in?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.property_type',
                'supporting_paths' => [],
                'formatter'        => 'criteria_property_type',
                'guards'           => [],
                'category'         => 'property',
                'order'            => 30,
                'aliases'          => ['property type', 'what kind of property', 'type of home'],
            ],
            'buyer_bedrooms' => [
                'role'             => 'buyer',
                'property_types'   => [PT::RESIDENTIAL, PT::INCOME],
                'question'         => 'How many bedrooms are they looking for?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.bedrooms',
                'supporting_paths' => [],
                'formatter'        => 'criteria_min_bedrooms',
                // The context resolves an "Other" free-text bedroom value; require the
                // structured field so the answer is a number the form actually stored.
                'guards'           => ['meta_present:bedrooms'],
                'category'         => 'size',
                'order'            => 40,
                'aliases'          => ['bedrooms', 'beds', 'how many bedrooms'],
            ],
            'buyer_bathrooms' => [
                'role'             => 'buyer',
                'property_types'   => [PT::RESIDENTIAL, PT::INCOME],
                'question'         => 'How many bathrooms are they looking for?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.bathrooms',
                'supporting_paths' => [],
                'formatter'        => 'criteria_min_bathrooms',
                'guards'           => ['meta_present:bathrooms'],
                'category'         => 'size',
                'order'            => 50,
                'aliases'          => ['bathrooms', 'baths', 'how many bathrooms'],
            ],
            'buyer_square_feet' => [
                'role'             => 'buyer',
                'property_types'   => [PT::RESIDENTIAL, PT::INCOME, PT::COMMERCIAL],
                'question'         => 'What square footage are they looking for?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.square_feet',
                'supporting_paths' => [],
                'formatter'        => 'criteria_min_square_feet',
                'guards'           => [],
                'category'         => 'size',
                'order'            => 60,
                'aliases'          => ['square footage', 'square feet', 'sq ft', 'how big'],
            ],
            'buyer_acreage' => [
                'role'             => 'buyer',
                'property_types'   => [PT::RESIDENTIAL, PT::INCOME, PT::COMMERCIAL, PT::VACANT_LAND],
                'question'         => 'What acreage are they looking for?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.total_acreage',
                'supporting_paths' => [],
                'formatter'        => 'criteria_min_acreage_band',
                'guards'           => [],
                'category'         => 'size',
                'order'            => 70,
                'aliases'          => ['acreage', 'lot size', 'acres', 'land'],
            ],
            'buyer_pool' => [
                'role'             => 'buyer',
                'property_types'   => [PT::RESIDENTIAL, PT::INCOME],
                'question'         => 'Do they want a pool?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.pool',
                'supporting_paths' => [],
                'formatter'        => 'criteria_wants_pool',
                'guards'           => [],
                'category'         => 'features',
                'order'            => 80,
                'aliases'          => ['pool', 'swimming pool', 'do they want a pool'],
            ],
            'buyer_garage' => [
                'role'             => 'buyer',
                'property_types'   => [PT::RESIDENTIAL, PT::INCOME],
                'question'         => 'Do they want a garage?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.garage',
                'supporting_paths' => [],
                'formatter'        => 'criteria_wants_garage',
                // The context cascades garage_needed -> other_garage -> other_garage_needed,
                // and the last two are free text that can hold a COUNT. The Yes/No field
                // must be the one that answered, or this becomes a count read as a flag.
                'guards'           => ['meta_present:garage_needed'],
                'category'         => 'features',
                'order'            => 90,
                'aliases'          => ['garage', 'do they want a garage'],
            ],
            'buyer_timeframe' => [
                'role'             => 'buyer',
                'property_types'   => PT::ALL_TYPES,
                'question'         => "What is the buyer's timeframe?",
                'source_kind'      => 'listing',
                'source_path'      => 'listing.closing_date',
                'supporting_paths' => [],
                'formatter'        => 'criteria_closing_timeframe',
                'guards'           => [],
                'category'         => 'timing',
                'order'            => 100,
                'aliases'          => ['timeframe', 'when', 'closing date', 'how soon', 'what is their timeframe'],
            ],
            'buyer_property_features' => [
                'role'             => 'buyer',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What other property features are they looking for?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.non_negotiable_amenities',
                'supporting_paths' => [],
                // Structured multi-selects only, read as their exact stored selections.
                'other_companions' => [
                    'amenities' => ['selected_in' => 'non_negotiable_amenities', 'meta_key' => 'other_non_negotiable_amenities'],
                    'views'     => ['selected_in' => 'view_preference',          'meta_key' => 'other_preferences'],
                ],
                'formatter'        => 'criteria_feature_list',
                'guards'           => [],
                'category'         => 'features',
                'order'            => 110,
                'aliases'          => ['features', 'amenities', 'must haves', 'what else'],
            ],
            'buyer_view_preference' => [
                'role'             => 'buyer',
                'property_types'   => [PT::RESIDENTIAL, PT::INCOME, PT::VACANT_LAND],
                'question'         => 'What other property features are they looking for?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.water_view',
                'supporting_paths' => [],
                'other_companions' => [
                    'views' => ['selected_in' => 'view_preference', 'meta_key' => 'other_preferences'],
                ],
                'formatter'        => 'criteria_feature_list',
                'guards'           => [],
                'narrower_of'      => 'buyer_property_features',
                'category'         => 'features',
                'order'            => 111,
                'aliases'          => ['view', 'views', 'water view'],
            ],

            // ================================================================
            // Tenant — search criteria (Batch 2d)
            //
            // Same contract as buyer, over a context that also carries monthly_income,
            // credit_score_range, prior_eviction, prior_felony, service_animal,
            // emotional_support_animal, accessibility_requirements, number_of_occupants and
            // the tenant's own address. None of those is in PUBLIC_TENANT_CRITERIA, so none
            // is reachable from any entry below.
            //
            // A SERVICE OR SUPPORT ANIMAL IS NOT A PET, and the pets question does not go
            // near either one: it reads the form's own "Pets:" Yes/No and nothing else.
            // ================================================================
            'tenant_max_rent' => [
                'role'             => 'tenant',
                'property_types'   => PT::ALL_TYPES,
                'question'         => "What is the tenant's maximum rent?",
                // criteria_meta, NOT a shared-context key: the rent budget is read from the
                // page's own meta through PUBLIC_CRITERIA_META_SOURCES, so nothing is widened
                // into the generic Ask AI / Agent AI context to make this question work.
                // NOT max_rent / min_rent / rental_price — a landlord's advertised rent range,
                // RESTRICTED for every role and structurally unreadable from here.
                'source_kind'      => 'criteria_meta',
                'source_path'      => 'criteria_meta.rent_budget',
                'supporting_paths' => [],
                'formatter'        => 'criteria_max_rent',
                // Never derived from income or move-in funds; those keys are not readable
                // here at all. The guard covers the page's own three-key rent fallback.
                'guards'           => ['tenant_rent_not_divergent'],
                'category'         => 'price',
                'order'            => 10,
                'aliases'          => ['rent', 'budget', 'rent budget', 'tenant budget', 'max rent', 'maximum rent', 'how much rent', 'how much rent can they pay'],
            ],
            'tenant_search_areas' => [
                'role'             => 'tenant',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What areas are they looking in?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.cities',
                'supporting_paths' => ['listing.counties', 'listing.zip_codes'],
                'formatter'        => 'criteria_search_areas',
                'guards'           => [],
                'category'         => 'location',
                'order'            => 20,
                'aliases'          => ['areas', 'where', 'locations', 'cities', 'neighborhoods'],
            ],
            'tenant_search_areas_counties' => [
                'role'             => 'tenant',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What areas are they looking in?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.counties',
                'supporting_paths' => ['listing.zip_codes'],
                'formatter'        => 'criteria_search_areas_counties',
                'guards'           => [],
                'narrower_of'      => 'tenant_search_areas',
                'category'         => 'location',
                'order'            => 21,
                'aliases'          => ['areas', 'where', 'counties'],
            ],
            'tenant_property_type' => [
                'role'             => 'tenant',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What property type are they looking for?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.property_type',
                'supporting_paths' => [],
                'formatter'        => 'criteria_property_type',
                'guards'           => [],
                'category'         => 'property',
                'order'            => 30,
                'aliases'          => ['property type', 'what kind of property', 'type of rental'],
            ],
            'tenant_bedrooms' => [
                'role'             => 'tenant',
                'property_types'   => [PT::RESIDENTIAL],
                'question'         => 'How many bedrooms are they looking for?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.bedrooms',
                'supporting_paths' => [],
                'formatter'        => 'criteria_min_bedrooms',
                'guards'           => ['meta_present:bedrooms'],
                'category'         => 'size',
                'order'            => 40,
                'aliases'          => ['bedrooms', 'beds', 'how many bedrooms'],
            ],
            'tenant_bathrooms' => [
                'role'             => 'tenant',
                'property_types'   => [PT::RESIDENTIAL],
                'question'         => 'How many bathrooms are they looking for?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.bathrooms',
                'supporting_paths' => [],
                'formatter'        => 'criteria_min_bathrooms',
                'guards'           => ['meta_present:bathrooms'],
                'category'         => 'size',
                'order'            => 50,
                'aliases'          => ['bathrooms', 'baths', 'how many bathrooms'],
            ],
            'tenant_square_feet' => [
                'role'             => 'tenant',
                'property_types'   => [PT::RESIDENTIAL, PT::COMMERCIAL],
                'question'         => 'What square footage are they looking for?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.square_feet',
                'supporting_paths' => [],
                'formatter'        => 'criteria_min_square_feet',
                'guards'           => [],
                'category'         => 'size',
                'order'            => 60,
                'aliases'          => ['square footage', 'square feet', 'sq ft', 'how big'],
            ],
            'tenant_acreage' => [
                'role'             => 'tenant',
                'property_types'   => [PT::RESIDENTIAL, PT::COMMERCIAL, PT::VACANT_LAND],
                'question'         => 'What acreage are they looking for?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.total_acreage',
                'supporting_paths' => [],
                'formatter'        => 'criteria_min_acreage_band',
                'guards'           => [],
                'category'         => 'size',
                'order'            => 70,
                'aliases'          => ['acreage', 'lot size', 'acres', 'land'],
            ],
            'tenant_lease_term' => [
                'role'             => 'tenant',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What lease term are they looking for?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.desired_lease_length',
                'supporting_paths' => [],
                'formatter'        => 'criteria_lease_term',
                // The context cascades desired_lease_length -> lease_for, and "Leasing For"
                // is a different question ("Residential", "Commercial"). Require the desired
                // lease-length field itself. `min_lease_period` is NOT consulted anywhere:
                // it is a seller/HOA restriction on a property, not a tenant preference.
                'guards'           => ['meta_present:desired_lease_length'],
                'category'         => 'lease',
                'order'            => 80,
                'aliases'          => ['lease term', 'lease length', 'how long', 'term'],
            ],
            'tenant_move_in' => [
                'role'             => 'tenant',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'When do they want to move in?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.move_in_date_earliest',
                'supporting_paths' => ['listing.move_in_date_latest'],
                'formatter'        => 'criteria_move_in_window',
                'guards'           => [],
                'category'         => 'timing',
                'order'            => 90,
                'aliases'          => ['move in', 'move in date', 'when can they move', 'when do they want to move', 'when does the tenant want to move', 'availability'],
            ],
            'tenant_pets' => [
                'role'             => 'tenant',
                'property_types'   => [PT::RESIDENTIAL],
                'question'         => 'Do they need pets allowed?',
                'source_kind'      => 'criteria_meta',
                'source_path'      => 'criteria_meta.pets_allowed',
                'supporting_paths' => [],
                'formatter'        => 'criteria_pets_needed',
                'guards'           => [],
                'category'         => 'features',
                'order'            => 100,
                'aliases'          => ['pets', 'pet friendly', 'do they have pets', 'do they need pets allowed'],
            ],
            'tenant_furnishings' => [
                'role'             => 'tenant',
                'property_types'   => [PT::RESIDENTIAL],
                'question'         => 'Do they want the property furnished?',
                'source_kind'      => 'criteria_meta',
                'source_path'      => 'criteria_meta.furnishings',
                'supporting_paths' => [],
                'formatter'        => 'criteria_furnishings',
                'guards'           => [],
                'category'         => 'features',
                'order'            => 110,
                'aliases'          => ['furnished', 'furnishings', 'unfurnished', 'turnkey', 'do they want it furnished'],
            ],
            'tenant_property_features' => [
                'role'             => 'tenant',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What amenities and features are they looking for?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.non_negotiable_amenities',
                'supporting_paths' => [],
                'other_companions' => [
                    'amenities' => ['selected_in' => 'non_negotiable_amenities', 'meta_key' => 'other_non_negotiable_amenities'],
                    'appliances'=> ['selected_in' => 'appliances',               'meta_key' => 'other_appliances'],
                    'items'     => ['selected_in' => 'property_items',           'meta_key' => 'other_property_items'],
                    'views'     => ['selected_in' => 'view_preference',          'meta_key' => 'other_preferences'],
                ],
                'formatter'        => 'criteria_feature_list',
                'guards'           => [],
                'category'         => 'features',
                'order'            => 120,
                'aliases'          => ['features', 'amenities', 'must haves', 'appliances', 'what else'],
            ],
            'tenant_appliances' => [
                'role'             => 'tenant',
                'property_types'   => [PT::RESIDENTIAL],
                'question'         => 'What amenities and features are they looking for?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.appliances',
                'supporting_paths' => [],
                'other_companions' => [
                    'appliances'=> ['selected_in' => 'appliances',      'meta_key' => 'other_appliances'],
                    'items'     => ['selected_in' => 'property_items',  'meta_key' => 'other_property_items'],
                    'views'     => ['selected_in' => 'view_preference', 'meta_key' => 'other_preferences'],
                ],
                'formatter'        => 'criteria_feature_list',
                'guards'           => [],
                'narrower_of'      => 'tenant_property_features',
                'category'         => 'features',
                'order'            => 121,
                'aliases'          => ['appliances', 'features'],
            ],
            'tenant_pool' => [
                'role'             => 'tenant',
                'property_types'   => [PT::RESIDENTIAL],
                'question'         => 'Do they want a pool?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.pool',
                'supporting_paths' => [],
                'formatter'        => 'criteria_wants_pool',
                'guards'           => [],
                'category'         => 'features',
                'order'            => 130,
                'aliases'          => ['pool', 'swimming pool'],
            ],

            /* ============================================================== *
             * Batch 5 — Seller property facts.
             *
             * COMPOSITES, NOT ONE QUESTION PER COLUMN. A shopper asks "is it on
             * the water?", never "what is waterfront_feet?". Where several
             * canonical fields answer one human question they are gathered into a
             * single entry whose `covers` lists every field it speaks for, so the
             * coverage harness still sees each column accounted for. `covers` is
             * documentation AND contract: it is what stops a composite quietly
             * dropping a field it used to include.
             * ============================================================== */

            'seller_listing_description' => [
                'role'             => 'seller',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What does the seller say about this property?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.description',
                'supporting_paths' => [],
                'covers'           => ['listing.description'],
                // Provider-authored prose, so it is screened rather than printed:
                // the formatter refuses anything PublicProviderTextPolicy will not
                // publish, and refuses whole rather than truncating.
                'formatter'        => 'provider_description',
                'guards'           => [],
                'category'         => 'property',
                'order'            => 200,
                'aliases'          => ['description', 'about the property', 'tell me about this property',
                                       'property description', 'what does the seller say'],
            ],
            'seller_water_frontage' => [
                'role'             => 'seller',
                // Land and income property can be waterfront too; a business without
                // real estate cannot.
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'Is this property on the water?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.waterfront',
                'supporting_paths' => ['listing.waterfront_feet', 'listing.water_access', 'listing.water_view'],
                'covers'           => ['listing.waterfront', 'listing.waterfront_feet',
                                       'listing.water_access', 'listing.water_view'],
                'formatter'        => 'waterfront_composite',
                'guards'           => [],
                'category'         => 'features',
                'order'            => 210,
                'aliases'          => ['waterfront', 'is it waterfront', 'on the water', 'water access',
                                       'water view', 'waterfront feet', 'water frontage', 'canal', 'is it on the water'],
            ],
            'seller_parking' => [
                'role'             => 'seller',
                'property_types'   => [PT::RESIDENTIAL],
                'question'         => 'What parking does this property have?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.garage',
                // No garage COUNT: `listing.garage_spaces` is `garage_parking_spaces`, a Yes/No
                // control rather than a count, and Batch 2b pins that no size is published.
                'supporting_paths' => ['listing.carport'],
                'covers'           => ['listing.garage', 'listing.carport'],
                'formatter'        => 'parking_composite',
                'guards'           => [],
                'category'         => 'features',
                'order'            => 220,
                'aliases'          => ['parking', 'garage', 'is there a garage',
                                       'carport', 'covered parking', 'does it have a garage'],
            ],
            'seller_climate_control' => [
                'role'             => 'seller',
                'property_types'   => [PT::RESIDENTIAL, PT::INCOME, PT::COMMERCIAL, PT::BUSINESS],
                'question'         => 'How is this property heated and cooled?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.air_conditioning',
                'supporting_paths' => ['listing.heating_and_fuel', 'listing.heating_fuel'],
                'covers'           => ['listing.air_conditioning', 'listing.heating_and_fuel', 'listing.heating_fuel'],
                'formatter'        => 'climate_composite',
                'guards'           => [],
                'category'         => 'construction',
                'order'            => 230,
                'aliases'          => ['heating and cooling', 'air conditioning', 'ac', 'a c', 'hvac',
                                       'heating', 'heat', 'is there central air', 'how is it heated'],
            ],
            'seller_water_and_sewer' => [
                'role'             => 'seller',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What water and sewer service does this property have?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.water',
                'supporting_paths' => ['listing.water_source', 'listing.sewer'],
                'covers'           => ['listing.water', 'listing.water_source', 'listing.sewer'],
                'formatter'        => 'water_sewer_composite',
                'guards'           => [],
                'category'         => 'construction',
                'order'            => 240,
                'aliases'          => ['water and sewer', 'water', 'sewer', 'septic', 'is it on city water',
                                       'well', 'water source', 'is it on septic'],
            ],
            'seller_construction' => [
                'role'             => 'seller',
                'property_types'   => [PT::RESIDENTIAL, PT::INCOME, PT::COMMERCIAL, PT::BUSINESS],
                'question'         => 'How is this property built?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.exterior_construction',
                'supporting_paths' => ['listing.foundation'],
                'covers'           => ['listing.exterior_construction', 'listing.foundation'],
                'formatter'        => 'construction_composite',
                'guards'           => [],
                'category'         => 'construction',
                'order'            => 250,
                'aliases'          => ['construction', 'exterior', 'what is it made of', 'block or frame',
                                       'foundation', 'slab', 'building materials'],
            ],
            'seller_interior_features' => [
                'role'             => 'seller',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What interior features does this property have?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.interior_features',
                'supporting_paths' => [],
                'covers'           => ['listing.interior_features'],
                'formatter'        => 'interior_feature_list',
                'guards'           => [],
                'category'         => 'features',
                'order'            => 260,
                'aliases'          => ['interior features', 'inside features', 'what features does it have',
                                       'interior', 'finishes'],
            ],
            'seller_building_features' => [
                'role'             => 'seller',
                'property_types'   => [PT::COMMERCIAL],
                'question'         => 'What building features does this property have?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.building_features',
                'supporting_paths' => [],
                'covers'           => ['listing.building_features'],
                'formatter'        => 'building_feature_list',
                'guards'           => [],
                'category'         => 'features',
                'order'            => 270,
                'aliases'          => ['building features', 'building amenities', 'elevator'],
            ],
            'seller_furnished' => [
                'role'             => 'seller',
                'property_types'   => [PT::COMMERCIAL],
                'question'         => 'Is this property furnished?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.furnished',
                'supporting_paths' => [],
                'covers'           => ['listing.furnished'],
                'formatter'        => 'furnishings',
                'guards'           => [],
                'category'         => 'features',
                'order'            => 280,
                'aliases'          => ['furnished', 'is it furnished', 'furnishings', 'does it come furnished',
                                       'unfurnished', 'turnkey'],
            ],
            'seller_home_warranty' => [
                'role'             => 'seller',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'Is a home warranty offered?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.home_warranty_offered',
                'supporting_paths' => [],
                'covers'           => ['listing.home_warranty_offered'],
                'formatter'        => 'home_warranty',
                'guards'           => [],
                'category'         => 'financing',
                'order'            => 290,
                'aliases'          => ['home warranty', 'warranty', 'is a warranty included'],
            ],
            'seller_lot_size' => [
                'role'             => 'seller',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'How big is the lot?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.lot_size',
                'supporting_paths' => ['listing.lot_dimensions'],
                'covers'           => ['listing.lot_size', 'listing.lot_dimensions'],
                'formatter'        => 'lot_size_composite',
                // The acreage question already answers "how big is the lot" when the
                // seller supplied acreage; this is the narrower fallback.
                'narrower_of'      => 'seller_total_acreage',
                'guards'           => [],
                'category'         => 'size',
                'order'            => 300,
                'aliases'          => ['lot size', 'how big is the lot', 'lot dimensions', 'lot', 'yard size'],
            ],
            'seller_association_details' => [
                'role'             => 'seller',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'Is there an association, and does it have to approve a buyer?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.hoa_association',
                'supporting_paths' => ['listing.association_name', 'listing.hoa_name',
                                       'listing.association_approval_required'],
                'covers'           => ['listing.association_name', 'listing.hoa_name',
                                       'listing.association_approval_required'],
                'formatter'        => 'association_details_composite',
                'guards'           => [],
                'category'         => 'hoa',
                'order'            => 310,
                // 'hoa' itself is the FEE question's word — two questions answering one
                // typed word is a non-deterministic route, so this entry takes the wordings
                // that are unambiguously about the association rather than its cost.
                'aliases'          => ['is there an hoa', 'association', 'association name',
                                       'hoa approval', 'does the hoa have to approve', 'association approval'],
            ],
            'seller_special_assessments' => [
                'role'             => 'seller',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'Are there any special assessments?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.has_special_assessments',
                'supporting_paths' => ['listing.special_assessment_amount', 'listing.special_assessment_description'],
                'covers'           => ['listing.has_special_assessments', 'listing.special_assessment_amount',
                                       'listing.special_assessment_description'],
                'formatter'        => 'special_assessment_composite',
                'guards'           => [],
                'category'         => 'costs',
                'order'            => 320,
                'aliases'          => ['special assessment', 'special assessments', 'assessments',
                                       'are there assessments', 'any special assessment'],
            ],
            'seller_occupancy' => [
                'role'             => 'seller',
                'property_types'   => [PT::RESIDENTIAL, PT::INCOME, PT::COMMERCIAL, PT::BUSINESS],
                'question'         => 'Is the property occupied?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.occupant_status',
                'supporting_paths' => [],
                'covers'           => ['listing.occupant_status'],
                'formatter'        => 'occupant_status',
                'guards'           => [],
                'category'         => 'property',
                'order'            => 330,
                'aliases'          => ['occupied', 'is it occupied', 'vacant', 'is it vacant',
                                       'occupancy', 'is anyone living there', 'is it tenant occupied'],
            ],
            'seller_target_closing' => [
                'role'             => 'seller',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'When does the seller want to close?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.closing_date',
                'supporting_paths' => [],
                'covers'           => ['listing.closing_date'],
                'formatter'        => 'target_closing_date',
                'guards'           => [],
                'category'         => 'timing',
                'order'            => 340,
                'aliases'          => ['closing date', 'when can we close', 'target closing',
                                       'preferred closing date', 'close by'],
            ],
            'seller_included_items' => [
                'role'             => 'seller',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What is included with the property?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.property_items',
                'supporting_paths' => [],
                'covers'           => ['listing.property_items'],
                'formatter'        => 'included_items_list',
                'guards'           => [],
                'category'         => 'features',
                'order'            => 350,
                'aliases'          => ['what is included', 'included items', 'what conveys', 'what comes with it',
                                       'included with the sale'],
            ],
            'seller_parcel_count' => [
                'role'             => 'seller',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'How many parcels are included?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.total_parcel_count',
                'supporting_paths' => ['listing.additional_parcels'],
                // A COUNT, never an identifier. `parcel_id` and `legal_description`
                // are deliberately NOT covered here — see the withheld-location note
                // on AskAiPublicPropertyQuestionService::LOCATION_IDENTIFIER_KEYS.
                'covers'           => ['listing.total_parcel_count', 'listing.additional_parcels'],
                'formatter'        => 'parcel_count_composite',
                'guards'           => [],
                'category'         => 'size',
                'order'            => 360,
                'aliases'          => ['how many parcels', 'parcels', 'additional parcels', 'parcel count'],
            ],

            /* ============================================================== *
             * Batch 6 — Landlord property and lease facts.
             *
             * The lease-side composites matter more here than on the sale side: a
             * renter asks "what are the lease terms?" and "can I have a dog?", and
             * both of those are five or six stored columns. Splitting them would
             * produce a card of near-duplicate rows.
             * ============================================================== */

            'landlord_listing_description' => [
                'role'             => 'landlord',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What does the landlord say about this property?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.description',
                'supporting_paths' => [],
                'covers'           => ['listing.description'],
                'formatter'        => 'provider_description',
                'guards'           => [],
                'category'         => 'property',
                'order'            => 200,
                'aliases'          => ['description', 'about the property', 'tell me about this property',
                                       'property description', 'what does the landlord say'],
            ],
            'landlord_rent' => [
                'role'             => 'landlord',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What is the rent?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.rent_amount',
                'supporting_paths' => [],
                'covers'           => ['listing.rent_amount'],
                'formatter'        => 'lease_price',
                'guards'           => [],
                'category'         => 'price',
                'order'            => 210,
                'aliases'          => ['rent', 'how much is rent', 'monthly rent', 'what is the rent',
                                       'price', 'how much', 'cost per month'],
            ],
            'landlord_available_date' => [
                'role'             => 'landlord',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'When is it available?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.available_date',
                'supporting_paths' => [],
                'covers'           => ['listing.available_date'],
                'formatter'        => 'available_date',
                'guards'           => [],
                'category'         => 'timing',
                'order'            => 16,
                'aliases'          => ['available', 'when is it available', 'move in date', 'availability',
                                       'when can i move in', 'available date'],
            ],
            'landlord_lease_terms' => [
                'role'             => 'landlord',
                'property_types'   => [PT::COMMERCIAL],
                'question'         => 'What are the lease terms?',
                'source_kind'      => 'listing',
                // The landlord's OFFERED terms lead. `listing.lease_length` is deliberately
                // not read: it resolves from `min_lease_period`, the HOA minimum lease
                // period, and Batch 2b pins that it never becomes an offered lease term.
                'source_path'      => 'listing.lease_terms',
                'supporting_paths' => ['listing.renewal_option', 'listing.additional_lease_terms'],
                'covers'           => ['listing.lease_terms', 'listing.renewal_option',
                                       'listing.additional_lease_terms'],
                'formatter'        => 'lease_terms_composite',
                'guards'           => [],
                'category'         => 'lease',
                'order'            => 17,
                'aliases'          => ['lease terms', 'lease', 'term', 'renewal', 'can i renew'],
            ],
            'landlord_smoking_policy' => [
                'role'             => 'landlord',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'Is smoking allowed?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.smoking_policy',
                'supporting_paths' => [],
                'covers'           => ['listing.smoking_policy'],
                'formatter'        => 'smoking_policy',
                'guards'           => [],
                'category'         => 'policies',
                'order'            => 19,
                'aliases'          => ['smoking', 'is smoking allowed', 'can i smoke', 'smoking policy',
                                       'non smoking'],
            ],
            // The renewal option and additional lease terms are asked on BOTH landlord forms,
            // while the lease-terms composite leads with `terms_of_lease`, which only the
            // Commercial form collects. This entry answers them where that composite cannot,
            // and yields to it wherever the composite renders.
            'landlord_renewal_option' => [
                'role'             => 'landlord',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'Is there a lease renewal option?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.renewal_option',
                'supporting_paths' => ['listing.additional_lease_terms'],
                'covers'           => ['listing.renewal_option', 'listing.additional_lease_terms'],
                'formatter'        => 'renewal_composite',
                'guards'           => [],
                'category'         => 'lease',
                'order'            => 18,
                'narrower_of'      => 'landlord_lease_terms',
                'aliases'          => ['renewal', 'can i renew', 'renew the lease', 'renewal option',
                                       'lease renewal'],
            ],
            // Pet fees are asked on BOTH landlord forms; the pet-policy composite that also
            // states them is Residential-only because the "pets" input is. On a Commercial
            // lease this is the only question that can reach them. `pet_fee_other` stays
            // unasked (AskAiFieldDisposition::DELIBERATELY_NOT_ASKED, breed-proxy exposure).
            'landlord_pet_fee' => [
                'role'             => 'landlord',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'Is there a pet fee?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.pet_fee_type',
                'supporting_paths' => ['listing.pet_fee_amount'],
                'covers'           => ['listing.pet_fee_type', 'listing.pet_fee_amount'],
                'formatter'        => 'pet_fee',
                'guards'           => [],
                'category'         => 'policies',
                'order'            => 111,
                'narrower_of'      => 'landlord_pets_allowed',
                'aliases'          => ['pet fee', 'pet deposit', 'how much is the pet fee', 'pet rent'],
            ],
            'landlord_subletting_policy' => [
                'role'             => 'landlord',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'Is subletting allowed?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.subletting_policy',
                'supporting_paths' => [],
                'covers'           => ['listing.subletting_policy'],
                'formatter'        => 'subletting_policy',
                'guards'           => [],
                'category'         => 'policies',
                'order'            => 260,
                'aliases'          => ['subletting', 'sublet', 'can i sublet', 'subleasing', 'airbnb'],
            ],
            'landlord_parking_terms' => [
                'role'             => 'landlord',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What parking is available?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.parking_terms',
                'supporting_paths' => [],
                'covers'           => ['listing.parking_terms'],
                'formatter'        => 'parking_terms',
                'guards'           => [],
                'category'         => 'features',
                'order'            => 270,
                'aliases'          => ['parking', 'is there parking', 'where do i park', 'parking terms',
                                       'assigned parking', 'garage'],
            ],
            'landlord_condition' => [
                'role'             => 'landlord',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What condition is the property in?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.condition_prop',
                'supporting_paths' => [],
                'covers'           => ['listing.condition_prop'],
                'formatter'        => 'property_condition',
                'guards'           => [],
                'category'         => 'construction',
                'order'            => 280,
                'aliases'          => ['condition', 'what condition', 'is it renovated', 'updated'],
            ],
            'landlord_unit_details' => [
                'role'             => 'landlord',
                'property_types'   => [PT::RESIDENTIAL, PT::COMMERCIAL],
                'question'         => 'How big is the unit, and how many units are there?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.unit_size',
                'supporting_paths' => ['listing.number_of_units'],
                'covers'           => ['listing.unit_size', 'listing.number_of_units'],
                'formatter'        => 'unit_details_composite',
                'guards'           => [],
                'category'         => 'size',
                'order'            => 290,
                'aliases'          => ['unit size', 'how big is the unit', 'how many units', 'unit',
                                       'square feet of the unit'],
            ],
            'landlord_interior_features' => [
                'role'             => 'landlord',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What interior features does this property have?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.interior_features',
                'supporting_paths' => [],
                'covers'           => ['listing.interior_features'],
                'formatter'        => 'interior_feature_list',
                'guards'           => [],
                'category'         => 'features',
                'order'            => 300,
                'aliases'          => ['interior features', 'inside features', 'interior', 'finishes'],
            ],
            'landlord_building_features' => [
                'role'             => 'landlord',
                'property_types'   => [PT::COMMERCIAL],
                'question'         => 'What building features does this property have?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.building_features',
                'supporting_paths' => [],
                'covers'           => ['listing.building_features'],
                'formatter'        => 'building_feature_list',
                'guards'           => [],
                'category'         => 'features',
                'order'            => 310,
                'aliases'          => ['building features', 'building amenities', 'elevator', 'gym'],
            ],
            'landlord_included_items' => [
                'role'             => 'landlord',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What is included with the property?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.property_items',
                'supporting_paths' => [],
                'covers'           => ['listing.property_items'],
                'formatter'        => 'included_items_list',
                'guards'           => [],
                'category'         => 'features',
                'order'            => 320,
                'aliases'          => ['what is included', 'included items', 'what comes with it'],
            ],
            // Landlord pool, garage/carport and acreage: form inputs (LP property-preferences
            // :865 / :661 / :626 Residential Property, :549 both types) that Ask AI never read.
            // Same formatters as the seller entries, so the two roles cannot word one fact two ways.
            'landlord_pool' => [
                'role'             => 'landlord',
                'property_types'   => [PT::RESIDENTIAL],
                'question'         => 'Does the property have a pool?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.pool',
                'supporting_paths' => [],
                'covers'           => ['listing.pool'],
                'formatter'        => 'pool_composite',
                'guards'           => [],
                'category'         => 'features',
                'order'            => 225,
                'aliases'          => ['pool', 'swimming pool', 'is there a pool', 'does it have a pool'],
            ],
            'landlord_garage_carport' => [
                'role'             => 'landlord',
                'property_types'   => [PT::RESIDENTIAL],
                'question'         => 'Is there a garage or carport?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.garage',
                'supporting_paths' => ['listing.carport'],
                'covers'           => ['listing.garage', 'listing.carport'],
                'formatter'        => 'parking_composite',
                'guards'           => [],
                'category'         => 'features',
                'order'            => 272,
                // Not the bare 'garage': landlord_parking_terms already answers to it on the
                // same listings, and one typed word must reach one question.
                'aliases'          => ['is there a garage', 'carport', 'covered parking'],
            ],
            'landlord_total_acreage' => [
                'role'             => 'landlord',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What is the lot size / acreage?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.total_acreage',
                'supporting_paths' => [],
                'covers'           => ['listing.total_acreage'],
                'formatter'        => 'acreage_band',
                'guards'           => [],
                'category'         => 'size',
                'order'            => 365,
                // 'lot size' / 'how big is the lot' belong to landlord_lot_dimensions.
                'aliases'          => ['acreage', 'total acreage', 'how many acres'],
            ],
            'landlord_water_frontage' => [
                'role'             => 'landlord',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'Is this property on the water?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.waterfront',
                // waterfront_feet is a landlord form input (LP property-preferences :822) the
                // context never read until the MLS-import completeness audit.
                'supporting_paths' => ['listing.waterfront_feet', 'listing.water_access', 'listing.water_view', 'listing.view'],
                'covers'           => ['listing.waterfront', 'listing.waterfront_feet', 'listing.water_access',
                                       'listing.water_view', 'listing.view'],
                'formatter'        => 'waterfront_composite',
                'guards'           => [],
                'category'         => 'features',
                'order'            => 330,
                'aliases'          => ['waterfront', 'is it waterfront', 'on the water', 'water access',
                                       'water view', 'view', 'what is the view'],
            ],
            'landlord_climate_control' => [
                'role'             => 'landlord',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'How is this property heated and cooled?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.air_conditioning',
                'supporting_paths' => ['listing.heating_fuel'],
                'covers'           => ['listing.air_conditioning', 'listing.heating_fuel'],
                'formatter'        => 'climate_composite',
                'guards'           => [],
                'category'         => 'construction',
                'order'            => 340,
                'aliases'          => ['air conditioning', 'ac', 'a c', 'hvac', 'heating', 'heat',
                                       'is there central air', 'heating and cooling'],
            ],
            'landlord_water_and_sewer' => [
                'role'             => 'landlord',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'What water and sewer service does this property have?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.water',
                'supporting_paths' => ['listing.sewer'],
                'covers'           => ['listing.water', 'listing.sewer'],
                'formatter'        => 'water_sewer_composite',
                'guards'           => [],
                'category'         => 'construction',
                'order'            => 350,
                'aliases'          => ['water and sewer', 'water', 'sewer', 'septic', 'well'],
            ],
            'landlord_construction' => [
                'role'             => 'landlord',
                'property_types'   => [PT::RESIDENTIAL],
                'question'         => 'How is this property built?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.exterior_construction',
                'supporting_paths' => ['listing.foundation'],
                'covers'           => ['listing.exterior_construction', 'listing.foundation'],
                'formatter'        => 'construction_composite',
                'guards'           => [],
                'category'         => 'construction',
                'order'            => 360,
                'aliases'          => ['construction', 'exterior', 'what is it made of', 'foundation',
                                       'block or frame'],
            ],
            'landlord_lot_dimensions' => [
                'role'             => 'landlord',
                'property_types'   => [PT::RESIDENTIAL],
                'question'         => 'What are the lot dimensions?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.lot_dimensions',
                'supporting_paths' => [],
                'covers'           => ['listing.lot_dimensions'],
                'formatter'        => 'lot_dimensions_only',
                'guards'           => [],
                'category'         => 'size',
                'order'            => 370,
                'aliases'          => ['lot dimensions', 'lot size', 'how big is the lot', 'yard size'],
            ],
            'landlord_association_details' => [
                'role'             => 'landlord',
                'property_types'   => PT::ALL_TYPES,
                'question'         => 'Is this property in an association?',
                'source_kind'      => 'listing',
                'source_path'      => 'listing.has_hoa',
                'supporting_paths' => ['listing.association_name'],
                'covers'           => ['listing.has_hoa', 'listing.association_name'],
                'formatter'        => 'association_details_composite',
                'guards'           => [],
                'category'         => 'hoa',
                'order'            => 380,
                'aliases'          => ['is there an hoa', 'association', 'association name',
                                       'condo association'],
            ],
        ];
    }

    /**
     * Returns all canonical listing model paths (listing.*).
     *
     * @return string[]
     */
    public static function allListingFieldPaths(): array
    {
        return array_keys(static::listingFieldRegistry());
    }

    /**
     * Returns listing model registry entries for the given role(s).
     *
     * @param  string|string[] $roles
     * @return array<string, array>
     */
    public static function listingFieldsByRole(string|array $roles): array
    {
        $roles  = (array) $roles;
        $result = [];
        foreach (static::listingFieldRegistry() as $path => $entry) {
            foreach ($roles as $role) {
                if (in_array($role, $entry['roles'], true)) {
                    $result[$path] = $entry;
                    break;
                }
            }
        }
        return $result;
    }
}
