<?php

namespace App\Services;

/**
 * Normalizes US city names for consistent comparison and search.
 *
 * Primary use-cases:
 *  1. Import deduplication — so "St. Petersburg" and "Saint Petersburg" hash
 *     to the same key and are never stored as separate alias rows.
 *  2. Search expansion — so a user typing "St. Pete Beach" finds records
 *     stored as "Saint Pete Beach".
 */
class CityNameNormalizer
{
    /**
     * Ordered list of prefix/word abbreviation expansions.
     * Each entry: [pattern (case-insensitive, applied to current value), replacement]
     *
     * Rules:
     *  - Multi-char abbreviations (St, Mt, Ft) expand with OR without a trailing period.
     *  - Single-char directionals (N, S, E, W) only expand when written with a period
     *    (N., S., etc.) to avoid false matches on standalone letters.
     *  - Patterns run before period removal so `\.?` still anchors correctly.
     */
    private const EXPANSIONS = [
        ['/\bSt\.(?=\s|$)/i',  'Saint'],   // "St." → Saint   (period required for standalone)
        ['/\bSt\b(?=\s)/i',    'Saint'],   // "St "  → Saint   (must be followed by space)
        ['/\bMt\.?(?=\s|$)/i', 'Mount'],   // "Mt."  → Mount
        ['/\bFt\.?(?=\s|$)/i', 'Fort'],    // "Ft."  → Fort
        ['/\bN\.(?=\s|$)/i',   'North'],   // "N."   → North
        ['/\bS\.(?=\s|$)/i',   'South'],   // "S."   → South
        ['/\bE\.(?=\s|$)/i',   'East'],    // "E."   → East
        ['/\bW\.(?=\s|$)/i',   'West'],    // "W."   → West
    ];

    /**
     * Return a canonical lowercase key for deduplication.
     *
     * Steps:
     *  1. Trim + collapse duplicate internal spaces
     *  2. Apply abbreviation expansions (before period removal so patterns still match)
     *  3. Strip remaining periods
     *  4. Lowercase + trim + final space collapse
     */
    public static function normalize(string $city): string
    {
        $city = self::collapseSpaces(trim($city));

        foreach (self::EXPANSIONS as [$pattern, $replacement]) {
            $city = preg_replace($pattern, $replacement, $city);
        }

        $city = str_replace('.', '', $city);

        return self::collapseSpaces(strtolower(trim($city)));
    }

    /**
     * Return all distinct city name variants worth querying.
     * The list always contains the trimmed original; the normalized form is
     * appended only when it differs from the original (case-insensitively).
     *
     * @return string[]  Non-empty, distinct, de-duplicated strings.
     */
    public static function searchVariants(string $city): array
    {
        $city = self::collapseSpaces(trim($city));
        if ($city === '') {
            return [];
        }

        $normalized = self::normalize($city);

        $variants = [$city];

        // Expand abbreviations into the full-word form and add as additional variant
        $expanded = self::applyExpansions($city);
        if (strtolower($expanded) !== strtolower($city)) {
            $variants[] = $expanded;
        }

        // Add the fully normalized (lowercase) form if distinct — useful when the
        // incoming string is already full-word ("saint petersburg") but the DB stores
        // mixed case ("Saint Petersburg"): ILIKE covers that, but having the variant
        // ensures no variant is missed.
        if (!in_array(strtolower($normalized), array_map('strtolower', $variants))) {
            $variants[] = $normalized;
        }

        return array_values(array_unique($variants));
    }

    /**
     * Apply only the abbreviation expansions (without lowercasing or stripping periods).
     * Used to produce a human-readable expanded form for search variants.
     */
    public static function applyExpansions(string $city): string
    {
        foreach (self::EXPANSIONS as [$pattern, $replacement]) {
            $city = preg_replace($pattern, $replacement, $city);
        }
        $city = str_replace('.', '', $city);
        return self::collapseSpaces(trim($city));
    }

    private static function collapseSpaces(string $s): string
    {
        return preg_replace('/\s+/', ' ', $s);
    }
}
