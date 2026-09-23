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
 * Since the public-access change the free-text modal is open to shoppers too — public
 * questions must not require login or ownership. Its endpoint authorizes per fact: a
 * shopper is answered at PUBLIC scope and never with the owner's options. What a shopper
 * is shown there is still governed here: its example questions are the listing's own
 * verified public questions, never the generic lists above, and it carries no suggestion
 * chips. The card itself stays inert.
 *
 * Revealing an answer is a native <details> toggle over text already in the page, so the
 * zero-network guarantee is structural: the card carries no script, link, form or handler,
 * no page script refers to it, and the shopper page contains no Ask AI endpoint at all.
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

        // The modal's examples are this listing's own verified public questions.
        $this->assertStringContainsString($prefix . 'AiExampleText', $html);
        $this->assertStringContainsString(json_encode('How many bedrooms are there?', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), $html);
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

    // ── 9–10. The public free-text modal, and revealing a card answer is network-free ─

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
            $prefix . 'AiTextarea',
            $prefix . 'AiSubmitBtn',
            '/ask-ai/listing-question',
            'Ask AI answers verified information available about this listing.',
        ] as $present) {
            $this->assertStringContainsString($present, $html, "Shopper page must carry '{$present}'.");
        }

        foreach ([
            'Ask AI for this listing is available to the listing owner.',
            'showOwnerOnlyNotice',
            '/api/ask-ai',
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

        // The card is inert markup.
        foreach (['<script', '<form', 'action=', '<textarea', 'href=', 'wire:', 'data-bs-toggle', 'fetch', 'XMLHttpRequest'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $card, "Card must not contain '{$forbidden}'.");
        }
        $this->assertDoesNotMatchRegularExpression('#\son[a-z]+\s*=#i', $card, 'Card must carry no inline event handler.');

        // No page script reaches into the card, so nothing can intercept a click on it.
        foreach ($this->scripts($html) as $script) {
            foreach (['ask-ai-pq', 'data-property-question', 'data-property-answer', 'ask-ai-card', 'data-ask-ai-property-questions'] as $hook) {
                $this->assertStringNotContainsString($hook, $script, "A page script must not hook the Ask AI card ('{$hook}').");
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

        // SUPERSEDED IN PART BY BATCH 3. The partial now pushes one external script — the
        // typed-question matcher — and carries an <input>. What must still hold, and is
        // asserted below, is that neither can become a request: no inline code, no form, no
        // request API, no handler attribute, and no Ask AI endpoint. '/ask-ai' also left this
        // list because it now matches the ASSET path js/ask-ai/…, which is a static file
        // rather than a route; the endpoints themselves are still forbidden by name.
        foreach (['fetch', 'XMLHttpRequest', 'axios', '$.ajax', '$.post',
                  '/ask-ai/listing-question', '/api/ask-ai', '/agent-ai/',
                  'href=', '<form', 'wire:', 'onclick'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $source, "Card partial contains '{$forbidden}'.");
        }

        preg_match_all('/<script\b[^>]*>/i', $source, $scripts);
        $this->assertCount(1, $scripts[0], 'The card partial must carry exactly one script tag.');
        $this->assertStringContainsString('js/ask-ai/deterministic-question-matcher.js', $scripts[0][0]);
        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)/i', $source,
            'Every script in the card partial must be an external asset, never inline.');
        $this->assertDoesNotMatchRegularExpression('/<input\b[^>]*\bname=/', $source,
            'The typed-question input must carry no name attribute.');
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

    // ── 14. The owner keeps their existing Ask AI ──────────────────────────────

    public static function roleProvider(): array
    {
        return ['seller' => ['seller'], 'landlord' => ['landlord']];
    }

    /**
     * @dataProvider roleProvider
     */
    public function test_owner_keeps_the_existing_ask_ai_modal_and_free_text_flow(string $role): void
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

        // … plus the owner's existing Ask AI: card trigger, modal, textbox and endpoint.
        $this->assertStringContainsString('data-bs-target="#' . $prefix . 'AiModal"', $card);
        $this->assertStringContainsString('id="' . $prefix . 'AiModal"', $html);
        $this->assertStringContainsString($prefix . 'AiTextarea', $html);
        $this->assertStringContainsString($prefix . 'AiSubmitBtn', $html);
        $this->assertStringContainsString('/ask-ai/listing-question', $html);
        $this->assertStringContainsString($prefix . 'AiExampleText', $html);
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
