<?php

namespace Tests\Feature\Spatial;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Batch 2D Part C2 — corpus:import-authority-overlay is an OFFLINE authoring tool: refuses
 * production, opens no DB/PostGIS connection, makes no network call, and authors overlay artifacts
 * from synthetic fixtures. Output asserted via Artisan::output() (Laravel 8.83 has no
 * expectsOutputToContain()).
 */
class CorpusImportAuthorityOverlayCommandTest extends TestCase
{
    private function outDir(string $source): string
    {
        return storage_path("app/spatial/authority/overlay-test/{$source}");
    }

    /** @test */
    public function it_imports_the_cms_fixture_and_exits_success(): void
    {
        $exit = Artisan::call('corpus:import-authority-overlay', ['--source' => 'cms', '--out-dir' => $this->outDir('cms')]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('kept                 : 2', $output);
        $this->assertStringContainsString('rejected_invalid     : 1', $output);
        $this->assertStringContainsString('rejected_out_of_domain : 1', $output);
        $this->assertStringContainsString('target   : link', $output);
        $this->assertStringNotContainsString('✗', $output);
    }

    /** @test */
    public function the_authored_cms_overlay_matches_the_expected_fixture(): void
    {
        Artisan::call('corpus:import-authority-overlay', ['--source' => 'cms', '--out-dir' => $this->outDir('cms')]);

        $actual = (string) file_get_contents($this->outDir('cms') . '/overlay.ndjson');
        $expected = (string) file_get_contents(base_path('tests/fixtures/spatial/authority_overlay/cms/expected_overlay.ndjson'));

        $this->assertSame($expected, $actual, 'authored CMS overlay must byte-match the expected fixture');
    }

    /** @test */
    public function it_imports_the_usgs_fixture_and_matches_the_expected_fixture(): void
    {
        $exit = Artisan::call('corpus:import-authority-overlay', ['--source' => 'usgs-boat-ramp', '--out-dir' => $this->outDir('usgs')]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('kept                 : 2', $output);
        $this->assertStringContainsString('target   : place', $output);

        $actual = (string) file_get_contents($this->outDir('usgs') . '/overlay.ndjson');
        $expected = (string) file_get_contents(base_path('tests/fixtures/spatial/authority_overlay/usgs/expected_overlay.ndjson'));
        $this->assertSame($expected, $actual, 'authored USGS overlay must byte-match the expected fixture');
    }

    /** @test */
    public function the_staging_artifact_carries_the_materializer_column_order(): void
    {
        Artisan::call('corpus:import-authority-overlay', ['--source' => 'cms', '--out-dir' => $this->outDir('cms')]);

        $staging = json_decode((string) file_get_contents($this->outDir('cms') . '/staging.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(
            ['authority_source', 'authority_ref', 'name', 'lon', 'lat', 'authority_metric'],
            $staging['columns'],
        );
        $this->assertSame('link', $staging['target']);
        $this->assertCount(2, $staging['rows']);
    }

    /** @test */
    public function an_unknown_source_exits_failure(): void
    {
        $exit = Artisan::call('corpus:import-authority-overlay', ['--source' => 'bogus', '--out-dir' => $this->outDir('bogus')]);
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Unknown --source', Artisan::output());
    }

    /** @test */
    public function it_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $exit = Artisan::call('corpus:import-authority-overlay', ['--source' => 'cms', '--out-dir' => $this->outDir('cms')]);
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('REFUSES to run in production', $output);
    }
}
