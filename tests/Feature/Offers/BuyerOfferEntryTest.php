<?php

namespace Tests\Feature\Offers;

use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionMeta;
use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BuyerOfferEntryTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private BuyerAgentAuction $auction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['user_type' => 'seller']);

        $this->auction = BuyerAgentAuction::create([
            'user_id'     => $this->user->id,
            'title'       => 'Test Buyer Criteria Listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);

        BuyerAgentAuctionMeta::create([
            'buyer_agent_auction_id' => $this->auction->id,
            'meta_key'               => 'workflow_type',
            'meta_value'             => 'offer_listing',
        ]);
    }

    private function actingAsAllowedUser(): static
    {
        $this->app['config']->set(
            'offer.playoff_access.allowed_user_ids',
            [$this->user->id]
        );

        return $this->actingAs($this->user);
    }

    // ── Test 1: View does not contain bolRespondModal ────────────────────────

    public function test_buyer_listing_view_does_not_contain_bolRespondModal(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('offer.listing.buyer.view', $this->auction->id));

        $response->assertStatus(200);
        $response->assertDontSee('bolRespondModal', false);
    }

    // ── Test 2: Old "coming soon" text from the placeholder modal is absent ──

    public function test_buyer_listing_view_does_not_contain_offer_modal_coming_soon(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('offer.listing.buyer.view', $this->auction->id));

        $response->assertStatus(200);

        $body = $response->getContent();

        $this->assertStringNotContainsStringIgnoringCase(
            'Online response submission for Buyer Criteria listings is coming soon',
            $body,
            'The old respond-modal "coming soon" message must not appear on the Buyer listing view page.'
        );

        $this->assertStringNotContainsStringIgnoringCase(
            'Online Response Submission',
            $body,
            'The old respond-modal heading "Online Response Submission" must not appear on the Buyer listing view page.'
        );
    }

    // ── Test 3: POST to offers.store with role=buyer redirects to offers.show ─

    public function test_post_to_offers_store_with_buyer_role_redirects_to_offers_show(): void
    {
        $offerAuction = OfferAuction::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAsAllowedUser()
            ->post(route('offers.store'), [
                'offer_auction_id' => $offerAuction->id,
                'role'             => 'buyer',
            ]);

        $offer = Offer::where('offer_auction_id', $offerAuction->id)
            ->where('role', 'buyer')
            ->where('user_id', $this->user->id)
            ->latest()
            ->first();

        $this->assertNotNull($offer, 'An Offer draft with role=buyer should have been created.');

        $response->assertRedirect(route('offers.show', $offer));
    }
}
