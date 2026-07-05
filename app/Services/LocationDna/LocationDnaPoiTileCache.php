<?php

namespace App\Services\LocationDna;

use App\Services\LocationDna\Providers\LocationProviderRegistry;
use Illuminate\Support\Facades\Cache;

/**
 * LocationDnaPoiTileCache — Spatial tile cache for Google Places raw candidates.
 *
 * Converts (lat, lng, google_type, keyword, provider-surface) into a stable tile
 * cache key by rounding coordinates to a configurable decimal precision read from
 * config('location_dna.poi.tile_precision').
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
    private readonly ?float  $precision;
    private readonly int     $ttl;
    private readonly ?string $storeName;

    /**
     * Provider-surface token (Stage E0): a short prefix of the active provider
     * capabilityHash, mixed into every tile key so raw candidates fetched under
     * one provider configuration are never reused under another. Constant while
     * only Google is enabled, so existing production behavior is unchanged apart
     * from a one-time key change (self-healing within the tile TTL).
     */
    private readonly string $capabilityToken;

    public function __construct()
    {
        $raw = config('location_dna.poi.tile_precision');

        $this->precision = ($raw !== null && $raw !== '' && is_numeric($raw))
            ? (float) $raw
            : null;

        $this->ttl = (int) config('location_dna.poi.tile_cache_ttl', 604800);

        $this->capabilityToken = substr(
            (new LocationProviderRegistry((array) config('location_providers', [])))->capabilityHash(),
            0,
            16
        );

        // Resolve the backing store name. null → application default store
        // (correct for production: a persistent, cross-process store). Coerce
        // empty string to null so a blank env value falls back to the default.
        $store = config('location_dna.poi.tile_cache_store');
        $this->storeName = ($store !== null && $store !== '') ? (string) $store : null;
    }

    /**
     * The cache store name backing this tile cache, or null when the application
     * default store is used. Resolves the effective store for diagnostics/flush.
     */
    public function storeName(): ?string
    {
        return $this->storeName;
    }

    /**
     * The configured cache repository. Passing null to Cache::store() returns the
     * application default store, so a null storeName transparently uses it.
     */
    private function store(): \Illuminate\Contracts\Cache\Repository
    {
        return Cache::store($this->storeName);
    }

    /**
     * Flush all entries in the backing store. Used by the benchmark command to
     * prevent cross-precision contamination between runs. No-op when disabled.
     *
     * NOTE: this flushes the ENTIRE store, not just tile keys — only call it in
     * benchmark/CLI contexts pointed at a dedicated/array store, never against a
     * shared production cache.
     */
    public function flush(): void
    {
        $this->store()->flush();
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
            $this->capabilityToken,
        ]);

        return 'ldna_poi_tile_' . hash('sha256', $raw);
    }

    /**
     * Retrieve raw candidates from the tile cache.
     *
     * Uses the configured backing store (default: application default store, a
     * persistent cross-process cache in production). Returns null when disabled,
     * the key is absent, or the TTL has expired.
     */
    public function get(string $tileKey): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $cached = $this->store()->get($tileKey);

        return is_array($cached) ? $cached : null;
    }

    /**
     * Store raw candidates in the tile cache.
     *
     * Uses the configured backing store. No-op when tile cache is disabled.
     */
    public function put(string $tileKey, array $rawCandidates): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->store()->put($tileKey, $rawCandidates, $this->ttl);
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
