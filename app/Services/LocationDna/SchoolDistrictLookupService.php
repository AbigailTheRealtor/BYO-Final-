<?php

namespace App\Services\LocationDna;

use App\Contracts\SchoolDistrictAdapterInterface;
use Illuminate\Support\Facades\Log;

class SchoolDistrictLookupService
{
    /**
     * Miles-per-degree constant used for radius → degree conversion.
     * 1° latitude ≈ 69.0 statute miles.
     */
    private const MILES_PER_DEGREE = 69.0;

    public function __construct(private SchoolDistrictAdapterInterface $adapter)
    {
    }

    /**
     * Resolve school district polygons for the active location boundary.
     *
     * Derives a bounding envelope from the resolved boundary geometry, enforces
     * a configurable area threshold to prevent expensive lookups over huge areas,
     * then delegates to the SchoolDistrictAdapterInterface implementation.
     *
     * @param  array  $boundaryData  Payload from BoundaryLookupService::resolve()
     *                               ['geojson_polygons' => [...], 'fallback' => bool]
     * @param  array  $preferences   Decoded location_dna_preferences array
     * @return array  ['school_districts' => [...], 'available' => bool]
     *                school_districts: array of ['district_name' => string, 'rings' => array]
     *                available:        false when lookup was skipped or returned no data
     */
    public function resolve(array $boundaryData, array $preferences): array
    {
        $unavailable = ['school_districts' => [], 'available' => false];

        $polygons   = $preferences['polygons']        ?? [];
        $radii      = $preferences['radius_searches'] ?? [];
        $isFallback = $boundaryData['fallback']        ?? true;

        // When BoundaryLookupService fell back and there are no drawn geometries,
        // there is nothing to derive a bounding box from — skip the Census call.
        if ($isFallback && empty($polygons) && empty($radii)) {
            return $unavailable;
        }

        $bbox = $this->deriveBbox($boundaryData, $polygons, $radii);
        if ($bbox === null) {
            return $unavailable;
        }

        // Area guard: skip the Census call for unreasonably large areas.
        $area    = ($bbox[2] - $bbox[0]) * ($bbox[3] - $bbox[1]);
        $maxArea = (float) config('location_dna.school_district_max_area_sq_degrees', 2.0);

        if ($area > $maxArea) {
            Log::warning('SchoolDistrictLookupService: skipping Census lookup — bounding area exceeds threshold', [
                'area_sq_degrees'      => round($area, 4),
                'threshold_sq_degrees' => $maxArea,
                'bbox'                 => $bbox,
            ]);
            return $unavailable;
        }

        $schoolDistricts = $this->adapter->lookup($bbox);

        if (empty($schoolDistricts)) {
            return $unavailable;
        }

        return [
            'school_districts' => $schoolDistricts,
            'available'        => true,
        ];
    }

    /**
     * Derive a [minLng, minLat, maxLng, maxLat] bounding envelope from
     * the available geometry sources.
     *
     * Priority:
     *   1. GeoJSON boundary polygons (Tiers 3-5, resolved by BoundaryLookupService)
     *   2. Drawn polygons from location_dna_preferences
     *   3. Radius circles from location_dna_preferences
     *
     * @return array|null  [minLng, minLat, maxLng, maxLat] or null when no geometry
     */
    private function deriveBbox(array $boundaryData, array $polygons, array $radii): ?array
    {
        $minLat =  PHP_FLOAT_MAX;
        $maxLat = -PHP_FLOAT_MAX;
        $minLng =  PHP_FLOAT_MAX;
        $maxLng = -PHP_FLOAT_MAX;
        $hasPoints = false;

        // 1. GeoJSON polygons from BoundaryLookupService (each entry is one boundary's
        //    array of polygons, where each polygon is an array of rings, and each ring
        //    is an array of [lng, lat] coordinate pairs).
        $geoPolygons = $boundaryData['geojson_polygons'] ?? [];
        foreach ($geoPolygons as $boundaryPolygons) {
            if (!is_array($boundaryPolygons)) continue;
            foreach ($boundaryPolygons as $rings) {
                if (!is_array($rings)) continue;
                // Only use the exterior ring (index 0) for bbox — holes skew the envelope
                $exterior = $rings[0] ?? [];
                foreach ($exterior as $coord) {
                    if (!isset($coord[0], $coord[1])) continue;
                    $lng = (float) $coord[0];
                    $lat = (float) $coord[1];
                    if ($lat < $minLat) $minLat = $lat;
                    if ($lat > $maxLat) $maxLat = $lat;
                    if ($lng < $minLng) $minLng = $lng;
                    if ($lng > $maxLng) $maxLng = $lng;
                    $hasPoints = true;
                }
            }
        }

        // 2. Drawn polygons from preferences (path is [{lat, lng}] object array)
        foreach ($polygons as $poly) {
            $path = $poly['path'] ?? [];
            if (!is_array($path)) continue;
            foreach ($path as $pt) {
                if (!isset($pt['lat'], $pt['lng'])) continue;
                $lat = (float) $pt['lat'];
                $lng = (float) $pt['lng'];
                if ($lat < $minLat) $minLat = $lat;
                if ($lat > $maxLat) $maxLat = $lat;
                if ($lng < $minLng) $minLng = $lng;
                if ($lng > $maxLng) $maxLng = $lng;
                $hasPoints = true;
            }
        }

        // 3. Radius circles from preferences
        foreach ($radii as $r) {
            $center = $r['center'] ?? null;
            $miles  = (float) ($r['radius_miles'] ?? 0);
            if (!isset($center['lat'], $center['lng']) || $miles <= 0) continue;
            $lat    = (float) $center['lat'];
            $lng    = (float) $center['lng'];
            $degLat = $miles / self::MILES_PER_DEGREE;
            // Longitude degrees per mile varies with latitude; use cos(lat) adjustment
            $degLng = ($lat !== 0.0)
                ? $miles / (self::MILES_PER_DEGREE * cos(deg2rad(abs($lat))))
                : $degLat;
            if (($lat - $degLat) < $minLat) $minLat = $lat - $degLat;
            if (($lat + $degLat) > $maxLat) $maxLat = $lat + $degLat;
            if (($lng - $degLng) < $minLng) $minLng = $lng - $degLng;
            if (($lng + $degLng) > $maxLng) $maxLng = $lng + $degLng;
            $hasPoints = true;
        }

        if (!$hasPoints) {
            return null;
        }

        // Small padding (0.01°) to ensure boundary-edge districts are captured
        return [
            round($minLng - 0.01, 6),
            round($minLat - 0.01, 6),
            round($maxLng + 0.01, 6),
            round($maxLat + 0.01, 6),
        ];
    }
}
