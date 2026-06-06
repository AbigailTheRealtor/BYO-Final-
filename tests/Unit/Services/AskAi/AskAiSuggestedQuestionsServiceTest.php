<?php

namespace Tests\Unit\Services\AskAi;

use Tests\TestCase;
use App\Services\AskAi\AskAiSuggestedQuestionsService;

class AskAiSuggestedQuestionsServiceTest extends TestCase
{
    private AskAiSuggestedQuestionsService $service;

    private const APPROVED_TYPES = [
        'listing_facts',
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

    /**
     * Build a well-formed FAQ answer object matching the shape produced by
     * AskAiContextBuilderService::buildFaqAnswers().
     */
    private function makeFaqEntry(string $answerText): array
    {
        return [
            'config_key'            => 'test_key',
            'answer_text'           => $answerText,
            'question_label'        => 'Test Question',
            'question_group'        => 'Test Group',
            'intelligence_category' => 'test_group',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AskAiSuggestedQuestionsService();
    }

    // -------------------------------------------------------------------------
    // Existing baseline tests
    // -------------------------------------------------------------------------

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
            'listing_facts'        => 0,
            'property_standout'    => 1,
            'suited_audience'      => 2,
            'buyer_tenant_match'   => 3,
            'compatibility_signals'=> 4,
            'missing_data'         => 5,
            'marketing_angles'     => 6,
            'educational'          => 7,
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

    public function test_every_suggestion_has_category_label(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $type) {
            $results = $this->service->forListing($type);
            foreach ($results as $i => $item) {
                $this->assertArrayHasKey('category_label', $item, "[$type][$i] missing category_label");
                $this->assertIsString($item['category_label'], "[$type][$i].category_label must be string");
                $this->assertNotEmpty($item['category_label'], "[$type][$i].category_label must not be empty");
            }
        }
    }

    public function test_every_suggestion_has_category_icon(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $type) {
            $results = $this->service->forListing($type);
            foreach ($results as $i => $item) {
                $this->assertArrayHasKey('category_icon', $item, "[$type][$i] missing category_icon");
                $this->assertIsString($item['category_icon'], "[$type][$i].category_icon must be string");
                $this->assertNotEmpty($item['category_icon'], "[$type][$i].category_icon must not be empty");
            }
        }
    }

    public function test_category_label_and_icon_match_question_type(): void
    {
        $canonicalMap = [
            'listing_facts'         => ['category_label' => 'Listing Facts',  'category_icon' => 'fa-circle-info'],
            'property_standout'     => ['category_label' => 'Property',       'category_icon' => 'fa-house'],
            'suited_audience'       => ['category_label' => 'Audience',        'category_icon' => 'fa-bullseye'],
            'buyer_tenant_match'    => ['category_label' => 'Match',           'category_icon' => 'fa-chart-simple'],
            'compatibility_signals' => ['category_label' => 'Compatibility',   'category_icon' => 'fa-scale-balanced'],
            'missing_data'          => ['category_label' => 'Missing Info',    'category_icon' => 'fa-circle-question'],
            'marketing_angles'      => ['category_label' => 'Marketing',       'category_icon' => 'fa-lightbulb'],
            'educational'           => ['category_label' => 'Education',       'category_icon' => 'fa-book-open'],
        ];

        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $type) {
            $results = $this->service->forListing($type);
            foreach ($results as $i => $item) {
                $qt = $item['question_type'];
                if (isset($canonicalMap[$qt])) {
                    $this->assertSame(
                        $canonicalMap[$qt]['category_label'],
                        $item['category_label'],
                        "[$type][$i] category_label mismatch for question_type '$qt'"
                    );
                    $this->assertSame(
                        $canonicalMap[$qt]['category_icon'],
                        $item['category_icon'],
                        "[$type][$i] category_icon mismatch for question_type '$qt'"
                    );
                } else {
                    $this->assertSame('General', $item['category_label'], "[$type][$i] unmapped type should fallback to 'General'");
                    $this->assertSame('fa-comment-dots', $item['category_icon'], "[$type][$i] unmapped type should fallback to 'fa-comment-dots'");
                }
            }
        }
    }

    public function test_original_required_keys_still_present(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $type) {
            $results = $this->service->forListing($type);
            foreach ($results as $i => $item) {
                $this->assertArrayHasKey('label',          $item, "[$type][$i] missing label");
                $this->assertArrayHasKey('question',       $item, "[$type][$i] missing question");
                $this->assertArrayHasKey('question_type',  $item, "[$type][$i] missing question_type");
                $this->assertArrayHasKey('category_label', $item, "[$type][$i] missing category_label");
                $this->assertArrayHasKey('category_icon',  $item, "[$type][$i] missing category_icon");
                $this->assertNotEmpty($item['label'],         "[$type][$i].label must not be empty");
                $this->assertNotEmpty($item['question'],      "[$type][$i].question must not be empty");
                $this->assertNotEmpty($item['question_type'], "[$type][$i].question_type must not be empty");
            }
        }
    }

    // -------------------------------------------------------------------------
    // Internal keys must never appear in output
    // -------------------------------------------------------------------------

    public function test_required_context_path_is_not_surfaced_in_output(): void
    {
        $withContext = ['listing' => ['bedrooms' => 3], 'faq_answers' => []];

        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $type) {
            foreach ([[], $withContext] as $ctx) {
                $results = $this->service->forListing($type, $ctx);
                foreach ($results as $i => $item) {
                    $this->assertArrayNotHasKey(
                        'required_context_path',
                        $item,
                        "[$type][$i] internal key 'required_context_path' must not appear in output"
                    );
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Context-aware filtering — empty context falls back to static pool
    // -------------------------------------------------------------------------

    public function test_empty_context_returns_same_results_as_no_context(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $type) {
            $noCtx    = $this->service->forListing($type);
            $emptyCtx = $this->service->forListing($type, []);
            $this->assertSame($noCtx, $emptyCtx, "$type: empty context must produce identical results to no context");
        }
    }

    public function test_empty_context_still_returns_five_or_fewer_suggestions(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $type) {
            $results = $this->service->forListing($type, []);
            $this->assertLessThanOrEqual(5, count($results), "$type with empty context must return ≤ 5");
        }
    }

    // -------------------------------------------------------------------------
    // Context-aware filtering — listing_facts chips suppressed when path absent
    // -------------------------------------------------------------------------

    public function test_listing_facts_chips_suppressed_when_listing_context_absent(): void
    {
        $context = ['listing' => [], 'faq_answers' => []];

        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $type) {
            $results = $this->service->forListing($type, $context);
            foreach ($results as $i => $item) {
                $this->assertNotSame(
                    'listing_facts',
                    $item['question_type'],
                    "[$type][$i] listing_facts chip must be suppressed when listing context fields are absent"
                );
            }
        }
    }

    public function test_listing_facts_chips_included_when_listing_field_present(): void
    {
        $context = [
            'listing'     => ['bedrooms' => 3],
            'faq_answers' => [],
        ];

        $resultsWithContext = $this->service->forListing('seller', $context);

        $hasListingFactsChip = collect($resultsWithContext)->contains('question_type', 'listing_facts');

        $this->assertTrue(
            $hasListingFactsChip,
            'seller: a listing_facts chip (bedrooms) must be included when listing.bedrooms is present'
        );
    }

    public function test_listing_facts_chip_suppressed_when_field_is_null(): void
    {
        $context = [
            'listing'     => ['bedrooms' => null],
            'faq_answers' => [],
        ];

        $results = $this->service->forListing('seller', $context);

        $bedroomsChipPresent = false;
        foreach ($results as $item) {
            if ($item['question_type'] === 'listing_facts' &&
                str_contains(strtolower($item['question']), 'bedroom')) {
                $bedroomsChipPresent = true;
            }
        }

        $this->assertFalse(
            $bedroomsChipPresent,
            'seller: the bedrooms listing_facts chip must be suppressed when listing.bedrooms is null'
        );
    }

    public function test_non_listing_facts_chips_never_filtered_by_context(): void
    {
        $context = ['listing' => [], 'faq_answers' => []];

        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $type) {
            $noCtxTypes = array_column($this->service->forListing($type), 'question_type');
            $ctxTypes   = array_column($this->service->forListing($type, $context), 'question_type');

            $nonFactsNoCtx = array_filter($noCtxTypes, fn($t) => $t !== 'listing_facts');
            $nonFactsCtx   = array_filter($ctxTypes,   fn($t) => $t !== 'listing_facts');

            foreach ($nonFactsNoCtx as $qt) {
                $this->assertContains(
                    $qt,
                    array_values($nonFactsCtx),
                    "[$type] non-listing_facts chip of type '$qt' must not be filtered out by context"
                );
            }
        }
    }

    // -------------------------------------------------------------------------
    // Context-aware filtering — FAQ-driven chips
    // FAQ answer shape: ['answer_text' => '...', 'question_label' => '...', ...]
    // This matches AskAiContextBuilderService::buildFaqAnswers() output.
    // -------------------------------------------------------------------------

    public function test_faq_chip_included_when_faq_entry_has_answer_text(): void
    {
        $context = [
            'listing'     => [],
            'faq_answers' => [
                'roof_age_and_condition' => $this->makeFaqEntry('Roof replaced 2019, good condition'),
            ],
        ];

        $results = $this->service->forListing('seller', $context);

        $roofChipPresent = false;
        foreach ($results as $item) {
            if ($item['question_type'] === 'listing_facts' &&
                stripos($item['question'], 'roof') !== false) {
                $roofChipPresent = true;
            }
        }

        $this->assertTrue(
            $roofChipPresent,
            'seller: the roof FAQ chip must be included when faq_answers.roof_age_and_condition has a valid answer_text'
        );
    }

    public function test_faq_chip_suppressed_when_faq_key_absent(): void
    {
        $context = [
            'listing'     => [],
            'faq_answers' => [],
        ];

        $results = $this->service->forListing('seller', $context);

        $roofChipPresent = false;
        foreach ($results as $item) {
            if ($item['question_type'] === 'listing_facts' &&
                stripos($item['question'], 'roof') !== false) {
                $roofChipPresent = true;
            }
        }

        $this->assertFalse(
            $roofChipPresent,
            'seller: roof FAQ chip must be suppressed when faq_answers.roof_age_and_condition is absent'
        );
    }

    public function test_faq_chip_suppressed_when_answer_text_is_empty(): void
    {
        $context = [
            'listing'     => [],
            'faq_answers' => [
                'laundry_situation' => $this->makeFaqEntry(''),
            ],
        ];

        foreach (['landlord', 'tenant'] as $type) {
            $results = $this->service->forListing($type, $context);

            $laundryChipPresent = false;
            foreach ($results as $item) {
                if ($item['question_type'] === 'listing_facts' &&
                    stripos($item['question'], 'laundry') !== false) {
                    $laundryChipPresent = true;
                }
            }

            $this->assertFalse(
                $laundryChipPresent,
                "[$type] laundry FAQ chip must be suppressed when answer_text is empty"
            );
        }
    }

    public function test_faq_chip_suppressed_when_faq_value_is_not_structured_object(): void
    {
        $context = [
            'listing'     => [],
            'faq_answers' => [
                'laundry_situation' => 'plain string — not a structured FAQ object',
            ],
        ];

        foreach (['landlord', 'tenant'] as $type) {
            $results = $this->service->forListing($type, $context);

            $laundryChipPresent = false;
            foreach ($results as $item) {
                if ($item['question_type'] === 'listing_facts' &&
                    stripos($item['question'], 'laundry') !== false) {
                    $laundryChipPresent = true;
                }
            }

            $this->assertFalse(
                $laundryChipPresent,
                "[$type] laundry FAQ chip must be suppressed when FAQ value is not a structured object"
            );
        }
    }

    public function test_faq_chip_suppressed_when_answer_text_key_missing(): void
    {
        $context = [
            'listing'     => [],
            'faq_answers' => [
                'roof_age_and_condition' => [
                    'config_key'     => 'roof_age_and_condition',
                    'question_label' => 'How old is the roof?',
                    // answer_text intentionally absent
                ],
            ],
        ];

        $results = $this->service->forListing('seller', $context);

        $roofChipPresent = false;
        foreach ($results as $item) {
            if ($item['question_type'] === 'listing_facts' &&
                stripos($item['question'], 'roof') !== false) {
                $roofChipPresent = true;
            }
        }

        $this->assertFalse(
            $roofChipPresent,
            'seller: roof FAQ chip must be suppressed when answer_text key is missing from the FAQ object'
        );
    }

    public function test_multiple_faq_chips_independently_gated(): void
    {
        $contextBoth = [
            'listing'     => [],
            'faq_answers' => [
                'laundry_situation'      => $this->makeFaqEntry('In-unit washer/dryer'),
                'heating_cooling_system' => $this->makeFaqEntry('Central HVAC'),
            ],
        ];
        $contextOne = [
            'listing'     => [],
            'faq_answers' => ['laundry_situation' => $this->makeFaqEntry('In-unit washer/dryer')],
        ];

        $resultsBoth = $this->service->forListing('landlord', $contextBoth);
        $resultsOne  = $this->service->forListing('landlord', $contextOne);

        $countBothFaq = count(array_filter($resultsBoth, fn($i) => $i['question_type'] === 'listing_facts'));
        $countOneFaq  = count(array_filter($resultsOne,  fn($i) => $i['question_type'] === 'listing_facts'));

        $this->assertGreaterThanOrEqual(
            $countOneFaq,
            $countBothFaq,
            'landlord: providing both FAQ keys must yield at least as many listing_facts chips as providing one'
        );
    }

    // -------------------------------------------------------------------------
    // Context-aware filtering — per-role field name contract
    // Field names must match AskAiContextBuilderService::extractFactualFields()
    // -------------------------------------------------------------------------

    public function test_seller_listing_facts_chips_with_full_context(): void
    {
        $context = [
            'listing' => [
                'address'             => '123 Main St',
                'asking_price'        => 450000,
                'bedrooms'            => 4,
                'rental_restrictions' => 'No short-term rentals',
                'lease_terms'         => '12 months minimum',
            ],
            'faq_answers' => [
                'roof_age_and_condition' => $this->makeFaqEntry('Replaced 2020'),
                'average_utility_costs'  => $this->makeFaqEntry('$150/month'),
                'heating_cooling_system' => $this->makeFaqEntry('Central HVAC'),
            ],
        ];

        $results = $this->service->forListing('seller', $context);

        $this->assertLessThanOrEqual(5, count($results), 'seller with full context must still cap at 5');
        $this->assertGreaterThanOrEqual(
            1,
            count(array_filter($results, fn($i) => $i['question_type'] === 'listing_facts')),
            'seller with full context must include at least one listing_facts chip'
        );
    }

    public function test_buyer_listing_facts_chips_use_max_price_and_financing_type(): void
    {
        // Correct field names from AskAiContextBuilderService::extractFactualFields() for buyer:
        //   max_price      (not max_budget)
        //   financing_type (singular, not financing_types)
        $context = [
            'listing' => [
                'max_price'      => 600000,
                'financing_type' => 'Conventional',
                'bedrooms'       => 3,
            ],
            'faq_answers' => [],
        ];

        $results = $this->service->forListing('buyer', $context);

        $this->assertLessThanOrEqual(5, count($results));
        $this->assertGreaterThanOrEqual(
            1,
            count(array_filter($results, fn($i) => $i['question_type'] === 'listing_facts')),
            'buyer with max_price/financing_type context must include at least one listing_facts chip'
        );
    }

    public function test_buyer_wrong_field_names_produce_no_listing_facts_chips(): void
    {
        // Verify that old/wrong field names (max_budget, financing_types) do NOT trigger chips
        $context = [
            'listing' => [
                'max_budget'      => 600000,   // wrong — correct is max_price
                'financing_types' => 'Cash',   // wrong — correct is financing_type (singular)
            ],
            'faq_answers' => [],
        ];

        $results = $this->service->forListing('buyer', $context);

        $factChips = array_filter($results, fn($i) => $i['question_type'] === 'listing_facts');
        $this->assertCount(0, $factChips, 'buyer: wrong field names must not surface any listing_facts chips');
    }

    public function test_landlord_listing_facts_chips_use_rent_amount_and_smoking_policy(): void
    {
        // Correct field names from AskAiContextBuilderService::extractFactualFields() for landlord:
        //   rent_amount    (not asking_price)
        //   smoking_policy (not smoking_allowed)
        $context = [
            'listing' => [
                'rent_amount'    => 2500,
                'bedrooms'       => 2,
                'pet_policy'     => 'Cats allowed, no dogs',
                'available_date' => '2026-08-01',
                'utilities'      => 'Water and trash',
                'smoking_policy' => 'No smoking',
            ],
            'faq_answers' => [
                'laundry_situation'      => $this->makeFaqEntry('In-unit washer/dryer'),
                'heating_cooling_system' => $this->makeFaqEntry('Central HVAC'),
            ],
        ];

        $results = $this->service->forListing('landlord', $context);

        $this->assertLessThanOrEqual(5, count($results));
        $this->assertGreaterThanOrEqual(
            1,
            count(array_filter($results, fn($i) => $i['question_type'] === 'listing_facts')),
            'landlord with rent_amount/smoking_policy context must include at least one listing_facts chip'
        );
    }

    public function test_landlord_wrong_field_names_produce_no_listing_facts_chips(): void
    {
        // Verify that old/wrong field names do NOT trigger chips
        $context = [
            'listing' => [
                'asking_price'   => 2500,      // wrong — correct is rent_amount
                'smoking_allowed'=> false,     // wrong — correct is smoking_policy
            ],
            'faq_answers' => [],
        ];

        $results = $this->service->forListing('landlord', $context);

        $factChips = array_filter($results, fn($i) => $i['question_type'] === 'listing_facts');
        $this->assertCount(0, $factChips, 'landlord: wrong field names must not surface any listing_facts chips');
    }

    public function test_tenant_listing_facts_chips_use_appliances_and_pet_information(): void
    {
        // Correct field names from AskAiContextBuilderService::extractFactualFields() for tenant:
        //   max_rent        (correct)
        //   appliances      (not required_appliances)
        //   pet_information (not pets_allowed)
        $context = [
            'listing' => [
                'max_rent'        => 2000,
                'appliances'      => 'Dishwasher, microwave',
                'pet_information' => 'One small dog',
            ],
            'faq_answers' => [
                'laundry_situation' => $this->makeFaqEntry('In-unit required'),
            ],
        ];

        $results = $this->service->forListing('tenant', $context);

        $this->assertLessThanOrEqual(5, count($results));
        $this->assertGreaterThanOrEqual(
            1,
            count(array_filter($results, fn($i) => $i['question_type'] === 'listing_facts')),
            'tenant with appliances/pet_information context must include at least one listing_facts chip'
        );
    }

    public function test_tenant_wrong_field_names_produce_no_listing_facts_chips(): void
    {
        // Verify that old/wrong field names do NOT trigger chips
        $context = [
            'listing' => [
                'required_appliances' => 'Dishwasher', // wrong — correct is appliances
                'pets_allowed'        => true,         // wrong — correct is pet_information
            ],
            'faq_answers' => [],
        ];

        $results = $this->service->forListing('tenant', $context);

        $factChips = array_filter($results, fn($i) => $i['question_type'] === 'listing_facts');
        $this->assertCount(0, $factChips, 'tenant: wrong field names must not surface any listing_facts chips');
    }

    // -------------------------------------------------------------------------
    // 5-chip cap is respected across all context scenarios
    // -------------------------------------------------------------------------

    public function test_five_chip_cap_preserved_with_full_context_for_all_roles(): void
    {
        $contexts = [
            'seller' => [
                'listing' => [
                    'address' => '1 Oak Ave', 'asking_price' => 300000, 'bedrooms' => 3,
                    'rental_restrictions' => 'HOA rules', 'lease_terms' => '12 months',
                ],
                'faq_answers' => [
                    'roof_age_and_condition' => $this->makeFaqEntry('New 2023'),
                    'average_utility_costs'  => $this->makeFaqEntry('$200/mo'),
                    'heating_cooling_system' => $this->makeFaqEntry('HVAC'),
                ],
            ],
            'buyer' => [
                'listing' => ['max_price' => 400000, 'financing_type' => 'Cash', 'bedrooms' => 2],
                'faq_answers' => [],
            ],
            'landlord' => [
                'listing' => [
                    'rent_amount' => 1800, 'bedrooms' => 1, 'pet_policy' => 'No pets',
                    'available_date' => '2026-07-01', 'utilities' => 'None',
                    'smoking_policy' => 'Non-smoking only',
                ],
                'faq_answers' => [
                    'laundry_situation'      => $this->makeFaqEntry('Shared'),
                    'heating_cooling_system' => $this->makeFaqEntry('Window units'),
                ],
            ],
            'tenant' => [
                'listing' => ['max_rent' => 1500, 'appliances' => 'Washer/dryer', 'pet_information' => 'No pets'],
                'faq_answers' => ['laundry_situation' => $this->makeFaqEntry('In-unit preferred')],
            ],
        ];

        foreach ($contexts as $type => $context) {
            $results = $this->service->forListing($type, $context);
            $this->assertLessThanOrEqual(5, count($results), "$type with full context must never exceed 5 chips");
        }
    }

    // -------------------------------------------------------------------------
    // No prohibited phrases in listing_facts question text
    // -------------------------------------------------------------------------

    public function test_no_prohibited_phrases_in_listing_facts_questions_with_context(): void
    {
        $context = [
            'listing' => [
                'address' => '1 Main', 'asking_price' => 500000, 'bedrooms' => 3,
                'rental_restrictions' => 'None', 'lease_terms' => '12 months',
                'max_price' => 600000, 'financing_type' => 'Cash',
                'rent_amount' => 1800, 'pet_policy' => 'No pets',
                'available_date' => '2026-09-01', 'utilities' => 'Water',
                'smoking_policy' => 'Non-smoking',
                'max_rent' => 1800, 'appliances' => 'Dishwasher',
                'pet_information' => 'One cat',
            ],
            'faq_answers' => [
                'roof_age_and_condition'   => $this->makeFaqEntry('Good'),
                'average_utility_costs'    => $this->makeFaqEntry('$100/mo'),
                'heating_cooling_system'   => $this->makeFaqEntry('HVAC'),
                'laundry_situation'        => $this->makeFaqEntry('In-unit'),
            ],
        ];

        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $type) {
            $results = $this->service->forListing($type, $context);
            foreach ($results as $i => $item) {
                if ($item['question_type'] !== 'listing_facts') {
                    continue;
                }
                $lower = strtolower($item['question']);
                foreach (self::PROHIBITED_PHRASES as $phrase) {
                    $this->assertStringNotContainsStringIgnoringCase(
                        $phrase,
                        $lower,
                        "[$type][$i] listing_facts chip contains prohibited phrase '$phrase': \"{$item['question']}\""
                    );
                }
            }
        }
    }
}
