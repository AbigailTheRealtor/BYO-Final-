<?php

namespace App\Contracts;

interface CommuteTimeAdapterInterface
{
    /**
     * Look up commute times from an origin to one or more destinations.
     *
     * @param  float   $originLat     Origin latitude
     * @param  float   $originLng     Origin longitude
     * @param  array   $destinations  Indexed array of destination entries, each containing:
     *                                  - 'label'   string  Human-readable name
     *                                  - 'address' string  Street address
     *                                  - 'lat'     float   Destination latitude
     *                                  - 'lng'     float   Destination longitude
     * @param  array   $travelModes   Subset of ['driving', 'walking', 'transit']
     * @return array   Flat indexed array of normalized result entries, one per
     *                 destination × travel_mode combination. Each entry contains:
     *                   - 'destination_label'   string
     *                   - 'destination_address' string
     *                   - 'destination_lat'     float
     *                   - 'destination_lng'     float
     *                   - 'travel_mode'         string
     *                   - 'travel_time_minutes' int|null
     *                   - 'distance_miles'      float|null
     *                   - 'source'              string
     *                   - 'confidence'          float|null   Provider confidence 0.0–1.0
     *                                                        (docs/canonical-field-mapping-spec.md §2);
     *                                                        null when no basis for a score.
     *                   - 'last_refreshed'      string|null  UTC ISO-8601 fetch/derive time (§4);
     *                                                        null when the provider cannot supply one.
     *                 Returns [] on any failure so callers degrade gracefully.
     *
     *                 `confidence`/`last_refreshed` are additive canonical-envelope metadata (Stage D);
     *                 they extend the in-memory contract only and are not persisted by this path.
     */
    public function lookup(
        float $originLat,
        float $originLng,
        array $destinations,
        array $travelModes
    ): array;
}
