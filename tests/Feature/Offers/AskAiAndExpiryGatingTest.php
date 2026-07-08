<?php

namespace Tests\Feature\Offers;

use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\User;
use App\Services\AskAi\AskAiRunnerV2Service;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase C verification:
 *   C1/WF-1 — Ask AI 403: the owner-only listing-question endpoint still blocks
 *             non-owners, and the offer-listing detail view exposes the V1 Ask AI
 *             path only to the owner (isOwner flag), so non-owners are no longer
 *             routed to a control that 403s.
 *   C2/BYA-H6 — the listing 'Expired' status signal (derived from expiration_date)
 *             that the new bid-submit guards rely on is correct.
 */
class AskAiAndExpiryGatingTest extends TestCase
{
    use DatabaseTransactions;

    private function makeSellerOfferListing(User $owner): SellerAgentAuction
    {
        $auction = SellerAgentAuction::create([
            'user_id'     => $owner->id,
            'is_approved' => true,
            'is_draft'    => false,
            'address'     => '1 Test Lane',
        ]);

        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'workflow_type',
            'meta_value'              => 'offer_listing',
        ]);

        return $auction;
    }

    // ── C1: endpoint authorization unchanged ────────────────────────────────

    public function test_non_owner_is_forbidden_from_listing_question_endpoint(): void
    {
        $owner   = User::factory()->create();
        $other   = User::factory()->create();
        $listing = $this->makeSellerOfferListing($owner);

        $response = $this->actingAs($other)->postJson(route('ask-ai.listing-question'), [
            'listing_type' => 'seller',
            'listing_id'   => $listing->id,
            'question'     => 'What is the asking price?',
        ]);

        $response->assertStatus(403);
    }

    // ── C1: the owner IS served an answer (not a swallowed soft-failure) ────

    public function test_owner_receives_answer_from_listing_question_endpoint(): void
    {
        $owner   = User::factory()->create();
        $listing = $this->makeSellerOfferListing($owner);

        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn([
            'success'        => true,
            'status'         => 'ready',
            'final_response' => [
                'success'             => true,
                'status'              => 'ready',
                'answer'              => 'The asking price is $500,000.',
                'refusal_message'     => null,
                'disclosures'         => [],
                'source_attribution'  => null,
                'follow_up_questions' => [],
            ],
        ]);
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->actingAs($owner)->postJson(route('ask-ai.listing-question'), [
            'listing_type' => 'seller',
            'listing_id'   => $listing->id,
            'question'     => 'What is the asking price?',
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'status'  => 'ready',
            'answer'  => 'The asking price is $500,000.',
        ]);
    }

    // ── C1: view exposes the owner-only Ask AI path only to the owner ───────

    public function test_owner_view_marks_isOwner_true(): void
    {
        $owner   = User::factory()->create();
        $listing = $this->makeSellerOfferListing($owner);

        $response = $this->actingAs($owner)
            ->get(route('offer.listing.seller.view', $listing->id));

        $response->assertStatus(200);
        $this->assertStringContainsString('isOwner       = true', $response->getContent());
    }

    public function test_non_owner_view_marks_isOwner_false(): void
    {
        $owner   = User::factory()->create();
        $other   = User::factory()->create();
        $listing = $this->makeSellerOfferListing($owner);

        $response = $this->actingAs($other)
            ->get(route('offer.listing.seller.view', $listing->id));

        $response->assertStatus(200);
        $this->assertStringContainsString('isOwner       = false', $response->getContent());
    }

    // ── C2: 'Expired' lifecycle signal used by the bid-submit guards ────────

    public function test_listing_status_is_expired_when_expiration_date_has_passed(): void
    {
        $owner   = User::factory()->create();
        $listing = $this->makeSellerOfferListing($owner);
        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $listing->id,
            'meta_key'                => 'expiration_date',
            'meta_value'              => now()->subDay()->toDateTimeString(),
        ]);

        $this->assertSame('Expired', $listing->fresh()->status);
    }

    public function test_listing_status_is_not_expired_when_expiration_date_is_future(): void
    {
        $owner   = User::factory()->create();
        $listing = $this->makeSellerOfferListing($owner);
        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $listing->id,
            'meta_key'                => 'expiration_date',
            'meta_value'              => now()->addDay()->toDateTimeString(),
        ]);

        $this->assertNotSame('Expired', $listing->fresh()->status);
    }
}
