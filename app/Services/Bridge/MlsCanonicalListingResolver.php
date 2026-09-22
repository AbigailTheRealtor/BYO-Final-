<?php

namespace App\Services\Bridge;

use App\Models\BridgeProperty;
use App\Services\Canonical\Adapters\MlsListingAdapter;
use App\Services\Canonical\CanonicalListing;
use App\Support\Listing\MlsProvider;

/**
 * MlsCanonicalListingResolver — the MLS entry point to the canonical listing
 * seam (P0-5).
 *
 *   BridgeProperty → BridgePropertyCandidateAdapter → PropertyCandidate
 *                  → MlsListingAdapter → CanonicalListing
 *
 * GOVERNANCE: read-only and LOCAL. It reads the `bridge_properties` row we
 * already hold — never BridgeListingLookupService, which falls back to the Bridge
 * API — and writes nothing: no row, no meta, no cache, no job.
 *
 * DELIBERATELY SEPARATE FROM CanonicalListingResolver. That resolver's
 * supports() gates ComputeLocationDna's chain into ComputeDnaScores, and
 * ComputeLocationDna is already dispatched with `bridge` by the MLS importers; if
 * it answered true for `bridge`, enabling DNA score generation would start
 * scoring every MLS row. Wiring MLS into DNA scores is a later, gated decision.
 *
 * INERT IN P0-5: no live surface calls this. Consumers adopt it one at a time,
 * each behind its own decision.
 */
final class MlsCanonicalListingResolver
{
    public function __construct(
        private readonly BridgePropertyCandidateAdapter $candidates = new BridgePropertyCandidateAdapter(),
        private readonly MlsListingAdapter $adapter = new MlsListingAdapter(),
    ) {}

    /** The canonical listing of a held MLS row, or null when it cannot be named. */
    public function forBridgeProperty(BridgeProperty $row): ?CanonicalListing
    {
        return $this->adapter->fromCandidate($this->candidates->fromModel($row));
    }

    /**
     * The canonical listing of the ONE local row carrying (provider, listingKey),
     * or null. Always provider-scoped: a ListingKey is unique only within its
     * provider, so the same key under another provider is another listing.
     */
    public function forNativeKey(MlsProvider $provider, string $listingKey): ?CanonicalListing
    {
        $listingKey = trim($listingKey);

        if ($listingKey === '') {
            return null;
        }

        $row = BridgeProperty::forNativeKey($provider, $listingKey)->first();

        return $row === null ? null : $this->forBridgeProperty($row);
    }
}
