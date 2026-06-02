<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiInternalRunnerService;
use App\Services\AskAi\AskAiQuestionClassifierService;
use App\Services\AskAi\AskAiTestHarnessService;
use PHPUnit\Framework\TestCase;

/**
 * AskAiTestHarnessServiceTest
 *
 * Pure unit tests — no database, no Laravel TestCase, no DB traits.
 * Both dependencies are mocked via getMockBuilder + disableOriginalConstructor.
 *
 * Test coverage (cases A–F):
 *   A. Classifier is called and its output appears in 'classification'.
 *   B. Runner is called with the question_type from classification; its output
 *      appears in 'runner_result'.
 *   C. Return array contains exactly 'classification' and 'runner_result' keys.
 *   D. Prohibited path: classifier returns question_type = 'prohibited'; runner is
 *      still invoked and runner_result reflects the blocked outcome.
 *   E. Unsupported path: classifier returns question_type = 'unsupported'; runner is
 *      still invoked and result is returned intact.
 *   F. Static governance grep: service file contains no OpenAI, Http::, \Http,
 *      ->save(), ->create(), ->update(), or ->delete() calls (comment lines stripped).
 */
class AskAiTestHarnessServiceTest extends TestCase
{
    /**
     * The two keys the return array must contain — no more, no less.
     */
    private const REQUIRED_RESULT_KEYS = [
        'classification',
        'runner_result',
    ];

    /**
     * Absolute path to the harness service file — derived without base_path() so
     * this works in pure PHPUnit\Framework\TestCase (no Laravel container).
     */
    private function serviceFilePath(): string
    {
        return dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiTestHarnessService.php';
    }

    /**
     * Build mocks for both dependencies.
     *
     * @return array{classifier: AskAiQuestionClassifierService&\PHPUnit\Framework\MockObject\MockObject,
     *               runner: AskAiInternalRunnerService&\PHPUnit\Framework\MockObject\MockObject}
     */
    private function makeMocks(): array
    {
        $classifierMock = $this->getMockBuilder(AskAiQuestionClassifierService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['classify'])
            ->getMock();

        $runnerMock = $this->getMockBuilder(AskAiInternalRunnerService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['run'])
            ->getMock();

        return [
            'classifier' => $classifierMock,
            'runner'     => $runnerMock,
        ];
    }

    private function makeHarness(array $mocks): AskAiTestHarnessService
    {
        return new AskAiTestHarnessService(
            $mocks['classifier'],
            $mocks['runner']
        );
    }

    /**
     * Minimal classification result stub matching the approved 3-key contract.
     */
    private function makeClassification(string $questionType = 'property_standout'): array
    {
        return [
            'question_type' => $questionType,
            'confidence'    => 0.92,
            'reason'        => 'Question matches a property highlight keyword.',
        ];
    }

    /**
     * Minimal runner result stub for a successful pipeline run.
     */
    private function makeRunnerResult(array $overrides = []): array
    {
        return array_merge([
            'success'        => true,
            'status'         => 'prompt_ready',
            'context'        => ['listing_type' => 'seller'],
            'contract'       => ['status' => 'contract_ready'],
            'prompt_package' => ['status' => 'prompt_ready'],
            'error'          => null,
        ], $overrides);
    }

    // =========================================================================
    // Case A — Classifier is called and its output appears in 'classification'
    // =========================================================================

    public function test_case_A_classifier_is_called_with_the_question(): void
    {
        $mocks   = $this->makeMocks();
        $harness = $this->makeHarness($mocks);

        $question = 'What makes this property stand out?';

        $mocks['classifier']->expects($this->once())
            ->method('classify')
            ->with($question)
            ->willReturn($this->makeClassification());

        $mocks['runner']->method('run')->willReturn($this->makeRunnerResult());

        $harness->runTest('seller', 1, $question);
    }

    public function test_case_A_classification_output_appears_in_result(): void
    {
        $mocks   = $this->makeMocks();
        $harness = $this->makeHarness($mocks);

        $classification = $this->makeClassification('property_standout');

        $mocks['classifier']->method('classify')->willReturn($classification);
        $mocks['runner']->method('run')->willReturn($this->makeRunnerResult());

        $result = $harness->runTest('seller', 1, 'What makes this property stand out?');

        $this->assertSame($classification, $result['classification']);
    }

    public function test_case_A_classification_question_type_is_present(): void
    {
        $mocks   = $this->makeMocks();
        $harness = $this->makeHarness($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification('marketing_angles'));
        $mocks['runner']->method('run')->willReturn($this->makeRunnerResult());

        $result = $harness->runTest('seller', 1, 'How should this listing be marketed?');

        $this->assertSame('marketing_angles', $result['classification']['question_type']);
    }

    // =========================================================================
    // Case B — Runner is called with the question_type from classification;
    //           its output appears in 'runner_result'
    // =========================================================================

    public function test_case_B_runner_is_called_with_question_type_from_classification(): void
    {
        $mocks   = $this->makeMocks();
        $harness = $this->makeHarness($mocks);

        $question = 'What makes this property stand out?';

        $mocks['classifier']->method('classify')
            ->willReturn($this->makeClassification('property_standout'));

        $mocks['runner']->expects($this->once())
            ->method('run')
            ->with('seller', 1, 'property_standout', $question, [])
            ->willReturn($this->makeRunnerResult());

        $harness->runTest('seller', 1, $question);
    }

    public function test_case_B_runner_result_appears_in_result(): void
    {
        $mocks   = $this->makeMocks();
        $harness = $this->makeHarness($mocks);

        $runnerResult = $this->makeRunnerResult();

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification());
        $mocks['runner']->method('run')->willReturn($runnerResult);

        $result = $harness->runTest('seller', 1, 'q');

        $this->assertSame($runnerResult, $result['runner_result']);
    }

    public function test_case_B_options_are_forwarded_to_runner(): void
    {
        $mocks   = $this->makeMocks();
        $harness = $this->makeHarness($mocks);

        $options = [
            'demand_listing_type' => 'buyer',
            'demand_listing_id'   => 7,
        ];

        $mocks['classifier']->method('classify')
            ->willReturn($this->makeClassification('buyer_tenant_match'));

        $mocks['runner']->expects($this->once())
            ->method('run')
            ->with('seller', 1, 'buyer_tenant_match', 'q', $options)
            ->willReturn($this->makeRunnerResult());

        $harness->runTest('seller', 1, 'q', $options);
    }

    public function test_case_B_listing_type_and_id_are_forwarded_to_runner(): void
    {
        $mocks   = $this->makeMocks();
        $harness = $this->makeHarness($mocks);

        $mocks['classifier']->method('classify')
            ->willReturn($this->makeClassification('suited_audience'));

        $mocks['runner']->expects($this->once())
            ->method('run')
            ->with('landlord', 42, 'suited_audience', 'Who is this rental suited for?', [])
            ->willReturn($this->makeRunnerResult());

        $harness->runTest('landlord', 42, 'Who is this rental suited for?');
    }

    // =========================================================================
    // Case C — Return array contains exactly 'classification' and 'runner_result'
    // =========================================================================

    public function test_case_C_result_contains_required_keys(): void
    {
        $mocks   = $this->makeMocks();
        $harness = $this->makeHarness($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification());
        $mocks['runner']->method('run')->willReturn($this->makeRunnerResult());

        $result = $harness->runTest('seller', 1, 'q');

        foreach (self::REQUIRED_RESULT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Result missing required key '{$key}'");
        }
    }

    public function test_case_C_result_contains_exactly_two_keys(): void
    {
        $mocks   = $this->makeMocks();
        $harness = $this->makeHarness($mocks);

        $mocks['classifier']->method('classify')->willReturn($this->makeClassification());
        $mocks['runner']->method('run')->willReturn($this->makeRunnerResult());

        $result = $harness->runTest('seller', 1, 'q');

        $this->assertCount(2, $result, 'runTest() must return exactly two keys: classification and runner_result');
        $this->assertArrayHasKey('classification', $result);
        $this->assertArrayHasKey('runner_result', $result);
    }

    // =========================================================================
    // Case D — Prohibited path: classifier returns question_type = 'prohibited';
    //           runner is still invoked and runner_result reflects blocked outcome
    // =========================================================================

    public function test_case_D_prohibited_runner_is_still_invoked(): void
    {
        $mocks   = $this->makeMocks();
        $harness = $this->makeHarness($mocks);

        $mocks['classifier']->method('classify')
            ->willReturn($this->makeClassification('prohibited'));

        $mocks['runner']->expects($this->once())
            ->method('run')
            ->with('seller', 1, 'prohibited', 'Which neighborhood has the best schools?', [])
            ->willReturn($this->makeRunnerResult([
                'success' => false,
                'status'  => 'blocked',
            ]));

        $result = $harness->runTest('seller', 1, 'Which neighborhood has the best schools?');

        $this->assertSame('prohibited', $result['classification']['question_type']);
    }

    public function test_case_D_prohibited_runner_result_reflects_blocked_outcome(): void
    {
        $mocks   = $this->makeMocks();
        $harness = $this->makeHarness($mocks);

        $blockedResult = $this->makeRunnerResult([
            'success' => false,
            'status'  => 'blocked',
            'error'   => null,
        ]);

        $mocks['classifier']->method('classify')
            ->willReturn($this->makeClassification('prohibited'));

        $mocks['runner']->method('run')->willReturn($blockedResult);

        $result = $harness->runTest('seller', 1, 'Which neighborhood has the best schools?');

        $this->assertSame($blockedResult, $result['runner_result']);
        $this->assertFalse($result['runner_result']['success']);
        $this->assertSame('blocked', $result['runner_result']['status']);
    }

    public function test_case_D_prohibited_result_still_has_required_keys(): void
    {
        $mocks   = $this->makeMocks();
        $harness = $this->makeHarness($mocks);

        $mocks['classifier']->method('classify')
            ->willReturn($this->makeClassification('prohibited'));

        $mocks['runner']->method('run')->willReturn($this->makeRunnerResult([
            'success' => false,
            'status'  => 'blocked',
        ]));

        $result = $harness->runTest('seller', 1, 'q');

        foreach (self::REQUIRED_RESULT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Result missing required key '{$key}' on prohibited path");
        }
    }

    // =========================================================================
    // Case E — Unsupported path: classifier returns question_type = 'unsupported';
    //           runner is still invoked and result is returned intact
    // =========================================================================

    public function test_case_E_unsupported_runner_is_still_invoked(): void
    {
        $mocks   = $this->makeMocks();
        $harness = $this->makeHarness($mocks);

        $mocks['classifier']->method('classify')
            ->willReturn($this->makeClassification('unsupported'));

        $mocks['runner']->expects($this->once())
            ->method('run')
            ->with('seller', 1, 'unsupported', 'Tell me a joke about real estate.', [])
            ->willReturn($this->makeRunnerResult([
                'success' => false,
                'status'  => 'unsupported',
            ]));

        $result = $harness->runTest('seller', 1, 'Tell me a joke about real estate.');

        $this->assertSame('unsupported', $result['classification']['question_type']);
    }

    public function test_case_E_unsupported_result_is_returned_intact(): void
    {
        $mocks   = $this->makeMocks();
        $harness = $this->makeHarness($mocks);

        $unsupportedRunnerResult = $this->makeRunnerResult([
            'success' => false,
            'status'  => 'unsupported',
            'error'   => null,
        ]);

        $mocks['classifier']->method('classify')
            ->willReturn($this->makeClassification('unsupported'));

        $mocks['runner']->method('run')->willReturn($unsupportedRunnerResult);

        $result = $harness->runTest('seller', 1, 'q');

        $this->assertSame($unsupportedRunnerResult, $result['runner_result']);
        $this->assertFalse($result['runner_result']['success']);
        $this->assertSame('unsupported', $result['runner_result']['status']);
    }

    public function test_case_E_unsupported_result_has_required_keys(): void
    {
        $mocks   = $this->makeMocks();
        $harness = $this->makeHarness($mocks);

        $mocks['classifier']->method('classify')
            ->willReturn($this->makeClassification('unsupported'));

        $mocks['runner']->method('run')->willReturn($this->makeRunnerResult([
            'success' => false,
            'status'  => 'unsupported',
        ]));

        $result = $harness->runTest('seller', 1, 'q');

        foreach (self::REQUIRED_RESULT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Result missing required key '{$key}' on unsupported path");
        }
    }

    // =========================================================================
    // Case F — Static governance grep: service file contains no OpenAI, Http::,
    //           \Http, ->save(), ->create(), ->update(), or ->delete() calls
    //           (comment lines stripped before scanning)
    // =========================================================================

    private function loadCodeLines(): string
    {
        $path = $this->serviceFilePath();
        $this->assertFileExists($path, 'Harness service file does not exist at expected path');

        $content = file_get_contents($path);

        return implode("\n", array_filter(
            explode("\n", $content),
            static function (string $line): bool {
                $trimmed = ltrim($line);
                return !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//');
            }
        ));
    }

    public function test_case_F_service_file_exists(): void
    {
        $this->assertFileExists(
            $this->serviceFilePath(),
            'AskAiTestHarnessService.php does not exist at expected path'
        );
    }

    public function test_case_F_service_file_contains_no_openai_calls(): void
    {
        $codeLines = $this->loadCodeLines();

        $prohibited = [
            'OpenAI',
            'use OpenAI\\',
            'use OpenAi\\',
            'use GuzzleHttp\\',
            'OpenAI::',
            'ChatGPT::',
        ];

        foreach ($prohibited as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "Harness service file must not contain '{$term}'"
            );
        }
    }

    public function test_case_F_service_file_contains_no_http_calls(): void
    {
        $codeLines = $this->loadCodeLines();

        $prohibited = [
            'Http::',
            '\Http',
            'curl_exec',
            'file_get_contents(\'http',
            'file_get_contents("http',
        ];

        foreach ($prohibited as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "Harness service file must not contain HTTP call '{$term}'"
            );
        }
    }

    public function test_case_F_service_file_contains_no_write_calls(): void
    {
        $codeLines = $this->loadCodeLines();

        $prohibited = [
            '->save(',
            '->create(',
            '->update(',
            '->delete(',
            '->insert(',
            'DB::statement(',
            'DB::insert(',
            'DB::update(',
            'DB::delete(',
        ];

        foreach ($prohibited as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "Harness service file must not contain write call '{$term}'"
            );
        }
    }
}
