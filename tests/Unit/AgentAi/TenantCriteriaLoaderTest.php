<?php

namespace Tests\Unit\AgentAi;

use App\Enums\AgentAiContextScope;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Services\AgentAi\Loaders\TenantCriteriaLoader;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * TenantCriteriaLoaderTest
 *
 * Verifies:
 *   (a) Only returns data for the requested tenant criteria listing.
 *   (b) Never includes private columns (user_id, referring_agent_id) or bid data.
 *   (c) Token estimate does not exceed the tenant_criteria scope budget (1,500 tokens).
 */
class TenantCriteriaLoaderTest extends TestCase
{
    use DatabaseTransactions;

    private TenantCriteriaLoader $loader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loader = new TenantCriteriaLoader();
    }

    private function makeScopeContext(int $listingId): array
    {
        return [
            'scope'        => AgentAiContextScope::TenantCriteria,
            'agent_id'     => 1,
            'listing_type' => 'tenant',
            'listing_id'   => $listingId,
        ];
    }

    public function test_returns_null_when_listing_id_is_zero(): void
    {
        $result = ($this->loader)($this->makeScopeContext(0));
        $this->assertNull($result);
    }

    public function test_returns_null_when_listing_does_not_exist(): void
    {
        $result = ($this->loader)($this->makeScopeContext(999999));
        $this->assertNull($result);
    }

    public function test_returns_fragment_for_valid_listing(): void
    {
        $listing = TenantAgentAuction::factory()->create([
            'is_approved' => true,
            'is_draft'    => false,
            'is_sold'     => false,
        ]);

        $result = ($this->loader)($this->makeScopeContext($listing->id));

        $this->assertNotNull($result);
        $this->assertEquals(TenantCriteriaLoader::SOURCE_KEY, $result['source_key']);
        $this->assertEquals(TenantCriteriaLoader::PRIORITY, $result['priority']);
        $this->assertArrayHasKey('content', $result);
    }

    public function test_only_returns_data_for_requested_listing(): void
    {
        $listingA = TenantAgentAuction::factory()->create(['title' => 'Tenant A', 'is_approved' => true, 'is_draft' => false, 'is_sold' => false]);
        $listingB = TenantAgentAuction::factory()->create(['title' => 'Tenant B', 'is_approved' => true, 'is_draft' => false, 'is_sold' => false]);

        $resultA = ($this->loader)($this->makeScopeContext($listingA->id));
        $resultB = ($this->loader)($this->makeScopeContext($listingB->id));

        $this->assertNotNull($resultA);
        $this->assertNotNull($resultB);

        $this->assertEquals($listingA->id, $resultA['content']['listing_id'] ?? null);
        $this->assertEquals($listingB->id, $resultB['content']['listing_id'] ?? null);
    }

    public function test_never_includes_private_columns(): void
    {
        $listing = TenantAgentAuction::factory()->create(['is_approved' => true, 'is_draft' => false, 'is_sold' => false]);

        $result = ($this->loader)($this->makeScopeContext($listing->id));

        $this->assertNotNull($result);
        $content = $result['content'];

        $privateColumns = ['user_id', 'referring_agent_id'];
        foreach ($privateColumns as $col) {
            $this->assertArrayNotHasKey($col, $content,
                "Private column '{$col}' must not appear in tenant criteria fragment.");
        }
    }

    public function test_never_includes_offer_or_bid_data(): void
    {
        $listing = TenantAgentAuction::factory()->create(['is_approved' => true, 'is_draft' => false, 'is_sold' => false]);

        $result = ($this->loader)($this->makeScopeContext($listing->id));

        $this->assertNotNull($result);
        $content = $result['content'];

        $bidKeys = ['bids', 'bid_amount', 'accepted_bid', 'counteroffer', 'agent_compensation', 'competing_bids'];
        foreach ($bidKeys as $key) {
            $this->assertArrayNotHasKey($key, $content,
                "Bid/offer field '{$key}' must never appear in tenant criteria context.");
        }
    }

    public function test_token_estimate_within_tenant_scope_budget(): void
    {
        $listing = TenantAgentAuction::factory()->create([
            'is_approved' => true,
            'is_draft'    => false,
            'is_sold'     => false,
        ]);

        $listing->saveMeta('budget', '1800');
        $listing->saveMeta('bedrooms', '2');
        $listing->saveMeta('bathrooms', '1');
        $listing->saveMeta('desired_lease_length', json_encode(['12 months', '6 months']));
        $listing->saveMeta('credit_score_range', '700-749');
        $listing->saveMeta('monthly_income', '6000');
        $listing->saveMeta('pet_information', 'One small dog');

        $result = ($this->loader)($this->makeScopeContext($listing->id));

        $this->assertNotNull($result);
        $this->assertLessThanOrEqual(1500, $result['token_estimate'],
            "TenantCriteriaLoader token_estimate must not exceed 1,500 tokens.");
    }

    public function test_fragment_contains_listing_type_tenant(): void
    {
        $listing = TenantAgentAuction::factory()->create(['is_approved' => true, 'is_draft' => false, 'is_sold' => false]);

        $result = ($this->loader)($this->makeScopeContext($listing->id));

        $this->assertNotNull($result);
        $this->assertEquals('tenant', $result['content']['listing_type'] ?? null);
    }
}
