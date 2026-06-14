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

    private function makeRunner(array $mocks, bool $enableDescriptionFallback = false): AskAiRunnerV2Service
    {
        return new AskAiRunnerV2Service(
            $mocks['classifier'],
            $mocks['internalRunner'],
            $mocks['adapter'],
            $mocks['finalBuilder'],
            $mocks['followUpService'],
            $mocks['normalizer']       ?? null,
            $mocks['knowledgeSearch']  ?? null,
            $enableDescriptionFallback
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

        $this->assertSame('This information was not provided in the listing.', $result['final_response']['answer'] ?? '');
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
        $this->assertSame('This information was not provided in the listing.', $answer);
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
                'allowed_context'      => ['listing' => ['bedrooms' => '3']],
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
                'allowed_context'      => ['listing' => ['bedrooms' => '3']],
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
                'allowed_context'      => ['listing' => ['bedrooms' => '3']],
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

    // =========================================================================
    // Case R — Regression tests for three-path listing.* graceful-failure fix
    //
    // Root Cause A: fields absent from response contract allowed_context
    //   → Guard B now fires when key is absent from listing subarray (not just null).
    // Root Cause B: universal backstop returned generic error for listing.* questions
    //   → backstop now returns "not provided" phrase when normalized_field_key starts with listing.
    // Root Cause C: direct-return fallback fell through to backstop on absent/null fields
    //   → direct-return else branch now returns "not provided" phrase.
    //
    // All three root causes share the same user-visible outcome for the fix:
    //   "This information was not provided in the listing."
    // =========================================================================

    /**
     * R1 — field key is completely absent from listing subarray in allowed_context.
     * This is Root Cause A: fields not declared in the response contract are excluded
     * from allowed_context by filterAllowedContext(), so their key never appears in
     * allowed_context['listing']. Guard B must now fire for the absent-key case.
     */
    public function test_case_R1_listing_field_absent_from_listing_subarray_fires_guard_b(): void
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
                'listing' => ['bedrooms' => '3'],
            ],
            'refusal_template'     => null,
        ];

        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => true,
            'status'         => 'prompt_ready',
            'prompt_package' => $promptPackage,
        ]));

        $mocks['adapter']->expects($this->never())->method('generate');

        $result = $runner->run(
            'seller',
            1,
            'What is the zoning?',
            ['normalized_field_key' => 'listing.zoning']
        );

        $this->assertSame(false, $result['success']);
        $this->assertSame('insufficient_context', $result['status']);
        $this->assertSame(
            'This information was not provided in the listing.',
            $result['final_response']['answer'] ?? '',
            'R1: absent listing field must return standard not-provided phrase, not generic error.'
        );
    }

    /**
     * R1b — listing subarray itself is absent from allowed_context entirely.
     * Edge-case variant: allowed_context has no 'listing' key at all.
     */
    public function test_case_R1b_no_listing_subarray_in_allowed_context_fires_guard_b(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));

        $promptPackage = [
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
            'prompt_package' => $promptPackage,
        ]));

        $mocks['adapter']->expects($this->never())->method('generate');

        $result = $runner->run(
            'seller',
            1,
            'What is the zoning?',
            ['normalized_field_key' => 'listing.zoning']
        );

        $this->assertSame(false, $result['success']);
        $this->assertSame('insufficient_context', $result['status']);
        $this->assertSame(
            'This information was not provided in the listing.',
            $result['final_response']['answer'] ?? '',
            'R1b: absent listing subarray must return standard not-provided phrase, not generic error.'
        );
    }

    /**
     * R2 — Root Cause C: direct-return else branch fires for null value.
     * This scenario is now largely prevented by Guard B (which fires before the adapter),
     * but the direct-return else branch provides belt-and-suspenders coverage in case
     * a future code path bypasses Guard B.
     *
     * Simulated: allowed_context is non-empty (so direct-return outer condition passes)
     * but zoning value is null (so the if-branch does not return the value).
     * Guard B fires first in real usage; this test verifies the else branch returns the
     * correct phrase and not the generic "could not generate" error.
     */
    public function test_case_R2_listing_field_null_never_surfaces_generic_error(): void
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
                'listing' => ['zoning' => null],
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
            'What is the zoning?',
            ['normalized_field_key' => 'listing.zoning']
        );

        $answer = $result['final_response']['answer'] ?? '';
        $this->assertStringNotContainsString(
            'could not be generated right now',
            $answer,
            'R2: null listing field must never return the generic "could not generate" error message.'
        );
        $this->assertSame(
            'This information was not provided in the listing.',
            $answer,
            'R2: null listing field must return the standard not-provided phrase.'
        );
    }

    /**
     * R3 — Standard phrase is consistent across all three Guard B input states.
     * Verifies that null value, empty string, and absent key all produce the
     * exact same "not provided" phrase — no variation permitted.
     */
    public function test_case_R3_guard_b_phrase_is_identical_for_null_empty_and_absent(): void
    {
        $makePromptPackage = static function (mixed $value, bool $includeKey): array {
            $listing = $includeKey ? ['zoning' => $value] : [];
            return [
                'status'               => 'prompt_ready',
                'question_type'        => 'listing_facts',
                'required_disclosures' => [],
                'source_attribution'   => [],
                'allowed_context'      => ['listing' => $listing],
                'refusal_template'     => null,
            ];
        };

        $runWithPackage = function (array $promptPackage): string {
            $mocks  = $this->makeMocks();
            $runner = $this->makeRunner($mocks);
            $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
            $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
                'success'        => true,
                'status'         => 'prompt_ready',
                'prompt_package' => $promptPackage,
            ]));
            $result = $runner->run('seller', 1, 'What is the zoning?', ['normalized_field_key' => 'listing.zoning']);
            return $result['final_response']['answer'] ?? '';
        };

        $nullAnswer   = $runWithPackage($makePromptPackage(null, true));
        $emptyAnswer  = $runWithPackage($makePromptPackage('', true));
        $absentAnswer = $runWithPackage($makePromptPackage(null, false));

        $this->assertSame('This information was not provided in the listing.', $nullAnswer,
            'R3: null value must return standard not-provided phrase.');
        $this->assertSame('This information was not provided in the listing.', $emptyAnswer,
            'R3: empty string must return standard not-provided phrase.');
        $this->assertSame('This information was not provided in the listing.', $absentAnswer,
            'R3: absent key must return standard not-provided phrase.');
        $this->assertSame($nullAnswer, $emptyAnswer,
            'R3: null and empty-string must produce identical answers.');
        $this->assertSame($nullAnswer, $absentAnswer,
            'R3: null and absent-key must produce identical answers.');
    }

    // =========================================================================
    // Case K — Step 1c: AI-driven routing for listing_facts with no
    //           deterministic key.  All tests use 'What brand of garbage
    //           disposal is installed?' as the novel question — specific enough
    //           that neither detectFaqFieldKey() nor detectListingFieldKey()
    //           will match it, so the Step 1c gate (no deterministic key) fires.
    // =========================================================================

    // ── K1 ── listing_facts + no key + normalizer enabled + match returned ────

    public function test_case_K1_step1c_normalizer_called_when_no_deterministic_key(): void
    {
        $mocks = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMock(
            true,
            'listing.hoa_fee',
            'matched'
        );
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What brand of garbage disposal is installed?');

        $this->assertSame('Y', $result['trace']['normalizer_called'],
            'K1: normalizer_called must be Y when Step 1c fires.');
        $this->assertSame('Y', $result['trace']['router_called'],
            'K1: router_called must be Y when Step 1c fires.');
        $this->assertSame('matched', $result['trace']['normalizer_status'],
            'K1: normalizer_status must be matched when normalizer returns a key.');
        $this->assertSame('listing.hoa_fee', $result['trace']['normalized_field_key'],
            'K1: normalized_field_key must carry the AI-resolved field path.');
        $this->assertSame('listing.hoa_fee', $result['classification']['normalized_field_key'],
            'K1: classification must also carry the resolved key for downstream guards.');
    }

    // ── K2 ── listing_facts + no key + normalizer disabled → never called ─────

    public function test_case_K2_step1c_skipped_when_normalizer_flag_off(): void
    {
        $mocks = $this->makeMocks();

        $normalizerMock = $this->createMock(AskAiIntentNormalizerService::class);
        $normalizerMock->method('isEnabled')->willReturn(false);
        $normalizerMock->expects($this->never())->method('normalize');
        $mocks['normalizer'] = $normalizerMock;

        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What brand of garbage disposal is installed?');

        $this->assertSame('N', $result['trace']['normalizer_called'],
            'K2: normalizer_called must be N when flag is off.');
        $this->assertSame('N', $result['trace']['router_called'],
            'K2: router_called must be N when flag is off.');
        $this->assertSame('not_applicable', $result['trace']['normalizer_status'],
            'K2: normalizer_status must be not_applicable when normalizer is disabled for listing_facts.');
    }

    // ── K3 ── listing_facts + HAS deterministic key → Step 1c gate fails ──────

    public function test_case_K3_step1c_not_called_when_deterministic_key_found(): void
    {
        $mocks = $this->makeMocks();

        $normalizerMock = $this->createMock(AskAiIntentNormalizerService::class);
        $normalizerMock->method('isEnabled')->willReturn(true);
        $normalizerMock->method('buildKnownFieldKeys')->willReturn([
            'listing.bedrooms', 'listing.hoa_fee', 'faq_answers.hvac_system_age',
        ]);
        $normalizerMock->expects($this->never())->method('normalize');
        $mocks['normalizer'] = $normalizerMock;

        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'How many bedrooms?');

        $this->assertSame('N', $result['trace']['normalizer_called'],
            'K3: normalizer_called must be N when Step 1b already resolved a key.');
        $this->assertSame('not_applicable', $result['trace']['normalizer_status'],
            'K3: normalizer_status must be not_applicable when deterministic key found.');
        $this->assertNotNull($result['trace']['deterministic_field_key'],
            'K3: deterministic_field_key must be set when Step 1b matched.');
    }

    // ── K4 ── listing_facts + no key + normalizer returns null (unknown) ───────

    public function test_case_K4_step1c_unknown_response_leaves_no_key_in_options(): void
    {
        $mocks = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMock(
            true,
            null,
            'unknown'
        );
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What brand of garbage disposal is installed?');

        $this->assertSame('Y', $result['trace']['normalizer_called'],
            'K4: normalizer_called must be Y even when result is unknown.');
        $this->assertSame('unknown', $result['trace']['normalizer_status'],
            'K4: normalizer_status must reflect the unknown response.');
        $this->assertSame('unsupported', $result['trace']['router_status'],
            'K4: router_status must be unsupported when normalizer returns unknown.');
        $this->assertNull($result['trace']['normalized_field_key'],
            'K4: normalized_field_key must stay null when no key was resolved.');
        $this->assertArrayNotHasKey('normalized_field_key', $result['classification'],
            'K4: classification must not have normalized_field_key for unknown response.');
    }

    // ── K5 ── listing_facts + no key + normalizer returns prohibited ───────────

    public function test_case_K5_step1c_prohibited_response_escalates_question_type(): void
    {
        $mocks = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMockWithStatus(
            true,
            null,
            'prohibited'
        );
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult([
            'success'        => false,
            'status'         => 'blocked',
            'prompt_package' => [
                'status'               => 'blocked',
                'required_disclosures' => [],
                'source_attribution'   => [],
                'refusal_template'     => 'Not permitted.',
            ],
        ]));
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse([
            'success' => false,
            'status'  => 'blocked',
        ]));

        $result = $runner->run('seller', 1, 'What brand of garbage disposal is installed?');

        $this->assertSame('prohibited', $result['trace']['final_question_type'],
            'K5: question_type must be escalated to prohibited when router flags it.');
        $this->assertSame('prohibited', $result['trace']['normalizer_status'],
            'K5: normalizer_status must carry prohibited when router flagged the question.');
        $this->assertSame('Y', $result['trace']['normalizer_called'],
            'K5: normalizer_called must be Y even on prohibited escalation.');
    }

    // ── K6 ── listing_facts + no key + normalizer operational failure ──────────

    public function test_case_K6_step1c_failed_status_leaves_no_key(): void
    {
        $mocks = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMock(
            true,
            null,
            'failed',
            'api_error'
        );
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What brand of garbage disposal is installed?');

        $this->assertSame('failed', $result['trace']['normalizer_status'],
            'K6: normalizer_status must be failed on API error.');
        $this->assertSame('api_error', $result['trace']['normalizer_error'],
            'K6: normalizer_error must carry the error code on failure.');
        $this->assertSame('failed', $result['trace']['router_status'],
            'K6: router_status must be failed when normalizer fails.');
        $this->assertNull($result['trace']['normalized_field_key'],
            'K6: normalized_field_key must stay null on normalizer failure.');
    }

    // ── K7 ── deterministic_field_key present in trace when Step 1b finds a key

    public function test_case_K7_deterministic_field_key_in_trace_when_step1b_matches(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'How many bedrooms?');

        $this->assertNotNull($result['trace']['deterministic_field_key'],
            'K7: deterministic_field_key must be set when Step 1b matched a key.');
        $this->assertStringContainsString('listing.', $result['trace']['deterministic_field_key'],
            'K7: deterministic_field_key must be a canonical listing.* path.');
    }

    // ── K8 ── deterministic_field_key is null when no Step 1b match ───────────

    public function test_case_K8_deterministic_field_key_null_when_no_step1b_match(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What brand of garbage disposal is installed?');

        $this->assertNull($result['trace']['deterministic_field_key'],
            'K8: deterministic_field_key must be null when neither keyword map matched.');
    }

    // ── K9 ── trace: normalizer_called=Y, router_called=Y, status=matched ──────

    public function test_case_K9_trace_fields_set_correctly_on_step1c_match(): void
    {
        $mocks = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMock(
            true,
            'faq_answers.hvac_system_age',
            'matched'
        );
        $runner = $this->makeRunner($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'What brand of garbage disposal is installed?');
        $trace = $result['trace'];

        $this->assertSame('Y', $trace['normalizer_called'], 'K9: normalizer_called=Y on Step 1c match.');
        $this->assertSame('Y', $trace['router_called'],     'K9: router_called=Y on Step 1c match.');
        $this->assertSame('matched', $trace['normalizer_status'], 'K9: normalizer_status=matched.');
        $this->assertSame('matched', $trace['router_status'],     'K9: router_status=matched.');
        $this->assertSame('faq_answers.hvac_system_age', $trace['normalized_field_key'],
            'K9: normalized_field_key set to the AI-resolved path.');
        $this->assertNull($trace['deterministic_field_key'],
            'K9: deterministic_field_key stays null since Step 1b found nothing.');
    }

    // ── K10 ── Regression: unsupported → Step 1a path unchanged ──────────────

    public function test_case_K10_regression_unsupported_step1a_path_unchanged(): void
    {
        $mocks = $this->makeMocks();
        $mocks['normalizer'] = $this->makeNormalizerMock(
            true,
            'listing.bedrooms',
            'matched'
        );
        $runner = $this->makeRunner($mocks);

        $unsupportedPackage = [
            'status'               => 'listing_facts',
            'question_type'        => 'listing_facts',
            'required_disclosures' => [],
            'source_attribution'   => [],
            'refusal_template'     => null,
        ];

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('unsupported'));
        $mocks['internalRunner']->method('run')->willReturn($this->makeInternalResult());
        $mocks['adapter']->method('generate')->willReturn($this->makeAdapterResult());
        $mocks['finalBuilder']->method('build')->willReturn($this->makeFinalResponse());

        $result = $runner->run('seller', 1, 'Who manufactured the dishwasher?');

        $this->assertSame('Y', $result['trace']['normalizer_called'],
            'K10: unsupported question still calls normalizer via Step 1a.');
        $this->assertSame('listing.bedrooms', $result['trace']['normalized_field_key'],
            'K10: normalized_field_key from Step 1a must be carried through.');
        $this->assertSame('listing_facts', $result['trace']['final_question_type'],
            'K10: question_type must be updated to listing_facts by Step 1a.');
    }

    // =========================================================================
    // Case L — Description fallback (Guard B extension)
    //
    // When a listing.* field is null/absent AND the listing description is
    // non-empty AND both the feature flag AND the normalizer are enabled,
    // Guard B should attempt an OpenAI call using only the description.
    //
    // If OpenAI finds an answer → status='ready', outcome_category='description_fallback'.
    // If OpenAI returns the sentinel → status='insufficient_context',
    //   answer='This information was not provided in the listing description.',
    //   source.answer_source='description_fallback_miss'.
    // If flag off or normalizer null → status='insufficient_context',
    //   answer='This information was not provided in the listing.' (unchanged message).
    // =========================================================================

    /**
     * Build a Guard-B prompt package for listing.seller_credit_offered = null.
     */
    private function makeGuardBPromptPackage(mixed $fieldValue = null): array
    {
        return [
            'status'               => 'prompt_ready',
            'question_type'        => 'listing_facts',
            'required_disclosures' => [],
            'source_attribution'   => [],
            'refusal_template'     => null,
            'allowed_context'      => [
                'listing' => ['seller_credit_offered' => $fieldValue],
            ],
        ];
    }

    /**
     * Build an internal result whose context includes a listing description.
     */
    private function makeGuardBInternalResult(?string $description = '', mixed $fieldValue = null): array
    {
        return $this->makeInternalResult([
            'context' => [
                'status'       => 'assembled',
                'listing_type' => 'seller',
                'listing'      => [
                    'description'          => $description,
                    'seller_credit_offered' => $fieldValue,
                ],
            ],
            'prompt_package' => $this->makeGuardBPromptPackage($fieldValue),
        ]);
    }

    /**
     * Build a normalizer mock with isEnabled() returning the given value.
     */
    private function makeEnabledNormalizerMock(bool $enabled = true): AskAiIntentNormalizerService
    {
        $mock = $this->createMock(AskAiIntentNormalizerService::class);
        $mock->method('isEnabled')->willReturn($enabled);
        return $mock;
    }

    // ── L1 ── Flag off, normalizer null → insufficient_context (original message) ─

    public function test_case_L1_flag_off_normalizer_null_returns_insufficient_context_with_original_message(): void
    {
        $mocks  = $this->makeMocks();   // normalizer not in mocks → null
        $runner = $this->makeRunner($mocks, false);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn(
            $this->makeGuardBInternalResult('Seller offering $5,000 credit toward closing costs.')
        );
        $mocks['adapter']->expects($this->never())->method('generate');

        $result = $runner->run(
            'seller', 1,
            'Does the seller offer a credit toward closing?',
            ['normalized_field_key' => 'listing.seller_credit_offered']
        );

        $this->assertSame(false, $result['success'], 'L1: flag off — must be unsuccessful');
        $this->assertSame('insufficient_context', $result['status'], 'L1: flag off — status must be insufficient_context');
        $this->assertSame(
            'This information was not provided in the listing.',
            $result['final_response']['answer'],
            'L1: flag off — original miss message must be used (no "listing description" phrasing)'
        );
        $this->assertSame('openai', $result['final_response']['source']['answer_source'],
            'L1: flag off — answer_source must remain openai');
    }

    // ── L2 ── Flag on, normalizer enabled, adapter returns valid answer → ready ──

    public function test_case_L2_flag_on_normalizer_enabled_adapter_succeeds_returns_description_fallback(): void
    {
        $descAnswer      = 'The seller is offering a $5,000 credit toward closing costs.';
        $descRawResponse = json_encode(['answer_text' => $descAnswer]);

        $mocks                = $this->makeMocks();
        $mocks['normalizer']  = $this->makeEnabledNormalizerMock(true);
        $runner               = $this->makeRunner($mocks, true);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn(
            $this->makeGuardBInternalResult('Beautiful home. Seller offering $5,000 credit toward closing costs.')
        );

        // Adapter returns a valid JSON description answer on the first (and only) call.
        $mocks['adapter']->expects($this->once())
            ->method('generate')
            ->willReturn([
                'success'      => true,
                'status'       => 'generated',
                'raw_response' => $descRawResponse,
                'model'        => 'gpt-4o',
                'error'        => null,
            ]);

        $result = $runner->run(
            'seller', 1,
            'Does the seller offer a credit toward closing?',
            ['normalized_field_key' => 'listing.seller_credit_offered']
        );

        $this->assertTrue($result['success'], 'L2: flag on + valid answer → success must be true');
        $this->assertSame('ready', $result['status'], 'L2: flag on + valid answer → status must be ready');
        $this->assertSame('description_fallback', $result['outcome_category'],
            'L2: flag on + valid answer → outcome_category must be description_fallback');
        $this->assertSame($descAnswer, $result['final_response']['answer'],
            'L2: answer must match the text extracted from the description fallback');
        $this->assertSame('description_fallback', $result['final_response']['source']['answer_source'],
            'L2: answer_source must be description_fallback');
        $this->assertTrue($result['trace']['description_fallback_used'] ?? false,
            'L2: trace must record description_fallback_used=true');
    }

    // ── L3 ── Flag on, normalizer enabled, adapter returns sentinel → miss message ─

    public function test_case_L3_flag_on_adapter_returns_sentinel_uses_description_miss_message(): void
    {
        // When the adapter returns the sentinel (INFORMATION_NOT_IN_DESCRIPTION),
        // parseDescriptionFallbackAnswer returns null → Guard B falls through.
        // The miss message must say "listing description" and source='description_fallback_miss'.
        $sentinelResponse = json_encode(['answer_text' => 'INFORMATION_NOT_IN_DESCRIPTION']);

        $mocks               = $this->makeMocks();
        $mocks['normalizer'] = $this->makeEnabledNormalizerMock(true);
        $runner              = $this->makeRunner($mocks, true);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn(
            $this->makeGuardBInternalResult('This home has many features listed here.')
        );
        $mocks['adapter']->method('generate')->willReturn([
            'success'      => true,
            'status'       => 'generated',
            'raw_response' => $sentinelResponse,
            'model'        => 'gpt-4o',
            'error'        => null,
        ]);

        $result = $runner->run(
            'seller', 1,
            'Does the seller offer a credit toward closing?',
            ['normalized_field_key' => 'listing.seller_credit_offered']
        );

        $this->assertSame(false, $result['success'], 'L3: sentinel → must be unsuccessful');
        $this->assertSame('insufficient_context', $result['status'], 'L3: sentinel → status must be insufficient_context');
        $this->assertSame(
            'This information was not provided in the listing description.',
            $result['final_response']['answer'],
            'L3: sentinel → miss message must say "not provided in the listing description"'
        );
        $this->assertSame('description_fallback_miss', $result['final_response']['source']['answer_source'],
            'L3: sentinel → answer_source must be description_fallback_miss');
    }

    // ── L4 ── Flag on, normalizer null → Guard B description fallback DOES fire ─────

    public function test_case_L4_flag_on_normalizer_null_description_fallback_fires(): void
    {
        // After decoupling Guard B from the normalizer requirement, flag=true +
        // non-empty description is sufficient to trigger the description fallback,
        // even when normalizer is null.  The adapter is called and returns the
        // sentinel, yielding description_fallback_miss.
        $sentinelResponse = json_encode(['answer_text' => 'INFORMATION_NOT_IN_DESCRIPTION']);

        $mocks  = $this->makeMocks();   // normalizer NOT in mocks → null
        $runner = $this->makeRunner($mocks, true);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn(
            $this->makeGuardBInternalResult('Seller offering concessions toward closing costs.')
        );

        // Adapter IS now called — Guard B no longer requires the normalizer.
        $mocks['adapter']->expects($this->once())
            ->method('generate')
            ->willReturn([
                'success'      => true,
                'status'       => 'generated',
                'raw_response' => $sentinelResponse,
                'model'        => 'gpt-4o',
                'error'        => null,
            ]);

        $result = $runner->run(
            'seller', 1,
            'Does the seller offer closing cost credits?',
            ['normalized_field_key' => 'listing.seller_credit_offered']
        );

        $this->assertSame('insufficient_context', $result['status'],
            'L4: sentinel returned → status must be insufficient_context');
        $this->assertSame(
            'This information was not provided in the listing description.',
            $result['final_response']['answer'],
            'L4: sentinel returned → miss message must reference "listing description"'
        );
        $this->assertSame('description_fallback_miss', $result['final_response']['source']['answer_source'],
            'L4: sentinel returned → answer_source must be description_fallback_miss');
        // Guard B records description_fallback_used only on a hit; on a sentinel miss the
        // $descFallbackAttempted flag controls the miss message/source inline (no extra trace key).
        $this->assertArrayNotHasKey('description_fallback_used', $result['trace'],
            'L4: sentinel miss → description_fallback_used must be absent (no hit occurred)');
    }

    // ── L5 ── Flag off, null description → insufficient_context (no crash) ────────

    public function test_case_L5_flag_off_null_description_returns_insufficient_context(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks, false);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn(
            $this->makeGuardBInternalResult(null)   // null description
        );
        $mocks['adapter']->expects($this->never())->method('generate');

        $result = $runner->run(
            'seller', 1,
            'Does the seller offer closing cost credits?',
            ['normalized_field_key' => 'listing.seller_credit_offered']
        );

        $this->assertSame('insufficient_context', $result['status'],
            'L5: null description + flag off must return insufficient_context without crashing');
    }

    // ── L6 ── config/ask_ai.php declares the enable_description_fallback key ──────

    public function test_case_L6_config_flag_key_present_in_ask_ai_config(): void
    {
        $configPath = dirname(__DIR__, 4) . '/config/ask_ai.php';
        $source     = file_get_contents($configPath);
        $this->assertStringContainsString(
            'enable_description_fallback',
            $source,
            'L6: config/ask_ai.php must declare the enable_description_fallback key'
        );
    }

    // ── L7 ── closing-cost credit synonyms are in LISTING_KEY_KEYWORD_MAP ─────────

    public function test_case_L7_closing_cost_credit_routes_to_listing_seller_credit_offered(): void
    {
        $source = file_get_contents($this->serviceFilePath());
        $this->assertStringContainsString("'credit toward closing'", $source,
            'L7: "credit toward closing" must be in LISTING_KEY_KEYWORD_MAP');
        $this->assertStringContainsString("'closing credit'", $source,
            'L7: "closing credit" must be in LISTING_KEY_KEYWORD_MAP');
        $this->assertStringContainsString("'seller credit at closing'", $source,
            'L7: "seller credit at closing" must be in LISTING_KEY_KEYWORD_MAP');
    }

    // ── L8 ── closing-cost credit synonyms are in FAQ_KEY_KEYWORD_MAP ────────────

    public function test_case_L8_faq_key_map_includes_closing_credit_synonyms(): void
    {
        $source = file_get_contents($this->serviceFilePath());
        $this->assertStringContainsString('faq_answers.seller_concessions_offered', $source,
            'L8: faq_answers.seller_concessions_offered must be in FAQ_KEY_KEYWORD_MAP');
        $this->assertStringContainsString("'credits toward closing'", $source,
            'L8: "credits toward closing" must be in the FAQ_KEY_KEYWORD_MAP block');
    }

    // ── L9 ── rent/deposit synonym expansion — new phrases route correctly ────────

    public function test_case_L9_expanded_rent_synonyms_route_to_listing_rent_amount(): void
    {
        $source = file_get_contents($this->serviceFilePath());
        // Search for phrase content without quote-style assumption (phrase may be single
        // or double-quoted in the PHP source depending on whether it contains an apostrophe).
        foreach (['cost to rent', 'rental rate', 'how much per month', 'rent price', "what's the rent"] as $phrase) {
            $this->assertStringContainsString($phrase, $source,
                "L9: '$phrase' must be present in LISTING_KEY_KEYWORD_MAP under listing.rent_amount");
        }
    }

    public function test_case_L9b_expanded_deposit_synonyms_route_to_listing_security_deposit(): void
    {
        $source = file_get_contents($this->serviceFilePath());
        foreach (['how much deposit', 'move in deposit', 'upfront deposit', "what's the deposit"] as $phrase) {
            $this->assertStringContainsString($phrase, $source,
                "L9b: '$phrase' must be present in LISTING_KEY_KEYWORD_MAP under listing.security_deposit_amount");
        }
    }

    public function test_case_L10_expanded_first_last_month_rent_synonyms_present(): void
    {
        $source = file_get_contents($this->serviceFilePath());
        // Apostrophe-containing phrases use double-quote PHP strings; search for bare phrase.
        foreach (['first and last month rent', 'first and last month', "first month's rent", "last month's rent"] as $phrase) {
            $this->assertStringContainsString($phrase, $source,
                "L10: '$phrase' must be present in LISTING_KEY_KEYWORD_MAP for first/last month rent fields");
        }
    }

    // ── L11 ── No-key listing_facts + adapter fails + description hit → ready ─────

    /**
     * Build an internal runner result where:
     *  - listing context contains a non-empty description (for the no-key fallback)
     *  - NO specific field value is present (normalizedFieldKey will be null)
     *  - prompt_package is prompt_ready so the primary adapter is called
     */
    private function makeNoKeyInternalResult(string $description = ''): array
    {
        return $this->makeInternalResult([
            'context' => [
                'status'       => 'assembled',
                'listing_type' => 'seller',
                'listing'      => [
                    'description' => $description,
                ],
            ],
            'prompt_package' => [
                'status'               => 'prompt_ready',
                'question_type'        => 'listing_facts',
                'required_disclosures' => ['Information is derived from structured property data.'],
                'source_attribution'   => ['required_sources' => ['property_intelligence']],
                'refusal_template'     => null,
                'allowed_context'      => ['listing' => ['description' => $description]],
            ],
        ]);
    }

    public function test_case_L11_no_key_listing_facts_flag_on_adapter_hit_returns_ready(): void
    {
        // When listing_facts has no keyword-map match (normalizedFieldKey === null),
        // the primary adapter call fails, and the no-key description fallback fires.
        // On a valid answer from the description adapter → status='ready'.
        $descAnswer      = 'The seller is willing to consider closing cost concessions.';
        $descRawResponse = json_encode(['answer_text' => $descAnswer]);

        $mocks               = $this->makeMocks();
        $mocks['normalizer'] = $this->makeEnabledNormalizerMock(true);
        $runner              = $this->makeRunner($mocks, true);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn(
            $this->makeNoKeyInternalResult('Spacious home. Seller may consider closing cost assistance.')
        );

        // Call 1 (primary adapter) fails.
        // Call 2 (no-key description fallback) succeeds with a valid answer.
        $mocks['adapter']->expects($this->exactly(2))
            ->method('generate')
            ->willReturnOnConsecutiveCalls(
                ['success' => false, 'status' => 'error', 'raw_response' => null, 'model' => null, 'error' => 'timeout'],
                ['success' => true,  'status' => 'generated', 'raw_response' => $descRawResponse, 'model' => 'gpt-4o', 'error' => null]
            );

        // normalizedFieldKey intentionally NOT passed → remains null inside the runner.
        $result = $runner->run('seller', 1, 'Does the seller offer closing cost assistance?');

        $this->assertTrue($result['success'], 'L11: no-key hit → success must be true');
        $this->assertSame('ready', $result['status'], 'L11: no-key hit → status must be ready');
        $this->assertSame('description_fallback', $result['outcome_category'],
            'L11: no-key hit → outcome_category must be description_fallback');
        $this->assertSame($descAnswer, $result['final_response']['answer'],
            'L11: no-key hit → answer must match description fallback text');
        $this->assertSame('description_fallback', $result['final_response']['source']['answer_source'],
            'L11: no-key hit → answer_source must be description_fallback');
        $this->assertTrue($result['trace']['description_fallback_used'] ?? false,
            'L11: no-key hit → trace must record description_fallback_used=true');
        $this->assertSame('no_key', $result['trace']['description_fallback_key_path'] ?? null,
            'L11: no-key hit → trace must record description_fallback_key_path=no_key');
    }

    // ── L12 ── No-key listing_facts + adapter fails + description sentinel → miss ─

    public function test_case_L12_no_key_listing_facts_flag_on_adapter_sentinel_returns_miss(): void
    {
        // When both adapter calls fail / return sentinel, the miss message must
        // reference the listing description specifically.
        $sentinelResponse = json_encode(['answer_text' => 'INFORMATION_NOT_IN_DESCRIPTION']);

        $mocks               = $this->makeMocks();
        $mocks['normalizer'] = $this->makeEnabledNormalizerMock(true);
        $runner              = $this->makeRunner($mocks, true);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('listing_facts'));
        $mocks['internalRunner']->method('run')->willReturn(
            $this->makeNoKeyInternalResult('A charming property in the heart of the city.')
        );

        // Call 1 (primary adapter) fails.
        // Call 2 (no-key description fallback) returns sentinel.
        $mocks['adapter']->expects($this->exactly(2))
            ->method('generate')
            ->willReturnOnConsecutiveCalls(
                ['success' => false, 'status' => 'error', 'raw_response' => null, 'model' => null, 'error' => 'timeout'],
                ['success' => true,  'status' => 'generated', 'raw_response' => $sentinelResponse, 'model' => 'gpt-4o', 'error' => null]
            );

        $result = $runner->run('seller', 1, 'Does the seller provide any closing cost help?');

        $this->assertSame(false, $result['success'], 'L12: no-key sentinel → must be unsuccessful');
        $this->assertSame('insufficient_context', $result['status'],
            'L12: no-key sentinel → status must be insufficient_context');
        $this->assertSame(
            'This information was not provided in the listing description.',
            $result['final_response']['answer'],
            'L12: no-key sentinel → miss message must say "not provided in the listing description"'
        );
        $this->assertSame('description_fallback_miss', $result['final_response']['source']['answer_source'],
            'L12: no-key sentinel → answer_source must be description_fallback_miss');
        $this->assertSame('description_fallback_miss', $result['outcome_category'],
            'L12: no-key sentinel → outcome_category must be description_fallback_miss');
    }
}
