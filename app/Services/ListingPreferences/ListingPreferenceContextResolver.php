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

    /**
     * Resolve many refs with ONE query per listing type.
     *
     * The mirror of {@see ListingPreferenceSubjectResolver::resolveMany()}, and
     * it exists for the same reason: a result page asks about a page of
     * listings at once, and `resolve()` per card is an N+1 against
     * `bridge_properties` or a meta table. The mapping itself is untouched —
     * every answer still comes from SmartTagContextResolver, so a batched read
     * and a single read cannot disagree about what a property type means.
     *
     * A ref whose context cannot be resolved is present in the result with a
     * NULL value, never absent. Absent would be indistinguishable from "not
     * asked", and the caller must be able to tell an unresolvable listing from
     * one it forgot to include.
     *
     * @param  list<SmartTagListingRef> $refs
     * @return array<string, ?SmartTagContext> keyed "<type>:<id>"
     */
    public function resolveMany(array $refs): array
    {
        /** @var array<string, array<int,int>> $idsByType */
        $idsByType = [];

        foreach ($refs as $ref) {
            $idsByType[$ref->type->value][$ref->id] = $ref->id;
        }

        $out = [];

        foreach ($idsByType as $typeValue => $ids) {
            $type = SmartTagListingType::from($typeValue);
            $ids  = array_values($ids);

            $propertyTypes = $type === SmartTagListingType::Bridge
                ? $this->bridgePropertyTypes($ids)
                : $this->nativePropertyTypes($type, $ids);

            foreach ($ids as $id) {
                $raw = $propertyTypes[$id] ?? null;
                $raw = is_string($raw) ? $raw : null;

                $out["{$typeValue}:{$id}"] = $type === SmartTagListingType::Bridge
                    ? SmartTagContextResolver::forBridge($raw)
                    : SmartTagContextResolver::forListingType($type, $raw);
            }
        }

        return $out;
    }

    /**
     * @param  list<int> $ids
     * @return array<int, string>
     */
    private function bridgePropertyTypes(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return BridgeProperty::query()
            ->whereIn('id', $ids)
            ->whereNotNull('property_type')
            ->pluck('property_type', 'id')
            ->map(static fn ($v): string => (string) $v)
            ->all();
    }

    /**
     * @param  list<int> $ids
     * @return array<int, string>
     */
    private function nativePropertyTypes(SmartTagListingType $type, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        [$metaClass, $foreignKey] = $this->metaTarget($type);

        if ($metaClass === null) {
            return [];
        }

        $out = [];

        // Grouped rather than plucked: NativeMetaValueReader is the one reading
        // of a meta value, and a raw pluck would be a second one that could
        // interpret a stored value differently from the single-ref path.
        foreach (
            $metaClass::query()
                ->whereIn($foreignKey, $ids)
                ->where('meta_key', SmartTagSourceRules::nativePropertyTypeField())
                ->get([$foreignKey, 'meta_key', 'meta_value'])
                ->groupBy($foreignKey)
            as $listingId => $rows
        ) {
            $value = NativeMetaValueReader::fromMetaRows($rows)
                ->scalar(SmartTagSourceRules::nativePropertyTypeField());

            if (is_string($value)) {
                $out[(int) $listingId] = $value;
            }
        }

        return $out;
    }

    /** @return array{0: ?class-string, 1: ?string} */
    private function metaTarget(SmartTagListingType $type): array
    {
        return match ($type) {
            SmartTagListingType::SellerAgent   => [SellerAgentAuctionMeta::class, 'seller_agent_auction_id'],
            SmartTagListingType::LandlordAgent => [LandlordAgentAuctionMeta::class, 'landlord_agent_auction_id'],
            default                            => [null, null],
        };
    }

    private function nativePropertyType(SmartTagListingRef $ref): ?string
    {
        [$metaClass, $foreignKey] = $this->metaTarget($ref->type);

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
