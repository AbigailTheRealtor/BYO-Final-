<?php

namespace Tests\Unit\Services\LocationDna;

use App\Services\LocationDna\CommuteTimeLookupService;
use App\Services\LocationDna\FloodZoneLookupService;
use App\Services\LocationDna\LocationDnaEnrichmentRunner;
use App\Services\LocationDna\PoiDistanceLookupService;
use App\Services\LocationDna\SchoolDistrictLookupService;
use Mockery;
use Tests\TestCase;

/**
 * POI geometry selection must honour the canonical precedence:
 *
 *     Polygon > Radius > resolved boundary geometry
 *
 * The runner previously returned on the first valid `radius_searches` entry
 * before it ever looked at `polygons`. A user who drew a precise search area
 * AND kept a radius therefore had POI distances measured from the radius, and
 * the drawn polygon — the more specific, deliberately hand-drawn shape — was
 * discarded without a trace.
 *
 * Geometry is observed where it is actually consumed: the argument handed to
 * PoiDistanceLookupService::lookup(). That exercises the real private
 * derivation through the real public run(), rather than asserting on source
 * text or reaching in with reflection.
 *
 * No network, no database: all four collaborators are mocks.
 */
class LocationDnaPoiGeometryPrecedenceTest extends TestCase
{
    private const RADIUS = [
        ['center' => ['lat' => 27.7676, 'lng' => -82.6403], 'radius_miles' => 5],
    ];

    private const POLYGON = [
        ['path' => [
            ['lat' => 27.70, 'lng' => -82.70],
            ['lat' => 27.80, 'lng' => -82.70],
            ['lat' => 27.80, 'lng' => -82.60],
        ]],
    ];

    /** A boundary payload as BoundaryLookupService would return it. */
    private function boundaryData(): array
    {
        return ['geojson_polygons' => [[[[[-82.7, 27.7], [-82.6, 27.7], [-82.6, 27.8]]]]], 'fallback' => false];
    }

    private function emptyBoundaryData(): array
    {
        return ['geojson_polygons' => [], 'fallback' => true];
    }

    /**
     * Run the runner and return the geometry that reached the POI service,
     * or null when the runner derived none.
     */
    private function geometryHandedToPoi(array $boundaryData, array $preferences): ?array
    {
        $captured = null;

        $poi = Mockery::mock(PoiDistanceLookupService::class);
        $poi->shouldReceive('lookup')
            ->andReturnUsing(function (array $geometry) use (&$captured) {
                $captured = $geometry;

                return ['results' => [], 'error' => null, 'source_lat' => null, 'source_lng' => null];
            });

        $flood = Mockery::mock(FloodZoneLookupService::class);
        $flood->shouldReceive('lookup')->andReturn(['zones' => [], 'error' => null]);

        $school = Mockery::mock(SchoolDistrictLookupService::class);
        $school->shouldReceive('lookup')->andReturn(['districts' => [], 'error' => null]);

        $commute = Mockery::mock(CommuteTimeLookupService::class);
        $commute->shouldReceive('lookup')->andReturn(['results' => [], 'error' => null]);

        (new LocationDnaEnrichmentRunner($flood, $school, $poi, $commute))
            ->run($boundaryData, $preferences);

        return $captured;
    }

    // ── the regression ──────────────────────────────────────────────────────

    /** Case 1: polygon and radius both usable → polygon wins. */
    public function test_polygon_outranks_radius(): void
    {
        $geometry = $this->geometryHandedToPoi($this->emptyBoundaryData(), [
            'radius_searches' => self::RADIUS,
            'polygons'        => self::POLYGON,
        ]);

        $this->assertNotNull($geometry, 'A usable geometry must be derived');
        $this->assertSame(
            'polygon',
            $geometry['type'],
            'A drawn polygon must outrank a radius search'
        );
    }

    /** Polygon wins even when the boundary tier also resolved. */
    public function test_polygon_outranks_radius_and_resolved_boundaries(): void
    {
        $geometry = $this->geometryHandedToPoi($this->boundaryData(), [
            'radius_searches' => self::RADIUS,
            'polygons'        => self::POLYGON,
        ]);

        $this->assertSame('polygon', $geometry['type']);
        $this->assertSame(
            [[-82.70, 27.70], [-82.70, 27.80], [-82.60, 27.80]],
            $geometry['coordinates'],
            'The winning polygon must be the drawn one, not a boundary ring'
        );
    }

    // ── the rest of the ladder ──────────────────────────────────────────────

    /** Case 9: radius wins when no polygon is present, over resolved boundaries. */
    public function test_radius_outranks_resolved_boundary_geometry(): void
    {
        $geometry = $this->geometryHandedToPoi($this->boundaryData(), [
            'radius_searches' => self::RADIUS,
        ]);

        $this->assertSame('radius', $geometry['type'], 'Radius must outrank named-boundary geometry');
        $this->assertSame(5, $geometry['radius_miles']);
    }

    public function test_boundary_geometry_is_used_when_neither_polygon_nor_radius_exists(): void
    {
        $geometry = $this->geometryHandedToPoi($this->boundaryData(), []);

        $this->assertSame('polygon', $geometry['type'], 'The resolved boundary ring is the last resort');
    }

    public function test_no_geometry_at_all_derives_nothing(): void
    {
        $this->assertNull($this->geometryHandedToPoi($this->emptyBoundaryData(), []));
    }

    // ── presence must not consume precedence ────────────────────────────────

    /**
     * An unusable polygon (fewer than three points) must not block a usable
     * radius. Presence alone does not win — usability does.
     */
    public function test_an_unusable_polygon_falls_through_to_a_usable_radius(): void
    {
        $geometry = $this->geometryHandedToPoi($this->emptyBoundaryData(), [
            'polygons'        => [['path' => [['lat' => 27.7, 'lng' => -82.7]]]], // only 1 point
            'radius_searches' => self::RADIUS,
        ]);

        $this->assertNotNull($geometry, 'An unusable polygon must not suppress a usable radius');
        $this->assertSame('radius', $geometry['type']);
    }

    /** An unusable radius must not block a usable polygon either. */
    public function test_an_unusable_radius_does_not_prevent_polygon_selection(): void
    {
        $geometry = $this->geometryHandedToPoi($this->emptyBoundaryData(), [
            'radius_searches' => [['center' => ['lat' => 27.7, 'lng' => -82.7], 'radius_miles' => 0]],
            'polygons'        => self::POLYGON,
        ]);

        $this->assertSame('polygon', $geometry['type']);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
