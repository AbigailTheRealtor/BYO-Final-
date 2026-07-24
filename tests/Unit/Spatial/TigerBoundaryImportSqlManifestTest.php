<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\BoundaryRowMaterializer;
use App\Services\Spatial\CorpusImportLedger;
use Tests\TestCase;

/**
 * Batch 2D Part C3b — the authored Class-2 TIGER boundary recipes must not drift from the framework
 * (COPY column order), stay guarded/offline, subdivide via ST_Subdivide (E-24), and verify geometry
 * with ST_IsValid without auto-applying ST_MakeValid. Pure file inspection; no PostGIS. Mirrors the
 * C3a BoundaryImportSqlManifest.
 */
class TigerBoundaryImportSqlManifestTest extends TestCase
{
    private string $spikeDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->spikeDir = dirname(__DIR__, 3) . '/spikes/phase-2-batch-2d-part-c3b-tiger-boundary-import';
    }

    private function sql(string $file): string
    {
        $path = $this->spikeDir . '/sql/' . $file;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /** @test */
    public function both_recipes_are_marked_authored_not_run_and_cluster_guarded(): void
    {
        foreach (['stage_boundaries.sql', 'load_tiger_boundaries.sql'] as $file) {
            $sql = $this->sql($file);
            $this->assertStringContainsString('AUTHORED, NOT RUN', $sql, "{$file} must be marked authored-not-run");
            $this->assertStringContainsString('SPATIAL_', $sql, "{$file} must reference the (unset) spatial guard");
        }
    }

    /** @test */
    public function the_stage_copy_column_list_equals_the_materializer_columns(): void
    {
        $this->assertStringContainsString(
            implode(', ', BoundaryRowMaterializer::COLUMNS),
            $this->sql('stage_boundaries.sql'),
            'stage COPY column list must equal BoundaryRowMaterializer::COLUMNS',
        );
    }

    /** @test */
    public function the_stage_recipe_never_writes_to_boundaries_directly(): void
    {
        $this->assertStringNotContainsString('INSERT INTO BOUNDARIES ', strtoupper($this->sql('stage_boundaries.sql')));
    }

    /** @test */
    public function the_load_recipe_appends_boundaries_and_derives_parts_via_st_subdivide(): void
    {
        $sql = $this->sql('load_tiger_boundaries.sql');
        $upper = strtoupper($sql);

        $this->assertSame(1, substr_count($upper, 'INSERT INTO BOUNDARIES '), 'exactly one boundaries append');
        $this->assertSame(1, substr_count($upper, 'INSERT INTO BOUNDARIES_PARTS'), 'exactly one boundaries_parts derivation');

        $this->assertStringContainsString('ST_Subdivide(', $sql);
        $this->assertStringContainsString('::geometry', $sql);
        $this->assertStringContainsString('::geography', $sql);
        $this->assertStringContainsString('256', $sql, 'ST_Subdivide <=256 vertex cap');

        // The four TIGER kinds are the load target.
        foreach (['county', 'place', 'zcta', 'school_district'] as $kind) {
            $this->assertStringContainsString("'{$kind}'", $sql, "load must target kind {$kind}");
        }
    }

    /** @test */
    public function the_load_recipe_verifies_with_st_isvalid_but_never_auto_applies_st_makevalid(): void
    {
        $sql = $this->sql('load_tiger_boundaries.sql');
        $upper = strtoupper($sql);

        $this->assertStringContainsString('ST_IsValid', $sql, 'geometry verification query present');
        $this->assertStringContainsString('ST_MakeValid', $sql, 'ST_MakeValid documented as possible remediation');
        $this->assertStringNotContainsString('UPDATE ', $upper, 'no auto-remediation UPDATE');
        foreach (['DELETE ', 'DROP ', 'ATTACH ', 'TRUNCATE '] as $verb) {
            $this->assertStringNotContainsString($verb, $upper, "recipe must not {$verb}");
        }
    }

    /** @test */
    public function the_spike_ships_a_readme_and_results_template(): void
    {
        $this->assertFileExists($this->spikeDir . '/README.md');
        $this->assertFileExists($this->spikeDir . '/RESULTS_TEMPLATE.md');
    }

    // ---- C3d-c: Florida county load recipe (G4 ledger, G6 verify, G5 runbook) ----

    /** @test */
    public function the_c3d_c_recipe_files_exist(): void
    {
        foreach ([
            'sql/ledger_insert.sql',
            'sql/ledger_activate.sql',
            'sql/verify_boundaries.sql',
            'bin/tiger_county_shp_to_ndjson.sh',
            'bin/load_florida_counties.sh',
            'RUNBOOK.md',
        ] as $rel) {
            $this->assertFileExists($this->spikeDir . '/' . $rel);
        }
    }

    /** @test */
    public function the_ledger_insert_is_authored_guarded_and_single_row_with_matching_columns(): void
    {
        $sql = $this->sql('ledger_insert.sql');

        $this->assertStringContainsString('AUTHORED, NOT RUN', $sql);
        $this->assertStringContainsString('SPATIAL_', $sql, 'must reference the (unset) spatial guard');

        // Exactly one INSERT, targeting corpus_imports.
        $this->assertSame(1, substr_count(strtoupper($sql), 'INSERT INTO CORPUS_IMPORTS'), 'exactly one ledger insert');

        // Column list mirrors CorpusImportLedger::COLUMNS, in order.
        $this->assertStringContainsString(implode(', ', CorpusImportLedger::COLUMNS), $sql,
            'ledger insert column list must equal CorpusImportLedger::COLUMNS');

        $this->assertStringContainsString("'census-tiger-county-fl'", $sql, 'fixed dataset name');
        $this->assertStringContainsString("'staging'", $sql, 'inserted as staging status');
        // Idempotent on (dataset, corpus_version) — never a second row for the same import.
        $this->assertMatchesRegularExpression('/WHERE\s+NOT\s+EXISTS/i', $sql, 'insert must guard against a duplicate ledger row');
    }

    /** @test */
    public function the_ledger_activate_is_tightly_scoped(): void
    {
        $sql = $this->sql('ledger_activate.sql');
        $upper = strtoupper($sql);

        $this->assertStringContainsString('AUTHORED, NOT RUN', $sql);
        $this->assertSame(1, substr_count($upper, 'UPDATE CORPUS_IMPORTS'), 'exactly one update');
        $this->assertStringContainsString("status = 'active'", $sql);
        $this->assertStringContainsString('finished_at = now()', $sql);
        // Scope: dataset + corpus_version + status = staging (never touches unrelated rows).
        $this->assertStringContainsString("dataset = 'census-tiger-county-fl'", $sql);
        $this->assertStringContainsString("corpus_version = :'corpus_version'", $sql);
        $this->assertStringContainsString("status = 'staging'", $sql);
        // Must not widen the blast radius.
        foreach (['DELETE ', 'DROP ', 'TRUNCATE ', 'INSERT '] as $verb) {
            $this->assertStringNotContainsString($verb, $upper, "activate must not {$verb}");
        }
    }

    /** @test */
    public function the_verify_sql_is_read_only_and_carries_every_proof(): void
    {
        $sql = $this->sql('verify_boundaries.sql');
        $upper = strtoupper($sql);

        $this->assertStringContainsString('AUTHORED, NOT RUN', $sql);
        $this->assertStringContainsString('READ-ONLY', $upper);

        // Strictly read-only — scan the EXECUTABLE SQL only (strip `--` comment lines so the
        // read-only description in the header does not self-trip), for no mutating/DDL verb.
        $executable = strtoupper(implode("\n", array_filter(
            explode("\n", $sql),
            static fn (string $line): bool => !str_starts_with(ltrim($line), '--'),
        )));
        foreach (['INSERT ', 'UPDATE ', 'DELETE ', 'DROP ', 'TRUNCATE ', 'ALTER ', 'CREATE ', 'ST_MAKEVALID'] as $verb) {
            $this->assertStringNotContainsString($verb, $executable, "verify SQL must not contain {$verb}");
        }

        // Proofs present.
        $this->assertStringContainsString('= 67', $sql, '67 county count proof');
        $this->assertStringContainsString('>= 67', $sql, 'parts >= 67 proof');
        $this->assertStringContainsString("'^12[0-9]{3}\$'", $sql, 'Florida GEOID regex proof');
        $this->assertStringContainsString("attrs->>'state_fips'", $sql, 'state_fips = 12 proof');
        $this->assertStringContainsString('ST_IsValid', $sql, 'geometry validity proof');
        $this->assertStringContainsString('ST_NPoints', $sql, 'subdivision vertex proof');
        $this->assertStringContainsString('256', $sql, 'ST_Subdivide 256-vertex cap proof');
        $this->assertStringContainsString("status = 'active'", $sql, 'active ledger row proof');
    }

    /** @test */
    public function the_runbook_references_every_committed_script_and_sql_file(): void
    {
        $runbook = (string) file_get_contents($this->spikeDir . '/RUNBOOK.md');

        foreach ([
            'bin/tiger_county_shp_to_ndjson.sh',
            'bin/load_florida_counties.sh',
            'sql/stage_boundaries.sql',
            'sql/load_tiger_boundaries.sql',
            'sql/ledger_insert.sql',
            'sql/ledger_activate.sql',
            'sql/verify_boundaries.sql',
        ] as $ref) {
            $this->assertStringContainsString($ref, $runbook, "RUNBOOK.md must reference {$ref}");
        }
    }
}
