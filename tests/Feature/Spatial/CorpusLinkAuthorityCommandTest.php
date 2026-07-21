<?php

namespace Tests\Feature\Spatial;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Batch 2D Part C1 — corpus:link-authority is an OFFLINE authoring tool: refuses production, opens
 * no DB/PostGIS connection, makes no network call, and authors link artifacts from synthetic
 * fixtures. Output asserted via Artisan::output() (Laravel 8.83 has no expectsOutputToContain()).
 */
class CorpusLinkAuthorityCommandTest extends TestCase
{
    private function outDir(): string
    {
        return storage_path('app/spatial/authority/link-test');
    }

    /** @test */
    public function it_links_the_synthetic_fixtures_and_exits_success(): void
    {
        $exit = Artisan::call('corpus:link-authority', ['--out-dir' => $this->outDir()]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('linked    : 1', $output);
        $this->assertStringContainsString('unlinked  : 1', $output);
        $this->assertStringContainsString('ambiguous : 1', $output);
        // Every acceptance check passed (no ✗ marks).
        $this->assertStringNotContainsString('✗', $output);
    }

    /** @test */
    public function the_authored_links_match_the_expected_fixture(): void
    {
        Artisan::call('corpus:link-authority', ['--out-dir' => $this->outDir()]);

        $actual = $this->readNdjson($this->outDir() . '/links.ndjson');
        $expected = $this->readNdjson(base_path('tests/fixtures/spatial/authority/expected_links.ndjson'));

        $this->assertSame($expected, $actual, 'authored links must equal the expected fixture');
    }

    /** @test */
    public function it_refuses_to_run_in_production(): void
    {
        // app()->environment() reads the container's `env` binding, not config('app.env').
        $this->app['env'] = 'production';

        $exit = Artisan::call('corpus:link-authority', ['--out-dir' => $this->outDir()]);
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('REFUSES to run in production', $output);
    }

    /** @return list<array<string,mixed>> */
    private function readNdjson(string $path): array
    {
        $this->assertFileExists($path);
        $rows = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) file_get_contents($path)) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $rows[] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            }
        }

        return $rows;
    }
}
