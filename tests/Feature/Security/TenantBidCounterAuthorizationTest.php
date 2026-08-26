<?php

namespace Tests\Feature\Security;

use App\Http\Livewire\Tenant\TenantAgentAuctionBidCounter;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionBid;
use App\Models\TenantCounterBidding;
use App\Models\TenantCounterTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tenant BID COUNTER — party authorization and object scoping.
 *
 * ── WHY THIS FILE EXISTS SEPARATELY FROM THE COUNTER-TERM SUITES ─────────────
 *
 * TenantCounterTermsAuthorizationTest and TenantCounterTermsBidScopingTest cover
 * TenantAgentAuctionCounterTerm — the OWNER's half of the conversation. They pass,
 * and nothing here weakens them. This file covers TenantAgentAuctionBidCounter,
 * the AGENT's half, which was left unhardened when its sibling was fixed.
 *
 * Searching those two suites for this component finds exactly one reference:
 * TenantCounterTermsBidScopingTest asserts the controller's counter-term PREFILL
 * no longer leaks across listings, using a correctly matched auction/bid pair. It
 * never exercises authorization, and it never supplies a mismatched pair. That
 * single green reference made this component look covered when it was not.
 *
 * ── THE DEFECT THESE TESTS PIN ──────────────────────────────────────────────
 *
 * The auction and the bid arrived as two INDEPENDENT route parameters, and the
 * controller admitted anyone who owned EITHER one:
 *
 *     $isListingOwner = ($auction->user_id === $userId);
 *     $isBidOwner     = ($bid->user_id === $userId);
 *     if (!$isListingOwner && !$isBidOwner) { ...refuse... }
 *
 * Nothing asserted the bid belonged to the auction, and the component itself had
 * no authorization at all — Auth:: appeared twice in ~900 lines, both times to
 * STAMP a row, never to check one. So an agent who owned any bid anywhere could
 * pair it with any listing's auction id, satisfy the "is bid owner" half, read the
 * counterparty's prefilled terms, and write a counter row into that negotiation.
 *
 * ── WHAT THE POSITIVE CONTROLS ASSERT ───────────────────────────────────────
 *
 * That a legitimate counter bid PERSISTS — the row survives the transaction and
 * its meta is written — not merely that an INSERT was attempted.
 *
 * They could not do that when this file was written: `saveAllMetaData()` threw on
 * every freshly-migrated database because `tenant_counter_bidding_meta` was never
 * created by any migration, and the surrounding catch rolled the row back. That
 * schema defect is now repaired by
 * 2026_08_26_000001_create_missing_counter_tables_fix, so these assertions
 * became possible and were tightened accordingly.
 *
 * The negative cases still assert that NO insert was attempted, which is the
 * sharper statement for them.
 *
 * ── DISCIPLINE ──────────────────────────────────────────────────────────────
 *
 * The positive controls are load-bearing: a guard that returned 403 to everyone
 * would satisfy every negative assertion below while shipping a broken feature.
 * Nothing here skips — a skipped security test reads as green in CI.
 */
class TenantBidCounterAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Authorization is what is under test, not CSRF token state.
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Notification::fake();
    }

    /**
     * Two unrelated listings.
     *
     *   listing A: ownerA, with competing bids from agentA and agentB
     *   listing B: ownerB, with a bid from agentC
     *
     * Every cross-object case below is some pairing drawn from across that line.
     */
    private function world(): array
    {
        $ownerA   = User::factory()->create();
        $ownerB   = User::factory()->create();
        $agentA   = User::factory()->asAgent()->create();
        $agentB   = User::factory()->asAgent()->create();
        $agentC   = User::factory()->asAgent()->create();
        $stranger = User::factory()->asAgent()->create();

        $auctionA = TenantAgentAuction::forceCreate(['user_id' => $ownerA->id]);
        $auctionB = TenantAgentAuction::forceCreate(['user_id' => $ownerB->id]);

        $bidA = TenantAgentAuctionBid::forceCreate([
            'tenant_agent_auction_id' => $auctionA->id,
            'user_id'                 => $agentA->id,
        ]);
        $bidB = TenantAgentAuctionBid::forceCreate([
            'tenant_agent_auction_id' => $auctionA->id,
            'user_id'                 => $agentB->id,
        ]);
        $bidC = TenantAgentAuctionBid::forceCreate([
            'tenant_agent_auction_id' => $auctionB->id,
            'user_id'                 => $agentC->id,
        ]);

        return compact(
            'ownerA', 'ownerB', 'agentA', 'agentB', 'agentC', 'stranger',
            'auctionA', 'auctionB', 'bidA', 'bidB', 'bidC'
        );
    }

    private function counterUrl($auctionId, $bidId): string
    {
        return 'tenant/hire/agent/auction/counter-bid/' . $auctionId . '/' . $bidId;
    }

    /**
     * Run $fn and return the bindings of the INSERT into tenant_counter_bidding,
     * or null when no such insert was attempted.
     *
     * Binding order mirrors the create() array in submit():
     *   0 user_id, 1 tenant_agent_auction_id, 2 tenant_agent_auction_bid_id,
     *   3 property_type, 4 parent_counter_id
     */
    private function captureCounterInsert(callable $fn): ?array
    {
        $captured = null;

        DB::listen(function ($event) use (&$captured) {
            $sql = strtolower($event->sql);
            if ($captured === null
                && str_contains($sql, 'insert into')
                && str_contains($sql, 'tenant_counter_bidding')
                && ! str_contains($sql, 'meta')) {
                $captured = $event->bindings;
            }
        });

        $fn();

        return $captured;
    }

    private function agentParent(array $w, string $bidKey, $userKey): TenantCounterBidding
    {
        return TenantCounterBidding::forceCreate([
            'user_id'                     => $w[$userKey]->id,
            'tenant_agent_auction_id'     => $w[$bidKey]->tenant_agent_auction_id,
            'tenant_agent_auction_bid_id' => $w[$bidKey]->id,
            'property_type'               => 'residential',
        ]);
    }

    // =====================================================================
    // A — positive controls
    // =====================================================================

    /** @test */
    public function the_bidding_agent_can_counter_their_own_bid_on_the_right_auction(): void
    {
        $w = $this->world();

        Livewire::actingAs($w['agentA'])
            ->test(TenantAgentAuctionBidCounter::class, [
                'pab'   => $w['auctionA'],
                'bidId' => $w['bidA']->id,
            ])
            ->set('additional_details', 'AGENT-A-LEGITIMATE-COUNTER')
            ->call('submit')
            ->assertStatus(200);

        // The row must SURVIVE the transaction, not merely have been attempted.
        $this->assertDatabaseHas('tenant_counter_bidding', [
            'user_id'                     => $w['agentA']->id,
            'tenant_agent_auction_id'     => $w['auctionA']->id,
            'tenant_agent_auction_bid_id' => $w['bidA']->id,
        ]);

        $row = TenantCounterBidding::where('user_id', $w['agentA']->id)->latest('id')->first();
        $this->assertSame(
            'AGENT-A-LEGITIMATE-COUNTER',
            $row->getMeta('additional_details'),
            'The counter bid persisted but its meta did not.'
        );
    }

    /** @test */
    public function the_listing_owner_can_counter_a_bid_on_their_own_auction(): void
    {
        $w = $this->world();

        Livewire::actingAs($w['ownerA'])
            ->test(TenantAgentAuctionBidCounter::class, [
                'pab'   => $w['auctionA'],
                'bidId' => $w['bidA']->id,
            ])
            ->set('additional_details', 'OWNER-LEGITIMATE-COUNTER')
            ->call('submit')
            ->assertStatus(200);

        $this->assertDatabaseHas('tenant_counter_bidding', [
            'user_id'                     => $w['ownerA']->id,
            'tenant_agent_auction_id'     => $w['auctionA']->id,
            'tenant_agent_auction_bid_id' => $w['bidA']->id,
        ]);
    }

    /** @test */
    public function the_persisted_row_is_built_from_the_authorized_relationship(): void
    {
        $w = $this->world();

        // The two foreign keys must come from the re-resolved bid/auction, not
        // from client-modifiable public state, so the pair is always coherent.
        Livewire::actingAs($w['agentA'])
            ->test(TenantAgentAuctionBidCounter::class, [
                'pab'   => $w['auctionA'],
                'bidId' => $w['bidA']->id,
            ])
            ->call('submit');

        $row = TenantCounterBidding::where('user_id', $w['agentA']->id)->latest('id')->first();

        $this->assertNotNull($row, 'The legitimate counter bid did not persist.');
        $this->assertSame(
            (int) $w['bidA']->tenant_agent_auction_id,
            (int) $row->tenant_agent_auction_id,
            'The stored auction id is not the auction that actually owns the bid.'
        );
        $this->assertSame((int) $w['bidA']->id, (int) $row->tenant_agent_auction_bid_id);
    }

    // =====================================================================
    // B — party membership
    // =====================================================================

    /** @test */
    public function an_unrelated_agent_cannot_mount_against_a_foreign_bid(): void
    {
        $w = $this->world();

        Livewire::actingAs($w['stranger'])
            ->test(TenantAgentAuctionBidCounter::class, [
                'pab'   => $w['auctionA'],
                'bidId' => $w['bidA']->id,
            ])
            ->assertStatus(403);
    }

    /** @test */
    public function a_competing_agent_on_the_same_listing_cannot_counter_a_rivals_bid(): void
    {
        $w = $this->world();

        // agentB is a genuine party to listing A — but to their OWN bid, not agentA's.
        Livewire::actingAs($w['agentB'])
            ->test(TenantAgentAuctionBidCounter::class, [
                'pab'   => $w['auctionA'],
                'bidId' => $w['bidA']->id,
            ])
            ->assertStatus(403);
    }

    /** @test */
    public function a_guest_is_refused_by_the_component(): void
    {
        $w = $this->world();

        Livewire::test(TenantAgentAuctionBidCounter::class, [
            'pab'   => $w['auctionA'],
            'bidId' => $w['bidA']->id,
        ])->assertStatus(403);
    }

    /** @test */
    public function a_guest_is_redirected_away_from_the_http_entry_point(): void
    {
        $w = $this->world();

        $this->get($this->counterUrl($w['auctionA']->id, $w['bidA']->id))
            ->assertRedirect();
    }

    // =====================================================================
    // C — the mismatched tuple: individually valid ids, incoherent pair
    // =====================================================================

    /** @test */
    public function an_agent_cannot_pair_their_own_bid_with_a_foreign_auction(): void
    {
        $w = $this->world();

        // THE CORE DEFECT. agentA owns bidA, so the old "is bid owner" half was
        // satisfied, and nothing checked that bidA belonged to auctionB.
        $bindings = $this->captureCounterInsert(function () use ($w) {
            Livewire::actingAs($w['agentA'])
                ->test(TenantAgentAuctionBidCounter::class, [
                    'pab'   => $w['auctionB'],
                    'bidId' => $w['bidA']->id,
                ])
                ->assertStatus(403);
        });

        $this->assertNull($bindings, 'A counter bid was written across listings.');
    }

    /** @test */
    public function a_listing_owner_cannot_pair_their_own_auction_with_a_foreign_bid(): void
    {
        $w = $this->world();

        // The mirror case: the "is listing owner" half was satisfied by auctionA
        // while bidC belongs to a different listing entirely.
        $bindings = $this->captureCounterInsert(function () use ($w) {
            Livewire::actingAs($w['ownerA'])
                ->test(TenantAgentAuctionBidCounter::class, [
                    'pab'   => $w['auctionA'],
                    'bidId' => $w['bidC']->id,
                ])
                ->assertStatus(403);
        });

        $this->assertNull($bindings);
    }

    /** @test */
    public function the_mismatched_tuple_is_refused_at_the_http_boundary_too(): void
    {
        $w = $this->world();

        // Defence in depth: the controller must refuse before the component is
        // ever constructed, so neither layer is the only guard.
        $this->actingAs($w['agentA'])
            ->get($this->counterUrl($w['auctionB']->id, $w['bidA']->id))
            ->assertForbidden();
    }

    /** @test */
    public function a_refused_request_leaks_no_counterparty_terms(): void
    {
        $w = $this->world();

        $ownersPrivateTerms = TenantCounterTerm::forceCreate([
            'user_id'                     => $w['ownerA']->id,
            'tenant_agent_auction_id'     => $w['auctionA']->id,
            'tenant_agent_auction_bid_id' => $w['bidA']->id,
            'property_type'               => 'residential',
            'status'                      => 1,
        ]);
        $ownersPrivateTerms->saveMeta('additional_details', 'OWNER-PRIVATE-COUNTER-TERMS');

        // A non-party is turned away by the controller's party check (a redirect,
        // its long-standing behaviour) and never reaches the prefill. The point of
        // this test is the absence of the other side's terms, not the status code.
        $response = $this->actingAs($w['stranger'])
            ->get($this->counterUrl($w['auctionA']->id, $w['bidA']->id));

        $response->assertRedirect();
        $response->assertDontSee('OWNER-PRIVATE-COUNTER-TERMS', false);
    }

    // =====================================================================
    // D — post-mount property tampering
    // =====================================================================

    /** @test */
    public function tampering_with_bid_id_after_a_legitimate_mount_is_refused_at_submit(): void
    {
        $w = $this->world();

        // Livewire v2 lets a client update any public property not declared on the
        // base class (HandlesActions::syncInput), so passing mount() is not a
        // credential. agentB mounts legitimately on their own bid, then retargets.
        $bindings = $this->captureCounterInsert(function () use ($w) {
            Livewire::actingAs($w['agentB'])
                ->test(TenantAgentAuctionBidCounter::class, [
                    'pab'   => $w['auctionA'],
                    'bidId' => $w['bidB']->id,
                ])
                ->set('bidId', $w['bidA']->id)
                ->set('additional_details', 'INJECTED-VIA-TAMPERED-BID-ID')
                ->call('submit')
                ->assertStatus(403);
        });

        $this->assertNull($bindings, 'A tampered bid id reached the write.');
    }

    /** @test */
    public function tampering_with_bid_id_toward_a_foreign_auction_is_refused_at_submit(): void
    {
        $w = $this->world();

        // agentA mounts legitimately, then points bidId at a bid on another
        // listing. The derived auction no longer matches the mounted $pab.
        $bindings = $this->captureCounterInsert(function () use ($w) {
            Livewire::actingAs($w['agentA'])
                ->test(TenantAgentAuctionBidCounter::class, [
                    'pab'   => $w['auctionA'],
                    'bidId' => $w['bidA']->id,
                ])
                ->set('bidId', $w['bidC']->id)
                ->call('submit')
                ->assertStatus(403);
        });

        $this->assertNull($bindings);
    }

    // =====================================================================
    // E — parent_counter_id scoping
    // =====================================================================

    /** @test */
    public function a_parent_counter_from_another_bid_is_refused(): void
    {
        $w = $this->world();
        $foreignParent = $this->agentParent($w, 'bidB', 'agentB');

        Livewire::actingAs($w['agentA'])
            ->test(TenantAgentAuctionBidCounter::class, [
                'pab'               => $w['auctionA'],
                'bidId'             => $w['bidA']->id,
                'parent_counter_id' => $foreignParent->id,
            ])
            ->assertStatus(403);
    }

    /** @test */
    public function a_parent_counter_from_another_auction_is_refused(): void
    {
        $w = $this->world();
        $foreignParent = $this->agentParent($w, 'bidC', 'agentC');

        Livewire::actingAs($w['agentA'])
            ->test(TenantAgentAuctionBidCounter::class, [
                'pab'               => $w['auctionA'],
                'bidId'             => $w['bidA']->id,
                'parent_counter_id' => $foreignParent->id,
            ])
            ->assertStatus(403);
    }

    /** @test */
    public function a_parent_counter_tampered_in_after_mount_is_refused_at_submit(): void
    {
        $w = $this->world();
        $foreignParent = $this->agentParent($w, 'bidC', 'agentC');

        $bindings = $this->captureCounterInsert(function () use ($w, $foreignParent) {
            Livewire::actingAs($w['agentA'])
                ->test(TenantAgentAuctionBidCounter::class, [
                    'pab'   => $w['auctionA'],
                    'bidId' => $w['bidA']->id,
                ])
                ->set('parent_counter_id', $foreignParent->id)
                ->call('submit')
                ->assertStatus(403);
        });

        $this->assertNull($bindings, 'A foreign parent counter was chained onto this negotiation.');
    }

    /** @test */
    public function a_legacy_parent_row_with_no_bid_id_cannot_be_adopted(): void
    {
        $w = $this->world();

        // Predates bid scoping: it cannot be PROVEN to belong to this negotiation,
        // so it is never adopted. Silently adopting it is precisely how an
        // unrelated thread would be joined.
        $legacyParent = TenantCounterTerm::forceCreate([
            'user_id'                     => $w['ownerA']->id,
            'tenant_agent_auction_id'     => $w['auctionA']->id,
            'tenant_agent_auction_bid_id' => null,
            'property_type'               => 'residential',
            'status'                      => 1,
        ]);

        Livewire::actingAs($w['agentA'])
            ->test(TenantAgentAuctionBidCounter::class, [
                'pab'               => $w['auctionA'],
                'bidId'             => $w['bidA']->id,
                'parent_counter_id' => $legacyParent->id,
            ])
            ->assertStatus(403);
    }

    /** @test */
    public function a_parent_counter_within_the_same_negotiation_still_works(): void
    {
        $w = $this->world();

        // The positive control for the parent guard. Without this, refusing every
        // parent_counter_id would satisfy all four negatives above and silently
        // break counter chaining.
        $ownParent = TenantCounterTerm::forceCreate([
            'user_id'                     => $w['ownerA']->id,
            'tenant_agent_auction_id'     => $w['auctionA']->id,
            'tenant_agent_auction_bid_id' => $w['bidA']->id,
            'property_type'               => 'residential',
            'status'                      => 1,
        ]);

        Livewire::actingAs($w['agentA'])
            ->test(TenantAgentAuctionBidCounter::class, [
                'pab'               => $w['auctionA'],
                'bidId'             => $w['bidA']->id,
                'parent_counter_id' => $ownParent->id,
            ])
            ->set('additional_details', 'CHAINED-COUNTER')
            ->call('submit')
            ->assertStatus(200);

        $this->assertDatabaseHas('tenant_counter_bidding', [
            'tenant_agent_auction_bid_id' => $w['bidA']->id,
            'parent_counter_id'           => $ownParent->id,
        ]);
    }

    // =====================================================================
    // F — pre-existing protections must survive the hardening
    // =====================================================================

    /** @test */
    public function a_sold_listing_still_refuses_new_counter_bids(): void
    {
        $w = $this->world();
        $w['auctionA']->forceFill(['is_sold' => true])->save();

        $bindings = $this->captureCounterInsert(function () use ($w) {
            Livewire::actingAs($w['agentA'])
                ->test(TenantAgentAuctionBidCounter::class, [
                    'pab'   => $w['auctionA'],
                    'bidId' => $w['bidA']->id,
                ])
                ->call('submit');
        });

        $this->assertNull($bindings, 'A sold listing accepted a new counter bid.');
    }
}
