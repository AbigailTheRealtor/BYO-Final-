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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "Questions About This Property" on the real public Seller and Landlord listing pages,
 * rendered through the real controllers and templates.
 *
 * The rule and formatters are pinned by Tests\Unit\AskAi\PublicPropertyQuestionAvailabilityTest;
 * this file proves what a shopper's browser actually receives.
 */
class PublicPropertyQuestionsListingPageTest extends TestCase
{
    use RefreshDatabase;

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /** @param array<string,mixed> $meta */
    private function sellerListing(array $meta): SellerAgentAuction
    {
        // Ask AI resolves property type fail-closed: a listing that names none gets only
        // the questions valid for every type. Every real listing states one.
        $meta += ['property_type' => 'Residential'];
        $user    = User::factory()->create();
        $listing = SellerAgentAuction::create([
            'user_id'     => $user->id,
            'is_approved' => true,
            'is_draft'    => false,
            'address'     => '100 Test Lane',
        ]);

        $listing->saveMeta('workflow_type', 'offer_listing');
        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }
        $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $user->id])->id);

        return $listing->fresh();
    }

    /** @param array<string,mixed> $meta */
    private function landlordListing(array $meta): LandlordAgentAuction
    {
        // Ask AI resolves property type fail-closed: a listing that names none gets only
        // the questions valid for every type. Every real listing states one.
        $meta += ['property_type' => 'Residential Property'];
        $user    = User::factory()->create();
        $listing = LandlordAgentAuction::create([
            'user_id'     => $user->id,
            'is_approved' => true,
            'is_draft'    => false,
            'title'       => 'Test Rental',
        ]);

        $listing->saveMeta('workflow_type', 'offer_listing');
        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }
        $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $user->id])->id);

        return $listing->fresh();
    }

    private function completeSellerMeta(): array
    {
        return [
            'auction_type'              => 'Traditional',
            'maximum_budget'            => '500000',
            'bedrooms'                  => '3',
            'bathrooms'                 => '2.5',
            'minimum_heated_square'     => '1,850',
            'year_built'                => '1998',
            'annual_property_taxes'     => '1856',
            'tax_year'                  => '2025',
            'has_hoa'                   => 'Yes',
            'association_fee_amount'    => '250',
            'association_fee_frequency' => 'Monthly',
            'total_acreage'             => '1/4 to less than 1/2 acre',
            'appliances'                => json_encode(['Dishwasher', 'Range', 'Refrigerator']),
            'utilities'                 => json_encode(['Electricity Connected', 'Water Available']),
        ];
    }

    private function completeLandlordMeta(): array
    {
        return [
            'bedrooms'              => '2',
            'bathrooms'             => '1',
            'minimum_heated_square' => '950',
            'appliances'            => json_encode(['Washer', 'Dryer']),
            'pets'                  => 'No',
        ];
    }

    private function sellerPage(SellerAgentAuction $listing): string
    {
        return $this->get(route('offer.listing.seller.view', ['id' => $listing->id]))->assertStatus(200)->getContent();
    }

    private function landlordPage(LandlordAgentAuction $listing): string
    {
        return $this->get(route('offer.listing.landlord.view', ['id' => $listing->id]))->assertStatus(200)->getContent();
    }

    /**
     * The Ask AI region only — the card (Batch 2a: the one home of Questions About This
     * Property) plus the selection modal — so values printed elsewhere on the page cannot
     * satisfy an assertion. Since the selection-based design (2026-09-25) the card shows the
     * FEATURED subset and the modal lists every answerable question with its answer, so both
     * halves are the public surface, and every leak assertion runs against both.
     */
    private function section(string $html, string $role): string
    {
        return $this->card($html, $role) . $this->modal($html, $role);
    }

    /** The card alone: featured questions as native <details>, ending at the next card. */
    private function card(string $html, string $role): string
    {
        $start = strpos($html, 'data-ask-ai-property-questions="' . $role . '"');
        if ($start === false) {
            return '';
        }
        $prefix = $role === 'landlord' ? 'lol' : 'sol';
        $next   = strpos($html, 'class="' . $prefix . '-interaction-card"', $start);

        return substr($html, $start, $next === false ? null : $next - $start);
    }

    /** The modal body, from the picker root to its disclaimer. */
    private function modal(string $html, string $role): string
    {
        $start = strpos($html, 'data-ask-ai-picker="' . $role . '"');
        if ($start === false) {
            return '';
        }
        $end = strpos($html, 'ask-ai-picker-disclaimer', $start);

        return substr($html, $start, $end === false ? null : $end - $start);
    }

    /**
     * EVERY question the surface offers, from the modal's "View all" list, with the answer
     * selecting it shows. The card carries only the featured subset, so completeness is
     * asserted here.
     *
     * @return array<string,array{question:string,answer:string}>
     */
    private function renderedQuestions(string $section): array
    {
        preg_match_all('#<button[^>]*data-ask-ai-pick="([a-z_0-9]+)"[^>]*data-ask-ai-search-terms="[^"]*"[^>]*>(.*?)</button>#s', $section, $q, PREG_SET_ORDER);
        preg_match_all('#<p[^>]*data-ask-ai-answer-for="([a-z_0-9]+)"[^>]*>(.*?)</p>#s', $section, $a, PREG_SET_ORDER);

        $answers = [];
        foreach ($a as [, $id, $answer]) {
            $answers[$id] = html_entity_decode(trim($answer), ENT_QUOTES);
        }

        $out = [];
        foreach ($q as [, $id, $question]) {
            $out[$id] = [
                'question' => html_entity_decode(trim($question), ENT_QUOTES),
                'answer'   => $answers[$id] ?? '',
            ];
        }

        return $out;
    }

    // ── 11. Seller listing renders only available questions ─────────────────

    public function test_seller_listing_renders_every_available_question_with_its_answer(): void
    {
        $section = $this->section($this->sellerPage($this->sellerListing($this->completeSellerMeta())), 'seller');

        $this->assertStringContainsString('Questions About This Property', $section);
        $this->assertSame([
            'seller_asking_price'       => ['question' => 'What is the asking price?',          'answer' => 'The asking price is $500,000.'],
            'seller_bedrooms'           => ['question' => 'How many bedrooms are there?',       'answer' => 'This property has 3 bedrooms.'],
            'seller_bathrooms'          => ['question' => 'How many bathrooms are there?',      'answer' => 'This property has 2.5 bathrooms.'],
            'seller_heated_square_feet' => ['question' => 'What is the heated square footage?', 'answer' => 'The heated square footage is 1,850 square feet.'],
            'seller_year_built'         => ['question' => 'What year was the property built?',  'answer' => 'This property was built in 1998.'],
            'seller_property_taxes'     => ['question' => 'What are the property taxes?',       'answer' => 'Annual property taxes are $1,856 for tax year 2025.'],
            'seller_hoa_fee'            => ['question' => 'What are the HOA fees?',             'answer' => 'The HOA fee is $250 per month.'],
            'seller_total_acreage'      => ['question' => 'What is the lot size / acreage?',    'answer' => 'The total acreage is 1/4 to less than 1/2 acre.'],
            'seller_appliances'         => ['question' => 'What appliances are included?',      'answer' => 'Appliances listed for this property: Dishwasher, Range, Refrigerator.'],
            'seller_utilities'          => ['question' => 'What utilities are listed for this property?', 'answer' => 'Utilities listed for this property: Electricity Connected, Water Available.'],
            'seller_association_details' => ['question' => 'Is there an association, and does it have to approve a buyer?', 'answer' => 'This property is in a homeowners association.'],
        ], $this->renderedQuestions($section));
    }

    public function test_seller_listing_omits_questions_whose_value_is_missing_or_blank(): void
    {
        $meta = $this->completeSellerMeta();
        unset($meta['year_built']);                // absent
        $meta['annual_property_taxes'] = '';       // blank
        $meta['appliances']            = '[]';     // empty multiselect
        $meta['has_hoa']               = 'No';     // stale fee left behind

        $questions = $this->renderedQuestions($this->section($this->sellerPage($this->sellerListing($meta)), 'seller'));

        foreach (['seller_year_built', 'seller_property_taxes', 'seller_appliances', 'seller_hoa_fee'] as $hidden) {
            $this->assertArrayNotHasKey($hidden, $questions, "{$hidden} must not render.");
        }
        $this->assertArrayHasKey('seller_bedrooms', $questions);
    }

    public function test_seller_listing_with_no_answerable_facts_renders_an_honest_empty_state(): void
    {
        $html    = $this->sellerPage($this->sellerListing(['auction_type' => 'Traditional']));
        $section = $this->section($html, 'seller');

        // The Ask AI card is still there, says plainly that nothing is verified yet, and
        // offers no invented question in its place.
        $this->assertStringContainsString('No verified property questions are available yet.', $section);
        $this->assertStringNotContainsString('data-property-question=', $html);
        $this->assertStringNotContainsString('Questions About This Property', $html);
    }

    public function test_seller_bidding_listing_does_not_publish_an_asking_price_question(): void
    {
        $meta = $this->completeSellerMeta();
        $meta['auction_type'] = 'Bidding Period';

        $questions = $this->renderedQuestions($this->section($this->sellerPage($this->sellerListing($meta)), 'seller'));

        $this->assertArrayNotHasKey('seller_asking_price', $questions);
        $this->assertArrayHasKey('seller_bedrooms', $questions);
    }

    // ── 12. Landlord listing renders only available questions ───────────────

    public function test_landlord_listing_renders_every_available_question_with_its_answer(): void
    {
        $section = $this->section($this->landlordPage($this->landlordListing($this->completeLandlordMeta())), 'landlord');

        $this->assertStringContainsString('Questions About This Property', $section);
        $this->assertSame([
            'landlord_bedrooms'           => ['question' => 'How many bedrooms are there?',       'answer' => 'This property has 2 bedrooms.'],
            'landlord_bathrooms'          => ['question' => 'How many bathrooms are there?',      'answer' => 'This property has 1 bathroom.'],
            'landlord_heated_square_feet' => ['question' => 'What is the heated square footage?', 'answer' => 'The heated square footage is 950 square feet.'],
            'landlord_appliances'         => ['question' => 'What appliances are included?',      'answer' => 'Appliances listed for this property: Washer, Dryer.'],
            'landlord_pets_allowed'       => ['question' => 'Are pets allowed?',                  'answer' => "Pets are not allowed under the property's pet policy. Assistance animals are handled separately under applicable law."],
        ], $this->renderedQuestions($section));
    }

    public function test_landlord_listing_omits_missing_values_and_withheld_terms(): void
    {
        $meta = $this->completeLandlordMeta();
        $meta['bathrooms']               = '';
        unset($meta['pets']);
        // Present on the listing. The deposit is restricted and utilities is a two-meaning
        // cascade — neither may publish. The rent AMOUNT is answerable and states no period;
        // the frequency is its OWN public fact since the universal coverage audit (2026-09-24),
        // because the landlord page prints "Lease Amount Frequency" beside the rent.
        $meta['desired_rental_amount']   = '2450';
        $meta['lease_amount_frequency']  = 'Monthly';
        $meta['security_deposit_amount'] = '3175';
        $meta['utilities']               = 'Included in Rent';

        $section   = $this->section($this->landlordPage($this->landlordListing($meta)), 'landlord');
        $questions = $this->renderedQuestions($section);

        $this->assertArrayNotHasKey('landlord_bathrooms', $questions);
        $this->assertArrayNotHasKey('landlord_pets_allowed', $questions);
        foreach (['landlord_bedrooms', 'landlord_heated_square_feet', 'landlord_appliances', 'landlord_rent', 'landlord_field_lease_amount_frequency'] as $id) {
            $this->assertArrayHasKey($id, $questions, $id);
        }
        $this->assertSame('The desired lease price is $2,450.', $questions['landlord_rent']['answer']);
        $this->assertSame('Lease Amount Frequency: Monthly.', $questions['landlord_field_lease_amount_frequency']['answer']);

        foreach (['3,175', '3175', 'Included in Rent', 'deposit'] as $withheld) {
            $this->assertStringNotContainsStringIgnoringCase($withheld, $section, "'{$withheld}' must not appear in the section.");
        }

        // The period is stated by its own question only — never folded into the rent answer.
        $this->assertStringNotContainsStringIgnoringCase('month', $questions['landlord_rent']['answer']);
    }

    // ── 9. Revealing a question returns its answer ──────────────────────────

    public function test_revealing_a_question_shows_its_precomputed_answer(): void
    {
        $section = $this->card($this->sellerPage($this->sellerListing($this->completeSellerMeta())), 'seller');

        // Native disclosure: the answer is inside the same <details> as its question,
        // closed by default, so opening it needs no request and no script.
        $this->assertMatchesRegularExpression(
            '#<details(?![^>]*\bopen\b)[^>]*data-property-question="seller_property_taxes"[^>]*>\s*'
            . '<summary[^>]*>What are the property taxes\?</summary>\s*'
            . '<p[^>]*data-property-answer="seller_property_taxes"[^>]*>Annual property taxes are \$1,856 for tax year 2025\.</p>\s*'
            . '</details>#',
            $section
        );
        $this->assertStringNotContainsString('<script', $section);
        $this->assertStringNotContainsString('<form', $section);
    }

    // ── 4 + 6. Owner-only sources and seller minimums never reach the page ──

    public function test_seller_minimums_and_owner_only_values_never_reach_the_section(): void
    {
        $meta = $this->completeSellerMeta() + [
            'minimum_cap_rate'          => '7.25',
            'minimum_annual_net_income' => '987654',
            'offered_financing'         => json_encode(['Seller Financing']),
            'sale_provision'            => json_encode(['Short Sale']),
            'flood_zone_code'           => 'AE',
            'seller_financing_interest_rate' => '6.125',
        ];

        // The owner looks at their own listing too; the public card is the same card.
        $listing = $this->sellerListing($meta);

        foreach ([null, $listing->user_id] as $viewer) {
            if ($viewer !== null) {
                $this->actingAs(User::find($viewer));
            }
            $section = $this->section($this->sellerPage($listing), 'seller');

            $this->assertNotSame('', $section);
            // Batch 2b: the offered financing TYPE is now published deliberately
            // ("Seller Financing" may appear); its TERMS — here the interest rate — never are.
            //
            // Batch 2e: 'flood' left this list. The FEMA designation is a public
            // seller/landlord fact by owner decision and is answered below; what must never
            // appear is a risk or insurance CONCLUSION drawn from it, which is asserted
            // straight after rather than by banning the word.
            // 'Short Sale' left this list in the universal coverage audit (2026-09-24): the special
            // sale provision is printed on the seller page and is stated under its own label.
            $this->assertStringContainsString('Special Sale Provision: Short Sale', html_entity_decode($section, ENT_QUOTES));
            foreach (['7.25', '987,654', '987654', 'cap rate', 'net income', '6.125', 'interest'] as $leak) {
                $this->assertStringNotContainsStringIgnoringCase($leak, $section, "'{$leak}' must never appear in the public questions.");
            }
            $this->assertStringContainsString('The seller has indicated they will consider the following financing type: Seller Financing.', $section);

            $this->assertStringContainsString('This property is in FEMA Flood Zone AE, which is within a Special Flood Hazard Area.', $section);
            foreach ([
                'not in a flood zone', 'no flood risk', 'cannot flood',
                'flood insurance is not required', 'insurance is not required', 'safe from flooding',
            ] as $forbidden) {
                $this->assertStringNotContainsStringIgnoringCase($forbidden, $section, "'{$forbidden}' must never be said.");
            }
        }
    }

    // ── 7. Buyer and tenant pages carry the CRITERIA surface, never this one ─

    /**
     * SUPERSEDED AND REPLACED, deliberately.
     *
     * This assertion used to be "the buyer and tenant pages do not reference
     * AskAiPublicPropertyQuestionService at all". Batch 2d gives those two pages their own
     * deterministic card, so that form of the test is now false by design — but the thing it
     * was protecting is not, and deleting it outright would have dropped the guarantee along
     * with the stale wording.
     *
     * What still must hold is sharper than a file-level absence: a criteria page may render
     * the shared card, and must never render a PROPERTY question through it. Seller and
     * landlord catalog entries are unreachable for those roles, and the property heading
     * never appears on their pages.
     */
    public function test_buyer_and_tenant_pages_never_render_a_property_question(): void
    {
        $registry = \App\Services\AskAi\AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry();
        $service  = new \App\Services\AskAi\AskAiPublicPropertyQuestionService();

        // A property role's own catalog entry, evaluated for a criteria role, is refused —
        // by role membership first, and by the criteria allowlist behind it.
        foreach (['seller_asking_price', 'seller_bedrooms', 'landlord_pets_allowed'] as $id) {
            if (!isset($registry[$id])) {
                continue;
            }
            foreach (['buyer', 'tenant'] as $role) {
                $result = $service->evaluate($registry[$id], $role, ['listing' => ['property_type' => 'Residential', 'asking_price' => '500000', 'bedrooms' => '3', 'pets_allowed' => 'Yes']], []);
                $this->assertFalse($result['available'], "{$id} became available for {$role}.");
            }
        }

        // And no criteria question is ever worded as a fact about a property.
        foreach ($registry as $id => $entry) {
            if (!in_array($entry['role'] ?? '', ['buyer', 'tenant'], true)) {
                continue;
            }
            $this->assertStringNotContainsStringIgnoringCase('this property has', (string) ($entry['question'] ?? ''), $id);
        }
    }

    // ── 10. Rendering the surface makes zero language-model calls ───────────

    public function test_rendering_the_surface_makes_zero_language_model_calls(): void
    {
        Http::fake();

        // Any call on these fails the request outright.
        $this->mock(OpenAiClientService::class)->shouldNotReceive('send');
        $this->mock(AskAiOpenAiAdapterService::class)->shouldNotReceive('generate');
        $this->mock(AskAiQuestionClassifierService::class)->shouldNotReceive('classify');
        $this->mock(AskAiIntentNormalizerService::class)->shouldNotReceive('normalize');
        $this->mock(AskAiKnowledgeSearchService::class)->shouldNotReceive('search');

        $seller = $this->renderedQuestions($this->section($this->sellerPage($this->sellerListing($this->completeSellerMeta())), 'seller'));
        $landlord = $this->renderedQuestions($this->section($this->landlordPage($this->landlordListing($this->completeLandlordMeta())), 'landlord'));

        $this->assertCount(11, $seller);
        $this->assertCount(5, $landlord);

        Http::assertNothingSent();
    }
}
