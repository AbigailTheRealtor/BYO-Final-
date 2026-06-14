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

        // Cache as a JSON string — see FemaFloodZoneAdapter::lookup() for rationale.
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            if (is_string($cached)) {
                try {
                    return json_decode($cached, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    Log::warning('CensusSchoolDistrictAdapter: cache JSON decode failed — evicting and fetching fresh', [
                        'cache_key' => $cacheKey,
                        'error'     => $e->getMessage(),
                    ]);
                    Cache::forget($cacheKey);
                    // fall through to live fetch below
                }
            } else {
                // Legacy PHP-serialized array (pre-JSON-cache format); return as-is.
                return $cached;
            }
        }

        try {
            $result = $this->fetchFromCensus($minLng, $minLat, $maxLng, $maxLat);
            Cache::put($cacheKey, json_encode($result, JSON_THROW_ON_ERROR), $ttl);
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
                    'rings'         => $this->truncateCoords($coords),
                ];
            } elseif ($type === 'MultiPolygon') {
                // Each polygon in a MultiPolygon becomes its own entry
                foreach ($coords as $polygonRings) {
                    $results[] = [
                        'district_name' => (string) $name,
                        'rings'         => $this->truncateCoords($polygonRings),
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Round every coordinate pair in a rings array to 5 decimal places.
     *
     * 5 decimal places ≈ 1 m accuracy — more than sufficient for school-district
     * polygon visualisation.  Reducing coordinate precision shrinks the
     * serialised payload compared with raw Census output.
     *
     * @param  array  $rings  [[lng, lat], …] pairs grouped into exterior/hole rings
     * @return array
     */
    private function truncateCoords(array $rings): array
    {
        return array_map(function (array $ring) {
            return array_map(function (array $coord) {
                return [
                    round((float) ($coord[0] ?? 0), 5),
                    round((float) ($coord[1] ?? 0), 5),
                ];
            }, $ring);
        }, $rings);
    }
}
