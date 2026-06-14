<?php

namespace Tests\Unit\Services\LocationDna;

use App\Contracts\PoiLookupAdapterInterface;
use App\Services\LocationDna\PoiDistanceLookupService;
use App\Services\LocationDna\StubPoiLookupAdapter;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * PoiDistanceLookupServiceTest
 *
 * Verifies PoiDistanceLookupService::lookup() using mocked adapters.
 * No real Google Places API calls are made in this file.
 *
 * Output contract (4-key shape):
 *   results (array), error (string|null), source_lat (float|null), source_lng (float|null)
 *
 * Test coverage:
 *   (a) Category normalisation — all 7 slugs appear in output items with all 7 required keys
 *   (b) Distance sorting      — adapter returns items out of order, service sorts ascending
 *   (c) Empty response        — adapter returns [], service returns results:[] error:null
 *   (d) API failure           — adapter search() throws, service returns empty + non-null error
 *   (e) Invalid geometry      — null/missing type key returns empty results with descriptive error
 *   (f) Max radius enforcement— radius exceeding max_radius_miles, adapter called with capped value
 *   (g) Cache behaviour       — same geometry called twice, adapter search() invoked only once
 *   (h) No-key stub path      — places_key null, IoC resolves StubPoiLookupAdapter, no exception
 */
class PoiDistanceLookupServiceTest extends TestCase
{
    private const SUPPORTED_CATEGORIES = [
        'schools', 'parks', 'shopping', 'hospitals', 'gyms', 'airports', 'downtown',
    ];

    private const ITEM_KEYS = [
        'category', 'name', 'address', 'latitude', 'longitude', 'distance_miles', 'source',
    ];

    private const CONTRACT_KEYS = ['results', 'error', 'source_lat', 'source_lng'];

    private const POINT_GEOMETRY = ['type' => 'point', 'lat' => 27.9506, 'lng' => -82.4572];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'location_dna.poi.max_radius_miles'      => 25,
            'location_dna.poi.category_result_limit' => 5,
            'location_dna.poi.cache_ttl'             => 86400,
            'location_dna.poi.timeout'               => 5,
            'services.google.places_key'             => 'test-places-key',
            'cache.default'                          => 'array',
        ]);

        Cache::flush();
    }

    private function makeService(PoiLookupAdapterInterface $adapter): PoiDistanceLookupService
    {
        return new PoiDistanceLookupService($adapter);
    }

    private function makeAdapter(): \PHPUnit\Framework\MockObject\MockObject
    {
        return $this->createMock(PoiLookupAdapterInterface::class);
    }

    private function makeItem(string $category, float $distanceMiles): array
    {
        return [
            'category'       => $category,
            'name'           => 'Test ' . $category,
            'address'        => '123 Main St',
            'latitude'       => 27.96,
            'longitude'      => -82.46,
            'distance_miles' => $distanceMiles,
            'source'         => 'google_places',
        ];
    }

    private function assertContractShape(array $result): void
    {
        foreach (self::CONTRACT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Output contract key '{$key}' is missing");
        }
        $this->assertCount(count(self::CONTRACT_KEYS), $result, 'Output must have exactly the 4 contract keys');
    }

    private function assertItemShape(array $item): void
    {
        foreach (self::ITEM_KEYS as $key) {
            $this->assertArrayHasKey($key, $item, "Result item missing required key '{$key}'");
        }
        $this->assertCount(count(self::ITEM_KEYS), $item, 'Result item must have exactly 7 keys');
    }

    // =========================================================================
    // (a) Category normalisation — all 7 slugs appear in output with 7 keys
    // =========================================================================

    /** @test */
    public function it_returns_items_with_all_seven_required_keys_for_every_category(): void
    {
        $adapter = $this->makeAdapter();

        $adapter->expects($this->exactly(count(self::SUPPORTED_CATEGORIES)))
            ->method('search')
            ->willReturnCallback(function (float $lat, float $lng, string $category) {
                return [$this->makeItem($category, 1.5)];
            });

        $result = $this->makeService($adapter)->lookup(self::POINT_GEOMETRY);

        $this->assertContractShape($result);
        $this->assertNull($result['error']);
        $this->assertNotEmpty($result['results']);

        $returnedCategories = array_column($result['results'], 'category');
        foreach (self::SUPPORTED_CATEGORIES as $category) {
            $this->assertContains($category, $returnedCategories, "Category '{$category}' missing from results");
        }

        foreach ($result['results'] as $item) {
            $this->assertItemShape($item);
        }
    }

    // =========================================================================
    // (b) Distance sorting — adapter returns out of order, service sorts ascending
    // =========================================================================

    /** @test */
    public function it_sorts_results_ascending_by_distance_miles(): void
    {
        $adapter = $this->makeAdapter();

        $adapter->method('search')
            ->willReturnOnConsecutiveCalls(
                [$this->makeItem('hospitals', 5.0)],
                [$this->makeItem('parks', 1.2)],
                [$this->makeItem('schools', 3.8)],
                [],
                [],
                [],
                [],
            );

        $result = $this->makeService($adapter)->lookup(
            self::POINT_GEOMETRY,
            ['hospitals', 'parks', 'schools', 'shopping', 'gyms', 'airports', 'downtown']
        );

        $this->assertContractShape($result);
        $this->assertNull($result['error']);

        $distances = array_column($result['results'], 'distance_miles');
        $sorted = $distances;
        sort($sorted);
        $this->assertSame($sorted, $distances, 'Results must be sorted ascending by distance_miles');
    }

    // =========================================================================
    // (c) Empty response — adapter returns [], service returns results:[] error:null
    // =========================================================================

    /** @test */
    public function it_returns_empty_results_with_null_error_when_adapter_returns_nothing(): void
    {
        $adapter = $this->makeAdapter();
        $adapter->method('search')->willReturn([]);

        $result = $this->makeService($adapter)->lookup(self::POINT_GEOMETRY);

        $this->assertContractShape($result);
        $this->assertSame([], $result['results']);
        $this->assertNull($result['error']);
        $this->assertEqualsWithDelta(27.9506, $result['source_lat'], 0.0001);
        $this->assertEqualsWithDelta(-82.4572, $result['source_lng'], 0.0001);
    }

    // =========================================================================
    // (d) API failure — adapter throws, service returns empty + non-null error
    // =========================================================================

    /** @test */
    public function it_returns_empty_results_with_error_string_when_adapter_throws(): void
    {
        $adapter = $this->makeAdapter();
        $adapter->method('search')->willThrowException(new \Exception('Connection timeout'));

        $result = $this->makeService($adapter)->lookup(self::POINT_GEOMETRY, ['schools']);

        $this->assertContractShape($result);
        $this->assertSame([], $result['results']);
        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('Connection timeout', $result['error']);
    }

    // =========================================================================
    // (e) Invalid geometry — null or missing type returns empty + descriptive error
    // =========================================================================

    /** @test */
    public function it_returns_empty_results_with_error_when_geometry_has_no_type(): void
    {
        $adapter = $this->makeAdapter();
        $adapter->expects($this->never())->method('search');

        $result = $this->makeService($adapter)->lookup([]);

        $this->assertContractShape($result);
        $this->assertSame([], $result['results']);
        $this->assertNotNull($result['error']);
        $this->assertNull($result['source_lat']);
        $this->assertNull($result['source_lng']);
    }

    /** @test */
    public function it_returns_empty_results_with_error_when_geometry_type_key_is_missing(): void
    {
        $adapter = $this->makeAdapter();
        $adapter->expects($this->never())->method('search');

        $result = $this->makeService($adapter)->lookup(['lat' => 27.9, 'lng' => -82.4]);

        $this->assertContractShape($result);
        $this->assertSame([], $result['results']);
        $this->assertNotNull($result['error']);
    }

    /** @test */
    public function it_returns_error_for_unknown_geometry_type(): void
    {
        $adapter = $this->makeAdapter();
        $adapter->expects($this->never())->method('search');

        $result = $this->makeService($adapter)->lookup(['type' => 'bbox']);

        $this->assertContractShape($result);
        $this->assertNotNull($result['error']);
    }

    // =========================================================================
    // (f) Max radius enforcement — capped value passed to adapter
    // =========================================================================

    /** @test */
    public function it_caps_radius_at_max_radius_miles_before_calling_adapter(): void
    {
        config(['location_dna.poi.max_radius_miles' => 10]);

        $capturedRadius = null;
        $adapter = $this->makeAdapter();
        $adapter->method('search')
            ->willReturnCallback(function (float $lat, float $lng, string $category, int $radiusMiles) use (&$capturedRadius) {
                $capturedRadius = $radiusMiles;
                return [];
            });

        $geometry = ['type' => 'radius', 'lat' => 27.9506, 'lng' => -82.4572, 'radius_miles' => 50];
        $this->makeService($adapter)->lookup($geometry, ['schools']);

        $this->assertSame(10, $capturedRadius, 'Adapter must receive the capped radius, not the original 50');
    }

    /** @test */
    public function it_passes_uncapped_radius_when_within_max(): void
    {
        config(['location_dna.poi.max_radius_miles' => 25]);

        $capturedRadius = null;
        $adapter = $this->makeAdapter();
        $adapter->method('search')
            ->willReturnCallback(function (float $lat, float $lng, string $category, int $radiusMiles) use (&$capturedRadius) {
                $capturedRadius = $radiusMiles;
                return [];
            });

        $geometry = ['type' => 'radius', 'lat' => 27.9506, 'lng' => -82.4572, 'radius_miles' => 15];
        $this->makeService($adapter)->lookup($geometry, ['schools']);

        $this->assertSame(15, $capturedRadius);
    }

    // =========================================================================
    // (g) Cache behaviour — adapter search() invoked only once per category
    // =========================================================================

    /** @test */
    public function it_calls_adapter_only_once_per_category_when_same_geometry_is_looked_up_twice(): void
    {
        $adapter = $this->makeAdapter();

        $adapter->expects($this->exactly(2))
            ->method('search')
            ->willReturn([$this->makeItem('schools', 2.0), $this->makeItem('parks', 3.0)]);

        $service = $this->makeService($adapter);

        $first  = $service->lookup(self::POINT_GEOMETRY, ['schools', 'parks']);
        $second = $service->lookup(self::POINT_GEOMETRY, ['schools', 'parks']);

        $this->assertSame($first['results'], $second['results']);
    }

    // =========================================================================
    // (h) No-key stub path — StubPoiLookupAdapter resolved from IoC when key absent
    // =========================================================================

    /** @test */
    public function it_returns_empty_results_without_exception_when_google_key_is_absent(): void
    {
        config(['services.google.places_key' => null]);

        $this->app->bind(PoiLookupAdapterInterface::class, StubPoiLookupAdapter::class);
        $this->app->forgetInstance(PoiDistanceLookupService::class);

        $service = $this->app->make(PoiDistanceLookupService::class);

        $result = $service->lookup(self::POINT_GEOMETRY);

        $this->assertContractShape($result);
        $this->assertSame([], $result['results']);
        $this->assertNull($result['error']);
    }

    // =========================================================================
    // Polygon centroid derivation
    // =========================================================================

    /** @test */
    public function it_computes_centroid_for_polygon_geometry(): void
    {
        $capturedLat = null;
        $capturedLng = null;

        $adapter = $this->makeAdapter();
        $adapter->method('search')
            ->willReturnCallback(function (float $lat, float $lng) use (&$capturedLat, &$capturedLng) {
                $capturedLat = $lat;
                $capturedLng = $lng;
                return [];
            });

        $geometry = [
            'type'        => 'polygon',
            'coordinates' => [
                [-82.5, 27.5],
                [-81.5, 27.5],
                [-81.5, 28.5],
                [-82.5, 28.5],
            ],
        ];

        $this->makeService($adapter)->lookup($geometry, ['schools']);

        $this->assertEqualsWithDelta(28.0, $capturedLat, 0.01);
        $this->assertEqualsWithDelta(-82.0, $capturedLng, 0.01);
    }

    /** @test */
    public function it_returns_error_for_polygon_geometry_with_no_coordinates(): void
    {
        $adapter = $this->makeAdapter();
        $adapter->expects($this->never())->method('search');

        $result = $this->makeService($adapter)->lookup(['type' => 'polygon', 'coordinates' => []]);

        $this->assertContractShape($result);
        $this->assertNotNull($result['error']);
    }
}
