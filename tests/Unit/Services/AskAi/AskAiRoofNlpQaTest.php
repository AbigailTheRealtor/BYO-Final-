<?php

namespace Tests\Unit\Services\AskAi;

use App\Services\AskAi\AskAiFinalResponseBuilderService;
use App\Services\AskAi\AskAiFollowUpQuestionService;
use App\Services\AskAi\AskAiIntentNormalizerService;
use App\Services\AskAi\AskAiInternalRunnerService;
use App\Services\AskAi\AskAiOpenAiAdapterService;
use App\Services\AskAi\AskAiQuestionClassifierService;
use App\Services\AskAi\AskAiRunnerV2Service;
use PHPUnit\Framework\TestCase;

/**
 * AskAiRoofNlpQaTest
 *
 * Pure unit tests — no database, no Laravel TestCase, no HTTP calls.
 *
 * Verifies the full Ask AI pipeline for roof/condition questions:
 *   (b) Flag OFF: expanded classifier keyword set handles all six roof phrase variants.
 *   (c) Flag ON: normalizer is invoked for previously-unsupported factual phrasing.
 *   (d) Prohibited questions always bypass the normalizer (Layer 1 wins).
 *   (e) Six roof phrase variants resolve to faq_answers.roof_age_and_condition
 *       (three via classifier, three via normalizer).
 *   (f) When the seller answered the roof FAQ, faq_answers.roof_age_and_condition
 *       is present in the context's faq_answers map.
 *   (g) When the roof FAQ key is absent, Ask AI returns the field-specific
 *       "Roof information has not been provided for this listing." message.
 *
 * Test cases (labels match the task spec):
 *   B. Classifier flag-OFF handles all six roof phrases → listing_facts.
 *   C. Runner flag-ON normalizer is called for unsupported roof phrasing.
 *   D. Prohibited question: normalizer methods are never invoked.
 *   E1. Three pre-existing classifier phrases resolve to listing_facts (flag OFF).
 *   E2. Three new classifier phrases resolve to listing_facts (flag OFF).
 *   E3. Normalizer (flag ON) maps ambiguous roof phrases to faq_answers.roof_age_and_condition.
 *   F. Roof FAQ answer present in context → faq_answers.roof_age_and_condition populated.
 *   G. Roof FAQ key absent → field-specific missing-data response returned.
 */
class AskAiRoofNlpQaTest extends TestCase
{
    private const ROOF_FAQ_KEY = 'faq_answers.roof_age_and_condition';

    private const REQUIRED_RESULT_KEYS = [
        'success', 'status', 'classification', 'context',
        'contract', 'prompt_package', 'adapter_result', 'final_response', 'error',
    ];

    private function makeClassifier(): AskAiQuestionClassifierService
    {
        return new AskAiQuestionClassifierService();
    }

    private function makeFollowUpMock(): AskAiFollowUpQuestionService
    {
        $mock = $this->createMock(AskAiFollowUpQuestionService::class);
        $mock->method('forResult')->willReturn([]);
        return $mock;
    }

    private function makeNormalizerMock(bool $isEnabled, ?string $normalizedKey = null): AskAiIntentNormalizerService
    {
        $mock = $this->createMock(AskAiIntentNormalizerService::class);
        $mock->method('isEnabled')->willReturn($isEnabled);
        $mock->method('buildKnownFieldKeys')->willReturn([
            'listing.bedrooms',
            'listing.hoa_fee',
            self::ROOF_FAQ_KEY,
            'faq_answers.hvac_system_age',
        ]);
        $mock->method('normalize')->willReturn($normalizedKey);
        return $mock;
    }

    private function makeRunner(
        AskAiQuestionClassifierService $classifier,
        AskAiInternalRunnerService $internalRunner,
        AskAiOpenAiAdapterService $adapter,
        AskAiFinalResponseBuilderService $finalBuilder,
        ?AskAiIntentNormalizerService $normalizer = null
    ): AskAiRunnerV2Service {
        return new AskAiRunnerV2Service(
            $classifier,
            $internalRunner,
            $adapter,
            $finalBuilder,
            $this->makeFollowUpMock(),
            $normalizer
        );
    }

    private function makeClassification(string $questionType): array
    {
        return [
            'question_type' => $questionType,
            'confidence'    => 0.90,
            'reason'        => "Matched keyword rule for '{$questionType}'.",
        ];
    }

    private function makePromptReadyPackage(array $allowedContext = ['faq_answers' => ['roof_age_and_condition' => ['answer_text' => 'Architectural shingles, 2019, no known issues.']]]): array
    {
        return [
            'status'               => 'prompt_ready',
            'question_type'        => 'listing_facts',
            'allowed_context'      => $allowedContext,
            'required_disclosures' => ['Information is sourced directly from the listing data.'],
            'source_attribution'   => ['required_sources' => ['listing']],
            'refusal_template'     => null,
        ];
    }

    private function makeInternalResult(array $promptPackage, string $status = 'prompt_ready'): array
    {
        return [
            'success'        => ($status === 'prompt_ready'),
            'status'         => $status,
            'context'        => ['listing' => ['listing_type' => 'seller'], 'faq_answers' => ['roof_age_and_condition' => ['answer_text' => 'Architectural shingles, 2019.']]],
            'contract'       => ['status' => 'contract_ready', 'question_type' => 'listing_facts'],
            'prompt_package' => $promptPackage,
            'error'          => null,
        ];
    }

    private function makeAdapterResult(): array
    {
        return [
            'success'      => true,
            'status'       => 'generated',
            'raw_response' => 'The roof is in good condition. Architectural shingles were replaced in 2019.',
            'model'        => 'gpt-4o',
            'error'        => null,
        ];
    }

    private function makeFinalResponse(array $overrides = []): array
    {
        return array_merge([
            'success'            => true,
            'status'             => 'ready',
            'answer'             => 'The roof was replaced in 2019 with architectural shingles.',
            'disclosures'        => ['Information is sourced directly from the listing data.'],
            'source_attribution' => ['required_sources' => ['listing']],
            'refusal_message'    => null,
            'error'              => null,
        ], $overrides);
    }

    // =========================================================================
    // Case B — Classifier flag-OFF: all six roof phrases → listing_facts
    // =========================================================================

    /**
     * @dataProvider roofPhrasesProvider
     */
    public function test_case_B_all_six_roof_phrases_classify_as_listing_facts_flag_off(string $phrase): void
    {
        $result = $this->makeClassifier()->classify($phrase);
        $this->assertSame(
            'listing_facts',
            $result['question_type'],
            "Phrase \"{$phrase}\" should classify as listing_facts with the flag OFF (deterministic classifier)."
        );
    }

    public static function roofPhrasesProvider(): array
    {
        return [
            'pre-existing: roof age'           => ['roof age'],
            'pre-existing: when was the roof'  => ['When was the roof replaced?'],
            'pre-existing: how old is the roof'=> ['How old is the roof?'],
            'new: age of roof'                 => ['Age of roof'],
            "new: what's the roof situation"   => ["What's the roof situation?"],
            'new: tell me about the roof'      => ['Tell me about the roof'],
        ];
    }

    // =========================================================================
    // Case E1 — Three pre-existing classifier phrases route deterministically
    // =========================================================================

    public function test_case_E1_roof_age_classifies_as_listing_facts(): void
    {
        $result = $this->makeClassifier()->classify('roof age');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_E1_when_was_the_roof_classifies_as_listing_facts(): void
    {
        $result = $this->makeClassifier()->classify('When was the roof replaced?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    public function test_case_E1_how_old_is_the_roof_classifies_as_listing_facts(): void
    {
        $result = $this->makeClassifier()->classify('How old is the roof?');
        $this->assertSame('listing_facts', $result['question_type']);
    }

    // =========================================================================
    // Case E2 — Three newly-added classifier phrases route deterministically
    // =========================================================================

    public function test_case_E2_age_of_roof_classifies_as_listing_facts(): void
    {
        $result = $this->makeClassifier()->classify('Age of roof');
        $this->assertSame('listing_facts', $result['question_type'],
            '"age of roof" must be a classifier keyword routing to listing_facts.');
    }

    public function test_case_E2_whats_the_roof_situation_classifies_as_listing_facts(): void
    {
        $result = $this->makeClassifier()->classify("What's the roof situation?");
        $this->assertSame('listing_facts', $result['question_type'],
            '"what\'s the roof situation" must be a classifier keyword routing to listing_facts.');
    }

    public function test_case_E2_what_is_the_roof_situation_classifies_as_listing_facts(): void
    {
        $result = $this->makeClassifier()->classify('What is the roof situation?');
        $this->assertSame('listing_facts', $result['question_type'],
            '"what is the roof situation" must be a classifier keyword routing to listing_facts.');
    }

    public function test_case_E2_tell_me_about_the_roof_classifies_as_listing_facts(): void
    {
        $result = $this->makeClassifier()->classify('Tell me about the roof');
        $this->assertSame('listing_facts', $result['question_type'],
            '"tell me about the roof" must be a classifier keyword routing to listing_facts.');
    }

    // =========================================================================
    // Case E3 — Normalizer (flag ON) maps roof phrases to faq_answers.roof_age_and_condition
    // =========================================================================

    public function test_case_E3_normalizer_maps_age_of_roof_to_roof_faq_key(): void
    {
        $normalizerMock = $this->createMock(AskAiIntentNormalizerService::class);
        $normalizerMock->method('isEnabled')->willReturn(true);
        $normalizerMock->method('buildKnownFieldKeys')->willReturn([
            self::ROOF_FAQ_KEY, 'listing.bedrooms', 'faq_answers.hvac_system_age',
        ]);
        $normalizerMock->method('normalize')->willReturn(self::ROOF_FAQ_KEY);

        $key = $normalizerMock->normalize('Age of roof', $normalizerMock->buildKnownFieldKeys());
        $this->assertSame(self::ROOF_FAQ_KEY, $key);
    }

    public function test_case_E3_normalizer_maps_roof_situation_to_roof_faq_key(): void
    {
        $normalizerMock = $this->createMock(AskAiIntentNormalizerService::class);
        $normalizerMock->method('isEnabled')->willReturn(true);
        $normalizerMock->method('buildKnownFieldKeys')->willReturn([
            self::ROOF_FAQ_KEY, 'listing.bedrooms',
        ]);
        $normalizerMock->method('normalize')->willReturn(self::ROOF_FAQ_KEY);

        $key = $normalizerMock->normalize("What's the roof situation?", $normalizerMock->buildKnownFieldKeys());
        $this->assertSame(self::ROOF_FAQ_KEY, $key);
    }

    public function test_case_E3_normalizer_maps_tell_me_about_the_roof_to_roof_faq_key(): void
    {
        $normalizerMock = $this->createMock(AskAiIntentNormalizerService::class);
        $normalizerMock->method('isEnabled')->willReturn(true);
        $normalizerMock->method('buildKnownFieldKeys')->willReturn([
            self::ROOF_FAQ_KEY, 'listing.bedrooms',
        ]);
        $normalizerMock->method('normalize')->willReturn(self::ROOF_FAQ_KEY);

        $key = $normalizerMock->normalize('Tell me about the roof', $normalizerMock->buildKnownFieldKeys());
        $this->assertSame(self::ROOF_FAQ_KEY, $key);
    }

    // =========================================================================
    // Case C — Flag ON: normalizer is called when classifier returns unsupported
    // =========================================================================

    public function test_case_C_flag_on_normalizer_normalize_is_called_for_unsupported_question(): void
    {
        $classifier     = $this->createMock(AskAiQuestionClassifierService::class);
        $internalRunner = $this->createMock(AskAiInternalRunnerService::class);
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $classifier->method('classify')->willReturn($this->makeClassification('unsupported'));

        $normalizerMock = $this->createMock(AskAiIntentNormalizerService::class);
        $normalizerMock->method('isEnabled')->willReturn(true);
        $normalizerMock->method('buildKnownFieldKeys')->willReturn([self::ROOF_FAQ_KEY]);
        $normalizerMock->expects($this->once())
            ->method('normalize')
            ->willReturn(self::ROOF_FAQ_KEY);

        $promptPackage  = $this->makePromptReadyPackage();
        $internalRunner->method('run')->willReturn($this->makeInternalResult($promptPackage));
        $adapter->method('generate')->willReturn($this->makeAdapterResult());
        $finalBuilder->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($classifier, $internalRunner, $adapter, $finalBuilder, $normalizerMock);
        $result = $runner->run('seller', 1, 'Tell me about the roof condition here');

        $this->assertTrue($result['success']);
        $this->assertSame('ready', $result['status']);
    }

    public function test_case_C_flag_on_normalization_sets_normalized_field_key_in_classification(): void
    {
        $classifier     = $this->createMock(AskAiQuestionClassifierService::class);
        $internalRunner = $this->createMock(AskAiInternalRunnerService::class);
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $classifier->method('classify')->willReturn($this->makeClassification('unsupported'));

        $normalizerMock = $this->makeNormalizerMock(true, self::ROOF_FAQ_KEY);

        $promptPackage  = $this->makePromptReadyPackage();
        $internalRunner->method('run')->willReturn($this->makeInternalResult($promptPackage));
        $adapter->method('generate')->willReturn($this->makeAdapterResult());
        $finalBuilder->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($classifier, $internalRunner, $adapter, $finalBuilder, $normalizerMock);
        $result = $runner->run('seller', 1, 'Tell me about the roof');

        $this->assertArrayHasKey('normalized_field_key', $result['classification']);
        $this->assertSame(self::ROOF_FAQ_KEY, $result['classification']['normalized_field_key']);
        $this->assertSame('listing_facts', $result['classification']['question_type']);
    }

    // =========================================================================
    // Case D — Prohibited question: normalizer isEnabled() is never called
    // =========================================================================

    public function test_case_D_prohibited_question_normalizer_is_never_called(): void
    {
        $classifier     = $this->createMock(AskAiQuestionClassifierService::class);
        $internalRunner = $this->createMock(AskAiInternalRunnerService::class);
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $classifier->method('classify')->willReturn($this->makeClassification('prohibited'));

        $normalizerMock = $this->createMock(AskAiIntentNormalizerService::class);
        $normalizerMock->expects($this->never())->method('isEnabled');
        $normalizerMock->expects($this->never())->method('normalize');

        $blockedPackage = [
            'status'               => 'blocked',
            'question_type'        => 'prohibited',
            'allowed_context'      => [],
            'required_disclosures' => [],
            'source_attribution'   => [],
            'refusal_template'     => 'Not permitted.',
        ];
        $internalRunner->method('run')->willReturn([
            'success'        => false,
            'status'         => 'blocked',
            'context'        => null,
            'contract'       => null,
            'prompt_package' => $blockedPackage,
            'error'          => null,
        ]);
        $finalBuilder->method('build')->willReturn($this->makeFinalResponse([
            'success'         => false,
            'status'          => 'blocked',
            'answer'          => null,
            'refusal_message' => 'Not permitted.',
            'error'           => null,
        ]));

        $runner = $this->makeRunner($classifier, $internalRunner, $adapter, $finalBuilder, $normalizerMock);
        $result = $runner->run('seller', 1, 'What race of people live in this neighborhood?');

        $this->assertFalse($result['success']);
        $this->assertSame('blocked', $result['status']);
    }

    // =========================================================================
    // Case F — Roof FAQ answer present: faq_answers.roof_age_and_condition populated
    // =========================================================================

    public function test_case_F_roof_faq_answer_present_in_prompt_package_allowed_context(): void
    {
        $classifier     = $this->createMock(AskAiQuestionClassifierService::class);
        $internalRunner = $this->createMock(AskAiInternalRunnerService::class);
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $classifier->method('classify')->willReturn($this->makeClassification('listing_facts'));

        $roofAnswer = [
            'answer_text'           => 'Architectural shingles replaced in 2019, no known issues.',
            'question_label'        => 'How old is the roof, and what condition is it in?',
            'question_group'        => 'Property Condition',
            'intelligence_category' => 'property_condition',
        ];

        $promptPackage = $this->makePromptReadyPackage([
            'faq_answers' => [
                'roof_age_and_condition' => $roofAnswer,
            ],
        ]);

        $internalResult = $this->makeInternalResult($promptPackage);
        $internalResult['context']['faq_answers']['roof_age_and_condition'] = $roofAnswer;

        $internalRunner->method('run')->willReturn($internalResult);
        $adapter->method('generate')->willReturn($this->makeAdapterResult());
        $finalBuilder->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($classifier, $internalRunner, $adapter, $finalBuilder);
        $result = $runner->run('seller', 1, 'How old is the roof?');

        $this->assertTrue($result['success']);

        $this->assertArrayHasKey('faq_answers', $result['context']);
        $this->assertArrayHasKey('roof_age_and_condition', $result['context']['faq_answers']);
        $this->assertSame(
            'Architectural shingles replaced in 2019, no known issues.',
            $result['context']['faq_answers']['roof_age_and_condition']['answer_text']
        );

        $allowedContext = $result['prompt_package']['allowed_context'] ?? [];
        $this->assertArrayHasKey('faq_answers', $allowedContext);
        $this->assertArrayHasKey('roof_age_and_condition', $allowedContext['faq_answers']);
        $this->assertSame(
            'Architectural shingles replaced in 2019, no known issues.',
            $allowedContext['faq_answers']['roof_age_and_condition']['answer_text']
        );
    }

    // =========================================================================
    // Case G — Roof FAQ key absent: field-specific missing-data response
    // =========================================================================

    public function test_case_G_missing_roof_faq_answer_returns_field_specific_message(): void
    {
        $classifier     = $this->createMock(AskAiQuestionClassifierService::class);
        $internalRunner = $this->createMock(AskAiInternalRunnerService::class);
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $classifier->method('classify')->willReturn($this->makeClassification('unsupported'));

        $normalizerMock = $this->makeNormalizerMock(true, self::ROOF_FAQ_KEY);

        $promptPackageNoRoof = [
            'status'               => 'prompt_ready',
            'question_type'        => 'listing_facts',
            'allowed_context'      => [],
            'required_disclosures' => ['Information is sourced directly from the listing data.'],
            'source_attribution'   => ['required_sources' => ['listing']],
            'refusal_template'     => null,
        ];

        $internalResult = [
            'success'        => true,
            'status'         => 'prompt_ready',
            'context'        => ['listing' => ['listing_type' => 'seller'], 'faq_answers' => []],
            'contract'       => ['status' => 'contract_ready', 'question_type' => 'listing_facts'],
            'prompt_package' => $promptPackageNoRoof,
            'error'          => null,
        ];

        $internalRunner->method('run')->willReturn($internalResult);
        $adapter->expects($this->never())->method('generate');
        $finalBuilder->expects($this->never())->method('build');

        $runner = $this->makeRunner($classifier, $internalRunner, $adapter, $finalBuilder, $normalizerMock);
        $result = $runner->run(
            'seller',
            1,
            'Tell me about the roof',
            ['normalized_field_key' => self::ROOF_FAQ_KEY]
        );

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_context', $result['status']);
        $this->assertNull($result['adapter_result']);

        $finalResponse = $result['final_response'];
        $this->assertNotNull($finalResponse);
        $this->assertSame('insufficient_context', $finalResponse['status']);
        $this->assertStringContainsString(
            'Roof information',
            $finalResponse['answer'],
            'The field-specific message must mention "Roof information".'
        );
        $this->assertStringContainsString(
            'has not been provided for this listing',
            $finalResponse['answer'],
            'The field-specific message must contain "has not been provided for this listing".'
        );
    }

    public function test_case_G_missing_roof_faq_returns_exact_message_format(): void
    {
        $classifier     = $this->createMock(AskAiQuestionClassifierService::class);
        $internalRunner = $this->createMock(AskAiInternalRunnerService::class);
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $classifier->method('classify')->willReturn($this->makeClassification('unsupported'));
        $normalizerMock = $this->makeNormalizerMock(true, self::ROOF_FAQ_KEY);

        $promptPackageNoRoof = [
            'status'               => 'prompt_ready',
            'question_type'        => 'listing_facts',
            'allowed_context'      => [],
            'required_disclosures' => [],
            'source_attribution'   => [],
            'refusal_template'     => null,
        ];

        $internalRunner->method('run')->willReturn([
            'success'        => true,
            'status'         => 'prompt_ready',
            'context'        => ['listing' => [], 'faq_answers' => []],
            'contract'       => ['status' => 'contract_ready', 'question_type' => 'listing_facts'],
            'prompt_package' => $promptPackageNoRoof,
            'error'          => null,
        ]);

        $runner = $this->makeRunner($classifier, $internalRunner, $adapter, $finalBuilder, $normalizerMock);
        $result = $runner->run(
            'seller',
            1,
            'Tell me about the roof',
            ['normalized_field_key' => self::ROOF_FAQ_KEY]
        );

        $expectedMessage = 'Roof information has not been provided for this listing.';
        $this->assertSame($expectedMessage, $result['final_response']['answer']);
    }

    public function test_case_G_missing_roof_faq_nine_key_output_contract_preserved(): void
    {
        $classifier     = $this->createMock(AskAiQuestionClassifierService::class);
        $internalRunner = $this->createMock(AskAiInternalRunnerService::class);
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $classifier->method('classify')->willReturn($this->makeClassification('unsupported'));
        $normalizerMock = $this->makeNormalizerMock(true, self::ROOF_FAQ_KEY);

        $internalRunner->method('run')->willReturn([
            'success'        => true,
            'status'         => 'prompt_ready',
            'context'        => ['listing' => [], 'faq_answers' => []],
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

        $runner = $this->makeRunner($classifier, $internalRunner, $adapter, $finalBuilder, $normalizerMock);
        $result = $runner->run(
            'seller',
            1,
            'Tell me about the roof',
            ['normalized_field_key' => self::ROOF_FAQ_KEY]
        );

        foreach (self::REQUIRED_RESULT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Result missing required key '{$key}'.");
        }
    }

    public function test_case_G_missing_non_roof_faq_key_returns_generic_label(): void
    {
        $classifier     = $this->createMock(AskAiQuestionClassifierService::class);
        $internalRunner = $this->createMock(AskAiInternalRunnerService::class);
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $unknownFaqKey = 'faq_answers.some_unlabeled_faq_key';
        $classifier->method('classify')->willReturn($this->makeClassification('unsupported'));
        $normalizerMock = $this->makeNormalizerMock(true, $unknownFaqKey);

        $internalRunner->method('run')->willReturn([
            'success'        => true,
            'status'         => 'prompt_ready',
            'context'        => ['listing' => [], 'faq_answers' => []],
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

        $runner = $this->makeRunner($classifier, $internalRunner, $adapter, $finalBuilder, $normalizerMock);
        $result = $runner->run(
            'seller',
            1,
            'Some unlabeled question',
            ['normalized_field_key' => $unknownFaqKey]
        );

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_context', $result['status']);
        $this->assertStringContainsString(
            'The requested information',
            $result['final_response']['answer'],
            'Unknown FAQ keys should fall back to the generic "The requested information" label.'
        );
    }

    public function test_case_G_listing_null_field_guard_fires_before_adapter_when_bedrooms_is_null(): void
    {
        $classifier     = $this->createMock(AskAiQuestionClassifierService::class);
        $internalRunner = $this->createMock(AskAiInternalRunnerService::class);
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $classifier->method('classify')->willReturn($this->makeClassification('listing_facts'));

        $promptPackage = [
            'status'               => 'prompt_ready',
            'question_type'        => 'listing_facts',
            'allowed_context'      => ['listing' => ['bedrooms' => null]],
            'required_disclosures' => [],
            'source_attribution'   => [],
            'refusal_template'     => null,
        ];

        $internalRunner->method('run')->willReturn([
            'success'        => true,
            'status'         => 'prompt_ready',
            'context'        => ['listing' => ['bedrooms' => null], 'faq_answers' => []],
            'contract'       => ['status' => 'contract_ready', 'question_type' => 'listing_facts'],
            'prompt_package' => $promptPackage,
            'error'          => null,
        ]);

        $adapter->expects($this->never())->method('generate');
        $finalBuilder->expects($this->never())->method('build');

        $runner = $this->makeRunner($classifier, $internalRunner, $adapter, $finalBuilder);
        $result = $runner->run(
            'seller',
            1,
            'How many bedrooms?',
            ['normalized_field_key' => 'listing.bedrooms']
        );

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_context', $result['status']);
        $this->assertStringContainsString(
            'Bedroom information',
            $result['final_response']['answer'],
            'Guard B must produce a field-specific "Bedroom information has not been provided" message.'
        );
        $this->assertStringContainsString(
            'has not been provided for this listing',
            $result['final_response']['answer']
        );
    }

    // =========================================================================
    // Integration — real classifier + mocked pipeline for six roof phrases
    //
    // Uses the actual AskAiQuestionClassifierService (no mock) together with
    // mocked InternalRunner / Adapter / FinalBuilder to verify the full call
    // path for each phrase under both flag-OFF and flag-ON conditions.
    //
    // This proves end-to-end that:
    //   (1) All six phrases route through listing_facts deterministically
    //       regardless of flag setting (normalizer never invoked).
    //   (2) Flag ON + a novel phrase the classifier cannot match causes the
    //       normalizer to be invoked and map to faq_answers.roof_age_and_condition.
    // =========================================================================

    /**
     * @dataProvider roofPhrasesProvider
     */
    public function test_integration_flag_off_six_roof_phrases_route_via_classifier_not_normalizer(
        string $phrase
    ): void {
        $realClassifier = $this->makeClassifier();
        $internalRunner = $this->createMock(AskAiInternalRunnerService::class);
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $normalizerMock = $this->createMock(AskAiIntentNormalizerService::class);
        $normalizerMock->method('isEnabled')->willReturn(false);
        // Normalizer normalize() must NOT be called for any of the six roof phrases because
        // the real classifier catches them as listing_facts (normalizer only fires for unsupported).
        $normalizerMock->expects($this->never())->method('normalize');

        $internalRunner->method('run')->willReturn($this->makeInternalResult(
            $this->makePromptReadyPackage()
        ));
        $adapter->method('generate')->willReturn($this->makeAdapterResult());
        $finalBuilder->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($realClassifier, $internalRunner, $adapter, $finalBuilder, $normalizerMock);
        $result = $runner->run('seller', 1, $phrase);

        $this->assertTrue($result['success'], "Flag OFF: phrase \"{$phrase}\" should succeed via listing_facts.");
        $this->assertSame('listing_facts', $result['classification']['question_type']);
        // The deterministic FAQ key detector (step 1b) fires regardless of the flag,
        // setting normalized_field_key so the prompt is narrowed to the roof FAQ answer.
        $this->assertArrayHasKey('normalized_field_key', $result['classification'],
            "Step 1b deterministic detector must set normalized_field_key for \"{$phrase}\".");
        $this->assertSame(
            self::ROOF_FAQ_KEY,
            $result['classification']['normalized_field_key'],
            "Flag OFF: \"{$phrase}\" must resolve to faq_answers.roof_age_and_condition via step 1b detector."
        );
    }

    /**
     * @dataProvider roofPhrasesProvider
     */
    public function test_integration_flag_on_six_roof_phrases_route_via_classifier_not_normalizer(
        string $phrase
    ): void {
        $realClassifier = $this->makeClassifier();
        $internalRunner = $this->createMock(AskAiInternalRunnerService::class);
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $normalizerMock = $this->createMock(AskAiIntentNormalizerService::class);
        $normalizerMock->method('isEnabled')->willReturn(true);
        $normalizerMock->method('buildKnownFieldKeys')->willReturn([self::ROOF_FAQ_KEY]);
        // Normalizer normalize() must NOT be called because the real classifier routes all six
        // roof phrases to listing_facts — normalizer only fires for unsupported questions.
        // The deterministic step 1b detector handles the FAQ key resolution instead.
        $normalizerMock->expects($this->never())->method('normalize');

        $internalRunner->method('run')->willReturn($this->makeInternalResult(
            $this->makePromptReadyPackage()
        ));
        $adapter->method('generate')->willReturn($this->makeAdapterResult());
        $finalBuilder->method('build')->willReturn($this->makeFinalResponse());

        $runner = $this->makeRunner($realClassifier, $internalRunner, $adapter, $finalBuilder, $normalizerMock);
        $result = $runner->run('seller', 1, $phrase);

        $this->assertTrue(
            $result['success'],
            "Flag ON: phrase \"{$phrase}\" should succeed via listing_facts (classifier + step 1b detector, normalizer not needed)."
        );
        $this->assertSame('listing_facts', $result['classification']['question_type']);
        // With flag ON, the step 1b deterministic detector still runs and resolves the FAQ key.
        // This verifies all six roof phrases resolve to faq_answers.roof_age_and_condition.
        $this->assertSame(
            self::ROOF_FAQ_KEY,
            $result['classification']['normalized_field_key'] ?? null,
            "Flag ON: \"{$phrase}\" must resolve to faq_answers.roof_age_and_condition via step 1b detector."
        );
    }

    public function test_integration_flag_on_novel_roof_phrasing_invokes_normalizer(): void
    {
        $realClassifier = $this->makeClassifier();
        $internalRunner = $this->createMock(AskAiInternalRunnerService::class);
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $normalizerMock = $this->createMock(AskAiIntentNormalizerService::class);
        $normalizerMock->method('isEnabled')->willReturn(true);
        $normalizerMock->method('buildKnownFieldKeys')->willReturn([self::ROOF_FAQ_KEY]);
        // This phrase is deliberately NOT in the classifier keyword list, so the normalizer fires.
        $normalizerMock->expects($this->once())
            ->method('normalize')
            ->willReturn(self::ROOF_FAQ_KEY);

        $internalRunner->method('run')->willReturn($this->makeInternalResult(
            $this->makePromptReadyPackage()
        ));
        $adapter->method('generate')->willReturn($this->makeAdapterResult());
        $finalBuilder->method('build')->willReturn($this->makeFinalResponse());

        $novelPhrase = 'Does this home have a solid covering overhead?';
        $this->assertSame('unsupported', $realClassifier->classify($novelPhrase)['question_type'],
            "Pre-condition: the novel phrase must not be in the classifier keyword list.");

        $runner = $this->makeRunner($realClassifier, $internalRunner, $adapter, $finalBuilder, $normalizerMock);
        $result = $runner->run('seller', 1, $novelPhrase);

        $this->assertTrue($result['success']);
        $this->assertSame('listing_facts', $result['classification']['question_type']);
        $this->assertSame(self::ROOF_FAQ_KEY, $result['classification']['normalized_field_key'] ?? null);
    }

    public function test_integration_flag_on_novel_roof_phrasing_missing_faq_returns_field_message(): void
    {
        $realClassifier = $this->makeClassifier();
        $internalRunner = $this->createMock(AskAiInternalRunnerService::class);
        $adapter        = $this->createMock(AskAiOpenAiAdapterService::class);
        $finalBuilder   = $this->createMock(AskAiFinalResponseBuilderService::class);

        $normalizerMock = $this->createMock(AskAiIntentNormalizerService::class);
        $normalizerMock->method('isEnabled')->willReturn(true);
        $normalizerMock->method('buildKnownFieldKeys')->willReturn([self::ROOF_FAQ_KEY]);
        $normalizerMock->method('normalize')->willReturn(self::ROOF_FAQ_KEY);

        $internalRunner->method('run')->willReturn([
            'success'        => true,
            'status'         => 'prompt_ready',
            'context'        => ['listing' => [], 'faq_answers' => []],
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
        $adapter->expects($this->never())->method('generate');
        $finalBuilder->expects($this->never())->method('build');

        $novelPhrase = 'Does this home have a solid covering overhead?';
        $runner = $this->makeRunner($realClassifier, $internalRunner, $adapter, $finalBuilder, $normalizerMock);

        $result = $runner->run('seller', 1, $novelPhrase);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_context', $result['status']);
        $this->assertSame(
            'Roof information has not been provided for this listing.',
            $result['final_response']['answer']
        );
    }

    // =========================================================================
    // Classifier keyword file verification (static source scan)
    // =========================================================================

    private function classifierFilePath(): string
    {
        return dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiQuestionClassifierService.php';
    }

    public function test_classifier_file_contains_age_of_roof_keyword(): void
    {
        $content = file_get_contents($this->classifierFilePath());
        $this->assertStringContainsString(
            "'age of roof'",
            $content,
            "AskAiQuestionClassifierService must contain 'age of roof' in the listing_facts keyword list."
        );
    }

    public function test_classifier_file_contains_whats_the_roof_situation_keyword(): void
    {
        $content = file_get_contents($this->classifierFilePath());
        $this->assertStringContainsString(
            "roof situation",
            $content,
            "AskAiQuestionClassifierService must contain a roof situation keyword variant."
        );
    }

    public function test_classifier_file_contains_tell_me_about_the_roof_keyword(): void
    {
        $content = file_get_contents($this->classifierFilePath());
        $this->assertStringContainsString(
            "'tell me about the roof'",
            $content,
            "AskAiQuestionClassifierService must contain 'tell me about the roof' keyword."
        );
    }

    // =========================================================================
    // Runner file verification (static source scan)
    // =========================================================================

    private function runnerFilePath(): string
    {
        return dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiRunnerV2Service.php';
    }

    public function test_runner_file_contains_detect_faq_field_key_method(): void
    {
        $content = file_get_contents($this->runnerFilePath());
        $this->assertStringContainsString(
            'detectFaqFieldKey',
            $content,
            'AskAiRunnerV2Service must contain the detectFaqFieldKey() method for step 1b.'
        );
    }

    public function test_runner_file_contains_faq_key_keyword_map_constant(): void
    {
        $content = file_get_contents($this->runnerFilePath());
        $this->assertStringContainsString(
            'FAQ_KEY_KEYWORD_MAP',
            $content,
            'AskAiRunnerV2Service must contain the FAQ_KEY_KEYWORD_MAP constant.'
        );
    }

    public function test_runner_file_contains_step_1b_listing_facts_detector(): void
    {
        $content = file_get_contents($this->runnerFilePath());
        $this->assertStringContainsString(
            "questionType === 'listing_facts' && !isset(\$options['normalized_field_key'])",
            $content,
            'Runner must include the step 1b listing_facts deterministic detector guard.'
        );
    }

    public function test_runner_file_roof_keyword_in_faq_key_map(): void
    {
        $content = file_get_contents($this->runnerFilePath());
        $this->assertStringContainsString(
            'roof condition',
            $content,
            'FAQ_KEY_KEYWORD_MAP must include "roof condition" so the detector fires for that phrase.'
        );
    }

    public function test_runner_file_contains_derive_field_label_method(): void
    {
        $content = file_get_contents($this->runnerFilePath());
        $this->assertStringContainsString(
            'deriveFieldLabel',
            $content,
            'AskAiRunnerV2Service must contain the deriveFieldLabel() method.'
        );
    }

    public function test_runner_file_contains_roof_label_in_label_map(): void
    {
        $content = file_get_contents($this->runnerFilePath());
        $this->assertStringContainsString(
            'Roof information',
            $content,
            'deriveFieldLabel() must map roof_age_and_condition to "Roof information".'
        );
    }

    public function test_runner_file_guards_faq_answers_prefix_for_missing_data(): void
    {
        $content = file_get_contents($this->runnerFilePath());
        $this->assertStringContainsString(
            "str_starts_with(\$normalizedFieldKey, 'faq_answers.')",
            $content,
            'Runner must use str_starts_with check for faq_answers.* keys in the missing-data guard.'
        );
    }

    public function test_runner_file_checks_empty_allowed_context(): void
    {
        $content = file_get_contents($this->runnerFilePath());
        $this->assertStringContainsString(
            "array_key_exists('allowed_context', \$promptPackage)",
            $content,
            'Runner must use array_key_exists check on allowed_context to detect the missing-data case '
            . '(empty($key ?? []) would incorrectly fire when the key is absent).'
        );
    }
}
