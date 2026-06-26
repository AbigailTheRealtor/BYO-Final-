<?php

namespace Tests\Feature\Security;

use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionBid;
use App\Models\CounterTerm;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordCounterTerm;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionBid;
use App\Models\SellerCounterTerm;
use App\Models\TenantAgentAuction;
use App\Models\TenantCounterTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * HIGH-5 — legacy `*CounteredTerms` store/update authorization (IDOR) regression.
 *
 * Before the Phase 1 "harden" remediation, the counter-terms store/update
 * endpoints performed NO authorization: any visitor could submit or overwrite
 * counter terms on any auction by guessing its id (the routes sit outside the
 * `auth` group; the controllers had no ownership check). The fix adds an
 * in-controller party-check to each method:
 *
 *     abort_unless(auth()->check() && $auction && (
 *         (int) $auction->user_id === (int) auth()->id() ||           // listing owner
 *         {Role}AgentAuctionBid::where(...auction...)
 *             ->where('user_id', auth()->id())->exists()              // bidding agent
 *     ), 403);
 *
 * These tests lock in that behaviour for the FOUR controllers whose store/update
 * are actually routed (per `php artisan route:list`):
 *   - Seller  -> SellerCounteredTermsController  (seller/add|update-counter-terms)
 *   - Buyer   -> CounteredTerms                  (buyer/add|update-counter-terms)
 *   - Landlord-> LandlordCounteredTermsController(landlord/add|update-counter-terms)
 *   - Tenant  -> TenantCounteredTermsController  (tenant/add|update-counter-terms)
 *
 * NOTE: `BuyerCounteredTermsController@store/@update` carry the same defensive
 * guard but are NOT wired to any route (only its @add/@edit are), so the live
 * buyer flow is `CounteredTerms`. The agent variant
 * (`AgentCounteredTermsController`) is intentionally NOT hardened: its
 * `agent_counter_terms` table has no migration (dead/broken path that 500s) and
 * it already sits behind the `agentAuth` middleware. See the HIGH-5 section of
 * docs/launch-audits/bidyouragent-phase1-summary.md.
 *
 * The security-critical assertion is the FORBIDDEN path (attacker / guest -> 403),
 * which aborts before any DB write and is independent of the legacy insert/update
 * column logic (which is frozen under OFFER_SYSTEM_DO_NOT_TOUCH and out of scope).
 *
 * Ownership uses the two-persona model: a single consumer account owns its
 * listing; an agent who has bid on the listing is the only other permitted party.
 */
class CounteredTermsAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Authorization is what is under test, not CSRF token state.
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    /** Skip DB-backed tests when the isolated SQLite test DB is unavailable. */
    private function requireIsolatedDb(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped(
                'Isolated SQLite test DB unavailable in this environment ' .
                '(pre-existing harness issue — the wider suite is affected too). ' .
                'HIGH-5 ownership logic is verified by code review + Phase 5 browser ' .
                'testing; this test is CI-ready against a working SQLite DB.'
            );
        }
    }

    // =====================================================================
    // store() — an unauthorized actor must NOT be able to submit counter terms
    // =====================================================================

    public function test_seller_store_blocks_non_party_and_guest(): void
    {
        $this->requireIsolatedDb();
        [$owner, $attacker] = [User::factory()->create(), User::factory()->create()];
        $auction = SellerAgentAuction::forceCreate(['user_id' => $owner->id]);

        $this->actingAs($attacker)
            ->post('seller/add-counter-terms', ['sellerId' => $auction->id])
            ->assertForbidden();

        $this->post('seller/add-counter-terms', ['sellerId' => $auction->id]) // guest
            ->assertForbidden();
    }

    public function test_buyer_store_blocks_non_party_and_guest(): void
    {
        $this->requireIsolatedDb();
        [$owner, $attacker] = [User::factory()->create(), User::factory()->create()];
        $auction = BuyerAgentAuction::forceCreate(['user_id' => $owner->id]);

        $this->actingAs($attacker)
            ->post('buyer/add-counter-terms', ['buyerId' => $auction->id])
            ->assertForbidden();

        $this->post('buyer/add-counter-terms', ['buyerId' => $auction->id]) // guest
            ->assertForbidden();
    }

    public function test_landlord_store_blocks_non_party_and_guest(): void
    {
        $this->requireIsolatedDb();
        [$owner, $attacker] = [User::factory()->create(), User::factory()->create()];
        $auction = LandlordAgentAuction::forceCreate(['user_id' => $owner->id]);

        $this->actingAs($attacker)
            ->post('landlord/add-counter-terms', ['landlordId' => $auction->id])
            ->assertForbidden();

        $this->post('landlord/add-counter-terms', ['landlordId' => $auction->id]) // guest
            ->assertForbidden();
    }

    public function test_tenant_store_blocks_non_party_and_guest(): void
    {
        $this->requireIsolatedDb();
        [$owner, $attacker] = [User::factory()->create(), User::factory()->create()];
        $auction = TenantAgentAuction::forceCreate(['user_id' => $owner->id]);

        // The Tenant controller reads the (mis-spelled) `tanantId` request key.
        $this->actingAs($attacker)
            ->post('tenant/add-counter-terms', ['tanantId' => $auction->id])
            ->assertForbidden();

        $this->post('tenant/add-counter-terms', ['tanantId' => $auction->id]) // guest
            ->assertForbidden();
    }

    // =====================================================================
    // store() — a legitimate party (listing owner / bidding agent) is NOT blocked
    // (asserted as "not 403"; we do not assert 2xx because the legacy insert path
    //  is frozen and writes divergent columns — out of scope for this security fix)
    // =====================================================================

    public function test_seller_store_allows_owner_and_bidding_agent(): void
    {
        $this->requireIsolatedDb();
        $owner  = User::factory()->create();
        $bidder = User::factory()->asAgent()->create();
        $auction = SellerAgentAuction::forceCreate(['user_id' => $owner->id]);
        SellerAgentAuctionBid::forceCreate([
            'seller_agent_auction_id' => $auction->id,
            'user_id' => $bidder->id,
        ]);

        $ownerStatus = $this->actingAs($owner)
            ->post('seller/add-counter-terms', ['sellerId' => $auction->id])->status();
        $this->assertNotSame(403, $ownerStatus, 'Listing owner must pass the HIGH-5 guard');

        $bidderStatus = $this->actingAs($bidder)
            ->post('seller/add-counter-terms', ['sellerId' => $auction->id])->status();
        $this->assertNotSame(403, $bidderStatus, 'Bidding agent (party) must pass the HIGH-5 guard');
    }

    public function test_buyer_store_allows_bidding_agent(): void
    {
        $this->requireIsolatedDb();
        $owner  = User::factory()->create();
        $bidder = User::factory()->asAgent()->create();
        $auction = BuyerAgentAuction::forceCreate(['user_id' => $owner->id]);
        BuyerAgentAuctionBid::forceCreate([
            'buyer_agent_auction_id' => $auction->id,
            'user_id' => $bidder->id,
        ]);

        $status = $this->actingAs($bidder)
            ->post('buyer/add-counter-terms', ['buyerId' => $auction->id])->status();
        $this->assertNotSame(403, $status, 'Bidding agent (party) must pass the HIGH-5 guard');
    }

    // =====================================================================
    // update() — an unauthorized actor must NOT be able to overwrite counter terms
    // =====================================================================

    public function test_seller_update_blocks_non_party_and_guest(): void
    {
        $this->requireIsolatedDb();
        [$owner, $attacker] = [User::factory()->create(), User::factory()->create()];
        $auction = SellerAgentAuction::forceCreate(['user_id' => $owner->id]);
        $bid = SellerAgentAuctionBid::forceCreate([
            'seller_agent_auction_id' => $auction->id,
            'user_id' => $owner->id,
        ]);
        $counter = SellerCounterTerm::forceCreate([
            'user_id' => $owner->id,
            'seller_agent_auction_bid_id' => $bid->id,
            'seller_agent_auction_id' => $auction->id,
        ]);

        $this->actingAs($attacker)
            ->post('seller/update-counter-terms/' . $counter->id)
            ->assertForbidden();

        $this->post('seller/update-counter-terms/' . $counter->id) // guest
            ->assertForbidden();
    }

    public function test_buyer_update_blocks_non_party_and_guest(): void
    {
        $this->requireIsolatedDb();
        [$owner, $attacker] = [User::factory()->create(), User::factory()->create()];
        $auction = BuyerAgentAuction::forceCreate(['user_id' => $owner->id]);
        // CounteredTerms@update reads $counter->buyer_auction_id (counter_terms table).
        $counter = CounterTerm::forceCreate([
            'buyer_auction_id' => $auction->id,
            'commission' => 'flat',
        ]);

        $this->actingAs($attacker)
            ->post('buyer/update-counter-terms/' . $counter->id)
            ->assertForbidden();

        $this->post('buyer/update-counter-terms/' . $counter->id) // guest
            ->assertForbidden();
    }

    public function test_landlord_update_blocks_non_party_and_guest(): void
    {
        $this->requireIsolatedDb();
        [$owner, $attacker] = [User::factory()->create(), User::factory()->create()];
        $auction = LandlordAgentAuction::forceCreate(['user_id' => $owner->id]);
        $counter = LandlordCounterTerm::forceCreate([
            'user_id' => $owner->id,
            'landlord_agent_auction_id' => $auction->id,
            'property_type' => 'house',
        ]);

        $this->actingAs($attacker)
            ->post('landlord/update-counter-terms/' . $counter->id)
            ->assertForbidden();

        $this->post('landlord/update-counter-terms/' . $counter->id) // guest
            ->assertForbidden();
    }

    public function test_tenant_update_blocks_non_party_and_guest(): void
    {
        $this->requireIsolatedDb();
        [$owner, $attacker] = [User::factory()->create(), User::factory()->create()];
        $auction = TenantAgentAuction::forceCreate(['user_id' => $owner->id]);
        $counter = TenantCounterTerm::forceCreate([
            'user_id' => $owner->id,
            'tenant_agent_auction_id' => $auction->id,
            'property_type' => 'apartment',
        ]);

        $this->actingAs($attacker)
            ->post('tenant/update-counter-terms/' . $counter->id)
            ->assertForbidden();

        $this->post('tenant/update-counter-terms/' . $counter->id) // guest
            ->assertForbidden();
    }
}
