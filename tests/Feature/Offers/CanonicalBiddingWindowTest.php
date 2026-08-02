<?php

namespace Tests\Feature\Offers;

use App\Models\LandlordAgentAuction;
use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\Offers\BiddingWindowService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression lock for the Owner-Approved canonical bidding timer (Decision A).
 *
 * This suite exists because the defect it guards against — a countdown quietly
 * sourced from the LISTING expiration date — shipped in June, survived eight
 * weeks, multiple audits and a full CI run, and was caught only by manual
 * inspection. Every assertion below maps to a Permanent Architectural Invariant
 * in TIMED_OFFER_RUNTIME_INVESTIGATION.md.
 *
 * If any test here fails, the canonical timer contract has been broken. Do not
 * relax the assertion — fix the implementation.
 */
class CanonicalBiddingWindowTest extends TestCase
{
    use DatabaseTransactions;

    private BiddingWindowService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BiddingWindowService::class);
    }

    // ------------------------------------------------------------- fixtures

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

    private function offerAuction(array $attributes = []): OfferAuction
    {
        $user = User::factory()->create();

        return OfferAuction::create(array_merge([
            'user_id'  => $user->id,
            'title'    => 'Linked OfferAuction',
            'is_draft' => false,
        ], $attributes));
    }

    // =====================================================================
    // 1. Publishing a timed listing stamps BOTH canonical timestamps once.
    //    Invariants 3, 4.
    // =====================================================================

    public function test_activation_stamps_both_canonical_timestamps(): void
    {
        $oa = $this->offerAuction();

        $this->assertNull($oa->bidding_starts_at);
        $this->assertNull($oa->bidding_ends_at);

        $stamped = $this->service->markActivated($oa, '5 Days');

        $this->assertTrue($stamped, 'markActivated() should report it performed the stamp.');

        $fresh = $oa->fresh();
        $this->assertNotNull($fresh->bidding_starts_at, 'bidding_starts_at must be persisted.');
        $this->assertNotNull($fresh->bidding_ends_at, 'bidding_ends_at must be persisted, not derived at read time.');
    }

    public function test_activation_is_idempotent_and_never_restarts_a_live_window(): void
    {
        $start = CarbonImmutable::now()->subDays(2);
        $oa    = $this->offerAuction();

        $this->service->markActivated($oa, '5 Days', $start);

        $firstStart = $oa->fresh()->bidding_starts_at->toDateTimeString();
        $firstEnd   = $oa->fresh()->bidding_ends_at->toDateTimeString();

        // Re-publishing, re-saving, editing — all funnel back through here.
        $again = $this->service->markActivated($oa->fresh(), '30 Days', CarbonImmutable::now());

        $this->assertFalse($again, 'A second activation must not re-stamp.');
        $this->assertSame($firstStart, $oa->fresh()->bidding_starts_at->toDateTimeString());
        $this->assertSame(
            $firstEnd,
            $oa->fresh()->bidding_ends_at->toDateTimeString(),
            'Re-publishing with a different duration must not move a live deadline.'
        );
    }

    // =====================================================================
    // 2. bidding_ends_at == bidding_starts_at + approved duration.
    // =====================================================================

    /** @dataProvider durationProvider */
    public function test_stored_end_equals_start_plus_approved_duration(string $auctionTime, string $method, int $value): void
    {
        $start = CarbonImmutable::parse('2026-03-01 12:00:00');
        $oa    = $this->offerAuction();

        $this->service->markActivated($oa, $auctionTime, $start);

        $expected = $start->{$method}($value);

        $this->assertSame(
            $expected->toDateTimeString(),
            $oa->fresh()->bidding_ends_at->toDateTimeString(),
            "auction_time '{$auctionTime}' must store start + {$value} {$method}."
        );
    }

    public static function durationProvider(): array
    {
        return [
            '5 Days'    => ['5 Days', 'addDays', 5],
            '14 Days'   => ['14 Days', 'addDays', 14],
            '2 Weeks'   => ['2 Weeks', 'addWeeks', 2],
            '48 Hours'  => ['48 Hours', 'addHours', 48],
            'bare 7'    => ['7', 'addDays', 7],
        ];
    }

    public function test_unusable_duration_stamps_nothing_rather_than_half_a_window(): void
    {
        $oa = $this->offerAuction();

        $stamped = $this->service->markActivated($oa, '');

        $this->assertFalse($stamped);
        $this->assertNull($oa->fresh()->bidding_starts_at, 'A start must never be written without an end.');
        $this->assertNull($oa->fresh()->bidding_ends_at);
    }

    // =====================================================================
    // 3. Reading / rendering never recomputes or mutates the timestamps.
    //    Invariants 4, 9.
    // =====================================================================

    public function test_resolving_the_window_repeatedly_never_changes_stored_values(): void
    {
        $start   = CarbonImmutable::now()->subDay();
        $oa      = $this->offerAuction();
        $listing = $this->seller(['auction_type' => 'Bidding Period', 'auction_time' => '5 Days']);

        $this->service->markActivated($oa, '5 Days', $start);

        $before = $oa->fresh();

        for ($i = 0; $i < 5; $i++) {
            $this->service->for($listing, $oa->fresh());
        }

        $after = $oa->fresh();

        $this->assertSame($before->bidding_starts_at->toDateTimeString(), $after->bidding_starts_at->toDateTimeString());
        $this->assertSame($before->bidding_ends_at->toDateTimeString(), $after->bidding_ends_at->toDateTimeString());
    }

    public function test_window_end_is_the_stored_column_not_start_plus_auction_time(): void
    {
        // Store a window, then change auction_time underneath it. A correct
        // implementation ignores the new duration entirely; a recomputing one
        // would move the deadline.
        $start   = CarbonImmutable::parse('2026-03-01 12:00:00');
        $oa      = $this->offerAuction();
        $listing = $this->seller(['auction_type' => 'Bidding Period', 'auction_time' => '5 Days']);

        $this->service->markActivated($oa, '5 Days', $start);

        $listing->saveMeta('auction_time', '90 Days');

        $window = $this->service->for($listing->fresh('meta'), $oa->fresh());

        $this->assertSame(
            $start->addDays(5)->toDateTimeString(),
            $window->endsAt->toDateTimeString(),
            'The deadline must come from storage, not from a re-read of auction_time.'
        );
    }

    // =====================================================================
    // 4 & 5. expiration_date can NEVER influence the bidding deadline.
    //        Invariants 1, 2, 10. This is the defect that shipped.
    // =====================================================================

    public function test_expiration_date_cannot_affect_a_canonical_deadline(): void
    {
        $start   = CarbonImmutable::parse('2026-03-01 12:00:00');
        $oa      = $this->offerAuction();
        $listing = $this->seller([
            'auction_type'    => 'Bidding Period',
            'auction_time'    => '5 Days',
            // ~39 days out — the exact shape that produced the 38d 6h defect.
            'expiration_date' => '2026-04-09',
        ]);

        $this->service->markActivated($oa, '5 Days', $start);

        $window = $this->service->for($listing, $oa->fresh());

        $this->assertSame(
            $start->addDays(5)->toDateTimeString(),
            $window->endsAt->toDateTimeString(),
            'expiration_date must have no influence on the bidding deadline.'
        );
    }

    public function test_legacy_listing_without_stamps_does_not_fall_back_to_expiration_date(): void
    {
        $listing = $this->seller([
            'auction_type'    => 'Bidding Period',
            'auction_time'    => '5 Days',
            'expiration_date' => '2026-12-31',
        ]);

        // No OfferAuction stamp at all — the pre-existing-listing case.
        $window = $this->service->for($listing, null);

        $this->assertTrue($window->isBiddingPeriod);
        $this->assertTrue($window->isUninitialized(), 'An unstamped listing must report an uninitialized window.');
        $this->assertFalse($window->isCanonical());
        $this->assertNull($window->endsAt, 'No deadline may be invented from expiration_date.');
        $this->assertFalse($window->hasDeadline());
        $this->assertSame(0, $window->remainingSeconds());
    }

    public function test_legacy_listing_without_stamps_does_not_fall_back_to_created_at(): void
    {
        $listing = $this->seller(['auction_type' => 'Bidding Period', 'auction_time' => '5 Days']);

        $window = $this->service->for($listing, null);

        $this->assertNull($window->endsAt, 'created_at is when the DRAFT was saved and is not a bidding anchor.');
        $this->assertTrue($window->isUninitialized());
    }

    public function test_half_stamped_row_is_uninitialized_not_completed_by_arithmetic(): void
    {
        // The exact state a row from the rejected architecture lands in after
        // the rename migration: a real start, no stored end.
        $oa      = $this->offerAuction(['bidding_starts_at' => CarbonImmutable::now()->subDay()]);
        $listing = $this->seller(['auction_type' => 'Bidding Period', 'auction_time' => '5 Days']);

        $window = $this->service->for($listing, $oa);

        $this->assertTrue($window->isUninitialized());
        $this->assertNull($window->endsAt, 'A missing end must not be reconstructed at read time.');
    }

    // =====================================================================
    // 6. Server-side enforcement reads the SAME stored timestamp.
    //    Invariant 6.
    // =====================================================================

    public function test_enforcement_uses_stored_bidding_ends_at(): void
    {
        $oa = $this->offerAuction([
            'bidding_starts_at' => CarbonImmutable::now()->subDays(10),
            'bidding_ends_at'   => CarbonImmutable::now()->subDay(),   // closed yesterday
        ]);

        $window = new \App\Services\Offers\BiddingWindow(
            isBiddingPeriod: true,
            startsAt: CarbonImmutable::parse($oa->bidding_starts_at),
            endsAt: CarbonImmutable::parse($oa->bidding_ends_at),
        );

        $this->assertTrue($window->isClosed(), 'A window whose stored end has passed must be closed.');
        $this->assertFalse($window->isOpen());
    }

    public function test_uninitialized_window_never_locks_a_bidder_out(): void
    {
        $listing = $this->seller(['auction_type' => 'Bidding Period', 'auction_time' => '5 Days']);

        $window = $this->service->for($listing, null);

        $this->assertFalse(
            $window->isClosed(),
            'Missing data on our side must never be turned into a refusal to bid.'
        );
    }

    // =====================================================================
    // 7. offers.expires_at is a SEPARATE clock. Invariant 2, artifact req. 7.
    // =====================================================================

    public function test_offer_expires_at_is_independent_of_the_listing_bidding_deadline(): void
    {
        $biddingEnd = CarbonImmutable::now()->addDays(5);
        $offerExpiry = CarbonImmutable::now()->addHours(12);

        $oa = $this->offerAuction([
            'bidding_starts_at' => CarbonImmutable::now(),
            'bidding_ends_at'   => $biddingEnd,
        ]);

        $user  = User::factory()->create();
        $offer = Offer::create([
            'user_id'          => $user->id,
            'offer_auction_id' => $oa->id,
            'role'             => 'seller',
            'status'           => 'draft',
            'expires_at'       => $offerExpiry,
        ]);

        $this->assertNotSame(
            $oa->fresh()->bidding_ends_at->toDateTimeString(),
            $offer->fresh()->expires_at->toDateTimeString(),
            'The two clocks must remain distinct values.'
        );

        // Moving the per-offer response deadline must not touch the listing window.
        $offer->update(['expires_at' => CarbonImmutable::now()->addMinutes(5)]);

        $this->assertSame(
            $biddingEnd->toDateTimeString(),
            $oa->fresh()->bidding_ends_at->toDateTimeString(),
            'Changing an offer response deadline must never move the listing bidding deadline.'
        );
    }

    // =====================================================================
    // 8. Every role follows the same contract. Invariant 8.
    // =====================================================================

    public function test_seller_and_landlord_use_an_identical_timer_contract(): void
    {
        $start = CarbonImmutable::parse('2026-03-01 12:00:00');

        $sellerOa = $this->offerAuction();
        $seller   = $this->seller([
            'auction_type' => 'Bidding Period', 'auction_time' => '5 Days',
            'expiration_date' => '2026-11-01',
        ]);
        $this->service->markActivated($sellerOa, '5 Days', $start);

        $landlordOa = $this->offerAuction();
        $landlord   = $this->landlord([
            'auction_type' => 'Bidding Period', 'auction_time' => '5 Days',
            'expiration_date' => '2026-11-01',
        ]);
        $this->service->markActivated($landlordOa, '5 Days', $start);

        $sellerWindow   = $this->service->for($seller, $sellerOa->fresh());
        $landlordWindow = $this->service->for($landlord, $landlordOa->fresh());

        $this->assertSame(
            $sellerWindow->endsAt->toDateTimeString(),
            $landlordWindow->endsAt->toDateTimeString(),
            'Identical inputs must produce an identical deadline for every role.'
        );
        $this->assertTrue($sellerWindow->isCanonical());
        $this->assertTrue($landlordWindow->isCanonical());
    }

    public function test_deadline_is_displayed_with_an_explicit_timezone(): void
    {
        $oa = $this->offerAuction([
            'bidding_starts_at' => CarbonImmutable::now(),
            'bidding_ends_at'   => CarbonImmutable::parse('2026-07-15 16:00:00', 'UTC'),
        ]);
        $listing = $this->seller(['auction_type' => 'Bidding Period', 'auction_time' => '5 Days']);

        $window = $this->service->for($listing, $oa);

        $this->assertSame('12:00', $window->endsAtForDisplay()->format('H:i'), 'UTC 16:00 is 12:00 EDT.');
        $this->assertContains($window->displayTimezoneAbbreviation(), ['EST', 'EDT']);
    }

    public function test_non_bidding_listing_has_no_window_at_all(): void
    {
        $listing = $this->seller(['auction_type' => 'Traditional', 'expiration_date' => '2026-12-31']);

        $window = $this->service->for($listing, null);

        $this->assertFalse($window->isBiddingPeriod);
        $this->assertFalse($window->isUninitialized());
        $this->assertNull($window->endsAt);
    }

    // =====================================================================
    // 9. Repository-level guard: expiration_date may not appear anywhere in
    //    the bidding timer code path. Invariant 10.
    // =====================================================================

    public function test_no_bidding_timer_source_file_references_expiration_date(): void
    {
        $base  = dirname(__DIR__, 3);
        $paths = [
            'app/Services/Offers/BiddingWindow.php',
            'app/Services/Offers/BiddingWindowService.php',
            'app/Http/Livewire/OfferListing/Concerns/StampsBiddingActivation.php',
        ];

        foreach ($paths as $relative) {
            $full = $base . '/' . $relative;
            $this->assertFileExists($full);

            $lines = file($full, FILE_IGNORE_NEW_LINES);

            foreach ($lines as $i => $line) {
                // Prose in a docblock may name the field to explain why it is
                // banned; executable code may not touch it at all.
                $trimmed = ltrim($line);
                $isComment = str_starts_with($trimmed, '*')
                    || str_starts_with($trimmed, '//')
                    || str_starts_with($trimmed, '/*');

                if ($isComment) {
                    continue;
                }

                $this->assertStringNotContainsString(
                    'expiration_date',
                    $line,
                    sprintf(
                        "Invariant 10 violated: %s:%d references expiration_date in executable code.\n"
                        . "The listing expiration date is permanently independent of the bidding period.",
                        $relative,
                        $i + 1
                    )
                );
            }
        }
    }

    /**
     * Repository-wide guard over the whole offer-listing consumer surface.
     *
     * Every countdown, sidebar, hub and sort ordering across all four roles must
     * be free of the banned derivations. This is the test that would have caught
     * c729357b1 in June.
     */
    public function test_no_offer_listing_surface_derives_a_bidding_deadline(): void
    {
        $base = dirname(__DIR__, 3);

        $files = array_merge(
            glob($base . '/resources/views/offer-listing/*/view.blade.php'),
            glob($base . '/resources/views/offer-listing/*/search.blade.php'),
            glob($base . '/app/Http/Controllers/*OfferListingController.php'),
        );

        $this->assertGreaterThanOrEqual(12, count($files), 'Expected all four roles to be scanned.');

        // Deadline arithmetic of any shape, and any read of a listing-expiry or
        // duration field for timing purposes.
        $bannedPatterns = [
            '/->addDays\(\$_[a-zA-Z]+\)/'      => 'deadline arithmetic (addDays)',
            '/->addHours\(\$_[a-zA-Z]+\)/'     => 'deadline arithmetic (addHours)',
            '/->addWeeks\(\$_[a-zA-Z]+\)/'     => 'deadline arithmetic (addWeeks)',
            '/->addMinutes\(\$_[a-zA-Z]+\)/'   => 'deadline arithmetic (addMinutes)',
            '/diffInSeconds\(\$_(end|timerEnd)/i' => 'runtime countdown computation',
            "/INTERVAL '1 day'/"               => 'SQL deadline arithmetic',
            "/meta_key = 'expiration_date'/"   => 'expiration_date in SQL ordering',
        ];

        $violations = [];

        foreach ($files as $file) {
            $relative = str_replace($base . '/', '', $file);

            foreach (file($file, FILE_IGNORE_NEW_LINES) as $i => $line) {
                $trimmed = ltrim($line);

                // Prose may name a banned construct to explain why it is banned.
                if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')
                    || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '{{--')) {
                    continue;
                }

                foreach ($bannedPatterns as $pattern => $label) {
                    if (preg_match($pattern, $line)) {
                        $violations[] = sprintf('%s:%d — %s', $relative, $i + 1, $label);
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Banned bidding-deadline derivation found on the offer-listing surface:\n  "
            . implode("\n  ", $violations)
            . "\n\nEvery countdown must read the stored offer_auctions.bidding_ends_at."
        );
    }

    public function test_bidding_window_service_performs_no_deadline_arithmetic_outside_activation(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Services/Offers/BiddingWindowService.php'
        );

        // addDuration is the single sanctioned arithmetic entry point and must
        // be reachable from exactly one caller: markActivated().
        $callSites = preg_match_all('/\$this->addDuration\(/', $source);

        $this->assertSame(
            1,
            $callSites,
            'addDuration() must be called exactly once (from markActivated). '
            . 'Any additional call site reintroduces runtime deadline recomputation.'
        );
    }

    // =====================================================================
    // 10. Containment for surfaces OUTSIDE the offer-listing globs above.
    //
    //     Two blind spots were identified in the 2026-07-29 regression
    //     reopening:
    //
    //       a) the duplicate offer-detail surface `/offer/listing/view/{id}`
    //          (AgentController::offerListingView + agent/offer-listing-view
    //          .blade.php), which renders a listing detail page but is not
    //          matched by the offer-listing/* globs; and
    //
    //       b) the legacy Hire-an-Agent auction views, a recorded scope
    //          boundary that still derives deadlines from created_at +
    //          auction_time and expiration_date.
    //
    //     (a) is clean today and is locked clean. (b) is NOT repaired here —
    //     repairing it is a separate scope decision. It is instead frozen as an
    //     inventory so no NEW surface can join it unnoticed.
    // =====================================================================

    /**
     * Deadline arithmetic of any shape. Broader than the offer-listing patterns
     * because these files use bare locals rather than the `$_name` convention.
     */
    private const BANNED_DEADLINE_PATTERNS = [
        '/->addDays\(/'       => 'deadline arithmetic (addDays)',
        '/->addHours\(/'      => 'deadline arithmetic (addHours)',
        '/->addWeeks\(/'      => 'deadline arithmetic (addWeeks)',
        '/->addMinutes\(/'    => 'deadline arithmetic (addMinutes)',
        '/->addMonths\(/'     => 'deadline arithmetic (addMonths)',
        '/diffInSeconds\(/'   => 'runtime countdown computation',
        '/diffInDays\(/'      => 'runtime countdown computation',
    ];

    /**
     * Scan one file for banned deadline derivations, ignoring prose.
     *
     * @return array<int, string>  "relative:line — label"
     */
    private function deadlineDerivationsIn(string $absolutePath, string $base): array
    {
        if (! is_file($absolutePath)) {
            return [];
        }

        $relative   = str_replace($base . '/', '', $absolutePath);
        $violations = [];

        foreach (file($absolutePath, FILE_IGNORE_NEW_LINES) as $i => $line) {
            $trimmed = ltrim($line);

            if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')
                || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '{{--')) {
                continue;
            }

            foreach (self::BANNED_DEADLINE_PATTERNS as $pattern => $label) {
                if (preg_match($pattern, $line)) {
                    $violations[] = sprintf('%s:%d — %s', $relative, $i + 1, $label);
                }
            }
        }

        return $violations;
    }

    /**
     * The duplicate offer-detail surface must never grow a countdown.
     *
     * `/offer/listing/view/{id}` (routes/web.php) renders a listing detail page
     * from a DIFFERENT table than the repaired `/offer-listing/{role}/view/{id}`
     * page. It currently displays Expiration Date and Auction Time as static
     * rows and computes nothing. If a countdown is ever added there it must read
     * the canonical window like every other surface — never local arithmetic.
     */
    public function test_duplicate_offer_detail_surface_derives_no_bidding_deadline(): void
    {
        $base = dirname(__DIR__, 3);

        $surfaces = [
            $base . '/resources/views/agent/offer-listing-view.blade.php',
            $base . '/app/Http/Controllers/AgentController.php',
        ];

        $violations = [];

        foreach ($surfaces as $surface) {
            $this->assertFileExists($surface);
            $violations = array_merge($violations, $this->deadlineDerivationsIn($surface, $base));
        }

        $this->assertSame(
            [],
            $violations,
            "The duplicate offer-detail surface has grown a bidding-deadline derivation:\n  "
            . implode("\n  ", $violations)
            . "\n\nThis page renders a listing detail view. Any countdown it shows must read the "
            . "stored offer_auctions.bidding_ends_at via BiddingWindowService — never local "
            . "arithmetic and never expiration_date (Invariants 3, 4, 5, 9, 10)."
        );
    }

    /**
     * Frozen inventory of legacy Hire-an-Agent surfaces that still self-compute.
     *
     * These are a RECORDED SCOPE BOUNDARY, not an oversight: the Hire-an-Agent
     * auction is a separate product with no OfferAuction linkage, so the
     * canonical window does not exist for it. They are deliberately NOT repaired
     * here.
     *
     * The risk this test addresses is different: because those files sit on the
     * same listing rows the repaired offer-listing pages render, a new timed
     * surface could quietly be added alongside them and inherit the same defect.
     * Freezing the inventory means:
     *
     *   - a NEW file that self-computes a deadline fails this test loudly;
     *   - REPAIRING one of these fails this test too, prompting the fixer to
     *     delete its entry rather than leave a stale exemption behind.
     *
     * Do not add entries to make a failure go away. A new entry is an
     * architectural decision requiring the same approval as any other deviation
     * (Invariants 11 and 12).
     */
    private const LEGACY_SELF_COMPUTING_SURFACES = [
        'resources/views/hire_landlord_agent/search.blade.php',
        'resources/views/hire_landlord_agent/view.blade.php',
        'resources/views/hire_seller_agent/search.blade.php',
        'resources/views/hire_seller_agent/view.blade.php',
        'resources/views/hire_tenant_agent/search.blade.php',
        'resources/views/hire_tenant_agent/view.blade.php',
        'resources/views/search-buyer-agent-auctions.blade.php',
        'resources/views/search-buyer-criteria-auctions.blade.php',
        'resources/views/search-seller-agent-auctions.blade.php',
        'resources/views/search-service-auctions.blade.php',
    ];

    public function test_no_new_legacy_surface_starts_deriving_a_bidding_deadline(): void
    {
        $base = dirname(__DIR__, 3);

        $candidates = array_merge(
            glob($base . '/resources/views/hire_*/*.blade.php'),
            glob($base . '/resources/views/search-*-auctions.blade.php'),
        );

        $this->assertNotEmpty($candidates, 'Expected the legacy auction surfaces to be scannable.');

        $offenders = [];

        foreach ($candidates as $file) {
            if ($this->deadlineDerivationsIn($file, $base) !== []) {
                $offenders[] = str_replace($base . '/', '', $file);
            }
        }

        sort($offenders);
        $known = self::LEGACY_SELF_COMPUTING_SURFACES;
        sort($known);

        $newlyOffending = array_values(array_diff($offenders, $known));
        $nowClean       = array_values(array_diff($known, $offenders));

        $this->assertSame(
            [],
            $newlyOffending,
            "A legacy surface began deriving a bidding deadline locally:\n  "
            . implode("\n  ", $newlyOffending)
            . "\n\nNew timed surfaces must read the canonical persisted window "
            . "(BiddingWindowService), never created_at + auction_time, never expiration_date, "
            . "and never ad-hoc duration arithmetic."
        );

        $this->assertSame(
            [],
            $nowClean,
            "These files no longer self-compute a deadline — good. Remove them from "
            . "LEGACY_SELF_COMPUTING_SURFACES so the inventory does not carry a stale exemption:\n  "
            . implode("\n  ", $nowClean)
        );
    }

    /**
     * The canonical timer entry point must stay unreachable from legacy prose.
     *
     * A cheap, direct assertion that the repaired offer-listing detail views
     * still resolve their countdown through the shared service rather than
     * having quietly reverted to a local computation.
     */
    public function test_every_offer_listing_detail_view_reads_the_shared_window_object(): void
    {
        $base  = dirname(__DIR__, 3);
        $views = glob($base . '/resources/views/offer-listing/*/view.blade.php');

        $this->assertCount(4, $views, 'All four role detail views must exist.');

        foreach ($views as $view) {
            $relative = str_replace($base . '/', '', $view);
            $source   = file_get_contents($view);

            $this->assertStringContainsString(
                '$biddingWindow',
                $source,
                "{$relative} must resolve its countdown from the shared BiddingWindow object "
                . 'supplied by the controller.'
            );
        }
    }
}
