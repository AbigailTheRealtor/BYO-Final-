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
            'disclosures', 'source_attribution', 'error', 'follow_up_questions',
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
     * (C2) status=ready includes follow_up_questions key as an array.
     */
    public function test_ready_status_includes_follow_up_questions_key(): void
    {
        $mock = $this->createMock(AskAiRunnerV2Service::class);
        $mock->method('run')->willReturn($this->makeReadyResult());
        $this->app->instance(AskAiRunnerV2Service::class, $mock);

        $response = $this->postJson('/ask-ai/listing-question', [
            'listing_type' => 'seller',
            'listing_id'   => 1,
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
            'listing_id'   => 5,
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
            'listing_id'   => 5,
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
        $this->assertArrayHasKey('follow_up_questions', $data);
        $this->assertIsArray($data['follow_up_questions']);
        $this->assertEmpty($data['follow_up_questions'], 'insufficient_context status must return empty follow_up_questions');
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
        $this->assertArrayHasKey('follow_up_questions', $data);
        $this->assertIsArray($data['follow_up_questions']);
        $this->assertEmpty($data['follow_up_questions'], 'exception path must return empty follow_up_questions');
    }
}
