<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\AuthorityRecord;
use App\Services\Spatial\NormalizedPlaceRecord;
use App\Services\Spatial\PlaceAuthorityLinkMaterializer;
use Tests\TestCase;

/**
 * Batch 2D Part C1 — the place_authority_links row materializer. Column order must mirror
 * B1.2 migration 05; match_score rounds to numeric(4,3) (D5). Pure; no DB.
 */
class PlaceAuthorityLinkMaterializerTest extends TestCase
{
    /** @test */
    public function columns_match_migration_05_order(): void
    {
        $this->assertSame(
            ['authority_source', 'authority_ref', 'place_source', 'place_source_ref', 'match_method', 'match_score', 'reviewed_by'],
            PlaceAuthorityLinkMaterializer::COLUMNS,
        );
    }

    /** @test */
    public function materialize_produces_a_column_keyed_row_with_rounded_score(): void
    {
        $row = (new PlaceAuthorityLinkMaterializer())->materialize(
            'cms', 'A1', 'overture', 'P1', 'spatial_name', 0.8127, null,
        );

        $this->assertSame(PlaceAuthorityLinkMaterializer::COLUMNS, array_keys($row));
        $this->assertSame(0.813, $row['match_score'], 'score rounds to 3 dp (numeric(4,3))');
        $this->assertNull($row['reviewed_by']);
        $this->assertSame('spatial_name', $row['match_method']);
    }

    /** @test */
    public function an_unknown_match_method_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PlaceAuthorityLinkMaterializer())->materialize('cms', 'A1', 'overture', 'P1', 'bogus', 1.0);
    }

    /** @test */
    public function materialize_linked_sorts_by_authority_key_and_to_row_is_ordered(): void
    {
        $m = new PlaceAuthorityLinkMaterializer();

        $linked = [
            ['authority' => new AuthorityRecord('cms', 'A2', 'B', 0.0, 0.0), 'place' => $this->place('P2'), 'score' => 0.9, 'method' => 'spatial_name'],
            ['authority' => new AuthorityRecord('cms', 'A1', 'A', 0.0, 0.0), 'place' => $this->place('P1'), 'score' => 1.0, 'method' => 'spatial_name'],
        ];

        $rows = $m->materializeLinked($linked);
        $this->assertSame(['A1', 'A2'], array_column($rows, 'authority_ref'), 'sorted by (source, ref)');

        $ordered = $m->toRow($rows[0]);
        $this->assertSame(['cms', 'A1', 'overture', 'P1', 'spatial_name', 1.0, null], $ordered);
    }

    private function place(string $ref): NormalizedPlaceRecord
    {
        return new NormalizedPlaceRecord('overture', $ref, null, 'hospital', 'X', null, 0.95, 1, 0.0, 0.0, 'Point');
    }
}
