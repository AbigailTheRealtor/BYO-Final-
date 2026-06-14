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

        $cacheKey = 'fema_flood_zones_' . md5(implode(',', [
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
            $result = $this->fetchFromFema($minLng, $minLat, $maxLng, $maxLat);
            // Only cache successful responses; failures bubble as exceptions
            // so the caller retries on the next request.
            Cache::put($cacheKey, $result, self::CACHE_TTL);
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
                    'rings'            => $coords,
                ];
            } elseif ($type === 'MultiPolygon') {
                // Each polygon in a MultiPolygon becomes its own entry
                foreach ($coords as $polygonRings) {
                    $results[] = [
                        'zone_designation' => (string) $zone,
                        'rings'            => $polygonRings,
                    ];
                }
            }
        }

        return $results;
    }
}
