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
 * Batch 2b on the real Seller and Landlord listing pages: the new structured questions reach
 * the Ask AI card through the real controllers, the real AskAiContextBuilderService extraction
 * and the real templates — as stored by the listing forms (JSON multi-selects, "Other"
 * companions, Yes/No selects) — and rendering them calls no AI service.
 */
class PublicPropertyQuestionsBatch2bListingPageTest extends TestCase
{
    use RefreshDatabase;

    private function sellerListing(array $meta): SellerAgentAuction
    {
        // Ask AI resolves property type fail-closed: a listing that names none gets only
        // the questions valid for every type. Every real listing states one.
        $meta += ['property_type' => 'Residential'];
        $user    = User::factory()->create();
        $listing = SellerAgentAuction::create(['user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'address' => '100 Test Lane']);
        $listing->saveMeta('workflow_type', 'offer_listing');
        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }
        $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $user->id])->id);

        return $listing->fresh();
    }

    private function landlordListing(array $meta): LandlordAgentAuction
    {
        // Ask AI resolves property type fail-closed: a listing that names none gets only
        // the questions valid for every type. Every real listing states one.
        $meta += ['property_type' => 'Residential Property'];
        $user    = User::factory()->create();
        $listing = LandlordAgentAuction::create(['user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'title' => 'Test Rental']);
        $listing->saveMeta('workflow_type', 'offer_listing');
        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }
        $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $user->id])->id);

        return $listing->fresh();
    }

    private function sellerMeta(): array
    {
        return [
            'auction_type'                    => 'Traditional',
            'bedrooms'                        => '3',
            'has_hoa'                         => 'Yes',
            'association_fee_amount'          => '250',
            'association_fee_frequency'       => 'semi_annually',
            'pets'                            => 'Yes',
            'pool_needed'                     => 'No',
            'garage_needed'                   => 'Yes',
            'other_garage_needed'             => '2',
            'zoning'                          => 'RS-60',
            'roof_type'                       => json_encode(['Shingle', 'Other']),
            'other_roof_type'                 => 'Standing seam metal',
            'leasing_restrictions'            => 'Yes',
            'offered_financing'               => json_encode(['Conventional', 'FHA', 'VA', 'Cash']),
            // Restricted / private values that must never reach the card:
            'seller_financing_interest_rate'  => '6.125',
            'down_payment_amount'             => '40000',
            'minimum_cap_rate'                => '7.25',
            'minimum_annual_net_income'       => '987654',
            'water_view'                      => json_encode(['Lake']),
        ];
    }

    private function landlordMeta(): array
    {
        return [
            'bedrooms'                        => '2',
            'year_built'                      => '2004',
            'annual_property_taxes'           => '3120',
            'tax_year'                        => '2024',
            'has_hoa'                         => 'Yes',
            'association_fee_amount'          => '175',
            'association_fee_frequency'       => 'Other',
            'association_fee_frequency_other' => 'Twice a year',
            'zoning'                          => 'RM-15',
            'roof_type'                       => json_encode(['Tile']),
            'leasing_restrictions'            => 'No',
            'association_amenities'           => json_encode(['Clubhouse', 'Other']),
            'association_amenities_other'     => 'Dog Park',
            // The HOA minimum lease period must never become an offered lease term.
            'min_lease_period'                => '12 Months',
            'water_view'                      => json_encode(['Bay']),
        ];
    }

    private function card(string $html, string $role): string
    {
        $start = strpos($html, 'data-ask-ai-property-questions="' . $role . '"');
        $this->assertNotFalse($start);
        $prefix = $role === 'landlord' ? 'lol' : 'sol';
        $next   = strpos($html, 'class="' . $prefix . '-interaction-card"', $start);

        return substr($html, $start, $next === false ? null : $next - $start);
    }

    /** @return array<string,string> id => answer (HTML-decoded) */
    private function answers(string $card): array
    {
        preg_match_all('#<details[^>]*data-property-question="([a-z_]+)"[^>]*>\s*<summary[^>]*>.*?</summary>\s*<p[^>]*data-property-answer="\1"[^>]*>(.*?)</p>#s', $card, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as [, $id, $answer]) {
            $out[$id] = html_entity_decode(trim($answer), ENT_QUOTES);
        }

        return $out;
    }

    public function test_seller_page_card_renders_the_batch_2b_questions(): void
    {
        $html    = $this->get(route('offer.listing.seller.view', ['id' => $this->sellerListing($this->sellerMeta())->id]))->assertOk()->getContent();
        $card    = $this->card($html, 'seller');
        $answers = $this->answers($card);

        $this->assertSame('The HOA fee is $250 every six months.', $answers['seller_hoa_fee']);
        $this->assertSame('Pets are allowed at this property.', $answers['seller_pets_allowed']);
        $this->assertSame('This property does not have a pool.', $answers['seller_pool']);
        // seller_garage is now the narrower fallback of seller_parking, which answers in its
        // place — still without a count ('2 car' and 'spaces' stay on the leak list below).
        $this->assertArrayNotHasKey('seller_garage', $answers);
        $this->assertSame('This property has a garage.', $answers['seller_parking']);
        $this->assertSame('The zoning is listed as RS-60.', $answers['seller_zoning']);
        $this->assertSame('Roof types listed for this property: Shingle, Standing seam metal.', $answers['seller_roof_type']);
        $this->assertSame('The listing indicates there are leasing restrictions.', $answers['seller_leasing_restrictions']);
        $this->assertSame(
            'The seller has indicated they will consider the following financing types: Conventional, FHA, VA and Cash.',
            $answers['seller_offered_financing']
        );

        foreach (['6.125', '40,000', '40000', '7.25', '987654', '987,654', 'interest', 'down payment', 'cap rate', 'Lake', '2 car', 'spaces'] as $leak) {
            $this->assertStringNotContainsStringIgnoringCase($leak, $card, "'{$leak}' must not appear in the Ask AI card.");
        }
    }

    public function test_landlord_page_card_renders_the_batch_2b_questions(): void
    {
        $html    = $this->get(route('offer.listing.landlord.view', ['id' => $this->landlordListing($this->landlordMeta())->id]))->assertOk()->getContent();
        $card    = $this->card($html, 'landlord');
        $answers = $this->answers($card);

        $this->assertSame('This property was built in 2004.', $answers['landlord_year_built']);
        $this->assertSame('Annual property taxes are $3,120 for tax year 2024.', $answers['landlord_property_taxes']);
        $this->assertSame('The HOA fee is $175 (frequency: Twice a year).', $answers['landlord_hoa_fee']);
        $this->assertSame('The zoning is listed as RM-15.', $answers['landlord_zoning']);
        $this->assertSame('Roof type listed for this property: Tile.', $answers['landlord_roof_type']);
        $this->assertSame('The listing indicates there are no leasing restrictions.', $answers['landlord_leasing_restrictions']);
        $this->assertSame('Community amenities listed for this property: Clubhouse, Dog Park.', $answers['landlord_association_amenities']);

        // Display order: size, construction, costs, hoa, then the rest.
        $this->assertSame(
            ['landlord_bedrooms', 'landlord_year_built', 'landlord_property_taxes', 'landlord_hoa_fee', 'landlord_zoning', 'landlord_roof_type', 'landlord_leasing_restrictions', 'landlord_association_amenities', 'landlord_association_details'],
            array_keys($answers)
        );

        foreach (['12 Months', 'lease term', 'Bay'] as $leak) {
            $this->assertStringNotContainsStringIgnoringCase($leak, $card, "'{$leak}' must not appear in the Ask AI card.");
        }
    }

    public function test_rendering_batch_2b_questions_calls_no_ai_service_or_network(): void
    {
        Http::fake();

        $this->mock(OpenAiClientService::class)->shouldNotReceive('send');
        $this->mock(AskAiOpenAiAdapterService::class)->shouldNotReceive('generate');
        $this->mock(AskAiIntentNormalizerService::class)->shouldNotReceive('normalize');
        $this->mock(AskAiQuestionClassifierService::class)->shouldNotReceive('classify');
        $this->mock(AskAiKnowledgeSearchService::class)->shouldNotReceive('search');
        $this->mock(AskAiRunnerV2Service::class)->shouldNotReceive('run');

        $seller   = $this->answers($this->card($this->get(route('offer.listing.seller.view', ['id' => $this->sellerListing($this->sellerMeta())->id]))->assertOk()->getContent(), 'seller'));
        $landlord = $this->answers($this->card($this->get(route('offer.listing.landlord.view', ['id' => $this->landlordListing($this->landlordMeta())->id]))->assertOk()->getContent(), 'landlord'));

        $this->assertArrayHasKey('seller_offered_financing', $seller);
        $this->assertArrayHasKey('landlord_association_amenities', $landlord);

        Http::assertNothingSent();
    }
}
