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

    // =========================================================================
    // Registry-driven chip tests (Phase 4 rev 2)
    // =========================================================================

    /**
     * (a) Every chip returned by forListing() must carry a `category` field
     *     whose value is one of the six approved category strings.
     */
    public function test_every_chip_has_category_field_from_registry(): void
    {
        $approvedCategories = ['Property', 'Financial', 'Match', 'Lifestyle', 'Marketing', 'Education'];

        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $type) {
            $results = $this->service->forListing($type);
            $this->assertNotEmpty($results, "$type must return at least one chip");
            foreach ($results as $i => $item) {
                $this->assertArrayHasKey(
                    'category',
                    $item,
                    "[$type][$i] chip is missing the 'category' field"
                );
                $this->assertContains(
                    $item['category'],
                    $approvedCategories,
                    "[$type][$i] category '{$item['category']}' is not one of the six approved categories"
                );
            }
        }
    }

    /**
     * (b) A requires_data=true chip must be suppressed when its source_path
     *     is absent from the context (empty listing + empty faq_answers).
     */
    public function test_requires_data_chip_suppressed_when_data_absent(): void
    {
        $emptyContext = ['listing' => [], 'faq_answers' => []];

        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $type) {
            $results = $this->service->forListing($type, $emptyContext);
            $factsChips = array_filter($results, fn($c) => $c['question_type'] === 'listing_facts');
            $this->assertCount(
                0,
                $factsChips,
                "$type: listing_facts (requires_data=true) chips must be absent when context is empty"
            );
        }
    }

    /**
     * (c) requires_data=false chips must appear when listing_facts chips are suppressed
     *     by a non-empty context that contains no matching field data.
     *
     * When context is provided but empty (listing={}, faq_answers={}), every
     * requires_data=true chip is suppressed, leaving only static chips. This
     * verifies that static chips (requires_data=false) are never themselves
     * filtered by context — they fill the output when data-aware chips are absent.
     *
     * Note: with no context at all (empty []), all pool entries (including
     * listing_facts) are eligible. Roles with 5+ listing_facts entries will fill
     * all 5 slots with listing_facts chips, which is correct and intentional.
     */
    public function test_static_chips_surface_when_listing_facts_suppressed_by_context(): void
    {
        // A non-empty but data-free context: triggers filterByContext, suppresses
        // all requires_data=true chips, leaving only requires_data=false static chips.
        $emptyDataContext = ['listing' => [], 'faq_answers' => []];

        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $type) {
            $results     = $this->service->forListing($type, $emptyDataContext);
            $staticChips = array_filter(
                $results,
                fn($c) => $c['question_type'] !== 'listing_facts'
            );
            $this->assertGreaterThanOrEqual(
                1,
                count($staticChips),
                "$type: at least one non-listing_facts (requires_data=false) chip must appear when data context is empty"
            );
        }
    }

    /**
     * (d) Chips with public_allowed=false must be hidden when $isAuthenticated is false.
     *     The registry marks marketing_angles and certain missing_data/buyer_tenant_match
     *     chips as not public — none should appear in guest results.
     */
    public function test_public_not_allowed_chips_hidden_for_unauthenticated_viewers(): void
    {
        $guestHiddenTypes = ['marketing_angles', 'buyer_tenant_match'];

        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $type) {
            $guestResults = $this->service->forListing($type, [], false);
            foreach ($guestResults as $i => $item) {
                $this->assertNotContains(
                    $item['question_type'],
                    $guestHiddenTypes,
                    "[$type][$i] question_type '{$item['question_type']}' must not appear for unauthenticated viewers"
                );
            }
        }
    }

    /**
     * (d-extension) Authenticated viewers must see public_allowed=false chips that
     *               guest viewers cannot see — i.e. the auth chip set is a strict
     *               superset of the guest chip set for roles that have public_allowed=false
     *               entries ranked within the top-5 priority slots.
     *
     * We use buyer and tenant — roles where all static chips are public_allowed=false
     * (buyer_tenant_match, missing_data) and they rank above educational in priority,
     * so they are visible to authenticated users but stripped for guests.
     * An empty-data context suppresses listing_facts chips to open up those slots.
     */
    public function test_public_not_allowed_chips_visible_for_authenticated_viewers(): void
    {
        $emptyDataContext = ['listing' => [], 'faq_answers' => []];

        // buyer and tenant have public_allowed=false chips that rank highly enough
        // (buyer_tenant_match = priority 3, missing_data = priority 5) to appear
        // within the 5-chip cap when listing_facts are absent.
        foreach (['buyer', 'tenant'] as $type) {
            $authQuestions  = array_column($this->service->forListing($type, $emptyDataContext, true),  'question');
            $guestQuestions = array_column($this->service->forListing($type, $emptyDataContext, false), 'question');

            $authOnly = array_diff($authQuestions, $guestQuestions);
            $this->assertNotEmpty(
                $authOnly,
                "$type: authenticated viewer must see at least one chip that is hidden from guests (public_allowed=false)"
            );
        }
    }

    /**
     * (e) All four roles must return at least one chip when given a rich context
     *     that populates their primary listing fields.
     */
    public function test_all_roles_produce_chips_with_seeded_context(): void
    {
        $contexts = [
            'seller' => [
                'listing'     => ['address' => '1 Oak Ave', 'asking_price' => 300000, 'bedrooms' => 3],
                'faq_answers' => ['roof_age_and_condition' => $this->makeFaqEntry('2022 replacement')],
            ],
            'buyer' => [
                'listing'     => ['max_price' => 500000, 'financing_type' => 'Conventional', 'bedrooms' => 2],
                'faq_answers' => [],
            ],
            'landlord' => [
                'listing'     => ['rent_amount' => 2000, 'bedrooms' => 2, 'pet_policy' => 'No pets'],
                'faq_answers' => ['laundry_situation' => $this->makeFaqEntry('In-unit')],
            ],
            'tenant' => [
                'listing'     => ['max_rent' => 1800, 'appliances' => 'Dishwasher', 'pet_information' => 'None'],
                'faq_answers' => ['laundry_situation' => $this->makeFaqEntry('In-unit required')],
            ],
        ];

        foreach ($contexts as $type => $context) {
            $results = $this->service->forListing($type, $context);
            $this->assertNotEmpty($results, "$type: must produce at least one chip with seeded context");
            $this->assertLessThanOrEqual(5, count($results), "$type: must not exceed 5 chips with seeded context");
        }
    }

    /**
     * (f) Output is deterministic: identical input always produces the same chip set.
     *     No random rotation or shuffle may occur between consecutive calls.
     */
    public function test_output_is_deterministic_for_same_input(): void
    {
        $context = [
            'listing'     => ['bedrooms' => 3, 'asking_price' => 400000, 'address' => '99 Elm St'],
            'faq_answers' => ['roof_age_and_condition' => $this->makeFaqEntry('Good')],
        ];

        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $type) {
            $first  = $this->service->forListing($type, $context);
            $second = $this->service->forListing($type, $context);
            $this->assertSame(
                $first,
                $second,
                "$type: two consecutive calls with the same context must return identical chip arrays"
            );
        }
    }

    /**
     * (g) The output chip array must not contain any registry-internal keys.
     *     Only the six public keys are permitted: label, question, question_type,
     *     category, category_label, category_icon.
     */
    public function test_registry_internal_keys_never_surfaced_in_output(): void
    {
        $internalKeys = [
            'required_context_path',
            'source_path',
            'requires_data',
            'public_allowed',
            'canonical_key',
            'alternate_questions',
            'roles',
            'primary_question',
            'field_id',
        ];

        $withContext = ['listing' => ['bedrooms' => 2], 'faq_answers' => []];

        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $type) {
            foreach ([[], $withContext] as $ctx) {
                $results = $this->service->forListing($type, $ctx);
                foreach ($results as $i => $item) {
                    foreach ($internalKeys as $key) {
                        $this->assertArrayNotHasKey(
                            $key,
                            $item,
                            "[$type][$i] internal registry key '$key' must not appear in chip output"
                        );
                    }
                }
            }
        }
    }

    /**
     * (h) The six required output keys must all be present on every chip:
     *     label, question, question_type, category, category_label, category_icon.
     */
    public function test_all_six_public_chip_keys_are_present(): void
    {
        $requiredKeys = ['label', 'question', 'question_type', 'category', 'category_label', 'category_icon'];

        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $type) {
            $results = $this->service->forListing($type);
            foreach ($results as $i => $item) {
                foreach ($requiredKeys as $key) {
                    $this->assertArrayHasKey(
                        $key,
                        $item,
                        "[$type][$i] chip is missing required key '$key'"
                    );
                    $this->assertIsString($item[$key], "[$type][$i].$key must be a string");
                    $this->assertNotEmpty($item[$key],  "[$type][$i].$key must not be empty");
                }
            }
        }
    }

    /**
     * (i) forListing() must not reference the deprecated POOLS constant.
     *     Verified by ensuring the registry-generated output matches questions
     *     that exist in suggestedQuestionRegistry() and not in the empty POOLS stub.
     */
    public function test_for_listing_is_driven_by_registry_not_pools(): void
    {
        $registry = \App\Services\AskAi\AskAiFieldQuestionRegistryService::suggestedQuestionRegistry();
        $registryQuestions = array_column(array_values($registry), 'primary_question');

        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $type) {
            $results = $this->service->forListing($type);
            foreach ($results as $i => $item) {
                $this->assertContains(
                    $item['question'],
                    $registryQuestions,
                    "[$type][$i] chip question '{$item['question']}' was not found in suggestedQuestionRegistry(); forListing() may be using legacy POOLS"
                );
            }
        }
    }

    /**
     * (j) Guest vs authenticated chip counts: unauthenticated viewers must see
     *     fewer or equal chips compared to authenticated viewers (auth guard removes chips,
     *     never adds them).
     */
    public function test_guest_chip_count_never_exceeds_authenticated_count(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $type) {
            $authCount  = count($this->service->forListing($type, [], true));
            $guestCount = count($this->service->forListing($type, [], false));
            $this->assertLessThanOrEqual(
                $authCount,
                $guestCount,
                "$type: unauthenticated viewers must not receive more chips than authenticated viewers"
            );
        }
    }

    /**
     * (k) suggestedQuestionRegistry() entries must not contain any phantom source_path
     *     values — i.e. all requires_data=true entries must reference a known path prefix
     *     (listing.* or faq_answers.*). Entries with requires_data=false may have null source_path.
     */
    public function test_registry_source_paths_are_well_formed(): void
    {
        $registry = \App\Services\AskAi\AskAiFieldQuestionRegistryService::suggestedQuestionRegistry();

        foreach ($registry as $fieldId => $entry) {
            if ($entry['requires_data']) {
                $this->assertNotNull(
                    $entry['source_path'],
                    "Registry entry '$fieldId': requires_data=true but source_path is null"
                );
                $this->assertTrue(
                    str_starts_with($entry['source_path'], 'listing.') ||
                    str_starts_with($entry['source_path'], 'faq_answers.'),
                    "Registry entry '$fieldId': source_path '{$entry['source_path']}' must start with 'listing.' or 'faq_answers.'"
                );
            } else {
                $this->assertNull(
                    $entry['source_path'],
                    "Registry entry '$fieldId': requires_data=false should have null source_path (static chip needs no data source)"
                );
            }
        }
    }

    // =========================================================================
    // Requirement (b): Empty / blank listing field values must suppress chips
    // =========================================================================

    /**
     * An empty string in a listing context field must be treated as missing data
     * and suppress the corresponding requires_data=true chip, the same as null.
     *
     * We provide only the asking_price key (with an empty string value) so the
     * only listing_facts chip that COULD appear is the asking_price one. With an
     * empty string it must be suppressed, yielding zero listing_facts chips.
     */
    public function test_empty_string_listing_value_suppresses_chip(): void
    {
        $context = [
            'listing'     => ['asking_price' => ''],
            'faq_answers' => [],
        ];

        $results   = $this->service->forListing('seller', $context);
        $factChips = array_values(array_filter($results, fn($c) => $c['question_type'] === 'listing_facts'));

        $this->assertCount(
            0,
            $factChips,
            'seller: asking_price chip must be suppressed when value is an empty string, yielding no listing_facts chips'
        );
    }

    /**
     * A whitespace-only string in a listing context field must be treated as missing
     * data and suppress the chip, the same as an empty string.
     */
    public function test_whitespace_only_listing_value_suppresses_chip(): void
    {
        $context = [
            'listing'     => ['asking_price' => '   '],
            'faq_answers' => [],
        ];

        $results   = $this->service->forListing('seller', $context);
        $factChips = array_values(array_filter($results, fn($c) => $c['question_type'] === 'listing_facts'));

        $this->assertCount(
            0,
            $factChips,
            'seller: asking_price chip must be suppressed when value is whitespace-only, yielding no listing_facts chips'
        );
    }

    /**
     * An empty array in a listing context field must also suppress the chip.
     */
    public function test_empty_array_listing_value_suppresses_chip(): void
    {
        $context = [
            'listing'     => ['asking_price' => []],
            'faq_answers' => [],
        ];

        $results   = $this->service->forListing('seller', $context);
        $factChips = array_values(array_filter($results, fn($c) => $c['question_type'] === 'listing_facts'));

        $this->assertCount(
            0,
            $factChips,
            'seller: asking_price chip must be suppressed when value is an empty array, yielding no listing_facts chips'
        );
    }

    /**
     * Falsy-but-intentional values (integer 0, boolean false, string "0") must NOT
     * suppress a chip — they represent real data, just with a falsy value.
     */
    public function test_falsy_integer_zero_listing_value_does_not_suppress_chip(): void
    {
        // bedrooms = 0 is a valid datum (a studio with no separate bedroom)
        $context = [
            'listing'     => ['bedrooms' => 0],
            'faq_answers' => [],
        ];

        $results   = $this->service->forListing('seller', $context);
        $factChips = array_filter($results, fn($c) => $c['question_type'] === 'listing_facts');

        $bedroomChips = array_filter($factChips, fn($c) => stripos($c['question'], 'bedroom') !== false);
        $this->assertNotEmpty(
            $bedroomChips,
            'seller: bedrooms chip must appear when bedrooms = 0 (intentional falsy datum)'
        );
    }

    // =========================================================================
    // Requirement: Phantom / removed canonical keys must not produce chips
    // =========================================================================

    /**
     * Every non-null canonical_key in suggestedQuestionRegistry() must exist in
     * its respective primary registry:
     *   - listing.* keys  → must be in listingFieldRegistry()
     *   - faq_answers.* keys → must be in registry() (FAQ registry)
     *
     * This catches phantom keys at the registry definition level, before forListing()
     * is called. Any key failing this check would be silently suppressed at runtime.
     */
    public function test_all_canonical_keys_exist_in_their_primary_registries(): void
    {
        $suggestedRegistry = \App\Services\AskAi\AskAiFieldQuestionRegistryService::suggestedQuestionRegistry();
        $validListingKeys  = array_flip(array_keys(\App\Services\AskAi\AskAiFieldQuestionRegistryService::listingFieldRegistry()));
        $validFaqKeys      = array_flip(array_keys(\App\Services\AskAi\AskAiFieldQuestionRegistryService::registry()));

        foreach ($suggestedRegistry as $fieldId => $entry) {
            $ck = $entry['canonical_key'] ?? null;
            if ($ck === null) {
                continue; // static chip — no canonical key required
            }
            if (str_starts_with($ck, 'listing.')) {
                $this->assertArrayHasKey(
                    $ck,
                    $validListingKeys,
                    "Registry entry '$fieldId': listing canonical_key '$ck' is not in listingFieldRegistry(). Add it there or remove it from suggestedQuestionRegistry()."
                );
            } elseif (str_starts_with($ck, 'faq_answers.')) {
                $this->assertArrayHasKey(
                    $ck,
                    $validFaqKeys,
                    "Registry entry '$fieldId': faq_answers canonical_key '$ck' is not in registry(). This is a phantom FAQ key and would be silently suppressed."
                );
            } else {
                $this->fail(
                    "Registry entry '$fieldId': canonical_key '$ck' has an unknown prefix — only 'listing.' and 'faq_answers.' are supported."
                );
            }
        }
    }

    /**
     * forListing() output must never include chips with phantom canonical_keys —
     * i.e. keys absent from their respective primary registry must be silently
     * suppressed before they reach the output.
     *
     * Both listing.* and faq_answers.* keys are verified here, consistent with
     * the full phantom-key guard now implemented in forListing().
     */
    public function test_forListing_output_never_contains_phantom_key_questions(): void
    {
        $suggestedRegistry = \App\Services\AskAi\AskAiFieldQuestionRegistryService::suggestedQuestionRegistry();
        $validListingKeys  = array_flip(array_keys(\App\Services\AskAi\AskAiFieldQuestionRegistryService::listingFieldRegistry()));
        $validFaqKeys      = array_flip(array_keys(\App\Services\AskAi\AskAiFieldQuestionRegistryService::registry()));

        // Build a question → canonical_key lookup from the full suggested registry
        $questionToCanonical = [];
        foreach ($suggestedRegistry as $entry) {
            $ck = $entry['canonical_key'] ?? null;
            if ($ck !== null) {
                $questionToCanonical[$entry['primary_question']] = $ck;
            }
        }

        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $type) {
            $chips = $this->service->forListing($type);
            foreach ($chips as $i => $chip) {
                $ck = $questionToCanonical[$chip['question']] ?? null;
                if ($ck === null) {
                    continue; // static chip — no canonical key to check
                }
                if (str_starts_with($ck, 'listing.')) {
                    $this->assertArrayHasKey(
                        $ck,
                        $validListingKeys,
                        "[$type][$i] chip '{$chip['question']}' has listing canonical_key '$ck' not in listingFieldRegistry() — phantom listing key leaked into output"
                    );
                } elseif (str_starts_with($ck, 'faq_answers.')) {
                    $this->assertArrayHasKey(
                        $ck,
                        $validFaqKeys,
                        "[$type][$i] chip '{$chip['question']}' has faq_answers canonical_key '$ck' not in registry() — phantom FAQ key leaked into output"
                    );
                }
            }
        }
    }
}
