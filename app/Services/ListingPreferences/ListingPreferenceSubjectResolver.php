<?php

namespace App\Services\ListingPreferences;

use App\Models\BridgeProperty;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\SellerAgentAuctionMeta;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;
use App\Support\Listing\MlsListingLink;
use App\Support\Listing\MlsProvider;
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

            $isBridge = $type === SmartTagListingType::Bridge;

            // Bridge rows carry (provider, key) on the row itself. Native rows
            // carry only the key, so their providers are resolved in ONE further
            // query for the whole batch rather than one per listing.
            $identities = $isBridge ? $this->bridgeListingKeys($ids) : [];
            $nativeKeys = $isBridge ? [] : $this->nativeListingKeys($type, $ids);
            $providers  = $isBridge ? [] : $this->providersForListingKeys(array_values($nativeKeys));

            foreach ($ids as $id) {
                $ref = new SmartTagListingRef($type, $id);

                if ($isBridge) {
                    $identity = $identities[$id] ?? null;

                    if ($identity !== null) {
                        [$provider, $listingKey] = $identity;
                        $out["{$typeValue}:{$id}"] = ListingPreferenceSubjectRef::mls($ref, $provider, $listingKey);
                    }

                    // A Bridge row with no listing key, or none whose provider we
                    // recognise, has no durable identity at all — it is omitted
                    // rather than given an invented one.
                    continue;
                }

                $listingKey = $nativeKeys[$id] ?? null;
                $provider   = is_string($listingKey) ? ($providers[$listingKey] ?? null) : null;

                $out["{$typeValue}:{$id}"] = ($provider !== null && trim((string) $listingKey) !== '')
                    ? ListingPreferenceSubjectRef::mls($ref, $provider, $listingKey)
                    : ListingPreferenceSubjectRef::native($ref);
            }
        }

        return $out;
    }

    private function resolveBridge(SmartTagListingRef $ref): ?ListingPreferenceSubjectRef
    {
        $identity = $this->bridgeListingKeys([$ref->id])[$ref->id] ?? null;

        if ($identity === null) {
            return null;
        }

        [$provider, $listingKey] = $identity;

        return ListingPreferenceSubjectRef::mls($ref, $provider, $listingKey);
    }

    private function resolveNative(SmartTagListingRef $ref): ListingPreferenceSubjectRef
    {
        $listingKey = $this->nativeListingKeys($ref->type, [$ref->id])[$ref->id] ?? null;

        if (is_string($listingKey) && trim($listingKey) !== '') {
            $provider = $this->providersForListingKeys([$listingKey])[$listingKey] ?? null;

            if ($provider !== null) {
                return ListingPreferenceSubjectRef::mls($ref, $provider, $listingKey);
            }
        }

        return ListingPreferenceSubjectRef::native($ref);
    }

    /**
     * @param  list<int> $ids
     * @return array<int, array{0: MlsProvider, 1: string}> id => [provider, listing key]
     */
    private function bridgeListingKeys(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $out = [];

        // Both halves of the native identity, read from the row itself. The
        // provider is a stored column since P0-1; nothing here infers it from
        // `listing_type`, a route, an attribution string or a display label.
        BridgeProperty::query()
            ->whereIn('id', $ids)
            ->whereNotNull('listing_key')
            ->get(['id', 'provider', 'listing_key'])
            ->each(function (BridgeProperty $row) use (&$out): void {
                $provider = $row->mlsProvider();

                // An unrecognised provider is a row whose origin we cannot name.
                // It gets no MLS subject rather than being read as Stellar's.
                if ($provider === null) {
                    return;
                }

                $out[$row->id] = [$provider, (string) $row->listing_key];
            });

        return $out;
    }

    /**
     * Which provider issued each of these listing keys, where that is
     * UNAMBIGUOUS.
     *
     * A native BidYourOffer listing stores only `mls_listing_key` — the key, with
     * no provider beside it — because it was written when one MLS was the only
     * MLS. Since `UNIQUE(provider, listing_key)` replaced the global unique, that
     * key alone no longer names one row.
     *
     * So the provider is resolved FROM the linked Bridge record, and only when
     * exactly one row carries the key. Two providers holding it is precisely the
     * state P0-2 made legal, and picking either one would attach a customer's
     * preference to a house they may never have seen. Such a key resolves to
     * nothing here and the listing falls back to its own `byo:` subject — it
     * simply stops unifying with the MLS row, which is a visible, harmless
     * limitation rather than a silent mis-attribution.
     *
     * THE DURABLE FIX IS NOT HERE: the import writer should stamp the provider
     * beside the key it already stores, so this lookup becomes unnecessary. That
     * is a write-path change and belongs with the provider-adapter work.
     *
     * @param  list<string> $listingKeys
     * @return array<string, MlsProvider>
     */
    private function providersForListingKeys(array $listingKeys): array
    {
        // The exactly-one-recognised-row rule lives in MlsListingLink, so every
        // caller that must turn a bare ListingKey into (provider, key) applies it
        // identically.
        return MlsListingLink::providersForListingKeys($listingKeys);
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
