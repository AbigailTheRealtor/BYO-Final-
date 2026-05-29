<?php

namespace Tests\Unit\Services\Dna\Compatibility;

use App\Services\Dna\Compatibility\ByaCompatibilityReportService;
use PHPUnit\Framework\TestCase;

/**
 * ByaCompatibilityReportServiceTest
 *
 * Verifies the BYA_REPORT_V1 internal report assembler against in-memory
 * BYA_ALIGN_V1, BYA_EXPLAIN_V1, and BYA_NARRATIVE_V1 stubs.
 * No database connection is required — all test data is fabricated inline.
 *
 * Test coverage:
 *   (A) Payload shape     — report_version, dimensions, summary, audit always present.
 *   (B) Version forwarding — alignment, explanation, and narrative versions forwarded.
 *   (C) Dimension merging — each field sourced from the correct upstream payload.
 *   (D) Null field handling — missing align/explain fields yield null (never invented).
 *   (E) Narrative passthrough — sentence and template_id copied unchanged.
 *   (F) Summary counts    — alignment_category_counts, explanation_type_counts,
 *                            narrative_type_counts copied from upstream summaries.
 *   (G) Zero-fill fallbacks — malformed or absent count blocks return zero-filled arrays.
 *   (H) Audit trace keys  — one trace entry per dimension with correct fields.
 *   (I) Stub payload      — invalid/null/missing-dimensions input returns structurally
 *                            complete stub, never throws.
 *   (J) No score fields   — no ranking, score, or recommendation fields in output.
 *   (K) Determinism       — identical inputs always produce identical output.
 */
class ByaCompatibilityReportServiceTest extends TestCase
{
    private ByaCompatibilityReportService $service;

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

    private const ALL_ALIGNMENT_CATEGORIES = [
        'full_alignment',
        'partial_alignment',
        'adjacent_compatibility',
        'neutral_compatibility',
        'incompatible_alignment',
        'insufficient_data',
    ];

    private const ALL_EXPLANATION_TYPES = [
        'alignment',
        'difference',
        'adjacent',
        'neutral',
        'insufficient_data',
    ];

    private const ALL_NARRATIVE_TYPES = [
        'alignment',
        'difference',
        'adjacent',
        'neutral',
        'insufficient_data',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ByaCompatibilityReportService();
    }

    // -------------------------------------------------------------------------
    // Helpers: build inline upstream payload stubs
    // -------------------------------------------------------------------------

    /**
     * Build a single BYA_ALIGN_V1 dimension entry.
     */
    private function makeAlignDimensionEntry(
        string  $relationship       = 'same',
        string  $alignmentCategory  = 'full_alignment',
        mixed   $consumer           = 'consumer_val',
        mixed   $agent              = 'agent_val'
    ): array {
        return [
            'relationship'       => $relationship,
            'alignment_category' => $alignmentCategory,
            'consumer'           => $consumer,
            'agent'              => $agent,
        ];
    }

    /**
     * Build a single BYA_EXPLAIN_V1 dimension entry.
     */
    private function makeExplainDimensionEntry(
        string  $dimensionName,
        string  $alignmentCategory = 'full_alignment',
        string  $explanationType   = 'alignment',
        ?string $relationship      = 'same'
    ): array {
        return [
            'relationship'       => $relationship,
            'alignment_category' => $alignmentCategory,
            'explanation_type'   => $explanationType,
            'explanation_key'    => $dimensionName . '_' . $alignmentCategory,
        ];
    }

    /**
     * Build a single BYA_NARRATIVE_V1 dimension entry.
     */
    private function makeNarrativeDimensionEntry(
        string  $dimensionName,
        string  $alignmentCategory = 'full_alignment',
        string  $narrativeType     = 'alignment',
        ?string $sentence          = null
    ): array {
        $templateId      = $dimensionName . '_' . $alignmentCategory;
        $resolvedSentence = $sentence ?? "Sample sentence for {$dimensionName}.";

        return [
            'explanation_key' => $dimensionName . '_' . $alignmentCategory,
            'narrative_type'  => $narrativeType,
            'template_id'     => $templateId,
            'sentence'        => $resolvedSentence,
        ];
    }

    /**
     * Build a zero-filled alignment_category_counts array.
     */
    private function zeroAlignmentCounts(): array
    {
        return array_fill_keys(self::ALL_ALIGNMENT_CATEGORIES, 0);
    }

    /**
     * Build a zero-filled explanation_type_counts array.
     */
    private function zeroExplanationCounts(): array
    {
        return array_fill_keys(self::ALL_EXPLANATION_TYPES, 0);
    }

    /**
     * Build a zero-filled narrative_type_counts array.
     */
    private function zeroNarrativeCounts(): array
    {
        return array_fill_keys(self::ALL_NARRATIVE_TYPES, 0);
    }

    /**
     * Build a BYA_ALIGN_V1 payload for a single dimension.
     */
    private function makeSingleAlignPayload(
        string $dimensionName,
        string $relationship      = 'same',
        string $alignmentCategory = 'full_alignment'
    ): array {
        return [
            'alignment_version'  => 'BYA_ALIGN_V1',
            'comparison_version' => 'BYA_COMP_V1',
            'dimensions'         => [
                $dimensionName => $this->makeAlignDimensionEntry($relationship, $alignmentCategory),
            ],
            'summary' => [
                'alignment_category_counts' => array_merge(
                    $this->zeroAlignmentCounts(),
                    [$alignmentCategory => 1]
                ),
            ],
        ];
    }

    /**
     * Build a BYA_EXPLAIN_V1 payload for a single dimension.
     */
    private function makeSingleExplainPayload(
        string  $dimensionName,
        string  $alignmentCategory = 'full_alignment',
        string  $explanationType   = 'alignment'
    ): array {
        $typeCounts = array_merge($this->zeroExplanationCounts(), [$explanationType => 1]);

        return [
            'explanation_version' => 'BYA_EXPLAIN_V1',
            'alignment_version'   => 'BYA_ALIGN_V1',
            'dimensions'          => [
                $dimensionName => $this->makeExplainDimensionEntry(
                    $dimensionName, $alignmentCategory, $explanationType
                ),
            ],
            'summary' => [
                'total_dimensions'        => 1,
                'explained_dimensions'    => 1,
                'explanation_type_counts' => $typeCounts,
            ],
        ];
    }

    /**
     * Build a BYA_NARRATIVE_V1 payload for a single dimension.
     */
    private function makeSingleNarrativePayload(
        string  $dimensionName,
        string  $alignmentCategory = 'full_alignment',
        string  $narrativeType     = 'alignment',
        ?string $sentence          = null,
        ?string $summarySentence   = 'Summary sentence.'
    ): array {
        $typeCounts = array_merge($this->zeroNarrativeCounts(), [$narrativeType => 1]);

        return [
            'narrative_version'   => 'BYA_NARRATIVE_V1',
            'explanation_version' => 'BYA_EXPLAIN_V1',
            'alignment_version'   => 'BYA_ALIGN_V1',
            'dimensions'          => [
                $dimensionName => $this->makeNarrativeDimensionEntry(
                    $dimensionName, $alignmentCategory, $narrativeType, $sentence
                ),
            ],
            'summary' => [
                'total_dimensions'      => 1,
                'narrative_type_counts' => $typeCounts,
                'summary_sentence'      => $summarySentence,
            ],
        ];
    }

    /**
     * Build a full 12-dimension set of all three upstream payloads with realistic
     * mixed alignment: 8 full_alignment, 2 incompatible_alignment, 2 insufficient_data.
     */
    private function make12DimensionPayloads(): array
    {
        $map = [
            'communication_style'         => ['same',      'full_alignment',         'alignment',        'alignment'],
            'communication_frequency'     => ['same',      'full_alignment',         'alignment',        'alignment'],
            'decision_speed'              => ['same',      'full_alignment',         'alignment',        'alignment'],
            'risk_tolerance'              => ['same',      'full_alignment',         'alignment',        'alignment'],
            'negotiation_style'           => ['same',      'full_alignment',         'alignment',        'alignment'],
            'advisor_expectation'         => ['same',      'full_alignment',         'alignment',        'alignment'],
            'technology_preference'       => ['unknown',   'insufficient_data',      'insufficient_data','insufficient_data'],
            'market_education_preference' => ['unknown',   'insufficient_data',      'insufficient_data','insufficient_data'],
            'property_search_involvement' => ['same',      'full_alignment',         'alignment',        'alignment'],
            'transaction_guidance_level'  => ['same',      'full_alignment',         'alignment',        'alignment'],
            'availability_expectation'    => ['different', 'incompatible_alignment', 'difference',       'difference'],
            'personality_style'           => ['different', 'incompatible_alignment', 'difference',       'difference'],
        ];

        $alignDimensions   = [];
        $explainDimensions = [];
        $narrativeDimensions = [];

        $alignCounts    = $this->zeroAlignmentCounts();
        $explainCounts  = $this->zeroExplanationCounts();
        $narrativeCounts = $this->zeroNarrativeCounts();

        foreach ($map as $dim => [$relationship, $alignCat, $explainType, $narrativeType]) {
            $alignDimensions[$dim]     = $this->makeAlignDimensionEntry($relationship, $alignCat);
            $explainDimensions[$dim]   = $this->makeExplainDimensionEntry($dim, $alignCat, $explainType);
            $narrativeDimensions[$dim] = $this->makeNarrativeDimensionEntry($dim, $alignCat, $narrativeType);
            $alignCounts[$alignCat]++;
            $explainCounts[$explainType]++;
            $narrativeCounts[$narrativeType]++;
        }

        $alignPayload = [
            'alignment_version'  => 'BYA_ALIGN_V1',
            'comparison_version' => 'BYA_COMP_V1',
            'dimensions'         => $alignDimensions,
            'summary'            => [
                'alignment_category_counts' => $alignCounts,
            ],
        ];

        $explainPayload = [
            'explanation_version' => 'BYA_EXPLAIN_V1',
            'alignment_version'   => 'BYA_ALIGN_V1',
            'dimensions'          => $explainDimensions,
            'summary'             => [
                'total_dimensions'        => 12,
                'explained_dimensions'    => 10,
                'explanation_type_counts' => $explainCounts,
            ],
        ];

        $narrativePayload = [
            'narrative_version'   => 'BYA_NARRATIVE_V1',
            'explanation_version' => 'BYA_EXPLAIN_V1',
            'alignment_version'   => 'BYA_ALIGN_V1',
            'dimensions'          => $narrativeDimensions,
            'summary'             => [
                'total_dimensions'      => 12,
                'narrative_type_counts' => $narrativeCounts,
                'summary_sentence'      => 'Several areas of alignment were found.',
            ],
        ];

        return [$alignPayload, $explainPayload, $narrativePayload];
    }

    // -------------------------------------------------------------------------
    // (A) Payload shape — top-level keys always present
    // -------------------------------------------------------------------------

    /** @test */
    public function it_always_returns_report_version_BYA_REPORT_V1(): void
    {
        [$align, $explain, $narrative] = $this->make12DimensionPayloads();

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertArrayHasKey('report_version', $result);
        $this->assertSame('BYA_REPORT_V1', $result['report_version']);
    }

    /** @test */
    public function it_always_returns_dimensions_summary_and_audit_keys(): void
    {
        [$align, $explain, $narrative] = $this->make12DimensionPayloads();

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertArrayHasKey('dimensions', $result);
        $this->assertArrayHasKey('summary',    $result);
        $this->assertArrayHasKey('audit',      $result);
        $this->assertIsArray($result['dimensions']);
        $this->assertIsArray($result['summary']);
        $this->assertIsArray($result['audit']);
    }

    /** @test */
    public function each_dimension_entry_contains_all_six_required_fields(): void
    {
        [$align, $explain, $narrative] = $this->make12DimensionPayloads();

        $result = $this->service->generate($align, $explain, $narrative);

        foreach ($result['dimensions'] as $dim => $entry) {
            $this->assertArrayHasKey('relationship',       $entry, "{$dim} missing relationship");
            $this->assertArrayHasKey('alignment_category', $entry, "{$dim} missing alignment_category");
            $this->assertArrayHasKey('explanation_type',   $entry, "{$dim} missing explanation_type");
            $this->assertArrayHasKey('explanation_key',    $entry, "{$dim} missing explanation_key");
            $this->assertArrayHasKey('template_id',        $entry, "{$dim} missing template_id");
            $this->assertArrayHasKey('sentence',           $entry, "{$dim} missing sentence");
        }
    }

    /** @test */
    public function summary_contains_all_four_required_keys(): void
    {
        [$align, $explain, $narrative] = $this->make12DimensionPayloads();

        $result  = $this->service->generate($align, $explain, $narrative);
        $summary = $result['summary'];

        $this->assertArrayHasKey('alignment_category_counts', $summary);
        $this->assertArrayHasKey('explanation_type_counts',   $summary);
        $this->assertArrayHasKey('narrative_type_counts',     $summary);
        $this->assertArrayHasKey('summary_sentence',          $summary);
    }

    /** @test */
    public function audit_contains_source_versions_and_trace_keys(): void
    {
        [$align, $explain, $narrative] = $this->make12DimensionPayloads();

        $result = $this->service->generate($align, $explain, $narrative);
        $audit  = $result['audit'];

        $this->assertArrayHasKey('source_versions', $audit);
        $this->assertArrayHasKey('trace_keys',      $audit);
        $this->assertIsArray($audit['source_versions']);
        $this->assertIsArray($audit['trace_keys']);
    }

    /** @test */
    public function audit_source_versions_contains_all_three_version_keys(): void
    {
        [$align, $explain, $narrative] = $this->make12DimensionPayloads();

        $result         = $this->service->generate($align, $explain, $narrative);
        $sourceVersions = $result['audit']['source_versions'];

        $this->assertArrayHasKey('alignment_version',   $sourceVersions);
        $this->assertArrayHasKey('explanation_version', $sourceVersions);
        $this->assertArrayHasKey('narrative_version',   $sourceVersions);
    }

    /** @test */
    public function valid_12_dimension_report_has_12_dimension_entries(): void
    {
        [$align, $explain, $narrative] = $this->make12DimensionPayloads();

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertCount(12, $result['dimensions']);
    }

    // -------------------------------------------------------------------------
    // (B) Version forwarding
    // -------------------------------------------------------------------------

    /** @test */
    public function it_forwards_alignment_version_from_align_payload(): void
    {
        [$align, $explain, $narrative] = $this->make12DimensionPayloads();

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertSame('BYA_ALIGN_V1', $result['alignment_version']);
    }

    /** @test */
    public function it_forwards_explanation_version_from_explain_payload(): void
    {
        [$align, $explain, $narrative] = $this->make12DimensionPayloads();

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertSame('BYA_EXPLAIN_V1', $result['explanation_version']);
    }

    /** @test */
    public function it_forwards_narrative_version_from_narrative_payload(): void
    {
        [$align, $explain, $narrative] = $this->make12DimensionPayloads();

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertSame('BYA_NARRATIVE_V1', $result['narrative_version']);
    }

    /** @test */
    public function audit_source_versions_match_top_level_version_fields(): void
    {
        [$align, $explain, $narrative] = $this->make12DimensionPayloads();

        $result         = $this->service->generate($align, $explain, $narrative);
        $sourceVersions = $result['audit']['source_versions'];

        $this->assertSame($result['alignment_version'],   $sourceVersions['alignment_version']);
        $this->assertSame($result['explanation_version'], $sourceVersions['explanation_version']);
        $this->assertSame($result['narrative_version'],   $sourceVersions['narrative_version']);
    }

    /** @test */
    public function it_returns_null_alignment_version_when_absent_from_align_payload(): void
    {
        $align   = ['dimensions' => ['d1' => $this->makeAlignDimensionEntry()]];
        $explain = $this->makeSingleExplainPayload('d1');
        $narrative = $this->makeSingleNarrativePayload('d1');

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertNull($result['alignment_version']);
    }

    /** @test */
    public function it_returns_null_explanation_version_when_absent_from_explain_payload(): void
    {
        $align   = $this->makeSingleAlignPayload('d1');
        $explain = ['dimensions' => ['d1' => $this->makeExplainDimensionEntry('d1')]];
        $narrative = $this->makeSingleNarrativePayload('d1');

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertNull($result['explanation_version']);
    }

    /** @test */
    public function it_returns_null_narrative_version_when_absent_from_narrative_payload(): void
    {
        $align   = $this->makeSingleAlignPayload('d1');
        $explain = $this->makeSingleExplainPayload('d1');
        $narrative = [
            'dimensions' => ['d1' => $this->makeNarrativeDimensionEntry('d1')],
            'summary'    => ['narrative_type_counts' => $this->zeroNarrativeCounts(), 'summary_sentence' => null],
        ];

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertNull($result['narrative_version']);
    }

    // -------------------------------------------------------------------------
    // (C) Dimension merging — each field sourced from the correct upstream
    // -------------------------------------------------------------------------

    /** @test */
    public function relationship_is_sourced_from_align_payload(): void
    {
        $align = [
            'alignment_version' => 'BYA_ALIGN_V1',
            'dimensions'        => [
                'negotiation_style' => $this->makeAlignDimensionEntry('different', 'incompatible_alignment'),
            ],
            'summary' => [],
        ];
        $explain   = $this->makeSingleExplainPayload('negotiation_style', 'incompatible_alignment', 'difference');
        $narrative = $this->makeSingleNarrativePayload('negotiation_style', 'incompatible_alignment', 'difference');

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertSame('different', $result['dimensions']['negotiation_style']['relationship']);
    }

    /** @test */
    public function alignment_category_is_sourced_from_align_payload(): void
    {
        $align = [
            'alignment_version' => 'BYA_ALIGN_V1',
            'dimensions'        => [
                'risk_tolerance' => $this->makeAlignDimensionEntry('different', 'incompatible_alignment'),
            ],
            'summary' => [],
        ];
        $explain   = $this->makeSingleExplainPayload('risk_tolerance', 'incompatible_alignment', 'difference');
        $narrative = $this->makeSingleNarrativePayload('risk_tolerance', 'incompatible_alignment', 'difference');

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertSame('incompatible_alignment', $result['dimensions']['risk_tolerance']['alignment_category']);
    }

    /** @test */
    public function explanation_type_is_sourced_from_explain_payload(): void
    {
        $align   = $this->makeSingleAlignPayload('communication_style', 'different', 'incompatible_alignment');
        $explain = [
            'explanation_version' => 'BYA_EXPLAIN_V1',
            'alignment_version'   => 'BYA_ALIGN_V1',
            'dimensions'          => [
                'communication_style' => [
                    'relationship'       => 'different',
                    'alignment_category' => 'incompatible_alignment',
                    'explanation_type'   => 'difference',
                    'explanation_key'    => 'communication_style_incompatible_alignment',
                ],
            ],
            'summary' => ['explanation_type_counts' => $this->zeroExplanationCounts()],
        ];
        $narrative = $this->makeSingleNarrativePayload('communication_style', 'incompatible_alignment', 'difference');

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertSame('difference', $result['dimensions']['communication_style']['explanation_type']);
    }

    /** @test */
    public function explanation_key_is_sourced_from_explain_payload(): void
    {
        $align   = $this->makeSingleAlignPayload('decision_speed');
        $explain = $this->makeSingleExplainPayload('decision_speed', 'full_alignment', 'alignment');
        $narrative = $this->makeSingleNarrativePayload('decision_speed');

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertSame('decision_speed_full_alignment', $result['dimensions']['decision_speed']['explanation_key']);
    }

    /** @test */
    public function template_id_is_sourced_from_narrative_payload(): void
    {
        $align   = $this->makeSingleAlignPayload('personality_style');
        $explain = $this->makeSingleExplainPayload('personality_style');
        $narrative = $this->makeSingleNarrativePayload('personality_style', 'full_alignment', 'alignment', 'The sentence.');

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertSame('personality_style_full_alignment', $result['dimensions']['personality_style']['template_id']);
    }

    /** @test */
    public function sentence_is_sourced_from_narrative_payload(): void
    {
        $expectedSentence = 'This is the expected sentence text.';

        $align   = $this->makeSingleAlignPayload('advisor_expectation');
        $explain = $this->makeSingleExplainPayload('advisor_expectation');
        $narrative = $this->makeSingleNarrativePayload('advisor_expectation', 'full_alignment', 'alignment', $expectedSentence);

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertSame($expectedSentence, $result['dimensions']['advisor_expectation']['sentence']);
    }

    // -------------------------------------------------------------------------
    // (D) Null field handling — missing align/explain fields yield null
    // -------------------------------------------------------------------------

    /** @test */
    public function relationship_is_null_when_align_payload_has_no_dimensions_entry_for_the_dimension(): void
    {
        $align = [
            'alignment_version' => 'BYA_ALIGN_V1',
            'dimensions'        => [],
            'summary'           => [],
        ];
        $explain   = $this->makeSingleExplainPayload('communication_frequency');
        $narrative = $this->makeSingleNarrativePayload('communication_frequency');

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertNull($result['dimensions']['communication_frequency']['relationship']);
    }

    /** @test */
    public function alignment_category_is_null_when_align_payload_has_no_dimensions_entry_for_the_dimension(): void
    {
        $align = [
            'alignment_version' => 'BYA_ALIGN_V1',
            'dimensions'        => [],
            'summary'           => [],
        ];
        $explain   = $this->makeSingleExplainPayload('communication_frequency');
        $narrative = $this->makeSingleNarrativePayload('communication_frequency');

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertNull($result['dimensions']['communication_frequency']['alignment_category']);
    }

    /** @test */
    public function explanation_type_is_null_when_explain_payload_has_no_dimensions_entry_for_the_dimension(): void
    {
        $align   = $this->makeSingleAlignPayload('availability_expectation');
        $explain = [
            'explanation_version' => 'BYA_EXPLAIN_V1',
            'alignment_version'   => 'BYA_ALIGN_V1',
            'dimensions'          => [],
            'summary'             => [],
        ];
        $narrative = $this->makeSingleNarrativePayload('availability_expectation');

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertNull($result['dimensions']['availability_expectation']['explanation_type']);
    }

    /** @test */
    public function explanation_key_is_null_when_explain_payload_has_no_dimensions_entry_for_the_dimension(): void
    {
        $align   = $this->makeSingleAlignPayload('availability_expectation');
        $explain = [
            'explanation_version' => 'BYA_EXPLAIN_V1',
            'alignment_version'   => 'BYA_ALIGN_V1',
            'dimensions'          => [],
            'summary'             => [],
        ];
        $narrative = $this->makeSingleNarrativePayload('availability_expectation');

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertNull($result['dimensions']['availability_expectation']['explanation_key']);
    }

    /** @test */
    public function null_align_payload_produces_null_relationship_and_alignment_category_for_all_dimensions(): void
    {
        $explain   = $this->makeSingleExplainPayload('transaction_guidance_level');
        $narrative = $this->makeSingleNarrativePayload('transaction_guidance_level');

        $result = $this->service->generate(null, $explain, $narrative);

        $this->assertNull($result['dimensions']['transaction_guidance_level']['relationship']);
        $this->assertNull($result['dimensions']['transaction_guidance_level']['alignment_category']);
    }

    /** @test */
    public function null_explain_payload_produces_null_explanation_type_and_explanation_key_for_all_dimensions(): void
    {
        $align     = $this->makeSingleAlignPayload('transaction_guidance_level');
        $narrative = $this->makeSingleNarrativePayload('transaction_guidance_level');

        $result = $this->service->generate($align, null, $narrative);

        $this->assertNull($result['dimensions']['transaction_guidance_level']['explanation_type']);
        $this->assertNull($result['dimensions']['transaction_guidance_level']['explanation_key']);
    }

    // -------------------------------------------------------------------------
    // (E) Narrative passthrough — sentence and template_id copied unchanged
    // -------------------------------------------------------------------------

    /** @test */
    public function sentence_text_is_passed_through_unchanged_from_narrative_payload(): void
    {
        $verbatimSentence = 'You indicated a preference for email; this agent describes their approach as email.';

        $align   = $this->makeSingleAlignPayload('communication_style');
        $explain = $this->makeSingleExplainPayload('communication_style');
        $narrative = $this->makeSingleNarrativePayload('communication_style', 'full_alignment', 'alignment', $verbatimSentence);

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertSame($verbatimSentence, $result['dimensions']['communication_style']['sentence']);
    }

    /** @test */
    public function all_12_dimensions_are_present_and_have_non_null_sentences(): void
    {
        [$align, $explain, $narrative] = $this->make12DimensionPayloads();

        $result = $this->service->generate($align, $explain, $narrative);

        foreach (self::CANONICAL_DIMENSIONS as $dim) {
            $this->assertArrayHasKey($dim, $result['dimensions'], "Missing dimension: {$dim}");
            $this->assertIsString($result['dimensions'][$dim]['sentence'], "{$dim} sentence must be a string");
        }
    }

    // -------------------------------------------------------------------------
    // (F) Summary counts — copied from upstream summaries
    // -------------------------------------------------------------------------

    /** @test */
    public function alignment_category_counts_are_copied_from_align_payload_summary(): void
    {
        $alignCounts = [
            'full_alignment'          => 8,
            'partial_alignment'       => 0,
            'adjacent_compatibility'  => 0,
            'neutral_compatibility'   => 0,
            'incompatible_alignment'  => 2,
            'insufficient_data'       => 2,
        ];

        [$align, $explain, $narrative] = $this->make12DimensionPayloads();
        $align['summary']['alignment_category_counts'] = $alignCounts;

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertSame($alignCounts, $result['summary']['alignment_category_counts']);
    }

    /** @test */
    public function explanation_type_counts_are_copied_from_explain_payload_summary(): void
    {
        $explainCounts = [
            'alignment'        => 8,
            'difference'       => 2,
            'adjacent'         => 0,
            'neutral'          => 0,
            'insufficient_data'=> 2,
        ];

        [$align, $explain, $narrative] = $this->make12DimensionPayloads();
        $explain['summary']['explanation_type_counts'] = $explainCounts;

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertSame($explainCounts, $result['summary']['explanation_type_counts']);
    }

    /** @test */
    public function narrative_type_counts_are_copied_from_narrative_payload_summary(): void
    {
        $narrativeCounts = [
            'alignment'        => 8,
            'difference'       => 2,
            'adjacent'         => 0,
            'neutral'          => 0,
            'insufficient_data'=> 2,
        ];

        [$align, $explain, $narrative] = $this->make12DimensionPayloads();
        $narrative['summary']['narrative_type_counts'] = $narrativeCounts;

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertSame($narrativeCounts, $result['summary']['narrative_type_counts']);
    }

    /** @test */
    public function summary_sentence_is_copied_from_narrative_payload_summary(): void
    {
        $expectedSentence = 'Several areas of alignment were found, and one or more areas of difference were also identified.';

        [$align, $explain, $narrative] = $this->make12DimensionPayloads();
        $narrative['summary']['summary_sentence'] = $expectedSentence;

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertSame($expectedSentence, $result['summary']['summary_sentence']);
    }

    // -------------------------------------------------------------------------
    // (G) Zero-fill fallbacks — malformed or absent count blocks
    // -------------------------------------------------------------------------

    /** @test */
    public function alignment_category_counts_are_zero_filled_when_align_summary_is_absent(): void
    {
        $align = [
            'alignment_version' => 'BYA_ALIGN_V1',
            'dimensions'        => ['d1' => $this->makeAlignDimensionEntry()],
        ];
        $explain   = $this->makeSingleExplainPayload('d1');
        $narrative = $this->makeSingleNarrativePayload('d1');

        $result = $this->service->generate($align, $explain, $narrative);
        $counts = $result['summary']['alignment_category_counts'];

        foreach (self::ALL_ALIGNMENT_CATEGORIES as $cat) {
            $this->assertArrayHasKey($cat, $counts, "alignment_category_counts missing key: {$cat}");
            $this->assertSame(0, $counts[$cat], "alignment_category_counts[{$cat}] should be 0");
        }
    }

    /** @test */
    public function alignment_category_counts_are_zero_filled_when_the_key_is_not_an_array(): void
    {
        $align = [
            'alignment_version' => 'BYA_ALIGN_V1',
            'dimensions'        => ['d1' => $this->makeAlignDimensionEntry()],
            'summary'           => ['alignment_category_counts' => 'not_an_array'],
        ];
        $explain   = $this->makeSingleExplainPayload('d1');
        $narrative = $this->makeSingleNarrativePayload('d1');

        $result = $this->service->generate($align, $explain, $narrative);
        $counts = $result['summary']['alignment_category_counts'];

        $this->assertIsArray($counts);
        foreach (self::ALL_ALIGNMENT_CATEGORIES as $cat) {
            $this->assertSame(0, $counts[$cat]);
        }
    }

    /** @test */
    public function explanation_type_counts_are_zero_filled_when_explain_summary_is_absent(): void
    {
        $align   = $this->makeSingleAlignPayload('d1');
        $explain = [
            'explanation_version' => 'BYA_EXPLAIN_V1',
            'alignment_version'   => 'BYA_ALIGN_V1',
            'dimensions'          => ['d1' => $this->makeExplainDimensionEntry('d1')],
        ];
        $narrative = $this->makeSingleNarrativePayload('d1');

        $result = $this->service->generate($align, $explain, $narrative);
        $counts = $result['summary']['explanation_type_counts'];

        foreach (self::ALL_EXPLANATION_TYPES as $type) {
            $this->assertArrayHasKey($type, $counts, "explanation_type_counts missing key: {$type}");
            $this->assertSame(0, $counts[$type]);
        }
    }

    /** @test */
    public function narrative_type_counts_are_zero_filled_when_narrative_summary_counts_not_an_array(): void
    {
        $align   = $this->makeSingleAlignPayload('d1');
        $explain = $this->makeSingleExplainPayload('d1');
        $narrative = [
            'narrative_version' => 'BYA_NARRATIVE_V1',
            'dimensions'        => ['d1' => $this->makeNarrativeDimensionEntry('d1')],
            'summary'           => ['narrative_type_counts' => null, 'summary_sentence' => 'Some sentence.'],
        ];

        $result = $this->service->generate($align, $explain, $narrative);
        $counts = $result['summary']['narrative_type_counts'];

        $this->assertIsArray($counts);
        foreach (self::ALL_NARRATIVE_TYPES as $type) {
            $this->assertSame(0, $counts[$type]);
        }
    }

    /** @test */
    public function summary_sentence_is_null_when_absent_from_narrative_summary(): void
    {
        $align   = $this->makeSingleAlignPayload('d1');
        $explain = $this->makeSingleExplainPayload('d1');
        $narrative = [
            'narrative_version' => 'BYA_NARRATIVE_V1',
            'dimensions'        => ['d1' => $this->makeNarrativeDimensionEntry('d1')],
            'summary'           => ['narrative_type_counts' => $this->zeroNarrativeCounts()],
        ];

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertNull($result['summary']['summary_sentence']);
    }

    // -------------------------------------------------------------------------
    // (H) Audit trace keys — one entry per dimension with correct fields
    // -------------------------------------------------------------------------

    /** @test */
    public function audit_trace_keys_has_one_entry_per_dimension(): void
    {
        [$align, $explain, $narrative] = $this->make12DimensionPayloads();

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertCount(12, $result['audit']['trace_keys']);
    }

    /** @test */
    public function each_trace_key_entry_has_alignment_category_explanation_key_and_template_id(): void
    {
        [$align, $explain, $narrative] = $this->make12DimensionPayloads();

        $result = $this->service->generate($align, $explain, $narrative);

        foreach ($result['audit']['trace_keys'] as $dim => $traceEntry) {
            $this->assertArrayHasKey('alignment_category', $traceEntry, "trace_keys[{$dim}] missing alignment_category");
            $this->assertArrayHasKey('explanation_key',    $traceEntry, "trace_keys[{$dim}] missing explanation_key");
            $this->assertArrayHasKey('template_id',        $traceEntry, "trace_keys[{$dim}] missing template_id");
        }
    }

    /** @test */
    public function trace_keys_are_keyed_by_dimension_name(): void
    {
        [$align, $explain, $narrative] = $this->make12DimensionPayloads();

        $result = $this->service->generate($align, $explain, $narrative);

        foreach (self::CANONICAL_DIMENSIONS as $dim) {
            $this->assertArrayHasKey($dim, $result['audit']['trace_keys'], "trace_keys missing dimension key: {$dim}");
        }
    }

    /** @test */
    public function trace_key_alignment_category_matches_merged_dimension_alignment_category(): void
    {
        [$align, $explain, $narrative] = $this->make12DimensionPayloads();

        $result = $this->service->generate($align, $explain, $narrative);

        foreach ($result['dimensions'] as $dim => $entry) {
            $this->assertSame(
                $entry['alignment_category'],
                $result['audit']['trace_keys'][$dim]['alignment_category'],
                "trace_keys[{$dim}] alignment_category does not match dimension alignment_category"
            );
        }
    }

    /** @test */
    public function trace_key_explanation_key_matches_merged_dimension_explanation_key(): void
    {
        [$align, $explain, $narrative] = $this->make12DimensionPayloads();

        $result = $this->service->generate($align, $explain, $narrative);

        foreach ($result['dimensions'] as $dim => $entry) {
            $this->assertSame(
                $entry['explanation_key'],
                $result['audit']['trace_keys'][$dim]['explanation_key'],
                "trace_keys[{$dim}] explanation_key does not match dimension explanation_key"
            );
        }
    }

    /** @test */
    public function trace_key_template_id_matches_merged_dimension_template_id(): void
    {
        [$align, $explain, $narrative] = $this->make12DimensionPayloads();

        $result = $this->service->generate($align, $explain, $narrative);

        foreach ($result['dimensions'] as $dim => $entry) {
            $this->assertSame(
                $entry['template_id'],
                $result['audit']['trace_keys'][$dim]['template_id'],
                "trace_keys[{$dim}] template_id does not match dimension template_id"
            );
        }
    }

    /** @test */
    public function single_dimension_produces_single_trace_key_entry(): void
    {
        $align   = $this->makeSingleAlignPayload('communication_style');
        $explain = $this->makeSingleExplainPayload('communication_style');
        $narrative = $this->makeSingleNarrativePayload('communication_style');

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertCount(1, $result['audit']['trace_keys']);
    }

    // -------------------------------------------------------------------------
    // (I) Stub payload — invalid/null inputs return structurally valid stub
    // -------------------------------------------------------------------------

    /** @test */
    public function null_narrative_payload_returns_stub_never_throws(): void
    {
        [$align, $explain] = [$this->makeSingleAlignPayload('d1'), $this->makeSingleExplainPayload('d1')];

        $result = $this->service->generate($align, $explain, null);

        $this->assertSame('BYA_REPORT_V1', $result['report_version']);
        $this->assertSame([], $result['dimensions']);
    }

    /** @test */
    public function non_array_narrative_payload_returns_stub(): void
    {
        [$align, $explain] = [$this->makeSingleAlignPayload('d1'), $this->makeSingleExplainPayload('d1')];

        $result = $this->service->generate($align, $explain, 'not_an_array');

        $this->assertSame('BYA_REPORT_V1', $result['report_version']);
        $this->assertSame([], $result['dimensions']);
    }

    /** @test */
    public function narrative_payload_missing_dimensions_key_returns_stub(): void
    {
        [$align, $explain] = [$this->makeSingleAlignPayload('d1'), $this->makeSingleExplainPayload('d1')];
        $narrative = ['narrative_version' => 'BYA_NARRATIVE_V1', 'summary' => []];

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertSame([], $result['dimensions']);
    }

    /** @test */
    public function narrative_payload_with_non_array_dimensions_returns_stub(): void
    {
        [$align, $explain] = [$this->makeSingleAlignPayload('d1'), $this->makeSingleExplainPayload('d1')];
        $narrative = [
            'narrative_version' => 'BYA_NARRATIVE_V1',
            'dimensions'        => 'not_an_array',
        ];

        $result = $this->service->generate($align, $explain, $narrative);

        $this->assertSame([], $result['dimensions']);
    }

    /** @test */
    public function stub_payload_has_report_version_BYA_REPORT_V1(): void
    {
        $result = $this->service->generate(null, null, null);

        $this->assertSame('BYA_REPORT_V1', $result['report_version']);
    }

    /** @test */
    public function stub_payload_has_empty_dimensions(): void
    {
        $result = $this->service->generate(null, null, null);

        $this->assertSame([], $result['dimensions']);
    }

    /** @test */
    public function stub_payload_summary_has_all_counts_zero_filled(): void
    {
        $result  = $this->service->generate(null, null, null);
        $summary = $result['summary'];

        $this->assertIsArray($summary['alignment_category_counts']);
        $this->assertIsArray($summary['explanation_type_counts']);
        $this->assertIsArray($summary['narrative_type_counts']);

        foreach (self::ALL_ALIGNMENT_CATEGORIES as $cat) {
            $this->assertSame(0, $summary['alignment_category_counts'][$cat]);
        }
        foreach (self::ALL_EXPLANATION_TYPES as $type) {
            $this->assertSame(0, $summary['explanation_type_counts'][$type]);
        }
        foreach (self::ALL_NARRATIVE_TYPES as $type) {
            $this->assertSame(0, $summary['narrative_type_counts'][$type]);
        }
    }

    /** @test */
    public function stub_payload_summary_sentence_is_null(): void
    {
        $result = $this->service->generate(null, null, null);

        $this->assertNull($result['summary']['summary_sentence']);
    }

    /** @test */
    public function stub_payload_audit_trace_keys_is_empty_array(): void
    {
        $result = $this->service->generate(null, null, null);

        $this->assertSame([], $result['audit']['trace_keys']);
    }

    /** @test */
    public function stub_forwards_alignment_version_from_align_payload_when_available(): void
    {
        $align = ['alignment_version' => 'BYA_ALIGN_V1', 'dimensions' => 'bad'];

        $result = $this->service->generate($align, null, null);

        $this->assertSame('BYA_ALIGN_V1', $result['alignment_version']);
    }

    /** @test */
    public function stub_forwards_explanation_version_from_explain_payload_when_available(): void
    {
        $explain = ['explanation_version' => 'BYA_EXPLAIN_V1', 'dimensions' => 'bad'];

        $result = $this->service->generate(null, $explain, null);

        $this->assertSame('BYA_EXPLAIN_V1', $result['explanation_version']);
    }

    /** @test */
    public function stub_forwards_narrative_version_from_narrative_payload_when_available(): void
    {
        $narrative = ['narrative_version' => 'BYA_NARRATIVE_V1'];

        $result = $this->service->generate(null, null, $narrative);

        $this->assertSame('BYA_NARRATIVE_V1', $result['narrative_version']);
    }

    /** @test */
    public function all_null_inputs_return_all_null_version_fields_in_stub(): void
    {
        $result = $this->service->generate(null, null, null);

        $this->assertNull($result['alignment_version']);
        $this->assertNull($result['explanation_version']);
        $this->assertNull($result['narrative_version']);
    }

    // -------------------------------------------------------------------------
    // (J) No score fields — no ranking, score, or recommendation fields
    // -------------------------------------------------------------------------

    /** @test */
    public function output_contains_no_score_percentage_or_ranking_fields(): void
    {
        [$align, $explain, $narrative] = $this->make12DimensionPayloads();

        $result = $this->service->generate($align, $explain, $narrative);

        $forbidden = ['score', 'percentage', 'rank', 'ranking', 'recommendation', 'recommended'];
        $serialized = strtolower(json_encode($result));

        foreach ($forbidden as $term) {
            $this->assertStringNotContainsString(
                '"' . $term . '"',
                $serialized,
                "Output must not contain field key \"{$term}\""
            );
        }
    }

    /** @test */
    public function dimension_entries_do_not_contain_score_or_rank_fields(): void
    {
        [$align, $explain, $narrative] = $this->make12DimensionPayloads();

        $result = $this->service->generate($align, $explain, $narrative);

        foreach ($result['dimensions'] as $dim => $entry) {
            $this->assertArrayNotHasKey('score',      $entry, "{$dim} must not have score field");
            $this->assertArrayNotHasKey('rank',       $entry, "{$dim} must not have rank field");
            $this->assertArrayNotHasKey('percentage', $entry, "{$dim} must not have percentage field");
        }
    }

    // -------------------------------------------------------------------------
    // (K) Determinism — identical inputs always produce identical output
    // -------------------------------------------------------------------------

    /** @test */
    public function same_12_dimension_input_always_produces_identical_output(): void
    {
        [$align, $explain, $narrative] = $this->make12DimensionPayloads();

        $resultA = $this->service->generate($align, $explain, $narrative);
        $resultB = $this->service->generate($align, $explain, $narrative);

        $this->assertSame($resultA, $resultB);
    }

    /** @test */
    public function same_single_dimension_input_always_produces_identical_output(): void
    {
        $align   = $this->makeSingleAlignPayload('communication_style', 'different', 'incompatible_alignment');
        $explain = $this->makeSingleExplainPayload('communication_style', 'incompatible_alignment', 'difference');
        $narrative = $this->makeSingleNarrativePayload('communication_style', 'incompatible_alignment', 'difference', 'A fixed sentence.');

        $resultA = $this->service->generate($align, $explain, $narrative);
        $resultB = $this->service->generate($align, $explain, $narrative);

        $this->assertSame($resultA, $resultB);
    }

    /** @test */
    public function same_null_inputs_always_produce_identical_stub_output(): void
    {
        $resultA = $this->service->generate(null, null, null);
        $resultB = $this->service->generate(null, null, null);

        $this->assertSame($resultA, $resultB);
    }
}
