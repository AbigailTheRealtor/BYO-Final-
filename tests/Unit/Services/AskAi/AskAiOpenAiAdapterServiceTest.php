<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\Ai\OpenAiClientService;
use App\Services\AskAi\AskAiOpenAiAdapterService;
use PHPUnit\Framework\TestCase;

/**
 * AskAiOpenAiAdapterServiceTest
 *
 * Pure unit tests — no database, no Laravel TestCase, no DB traits.
 * OpenAiClientService is mocked via getMockBuilder.
 *
 * Test coverage (cases A–G):
 *   A. prompt_ready NEVER calls send(): LLM_ANSWERING_APPROVED is false, status='blocked'
 *   B. blocked status does not call send(), returns status='blocked'
 *   C. insufficient_context does not call send(), returns status='blocked'
 *   D. unsupported does not call send(), returns status='blocked'
 *   E/F. send() failures are unreachable behind the hard gate; the refusal names the gate
 *   G. Governance static grep: no hardcoded API keys, no DB writes, no routes in service file
 */
class AskAiOpenAiAdapterServiceTest extends TestCase
{
    private const REQUIRED_RESULT_KEYS = [
        'success',
        'status',
        'raw_response',
        'model',
        'error',
    ];

    /**
     * Absolute path to the adapter service file — derived without base_path() so this
     * works in a pure PHPUnit\Framework\TestCase (no Laravel container).
     */
    private function serviceFilePath(): string
    {
        return dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiOpenAiAdapterService.php';
    }

    /**
     * Build a mock for OpenAiClientService.
     *
     * @return OpenAiClientService&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeClientMock(): OpenAiClientService
    {
        return $this->getMockBuilder(OpenAiClientService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();
    }

    /**
     * Minimal prompt_ready package stub.
     */
    private function makePromptReadyPackage(array $overrides = []): array
    {
        return array_merge([
            'success'              => true,
            'status'               => 'prompt_ready',
            'prompt_package_version' => 'ASK_AI_PROMPT_PACKAGE_V1',
            'question'             => 'What makes this property stand out?',
            'question_type'        => 'property_standout',
            'system_instructions'  => ['You are an AI assistant for a real estate platform.'],
            'developer_instructions' => [],
            'allowed_context'      => ['property_intelligence' => ['property_highlights' => ['Pool']]],
            'source_attribution'   => ['required_sources' => ['property_intelligence'], 'versions' => []],
            'required_disclosures' => ['Information is derived from structured property data.'],
            'refusal_template'     => null,
            'missing_required_sources' => [],
            'context_versions'     => ['ask_ai_context' => 'ASK_AI_CONTEXT_V1'],
            'response_format'      => ['type' => 'structured_text'],
            'error'                => null,
        ], $overrides);
    }

    /**
     * Minimal successful send() return value stub.
     */
    private function makeSendResult(array $overrides = []): array
    {
        return array_merge([
            'data'           => ['answer' => 'This property has a pool and a garage.'],
            'model'          => 'gpt-5-2025-11-01',
            'prompt_version' => 'ASK_AI_PROMPT_PACKAGE_V1',
            'attempt_count'  => 1,
            'requested_at'   => '2026-06-02T10:00:00+00:00',
            'completed_at'   => '2026-06-02T10:00:02+00:00',
        ], $overrides);
    }

    // =========================================================================
    // Case A — prompt_ready calls send() once and returns status='generated'
    // =========================================================================

    public function test_case_A_prompt_ready_never_calls_send(): void
    {
        // LLM_ANSWERING_APPROVED is false: generate() refuses before the client is touched,
        // so the send() behaviour this case used to cover is unreachable by design.
        $clientMock = $this->makeClientMock();
        $service    = new AskAiOpenAiAdapterService($clientMock);

        $clientMock->expects($this->never())->method('send');

        $result = $service->generate($this->makePromptReadyPackage());

        $this->assertSame('blocked', $result['status']);
    }

    public function test_case_A_prompt_ready_returns_success_false(): void
    {
        // LLM_ANSWERING_APPROVED is false: generate() refuses before the client is touched,
        // so the send() behaviour this case used to cover is unreachable by design.
        $clientMock = $this->makeClientMock();
        $service    = new AskAiOpenAiAdapterService($clientMock);

        $clientMock->method('send')->willReturn($this->makeSendResult());

        $result = $service->generate($this->makePromptReadyPackage());

        $this->assertFalse($result['success']);
    }

    public function test_case_A_prompt_ready_returns_all_five_required_keys(): void
    {
        $clientMock = $this->makeClientMock();
        $service    = new AskAiOpenAiAdapterService($clientMock);

        $clientMock->method('send')->willReturn($this->makeSendResult());

        $result = $service->generate($this->makePromptReadyPackage());

        foreach (self::REQUIRED_RESULT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Result missing required key '{$key}'");
        }
    }

    public function test_case_A_prompt_ready_raw_response_is_null(): void
    {
        // LLM_ANSWERING_APPROVED is false: generate() refuses before the client is touched,
        // so the send() behaviour this case used to cover is unreachable by design.
        $clientMock = $this->makeClientMock();
        $service    = new AskAiOpenAiAdapterService($clientMock);

        $clientMock->method('send')->willReturn($this->makeSendResult());

        $result = $service->generate($this->makePromptReadyPackage());

        $this->assertNull($result['raw_response']);
    }

    public function test_case_A_prompt_ready_model_is_null(): void
    {
        // LLM_ANSWERING_APPROVED is false: generate() refuses before the client is touched,
        // so the send() behaviour this case used to cover is unreachable by design.
        $clientMock = $this->makeClientMock();
        $service    = new AskAiOpenAiAdapterService($clientMock);

        $clientMock->method('send')->willReturn($this->makeSendResult(['model' => 'gpt-5-2025-11-01']));

        $result = $service->generate($this->makePromptReadyPackage());

        $this->assertNull($result['model']);
    }

    public function test_case_A_prompt_ready_error_names_the_gate(): void
    {
        // LLM_ANSWERING_APPROVED is false: generate() refuses before the client is touched,
        // so the send() behaviour this case used to cover is unreachable by design.
        $clientMock = $this->makeClientMock();
        $service    = new AskAiOpenAiAdapterService($clientMock);

        $clientMock->method('send')->willReturn($this->makeSendResult());

        $result = $service->generate($this->makePromptReadyPackage());

        $this->assertSame('llm_answering_not_approved', $result['error']);
    }

    // =========================================================================
    // Case B — blocked status does not call send(), returns status='blocked'
    // =========================================================================

    public function test_case_B_blocked_status_does_not_call_send(): void
    {
        $clientMock = $this->makeClientMock();
        $service    = new AskAiOpenAiAdapterService($clientMock);

        $clientMock->expects($this->never())->method('send');

        $result = $service->generate($this->makePromptReadyPackage(['status' => 'blocked']));

        $this->assertSame('blocked', $result['status']);
    }

    public function test_case_B_blocked_returns_success_false(): void
    {
        $clientMock = $this->makeClientMock();
        $service    = new AskAiOpenAiAdapterService($clientMock);

        $clientMock->expects($this->never())->method('send');

        $result = $service->generate($this->makePromptReadyPackage(['status' => 'blocked']));

        $this->assertFalse($result['success']);
    }

    public function test_case_B_blocked_returns_all_five_required_keys(): void
    {
        $clientMock = $this->makeClientMock();
        $service    = new AskAiOpenAiAdapterService($clientMock);

        $result = $service->generate($this->makePromptReadyPackage(['status' => 'blocked']));

        foreach (self::REQUIRED_RESULT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Result missing required key '{$key}'");
        }
    }

    // =========================================================================
    // Case C — insufficient_context does not call send(), returns status='blocked'
    // =========================================================================

    public function test_case_C_insufficient_context_does_not_call_send(): void
    {
        $clientMock = $this->makeClientMock();
        $service    = new AskAiOpenAiAdapterService($clientMock);

        $clientMock->expects($this->never())->method('send');

        $result = $service->generate($this->makePromptReadyPackage(['status' => 'insufficient_context']));

        $this->assertSame('blocked', $result['status']);
    }

    public function test_case_C_insufficient_context_returns_success_false(): void
    {
        $clientMock = $this->makeClientMock();
        $service    = new AskAiOpenAiAdapterService($clientMock);

        $result = $service->generate($this->makePromptReadyPackage(['status' => 'insufficient_context']));

        $this->assertFalse($result['success']);
    }

    public function test_case_C_insufficient_context_returns_all_five_required_keys(): void
    {
        $clientMock = $this->makeClientMock();
        $service    = new AskAiOpenAiAdapterService($clientMock);

        $result = $service->generate($this->makePromptReadyPackage(['status' => 'insufficient_context']));

        foreach (self::REQUIRED_RESULT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Result missing required key '{$key}'");
        }
    }

    // =========================================================================
    // Case D — unsupported does not call send(), returns status='blocked'
    // =========================================================================

    public function test_case_D_unsupported_does_not_call_send(): void
    {
        $clientMock = $this->makeClientMock();
        $service    = new AskAiOpenAiAdapterService($clientMock);

        $clientMock->expects($this->never())->method('send');

        $result = $service->generate($this->makePromptReadyPackage(['status' => 'unsupported']));

        $this->assertSame('blocked', $result['status']);
    }

    public function test_case_D_unsupported_returns_success_false(): void
    {
        $clientMock = $this->makeClientMock();
        $service    = new AskAiOpenAiAdapterService($clientMock);

        $result = $service->generate($this->makePromptReadyPackage(['status' => 'unsupported']));

        $this->assertFalse($result['success']);
    }

    public function test_case_D_unsupported_returns_all_five_required_keys(): void
    {
        $clientMock = $this->makeClientMock();
        $service    = new AskAiOpenAiAdapterService($clientMock);

        $result = $service->generate($this->makePromptReadyPackage(['status' => 'unsupported']));

        foreach (self::REQUIRED_RESULT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Result missing required key '{$key}'");
        }
    }

    // =========================================================================
    // Case E — exception from send() (simulating missing/empty API key scenario)
    //           returns status='failed' safely without re-throwing
    // =========================================================================

    public function test_case_E_missing_api_key_path_is_never_reached(): void
    {
        // LLM_ANSWERING_APPROVED is false: generate() refuses before the client is touched,
        // so the send() behaviour this case used to cover is unreachable by design.
        $clientMock = $this->makeClientMock();
        $service    = new AskAiOpenAiAdapterService($clientMock);

        $clientMock->expects($this->never())->method('send');

        $result = $service->generate($this->makePromptReadyPackage());

        $this->assertSame('blocked', $result['status']);
        $this->assertFalse($result['success']);
    }

    public function test_case_E_send_exception_returns_all_five_required_keys(): void
    {
        $clientMock = $this->makeClientMock();
        $service    = new AskAiOpenAiAdapterService($clientMock);

        $clientMock->method('send')
            ->willThrowException(new \Exception('OpenAI API key is not configured.'));

        $result = $service->generate($this->makePromptReadyPackage());

        foreach (self::REQUIRED_RESULT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Result missing required key '{$key}'");
        }
    }

    public function test_case_E_send_exception_raw_response_and_model_are_null(): void
    {
        $clientMock = $this->makeClientMock();
        $service    = new AskAiOpenAiAdapterService($clientMock);

        $clientMock->method('send')
            ->willThrowException(new \Exception('OpenAI API key is not configured.'));

        $result = $service->generate($this->makePromptReadyPackage());

        $this->assertNull($result['raw_response']);
        $this->assertNull($result['model']);
    }

    // =========================================================================
    // Case F — any exception from send() returns status='failed' with error message
    // =========================================================================

    public function test_case_F_send_exception_is_unreachable(): void
    {
        // LLM_ANSWERING_APPROVED is false: generate() refuses before the client is touched,
        // so the send() behaviour this case used to cover is unreachable by design.
        $clientMock = $this->makeClientMock();
        $service    = new AskAiOpenAiAdapterService($clientMock);

        $clientMock->expects($this->never())->method('send');

        $result = $service->generate($this->makePromptReadyPackage());

        $this->assertSame('blocked', $result['status']);
        $this->assertFalse($result['success']);
    }

    public function test_case_F_error_names_the_gate_not_a_provider_message(): void
    {
        // LLM_ANSWERING_APPROVED is false: generate() refuses before the client is touched,
        // so the send() behaviour this case used to cover is unreachable by design.
        $clientMock = $this->makeClientMock();
        $service    = new AskAiOpenAiAdapterService($clientMock);

        $clientMock->method('send')
            ->willThrowException(new \RuntimeException('Network timeout after 90 seconds'));

        $result = $service->generate($this->makePromptReadyPackage());

        $this->assertSame('llm_answering_not_approved', $result['error']);
    }

    public function test_case_F_exception_returns_all_five_required_keys(): void
    {
        $clientMock = $this->makeClientMock();
        $service    = new AskAiOpenAiAdapterService($clientMock);

        $clientMock->method('send')
            ->willThrowException(new \Exception('Some OpenAI error'));

        $result = $service->generate($this->makePromptReadyPackage());

        foreach (self::REQUIRED_RESULT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Result missing required key '{$key}'");
        }
    }

    public function test_case_F_exception_raw_response_and_model_are_null(): void
    {
        $clientMock = $this->makeClientMock();
        $service    = new AskAiOpenAiAdapterService($clientMock);

        $clientMock->method('send')
            ->willThrowException(new \Exception('Some OpenAI error'));

        $result = $service->generate($this->makePromptReadyPackage());

        $this->assertNull($result['raw_response']);
        $this->assertNull($result['model']);
    }

    // =========================================================================
    // Gate — Ask AI never reaches a language model
    // =========================================================================

    public function test_llm_answering_is_hard_disabled_in_code(): void
    {
        // A code constant, not config: flipping it is a reviewed product decision.
        $this->assertFalse(AskAiOpenAiAdapterService::LLM_ANSWERING_APPROVED);
    }

    // =========================================================================
    // Case G — Governance static grep: no hardcoded API keys, no DB writes,
    //           no routes/controllers/views in the service file
    // =========================================================================

    public function test_case_G_no_hardcoded_api_key_in_service_file(): void
    {
        $path    = $this->serviceFilePath();
        $content = file_get_contents($path);
        $this->assertNotFalse($content, 'Could not read service file: ' . $path);

        $lines = explode("\n", $content);
        $codeLines = array_filter($lines, function (string $line): bool {
            $trimmed = ltrim($line);
            return $trimmed !== '' && !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//');
        });
        $codeOnly = implode("\n", $codeLines);

        $this->assertDoesNotMatchRegularExpression(
            '/sk-[A-Za-z0-9\-_]{20,}/',
            $codeOnly,
            'Service file must not contain a hardcoded OpenAI API key'
        );
    }

    public function test_case_G_no_db_writes_in_service_file(): void
    {
        $path    = $this->serviceFilePath();
        $content = file_get_contents($path);
        $this->assertNotFalse($content, 'Could not read service file: ' . $path);

        $lines = explode("\n", $content);
        $codeLines = array_filter($lines, function (string $line): bool {
            $trimmed = ltrim($line);
            return $trimmed !== '' && !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//');
        });
        $codeOnly = implode("\n", $codeLines);

        $this->assertStringNotContainsString('DB::', $codeOnly, 'Service file must not contain DB:: calls');
        $this->assertStringNotContainsString('->save()', $codeOnly, 'Service file must not call ->save()');
        $this->assertStringNotContainsString('->create(', $codeOnly, 'Service file must not call ->create(');
    }

    public function test_case_G_no_routes_controllers_views_in_service_file(): void
    {
        $path    = $this->serviceFilePath();
        $content = file_get_contents($path);
        $this->assertNotFalse($content, 'Could not read service file: ' . $path);

        $lines = explode("\n", $content);
        $codeLines = array_filter($lines, function (string $line): bool {
            $trimmed = ltrim($line);
            return $trimmed !== '' && !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//');
        });
        $codeOnly = implode("\n", $codeLines);

        $this->assertStringNotContainsString('Route::', $codeOnly, 'Service file must not reference Route::');
        $this->assertStringNotContainsString('view(', $codeOnly, 'Service file must not call view()');
        $this->assertStringNotContainsString('Controller', $codeOnly, 'Service file must not reference any Controller');
    }
}
