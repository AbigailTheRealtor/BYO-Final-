<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ComputeDnaScores;
use App\Jobs\ComputeLocationDna;
use App\Services\Canonical\CanonicalListingResolver;
use App\Services\LocationDna\LocationDnaPipelineRunner;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

/**
 * Phase 13 — ComputeLocationDna chains ComputeDnaScores after a SUCCESSFUL
 * Location DNA run, but only when the master flag is on and the listing type is
 * supported. Pure unit: runner + resolver are mocked, no DB.
 */
class ComputeLocationDnaChainTest extends TestCase
{
    private function runner(string $status): LocationDnaPipelineRunner
    {
        $runner = Mockery::mock(LocationDnaPipelineRunner::class);
        $runner->shouldReceive('run')->once()->andReturn(['status' => $status]);
        return $runner;
    }

    private function resolver(bool $supports): CanonicalListingResolver
    {
        $resolver = Mockery::mock(CanonicalListingResolver::class);
        $resolver->shouldReceive('supports')->andReturn($supports);
        return $resolver;
    }

    public function test_chains_generation_on_success_when_enabled_and_supported(): void
    {
        config(['dna_scores.generation_enabled' => true]);
        Bus::fake();

        (new ComputeLocationDna('seller_agent', 55))
            ->handle($this->runner('success'), $this->resolver(true));

        Bus::assertDispatched(ComputeDnaScores::class, function (ComputeDnaScores $job) {
            return $job->listingType === 'seller_agent' && $job->listingId === 55;
        });
    }

    public function test_does_not_chain_when_generation_disabled(): void
    {
        config(['dna_scores.generation_enabled' => false]);
        Bus::fake();

        (new ComputeLocationDna('seller_agent', 55))
            ->handle($this->runner('success'), $this->resolver(true));

        Bus::assertNotDispatched(ComputeDnaScores::class);
    }

    public function test_does_not_chain_on_non_success(): void
    {
        config(['dna_scores.generation_enabled' => true]);
        Bus::fake();

        (new ComputeLocationDna('seller_agent', 55))
            ->handle($this->runner('skipped'), $this->resolver(true));

        Bus::assertNotDispatched(ComputeDnaScores::class);
    }

    public function test_does_not_chain_for_unsupported_type(): void
    {
        config(['dna_scores.generation_enabled' => true]);
        Bus::fake();

        (new ComputeLocationDna('seller', 55))
            ->handle($this->runner('success'), $this->resolver(false));

        Bus::assertNotDispatched(ComputeDnaScores::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
