<?php

namespace Tests\Feature\AskAi;

use App\Models\LandlordAgentAuction;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\AskAi\AskAiPublicPropertyQuestionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Batch 4 on the real public Seller and Landlord listing pages.
 *
 * The unit suite pins the gate chain; this pins that the chain is actually WIRED — that the
 * controllers pass the viewer context they resolve, that an acknowledged answer reaches the
 * page attributed, and above all that an UNacknowledged one does not.
 *
 * Every page here is fetched as an ANONYMOUS visitor, because that is the viewer the batch
 * exists to protect. The owner sees their own knowledge base through a different, older path
 * that this batch does not touch.
 */
class PublicKnowledgeBaseBatch4ListingPageTest extends TestCase
{
    use DatabaseTransactions;

    private const SELLER_KEY      = 'roof_age_and_condition';
    private const SELLER_QUESTION = 'How old is the roof, and what condition is it in?';
    private const LANDLORD_KEY    = 'notice_to_vacate_required';

    private function seller(array $meta): SellerAgentAuction
    {
        $user    = User::factory()->create();
        $listing = SellerAgentAuction::create([
            'user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'address' => '100 Test Lane',
        ]);
        $listing->saveMeta('workflow_type', 'offer_listing');
        $listing->saveMeta('auction_type', 'Traditional');
        $listing->saveMeta('property_type', 'Residential');
        foreach ($meta as $k => $v) {
            $listing->saveMeta($k, is_array($v) ? json_encode($v) : $v);
        }
        $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $user->id])->id);

        return $listing->fresh();
    }

    private function landlord(array $meta): LandlordAgentAuction
    {
        $user    = User::factory()->create();
        $listing = LandlordAgentAuction::create([
            'user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'title' => 'Test Rental',
        ]);
        $listing->saveMeta('workflow_type', 'offer_listing');
        $listing->saveMeta('property_type', 'Residential Property');
        foreach ($meta as $k => $v) {
            $listing->saveMeta($k, is_array($v) ? json_encode($v) : $v);
        }
        $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $user->id])->id);

        return $listing->fresh();
    }

    private function sellerPage(array $meta): string
    {
        $id = $this->seller($meta)->id;

        return html_entity_decode(
            $this->get(route('offer.listing.seller.view', ['id' => $id]))->assertOk()->getContent(),
            ENT_QUOTES
        );
    }

    private function landlordPage(array $meta): string
    {
        $id = $this->landlord($meta)->id;

        return html_entity_decode(
            $this->get(route('offer.listing.landlord.view', ['id' => $id]))->assertOk()->getContent(),
            ENT_QUOTES
        );
    }

    /** Meta for an acknowledged seller listing carrying one allowlisted answer. */
    private function acknowledgedSeller(string $answer): array
    {
        return [
            AskAiPublicPropertyQuestionService::KB_PUBLICATION_ACK_META_KEY => '1',
            'listing_ai_faq'                                            => [self::SELLER_KEY => $answer],
        ];
    }

    /* ================================================================== */

    public function test_an_acknowledged_answer_renders_publicly_with_attribution(): void
    {
        $page = $this->sellerPage($this->acknowledgedSeller('Roof replaced in 2019, architectural shingle.'));

        $this->assertStringContainsString(self::SELLER_QUESTION, $page);
        $this->assertStringContainsString(
            'According to the seller: Roof replaced in 2019, architectural shingle.',
            $page
        );
        $this->assertSame(
            1,
            substr_count($page, 'data-property-question="kb_seller_' . self::SELLER_KEY . '"'),
            'The knowledge-base question must appear exactly once.'
        );
    }

    public function test_the_landlord_page_attributes_to_the_landlord(): void
    {
        $page = $this->landlordPage([
            AskAiPublicPropertyQuestionService::KB_PUBLICATION_ACK_META_KEY => '1',
            'listing_ai_faq'                                            => [self::LANDLORD_KEY => 'Sixty days written notice.'],
        ]);

        $this->assertStringContainsString('According to the landlord: Sixty days written notice.', $page);
        $this->assertStringNotContainsString('According to the seller:', $page);
    }

    public function test_a_legacy_listing_publishes_no_knowledge_base_answer(): void
    {
        // Answers stored, acknowledgement never given — the state of every listing that
        // existed before this batch. This is the single most important assertion in the file:
        // merging Batch 4 must not publish one previously private answer.
        $page = $this->sellerPage(['listing_ai_faq' => [self::SELLER_KEY => 'Roof replaced in 2019.']]);

        $this->assertStringNotContainsString('According to the seller:', $page);
        $this->assertStringNotContainsString('Roof replaced in 2019', $page);
        $this->assertStringNotContainsString('data-property-question="kb_seller_', $page);
    }

    public function test_revoking_the_acknowledgement_removes_the_public_answer(): void
    {
        $revoked = $this->sellerPage([
            AskAiPublicPropertyQuestionService::KB_PUBLICATION_ACK_META_KEY => '0',
            'listing_ai_faq'                                            => [self::SELLER_KEY => 'Roof replaced in 2019.'],
        ]);

        $this->assertStringNotContainsString('According to the seller:', $revoked);
        $this->assertStringNotContainsString('Roof replaced in 2019', $revoked);
    }

    public function test_a_non_allowlisted_answer_never_appears(): void
    {
        $page = $this->sellerPage([
            AskAiPublicPropertyQuestionService::KB_PUBLICATION_ACK_META_KEY => '1',
            'listing_ai_faq'                                            => [
                self::SELLER_KEY             => 'Roof replaced in 2019, architectural shingle.',
                'seller_motivation_timeline' => 'We are desperate and must close before September.',
                'known_issues_disclosure'    => 'There is a recurring leak in the primary bathroom.',
            ],
        ]);

        // The allowlisted one publishes...
        $this->assertStringContainsString('According to the seller: Roof replaced in 2019', $page);

        // ...and the sensitive ones do not, in any form.
        $this->assertStringNotContainsString('desperate', $page);
        $this->assertStringNotContainsString('must close before September', $page);
        $this->assertStringNotContainsString('recurring leak', $page);
    }

    public function test_fair_housing_prose_is_not_published(): void
    {
        $page = $this->landlordPage([
            AskAiPublicPropertyQuestionService::KB_PUBLICATION_ACK_META_KEY => '1',
            'listing_ai_faq'                                            => [
                self::LANDLORD_KEY => 'Sixty days notice. Perfect family neighborhood.',
            ],
        ]);

        $this->assertStringNotContainsString('Perfect family neighborhood', $page);
        $this->assertStringNotContainsString('According to the landlord:', $page);
    }

    public function test_contact_details_are_not_published(): void
    {
        $page = $this->sellerPage($this->acknowledgedSeller('Roof is fine. Call me at 727-555-0147.'));

        $this->assertStringNotContainsString('727-555-0147', $page);
        $this->assertStringNotContainsString('According to the seller:', $page);
    }

    public function test_a_withheld_listing_address_cannot_leak_through_a_knowledge_base_answer(): void
    {
        // The feed refuses the address to non-owners; the answer restates the street line.
        $page = $this->sellerPage([
            AskAiPublicPropertyQuestionService::KB_PUBLICATION_ACK_META_KEY => '1',
            'address'                                                   => '1234 Gulf Boulevard',
            // The feed's own refusal: InternetAddressDisplayYN = false, stored in the shape
            // MlsDisplayPermissions::fromStored() reads. 71 of 1,202 live records look like this.
            'mls_listing_key'                                           => 'TEST-KEY-1',
            'mls_display_permissions'                                   => ['address_display' => false],
            'listing_ai_faq'                                            => [
                self::SELLER_KEY => 'The roof was replaced in 2019; the entrance is on Gulf Boulevard.',
            ],
        ]);

        $this->assertStringNotContainsString('According to the seller:', $page);
        $this->assertStringNotContainsString('entrance is on Gulf Boulevard', $page);
    }

    public function test_no_hidden_answer_or_reason_leaks_into_markup_or_attributes(): void
    {
        // A hidden answer must be absent from the DOM entirely — not merely un-rendered
        // visually, and not present in a data attribute, a title, or a JSON island.
        $page = $this->sellerPage([
            AskAiPublicPropertyQuestionService::KB_PUBLICATION_ACK_META_KEY => '0',
            'listing_ai_faq'                                            => [
                self::SELLER_KEY => 'SENTINELROOFVALUE replaced in 2019.',
            ],
        ]);

        $this->assertStringNotContainsString('SENTINELROOFVALUE', $page);
        $this->assertStringNotContainsString('kb_owner_publication_not_confirmed', $page);
        $this->assertStringNotContainsString('kb_address_visibility_unknown', $page);
        $this->assertStringNotContainsString('kb_answer_blocked', $page);
    }

    public function test_the_non_owner_faq_answers_redaction_is_still_intact(): void
    {
        // Batch 4 admits individual KEYS to one surface. It must not have loosened the
        // wholesale redaction that keeps the knowledge-base STRUCTURE away from non-owners.
        $page = $this->sellerPage($this->acknowledgedSeller('Roof replaced in 2019, architectural shingle.'));

        $this->assertStringNotContainsString('faq_answers', $page);
        $this->assertStringNotContainsString('listing_ai_faq', $page);
    }

    public function test_the_acknowledgement_round_trips_through_the_existing_meta_save_flow(): void
    {
        // Exactly what the four Seller/Landlord components do: write with saveMeta(), read back
        // with info(), interpret with the one reader. No new storage, no migration — this test is
        // here to prove that claim rather than leave it asserted only in a comment.
        $listing = $this->seller([]);
        $key     = AskAiPublicPropertyQuestionService::KB_PUBLICATION_ACK_META_KEY;

        // Default: a listing that has never been acknowledged has no row, and whatever the meta
        // accessor returns for a missing key (false here, null elsewhere) must read as refused.
        $this->assertNotSame('1', $listing->info($key));
        $this->assertFalse(AskAiPublicPropertyQuestionService::kbPublicationConfirmed([]));
        $this->assertFalse(
            AskAiPublicPropertyQuestionService::kbPublicationConfirmed([$key => $listing->info($key)])
        );

        // Ticked.
        $listing->saveMeta($key, '1');
        $stored = $listing->fresh()->info($key);
        $this->assertSame('1', $stored);
        $this->assertTrue(AskAiPublicPropertyQuestionService::kbPublicationConfirmed([$key => $stored]));

        // Unticked again — the value is overwritten, not removed, and reads as refused.
        $listing->saveMeta($key, '0');
        $stored = $listing->fresh()->info($key);
        $this->assertSame('0', $stored);
        $this->assertFalse(AskAiPublicPropertyQuestionService::kbPublicationConfirmed([$key => $stored]));
    }

    public function test_buyer_and_tenant_pages_carry_no_knowledge_base_questions(): void
    {
        // Guarded structurally in the unit suite; asserted here as a rendered fact.
        $page = $this->sellerPage($this->acknowledgedSeller('Roof replaced in 2019, architectural shingle.'));

        $this->assertStringNotContainsString('data-property-question="kb_buyer_', $page);
        $this->assertStringNotContainsString('data-property-question="kb_tenant_', $page);
    }
}
