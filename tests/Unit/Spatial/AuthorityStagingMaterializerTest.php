<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\AuthorityRecord;
use App\Services\Spatial\AuthorityStagingMaterializer;
use Tests\TestCase;

/**
 * Batch 2D Part C2 — the authority_staging row materializer. Column order is the wire order the
 * Class-2 COPY consumes (asserted against the SQL manifest separately). Pure; no DB.
 */
class AuthorityStagingMaterializerTest extends TestCase
{
    /** @test */
    public function columns_are_the_authority_record_staging_order(): void
    {
        $this->assertSame(
            ['authority_source', 'authority_ref', 'name', 'lon', 'lat', 'authority_metric'],
            AuthorityStagingMaterializer::COLUMNS,
        );
    }

    /** @test */
    public function materialize_produces_a_column_keyed_row(): void
    {
        $row = (new AuthorityStagingMaterializer())->materialize(
            new AuthorityRecord('cms', '100001', 'Synthetic General Hospital', -82.64, 27.77, 4.0),
        );

        $this->assertSame(AuthorityStagingMaterializer::COLUMNS, array_keys($row));
        $this->assertSame('cms', $row['authority_source']);
        $this->assertSame('100001', $row['authority_ref']);
        $this->assertSame(4.0, $row['authority_metric']);
    }

    /** @test */
    public function to_row_preserves_the_canonical_column_order(): void
    {
        $ordered = (new AuthorityStagingMaterializer())->toRow(
            new AuthorityRecord('usgs', 'BR-0001', 'Synthetic Boat Ramp North', -80.19, 25.77, null),
        );

        $this->assertSame(['usgs', 'BR-0001', 'Synthetic Boat Ramp North', -80.19, 25.77, null], $ordered);
    }

    /** @test */
    public function to_rows_batches_in_input_order(): void
    {
        $rows = (new AuthorityStagingMaterializer())->toRows([
            new AuthorityRecord('cms', '100002', 'B', -82.63, 27.78, null),
            new AuthorityRecord('cms', '100001', 'A', -82.64, 27.77, 4.0),
        ]);

        $this->assertSame(['100002', '100001'], array_column($rows, 1));
    }
}
