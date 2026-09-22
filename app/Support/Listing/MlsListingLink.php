<?php

namespace App\Support\Listing;

use App\Models\BridgeProperty;

/**
 * MlsListingLink — which MLS provider issued a bare ListingKey, where that is
 * UNAMBIGUOUS.
 *
 * A native BidYourOffer listing linked to the MLS stores `mls_listing_key` — the
 * key, with no governed provider beside it — because it was written when one MLS
 * was the only MLS. Since `UNIQUE(provider, listing_key)` replaced the global
 * unique, that key alone no longer names one record.
 *
 * THE RULE: a key resolves to a provider only when EXACTLY ONE local
 * `bridge_properties` row carries it AND that row's provider is recognised.
 *
 *   · Ambiguity is counted in ROWS, not in recognised providers. If a second row
 *     also holds the key, discarding it because its provider is unrecognised
 *     would resolve the key to Stellar by elimination — the silent substitution
 *     the provider-scoped identity exists to prevent.
 *   · No row, several rows, or one unrecognised row: the key is not in the
 *     result. The caller decides what "unlinked" means for it.
 *
 * Read-only, local only: one query per call, no network, no writes. The durable
 * fix is still to stamp the provider beside the key on the import write path;
 * the Quick Import `mls_provider` meta is a legacy transport label (`'bridge'`),
 * not a governed provider, and is deliberately NOT read here.
 */
final class MlsListingLink
{
    /**
     * @param  iterable<string> $listingKeys keys exactly as stored
     * @return array<string, MlsProvider>    key => the one provider that issued it
     */
    public static function providersForListingKeys(iterable $listingKeys): array
    {
        $keys = [];
        foreach ($listingKeys as $key) {
            if (is_string($key) && $key !== '') {
                $keys[$key] = true;
            }
        }

        if ($keys === []) {
            return [];
        }

        $seen = [];

        BridgeProperty::query()
            ->whereIn('listing_key', array_keys($keys))
            ->get(['provider', 'listing_key'])
            ->each(function (BridgeProperty $row) use (&$seen): void {
                $seen[(string) $row->listing_key][] = $row->mlsProvider();
            });

        $out = [];

        foreach ($seen as $key => $providers) {
            if (count($providers) !== 1 || ! $providers[0] instanceof MlsProvider) {
                continue;
            }

            $out[(string) $key] = $providers[0];
        }

        return $out;
    }

    /** The one provider that issued this key, or null when unlinked or ambiguous. */
    public static function providerForListingKey(?string $listingKey): ?MlsProvider
    {
        if ($listingKey === null || $listingKey === '') {
            return null;
        }

        return self::providersForListingKeys([$listingKey])[$listingKey] ?? null;
    }
}
