<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\User;
use App\Services\Offers\OfferPermissionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase C — C3 (BYA-H2 self-bid) for the BidYourOffer negotiation engine.
 *
 * NOTE on scope: in this engine an `OfferAuction` is the offerer's OWN offer
 * container, so creating the initial `Offer` via OfferController::store where
 * the auction owner == the offerer is the normal primary flow (every existing
 * *OfferEntryTest relies on this). Self-bid prevention therefore lives on the
 * RESPONSE side: a party may not counter / accept / reject their OWN offer.
 * This test locks in that already-enforced behaviour.
 */
class OfferSelfBidDuplicateTest extends TestCase
{
    use DatabaseTransactions;

    private OfferPermissionService $permissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->permissions = $this->app->make(OfferPermissionService::class);
    }

    public function test_offer_creator_cannot_counter_their_own_submitted_offer(): void
    {
        $creator = User::factory()->create();
        $listing = OfferAuction::factory()->create(['user_id' => $creator->id]);
        $offer   = Offer::factory()->submitted()->create([
            'user_id'          => $creator->id,
            'offer_auction_id' => $listing->id,
        ]);

        $result = $this->permissions->canCounter($offer, $creator->id, 'buyer');

        $this->assertFalse($result['allowed'],
            'A party must not be able to counter their own offer (self-bid).');
    }

    public function test_offer_creator_cannot_accept_their_own_submitted_offer(): void
    {
        $creator = User::factory()->create();
        $listing = OfferAuction::factory()->create(['user_id' => $creator->id]);
        $offer   = Offer::factory()->submitted()->create([
            'user_id'          => $creator->id,
            'offer_auction_id' => $listing->id,
        ]);

        $result = $this->permissions->canAccept($offer, $creator->id, 'buyer');

        $this->assertFalse($result['allowed'],
            'A party must not be able to accept their own offer (self-bid).');
    }

    public function test_counterparty_may_act_on_the_offer(): void
    {
        $creator      = User::factory()->create();
        $counterparty = User::factory()->create();
        $listing      = OfferAuction::factory()->create(['user_id' => $counterparty->id]);
        $offer        = Offer::factory()->submitted()->create([
            'user_id'          => $creator->id,
            'offer_auction_id' => $listing->id,
        ]);

        $result = $this->permissions->canAccept($offer, $counterparty->id, 'seller');

        $this->assertTrue($result['allowed'],
            'The listing-owner counterparty must be able to act on the offer.');
    }
}
