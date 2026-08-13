<?php

namespace Tests\Feature\Security;

use App\Http\Controllers\LandlordCounteredTermsController;
use App\Http\Controllers\SellerCounteredTermsController;
use App\Http\Livewire\Seller\SellerAgentAuctionCounterTerm;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionBid;
use App\Models\CounterTerm;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionBid;
use App\Models\LandlordCounterTerm;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionBid;
use App\Models\SellerCounterTerm;
use App\Models\TenantAgentAuction;
use App\Models\TenantCounterTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * HIGH-5 — `*CounteredTerms` store/update authorization (IDOR) regression.
 *
 * ── WHAT CHANGED, AND WHY THIS FILE LOOKS DIFFERENT ──────────────────────────
 *
 * The Seller and Landlord legacy HTTP write endpoints have been RETIRED. They were
 * unreachable from the UI, returned 500 for every caller including the listing owner,
 * and the Landlord `update` carried a live wrong-entity authorization defect. The
 * working Counter Terms feature is the Livewire implementation and is untouched.
 *
 * So the Seller/Landlord tests here no longer assert "403 for an attacker" — there is
 * nothing left to attack. They assert the surface is GONE (404 / route unregistered /
 * controller method absent), which is a strictly stronger property than 403.
 *
 * ── THE GUEST ASSERTIONS ARE NOW REAL ────────────────────────────────────────
 *
 * The previous revision asserted 403 for "guests". Those assertions were vacuous:
 * `actingAs()` sets the user on the container's guard, and that state PERSISTS across
 * every later request in the same test method. The "guest" call was still the attacker,
 * so it re-tested the case immediately above it and never exercised a logged-out user.
 *
 * A genuine guest is redirected, not forbidden: every counter-terms route sits inside
 * `Route::middleware(['auth', 'verified'])` (routes/web.php), so `Authenticate` fires
 * first and returns 302 -> /login. `assertGuestIsRedirectedToLogin()` drops any leaked
 * guard state, proves the caller really is anonymous, and asserts that.
 *
 * ── FIXTURES ─────────────────────────────────────────────────────────────────
 *
 * `landlord_counter_terms.landlord_agent_auction_id` stores a *BID* id despite its
 * name, and its FK references `landlord_agent_auction_bids.id`. The fixture therefore
 * creates a LandlordAgentAuctionBid and uses ITS id. The earlier revision passed an
 * auction id, which is what produced the FOREIGN KEY error — a test defect, not a
 * schema defect. The schema is correct and is deliberately left alone.
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
                '(pre-existing harness issue — the wider suite is affected too).'
            );
        }
    }

    /**
     * Assert a genuinely anonymous POST is bounced to the login screen.
     *
     * `forgetGuards()` is the load-bearing line. Without it, an `actingAs()` earlier in
     * the same test method leaves a user on the guard and this "guest" is not a guest —
     * which is exactly how the previous revision's guest coverage became vacuous.
     */
    private function assertGuestIsRedirectedToLogin(string $uri, array $payload = []): void
    {
        $this->app['auth']->forgetGuards();

        $this->assertFalse(
            auth()->check(),
            "Refusing to assert guest behaviour while a user is still authenticated — {$uri}"
        );

        $response = $this->post($uri, $payload)->assertStatus(302);

        $this->assertStringContainsString(
            '/login',
            (string) $response->headers->get('Location'),
            "A guest POST to {$uri} must be redirected to the login screen by the auth middleware."
        );
    }

    // =====================================================================
    // RETIREMENT — the obsolete Seller/Landlord HTTP write surface is gone
    // =====================================================================

    public function test_retired_seller_and_landlord_write_routes_are_not_registered(): void
    {
        foreach ([
            'seller.add-counter-terms',
            'seller.update-counter-terms',
            'landlord.add-counter-terms',
            'landlord.update-counter-terms',
        ] as $name) {
            $this->assertFalse(
                Route::has($name),
                "Retired route [{$name}] is still registered — the obsolete write surface is back."
            );
        }
    }

    public function test_retired_controllers_no_longer_expose_http_write_methods(): void
    {
        foreach ([
            [SellerCounteredTermsController::class, 'store'],
            [SellerCounteredTermsController::class, 'update'],
            [LandlordCounteredTermsController::class, 'store'],
            [LandlordCounteredTermsController::class, 'update'],
        ] as [$class, $method]) {
            $this->assertFalse(
                method_exists($class, $method),
                "{$class}::{$method}() still exists — the retired Gen-1 write path is back."
            );
        }
    }

    /**
     * The listing owner is the most privileged caller these endpoints ever had. If even
     * they cannot reach a handler, no one can.
     */
    public function test_retired_seller_write_endpoints_are_unreachable_even_for_the_listing_owner(): void
    {
        $this->requireIsolatedDb();

        $owner   = User::factory()->create();
        $auction = SellerAgentAuction::forceCreate(['user_id' => $owner->id]);
        $bid     = SellerAgentAuctionBid::forceCreate([
            'seller_agent_auction_id' => $auction->id,
            'user_id'                 => $owner->id,
        ]);
        $counter = SellerCounterTerm::forceCreate([
            'user_id'                     => $owner->id,
            'seller_agent_auction_bid_id' => $bid->id,
            'seller_agent_auction_id'     => $auction->id,
        ]);

        $this->actingAs($owner)
            ->post('seller/add-counter-terms', ['sellerId' => $auction->id, 'commission' => '5%'])
            ->assertNotFound();

        $this->actingAs($owner)
            ->post('seller/update-counter-terms/' . $counter->id, ['timeframe' => '6 Months'])
            ->assertNotFound();
    }

    public function test_retired_landlord_write_endpoints_are_unreachable_even_for_the_listing_owner(): void
    {
        $this->requireIsolatedDb();

        $owner   = User::factory()->create();
        $agent   = User::factory()->asAgent()->create();
        $auction = LandlordAgentAuction::forceCreate(['user_id' => $owner->id]);
        $bid     = LandlordAgentAuctionBid::forceCreate([
            'landlord_agent_auction_id' => $auction->id,
            'user_id'                   => $agent->id,
        ]);
        $counter = LandlordCounterTerm::forceCreate([
            'user_id'                   => $owner->id,
            'landlord_agent_auction_id' => $bid->id, // BID id — see class docblock
            'property_type'             => 'house',
        ]);

        $this->actingAs($owner)
            ->post('landlord/add-counter-terms', ['landlordId' => $auction->id, 'commission' => '5%'])
            ->assertNotFound();

        $this->actingAs($owner)
            ->post('landlord/update-counter-terms/' . $counter->id, ['timeframe' => '6 Months'])
            ->assertNotFound();
    }

    /**
     * Regression for the confirmed wrong-entity guard bypass.
     *
     * `LandlordCounteredTermsController::update()` resolved
     * `LandlordAgentAuction::find($counter->landlord_agent_auction_id)` — but that column
     * holds a BID id. An attacker who happened to own the landlord auction whose id equalled
     * the victim's bid id therefore PASSED the ownership check. Unauthorized mutation was
     * prevented only because the follow-up Gen-1 UPDATE hit "no such column" and 500'd, so
     * repairing the write path without the guard would have turned this into a foreign write.
     *
     * Retirement removes the endpoint, so the collision no longer reaches any handler at all.
     */
    public function test_landlord_counter_term_id_collision_no_longer_reaches_a_handler(): void
    {
        $this->requireIsolatedDb();

        $victim   = User::factory()->create();
        $agent    = User::factory()->asAgent()->create();
        $attacker = User::factory()->create();

        $victimAuction = LandlordAgentAuction::forceCreate(['id' => 4100, 'user_id' => $victim->id]);
        $bid           = LandlordAgentAuctionBid::forceCreate([
            'id'                        => 4055,
            'landlord_agent_auction_id' => $victimAuction->id,
            'user_id'                   => $agent->id,
        ]);
        $counter = LandlordCounterTerm::forceCreate([
            'user_id'                   => $victim->id,
            'landlord_agent_auction_id' => $bid->id, // 4055
            'property_type'             => 'house',
        ]);

        // The collision: the attacker owns the AUCTION whose id equals that BID id.
        $attackerAuction = LandlordAgentAuction::forceCreate(['id' => 4055, 'user_id' => $attacker->id]);

        $this->assertSame(
            (int) $counter->landlord_agent_auction_id,
            (int) $attackerAuction->id,
            'Fixture must reproduce the id collision, otherwise this regression proves nothing.'
        );

        $this->actingAs($attacker)
            ->post('landlord/update-counter-terms/' . $counter->id, [
                'timeframe'          => 'HACKED',
                'commission'         => '99%',
                'additional_details' => 'pwned',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('landlord_counter_terms', [
            'id'            => $counter->id,
            'user_id'       => $victim->id,
            'property_type' => 'house',
        ]);
    }

    // =====================================================================
    // Buyer — legacy `CounteredTerms` endpoints remain live and are unchanged
    // =====================================================================

    public function test_buyer_store_blocks_authenticated_non_party(): void
    {
        $this->requireIsolatedDb();

        $owner    = User::factory()->create();
        $attacker = User::factory()->create();
        $auction  = BuyerAgentAuction::forceCreate(['user_id' => $owner->id, 'title' => 'Buyer Agent Listing']);

        // `commission` is supplied deliberately: it is NOT NULL on `counter_terms`, so
        // without it the insert would fail on the column and assertDatabaseMissing below
        // would pass even if the guard were removed. With it, only the guard stops the write.
        $this->actingAs($attacker)
            ->post('buyer/add-counter-terms', ['buyerId' => $auction->id, 'commission' => '5%'])
            ->assertForbidden();

        $this->assertDatabaseMissing('counter_terms', ['buyer_auction_id' => $auction->id]);
    }

    public function test_buyer_store_redirects_a_real_guest_to_login(): void
    {
        $this->requireIsolatedDb();

        $owner   = User::factory()->create();
        $auction = BuyerAgentAuction::forceCreate(['user_id' => $owner->id, 'title' => 'Buyer Agent Listing']);

        $this->assertGuestIsRedirectedToLogin('buyer/add-counter-terms', [
            'buyerId'    => $auction->id,
            'commission' => '5%',
        ]);

        $this->assertDatabaseMissing('counter_terms', ['buyer_auction_id' => $auction->id]);
    }

    public function test_buyer_store_allows_bidding_agent(): void
    {
        $this->requireIsolatedDb();

        $owner   = User::factory()->create();
        $bidder  = User::factory()->asAgent()->create();
        $auction = BuyerAgentAuction::forceCreate(['user_id' => $owner->id, 'title' => 'Buyer Agent Listing']);
        BuyerAgentAuctionBid::forceCreate([
            'buyer_agent_auction_id' => $auction->id,
            'user_id'                => $bidder->id,
        ]);

        $status = $this->actingAs($bidder)
            ->post('buyer/add-counter-terms', ['buyerId' => $auction->id, 'commission' => '5%'])
            ->status();

        $this->assertNotSame(403, $status, 'Bidding agent (party) must pass the HIGH-5 guard');
    }

    public function test_buyer_update_blocks_authenticated_non_party(): void
    {
        $this->requireIsolatedDb();

        $owner    = User::factory()->create();
        $attacker = User::factory()->create();
        $auction  = BuyerAgentAuction::forceCreate(['user_id' => $owner->id, 'title' => 'Buyer Agent Listing']);
        $counter  = CounterTerm::forceCreate([
            'buyer_auction_id' => $auction->id,
            'commission'       => 'flat',
        ]);

        $this->actingAs($attacker)
            ->post('buyer/update-counter-terms/' . $counter->id, ['commission' => 'HACKED'])
            ->assertForbidden();

        $this->assertDatabaseHas('counter_terms', [
            'id'         => $counter->id,
            'commission' => 'flat',
        ]);
    }

    public function test_buyer_update_redirects_a_real_guest_to_login(): void
    {
        $this->requireIsolatedDb();

        $owner   = User::factory()->create();
        $auction = BuyerAgentAuction::forceCreate(['user_id' => $owner->id, 'title' => 'Buyer Agent Listing']);
        $counter = CounterTerm::forceCreate([
            'buyer_auction_id' => $auction->id,
            'commission'       => 'flat',
        ]);

        $this->assertGuestIsRedirectedToLogin('buyer/update-counter-terms/' . $counter->id, [
            'commission' => 'HACKED',
        ]);

        $this->assertDatabaseHas('counter_terms', [
            'id'         => $counter->id,
            'commission' => 'flat',
        ]);
    }

    // =====================================================================
    // Tenant — legacy endpoints remain live and are unchanged
    // =====================================================================

    public function test_tenant_store_blocks_authenticated_non_party(): void
    {
        $this->requireIsolatedDb();

        $owner    = User::factory()->create();
        $attacker = User::factory()->create();
        $auction  = TenantAgentAuction::forceCreate(['user_id' => $owner->id]);

        // The Tenant controller reads the (mis-spelled) `tanantId` request key.
        $this->actingAs($attacker)
            ->post('tenant/add-counter-terms', ['tanantId' => $auction->id, 'timeframe' => '6 Months'])
            ->assertForbidden();

        $this->assertDatabaseMissing('tenant_counter_terms', ['tenant_agent_auction_id' => $auction->id]);
    }

    public function test_tenant_store_redirects_a_real_guest_to_login(): void
    {
        $this->requireIsolatedDb();

        $owner   = User::factory()->create();
        $auction = TenantAgentAuction::forceCreate(['user_id' => $owner->id]);

        $this->assertGuestIsRedirectedToLogin('tenant/add-counter-terms', [
            'tanantId'  => $auction->id,
            'timeframe' => '6 Months',
        ]);

        $this->assertDatabaseMissing('tenant_counter_terms', ['tenant_agent_auction_id' => $auction->id]);
    }

    public function test_tenant_update_blocks_authenticated_non_party(): void
    {
        $this->requireIsolatedDb();

        $owner    = User::factory()->create();
        $attacker = User::factory()->create();
        $auction  = TenantAgentAuction::forceCreate(['user_id' => $owner->id]);
        $counter  = TenantCounterTerm::forceCreate([
            'user_id'                 => $owner->id,
            'tenant_agent_auction_id' => $auction->id,
            'property_type'           => 'apartment',
        ]);

        $this->actingAs($attacker)
            ->post('tenant/update-counter-terms/' . $counter->id, ['timeframe' => 'HACKED'])
            ->assertForbidden();

        $this->assertDatabaseHas('tenant_counter_terms', [
            'id'            => $counter->id,
            'user_id'       => $owner->id,
            'property_type' => 'apartment',
        ]);
    }

    public function test_tenant_update_redirects_a_real_guest_to_login(): void
    {
        $this->requireIsolatedDb();

        $owner   = User::factory()->create();
        $auction = TenantAgentAuction::forceCreate(['user_id' => $owner->id]);
        $counter = TenantCounterTerm::forceCreate([
            'user_id'                 => $owner->id,
            'tenant_agent_auction_id' => $auction->id,
            'property_type'           => 'apartment',
        ]);

        $this->assertGuestIsRedirectedToLogin('tenant/update-counter-terms/' . $counter->id, [
            'timeframe' => 'HACKED',
        ]);

        $this->assertDatabaseHas('tenant_counter_terms', [
            'id'      => $counter->id,
            'user_id' => $owner->id,
        ]);
    }

    // =====================================================================
    // The surviving, working path — Livewire
    // =====================================================================

    /**
     * Retiring the HTTP endpoint must not be mistaken for retiring Counter Terms. The
     * Seller Livewire component is the live write path, and it carries its own
     * party-check: mount() aborts 403 for anyone who is neither the listing owner nor
     * the bidding agent. This pins that guard so the retirement cannot silently become
     * a removal of the feature's protection.
     *
     * mount() is invoked directly rather than through `Livewire::test()`. Livewire's test
     * harness converts an abort during mount into a *response* instead of letting the
     * HttpException propagate, so a try/catch around `Livewire::test()` silently observes
     * no exception and the assertion passes for the wrong reason — it would report the
     * guard as absent even when it fires, and would keep "passing" if the guard were
     * deleted. Calling mount() directly exercises the same production code and fails
     * honestly in both directions.
     */
    public function test_seller_livewire_counter_term_component_rejects_a_non_party(): void
    {
        $this->requireIsolatedDb();

        $owner    = User::factory()->create();
        $agent    = User::factory()->asAgent()->create();
        $attacker = User::factory()->create();

        $auction = SellerAgentAuction::forceCreate(['user_id' => $owner->id]);
        $bid     = SellerAgentAuctionBid::forceCreate([
            'seller_agent_auction_id' => $auction->id,
            'user_id'                 => $agent->id,
        ]);

        $this->actingAs($attacker);

        $bid = SellerAgentAuctionBid::with('meta', 'auction')->find($bid->id);

        try {
            (new SellerAgentAuctionCounterTerm())->mount($bid, $bid->id);
            $this->fail('A non-party mounted the Seller counter-terms component without being forbidden.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
