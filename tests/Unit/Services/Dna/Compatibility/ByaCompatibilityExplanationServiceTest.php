<?php

namespace Tests\Unit\Services\Dna\Compatibility;

use App\Services\Dna\Compatibility\ByaCompatibilityExplanationService;
use PHPUnit\Framework\TestCase;

/**
 * ByaCompatibilityExplanationServiceTest
 *
 * Verifies the BYA_EXPLAIN_V1 explanation key layer against in-memory
 * BYA_ALIGN_V1 stubs. No database connection is required — all test data is
 * fabricated inline.
 *
 * Each test asserts one or more of:
 *   (a) Payload shape — explanation_version, alignment_version, dimensions, summary
 *       are always present in every output
 *   (b) Explanation type mapping — each of the six alignment categories (plus unknown)
 *       maps to the correct explanation type via CATEGORY_TYPE_MAP
 *   (c) Explanation key construction — key is always {dimension_name}_{alignment_category}
 *   (d) Summary count accuracy — total_dimensions, explained_dimensions, and
 *       explanation_type_counts are correct for all inputs
 *   (e) Stub payload — malformed/null/missing-dimensions input always returns
 *       a structurally complete, all-zero stub payload
 */
class ByaCompatibilityExplanationServiceTest extends TestCase
{
    private ByaCompatibilityExplanationService $service;

    private const EXPECTED_EXPLANATION_TYPES = [
        'alignment',
        'difference',
        'adjacent',
        'neutral',
        'insufficient_data',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ByaCompatibilityExplanationService();
    }

    // -------------------------------------------------------------------------
    // Helpers: build BYA_ALIGN_V1 payload stubs
    // -------------------------------------------------------------------------

    /**
     * Build a minimal BYA_ALIGN_V1 dimension entry.
     */
    private function makeDimensionEntry(string $alignmentCategory, ?string $relationship = 'same'): array
    {
        return [
            'relationship'       => $relationship,
            'alignment_category' => $alignmentCategory,
            'consumer'           => 'val_a',
            'agent'              => 'val_b',
        ];
    }

    /**
     * Build a BYA_ALIGN_V1 payload with a single named dimension.
     */
    private function makeSingleDimensionPayload(string $dimensionName, string $alignmentCategory, ?string $relationship = 'same'): array
    {
        return [
            'alignment_version'  => 'BYA_ALIGN_V1',
            'comparison_version' => 'BYA_COMP_V1',
            'dimensions'         => [
                $dimensionName => $this->makeDimensionEntry($alignmentCategory, $relationship),
            ],
            'summary'            => [],
        ];
    }

    /**
     * Build a BYA_ALIGN_V1 payload with multiple dimensions, each with its own category.
     * Keys are dimension names, values are alignment_category strings.
     */
    private function makeMixedAlignPayload(array $dimensionCategories): array
    {
        $dimensions = [];
        foreach ($dimensionCategories as $dim => $category) {
            $dimensions[$dim] = $this->makeDimensionEntry($category);
        }

        return [
            'alignment_version' => 'BYA_ALIGN_V1',
            'dimensions'        => $dimensions,
        ];
    }

    // -------------------------------------------------------------------------
    // (A) Payload shape — top-level keys always present
    // -------------------------------------------------------------------------

    /** @test */
    public function it_always_returns_explanation_version_BYA_EXPLAIN_V1(): void
    {
        $payload = $this->makeSingleDimensionPayload('communication_style', 'full_alignment');

        $result = $this->service->explain($payload);

        $this->assertArrayHasKey('explanation_version', $result);
        $this->assertSame('BYA_EXPLAIN_V1', $result['explanation_version']);
    }

    /** @test */
    public function it_forwards_alignment_version_from_input_payload(): void
    {
        $payload = $this->makeSingleDimensionPayload('risk_tolerance', 'partial_alignment');

        $result = $this->service->explain($payload);

        $this->assertArrayHasKey('alignment_version', $result);
        $this->assertSame('BYA_ALIGN_V1', $result['alignment_version']);
    }

    /** @test */
    public function it_returns_null_alignment_version_when_absent_from_input(): void
    {
        $payload = [
            'dimensions' => [
                'negotiation_style' => $this->makeDimensionEntry('full_alignment'),
            ],
        ];

        $result = $this->service->explain($payload);

        $this->assertNull($result['alignment_version']);
    }

    /** @test */
    public function it_always_returns_dimensions_and_summary_keys(): void
    {
        $payload = $this->makeSingleDimensionPayload('communication_style', 'full_alignment');

        $result = $this->service->explain($payload);

        $this->assertArrayHasKey('dimensions', $result);
        $this->assertArrayHasKey('summary',    $result);
        $this->assertIsArray($result['dimensions']);
        $this->assertIsArray($result['summary']);
    }

    /** @test */
    public function each_dimension_entry_contains_required_keys(): void
    {
        $payload = $this->makeMixedAlignPayload([
            'communication_style'     => 'full_alignment',
            'risk_tolerance'          => 'partial_alignment',
            'negotiation_style'       => 'incompatible_alignment',
            'technology_preference'   => 'insufficient_data',
            'advisor_expectation'     => 'adjacent_compatibility',
            'availability_expectation'=> 'neutral_compatibility',
        ]);

        $result = $this->service->explain($payload);

        foreach ($result['dimensions'] as $dim => $entry) {
            $this->assertArrayHasKey('relationship',       $entry, "{$dim} missing relationship");
            $this->assertArrayHasKey('alignment_category', $entry, "{$dim} missing alignment_category");
            $this->assertArrayHasKey('explanation_type',   $entry, "{$dim} missing explanation_type");
            $this->assertArrayHasKey('explanation_key',    $entry, "{$dim} missing explanation_key");
        }
    }

    /** @test */
    public function summary_contains_all_required_keys(): void
    {
        $payload = $this->makeSingleDimensionPayload('communication_style', 'full_alignment');

        $result  = $this->service->explain($payload);
        $summary = $result['summary'];

        $this->assertArrayHasKey('total_dimensions',       $summary);
        $this->assertArrayHasKey('explained_dimensions',   $summary);
        $this->assertArrayHasKey('explanation_type_counts', $summary);
        $this->assertIsArray($summary['explanation_type_counts']);
    }

    /** @test */
    public function summary_explanation_type_counts_contains_all_five_type_keys(): void
    {
        $payload = $this->makeSingleDimensionPayload('communication_style', 'full_alignment');

        $result = $this->service->explain($payload);
        $counts = $result['summary']['explanation_type_counts'];

        foreach (self::EXPECTED_EXPLANATION_TYPES as $type) {
            $this->assertArrayHasKey($type, $counts, "explanation_type_counts missing key: {$type}");
        }
    }

    // -------------------------------------------------------------------------
    // (B) Alignment category → explanation type mapping
    // -------------------------------------------------------------------------

    /** @test */
    public function full_alignment_category_maps_to_alignment_type(): void
    {
        $payload = $this->makeSingleDimensionPayload('communication_style', 'full_alignment');

        $result = $this->service->explain($payload);

        $this->assertSame('alignment', $result['dimensions']['communication_style']['explanation_type']);
    }

    /** @test */
    public function partial_alignment_category_maps_to_alignment_type(): void
    {
        $payload = $this->makeSingleDimensionPayload('risk_tolerance', 'partial_alignment');

        $result = $this->service->explain($payload);

        $this->assertSame('alignment', $result['dimensions']['risk_tolerance']['explanation_type']);
    }

    /** @test */
    public function incompatible_alignment_category_maps_to_difference_type(): void
    {
        $payload = $this->makeSingleDimensionPayload('negotiation_style', 'incompatible_alignment');

        $result = $this->service->explain($payload);

        $this->assertSame('difference', $result['dimensions']['negotiation_style']['explanation_type']);
    }

    /** @test */
    public function insufficient_data_category_maps_to_insufficient_data_type(): void
    {
        $payload = $this->makeSingleDimensionPayload('technology_preference', 'insufficient_data');

        $result = $this->service->explain($payload);

        $this->assertSame('insufficient_data', $result['dimensions']['technology_preference']['explanation_type']);
    }

    /** @test */
    public function adjacent_compatibility_category_maps_to_adjacent_type(): void
    {
        $payload = $this->makeSingleDimensionPayload('advisor_expectation', 'adjacent_compatibility');

        $result = $this->service->explain($payload);

        $this->assertSame('adjacent', $result['dimensions']['advisor_expectation']['explanation_type']);
    }

    /** @test */
    public function neutral_compatibility_category_maps_to_neutral_type(): void
    {
        $payload = $this->makeSingleDimensionPayload('availability_expectation', 'neutral_compatibility');

        $result = $this->service->explain($payload);

        $this->assertSame('neutral', $result['dimensions']['availability_expectation']['explanation_type']);
    }

    /** @test */
    public function unknown_unrecognized_category_falls_back_to_insufficient_data_type(): void
    {
        $payload = $this->makeSingleDimensionPayload('decision_speed', 'future_unknown_category');

        $result = $this->service->explain($payload);

        $this->assertSame('insufficient_data', $result['dimensions']['decision_speed']['explanation_type']);
    }

    // -------------------------------------------------------------------------
    // (C) Explanation key construction
    // -------------------------------------------------------------------------

    /** @test */
    public function explanation_key_is_dimension_name_underscore_alignment_category(): void
    {
        $payload = $this->makeSingleDimensionPayload('communication_style', 'partial_alignment');

        $result = $this->service->explain($payload);

        $this->assertSame(
            'communication_style_partial_alignment',
            $result['dimensions']['communication_style']['explanation_key']
        );
    }

    /** @test */
    public function explanation_key_uses_actual_alignment_category_not_explanation_type(): void
    {
        $payload = $this->makeSingleDimensionPayload('risk_tolerance', 'incompatible_alignment');

        $result = $this->service->explain($payload);

        $this->assertSame(
            'risk_tolerance_incompatible_alignment',
            $result['dimensions']['risk_tolerance']['explanation_key']
        );
    }

    /** @test */
    public function explanation_key_for_unknown_category_uses_raw_category_string(): void
    {
        $payload = $this->makeSingleDimensionPayload('decision_speed', 'future_unknown_category');

        $result = $this->service->explain($payload);

        $this->assertSame(
            'decision_speed_future_unknown_category',
            $result['dimensions']['decision_speed']['explanation_key']
        );
    }

    /** @test */
    public function explanation_keys_are_correct_for_all_six_known_alignment_categories(): void
    {
        $categoryExpectedKeys = [
            'full_alignment'         => 'dim_full_alignment',
            'partial_alignment'      => 'dim_partial_alignment',
            'incompatible_alignment' => 'dim_incompatible_alignment',
            'insufficient_data'      => 'dim_insufficient_data',
            'adjacent_compatibility' => 'dim_adjacent_compatibility',
            'neutral_compatibility'  => 'dim_neutral_compatibility',
        ];

        foreach ($categoryExpectedKeys as $category => $expectedKey) {
            $payload = $this->makeSingleDimensionPayload('dim', $category);
            $result  = $this->service->explain($payload);

            $this->assertSame(
                $expectedKey,
                $result['dimensions']['dim']['explanation_key'],
                "Explanation key mismatch for category: {$category}"
            );
        }
    }

    // -------------------------------------------------------------------------
    // (D) Passthrough fields: relationship and alignment_category
    // -------------------------------------------------------------------------

    /** @test */
    public function relationship_is_passed_through_unchanged(): void
    {
        $payload = [
            'alignment_version' => 'BYA_ALIGN_V1',
            'dimensions'        => [
                'negotiation_style' => [
                    'relationship'       => 'different',
                    'alignment_category' => 'incompatible_alignment',
                    'consumer'           => 'collaborative',
                    'agent'              => 'competitive',
                ],
            ],
        ];

        $result = $this->service->explain($payload);

        $this->assertSame('different', $result['dimensions']['negotiation_style']['relationship']);
    }

    /** @test */
    public function alignment_category_is_passed_through_unchanged(): void
    {
        $payload = $this->makeSingleDimensionPayload('risk_tolerance', 'partial_alignment');

        $result = $this->service->explain($payload);

        $this->assertSame('partial_alignment', $result['dimensions']['risk_tolerance']['alignment_category']);
    }

    /** @test */
    public function null_relationship_is_passed_through_as_null(): void
    {
        $payload = [
            'alignment_version' => 'BYA_ALIGN_V1',
            'dimensions'        => [
                'technology_preference' => [
                    'relationship'       => null,
                    'alignment_category' => 'insufficient_data',
                ],
            ],
        ];

        $result = $this->service->explain($payload);

        $this->assertNull($result['dimensions']['technology_preference']['relationship']);
    }

    // -------------------------------------------------------------------------
    // (E) Summary count accuracy
    // -------------------------------------------------------------------------

    /** @test */
    public function total_dimensions_count_is_correct(): void
    {
        $payload = $this->makeMixedAlignPayload([
            'd1' => 'full_alignment',
            'd2' => 'partial_alignment',
            'd3' => 'incompatible_alignment',
        ]);

        $result = $this->service->explain($payload);

        $this->assertSame(3, $result['summary']['total_dimensions']);
    }

    /** @test */
    public function explained_dimensions_excludes_insufficient_data_dimensions(): void
    {
        $payload = $this->makeMixedAlignPayload([
            'd1' => 'full_alignment',
            'd2' => 'partial_alignment',
            'd3' => 'insufficient_data',
            'd4' => 'insufficient_data',
        ]);

        $result = $this->service->explain($payload);

        $this->assertSame(4, $result['summary']['total_dimensions']);
        $this->assertSame(2, $result['summary']['explained_dimensions']);
    }

    /** @test */
    public function explained_dimensions_counts_all_non_insufficient_data_categories(): void
    {
        $payload = $this->makeMixedAlignPayload([
            'd1' => 'full_alignment',
            'd2' => 'partial_alignment',
            'd3' => 'incompatible_alignment',
            'd4' => 'adjacent_compatibility',
            'd5' => 'neutral_compatibility',
            'd6' => 'insufficient_data',
        ]);

        $result = $this->service->explain($payload);

        $this->assertSame(6, $result['summary']['total_dimensions']);
        $this->assertSame(5, $result['summary']['explained_dimensions']);
    }

    /** @test */
    public function explanation_type_counts_are_correct_for_mixed_input(): void
    {
        $payload = $this->makeMixedAlignPayload([
            'd1' => 'full_alignment',
            'd2' => 'partial_alignment',
            'd3' => 'incompatible_alignment',
            'd4' => 'insufficient_data',
            'd5' => 'adjacent_compatibility',
            'd6' => 'neutral_compatibility',
        ]);

        $result = $this->service->explain($payload);
        $counts = $result['summary']['explanation_type_counts'];

        $this->assertSame(2, $counts['alignment']);
        $this->assertSame(1, $counts['difference']);
        $this->assertSame(1, $counts['adjacent']);
        $this->assertSame(1, $counts['neutral']);
        $this->assertSame(1, $counts['insufficient_data']);
    }

    /** @test */
    public function all_full_alignment_input_produces_correct_counts(): void
    {
        $payload = $this->makeMixedAlignPayload([
            'd1' => 'full_alignment',
            'd2' => 'full_alignment',
            'd3' => 'full_alignment',
        ]);

        $result = $this->service->explain($payload);
        $counts = $result['summary']['explanation_type_counts'];

        $this->assertSame(3, $counts['alignment']);
        $this->assertSame(0, $counts['difference']);
        $this->assertSame(0, $counts['adjacent']);
        $this->assertSame(0, $counts['neutral']);
        $this->assertSame(0, $counts['insufficient_data']);
        $this->assertSame(3, $result['summary']['total_dimensions']);
        $this->assertSame(3, $result['summary']['explained_dimensions']);
    }

    /** @test */
    public function all_insufficient_data_input_produces_zero_explained_dimensions(): void
    {
        $payload = $this->makeMixedAlignPayload([
            'd1' => 'insufficient_data',
            'd2' => 'insufficient_data',
        ]);

        $result = $this->service->explain($payload);

        $this->assertSame(2, $result['summary']['total_dimensions']);
        $this->assertSame(0, $result['summary']['explained_dimensions']);
        $this->assertSame(2, $result['summary']['explanation_type_counts']['insufficient_data']);
    }

    /** @test */
    public function empty_dimensions_array_produces_all_zero_summary(): void
    {
        $payload = [
            'alignment_version' => 'BYA_ALIGN_V1',
            'dimensions'        => [],
        ];

        $result  = $this->service->explain($payload);
        $summary = $result['summary'];

        $this->assertSame(0, $summary['total_dimensions']);
        $this->assertSame(0, $summary['explained_dimensions']);

        foreach (self::EXPECTED_EXPLANATION_TYPES as $type) {
            $this->assertSame(0, $summary['explanation_type_counts'][$type], "Type count for {$type} should be 0");
        }
    }

    // -------------------------------------------------------------------------
    // (F) Stub payload — malformed/null/missing-dimensions input
    // -------------------------------------------------------------------------

    /** @test */
    public function null_input_returns_stub_payload(): void
    {
        $result = $this->service->explain(null);

        $this->assertSame('BYA_EXPLAIN_V1', $result['explanation_version']);
        $this->assertSame([], $result['dimensions']);
        $this->assertSame(0,  $result['summary']['total_dimensions']);
        $this->assertSame(0,  $result['summary']['explained_dimensions']);
    }

    /** @test */
    public function empty_array_input_returns_stub_payload(): void
    {
        $result = $this->service->explain([]);

        $this->assertSame('BYA_EXPLAIN_V1', $result['explanation_version']);
        $this->assertSame([], $result['dimensions']);
    }

    /** @test */
    public function missing_dimensions_key_returns_stub_payload(): void
    {
        $payload = [
            'alignment_version' => 'BYA_ALIGN_V1',
            'summary'           => [],
        ];

        $result = $this->service->explain($payload);

        $this->assertSame('BYA_EXPLAIN_V1', $result['explanation_version']);
        $this->assertSame([], $result['dimensions']);
    }

    /** @test */
    public function non_array_dimensions_value_returns_stub_payload(): void
    {
        $payload = [
            'alignment_version' => 'BYA_ALIGN_V1',
            'dimensions'        => 'not_an_array',
        ];

        $result = $this->service->explain($payload);

        $this->assertSame('BYA_EXPLAIN_V1', $result['explanation_version']);
        $this->assertSame([], $result['dimensions']);
    }

    /** @test */
    public function stub_payload_forwards_alignment_version_when_present(): void
    {
        $payload = [
            'alignment_version' => 'BYA_ALIGN_V1',
        ];

        $result = $this->service->explain($payload);

        $this->assertSame('BYA_ALIGN_V1', $result['alignment_version']);
    }

    /** @test */
    public function stub_payload_has_null_alignment_version_when_absent(): void
    {
        $result = $this->service->explain(null);

        $this->assertNull($result['alignment_version']);
    }

    /** @test */
    public function stub_payload_has_structurally_complete_summary(): void
    {
        $result  = $this->service->explain(null);
        $summary = $result['summary'];

        $this->assertArrayHasKey('total_dimensions',        $summary);
        $this->assertArrayHasKey('explained_dimensions',    $summary);
        $this->assertArrayHasKey('explanation_type_counts', $summary);
        $this->assertSame(0, $summary['total_dimensions']);
        $this->assertSame(0, $summary['explained_dimensions']);

        foreach (self::EXPECTED_EXPLANATION_TYPES as $type) {
            $this->assertArrayHasKey($type, $summary['explanation_type_counts']);
            $this->assertSame(0, $summary['explanation_type_counts'][$type]);
        }
    }

    /** @test */
    public function string_input_returns_stub_payload(): void
    {
        $result = $this->service->explain('invalid_string_input');

        $this->assertSame('BYA_EXPLAIN_V1', $result['explanation_version']);
        $this->assertSame([], $result['dimensions']);
    }

    /** @test */
    public function integer_input_returns_stub_payload(): void
    {
        $result = $this->service->explain(42);

        $this->assertSame('BYA_EXPLAIN_V1', $result['explanation_version']);
        $this->assertSame([], $result['dimensions']);
    }

    // -------------------------------------------------------------------------
    // (G) Multi-dimension valid payload — all six categories exercised together
    // -------------------------------------------------------------------------

    /** @test */
    public function full_valid_payload_with_all_six_categories_produces_correct_output(): void
    {
        $payload = $this->makeMixedAlignPayload([
            'communication_style'        => 'full_alignment',
            'communication_frequency'    => 'partial_alignment',
            'decision_speed'             => 'incompatible_alignment',
            'technology_preference'      => 'insufficient_data',
            'market_education_preference'=> 'adjacent_compatibility',
            'availability_expectation'   => 'neutral_compatibility',
        ]);

        $result = $this->service->explain($payload);

        $this->assertSame('BYA_EXPLAIN_V1', $result['explanation_version']);
        $this->assertCount(6, $result['dimensions']);

        $this->assertSame('alignment',        $result['dimensions']['communication_style']['explanation_type']);
        $this->assertSame('alignment',        $result['dimensions']['communication_frequency']['explanation_type']);
        $this->assertSame('difference',       $result['dimensions']['decision_speed']['explanation_type']);
        $this->assertSame('insufficient_data',$result['dimensions']['technology_preference']['explanation_type']);
        $this->assertSame('adjacent',         $result['dimensions']['market_education_preference']['explanation_type']);
        $this->assertSame('neutral',          $result['dimensions']['availability_expectation']['explanation_type']);

        $this->assertSame('communication_style_full_alignment',               $result['dimensions']['communication_style']['explanation_key']);
        $this->assertSame('communication_frequency_partial_alignment',        $result['dimensions']['communication_frequency']['explanation_key']);
        $this->assertSame('decision_speed_incompatible_alignment',            $result['dimensions']['decision_speed']['explanation_key']);
        $this->assertSame('technology_preference_insufficient_data',          $result['dimensions']['technology_preference']['explanation_key']);
        $this->assertSame('market_education_preference_adjacent_compatibility',$result['dimensions']['market_education_preference']['explanation_key']);
        $this->assertSame('availability_expectation_neutral_compatibility',   $result['dimensions']['availability_expectation']['explanation_key']);

        $summary = $result['summary'];
        $this->assertSame(6, $summary['total_dimensions']);
        $this->assertSame(5, $summary['explained_dimensions']);
        $this->assertSame(2, $summary['explanation_type_counts']['alignment']);
        $this->assertSame(1, $summary['explanation_type_counts']['difference']);
        $this->assertSame(1, $summary['explanation_type_counts']['adjacent']);
        $this->assertSame(1, $summary['explanation_type_counts']['neutral']);
        $this->assertSame(1, $summary['explanation_type_counts']['insufficient_data']);
    }
}
