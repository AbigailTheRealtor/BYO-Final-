<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiFinalResponseBuilderService;
use App\Services\AskAi\AskAiFieldQuestionRegistryService;
use App\Services\AskAi\AskAiFollowUpQuestionService;
use App\Services\AskAi\AskAiInternalRunnerService;
use App\Services\AskAi\AskAiOpenAiAdapterService;
use App\Services\AskAi\AskAiQuestionClassifierService;
use App\Services\AskAi\AskAiRunnerV2Service;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * AskAiPipelineCoverageE2ETest
 *
 * True end-to-end pipeline coverage harness for Ask AI FAQ field routing.
 *
 * Each test case seeds a context block (simulating a real listing's faq_answers
 * data), executes the Ask AI pipeline through AskAiRunnerV2Service with the
 * real AskAiQuestionClassifierService, and asserts the full outcome:
 *
 *   - classification.question_type  → 'listing_facts'
 *   - classification.normalized_field_key → the expected canonical path
 *   - prompt_package.status         → 'prompt_ready' (when field is present)
 *   - final_response.status / answer (field absent → field-specific missing-data message)
 *   - source_attribution            (from finalBuilder mock when pipeline succeeds)
 *   - adapter and finalBuilder call counts
 *
 * Two scenarios per FAQ field:
 *   A. Field PRESENT in seeded context → pipeline proceeds, adapter called once,
 *      final response built with source_attribution set, success=true.
 *   B. Field ABSENT from seeded context → missing-data guard fires, adapter and
 *      finalBuilder never called, answer is the field-specific message.
 *
 * Covered FAQ fields (seller + landlord, cross-role):
 *   1. hvac_system_age          (seller)
 *   2. recent_renovations_list  (seller)
 *   3. flood_damage_history     (seller)
 *   4. average_utility_costs    (seller)
 *   5. laundry_situation        (landlord)
 *   6. lease_renewal_process    (landlord)
 *   7. security_features        (landlord)
 *   8. pest_termite_history     (seller)
 *
 * Pure PHPUnit — no Laravel container, no DB.
 */
class AskAiPipelineCoverageE2ETest extends TestCase
{
    // =========================================================================
    // Shared helpers
    // =========================================================================

    private function makeFollowUpMock(): AskAiFollowUpQuestionService
    {
        $mock = $this->createMock(AskAiFollowUpQuestionService::class);
        $mock->method('forResult')->willReturn([]);
        return $mock;
    }

    private function makeRunner(
        AskAiInternalRunnerService $internalRunner,
        AskAiOpenAiAdapterService $adapter,
        AskAiFinalResponseBuilderService $finalBuilder
    ): AskAiRunnerV2Service {
        return new AskAiRunnerV2Service(
            new AskAiQuestionClassifierService(),
            $internalRunner,
            $adapter,
            $finalBuilder,
            $this->makeFollowUpMock()
        );
    }

    /**
     * Build a mock internalRunner that returns context WITH the given FAQ answer.
     */
    private function makeInternalRunnerWithField(string $configKey, string $answerText): AskAiInternalRunnerService
    {
        $mock = $this->createMock(AskAiInternalRunnerService::class);

        $promptPackage = [
            'status'               => 'prompt_ready',
            'question_type'        => 'listing_facts',
            'allowed_context'      => ['faq_answers' => [$configKey => ['answer_text' => $answerText]]],
            'required_disclosures' => ['Information is sourced directly from the listing data.'],
            'source_attribution'   => ['required_sources' => ['listing']],
            'refusal_template'     => null,
        ];

        $mock->method('run')->willReturn([
            'success'        => true,
            'status'         => 'prompt_ready',
            'context'        => [
                'listing'     => ['listing_type' => 'seller'],
                'faq_answers' => [$configKey => ['answer_text' => $answerText]],
            ],
            'contract'       => ['status' => 'contract_ready', 'question_type' => 'listing_facts'],
            'prompt_package' => $promptPackage,
            'error'          => null,
        ]);

        return $mock;
    }

    /**
     * Build a mock internalRunner that returns context WITHOUT the given FAQ answer (empty faq_answers).
     */
    private function makeInternalRunnerWithoutField(): AskAiInternalRunnerService
    {
        $mock = $this->createMock(AskAiInternalRunnerService::class);

        $mock->method('run')->willReturn([
            'success'        => true,
            'status'         => 'prompt_ready',
            'context'        => [
                'listing'     => ['listing_type' => 'seller'],
                'faq_answers' => [],
            ],
            'contract'       => ['status' => 'contract_ready', 'question_type' => 'listing_facts'],
            'prompt_package' => [
                'status'               => 'prompt_ready',
                'question_type'        => 'listing_facts',
                'allowed_context'      => [],
                'required_disclosures' => [],
                'source_attribution'   => [],
                'refusal_template'     => null,
            ],
            'error'          => null,
        ]);

        return $mock;
    }

    private function makeAdapterResult(): array
    {
        return [
            'success'      => true,
            'status'       => 'generated',
            'raw_response' => 'This is the AI generated answer based on the listing data.',
            'model'        => 'gpt-4o',
            'error'        => null,
        ];
    }

    private function makeFinalResponse(array $overrides = []): array
    {
        return array_merge([
            'success'            => true,
            'status'             => 'ready',
            'answer'             => 'AI-generated answer based on listing data.',
            'disclosures'        => ['Information is sourced directly from the listing data.'],
            'source_attribution' => ['required_sources' => ['listing']],
            'refusal_message'    => null,
            'error'              => null,
        ], $overrides);
    }

    // =========================================================================
    // 1. hvac_system_age (seller)
    // =========================================================================

    public function test_hvac_age_question_classifies_as_listing_facts(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('how old is the hvac?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_hvac_age_field_present_pipeline_succeeds(): void
    {
        $internalRunner = $this->makeInternalRunnerWithField('hvac_system_age', 'Carrier unit, installed 2017, serviced annually.');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->once())->method('generate')->willReturn($this->makeAdapterResult());
        $finalBuilder->expects($this->once())->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, 'how old is the hvac?');

        $this->assertTrue($result['success'], 'Pipeline should succeed when hvac_system_age is in context.');
        $this->assertSame('ready', $result['status']);
        $this->assertSame('listing_facts', $result['classification']['question_type']);
        $this->assertSame(
            'faq_answers.hvac_system_age',
            $result['classification']['normalized_field_key'] ?? null,
            'Runner step 1b must detect faq_answers.hvac_system_age from the question.'
        );
        $this->assertSame('prompt_ready', $result['prompt_package']['status'] ?? null);
        $this->assertNotEmpty($result['final_response']['source_attribution'] ?? []);
    }

    public function test_hvac_age_field_absent_returns_field_specific_message(): void
    {
        $internalRunner = $this->makeInternalRunnerWithoutField();
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->never())->method('generate');
        $finalBuilder->expects($this->never())->method('build');

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, 'how old is the hvac?');

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_context', $result['status']);
        $this->assertSame(
            'HVAC system information has not been provided for this listing.',
            $result['final_response']['answer']
        );
    }

    // =========================================================================
    // 2. recent_renovations_list (seller)
    // =========================================================================

    public function test_renovations_question_classifies_as_listing_facts(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('what renovations have been made?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_recent_renovations_field_present_pipeline_succeeds(): void
    {
        $internalRunner = $this->makeInternalRunnerWithField('recent_renovations_list', 'Kitchen remodel 2022, new roof 2021, updated bathrooms 2020.');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->once())->method('generate')->willReturn($this->makeAdapterResult());
        $finalBuilder->expects($this->once())->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, 'what renovations have been made?');

        $this->assertTrue($result['success']);
        $this->assertSame('ready', $result['status']);
        $this->assertSame('faq_answers.recent_renovations_list', $result['classification']['normalized_field_key'] ?? null);
        $this->assertSame('prompt_ready', $result['prompt_package']['status'] ?? null);
    }

    public function test_recent_renovations_field_absent_returns_field_specific_message(): void
    {
        $internalRunner = $this->makeInternalRunnerWithoutField();
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->never())->method('generate');
        $finalBuilder->expects($this->never())->method('build');

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, 'what renovations have been made?');

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_context', $result['status']);
        $this->assertSame(
            'Recent renovation information has not been provided for this listing.',
            $result['final_response']['answer']
        );
    }

    // =========================================================================
    // 3. flood_damage_history (seller)
    // =========================================================================

    public function test_flood_question_classifies_as_listing_facts(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('has the property flooded?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_flood_damage_field_present_pipeline_succeeds(): void
    {
        $internalRunner = $this->makeInternalRunnerWithField('flood_damage_history', 'No known flood or water damage history. Property is in Zone X.');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->once())->method('generate')->willReturn($this->makeAdapterResult());
        $finalBuilder->expects($this->once())->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, 'has the property flooded?');

        $this->assertTrue($result['success']);
        $this->assertSame('faq_answers.flood_damage_history', $result['classification']['normalized_field_key'] ?? null);
        $this->assertSame('prompt_ready', $result['prompt_package']['status'] ?? null);
    }

    public function test_flood_damage_field_absent_returns_field_specific_message(): void
    {
        $internalRunner = $this->makeInternalRunnerWithoutField();
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->never())->method('generate');
        $finalBuilder->expects($this->never())->method('build');

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, 'has the property flooded?');

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_context', $result['status']);
        $this->assertSame(
            'Flood and water damage history information has not been provided for this listing.',
            $result['final_response']['answer']
        );
    }

    // =========================================================================
    // 4. average_utility_costs (seller)
    // =========================================================================

    public function test_utility_costs_question_classifies_as_listing_facts(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('what are the average monthly utility costs?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_utility_costs_field_present_pipeline_succeeds(): void
    {
        $internalRunner = $this->makeInternalRunnerWithField('average_utility_costs', 'Electric ~$140/mo, water/sewer ~$60/mo, gas ~$45/mo in winter.');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->once())->method('generate')->willReturn($this->makeAdapterResult());
        $finalBuilder->expects($this->once())->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, 'what are the average monthly utility costs?');

        $this->assertTrue($result['success']);
        $this->assertSame('faq_answers.average_utility_costs', $result['classification']['normalized_field_key'] ?? null);
    }

    public function test_utility_costs_field_absent_returns_field_specific_message(): void
    {
        $internalRunner = $this->makeInternalRunnerWithoutField();
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->never())->method('generate');
        $finalBuilder->expects($this->never())->method('build');

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, 'what are the average monthly utility costs?');

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_context', $result['status']);
        $this->assertSame(
            'Utility cost information has not been provided for this listing.',
            $result['final_response']['answer']
        );
    }

    // =========================================================================
    // 5. laundry_situation (landlord)
    // =========================================================================

    public function test_laundry_question_classifies_as_listing_facts(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('is there in-unit laundry?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_laundry_field_present_pipeline_succeeds(): void
    {
        $internalRunner = $this->makeInternalRunnerWithField('laundry_situation', 'In-unit washer/dryer hookups. Full-size washer and dryer included.');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->once())->method('generate')->willReturn($this->makeAdapterResult());
        $finalBuilder->expects($this->once())->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('landlord', 1, 'is there in-unit laundry?');

        $this->assertTrue($result['success']);
        $this->assertSame('faq_answers.laundry_situation', $result['classification']['normalized_field_key'] ?? null);
        $this->assertSame('listing_facts', $result['classification']['question_type']);
        $this->assertSame('prompt_ready', $result['prompt_package']['status'] ?? null);
        $this->assertNotEmpty($result['final_response']['source_attribution'] ?? []);
    }

    public function test_laundry_field_absent_returns_field_specific_message(): void
    {
        $internalRunner = $this->makeInternalRunnerWithoutField();
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->never())->method('generate');
        $finalBuilder->expects($this->never())->method('build');

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('landlord', 1, 'is there in-unit laundry?');

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_context', $result['status']);
        $this->assertSame(
            'Laundry information has not been provided for this listing.',
            $result['final_response']['answer']
        );
    }

    // =========================================================================
    // 6. lease_renewal_process (landlord)
    // =========================================================================

    public function test_lease_renewal_question_classifies_as_listing_facts(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('how does lease renewal work?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_lease_renewal_field_present_pipeline_succeeds(): void
    {
        $internalRunner = $this->makeInternalRunnerWithField('lease_renewal_process', '60-day notice required. Renewals offered at market rate. No auto-renewal.');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->once())->method('generate')->willReturn($this->makeAdapterResult());
        $finalBuilder->expects($this->once())->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('landlord', 1, 'how does lease renewal work?');

        $this->assertTrue($result['success']);
        $this->assertSame('faq_answers.lease_renewal_process', $result['classification']['normalized_field_key'] ?? null);
        $this->assertSame('prompt_ready', $result['prompt_package']['status'] ?? null);
    }

    public function test_lease_renewal_field_absent_returns_field_specific_message(): void
    {
        $internalRunner = $this->makeInternalRunnerWithoutField();
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->never())->method('generate');
        $finalBuilder->expects($this->never())->method('build');

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('landlord', 1, 'how does lease renewal work?');

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_context', $result['status']);
        $this->assertSame(
            'Lease renewal process information has not been provided for this listing.',
            $result['final_response']['answer']
        );
    }

    // =========================================================================
    // 7. security_features (landlord)
    // =========================================================================

    public function test_security_features_question_classifies_as_listing_facts(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('is there a security system?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_security_features_field_present_pipeline_succeeds(): void
    {
        $internalRunner = $this->makeInternalRunnerWithField('security_features', 'Keypad entry, security cameras in lobby, gated parking.');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->once())->method('generate')->willReturn($this->makeAdapterResult());
        $finalBuilder->expects($this->once())->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('landlord', 1, 'is there a security system?');

        $this->assertTrue($result['success']);
        $this->assertSame('faq_answers.security_features', $result['classification']['normalized_field_key'] ?? null);
    }

    public function test_security_features_field_absent_returns_field_specific_message(): void
    {
        $internalRunner = $this->makeInternalRunnerWithoutField();
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->never())->method('generate');
        $finalBuilder->expects($this->never())->method('build');

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('landlord', 1, 'is there a security system?');

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_context', $result['status']);
        $this->assertSame(
            'Security feature information has not been provided for this listing.',
            $result['final_response']['answer']
        );
    }

    // =========================================================================
    // 8. pest_termite_history (seller)
    // =========================================================================

    public function test_pest_question_classifies_as_listing_facts(): void
    {
        $result = (new AskAiQuestionClassifierService())->classify('have there been termites?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_pest_termite_field_present_pipeline_succeeds(): void
    {
        $internalRunner = $this->makeInternalRunnerWithField('pest_termite_history', 'Termite treatment in 2018, no recurrence. Annual pest inspection current.');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->once())->method('generate')->willReturn($this->makeAdapterResult());
        $finalBuilder->expects($this->once())->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, 'have there been termites?');

        $this->assertTrue($result['success']);
        $this->assertSame('faq_answers.pest_termite_history', $result['classification']['normalized_field_key'] ?? null);
        $this->assertSame('prompt_ready', $result['prompt_package']['status'] ?? null);
    }

    public function test_pest_termite_field_absent_returns_field_specific_message(): void
    {
        $internalRunner = $this->makeInternalRunnerWithoutField();
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->never())->method('generate');
        $finalBuilder->expects($this->never())->method('build');

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, 'have there been termites?');

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_context', $result['status']);
        $this->assertSame(
            'Pest and termite history information has not been provided for this listing.',
            $result['final_response']['answer']
        );
    }

    // =========================================================================
    // Cross-cutting: source_attribution and prompt_package.status assertions
    // =========================================================================

    public function test_field_present_result_has_all_required_top_level_keys(): void
    {
        $internalRunner = $this->makeInternalRunnerWithField('hvac_system_age', 'Heat pump, 2019.');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->method('generate')->willReturn($this->makeAdapterResult());
        $finalBuilder->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, 'how old is the hvac?');

        $requiredKeys = ['success', 'status', 'classification', 'context', 'contract', 'prompt_package', 'adapter_result', 'final_response', 'error'];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Result must contain top-level key '{$key}'");
        }
    }

    public function test_field_absent_result_has_empty_source_attribution(): void
    {
        $internalRunner = $this->makeInternalRunnerWithoutField();
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->never())->method('generate');
        $finalBuilder->expects($this->never())->method('build');

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, 'how old is the hvac?');

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_context', $result['status']);
        $sourceAttr = $result['final_response']['source_attribution'] ?? null;
        $this->assertEmpty(
            $sourceAttr,
            'Missing-data short-circuit must produce empty/null source_attribution in final_response '
                . '(no AI attribution since the answer was sourced from the missing-data guard, not the adapter).'
        );
    }

    public function test_field_present_source_attribution_comes_from_final_builder(): void
    {
        $internalRunner = $this->makeInternalRunnerWithField('laundry_situation', 'In-unit washer/dryer.');
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $expectedAttribution = ['required_sources' => ['listing'], 'disclosure_level' => 'standard'];
        $adapter->method('generate')->willReturn($this->makeAdapterResult());
        $finalBuilder->method('build')->willReturn($this->makeFinalResponse([
            'source_attribution' => $expectedAttribution,
        ]));

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('landlord', 1, 'is there in-unit laundry?');

        $this->assertTrue($result['success']);
        $this->assertSame($expectedAttribution, $result['final_response']['source_attribution']);
    }

    // =========================================================================
    // Full pinned-registry data-provider tests
    //
    // Iterates every pinned FAQ field across all four roles. For each entry:
    //   - Uses the first keyword from FAQ_KEY_KEYWORD_MAP as the test phrase
    //   - Asserts classifier routes to listing_facts
    //   - Asserts normalized_field_key resolves to the correct canonical path
    //   - Scenario A (field present)  → pipeline succeeds, prompt_ready, source_attribution set
    //   - Scenario B (field absent)   → adapter/builder never called, field-specific message
    // =========================================================================

    public static function allPinnedFaqFieldsProvider(): array
    {
        $rc  = new ReflectionClass(AskAiRunnerV2Service::class);
        $map = $rc->getConstant('FAQ_KEY_KEYWORD_MAP');

        $registry = AskAiFieldQuestionRegistryService::pinnedRegistry();

        $data = [];
        foreach ($registry as $canonicalPath => $entry) {
            $configKey  = $entry['config_key'];
            $keywords   = $map[$canonicalPath] ?? [];
            $testPhrase = $keywords[0] ?? $entry['sample_question'];
            $data[$configKey] = [$canonicalPath, $configKey, $testPhrase];
        }
        return $data;
    }

    /**
     * @dataProvider allPinnedFaqFieldsProvider
     */
    public function test_all_pinned_fields_present_pipeline_succeeds(
        string $canonicalPath,
        string $configKey,
        string $phrase
    ): void {
        $internalRunner = $this->makeInternalRunnerWithField($configKey, 'Sample answer for ' . $configKey);
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->once())->method('generate')->willReturn($this->makeAdapterResult());
        $finalBuilder->expects($this->once())->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, $phrase);

        $this->assertTrue(
            $result['success'],
            "Pipeline should succeed for [{$configKey}] with phrase [{$phrase}]."
        );
        $this->assertSame(
            'ready',
            $result['status'],
            "Status must be 'ready' for [{$configKey}]."
        );
        $this->assertSame(
            'listing_facts',
            $result['classification']['question_type'] ?? null,
            "Phrase [{$phrase}] must classify as listing_facts for [{$configKey}]."
        );
        $this->assertSame(
            $canonicalPath,
            $result['classification']['normalized_field_key'] ?? null,
            "Runner must detect [{$canonicalPath}] from phrase [{$phrase}]."
        );
        $this->assertSame(
            'prompt_ready',
            $result['prompt_package']['status'] ?? null,
            "Prompt package status must be prompt_ready for [{$configKey}]."
        );
        $this->assertNotEmpty(
            $result['final_response']['source_attribution'] ?? [],
            "Source attribution must be set for [{$configKey}]."
        );
    }

    /**
     * @dataProvider allPinnedFaqFieldsProvider
     */
    public function test_all_pinned_fields_absent_returns_field_specific_message(
        string $canonicalPath,
        string $configKey,
        string $phrase
    ): void {
        $internalRunner = $this->makeInternalRunnerWithoutField();
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $adapter->expects($this->never())->method('generate');
        $finalBuilder->expects($this->never())->method('build');

        $runner = $this->makeRunner($internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, $phrase);

        $this->assertFalse(
            $result['success'],
            "Pipeline should fail for [{$configKey}] when field is absent."
        );
        $this->assertSame(
            'insufficient_context',
            $result['status'],
            "Status must be insufficient_context for absent [{$configKey}]."
        );
        $answer = $result['final_response']['answer'] ?? '';
        $this->assertStringNotContainsString(
            'The requested information has not been provided',
            $answer,
            "Missing-data message for [{$configKey}] must be field-specific, not the generic fallback."
        );
        $this->assertStringEndsWith(
            ' has not been provided for this listing.',
            $answer,
            "Missing-data message for [{$configKey}] must end with ' has not been provided for this listing.'"
        );
    }
}
