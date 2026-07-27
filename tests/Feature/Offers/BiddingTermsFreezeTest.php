<?php

namespace Tests\Feature\Offers;

use App\Models\LandlordAgentAuction;
use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\Offers\BiddingWindowService;
use App\Services\Offers\ListingOfferAuctionLinker;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Auction terms freeze once a listing is live AND has a submitted offer.
 *
 * Exercises the trait's decision logic directly rather than driving the full
 * Livewire wizards — the rule under test is "may these two fields change?",
 * and routing it through a multi-tab form would test the form, not the rule.
 */
class BiddingTermsFreezeTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Minimal host for the concern under test, standing in for the edit wizards.
     */
    private function harness(string $auctionType, string $auctionTime): object
    {
        return new class($auctionType, $auctionTime) {
            use \App\Http\Livewire\OfferListing\Concerns\StampsBiddingActivation;

            public function __construct(public $auction_type, public $auction_time) {}

            public function frozen(Model $listing, string $role): bool
            {
                return $this->biddingTermsAreFrozen($listing, $role);
            }

            public function resolve(Model $listing, string $role): array
            {
                return $this->resolveAuctionTermsForSave($listing, $role);
            }

            public function stamp(Model $listing, string $role): void
            {
                $this->stampBiddingActivation($listing, $role);
            }
        };
    }

    private function sellerListing(bool $isDraft = false): array
    {
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $listing = SellerAgentAuction::create([
            'user_id' => $owner->id, 'title' => 'Freeze Listing',
            'is_draft' => $isDraft, 'is_approved' => !$isDraft, 'is_sold' => false,
        ]);
        $listing->saveMeta('workflow_type', 'offer_listing');
        $listing->saveMeta('auction_type', 'Bidding Period');
        $listing->saveMeta('auction_time', '14 Days');

        $offerAuction = OfferAuction::factory()->create([
            'user_id'            => $owner->id,
            'bidding_started_at' => CarbonImmutable::now()->subDay(),
        ]);
        $listing->saveMeta('linked_offer_auction_id', $offerAuction->id);

        return [$listing->fresh('meta'), $offerAuction, $owner];
    }

    private function submitBid(OfferAuction $auction): Offer
    {
        return Offer::factory()->submitted()->create([
            'user_id'          => User::factory()->create(['user_type' => 'buyer'])->id,
            'offer_auction_id' => $auction->id,
            'role'             => 'buyer',
        ]);
    }

    // -------------------------------------------------------------- frozen

    public function test_terms_are_frozen_once_a_live_listing_has_a_submitted_offer(): void
    {
        [$listing, $offerAuction] = $this->sellerListing();
        $this->submitBid($offerAuction);

        $this->assertTrue($this->harness('Bidding Period', '3 Days')->frozen($listing, 'seller'));
    }

    public function test_frozen_listing_keeps_its_stored_auction_time(): void
    {
        [$listing, $offerAuction] = $this->sellerListing();
        $this->submitBid($offerAuction);

        // Form posts a much shorter window; the stored value must survive.
        [$type, $time] = $this->harness('Bidding Period', '1 Days')->resolve($listing, 'seller');

        $this->assertSame('Bidding Period', $type);
        $this->assertSame('14 Days', $time);
    }

    public function test_frozen_listing_cannot_be_switched_to_traditional(): void
    {
        [$listing, $offerAuction] = $this->sellerListing();
        $this->submitBid($offerAuction);

        [$type, $time] = $this->harness('Traditional', '')->resolve($listing, 'seller');

        $this->assertSame('Bidding Period', $type);
        $this->assertSame('14 Days', $time);
    }

    public function test_frozen_terms_keep_the_deadline_stationary(): void
    {
        [$listing, $offerAuction] = $this->sellerListing();
        $this->submitBid($offerAuction);

        $before = app(BiddingWindowService::class)->for($listing, $offerAuction)->endsAt;

        // Simulate the edit wizard saving with a tampered/stale shorter length.
        [$type, $time] = $this->harness('Bidding Period', '1 Days')->resolve($listing, 'seller');
        $listing->saveMeta('auction_type', $type);
        $listing->saveMeta('auction_time', $time);

        $after = app(BiddingWindowService::class)->for($listing->fresh('meta'), $offerAuction)->endsAt;

        $this->assertSame($before->toDateTimeString(), $after->toDateTimeString());
    }

    // ------------------------------------------------------------ editable

    public function test_terms_are_editable_while_the_listing_is_a_draft(): void
    {
        [$listing, $offerAuction] = $this->sellerListing(isDraft: true);
        $this->submitBid($offerAuction);

        $this->assertFalse($this->harness('Bidding Period', '3 Days')->frozen($listing, 'seller'));

        [, $time] = $this->harness('Bidding Period', '3 Days')->resolve($listing, 'seller');
        $this->assertSame('3 Days', $time);
    }

    public function test_terms_are_editable_on_a_live_listing_with_no_bids(): void
    {
        [$listing] = $this->sellerListing();

        $this->assertFalse($this->harness('Bidding Period', '3 Days')->frozen($listing, 'seller'));

        [, $time] = $this->harness('Bidding Period', '3 Days')->resolve($listing, 'seller');
        $this->assertSame('3 Days', $time);
    }

    public function test_a_draft_offer_alone_does_not_freeze_the_terms(): void
    {
        [$listing, $offerAuction] = $this->sellerListing();

        Offer::factory()->create([
            'user_id'          => User::factory()->create(['user_type' => 'buyer'])->id,
            'offer_auction_id' => $offerAuction->id,
            'role'             => 'buyer',
            'status'           => 'draft',
        ]);

        $this->assertFalse($this->harness('Bidding Period', '3 Days')->frozen($listing, 'seller'));
    }

    // ------------------------------------------- freeze survives terminal bids

    /**
     * The freeze must outlive the bid that caused it. Otherwise an owner could
     * withdraw-or-reject their way back into an editable window and move the
     * deadline after the fact.
     *
     * @dataProvider terminalRootStatusProvider
     */
    public function test_freeze_survives_a_root_offer_reaching_a_terminal_state(string $status): void
    {
        [$listing, $offerAuction] = $this->sellerListing();

        Offer::factory()->create([
            'user_id'          => User::factory()->create(['user_type' => 'buyer'])->id,
            'offer_auction_id' => $offerAuction->id,
            'role'             => 'buyer',
            'status'           => $status,
            'submitted_at'     => CarbonImmutable::now()->subHours(3),
        ]);

        $this->assertTrue(
            $this->harness('Bidding Period', '3 Days')->frozen($listing, 'seller'),
            "A root offer in '{$status}' still proves a genuine bid existed; terms must stay frozen.",
        );

        [, $time] = $this->harness('Bidding Period', '1 Days')->resolve($listing, 'seller');
        $this->assertSame('14 Days', $time);
    }

    public static function terminalRootStatusProvider(): array
    {
        return [
            'withdrawn' => ['withdrawn'],
            'rejected'  => ['rejected'],
            'expired'   => ['expired'],
        ];
    }

    public function test_freeze_is_scoped_to_root_offers(): void
    {
        [$listing, $offerAuction] = $this->sellerListing();

        // A counter with no root is not a distinct bidder entering the contest.
        // (Orphan child rows should not occur, but the predicate must be exact.)
        $root = Offer::factory()->create([
            'user_id'          => User::factory()->create(['user_type' => 'buyer'])->id,
            'offer_auction_id' => $offerAuction->id,
            'role'             => 'buyer',
            'status'           => 'draft',
        ]);

        Offer::factory()->create([
            'user_id'          => User::factory()->create(['user_type' => 'seller'])->id,
            'offer_auction_id' => $offerAuction->id,
            'parent_offer_id'  => $root->id,
            'role'             => 'seller',
            'status'           => 'submitted',
            'submitted_at'     => CarbonImmutable::now()->subHour(),
        ]);

        // Root is still a draft — no genuine submission entered the contest.
        $this->assertFalse($this->harness('Bidding Period', '3 Days')->frozen($listing, 'seller'));
    }

    public function test_landlord_freeze_survives_a_withdrawn_root_offer(): void
    {
        $owner   = User::factory()->create(['user_type' => 'landlord']);
        $listing = LandlordAgentAuction::create([
            'user_id' => $owner->id, 'title' => 'Landlord Freeze',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);
        $listing->saveMeta('auction_type', 'Bidding Period');
        $listing->saveMeta('auction_time', '10 Days');

        $offerAuction = OfferAuction::factory()->create([
            'user_id'            => $owner->id,
            'bidding_started_at' => CarbonImmutable::now()->subDay(),
        ]);
        $listing->saveMeta('linked_offer_auction_id', $offerAuction->id);
        $listing = $listing->fresh('meta');

        Offer::factory()->create([
            'user_id'          => User::factory()->create(['user_type' => 'tenant'])->id,
            'offer_auction_id' => $offerAuction->id,
            'role'             => 'tenant',
            'status'           => 'withdrawn',
            'submitted_at'     => CarbonImmutable::now()->subHours(2),
        ]);

        $this->assertTrue($this->harness('Bidding Period', '2 Days')->frozen($listing, 'landlord'));

        [, $time] = $this->harness('Bidding Period', '2 Days')->resolve($listing, 'landlord');
        $this->assertSame('10 Days', $time);
    }

    // ------------------------------------------------------ activation path

    public function test_publishing_stamps_the_window_and_republishing_does_not_restart_it(): void
    {
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $listing = SellerAgentAuction::create([
            'user_id' => $owner->id, 'title' => 'Activation Listing',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);
        $listing->saveMeta('auction_type', 'Bidding Period');
        $listing->saveMeta('auction_time', '14 Days');
        $listing = $listing->fresh('meta');

        $harness = $this->harness('Bidding Period', '14 Days');

        $harness->stamp($listing, 'seller');

        $offerAuction = app(ListingOfferAuctionLinker::class)->resolve($listing->fresh('meta'));
        $this->assertNotNull($offerAuction, 'Publishing must link an OfferAuction to stamp.');

        $first = $offerAuction->fresh()->bidding_started_at;
        $this->assertNotNull($first);

        // Re-publish / re-save.
        $harness->stamp($listing->fresh('meta'), 'seller');

        $this->assertSame(
            $first->toDateTimeString(),
            $offerAuction->fresh()->bidding_started_at->toDateTimeString(),
        );
    }

    public function test_publishing_a_traditional_listing_does_not_stamp_a_window(): void
    {
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $listing = SellerAgentAuction::create([
            'user_id' => $owner->id, 'title' => 'Traditional Listing',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);
        $listing->saveMeta('auction_type', 'Traditional');
        $listing = $listing->fresh('meta');

        $this->harness('Traditional', '')->stamp($listing, 'seller');

        $offerAuction = app(ListingOfferAuctionLinker::class)->resolve($listing->fresh('meta'));

        $this->assertNull($offerAuction?->bidding_started_at);
    }

    public function test_landlord_activation_creates_a_correctly_shaped_offer_auction(): void
    {
        $owner   = User::factory()->create(['user_type' => 'landlord']);
        $listing = LandlordAgentAuction::create([
            'user_id' => $owner->id, 'title' => 'Landlord Activation',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);
        $listing->saveMeta('auction_type', 'Bidding Period');
        $listing->saveMeta('auction_time', '7 Days');
        $listing = $listing->fresh('meta');

        $this->harness('Bidding Period', '7 Days')->stamp($listing, 'landlord');

        $offerAuction = app(ListingOfferAuctionLinker::class)->resolve($listing->fresh('meta'));

        $this->assertNotNull($offerAuction);
        $this->assertNotNull($offerAuction->bidding_started_at);
        // Landlord rows must keep the metas the offer detail page relies on.
        $this->assertSame('rental', $offerAuction->info('offer_type'));
        $this->assertSame((string) $listing->id, (string) $offerAuction->info('linked_landlord_auction_id'));
    }
}
