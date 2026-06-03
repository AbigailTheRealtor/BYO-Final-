<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\AskAiUsageLog;
use App\Services\AskAi\AskAiRunnerV2Service;
use App\Services\AskAi\AskAiUsageLoggerService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AskAiUsageLoggingTest extends TestCase
{
    use RefreshDatabase;

    private function makeReadyResult(array $overrides = []): array
    {
        return array_merge([
            'success'        => true,
            'status'         => 'ready',
            'classification' => ['question_type' => 'factual'],
            'context'        => ['listing' => []],
            'contract'       => ['rules' => []],
            'prompt_package' => ['prompt' => 'test'],
            'adapter_result' => ['model' => 'gpt-4o', 'raw_response' => 'ok'],
            'final_response' => [
                'success'            => true,
                'status'             => 'ready',
                'answer'             => 'The HOA fee is $250/month.',
                'refusal_message'    => null,
                'disclosures'        => 'AI-generated. Verify independently.',
                'source_attribution' => 'Listing data only.',
                'error'              => null,
            ],
            'error' => null,
        ], $overrides);
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
            'error'          => 'Internal error.',
        ];
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
            'listing_id'   => 42,
            'question'     => 'What are the HOA fees?',
        ], $overrides));
    }

    /**
     * (1) Successful public request creates exactly one usage log row.
     */
    public function test_successful_request_creates_exactly_one_usage_log_row(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($this->makeReadyResult());
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $countBefore = AskAiUsageLog::count();

        $response = $this->postQuestion();

        $response->assertOk();
        $this->assertSame($countBefore + 1, AskAiUsageLog::count());

        $log = AskAiUsageLog::latest('id')->first();
        $this->assertSame('seller', $log->listing_type);
        $this->assertSame(42, (int) $log->listing_id);
        $this->assertSame('ready', $log->status);
        $this->assertTrue((bool) $log->success);
        $this->assertSame('gpt-4o', $log->model);
        $this->assertSame('factual', $log->question_type);
        $this->assertNotNull($log->response_time_ms);
        $this->assertGreaterThanOrEqual(0, $log->response_time_ms);
    }

    /**
     * (2) Failed runner result creates one log row with status = 'failed'.
     */
    public function test_failed_runner_result_creates_one_log_row_with_failed_status(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($this->makeFailedResult());
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $countBefore = AskAiUsageLog::count();

        $response = $this->postQuestion();

        $response->assertOk();
        $this->assertSame($countBefore + 1, AskAiUsageLog::count());

        $log = AskAiUsageLog::latest('id')->first();
        $this->assertSame('failed', $log->status);
        $this->assertFalse((bool) $log->success);
        $this->assertSame('failed', $log->error_code);
    }

    /**
     * (3) Blocked result creates one log row with status = 'blocked'.
     */
    public function test_blocked_result_creates_one_log_row_with_blocked_status(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($this->makeBlockedResult());
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $countBefore = AskAiUsageLog::count();

        $response = $this->postQuestion();

        $response->assertOk();
        $this->assertSame($countBefore + 1, AskAiUsageLog::count());

        $log = AskAiUsageLog::latest('id')->first();
        $this->assertSame('blocked', $log->status);
        $this->assertFalse((bool) $log->success);
        $this->assertSame('blocked', $log->error_code);
    }

    /**
     * (4) prompt_package is not stored in the log row.
     */
    public function test_prompt_package_is_not_stored_in_log_row(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($this->makeReadyResult());
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $this->postQuestion()->assertOk();

        $log = AskAiUsageLog::first();
        $columns = array_keys($log->getAttributes());
        $this->assertNotContains('prompt_package', $columns);
    }

    /**
     * (5) raw_response is not stored in the log row.
     */
    public function test_raw_response_is_not_stored_in_log_row(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($this->makeReadyResult());
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $this->postQuestion()->assertOk();

        $log = AskAiUsageLog::first();
        $columns = array_keys($log->getAttributes());
        $this->assertNotContains('raw_response', $columns);
    }

    /**
     * (6) context and contract are not stored in the log row.
     */
    public function test_context_and_contract_are_not_stored_in_log_row(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($this->makeReadyResult());
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $this->postQuestion()->assertOk();

        $log = AskAiUsageLog::first();
        $columns = array_keys($log->getAttributes());
        $this->assertNotContains('context', $columns);
        $this->assertNotContains('contract', $columns);
    }

    /**
     * (7) Full question text is not stored — question_hash is a 64-char hex string.
     */
    public function test_question_text_is_not_stored_and_hash_is_64_char_hex(): void
    {
        $question = 'What are the HOA fees?';

        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($this->makeReadyResult());
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $this->postQuestion(['question' => $question])->assertOk();

        $log = AskAiUsageLog::first();

        $this->assertNotNull($log->question_hash);
        $this->assertSame(64, strlen($log->question_hash));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $log->question_hash);
        $this->assertNotSame($question, $log->question_hash);

        $columns = array_keys($log->getAttributes());
        $this->assertNotContains('question', $columns);
    }

    /**
     * (8) A logger failure does not break the public JSON response.
     */
    public function test_logger_failure_does_not_break_public_json_response(): void
    {
        $runnerMock = $this->createMock(AskAiRunnerV2Service::class);
        $runnerMock->method('run')->willReturn($this->makeReadyResult());
        $this->app->instance(AskAiRunnerV2Service::class, $runnerMock);

        $loggerMock = $this->createMock(AskAiUsageLoggerService::class);
        $loggerMock->method('logListingQuestion')->willThrowException(new \RuntimeException('DB is down'));
        $this->app->instance(AskAiUsageLoggerService::class, $loggerMock);

        $response = $this->postQuestion();

        $response->assertOk()->assertJsonStructure([
            'success', 'status', 'answer', 'refusal_message',
            'disclosures', 'source_attribution', 'error',
        ]);

        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertSame('ready', $data['status']);
    }
}
