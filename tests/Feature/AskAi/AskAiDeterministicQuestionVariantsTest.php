<?php

namespace Tests\Feature\AskAi;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Services\Ai\OpenAiClientService;
use App\Services\AskAi\AskAiFieldQuestionRegistryService;
use App\Services\AskAi\AskAiIntentNormalizerService;
use App\Services\AskAi\AskAiPublicPropertyQuestionService;
use App\Support\AskAi\AskAiPublicQuestionMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Ordinary wording reaches the fact the displayed question reaches — deterministically.
 *
 * "how many bathrooms", "bathroom", "baths", "how many baths", "number of bathrooms" and
 * "How many bathrooms are there?" are one question. They get there by EXACT phrases: the
 * quantity vocabulary an entry declares (AskAiFieldQuestionRegistryService::quantityAliases)
 * and the "what is/are [the] <alias>" framing of a noun-phrase alias
 * (AskAiPublicPropertyQuestionService::aliasFramings). No stemming, no similarity, no model.
 *
 * What must NOT change is asserted beside what must: wording nobody approved still refuses,
 * a framing two questions could claim reaches neither, private facts are unreachable by any
 * alias, and guest / non-owner / owner see exactly what they saw before.
 *
 * Every test runs with no model reachable: the OpenAI client fails the test if called, the
 * intent normaliser's model method must never run, and every outbound HTTP request is faked
 * and asserted absent — with the intent-normalisation flag ON, so nothing depends on it.
 */
class AskAiDeterministicQuestionVariantsTest extends TestCase
{
    use RefreshDatabase;

    private const PRIVATE_INCOME = '52,800';
    private const PRIVATE_CREDIT = '740-799';

    private User $sellerOwner;
    private User $landlordOwner;
    private User $buyerOwner;
    private User $tenantOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $client = $this->getMockBuilder(OpenAiClientService::class)->disableOriginalConstructor()->onlyMethods(['send'])->getMock();
        $client->expects($this->never())->method('send');
        $this->app->instance(OpenAiClientService::class, $client);

        $this->partialMock(AskAiIntentNormalizerService::class, function ($mock): void {
            $mock->shouldNotReceive('normalize');
        });

        config(['ask_ai.enable_openai_intent_normalization' => true]);
        Http::fake();

        $this->sellerOwner   = User::factory()->create();
        $this->landlordOwner = User::factory()->create();
        $this->buyerOwner    = User::factory()->create();
        $this->tenantOwner   = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Http::assertNothingSent();
        parent::tearDown();
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function seller(array $meta): SellerAgentAuction
    {
        $listing = SellerAgentAuction::create([
            'user_id' => $this->sellerOwner->id, 'is_approved' => true, 'is_draft' => false, 'address' => '100 Variant Lane',
        ]);
        foreach (['workflow_type' => 'offer_listing'] + $meta as $key => $value) {
            $listing->saveMeta($key, is_array($value) ? json_encode($value) : (string) $value);
        }
        $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $listing->user_id])->id);

        return $listing->fresh();
    }

    private function residentialSeller(): SellerAgentAuction
    {
        return $this->seller([
            'property_type' => 'Residential', 'maximum_budget' => '525000', 'bedrooms' => '3', 'bathrooms' => '2.5',
            'heated_square_footage' => '1850', 'year_built' => '1998',
        ]);
    }

    private function incomeSeller(): SellerAgentAuction
    {
        return $this->seller(['property_type' => 'Income', 'maximum_budget' => '780000', 'gross_annual_income' => self::PRIVATE_INCOME]);
    }

    private function residentialLandlord(): LandlordAgentAuction
    {
        $listing = LandlordAgentAuction::create(['user_id' => $this->landlordOwner->id, 'is_approved' => true, 'is_draft' => false, 'title' => '12 Variant Row']);
        foreach (['workflow_type' => 'offer_listing', 'property_type' => 'Residential Property',
                  'desired_rental_amount' => '2,450', 'bedrooms' => '2', 'bathrooms' => '1'] as $key => $value) {
            $listing->saveMeta($key, $value);
        }
        $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $listing->user_id])->id);

        return $listing->fresh();
    }

    private function buyer(): BuyerAgentAuction
    {
        $listing = BuyerAgentAuction::create(['user_id' => $this->buyerOwner->id, 'title' => 'Variant buyer', 'is_approved' => true, 'is_draft' => false, 'is_sold' => false]);
        foreach (['workflow_type' => 'offer_listing', 'property_type' => 'Residential', 'bedrooms' => '3', 'bathrooms' => '2',
                  'maximum_budget' => '450000', 'credit_scroe_rating' => self::PRIVATE_CREDIT] as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    private function tenant(): TenantAgentAuction
    {
        $listing = TenantAgentAuction::factory()->active()->create(['user_id' => $this->tenantOwner->id]);
        foreach (['workflow_type' => 'offer_listing', 'property_type' => 'Residential', 'bedrooms' => '2', 'bathrooms' => '1',
                  'budget' => '2,200', 'monthly_income' => self::PRIVATE_INCOME] as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function ask(?User $as, string $type, int $id, string $question): TestResponse
    {
        Cache::flush(); // rate limits are AskAiRateLimiterTest's subject, not this one's
        auth()->logout();
        if ($as !== null) {
            $this->actingAs($as);
        }

        return $this->postJson('/ask-ai/listing-question', ['listing_type' => $type, 'listing_id' => $id, 'question' => $question]);
    }

    /** The answer the DISPLAYED question gets — the reference every variant must equal. */
    private function reference(string $type, int $id, string $displayed): string
    {
        $response = $this->ask(null, $type, $id, $displayed);
        $response->assertOk();
        $this->assertSame('ready', $response->json('status'), (string) $response->getContent());

        return (string) $response->json('answer');
    }

    private function assertSameAnswer(string $expected, TestResponse $response, string $asked): void
    {
        $response->assertOk();
        $this->assertSame('ready', $response->json('status'), "'{$asked}' was not answered: " . $response->getContent());
        $this->assertSame($expected, (string) $response->json('answer'), "'{$asked}' reached a different answer.");
    }

    private function assertRefused(TestResponse $response, string $asked, ?string $secret = null): void
    {
        $response->assertOk();
        $this->assertNotSame('ready', $response->json('status'), "'{$asked}' should have been refused: " . $response->getContent());
        if ($secret !== null) {
            $this->assertStringNotContainsString($secret, (string) $response->getContent());
        }
    }

    // ── The reported case, end to end ───────────────────────────────────────

    public function test_how_many_bathrooms_matches_the_same_fact_as_the_displayed_question(): void
    {
        $listing  = $this->residentialSeller();
        $expected = $this->reference('seller', $listing->id, 'How many bathrooms are there?');
        $this->assertSame('This property has 2.5 bathrooms.', $expected);

        foreach ([
            'how many bathrooms', 'how many bathrooms are there', 'bathrooms', 'bathroom', 'baths', 'bath',
            'how many baths', 'number of bathrooms', 'what is the number of bathrooms',
            // capitalisation, punctuation and spacing are normalisation, not matching
            'HOW MANY BATHROOMS', 'How Many Bathrooms?', '  how many   bathrooms  ', 'how many bathrooms??', 'Bathrooms.',
        ] as $asked) {
            $this->assertSameAnswer($expected, $this->ask(null, 'seller', $listing->id, $asked), $asked);
        }
    }

    // ── Every role, every count question ────────────────────────────────────

    /**
     * @dataProvider roleVariantCases
     */
    public function test_ordinary_wording_reaches_the_displayed_questions_fact(string $role, string $displayed, array $variants): void
    {
        $listing  = $this->{$role === 'seller' ? 'residentialSeller' : ($role === 'landlord' ? 'residentialLandlord' : $role)}();
        $expected = $this->reference($role, $listing->id, $displayed);

        foreach ($variants as $asked) {
            $this->assertSameAnswer($expected, $this->ask(null, $role, $listing->id, $asked), "{$role}: {$asked}");
        }
    }

    public static function roleVariantCases(): array
    {
        $bedrooms  = ['how many bedrooms', 'bedrooms', 'bedroom', 'beds', 'bed', 'how many beds', 'number of bedrooms', 'BEDROOMS?'];
        $bathrooms = ['how many bathrooms', 'bathrooms', 'bathroom', 'baths', 'how many baths', 'number of bathrooms'];

        return [
            'seller bedrooms'    => ['seller', 'How many bedrooms are there?', array_merge($bedrooms, ['how many bedrooms are there'])],
            'seller sq ft'       => ['seller', 'What is the heated square footage?', ['square footage', 'sq ft', 'sqft', 'Sq. Ft.', 'square feet', 'how many square feet', 'what is the square footage']],
            'seller price'       => ['seller', 'What is the asking price?', ['price', 'asking price', 'what is the asking price', 'how much is the property', 'list price', 'what is the list price', 'sale price']],
            'landlord bedrooms'  => ['landlord', 'How many bedrooms are there?', $bedrooms],
            'landlord bathrooms' => ['landlord', 'How many bathrooms are there?', array_merge($bathrooms, ['how many bathrooms are there'])],
            'landlord rent'      => ['landlord', 'What is the rent?', ['rent', 'monthly rent', 'how much is the rent', 'what is the monthly rent', 'rent price']],
            'buyer bedrooms'     => ['buyer', 'How many bedrooms are they looking for?', array_merge($bedrooms, ['how many bedrooms are they looking for', 'how many bedrooms do they want'])],
            'buyer bathrooms'    => ['buyer', 'How many bathrooms are they looking for?', array_merge($bathrooms, ['how many bathrooms do they need'])],
            'buyer budget'       => ['buyer', "What is the buyer's budget?", ['budget', 'what is the budget', 'max budget']],
            'tenant bedrooms'    => ['tenant', 'How many bedrooms are they looking for?', $bedrooms],
            'tenant bathrooms'   => ['tenant', 'How many bathrooms are they looking for?', $bathrooms],
            'tenant rent'        => ['tenant', "What is the tenant's maximum rent?", ['max rent', 'what is the max rent', 'maximum rent']],
        ];
    }

    // ── What must still refuse ──────────────────────────────────────────────

    public function test_unapproved_or_underspecified_wording_still_refuses(): void
    {
        $listing = $this->residentialSeller();

        // No alias was added for vague size wording ("how big is the property" reaches a fact
        // only through the runner's pre-existing role-validated keyword map, unchanged here),
        // and a bare quantity word, a misspelling or two facts at once is not a question.
        foreach (['how many', 'number', 'number of', 'what is the', 'size', 'bathroomz', 'bathrooms and bedrooms'] as $asked) {
            $this->assertRefused($this->ask(null, 'seller', $listing->id, $asked), $asked);
        }
    }

    public function test_a_fact_the_listing_does_not_have_is_not_reached_by_any_variant(): void
    {
        // An Income listing has no public bathroom question at all; the vocabulary of a
        // question that is not available is not shipped, so every variant refuses.
        $listing = $this->incomeSeller();

        foreach (['how many bathrooms', 'bathroom', 'baths', 'how many bedrooms', 'beds', 'sq ft'] as $asked) {
            $this->assertRefused($this->ask(null, 'seller', $listing->id, $asked), $asked);
        }
    }

    public function test_a_framing_two_questions_could_claim_reaches_neither(): void
    {
        $questions = [
            ['id' => 'a', 'question' => 'Question A?', 'answer' => 'A', 'aliases' => ['fees']],
            ['id' => 'b', 'question' => 'Question B?', 'answer' => 'B', 'aliases' => ['fees']],
        ];
        $this->assertSame('ambiguous', AskAiPublicQuestionMatcher::match('fees', $questions)['status']);

        // The framings of one alias are a pure function of it — identical aliases on two
        // questions produce identical framings, which the runtime filter hands to neither.
        $this->assertSame(
            AskAiPublicPropertyQuestionService::aliasFramings(['fees']),
            AskAiPublicPropertyQuestionService::aliasFramings(['fees'])
        );
        $this->assertContains('what are the fees', AskAiPublicPropertyQuestionService::aliasFramings(['fees']));
        // A question-shaped alias is never framed.
        $this->assertSame([], AskAiPublicPropertyQuestionService::aliasFramings(['how many bathrooms', 'is there a pool']));
    }

    public function test_no_framing_shipped_by_a_real_listing_is_claimed_by_two_of_its_questions(): void
    {
        $service = new AskAiPublicPropertyQuestionService();
        $context = ['listing' => [
            'property_type' => 'Residential', 'asking_price' => '525000', 'bedrooms' => '3', 'bathrooms' => '2.5',
            'square_feet' => '1850', 'year_built' => '1998', 'annual_property_taxes' => '4200', 'tax_year' => '2025',
            'flood_zone_code' => 'AE', 'pool' => 'Yes', 'hoa_fee' => '250', 'zoning' => 'RS-1',
        ]];
        $questions = $service->forListing('seller', $context, ['bedrooms' => '3', 'bathrooms' => '2.5']);

        $owners = [];
        foreach ($questions as $q) {
            $owners[AskAiPublicPropertyQuestionService::normalizeQuery($q['question'])][] = $q['id'];
            foreach ($q['aliases'] as $alias) {
                $owners[$alias][] = $q['id'];
            }
        }
        $shared = array_filter($owners, static fn (array $ids): bool => count(array_unique($ids)) > 1);
        $this->assertSame([], $shared, 'Phrases shipped to more than one question: ' . json_encode($shared));
    }

    // ── "How big is the property" is ambiguous on EVERY interface ───────────

    /** Living area AND lot both public, so a guess would have something to return. */
    private function sellerWithHouseAndLot(): SellerAgentAuction
    {
        return $this->seller([
            'property_type' => 'Residential', 'maximum_budget' => '525000', 'bedrooms' => '3', 'bathrooms' => '2.5',
            'heated_square_footage' => '1850', 'total_acreage' => '0.75',
        ]);
    }

    public function test_how_big_is_the_property_is_refused_by_the_modal_for_every_viewer(): void
    {
        $listing  = $this->sellerWithHouseAndLot();
        $sqft     = $this->reference('seller', $listing->id, 'What is the heated square footage?');
        $lot      = $this->reference('seller', $listing->id, 'lot size');
        $stranger = User::factory()->create();

        foreach ([null, $stranger, $this->sellerOwner] as $viewer) {
            foreach (['how big is the property', 'How big is the property?', 'how large is the property', 'HOW BIG IS THE PROPERTY'] as $asked) {
                $response = $this->ask($viewer, 'seller', $listing->id, $asked);
                $this->assertRefused($response, $asked);
                $body = (string) $response->getContent();
                $this->assertStringNotContainsString('1,850', $body, "'{$asked}' leaked the square footage");
                $this->assertStringNotContainsString($sqft, $body, "'{$asked}' returned the square-footage answer");
                $this->assertStringNotContainsString($lot, $body, "'{$asked}' returned the lot-size answer");
            }
        }
    }

    public function test_how_big_is_the_property_is_refused_by_the_card_matcher_and_absent_from_the_card(): void
    {
        $listing = $this->sellerWithHouseAndLot();

        // The card's typed box matches ONLY the vocabulary the page ships (the JS matcher is
        // exact). Neither the card's own questions nor its markup carry this wording.
        $questions = (new AskAiPublicPropertyQuestionService())->forStoredListing('seller', $listing->id, false);
        $ids = array_column($questions, 'id');
        $this->assertContains('seller_heated_square_feet', $ids);
        // One of the lot pair is shown (narrower_of decides which) — either way a lot answer
        // is on the card, so a guess toward the lot would have had something to return.
        $this->assertNotSame([], array_intersect(['seller_lot_size', 'seller_total_acreage'], $ids));

        foreach (['how big is the property', 'How big is the property?', 'how large is the property'] as $asked) {
            $this->assertNotSame('matched', AskAiPublicQuestionMatcher::match($asked, $questions)['status'], $asked);
        }

        $html = $this->get('/offer-listing/seller/view/' . $listing->id)->assertOk()->getContent();
        preg_match_all('/data-question-aliases="([^"]*)"/', $html, $m);
        $shipped = array_merge(...array_map(static fn ($a) => explode('|', html_entity_decode($a, ENT_QUOTES)), $m[1]));
        $this->assertNotContains('how big is the property', $shipped);
        $this->assertNotContains('how large is the property', $shipped);
    }

    public function test_the_unambiguous_size_questions_still_work_on_both_interfaces(): void
    {
        $listing   = $this->sellerWithHouseAndLot();
        $questions = (new AskAiPublicPropertyQuestionService())->forStoredListing('seller', $listing->id, false);
        $sqft      = $this->reference('seller', $listing->id, 'What is the heated square footage?');

        foreach (['square footage', 'sq ft', 'sqft', 'how many square feet'] as $asked) {
            $this->assertSameAnswer($sqft, $this->ask(null, 'seller', $listing->id, $asked), $asked);
            $this->assertSame('seller_heated_square_feet', AskAiPublicQuestionMatcher::match($asked, $questions)['question']['id'] ?? null, $asked);
        }

        $lotResponse = $this->ask(null, 'seller', $listing->id, 'lot size');
        $lotResponse->assertOk();
        $this->assertSame('ready', $lotResponse->json('status'));
        $lotId = array_values(array_intersect(['seller_lot_size', 'seller_total_acreage'], array_column($questions, 'id')))[0] ?? null;
        $this->assertNotNull($lotId);
        $this->assertSame($lotId, AskAiPublicQuestionMatcher::match('lot size', $questions)['question']['id'] ?? null);

        // Bathrooms on a listing that has them; refused on one that does not — both interfaces.
        $this->assertSame('seller_bathrooms', AskAiPublicQuestionMatcher::match('how many bathrooms', $questions)['question']['id'] ?? null);
        $this->assertSame('ready', $this->ask(null, 'seller', $listing->id, 'how many bathrooms')->json('status'));

        $income = $this->incomeSeller();
        $incomeQuestions = (new AskAiPublicPropertyQuestionService())->forStoredListing('seller', $income->id, false);
        $this->assertNotSame('matched', AskAiPublicQuestionMatcher::match('how many bathrooms', $incomeQuestions)['status']);
        $this->assertRefused($this->ask(null, 'seller', $income->id, 'how many bathrooms'), 'how many bathrooms');
    }

    public function test_the_modal_fallback_refuses_wording_that_names_two_facts_instead_of_ranking_them(): void
    {
        $listing = $this->sellerWithHouseAndLot();

        // Each fact alone is reachable through the fallback; together they are two facts, and
        // the old "longest keyword wins" rule would have answered one of them.
        foreach (['tell me the home square footage and the lot size', 'bathroom count and bedroom count'] as $asked) {
            $response = $this->ask(null, 'seller', $listing->id, $asked);
            $this->assertRefused($response, $asked);
        }
    }

    // ── Visibility is exactly what it was ───────────────────────────────────

    public function test_private_facts_are_not_reachable_through_any_alias(): void
    {
        $income = $this->incomeSeller();
        $buyer  = $this->buyer();
        $tenant = $this->tenant();
        $stranger = User::factory()->create();

        foreach ([null, $stranger] as $viewer) {
            foreach (['income', 'gross income', 'what is the income', 'what is the gross annual income', 'how much income'] as $asked) {
                $this->assertRefused($this->ask($viewer, 'seller', $income->id, $asked), $asked, self::PRIVATE_INCOME);
            }
            foreach (['credit', 'credit score', 'what is the credit score', 'what is the credit score range'] as $asked) {
                $this->assertRefused($this->ask($viewer, 'buyer', $buyer->id, $asked), $asked, self::PRIVATE_CREDIT);
            }
            foreach (['income', 'monthly income', 'what is the monthly income', 'how much income'] as $asked) {
                $this->assertRefused($this->ask($viewer, 'tenant', $tenant->id, $asked), $asked, self::PRIVATE_INCOME);
            }
        }
    }

    public function test_guest_non_owner_and_owner_see_what_they_saw_before(): void
    {
        $seller = $this->residentialSeller();
        $income = $this->incomeSeller();
        $stranger = User::factory()->create();

        foreach (['how many bathrooms', 'beds', 'sqft', 'how much is the property'] as $asked) {
            $guest = $this->ask(null, 'seller', $seller->id, $asked)->json();
            $other = $this->ask($stranger, 'seller', $seller->id, $asked)->json();
            $owner = $this->ask($this->sellerOwner, 'seller', $seller->id, $asked)->json();
            $this->assertSame('ready', $guest['status'], $asked);
            $this->assertSame($guest['answer'], $other['answer'], "non-owner differs from guest for '{$asked}'");
            $this->assertSame($guest['answer'], $owner['answer'], "owner differs from guest for '{$asked}'");
        }

        // The owner keeps the owner-only fact, exactly as before, by its own question.
        $owner = $this->ask($this->sellerOwner, 'seller', $income->id, 'What is the gross annual income?');
        $this->assertSame('ready', $owner->json('status'));
        $this->assertStringContainsString(self::PRIVATE_INCOME, (string) $owner->json('answer'));
    }

    // ── The card ships the same vocabulary the endpoint matches ─────────────

    public function test_the_card_markup_carries_the_new_vocabulary_for_available_questions_only(): void
    {
        $residential = $this->residentialSeller();
        $html = $this->get('/offer-listing/seller/view/' . $residential->id)->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-property-question="seller_bathrooms"\s+data-question-aliases="[^"]*\|how many baths\|/', $html);
        $this->assertMatchesRegularExpression('/data-question-aliases="[^"]*\|bathroom\|/', $html);

        $income = $this->incomeSeller();
        $html = $this->get('/offer-listing/seller/view/' . $income->id)->assertOk()->getContent();
        $this->assertStringNotContainsString('how many baths', $html);
    }

    // ── The quantity vocabulary is declared, exact, and normalised ──────────

    public function test_quantity_aliases_are_exact_phrases_from_declared_nouns(): void
    {
        $this->assertSame(
            ['bathrooms', 'how many bathrooms', 'number of bathrooms', 'how many bathrooms are there', 'bathroom'],
            AskAiFieldQuestionRegistryService::quantityAliases(['bathrooms'], ['bathroom'], ['are there'])
        );

        foreach (AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry() as $id => $entry) {
            foreach ($entry['aliases'] ?? [] as $alias) {
                $this->assertSame(AskAiPublicPropertyQuestionService::normalizeQuery($alias), $alias, "{$id}: '{$alias}' is not normalised");
            }
        }
    }
}
