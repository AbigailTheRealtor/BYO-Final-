<?php

namespace Tests\Unit\Services\LocationDna;

use App\Contracts\SchoolDistrictAdapterInterface;
use App\Services\LocationDna\SchoolDistrictLookupService;
use Mockery;
use Tests\TestCase;

/**
 * SchoolDistrictLookupServiceTest
 *
 * Verifies SchoolDistrictLookupService::resolve() using a mocked adapter so that
 * no real HTTP calls are made.
 *
 * Test coverage:
 *   (a) Skips adapter when boundary fallback=true and no drawn polygons/radii
 *   (b) Calls adapter when boundary has geojson_polygons (Tiers 3-5)
 *   (c) Returns available=false when adapter returns empty array
 *   (d) Returns available=true with district data when adapter returns data
 *   (e) Skips adapter when bounding area exceeds threshold
 *   (f) Calls adapter when drawn polygons exist, even if boundary fallback=true
 *   (g) Calls adapter when radius circles exist, even if boundary fallback=true
 *   (h) Derives correct bbox from geojson_polygons
 *   (i) Derives correct bbox from drawn polygon path points
 *   (j) Derives correct bbox from radius circles
 */
class SchoolDistrictLookupServiceTest extends TestCase
{
    private function makeService(SchoolDistrictAdapterInterface $adapter): SchoolDistrictLookupService
    {
        return new SchoolDistrictLookupService($adapter);
    }

    private function makeMockAdapter(array $returnValue = []): SchoolDistrictAdapterInterface
    {
        $mock = Mockery::mock(SchoolDistrictAdapterInterface::class);
        $mock->shouldReceive('lookup')->andReturn($returnValue);
        return $mock;
    }

    private function neverCalledAdapter(): SchoolDistrictAdapterInterface
    {
        $mock = Mockery::mock(SchoolDistrictAdapterInterface::class);
        $mock->shouldNotReceive('lookup');
        return $mock;
    }

    /** (a) Boundary fallback=true with no drawn geometries → adapter never called */
    public function test_skips_adapter_when_fallback_true_and_no_drawn_shapes(): void
    {
        $adapter = $this->neverCalledAdapter();
        $service = $this->makeService($adapter);
        $result  = $service->resolve(
            ['geojson_polygons' => [], 'fallback' => true],
            []
        );

        $this->assertFalse($result['available']);
        $this->assertSame([], $result['school_districts']);
    }

    /** (b) Boundary has geojson_polygons → adapter IS called */
    public function test_calls_adapter_when_geojson_polygons_present(): void
    {
        $districtData = [
            ['district_name' => 'Hillsborough County Schools', 'rings' => [[[-82.5, 27.8], [-82.4, 27.8]]]],
        ];
        $adapter = Mockery::mock(SchoolDistrictAdapterInterface::class);
        $adapter->shouldReceive('lookup')->once()->andReturn($districtData);

        $boundaryData = [
            'geojson_polygons' => [
                [[
                    [[-82.5, 27.8], [-82.4, 27.8], [-82.4, 27.9], [-82.5, 27.9], [-82.5, 27.8]],
                ]],
            ],
            'fallback' => false,
        ];

        $result = $this->makeService($adapter)->resolve($boundaryData, []);

        $this->assertTrue($result['available']);
        $this->assertCount(1, $result['school_districts']);
    }

    /** (c) Adapter returns empty → available=false */
    public function test_returns_unavailable_when_adapter_returns_empty(): void
    {
        $adapter = Mockery::mock(SchoolDistrictAdapterInterface::class);
        $adapter->shouldReceive('lookup')->once()->andReturn([]);

        $boundaryData = [
            'geojson_polygons' => [
                [[
                    [[-82.5, 27.8], [-82.4, 27.8], [-82.4, 27.9], [-82.5, 27.9], [-82.5, 27.8]],
                ]],
            ],
            'fallback' => false,
        ];

        $result = $this->makeService($adapter)->resolve($boundaryData, []);

        $this->assertFalse($result['available']);
        $this->assertSame([], $result['school_districts']);
    }

    /** (d) Adapter returns data → available=true with school_districts populated */
    public function test_returns_available_true_when_adapter_returns_data(): void
    {
        $districts = [
            ['district_name' => 'Pinellas County Schools', 'rings' => [[[-82.5, 27.8], [-82.4, 27.8]]]],
            ['district_name' => 'Hillsborough County Schools', 'rings' => [[[-82.3, 27.8], [-82.2, 27.8]]]],
        ];
        $adapter = Mockery::mock(SchoolDistrictAdapterInterface::class);
        $adapter->shouldReceive('lookup')->once()->andReturn($districts);

        $boundaryData = [
            'geojson_polygons' => [
                [[
                    [[-82.5, 27.8], [-82.4, 27.8], [-82.4, 27.9], [-82.5, 27.9], [-82.5, 27.8]],
                ]],
            ],
            'fallback' => false,
        ];

        $result = $this->makeService($adapter)->resolve($boundaryData, []);

        $this->assertTrue($result['available']);
        $this->assertSame($districts, $result['school_districts']);
    }

    /** (e) Bounding area exceeds threshold → adapter skipped, warning logged */
    public function test_skips_adapter_when_area_exceeds_threshold(): void
    {
        // A 3° × 3° bounding area (= 9 sq degrees) exceeds the default 2.0 threshold
        $hugeBoundary = [
            'geojson_polygons' => [
                [[
                    [[-90.0, 25.0], [-87.0, 25.0], [-87.0, 28.0], [-90.0, 28.0], [-90.0, 25.0]],
                ]],
            ],
            'fallback' => false,
        ];

        $adapter = $this->neverCalledAdapter();
        $result  = $this->makeService($adapter)->resolve($hugeBoundary, []);

        $this->assertFalse($result['available']);
        $this->assertSame([], $result['school_districts']);
    }

    /** (f) Drawn polygon in preferences → adapter IS called despite fallback=true */
    public function test_calls_adapter_when_drawn_polygon_exists(): void
    {
        $adapter = Mockery::mock(SchoolDistrictAdapterInterface::class);
        $adapter->shouldReceive('lookup')->once()->andReturn([
            ['district_name' => 'Pasco County Schools', 'rings' => [[[-82.5, 27.8], [-82.4, 27.8]]]],
        ]);

        $preferences = [
            'polygons' => [
                [
                    'path' => [
                        ['lat' => 27.8, 'lng' => -82.5],
                        ['lat' => 27.8, 'lng' => -82.4],
                        ['lat' => 27.9, 'lng' => -82.4],
                        ['lat' => 27.9, 'lng' => -82.5],
                    ],
                    'label' => 'Test Area',
                ],
            ],
        ];

        $result = $this->makeService($adapter)->resolve(
            ['geojson_polygons' => [], 'fallback' => true],
            $preferences
        );

        $this->assertTrue($result['available']);
    }

    /** (g) Radius circle in preferences → adapter IS called despite fallback=true */
    public function test_calls_adapter_when_radius_circle_exists(): void
    {
        $adapter = Mockery::mock(SchoolDistrictAdapterInterface::class);
        $adapter->shouldReceive('lookup')->once()->andReturn([
            ['district_name' => 'Manatee County Schools', 'rings' => [[[-82.5, 27.8], [-82.4, 27.8]]]],
        ]);

        $preferences = [
            'radius_searches' => [
                ['center' => ['lat' => 27.9, 'lng' => -82.45], 'radius_miles' => 5, 'label' => 'Tampa'],
            ],
        ];

        $result = $this->makeService($adapter)->resolve(
            ['geojson_polygons' => [], 'fallback' => true],
            $preferences
        );

        $this->assertTrue($result['available']);
    }

    /** (h) Bbox derived from geojson_polygons passes correct bounds to adapter */
    public function test_bbox_derived_from_geojson_polygons_is_correct(): void
    {
        $capturedBbox = null;
        $adapter = Mockery::mock(SchoolDistrictAdapterInterface::class);
        $adapter->shouldReceive('lookup')
            ->once()
            ->with(Mockery::on(function (array $bbox) use (&$capturedBbox) {
                $capturedBbox = $bbox;
                return true;
            }))
            ->andReturn([]);

        $ring = [
            [-82.5, 27.8], [-82.3, 27.8], [-82.3, 28.0], [-82.5, 28.0], [-82.5, 27.8],
        ];
        $boundaryData = [
            'geojson_polygons' => [[[ $ring ]]],
            'fallback' => false,
        ];

        $this->makeService($adapter)->resolve($boundaryData, []);

        $this->assertIsArray($capturedBbox);
        $this->assertCount(4, $capturedBbox);
        // minLng should be ≤ -82.5, maxLng should be ≥ -82.3
        $this->assertLessThanOrEqual(-82.5, $capturedBbox[0]);
        $this->assertGreaterThanOrEqual(-82.3, $capturedBbox[2]);
        // minLat should be ≤ 27.8, maxLat should be ≥ 28.0
        $this->assertLessThanOrEqual(27.8, $capturedBbox[1]);
        $this->assertGreaterThanOrEqual(28.0, $capturedBbox[3]);
    }

    /** (i) Bbox derived from drawn polygon path is correct */
    public function test_bbox_derived_from_drawn_polygon_path_is_correct(): void
    {
        $capturedBbox = null;
        $adapter = Mockery::mock(SchoolDistrictAdapterInterface::class);
        $adapter->shouldReceive('lookup')
            ->once()
            ->with(Mockery::on(function (array $bbox) use (&$capturedBbox) {
                $capturedBbox = $bbox;
                return true;
            }))
            ->andReturn([]);

        $preferences = [
            'polygons' => [
                [
                    'path' => [
                        ['lat' => 27.8, 'lng' => -82.5],
                        ['lat' => 27.8, 'lng' => -82.3],
                        ['lat' => 28.0, 'lng' => -82.3],
                        ['lat' => 28.0, 'lng' => -82.5],
                    ],
                ],
            ],
        ];

        $this->makeService($adapter)->resolve(
            ['geojson_polygons' => [], 'fallback' => true],
            $preferences
        );

        $this->assertIsArray($capturedBbox);
        $this->assertLessThanOrEqual(-82.5, $capturedBbox[0]);
        $this->assertGreaterThanOrEqual(-82.3, $capturedBbox[2]);
        $this->assertLessThanOrEqual(27.8, $capturedBbox[1]);
        $this->assertGreaterThanOrEqual(28.0, $capturedBbox[3]);
    }

    /** (j) Bbox derived from radius circles is correct */
    public function test_bbox_derived_from_radius_circle_is_correct(): void
    {
        $capturedBbox = null;
        $adapter = Mockery::mock(SchoolDistrictAdapterInterface::class);
        $adapter->shouldReceive('lookup')
            ->once()
            ->with(Mockery::on(function (array $bbox) use (&$capturedBbox) {
                $capturedBbox = $bbox;
                return true;
            }))
            ->andReturn([]);

        $preferences = [
            'radius_searches' => [
                ['center' => ['lat' => 28.0, 'lng' => -82.4], 'radius_miles' => 10],
            ],
        ];

        $this->makeService($adapter)->resolve(
            ['geojson_polygons' => [], 'fallback' => true],
            $preferences
        );

        $this->assertIsArray($capturedBbox);
        // 10 miles ≈ 0.145° latitude — bbox should extend beyond center ± 0.14°
        $this->assertLessThan(28.0, $capturedBbox[1]);    // minLat < center lat
        $this->assertGreaterThan(28.0, $capturedBbox[3]); // maxLat > center lat
        $this->assertLessThan(-82.4, $capturedBbox[0]);   // minLng < center lng
        $this->assertGreaterThan(-82.4, $capturedBbox[2]); // maxLng > center lng
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
