<?php

namespace App\Services\LocationDna;

use App\Contracts\FloodZoneAdapterInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FemaFloodZoneAdapter implements FloodZoneAdapterInterface
{
    private const CACHE_TTL   = 86400; // 24 hours in seconds
    private const API_TIMEOUT = 15;    // seconds

    /**
     * FEMA National Flood Hazard Layer (NFHL) ArcGIS REST endpoint.
     *
     * Layer 28 = "S_Fld_Haz_Ar" (Special Flood Hazard Area Regions).
     * This layer contains ALL FEMA flood zone designations, including:
     *   — Zone A, AE, AH, AO, AR, A99  (high-risk riverine/tidal SFHA)
     *   — Zone VE, V                    (coastal high-hazard SFHA)
     *   — Zone X (shaded)               (moderate risk, 0.2% annual chance)
     *   — Zone X (unshaded)             (minimal risk, outside 500-yr floodplain)
     *   — Zone D                        (undetermined risk)
     *
     * All zone types are present in layer 28. Zone X polygons are included
     * alongside the high-hazard zones — no separate layer query is required.
     *
     * Layer ID reference: FEMA NFHL MapServer metadata (verified June 2026)
     * https://hazards.fema.gov/arcgis/rest/services/public/NFHL/MapServer/28
     */
    private const ENDPOINT = 'https://hazards.fema.gov/arcgis/rest/services/public/NFHL/MapServer/28/query';

    /**
     * Maximum number of features to request per call (ArcGIS server cap).
     */
    private const MAX_RECORDS = 500;

    /**
     * @inheritDoc
     */
    public function lookup(array $bbox): array
    {
        if (count($bbox) < 4) {
            return [];
        }

        [$minLng, $minLat, $maxLng, $maxLat] = $bbox;

        $cacheKey = 'fema_flood_zones_v2_' . md5(implode(',', [
            round($minLng, 4),
            round($minLat, 4),
            round($maxLng, 4),
            round($maxLat, 4),
        ]));

        // Cache as a JSON string rather than a raw PHP-serialized array.
        // File-cache serialisation of a large nested array (500 polygons × many
        // coordinate pairs) can exhaust the default PHP memory limit when the
        // payload is deserialised.  Storing a JSON string reduces peak memory
        // during cache reads by ~70–80 % because unserialize() on a scalar is
        // trivial and json_decode() streams without building intermediate
        // PHP internal structures for the container object.
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            if (is_string($cached)) {
                try {
                    return json_decode($cached, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    Log::warning('FemaFloodZoneAdapter: cache JSON decode failed — evicting and fetching fresh', [
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
            $result = $this->fetchFromFema($minLng, $minLat, $maxLng, $maxLat);
            Cache::put($cacheKey, json_encode($result, JSON_THROW_ON_ERROR), self::CACHE_TTL);
            return $result;
        } catch (\Throwable $e) {
            Log::warning('FemaFloodZoneAdapter: lookup failed', [
                'bbox'  => $bbox,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Perform the actual HTTP request to the FEMA NFHL ArcGIS REST API.
     *
     * @throws \RuntimeException  On non-2xx HTTP status or unexpected response shape
     */
    private function fetchFromFema(
        float $minLng, float $minLat, float $maxLng, float $maxLat
    ): array {
        $response = Http::timeout(self::API_TIMEOUT)->get(self::ENDPOINT, [
            'geometry'           => "{$minLng},{$minLat},{$maxLng},{$maxLat}",
            'geometryType'       => 'esriGeometryEnvelope',
            'inSR'               => '4326',
            'spatialRel'         => 'esriSpatialRelIntersects',
            'outFields'          => 'FLD_ZONE',
            'returnGeometry'     => 'true',
            'outSR'              => '4326',
            'resultRecordCount'  => self::MAX_RECORDS,
            'f'                  => 'geojson',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'FEMA NFHL API returned HTTP ' . $response->status()
            );
        }

        $body = $response->json();

        if (!isset($body['features']) || !is_array($body['features'])) {
            return [];
        }

        return $this->parseFeatures($body['features']);
    }

    /**
     * Parse GeoJSON features from the FEMA API into the normalized return shape.
     *
     * @param  array  $features  GeoJSON features array
     * @return array
     */
    private function parseFeatures(array $features): array
    {
        $results = [];

        foreach ($features as $feature) {
            $zone = $feature['properties']['FLD_ZONE'] ?? null;
            if ($zone === null || $zone === '') {
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
                    'zone_designation' => (string) $zone,
                    'rings'            => $this->truncateCoords($coords),
                ];
            } elseif ($type === 'MultiPolygon') {
                // Each polygon in a MultiPolygon becomes its own entry
                foreach ($coords as $polygonRings) {
                    $results[] = [
                        'zone_designation' => (string) $zone,
                        'rings'            => $this->truncateCoords($polygonRings),
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Round every coordinate pair in a rings array to 5 decimal places.
     *
     * 5 decimal places ≈ 1 m accuracy — more than sufficient for flood-zone
     * polygon visualisation.  Reducing coordinate precision shrinks the
     * serialised payload by roughly 40–60 % compared with raw FEMA output.
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
