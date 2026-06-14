<?php

namespace App\Services\LocationDna;

use App\Contracts\CommuteTimeAdapterInterface;

class CommuteTimeStubAdapter implements CommuteTimeAdapterInterface
{
    /**
     * Return deterministic zero-value results for every destination × travel_mode pair.
     *
     * No HTTP calls are made. All result fields are null except 'source' which is
     * always 'stub'. This adapter exists solely to satisfy the interface contract
     * during development and testing, until a real provider is wired.
     *
     * @inheritDoc
     */
    public function lookup(
        float $originLat,
        float $originLng,
        array $destinations,
        array $travelModes
    ): array {
        $results = [];

        foreach ($destinations as $destination) {
            foreach ($travelModes as $mode) {
                $results[] = [
                    'destination_label'   => $destination['label']   ?? '',
                    'destination_address' => $destination['address'] ?? '',
                    'destination_lat'     => isset($destination['lat']) ? (float) $destination['lat'] : 0.0,
                    'destination_lng'     => isset($destination['lng']) ? (float) $destination['lng'] : 0.0,
                    'travel_mode'         => $mode,
                    'travel_time_minutes' => null,
                    'distance_miles'      => null,
                    'source'              => 'stub',
                ];
            }
        }

        return $results;
    }
}
