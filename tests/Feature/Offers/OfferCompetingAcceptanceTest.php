<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\User;
use App\Services\Offers\OfferDecisionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * BLK-05 — accepting one offer must close every competing root offer on the same
 * parent, must not touch other listings, and must never allow two offers to be
 * accepted for the same parent.
 */
class OfferCompetingAcceptanceTest extends TestCase
{
    use DatabaseTransactions;

    private function decision(): OfferDecisionService
    {
        return app(OfferDecisionService::class);
    }

    /** Create an auction owned by a fresh seller. */
    private function auction(): OfferAuction
    {
        return OfferAuction::factory()->create(['user_id' => User::factory()->create()->id]);
    }

    private function rootOffer(OfferAuction $auction, string $status = 'submitted'): Offer
    {
        return Offer::factory()->create([
            'offer_auction_id' => $auction->id,
            'user_id'          => User::factory()->create()->id,
            'status'           => $status,
            'parent_offer_id'  => null,
        ]);
    }

    // ── Accepting one root closes all other active competing root offers ─────

    public function test_accepting_one_root_closes_all_competing_roots(): void
    {
        $auction = $this->auction();
        $accepted   = $this->rootOffer($auction, 'submitted');
        $competitor1 = $this->rootOffer($auction, 'submitted');
        $competitor2 = $this->rootOffer($auction, 'countered');

        $result = $this->decision()->accept($accepted, null, 'system');

        $this->assertTrue($result['allowed']);
        $this->assertSame('accepted', Offer::find($accepted->id)->status);
        $this->assertSame('rejected', Offer::find($competitor1->id)->status);
        $this->assertSame('rejected', Offer::find($competitor2->id)->status);

        // The closed competitors are reported back for post-commit notification.
        $closedIds = collect($result['closed_competing_offers'])->pluck('id')->all();
        $this->assertContains($competitor1->id, $closedIds);
        $this->assertContains($competitor2->id, $closedIds);
        $this->assertCount(2, $closedIds);
    }

    // ── Auto-closed competitors are recorded with an audit reason ────────────

    public function test_competing_close_writes_audit_row_with_reason(): void
    {
        $auction = $this->auction();
        $accepted   = $this->rootOffer($auction);
        $competitor = $this->rootOffer($auction);

        $this->decision()->accept($accepted, null, 'system');

        $log = \App\Models\OfferEventLog::where('offer_id', $competitor->id)
            ->where('event_type', 'offer_rejected')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('competing_offer_auto_closed', $log->metadata['reason'] ?? null);
        $this->assertSame($accepted->id, $log->metadata['accepted_offer_id'] ?? null);
    }

    // ── Offers on another listing/auction are untouched ──────────────────────

    public function test_offers_on_another_auction_are_not_closed(): void
    {
        $auctionA = $this->auction();
        $auctionB = $this->auction();

        $accepted = $this->rootOffer($auctionA, 'submitted');
        $other    = $this->rootOffer($auctionB, 'submitted');

        $this->decision()->accept($accepted, null, 'system');

        $this->assertSame('submitted', Offer::find($other->id)->status, 'Offer on a different auction must remain unchanged.');
    }

    // ── Two competing offers cannot both become accepted ─────────────────────

    public function test_two_competing_offers_cannot_both_be_accepted(): void
    {
        $auction = $this->auction();
        $offerA = $this->rootOffer($auction, 'submitted');
        $offerB = $this->rootOffer($auction, 'submitted');

        $this->decision()->accept($offerA, null, 'system'); // closes B → rejected

        // Attempting to accept B now must fail — B is final and A is already accepted.
        $result = $this->decision()->accept($offerB, null, 'system');

        $this->assertFalse($result['allowed']);
        $this->assertSame('accepted', Offer::find($offerA->id)->status);
        $this->assertSame('rejected', Offer::find($offerB->id)->status);
        $this->assertNotSame('accepted', Offer::find($offerB->id)->status);
    }

    // ── Repeat acceptance of the same offer is safely rejected (idempotent) ──

    public function test_repeat_acceptance_is_safely_rejected(): void
    {
        $auction = $this->auction();
        $offer = $this->rootOffer($auction, 'submitted');

        $first  = $this->decision()->accept($offer, null, 'system');
        $second = $this->decision()->accept($offer, null, 'system');

        $this->assertTrue($first['allowed']);
        $this->assertFalse($second['allowed']);
        $this->assertSame('accepted', Offer::find($offer->id)->status);

        // Exactly one accepted_terms_snapshot exists — no duplication on repeat.
        $this->assertSame(
            1,
            \App\Models\OfferMeta::where('offer_id', $offer->id)
                ->where('meta_key', 'accepted_terms_snapshot')
                ->count()
        );
    }

    // ── Accepting the active leaf preserves its own counteroffer chain ───────

    public function test_accepting_leaf_preserves_own_chain_and_closes_competitor(): void
    {
        $auction = $this->auction();

        // Chain: A (countered root) → B (submitted active leaf)
        $rootA = $this->rootOffer($auction, 'countered');
        $leafB = Offer::factory()->create([
            'offer_auction_id' => $auction->id,
            'user_id'          => $rootA->user_id,
            'status'           => 'submitted',
            'parent_offer_id'  => $rootA->id,
        ]);

        // Separate competing chain: C (submitted root)
        $competitorC = $this->rootOffer($auction, 'submitted');

        $result = $this->decision()->accept($leafB, null, 'system');

        $this->assertTrue($result['allowed']);
        $this->assertSame('accepted', Offer::find($leafB->id)->status);
        // The accepted offer's own chain parent is preserved (NOT rejected).
        $this->assertSame('countered', Offer::find($rootA->id)->status);
        // The competing chain is closed.
        $this->assertSame('rejected', Offer::find($competitorC->id)->status);

        $closedIds = collect($result['closed_competing_offers'])->pluck('id')->all();
        $this->assertContains($competitorC->id, $closedIds);
        $this->assertNotContains($rootA->id, $closedIds);
        $this->assertNotContains($leafB->id, $closedIds);
    }

    // ── A competing offer that is already accepted blocks a second acceptance ─

    public function test_pre_existing_accepted_competitor_blocks_acceptance(): void
    {
        $auction = $this->auction();
        // Simulate a competitor that reached 'accepted' out-of-band.
        $alreadyAccepted = $this->rootOffer($auction, 'accepted');
        $candidate       = $this->rootOffer($auction, 'submitted');

        $result = $this->decision()->accept($candidate, null, 'system');

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsStringIgnoringCase('already been accepted', $result['reason']);
        $this->assertSame('submitted', Offer::find($candidate->id)->status);
    }
}
