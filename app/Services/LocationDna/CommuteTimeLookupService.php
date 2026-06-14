<?php

namespace App\Services\LocationDna;

use App\Contracts\CommuteTimeAdapterInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CommuteTimeLookupService
{
    public function __construct(private CommuteTimeAdapterInterface $adapter)
    {
    }

    /**
     * Resolve commute times from an origin point to a list of destinations.
     *
     * Behaviour:
     *   - Returns [] immediately when $destinations is empty.
     *   - Silently skips any destination entry missing 'lat' or 'lng'.
     *   - Enforces max_destinations from config, truncating excess entries
     *     with a Log::warning when the limit is exceeded.
     *   - Builds a cache key from origin + valid destinations + travel modes;
     *     returns cached results without calling the adapter when present.
     *   - On any adapter \Throwable, logs a warning and returns [].
     *   - Stores successful adapter responses in cache using the configured TTL.
     *
     * @param  float   $originLat     Origin latitude
     * @param  float   $originLng     Origin longitude
     * @param  array   $destinations  Each entry: ['label', 'address', 'lat', 'lng']
     * @param  array   $travelModes   Subset of ['driving', 'walking', 'transit']
     * @return array   Flat normalized result entries (one per destination × mode)
     */
    public function resolve(
        float $originLat,
        float $originLng,
        array $destinations,
        array $travelModes = ['driving']
    ): array {
        if (empty($destinations)) {
            return [];
        }

        $valid = array_values(array_filter(
            $destinations,
            fn (array $d) => isset($d['lat'], $d['lng'])
        ));

        $maxDestinations = (int) config('location_dna.commute_time.max_destinations', 10);

        if (count($valid) > $maxDestinations) {
            Log::warning('CommuteTimeLookupService: destinations list exceeds max_destinations — truncating', [
                'provided'         => count($valid),
                'max_destinations' => $maxDestinations,
            ]);
            $valid = array_slice($valid, 0, $maxDestinations);
        }

        $cacheTtl = (int) config('location_dna.commute_time.cache_ttl', 86400);
        $cacheKey = $this->buildCacheKey($originLat, $originLng, $valid, $travelModes);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $results = $this->adapter->lookup($originLat, $originLng, $valid, $travelModes);
        } catch (\Throwable $e) {
            Log::warning('CommuteTimeLookupService: adapter threw an exception', [
                'origin_lat' => $originLat,
                'origin_lng' => $originLng,
                'error'      => $e->getMessage(),
            ]);
            return [];
        }

        Cache::put($cacheKey, $results, $cacheTtl);

        return $results;
    }

    /**
     * Build a deterministic cache key from origin, valid destinations, and modes.
     *
     * Any change to origin coordinates, the filtered destination list, or the
     * travel modes produces a different key.
     */
    private function buildCacheKey(
        float $originLat,
        float $originLng,
        array $destinations,
        array $travelModes
    ): string {
        $destFingerprint = array_map(
            fn (array $d) => implode('|', [
                round((float) $d['lat'], 6),
                round((float) $d['lng'], 6),
                $d['label']   ?? '',
                $d['address'] ?? '',
            ]),
            $destinations
        );

        sort($travelModes);

        $raw = implode(':', [
            round($originLat, 6),
            round($originLng, 6),
            implode(';', $destFingerprint),
            implode(',', $travelModes),
        ]);

        return 'commute_time_' . md5($raw);
    }
}
