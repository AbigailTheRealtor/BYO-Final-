<?php

namespace Tests\Feature\AskAi;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Services\Ai\OpenAiClientService;
use App\Services\AskAi\AskAiIntentNormalizerService;
use App\Services\AskAi\AskAiOpenAiAdapterService;
use App\Services\AskAi\AskAiPublicPropertyQuestionService;
use App\Services\AskAi\AskAiRunnerV2Service;
use App\Services\ListingImport\Mls\MlsSupplementalDetails;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter as W;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Public questions must not require login or listing ownership.
 *
 * POST /ask-ai/listing-question, end to end through the REAL runner (nothing about the
 * answer is mocked): an anonymous guest and a signed-in non-owner are answered at PUBLIC
 * scope and get identical answers; the owner is answered at owner scope and keeps the
 * facts policy permits only to them. Authorization is per FACT — every private,
 * restricted, prohibited, non-displayable, unsupported or ambiguous question is refused —
 * and per LISTING only in that a non-owner reaches exactly the listings whose public page
 * would render for them.
 *
 * Every test runs with no model reachable: the OpenAI client fails the test if called, the
 * intent normaliser's one model method must never run, every outbound HTTP request is
 * faked and asserted absent, and the intent-normalisation flag the workspace sets true is
 * ON, so none of this depends on it being off.
 */
class AskAiPublicGuestAccessTest extends TestCase
{
    use RefreshDatabase;

    private const PRIVATE_INCOME   = '52,800';
    private const PRIVATE_CREDIT   = '740-799';
    private const PRIVATE_EVICTION = 'ZqxEvictionZqx';

    private User $sellerOwner;
    private User $landlordOwner;

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
    }

    protected function tearDown(): void
    {
        Http::assertNothingSent();
        parent::tearDown();
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function seller(array $meta, array $columns = []): SellerAgentAuction
    {
        $listing = SellerAgentAuction::create($columns + [
            'user_id' => $this->sellerOwner->id, 'is_approved' => true, 'is_draft' => false, 'address' => '100 Guest Lane',
        ]);
        foreach (array_merge(['workflow_type' => 'offer_listing'], $meta) as $key => $value) {
            $listing->saveMeta($key, is_array($value) ? json_encode($value) : (string) $value);
        }
        $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $listing->user_id])->id);

        return $listing->fresh();
    }

    private function residentialSeller(array $extra = [], array $columns = []): SellerAgentAuction
    {
        return $this->seller($extra + [
            'property_type' => 'Residential', 'maximum_budget' => '525000', 'bedrooms' => '3', 'bathrooms' => '2.5',
            'year_built' => '1998', 'listing_ai_faq_public_ack' => '1',
            'listing_ai_faq' => ['roof_age_and_condition' => 'Roof replaced in 2019, architectural shingle.'],
        ], $columns);
    }

    private function incomeSeller(): SellerAgentAuction
    {
        return $this->seller([
            'property_type' => 'Income', 'maximum_budget' => '780000', 'year_built' => '1972',
            'minimum_annual_net_income' => self::PRIVATE_INCOME /* owner-only: the seller's desired minimum; gross_annual_income became public in the universal coverage audit (2026-09-24) */,
        ]);
    }

    private function residentialLandlord(): LandlordAgentAuction
    {
        $listing = LandlordAgentAuction::create(['user_id' => $this->landlordOwner->id, 'is_approved' => true, 'is_draft' => false, 'title' => '12 Rental Row']);
        foreach ([
            'workflow_type' => 'offer_listing', 'property_type' => 'Residential Property',
            'desired_rental_amount' => '2,450', 'bedrooms' => '2', 'bathrooms' => '1', 'security_deposit_amount' => '2,450',
            'listing_ai_faq_public_ack' => '1',
            'listing_ai_faq' => json_encode(['notice_to_vacate_required' => '60 days written notice before lease end.']),
        ] as $key => $value) {
            $listing->saveMeta($key, $value);
        }
        $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $listing->user_id])->id);

        return $listing->fresh();
    }

    private function mlsSeller(array $permissions): SellerAgentAuction
    {
        $raw     = json_decode((string) file_get_contents(base_path('tests/fixtures/mls/bridge/residential.json')), true);
        $details = MlsSupplementalDetails::fromRecord($raw, 'seller');

        return $this->seller([
            'property_type' => 'Residential', 'bedrooms' => '3',
            W::META_LISTING_KEY => 'GUEST-LK-1', W::META_DISPLAY_PERMISSIONS => $permissions,
            W::META_PROPERTY_DETAILS => $details->toArray(),
        ]);
    }

    private function buyer(): BuyerAgentAuction
    {
        $listing = BuyerAgentAuction::create(['user_id' => User::factory()->create()->id, 'title' => 'Guest buyer', 'is_approved' => true, 'is_draft' => false, 'is_sold' => false]);
        foreach (['workflow_type' => 'offer_listing', 'property_type' => 'Residential', 'bedrooms' => '3', 'maximum_budget' => '450000', 'credit_scroe_rating' => self::PRIVATE_CREDIT] as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    private function tenant(): TenantAgentAuction
    {
        $listing = TenantAgentAuction::factory()->active()->create(['user_id' => User::factory()->create()->id]);
        foreach (['workflow_type' => 'offer_listing', 'property_type' => 'Residential', 'bedrooms' => '2', 'budget' => '2,200', 'monthly_income' => self::PRIVATE_INCOME, 'prior_eviction' => self::PRIVATE_EVICTION] as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** Ask as a guest (null), a signed-in user, with optional client-supplied options. */
    private function ask(?User $as, string $type, int $id, string $question, ?array $options = null): TestResponse
    {
        // The controller's per-IP / per-user / per-listing limits are covered by
        // AskAiRateLimiterTest; each question here starts with a fresh allowance.
        Cache::flush();
        auth()->logout();
        if ($as !== null) {
            $this->actingAs($as);
        }

        return $this->postJson('/ask-ai/listing-question', array_filter([
            'listing_type' => $type,
            'listing_id'   => $id,
            'question'     => $question,
            'options'      => $options,
        ], fn ($v) => $v !== null));
    }

    private function assertAnswered(TestResponse $response, string $expected): void
    {
        $response->assertOk();
        $this->assertSame('ready', $response->json('status'), (string) $response->getContent());
        $this->assertStringContainsString($expected, (string) $response->json('answer'));
    }

    private function assertRefusedWithout(TestResponse $response, string $secret): void
    {
        $response->assertOk();
        $this->assertNotSame('ready', $response->json('status'), (string) $response->getContent());
        $this->assertStringNotContainsString($secret, (string) $response->getContent());
    }

    // ── Anonymous guests are answered public facts ──────────────────────────

    public function test_anonymous_seller_viewer_can_ask_a_public_fact(): void
    {
        $listing = $this->residentialSeller();

        $this->assertAnswered($this->ask(null, 'seller', $listing->id, 'How many bedrooms are there?'), 'This property has 3 bedrooms.');
        $this->assertAnswered($this->ask(null, 'seller', $listing->id, 'beds'), 'This property has 3 bedrooms.');
    }

    public function test_anonymous_seller_viewer_can_ask_an_approved_public_knowledge_base_answer(): void
    {
        $listing = $this->residentialSeller();

        $this->assertAnswered(
            $this->ask(null, 'seller', $listing->id, 'How old is the roof, and what condition is it in?'),
            'According to the seller: Roof replaced in 2019, architectural shingle.'
        );
    }

    public function test_an_unacknowledged_knowledge_base_answer_is_not_public(): void
    {
        $listing = $this->residentialSeller(['listing_ai_faq_public_ack' => '0']);

        $this->assertRefusedWithout(
            $this->ask(null, 'seller', $listing->id, 'How old is the roof, and what condition is it in?'),
            'Roof replaced in 2019'
        );
    }

    public function test_anonymous_landlord_viewer_can_ask_public_facts(): void
    {
        $listing = $this->residentialLandlord();

        $this->assertAnswered($this->ask(null, 'landlord', $listing->id, 'What is the rent?'), '$2,450');
        $this->assertAnswered($this->ask(null, 'landlord', $listing->id, 'How many bedrooms are there?'), '2 bedrooms');
        $this->assertAnswered(
            $this->ask(null, 'landlord', $listing->id, 'How much notice is required to vacate at lease end?'),
            'According to the landlord: 60 days written notice before lease end.'
        );
    }

    public function test_anonymous_viewer_can_ask_permitted_public_mls_facts(): void
    {
        $listing = $this->mlsSeller(['idx_participation' => true, 'entire_listing_display' => true, 'address_display' => true]);

        $this->assertAnswered($this->ask(null, 'seller', $listing->id, 'Flooring'), 'Flooring: Laminate.');
    }

    public function test_non_displayable_mls_facts_are_refused_to_non_owners_and_kept_for_the_owner(): void
    {
        $listing = $this->mlsSeller(['idx_participation' => false]);

        $this->assertRefusedWithout($this->ask(null, 'seller', $listing->id, 'Flooring'), 'Laminate');
        $this->assertRefusedWithout($this->ask(User::factory()->create(), 'seller', $listing->id, 'Flooring'), 'Laminate');
        $this->assertAnswered($this->ask($this->sellerOwner, 'seller', $listing->id, 'Flooring'), 'Flooring: Laminate.');
    }

    public function test_anonymous_viewer_can_ask_public_buyer_and_tenant_criteria(): void
    {
        $buyer  = $this->buyer();
        $tenant = $this->tenant();

        $this->assertAnswered($this->ask(null, 'buyer', $buyer->id, 'How many bedrooms are they looking for?'), 'at least 3 bedrooms');
        $this->assertAnswered($this->ask(null, 'tenant', $tenant->id, "What is the tenant's maximum rent?"), '$2,200');
    }

    // ── A signed-in non-owner gets exactly a guest's answers ─────────────────

    public function test_logged_in_non_owner_receives_the_same_public_answers_as_a_guest(): void
    {
        $seller   = $this->residentialSeller();
        $income   = $this->incomeSeller();
        $landlord = $this->residentialLandlord();
        $stranger = User::factory()->create();

        foreach ([
            ['seller', $seller->id, 'How many bedrooms are there?'],
            ['seller', $seller->id, 'How old is the roof, and what condition is it in?'],
            ['seller', $seller->id, 'What is the racial makeup of this neighborhood?'],
            ['seller', $income->id, 'What is the minimum annual net income?'],
            ['landlord', $landlord->id, 'What is the security deposit?'],
            ['landlord', $landlord->id, 'What is the rent?'],
        ] as [$type, $id, $question]) {
            $guest = $this->ask(null, $type, $id, $question)->json();
            $other = $this->ask($stranger, $type, $id, $question)->json();

            $this->assertSame($guest, $other, "A signed-in non-owner must get a guest's answer to '{$question}'.");
        }
    }

    // ── The owner keeps what policy permits only to them ─────────────────────

    public function test_owner_retains_permitted_owner_only_access(): void
    {
        $income = $this->incomeSeller();

        $this->assertAnswered($this->ask($this->sellerOwner, 'seller', $income->id, 'What is the minimum annual net income?'), self::PRIVATE_INCOME);
    }

    // ── Private, restricted, prohibited, unsupported and ambiguous all refuse ─

    public function test_private_facts_remain_refused_to_guests_and_non_owners(): void
    {
        $income   = $this->incomeSeller();
        $buyer    = $this->buyer();
        $tenant   = $this->tenant();
        $stranger = User::factory()->create();

        foreach ([null, $stranger] as $viewer) {
            $this->assertRefusedWithout($this->ask($viewer, 'seller', $income->id, 'What is the minimum annual net income?'), self::PRIVATE_INCOME);
            $this->assertRefusedWithout($this->ask($viewer, 'buyer', $buyer->id, 'What is the credit score range?'), self::PRIVATE_CREDIT);
            $this->assertRefusedWithout($this->ask($viewer, 'tenant', $tenant->id, 'What is the monthly income?'), self::PRIVATE_INCOME);
            $this->assertRefusedWithout($this->ask($viewer, 'tenant', $tenant->id, 'Does the tenant have a prior eviction?'), self::PRIVATE_EVICTION);
        }
    }

    public function test_a_non_owners_runner_options_are_ignored(): void
    {
        $income = $this->incomeSeller();

        $this->assertRefusedWithout($this->ask(null, 'seller', $income->id, 'What is the minimum annual net income?', [
            'normalized_field_key'    => 'minimum_annual_net_income',
            'viewer_scope'            => 'owner',
            'restricted_owner_answer' => 'INJECTED',
        ]), self::PRIVATE_INCOME);
        $this->assertStringNotContainsString('INJECTED', (string) $this->ask(null, 'seller', $income->id, 'x', ['restricted_owner_answer' => 'INJECTED'])->getContent());
    }

    public function test_prohibited_questions_refuse(): void
    {
        $listing = $this->residentialSeller();

        $response = $this->ask(null, 'seller', $listing->id, 'What is the racial makeup of this neighborhood?');
        $response->assertOk();
        $this->assertSame('blocked', $response->json('status'));
        $this->assertNull($response->json('answer'));
    }

    public function test_unsupported_questions_refuse(): void
    {
        $listing = $this->residentialSeller();

        foreach (['qwzx plarn vorble?', 'What should I know before I buy this?'] as $question) {
            $response = $this->ask(null, 'seller', $listing->id, $question);
            $response->assertOk();
            $this->assertNotSame('ready', $response->json('status'), $question);
            $this->assertSame(AskAiRunnerV2Service::DETERMINISTIC_UNANSWERABLE, $response->json('answer'), $question);
        }
    }

    public function test_ambiguous_questions_refuse(): void
    {
        $listing = $this->residentialSeller();

        // Two available questions both claim "parking": refused, never guessed.
        $service = $this->getMockBuilder(AskAiPublicPropertyQuestionService::class)->disableOriginalConstructor()->onlyMethods(['forStoredListing'])->getMock();
        $service->method('forStoredListing')->willReturn([
            ['id' => 'q_garage',   'question' => 'Is there a garage?',   'answer' => 'Garage: 2 spaces.', 'aliases' => ['parking']],
            ['id' => 'q_driveway', 'question' => 'Is there a driveway?', 'answer' => 'Driveway: Paved.',  'aliases' => ['parking']],
        ]);
        $this->app->instance(AskAiPublicPropertyQuestionService::class, $service);

        $response = $this->ask(null, 'seller', $listing->id, 'parking');
        $response->assertOk();
        $this->assertSame(AskAiRunnerV2Service::DETERMINISTIC_AMBIGUOUS, $response->json('answer'));
        $this->assertStringNotContainsString('2 spaces', (string) $response->getContent());
        $this->assertStringNotContainsString('Paved', (string) $response->getContent());
    }

    // ── A non-owner reaches exactly the listings whose public page renders ────

    public function test_a_non_owner_cannot_reach_a_listing_whose_public_page_would_not_render(): void
    {
        $draft      = $this->residentialSeller([], ['is_draft' => true]);
        $unapproved = $this->residentialSeller([], ['is_approved' => false]);
        $archived   = $this->residentialSeller([], ['is_archived' => true]);
        $hire       = $this->residentialSeller(['workflow_type' => 'hire_agent']);
        $stranger   = User::factory()->create();

        foreach ([$draft, $unapproved, $archived, $hire] as $listing) {
            foreach ([null, $stranger] as $viewer) {
                $response = $this->ask($viewer, 'seller', $listing->id, 'How many bedrooms are there?');
                $response->assertNotFound();
                $this->assertSame('not_found', $response->json('status'));
                $this->assertStringNotContainsString('3 bedrooms', (string) $response->getContent());
            }
            // …and the page agrees.
            $this->get(route('offer.listing.seller.view', $listing->id))->assertNotFound();
        }

        // The owner may still ask about their own draft, as they can open its page.
        $this->assertAnswered($this->ask($this->sellerOwner, 'seller', $draft->id, 'How many bedrooms are there?'), '3 bedrooms');
    }

    // ── The page a guest sees works, and says nothing about ownership ─────────

    public function test_a_logged_out_seller_page_offers_a_working_ask_ai_modal(): void
    {
        $listing = $this->residentialSeller();

        $html = $this->get(route('offer.listing.seller.view', $listing->id))->assertOk()->getContent();

        // Ask AI is selection-based: the guest opens the modal and SELECTS a verified public
        // question whose answer is already in the page. No login, no ownership, no text box.
        $this->assertStringContainsString('id="solAiModal"', $html);
        $this->assertStringContainsString('data-bs-target="#solAiModal"', $html);
        $this->assertStringContainsString('data-ask-ai-picker="seller"', $html);
        $this->assertStringContainsString('data-ask-ai-pick="seller_bedrooms"', $html);
        $this->assertMatchesRegularExpression('/data-ask-ai-answer-for="seller_bedrooms"[^>]*>[^<]*3 bedrooms/', $html);
        $this->assertStringNotContainsString('available to the listing owner', $html);

        // Owner questions, and the one asset that reaches the endpoint, are the owner's alone.
        $this->assertStringNotContainsString('data-ask-ai-owner-picker', $html);
        $this->assertStringNotContainsString('owner-question-picker.js', $html);
    }

    // ── Zero model calls on every path ───────────────────────────────────────

    public function test_guest_non_owner_and_owner_paths_make_zero_model_calls(): void
    {
        $this->assertFalse(AskAiOpenAiAdapterService::LLM_ANSWERING_APPROVED);

        $seller = $this->residentialSeller();
        $income = $this->incomeSeller();

        foreach ([null, User::factory()->create(), $this->sellerOwner] as $viewer) {
            foreach ([
                [$seller->id, 'How many bedrooms are there?'],
                [$seller->id, 'Tell me something interesting about the neighbourhood vibe'],
                [$income->id, 'What is the minimum annual net income?'],
                [$income->id, 'Does the description mention a workshop?'],
            ] as [$id, $question]) {
                $this->ask($viewer, 'seller', $id, $question)->assertOk();
            }
        }
        // setUp's OpenAiClientService mock (never send), the normaliser's shouldNotReceive,
        // and tearDown's Http::assertNothingSent() are the assertions.
    }
}
