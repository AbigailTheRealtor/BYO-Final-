<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Presenters\OfferTermPresenter;
use App\Services\Offers\BiddingWindowService;
use App\Services\Offers\OfferAvailableActionsService;
use App\Services\Offers\PublicOfferFeedService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Permanent submitted-bid history, and display formatting for the bidding feed.
 *
 * ---------------------------------------------------------------------------
 * THE RULE (ratified 2026-07-29)
 * ---------------------------------------------------------------------------
 * Once a bid has been validly submitted, a later status change must not make it
 * disappear from bidding history. submitted, countered, accepted, expired,
 * rejected and withdrawn all remain visible; only 'draft' is excluded because a
 * draft was never a bid.
 *
 * Visibility is NOT actionability, and it is NOT ranking:
 *   - every finalized status is in OfferStateMachineService::FINAL_STATUSES, so
 *     OfferPermissionService already denies every transition on them;
 *   - the feed computes no "current high bid" — none exists on this surface.
 *     See the deferred Bidding Rules scope.
 *
 * The formatting half of this suite locks the storage conventions documented on
 * OfferTermPresenter. Getting those wrong silently misprices a listing, so they
 * are asserted rather than assumed.
 */
class SubmittedBidHistoryTest extends TestCase
{
    use DatabaseTransactions;

    private PublicOfferFeedService $feed;
    private OfferTermPresenter $terms;

    /** Every status that must survive in history, oldest-submitted first. */
    private const HISTORY_STATUSES = [
        'submitted'  => 'Submitted',
        'countered'  => 'Countered',
        'accepted'   => 'Accepted',
        'expired'    => 'Expired',
        'rejected'   => 'Rejected',
        'withdrawn'  => 'Withdrawn',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->feed  = app(PublicOfferFeedService::class);
        $this->terms = app(OfferTermPresenter::class);
        $this->app['config']->set('offer.playoff_access.allowed_user_ids', '*');
    }

    // ------------------------------------------------------------- fixtures

    /** @return array{0: SellerAgentAuction, 1: OfferAuction} */
    private function openListing(User $owner): array
    {
        $listing = SellerAgentAuction::create([
            'user_id'  => $owner->id,
            'title'    => 'Bid History Fixture',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);
        $listing->saveMeta('workflow_type', 'offer_listing');
        $listing->saveMeta('auction_type', 'Bidding Period');
        $listing->saveMeta('auction_time', '7 Days');
        $listing->saveMeta('expiration_date', '2027-03-31');

        $offerAuction = OfferAuction::factory()->create([
            'user_id'           => $owner->id,
            'bidding_starts_at' => CarbonImmutable::now()->subDays(2),
            'bidding_ends_at'   => CarbonImmutable::now()->addDays(5),
        ]);
        $listing->saveMeta('linked_offer_auction_id', $offerAuction->id);

        return [$listing->fresh('meta'), $offerAuction];
    }

    private function bid(
        OfferAuction $auction,
        string $status,
        CarbonImmutable $submittedAt,
        array $metas = [],
        ?CarbonImmutable $expiresAt = null,
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

    /** One bid per history status, submitted an hour apart in declaration order. */
    private function oneBidPerStatus(OfferAuction $auction): array
    {
        $created = [];
        $hours   = count(self::HISTORY_STATUSES) + 1;

        foreach (array_keys(self::HISTORY_STATUSES) as $status) {
            $created[$status] = $this->bid(
                $auction,
                $status,
                CarbonImmutable::now()->subHours($hours--),
                ['offer_price' => '500000'],
            );
        }

        return $created;
    }

    // =====================================================================
    // 1-8. Visibility and accurate status.
    // =====================================================================

    /** Tests 1-6: every validly submitted status survives in history. */
    public function test_every_submitted_status_remains_visible_in_history(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [, $offerAuction] = $this->openListing($owner);

        $this->oneBidPerStatus($offerAuction);

        $rows = $this->feed->build($offerAuction, 'seller');

        $this->assertCount(
            count(self::HISTORY_STATUSES),
            $rows,
            'Every validly submitted bid stays in bidding history regardless of its terminal status.'
        );
    }

    /** Test 8: each bid reports its own accurate, sanitized label. */
    public function test_each_bid_displays_its_accurate_status_label(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [, $offerAuction] = $this->openListing($owner);

        $this->oneBidPerStatus($offerAuction);

        $labels = array_column($this->feed->build($offerAuction, 'seller'), 'status');

        $this->assertSame(
            array_values(self::HISTORY_STATUSES),
            $labels,
            'Statuses must be reported accurately and in bidder order.'
        );

        // Sanitized: no internal state name leaks through.
        foreach (array_keys(self::HISTORY_STATUSES) as $internal) {
            $this->assertNotContains($internal, $labels);
        }
    }

    /** Test 7: drafts were never bids. */
    public function test_draft_and_never_submitted_offers_remain_excluded(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [, $offerAuction] = $this->openListing($owner);

        $this->bid($offerAuction, 'draft', CarbonImmutable::now()->subHours(5));

        // A never-submitted row: draft status and no submitted_at at all.
        Offer::factory()->create([
            'user_id'          => User::factory()->create(['user_type' => 'buyer'])->id,
            'offer_auction_id' => $offerAuction->id,
            'role'             => 'buyer',
            'status'           => 'draft',
            'submitted_at'     => null,
        ]);

        $this->assertSame(
            [],
            $this->feed->build($offerAuction, 'seller'),
            'A draft is not a bid and its existence is private to its author.'
        );
    }

    // =====================================================================
    // 9-11. Visibility does not restore actions.
    // =====================================================================

    /**
     * @dataProvider finalizedStatusProvider
     */
    public function test_finalized_bids_expose_no_invalid_actions(string $status): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [, $offerAuction] = $this->openListing($owner);

        $offer = $this->bid($offerAuction, $status, CarbonImmutable::now()->subHours(4));

        $actions = app(OfferAvailableActionsService::class)->forOffer($offer, $owner->id, 'seller');

        foreach (['can_submit', 'can_counter', 'can_accept', 'can_reject', 'can_withdraw', 'can_cancel', 'can_expire'] as $capability) {
            $this->assertFalse(
                $actions[$capability],
                "[{$status}] {$capability} must stay unavailable — showing a finalized bid does not resurrect it."
            );
        }

        // History remains readable; that is the whole point of retaining it.
        $this->assertTrue($actions['can_view_timeline']);

        // And the status is not quietly reverted to a live one.
        $this->assertSame($status, $offer->fresh()->status);
    }

    public static function finalizedStatusProvider(): array
    {
        return [
            'expired'   => ['expired'],
            'rejected'  => ['rejected'],
            'withdrawn' => ['withdrawn'],
        ];
    }

    public function test_live_bids_retain_their_valid_actions(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [, $offerAuction] = $this->openListing($owner);

        foreach (['submitted', 'countered'] as $status) {
            $offer   = $this->bid($offerAuction, $status, CarbonImmutable::now()->subHours(3));
            $actions = app(OfferAvailableActionsService::class)->forOffer($offer, $owner->id, 'seller');

            $this->assertTrue($actions['can_accept'], "[{$status}] owner must still be able to accept a live bid.");
            $this->assertTrue($actions['can_reject'], "[{$status}] owner must still be able to reject a live bid.");
            $this->assertTrue($actions['can_counter'], "[{$status}] owner must still be able to counter a live bid.");
        }
    }

    // =====================================================================
    // 12-14. Numbering stability and privacy.
    // =====================================================================

    /** Test 12: bidder 1 expires, 2 is rejected, 3 withdraws — nobody renumbers. */
    public function test_bidder_numbering_is_stable_across_every_finalization(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [, $offerAuction] = $this->openListing($owner);

        $one   = $this->bid($offerAuction, 'submitted', CarbonImmutable::now()->subHours(9));
        $two   = $this->bid($offerAuction, 'submitted', CarbonImmutable::now()->subHours(6));
        $three = $this->bid($offerAuction, 'submitted', CarbonImmutable::now()->subHours(3));

        $this->assertSame([1, 2, 3], array_column($this->feed->build($offerAuction, 'seller'), 'bidder_number'));

        $one->update(['status' => 'expired']);
        $two->update(['status' => 'rejected']);
        $three->update(['status' => 'withdrawn']);

        $rows = $this->feed->build($offerAuction, 'seller');

        $this->assertSame(
            [1, 2, 3],
            array_column($rows, 'bidder_number'),
            'Bidder 1 stays 1 after expiry, 2 stays 2 after rejection, 3 stays 3 after withdrawal.'
        );
        $this->assertSame(
            ['Expired', 'Rejected', 'Withdrawn'],
            array_column($rows, 'status')
        );
    }

    /** Tests 13-14: retention must not widen disclosure. */
    public function test_finalized_bids_never_expose_identity_or_confidential_terms(): void
    {
        $owner  = User::factory()->create(['user_type' => 'seller']);
        [, $offerAuction] = $this->openListing($owner);

        $bidder = User::factory()->create(['user_type' => 'buyer', 'email' => 'secret.bidder@example.com']);

        $offer = Offer::factory()->create([
            'user_id'          => $bidder->id,
            'offer_auction_id' => $offerAuction->id,
            'role'             => 'buyer',
            'status'           => 'rejected',
            'submitted_at'     => CarbonImmutable::now()->subHours(4),
        ]);

        foreach ([
            'offer_price'                 => '500000',   // allow-listed
            'notes'                       => 'PRIVATE NOTE',
            'custom_terms'                => 'PRIVATE CONTINGENCY',
            'proof_of_funds'              => 'pof-document.pdf',
            'financing_documents'         => 'financing.pdf',
            'contact_email'               => 'secret.bidder@example.com',
            'contact_phone'               => '555-0100',
            'possession_notes'            => 'PRIVATE POSSESSION NOTE',
            'seller_contribution_details' => 'PRIVATE CONTRIBUTION DETAIL',
        ] as $k => $v) {
            $offer->saveMeta($k, $v);
        }

        $row     = $this->feed->build($offerAuction, 'seller')[0];
        $encoded = json_encode($row);

        $this->assertSame('Rejected', $row['status']);
        $this->assertArrayHasKey('bidder_number', $row);
        $this->assertArrayNotHasKey('user_id', $row);
        $this->assertArrayNotHasKey('offer_id', $row);

        foreach ([
            'PRIVATE NOTE', 'PRIVATE CONTINGENCY', 'pof-document.pdf', 'financing.pdf',
            'secret.bidder@example.com', '555-0100', 'PRIVATE POSSESSION NOTE',
            'PRIVATE CONTRIBUTION DETAIL', $bidder->name,
        ] as $secret) {
            $this->assertStringNotContainsString(
                (string) $secret,
                $encoded,
                'Retaining a finalized bid must not widen what it discloses.'
            );
        }
    }

    // =====================================================================
    // 15-20. Bidding Period surface and the canonical live route.
    // =====================================================================

    /** Tests 15, 16, 20: the real route renders the canonical feed with history. */
    public function test_canonical_listing_route_renders_the_feed_including_finalized_bids(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [$listing, $offerAuction] = $this->openListing($owner);

        $this->bid($offerAuction, 'submitted', CarbonImmutable::now()->subHours(6), ['offer_price' => '500000']);
        $this->bid($offerAuction, 'rejected',  CarbonImmutable::now()->subHours(5), ['offer_price' => '510000']);
        $this->bid($offerAuction, 'withdrawn', CarbonImmutable::now()->subHours(4), ['offer_price' => '520000']);

        $response = $this->actingAs($owner)->get(route('offer.listing.seller.view', $listing->id));

        $response->assertOk();
        $response->assertSee('Competing Bids');
        $response->assertSee('Bidder #1');
        $response->assertSee('Bidder #2');
        $response->assertSee('Bidder #3');
        $response->assertSee('Rejected');
        $response->assertSee('Withdrawn');
        $response->assertSee('$510,000');
        $response->assertSee('$520,000');
    }

    /** Test 20: the canonical route resolves the expected controller + feed. */
    public function test_canonical_route_uses_the_expected_controller_and_feed(): void
    {
        $route = app('router')->getRoutes()->getByName('offer.listing.seller.view');

        $this->assertNotNull($route, 'The canonical seller listing-detail route must exist.');
        $this->assertSame('offer-listing/seller/view/{id}', $route->uri());
        $this->assertSame(
            \App\Http\Controllers\SellerOfferListingController::class . '@view',
            $route->getActionName()
        );

        $controller = file_get_contents(app_path('Http/Controllers/SellerOfferListingController.php'));

        $this->assertStringContainsString('PublicOfferFeedService', $controller);
        $this->assertStringContainsString("view('offer-listing.seller.view'", $controller);
        $this->assertStringContainsString(
            "@include('offer-listing.partials._competing-bids'",
            file_get_contents(resource_path('views/offer-listing/seller/view.blade.php'))
        );
    }

    /** Test 17: a lapsed expires_at does not remove the bid. */
    public function test_lapsed_expires_at_does_not_remove_the_bid(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [, $offerAuction] = $this->openListing($owner);

        $this->bid(
            $offerAuction,
            'expired',
            CarbonImmutable::now()->subHours(6),
            ['offer_price' => '480000'],
            CarbonImmutable::now()->subHours(2),
        );

        $rows = $this->feed->build($offerAuction, 'seller');

        $this->assertCount(1, $rows);
        $this->assertSame('Expired', $rows[0]['status']);
    }

    /** Test 18: no finalized status may move the listing deadline. */
    public function test_finalized_statuses_do_not_alter_bidding_ends_at(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [$listing, $offerAuction] = $this->openListing($owner);

        $before = $offerAuction->bidding_ends_at->toDateTimeString();

        $this->oneBidPerStatus($offerAuction);
        $this->feed->build($offerAuction, 'seller');

        $after = $offerAuction->fresh();

        $this->assertSame(
            $before,
            $after->bidding_ends_at->toDateTimeString(),
            'No offer status change may move the listing bidding deadline.'
        );

        $window = app(BiddingWindowService::class)->for($listing->fresh('meta'), $after);
        $this->assertTrue($window->isCanonical());
        $this->assertFalse($window->isClosed());
    }

    /** Test 19: expiration_date never influences the countdown. */
    public function test_expiration_date_does_not_influence_the_countdown(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [$listing, $offerAuction] = $this->openListing($owner);

        $this->oneBidPerStatus($offerAuction);

        $window = app(BiddingWindowService::class)->for($listing, $offerAuction);

        $this->assertSame(
            $offerAuction->bidding_ends_at->toDateTimeString(),
            $window->endsAt->toDateTimeString()
        );
        $this->assertLessThan(
            CarbonImmutable::parse('2027-03-31')->getTimestamp(),
            $window->endsAt->getTimestamp(),
            'Invariant 10: the listing expiration date must never drive the bidding countdown.'
        );
    }

    // =====================================================================
    // 21-27. Display formatting.
    // =====================================================================

    /** Tests 21-23: currency. */
    public function test_currency_formatting(): void
    {
        $this->assertSame('$5,000',    $this->terms->display('offer_price', ['offer_price' => '5000']));
        $this->assertSame('$125,000',  $this->terms->display('offer_price', ['offer_price' => '125000']));
        $this->assertSame('$5,250.50', $this->terms->display('offer_price', ['offer_price' => '5250.5']));

        // Whole dollars carry no decimals, including when stored with a .00 tail.
        $this->assertSame('$5,000', $this->terms->display('offer_price', ['offer_price' => '5000.00']));
        $this->assertSame('$2,500', $this->terms->display('monthly_rent', ['monthly_rent' => '2500']));
        $this->assertSame('$1,800', $this->terms->display('security_deposit', ['security_deposit' => '1800']));
        $this->assertSame('$4,300', $this->terms->display('move_in_funds', ['move_in_funds' => '4300']));
    }

    /** Test 24: absent money is an em dash, never $0. */
    public function test_missing_money_never_renders_as_zero(): void
    {
        foreach ([[], ['offer_price' => null], ['offer_price' => '']] as $terms) {
            $this->assertSame(
                OfferTermPresenter::EMPTY_DISPLAY,
                $this->terms->display('offer_price', $terms),
                'A missing amount must not be presented as $0.'
            );
        }

        // A genuine stored zero is still shown as zero.
        $this->assertSame('$0', $this->terms->display('offer_price', ['offer_price' => '0']));
    }

    /**
     * Test 25: percentages follow the stored convention.
     *
     * earnest_deposit / down_payment_value are WHOLE percentages when their unit
     * is '%'. Never multiplied by 100, never shown as a ratio.
     */
    public function test_percentage_formatting_follows_the_storage_convention(): void
    {
        $this->assertSame('3%', $this->terms->display('down_payment_value', [
            'down_payment_value' => '3', 'down_payment_unit' => '%',
        ]));
        $this->assertSame('3.5%', $this->terms->display('down_payment_value', [
            'down_payment_value' => '3.5', 'down_payment_unit' => '%',
        ]));
        $this->assertSame('20%', $this->terms->display('down_payment_value', [
            'down_payment_value' => '20', 'down_payment_unit' => '%',
        ]));
        $this->assertSame('1.5%', $this->terms->display('earnest_deposit', [
            'earnest_deposit' => '1.5', 'earnest_deposit_unit' => '%',
        ]));

        // Same field, dollar unit => currency, not a percentage.
        $this->assertSame('$90,000', $this->terms->display('down_payment_value', [
            'down_payment_value' => '90000', 'down_payment_unit' => '$',
        ]));
        $this->assertSame('$5,000', $this->terms->display('earnest_deposit', [
            'earnest_deposit' => '5000', 'earnest_deposit_unit' => '$',
        ]));

        // Missing unit falls back to dollars, matching _offer_terms_display.
        $this->assertSame('$5,000', $this->terms->display('earnest_deposit', ['earnest_deposit' => '5000']));
    }

    /** Test 26: metric and duration units. */
    public function test_duration_units_are_appended_correctly(): void
    {
        $this->assertSame('7 days',  $this->terms->display('financing_contingency_days', ['financing_contingency_days' => '7']));
        $this->assertSame('1 day',   $this->terms->display('inspection_contingency_days', ['inspection_contingency_days' => '1']));
        $this->assertSame('12 months', $this->terms->display('lease_term_months', ['lease_term_months' => '12']));
        $this->assertSame('1 month',   $this->terms->display('lease_term_months', ['lease_term_months' => '1']));
    }

    /** Units must not be bolted onto fields that do not use them. */
    public function test_non_numeric_terms_are_passed_through_untouched(): void
    {
        $this->assertSame('Conventional', $this->terms->display('financing_type', ['financing_type' => 'Conventional']));
        $this->assertSame('Yes', $this->terms->display('home_warranty_requested', ['home_warranty_requested' => 'Yes']));
        $this->assertSame('Yes', $this->terms->display('last_month_rent_offered', ['last_month_rent_offered' => 'Yes']));
        $this->assertSame('Landlord', $this->terms->display('maintenance_responsibility', ['maintenance_responsibility' => 'Landlord']));
        $this->assertSame('2026-09-01', $this->terms->display('closing_date', ['closing_date' => '2026-09-01']));
    }

    /** Unit companions fold into their value rather than taking a column. */
    public function test_unit_companion_keys_do_not_become_their_own_column(): void
    {
        $allowed = PublicOfferFeedService::SELLER_ALLOWED_TERMS;
        $rows    = [[
            'terms' => [
                'offer_price'         => '500000',
                'earnest_deposit'     => '5000',
                'earnest_deposit_unit' => '$',
            ],
        ]];

        $columns = $this->terms->columnKeys($allowed, $rows);

        $this->assertContains('offer_price', $columns);
        $this->assertContains('earnest_deposit', $columns);
        $this->assertNotContains('earnest_deposit_unit', $columns);
        $this->assertNotContains('down_payment_unit', $columns);

        // Columns nobody filled in are still omitted.
        $this->assertNotContains('closing_date', $columns);
    }

    /** Test 27: formatting is display-only. */
    public function test_formatting_never_mutates_persisted_values(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [$listing, $offerAuction] = $this->openListing($owner);

        $offer = $this->bid($offerAuction, 'submitted', CarbonImmutable::now()->subHours(4), [
            'offer_price'        => '5250.5',
            'down_payment_value' => '20',
            'down_payment_unit'  => '%',
        ]);

        $row = $this->feed->build($offerAuction, 'seller')[0];

        // The feed still carries raw stored values...
        $this->assertSame('5250.5', $row['terms']['offer_price']);
        $this->assertSame('20', $row['terms']['down_payment_value']);

        // ...the presenter only changes how they read.
        $this->assertSame('$5,250.50', $this->terms->display('offer_price', $row['terms']));
        $this->assertSame('20%', $this->terms->display('down_payment_value', $row['terms']));

        // And nothing was written back.
        $fresh = $offer->fresh('metas');
        $this->assertSame('5250.5', $fresh->getMeta('offer_price'));
        $this->assertSame('20', $fresh->getMeta('down_payment_value'));

        // Rendering the real page must not change them either.
        $this->actingAs($owner)->get(route('offer.listing.seller.view', $listing->id))->assertOk();

        $this->assertSame('5250.5', $offer->fresh('metas')->getMeta('offer_price'));
    }
}
