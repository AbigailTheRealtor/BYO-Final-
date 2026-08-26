<?php

namespace Tests\Feature\Security;

use App\Http\Livewire\Seller\SellerAgentAuctionCounterTerm;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionBid;
use App\Models\SellerCounterTerm;
use App\Models\User;
use App\Notifications\CounterBidSubmittedNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Seller Counter Terms — counter-row scoping.
 *
 * ── HOW SELLER DIFFERED FROM BUYER (#98) AND LANDLORD (#96) ─────────────────
 *
 * Seller was NOT missing authorization. Both entry points already worked:
 *
 *   * SellerCounteredTermsController::add() admitted only the listing owner or the
 *     bidding agent, and enforced the counter-back precondition;
 *   * the component guarded mount(); and — unlike Buyer and Landlord — it ALSO
 *     re-ran that guard at the top of submit().
 *
 * The residual defect was narrower. That submit-time guard proves the caller is a
 * party to `$this->pab`. It proves nothing about `$this->counterTermId`, a plain
 * public property the client sets freely on any request. `submit()` then ran
 *
 *     SellerCounterTerm::findOrFail($this->counterTermId)->update(...)
 *
 * followed by saveAllMetaData() on that same row. So a legitimate party to one
 * negotiation could name a counter row from ANOTHER and rewrite its terms and all
 * of its meta. Because the update never touches user_id, the attacker's content
 * stayed attributed to the victim.
 *
 * Reproduced before the fix: sellerA, party to negotiation A only, passed the
 * existing guard and rewrote sellerB's `additional_details` and `protection_period`
 * in negotiation B, with `user_id` unchanged.
 *
 * ── WHY THE FIX IS SELLER-SHAPED ────────────────────────────────────────────
 *
 * `seller_counter_terms` carries BOTH `seller_agent_auction_bid_id` and
 * `seller_agent_auction_id`, both NOT NULL — the richest scoping of the four roles
 * (Landlord has only a bid-holding column; Buyer pins the bid through
 * `parent_counter_id`). The row is therefore pinned on both axes.
 *
 * The user_id clause is Seller-specific business logic, not defensive padding:
 * mount() selects the editable row with `user_id = Auth::id()`, because a Seller
 * negotiation has TWO counter authors — the seller's original counter and the
 * agent's counter-back — and each party edits their own. Being a party to the bid
 * is not sufficient to edit the other party's counter.
 *
 * `parent_counter_id` here means the seller counter a counter-back replies to (set
 * server-side in mount()), which is again unlike Buyer, where it holds a bid id.
 *
 * ── SCOPE ───────────────────────────────────────────────────────────────────
 *
 * Seller only. No schema changes — both scoping columns already exist. The
 * pre-existing party guards are preserved, not replaced.
 */
class SellerCounterTermAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    /**
     * Two independent negotiations plus an unrelated user.
     *
     * A: sellerA's listing, agentA bidding — plus a second bid (bidA2) from agentA2
     *    on that same listing, so "another bid, same auction" is testable.
     * B: sellerB's listing, agentB bidding.
     */
    private function world(): array
    {
        $sellerA  = User::factory()->create(['user_type' => 'seller']);
        $sellerB  = User::factory()->create(['user_type' => 'seller']);
        $agentA   = User::factory()->asAgent()->create();
        $agentA2  = User::factory()->asAgent()->create();
        $agentB   = User::factory()->asAgent()->create();
        $outsider = User::factory()->create();

        // Decoys first so an auction id and a bid id can never coincide — an assertion
        // written while those numbers happened to be equal would prove neither.
        SellerAgentAuction::forceCreate(['user_id' => $sellerA->id, 'address' => '1 Decoy']);
        SellerAgentAuction::forceCreate(['user_id' => $sellerA->id, 'address' => '2 Decoy']);

        $auctionA = SellerAgentAuction::forceCreate(['user_id' => $sellerA->id, 'address' => '10 A St']);
        $auctionB = SellerAgentAuction::forceCreate(['user_id' => $sellerB->id, 'address' => '20 B St']);

        $bidA  = SellerAgentAuctionBid::forceCreate(['seller_agent_auction_id' => $auctionA->id, 'user_id' => $agentA->id]);
        $bidA2 = SellerAgentAuctionBid::forceCreate(['seller_agent_auction_id' => $auctionA->id, 'user_id' => $agentA2->id]);
        $bidB  = SellerAgentAuctionBid::forceCreate(['seller_agent_auction_id' => $auctionB->id, 'user_id' => $agentB->id]);

        $this->assertNotSame((int) $auctionA->id, (int) $bidA->id);

        return compact(
            'sellerA', 'sellerB', 'agentA', 'agentA2', 'agentB', 'outsider',
            'auctionA', 'auctionB', 'bidA', 'bidA2', 'bidB'
        );
    }

    private function counterTermFor(User $user, SellerAgentAuctionBid $bid, SellerAgentAuction $auction, string $details): SellerCounterTerm
    {
        $term = SellerCounterTerm::forceCreate([
            'user_id'                     => $user->id,
            'seller_agent_auction_bid_id' => $bid->id,
            'seller_agent_auction_id'     => $auction->id,
            'property_type'               => 'residential',
            'status'                      => 1,
        ]);
        $term->saveMeta('additional_details', $details);
        $term->saveMeta('protection_period', '90');

        return $term;
    }

    private function metaOf(SellerCounterTerm $t, string $key): ?string
    {
        return optional($t->meta()->where('meta_key', $key)->first())->meta_value;
    }

    /** Assert a victim row is untouched in every field this flow could have written. */
    private function assertVictimIntact(SellerCounterTerm $victim, User $owner, string $details): void
    {
        $victim->refresh();
        $this->assertSame($details, $this->metaOf($victim, 'additional_details'), 'victim terms were rewritten');
        $this->assertSame('90', (string) $this->metaOf($victim, 'protection_period'), 'victim meta was rewritten');
        $this->assertSame((int) $owner->id, (int) $victim->user_id, 'victim attribution changed');
        $this->assertSame(1, (int) $victim->status);
    }

    private function assertMountDenied(User $actor, $pab, $bidId): void
    {
        Livewire::actingAs($actor)
            ->test(SellerAgentAuctionCounterTerm::class, ['pab' => $pab, 'bidId' => $bidId])
            ->assertStatus(403);
    }

    // =====================================================================
    // Legitimate parties (pre-existing behaviour — must not regress)
    // =====================================================================

    public function test_listing_owner_can_reach_their_own_negotiation(): void
    {
        $w = $this->world();

        $this->actingAs($w['sellerA'])
            ->get(route('seller.counter-terms', ['id' => $w['bidA']->id]))
            ->assertOk();
    }

    public function test_bidding_agent_may_counter_back_only_after_the_seller_has_countered(): void
    {
        $w = $this->world();

        // Seller-specific business rule: before any seller counter exists, the agent
        // is refused.
        $this->assertMountDenied($w['agentA'], $w['bidA'], $w['bidA']->id);

        // Once the seller has countered, the same agent is admitted.
        $this->counterTermFor($w['sellerA'], $w['bidA'], $w['auctionA'], 'SELLER-A-TERMS');

        Livewire::actingAs($w['agentA'])
            ->test(SellerAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->assertStatus(200);
    }

    // =====================================================================
    // Non-parties
    // =====================================================================

    public function test_unrelated_authenticated_user_is_denied(): void
    {
        $w = $this->world();

        $this->actingAs($w['outsider'])
            ->get(route('seller.counter-terms', ['id' => $w['bidA']->id]))
            ->assertForbidden();

        $this->actingAs($w['outsider'])
            ->get(route('seller.edit-counter-terms', ['id' => $w['bidA']->id]))
            ->assertForbidden();

        $this->assertMountDenied($w['outsider'], $w['bidA'], $w['bidA']->id);
    }

    public function test_seller_from_another_negotiation_is_denied(): void
    {
        $w = $this->world();

        $this->actingAs($w['sellerB'])
            ->get(route('seller.counter-terms', ['id' => $w['bidA']->id]))
            ->assertForbidden();

        $this->actingAs($w['sellerB'])
            ->get(route('seller.edit-counter-terms', ['id' => $w['bidA']->id]))
            ->assertForbidden();

        $this->assertMountDenied($w['sellerB'], $w['bidA'], $w['bidA']->id);
    }

    public function test_bidding_agent_from_another_negotiation_is_denied(): void
    {
        $w = $this->world();

        $this->actingAs($w['agentB'])
            ->get(route('seller.counter-terms', ['id' => $w['bidA']->id]))
            ->assertForbidden();

        $this->assertMountDenied($w['agentB'], $w['bidA'], $w['bidA']->id);
    }

    // =====================================================================
    // The residual defect: counter-row scoping
    // =====================================================================

    public function test_counter_term_id_from_another_auction_is_denied(): void
    {
        $w = $this->world();

        $victim = $this->counterTermFor($w['sellerB'], $w['bidB'], $w['auctionB'], 'SELLER-B-PRIVATE-TERMS');

        Livewire::actingAs($w['sellerA'])
            ->test(SellerAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('counterTermId', $victim->id)
            ->set('additional_details', 'ATTACKER-CONTROLLED-TEXT')
            ->set('protection_period', '999')
            ->call('submit')
            ->assertStatus(403);

        $this->assertVictimIntact($victim, $w['sellerB'], 'SELLER-B-PRIVATE-TERMS');
    }

    public function test_counter_term_id_from_a_sibling_bid_on_the_same_listing_is_denied(): void
    {
        $w = $this->world();

        // Same seller, same AUCTION — only the bid differs. An auction-only guard
        // would wrongly admit this.
        $victim = $this->counterTermFor($w['sellerA'], $w['bidA2'], $w['auctionA'], 'TERMS-FOR-AGENT-A2');

        Livewire::actingAs($w['sellerA'])
            ->test(SellerAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('counterTermId', $victim->id)
            ->set('additional_details', 'RETARGETED')
            ->set('protection_period', '999')
            ->call('submit')
            ->assertStatus(403);

        $this->assertVictimIntact($victim, $w['sellerA'], 'TERMS-FOR-AGENT-A2');
        $this->assertSame((int) $w['bidA2']->id, (int) $victim->seller_agent_auction_bid_id);
    }

    /**
     * Seller-specific: a negotiation has TWO counter authors. Being a party to the
     * bid does not entitle you to edit the OTHER party's counter row.
     */
    public function test_bidding_agent_cannot_edit_the_sellers_own_counter_on_the_same_bid(): void
    {
        $w = $this->world();

        $sellerCounter = $this->counterTermFor($w['sellerA'], $w['bidA'], $w['auctionA'], 'SELLER-A-TERMS');

        // agentA is a legitimate party to this exact bid, and the counter-back
        // precondition is satisfied — so mount succeeds. The row still is not theirs.
        Livewire::actingAs($w['agentA'])
            ->test(SellerAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('counterTermId', $sellerCounter->id)
            ->set('additional_details', 'AGENT-OVERWROTE-SELLER')
            ->set('protection_period', '999')
            ->call('submit')
            ->assertStatus(403);

        $this->assertVictimIntact($sellerCounter, $w['sellerA'], 'SELLER-A-TERMS');
    }

    public function test_post_mount_counter_term_id_tampering_is_denied_on_submit(): void
    {
        $w = $this->world();

        $victim = $this->counterTermFor($w['sellerB'], $w['bidB'], $w['auctionB'], 'SELLER-B-PRIVATE-TERMS');

        // Mounts legitimately, then swaps the target on the submit request.
        Livewire::actingAs($w['sellerA'])
            ->test(SellerAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('additional_details', 'LOOKS-LEGITIMATE')
            ->set('counterTermId', $victim->id)
            ->call('submit')
            ->assertStatus(403);

        $this->assertVictimIntact($victim, $w['sellerB'], 'SELLER-B-PRIVATE-TERMS');
    }

    public function test_nonexistent_counter_term_id_is_refused_rather_than_creating_a_row(): void
    {
        $w = $this->world();

        Livewire::actingAs($w['sellerA'])
            ->test(SellerAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('counterTermId', 999999)
            ->set('additional_details', 'GHOST')
            ->call('submit')
            ->assertStatus(403);

        $this->assertSame(0, SellerCounterTerm::count());
    }

    public function test_post_mount_auction_id_tampering_is_denied_on_submit(): void
    {
        $w = $this->world();

        // $auctionId is public and the create path reads it.
        Livewire::actingAs($w['sellerA'])
            ->test(SellerAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('auctionId', $w['auctionB']->id)
            ->set('additional_details', 'FORGED-TUPLE')
            ->call('submit')
            ->assertStatus(403);

        $this->assertSame(
            0,
            SellerCounterTerm::where('seller_agent_auction_id', $w['auctionB']->id)->count(),
            'A counter term was written against an auction the caller is not party to.'
        );
    }

    // =====================================================================
    // Unauthenticated
    // =====================================================================

    public function test_unauthenticated_access_is_refused(): void
    {
        $w = $this->world();

        $this->get(route('seller.counter-terms', ['id' => $w['bidA']->id]))
            ->assertRedirect(route('login'));

        $this->assertSame(0, SellerCounterTerm::count());
    }

    // =====================================================================
    // Legitimate behaviour is preserved
    // =====================================================================

    public function test_legitimate_new_counter_still_persists(): void
    {
        $w = $this->world();

        Livewire::actingAs($w['sellerA'])
            ->test(SellerAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('additional_details', 'LEGITIMATE-NEW-COUNTER')
            ->call('submit');

        $this->assertNull(session('error'), 'submit() flashed an error instead of saving: ' . (string) session('error'));

        $row = SellerCounterTerm::where('user_id', $w['sellerA']->id)->latest('id')->first();

        $this->assertNotNull($row, 'The seller counter term did not persist.');
        $this->assertSame((int) $w['bidA']->id, (int) $row->seller_agent_auction_bid_id);
        $this->assertSame((int) $w['auctionA']->id, (int) $row->seller_agent_auction_id);
        $this->assertSame('LEGITIMATE-NEW-COUNTER', $this->metaOf($row, 'additional_details'));
    }

    public function test_legitimate_update_persists_without_creating_a_duplicate(): void
    {
        $w = $this->world();

        Livewire::actingAs($w['sellerA'])
            ->test(SellerAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('additional_details', 'FIRST-PASS')
            ->call('submit');

        $row = SellerCounterTerm::where('user_id', $w['sellerA']->id)->latest('id')->firstOrFail();

        // Remounting finds it (status=1, same bid, same user) and edits it in place.
        Livewire::actingAs($w['sellerA'])
            ->test(SellerAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('additional_details', 'SECOND-PASS')
            ->call('submit');

        $this->assertNull(session('error'));
        $this->assertSame(
            1,
            SellerCounterTerm::where('user_id', $w['sellerA']->id)->count(),
            'The legitimate edit created a second row instead of updating the first.'
        );

        $row->refresh();
        $this->assertSame('SECOND-PASS', $this->metaOf($row, 'additional_details'));
    }

    public function test_agent_counter_back_still_persists_as_its_own_row(): void
    {
        $w = $this->world();

        $sellerCounter = $this->counterTermFor($w['sellerA'], $w['bidA'], $w['auctionA'], 'SELLER-A-TERMS');

        Livewire::actingAs($w['agentA'])
            ->test(SellerAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('additional_details', 'AGENT-COUNTER-BACK')
            ->call('submit');

        $this->assertNull(session('error'));

        $agentRow = SellerCounterTerm::where('user_id', $w['agentA']->id)->latest('id')->first();

        $this->assertNotNull($agentRow, 'The agent counter-back did not persist.');
        $this->assertSame((int) $sellerCounter->id, (int) $agentRow->parent_counter_id);

        // The seller's own row is a separate record and is untouched.
        $this->assertVictimIntact($sellerCounter, $w['sellerA'], 'SELLER-A-TERMS');
        $this->assertSame(2, SellerCounterTerm::count());
    }

    public function test_meta_writes_stay_scoped_to_the_authorized_counter(): void
    {
        $w = $this->world();

        $other = $this->counterTermFor($w['sellerB'], $w['bidB'], $w['auctionB'], 'SELLER-B-PRIVATE-TERMS');

        Livewire::actingAs($w['sellerA'])
            ->test(SellerAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('additional_details', 'ONLY-MINE')
            ->call('submit');

        $mine = SellerCounterTerm::where('user_id', $w['sellerA']->id)->latest('id')->firstOrFail();
        $this->assertSame('ONLY-MINE', $this->metaOf($mine, 'additional_details'));

        $this->assertVictimIntact($other, $w['sellerB'], 'SELLER-B-PRIVATE-TERMS');
    }

    public function test_notification_reaches_only_the_intended_counterparty(): void
    {
        $w = $this->world();

        Livewire::actingAs($w['sellerA'])
            ->test(SellerAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->call('submit');

        // The seller countered, so the bidding agent on THIS bid is the recipient.
        Notification::assertSentTo($w['agentA'], CounterBidSubmittedNotification::class);

        foreach (['sellerA', 'sellerB', 'agentA2', 'agentB', 'outsider'] as $key) {
            Notification::assertNotSentTo($w[$key], CounterBidSubmittedNotification::class);
        }
    }
}
