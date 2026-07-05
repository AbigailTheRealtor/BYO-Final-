<?php

namespace Tests\Unit\Services\LocationDna;

use App\Services\LocationDna\LocationDnaPoiTileCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * LocationDnaPoiTileCacheTest
 *
 * Verifies tile key generation stability, get/put round-trips, and disabled-mode
 * behaviour for all four candidate precision levels.
 *
 * Test coverage:
 *   (A) isEnabled() — false when precision absent/empty/zero; true when set
 *   (B) tileCoordinate() — rounds correctly at each of the four candidate precisions
 *   (C) buildKey() — stable key for coordinates in the same tile; different key across tiles
 *   (D) buildKey() — different key for different google_type or keyword
 *   (E) get() / put() round-trip — stores and retrieves raw candidates correctly
 *   (F) get() returns null when disabled
 *   (G) put() is a no-op when disabled
 *   (H) Key stability — identical inputs always produce identical keys
 */
class LocationDnaPoiTileCacheTest extends TestCase
{
    private const SAMPLE_META = [
        'google_type' => 'park',
        'keyword'     => null,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default'                  => 'array',
            'location_dna.poi.tile_precision' => null,
            'location_dna.poi.tile_cache_ttl' => 604800,
        ]);
        Cache::flush();
    }

    // =========================================================================
    // (A) isEnabled()
    // =========================================================================

    /** @test */
    public function is_enabled_returns_false_when_precision_is_null(): void
    {
        config(['location_dna.poi.tile_precision' => null]);
        $cache = new LocationDnaPoiTileCache();
        $this->assertFalse($cache->isEnabled());
    }

    /** @test */
    public function is_enabled_returns_false_when_precision_is_empty_string(): void
    {
        config(['location_dna.poi.tile_precision' => '']);
        $cache = new LocationDnaPoiTileCache();
        $this->assertFalse($cache->isEnabled());
    }

    /** @test */
    public function is_enabled_returns_false_when_precision_is_zero(): void
    {
        config(['location_dna.poi.tile_precision' => '0']);
        $cache = new LocationDnaPoiTileCache();
        $this->assertFalse($cache->isEnabled());
    }

    /** @test */
    public function is_enabled_returns_true_for_each_candidate_precision(): void
    {
        foreach ([0.001, 0.0025, 0.005, 0.01] as $precision) {
            config(['location_dna.poi.tile_precision' => (string) $precision]);
            $cache = new LocationDnaPoiTileCache();
            $this->assertTrue($cache->isEnabled(),
                "isEnabled() must return true for precision={$precision}");
        }
    }

    // =========================================================================
    // (B) tileCoordinate() — rounds correctly at each candidate precision
    // =========================================================================

    /**
     * @test
     * @dataProvider tilePrecisionProvider
     */
    public function tile_coordinate_rounds_coordinate_down_to_tile_grid(float $precision, float $input, float $expected): void
    {
        config(['location_dna.poi.tile_precision' => (string) $precision]);
        $cache = new LocationDnaPoiTileCache();

        $result = $cache->tileCoordinate($input, $precision);

        $this->assertEqualsWithDelta($expected, $result, 1e-10,
            "tileCoordinate({$input}, {$precision}) should be {$expected}, got {$result}");
    }

    public static function tilePrecisionProvider(): array
    {
        return [
            // precision=0.001 (~100 m)
            '0.001_floor_27.9506' => [0.001, 27.9506, 27.950],
            '0.001_floor_-82.4572' => [0.001, -82.4572, -82.458],

            // precision=0.0025 (~250 m)
            '0.0025_floor_27.9506' => [0.0025, 27.9506, 27.950],
            '0.0025_floor_-82.4572' => [0.0025, -82.4572, -82.4575],

            // precision=0.005 (~500 m)
            '0.005_floor_27.9506' => [0.005, 27.9506, 27.950],
            '0.005_floor_-82.4572' => [0.005, -82.4572, -82.460],

            // precision=0.01 (~1 km)
            '0.01_floor_27.9506' => [0.01, 27.9506, 27.95],
            '0.01_floor_-82.4572' => [0.01, -82.4572, -82.46],
        ];
    }

    // =========================================================================
    // (C) buildKey() — same tile → same key; different tile → different key
    // =========================================================================

    /** @test */
    public function build_key_returns_same_key_for_coordinates_in_same_tile(): void
    {
        config(['location_dna.poi.tile_precision' => '0.005']);
        $cache = new LocationDnaPoiTileCache();

        // Both coordinates fall in the same 0.005° tile
        $key1 = $cache->buildKey(self::SAMPLE_META, 27.9506, -82.4572);
        $key2 = $cache->buildKey(self::SAMPLE_META, 27.9501, -82.4578);

        $this->assertSame($key1, $key2,
            'Coordinates within the same 0.005° tile must produce identical cache keys');
    }

    /** @test */
    public function build_key_returns_different_key_for_coordinates_in_different_tiles(): void
    {
        config(['location_dna.poi.tile_precision' => '0.005']);
        $cache = new LocationDnaPoiTileCache();

        $key1 = $cache->buildKey(self::SAMPLE_META, 27.900, -82.450);
        $key2 = $cache->buildKey(self::SAMPLE_META, 28.100, -82.450);  // 40+ tiles north

        $this->assertNotSame($key1, $key2,
            'Coordinates in different 0.005° tiles must produce different cache keys');
    }

    /** @test */
    public function build_key_for_precision_0001_differs_from_0010(): void
    {
        // Verify that precision is incorporated into the cache key so that two instances
        // configured with different precisions produce different keys for the same coordinates.
        // Uses exact integer-like lat/lng so tile rounding is deterministic.
        $meta = ['google_type' => 'park', 'keyword' => null];

        config(['location_dna.poi.tile_precision' => '0.001']);
        $cache001 = new LocationDnaPoiTileCache();
        $this->assertEqualsWithDelta(0.001, $cache001->getPrecision(), 1e-9,
            'Sanity: cache001 must have precision 0.001');

        config(['location_dna.poi.tile_precision' => '0.01']);
        $cache010 = new LocationDnaPoiTileCache();
        $this->assertEqualsWithDelta(0.01, $cache010->getPrecision(), 1e-9,
            'Sanity: cache010 must have precision 0.01');

        $key001 = $cache001->buildKey($meta, 10.0, -80.0);
        $key010 = $cache010->buildKey($meta, 10.0, -80.0);

        $this->assertNotSame($key001, $key010,
            'Precision 0.001 and 0.01 must produce different cache keys (precision embedded in key)');
    }

    // =========================================================================
    // (D) buildKey() differentiates by google_type and keyword
    // =========================================================================

    /** @test */
    public function build_key_differs_by_google_type(): void
    {
        config(['location_dna.poi.tile_precision' => '0.005']);
        $cache = new LocationDnaPoiTileCache();

        $metaPark = ['google_type' => 'park',      'keyword' => null];
        $metaGym  = ['google_type' => 'gym',       'keyword' => null];

        $key1 = $cache->buildKey($metaPark, 27.9506, -82.4572);
        $key2 = $cache->buildKey($metaGym,  27.9506, -82.4572);

        $this->assertNotSame($key1, $key2,
            'Different google_type values must produce different cache keys');
    }

    /**
     * Stage E0: the tile key is provider-aware — changing which providers are
     * enabled changes the capability surface and therefore the key, so raw
     * candidates from one provider config are never reused under another.
     *
     * @test
     */
    public function build_key_differs_when_provider_surface_changes(): void
    {
        config(['location_dna.poi.tile_precision' => '0.005']);

        // Baseline provider surface (from config/location_providers.php).
        $keyBaseline = (new LocationDnaPoiTileCache())
            ->buildKey(self::SAMPLE_META, 27.9506, -82.4572);

        // Enable an additional provider — the capability surface changes.
        config(['location_providers.providers.osm_overpass.enabled' => true]);
        $keyChanged = (new LocationDnaPoiTileCache())
            ->buildKey(self::SAMPLE_META, 27.9506, -82.4572);

        $this->assertNotSame($keyBaseline, $keyChanged,
            'Changing the enabled provider surface must change the tile cache key');
    }

    /** @test */
    public function build_key_is_stable_for_identical_provider_surface(): void
    {
        config(['location_dna.poi.tile_precision' => '0.005']);

        $key1 = (new LocationDnaPoiTileCache())->buildKey(self::SAMPLE_META, 27.9506, -82.4572);
        $key2 = (new LocationDnaPoiTileCache())->buildKey(self::SAMPLE_META, 27.9506, -82.4572);

        $this->assertSame($key1, $key2,
            'Identical inputs and provider surface must produce identical keys');
    }

    /** @test */
    public function build_key_differs_by_keyword(): void
    {
        config(['location_dna.poi.tile_precision' => '0.005']);
        $cache = new LocationDnaPoiTileCache();

        $metaNoKeyword  = ['google_type' => 'park', 'keyword' => null];
        $metaWaterfront = ['google_type' => 'park', 'keyword' => 'waterfront'];

        $key1 = $cache->buildKey($metaNoKeyword,  27.9506, -82.4572);
        $key2 = $cache->buildKey($metaWaterfront, 27.9506, -82.4572);

        $this->assertNotSame($key1, $key2,
            'Same google_type but different keyword must produce different cache keys');
    }

    // =========================================================================
    // (E) get() / put() round-trip
    // =========================================================================

    /** @test */
    public function get_and_put_round_trip_stores_and_retrieves_raw_candidates(): void
    {
        config(['location_dna.poi.tile_precision' => '0.005']);
        $cache = new LocationDnaPoiTileCache();

        $candidates = [
            ['name' => 'Riverside Park', 'geometry' => ['location' => ['lat' => 27.95, 'lng' => -82.46]]],
            ['name' => 'Bayview Park',   'geometry' => ['location' => ['lat' => 27.96, 'lng' => -82.47]]],
        ];

        $key = $cache->buildKey(self::SAMPLE_META, 27.9506, -82.4572);
        $cache->put($key, $candidates);

        $retrieved = $cache->get($key);

        $this->assertSame($candidates, $retrieved,
            'get() must return the same array that was put() into the cache');
    }

    /** @test */
    public function get_returns_null_for_unknown_key(): void
    {
        config(['location_dna.poi.tile_precision' => '0.005']);
        $cache = new LocationDnaPoiTileCache();

        $result = $cache->get('ldna_poi_tile_nonexistent_key');

        $this->assertNull($result);
    }

    // =========================================================================
    // (F) get() returns null when disabled
    // =========================================================================

    /** @test */
    public function get_returns_null_when_tile_cache_is_disabled(): void
    {
        config(['location_dna.poi.tile_precision' => null]);
        $cache = new LocationDnaPoiTileCache();

        // Manually put something in cache to ensure the guard isn't just a miss
        Cache::put('ldna_poi_tile_test_key', ['place' => 'data'], 3600);

        $result = $cache->get('ldna_poi_tile_test_key');

        $this->assertNull($result,
            'get() must return null when tile cache is disabled, even if cache key exists');
    }

    // =========================================================================
    // (G) put() is a no-op when disabled
    // =========================================================================

    /** @test */
    public function put_is_a_noop_when_tile_cache_is_disabled(): void
    {
        config(['location_dna.poi.tile_precision' => null]);
        $cache = new LocationDnaPoiTileCache();

        $key = 'ldna_poi_tile_noop_test';
        $cache->put($key, [['name' => 'Test Place']]);

        // Cache should NOT contain the value since tile cache is disabled
        $this->assertNull(Cache::get($key),
            'put() must be a no-op when tile cache is disabled');
    }

    // =========================================================================
    // (H) Key stability — identical inputs always produce identical keys
    // =========================================================================

    /** @test */
    public function build_key_is_deterministic_across_repeated_calls(): void
    {
        config(['location_dna.poi.tile_precision' => '0.005']);
        $cache = new LocationDnaPoiTileCache();

        $meta = ['google_type' => 'grocery_or_supermarket', 'keyword' => null];
        $lat  = 27.9506;
        $lng  = -82.4572;

        $keys = [];
        for ($i = 0; $i < 5; $i++) {
            $keys[] = $cache->buildKey($meta, $lat, $lng);
        }

        $this->assertCount(1, array_unique($keys),
            'buildKey() must always produce the same key for the same inputs');
    }

    // =========================================================================
    // (I) Backing store resolution — must honour config, not hardcode 'array'
    // =========================================================================

    /** @test */
    public function store_name_is_null_when_tile_cache_store_config_is_absent(): void
    {
        config(['location_dna.poi.tile_cache_store' => null]);
        $cache = new LocationDnaPoiTileCache();

        // null storeName means Cache::store(null) → application default store.
        $this->assertNull($cache->storeName());
    }

    /** @test */
    public function store_name_is_null_when_tile_cache_store_config_is_empty_string(): void
    {
        config(['location_dna.poi.tile_cache_store' => '']);
        $cache = new LocationDnaPoiTileCache();

        $this->assertNull($cache->storeName(),
            'An empty store config must fall back to the default store (null), not an empty name');
    }

    /** @test */
    public function store_name_reflects_configured_store(): void
    {
        config(['location_dna.poi.tile_cache_store' => 'array']);
        $cache = new LocationDnaPoiTileCache();

        $this->assertSame('array', $cache->storeName());
    }

    /** @test */
    public function get_and_put_route_through_the_configured_store_not_a_hardcoded_one(): void
    {
        // Define a second, named array-backed store distinct from the default,
        // then point the tile cache at it. Proves get/put honour config rather
        // than the previously hardcoded Cache::store('array').
        config([
            'cache.stores.ldna_dedicated' => ['driver' => 'array'],
            'location_dna.poi.tile_precision'   => '0.005',
            'location_dna.poi.tile_cache_store' => 'ldna_dedicated',
        ]);

        $cache = new LocationDnaPoiTileCache();
        $key   = 'ldna_poi_tile_routing_test';
        $value = [['name' => 'Routed Place']];

        $cache->put($key, $value);

        // Value lands in the dedicated store...
        $this->assertSame($value, Cache::store('ldna_dedicated')->get($key));
        // ...and is readable back through the tile cache.
        $this->assertSame($value, $cache->get($key));
        // ...but NOT in the application default store (which is 'array' in tests).
        $this->assertNull(Cache::store('array')->get($key),
            'Tile cache must not write to the default store when another store is configured');
    }

    /** @test */
    public function flush_clears_entries_in_the_configured_store(): void
    {
        config([
            'cache.stores.ldna_dedicated' => ['driver' => 'array'],
            'location_dna.poi.tile_precision'   => '0.005',
            'location_dna.poi.tile_cache_store' => 'ldna_dedicated',
        ]);

        $cache = new LocationDnaPoiTileCache();
        $cache->put('ldna_poi_tile_flush_test', [['name' => 'Doomed Place']]);
        $this->assertNotNull($cache->get('ldna_poi_tile_flush_test'));

        $cache->flush();

        $this->assertNull($cache->get('ldna_poi_tile_flush_test'),
            'flush() must clear the configured backing store');
    }
}
