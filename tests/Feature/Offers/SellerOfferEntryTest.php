<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SellerOfferEntryTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private SellerAgentAuction $auction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['user_type' => 'buyer']);

        $this->auction = SellerAgentAuction::create([
            'user_id'     => $this->user->id,
            'title'       => 'Test Seller Listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);

        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $this->auction->id,
            'meta_key'                => 'workflow_type',
            'meta_value'              => 'offer_listing',
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

    // ── Test 1: View does not contain solOfferModal ───────────────────────────

    public function test_seller_listing_view_does_not_contain_solOfferModal(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('offer.listing.seller.view', $this->auction->id));

        $response->assertStatus(200);
        $response->assertDontSee('solOfferModal', false);
    }

    // ── Test 2: View contains the offers.store form action ───────────────────

    public function test_seller_listing_view_contains_offers_store_action(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('offer.listing.seller.view', $this->auction->id));

        $response->assertStatus(200);
        $response->assertSee(route('offers.store'), false);
    }

    // ── Test 3: Old offer modal "coming soon" message is absent ──────────────

    public function test_seller_listing_view_does_not_contain_offer_modal_coming_soon(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('offer.listing.seller.view', $this->auction->id));

        $response->assertStatus(200);

        $body = $response->getContent();

        $this->assertStringNotContainsStringIgnoringCase(
            'Secure online offer submission is coming soon',
            $body,
            'The old offer-modal "coming soon" message must not appear on the Seller listing view page.'
        );

        $this->assertStringNotContainsStringIgnoringCase(
            'Online Offer Submission',
            $body,
            'The old offer-modal heading "Online Offer Submission" must not appear on the Seller listing view page.'
        );
    }

    // ── Test 3b: The rendered form's offer_auction_id belongs to offer_auctions ──

    public function test_seller_listing_view_offer_auction_id_exists_in_offer_auctions_table(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('offer.listing.seller.view', $this->auction->id));

        $response->assertStatus(200);

        $body = $response->getContent();

        // Extract all offer_auction_id hidden input values from the page
        preg_match_all(
            '/<input[^>]+name="offer_auction_id"[^>]+value="(\d+)"/i',
            $body,
            $matches
        );

        $this->assertNotEmpty($matches[1], 'At least one offer_auction_id hidden input must be present.');

        foreach ($matches[1] as $extractedId) {
            $this->assertTrue(
                OfferAuction::where('id', (int) $extractedId)->exists(),
                "offer_auction_id value {$extractedId} found in the form must exist in offer_auctions table, not just seller_agent_auctions."
            );
        }
    }

    // ── Test 4: POST to offers.store using resolved offer_auction_id redirects to offers.show ──

    public function test_post_to_offers_store_redirects_to_offers_show(): void
    {
        // Load the view to trigger the find-or-create of the linked OfferAuction
        $viewResponse = $this->actingAsAllowedUser()
            ->get(route('offer.listing.seller.view', $this->auction->id));

        $viewResponse->assertStatus(200);

        // Extract the offer_auction_id the view actually rendered
        preg_match(
            '/<input[^>]+name="offer_auction_id"[^>]+value="(\d+)"/i',
            $viewResponse->getContent(),
            $match
        );

        $this->assertNotEmpty($match, 'offer_auction_id hidden input must be present in the seller listing view.');

        $resolvedOfferAuctionId = (int) $match[1];

        // Confirm it resolves to an offer_auctions row (not a seller_agent_auctions row)
        $this->assertTrue(
            OfferAuction::where('id', $resolvedOfferAuctionId)->exists(),
            "The offer_auction_id ({$resolvedOfferAuctionId}) rendered by the view must exist in offer_auctions."
        );

        $response = $this->actingAsAllowedUser()
            ->post(route('offers.store'), [
                'offer_auction_id' => $resolvedOfferAuctionId,
                'role'             => 'seller',
            ]);

        $offer = Offer::where('offer_auction_id', $resolvedOfferAuctionId)
            ->where('role', 'seller')
            ->where('user_id', $this->user->id)
            ->latest()
            ->first();

        $this->assertNotNull($offer, 'An Offer draft should have been created.');

        $response->assertRedirect(route('offers.show', $offer));
    }
}
