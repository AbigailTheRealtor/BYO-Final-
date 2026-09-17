<?php

namespace App\Services\ListingPreferences;

use App\Models\BridgeProperty;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\SellerAgentAuctionMeta;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;
use App\Support\ListingPreferences\ListingPreferenceSubjectRef;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;

/**
 * Turns the listing a customer acted on into the durable subject a preference is
 * stored against.
 *
 * THE ONE PLACE PROVENANCE DECIDES A SUBJECT KEY. Two surfaces can show the same
 * property — the Bridge MLS row and the BidYourOffer listing imported from it —
 * and a preference expressed on one must be the same preference on the other.
 * The link already exists as the `mls_listing_key` meta that
 * {@see MlsQuickImportDraftWriter} writes and
 * {@see \App\Services\Explore\ExploreCanonicalListingResolver} reads; this class
 * reads the same meta under the same constant rather than inventing a second
 * notion of "the same property".
 *
 *   bridge                                    → mls:<listing_key>
 *   seller_agent / landlord_agent, no MLS     → byo:<listing_type>:<id>
 *   seller_agent / landlord_agent, MLS-linked → mls:<listing_key>
 *
 * READ-ONLY AND NON-DISPATCHING. It opens no provider connection, writes
 * nothing, and resolves from rows that already exist. A listing it cannot
 * resolve is not an error: a Bridge row with no listing_key has no durable
 * identity, so it returns null and the caller declines the preference rather
 * than storing one against a key that may later belong to something else.
 *
 * NO PARCEL OR ADDRESS GROUPING. Deliberate, and stated in
 * ListingPreferenceSubjectRef: grouping successive listings of one property is a
 * different question, answered by ExplorePropertyIdentity, and answering it here
 * would merge listings a customer may feel differently about.
 */
class ListingPreferenceSubjectResolver
{
    /**
     * @return ListingPreferenceSubjectRef|null null when the listing has no durable identity
     */
    public function resolve(SmartTagListingRef $ref): ?ListingPreferenceSubjectRef
    {
        if ($ref->type === SmartTagListingType::Bridge) {
            return $this->resolveBridge($ref);
        }

        return $this->resolveNative($ref);
    }

    /**
     * Resolve many refs of ONE listing type in a single query per type.
     *
     * The batched form exists because a results page resolves a page of
     * listings at once; a per-row resolve would be an N+1 against the meta
     * tables, which is the same mistake BridgeRelatedResourceService documents
     * avoiding.
     *
     * @param  list<SmartTagListingRef> $refs
     * @return array<string, ListingPreferenceSubjectRef> keyed "<type>:<id>", unresolvable refs omitted
     */
    public function resolveMany(array $refs): array
    {
        /** @var array<string, list<int>> $idsByType */
        $idsByType = [];

        foreach ($refs as $ref) {
            $idsByType[$ref->type->value][$ref->id] = $ref->id;
        }

        $out = [];

        foreach ($idsByType as $typeValue => $ids) {
            $type = SmartTagListingType::from($typeValue);
            $ids  = array_values($ids);

            $keys = $type === SmartTagListingType::Bridge
                ? $this->bridgeListingKeys($ids)
                : $this->nativeListingKeys($type, $ids);

            foreach ($ids as $id) {
                $ref       = new SmartTagListingRef($type, $id);
                $listingKey = $keys[$id] ?? null;

                if (is_string($listingKey) && trim($listingKey) !== '') {
                    $out["{$typeValue}:{$id}"] = ListingPreferenceSubjectRef::mls($ref, $listingKey);
                    continue;
                }

                // A Bridge row with no listing key has no durable identity at
                // all; a native row without one is simply not MLS-linked.
                if ($type !== SmartTagListingType::Bridge) {
                    $out["{$typeValue}:{$id}"] = ListingPreferenceSubjectRef::native($ref);
                }
            }
        }

        return $out;
    }

    private function resolveBridge(SmartTagListingRef $ref): ?ListingPreferenceSubjectRef
    {
        $listingKey = $this->bridgeListingKeys([$ref->id])[$ref->id] ?? null;

        if (! is_string($listingKey) || trim($listingKey) === '') {
            return null;
        }

        return ListingPreferenceSubjectRef::mls($ref, $listingKey);
    }

    private function resolveNative(SmartTagListingRef $ref): ListingPreferenceSubjectRef
    {
        $listingKey = $this->nativeListingKeys($ref->type, [$ref->id])[$ref->id] ?? null;

        if (is_string($listingKey) && trim($listingKey) !== '') {
            return ListingPreferenceSubjectRef::mls($ref, $listingKey);
        }

        return ListingPreferenceSubjectRef::native($ref);
    }

    /**
     * @param  list<int> $ids
     * @return array<int, string>
     */
    private function bridgeListingKeys(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return BridgeProperty::query()
            ->whereIn('id', $ids)
            ->whereNotNull('listing_key')
            ->pluck('listing_key', 'id')
            ->map(static fn ($v): string => (string) $v)
            ->all();
    }

    /**
     * @param  list<int> $ids
     * @return array<int, string>
     */
    private function nativeListingKeys(SmartTagListingType $type, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        [$metaClass, $foreignKey] = match ($type) {
            SmartTagListingType::SellerAgent   => [SellerAgentAuctionMeta::class, 'seller_agent_auction_id'],
            SmartTagListingType::LandlordAgent => [LandlordAgentAuctionMeta::class, 'landlord_agent_auction_id'],
            default                            => [null, null],
        };

        if ($metaClass === null) {
            return [];
        }

        return $metaClass::query()
            ->where('meta_key', MlsQuickImportDraftWriter::META_LISTING_KEY)
            ->whereIn($foreignKey, $ids)
            ->whereNotNull('meta_value')
            ->pluck('meta_value', $foreignKey)
            ->map(static fn ($v): string => (string) $v)
            ->all();
    }
}
