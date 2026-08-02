<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\Offers\BiddingWindowService;
use App\Services\Offers\OfferAvailableActionsService;
use App\Services\Offers\PublicOfferFeedService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression lock: a lapsed response deadline must not erase a bid.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS SUITE EXISTS
 * ---------------------------------------------------------------------------
 * Two independent clocks govern a timed listing, and Requirement 7 of the
 * approved architecture forbids merging them:
 *
 *   offer_auctions.bidding_ends_at   the LISTING's bidding window — controls
 *                                    whether new bids may be submitted.
 *   offers.expires_at                an INDIVIDUAL offer's "respond by" date,
 *                                    addressed to the listing owner.
 *
 * The owner feed previously admitted only ['submitted','countered','accepted'].
 * Because expires_at is mandatory on every submit (OfferController::submit)
 * and `offers:expire-pending` sweeps every minute (Kernel::schedule), every bid
 * on the platform was guaranteed to eventually flip to 'expired' and silently
 * vanish from the owner's comparison feed — while its listing's bidding window
 * was still open. The owner lost candidates mid-auction with no gap and no
 * notice, because bidder numbering already skipped expired rows correctly.
 *
 * Expiring an offer may change its status and its available actions. It must
 * not unmake the bid. See the Regression Reopening section of
 * TIMED_OFFER_RUNTIME_INVESTIGATION.md.
 */
class ExpiredOfferFeedRetentionTest extends TestCase
{
    use DatabaseTransactions;

    private PublicOfferFeedService $feed;

    protected function setUp(): void
    {
        parent::setUp();
        $this->feed = app(PublicOfferFeedService::class);
        $this->app['config']->set('offer.playoff_access.allowed_user_ids', '*');
    }

    // ------------------------------------------------------------- fixtures

    /**
     * A published Bidding Period seller listing whose window is OPEN, linked to
     * its canonical OfferAuction.
     *
     * expiration_date is deliberately set to a wildly different date so any
     * accidental reintroduction of the prohibited fallback is visible rather
     * than plausible.
     *
     * @return array{0: SellerAgentAuction, 1: OfferAuction}
     */
    private function openListing(User $owner): array
    {
        $listing = SellerAgentAuction::create([
            'user_id'  => $owner->id,
            'title'    => 'Expired Retention Fixture',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);
        $listing->saveMeta('workflow_type', 'offer_listing');
        $listing->saveMeta('auction_type', 'Bidding Period');
        $listing->saveMeta('auction_time', '7 Days');
        $listing->saveMeta('expiration_date', '2027-01-31');

        $offerAuction = OfferAuction::factory()->create([
            'user_id'           => $owner->id,
            'bidding_starts_at' => CarbonImmutable::now()->subDays(2),
            'bidding_ends_at'   => CarbonImmutable::now()->addDays(5),
        ]);
        $listing->saveMeta('linked_offer_auction_id', $offerAuction->id);

        return [$listing->fresh('meta'), $offerAuction];
    }

    private function offerWithStatus(
        OfferAuction $auction,
        string $status,
        CarbonImmutable $submittedAt,
        ?CarbonImmutable $expiresAt = null,
        array $metas = [],
    ): Offer {
        $offer = Offer::factory()->create([
            'user_id'          => User::factory()->create(['user_type' => 'buyer'])->id,
            'offer_auction_id' => $auction->id,
            'role'             => 'buyer',
            'status'           => $status,
            'submitted_at'     => $submittedAt,
            'expires_at'       => $expiresAt,
        ]);

        foreach ($metas as $k => $v) {
            $offer->saveMeta($k, $v);
        }

        return $offer->fresh('metas');
    }

    // =====================================================================
    // B1 — an expired offer remains visible in the owner feed.
    // =====================================================================

    public function test_b1_expired_offer_remains_in_the_owner_feed(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [$listing, $offerAuction] = $this->openListing($owner);

        $expired = $this->offerWithStatus(
            $offerAuction,
            'expired',
            CarbonImmutable::now()->subDays(2),
            CarbonImmutable::now()->subHour(),
            ['offer_price' => '525000', 'financing_type' => 'Conventional'],
        );

        $this->assertTrue(
            $this->feed->canView($owner, $listing, 'seller'),
            'The listing owner must be able to view their own offer feed.'
        );

        $rows = $this->feed->build($offerAuction, 'seller');

        $this->assertCount(
            1,
            $rows,
            'An offer whose response deadline lapsed must remain on the competitive record. '
            . 'expires_at is a response deadline, not a deletion instruction.'
        );
        $this->assertSame('525000', $rows[0]['terms']['offer_price'] ?? null);

        // The stored row is untouched — the feed widened, the data did not move.
        $this->assertSame('expired', $expired->fresh()->status);
    }

    // =====================================================================
    // B2 — the expired status is presented accurately, and sanitized.
    // =====================================================================

    public function test_b2_expired_offer_is_labelled_expired_and_not_as_a_live_status(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [, $offerAuction] = $this->openListing($owner);

        $this->offerWithStatus(
            $offerAuction,
            'expired',
            CarbonImmutable::now()->subDays(2),
            CarbonImmutable::now()->subHour(),
        );

        $row = $this->feed->build($offerAuction, 'seller')[0];

        $this->assertSame('Expired', $row['status']);

        foreach (['Submitted', 'Countered', 'Accepted'] as $liveLabel) {
            $this->assertNotSame(
                $liveLabel,
                $row['status'],
                'An expired offer must never be presented as still awaiting a response.'
            );
        }

        // Sanitized label, not the raw internal state name.
        $this->assertNotSame('expired', $row['status']);
    }

    // =====================================================================
    // B3 — THE KEY ASSERTION.
    //      An open bidding window plus a lapsed individual deadline: the owner
    //      sees BOTH offers. This is the interaction that was never tested and
    //      that allowed the defect to ship.
    // =====================================================================

    public function test_b3_open_bidding_window_still_shows_an_offer_whose_own_deadline_lapsed(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [$listing, $offerAuction] = $this->openListing($owner);

        $live = $this->offerWithStatus(
            $offerAuction,
            'submitted',
            CarbonImmutable::now()->subDays(2),
            CarbonImmutable::now()->addDays(3),
            ['offer_price' => '500000'],
        );

        $lapsed = $this->offerWithStatus(
            $offerAuction,
            'expired',
            CarbonImmutable::now()->subDay(),
            CarbonImmutable::now()->subHours(2),
            ['offer_price' => '515000'],
        );

        // Precondition: the LISTING's window is genuinely still open.
        $window = app(BiddingWindowService::class)->for($listing, $offerAuction);
        $this->assertTrue($window->isCanonical(), 'Fixture must carry a canonical window.');
        $this->assertFalse(
            $window->isClosed(),
            'Precondition: the listing bidding window must still be open for this test to mean anything.'
        );

        $rows = $this->feed->build($offerAuction, 'seller');

        $this->assertCount(
            2,
            $rows,
            'The owner must see BOTH offers. One bidder\'s lapsed response deadline says nothing '
            . 'about the listing\'s bidding window and must not remove them from comparison.'
        );

        $prices = array_map(fn ($r) => $r['terms']['offer_price'] ?? null, $rows);
        sort($prices);
        $this->assertSame(['500000', '515000'], $prices);

        $statuses = array_column($rows, 'status');
        sort($statuses);
        $this->assertSame(['Expired', 'Submitted'], $statuses);

        unset($live, $lapsed);
    }

    // =====================================================================
    // B4 — an expired offer exposes no action that is no longer valid.
    //      Tests the real product rules (OfferAvailableActionsService), not a
    //      restatement of them.
    // =====================================================================

    public function test_b4_expired_offer_exposes_no_invalid_actions_to_the_owner(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [, $offerAuction] = $this->openListing($owner);

        $expired = $this->offerWithStatus(
            $offerAuction,
            'expired',
            CarbonImmutable::now()->subDays(2),
            CarbonImmutable::now()->subHour(),
        );

        $actions = app(OfferAvailableActionsService::class)
            ->forOffer($expired, $owner->id, 'seller');

        foreach (['can_submit', 'can_counter', 'can_accept', 'can_reject', 'can_withdraw', 'can_cancel', 'can_expire'] as $capability) {
            $this->assertFalse(
                $actions[$capability],
                "[{$capability}] must be unavailable on an expired offer — its response period has closed."
            );
        }

        // History stays readable: retaining the bid is the entire point.
        $this->assertTrue(
            $actions['can_view_timeline'],
            'The owner must still be able to inspect an expired offer\'s history.'
        );
    }

    public function test_b4_expiring_does_not_revert_the_offer_to_a_live_status(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [, $offerAuction] = $this->openListing($owner);

        $expired = $this->offerWithStatus(
            $offerAuction,
            'expired',
            CarbonImmutable::now()->subDays(2),
            CarbonImmutable::now()->subHour(),
        );

        $this->feed->build($offerAuction, 'seller');

        $this->assertSame(
            'expired',
            $expired->fresh()->status,
            'Rendering the feed must not mutate offer status. The fix widens what is displayed, '
            . 'never what is stored.'
        );
    }

    // =====================================================================
    // B5 — only drafts are excluded.
    //
    //      SUPERSEDED 2026-07-29: this test previously asserted that withdrawn
    //      and rejected bids stay hidden. The permanent submitted-bid history
    //      rule replaced that — once validly submitted, a bid never disappears.
    //      Full coverage lives in SubmittedBidHistoryTest; retained here so the
    //      supersession is visible where the old rule was written.
    // =====================================================================

    public function test_b5_finalized_offers_remain_visible_alongside_the_expired_one(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [, $offerAuction] = $this->openListing($owner);

        $this->offerWithStatus($offerAuction, 'withdrawn', CarbonImmutable::now()->subHours(9));
        $this->offerWithStatus($offerAuction, 'rejected',  CarbonImmutable::now()->subHours(8));
        $this->offerWithStatus($offerAuction, 'expired',   CarbonImmutable::now()->subHours(7));

        $rows = $this->feed->build($offerAuction, 'seller');

        $this->assertCount(
            3,
            $rows,
            'A validly submitted bid stays in bidding history whatever its terminal status.'
        );
        $this->assertSame(
            ['Withdrawn', 'Rejected', 'Expired'],
            array_column($rows, 'status'),
            'Each finalized bid reports its own accurate status, in bidder order.'
        );
    }

    public function test_b5_draft_offers_remain_invisible(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [, $offerAuction] = $this->openListing($owner);

        $this->offerWithStatus($offerAuction, 'draft', CarbonImmutable::now()->subHours(3));

        $this->assertSame(
            [],
            $this->feed->build($offerAuction, 'seller'),
            'An unsubmitted draft is not a competing bid and stays private to its author.'
        );
    }

    public function test_b5_expired_offer_still_honours_the_privacy_allow_list(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [, $offerAuction] = $this->openListing($owner);

        $this->offerWithStatus(
            $offerAuction,
            'expired',
            CarbonImmutable::now()->subDay(),
            CarbonImmutable::now()->subHour(),
            [
                'offer_price'   => '499000',   // allow-listed
                'notes'         => 'PRIVATE NOTE',
                'custom_terms'  => 'PRIVATE TERMS',
                'contact_email' => 'bidder@example.com',
            ],
        );

        $row     = $this->feed->build($offerAuction, 'seller')[0];
        $encoded = json_encode($row);

        $this->assertSame('499000', $row['terms']['offer_price']);

        foreach (['PRIVATE NOTE', 'PRIVATE TERMS', 'bidder@example.com'] as $secret) {
            $this->assertStringNotContainsString(
                $secret,
                $encoded,
                'Retaining an expired bid must not widen what that bid discloses.'
            );
        }

        // Anonymity: no identity, no raw ids.
        $this->assertArrayNotHasKey('user_id', $row);
        $this->assertArrayNotHasKey('offer_id', $row);
        $this->assertArrayHasKey('bidder_number', $row);
    }

    // =====================================================================
    // B6 — bidder numbering is unaffected by an expiry.
    // =====================================================================

    public function test_b6_expiring_an_offer_does_not_renumber_surviving_bidders(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [, $offerAuction] = $this->openListing($owner);

        $first  = $this->offerWithStatus($offerAuction, 'submitted', CarbonImmutable::now()->subHours(9));
        $second = $this->offerWithStatus($offerAuction, 'submitted', CarbonImmutable::now()->subHours(6));
        $third  = $this->offerWithStatus($offerAuction, 'submitted', CarbonImmutable::now()->subHours(3));

        $before = array_column($this->feed->build($offerAuction, 'seller'), 'bidder_number');
        $this->assertSame([1, 2, 3], $before);

        // The middle bidder's response window lapses.
        $second->update(['status' => 'expired', 'expires_at' => CarbonImmutable::now()->subMinute()]);

        $after = $this->feed->build($offerAuction, 'seller');

        $this->assertSame(
            [1, 2, 3],
            array_column($after, 'bidder_number'),
            'An expiry must not renumber anyone: the expired bidder keeps #2 and the survivors '
            . 'keep #1 and #3.'
        );

        $byNumber = array_column($after, 'status', 'bidder_number');
        $this->assertSame('Expired', $byNumber[2]);
        $this->assertSame('Submitted', $byNumber[1]);
        $this->assertSame('Submitted', $byNumber[3]);

        unset($first, $third);
    }

    public function test_b6_numbering_stays_stable_when_the_first_bidder_expires(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [, $offerAuction] = $this->openListing($owner);

        $this->offerWithStatus(
            $offerAuction,
            'expired',
            CarbonImmutable::now()->subHours(9),
            CarbonImmutable::now()->subHour(),
        );
        $this->offerWithStatus($offerAuction, 'submitted', CarbonImmutable::now()->subHours(4));

        $rows = $this->feed->build($offerAuction, 'seller');

        $this->assertSame([1, 2], array_column($rows, 'bidder_number'));
        $this->assertSame(['Expired', 'Submitted'], array_column($rows, 'status'));
    }

    // =====================================================================
    // B7 — timer independence. Rendering an expired offer changes nothing
    //      about the listing's countdown, which stays sourced exclusively from
    //      the persisted bidding window (Invariants 1, 2, 3, 4, 5, 10).
    // =====================================================================

    public function test_b7_expired_offer_does_not_affect_the_listing_countdown(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [$listing, $offerAuction] = $this->openListing($owner);

        $windows  = app(BiddingWindowService::class);
        $expected = $offerAuction->bidding_ends_at;

        $before = $windows->for($listing, $offerAuction);

        $this->offerWithStatus(
            $offerAuction,
            'expired',
            CarbonImmutable::now()->subDay(),
            // A response deadline far outside the bidding window in both
            // directions would be visible if it ever leaked into the countdown.
            CarbonImmutable::now()->subDays(30),
        );

        $this->feed->build($offerAuction, 'seller');

        $after = $windows->for($listing->fresh('meta'), $offerAuction->fresh());

        $this->assertSame(
            $expected->toDateTimeString(),
            $after->endsAt->toDateTimeString(),
            'The countdown must continue to read the persisted bidding_ends_at, unchanged.'
        );
        $this->assertSame($before->endsAt->toDateTimeString(), $after->endsAt->toDateTimeString());
        $this->assertFalse($after->isClosed(), 'A lapsed offer deadline must not close the listing window.');
        $this->assertGreaterThan(0, $after->remainingSeconds());
    }

    public function test_b7_countdown_ignores_listing_expiration_date_even_with_an_expired_offer(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [$listing, $offerAuction] = $this->openListing($owner);

        // Fixture sets expiration_date to 2027-01-31 — ~5 days of bidding window
        // versus months of listing life. If the prohibited fallback ever returns,
        // remainingSeconds() jumps by orders of magnitude.
        $this->offerWithStatus(
            $offerAuction,
            'expired',
            CarbonImmutable::now()->subDay(),
            CarbonImmutable::now()->subHour(),
        );

        $window = app(BiddingWindowService::class)->for($listing, $offerAuction);

        $this->assertSame(
            $offerAuction->bidding_ends_at->toDateTimeString(),
            $window->endsAt->toDateTimeString(),
            'Invariant 10: expiration_date may never appear in the bidding timer path.'
        );

        $this->assertLessThan(
            CarbonImmutable::parse('2027-01-31')->getTimestamp(),
            $window->endsAt->getTimestamp(),
            'The deadline must be the bidding window, not the listing expiration date.'
        );
    }

    public function test_b7_an_uninitialized_window_still_renders_retained_offers(): void
    {
        // A legacy listing with no canonical window shows no countdown (Decision B).
        // That must not also suppress its offer history.
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $listing = SellerAgentAuction::create([
            'user_id'  => $owner->id,
            'title'    => 'Legacy Unstamped Listing',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);
        $listing->saveMeta('auction_type', 'Bidding Period');
        $listing->saveMeta('auction_time', '7 Days');

        $offerAuction = OfferAuction::factory()->create([
            'user_id'           => $owner->id,
            'bidding_starts_at' => CarbonImmutable::now()->subDays(400),
            'bidding_ends_at'   => null,
        ]);
        $listing->saveMeta('linked_offer_auction_id', $offerAuction->id);

        $this->offerWithStatus(
            $offerAuction,
            'expired',
            CarbonImmutable::now()->subDays(300),
            CarbonImmutable::now()->subDays(290),
        );

        $window = app(BiddingWindowService::class)->for($listing->fresh('meta'), $offerAuction);

        $this->assertTrue($window->isUninitialized(), 'No window may be invented for a legacy listing.');
        $this->assertFalse($window->hasDeadline(), 'No countdown may render.');

        $this->assertCount(
            1,
            $this->feed->build($offerAuction, 'seller'),
            'A missing countdown must not also erase the listing\'s offer history.'
        );
    }
}
