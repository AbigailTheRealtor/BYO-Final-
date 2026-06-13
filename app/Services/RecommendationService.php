<?php

namespace App\Services;

/**
 * RecommendationService
 *
 * Generates structured, provenance-bearing recommendations for consumers and agents.
 * Every recommendation is traceable to P4 CompatibilityScoreService data or
 * P5 ScoreBreakdownService data. No ML, no hidden weighting, no black-box ranking.
 *
 * Three public entry points:
 *
 *   consumerFitRecommendation($breakdown, $role)
 *     → Consumer-facing fit indicator derived from ScoreBreakdownService output.
 *       Includes reasons array drawn from field-level result categories.
 *       Labels never use superlatives ("Best agent", "Top agent", etc.).
 *
 *   agentCoachingRecommendation($bidData, $role)
 *     → Agent-facing coaching suggestion derived from MatchReadinessService
 *       missing_fields. Identifies which fields to complete and which readiness
 *       tier would be unlocked.
 *
 *   presetCompletionAnalysis($presetData, $role)
 *     → Preset-facing completion analysis identifying which match_readiness
 *       fields are absent from a saved preset's profile_data, with an impact note
 *       indicating which readiness tier would be unlocked for future bids.
 *
 * Label guardrail (enforced by assertNoSuperlatives()):
 *   Forbidden terms: "best agent", "top agent", "best", "top", "#1", "number one",
 *   "number 1", "leading", "premier".
 *
 * No database access, no side effects. Pure transformation.
 *
 * Roles supported: seller, buyer, landlord, tenant.
 */
class RecommendationService
{
    /**
     * Superlative terms that must never appear in any recommendation label.
     * Enforced by assertNoSuperlatives().
     */
    private const FORBIDDEN_LABELS = [
        'best agent',
        'top agent',
        'best',
        'top',
        '#1',
        'number one',
        'number 1',
        'leading',
        'premier',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Consumer fit recommendations
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate a consumer-facing fit recommendation from ScoreBreakdownService output.
     *
     * Recommendation types (score-gated):
     *   'strong_fit'  — score >= 80
     *   'good_fit'    — score >= 60
     *   'partial_fit' — score < 60
     *   'not_scored'  — bid is not_ready or no comparable fields were found
     *
     * Every recommendation includes:
     *   - recommendation_type  string
     *   - score                int|null  (null when not_scored)
     *   - label                string|null  (null when not_scored; no superlatives)
     *   - reasons              string[]  (traceable to field-level breakdown results)
     *   - source               string   'score_breakdown' | 'score_type_none'
     *
     * @param  array  $breakdown  Result from ScoreBreakdownService::breakdown()
     * @param  string $role       One of: 'seller', 'buyer', 'landlord', 'tenant'
     * @return array
     */
    public static function consumerFitRecommendation(array $breakdown, string $role): array
    {
        $scoreData = $breakdown['score_data'] ?? [];
        $scoreType = $scoreData['score_type'] ?? 'none';
        $score     = $scoreData['score'] ?? null;

        if ($scoreType === 'none' || $score === null) {
            return [
                'recommendation_type' => 'not_scored',
                'score'               => null,
                'label'               => null,
                'reasons'             => [],
                'source'              => 'score_type_none',
            ];
        }

        $fieldBreakdown = $breakdown['field_breakdown'] ?? [];
        $summary        = $breakdown['summary'] ?? [];
        $labels         = ScoreBreakdownService::fieldLabels(strtolower($role));

        $reasons = self::deriveConsumerReasons($fieldBreakdown, $labels, $summary);

        if ($score >= 80) {
            $type  = 'strong_fit';
            $label = 'Recommended based on your criteria';
        } elseif ($score >= 60) {
            $type  = 'good_fit';
            $label = 'Strong fit for several of your requirements';
        } else {
            $type  = 'partial_fit';
            $label = 'Partial fit — some criteria align';
        }

        return [
            'recommendation_type' => $type,
            'score'               => $score,
            'label'               => $label,
            'reasons'             => $reasons,
            'source'              => 'score_breakdown',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Agent coaching recommendations
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate agent coaching suggestions derived from MatchReadinessService output.
     *
     * Returns which fields to complete and which readiness tier would be unlocked:
     *   - not_ready        → missing quick_match fields; impact: 'Required to reach Quick Match Ready'
     *   - quick_match_ready → missing full_match fields; impact: 'May improve Full Match readiness'
     *   - full_match_ready  → no missing fields; impact note confirms full readiness
     *
     * Response shape:
     *   [
     *     'recommendation_type' => 'profile_completion',
     *     'state'               => 'not_ready|quick_match_ready|full_match_ready',
     *     'missing_fields'      => string[],   // field keys
     *     'missing_labels'      => string[],   // human-readable labels
     *     'impact'              => string,
     *     'source'              => 'match_readiness',
     *   ]
     *
     * @param  array  $bidData  Decoded bid data array.
     * @param  string $role     One of: 'seller', 'buyer', 'landlord', 'tenant'
     * @return array
     */
    public static function agentCoachingRecommendation(array $bidData, string $role): array
    {
        $readiness    = MatchReadinessService::evaluate($bidData, $role);
        $state        = $readiness['state'];
        $missingQuick = $readiness['missing_quick'] ?? [];
        $missingFull  = $readiness['missing_full']  ?? [];
        $labels       = ScoreBreakdownService::fieldLabels(strtolower($role));

        if ($state === 'full_match_ready') {
            return [
                'recommendation_type' => 'profile_completion',
                'state'               => 'full_match_ready',
                'missing_fields'      => [],
                'missing_labels'      => [],
                'impact'              => 'Already Full Match Ready — no additional fields required.',
                'source'              => 'match_readiness',
            ];
        }

        if ($state === 'quick_match_ready') {
            return [
                'recommendation_type' => 'profile_completion',
                'state'               => 'quick_match_ready',
                'missing_fields'      => $missingFull,
                'missing_labels'      => self::fieldsToLabels($missingFull, $labels),
                'impact'              => 'May improve Full Match readiness',
                'source'              => 'match_readiness',
            ];
        }

        // not_ready — missing quick fields block even Quick Match
        return [
            'recommendation_type' => 'profile_completion',
            'state'               => 'not_ready',
            'missing_fields'      => $missingQuick,
            'missing_labels'      => self::fieldsToLabels($missingQuick, $labels),
            'impact'              => 'Required to reach Quick Match Ready',
            'source'              => 'match_readiness',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Preset completion analysis
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Analyse which match readiness fields are absent from a preset's profile_data.
     *
     * Identifies which fields, if populated, would increase the count of hydrated
     * bid fields and improve future Quick Match or Full Match readiness.
     *
     * Response shape:
     *   [
     *     'recommendation_type'   => 'profile_completion',
     *     'missing_quick_fields'  => string[],   // field keys absent from preset
     *     'missing_quick_labels'  => string[],   // human-readable labels
     *     'missing_full_fields'   => string[],
     *     'missing_full_labels'   => string[],
     *     'impact'                => string,
     *     'source'                => 'preset_data',
     *   ]
     *
     * @param  array  $presetData  The preset's profile_data array.
     * @param  string $role        One of: 'seller', 'buyer', 'landlord', 'tenant'
     * @return array
     */
    public static function presetCompletionAnalysis(array $presetData, string $role): array
    {
        $config       = config('match_readiness.' . strtolower($role), []);
        $quickFields  = $config['quick_match'] ?? [];
        $fullFields   = $config['full_match']  ?? [];
        $arrayFields  = $config['array_fields'] ?? [];
        $placeholders = config('match_readiness.global_placeholders', []);
        $labels       = ScoreBreakdownService::fieldLabels(strtolower($role));

        $missingQuick = self::findMissingInData($presetData, $quickFields, $arrayFields, $placeholders);
        $missingFull  = self::findMissingInData($presetData, $fullFields,  $arrayFields, $placeholders);

        if (empty($missingQuick) && empty($missingFull)) {
            $impact = 'All match readiness fields are set — bids from this preset will be Full Match Ready.';
        } elseif (empty($missingQuick)) {
            $impact = 'May improve Full Match readiness';
        } else {
            $impact = 'Required to reach Quick Match Ready';
        }

        return [
            'recommendation_type'  => 'profile_completion',
            'missing_quick_fields' => $missingQuick,
            'missing_quick_labels' => self::fieldsToLabels($missingQuick, $labels),
            'missing_full_fields'  => $missingFull,
            'missing_full_labels'  => self::fieldsToLabels($missingFull, $labels),
            'impact'               => $impact,
            'source'               => 'preset_data',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Label guardrail
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return true when the given label contains no forbidden superlative terms.
     * Used in tests to assert guardrail compliance.
     *
     * @param  string $label
     * @return bool
     */
    public static function assertNoSuperlatives(string $label): bool
    {
        $lower = strtolower($label);
        foreach (self::FORBIDDEN_LABELS as $forbidden) {
            if (str_contains($lower, $forbidden)) {
                return false;
            }
        }
        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Derive human-readable reasons for a consumer fit recommendation from the
     * field-level breakdown produced by ScoreBreakdownService.
     *
     * Rules:
     *   - strong  → "{Label} matches requested criteria"
     *   - weak    → "{Label} differs from requested terms"
     *   - partial → generic services partial-match note (de-duplicated)
     *   - missing → single note that missing fields are excluded from scoring
     *
     * @param  array  $fieldBreakdown  'field_breakdown' from ScoreBreakdownService
     * @param  array  $labels          Role-specific field label map
     * @param  array  $summary         'summary' from ScoreBreakdownService
     * @return string[]
     */
    private static function deriveConsumerReasons(
        array $fieldBreakdown,
        array $labels,
        array $summary
    ): array {
        $reasons         = [];
        $partialMentioned = false;

        foreach ($fieldBreakdown as $row) {
            $label = $labels[$row['field']] ?? ucwords(str_replace('_', ' ', $row['field']));

            switch ($row['result']) {
                case 'strong':
                    $reasons[] = $label . ' matches requested criteria';
                    break;
                case 'weak':
                    $reasons[] = $label . ' differs from requested terms';
                    break;
                case 'partial':
                    if (!$partialMentioned) {
                        $reasons[]        = 'Service package partially aligns with requested services';
                        $partialMentioned = true;
                    }
                    break;
            }
        }

        if (($summary['missing'] ?? 0) > 0) {
            $reasons[] = 'Some fields were not provided — excluded from scoring';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * Convert a list of field keys to their human-readable labels.
     *
     * @param  string[] $fields
     * @param  array    $labels
     * @return string[]
     */
    private static function fieldsToLabels(array $fields, array $labels): array
    {
        return array_values(array_map(
            fn($f) => $labels[$f] ?? ucwords(str_replace('_', ' ', $f)),
            $fields
        ));
    }

    /**
     * Find which of the specified fields are absent or empty in $data.
     *
     * @param  array    $data
     * @param  string[] $fields
     * @param  string[] $arrayFields
     * @param  string[] $placeholders
     * @return string[]
     */
    private static function findMissingInData(
        array $data,
        array $fields,
        array $arrayFields,
        array $placeholders
    ): array {
        $missing = [];
        foreach ($fields as $field) {
            $value = $data[$field] ?? null;
            if (in_array($field, $arrayFields, true)) {
                $populated = self::isPopulatedArray($value);
            } else {
                $populated = self::isPopulatedScalar($value, $placeholders);
            }
            if (!$populated) {
                $missing[] = $field;
            }
        }
        return $missing;
    }

    /**
     * Return true when a scalar value is considered "populated".
     * Mirrors MatchReadinessService::isPopulatedScalar() rules.
     */
    private static function isPopulatedScalar(mixed $value, array $placeholders = []): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_array($value)) {
            return !empty($value);
        }
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return false;
        }
        if (!empty($placeholders) && in_array($trimmed, $placeholders, true)) {
            return false;
        }
        return true;
    }

    /**
     * Return true when an array value is considered "populated".
     * Mirrors MatchReadinessService::isPopulatedArray() rules.
     */
    private static function isPopulatedArray(mixed $value): bool
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) && count($decoded) > 0;
        }
        return is_array($value) && count($value) > 0;
    }
}
