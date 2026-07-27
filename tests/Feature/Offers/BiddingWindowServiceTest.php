<?php

namespace Tests\Feature\Offers;

use App\Models\LandlordAgentAuction;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\Offers\BiddingWindowService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The canonical bidding window: bidding_started_at + auction_time.
 *
 * Covers the activation stamp (written once, never moved), the documented
 * legacy fallback for listings that pre-date the column, and the Eastern-time
 * display conversion.
 */
class BiddingWindowServiceTest extends TestCase
{
    use DatabaseTransactions;

    private BiddingWindowService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BiddingWindowService::class);
    }

    private function seller(array $meta = []): SellerAgentAuction
    {
        $user    = User::factory()->create(['user_type' => 'seller']);
        $listing = SellerAgentAuction::create([
            'user_id' => $user->id, 'title' => 'BP Seller Listing',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);

        foreach ($meta as $k => $v) {
            $listing->saveMeta($k, $v);
        }

        return $listing->fresh('meta');
    }

    private function landlord(array $meta = []): LandlordAgentAuction
    {
        $user    = User::factory()->create(['user_type' => 'landlord']);
        $listing = LandlordAgentAuction::create([
            'user_id' => $user->id, 'title' => 'BP Landlord Listing',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);

        foreach ($meta as $k => $v) {
            $listing->saveMeta($k, $v);
        }

        return $listing->fresh('meta');
    }

    // ---------------------------------------------------------------- canonical

    public function test_deadline_is_bidding_started_at_plus_auction_time(): void
    {
        $start   = CarbonImmutable::parse('2026-07-01 12:00:00');
        $listing = $this->seller(['auction_type' => 'Bidding Period', 'auction_time' => '14 Days']);
        $oa      = OfferAuction::factory()->create(['bidding_started_at' => $start]);

        $window = $this->service->for($listing, $oa);

        $this->assertTrue($window->isBiddingPeriod);
        $this->assertFalse($window->isLegacyFallback);
        $this->assertSame(
            $start->addDays(14)->toDateTimeString(),
            $window->endsAt->toDateTimeString(),
        );
    }

    public function test_deadline_ignores_expiration_date_when_stamp_is_present(): void
    {
        $start   = CarbonImmutable::parse('2026-07-01 12:00:00');
        $listing = $this->seller([
            'auction_type'    => 'Bidding Period',
            'auction_time'    => '7 Days',
            // A listing expiry far from the bidding deadline — it must not win.
            'expiration_date' => '2026-12-31',
        ]);
        $oa = OfferAuction::factory()->create(['bidding_started_at' => $start]);

        $window = $this->service->for($listing, $oa);

        $this->assertSame('2026-07-08 12:00:00', $window->endsAt->toDateTimeString());
    }

    public function test_deadline_ignores_listing_created_at_when_stamp_is_present(): void
    {
        $listing = $this->seller(['auction_type' => 'Bidding Period', 'auction_time' => '3 Days']);
        $listing->created_at = CarbonImmutable::parse('2026-01-01 00:00:00');
        $listing->save();

        $start = CarbonImmutable::parse('2026-06-01 09:00:00');
        $oa    = OfferAuction::factory()->create(['bidding_started_at' => $start]);

        $window = $this->service->for($listing->fresh('meta'), $oa);

        $this->assertSame('2026-06-04 09:00:00', $window->endsAt->toDateTimeString());
    }

    public function test_traditional_listing_has_no_bidding_window(): void
    {
        $listing = $this->seller(['auction_type' => 'Traditional', 'auction_time' => '14 Days']);
        $oa      = OfferAuction::factory()->create(['bidding_started_at' => CarbonImmutable::now()]);

        $window = $this->service->for($listing, $oa);

        $this->assertFalse($window->isBiddingPeriod);
        $this->assertNull($window->endsAt);
        $this->assertFalse($window->isClosed());
    }

    // --------------------------------------------------------------- activation

    public function test_activation_stamps_bidding_started_at_once(): void
    {
        $first = CarbonImmutable::parse('2026-07-01 10:00:00');
        $oa    = OfferAuction::factory()->create(['bidding_started_at' => null]);

        $this->assertTrue($this->service->markActivated($oa, $first));
        $this->assertSame($first->toDateTimeString(), $oa->fresh()->bidding_started_at->toDateTimeString());
    }

    public function test_activation_never_overwrites_or_restarts_an_existing_stamp(): void
    {
        $original = CarbonImmutable::parse('2026-07-01 10:00:00');
        $oa       = OfferAuction::factory()->create(['bidding_started_at' => $original]);

        // Re-publishing, re-saving, or a duplicate request must not move the clock.
        $this->assertFalse($this->service->markActivated($oa, CarbonImmutable::parse('2026-08-01 10:00:00')));
        $this->assertFalse($this->service->markActivated($oa, CarbonImmutable::parse('2026-09-01 10:00:00')));

        $this->assertSame($original->toDateTimeString(), $oa->fresh()->bidding_started_at->toDateTimeString());
    }

    public function test_activation_is_a_no_op_without_a_linked_offer_auction(): void
    {
        $this->assertFalse($this->service->markActivated(null));
    }

    // ------------------------------------------------------------------ closing

    public function test_window_is_open_before_the_deadline_and_closed_after(): void
    {
        $start   = CarbonImmutable::parse('2026-07-01 12:00:00');
        $listing = $this->seller(['auction_type' => 'Bidding Period', 'auction_time' => '2 Days']);
        $oa      = OfferAuction::factory()->create(['bidding_started_at' => $start]);

        $window = $this->service->for($listing, $oa);

        $this->assertFalse($window->isClosed($start->addDay()));
        $this->assertTrue($window->isClosed($start->addDays(2)));
        $this->assertTrue($window->isClosed($start->addDays(3)));
    }

    public function test_remaining_seconds_never_goes_negative(): void
    {
        $start   = CarbonImmutable::parse('2026-07-01 12:00:00');
        $listing = $this->seller(['auction_type' => 'Bidding Period', 'auction_time' => '1 Days']);
        $oa      = OfferAuction::factory()->create(['bidding_started_at' => $start]);

        $window = $this->service->for($listing, $oa);

        $this->assertSame(0, $window->remainingSeconds($start->addDays(5)));
        $this->assertSame(3600, $window->remainingSeconds($start->addDays(1)->subHour()));
    }

    public function test_unresolvable_deadline_leaves_the_window_open(): void
    {
        // Bidding Period with no usable auction_time and no expiration_date: we
        // cannot compute a close, so bidders must not be locked out.
        $listing = $this->seller(['auction_type' => 'Bidding Period', 'auction_time' => '']);
        $oa      = OfferAuction::factory()->create(['bidding_started_at' => CarbonImmutable::now()]);

        $window = $this->service->for($listing, $oa);

        $this->assertNull($window->endsAt);
        $this->assertFalse($window->isClosed());
    }

    // ------------------------------------------------------------------- legacy

    public function test_legacy_fallback_uses_expiration_date_when_stamp_is_null(): void
    {
        $listing = $this->seller([
            'auction_type'    => 'Bidding Period',
            'auction_time'    => '14 Days',
            'expiration_date' => '2026-08-15 00:00:00',
        ]);
        $oa = OfferAuction::factory()->create(['bidding_started_at' => null]);

        $window = $this->service->for($listing, $oa);

        $this->assertTrue($window->isLegacyFallback);
        $this->assertSame('2026-08-15 00:00:00', $window->endsAt->toDateTimeString());
        $this->assertStringContainsString('expiration_date', $window->legacyFallbackReason);
    }

    public function test_legacy_fallback_uses_created_at_when_no_expiration_date(): void
    {
        $listing = $this->seller(['auction_type' => 'Bidding Period', 'auction_time' => '5 Days']);
        $listing->created_at = CarbonImmutable::parse('2026-03-01 08:00:00');
        $listing->save();

        $oa = OfferAuction::factory()->create(['bidding_started_at' => null]);

        $window = $this->service->for($listing->fresh('meta'), $oa);

        $this->assertTrue($window->isLegacyFallback);
        $this->assertSame('2026-03-06 08:00:00', $window->endsAt->toDateTimeString());
        $this->assertStringContainsString('created_at', $window->legacyFallbackReason);
    }

    public function test_migration_leaves_existing_rows_null(): void
    {
        // A row created without an explicit stamp must stay NULL — the migration
        // deliberately performs no backfill.
        $oa = OfferAuction::create(['user_id' => User::factory()->create()->id]);

        $this->assertNull($oa->fresh()->bidding_started_at);
    }

    // ---------------------------------------------------------------- durations

    /** @dataProvider durationProvider */
    public function test_auction_time_labels_parse_to_the_expected_deadline(string $label, string $expected): void
    {
        $start   = CarbonImmutable::parse('2026-07-01 00:00:00');
        $listing = $this->seller(['auction_type' => 'Bidding Period', 'auction_time' => $label]);
        $oa      = OfferAuction::factory()->create(['bidding_started_at' => $start]);

        $this->assertSame($expected, $this->service->for($listing, $oa)->endsAt->toDateTimeString());
    }

    public static function durationProvider(): array
    {
        return [
            'days'          => ['14 Days',    '2026-07-15 00:00:00'],
            'singular day'  => ['1 Day',      '2026-07-02 00:00:00'],
            'weeks'         => ['2 Weeks',    '2026-07-15 00:00:00'],
            'hours'         => ['48 Hours',   '2026-07-03 00:00:00'],
            'minutes'       => ['90 Minutes', '2026-07-01 01:30:00'],
            // A bare number means days, matching how the legacy views read it.
            'bare number'   => ['7',          '2026-07-08 00:00:00'],
        ];
    }

    public function test_zero_and_junk_durations_produce_no_deadline(): void
    {
        foreach (['0 Days', 'null', '', 'soon'] as $label) {
            $listing = $this->seller(['auction_type' => 'Bidding Period', 'auction_time' => $label]);
            $oa      = OfferAuction::factory()->create(['bidding_started_at' => CarbonImmutable::now()]);

            $this->assertNull(
                $this->service->for($listing, $oa)->endsAt,
                "auction_time '{$label}' should not yield a deadline",
            );
        }
    }

    // ----------------------------------------------------------------- timezone

    public function test_deadline_is_displayed_in_eastern_time_without_changing_storage(): void
    {
        // 2026-07-01 16:00 UTC is 12:00 EDT.
        $start   = CarbonImmutable::parse('2026-07-01 16:00:00', 'UTC');
        $listing = $this->seller(['auction_type' => 'Bidding Period', 'auction_time' => '1 Days']);
        $oa      = OfferAuction::factory()->create(['bidding_started_at' => $start]);

        $window = $this->service->for($listing, $oa);

        $this->assertSame('America/New_York', $window->endsAtForDisplay()->timezone->getName());
        $this->assertSame('12:00 PM', $window->endsAtForDisplay()->format('g:i A'));

        // Stored/underlying value is untouched.
        $this->assertSame('2026-07-02 16:00:00', $window->endsAt->toDateTimeString());
    }

    // -------------------------------------------------------- landlord symmetry

    public function test_landlord_listings_resolve_the_same_canonical_window(): void
    {
        $start   = CarbonImmutable::parse('2026-07-01 12:00:00');
        $listing = $this->landlord(['auction_type' => 'Bidding Period', 'auction_time' => '10 Days']);
        $oa      = OfferAuction::factory()->create(['bidding_started_at' => $start]);

        $window = $this->service->for($listing, $oa);

        $this->assertTrue($window->isBiddingPeriod);
        $this->assertFalse($window->isLegacyFallback);
        $this->assertSame('2026-07-11 12:00:00', $window->endsAt->toDateTimeString());
    }
}
