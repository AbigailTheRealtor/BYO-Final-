<?php

namespace App\Services\LocationDna;

use App\Contracts\PoiLookupAdapterInterface;
use Illuminate\Support\Facades\Cache;
use Throwable;

class PoiDistanceLookupService
{
    private const SUPPORTED_CATEGORIES = [
        'schools', 'parks', 'shopping', 'hospitals', 'gyms', 'airports', 'downtown',
    ];

    private readonly int   $maxRadiusMiles;
    private readonly int   $categoryResultLimit;
    private readonly int   $cacheTtl;

    public function __construct(private readonly PoiLookupAdapterInterface $adapter)
    {
        $this->maxRadiusMiles      = (int) config('location_dna.poi.max_radius_miles', 25);
        $this->categoryResultLimit = (int) config('location_dna.poi.category_result_limit', 5);
        $this->cacheTtl            = (int) config('location_dna.poi.cache_ttl', 86400);
    }

    /**
     * Look up POIs for a given search-area geometry.
     *
     * @param  array  $geometry    One of three shapes:
     *                               {type:'point',  lat, lng}
     *                               {type:'radius', lat, lng, radius_miles}
     *                               {type:'polygon', coordinates:[[lng,lat],...]}
     * @param  array  $categories  Subset of supported category slugs. Empty = all categories.
     * @return array  [
     *                  'results'    => array   Normalised POI items sorted by distance_miles asc,
     *                  'error'      => string|null,
     *                  'source_lat' => float|null,
     *                  'source_lng' => float|null,
     *                ]
     */
    public function lookup(array $geometry, array $categories = []): array
    {
        // Validate geometry
        if (empty($geometry) || !isset($geometry['type'])) {
            return $this->errorResponse('Invalid geometry: missing or null type key');
        }

        $type = $geometry['type'];

        if (!in_array($type, ['point', 'radius', 'polygon'], true)) {
            return $this->errorResponse("Invalid geometry type: '{$type}'");
        }

        // Derive centre coordinates
        try {
            [$sourceLat, $sourceLng] = $this->deriveCenter($geometry);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }

        // Determine radius
        $radiusMiles = $this->deriveRadius($geometry);

        // Resolve categories
        $resolvedCategories = empty($categories) ? self::SUPPORTED_CATEGORIES : array_values(
            array_intersect($categories, self::SUPPORTED_CATEGORIES)
        );

        if (empty($resolvedCategories)) {
            return $this->errorResponse('No valid categories requested');
        }

        // Cache lookup
        $cacheKey = 'poi_lookup_' . sha1(serialize($geometry) . serialize($resolvedCategories));

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Call adapter for each category and merge results
        $allResults = [];

        foreach ($resolvedCategories as $category) {
            try {
                $items = $this->adapter->search(
                    $sourceLat,
                    $sourceLng,
                    $category,
                    $radiusMiles,
                    $this->categoryResultLimit,
                );

                foreach ($items as $item) {
                    $allResults[] = $item;
                }
            } catch (Throwable $e) {
                $result = [
                    'results'    => [],
                    'error'      => 'Adapter error: ' . $e->getMessage(),
                    'source_lat' => $sourceLat,
                    'source_lng' => $sourceLng,
                ];
                Cache::put($cacheKey, $result, $this->cacheTtl);
                return $result;
            }
        }

        // Sort ascending by distance_miles
        usort($allResults, fn($a, $b) => $a['distance_miles'] <=> $b['distance_miles']);

        $result = [
            'results'    => $allResults,
            'error'      => null,
            'source_lat' => $sourceLat,
            'source_lng' => $sourceLng,
        ];

        Cache::put($cacheKey, $result, $this->cacheTtl);

        return $result;
    }

    /**
     * Derive the search centre [lat, lng] from the geometry payload.
     *
     * @throws \InvalidArgumentException  When required keys are missing
     */
    private function deriveCenter(array $geometry): array
    {
        switch ($geometry['type']) {
            case 'point':
            case 'radius':
                if (!isset($geometry['lat'], $geometry['lng'])) {
                    throw new \InvalidArgumentException(
                        "Geometry type '{$geometry['type']}' requires lat and lng keys"
                    );
                }
                return [(float) $geometry['lat'], (float) $geometry['lng']];

            case 'polygon':
                $coords = $geometry['coordinates'] ?? [];
                if (empty($coords)) {
                    throw new \InvalidArgumentException(
                        'Polygon geometry requires at least one coordinate pair'
                    );
                }
                // Centroid = average of vertex coordinates ([lng, lat] order)
                $sumLat = 0.0;
                $sumLng = 0.0;
                $count  = 0;
                foreach ($coords as $pair) {
                    if (!isset($pair[0], $pair[1])) continue;
                    $sumLng += (float) $pair[0];
                    $sumLat += (float) $pair[1];
                    $count++;
                }
                if ($count === 0) {
                    throw new \InvalidArgumentException(
                        'Polygon geometry has no valid coordinate pairs'
                    );
                }
                return [$sumLat / $count, $sumLng / $count];
        }

        throw new \InvalidArgumentException("Unrecognised geometry type: '{$geometry['type']}'");
    }

    /**
     * Determine the search radius in miles from the geometry, capped at max_radius_miles.
     */
    private function deriveRadius(array $geometry): int
    {
        if ($geometry['type'] === 'radius' && isset($geometry['radius_miles'])) {
            $requested = (int) $geometry['radius_miles'];
            return min($requested, $this->maxRadiusMiles);
        }

        // point and polygon always use the configured maximum radius
        return $this->maxRadiusMiles;
    }

    private function errorResponse(string $error): array
    {
        return [
            'results'    => [],
            'error'      => $error,
            'source_lat' => null,
            'source_lng' => null,
        ];
    }
}
