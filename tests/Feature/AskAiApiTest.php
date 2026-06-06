<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Services\AskAi\AskAiRunnerV2Service;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

class AskAiApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAnsweredResult(array $overrides = []): array
    {
        return array_merge([
            'success'        => true,
            'status'         => 'ready',
            'classification' => ['question_type' => 'listing_facts'],
            'context'        => ['listing' => []],
            'contract'       => ['rules' => []],
            'prompt_package' => ['prompt' => 'test'],
            'adapter_result' => ['raw' => 'ok'],
            'final_response' => [
                'success'             => true,
                'status'              => 'ready',
                'answer'              => 'This property has 4 bedrooms.',
                'refusal_message'     => null,
                'disclosures'         => 'AI-generated. Verify independently.',
                'source_attribution'  => ['sources' => []],
                'error'               => null,
                'follow_up_questions' => [
                    ['label' => 'What are the HOA fees?', 'question' => 'What are the HOA fees?', 'question_type' => 'listing_facts'],
                ],
            ],
            'error' => null,
        ], $overrides);
    }

    private function makeRunnerResult(string $runnerStatus, bool $success = false): array
    {
        $finalResponse = [
            'success'             => $success,
            'status'              => $runnerStatus,
            'answer'              => $success ? 'Some answer.' : null,
            'refusal_message'     => null,
            'disclosures'         => null,
            'source_attribution'  => null,
            'error'               => null,
            'follow_up_questions' => [],
        ];

        return [
            'success'        => $success,
            'status'         => $runnerStatus,
            'classification' => ['question_type' => 'listing_facts'],
            'context'        => null,
            'contract'       => null,
            'prompt_package' => null,
            'adapter_result' => null,
            'final_response' => ($runnerStatus === 'failed') ? null : $finalResponse,
            'error'          => ($runnerStatus === 'failed') ? 'Pipeline error.' : null,
        ];
    }

    private function mockRunner(array $result): AskAiRunnerV2Service
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($result);
        $this->app->instance(AskAiRunnerV2Service::class, $mock);
        return $mock;
    }

    private function webPayload(array $overrides = []): array
    {
        return array_merge([
            'listing_type' => 'seller',
            'listing_id'   => 42,
            'question'     => 'How many bedrooms does this have?',
            'channel'      => 'web',
        ], $overrides);
    }

    private function apiPayload(array $overrides = []): array
    {
        return array_merge([
            'listing_type' => 'seller',
            'listing_id'   => 42,
            'question'     => 'How many bedrooms does this have?',
            'channel'      => 'mobile',
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // (1) Valid web-route request returns `answered`
    // -------------------------------------------------------------------------

    public function test_valid_web_route_request_returns_answered(): void
    {
        $this->mockRunner($this->makeAnsweredResult());

        $response = $this->postJson('/ask-ai/ask', $this->webPayload());

        $response->assertOk()->assertJson([
            'success'          => true,
            'status'           => 'answered',
            'answer_text'      => 'This property has 4 bedrooms.',
            'question_type'    => 'listing_facts',
            'error'            => null,
            'contract_version' => 'ASK_AI_API_V1',
        ]);

        $data = $response->json();
        $this->assertArrayHasKey('follow_up_questions', $data);
        $this->assertIsArray($data['follow_up_questions']);
        $this->assertArrayHasKey('disclosures',  $data);
        $this->assertArrayHasKey('attribution',  $data);
    }

    // -------------------------------------------------------------------------
    // (2) Valid API-route request returns `answered`
    // -------------------------------------------------------------------------

    public function test_valid_api_route_request_returns_answered(): void
    {
        $this->mockRunner($this->makeAnsweredResult());

        $user = User::factory()->create(['user_type' => 'buyer']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ask-ai/ask', $this->apiPayload());

        $response->assertOk()->assertJson([
            'success'          => true,
            'status'           => 'answered',
            'answer_text'      => 'This property has 4 bedrooms.',
            'question_type'    => 'listing_facts',
            'error'            => null,
            'contract_version' => 'ASK_AI_API_V1',
        ]);
    }

    // -------------------------------------------------------------------------
    // (3) Missing listing_id returns 422 — web route
    // -------------------------------------------------------------------------

    public function test_missing_listing_id_returns_422_on_web_route(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->expects($this->never())->method('run');
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->postJson('/ask-ai/ask', [
            'listing_type' => 'seller',
            'question'     => 'What is the price?',
            'channel'      => 'web',
        ]);

        $response->assertUnprocessable()
                 ->assertJsonValidationErrors(['listing_id']);
    }

    // -------------------------------------------------------------------------
    // (4) Missing listing_id returns 422 — API route
    // -------------------------------------------------------------------------

    public function test_missing_listing_id_returns_422_on_api_route(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->expects($this->never())->method('run');
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $user = User::factory()->create(['user_type' => 'buyer']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ask-ai/ask', [
            'listing_type' => 'seller',
            'question'     => 'What is the price?',
            'channel'      => 'mobile',
        ]);

        $response->assertUnprocessable()
                 ->assertJsonValidationErrors(['listing_id']);
    }

    // -------------------------------------------------------------------------
    // (5) Rate limit exceeded returns 429
    // -------------------------------------------------------------------------

    public function test_rate_limit_exceeded_returns_429(): void
    {
        $this->mockRunner($this->makeAnsweredResult());

        config(['ask_ai.rate_limit_per_minute' => 1]);

        $this->postJson('/ask-ai/ask', $this->webPayload())->assertOk();

        $this->postJson('/ask-ai/ask', $this->webPayload())->assertStatus(429);
    }

    // -------------------------------------------------------------------------
    // (6) Unauthenticated request to /api/ask-ai/ask returns 401
    // -------------------------------------------------------------------------

    public function test_unauthenticated_api_route_returns_401(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->expects($this->never())->method('run');
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->postJson('/api/ask-ai/ask', $this->apiPayload());

        $response->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // (7–11) Status mapping — all five runner output statuses
    // -------------------------------------------------------------------------

    public function test_runner_status_ready_maps_to_answered(): void
    {
        $this->mockRunner($this->makeAnsweredResult());

        $response = $this->postJson('/ask-ai/ask', $this->webPayload());

        $response->assertOk()->assertJson(['status' => 'answered', 'success' => true]);
    }

    public function test_runner_status_insufficient_context_maps_to_insufficient_context(): void
    {
        $this->mockRunner($this->makeRunnerResult('insufficient_context'));

        $response = $this->postJson('/ask-ai/ask', $this->webPayload());

        $response->assertOk()->assertJson(['status' => 'insufficient_context', 'success' => false]);
    }

    public function test_runner_status_blocked_maps_to_blocked(): void
    {
        $this->mockRunner($this->makeRunnerResult('blocked'));

        $response = $this->postJson('/ask-ai/ask', $this->webPayload());

        $response->assertOk()->assertJson(['status' => 'blocked', 'success' => false]);
    }

    public function test_runner_status_unsupported_maps_to_unsupported(): void
    {
        $this->mockRunner($this->makeRunnerResult('unsupported'));

        $response = $this->postJson('/ask-ai/ask', $this->webPayload());

        $response->assertOk()->assertJson(['status' => 'unsupported', 'success' => false]);
    }

    public function test_runner_status_failed_maps_to_failed(): void
    {
        $this->mockRunner($this->makeRunnerResult('failed'));

        $response = $this->postJson('/ask-ai/ask', $this->webPayload());

        $response->assertOk()->assertJson(['status' => 'failed', 'success' => false]);
    }

    // -------------------------------------------------------------------------
    // (12) Web route parity with existing pipeline output
    // -------------------------------------------------------------------------

    public function test_web_route_calls_runner_with_same_params_as_existing_pipeline(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->expects($this->once())
            ->method('run')
            ->with('seller', 42, 'How many bedrooms does this have?', [])
            ->willReturn($this->makeAnsweredResult());
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $this->postJson('/ask-ai/ask', $this->webPayload())->assertOk();
    }

    // -------------------------------------------------------------------------
    // (13) Internal pipeline fields never leak in API response
    // -------------------------------------------------------------------------

    public function test_internal_fields_never_leak_in_api_response(): void
    {
        $this->mockRunner($this->makeAnsweredResult());

        $response = $this->postJson('/ask-ai/ask', $this->webPayload());
        $data = $response->json();

        $this->assertArrayNotHasKey('prompt_package',  $data);
        $this->assertArrayNotHasKey('adapter_result',  $data);
        $this->assertArrayNotHasKey('context',         $data);
        $this->assertArrayNotHasKey('contract',        $data);
        $this->assertArrayNotHasKey('classification',  $data);
        $this->assertArrayNotHasKey('raw_response',    $data);
        $this->assertArrayNotHasKey('final_response',  $data);
    }

    // -------------------------------------------------------------------------
    // (16) Runner internal error text is never exposed in the API response
    // -------------------------------------------------------------------------

    public function test_runner_internal_error_string_never_exposed_in_response(): void
    {
        $result = $this->makeRunnerResult('failed');
        $result['error'] = 'Internal runner returned no prompt_package; OpenAI call skipped.';
        $this->mockRunner($result);

        $response = $this->postJson('/ask-ai/ask', $this->webPayload());
        $data = $response->json();

        $this->assertSame('failed', $data['status']);
        $this->assertNotSame(
            'Internal runner returned no prompt_package; OpenAI call skipped.',
            $data['error'],
            'Internal runner error message must never be returned to the client.'
        );
        $this->assertSame(
            'Ask AI could not generate a response right now. Please try again later.',
            $data['error']
        );
    }

    // -------------------------------------------------------------------------
    // (17) Thrown exception internal message is never exposed in the API response
    // -------------------------------------------------------------------------

    public function test_thrown_exception_message_never_exposed_in_response(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willThrowException(new \RuntimeException('Secret DB connection string or stack trace'));
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->postJson('/ask-ai/ask', $this->webPayload());
        $data = $response->json();

        $this->assertSame('failed', $data['status']);
        $this->assertStringNotContainsString('Secret DB', $data['error'] ?? '');
        $this->assertStringNotContainsString('stack trace', $data['error'] ?? '');
        $this->assertSame(
            'Ask AI could not generate a response right now. Please try again later.',
            $data['error']
        );
    }

    // -------------------------------------------------------------------------
    // (18) Non-failed statuses return null for the error field
    // -------------------------------------------------------------------------

    public function test_non_failed_status_returns_null_error(): void
    {
        $this->mockRunner($this->makeAnsweredResult());

        $response = $this->postJson('/ask-ai/ask', $this->webPayload());

        $response->assertOk()->assertJson(['status' => 'answered', 'error' => null]);
    }

    public function test_blocked_status_returns_null_error(): void
    {
        $this->mockRunner($this->makeRunnerResult('blocked'));

        $response = $this->postJson('/ask-ai/ask', $this->webPayload());

        $response->assertOk()->assertJson(['status' => 'blocked', 'error' => null]);
    }

    // -------------------------------------------------------------------------
    // (14) Contract version always present in response
    // -------------------------------------------------------------------------

    public function test_contract_version_always_present(): void
    {
        $this->mockRunner($this->makeAnsweredResult());

        $response = $this->postJson('/ask-ai/ask', $this->webPayload());

        $response->assertOk()->assertJson(['contract_version' => 'ASK_AI_API_V1']);
    }

    // -------------------------------------------------------------------------
    // (15) 429 response includes Retry-After header
    // -------------------------------------------------------------------------

    public function test_rate_limit_429_includes_retry_after_header(): void
    {
        $this->mockRunner($this->makeAnsweredResult());

        config(['ask_ai.rate_limit_per_minute' => 1]);

        $this->postJson('/ask-ai/ask', $this->webPayload())->assertOk();

        $response = $this->postJson('/ask-ai/ask', $this->webPayload());
        $response->assertStatus(429);
        $this->assertNotEmpty($response->headers->get('Retry-After'));
    }
}
