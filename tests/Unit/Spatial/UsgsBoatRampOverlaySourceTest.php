<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\Overlay\UsgsBoatRampOverlaySource;
use Tests\TestCase;

/**
 * Batch 2D Part C2 — USGS Boat Ramps base-source importer (membership; target=place).
 * Pure; no DB, no network.
 */
class UsgsBoatRampOverlaySourceTest extends TestCase
{
    private UsgsBoatRampOverlaySource $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->source = new UsgsBoatRampOverlaySource();
    }

    /** @test */
    public function it_declares_a_base_place_target_with_no_metric(): void
    {
        $this->assertSame('usgs', $this->source->sourceKey());
        $this->assertSame('place', $this->source->target());
        $this->assertNull($this->source->metricLabel());
        $this->assertNull($this->source->metricDomain());
    }

    /** @test */
    public function a_ramp_becomes_an_authority_record_with_a_null_metric(): void
    {
        $result = $this->source->normalize([
            ['id' => 'BR-0001', 'name' => 'Synthetic Boat Ramp North', 'lon' => -80.19, 'lat' => 25.77],
        ]);

        $this->assertCount(1, $result->records);
        $r = $result->records[0];
        $this->assertSame('usgs', $r->authority_source);
        $this->assertSame('BR-0001', $r->authority_ref);
        $this->assertNull($r->authority_metric);
        $this->assertTrue($result->isFullyAccounted());
    }

    /** @test */
    public function a_row_missing_coordinates_is_rejected_invalid(): void
    {
        $result = $this->source->normalize([
            ['id' => 'BR-0003', 'name' => 'No Coords Ramp'],
        ]);

        $this->assertCount(0, $result->records);
        $this->assertSame(1, $result->rejectedInvalid);
        $this->assertSame(0, $result->rejectedOutOfDomain);
        $this->assertTrue($result->isFullyAccounted());
    }

    /** @test */
    public function a_row_missing_an_id_is_rejected_invalid(): void
    {
        $result = $this->source->normalize([
            ['name' => 'Nameless-id Ramp', 'lon' => -80.18, 'lat' => 25.76],
        ]);

        $this->assertCount(0, $result->records);
        $this->assertSame(1, $result->rejectedInvalid);
    }
}
