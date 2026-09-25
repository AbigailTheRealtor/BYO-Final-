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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Batch 2a — one shopper-facing Ask AI experience on the Seller and Landlord listing pages.
 *
 * The Ask AI quick-actions card is now where "Questions About This Property" lives. For a
 * shopper (a guest, or a signed-in user who does not own the listing) it holds the verified,
 * precomputed questions and nothing else: the decorative chips, the disabled textbox, the
 * generic rotating examples, the suggested-question chips and the separate Batch 1 section
 * are all gone. The listing owner keeps their existing Ask AI modal.
 *
 * SELECTION-BASED (2026-09-25). The card shows the FEATURED subset and one button that opens
 * the Ask AI modal, open to every viewer. The modal lists the listing's own verified public
 * questions — recommended first, the complete set behind "View all questions", filtered by a
 * nameless search box — with their precomputed answers already in the markup. There is no
 * free-text box: a shopper selects, never types a question to be answered. The owner
 * additionally gets their suggested questions as selectable buttons (owner scope).
 *
 * Revealing an answer is a native <details> toggle (card) or a local lookup of text already
 * in the page (modal), so the zero-network guarantee is structural: neither region carries a
 * form, link, request API or handler, no inline page script refers to them, and a shopper's
 * page contains no Ask AI endpoint at all.
 * (The repository's Playwright suite runs against static fixtures for JavaScript renderers;
 * this card has no JavaScript to drive, so the proof lives here, against the real page.)
 */
class AskAiPropertyCardShopperExperienceTest extends TestCase
{
    use RefreshDatabase;

    /** Old hardcoded Seller card chips and rotating examples (exact rendered text). */
    private const OLD_SELLER_GENERIC = [
        'HOA fees &amp; what they cover?',
        'Is this in a flood zone?',
        'Financing options available?',
        'Roof age &amp; condition?',
        'School districts nearby?',
        '"What are the HOA fees and what do they cover?"',
        '"Is this property in a flood zone?"',
        '"What financing options does the seller accept?"',
        '"When was the roof last replaced?"',
    ];

    /**
     * Old hardcoded Landlord card chips and rotating examples. "Are pets allowed?" and
     * "What appliances are included?" are omitted on purpose: both are real Batch 1
     * questions that legitimately render when the listing can answer them.
     */
    private const OLD_LANDLORD_GENERIC = [
        'What utilities are included?',
        'What is the lease term?',
        'What are the move-in costs?',
        'Is parking available?',
        'What utilities are included in the rent?',
        'Are pets allowed at this property?',
        'What is the minimum lease term?',
        'What are the move-in costs and deposits?',
        'Is parking included or available?',
        'Is the property near public transit?',
    ];

    // ── Fixtures ─────────────────────────────────────────────────────────────

    private function sellerListing(?User $owner = null): SellerAgentAuction
    {
        $owner ??= User::factory()->create();
        $listing = SellerAgentAuction::create([
            'user_id'     => $owner->id,
            'is_approved' => true,
            'is_draft'    => false,
            'address'     => '100 Test Lane',
        ]);

        $listing->saveMeta('workflow_type', 'offer_listing');
        foreach ([
            // Ask AI resolves property type fail-closed; every real listing states one.
            'property_type'             => 'Residential',
            'auction_type'              => 'Traditional',
            'maximum_budget'            => '500000',
            'bedrooms'                  => '3',
            'bathrooms'                 => '2.5',
            'annual_property_taxes'     => '1856',
            'tax_year'                  => '2025',
            'has_hoa'                   => 'Yes',
            'association_fee_amount'    => '250',
            'association_fee_frequency' => 'Monthly',
            'appliances'                => json_encode(['Dishwasher', 'Range']),
        ] as $key => $value) {
            $listing->saveMeta($key, $value);
        }
        $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $owner->id])->id);

        return $listing->fresh();
    }

    private function landlordListing(?User $owner = null): LandlordAgentAuction
    {
        $owner ??= User::factory()->create();
        $listing = LandlordAgentAuction::create([
            'user_id'     => $owner->id,
            'is_approved' => true,
            'is_draft'    => false,
            'title'       => 'Test Rental',
        ]);

        $listing->saveMeta('workflow_type', 'offer_listing');
        foreach ([
            'property_type' => 'Residential Property',
            'bedrooms'   => '2',
            'bathrooms'  => '1',
            'appliances' => json_encode(['Washer', 'Dryer']),
            'pets'       => 'No',
        ] as $key => $value) {
            $listing->saveMeta($key, $value);
        }
        $listing->saveMeta('linked_offer_auction_id', OfferAuction::create(['user_id' => $owner->id])->id);

        return $listing->fresh();
    }

    private function page(string $role, int $id): string
    {
        $route = $role === 'landlord' ? 'offer.listing.landlord.view' : 'offer.listing.seller.view';

        return $this->get(route($route, ['id' => $id]))->assertStatus(200)->getContent();
    }

    private function card(string $html, string $role): string
    {
        $start = strpos($html, 'data-ask-ai-property-questions="' . $role . '"');
        $this->assertNotFalse($start, "The {$role} Ask AI card must render.");
        $prefix = $role === 'landlord' ? 'lol' : 'sol';
        $next   = strpos($html, 'class="' . $prefix . '-interaction-card"', $start);

        return substr($html, $start, $next === false ? null : $next - $start);
    }

    private function modal(string $html, string $role): string
    {
        $start = strpos($html, 'data-ask-ai-picker="' . $role . '"');
        $this->assertNotFalse($start, "The {$role} Ask AI modal must render.");
        $end = strpos($html, 'ask-ai-picker-disclaimer', $start);

        return substr($html, $start, $end === false ? null : $end - $start);
    }

    /** @return string[] the inline script bodies on the page */
    private function scripts(string $html): array
    {
        preg_match_all('#<script\b[^>]*>(.*?)</script>#is', $html, $m);

        return $m[1];
    }

    /**
     * @return array<string, array{string, string}> role => [role, viewer]
     */
    public static function shopperProvider(): array
    {
        return [
            'seller / guest'               => ['seller', 'guest'],
            'seller / signed-in non-owner' => ['seller', 'non-owner'],
            'landlord / guest'             => ['landlord', 'guest'],
            'landlord / signed-in non-owner' => ['landlord', 'non-owner'],
        ];
    }

    private function shopperPage(string $role, string $viewer): string
    {
        $listing = $role === 'landlord' ? $this->landlordListing() : $this->sellerListing();
        if ($viewer === 'non-owner') {
            $this->actingAs(User::factory()->create());
        }

        return $this->page($role, $listing->id);
    }

    // ── 1–4. The card renders the current Batch 1 questions with their answers ──

    /**
     * @dataProvider shopperProvider
     */
    public function test_shopper_card_renders_current_questions_with_prerendered_answers(string $role, string $viewer): void
    {
        $card = $this->card($this->shopperPage($role, $viewer), $role);

        $this->assertStringContainsString('Questions About This Property', $card);

        $expected = $role === 'landlord'
            ? [
                'landlord_bedrooms'     => ['How many bedrooms are there?', 'This property has 2 bedrooms.'],
                'landlord_appliances'   => ['What appliances are included?', 'Appliances listed for this property: Washer, Dryer.'],
                'landlord_pets_allowed' => ['Are pets allowed?', "Pets are not allowed under the property&#039;s pet policy."],
            ]
            : [
                'seller_property_taxes' => ['What are the property taxes?', 'Annual property taxes are $1,856 for tax year 2025.'],
                'seller_hoa_fee'        => ['What are the HOA fees?', 'The HOA fee is $250 per month.'],
                'seller_bedrooms'       => ['How many bedrooms are there?', 'This property has 3 bedrooms.'],
            ];

        foreach ($expected as $id => [$question, $answer]) {
            // Question and answer are in the same closed <details>: the answer is already
            // in the page before anyone clicks.
            $this->assertMatchesRegularExpression(
                '#<details(?![^>]*\bopen\b)[^>]*data-property-question="' . $id . '"[^>]*>\s*'
                . '<summary[^>]*>' . preg_quote($question, '#') . '</summary>\s*'
                . '<p[^>]*data-property-answer="' . $id . '"[^>]*>' . preg_quote($answer, '#') . '#',
                $card,
                "{$id} must render with its precomputed answer for a {$viewer}."
            );
        }
    }

    // ── 6–8. Old shopper UI is gone and there is exactly one surface ──────────

    /**
     * @dataProvider shopperProvider
     */
    public function test_old_generic_shopper_ui_is_gone(string $role, string $viewer): void
    {
        $html   = $this->shopperPage($role, $viewer);
        $prefix = $role === 'landlord' ? 'lol' : 'sol';

        foreach ($role === 'landlord' ? self::OLD_LANDLORD_GENERIC : self::OLD_SELLER_GENERIC as $generic) {
            $this->assertStringNotContainsString($generic, $html, "Generic '{$generic}' must not reach a shopper.");
        }

        foreach ([
            $prefix . '-interaction-ai-chip',                   // decorative chips
            'Ask a question about this property…',              // the disabled textbox
            'Suggested Questions',                               // modal suggested chips (V2 header)
            '<button type="button" role="button" class="ask-ai-chip"', // rendered suggestion chips
        ] as $old) {
            $this->assertStringNotContainsString($old, $html, "Old shopper Ask AI UI '{$old}' must be gone.");
        }

        // The retired free-text modal and its rotating examples are gone too.
        foreach ([$prefix . 'AiExampleText', $prefix . 'AiTextarea', $prefix . 'AiSubmitBtn'] as $old) {
            $this->assertStringNotContainsString($old, $html, "Retired free-text Ask AI '{$old}' must be gone.");
        }

        // What the modal offers is this listing's own verified public questions.
        $modal = $this->modal($html, $role);
        $this->assertStringContainsString('data-ask-ai-pick="' . $role . '_bedrooms"', $modal);
        $this->assertStringContainsString('How many bedrooms are there?', $modal);
    }

    /**
     * @dataProvider shopperProvider
     */
    public function test_questions_about_this_property_appears_once_in_the_ask_ai_card(string $role, string $viewer): void
    {
        $html = $this->shopperPage($role, $viewer);

        $this->assertSame(1, substr_count($html, 'Questions About This Property'));
        $this->assertSame(1, substr_count($html, 'data-ask-ai-property-questions='));
        $this->assertStringNotContainsString('id="section-property-questions"', $html);
        $this->assertStringNotContainsString('data-property-questions=', $html);
        $this->assertFileDoesNotExist(base_path('resources/views/offer-listing/partials/_property-questions.blade.php'));
    }

    // ── 9–10. The public selection modal, and revealing an answer is network-free ──

    /**
     * @dataProvider shopperProvider
     */
    public function test_shopper_page_offers_the_public_ask_ai_modal(string $role, string $viewer): void
    {
        $html   = $this->shopperPage($role, $viewer);
        $prefix = $role === 'landlord' ? 'lol' : 'sol';

        foreach ([
            'id="' . $prefix . 'AiModal"',
            'data-bs-target="#' . $prefix . 'AiModal"',
            'data-ask-ai-picker="' . $role . '"',
            'placeholder="Search questions..."',
            'View all questions',
            'js/ask-ai/question-picker.js',
        ] as $present) {
            $this->assertStringContainsString($present, $html, "Shopper page must carry '{$present}'.");
        }

        foreach ([
            'Ask AI for this listing is available to the listing owner.',
            'showOwnerOnlyNotice',
            '/api/ask-ai',
            // A shopper selects; nothing on their page can submit a question.
            '/ask-ai/listing-question',
            'owner-question-picker.js',
            'data-ask-ai-owner-picker',
            $prefix . 'AiTextarea',
            $prefix . 'AiSubmitBtn',
        ] as $absent) {
            $this->assertStringNotContainsString($absent, $html, "Shopper page must not carry '{$absent}'.");
        }
    }

    /**
     * @dataProvider shopperProvider
     */
    public function test_revealing_an_answer_has_no_request_path(string $role, string $viewer): void
    {
        $html = $this->shopperPage($role, $viewer);
        $card = $this->card($html, $role);

        $modal = $this->modal($html, $role);

        // The card and the modal are inert markup. The card's one control opens the modal
        // (a Bootstrap toggle, not a request) and names nothing else.
        foreach (['card' => $card, 'modal' => $modal] as $name => $region) {
            foreach (['<script', '<form', 'action=', '<textarea', 'href=', 'wire:', 'fetch', 'XMLHttpRequest'] as $forbidden) {
                $this->assertStringNotContainsStringIgnoringCase($forbidden, $region, ucfirst($name) . " must not contain '{$forbidden}'.");
            }
            $this->assertDoesNotMatchRegularExpression('#\son[a-z]+\s*=#i', $region, ucfirst($name) . ' must carry no inline event handler.');
            $this->assertDoesNotMatchRegularExpression('/<input\b[^>]*\bname=/', $region, ucfirst($name) . ' must carry no named input.');
        }
        $prefix = $role === 'landlord' ? 'lol' : 'sol';
        preg_match_all('/data-bs-target="([^"]*)"/', $card, $targets);
        $this->assertSame(['#' . $prefix . 'AiModal'], array_values(array_unique($targets[1])), 'The card may only open the Ask AI modal.');

        // Every listed question's answer is already in the page before anyone selects it.
        preg_match_all('/data-ask-ai-pick="([^"]+)"/', $modal, $picks);
        preg_match_all('/data-ask-ai-answer-for="([^"]+)"/', $modal, $answers);
        $this->assertNotEmpty($picks[1]);
        $this->assertSame([], array_diff($picks[1], $answers[1]), 'A selectable question has no precomputed answer.');

        // No inline page script reaches into the card or the modal, so nothing can intercept
        // a selection; the only picker script is the external, request-free asset.
        foreach ($this->scripts($html) as $script) {
            foreach (['ask-ai-pq', 'data-property-question', 'data-property-answer', 'ask-ai-card', 'data-ask-ai-property-questions',
                      'data-ask-ai-pick', 'data-ask-ai-answer-for', 'data-ask-ai-picker'] as $hook) {
                $this->assertStringNotContainsString($hook, $script, "A page script must not hook Ask AI ('{$hook}').");
            }
        }
    }

    public function test_card_partial_source_has_no_script_request_or_link(): void
    {
        $source = preg_replace(
            '/\{\{--.*?--\}\}/s',
            '',
            file_get_contents(base_path('resources/views/offer-listing/partials/_ask-ai-property-card.blade.php'))
        );

        // SELECTION-BASED (2026-09-25) retired the Batch 3 typed box: the card is inert
        // markup again — featured questions and one button that opens the modal. Search
        // lives in the modal and only filters.
        foreach (['fetch', 'XMLHttpRequest', 'axios', '$.ajax', '$.post',
                  '/ask-ai/listing-question', '/api/ask-ai', '/agent-ai/',
                  'href=', '<form', 'wire:', 'onclick', '<textarea'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $source, "Card partial contains '{$forbidden}'.");
        }

        $this->assertDoesNotMatchRegularExpression('/<script\b/i', $source, 'The card partial carries no script.');
        $this->assertDoesNotMatchRegularExpression('/<input\b/i', $source, 'The card partial carries no input.');
        $this->assertStringNotContainsString('deterministic-question-matcher.js', $source);
    }

    // ── 11–13. No language model, normaliser, classifier or search ────────────

    public function test_rendering_the_card_for_shoppers_and_owners_calls_no_ai_service_or_network(): void
    {
        Http::fake();

        $this->mock(OpenAiClientService::class)->shouldNotReceive('send');
        $this->mock(AskAiOpenAiAdapterService::class)->shouldNotReceive('generate');
        $this->mock(AskAiIntentNormalizerService::class)->shouldNotReceive('normalize');
        $this->mock(AskAiQuestionClassifierService::class)->shouldNotReceive('classify');
        $this->mock(AskAiKnowledgeSearchService::class)->shouldNotReceive('search');
        $this->mock(AskAiRunnerV2Service::class)->shouldNotReceive('run');

        $sellerOwner   = User::factory()->create();
        $landlordOwner = User::factory()->create();
        $seller   = $this->sellerListing($sellerOwner);
        $landlord = $this->landlordListing($landlordOwner);

        // Guest, signed-in non-owner, and owner — every rendering of the card.
        $this->page('seller', $seller->id);
        $this->page('landlord', $landlord->id);
        $this->actingAs(User::factory()->create());
        $this->page('seller', $seller->id);
        $this->page('landlord', $landlord->id);
        $this->actingAs($sellerOwner);
        $this->page('seller', $seller->id);
        $this->actingAs($landlordOwner);
        $this->page('landlord', $landlord->id);

        Http::assertNothingSent();
    }

    // ── 14. The owner gets their own questions — still selected, never typed ───

    public static function roleProvider(): array
    {
        return ['seller' => ['seller'], 'landlord' => ['landlord']];
    }

    /**
     * @dataProvider roleProvider
     */
    public function test_owner_gets_selectable_owner_questions_and_no_free_text_box(string $role): void
    {
        $owner   = User::factory()->create();
        $listing = $role === 'landlord' ? $this->landlordListing($owner) : $this->sellerListing($owner);
        $prefix  = $role === 'landlord' ? 'lol' : 'sol';

        $this->actingAs($owner);
        $html = $this->page($role, $listing->id);
        $card = $this->card($html, $role);

        // Same verified questions in the card …
        $this->assertStringContainsString('Questions About This Property', $card);
        $this->assertStringContainsString('data-property-question="' . $role . '_bedrooms"', $card);

        // … plus the owner's own suggested questions in the modal, as selectable buttons
        // answered at owner scope by the owner-only picker asset. Still no text box.
        $this->assertStringContainsString('data-bs-target="#' . $prefix . 'AiModal"', $card);
        $this->assertStringContainsString('id="' . $prefix . 'AiModal"', $html);
        $modal = $this->modal($html, $role);
        $this->assertStringContainsString('data-ask-ai-owner-picker', $modal);
        $this->assertMatchesRegularExpression('/<button type="button"[^>]*data-ask-ai-owner-question="[^"]+"/', $modal);
        $this->assertStringContainsString('js/ask-ai/owner-question-picker.js', $html);
        foreach (['<textarea', '<form', $prefix . 'AiTextarea', $prefix . 'AiSubmitBtn'] as $absent) {
            $this->assertStringNotContainsString($absent, $modal, "The owner's Ask AI must not carry '{$absent}'.");
        }
        $this->assertDoesNotMatchRegularExpression('/<input\b[^>]*\bname=/', $modal);
    }

    /**
     * A guest's null id and a listing's null user_id both cast to 0; that must never read as
     * ownership — neither in what the page shows the guest nor in the scope they are answered at.
     */
    public function test_guest_on_a_listing_without_an_owner_is_never_treated_as_its_owner(): void
    {
        $listing = $this->landlordListing();
        DB::table('landlord_agent_auctions')->where('id', $listing->id)->update(['user_id' => null]);

        $html = $this->page('landlord', $listing->id);
        foreach (self::OLD_LANDLORD_GENERIC as $ownerExample) {
            $this->assertStringNotContainsString($ownerExample, $html, 'The owner\'s modal examples must not reach a guest.');
        }
        $this->assertStringNotContainsString('<button type="button" role="button" class="ask-ai-chip"', $html);
        $this->assertStringNotContainsString('data-ask-ai-owner-picker', $html, 'A guest must never get owner questions.');

        $this->mock(AskAiRunnerV2Service::class)->shouldReceive('run')->once()
            ->withArgs(fn ($type, $id, $q, $options) => ($options['viewer_scope'] ?? null) === 'public')
            ->andReturn(['success' => true, 'status' => 'ready', 'final_response' => ['answer' => 'ok']]);

        $this->postJson('/ask-ai/listing-question', [
            'listing_type' => 'landlord',
            'listing_id'   => $listing->id,
            'question'     => 'How many bedrooms are there?',
        ])->assertOk();
    }

    /**
     * A shopper reaches the endpoint, and is answered at PUBLIC scope with none of the
     * client-supplied runner options — never the owner's scope, whatever a page renders.
     */
    public function test_endpoint_answers_a_non_owner_at_public_scope_only(): void
    {
        $this->mock(AskAiRunnerV2Service::class)->shouldReceive('run')->once()
            ->withArgs(fn ($type, $id, $q, $options) => $options === ['viewer_scope' => 'public', 'requester_user_id' => auth()->id()])
            ->andReturn(['success' => true, 'status' => 'ready', 'final_response' => ['answer' => 'Taxes are public.']]);

        $listing = $this->sellerListing();
        $this->actingAs(User::factory()->create());

        $this->postJson('/ask-ai/listing-question', [
            'listing_type' => 'seller',
            'listing_id'   => $listing->id,
            'question'     => 'What are the property taxes?',
            'options'      => ['normalized_field_key' => 'reason_for_sale', 'viewer_scope' => 'owner'],
        ])->assertOk()->assertJsonPath('answer', 'Taxes are public.');
    }
}
