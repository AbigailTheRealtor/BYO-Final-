<?php

namespace Tests\Feature\Offers;

use App\Models\BuyerAgentAuction;
use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Services\Offers\BiddingWindowService;
use App\Services\Offers\ListingOfferAuctionLinker;
use App\Services\Offers\OfferWorkflowFacade;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Timed listings fail closed.
 *
 * A Bidding Period listing with no canonical window has no deadline, and a bid
 * accepted against no deadline cannot be adjudicated — there is no defensible
 * answer to "was this bid in time?". Such a listing refuses new offers outright
 * rather than accepting them into an undefined window.
 *
 * Distinct from a CLOSED window, which has a real deadline that has passed. The
 * two carry separate messages and separate reasons; neither ever invents a
 * deadline from expiration_date, created_at, auction_time or the submission time.
 */
class UninitializedBiddingWindowFailsClosedTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['config']->set('offer.playoff_access.allowed_user_ids', '*');
        Notification::fake();
    }

    // ------------------------------------------------------------- fixtures

    private function listing(string $role, array $meta = []): BuyerAgentAuction|TenantAgentAuction
    {
        $user  = User::factory()->create(['user_type' => $role]);
        $class = $role === 'buyer' ? BuyerAgentAuction::class : TenantAgentAuction::class;

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

    /** A listing published before Stage 1: linked auction, both timers NULL. */
    private function uninitialized(string $role, array $meta = []): array
    {
        $listing = $this->listing($role, $meta);
        $oa      = app(ListingOfferAuctionLinker::class)->ensureFor($listing, $role);

        return [$listing->fresh('meta'), $oa->fresh()];
    }

    private function initialized(string $role, string $duration = '5 Days', ?CarbonImmutable $at = null): array
    {
        [$listing, $oa] = $this->uninitialized($role);

        app(BiddingWindowService::class)->markActivated($oa, $duration, $at ?? CarbonImmutable::now());

        return [$listing, $oa->fresh()];
    }

    private function bidder(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    public static function roleProvider(): array
    {
        return ['buyer' => ['buyer'], 'tenant' => ['tenant']];
    }

    // =====================================================================
    // Draft creation is refused, and nothing is persisted.
    // =====================================================================

    /** @dataProvider roleProvider */
    public function test_uninitialized_timed_listing_rejects_offer_submission(string $role): void
    {
        [, $oa] = $this->uninitialized($role);

        $response = $this->actingAs($this->bidder())
            ->postJson(route('offers.store'), ['offer_auction_id' => $oa->id, 'role' => $role]);

        $response->assertStatus(422);
        $this->assertStringContainsString('has not been initialized', $response->json('message'));
    }

    /** @dataProvider roleProvider */
    public function test_no_offer_row_is_created_after_rejection(string $role): void
    {
        [, $oa] = $this->uninitialized($role);

        $this->actingAs($this->bidder())
            ->postJson(route('offers.store'), ['offer_auction_id' => $oa->id, 'role' => $role]);

        $this->assertSame(0, Offer::where('offer_auction_id', $oa->id)->count());
    }

    /** @dataProvider roleProvider */
    public function test_no_canonical_timestamp_is_written_during_submission(string $role): void
    {
        [, $oa] = $this->uninitialized($role);

        $this->actingAs($this->bidder())
            ->postJson(route('offers.store'), ['offer_auction_id' => $oa->id, 'role' => $role]);

        $after = $oa->fresh();
        $this->assertNull($after->bidding_starts_at, 'Submission must never start the clock.');
        $this->assertNull($after->bidding_ends_at, 'Submission must never set a deadline.');
    }

    /** @dataProvider roleProvider */
    public function test_no_deadline_is_fabricated_from_any_other_timestamp(string $role): void
    {
        // Every banned source is present and plausible-looking.
        [, $oa] = $this->uninitialized($role, [
            'expiration_date' => '2026-12-31',
            'auction_time'    => '5 Days',
        ]);

        $this->actingAs($this->bidder())
            ->postJson(route('offers.store'), ['offer_auction_id' => $oa->id, 'role' => $role]);

        $window = app(BiddingWindowService::class)->forOfferAuction($oa->fresh());

        $this->assertTrue($window->isUninitialized());
        $this->assertNull($window->endsAt, 'No deadline may be derived from expiration_date, created_at or auction_time.');
        $this->assertFalse($window->isClosed(), 'Uninitialized is not the same as closed.');
    }

    // =====================================================================
    // The submit backstop refuses too, even past the draft-creation gate.
    // =====================================================================

    /** @dataProvider roleProvider */
    public function test_submit_backstop_refuses_an_uninitialized_window(string $role): void
    {
        [, $oa] = $this->uninitialized($role);
        $bidder = $this->bidder();

        // A draft that predates this rule, or was created by a direct write.
        $offer = Offer::create([
            'user_id'          => $bidder->id,
            'offer_auction_id' => $oa->id,
            'role'             => $role,
            'status'           => 'draft',
        ]);

        $result = app(OfferWorkflowFacade::class)->submit($offer, $bidder->id, $role);

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('has not been initialized', $result['reason']);
        $this->assertSame('draft', $offer->fresh()->status);
        $this->assertNull($offer->fresh()->submitted_at);
        $this->assertNull($oa->fresh()->bidding_ends_at);
    }

    /** @dataProvider roleProvider */
    public function test_permission_gate_hides_submit_for_an_uninitialized_window(string $role): void
    {
        [, $oa] = $this->uninitialized($role);
        $bidder = $this->bidder();

        $offer = Offer::create([
            'user_id'          => $bidder->id,
            'offer_auction_id' => $oa->id,
            'role'             => $role,
            'status'           => 'draft',
        ]);

        $actions = app(\App\Services\Offers\OfferAvailableActionsService::class)
            ->forOffer($offer, $bidder->id, $role);

        $this->assertFalse($actions['can_submit']);
    }

    // =====================================================================
    // A properly initialized window still works.
    // =====================================================================

    /** @dataProvider roleProvider */
    public function test_initialized_open_window_accepts_submission(string $role): void
    {
        [, $oa] = $this->initialized($role);

        $response = $this->actingAs($this->bidder())
            ->postJson(route('offers.store'), ['offer_auction_id' => $oa->id, 'role' => $role]);

        $response->assertStatus(201);
        $this->assertSame(1, Offer::where('offer_auction_id', $oa->id)->count());
    }

    /** @dataProvider roleProvider */
    public function test_closed_canonical_window_still_rejects_with_the_closed_message(string $role): void
    {
        [, $oa] = $this->initialized($role, '5 Days', CarbonImmutable::now()->subDays(30));

        $response = $this->actingAs($this->bidder())
            ->postJson(route('offers.store'), ['offer_auction_id' => $oa->id, 'role' => $role]);

        $response->assertStatus(422);
        $this->assertStringContainsString('has closed', $response->json('message'));
        $this->assertStringNotContainsString('has not been initialized', $response->json('message'));
        $this->assertSame(0, Offer::where('offer_auction_id', $oa->id)->count());
    }

    // =====================================================================
    // Traditional listings are untouched.
    // =====================================================================

    /** @dataProvider roleProvider */
    public function test_traditional_listing_submission_is_unaffected(string $role): void
    {
        [, $oa] = $this->uninitialized($role, ['auction_type' => 'Traditional', 'auction_time' => '']);

        $response = $this->actingAs($this->bidder())
            ->postJson(route('offers.store'), ['offer_auction_id' => $oa->id, 'role' => $role]);

        $response->assertStatus(201);
        $this->assertSame(1, Offer::where('offer_auction_id', $oa->id)->count());
        $this->assertNull($oa->fresh()->bidding_ends_at, 'A Traditional listing never gains a window.');
    }

    // =====================================================================
    // Offers that predate the change stay readable and attached.
    // =====================================================================

    /** @dataProvider roleProvider */
    public function test_offers_attached_before_this_change_are_preserved(string $role): void
    {
        [$listing, $oa] = $this->uninitialized($role);
        $bidder = $this->bidder();

        $legacyOffer = Offer::create([
            'user_id'          => $bidder->id,
            'offer_auction_id' => $oa->id,
            'role'             => $role,
            'status'           => 'submitted',
            'submitted_at'     => CarbonImmutable::now()->subDays(3),
        ]);

        // A new submission is refused...
        $this->actingAs($this->bidder())
            ->postJson(route('offers.store'), ['offer_auction_id' => $oa->id, 'role' => $role])
            ->assertStatus(422);

        // ...but the existing offer is untouched and still resolves.
        $fresh = $legacyOffer->fresh();
        $this->assertNotNull($fresh, 'The pre-existing offer must survive.');
        $this->assertSame('submitted', $fresh->status);
        $this->assertSame($oa->id, $fresh->offer_auction_id);
        $this->assertNotNull($fresh->offerAuction, 'It must still resolve to its auction.');

        // And publication later adopts the same auction, stamping it once.
        app(ListingOfferAuctionLinker::class)->ensureFor($listing, $role);
        app(BiddingWindowService::class)->markActivated($oa->fresh(), '5 Days');

        $this->assertSame($oa->id, $legacyOffer->fresh()->offer_auction_id);
        $this->assertSame(
            1,
            OfferAuction::where('listing_id', ListingOfferAuctionLinker::criteriaKey($role, $listing->id))->count()
        );
    }
}
