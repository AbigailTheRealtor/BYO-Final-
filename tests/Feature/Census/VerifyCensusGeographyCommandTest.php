<?php

namespace Tests\Feature\Census;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 1d-2 — `census:verify-geography` fails loudly on an incomplete corpus.
 *
 * A verification command that cannot fail is not a verification command. Each test here breaks the
 * corpus in one specific way and asserts a non-zero exit, because every one of these states would
 * otherwise present as an empty cascade rather than as an error:
 *
 *   - no rows at all (migrations ran, import never did)
 *   - one dataset empty (a partially restored database)
 *   - metadata missing (rows written by something other than the importer)
 *   - metadata disagreeing with the table (a truncate, or a manual edit)
 *   - two vintages present (relationships crossing publications)
 *
 * The happy path is asserted too, against the real fixtures, so a command that failed everything
 * would not pass this suite either.
 */
class VerifyCensusGeographyCommandTest extends TestCase
{
    use DatabaseTransactions;

    private function fixturePath(): string
    {
        return base_path('tests/fixtures/census/2020');
    }

    private function import(): int
    {
        return $this->artisan('census:import-geography', ['--path' => $this->fixturePath()])->run();
    }

    private function verify(array $options = []): int
    {
        return $this->artisan('census:verify-geography', $options)->run();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Happy path
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function a_complete_corpus_verifies(): void
    {
        $this->assertSame(0, $this->import());

        $this->assertSame(0, $this->verify(), 'A freshly imported corpus must verify cleanly.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Incomplete corpora fail
    // ─────────────────────────────────────────────────────────────────────

    /**
     * The state Phase 1d-2 is most likely to meet: migrations run, import never executed.
     *
     * This is exactly the condition that produces an empty cascade with no error, and it is the
     * reason this command exists at all.
     */
    /** @test */
    public function an_unpopulated_corpus_fails(): void
    {
        $this->assertSame(1, $this->verify(), 'Tables that exist but hold nothing must not verify.');
    }

    /** @test */
    public function a_single_empty_dataset_fails(): void
    {
        $this->import();

        DB::table('census_zcta_counties')->delete();

        $this->assertSame(1, $this->verify(), 'One empty relationship table is still a broken corpus.');
    }

    /** @test */
    public function missing_metadata_fails_even_when_the_tables_hold_data(): void
    {
        $this->import();

        DB::table('census_geography_meta')->where('dataset', 'places')->delete();

        $this->assertSame(
            1,
            $this->verify(),
            'Rows with no provenance are the exact situation the metadata table was created to end.'
        );
    }

    /**
     * A count that no longer matches its record.
     *
     * The table is non-empty and the metadata exists, so every existence check passes. Only the
     * comparison against the recorded count catches it — which is the whole point of recording it.
     */
    /** @test */
    public function a_table_changed_behind_the_importers_back_fails(): void
    {
        $this->import();

        DB::table('census_places')->orderBy('geoid')->limit(1)->delete();

        $this->assertSame(1, $this->verify());
    }

    /** @test */
    public function a_corpus_recording_two_vintages_fails(): void
    {
        $this->import();

        DB::table('census_geography_meta')
            ->where('dataset', 'zctas')
            ->update(['vintage' => '2030']);

        $this->assertSame(
            1,
            $this->verify(),
            'Mixed vintages break referential integrity across the schema and must be refused.'
        );
    }

    /** @test */
    public function a_vintage_nothing_was_imported_under_fails(): void
    {
        $this->import();

        $this->assertSame(
            1,
            $this->verify(['--vintage' => '2010']),
            'Asking about a vintage the corpus does not hold must fail, not silently pass.'
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Read-only
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function verification_changes_nothing(): void
    {
        $this->import();

        $tables = [
            'census_states', 'census_counties', 'census_places',
            'census_place_counties', 'census_zctas', 'census_zcta_counties',
            'census_geography_meta',
        ];

        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->count();
        }

        $this->verify();

        foreach ($tables as $table) {
            $this->assertSame(
                $before[$table],
                DB::table($table)->count(),
                "{$table} must be unchanged — the command reports, it does not repair."
            );
        }
    }
}
