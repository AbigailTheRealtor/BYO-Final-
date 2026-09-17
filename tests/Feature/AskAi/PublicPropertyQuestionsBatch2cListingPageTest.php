<?php

namespace Tests\Feature\AskAi;

use App\Models\LandlordAgentAuction;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\Ai\OpenAiClientService;
use App\Services\AskAi\AskAiIntentNormalizerService;
use App\Services\AskAi\AskAiKnowledgeSearchService;
use App\Services\AskAi\AskAiOpenAiAdapterService;
use App\Services\AskAi\AskAiQuestionClassifierService;
use App\Services\AskAi\AskAiRunnerV2Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Batch 2c on the real Seller and Landlord listing pages, through the real controllers,
 * AskAiContextBuilderService (including the new landlord association_fee_includes key) and
 * templates: the HOA coverage composite replaces the narrower fee question in the Ask AI card,
 * the narrower question returns when coverage is absent, the seller CDD and pet-limit answers
 * render, and rendering calls no AI service.
 */
class PublicPropertyQuestionsBatch2cListingPageTest extends TestCase
{
    use RefreshDatabase;

    private function seller(array $meta): SellerAgentAuction
    {
        $user    = User::factory()->create();
        $listing = SellerAgentAuction::create(['user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'address' => '100 Test Lane']);
        $listing->saveMeta('workflow_type', 'offer_listing');
        foreach ($meta as $k => $v) {
            $listing->saveMeta($k, $v);
        }
        $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $user->id])->id);

        return $listing->fresh();
    }

    private function landlord(array $meta): LandlordAgentAuction
    {
        $user    = User::factory()->create();
        $listing = LandlordAgentAuction::create(['user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'title' => 'Test Rental']);
        $listing->saveMeta('workflow_type', 'offer_listing');
        foreach ($meta as $k => $v) {
            $listing->saveMeta($k, $v);
        }
        $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $user->id])->id);

        return $listing->fresh();
    }

    /** @return array<string,string> id => answer, from the Ask AI card only */
    private function cardAnswers(string $html, string $role): array
    {
        $start = strpos($html, 'data-ask-ai-property-questions="' . $role . '"');
        $this->assertNotFalse($start);
        $prefix = $role === 'landlord' ? 'lol' : 'sol';
        $next   = strpos($html, 'class="' . $prefix . '-interaction-card"', $start);
        $card   = substr($html, $start, $next === false ? null : $next - $start);

        preg_match_all('#<details[^>]*data-property-question="([a-z_]+)"[^>]*>\s*<summary[^>]*>.*?</summary>\s*<p[^>]*data-property-answer="\1"[^>]*>(.*?)</p>#s', $card, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as [, $id, $answer]) {
            $out[$id] = html_entity_decode(trim($answer), ENT_QUOTES);
        }

        return $out;
    }

    private function sellerPage(array $meta): array
    {
        return $this->cardAnswers($this->get(route('offer.listing.seller.view', ['id' => $this->seller($meta)->id]))->assertOk()->getContent(), 'seller');
    }

    private function landlordPage(array $meta): array
    {
        return $this->cardAnswers($this->get(route('offer.listing.landlord.view', ['id' => $this->landlord($meta)->id]))->assertOk()->getContent(), 'landlord');
    }

    public function test_seller_card_shows_the_hoa_composite_instead_of_the_narrower_question(): void
    {
        $answers = $this->sellerPage([
            'auction_type'                  => 'Traditional',
            'has_hoa'                       => 'Yes',
            'association_fee_amount'        => '250',
            'association_fee_frequency'     => 'Monthly',
            'association_fee_includes'      => json_encode(['Water', 'Sewer', 'Trash', 'Other']),
            'association_fee_includes_other' => 'Building insurance',
        ]);

        $this->assertSame('The HOA fee is $250 per month and includes water, sewer, trash and Building insurance.', $answers['seller_hoa_fee_coverage']);
        $this->assertArrayNotHasKey('seller_hoa_fee', $answers);
    }

    public function test_seller_card_falls_back_to_the_narrower_question_without_coverage(): void
    {
        $answers = $this->sellerPage([
            'auction_type'              => 'Traditional',
            'has_hoa'                   => 'Yes',
            'association_fee_amount'    => '250',
            'association_fee_frequency' => 'Monthly',
        ]);

        $this->assertSame('The HOA fee is $250 per month.', $answers['seller_hoa_fee']);
        $this->assertArrayNotHasKey('seller_hoa_fee_coverage', $answers);
    }

    public function test_landlord_card_reads_fee_includes_through_the_new_context_key(): void
    {
        $answers = $this->landlordPage([
            'has_hoa'                   => 'Yes',
            'association_fee_amount'    => '175',
            'association_fee_frequency' => 'Quarterly',
            'association_fee_includes'  => json_encode(['Grounds Maintenance', 'Cable TV']),
        ]);

        $this->assertSame('The HOA fee is $175 per quarter and includes grounds maintenance and cable TV.', $answers['landlord_hoa_fee_coverage']);
        $this->assertArrayNotHasKey('landlord_hoa_fee', $answers);
    }

    public function test_seller_card_renders_cdd_and_pet_limits(): void
    {
        $answers = $this->sellerPage([
            'auction_type'   => 'Traditional',
            'has_cdd'        => 'Yes',
            'annual_cdd_fee' => '1,200',
            'pets'           => 'Yes',
            'number_of_pets' => '2',
            'weight_of_pets' => '50',
            'breed_of_pets'  => 'SENTINEL-BREED',
        ]);

        $this->assertSame('The annual CDD fee is $1,200.', $answers['seller_cdd_fee']);
        $this->assertSame('Pets are allowed at this property. Up to 2 pets are permitted. The maximum weight per pet is 50 lbs.', $answers['seller_pets_allowed']);
        $this->assertStringNotContainsString('SENTINEL', implode(' ', $answers));

        $noCdd = $this->sellerPage(['auction_type' => 'Traditional', 'has_cdd' => 'No']);
        $this->assertSame('There is no CDD fee listed for this property.', $noCdd['seller_cdd_fee']);
    }

    public function test_rendering_composites_calls_no_ai_service_or_network(): void
    {
        Http::fake();

        $this->mock(OpenAiClientService::class)->shouldNotReceive('send');
        $this->mock(AskAiOpenAiAdapterService::class)->shouldNotReceive('generate');
        $this->mock(AskAiIntentNormalizerService::class)->shouldNotReceive('normalize');
        $this->mock(AskAiQuestionClassifierService::class)->shouldNotReceive('classify');
        $this->mock(AskAiKnowledgeSearchService::class)->shouldNotReceive('search');
        $this->mock(AskAiRunnerV2Service::class)->shouldNotReceive('run');

        $seller = $this->sellerPage([
            'auction_type' => 'Traditional', 'has_hoa' => 'Yes', 'association_fee_amount' => '250',
            'association_fee_frequency' => 'Monthly', 'association_fee_includes' => json_encode(['Water']),
            'has_cdd' => 'Yes', 'pets' => 'Yes', 'number_of_pets' => '2',
        ]);
        $landlord = $this->landlordPage([
            'has_hoa' => 'Yes', 'association_fee_amount' => '175', 'association_fee_frequency' => 'Quarterly',
            'association_fee_includes' => json_encode(['Water']),
        ]);

        $this->assertArrayHasKey('seller_hoa_fee_coverage', $seller);
        $this->assertArrayHasKey('seller_cdd_fee', $seller);
        $this->assertArrayHasKey('landlord_hoa_fee_coverage', $landlord);

        Http::assertNothingSent();
    }
}
