<?php

namespace Tests\Feature\Spatial;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Batch 2D Part C3d-a — spatial:gate2-matrix drives the Gate 2 evidence schema offline: refuses
 * production, opens no DB/PostGIS, makes no network call, computes no coverage metric and no
 * pass/fail, and authors deterministic matrix.json / summary.json that byte-match the committed
 * reference artifacts.
 *
 * Output is asserted via Artisan::output() (this app runs Laravel 8.83, which has no
 * expectsOutputToContain()).
 */
class Gate2CoverageMatrixCommandTest extends TestCase
{
    private function outDir(): string
    {
        return storage_path('app/spatial/gate2-test');
    }

    /** @test */
    public function it_assembles_the_evidence_schema_and_exits_success(): void
    {
        $exit = Artisan::call('spatial:gate2-matrix', ['--out-dir' => $this->outDir()]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('EVIDENCE SCHEMA', $output);
        $this->assertStringContainsString('territories  : FL, PR, AK, rural_CONUS', $output);
        // Exit 0 is explicitly NOT a Gate 2 pass.
        $this->assertStringContainsString('This is NOT a Gate 2 pass', $output);
        $this->assertStringContainsString('NO automated pass/fail', $output);
    }

    /** @test */
    public function the_authored_matrix_and_summary_byte_match_the_committed_reference(): void
    {
        Artisan::call('spatial:gate2-matrix', ['--out-dir' => $this->outDir()]);

        foreach (['matrix.json', 'summary.json'] as $artifact) {
            $actual = (string) file_get_contents($this->outDir() . '/' . $artifact);
            $expected = (string) file_get_contents(base_path("tests/Fixtures/Spatial/Gate2/reference/{$artifact}"));
            $this->assertSame($expected, $actual, "authored {$artifact} must byte-match the committed reference");
        }
    }

    /** @test */
    public function re_running_is_byte_identical_so_the_artifacts_are_deterministic(): void
    {
        Artisan::call('spatial:gate2-matrix', ['--out-dir' => $this->outDir() . '/a']);
        Artisan::call('spatial:gate2-matrix', ['--out-dir' => $this->outDir() . '/b']);

        foreach (['matrix.json', 'summary.json'] as $artifact) {
            $this->assertSame(
                (string) file_get_contents($this->outDir() . "/a/{$artifact}"),
                (string) file_get_contents($this->outDir() . "/b/{$artifact}"),
                "{$artifact} must be deterministic across runs",
            );
        }
    }

    /** @test */
    public function the_summary_defers_acceptance_and_carries_no_pass_fail(): void
    {
        Artisan::call('spatial:gate2-matrix', ['--out-dir' => $this->outDir()]);

        $summary = json_decode((string) file_get_contents($this->outDir() . '/summary.json'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayNotHasKey('passed', $summary);
        $this->assertFalse($summary['acceptance']['coverage_metric_defined']);
        $this->assertFalse($summary['acceptance']['threshold_defined']);
        $this->assertFalse($summary['acceptance']['automated_pass_fail']);
        $this->assertStringContainsString('deferred to C3d-b', $summary['acceptance']['product_owner_acceptance']);
    }

    /** @test */
    public function the_four_pr_watch_datasets_are_verified_and_unmeasured_is_not_zero(): void
    {
        Artisan::call('spatial:gate2-matrix', ['--out-dir' => $this->outDir()]);

        $summary = json_decode((string) file_get_contents($this->outDir() . '/summary.json'), true, 512, JSON_THROW_ON_ERROR);
        $watch = $summary['pr_watch_verification'];

        foreach (['epa_walkability', 'usgs_boat_ramps', 'noaa_cusp', 'dot_nad'] as $dataset) {
            $this->assertArrayHasKey($dataset, $watch, "PR watch dataset {$dataset} must be verified");
        }

        // NOAA CUSP for PR is unmeasured (unknown), never reported as a measured zero.
        $this->assertSame('unmeasured', $watch['noaa_cusp'][0]['status']);
        $this->assertNull($watch['noaa_cusp'][0]['present_count']);
        $this->assertSame('absent', $watch['epa_walkability'][0]['status']);
        $this->assertSame(0, $watch['epa_walkability'][0]['present_count']);
    }

    /** @test */
    public function it_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $exit = Artisan::call('spatial:gate2-matrix', ['--out-dir' => $this->outDir()]);
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('REFUSES to run in production', $output);
    }
}
