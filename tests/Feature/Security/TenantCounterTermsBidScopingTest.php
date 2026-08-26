<?php

namespace Tests\Feature\Security;

use App\Http\Livewire\Tenant\TenantAgentAuctionCounterTerm;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionBid;
use App\Models\TenantCounterTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tenant Counter Terms — bid / negotiation ISOLATION (PR 2).
 *
 * ── HOW THIS DIFFERS FROM TenantCounterTermsAuthorizationTest (PR 1) ─────────
 *
 * PR 1 asked "may this user touch this row at all?" and closed every cross-USER
 * hole. Those 11 tests pass and must keep passing — nothing here weakens them.
 *
 * This file asks a question PR 1 could not: "which NEGOTIATION does this row
 * belong to?" `tenant_counter_terms` has user_id and tenant_agent_auction_id and
 * no bid column, so the finest boundary the schema can express is the LISTING.
 * PR 1's guard is therefore already as tight as the storage allows — its own
 * comment says it verifies "the row belongs to this negotiation", but what it can
 * actually compare is the auction. Every failure below is that gap, not a
 * regression in PR 1.
 *
 * The agent's half of the same conversation IS bid-scoped:
 *
 *   tenant_counter_bidding  user_id, tenant_agent_auction_id, tenant_agent_auction_bid_id
 *   tenant_counter_terms    user_id, tenant_agent_auction_id
 *
 * So one owner countering two agents on one listing has two agent-side threads
 * and a single shared owner-side row.
 *
 * ── EXPECTED STATE: RED ──────────────────────────────────────────────────────
 *
 * These are written against the intended post-fix behaviour and fail today. They
 * are deliberately COLUMN-AGNOSTIC — they assert on observable negotiation
 * behaviour, never on `tenant_agent_auction_bid_id` — so they stay valid before
 * the migration exists, after it lands, and if the fix is shaped differently.
 */
class TenantCounterTermsBidScopingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    private function requireIsolatedDb(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Isolated SQLite test DB unavailable in this environment.');
        }
    }

    /** One owner, one listing, two competing agents. */
    private function negotiation(): array
    {
        $owner  = User::factory()->create();
        $agentA = User::factory()->asAgent()->create();
        $agentB = User::factory()->asAgent()->create();

        $auction = TenantAgentAuction::forceCreate(['user_id' => $owner->id]);

        $bidA = TenantAgentAuctionBid::forceCreate([
            'tenant_agent_auction_id' => $auction->id,
            'user_id'                 => $agentA->id,
        ]);
        $bidB = TenantAgentAuctionBid::forceCreate([
            'tenant_agent_auction_id' => $auction->id,
            'user_id'                 => $agentB->id,
        ]);

        return compact('owner', 'agentA', 'agentB', 'auction', 'bidA', 'bidB');
    }

    private function ownerCounters(array $n, string $bidKey, string $marker): void
    {
        Livewire::actingAs($n['owner'])
            ->test(TenantAgentAuctionCounterTerm::class, [
                'pab'   => $n[$bidKey],
                'bidId' => $n[$bidKey]->id,
            ])
            ->set('additional_details', $marker)
            ->call('submit');
    }

    // =====================================================================
    // 1 — countering a second agent must not adopt the first agent's row
    // =====================================================================

    public function test_countering_a_second_agent_creates_a_separate_counter_term(): void
    {
        $this->requireIsolatedDb();
        $n = $this->negotiation();

        $this->ownerCounters($n, 'bidA', 'TERMS-FOR-AGENT-A');
        $this->ownerCounters($n, 'bidB', 'TERMS-FOR-AGENT-B');

        $this->assertSame(
            2,
            TenantCounterTerm::where('user_id', $n['owner']->id)->count(),
            'The owner countered two different agents but only one counter-term row exists: '
            . 'mount() matched on auction+user+status with no bid dimension, entered EDIT mode, '
            . "and the second submit overwrote the first agent's terms."
        );
    }

    // =====================================================================
    // 2 — a co-bidder must not see terms written for a rival
    // =====================================================================

    public function test_second_agent_does_not_see_counter_terms_written_for_the_first_agent(): void
    {
        $this->requireIsolatedDb();
        $n = $this->negotiation();

        $this->ownerCounters($n, 'bidA', 'PRIVATE-TERMS-FOR-AGENT-A');

        // Agent B asks for THEIR OWN bid's counter screen. Entirely legitimate:
        // no tampering, no guessed id, PR 1's party check correctly admits them.
        $response = $this->actingAs($n['agentB'])
            ->get('tenant/hire/agent/auction/bid/' . $n['bidB']->id . '/view-counter')
            ->assertOk();

        $leaked = $response->original->getData()['tenantCounter'] ?? null;

        $this->assertNull(
            $leaked,
            'Agent B was handed the counter terms the owner wrote privately for Agent A. '
            . 'view_counter_terms() filters on the auction only — no bid filter, no user filter, '
            . 'no status filter — so every bidder on the listing reads the same row.'
        );
    }

    // =====================================================================
    // 3 — bid id must never be matched against the auction-id column
    // =====================================================================

    public function test_counter_lookup_does_not_match_a_foreign_auction_by_numeric_collision(): void
    {
        $this->requireIsolatedDb();
        $n = $this->negotiation();

        // A STRANGER's listing whose auction id numerically equals our bidB's id.
        // add_counter_bid() runs where('tenant_agent_auction_id', $bid_id), so it
        // compares a bid id against an auction-id column and matches this row.
        $stranger = User::factory()->create();
        $strangerAuction = TenantAgentAuction::forceCreate([
            'id'      => $n['bidB']->id,
            'user_id' => $stranger->id,
        ]);

        $strangerTerm = TenantCounterTerm::forceCreate([
            'user_id'                 => $stranger->id,
            'tenant_agent_auction_id' => $strangerAuction->id,
            'property_type'           => 'residential',
            'status'                  => 1,
        ]);
        $strangerTerm->saveMeta('additional_details', 'STRANGER-PRIVATE-TERMS');

        $response = $this->actingAs($n['owner'])
            ->get('tenant/hire/agent/auction/counter-bid/' . $n['auction']->id . '/' . $n['bidB']->id)
            ->assertOk();

        $data = $response->original->getData();
        $leaked = $data['latestTenantCounter'] ?? null;

        $this->assertTrue(
            $leaked === null || (int) $leaked->id !== (int) $strangerTerm->id,
            "A counter term from an unrelated listing was selected because that listing's "
            . "auction id happens to equal this bid's id. Two independent autoincrement "
            . 'sequences make the collision routine, not exotic.'
        );

        // The same wrong value seeds the counter-back chain.
        $this->assertTrue(
            ($data['parent_counter_id'] ?? null) === null
                || (int) $data['parent_counter_id'] !== (int) $strangerTerm->id,
            "parent_counter_id was seeded from a stranger's counter term."
        );
    }

    // =====================================================================
    // 5 — the two negotiations must be independently readable
    // =====================================================================

    public function test_owner_can_hold_independent_negotiations_with_two_agents(): void
    {
        $this->requireIsolatedDb();
        $n = $this->negotiation();

        $this->ownerCounters($n, 'bidA', 'TERMS-FOR-AGENT-A');
        $this->ownerCounters($n, 'bidB', 'TERMS-FOR-AGENT-B');

        // Re-entering each thread must show that thread's own terms.
        $seenOnA = Livewire::actingAs($n['owner'])
            ->test(TenantAgentAuctionCounterTerm::class, ['pab' => $n['bidA'], 'bidId' => $n['bidA']->id])
            ->get('additional_details');

        $seenOnB = Livewire::actingAs($n['owner'])
            ->test(TenantAgentAuctionCounterTerm::class, ['pab' => $n['bidB'], 'bidId' => $n['bidB']->id])
            ->get('additional_details');

        $this->assertSame('TERMS-FOR-AGENT-A', $seenOnA, "Agent A's thread does not show Agent A's terms.");
        $this->assertSame('TERMS-FOR-AGENT-B', $seenOnB, "Agent B's thread does not show Agent B's terms.");
    }

    // =====================================================================
    // Schema — the column the whole fix rests on
    // =====================================================================

    public function test_bid_id_column_exists_and_is_nullable(): void
    {
        $this->requireIsolatedDb();

        $this->assertTrue(
            Schema::hasColumn('tenant_counter_terms', 'tenant_agent_auction_bid_id'),
            'The PR 2 migration did not run.'
        );

        // tenant_agent_auction_id must survive untouched — PR 2 adds a column,
        // it does not repurpose the existing one.
        $this->assertTrue(Schema::hasColumn('tenant_counter_terms', 'tenant_agent_auction_id'));

        // Nullability is not cosmetic: historical rows that cannot be resolved to a
        // bid must be storable as NULL rather than guessed. A row inserted without
        // a bid id therefore has to be accepted.
        $owner   = User::factory()->create();
        $auction = TenantAgentAuction::forceCreate(['user_id' => $owner->id]);

        $legacy = TenantCounterTerm::forceCreate([
            'user_id'                 => $owner->id,
            'tenant_agent_auction_id' => $auction->id,
            'property_type'           => 'residential',
            'status'                  => 1,
        ]);

        $this->assertNull($legacy->fresh()->tenant_agent_auction_bid_id);
    }

    // =====================================================================
    // Write correctness — every new row is bid-scoped
    // =====================================================================

    public function test_new_writes_populate_the_bid_id_without_disturbing_the_auction_id(): void
    {
        $this->requireIsolatedDb();
        $n = $this->negotiation();

        $this->ownerCounters($n, 'bidA', 'TERMS-FOR-AGENT-A');

        $row = TenantCounterTerm::where('user_id', $n['owner']->id)->latest('id')->firstOrFail();

        $this->assertSame((int) $n['bidA']->id, (int) $row->tenant_agent_auction_bid_id);
        $this->assertSame(
            (int) $n['auction']->id,
            (int) $row->tenant_agent_auction_id,
            'tenant_agent_auction_id must still be the AUCTION reference — PR 2 adds a column, '
            . 'it does not reinterpret the existing one.'
        );
    }

    // =====================================================================
    // Legacy rows — present, but never silently adopted
    // =====================================================================

    /** A row as it existed before PR 2: owner + auction, no bid. */
    private function legacyRow(array $n, string $marker): TenantCounterTerm
    {
        $legacy = TenantCounterTerm::forceCreate([
            'user_id'                     => $n['owner']->id,
            'tenant_agent_auction_id'     => $n['auction']->id,
            'tenant_agent_auction_bid_id' => null,
            'property_type'               => 'residential',
            'status'                      => 1,
        ]);
        $legacy->saveMeta('additional_details', $marker);

        return $legacy;
    }

    public function test_legacy_null_bid_row_is_not_adopted_by_edit_mode(): void
    {
        $this->requireIsolatedDb();
        $n = $this->negotiation();
        $legacy = $this->legacyRow($n, 'LEGACY-UNSCOPED-TERMS');

        // Countering Agent A must start a fresh, bid-scoped row rather than
        // reactivating an unscoped historical one.
        $this->ownerCounters($n, 'bidA', 'NEW-BID-SCOPED-TERMS');

        $legacy->refresh();
        $this->assertNull($legacy->tenant_agent_auction_bid_id);
        $this->assertSame(
            'LEGACY-UNSCOPED-TERMS',
            optional($legacy->meta()->where('meta_key', 'additional_details')->first())->meta_value,
            'The legacy row was adopted and overwritten by the new negotiation.'
        );

        $this->assertSame(2, TenantCounterTerm::where('user_id', $n['owner']->id)->count());
    }

    public function test_legacy_null_bid_row_cannot_be_adopted_by_setting_counter_term_id(): void
    {
        $this->requireIsolatedDb();
        $n = $this->negotiation();
        $legacy = $this->legacyRow($n, 'LEGACY-UNSCOPED-TERMS');

        Livewire::actingAs($n['owner'])
            ->test(TenantAgentAuctionCounterTerm::class, ['pab' => $n['bidA'], 'bidId' => $n['bidA']->id])
            ->set('counterTermId', $legacy->id)
            ->set('additional_details', 'HIJACKED-LEGACY')
            ->call('submit')
            ->assertStatus(403);

        $legacy->refresh();
        $this->assertSame(
            'LEGACY-UNSCOPED-TERMS',
            optional($legacy->meta()->where('meta_key', 'additional_details')->first())->meta_value
        );
    }

    // =====================================================================
    // Tampering — the vector PR 1 could not reach
    // =====================================================================

    public function test_owner_cannot_retarget_their_own_counter_term_at_a_sibling_bid(): void
    {
        $this->requireIsolatedDb();
        $n = $this->negotiation();

        $this->ownerCounters($n, 'bidA', 'TERMS-FOR-AGENT-A');
        $termA = TenantCounterTerm::where('user_id', $n['owner']->id)->latest('id')->firstOrFail();

        // Same user, same auction — so PR 1's user and auction clauses BOTH pass.
        // Only the bid clause added by PR 2 stops this, which is precisely the gap
        // PR 1 documented but could not close without the column.
        Livewire::actingAs($n['owner'])
            ->test(TenantAgentAuctionCounterTerm::class, ['pab' => $n['bidB'], 'bidId' => $n['bidB']->id])
            ->set('counterTermId', $termA->id)
            ->set('additional_details', 'RETARGETED-AT-AGENT-B')
            ->call('submit')
            ->assertStatus(403);

        $termA->refresh();
        $this->assertSame(
            'TERMS-FOR-AGENT-A',
            optional($termA->meta()->where('meta_key', 'additional_details')->first())->meta_value
        );
        $this->assertSame((int) $n['bidA']->id, (int) $termA->tenant_agent_auction_bid_id);
    }

    public function test_cross_auction_counter_term_id_tampering_remains_blocked(): void
    {
        $this->requireIsolatedDb();
        $n = $this->negotiation();

        // PR 1's auction clause is still load-bearing and must not have been
        // replaced by the new bid clause.
        $stranger = User::factory()->create();
        $strangerAuction = TenantAgentAuction::forceCreate(['user_id' => $stranger->id]);
        $strangerBid = TenantAgentAuctionBid::forceCreate([
            'tenant_agent_auction_id' => $strangerAuction->id,
            'user_id'                 => $n['agentA']->id,
        ]);
        $strangerTerm = TenantCounterTerm::forceCreate([
            'user_id'                     => $stranger->id,
            'tenant_agent_auction_id'     => $strangerAuction->id,
            'tenant_agent_auction_bid_id' => $strangerBid->id,
            'property_type'               => 'residential',
            'status'                      => 1,
        ]);
        $strangerTerm->saveMeta('additional_details', 'STRANGER-TERMS');

        Livewire::actingAs($n['owner'])
            ->test(TenantAgentAuctionCounterTerm::class, ['pab' => $n['bidA'], 'bidId' => $n['bidA']->id])
            ->set('counterTermId', $strangerTerm->id)
            ->set('additional_details', 'TAMPERED')
            ->call('submit')
            ->assertStatus(403);

        $strangerTerm->refresh();
        $this->assertSame(
            'STRANGER-TERMS',
            optional($strangerTerm->meta()->where('meta_key', 'additional_details')->first())->meta_value
        );
        $this->assertSame((int) $stranger->id, (int) $strangerTerm->user_id);
    }

    // =====================================================================
    // Positive controls — the fix must not break single-agent negotiation
    // =====================================================================

    public function test_owner_editing_the_same_thread_still_updates_one_row(): void
    {
        $this->requireIsolatedDb();
        $n = $this->negotiation();

        $this->ownerCounters($n, 'bidA', 'FIRST-VERSION');
        $this->ownerCounters($n, 'bidA', 'SECOND-VERSION');

        $this->assertSame(
            1,
            TenantCounterTerm::where('user_id', $n['owner']->id)->count(),
            'Re-countering the SAME agent must edit in place, not fork a new row.'
        );

        $term = TenantCounterTerm::where('user_id', $n['owner']->id)->firstOrFail();
        $this->assertSame(
            'SECOND-VERSION',
            optional($term->meta()->where('meta_key', 'additional_details')->first())->meta_value
        );
    }

    public function test_agent_still_sees_counter_terms_written_for_their_own_bid(): void
    {
        $this->requireIsolatedDb();
        $n = $this->negotiation();

        $this->ownerCounters($n, 'bidA', 'TERMS-FOR-AGENT-A');

        $response = $this->actingAs($n['agentA'])
            ->get('tenant/hire/agent/auction/bid/' . $n['bidA']->id . '/view-counter')
            ->assertOk();

        $seen = $response->original->getData()['tenantCounter'] ?? null;

        $this->assertNotNull(
            $seen,
            'Agent A can no longer see the terms written for them — the fix over-filtered.'
        );
    }
}
