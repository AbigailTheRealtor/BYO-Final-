<?php

namespace Tests\Feature\Showing;

use App\Enums\ShowingStatus;
use App\Models\OfferAuction;
use App\Models\Showing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ShowingRequestTest extends TestCase
{
    use DatabaseTransactions;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function sellerAuction(array $attrs = []): OfferAuction
    {
        return OfferAuction::factory()->sellerListing()->create($attrs);
    }

    private function landlordAuction(array $attrs = []): OfferAuction
    {
        return OfferAuction::factory()->landlordListing()->create($attrs);
    }

    private function buyerAuction(): OfferAuction
    {
        return OfferAuction::factory()->buyerListing()->create();
    }

    private function tenantAuction(): OfferAuction
    {
        return OfferAuction::factory()->tenantListing()->create();
    }

    private function validPayload(OfferAuction $auction): array
    {
        return [
            'offer_auction_id'     => $auction->id,
            'requested_date'       => now()->addDays(3)->format('Y-m-d'),
            'requested_start_time' => '10:00',
            'requested_end_time'   => '11:00',
            'requester_message'    => 'Looking forward to seeing the property.',
        ];
    }

    // -------------------------------------------------------------------------
    // Guest is redirected to login
    // -------------------------------------------------------------------------

    public function test_guest_is_redirected_to_login_when_submitting_showing_request()
    {
        $auction = $this->sellerAuction();

        $response = $this->post(route('showings.store'), $this->validPayload($auction));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('showings', ['offer_auction_id' => $auction->id]);
    }

    public function test_guest_cannot_access_my_showings_page()
    {
        $response = $this->get(route('showings.index'));
        $response->assertRedirect(route('login'));
    }

    // -------------------------------------------------------------------------
    // Owner cannot request their own listing
    // -------------------------------------------------------------------------

    public function test_owner_cannot_request_showing_on_their_own_listing()
    {
        $owner   = User::factory()->create();
        $auction = $this->sellerAuction(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->post(route('showings.store'), $this->validPayload($auction));

        $response->assertForbidden();
        $this->assertDatabaseMissing('showings', ['offer_auction_id' => $auction->id]);
    }

    // -------------------------------------------------------------------------
    // Buyer/Tenant listing types are rejected
    // -------------------------------------------------------------------------

    public function test_cannot_request_showing_on_buyer_criteria_listing()
    {
        $user    = User::factory()->create();
        $auction = $this->buyerAuction();

        $response = $this->actingAs($user)
            ->post(route('showings.store'), $this->validPayload($auction));

        $response->assertForbidden();
        $this->assertDatabaseMissing('showings', ['offer_auction_id' => $auction->id]);
    }

    public function test_cannot_request_showing_on_tenant_criteria_listing()
    {
        $user    = User::factory()->create();
        $auction = $this->tenantAuction();

        $response = $this->actingAs($user)
            ->post(route('showings.store'), $this->validPayload($auction));

        $response->assertForbidden();
        $this->assertDatabaseMissing('showings', ['offer_auction_id' => $auction->id]);
    }

    // -------------------------------------------------------------------------
    // Valid seller listing — authenticated user can submit
    // -------------------------------------------------------------------------

    public function test_authenticated_user_can_request_showing_on_seller_listing()
    {
        $user    = User::factory()->create();
        $auction = $this->sellerAuction();

        $response = $this->actingAs($user)
            ->post(route('showings.store'), $this->validPayload($auction));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('showings', [
            'offer_auction_id' => $auction->id,
            'requester_id'     => $user->id,
            'status'           => ShowingStatus::REQUESTED,
        ]);
    }

    public function test_showing_is_stored_with_requested_status()
    {
        $user    = User::factory()->create();
        $auction = $this->sellerAuction();

        $this->actingAs($user)
            ->post(route('showings.store'), $this->validPayload($auction));

        $showing = Showing::where('offer_auction_id', $auction->id)
            ->where('requester_id', $user->id)
            ->first();

        $this->assertNotNull($showing);
        $this->assertSame(ShowingStatus::REQUESTED, $showing->status);
        $this->assertTrue($showing->isRequested());
    }

    // -------------------------------------------------------------------------
    // Buyer can request a seller listing
    // -------------------------------------------------------------------------

    public function test_buyer_user_can_request_showing_on_seller_listing()
    {
        $buyer   = User::factory()->create();
        $auction = $this->sellerAuction();

        $response = $this->actingAs($buyer)
            ->post(route('showings.store'), $this->validPayload($auction));

        $response->assertRedirect();
        $this->assertDatabaseHas('showings', [
            'offer_auction_id' => $auction->id,
            'requester_id'     => $buyer->id,
            'status'           => ShowingStatus::REQUESTED,
        ]);
    }

    // -------------------------------------------------------------------------
    // Valid landlord listing
    // -------------------------------------------------------------------------

    public function test_authenticated_user_can_request_showing_on_landlord_listing()
    {
        $user    = User::factory()->create();
        $auction = $this->landlordAuction();

        $response = $this->actingAs($user)
            ->post(route('showings.store'), $this->validPayload($auction));

        $response->assertRedirect();
        $this->assertDatabaseHas('showings', [
            'offer_auction_id' => $auction->id,
            'requester_id'     => $user->id,
            'status'           => ShowingStatus::REQUESTED,
        ]);
    }

    // -------------------------------------------------------------------------
    // Flexible request (no availability slot) is allowed
    // -------------------------------------------------------------------------

    public function test_showing_request_without_availability_slot_is_stored_without_slot_id()
    {
        $user    = User::factory()->create();
        $auction = $this->sellerAuction();

        $this->actingAs($user)
            ->post(route('showings.store'), $this->validPayload($auction));

        $showing = Showing::where('offer_auction_id', $auction->id)->first();
        $this->assertNotNull($showing);
        $this->assertNull($showing->showing_availability_id);
    }

    // -------------------------------------------------------------------------
    // Validation errors
    // -------------------------------------------------------------------------

    public function test_showing_request_fails_when_date_is_missing()
    {
        $user    = User::factory()->create();
        $auction = $this->sellerAuction();
        $payload = $this->validPayload($auction);
        unset($payload['requested_date']);

        $response = $this->actingAs($user)
            ->post(route('showings.store'), $payload);

        $response->assertSessionHasErrors('requested_date');
        $this->assertDatabaseMissing('showings', ['offer_auction_id' => $auction->id]);
    }

    public function test_showing_request_fails_when_date_is_in_the_past()
    {
        $user    = User::factory()->create();
        $auction = $this->sellerAuction();
        $payload = $this->validPayload($auction);
        $payload['requested_date'] = now()->subDay()->format('Y-m-d');

        $response = $this->actingAs($user)
            ->post(route('showings.store'), $payload);

        $response->assertSessionHasErrors('requested_date');
        $this->assertDatabaseMissing('showings', ['offer_auction_id' => $auction->id]);
    }

    public function test_showing_request_fails_when_end_time_before_start_time()
    {
        $user    = User::factory()->create();
        $auction = $this->sellerAuction();
        $payload = $this->validPayload($auction);
        $payload['requested_start_time'] = '14:00';
        $payload['requested_end_time']   = '09:00';

        $response = $this->actingAs($user)
            ->post(route('showings.store'), $payload);

        $response->assertSessionHasErrors('requested_end_time');
        $this->assertDatabaseMissing('showings', ['offer_auction_id' => $auction->id]);
    }

    public function test_showing_request_fails_for_nonexistent_listing()
    {
        $user    = User::factory()->create();
        $payload = $this->validPayload(OfferAuction::factory()->make(['id' => 999999]));
        $payload['offer_auction_id'] = 999999;

        $response = $this->actingAs($user)
            ->post(route('showings.store'), $payload);

        $response->assertSessionHasErrors('offer_auction_id');
    }

    // -------------------------------------------------------------------------
    // My Showings page
    // -------------------------------------------------------------------------

    public function test_my_showings_page_is_accessible_when_authenticated()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('showings.index'));
        $response->assertOk();
        $response->assertViewIs('showings.index');
    }

    public function test_my_showings_page_lists_only_the_current_users_showings()
    {
        $user1   = User::factory()->create();
        $user2   = User::factory()->create();
        $auction = $this->sellerAuction();

        Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $user1->id,
            'requested_date'       => now()->addDays(2)->format('Y-m-d'),
            'requested_start_time' => '10:00:00',
            'requested_end_time'   => '11:00:00',
            'status'               => ShowingStatus::REQUESTED,
        ]);

        Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $user2->id,
            'requested_date'       => now()->addDays(3)->format('Y-m-d'),
            'requested_start_time' => '14:00:00',
            'requested_end_time'   => '15:00:00',
            'status'               => ShowingStatus::REQUESTED,
        ]);

        $response = $this->actingAs($user1)->get(route('showings.index'));
        $response->assertOk();

        $showings = $response->viewData('showings');
        $this->assertTrue($showings->contains('requester_id', $user1->id));
        $this->assertFalse($showings->contains('requester_id', $user2->id));
    }

    public function test_requester_message_is_stored_when_provided()
    {
        $user    = User::factory()->create();
        $auction = $this->sellerAuction();
        $payload = $this->validPayload($auction);
        $payload['requester_message'] = 'I would like to see the kitchen in detail.';

        $this->actingAs($user)
            ->post(route('showings.store'), $payload);

        $this->assertDatabaseHas('showings', [
            'offer_auction_id'  => $auction->id,
            'requester_id'      => $user->id,
            'requester_message' => 'I would like to see the kitchen in detail.',
        ]);
    }

    public function test_requester_message_is_optional()
    {
        $user    = User::factory()->create();
        $auction = $this->sellerAuction();
        $payload = $this->validPayload($auction);
        unset($payload['requester_message']);

        $response = $this->actingAs($user)
            ->post(route('showings.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('showings', [
            'offer_auction_id' => $auction->id,
            'requester_id'     => $user->id,
            'status'           => ShowingStatus::REQUESTED,
        ]);
    }

    // -------------------------------------------------------------------------
    // Validation redirect renders without 500 (regression: MessageBag::only()
    // does not exist; form partial must use $errors->get() per field)
    // -------------------------------------------------------------------------

    public function test_validation_failure_redirects_back_without_server_error()
    {
        $user    = User::factory()->create();
        $auction = $this->sellerAuction();
        $payload = $this->validPayload($auction);
        unset($payload['requested_date']);

        $response = $this->actingAs($user)
            ->post(route('showings.store'), $payload);

        $response->assertSessionHasErrors('requested_date');
        $response->assertStatus(302);
        $this->assertDatabaseMissing('showings', ['offer_auction_id' => $auction->id]);
    }

    // -------------------------------------------------------------------------
    // Assigned agent is blocked
    // -------------------------------------------------------------------------

    public function test_assigned_agent_cannot_request_showing_on_seller_listing()
    {
        $agent   = User::factory()->create();
        $auction = $this->sellerAuction();
        $auction->saveMeta('hired_agent_id', (string) $agent->id);

        $response = $this->actingAs($agent)
            ->post(route('showings.store'), $this->validPayload($auction));

        $response->assertForbidden();
        $this->assertDatabaseMissing('showings', ['offer_auction_id' => $auction->id]);
    }

    public function test_assigned_agent_cannot_request_showing_on_landlord_listing()
    {
        $agent   = User::factory()->create();
        $auction = $this->landlordAuction();
        $auction->saveMeta('hired_agent_id', (string) $agent->id);

        $response = $this->actingAs($agent)
            ->post(route('showings.store'), $this->validPayload($auction));

        $response->assertForbidden();
        $this->assertDatabaseMissing('showings', ['offer_auction_id' => $auction->id]);
    }
}
