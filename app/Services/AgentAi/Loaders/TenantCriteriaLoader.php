<?php

namespace App\Services\AgentAi\Loaders;

use App\Models\TenantAgentAuction;

/**
 * TenantCriteriaLoader
 *
 * Loads public tenant criteria fields — budget, lease preferences, must-haves,
 * and location preferences — from `tenant_agent_auctions` and
 * `tenant_agent_auction_metas`.
 *
 * Registration:
 *   source_key: 'listing_core'
 *   priority:   100
 *   scope:      TenantCriteria only
 *
 * Field classification authority: docs/audits/AGENT_AI_ASSISTANT_CONTEXT_SOURCE_MAP.md
 * Sections 5.1 (native columns) and 5.2 (EAV keys).
 *
 * GOVERNANCE:
 *   - user_id, referring_agent_id are NEVER included.
 *   - monthly_income is classified as Conditionally Public in the audit (tenant's
 *     own stated income submitted to attract agent bids). Included per Section 5.2.
 *   - Bid, offer, and counteroffer data are NEVER included.
 *   - No DB writes. No external HTTP calls.
 */
class TenantCriteriaLoader
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

        $listing = TenantAgentAuction::find($listingId);
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
            ['tenant'],
            self::CACHE_TTL
        );
    }

    /**
     * Extract all public-safe fields per audit Section 5.1 + 5.2.
     *
     * Note: tenant has fewer native columns than buyer (no address, no additional_details
     * native column — description stored under EAV key 'additional_details').
     */
    private function extractFields(
        TenantAgentAuction $listing,
        callable $infoGet,
        callable $nativeGet
    ): array {
        return [
            'listing_type'         => 'tenant',
            'listing_id'           => $listing->id,
            'listing_title'        => $nativeGet('title'),
            'city'                 => $infoGet('city'),
            'state'                => $infoGet('state'),
            'county'               => $infoGet('county'),
            'property_type'        => $infoGet('property_type'),
            'listing_id_ref'       => $nativeGet('listing_id'),
            'description'          => self::truncateText($infoGet('additional_details')),
            'created_at'           => $listing->created_at ? (string) $listing->created_at : null,
            'updated_at'           => $listing->updated_at ? (string) $listing->updated_at : null,

            'max_rent'             => $infoGet('budget') ?? $infoGet('maximum_budget'),
            'bedrooms'             => self::resolveOtherValue($infoGet('bedrooms'), $infoGet, 'other_bedrooms'),
            'bathrooms'            => self::resolveOtherValue($infoGet('bathrooms'), $infoGet, 'other_bathrooms'),
            'desired_lease_length' => self::decodeJsonField($infoGet('desired_lease_length'))
                                          ?? self::decodeJsonField($infoGet('lease_for')),
            'property_items'       => self::decodeJsonField($infoGet('property_items')),
            'appliances'           => self::decodeJsonField($infoGet('appliances')),
            'condition_prop'       => self::resolveOtherValue($infoGet('condition_prop'), $infoGet, 'other_property_condition'),
            'pet_information'      => $infoGet('pet_information'),
            'parking_needed'       => $infoGet('parking_needed'),
            'utilities'            => $infoGet('utilities'),
            'utility_preference'   => $infoGet('utility_preference'),
            'tenant_pays'          => self::decodeJsonField($infoGet('tenant_pays')),
            'current_status'       => $infoGet('current_status'),
            'number_of_occupants'  => $infoGet('number_of_occupants'),
            'number_of_units'      => $infoGet('number_of_unit'),
            'credit_score_range'   => $infoGet('credit_score_range') ?? $infoGet('credit_score'),
            'monthly_income'       => $infoGet('monthly_income') ?? $infoGet('household_monthly_income'),
            'cities'               => self::decodeJsonField($infoGet('cities')),
            'counties'             => self::decodeJsonField($infoGet('counties')),
            'listing_status'       => $infoGet('listing_status'),
        ];
    }
}
