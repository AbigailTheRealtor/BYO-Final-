<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\BoundaryRecord;
use App\Services\Spatial\BoundaryRowMaterializer;
use Tests\TestCase;

/**
 * Batch 2D Part C3a — the boundaries row materializer. Column order mirrors migration 06; geometry
 * becomes deterministic SRID=4326 MultiPolygon EWKT. Pure; no DB.
 */
class BoundaryRowMaterializerTest extends TestCase
{
    private function record(): BoundaryRecord
    {
        $geometry = ['type' => 'MultiPolygon', 'coordinates' => [[[[-82.0, 27.0], [-82.0, 27.01], [-81.99, 27.01], [-81.99, 27.0], [-82.0, 27.0]]]]];

        return new BoundaryRecord('protected_area', 'PADUS-0001', $geometry, ['acres' => 1200.5, 'name' => 'X', 'source' => 'padus']);
    }

    /** @test */
    public function columns_mirror_migration_06_order(): void
    {
        $this->assertSame(
            ['kind', 'external_ref', 'attrs', 'geom', 'corpus_version'],
            BoundaryRowMaterializer::COLUMNS,
        );
    }

    /** @test */
    public function materialize_produces_a_column_keyed_row_with_multipolygon_ewkt(): void
    {
        $row = (new BoundaryRowMaterializer())->materialize($this->record(), 'padus-v1');

        $this->assertSame(BoundaryRowMaterializer::COLUMNS, array_keys($row));
        $this->assertSame('protected_area', $row['kind']);
        $this->assertSame('PADUS-0001', $row['external_ref']);
        $this->assertSame('padus-v1', $row['corpus_version']);
        $this->assertStringContainsString('"acres":1200.5', $row['attrs']);
        $this->assertSame(
            'SRID=4326;MULTIPOLYGON(((-82 27, -82 27.01, -81.99 27.01, -81.99 27, -82 27)))',
            $row['geom'],
        );
    }

    /** @test */
    public function ewkt_is_deterministic_and_trims_trailing_zeros(): void
    {
        $m = new BoundaryRowMaterializer();
        $ewkt = $m->multiPolygonEwkt($this->record()->geometry);
        // No scientific notation, no trailing ".0", stable text.
        $this->assertStringStartsWith('SRID=4326;MULTIPOLYGON(((', $ewkt);
        $this->assertStringNotContainsString('27.000', $ewkt);
        $this->assertSame($ewkt, $m->multiPolygonEwkt($this->record()->geometry));
    }

    /** @test */
    public function to_row_preserves_the_canonical_column_order(): void
    {
        $ordered = (new BoundaryRowMaterializer())->toRow($this->record(), 'padus-v1');
        $this->assertSame('protected_area', $ordered[0]);
        $this->assertSame('PADUS-0001', $ordered[1]);
        $this->assertStringContainsString('SRID=4326;MULTIPOLYGON', $ordered[3]);
        $this->assertSame('padus-v1', $ordered[4]);
    }

    /** @test */
    public function an_empty_corpus_version_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new BoundaryRowMaterializer())->materialize($this->record(), '   ');
    }
}
