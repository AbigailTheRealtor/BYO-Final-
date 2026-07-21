<?php

namespace Tests\Feature\Spatial;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Batch 2D Part C3b — corpus:import-boundaries drives the four Census TIGER sources offline: refuses
 * production, opens no DB/PostGIS connection, makes no network call, and authors boundary artifacts
 * from synthetic fixtures that byte-match the expected golden output.
 */
class CorpusImportTigerBoundariesCommandTest extends TestCase
{
    /** @return array<string,array{0:string,1:string,2:string}> [source, kind, fixtureBase] */
    public static function tigerSources(): array
    {
        return [
            'county'          => ['tiger_county', 'county', 'county'],
            'place'           => ['tiger_place', 'place', 'place'],
            'zcta'            => ['tiger_zcta', 'zcta', 'zcta'],
            'school_district' => ['tiger_school_district', 'school_district', 'school_district'],
        ];
    }

    private function outDir(string $source): string
    {
        return storage_path("app/spatial/boundaries/{$source}-test");
    }

    /**
     * @test
     * @dataProvider tigerSources
     */
    public function it_imports_each_tiger_layer_and_byte_matches_the_expected_fixture(string $source, string $kind, string $base): void
    {
        $exit = Artisan::call('corpus:import-boundaries', ['--source' => $source, '--out-dir' => $this->outDir($source)]);
        $output = Artisan::output();

        $this->assertSame(0, $exit, "command should succeed for {$source}");
        $this->assertStringContainsString('kept                     : 2', $output);
        $this->assertStringContainsString('rejected_invalid_geometry : 1', $output);
        $this->assertStringContainsString('rejected_invalid_field   : 1', $output);
        $this->assertStringContainsString("kind     : {$kind}", $output);
        $this->assertStringNotContainsString('✗', $output);

        $actual = (string) file_get_contents($this->outDir($source) . '/boundaries.ndjson');
        $expected = (string) file_get_contents(base_path("tests/fixtures/spatial/boundaries/tiger/{$base}_expected.ndjson"));
        $this->assertSame($expected, $actual, "authored {$source} boundaries must byte-match the expected fixture");
    }

    /** @test */
    public function the_staging_artifact_carries_the_source_derived_corpus_version_and_multipolygon_ewkt(): void
    {
        Artisan::call('corpus:import-boundaries', ['--source' => 'tiger_county', '--out-dir' => $this->outDir('tiger_county')]);

        $staging = json_decode((string) file_get_contents($this->outDir('tiger_county') . '/staging.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['kind', 'external_ref', 'attrs', 'geom', 'corpus_version'], $staging['columns']);
        $this->assertSame('tiger_county-authoring-fixture', $staging['corpus_version']); // D-C3b-2 source-derived
        $this->assertCount(2, $staging['rows']);
        $this->assertStringContainsString('SRID=4326;MULTIPOLYGON', $staging['rows'][0][3]);
    }

    /** @test */
    public function an_unknown_source_exits_failure(): void
    {
        $exit = Artisan::call('corpus:import-boundaries', ['--source' => 'tiger_bogus', '--out-dir' => $this->outDir('bogus')]);
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Unknown --source', Artisan::output());
    }

    /** @test */
    public function it_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $exit = Artisan::call('corpus:import-boundaries', ['--source' => 'tiger_county', '--out-dir' => $this->outDir('tiger_county')]);
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('REFUSES to run in production', $output);
    }
}
