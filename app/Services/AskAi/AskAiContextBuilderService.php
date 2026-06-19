<?php

namespace App\Services\AskAi;

use App\Models\AcceptedBidSummary;
use App\Models\AiFaqAnswer;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerTenantDnaProfile;
use App\Models\LandlordAgentAuction;
use App\Models\ListingCompatibilityScore;
use App\Models\PropertyDnaProfile;
use App\Models\PropertyLocationDna;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Services\AgentAi\Loaders\AgentPresetLoader;
use App\Services\AgentAi\Loaders\AgentProfileLoader;
use App\Services\Dna\PropertyIntelligenceProfileService;
use App\Services\LocationDna\LocationDnaIntelligenceContextService;
use App\Services\LocationDna\LocationDnaMarketingContextService;

/**
 * AskAiContextBuilderService — Phase 1 Read-Only Context Assembly
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * ROLE: Read-only context assembly layer for Ask AI (Phase 1).
 * Gathers approved structured data from existing intelligence models into a single
 * safe context object. This is the foundation that future Ask AI phases call before
 * generating any response.
 *
 * This service MUST NEVER:
 *   - Call any external LLM, language model, or external HTTP service.
 *   - Execute any database write (save, update, create, delete).
 *   - Infer, estimate, or invent values for missing data.
 *   - Recalculate, modify, or override any DNA score, compatibility rating,
 *     avatar value, or offer analysis result.
 *   - Create routes, controllers, Blade views, or database migrations.
 *   - Rank, sort, or recommend any listing, offer, buyer, or agent.
 *   - Reference or infer protected class characteristics.
 *   - Call PropertyIntelligenceProfileService::generate() — use buildPayloadReadOnly()
 *     instead; generate() persists location_intelligence_context as a side-effect.
 * ==================================================================================
 */
class AskAiContextBuilderService
{
    public const CONTEXT_VERSION = 'ASK_AI_CONTEXT_V1';

    private PropertyIntelligenceProfileService $propertyIntelligenceService;
    private LocationDnaIntelligenceContextService $locationDnaIntelligenceService;
    private LocationDnaMarketingContextService $locationDnaMarketingService;

    public function __construct(
        PropertyIntelligenceProfileService $propertyIntelligenceService,
        LocationDnaIntelligenceContextService $locationDnaIntelligenceService,
        LocationDnaMarketingContextService $locationDnaMarketingService
    ) {
        $this->propertyIntelligenceService    = $propertyIntelligenceService;
        $this->locationDnaIntelligenceService = $locationDnaIntelligenceService;
        $this->locationDnaMarketingService    = $locationDnaMarketingService;
    }

    /**
     * Truth Source Contract — canonical EAV or native source key for each context
     * key, per role.
     *
     * This constant is the single authoritative reference that ties Ask AI's context
     * assembly to the same data the public listing views render.  Every field
     * declared here must be populated using the specified source key(s) in
     * extractFactualFields().  Golden QA tests assert that DB values read through
     * these source keys match the values returned in the assembled context.
     *
     * Format:
     *   [ role => [ context_key => source_spec ] ]
     *
     * source_spec can be:
     *   'meta_key'             — single EAV meta key read via info()
     *   'native:column'        — native model column read via $listing->{column}
     *   ['key1','key2',...]    — cascade: first non-null wins (same as code logic)
     *
     * Source alignment guarantee: every entry's first element matches the key rendered
     * by the public listing view so conflict-detection tools read the UI-visible value.
     */
    public const CANONICAL_SOURCE_MAP = [
        // =====================================================================
        // SELLER
        // Source tables: seller_agent_auctions (native) + seller_agent_auction_metas (EAV)
        // =====================================================================
        'seller' => [
            // ── Core listing fields ───────────────────────────────────────────
            // description: offer-listing Livewire forms save via saveMeta('additional_details', ...)
            // into seller_agent_auction_metas; the native description column is used only for
            // agent-auction wizard rows. Context reads EAV first so the common offer-listing path
            // populates description correctly; fallback to native covers legacy agent-auction rows.
            'description'                    => ['additional_details', 'native:description'],
            'address'                        => 'native:address',
            'asking_price'                   => 'maximum_budget',
            'buy_now_price'                  => 'buy_now_price',
            'service_type'                   => 'service_type',
            'sold'                           => 'native:is_sold',
            'auction_length'                 => 'native:auction_length',
            // ── Bedroom / Bathroom / Size ─────────────────────────────────────
            'bedrooms'                       => ['bedrooms', 'native:bedroom_id', 'other_bedrooms'],
            'bathrooms'                      => ['bathrooms', 'native:bathroom_id', 'other_bathrooms'],
            'square_feet'                    => ['minimum_heated_square', 'heated_square_footage', 'heated_square'],
            'year_built'                     => 'year_built',
            // ── Pool / Garage / Carport ───────────────────────────────────────
            'pool'                           => 'pool_needed',
            'pool_type'                      => 'pool_type',
            'garage'                         => ['garage_needed', 'other_garage', 'other_garage_needed'],
            'garage_spaces'                  => 'garage_parking_spaces',
            'carport'                        => ['carport_needed', 'other_carport_needed'],
            // ── View / Lot ────────────────────────────────────────────────────
            // water_view: 'water_view' holds specific water-body types (MLS/Livewire path);
            // 'view_preference' holds scenic/legacy selections. Read water_view first.
            'water_view'                     => ['water_view', 'view_preference'],
            'lot_size'                       => ['total_acreage', 'min_acreage'],
            'total_acreage'                  => 'total_acreage',
            'lot_dimensions'                 => 'lot_dimensions',
            'zoning'                         => 'zoning',
            'waterfront'                     => 'waterfront',
            'water_access'                   => 'water_access',
            'waterfront_feet'                => 'waterfront_feet',
            // ── Home Warranty / HOA Approval ──────────────────────────────────
            'home_warranty_offered'          => 'home_warranty_offered',
            'association_approval_required'  => 'association_approval_required',
            // ── Interior / Structural ─────────────────────────────────────────
            'interior_features'              => 'interior_features',
            'appliances'                     => 'appliances',
            'roof_type'                      => 'roof_type',
            'exterior_construction'          => 'exterior_construction',
            'foundation'                     => 'foundation',
            'heating_and_fuel'               => 'heating_and_fuel',
            'heating_fuel'                   => 'heating_and_fuel',
            'air_conditioning'               => 'air_conditioning',
            // building_features: JSON multiselect shared between residential and commercial.
            // 'furnished' is derived from this same key (filtered to furnished-related values).
            'building_features'              => 'building_features',
            'furnished'                      => 'building_features',
            // ── Utilities ─────────────────────────────────────────────────────
            'utilities'                      => 'utilities',
            'water'                          => 'water',
            'water_source'                   => 'water',
            'sewer'                          => 'sewer',
            // ── Transaction / Occupancy ───────────────────────────────────────
            'sale_provision'                 => 'sale_provision',
            'offered_financing'              => 'offered_financing',
            'occupant_status'                => 'occupant_status',
            'closing_date'                   => 'target_closing_date',
            // ── HOA ───────────────────────────────────────────────────────────
            'hoa_association'                => 'has_hoa',
            'hoa_fee'                        => 'association_fee_amount',
            'hoa_payment_schedule'           => 'association_fee_frequency',
            'association_name'               => 'association_name',
            'hoa_name'                       => 'association_name',
            'association_fee_includes'       => 'association_fee_includes',
            // ── CDD / Special Assessments ─────────────────────────────────────
            'has_cdd'                        => 'has_cdd',
            'annual_cdd_fee'                 => 'annual_cdd_fee',
            'has_special_assessments'        => 'has_special_assessments',
            'additional_parcels'             => 'additional_parcels',
            'total_parcel_count'             => 'total_parcel_count',
            'special_assessment_amount'      => 'special_assessment_amount',
            'special_assessment_description' => 'special_assessment_description',
            // ── Pets / Restrictions ───────────────────────────────────────────
            'pets_allowed'                   => 'pets',
            'number_of_pets_allowed'         => 'number_of_pets',
            'max_pet_weight'                 => 'weight_of_pets',
            'pet_restrictions'               => 'pet_restrictions',
            'rental_restrictions'            => 'leasing_restrictions',
            // ── Flood Zone ────────────────────────────────────────────────────
            // flood_zone_code_other is the free-text fallback when user selects "Other".
            'flood_zone_code'                => ['flood_zone_code', 'flood_zone_code_other'],
            'flood_zone_panel'               => 'flood_zone_panel',
            'flood_zone_date'                => 'flood_zone_date',
            'flood_insurance_required'       => 'flood_insurance_required',
            // ── Tax / Legal ───────────────────────────────────────────────────
            'annual_property_taxes'          => 'annual_property_taxes',
            'parcel_id'                      => 'parcel_id',
            'tax_year'                       => 'tax_year',
            'legal_description'              => 'legal_description',
            // ── Commercial / Structural ───────────────────────────────────────
            'building_sqft'                  => 'total_square_feet',
            'ceiling_height'                 => 'ceiling_height',
            'parking_spaces'                 => 'garage_parking_spaces',
            'annual_noi'                     => 'minimum_annual_net_income',
            'price_per_sqft'                 => 'price_per_sqft',
            'existing_lease_type'            => 'existing_lease_type',
            'lease_expiration'               => 'lease_expiration',
            'lease_assignable'               => 'lease_assignable',
            // ── Income / Multifamily ──────────────────────────────────────────
            'property_items'                 => 'property_items',
            'total_units'                    => 'unit_number',
            'total_buildings'                => 'unit_buildings',
            'unit_mix_summary'               => 'unit_type_configurations',
            'gross_annual_income'            => 'gross_annual_income',
            'annual_operating_expenses'      => 'annual_operating_expenses',
            'minimum_annual_net_income'      => 'minimum_annual_net_income',
            'minimum_cap_rate'               => 'minimum_cap_rate',
            'annual_net_income'              => 'minimum_annual_net_income',
            'cap_rate'                       => 'minimum_cap_rate',
            'rent_roll_available'            => 'rent_roll_available',
            'operating_statement_available'  => 'operating_statement_available',
            'occupancy_requirement'          => ['assumable_occupancy_requirement', 'assumable_occupancy_other'],
            'income_requirement'             => 'monthly_income',
            // ── Seller credit (paired synthesis fields) ───────────────────────
            'seller_credit_offered'          => 'seller_contribution_credit_offered',
            'seller_credit_amount'           => 'seller_contribution_amount_details',
            // ── Vacant Land ───────────────────────────────────────────────────
            'current_adjacent_use'           => 'current_adjacent_use',
            'water_available'                => 'water_available',
            'sewer_available'                => 'sewer_available',
            'electric_available'             => 'electric_available',
            'gas_available'                  => 'gas_available',
            'telecom_available'              => 'telecom_available',
            'road_surface_type'              => 'road_surface_type',
            'front_footage'                  => 'front_footage',
            'number_of_wells'                => 'number_of_wells',
            'number_of_septics'              => 'number_of_septics',
            'fences'                         => 'fences',
            'vegetation'                     => 'vegetation',
            'buildable'                      => 'buildable',
            'easements'                      => 'easements',
            // ── Business Opportunity ──────────────────────────────────────────
            'business_type'                  => ['business_type', 'other_business_type'],
            'business_name'                  => 'business_name',
            'year_established'               => 'year_established',
            'annual_revenue'                 => 'annual_revenue',
            'gross_profit'                   => 'gross_profit',
            'sde_ebitda'                     => 'sde_ebitda',
            'inventory_value'                => 'inventory_value',
            'ffe_value'                      => 'ffe_value',
            'reason_for_sale'                => ['reason_for_sale', 'other_reason_for_sale'],
            'employee_count'                 => 'employee_count',
            'financial_statements_available' => 'financial_statements_available',
            'tax_returns_available'          => 'tax_returns_available',
            'nda_required'                   => 'nda_required',
            'business_location_leased'       => 'business_location_leased',
            'business_lease_monthly_rent'    => 'business_lease_monthly_rent',
            'business_lease_expiration'      => 'business_lease_expiration',
            'business_lease_renewal_options' => 'business_lease_renewal_options',
            'business_lease_assignable'      => 'business_lease_assignable',
            'business_lease_additional_terms'=> 'business_lease_additional_terms',
            'licenses'                       => 'licenses',
            'sale_includes'                  => 'sale_includes',
            'electrical_service'             => 'electrical_service',
            'business_assets'                => 'business_assets',
            // ── Shared Vacant Land + Business ─────────────────────────────────
            'current_use'                    => 'current_use',
            'road_frontage'                  => 'road_frontage',
            // ── Synthetic / governance ────────────────────────────────────────
            // disclosure_flags is a constant array set inline; not read from EAV.
            'disclosure_flags'               => 'synthetic:flood_zone_flag',
        ],

        // =====================================================================
        // BUYER
        // Source tables: buyer_agent_auctions (native) + buyer_agent_auction_metas (EAV)
        // =====================================================================
        'buyer' => [
            // ── Core ─────────────────────────────────────────────────────────
            'address'                        => 'native:address',
            'description'                    => 'native:additional_details',
            'max_price'                      => 'maximum_budget',
            // ── Size / Features ───────────────────────────────────────────────
            'bedrooms'                       => ['bedrooms', 'other_bedrooms'],
            'bathrooms'                      => ['bathrooms', 'other_bathrooms'],
            'square_feet'                    => ['minimum_heated_square', 'heated_square_footage', 'heated_square'],
            'pool'                           => 'pool_needed',
            'carport'                        => ['carport_needed', 'other_carport_needed'],
            'garage'                         => ['garage_needed', 'other_garage', 'other_garage_needed'],
            'garage_spaces'                  => 'garage_parking_spaces',
            // water_view: buyer form stores under 'view_preference' (not 'water_view').
            // Live-DB audit (June 2026) confirmed 'water_view' does not exist in
            // buyer_agent_auction_metas — the correct key is 'view_preference'.
            'water_view'                     => 'view_preference',
            // ── HOA / Pets ────────────────────────────────────────────────────
            'hoa_acceptable'                 => 'hoa_acceptance',
            'max_hoa_fee'                    => 'hoa_max_monthly_fee',
            'pets_allowed'                   => 'pets',
            'pets_detail'                    => 'type_of_pets',
            'pets_breed'                     => 'breed_of_pets',
            'pets_weight'                    => 'weight_of_pets',
            // ── Financing / Contingencies ─────────────────────────────────────
            'loan_pre_approved'              => 'pre_approved',
            // financing_type: 'financing_type' is the correct key (JSON multiselect).
            // 'offered_financing' is a legacy fallback only; confirmed always null for buyer rows.
            'financing_type'                 => ['financing_type', 'offered_financing'],
            'inspection_period'              => 'inspection_period_days',
            'closing_date'                   => 'target_closing_date',
            'inspection_contingency_buyer'   => 'inspection_contingency_buyer',
            'appraisal_contingency_buyer'    => 'appraisal_contingency_buyer',
            'financing_contingency_buyer'    => 'financing_contingency_buyer',
            // ── Search Criteria ───────────────────────────────────────────────
            'cities'                         => 'cities',
            'counties'                       => 'counties',
            // ── Buyer Criteria Expansion ──────────────────────────────────────
            'year_built_preference'          => 'year_built',
            'minimum_cap_rate'               => 'minimum_cap_rate',
            'number_of_units'                => 'number_of_unit',
            'commute_destination_zip'        => 'commute_destination_zip',
            'max_commute_minutes'            => 'max_commute_minutes',
            'commute_mode'                   => 'commute_mode',
            'flood_zone_tolerance'           => 'flood_zone_tolerance',
            'purchase_purpose'               => 'purchase_purpose',
            'monthly_income'                 => 'monthly_income',
            'number_of_occupants'            => 'number_occupant',
            // credit_score_range: EAV key is misspelled ('credit_scroe_rating') — matches form save key.
            'credit_score_range'             => 'credit_scroe_rating',
            'leasing_55_plus'                => 'leasing_55_plus',
            'non_negotiable_amenities'       => 'non_negotiable_amenities',
            'min_acreage'                    => 'min_acreage',
            'total_acreage'                  => 'total_acreage',
            'additional_preferences'         => 'preferance_details',
            'business_type_preference'       => 'business_type_selected',
        ],

        // =====================================================================
        // LANDLORD
        // Source tables: landlord_agent_auctions (native) + landlord_agent_auction_metas (EAV)
        // =====================================================================
        'landlord' => [
            // ── Core listing fields ───────────────────────────────────────────
            'description'                    => 'additional_details',
            'address'                        => 'native:address',
            'rent_amount'                    => ['desired_rental_amount', 'starting_rent', 'lease_now_price'],
            // ── Size / Physical ───────────────────────────────────────────────
            'bedrooms'                       => ['bedrooms', 'other_bedrooms'],
            'bathrooms'                      => ['bathrooms', 'other_bathrooms'],
            'square_feet'                    => ['minimum_heated_square', 'heated_square_footage', 'heated_square'],
            'year_built'                     => 'year_built',
            'unit_size'                      => 'unit_size',
            'number_of_units'                => 'number_of_unit',
            'property_zip'                   => 'property_zip',
            // ── Amenities / Interior ──────────────────────────────────────────
            'property_items'                 => 'property_items',
            'condition_prop'                 => ['condition_prop', 'other_property_condition'],
            'appliances'                     => 'appliances',
            'interior_features'              => 'interior_features',
            'building_features'              => 'building_features',
            // ── View ─────────────────────────────────────────────────────────
            // water_view and view are aliases backed by the same cascade.
            'water_view'                     => ['water_view', 'view_preference'],
            'view'                           => ['water_view', 'view_preference'],
            // ── Pet Policy ───────────────────────────────────────────────────
            // 'pets' declared first to match UI view priority ($str('pets') ?: $str('pet_policy')).
            // Live-DB audit (June 2026) confirmed 'pet_policy' is always empty; real data is in 'pets'.
            'pet_policy'                     => ['pets', 'pet_policy'],
            'pet_deposit_fee_rent'           => 'pet_deposit_fee_rent',
            'pet_max_weight_lbs'             => 'pet_max_weight_lbs',
            'pet_species_allowed'            => 'pet_species_allowed',
            // ── Utilities / Terms ─────────────────────────────────────────────
            'parking_terms'                  => 'parking_terms',
            // 'utilities' declared first (UI-view key) for conflict-detection reference;
            // context builder reads 'property_utilities' first as the primary source.
            'utilities'                      => ['utilities', 'property_utilities'],
            'smoking_policy'                 => 'smoking_policy',
            'subletting_policy'              => 'subletting_policy',
            'available_date'                 => 'available_date',
            // ── HOA ───────────────────────────────────────────────────────────
            'has_hoa'                        => 'has_hoa',
            'association_name'               => 'association_name',
            'association_fee_amount'         => 'association_fee_amount',
            'association_fee_frequency'      => 'association_fee_frequency',
            'association_amenities'          => 'association_amenities',
            // ── Lease Terms ───────────────────────────────────────────────────
            'annual_property_taxes'          => 'annual_property_taxes',
            'leasing_restrictions'           => 'leasing_restrictions',
            'lease_length'                   => ['min_lease_period', 'minimum_lease_period', 'desired_lease_length'],
            'renewal_option'                 => 'renewal_option_offered',
            'number_of_occupants'            => 'number_occupant',
            'additional_lease_terms'         => 'additional_landlord_lease_terms',
            'lease_terms'                    => 'terms_of_lease',
            'security_deposit_amount'        => 'security_deposit_amount',
            // Phase D4: 'terms_of_lease' duplicate removed — 'lease_terms' is the canonical
            // context key; both resolve to the same EAV source ('terms_of_lease' meta key).
            // Duplicate removed to prevent PHP silent-override (last-key-wins behaviour).
            'tenant_pays'                    => 'tenant_pays',
            'rent_includes'                  => 'rent_includes',
            'lease_amount_frequency'         => 'lease_amount_frequency',
            'first_month_rent_required'      => 'first_month_rent_required',
            'last_month_rent_required'       => 'last_month_rent_required',
            'total_move_in_funds_required'   => 'total_move_in_funds_required',
            // ── Structural / Water ────────────────────────────────────────────
            'lot_dimensions'                 => 'lot_dimensions',
            'zoning'                         => 'zoning',
            'waterfront'                     => 'waterfront',
            'water_access'                   => 'water_access',
            'roof_type'                      => 'roof_type',
            'exterior_construction'          => 'exterior_construction',
            'foundation'                     => 'foundation',
            'heating_fuel'                   => 'heating_fuel',
            'air_conditioning'               => 'air_conditioning',
            'water'                          => 'water',
            'sewer'                          => 'sewer',
            // ── Flood Zone ────────────────────────────────────────────────────
            'flood_zone_code'                => ['flood_zone_code', 'flood_zone_code_other'],
            'flood_zone_panel'               => 'flood_zone_panel',
            'flood_zone_date'                => 'flood_zone_date',
            'flood_insurance_required'       => 'flood_insurance_required',
            // ── Tax / Legal / Parcel ──────────────────────────────────────────
            'parcel_id'                      => 'parcel_id',
            'tax_year'                       => 'tax_year',
            'legal_description'              => 'legal_description',
            'additional_parcels'             => 'additional_parcels',
            'total_parcel_count'             => 'total_parcel_count',
            'additional_parcel_ids'          => 'additional_parcel_ids',
            // ── CDD / Special Assessments ─────────────────────────────────────
            'has_cdd'                        => 'has_cdd',
            'annual_cdd_fee'                 => 'annual_cdd_fee',
            'has_special_assessments'        => 'has_special_assessments',
            'special_assessment_amount'      => 'special_assessment_amount',
            'special_assessment_description' => 'special_assessment_description',
            // ── Commercial Lease Terms ────────────────────────────────────────
            'commercial_lease_type'          => ['commercial_lease_type', 'commercial_lease_type_other'],
            'cam_nnn_additional_rent_charges'=> 'cam_nnn_additional_rent_charges',
            'rent_escalation_terms'          => 'rent_escalation_terms',
            'tenant_improvement_buildout_terms' => 'tenant_improvement_buildout_terms',
            'permitted_use_restrictions'     => 'permitted_use_restrictions',
            'signage_rights'                 => 'signage_rights',
            'commercial_parking_terms'       => 'commercial_parking_terms',
            'personal_guarantee_requirement' => 'personal_guarantee_requirement',
            'commercial_approval_conditions' => 'commercial_approval_conditions',
            // ── Commercial Building Details ────────────────────────────────────
            'total_buildings'                => 'total_buildings',
            'total_units_on_property'        => 'total_units_on_property',
            'office_retail_sqft'             => 'office_retail_sqft',
            'flex_space_sqft'                => 'flex_space_sqft',
            'ceiling_height'                 => 'ceiling_height',
            'space_type'                     => 'space_type',
            'space_classification'           => 'space_classification',
            'space_features'                 => 'space_features',
            'number_of_restrooms'            => 'number_of_restrooms',
            'number_of_offices'              => 'number_of_offices',
            'number_of_conference_rooms'     => 'number_of_conference_rooms',
            'electrical_service'             => 'electrical_service',
            'road_surface_type'              => 'road_surface_type',
            'number_electric_meters'         => 'number_electric_meters',
            'number_water_meters'            => 'number_water_meters',
            'number_gas_meters'              => 'number_gas_meters',
            'building_hours'                 => 'building_hours',
            'access_24_7'                    => 'access_24_7',
            'zoning_allows'                  => 'zoning_allows',
            'neighboring_tenants'            => 'neighboring_tenants',
            'shared_amenities'               => 'shared_amenities',
            'sqft_heated_source'             => 'sqft_heated_source',
            // ── Additional Landlord Terms ─────────────────────────────────────
            'min_income_requirement'         => 'min_income_requirement',
            'll_maintenance_responsibility'  => 'll_maintenance_responsibility',
            'renewal_option_details'         => 'renewal_option_details',
            'landlord_approval_conditions'   => 'landlord_approval_conditions',
            'pet_deposit_amount'             => 'pet_deposit_amount',
            'pet_monthly_fee'                => 'pet_monthly_fee',
            'number_of_occupants_allowed'    => 'number_of_occupants_allowed',
            // ── Applicant Screening ───────────────────────────────────────────
            'min_credit_score'               => 'min_credit_score',
            'income_qualification_method'    => 'income_qualification_method',
            'employment_requirement'         => 'employment_requirement',
            'eviction_history_requirement'   => 'eviction_history_requirement',
            'bankruptcy_requirement'         => 'bankruptcy_requirement',
            // ── Estimated Utility Costs ───────────────────────────────────────
            'est_water_sewer_trash'          => 'est_water_sewer_trash',
            'est_electric'                   => 'est_electric',
            'est_internet'                   => 'est_internet',
            'est_cable'                      => 'est_cable',
            // ── Lease Restrictions / Policies ─────────────────────────────────
            'max_leases_per_year'            => 'max_leases_per_year',
            'additional_lease_restrictions'  => 'additional_lease_restrictions',
            'security_deposit_required'      => 'security_deposit_required',
            'leasing_55_plus'                => 'leasing_55_plus',
            // ── Lifestyle / Occupancy Rules ───────────────────────────────────
            'guests_allowed'                 => 'guests_allowed',
            'maintenance_by'                 => 'maintenance_by',
            'maintenance_response_time'      => 'maintenance_response_time',
            'common_areas_access'            => 'common_areas_access',
            'bathroom_facilities'            => 'bathroom_facilities',
            'room_size'                      => 'room_size',
            // ── Applicant Requirements (manual extractor — resolveOtherValue needed) ──
            // These fields require resolveOtherValue() to handle "Other" → custom text,
            // so they cannot be expressed as bare CANONICAL_SOURCE_MAP entries alone.
            // Declared here so extractFactualFields() does not fail §28A / §30B parity.
            'credit_score_flexibility'            => 'credit_score_flexibility',
            'pet_policy_requirement'              => ['pet_policy_requirement', 'custom_pet_policy_requirement'],
            'pet_restrictions'                    => 'pet_restrictions',
            'smoking_policy_requirement'          => ['smoking_policy_requirement', 'custom_smoking_policy_requirement'],
            'criminal_background_requirement'     => ['criminal_background_requirement', 'custom_criminal_background_requirement'],
            'reference_requirement'               => ['reference_requirement', 'custom_reference_requirement'],
            'employment_verification_requirement' => 'employment_verification_requirement',
            'income_verification_requirement'     => 'income_verification_requirement',
            'preferred_move_in_timeframe'         => ['preferred_move_in_timeframe', 'custom_preferred_move_in_timeframe'],
        ],

        // =====================================================================
        // TENANT
        // Source tables: tenant_agent_auctions (native) + tenant_agent_auction_metas (EAV)
        // =====================================================================
        'tenant' => [
            // max_rent: 'budget' holds the actual value; 'maximum_budget' is always null
            // for tenant listings (confirmed June 2026 live-DB audit).
            'max_rent'                       => ['budget', 'maximum_budget'],
            // ── Size / Features ───────────────────────────────────────────────
            'bedrooms'                       => ['bedrooms', 'other_bedrooms'],
            'bathrooms'                      => ['bathrooms', 'other_bathrooms'],
            // desired_lease_length: 'desired_lease_length' is the primary JSON multiselect;
            // 'lease_for' is a legacy fallback.
            'desired_lease_length'           => ['desired_lease_length', 'lease_for'],
            'property_items'                 => 'property_items',
            'appliances'                     => 'appliances',
            'condition_prop'                 => ['condition_prop', 'other_property_condition'],
            // ── Pet / Parking / Utilities ─────────────────────────────────────
            'pet_information'                => 'pet_information',
            'parking_needed'                 => 'parking_needed',
            'utilities'                      => 'utilities',
            'utility_preference'             => 'utility_preference',
            'tenant_pays'                    => 'tenant_pays',
            // ── Status / Household ────────────────────────────────────────────
            'current_status'                 => 'current_status',
            'number_of_occupants'            => 'number_of_occupants',
            'number_of_units'                => 'number_of_unit',
            // credit_score_range: 'credit_score_range' for offer-flow; 'credit_score' for agent-auction.
            'credit_score_range'             => ['credit_score_range', 'credit_score'],
            // monthly_income: 'monthly_income' primary; 'household_monthly_income' is legacy form key.
            'monthly_income'                 => ['monthly_income', 'household_monthly_income'],
            // ── Core ─────────────────────────────────────────────────────────
            'address'                        => 'native:address',
            'description'                    => 'additional_details',
            // ── Size / Features ───────────────────────────────────────────────
            'square_feet'                    => 'minimum_heated_square',
            'pool'                           => 'pool_needed',
            'pool_type'                      => 'pool_type',
            'garage'                         => ['garage_needed', 'other_garage_needed'],
            'garage_spaces'                  => 'garage_parking_spaces',
            'carport'                        => ['carport_needed', 'other_carport_needed'],
            // water_view: tenant form saves scenic/water view under 'view_preference'.
            'water_view'                     => 'view_preference',
            'non_negotiable_amenities'       => 'non_negotiable_amenities',
            'leasing_55_plus'                => 'leasing_55_plus',
            'total_acreage'                  => 'total_acreage',
            'min_acreage'                    => 'min_acreage',
            // ── Move-in / Security ────────────────────────────────────────────
            'security_deposit_budget'        => 'security_deposit_budget',
            'move_in_funds_available'        => 'move_in_funds_available',
            'first_month_rent_available'     => 'first_month_rent_available',
            'last_month_rent_available'      => 'last_month_rent_available',
            'move_in_date_earliest'          => 'move_in_date_earliest',
            'move_in_date_latest'            => 'move_in_date_latest',
            // ── Renewal / Conditions ──────────────────────────────────────────
            'renewal_option_requested'       => 'renewal_option_requested',
            'renewal_option_details'         => 'renewal_option_details',
            'tenant_conditions'              => 'tenant_conditions',
            'additional_tenant_lease_terms'  => 'additional_tenant_lease_terms',
            // ── Commercial Tenant ─────────────────────────────────────────────
            // Aliased to 'commercial_lease_type' (not '_preference') so Guard B
            // and LISTING_KEY_KEYWORD_MAP use the same key as the landlord role.
            'commercial_lease_type'          => 'commercial_lease_type_preference',
            'cam_nnn_preference'             => 'cam_nnn_preference',
            'rent_escalation_preference'     => 'rent_escalation_preference',
            'buildout_tenant_improvement_request' => 'buildout_tenant_improvement_request',
            'intended_business_use'          => 'intended_business_use',
            'signage_request'                => 'signage_request',
            'commercial_parking_access_needs' => 'commercial_parking_access_needs',
            'personal_guarantee_preference'  => 'personal_guarantee_preference',
            'commercial_approval_conditions' => 'commercial_approval_conditions',
            // ── Screening / Background ────────────────────────────────────────
            'prior_eviction'                 => 'prior_eviction',
            'prior_felony'                   => 'prior_felony',
            'smoking_preference'             => 'smoking_preference',
            'service_animal'                 => 'service_animal',
            'emotional_support_animal'       => 'emotional_support_animal',
            'accessibility_requirements'     => 'accessibility_requirements',
            'rental_purpose'                 => 'rental_purpose',
            // ── Commute / Lifestyle ───────────────────────────────────────────
            'commute_destination_zip'        => 'commute_destination_zip',
            'max_commute_minutes'            => 'max_commute_minutes',
            'commute_mode'                   => 'commute_mode',
            'maintenance_preference'         => 'maintenance_preference',
            'guests_allowed'                 => 'guests_allowed',
            // ── Location ──────────────────────────────────────────────────────
            'cities'                         => 'cities',
            'counties'                       => 'counties',
            'zip_codes'                      => 'zipCodes',
        ],

        // =====================================================================
        // AGENT PROFILE (5th role — not a listing; _sources covers listing fields only)
        // Source: users table + agent_default_profiles.profile_data JSON
        // All 47 public-safe fields from AgentProfileLoader::buildContent() are
        // documented here per Sprint 3 / B5 audit requirement.
        // Fields filtered by PRIVATE_KEYS (email, phone, compensation amounts) are
        // NOT listed — they never appear in context per governance Section 7.1.
        // =====================================================================
        'agent_profile' => [
            // Identity (from users table + profile_data)
            'agent_name'                            => 'users.first_name + users.last_name',
            'short_id'                              => 'native:users.short_id',
            'brokerage'                             => ['profile_data.brokerage', 'users.brokerage_attribute'],
            'license_no'                            => 'profile_data.license_no',
            'nar_id'                                => 'profile_data.nar_id',
            // Experience & Credentials
            'year_licensed'                         => 'profile_data.year_licensed',
            'years_experience'                      => 'profile_data.years_experience',
            'is_full_time'                          => 'profile_data.is_full_time',
            'transactions_last_12_months'           => 'profile_data.transactions_last_12_months',
            // Bio & Differentiators
            'bio'                                   => 'profile_data.bio',
            'awards_recognition'                    => 'profile_data.awards_recognition',
            'what_sets_you_apart'                   => 'profile_data.what_sets_you_apart',
            'why_hire_you'                          => 'profile_data.why_hire_you',
            // Reviews
            'review_1'                              => 'profile_data.review_1',
            'review_2'                              => 'profile_data.review_2',
            'review_3'                              => 'profile_data.review_3',
            'reviews_links'                         => 'profile_data.reviews_links',
            // Online Presence
            'website_link'                          => 'profile_data.website_link',
            'intro_video_url'                       => 'profile_data.intro_video_url',
            'presentation_link'                     => 'profile_data.presentation_link',
            'social_media'                          => 'profile_data.social_media',
            // Availability & Communication
            'availability_status'                   => 'profile_data.availability_status',
            'avg_response_time'                     => 'profile_data.avg_response_time',
            'communication_style'                   => 'profile_data.communication_style',
            'preferred_contact_method'              => 'profile_data.preferred_contact_method',
            'evenings_available'                    => 'profile_data.evenings_available',
            'weekends_available'                    => 'profile_data.weekends_available',
            // Geographic Coverage
            'cities_served'                         => 'profile_data.cities_served',
            'counties_served'                       => 'profile_data.counties_served',
            'neighborhoods_served'                  => 'profile_data.neighborhoods_served',
            'primary_areas_served'                  => 'profile_data.primary_areas_served',
            'areas_notes'                           => 'profile_data.areas_notes',
            // Services
            'services'                              => 'profile_data.services',
            'other_services'                        => 'profile_data.other_services',
            'marketing_plan'                        => 'profile_data.marketing_plan',
            // Fee Structure (type labels only — amounts excluded by PRIVATE_KEYS)
            'commission_structure'                  => 'profile_data.commission_structure',
            'commission_structure_type'             => 'profile_data.commission_structure_type',
            'purchase_fee_type'                     => 'profile_data.purchase_fee_type',
            'lease_fee_type'                        => 'profile_data.lease_fee_type',
            'retainer_fee_option'                   => 'profile_data.retainer_fee_option',
            'retainer_fee_application'              => 'profile_data.retainer_fee_application',
            'protection_period'                     => 'profile_data.protection_period',
            'early_termination_fee_option'          => 'profile_data.early_termination_fee_option',
            // Referral & Property Management Interest
            'interested_in_selling'                 => 'profile_data.interested_in_selling',
            'interested_in_selling_type'            => 'profile_data.interested_in_selling_type',
            'interested_in_property_management'     => 'profile_data.interested_in_property_management',
            'interested_in_property_management_fee' => 'profile_data.interested_in_property_management_fee',
        ],
    ];

    /**
     * Canonical listing type names and all accepted aliases.
     */
    private const TYPE_ALIASES = [
        'seller'                   => 'seller',
        'seller_agent_auction'     => 'seller',
        'property_auction'         => 'seller',
        'buyer'                    => 'buyer',
        'buyer_agent_auction'      => 'buyer',
        'buyer_criteria_auction'   => 'buyer',
        'landlord'                 => 'landlord',
        'landlord_agent_auction'   => 'landlord',
        'landlord_auction'         => 'landlord',
        'tenant'                   => 'tenant',
        'tenant_agent_auction'     => 'tenant',
        'tenant_criteria_auction'  => 'tenant',
    ];

    /**
     * Assemble a lightweight chip context for the listing view suggested-questions chips.
     *
     * Returns only the two keys consumed by AskAiSuggestedQuestionsService::forListing():
     *   'listing'     — array produced by extractListingFields() for the given listing.
     *   'faq_answers' — array produced by buildFaqAnswers() for the given listing.
     *
     * No DNA, no compatibility, no location intelligence, no OpenAI calls are made.
     * This method is deliberately cheap so it can be called during a normal page render.
     *
     * When any exception is thrown (e.g. listing has no `id` property, EAV meta
     * unavailable), an empty array is returned so forListing() falls back to the
     * static pool without surfacing a page error.
     *
     * @param  object  $listing        Resolved listing model instance (already loaded by the controller).
     * @param  string  $canonicalType  One of: 'seller', 'buyer', 'landlord', 'tenant'.
     * @return array{listing: array, faq_answers: array}|array{}
     */
    public function buildChipContext(object $listing, string $canonicalType): array
    {
        try {
            $canonical    = $this->normalizeListingType($canonicalType);
            $listingId    = (int) ($listing->id ?? 0);
            $fields       = $this->extractListingFields($listing, $canonical, $listingId);
            $faqAnswers   = $this->buildFaqAnswers($listing, $canonical);
            $agentProfile = $this->buildAgentProfile($canonical, $listing);
            $agentPresets = $this->buildAgentPresets($canonical, $listing);

            return [
                'listing'       => $fields,
                'faq_answers'   => $faqAnswers,
                'agent_profile' => $agentProfile,
                'agent_presets' => $agentPresets,
            ];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Assemble a read-only Ask AI context object for the given listing.
     *
     * Output contract — always returns exactly these top-level keys:
     *   success               bool         — true when listing was found and assembly ran; false otherwise
     *   listing_type          string       — canonical or aliased type as supplied by the caller
     *   listing_id            int          — listing primary key as supplied by the caller
     *   context_version       string       — always 'ASK_AI_CONTEXT_V1'
     *   status                string       — 'assembled' | 'partial' | 'not_found' | 'failed'
     *   listing               array|null
     *   property_intelligence array|null
     *   location_intelligence array|null
     *   buyer_avatar          array|null
     *   tenant_avatar         array|null
     *   compatibility         array|null
     *   offer_analysis        array|null
     *   missing_sources       string[]
     *   warnings              string[]
     *   source_versions       array
     *   assembled_at          string       — ISO-8601 metadata timestamp; represents when this
     *                                        context object was assembled, NOT AI generation time,
     *                                        score computation time, or listing update time.
     *   error                 string|null  — null on non-failed paths; error message on 'failed'
     *
     * @param  string      $listingType  Canonical or aliased listing type string.
     * @param  int         $listingId    Primary key of the listing record.
     * @param  array|null  $options      Optional: demand_listing_type/demand_listing_id or
     *                                  supply_listing_type/supply_listing_id for compatibility.
     * @return array
     */
    public function buildForListing(string $listingType, int $listingId, ?array $options = []): array
    {
        try {
            $canonical = $this->normalizeListingType($listingType);
            $listing   = $this->findListing($canonical, $listingId);

            if ($listing === null) {
                return $this->buildNotFoundResponse($listingType, $listingId);
            }

            $missingSources = [];
            $warnings       = [];

            $listingFields = $this->extractListingFields($listing, $canonical, $listingId);
            $faqAnswers    = $this->buildFaqAnswers($listing, $canonical);

            $propertyIntelligence = null;
            if (in_array($canonical, ['seller', 'landlord'], true)) {
                $propertyIntelligence = $this->buildPropertyIntelligence(
                    $canonical, $listingId, $missingSources
                );
            }

            $locationIntelligence = $this->buildLocationIntelligence(
                $canonical, $listingId, $missingSources, $warnings
            );

            $buyerAvatar = null;
            if ($canonical === 'buyer') {
                $buyerAvatar = $this->buildBuyerAvatar($listingId, $missingSources);
            }

            $tenantAvatar = null;
            if ($canonical === 'tenant') {
                $tenantAvatar = $this->buildTenantAvatar($listingId, $missingSources);
            }

            $compatibility = null;
            if ($this->hasPairOptions($options ?? [])) {
                $compatibility = $this->buildCompatibility(
                    $canonical, $listingId, $options ?? [], $warnings
                );
            }

            $offerAnalysis = $this->buildOfferAnalysis($canonical, $listingId);

            $agentProfile = $this->buildAgentProfile($canonical, $listing);
            $agentPresets = $this->buildAgentPresets($canonical, $listing);

            $sourceVersions = $this->buildSourceVersions(
                $propertyIntelligence, $locationIntelligence,
                $buyerAvatar, $tenantAvatar, $compatibility,
                $agentProfile, $agentPresets
            );

            $status = $this->determineStatus(
                $canonical, $propertyIntelligence, $locationIntelligence,
                $buyerAvatar, $tenantAvatar, $missingSources
            );

            return [
                'success'               => true,
                'listing_type'          => $canonical,
                'listing_id'            => $listingId,
                'context_version'       => self::CONTEXT_VERSION,
                'status'                => $status,
                // _sources: authoritative EAV / native source key per context field.
                // Used by golden QA tests (AskAiGoldenQaSuiteTest) to assert that
                // context values match what the public listing UI renders.
                '_sources'              => self::CANONICAL_SOURCE_MAP[$canonical] ?? [],
                'listing'               => $listingFields,
                'faq_answers'           => $faqAnswers,
                'property_intelligence' => $propertyIntelligence,
                'location_intelligence' => $locationIntelligence,
                'buyer_avatar'          => $buyerAvatar,
                'tenant_avatar'         => $tenantAvatar,
                'compatibility'         => $compatibility,
                'offer_analysis'        => $offerAnalysis,
                'agent_profile'         => $agentProfile,
                'agent_presets'         => $agentPresets,
                'missing_sources'       => $missingSources,
                'warnings'              => $warnings,
                'source_versions'       => $sourceVersions,
                'assembled_at'          => now()->toISOString(),
                'error'                 => null,
            ];
        } catch (\Throwable $e) {
            return $this->buildFailedResponse($listingType, $listingId, $e->getMessage());
        }
    }

    // =========================================================================
    // Listing Resolution
    // =========================================================================

    /**
     * Normalize an input listing type string to one of the four canonical values.
     * Returns the input unchanged when no alias is found (will resolve as not_found).
     */
    protected function normalizeListingType(string $listingType): string
    {
        return self::TYPE_ALIASES[strtolower($listingType)] ?? $listingType;
    }

    /**
     * Resolve the primary listing model for the given canonical type and ID.
     * Returns null when no record exists.
     *
     * Model-to-table mapping:
     *   seller   → SellerAgentAuction   → seller_agent_auctions
     *   buyer    → BuyerAgentAuction    → buyer_agent_auctions
     *   landlord → LandlordAgentAuction → landlord_agent_auctions
     *   tenant   → TenantAgentAuction   → tenant_agent_auctions
     */
    protected function findListing(string $canonicalType, int $listingId): ?object
    {
        return match ($canonicalType) {
            'seller'   => SellerAgentAuction::find($listingId),
            'buyer'    => BuyerAgentAuction::find($listingId),
            'landlord' => LandlordAgentAuction::find($listingId),
            'tenant'   => TenantAgentAuction::find($listingId),
            default    => null,
        };
    }

    /**
     * Extract the approved listing fields from the resolved model.
     *
     * Returns the base metadata fields (present for all roles) merged with
     * role-specific public-factual fields sourced from native columns and EAV meta.
     *
     * Base fields (all roles): listing_type, listing_id, listing_title, city, state,
     * county, property_type, listing_status, created_at, updated_at.
     *
     * Factual fields (role-specific): bedrooms, bathrooms, asking_price, rent_amount,
     * lease_length, pets_allowed, hoa_fee, pool, parking_spaces, square_feet,
     * year_built, and additional role-appropriate fields. All non-synthetic
     * CANONICAL_SOURCE_MAP fields are resolved via AskAiSourceResolver; fields
     * requiring JSON decoding, Other-fallback resolution, or derived computation
     * are subsequently overridden by extractManualFields().
     */
    protected function extractListingFields(object $listing, string $canonicalType, int $listingId): array
    {
        $infoGet = function (string $key) use ($listing): ?string {
            if (method_exists($listing, 'info')) {
                $val = $listing->info($key);
                return ($val !== false && $val !== null) ? (string) $val : null;
            }
            return null;
        };

        $nativeGet = function (string $key) use ($listing): ?string {
            return isset($listing->{$key}) ? (string) $listing->{$key} : null;
        };

        $resolve = function (string $key) use ($infoGet, $nativeGet): ?string {
            return $nativeGet($key) ?? $infoGet($key);
        };

        $base = [
            'listing_type'   => $canonicalType,
            'listing_id'     => $listingId,
            'listing_title'  => $infoGet('listing_title') ?? $infoGet('title') ?? $nativeGet('title'),
            'city'           => $resolve('city'),
            'state'          => $resolve('state'),
            'county'         => $resolve('county'),
            'property_type'  => $infoGet('property_type') ?? $nativeGet('property_type'),
            'listing_status' => $nativeGet('is_approved') !== null
                ? ($listing->is_approved ? 'approved' : 'pending')
                : ($infoGet('status') ?? null),
            'created_at'     => isset($listing->created_at) ? (string) $listing->created_at : null,
            'updated_at'     => isset($listing->updated_at) ? (string) $listing->updated_at : null,
        ];

        // Step 1: Resolver loop — uses CANONICAL_SOURCE_MAP as the single source
        // of truth for field→source mapping. Produces raw values for all
        // non-synthetic entries without re-declaring sources a second time in code.
        $resolver  = new AskAiSourceResolver();
        $sourceMap = self::CANONICAL_SOURCE_MAP[$canonicalType] ?? [];
        $resolved  = [];
        foreach ($sourceMap as $field => $source) {
            if (is_string($source) && str_starts_with($source, 'synthetic:')) {
                // Excluded: not a direct lookup — handled by extractManualFields() below.
                continue;
            }
            $resolved[$field] = $resolver->resolveField($field, $source, $infoGet, $nativeGet);
        }

        // Step 1b (seller only): Strip property-type-conditional fields that must be
        // ABSENT (not merely null) for non-matching property types.
        // CANONICAL_SOURCE_MAP lists Vacant Land, Business, and shared VL+Business fields
        // unconditionally so the resolver loop resolves them for every seller listing.
        // These keys must be removed from $resolved when property_type does not match
        // the gate condition, preserving the old extractFactualFields conditional-block
        // behavior (assertArrayNotHasKey tests verify absence, not just null).
        if ($canonicalType === 'seller') {
            $propType = $infoGet('property_type');
            if ($propType !== 'Vacant Land') {
                foreach ([
                    'current_adjacent_use', 'water_available', 'sewer_available',
                    'electric_available', 'gas_available', 'telecom_available',
                    'road_surface_type', 'front_footage', 'number_of_wells',
                    'number_of_septics', 'fences', 'vegetation', 'buildable', 'easements',
                ] as $k) {
                    unset($resolved[$k]);
                }
            }
            if ($propType !== 'Business') {
                foreach ([
                    'business_type', 'business_name', 'year_established', 'annual_revenue',
                    'gross_profit', 'sde_ebitda', 'inventory_value', 'ffe_value',
                    'reason_for_sale', 'employee_count', 'financial_statements_available',
                    'tax_returns_available', 'nda_required', 'business_location_leased',
                    'business_lease_monthly_rent', 'business_lease_expiration',
                    'business_lease_renewal_options', 'business_lease_assignable',
                    'business_lease_additional_terms', 'licenses', 'sale_includes',
                    'electrical_service', 'business_assets',
                ] as $k) {
                    unset($resolved[$k]);
                }
            }
            if (!in_array($propType, ['Vacant Land', 'Business'], true)) {
                unset($resolved['current_use'], $resolved['road_frontage']);
            }
        }

        // Step 2: Manual overrides — fields requiring transformation logic that
        // cannot be expressed as a CANONICAL_SOURCE_MAP source entry:
        //   • decodeJsonField()             — JSON multi-select arrays → comma string
        //   • resolveOtherValue()           — "Other" placeholder → free-text sibling
        //   • summarizeUnitConfigurations() — unit-mix JSON → readable summary
        //   • disclosure_flags              — governance constant, not from EAV
        //   • furnished                     — derived from building_features subset
        //   • Conditional VL/Business blocks — property-type-gated field groups
        // Values produced here replace the raw resolver results for the same keys.
        $manualFields = $this->extractManualFields($listing, $canonicalType, $infoGet, $nativeGet);

        $factual = array_merge($resolved, $manualFields);
        return array_merge($base, $factual);
    }

    /**
     * Returns manual field overrides for fields whose values cannot be expressed
     * as a bare CANONICAL_SOURCE_MAP source entry (decodeJsonField, resolveOtherValue,
     * derived computation, governance constants, conditional property-type blocks).
     * The resolver loop in extractListingFields() provides raw values for all
     * other CANONICAL_SOURCE_MAP fields; values returned here take precedence.
     *
     * @param  object    $listing       The resolved listing model instance.
     * @param  string    $canonicalType One of 'seller', 'buyer', 'landlord', 'tenant'.
     * @param  callable  $infoGet       EAV meta accessor: info($key) → ?string.
     * @param  callable  $nativeGet     Native column accessor: $listing->{$key} → ?string.
     * @return array
     */
    private function extractManualFields(
        object $listing,
        string $canonicalType,
        callable $infoGet,
        callable $nativeGet
    ): array {
        return match ($canonicalType) {
            'seller'   => $this->extractSellerManualFields($infoGet, $nativeGet),
            'buyer'    => $this->extractBuyerManualFields($infoGet, $nativeGet),
            'landlord' => $this->extractLandlordManualFields($infoGet),
            'tenant'   => $this->extractTenantManualFields($infoGet),
            default    => [],
        };
    }

    /**
     * Seller manual field overrides.
     *
     * Covers fields that require transformation logic not expressible as a source-map entry:
     *   – decodeJsonField(): JSON-encoded multiselects (pool_type, water_view, appliances, etc.)
     *   – resolveOtherValue(): bedrooms/bathrooms/carport/garage/flood_zone_code with "Other" fallback
     *   – furnished: derived by filtering building_features JSON for furnished-related tokens
     *   – disclosure_flags: governance constant (excluded from resolver — not an EAV source)
     *   – unit_mix_summary: summarizeUnitConfigurations()
     *   – Vacant Land, Business, shared VL+Business conditional blocks
     *
     * Indexed by the same context key names used in CANONICAL_SOURCE_MAP.
     */
    private function extractSellerManualFields(callable $infoGet, callable $nativeGet): array
    {
        return array_merge(
            [
                // ── native column: sold status ────────────────────────────────────
                // 'sold' maps to the native boolean column 'is_sold' whose name differs
                // from the context key. Listed explicitly here so the source reference
                // for 'is_sold' is visible to static analysis (§28B phantom-source guard).
                'sold'                 => $nativeGet('is_sold'),

                // ── resolveOtherValue: bedrooms / bathrooms ────────────────────────
                // Native bedroom_id / bathroom_id FK columns serve as fallback when
                // the EAV key is absent (legacy agent-auction wizard rows).
                'bedrooms'             => $this->resolveOtherValue(
                                             $infoGet('bedrooms') ?? $nativeGet('bedroom_id'),
                                             $infoGet,
                                             'other_bedrooms'
                                         ),
                'bathrooms'            => $this->resolveOtherValue(
                                             $infoGet('bathrooms') ?? $nativeGet('bathroom_id'),
                                             $infoGet,
                                             'other_bathrooms'
                                         ),

                // ── resolveOtherValue: parking ────────────────────────────────────
                'carport'              => $this->resolveOtherValue(
                                             $infoGet('carport_needed'),
                                             $infoGet,
                                             'other_carport_needed'
                                         ),
                'garage'               => $this->resolveOtherValue(
                                             $infoGet('garage_needed'),
                                             $infoGet,
                                             'other_garage',
                                             'other_garage_needed'
                                         ),

                // ── decodeJsonField: pool ─────────────────────────────────────────
                'pool_type'            => $this->decodeJsonField($infoGet('pool_type')),

                // ── decodeJsonField + cascade: water view ─────────────────────────
                // water_view: the offer-listing wizard (and MLS import) saves specific water
                // body type selections under the 'water_view' meta key.  Older listings
                // and manual scenic-preference entries live under 'view_preference'.
                // Read 'water_view' first (present on all Livewire/MLS-created records);
                // fall back to 'view_preference' for legacy rows where 'water_view' is absent.
                'water_view'           => $this->decodeJsonField($infoGet('water_view'))
                                             ?: $this->decodeJsonField($infoGet('view_preference')),

                // ── decodeJsonField: waterfront ───────────────────────────────────
                'water_access'         => $this->decodeJsonField($infoGet('water_access')),

                // ── decodeJsonField + other_* cascade: interior ──────────────────
                // Other-loss fix (Phase C): when a multi-select field includes "Other",
                // decodeJsonField() strips the literal token and reads only the checked
                // items. The companion other_* EAV key holds any free-text the user typed.
                // Appending it here ensures custom descriptions are preserved in context.
                'interior_features'    => implode(', ', array_filter([
                                              $this->decodeJsonField($infoGet('interior_features')),
                                              $infoGet('other_interior_features'),
                                          ])) ?: null,
                'appliances'           => implode(', ', array_filter([
                                              $this->decodeJsonField($infoGet('appliances')),
                                              $infoGet('other_appliances'),
                                          ])) ?: null,

                // ── decodeJsonField + other_* cascade: structural ─────────────────
                'roof_type'            => implode(', ', array_filter([
                                              $this->decodeJsonField($infoGet('roof_type')),
                                              $infoGet('other_roof_type'),
                                          ])) ?: null,
                'exterior_construction' => implode(', ', array_filter([
                                              $this->decodeJsonField($infoGet('exterior_construction')),
                                              $infoGet('other_exterior_construction'),
                                          ])) ?: null,
                'foundation'           => implode(', ', array_filter([
                                              $this->decodeJsonField($infoGet('foundation')),
                                              $infoGet('other_foundation'),
                                          ])) ?: null,
                'heating_and_fuel'     => implode(', ', array_filter([
                                              $this->decodeJsonField($infoGet('heating_and_fuel')),
                                              $infoGet('other_heating_and_fuel'),
                                          ])) ?: null,
                // heating_fuel: alternate output key for the same meta; retained for
                // commercial/income context where 'heating_fuel' is the contract name.
                'heating_fuel'         => implode(', ', array_filter([
                                              $this->decodeJsonField($infoGet('heating_and_fuel')),
                                              $infoGet('other_heating_and_fuel'),
                                          ])) ?: null,
                'air_conditioning'     => implode(', ', array_filter([
                                              $this->decodeJsonField($infoGet('air_conditioning')),
                                              $infoGet('other_air_conditioning'),
                                          ])) ?: null,

                // ── decodeJsonField + other_* cascade: utilities ──────────────────
                'water'                => implode(', ', array_filter([
                                              $this->decodeJsonField($infoGet('water')),
                                              $infoGet('other_water'),
                                          ])) ?: null,
                // water_source: alternate output key for the same meta; used in
                // commercial and income-property context.
                'water_source'         => implode(', ', array_filter([
                                              $this->decodeJsonField($infoGet('water')),
                                              $infoGet('other_water'),
                                          ])) ?: null,
                'sewer'                => implode(', ', array_filter([
                                              $this->decodeJsonField($infoGet('sewer')),
                                              $infoGet('other_sewer'),
                                          ])) ?: null,
                'utilities'            => implode(', ', array_filter([
                                              $this->decodeJsonField($infoGet('utilities')),
                                              $infoGet('other_utilities'),
                                          ])) ?: null,

                // ── decodeJsonField: transaction ──────────────────────────────────
                'sale_provision'       => $this->decodeJsonField($infoGet('sale_provision')),
                'offered_financing'    => $this->decodeJsonField($infoGet('offered_financing')),

                // ── derived: furnished ────────────────────────────────────────────
                // Excluded from resolver: requires filtering the building_features JSON
                // array to extract furnished-related tokens — not a simple source lookup.
                // Seller stores furnishing state inside building_features (e.g. "Furnished",
                // "Turnkey"). Only furnished-related values are surfaced to the AI.
                'furnished'            => (static function (callable $ig): ?string {
                    $raw = $ig('building_features');
                    if (!$raw) {
                        return null;
                    }
                    $arr = is_string($raw) ? (json_decode($raw, true) ?? []) : (array) $raw;
                    $f   = array_filter($arr, fn ($v) => preg_match('/furnish|turnkey|negotiable|partial/i', (string) $v));
                    return implode(', ', $f) ?: null;
                })($infoGet),

                // ── decodeJsonField: HOA ──────────────────────────────────────────
                'association_fee_includes' => $this->decodeJsonField($infoGet('association_fee_includes')),

                // ── resolveOtherValue: flood zone ─────────────────────────────────
                // flood_zone_code may be "Other" when the user selected a non-standard
                // zone code; resolve to the free-text sibling 'flood_zone_code_other'
                // so the AI never returns the bare placeholder string "Other".
                'flood_zone_code'      => $this->resolveOtherValue(
                                             $infoGet('flood_zone_code'),
                                             $infoGet,
                                             'flood_zone_code_other'
                                         ),

                // ── governance constant: disclosure_flags ─────────────────────────
                // Excluded from resolver: constant, not an EAV source lookup.
                // flood_zone => true does NOT mean the property is in a flood zone — the
                // flood_zone_code scalar carries that data. This flag tells the AI that
                // flood-zone data is present in this context and must be handled with
                // the flood-zone disclosure template. Always set for seller listings.
                'disclosure_flags'     => ['flood_zone' => true],

                // ── decodeJsonField + other_* cascade: building ──────────────────
                // building_features: surfaces for ALL seller property types so
                // commercial-sale and income/multifamily listings can answer questions
                // about building amenities via Guard B without an OpenAI call.
                'building_features'    => implode(', ', array_filter([
                                              $this->decodeJsonField($infoGet('building_features')),
                                              $infoGet('other_building_features'),
                                          ])) ?: null,

                // ── decodeJsonField / summarize: income/multifamily ───────────────
                'property_items'       => $this->decodeJsonField($infoGet('property_items')),
                // unit_mix_summary: excluded from resolver — requires summarizeUnitConfigurations()
                // to convert unit_type_configurations JSON into a readable summary string.
                'unit_mix_summary'     => $this->summarizeUnitConfigurations($infoGet('unit_type_configurations')),

                // ── resolveOtherValue: occupancy ──────────────────────────────────
                'occupancy_requirement' => $this->resolveOtherValue(
                                               $infoGet('assumable_occupancy_requirement'),
                                               $infoGet,
                                               'assumable_occupancy_other'
                                           ),
            ],

            // ── Vacant Land-specific fields ──────────────────────────────────────
            // Only populated when property_type === 'Vacant Land'. All array-valued
            // meta keys are decoded from JSON and returned as comma-separated strings
            // via decodeJsonField() for prompt-friendly consumption.
            ($infoGet('property_type') === 'Vacant Land') ? [
                'current_adjacent_use' => implode(', ', array_filter([
                                             $this->decodeJsonField($infoGet('current_adjacent_use')),
                                             $infoGet('other_current_adjacent_use'),
                                         ])) ?: null,
                'water_available'      => $infoGet('water_available'),
                'sewer_available'      => $infoGet('sewer_available'),
                'electric_available'   => $infoGet('electric_available'),
                'gas_available'        => $infoGet('gas_available'),
                'telecom_available'    => $infoGet('telecom_available'),
                'road_surface_type'    => $this->decodeJsonField($infoGet('road_surface_type')),
                'front_footage'        => $infoGet('front_footage'),
                'number_of_wells'      => $infoGet('number_of_wells'),
                'number_of_septics'    => $infoGet('number_of_septics'),
                'fences'               => implode(', ', array_filter([
                                             $this->decodeJsonField($infoGet('fences')),
                                             $infoGet('other_fences'),
                                         ])) ?: null,
                'vegetation'           => implode(', ', array_filter([
                                             $this->decodeJsonField($infoGet('vegetation')),
                                             $infoGet('other_vegetation'),
                                         ])) ?: null,
                'buildable'            => $infoGet('buildable'),
                'easements'            => $this->decodeJsonField($infoGet('easements')),
            ] : [],

            // ── Business Opportunity fields ───────────────────────────────────────
            // Only surfaced when property_type === 'Business'. All stored via
            // saveMeta() in seller_agent_auction_metas.
            ($infoGet('property_type') === 'Business') ? [
                'business_type'                   => $this->resolveOtherValue(
                                                         $infoGet('business_type'),
                                                         $infoGet,
                                                         'other_business_type'
                                                     ),
                'business_name'                   => $infoGet('business_name'),
                'year_established'                => $infoGet('year_established'),
                'annual_revenue'                  => $infoGet('annual_revenue'),
                'gross_profit'                    => $infoGet('gross_profit'),
                'sde_ebitda'                      => $infoGet('sde_ebitda'),
                'inventory_value'                 => $infoGet('inventory_value'),
                'ffe_value'                       => $infoGet('ffe_value'),
                'reason_for_sale'                 => $this->resolveOtherValue(
                                                         $infoGet('reason_for_sale'),
                                                         $infoGet,
                                                         'other_reason_for_sale'
                                                     ),
                'employee_count'                  => $infoGet('employee_count'),
                'financial_statements_available'  => $infoGet('financial_statements_available'),
                'tax_returns_available'           => $infoGet('tax_returns_available'),
                'nda_required'                    => $infoGet('nda_required'),
                'business_location_leased'        => $infoGet('business_location_leased'),
                'business_lease_monthly_rent'     => $infoGet('business_lease_monthly_rent'),
                'business_lease_expiration'       => $infoGet('business_lease_expiration'),
                'business_lease_renewal_options'  => $infoGet('business_lease_renewal_options'),
                'business_lease_assignable'       => $infoGet('business_lease_assignable'),
                'business_lease_additional_terms' => $infoGet('business_lease_additional_terms'),
                'licenses'                        => $this->decodeJsonField($infoGet('licenses')),
                'sale_includes'                   => implode(', ', array_filter([
                                                      $this->decodeJsonField($infoGet('sale_includes')),
                                                      $infoGet('other_sale_includes'),
                                                  ])) ?: null,
                // building_features: moved to the general seller commercial/structural
                // block (unconditional) so commercial-sale listings also get it.
                'electrical_service'              => $this->decodeJsonField($infoGet('electrical_service')),
                'business_assets'                 => $this->decodeJsonField($infoGet('business_assets')),
            ] : [],

            // ── Shared Vacant Land + Business fields ──────────────────────────────
            // current_use and road_frontage apply to both Vacant Land and Business
            // Opportunity listings. Kept in a single conditional block so each key
            // appears exactly once in source (prevents PHP silent-override bugs and
            // source-level duplicate detection failures).
            (in_array($infoGet('property_type'), ['Vacant Land', 'Business'], true)) ? [
                'current_use'   => implode(', ', array_filter([
                                      $this->decodeJsonField($infoGet('current_use')),
                                      $infoGet('other_current_use'),
                                  ])) ?: null,
                'road_frontage' => $this->decodeJsonField($infoGet('road_frontage')),
            ] : []
        );
    }

    /**
     * Buyer manual field overrides.
     *
     * Covers fields that require transformation logic not expressible as a source-map entry:
     *   – decodeJsonField(): water_view, financing_type (cascade), cities, counties
     *   – resolveOtherValue(): bedrooms, bathrooms, carport, garage with "Other" fallback
     */
    private function extractBuyerManualFields(callable $infoGet, callable $nativeGet): array
    {
        return [
            // ── resolveOtherValue: bedrooms / bathrooms ────────────────────────
            'bedrooms'       => $this->resolveOtherValue(
                                    $infoGet('bedrooms'),
                                    $infoGet,
                                    'other_bedrooms'
                                ),
            'bathrooms'      => $this->resolveOtherValue(
                                    $infoGet('bathrooms'),
                                    $infoGet,
                                    'other_bathrooms'
                                ),

            // ── resolveOtherValue: parking ────────────────────────────────────
            'carport'        => $this->resolveOtherValue(
                                    $infoGet('carport_needed'),
                                    $infoGet,
                                    'other_carport_needed'
                                ),
            'garage'         => $this->resolveOtherValue(
                                    $infoGet('garage_needed'),
                                    $infoGet,
                                    'other_garage',
                                    'other_garage_needed'
                                ),

            // ── decodeJsonField: water view ───────────────────────────────────
            // view / water_view: the form stores scenic/water view selections as a
            // JSON-encoded multiselect under the 'view_preference' meta key.
            // Live-DB audit (June 2026) confirmed 'water_view' does not exist in
            // buyer_agent_auction_metas — the actual key is 'view_preference'.
            'water_view'     => $this->decodeJsonField($infoGet('view_preference')),

            // ── decodeJsonField + cascade: financing type ─────────────────────
            // financing_type: the form saves buyer financing selections under 'financing_type'
            // (JSON multiselect). Legacy/agent-wizard rows may use 'offered_financing'.
            // Live-DB audit (June 2026) confirmed 'offered_financing' is always null for
            // buyer agent auction listings — the correct key is 'financing_type'.
            'financing_type' => $this->decodeJsonField(
                                    $infoGet('financing_type') ?? $infoGet('offered_financing')
                                ),

            // ── decodeJsonField: location multiselects ────────────────────────
            // cities / counties: stored as JSON arrays via saveMeta('cities', json_encode($request->cities))
            // in BuyerCriteriaAuctionController. TenantAgentAuctionController uses the same keys.
            // decodeJsonField() converts '["Tampa","Orlando"]' → 'Tampa, Orlando'.
            'cities'         => $this->decodeJsonField($infoGet('cities')),
            'counties'       => $this->decodeJsonField($infoGet('counties')),
        ];
    }

    /**
     * Landlord manual field overrides.
     *
     * Covers fields that require transformation logic not expressible as a source-map entry:
     *   – decodeJsonField(): property_items, appliances, building_features, water_view/view,
     *     pet_species_allowed, association_amenities, lease_terms, terms_of_lease, tenant_pays,
     *     rent_includes, heating_fuel, air_conditioning, water, sewer, space_type,
     *     space_classification, electrical_service, road_surface_type, water_access,
     *     interior_features, roof_type, exterior_construction, foundation
     *   – resolveOtherValue(): bedrooms, bathrooms, condition_prop, flood_zone_code,
     *     commercial_lease_type, lease_length (complex: resolveOtherValue + decodeJsonField cascade)
     *   – utilities: mixed cascade (raw infoGet ?: decodeJsonField on alternate key)
     */
    private function extractLandlordManualFields(callable $infoGet): array
    {
        return [
            // ── resolveOtherValue: bedrooms / bathrooms ────────────────────────
            'bedrooms'                  => $this->resolveOtherValue(
                                              $infoGet('bedrooms'),
                                              $infoGet,
                                              'other_bedrooms'
                                          ),
            'bathrooms'                 => $this->resolveOtherValue(
                                              $infoGet('bathrooms'),
                                              $infoGet,
                                              'other_bathrooms'
                                          ),

            // ── decodeJsonField: property condition ───────────────────────────
            'property_items'            => $this->decodeJsonField($infoGet('property_items')),
            'condition_prop'            => $this->resolveOtherValue(
                                              $infoGet('condition_prop'),
                                              $infoGet,
                                              'other_property_condition'
                                              ),

            // ── decodeJsonField: interior / structural ────────────────────────
            'appliances'                => $this->decodeJsonField($infoGet('appliances')),
            // water_view / view: the offer-listing wizard (and MLS import) saves specific
            // water-body-type selections under the 'water_view' meta key; older/legacy
            // records and scenic preferences live under 'view_preference'.
            // Read 'water_view' first with 'view_preference' as fallback for legacy rows.
            'water_view'                => $this->decodeJsonField($infoGet('water_view'))
                                              ?: $this->decodeJsonField($infoGet('view_preference')),
            'view'                      => $this->decodeJsonField($infoGet('water_view'))
                                              ?: $this->decodeJsonField($infoGet('view_preference')),

            // ── decodeJsonField: pets ─────────────────────────────────────────
            'pet_species_allowed'       => $this->decodeJsonField($infoGet('pet_species_allowed')),

            // ── mixed cascade: utilities ──────────────────────────────────────
            // utilities: offer-listing forms save scalar data under 'utilities' (the key
            // rendered by the public view at offer-listing/landlord/view.blade.php line 1289)
            // and JSON multiselect data under 'property_utilities'.  Agent-auction wizard
            // rows save only 'property_utilities'.  Context reads 'utilities' first so it
            // matches the UI-visible value on offer-listing rows; falls back to
            // 'property_utilities' for agent-auction rows where 'utilities' is always empty.
            // Note: uses ?: (not ??) because infoGet() returns '' for missing EAV rows, and
            // '' is truthy for ?? but falsy for ?:, which is the intended "absent" behavior.
            // CANONICAL_SOURCE_MAP mirrors this order: ['utilities','property_utilities'].
            // Excluded from resolver: second cascade key requires decodeJsonField() — not a
            // simple source lookup.
            'utilities'                 => $infoGet('utilities')
                                              ?: $this->decodeJsonField($infoGet('property_utilities')),

            // ── decodeJsonField: HOA ──────────────────────────────────────────
            'association_amenities'     => $this->decodeJsonField($infoGet('association_amenities')),

            // ── complex: lease_length ─────────────────────────────────────────
            // lease_length: 'min_lease_period' may be "Other"; resolve to the free-text
            // sibling 'min_lease_period_other' in that case. Also check the alternate
            // 'desired_lease_length' multiselect for cases where the primary is absent.
            // Excluded from resolver: requires resolveOtherValue() on the primary value
            // AND decodeJsonField() on the JSON-array fallback.
            'lease_length'              => $this->resolveOtherValue(
                                              $infoGet('min_lease_period') ?? $infoGet('minimum_lease_period'),
                                              $infoGet,
                                              'min_lease_period_other'
                                          ) ?? $this->decodeJsonField($infoGet('desired_lease_length')),

            // ── decodeJsonField: lease terms ──────────────────────────────────
            // lease_terms: alias for terms_of_lease — keeps context key in sync with
            // listingFieldRegistry() which uses config_key 'lease_terms'.
            'lease_terms'               => $this->decodeJsonField($infoGet('terms_of_lease')),

            // ── resolveOtherValue: commercial lease type ──────────────────────
            'commercial_lease_type'     => $this->resolveOtherValue(
                                               $infoGet('commercial_lease_type'),
                                               $infoGet,
                                               'commercial_lease_type_other'
                                           ),

            // ── decodeJsonField + other_* cascade: structural / waterfront ───────
            // Other-loss fix (Phase C): same cascade pattern as seller — companion
            // other_* EAV keys carry free-text when "Other" was selected in the form.
            'water_access'              => $this->decodeJsonField($infoGet('water_access')),
            'interior_features'         => implode(', ', array_filter([
                                               $this->decodeJsonField($infoGet('interior_features')),
                                               $infoGet('other_interior_features'),
                                           ])) ?: null,
            'roof_type'                 => implode(', ', array_filter([
                                               $this->decodeJsonField($infoGet('roof_type')),
                                               $infoGet('other_roof_type'),
                                           ])) ?: null,
            'exterior_construction'     => implode(', ', array_filter([
                                               $this->decodeJsonField($infoGet('exterior_construction')),
                                               $infoGet('other_exterior_construction'),
                                           ])) ?: null,
            'foundation'                => implode(', ', array_filter([
                                               $this->decodeJsonField($infoGet('foundation')),
                                               $infoGet('other_foundation'),
                                           ])) ?: null,

            // ── resolveOtherValue: flood zone ─────────────────────────────────
            // flood_zone_code may be "Other" when the user selected a non-standard
            // zone code; resolve to the free-text sibling 'flood_zone_code_other'
            // so the AI never returns the bare placeholder string "Other".
            'flood_zone_code'           => $this->resolveOtherValue(
                                               $infoGet('flood_zone_code'),
                                               $infoGet,
                                               'flood_zone_code_other'
                                           ),

            // ── decodeJsonField + other_* cascade: lease financial terms ──────
            // Phase D4: 'terms_of_lease' duplicate removed — context key is 'lease_terms'
            // (set above via extractLandlordManualFields). CANONICAL_SOURCE_MAP line 374
            // entry was also removed; only 'lease_terms' → 'terms_of_lease' remains.
            'tenant_pays'               => $this->decodeJsonField($infoGet('tenant_pays')),
            'rent_includes'             => $this->decodeJsonField($infoGet('rent_includes')),
            'heating_fuel'              => implode(', ', array_filter([
                                               $this->decodeJsonField($infoGet('heating_fuel')),
                                               $infoGet('other_heating_fuel'),
                                           ])) ?: null,
            'air_conditioning'          => implode(', ', array_filter([
                                               $this->decodeJsonField($infoGet('air_conditioning')),
                                               $infoGet('other_air_conditioning'),
                                           ])) ?: null,
            'water'                     => implode(', ', array_filter([
                                               $this->decodeJsonField($infoGet('water')),
                                               $infoGet('other_water'),
                                           ])) ?: null,
            'sewer'                     => implode(', ', array_filter([
                                               $this->decodeJsonField($infoGet('sewer')),
                                               $infoGet('other_sewer'),
                                           ])) ?: null,

            // ── decodeJsonField + other_* cascade: commercial building ────────
            'space_type'                => $this->decodeJsonField($infoGet('space_type')),
            'space_classification'      => $this->decodeJsonField($infoGet('space_classification')),
            'electrical_service'        => $this->decodeJsonField($infoGet('electrical_service')),
            'building_features'         => implode(', ', array_filter([
                                               $this->decodeJsonField($infoGet('building_features')),
                                               $infoGet('other_building_features'),
                                           ])) ?: null,
            'road_surface_type'         => $this->decodeJsonField($infoGet('road_surface_type')),

            // ── Applicant Requirements: new fields (Step 6) ───────────────────
            // Scalar EAV keys — resolveOtherValue resolves "Other" → custom text.
            'credit_score_flexibility'         => $infoGet('credit_score_flexibility') ?: null,
            'pet_policy_requirement'           => $this->resolveOtherValue(
                                                      $infoGet('pet_policy_requirement'),
                                                      $infoGet,
                                                      'custom_pet_policy_requirement'
                                                  ),
            'pet_restrictions'                 => $infoGet('pet_restrictions') ?: null,
            'smoking_policy_requirement'       => $this->resolveOtherValue(
                                                      $infoGet('smoking_policy_requirement'),
                                                      $infoGet,
                                                      'custom_smoking_policy_requirement'
                                                  ),
            'criminal_background_requirement'  => $this->resolveOtherValue(
                                                      $infoGet('criminal_background_requirement'),
                                                      $infoGet,
                                                      'custom_criminal_background_requirement'
                                                  ),
            'reference_requirement'            => $this->resolveOtherValue(
                                                      $infoGet('reference_requirement'),
                                                      $infoGet,
                                                      'custom_reference_requirement'
                                                  ),
            'employment_verification_requirement' => $infoGet('employment_verification_requirement') ?: null,
            'income_verification_requirement'     => $infoGet('income_verification_requirement') ?: null,
            'preferred_move_in_timeframe'      => $this->resolveOtherValue(
                                                      $infoGet('preferred_move_in_timeframe'),
                                                      $infoGet,
                                                      'custom_preferred_move_in_timeframe'
                                                  ),
        ];
    }

    /**
     * Tenant manual field overrides.
     *
     * Covers fields that require transformation logic not expressible as a source-map entry:
     *   – decodeJsonField(): desired_lease_length (cascade), property_items, appliances, tenant_pays
     *   – resolveOtherValue(): bedrooms, bathrooms, condition_prop with "Other" fallback
     */
    private function extractTenantManualFields(callable $infoGet): array
    {
        return [
            // ── resolveOtherValue: bedrooms / bathrooms ────────────────────────
            'bedrooms'             => $this->resolveOtherValue(
                                         $infoGet('bedrooms'),
                                         $infoGet,
                                         'other_bedrooms'
                                     ),
            'bathrooms'            => $this->resolveOtherValue(
                                         $infoGet('bathrooms'),
                                         $infoGet,
                                         'other_bathrooms'
                                     ),

            // ── decodeJsonField + cascade: desired lease length ────────────────
            // desired_lease_length: stored under 'desired_lease_length' (JSON multiselect)
            // or 'lease_for' depending on the form path. Live-DB audit confirmed
            // 'tenant_desired_lease_length' is always empty — wrong key.
            // Excluded from resolver: requires decodeJsonField() on both cascade keys.
            'desired_lease_length' => $this->decodeJsonField($infoGet('desired_lease_length'))
                                          ?? $this->decodeJsonField($infoGet('lease_for')),

            // ── decodeJsonField: property items / appliances ───────────────────
            'property_items'       => $this->decodeJsonField($infoGet('property_items')),
            'appliances'           => $this->decodeJsonField($infoGet('appliances')),

            // ── resolveOtherValue: property condition ──────────────────────────
            'condition_prop'       => $this->resolveOtherValue(
                                         $infoGet('condition_prop'),
                                         $infoGet,
                                         'other_property_condition'
                                     ),

            // ── decodeJsonField: tenant payments ───────────────────────────────
            'tenant_pays'          => $this->decodeJsonField($infoGet('tenant_pays')),

            // ── decodeJsonField: Phase 2 new fields ───────────────────────────
            // water_view: saved as JSON multiselect under 'view_preference' for tenant.
            'water_view'           => $this->decodeJsonField($infoGet('view_preference')),
            // pool_type: JSON multiselect of pool type preferences.
            'pool_type'            => $this->decodeJsonField($infoGet('pool_type')),
            // cities / counties / zip_codes: JSON arrays of preferred locations.
            'cities'               => $this->decodeJsonField($infoGet('cities')),
            'counties'             => $this->decodeJsonField($infoGet('counties')),
            'zip_codes'            => $this->decodeJsonField($infoGet('zipCodes')),
        ];
    }

    /**
     * Resolve a field value that may contain a placeholder string so that the
     * AI prompt never receives a bare sentinel.
     *
     * Behaviour by normalized primary value:
     *
     *   "other" | "see remarks" | "see private remarks" | "per remarks"
     *     → Attempt each $fallbackKey in order; return the first non-empty
     *       value.  Return null when all fallbacks are empty/null.
     *
     *   "tbd" | "t.b.d." | "n/a" | "na" | "none" | "unknown" |
     *   "not applicable" | "not available"
     *     → Return null immediately (no fallback possible; value is absent by
     *       definition).
     *
     *   any other value (real data)
     *     → Return $primaryValue unchanged.
     *
     * @param  string|null $primaryValue   Raw field value from EAV / native column.
     * @param  callable    $infoGet        Closure that resolves a meta key to its value.
     * @param  string      ...$fallbackKeys Meta keys to try when primary is "Other" / "See Remarks".
     * @return string|null
     */
    private function resolveOtherValue(?string $primaryValue, callable $infoGet, string ...$fallbackKeys): ?string
    {
        if ($primaryValue === null || $primaryValue === '') {
            return null;
        }

        $normalized = strtolower(trim($primaryValue));

        // Placeholders that have no meaningful fallback — the field is blank by intent.
        if (in_array($normalized, ['tbd', 't.b.d.', 'n/a', 'na', 'none', 'unknown', 'not applicable', 'not available'], true)) {
            return null;
        }

        // "Other" and "See Remarks" variants — try supplied fallback keys.
        if (in_array($normalized, ['other', 'see remarks', 'see private remarks', 'per remarks'], true)) {
            foreach ($fallbackKeys as $key) {
                $val = $infoGet($key);
                if ($val !== null && $val !== '' && $val !== false) {
                    return (string) $val;
                }
            }
            return null;
        }

        return $primaryValue;
    }

    /**
     * Summarize a `unit_type_configurations` JSON value into a human-readable string
     * for the Ask AI prompt layer.
     *
     * Each entry in the JSON array may contain: unit_type, number_of_units,
     * beds_unit, baths_unit, sqft_heated, expected_rent, number_occupied,
     * garage_spaces, carport_spaces, other_spaces, unit_type_description.
     *
     * Returns null when the value is empty or contains no valid rows.
     *
     * @param  string|null $json  Raw JSON string from the unit_type_configurations meta key.
     * @return string|null        E.g. "2× 2BR/1BA at $1,200/mo, 4× Studio at $850/mo"
     */
    private function summarizeUnitConfigurations(?string $json): ?string
    {
        if ($json === null || $json === '') {
            return null;
        }

        $rows = json_decode($json, true);
        if (!is_array($rows)) {
            return null;
        }

        $parts = [];
        foreach ($rows as $uc) {
            if (!is_array($uc)) {
                continue;
            }

            $count     = isset($uc['number_of_units']) && $uc['number_of_units'] !== '' ? (int) $uc['number_of_units'] : null;
            $beds      = isset($uc['beds_unit'])   && $uc['beds_unit']   !== '' ? $uc['beds_unit']   : null;
            $baths     = isset($uc['baths_unit'])  && $uc['baths_unit']  !== '' ? $uc['baths_unit']  : null;
            $unitType  = isset($uc['unit_type'])   && $uc['unit_type']   !== '' ? $uc['unit_type']   : null;
            $rent      = isset($uc['expected_rent']) && $uc['expected_rent'] !== '' ? (float) str_replace(',', '', (string) $uc['expected_rent']) : null;

            if ($count === null && $beds === null && $unitType === null) {
                continue;
            }

            $label = '';
            if ($beds !== null && $baths !== null) {
                $label = "{$beds}BR/{$baths}BA";
            } elseif ($unitType !== null) {
                $label = $unitType;
            } elseif ($beds !== null) {
                $label = "{$beds}BR";
            }

            $prefix = $count !== null ? "{$count}×" : '';
            $suffix = $rent !== null ? ' at $' . number_format($rent) . '/mo' : '';
            $part   = trim("{$prefix} {$label}{$suffix}");

            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return !empty($parts) ? implode(', ', $parts) : null;
    }

    /**
     * Detect whether an Ask AI context value conflicts with the corresponding
     * UI-visible value for the same field.
     *
     * Used by golden QA tests to assert that the value Ask AI surfaces to users
     * matches what the public listing view renders.  Both sides are compared as
     * normalised lower-case trimmed strings; null and '' are treated as equivalent.
     *
     * Returns an array:
     *   'conflict'       bool    — true when the two sides disagree
     *   'context_value'  mixed   — the value from the assembled Ask AI context
     *   'ui_value'       mixed   — the value from the public listing view / DB query
     *   'canonical_key'  string  — the context key being compared (e.g. 'asking_price')
     *
     * @param  string $canonicalKey   Context key name, for traceability.
     * @param  mixed  $contextValue   Value from $context['listing'][$canonicalKey].
     * @param  mixed  $uiValue        Value rendered by the public listing view.
     * @return array
     */
    public static function conflictDetect(
        string $canonicalKey,
        mixed $contextValue,
        mixed $uiValue
    ): array {
        $normalize = static function (mixed $v): string {
            if ($v === null || $v === '') {
                return '';
            }
            $str = strtolower(trim((string) $v));
            // Treat empty JSON arrays / objects stored in EAV as absent.
            // The context builder's decodeJsonField() returns null for '[]';
            // the raw DB value is '[]' — both represent "no data".
            if ($str === '[]' || $str === '{}') {
                return '';
            }
            return $str;
        };

        $ctx = $normalize($contextValue);
        $ui  = $normalize($uiValue);

        // Both absent — no conflict.
        if ($ctx === '' && $ui === '') {
            return [
                'conflict'      => false,
                'context_value' => $contextValue,
                'ui_value'      => $uiValue,
                'canonical_key' => $canonicalKey,
            ];
        }

        return [
            'conflict'      => ($ctx !== $ui),
            'context_value' => $contextValue,
            'ui_value'      => $uiValue,
            'canonical_key' => $canonicalKey,
        ];
    }

    /**
     * Decode a JSON meta value to a comma-separated string for prompt consumption.
     *
     * Many EAV meta fields store multi-select arrays as JSON (e.g. appliances,
     * pet_species_allowed, tenant_pays). This helper decodes them to a flat,
     * human-readable string. When the value is already a plain string or null,
     * it is returned as-is.
     *
     * OUTPUT FORMAT CONTRACT (established in Phase 1):
     * JSON arrays are decoded to a comma-separated plain string, e.g.:
     *   '["Washer","Dryer","Dishwasher"]'  →  'Washer, Dryer, Dishwasher'
     *   '["Pool","Gym"]'                   →  'Pool, Gym'
     * This string format is intentional — it is prompt-friendly and avoids
     * embedding PHP arrays or raw JSON brackets in the AI context payload.
     * All Ask AI tests assert this string shape (not a PHP array).
     *
     * @param  string|null $value  Raw meta value (JSON string or plain string).
     * @return string|null
     */
    private function decodeJsonField(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            // Strip the literal token "Other" (case-insensitive) from multi-select
            // JSON arrays.  When "Other" appears as an element alongside real values it
            // means the user selected a custom-entry option but the custom text itself is
            // stored in a sibling meta key (handled by resolveOtherValue for single-select
            // fields).  For multi-select arrays the literal "Other" token is never
            // meaningful to the AI and must be removed to avoid the "Other leak" pattern.
            $items = array_filter(
                array_map('strval', $decoded),
                static fn ($v) => $v !== '' && strtolower(trim($v)) !== 'other'
            );
            return !empty($items) ? implode(', ', array_values($items)) : null;
        }

        return $value;
    }

    /**
     * Resolve a financing_id FK to its human-readable label from the financings table.
     *
     * Returns null when the id is absent, zero, or the DB record cannot be found.
     * A try/catch guards against environments where the table may not be present
     * (e.g. unit-test runs without a database connection).
     *
     * @param  string|null $financingIdRaw  Raw string value of the financing_id column.
     * @return string|null
     */
    protected function resolveFinancingType(?string $financingIdRaw): ?string
    {
        if ($financingIdRaw === null || $financingIdRaw === '' || $financingIdRaw === '0') {
            return null;
        }

        try {
            $name = \Illuminate\Support\Facades\DB::table('financings')
                ->where('id', (int) $financingIdRaw)
                ->value('name');
            return $name !== null ? (string) $name : null;
        } catch (\Throwable) {
            return null;
        }
    }

    // =========================================================================
    // Property Intelligence Context (seller and landlord only)
    //
    // READ-ONLY: calls PropertyIntelligenceProfileService::buildPayloadReadOnly()
    // which derives all approved intelligence fields from the persisted profile
    // WITHOUT calling save(). The caller (generate()) is the only path that writes.
    //
    // Approved fields returned:
    //   property_strengths, property_highlights, property_positioning,
    //   property_target_audiences, property_personality_tags, property_story,
    //   location_intelligence_context (from persisted column, not re-fetched),
    //   property_intelligence_version, source_profile_id, source_profile_version,
    //   source_profile_computed_at.
    // =========================================================================

    /**
     * Resolve the latest active PropertyDnaProfile for a listing.
     */
    protected function findPropertyDnaProfile(string $canonicalType, int $listingId): ?PropertyDnaProfile
    {
        return PropertyDnaProfile::where('listing_type', $canonicalType)
            ->where('listing_id', $listingId)
            ->whereNull('archived_at')
            ->latest('computed_at')
            ->first();
    }

    /**
     * Assemble the property intelligence context section.
     * Returns null and appends 'property_intelligence' to $missingSources on failure.
     *
     * Calls PropertyIntelligenceProfileService::buildPayloadReadOnly() — no DB writes.
     */
    protected function buildPropertyIntelligence(
        string $canonicalType,
        int $listingId,
        array &$missingSources
    ): ?array {
        $profile = $this->findPropertyDnaProfile($canonicalType, $listingId);

        if ($profile === null) {
            $missingSources[] = 'property_intelligence';
            return null;
        }

        $payload = $this->propertyIntelligenceService->buildPayloadReadOnly($profile);

        if (!($payload['success'] ?? false)) {
            $missingSources[] = 'property_intelligence';
            return null;
        }

        return [
            'property_strengths'            => $payload['property_strengths'],
            'property_highlights'           => $payload['property_highlights'],
            'property_positioning'          => $payload['property_positioning'],
            'property_target_audiences'     => $payload['property_target_audiences'],
            'property_personality_tags'     => $payload['property_personality_tags'],
            'property_story'                => $payload['property_story'],
            'location_intelligence_context' => $payload['location_intelligence_context'] ?? null,
            'property_intelligence_version' => $payload['property_intelligence_version'],
            'source_profile_id'             => $profile->id,
            'source_profile_version'        => $profile->version ?? null,
            'source_profile_computed_at'    => isset($profile->computed_at)
                ? (string) $profile->computed_at
                : null,
        ];
    }

    // =========================================================================
    // FAQ Answers Context (all listing types)
    // =========================================================================

    /**
     * Build the faq_answers map for the listing.
     *
     * Resolution order:
     *   1. Inline JSON stored in the native `listing_ai_faq` column (tenant) or
     *      the `listing_ai_faq` EAV meta key (seller, buyer, landlord).
     *      Expected shape: {"question_key": "answer text", ...}
     *   2. Fallback: rows in `ai_faq_answers` matching listing_type + listing_id.
     *
     * Each entry in the returned array is an enriched object shape:
     *   config_key            — the original question key
     *   answer_text           — the answer text
     *   question_label        — human-readable question label (from config, or null)
     *   question_group        — group/category the question belongs to (from config, or null)
     *   intelligence_category — snake_case category derived from question_group (or null)
     *
     * Returns an empty array when no FAQ answers are available. Exceptions are
     * caught and silenced so a missing or malformed FAQ record never interrupts
     * context assembly.
     *
     * This method always returns the enriched object shape. The prompt builder's
     * sanitizeFaqAnswers() forwards only the four LLM-safe fields and also accepts
     * legacy raw-string entries for robustness when context is assembled from external
     * or cached sources.
     *
     * @param  object $listing       Resolved listing model instance.
     * @param  string $canonicalType One of 'seller', 'buyer', 'landlord', 'tenant'.
     * @return array<string, array{config_key: string, answer_text: string, question_label: string|null, question_group: string|null, intelligence_category: string|null}>
     */
    protected function buildFaqAnswers(object $listing, string $canonicalType): array
    {
        try {
            $raw = null;

            if ($canonicalType === 'tenant') {
                // Try native listing_ai_faq column first (legacy tenant_criteria_auctions has it).
                // Live TenantAgentAuction stores FAQ as EAV meta — fall back to info() when absent.
                $col = $listing->listing_ai_faq ?? null;
                if ($col !== null) {
                    $raw = is_array($col) ? $col : json_decode((string) $col, true);
                }
                if ($raw === null && method_exists($listing, 'info')) {
                    $meta = $listing->info('listing_ai_faq');
                    if ($meta !== null && $meta !== false && $meta !== '') {
                        $raw = is_array($meta) ? $meta : json_decode((string) $meta, true);
                    }
                }
            } else {
                // All other roles store the FAQ as EAV meta
                if (method_exists($listing, 'info')) {
                    $meta = $listing->info('listing_ai_faq');
                    if ($meta !== null && $meta !== false && $meta !== '') {
                        $raw = is_array($meta) ? $meta : json_decode((string) $meta, true);
                    }
                }
            }

            $answers = [];

            if (is_array($raw)) {
                $configIndex = AskAiFaqEnrichmentService::buildConfigIndex($canonicalType);

                foreach ($raw as $qKey => $answerText) {
                    $qKey = (string) $qKey;
                    if ($answerText !== null && $answerText !== '' && $answerText !== false) {
                        $meta = $configIndex[$qKey] ?? [
                            'question_group'        => null,
                            'question_label'        => null,
                            'intelligence_category' => null,
                        ];
                        $answers[$qKey] = [
                            'config_key'            => $qKey,
                            'answer_text'           => (string) $answerText,
                            'question_label'        => $meta['question_label'],
                            'question_group'        => $meta['question_group'],
                            'intelligence_category' => $meta['intelligence_category'],
                        ];
                    }
                }
            }

            // Fallback: query ai_faq_answers table when no inline answers were found
            if (empty($answers)) {
                $dbRows = AiFaqAnswer::where('listing_type', $canonicalType)
                    ->where('listing_id', $listing->id)
                    ->get();

                foreach ($dbRows as $row) {
                    $text = $row->answer_text ?? null;
                    if (!empty($text)) {
                        $normalized    = is_array($row->answer_normalized) ? $row->answer_normalized : [];
                        $qKey          = (string) $row->question_key;
                        $answers[$qKey] = [
                            'config_key'            => $normalized['config_key'] ?? $qKey,
                            'answer_text'           => (string) $text,
                            'question_label'        => $normalized['question_label'] ?? null,
                            'question_group'        => $row->question_group ?? null,
                            'intelligence_category' => $row->intelligence_category ?? null,
                        ];
                    }
                }
            }

            return $answers;
        } catch (\Throwable) {
            return [];
        }
    }

    // =========================================================================
    // Location Intelligence Context (all listing types)
    //
    // Assembles three layers of location data:
    //   1. lifestyle_json sub-fields (scores, categories, narrative, version)
    //      sourced directly from PropertyLocationDna.
    //   2. Structured POI/amenity data (nearest_highlights, thematic blocks,
    //      available_categories, missing_categories) from
    //      LocationDnaIntelligenceContextService — merged when status=available.
    //   3. Marketing-framed thematic context (marketing_context sub-key) from
    //      LocationDnaMarketingContextService — merged when status=available.
    //
    // When the intelligence or marketing services return non-available status,
    // a warning is appended to $warnings. The overall location_intelligence
    // section is still returned (from lifestyle_json) — missing POI data is
    // NOT added to $missingSources because it is optional for all question types.
    // =========================================================================

    /**
     * Resolve the latest PropertyLocationDna for a listing.
     */
    protected function findPropertyLocationDna(string $canonicalType, int $listingId): ?PropertyLocationDna
    {
        return PropertyLocationDna::where('listing_type', $canonicalType)
            ->where('listing_id', $listingId)
            ->latest('generated_at')
            ->first();
    }

    /**
     * Assemble the location intelligence context section.
     * Returns null and appends 'location_intelligence' to $missingSources when no DNA record exists.
     *
     * lifestyle_scores, lifestyle_categories, and location_narrative are extracted
     * from sub-keys within lifestyle_json when available.
     * lifestyle_version is extracted from lifestyle_json['version'] when present.
     *
     * When LocationDnaIntelligenceContextService returns status=available, the
     * following keys are merged into the returned array:
     *   nearest_highlights, available_categories, missing_categories,
     *   coastal_features, daily_convenience, outdoor_recreation, transportation.
     *
     * When LocationDnaMarketingContextService returns status=available, the
     * marketing_context sub-key is added containing the four thematic blocks
     * plus available/missing categories in marketing framing.
     *
     * Non-available status from either service appends a warning to $warnings
     * but does not affect $missingSources (both are optional for all question types).
     */
    protected function buildLocationIntelligence(
        string $canonicalType,
        int $listingId,
        array &$missingSources,
        array &$warnings
    ): ?array {
        $locationDna = $this->findPropertyLocationDna($canonicalType, $listingId);

        if ($locationDna === null) {
            $missingSources[] = 'location_intelligence';
            return null;
        }

        $lifestyleJson = $locationDna->lifestyle_json;
        $lifestyleArr  = is_array($lifestyleJson) ? $lifestyleJson : [];

        $context = [
            'lifestyle_json'       => $lifestyleJson,
            'lifestyle_scores'     => $lifestyleArr['scores'] ?? null,
            'lifestyle_categories' => $lifestyleArr['categories'] ?? null,
            'location_narrative'   => $lifestyleArr['narrative'] ?? null,
            'lifestyle_version'    => $lifestyleArr['version'] ?? null,
            'geocode_status'       => $locationDna->geocode_status ?? null,
            'generated_at'         => isset($locationDna->generated_at)
                ? (string) $locationDna->generated_at
                : null,
        ];

        $intelligenceResult = $this->locationDnaIntelligenceService->getForListing($canonicalType, $listingId);

        if ($intelligenceResult['status'] === 'available') {
            $lic = $intelligenceResult['location_intelligence_context'];
            $context['nearest_highlights']   = $lic['nearest_highlights'] ?? null;
            $context['available_categories'] = $lic['available_categories'] ?? null;
            $context['missing_categories']   = $lic['missing_categories'] ?? null;
            $context['coastal_features']     = $lic['coastal_features'] ?? null;
            $context['daily_convenience']    = $lic['daily_convenience'] ?? null;
            $context['outdoor_recreation']   = $lic['outdoor_recreation'] ?? null;
            $context['transportation']       = $lic['transportation'] ?? null;
        } else {
            $warnings[] = 'location_intelligence_context not available: '
                . ($intelligenceResult['error'] ?? $intelligenceResult['status']);
        }

        $marketingResult = $this->locationDnaMarketingService->getForListing($canonicalType, $listingId);

        if ($marketingResult['status'] === 'available') {
            $context['marketing_context'] = $marketingResult['marketing_location_context'];
        } else {
            $warnings[] = 'location_marketing_context not available: '
                . ($marketingResult['error'] ?? $marketingResult['status']);
        }

        return $context;
    }

    // =========================================================================
    // Buyer Avatar Context (buyer listings only)
    // =========================================================================

    /**
     * Resolve the latest active BuyerTenantDnaProfile for a listing.
     */
    protected function findBuyerTenantDnaProfile(string $canonicalType, int $listingId): ?BuyerTenantDnaProfile
    {
        return BuyerTenantDnaProfile::where('listing_type', $canonicalType)
            ->where('listing_id', $listingId)
            ->whereNull('archived_at')
            ->latest('computed_at')
            ->first();
    }

    /**
     * Assemble the buyer avatar context section.
     * Returns null and appends 'buyer_avatar' to $missingSources when profile is absent.
     */
    protected function buildBuyerAvatar(int $listingId, array &$missingSources): ?array
    {
        $profile = $this->findBuyerTenantDnaProfile('buyer', $listingId);

        if ($profile === null) {
            $missingSources[] = 'buyer_avatar';
            return null;
        }

        return [
            'avatar_type'                => $profile->avatar_type ?? null,
            'primary_motivation'         => $profile->primary_motivation ?? null,
            'secondary_motivation'       => $profile->secondary_motivation ?? null,
            'buyer_narrative'            => $profile->buyer_narrative ?? null,
            'buyer_preference_summary'   => $profile->buyer_preference_summary ?? null,
            'buyer_personality_tags'     => $profile->buyer_personality_tags ?? null,
            'buyer_match_preferences'    => $profile->buyer_match_preferences ?? null,
            'avatar_confidence_score'    => $profile->avatar_confidence_score ?? null,
            'buyer_readiness_score'      => $profile->buyer_readiness_score ?? null,
            'buyer_avatar_version'       => $profile->buyer_avatar_version ?? null,
        ];
    }

    // =========================================================================
    // Tenant Avatar Context (tenant listings only)
    // =========================================================================

    /**
     * Assemble the tenant avatar context section.
     * Returns null and appends 'tenant_avatar' to $missingSources when profile is absent.
     */
    protected function buildTenantAvatar(int $listingId, array &$missingSources): ?array
    {
        $profile = $this->findBuyerTenantDnaProfile('tenant', $listingId);

        if ($profile === null) {
            $missingSources[] = 'tenant_avatar';
            return null;
        }

        return [
            'avatar_type'                => $profile->avatar_type ?? null,
            'primary_motivation'         => $profile->primary_motivation ?? null,
            'secondary_motivation'       => $profile->secondary_motivation ?? null,
            'tenant_narrative'           => $profile->tenant_narrative ?? null,
            'tenant_preference_summary'  => $profile->tenant_preference_summary ?? null,
            'tenant_personality_tags'    => $profile->tenant_personality_tags ?? null,
            'tenant_match_preferences'   => $profile->tenant_match_preferences ?? null,
            'avatar_confidence_score'    => $profile->avatar_confidence_score ?? null,
            'tenant_avatar_version'      => $profile->tenant_avatar_version ?? null,
        ];
    }

    // =========================================================================
    // Compatibility Context (only when pair options are supplied)
    // =========================================================================

    /**
     * Returns true when $options contains a complete demand+supply pair.
     */
    protected function hasPairOptions(array $options): bool
    {
        return isset($options['demand_listing_type'], $options['demand_listing_id'],
                     $options['supply_listing_type'], $options['supply_listing_id']);
    }

    /**
     * Resolve the latest active ListingCompatibilityScore for the given pair.
     */
    protected function findCompatibilityScore(
        string $demandType,
        int $demandId,
        string $supplyType,
        int $supplyId
    ): ?ListingCompatibilityScore {
        return ListingCompatibilityScore::where('demand_listing_type', $demandType)
            ->where('demand_listing_id', $demandId)
            ->where('supply_listing_type', $supplyType)
            ->where('supply_listing_id', $supplyId)
            ->whereNull('archived_at')
            ->latest('computed_at')
            ->first();
    }

    /**
     * Assemble the compatibility context section.
     * When a pair is requested but no score record exists, appends a warning (not a missing_source).
     */
    protected function buildCompatibility(
        string $canonicalType,
        int $listingId,
        array $options,
        array &$warnings
    ): ?array {
        $demandType = (string) $options['demand_listing_type'];
        $demandId   = (int)    $options['demand_listing_id'];
        $supplyType = (string) $options['supply_listing_type'];
        $supplyId   = (int)    $options['supply_listing_id'];

        $score = $this->findCompatibilityScore($demandType, $demandId, $supplyType, $supplyId);

        if ($score === null) {
            $warnings[] = 'Compatibility data is not available for the requested listing pair.';
            return null;
        }

        $result = [
            'overall_score'                 => $score->overall_score ?? null,
            'physical_match_score'          => $score->physical_match_score ?? null,
            'financial_match_score'         => $score->financial_match_score ?? null,
            'terms_match_score'             => $score->terms_match_score ?? null,
            'location_match_score'          => $score->location_match_score ?? null,
            'compatibility_summary_json'    => $score->compatibility_summary_json ?? null,
            'compatibility_highlights'      => $score->compatibility_highlights ?? null,
            'compatibility_warnings'        => $score->compatibility_warnings ?? null,
            'compatibility_readiness_score' => $score->compatibility_readiness_score ?? null,
            'compatibility_narrative'       => $score->compatibility_narrative ?? null,
            'score_explanation'             => $score->score_explanation ?? null,
            'version'                       => $score->version ?? null,
            'computed_at'                   => isset($score->computed_at)
                ? (string) $score->computed_at
                : null,
        ];

        if ($score->compatibility_trait_results !== null) {
            $result['compatibility_trait_results'] = $score->compatibility_trait_results;
        }

        return $result;
    }

    // =========================================================================
    // Offer Analysis Context
    // =========================================================================

    /**
     * Resolve the latest AcceptedBidSummary linked to a listing.
     */
    protected function findAcceptedBidSummary(string $canonicalType, int $listingId): ?AcceptedBidSummary
    {
        return AcceptedBidSummary::where('listing_type', $canonicalType)
            ->where('listing_id', $listingId)
            ->latest()
            ->first();
    }

    /**
     * Assemble the offer analysis context section.
     * Returns null when no accepted bid summary exists — this is not a failure.
     *
     * DATA GOVERNANCE: Only deal-content fields are exposed here. Signature
     * metadata (names, IP addresses, user-agent strings, timezones), user IDs,
     * and any other user-identifying fields are deliberately excluded to prevent
     * PII leakage into the Ask AI context payload. Ask AI phases require only
     * the accepted-terms content to reason about offer status and deal structure.
     *
     * Approved fields:
     *   id, listing_type, listing_id, accepted_bid_id, accepted_counter_id,
     *   summary_html, summary_pdf_path, created_at, updated_at.
     */
    protected function buildOfferAnalysis(string $canonicalType, int $listingId): ?array
    {
        $summary = $this->findAcceptedBidSummary($canonicalType, $listingId);

        if ($summary === null) {
            return null;
        }

        return [
            'id'                  => $summary->id,
            'listing_type'        => $summary->listing_type ?? $canonicalType,
            'listing_id'          => $summary->listing_id ?? $listingId,
            'accepted_bid_id'     => $summary->accepted_bid_id ?? null,
            'accepted_counter_id' => $summary->accepted_counter_id ?? null,
            'summary_html'        => $summary->summary_html ?? null,
            'summary_pdf_path'    => $summary->summary_pdf_path ?? null,
            'created_at'          => isset($summary->created_at) ? (string) $summary->created_at : null,
            'updated_at'          => isset($summary->updated_at) ? (string) $summary->updated_at : null,
        ];
    }

    // =========================================================================
    // Source Versions & Status Assembly
    // =========================================================================

    /**
     * Build the source_versions map from available intelligence sections.
     *
     * location_dna_lifestyle_version is populated from location_intelligence['lifestyle_version']
     * when the location intelligence section is available.
     *
     * agent_profile_available and agent_presets_count track whether agent context
     * is present in this payload, allowing callers to determine if agent-related
     * questions can be answered without a separate lookup.
     */
    protected function buildSourceVersions(
        ?array $propertyIntelligence,
        ?array $locationIntelligence,
        ?array $buyerAvatar,
        ?array $tenantAvatar,
        ?array $compatibility,
        ?array $agentProfile = null,
        ?array $agentPresets = null
    ): array {
        $versions = [
            'ask_ai_context'                => self::CONTEXT_VERSION,
            'property_intelligence_version' => null,
            'location_dna_lifestyle_version'=> null,
            'buyer_avatar_version'          => null,
            'tenant_avatar_version'         => null,
            'compatibility_version'         => null,
            'agent_profile_available'       => false,
            'agent_presets_count'           => 0,
        ];

        if ($propertyIntelligence !== null) {
            $versions['property_intelligence_version'] =
                $propertyIntelligence['property_intelligence_version'] ?? null;
        }

        if ($locationIntelligence !== null) {
            $versions['location_dna_lifestyle_version'] =
                $locationIntelligence['lifestyle_version'] ?? null;
        }

        if ($buyerAvatar !== null) {
            $versions['buyer_avatar_version'] = $buyerAvatar['buyer_avatar_version'] ?? null;
        }

        if ($tenantAvatar !== null) {
            $versions['tenant_avatar_version'] = $tenantAvatar['tenant_avatar_version'] ?? null;
        }

        if ($compatibility !== null) {
            $versions['compatibility_version'] = $compatibility['version'] ?? null;
        }

        if ($agentProfile !== null) {
            $versions['agent_profile_available'] = true;
        }

        if ($agentPresets !== null) {
            $versions['agent_presets_count'] = (int) ($agentPresets['preset_count'] ?? 0);
        }

        return $versions;
    }

    /**
     * Determine the final status string.
     *
     * 'assembled' — listing found and at least one intelligence source is populated.
     * 'partial'   — listing found but one or more expected sources are missing.
     */
    protected function determineStatus(
        string $canonicalType,
        ?array $propertyIntelligence,
        ?array $locationIntelligence,
        ?array $buyerAvatar,
        ?array $tenantAvatar,
        array $missingSources
    ): string {
        if (!empty($missingSources)) {
            return 'partial';
        }

        $hasIntelligence = false;

        if (in_array($canonicalType, ['seller', 'landlord'], true)) {
            $hasIntelligence = $propertyIntelligence !== null;
        }

        if ($locationIntelligence !== null) {
            $hasIntelligence = true;
        }

        if ($canonicalType === 'buyer' && $buyerAvatar !== null) {
            $hasIntelligence = true;
        }

        if ($canonicalType === 'tenant' && $tenantAvatar !== null) {
            $hasIntelligence = true;
        }

        return $hasIntelligence ? 'assembled' : 'partial';
    }

    // =========================================================================
    // Agent Profile & Preset Context
    // =========================================================================

    /**
     * Load the public-safe agent profile fragment for the listing's linked agent.
     *
     * The agent is resolved from the listing's `user_id` column (present on all four
     * listing models). Returns null when the listing has no linked agent or when
     * AgentProfileLoader returns null (e.g. user not found, no profile data).
     *
     * DATA GOVERNANCE: AgentProfileLoader already excludes all PRIVATE_KEYS
     * (email, phone, fee amounts, commission rates). This method forwards the
     * loader's `content` array directly — no further filtering required.
     *
     * @param  string $canonicalType  One of 'seller', 'buyer', 'landlord', 'tenant'.
     * @param  object $listing        The resolved listing model instance.
     * @return array|null
     */
    protected function buildAgentProfile(string $canonicalType, object $listing): ?array
    {
        $agentId = (int) ($listing->user_id ?? 0);
        if ($agentId <= 0) {
            return null;
        }

        $loader   = new AgentProfileLoader();
        $fragment = $loader([
            'agent_id'     => $agentId,
            'listing_type' => $canonicalType,
            'listing_id'   => (int) ($listing->id ?? 0),
            'scope'        => $canonicalType,
        ]);

        return $fragment['content'] ?? null;
    }

    /**
     * Load the public-safe agent preset summaries for the listing's linked agent.
     *
     * Returns null when the listing has no linked agent or when the agent has
     * no presets (AgentPresetLoader returns null). When presets exist, the
     * returned array has keys: `presets` (array of preset summaries) and
     * `preset_count` (int).
     *
     * DATA GOVERNANCE: AgentPresetLoader already excludes all PRIVATE_KEYS
     * (email, phone, fee amounts, commission percentages, personal details).
     * This method forwards the loader's `content` array directly.
     *
     * @param  string $canonicalType  One of 'seller', 'buyer', 'landlord', 'tenant'.
     * @param  object $listing        The resolved listing model instance.
     * @return array|null
     */
    protected function buildAgentPresets(string $canonicalType, object $listing): ?array
    {
        $agentId = (int) ($listing->user_id ?? 0);
        if ($agentId <= 0) {
            return null;
        }

        $loader   = new AgentPresetLoader();
        $fragment = $loader([
            'agent_id'     => $agentId,
            'listing_type' => $canonicalType,
            'listing_id'   => (int) ($listing->id ?? 0),
            'scope'        => $canonicalType,
        ]);

        return $fragment['content'] ?? null;
    }

    // =========================================================================
    // Fixed-shape response helpers
    // =========================================================================

    /**
     * Build the empty contract-shaped payload used by not_found and failed responses.
     * All optional intelligence sections are null; missing_sources and warnings are empty.
     * Includes success=false plus top-level listing_type/listing_id to match the full contract.
     * The `error` key is always present (null in this base payload).
     */
    protected function buildEmptyPayload(string $status, string $listingType, int $listingId): array
    {
        return [
            'success'               => false,
            'listing_type'          => $listingType,
            'listing_id'            => $listingId,
            'context_version'       => self::CONTEXT_VERSION,
            'status'                => $status,
            'listing'               => null,
            'faq_answers'           => [],
            'property_intelligence' => null,
            'location_intelligence' => null,
            'buyer_avatar'          => null,
            'tenant_avatar'         => null,
            'compatibility'         => null,
            'offer_analysis'        => null,
            'agent_profile'         => null,
            'agent_presets'         => null,
            'missing_sources'       => [],
            'warnings'              => [],
            'source_versions'       => [
                'ask_ai_context'                => self::CONTEXT_VERSION,
                'property_intelligence_version' => null,
                'location_dna_lifestyle_version'=> null,
                'buyer_avatar_version'          => null,
                'tenant_avatar_version'         => null,
                'compatibility_version'         => null,
                'agent_profile_available'       => false,
                'agent_presets_count'           => 0,
            ],
            'assembled_at'          => now()->toISOString(),
            'error'                 => null,
        ];
    }

    private function buildNotFoundResponse(string $listingType, int $listingId): array
    {
        return $this->buildEmptyPayload('not_found', $listingType, $listingId);
    }

    private function buildFailedResponse(string $listingType, int $listingId, string $error): array
    {
        $payload          = $this->buildEmptyPayload('failed', $listingType, $listingId);
        $payload['error'] = $error;
        return $payload;
    }
}
