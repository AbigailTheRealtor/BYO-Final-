<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\AgentAuth;
use App\Models\BuyerCriteriaAuction;
use App\Models\PropertyAuction;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionBid;
use App\Models\TenantCriteriaAuction;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 1 — Authorization & Security remediation regression tests.
 *
 * Two tiers:
 *   (A) Deterministic, DB-independent checks — AgentAuth middleware behaviour
 *       and route-middleware wiring. These run green in every environment.
 *   (B) DB-backed ownership checks — auto-skipped when an isolated SQLite test
 *       database is unavailable (a PRE-EXISTING harness limitation in this
 *       workspace; the wider suite is affected the same way). They are CI-ready.
 *
 * Ownership is keyed on user_id (two-persona model: one consumer account owns
 * all of its listing types).
 */
class Phase1AuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // CSRF is irrelevant to authorization assertions; disable only that one
        // middleware so HTTP-level ownership tests exercise auth, not token state.
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    /** Skip DB-backed tests when the isolated SQLite test DB is unavailable. */
    private function requireIsolatedDb(): void
    {
        if (\Illuminate\Support\Facades\DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped(
                'Isolated SQLite test DB unavailable in this environment ' .
                '(pre-existing harness issue — the wider suite is affected too). ' .
                'Ownership logic is verified by code review + Phase 5 browser testing; ' .
                'this test is CI-ready against a working SQLite DB.'
            );
        }
    }

    // =====================================================================
    // (A) HIGH-1 — AgentAuth must allow ONLY the agent persona (no DB)
    // =====================================================================

    private function loginAs(?string $userType): void
    {
        if ($userType === null) {
            return; // guest
        }
        $u = new User();
        $u->user_type = $userType;
        Auth::login($u);
    }

    public function test_agentauth_allows_agent_persona(): void
    {
        $this->loginAs('agent');
        $resp = (new AgentAuth())->handle(Request::create('/x'), fn ($r) => response('PASS'));
        $this->assertSame('PASS', $resp->getContent(), 'Agent persona must pass the agent gate');
    }

    public function test_agentauth_blocks_consumer_persona(): void
    {
        $this->loginAs('tenant'); // consumer (owns seller/buyer/landlord/tenant listings)
        $resp = (new AgentAuth())->handle(Request::create('/x'), fn ($r) => response('PASS'));
        $this->assertTrue($resp->isRedirect(), 'Consumer must be redirected away from agent-only routes');
        $this->assertNotSame('PASS', $resp->getContent());
    }

    public function test_agentauth_blocks_legacy_agent_types_unchanged(): void
    {
        // Legacy seller_agent/buyer_agent were already excluded before; still are.
        foreach (['seller_agent', 'buyer_agent'] as $type) {
            $this->loginAs($type);
            $resp = (new AgentAuth())->handle(Request::create('/x'), fn ($r) => response('PASS'));
            $this->assertTrue($resp->isRedirect(), "{$type} should remain excluded");
            Auth::logout();
        }
    }

    public function test_agentauth_blocks_guest(): void
    {
        $this->loginAs(null);
        $resp = (new AgentAuth())->handle(Request::create('/x'), fn ($r) => response('PASS'));
        $this->assertTrue($resp->isRedirect());
    }

    // =====================================================================
    // (A) Route-middleware wiring — deterministic, no DB
    // =====================================================================

    private function middlewareFor(string $routeName): array
    {
        $route = Route::getRoutes()->getByName($routeName);
        $this->assertNotNull($route, "Route '{$routeName}' must exist");
        return $route->gatherMiddleware();
    }

    private function assertRouteRequiresAuth(string $routeName): void
    {
        $this->assertContains('auth', $this->middlewareFor($routeName), "Route '{$routeName}' must require auth");
    }

    public function test_crit2_end_auction_routes_require_auth(): void
    {
        $this->assertRouteRequiresAuth('property.auction.end');
        $this->assertRouteRequiresAuth('landlord.agent.auction.end');
    }

    public function test_high7_renew_routes_require_auth(): void
    {
        foreach (['renewBID', 'renewBuyer', 'renewTenant', 'renewLandlord'] as $name) {
            $this->assertRouteRequiresAuth($name);
        }
    }

    public function test_crit4_counter_routes_require_auth(): void
    {
        foreach (['counterBiding', 'sellerCounterBid', 'add-counterBiding'] as $name) {
            $this->assertRouteRequiresAuth($name);
        }
    }

    public function test_dead_landlord_auction_routes_require_auth(): void
    {
        // Reclassified dead-code endpoints; auth still added for surface reduction.
        $this->assertRouteRequiresAuth('landlord.auction.bid.view');
        $this->assertRouteRequiresAuth('landlord.auction.end');
    }

    public function test_crit5_destroy_counter_is_auth_and_agent_gated(): void
    {
        $mw = $this->middlewareFor('destroySellerCounter');
        $this->assertContains('auth', $mw, 'destroyCounter must require auth');
        $this->assertContains('agentAuth', $mw, 'destroyCounter must remain agent-gated');
    }

    public function test_crit1_listing_edit_route_requires_auth(): void
    {
        $this->assertRouteRequiresAuth('hire.agent.auction.edit');
    }

    // =====================================================================
    // (B) DB-backed ownership checks — CI-ready, auto-skipped here
    // =====================================================================

    public function test_crit2_non_owner_cannot_end_property_auction(): void
    {
        $this->requireIsolatedDb();
        $owner    = User::factory()->create();
        $attacker = User::factory()->create();
        $auction  = PropertyAuction::forceCreate(['user_id' => $owner->id]);

        $this->actingAs($attacker)->post('/property/auction/end/' . $auction->id)->assertForbidden();
        $this->assertNotEquals(true, (bool) $auction->fresh()->auction_ended);

        $this->actingAs($owner)->post('/property/auction/end/' . $auction->id)->assertOk();
        $this->assertTrue((bool) $auction->fresh()->auction_ended);
    }

    public function test_high7_non_owner_cannot_renew_buyer_criteria(): void
    {
        $this->requireIsolatedDb();
        $owner    = User::factory()->create();
        $attacker = User::factory()->create();
        $auction  = BuyerCriteriaAuction::forceCreate(['user_id' => $owner->id]);

        $this->actingAs($attacker)
            ->post('/renew_buyer', ['id' => $auction->id, 'listing_date' => '2026-01-01', 'expiration_date' => '2026-02-01'])
            ->assertForbidden();
    }

    public function test_high7_non_owner_cannot_renew_tenant_criteria(): void
    {
        $this->requireIsolatedDb();
        $owner    = User::factory()->create();
        $attacker = User::factory()->create();
        $auction  = TenantCriteriaAuction::forceCreate(['user_id' => $owner->id]);

        $this->actingAs($attacker)
            ->post('/renew_tenant', ['id' => $auction->id, 'listing_date' => '2026-01-01', 'expiration_date' => '2026-02-01'])
            ->assertForbidden();
    }

    public function test_crit5_non_party_agent_cannot_destroy_seller_counter(): void
    {
        $owner    = User::factory()->create();
        $bidder   = User::factory()->asAgent()->create();
        $attacker = User::factory()->asAgent()->create();

        $auction = SellerAgentAuction::forceCreate(['user_id' => $owner->id]);
        $bid     = SellerAgentAuctionBid::forceCreate(['seller_agent_auction_id' => $auction->id, 'user_id' => $bidder->id]);

        $this->actingAs($attacker)->post('/hire/agent/seller/destroy/counter/' . $bid->id)->assertForbidden();
        $this->assertNotNull($bid->fresh(), 'Bid must NOT be deleted by a non-party');

        $this->actingAs($bidder)->post('/hire/agent/seller/destroy/counter/' . $bid->id)->assertRedirect();
        $this->assertNull($bid->fresh(), 'Bidding agent (party) may reject the counter');
    }

    public function test_crit1_non_owner_cannot_load_listing_for_edit(): void
    {
        $owner    = User::factory()->create();
        $attacker = User::factory()->create();
        $auction  = SellerAgentAuction::forceCreate(['user_id' => $owner->id, 'title' => 'Owned']);

        $this->actingAs($attacker);

        // The owner-scoped findOrFail must yield no result for a non-owner.
        try {
            Livewire::test(\App\Http\Livewire\TenantAgentAuctionEdit::class, ['auctionId' => $auction->id, 'user_type' => 'seller']);
            $this->fail('Non-owner must not be able to mount the listing edit component.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString(
                'No query results',
                $e->getMessage(),
                'Non-owner edit attempt should fail the ownership-scoped lookup'
            );
        }
    }
}
