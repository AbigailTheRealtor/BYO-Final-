<?php

namespace Tests\Feature\Security;

use App\Http\Livewire\Tenant\TenantAgentAuctionCounterTerm;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionBid;
use App\Models\TenantCounterTerm;
use App\Models\User;
use App\Notifications\CounterBidSubmittedNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tenant Counter Terms — party authorization on the LIVE (Livewire) path.
 *
 * ── WHY THIS FILE EXISTS SEPARATELY FROM CounteredTermsAuthorizationTest ─────
 *
 * That file covers the legacy HTTP `store`/`update` endpoints. Those are dead
 * Gen-1 code: they write columns `tenant_counter_terms` does not have, so they
 * 500 before persisting anything, and their guards were never the thing standing
 * between an attacker and the data. They were green while every vector asserted
 * below was wide open.
 *
 * The live path is the Livewire component. Each vector here was confirmed
 * exploitable at runtime against the pre-fix code, three runs, identical results:
 *
 *   - a foreign bid id in the URL returned HTTP 200 and rendered the counter-terms
 *     screen for a stranger's negotiation, prefilled with the agent's bid terms
 *   - mount() and submit() both completed with no authorization raised
 *   - the attacker's submit inserted an ACTIVE TenantCounterTerm onto the victim's
 *     auction with 44 meta rows, and notified the victim's agent
 *   - setting $counterTermId to a victim's row rewrote that row's meta while
 *     leaving user_id as the victim, so the mutation was attributed to them
 *
 * ── WHY submit() IS GUARDED AND NOT JUST mount() ─────────────────────────────
 *
 * mount() does not re-run between Livewire requests. Every public property on a
 * submit arrives from the client on that request, and Livewire v2 has no locked
 * property — the payload checksum protects server-supplied state, not
 * client-initiated property updates. A mount-only guard therefore leaves the
 * $counterTermId vector fully open, which is exactly what the runtime probe
 * demonstrated. `Livewire::set()` below is a faithful model of that request, not
 * a test-harness bypass.
 *
 * The positive controls are load-bearing: a guard that returns 403 to everyone
 * would satisfy every negative assertion here and ship a broken feature.
 */
class TenantCounterTermsAuthorizationTest extends TestCase
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
     * One tenant-owned auction with two competing agent bids, plus an unrelated
     * authenticated user. Two bids because the attacker in the tampering case must
     * be able to mount a screen of their own before reaching for someone else's row.
     *
     * @return array{owner:User,agentA:User,agentB:User,attacker:User,auction:TenantAgentAuction,bidA:TenantAgentAuctionBid,bidB:TenantAgentAuctionBid}
     */
    private function negotiation(): array
    {
        $owner    = User::factory()->create();
        $agentA   = User::factory()->asAgent()->create();
        $agentB   = User::factory()->asAgent()->create();
        $attacker = User::factory()->create();

        $auction = TenantAgentAuction::forceCreate(['user_id' => $owner->id]);

        $bidA = TenantAgentAuctionBid::forceCreate([
            'tenant_agent_auction_id' => $auction->id,
            'user_id'                 => $agentA->id,
        ]);
        $bidB = TenantAgentAuctionBid::forceCreate([
            'tenant_agent_auction_id' => $auction->id,
            'user_id'                 => $agentB->id,
        ]);

        return compact('owner', 'agentA', 'agentB', 'attacker', 'auction', 'bidA', 'bidB');
    }

    // =====================================================================
    // A — the screen itself
    // =====================================================================

    public function test_non_party_cannot_open_the_counter_terms_screen_for_a_foreign_bid(): void
    {
        $this->requireIsolatedDb();
        $n = $this->negotiation();

        $this->actingAs($n['attacker'])
            ->get('tenant/counter-terms/' . $n['bidA']->id)
            ->assertForbidden();
    }

    public function test_non_party_cannot_open_the_counter_terms_edit_screen_for_a_foreign_bid(): void
    {
        $this->requireIsolatedDb();
        $n = $this->negotiation();

        $this->actingAs($n['attacker'])
            ->get('tenant/counter-terms/' . $n['bidA']->id . '/edit')
            ->assertForbidden();
    }

    // =====================================================================
    // B — mounting the live component
    // =====================================================================

    /**
     * Livewire's test harness turns an abort into a *response* rather than letting
     * the HttpException propagate, and it does not update component state when the
     * response carries an exception. So this asserts the response, and deliberately
     * does not chain further calls onto a component that never mounted.
     */
    public function test_non_party_cannot_mount_the_counter_terms_component_for_a_foreign_bid(): void
    {
        $this->requireIsolatedDb();
        Notification::fake();
        $n = $this->negotiation();

        Livewire::actingAs($n['attacker'])
            ->test(TenantAgentAuctionCounterTerm::class, ['pab' => $n['bidA'], 'bidId' => $n['bidA']->id])
            ->assertStatus(403);

        // Pre-fix, mounting and submitting inserted an active counter term onto the
        // victim's auction and notified their agent.
        $this->assertDatabaseMissing('tenant_counter_terms', [
            'tenant_agent_auction_id' => $n['auction']->id,
        ]);
        Notification::assertNothingSent();
    }

    // =====================================================================
    // C — the write, guarded at action time and not only at mount
    // =====================================================================

    /**
     * mount() does not re-run between Livewire requests, so submit() must stand on
     * its own. Agent B mounts their own bid legitimately, then points $bidId — a
     * plain public property the client controls — at a foreign bid. Only the
     * submit-time guard is left to catch this.
     */
    public function test_submit_rejects_a_bid_id_that_does_not_match_the_mounted_bid(): void
    {
        $this->requireIsolatedDb();
        Notification::fake();
        $n = $this->negotiation();

        Livewire::actingAs($n['agentB'])
            ->test(TenantAgentAuctionCounterTerm::class, ['pab' => $n['bidB'], 'bidId' => $n['bidB']->id])
            ->set('bidId', $n['bidA']->id)
            ->set('additional_details', 'INJECTED-VIA-TAMPERED-BID-ID')
            ->call('submit')
            ->assertStatus(403);

        $this->assertDatabaseMissing('tenant_counter_terms', [
            'user_id' => $n['agentB']->id,
        ]);
        Notification::assertNothingSent();
    }

    // =====================================================================
    // D — $counterTermId tampering
    // =====================================================================

    public function test_a_party_cannot_rewrite_another_users_counter_terms_by_setting_counter_term_id(): void
    {
        $this->requireIsolatedDb();
        $n = $this->negotiation();

        // The listing owner legitimately counters agent A.
        Livewire::actingAs($n['owner'])
            ->test(TenantAgentAuctionCounterTerm::class, ['pab' => $n['bidA'], 'bidId' => $n['bidA']->id])
            ->set('additional_details', 'OWNER-ORIGINAL-TERMS')
            ->call('submit');

        $victimTerm = TenantCounterTerm::where('user_id', $n['owner']->id)->latest('id')->firstOrFail();
        $this->assertSame(
            'OWNER-ORIGINAL-TERMS',
            optional($victimTerm->meta()->where('meta_key', 'additional_details')->first())->meta_value,
            'Fixture must establish the victim row, otherwise this regression proves nothing.'
        );

        // Agent B is a genuine party to their OWN bid — so the party check alone
        // admits them. Only the ownership check on $counterTermId stops them here.
        Livewire::actingAs($n['agentB'])
            ->test(TenantAgentAuctionCounterTerm::class, ['pab' => $n['bidB'], 'bidId' => $n['bidB']->id])
            ->set('counterTermId', $victimTerm->id)
            ->set('additional_details', 'TAMPERED')
            ->call('submit')
            ->assertStatus(403);

        $victimTerm->refresh();
        $this->assertSame(
            'OWNER-ORIGINAL-TERMS',
            optional($victimTerm->meta()->where('meta_key', 'additional_details')->first())->meta_value,
            'The victim\'s counter terms were mutated.'
        );
        $this->assertSame((int) $n['owner']->id, (int) $victimTerm->user_id);
    }

    public function test_a_non_party_cannot_reach_the_counter_term_id_path_at_all(): void
    {
        $this->requireIsolatedDb();
        $n = $this->negotiation();

        Livewire::actingAs($n['owner'])
            ->test(TenantAgentAuctionCounterTerm::class, ['pab' => $n['bidA'], 'bidId' => $n['bidA']->id])
            ->set('additional_details', 'OWNER-ORIGINAL-TERMS')
            ->call('submit');

        $victimTerm = TenantCounterTerm::where('user_id', $n['owner']->id)->latest('id')->firstOrFail();

        // A non-party is stopped a step earlier — they cannot mount at all, so the
        // $counterTermId path is unreachable for them.
        Livewire::actingAs($n['attacker'])
            ->test(TenantAgentAuctionCounterTerm::class, ['pab' => $n['bidA'], 'bidId' => $n['bidA']->id])
            ->assertStatus(403);

        $victimTerm->refresh();
        $this->assertSame(
            'OWNER-ORIGINAL-TERMS',
            optional($victimTerm->meta()->where('meta_key', 'additional_details')->first())->meta_value
        );
    }

    // =====================================================================
    // Positive controls — the feature must still work
    // =====================================================================

    public function test_listing_owner_can_open_the_counter_terms_screen(): void
    {
        $this->requireIsolatedDb();
        $n = $this->negotiation();

        $this->actingAs($n['owner'])
            ->get('tenant/counter-terms/' . $n['bidA']->id)
            ->assertOk();
    }

    public function test_bidding_agent_can_open_the_counter_terms_screen_for_their_own_bid(): void
    {
        $this->requireIsolatedDb();
        $n = $this->negotiation();

        $this->actingAs($n['agentA'])
            ->get('tenant/counter-terms/' . $n['bidA']->id)
            ->assertOk();
    }

    public function test_listing_owner_can_submit_counter_terms(): void
    {
        $this->requireIsolatedDb();
        $n = $this->negotiation();

        Livewire::actingAs($n['owner'])
            ->test(TenantAgentAuctionCounterTerm::class, ['pab' => $n['bidA'], 'bidId' => $n['bidA']->id])
            ->set('additional_details', 'LEGITIMATE-OWNER-TERMS')
            ->call('submit');

        $this->assertDatabaseHas('tenant_counter_terms', [
            'user_id'                 => $n['owner']->id,
            'tenant_agent_auction_id' => $n['auction']->id,
            'status'                  => 1,
        ]);
    }

    public function test_listing_owner_can_edit_their_own_counter_terms(): void
    {
        $this->requireIsolatedDb();
        $n = $this->negotiation();

        Livewire::actingAs($n['owner'])
            ->test(TenantAgentAuctionCounterTerm::class, ['pab' => $n['bidA'], 'bidId' => $n['bidA']->id])
            ->set('additional_details', 'FIRST-VERSION')
            ->call('submit');

        $term = TenantCounterTerm::where('user_id', $n['owner']->id)->latest('id')->firstOrFail();

        // Re-entering the screen finds the owner's own active counter and edits it —
        // the legitimate $counterTermId path, which the new guard must not break.
        Livewire::actingAs($n['owner'])
            ->test(TenantAgentAuctionCounterTerm::class, ['pab' => $n['bidA'], 'bidId' => $n['bidA']->id])
            ->set('additional_details', 'SECOND-VERSION')
            ->call('submit');

        $term->refresh();
        $this->assertSame(
            'SECOND-VERSION',
            optional($term->meta()->where('meta_key', 'additional_details')->first())->meta_value,
            'The owner can no longer edit their own counter terms.'
        );
    }

    public function test_a_real_guest_is_redirected_to_login(): void
    {
        $this->requireIsolatedDb();
        $n = $this->negotiation();

        $this->app['auth']->forgetGuards();
        $this->assertFalse(auth()->check());

        $response = $this->get('tenant/counter-terms/' . $n['bidA']->id)->assertStatus(302);
        $this->assertStringContainsString('/login', (string) $response->headers->get('Location'));
    }
}
