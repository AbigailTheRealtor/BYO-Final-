<?php

namespace App\Services\LocationDna;

use App\Contracts\SchoolDistrictAdapterInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CensusSchoolDistrictAdapter implements SchoolDistrictAdapterInterface
{
    /**
     * U.S. Census TIGER/Line Unified School District ArcGIS REST endpoint.
     *
     * This layer contains Unified School District boundaries from the Census
     * TIGER/Line dataset. Unified districts serve all grades K-12 and cover
     * the majority of the U.S. population, making them the most broadly useful
     * single layer for a real-estate context.
     *
     * ArcGIS REST reference (Census TIGER School Districts MapServer):
     * https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/School_Districts/MapServer/0
     */
    private const ENDPOINT = 'https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/School_Districts/MapServer/0/query';

    /**
     * Maximum number of features to request per call (ArcGIS server cap).
     */
    private const MAX_RECORDS = 200;

    /**
     * @inheritDoc
     */
    public function lookup(array $bbox): array
    {
        if (count($bbox) < 4) {
            return [];
        }

        [$minLng, $minLat, $maxLng, $maxLat] = $bbox;

        $ttl      = (int) config('location_dna.school_district_cache_ttl', 86400);
        $cacheKey = 'census_school_districts_' . md5(implode(',', [
            round($minLng, 4),
            round($minLat, 4),
            round($maxLng, 4),
            round($maxLat, 4),
        ]));

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $result = $this->fetchFromCensus($minLng, $minLat, $maxLng, $maxLat);
            // Only cache successful responses; failures bubble as exceptions
            // so the caller retries on the next request.
            Cache::put($cacheKey, $result, $ttl);
            return $result;
        } catch (\Throwable $e) {
            Log::warning('CensusSchoolDistrictAdapter: lookup failed', [
                'bbox'  => $bbox,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Perform the actual HTTP request to the Census TIGER ArcGIS REST API.
     *
     * @throws \RuntimeException  On non-2xx HTTP status or unexpected response shape
     */
    private function fetchFromCensus(
        float $minLng, float $minLat, float $maxLng, float $maxLat
    ): array {
        $timeout  = (int) config('location_dna.school_district_timeout', 15);
        $endpoint = config('location_dna.school_district_endpoint', self::ENDPOINT);

        $response = Http::timeout($timeout)->get($endpoint, [
            'geometry'          => "{$minLng},{$minLat},{$maxLng},{$maxLat}",
            'geometryType'      => 'esriGeometryEnvelope',
            'inSR'              => '4326',
            'spatialRel'        => 'esriSpatialRelIntersects',
            'outFields'         => 'NAME',
            'returnGeometry'    => 'true',
            'outSR'             => '4326',
            'resultRecordCount' => self::MAX_RECORDS,
            'f'                 => 'geojson',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Census TIGER School Districts API returned HTTP ' . $response->status()
            );
        }

        $body = $response->json();

        if (!isset($body['features']) || !is_array($body['features'])) {
            return [];
        }

        return $this->parseFeatures($body['features']);
    }

    /**
     * Parse GeoJSON features from the Census API into the normalized return shape.
     *
     * @param  array  $features  GeoJSON features array
     * @return array
     */
    private function parseFeatures(array $features): array
    {
        $results = [];

        foreach ($features as $feature) {
            $name = $feature['properties']['NAME'] ?? null;
            if ($name === null || $name === '') {
                continue;
            }

            $geometry = $feature['geometry'] ?? null;
            if (!$geometry || !isset($geometry['coordinates'])) {
                continue;
            }

            $type   = $geometry['type'] ?? '';
            $coords = $geometry['coordinates'];

            if ($type === 'Polygon') {
                $results[] = [
                    'district_name' => (string) $name,
                    'rings'         => $coords,
                ];
            } elseif ($type === 'MultiPolygon') {
                // Each polygon in a MultiPolygon becomes its own entry
                foreach ($coords as $polygonRings) {
                    $results[] = [
                        'district_name' => (string) $name,
                        'rings'         => $polygonRings,
                    ];
                }
            }
        }

        return $results;
    }
}
