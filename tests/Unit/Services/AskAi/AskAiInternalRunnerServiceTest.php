<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\AskAi\AskAiInternalRunnerService;
use App\Services\AskAi\AskAiPromptBuilderService;
use App\Services\AskAi\AskAiResponseContractService;
use PHPUnit\Framework\TestCase;

/**
 * AskAiInternalRunnerServiceTest
 *
 * Pure unit tests — no database, no Laravel TestCase, no DB traits.
 * All three Phase 1–3 dependencies are mocked via getMockBuilder.
 *
 * Test coverage (cases A–E):
 *   A. Successful chain: buildForListing returns assembled context, buildContract returns
 *      contract_ready, buildPromptPackage returns prompt_ready; runner returns success=true
 *      with all three payloads populated.
 *   B. not_found context: buildForListing returns not_found status, contract gets
 *      insufficient_context, prompt gets insufficient_context; runner returns a safe
 *      (non-exception) package with success=false.
 *   C. insufficient_context from contract: context is assembled but contract returns
 *      insufficient_context; runner still returns all three keys populated and success=false.
 *   D. prohibited question type: contract returns refusal_required, prompt returns blocked;
 *      runner returns success=false, status='blocked'.
 *   E. Governance static grep: no OpenAI, HTTP, or write calls in the runner service file
 *      (strip comment lines before checking).
 */
class AskAiInternalRunnerServiceTest extends TestCase
{
    private const REQUIRED_RESULT_KEYS = [
        'success',
        'status',
        'context',
        'contract',
        'prompt_package',
        'error',
    ];

    /**
     * Absolute path to the runner service file — derived without base_path() so this
     * works in pure PHPUnit\Framework\TestCase (no Laravel container).
     */
    private function serviceFilePath(): string
    {
        return dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiInternalRunnerService.php';
    }

    /**
     * Build mocks for all three dependencies.
     *
     * @return array{context: AskAiContextBuilderService&\PHPUnit\Framework\MockObject\MockObject,
     *               contract: AskAiResponseContractService&\PHPUnit\Framework\MockObject\MockObject,
     *               prompt: AskAiPromptBuilderService&\PHPUnit\Framework\MockObject\MockObject}
     */
    private function makeMocks(): array
    {
        $contextMock = $this->getMockBuilder(AskAiContextBuilderService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['buildForListing'])
            ->getMock();

        $contractMock = $this->getMockBuilder(AskAiResponseContractService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['buildContract'])
            ->getMock();

        $promptMock = $this->getMockBuilder(AskAiPromptBuilderService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['buildPromptPackage'])
            ->getMock();

        return [
            'context'  => $contextMock,
            'contract' => $contractMock,
            'prompt'   => $promptMock,
        ];
    }

    private function makeRunner(array $mocks): AskAiInternalRunnerService
    {
        return new AskAiInternalRunnerService(
            $mocks['context'],
            $mocks['contract'],
            $mocks['prompt']
        );
    }

    /**
     * Minimal assembled context stub representing a seller listing with full data.
     */
    private function makeAssembledContext(array $overrides = []): array
    {
        return array_merge([
            'success'               => true,
            'listing_type'          => 'seller',
            'listing_id'            => 1,
            'context_version'       => 'ASK_AI_CONTEXT_V1',
            'status'                => 'assembled',
            'listing'               => ['listing_id' => 1, 'listing_type' => 'seller'],
            'property_intelligence' => ['property_highlights' => ['Pool', 'Garage']],
            'location_intelligence' => ['location_narrative' => 'A walkable neighborhood.'],
            'buyer_avatar'          => null,
            'tenant_avatar'         => null,
            'compatibility'         => null,
            'offer_analysis'        => null,
            'missing_sources'       => [],
            'warnings'              => [],
            'source_versions'       => [
                'ask_ai_context'                => 'ASK_AI_CONTEXT_V1',
                'property_intelligence_version' => 'PROPERTY_INTELLIGENCE_V1',
                'location_dna_lifestyle_version'=> 'LIFESTYLE_V1',
                'buyer_avatar_version'          => null,
                'tenant_avatar_version'         => null,
                'compatibility_version'         => null,
            ],
            'assembled_at'          => '2026-06-02T10:00:00.000000Z',
            'error'                 => null,
        ], $overrides);
    }

    /**
     * Minimal contract_ready contract stub.
     */
    private function makeContractReady(array $overrides = []): array
    {
        return array_merge([
            'success'                  => true,
            'status'                   => 'contract_ready',
            'question_type'            => 'property_standout',
            'allowed_context'          => ['property_intelligence.property_highlights'],
            'required_sources'         => ['property_intelligence'],
            'missing_required_sources' => [],
            'response_rules'           => ['Base response only on provided highlights.'],
            'required_disclosures'     => ['Information is derived from structured property data.'],
            'refusal_template'         => null,
            'contract_version'         => 'ASK_AI_RESPONSE_CONTRACT_V1',
        ], $overrides);
    }

    /**
     * Minimal prompt_ready package stub.
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
            'allowed_context'          => ['property_intelligence' => ['property_highlights' => ['Pool']]],
            'source_attribution'       => ['required_sources' => ['property_intelligence'], 'versions' => []],
            'required_disclosures'     => ['Information is derived from structured property data.'],
            'refusal_template'         => null,
            'missing_required_sources' => [],
            'context_versions'         => ['ask_ai_context' => 'ASK_AI_CONTEXT_V1'],
            'response_format'          => ['type' => 'structured_text'],
            'error'                    => null,
        ], $overrides);
    }

    // =========================================================================
    // Case A — Successful chain returns success=true, all three payloads populated
    // =========================================================================

    public function test_case_A_successful_chain_returns_success_true(): void
    {
        $mocks   = $this->makeMocks();
        $runner  = $this->makeRunner($mocks);

        $context  = $this->makeAssembledContext();
        $contract = $this->makeContractReady();
        $package  = $this->makePromptReadyPackage();

        $mocks['context']->method('buildForListing')->willReturn($context);
        $mocks['contract']->method('buildContract')->willReturn($contract);
        $mocks['prompt']->method('buildPromptPackage')->willReturn($package);

        $result = $runner->run('seller', 1, 'property_standout', 'What makes this property stand out?');

        $this->assertTrue($result['success']);
        $this->assertSame('prompt_ready', $result['status']);
    }

    public function test_case_A_successful_chain_returns_all_six_required_keys(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['context']->method('buildForListing')->willReturn($this->makeAssembledContext());
        $mocks['contract']->method('buildContract')->willReturn($this->makeContractReady());
        $mocks['prompt']->method('buildPromptPackage')->willReturn($this->makePromptReadyPackage());

        $result = $runner->run('seller', 1, 'property_standout', 'What makes this property stand out?');

        foreach (self::REQUIRED_RESULT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Result missing required key '{$key}'");
        }
    }

    public function test_case_A_successful_chain_populates_all_three_payloads(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $context  = $this->makeAssembledContext();
        $contract = $this->makeContractReady();
        $package  = $this->makePromptReadyPackage();

        $mocks['context']->method('buildForListing')->willReturn($context);
        $mocks['contract']->method('buildContract')->willReturn($contract);
        $mocks['prompt']->method('buildPromptPackage')->willReturn($package);

        $result = $runner->run('seller', 1, 'property_standout', 'What makes this property stand out?');

        $this->assertSame($context, $result['context']);
        $this->assertSame($contract, $result['contract']);
        $this->assertSame($package, $result['prompt_package']);
    }

    public function test_case_A_successful_chain_error_is_null(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['context']->method('buildForListing')->willReturn($this->makeAssembledContext());
        $mocks['contract']->method('buildContract')->willReturn($this->makeContractReady());
        $mocks['prompt']->method('buildPromptPackage')->willReturn($this->makePromptReadyPackage());

        $result = $runner->run('seller', 1, 'property_standout', 'q');

        $this->assertNull($result['error']);
    }

    public function test_case_A_options_forwarded_to_build_for_listing(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $options = [
            'demand_listing_type' => 'buyer',
            'demand_listing_id'   => 5,
            'supply_listing_type' => 'seller',
            'supply_listing_id'   => 1,
        ];

        $mocks['context']->expects($this->once())
            ->method('buildForListing')
            ->with('seller', 1, $options)
            ->willReturn($this->makeAssembledContext());

        $mocks['contract']->method('buildContract')->willReturn($this->makeContractReady());
        $mocks['prompt']->method('buildPromptPackage')->willReturn($this->makePromptReadyPackage());

        $result = $runner->run('seller', 1, 'property_standout', 'q', $options);

        $this->assertTrue($result['success']);
    }

    // =========================================================================
    // Case B — not_found context: contract gets insufficient_context, prompt gets
    //           insufficient_context; runner returns success=false (non-exception path)
    // =========================================================================

    public function test_case_B_not_found_context_returns_success_false(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $notFoundContext = [
            'success'               => false,
            'listing_type'          => 'seller',
            'listing_id'            => 999,
            'context_version'       => 'ASK_AI_CONTEXT_V1',
            'status'                => 'not_found',
            'listing'               => null,
            'property_intelligence' => null,
            'location_intelligence' => null,
            'buyer_avatar'          => null,
            'tenant_avatar'         => null,
            'compatibility'         => null,
            'offer_analysis'        => null,
            'missing_sources'       => [],
            'warnings'              => [],
            'source_versions'       => [],
            'assembled_at'          => '2026-06-02T10:00:00.000000Z',
            'error'                 => null,
        ];

        $insufficientContract = $this->makeContractReady([
            'success'                  => false,
            'status'                   => 'insufficient_context',
            'missing_required_sources' => ['property_intelligence'],
        ]);

        $insufficientPackage = $this->makePromptReadyPackage([
            'success' => false,
            'status'  => 'insufficient_context',
            'error'   => null,
        ]);

        $mocks['context']->method('buildForListing')->willReturn($notFoundContext);
        $mocks['contract']->method('buildContract')->willReturn($insufficientContract);
        $mocks['prompt']->method('buildPromptPackage')->willReturn($insufficientPackage);

        $result = $runner->run('seller', 999, 'property_standout', 'q');

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_context', $result['status']);
        $this->assertNull($result['error'], 'error must be null on non-exception path');
    }

    public function test_case_B_not_found_context_all_three_payloads_are_populated(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $notFoundContext = $this->makeAssembledContext(['status' => 'not_found', 'success' => false]);
        $insufficientContract = $this->makeContractReady([
            'success' => false,
            'status'  => 'insufficient_context',
            'missing_required_sources' => ['property_intelligence'],
        ]);
        $insufficientPackage = $this->makePromptReadyPackage([
            'success' => false,
            'status'  => 'insufficient_context',
        ]);

        $mocks['context']->method('buildForListing')->willReturn($notFoundContext);
        $mocks['contract']->method('buildContract')->willReturn($insufficientContract);
        $mocks['prompt']->method('buildPromptPackage')->willReturn($insufficientPackage);

        $result = $runner->run('seller', 999, 'property_standout', 'q');

        $this->assertNotNull($result['context']);
        $this->assertNotNull($result['contract']);
        $this->assertNotNull($result['prompt_package']);
    }

    // =========================================================================
    // Case C — insufficient_context from contract: context is assembled but contract
    //           returns insufficient_context; all three keys populated, success=false
    // =========================================================================

    public function test_case_C_insufficient_context_from_contract_returns_success_false(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $assembledContext = $this->makeAssembledContext([
            'property_intelligence' => null,
            'missing_sources'       => ['property_intelligence'],
            'status'                => 'partial',
        ]);

        $insufficientContract = $this->makeContractReady([
            'success'                  => false,
            'status'                   => 'insufficient_context',
            'missing_required_sources' => ['property_intelligence'],
        ]);

        $insufficientPackage = $this->makePromptReadyPackage([
            'success' => false,
            'status'  => 'insufficient_context',
        ]);

        $mocks['context']->method('buildForListing')->willReturn($assembledContext);
        $mocks['contract']->method('buildContract')->willReturn($insufficientContract);
        $mocks['prompt']->method('buildPromptPackage')->willReturn($insufficientPackage);

        $result = $runner->run('seller', 1, 'property_standout', 'q');

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_context', $result['status']);
        $this->assertNull($result['error']);
    }

    public function test_case_C_insufficient_context_all_three_payloads_populated(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $assembledContext     = $this->makeAssembledContext(['property_intelligence' => null]);
        $insufficientContract = $this->makeContractReady([
            'success' => false,
            'status'  => 'insufficient_context',
            'missing_required_sources' => ['property_intelligence'],
        ]);
        $insufficientPackage  = $this->makePromptReadyPackage([
            'success' => false,
            'status'  => 'insufficient_context',
        ]);

        $mocks['context']->method('buildForListing')->willReturn($assembledContext);
        $mocks['contract']->method('buildContract')->willReturn($insufficientContract);
        $mocks['prompt']->method('buildPromptPackage')->willReturn($insufficientPackage);

        $result = $runner->run('seller', 1, 'property_standout', 'q');

        $this->assertNotNull($result['context']);
        $this->assertNotNull($result['contract']);
        $this->assertNotNull($result['prompt_package']);
    }

    // =========================================================================
    // Case D — prohibited question: contract returns refusal_required, prompt returns
    //           blocked; runner returns success=false, status='blocked'
    // =========================================================================

    public function test_case_D_prohibited_question_returns_success_false_and_blocked_status(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $assembledContext = $this->makeAssembledContext();

        $refusalContract = $this->makeContractReady([
            'success'                  => false,
            'status'                   => 'refusal_required',
            'question_type'            => 'prohibited',
            'allowed_context'          => [],
            'required_sources'         => [],
            'missing_required_sources' => [],
            'response_rules'           => [],
            'required_disclosures'     => [],
            'refusal_template'         => 'This question type is not permitted on this platform. No response can be generated.',
        ]);

        $blockedPackage = $this->makePromptReadyPackage([
            'success'          => false,
            'status'           => 'blocked',
            'question_type'    => 'prohibited',
            'allowed_context'  => [],
            'refusal_template' => 'This question type is not permitted on this platform. No response can be generated.',
            'error'            => null,
        ]);

        $mocks['context']->method('buildForListing')->willReturn($assembledContext);
        $mocks['contract']->method('buildContract')->willReturn($refusalContract);
        $mocks['prompt']->method('buildPromptPackage')->willReturn($blockedPackage);

        $result = $runner->run('seller', 1, 'prohibited', 'Which neighborhood has the best schools?');

        $this->assertFalse($result['success']);
        $this->assertSame('blocked', $result['status']);
        $this->assertNull($result['error']);
    }

    public function test_case_D_prohibited_question_all_three_payloads_populated(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $refusalContract = $this->makeContractReady([
            'success'         => false,
            'status'          => 'refusal_required',
            'question_type'   => 'prohibited',
            'allowed_context' => [],
            'required_sources'=> [],
            'missing_required_sources' => [],
            'response_rules'  => [],
            'required_disclosures' => [],
            'refusal_template'=> 'Not permitted.',
        ]);
        $blockedPackage = $this->makePromptReadyPackage([
            'success' => false,
            'status'  => 'blocked',
        ]);

        $mocks['context']->method('buildForListing')->willReturn($this->makeAssembledContext());
        $mocks['contract']->method('buildContract')->willReturn($refusalContract);
        $mocks['prompt']->method('buildPromptPackage')->willReturn($blockedPackage);

        $result = $runner->run('seller', 1, 'prohibited', 'q');

        $this->assertNotNull($result['context']);
        $this->assertNotNull($result['contract']);
        $this->assertNotNull($result['prompt_package']);
    }

    // =========================================================================
    // Case D — exception path: Throwable produces failed shape with error populated
    // =========================================================================

    public function test_case_D_throwable_produces_failed_shape_with_error_populated(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['context']->method('buildForListing')
            ->willThrowException(new \RuntimeException('Unexpected DB failure'));

        $result = $runner->run('seller', 1, 'property_standout', 'q');

        $this->assertFalse($result['success']);
        $this->assertSame('failed', $result['status']);
        $this->assertSame('Unexpected DB failure', $result['error']);
        $this->assertNull($result['context']);
        $this->assertNull($result['contract']);
        $this->assertNull($result['prompt_package']);
    }

    public function test_case_D_throwable_result_contains_all_six_required_keys(): void
    {
        $mocks  = $this->makeMocks();
        $runner = $this->makeRunner($mocks);

        $mocks['context']->method('buildForListing')
            ->willThrowException(new \RuntimeException('fail'));

        $result = $runner->run('seller', 1, 'property_standout', 'q');

        foreach (self::REQUIRED_RESULT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Failed result missing required key '{$key}'");
        }
    }

    // =========================================================================
    // Case E — Governance static grep: no OpenAI, HTTP, or write calls in runner file
    // =========================================================================

    public function test_case_E_service_file_contains_no_openai_calls(): void
    {
        $path = $this->serviceFilePath();
        $this->assertFileExists($path, 'Runner service file does not exist at expected path');

        $content = file_get_contents($path);

        $codeLines = implode("\n", array_filter(
            explode("\n", $content),
            static function (string $line): bool {
                $trimmed = ltrim($line);
                return !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//');
            }
        ));

        $prohibitedImports = [
            'use OpenAI\\',
            'use OpenAi\\',
            'use GuzzleHttp\\',
            'OpenAI::',
            'ChatGPT::',
        ];

        foreach ($prohibitedImports as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "Runner service file must not import or call '{$term}'"
            );
        }
    }

    public function test_case_E_service_file_contains_no_http_calls(): void
    {
        $path = $this->serviceFilePath();
        $this->assertFileExists($path, 'Runner service file does not exist at expected path');

        $content = file_get_contents($path);

        $codeLines = implode("\n", array_filter(
            explode("\n", $content),
            static function (string $line): bool {
                $trimmed = ltrim($line);
                return !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//');
            }
        ));

        $prohibitedHttpCalls = [
            'Http::post',
            'Http::get',
            'Http::put',
            'curl_exec',
            'file_get_contents(\'http',
            'file_get_contents("http',
        ];

        foreach ($prohibitedHttpCalls as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "Runner service file must not contain HTTP call '{$term}'"
            );
        }
    }

    public function test_case_E_service_file_contains_no_write_calls(): void
    {
        $path = $this->serviceFilePath();
        $this->assertFileExists($path, 'Runner service file does not exist at expected path');

        $content = file_get_contents($path);

        $codeLines = implode("\n", array_filter(
            explode("\n", $content),
            static function (string $line): bool {
                $trimmed = ltrim($line);
                return !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//');
            }
        ));

        $prohibitedWriteCalls = [
            '->save(',
            '->update(',
            '->create(',
            '->delete(',
            '->insert(',
            'DB::statement(',
            'DB::insert(',
            'DB::update(',
            'DB::delete(',
        ];

        foreach ($prohibitedWriteCalls as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "Runner service file must not contain write call '{$term}'"
            );
        }
    }
}
