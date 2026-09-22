<?php

namespace Tests\Feature\AskAi;

use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\Ai\OpenAiClientService;
use App\Services\AskAi\AskAiIntentNormalizerService;
use App\Services\AskAi\AskAiPublicPropertyQuestionService;
use App\Services\AskAi\AskAiRunnerV2Service;
use App\Services\AskAi\AskAiViewerAuthorizationService;
use App\Support\AskAi\AskAiPublicQuestionMatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Ask AI refuses what it cannot answer deterministically — permanently, truthfully, and without
 * reaching for anything else.
 *
 * For a non-owner viewer (PUBLIC and AUTHORIZED scope) each of these must end in a refusal that
 * carries no listing value:
 *   - nonsense;
 *   - an open-ended question no stored fact answers;
 *   - a question about a PRIVATE field whose value is present on the listing;
 *   - a PROHIBITED (Fair Housing) question;
 *   - a phrase two available questions both claim (ambiguous) — refused, never guessed.
 *
 * And for every one of them: no model client is invoked, the intent normaliser never normalises
 * (with its config flag deliberately ON), no adapter answered — at most the zero-LLM gate
 * refused — and asking again gives the identical refusal: nothing is retried, cached or
 * escalated.
 */
class AskAiDeterministicRefusalTest extends TestCase
{
    use DatabaseTransactions;

    private const PRIVATE_SENTINEL = 'ZqxPrivateReasonZqx';

    protected function setUp(): void
    {
        parent::setUp();

        $client = $this->getMockBuilder(OpenAiClientService::class)->disableOriginalConstructor()->onlyMethods(['send'])->getMock();
        $client->expects($this->never())->method('send');
        $this->app->instance(OpenAiClientService::class, $client);

        // The real gate (isEnabled) stays; the one method that could reach a model must never run.
        $this->partialMock(AskAiIntentNormalizerService::class, function ($mock): void {
            $mock->shouldNotReceive('normalize');
        });

        // The flag the workspace .env sets true: refusals must not depend on it being off.
        config(['ask_ai.enable_openai_intent_normalization' => true]);
    }

    /** @return array<string, array{0: string}> */
    public static function nonOwnerScopes(): array
    {
        return [
            'public'     => [AskAiViewerAuthorizationService::SCOPE_PUBLIC],
            'authorized' => [AskAiViewerAuthorizationService::SCOPE_AUTHORIZED],
        ];
    }

    /**
     * @dataProvider nonOwnerScopes
     */
    public function test_unsupported_private_and_prohibited_questions_are_refused(string $scope): void
    {
        $listingId = $this->sellerListing();
        $runner    = app(AskAiRunnerV2Service::class);

        $cases = [
            'nonsense'       => 'qwzx plarn vorble?',
            'open-ended'     => 'What should I know before I buy this?',
            'private field'  => 'What is the reason for sale?',
            'private keyword' => 'why are they selling',
            'prohibited'     => 'What is the racial makeup of this neighborhood?',
        ];

        foreach ($cases as $case => $question) {
            $first  = $runner->run('seller', $listingId, $question, ['viewer_scope' => $scope]);
            $second = $runner->run('seller', $listingId, $question, ['viewer_scope' => $scope]);

            $this->assertNotSame('ready', $first['status'], "{$scope} / {$case}: answered instead of refused.");
            // Either no adapter was reached at all, or the only thing that answered was the
            // zero-LLM gate's own refusal — no model, no tokens, no request.
            $adapter = $first['adapter_result'] ?? null;
            if ($adapter !== null) {
                $this->assertSame('llm_answering_not_approved', $adapter['error'] ?? null, "{$scope} / {$case}: an adapter answered.");
                $this->assertNull($adapter['model'] ?? null);
                $this->assertSame(0, (int) ($adapter['total_tokens'] ?? 0));
                $this->assertNull($adapter['api_request_id'] ?? null);
            }

            $envelope = (string) json_encode($first['final_response'] ?? []);
            $this->assertStringNotContainsString(self::PRIVATE_SENTINEL, $envelope, "{$scope} / {$case}: the private value leaked.");
            $this->assertStringNotContainsString('4 bedrooms', $envelope, "{$scope} / {$case}: an unrelated public fact was offered as the answer.");
            $this->assertDoesNotMatchRegularExpression('/try again|temporarily|later/i', (string) ($first['final_response']['answer'] ?? ''),
                "{$scope} / {$case}: a permanent refusal must not invite a retry.");

            // Deterministic: the same question gets the same refusal, word for word.
            $this->assertSame($first['final_response']['answer'] ?? null, $second['final_response']['answer'] ?? null, "{$scope} / {$case}: not deterministic.");
            $this->assertSame($first['status'], $second['status']);
        }
    }

    /**
     * @dataProvider nonOwnerScopes
     */
    public function test_the_public_questions_themselves_are_still_answered(string $scope): void
    {
        // The refusals above are not a runner that refuses everything.
        $result = app(AskAiRunnerV2Service::class)->run('seller', $this->sellerListing(), 'How many bedrooms are there?', ['viewer_scope' => $scope]);

        $this->assertSame('ready', $result['status']);
        $this->assertStringContainsString('4 bedrooms', (string) $result['final_response']['answer']);
    }

    /**
     * @dataProvider nonOwnerScopes
     */
    public function test_a_phrase_two_questions_claim_is_refused_as_ambiguous_never_guessed(string $scope): void
    {
        $listingId = $this->sellerListing();

        // A card on which two available questions both answer to "parking" — the runner must
        // refuse rather than pick either.
        $card = [
            ['id' => 'q_garage',   'question' => 'Is there a garage?',       'answer' => 'Garage: 2 spaces.',    'aliases' => ['parking']],
            ['id' => 'q_driveway', 'question' => 'Is there a driveway?',     'answer' => 'Driveway: Paved.',     'aliases' => ['parking']],
        ];
        $service = $this->getMockBuilder(AskAiPublicPropertyQuestionService::class)->disableOriginalConstructor()->onlyMethods(['forStoredListing'])->getMock();
        $service->method('forStoredListing')->willReturn($card);
        $this->app->instance(AskAiPublicPropertyQuestionService::class, $service);

        $result = app(AskAiRunnerV2Service::class)->run('seller', $listingId, 'parking', ['viewer_scope' => $scope]);

        $this->assertNotSame('ready', $result['status']);
        $this->assertSame(AskAiRunnerV2Service::DETERMINISTIC_AMBIGUOUS, $result['final_response']['answer'] ?? null);
        foreach (['2 spaces', 'Paved'] as $value) {
            $this->assertStringNotContainsString($value, (string) json_encode($result['final_response']));
        }
    }

    public function test_the_matcher_refuses_ambiguity_at_every_tier(): void
    {
        $questions = [
            ['id' => 'a', 'question' => 'What is the zoning?', 'answer' => 'A', 'aliases' => ['land use']],
            ['id' => 'b', 'question' => 'What is the zoning?', 'answer' => 'B', 'aliases' => ['land use']],
            ['id' => 'c', 'question' => 'What is the lot size?', 'answer' => 'C', 'aliases' => ['lot']],
        ];

        // Two questions share the displayed text, and two share an alias: both refused.
        $this->assertSame('ambiguous', AskAiPublicQuestionMatcher::match('what is the zoning', $questions)['status']);
        $this->assertSame('ambiguous', AskAiPublicQuestionMatcher::match('Land use', $questions)['status']);
        // Exactly one claimant: matched.
        $this->assertSame('c', AskAiPublicQuestionMatcher::match('lot', $questions)['question']['id']);
        // No claimant, no guess — not even the nearest wording.
        $this->assertSame('none', AskAiPublicQuestionMatcher::match('lot sizes', $questions)['status']);
        $this->assertSame('none', AskAiPublicQuestionMatcher::match('', $questions)['status']);
    }

    private function sellerListing(): int
    {
        $owner   = User::factory()->create();
        $listing = SellerAgentAuction::forceCreate(['user_id' => $owner->id, 'title' => 'Refusal fixture', 'is_draft' => false, 'is_approved' => true]);
        $listing->saveMeta('workflow_type', 'offer_listing');
        $listing->saveMeta('property_type', 'Residential');
        $listing->saveMeta('bedrooms', '4');
        // Present, and private (owner_only): the refusal must not be "no data".
        $listing->saveMeta('reason_for_sale', self::PRIVATE_SENTINEL);

        return $listing->id;
    }
}
