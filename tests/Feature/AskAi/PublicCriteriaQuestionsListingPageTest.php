<?php

namespace Tests\Feature\AskAi;

use App\Models\BuyerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Services\Ai\OpenAiClientService;
use App\Services\AskAi\AskAiIntentNormalizerService;
use App\Services\AskAi\AskAiKnowledgeSearchService;
use App\Services\AskAi\AskAiOpenAiAdapterService;
use App\Services\AskAi\AskAiQuestionClassifierService;
use App\Services\AskAi\AskAiRunnerV2Service;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Batch 2d on the real public Buyer and Tenant listing pages, through the real controllers,
 * context builder and templates.
 *
 * THREE THINGS ARE UNDER TEST AT ONCE, and each is worthless without the others:
 *   1. the criteria questions render, with exact answers;
 *   2. the qualification data sitting beside them in the same context does not;
 *   3. rendering and revealing an answer reach no model, no classifier and no network.
 *
 * (3) is a property of the mechanism rather than a promise: the answers are already in the
 * markup and the reveal is a native <details> toggle, so there is nothing to send. The card
 * partial carries no <script>, no form, no input and no link, and that is asserted here
 * rather than trusted, because a later edit adding one would move the answer off the page
 * and onto a request.
 */
class PublicCriteriaQuestionsListingPageTest extends TestCase
{
    use DatabaseTransactions;

    private function buyer(array $meta, ?User $owner = null): BuyerAgentAuction
    {
        $owner ??= User::factory()->create();
        $listing = BuyerAgentAuction::create([
            'user_id' => $owner->id, 'title' => 'Buyer criteria', 'is_approved' => true, 'is_draft' => false, 'is_sold' => false,
        ]);
        $listing->saveMeta('workflow_type', 'offer_listing');
        foreach ($meta as $k => $v) {
            $listing->saveMeta($k, $v);
        }

        return $listing->fresh();
    }

    private function tenant(array $meta, ?User $owner = null): TenantAgentAuction
    {
        $owner ??= User::factory()->create();
        $listing = TenantAgentAuction::factory()->active()->create(['user_id' => $owner->id]);
        $listing->saveMeta('workflow_type', 'offer_listing');
        foreach ($meta as $k => $v) {
            $listing->saveMeta($k, $v);
        }

        return $listing->fresh();
    }

    /** @return array<string,string> question id => answer, read from the Ask AI card only */
    private function cardAnswers(string $html, string $role): array
    {
        $start = strpos($html, 'data-ask-ai-property-questions="' . $role . '"');
        if ($start === false) {
            return [];
        }
        $card = substr($html, $start);

        preg_match_all(
            '#<details[^>]*data-property-question="([a-z_]+)"[^>]*>\s*<summary[^>]*>.*?</summary>\s*<p[^>]*data-property-answer="\1"[^>]*>(.*?)</p>#s',
            $card, $m, PREG_SET_ORDER
        );
        $out = [];
        foreach ($m as [, $id, $answer]) {
            $out[$id] = html_entity_decode(trim($answer), ENT_QUOTES);
        }

        return $out;
    }

    private function buyerPage(array $meta, ?User $as = null, ?User $owner = null): string
    {
        $listing = $this->buyer($meta, $owner);
        $request = $as ? $this->actingAs($as) : $this;

        return $request->get(route('offer.listing.buyer.view', $listing->id))->assertOk()->getContent();
    }

    private function tenantPage(array $meta, ?User $as = null, ?User $owner = null): string
    {
        $listing = $this->tenant($meta, $owner);
        $request = $as ? $this->actingAs($as) : $this;

        return $request->get(route('offer.listing.tenant.view', $listing->id))->assertOk()->getContent();
    }

    /** A buyer listing with every public criterion AND every private qualification field set. */
    private function fullBuyerMeta(): array
    {
        return [
            // public criteria
            'maximum_budget'           => '450000',
            'cities'                   => json_encode(['Seminole', 'St. Petersburg']),
            'counties'                 => json_encode(['Pinellas']),
            'property_type'            => 'Residential',
            'bedrooms'                 => '3',
            'bathrooms'                => '2.5',
            'minimum_heated_square'    => '1800',
            'total_acreage'            => '1/4 to less than 1/2 acre',
            'pool_needed'              => 'Yes',
            'garage_needed'            => 'Yes',
            'target_closing_date'      => 'Within 3 Months',
            'non_negotiable_amenities' => json_encode(['Fenced Yard']),
            // private qualification — in the same context, must never be answered
            'pre_approved'                  => 'Yes',
            'pre_approval_amount'           => '525000',
            'cash_budget'                   => '120000',
            'down_payment_amount'           => '90000',
            'credit_scroe_rating'           => 'SENTINEL-BUYER-CREDIT',
            'number_occupant'               => 'SENTINEL-BUYER-OCCUPANTS',
            'home_sale_contingency_address' => 'SENTINEL-BUYER-CURRENT-HOME',
            'commute_destination_zip'       => 'SENTINEL-BUYER-COMMUTE',
            'email'                         => 'sentinel-buyer@example.test',
            'phone_number'                  => 'SENTINEL-BUYER-PHONE',
        ];
    }

    /** A tenant listing with every public criterion AND every private disclosure set. */
    private function fullTenantMeta(): array
    {
        return [
            // public criteria
            'budget'                   => '2500',
            'cities'                   => json_encode(['Seminole']),
            'counties'                 => json_encode(['Pinellas']),
            'zipCodes'                 => json_encode(['33772', '33776']),
            'property_type'            => 'Residential Property',
            'bedrooms'                 => '2',
            'bathrooms'                => '1',
            'minimum_heated_square'    => '900',
            'total_acreage'            => '0 to less than 1/4 acre',
            'desired_lease_length'     => json_encode(['12 Months']),
            'move_in_date_earliest'    => '2027-01-15',
            'move_in_date_latest'      => '2027-03-01',
            'pets'                     => 'Yes',
            'tenant_require'           => json_encode(['Furnished']),
            'non_negotiable_amenities' => json_encode(['In-unit Laundry']),
            'pool_needed'              => 'No',
            // private disclosures — in the same context, must never be answered
            'monthly_income'             => '91317',
            'minimum_annual_net_income'  => '91319',
            'credit_score_range'         => 'SENTINEL-TENANT-CREDIT',
            'prior_eviction'             => 'SENTINEL-TENANT-EVICTION',
            'prior_felony'               => 'SENTINEL-TENANT-FELONY',
            'service_animal'             => 'SENTINEL-TENANT-SERVICE-ANIMAL',
            'support_animal'             => 'SENTINEL-TENANT-SUPPORT-ANIMAL',
            'accessibility_requirements' => 'SENTINEL-TENANT-ACCESSIBILITY',
            'number_occupant'            => 'SENTINEL-TENANT-OCCUPANTS',
            'address'                    => 'SENTINEL-TENANT-PRIVATE-STREET',
            'commute_destination_zip'    => 'SENTINEL-TENANT-COMMUTE',
            'email'                      => 'sentinel-tenant@example.test',
        ];
    }

    /* ================================================================== */
    /* The card renders, with the right heading                            */
    /* ================================================================== */

    public function test_the_buyer_card_renders_the_criteria_questions_for_a_shopper(): void
    {
        $html    = $this->buyerPage($this->fullBuyerMeta());
        $answers = $this->cardAnswers($html, 'buyer');

        // Blade escapes the apostrophe, so the heading is in the markup as &#039;.
        $this->assertStringContainsString('Questions About This Buyer&#039;s Criteria', $html);
        $this->assertStringNotContainsString('Questions About This Property', $html);

        $this->assertSame('The buyer is looking for a purchase price up to $450,000.', $answers['buyer_budget'] ?? null);
        $this->assertSame('The buyer is looking in Seminole and St. Petersburg, and in Pinellas County.', $answers['buyer_search_areas'] ?? null);
        $this->assertSame('The buyer is looking for this property type: Residential.', $answers['buyer_property_type'] ?? null);
        $this->assertSame('The buyer is looking for at least 3 bedrooms.', $answers['buyer_bedrooms'] ?? null);
        $this->assertSame('The buyer is looking for at least 2.5 bathrooms.', $answers['buyer_bathrooms'] ?? null);
        $this->assertSame('The buyer is looking for at least 1,800 heated square feet.', $answers['buyer_square_feet'] ?? null);
        $this->assertSame('The buyer is looking for a lot of 1/4 to less than 1/2 acre.', $answers['buyer_acreage'] ?? null);
        $this->assertSame('The buyer is looking for a property with a pool.', $answers['buyer_pool'] ?? null);
        $this->assertSame('The buyer is looking for a property with a garage.', $answers['buyer_garage'] ?? null);
        $this->assertSame('The buyer is looking to close within 3 months.', $answers['buyer_timeframe'] ?? null);
        $this->assertSame('The buyer is looking for these features: Fenced Yard.', $answers['buyer_property_features'] ?? null);
    }

    public function test_the_tenant_card_renders_the_criteria_questions_for_a_shopper(): void
    {
        $html    = $this->tenantPage($this->fullTenantMeta());
        $answers = $this->cardAnswers($html, 'tenant');

        $this->assertStringContainsString('Questions About This Tenant&#039;s Criteria', $html);
        $this->assertStringNotContainsString('Questions About This Property', $html);

        $this->assertSame('The tenant is looking for rent up to $2,500.', $answers['tenant_max_rent'] ?? null);
        $this->assertSame('The tenant is looking in Seminole, in Pinellas County, and in ZIP codes 33772 and 33776.', $answers['tenant_search_areas'] ?? null);
        $this->assertSame('The tenant is looking for this property type: Residential Property.', $answers['tenant_property_type'] ?? null);
        $this->assertSame('The tenant is looking for at least 2 bedrooms.', $answers['tenant_bedrooms'] ?? null);
        $this->assertSame('The tenant is looking for at least 1 bathroom.', $answers['tenant_bathrooms'] ?? null);
        $this->assertSame('The tenant is looking for at least 900 heated square feet.', $answers['tenant_square_feet'] ?? null);
        $this->assertSame('The tenant is looking for a lot of 0 to less than 1/4 acre.', $answers['tenant_acreage'] ?? null);
        $this->assertSame('The tenant is looking for a lease term of 12 Months.', $answers['tenant_lease_term'] ?? null);
        $this->assertSame('The tenant is looking to move in between January 15, 2027 and March 1, 2027.', $answers['tenant_move_in'] ?? null);
        $this->assertSame('The tenant is looking for a property that allows pets.', $answers['tenant_pets'] ?? null);
        $this->assertSame('The tenant is looking for a furnished property.', $answers['tenant_furnishings'] ?? null);
        $this->assertSame('The tenant is looking for these features: In-unit Laundry.', $answers['tenant_property_features'] ?? null);
        $this->assertSame('The tenant has not listed a pool as a requirement.', $answers['tenant_pool'] ?? null);
    }

    /* ================================================================== */
    /* Privacy — the qualification data beside the criteria                */
    /* ================================================================== */

    public function test_no_buyer_answer_carries_private_qualification_data(): void
    {
        $answers = implode(' ', $this->cardAnswers($this->buyerPage($this->fullBuyerMeta()), 'buyer'));

        foreach (['525,000', '525000', '120,000', '120000', '90,000', '90000',
                  'SENTINEL-BUYER-CREDIT', 'SENTINEL-BUYER-OCCUPANTS',
                  'SENTINEL-BUYER-CURRENT-HOME', 'SENTINEL-BUYER-COMMUTE',
                  'sentinel-buyer@example.test', 'SENTINEL-BUYER-PHONE'] as $needle) {
            $this->assertStringNotContainsString($needle, $answers, "Buyer Ask AI leaked '{$needle}'.");
        }
        foreach (['pre-approv', 'preapprov', 'down payment', 'cash', 'credit', 'occupant'] as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, $answers, "Buyer Ask AI mentions '{$word}'.");
        }
    }

    public function test_no_tenant_answer_carries_private_disclosures(): void
    {
        $answers = implode(' ', $this->cardAnswers($this->tenantPage($this->fullTenantMeta()), 'tenant'));

        foreach (['91,317', '91317', '91,319', '91319',
                  'SENTINEL-TENANT-CREDIT', 'SENTINEL-TENANT-EVICTION', 'SENTINEL-TENANT-FELONY',
                  'SENTINEL-TENANT-SERVICE-ANIMAL', 'SENTINEL-TENANT-SUPPORT-ANIMAL',
                  'SENTINEL-TENANT-ACCESSIBILITY', 'SENTINEL-TENANT-OCCUPANTS',
                  'SENTINEL-TENANT-PRIVATE-STREET', 'SENTINEL-TENANT-COMMUTE',
                  'sentinel-tenant@example.test'] as $needle) {
            $this->assertStringNotContainsString($needle, $answers, "Tenant Ask AI leaked '{$needle}'.");
        }
        foreach (['income', 'credit', 'eviction', 'felony', 'service animal',
                  'support animal', 'accessibility', 'occupant'] as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, $answers, "Tenant Ask AI mentions '{$word}'.");
        }
    }

    public function test_the_tenant_pet_question_is_not_an_assistance_animal_question(): void
    {
        // The pets answer must appear WHILE the service and support animal values exist on
        // the listing — proving they were refused, not merely absent.
        $answers = $this->cardAnswers($this->tenantPage($this->fullTenantMeta()), 'tenant');

        $this->assertSame('The tenant is looking for a property that allows pets.', $answers['tenant_pets'] ?? null);
        $this->assertStringNotContainsStringIgnoringCase('animal', $answers['tenant_pets'] ?? '');
    }

    /* ================================================================== */
    /* Owner                                                               */
    /* ================================================================== */

    public function test_the_owner_sees_the_same_criteria_questions_and_keeps_the_ask_ai_modal(): void
    {
        $owner = User::factory()->create();

        $shopper = $this->cardAnswers($this->buyerPage($this->fullBuyerMeta()), 'buyer');
        $ownerHtml = $this->buyerPage($this->fullBuyerMeta(), $owner, $owner);

        // Same deterministic questions for both — the catalog governs the context, not the
        // viewer, so an owner is never shown a different set of "verified" answers.
        $this->assertSame(array_keys($shopper), array_keys($this->cardAnswers($ownerHtml, 'buyer')));

        // The free-text modal trigger is the owner's alone; its endpoint is owner-scoped.
        $this->assertStringContainsString('data-bs-target="#bolAiModal"', $ownerHtml);
    }

    public function test_a_shopper_gets_no_free_text_ask_ai_trigger_inside_the_card(): void
    {
        $html  = $this->buyerPage($this->fullBuyerMeta());
        $start = strpos($html, 'data-ask-ai-property-questions="buyer"');
        $this->assertNotFalse($start);
        $card = substr($html, $start, strpos($html, '</div>', strrpos($html, 'ask-ai-pq-note')) - $start);

        $this->assertStringNotContainsString('bolAiModal', $card);
    }

    /* ================================================================== */
    /* Zero AI, zero network                                               */
    /* ================================================================== */

    /**
     * NOT Http::assertNothingSent(), and the difference is the point.
     *
     * Unlike the Seller and Landlord pages, a Buyer or Tenant detail page names cities, and
     * BoundaryLookupService fetches their outlines from the Census TIGERweb service to draw
     * the Location DNA map. That request predates this work, belongs to the map, and would
     * make a blanket "nothing was sent" assertion fail for a reason that has nothing to do
     * with Ask AI — and the tempting fix, dropping the cities from the fixture, would drop
     * the areas question this batch exists to add.
     *
     * So the claim is stated exactly: no AI service is invoked, and no request reaches a
     * model provider or an Ask AI endpoint. Every recorded request is named and checked
     * rather than counted.
     */
    public function test_rendering_criteria_questions_calls_no_ai_service_or_ai_endpoint(): void
    {
        Http::fake();

        $this->mock(OpenAiClientService::class)->shouldNotReceive('send');
        $this->mock(AskAiOpenAiAdapterService::class)->shouldNotReceive('generate');
        $this->mock(AskAiIntentNormalizerService::class)->shouldNotReceive('normalize');
        $this->mock(AskAiQuestionClassifierService::class)->shouldNotReceive('classify');
        $this->mock(AskAiKnowledgeSearchService::class)->shouldNotReceive('search');
        $this->mock(AskAiRunnerV2Service::class)->shouldNotReceive('run');

        $buyer  = $this->cardAnswers($this->buyerPage($this->fullBuyerMeta()), 'buyer');
        $tenant = $this->cardAnswers($this->tenantPage($this->fullTenantMeta()), 'tenant');

        $this->assertArrayHasKey('buyer_budget', $buyer);
        $this->assertArrayHasKey('tenant_max_rent', $tenant);

        foreach (Http::recorded() as $pair) {
            $url = $pair[0]->url();
            foreach (['openai', 'anthropic', 'ask-ai', 'agent-ai'] as $forbidden) {
                $this->assertStringNotContainsStringIgnoringCase($forbidden, $url,
                    "Rendering the criteria card sent a request to {$url}.");
            }
            // Whatever else went out belongs to the map, not to Ask AI.
            $this->assertStringContainsString('census.gov', $url,
                "Unexpected outbound request while rendering the criteria card: {$url}");
        }
    }

    public function test_revealing_an_answer_cannot_make_a_request_because_the_answer_is_already_there(): void
    {
        // The reveal is a native <details> toggle over markup already on the page. The proof
        // is structural: the card region contains the answer text AND contains no script,
        // form, input or link that could turn a toggle into a request.
        $html  = $this->tenantPage($this->fullTenantMeta());
        $start = strpos($html, 'data-ask-ai-property-questions="tenant"');
        $this->assertNotFalse($start);
        $card = substr($html, $start, strpos($html, 'ask-ai-pq-note', $start) - $start);

        $this->assertStringContainsString('The tenant is looking for rent up to $2,500.', html_entity_decode($card, ENT_QUOTES));

        foreach (['<script', '<form', '<input', '<a ', 'fetch(', 'XMLHttpRequest',
                  'ask-ai/listing-question', 'api/ask-ai/ask', 'agent-ai/'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $card, "The criteria card contains '{$forbidden}'.");
        }
    }

    /* ================================================================== */
    /* Knowledge Base stays private                                        */
    /* ================================================================== */

    public function test_a_buyer_or_tenant_knowledge_base_answer_never_reaches_the_public_page(): void
    {
        // Batch 0 made owner-authored faq_answers.* owner-only for non-owners. Batch 2d adds
        // a deterministic surface beside them and must not become a second way out.
        $buyerMeta  = $this->fullBuyerMeta();
        $tenantMeta = $this->fullTenantMeta();
        $buyerMeta['listing_ai_faq']  = json_encode(['faq_answers' => ['q1' => 'SENTINEL-BUYER-KB-ANSWER']]);
        $tenantMeta['listing_ai_faq'] = json_encode(['faq_answers' => ['q1' => 'SENTINEL-TENANT-KB-ANSWER']]);

        $this->assertStringNotContainsString('SENTINEL-BUYER-KB-ANSWER', $this->buyerPage($buyerMeta));
        $this->assertStringNotContainsString('SENTINEL-TENANT-KB-ANSWER', $this->tenantPage($tenantMeta));
    }

    /* ================================================================== */
    /* Missing values                                                      */
    /* ================================================================== */

    public function test_a_listing_with_no_public_criteria_shows_an_empty_state_not_a_broken_card(): void
    {
        // Only private values stored. The card renders, says nothing is available, and
        // names criteria rather than a property.
        $html = $this->tenantPage([
            'monthly_income'             => '91317',
            'service_animal'             => 'Yes',
            'accessibility_requirements' => 'Ground floor',
        ]);

        $this->assertSame([], $this->cardAnswers($html, 'tenant'));
        $this->assertStringContainsString('No verified criteria questions are available yet.', $html);
    }
}
