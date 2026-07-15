<?php

namespace App\Contracts;

/**
 * NearbyPoiFetcherInterface — the production Location DNA path's raw POI fetch seam.
 *
 * WHY THIS EXISTS (Phase 1, Batch 1)
 * ----------------------------------
 * `LocationDnaPoiDistanceService` (the launch-critical enrichment writer) used to call
 * Google's Nearby Search inline via raw Guzzle. This interface moves that outbound call
 * behind an adapter so the service is indifferent to who supplies the candidates. The
 * Phase 3a `CorpusPoiAdapter` will implement this same interface, returning corpus-native
 * rows, with no change to the service.
 *
 * This is deliberately SEPARATE from {@see PoiLookupAdapterInterface}. That interface
 * returns a NORMALISED 9-key envelope for the buyer/tenant search-area path. This one
 * returns the RAW provider `results` rows — because the production path's exclusion rules,
 * transit dedup, ranking (via `PoiCandidate::fromGooglePlaces()`), and persistence all read
 * the provider-native shape (`geometry.location.lat`, `types`, `rating`,
 * `user_ratings_total`, `vicinity`). Narrowing to the normalised envelope here would strip
 * those fields and change ranking and persisted output.
 *
 * EXCEPTION CONTRACT — LOAD-BEARING
 * ---------------------------------
 * Implementations MUST let HTTP/provider exceptions PROPAGATE. The caller
 * (`LocationDnaPoiDistanceService::fetchAndPersistCategoryMulti`) distinguishes a thrown
 * error (→ persists `status = 'error'` with the message) from an empty result set
 * (→ persists `status = 'not_found'`). Swallowing an exception and returning `[]` would
 * silently reclassify a fetch failure as "nothing nearby". This differs from
 * `PoiLookupAdapterInterface::search()`, which swallows for graceful buyer/tenant degradation.
 */
interface NearbyPoiFetcherInterface
{
    /**
     * Fetch raw nearby POI candidates for one category around a coordinate.
     *
     * @param  float  $lat   Search-centre latitude.
     * @param  float  $lng   Search-centre longitude.
     * @param  array  $meta  The category descriptor from
     *                       `LocationDnaPoiDistanceService::CATEGORIES` — reads
     *                       `google_type` and `keyword`.
     * @return array         Indexed array of raw, provider-native result rows (the shape a
     *                       Google Places Nearby Search `results` element carries). Empty
     *                       array when the provider returned no results. Exceptions
     *                       propagate — they are NOT converted to `[]`.
     */
    public function fetchNearby(float $lat, float $lng, array $meta): array;
}
