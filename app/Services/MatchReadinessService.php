<?php

namespace App\Services;

/**
 * MatchReadinessService
 *
 * Evaluates a bid's readiness to participate in Quick Match and/or Full Match
 * workflows. Returns a structured result indicating the bid's current state
 * and which required fields are missing.
 *
 * States (mutually exclusive; Full Match supersedes Quick Match):
 *   - 'not_ready'         — does not satisfy all Quick Match required fields
 *   - 'quick_match_ready' — all Quick Match required fields populated
 *   - 'full_match_ready'  — all Full Match required fields populated
 *
 * Population rules (mirrors bid form normalisation):
 *   - null, '', whitespace-only string  → not populated
 *   - [] (empty array)                  → not populated
 *   - global_placeholders ('0','0.00')  → not populated (config-driven)
 *   - any other non-empty value         → populated
 *
 * No database access, no side effects. Pure transformation.
 */
class MatchReadinessService
{
    /**
     * Evaluate match readiness for a bid.
     *
     * @param  array  $bidData  Decoded bid meta array (from $userBid->get cast to array).
     * @param  string $role     One of: 'seller', 'buyer', 'landlord', 'tenant'.
     * @return array{
     *     state: string,
     *     missing_quick: string[],
     *     missing_full: string[],
     *     missing_fields: string[],
     * }
     */
    public static function evaluate(array $bidData, string $role): array
    {
        $config = config('match_readiness.' . strtolower($role));

        if (empty($config)) {
            return [
                'state'          => 'not_ready',
                'missing_quick'  => [],
                'missing_full'   => [],
                'missing_fields' => [],
            ];
        }

        $quickFields        = $config['quick_match']  ?? [];
        $fullFields         = $config['full_match']   ?? [];
        $arrayFields        = $config['array_fields'] ?? [];
        $globalPlaceholders = config('match_readiness.global_placeholders', []);

        $missingQuick = self::missingFields($bidData, $quickFields, $arrayFields, $globalPlaceholders);
        $missingFull  = self::missingFields($bidData, $fullFields,  $arrayFields, $globalPlaceholders);

        if (empty($missingFull)) {
            $state = 'full_match_ready';
        } elseif (empty($missingQuick)) {
            $state = 'quick_match_ready';
        } else {
            $state = 'not_ready';
        }

        return [
            'state'          => $state,
            'missing_quick'  => $missingQuick,
            'missing_full'   => $missingFull,
            'missing_fields' => $missingFull,
        ];
    }

    /**
     * Return the list of required fields that are not populated in $bidData.
     *
     * @param  array    $bidData
     * @param  string[] $required
     * @param  string[] $arrayFields        Fields whose values must be non-empty arrays.
     * @param  string[] $globalPlaceholders Scalar values treated as "not populated" (e.g. '0').
     * @return string[]
     */
    private static function missingFields(
        array $bidData,
        array $required,
        array $arrayFields,
        array $globalPlaceholders = []
    ): array {
        $missing = [];
        foreach ($required as $field) {
            $value = $bidData[$field] ?? null;
            if (in_array($field, $arrayFields, true)) {
                if (!self::isPopulatedArray($value)) {
                    $missing[] = $field;
                }
            } else {
                if (!self::isPopulatedScalar($value, $globalPlaceholders)) {
                    $missing[] = $field;
                }
            }
        }
        return $missing;
    }

    /**
     * True when a scalar value is considered "populated":
     *   - not null
     *   - not empty string
     *   - not whitespace-only
     *   - not a global placeholder value (e.g. '0', '0.00')
     *
     * @param  string[] $placeholders  Values to treat as not populated.
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
     * True when an array value is considered "populated":
     *   - is an array
     *   - has at least one element
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
