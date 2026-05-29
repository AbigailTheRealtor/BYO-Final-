<?php

namespace Tests\Unit\Services\Dna\Compatibility;

use App\Services\Dna\Compatibility\ByaCompatibilityNarrativeService;
use PHPUnit\Framework\TestCase;

/**
 * ByaCompatibilityNarrativeServiceTest
 *
 * Verifies the BYA_NARRATIVE_V1 deterministic template service against in-memory
 * BYA_EXPLAIN_V1 and BYA_ALIGN_V1 stubs. No database connection is required —
 * all test data is fabricated inline.
 *
 * Test coverage:
 *   (A) Payload shape     — narrative_version, dimensions, summary always present.
 *   (B) Determinism       — same input always produces identical output.
 *   (C) Template selection — each explanation_key selects the correct template.
 *   (D) Placeholder substitution — consumer_value and agent_value appear correctly.
 *   (E) Fallback behavior — unknown keys and adjacent/neutral types use insufficient_data.
 *   (F) Null value handling — missing consumer or agent value yields a valid sentence.
 *   (G) Insufficient data — all 12 dimensions produce correct insufficient_data sentences.
 *   (H) Summary generation — counts and summary sentence are correct for full payloads.
 *   (I) Stub payload      — invalid/null/missing-dimensions input returns structurally
 *                           complete stub, never throws.
 */
class ByaCompatibilityNarrativeServiceTest extends TestCase
{
    private ByaCompatibilityNarrativeService $service;

    private const ALL_NARRATIVE_TYPES = [
        'alignment',
        'difference',
        'adjacent',
        'neutral',
        'insufficient_data',
    ];

    private const CANONICAL_DIMENSIONS = [
        'communication_style',
        'communication_frequency',
        'decision_speed',
        'risk_tolerance',
        'negotiation_style',
        'advisor_expectation',
        'technology_preference',
        'market_education_preference',
        'property_search_involvement',
        'transaction_guidance_level',
        'availability_expectation',
        'personality_style',
    ];

    private const FULL_ALIGNMENT_DIMENSIONS = [
        'communication_style',
        'communication_frequency',
        'decision_speed',
        'risk_tolerance',
        'negotiation_style',
        'advisor_expectation',
        'property_search_involvement',
        'transaction_guidance_level',
        'availability_expectation',
        'personality_style',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ByaCompatibilityNarrativeService();
    }

    // -------------------------------------------------------------------------
    // Helpers: build BYA_EXPLAIN_V1 and BYA_ALIGN_V1 stubs
    // -------------------------------------------------------------------------

    private function makeExplainEntry(
        string  $dimensionName,
        string  $alignmentCategory,
        string  $explanationType,
        ?string $relationship = 'same'
    ): array {
        return [
            'relationship'       => $relationship,
            'alignment_category' => $alignmentCategory,
            'explanation_type'   => $explanationType,
            'explanation_key'    => $dimensionName . '_' . $alignmentCategory,
        ];
    }

    private function makeAlignEntry(mixed $consumer = 'consumer_val', mixed $agent = 'agent_val'): array
    {
        return [
            'relationship'       => 'same',
            'alignment_category' => 'full_alignment',
            'consumer'           => $consumer,
            'agent'              => $agent,
        ];
    }

    private function makeSingleDimensionPayloads(
        string  $dimensionName,
        string  $alignmentCategory,
        string  $explanationType,
        mixed   $consumerValue = 'consumer_val',
        mixed   $agentValue    = 'agent_val',
        ?string $relationship  = 'same'
    ): array {
        $explainPayload = [
            'explanation_version' => 'BYA_EXPLAIN_V1',
            'alignment_version'   => 'BYA_ALIGN_V1',
            'dimensions'          => [
                $dimensionName => $this->makeExplainEntry(
                    $dimensionName,
                    $alignmentCategory,
                    $explanationType,
                    $relationship
                ),
            ],
            'summary' => [],
        ];

        $alignPayload = [
            'alignment_version' => 'BYA_ALIGN_V1',
            'dimensions'        => [
                $dimensionName => $this->makeAlignEntry($consumerValue, $agentValue),
            ],
        ];

        return [$explainPayload, $alignPayload];
    }

    /**
     * Build payloads for all 12 canonical dimensions with the given category/type.
     */
    private function makeFullPayloads(
        string $alignmentCategory,
        string $explanationType,
        mixed  $consumerValue = 'consumer_val',
        mixed  $agentValue    = 'agent_val'
    ): array {
        $explainDimensions = [];
        $alignDimensions   = [];

        foreach (self::CANONICAL_DIMENSIONS as $dim) {
            $explainDimensions[$dim] = $this->makeExplainEntry(
                $dim,
                $alignmentCategory,
                $explanationType
            );
            $alignDimensions[$dim] = $this->makeAlignEntry($consumerValue, $agentValue);
        }

        $explainPayload = [
            'explanation_version' => 'BYA_EXPLAIN_V1',
            'alignment_version'   => 'BYA_ALIGN_V1',
            'dimensions'          => $explainDimensions,
            'summary'             => [],
        ];

        $alignPayload = [
            'alignment_version' => 'BYA_ALIGN_V1',
            'dimensions'        => $alignDimensions,
        ];

        return [$explainPayload, $alignPayload];
    }

    /**
     * Build a full 12-dimension payload with realistic mixed alignment categories.
     * 8 aligned + 2 incompatible + 2 insufficient_data.
     */
    private function makeMixed12DimensionPayloads(): array
    {
        $map = [
            'communication_style'        => ['full_alignment',          'alignment'],
            'communication_frequency'    => ['full_alignment',          'alignment'],
            'decision_speed'             => ['full_alignment',          'alignment'],
            'risk_tolerance'             => ['full_alignment',          'alignment'],
            'negotiation_style'          => ['full_alignment',          'alignment'],
            'advisor_expectation'        => ['full_alignment',          'alignment'],
            'technology_preference'      => ['insufficient_data',       'insufficient_data'],
            'market_education_preference'=> ['insufficient_data',       'insufficient_data'],
            'property_search_involvement'=> ['full_alignment',          'alignment'],
            'transaction_guidance_level' => ['full_alignment',          'alignment'],
            'availability_expectation'   => ['incompatible_alignment',  'difference'],
            'personality_style'          => ['incompatible_alignment',  'difference'],
        ];

        $explainDimensions = [];
        $alignDimensions   = [];

        foreach ($map as $dim => [$category, $type]) {
            $explainDimensions[$dim] = $this->makeExplainEntry($dim, $category, $type);
            $alignDimensions[$dim]   = $this->makeAlignEntry('consumer_a', 'agent_b');
        }

        $explainPayload = [
            'explanation_version' => 'BYA_EXPLAIN_V1',
            'alignment_version'   => 'BYA_ALIGN_V1',
            'dimensions'          => $explainDimensions,
            'summary'             => [],
        ];

        $alignPayload = [
            'alignment_version' => 'BYA_ALIGN_V1',
            'dimensions'        => $alignDimensions,
        ];

        return [$explainPayload, $alignPayload];
    }

    // -------------------------------------------------------------------------
    // (A) Payload shape — top-level keys always present
    // -------------------------------------------------------------------------

    /** @test */
    public function it_always_returns_narrative_version_BYA_NARRATIVE_V1(): void
    {
        [$explain, $align] = $this->makeSingleDimensionPayloads(
            'communication_style', 'full_alignment', 'alignment'
        );

        $result = $this->service->generate($explain, $align);

        $this->assertArrayHasKey('narrative_version', $result);
        $this->assertSame('BYA_NARRATIVE_V1', $result['narrative_version']);
    }

    /** @test */
    public function it_always_returns_dimensions_and_summary_keys(): void
    {
        [$explain, $align] = $this->makeSingleDimensionPayloads(
            'communication_style', 'full_alignment', 'alignment'
        );

        $result = $this->service->generate($explain, $align);

        $this->assertArrayHasKey('dimensions', $result);
        $this->assertArrayHasKey('summary',    $result);
        $this->assertIsArray($result['dimensions']);
        $this->assertIsArray($result['summary']);
    }

    /** @test */
    public function each_dimension_entry_contains_required_keys(): void
    {
        [$explain, $align] = $this->makeSingleDimensionPayloads(
            'communication_style', 'full_alignment', 'alignment'
        );

        $result = $this->service->generate($explain, $align);

        foreach ($result['dimensions'] as $dim => $entry) {
            $this->assertArrayHasKey('explanation_key', $entry, "{$dim} missing explanation_key");
            $this->assertArrayHasKey('narrative_type',  $entry, "{$dim} missing narrative_type");
            $this->assertArrayHasKey('template_id',     $entry, "{$dim} missing template_id");
            $this->assertArrayHasKey('sentence',        $entry, "{$dim} missing sentence");
        }
    }

    /** @test */
    public function summary_contains_all_required_keys(): void
    {
        [$explain, $align] = $this->makeSingleDimensionPayloads(
            'communication_style', 'full_alignment', 'alignment'
        );

        $result  = $this->service->generate($explain, $align);
        $summary = $result['summary'];

        $this->assertArrayHasKey('total_dimensions',      $summary);
        $this->assertArrayHasKey('narrative_type_counts', $summary);
        $this->assertArrayHasKey('summary_sentence',      $summary);
        $this->assertIsArray($summary['narrative_type_counts']);
        $this->assertIsString($summary['summary_sentence']);
    }

    /** @test */
    public function summary_narrative_type_counts_contains_all_five_type_keys(): void
    {
        [$explain, $align] = $this->makeSingleDimensionPayloads(
            'communication_style', 'full_alignment', 'alignment'
        );

        $result = $this->service->generate($explain, $align);
        $counts = $result['summary']['narrative_type_counts'];

        foreach (self::ALL_NARRATIVE_TYPES as $type) {
            $this->assertArrayHasKey($type, $counts, "narrative_type_counts missing key: {$type}");
        }
    }

    /** @test */
    public function it_forwards_explanation_version_from_explain_payload(): void
    {
        [$explain, $align] = $this->makeSingleDimensionPayloads(
            'risk_tolerance', 'full_alignment', 'alignment'
        );

        $result = $this->service->generate($explain, $align);

        $this->assertArrayHasKey('explanation_version', $result);
        $this->assertSame('BYA_EXPLAIN_V1', $result['explanation_version']);
    }

    /** @test */
    public function it_forwards_alignment_version_from_explain_payload(): void
    {
        [$explain, $align] = $this->makeSingleDimensionPayloads(
            'risk_tolerance', 'full_alignment', 'alignment'
        );

        $result = $this->service->generate($explain, $align);

        $this->assertArrayHasKey('alignment_version', $result);
        $this->assertSame('BYA_ALIGN_V1', $result['alignment_version']);
    }

    // -------------------------------------------------------------------------
    // (B) Determinism — same input always produces identical output
    // -------------------------------------------------------------------------

    /** @test */
    public function same_input_always_produces_identical_output_first_call(): void
    {
        [$explain, $align] = $this->makeSingleDimensionPayloads(
            'negotiation_style', 'full_alignment', 'alignment', 'collaborative', 'collaborative'
        );

        $resultA = $this->service->generate($explain, $align);
        $resultB = $this->service->generate($explain, $align);

        $this->assertSame($resultA, $resultB);
    }

    /** @test */
    public function same_input_always_produces_identical_output_for_incompatible(): void
    {
        [$explain, $align] = $this->makeSingleDimensionPayloads(
            'availability_expectation', 'incompatible_alignment', 'difference', 'within 1 hour', 'within 1 business day'
        );

        $resultA = $this->service->generate($explain, $align);
        $resultB = $this->service->generate($explain, $align);

        $this->assertSame($resultA, $resultB);
    }

    /** @test */
    public function same_input_always_produces_identical_output_for_full_12_dimension_payload(): void
    {
        [$explain, $align] = $this->makeMixed12DimensionPayloads();

        $resultA = $this->service->generate($explain, $align);
        $resultB = $this->service->generate($explain, $align);

        $this->assertSame($resultA, $resultB);
    }

    // -------------------------------------------------------------------------
    // (C) Template selection — each explanation_key selects the correct template
    // -------------------------------------------------------------------------

    /** @test */
    public function full_alignment_dimensions_select_full_alignment_templates(): void
    {
        foreach (self::FULL_ALIGNMENT_DIMENSIONS as $dim) {
            [$explain, $align] = $this->makeSingleDimensionPayloads(
                $dim, 'full_alignment', 'alignment', 'val_a', 'val_b'
            );

            $result = $this->service->generate($explain, $align);
            $entry  = $result['dimensions'][$dim];

            $this->assertSame(
                $dim . '_full_alignment',
                $entry['template_id'],
                "Expected template_id '{$dim}_full_alignment' for dimension {$dim}"
            );
            $this->assertSame('alignment', $entry['narrative_type']);
        }
    }

    /** @test */
    public function incompatible_alignment_dimensions_select_incompatible_templates(): void
    {
        foreach (self::FULL_ALIGNMENT_DIMENSIONS as $dim) {
            [$explain, $align] = $this->makeSingleDimensionPayloads(
                $dim, 'incompatible_alignment', 'difference', 'val_a', 'val_b'
            );

            $result = $this->service->generate($explain, $align);
            $entry  = $result['dimensions'][$dim];

            $this->assertSame(
                $dim . '_incompatible_alignment',
                $entry['template_id'],
                "Expected template_id '{$dim}_incompatible_alignment' for dimension {$dim}"
            );
            $this->assertSame('difference', $entry['narrative_type']);
        }
    }

    /** @test */
    public function insufficient_data_dimensions_select_insufficient_data_templates(): void
    {
        foreach (self::CANONICAL_DIMENSIONS as $dim) {
            [$explain, $align] = $this->makeSingleDimensionPayloads(
                $dim, 'insufficient_data', 'insufficient_data', null, null, null
            );

            $result = $this->service->generate($explain, $align);
            $entry  = $result['dimensions'][$dim];

            $this->assertSame(
                $dim . '_insufficient_data',
                $entry['template_id'],
                "Expected template_id '{$dim}_insufficient_data' for dimension {$dim}"
            );
            $this->assertSame('insufficient_data', $entry['narrative_type']);
        }
    }

    // -------------------------------------------------------------------------
    // (D) Placeholder substitution — consumer_value and agent_value appear in sentence
    // -------------------------------------------------------------------------

    /** @test */
    public function consumer_value_appears_in_full_alignment_sentence(): void
    {
        [$explain, $align] = $this->makeSingleDimensionPayloads(
            'communication_frequency', 'full_alignment', 'alignment', 'daily updates', 'proactive daily contact'
        );

        $result   = $this->service->generate($explain, $align);
        $sentence = $result['dimensions']['communication_frequency']['sentence'];

        $this->assertStringContainsString('daily updates', $sentence);
    }

    /** @test */
    public function agent_value_appears_in_full_alignment_sentence(): void
    {
        [$explain, $align] = $this->makeSingleDimensionPayloads(
            'communication_frequency', 'full_alignment', 'alignment', 'daily updates', 'proactive daily contact'
        );

        $result   = $this->service->generate($explain, $align);
        $sentence = $result['dimensions']['communication_frequency']['sentence'];

        $this->assertStringContainsString('proactive daily contact', $sentence);
    }

    /** @test */
    public function consumer_value_appears_in_incompatible_alignment_sentence(): void
    {
        [$explain, $align] = $this->makeSingleDimensionPayloads(
            'negotiation_style', 'incompatible_alignment', 'difference', 'collaborative', 'competitive'
        );

        $result   = $this->service->generate($explain, $align);
        $sentence = $result['dimensions']['negotiation_style']['sentence'];

        $this->assertStringContainsString('collaborative', $sentence);
    }

    /** @test */
    public function agent_value_appears_in_incompatible_alignment_sentence(): void
    {
        [$explain, $align] = $this->makeSingleDimensionPayloads(
            'negotiation_style', 'incompatible_alignment', 'difference', 'collaborative', 'competitive'
        );

        $result   = $this->service->generate($explain, $align);
        $sentence = $result['dimensions']['negotiation_style']['sentence'];

        $this->assertStringContainsString('competitive', $sentence);
    }

    /** @test */
    public function both_placeholder_values_appear_correctly_in_sentence(): void
    {
        [$explain, $align] = $this->makeSingleDimensionPayloads(
            'decision_speed', 'full_alignment', 'alignment', 'urgent pace', 'urgent timeline management'
        );

        $result   = $this->service->generate($explain, $align);
        $sentence = $result['dimensions']['decision_speed']['sentence'];

        $this->assertStringContainsString('urgent pace', $sentence);
        $this->assertStringContainsString('urgent timeline management', $sentence);
    }

    // -------------------------------------------------------------------------
    // (E) Fallback behavior — unknown keys and adjacent/neutral types
    // -------------------------------------------------------------------------

    /** @test */
    public function unknown_explanation_key_falls_back_to_insufficient_data_template(): void
    {
        $explainPayload = [
            'explanation_version' => 'BYA_EXPLAIN_V1',
            'alignment_version'   => 'BYA_ALIGN_V1',
            'dimensions'          => [
                'communication_style' => [
                    'relationship'       => 'same',
                    'alignment_category' => 'full_alignment',
                    'explanation_type'   => 'alignment',
                    'explanation_key'    => 'communication_style_totally_unknown_key',
                ],
            ],
            'summary' => [],
        ];

        $alignPayload = [
            'alignment_version' => 'BYA_ALIGN_V1',
            'dimensions'        => [
                'communication_style' => $this->makeAlignEntry('val_a', 'val_b'),
            ],
        ];

        $result = $this->service->generate($explainPayload, $alignPayload);
        $entry  = $result['dimensions']['communication_style'];

        $this->assertSame('communication_style_insufficient_data', $entry['template_id']);
        $this->assertIsString($entry['sentence']);
        $this->assertNotEmpty($entry['sentence']);
    }

    /** @test */
    public function unknown_explanation_key_does_not_throw(): void
    {
        $explainPayload = [
            'explanation_version' => 'BYA_EXPLAIN_V1',
            'alignment_version'   => 'BYA_ALIGN_V1',
            'dimensions'          => [
                'risk_tolerance' => [
                    'relationship'       => null,
                    'alignment_category' => 'future_unknown_category',
                    'explanation_type'   => 'alignment',
                    'explanation_key'    => 'risk_tolerance_future_unknown_category',
                ],
            ],
            'summary' => [],
        ];

        $alignPayload = [
            'alignment_version' => 'BYA_ALIGN_V1',
            'dimensions'        => [
                'risk_tolerance' => $this->makeAlignEntry(),
            ],
        ];

        $result = $this->service->generate($explainPayload, $alignPayload);

        $this->assertArrayHasKey('dimensions', $result);
        $this->assertArrayHasKey('risk_tolerance', $result['dimensions']);
        $this->assertIsString($result['dimensions']['risk_tolerance']['sentence']);
    }

    /** @test */
    public function adjacent_explanation_type_falls_back_to_insufficient_data_template(): void
    {
        $explainPayload = [
            'explanation_version' => 'BYA_EXPLAIN_V1',
            'alignment_version'   => 'BYA_ALIGN_V1',
            'dimensions'          => [
                'advisor_expectation' => [
                    'relationship'       => null,
                    'alignment_category' => 'adjacent_compatibility',
                    'explanation_type'   => 'adjacent',
                    'explanation_key'    => 'advisor_expectation_adjacent_compatibility',
                ],
            ],
            'summary' => [],
        ];

        $alignPayload = [
            'alignment_version' => 'BYA_ALIGN_V1',
            'dimensions'        => [
                'advisor_expectation' => $this->makeAlignEntry('high', 'moderate'),
            ],
        ];

        $result = $this->service->generate($explainPayload, $alignPayload);
        $entry  = $result['dimensions']['advisor_expectation'];

        $this->assertSame('adjacent', $entry['narrative_type']);
        $this->assertSame('advisor_expectation_insufficient_data', $entry['template_id']);
        $this->assertIsString($entry['sentence']);
        $this->assertNotEmpty($entry['sentence']);
    }

    /** @test */
    public function neutral_explanation_type_falls_back_to_insufficient_data_template(): void
    {
        $explainPayload = [
            'explanation_version' => 'BYA_EXPLAIN_V1',
            'alignment_version'   => 'BYA_ALIGN_V1',
            'dimensions'          => [
                'personality_style' => [
                    'relationship'       => null,
                    'alignment_category' => 'neutral_compatibility',
                    'explanation_type'   => 'neutral',
                    'explanation_key'    => 'personality_style_neutral_compatibility',
                ],
            ],
            'summary' => [],
        ];

        $alignPayload = [
            'alignment_version' => 'BYA_ALIGN_V1',
            'dimensions'        => [
                'personality_style' => $this->makeAlignEntry('val_a', 'val_b'),
            ],
        ];

        $result = $this->service->generate($explainPayload, $alignPayload);
        $entry  = $result['dimensions']['personality_style'];

        $this->assertSame('neutral', $entry['narrative_type']);
        $this->assertSame('personality_style_insufficient_data', $entry['template_id']);
        $this->assertIsString($entry['sentence']);
        $this->assertNotEmpty($entry['sentence']);
    }

    // -------------------------------------------------------------------------
    // (F) Null value handling — missing consumer or agent value
    // -------------------------------------------------------------------------

    /** @test */
    public function null_consumer_value_produces_structurally_valid_sentence(): void
    {
        [$explain, $align] = $this->makeSingleDimensionPayloads(
            'communication_frequency', 'full_alignment', 'alignment', null, 'agent_val'
        );

        $result = $this->service->generate($explain, $align);
        $entry  = $result['dimensions']['communication_frequency'];

        $this->assertArrayHasKey('sentence', $entry);
        $this->assertIsString($entry['sentence']);
        $this->assertNotEmpty($entry['sentence']);
    }

    /** @test */
    public function null_agent_value_produces_structurally_valid_sentence(): void
    {
        [$explain, $align] = $this->makeSingleDimensionPayloads(
            'negotiation_style', 'full_alignment', 'alignment', 'collaborative', null
        );

        $result = $this->service->generate($explain, $align);
        $entry  = $result['dimensions']['negotiation_style'];

        $this->assertArrayHasKey('sentence', $entry);
        $this->assertIsString($entry['sentence']);
        $this->assertNotEmpty($entry['sentence']);
    }

    /** @test */
    public function null_consumer_value_does_not_introduce_literal_placeholder_in_sentence(): void
    {
        [$explain, $align] = $this->makeSingleDimensionPayloads(
            'decision_speed', 'incompatible_alignment', 'difference', null, 'urgent'
        );

        $result   = $this->service->generate($explain, $align);
        $sentence = $result['dimensions']['decision_speed']['sentence'];

        $this->assertStringNotContainsString('{consumer_value}', $sentence);
        $this->assertStringNotContainsString('{agent_value}',    $sentence);
    }

    /** @test */
    public function null_agent_value_does_not_introduce_literal_placeholder_in_sentence(): void
    {
        [$explain, $align] = $this->makeSingleDimensionPayloads(
            'decision_speed', 'incompatible_alignment', 'difference', 'flexible', null
        );

        $result   = $this->service->generate($explain, $align);
        $sentence = $result['dimensions']['decision_speed']['sentence'];

        $this->assertStringNotContainsString('{consumer_value}', $sentence);
        $this->assertStringNotContainsString('{agent_value}',    $sentence);
    }

    /** @test */
    public function missing_align_dimensions_for_a_dimension_still_produces_valid_entry(): void
    {
        $explainPayload = [
            'explanation_version' => 'BYA_EXPLAIN_V1',
            'alignment_version'   => 'BYA_ALIGN_V1',
            'dimensions'          => [
                'risk_tolerance' => $this->makeExplainEntry('risk_tolerance', 'full_alignment', 'alignment'),
            ],
            'summary' => [],
        ];

        $alignPayload = [
            'alignment_version' => 'BYA_ALIGN_V1',
            'dimensions'        => [],
        ];

        $result = $this->service->generate($explainPayload, $alignPayload);
        $entry  = $result['dimensions']['risk_tolerance'];

        $this->assertArrayHasKey('sentence', $entry);
        $this->assertIsString($entry['sentence']);
        $this->assertNotEmpty($entry['sentence']);
        $this->assertStringNotContainsString('{consumer_value}', $entry['sentence']);
        $this->assertStringNotContainsString('{agent_value}',    $entry['sentence']);
    }

    // -------------------------------------------------------------------------
    // (G) Insufficient data — all 12 dimensions produce correct sentences
    // -------------------------------------------------------------------------

    /** @test */
    public function all_12_insufficient_data_dimensions_produce_non_empty_sentences(): void
    {
        foreach (self::CANONICAL_DIMENSIONS as $dim) {
            [$explain, $align] = $this->makeSingleDimensionPayloads(
                $dim, 'insufficient_data', 'insufficient_data', null, null, null
            );

            $result = $this->service->generate($explain, $align);
            $entry  = $result['dimensions'][$dim];

            $this->assertIsString($entry['sentence'], "{$dim} sentence must be a string");
            $this->assertNotEmpty($entry['sentence'],  "{$dim} sentence must not be empty");
            $this->assertSame($dim . '_insufficient_data', $entry['template_id'], "{$dim} must use its own insufficient_data template");
        }
    }

    /** @test */
    public function technology_preference_insufficient_data_sentence_does_not_speculate(): void
    {
        [$explain, $align] = $this->makeSingleDimensionPayloads(
            'technology_preference', 'insufficient_data', 'insufficient_data', null, null, null
        );

        $result   = $this->service->generate($explain, $align);
        $sentence = $result['dimensions']['technology_preference']['sentence'];

        $this->assertStringNotContainsString('{consumer_value}', $sentence);
        $this->assertStringNotContainsString('{agent_value}',    $sentence);
        $this->assertNotEmpty($sentence);
    }

    /** @test */
    public function market_education_preference_insufficient_data_sentence_does_not_speculate(): void
    {
        [$explain, $align] = $this->makeSingleDimensionPayloads(
            'market_education_preference', 'insufficient_data', 'insufficient_data', null, null, null
        );

        $result   = $this->service->generate($explain, $align);
        $sentence = $result['dimensions']['market_education_preference']['sentence'];

        $this->assertStringNotContainsString('{consumer_value}', $sentence);
        $this->assertStringNotContainsString('{agent_value}',    $sentence);
        $this->assertNotEmpty($sentence);
    }

    // -------------------------------------------------------------------------
    // (H) Summary generation — counts and summary sentence
    // -------------------------------------------------------------------------

    /** @test */
    public function summary_total_dimensions_is_correct(): void
    {
        [$explain, $align] = $this->makeMixed12DimensionPayloads();

        $result = $this->service->generate($explain, $align);

        $this->assertSame(12, $result['summary']['total_dimensions']);
    }

    /** @test */
    public function summary_narrative_type_counts_are_correct_for_mixed_12_dimension_payload(): void
    {
        [$explain, $align] = $this->makeMixed12DimensionPayloads();

        $result = $this->service->generate($explain, $align);
        $counts = $result['summary']['narrative_type_counts'];

        $this->assertSame(8, $counts['alignment']);
        $this->assertSame(2, $counts['difference']);
        $this->assertSame(0, $counts['adjacent']);
        $this->assertSame(0, $counts['neutral']);
        $this->assertSame(2, $counts['insufficient_data']);
    }

    /** @test */
    public function summary_sentence_is_non_empty_string(): void
    {
        [$explain, $align] = $this->makeMixed12DimensionPayloads();

        $result = $this->service->generate($explain, $align);

        $this->assertIsString($result['summary']['summary_sentence']);
        $this->assertNotEmpty($result['summary']['summary_sentence']);
    }

    /** @test */
    public function summary_sentence_contains_no_ranking_or_hire_language(): void
    {
        [$explain, $align] = $this->makeMixed12DimensionPayloads();

        $result   = $this->service->generate($explain, $align);
        $sentence = strtolower($result['summary']['summary_sentence']);

        $this->assertStringNotContainsString('you should hire',    $sentence);
        $this->assertStringNotContainsString('we recommend',       $sentence);
        $this->assertStringNotContainsString('best match',         $sentence);
        $this->assertStringNotContainsString('avoid',              $sentence);
        $this->assertStringNotContainsString('not a good fit',     $sentence);
        $this->assertStringNotContainsString('top agent',          $sentence);
        $this->assertStringNotContainsString('hiring decision',    $sentence);
        $this->assertStringNotContainsString('hire this agent',    $sentence);
        $this->assertStringNotContainsString('consider hiring',    $sentence);
    }

    /** @test */
    public function all_summary_template_variants_contain_no_hire_language(): void
    {
        $prohibitedPhrases = [
            'hiring decision',
            'you should hire',
            'we recommend',
            'consider hiring',
            'hire this agent',
            'best match',
            'top agent',
            'avoid',
            'not a good fit',
            'incompatible with your needs',
            'unlikely to meet',
            'most compatible',
            'ranks',
        ];

        $scenarios = [
            'all_alignment'   => $this->makeFullPayloads('full_alignment',         'alignment',        'val_a', 'val_b'),
            'all_difference'  => $this->makeFullPayloads('incompatible_alignment', 'difference',       'val_a', 'val_b'),
            'all_insufficient'=> $this->makeFullPayloads('insufficient_data',      'insufficient_data', null,   null),
        ];

        foreach ($scenarios as $scenarioName => [$explain, $align]) {
            $result   = $this->service->generate($explain, $align);
            $sentence = strtolower($result['summary']['summary_sentence']);

            foreach ($prohibitedPhrases as $phrase) {
                $this->assertStringNotContainsString(
                    $phrase,
                    $sentence,
                    "Summary sentence for scenario '{$scenarioName}' must not contain prohibited phrase: '{$phrase}'"
                );
            }
        }
    }

    /** @test */
    public function no_dimension_sentence_contains_hire_language(): void
    {
        $prohibitedPhrases = [
            'hiring decision',
            'you should hire',
            'we recommend',
            'consider hiring',
            'hire this agent',
            'best match',
            'top agent',
            'not a good fit',
            'incompatible with your needs',
            'unlikely to meet',
        ];

        [$explain, $align] = $this->makeMixed12DimensionPayloads();
        $result            = $this->service->generate($explain, $align);

        foreach ($result['dimensions'] as $dim => $entry) {
            $sentence = strtolower($entry['sentence']);
            foreach ($prohibitedPhrases as $phrase) {
                $this->assertStringNotContainsString(
                    $phrase,
                    $sentence,
                    "Dimension '{$dim}' sentence must not contain prohibited phrase: '{$phrase}'"
                );
            }
        }
    }

    /** @test */
    public function no_incompatible_alignment_sentence_contains_hire_language(): void
    {
        $prohibitedPhrases = [
            'hiring decision',
            'hire',
            'recommend',
            'disqualif',
            'avoid',
            'unsuitable',
        ];

        foreach (self::FULL_ALIGNMENT_DIMENSIONS as $dim) {
            [$explain, $align] = $this->makeSingleDimensionPayloads(
                $dim, 'incompatible_alignment', 'difference', 'val_a', 'val_b'
            );

            $result   = $this->service->generate($explain, $align);
            $sentence = strtolower($result['dimensions'][$dim]['sentence']);

            foreach ($prohibitedPhrases as $phrase) {
                $this->assertStringNotContainsString(
                    $phrase,
                    $sentence,
                    "Incompatible sentence for dimension '{$dim}' must not contain prohibited phrase: '{$phrase}'"
                );
            }
        }
    }

    /** @test */
    public function all_alignment_input_produces_alignment_dominant_summary_sentence(): void
    {
        [$explain, $align] = $this->makeFullPayloads('full_alignment', 'alignment', 'val_a', 'val_b');

        $result   = $this->service->generate($explain, $align);
        $sentence = $result['summary']['summary_sentence'];

        $this->assertIsString($sentence);
        $this->assertNotEmpty($sentence);
        $this->assertSame(12, $result['summary']['narrative_type_counts']['alignment']);
        $this->assertSame(0,  $result['summary']['narrative_type_counts']['difference']);
    }

    /** @test */
    public function all_difference_input_produces_difference_dominant_summary_sentence(): void
    {
        [$explain, $align] = $this->makeFullPayloads('incompatible_alignment', 'difference', 'val_a', 'val_b');

        $result   = $this->service->generate($explain, $align);
        $sentence = $result['summary']['summary_sentence'];

        $this->assertIsString($sentence);
        $this->assertNotEmpty($sentence);
        $this->assertSame(0,  $result['summary']['narrative_type_counts']['alignment']);
        $this->assertSame(12, $result['summary']['narrative_type_counts']['difference']);
    }

    /** @test */
    public function all_insufficient_data_input_produces_insufficient_summary_sentence(): void
    {
        [$explain, $align] = $this->makeFullPayloads('insufficient_data', 'insufficient_data', null, null);

        $result   = $this->service->generate($explain, $align);
        $sentence = $result['summary']['summary_sentence'];

        $this->assertIsString($sentence);
        $this->assertNotEmpty($sentence);
        $this->assertSame(0,  $result['summary']['narrative_type_counts']['alignment']);
        $this->assertSame(0,  $result['summary']['narrative_type_counts']['difference']);
        $this->assertSame(12, $result['summary']['narrative_type_counts']['insufficient_data']);
    }

    /** @test */
    public function empty_dimensions_produces_zero_total_and_no_dimensions_summary(): void
    {
        $explainPayload = [
            'explanation_version' => 'BYA_EXPLAIN_V1',
            'alignment_version'   => 'BYA_ALIGN_V1',
            'dimensions'          => [],
            'summary'             => [],
        ];

        $alignPayload = [
            'alignment_version' => 'BYA_ALIGN_V1',
            'dimensions'        => [],
        ];

        $result  = $this->service->generate($explainPayload, $alignPayload);
        $summary = $result['summary'];

        $this->assertSame(0, $summary['total_dimensions']);

        foreach (self::ALL_NARRATIVE_TYPES as $type) {
            $this->assertSame(0, $summary['narrative_type_counts'][$type], "Count for {$type} should be 0");
        }

        $this->assertIsString($summary['summary_sentence']);
        $this->assertNotEmpty($summary['summary_sentence']);
    }

    // -------------------------------------------------------------------------
    // (I) Stub payload — invalid/null/missing-dimensions input never throws
    // -------------------------------------------------------------------------

    /** @test */
    public function null_explain_input_returns_stub_payload(): void
    {
        $result = $this->service->generate(null, null);

        $this->assertSame('BYA_NARRATIVE_V1', $result['narrative_version']);
        $this->assertSame([], $result['dimensions']);
        $this->assertSame(0,  $result['summary']['total_dimensions']);
    }

    /** @test */
    public function empty_array_explain_input_returns_stub_payload(): void
    {
        $result = $this->service->generate([], []);

        $this->assertSame('BYA_NARRATIVE_V1', $result['narrative_version']);
        $this->assertSame([], $result['dimensions']);
    }

    /** @test */
    public function missing_dimensions_key_returns_stub_payload(): void
    {
        $explainPayload = [
            'explanation_version' => 'BYA_EXPLAIN_V1',
            'alignment_version'   => 'BYA_ALIGN_V1',
            'summary'             => [],
        ];

        $result = $this->service->generate($explainPayload, []);

        $this->assertSame('BYA_NARRATIVE_V1', $result['narrative_version']);
        $this->assertSame([], $result['dimensions']);
    }

    /** @test */
    public function non_array_dimensions_value_returns_stub_payload(): void
    {
        $explainPayload = [
            'explanation_version' => 'BYA_EXPLAIN_V1',
            'alignment_version'   => 'BYA_ALIGN_V1',
            'dimensions'          => 'not_an_array',
        ];

        $result = $this->service->generate($explainPayload, []);

        $this->assertSame('BYA_NARRATIVE_V1', $result['narrative_version']);
        $this->assertSame([], $result['dimensions']);
    }

    /** @test */
    public function string_explain_input_returns_stub_payload(): void
    {
        $result = $this->service->generate('invalid_string', []);

        $this->assertSame('BYA_NARRATIVE_V1', $result['narrative_version']);
        $this->assertSame([], $result['dimensions']);
    }

    /** @test */
    public function integer_explain_input_returns_stub_payload(): void
    {
        $result = $this->service->generate(42, null);

        $this->assertSame('BYA_NARRATIVE_V1', $result['narrative_version']);
        $this->assertSame([], $result['dimensions']);
    }

    /** @test */
    public function stub_payload_has_structurally_complete_summary(): void
    {
        $result  = $this->service->generate(null, null);
        $summary = $result['summary'];

        $this->assertArrayHasKey('total_dimensions',      $summary);
        $this->assertArrayHasKey('narrative_type_counts', $summary);
        $this->assertArrayHasKey('summary_sentence',      $summary);
        $this->assertSame(0, $summary['total_dimensions']);

        foreach (self::ALL_NARRATIVE_TYPES as $type) {
            $this->assertArrayHasKey($type, $summary['narrative_type_counts']);
            $this->assertSame(0, $summary['narrative_type_counts'][$type]);
        }

        $this->assertIsString($summary['summary_sentence']);
        $this->assertNotEmpty($summary['summary_sentence']);
    }

    /** @test */
    public function stub_payload_forwards_explanation_version_when_present(): void
    {
        $explainPayload = [
            'explanation_version' => 'BYA_EXPLAIN_V1',
            'alignment_version'   => 'BYA_ALIGN_V1',
        ];

        $result = $this->service->generate($explainPayload, null);

        $this->assertSame('BYA_EXPLAIN_V1', $result['explanation_version']);
        $this->assertSame('BYA_ALIGN_V1',   $result['alignment_version']);
    }

    /** @test */
    public function stub_payload_has_null_versions_when_absent(): void
    {
        $result = $this->service->generate(null, null);

        $this->assertNull($result['explanation_version']);
        $this->assertNull($result['alignment_version']);
    }

    /** @test */
    public function null_align_payload_still_produces_valid_narrative_with_empty_values(): void
    {
        [$explain, ] = $this->makeSingleDimensionPayloads(
            'communication_style', 'full_alignment', 'alignment'
        );

        $result = $this->service->generate($explain, null);

        $this->assertSame('BYA_NARRATIVE_V1', $result['narrative_version']);
        $this->assertArrayHasKey('communication_style', $result['dimensions']);

        $entry = $result['dimensions']['communication_style'];
        $this->assertIsString($entry['sentence']);
        $this->assertNotEmpty($entry['sentence']);
        $this->assertStringNotContainsString('{consumer_value}', $entry['sentence']);
        $this->assertStringNotContainsString('{agent_value}',    $entry['sentence']);
    }
}
