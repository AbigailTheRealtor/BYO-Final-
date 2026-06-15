<?php

namespace App\Services\AgentAi\Loaders;

use App\Models\BuyerAgentAuction;

/**
 * BuyerCriteriaLoader
 *
 * Loads public buyer criteria fields including budget range, location preferences,
 * must-haves, and respond link from `buyer_agent_auctions` and
 * `buyer_agent_auction_metas`.
 *
 * Registration:
 *   source_key: 'listing_core'
 *   priority:   100
 *   scope:      BuyerCriteria only
 *
 * Field classification authority: docs/audits/AGENT_AI_ASSISTANT_CONTEXT_SOURCE_MAP.md
 * Sections 4.1 (native columns) and 4.2 (EAV keys).
 *
 * GOVERNANCE:
 *   - user_id, concession, cash_budget, crypto_budget, preapproval_amount,
 *     need_lender, is_paid, referring_agent_id are NEVER included.
 *   - Bid, offer, and counteroffer data are NEVER included.
 *   - No DB writes. No external HTTP calls.
 */
class BuyerCriteriaLoader
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

        $listing = BuyerAgentAuction::find($listingId);
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
            ['buyer'],
            self::CACHE_TTL
        );
    }

    /**
     * Extract all public-safe fields per audit Section 4.1 + 4.2.
     */
    private function extractFields(
        BuyerAgentAuction $listing,
        callable $infoGet,
        callable $nativeGet
    ): array {
        return [
            'listing_type'                  => 'buyer',
            'listing_id'                    => $listing->id,
            'listing_title'                 => $nativeGet('title'),
            'address'                       => $nativeGet('address'),
            'city'                          => $infoGet('city') ?? $nativeGet('city'),
            'state'                         => $infoGet('state') ?? $nativeGet('state'),
            'county'                        => $infoGet('county') ?? $nativeGet('county'),
            'property_type'                 => $infoGet('property_type'),
            'listing_id_ref'                => $nativeGet('listing_id'),
            'listing_url'                   => '/buyer-criteria/' . $listing->id,
            'description'                   => self::truncateText($nativeGet('additional_details')),
            'created_at'                    => $listing->created_at ? (string) $listing->created_at : null,
            'updated_at'                    => $listing->updated_at ? (string) $listing->updated_at : null,

            'max_price'                     => $infoGet('maximum_budget'),
            'bedrooms'                      => self::resolveOtherValue($infoGet('bedrooms') ?? $nativeGet('bedroom_id'), $infoGet, 'other_bedrooms'),
            'bathrooms'                     => self::resolveOtherValue($infoGet('bathrooms') ?? $nativeGet('bathroom_id'), $infoGet, 'other_bathrooms'),
            'square_feet'                   => $infoGet('minimum_heated_square') ?? $infoGet('heated_square_footage') ?? $infoGet('heated_square'),
            'pool'                          => $infoGet('pool_needed'),
            'carport'                       => self::resolveOtherValue($infoGet('carport_needed'), $infoGet, 'other_carport_needed'),
            'garage'                        => self::resolveOtherValue($infoGet('garage_needed'), $infoGet, 'other_garage', 'other_garage_needed'),
            'garage_spaces'                 => $infoGet('garage_parking_spaces'),
            'water_view'                    => self::decodeJsonField($infoGet('view_preference')),
            'hoa_acceptable'                => $infoGet('hoa_acceptance'),
            'max_hoa_fee'                   => $infoGet('hoa_max_monthly_fee'),
            'pets_allowed'                  => $infoGet('pets'),
            'pets_detail'                   => $infoGet('type_of_pets'),
            'pets_breed'                    => $infoGet('breed_of_pets'),
            'pets_weight'                   => $infoGet('weight_of_pets'),
            'loan_pre_approved'             => $infoGet('pre_approved'),
            'financing_type'                => self::decodeJsonField($infoGet('financing_type') ?? $infoGet('offered_financing')),
            'inspection_period'             => $infoGet('inspection_period_days'),
            'closing_date'                  => $infoGet('target_closing_date'),
            'inspection_contingency_buyer'  => $infoGet('inspection_contingency_buyer'),
            'appraisal_contingency_buyer'   => $infoGet('appraisal_contingency_buyer'),
            'financing_contingency_buyer'   => $infoGet('financing_contingency_buyer'),
            'cities'                        => self::decodeJsonField($infoGet('cities')),
            'counties'                      => self::decodeJsonField($infoGet('counties')),
            'listing_status'                => $infoGet('listing_status'),
        ];
    }
}
