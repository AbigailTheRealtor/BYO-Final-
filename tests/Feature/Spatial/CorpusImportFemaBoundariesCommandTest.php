<?php

namespace Tests\Feature\Spatial;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Batch 2D Part C3c — corpus:import-boundaries drives the two FEMA NFHL sources offline: refuses
 * production, opens no DB/PostGIS connection, makes no network call, and authors boundary artifacts
 * from synthetic fixtures that byte-match the expected golden output.
 */
class CorpusImportFemaBoundariesCommandTest extends TestCase
{
    /** @return array<string,array{0:string,1:string,2:string}> [source, kind, fixtureBase] */
    public static function femaSources(): array
    {
        return [
            'flood_zone'     => ['fema_flood_zone', 'flood_zone', 'flood_zone'],
            'flood_coverage' => ['fema_flood_coverage', 'flood_coverage', 'flood_coverage'],
        ];
    }

    private function outDir(string $source): string
    {
        return storage_path("app/spatial/boundaries/{$source}-test");
    }

    /**
     * @test
     * @dataProvider femaSources
     */
    public function it_imports_each_fema_layer_and_byte_matches_the_expected_fixture(string $source, string $kind, string $base): void
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
        $expected = (string) file_get_contents(base_path("tests/fixtures/spatial/boundaries/fema/{$base}_expected.ndjson"));
        $this->assertSame($expected, $actual, "authored {$source} boundaries must byte-match the expected fixture");
    }

    /**
     * @test
     * @dataProvider femaSources
     */
    public function the_rejects_artifact_names_the_missing_key_per_layer(string $source, string $kind, string $base): void
    {
        Artisan::call('corpus:import-boundaries', ['--source' => $source, '--out-dir' => $this->outDir($source)]);

        $rejects = json_decode((string) file_get_contents($this->outDir($source) . '/rejects.json'), true, 512, JSON_THROW_ON_ERROR);
        $expectedReason = $kind === 'flood_zone' ? 'invalid_missing_fld_ar_id' : 'invalid_missing_firm_pan';

        $this->assertSame(1, $rejects['reject_reasons'][$expectedReason] ?? null);
        $this->assertSame(1, $rejects['reject_reasons']['invalid_geometry'] ?? null);
        $this->assertSame([], $rejects['acceptance_failures']);
    }

    /** @test */
    public function the_staging_artifact_carries_the_source_derived_corpus_version_and_multipolygon_ewkt(): void
    {
        Artisan::call('corpus:import-boundaries', ['--source' => 'fema_flood_zone', '--out-dir' => $this->outDir('fema_flood_zone')]);

        $staging = json_decode((string) file_get_contents($this->outDir('fema_flood_zone') . '/staging.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['kind', 'external_ref', 'attrs', 'geom', 'corpus_version'], $staging['columns']);
        $this->assertSame('fema_flood_zone-authoring-fixture', $staging['corpus_version']); // D-C3b-2 source-derived
        $this->assertCount(2, $staging['rows']);
        $this->assertStringContainsString('SRID=4326;MULTIPOLYGON', $staging['rows'][0][3]);
    }

    /** @test */
    public function the_summary_reports_the_enforced_ref_checks_as_passing(): void
    {
        Artisan::call('corpus:import-boundaries', ['--source' => 'fema_flood_coverage', '--out-dir' => $this->outDir('fema_flood_coverage')]);

        $summary = json_decode((string) file_get_contents($this->outDir('fema_flood_coverage') . '/summary.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('flood_coverage', $summary['kind']);
        $this->assertTrue($summary['acceptance']['passed']);

        $checks = collect($summary['acceptance']['checks'])->keyBy('name');
        foreach (['ref_present', 'ref_unique', 'geometry_multipolygon'] as $name) {
            $this->assertTrue($checks[$name]['passed'], "{$name} must be enforced and passing");
        }
    }

    /** @test */
    public function it_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $exit = Artisan::call('corpus:import-boundaries', ['--source' => 'fema_flood_zone', '--out-dir' => $this->outDir('fema_flood_zone')]);
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('REFUSES to run in production', $output);
    }
}
