<?php

namespace Tests\Unit\Services\AskAi;

use Tests\TestCase;
use App\Services\AskAi\AskAiFollowUpQuestionService;

class AskAiFollowUpQuestionServiceTest extends TestCase
{
    private AskAiFollowUpQuestionService $service;

    private const APPROVED_TYPES = [
        'property_standout',
        'suited_audience',
        'buyer_tenant_match',
        'compatibility_signals',
        'missing_data',
        'marketing_angles',
        'educational',
    ];

    private const PROHIBITED_PHRASES = [
        'legal right',
        'legal rights',
        'legally required',
        'enforceable',
        'am i legally',
        'should i accept',
        'what price should i counter',
        'is this a good deal',
        'negotiate the commission',
        'will i qualify',
        'what loan type',
        'how much should i put down',
        'debt-to-income',
        'capital gains',
        '1031 exchange',
        'what can i deduct',
        'is this a good investment',
        'cap rate',
        'will this appreciate',
        'should i buy this as a rental',
        'will this property go up',
        'is now a good time to buy',
        'what will rents be',
        'is the market going to',
        'what kind of people live',
        'school demographics',
        'safe area for someone',
        'safe for someone',
        'good for families like mine',
        'good for someone with my background',
        'do i have to disclose',
        'am i required to accept',
        'race',
        'religion',
        'national origin',
        'sex',
        'disability',
        'familial status',
        'crime rate',
        'criminal',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AskAiFollowUpQuestionService();
    }

    private function makeReadyResponse(string $questionType = 'property_standout'): array
    {
        return [
            'success'            => true,
            'status'             => 'ready',
            'answer'             => 'Test answer.',
            'disclosures'        => 'AI-generated.',
            'source_attribution' => 'Listing data.',
            'refusal_message'    => null,
            'error'              => null,
        ];
    }

    private function makeClassification(string $questionType): array
    {
        return ['question_type' => $questionType];
    }

    public function test_property_standout_result_returns_safe_follow_ups(): void
    {
        $result = $this->service->forResult(
            $this->makeReadyResponse(),
            $this->makeClassification('property_standout')
        );

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertLessThanOrEqual(3, count($result));

        foreach ($result as $item) {
            $this->assertArrayHasKey('label',         $item);
            $this->assertArrayHasKey('question',      $item);
            $this->assertArrayHasKey('question_type', $item);
            $this->assertContains($item['question_type'], self::APPROVED_TYPES);
        }
    }

    public function test_marketing_angles_result_returns_safe_follow_ups(): void
    {
        $result = $this->service->forResult(
            $this->makeReadyResponse(),
            $this->makeClassification('marketing_angles')
        );

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertLessThanOrEqual(3, count($result));

        foreach ($result as $item) {
            $this->assertArrayHasKey('label',         $item);
            $this->assertArrayHasKey('question',      $item);
            $this->assertArrayHasKey('question_type', $item);
            $this->assertContains($item['question_type'], self::APPROVED_TYPES);
        }
    }

    public function test_compatibility_signals_result_returns_safe_follow_ups(): void
    {
        $result = $this->service->forResult(
            $this->makeReadyResponse(),
            $this->makeClassification('compatibility_signals')
        );

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertLessThanOrEqual(3, count($result));

        foreach ($result as $item) {
            $this->assertArrayHasKey('label',         $item);
            $this->assertArrayHasKey('question',      $item);
            $this->assertArrayHasKey('question_type', $item);
            $this->assertContains($item['question_type'], self::APPROVED_TYPES);
        }
    }

    public function test_blocked_response_returns_empty_array(): void
    {
        $blocked = [
            'success'            => false,
            'status'             => 'blocked',
            'answer'             => null,
            'refusal_message'    => 'Not allowed.',
            'disclosures'        => null,
            'source_attribution' => null,
            'error'              => null,
        ];

        $result = $this->service->forResult($blocked, $this->makeClassification('property_standout'));

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_failed_response_returns_empty_array(): void
    {
        $failed = [
            'success'            => false,
            'status'             => 'failed',
            'answer'             => null,
            'refusal_message'    => null,
            'disclosures'        => null,
            'source_attribution' => null,
            'error'              => 'Something went wrong.',
        ];

        $result = $this->service->forResult($failed, $this->makeClassification('property_standout'));

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_insufficient_context_response_returns_empty_array(): void
    {
        $ic = [
            'success'            => false,
            'status'             => 'insufficient_context',
            'answer'             => 'Not enough data.',
            'refusal_message'    => null,
            'disclosures'        => null,
            'source_attribution' => null,
            'error'              => null,
        ];

        $result = $this->service->forResult($ic, $this->makeClassification('property_standout'));

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_unsupported_response_returns_empty_array(): void
    {
        $unsupported = [
            'success'            => false,
            'status'             => 'unsupported',
            'answer'             => 'Unsupported type.',
            'refusal_message'    => null,
            'disclosures'        => null,
            'source_attribution' => null,
            'error'              => null,
        ];

        $result = $this->service->forResult($unsupported, $this->makeClassification('property_standout'));

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_result_set_is_never_larger_than_three(): void
    {
        foreach (self::APPROVED_TYPES as $type) {
            $result = $this->service->forResult(
                $this->makeReadyResponse(),
                $this->makeClassification($type)
            );
            $this->assertLessThanOrEqual(3, count($result), "question_type '$type' returned more than 3 follow-ups");
        }
    }

    public function test_every_returned_question_type_is_in_approved_categories(): void
    {
        foreach (self::APPROVED_TYPES as $incomingType) {
            $result = $this->service->forResult(
                $this->makeReadyResponse(),
                $this->makeClassification($incomingType)
            );
            foreach ($result as $i => $item) {
                $this->assertContains(
                    $item['question_type'],
                    self::APPROVED_TYPES,
                    "[$incomingType][$i] returned question_type '{$item['question_type']}' is not in the approved list"
                );
            }
        }
    }

    public function test_no_prohibited_phrases_appear_in_any_label_or_question(): void
    {
        foreach (self::APPROVED_TYPES as $incomingType) {
            $result = $this->service->forResult(
                $this->makeReadyResponse(),
                $this->makeClassification($incomingType)
            );
            foreach ($result as $i => $item) {
                $lowerLabel    = strtolower($item['label']);
                $lowerQuestion = strtolower($item['question']);
                foreach (self::PROHIBITED_PHRASES as $phrase) {
                    $this->assertStringNotContainsStringIgnoringCase(
                        $phrase,
                        $lowerLabel,
                        "[$incomingType][$i] label contains prohibited phrase '$phrase': \"{$item['label']}\""
                    );
                    $this->assertStringNotContainsStringIgnoringCase(
                        $phrase,
                        $lowerQuestion,
                        "[$incomingType][$i] question contains prohibited phrase '$phrase': \"{$item['question']}\""
                    );
                }
            }
        }
    }

    public function test_unrecognised_question_type_returns_empty_array(): void
    {
        $result = $this->service->forResult(
            $this->makeReadyResponse(),
            ['question_type' => 'totally_unknown_type']
        );

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_empty_classification_returns_empty_array(): void
    {
        $result = $this->service->forResult($this->makeReadyResponse(), []);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_service_has_no_external_dependencies(): void
    {
        $reflection  = new \ReflectionClass(AskAiFollowUpQuestionService::class);
        $constructor = $reflection->getConstructor();

        $this->assertNull(
            $constructor,
            'AskAiFollowUpQuestionService must have no constructor (no injected dependencies)'
        );
    }

    public function test_service_source_contains_no_prohibited_external_calls(): void
    {
        $reflection = new \ReflectionClass(AskAiFollowUpQuestionService::class);
        $rawSource  = file_get_contents($reflection->getFileName());

        // Strip PHP comments (T_COMMENT, T_DOC_COMMENT) before scanning so that
        // governance block comments mentioning "OpenAI" etc. do not false-positive.
        $tokens = token_get_all($rawSource);
        $codeOnly = '';
        foreach ($tokens as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $codeOnly .= is_array($token) ? $token[1] : $token;
        }

        // Patterns that indicate real executable calls — not comment text.
        $prohibited = [
            'OpenAI class usage'       => 'OpenAI::',
            'OpenAI namespace import'  => '\\OpenAI\\',
            'new OpenAI instantiation' => 'new OpenAI',
            'Http facade'              => 'Http::',
            'DB facade'                => 'DB::',
            'GuzzleHttp namespace'     => 'GuzzleHttp\\',
            'curl_init call'           => 'curl_init(',
            'stream_context_create'    => 'stream_context_create(',
        ];

        foreach ($prohibited as $label => $token) {
            $this->assertStringNotContainsString(
                $token,
                $codeOnly,
                "AskAiFollowUpQuestionService must not contain '$token' ($label) — " .
                "this service is prohibited from making external OpenAI, HTTP, or DB calls."
            );
        }
    }

    public function test_each_result_entry_has_required_keys_and_non_empty_strings(): void
    {
        foreach (self::APPROVED_TYPES as $type) {
            $result = $this->service->forResult(
                $this->makeReadyResponse(),
                $this->makeClassification($type)
            );
            foreach ($result as $i => $item) {
                $this->assertArrayHasKey('label',         $item, "[$type][$i] missing label");
                $this->assertArrayHasKey('question',      $item, "[$type][$i] missing question");
                $this->assertArrayHasKey('question_type', $item, "[$type][$i] missing question_type");
                $this->assertIsString($item['label'],         "[$type][$i].label must be string");
                $this->assertIsString($item['question'],      "[$type][$i].question must be string");
                $this->assertIsString($item['question_type'], "[$type][$i].question_type must be string");
                $this->assertNotEmpty($item['label'],         "[$type][$i].label must not be empty");
                $this->assertNotEmpty($item['question'],      "[$type][$i].question must not be empty");
                $this->assertNotEmpty($item['question_type'], "[$type][$i].question_type must not be empty");
            }
        }
    }
}
