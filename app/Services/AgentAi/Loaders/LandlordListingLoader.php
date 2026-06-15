<?php

namespace App\Services\AgentAi\Loaders;

use App\Models\LandlordAgentAuction;

/**
 * LandlordListingLoader
 *
 * Loads public-safe native columns and EAV meta from `landlord_agent_auctions`
 * and `landlord_agent_auction_metas` for the AgentAi V2 landlord listing scope.
 *
 * Registration:
 *   source_key: 'listing_core'
 *   priority:   100
 *   scope:      PublicListingLandlord only
 *
 * Field classification authority: docs/audits/AGENT_AI_ASSISTANT_CONTEXT_SOURCE_MAP.md
 * Sections 3.1 (native columns) and 3.2 (EAV keys).
 *
 * GOVERNANCE:
 *   - user_id, referring_agent_id, referral_source_code, referral_captured_at,
 *     referral_locked are NEVER included.
 *   - Bid, offer, counteroffer, and accepted-bid-summary data are NEVER included.
 *   - No DB writes. No external HTTP calls.
 */
class LandlordListingLoader
{
    use LoaderHelpers;

    public const SOURCE_KEY = 'listing_core';
    public const PRIORITY   = 100;
    public const CACHE_TTL  = 300;

    /**
     * Callable entry point registered with AgentAiContextSourceRegistry.
     *
     * @param  array $scopeContext  {scope, agent_id, listing_type, listing_id}
     * @return array|null           Fragment or null when listing not found.
     */
    public function __invoke(array $scopeContext): ?array
    {
        $listingId = (int) ($scopeContext['listing_id'] ?? 0);
        if ($listingId <= 0) {
            return null;
        }

        $listing = LandlordAgentAuction::find($listingId);
        if (!$listing) {
            return null;
        }

        $infoGet   = self::makeInfoGet($listing);
        $nativeGet = self::makeNativeGet($listing);

        $content = $this->extractFields($listing, $infoGet, $nativeGet);

        return self::makeFragment(
            self::SOURCE_KEY,
            self::PRIORITY,
            $content,
            true,
            ['landlord'],
            self::CACHE_TTL
        );
    }

    /**
     * Extract all public-safe fields per audit Section 3.1 + 3.2.
     *
     * Note: Landlord has far fewer native columns than seller (audit Section 3.1).
     * Almost all property data is EAV-only via info().
     */
    private function extractFields(
        LandlordAgentAuction $listing,
        callable $infoGet,
        callable $nativeGet
    ): array {
        return [
            'listing_type'    => 'landlord',
            'listing_id'      => $listing->id,
            'listing_title'   => $nativeGet('title'),
            'city'            => $infoGet('city'),
            'state'           => $infoGet('state'),
            'county'          => $infoGet('county'),
            'property_type'   => $infoGet('property_type'),
            'listing_id_ref'  => $nativeGet('listing_id'),
            'created_at'      => $listing->created_at ? (string) $listing->created_at : null,
            'updated_at'      => $listing->updated_at ? (string) $listing->updated_at : null,

            'rent_amount'               => $infoGet('desired_rental_amount') ?? $infoGet('starting_rent') ?? $infoGet('lease_now_price'),
            'bedrooms'                  => self::resolveOtherValue($infoGet('bedrooms'), $infoGet, 'other_bedrooms'),
            'bathrooms'                 => self::resolveOtherValue($infoGet('bathrooms'), $infoGet, 'other_bathrooms'),
            'square_feet'               => $infoGet('minimum_heated_square') ?? $infoGet('heated_square_footage') ?? $infoGet('heated_square'),
            'unit_size'                 => $infoGet('unit_size'),
            'number_of_units'           => $infoGet('number_of_unit'),
            'property_zip'              => $infoGet('property_zip'),
            'property_items'            => self::decodeJsonField($infoGet('property_items')),
            'condition_prop'            => self::resolveOtherValue($infoGet('condition_prop'), $infoGet, 'other_property_condition'),
            'appliances'                => self::decodeJsonField($infoGet('appliances')),
            'water_view'                => self::decodeJsonField($infoGet('water_view')) ?: self::decodeJsonField($infoGet('view_preference')),
            'available_date'            => $infoGet('available_date'),
            'pet_policy'                => $infoGet('pet_policy') ?: $infoGet('pets'),
            'pet_deposit_fee_rent'      => $infoGet('pet_deposit_fee_rent'),
            'pet_max_weight_lbs'        => $infoGet('pet_max_weight_lbs'),
            'pet_species_allowed'       => self::decodeJsonField($infoGet('pet_species_allowed')),
            'pet_deposit_amount'        => $infoGet('pet_deposit_amount'),
            'pet_monthly_fee'           => $infoGet('pet_monthly_fee'),
            'parking_terms'             => $infoGet('parking_terms'),
            'utilities'                 => self::decodeJsonField($infoGet('property_utilities')) ?? $infoGet('utilities'),
            'smoking_policy'            => $infoGet('smoking_policy'),
            'subletting_policy'         => $infoGet('subletting_policy'),
            'has_hoa'                   => $infoGet('has_hoa'),
            'association_name'          => $infoGet('association_name'),
            'association_fee_amount'    => $infoGet('association_fee_amount'),
            'association_fee_frequency' => $infoGet('association_fee_frequency'),
            'association_amenities'     => self::decodeJsonField($infoGet('association_amenities')),
            'annual_property_taxes'     => $infoGet('annual_property_taxes'),
            'leasing_restrictions'      => $infoGet('leasing_restrictions'),
            'lease_length'              => self::resolveOtherValue(
                                              $infoGet('min_lease_period') ?? $infoGet('minimum_lease_period'),
                                              $infoGet,
                                              'min_lease_period_other'
                                          ) ?? self::decodeJsonField($infoGet('desired_lease_length')),
            'renewal_option'            => $infoGet('renewal_option_offered'),
            'renewal_option_details'    => $infoGet('renewal_option_details'),
            'number_of_occupants'       => $infoGet('number_occupant'),
            'number_of_occupants_allowed' => $infoGet('number_of_occupants_allowed'),
            'additional_lease_terms'    => $infoGet('additional_landlord_lease_terms'),
            'lease_terms'               => self::decodeJsonField($infoGet('terms_of_lease')),
            'terms_of_lease'            => self::decodeJsonField($infoGet('terms_of_lease')),
            'commercial_lease_type'     => self::resolveOtherValue($infoGet('commercial_lease_type'), $infoGet, 'commercial_lease_type_other'),
            'cam_nnn_additional_rent_charges'   => $infoGet('cam_nnn_additional_rent_charges'),
            'rent_escalation_terms'             => $infoGet('rent_escalation_terms'),
            'tenant_improvement_buildout_terms' => $infoGet('tenant_improvement_buildout_terms'),
            'permitted_use_restrictions'        => $infoGet('permitted_use_restrictions'),
            'signage_rights'                    => $infoGet('signage_rights'),
            'commercial_parking_terms'          => $infoGet('commercial_parking_terms'),
            'personal_guarantee_requirement'    => $infoGet('personal_guarantee_requirement'),
            'commercial_approval_conditions'    => $infoGet('commercial_approval_conditions'),
            'parcel_id'                 => $infoGet('parcel_id'),
            'tax_year'                  => $infoGet('tax_year'),
            'legal_description'         => $infoGet('legal_description'),
            'additional_parcels'        => $infoGet('additional_parcels'),
            'total_parcel_count'        => $infoGet('total_parcel_count'),
            'additional_parcel_ids'     => $infoGet('additional_parcel_ids'),
            'year_built'                => $infoGet('year_built'),
            'lot_dimensions'            => $infoGet('lot_dimensions'),
            'zoning'                    => $infoGet('zoning'),
            'waterfront'                => $infoGet('waterfront'),
            'water_access'              => self::decodeJsonField($infoGet('water_access')),
            'interior_features'         => self::decodeJsonField($infoGet('interior_features')),
            'roof_type'                 => self::decodeJsonField($infoGet('roof_type')),
            'exterior_construction'     => self::decodeJsonField($infoGet('exterior_construction')),
            'foundation'                => self::decodeJsonField($infoGet('foundation')),
            'flood_zone_code'           => self::resolveOtherValue($infoGet('flood_zone_code'), $infoGet, 'flood_zone_code_other'),
            'flood_zone_panel'          => $infoGet('flood_zone_panel'),
            'flood_zone_date'           => $infoGet('flood_zone_date'),
            'flood_insurance_required'  => $infoGet('flood_insurance_required'),
            'security_deposit_amount'   => $infoGet('security_deposit_amount'),
            'tenant_pays'               => self::decodeJsonField($infoGet('tenant_pays')),
            'rent_includes'             => self::decodeJsonField($infoGet('rent_includes')),
            'heating_fuel'              => self::decodeJsonField($infoGet('heating_fuel')),
            'air_conditioning'          => self::decodeJsonField($infoGet('air_conditioning')),
            'water'                     => self::decodeJsonField($infoGet('water')),
            'sewer'                     => self::decodeJsonField($infoGet('sewer')),
            'lease_amount_frequency'    => $infoGet('lease_amount_frequency'),
            'has_cdd'                   => $infoGet('has_cdd'),
            'annual_cdd_fee'            => $infoGet('annual_cdd_fee'),
            'first_month_rent_required' => $infoGet('first_month_rent_required'),
            'last_month_rent_required'  => $infoGet('last_month_rent_required'),
            'total_move_in_funds_required' => $infoGet('total_move_in_funds_required'),
            'min_income_requirement'    => $infoGet('min_income_requirement'),
            'll_maintenance_responsibility' => $infoGet('ll_maintenance_responsibility'),
            'landlord_approval_conditions' => $infoGet('landlord_approval_conditions'),
            'disclosure_flags'          => 'flood_zone:true',
            'listing_status'            => $infoGet('listing_status'),
        ];
    }
}
