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
 * Test coverage (cases A–J):
 *   A. Generated response (adapter success + prompt_ready) → status 'ready', success true, answer populated
 *   B. 'blocked' prompt package → status 'blocked', refusal_message from refusal_template, answer null
 *   C. 'insufficient_context' prompt package → status 'insufficient_context', answer carries unavailable-data message
 *   D. 'unsupported' prompt package → status 'unsupported', answer carries unsupported message
 *   E. Failed adapter result (success=false or error set) → status 'failed', error populated
 *   F. 'disclosures' key always present and populated from prompt_package['required_disclosures']
 *   G. 'source_attribution' key always present and passed through from prompt_package['source_attribution']
 *   H. Static governance grep — no OpenAI/HTTP calls and no write calls in non-comment lines of the service file
 *   I. JSON extraction: answer key extraction, answer_text fallback, recursive nested-JSON extraction,
 *      first-string-value fallback, plain-text pass-through, whitespace normalisation, key priority
 *   J. isResponseDegraded() quality detection: raw JSON blob, JSON array, JSON key-value pattern inside text,
 *      very short char count, very short word count, empty string — all degraded; normal paragraph — not degraded
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
    // Case I — JSON extraction from raw_response (new: OpenAI responds with JSON)
    // =========================================================================

    public function test_case_I_json_raw_response_with_answer_key_extracts_text(): void
    {
        $service = $this->makeService();
        $result  = $service->build(
            $this->makePromptReadyPackage(),
            [
                'success'      => true,
                'raw_response' => '{"answer":"The property features a heated pool, updated kitchen, and a two-car garage."}',
                'error'        => null,
            ]
        );

        $this->assertSame(
            'The property features a heated pool, updated kitchen, and a two-car garage.',
            $result['answer']
        );
    }

    public function test_case_I_json_raw_response_with_answer_key_is_not_a_json_blob(): void
    {
        $service = $this->makeService();
        $result  = $service->build(
            $this->makePromptReadyPackage(),
            [
                'success'      => true,
                'raw_response' => '{"answer":"Yes, the seller is offering a credit of $5,000 toward closing costs."}',
                'error'        => null,
            ]
        );

        $this->assertStringNotContainsString('{', $result['answer']);
        $this->assertStringNotContainsString('"answer"', $result['answer']);
        $this->assertStringContainsString('seller is offering', $result['answer']);
    }

    public function test_case_I_json_raw_response_answer_key_whitespace_is_normalised(): void
    {
        $service = $this->makeService();
        $result  = $service->build(
            $this->makePromptReadyPackage(),
            [
                'success'      => true,
                'raw_response' => '{"answer":"  The  monthly  rent  is  $2,500.  "}',
                'error'        => null,
            ]
        );

        $this->assertSame('The monthly rent is $2,500.', $result['answer']);
    }

    public function test_case_I_json_raw_response_answer_text_key_used_as_fallback(): void
    {
        $service = $this->makeService();
        $result  = $service->build(
            $this->makePromptReadyPackage(),
            [
                'success'      => true,
                'raw_response' => '{"answer_text":"The roof was replaced in 2022 and is in excellent condition."}',
                'error'        => null,
            ]
        );

        $this->assertSame(
            'The roof was replaced in 2022 and is in excellent condition.',
            $result['answer']
        );
    }

    public function test_case_I_json_raw_response_first_string_value_used_when_no_known_key(): void
    {
        $service = $this->makeService();
        $result  = $service->build(
            $this->makePromptReadyPackage(),
            [
                'success'      => true,
                'raw_response' => '{"response":"Parking is available in the attached two-car garage."}',
                'error'        => null,
            ]
        );

        $this->assertSame(
            'Parking is available in the attached two-car garage.',
            $result['answer']
        );
    }

    public function test_case_I_answer_key_takes_priority_over_answer_text_key(): void
    {
        $service = $this->makeService();
        $result  = $service->build(
            $this->makePromptReadyPackage(),
            [
                'success'      => true,
                'raw_response' => '{"answer":"Primary answer text.","answer_text":"Secondary answer text."}',
                'error'        => null,
            ]
        );

        $this->assertSame('Primary answer text.', $result['answer']);
    }

    public function test_case_I_plain_text_raw_response_passes_through_unchanged(): void
    {
        $service = $this->makeService();
        $result  = $service->build(
            $this->makePromptReadyPackage(),
            [
                'success'      => true,
                'raw_response' => 'Plain text answer with no JSON encoding.',
                'error'        => null,
            ]
        );

        $this->assertSame('Plain text answer with no JSON encoding.', $result['answer']);
    }

    public function test_case_I_nested_json_falls_back_to_first_string_value(): void
    {
        $service = $this->makeService();
        $result  = $service->build(
            $this->makePromptReadyPackage(),
            [
                'success'      => true,
                'raw_response' => '{"text":"Pets are allowed with a $500 refundable deposit."}',
                'error'        => null,
            ]
        );

        $this->assertSame('Pets are allowed with a $500 refundable deposit.', $result['answer']);
    }

    public function test_case_I_deeply_nested_json_uses_recursive_extraction(): void
    {
        $service = $this->makeService();
        $result  = $service->build(
            $this->makePromptReadyPackage(),
            [
                'success'      => true,
                'raw_response' => '{"data":{"answer":"Pets are welcome with a 25 lb weight limit and a $300 deposit."}}',
                'error'        => null,
            ]
        );

        $this->assertSame(
            'Pets are welcome with a 25 lb weight limit and a $300 deposit.',
            $result['answer'],
            'Recursive extraction must surface the string from a doubly-nested JSON object.'
        );
    }

    // =========================================================================
    // Case J — isResponseDegraded() quality detection
    // =========================================================================

    public function test_case_J_raw_json_blob_is_degraded(): void
    {
        $service = $this->makeService();
        $this->assertTrue(
            $service->isResponseDegraded('{"answer":"Yes","hoa_fee":"250"}'),
            'A raw JSON blob starting with "{" must be considered degraded.'
        );
    }

    public function test_case_J_json_array_blob_is_degraded(): void
    {
        $service = $this->makeService();
        $this->assertTrue(
            $service->isResponseDegraded('["Yes", "250"]'),
            'A raw JSON array starting with "[" must be considered degraded.'
        );
    }

    public function test_case_J_json_key_value_pattern_inside_text_is_degraded(): void
    {
        $service = $this->makeService();
        $this->assertTrue(
            $service->isResponseDegraded('"hoa_fee": "250 per month"'),
            'Text containing a JSON key-value pattern (quoted key + colon) must be considered degraded.'
        );
    }

    public function test_case_J_very_short_char_count_is_degraded(): void
    {
        $service = $this->makeService();
        $this->assertTrue(
            $service->isResponseDegraded('Yes'),
            'A bare one-word answer (< 15 chars) must be considered degraded.'
        );
    }

    public function test_case_J_fewer_than_three_words_is_degraded(): void
    {
        $service = $this->makeService();
        $this->assertTrue(
            $service->isResponseDegraded('No pets.'),
            'A two-word answer (fewer than 3 words) must be considered degraded.'
        );
    }

    public function test_case_J_empty_string_is_degraded(): void
    {
        $service = $this->makeService();
        $this->assertTrue(
            $service->isResponseDegraded(''),
            'An empty string must be considered degraded.'
        );
    }

    public function test_case_J_whitespace_only_is_degraded(): void
    {
        $service = $this->makeService();
        $this->assertTrue(
            $service->isResponseDegraded('   '),
            'A whitespace-only string must be considered degraded.'
        );
    }

    public function test_case_J_normal_paragraph_is_not_degraded(): void
    {
        $service = $this->makeService();
        $this->assertFalse(
            $service->isResponseDegraded(
                'The HOA fee is $250 per month, which includes access to the community pool and landscaping services.'
            ),
            'A complete natural-language sentence must NOT be considered degraded.'
        );
    }

    public function test_case_J_multi_sentence_answer_is_not_degraded(): void
    {
        $service = $this->makeService();
        $this->assertFalse(
            $service->isResponseDegraded(
                'Pets are allowed with restrictions. Dogs up to 25 lbs are permitted with a $300 refundable deposit and a $50 monthly pet fee.'
            ),
            'A multi-sentence answer must NOT be considered degraded.'
        );
    }

    public function test_case_J_answer_with_dollar_amount_is_not_degraded(): void
    {
        $service = $this->makeService();
        $this->assertFalse(
            $service->isResponseDegraded('The seller is offering a $5,000 closing cost credit to buyers.'),
            'A sentence containing a dollar amount must NOT be considered degraded.'
        );
    }

    // =========================================================================
    // Case K — End-to-end no raw JSON leakage regression
    // =========================================================================
    // Ensures that build() extracts a clean string answer regardless of how
    // deeply the model wraps it in JSON. These are behavioral regressions for
    // the system-wide natural-language quality requirement.

    public function test_case_K_build_extracts_answer_from_flat_json_string(): void
    {
        $service  = $this->makeService();
        $raw      = ['success' => true, 'raw_response' => '{"answer":"The HOA fee is $250 per month."}', 'error' => null];
        $result   = $service->build($this->makePromptReadyPackage(), $raw);

        $this->assertSame('ready', $result['status']);
        $this->assertSame('The HOA fee is $250 per month.', $result['answer']);
        $this->assertFalse(
            $service->isResponseDegraded($result['answer']),
            'Case K: extracted answer must not be degraded.'
        );
    }

    public function test_case_K_build_extracts_answer_from_nested_json_string(): void
    {
        $service  = $this->makeService();
        $inner    = json_encode(['answer' => 'The seller is offering a $5,000 closing cost credit.']);
        $raw      = ['success' => true, 'raw_response' => json_encode(['answer' => $inner]), 'error' => null];
        $result   = $service->build($this->makePromptReadyPackage(), $raw);

        $this->assertSame('ready', $result['status']);
        $this->assertSame(
            'The seller is offering a $5,000 closing cost credit.',
            $result['answer'],
            'Case K: nested JSON double-encoding must be unwrapped to the raw sentence.'
        );
        $this->assertFalse(
            $service->isResponseDegraded($result['answer']),
            'Case K: unwrapped answer from nested JSON must not be degraded.'
        );
    }

    public function test_case_K_build_result_not_degraded_for_pet_policy_answer(): void
    {
        $service  = $this->makeService();
        $raw      = [
            'success'      => true,
            'raw_response' => '{"answer":"Pets are allowed with restrictions. Dogs up to 25 lbs are permitted with a $300 refundable deposit and a $50 monthly pet fee."}',
            'error'        => null,
        ];
        $result   = $service->build($this->makePromptReadyPackage(), $raw);

        $this->assertSame('ready', $result['status']);
        $this->assertFalse(
            $service->isResponseDegraded($result['answer']),
            'Case K: a complete pet-policy paragraph must not be flagged as degraded.'
        );
    }

    public function test_case_K_build_result_not_degraded_for_financing_answer(): void
    {
        $service  = $this->makeService();
        $raw      = [
            'success'      => true,
            'raw_response' => '{"answer":"The buyer is pre-approved for conventional financing and is open to FHA or VA loans. An appraisal contingency and financing contingency are both included in the offer terms."}',
            'error'        => null,
        ];
        $result   = $service->build($this->makePromptReadyPackage(), $raw);

        $this->assertSame('ready', $result['status']);
        $this->assertFalse(
            $service->isResponseDegraded($result['answer']),
            'Case K: a complete financing-criteria paragraph must not be flagged as degraded.'
        );
    }

    public function test_case_K_build_flags_bare_boolean_as_degraded(): void
    {
        $service = $this->makeService();
        $this->assertTrue(
            $service->isResponseDegraded('Yes'),
            'Case K: a bare "Yes" answer must be flagged as degraded.'
        );
        $this->assertTrue(
            $service->isResponseDegraded('No'),
            'Case K: a bare "No" answer must be flagged as degraded.'
        );
    }

    public function test_case_K_build_flags_raw_json_blob_as_degraded(): void
    {
        $service = $this->makeService();
        $this->assertTrue(
            $service->isResponseDegraded('{"seller_credit_offered":"Yes","seller_credit_amount":"$5000"}'),
            'Case K: a raw JSON blob must be flagged as degraded.'
        );
    }

    public function test_case_K_build_flags_key_value_pattern_as_degraded(): void
    {
        $service = $this->makeService();
        $this->assertTrue(
            $service->isResponseDegraded("hoa_fee: 250\ncdd_fee: 1200"),
            'Case K: a key:value dump must be flagged as degraded.'
        );
    }

    public function test_case_K_agent_services_answer_is_not_degraded(): void
    {
        $service  = $this->makeService();
        $raw      = [
            'success'      => true,
            'raw_response' => '{"answer":"This agent specializes in full-service residential sales, providing professional photography, MLS listing, open house coordination, and negotiation support throughout the transaction."}',
            'error'        => null,
        ];
        $result   = $service->build($this->makePromptReadyPackage(), $raw);

        $this->assertSame('ready', $result['status']);
        $this->assertFalse(
            $service->isResponseDegraded($result['answer']),
            'Case K: an agent services prose paragraph must not be flagged as degraded.'
        );
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

    // =========================================================================
    // Case L — Behavior regression: key synthesis categories produce prose
    //
    // These tests verify that natural-language paragraphs representing real
    // category outputs are NOT flagged as degraded and that paired JSON fields
    // (seller credit + amount) are extracted correctly. Each sub-case mirrors
    // an actual OpenAI response format for a known category path.
    // =========================================================================

    public function test_case_L1_seller_credit_and_amount_prose_is_not_degraded(): void
    {
        $svc    = $this->makeService();
        $answer = 'The seller is offering a credit of $5,000 toward the buyer\'s closing costs. '
            . 'This concession is intended to reduce the upfront cash required at settlement '
            . 'and can be applied to lender fees, title charges, or prepaid expenses.';

        $this->assertFalse(
            $svc->isResponseDegraded($answer),
            'L1: a seller credit+amount prose paragraph must not be flagged as degraded.'
        );
    }

    public function test_case_L1_seller_credit_amount_extracted_from_json_envelope(): void
    {
        $svc    = $this->makeService();
        $raw    = json_encode([
            'answer' => 'The seller is offering a $5,000 credit toward the buyer\'s closing costs to help offset settlement expenses.',
        ]);
        $result = $svc->build(
            $this->makePromptReadyPackage(),
            $this->makeSuccessAdapterResult(['raw_response' => $raw])
        );

        $this->assertSame('ready', $result['status'],  'L1: seller credit JSON envelope must produce ready status.');
        $this->assertStringContainsString('$5,000', $result['answer'] ?? '', 'L1: answer must contain the dollar amount.');
        $this->assertFalse(
            $svc->isResponseDegraded($result['answer']),
            'L1: extracted seller credit answer must not be flagged as degraded.'
        );
    }

    public function test_case_L2_financing_types_prose_is_not_degraded(): void
    {
        $svc    = $this->makeService();
        $answer = 'The buyer is open to conventional financing, FHA loans, and VA-backed mortgages. '
            . 'Cash offers are also acceptable. Seller financing is not being considered at this time.';

        $this->assertFalse(
            $svc->isResponseDegraded($answer),
            'L2: a financing types prose paragraph must not be flagged as degraded.'
        );
    }

    public function test_case_L3_ownership_cost_synthesis_prose_is_not_degraded(): void
    {
        $svc    = $this->makeService();
        $answer = 'The estimated monthly ownership costs include an HOA fee of $320, an annual CDD '
            . 'assessment of $1,800 (approximately $150 per month), and annual property taxes of '
            . '$4,200 (approximately $350 per month). Combined, these recurring costs total roughly '
            . '$820 per month on top of any mortgage payment.';

        $this->assertFalse(
            $svc->isResponseDegraded($answer),
            'L3: an ownership-cost synthesized paragraph must not be flagged as degraded.'
        );
    }

    public function test_case_L4_utilities_and_pets_paired_prose_is_not_degraded(): void
    {
        $svc    = $this->makeService();
        $answer = 'Water, trash, and lawn care are included in the rent. Tenants are responsible '
            . 'for electricity, internet, and renter\'s insurance. Regarding pets, small dogs and '
            . 'cats are permitted with a $500 non-refundable pet deposit and a $50 monthly pet fee.';

        $this->assertFalse(
            $svc->isResponseDegraded($answer),
            'L4: a utilities+pets paired-term prose paragraph must not be flagged as degraded.'
        );
    }

    public function test_case_L5_agent_services_prose_is_not_degraded(): void
    {
        $svc    = $this->makeService();
        $answer = 'This agent offers full-service representation including professional photography, '
            . 'MLS listing, open house coordination, offer negotiation, and transaction management '
            . 'through closing. A referral fee of 25% applies to any co-brokered transactions.';

        $this->assertFalse(
            $svc->isResponseDegraded($answer),
            'L5: an agent services prose paragraph must not be flagged as degraded.'
        );
    }

    public function test_case_L6_ownership_cost_json_blob_is_degraded(): void
    {
        $svc    = $this->makeService();
        $answer = '{"hoa_fee": 320, "annual_cdd_fee": 1800, "annual_property_taxes": 4200}';

        $this->assertTrue(
            $svc->isResponseDegraded($answer),
            'L6: a raw JSON ownership-cost blob must be flagged as degraded.'
        );
    }

    public function test_case_L7_financing_key_value_dump_is_degraded(): void
    {
        $svc    = $this->makeService();
        $answer = "financing_type: Conventional\nloan_pre_approved: Yes\nappraisal_contingency_buyer: Yes";

        $this->assertTrue(
            $svc->isResponseDegraded($answer),
            'L7: a bare key-value financing dump must be flagged as degraded.'
        );
    }}
