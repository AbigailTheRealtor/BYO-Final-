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
     * @return array   Indexed array of normalised POI items. Each item carries exactly 7 keys:
     *                   - 'category'       string  Canonical category slug (e.g. 'schools')
     *                   - 'name'           string  POI display name
     *                   - 'address'        string  Human-readable address or vicinity
     *                   - 'latitude'       float   POI latitude
     *                   - 'longitude'      float   POI longitude
     *                   - 'distance_miles' float   Straight-line distance from search centre in miles
     *                   - 'source'         string  Provider identifier (e.g. 'google_places')
     *                 Returns [] on any provider error so callers always degrade gracefully.
     */
    public function search(float $lat, float $lng, string $category, int $radiusMiles, int $limit): array;
}
