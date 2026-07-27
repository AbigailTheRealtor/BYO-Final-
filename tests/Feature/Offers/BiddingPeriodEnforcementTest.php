<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\Offers\OfferWorkflowFacade;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Server-side enforcement of the bidding deadline.
 *
 * The rule is asymmetric on purpose: once the window closes the BIDDER can no
 * longer start or submit anything, but the listing OWNER keeps every review
 * action (accept / counter / reject) so they can work through the bids that
 * arrived in time.
 */
class BiddingPeriodEnforcementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['config']->set('offer.playoff_access.allowed_user_ids', '*');
        Notification::fake();
    }

    /**
     * A Seller Bidding Period listing whose window opened $daysAgo days ago and
     * runs for $length.
     *
     * @return array{0: SellerAgentAuction, 1: OfferAuction, 2: User}
     */
    private function biddingListing(int $daysAgo, string $length = '7 Days'): array
    {
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $listing = SellerAgentAuction::create([
            'user_id' => $owner->id, 'title' => 'Enforcement Listing',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);
        $listing->saveMeta('workflow_type', 'offer_listing');
        $listing->saveMeta('auction_type', 'Bidding Period');
        $listing->saveMeta('auction_time', $length);

        $offerAuction = OfferAuction::factory()->create([
            'user_id'            => $owner->id,
            'bidding_started_at' => CarbonImmutable::now()->subDays($daysAgo),
        ]);
        $listing->saveMeta('linked_offer_auction_id', $offerAuction->id);

        return [$listing->fresh('meta'), $offerAuction, $owner];
    }

    private function buyer(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    private function draftOffer(OfferAuction $auction, User $buyer): Offer
    {
        return Offer::factory()->create([
            'user_id'          => $buyer->id,
            'offer_auction_id' => $auction->id,
            'role'             => 'buyer',
            'status'           => 'draft',
        ]);
    }

    // ------------------------------------------------------- draft creation

    public function test_bidder_cannot_create_a_draft_after_the_window_closes(): void
    {
        [, $offerAuction] = $this->biddingListing(daysAgo: 30);

        $response = $this->actingAs($this->buyer())
            ->postJson(route('offers.store'), [
                'offer_auction_id' => $offerAuction->id,
                'role'             => 'buyer',
            ]);

        $response->assertStatus(422);
        $this->assertSame(0, Offer::where('offer_auction_id', $offerAuction->id)->count());
    }

    public function test_bidder_can_create_a_draft_while_the_window_is_open(): void
    {
        [, $offerAuction] = $this->biddingListing(daysAgo: 1);

        $response = $this->actingAs($this->buyer())
            ->postJson(route('offers.store'), [
                'offer_auction_id' => $offerAuction->id,
                'role'             => 'buyer',
            ]);

        $response->assertStatus(201);
        $this->assertSame(1, Offer::where('offer_auction_id', $offerAuction->id)->count());
    }

    public function test_traditional_listings_are_unaffected_by_the_deadline_guard(): void
    {
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $listing = SellerAgentAuction::create([
            'user_id' => $owner->id, 'title' => 'Traditional Listing',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);
        $listing->saveMeta('workflow_type', 'offer_listing');
        $listing->saveMeta('auction_type', 'Traditional');

        $offerAuction = OfferAuction::factory()->create([
            'user_id'            => $owner->id,
            'bidding_started_at' => CarbonImmutable::now()->subYears(2),
        ]);
        $listing->saveMeta('linked_offer_auction_id', $offerAuction->id);

        $response = $this->actingAs($this->buyer())
            ->postJson(route('offers.store'), [
                'offer_auction_id' => $offerAuction->id,
                'role'             => 'buyer',
            ]);

        $response->assertStatus(201);
    }

    // ----------------------------------------------------------- submission

    public function test_submission_is_refused_after_the_window_closes(): void
    {
        [, $offerAuction] = $this->biddingListing(daysAgo: 30);
        $buyer = $this->buyer();
        $offer = $this->draftOffer($offerAuction, $buyer);

        $result = app(OfferWorkflowFacade::class)->submit($offer, $buyer->id, 'buyer');

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('bidding period', strtolower($result['reason']));
        $this->assertSame('draft', $offer->fresh()->status);
        $this->assertNull($offer->fresh()->submitted_at);
    }

    public function test_refused_submission_is_recorded_in_the_event_log(): void
    {
        [, $offerAuction] = $this->biddingListing(daysAgo: 30);
        $buyer = $this->buyer();
        $offer = $this->draftOffer($offerAuction, $buyer);

        app(OfferWorkflowFacade::class)->submit($offer, $buyer->id, 'buyer');

        $this->assertDatabaseHas('offer_event_logs', [
            'offer_id'   => $offer->id,
            'event_type' => 'forbidden_transition_attempt',
        ]);
    }

    public function test_submission_succeeds_while_the_window_is_open(): void
    {
        [, $offerAuction] = $this->biddingListing(daysAgo: 1);
        $buyer = $this->buyer();
        $offer = $this->draftOffer($offerAuction, $buyer);

        $result = app(OfferWorkflowFacade::class)->submit($offer, $buyer->id, 'buyer');

        $this->assertTrue($result['allowed']);
        $this->assertSame('submitted', $offer->fresh()->status);
    }

    public function test_permission_gate_hides_submit_once_the_window_closes(): void
    {
        [, $offerAuction] = $this->biddingListing(daysAgo: 30);
        $buyer = $this->buyer();
        $offer = $this->draftOffer($offerAuction, $buyer);

        $actions = app(\App\Services\Offers\OfferAvailableActionsService::class)
            ->forOffer($offer, $buyer->id, 'buyer');

        $this->assertFalse($actions['can_submit']);
    }

    // ------------------------------------------------- owner actions survive

    public function test_owner_can_still_accept_after_the_window_closes(): void
    {
        [, $offerAuction, $owner] = $this->biddingListing(daysAgo: 1);
        $buyer = $this->buyer();
        $offer = $this->draftOffer($offerAuction, $buyer);

        // Bid lands while the window is open.
        app(OfferWorkflowFacade::class)->submit($offer, $buyer->id, 'buyer');

        // Window then closes.
        $offerAuction->update(['bidding_started_at' => CarbonImmutable::now()->subDays(30)]);

        $result = app(OfferWorkflowFacade::class)->accept($offer->fresh(), $owner->id, 'seller');

        $this->assertTrue($result['allowed'], 'Owner must retain accept after the bidding window closes.');
        $this->assertSame('accepted', $offer->fresh()->status);
    }

    public function test_owner_can_still_reject_after_the_window_closes(): void
    {
        [, $offerAuction, $owner] = $this->biddingListing(daysAgo: 1);
        $buyer = $this->buyer();
        $offer = $this->draftOffer($offerAuction, $buyer);

        app(OfferWorkflowFacade::class)->submit($offer, $buyer->id, 'buyer');
        $offerAuction->update(['bidding_started_at' => CarbonImmutable::now()->subDays(30)]);

        $result = app(OfferWorkflowFacade::class)->reject($offer->fresh(), $owner->id, 'seller');

        $this->assertTrue($result['allowed'], 'Owner must retain reject after the bidding window closes.');
        $this->assertSame('rejected', $offer->fresh()->status);
    }

    public function test_owner_review_actions_remain_available_in_the_actions_service(): void
    {
        [, $offerAuction, $owner] = $this->biddingListing(daysAgo: 1);
        $buyer = $this->buyer();
        $offer = $this->draftOffer($offerAuction, $buyer);

        app(OfferWorkflowFacade::class)->submit($offer, $buyer->id, 'buyer');
        $offerAuction->update(['bidding_started_at' => CarbonImmutable::now()->subDays(30)]);

        $actions = app(\App\Services\Offers\OfferAvailableActionsService::class)
            ->forOffer($offer->fresh(), $owner->id, 'seller');

        $this->assertTrue($actions['can_accept']);
        $this->assertTrue($actions['can_reject']);
        $this->assertTrue($actions['can_counter']);
    }
}
