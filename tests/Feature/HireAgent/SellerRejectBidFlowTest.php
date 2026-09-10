<?php

namespace Tests\Feature\HireAgent;

use App\Models\AcceptedBidSummary;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionBid;
use App\Models\User;
use App\Notifications\BidRejectedNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Seller Hire Agent — declining an incoming agent proposal, end to end.
 *
 * ── WHY THIS EXISTS ─────────────────────────────────────────────────────────
 *
 * `POST hire/agent/seller/bid/reject` was registered inside `agentAuth`, so every
 * Seller was redirected to the dashboard before `rejectSABid()` ran and no Seller
 * could decline a bid. Nothing went red, because no test exercised the Seller
 * reject path at all — the accept path got its coverage in PR #143, and reject was
 * explicitly left out of that change.
 *
 * The route contract is pinned separately by SellerRejectBidRouteTest; this class
 * is about what actually happens to the data.
 *
 * ── THE CONTRACT, AS THE CONTROLLER ALREADY IMPLEMENTS IT ───────────────────
 *
 * Rejection is deliberately far smaller than acceptance, and the assertions below
 * are written from `SellerAgentAuctionController::rejectSABid()` as it stands. It:
 *
 *   1. marks THAT bid `rejected` and stamps `accepted_date`;
 *   2. notifies the bidding agent;
 *   3. redirects back with a success message.
 *
 * And, just as importantly, it does none of what acceptance does. Declining one
 * proposal is not a decision about the listing or about anybody else's proposal,
 * so the negative assertions here are the substance of the contract rather than
 * padding: no `is_sold`, no `sold_date`, no `listing_status` meta, no `UserAgent`
 * hire, no Accepted Bid Summary, and every sibling bid untouched. A future edit
 * that "shares code" between accept and reject would fail on those.
 *
 * Notifications are wrapped in `try/catch` in the controller — a notification
 * failure is logged and must not undo the rejection — so `Notification::fake()`
 * here asserts the intended delivery without making it load-bearing.
 *
 * ── TWO DELIBERATE CHOICES ABOUT WHAT IS ASSERTED ───────────────────────────
 *
 * `seller_agent_auction_bids.accepted` does NOT start null. It was created as a
 * boolean defaulting to false and later widened to varchar defaulting to `'0'` by
 * a pgsql-only migration, so an undecided bid reads back as `0` on SQLite and
 * `'0'` on PostgreSQL. `assertUndecided()` therefore asserts what the controller
 * itself branches on — the value is neither `accepted` nor `rejected` — instead
 * of hardcoding a sentinel that differs by driver.
 *
 * The flash messages are NOT asserted. `rejectSABid()` flashes `success`/`error`
 * via `redirect()->back()->with(...)`, but by the time a test inspects the store
 * the flash has been aged and only the key name survives in `_flash.old`. Asserting
 * on that would pin a harness artefact rather than the controller. The refusal
 * paths are pinned on the durable evidence instead: the bid's state and its
 * `accepted_date` are unchanged. (TenantAgentAuctionBidTest reaches the same
 * conclusion for the same endpoints.)
 */
class SellerRejectBidFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    // =========================================================================
    // The happy path — the listing owner declines a proposal
    // =========================================================================

    public function test_listing_owner_can_reject_an_incoming_agent_bid(): void
    {
        $s = $this->scenario();

        $response = $this->actingAs($s['owner'])->post(route('rejectSABid'), $s['payload']);

        $this->assertNotSame(403, $response->getStatusCode(), 'the listing owner was refused');

        $bid = $s['bid']->refresh();

        $this->assertSame('rejected', (string) $bid->accepted, 'bid not marked rejected');
        $this->assertNotNull($bid->accepted_date, 'accepted_date not stamped');
    }

    /**
     * The defect this branch fixes, stated directly: a Seller is not an agent, so
     * under the `agentAuth` registration this request was redirected to the
     * dashboard and the bid was never touched.
     */
    public function test_seller_is_not_bounced_for_not_being_an_agent(): void
    {
        $s = $this->scenario();

        $this->assertSame('seller', $s['owner']->user_type, 'the fixture must not be an agent account');

        $response = $this->actingAs($s['owner'])->post(route('rejectSABid'), $s['payload']);

        $response->assertRedirect();
        $this->assertStringNotContainsString(
            'dashboard',
            (string) $response->headers->get('Location'),
            'the Seller was redirected to the dashboard — agentAuth is back on the reject route'
        );
        $this->assertSame('rejected', (string) $s['bid']->refresh()->accepted);
    }

    public function test_rejecting_redirects_back_rather_than_to_a_gate(): void
    {
        $s = $this->scenario();

        $response = $this->actingAs($s['owner'])->post(route('rejectSABid'), $s['payload']);

        $response->assertRedirect();

        $location = (string) $response->headers->get('Location');

        $this->assertStringNotContainsString('login', $location, 'the Seller was sent to login');
        $this->assertStringNotContainsString('dashboard', $location, 'the Seller was bounced by a role gate');
    }

    public function test_rejecting_notifies_the_bidding_agent(): void
    {
        $s = $this->scenario();

        $this->actingAs($s['owner'])->post(route('rejectSABid'), $s['payload']);

        Notification::assertSentTo($s['agent'], BidRejectedNotification::class);
        Notification::assertNotSentTo($s['rival'], BidRejectedNotification::class);
    }

    // =========================================================================
    // Rejection is NOT acceptance — the listing and the other bids are untouched
    // =========================================================================

    public function test_rejecting_leaves_the_listing_open(): void
    {
        $s = $this->scenario();

        $this->actingAs($s['owner'])->post(route('rejectSABid'), $s['payload']);

        $auction = $s['auction']->refresh();

        $this->assertFalse((bool) $auction->is_sold, 'declining a proposal must not sell the listing');
        $this->assertNull($auction->sold_date, 'declining a proposal must not stamp sold_date');
        $this->assertNotSame(
            'Hired Agent',
            (string) $this->meta($auction, 'listing_status'),
            'declining a proposal must not mark the listing as having hired an agent'
        );
    }

    public function test_rejecting_one_bid_leaves_the_competing_bids_alone(): void
    {
        $s = $this->scenario();

        $this->actingAs($s['owner'])->post(route('rejectSABid'), $s['payload']);

        $rival = $s['rivalBid']->refresh();

        $this->assertSame('rejected', (string) $s['bid']->refresh()->accepted, 'target bid not rejected');
        $this->assertUndecided($rival, 'a competing bid was changed by rejecting a different one');
        $this->assertNull($rival->accepted_date, 'a competing bid was stamped by rejecting a different one');
    }

    public function test_rejecting_does_not_hire_the_agent_or_produce_a_summary(): void
    {
        $s = $this->scenario();

        $this->actingAs($s['owner'])->post(route('rejectSABid'), $s['payload']);

        $this->assertDatabaseMissing('user_agents', [
            'user_id'  => $s['owner']->id,
            'agent_id' => $s['agent']->id,
            'type'     => 'seller',
        ]);

        $this->assertNull(
            AcceptedBidSummary::where('accepted_bid_id', $s['bid']->id)->first(),
            'declining a proposal must not generate an Accepted Bid Summary'
        );
    }

    // =========================================================================
    // Security — these must keep failing
    // =========================================================================

    public function test_an_unrelated_consumer_cannot_reject_someone_elses_bid(): void
    {
        $s = $this->scenario();
        $outsider = User::factory()->create(['user_type' => 'seller']);

        $response = $this->actingAs($outsider)->post(route('rejectSABid'), $s['payload']);

        $response->assertForbidden();
        $this->assertUndecided($s['bid']->refresh(), 'an outsider rejected a bid');
    }

    /**
     * The inverse of the defect. `agentAuth` admitted ONLY agents, so the one
     * account the middleware let through was the one the controller must refuse.
     * Removing the middleware must not turn that refusal off.
     */
    public function test_the_bidding_agent_cannot_reject_their_own_proposal(): void
    {
        $s = $this->scenario();

        $this->assertSame('agent', $s['agent']->user_type, 'the fixture must be an agent account');

        $response = $this->actingAs($s['agent'])->post(route('rejectSABid'), $s['payload']);

        $response->assertForbidden();
        $this->assertUndecided($s['bid']->refresh(), 'the bidding agent rejected their own proposal');
    }

    /**
     * `rejectSABid()` resolves the listing FROM the bid and treats a supplied
     * `auction_id` as a cross-check, so substituting a bid from another Seller's
     * listing fails ownership; substituting one from the caller's OWN other
     * listing fails the pairing check. Both are asserted.
     */
    public function test_a_bid_belonging_to_another_listing_is_refused(): void
    {
        $s = $this->scenario();
        $other = $this->scenario();

        $response = $this->actingAs($s['owner'])->post(route('rejectSABid'), [
            'bid_id'     => $other['bid']->id,
            'auction_id' => $s['auction']->id,
        ]);

        $response->assertForbidden();
        $this->assertUndecided($other['bid']->refresh(), 'a bid from another listing was rejected');
    }

    public function test_a_mismatched_auction_id_on_the_owners_own_bid_is_refused(): void
    {
        $s = $this->scenario();
        $secondListing = SellerAgentAuction::forceCreate([
            'user_id' => $s['owner']->id,
            'address' => '99 Second St',
        ]);

        $response = $this->actingAs($s['owner'])->post(route('rejectSABid'), [
            'bid_id'     => $s['bid']->id,
            'auction_id' => $secondListing->id,
        ]);

        $response->assertForbidden();
        $this->assertUndecided($s['bid']->refresh(), 'the auction_id cross-check did not hold');
    }

    public function test_a_bid_cannot_be_rejected_on_an_expired_listing(): void
    {
        $s = $this->scenario();
        $s['auction']->saveMeta('expiration_date', now()->subDay()->toDateString());

        $this->actingAs($s['owner'])->post(route('rejectSABid'), $s['payload']);

        $this->assertUndecided($s['bid']->refresh(), 'an expired listing rejected a bid');
    }

    public function test_an_already_rejected_bid_is_not_rejected_twice(): void
    {
        $s = $this->scenario();

        $this->actingAs($s['owner'])->post(route('rejectSABid'), $s['payload']);
        $firstRejectedAt = (string) $s['bid']->refresh()->accepted_date;

        Notification::fake();

        $this->actingAs($s['owner'])->post(route('rejectSABid'), $s['payload']);

        $this->assertSame('rejected', (string) $s['bid']->refresh()->accepted);
        $this->assertSame(
            $firstRejectedAt,
            (string) $s['bid']->refresh()->accepted_date,
            're-rejecting re-stamped accepted_date'
        );
        Notification::assertNothingSent();
    }

    public function test_an_accepted_bid_cannot_be_rejected(): void
    {
        $s = $this->scenario();
        $s['bid']->forceFill([
            'accepted'      => 'accepted',
            'accepted_date' => now()->subHour()->format('Y-m-d H:i:s'),
        ])->save();

        $this->actingAs($s['owner'])->post(route('rejectSABid'), $s['payload']);

        $this->assertSame(
            'accepted',
            (string) $s['bid']->refresh()->accepted,
            'an accepted bid was flipped to rejected'
        );
    }

    public function test_a_guest_cannot_reject_a_bid(): void
    {
        $s = $this->scenario();

        $response = $this->post(route('rejectSABid'), $s['payload']);

        $response->assertRedirect(route('login'));
        $this->assertUndecided($s['bid']->refresh(), 'a guest rejected a bid');
    }

    // =========================================================================
    // Fixtures
    // =========================================================================

    /**
     * One Seller listing owned by `owner`, with a bid from `agent` and a rival bid
     * from a second agent, plus the payload the Seller's reject form posts.
     *
     * @return array{owner: User, agent: User, rival: User, auction: SellerAgentAuction, bid: SellerAgentAuctionBid, rivalBid: SellerAgentAuctionBid, payload: array<string, int>}
     */
    private function scenario(): array
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        $agent = User::factory()->asAgent()->create();
        $rival = User::factory()->asAgent()->create();

        // Decoy listings first, so an auction id and a bid id can never coincide —
        // an assertion written while those numbers happened to be equal would prove
        // neither. (Same reasoning as HireAgentAcceptBidFlowTest.)
        SellerAgentAuction::forceCreate(['user_id' => $owner->id, 'address' => '1 Decoy']);
        SellerAgentAuction::forceCreate(['user_id' => $owner->id, 'address' => '2 Decoy']);

        $auction  = SellerAgentAuction::forceCreate(['user_id' => $owner->id, 'address' => '10 Seller St']);
        $bid      = SellerAgentAuctionBid::forceCreate(['seller_agent_auction_id' => $auction->id, 'user_id' => $agent->id]);
        $rivalBid = SellerAgentAuctionBid::forceCreate(['seller_agent_auction_id' => $auction->id, 'user_id' => $rival->id]);

        $this->assertNotSame((int) $auction->id, (int) $bid->id);
        $this->assertUndecided($bid->refresh(), 'a new bid must start undecided');

        return [
            'owner'    => $owner,
            'agent'    => $agent,
            'rival'    => $rival,
            'auction'  => $auction,
            'bid'      => $bid,
            'rivalBid' => $rivalBid,
            'payload'  => ['bid_id' => $bid->id, 'auction_id' => $auction->id],
        ];
    }

    /**
     * A bid that has not been decided. See the class docblock: the stored value is
     * driver-dependent (`0` / `'0'`), so this asserts the property the controller
     * actually tests for rather than a literal.
     */
    private function assertUndecided(SellerAgentAuctionBid $bid, string $message): void
    {
        $this->assertNotContains(
            (string) $bid->accepted,
            ['accepted', 'rejected'],
            $message
        );
    }

    private function meta(Model $auction, string $key): ?string
    {
        return optional($auction->meta()->where('meta_key', $key)->first())->meta_value;
    }
}
