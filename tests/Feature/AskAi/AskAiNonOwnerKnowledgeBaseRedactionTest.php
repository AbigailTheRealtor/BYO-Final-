<?php

namespace Tests\Feature\AskAi;

use App\Models\User;
use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\AskAi\AskAiInternalRunnerService;
use App\Services\AskAi\AskAiIntentNormalizerService;
use App\Services\AskAi\AskAiOpenAiAdapterService;
use App\Services\AskAi\AskAiPromptBuilderService;
use App\Services\AskAi\AskAiResponseContractService;
use App\Services\AskAi\AskAiViewerAuthorizationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Batch 0 — the owner-authored Knowledge Base (faq_answers) never reaches a non-owner
 * Ask AI context or prompt through POST /api/ask-ai/ask.
 *
 * That route resolves the requester's scope itself and has no ownership check, so it is
 * where a non-owner's question meets a listing's full Knowledge Base. The pipeline here is
 * REAL from the controller down — scope resolution against the database, the runner, the
 * internal runner, redaction, the response contract and the prompt builder. Only three
 * seams are replaced: the context builder (so the Knowledge Base holds a known sentinel),
 * the OpenAI adapter and the intent normaliser (so nothing leaves the process). A recording
 * internal runner captures the exact context and prompt package the pipeline produced.
 */
class AskAiNonOwnerKnowledgeBaseRedactionTest extends TestCase
{
    use DatabaseTransactions;

    private const KB_SENTINEL = 'KB-SENTINEL-new-roof-2019-architectural-shingle';

    private const OWNER_TABLES = [
        'seller'   => 'seller_agent_auctions',
        'landlord' => 'landlord_agent_auctions',
        'buyer'    => 'buyer_agent_auctions',
        'tenant'   => 'tenant_agent_auctions',
    ];

    /** @var array<int, array> Every internal-runner result the pipeline produced. */
    private array $internalRuns = [];

    /** @var array<int, array> Every prompt package handed to the OpenAI adapter. */
    private array $adapterPackages = [];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::fake();

        config([
            'ask_ai.enable_openai_intent_normalization' => false,
            'ask_ai.enable_description_fallback'        => false,
        ]);

        $this->bindPipelineSeams();
    }

    private function knowledgeBaseContext(string $role): array
    {
        return [
            'listing' => [
                'listing_type' => $role,
                'bedrooms'     => 3,
                'city'         => 'Tampa',
            ],
            'faq_answers' => [
                'roof_age_and_condition' => [
                    'answer_text'           => self::KB_SENTINEL,
                    'question_label'        => 'How old is the roof, and what condition is it in?',
                    'question_group'        => 'Property Condition & Systems',
                    'intelligence_category' => 'condition',
                ],
                'seller_motivation_for_selling' => [
                    'answer_text'    => 'Relocating — ' . self::KB_SENTINEL,
                    'question_label' => 'Why is the owner selling the property?',
                ],
            ],
        ];
    }

    private function bindPipelineSeams(): void
    {
        $test = $this;

        $contextBuilder = $this->createMock(AskAiContextBuilderService::class);
        $contextBuilder->method('buildForListing')->willReturnCallback(
            fn (string $listingType) => $test->knowledgeBaseContext(strtolower($listingType))
        );

        $recordingRunner = new class(
            $contextBuilder,
            $this->app->make(AskAiResponseContractService::class),
            $this->app->make(AskAiPromptBuilderService::class),
            new AskAiViewerAuthorizationService()
        ) extends AskAiInternalRunnerService {
            /** @var callable */
            public $record;

            public function run(string $listingType, int $listingId, string $questionType, string $userQuestion, array $options = []): array
            {
                $result = parent::run($listingType, $listingId, $questionType, $userQuestion, $options);
                ($this->record)($result + ['viewer_scope' => $options['viewer_scope'] ?? null]);
                return $result;
            }
        };
        $recordingRunner->record = function (array $result) use ($test): void {
            $test->internalRuns[] = $result;
        };
        $this->app->instance(AskAiInternalRunnerService::class, $recordingRunner);

        $adapter = $this->createMock(AskAiOpenAiAdapterService::class);
        $adapter->method('generate')->willReturnCallback(function (array $package) use ($test): array {
            $test->adapterPackages[] = $package;
            return [
                'success'      => true,
                'status'       => 'generated',
                'raw_response' => 'Generated answer.',
                'model'        => 'test-double',
                'error'        => null,
            ];
        });
        $this->app->instance(AskAiOpenAiAdapterService::class, $adapter);

        $normalizer = $this->createMock(AskAiIntentNormalizerService::class);
        $normalizer->method('isEnabled')->willReturn(false);
        $this->app->instance(AskAiIntentNormalizerService::class, $normalizer);
    }

    private function makeListing(string $role, User $owner): int
    {
        $table = self::OWNER_TABLES[$role];
        $row   = [
            'user_id'    => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        // buyer_agent_auctions.title is NOT NULL; the other three tables have no such column.
        if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'title')) {
            $row['title'] = 'Batch 0 fixture';
        }

        return (int) DB::table($table)->insertGetId($row);
    }

    private function ask(User $as, string $role, int $listingId, string $question): \Illuminate\Testing\TestResponse
    {
        Sanctum::actingAs($as);

        return $this->postJson('/api/ask-ai/ask', [
            'listing_type' => $role,
            'listing_id'   => $listingId,
            'question'     => $question,
        ]);
    }

    public static function rolesAndQuestionsProvider(): array
    {
        $cases = [];
        foreach (['seller', 'landlord', 'buyer', 'tenant'] as $role) {
            // A question the keyword router grounds in the Knowledge Base key itself, and a
            // broad one that reaches the listing_facts contract whose allowed context is the
            // whole faq_answers umbrella.
            $cases["{$role} / kb-grounded question"] = [$role, 'How old is the roof?'];
            $cases["{$role} / broad listing question"] = [$role, 'What should I know about this home?'];
        }
        return $cases;
    }

    /**
     * @dataProvider rolesAndQuestionsProvider
     */
    public function test_non_owner_api_request_never_places_knowledge_base_in_context_or_prompt(string $role, string $question): void
    {
        $owner    = User::factory()->create();
        $nonOwner = User::factory()->create();
        $listingId = $this->makeListing($role, $owner);

        $response = $this->ask($nonOwner, $role, $listingId, $question);
        $response->assertOk();

        // The non-owner path now resolves deterministically BEFORE any context is built: an
        // answer comes from the public card (which never reads an unacknowledged Knowledge
        // Base) and anything else is refused. So the proof is either (a) the request never
        // built a context at all — a stronger guarantee than a redacted one — or (b) every
        // context it did build is redacted. The response assertion below holds for both, and
        // the owner test keeps it sensitive.
        if ($this->internalRuns === []) {
            // This fixture listing holds no public fact, so the resolver's only true answer is
            // the deterministic refusal.
            $this->assertSame(
                \App\Services\AskAi\AskAiRunnerV2Service::DETERMINISTIC_UNANSWERABLE,
                $response->json('answer_text'),
                'With no pipeline run the answer must come from the deterministic resolver.'
            );
        }

        foreach ($this->internalRuns as $run) {
            $this->assertNotSame(AskAiViewerAuthorizationService::SCOPE_OWNER, $run['viewer_scope']);
            $this->assertIsArray($run['context']);
            $this->assertArrayNotHasKey('faq_answers', $run['context']);
            $this->assertStringNotContainsString(self::KB_SENTINEL, json_encode($run['context']));
            $this->assertStringNotContainsString(self::KB_SENTINEL, json_encode($run['prompt_package']));
        }

        foreach ($this->adapterPackages as $package) {
            $this->assertStringNotContainsString(self::KB_SENTINEL, json_encode($package));
        }

        $this->assertStringNotContainsString(self::KB_SENTINEL, $response->getContent());
    }

    /**
     * The same request as the listing's owner still receives the Knowledge Base, which is
     * what proves the non-owner assertions above are sensitive rather than vacuous.
     *
     * @dataProvider rolesAndQuestionsProvider
     */
    public function test_owner_api_request_keeps_existing_knowledge_base_behavior(string $role, string $question): void
    {
        $owner     = User::factory()->create();
        $listingId = $this->makeListing($role, $owner);

        $this->ask($owner, $role, $listingId, $question)->assertOk();

        $this->assertNotEmpty($this->internalRuns);
        foreach ($this->internalRuns as $run) {
            $this->assertSame(AskAiViewerAuthorizationService::SCOPE_OWNER, $run['viewer_scope']);
            $this->assertArrayHasKey('faq_answers', $run['context']);
            $this->assertSame(
                self::KB_SENTINEL,
                $run['context']['faq_answers']['roof_age_and_condition']['answer_text']
            );
        }
    }

    /**
     * The knowledge-grounded owner question still reaches the Knowledge Base answer in the
     * prompt package — the owner's existing path to a KB-backed answer is intact.
     */
    public function test_owner_kb_grounded_question_still_carries_the_answer_into_the_prompt(): void
    {
        $owner     = User::factory()->create();
        $listingId = $this->makeListing('seller', $owner);

        $this->ask($owner, 'seller', $listingId, 'How old is the roof?')->assertOk();

        $carried = false;
        foreach ($this->internalRuns as $run) {
            if (str_contains((string) json_encode($run['prompt_package']), self::KB_SENTINEL)) {
                $carried = true;
            }
        }
        $this->assertTrue($carried, 'Owner prompt package must still contain the Knowledge Base answer');
    }

    public function test_guest_cannot_reach_the_pipeline_at_all(): void
    {
        $owner     = User::factory()->create();
        $listingId = $this->makeListing('seller', $owner);

        $this->postJson('/api/ask-ai/ask', [
            'listing_type' => 'seller',
            'listing_id'   => $listingId,
            'question'     => 'How old is the roof?',
        ])->assertStatus(401);

        $this->assertSame([], $this->internalRuns);
        $this->assertSame([], $this->adapterPackages);
    }
}
