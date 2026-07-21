<?php

namespace Tests\Feature\Spatial;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Batch 2D Part C3a — corpus:import-boundaries is an OFFLINE authoring tool: refuses production,
 * opens no DB/PostGIS connection, makes no network call, and authors boundary artifacts from
 * synthetic fixtures. Output asserted via Artisan::output() (Laravel 8.83 has no
 * expectsOutputToContain()).
 */
class CorpusImportBoundariesCommandTest extends TestCase
{
    private function outDir(string $source): string
    {
        return storage_path("app/spatial/boundaries/{$source}-test");
    }

    /** @test */
    public function it_imports_the_padus_fixture_and_exits_success(): void
    {
        $exit = Artisan::call('corpus:import-boundaries', ['--source' => 'padus', '--out-dir' => $this->outDir('padus')]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('kept                     : 3', $output);
        $this->assertStringContainsString('rejected_invalid_geometry : 1', $output);
        $this->assertStringContainsString('rejected_invalid_field   : 1', $output);
        $this->assertStringContainsString('kind     : protected_area', $output);
        $this->assertStringNotContainsString('✗', $output);
    }

    /** @test */
    public function the_authored_boundaries_match_the_expected_fixture(): void
    {
        Artisan::call('corpus:import-boundaries', ['--source' => 'padus', '--out-dir' => $this->outDir('padus')]);

        $actual = (string) file_get_contents($this->outDir('padus') . '/boundaries.ndjson');
        $expected = (string) file_get_contents(base_path('tests/fixtures/spatial/boundaries/padus/expected_boundaries.ndjson'));

        $this->assertSame($expected, $actual, 'authored boundaries must byte-match the expected fixture');
    }

    /** @test */
    public function the_staging_artifact_carries_the_materializer_column_order_and_multipolygon_ewkt(): void
    {
        Artisan::call('corpus:import-boundaries', ['--source' => 'padus', '--out-dir' => $this->outDir('padus')]);

        $staging = json_decode((string) file_get_contents($this->outDir('padus') . '/staging.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['kind', 'external_ref', 'attrs', 'geom', 'corpus_version'], $staging['columns']);
        $this->assertCount(3, $staging['rows']);
        // geom column (index 3) is MultiPolygon EWKT.
        $this->assertStringContainsString('SRID=4326;MULTIPOLYGON', $staging['rows'][0][3]);
    }

    /** @test */
    public function an_unknown_source_exits_failure(): void
    {
        $exit = Artisan::call('corpus:import-boundaries', ['--source' => 'bogus', '--out-dir' => $this->outDir('bogus')]);
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Unknown --source', Artisan::output());
    }

    /** @test */
    public function it_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $exit = Artisan::call('corpus:import-boundaries', ['--source' => 'padus', '--out-dir' => $this->outDir('padus')]);
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('REFUSES to run in production', $output);
    }
}
