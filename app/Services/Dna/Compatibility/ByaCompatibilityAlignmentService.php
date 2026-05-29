<?php

namespace App\Services\Dna\Compatibility;

/**
 * ByaCompatibilityAlignmentService — BYA_ALIGN_V1 Alignment Categorization Layer
 *
 * Accepts a BYA_COMP_V1 comparison payload (from ByaCompatibilityComparisonService)
 * and produces a deterministic BYA_ALIGN_V1 alignment payload that categorizes each
 * comparison dimension and derives a single qualitative advisory label for the overall
 * alignment picture.
 *
 * This is a pure categorization layer. Its only question is: "what does this comparison
 * mean?" It emits structured alignment categories and an advisory label. It does not
 * rank, score, weight, recommend, or make decisions.
 *
 * GOVERNANCE CONSTRAINTS:
 * - Deterministic only. No randomness, no model calls, no HTTP calls.
 * - No database reads or writes.
 * - No routes, no controllers, no Blade, no Livewire changes.
 * - No numeric scores, no percentages, no weighted composites.
 * - No ranking logic. The advisory label is a qualitative description, not a ranking signal.
 * - No recommendation language. The label describes alignment; it does not endorse or disqualify.
 * - Never throws. All error paths return a structurally valid stub payload.
 * - Output is internal-only. It is never surfaced directly to any public-facing route.
 */
class ByaCompatibilityAlignmentService
{
    private const ALIGNMENT_VERSION = 'BYA_ALIGN_V1';

    /**
     * Maps each BYA_COMP_V1 relationship value to its BYA_ALIGN_V1 alignment category.
     *
     * This is the authoritative mapping for Milestone 5. It is defined as a named constant
     * so it is auditable and not buried in logic.
     *
     * REACHABLE in Milestone 5 (via BYA_COMP_V1 relationship values):
     *   same        → full_alignment
     *   similar     → partial_alignment
     *   different   → incompatible_alignment
     *   unknown     → insufficient_data
     *
     * RESERVED for future governance-defined relationship mappings (not reachable in Milestone 5):
     *   adjacent_compatibility — reserved for when a future milestone adds an 'adjacent'
     *     relationship value to the comparison layer.
     *   neutral_compatibility  — reserved for when a future milestone adds a 'neutral'
     *     relationship value to the comparison layer.
     *
     * Per governance: the executor must not invent relationship mappings to produce
     * adjacent_compatibility or neutral_compatibility. They remain at zero counts in
     * all Milestone 5 outputs.
     */
    private const RELATIONSHIP_CATEGORY_MAP = [
        'same'      => 'full_alignment',
        'similar'   => 'partial_alignment',
        'different' => 'incompatible_alignment',
        'unknown'   => 'insufficient_data',
    ];

    /**
     * The full Phase D alignment category vocabulary. All six values are defined here
     * so that the payload shape (including summary count keys) is stable and
     * future-compatible, even though only four are reachable in Milestone 5.
     */
    private const ALL_ALIGNMENT_CATEGORIES = [
        'full_alignment',
        'partial_alignment',
        'adjacent_compatibility',
        'neutral_compatibility',
        'incompatible_alignment',
        'insufficient_data',
    ];

    /**
     * Fallback category emitted when a dimension's relationship value is not
     * recognized (i.e., not present in RELATIONSHIP_CATEGORY_MAP).
     */
    private const FALLBACK_CATEGORY = 'insufficient_data';

    // -------------------------------------------------------------------------
    // Advisory label constants
    // All thresholds and label values are defined here as named constants per
    // Phase D Section 12.4. Labels are machine-readable keys; display copy is a
    // Phase F concern.
    // -------------------------------------------------------------------------

    /** Minimum ratio of incompatible_alignment dimensions to trigger "notable_differences". */
    private const NOTABLE_DIFFERENCES_THRESHOLD = 0.4;

    /** Minimum ratio of aligned (full + partial) dimensions to trigger "strong_alignment". */
    private const STRONG_ALIGNMENT_THRESHOLD = 0.6;

    /** Advisory label: no scored dimensions available to form an opinion. */
    private const LABEL_INSUFFICIENT_DATA  = 'insufficient_compatibility_data';

    /** Advisory label: incompatible dimensions represent >= 40% of scored dimensions. */
    private const LABEL_NOTABLE_DIFFERENCES = 'notable_differences';

    /** Advisory label: aligned dimensions (full + partial) represent >= 60% of scored dimensions. */
    private const LABEL_STRONG_ALIGNMENT    = 'strong_alignment';

    /** Advisory label: neither threshold crossed; moderate, mixed, or narrow alignment. */
    private const LABEL_BROAD_COMPATIBILITY = 'broad_compatibility';

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Categorize a BYA_COMP_V1 payload and produce a BYA_ALIGN_V1 alignment payload.
     *
     * Accepts the array produced by ByaCompatibilityComparisonService::compare().
     *
     * Returns a BYA_ALIGN_V1 payload. If the input is not a structurally valid
     * BYA_COMP_V1 array (missing `dimensions` key, non-array `dimensions`, or
     * any non-array input), returns a stub payload with dimensions: [] and
     * advisory_label: "insufficient_compatibility_data".
     *
     * DIMENSION PASSTHROUGH DESIGN:
     * This service categorizes exactly the dimensions present in the input payload —
     * no more, no fewer. It does not pad missing dimensions or enforce that the 12
     * canonical names are present. Canonical dimension enforcement is the responsibility
     * of ByaCompatibilityComparisonService (the upstream producer), which always emits
     * all 12 canonical dimensions when at least one profile is structurally valid.
     * This separation of concerns keeps each layer auditable at its own boundary.
     *
     * Never throws. All exceptions are caught and result in the stub payload.
     *
     * @param  mixed  $compV1Payload  BYA_COMP_V1 payload array.
     * @return array                  BYA_ALIGN_V1 payload array.
     */
    public function categorize(mixed $compV1Payload): array
    {
        try {
            if (!$this->isStructurallyValid($compV1Payload)) {
                return $this->buildStubPayload($compV1Payload);
            }

            $comparisonVersion = $compV1Payload['comparison_version'] ?? null;
            $inputDimensions   = $compV1Payload['dimensions'];

            $dimensions = [];
            foreach ($inputDimensions as $dimensionName => $dimensionEntry) {
                $dimensions[$dimensionName] = $this->categorizeDimension($dimensionEntry);
            }

            $summary = $this->buildSummary($dimensions);

            return [
                'alignment_version'  => self::ALIGNMENT_VERSION,
                'comparison_version' => $comparisonVersion,
                'dimensions'         => $dimensions,
                'summary'            => $summary,
            ];
        } catch (\Throwable $e) {
            return $this->buildStubPayload($compV1Payload ?? null);
        }
    }

    // -------------------------------------------------------------------------
    // Dimension categorization
    // -------------------------------------------------------------------------

    /**
     * Categorize a single dimension entry from the BYA_COMP_V1 payload.
     *
     * Each input dimension entry has the shape: {consumer, agent, relationship}.
     * Each output dimension entry has the shape: {relationship, alignment_category, consumer, agent}.
     *
     * If the entry is not an array, returns an insufficient_data fallback entry.
     */
    private function categorizeDimension(mixed $entry): array
    {
        try {
            if (!is_array($entry)) {
                return $this->buildFallbackDimensionEntry(null, null, null);
            }

            $relationship = isset($entry['relationship']) && is_string($entry['relationship'])
                ? $entry['relationship']
                : null;

            $alignmentCategory = $this->mapRelationshipToCategory($relationship);

            return [
                'relationship'       => $relationship,
                'alignment_category' => $alignmentCategory,
                'consumer'           => $entry['consumer'] ?? null,
                'agent'              => $entry['agent']    ?? null,
            ];
        } catch (\Throwable $e) {
            return $this->buildFallbackDimensionEntry(null, null, null);
        }
    }

    /**
     * Map a raw BYA_COMP_V1 relationship string to a BYA_ALIGN_V1 alignment category.
     *
     * Uses RELATIONSHIP_CATEGORY_MAP as the single authoritative lookup.
     * Any relationship value not present in the map (including null) falls back
     * to FALLBACK_CATEGORY (insufficient_data).
     */
    private function mapRelationshipToCategory(?string $relationship): string
    {
        if ($relationship === null) {
            return self::FALLBACK_CATEGORY;
        }

        return self::RELATIONSHIP_CATEGORY_MAP[$relationship] ?? self::FALLBACK_CATEGORY;
    }

    /**
     * Build a fallback dimension entry used when a dimension entry is malformed
     * or an exception is caught during dimension categorization.
     */
    private function buildFallbackDimensionEntry(mixed $relationship, mixed $consumer, mixed $agent): array
    {
        return [
            'relationship'       => $relationship,
            'alignment_category' => self::FALLBACK_CATEGORY,
            'consumer'           => $consumer,
            'agent'              => $agent,
        ];
    }

    // -------------------------------------------------------------------------
    // Summary and advisory label
    // -------------------------------------------------------------------------

    /**
     * Build the summary block from the categorized dimensions array.
     *
     * Counts each alignment category across all dimensions, derives the
     * scored_dimensions count, and applies the advisory label rules in order.
     *
     * Advisory label rules (Phase D Section 12.4, applied in stated priority order):
     *   1. scored_dimensions == 0                                    → insufficient_compatibility_data
     *   2. incompatible_alignment_count / scored_dimensions >= 0.4   → notable_differences
     *   3. (full_alignment_count + partial_alignment_count)
     *      / scored_dimensions >= 0.6                                → strong_alignment
     *   4. (otherwise)                                               → broad_compatibility
     */
    private function buildSummary(array $dimensions): array
    {
        $counts = array_fill_keys(
            array_map(fn($cat) => $cat . '_count', self::ALL_ALIGNMENT_CATEGORIES),
            0
        );

        foreach ($dimensions as $entry) {
            $category = $entry['alignment_category'] ?? self::FALLBACK_CATEGORY;
            $countKey = $category . '_count';
            if (isset($counts[$countKey])) {
                $counts[$countKey]++;
            }
        }

        $scoredDimensions = count($dimensions) - $counts['insufficient_data_count'];

        $advisoryLabel = $this->deriveAdvisoryLabel(
            $scoredDimensions,
            $counts['incompatible_alignment_count'],
            $counts['full_alignment_count'],
            $counts['partial_alignment_count']
        );

        return array_merge(
            ['scored_dimensions' => $scoredDimensions],
            $counts,
            ['advisory_label' => $advisoryLabel]
        );
    }

    /**
     * Derive the advisory label from summary counts.
     *
     * Rules are applied in strict priority order as defined by Phase D Section 12.4.
     * All thresholds are defined as named constants; no magic numbers appear here.
     *
     * The advisory label is a machine-readable key that describes alignment —
     * it does not endorse, disqualify, or rank any party.
     *
     * REACHABILITY NOTE — Milestone 5:
     * Under the current RELATIONSHIP_CATEGORY_MAP, every scored dimension is either
     * full_alignment, partial_alignment, or incompatible_alignment, meaning:
     *
     *   aligned + incompatible = scored
     *   → aligned/scored = 1 − incompatible/scored
     *
     * If incompatible/scored < 0.4 (notable_differences does not fire), then
     * aligned/scored > 0.6 (strong_alignment fires). The two thresholds are
     * complementary given the current scored-category vocabulary, so
     * broad_compatibility is mathematically unreachable in Milestone 5.
     *
     * broad_compatibility will become reachable when future milestones add
     * adjacent_compatibility or neutral_compatibility as scored categories
     * (i.e., when dimensions exist that are scored but neither aligned nor
     * incompatible). The constant, the rule, and the label are defined now
     * so the payload shape is stable for that future addition.
     */
    private function deriveAdvisoryLabel(
        int $scoredDimensions,
        int $incompatibleCount,
        int $fullAlignmentCount,
        int $partialAlignmentCount
    ): string {
        if ($scoredDimensions === 0) {
            return self::LABEL_INSUFFICIENT_DATA;
        }

        if ($incompatibleCount / $scoredDimensions >= self::NOTABLE_DIFFERENCES_THRESHOLD) {
            return self::LABEL_NOTABLE_DIFFERENCES;
        }

        if (($fullAlignmentCount + $partialAlignmentCount) / $scoredDimensions >= self::STRONG_ALIGNMENT_THRESHOLD) {
            return self::LABEL_STRONG_ALIGNMENT;
        }

        return self::LABEL_BROAD_COMPATIBILITY;
    }

    // -------------------------------------------------------------------------
    // Structural validity
    // -------------------------------------------------------------------------

    /**
     * Determine whether the input is a structurally valid BYA_COMP_V1 payload.
     *
     * A payload is structurally valid when it is a non-empty array that contains
     * a `dimensions` key whose value is an array (including an empty array, which
     * represents a valid stub payload from the comparison layer).
     */
    private function isStructurallyValid(mixed $payload): bool
    {
        return is_array($payload)
            && !empty($payload)
            && array_key_exists('dimensions', $payload)
            && is_array($payload['dimensions']);
    }

    // -------------------------------------------------------------------------
    // Stub payload
    // -------------------------------------------------------------------------

    /**
     * Build the stub payload returned when the input is not a structurally valid
     * BYA_COMP_V1 array, or when an unrecoverable exception escapes the main loop.
     *
     * dimensions: [] signals to callers that no categorization was possible.
     * All category counts are 0. advisory_label is insufficient_compatibility_data.
     * comparison_version is forwarded from the input when available, otherwise null.
     */
    private function buildStubPayload(mixed $payload): array
    {
        $comparisonVersion = (is_array($payload) && isset($payload['comparison_version']))
            ? $payload['comparison_version']
            : null;

        $zeroCounts = array_fill_keys(
            array_map(fn($cat) => $cat . '_count', self::ALL_ALIGNMENT_CATEGORIES),
            0
        );

        return [
            'alignment_version'  => self::ALIGNMENT_VERSION,
            'comparison_version' => $comparisonVersion,
            'dimensions'         => [],
            'summary'            => array_merge(
                ['scored_dimensions' => 0],
                $zeroCounts,
                ['advisory_label' => self::LABEL_INSUFFICIENT_DATA]
            ),
        ];
    }
}
