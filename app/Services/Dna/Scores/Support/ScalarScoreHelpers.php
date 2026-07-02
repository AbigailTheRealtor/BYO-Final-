<?php

namespace App\Services\Dna\Scores\Support;

use App\Services\Canonical\Adapters\ByoListingAdapter;
use App\Services\Dna\Confidence\ConfidenceCalculator;

/**
 * ScalarScoreHelpers — shared, deterministic helpers for Beyond-MLS scalar
 * score services (§8). Keeps confidence (§F4), the result contract, and
 * tolerant canonical-value matching consistent across scores without any AI or
 * external calls.
 */
trait ScalarScoreHelpers
{
    /**
     * @param array<string,mixed> $inputs
     * @return array{score_key:string,side:string,value:?int,data_completeness:int,confidence:int,explanation:string,inputs:array,version:string}
     */
    protected function result(string $scoreKey, string $side, string $version, ?int $value, int $completeness, string $explanation, array $inputs): array
    {
        $completeness = max(0, min(100, $completeness));
        // §F4: confidence is 0 whenever the decisive input is absent (value null).
        $confidence = $value === null
            ? 0
            : ConfidenceCalculator::derive($completeness, ByoListingAdapter::SOURCE_RELIABILITY);

        return [
            'score_key'         => $scoreKey,
            'side'              => $side,
            'value'             => $value === null ? null : max(0, min(100, $value)),
            'data_completeness' => $completeness,
            'confidence'        => $confidence,
            'explanation'       => $explanation,
            'inputs'            => $inputs,
            'version'           => $version,
        ];
    }

    /** Lower-cased, space-joined text form of a canonical string/array value. */
    protected function textOf($value): string
    {
        if (is_array($value)) {
            return strtolower(trim(implode(' ', array_map('strval', $value))));
        }
        return strtolower(trim((string) $value));
    }

    /** True if the value's text contains ANY of the needles (case-insensitive). */
    protected function containsAny($value, array $needles): bool
    {
        $text = $this->textOf($value);
        if ($text === '') {
            return false;
        }
        foreach ($needles as $n) {
            if (strpos($text, strtolower($n)) !== false) {
                return true;
            }
        }
        return false;
    }

    protected function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
    }
}
