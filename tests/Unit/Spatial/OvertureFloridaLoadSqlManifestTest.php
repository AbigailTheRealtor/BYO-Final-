<?php

namespace Tests\Unit\Spatial;

use Tests\TestCase;

/**
 * Batch 2D Part C3d-d — the Florida Overture load recipe must ship a guarded orchestrator, a strictly
 * read-only verify SQL carrying every proof, and a runbook that references the committed artifacts.
 * Pure file inspection; no PostGIS, no DuckDB.
 */
class OvertureFloridaLoadSqlManifestTest extends TestCase
{
    private string $spikeDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->spikeDir = dirname(__DIR__, 3) . '/spikes/phase-2-batch-2c-overture-import-framework';
    }

    private function read(string $rel): string
    {
        $path = $this->spikeDir . '/' . $rel;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /** @test */
    public function the_c3d_d_recipe_files_exist(): void
    {
        foreach ([
            'bin/load_florida_overture_places.sh',
            'sql/verify_overture_fl.sql',
            'RUNBOOK.md',
        ] as $rel) {
            $this->assertFileExists($this->spikeDir . '/' . $rel);
        }
    }

    /** @test */
    public function the_verify_sql_is_read_only(): void
    {
        $sql = $this->read('sql/verify_overture_fl.sql');
        $this->assertStringContainsString('AUTHORED, NOT RUN', $sql);
        $this->assertStringContainsString('READ-ONLY', strtoupper($sql));

        // Scan the EXECUTABLE SQL only (strip `--` comment lines) for any mutating/DDL verb.
        $executable = strtoupper(implode("\n", array_filter(
            explode("\n", $sql),
            static fn (string $line): bool => !str_starts_with(ltrim($line), '--'),
        )));
        foreach (['INSERT ', 'UPDATE ', 'DELETE ', 'DROP ', 'TRUNCATE ', 'ALTER ', 'CREATE ', 'ATTACH ', 'ST_MAKEVALID'] as $verb) {
            $this->assertStringNotContainsString($verb, $executable, "verify SQL must not contain {$verb}");
        }
    }

    /** @test */
    public function the_verify_sql_carries_every_proof(): void
    {
        $sql = $this->read('sql/verify_overture_fl.sql');

        // corpus_version parameterized (default + override).
        $this->assertStringContainsString(":'corpus_version'", $sql);
        $this->assertStringContainsString('overture-2026-06-17.0-fl', $sql, 'default FL corpus_version');

        // Taxonomy proofs (7 categories, 8 mappings).
        foreach (['grocery_store', 'restaurant', 'pharmacy', 'shopping_center', 'coffee_shop', 'gym', 'gas_station'] as $cat) {
            $this->assertStringContainsString("'{$cat}'", $sql, "verify must reference category {$cat}");
        }
        $this->assertStringContainsString('= 8', $sql, '8 overture mappings proof');
        $this->assertStringContainsString("source = 'overture'", $sql);

        // Integrity proofs.
        $this->assertStringContainsString('place_categories', $sql, 'unregistered-category proof');
        $this->assertStringContainsString('0.90', $sql, 'confidence floor proof');
        $this->assertStringContainsString('ST_SRID', $sql);
        $this->assertStringContainsString('4326', $sql);
        $this->assertStringContainsString("'tiger-2024'", $sql, 'Florida county attribution proof');
        $this->assertStringContainsString('ST_Covers', $sql);
        $this->assertStringContainsString('pg_inherits', $sql, 'partition-attached proof');
        $this->assertStringContainsString("status = 'active'", $sql, 'active ledger proof');
        $this->assertStringContainsString("dataset = 'overture-places'", $sql);
    }

    /** @test */
    public function the_runbook_references_the_committed_artifacts(): void
    {
        $runbook = $this->read('RUNBOOK.md');
        foreach ([
            'bin/load_florida_overture_places.sh',
            'sql/verify_overture_fl.sql',
            'corpus:import-overture',
            'partition_load.sql',
            'overture-2026-06-17.0-fl',
        ] as $ref) {
            $this->assertStringContainsString($ref, $runbook, "RUNBOOK.md must reference {$ref}");
        }
    }
}
