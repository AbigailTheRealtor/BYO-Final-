<?php

namespace Tests\Feature\Spatial;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Phase 2 Batch 2D Part B — spatial:gate1-validate command tests.
 *
 * The command is an OFFLINE authoring tool: it runs the Hybrid Gate 1 harness over the synthetic
 * benchmark and refuses production. No DB, no network, no corpus.
 *
 * Output is asserted via Artisan::output() (this app runs Laravel 8.83, which has no
 * expectsOutputToContain()).
 */
class Gate1ValidateCommandTest extends TestCase
{
    /** @test */
    public function it_passes_over_the_synthetic_benchmark_and_exits_success(): void
    {
        $exit = Artisan::call('spatial:gate1-validate');
        $output = Artisan::output();

        $this->assertSame(0, $exit, 'The command must succeed on the clean synthetic benchmark.');
        $this->assertStringContainsString('Hybrid Gate 1', $output);
        $this->assertStringContainsString('embarrassments  : 0', $output);
        $this->assertStringContainsString('PASS', $output);
    }

    /** @test */
    public function it_refuses_to_run_in_production(): void
    {
        // app()->environment() reads the container's `env` binding, not config('app.env').
        $this->app['env'] = 'production';

        $exit = Artisan::call('spatial:gate1-validate');
        $output = Artisan::output();

        $this->assertSame(1, $exit, 'The command must refuse to run in production.');
        $this->assertStringContainsString('REFUSES to run in production', $output);
    }

    /** @test */
    public function a_zero_threshold_still_passes_the_clean_benchmark(): void
    {
        // The synthetic set has zero embarrassments, so even an exact-zero gate passes.
        $exit = Artisan::call('spatial:gate1-validate', ['--threshold' => '0']);

        $this->assertSame(0, $exit);
    }
}
