<?php

namespace Tests\Unit\AgentAi;

use App\Enums\AgentAiContextScope;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\AgentAi\Loaders\SellerListingLoader;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * SellerListingLoaderTest
 *
 * Verifies:
 *   (a) Only returns data for the requested listing (agent isolation).
 *   (b) Never includes bid, offer, counteroffer, or competing-agent data.
 *   (c) Token estimate does not exceed the seller listing scope budget (3,000 tokens).
 *   (d) Private native columns (user_id, max_commission, etc.) are excluded.
 */
class SellerListingLoaderTest extends TestCase
{
    use DatabaseTransactions;

    private SellerListingLoader $loader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loader = new SellerListingLoader();
    }

    private function makeScopeContext(int $listingId): array
    {
        return [
            'scope'        => AgentAiContextScope::PublicListingSeller,
            'agent_id'     => 1,
            'listing_type' => 'seller',
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
        $listing = SellerAgentAuction::create([
            'user_id'     => $user->id,
            'address'     => '123 Main St',
            'description' => 'Beautiful home.',
            'is_approved' => true,
            'is_draft'    => false,
            'is_sold'     => false,
        ]);

        $result = ($this->loader)($this->makeScopeContext($listing->id));

        $this->assertNotNull($result);
        $this->assertEquals(SellerListingLoader::SOURCE_KEY, $result['source_key']);
        $this->assertEquals(SellerListingLoader::PRIORITY, $result['priority']);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('token_estimate', $result);
    }

    public function test_only_returns_data_for_requested_listing(): void
    {
        $user     = User::factory()->create();
        $listingA = SellerAgentAuction::create(['user_id' => $user->id, 'address' => '100 Oak Ave', 'is_approved' => true, 'is_draft' => false, 'is_sold' => false]);
        $listingB = SellerAgentAuction::create(['user_id' => $user->id, 'address' => '200 Elm St', 'is_approved' => true, 'is_draft' => false, 'is_sold' => false]);

        $resultA = ($this->loader)($this->makeScopeContext($listingA->id));
        $resultB = ($this->loader)($this->makeScopeContext($listingB->id));

        $this->assertNotNull($resultA);
        $this->assertNotNull($resultB);

        $addressA = $resultA['content']['address'] ?? null;
        $addressB = $resultB['content']['address'] ?? null;

        if ($addressA) {
            $this->assertEquals('100 Oak Ave', $addressA);
        }
        if ($addressB) {
            $this->assertEquals('200 Elm St', $addressB);
        }

        $this->assertEquals($listingA->id, $resultA['content']['listing_id'] ?? null);
        $this->assertEquals($listingB->id, $resultB['content']['listing_id'] ?? null);
    }

    public function test_never_includes_private_native_columns(): void
    {
        $user    = User::factory()->create();
        $listing = SellerAgentAuction::create([
            'user_id'        => $user->id,
            'address'        => '999 Private Rd',
            'max_commission' => 5.0,
            'is_paid'        => true,
            'is_approved'    => true,
            'is_draft'       => false,
            'is_sold'        => false,
        ]);

        $result = ($this->loader)($this->makeScopeContext($listing->id));

        $this->assertNotNull($result);
        $content = $result['content'];

        $privateColumns = ['user_id', 'max_commission', 'is_paid', 'referring_agent_id', 'referral_source_code'];
        foreach ($privateColumns as $col) {
            $this->assertArrayNotHasKey($col, $content,
                "Private column '{$col}' must not appear in seller listing fragment.");
        }
    }

    public function test_never_includes_offer_or_bid_data(): void
    {
        $user    = User::factory()->create();
        $listing = SellerAgentAuction::create([
            'user_id'     => $user->id,
            'address'     => '456 Test Dr',
            'is_approved' => true,
            'is_draft'    => false,
            'is_sold'     => false,
        ]);

        $result = ($this->loader)($this->makeScopeContext($listing->id));

        $this->assertNotNull($result);
        $content = $result['content'];

        $bidRelatedKeys = [
            'bids', 'bid_amount', 'offers', 'offer_price', 'counteroffer',
            'accepted_bid', 'accepted_bid_summary', 'commission_rate',
            'agent_compensation', 'competing_bids',
        ];

        foreach ($bidRelatedKeys as $key) {
            $this->assertArrayNotHasKey($key, $content,
                "Bid/offer field '{$key}' must never appear in seller listing context.");
        }
    }

    public function test_token_estimate_within_seller_scope_budget(): void
    {
        $user    = User::factory()->create();
        $listing = SellerAgentAuction::create([
            'user_id'     => $user->id,
            'address'     => '1 Full St',
            'description' => str_repeat('Great home. ', 50),
            'is_approved' => true,
            'is_draft'    => false,
            'is_sold'     => false,
        ]);

        $listing->saveMeta('maximum_budget', '550000');
        $listing->saveMeta('bedrooms', '4');
        $listing->saveMeta('bathrooms', '3');
        $listing->saveMeta('minimum_heated_square', '2200');
        $listing->saveMeta('year_built', '2005');
        $listing->saveMeta('pool_needed', 'Yes');
        $listing->saveMeta('has_hoa', 'Yes');
        $listing->saveMeta('association_fee_amount', '250');
        $listing->saveMeta('flood_zone_code', 'X');

        $result = ($this->loader)($this->makeScopeContext($listing->id));

        $this->assertNotNull($result);
        $this->assertLessThanOrEqual(3000, $result['token_estimate'],
            "SellerListingLoader token_estimate must not exceed the seller scope budget of 3,000 tokens.");
    }

    public function test_fragment_contains_listing_type_seller(): void
    {
        $user    = User::factory()->create();
        $listing = SellerAgentAuction::create(['user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'is_sold' => false]);

        $result = ($this->loader)($this->makeScopeContext($listing->id));

        $this->assertNotNull($result);
        $this->assertEquals('seller', $result['content']['listing_type'] ?? null);
    }
}
