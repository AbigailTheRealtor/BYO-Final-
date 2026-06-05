<?php

namespace Tests\Feature\Showing;

use App\Enums\ShowingStatus;
use App\Models\OfferAuction;
use App\Models\Showing;
use App\Models\User;
use App\Models\UserAgent;
use App\Services\Showing\ShowingStatusService;
use Illuminate\Support\Facades\DB;
use App\Exceptions\ShowingTransitionException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ShowingApprovalTest extends TestCase
{
    use DatabaseTransactions;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function sellerAuction(User $owner = null): OfferAuction
    {
        $auction = OfferAuction::factory()->sellerListing()->create([
            'user_id' => ($owner ?? User::factory()->create())->id,
        ]);
        return $auction->fresh();
    }

    private function landlordAuction(User $owner = null): OfferAuction
    {
        $auction = OfferAuction::factory()->landlordListing()->create([
            'user_id' => ($owner ?? User::factory()->create())->id,
        ]);
        return $auction->fresh();
    }

    private function requestedShowing(OfferAuction $auction, User $requester = null): Showing
    {
        return Showing::factory()->create([
            'offer_auction_id' => $auction->id,
            'requester_id'     => ($requester ?? User::factory()->create())->id,
            'status'           => ShowingStatus::REQUESTED,
        ]);
    }

    private function approvedShowing(OfferAuction $auction, User $requester = null): Showing
    {
        return Showing::factory()->approved()->create([
            'offer_auction_id' => $auction->id,
            'requester_id'     => ($requester ?? User::factory()->create())->id,
        ]);
    }

    private function service(): ShowingStatusService
    {
        return app(ShowingStatusService::class);
    }

    private function actor(): User
    {
        return User::factory()->create();
    }

    // -------------------------------------------------------------------------
    // ShowingStatusService — valid transitions
    // -------------------------------------------------------------------------

    public function test_approve_transitions_from_requested_to_approved()
    {
        $auction = $this->sellerAuction();
        $showing = $this->requestedShowing($auction);

        $result = $this->service()->approve($showing, [
            'approved_date'       => '2026-08-01',
            'approved_start_time' => '10:00:00',
            'approved_end_time'   => '11:00:00',
            'owner_message'       => 'See you then!',
        ], $this->actor());

        $this->assertSame(ShowingStatus::APPROVED, $result->fresh()->status);
        $this->assertDatabaseHas('showings', [
            'id'            => $showing->id,
            'status'        => 'approved',
            'owner_message' => 'See you then!',
        ]);
    }

    public function test_approve_defaults_to_requested_slot_when_no_confirmed_time_given()
    {
        $auction = $this->sellerAuction();
        $showing = $this->requestedShowing($auction);

        $result = $this->service()->approve($showing, [], $this->actor());

        $this->assertSame(ShowingStatus::APPROVED, $result->fresh()->status);
    }

    public function test_decline_transitions_from_requested_to_declined()
    {
        $auction = $this->sellerAuction();
        $showing = $this->requestedShowing($auction);

        $result = $this->service()->decline($showing, ['owner_message' => 'Not available.'], $this->actor());

        $this->assertSame(ShowingStatus::DECLINED, $result->fresh()->status);
        $this->assertDatabaseHas('showings', [
            'id'            => $showing->id,
            'status'        => 'declined',
            'owner_message' => 'Not available.',
        ]);
    }

    public function test_cancel_transitions_from_requested_to_canceled()
    {
        $owner   = User::factory()->create();
        $auction = $this->sellerAuction($owner);
        $showing = $this->requestedShowing($auction);

        $result = $this->service()->cancel($showing, $owner);

        $this->assertSame(ShowingStatus::CANCELED, $result->fresh()->status);
        $this->assertNotNull($result->fresh()->canceled_at);
    }

    public function test_cancel_transitions_from_approved_to_canceled()
    {
        $owner   = User::factory()->create();
        $auction = $this->sellerAuction($owner);
        $showing = $this->approvedShowing($auction);

        $result = $this->service()->cancel($showing, $owner);

        $this->assertSame(ShowingStatus::CANCELED, $result->fresh()->status);
    }

    public function test_complete_transitions_from_approved_to_completed()
    {
        $owner   = User::factory()->create();
        $auction = $this->sellerAuction($owner);
        $showing = $this->approvedShowing($auction);

        $result = $this->service()->complete($showing, $owner);

        $this->assertSame(ShowingStatus::COMPLETED, $result->fresh()->status);
        $this->assertNotNull($result->fresh()->completed_at);
    }

    // -------------------------------------------------------------------------
    // ShowingStatusService — invalid transitions
    // -------------------------------------------------------------------------

    public function test_approve_throws_when_already_approved()
    {
        $this->expectException(ShowingTransitionException::class);

        $auction = $this->sellerAuction();
        $showing = $this->approvedShowing($auction);

        $this->service()->approve($showing, [], $this->actor());
    }

    public function test_approve_throws_when_declined()
    {
        $this->expectException(ShowingTransitionException::class);

        $auction = $this->sellerAuction();
        $showing = Showing::factory()->declined()->create(['offer_auction_id' => $auction->id]);

        $this->service()->approve($showing, [], $this->actor());
    }

    public function test_approve_throws_when_canceled()
    {
        $this->expectException(ShowingTransitionException::class);

        $auction = $this->sellerAuction();
        $showing = Showing::factory()->canceled()->create(['offer_auction_id' => $auction->id]);

        $this->service()->approve($showing, [], $this->actor());
    }

    public function test_approve_throws_when_completed()
    {
        $this->expectException(ShowingTransitionException::class);

        $auction = $this->sellerAuction();
        $showing = Showing::factory()->completed()->create(['offer_auction_id' => $auction->id]);

        $this->service()->approve($showing, [], $this->actor());
    }

    public function test_decline_throws_when_already_approved()
    {
        $this->expectException(ShowingTransitionException::class);

        $auction = $this->sellerAuction();
        $showing = $this->approvedShowing($auction);

        $this->service()->decline($showing, [], $this->actor());
    }

    public function test_decline_throws_when_already_declined()
    {
        $this->expectException(ShowingTransitionException::class);

        $auction = $this->sellerAuction();
        $showing = Showing::factory()->declined()->create(['offer_auction_id' => $auction->id]);

        $this->service()->decline($showing, [], $this->actor());
    }

    public function test_decline_throws_when_canceled()
    {
        $this->expectException(ShowingTransitionException::class);

        $auction = $this->sellerAuction();
        $showing = Showing::factory()->canceled()->create(['offer_auction_id' => $auction->id]);

        $this->service()->decline($showing, [], $this->actor());
    }

    public function test_cancel_throws_when_already_declined()
    {
        $this->expectException(ShowingTransitionException::class);

        $owner   = User::factory()->create();
        $auction = $this->sellerAuction($owner);
        $showing = Showing::factory()->declined()->create(['offer_auction_id' => $auction->id]);

        $this->service()->cancel($showing, $owner);
    }

    public function test_cancel_throws_when_already_canceled()
    {
        $this->expectException(ShowingTransitionException::class);

        $owner   = User::factory()->create();
        $auction = $this->sellerAuction($owner);
        $showing = Showing::factory()->canceled()->create(['offer_auction_id' => $auction->id]);

        $this->service()->cancel($showing, $owner);
    }

    public function test_cancel_throws_when_completed()
    {
        $this->expectException(ShowingTransitionException::class);

        $owner   = User::factory()->create();
        $auction = $this->sellerAuction($owner);
        $showing = Showing::factory()->completed()->create(['offer_auction_id' => $auction->id]);

        $this->service()->cancel($showing, $owner);
    }

    public function test_complete_throws_when_requested()
    {
        $this->expectException(ShowingTransitionException::class);

        $owner   = User::factory()->create();
        $auction = $this->sellerAuction($owner);
        $showing = $this->requestedShowing($auction);

        $this->service()->complete($showing, $owner);
    }

    public function test_complete_throws_when_declined()
    {
        $this->expectException(ShowingTransitionException::class);

        $owner   = User::factory()->create();
        $auction = $this->sellerAuction($owner);
        $showing = Showing::factory()->declined()->create(['offer_auction_id' => $auction->id]);

        $this->service()->complete($showing, $owner);
    }

    public function test_complete_throws_when_canceled()
    {
        $this->expectException(ShowingTransitionException::class);

        $owner   = User::factory()->create();
        $auction = $this->sellerAuction($owner);
        $showing = Showing::factory()->canceled()->create(['offer_auction_id' => $auction->id]);

        $this->service()->complete($showing, $owner);
    }

    // -------------------------------------------------------------------------
    // HTTP — owner can approve (200 → redirect with success)
    // -------------------------------------------------------------------------

    public function test_owner_can_approve_showing_via_http()
    {
        $owner   = User::factory()->create();
        $auction = $this->sellerAuction($owner);
        $showing = $this->requestedShowing($auction);

        $response = $this->actingAs($owner)->patch(route('showings.approve', $showing), [
            'approved_date'       => '2026-09-01',
            'approved_start_time' => '10:00',
            'approved_end_time'   => '11:00',
        ]);

        $response->assertRedirect();
        $this->assertSame(ShowingStatus::APPROVED, $showing->fresh()->status);
    }

    public function test_owner_can_decline_showing_via_http()
    {
        $owner   = User::factory()->create();
        $auction = $this->sellerAuction($owner);
        $showing = $this->requestedShowing($auction);

        $response = $this->actingAs($owner)->patch(route('showings.decline', $showing), [
            'owner_message' => 'Slot unavailable.',
        ]);

        $response->assertRedirect();
        $this->assertSame(ShowingStatus::DECLINED, $showing->fresh()->status);
    }

    public function test_owner_can_cancel_approved_showing_via_http()
    {
        $owner   = User::factory()->create();
        $auction = $this->sellerAuction($owner);
        $showing = $this->approvedShowing($auction);

        $response = $this->actingAs($owner)->patch(route('showings.cancel', $showing));

        $response->assertRedirect();
        $this->assertSame(ShowingStatus::CANCELED, $showing->fresh()->status);
    }

    public function test_owner_can_complete_approved_showing_via_http()
    {
        $owner   = User::factory()->create();
        $auction = $this->sellerAuction($owner);
        $showing = $this->approvedShowing($auction);

        $response = $this->actingAs($owner)->patch(route('showings.complete', $showing));

        $response->assertRedirect();
        $this->assertSame(ShowingStatus::COMPLETED, $showing->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // HTTP — invalid transitions return error (redirect back with error flash)
    // -------------------------------------------------------------------------

    public function test_owner_cannot_approve_already_approved_showing()
    {
        $owner   = User::factory()->create();
        $auction = $this->sellerAuction($owner);
        $showing = $this->approvedShowing($auction);

        $response = $this->actingAs($owner)->patch(route('showings.approve', $showing), []);

        $response->assertStatus(422);
        $this->assertSame(ShowingStatus::APPROVED, $showing->fresh()->status);
    }

    public function test_owner_cannot_complete_a_requested_showing()
    {
        $owner   = User::factory()->create();
        $auction = $this->sellerAuction($owner);
        $showing = $this->requestedShowing($auction);

        $response = $this->actingAs($owner)->patch(route('showings.complete', $showing));

        $response->assertStatus(422);
        $this->assertSame(ShowingStatus::REQUESTED, $showing->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // HTTP — non-owner cannot approve/decline
    // -------------------------------------------------------------------------

    public function test_non_owner_cannot_approve_showing()
    {
        $owner     = User::factory()->create();
        $stranger  = User::factory()->create();
        $auction   = $this->sellerAuction($owner);
        $showing   = $this->requestedShowing($auction);

        $response = $this->actingAs($stranger)->patch(route('showings.approve', $showing), []);

        $response->assertStatus(403);
        $this->assertSame(ShowingStatus::REQUESTED, $showing->fresh()->status);
    }

    public function test_non_owner_cannot_decline_showing()
    {
        $owner    = User::factory()->create();
        $stranger = User::factory()->create();
        $auction  = $this->sellerAuction($owner);
        $showing  = $this->requestedShowing($auction);

        $response = $this->actingAs($stranger)->patch(route('showings.decline', $showing), []);

        $response->assertStatus(403);
        $this->assertSame(ShowingStatus::REQUESTED, $showing->fresh()->status);
    }

    public function test_non_owner_cannot_complete_showing()
    {
        $owner    = User::factory()->create();
        $stranger = User::factory()->create();
        $auction  = $this->sellerAuction($owner);
        $showing  = $this->approvedShowing($auction);

        $response = $this->actingAs($stranger)->patch(route('showings.complete', $showing));

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // HTTP — requester can cancel their own pending or approved request
    // -------------------------------------------------------------------------

    public function test_requester_can_cancel_their_own_pending_request()
    {
        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $auction   = $this->sellerAuction($owner);
        $showing   = $this->requestedShowing($auction, $requester);

        $response = $this->actingAs($requester)->patch(route('showings.cancel', $showing));

        $response->assertRedirect();
        $this->assertSame(ShowingStatus::CANCELED, $showing->fresh()->status);
    }

    public function test_requester_can_cancel_their_own_approved_request()
    {
        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $auction   = $this->sellerAuction($owner);
        $showing   = $this->approvedShowing($auction, $requester);

        $response = $this->actingAs($requester)->patch(route('showings.cancel', $showing));

        $response->assertRedirect();
        $this->assertSame(ShowingStatus::CANCELED, $showing->fresh()->status);
    }

    public function test_requester_cannot_cancel_someone_elses_request()
    {
        $owner     = User::factory()->create();
        $requester = User::factory()->create();
        $stranger  = User::factory()->create();
        $auction   = $this->sellerAuction($owner);
        $showing   = $this->requestedShowing($auction, $requester);

        $response = $this->actingAs($stranger)->patch(route('showings.cancel', $showing));

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // HTTP — assigned agent can approve/decline/complete
    // -------------------------------------------------------------------------

    public function test_assigned_agent_can_approve_showing()
    {
        $owner   = User::factory()->create();
        $agent   = User::factory()->create(['user_type' => 'agent']);
        $auction = $this->sellerAuction($owner);
        $showing = $this->requestedShowing($auction);

        DB::table('user_agents')->insert(['user_id' => $owner->id, 'agent_id' => $agent->id, 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->actingAs($agent)->patch(route('showings.approve', $showing), []);

        $response->assertRedirect();
        $this->assertSame(ShowingStatus::APPROVED, $showing->fresh()->status);
    }

    public function test_assigned_agent_can_decline_showing()
    {
        $owner   = User::factory()->create();
        $agent   = User::factory()->create(['user_type' => 'agent']);
        $auction = $this->sellerAuction($owner);
        $showing = $this->requestedShowing($auction);

        DB::table('user_agents')->insert(['user_id' => $owner->id, 'agent_id' => $agent->id, 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->actingAs($agent)->patch(route('showings.decline', $showing), []);

        $response->assertRedirect();
        $this->assertSame(ShowingStatus::DECLINED, $showing->fresh()->status);
    }

    public function test_assigned_agent_can_complete_showing()
    {
        $owner   = User::factory()->create();
        $agent   = User::factory()->create(['user_type' => 'agent']);
        $auction = $this->sellerAuction($owner);
        $showing = $this->approvedShowing($auction);

        DB::table('user_agents')->insert(['user_id' => $owner->id, 'agent_id' => $agent->id, 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->actingAs($agent)->patch(route('showings.complete', $showing));

        $response->assertRedirect();
        $this->assertSame(ShowingStatus::COMPLETED, $showing->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // HTTP — manage page accessible to authenticated owner
    // -------------------------------------------------------------------------

    public function test_manage_page_is_accessible_to_authenticated_user()
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->get(route('showings.manage'))->assertStatus(200);
    }

    public function test_manage_page_redirects_unauthenticated_users()
    {
        $this->get(route('showings.manage'))->assertRedirect(route('login'));
    }
}
