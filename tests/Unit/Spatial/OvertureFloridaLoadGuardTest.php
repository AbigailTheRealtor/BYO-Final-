<?php

namespace Tests\Unit\Spatial;

use Tests\TestCase;

/**
 * Batch 2D Part C3d-d — load_florida_overture_places.sh is an operator-only orchestrator. It must
 * refuse to act without an explicit live opt-in, refuse production, require the secret without ever
 * printing it, run no download/extraction, and drive only the committed seeders + recipes. Guard
 * paths are exercised as a subprocess; the rest is static text inspection. No cluster, no psql.
 */
class OvertureFloridaLoadGuardTest extends TestCase
{
    private function script(): string
    {
        return base_path('spikes/phase-2-batch-2c-overture-import-framework/bin/load_florida_overture_places.sh');
    }

    /**
     * @param list<string> $args
     * @param array<string,string> $env
     * @return array{code:int,err:string}
     */
    private function runScript(array $args, array $env): array
    {
        $cmd = array_merge(['bash', $this->script()], $args);
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $env = array_merge(['PATH' => (string) getenv('PATH')], $env);
        $proc = proc_open($cmd, $descriptors, $pipes, null, $env);
        $this->assertIsResource($proc);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        return ['code' => $code, 'err' => $err . $out];
    }

    /** @test */
    public function it_refuses_without_the_live_flag(): void
    {
        $r = $this->runScript([], ['APP_ENV' => 'local']);
        $this->assertNotSame(0, $r['code']);
        $this->assertStringContainsString('i-understand-live', $r['err']);
    }

    /** @test */
    public function it_refuses_in_production(): void
    {
        $r = $this->runScript(['--i-understand-live'], ['APP_ENV' => 'production']);
        $this->assertNotSame(0, $r['code']);
        $this->assertStringContainsString('production', $r['err']);
    }

    /** @test */
    public function it_requires_the_spatial_database_url(): void
    {
        $r = $this->runScript(['--i-understand-live'], ['APP_ENV' => 'local']);
        $this->assertNotSame(0, $r['code']);
        $this->assertStringContainsString('SPATIAL_DATABASE_URL', $r['err']);
    }

    /** @test */
    public function it_never_echoes_the_secret(): void
    {
        $src = (string) file_get_contents($this->script());
        $this->assertDoesNotMatchRegularExpression('/echo[^\n]*SPATIAL_DATABASE_URL/', $src);
        $this->assertDoesNotMatchRegularExpression('/printf[^\n]*SPATIAL_DATABASE_URL/', $src);
    }

    /** @test */
    public function it_carries_no_download_or_extraction_logic(): void
    {
        $src = strtolower((string) file_get_contents($this->script()));
        foreach (['curl', 'wget', 'duckdb', 's3://', 'read_parquet', '/home/runner/worktrees', 'hi-05'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $src, "orchestrator must not contain '{$forbidden}'");
        }
    }

    /** @test */
    public function it_seeds_taxonomy_and_drives_the_committed_recipes(): void
    {
        $src = (string) file_get_contents($this->script());
        foreach ([
            'SpatialFirstSliceCategorySeeder',
            'SpatialOvertureCategoryMappingSeeder',
            'partition_load.sql',
            'verify_overture_fl.sql',
            'overture-2026-06-17.0-fl',
        ] as $ref) {
            $this->assertStringContainsString($ref, $src, "orchestrator must reference {$ref}");
        }
        // Taxonomy seed must precede the attach (compare actual execution markers,
        // not the header comment which names partition_load.sql first).
        $this->assertLessThan(
            strpos($src, 'ATTACH PARTITION'),
            strpos($src, 'db:seed'),
            'taxonomy must be seeded before the partition attach',
        );
    }
}
