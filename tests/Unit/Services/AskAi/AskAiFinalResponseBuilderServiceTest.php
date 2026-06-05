<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiFinalResponseBuilderService;
use PHPUnit\Framework\TestCase;

/**
 * AskAiFinalResponseBuilderServiceTest
 *
 * Pure unit tests — no database, no Laravel TestCase, no DB traits.
 * AskAiFinalResponseBuilderService is stateless and requires no mocking.
 *
 * Test coverage (cases A–H):
 *   A. Generated response (adapter success + prompt_ready) → status 'ready', success true, answer populated
 *   B. 'blocked' prompt package → status 'blocked', refusal_message from refusal_template, answer null
 *   C. 'insufficient_context' prompt package → status 'insufficient_context', answer carries unavailable-data message
 *   D. 'unsupported' prompt package → status 'unsupported', answer carries unsupported message
 *   E. Failed adapter result (success=false or error set) → status 'failed', error populated
 *   F. 'disclosures' key always present and populated from prompt_package['required_disclosures']
 *   G. 'source_attribution' key always present and passed through from prompt_package['source_attribution']
 *   H. Static governance grep — no OpenAI/HTTP calls and no write calls in non-comment lines of the service file
 */
class AskAiFinalResponseBuilderServiceTest extends TestCase
{
    /**
     * The 7 keys that must be present in every build() response.
     */
    private const REQUIRED_RESPONSE_KEYS = [
        'success',
        'status',
        'answer',
        'disclosures',
        'source_attribution',
        'refusal_message',
        'error',
    ];

    /**
     * Absolute path to the service file — derived without base_path() so this
     * works in pure PHPUnit\Framework\TestCase (no Laravel container).
     */
    private function serviceFilePath(): string
    {
        return dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiFinalResponseBuilderService.php';
    }

    private function makeService(): AskAiFinalResponseBuilderService
    {
        return new AskAiFinalResponseBuilderService();
    }

    /**
     * Build a minimal prompt_ready prompt package stub.
     */
    private function makePromptReadyPackage(array $overrides = []): array
    {
        return array_merge([
            'success'                  => true,
            'status'                   => 'prompt_ready',
            'prompt_package_version'   => 'ASK_AI_PROMPT_PACKAGE_V1',
            'question'                 => 'What makes this property stand out?',
            'question_type'            => 'property_standout',
            'system_instructions'      => ['You are an AI assistant for a real estate platform.'],
            'developer_instructions'   => [],
            'allowed_context'          => [],
            'source_attribution'       => [
                'sources'          => [
                    ['key' => 'property_intelligence', 'label' => 'Property Intelligence', 'version' => 'PROPERTY_INTELLIGENCE_V1'],
                ],
                'required_sources' => ['property_intelligence'],
                'versions'         => [
                    'property_intelligence_version' => 'PROPERTY_INTELLIGENCE_V1',
                    'ask_ai_context'                => 'ASK_AI_CONTEXT_V1',
                    'contract_version'              => 'ASK_AI_RESPONSE_CONTRACT_V1',
                ],
            ],
            'required_disclosures'     => [
                'Information is derived from structured property data and may not reflect all property features.',
            ],
            'refusal_template'         => null,
            'missing_required_sources' => [],
            'context_versions'         => ['ask_ai_context' => 'ASK_AI_CONTEXT_V1'],
            'response_format'          => ['type' => 'structured_text'],
            'error'                    => null,
        ], $overrides);
    }

    /**
     * Build a minimal successful adapter result stub.
     *
     * Uses raw_response as the primary answer key, matching the official output
     * contract of AskAiOpenAiAdapterService.
     */
    private function makeSuccessAdapterResult(array $overrides = []): array
    {
        return array_merge([
            'success'      => true,
            'raw_response' => 'This property stands out because of its pool, updated kitchen, and garage.',
            'error'        => null,
        ], $overrides);
    }

    // =========================================================================
    // Case A — Generated response (adapter success + prompt_ready) → ready
    // =========================================================================

    public function test_case_A_prompt_ready_with_successful_adapter_returns_ready_status(): void
    {
        $service = $this->makeService();
        $result  = $service->build($this->makePromptReadyPackage(), $this->makeSuccessAdapterResult());

        $this->assertSame('ready', $result['status']);
        $this->assertTrue($result['success']);
    }

    public function test_case_A_ready_answer_is_populated_from_raw_response(): void
    {
        $service = $this->makeService();
        $result  = $service->build(
            $this->makePromptReadyPackage(),
            $this->makeSuccessAdapterResult(['raw_response' => 'Great pool and garage.'])
        );

        $this->assertNotNull($result['answer']);
        $this->assertStringContainsString('pool', $result['answer']);
    }

    public function test_case_A_raw_response_takes_priority_over_text_fallback(): void
    {
        $service = $this->makeService();
        $result  = $service->build(
            $this->makePromptReadyPackage(),
            [
                'success'      => true,
                'raw_response' => 'Answer from raw_response.',
                'text'         => 'Answer from text.',
                'error'        => null,
            ]
        );

        $this->assertSame('Answer from raw_response.', $result['answer']);
    }

    public function test_case_A_raw_response_takes_priority_over_answer_fallback(): void
    {
        $service = $this->makeService();
        $result  = $service->build(
            $this->makePromptReadyPackage(),
            [
                'success'      => true,
                'raw_response' => 'Answer from raw_response.',
                'answer'       => 'Answer from answer key.',
                'error'        => null,
            ]
        );

        $this->assertSame('Answer from raw_response.', $result['answer']);
    }

    public function test_case_A_text_fallback_used_when_raw_response_absent(): void
    {
        $service = $this->makeService();
        $result  = $service->build(
            $this->makePromptReadyPackage(),
            [
                'success' => true,
                'text'    => 'Answer from text fallback.',
                'error'   => null,
            ]
        );

        $this->assertSame('Answer from text fallback.', $result['answer']);
    }

    public function test_case_A_answer_fallback_used_when_raw_response_and_text_absent(): void
    {
        $service = $this->makeService();
        $result  = $service->build(
            $this->makePromptReadyPackage(),
            [
                'success' => true,
                'answer'  => 'Answer from answer fallback.',
                'error'   => null,
            ]
        );

        $this->assertSame('Answer from answer fallback.', $result['answer']);
    }

    public function test_case_A_ready_answer_is_trimmed(): void
    {
        $service = $this->makeService();
        $result  = $service->build(
            $this->makePromptReadyPackage(),
            $this->makeSuccessAdapterResult(['raw_response' => '   This property is great.   '])
        );

        $this->assertSame('This property is great.', $result['answer']);
    }

    public function test_case_A_ready_answer_collapses_internal_whitespace(): void
    {
        $service = $this->makeService();
        $result  = $service->build(
            $this->makePromptReadyPackage(),
            $this->makeSuccessAdapterResult(['raw_response' => "This  property   has    a   pool."])
        );

        $this->assertSame('This property has a pool.', $result['answer']);
    }

    public function test_case_A_ready_returns_all_seven_required_keys(): void
    {
        $service = $this->makeService();
        $result  = $service->build($this->makePromptReadyPackage(), $this->makeSuccessAdapterResult());

        foreach (self::REQUIRED_RESPONSE_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Key '{$key}' missing in ready response");
        }
        $this->assertCount(7, array_intersect_key($result, array_flip(self::REQUIRED_RESPONSE_KEYS)));
    }

    public function test_case_A_ready_refusal_message_is_null(): void
    {
        $service = $this->makeService();
        $result  = $service->build($this->makePromptReadyPackage(), $this->makeSuccessAdapterResult());

        $this->assertNull($result['refusal_message']);
    }

    public function test_case_A_ready_error_is_null(): void
    {
        $service = $this->makeService();
        $result  = $service->build($this->makePromptReadyPackage(), $this->makeSuccessAdapterResult());

        $this->assertNull($result['error']);
    }

    // =========================================================================
    // Case B — 'blocked' prompt package → blocked, refusal_message set, answer null
    // =========================================================================

    public function test_case_B_blocked_prompt_package_returns_blocked_status(): void
    {
        $service = $this->makeService();
        $package = $this->makePromptReadyPackage([
            'success'          => false,
            'status'           => 'blocked',
            'refusal_template' => 'This question type is not permitted on this platform. No response can be generated.',
        ]);

        $result = $service->build($package, $this->makeSuccessAdapterResult());

        $this->assertSame('blocked', $result['status']);
        $this->assertFalse($result['success']);
    }

    public function test_case_B_blocked_answer_is_null(): void
    {
        $service = $this->makeService();
        $package = $this->makePromptReadyPackage([
            'status'           => 'blocked',
            'refusal_template' => 'Not permitted.',
        ]);

        $result = $service->build($package, $this->makeSuccessAdapterResult());

        $this->assertNull($result['answer']);
    }

    public function test_case_B_blocked_refusal_message_comes_from_refusal_template(): void
    {
        $service         = $this->makeService();
        $refusalTemplate = 'This question type is not permitted on this platform. No response can be generated.';
        $package         = $this->makePromptReadyPackage([
            'status'           => 'blocked',
            'refusal_template' => $refusalTemplate,
        ]);

        $result = $service->build($package, $this->makeSuccessAdapterResult());

        $this->assertSame($refusalTemplate, $result['refusal_message']);
    }

    public function test_case_B_blocked_error_is_null(): void
    {
        $service = $this->makeService();
        $package = $this->makePromptReadyPackage([
            'status'           => 'blocked',
            'refusal_template' => 'Not permitted.',
        ]);

        $result = $service->build($package, $this->makeSuccessAdapterResult());

        $this->assertNull($result['error']);
    }

    // =========================================================================
    // Case C — 'insufficient_context' prompt package → answer carries unavailable-data message
    // =========================================================================

    public function test_case_C_insufficient_context_returns_insufficient_context_status(): void
    {
        $service = $this->makeService();
        $package = $this->makePromptReadyPackage([
            'success' => false,
            'status'  => 'insufficient_context',
        ]);

        $result = $service->build($package, $this->makeSuccessAdapterResult());

        $this->assertSame('insufficient_context', $result['status']);
        $this->assertFalse($result['success']);
    }

    public function test_case_C_insufficient_context_answer_carries_unavailable_data_message(): void
    {
        $service = $this->makeService();
        $package = $this->makePromptReadyPackage([
            'status' => 'insufficient_context',
        ]);

        $result = $service->build($package, $this->makeSuccessAdapterResult());

        $this->assertNotNull($result['answer']);
        $this->assertIsString($result['answer']);
        $this->assertNotEmpty($result['answer']);
    }

    public function test_case_C_insufficient_context_refusal_message_is_null(): void
    {
        $service = $this->makeService();
        $package = $this->makePromptReadyPackage([
            'status' => 'insufficient_context',
        ]);

        $result = $service->build($package, $this->makeSuccessAdapterResult());

        $this->assertNull($result['refusal_message']);
    }

    public function test_case_C_insufficient_context_error_is_null(): void
    {
        $service = $this->makeService();
        $package = $this->makePromptReadyPackage([
            'status' => 'insufficient_context',
        ]);

        $result = $service->build($package, $this->makeSuccessAdapterResult());

        $this->assertNull($result['error']);
    }

    // =========================================================================
    // Case D — 'unsupported' prompt package → answer carries unsupported message
    // =========================================================================

    public function test_case_D_unsupported_prompt_package_returns_unsupported_status(): void
    {
        $service = $this->makeService();
        $package = $this->makePromptReadyPackage([
            'success' => false,
            'status'  => 'unsupported',
        ]);

        $result = $service->build($package, $this->makeSuccessAdapterResult());

        $this->assertSame('unsupported', $result['status']);
        $this->assertFalse($result['success']);
    }

    public function test_case_D_unsupported_answer_carries_unsupported_message(): void
    {
        $service = $this->makeService();
        $package = $this->makePromptReadyPackage([
            'status' => 'unsupported',
        ]);

        $result = $service->build($package, $this->makeSuccessAdapterResult());

        $this->assertNotNull($result['answer']);
        $this->assertIsString($result['answer']);
        $this->assertNotEmpty($result['answer']);
    }

    public function test_case_D_unsupported_refusal_message_is_null(): void
    {
        $service = $this->makeService();
        $package = $this->makePromptReadyPackage([
            'status' => 'unsupported',
        ]);

        $result = $service->build($package, $this->makeSuccessAdapterResult());

        $this->assertNull($result['refusal_message']);
    }

    public function test_case_D_unsupported_error_is_null(): void
    {
        $service = $this->makeService();
        $package = $this->makePromptReadyPackage([
            'status' => 'unsupported',
        ]);

        $result = $service->build($package, $this->makeSuccessAdapterResult());

        $this->assertNull($result['error']);
    }

    // =========================================================================
    // Case E — Failed adapter result → status 'failed', error populated
    // =========================================================================

    public function test_case_E_adapter_success_false_returns_failed_status(): void
    {
        $service       = $this->makeService();
        $adapterResult = ['success' => false, 'error' => 'OpenAI rate limit exceeded.'];

        $result = $service->build($this->makePromptReadyPackage(), $adapterResult);

        $this->assertSame('failed', $result['status']);
        $this->assertFalse($result['success']);
    }

    public function test_case_E_adapter_error_present_returns_failed_status(): void
    {
        $service       = $this->makeService();
        $adapterResult = ['success' => false, 'error' => 'Connection timeout.'];

        $result = $service->build($this->makePromptReadyPackage(), $adapterResult);

        $this->assertSame('failed', $result['status']);
    }

    public function test_case_E_failed_adapter_error_key_is_populated(): void
    {
        $service       = $this->makeService();
        $adapterResult = ['success' => false, 'error' => 'OpenAI rate limit exceeded.'];

        $result = $service->build($this->makePromptReadyPackage(), $adapterResult);

        $this->assertNotNull($result['error']);
        $this->assertIsString($result['error']);
        $this->assertNotEmpty($result['error']);
    }

    public function test_case_E_failed_adapter_error_message_matches_adapter_error(): void
    {
        $service       = $this->makeService();
        $errorMessage  = 'OpenAI rate limit exceeded.';
        $adapterResult = ['success' => false, 'error' => $errorMessage];

        $result = $service->build($this->makePromptReadyPackage(), $adapterResult);

        $this->assertSame($errorMessage, $result['error']);
    }

    public function test_case_E_failed_adapter_answer_is_null(): void
    {
        $service       = $this->makeService();
        $adapterResult = ['success' => false, 'error' => 'Timeout.'];

        $result = $service->build($this->makePromptReadyPackage(), $adapterResult);

        $this->assertNull($result['answer']);
    }

    // =========================================================================
    // Case F — 'disclosures' always present and populated from required_disclosures
    // =========================================================================

    public function test_case_F_disclosures_key_always_present_on_ready_path(): void
    {
        $service = $this->makeService();
        $result  = $service->build($this->makePromptReadyPackage(), $this->makeSuccessAdapterResult());

        $this->assertArrayHasKey('disclosures', $result);
    }

    public function test_case_F_disclosures_populated_from_required_disclosures_on_ready_path(): void
    {
        $service     = $this->makeService();
        $disclosures = [
            'Information is derived from structured property data.',
            'This is not legal advice.',
        ];
        $package = $this->makePromptReadyPackage(['required_disclosures' => $disclosures]);

        $result = $service->build($package, $this->makeSuccessAdapterResult());

        $this->assertSame($disclosures, $result['disclosures']);
    }

    public function test_case_F_disclosures_key_always_present_on_blocked_path(): void
    {
        $service = $this->makeService();
        $package = $this->makePromptReadyPackage([
            'status'               => 'blocked',
            'refusal_template'     => 'Not permitted.',
            'required_disclosures' => ['Fair housing disclosure.'],
        ]);

        $result = $service->build($package, $this->makeSuccessAdapterResult());

        $this->assertArrayHasKey('disclosures', $result);
        $this->assertSame(['Fair housing disclosure.'], $result['disclosures']);
    }

    public function test_case_F_disclosures_key_always_present_on_failed_adapter_path(): void
    {
        $service       = $this->makeService();
        $disclosures   = ['Some disclosure.'];
        $package       = $this->makePromptReadyPackage(['required_disclosures' => $disclosures]);
        $adapterResult = ['success' => false, 'error' => 'Timeout.'];

        $result = $service->build($package, $adapterResult);

        $this->assertArrayHasKey('disclosures', $result);
        $this->assertSame($disclosures, $result['disclosures']);
    }

    public function test_case_F_disclosures_not_empty_skipped_even_when_populated(): void
    {
        $service     = $this->makeService();
        $disclosures = ['Disclosure A.', 'Disclosure B.'];
        $package     = $this->makePromptReadyPackage(['required_disclosures' => $disclosures]);

        $result = $service->build($package, $this->makeSuccessAdapterResult());

        $this->assertCount(2, $result['disclosures']);
    }

    // =========================================================================
    // Case G — 'source_attribution' always present and passed through
    // =========================================================================

    public function test_case_G_source_attribution_key_always_present_on_ready_path(): void
    {
        $service = $this->makeService();
        $result  = $service->build($this->makePromptReadyPackage(), $this->makeSuccessAdapterResult());

        $this->assertArrayHasKey('source_attribution', $result);
    }

    public function test_case_G_source_attribution_passed_through_unchanged_on_ready_path(): void
    {
        $service           = $this->makeService();
        $sourceAttribution = [
            'required_sources' => ['property_intelligence', 'location_intelligence'],
            'versions'         => [
                'property_intelligence_version' => 'PROPERTY_INTELLIGENCE_V1',
                'ask_ai_context'                => 'ASK_AI_CONTEXT_V1',
                'contract_version'              => 'ASK_AI_RESPONSE_CONTRACT_V1',
            ],
        ];
        $package = $this->makePromptReadyPackage(['source_attribution' => $sourceAttribution]);

        $result = $service->build($package, $this->makeSuccessAdapterResult());

        $this->assertSame($sourceAttribution, $result['source_attribution']);
    }

    public function test_case_G_source_attribution_key_always_present_on_blocked_path(): void
    {
        $service = $this->makeService();
        $package = $this->makePromptReadyPackage([
            'status'             => 'blocked',
            'refusal_template'   => 'Not permitted.',
            'source_attribution' => ['required_sources' => [], 'versions' => []],
        ]);

        $result = $service->build($package, $this->makeSuccessAdapterResult());

        $this->assertArrayHasKey('source_attribution', $result);
    }

    public function test_case_G_source_attribution_passed_through_on_insufficient_context_path(): void
    {
        $service           = $this->makeService();
        $sourceAttribution = ['required_sources' => ['listing'], 'versions' => []];
        $package           = $this->makePromptReadyPackage([
            'status'             => 'insufficient_context',
            'source_attribution' => $sourceAttribution,
        ]);

        $result = $service->build($package, $this->makeSuccessAdapterResult());

        $this->assertSame($sourceAttribution, $result['source_attribution']);
    }

    public function test_case_G_source_attribution_passed_through_on_failed_adapter_path(): void
    {
        $service           = $this->makeService();
        $sourceAttribution = ['required_sources' => ['property_intelligence'], 'versions' => []];
        $package           = $this->makePromptReadyPackage(['source_attribution' => $sourceAttribution]);
        $adapterResult     = ['success' => false, 'error' => 'Error.'];

        $result = $service->build($package, $adapterResult);

        $this->assertSame($sourceAttribution, $result['source_attribution']);
    }

    // =========================================================================
    // Case H — Static governance grep
    // =========================================================================

    public function test_case_H_service_file_contains_no_openai_or_http_calls(): void
    {
        $filePath = $this->serviceFilePath();
        $this->assertFileExists($filePath, 'Service file does not exist at expected path.');

        $lines    = file($filePath, FILE_IGNORE_NEW_LINES);
        $nonCommentLines = array_filter($lines, static function (string $line): bool {
            return !preg_match('/^\s*\*/', $line) && !preg_match('/^\s*\/\//', $line);
        });

        $sourceCode = implode("\n", $nonCommentLines);

        $forbiddenPatterns = [
            'OpenAI',
            'openai',
            'Http::',
            'Curl',
            'curl_',
            'file_get_contents',
            'GuzzleHttp',
            'HttpClient',
        ];

        foreach ($forbiddenPatterns as $pattern) {
            $this->assertStringNotContainsString(
                $pattern,
                $sourceCode,
                "Service file must not contain '{$pattern}' in non-comment lines."
            );
        }
    }

    public function test_case_H_service_file_contains_no_write_calls(): void
    {
        $filePath = $this->serviceFilePath();
        $this->assertFileExists($filePath, 'Service file does not exist at expected path.');

        $lines           = file($filePath, FILE_IGNORE_NEW_LINES);
        $nonCommentLines = array_filter($lines, static function (string $line): bool {
            return !preg_match('/^\s*\*/', $line) && !preg_match('/^\s*\/\//', $line);
        });

        $sourceCode = implode("\n", $nonCommentLines);

        $writeForbidden = [
            '->save(',
            '->update(',
            '->create(',
            '->delete(',
            '->insert(',
            'DB::insert',
            'DB::update',
            'DB::delete',
            'DB::statement',
        ];

        foreach ($writeForbidden as $pattern) {
            $this->assertStringNotContainsString(
                $pattern,
                $sourceCode,
                "Service file must not contain '{$pattern}' in non-comment lines."
            );
        }
    }

    public function test_case_H_service_file_contains_governance_block(): void
    {
        $filePath = $this->serviceFilePath();
        $this->assertFileExists($filePath);

        $contents = file_get_contents($filePath);

        $this->assertStringContainsString('GOVERNANCE BLOCK', $contents);
        $this->assertStringContainsString('MUST NEVER', $contents);
        $this->assertStringContainsString('call OpenAI', $contents);
    }
}
