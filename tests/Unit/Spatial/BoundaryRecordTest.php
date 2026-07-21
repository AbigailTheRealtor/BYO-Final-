<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\BoundaryRecord;
use Tests\TestCase;

/**
 * Batch 2D Part C3a — the BoundaryRecord DTO: GeoJSON round-trip, required-key throw, deterministic
 * NDJSON ordering. Pure; no DB.
 */
class BoundaryRecordTest extends TestCase
{
    private function geometry(): array
    {
        return ['type' => 'MultiPolygon', 'coordinates' => [[[[-82.0, 27.0], [-82.0, 27.01], [-81.99, 27.01], [-81.99, 27.0], [-82.0, 27.0]]]]];
    }

    /** @test */
    public function to_array_key_order_is_the_wire_format(): void
    {
        $r = new BoundaryRecord('protected_area', 'PADUS-0001', $this->geometry(), ['acres' => 10.0, 'name' => 'X', 'source' => 'padus']);
        $this->assertSame(['kind', 'external_ref', 'geometry', 'attrs'], array_keys($r->toArray()));
    }

    /** @test */
    public function from_array_round_trips_and_defaults_attrs(): void
    {
        $r = new BoundaryRecord('protected_area', 'PADUS-0001', $this->geometry(), ['acres' => null, 'name' => 'X', 'source' => 'padus']);
        $this->assertEquals($r, BoundaryRecord::fromArray($r->toArray()));
    }

    /** @test */
    public function from_array_requires_kind_and_geometry(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BoundaryRecord::fromArray(['kind' => 'protected_area']); // missing geometry
    }

    /** @test */
    public function to_ndjson_is_deterministically_sorted_by_kind_then_ref(): void
    {
        $a = new BoundaryRecord('protected_area', 'PADUS-0002', $this->geometry(), []);
        $b = new BoundaryRecord('protected_area', 'PADUS-0001', $this->geometry(), []);

        $ndjson = BoundaryRecord::toNdjson([$a, $b]);
        $lines = array_values(array_filter(explode("\n", $ndjson)));
        $this->assertStringContainsString('PADUS-0001', $lines[0]);
        $this->assertStringContainsString('PADUS-0002', $lines[1]);
        $this->assertStringEndsWith("\n", $ndjson);
    }

    /** @test */
    public function to_ndjson_round_trips_through_from_ndjson(): void
    {
        $records = [new BoundaryRecord('protected_area', 'PADUS-0001', $this->geometry(), ['acres' => 340.0, 'name' => 'X', 'source' => 'padus'])];
        $parsed = BoundaryRecord::fromNdjson(BoundaryRecord::toNdjson($records));
        $this->assertEquals($records, $parsed);
    }
}
