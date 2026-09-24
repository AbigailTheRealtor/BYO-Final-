<?php

namespace Tests\Feature\Spatial\OvertureV2Import;

use App\Console\Commands\CorpusImportOvertureV2;
use App\Support\Safeguards\ProductionDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * `corpus:import-overture-v2` on the SQLite suite: registration, the explicit write target, the
 * production refusal and the dry run. Nothing here can reach PostGIS, so every --write case below
 * is one the command must refuse BEFORE it opens a connection; the real writes are exercised
 * against a scratch PostGIS by OvertureV2PostgisIntegrationTest.
 */
class CorpusImportOvertureV2CommandTest extends TestCase
{
    use BuildsOvertureV2Extraction;

    private const COMMAND = 'corpus:import-overture-v2';

    protected function tearDown(): void
    {
        $this->tearDownExtractions();
        parent::tearDown();
    }

    /** A declared fixture contract and its extraction directory. */
    private function declaredFixture(): string
    {
        $dir = $this->extractFixture();
        config(['overture_v2_corpus' => $this->contractConfigFor($dir)]);

        return $dir;
    }

    /** @return array{0: int, 1: string} */
    private function runImport(array $args): array
    {
        $output = new BufferedOutput();
        $code = $this->app[Kernel::class]->call(self::COMMAND, $args, $output);

        return [$code, $output->fetch()];
    }

    /** @return list<string> */
    private function openConnections(): array
    {
        return array_keys(DB::getConnections());
    }

    public function test_the_command_is_registered_by_the_kernel_directory_scan(): void
    {
        $this->assertInstanceOf(CorpusImportOvertureV2::class, Artisan::all()[self::COMMAND] ?? null);
        $this->assertStringContainsString("\$this->load(__DIR__ . '/Commands')", (string) file_get_contents(app_path('Console/Kernel.php')));
    }

    public function test_the_write_target_has_no_default(): void
    {
        $option = Artisan::all()[self::COMMAND]->getDefinition()->getOption('database');

        $this->assertNull($option->getDefault(), 'a default here would make --write reach a configured database nobody named');
        $this->assertFalse(Artisan::all()[self::COMMAND]->getDefinition()->getOption('write')->acceptValue());
    }

    public function test_a_dry_run_validates_without_any_database(): void
    {
        $dir = $this->declaredFixture();
        $before = $this->openConnections();

        [$code, $out] = $this->runImport(['--corpus-version' => $this->fixtureVersion, '--extract-dir' => $dir]);

        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('VALIDATED — dry run, nothing written', $out);
        $this->assertStringContainsString('chain memberships           : 7', $out);
        $this->assertSame($before, $this->openConnections(), 'a dry run must open no connection');
    }

    public function test_write_without_database_refuses_before_any_connection_and_never_falls_back(): void
    {
        $dir = $this->declaredFixture();
        // A reachable-looking spatial connection: if --write fell back to it, the command would
        // open it (and fail differently). It must not even be resolved.
        config(['database.connections.pgsql_spatial.host' => '127.0.0.1', 'database.connections.pgsql_spatial.port' => '1']);
        $before = $this->openConnections();

        [$code, $out] = $this->runImport(['--corpus-version' => $this->fixtureVersion, '--extract-dir' => $dir, '--write' => true]);

        $this->assertSame(1, $code, $out);
        $this->assertStringContainsString('--write requires an explicit --database=<connection>', $out);
        $this->assertStringNotContainsString('overture v2 import', $out, 'the refusal comes before the extraction is even read');
        $this->assertSame($before, $this->openConnections());
        $this->assertNotContains('pgsql_spatial', $this->openConnections());
    }

    public function test_an_empty_database_name_is_the_same_refusal(): void
    {
        $dir = $this->declaredFixture();

        [$code, $out] = $this->runImport(['--corpus-version' => $this->fixtureVersion, '--extract-dir' => $dir, '--write' => true, '--database' => '  ']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('There is no default write target', $out);
    }

    public function test_an_unknown_connection_is_refused(): void
    {
        $dir = $this->declaredFixture();

        [$code, $out] = $this->runImport(['--corpus-version' => $this->fixtureVersion, '--extract-dir' => $dir, '--write' => true, '--database' => 'pgsql_spatail']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('[pgsql_spatail] is not a configured database connection', $out);
    }

    public function test_the_application_default_connection_is_refused(): void
    {
        $dir = $this->declaredFixture();
        $before = $this->openConnections();

        [$code, $out] = $this->runImport(['--corpus-version' => $this->fixtureVersion, '--extract-dir' => $dir, '--write' => true, '--database' => config('database.default')]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString("is the application's default connection", $out);
        $this->assertSame($before, $this->openConnections());
    }

    public function test_a_connection_that_is_not_postgresql_is_refused(): void
    {
        $dir = $this->declaredFixture();
        config(['database.connections.overture_v2_not_pg' => ['driver' => 'sqlite', 'database' => ':memory:']]);

        [$code, $out] = $this->runImport(['--corpus-version' => $this->fixtureVersion, '--extract-dir' => $dir, '--write' => true, '--database' => 'overture_v2_not_pg']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('is not PostgreSQL', $out);
        $this->assertNotContains('overture_v2_not_pg', $this->openConnections());
    }

    public function test_production_is_refused_by_the_shared_guard_before_anything_is_read(): void
    {
        $dir = $this->declaredFixture();
        $original = config('database.connections.pgsql');

        try {
            config(['database.connections.pgsql' => ['driver' => 'pgsql', 'url' => null, 'host' => 'helium', 'database' => 'heliumdb']]);
            $this->assertTrue(ProductionDatabaseGuard::assessApplication($this->app)->isProduction());

            [$code, $out] = $this->runImport(['--corpus-version' => $this->fixtureVersion, '--extract-dir' => $dir]);

            $this->assertSame(ProductionDatabaseGuard::EXIT_REFUSED, $code);
            $this->assertStringNotContainsString('overture v2 import', $out, 'the command body must not run');
        } finally {
            config(['database.connections.pgsql' => $original]);
        }
    }

    /**
     * The shared guard reads the application environment too, so it refuses first; the command's
     * own environment('production') check behind it is deliberate defence in depth, and this test
     * pins only the outcome — refused, body never ran.
     */
    public function test_the_production_application_environment_is_refused(): void
    {
        $dir = $this->declaredFixture();
        $env = $this->app['env'];

        try {
            $this->app['env'] = 'production';
            [$code, $out] = $this->runImport(['--corpus-version' => $this->fixtureVersion, '--extract-dir' => $dir]);
        } finally {
            $this->app['env'] = $env;
        }

        $this->assertNotSame(0, $code);
        $this->assertStringNotContainsString('overture v2 import', $out);
    }

    public function test_missing_arguments_and_an_undeclared_version_are_refused(): void
    {
        $dir = $this->declaredFixture();

        [$code, $out] = $this->runImport(['--extract-dir' => $dir]);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('--corpus-version and --extract-dir are required', $out);

        [$code, $out] = $this->runImport(['--corpus-version' => 'overture-2026-08-19.0-fl-r91', '--extract-dir' => $dir]);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('REFUSED, nothing written: no import contract is declared', $out);
    }

    public function test_an_unresolved_rescue_verdict_is_refused_on_a_dry_run(): void
    {
        // Declared AFTER the edit, so the contract matches the tampered files and the refusal is
        // the row rule's — not the checksum gate's.
        $dir = $this->extractFixture();
        $this->editRow($dir, 'supplementary.ndjson', 'im-06', function (array &$r): void {
            $r['rescue_verdict'] = 'pending';
        });
        config(['overture_v2_corpus' => $this->contractConfigFor($dir)]);

        [$code, $out] = $this->runImport(['--corpus-version' => $this->fixtureVersion, '--extract-dir' => $dir]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('REFUSED, nothing written: supplementary.ndjson', $out);
        $this->assertStringContainsString('unresolved or inconsistent rescue verdict', $out);
    }

    public function test_a_file_changed_after_the_contract_was_declared_is_refused_on_a_dry_run(): void
    {
        $dir = $this->declaredFixture();
        $this->editRow($dir, 'base.ndjson', 'im-01', function (array &$r): void {
            $r['name'] = 'Somebody Else';
        });

        [$code, $out] = $this->runImport(['--corpus-version' => $this->fixtureVersion, '--extract-dir' => $dir]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('REFUSED, nothing written: manifest outputs.base.ndjson sha256', $out);
    }

    public function test_the_guard_is_the_first_statement_and_no_connection_is_named_in_code(): void
    {
        $source = (string) file_get_contents(app_path('Console/Commands/CorpusImportOvertureV2.php'));

        $this->assertMatchesRegularExpression('/public function handle\(\): int\s*\{\s*if \(\$this->refusesProductionDatabase\(\)\) \{/', $source);
        $this->assertStringNotContainsString('i-know-this-is-production', $source, 'a command that writes may not accept the override');

        $code = '';
        foreach (token_get_all($source) as $t) {
            if (! is_array($t) || ! in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $code .= is_array($t) ? $t[1] : $t;
            }
        }
        $this->assertStringNotContainsString('pgsql_spatial', $code, 'the write target is named by the operator, never by the code');
    }
}
