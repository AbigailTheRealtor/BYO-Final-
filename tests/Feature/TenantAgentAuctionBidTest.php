<?php

namespace Tests\Feature;

use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionBid;
use App\Models\TenantCounterBidding;
use App\Models\User;
use App\Notifications\BidAcceptedNotification;
use App\Notifications\BidRejectedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TenantAgentAuctionBidTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        // Register a test-only route for save_bid because the production
        // route is handled via a Livewire component.
        Route::post('/test/tenant/auction/bid/save', [\App\Http\Controllers\TenantAgentAuctionBidController::class, 'save_bid'])
            ->middleware('web');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeTenant(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    private function makeAgent(): User
    {
        return User::factory()->create(['user_type' => 'buyer_agent']);
    }

    private function makeActiveAuction(User $tenant): TenantAgentAuction
    {
        return TenantAgentAuction::factory()->active()->create(['user_id' => $tenant->id]);
    }

    private function makeActiveBid(TenantAgentAuction $auction, User $agent): TenantAgentAuctionBid
    {
        return TenantAgentAuctionBid::factory()->active()->create([
            'tenant_agent_auction_id' => $auction->id,
            'user_id'                 => $agent->id,
        ]);
    }

    // =========================================================================
    // Bid Submission (save_bid)
    // =========================================================================

    public function test_agent_can_submit_a_bid_on_an_active_auction(): void
    {
        $tenant  = $this->makeTenant();
        $agent   = $this->makeAgent();
        $auction = $this->makeActiveAuction($tenant);

        $this->actingAs($agent)->post('/test/tenant/auction/bid/save', [
            'auction_id'  => $auction->id,
            'first_name'  => 'Jane',
            'last_name'   => 'Smith',
            'email'       => $agent->email,
            'phone'       => '555-123-4567',
            'finder_fee'  => '2%',
            'bio'         => 'Experienced tenant agent.',
        ]);

        $bid = TenantAgentAuctionBid::where('tenant_agent_auction_id', $auction->id)
            ->where('user_id', $agent->id)
            ->first();

        $this->assertNotNull($bid, 'Bid should have been created in the database.');
        $this->assertNull($bid->accepted, 'New bid should not be accepted or rejected.');
    }

    public function test_bid_submission_is_blocked_when_auction_is_sold(): void
    {
        $tenant  = $this->makeTenant();
        $agent   = $this->makeAgent();
        $auction = TenantAgentAuction::factory()->sold()->create(['user_id' => $tenant->id]);

        $this->actingAs($agent)->post('/test/tenant/auction/bid/save', [
            'auction_id' => $auction->id,
            'first_name' => 'Jane',
            'last_name'  => 'Smith',
            'email'      => $agent->email,
        ]);

        $this->assertDatabaseMissing('tenant_agent_auction_bids', [
            'tenant_agent_auction_id' => $auction->id,
            'user_id'                 => $agent->id,
        ]);
    }

    public function test_bid_submission_is_blocked_for_nonexistent_auction(): void
    {
        $agent = $this->makeAgent();

        $response = $this->actingAs($agent)->post('/test/tenant/auction/bid/save', [
            'auction_id' => 99999,
            'first_name' => 'Jane',
            'last_name'  => 'Smith',
            'email'      => $agent->email,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('tenant_agent_auction_bids', 0);
    }

    // =========================================================================
    // Accept Bid
    // =========================================================================

    public function test_tenant_can_accept_an_active_bid(): void
    {
        $tenant  = $this->makeTenant();
        $agent   = $this->makeAgent();
        $auction = $this->makeActiveAuction($tenant);
        $bid     = $this->makeActiveBid($auction, $agent);

        $this->actingAs($tenant)->post(route('tenant.hire.agent.auction.bid.accept'), [
            'bid_id' => $bid->id,
        ]);

        $bid->refresh();
        $this->assertSame('accepted', $bid->accepted);

        $auction->refresh();
        $this->assertTrue((bool) $auction->is_sold);
    }

    public function test_accepting_a_bid_rejects_all_other_bids_on_the_same_auction(): void
    {
        $tenant  = $this->makeTenant();
        $agent1  = $this->makeAgent();
        $agent2  = $this->makeAgent();
        $auction = $this->makeActiveAuction($tenant);

        $bidToAccept = $this->makeActiveBid($auction, $agent1);
        $otherBid    = $this->makeActiveBid($auction, $agent2);

        $this->actingAs($tenant)->post(route('tenant.hire.agent.auction.bid.accept'), [
            'bid_id' => $bidToAccept->id,
        ]);

        $bidToAccept->refresh();
        $otherBid->refresh();

        $this->assertSame('accepted', $bidToAccept->accepted);
        $this->assertSame('rejected', $otherBid->accepted);
    }

    public function test_accepting_a_bid_creates_a_user_agent_record(): void
    {
        $tenant  = $this->makeTenant();
        $agent   = $this->makeAgent();
        $auction = $this->makeActiveAuction($tenant);
        $bid     = $this->makeActiveBid($auction, $agent);

        $this->actingAs($tenant)->post(route('tenant.hire.agent.auction.bid.accept'), [
            'bid_id' => $bid->id,
        ]);

        $this->assertDatabaseHas('user_agents', [
            'user_id'  => $tenant->id,
            'agent_id' => $agent->id,
            'type'     => 'tenant',
        ]);
    }

    public function test_non_owner_cannot_accept_a_bid(): void
    {
        $tenant   = $this->makeTenant();
        $agent    = $this->makeAgent();
        $stranger = $this->makeTenant();
        $auction  = $this->makeActiveAuction($tenant);
        $bid      = $this->makeActiveBid($auction, $agent);

        $response = $this->actingAs($stranger)->post(route('tenant.hire.agent.auction.bid.accept'), [
            'bid_id' => $bid->id,
        ]);

        $response->assertForbidden();
        $bid->refresh();
        $this->assertNull($bid->accepted);
    }

    public function test_cannot_accept_an_already_accepted_bid(): void
    {
        $tenant  = $this->makeTenant();
        $agent   = $this->makeAgent();
        $auction = $this->makeActiveAuction($tenant);
        $bid     = TenantAgentAuctionBid::factory()->accepted()->create([
            'tenant_agent_auction_id' => $auction->id,
            'user_id'                 => $agent->id,
        ]);

        $this->actingAs($tenant)->post(route('tenant.hire.agent.auction.bid.accept'), [
            'bid_id' => $bid->id,
        ]);

        // Bid status must remain unchanged — no double-acceptance.
        $bid->refresh();
        $this->assertSame('accepted', $bid->accepted);

        // No additional UserAgent should have been created for this attempt.
        $this->assertDatabaseCount('user_agents', 0);
    }

    public function test_cannot_accept_a_bid_on_expired_listing(): void
    {
        $tenant  = $this->makeTenant();
        $agent   = $this->makeAgent();
        $auction = TenantAgentAuction::factory()->expired()->create(['user_id' => $tenant->id]);
        $auction->saveMeta('expiration_date', now()->subDay()->toDateString());

        $bid = $this->makeActiveBid($auction, $agent);

        $this->actingAs($tenant)->post(route('tenant.hire.agent.auction.bid.accept'), [
            'bid_id' => $bid->id,
        ]);

        $bid->refresh();
        $this->assertNull($bid->accepted, 'Bid should not be accepted on an expired listing.');
    }

    public function test_accept_bid_sends_notification_to_agent(): void
    {
        $tenant  = $this->makeTenant();
        $agent   = $this->makeAgent();
        $auction = $this->makeActiveAuction($tenant);
        $bid     = $this->makeActiveBid($auction, $agent);

        $this->actingAs($tenant)->post(route('tenant.hire.agent.auction.bid.accept'), [
            'bid_id' => $bid->id,
        ]);

        Notification::assertSentTo($agent, BidAcceptedNotification::class);
    }

    // =========================================================================
    // Reject Bid
    // =========================================================================

    public function test_tenant_can_reject_an_active_bid(): void
    {
        $tenant  = $this->makeTenant();
        $agent   = $this->makeAgent();
        $auction = $this->makeActiveAuction($tenant);
        $bid     = $this->makeActiveBid($auction, $agent);

        $response = $this->actingAs($tenant)->post(route('tenant.hire.agent.auction.bid.reject'), [
            'bid_id' => $bid->id,
        ]);

        $response->assertRedirect();

        $bid->refresh();
        $this->assertSame('rejected', $bid->accepted);
    }

    public function test_non_owner_cannot_reject_a_bid(): void
    {
        $tenant   = $this->makeTenant();
        $agent    = $this->makeAgent();
        $stranger = $this->makeTenant();
        $auction  = $this->makeActiveAuction($tenant);
        $bid      = $this->makeActiveBid($auction, $agent);

        $response = $this->actingAs($stranger)->post(route('tenant.hire.agent.auction.bid.reject'), [
            'bid_id' => $bid->id,
        ]);

        $response->assertForbidden();
        $bid->refresh();
        $this->assertNull($bid->accepted);
    }

    public function test_cannot_reject_an_already_rejected_bid(): void
    {
        $tenant  = $this->makeTenant();
        $agent   = $this->makeAgent();
        $auction = $this->makeActiveAuction($tenant);
        $bid     = TenantAgentAuctionBid::factory()->rejected()->create([
            'tenant_agent_auction_id' => $auction->id,
            'user_id'                 => $agent->id,
        ]);

        $this->actingAs($tenant)->post(route('tenant.hire.agent.auction.bid.reject'), [
            'bid_id' => $bid->id,
        ]);

        // Bid should remain 'rejected', not change.
        $bid->refresh();
        $this->assertSame('rejected', $bid->accepted);
    }

    public function test_cannot_reject_a_bid_on_expired_listing(): void
    {
        $tenant  = $this->makeTenant();
        $agent   = $this->makeAgent();
        $auction = TenantAgentAuction::factory()->expired()->create(['user_id' => $tenant->id]);
        $auction->saveMeta('expiration_date', now()->subDay()->toDateString());

        $bid = $this->makeActiveBid($auction, $agent);

        $this->actingAs($tenant)->post(route('tenant.hire.agent.auction.bid.reject'), [
            'bid_id' => $bid->id,
        ]);

        $bid->refresh();
        $this->assertNull($bid->accepted, 'Bid should not be rejected on an expired listing.');
    }

    public function test_reject_bid_sends_notification_to_agent(): void
    {
        $tenant  = $this->makeTenant();
        $agent   = $this->makeAgent();
        $auction = $this->makeActiveAuction($tenant);
        $bid     = $this->makeActiveBid($auction, $agent);

        $this->actingAs($tenant)->post(route('tenant.hire.agent.auction.bid.reject'), [
            'bid_id' => $bid->id,
        ]);

        Notification::assertSentTo($agent, BidRejectedNotification::class);
    }

    // =========================================================================
    // Counter-Bid Flow
    // =========================================================================

    public function test_tenant_can_accept_a_counter_bid(): void
    {
        $tenant  = $this->makeTenant();
        $agent   = $this->makeAgent();
        $auction = $this->makeActiveAuction($tenant);
        $bid     = $this->makeActiveBid($auction, $agent);

        $counterBid = TenantCounterBidding::factory()->create([
            'user_id'                     => $agent->id,
            'tenant_agent_auction_id'     => $auction->id,
            'tenant_agent_auction_bid_id' => $bid->id,
            'accepted'                    => '0',
        ]);

        $this->actingAs($tenant)->post(route('tenant.hire.agent.auction.counter.bid.accept'), [
            'counter_bid_id' => $counterBid->id,
        ]);

        $counterBid->refresh();
        $this->assertSame('accepted', $counterBid->accepted);

        $bid->refresh();
        $this->assertSame('accepted', $bid->accepted);

        $auction->refresh();
        $this->assertTrue((bool) $auction->is_sold);
    }

    public function test_agent_can_accept_a_counter_bid_made_by_tenant(): void
    {
        $tenant  = $this->makeTenant();
        $agent   = $this->makeAgent();
        $auction = $this->makeActiveAuction($tenant);
        $bid     = $this->makeActiveBid($auction, $agent);

        $counterBid = TenantCounterBidding::factory()->create([
            'user_id'                     => $tenant->id,
            'tenant_agent_auction_id'     => $auction->id,
            'tenant_agent_auction_bid_id' => $bid->id,
            'accepted'                    => '0',
        ]);

        $this->actingAs($agent)->post(route('tenant.hire.agent.auction.counter.bid.accept'), [
            'counter_bid_id' => $counterBid->id,
        ]);

        $counterBid->refresh();
        $this->assertSame('accepted', $counterBid->accepted);
    }

    public function test_accepting_a_counter_bid_rejects_other_bids(): void
    {
        $tenant  = $this->makeTenant();
        $agent1  = $this->makeAgent();
        $agent2  = $this->makeAgent();
        $auction = $this->makeActiveAuction($tenant);

        $bid1 = $this->makeActiveBid($auction, $agent1);
        $bid2 = $this->makeActiveBid($auction, $agent2);

        $counterBid = TenantCounterBidding::factory()->create([
            'user_id'                     => $agent1->id,
            'tenant_agent_auction_id'     => $auction->id,
            'tenant_agent_auction_bid_id' => $bid1->id,
            'accepted'                    => '0',
        ]);

        $this->actingAs($tenant)->post(route('tenant.hire.agent.auction.counter.bid.accept'), [
            'counter_bid_id' => $counterBid->id,
        ]);

        $bid2->refresh();
        $this->assertSame('rejected', $bid2->accepted);
    }

    public function test_unauthorized_user_cannot_accept_a_counter_bid(): void
    {
        $tenant   = $this->makeTenant();
        $agent    = $this->makeAgent();
        $stranger = $this->makeTenant();
        $auction  = $this->makeActiveAuction($tenant);
        $bid      = $this->makeActiveBid($auction, $agent);

        $counterBid = TenantCounterBidding::factory()->create([
            'user_id'                     => $agent->id,
            'tenant_agent_auction_id'     => $auction->id,
            'tenant_agent_auction_bid_id' => $bid->id,
            'accepted'                    => '0',
        ]);

        $response = $this->actingAs($stranger)->post(route('tenant.hire.agent.auction.counter.bid.accept'), [
            'counter_bid_id' => $counterBid->id,
        ]);

        $response->assertForbidden();
        $counterBid->refresh();
        $this->assertSame('0', $counterBid->accepted);
    }

    public function test_tenant_can_reject_a_counter_bid(): void
    {
        $tenant  = $this->makeTenant();
        $agent   = $this->makeAgent();
        $auction = $this->makeActiveAuction($tenant);
        $bid     = $this->makeActiveBid($auction, $agent);

        $counterBid = TenantCounterBidding::factory()->create([
            'user_id'                     => $agent->id,
            'tenant_agent_auction_id'     => $auction->id,
            'tenant_agent_auction_bid_id' => $bid->id,
            'accepted'                    => '0',
        ]);

        $response = $this->actingAs($tenant)->post(route('tenant.hire.agent.auction.counter.bid.reject'), [
            'counter_bid_id' => $counterBid->id,
        ]);

        $response->assertRedirect();

        $counterBid->refresh();
        $this->assertSame('rejected', $counterBid->accepted);

        // Original bid should still be active (not affected by counter rejection)
        $bid->refresh();
        $this->assertNull($bid->accepted);
    }

    public function test_agent_can_reject_a_counter_bid_made_by_tenant(): void
    {
        $tenant  = $this->makeTenant();
        $agent   = $this->makeAgent();
        $auction = $this->makeActiveAuction($tenant);
        $bid     = $this->makeActiveBid($auction, $agent);

        $counterBid = TenantCounterBidding::factory()->create([
            'user_id'                     => $tenant->id,
            'tenant_agent_auction_id'     => $auction->id,
            'tenant_agent_auction_bid_id' => $bid->id,
            'accepted'                    => '0',
        ]);

        $response = $this->actingAs($agent)->post(route('tenant.hire.agent.auction.counter.bid.reject'), [
            'counter_bid_id' => $counterBid->id,
        ]);

        $response->assertRedirect();

        $counterBid->refresh();
        $this->assertSame('rejected', $counterBid->accepted);
    }

    public function test_unauthorized_user_cannot_reject_a_counter_bid(): void
    {
        $tenant   = $this->makeTenant();
        $agent    = $this->makeAgent();
        $stranger = $this->makeTenant();
        $auction  = $this->makeActiveAuction($tenant);
        $bid      = $this->makeActiveBid($auction, $agent);

        $counterBid = TenantCounterBidding::factory()->create([
            'user_id'                     => $agent->id,
            'tenant_agent_auction_id'     => $auction->id,
            'tenant_agent_auction_bid_id' => $bid->id,
            'accepted'                    => '0',
        ]);

        $response = $this->actingAs($stranger)->post(route('tenant.hire.agent.auction.counter.bid.reject'), [
            'counter_bid_id' => $counterBid->id,
        ]);

        $response->assertForbidden();
        $counterBid->refresh();
        $this->assertSame('0', $counterBid->accepted);
    }

    public function test_cannot_reject_an_already_settled_counter_bid(): void
    {
        $tenant  = $this->makeTenant();
        $agent   = $this->makeAgent();
        $auction = $this->makeActiveAuction($tenant);
        $bid     = $this->makeActiveBid($auction, $agent);

        $counterBid = TenantCounterBidding::factory()->accepted()->create([
            'user_id'                     => $agent->id,
            'tenant_agent_auction_id'     => $auction->id,
            'tenant_agent_auction_bid_id' => $bid->id,
        ]);

        $this->actingAs($tenant)->post(route('tenant.hire.agent.auction.counter.bid.reject'), [
            'counter_bid_id' => $counterBid->id,
        ]);

        // Counter bid should remain 'accepted', not flipped to 'rejected'.
        $counterBid->refresh();
        $this->assertSame('accepted', $counterBid->accepted);
    }
}
