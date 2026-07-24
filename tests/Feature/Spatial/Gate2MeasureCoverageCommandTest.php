<?php

namespace Tests\Feature\Spatial;

use App\Console\Commands\Gate2MeasureCoverage;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Batch 2D Part C3d-b — spatial:gate2-measure-coverage drives the LIVE Gate 2 measurement Florida-only:
 * it refuses production, refuses an unconfigured cluster, refuses any non-FL territory, measures FL via
 * the injected count runner, renders FL as absent/present while PR/AK/rural_CONUS stay unmeasured,
 * records exactly one idempotent corpus_imports row, and computes no metric / threshold / pass-fail.
 *
 * A fake count runner stands in for pgsql_spatial and a controlled in-memory SQLite connection stands
 * in for the ledger — the real cluster is never contacted here.
 */
class Gate2MeasureCoverageCommandTest extends TestCase
{
    private const CONN = 'gate2_cmd_test';

    protected function setUp(): void
    {
        parent::setUp();

        // Controlled ledger connection (jsonb → text) in place of the cluster.
        config(['database.connections.' . self::CONN => [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]]);
        config(['spatial_gate2_corpus.connection' => self::CONN]);
        DB::purge(self::CONN);
        DB::connection(self::CONN)->statement(<<<'SQL'
            CREATE TABLE corpus_imports (
                id integer PRIMARY KEY AUTOINCREMENT,
                dataset text, corpus_version text, row_count integer, bytes integer,
                territory_coverage text, started_at text, finished_at text, status text, notes text
            )
        SQL);

        // Fake count runner: every FL cell measures zero → absent (distinct from unmeasured).
        $this->app->instance(Gate2MeasureCoverage::COUNT_RUNNER_BINDING, static fn (string $sql, array $bindings): int => 0);
    }

    protected function tearDown(): void
    {
        DB::purge(self::CONN);
        parent::tearDown();
    }

    private function outDir(): string
    {
        return storage_path('app/spatial/gate2-corpus-test');
    }

    /** @return array<string,mixed> */
    private function runAndReadMatrix(): array
    {
        Artisan::call('spatial:gate2-measure-coverage', ['--out-dir' => $this->outDir()]);

        return json_decode((string) file_get_contents($this->outDir() . '/matrix.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param array<string,mixed> $matrix */
    private function cell(array $matrix, string $dataset, string $category, string $territory): array
    {
        foreach ($matrix['cells'] as $c) {
            if ($c['dataset'] === $dataset && $c['category'] === $category && $c['territory'] === $territory) {
                return $c;
            }
        }
        $this->fail("cell {$dataset}/{$category}/{$territory} not found in matrix");
    }

    /** @test */
    public function florida_only_run_succeeds_and_disclaims_gate_acceptance(): void
    {
        $exit = Artisan::call('spatial:gate2-measure-coverage', ['--out-dir' => $this->outDir()]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('LIVE MEASUREMENT', $output);
        $this->assertStringContainsString('FL only', $output);
        $this->assertStringContainsString('NO automated pass/fail', $output);
        $this->assertStringContainsString('This is NOT a Gate 2 pass', $output);
        $this->assertFileExists($this->outDir() . '/matrix.json');
        $this->assertFileExists($this->outDir() . '/summary.json');
    }

    /** @test */
    public function florida_cells_are_measured_while_pr_ak_and_watch_datasets_stay_unmeasured(): void
    {
        $matrix = $this->runAndReadMatrix();

        // FL, overture_places, count 0 → measured ABSENT (an honest gap on the empty corpus).
        $flGrocery = $this->cell($matrix, 'overture_places', 'grocery_store', 'FL');
        $this->assertTrue($flGrocery['measured']);
        $this->assertSame(0, $flGrocery['present_count']);
        $this->assertSame('absent', $flGrocery['status']);

        // Same dataset/category in PR was never queried → UNMEASURED (not a zero).
        $prGrocery = $this->cell($matrix, 'overture_places', 'grocery_store', 'PR');
        $this->assertFalse($prGrocery['measured']);
        $this->assertNull($prGrocery['present_count']);
        $this->assertSame('unmeasured', $prGrocery['status']);

        // A declared-unmeasured PR watch dataset is unmeasured even in FL.
        $flCusp = $this->cell($matrix, 'noaa_cusp', 'shoreline', 'FL');
        $this->assertFalse($flCusp['measured']);
        $this->assertSame('unmeasured', $flCusp['status']);

        // rural_CONUS is never measured (no FIPS).
        $ruralGrocery = $this->cell($matrix, 'overture_places', 'grocery_store', 'rural_CONUS');
        $this->assertSame('unmeasured', $ruralGrocery['status']);
    }

    /** @test */
    public function it_records_exactly_one_idempotent_ledger_row(): void
    {
        Artisan::call('spatial:gate2-measure-coverage', ['--out-dir' => $this->outDir(), '--corpus-version' => 'fl-pilot-test']);
        Artisan::call('spatial:gate2-measure-coverage', ['--out-dir' => $this->outDir(), '--corpus-version' => 'fl-pilot-test']);

        $rows = DB::connection(self::CONN)->select('SELECT * FROM corpus_imports');
        $this->assertCount(1, $rows, 're-running the same corpus_version must not duplicate the ledger row');

        $coverage = json_decode($rows[0]->territory_coverage, true);
        $this->assertSame(['FL'], $coverage['measured_territories']);
        $this->assertContains('PR', $coverage['unmeasured_territories']);
    }

    /** @test */
    public function it_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $exit = Artisan::call('spatial:gate2-measure-coverage', ['--out-dir' => $this->outDir()]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('REFUSES to run in production', Artisan::output());
        $this->assertSame(0, DB::connection(self::CONN)->selectOne('SELECT count(*) AS c FROM corpus_imports')->c);
    }

    /** @test */
    public function it_refuses_when_the_spatial_connection_is_not_configured(): void
    {
        config(['database.connections.gate2_absent' => ['driver' => 'pgsql', 'url' => null, 'host' => null, 'database' => null]]);
        config(['spatial_gate2_corpus.connection' => 'gate2_absent']);

        $exit = Artisan::call('spatial:gate2-measure-coverage', ['--out-dir' => $this->outDir()]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('not configured', Artisan::output());
    }

    /** @test */
    public function it_refuses_any_non_florida_territory(): void
    {
        $exit = Artisan::call('spatial:gate2-measure-coverage', ['--territory' => 'PR', '--out-dir' => $this->outDir()]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('FLORIDA-ONLY', Artisan::output());
        $this->assertSame(0, DB::connection(self::CONN)->selectOne('SELECT count(*) AS c FROM corpus_imports')->c);
    }

    /** @test */
    public function the_command_has_no_threshold_option_by_design(): void
    {
        $command = $this->app[ConsoleKernel::class]->all()['spatial:gate2-measure-coverage'];

        $this->assertFalse($command->getDefinition()->hasOption('threshold'), 'Gate 2 has no threshold in the SSOT');
    }
}
