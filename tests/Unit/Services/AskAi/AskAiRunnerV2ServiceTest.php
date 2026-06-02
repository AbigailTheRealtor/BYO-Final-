<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiFinalResponseBuilderService;
use App\Services\AskAi\AskAiInternalRunnerService;
use App\Services\AskAi\AskAiOpenAiAdapterService;
use App\Services\AskAi\AskAiQuestionClassifierService;
use App\Services\AskAi\AskAiRunnerV2Service;
use PHPUnit\Framework\TestCase;

/**
 * AskAiRunnerV2ServiceTest
 *
 * Pure unit tests — no database, no Laravel TestCase, no DB traits.
 * All four pipeline dependencies are mocked via createMock().
 *
 * Test coverage (cases A–I):
 *   A. Happy path — all four stages succeed; result has success=true, status='ready', all nine keys present.
 *   B. Blocked — classifier returns 'prohibited', internal runner returns blocked package,
 *      final response returns blocked; result has success=false, status='blocked'.
 *   C. Insufficient context — internal runner returns insufficient_context prompt package;
 *      final response returns insufficient_context; result has success=false, status='insufficient_context'.
 *   D. Unsupported — classifier returns 'unsupported'; final response returns unsupported;
 *      result has success=false, status='unsupported'.
 *   E. Adapter failure — adapter returns failed; final response returns failed;
 *      result has success=false, status='failed', error populated.
 *   F. Missing prompt_package — internal runner returns null prompt_package; OpenAI skipped;
 *      safe failed result returned with error populated.
 *   G. Exception/Throwable — exception thrown during pipeline; catch returns fixed nine-key shape
 *      with success=false, status='failed', error=exception message.
 *   H. Options forwarding — $options array is forwarded unchanged to AskAiInternalRunnerService::run().
 *   I. Static governance scan — service file contains no DB facade calls, no direct OpenAI SDK
 *      instantiation, and no hardcoded API keys.
 */
class AskAiRunnerV2ServiceTest extends TestCase
{
    private const REQUIRED_RESULT_KEYS = [
        'success',
        'status',
        'classification',
        'context',
        'contract',
        'prompt_package',
        'adapter_result',
        'final_response',
        'error',
    ];

    /**
     * Absolute path to the runner service file — derived without base_path() so this
     * works in pure PHPUnit\Framework\TestCase (no Laravel container).
     */
    private function serviceFilePath(): string
    {
        return dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiRunnerV2Service.php';
    }

    /**
     * Build createMock instances for all four dependencies.
     */
    private function makeMocks(): array
    {
        return [
            'classifier'    => $this->createMock(AskAiQuestionClassifierService::class),
            'internalRunner'=> $this->createMock(AskAiInternalRunnerService::class),
            'adapter'       => $this->createMock(AskAiOpenAiAdapterService::class),
            'finalBuilder'  => $this->createMock(AskAiFinalResponseBuilderService::class),
        ];
    }

    private function makeRunner(array $mocks): AskAiRunnerV2Service
    {
        return new AskAiRunnerV2Service(
            $mocks['classifier'],
            $mocks['internalRunner'],
            $mocks['adapter'],
            $mocks['finalBuilder']
        );
    }

    private function makeClassification(string $questionType = 'property_standout'): array
    {
        return [
            'question_type' => $questionType,
            'confidence'    => 0.85,
            'reason'        => "Matched keyword rule for '{$questionType}'.",
        ];
    }

    private function makeInternalResult(array $overrides = []): array
    {
        return array_merge([
            'success'        => true,
            'status'         => 'prompt_ready',
            'context'        => ['status' => 'assembled', 'listing_type' => 'seller'],
            'contract'       => ['status' => 'contract_ready', 'question_type' => 'property_standout'],
            'prompt_package' => [
                'status'               => 'prompt_ready',
                'question_type'        => 'property_standout',
                'required_disclosures' => ['Information is derived from structured property data.'],
                'source_attribution'   => ['required_sources' => ['property_intelligence']],
                'refusal_template'     => null,
            ],
            'error'          => null,
        ], $overrides);
    }

    private function makeAdapterResult(array $overrides = []): array
    {
        return array_merge([
            'success'      => true,
            'status'       => 'generated',
            'raw_response' => 'This property stands out because of its pool and updated kitchen.',
            'model'        => 'gpt-4o',
            'error'        => null,
        ], $overrides);
    }

    private function makeFinalResponse(array $overrides = []): array
    {
        return array_merge([
            'success'            => true,
            'status'             => 'ready',
            'answer'             => 'This property stands out because of its pool and updated kitchen.',
            'disclosures'        => ['Information is derived from structured property data.'],
            'source_attribution' => ['required_sources' => ['property_intelligence']],
            'refusal_message'    => null,
            'error'              => null,
        ], $overrides);
    }

    // =========================================================================
    // Case A — Happy path: all four stages succeed
    // =========================================================================

    public function test_case_A_happy_path_returns_success_true_and_ready_status(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification());
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What makes this property stand out?');

        $this->assertTrue($result['success']);
        $this->assertSame('ready', $result['status']);
    }

    public function test_case_A_happy_path_returns_all_nine_required_keys(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification());
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What makes this property stand out?');

        foreach (self::REQUIRED_RESULT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Result missing required key '{$key}'");
        }
        $this->assertCount(9, array_intersect_key($result, array_flip(self::REQUIRED_RESULT_KEYS)));
    }

    public function test_case_A_happy_path_classification_is_populated(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $classification = $this->makeClassification('property_standout');
        $mocks['classifier']->method('classify')->willReturn($classification);
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What makes this property stand out?');

        $this->assertSame($classification, $result['classification']);
    }

    public function test_case_A_happy_path_error_is_null(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification());
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What makes this property stand out?');

        $this->assertNull($result['error']);
    }

    public function test_case_A_happy_path_all_stage_outputs_preserved(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $internalResult = $this->makeInternalResult();
        $adapterResult  = $this->makeAdapterResult();
        $finalResponse  = $this->makeFinalResponse();

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification());
        $mocks['internalRunner']->method('run')->willReturn($internalResult);
        $mocks['adapter']->method('generate')->willReturn($adapterResult);
        $mocks['finalBuilder']->method('build')->willReturn($finalResponse);

        $result = $runner->run('seller', 1, 'What makes this property stand out?');

        $this->assertSame($internalResult['context'],        $result['context']);
        $this->assertSame($internalResult['contract'],       $result['contract']);
        $this->assertSame($internalResult['prompt_package'], $result['prompt_package']);
        $this->assertSame($adapterResult,                    $result['adapter_result']);
        $this->assertSame($finalResponse,                    $result['final_response']);
    }

    // =========================================================================
    // Case B — Blocked: classifier returns 'prohibited', final response is blocked
    // =========================================================================

    public function test_case_B_blocked_returns_success_false_and_blocked_status(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $blockedPackage = [
            'status'               => 'blocked',
            'question_type'        => 'prohibited',
            'required_disclosures' => [],
            'source_attribution'   => [],
            'refusal_template'     => 'This question type is not permitted on this platform.',
        ];

        $internalResult = $this->makeInternalResult([
            'success'        => false,
            'status'         => 'blocked',
            'prompt_package' => $blockedPackage,
        ]);

        $blockedFinal = $this->makeFinalResponse([
            'success'         => false,
            'status'          => 'blocked',
            'answer'          => null,
            'refusal_message' => 'This question type is not permitted on this platform.',
            'error'           => null,
        ]);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('prohibited'));
        $mocks['internalRunner']->method('run')->willReturn($internalResult);
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($blockedFinal);

        $result = $runner->run('seller', 1, 'Which neighborhood has the best schools?');

        $this->assertFalse($result['success']);
        $this->assertSame('blocked', $result['status']);
    }

    public function test_case_B_blocked_error_is_null(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $blockedPackage = [
            'status'               => 'blocked',
            'question_type'        => 'prohibited',
            'required_disclosures' => [],
            'source_attribution'   => [],
            'refusal_template'     => 'Not permitted.',
        ];

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('prohibited'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult(['prompt_package' => $blockedPackage]));
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse([
            'success' => false,
            'status'  => 'blocked',
            'error'   => null,
        ]));

        $result = $runner->run('seller', 1, 'Is this a safe neighborhood?');

        $this->assertNull($result['error']);
    }

    // =========================================================================
    // Case C — Insufficient context: internal runner returns insufficient_context
    // =========================================================================

    public function test_case_C_insufficient_context_returns_success_false_and_correct_status(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $insufficientPackage = [
            'status'               => 'insufficient_context',
            'question_type'        => 'property_standout',
            'required_disclosures' => [],
            'source_attribution'   => [],
            'refusal_template'     => null,
        ];

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification());
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => false,
            'status'         => 'insufficient_context',
            'prompt_package' => $insufficientPackage,
        ]));
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse([
            'success' => false,
            'status'  => 'insufficient_context',
            'answer'  => 'The requested information is not available because one or more required data sources are missing.',
            'error'   => null,
        ]));

        $result = $runner->run('seller', 1, 'What makes this property stand out?');

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_context', $result['status']);
        $this->assertNull($result['error']);
    }

    public function test_case_C_insufficient_context_all_nine_keys_present(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification());
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => false,
            'status'         => 'insufficient_context',
            'prompt_package' => ['status' => 'insufficient_context', 'required_disclosures' => [], 'source_attribution' => [], 'refusal_template' => null],
        ]));
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse([
            'success' => false,
            'status'  => 'insufficient_context',
        ]));

        $result = $runner->run('seller', 1, 'What makes this property stand out?');

        foreach (self::REQUIRED_RESULT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Result missing required key '{$key}'");
        }
    }

    // =========================================================================
    // Case D — Unsupported: classifier returns 'unsupported', final response is unsupported
    // =========================================================================

    public function test_case_D_unsupported_returns_success_false_and_unsupported_status(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $unsupportedPackage = [
            'status'               => 'unsupported',
            'question_type'        => 'unsupported',
            'required_disclosures' => [],
            'source_attribution'   => [],
            'refusal_template'     => null,
        ];

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => false,
            'status'         => 'unsupported',
            'prompt_package' => $unsupportedPackage,
        ]));
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse([
            'success' => false,
            'status'  => 'unsupported',
            'answer'  => 'This question type is not supported.',
            'error'   => null,
        ]));

        $result = $runner->run('seller', 1, 'Some unrecognised question?');

        $this->assertFalse($result['success']);
        $this->assertSame('unsupported', $result['status']);
        $this->assertNull($result['error']);
    }

    // =========================================================================
    // Case E — Adapter failure: adapter returns failed, final response returns failed
    // =========================================================================

    public function test_case_E_adapter_failure_returns_success_false_and_failed_status(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification());
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult([
            'success' => false,
            'status'  => 'failed',
            'error'   => 'OpenAI rate limit exceeded.',
        ]));
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse([
            'success' => false,
            'status'  => 'failed',
            'answer'  => null,
            'error'   => 'OpenAI rate limit exceeded.',
        ]));

        $result = $runner->run('seller', 1, 'What makes this property stand out?');

        $this->assertFalse($result['success']);
        $this->assertSame('failed', $result['status']);
    }

    public function test_case_E_adapter_failure_error_is_populated(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $errorMessage = 'OpenAI rate limit exceeded.';

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification());
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult([
            'success' => false,
            'status'  => 'failed',
            'error'   => $errorMessage,
        ]));
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse([
            'success' => false,
            'status'  => 'failed',
            'answer'  => null,
            'error'   => $errorMessage,
        ]));

        $result = $runner->run('seller', 1, 'What makes this property stand out?');

        $this->assertNotNull($result['error']);
        $this->assertSame($errorMessage, $result['error']);
    }

    // =========================================================================
    // Case F — Missing prompt_package: internal runner returns null; OpenAI skipped
    // =========================================================================

    public function test_case_F_missing_prompt_package_skips_openai_and_returns_failed(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification());
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => false,
            'status'         => 'failed',
            'prompt_package' => null,
            'error'          => 'Context builder threw.',
        ]));

        $mocks['adapter']->expects($this->never())->method('generate');
        $mocks['finalBuilder']->expects($this->never())->method('build');

        $result = $runner->run('seller', 1, 'What makes this property stand out?');

        $this->assertFalse($result['success']);
        $this->assertSame('failed', $result['status']);
        $this->assertNull($result['prompt_package']);
        $this->assertNull($result['adapter_result']);
        $this->assertNull($result['final_response']);
        $this->assertNotNull($result['error']);
    }

    public function test_case_F_missing_prompt_package_all_nine_keys_present(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification());
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => false,
            'status'         => 'failed',
            'prompt_package' => null,
        ]));
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What makes this property stand out?');

        foreach (self::REQUIRED_RESULT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Result missing required key '{$key}'");
        }
    }

    // =========================================================================
    // Case G — Exception/Throwable: catch returns fixed nine-key shape
    // =========================================================================

    public function test_case_G_throwable_returns_failed_shape_with_error_message(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')
            ->willThrowException(new \RuntimeException('Unexpected pipeline failure'));

        $result = $runner->run('seller', 1, 'What makes this property stand out?');

        $this->assertFalse($result['success']);
        $this->assertSame('failed', $result['status']);
        $this->assertSame('Unexpected pipeline failure', $result['error']);
    }

    public function test_case_G_throwable_returns_all_nine_required_keys(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')
            ->willThrowException(new \RuntimeException('fail'));

        $result = $runner->run('seller', 1, 'q');

        foreach (self::REQUIRED_RESULT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Throwable result missing required key '{$key}'");
        }
    }

    public function test_case_G_throwable_all_payload_keys_are_null(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')
            ->willThrowException(new \RuntimeException('fail'));

        $result = $runner->run('seller', 1, 'q');

        $this->assertNull($result['classification']);
        $this->assertNull($result['context']);
        $this->assertNull($result['contract']);
        $this->assertNull($result['prompt_package']);
        $this->assertNull($result['adapter_result']);
        $this->assertNull($result['final_response']);
    }

    public function test_case_G_throwable_thrown_by_internal_runner_is_caught(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification());
        $mocks['internalRunner']->method('run')
            ->willThrowException(new \RuntimeException('Internal runner exploded'));

        $result = $runner->run('seller', 1, 'q');

        $this->assertFalse($result['success']);
        $this->assertSame('failed', $result['status']);
        $this->assertSame('Internal runner exploded', $result['error']);
    }

    // =========================================================================
    // Case H — Options forwarding: $options passed unchanged to internal runner
    // =========================================================================

    public function test_case_H_options_are_forwarded_to_internal_runner(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $options = [
            'demand_listing_type' => 'buyer',
            'demand_listing_id'   => 7,
            'supply_listing_type' => 'seller',
            'supply_listing_id'   => 1,
        ];

        $classification = $this->makeClassification('compatibility_signals');

        $mocks['classifier']->method('classify')->willReturn($classification);

        $mocks['internalRunner']->expects($this->once())
            ->method('run')
            ->with('seller', 1, 'compatibility_signals', 'How compatible is the buyer?', $options)
            ->willReturn($this->makeInternalResult());

        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'How compatible is the buyer?', $options);

        $this->assertTrue($result['success']);
    }

    public function test_case_H_empty_options_forwarded_when_not_provided(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification());

        $mocks['internalRunner']->expects($this->once())
            ->method('run')
            ->with('seller', 1, 'property_standout', 'q', [])
            ->willReturn($this->makeInternalResult());

        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'q');

        $this->assertTrue($result['success']);
    }

    // =========================================================================
    // Case I — Static governance scan
    // =========================================================================

    public function test_case_I_service_file_exists(): void
    {
        $this->assertFileExists(
            $this->serviceFilePath(),
            'AskAiRunnerV2Service file does not exist at expected path'
        );
    }

    public function test_case_I_service_file_contains_no_db_facade_calls(): void
    {
        $path    = $this->serviceFilePath();
        $content = file_get_contents($path);

        $codeLines = implode("\n", array_filter(
            explode("\n", $content),
            static function (string $line): bool {
                $trimmed = ltrim($line);
                return !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//');
            }
        ));

        $prohibited = [
            'DB::table(',
            'DB::select(',
            'DB::insert(',
            'DB::update(',
            'DB::delete(',
            'DB::statement(',
            '->save(',
            '->update(',
            '->create(',
            '->delete(',
            '->insert(',
        ];

        foreach ($prohibited as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "AskAiRunnerV2Service must not contain DB call '{$term}'"
            );
        }
    }

    public function test_case_I_service_file_contains_no_direct_openai_sdk_instantiation(): void
    {
        $path    = $this->serviceFilePath();
        $content = file_get_contents($path);

        $codeLines = implode("\n", array_filter(
            explode("\n", $content),
            static function (string $line): bool {
                $trimmed = ltrim($line);
                return !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//');
            }
        ));

        $prohibited = [
            'use OpenAI\\',
            'use OpenAi\\',
            'OpenAI::',
            'new OpenAI(',
            'new Client(',
            'use GuzzleHttp\\',
            'Http::post(',
            'Http::get(',
            'curl_exec(',
        ];

        foreach ($prohibited as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "AskAiRunnerV2Service must not directly instantiate or import '{$term}'"
            );
        }
    }

    public function test_case_I_service_file_contains_no_hardcoded_api_keys(): void
    {
        $path    = $this->serviceFilePath();
        $content = file_get_contents($path);

        $codeLines = implode("\n", array_filter(
            explode("\n", $content),
            static function (string $line): bool {
                $trimmed = ltrim($line);
                return !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//');
            }
        ));

        $prohibited = [
            'sk-',
            'api_key',
            'OPENAI_API_KEY',
            'openai_api_key',
        ];

        foreach ($prohibited as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "AskAiRunnerV2Service must not contain hardcoded API key pattern '{$term}'"
            );
        }
    }
}
