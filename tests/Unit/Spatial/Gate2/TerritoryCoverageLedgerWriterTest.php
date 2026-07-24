<?php

namespace Tests\Unit\Spatial\Gate2;

use App\Services\Spatial\CorpusImportLedger;
use App\Services\Spatial\Gate2\TerritoryCoverageLedgerWriter;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Batch 2D Part C3d-b — TerritoryCoverageLedgerWriter writes exactly one corpus_imports row per
 * measurement run, stores territory_coverage as JSON, is idempotent on (corpus_version, dataset), and
 * reuses the CorpusImportLedger contract. Exercised against a controlled in-memory SQLite connection
 * (jsonb → text) — never the real cluster.
 */
class TerritoryCoverageLedgerWriterTest extends TestCase
{
    private const CONN = 'gate2_ledger_test';

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.' . self::CONN => [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]]);
        DB::purge(self::CONN);

        // corpus_imports shaped like B1.2 migration 11 (jsonb columns as text for SQLite).
        DB::connection(self::CONN)->statement(<<<'SQL'
            CREATE TABLE corpus_imports (
                id integer PRIMARY KEY AUTOINCREMENT,
                dataset text,
                corpus_version text,
                row_count integer,
                bytes integer,
                territory_coverage text,
                started_at text,
                finished_at text,
                status text,
                notes text
            )
        SQL);
    }

    protected function tearDown(): void
    {
        DB::purge(self::CONN);
        parent::tearDown();
    }

    private function writer(): TerritoryCoverageLedgerWriter
    {
        return new TerritoryCoverageLedgerWriter(DB::connection(self::CONN));
    }

    private function rowCount(): int
    {
        return (int) DB::connection(self::CONN)->selectOne('SELECT count(*) AS c FROM corpus_imports')->c;
    }

    /** @test */
    public function it_writes_exactly_one_row_with_territory_coverage_as_json(): void
    {
        $coverage = ['scope' => 'c3d-b-fl-pilot', 'measured_territories' => ['FL']];

        $result = $this->writer()->write('gate2_coverage', 'fl-pilot-2026', 42, $coverage, ['no_metric' => true]);

        $this->assertTrue($result['written']);
        $this->assertFalse($result['skipped']);
        $this->assertSame(1, $this->rowCount());

        $row = DB::connection(self::CONN)->selectOne('SELECT * FROM corpus_imports LIMIT 1');
        $this->assertSame('gate2_coverage', $row->dataset);
        $this->assertSame('fl-pilot-2026', $row->corpus_version);
        $this->assertSame(42, (int) $row->row_count);
        $this->assertSame(CorpusImportLedger::STATUS_ACTIVE, $row->status);
        $this->assertSame($coverage, json_decode($row->territory_coverage, true));
        // Reuses CorpusImportLedger: bytes defaults to the row_count × 450 sizing proxy.
        $this->assertSame(42 * 450, (int) $row->bytes);
    }

    /** @test */
    public function it_is_idempotent_on_corpus_version_and_dataset(): void
    {
        $this->writer()->write('gate2_coverage', 'fl-pilot-2026', 1, ['a' => 1]);
        $again = $this->writer()->write('gate2_coverage', 'fl-pilot-2026', 999, ['a' => 2]);

        $this->assertFalse($again['written']);
        $this->assertTrue($again['skipped']);
        $this->assertSame(1, $this->rowCount(), 're-running the same run key must not duplicate the row');
    }

    /** @test */
    public function a_different_corpus_version_writes_a_new_row(): void
    {
        $this->writer()->write('gate2_coverage', 'fl-pilot-2026', 1, ['a' => 1]);
        $this->writer()->write('gate2_coverage', 'fl-pilot-2027', 1, ['a' => 1]);

        $this->assertSame(2, $this->rowCount());
    }

    /** @test */
    public function it_rejects_an_empty_corpus_version(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->writer()->write('gate2_coverage', '   ', 1, []);
    }
}
