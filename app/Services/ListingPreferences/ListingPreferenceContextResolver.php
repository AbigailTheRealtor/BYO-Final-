<?php

namespace App\Services\ListingPreferences;

use App\Models\BridgeProperty;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\SellerAgentAuctionMeta;
use App\Services\SmartTags\Derivation\SmartTagSourceRules;
use App\Support\SmartTags\NativeMetaValueReader;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagContextResolver;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;

/**
 * The listing's property context, resolved from the listing itself.
 *
 * PHASE 2'S OBLIGATION. Phase 1 approved a null-context fallback in
 * ListingPreferenceReasonPolicy only for genuinely unresolvable cases, on the
 * condition that Phase 2 make a real attempt first. This class IS that attempt:
 * every surface resolves through it before presenting or accepting reasons, so
 * the fallback is reached when the data cannot answer, never because the caller
 * did not ask.
 *
 * IT DELEGATES, IT DOES NOT DECIDE. The property-type-to-context mapping is
 * {@see SmartTagContextResolver} — exact, fail-closed, and already the one
 * definition used by the derivers. This class only knows WHERE each listing
 * type keeps its property type:
 *
 *   bridge                        `bridge_properties.property_type`
 *   seller_agent / landlord_agent the native `property_type` meta row, read
 *                                 through NativeMetaValueReader under the key
 *                                 SmartTagSourceRules names
 *
 * Null is a legitimate answer — an unrecognised spelling, a legacy value, a
 * listing with no property type at all — and callers must treat it as "not
 * resolvable", never as a default.
 */
class ListingPreferenceContextResolver
{
    public function resolve(SmartTagListingRef $ref): ?SmartTagContext
    {
        if ($ref->type === SmartTagListingType::Bridge) {
            $type = BridgeProperty::query()->whereKey($ref->id)->value('property_type');

            return SmartTagContextResolver::forBridge(is_string($type) ? $type : null);
        }

        return SmartTagContextResolver::forListingType($ref->type, $this->nativePropertyType($ref));
    }

    private function nativePropertyType(SmartTagListingRef $ref): ?string
    {
        [$metaClass, $foreignKey] = match ($ref->type) {
            SmartTagListingType::SellerAgent   => [SellerAgentAuctionMeta::class, 'seller_agent_auction_id'],
            SmartTagListingType::LandlordAgent => [LandlordAgentAuctionMeta::class, 'landlord_agent_auction_id'],
            default                            => [null, null],
        };

        if ($metaClass === null) {
            return null;
        }

        $rows = $metaClass::query()
            ->where($foreignKey, $ref->id)
            ->where('meta_key', SmartTagSourceRules::nativePropertyTypeField())
            ->get(['meta_key', 'meta_value']);

        return NativeMetaValueReader::fromMetaRows($rows)
            ->scalar(SmartTagSourceRules::nativePropertyTypeField());
    }
}
