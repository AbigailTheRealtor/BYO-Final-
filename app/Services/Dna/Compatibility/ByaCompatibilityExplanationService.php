<?php

namespace App\Services\Dna\Compatibility;

/**
 * ByaCompatibilityExplanationService — BYA_EXPLAIN_V1 Deterministic Compatibility Explanation Layer
 *
 * Accepts a BYA_ALIGN_V1 payload (from ByaCompatibilityAlignmentService) and produces a
 * BYA_EXPLAIN_V1 payload that maps each dimension's alignment category to a deterministic,
 * machine-readable explanation type and explanation key.
 *
 * This is a pure key-derivation layer. It produces structured lookup keys only — no
 * human-readable narrative, no scoring, no ranking, no recommendations, no UI.
 *
 * GOVERNANCE CONSTRAINTS:
 * - Deterministic only. No randomness, no model calls, no HTTP calls.
 * - No database reads or writes.
 * - No routes, no controllers, no Blade, no Livewire changes.
 * - No numeric scores, no percentages, no weighted composites.
 * - No ranking logic. Explanation keys are internal system identifiers only.
 * - No recommendation language. This service produces only machine-readable keys.
 *   Explanation keys (e.g. communication_style_partial_alignment) MUST NEVER be
 *   interpreted, labeled, or presented as recommendations, suitability determinations,
 *   hiring suggestions, endorsements, or transaction advice of any kind.
 * - Never throws. All error paths return a structurally valid stub payload.
 * - Output is internal-only. It is never surfaced directly to any public-facing route.
 */
class ByaCompatibilityExplanationService
{
    private const EXPLANATION_VERSION = 'BYA_EXPLAIN_V1';

    /**
     * Maps each BYA_ALIGN_V1 alignment category to its BYA_EXPLAIN_V1 explanation type.
     *
     * This is the authoritative mapping for Milestone 6. It is defined as a named constant
     * so it is auditable and not buried in logic.
     *
     * Any alignment category value not present in this map falls back to 'insufficient_data'.
     */
    private const CATEGORY_TYPE_MAP = [
        'full_alignment'          => 'alignment',
        'partial_alignment'       => 'alignment',
        'incompatible_alignment'  => 'difference',
        'insufficient_data'       => 'insufficient_data',
        'adjacent_compatibility'  => 'adjacent',
        'neutral_compatibility'   => 'neutral',
    ];

    /**
     * The full explanation type vocabulary. All five values are defined here
     * so that the payload shape (including summary count keys) is stable and
     * future-compatible.
     */
    private const ALL_EXPLANATION_TYPES = [
        'alignment',
        'difference',
        'adjacent',
        'neutral',
        'insufficient_data',
    ];

    /**
     * Fallback explanation type emitted when a dimension's alignment category is not
     * recognized (i.e., not present in CATEGORY_TYPE_MAP).
     */
    private const FALLBACK_TYPE = 'insufficient_data';

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Explain a BYA_ALIGN_V1 payload and produce a BYA_EXPLAIN_V1 explanation payload.
     *
     * Accepts the array produced by ByaCompatibilityAlignmentService::categorize().
     *
     * Returns a BYA_EXPLAIN_V1 payload. If the input is not a structurally valid
     * BYA_ALIGN_V1 array (missing `dimensions` key, non-array `dimensions`, non-array
     * input, or null), returns a stub payload with dimensions: [] and all counts 0.
     *
     * DIMENSION PASSTHROUGH DESIGN:
     * This service explains exactly the dimensions present in the input payload —
     * no more, no fewer. It does not pad missing dimensions or enforce that any
     * canonical names are present. Dimension enforcement is the responsibility of
     * upstream layers.
     *
     * Never throws. All exceptions are caught and result in the stub payload.
     *
     * @param  mixed  $alignV1Payload  BYA_ALIGN_V1 payload array.
     * @return array                   BYA_EXPLAIN_V1 payload array.
     */
    public function explain(mixed $alignV1Payload): array
    {
        try {
            if (!$this->isStructurallyValid($alignV1Payload)) {
                return $this->buildStubPayload($alignV1Payload);
            }

            $alignmentVersion = $alignV1Payload['alignment_version'] ?? null;
            $inputDimensions  = $alignV1Payload['dimensions'];

            $dimensions = [];
            foreach ($inputDimensions as $dimensionName => $dimensionEntry) {
                $dimensions[(string) $dimensionName] = $this->explainDimension(
                    (string) $dimensionName,
                    $dimensionEntry
                );
            }

            $summary = $this->buildSummary($dimensions);

            return [
                'explanation_version' => self::EXPLANATION_VERSION,
                'alignment_version'   => $alignmentVersion,
                'dimensions'          => $dimensions,
                'summary'             => $summary,
            ];
        } catch (\Throwable $e) {
            return $this->buildStubPayload($alignV1Payload ?? null);
        }
    }

    // -------------------------------------------------------------------------
    // Dimension explanation
    // -------------------------------------------------------------------------

    /**
     * Explain a single dimension entry from the BYA_ALIGN_V1 payload.
     *
     * Each input dimension entry has the shape:
     *   {relationship, alignment_category, consumer, agent}
     *
     * Each output dimension entry has the shape:
     *   {relationship, alignment_category, explanation_type, explanation_key}
     *
     * If the entry is not an array, returns a fallback entry using insufficient_data.
     */
    private function explainDimension(string $dimensionName, mixed $entry): array
    {
        try {
            if (!is_array($entry)) {
                return $this->buildFallbackDimensionEntry($dimensionName, null);
            }

            $relationship      = isset($entry['relationship']) && (is_string($entry['relationship']) || $entry['relationship'] === null)
                ? $entry['relationship']
                : null;

            $alignmentCategory = isset($entry['alignment_category']) && is_string($entry['alignment_category'])
                ? $entry['alignment_category']
                : self::FALLBACK_TYPE;

            $explanationType = self::CATEGORY_TYPE_MAP[$alignmentCategory] ?? self::FALLBACK_TYPE;
            $explanationKey  = $dimensionName . '_' . $alignmentCategory;

            return [
                'relationship'       => $relationship,
                'alignment_category' => $alignmentCategory,
                'explanation_type'   => $explanationType,
                'explanation_key'    => $explanationKey,
            ];
        } catch (\Throwable $e) {
            return $this->buildFallbackDimensionEntry($dimensionName, null);
        }
    }

    /**
     * Build a fallback dimension entry used when a dimension entry is malformed
     * or an exception is caught during dimension explanation.
     */
    private function buildFallbackDimensionEntry(string $dimensionName, mixed $relationship): array
    {
        return [
            'relationship'       => $relationship,
            'alignment_category' => self::FALLBACK_TYPE,
            'explanation_type'   => self::FALLBACK_TYPE,
            'explanation_key'    => $dimensionName . '_' . self::FALLBACK_TYPE,
        ];
    }

    // -------------------------------------------------------------------------
    // Summary
    // -------------------------------------------------------------------------

    /**
     * Build the summary block from the explained dimensions array.
     *
     * Counts total_dimensions, explained_dimensions (those whose alignment_category
     * is not 'insufficient_data'), and per-type counts across all five explanation
     * type buckets. All counts are initialized to 0 so the shape is stable regardless
     * of input content.
     */
    private function buildSummary(array $dimensions): array
    {
        $typeCounts = array_fill_keys(self::ALL_EXPLANATION_TYPES, 0);

        $totalDimensions     = count($dimensions);
        $explainedDimensions = 0;

        foreach ($dimensions as $entry) {
            $alignmentCategory = $entry['alignment_category'] ?? self::FALLBACK_TYPE;
            $explanationType   = $entry['explanation_type']   ?? self::FALLBACK_TYPE;

            if ($alignmentCategory !== 'insufficient_data') {
                $explainedDimensions++;
            }

            if (isset($typeCounts[$explanationType])) {
                $typeCounts[$explanationType]++;
            } else {
                $typeCounts[self::FALLBACK_TYPE]++;
            }
        }

        return [
            'total_dimensions'      => $totalDimensions,
            'explained_dimensions'  => $explainedDimensions,
            'explanation_type_counts' => $typeCounts,
        ];
    }

    // -------------------------------------------------------------------------
    // Structural validity
    // -------------------------------------------------------------------------

    /**
     * Determine whether the input is a structurally valid BYA_ALIGN_V1 payload.
     *
     * A payload is structurally valid when it is a non-empty array that contains
     * a `dimensions` key whose value is an array (including an empty array, which
     * represents a valid stub payload from the alignment layer).
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
     * BYA_ALIGN_V1 array, or when an unrecoverable exception escapes the main loop.
     *
     * dimensions: [] signals to callers that no explanation was possible.
     * All explanation type counts are 0. alignment_version is forwarded from the
     * input when available, otherwise null.
     */
    private function buildStubPayload(mixed $payload): array
    {
        $alignmentVersion = (is_array($payload) && isset($payload['alignment_version']))
            ? $payload['alignment_version']
            : null;

        return [
            'explanation_version' => self::EXPLANATION_VERSION,
            'alignment_version'   => $alignmentVersion,
            'dimensions'          => [],
            'summary'             => [
                'total_dimensions'        => 0,
                'explained_dimensions'    => 0,
                'explanation_type_counts' => array_fill_keys(self::ALL_EXPLANATION_TYPES, 0),
            ],
        ];
    }
}
