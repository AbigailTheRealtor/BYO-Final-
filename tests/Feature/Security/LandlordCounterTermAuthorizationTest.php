<?php

namespace Tests\Feature\Security;

use App\Http\Livewire\Landlord\LandlordAgentAuctionCounterTerm;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionBid;
use App\Models\LandlordCounterTerm;
use App\Models\User;
use App\Notifications\CounterBidSubmittedNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Landlord Counter Terms — party authorization and negotiation scoping.
 *
 * ── WHAT WAS WRONG ──────────────────────────────────────────────────────────
 *
 * Nothing authorized this surface. `LandlordCounteredTermsController::add()/edit()`
 * loaded the bid straight from the URL id and rendered; the Livewire component had
 * no guard in either `mount()` or `submit()`. The landlord route prefix carries only
 * `auth` + `verified` — its `landlordAuth` middleware is commented out — so ANY
 * verified user of ANY role could open any bid's counter screen and write to it.
 *
 * Two distinct holes, both closed here:
 *
 *   1. NON-PARTY ACCESS. No check that the caller is the listing owner or the
 *      bidding agent for the bid named in the URL.
 *
 *   2. `$counterTermId` HIJACK. It is a plain public Livewire property, so the
 *      client sets it freely on any request after mount. `submit()` ran
 *      `findOrFail($this->counterTermId)->update(...)` with no ownership check, and
 *      because the update leaves `user_id` alone the rewrite stayed attributed to
 *      the victim. A valid `$pab` authorized a completely unrelated `$counterTermId`.
 *
 * PR #95 is what made this reachable: the page previously died in the view on an
 * undefined `$defaultProfileLoaded`, so the write path behind it could not be
 * driven. Fixing the render removed the only thing standing in front of it.
 *
 * ── SCOPE ───────────────────────────────────────────────────────────────────
 *
 * Landlord only. Buyer and Seller carry the same shape and are deliberately left
 * for separate follow-ups. Tenant was hardened in PR #91/#87 and this mirrors that
 * pattern rather than inventing a second one.
 *
 * `landlord_counter_terms.landlord_agent_auction_id` stores a BID id despite its
 * name (its FK references `landlord_agent_auction_bids.id`). The fixtures below
 * create decoy auctions so an auction id and a bid id can never coincide — an
 * assertion written while those numbers happened to be equal would pass for either
 * meaning and prove neither.
 */
class LandlordCounterTermAuthorizationTest extends TestCase
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
     * A: ownerA's listing, agentA bidding — and a SECOND bid (bidA2) from agentA2 on
     *    that same listing, so "another bid" can be tested without also changing the
     *    auction or the user.
     * B: ownerB's listing, agentB bidding.
     */
    private function world(): array
    {
        $ownerA   = User::factory()->create(['user_type' => 'landlord']);
        $ownerB   = User::factory()->create(['user_type' => 'landlord']);
        $agentA   = User::factory()->asAgent()->create();
        $agentA2  = User::factory()->asAgent()->create();
        $agentB   = User::factory()->asAgent()->create();
        $outsider = User::factory()->create();

        // Decoys first so auction ids and bid ids can never coincide.
        LandlordAgentAuction::forceCreate(['user_id' => $ownerA->id]);
        LandlordAgentAuction::forceCreate(['user_id' => $ownerA->id]);

        $auctionA = LandlordAgentAuction::forceCreate(['user_id' => $ownerA->id]);
        $auctionB = LandlordAgentAuction::forceCreate(['user_id' => $ownerB->id]);

        $bidA = LandlordAgentAuctionBid::forceCreate([
            'landlord_agent_auction_id' => $auctionA->id,
            'user_id'                   => $agentA->id,
        ]);
        $bidA2 = LandlordAgentAuctionBid::forceCreate([
            'landlord_agent_auction_id' => $auctionA->id,
            'user_id'                   => $agentA2->id,
        ]);
        $bidB = LandlordAgentAuctionBid::forceCreate([
            'landlord_agent_auction_id' => $auctionB->id,
            'user_id'                   => $agentB->id,
        ]);

        $this->assertNotSame((int) $auctionA->id, (int) $bidA->id);

        return compact(
            'ownerA', 'ownerB', 'agentA', 'agentA2', 'agentB', 'outsider',
            'auctionA', 'auctionB', 'bidA', 'bidA2', 'bidB'
        );
    }

    /** A counter term written directly, bypassing the component. */
    private function counterTermFor(User $user, LandlordAgentAuctionBid $bid, string $details): LandlordCounterTerm
    {
        $term = LandlordCounterTerm::forceCreate([
            'user_id'                   => $user->id,
            'landlord_agent_auction_id' => $bid->id, // stores a BID id
            'property_type'             => 'residential',
            'status'                    => 1,
        ]);
        $term->saveMeta('additional_details', $details);

        return $term;
    }

    private function detailsOf(LandlordCounterTerm $term): ?string
    {
        return optional($term->meta()->where('meta_key', 'additional_details')->first())->meta_value;
    }

    /**
     * Mounting the component directly must fail closed for a non-party.
     *
     * Livewire's test harness renders the abort into a response rather than letting
     * the HttpException escape, so this asserts the status the same way the
     * submit-time cases do.
     */
    private function assertMountDenied(User $actor, $pab, $bidId): void
    {
        Livewire::actingAs($actor)
            ->test(LandlordAgentAuctionCounterTerm::class, ['pab' => $pab, 'bidId' => $bidId])
            ->assertStatus(403);
    }

    // =====================================================================
    // 1–2. The legitimate parties still get in
    // =====================================================================

    public function test_listing_owner_can_reach_their_own_negotiation(): void
    {
        $w = $this->world();

        $this->actingAs($w['ownerA'])
            ->get(route('landlord.counter-terms', ['id' => $w['bidA']->id]))
            ->assertOk()
            ->assertSee('Broker Compensation', false);
    }

    public function test_bidding_agent_can_reach_the_negotiation_they_bid_on(): void
    {
        $w = $this->world();

        $this->actingAs($w['agentA'])
            ->get(route('landlord.counter-terms', ['id' => $w['bidA']->id]))
            ->assertOk();

        $this->actingAs($w['agentA'])
            ->get(route('landlord.edit-counter-terms', ['id' => $w['bidA']->id]))
            ->assertOk();
    }

    // =====================================================================
    // 3–5. Non-parties are refused
    // =====================================================================

    public function test_unrelated_authenticated_user_is_denied(): void
    {
        $w = $this->world();

        $this->actingAs($w['outsider'])
            ->get(route('landlord.counter-terms', ['id' => $w['bidA']->id]))
            ->assertForbidden();

        $this->assertMountDenied($w['outsider'], $w['bidA'], $w['bidA']->id);
    }

    public function test_owner_of_another_landlord_auction_is_denied(): void
    {
        $w = $this->world();

        $this->actingAs($w['ownerB'])
            ->get(route('landlord.counter-terms', ['id' => $w['bidA']->id]))
            ->assertForbidden();

        $this->assertMountDenied($w['ownerB'], $w['bidA'], $w['bidA']->id);
    }

    public function test_bidder_from_another_auction_is_denied(): void
    {
        $w = $this->world();

        $this->actingAs($w['agentB'])
            ->get(route('landlord.counter-terms', ['id' => $w['bidA']->id]))
            ->assertForbidden();

        $this->assertMountDenied($w['agentB'], $w['bidA'], $w['bidA']->id);
    }

    // =====================================================================
    // 6. Individually valid ids combined into a mismatched tuple
    // =====================================================================

    public function test_mismatched_pab_and_bid_id_tuple_is_denied(): void
    {
        $w = $this->world();

        // ownerA IS a party to bidA, and bidB is a real bid. Neither id is invalid;
        // the pair describes two different negotiations.
        $this->assertMountDenied($w['ownerA'], $w['bidB'], $w['bidA']->id);
    }

    public function test_mismatched_auction_pab_against_another_bid_is_denied(): void
    {
        $w = $this->world();

        // $pab may legitimately be an auction on this surface — but it must be the
        // auction the authorized bid belongs to.
        $this->assertMountDenied($w['ownerA'], $w['auctionB'], $w['bidA']->id);
    }

    // =====================================================================
    // 7–9. counterTermId ownership and post-mount tampering
    // =====================================================================

    public function test_counter_term_id_from_a_sibling_bid_is_denied(): void
    {
        $w = $this->world();

        // Same user, same auction — only the bid differs. This is the case a
        // user-plus-auction check would wrongly admit.
        $victim = $this->counterTermFor($w['ownerA'], $w['bidA2'], 'TERMS-FOR-AGENT-A2');

        Livewire::actingAs($w['ownerA'])
            ->test(LandlordAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('counterTermId', $victim->id)
            ->set('additional_details', 'RETARGETED-AT-AGENT-A')
            ->call('submit')
            ->assertStatus(403);

        $victim->refresh();
        $this->assertSame('TERMS-FOR-AGENT-A2', $this->detailsOf($victim));
        $this->assertSame((int) $w['bidA2']->id, (int) $victim->landlord_agent_auction_id);
    }

    public function test_counter_term_id_from_another_auction_and_landlord_is_denied(): void
    {
        $w = $this->world();

        $victim = $this->counterTermFor($w['ownerB'], $w['bidB'], 'OWNER-B-TERMS');

        Livewire::actingAs($w['ownerA'])
            ->test(LandlordAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('counterTermId', $victim->id)
            ->set('additional_details', 'HIJACKED')
            ->call('submit')
            ->assertStatus(403);

        $victim->refresh();
        $this->assertSame('OWNER-B-TERMS', $this->detailsOf($victim));
        $this->assertSame((int) $w['ownerB']->id, (int) $victim->user_id);
    }

    public function test_post_mount_counter_term_id_tampering_is_denied_on_submit(): void
    {
        $w = $this->world();

        $victim = $this->counterTermFor($w['ownerB'], $w['bidB'], 'OWNER-B-TERMS');

        // Mounts legitimately, then swaps the target on the submit request — the
        // exact shape mount()-only authorization cannot see.
        Livewire::actingAs($w['ownerA'])
            ->test(LandlordAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('additional_details', 'LOOKS-LEGITIMATE')
            ->set('counterTermId', $victim->id)
            ->call('submit')
            ->assertStatus(403);

        $victim->refresh();
        $this->assertSame('OWNER-B-TERMS', $this->detailsOf($victim));
    }

    public function test_post_mount_bid_context_tampering_is_denied_on_submit(): void
    {
        $w = $this->world();

        Livewire::actingAs($w['ownerA'])
            ->test(LandlordAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('bidId', $w['bidB']->id)
            ->set('additional_details', 'WRITTEN-INTO-ANOTHER-NEGOTIATION')
            ->call('submit')
            ->assertStatus(403);

        $this->assertSame(
            0,
            LandlordCounterTerm::where('landlord_agent_auction_id', $w['bidB']->id)->count(),
            'A counter term was written into a negotiation the caller is not party to.'
        );
    }

    // =====================================================================
    // 11. Unauthenticated
    // =====================================================================

    public function test_unauthenticated_access_is_refused(): void
    {
        $w = $this->world();

        $this->get(route('landlord.counter-terms', ['id' => $w['bidA']->id]))
            ->assertRedirect(route('login'));

        $this->assertSame(0, LandlordCounterTerm::count());
    }

    // =====================================================================
    // 12–15. Legitimate behaviour is preserved
    // =====================================================================

    public function test_legitimate_new_counter_still_persists(): void
    {
        $w = $this->world();

        Livewire::actingAs($w['ownerA'])
            ->test(LandlordAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('additional_details', 'LEGITIMATE-NEW-COUNTER')
            ->call('submit');

        $this->assertNull(session('error'), 'submit() flashed an error instead of saving: ' . (string) session('error'));

        $row = LandlordCounterTerm::where('user_id', $w['ownerA']->id)->latest('id')->first();

        $this->assertNotNull($row, 'The landlord counter term did not persist.');
        $this->assertSame((int) $w['bidA']->id, (int) $row->landlord_agent_auction_id);
        $this->assertNotSame((int) $w['auctionA']->id, (int) $row->landlord_agent_auction_id);
        $this->assertSame('LEGITIMATE-NEW-COUNTER', $row->getMeta('additional_details'));
    }

    public function test_legitimate_update_of_the_callers_own_counter_still_persists(): void
    {
        $w = $this->world();

        // First submit creates it; the component tracks the id for edit mode.
        Livewire::actingAs($w['ownerA'])
            ->test(LandlordAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('additional_details', 'FIRST-PASS')
            ->call('submit');

        $row = LandlordCounterTerm::where('user_id', $w['ownerA']->id)->latest('id')->firstOrFail();

        // Remounting finds it (status=1, same bid, same user) and edits in place.
        Livewire::actingAs($w['ownerA'])
            ->test(LandlordAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('additional_details', 'SECOND-PASS')
            ->call('submit');

        $this->assertNull(session('error'));
        $this->assertSame(
            1,
            LandlordCounterTerm::where('user_id', $w['ownerA']->id)->count(),
            'The legitimate edit created a second row instead of updating the first.'
        );

        $row->refresh();
        $this->assertSame('SECOND-PASS', $this->detailsOf($row));
    }

    public function test_meta_writes_stay_scoped_to_the_authorized_counter(): void
    {
        $w = $this->world();

        $other = $this->counterTermFor($w['ownerB'], $w['bidB'], 'OWNER-B-TERMS');

        Livewire::actingAs($w['ownerA'])
            ->test(LandlordAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->set('additional_details', 'ONLY-MINE')
            ->call('submit');

        $mine = LandlordCounterTerm::where('user_id', $w['ownerA']->id)->latest('id')->firstOrFail();

        $this->assertSame('ONLY-MINE', $this->detailsOf($mine));

        $other->refresh();
        $this->assertSame('OWNER-B-TERMS', $this->detailsOf($other), 'A meta write leaked onto another negotiation.');
    }

    public function test_notification_reaches_only_the_legitimate_counterparty(): void
    {
        $w = $this->world();

        Livewire::actingAs($w['ownerA'])
            ->test(LandlordAgentAuctionCounterTerm::class, ['pab' => $w['bidA'], 'bidId' => $w['bidA']->id])
            ->call('submit');

        // The listing owner countered, so the bidding agent on THIS bid is the recipient.
        Notification::assertSentTo($w['agentA'], CounterBidSubmittedNotification::class);

        foreach (['ownerA', 'ownerB', 'agentA2', 'agentB', 'outsider'] as $key) {
            Notification::assertNotSentTo($w[$key], CounterBidSubmittedNotification::class);
        }
    }
}
