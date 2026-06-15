<?php

namespace Tests\Unit\AgentAi;

use App\Enums\AgentAiContextScope;
use App\Models\BuyerAgentAuction;
use App\Models\User;
use App\Services\AgentAi\Loaders\BuyerCriteriaLoader;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * BuyerCriteriaLoaderTest
 *
 * Verifies:
 *   (a) Only returns data for the requested buyer criteria listing.
 *   (b) Never includes private financial data (preapproval_amount, cash_budget, etc.)
 *       or bid/offer data.
 *   (c) Token estimate does not exceed the buyer_criteria scope budget (1,500 tokens).
 */
class BuyerCriteriaLoaderTest extends TestCase
{
    use DatabaseTransactions;

    private BuyerCriteriaLoader $loader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loader = new BuyerCriteriaLoader();
    }

    private function makeScopeContext(int $listingId): array
    {
        return [
            'scope'        => AgentAiContextScope::BuyerCriteria,
            'agent_id'     => 1,
            'listing_type' => 'buyer',
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
        $user    = User::factory()->create();
        $listing = BuyerAgentAuction::create([
            'user_id'     => $user->id,
            'title'       => 'Looking for 3BR',
            'is_approved' => true,
            'is_draft'    => false,
            'is_sold'     => false,
        ]);

        $result = ($this->loader)($this->makeScopeContext($listing->id));

        $this->assertNotNull($result);
        $this->assertEquals(BuyerCriteriaLoader::SOURCE_KEY, $result['source_key']);
        $this->assertEquals(BuyerCriteriaLoader::PRIORITY, $result['priority']);
        $this->assertArrayHasKey('content', $result);
    }

    public function test_only_returns_data_for_requested_listing(): void
    {
        $user     = User::factory()->create();
        $listingA = BuyerAgentAuction::create(['user_id' => $user->id, 'title' => 'Buyer A', 'address' => '10 Buyer A Ln', 'is_approved' => true, 'is_draft' => false, 'is_sold' => false]);
        $listingB = BuyerAgentAuction::create(['user_id' => $user->id, 'title' => 'Buyer B', 'address' => '20 Buyer B Ln', 'is_approved' => true, 'is_draft' => false, 'is_sold' => false]);

        $resultA = ($this->loader)($this->makeScopeContext($listingA->id));
        $resultB = ($this->loader)($this->makeScopeContext($listingB->id));

        $this->assertEquals($listingA->id, $resultA['content']['listing_id'] ?? null);
        $this->assertEquals($listingB->id, $resultB['content']['listing_id'] ?? null);
    }

    public function test_never_includes_private_financial_fields(): void
    {
        $user    = User::factory()->create();
        $listing = BuyerAgentAuction::create([
            'user_id'            => $user->id,
            'title'              => 'Test Private Fields',
            'concession'         => 3.0,
            'preapproval_amount' => 450000,
            'cash_budget'        => '200000',
            'crypto_budget'      => '50000',
            'is_approved'        => true,
            'is_draft'           => false,
            'is_sold'            => false,
        ]);

        $result = ($this->loader)($this->makeScopeContext($listing->id));

        $this->assertNotNull($result);
        $content = $result['content'];

        $privateColumns = ['user_id', 'concession', 'preapproval_amount', 'cash_budget', 'crypto_budget', 'is_paid', 'referring_agent_id'];
        foreach ($privateColumns as $col) {
            $this->assertArrayNotHasKey($col, $content,
                "Private column '{$col}' must not appear in buyer criteria fragment.");
        }
    }

    public function test_never_includes_offer_or_bid_data(): void
    {
        $user    = User::factory()->create();
        $listing = BuyerAgentAuction::create(['user_id' => $user->id, 'title' => 'Test Bid Keys', 'is_approved' => true, 'is_draft' => false, 'is_sold' => false]);

        $result = ($this->loader)($this->makeScopeContext($listing->id));

        $this->assertNotNull($result);
        $content = $result['content'];

        $bidKeys = ['bids', 'bid_amount', 'accepted_bid', 'counteroffer', 'agent_compensation', 'competing_bids'];
        foreach ($bidKeys as $key) {
            $this->assertArrayNotHasKey($key, $content,
                "Bid/offer field '{$key}' must never appear in buyer criteria context.");
        }
    }

    public function test_token_estimate_within_buyer_scope_budget(): void
    {
        $user    = User::factory()->create();
        $listing = BuyerAgentAuction::create([
            'user_id'            => $user->id,
            'title'              => 'Test Token Budget',
            'additional_details' => str_repeat('Looking for a great home. ', 20),
            'is_approved'        => true,
            'is_draft'           => false,
            'is_sold'            => false,
        ]);

        $listing->saveMeta('maximum_budget', '400000');
        $listing->saveMeta('bedrooms', '3');
        $listing->saveMeta('bathrooms', '2');
        $listing->saveMeta('cities', json_encode(['Tampa', 'St. Petersburg']));
        $listing->saveMeta('financing_type', json_encode(['Conventional', 'FHA']));

        $result = ($this->loader)($this->makeScopeContext($listing->id));

        $this->assertNotNull($result);
        $this->assertLessThanOrEqual(1500, $result['token_estimate'],
            "BuyerCriteriaLoader token_estimate must not exceed 1,500 tokens.");
    }

    public function test_fragment_contains_listing_type_buyer(): void
    {
        $user    = User::factory()->create();
        $listing = BuyerAgentAuction::create(['user_id' => $user->id, 'title' => 'Test Type Label', 'is_approved' => true, 'is_draft' => false, 'is_sold' => false]);

        $result = ($this->loader)($this->makeScopeContext($listing->id));

        $this->assertNotNull($result);
        $this->assertEquals('buyer', $result['content']['listing_type'] ?? null);
    }

    public function test_fragment_contains_listing_url_respond_link(): void
    {
        $user    = User::factory()->create();
        $listing = BuyerAgentAuction::create([
            'user_id'     => $user->id,
            'title'       => 'Respond Link Test',
            'is_approved' => true,
            'is_draft'    => false,
            'is_sold'     => false,
        ]);

        $result = ($this->loader)($this->makeScopeContext($listing->id));

        $this->assertNotNull($result);
        $this->assertArrayHasKey('listing_url', $result['content'],
            'Fragment must include a listing_url (respond link) so agents know where to direct responses.');
        $this->assertStringContainsString((string) $listing->id, $result['content']['listing_url'],
            'listing_url must embed the listing ID.');
        $this->assertStringStartsWith('/', $result['content']['listing_url'],
            'listing_url must be a relative URL path.');
    }
}
