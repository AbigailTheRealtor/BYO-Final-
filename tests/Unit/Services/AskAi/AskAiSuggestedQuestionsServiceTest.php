<?php

namespace Tests\Unit\Services\AskAi;

use Tests\TestCase;
use App\Services\AskAi\AskAiSuggestedQuestionsService;

class AskAiSuggestedQuestionsServiceTest extends TestCase
{
    private AskAiSuggestedQuestionsService $service;

    private const APPROVED_TYPES = [
        'property_standout',
        'suited_audience',
        'buyer_tenant_match',
        'compatibility_signals',
        'missing_data',
        'marketing_angles',
        'educational',
    ];

    /**
     * Phrases that must never appear in any suggested question text.
     * Source: ASK_AI_SUGGESTED_QUESTIONS_ENGINE_SPEC_V1 Section 4.
     */
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
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AskAiSuggestedQuestionsService();
    }

    public function test_seller_returns_at_least_three_suggestions(): void
    {
        $results = $this->service->forListing('seller');
        $this->assertGreaterThanOrEqual(3, count($results), 'seller must return ≥ 3 suggestions');
    }

    public function test_buyer_returns_at_least_three_suggestions(): void
    {
        $results = $this->service->forListing('buyer');
        $this->assertGreaterThanOrEqual(3, count($results), 'buyer must return ≥ 3 suggestions');
    }

    public function test_landlord_returns_at_least_three_suggestions(): void
    {
        $results = $this->service->forListing('landlord');
        $this->assertGreaterThanOrEqual(3, count($results), 'landlord must return ≥ 3 suggestions');
    }

    public function test_tenant_returns_at_least_three_suggestions(): void
    {
        $results = $this->service->forListing('tenant');
        $this->assertGreaterThanOrEqual(3, count($results), 'tenant must return ≥ 3 suggestions');
    }

    public function test_each_listing_type_result_has_required_keys(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $type) {
            $results = $this->service->forListing($type);
            foreach ($results as $i => $item) {
                $this->assertArrayHasKey('label',         $item, "$type[$i] missing label");
                $this->assertArrayHasKey('question',      $item, "$type[$i] missing question");
                $this->assertArrayHasKey('question_type', $item, "$type[$i] missing question_type");
                $this->assertIsString($item['label'],         "$type[$i].label must be string");
                $this->assertIsString($item['question'],      "$type[$i].question must be string");
                $this->assertIsString($item['question_type'], "$type[$i].question_type must be string");
                $this->assertNotEmpty($item['label'],         "$type[$i].label must not be empty");
                $this->assertNotEmpty($item['question'],      "$type[$i].question must not be empty");
                $this->assertNotEmpty($item['question_type'], "$type[$i].question_type must not be empty");
            }
        }
    }

    public function test_every_question_type_is_in_approved_classification_list(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $type) {
            $results = $this->service->forListing($type);
            foreach ($results as $i => $item) {
                $this->assertContains(
                    $item['question_type'],
                    self::APPROVED_TYPES,
                    "[$type][$i] question_type '{$item['question_type']}' is not in the approved list"
                );
            }
        }
    }

    public function test_no_prohibited_phrases_appear_in_any_question_text(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $type) {
            $results = $this->service->forListing($type);
            foreach ($results as $i => $item) {
                $lower = strtolower($item['question']);
                foreach (self::PROHIBITED_PHRASES as $phrase) {
                    $this->assertStringNotContainsStringIgnoringCase(
                        $phrase,
                        $lower,
                        "[$type][$i] question contains prohibited phrase '$phrase': \"{$item['question']}\""
                    );
                }
            }
        }
    }

    public function test_maximum_five_suggestions_returned_per_type(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $type) {
            $results = $this->service->forListing($type);
            $this->assertLessThanOrEqual(5, count($results), "$type must return ≤ 5 suggestions");
        }
    }

    public function test_empty_context_still_returns_fallback_suggestions(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $type) {
            $results = $this->service->forListing($type, []);
            $this->assertGreaterThanOrEqual(3, count($results), "$type with empty context must return ≥ 3 suggestions");
        }
    }

    public function test_invalid_listing_type_returns_empty_array(): void
    {
        $results = $this->service->forListing('unknown');
        $this->assertIsArray($results);
        $this->assertEmpty($results, 'unrecognised listing type must return []');
    }

    public function test_empty_string_listing_type_returns_empty_array(): void
    {
        $results = $this->service->forListing('');
        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function test_results_are_ordered_by_category_priority(): void
    {
        $priorityOrder = [
            'property_standout'    => 0,
            'suited_audience'      => 1,
            'buyer_tenant_match'   => 2,
            'compatibility_signals'=> 3,
            'missing_data'         => 4,
            'marketing_angles'     => 5,
            'educational'          => 6,
        ];

        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $type) {
            $results  = $this->service->forListing($type);
            $lastRank = -1;
            foreach ($results as $i => $item) {
                $rank = $priorityOrder[$item['question_type']] ?? PHP_INT_MAX;
                $this->assertGreaterThanOrEqual(
                    $lastRank,
                    $rank,
                    "[$type][$i] category '{$item['question_type']}' is out of priority order"
                );
                $lastRank = $rank;
            }
        }
    }

    public function test_service_has_no_external_dependencies(): void
    {
        $reflection = new \ReflectionClass(AskAiSuggestedQuestionsService::class);
        $constructor = $reflection->getConstructor();

        $this->assertNull(
            $constructor,
            'AskAiSuggestedQuestionsService must have no constructor (no injected dependencies)'
        );
    }
}
