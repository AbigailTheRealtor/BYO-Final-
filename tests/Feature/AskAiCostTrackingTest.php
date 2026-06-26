<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\AskAiUsageLog;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Http\Middleware\VerifyCsrfToken;
use App\Services\AskAi\AskAiRunnerV2Service;
use App\Services\AskAi\AskAiUsageLoggerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Config;

class AskAiCostTrackingTest extends TestCase
{
    use RefreshDatabase;

    private int $listingId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->actingAs($user);
        $this->withoutMiddleware([ThrottleRequests::class, VerifyCsrfToken::class]);

        $this->listingId = SellerAgentAuction::forceCreate([
            'user_id'  => $user->id,
            'title'    => 'Owned listing',
            'is_draft' => true,
        ])->id;
    }

    private function makeReadyResultWithTokens(array $tokenOverrides = [], array $overrides = []): array
    {
        $adapterResult = array_merge([
            'success'           => true,
            'status'            => 'generated',
            'raw_response'      => '{"answer":"test"}',
            'model'             => 'gpt-4o',
            'error'             => null,
            'prompt_tokens'     => 100,
            'completion_tokens' => 50,
            'total_tokens'      => 150,
            'api_request_id'    => 'req_abc123',
        ], $tokenOverrides);

        return array_merge([
            'success'        => true,
            'status'         => 'ready',
            'classification' => ['question_type' => 'factual'],
            'context'        => ['listing' => []],
            'contract'       => ['rules' => []],
            'prompt_package' => ['prompt' => 'test'],
            'adapter_result' => $adapterResult,
            'final_response' => [
                'success'            => true,
                'status'             => 'ready',
                'answer'             => 'Test answer.',
                'refusal_message'    => null,
                'disclosures'        => 'AI-generated.',
                'source_attribution' => 'Listing data only.',
                'error'              => null,
            ],
            'error' => null,
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
            'adapter_result' => [
                'success'           => false,
                'status'            => 'blocked',
                'raw_response'      => null,
                'model'             => null,
                'error'             => null,
                'prompt_tokens'     => 0,
                'completion_tokens' => 0,
                'total_tokens'      => 0,
                'api_request_id'    => null,
            ],
            'final_response' => [
                'success'            => false,
                'status'             => 'blocked',
                'answer'             => null,
                'refusal_message'    => 'That question cannot be answered.',
                'disclosures'        => null,
                'source_attribution' => null,
                'error'              => null,
            ],
            'error' => null,
        ];
    }

    private function postQuestion(array $overrides = [])
    {
        return $this->postJson('/ask-ai/listing-question', array_merge([
            'listing_type' => 'seller',
            'listing_id'   => $this->listingId,
            'question'     => 'What are the HOA fees?',
        ], $overrides));
    }

    /**
     * (1) Token fields are stored when the adapter result provides them.
     */
    public function test_token_fields_are_stored_from_adapter_result(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($this->makeReadyResultWithTokens([
            'prompt_tokens'     => 200,
            'completion_tokens' => 80,
            'total_tokens'      => 280,
            'api_request_id'    => 'req_xyz999',
        ]));
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $this->postQuestion()->assertOk();

        $log = AskAiUsageLog::latest('id')->first();
        $this->assertSame(200, (int) $log->prompt_tokens);
        $this->assertSame(80,  (int) $log->completion_tokens);
        $this->assertSame(280, (int) $log->total_tokens);
        $this->assertSame('req_xyz999', $log->api_request_id);
    }

    /**
     * (2) estimated_cost_usd is correctly calculated for a known model with known token counts.
     */
    public function test_estimated_cost_usd_is_calculated_correctly_for_known_model(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($this->makeReadyResultWithTokens([
            'model'             => 'gpt-4o',
            'prompt_tokens'     => 1000,
            'completion_tokens' => 500,
            'total_tokens'      => 1500,
        ]));
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $this->postQuestion()->assertOk();

        $log = AskAiUsageLog::latest('id')->first();

        // gpt-4o: prompt $0.005/1k, completion $0.015/1k
        // (1000/1000 * 0.005) + (500/1000 * 0.015) = 0.005 + 0.0075 = 0.0125
        $this->assertNotNull($log->estimated_cost_usd);
        $this->assertEqualsWithDelta(0.0125, (float) $log->estimated_cost_usd, 0.000001);
    }

    /**
     * (3) When model is not in the rate table, estimated_cost_usd is stored as null (not an error).
     */
    public function test_unknown_model_stores_null_cost_without_error(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($this->makeReadyResultWithTokens([
            'model'             => 'gpt-99-unknown',
            'prompt_tokens'     => 100,
            'completion_tokens' => 50,
            'total_tokens'      => 150,
        ]));
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->postQuestion();

        $response->assertOk();
        $this->assertTrue($response->json('success'));

        $log = AskAiUsageLog::latest('id')->first();
        $this->assertNull($log->estimated_cost_usd);
        $this->assertSame(100, (int) $log->prompt_tokens);
    }

    /**
     * (4) Blocked/refused requests store 0 for all token fields.
     */
    public function test_blocked_request_stores_zero_for_all_token_fields(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($this->makeBlockedResult());
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $this->postQuestion()->assertOk();

        $log = AskAiUsageLog::latest('id')->first();
        $this->assertSame(0, (int) $log->prompt_tokens);
        $this->assertSame(0, (int) $log->completion_tokens);
        $this->assertSame(0, (int) $log->total_tokens);
        $this->assertSame('blocked', $log->status);
    }

    /**
     * (5) Log row contains no prompt, raw_response, answer, context, or prompt_package column.
     */
    public function test_log_row_contains_no_sensitive_content_columns(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($this->makeReadyResultWithTokens());
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $this->postQuestion()->assertOk();

        $log     = AskAiUsageLog::latest('id')->first();
        $columns = array_keys($log->getAttributes());

        foreach (['prompt', 'raw_response', 'answer', 'context', 'prompt_package'] as $prohibited) {
            $this->assertNotContains($prohibited, $columns, "Column '{$prohibited}' must not be stored in the log row.");
        }
    }

    /**
     * (6) A cost calculation exception (via bad config) does not break the JSON response.
     */
    public function test_cost_calculation_exception_does_not_break_json_response(): void
    {
        Config::set('ai.ask_ai_costs', null);

        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($this->makeReadyResultWithTokens([
            'model'             => 'gpt-4o',
            'prompt_tokens'     => 100,
            'completion_tokens' => 50,
            'total_tokens'      => 150,
        ]));
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->postQuestion();

        $response->assertOk()->assertJsonStructure([
            'success', 'status', 'answer', 'refusal_message',
            'disclosures', 'source_attribution', 'error',
        ]);

        $this->assertTrue($response->json('success'));
    }
}
