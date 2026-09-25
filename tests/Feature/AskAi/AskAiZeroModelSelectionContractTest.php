<?php

namespace Tests\Feature\AskAi;

use App\Models\User;
use App\Services\Ai\OpenAiClientService;
use App\Services\AskAi\AskAiIntentNormalizerService;
use App\Services\AskAi\AskAiOpenAiAdapterService;
use App\Services\AskAi\AskAiPublicPropertyQuestionService;
use App\Services\AskAi\AskAiRunnerV2Service;
use App\Support\AskAi\AskAiOwnerQuestionSelection;
use App\Support\AskAi\AskAiQuestionPresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Support\AskAi\MlsImportFixture;
use Tests\TestCase;

/**
 * Ask AI is deterministic and zero-LLM for EVERY viewer — guest, logged-in non-owner and
 * owner — on every selectable question, and on anything else posted to the endpoint.
 *
 * The public picker answers from precomputed markup and sends nothing. The owner picker
 * sends a registry KEY (AskAiOwnerQuestionSelection), resolved on the server by exact
 * match; an unknown key is refused before the runner runs. A question with no
 * deterministic answer is REFUSED — AskAiOpenAiAdapterService::LLM_ANSWERING_APPROVED is
 * false, and that constant stays the authority.
 *
 * Every test runs with every model seam guarded, and with the intent-normalisation flag
 * the workspace sets true switched ON, so nothing here depends on it being off:
 *   OPENAI CALLS = 0         the OpenAI client fails the test if send() is called
 *   OTHER MODEL CALLS = 0    the normaliser's model method must never run, and
 *                            test_the_endpoint_reaches_no_ungated_model_client() proves
 *                            the endpoint's whole reference closure holds no other client
 *   OUTBOUND MODEL HTTP = 0  every outbound request throws, and none may be recorded
 */
class AskAiZeroModelSelectionContractTest extends TestCase
{
    use RefreshDatabase;

    private const ROOF = 'Roof replaced in 2019 with architectural shingle.';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mls_direct_import.prefill_enabled'      => true,
            'mls_direct_import.quick_import_enabled' => true,
            'mls_direct_import.prefill_roles'        => ['seller', 'landlord'],
            'ask_ai.enable_openai_intent_normalization' => true,
        ]);

        $client = $this->getMockBuilder(OpenAiClientService::class)->disableOriginalConstructor()->onlyMethods(['send'])->getMock();
        $client->expects($this->never())->method('send');
        $this->app->instance(OpenAiClientService::class, $client);

        $this->partialMock(AskAiIntentNormalizerService::class, function ($mock): void {
            $mock->shouldNotReceive('normalize');
        });

        Http::fake(static fn () => throw new \RuntimeException('No outbound request is allowed in this test.'));
    }

    protected function tearDown(): void
    {
        Http::assertNothingSent();
        parent::tearDown();
    }

    // ── Fixtures and parsing ────────────────────────────────────────────────

    /** A rich MLS seller listing whose roof answer is the owner's own, unpublished KB answer. */
    private function listing(): object
    {
        $listing = MlsImportFixture::import($this, 'residential', 'seller');
        $listing->saveMeta('listing_ai_faq', json_encode(['roof_age_and_condition' => self::ROOF]));

        return $listing->fresh();
    }

    private function page(int $id, ?User $as = null): string
    {
        $req  = $as ? $this->actingAs($as) : $this;
        $html = $req->get(route('offer.listing.seller.view', $id))->assertOk()->getContent();
        auth()->logout();

        return $html;
    }

    private function modal(string $html): string
    {
        $start = strpos($html, 'data-ask-ai-picker="seller"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'ask-ai-picker-disclaimer', $start);

        return substr($html, $start, $end - $start);
    }

    /** @return array<string, string> id => the precomputed answer shown on selection */
    private function answers(string $modal): array
    {
        preg_match_all('/data-ask-ai-answer-for="([^"]+)"[^>]*>(.*?)<\/p>/s', $modal, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as [, $id, $answer]) {
            $out[$id] = html_entity_decode(trim($answer), ENT_QUOTES);
        }

        return $out;
    }

    /** @return list<array{key: string, question: string}> the owner buttons, as rendered */
    private function ownerButtons(string $modal): array
    {
        preg_match_all('/<button[^>]*data-ask-ai-owner-question="([^"]*)"[^>]*data-ask-ai-owner-key="([^"]*)"[^>]*>/', $modal, $m, PREG_SET_ORDER);

        return array_map(static fn ($x) => [
            'key'      => html_entity_decode($x[2], ENT_QUOTES),
            'question' => html_entity_decode($x[1], ENT_QUOTES),
        ], $m);
    }

    private function ask(object $listing, array $payload, ?User $as = null): TestResponse
    {
        Cache::flush(); // the controller's per-viewer rate limits are not under test here
        $req = $as ? $this->actingAs($as) : $this;
        $res = $req->postJson('/ask-ai/listing-question', $payload + ['listing_type' => 'seller', 'listing_id' => $listing->id]);
        auth()->logout();

        return $res->assertOk();
    }

    private function assertRefused(TestResponse $res, string $why): void
    {
        $this->assertSame('insufficient_context', $res->json('status'), $why);
        $this->assertFalse((bool) $res->json('success'), $why);
        $this->assertNotSame('failed', $res->json('status'), $why);
    }

    // ── Owner: featured, View All, owner-only ───────────────────────────────

    public function test_owner_selecting_a_featured_or_view_all_question_gets_its_deterministic_answer_with_no_request(): void
    {
        $listing = $this->listing();
        $owner   = User::find($listing->user_id);
        $rows    = app(AskAiPublicPropertyQuestionService::class)->forStoredListing('seller', $listing->id, true);
        $set     = AskAiQuestionPresentation::build('seller', $rows, []);
        $byId    = array_column($rows, 'answer', 'id');

        $featured = $set['featured'][0]['id'] ?? null;
        $nonFeatured = array_values(array_diff(array_column($set['all'], 'id'), array_column($set['featured'], 'id')))[0] ?? null;
        $this->assertNotNull($featured);
        $this->assertNotNull($nonFeatured, 'View All must hold a question that is not featured.');

        // Selecting a question reveals the answer already in the owner's page; the public
        // picker sends nothing (tearDown asserts no request was made).
        $answers = $this->answers($this->modal($this->page($listing->id, $owner)));
        foreach ([$featured, $nonFeatured] as $id) {
            $this->assertArrayHasKey($id, $answers);
            $this->assertSame((string) $byId[$id], $answers[$id], "{$id}: the shown answer is the service's deterministic answer.");
            $this->assertNotSame('', trim($answers[$id]));
        }
    }

    public function test_owner_only_question_is_answered_deterministically_for_the_owner_by_key_and_refused_to_everyone_else(): void
    {
        $listing = $this->listing();
        $owner   = User::find($listing->user_id);

        // Unpublished KB answer: not a public question at all.
        $this->assertStringNotContainsString(self::ROOF, $this->page($listing->id));

        $buttons = $this->ownerButtons($this->modal($this->page($listing->id, $owner)));
        $this->assertContains('faq_answers.roof_age_and_condition', array_column($buttons, 'key'));

        $mine = $this->ask($listing, ['question_key' => 'faq_answers.roof_age_and_condition'], $owner);
        $this->assertSame('ready', $mine->json('status'));
        $this->assertTrue((bool) $mine->json('success'));
        $this->assertStringContainsString(self::ROOF, (string) $mine->json('answer'));

        foreach ([null, User::factory()->create()] as $viewer) {
            $theirs = $this->ask($listing, ['question_key' => 'faq_answers.roof_age_and_condition'], $viewer);
            $this->assertStringNotContainsString(self::ROOF, json_encode($theirs->json()), 'An owner-only answer never reaches a non-owner.');
        }
    }

    public function test_every_rendered_owner_question_is_a_registry_key_that_resolves_to_its_own_question(): void
    {
        $listing = $this->listing();
        $buttons = $this->ownerButtons($this->modal($this->page($listing->id, User::find($listing->user_id))));
        $this->assertNotEmpty($buttons);

        // The same count of owner buttons as button elements: none is missing its key.
        $this->assertSame(substr_count($this->modal($this->page($listing->id, User::find($listing->user_id))), 'data-ask-ai-owner-question='), count($buttons));

        foreach ($buttons as $b) {
            $this->assertSame($b, AskAiOwnerQuestionSelection::resolve('seller', $b['key']), "{$b['key']} must resolve exactly to its button.");
            $res = $this->ask($listing, ['question_key' => $b['key']], User::find($listing->user_id));
            $this->assertContains($res->json('status'), ['ready', 'insufficient_context'], "{$b['key']}: deterministic answer or refusal, nothing else.");
        }
    }

    public function test_the_owner_picker_sends_the_selected_key_and_no_question_text(): void
    {
        $js = (string) file_get_contents(base_path('public/js/ask-ai/owner-question-picker.js'));

        $this->assertSame(1, substr_count($js, "fetch('/ask-ai/listing-question'"), 'One request site.');
        $this->assertStringContainsString("getAttribute('data-ask-ai-owner-key')", $js);
        preg_match('/body:\s*JSON\.stringify\(\{(.*?)\}\)/s', $js, $body);
        $this->assertNotEmpty($body, 'The request body must be a literal object.');
        $this->assertMatchesRegularExpression('/\bquestion_key\s*:\s*questionKey\b/', $body[1]);
        $this->assertDoesNotMatchRegularExpression('/\bquestion\s*:/', $body[1], 'Display text is never sent for re-interpretation.');
        $this->assertDoesNotMatchRegularExpression('/\boptions\s*:/', $body[1], 'No runner options from the browser.');
    }

    public function test_keyless_suggestions_are_never_offered_to_the_owner(): void
    {
        $listing = $this->listing();
        $modal   = $this->modal($this->page($listing->id, User::find($listing->user_id)));

        foreach ([
            'What marketing angles could work for this property?',
            'How does this home compare to similar listings on the platform?',
            'What type of buyer might find this property a practical fit?',
            'What information is missing from this listing?',
        ] as $modelShaped) {
            $this->assertStringNotContainsString(e($modelShaped), $modal);
        }
    }

    // ── Refusal, never fallback ─────────────────────────────────────────────

    public function test_a_malformed_or_unrecognised_key_is_refused_before_the_runner_runs(): void
    {
        $listing = $this->listing();
        $owner   = User::find($listing->user_id);
        $this->partialMock(AskAiRunnerV2Service::class, function ($mock): void {
            $mock->shouldNotReceive('run');
        });

        foreach ([
            'listing.not_a_field', 'faq_answers.roof_age_and_conditions', 'LISTING.BEDROOMS', 'faq_answers.',
            'listing.max_rent' /* a tenant key, not a seller one */, 'roof', '../listing.bedrooms',
        ] as $key) {
            $res = $this->ask($listing, ['question_key' => $key, 'question' => 'What is the roof age?'], $owner);
            $this->assertRefused($res, "key '{$key}'");
            $this->assertSame(AskAiRunnerV2Service::DETERMINISTIC_UNANSWERABLE, $res->json('answer'));
            $this->assertSame('deterministic_refusal', $res->json('source.answer_source'));
        }
    }

    public function test_an_owner_question_with_no_deterministic_answer_refuses_rather_than_falling_back(): void
    {
        $this->assertFalse(AskAiOpenAiAdapterService::LLM_ANSWERING_APPROVED);

        $listing = $this->listing();
        $owner   = User::find($listing->user_id);

        foreach ([
            'What marketing angles could work for this property?',
            'How does this home compare to similar listings on the platform?',
            'Write me a catchy description for this home.',
            'zzqx blorp?',
        ] as $question) {
            $res = $this->ask($listing, ['question' => $question], $owner);
            $this->assertRefused($res, $question);
            $this->assertNull($res->json('source_attribution.model'));
        }
    }

    // ── One contract for every viewer ───────────────────────────────────────

    public function test_guest_non_owner_and_owner_share_the_zero_model_contract(): void
    {
        $listing = $this->listing();
        $viewers = ['guest' => null, 'non-owner' => User::factory()->create(), 'owner' => User::find($listing->user_id)];

        foreach ($viewers as $who => $viewer) {
            $this->page($listing->id, $viewer);

            foreach ([
                ['question_key' => 'listing.bedrooms'],
                ['question_key' => 'faq_answers.roof_age_and_condition'],
                ['question_key' => 'listing.not_a_field'],
                ['question' => 'How many bedrooms does this property have?'],
                ['question' => 'What marketing angles could work for this property?'],
                ['question' => 'zzqx blorp?'],
            ] as $payload) {
                $res = $this->ask($listing, $payload, $viewer);
                $this->assertContains($res->json('status'), ['ready', 'insufficient_context', 'blocked'],
                    "{$who} " . json_encode($payload) . ': deterministic answer or refusal only.');
            }
        }

        // The bedrooms key answers deterministically for every viewer: guest and non-owner
        // identically (public scope), and the owner's key selection exactly as the owner's
        // own question text is answered — the key changes how the fact is FOUND, not what
        // the owner is told.
        $answers = array_map(fn ($v) => $this->ask($listing, ['question_key' => 'listing.bedrooms'], $v)->json('answer'), $viewers);
        $this->assertSame($answers['guest'], $answers['non-owner'], 'Guest and non-owner read the same public answer.');
        $ownerByText = $this->ask($listing, ['question' => 'How many bedrooms does this property have?'], $viewers['owner'])->json('answer');
        $this->assertSame($ownerByText, $answers['owner']);
        foreach ($answers as $who => $answer) {
            $this->assertStringContainsString('2', (string) $answer, "{$who}: the bedroom count is answered.");
            $this->assertNotSame(AskAiRunnerV2Service::DETERMINISTIC_UNANSWERABLE, $answer);
        }
    }

    // ── Reachability ────────────────────────────────────────────────────────

    /**
     * REACHABLE LLM PATHS FROM THE ENDPOINT = 0. Walk every App\ class the controller
     * references, transitively (an over-approximation of what the request can reach), and
     * require that the only model client in that closure is OpenAiClientService, held only
     * by the two classes that refuse on LLM_ANSWERING_APPROVED before touching it, and that
     * no other model SDK or model host appears anywhere in it.
     */
    public function test_the_endpoint_reaches_no_ungated_model_client(): void
    {
        $closure = $this->referenceClosure(\App\Http\Controllers\AskAiListingQuestionController::class);
        $this->assertGreaterThan(50, count($closure), 'The closure walk found too little to be meaningful.');

        $holders = [];
        $otherModelClients = [];
        foreach ($closure as $class => $file) {
            $src = $this->code((string) file_get_contents($file));
            if ($class !== OpenAiClientService::class && str_contains($src, 'OpenAiClientService')) {
                $holders[] = $class;
            }
            if ($class !== OpenAiClientService::class
                && preg_match('/\\\\?OpenAI\\\\|Anthropic|Gemini|GenerativeAI|api\.openai\.com|api\.anthropic\.com|generativelanguage\.googleapis\.com/i', $src)) {
                $otherModelClients[] = $class;
            }
        }
        sort($holders);

        $this->assertSame([AskAiIntentNormalizerService::class, AskAiOpenAiAdapterService::class], $holders,
            'Only the two gated classes may hold the model client on this request path.');
        $this->assertSame([], $otherModelClients, 'No other model SDK or model host is reachable from the endpoint.');

        foreach ($holders as $holder) {
            $src = (string) file_get_contents((new \ReflectionClass($holder))->getFileName());
            $this->assertMatchesRegularExpression('/LLM_ANSWERING_APPROVED\s*!==\s*true\)\s*\{/', $src, "{$holder} must refuse on the constant.");
        }
    }

    /** @return array<class-string, string> */
    private function referenceClosure(string $root): array
    {
        $seen  = [];
        $queue = [$root];
        while ($queue !== []) {
            $class = array_pop($queue);
            if (isset($seen[$class]) || ! str_starts_with($class, 'App\\')) {
                continue;
            }
            $file = base_path('app/' . str_replace('\\', '/', substr($class, 4)) . '.php');
            if (! is_file($file)) {
                $seen[$class] = null;
                continue;
            }
            $seen[$class] = $file;
            $src = (string) file_get_contents($file);
            preg_match('/^namespace\s+([^;]+);/m', $src, $ns);
            preg_match_all('/\\\\?(App\\\\[A-Za-z0-9_\\\\]+)/', $src, $fq);
            foreach ($fq[1] as $ref) {
                $queue[] = rtrim($ref, '\\');
            }
            preg_match_all('/\b([A-Z][A-Za-z0-9_]+)(?=::|\s+\$|\))/', $src, $short);
            foreach ($short[1] as $name) {
                $queue[] = ($ns[1] ?? 'App') . '\\' . $name;
            }
        }

        return array_filter($seen);
    }

    /** Source with comments removed, so a docblock that names a provider is not a client. */
    private function code(string $src): string
    {
        $out = '';
        foreach (token_get_all($src) as $t) {
            if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $out .= is_array($t) ? $t[1] : $t;
        }

        return $out;
    }
}
