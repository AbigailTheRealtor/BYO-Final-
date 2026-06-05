<?php

namespace Tests\Feature\Showing;

use App\Enums\ShowingStatus;
use App\Models\OfferAuction;
use App\Models\Showing;
use App\Models\ShowingAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ShowingFoundationTest extends TestCase
{
    use DatabaseTransactions;

    private function sellerAuction(): OfferAuction
    {
        return OfferAuction::factory()->sellerListing()->create();
    }

    private function landlordAuction(): OfferAuction
    {
        return OfferAuction::factory()->landlordListing()->create();
    }

    private function buyerAuction(): OfferAuction
    {
        return OfferAuction::factory()->buyerListing()->create();
    }

    private function tenantAuction(): OfferAuction
    {
        return OfferAuction::factory()->tenantListing()->create();
    }

    // -------------------------------------------------------------------------
    // ShowingStatus constants
    // -------------------------------------------------------------------------

    public function test_showing_status_constants_are_defined()
    {
        $this->assertSame('requested', ShowingStatus::REQUESTED);
        $this->assertSame('approved',  ShowingStatus::APPROVED);
        $this->assertSame('declined',  ShowingStatus::DECLINED);
        $this->assertSame('canceled',  ShowingStatus::CANCELED);
        $this->assertSame('completed', ShowingStatus::COMPLETED);
    }

    public function test_showing_status_all_returns_all_five_statuses()
    {
        $all = ShowingStatus::all();
        $this->assertCount(5, $all);
        $this->assertContains(ShowingStatus::REQUESTED, $all);
        $this->assertContains(ShowingStatus::APPROVED,  $all);
        $this->assertContains(ShowingStatus::DECLINED,  $all);
        $this->assertContains(ShowingStatus::CANCELED,  $all);
        $this->assertContains(ShowingStatus::COMPLETED, $all);
    }

    // -------------------------------------------------------------------------
    // ShowingAvailability — save & retrieve
    // -------------------------------------------------------------------------

    public function test_showing_availability_can_be_created_and_retrieved()
    {
        $auction = $this->sellerAuction();
        $user    = User::factory()->create();

        $availability = ShowingAvailability::create([
            'offer_auction_id' => $auction->id,
            'user_id'          => $user->id,
            'available_date'   => '2026-07-15',
            'start_time'       => '09:00:00',
            'end_time'         => '11:00:00',
            'notes'            => 'Morning slot only.',
            'max_showings'     => 3,
        ]);

        $this->assertDatabaseHas('showing_availabilities', [
            'id'               => $availability->id,
            'offer_auction_id' => $auction->id,
            'user_id'          => $user->id,
            'max_showings'     => 3,
        ]);

        $fresh = ShowingAvailability::find($availability->id);
        $this->assertSame('Morning slot only.', $fresh->notes);
        $this->assertSame(3, $fresh->max_showings);
    }

    public function test_showing_availability_max_showings_can_be_null()
    {
        $auction = $this->landlordAuction();
        $user    = User::factory()->create();

        $availability = ShowingAvailability::create([
            'offer_auction_id' => $auction->id,
            'user_id'          => $user->id,
            'available_date'   => '2026-08-01',
            'start_time'       => '10:00:00',
            'end_time'         => '12:00:00',
            'max_showings'     => null,
        ]);

        $this->assertNull($availability->fresh()->max_showings);
    }

    // -------------------------------------------------------------------------
    // Showing — save & retrieve across all statuses
    // -------------------------------------------------------------------------

    public function test_showing_can_be_created_in_requested_status()
    {
        $auction   = $this->sellerAuction();
        $requester = User::factory()->create();

        $showing = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => '2026-07-20',
            'requested_start_time' => '14:00:00',
            'requested_end_time'   => '15:00:00',
            'status'               => ShowingStatus::REQUESTED,
        ]);

        $this->assertDatabaseHas('showings', [
            'id'     => $showing->id,
            'status' => 'requested',
        ]);
        $this->assertTrue($showing->isRequested());
    }

    public function test_showing_can_be_created_in_approved_status()
    {
        $auction   = $this->landlordAuction();
        $requester = User::factory()->create();

        $showing = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => '2026-07-21',
            'requested_start_time' => '09:00:00',
            'requested_end_time'   => '10:00:00',
            'status'               => ShowingStatus::APPROVED,
            'approved_date'        => '2026-07-21',
            'approved_start_time'  => '09:00:00',
            'approved_end_time'    => '10:00:00',
        ]);

        $this->assertTrue($showing->isApproved());
        $this->assertFalse($showing->isRequested());
    }

    public function test_showing_can_be_created_in_declined_status()
    {
        $auction   = $this->sellerAuction();
        $requester = User::factory()->create();

        $showing = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => '2026-07-22',
            'requested_start_time' => '11:00:00',
            'requested_end_time'   => '12:00:00',
            'status'               => ShowingStatus::DECLINED,
        ]);

        $this->assertTrue($showing->isDeclined());
    }

    public function test_showing_can_be_created_in_canceled_status()
    {
        $auction   = $this->sellerAuction();
        $requester = User::factory()->create();

        $showing = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => '2026-07-23',
            'requested_start_time' => '13:00:00',
            'requested_end_time'   => '14:00:00',
            'status'               => ShowingStatus::CANCELED,
            'canceled_at'          => now(),
        ]);

        $this->assertTrue($showing->isCanceled());
    }

    public function test_showing_can_be_created_in_completed_status()
    {
        $auction   = $this->landlordAuction();
        $requester = User::factory()->create();

        $showing = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => '2026-07-24',
            'requested_start_time' => '15:00:00',
            'requested_end_time'   => '16:00:00',
            'status'               => ShowingStatus::COMPLETED,
            'completed_at'         => now(),
        ]);

        $this->assertTrue($showing->isCompleted());
    }

    // -------------------------------------------------------------------------
    // Showing — linked to availability slot
    // -------------------------------------------------------------------------

    public function test_showing_can_be_linked_to_availability_slot()
    {
        $auction      = $this->sellerAuction();
        $user         = User::factory()->create();
        $requester    = User::factory()->create();

        $availability = ShowingAvailability::create([
            'offer_auction_id' => $auction->id,
            'user_id'          => $user->id,
            'available_date'   => '2026-07-25',
            'start_time'       => '10:00:00',
            'end_time'         => '12:00:00',
        ]);

        $showing = Showing::create([
            'showing_availability_id' => $availability->id,
            'offer_auction_id'        => $auction->id,
            'requester_id'            => $requester->id,
            'requested_date'          => '2026-07-25',
            'requested_start_time'    => '10:00:00',
            'requested_end_time'      => '11:00:00',
            'status'                  => ShowingStatus::REQUESTED,
        ]);

        $this->assertSame($availability->id, $showing->fresh()->showing_availability_id);
        $this->assertSame($showing->id, $availability->showings()->first()->id);
    }

    public function test_showing_can_exist_without_availability_slot()
    {
        $auction   = $this->sellerAuction();
        $requester = User::factory()->create();

        $showing = Showing::create([
            'showing_availability_id' => null,
            'offer_auction_id'        => $auction->id,
            'requester_id'            => $requester->id,
            'requested_date'          => '2026-07-26',
            'requested_start_time'    => '14:00:00',
            'requested_end_time'      => '15:00:00',
            'status'                  => ShowingStatus::REQUESTED,
        ]);

        $this->assertNull($showing->fresh()->showing_availability_id);
    }

    // -------------------------------------------------------------------------
    // Eligibility scope — seller and landlord are eligible
    // -------------------------------------------------------------------------

    public function test_scope_for_eligible_listings_includes_seller_auctions()
    {
        $auction = $this->sellerAuction();
        $user    = User::factory()->create();

        ShowingAvailability::create([
            'offer_auction_id' => $auction->id,
            'user_id'          => $user->id,
            'available_date'   => '2026-08-01',
            'start_time'       => '09:00:00',
            'end_time'         => '10:00:00',
        ]);

        $results = ShowingAvailability::forEligibleListings()->get();
        $this->assertTrue($results->contains('offer_auction_id', $auction->id));
    }

    public function test_scope_for_eligible_listings_includes_landlord_auctions()
    {
        $auction = $this->landlordAuction();
        $user    = User::factory()->create();

        ShowingAvailability::create([
            'offer_auction_id' => $auction->id,
            'user_id'          => $user->id,
            'available_date'   => '2026-08-02',
            'start_time'       => '10:00:00',
            'end_time'         => '11:00:00',
        ]);

        $results = ShowingAvailability::forEligibleListings()->get();
        $this->assertTrue($results->contains('offer_auction_id', $auction->id));
    }

    public function test_scope_for_eligible_listings_excludes_buyer_auctions()
    {
        $auction = $this->buyerAuction();
        $user    = User::factory()->create();

        ShowingAvailability::create([
            'offer_auction_id' => $auction->id,
            'user_id'          => $user->id,
            'available_date'   => '2026-08-03',
            'start_time'       => '11:00:00',
            'end_time'         => '12:00:00',
        ]);

        $results = ShowingAvailability::forEligibleListings()->get();
        $this->assertFalse($results->contains('offer_auction_id', $auction->id));
    }

    public function test_scope_for_eligible_listings_excludes_tenant_auctions()
    {
        $auction = $this->tenantAuction();
        $user    = User::factory()->create();

        ShowingAvailability::create([
            'offer_auction_id' => $auction->id,
            'user_id'          => $user->id,
            'available_date'   => '2026-08-04',
            'start_time'       => '12:00:00',
            'end_time'         => '13:00:00',
        ]);

        $results = ShowingAvailability::forEligibleListings()->get();
        $this->assertFalse($results->contains('offer_auction_id', $auction->id));
    }

    // -------------------------------------------------------------------------
    // OfferAuction scopeShowingEligible
    // -------------------------------------------------------------------------

    public function test_offer_auction_scope_showing_eligible_includes_seller()
    {
        $auction = $this->sellerAuction();

        $found = OfferAuction::showingEligible()->where('id', $auction->id)->exists();
        $this->assertTrue($found);
    }

    public function test_offer_auction_scope_showing_eligible_includes_landlord()
    {
        $auction = $this->landlordAuction();

        $found = OfferAuction::showingEligible()->where('id', $auction->id)->exists();
        $this->assertTrue($found);
    }

    public function test_offer_auction_scope_showing_eligible_excludes_buyer()
    {
        $auction = $this->buyerAuction();

        $found = OfferAuction::showingEligible()->where('id', $auction->id)->exists();
        $this->assertFalse($found);
    }

    public function test_offer_auction_scope_showing_eligible_excludes_tenant()
    {
        $auction = $this->tenantAuction();

        $found = OfferAuction::showingEligible()->where('id', $auction->id)->exists();
        $this->assertFalse($found);
    }

    // -------------------------------------------------------------------------
    // OfferAuction relationships
    // -------------------------------------------------------------------------

    public function test_offer_auction_has_many_showing_availabilities()
    {
        $auction = $this->sellerAuction();
        $user    = User::factory()->create();

        ShowingAvailability::create([
            'offer_auction_id' => $auction->id,
            'user_id'          => $user->id,
            'available_date'   => '2026-08-10',
            'start_time'       => '09:00:00',
            'end_time'         => '10:00:00',
        ]);

        ShowingAvailability::create([
            'offer_auction_id' => $auction->id,
            'user_id'          => $user->id,
            'available_date'   => '2026-08-11',
            'start_time'       => '14:00:00',
            'end_time'         => '15:00:00',
        ]);

        $this->assertCount(2, $auction->showingAvailabilities()->get());
    }

    public function test_offer_auction_has_many_showings()
    {
        $auction   = $this->landlordAuction();
        $requester = User::factory()->create();

        Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => '2026-08-12',
            'requested_start_time' => '10:00:00',
            'requested_end_time'   => '11:00:00',
            'status'               => ShowingStatus::REQUESTED,
        ]);

        $this->assertCount(1, $auction->showings()->get());
    }

    // -------------------------------------------------------------------------
    // Showing scopes
    // -------------------------------------------------------------------------

    public function test_scope_pending_returns_only_requested_showings()
    {
        $auction   = $this->sellerAuction();
        $requester = User::factory()->create();

        $requested = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => '2026-08-15',
            'requested_start_time' => '09:00:00',
            'requested_end_time'   => '10:00:00',
            'status'               => ShowingStatus::REQUESTED,
        ]);

        $approved = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => '2026-08-16',
            'requested_start_time' => '10:00:00',
            'requested_end_time'   => '11:00:00',
            'status'               => ShowingStatus::APPROVED,
        ]);

        $pending = Showing::pending()->forListing($auction->id)->get();

        $this->assertTrue($pending->contains('id', $requested->id));
        $this->assertFalse($pending->contains('id', $approved->id));
    }

    public function test_scope_active_returns_approved_and_requested()
    {
        $auction   = $this->sellerAuction();
        $requester = User::factory()->create();

        $requested = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => '2026-08-17',
            'requested_start_time' => '09:00:00',
            'requested_end_time'   => '10:00:00',
            'status'               => ShowingStatus::REQUESTED,
        ]);

        $approved = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => '2026-08-18',
            'requested_start_time' => '10:00:00',
            'requested_end_time'   => '11:00:00',
            'status'               => ShowingStatus::APPROVED,
        ]);

        $declined = Showing::create([
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => '2026-08-19',
            'requested_start_time' => '11:00:00',
            'requested_end_time'   => '12:00:00',
            'status'               => ShowingStatus::DECLINED,
        ]);

        $active = Showing::active()->forListing($auction->id)->get();

        $this->assertTrue($active->contains('id', $requested->id));
        $this->assertTrue($active->contains('id', $approved->id));
        $this->assertFalse($active->contains('id', $declined->id));
    }

    public function test_scope_for_listing_filters_by_offer_auction_id()
    {
        $auction1  = $this->sellerAuction();
        $auction2  = $this->landlordAuction();
        $requester = User::factory()->create();

        $showing1 = Showing::create([
            'offer_auction_id'     => $auction1->id,
            'requester_id'         => $requester->id,
            'requested_date'       => '2026-08-20',
            'requested_start_time' => '09:00:00',
            'requested_end_time'   => '10:00:00',
            'status'               => ShowingStatus::REQUESTED,
        ]);

        $showing2 = Showing::create([
            'offer_auction_id'     => $auction2->id,
            'requester_id'         => $requester->id,
            'requested_date'       => '2026-08-21',
            'requested_start_time' => '10:00:00',
            'requested_end_time'   => '11:00:00',
            'status'               => ShowingStatus::REQUESTED,
        ]);

        $forListing1 = Showing::forListing($auction1->id)->get();

        $this->assertTrue($forListing1->contains('id', $showing1->id));
        $this->assertFalse($forListing1->contains('id', $showing2->id));
    }

    // -------------------------------------------------------------------------
    // Status helper methods — mutual exclusivity
    // -------------------------------------------------------------------------

    public function test_status_helpers_are_mutually_exclusive()
    {
        $auction   = $this->sellerAuction();
        $requester = User::factory()->create();

        $base = [
            'offer_auction_id'     => $auction->id,
            'requester_id'         => $requester->id,
            'requested_date'       => '2026-08-22',
            'requested_start_time' => '09:00:00',
            'requested_end_time'   => '10:00:00',
        ];

        $showing = Showing::create(array_merge($base, ['status' => ShowingStatus::APPROVED]));

        $this->assertFalse($showing->isRequested());
        $this->assertTrue($showing->isApproved());
        $this->assertFalse($showing->isDeclined());
        $this->assertFalse($showing->isCanceled());
        $this->assertFalse($showing->isCompleted());
    }
}
