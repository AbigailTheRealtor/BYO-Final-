<?php

namespace Tests\Unit\Observers;

use App\Jobs\ComputeDnaScores;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Observers\Dna\SellerAgentAuctionDnaScoreObserver;
use App\Observers\Dna\TenantAgentAuctionDnaScoreObserver;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Phase 13 — the *_agent DNA-score observers. Pure unit tests: the observer's
 * saved() hook is invoked directly on an in-memory model (no persistence), so
 * we test exactly the dispatch decision without touching the database.
 *
 * They dispatch ComputeDnaScores ONLY when the master flag is on, with the
 * correct listing type, and never throw out of the save path.
 */
class AgentAuctionDnaScoreObserverTest extends TestCase
{
    private function seller(int $id): SellerAgentAuction
    {
        $m = new SellerAgentAuction();
        $m->id = $id;
        return $m;
    }

    private function tenant(int $id): TenantAgentAuction
    {
        $m = new TenantAgentAuction();
        $m->id = $id;
        return $m;
    }

    public function test_no_dispatch_when_generation_disabled(): void
    {
        config(['dna_scores.generation_enabled' => false]);
        Bus::fake();

        (new SellerAgentAuctionDnaScoreObserver())->saved($this->seller(101));

        Bus::assertNotDispatched(ComputeDnaScores::class);
    }

    public function test_seller_save_dispatches_property_side_generation(): void
    {
        config(['dna_scores.generation_enabled' => true]);
        Bus::fake();

        (new SellerAgentAuctionDnaScoreObserver())->saved($this->seller(202));

        Bus::assertDispatched(ComputeDnaScores::class, function (ComputeDnaScores $job) {
            return $job->listingType === 'seller_agent'
                && $job->listingId === 202
                && $job->generatedBy === 'system';
        });
    }

    public function test_tenant_save_dispatches_demand_side_generation(): void
    {
        // Also confirms observing the (frozen) TenantAgentAuction is additive.
        config(['dna_scores.generation_enabled' => true]);
        Bus::fake();

        (new TenantAgentAuctionDnaScoreObserver())->saved($this->tenant(303));

        Bus::assertDispatched(ComputeDnaScores::class, function (ComputeDnaScores $job) {
            return $job->listingType === 'tenant_agent' && $job->listingId === 303;
        });
    }
}
