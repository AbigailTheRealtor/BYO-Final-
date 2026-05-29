<?php

namespace App\Services\Dna\Compatibility;

/**
 * ByaCompatibilityNarrativeService — BYA_NARRATIVE_V1 Deterministic Template Service
 *
 * Accepts a BYA_EXPLAIN_V1 payload (from ByaCompatibilityExplanationService) and the
 * BYA_ALIGN_V1 payload (from ByaCompatibilityAlignmentService) and produces a
 * BYA_NARRATIVE_V1 payload containing per-dimension plain-language sentences and a
 * summary — all derived exclusively from pre-approved templates with no AI, no
 * database access, and no UI.
 *
 * GOVERNANCE CONSTRAINTS (Milestone 8):
 * - Deterministic only. No randomness, no model calls, no HTTP calls, no AI.
 * - No database reads or writes.
 * - No routes, no controllers, no Blade, no Livewire changes.
 * - No numeric scores, no percentages, no ranking or recommendation language.
 * - No endorsement, disqualification, or prediction language.
 * - No protected-class, demographic, or externally sourced inputs.
 * - Templates stored in code only — not loaded from DB, config, files, or remote services.
 * - Unknown explanation_key values fall back to the insufficient_data template; never throws.
 * - adjacent and neutral explanation types fall back to insufficient_data templates.
 * - Same inputs always produce identical outputs (deterministic).
 * - Output is internal-only. It is never surfaced directly to any public-facing route.
 * - No consumer-facing display at Milestone 8.
 */
class ByaCompatibilityNarrativeService
{
    private const NARRATIVE_VERSION = 'BYA_NARRATIVE_V1';

    /**
     * All five explanation type buckets from BYA_EXPLAIN_V1.
     * Used to initialise per-type counts in the summary block.
     */
    private const ALL_NARRATIVE_TYPES = [
        'alignment',
        'difference',
        'adjacent',
        'neutral',
        'insufficient_data',
    ];

    /**
     * The explanation types that fall back to the insufficient_data template.
     *
     * adjacent and neutral are not yet reachable in current milestones; per
     * governance (Section 8.4) they must use the insufficient_data fallback
     * until approved templates are authored for those types.
     */
    private const FALLBACK_TO_INSUFFICIENT_DATA_TYPES = [
        'adjacent',
        'neutral',
        'insufficient_data',
    ];

    /**
     * Generic approved fallback sentence used only when both the primary template
     * and the dimension's insufficient_data template are absent from TEMPLATE_MAP.
     * This sentence is governance-compliant and makes no claim about either party.
     */
    private const GENERIC_FALLBACK_SENTENCE =
        'No comparison is available for this dimension. One or both parties did not provide a response.';

    // -------------------------------------------------------------------------
    // Approved template map — BYA_NARRATIVE_V1 Milestone 8 template library
    //
    // 32 templates total:
    //   10 × full_alignment
    //   10 × incompatible_alignment
    //   12 × insufficient_data
    //
    // Each template uses only {consumer_value} and {agent_value} as placeholders.
    // All template text complies with Sections 3–6 of the governance document:
    //   - Factual observations only
    //   - No hire / ranking / endorsement / disqualification / prediction language
    //   - No numeric scores or protected-class references
    //   - Advisory-only standard (Section 6)
    // -------------------------------------------------------------------------

    private const TEMPLATE_MAP = [

        // ------------------------------------------------------------------
        // communication_style — full_alignment
        // ------------------------------------------------------------------
        'communication_style_full_alignment' =>
            'You indicated a preference for {consumer_value} as your primary communication channel; '
            . 'this agent describes their standard approach as {agent_value}. '
            . 'Your communication style preferences appear to be well aligned on this dimension.',

        // ------------------------------------------------------------------
        // communication_style — incompatible_alignment
        // ------------------------------------------------------------------
        'communication_style_incompatible_alignment' =>
            'You indicated a preference for {consumer_value} as your primary communication channel; '
            . 'this agent describes their standard approach as {agent_value}. '
            . 'A difference was found on this dimension.',

        // ------------------------------------------------------------------
        // communication_style — insufficient_data
        // ------------------------------------------------------------------
        'communication_style_insufficient_data' =>
            'No comparison is available for communication style. '
            . 'One or both parties did not provide a response for this dimension.',

        // ------------------------------------------------------------------
        // communication_frequency — full_alignment
        // ------------------------------------------------------------------
        'communication_frequency_full_alignment' =>
            'You indicated a preference for {consumer_value} contact; '
            . 'this agent describes their standard practice as {agent_value}. '
            . 'Your communication frequency expectations appear to be well aligned.',

        // ------------------------------------------------------------------
        // communication_frequency — incompatible_alignment
        // ------------------------------------------------------------------
        'communication_frequency_incompatible_alignment' =>
            'You indicated a preference for {consumer_value} contact; '
            . 'this agent describes their standard practice as {agent_value}. '
            . 'A difference was found on this dimension.',

        // ------------------------------------------------------------------
        // communication_frequency — insufficient_data
        // ------------------------------------------------------------------
        'communication_frequency_insufficient_data' =>
            'No comparison is available for communication frequency. '
            . 'One or both parties did not provide a response for this dimension.',

        // ------------------------------------------------------------------
        // decision_speed — full_alignment
        // ------------------------------------------------------------------
        'decision_speed_full_alignment' =>
            'You indicated a {consumer_value} transaction pace; '
            . 'this agent describes their approach as {agent_value}. '
            . 'Your expectations regarding decision speed appear to be well aligned.',

        // ------------------------------------------------------------------
        // decision_speed — incompatible_alignment
        // ------------------------------------------------------------------
        'decision_speed_incompatible_alignment' =>
            'You indicated a {consumer_value} transaction pace; '
            . 'this agent describes their approach as {agent_value}. '
            . 'A difference was found on this dimension.',

        // ------------------------------------------------------------------
        // decision_speed — insufficient_data
        // ------------------------------------------------------------------
        'decision_speed_insufficient_data' =>
            'No comparison is available for decision speed. '
            . 'One or both parties did not provide a response for this dimension.',

        // ------------------------------------------------------------------
        // risk_tolerance — full_alignment
        // ------------------------------------------------------------------
        'risk_tolerance_full_alignment' =>
            'You described your risk tolerance as {consumer_value}; '
            . 'this agent describes their professional approach as {agent_value}. '
            . 'Your risk tolerance appears to be well aligned with this agent\'s described approach.',

        // ------------------------------------------------------------------
        // risk_tolerance — incompatible_alignment
        // ------------------------------------------------------------------
        'risk_tolerance_incompatible_alignment' =>
            'You described your risk tolerance as {consumer_value}; '
            . 'this agent describes their professional approach as {agent_value}. '
            . 'A difference was found on this dimension.',

        // ------------------------------------------------------------------
        // risk_tolerance — insufficient_data
        // ------------------------------------------------------------------
        'risk_tolerance_insufficient_data' =>
            'No comparison is available for risk tolerance. '
            . 'One or both parties did not provide a response for this dimension.',

        // ------------------------------------------------------------------
        // negotiation_style — full_alignment
        // ------------------------------------------------------------------
        'negotiation_style_full_alignment' =>
            'You described your negotiation style as {consumer_value}; '
            . 'this agent describes their approach as {agent_value}. '
            . 'Your negotiation style preferences appear to be well aligned.',

        // ------------------------------------------------------------------
        // negotiation_style — incompatible_alignment
        // ------------------------------------------------------------------
        'negotiation_style_incompatible_alignment' =>
            'You described your negotiation style as {consumer_value}; '
            . 'this agent describes their approach as {agent_value}. '
            . 'A difference was found on this dimension.',

        // ------------------------------------------------------------------
        // negotiation_style — insufficient_data
        // ------------------------------------------------------------------
        'negotiation_style_insufficient_data' =>
            'No comparison is available for negotiation style. '
            . 'One or both parties did not provide a response for this dimension.',

        // ------------------------------------------------------------------
        // advisor_expectation — full_alignment
        // ------------------------------------------------------------------
        'advisor_expectation_full_alignment' =>
            'You indicated you are looking for {consumer_value} guidance; '
            . 'this agent describes their standard level of involvement as {agent_value}. '
            . 'Your advisor expectation appears to be well aligned with this agent\'s described approach.',

        // ------------------------------------------------------------------
        // advisor_expectation — incompatible_alignment
        // ------------------------------------------------------------------
        'advisor_expectation_incompatible_alignment' =>
            'You indicated you are looking for {consumer_value} guidance; '
            . 'this agent describes their standard level of involvement as {agent_value}. '
            . 'A difference was found on this dimension.',

        // ------------------------------------------------------------------
        // advisor_expectation — insufficient_data
        // ------------------------------------------------------------------
        'advisor_expectation_insufficient_data' =>
            'No comparison is available for advisor expectation. '
            . 'One or both parties did not provide a response for this dimension.',

        // ------------------------------------------------------------------
        // technology_preference — insufficient_data (placeholder dimension;
        // always produces insufficient_data in current milestones)
        // ------------------------------------------------------------------
        'technology_preference_insufficient_data' =>
            'This dimension is not yet supported and is not included in this compatibility summary.',

        // ------------------------------------------------------------------
        // market_education_preference — insufficient_data (placeholder dimension;
        // always produces insufficient_data in current milestones)
        // ------------------------------------------------------------------
        'market_education_preference_insufficient_data' =>
            'This dimension is not yet supported and is not included in this compatibility summary.',

        // ------------------------------------------------------------------
        // property_search_involvement — full_alignment
        // ------------------------------------------------------------------
        'property_search_involvement_full_alignment' =>
            'You described your preferred collaboration style as {consumer_value}; '
            . 'this agent describes their working style as {agent_value}. '
            . 'Your property search involvement preferences appear to be well aligned.',

        // ------------------------------------------------------------------
        // property_search_involvement — incompatible_alignment
        // ------------------------------------------------------------------
        'property_search_involvement_incompatible_alignment' =>
            'You described your preferred collaboration style as {consumer_value}; '
            . 'this agent describes their working style as {agent_value}. '
            . 'A difference was found on this dimension.',

        // ------------------------------------------------------------------
        // property_search_involvement — insufficient_data
        // ------------------------------------------------------------------
        'property_search_involvement_insufficient_data' =>
            'No comparison is available for property search involvement. '
            . 'One or both parties did not provide a response for this dimension.',

        // ------------------------------------------------------------------
        // transaction_guidance_level — full_alignment
        // ------------------------------------------------------------------
        'transaction_guidance_level_full_alignment' =>
            'You described your decision-making approach as {consumer_value}; '
            . 'this agent describes how they support client decisions as {agent_value}. '
            . 'Your transaction guidance level preferences appear to be well aligned.',

        // ------------------------------------------------------------------
        // transaction_guidance_level — incompatible_alignment
        // ------------------------------------------------------------------
        'transaction_guidance_level_incompatible_alignment' =>
            'You described your decision-making approach as {consumer_value}; '
            . 'this agent describes how they support client decisions as {agent_value}. '
            . 'A difference was found on this dimension.',

        // ------------------------------------------------------------------
        // transaction_guidance_level — insufficient_data
        // ------------------------------------------------------------------
        'transaction_guidance_level_insufficient_data' =>
            'No comparison is available for transaction guidance level. '
            . 'One or both parties did not provide a response for this dimension.',

        // ------------------------------------------------------------------
        // availability_expectation — full_alignment
        // ------------------------------------------------------------------
        'availability_expectation_full_alignment' =>
            'You indicated an availability expectation of {consumer_value}; '
            . 'this agent describes their standard responsiveness as {agent_value}. '
            . 'Your availability expectations appear to be well aligned.',

        // ------------------------------------------------------------------
        // availability_expectation — incompatible_alignment
        // ------------------------------------------------------------------
        'availability_expectation_incompatible_alignment' =>
            'You indicated an availability expectation of {consumer_value}; '
            . 'this agent describes their standard responsiveness as {agent_value}. '
            . 'A difference was found on this dimension.',

        // ------------------------------------------------------------------
        // availability_expectation — insufficient_data
        // ------------------------------------------------------------------
        'availability_expectation_insufficient_data' =>
            'No comparison is available for availability expectation. '
            . 'One or both parties did not provide a response for this dimension.',

        // ------------------------------------------------------------------
        // personality_style — full_alignment
        // ------------------------------------------------------------------
        'personality_style_full_alignment' =>
            'You described your representation philosophy as {consumer_value}; '
            . 'this agent describes their professional approach as {agent_value}. '
            . 'Your working style and representation philosophy appear to be well aligned.',

        // ------------------------------------------------------------------
        // personality_style — incompatible_alignment
        // ------------------------------------------------------------------
        'personality_style_incompatible_alignment' =>
            'You described your representation philosophy as {consumer_value}; '
            . 'this agent describes their professional approach as {agent_value}. '
            . 'A difference was found on this dimension.',

        // ------------------------------------------------------------------
        // personality_style — insufficient_data
        // ------------------------------------------------------------------
        'personality_style_insufficient_data' =>
            'No comparison is available for representation philosophy. '
            . 'One or both parties did not provide a response for this dimension.',
    ];

    /**
     * Approved summary sentence templates keyed on the dominant pattern derived
     * from per-type narrative counts.
     *
     * All sentences comply with Section 3.6 and Section 6: advisory observations
     * only, no recommendation, no ranking, no hire/endorse/disqualify language.
     */
    private const SUMMARY_TEMPLATE_MAP = [
        'all_alignment' =>
            'Across the dimensions where both you and this agent provided responses, '
            . 'your working-style preferences appear to be well aligned.',

        'mostly_alignment' =>
            'Across the dimensions where both you and this agent provided responses, '
            . 'several areas of alignment were found, '
            . 'and one or more areas of difference were also identified.',

        'mostly_difference' =>
            'Across the dimensions where both you and this agent provided responses, '
            . 'several areas of difference were identified.',

        'all_difference' =>
            'Across the dimensions where both you and this agent provided responses, '
            . 'differences were found on each dimension.',

        'mixed' =>
            'Across the dimensions where both you and this agent provided responses, '
            . 'a mix of aligned areas and differences was found.',

        'all_insufficient' =>
            'No working-style comparison was possible because one or both parties did not '
            . 'provide sufficient preference information for any dimension.',

        'no_dimensions' =>
            'No dimensions were included in this comparison. No working-style comparison is available.',
    ];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Generate a BYA_NARRATIVE_V1 payload from a BYA_EXPLAIN_V1 payload and the
     * corresponding BYA_ALIGN_V1 payload.
     *
     * Accepts:
     *   $explainV1Payload — array produced by ByaCompatibilityExplanationService::explain()
     *   $alignV1Payload   — array produced by ByaCompatibilityAlignmentService::categorize()
     *
     * Returns a BYA_NARRATIVE_V1 payload. If either input is not structurally valid,
     * returns a stub payload with dimensions: [] and all counts 0.
     *
     * DIMENSION PASSTHROUGH DESIGN:
     * This service processes exactly the dimensions present in the BYA_EXPLAIN_V1 payload.
     * For each dimension it reads the explanation_key and explanation_type from BYA_EXPLAIN_V1,
     * and reads the consumer and agent values from the corresponding dimension in BYA_ALIGN_V1.
     *
     * Never throws. All exceptions are caught and result in the stub payload.
     *
     * @param  mixed  $explainV1Payload  BYA_EXPLAIN_V1 payload array.
     * @param  mixed  $alignV1Payload    BYA_ALIGN_V1 payload array.
     * @return array                     BYA_NARRATIVE_V1 payload array.
     */
    public function generate(mixed $explainV1Payload, mixed $alignV1Payload): array
    {
        try {
            if (!$this->isExplainPayloadValid($explainV1Payload)) {
                return $this->buildStubPayload($explainV1Payload);
            }

            $explanationVersion = $explainV1Payload['explanation_version'] ?? null;
            $alignmentVersion   = $explainV1Payload['alignment_version']   ?? null;
            $inputDimensions    = $explainV1Payload['dimensions'];

            $alignDimensions = $this->extractAlignDimensions($alignV1Payload);

            $dimensions = [];
            foreach ($inputDimensions as $dimensionName => $explainEntry) {
                $alignEntry = $alignDimensions[(string) $dimensionName] ?? [];
                $dimensions[(string) $dimensionName] = $this->generateDimensionNarrative(
                    (string) $dimensionName,
                    $explainEntry,
                    $alignEntry
                );
            }

            $summary = $this->buildSummary($dimensions);

            return [
                'narrative_version'   => self::NARRATIVE_VERSION,
                'explanation_version' => $explanationVersion,
                'alignment_version'   => $alignmentVersion,
                'dimensions'          => $dimensions,
                'summary'             => $summary,
            ];
        } catch (\Throwable $e) {
            return $this->buildStubPayload($explainV1Payload ?? null);
        }
    }

    // -------------------------------------------------------------------------
    // Per-dimension narrative generation
    // -------------------------------------------------------------------------

    /**
     * Generate the narrative entry for a single dimension.
     *
     * Reads the explanation_key and explanation_type from the BYA_EXPLAIN_V1 dimension
     * entry, and reads the consumer and agent values from the BYA_ALIGN_V1 dimension entry.
     *
     * Template selection rules:
     *   1. If explanation_type is 'adjacent' or 'neutral', fall back to the
     *      dimension's insufficient_data template (per Section 8.4 governance rule).
     *   2. Otherwise, look up explanation_key in TEMPLATE_MAP.
     *   3. If not found, fall back to {dimension_name}_insufficient_data.
     *   4. If that also fails, use GENERIC_FALLBACK_SENTENCE.
     */
    private function generateDimensionNarrative(
        string $dimensionName,
        mixed  $explainEntry,
        mixed  $alignEntry
    ): array {
        try {
            if (!is_array($explainEntry)) {
                return $this->buildFallbackDimensionEntry($dimensionName, null, null, null, null);
            }

            $explanationKey  = isset($explainEntry['explanation_key'])  && is_string($explainEntry['explanation_key'])
                ? $explainEntry['explanation_key']
                : ($dimensionName . '_insufficient_data');

            $explanationType = isset($explainEntry['explanation_type']) && is_string($explainEntry['explanation_type'])
                ? $explainEntry['explanation_type']
                : 'insufficient_data';

            $consumerValue = is_array($alignEntry) ? ($alignEntry['consumer'] ?? null) : null;
            $agentValue    = is_array($alignEntry) ? ($alignEntry['agent']    ?? null) : null;

            [$templateId, $sentence] = $this->resolveTemplate(
                $dimensionName,
                $explanationKey,
                $explanationType,
                $consumerValue,
                $agentValue
            );

            return [
                'explanation_key' => $explanationKey,
                'narrative_type'  => $explanationType,
                'template_id'     => $templateId,
                'sentence'        => $sentence,
            ];
        } catch (\Throwable $e) {
            return $this->buildFallbackDimensionEntry($dimensionName, null, null, null, null);
        }
    }

    /**
     * Resolve the template id and rendered sentence for a dimension.
     *
     * Returns [template_id, sentence].
     *
     * Fallback priority:
     *   1. If explanation_type is in FALLBACK_TO_INSUFFICIENT_DATA_TYPES,
     *      use {dimension_name}_insufficient_data as the lookup key.
     *   2. Otherwise look up explanation_key directly in TEMPLATE_MAP.
     *   3. If not found, try {dimension_name}_insufficient_data.
     *   4. If still not found, return the generic approved fallback sentence.
     */
    private function resolveTemplate(
        string $dimensionName,
        string $explanationKey,
        string $explanationType,
        mixed  $consumerValue,
        mixed  $agentValue
    ): array {
        $insufficientDataKey = $dimensionName . '_insufficient_data';

        if (in_array($explanationType, self::FALLBACK_TO_INSUFFICIENT_DATA_TYPES, true)) {
            $templateKey = $insufficientDataKey;
        } else {
            $templateKey = $explanationKey;
        }

        if (isset(self::TEMPLATE_MAP[$templateKey])) {
            return [
                $templateKey,
                $this->fillPlaceholders(self::TEMPLATE_MAP[$templateKey], $consumerValue, $agentValue),
            ];
        }

        if ($templateKey !== $insufficientDataKey && isset(self::TEMPLATE_MAP[$insufficientDataKey])) {
            return [
                $insufficientDataKey,
                $this->fillPlaceholders(self::TEMPLATE_MAP[$insufficientDataKey], $consumerValue, $agentValue),
            ];
        }

        return ['generic_insufficient_data_fallback', self::GENERIC_FALLBACK_SENTENCE];
    }

    /**
     * Fill {consumer_value} and {agent_value} placeholders in a template string.
     *
     * Null values are rendered as an empty string so the sentence remains structurally
     * valid even when a party did not provide a value for the dimension.
     */
    private function fillPlaceholders(string $template, mixed $consumerValue, mixed $agentValue): string
    {
        $consumer = ($consumerValue !== null) ? (string) $consumerValue : '';
        $agent    = ($agentValue    !== null) ? (string) $agentValue    : '';

        return str_replace(
            ['{consumer_value}', '{agent_value}'],
            [$consumer, $agent],
            $template
        );
    }

    /**
     * Build the fallback dimension entry used when a dimension entry is malformed
     * or an exception is caught during dimension narrative generation.
     */
    private function buildFallbackDimensionEntry(
        string  $dimensionName,
        ?string $explanationKey,
        ?string $narrativeType,
        ?string $templateId,
        ?string $sentence
    ): array {
        $insufficientDataKey = $dimensionName . '_insufficient_data';
        $resolvedTemplateId  = $templateId ?? $insufficientDataKey;
        $resolvedSentence    = $sentence   ?? (
            isset(self::TEMPLATE_MAP[$insufficientDataKey])
                ? $this->fillPlaceholders(self::TEMPLATE_MAP[$insufficientDataKey], null, null)
                : self::GENERIC_FALLBACK_SENTENCE
        );

        return [
            'explanation_key' => $explanationKey ?? $insufficientDataKey,
            'narrative_type'  => $narrativeType  ?? 'insufficient_data',
            'template_id'     => $resolvedTemplateId,
            'sentence'        => $resolvedSentence,
        ];
    }

    // -------------------------------------------------------------------------
    // Summary
    // -------------------------------------------------------------------------

    /**
     * Build the summary block from the generated dimensions array.
     *
     * Counts per-type narrative entries and selects a summary sentence from
     * SUMMARY_TEMPLATE_MAP based on the dominant explanation-type pattern.
     *
     * All counts are initialised to 0 so the shape is stable regardless of input.
     * The summary sentence complies with Section 3.6 and Section 6: advisory
     * observations only, no recommendation, no ranking language.
     */
    private function buildSummary(array $dimensions): array
    {
        $typeCounts = array_fill_keys(self::ALL_NARRATIVE_TYPES, 0);
        $totalDimensions = count($dimensions);

        foreach ($dimensions as $entry) {
            $narrativeType = $entry['narrative_type'] ?? 'insufficient_data';
            if (isset($typeCounts[$narrativeType])) {
                $typeCounts[$narrativeType]++;
            } else {
                $typeCounts['insufficient_data']++;
            }
        }

        $summarySentence = $this->deriveSummarySentence($typeCounts, $totalDimensions);

        return [
            'total_dimensions'    => $totalDimensions,
            'narrative_type_counts' => $typeCounts,
            'summary_sentence'    => $summarySentence,
        ];
    }

    /**
     * Derive the approved summary sentence from per-type narrative counts.
     *
     * The pattern key is determined by the relationship between the alignment
     * and difference counts among the scored (non-insufficient_data) dimensions.
     * Adjacent and neutral types, if present, are counted alongside other types
     * but do not have dedicated summary templates — they contribute to totals only.
     *
     * Pattern rules (applied in order):
     *   1. totalDimensions == 0                          → no_dimensions
     *   2. alignment == 0 and difference == 0            → all_insufficient
     *   3. difference == 0                               → all_alignment
     *   4. alignment == 0                                → all_difference
     *   5. alignment > difference                        → mostly_alignment
     *   6. difference > alignment                        → mostly_difference
     *   7. (otherwise, alignment == difference)          → mixed
     */
    private function deriveSummarySentence(array $typeCounts, int $totalDimensions): string
    {
        if ($totalDimensions === 0) {
            return self::SUMMARY_TEMPLATE_MAP['no_dimensions'];
        }

        $alignmentCount  = $typeCounts['alignment']         ?? 0;
        $differenceCount = $typeCounts['difference']        ?? 0;

        if ($alignmentCount === 0 && $differenceCount === 0) {
            return self::SUMMARY_TEMPLATE_MAP['all_insufficient'];
        }

        if ($differenceCount === 0) {
            return self::SUMMARY_TEMPLATE_MAP['all_alignment'];
        }

        if ($alignmentCount === 0) {
            return self::SUMMARY_TEMPLATE_MAP['all_difference'];
        }

        if ($alignmentCount > $differenceCount) {
            return self::SUMMARY_TEMPLATE_MAP['mostly_alignment'];
        }

        if ($differenceCount > $alignmentCount) {
            return self::SUMMARY_TEMPLATE_MAP['mostly_difference'];
        }

        return self::SUMMARY_TEMPLATE_MAP['mixed'];
    }

    // -------------------------------------------------------------------------
    // Input helpers
    // -------------------------------------------------------------------------

    /**
     * Extract the dimensions array from a BYA_ALIGN_V1 payload.
     * Returns an empty array when the payload is absent or malformed.
     */
    private function extractAlignDimensions(mixed $alignV1Payload): array
    {
        if (!is_array($alignV1Payload)) {
            return [];
        }

        $dims = $alignV1Payload['dimensions'] ?? null;

        return is_array($dims) ? $dims : [];
    }

    // -------------------------------------------------------------------------
    // Structural validity
    // -------------------------------------------------------------------------

    /**
     * Determine whether the BYA_EXPLAIN_V1 input is structurally valid.
     *
     * A payload is structurally valid when it is a non-empty array that contains
     * a `dimensions` key whose value is an array.
     */
    private function isExplainPayloadValid(mixed $payload): bool
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
     * Build the stub payload returned when the BYA_EXPLAIN_V1 input is not
     * structurally valid, or when an unrecoverable exception escapes the main loop.
     *
     * dimensions: [] signals to callers that no narrative was possible.
     * All narrative type counts are 0.
     */
    private function buildStubPayload(mixed $explainPayload): array
    {
        $explanationVersion = (is_array($explainPayload) && isset($explainPayload['explanation_version']))
            ? $explainPayload['explanation_version']
            : null;

        $alignmentVersion = (is_array($explainPayload) && isset($explainPayload['alignment_version']))
            ? $explainPayload['alignment_version']
            : null;

        return [
            'narrative_version'   => self::NARRATIVE_VERSION,
            'explanation_version' => $explanationVersion,
            'alignment_version'   => $alignmentVersion,
            'dimensions'          => [],
            'summary'             => [
                'total_dimensions'      => 0,
                'narrative_type_counts' => array_fill_keys(self::ALL_NARRATIVE_TYPES, 0),
                'summary_sentence'      => self::SUMMARY_TEMPLATE_MAP['no_dimensions'],
            ],
        ];
    }
}
