<?php

namespace App\Services\Dna\Compatibility;

/**
 * ByaCompatibilityReportService — BYA_REPORT_V1 Internal Compatibility Report Builder
 *
 * Accepts the three upstream BYA payloads (BYA_ALIGN_V1, BYA_EXPLAIN_V1, BYA_NARRATIVE_V1)
 * and assembles them into a single structured BYA_REPORT_V1 payload for internal audit
 * and admin/legal review.
 *
 * This is a pure internal assembly layer. No UI, no routes, no database access, no AI.
 *
 * GOVERNANCE CONSTRAINTS (Milestone 9):
 * - Deterministic only. No randomness, no model calls, no HTTP calls, no AI.
 * - No database reads or writes.
 * - No routes, no controllers, no Blade, no Livewire changes.
 * - No numeric scores, no percentages, no ranking or recommendation language.
 * - No endorsement, disqualification, or prediction language.
 * - Never throws. All Throwable paths are caught and return the stub payload.
 * - Missing fields return null — no values are invented.
 * - Output is internal-only. Never surfaced to any public-facing or consumer-facing route.
 * - Same inputs always produce identical outputs (deterministic).
 */
class ByaCompatibilityReportService
{
    private const REPORT_VERSION = 'BYA_REPORT_V1';

    /**
     * The six alignment category keys used in BYA_ALIGN_V1.
     * Used to zero-fill the alignment_category_counts block when the upstream
     * align summary is absent or malformed.
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
     * The five explanation type keys used in BYA_EXPLAIN_V1.
     * Used to zero-fill the explanation_type_counts block when the upstream
     * explain summary is absent or malformed.
     */
    private const ALL_EXPLANATION_TYPES = [
        'alignment',
        'difference',
        'adjacent',
        'neutral',
        'insufficient_data',
    ];

    /**
     * The five narrative type keys used in BYA_NARRATIVE_V1.
     * Used to zero-fill the narrative_type_counts block when the upstream
     * narrative summary is absent or malformed.
     */
    private const ALL_NARRATIVE_TYPES = [
        'alignment',
        'difference',
        'adjacent',
        'neutral',
        'insufficient_data',
    ];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Assemble a BYA_REPORT_V1 payload from the three upstream BYA payloads.
     *
     * Accepts:
     *   $alignV1Payload     — array produced by ByaCompatibilityAlignmentService::categorize()
     *   $explainV1Payload   — array produced by ByaCompatibilityExplanationService::explain()
     *   $narrativeV1Payload — array produced by ByaCompatibilityNarrativeService::generate()
     *
     * The narrative payload's dimensions array is the source of truth for iteration.
     * Returns a stub payload (dimensions: [], all counts zero-filled) if the narrative
     * payload is not structurally valid.
     *
     * Never throws. All exceptions are caught and result in the stub payload.
     * Missing fields in any upstream payload return null — no values are invented.
     *
     * @param  mixed  $alignV1Payload     BYA_ALIGN_V1 payload array.
     * @param  mixed  $explainV1Payload   BYA_EXPLAIN_V1 payload array.
     * @param  mixed  $narrativeV1Payload BYA_NARRATIVE_V1 payload array.
     * @return array                      BYA_REPORT_V1 payload array.
     */
    public function generate(mixed $alignV1Payload, mixed $explainV1Payload, mixed $narrativeV1Payload): array
    {
        try {
            if (!$this->isNarrativePayloadValid($narrativeV1Payload)) {
                return $this->buildStubPayload($alignV1Payload, $explainV1Payload, $narrativeV1Payload);
            }

            $alignmentVersion   = is_array($alignV1Payload)    ? ($alignV1Payload['alignment_version']    ?? null) : null;
            $explanationVersion = is_array($explainV1Payload)   ? ($explainV1Payload['explanation_version'] ?? null) : null;
            $narrativeVersion   = $narrativeV1Payload['narrative_version'] ?? null;

            $narrativeDimensions = $narrativeV1Payload['dimensions'];
            $alignDimensions     = $this->extractDimensions($alignV1Payload);
            $explainDimensions   = $this->extractDimensions($explainV1Payload);

            $dimensions = [];
            foreach ($narrativeDimensions as $dimensionName => $narrativeEntry) {
                $alignEntry  = $alignDimensions[(string) $dimensionName]  ?? [];
                $explainEntry = $explainDimensions[(string) $dimensionName] ?? [];
                $dimensions[(string) $dimensionName] = $this->mergeDimension(
                    is_array($alignEntry)   ? $alignEntry   : [],
                    is_array($explainEntry) ? $explainEntry : [],
                    is_array($narrativeEntry) ? $narrativeEntry : []
                );
            }

            $summary = $this->buildSummary($alignV1Payload, $explainV1Payload, $narrativeV1Payload);
            $audit   = $this->buildAudit($alignmentVersion, $explanationVersion, $narrativeVersion, $dimensions);

            return [
                'report_version'      => self::REPORT_VERSION,
                'alignment_version'   => $alignmentVersion,
                'explanation_version' => $explanationVersion,
                'narrative_version'   => $narrativeVersion,
                'dimensions'          => $dimensions,
                'summary'             => $summary,
                'audit'               => $audit,
            ];
        } catch (\Throwable $e) {
            return $this->buildStubPayload(
                $alignV1Payload     ?? null,
                $explainV1Payload   ?? null,
                $narrativeV1Payload ?? null
            );
        }
    }

    // -------------------------------------------------------------------------
    // Dimension merging
    // -------------------------------------------------------------------------

    /**
     * Merge a single dimension's data from all three upstream payloads.
     *
     * Source of each field:
     *   relationship       — align payload dimension entry
     *   alignment_category — align payload dimension entry
     *   explanation_type   — explain payload dimension entry
     *   explanation_key    — explain payload dimension entry
     *   template_id        — narrative payload dimension entry
     *   sentence           — narrative payload dimension entry
     *
     * Returns null for any field not present in its source payload.
     * No values are invented.
     */
    private function mergeDimension(array $alignEntry, array $explainEntry, array $narrativeEntry): array
    {
        return [
            'relationship'       => $alignEntry['relationship']       ?? null,
            'alignment_category' => $alignEntry['alignment_category'] ?? null,
            'explanation_type'   => $explainEntry['explanation_type']  ?? null,
            'explanation_key'    => $explainEntry['explanation_key']   ?? null,
            'template_id'        => $narrativeEntry['template_id']     ?? null,
            'sentence'           => $narrativeEntry['sentence']        ?? null,
        ];
    }

    // -------------------------------------------------------------------------
    // Summary block
    // -------------------------------------------------------------------------

    /**
     * Build the summary block from the three upstream payload summaries.
     *
     * alignment_category_counts — copied from the align payload summary.
     * explanation_type_counts   — copied from the explain payload summary.
     * narrative_type_counts     — copied from the narrative payload summary.
     * summary_sentence          — copied from the narrative payload summary.
     *
     * If any counts block is absent or not an array, returns the canonical
     * zero-filled array for that block using the appropriate type keys.
     */
    private function buildSummary(mixed $alignPayload, mixed $explainPayload, mixed $narrativePayload): array
    {
        $alignSummary     = is_array($alignPayload)     ? ($alignPayload['summary']     ?? []) : [];
        $explainSummary   = is_array($explainPayload)   ? ($explainPayload['summary']   ?? []) : [];
        $narrativeSummary = is_array($narrativePayload) ? ($narrativePayload['summary'] ?? []) : [];

        $alignmentCategoryCounts = (
            isset($alignSummary['alignment_category_counts'])
            && is_array($alignSummary['alignment_category_counts'])
        )
            ? $alignSummary['alignment_category_counts']
            : array_fill_keys(self::ALL_ALIGNMENT_CATEGORIES, 0);

        $explanationTypeCounts = (
            isset($explainSummary['explanation_type_counts'])
            && is_array($explainSummary['explanation_type_counts'])
        )
            ? $explainSummary['explanation_type_counts']
            : array_fill_keys(self::ALL_EXPLANATION_TYPES, 0);

        $narrativeTypeCounts = (
            isset($narrativeSummary['narrative_type_counts'])
            && is_array($narrativeSummary['narrative_type_counts'])
        )
            ? $narrativeSummary['narrative_type_counts']
            : array_fill_keys(self::ALL_NARRATIVE_TYPES, 0);

        $summarySentence = array_key_exists('summary_sentence', $narrativeSummary)
            ? $narrativeSummary['summary_sentence']
            : null;

        return [
            'alignment_category_counts' => $alignmentCategoryCounts,
            'explanation_type_counts'   => $explanationTypeCounts,
            'narrative_type_counts'     => $narrativeTypeCounts,
            'summary_sentence'          => $summarySentence,
        ];
    }

    // -------------------------------------------------------------------------
    // Audit block
    // -------------------------------------------------------------------------

    /**
     * Build the audit block containing source version identifiers and trace keys.
     *
     * source_versions — forwarded directly from each upstream payload (null if absent).
     * trace_keys      — one entry per dimension with alignment_category, explanation_key,
     *                   and template_id, assembled from the merged dimensions array.
     *
     * No database persistence. Audit is in-memory only.
     */
    private function buildAudit(
        ?string $alignmentVersion,
        ?string $explanationVersion,
        ?string $narrativeVersion,
        array   $dimensions
    ): array {
        $traceKeys = [];
        foreach ($dimensions as $dimensionName => $entry) {
            $traceKeys[(string) $dimensionName] = [
                'alignment_category' => $entry['alignment_category'] ?? null,
                'explanation_key'    => $entry['explanation_key']    ?? null,
                'template_id'        => $entry['template_id']        ?? null,
            ];
        }

        return [
            'source_versions' => [
                'alignment_version'   => $alignmentVersion,
                'explanation_version' => $explanationVersion,
                'narrative_version'   => $narrativeVersion,
            ],
            'trace_keys' => $traceKeys,
        ];
    }

    // -------------------------------------------------------------------------
    // Structural validity
    // -------------------------------------------------------------------------

    /**
     * Determine whether the BYA_NARRATIVE_V1 input is structurally valid.
     *
     * A payload is structurally valid when it is a non-empty array that contains
     * a `dimensions` key whose value is an array (including an empty array, which
     * represents a valid stub from the narrative layer).
     */
    private function isNarrativePayloadValid(mixed $payload): bool
    {
        return is_array($payload)
            && !empty($payload)
            && array_key_exists('dimensions', $payload)
            && is_array($payload['dimensions']);
    }

    // -------------------------------------------------------------------------
    // Input helpers
    // -------------------------------------------------------------------------

    /**
     * Safely extract the dimensions array from any upstream payload.
     * Returns an empty array when the payload is absent or malformed.
     */
    private function extractDimensions(mixed $payload): array
    {
        if (!is_array($payload)) {
            return [];
        }

        $dims = $payload['dimensions'] ?? null;

        return is_array($dims) ? $dims : [];
    }

    // -------------------------------------------------------------------------
    // Stub payload
    // -------------------------------------------------------------------------

    /**
     * Build the stub payload returned when the narrative input is not structurally
     * valid, or when an unrecoverable exception escapes the main loop.
     *
     * dimensions: [] signals to callers that no report was possible.
     * All count blocks are zero-filled with canonical keys.
     * summary_sentence is null.
     * audit.trace_keys is [].
     * Version strings are forwarded from their respective payloads when available.
     */
    private function buildStubPayload(mixed $alignPayload, mixed $explainPayload, mixed $narrativePayload): array
    {
        $alignmentVersion   = is_array($alignPayload)     ? ($alignPayload['alignment_version']    ?? null) : null;
        $explanationVersion = is_array($explainPayload)   ? ($explainPayload['explanation_version'] ?? null) : null;
        $narrativeVersion   = is_array($narrativePayload) ? ($narrativePayload['narrative_version']  ?? null) : null;

        return [
            'report_version'      => self::REPORT_VERSION,
            'alignment_version'   => $alignmentVersion,
            'explanation_version' => $explanationVersion,
            'narrative_version'   => $narrativeVersion,
            'dimensions'          => [],
            'summary'             => [
                'alignment_category_counts' => array_fill_keys(self::ALL_ALIGNMENT_CATEGORIES, 0),
                'explanation_type_counts'   => array_fill_keys(self::ALL_EXPLANATION_TYPES, 0),
                'narrative_type_counts'     => array_fill_keys(self::ALL_NARRATIVE_TYPES, 0),
                'summary_sentence'          => null,
            ],
            'audit' => [
                'source_versions' => [
                    'alignment_version'   => $alignmentVersion,
                    'explanation_version' => $explanationVersion,
                    'narrative_version'   => $narrativeVersion,
                ],
                'trace_keys' => [],
            ],
        ];
    }
}
