<?php

namespace Tests\Feature;

use App\Http\Livewire\Buyer\BuyerAgentAuctionBid;
use App\Http\Livewire\Landlord\LandlordAgentAuctionBid;
use App\Http\Livewire\Seller\SellerAgentAuctionBid;
use App\Http\Livewire\Tenant\TenantAgentAuctionBid;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionBid as BuyerAgentAuctionBidData;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionBid as LandlordAgentAuctionBidData;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionBid as SellerAgentAuctionBidData;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionBid as TenantAgentAuctionBidData;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * BYA-H2 (Rule B1): the owner of a hire-agent listing must not be able to submit
 * an agent bid on their own listing. The guard lives at the top of each role's
 * *AgentAuctionBid::submit() (and the live legacy bid controllers). These tests
 * drive the primary Livewire submit path as the listing owner and assert no bid
 * is created.
 */
class ByaSelfBidGuardTest extends TestCase
{
    use DatabaseTransactions;

    private function owner(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    public function test_buyer_listing_owner_cannot_self_bid(): void
    {
        $owner   = $this->owner();
        $auction = BuyerAgentAuction::create([
            'user_id' => $owner->id, 'title' => 'Buyer hire-agent listing',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);

        Livewire::actingAs($owner)
            ->test(BuyerAgentAuctionBid::class, ['auctionId' => $auction->id])
            ->call('submit')
            ->assertRedirect(route('buyer.view-auction', $auction->id));

        $this->assertSame(0, BuyerAgentAuctionBidData::where('buyer_agent_auction_id', $auction->id)
            ->where('user_id', $owner->id)->count());
    }

    public function test_seller_listing_owner_cannot_self_bid(): void
    {
        $owner   = $this->owner();
        $auction = SellerAgentAuction::create([
            'user_id' => $owner->id, 'title' => 'Seller hire-agent listing',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);

        Livewire::actingAs($owner)
            ->test(SellerAgentAuctionBid::class, ['auctionId' => $auction->id])
            ->call('submit')
            ->assertRedirect(route('seller.agent.auction.detail', $auction->id));

        $this->assertSame(0, SellerAgentAuctionBidData::where('seller_agent_auction_id', $auction->id)
            ->where('user_id', $owner->id)->count());
    }

    public function test_landlord_listing_owner_cannot_self_bid(): void
    {
        $owner   = $this->owner();
        $auction = LandlordAgentAuction::create([
            'user_id' => $owner->id, 'title' => 'Landlord hire-agent listing',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);

        Livewire::actingAs($owner)
            ->test(LandlordAgentAuctionBid::class, ['auctionId' => $auction->id])
            ->call('submit')
            ->assertRedirect(route('landlord.agent.auction.view', $auction->id));

        $this->assertSame(0, LandlordAgentAuctionBidData::where('landlord_agent_auction_id', $auction->id)
            ->where('user_id', $owner->id)->count());
    }

    public function test_tenant_listing_owner_cannot_self_bid(): void
    {
        $owner   = $this->owner();
        $auction = TenantAgentAuction::factory()->active()->create(['user_id' => $owner->id]);

        Livewire::actingAs($owner)
            ->test(TenantAgentAuctionBid::class, ['auctionId' => $auction->id])
            ->call('submit')
            ->assertRedirect(route('tenant.agent.auction.view', $auction->id));

        $this->assertSame(0, TenantAgentAuctionBidData::where('tenant_agent_auction_id', $auction->id)
            ->where('user_id', $owner->id)->count());
    }

    public function test_non_owner_agent_is_not_blocked_by_the_self_bid_guard(): void
    {
        // A different user (agent) must pass the self-bid guard (the guard is the
        // first statement in submit(); validation/other guards may still apply, but
        // the self-bid redirect must NOT fire for a non-owner).
        $owner   = $this->owner();
        $agent   = User::factory()->create(['user_type' => 'buyer_agent']);
        $auction = TenantAgentAuction::factory()->active()->create(['user_id' => $owner->id]);

        Livewire::actingAs($agent)
            ->test(TenantAgentAuctionBid::class, ['auctionId' => $auction->id])
            ->call('submit')
            ->assertNoRedirect();
    }
}
