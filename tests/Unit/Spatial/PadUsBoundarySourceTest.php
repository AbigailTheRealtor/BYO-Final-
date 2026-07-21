<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\Boundary\PadUsBoundarySource;
use Tests\TestCase;

/**
 * Batch 2D Part C3a — PAD-US protected-area source adapter. Pure; no DB, no network, no download.
 */
class PadUsBoundarySourceTest extends TestCase
{
    private PadUsBoundarySource $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->source = new PadUsBoundarySource();
    }

    private function polygon(): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[[-82.0, 27.0], [-82.0, 27.01], [-81.99, 27.01], [-81.99, 27.0], [-82.0, 27.0]]]];
    }

    /** @test */
    public function it_declares_protected_area_kind(): void
    {
        $this->assertSame('padus', $this->source->sourceKey());
        $this->assertSame('protected_area', $this->source->kind());
    }

    /** @test */
    public function a_valid_row_becomes_a_boundary_record_with_wrapped_multipolygon_and_acres_in_attrs(): void
    {
        $result = $this->source->normalize([
            ['unit_id' => 'PADUS-0001', 'unit_nm' => 'Synthetic State Park', 'gis_acres' => 1200.5, 'geometry' => $this->polygon()],
        ]);

        $this->assertCount(1, $result->records);
        $r = $result->records[0];
        $this->assertSame('protected_area', $r->kind);
        $this->assertSame('PADUS-0001', $r->external_ref);
        $this->assertSame('MultiPolygon', $r->geometry['type']);
        $this->assertSame(1200.5, $r->attrs['acres']);
        $this->assertSame('Synthetic State Park', $r->attrs['name']);
        $this->assertSame('padus', $r->attrs['source']);
        $this->assertTrue($result->isFullyAccounted());
    }

    /** @test */
    public function absent_or_non_numeric_acreage_is_kept_with_null_acres_D4(): void
    {
        $result = $this->source->normalize([
            ['unit_id' => 'PADUS-0004', 'unit_nm' => 'Synthetic Preserve', 'gis_acres' => 'N/A', 'geometry' => $this->polygon()],
        ]);

        $this->assertCount(1, $result->records);
        $this->assertNull($result->records[0]->attrs['acres']);
        $this->assertSame(0, $result->rejectedInvalidGeometry);
        $this->assertSame(0, $result->rejectedInvalidField);
    }

    /** @test */
    public function a_missing_unit_id_is_rejected_invalid_field(): void
    {
        $result = $this->source->normalize([
            ['unit_nm' => 'No-ID Tract', 'gis_acres' => 50.0, 'geometry' => $this->polygon()],
        ]);

        $this->assertCount(0, $result->records);
        $this->assertSame(1, $result->rejectedInvalidField);
        $this->assertTrue($result->isFullyAccounted());
    }

    /** @test */
    public function an_unclosed_ring_is_rejected_invalid_geometry(): void
    {
        $open = ['type' => 'Polygon', 'coordinates' => [[[-85.0, 30.0], [-85.0, 30.01], [-84.99, 30.01], [-84.99, 30.0]]]];
        $result = $this->source->normalize([
            ['unit_id' => 'PADUS-0003', 'geometry' => $open],
        ]);

        $this->assertCount(0, $result->records);
        $this->assertSame(1, $result->rejectedInvalidGeometry);
    }

    /** @test */
    public function normalization_preserves_input_order_and_is_deterministic(): void
    {
        $rows = [
            ['unit_id' => 'PADUS-0002', 'gis_acres' => 1.0, 'geometry' => $this->polygon()],
            ['unit_id' => 'PADUS-0001', 'gis_acres' => 2.0, 'geometry' => $this->polygon()],
        ];
        $result = $this->source->normalize($rows);
        $this->assertSame(['PADUS-0002', 'PADUS-0001'], array_map(fn ($r) => $r->external_ref, $result->records));
    }
}
