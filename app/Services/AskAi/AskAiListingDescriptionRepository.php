<?php

namespace App\Services\AskAi;

use Illuminate\Support\Facades\DB;

/**
 * AskAiListingDescriptionRepository
 *
 * Thin read-only repository that loads the free-text listing description
 * for a given role + listing ID.  Extracted from AskAiRunnerV2Service so
 * that the runner itself contains no direct DB calls (architectural rule).
 *
 * Source per role (mirrors AskAiContextBuilderService::extractListingFields):
 *   seller   → seller_agent_auctions.description            (native column)
 *   buyer    → buyer_agent_auctions.additional_details      (native column)
 *   tenant   → tenant_agent_auction_metas meta_key='additional_details' (EAV)
 *   landlord → landlord_agent_auction_metas meta_key='additional_details' (EAV)
 *
 * Returns null when:
 *   - The listing type is unrecognised.
 *   - No row exists for the given listing ID.
 *   - The description column/meta value is null or blank.
 *   - Any DB exception occurs (silently caught).
 */
class AskAiListingDescriptionRepository
{
    private static array $typeAliases = [
        'seller'                  => 'seller',
        'seller_agent_auction'    => 'seller',
        'property_auction'        => 'seller',
        'buyer'                   => 'buyer',
        'buyer_agent_auction'     => 'buyer',
        'buyer_criteria_auction'  => 'buyer',
        'landlord'                => 'landlord',
        'landlord_agent_auction'  => 'landlord',
        'landlord_auction'        => 'landlord',
        'tenant'                  => 'tenant',
        'tenant_agent_auction'    => 'tenant',
        'tenant_criteria_auction' => 'tenant',
    ];

    /**
     * Load the free-text description for a listing.
     *
     * @param  string  $listingType  Canonical or aliased listing type string.
     * @param  int     $listingId    Primary key of the listing record.
     * @return string|null           Trimmed description text, or null.
     */
    public function load(string $listingType, int $listingId): ?string
    {
        $canonical = self::$typeAliases[strtolower($listingType)] ?? null;
        if ($canonical === null) {
            return null;
        }

        try {
            if ($canonical === 'seller') {
                $value = DB::table('seller_agent_auctions')
                    ->where('id', $listingId)
                    ->value('description');
            } elseif ($canonical === 'buyer') {
                $value = DB::table('buyer_agent_auctions')
                    ->where('id', $listingId)
                    ->value('additional_details');
            } elseif ($canonical === 'tenant') {
                $value = DB::table('tenant_agent_auction_metas')
                    ->where('tenant_agent_auction_id', $listingId)
                    ->where('meta_key', 'additional_details')
                    ->value('meta_value');
            } else {
                $value = DB::table('landlord_agent_auction_metas')
                    ->where('landlord_agent_auction_id', $listingId)
                    ->where('meta_key', 'additional_details')
                    ->value('meta_value');
            }

            if (!is_string($value) || trim($value) === '') {
                return null;
            }

            return trim($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
