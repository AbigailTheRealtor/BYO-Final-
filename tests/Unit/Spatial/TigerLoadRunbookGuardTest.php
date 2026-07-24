<?php

namespace Tests\Unit\Spatial;

use Tests\TestCase;

/**
 * Batch 2D Part C3d-c (G5) — load_florida_counties.sh is an operator-only orchestrator. It must
 * refuse to do anything live without an explicit opt-in, never leak the secret, run only the
 * committed boundary recipes, and carry no download / unrelated-slice / worktree logic. Guard paths
 * are exercised as a subprocess; the rest is static text inspection. No cluster, no psql.
 */
class TigerLoadRunbookGuardTest extends TestCase
{
    private function script(): string
    {
        return base_path('spikes/phase-2-batch-2d-part-c3b-tiger-boundary-import/bin/load_florida_counties.sh');
    }

    /**
     * @param list<string> $args
     * @param array<string,string> $env
     * @return array{code:int,out:string,err:string}
     */
    private function runScript(array $args, array $env): array
    {
        $cmd = array_merge(['bash', $this->script()], $args);
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        // Explicit env: only what we pass (plus PATH for bash) — SPATIAL_DATABASE_URL is absent unless set here.
        $env = array_merge(['PATH' => (string) getenv('PATH')], $env);
        $proc = proc_open($cmd, $descriptors, $pipes, null, $env);
        $this->assertIsResource($proc);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        return ['code' => $code, 'out' => $out, 'err' => $err];
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
        // Live flag present, non-production, corpus_version supplied, but SPATIAL_DATABASE_URL unset.
        $r = $this->runScript(['--i-understand-live', '--corpus-version=tiger-test'], ['APP_ENV' => 'local']);
        $this->assertNotSame(0, $r['code']);
        $this->assertStringContainsString('SPATIAL_DATABASE_URL', $r['err']);
    }

    /** @test */
    public function it_never_echoes_the_secret(): void
    {
        $src = (string) file_get_contents($this->script());
        // The secret is only ever handed to psql, never printed.
        $this->assertDoesNotMatchRegularExpression('/echo[^\n]*SPATIAL_DATABASE_URL/', $src);
        $this->assertDoesNotMatchRegularExpression('/printf[^\n]*SPATIAL_DATABASE_URL/', $src);
    }

    /** @test */
    public function it_references_only_the_committed_boundary_recipes(): void
    {
        $src = (string) file_get_contents($this->script());
        foreach ([
            'stage_boundaries.sql',
            'load_tiger_boundaries.sql',
            'ledger_insert.sql',
            'ledger_activate.sql',
            'verify_boundaries.sql',
        ] as $sql) {
            $this->assertStringContainsString($sql, $src, "orchestrator must drive {$sql}");
        }
        // Every -f target is a .sql (no stray recipe).
        preg_match_all('/-f\s+"?\$\{?SQL_DIR\}?\/([^"\s]+)/', $src, $m);
        foreach ($m[1] as $file) {
            $this->assertStringEndsWith('.sql', $file, "unexpected -f target: {$file}");
        }
    }

    /** @test */
    public function it_carries_no_download_other_slice_or_worktree_logic(): void
    {
        $src = strtolower((string) file_get_contents($this->script()));
        foreach (['curl', 'wget', 'overture', 'gate2', 'gate-2', '/home/runner/worktrees', 'hi-05', 'timed-'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $src, "orchestrator must not contain '{$forbidden}'");
        }
    }
}
