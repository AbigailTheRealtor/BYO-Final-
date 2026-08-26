<?php

namespace Tests\Feature\Security;

use App\Http\Livewire\Buyer\BuyerAgentAuctionCounterTerm;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionBid;
use App\Models\BuyerCounterTerm;
use App\Models\User;
use App\Notifications\CounterBidSubmittedNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Buyer Counter Terms — party authorization and negotiation scoping.
 *
 * ── WHAT WAS WRONG ──────────────────────────────────────────────────────────
 *
 * Nothing authorized the live path. `BuyerCounteredTermsController::add()/edit()`
 * loaded the bid straight from the URL id and rendered; the Livewire component had
 * no guard in `mount()` or `submit()`. The buyer route prefix carries only `auth` +
 * `verified`, so ANY verified user of ANY role could open any bid's counter screen
 * and write to it.
 *
 * The controller's `store()`/`update()` DO carry guards — but neither is routed
 * (`route:list` shows only `add` and `edit`). The guards protected dead code while
 * the two live entry points had none.
 *
 * Three holes:
 *
 *   1. NON-PARTY ACCESS. No check that the caller is the listing owner or the
 *      bidding agent for the bid named in the URL.
 *
 *   2. INCOHERENT TUPLE. `$bidId` and `$auctionId` are SEPARATE public properties
 *      and the create read both, so a caller party to one negotiation could forge a
 *      row whose auction and bid belonged to different negotiations. `$pab` is a
 *      third independently-supplied handle.
 *
 *   3. `$counterTermId` HIJACK. A plain public property fed straight into
 *      `findOrFail(...)->update(...)`. Because the update leaves `user_id` alone,
 *      the rewrite stayed attributed to the victim.
 *
 * ── BUYER IS NOT LANDLORD ───────────────────────────────────────────────────
 *
 * `buyer_counter_terms.buyer_agent_auction_id` is a genuine AUCTION id (FK →
 * `buyer_agent_auctions.id`). That is the opposite of `landlord_counter_terms`,
 * whose same-shaped column holds a BID id. The bid is carried here in
 * `parent_counter_id`: despite that column's "counter-back chain" name, both write
 * paths store the bid id in it and mount()'s edit lookup keys off it.
 *
 * So a Buyer counter term is pinned by BOTH columns, and the guard must compare
 * both — auction alone would admit a sibling bid's row on the same listing.
 *
 * The two legitimate parties are not invented here: BuyerAgentAuctionBidController
 * ::view_counter_terms() — the page that links to this screen — already admits
 * exactly `isAgent || isBuyer` and 403s everyone else.
 *
 * ── SCOPE ───────────────────────────────────────────────────────────────────
 *
 * Buyer only. Seller carries the same shape and is the next follow-up. Landlord was
 * hardened in PR #96 and Tenant in #91/#87; this mirrors that pattern rather than
 * inventing a third one. No schema changes — the scoping columns already exist.
 */
class BuyerCounterTermAuthorizationTest extends TestCase
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
     * A: buyerA's listing, agentA bidding — plus a SECOND bid (bidA2) from agentA2 on
     *    that same listing, so the sibling-bid case can be tested without also
     *    changing the auction or the user.
     * B: buyerB's listing, agentB bidding.
     */
    private function world(): array
    {
        $buyerA   = User::factory()->create(['user_type' => 'buyer']);
        $buyerB   = User::factory()->create(['user_type' => 'buyer']);
        $agentA   = User::factory()->asAgent()->create();
        $agentA2  = User::factory()->asAgent()->create();
        $agentB   = User::factory()->asAgent()->create();
        $outsider = User::factory()->create();

        // Decoys first so auction ids and bid ids can never coincide — an assertion
        // written while those numbers happened to be equal would pass for either
        // meaning and prove neither.
        BuyerAgentAuction::forceCreate($this->auctionAttrs($buyerA));
        BuyerAgentAuction::forceCreate($this->auctionAttrs($buyerA));

        $auctionA = BuyerAgentAuction::forceCreate($this->auctionAttrs($buyerA));
        $auctionB = BuyerAgentAuction::forceCreate($this->auctionAttrs($buyerB));

        $bidA = BuyerAgentAuctionBid::forceCreate([
            'buyer_agent_auction_id' => $auctionA->id,
            'user_id'                => $agentA->id,
        ]);
        $bidA2 = BuyerAgentAuctionBid::forceCreate([
            'buyer_agent_auction_id' => $auctionA->id,
            'user_id'                => $agentA2->id,
        ]);
        $bidB = BuyerAgentAuctionBid::forceCreate([
            'buyer_agent_auction_id' => $auctionB->id,
            'user_id'                => $agentB->id,
        ]);

        $this->assertNotSame((int) $auctionA->id, (int) $bidA->id);

        return compact(
            'buyerA', 'buyerB', 'agentA', 'agentA2', 'agentB', 'outsider',
            'auctionA', 'auctionB', 'bidA', 'bidA2', 'bidB'
        );
    }

    /** buyer_agent_auctions requires title and address (both NOT NULL, no default). */
    private function auctionAttrs(User $owner): array
    {
        return [
            'user_id' => $owner->id,
            'title'   => 'Buyer listing',
            'address' => '1 Test Street',
        ];
    }

    /**
     * A counter term written directly, bypassing the component.
     *
     * $bid may be null to model a LEGACY row — the retired controller store() wrote
     * rows with buyer_agent_auction_id set and parent_counter_id NULL.
     */
    private function counterTermFor(User $user, BuyerAgentAuction $auction, ?BuyerAgentAuctionBid $bid, string $details): BuyerCounterTerm
    {
        $term = BuyerCounterTerm::forceCreate([
            'user_id'                => $user->id,
            'buyer_agent_auction_id' => $auction->id,   // a real AUCTION id
            'parent_counter_id'      => $bid?->id,      // the BID id, or NULL for legacy
            'property_type'          => 'residential',
            'status'                 => 1,
        ]);
        $term->saveMeta('additional_details', $details);

        return $term;
    }

    private function detailsOf(BuyerCounterTerm $term): ?string
    {
        return optional($term->meta()->where('meta_key', 'additional_details')->first())->meta_value;
    }

    /** Mounting the component directly must fail closed for a non-party. */
    private function assertMountDenied(User $actor, $pab, $bidId): void
    {
        Livewire::actingAs($actor)
            ->test(BuyerAgentAuctionCounterTerm::class, ['pab' => $pab, 'bidId' => $bidId])
            ->assertStatus(403);
    }

    // =====================================================================
    // 1–2. The legitimate parties still get in
    // =====================================================================

    public function test_listing_owner_can_reach_their_own_negotiation(): void
    {
        $w = $this->world();

        $this->actingAs($w['buyerA'])
            ->get(route('buyer.counter-terms', ['id' => $w['bidA']->id]))
            ->assertOk();
    }

    public function test_bidding_agent_can_reach_the_negotiation_they_bid_on(): void
    {
        $w = $this->world();

        $this->actingAs($w['agentA'])
            ->get(route('buyer.counter-terms', ['id' => $w['bidA']->id]))
            ->assertOk();

        $this->actingAs($w['agentA'])
            ->get(route('buyer.edit-counter-terms', ['id' => $w['bidA']->id]))
            ->assertOk();
    }

    // =====================================================================
    // 3–5. Non-parties are refused
    // =====================================================================

    public function test_unrelated_authenticated_user_is_denied(): void
    {
        $w = $this->world();

        $this->actingAs($w['outsider'])
            ->get(route('buyer.counter-terms', ['id' => $w['bidA']->id]))
            ->assertForbidden();

        $this->assertMountDenied($w['outsider'], $w['bidA'], $w['bidA']->id);
    }

    public function test_buyer_from_another_negotiation_is_denied(): void
    {
        $w = $this->world();

        $this->actingAs($w['buyerB'])
            ->get(route('buyer.counter-terms', ['id' => $w['bidA']->id]))
            ->assertForbidden();

        $this->assertMountDenied($w['buyerB'], $w['bidA'], $w['bidA']->id);
    }

    public function test_agent_from_another_negotiation_is_denied(): void
    {
        $w = $this->world();

        $this->actingAs($w['agentB'])
            ->get(route('buyer.counter-terms', ['id' => $w['bidA']->id]))
            ->assertForbidden();

        $this->assertMountDenied($w['agentB'], $w['bidA'], $w['bidA']->id);
    }

    // =====================================================================
    // 6. Individually valid ids combined into a mismatched tuple
    // =====================================================================

    public function test_mismatched_pab_and_bid_id_tuple_is_denied(): void
    {
        $w = $this->world();

        // buyerA IS a party to bidA, and bidB is a real bid. Neither id is invalid;
        // the pair describes two different negotiations.
        $this->assertMountDenied($w['buyerA'], $w['bidB'], $w['bidA']->id);
    }

    public function test_mismatched_auction_id_is_denied_on_submit(): void
    {
        $w = $this->world();

        // $auctionId is a separate public property that the create reads. Left
        // unchecked this forges a row whose auction and bid disagree.
        Livewire::actingAs($w['buyerA'])
            ->test(BuyerAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('auctionId', $w['auctionB']->id)
            ->set('additional_details', 'FORGED-TUPLE')
            ->call('submit')
            ->assertStatus(403);

        $this->assertSame(
            0,
            BuyerCounterTerm::where('buyer_agent_auction_id', $w['auctionB']->id)->count(),
            'A counter term was written against an auction the caller is not party to.'
        );
    }

    // =====================================================================
    // 7–10. counterTermId ownership and post-mount tampering
    // =====================================================================

    public function test_counter_term_id_from_another_negotiation_is_denied(): void
    {
        $w = $this->world();

        $victim = $this->counterTermFor($w['buyerB'], $w['auctionB'], $w['bidB'], 'BUYER-B-TERMS');

        Livewire::actingAs($w['buyerA'])
            ->test(BuyerAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('counterTermId', $victim->id)
            ->set('additional_details', 'HIJACKED')
            ->call('submit')
            ->assertStatus(403);

        $victim->refresh();
        $this->assertSame('BUYER-B-TERMS', $this->detailsOf($victim));
        $this->assertSame((int) $w['buyerB']->id, (int) $victim->user_id);
    }

    public function test_counter_term_id_from_a_sibling_bid_is_denied(): void
    {
        $w = $this->world();

        // Same user, same AUCTION — only the bid differs. An auction-only guard would
        // wrongly admit this; the parent_counter_id clause is what stops it.
        $victim = $this->counterTermFor($w['buyerA'], $w['auctionA'], $w['bidA2'], 'TERMS-FOR-AGENT-A2');

        Livewire::actingAs($w['buyerA'])
            ->test(BuyerAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('counterTermId', $victim->id)
            ->set('additional_details', 'RETARGETED-AT-AGENT-A')
            ->call('submit')
            ->assertStatus(403);

        $victim->refresh();
        $this->assertSame('TERMS-FOR-AGENT-A2', $this->detailsOf($victim));
        $this->assertSame((int) $w['bidA2']->id, (int) $victim->parent_counter_id);
    }

    public function test_legacy_row_with_no_bid_cannot_be_adopted(): void
    {
        $w = $this->world();

        // The retired controller store() wrote rows with no bid at all. An unscoped
        // historical row must not become the editable counter for a specific bid.
        $legacy = $this->counterTermFor($w['buyerA'], $w['auctionA'], null, 'LEGACY-UNSCOPED');

        Livewire::actingAs($w['buyerA'])
            ->test(BuyerAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('counterTermId', $legacy->id)
            ->set('additional_details', 'ADOPTED-LEGACY')
            ->call('submit')
            ->assertStatus(403);

        $legacy->refresh();
        $this->assertSame('LEGACY-UNSCOPED', $this->detailsOf($legacy));
        $this->assertNull($legacy->parent_counter_id);
    }

    public function test_post_mount_counter_term_id_tampering_is_denied_on_submit(): void
    {
        $w = $this->world();

        $victim = $this->counterTermFor($w['buyerB'], $w['auctionB'], $w['bidB'], 'BUYER-B-TERMS');

        // Mounts legitimately, then swaps the target on the submit request — the exact
        // shape mount()-only authorization cannot see.
        Livewire::actingAs($w['buyerA'])
            ->test(BuyerAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('additional_details', 'LOOKS-LEGITIMATE')
            ->set('counterTermId', $victim->id)
            ->call('submit')
            ->assertStatus(403);

        $victim->refresh();
        $this->assertSame('BUYER-B-TERMS', $this->detailsOf($victim));
    }

    public function test_post_mount_bid_context_tampering_is_denied_on_submit(): void
    {
        $w = $this->world();

        Livewire::actingAs($w['buyerA'])
            ->test(BuyerAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('bidId', $w['bidB']->id)
            ->set('additional_details', 'WRITTEN-INTO-ANOTHER-NEGOTIATION')
            ->call('submit')
            ->assertStatus(403);

        $this->assertSame(
            0,
            BuyerCounterTerm::where('parent_counter_id', $w['bidB']->id)->count(),
            'A counter term was written into a negotiation the caller is not party to.'
        );
    }

    // =====================================================================
    // 11. Unauthenticated
    // =====================================================================

    public function test_unauthenticated_access_is_refused(): void
    {
        $w = $this->world();

        $this->get(route('buyer.counter-terms', ['id' => $w['bidA']->id]))
            ->assertRedirect(route('login'));

        $this->assertSame(0, BuyerCounterTerm::count());
    }

    // =====================================================================
    // 12–15. Legitimate behaviour is preserved
    // =====================================================================

    public function test_legitimate_new_counter_still_persists(): void
    {
        $w = $this->world();

        Livewire::actingAs($w['buyerA'])
            ->test(BuyerAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('additional_details', 'LEGITIMATE-NEW-COUNTER')
            ->call('submit');

        $this->assertNull(session('error'), 'submit() flashed an error instead of saving: ' . (string) session('error'));

        $row = BuyerCounterTerm::where('user_id', $w['buyerA']->id)->latest('id')->first();

        $this->assertNotNull($row, 'The buyer counter term did not persist.');
        $this->assertSame((int) $w['auctionA']->id, (int) $row->buyer_agent_auction_id);
        $this->assertSame((int) $w['bidA']->id, (int) $row->parent_counter_id);
        $this->assertNotSame((int) $w['auctionA']->id, (int) $row->parent_counter_id);
        $this->assertSame('LEGITIMATE-NEW-COUNTER', $row->getMeta('additional_details'));
    }

    public function test_legitimate_update_of_the_callers_own_counter_still_persists(): void
    {
        $w = $this->world();

        Livewire::actingAs($w['buyerA'])
            ->test(BuyerAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('additional_details', 'FIRST-PASS')
            ->call('submit');

        $row = BuyerCounterTerm::where('user_id', $w['buyerA']->id)->latest('id')->firstOrFail();

        // Remounting finds it (status=1, same auction, same bid, same user) and edits
        // it in place rather than creating a second row.
        Livewire::actingAs($w['buyerA'])
            ->test(BuyerAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('additional_details', 'SECOND-PASS')
            ->call('submit');

        $this->assertNull(session('error'));
        $this->assertSame(
            1,
            BuyerCounterTerm::where('user_id', $w['buyerA']->id)->count(),
            'The legitimate edit created a second row instead of updating the first.'
        );

        $row->refresh();
        $this->assertSame('SECOND-PASS', $this->detailsOf($row));
    }

    public function test_meta_writes_stay_scoped_to_the_authorized_counter(): void
    {
        $w = $this->world();

        $other = $this->counterTermFor($w['buyerB'], $w['auctionB'], $w['bidB'], 'BUYER-B-TERMS');

        Livewire::actingAs($w['buyerA'])
            ->test(BuyerAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('additional_details', 'ONLY-MINE')
            ->call('submit');

        $mine = BuyerCounterTerm::where('user_id', $w['buyerA']->id)->latest('id')->firstOrFail();

        $this->assertSame('ONLY-MINE', $this->detailsOf($mine));

        $other->refresh();
        $this->assertSame('BUYER-B-TERMS', $this->detailsOf($other), 'A meta write leaked onto another negotiation.');
    }

    public function test_notification_reaches_only_the_legitimate_counterparty(): void
    {
        $w = $this->world();

        Livewire::actingAs($w['buyerA'])
            ->test(BuyerAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->call('submit');

        // The Buyer rule is that the countered bid's agent is the recipient. That rule
        // is unchanged — only its derivation moved to the authorized models.
        Notification::assertSentTo($w['agentA'], CounterBidSubmittedNotification::class);

        foreach (['buyerA', 'buyerB', 'agentA2', 'agentB', 'outsider'] as $key) {
            Notification::assertNotSentTo($w[$key], CounterBidSubmittedNotification::class);
        }
    }
}
