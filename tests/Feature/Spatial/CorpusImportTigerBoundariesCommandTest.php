<?php

namespace Tests\Feature\Spatial;

use App\Services\Spatial\CorpusCopyLoader;
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
    public function it_writes_a_boundaries_payload_artifact(): void
    {
        Artisan::call('corpus:import-boundaries', ['--source' => 'tiger_county', '--out-dir' => $this->outDir('tiger_county')]);

        $this->assertFileExists($this->outDir('tiger_county') . '/boundaries_payload.txt');
    }

    /** @test */
    public function the_payload_has_one_line_per_kept_record(): void
    {
        Artisan::call('corpus:import-boundaries', ['--source' => 'tiger_county', '--out-dir' => $this->outDir('tiger_county')]);

        $payload = (string) file_get_contents($this->outDir('tiger_county') . '/boundaries_payload.txt');
        // Two kept records (the fixture's Polygon + native MultiPolygon); toCopyText LF-terminates each.
        $this->assertSame(2, substr_count($payload, "\n"));
        $this->assertCount(2, array_filter(explode("\n", $payload), static fn (string $l): bool => $l !== ''));
    }

    /** @test */
    public function each_payload_line_is_five_tab_separated_columns(): void
    {
        Artisan::call('corpus:import-boundaries', ['--source' => 'tiger_county', '--out-dir' => $this->outDir('tiger_county')]);

        $payload = (string) file_get_contents($this->outDir('tiger_county') . '/boundaries_payload.txt');
        foreach (array_filter(explode("\n", $payload), static fn (string $l): bool => $l !== '') as $line) {
            $this->assertCount(5, explode("\t", $line), 'each COPY row must have kind, external_ref, attrs, geom, corpus_version');
        }
    }

    /** @test */
    public function the_payload_is_byte_identical_to_the_copy_loader_over_the_staging_rows(): void
    {
        Artisan::call('corpus:import-boundaries', ['--source' => 'tiger_county', '--out-dir' => $this->outDir('tiger_county')]);

        $staging = json_decode((string) file_get_contents($this->outDir('tiger_county') . '/staging.json'), true, 512, JSON_THROW_ON_ERROR);
        $expected = (new CorpusCopyLoader())->toCopyText($staging['rows']);
        $actual = (string) file_get_contents($this->outDir('tiger_county') . '/boundaries_payload.txt');

        // Proves the payload derives from the SAME rows array as staging.json via the shared wire format.
        $this->assertSame($expected, $actual);
    }

    /** @test */
    public function the_default_corpus_version_is_unchanged_in_summary_and_payload(): void
    {
        Artisan::call('corpus:import-boundaries', ['--source' => 'tiger_county', '--out-dir' => $this->outDir('tiger_county')]);

        $summary = json_decode((string) file_get_contents($this->outDir('tiger_county') . '/summary.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('tiger_county-authoring-fixture', $summary['corpus_version']);

        $payload = (string) file_get_contents($this->outDir('tiger_county') . '/boundaries_payload.txt');
        foreach (array_filter(explode("\n", $payload), static fn (string $l): bool => $l !== '') as $line) {
            $cols = explode("\t", $line);
            $this->assertSame('tiger_county-authoring-fixture', end($cols));
        }
    }

    /** @test */
    public function a_custom_corpus_version_propagates_to_staging_summary_and_payload(): void
    {
        Artisan::call('corpus:import-boundaries', [
            '--source'         => 'tiger_county',
            '--out-dir'        => $this->outDir('tiger_county'),
            '--corpus-version' => 'tiger-2024',
        ]);

        $staging = json_decode((string) file_get_contents($this->outDir('tiger_county') . '/staging.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('tiger-2024', $staging['corpus_version']);
        foreach ($staging['rows'] as $row) {
            $this->assertSame('tiger-2024', $row[4], 'corpus_version is the 5th COPY column');
        }

        $summary = json_decode((string) file_get_contents($this->outDir('tiger_county') . '/summary.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('tiger-2024', $summary['corpus_version']);

        $payload = (string) file_get_contents($this->outDir('tiger_county') . '/boundaries_payload.txt');
        foreach (array_filter(explode("\n", $payload), static fn (string $l): bool => $l !== '') as $line) {
            $cols = explode("\t", $line);
            $this->assertSame('tiger-2024', end($cols));
        }
    }

    /** @test */
    public function an_acceptance_failure_writes_no_payload(): void
    {
        // A single row missing GEOID → rejected_invalid_field, zero kept → non_empty acceptance fails.
        $in = tempnam(sys_get_temp_dir(), 'tiger_reject_') . '.ndjson';
        file_put_contents($in, json_encode([
            'name'      => 'No-GEOID County',
            'statefp'   => '12',
            'geometry'  => ['type' => 'Polygon', 'coordinates' => [[[-81.0, 28.0], [-81.0, 28.01], [-80.99, 28.01], [-80.99, 28.0], [-81.0, 28.0]]]],
        ]) . "\n");

        $outDir = $this->outDir('reject');
        $exit = Artisan::call('corpus:import-boundaries', ['--source' => 'tiger_county', '--in' => $in, '--out-dir' => $outDir]);
        @unlink($in);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Acceptance FAILED', Artisan::output());
        $this->assertFileDoesNotExist($outDir . '/boundaries_payload.txt');
        // The early-return path writes only rejects.json.
        $this->assertFileDoesNotExist($outDir . '/staging.json');
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
