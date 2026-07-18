<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\NormalizedPlaceRecord;
use App\Services\Spatial\PlaceRowMaterializer;
use Tests\TestCase;

/**
 * Batch 2C — normalized record → places row. Pure transform: EWKT geography,
 * fixed column order, no DB.
 */
class PlaceRowMaterializerTest extends TestCase
{
    private PlaceRowMaterializer $mat;
    private string $version = 'overture-2026-06-17.0-pinellas';

    protected function setUp(): void
    {
        parent::setUp();
        $this->mat = new PlaceRowMaterializer();
    }

    private function record(array $over = []): NormalizedPlaceRecord
    {
        return new NormalizedPlaceRecord(
            source: $over['source'] ?? 'overture',
            source_ref: $over['source_ref'] ?? 'ref-1',
            gers_id: $over['gers_id'] ?? 'ref-1',
            category_key: $over['category_key'] ?? 'gym',
            name: $over['name'] ?? 'Sunrise Fitness',
            brand: $over['brand'] ?? null,
            confidence: $over['confidence'] ?? 0.93,
            source_count: $over['source_count'] ?? 2,
            lon: $over['lon'] ?? -82.6550,
            lat: $over['lat'] ?? 27.8330,
        );
    }

    /** @test */
    public function it_materializes_point_geometry_as_ewkt_geography(): void
    {
        $row = $this->mat->materialize($this->record(), $this->version);

        $this->assertSame('SRID=4326;POINT(-82.655 27.833)', $row['geom']);
        $this->assertSame($row['geom'], $row['centroid'], 'point places share geom + centroid');
    }

    /** @test */
    public function it_carries_the_full_column_contract(): void
    {
        $row = $this->mat->materialize($this->record(['brand' => null]), $this->version);

        $this->assertSame($this->version, $row['corpus_version']);
        $this->assertSame('overture', $row['source']);
        $this->assertSame('ref-1', $row['source_ref']);
        $this->assertSame('gym', $row['category_key']);
        $this->assertNull($row['brand']);
        $this->assertNull($row['authority_metric']);
        $this->assertNull($row['first_seen']);
        $this->assertSame('{"geometry_type":"Point"}', $row['attrs']);
        $this->assertSame(2, $row['source_count']);
    }

    /** @test */
    public function to_row_preserves_the_canonical_column_order(): void
    {
        $row = $this->mat->toRow($this->record(), $this->version);
        $assoc = $this->mat->materialize($this->record(), $this->version);

        $this->assertSame(count(PlaceRowMaterializer::COLUMNS), count($row));
        foreach (PlaceRowMaterializer::COLUMNS as $i => $col) {
            $this->assertSame($assoc[$col], $row[$i], "column {$col} out of order");
        }
    }

    /** @test */
    public function the_stamp_fills_first_and_last_seen(): void
    {
        $row = $this->mat->materialize($this->record(), $this->version, '2026-07-18T00:00:00Z');
        $this->assertSame('2026-07-18T00:00:00Z', $row['first_seen']);
        $this->assertSame('2026-07-18T00:00:00Z', $row['last_seen']);
    }

    /** @test */
    public function it_refuses_out_of_range_coordinates(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->mat->materialize($this->record(['lon' => 200.0]), $this->version);
    }

    /** @test */
    public function it_refuses_an_empty_corpus_version(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->mat->materialize($this->record(), '  ');
    }

    /** @test */
    public function point_ewkt_trims_trailing_zeros_without_scientific_notation(): void
    {
        $this->assertSame('SRID=4326;POINT(-82.65 27.5)', $this->mat->pointEwkt(-82.6500, 27.5));
        $this->assertSame('SRID=4326;POINT(0 0)', $this->mat->pointEwkt(0.0, -0.0));
    }
}
