<?php

namespace App\Services\LocationDna;

use Illuminate\Support\Facades\Cache;

/**
 * LocationDnaPoiTileCache — Spatial tile cache for Google Places raw candidates.
 *
 * Converts (lat, lng, google_type, keyword) into a stable tile cache key by rounding
 * coordinates to a configurable decimal precision read from config('location_dna.poi.tile_precision').
 *
 * Safe disabled/default mode:
 *   When LOCATION_DNA_POI_TILE_PRECISION is absent or empty, the tile cache is disabled
 *   entirely and every listing fetches fresh from Google — no precision-related failure,
 *   no silent wrong-tile behaviour. The env value must be explicitly set to enable caching.
 *
 * TTL is configurable via LOCATION_DNA_POI_TILE_CACHE_TTL (default 7 days = 604800 seconds).
 */
class LocationDnaPoiTileCache
{
    private readonly ?float $precision;
    private readonly int    $ttl;

    public function __construct()
    {
        $raw = config('location_dna.poi.tile_precision');

        $this->precision = ($raw !== null && $raw !== '' && is_numeric($raw))
            ? (float) $raw
            : null;

        $this->ttl = (int) config('location_dna.poi.tile_cache_ttl', 604800);
    }

    /**
     * Whether tile caching is enabled (precision is explicitly configured).
     */
    public function isEnabled(): bool
    {
        return $this->precision !== null && $this->precision > 0;
    }

    /**
     * Return the configured tile precision, or null when disabled.
     */
    public function getPrecision(): ?float
    {
        return $this->precision;
    }

    /**
     * Build a stable cache key from the category meta + rounded coordinates.
     *
     * @param  array  $meta       Category meta array (keys: google_type, keyword).
     * @param  float  $sourceLat  Listing latitude.
     * @param  float  $sourceLng  Listing longitude.
     * @return string             SHA-256 tile key.
     */
    public function buildKey(array $meta, float $sourceLat, float $sourceLng): string
    {
        $precision = $this->precision ?? 0;

        $tiledLat = $this->tileCoordinate($sourceLat, $precision);
        $tiledLng = $this->tileCoordinate($sourceLng, $precision);

        $googleType = $meta['google_type'] ?? '';
        $keyword    = $meta['keyword'] ?? '';

        $raw = implode('|', [
            number_format($tiledLat, 10, '.', ''),
            number_format($tiledLng, 10, '.', ''),
            (string) $googleType,
            (string) $keyword,
            number_format($precision, 10, '.', ''),
        ]);

        return 'ldna_poi_tile_' . hash('sha256', $raw);
    }

    /**
     * Retrieve raw candidates from the tile cache.
     *
     * Always uses the array cache store so that the benchmark runner's
     * Cache::store('array')->flush() call reliably clears these entries.
     * Returns null when disabled, key is absent, or TTL has expired.
     */
    public function get(string $tileKey): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $cached = Cache::store('array')->get($tileKey);

        return is_array($cached) ? $cached : null;
    }

    /**
     * Store raw candidates in the tile cache.
     *
     * Always uses the array cache store (in-process, per-request scope).
     * No-op when tile cache is disabled.
     */
    public function put(string $tileKey, array $rawCandidates): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        Cache::store('array')->put($tileKey, $rawCandidates, $this->ttl);
    }

    /**
     * Round a coordinate to the tile grid at the given decimal precision.
     *
     * e.g. precision=0.005: 27.9506 → floor(27.9506/0.005)*0.005 = 27.950
     */
    public function tileCoordinate(float $coordinate, float $precision): float
    {
        if ($precision <= 0) {
            return $coordinate;
        }

        return floor($coordinate / $precision) * $precision;
    }
}
