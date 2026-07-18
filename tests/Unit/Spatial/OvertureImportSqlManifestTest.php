<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\CorpusPartitionManager;
use App\Services\Spatial\PlaceRowMaterializer;
use Tests\TestCase;

/**
 * Batch 2C — the authored import SQL recipes must not drift from the framework
 * (partition naming, COPY column order) and must stay guarded + offline. Pure
 * file inspection; no DuckDB, no PostGIS. Mirrors 2A's OvertureExtractSqlManifest.
 */
class OvertureImportSqlManifestTest extends TestCase
{
    private string $spikeDir;
    private string $version = 'overture-2026-06-17.0-pinellas';

    protected function setUp(): void
    {
        parent::setUp();
        $this->spikeDir = dirname(__DIR__, 3) . '/spikes/phase-2-batch-2c-overture-import-framework';
    }

    /** @test */
    public function every_recipe_is_marked_authored_not_run_and_cluster_free(): void
    {
        foreach ($this->recipes() as $file) {
            $sql = $this->read("sql/{$file}");
            $this->assertStringContainsString('AUTHORED, NOT RUN', $sql, "{$file} must be marked authored-not-run");
            $this->assertStringContainsString('SPATIAL_', $sql, "{$file} must reference the (unset) spatial guard");
        }
    }

    /** @test */
    public function the_partition_recipe_matches_the_manager_naming(): void
    {
        $sql = $this->read('sql/create_partition.sql');
        $partition = (new CorpusPartitionManager())->partitionName($this->version);

        $this->assertStringContainsString($partition, $sql, 'partition name must match CorpusPartitionManager');
        $this->assertStringContainsString('LIKE places', $sql, 'preferred staging flow');
        $this->assertStringContainsString('PARTITION OF places', $sql, 'direct-partition alternative documented');
    }

    /** @test */
    public function the_copy_recipe_uses_the_materializer_column_order(): void
    {
        $sql = $this->read('sql/load_copy.sql');
        $this->assertStringContainsStringIgnoringCase('copy', $sql);
        $this->assertStringContainsString(implode(', ', PlaceRowMaterializer::COLUMNS), $sql,
            'COPY column list must equal PlaceRowMaterializer::COLUMNS');
    }

    /** @test */
    public function the_acceptance_recipe_is_read_only(): void
    {
        $sql = $this->read('sql/acceptance_checks.sql');
        $this->assertStringContainsString('count(*)', $sql);
        $this->assertStringContainsString('0.90', $sql, 'confidence floor must appear');
        foreach (['INSERT', 'UPDATE', 'DELETE', 'DROP', 'ATTACH'] as $writeVerb) {
            $this->assertStringNotContainsString($writeVerb, $sql, "acceptance must not {$writeVerb}");
        }
    }

    /** @test */
    public function the_activation_recipe_is_transactional_and_attaches(): void
    {
        $sql = $this->read('sql/attach_activate.sql');
        $this->assertStringContainsString('BEGIN;', $sql);
        $this->assertStringContainsString('COMMIT;', $sql);
        $this->assertStringContainsString('ATTACH PARTITION', $sql);
        $this->assertStringContainsString("status = 'active'", $sql);
    }

    /** @test */
    public function the_ledger_recipe_writes_exactly_one_corpus_imports_row(): void
    {
        $sql = $this->read('sql/ledger_insert.sql');
        $this->assertStringContainsString('INSERT INTO corpus_imports', $sql);
        $this->assertSame(1, substr_count(strtoupper($sql), 'INSERT INTO'), 'exactly one ledger row per import');
    }

    /** @test */
    public function the_spike_ships_a_readme_and_results_template(): void
    {
        $this->assertFileExists($this->spikeDir . '/README.md');
        $this->assertFileExists($this->spikeDir . '/RESULTS_TEMPLATE.md');
    }

    /** @return string[] */
    private function recipes(): array
    {
        return [
            'create_partition.sql',
            'load_copy.sql',
            'acceptance_checks.sql',
            'attach_activate.sql',
            'ledger_insert.sql',
        ];
    }

    private function read(string $rel): string
    {
        $path = $this->spikeDir . '/' . $rel;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
