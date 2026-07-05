<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ComputeDnaScores;
use App\Services\Dna\Scores\DnaScoreGenerationService;
use Mockery;
use Tests\TestCase;

/**
 * Phase 13 — ComputeDnaScores delegates entirely to DnaScoreGenerationService,
 * forwarding the origin tag. No business logic lives in the job.
 */
class ComputeDnaScoresTest extends TestCase
{
    public function test_handle_delegates_to_service_with_origin(): void
    {
        $service = Mockery::mock(DnaScoreGenerationService::class);
        $service->shouldReceive('generateForListing')
            ->once()
            ->with('landlord_agent', 42, ['generated_by' => 'imported'])
            ->andReturn([]);

        (new ComputeDnaScores('landlord_agent', 42, 'imported'))->handle($service);

        // Mockery expectation asserts the delegation.
        $this->assertTrue(true);
    }

    public function test_defaults_origin_to_system(): void
    {
        $service = Mockery::mock(DnaScoreGenerationService::class);
        $service->shouldReceive('generateForListing')
            ->once()
            ->with('seller_agent', 7, ['generated_by' => 'system'])
            ->andReturn([]);

        (new ComputeDnaScores('seller_agent', 7))->handle($service);

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
