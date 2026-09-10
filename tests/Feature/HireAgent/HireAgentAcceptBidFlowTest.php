<?php

namespace Tests\Feature\HireAgent;

use App\Models\AcceptedBidSummary;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionBid;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionBid;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionBid;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionBid;
use App\Models\User;
use App\Models\UserAgent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Hire Agent — accepting a bid and hiring the agent, for all four roles.
 *
 * ── WHY THIS EXISTS ─────────────────────────────────────────────────────────
 *
 * The BidYourAgent independent-launch audit found that `hire/agent/seller/bid/accept`
 * was registered twice and that the surviving registration sat inside `agentAuth`,
 * which made Seller bid acceptance unreachable — and that NO end-to-end test covered
 * accepting a Hire Agent bid on the Seller, Buyer or Landlord roles, so nothing went
 * red. (Tenant had coverage in TenantAgentAuctionBidTest; this class does not repeat
 * that file's counter-bid or reject cases.)
 *
 * The route contract itself is pinned separately by SellerAcceptBidRouteTest.
 *
 * ── THE SHARED CONTRACT ─────────────────────────────────────────────────────
 *
 * All four controllers do the same six things inside one transaction, and this class
 * asserts each of them per role rather than assuming symmetry:
 *
 *   1. the bid becomes `accepted` with an `accepted_date`;
 *   2. the listing becomes `is_sold` with a `sold_date`;
 *   3. `listing_status` meta becomes `Hired Agent`;
 *   4. every sibling bid on that listing becomes `rejected`;
 *   5. a `UserAgent` row records the hire, typed by role;
 *   6. the caller lands in the Accepted Bid Summary flow.
 *
 * ── WHERE THE ROLES GENUINELY DIFFER ────────────────────────────────────────
 *
 * Not normalised away, because the differences are real:
 *
 *   * Buyer and Landlord resolve the listing from the REQUEST's `auction_id`; Seller
 *     and Tenant resolve it from the bid and treat `auction_id` as a cross-check.
 *   * Buyer writes `UserAgent.user_id` from `$auction->user_id`; the other three use
 *     `Auth::id()`. Those coincide for the listing owner, which is the only caller
 *     that gets this far.
 *   * Landlord additionally stamps `UserAgent.property_id`.
 *
 * Summary generation and both notifications are wrapped in `try/catch` in every
 * controller — a failure there is logged and must not roll back the hire — so the
 * summary assertion checks the redirect the summary produced, not the HTML.
 */
class HireAgentAcceptBidFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    /** @return array<string, array{0: string}> */
    public function roleProvider(): array
    {
        return [
            'seller'   => ['seller'],
            'buyer'    => ['buyer'],
            'landlord' => ['landlord'],
            'tenant'   => ['tenant'],
        ];
    }

    // =========================================================================
    // The happy path — the listing owner hires an agent
    // =========================================================================

    /** @dataProvider roleProvider */
    public function test_listing_owner_can_accept_a_bid_and_hire_the_agent(string $role): void
    {
        $this->skipIfHireCannotBeRecorded($role);

        $s = $this->scenario($role);

        $response = $this->actingAs($s['owner'])->post(route($s['route']), $s['payload']);

        $this->assertNotSame(403, $response->getStatusCode(), "{$role}: the listing owner was refused");

        $s['bid']->refresh();
        $s['auction']->refresh();

        $this->assertSame('accepted', (string) $s['bid']->accepted, "{$role}: bid not accepted");
        $this->assertNotNull($s['bid']->accepted_date, "{$role}: accepted_date not stamped");
    }

    /**
     * The Seller case the audit was actually about: a Seller is not an agent, so under
     * the shadowing `agentAuth` registration this request was redirected to the
     * dashboard and the bid was never touched.
     */
    public function test_seller_is_not_bounced_for_not_being_an_agent(): void
    {
        $s = $this->scenario('seller');

        $this->assertSame('seller', $s['owner']->user_type, 'the fixture must not be an agent account');

        $response = $this->actingAs($s['owner'])->post(route($s['route']), $s['payload']);

        $response->assertRedirect();
        $this->assertStringNotContainsString(
            'dashboard',
            (string) $response->headers->get('Location'),
            'the Seller was redirected to the dashboard — agentAuth is back on the accept route'
        );
        $this->assertSame('accepted', (string) $s['bid']->refresh()->accepted);
    }

    /** @dataProvider roleProvider */
    public function test_accepting_marks_the_listing_sold_and_hired(string $role): void
    {
        $this->skipIfHireCannotBeRecorded($role);

        $s = $this->scenario($role);

        $this->actingAs($s['owner'])->post(route($s['route']), $s['payload']);

        $auction = $s['auction']->refresh();

        $this->assertTrue((bool) $auction->is_sold, "{$role}: is_sold not set");
        $this->assertNotNull($auction->sold_date, "{$role}: sold_date not stamped");
        $this->assertSame(
            'Hired Agent',
            (string) $this->meta($auction, 'listing_status'),
            "{$role}: listing_status meta is not 'Hired Agent'"
        );
    }

    /** @dataProvider roleProvider */
    public function test_accepting_rejects_the_competing_bids(string $role): void
    {
        $this->skipIfHireCannotBeRecorded($role);

        $s = $this->scenario($role);

        $this->actingAs($s['owner'])->post(route($s['route']), $s['payload']);

        $this->assertSame('accepted', (string) $s['bid']->refresh()->accepted, "{$role}: winner not accepted");
        $this->assertSame('rejected', (string) $s['rivalBid']->refresh()->accepted, "{$role}: rival not rejected");
    }

    /** @dataProvider roleProvider */
    public function test_accepting_creates_the_user_agent_relationship(string $role): void
    {
        $this->skipIfHireCannotBeRecorded($role);

        $s = $this->scenario($role);

        $this->actingAs($s['owner'])->post(route($s['route']), $s['payload']);

        $this->assertDatabaseHas('user_agents', [
            'user_id'  => $s['owner']->id,
            'agent_id' => $s['agent']->id,
            'type'     => $role,
        ]);
    }

    /** @dataProvider roleProvider */
    public function test_accepting_produces_a_summary_and_redirects_into_it(string $role): void
    {
        $this->skipIfHireCannotBeRecorded($role);

        $s = $this->scenario($role);

        $response = $this->actingAs($s['owner'])->post(route($s['route']), $s['payload']);

        $summary = AcceptedBidSummary::where('accepted_bid_id', $s['bid']->id)->first();

        $this->assertNotNull($summary, "{$role}: no Accepted Bid Summary was generated");
        $response->assertRedirect(route('accepted-bid-summary.view', $summary->id));
    }

    // =========================================================================
    // Security — these must keep failing
    // =========================================================================

    /** @dataProvider roleProvider */
    public function test_an_unrelated_consumer_cannot_accept_someone_elses_bid(string $role): void
    {
        $s = $this->scenario($role);
        $outsider = User::factory()->create();

        $response = $this->actingAs($outsider)->post(route($s['route']), $s['payload']);

        $response->assertForbidden();
        $this->assertNotSame('accepted', (string) $s['bid']->refresh()->accepted, "{$role}: outsider accepted a bid");
        $this->assertFalse((bool) $s['auction']->refresh()->is_sold, "{$role}: outsider sold a listing");
    }

    /**
     * The bidding agent may not accept their own proposal on the owner's behalf. This
     * is the assertion the `agentAuth` registration inverted: it admitted only agents.
     *
     * @dataProvider roleProvider
     */
    public function test_the_bidding_agent_cannot_accept_their_own_proposal(string $role): void
    {
        $s = $this->scenario($role);

        $response = $this->actingAs($s['agent'])->post(route($s['route']), $s['payload']);

        $response->assertForbidden();
        $this->assertNotSame('accepted', (string) $s['bid']->refresh()->accepted, "{$role}: agent self-accepted");
        $this->assertDatabaseMissing('user_agents', [
            'agent_id' => $s['agent']->id,
            'type'     => $role,
        ]);
    }

    /** @dataProvider roleProvider */
    public function test_a_bid_cannot_be_accepted_on_an_expired_listing(string $role): void
    {
        $s = $this->scenario($role);
        $s['auction']->saveMeta('expiration_date', now()->subDay()->toDateString());

        $this->actingAs($s['owner'])->post(route($s['route']), $s['payload']);

        $this->assertNotSame('accepted', (string) $s['bid']->refresh()->accepted, "{$role}: expired listing accepted a bid");
        $this->assertFalse((bool) $s['auction']->refresh()->is_sold, "{$role}: expired listing was sold");
    }

    /** @dataProvider roleProvider */
    public function test_an_already_accepted_bid_cannot_be_accepted_twice(string $role): void
    {
        $this->skipIfHireCannotBeRecorded($role);

        $s = $this->scenario($role);

        $this->actingAs($s['owner'])->post(route($s['route']), $s['payload']);
        $firstAcceptedAt = (string) $s['bid']->refresh()->accepted_date;

        $this->actingAs($s['owner'])->post(route($s['route']), $s['payload']);

        $this->assertSame('accepted', (string) $s['bid']->refresh()->accepted);
        $this->assertSame($firstAcceptedAt, (string) $s['bid']->refresh()->accepted_date, "{$role}: re-accepted");
        $this->assertSame(
            1,
            UserAgent::where('agent_id', $s['agent']->id)->where('type', $role)->count(),
            "{$role}: a second hire was recorded"
        );
    }

    /**
     * Seller and Tenant resolve the listing from the bid and cross-check any supplied
     * `auction_id`; Buyer and Landlord resolve the listing FROM `auction_id` and then
     * confirm the pairing. Either way a bid from another listing must be refused.
     *
     * @dataProvider roleProvider
     */
    public function test_a_bid_belonging_to_another_listing_is_refused(string $role): void
    {
        $s = $this->scenario($role);
        $other = $this->scenario($role);

        $response = $this->actingAs($s['owner'])->post(route($s['route']), [
            'bid_id'     => $other['bid']->id,
            'auction_id' => $s['auction']->id,
        ]);

        $this->assertNotSame(
            'accepted',
            (string) $other['bid']->refresh()->accepted,
            "{$role}: a bid from another listing was accepted"
        );
        $this->assertFalse((bool) $s['auction']->refresh()->is_sold, "{$role}: the wrong listing was sold");
        $this->assertNotNull($response);
    }

    // =========================================================================
    // Fixtures
    // =========================================================================

    /**
     * One listing owned by `owner`, with a bid from `agent` and a rival bid from a
     * second agent, plus the route name and payload that role's form posts.
     *
     * @return array{owner: User, agent: User, auction: Model, bid: Model, rivalBid: Model, route: string, payload: array<string, int>}
     */
    private function scenario(string $role): array
    {
        $owner = User::factory()->create(['user_type' => $this->ownerUserType($role)]);
        $agent = User::factory()->asAgent()->create();
        $rival = User::factory()->asAgent()->create();

        switch ($role) {
            case 'seller':
                // Decoy listings first, so an auction id and a bid id can never
                // coincide — an assertion written while those numbers happened to be
                // equal would prove neither.
                SellerAgentAuction::forceCreate(['user_id' => $owner->id, 'address' => '1 Decoy']);
                SellerAgentAuction::forceCreate(['user_id' => $owner->id, 'address' => '2 Decoy']);
                $auction  = SellerAgentAuction::forceCreate(['user_id' => $owner->id, 'address' => '10 Seller St']);
                $bid      = SellerAgentAuctionBid::forceCreate(['seller_agent_auction_id' => $auction->id, 'user_id' => $agent->id]);
                $rivalBid = SellerAgentAuctionBid::forceCreate(['seller_agent_auction_id' => $auction->id, 'user_id' => $rival->id]);
                $route    = 'acceptSABid';
                break;

            case 'buyer':
                BuyerAgentAuction::forceCreate(['user_id' => $owner->id, 'address' => '1 Decoy', 'title' => 'Decoy']);
                BuyerAgentAuction::forceCreate(['user_id' => $owner->id, 'address' => '2 Decoy', 'title' => 'Decoy']);
                $auction  = BuyerAgentAuction::forceCreate(['user_id' => $owner->id, 'address' => '20 Buyer Ave', 'title' => 'Buyer listing']);
                $bid      = BuyerAgentAuctionBid::forceCreate(['buyer_agent_auction_id' => $auction->id, 'user_id' => $agent->id]);
                $rivalBid = BuyerAgentAuctionBid::forceCreate(['buyer_agent_auction_id' => $auction->id, 'user_id' => $rival->id]);
                $route    = 'buyer.hire.agent.auction.bid.accept';
                break;

            case 'landlord':
                LandlordAgentAuction::forceCreate(['user_id' => $owner->id]);
                LandlordAgentAuction::forceCreate(['user_id' => $owner->id]);
                $auction  = LandlordAgentAuction::forceCreate(['user_id' => $owner->id]);
                $bid      = LandlordAgentAuctionBid::forceCreate(['landlord_agent_auction_id' => $auction->id, 'user_id' => $agent->id]);
                $rivalBid = LandlordAgentAuctionBid::forceCreate(['landlord_agent_auction_id' => $auction->id, 'user_id' => $rival->id]);
                $route    = 'landlord.hire.agent.auction.bid.accept';
                break;

            case 'tenant':
                TenantAgentAuction::factory()->active()->create(['user_id' => $owner->id]);
                TenantAgentAuction::factory()->active()->create(['user_id' => $owner->id]);
                $auction  = TenantAgentAuction::factory()->active()->create(['user_id' => $owner->id]);
                $bid      = TenantAgentAuctionBid::factory()->active()->create(['tenant_agent_auction_id' => $auction->id, 'user_id' => $agent->id]);
                $rivalBid = TenantAgentAuctionBid::factory()->active()->create(['tenant_agent_auction_id' => $auction->id, 'user_id' => $rival->id]);
                $route    = 'tenant.hire.agent.auction.bid.accept';
                break;

            default:
                $this->fail("unknown role [{$role}]");
        }

        // An auction id and a bid id that happen to be equal would make a pairing
        // assertion prove nothing.
        $this->assertNotSame((int) $auction->id, (int) $bid->id);

        return [
            'owner'    => $owner,
            'agent'    => $agent,
            'auction'  => $auction,
            'bid'      => $bid,
            'rivalBid' => $rivalBid,
            'route'    => $route,
            'payload'  => ['bid_id' => $bid->id, 'auction_id' => $auction->id],
        ];
    }

    /**
     * `users.user_type` has no `landlord` value — a landlord listing is owned by a
     * `seller`-typed account. None of these routes gate on `user_type`; only the
     * controller's ownership check decides, which is the point of the fix.
     */
    private function ownerUserType(string $role): string
    {
        return in_array($role, ['seller', 'landlord'], true) ? 'seller' : $role;
    }

    private function meta(Model $auction, string $key): ?string
    {
        return optional($auction->meta()->where('meta_key', $key)->first())->meta_value;
    }

    /**
     * Skip when the running database cannot store a `UserAgent` of this role's type.
     *
     * ── A PRE-EXISTING TEST-SCHEMA GAP, NOT A PRODUCTION DEFECT ─────────────
     *
     * `user_agents.type` was created as `enum('seller','buyer')`. Migration
     * 2026_01_04_063218 widens it to include `tenant` and `landlord`, but it opens with
     *
     *     if (! Schema::hasTable('user_agents') || DB::getDriverName() !== 'pgsql')
     *
     * and the suite runs on SQLite, where Laravel rendered the original `enum` as a
     * CHECK constraint that the pgsql-only widening never reaches. So under SQLite the
     * `UserAgent` insert inside `accept_bid()` raises, the controller rolls the whole
     * transaction back, and a landlord/tenant hire silently does nothing.
     *
     * Production runs PostgreSQL with the widened constraint, so the flow is fine there.
     * This is visible on `main` independently of this change: seven tests in
     * tests/Feature/TenantAgentAuctionBidTest.php — every one of them on the accept
     * path — fail for exactly this reason before any of this branch's edits.
     *
     * Fixing it means changing a migration, which is out of scope for a route fix. The
     * assertions are therefore kept and gated on a CAPABILITY rather than deleted or
     * hardcoded per role: they run in full wherever the constraint is correct, and they
     * start running on SQLite the moment the migration is made driver-agnostic.
     */
    private function skipIfHireCannotBeRecorded(string $role): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        $ddl = (string) optional(DB::selectOne(
            "select sql from sqlite_master where type = 'table' and name = 'user_agents'"
        ))->sql;

        if (! preg_match('/check\s*\(\s*"type"\s+in\s*\(([^)]*)\)/i', $ddl, $matches)) {
            return;
        }

        if (! str_contains($matches[1], "'".$role."'")) {
            $this->markTestSkipped(
                "user_agents.type on this connection does not accept '{$role}', so the hire "
                ."cannot commit; migration 2026_01_04_063218 widens the constraint on pgsql only."
            );
        }
    }
}
