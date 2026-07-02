<?php

namespace App\Services\Canonical;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Services\Canonical\Adapters\ByoListingAdapter;

/**
 * CanonicalListingResolver — given (listing_type, listing_id), loads the
 * correct first-party role model and projects it onto the canonical vocabulary
 * via the BYO adapter (§F1).
 *
 * GOVERNANCE: read-only; no writes, no AI, no external calls.
 *
 * Wave 1 supports the four BYO role listings. Bridge/RESO, RentCast, ATTOM,
 * CSV adapters plug in here later behind the same CanonicalListing return type
 * without any downstream change.
 */
class CanonicalListingResolver
{
    private const MODELS = [
        'landlord_agent' => LandlordAgentAuction::class,
        'seller_agent'   => SellerAgentAuction::class,
        'tenant_agent'   => TenantAgentAuction::class,
        'buyer_agent'    => BuyerAgentAuction::class,
    ];

    private ByoListingAdapter $byoAdapter;

    public function __construct(ByoListingAdapter $byoAdapter)
    {
        $this->byoAdapter = $byoAdapter;
    }

    public function supports(string $listingType): bool
    {
        return isset(self::MODELS[$listingType]);
    }

    /**
     * Resolve a canonical projection, or null when the listing cannot be found
     * or the type is unsupported.
     */
    public function resolve(string $listingType, int $listingId): ?CanonicalListing
    {
        $modelClass = self::MODELS[$listingType] ?? null;
        if ($modelClass === null) {
            return null;
        }

        // Load fresh with meta so info() reflects persisted state.
        $model = $modelClass::with('meta')->find($listingId);
        if ($model === null) {
            return null;
        }

        return $this->byoAdapter->fromModel($model, $listingType, $listingId);
    }
}
