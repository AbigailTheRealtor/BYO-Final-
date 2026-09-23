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
 *   C1/WF-1 — Ask AI access: the listing-question endpoint answers a non-owner of a
 *             public listing at PUBLIC scope (never the owner's), and the detail view
 *             offers every viewer the same working Ask AI modal — no control that
 *             then answers "available to the listing owner".
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

    // ── C1: a non-owner is answered, at public scope only ───────────────────

    public function test_non_owner_is_answered_at_public_scope_by_listing_question_endpoint(): void
    {
        $owner   = User::factory()->create();
        $other   = User::factory()->create();
        $listing = $this->makeSellerOfferListing($owner);

        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->expects($this->once())->method('run')
            ->with('seller', $listing->id, 'What is the asking price?', $this->callback(
                fn ($options) => ($options['viewer_scope'] ?? null) === 'public'
            ))
            ->willReturn(['success' => true, 'status' => 'ready', 'final_response' => ['answer' => 'The asking price is $500,000.']]);
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->actingAs($other)->postJson(route('ask-ai.listing-question'), [
            'listing_type' => 'seller',
            'listing_id'   => $listing->id,
            'question'     => 'What is the asking price?',
        ]);

        $response->assertOk()->assertJsonPath('answer', 'The asking price is $500,000.');
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

    // ── C1: every viewer gets the same working Ask AI modal ─────────────────

    public function test_owner_view_carries_the_ask_ai_modal_with_the_owner_examples(): void
    {
        $owner   = User::factory()->create();
        $listing = $this->makeSellerOfferListing($owner);

        $response = $this->actingAs($owner)
            ->get(route('offer.listing.seller.view', $listing->id));

        $response->assertStatus(200);
        $html = $response->getContent();
        $this->assertStringContainsString('id="solAiModal"', $html);
        $this->assertStringContainsString('/ask-ai/listing-question', $html);
        $this->assertStringContainsString(json_encode('"What financing options does the seller accept?"', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), $html);
    }

    /**
     * Batch 2a hid the modal from non-owners because its endpoint was owner-only and every
     * question they typed ended in "available to the listing owner". The endpoint now
     * authorizes per fact, so a non-owner gets the same modal — and no owner-only notice,
     * no client-side owner gate, and none of the owner's generic example questions.
     */
    public function test_non_owner_view_carries_the_public_ask_ai_modal_and_no_owner_gate(): void
    {
        $owner   = User::factory()->create();
        $other   = User::factory()->create();
        $listing = $this->makeSellerOfferListing($owner);

        $response = $this->actingAs($other)
            ->get(route('offer.listing.seller.view', $listing->id));

        $response->assertStatus(200);
        $html = $response->getContent();
        $this->assertStringContainsString('id="solAiModal"', $html);
        $this->assertStringContainsString('/ask-ai/listing-question', $html);
        $this->assertStringNotContainsString('isOwner       =', $html);
        $this->assertStringNotContainsString('Ask AI for this listing is available to the listing owner.', $html);
        $this->assertStringNotContainsString('What financing options does the seller accept?', $html);
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
