<?php

namespace App\Services;

/**
 * Normalizes US city names for consistent comparison and search.
 *
 * Primary use-cases:
 *  1. Import deduplication — so "St. Petersburg" and "Saint Petersburg" hash
 *     to the same key and are never stored as separate alias rows.
 *  2. Search expansion — so a user typing "St. Pete Beach" finds records
 *     stored as "Saint Pete Beach", and a user typing "Saint Petersburg"
 *     finds records stored as "St. Petersburg".
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
     * Reverse contractions — full word back to abbreviated form with period.
     * Used in searchVariants so that typing "Saint Petersburg" also generates
     * "St. Petersburg" as a search candidate, matching DB records stored in
     * abbreviated form.
     */
    private const CONTRACTIONS = [
        ['/\bSaint\b/i', 'St.'],
        ['/\bFort\b/i',  'Ft.'],
        ['/\bMount\b/i', 'Mt.'],
        ['/\bNorth\b/i', 'N.'],
        ['/\bSouth\b/i', 'S.'],
        ['/\bEast\b/i',  'E.'],
        ['/\bWest\b/i',  'W.'],
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
     *
     * Always includes the trimmed original. Also adds:
     *  - The abbreviated→full expansion (e.g. "St. Pete" → "Saint Pete")
     *  - The full→abbreviated contraction (e.g. "Saint Petersburg" → "St. Petersburg")
     *  - The fully normalized (lowercase) form for case-insensitive fallback
     *
     * This ensures that typing either "Saint Petersburg" or "St. Petersburg"
     * finds records regardless of which form is stored in the DB.
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

        // Abbreviated → full expansion (e.g. "St." → "Saint", "Ft." → "Fort")
        $expanded = self::applyExpansions($city);
        if (strtolower($expanded) !== strtolower($city)) {
            $variants[] = $expanded;
        }

        // Full → abbreviated contraction (e.g. "Saint" → "St.", "Fort" → "Ft.")
        // Covers the case where the DB stores the abbreviated form and the user
        // types the full word (e.g. "Saint Petersburg" finding "St. Petersburg").
        $contracted = self::applyContractions($city);
        if (strtolower($contracted) !== strtolower($city) &&
            !in_array(strtolower($contracted), array_map('strtolower', $variants))) {
            $variants[] = $contracted;
        }

        // Fully normalized (lowercase, no periods) as a final fallback
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

    /**
     * Apply reverse contractions — full words back to abbreviated form with period.
     * Used to generate the abbreviated variant when the user types the full word,
     * so that "Saint Petersburg" also searches for "St. Petersburg".
     */
    public static function applyContractions(string $city): string
    {
        foreach (self::CONTRACTIONS as [$pattern, $replacement]) {
            $city = preg_replace($pattern, $replacement, $city);
        }
        return self::collapseSpaces(trim($city));
    }

    private static function collapseSpaces(string $s): string
    {
        return preg_replace('/\s+/', ' ', $s);
    }
}
