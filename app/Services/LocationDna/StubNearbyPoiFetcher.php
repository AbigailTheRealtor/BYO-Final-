<?php

namespace App\Services\LocationDna;

use App\Contracts\NearbyPoiFetcherInterface;

/**
 * StubNearbyPoiFetcher — inert raw POI fetcher.
 *
 * Bound in place of {@see GooglePlacesPoiAdapter} when the provider registry selects no
 * live Google provider (mirrors {@see StubPoiLookupAdapter}). Returns no candidates and
 * makes no outbound call. In the production flow it is unreachable: the whole-run
 * kill-switch and API-key guards in `LocationDnaPoiDistanceService::calculateForListing()`
 * short-circuit before any fetch when Google is unavailable.
 */
class StubNearbyPoiFetcher implements NearbyPoiFetcherInterface
{
    public function fetchNearby(float $lat, float $lng, array $meta): array
    {
        return [];
    }
}
