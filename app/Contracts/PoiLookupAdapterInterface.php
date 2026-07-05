<?php

namespace App\Contracts;

interface PoiLookupAdapterInterface
{
    /**
     * Search for points of interest near a coordinate.
     *
     * @param  float   $lat          Latitude of the search centre
     * @param  float   $lng          Longitude of the search centre
     * @param  string  $category     One of: schools, parks, shopping, hospitals, gyms, airports, downtown
     * @param  int     $radiusMiles  Search radius in miles
     * @param  int     $limit        Maximum number of results to return
     * @return array   Indexed array of normalised POI items. Each item carries exactly 9 keys:
     *                   - 'category'       string       Canonical category slug (e.g. 'schools')
     *                   - 'name'           string       POI display name
     *                   - 'address'        string       Human-readable address or vicinity
     *                   - 'latitude'       float        POI latitude
     *                   - 'longitude'      float        POI longitude
     *                   - 'distance_miles' float        Straight-line distance from search centre in miles
     *                   - 'source'         string        Provider identifier (e.g. 'google_places')
     *                   - 'confidence'     float|null   Provider confidence in this item, 0.0–1.0
     *                                                    (docs/canonical-field-mapping-spec.md §2). null when
     *                                                    the provider offers no basis for a score.
     *                   - 'last_refreshed' string|null  UTC ISO-8601 timestamp of when this item was
     *                                                    fetched/derived (canonical-field-mapping-spec §4).
     *                                                    null when the provider cannot supply one.
     *                 Returns [] on any provider error so callers always degrade gracefully.
     *
     *                 `confidence`/`last_refreshed` are the canonical-envelope metadata (Stage D). They are
     *                 additive: consumers that predate them simply ignore the extra keys. NOTE: they extend the
     *                 in-memory adapter contract only — they are NOT persisted to property_location_pois by this
     *                 path (persistence is a later, separately-reviewed phase).
     */
    public function search(float $lat, float $lng, string $category, int $radiusMiles, int $limit): array;
}
