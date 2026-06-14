<?php

namespace App\Services\LocationDna;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * LocationDnaEnrichmentRunner
 *
 * Fan-out runner that executes all four Location DNA enrichment services
 * from a single call and returns a unified payload.
 *
 * Each service is called independently inside its own try/catch so that a
 * failure in one service never prevents the remaining services from running.
 * On exception the failed service's empty/fallback payload is used and a
 * Log::warning is emitted; no exception escapes to the caller.
 *
 * This class is infrastructure only — it performs no database writes,
 * scoring, geocoding, or external API calls of its own.
 *
 * Return shape:
 * [
 *   'floodZones'      => FloodZoneLookupService result (or fallback),
 *   'schoolDistricts' => SchoolDistrictLookupService result (or fallback),
 *   'pois'            => PoiDistanceLookupService result (or fallback),
 *   'commuteTimes'    => CommuteTimeLookupService result (or fallback),
 * ]
 */
class LocationDnaEnrichmentRunner
{
    public function __construct(
        private readonly FloodZoneLookupService      $floodZoneService,
        private readonly SchoolDistrictLookupService $schoolDistrictService,
        private readonly PoiDistanceLookupService    $poiDistanceLookupService,
        private readonly CommuteTimeLookupService    $commuteTimeLookupService,
    ) {}

    /**
     * Execute all four enrichment services and return a unified payload.
     *
     * @param  array  $boundaryData  Payload from BoundaryLookupService::resolve()
     *                               ['geojson_polygons' => [...], 'fallback' => bool]
     * @param  array  $preferences   Decoded location_dna_preferences array.
     *                               Recognised keys used by this runner:
     *                                 polygons, radius_searches — for flood/school/POI geometry
     *                                 commute_origin            — ['lat', 'lng'] for commute origin
     *                                 commute_destinations      — array of destination entries
     *                                 travel_modes              — optional, defaults to ['driving']
     * @return array  ['floodZones' => [...], 'schoolDistricts' => [...], 'pois' => [...], 'commuteTimes' => [...]]
     */
    public function run(array $boundaryData, array $preferences): array
    {
        return [
            'floodZones'      => $this->runFloodZones($boundaryData, $preferences),
            'schoolDistricts' => $this->runSchoolDistricts($boundaryData, $preferences),
            'pois'            => $this->runPois($boundaryData, $preferences),
            'commuteTimes'    => $this->runCommuteTimes($boundaryData, $preferences),
        ];
    }

    // -------------------------------------------------------------------------
    // Per-service runners (each isolated in its own try/catch)
    // -------------------------------------------------------------------------

    private function runFloodZones(array $boundaryData, array $preferences): array
    {
        try {
            return $this->floodZoneService->resolve($boundaryData, $preferences);
        } catch (Throwable $e) {
            Log::warning('LocationDnaEnrichmentRunner: FloodZoneLookupService failed', [
                'error' => $e->getMessage(),
            ]);
            return ['flood_zones' => [], 'available' => false];
        }
    }

    private function runSchoolDistricts(array $boundaryData, array $preferences): array
    {
        try {
            return $this->schoolDistrictService->resolve($boundaryData, $preferences);
        } catch (Throwable $e) {
            Log::warning('LocationDnaEnrichmentRunner: SchoolDistrictLookupService failed', [
                'error' => $e->getMessage(),
            ]);
            return ['school_districts' => [], 'available' => false];
        }
    }

    private function runPois(array $boundaryData, array $preferences): array
    {
        $fallback = ['results' => [], 'error' => null, 'source_lat' => null, 'source_lng' => null];

        $geometry = $this->derivePoiGeometry($boundaryData, $preferences);
        if ($geometry === null) {
            return $fallback;
        }

        try {
            return $this->poiDistanceLookupService->lookup($geometry);
        } catch (Throwable $e) {
            Log::warning('LocationDnaEnrichmentRunner: PoiDistanceLookupService failed', [
                'error' => $e->getMessage(),
            ]);
            return $fallback;
        }
    }

    private function runCommuteTimes(array $boundaryData, array $preferences): array
    {
        $origin       = $preferences['commute_origin']       ?? null;
        $destinations = $preferences['commute_destinations'] ?? [];

        if (!isset($origin['lat'], $origin['lng']) || empty($destinations)) {
            return [];
        }

        $travelModes = $preferences['travel_modes'] ?? ['driving'];

        try {
            return $this->commuteTimeLookupService->resolve(
                (float) $origin['lat'],
                (float) $origin['lng'],
                $destinations,
                $travelModes,
            );
        } catch (Throwable $e) {
            Log::warning('LocationDnaEnrichmentRunner: CommuteTimeLookupService failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    // -------------------------------------------------------------------------
    // Geometry derivation helpers
    // -------------------------------------------------------------------------

    /**
     * Derive a PoiDistanceLookupService-compatible geometry array from the
     * available boundary data and preferences.
     *
     * Priority:
     *   1. First valid radius_search entry from preferences
     *   2. First drawn polygon (≥3 points) from preferences
     *   3. First exterior ring (≥3 coords) from geojson_polygons in boundaryData
     *
     * Returns null when no usable geometry can be derived.
     */
    private function derivePoiGeometry(array $boundaryData, array $preferences): ?array
    {
        // 1. Radius searches from preferences
        foreach ($preferences['radius_searches'] ?? [] as $r) {
            if (isset($r['center']['lat'], $r['center']['lng']) && ((float) ($r['radius_miles'] ?? 0)) > 0) {
                return [
                    'type'         => 'radius',
                    'lat'          => (float) $r['center']['lat'],
                    'lng'          => (float) $r['center']['lng'],
                    'radius_miles' => (int) $r['radius_miles'],
                ];
            }
        }

        // 2. Drawn polygons from preferences
        foreach ($preferences['polygons'] ?? [] as $poly) {
            $path = $poly['path'] ?? [];
            if (!is_array($path) || count($path) < 3) {
                continue;
            }
            $coordinates = array_map(
                fn (array $pt) => [(float) $pt['lng'], (float) $pt['lat']],
                array_filter($path, fn (array $pt) => isset($pt['lat'], $pt['lng']))
            );
            if (count($coordinates) >= 3) {
                return ['type' => 'polygon', 'coordinates' => array_values($coordinates)];
            }
        }

        // 3. GeoJSON polygons from boundaryData
        foreach ($boundaryData['geojson_polygons'] ?? [] as $boundaryPolygons) {
            if (!is_array($boundaryPolygons)) {
                continue;
            }
            foreach ($boundaryPolygons as $rings) {
                if (!is_array($rings)) {
                    continue;
                }
                $exterior = $rings[0] ?? [];
                if (count($exterior) < 3) {
                    continue;
                }
                $coordinates = array_values(array_filter(
                    array_map(
                        fn ($c) => isset($c[0], $c[1]) ? [(float) $c[0], (float) $c[1]] : null,
                        $exterior
                    )
                ));
                if (count($coordinates) >= 3) {
                    return ['type' => 'polygon', 'coordinates' => $coordinates];
                }
            }
        }

        return null;
    }
}
