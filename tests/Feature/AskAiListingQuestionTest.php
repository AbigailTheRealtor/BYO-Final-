<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\AskAi\AskAiRunnerV2Service;
use App\Services\AskAi\AskAiViewerAuthorizationService;
use App\Services\AskAi\AskAiComplianceGuardrailService;
use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Routing\Middleware\ThrottleRequests;
use App\Http\Middleware\VerifyCsrfToken;

class AskAiListingQuestionTest extends TestCase
{
    use WithFaker;
    use DatabaseTransactions;

    private User $user;
    private int $sellerId;
    private int $buyerId;
    private int $landlordId;
    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        // The endpoint is now authenticated and answers only about a listing the
        // requester OWNS. Seed one owned auction per type (auto-increment ids, so
        // they never collide with existing data) and reference those ids below.
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // Avoid edge-throttle flakiness across the many requests in this suite;
        // the controller's own rate limiter and the auth middleware remain active.
        // CSRF is disabled for the test harness (production uses the blade @csrf
        // token); the new auth middleware stays active so 401 coverage holds.
        $this->withoutMiddleware([ThrottleRequests::class, VerifyCsrfToken::class]);

        $this->sellerId   = $this->ownedAuctionId(SellerAgentAuction::class);
        $this->buyerId    = $this->ownedAuctionId(BuyerAgentAuction::class);
        $this->landlordId = $this->ownedAuctionId(LandlordAgentAuction::class);
        $this->tenantId   = $this->ownedAuctionId(TenantAgentAuction::class);
    }

    private function ownedAuctionId(string $modelClass): int
    {
        return $modelClass::forceCreate([
            'user_id'  => $this->user->id,
            'title'    => 'Owned listing',
            'is_draft' => true,
        ])->id;
    }

    private function makeReadyResult(array $overrides = []): array
    {
        return array_merge([
            'success'        => true,
            'status'         => 'ready',
            'classification' => ['question_type' => 'property_standout'],
            'context'        => ['listing' => []],
            'contract'       => ['rules' => []],
            'prompt_package' => ['prompt' => 'test'],
            'adapter_result' => ['raw' => 'ok'],
            'final_response' => [
                'success'             => true,
                'status'              => 'ready',
                'answer'              => 'The HOA fee is $250/month.',
                'refusal_message'     => null,
                'disclosures'         => 'AI-generated. Verify independently.',
                'source_attribution'  => 'Listing data only.',
                'error'               => null,
                'follow_up_questions' => [
                    ['label' => 'Who would find this listing a practical fit?', 'question' => 'Who would find this listing a practical fit?', 'question_type' => 'suited_audience'],
                    ['label' => 'How does this listing compare to what a typical buyer or tenant seeks?', 'question' => 'How does this listing compare to what a typical buyer or tenant seeks?', 'question_type' => 'buyer_tenant_match'],
                    ['label' => 'What are the strongest marketing angles for this listing?', 'question' => 'What are the strongest marketing angles for this listing?', 'question_type' => 'marketing_angles'],
                ],
            ],
            'error'          => null,
        ], $overrides);
    }

    private function makeBlockedResult(): array
    {
        return [
            'success'        => false,
            'status'         => 'blocked',
            'classification' => ['question_type' => 'prohibited'],
            'context'        => null,
            'contract'       => null,
            'prompt_package' => null,
            'adapter_result' => null,
            'final_response' => [
                'success'             => false,
                'status'              => 'blocked',
                'answer'              => null,
                'refusal_message'     => 'That question cannot be answered.',
                'disclosures'         => null,
                'source_attribution'  => null,
                'error'               => null,
                'follow_up_questions' => [],
            ],
            'error'          => null,
        ];
    }

    private function makeFailedResult(): array
    {
        return [
            'success'        => false,
            'status'         => 'failed',
            'classification' => null,
            'context'        => null,
            'contract'       => null,
            'prompt_package' => null,
            'adapter_result' => null,
            'final_response' => null,
            'error'          => 'Internal runner returned no prompt_package.',
        ];
    }

    /**
     * (A) Valid POST calls service and returns safe JSON.
     */
    public function test_valid_post_calls_service_and_returns_safe_json(): void
    {
        // The controller verifies ownership, then hands the runner an 'owner'
        // viewer scope + requester id so downstream redaction is a no-op for the
        // authenticated owner. Assert those options reach the runner verbatim.
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->expects($this->once())
            ->method('run')
            ->with('seller', $this->sellerId, 'What are the HOA fees?', [
                'viewer_scope'      => AskAiViewerAuthorizationService::SCOPE_OWNER,
                'requester_user_id' => $this->user->id,
            ])
            ->willReturn($this->makeReadyResult());

        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->postJson('/ask-ai/listing-question', [
            'listing_type' => 'seller',
            'listing_id'   => $this->sellerId,
            'question'     => 'What are the HOA fees?',
        ]);

        $response->assertOk()->assertJsonStructure([
            'success', 'status', 'answer', 'refusal_message',
            'disclosures', 'source_attribution', 'error', 'follow_up_questions',
        ]);
    }

    /**
     * (A2/WF-1) Regression: after ownership passes, the owner reaches the runner
     * (no fatal), receives a grounded 200 answer, and the runner is invoked with
     * viewer_scope = SCOPE_OWNER. Guards the missing-import fatal that previously
     * turned every owner request into a swallowed soft-failure.
     */
    public function test_owner_reaches_runner_with_owner_scope_and_gets_answer(): void
    {
        $captured = null;
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->expects($this->once())
            ->method('run')
            ->willReturnCallback(function ($type, $id, $question, $options) use (&$captured) {
                $captured = $options;
                return $this->makeReadyResult();
            });
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->postJson('/ask-ai/listing-question', [
            'listing_type' => 'seller',
            'listing_id'   => $this->sellerId,
            'question'     => 'What are the HOA fees?',
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'status'  => 'ready',
            'answer'  => 'The HOA fee is $250/month.',
        ]);

        $this->assertNotNull($captured, 'The runner was not reached.');
        $this->assertSame(AskAiViewerAuthorizationService::SCOPE_OWNER, $captured['viewer_scope']);
        $this->assertSame($this->user->id, $captured['requester_user_id']);
    }

    /**
     * (B) Response never contains forbidden fields.
     */
    public function test_response_never_contains_forbidden_fields(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($this->makeReadyResult());
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->postJson('/ask-ai/listing-question', [
            'listing_type' => 'buyer',
            'listing_id'   => $this->buyerId,
            'question'     => 'What is the max budget?',
        ]);

        $data = $response->json();

        $this->assertArrayNotHasKey('prompt_package',  $data);
        $this->assertArrayNotHasKey('adapter_result',  $data);
        $this->assertArrayNotHasKey('raw_response',    $data);
        $this->assertArrayNotHasKey('context',         $data);
        $this->assertArrayNotHasKey('contract',        $data);
        $this->assertArrayNotHasKey('classification',  $data);
    }

    /**
     * (C) status=ready includes answer/disclosures/source_attribution.
     */
    public function test_ready_status_includes_answer_disclosures_source_attribution(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($this->makeReadyResult());
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->postJson('/ask-ai/listing-question', [
            'listing_type' => 'landlord',
            'listing_id'   => $this->landlordId,
            'question'     => 'Are pets allowed?',
        ]);

        $response->assertOk()->assertJson([
            'success'            => true,
            'status'             => 'ready',
            'answer'             => 'The HOA fee is $250/month.',
            // Production contract: the controller normalizes disclosures to an
            // array and always includes the educational disclaimer.
            'disclosures'        => [AskAiComplianceGuardrailService::EDUCATIONAL_DISCLAIMER],
            'source_attribution' => 'Listing data only.',
            'error'              => null,
        ]);
    }

    /**
     * (C2) status=ready includes follow_up_questions key as an array.
     */
    public function test_ready_status_includes_follow_up_questions_key(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($this->makeReadyResult());
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->postJson('/ask-ai/listing-question', [
            'listing_type' => 'seller',
            'listing_id'   => $this->sellerId,
            'question'     => 'What are the key features?',
        ]);

        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('follow_up_questions', $data);
        $this->assertIsArray($data['follow_up_questions']);
    }

    /**
     * (C3) status=ready with a recognised question_type returns populated follow_up_questions.
     */
    public function test_ready_status_with_recognised_type_returns_populated_follow_ups(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($this->makeReadyResult([
            'classification' => ['question_type' => 'property_standout'],
        ]));
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->postJson('/ask-ai/listing-question', [
            'listing_type' => 'seller',
            'listing_id'   => $this->sellerId,
            'question'     => 'What are the standout features?',
        ]);

        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('follow_up_questions', $data);
        $this->assertIsArray($data['follow_up_questions']);
        $this->assertNotEmpty($data['follow_up_questions'], 'follow_up_questions should be populated for ready status with a recognised question_type');
        $this->assertLessThanOrEqual(3, count($data['follow_up_questions']));

        foreach ($data['follow_up_questions'] as $item) {
            $this->assertArrayHasKey('label',         $item);
            $this->assertArrayHasKey('question',      $item);
            $this->assertArrayHasKey('question_type', $item);
        }
    }

    /**
     * (D) status=blocked returns refusal_message and empty follow_up_questions.
     */
    public function test_blocked_status_returns_refusal_message(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($this->makeBlockedResult());
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->postJson('/ask-ai/listing-question', [
            'listing_type' => 'tenant',
            'listing_id'   => $this->tenantId,
            'question'     => 'Is the owner of this property a minority?',
        ]);

        $response->assertOk()->assertJson([
            'success'         => false,
            'status'          => 'blocked',
            'answer'          => null,
            'refusal_message' => 'That question cannot be answered.',
        ]);

        $data = $response->json();
        $this->assertArrayHasKey('follow_up_questions', $data);
        $this->assertIsArray($data['follow_up_questions']);
        $this->assertEmpty($data['follow_up_questions'], 'blocked status must return empty follow_up_questions');
    }

    /**
     * (E) status=failed returns generic public error message, never internal details.
     *     follow_up_questions must be [] on failed status.
     */
    public function test_failed_status_returns_generic_public_error(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($this->makeFailedResult());
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->postJson('/ask-ai/listing-question', [
            'listing_type' => 'seller',
            'listing_id'   => $this->sellerId,
            'question'     => 'What is the price?',
        ]);

        $response->assertOk();
        $data = $response->json();

        $this->assertSame('failed', $data['status']);
        $this->assertSame(false, $data['success']);
        $this->assertSame(
            'Ask AI could not generate a response right now. Please try again later.',
            $data['error']
        );
        $this->assertStringNotContainsString('prompt_package', $data['error'] ?? '');
        $this->assertStringNotContainsString('Internal runner', $data['error'] ?? '');
        $this->assertArrayHasKey('follow_up_questions', $data);
        $this->assertIsArray($data['follow_up_questions']);
        $this->assertEmpty($data['follow_up_questions'], 'failed status must return empty follow_up_questions');
    }

    /**
     * (F) Missing question fails validation with 422.
     */
    public function test_missing_question_fails_validation(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->expects($this->never())->method('run');
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->postJson('/ask-ai/listing-question', [
            'listing_type' => 'seller',
            'listing_id'   => $this->sellerId,
        ]);

        $response->assertUnprocessable()
                 ->assertJsonValidationErrors(['question']);
    }

    /**
     * (F) Missing listing_id fails validation with 422.
     */
    public function test_missing_listing_id_fails_validation(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->expects($this->never())->method('run');
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->postJson('/ask-ai/listing-question', [
            'listing_type' => 'seller',
            'question'     => 'What is the asking price?',
        ]);

        $response->assertUnprocessable()
                 ->assertJsonValidationErrors(['listing_id']);
    }

    /**
     * (F) Question exceeding 1000 chars fails validation.
     */
    public function test_question_over_1000_chars_fails_validation(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->expects($this->never())->method('run');
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->postJson('/ask-ai/listing-question', [
            'listing_type' => 'seller',
            'listing_id'   => $this->sellerId,
            'question'     => str_repeat('a', 1001),
        ]);

        $response->assertUnprocessable()
                 ->assertJsonValidationErrors(['question']);
    }

    /**
     * (H) status=unsupported returns the safe answer message, not a generic failure.
     */
    public function test_unsupported_status_returns_safe_answer_message(): void
    {
        $result = array_merge($this->makeReadyResult(), [
            'success' => false,
            'status'  => 'unsupported',
            'final_response' => [
                'success'            => false,
                'status'             => 'unsupported',
                'answer'             => 'This type of question is not supported.',
                'refusal_message'    => null,
                'disclosures'        => null,
                'source_attribution' => null,
                'error'              => null,
            ],
        ]);

        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($result);
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->postJson('/ask-ai/listing-question', [
            'listing_type' => 'seller',
            'listing_id'   => $this->sellerId,
            'question'     => 'What is the seller\'s SSN?',
        ]);

        $response->assertOk()->assertJson([
            'status'  => 'unsupported',
            'success' => false,
            'answer'  => 'This type of question is not supported.',
            'error'   => null,
        ]);

        $data = $response->json();
        $this->assertArrayNotHasKey('prompt_package', $data);
        $this->assertArrayNotHasKey('classification',  $data);
        $this->assertArrayHasKey('follow_up_questions', $data);
        $this->assertIsArray($data['follow_up_questions']);
        $this->assertEmpty($data['follow_up_questions'], 'unsupported status must return empty follow_up_questions');
    }

    /**
     * (H) status=insufficient_context returns the safe answer message, not a generic failure.
     */
    public function test_insufficient_context_status_returns_safe_answer_message(): void
    {
        $result = array_merge($this->makeReadyResult(), [
            'success' => false,
            'status'  => 'insufficient_context',
            'final_response' => [
                'success'            => false,
                'status'             => 'insufficient_context',
                'answer'             => 'There isn\'t enough information in this listing to answer that question.',
                'refusal_message'    => null,
                'disclosures'        => 'AI-generated. Verify independently.',
                'source_attribution' => null,
                'error'              => null,
            ],
        ]);

        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($result);
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->postJson('/ask-ai/listing-question', [
            'listing_type' => 'landlord',
            'listing_id'   => $this->landlordId,
            'question'     => 'What is the exact square footage of the garage?',
        ]);

        $response->assertOk()->assertJson([
            'status'      => 'insufficient_context',
            'success'     => false,
            'answer'      => 'There isn\'t enough information in this listing to answer that question.',
            // Production contract: disclosures is an array containing the disclaimer.
            'disclosures' => [AskAiComplianceGuardrailService::EDUCATIONAL_DISCLAIMER],
            'error'       => null,
        ]);

        $data = $response->json();
        $this->assertArrayNotHasKey('prompt_package', $data);
        $this->assertArrayNotHasKey('context',        $data);
        $this->assertArrayHasKey('follow_up_questions', $data);
        $this->assertIsArray($data['follow_up_questions']);
        $this->assertEmpty($data['follow_up_questions'], 'insufficient_context status must return empty follow_up_questions');
    }

    /**
     * (I) source_attribution with structured sources array is exposed in public JSON.
     */
    public function test_source_attribution_with_sources_array_is_exposed_in_public_response(): void
    {
        $structuredAttribution = [
            'sources'          => [
                ['key' => 'property_intelligence', 'label' => 'Property Intelligence', 'version' => 'PROPERTY_INTELLIGENCE_V1'],
                ['key' => 'location_intelligence',  'label' => 'Location Intelligence',  'version' => 'LIFESTYLE_V1'],
            ],
            'required_sources' => ['property_intelligence', 'location_intelligence'],
            'versions'         => [
                'property_intelligence_version' => 'PROPERTY_INTELLIGENCE_V1',
                'ask_ai_context'                => 'ASK_AI_CONTEXT_V1',
                'contract_version'              => 'ASK_AI_RESPONSE_CONTRACT_V1',
            ],
        ];

        $result = $this->makeReadyResult([
            'final_response' => [
                'success'            => true,
                'status'             => 'ready',
                'answer'             => 'This property has great intelligence coverage.',
                'refusal_message'    => null,
                'disclosures'        => 'AI-generated. Verify independently.',
                'source_attribution' => $structuredAttribution,
                'error'              => null,
            ],
        ]);

        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($result);
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->postJson('/ask-ai/listing-question', [
            'listing_type' => 'seller',
            'listing_id'   => $this->sellerId,
            'question'     => 'What makes this property stand out?',
        ]);

        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('source_attribution', $data);
        $attribution = $data['source_attribution'];
        $this->assertIsArray($attribution);
        $this->assertArrayHasKey('sources', $attribution);
        $this->assertCount(2, $attribution['sources']);

        $firstSource = $attribution['sources'][0];
        $this->assertSame('property_intelligence', $firstSource['key']);
        $this->assertSame('Property Intelligence', $firstSource['label']);
        $this->assertSame('PROPERTY_INTELLIGENCE_V1', $firstSource['version']);

        $secondSource = $attribution['sources'][1];
        $this->assertSame('location_intelligence', $secondSource['key']);
        $this->assertSame('Location Intelligence', $secondSource['label']);
        $this->assertSame('LIFESTYLE_V1', $secondSource['version']);
    }

    /**
     * (I) Internal fields never leak regardless of source_attribution shape.
     */
    public function test_internal_fields_never_leak_with_structured_source_attribution(): void
    {
        $result = $this->makeReadyResult([
            'final_response' => [
                'success'            => true,
                'status'             => 'ready',
                'answer'             => 'Detailed answer here.',
                'refusal_message'    => null,
                'disclosures'        => 'AI-generated.',
                'source_attribution' => [
                    'sources'          => [
                        ['key' => 'property_intelligence', 'label' => 'Property Intelligence', 'version' => 'PROPERTY_INTELLIGENCE_V1'],
                    ],
                    'required_sources' => ['property_intelligence'],
                    'versions'         => ['property_intelligence_version' => 'PROPERTY_INTELLIGENCE_V1'],
                ],
                'error'              => null,
            ],
        ]);

        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($result);
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->postJson('/ask-ai/listing-question', [
            'listing_type' => 'seller',
            'listing_id'   => $this->sellerId,
            'question'     => 'What is the listing price?',
        ]);

        $data = $response->json();

        $this->assertArrayNotHasKey('prompt_package',  $data);
        $this->assertArrayNotHasKey('raw_response',    $data);
        $this->assertArrayNotHasKey('context',         $data);
        $this->assertArrayNotHasKey('contract',        $data);
        $this->assertArrayNotHasKey('classification',  $data);
        $this->assertArrayNotHasKey('adapter_result',  $data);
    }

    /**
     * Throwable from service returns generic public error, not internal message.
     */
    public function test_service_exception_returns_generic_public_error(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willThrowException(new \RuntimeException('Secret internal error'));
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->postJson('/ask-ai/listing-question', [
            'listing_type' => 'seller',
            'listing_id'   => $this->sellerId,
            'question'     => 'Any question?',
        ]);

        $response->assertOk();
        $data = $response->json();

        $this->assertSame('failed', $data['status']);
        $this->assertStringNotContainsString('Secret internal error', $data['error'] ?? '');
        $this->assertSame(
            'Ask AI could not generate a response right now. Please try again later.',
            $data['error']
        );
        $this->assertArrayHasKey('follow_up_questions', $data);
        $this->assertIsArray($data['follow_up_questions']);
        $this->assertEmpty($data['follow_up_questions'], 'exception path must return empty follow_up_questions');
    }

    // ── C2 — authentication & object-level authorization ──────────────────────

    /**
     * (C2) Unauthenticated requests are rejected (route is behind auth).
     */
    public function test_unauthenticated_request_is_rejected(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->expects($this->never())->method('run');
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        auth()->logout();

        $response = $this->postJson('/ask-ai/listing-question', [
            'listing_type' => 'seller',
            'listing_id'   => $this->sellerId,
            'question'     => 'What are the HOA fees?',
        ]);

        $response->assertUnauthorized(); // 401
    }

    /**
     * (C2) An authenticated user cannot ask about a listing they do not own.
     */
    public function test_non_owner_is_forbidden(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->expects($this->never())->method('run');
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        // A listing owned by a DIFFERENT user — the acting user must not reach it.
        $victim = User::factory()->create();
        $victimListingId = SellerAgentAuction::forceCreate([
            'user_id'  => $victim->id,
            'title'    => 'Victim listing',
            'is_draft' => true,
        ])->id;

        $response = $this->postJson('/ask-ai/listing-question', [
            'listing_type' => 'seller',
            'listing_id'   => $victimListingId,
            'question'     => 'What is the income requirement?',
        ]);

        $response->assertForbidden(); // 403
        $this->assertSame('forbidden', $response->json('status'));
    }

    /**
     * (C2) An unknown / unsupported listing type is denied (no MLS support exists).
     */
    public function test_unknown_listing_type_is_forbidden(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->expects($this->never())->method('run');
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->postJson('/ask-ai/listing-question', [
            'listing_type' => 'bridge',
            'listing_id'   => 1,
            'question'     => 'Tell me about this MLS property.',
        ]);

        $response->assertForbidden(); // 403
    }
}
