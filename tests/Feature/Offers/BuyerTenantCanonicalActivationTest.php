<?php

namespace Tests\Feature\Offers;

use App\Models\BuyerAgentAuction;
use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Services\Offers\BiddingWindowService;
use App\Services\Offers\ListingOfferAuctionLinker;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Stage 1 — Buyer/Tenant canonical activation linkage.
 *
 * The rule this suite defends: a Buyer or Tenant bidding window begins when the
 * listing is PUBLISHED, never when the first offer arrives. Anchoring to the
 * first bid would start the contest whenever a bidder happened to show up, which
 * is the same wrong-lifecycle-event defect Stage 0 removed elsewhere.
 */
class BuyerTenantCanonicalActivationTest extends TestCase
{
    use DatabaseTransactions;

    private ListingOfferAuctionLinker $linker;
    private BiddingWindowService $windows;

    protected function setUp(): void
    {
        parent::setUp();
        $this->linker  = app(ListingOfferAuctionLinker::class);
        $this->windows = app(BiddingWindowService::class);
    }

    // ------------------------------------------------------------- fixtures

    private function listing(string $role, array $meta = []): BuyerAgentAuction|TenantAgentAuction
    {
        $user  = User::factory()->create(['user_type' => $role]);
        $class = $role === 'buyer' ? BuyerAgentAuction::class : TenantAgentAuction::class;

        // Assigned individually: TenantAgentAuction is not mass-assignable.
        $listing              = new $class();
        $listing->user_id     = $user->id;
        $listing->title       = ucfirst($role) . ' Criteria Listing';
        $listing->is_draft    = false;
        $listing->is_approved = true;
        $listing->save();

        foreach ($meta + ['auction_type' => 'Bidding Period', 'auction_time' => '5 Days'] as $k => $v) {
            $listing->saveMeta($k, $v);
        }

        return $listing->fresh('meta');
    }

    /** Simulate publication exactly as the Livewire components now do. */
    private function publish($listing, string $role, ?CarbonImmutable $at = null): OfferAuction
    {
        $offerAuction = $this->linker->ensureFor($listing, $role);

        if ($this->windows->isBiddingPeriod($listing->info('auction_type') ?: null)) {
            $this->windows->markActivated($offerAuction, $listing->info('auction_time') ?: null, $at);
        }

        return $offerAuction->fresh();
    }

    public static function roleProvider(): array
    {
        return ['buyer' => ['buyer'], 'tenant' => ['tenant']];
    }

    // =====================================================================
    // 1 & 2. Publishing creates exactly ONE canonical auction.
    // =====================================================================

    /** @dataProvider roleProvider */
    public function test_publishing_creates_exactly_one_canonical_auction(string $role): void
    {
        $listing = $this->listing($role);
        $key     = ListingOfferAuctionLinker::criteriaKey($role, $listing->id);

        $this->publish($listing, $role);

        $this->assertSame(1, OfferAuction::where('listing_id', $key)->count());
        $this->assertSame((string) $key, (string) OfferAuction::where('listing_id', $key)->first()->listing_id);
    }

    // =====================================================================
    // 3. Both timestamps stamped at publication.
    // =====================================================================

    /** @dataProvider roleProvider */
    public function test_both_timestamps_are_stamped_at_publication(string $role): void
    {
        $at      = CarbonImmutable::parse('2026-03-01 12:00:00');
        $listing = $this->listing($role);

        $oa = $this->publish($listing, $role, $at);

        $this->assertNotNull($oa->bidding_starts_at);
        $this->assertNotNull($oa->bidding_ends_at);
        $this->assertSame($at->toDateTimeString(), $oa->bidding_starts_at->toDateTimeString());
        $this->assertSame($at->addDays(5)->toDateTimeString(), $oa->bidding_ends_at->toDateTimeString());
    }

    // =====================================================================
    // 4 & 5. The first offer creates nothing and starts nothing.
    // =====================================================================

    /** @dataProvider roleProvider */
    public function test_first_offer_does_not_create_a_second_auction(string $role): void
    {
        $listing = $this->listing($role);
        $key     = ListingOfferAuctionLinker::criteriaKey($role, $listing->id);
        $this->publish($listing, $role);

        // The submission path resolves through the same linker.
        $resolved = $this->linker->ensureFor($listing->fresh('meta'), $role);

        $this->assertSame(1, OfferAuction::where('listing_id', $key)->count());
        $this->assertSame($key, $resolved->listing_id);
    }

    /** @dataProvider roleProvider */
    public function test_first_offer_does_not_start_restart_or_extend_the_window(string $role): void
    {
        $at      = CarbonImmutable::parse('2026-03-01 12:00:00');
        $listing = $this->listing($role);
        $oa      = $this->publish($listing, $role, $at);

        $startsBefore = $oa->bidding_starts_at->toDateTimeString();
        $endsBefore   = $oa->bidding_ends_at->toDateTimeString();

        // A bidder arrives days later.
        $resolved = $this->linker->ensureFor($listing->fresh('meta'), $role);
        Offer::create([
            'user_id'          => User::factory()->create()->id,
            'offer_auction_id' => $resolved->id,
            'role'             => $role,
            'status'           => 'draft',
        ]);

        $after = $resolved->fresh();
        $this->assertSame($startsBefore, $after->bidding_starts_at->toDateTimeString());
        $this->assertSame($endsBefore, $after->bidding_ends_at->toDateTimeString());
    }

    // =====================================================================
    // 6. Re-saving an active listing does not move either timestamp.
    // =====================================================================

    /** @dataProvider roleProvider */
    public function test_republishing_does_not_change_either_timestamp(string $role): void
    {
        $at      = CarbonImmutable::parse('2026-03-01 12:00:00');
        $listing = $this->listing($role);
        $oa      = $this->publish($listing, $role, $at);

        $startsBefore = $oa->bidding_starts_at->toDateTimeString();
        $endsBefore   = $oa->bidding_ends_at->toDateTimeString();

        // Owner edits and re-saves a month later, even lengthening the duration.
        $listing->saveMeta('auction_time', '90 Days');
        $again = $this->publish($listing->fresh('meta'), $role, CarbonImmutable::parse('2026-04-01 12:00:00'));

        $this->assertSame($startsBefore, $again->bidding_starts_at->toDateTimeString());
        $this->assertSame($endsBefore, $again->bidding_ends_at->toDateTimeString());
    }

    // =====================================================================
    // 7. Traditional listings never receive a window.
    // =====================================================================

    /** @dataProvider roleProvider */
    public function test_traditional_listings_receive_no_bidding_window(string $role): void
    {
        $listing = $this->listing($role, ['auction_type' => 'Traditional', 'auction_time' => '']);

        $oa = $this->publish($listing, $role);

        $this->assertNull($oa->bidding_starts_at);
        $this->assertNull($oa->bidding_ends_at);
        $this->assertFalse($this->windows->for($listing, $oa)->isBiddingPeriod);
    }

    // =====================================================================
    // 8 & 9. Pre-existing auctions are adopted; their offers survive.
    // =====================================================================

    /** @dataProvider roleProvider */
    public function test_existing_legacy_auction_is_reused_and_its_offers_stay_attached(string $role): void
    {
        $listing = $this->listing($role);
        $key     = ListingOfferAuctionLinker::criteriaKey($role, $listing->id);

        // A listing published BEFORE Stage 1: the bridge was created lazily by a
        // first-offer submission, with no bidding window.
        $legacy = OfferAuction::create([
            'listing_id' => $key,
            'user_id'    => $listing->user_id,
            'title'      => $listing->title,
            'is_draft'   => false,
        ]);
        $offer = Offer::create([
            'user_id'          => User::factory()->create()->id,
            'offer_auction_id' => $legacy->id,
            'role'             => $role,
            'status'           => 'submitted',
        ]);

        $adopted = $this->publish($listing, $role);

        $this->assertSame($legacy->id, $adopted->id, 'The existing auction must be adopted, not duplicated.');
        $this->assertSame(1, OfferAuction::where('listing_id', $key)->count());
        $this->assertSame($legacy->id, $offer->fresh()->offer_auction_id, 'Existing offers must stay attached.');
        $this->assertNotNull($adopted->bidding_ends_at, 'Publication stamps the adopted row.');
    }

    // =====================================================================
    // 10 & 11. Surfaces read the stored value; legacy shows nothing.
    // =====================================================================

    /** @dataProvider roleProvider */
    public function test_surfaces_read_the_stored_bidding_ends_at(string $role): void
    {
        $at      = CarbonImmutable::parse('2026-03-01 12:00:00');
        $listing = $this->listing($role, ['expiration_date' => '2026-12-31']);
        $oa      = $this->publish($listing, $role, $at);

        $window = $this->windows->for($listing->fresh('meta'), $oa);

        $this->assertTrue($window->isCanonical());
        $this->assertSame($at->addDays(5)->toDateTimeString(), $window->endsAt->toDateTimeString());
        $this->assertContains($window->displayTimezoneAbbreviation(), ['EST', 'EDT']);
    }

    /** @dataProvider roleProvider */
    public function test_legacy_uninitialized_listing_shows_no_countdown(string $role): void
    {
        $listing = $this->listing($role, ['expiration_date' => '2026-12-31']);

        // Never published through Stage 1 — no auction at all.
        $window = $this->windows->for($listing, null);

        $this->assertTrue($window->isUninitialized());
        $this->assertNull($window->endsAt);
        $this->assertSame(0, $window->remainingSeconds());
        $this->assertFalse($window->isClosed(), 'Missing data must never block a bidder.');
    }

    // =====================================================================
    // Reverse resolution — enforcement must reach the listing.
    // =====================================================================

    /** @dataProvider roleProvider */
    public function test_reverse_resolution_finds_the_listing_behind_a_criteria_auction(string $role): void
    {
        $listing = $this->listing($role);
        $oa      = $this->publish($listing, $role);

        [$found, $foundRole] = $this->linker->listingFor($oa);

        $this->assertNotNull($found, 'Enforcement guards resolve the listing through here.');
        $this->assertSame($listing->id, $found->id);
        $this->assertSame($role, $foundRole);
    }

    // =====================================================================
    // 14. Seller/Landlord behaviour unchanged.
    // =====================================================================

    public function test_seller_linkage_is_unchanged_by_the_criteria_branch(): void
    {
        $user   = User::factory()->create(['user_type' => 'seller']);
        $seller = SellerAgentAuction::create([
            'user_id' => $user->id, 'title' => 'Seller Listing',
            'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);
        $seller->saveMeta('auction_type', 'Bidding Period');
        $seller->saveMeta('auction_time', '5 Days');

        $oa = $this->linker->ensureFor($seller->fresh('meta'), 'seller');

        // Seller auctions keep their generated listing_id — never a criteria key.
        $this->assertStringStartsNotWith('buyer_criteria:', (string) $oa->listing_id);
        $this->assertStringStartsNotWith('tenant_criteria:', (string) $oa->listing_id);
        $this->assertSame((string) $oa->id, (string) $seller->fresh('meta')->info('linked_offer_auction_id'));

        [, $role] = $this->linker->listingFor($oa);
        $this->assertSame('seller', $role);
    }

    // =====================================================================
    // 13. No Buyer/Tenant bidding path references a banned source.
    // =====================================================================

    public function test_buyer_tenant_surfaces_contain_no_banned_deadline_derivation(): void
    {
        $base  = dirname(__DIR__, 3);
        $files = [
            'resources/views/offer-listing/buyer/view.blade.php',
            'resources/views/offer-listing/tenant/view.blade.php',
            'resources/views/offer-listing/buyer/search.blade.php',
            'resources/views/offer-listing/tenant/search.blade.php',
            'app/Http/Controllers/BuyerOfferListingController.php',
            'app/Http/Controllers/TenantOfferListingController.php',
            'app/Services/Offers/ListingOfferAuctionLinker.php',
        ];

        $banned = [
            '/->addDays\(\$_[a-zA-Z]+\)/'          => 'deadline arithmetic',
            '/->addHours\(\$_[a-zA-Z]+\)/'         => 'deadline arithmetic',
            '/diffInSeconds\(\$_(end|timerEnd)/i'  => 'runtime countdown computation',
            "/INTERVAL '1 day'/"                   => 'SQL deadline arithmetic',
            "/meta_key = 'expiration_date'/"       => 'expiration_date in SQL ordering',
        ];

        $violations = [];

        foreach ($files as $relative) {
            $full = $base . '/' . $relative;
            $this->assertFileExists($full);

            foreach (file($full, FILE_IGNORE_NEW_LINES) as $i => $line) {
                $t = ltrim($line);
                if (str_starts_with($t, '//') || str_starts_with($t, '*')
                    || str_starts_with($t, '/*') || str_starts_with($t, '{{--')) {
                    continue;
                }
                foreach ($banned as $pattern => $label) {
                    if (preg_match($pattern, $line)) {
                        $violations[] = sprintf('%s:%d — %s', $relative, $i + 1, $label);
                    }
                }
            }
        }

        $this->assertSame([], $violations, "Banned derivation on a Buyer/Tenant bidding path:\n  " . implode("\n  ", $violations));
    }

    public function test_offer_submission_path_never_stamps_a_window(): void
    {
        $lines = file(
            dirname(__DIR__, 3) . '/app/Http/Controllers/OfferController.php',
            FILE_IGNORE_NEW_LINES
        );

        $callSites = [];

        foreach ($lines as $i => $line) {
            // Docblocks explain that this path must NOT stamp; only executable
            // code counts as a violation.
            $t = ltrim($line);
            if (str_starts_with($t, '//') || str_starts_with($t, '*') || str_starts_with($t, '/*')) {
                continue;
            }
            if (str_contains($line, 'markActivated(')) {
                $callSites[] = 'line ' . ($i + 1);
            }
        }

        $this->assertSame(
            [],
            $callSites,
            'OfferController must never stamp a bidding window — publication is the only activation point. Found at: '
            . implode(', ', $callSites)
        );
    }
}
