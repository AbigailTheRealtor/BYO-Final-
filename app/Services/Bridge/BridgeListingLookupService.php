<?php

namespace App\Services\Bridge;

use App\Jobs\ComputeLocationDna;
use App\Models\BridgeProperty;
use App\Services\Property\PropertyCandidate;
use Illuminate\Support\Collection;

/**
 * Single-record MLS lookup with a local-first, API-second, cache-on-miss
 * strategy. This is the shared backend seam for BOTH planned workflows
 * (Seller/Landlord prefill and Buyer/Tenant match analysis) — it returns
 * provider-agnostic PropertyCandidate objects and never touches any UI.
 *
 * Lookup order for every method:
 *   1. LOCAL  — query the bridge_properties cache first (fast path, no API call).
 *   2. API    — on a local miss, query the Bridge OData API with a targeted
 *               $filter, upsert the returned record(s) into bridge_properties,
 *               and (for new/changed rows) dispatch ComputeLocationDna to prime
 *               Location DNA — mirroring the existing import paths.
 *   3. RETURN — map the resulting BridgeProperty row(s) to PropertyCandidate.
 *
 * The Bridge OData API is a search endpoint (returns a `value[]` array); there
 * is no key-addressable single-record endpoint, so single-record lookups are
 * expressed as an equality $filter with a page size of 1.
 */
class BridgeListingLookupService
{
    /** Max records returned/upserted for an address search in one call. */
    public const ADDRESS_RESULT_LIMIT = 25;

    public function __construct(
        private readonly BridgeApiService $api,
        private readonly BridgePropertyNormalizer $normalizer,
        private readonly BridgePropertyCandidateAdapter $adapter,
    ) {}

    /**
     * Look up by human-facing MLS Number (RESO ListingId).
     * Local `listing_id` match first; then `ListingId eq '...'` against the API.
     */
    public function findByMlsNumber(string $mlsNumber): ?PropertyCandidate
    {
        $mlsNumber = trim($mlsNumber);
        if ($mlsNumber === '') {
            return null;
        }

        $local = BridgeProperty::where('listing_id', $mlsNumber)->first();
        if ($local !== null) {
            return $this->adapter->fromModel($local);
        }

        $model = $this->fetchOneAndCache("ListingId eq '" . $this->escape($mlsNumber) . "'");

        return $model !== null ? $this->adapter->fromModel($model) : null;
    }

    /**
     * Look up by globally-unique RESO ListingKey.
     * Local `listing_key` match first; then `ListingKey eq '...'` against the API.
     */
    public function findByListingKey(string $listingKey): ?PropertyCandidate
    {
        $listingKey = trim($listingKey);
        if ($listingKey === '') {
            return null;
        }

        $local = BridgeProperty::where('listing_key', $listingKey)->first();
        if ($local !== null) {
            return $this->adapter->fromModel($local);
        }

        $model = $this->fetchOneAndCache("ListingKey eq '" . $this->escape($listingKey) . "'");

        return $model !== null ? $this->adapter->fromModel($model) : null;
    }

    /**
     * Search by address parts. Returns 0..N candidates — the multi-result case
     * (condos/units at one address) is what drives the "choose the right one"
     * UI in a later phase.
     *
     * Accepted keys (all optional; empties ignored):
     *   street_number, street_name, city, state, postal_code, address (freeform)
     *
     * Local cache is queried first; only on an empty local result does it hit
     * the API and cache what it finds.
     *
     * @param  array<string,string> $parts
     * @return Collection<int,PropertyCandidate>
     */
    public function searchByAddress(array $parts): Collection
    {
        $parts = $this->normalizeParts($parts);
        if (empty($parts)) {
            return collect();
        }

        $local = $this->queryLocalByAddress($parts);
        if ($local->isNotEmpty()) {
            return $local->map(fn (BridgeProperty $m) => $this->adapter->fromModel($m))->values();
        }

        $filter = $this->buildAddressFilter($parts);
        if ($filter === null) {
            return collect();
        }

        $records = $this->api->fetchProperties(self::ADDRESS_RESULT_LIMIT, $filter);

        $models = [];
        foreach ($records as $record) {
            $model = $this->cacheRecord($record);
            if ($model !== null) {
                $models[] = $model;
            }
        }

        return collect($models)
            ->map(fn (BridgeProperty $m) => $this->adapter->fromModel($m))
            ->values();
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    /**
     * Fetch a single record for an equality filter and cache it.
     * Returns null when the API yields nothing or the record is unusable
     * (e.g. missing ListingKey).
     */
    private function fetchOneAndCache(string $filter): ?BridgeProperty
    {
        $records = $this->api->fetchProperties(1, $filter);
        if (empty($records)) {
            return null;
        }

        return $this->cacheRecord($records[0]);
    }

    /**
     * Upsert one raw API record into bridge_properties. Dispatches
     * ComputeLocationDna for new/address-changed rows, matching
     * ImportBridgeProperties and LazyBridgeImportService.
     */
    private function cacheRecord(array $record): ?BridgeProperty
    {
        $result = $this->normalizer->upsert($record);
        if ($result === null) {
            return null;
        }

        if ($result->shouldDispatchDna()) {
            ComputeLocationDna::dispatch('bridge', $result->model->id);
        }

        return $result->model;
    }

    /**
     * @param  array<string,string> $parts
     * @return Collection<int,BridgeProperty>
     */
    private function queryLocalByAddress(array $parts): Collection
    {
        $query   = BridgeProperty::query();
        $applied = false;

        if (isset($parts['postal_code'])) {
            $query->where('postal_code', $parts['postal_code']);
            $applied = true;
        }
        if (isset($parts['city'])) {
            $query->whereRaw('LOWER(city) = ?', [mb_strtolower($parts['city'])]);
            $applied = true;
        }
        if (isset($parts['state'])) {
            $query->where('state_or_province', strtoupper($parts['state']));
            $applied = true;
        }

        $addressTerm = $this->addressTerm($parts);
        if ($addressTerm !== null) {
            $query->where('unparsed_address', 'like', '%' . $addressTerm . '%');
            $applied = true;
        }

        if (!$applied) {
            return collect();
        }

        return $query->orderByDesc('modification_timestamp')
            ->limit(self::ADDRESS_RESULT_LIMIT)
            ->get();
    }

    /**
     * Build an OData $filter from address parts. Structured parts take
     * precedence; a freeform `address` is only used when no parts are present.
     *
     * @param  array<string,string> $parts
     */
    private function buildAddressFilter(array $parts): ?string
    {
        $clauses = [];

        if (isset($parts['street_number'])) {
            $clauses[] = "StreetNumber eq '" . $this->escape($parts['street_number']) . "'";
        }
        if (isset($parts['street_name'])) {
            $clauses[] = "contains(StreetName,'" . $this->escape($parts['street_name']) . "')";
        }
        if (isset($parts['city'])) {
            $clauses[] = "City eq '" . $this->escape($parts['city']) . "'";
        }
        if (isset($parts['state'])) {
            $clauses[] = "StateOrProvince eq '" . $this->escape(strtoupper($parts['state'])) . "'";
        }
        if (isset($parts['postal_code'])) {
            $clauses[] = "PostalCode eq '" . $this->escape($parts['postal_code']) . "'";
        }

        if (empty($clauses) && isset($parts['address'])) {
            $clauses[] = "contains(UnparsedAddress,'" . $this->escape($parts['address']) . "')";
        }

        return empty($clauses) ? null : implode(' and ', $clauses);
    }

    /**
     * The freeform street term used for a local LIKE match: an explicit
     * `address`, else `street_number street_name` joined.
     *
     * @param  array<string,string> $parts
     */
    private function addressTerm(array $parts): ?string
    {
        if (isset($parts['address'])) {
            return $parts['address'];
        }

        $joined = trim(($parts['street_number'] ?? '') . ' ' . ($parts['street_name'] ?? ''));

        return $joined !== '' ? $joined : null;
    }

    /**
     * Keep only recognised keys, trim whitespace, drop empty values.
     *
     * @param  array<string,mixed>  $parts
     * @return array<string,string>
     */
    private function normalizeParts(array $parts): array
    {
        $allowed = ['street_number', 'street_name', 'city', 'state', 'postal_code', 'address'];
        $out     = [];

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $parts) || $parts[$key] === null) {
                continue;
            }
            $value = trim((string) $parts[$key]);
            if ($value !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * Escape a value for an OData single-quoted literal (double the quote).
     */
    private function escape(string $value): string
    {
        return str_replace("'", "''", $value);
    }
}
