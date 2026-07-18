<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\CorpusImportLedger;
use Tests\TestCase;

/**
 * Batch 2C — the corpus_imports provenance ledger builder. Pure: builds the row
 * and the parameterized SQL; never executes. No DB, no cluster.
 */
class CorpusImportLedgerTest extends TestCase
{
    private CorpusImportLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = new CorpusImportLedger();
    }

    private function build(array $over = []): array
    {
        return $this->ledger->build(
            dataset: $over['dataset'] ?? 'overture-places',
            corpusVersion: $over['corpusVersion'] ?? 'overture-2026-06-17.0-pinellas',
            rowCount: $over['rowCount'] ?? 8,
            territoryCoverage: $over['territory'] ?? ['region' => 'pinellas', 'crs' => 'EPSG:4326'],
            status: $over['status'] ?? CorpusImportLedger::STATUS_STAGING,
            notes: $over['notes'] ?? ['source' => 'overture'],
            bytes: $over['bytes'] ?? null,
        );
    }

    /** @test */
    public function bytes_default_to_the_450_planning_proxy(): void
    {
        $row = $this->build(['rowCount' => 8]);
        $this->assertSame(8 * 450, $row['bytes']);
        $this->assertSame('staging', $row['status']);
        $this->assertSame(8, $row['row_count']);
    }

    /** @test */
    public function an_explicit_measured_size_overrides_the_proxy(): void
    {
        $row = $this->build(['rowCount' => 8, 'bytes' => 99999]);
        $this->assertSame(99999, $row['bytes']);
    }

    /** @test */
    public function it_rejects_an_unknown_status(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->build(['status' => 'live']);
    }

    /** @test */
    public function it_rejects_a_negative_row_count(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->build(['rowCount' => -1]);
    }

    /** @test */
    public function insert_sql_covers_every_column_except_the_serial_id(): void
    {
        $sql = $this->ledger->insertSql();
        $this->assertStringContainsString('INSERT INTO corpus_imports (', $sql);
        $this->assertStringContainsString(implode(', ', CorpusImportLedger::COLUMNS), $sql);
        $this->assertStringNotContainsString(' id,', $sql, 'id is bigserial — never inserted');
        // one placeholder per column
        $this->assertSame(count(CorpusImportLedger::COLUMNS), substr_count($sql, '?'));
    }

    /** @test */
    public function insert_bindings_json_encode_the_jsonb_columns_in_column_order(): void
    {
        $row = $this->build();
        $bindings = $this->ledger->insertBindings($row);

        $this->assertSame(count(CorpusImportLedger::COLUMNS), count($bindings));

        $byCol = array_combine(CorpusImportLedger::COLUMNS, $bindings);
        $this->assertSame('overture-places', $byCol['dataset']);
        $this->assertSame(8, $byCol['row_count']);
        $this->assertSame('{"region":"pinellas","crs":"EPSG:4326"}', $byCol['territory_coverage']);
        $this->assertSame('{"source":"overture"}', $byCol['notes']);
    }

    /** @test */
    public function activate_and_supersede_sql_flip_the_status(): void
    {
        $activate = $this->ledger->activateSql();
        $this->assertStringContainsString("SET status = 'active', finished_at = now()", $activate);
        $this->assertStringContainsString("status = 'staging'", $activate);
        $this->assertStringContainsString('corpus_version = ?', $activate);

        $supersede = $this->ledger->supersedeSql();
        $this->assertStringContainsString("SET status = 'superseded'", $supersede);
        $this->assertStringContainsString("status = 'active'", $supersede);
    }
}
