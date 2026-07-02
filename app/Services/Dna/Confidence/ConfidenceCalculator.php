<?php

namespace App\Services\Dna\Confidence;

/**
 * ConfidenceCalculator — §F4 of the frozen roadmap.
 *
 * Confidence is derived, never asserted, and NEVER inflates: a score's
 * confidence can never exceed the completeness of the inputs it rests on.
 *
 *     confidence = floor(data_completeness × source_reliability / 100)
 *     confidence = min(confidence, data_completeness)      // non-inflating
 *
 * This is the shared primitive every Beyond-MLS score uses so the same
 * propagation rule (Data Completeness → DNA Confidence → Match Confidence)
 * holds everywhere.
 */
class ConfidenceCalculator
{
    /**
     * @param int $dataCompleteness 0–100 coverage of the score's canonical inputs
     * @param int $sourceReliability 0–100 reliability of the source(s) supplying them
     */
    public static function derive(int $dataCompleteness, int $sourceReliability): int
    {
        $dataCompleteness  = self::clamp($dataCompleteness);
        $sourceReliability = self::clamp($sourceReliability);

        $confidence = (int) floor($dataCompleteness * $sourceReliability / 100);

        // Non-inflating: cannot exceed the weakest input (completeness).
        return min($confidence, $dataCompleteness);
    }

    private static function clamp(int $v): int
    {
        return max(0, min(100, $v));
    }
}
