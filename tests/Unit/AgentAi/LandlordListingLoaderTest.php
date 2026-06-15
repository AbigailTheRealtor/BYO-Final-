<?php

namespace Tests\Unit\AgentAi;

use App\Enums\AgentAiContextScope;
use App\Models\LandlordAgentAuction;
use App\Models\User;
use App\Services\AgentAi\Loaders\LandlordListingLoader;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LandlordListingLoaderTest
 *
 * Verifies:
 *   (a) Only returns data for the requested listing.
 *   (b) Never includes bid, offer, or counteroffer data.
 *   (c) Token estimate does not exceed the landlord listing scope budget (3,000 tokens).
 *   (d) Private columns (user_id, referring_agent_id) are excluded.
 */
class LandlordListingLoaderTest extends TestCase
{
    use DatabaseTransactions;

    private LandlordListingLoader $loader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loader = new LandlordListingLoader();
    }

    private function makeScopeContext(int $listingId): array
    {
        return [
            'scope'        => AgentAiContextScope::PublicListingLandlord,
            'agent_id'     => 1,
            'listing_type' => 'landlord',
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
        $listing = LandlordAgentAuction::create([
            'user_id'  => $user->id,
            'title'    => 'Nice Apartment',
            'is_approved' => true,
            'is_draft'    => false,
            'is_sold'     => false,
        ]);

        $result = ($this->loader)($this->makeScopeContext($listing->id));

        $this->assertNotNull($result);
        $this->assertEquals(LandlordListingLoader::SOURCE_KEY, $result['source_key']);
        $this->assertEquals(LandlordListingLoader::PRIORITY, $result['priority']);
        $this->assertArrayHasKey('content', $result);
    }

    public function test_only_returns_data_for_requested_listing(): void
    {
        $user     = User::factory()->create();
        $listingA = LandlordAgentAuction::create(['user_id' => $user->id, 'title' => 'Unit A', 'is_approved' => true, 'is_draft' => false, 'is_sold' => false]);
        $listingB = LandlordAgentAuction::create(['user_id' => $user->id, 'title' => 'Unit B', 'is_approved' => true, 'is_draft' => false, 'is_sold' => false]);

        $resultA = ($this->loader)($this->makeScopeContext($listingA->id));
        $resultB = ($this->loader)($this->makeScopeContext($listingB->id));

        $this->assertNotNull($resultA);
        $this->assertNotNull($resultB);

        $this->assertEquals($listingA->id, $resultA['content']['listing_id'] ?? null);
        $this->assertEquals($listingB->id, $resultB['content']['listing_id'] ?? null);
    }

    public function test_never_includes_private_columns(): void
    {
        $user    = User::factory()->create();
        $listing = LandlordAgentAuction::create([
            'user_id'  => $user->id,
            'is_approved' => true,
            'is_draft'    => false,
            'is_sold'     => false,
        ]);

        $result = ($this->loader)($this->makeScopeContext($listing->id));

        $this->assertNotNull($result);
        $content = $result['content'];

        $privateColumns = ['user_id', 'referring_agent_id', 'referral_source_code', 'referral_captured_at', 'referral_locked'];
        foreach ($privateColumns as $col) {
            $this->assertArrayNotHasKey($col, $content,
                "Private column '{$col}' must not appear in landlord listing fragment.");
        }
    }

    public function test_never_includes_offer_or_bid_data(): void
    {
        $user    = User::factory()->create();
        $listing = LandlordAgentAuction::create(['user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'is_sold' => false]);

        $result = ($this->loader)($this->makeScopeContext($listing->id));

        $this->assertNotNull($result);
        $content = $result['content'];

        $bidKeys = ['bids', 'bid_amount', 'accepted_bid', 'counteroffer', 'agent_compensation', 'competing_bids'];
        foreach ($bidKeys as $key) {
            $this->assertArrayNotHasKey($key, $content,
                "Bid/offer field '{$key}' must never appear in landlord listing context.");
        }
    }

    public function test_token_estimate_within_landlord_scope_budget(): void
    {
        $user    = User::factory()->create();
        $listing = LandlordAgentAuction::create([
            'user_id'  => $user->id,
            'is_approved' => true,
            'is_draft'    => false,
            'is_sold'     => false,
        ]);

        $listing->saveMeta('desired_rental_amount', '2500');
        $listing->saveMeta('bedrooms', '3');
        $listing->saveMeta('bathrooms', '2');
        $listing->saveMeta('minimum_heated_square', '1400');
        $listing->saveMeta('available_date', '2026-07-01');
        $listing->saveMeta('pet_policy', 'Cats allowed');
        $listing->saveMeta('lease_length', '12 months');
        $listing->saveMeta('has_hoa', 'No');

        $result = ($this->loader)($this->makeScopeContext($listing->id));

        $this->assertNotNull($result);
        $this->assertLessThanOrEqual(3000, $result['token_estimate'],
            "LandlordListingLoader token_estimate must not exceed 3,000 tokens.");
    }

    public function test_fragment_contains_listing_type_landlord(): void
    {
        $user    = User::factory()->create();
        $listing = LandlordAgentAuction::create(['user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'is_sold' => false]);

        $result = ($this->loader)($this->makeScopeContext($listing->id));

        $this->assertNotNull($result);
        $this->assertEquals('landlord', $result['content']['listing_type'] ?? null);
    }
}
