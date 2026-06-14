<?php

namespace Tests\Unit\Services\LocationDna;

use App\Services\LocationDna\FemaFloodZoneAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * FemaFloodZoneAdapterTest
 *
 * Verifies FemaFloodZoneAdapter::lookup() using Http::fake() so no real
 * HTTP calls are made.
 *
 * Test coverage:
 *   (a) Successful response — Polygon feature parsed into normalized shape
 *   (b) Successful response — MultiPolygon feature split into multiple entries
 *   (c) Successful response with no features returns []
 *   (d) Non-2xx HTTP response returns [] and does not cache
 *   (e) Transport exception (connection error) returns [] and does not cache
 *   (f) Successful response is cached for 24 h; second call hits cache, not HTTP
 *   (g) Failed responses are NOT cached — next call retries the API
 *   (h) Malformed response body (missing 'features' key) returns []
 *   (i) Features with null/empty FLD_ZONE are filtered out
 *   (j) Invalid bbox (fewer than 4 elements) returns [] immediately
 */
class FemaFloodZoneAdapterTest extends TestCase
{
    private function makeAdapter(): FemaFloodZoneAdapter
    {
        return new FemaFloodZoneAdapter();
    }

    private function validBbox(): array
    {
        return [-82.5, 27.8, -82.3, 28.0];
    }

    /** (a) Successful Polygon response parsed correctly */
    public function test_successful_polygon_response_is_parsed(): void
    {
        Http::fake([
            'hazards.fema.gov/*' => Http::response($this->polygonFeatureCollection(), 200),
        ]);
        Cache::flush();

        $adapter = $this->makeAdapter();
        $results = $adapter->lookup($this->validBbox());

        $this->assertCount(1, $results);
        $this->assertSame('AE', $results[0]['zone_designation']);
        $this->assertIsArray($results[0]['rings']);
        $this->assertNotEmpty($results[0]['rings']);
    }

    /** (b) MultiPolygon feature is split into separate entries */
    public function test_multipolygon_feature_is_split_into_separate_entries(): void
    {
        Http::fake([
            'hazards.fema.gov/*' => Http::response($this->multipolygonFeatureCollection(), 200),
        ]);
        Cache::flush();

        $adapter = $this->makeAdapter();
        $results = $adapter->lookup($this->validBbox());

        // Two polygons from one MultiPolygon feature
        $this->assertCount(2, $results);
        foreach ($results as $r) {
            $this->assertSame('VE', $r['zone_designation']);
            $this->assertIsArray($r['rings']);
        }
    }

    /** (c) 200 response with no features returns empty array */
    public function test_empty_features_returns_empty_array(): void
    {
        Http::fake([
            'hazards.fema.gov/*' => Http::response(['features' => []], 200),
        ]);
        Cache::flush();

        $results = $this->makeAdapter()->lookup($this->validBbox());

        $this->assertSame([], $results);
    }

    /** (d) Non-2xx HTTP response returns [] and the cache key is never set */
    public function test_non_2xx_response_returns_empty_and_is_not_cached(): void
    {
        Cache::flush();
        Http::fake([
            'hazards.fema.gov/*' => Http::response('Server Error', 503),
        ]);

        $adapter = $this->makeAdapter();
        $bbox    = $this->validBbox();
        $results = $adapter->lookup($bbox);

        $this->assertSame([], $results);

        // The cache key must NOT exist after a failure — verify directly on the store
        $cacheKey = 'fema_flood_zones_' . md5(implode(',', [
            round($bbox[0], 4),
            round($bbox[1], 4),
            round($bbox[2], 4),
            round($bbox[3], 4),
        ]));
        $this->assertNull(Cache::get($cacheKey), 'Failed response must not be cached');
    }

    /** (e) Transport exception returns [] gracefully */
    public function test_transport_exception_returns_empty_array(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });
        Cache::flush();

        $results = $this->makeAdapter()->lookup($this->validBbox());

        $this->assertSame([], $results);
    }

    /** (f) Successful response is cached; second call does not fire HTTP */
    public function test_successful_response_is_cached(): void
    {
        Http::fake([
            'hazards.fema.gov/*' => Http::response($this->polygonFeatureCollection(), 200),
        ]);
        Cache::flush();

        $adapter = $this->makeAdapter();
        $bbox    = $this->validBbox();

        $adapter->lookup($bbox); // first call — hits HTTP and fills cache

        // Replace the fake with a 500 so any real HTTP request fails
        Http::fake([
            'hazards.fema.gov/*' => Http::response('Error', 500),
        ]);

        $results = $adapter->lookup($bbox); // second call — should hit cache
        $this->assertCount(1, $results);    // still returns parsed data from cache
    }

    /** (g) Failed responses are not cached — cache key absent after 502 */
    public function test_failed_response_is_not_cached_so_next_call_retries(): void
    {
        Cache::flush();
        Http::fake([
            'hazards.fema.gov/*' => Http::response('Bad Gateway', 502),
        ]);

        $adapter = $this->makeAdapter();
        $bbox    = $this->validBbox();
        $adapter->lookup($bbox); // failure — must not populate cache

        $cacheKey = 'fema_flood_zones_' . md5(implode(',', [
            round($bbox[0], 4),
            round($bbox[1], 4),
            round($bbox[2], 4),
            round($bbox[3], 4),
        ]));
        $this->assertNull(Cache::get($cacheKey), 'Bad-gateway response must not be cached');
    }

    /** (h) Malformed body missing 'features' key returns [] */
    public function test_missing_features_key_returns_empty_array(): void
    {
        Http::fake([
            'hazards.fema.gov/*' => Http::response(['type' => 'FeatureCollection'], 200),
        ]);
        Cache::flush();

        $results = $this->makeAdapter()->lookup($this->validBbox());

        $this->assertSame([], $results);
    }

    /** (i) Features with null or empty FLD_ZONE are filtered out */
    public function test_features_with_null_zone_are_filtered(): void
    {
        $body = [
            'type'     => 'FeatureCollection',
            'features' => [
                $this->makePolygonFeature(null),   // null zone
                $this->makePolygonFeature(''),     // empty zone
                $this->makePolygonFeature('X'),    // valid
            ],
        ];
        Http::fake([
            'hazards.fema.gov/*' => Http::response($body, 200),
        ]);
        Cache::flush();

        $results = $this->makeAdapter()->lookup($this->validBbox());

        $this->assertCount(1, $results);
        $this->assertSame('X', $results[0]['zone_designation']);
    }

    /** (j) Invalid bbox shorter than 4 elements returns [] immediately (no HTTP) */
    public function test_invalid_bbox_returns_empty_without_http_call(): void
    {
        Http::fake(); // will throw if any request is made

        $results = $this->makeAdapter()->lookup([-82.5, 27.8]); // only 2 elements

        $this->assertSame([], $results);
        Http::assertNothingSent();
    }

    // ── Fixture helpers ────────────────────────────────────────────────────────

    private function polygonFeatureCollection(): array
    {
        return [
            'type'     => 'FeatureCollection',
            'features' => [$this->makePolygonFeature('AE')],
        ];
    }

    private function multipolygonFeatureCollection(): array
    {
        return [
            'type'     => 'FeatureCollection',
            'features' => [$this->makeMultipolygonFeature('VE')],
        ];
    }

    private function makePolygonFeature(?string $zone): array
    {
        return [
            'type'       => 'Feature',
            'properties' => ['FLD_ZONE' => $zone],
            'geometry'   => [
                'type'        => 'Polygon',
                'coordinates' => [
                    [[-82.5, 27.8], [-82.4, 27.8], [-82.4, 27.9], [-82.5, 27.9], [-82.5, 27.8]],
                ],
            ],
        ];
    }

    private function makeMultipolygonFeature(string $zone): array
    {
        $ring1 = [[-82.5, 27.8], [-82.4, 27.8], [-82.4, 27.9], [-82.5, 27.9], [-82.5, 27.8]];
        $ring2 = [[-82.3, 27.8], [-82.2, 27.8], [-82.2, 27.9], [-82.3, 27.9], [-82.3, 27.8]];
        return [
            'type'       => 'Feature',
            'properties' => ['FLD_ZONE' => $zone],
            'geometry'   => [
                'type'        => 'MultiPolygon',
                'coordinates' => [[$ring1], [$ring2]],
            ],
        ];
    }
}
