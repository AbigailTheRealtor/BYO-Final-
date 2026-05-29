<?php

namespace Tests\Unit\Services\Dna\Compatibility;

use App\Services\Dna\Compatibility\ByaCompatibilityAlignmentService;
use PHPUnit\Framework\TestCase;

/**
 * ByaCompatibilityAlignmentServiceTest
 *
 * Verifies the BYA_ALIGN_V1 alignment categorization layer against in-memory
 * BYA_COMP_V1 stubs. No database connection is required — all test data is
 * fabricated inline.
 *
 * Each test asserts one or more of:
 *   (a) Payload shape — alignment_version, comparison_version, dimensions, summary
 *       are always present in every output
 *   (b) Relationship-to-category mapping — same→full_alignment, similar→partial_alignment,
 *       different→incompatible_alignment, unknown→insufficient_data
 *   (c) Summary counts — each category count is correct given the dimensions input
 *   (d) Advisory label correctness — the four label rules fire in the correct priority order
 *   (e) Stub payload — invalid/empty input always returns a structurally valid payload
 *   (f) Reserved categories — adjacent_compatibility and neutral_compatibility are never
 *       emitted by Milestone 5; no test asserts that either is produced
 */
class ByaCompatibilityAlignmentServiceTest extends TestCase
{
    private ByaCompatibilityAlignmentService $service;

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

    private const EXPECTED_SUMMARY_KEYS = [
        'scored_dimensions',
        'full_alignment_count',
        'partial_alignment_count',
        'adjacent_compatibility_count',
        'neutral_compatibility_count',
        'incompatible_alignment_count',
        'insufficient_data_count',
        'advisory_label',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ByaCompatibilityAlignmentService();
    }

    // -------------------------------------------------------------------------
    // Helpers: build BYA_COMP_V1 payload stubs
    // -------------------------------------------------------------------------

    /**
     * Build a minimal BYA_COMP_V1 dimension entry.
     */
    private function makeDimensionEntry(string $relationship, mixed $consumer = 'a', mixed $agent = 'b'): array
    {
        return [
            'consumer'     => $consumer,
            'agent'        => $agent,
            'relationship' => $relationship,
        ];
    }

    /**
     * Build a BYA_COMP_V1 payload with all 12 canonical dimensions sharing the same relationship.
     */
    private function makeUniformCompPayload(string $relationship): array
    {
        $dimensions = [];
        foreach (self::CANONICAL_DIMENSIONS as $dim) {
            $dimensions[$dim] = $this->makeDimensionEntry($relationship);
        }

        return [
            'comparison_version'       => 'BYA_COMP_V1',
            'consumer_profile_version' => 'BYA_NORM_V1',
            'agent_profile_version'    => 'BYA_AGENT_NORM_V1',
            'dimensions'               => $dimensions,
        ];
    }

    /**
     * Build a BYA_COMP_V1 payload with a custom set of dimension relationships.
     * Keys are dimension names, values are relationship strings.
     */
    private function makeMixedCompPayload(array $dimensionRelationships): array
    {
        $dimensions = [];
        foreach ($dimensionRelationships as $dim => $relationship) {
            $dimensions[$dim] = $this->makeDimensionEntry($relationship);
        }

        return [
            'comparison_version' => 'BYA_COMP_V1',
            'dimensions'         => $dimensions,
        ];
    }

    // -------------------------------------------------------------------------
    // (A) Payload shape — top-level keys always present
    // -------------------------------------------------------------------------

    /** @test */
    public function it_always_returns_alignment_version_BYA_ALIGN_V1(): void
    {
        $payload = $this->makeUniformCompPayload('same');

        $result = $this->service->categorize($payload);

        $this->assertArrayHasKey('alignment_version', $result);
        $this->assertSame('BYA_ALIGN_V1', $result['alignment_version']);
    }

    /** @test */
    public function it_forwards_comparison_version_from_input_payload(): void
    {
        $payload = $this->makeUniformCompPayload('same');

        $result = $this->service->categorize($payload);

        $this->assertArrayHasKey('comparison_version', $result);
        $this->assertSame('BYA_COMP_V1', $result['comparison_version']);
    }

    /** @test */
    public function it_returns_null_comparison_version_when_absent_from_input(): void
    {
        $payload = [
            'dimensions' => [
                'negotiation_style' => $this->makeDimensionEntry('same'),
            ],
        ];

        $result = $this->service->categorize($payload);

        $this->assertNull($result['comparison_version']);
    }

    /** @test */
    public function it_always_returns_dimensions_and_summary_keys(): void
    {
        $payload = $this->makeUniformCompPayload('same');

        $result = $this->service->categorize($payload);

        $this->assertArrayHasKey('dimensions', $result);
        $this->assertArrayHasKey('summary',    $result);
        $this->assertIsArray($result['dimensions']);
        $this->assertIsArray($result['summary']);
    }

    /** @test */
    public function each_dimension_entry_contains_relationship_alignment_category_consumer_agent(): void
    {
        $payload = $this->makeUniformCompPayload('same');

        $result = $this->service->categorize($payload);

        foreach ($result['dimensions'] as $dim => $entry) {
            $this->assertArrayHasKey('relationship',       $entry, "{$dim} missing relationship");
            $this->assertArrayHasKey('alignment_category', $entry, "{$dim} missing alignment_category");
            $this->assertArrayHasKey('consumer',           $entry, "{$dim} missing consumer");
            $this->assertArrayHasKey('agent',              $entry, "{$dim} missing agent");
        }
    }

    /** @test */
    public function summary_contains_all_required_keys(): void
    {
        $payload = $this->makeUniformCompPayload('same');

        $result  = $this->service->categorize($payload);
        $summary = $result['summary'];

        foreach (self::EXPECTED_SUMMARY_KEYS as $key) {
            $this->assertArrayHasKey($key, $summary, "summary missing key: {$key}");
        }
    }

    // -------------------------------------------------------------------------
    // (B) Relationship-to-category mapping
    // -------------------------------------------------------------------------

    /** @test */
    public function same_relationship_maps_to_full_alignment(): void
    {
        $payload = $this->makeMixedCompPayload([
            'negotiation_style' => 'same',
        ]);

        $result = $this->service->categorize($payload);

        $this->assertSame('full_alignment', $result['dimensions']['negotiation_style']['alignment_category']);
    }

    /** @test */
    public function similar_relationship_maps_to_partial_alignment(): void
    {
        $payload = $this->makeMixedCompPayload([
            'risk_tolerance' => 'similar',
        ]);

        $result = $this->service->categorize($payload);

        $this->assertSame('partial_alignment', $result['dimensions']['risk_tolerance']['alignment_category']);
    }

    /** @test */
    public function different_relationship_maps_to_incompatible_alignment(): void
    {
        $payload = $this->makeMixedCompPayload([
            'communication_style' => 'different',
        ]);

        $result = $this->service->categorize($payload);

        $this->assertSame('incompatible_alignment', $result['dimensions']['communication_style']['alignment_category']);
    }

    /** @test */
    public function unknown_relationship_maps_to_insufficient_data(): void
    {
        $payload = $this->makeMixedCompPayload([
            'technology_preference' => 'unknown',
        ]);

        $result = $this->service->categorize($payload);

        $this->assertSame('insufficient_data', $result['dimensions']['technology_preference']['alignment_category']);
    }

    /** @test */
    public function unrecognized_relationship_value_falls_back_to_insufficient_data(): void
    {
        $payload = $this->makeMixedCompPayload([
            'decision_speed' => 'future_relationship_value',
        ]);

        $result = $this->service->categorize($payload);

        $this->assertSame('insufficient_data', $result['dimensions']['decision_speed']['alignment_category']);
    }

    /** @test */
    public function raw_relationship_string_is_preserved_in_output_dimension(): void
    {
        $payload = $this->makeMixedCompPayload([
            'negotiation_style' => 'same',
            'risk_tolerance'    => 'different',
        ]);

        $result = $this->service->categorize($payload);

        $this->assertSame('same',      $result['dimensions']['negotiation_style']['relationship']);
        $this->assertSame('different', $result['dimensions']['risk_tolerance']['relationship']);
    }

    /** @test */
    public function consumer_and_agent_values_are_forwarded_into_output_dimensions(): void
    {
        $payload = [
            'comparison_version' => 'BYA_COMP_V1',
            'dimensions'         => [
                'negotiation_style' => [
                    'consumer'     => 'collaborative',
                    'agent'        => 'competitive',
                    'relationship' => 'different',
                ],
            ],
        ];

        $result = $this->service->categorize($payload);

        $this->assertSame('collaborative', $result['dimensions']['negotiation_style']['consumer']);
        $this->assertSame('competitive',   $result['dimensions']['negotiation_style']['agent']);
    }

    // -------------------------------------------------------------------------
    // (C) All-same input → all full_alignment, label strong_alignment
    // -------------------------------------------------------------------------

    /** @test */
    public function all_same_input_produces_all_full_alignment_dimensions(): void
    {
        $payload = $this->makeUniformCompPayload('same');

        $result = $this->service->categorize($payload);

        foreach ($result['dimensions'] as $dim => $entry) {
            $this->assertSame(
                'full_alignment',
                $entry['alignment_category'],
                "Expected full_alignment for {$dim} but got {$entry['alignment_category']}"
            );
        }
    }

    /** @test */
    public function all_same_input_produces_advisory_label_strong_alignment(): void
    {
        $payload = $this->makeUniformCompPayload('same');

        $result = $this->service->categorize($payload);

        $this->assertSame('strong_alignment', $result['summary']['advisory_label']);
    }

    /** @test */
    public function all_same_input_has_correct_summary_counts(): void
    {
        $payload = $this->makeUniformCompPayload('same');

        $result  = $this->service->categorize($payload);
        $summary = $result['summary'];

        $this->assertSame(12, $summary['full_alignment_count']);
        $this->assertSame(0,  $summary['partial_alignment_count']);
        $this->assertSame(0,  $summary['adjacent_compatibility_count']);
        $this->assertSame(0,  $summary['neutral_compatibility_count']);
        $this->assertSame(0,  $summary['incompatible_alignment_count']);
        $this->assertSame(0,  $summary['insufficient_data_count']);
        $this->assertSame(12, $summary['scored_dimensions']);
    }

    // -------------------------------------------------------------------------
    // (D) All-different input → all incompatible_alignment, label notable_differences
    // -------------------------------------------------------------------------

    /** @test */
    public function all_different_input_produces_all_incompatible_alignment_dimensions(): void
    {
        $payload = $this->makeUniformCompPayload('different');

        $result = $this->service->categorize($payload);

        foreach ($result['dimensions'] as $dim => $entry) {
            $this->assertSame(
                'incompatible_alignment',
                $entry['alignment_category'],
                "Expected incompatible_alignment for {$dim} but got {$entry['alignment_category']}"
            );
        }
    }

    /** @test */
    public function all_different_input_produces_advisory_label_notable_differences(): void
    {
        $payload = $this->makeUniformCompPayload('different');

        $result = $this->service->categorize($payload);

        $this->assertSame('notable_differences', $result['summary']['advisory_label']);
    }

    /** @test */
    public function all_different_input_has_correct_summary_counts(): void
    {
        $payload = $this->makeUniformCompPayload('different');

        $result  = $this->service->categorize($payload);
        $summary = $result['summary'];

        $this->assertSame(0,  $summary['full_alignment_count']);
        $this->assertSame(0,  $summary['partial_alignment_count']);
        $this->assertSame(0,  $summary['adjacent_compatibility_count']);
        $this->assertSame(0,  $summary['neutral_compatibility_count']);
        $this->assertSame(12, $summary['incompatible_alignment_count']);
        $this->assertSame(0,  $summary['insufficient_data_count']);
        $this->assertSame(12, $summary['scored_dimensions']);
    }

    // -------------------------------------------------------------------------
    // (E) All-unknown input → all insufficient_data, label insufficient_compatibility_data
    // -------------------------------------------------------------------------

    /** @test */
    public function all_unknown_input_produces_all_insufficient_data_dimensions(): void
    {
        $payload = $this->makeUniformCompPayload('unknown');

        $result = $this->service->categorize($payload);

        foreach ($result['dimensions'] as $dim => $entry) {
            $this->assertSame(
                'insufficient_data',
                $entry['alignment_category'],
                "Expected insufficient_data for {$dim} but got {$entry['alignment_category']}"
            );
        }
    }

    /** @test */
    public function all_unknown_input_produces_advisory_label_insufficient_compatibility_data(): void
    {
        $payload = $this->makeUniformCompPayload('unknown');

        $result = $this->service->categorize($payload);

        $this->assertSame('insufficient_compatibility_data', $result['summary']['advisory_label']);
    }

    /** @test */
    public function all_unknown_input_has_zero_scored_dimensions(): void
    {
        $payload = $this->makeUniformCompPayload('unknown');

        $result = $this->service->categorize($payload);

        $this->assertSame(0,  $result['summary']['scored_dimensions']);
        $this->assertSame(12, $result['summary']['insufficient_data_count']);
    }

    // -------------------------------------------------------------------------
    // (F) Advisory label boundary conditions
    // -------------------------------------------------------------------------

    /** @test */
    public function notable_differences_label_fires_when_incompatible_is_exactly_40_percent(): void
    {
        // 10 dimensions: 4 different (40%), 6 same (60%) → notable_differences fires first
        $payload = $this->makeMixedCompPayload([
            'd1' => 'different',
            'd2' => 'different',
            'd3' => 'different',
            'd4' => 'different',
            'd5' => 'same',
            'd6' => 'same',
            'd7' => 'same',
            'd8' => 'same',
            'd9' => 'same',
            'd10'=> 'same',
        ]);

        $result = $this->service->categorize($payload);

        $this->assertSame('notable_differences', $result['summary']['advisory_label']);
    }

    /** @test */
    public function notable_differences_label_does_not_fire_when_incompatible_is_below_40_percent(): void
    {
        // 10 dimensions: 3 different (30%), 7 same (70%) → notable_differences does not fire;
        // strong_alignment fires because (7/10 = 70%) >= 60%
        $payload = $this->makeMixedCompPayload([
            'd1' => 'different',
            'd2' => 'different',
            'd3' => 'different',
            'd4' => 'same',
            'd5' => 'same',
            'd6' => 'same',
            'd7' => 'same',
            'd8' => 'same',
            'd9' => 'same',
            'd10'=> 'same',
        ]);

        $result = $this->service->categorize($payload);

        $this->assertNotSame('notable_differences', $result['summary']['advisory_label']);
        $this->assertSame('strong_alignment', $result['summary']['advisory_label']);
    }

    /** @test */
    public function strong_alignment_label_fires_when_aligned_exceeds_60_percent_and_incompatible_is_below_40_percent(): void
    {
        // scored=9 (1 unknown), aligned=6 (66.7%), incompatible=3 (33.3%)
        // → notable_differences does not fire (33.3% < 40%)
        // → strong_alignment fires (66.7% >= 60%)
        $payload = $this->makeMixedCompPayload([
            'd1' => 'same',
            'd2' => 'same',
            'd3' => 'same',
            'd4' => 'similar',
            'd5' => 'similar',
            'd6' => 'similar',
            'd7' => 'different',
            'd8' => 'different',
            'd9' => 'different',
            'd10'=> 'unknown',
        ]);

        $result  = $this->service->categorize($payload);
        $summary = $result['summary'];

        $this->assertSame(9, $summary['scored_dimensions']);
        $this->assertSame(3, $summary['full_alignment_count']);
        $this->assertSame(3, $summary['partial_alignment_count']);
        $this->assertSame(3, $summary['incompatible_alignment_count']);
        $this->assertSame('strong_alignment', $summary['advisory_label']);
    }

    /**
     * broad_compatibility is mathematically unreachable in Milestone 5.
     *
     * Under the current RELATIONSHIP_CATEGORY_MAP every scored dimension is either
     * full_alignment, partial_alignment, or incompatible_alignment, so:
     *
     *   aligned + incompatible = scored
     *   → aligned/scored = 1 − incompatible/scored
     *
     * If incompatible/scored < 0.4 (notable_differences does not fire), then
     * aligned/scored > 0.6 and strong_alignment fires instead.
     *
     * broad_compatibility will become reachable when a future milestone adds
     * adjacent_compatibility or neutral_compatibility as scored categories.
     * The label constant and the else-branch rule are defined now so the payload
     * shape and logic are stable for that future addition.
     *
     * @test
     */
    public function broad_compatibility_is_reserved_and_unreachable_in_milestone_5(): void
    {
        // Verify the four uniform cases all produce labels other than broad_compatibility,
        // confirming the label is not emitted by any currently-mapped relationship value.
        $cases = ['same', 'similar', 'different', 'unknown'];

        foreach ($cases as $relationship) {
            $result = $this->service->categorize($this->makeUniformCompPayload($relationship));
            $this->assertNotSame(
                'broad_compatibility',
                $result['summary']['advisory_label'],
                "broad_compatibility must not be emitted in Milestone 5 (relationship: {$relationship})"
            );
        }
    }

    // -------------------------------------------------------------------------
    // (G) Mixed input — crosses label boundaries
    // -------------------------------------------------------------------------

    /** @test */
    public function mixed_input_summary_counts_are_correct(): void
    {
        // 3 same, 2 similar, 4 different, 3 unknown
        $payload = $this->makeMixedCompPayload([
            'd1' => 'same',
            'd2' => 'same',
            'd3' => 'same',
            'd4' => 'similar',
            'd5' => 'similar',
            'd6' => 'different',
            'd7' => 'different',
            'd8' => 'different',
            'd9' => 'different',
            'd10'=> 'unknown',
            'd11'=> 'unknown',
            'd12'=> 'unknown',
        ]);

        $result  = $this->service->categorize($payload);
        $summary = $result['summary'];

        $this->assertSame(3, $summary['full_alignment_count']);
        $this->assertSame(2, $summary['partial_alignment_count']);
        $this->assertSame(0, $summary['adjacent_compatibility_count']);
        $this->assertSame(0, $summary['neutral_compatibility_count']);
        $this->assertSame(4, $summary['incompatible_alignment_count']);
        $this->assertSame(3, $summary['insufficient_data_count']);
        $this->assertSame(9, $summary['scored_dimensions']);
    }

    /** @test */
    public function mixed_input_notable_differences_fires_when_incompatible_at_44_percent(): void
    {
        // scored=9, incompatible=4 (44.4% ≥ 40%) → notable_differences
        $payload = $this->makeMixedCompPayload([
            'd1' => 'same',
            'd2' => 'same',
            'd3' => 'same',
            'd4' => 'similar',
            'd5' => 'similar',
            'd6' => 'different',
            'd7' => 'different',
            'd8' => 'different',
            'd9' => 'different',
            'd10'=> 'unknown',
            'd11'=> 'unknown',
            'd12'=> 'unknown',
        ]);

        $result = $this->service->categorize($payload);

        $this->assertSame('notable_differences', $result['summary']['advisory_label']);
    }

    /** @test */
    public function all_similar_input_produces_all_partial_alignment_and_strong_alignment_label(): void
    {
        $payload = $this->makeUniformCompPayload('similar');

        $result  = $this->service->categorize($payload);
        $summary = $result['summary'];

        $this->assertSame(12, $summary['partial_alignment_count']);
        $this->assertSame(0,  $summary['full_alignment_count']);
        $this->assertSame(0,  $summary['incompatible_alignment_count']);
        $this->assertSame(12, $summary['scored_dimensions']);
        $this->assertSame('strong_alignment', $summary['advisory_label']);
    }

    // -------------------------------------------------------------------------
    // (E) Stub payload — invalid/empty input returns structurally valid payload
    // -------------------------------------------------------------------------

    /** @test */
    public function null_input_returns_structurally_valid_stub_payload(): void
    {
        $result = $this->service->categorize(null);

        $this->assertSame('BYA_ALIGN_V1', $result['alignment_version']);
        $this->assertSame([], $result['dimensions']);
        $this->assertArrayHasKey('summary', $result);
        $this->assertSame('insufficient_compatibility_data', $result['summary']['advisory_label']);
    }

    /** @test */
    public function non_array_input_returns_structurally_valid_stub_payload(): void
    {
        $result = $this->service->categorize('not-an-array');

        $this->assertSame('BYA_ALIGN_V1', $result['alignment_version']);
        $this->assertSame([], $result['dimensions']);
        $this->assertSame('insufficient_compatibility_data', $result['summary']['advisory_label']);
    }

    /** @test */
    public function empty_array_input_returns_structurally_valid_stub_payload(): void
    {
        $result = $this->service->categorize([]);

        $this->assertSame('BYA_ALIGN_V1', $result['alignment_version']);
        $this->assertSame([], $result['dimensions']);
        $this->assertSame('insufficient_compatibility_data', $result['summary']['advisory_label']);
    }

    /** @test */
    public function array_without_dimensions_key_returns_stub_payload(): void
    {
        $result = $this->service->categorize([
            'comparison_version' => 'BYA_COMP_V1',
            'consumer_profile_version' => 'BYA_NORM_V1',
        ]);

        $this->assertSame('BYA_ALIGN_V1', $result['alignment_version']);
        $this->assertSame([], $result['dimensions']);
        $this->assertSame('insufficient_compatibility_data', $result['summary']['advisory_label']);
    }

    /** @test */
    public function array_with_non_array_dimensions_value_returns_stub_payload(): void
    {
        $result = $this->service->categorize([
            'comparison_version' => 'BYA_COMP_V1',
            'dimensions'         => 'not-an-array',
        ]);

        $this->assertSame('BYA_ALIGN_V1', $result['alignment_version']);
        $this->assertSame([], $result['dimensions']);
        $this->assertSame('insufficient_compatibility_data', $result['summary']['advisory_label']);
    }

    /** @test */
    public function stub_payload_has_all_required_summary_keys_with_zero_counts(): void
    {
        $result  = $this->service->categorize(null);
        $summary = $result['summary'];

        foreach (self::EXPECTED_SUMMARY_KEYS as $key) {
            $this->assertArrayHasKey($key, $summary, "stub summary missing key: {$key}");
        }

        $this->assertSame(0, $summary['scored_dimensions']);
        $this->assertSame(0, $summary['full_alignment_count']);
        $this->assertSame(0, $summary['partial_alignment_count']);
        $this->assertSame(0, $summary['adjacent_compatibility_count']);
        $this->assertSame(0, $summary['neutral_compatibility_count']);
        $this->assertSame(0, $summary['incompatible_alignment_count']);
        $this->assertSame(0, $summary['insufficient_data_count']);
    }

    /** @test */
    public function stub_payload_forwards_comparison_version_when_present_in_invalid_input(): void
    {
        $result = $this->service->categorize([
            'comparison_version' => 'BYA_COMP_V1',
        ]);

        $this->assertSame('BYA_COMP_V1', $result['comparison_version']);
    }

    /** @test */
    public function empty_dimensions_array_in_valid_input_produces_insufficient_data_label(): void
    {
        // A valid BYA_COMP_V1 stub payload (e.g., from when both profiles were invalid)
        // has dimensions: []. categorize() should handle it gracefully.
        $payload = [
            'comparison_version'       => 'BYA_COMP_V1',
            'consumer_profile_version' => null,
            'agent_profile_version'    => null,
            'dimensions'               => [],
        ];

        $result = $this->service->categorize($payload);

        $this->assertSame('BYA_ALIGN_V1', $result['alignment_version']);
        $this->assertSame('BYA_COMP_V1',  $result['comparison_version']);
        $this->assertSame([], $result['dimensions']);
        $this->assertSame(0,  $result['summary']['scored_dimensions']);
        $this->assertSame('insufficient_compatibility_data', $result['summary']['advisory_label']);
    }

    // -------------------------------------------------------------------------
    // (H) Reserved categories — never emitted in Milestone 5
    // No test asserts that adjacent_compatibility or neutral_compatibility is produced.
    // This test confirms that all-same and all-different inputs do not produce them.
    // -------------------------------------------------------------------------

    /** @test */
    public function adjacent_compatibility_count_is_always_zero_in_milestone_5_outputs(): void
    {
        $allSame      = $this->makeUniformCompPayload('same');
        $allDifferent = $this->makeUniformCompPayload('different');
        $allSimilar   = $this->makeUniformCompPayload('similar');
        $allUnknown   = $this->makeUniformCompPayload('unknown');

        foreach ([$allSame, $allDifferent, $allSimilar, $allUnknown] as $payload) {
            $result = $this->service->categorize($payload);
            $this->assertSame(
                0,
                $result['summary']['adjacent_compatibility_count'],
                'adjacent_compatibility_count must be 0 in all Milestone 5 outputs'
            );
        }
    }

    /** @test */
    public function neutral_compatibility_count_is_always_zero_in_milestone_5_outputs(): void
    {
        $allSame      = $this->makeUniformCompPayload('same');
        $allDifferent = $this->makeUniformCompPayload('different');
        $allSimilar   = $this->makeUniformCompPayload('similar');
        $allUnknown   = $this->makeUniformCompPayload('unknown');

        foreach ([$allSame, $allDifferent, $allSimilar, $allUnknown] as $payload) {
            $result = $this->service->categorize($payload);
            $this->assertSame(
                0,
                $result['summary']['neutral_compatibility_count'],
                'neutral_compatibility_count must be 0 in all Milestone 5 outputs'
            );
        }
    }
}
