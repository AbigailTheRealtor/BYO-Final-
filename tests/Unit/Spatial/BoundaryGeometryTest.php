<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\BoundaryGeometry;
use Tests\TestCase;

/**
 * Batch 2D Part C3a — canonical GeoJSON validation + Polygon→MultiPolygon normalization (D1/D2/D5).
 * Structural only; no PostGIS. Pure.
 */
class BoundaryGeometryTest extends TestCase
{
    private BoundaryGeometry $g;

    protected function setUp(): void
    {
        parent::setUp();
        $this->g = new BoundaryGeometry();
    }

    private function ring(): array
    {
        return [[-82.0, 27.0], [-82.0, 27.01], [-81.99, 27.01], [-81.99, 27.0], [-82.0, 27.0]];
    }

    /** @test */
    public function a_valid_polygon_is_wrapped_into_a_one_member_multipolygon(): void
    {
        $out = $this->g->normalizeToMultiPolygon(['type' => 'Polygon', 'coordinates' => [$this->ring()]]);

        $this->assertSame('MultiPolygon', $out['type']);
        $this->assertSame([[$this->ring()]], $out['coordinates']);
        $this->assertTrue($this->g->isValidMultiPolygon($out));
    }

    /** @test */
    public function a_valid_multipolygon_passes_through_unchanged(): void
    {
        $mp = ['type' => 'MultiPolygon', 'coordinates' => [[$this->ring()]]];
        $this->assertSame($mp, $this->g->normalizeToMultiPolygon($mp));
    }

    /** @test */
    public function an_unclosed_ring_is_invalid(): void
    {
        $open = [[-82.0, 27.0], [-82.0, 27.01], [-81.99, 27.01], [-81.99, 27.0]]; // first != last
        $this->assertNull($this->g->normalizeToMultiPolygon(['type' => 'Polygon', 'coordinates' => [$open]]));
    }

    /** @test */
    public function a_ring_with_too_few_positions_is_invalid(): void
    {
        $tiny = [[-82.0, 27.0], [-82.0, 27.01], [-82.0, 27.0]]; // only 3 positions
        $this->assertNull($this->g->normalizeToMultiPolygon(['type' => 'Polygon', 'coordinates' => [$tiny]]));
    }

    /** @test */
    public function out_of_range_coordinates_are_invalid(): void
    {
        $bad = [[-999.0, 27.0], [-82.0, 27.01], [-81.99, 27.01], [-81.99, 27.0], [-999.0, 27.0]];
        $this->assertNull($this->g->normalizeToMultiPolygon(['type' => 'Polygon', 'coordinates' => [$bad]]));
    }

    /** @test */
    public function an_empty_polygon_or_ring_is_invalid(): void
    {
        $this->assertNull($this->g->normalizeToMultiPolygon(['type' => 'Polygon', 'coordinates' => []]));
        $this->assertNull($this->g->normalizeToMultiPolygon(['type' => 'MultiPolygon', 'coordinates' => []]));
    }

    /** @test */
    public function a_non_polygon_type_is_rejected_not_wrapped(): void
    {
        $this->assertNull($this->g->normalizeToMultiPolygon(['type' => 'Point', 'coordinates' => [-82.0, 27.0]]));
        $this->assertNull($this->g->normalizeToMultiPolygon(['type' => 'LineString', 'coordinates' => [[-82.0, 27.0], [-81.0, 27.0]]]));
        $this->assertNull($this->g->normalizeToMultiPolygon(null));
        $this->assertNull($this->g->normalizeToMultiPolygon('not geojson'));
    }

    /** @test */
    public function a_non_finite_coordinate_is_invalid(): void
    {
        // Non-numeric position component.
        $bad = [['x', 27.0], [-82.0, 27.01], [-81.99, 27.01], [-81.99, 27.0], ['x', 27.0]];
        $this->assertNull($this->g->normalizeToMultiPolygon(['type' => 'Polygon', 'coordinates' => [$bad]]));
    }
}
