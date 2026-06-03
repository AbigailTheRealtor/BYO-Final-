<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\AskAi\AskAiRunnerV2Service;
use Illuminate\Foundation\Testing\WithFaker;

class AskAiListingQuestionTest extends TestCase
{
    use WithFaker;

    private function makeReadyResult(array $overrides = []): array
    {
        return array_merge([
            'success'        => true,
            'status'         => 'ready',
            'classification' => ['question_type' => 'factual'],
            'context'        => ['listing' => []],
            'contract'       => ['rules' => []],
            'prompt_package' => ['prompt' => 'test'],
            'adapter_result' => ['raw' => 'ok'],
            'final_response' => [
                'success'            => true,
                'status'             => 'ready',
                'answer'             => 'The HOA fee is $250/month.',
                'refusal_message'    => null,
                'disclosures'        => 'AI-generated. Verify independently.',
                'source_attribution' => 'Listing data only.',
                'error'              => null,
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
                'success'            => false,
                'status'             => 'blocked',
                'answer'             => null,
                'refusal_message'    => 'That question cannot be answered.',
                'disclosures'        => null,
                'source_attribution' => null,
                'error'              => null,
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
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->expects($this->once())
            ->method('run')
            ->with('seller', 42, 'What are the HOA fees?', [])
            ->willReturn($this->makeReadyResult());

        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->postJson('/ask-ai/listing-question', [
            'listing_type' => 'seller',
            'listing_id'   => 42,
            'question'     => 'What are the HOA fees?',
        ]);

        $response->assertOk()->assertJsonStructure([
            'success', 'status', 'answer', 'refusal_message',
            'disclosures', 'source_attribution', 'error',
        ]);
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
            'listing_id'   => 1,
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
            'listing_id'   => 7,
            'question'     => 'Are pets allowed?',
        ]);

        $response->assertOk()->assertJson([
            'success'            => true,
            'status'             => 'ready',
            'answer'             => 'The HOA fee is $250/month.',
            'disclosures'        => 'AI-generated. Verify independently.',
            'source_attribution' => 'Listing data only.',
            'error'              => null,
        ]);
    }

    /**
     * (D) status=blocked returns refusal_message.
     */
    public function test_blocked_status_returns_refusal_message(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($this->makeBlockedResult());
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->postJson('/ask-ai/listing-question', [
            'listing_type' => 'tenant',
            'listing_id'   => 5,
            'question'     => 'Is the owner of this property a minority?',
        ]);

        $response->assertOk()->assertJson([
            'success'         => false,
            'status'          => 'blocked',
            'answer'          => null,
            'refusal_message' => 'That question cannot be answered.',
        ]);
    }

    /**
     * (E) status=failed returns generic public error message, never internal details.
     */
    public function test_failed_status_returns_generic_public_error(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($this->makeFailedResult());
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->postJson('/ask-ai/listing-question', [
            'listing_type' => 'seller',
            'listing_id'   => 3,
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
            'listing_id'   => 1,
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
            'listing_id'   => 1,
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
            'listing_id'   => 1,
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
            'listing_id'   => 9,
            'question'     => 'What is the exact square footage of the garage?',
        ]);

        $response->assertOk()->assertJson([
            'status'      => 'insufficient_context',
            'success'     => false,
            'answer'      => 'There isn\'t enough information in this listing to answer that question.',
            'disclosures' => 'AI-generated. Verify independently.',
            'error'       => null,
        ]);

        $data = $response->json();
        $this->assertArrayNotHasKey('prompt_package', $data);
        $this->assertArrayNotHasKey('context',        $data);
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
            'listing_id'   => 1,
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
    }
}
