<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\NormalizedPlaceRecord;
use PHPUnit\Framework\TestCase;

/**
 * Batch 2A (B2) — the normalized DTO round-trips exactly through its NDJSON
 * array shape, and enforces required keys.
 */
class NormalizedPlaceRecordTest extends TestCase
{
    private function sample(): NormalizedPlaceRecord
    {
        return new NormalizedPlaceRecord(
            source: 'overture',
            source_ref: 'gers-pinellas-0001',
            gers_id: 'gers-pinellas-0001',
            category_key: 'grocery_store',
            name: 'Sunshine Market',
            brand: 'Publix',
            confidence: 0.98,
            source_count: 2,
            lon: -82.6403,
            lat: 27.7701,
            geometry_type: 'Point',
        );
    }

    /** @test */
    public function it_round_trips_through_to_array_and_from_array(): void
    {
        $record = $this->sample();
        $rebuilt = NormalizedPlaceRecord::fromArray($record->toArray());

        $this->assertEquals($record, $rebuilt);
        $this->assertSame($record->toArray(), $rebuilt->toArray());
    }

    /** @test */
    public function to_array_key_order_is_the_stable_wire_format(): void
    {
        $this->assertSame(
            ['source', 'source_ref', 'gers_id', 'category_key', 'name', 'brand',
                'confidence', 'source_count', 'lon', 'lat', 'geometry_type'],
            array_keys($this->sample()->toArray())
        );
    }

    /** @test */
    public function null_optional_fields_survive_the_round_trip(): void
    {
        $record = new NormalizedPlaceRecord(
            source: 'overture',
            source_ref: 'gers-x',
            gers_id: null,
            category_key: 'restaurant',
            name: null,
            brand: null,
            confidence: null,
            source_count: 1,
            lon: -82.6,
            lat: 27.7,
        );

        $rebuilt = NormalizedPlaceRecord::fromArray($record->toArray());
        $this->assertEquals($record, $rebuilt);
        $this->assertNull($rebuilt->gers_id);
        $this->assertNull($rebuilt->confidence);
        $this->assertSame('Point', $rebuilt->geometry_type);
    }

    /** @test */
    public function from_array_rejects_a_missing_required_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NormalizedPlaceRecord::fromArray([
            'source'     => 'overture',
            'source_ref' => 'gers-x',
            // category_key missing
            'lon'        => -82.6,
            'lat'        => 27.7,
        ]);
    }
}
