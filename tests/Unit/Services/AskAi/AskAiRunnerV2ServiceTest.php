<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiFinalResponseBuilderService;
use App\Services\AskAi\AskAiFollowUpQuestionService;
use App\Services\AskAi\AskAiIntentNormalizerService;
use App\Services\AskAi\AskAiInternalRunnerService;
use App\Services\AskAi\AskAiOpenAiAdapterService;
use App\Services\AskAi\AskAiQuestionClassifierService;
use App\Services\AskAi\AskAiKnowledgeSearchService;
use App\Services\AskAi\AskAiRunnerV2Service;
use PHPUnit\Framework\TestCase;

/**
 * AskAiRunnerV2ServiceTest
 *
 * Pure unit tests — no database, no Laravel TestCase, no DB traits.
 * All four pipeline dependencies are mocked via createMock().
 *
 * Test coverage (cases A–J):
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
 *   J. Intent normalizer integration —
 *      J1. Flag-off (normalizer null): 'unsupported' path is unchanged; normalizer never called.
 *      J2. Flag-off (isEnabled=false): 'unsupported' path is unchanged; normalize() never called.
 *      J3. Flag-on + normalizer returns a key: runner re-enters listing_facts; classification
 *          carries normalized_field_key; nine-key output contract preserved.
 *      J4. Flag-on + normalizer returns null: runner falls through to original unsupported path unchanged.
 *      J5. Prohibited question type: normalizer isEnabled() is never called (Layer 1 wins).
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
     * Build createMock instances for all five dependencies.
     */
    private function makeMocks(): array
    {
        $followUpMock = $this->createMock(AskAiFollowUpQuestionService::class);
        $followUpMock->method('forResult')->willReturn([]);

        return [
            'classifier'     => $this->createMock(AskAiQuestionClassifierService::class),
            'internalRunner' => $this->createMock(AskAiInternalRunnerService::class),
            'adapter'        => $this->createMock(AskAiOpenAiAdapterService::class),
            'finalBuilder'   => $this->createMock(AskAiFinalResponseBuilderService::class),
            'followUpService'=> $followUpMock,
        ];
    }

    private function makeRunner(array $mocks): AskAiRunnerV2Service
    {
        return new AskAiRunnerV2Service(
            $mocks['classifier'],
            $mocks['internalRunner'],
            $mocks['adapter'],
            $mocks['finalBuilder'],
            $mocks['followUpService'],
            $mocks['normalizer']       ?? null,
            $mocks['knowledgeSearch']  ?? null
        );
    }

    /**
     * Build a mock AskAiKnowledgeSearchService that returns the given outcome.
     *
     * @param  string      $outcome  database_hit|blank_information_not_provided|restricted|not_found
     * @param  string|null $answer   Stored answer text (for hit/blank outcomes).
     */
    private function makeSearchMock(string $outcome, ?string $answer = 'Stored answer.'): AskAiKnowledgeSearchService
    {
        $source = [
            'answer_source'    => ($outcome === 'database_hit' || $outcome === 'blank_information_not_provided' || $outcome === 'restricted') ? 'database' : null,
            'snapshot_id'      => $outcome !== 'not_found' ? 1 : null,
            'canonical_key'    => $outcome !== 'not_found' ? 'test_key' : null,
            'match_type'       => $outcome !== 'not_found' ? 'faq_canonical_key' : null,
            'snapshot_version' => $outcome !== 'not_found' ? 1 : null,
        ];

        $mock = $this->createMock(AskAiKnowledgeSearchService::class);
        $mock->method('search')->willReturn([
            'outcome' => $outcome,
            'answer'  => in_array($outcome, ['database_hit', 'blank_information_not_provided']) ? $answer : null,
            'source'  => $source,
        ]);

        return $mock;
    }

    /**
     * Build a mock AskAiIntentNormalizerService.
     *
     * @param  bool        $isEnabled     Value isEnabled() should return.
     * @param  string|null $normalizedKey Value normalize() should return; null if not configured.
     * @param  string|null $lastStatus    Value getLastStatus() should return after normalize().
     * @param  string|null $lastError     Value getLastError() should return after normalize().
     * @return AskAiIntentNormalizerService&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeNormalizerMock(
        bool $isEnabled,
        ?string $normalizedKey = null,
        ?string $lastStatus = null,
        ?string $lastError = null
    ) {
        $mock = $this->createMock(AskAiIntentNormalizerService::class);
        $mock->method('isEnabled')->willReturn($isEnabled);
        $mock->method('buildKnownFieldKeys')->willReturn([
            'listing.bedrooms',
            'listing.hoa_fee',
            'faq_answers.hvac_system_age',
        ]);
        if ($normalizedKey !== null) {
            $mock->method('normalize')->willReturn($normalizedKey);
            $mock->method('getLastStatus')->willReturn($lastStatus ?? 'matched');
        } else {
            $mock->method('normalize')->willReturn(null);
            $mock->method('getLastStatus')->willReturn($lastStatus ?? 'unknown');
        }
        $mock->method('getLastError')->willReturn($lastError);
        return $mock;
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

        // The runner appends follow_up_questions to final_response after the builder runs;
        // assert all original builder keys are preserved and the new key is present.
        foreach ($finalResponse as $key => $value) {
            $this->assertArrayHasKey($key, $result['final_response'], "final_response missing builder key '{$key}'");
            $this->assertSame($value, $result['final_response'][$key], "final_response key '{$key}' does not match builder output");
        }
        $this->assertArrayHasKey('follow_up_questions', $result['final_response']);
        $this->assertIsArray($result['final_response']['follow_up_questions']);
    }

    public function test_case_A_happy_path_final_response_contains_follow_up_questions(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification());
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What makes this property stand out?');

        $this->assertArrayHasKey('follow_up_questions', $result['final_response']);
        $this->assertIsArray($result['final_response']['follow_up_questions']);
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
    // Case E — Adapter failure with prompt_ready: universal fallback returns insufficient_context
    //
    // When the prompt package is prompt_ready and the adapter fails, the universal
    // prompt-ready adapter-failed fallback fires BEFORE finalResponseBuilder.build().
    // This means the result is always 'insufficient_context' (not 'failed') and
    // error is null (the adapter error is intentionally swallowed by the fallback
    // to avoid surfacing raw OpenAI error strings to users).
    // =========================================================================

    public function test_case_E_adapter_failure_returns_insufficient_context_not_failed(): void
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
        $mocks['finalBuilder']->expects($this->never())->method('build');

        $result = $runner->run('seller', 1, 'What makes this property stand out?');

        $this->assertFalse($result['success']);
        $this->assertSame(
            'insufficient_context',
            $result['status'],
            'Universal prompt-ready fallback must return insufficient_context, never failed.'
        );
    }

    public function test_case_E_adapter_failure_error_is_null_on_universal_fallback(): void
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
        $mocks['finalBuilder']->expects($this->never())->method('build');

        $result = $runner->run('seller', 1, 'What makes this property stand out?');

        $this->assertNull(
            $result['error'],
            'Universal fallback must set error=null; adapter error string must not surface at top level.'
        );
        $this->assertSame(
            'A response could not be generated right now. Please try again shortly.',
            $result['final_response']['answer'] ?? null,
            'Universal fallback must return the clean try-again message.'
        );
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

    // =========================================================================
    // Case J — Intent normalizer integration
    // =========================================================================

    // ── J1 ── No normalizer injected (null): unsupported path unchanged ───────

    public function test_case_J1_no_normalizer_injected_unsupported_path_unchanged(): void
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
            'error'   => null,
        ]));

        $result = $runner->run('seller', 1, 'Some ambiguous question about the A/C?');

        $this->assertFalse($result['success']);
        $this->assertSame('unsupported', $result['status']);
    }

    // ── J2 ── Normalizer injected but isEnabled() = false: normalize() never called ──

    public function test_case_J2_flag_off_normalize_is_never_called(): void
    {
        $mocks = $this->makeMocks();

        $normalizerMock = $this->createMock(AskAiIntentNormalizerService::class);
        $normalizerMock->method('isEnabled')->willReturn(false);
        $normalizerMock->expects($this->never())->method('normalize');
        $mocks['normalizer'] = $normalizerMock;

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
            'error'   => null,
        ]));

        $result = $runner->run('seller', 1, 'Does the A/C work well?');

        $this->assertFalse($result['success']);
        $this->assertSame('unsupported', $result['status']);
    }

    public function test_case_J2_flag_off_classification_has_no_normalized_field_key(): void
    {
        $mocks = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMock(false);

        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => false,
            'status'         => 'unsupported',
            'prompt_package' => ['status' => 'unsupported', 'required_disclosures' => [], 'source_attribution' => [], 'refusal_template' => null],
        ]));
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse([
            'success' => false,
            'status'  => 'unsupported',
            'error'   => null,
        ]));

        $result = $runner->run('seller', 1, 'Does the A/C work well?');

        $this->assertArrayNotHasKey('normalized_field_key', $result['classification']);
    }

    // ── J3 ── Flag-on + normalizer maps to a key: runner re-enters listing_facts ──

    public function test_case_J3_flag_on_and_normalizer_returns_key_runner_re_enters_listing_facts(): void
    {
        $mocks = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMock(true, 'faq_answers.hvac_system_age');

        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));

        // The internal runner must be called with 'listing_facts' after normalization.
        $mocks['internalRunner']->expects($this->once())
            ->method('run')
            ->with('seller', 1, 'listing_facts', 'Does the A/C work well?', $this->anything())
            ->willReturn($this->makeInternalResult([
                'contract' => ['status' => 'contract_ready', 'question_type' => 'listing_facts'],
            ]));

        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'Does the A/C work well?');

        $this->assertTrue($result['success']);
    }

    public function test_case_J3_flag_on_normalized_field_key_propagated_into_options(): void
    {
        $mocks = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMock(true, 'faq_answers.hvac_system_age');

        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));

        // The $options passed to the internal runner must contain normalized_field_key.
        $mocks['internalRunner']->expects($this->once())
            ->method('run')
            ->with(
                'seller',
                1,
                'listing_facts',
                'Does the A/C work well?',
                $this->callback(function (array $opts): bool {
                    return isset($opts['normalized_field_key'])
                        && $opts['normalized_field_key'] === 'faq_answers.hvac_system_age';
                })
            )
            ->willReturn($this->makeInternalResult());

        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $runner->run('seller', 1, 'Does the A/C work well?');
    }

    public function test_case_J3_flag_on_normalized_field_key_in_options_merged_with_existing_options(): void
    {
        $mocks = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMock(true, 'listing.hoa_fee');

        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));

        // Existing options (e.g. pair keys) must be preserved alongside normalized_field_key.
        $mocks['internalRunner']->expects($this->once())
            ->method('run')
            ->with(
                'seller',
                1,
                'listing_facts',
                'What is the HOA?',
                $this->callback(function (array $opts): bool {
                    return isset($opts['normalized_field_key'])
                        && $opts['normalized_field_key'] === 'listing.hoa_fee'
                        && isset($opts['some_existing_option'])
                        && $opts['some_existing_option'] === 'preserved';
                })
            )
            ->willReturn($this->makeInternalResult());

        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $runner->run('seller', 1, 'What is the HOA?', ['some_existing_option' => 'preserved']);
    }

    public function test_case_J3_flag_on_classification_carries_normalized_field_key(): void
    {
        $mocks = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMock(true, 'faq_answers.hvac_system_age');

        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'Does the A/C work well?');

        $this->assertArrayHasKey('normalized_field_key', $result['classification']);
        $this->assertSame('faq_answers.hvac_system_age', $result['classification']['normalized_field_key']);
        $this->assertSame('listing_facts', $result['classification']['question_type']);
    }

    public function test_case_J3_flag_on_nine_key_output_contract_preserved(): void
    {
        $mocks = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMock(true, 'listing.hoa_fee');

        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What is the HOA situation here?');

        foreach (self::REQUIRED_RESULT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Result missing required key '{$key}' after normalization");
        }
    }

    // ── J4 ── Flag-on + normalizer returns null: fall through to unsupported ──

    public function test_case_J4_flag_on_normalizer_returns_null_falls_through_to_unsupported(): void
    {
        $mocks = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMock(true, null);

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
            'error'   => null,
        ]));

        $result = $runner->run('seller', 1, 'Something OpenAI cannot normalize.');

        $this->assertFalse($result['success']);
        $this->assertSame('unsupported', $result['status']);
        $this->assertSame('unsupported', $result['classification']['question_type']);
    }

    public function test_case_J4_flag_on_normalizer_null_result_has_no_normalized_field_key(): void
    {
        $mocks = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMock(true, null);

        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => false,
            'status'         => 'unsupported',
            'prompt_package' => ['status' => 'unsupported', 'required_disclosures' => [], 'source_attribution' => [], 'refusal_template' => null],
        ]));
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse([
            'success' => false,
            'status'  => 'unsupported',
            'error'   => null,
        ]));

        $result = $runner->run('seller', 1, 'Something OpenAI cannot normalize.');

        $this->assertArrayNotHasKey('normalized_field_key', $result['classification']);
    }

    // ── J5 ── Prohibited question: normalizer isEnabled() is never invoked ────

    public function test_case_J5_prohibited_question_normalizer_is_enabled_never_called(): void
    {
        $mocks = $this->makeMocks();

        $normalizerMock = $this->createMock(AskAiIntentNormalizerService::class);
        $normalizerMock->expects($this->never())->method('isEnabled');
        $normalizerMock->expects($this->never())->method('normalize');
        $mocks['normalizer'] = $normalizerMock;

        $runner = $this->makeRunner($mocks);

        $blockedPackage = [
            'status'               => 'blocked',
            'question_type'        => 'prohibited',
            'required_disclosures' => [],
            'source_attribution'   => [],
            'refusal_template'     => 'Not permitted.',
        ];

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('prohibited'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => false,
            'status'         => 'blocked',
            'prompt_package' => $blockedPackage,
        ]));
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse([
            'success'         => false,
            'status'          => 'blocked',
            'answer'          => null,
            'refusal_message' => 'Not permitted.',
            'error'           => null,
        ]));

        $result = $runner->run('seller', 1, 'Is this a safe neighborhood?');

        $this->assertFalse($result['success']);
        $this->assertSame('blocked', $result['status']);
    }

    // =========================================================================
    // Case K — Trace key is present and correct on every run() exit path
    // =========================================================================

    // ── K1 ── Happy path: trace key present with correct final_status ─────────

    public function test_case_K1_happy_path_result_contains_trace_key(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification());
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What makes this property stand out?');

        $this->assertArrayHasKey('trace', $result);
        $this->assertIsArray($result['trace']);
    }

    public function test_case_K1_happy_path_trace_final_status_is_ready(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification());
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What makes this property stand out?');

        $this->assertSame('ready', $result['trace']['final_status']);
    }

    public function test_case_K1_happy_path_trace_classifier_result_is_populated(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('property_standout'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What makes this property stand out?');

        $this->assertSame('property_standout', $result['trace']['classifier_result']);
    }

    // ── K2 ── Missing prompt_package path: trace key present with correct status ──

    public function test_case_K2_missing_prompt_package_result_contains_trace_key(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => false,
            'status'         => 'failed',
            'prompt_package' => null,
        ]));

        $result = $runner->run('seller', 1, 'How many bedrooms?');

        $this->assertArrayHasKey('trace', $result);
        $this->assertIsArray($result['trace']);
    }

    public function test_case_K2_missing_prompt_package_trace_final_status_is_failed(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => false,
            'status'         => 'failed',
            'prompt_package' => null,
        ]));

        $result = $runner->run('seller', 1, 'How many bedrooms?');

        $this->assertSame('failed', $result['trace']['final_status']);
    }

    // ── K3 ── Exception path: trace key present with correct status ───────────

    public function test_case_K3_throwable_result_contains_trace_key(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willThrowException(new \RuntimeException('classifier exploded'));

        $result = $runner->run('seller', 1, 'What makes this property stand out?');

        $this->assertArrayHasKey('trace', $result);
        $this->assertIsArray($result['trace']);
    }

    public function test_case_K3_throwable_trace_final_status_is_failed(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willThrowException(new \RuntimeException('classifier exploded'));

        $result = $runner->run('seller', 1, 'What makes this property stand out?');

        $this->assertSame('failed', $result['trace']['final_status']);
    }

    // ── K4 ── Missing-data guard path: trace key present with correct status ──

    public function test_case_K4_missing_data_guard_result_contains_trace_key(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));

        $promptPackageWithEmptyFaq = [
            'status'               => 'prompt_ready',
            'question_type'        => 'listing_facts',
            'required_disclosures' => [],
            'source_attribution'   => [],
            'allowed_context'      => [],
            'refusal_template'     => null,
        ];

        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => true,
            'status'         => 'prompt_ready',
            'prompt_package' => $promptPackageWithEmptyFaq,
        ]));

        $result = $runner->run(
            'seller',
            1,
            'Tell me about the roof',
            ['normalized_field_key' => 'faq_answers.roof_age_and_condition']
        );

        $this->assertArrayHasKey('trace', $result);
        $this->assertIsArray($result['trace']);
    }

    public function test_case_K4_missing_data_guard_trace_final_status_is_insufficient_context(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));

        $promptPackageWithEmptyFaq = [
            'status'               => 'prompt_ready',
            'question_type'        => 'listing_facts',
            'required_disclosures' => [],
            'source_attribution'   => [],
            'allowed_context'      => [],
            'refusal_template'     => null,
        ];

        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => true,
            'status'         => 'prompt_ready',
            'prompt_package' => $promptPackageWithEmptyFaq,
        ]));

        $result = $runner->run(
            'seller',
            1,
            'Tell me about the roof',
            ['normalized_field_key' => 'faq_answers.roof_age_and_condition']
        );

        $this->assertSame('insufficient_context', $result['trace']['final_status']);
    }

    // ── K5 ── FAQ key detection sets trace.faq_key_detected ──────────────────

    public function test_case_K5_faq_key_detected_set_in_trace_when_keyword_matches(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));

        $promptPackage = [
            'status'               => 'prompt_ready',
            'question_type'        => 'listing_facts',
            'required_disclosures' => [],
            'source_attribution'   => ['required_sources' => ['property_intelligence']],
            'allowed_context'      => ['faq_answers' => ['roof_age_and_condition' => ['answer_text' => 'New roof 2022']]],
            'refusal_template'     => null,
        ];

        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => true,
            'status'         => 'prompt_ready',
            'prompt_package' => $promptPackage,
        ]));
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'Tell me about the roof condition here');

        $this->assertSame('faq_answers.roof_age_and_condition', $result['trace']['faq_key_detected']);
    }

    public function test_case_K5_faq_key_detected_null_when_no_keyword_matches(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('property_standout'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What makes this property stand out?');

        $this->assertNull($result['trace']['faq_key_detected']);
    }

    // =========================================================================
    // Case L — Contract governance: listing.address in listing_facts allowed_context
    // =========================================================================

    public function test_case_L_contract_service_listing_facts_includes_listing_address(): void
    {
        $contractServicePath = dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiResponseContractService.php';
        $this->assertFileExists($contractServicePath);
        $content = file_get_contents($contractServicePath);
        $this->assertStringContainsString(
            "'listing.address'",
            $content,
            "AskAiResponseContractService must include 'listing.address' in listing_facts allowed_context."
        );
    }

    public function test_case_L_address_keyword_in_classifier_listing_facts(): void
    {
        $classifierPath = dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiQuestionClassifierService.php';
        $this->assertFileExists($classifierPath);
        $content = file_get_contents($classifierPath);
        $this->assertStringContainsString(
            "'address'",
            $content,
            "AskAiQuestionClassifierService must include 'address' keyword in the listing_facts block."
        );
        $this->assertStringContainsString(
            "'property address'",
            $content,
            "AskAiQuestionClassifierService must include 'property address' keyword in the listing_facts block."
        );
    }

    public function test_case_L_context_builder_extracts_address_field(): void
    {
        $contextBuilderPath = dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiContextBuilderService.php';
        $this->assertFileExists($contextBuilderPath);
        $content = file_get_contents($contextBuilderPath);
        $this->assertStringContainsString(
            "'address'",
            $content,
            "AskAiContextBuilderService must extract the 'address' field for seller listings."
        );
    }

    // =========================================================================
    // Case M — normalizer_status and normalizer_error in trace
    //
    // Verifies that run() populates trace.normalizer_status and
    // trace.normalizer_error correctly for every code path:
    //   not_applicable — question is deterministic (not 'unsupported')
    //   not_called     — 'unsupported' but normalizer null or flag off
    //   matched        — normalizer called and returned a canonical key
    //   unknown        — normalizer called, OpenAI returned 'unknown'
    //   failed         — normalizer called, operational failure
    //
    // All tests use pure unit mocks (no DB, no Laravel app).
    // The run() trace always carries normalizer_status and normalizer_error so
    // QA/staging can distinguish why normalization returned null.
    // =========================================================================

    /**
     * Build a normalizer mock that also stubs getLastStatus() and getLastError().
     *
     * @param  bool        $isEnabled
     * @param  string|null $normalizedKey   Value normalize() returns.
     * @param  string      $lastStatus      Value getLastStatus() returns.
     * @param  string|null $lastError       Value getLastError() returns.
     */
    private function makeNormalizerMockWithStatus(
        bool $isEnabled,
        ?string $normalizedKey,
        string $lastStatus,
        ?string $lastError = null
    ) {
        $mock = $this->createMock(AskAiIntentNormalizerService::class);
        $mock->method('isEnabled')->willReturn($isEnabled);
        $mock->method('buildKnownFieldKeys')->willReturn([
            'listing.bedrooms',
            'listing.hoa_fee',
            'faq_answers.hvac_system_age',
        ]);
        $mock->method('normalize')->willReturn($normalizedKey);
        $mock->method('getLastStatus')->willReturn($lastStatus);
        $mock->method('getLastError')->willReturn($lastError);
        return $mock;
    }

    // ── M1 ── Deterministic question type → normalizer_status=not_applicable ─

    public function test_case_M1_listing_facts_question_produces_not_applicable_status(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'How many bedrooms?');

        $this->assertSame('not_applicable', $result['trace']['normalizer_status']);
        $this->assertNull($result['trace']['normalizer_error']);
    }

    public function test_case_M1_non_unsupported_question_trace_normalizer_status_not_applicable(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('property_standout'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What makes this property stand out?');

        $this->assertSame('not_applicable', $result['trace']['normalizer_status']);
        $this->assertNull($result['trace']['normalizer_error']);
    }

    // ── M2 ── Prohibited question → normalizer_status=not_applicable ─────────

    public function test_case_M2_prohibited_question_produces_not_applicable_status(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('prohibited'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What race is the neighborhood?');

        $this->assertSame('not_applicable', $result['trace']['normalizer_status']);
        $this->assertNull($result['trace']['normalizer_error']);
    }

    public function test_case_M1_prohibited_question_trace_normalizer_status_not_applicable(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('prohibited'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => false,
            'status'         => 'blocked',
            'prompt_package' => ['status' => 'blocked', 'required_disclosures' => [], 'source_attribution' => [], 'refusal_template' => 'Not permitted.'],
        ]));
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse([
            'success' => false,
            'status'  => 'blocked',
        ]));

        $result = $runner->run('seller', 1, 'Is this a safe neighborhood?');

        $this->assertSame('not_applicable', $result['trace']['normalizer_status']);
        $this->assertNull($result['trace']['normalizer_error']);
    }

    // ── M3 ── Unsupported + no normalizer injected → not_called ──────────────

    public function test_case_M3_unsupported_with_null_normalizer_produces_not_called(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'Who designed this house?');

        $this->assertSame('not_called', $result['trace']['normalizer_status']);
        $this->assertNull($result['trace']['normalizer_error']);
    }

    // ── M2 ── Unsupported with normalizer null: normalizer_status = not_called ──

    public function test_case_M2_no_normalizer_injected_trace_normalizer_status_not_called(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => false,
            'status'         => 'unsupported',
            'prompt_package' => ['status' => 'unsupported', 'required_disclosures' => [], 'source_attribution' => [], 'refusal_template' => null],
        ]));
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse([
            'success' => false,
            'status'  => 'unsupported',
        ]));

        $result = $runner->run('seller', 1, 'Some ambiguous question.');

        $this->assertSame('not_called', $result['trace']['normalizer_status']);
        $this->assertNull($result['trace']['normalizer_error']);
    }

    // ── M4 ── Unsupported + flag off → not_called ────────────────────────────

    public function test_case_M4_unsupported_with_flag_off_produces_not_called(): void
    {
        $mocks              = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMockWithStatus(
            false,   // isEnabled=false
            null,    // normalize() returns null
            'not_called'
        );
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'Who designed this house?');

        $this->assertSame('not_called', $result['trace']['normalizer_status']);
        $this->assertNull($result['trace']['normalizer_error']);
    }

    public function test_case_M2_flag_off_trace_normalizer_status_not_called(): void
    {
        $mocks               = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMock(false);

        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => false,
            'status'         => 'unsupported',
            'prompt_package' => ['status' => 'unsupported', 'required_disclosures' => [], 'source_attribution' => [], 'refusal_template' => null],
        ]));
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse([
            'success' => false,
            'status'  => 'unsupported',
        ]));

        $result = $runner->run('seller', 1, 'Some ambiguous question.');

        $this->assertSame('not_called', $result['trace']['normalizer_status']);
        $this->assertNull($result['trace']['normalizer_error']);
    }

    // ── M5 ── Normalizer called + matched → normalizer_status=matched ─────────

    public function test_case_M5_normalizer_matched_produces_matched_status(): void
    {
        $mocks              = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMockWithStatus(
            true,
            'listing.bedrooms',
            'matched',
            null
        );
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'How many rooms does this place have?');

        $this->assertSame('matched', $result['trace']['normalizer_status']);
        $this->assertNull($result['trace']['normalizer_error']);
    }

    // ── M3 ── Normalizer called and matched: normalizer_status = matched ───────

    public function test_case_M3_normalizer_matched_trace_normalizer_status_matched(): void
    {
        $mocks               = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMock(true, 'faq_answers.hvac_system_age', 'matched', null);

        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'Does the A/C work well?');

        $this->assertSame('matched', $result['trace']['normalizer_status']);
        $this->assertNull($result['trace']['normalizer_error']);
    }

    // ── M6 ── Normalizer called + unknown → normalizer_status=unknown ─────────

    public function test_case_M6_normalizer_unknown_produces_unknown_status(): void
    {
        $mocks              = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMockWithStatus(
            true,
            null,
            'unknown',
            null
        );
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'Who is the best architect?');

        $this->assertSame('unknown', $result['trace']['normalizer_status']);
        $this->assertNull($result['trace']['normalizer_error']);
    }

    // ── M4 ── Normalizer called and returned null (unknown): trace reflects it ─

    public function test_case_M4_normalizer_unknown_trace_normalizer_status_unknown(): void
    {
        $mocks               = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMock(true, null, 'unknown', null);

        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => false,
            'status'         => 'unsupported',
            'prompt_package' => ['status' => 'unsupported', 'required_disclosures' => [], 'source_attribution' => [], 'refusal_template' => null],
        ]));
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse([
            'success' => false,
            'status'  => 'unsupported',
        ]));

        $result = $runner->run('seller', 1, 'Something OpenAI could not map.');

        $this->assertSame('unknown', $result['trace']['normalizer_status']);
        $this->assertNull($result['trace']['normalizer_error']);
    }

    // ── M7 ── Normalizer called + operational failure → failed + error ────────

    public function test_case_M7_normalizer_rate_limit_failure_produces_failed_status(): void
    {
        $mocks              = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMockWithStatus(
            true,
            null,
            'failed',
            'rate_limited'
        );
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'Who is the best architect?');

        $this->assertSame('failed', $result['trace']['normalizer_status']);
        $this->assertSame('rate_limited', $result['trace']['normalizer_error']);
    }

    // ── M5 ── Normalizer failed (operational): trace carries status + error code ──

    public function test_case_M5_normalizer_failed_trace_normalizer_status_failed(): void
    {
        $mocks               = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMock(true, null, 'failed', 'rate_limited');

        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => false,
            'status'         => 'unsupported',
            'prompt_package' => ['status' => 'unsupported', 'required_disclosures' => [], 'source_attribution' => [], 'refusal_template' => null],
        ]));
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse([
            'success' => false,
            'status'  => 'unsupported',
        ]));

        $result = $runner->run('seller', 1, 'Something the normalizer rate-limited on.');

        $this->assertSame('failed', $result['trace']['normalizer_status']);
        $this->assertSame('rate_limited', $result['trace']['normalizer_error']);
    }

    public function test_case_M5_normalizer_failed_with_timeout_trace_carries_error_code(): void
    {
        $mocks               = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMock(true, null, 'failed', 'timeout');

        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => false,
            'status'         => 'unsupported',
            'prompt_package' => ['status' => 'unsupported', 'required_disclosures' => [], 'source_attribution' => [], 'refusal_template' => null],
        ]));
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse([
            'success' => false,
            'status'  => 'unsupported',
        ]));

        $result = $runner->run('seller', 1, 'Something that timed out.');

        $this->assertSame('timeout', $result['trace']['normalizer_error']);
    }

    // ── M6 ── Exception path carries normalizer_status and normalizer_error ───

    public function test_case_M6_throwable_before_normalization_trace_has_null_normalizer_status(): void
    {
        // Exception fires during classify() — normalizer never ran.
        // Both fields should be null (initial pre-try values).
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willThrowException(new \RuntimeException('pipeline exploded'));

        $result = $runner->run('seller', 1, 'q');

        $this->assertArrayHasKey('normalizer_status', $result['trace']);
        $this->assertArrayHasKey('normalizer_error', $result['trace']);
        $this->assertNull($result['trace']['normalizer_status']);
        $this->assertNull($result['trace']['normalizer_error']);
    }

    public function test_case_M6_throwable_after_normalization_preserves_normalizer_status(): void
    {
        // Normalizer succeeds (matched), but the internal runner throws afterwards.
        // The exception trace must carry the normalizer status that was already set.
        $mocks               = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMock(true, 'faq_answers.hvac_system_age', 'matched', null);

        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willThrowException(new \RuntimeException('internal runner exploded'));

        $result = $runner->run('seller', 1, 'Does the A/C work well?');

        $this->assertSame('failed', $result['status']);
        $this->assertSame('matched', $result['trace']['normalizer_status']);
        $this->assertNull($result['trace']['normalizer_error']);
    }

    public function test_case_M6_throwable_after_failed_normalization_preserves_normalizer_error(): void
    {
        // Normalizer fails (rate_limited), then the internal runner also throws.
        // The exception trace must carry the rate_limited error code — not null.
        $mocks               = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMock(true, null, 'failed', 'rate_limited');

        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willThrowException(new \RuntimeException('internal runner exploded'));

        $result = $runner->run('seller', 1, 'Some question.');

        $this->assertSame('failed', $result['status']);
        $this->assertSame('failed', $result['trace']['normalizer_status']);
        $this->assertSame('rate_limited', $result['trace']['normalizer_error']);
    }

    // ── M7 ── Trace keys always present regardless of path ─────────────────────

    public function test_case_M7_trace_always_has_normalizer_status_key_on_happy_path(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification());
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What makes this property stand out?');

        $this->assertArrayHasKey('normalizer_status', $result['trace']);
        $this->assertArrayHasKey('normalizer_error', $result['trace']);
    }

    public function test_case_M7_trace_always_has_normalizer_status_key_on_missing_package_path(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification());
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => false,
            'status'         => 'failed',
            'prompt_package' => null,
        ]));

        $result = $runner->run('seller', 1, 'Some question.');

        $this->assertArrayHasKey('normalizer_status', $result['trace']);
        $this->assertArrayHasKey('normalizer_error', $result['trace']);
    }

    // =========================================================================
    // Case N — Guard B empty-string fix and listing.* direct-return fallback
    //
    // N1. listing.* field present as empty string ('') fires Guard B and returns
    //     insufficient_context (not a generic failure).
    // N2. listing.* field present as null fires Guard B and returns
    //     insufficient_context (pre-existing null check still works).
    // N3. listing.* field with a real value but failed adapter returns the raw
    //     field value directly (status=ready, success=true) — parity with FAQ.
    // N4. Regression: annual_property_taxes empty-string returns correct
    //     "has not been provided" message (not generic error).
    // N5. annual_property_taxes with data and failed adapter returns field value
    //     directly via listing.* direct-return fallback.
    // =========================================================================

    public function test_case_N1_listing_field_empty_string_fires_guard_b_insufficient_context(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));

        $promptPackage = [
            'status'               => 'prompt_ready',
            'question_type'        => 'listing_facts',
            'required_disclosures' => [],
            'source_attribution'   => [],
            'allowed_context'      => [
                'listing' => ['annual_property_taxes' => ''],
            ],
            'refusal_template'     => null,
        ];

        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => true,
            'status'         => 'prompt_ready',
            'prompt_package' => $promptPackage,
        ]));

        $result = $runner->run(
            'seller',
            1,
            'What are the taxes?',
            ['normalized_field_key' => 'listing.annual_property_taxes']
        );

        $this->assertSame(false, $result['success']);
        $this->assertSame('insufficient_context', $result['status']);
        $this->assertSame('insufficient_context', $result['final_response']['status']);
    }

    public function test_case_N1_listing_field_empty_string_answer_contains_not_provided(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));

        $promptPackage = [
            'status'               => 'prompt_ready',
            'question_type'        => 'listing_facts',
            'required_disclosures' => [],
            'source_attribution'   => [],
            'allowed_context'      => [
                'listing' => ['annual_property_taxes' => ''],
            ],
            'refusal_template'     => null,
        ];

        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => true,
            'status'         => 'prompt_ready',
            'prompt_package' => $promptPackage,
        ]));

        $result = $runner->run(
            'seller',
            1,
            'What are the taxes?',
            ['normalized_field_key' => 'listing.annual_property_taxes']
        );

        $this->assertSame('Information not provided.', $result['final_response']['answer'] ?? '');
    }

    public function test_case_N2_listing_field_null_still_fires_guard_b(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));

        $promptPackage = [
            'status'               => 'prompt_ready',
            'question_type'        => 'listing_facts',
            'required_disclosures' => [],
            'source_attribution'   => [],
            'allowed_context'      => [
                'listing' => ['annual_property_taxes' => null],
            ],
            'refusal_template'     => null,
        ];

        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => true,
            'status'         => 'prompt_ready',
            'prompt_package' => $promptPackage,
        ]));

        $result = $runner->run(
            'seller',
            1,
            'What are the taxes?',
            ['normalized_field_key' => 'listing.annual_property_taxes']
        );

        $this->assertSame(false, $result['success']);
        $this->assertSame('insufficient_context', $result['status']);
    }

    public function test_case_N3_listing_field_with_data_and_failed_adapter_returns_field_value_directly(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));

        $promptPackage = [
            'status'               => 'prompt_ready',
            'question_type'        => 'listing_facts',
            'required_disclosures' => [],
            'source_attribution'   => [],
            'allowed_context'      => [
                'listing' => ['annual_property_taxes' => '4200'],
            ],
            'refusal_template'     => null,
        ];

        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => true,
            'status'         => 'prompt_ready',
            'prompt_package' => $promptPackage,
        ]));

        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult([
            'success' => false,
            'status'  => 'failed',
            'error'   => 'OpenAI unavailable',
        ]));

        $result = $runner->run(
            'seller',
            1,
            'What are the taxes?',
            ['normalized_field_key' => 'listing.annual_property_taxes']
        );

        $this->assertSame(true, $result['success']);
        $this->assertSame('ready', $result['status']);
        $this->assertSame('4200', $result['final_response']['answer']);
    }

    public function test_case_N3_listing_field_fallback_result_has_all_nine_keys(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));

        $promptPackage = [
            'status'               => 'prompt_ready',
            'question_type'        => 'listing_facts',
            'required_disclosures' => [],
            'source_attribution'   => [],
            'allowed_context'      => [
                'listing' => ['annual_property_taxes' => '3500'],
            ],
            'refusal_template'     => null,
        ];

        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => true,
            'status'         => 'prompt_ready',
            'prompt_package' => $promptPackage,
        ]));

        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult([
            'success' => false,
            'status'  => 'failed',
            'error'   => 'OpenAI unavailable',
        ]));

        $result = $runner->run(
            'seller',
            1,
            'What are the taxes?',
            ['normalized_field_key' => 'listing.annual_property_taxes']
        );

        foreach (self::REQUIRED_RESULT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Result missing key: {$key}");
        }
    }

    public function test_case_N4_annual_property_taxes_empty_string_returns_not_provided_message(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));

        $promptPackage = [
            'status'               => 'prompt_ready',
            'question_type'        => 'listing_facts',
            'required_disclosures' => [],
            'source_attribution'   => [],
            'allowed_context'      => [
                'listing' => ['annual_property_taxes' => ''],
            ],
            'refusal_template'     => null,
        ];

        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => true,
            'status'         => 'prompt_ready',
            'prompt_package' => $promptPackage,
        ]));

        $result = $runner->run(
            'seller',
            1,
            'What are the taxes?',
            ['normalized_field_key' => 'listing.annual_property_taxes']
        );

        $answer = $result['final_response']['answer'] ?? '';
        $this->assertStringNotContainsString('could not generate', $answer);
        $this->assertSame('Information not provided.', $answer);
    }

    public function test_case_N5_annual_property_taxes_populated_failed_adapter_returns_value_directly(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));

        $promptPackage = [
            'status'               => 'prompt_ready',
            'question_type'        => 'listing_facts',
            'required_disclosures' => [],
            'source_attribution'   => [],
            'allowed_context'      => [
                'listing' => ['annual_property_taxes' => '5100'],
            ],
            'refusal_template'     => null,
        ];

        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => true,
            'status'         => 'prompt_ready',
            'prompt_package' => $promptPackage,
        ]));

        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult([
            'success' => false,
            'status'  => 'failed',
            'error'   => 'OpenAI timeout',
        ]));

        $result = $runner->run(
            'seller',
            1,
            'What are the annual property taxes?',
            ['normalized_field_key' => 'listing.annual_property_taxes']
        );

        $this->assertSame(true, $result['success']);
        $this->assertSame('ready', $result['status']);
        $this->assertSame('5100', $result['final_response']['answer']);
        $this->assertSame('ready', $result['trace']['final_status']);
    }

    // =========================================================================
    // Case P — Router trace fields: deterministic_question_type, router_called,
    //           router_status, router_context_path
    //
    // Verifies that run() always populates the four new router observability
    // fields in the trace regardless of which code path fires.
    // =========================================================================

    // ── P1 ── trace always has the four router keys ──────────────────────────

    public function test_case_P1_trace_always_has_deterministic_question_type_key(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'How many bedrooms?');

        $this->assertArrayHasKey('deterministic_question_type', $result['trace']);
    }

    public function test_case_P1_trace_always_has_router_called_key(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification());
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What makes this property stand out?');

        $this->assertArrayHasKey('router_called', $result['trace']);
    }

    public function test_case_P1_trace_always_has_router_status_key(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification());
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What makes this property stand out?');

        $this->assertArrayHasKey('router_status', $result['trace']);
    }

    public function test_case_P1_trace_always_has_router_context_path_key(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification());
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What makes this property stand out?');

        $this->assertArrayHasKey('router_context_path', $result['trace']);
    }

    // ── P2 ── deterministic_question_type reflects classifier output ──────────

    public function test_case_P2_deterministic_question_type_is_classifier_result(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'How many bedrooms?');

        $this->assertSame('listing_facts', $result['trace']['deterministic_question_type']);
    }

    public function test_case_P2_deterministic_question_type_unsupported(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'Who designed this house?');

        $this->assertSame('unsupported', $result['trace']['deterministic_question_type']);
    }

    // ── P3 ── router_called reflects whether the normalizer fired ─────────────

    public function test_case_P3_router_called_N_for_deterministic_questions(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'How many bedrooms?');

        $this->assertSame('N', $result['trace']['router_called']);
    }

    public function test_case_P3_router_called_N_when_normalizer_flag_off(): void
    {
        $mocks               = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMock(false);
        $runner              = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'Who designed this house?');

        $this->assertSame('N', $result['trace']['router_called']);
    }

    public function test_case_P3_router_called_Y_when_normalizer_enabled_and_unsupported(): void
    {
        $mocks               = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMock(true, 'listing.bedrooms', 'matched');
        $runner              = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'How many rooms does this place have?');

        $this->assertSame('Y', $result['trace']['router_called']);
    }

    // ── P4 ── router_status maps from normalizer status ───────────────────────

    public function test_case_P4_router_status_not_called_for_deterministic_question(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('property_standout'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What are the key features?');

        $this->assertSame('not_called', $result['trace']['router_status']);
    }

    public function test_case_P4_router_status_matched_when_normalizer_matched(): void
    {
        $mocks               = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMock(true, 'listing.bedrooms', 'matched');
        $runner              = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'How many rooms does this place have?');

        $this->assertSame('matched', $result['trace']['router_status']);
    }

    public function test_case_P4_router_status_unsupported_when_normalizer_unknown(): void
    {
        $mocks               = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMock(true, null, 'unknown');
        $runner              = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'Who is the best architect?');

        $this->assertSame('unsupported', $result['trace']['router_status']);
    }

    public function test_case_P4_router_status_failed_when_normalizer_failed(): void
    {
        $mocks               = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMock(true, null, 'failed', 'timeout');
        $runner              = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'Who is the best architect?');

        $this->assertSame('failed', $result['trace']['router_status']);
    }

    // ── P5 ── router_context_path ──────────────────────────────────────────────

    public function test_case_P5_router_context_path_null_for_deterministic_question(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'How many bedrooms?');

        $this->assertNull($result['trace']['router_context_path']);
    }

    public function test_case_P5_router_context_path_populated_from_normalizer(): void
    {
        // Build a normalizer mock that also stubs getLastContextPath()
        $mock = $this->createMock(AskAiIntentNormalizerService::class);
        $mock->method('isEnabled')->willReturn(true);
        $mock->method('buildKnownFieldKeys')->willReturn(['listing.bedrooms', 'faq_answers.hvac_system_age']);
        $mock->method('normalize')->willReturn('listing.bedrooms');
        $mock->method('getLastStatus')->willReturn('matched');
        $mock->method('getLastError')->willReturn(null);
        $mock->method('getLastContextPath')->willReturn('listing.bedrooms');

        $mocks               = $this->makeMocks();
        $mocks['normalizer'] = $mock;
        $runner              = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'How many rooms does this place have?');

        $this->assertSame('listing.bedrooms', $result['trace']['router_context_path']);
    }

    public function test_case_P5_router_context_path_null_when_normalizer_not_called(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        // No normalizer injected → router not called
        $result = $runner->run('seller', 1, 'Who designed this house?');

        $this->assertNull($result['trace']['router_context_path']);
    }

    // ── P6 ── prohibited from router re-classifies question ──────────────────

    public function test_case_P6_router_prohibited_re_classifies_as_prohibited(): void
    {
        $mock = $this->createMock(AskAiIntentNormalizerService::class);
        $mock->method('isEnabled')->willReturn(true);
        $mock->method('buildKnownFieldKeys')->willReturn(['listing.bedrooms']);
        $mock->method('normalize')->willReturn(null);
        $mock->method('getLastStatus')->willReturn('prohibited');
        $mock->method('getLastError')->willReturn(null);
        $mock->method('getLastContextPath')->willReturn(null);

        $mocks               = $this->makeMocks();
        $mocks['normalizer'] = $mock;
        $runner              = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => false,
            'status'         => 'blocked',
            'prompt_package' => ['status' => 'blocked', 'required_disclosures' => [], 'source_attribution' => [], 'refusal_template' => 'Not permitted.'],
        ]));
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse([
            'success' => false,
            'status'  => 'blocked',
        ]));

        $result = $runner->run('seller', 1, 'Is this a fair housing violation question?');

        $this->assertSame('prohibited', $result['trace']['router_status']);
    }

    // ── P7 ── router trace fields present on exception path ──────────────────

    public function test_case_P7_exception_path_has_router_called_key(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willThrowException(new \RuntimeException('pipeline exploded'));

        $result = $runner->run('seller', 1, 'q');

        $this->assertArrayHasKey('router_called', $result['trace']);
        $this->assertArrayHasKey('router_status', $result['trace']);
        $this->assertArrayHasKey('router_context_path', $result['trace']);
    }

    // ── P8 ── run() file passes role to normalizer ────────────────────────────

    public function test_case_P8_runner_file_passes_listing_type_to_normalize(): void
    {
        $content = file_get_contents(
            app_path('Services/AskAi/AskAiRunnerV2Service.php')
        );
        $this->assertStringContainsString(
            'normalize($question, $knownFieldKeys, $listingType)',
            $content,
            'run() must pass $listingType as the third argument to normalize()'
        );
    }

    // =========================================================================
    // Case Q — Phase 4: database-first knowledge search short-circuit paths
    //
    //   Q1. database_hit   — adapter is never called; status=ready;
    //                        outcome_category=database_hit; source.answer_source=database.
    //   Q2. blank          — adapter is never called; status=insufficient_context;
    //                        outcome_category=blank_information_not_provided.
    //   Q3. restricted     — adapter is never called; status=blocked;
    //                        outcome_category=blocked_restricted.
    //   Q4. not_found      — adapter IS called (falls through to OpenAI);
    //                        outcome_category=openai_fallback.
    //   Q5. blocked pkg    — prompt_package is blocked (no search service);
    //                        outcome_category=blocked_restricted (not openai_fallback).
    //   Q6. unsupported pkg— prompt_package is unsupported;
    //                        outcome_category=unsupported.
    // =========================================================================

    // ── Q1 ── database_hit short-circuit ──────────────────────────────────────

    public function test_case_Q1_database_hit_adapter_never_called(): void
    {
        $mocks                   = $this->makeMocks();
        $mocks['knowledgeSearch'] = $this->makeSearchMock('database_hit', 'Bedrooms: 3');
        $runner                  = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'prompt_package' => [
                'status'               => 'prompt_ready',
                'question_type'        => 'listing_facts',
                'required_disclosures' => [],
                'source_attribution'   => [],
                'refusal_template'     => null,
            ],
        ]));
        $mocks['adapter']->expects($this->never())->method('generate');

        $result = $runner->run('seller', 1, 'How many bedrooms?');

        $this->assertSame('ready', $result['status']);
        $this->assertTrue($result['success']);
    }

    public function test_case_Q1_database_hit_outcome_category_is_database_hit(): void
    {
        $mocks                   = $this->makeMocks();
        $mocks['knowledgeSearch'] = $this->makeSearchMock('database_hit', 'Bedrooms: 3');
        $runner                  = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'prompt_package' => [
                'status'               => 'prompt_ready',
                'question_type'        => 'listing_facts',
                'required_disclosures' => [],
                'source_attribution'   => [],
                'refusal_template'     => null,
            ],
        ]));
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());

        $result = $runner->run('seller', 1, 'How many bedrooms?');

        $this->assertSame('database_hit', $result['outcome_category']);
    }

    public function test_case_Q1_database_hit_source_metadata_has_database_answer_source(): void
    {
        $mocks                   = $this->makeMocks();
        $mocks['knowledgeSearch'] = $this->makeSearchMock('database_hit', 'Bedrooms: 3');
        $runner                  = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'prompt_package' => [
                'status'               => 'prompt_ready',
                'question_type'        => 'listing_facts',
                'required_disclosures' => [],
                'source_attribution'   => [],
                'refusal_template'     => null,
            ],
        ]));
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());

        $result = $runner->run('seller', 1, 'How many bedrooms?');

        $source = $result['final_response']['source'] ?? null;
        $this->assertNotNull($source, 'final_response must contain source key on database_hit');
        $this->assertSame('database', $source['answer_source']);
    }

    // ── Q2 ── blank short-circuit ─────────────────────────────────────────────

    public function test_case_Q2_blank_adapter_never_called(): void
    {
        $mocks                   = $this->makeMocks();
        $mocks['knowledgeSearch'] = $this->makeSearchMock('blank_information_not_provided', 'Information not provided.');
        $runner                  = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'prompt_package' => [
                'status'               => 'prompt_ready',
                'question_type'        => 'listing_facts',
                'required_disclosures' => [],
                'source_attribution'   => [],
                'refusal_template'     => null,
            ],
        ]));
        $mocks['adapter']->expects($this->never())->method('generate');

        $result = $runner->run('seller', 1, 'What is the lot size?');

        $this->assertSame('insufficient_context', $result['status']);
        $this->assertFalse($result['success']);
    }

    public function test_case_Q2_blank_outcome_category_is_blank_information_not_provided(): void
    {
        $mocks                   = $this->makeMocks();
        $mocks['knowledgeSearch'] = $this->makeSearchMock('blank_information_not_provided', 'Information not provided.');
        $runner                  = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'prompt_package' => [
                'status'               => 'prompt_ready',
                'question_type'        => 'listing_facts',
                'required_disclosures' => [],
                'source_attribution'   => [],
                'refusal_template'     => null,
            ],
        ]));
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());

        $result = $runner->run('seller', 1, 'What is the lot size?');

        $this->assertSame('blank_information_not_provided', $result['outcome_category']);
    }

    // ── Q3 ── restricted short-circuit ───────────────────────────────────────

    public function test_case_Q3_restricted_adapter_never_called(): void
    {
        $mocks                   = $this->makeMocks();
        $mocks['knowledgeSearch'] = $this->makeSearchMock('restricted');
        $runner                  = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'prompt_package' => [
                'status'               => 'prompt_ready',
                'question_type'        => 'listing_facts',
                'required_disclosures' => [],
                'source_attribution'   => [],
                'refusal_template'     => 'This information is restricted.',
            ],
        ]));
        $mocks['adapter']->expects($this->never())->method('generate');

        $result = $runner->run('seller', 1, 'What is the owner name?');

        $this->assertSame('blocked', $result['status']);
        $this->assertFalse($result['success']);
    }

    public function test_case_Q3_restricted_outcome_category_is_blocked_restricted(): void
    {
        $mocks                   = $this->makeMocks();
        $mocks['knowledgeSearch'] = $this->makeSearchMock('restricted');
        $runner                  = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'prompt_package' => [
                'status'               => 'prompt_ready',
                'question_type'        => 'listing_facts',
                'required_disclosures' => [],
                'source_attribution'   => [],
                'refusal_template'     => 'This information is restricted.',
            ],
        ]));
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());

        $result = $runner->run('seller', 1, 'What is the owner name?');

        $this->assertSame('blocked_restricted', $result['outcome_category']);
    }

    // ── Q4 ── not_found falls through to OpenAI ───────────────────────────────

    public function test_case_Q4_not_found_adapter_is_called(): void
    {
        $mocks                   = $this->makeMocks();
        $mocks['knowledgeSearch'] = $this->makeSearchMock('not_found');
        $runner                  = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification());
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->expects($this->once())->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What makes this property special?');

        $this->assertSame('ready', $result['status']);
    }

    public function test_case_Q4_not_found_outcome_category_is_openai_fallback(): void
    {
        $mocks                   = $this->makeMocks();
        $mocks['knowledgeSearch'] = $this->makeSearchMock('not_found');
        $runner                  = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification());
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What makes this property special?');

        $this->assertSame('openai_fallback', $result['outcome_category']);
    }

    public function test_case_Q4_not_found_source_metadata_has_openai_answer_source(): void
    {
        $mocks                   = $this->makeMocks();
        $mocks['knowledgeSearch'] = $this->makeSearchMock('not_found');
        $runner                  = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification());
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What makes this property special?');

        $source = $result['final_response']['source'] ?? null;
        $this->assertNotNull($source, 'final_response must contain source key on OpenAI path');
        $this->assertSame('openai', $source['answer_source']);
    }

    // ── Q5 ── blocked prompt_package → outcome_category=blocked_restricted ────

    public function test_case_Q5_blocked_prompt_package_outcome_category_is_blocked_restricted(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('prohibited'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => false,
            'status'         => 'blocked',
            'prompt_package' => [
                'status'               => 'blocked',
                'question_type'        => 'prohibited',
                'required_disclosures' => [],
                'source_attribution'   => [],
                'refusal_template'     => 'Cannot answer this question.',
            ],
        ]));
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse([
            'success' => false,
            'status'  => 'blocked',
        ]));

        $result = $runner->run('seller', 1, 'What is the seller tax ID?');

        $this->assertSame('blocked_restricted', $result['outcome_category'],
            'A blocked prompt_package must produce outcome_category=blocked_restricted, not openai_fallback');
    }

    // ── Q6 ── unsupported prompt_package → outcome_category=unsupported ───────

    public function test_case_Q6_unsupported_prompt_package_outcome_category_is_unsupported(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => false,
            'status'         => 'unsupported',
            'prompt_package' => [
                'status'               => 'unsupported',
                'question_type'        => 'unsupported',
                'required_disclosures' => [],
                'source_attribution'   => [],
                'refusal_template'     => null,
            ],
        ]));
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse([
            'success' => false,
            'status'  => 'unsupported',
        ]));

        $result = $runner->run('seller', 1, 'What is the meaning of life?');

        $this->assertSame('unsupported', $result['outcome_category'],
            'An unsupported prompt_package must produce outcome_category=unsupported, not openai_fallback');
    }
}
