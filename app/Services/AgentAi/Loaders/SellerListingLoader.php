<?php

namespace App\Services\AgentAi\Loaders;

use App\Models\SellerAgentAuction;

/**
 * SellerListingLoader
 *
 * Loads public-safe native columns and EAV meta from `seller_agent_auctions`
 * and `seller_agent_auction_metas` for the AgentAi V2 seller listing scope.
 *
 * Registration:
 *   source_key: 'listing_core'
 *   priority:   100
 *   scope:      PublicListingSeller only
 *
 * Field classification authority: docs/audits/AGENT_AI_ASSISTANT_CONTEXT_SOURCE_MAP.md
 * Sections 2.1 (native columns) and 2.2 (EAV keys). Any field not explicitly
 * classified as Public or Compliance-Sensitive in those sections is excluded.
 *
 * GOVERNANCE:
 *   - user_id, max_commission, additional_services, important_info, contract_terms,
 *     description_ideal_agent, photos, video_url/file, audio_file, is_paid,
 *     referring_agent_id, referral_source_code are NEVER included.
 *   - Bid, offer, counteroffer, and accepted-bid-summary data are NEVER included.
 *   - No DB writes. No external HTTP calls.
 */
class SellerListingLoader
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

        $listing = SellerAgentAuction::find($listingId);
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
            ['seller'],
            self::CACHE_TTL
        );
    }

    /**
     * Extract all public-safe fields per audit Section 2.1 + 2.2.
     */
    private function extractFields(
        SellerAgentAuction $listing,
        callable $infoGet,
        callable $nativeGet
    ): array {
        $propertyType = $infoGet('property_type');

        return array_merge(
            [
                'listing_type'    => 'seller',
                'listing_id'      => $listing->id,
                'listing_title'   => $infoGet('listing_title') ?? $infoGet('title') ?? $nativeGet('title'),
                'address'         => $nativeGet('address'),
                'city'            => $infoGet('city') ?? $nativeGet('city'),
                'state'           => $infoGet('state') ?? $nativeGet('state'),
                'county'          => $infoGet('county') ?? $nativeGet('county'),
                'property_type'   => $propertyType,
                'listing_id_ref'  => $nativeGet('listing_id'),
                'auction_length'  => $nativeGet('auction_length'),
                'description'     => self::truncateText($nativeGet('description')),
                'created_at'      => $listing->created_at ? (string) $listing->created_at : null,
                'updated_at'      => $listing->updated_at ? (string) $listing->updated_at : null,

                'asking_price'              => $infoGet('maximum_budget'),
                'buy_now_price'             => $infoGet('buy_now_price'),
                'bedrooms'                  => self::resolveOtherValue($infoGet('bedrooms') ?? $nativeGet('bedroom_id'), $infoGet, 'other_bedrooms'),
                'bathrooms'                 => self::resolveOtherValue($infoGet('bathrooms') ?? $nativeGet('bathroom_id'), $infoGet, 'other_bathrooms'),
                'square_feet'               => $infoGet('minimum_heated_square') ?? $infoGet('heated_square_footage') ?? $infoGet('heated_square'),
                'year_built'                => $infoGet('year_built'),
                'pool'                      => $infoGet('pool_needed'),
                'pool_type'                 => self::decodeJsonField($infoGet('pool_type')),
                'carport'                   => self::resolveOtherValue($infoGet('carport_needed'), $infoGet, 'other_carport_needed'),
                'garage'                    => self::resolveOtherValue($infoGet('garage_needed'), $infoGet, 'other_garage', 'other_garage_needed'),
                'garage_spaces'             => $infoGet('garage_parking_spaces'),
                'parking_spaces'            => $infoGet('garage_parking_spaces'),
                'water_view'                => self::decodeJsonField($infoGet('water_view')) ?: self::decodeJsonField($infoGet('view_preference')),
                'lot_size'                  => $infoGet('total_acreage') ?? $infoGet('min_acreage'),
                'total_acreage'             => $infoGet('total_acreage'),
                'lot_dimensions'            => $infoGet('lot_dimensions'),
                'zoning'                    => $infoGet('zoning'),
                'waterfront'                => $infoGet('waterfront'),
                'water_access'              => self::decodeJsonField($infoGet('water_access')),
                'interior_features'         => self::decodeJsonField($infoGet('interior_features')),
                'appliances'                => self::decodeJsonField($infoGet('appliances')),
                'roof_type'                 => self::decodeJsonField($infoGet('roof_type')),
                'exterior_construction'     => self::decodeJsonField($infoGet('exterior_construction')),
                'foundation'                => self::decodeJsonField($infoGet('foundation')),
                'heating_and_fuel'          => self::decodeJsonField($infoGet('heating_and_fuel')),
                'air_conditioning'          => self::decodeJsonField($infoGet('air_conditioning')),
                'water'                     => self::decodeJsonField($infoGet('water')),
                'sewer'                     => self::decodeJsonField($infoGet('sewer')),
                'utilities'                 => self::decodeJsonField($infoGet('utilities')),
                'sale_provision'            => self::decodeJsonField($infoGet('sale_provision')),
                'offered_financing'         => self::decodeJsonField($infoGet('offered_financing')),
                'occupant_status'           => $infoGet('occupant_status'),
                'building_features'         => self::decodeJsonField($infoGet('building_features')),
                'hoa_association'           => $infoGet('has_hoa'),
                'hoa_fee'                   => $infoGet('association_fee_amount'),
                'hoa_payment_schedule'      => $infoGet('association_fee_frequency'),
                'hoa_name'                  => $infoGet('association_name'),
                'association_fee_includes'  => self::decodeJsonField($infoGet('association_fee_includes')),
                'has_cdd'                   => $infoGet('has_cdd'),
                'annual_cdd_fee'            => $infoGet('annual_cdd_fee'),
                'has_special_assessments'   => $infoGet('has_special_assessments'),
                'special_assessment_amount' => $infoGet('special_assessment_amount'),
                'special_assessment_description' => $infoGet('special_assessment_description'),
                'additional_parcels'        => $infoGet('additional_parcels'),
                'total_parcel_count'        => $infoGet('total_parcel_count'),
                'pets_allowed'              => $infoGet('pets'),
                'number_of_pets_allowed'    => $infoGet('number_of_pets'),
                'max_pet_weight'            => $infoGet('weight_of_pets'),
                'pet_restrictions'          => $infoGet('pet_restrictions'),
                'rental_restrictions'       => $infoGet('leasing_restrictions'),
                'flood_zone_code'           => self::resolveOtherValue($infoGet('flood_zone_code'), $infoGet, 'flood_zone_code_other'),
                'flood_zone_panel'          => $infoGet('flood_zone_panel'),
                'flood_zone_date'           => $infoGet('flood_zone_date'),
                'flood_insurance_required'  => $infoGet('flood_insurance_required'),
                'disclosure_flags'          => 'flood_zone:true',
                'parcel_id'                 => $infoGet('parcel_id'),
                'tax_year'                  => $infoGet('tax_year'),
                'legal_description'         => $infoGet('legal_description'),
                'closing_date'              => $infoGet('target_closing_date'),
                'annual_property_taxes'     => $infoGet('annual_property_taxes'),
                'building_sqft'             => $infoGet('total_square_feet'),
                'ceiling_height'            => $infoGet('ceiling_height'),
                'annual_noi'                => $infoGet('minimum_annual_net_income'),
                'cap_rate'                  => $infoGet('minimum_cap_rate'),
                'price_per_sqft'            => $infoGet('price_per_sqft'),
                'existing_lease_type'       => $infoGet('existing_lease_type'),
                'lease_expiration'          => $infoGet('lease_expiration'),
                'lease_assignable'          => $infoGet('lease_assignable'),
                'property_items'            => self::decodeJsonField($infoGet('property_items')),
                'total_units'               => $infoGet('unit_number'),
                'total_buildings'           => $infoGet('unit_buildings'),
                'gross_annual_income'       => $infoGet('gross_annual_income'),
                'annual_operating_expenses' => $infoGet('annual_operating_expenses'),
                'rent_roll_available'       => $infoGet('rent_roll_available'),
                'seller_credit_offered'     => $infoGet('seller_contribution_credit_offered'),
                'listing_status'            => $infoGet('listing_status'),
            ],
            ($propertyType === 'Vacant Land') ? [
                'water_available'   => $infoGet('water_available'),
                'sewer_available'   => $infoGet('sewer_available'),
                'electric_available'=> $infoGet('electric_available'),
                'gas_available'     => $infoGet('gas_available'),
                'telecom_available' => $infoGet('telecom_available'),
                'road_surface_type' => self::decodeJsonField($infoGet('road_surface_type')),
                'front_footage'     => $infoGet('front_footage'),
                'buildable'         => $infoGet('buildable'),
                'current_use'       => self::decodeJsonField($infoGet('current_use')),
                'road_frontage'     => self::decodeJsonField($infoGet('road_frontage')),
                'easements'         => self::decodeJsonField($infoGet('easements')),
                'vegetation'        => self::decodeJsonField($infoGet('vegetation')),
            ] : [],
            ($propertyType === 'Business') ? [
                'business_type'                   => self::resolveOtherValue($infoGet('business_type'), $infoGet, 'other_business_type'),
                'business_name'                   => $infoGet('business_name'),
                'year_established'                => $infoGet('year_established'),
                'annual_revenue'                  => $infoGet('annual_revenue'),
                'gross_profit'                    => $infoGet('gross_profit'),
                'sde_ebitda'                      => $infoGet('sde_ebitda'),
                'reason_for_sale'                 => self::resolveOtherValue($infoGet('reason_for_sale'), $infoGet, 'other_reason_for_sale'),
                'employee_count'                  => $infoGet('employee_count'),
                'nda_required'                    => $infoGet('nda_required'),
                'financial_statements_available'  => $infoGet('financial_statements_available'),
                'current_use'                     => self::decodeJsonField($infoGet('current_use')),
                'road_frontage'                   => self::decodeJsonField($infoGet('road_frontage')),
            ] : []
        );
    }
}
