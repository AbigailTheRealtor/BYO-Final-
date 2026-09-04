<?php

namespace App\Services\ListingImport\Mls;

/**
 * The one place that decides how a raw Bridge value becomes a display string,
 * and — more importantly — when it becomes nothing at all.
 *
 * WHY THIS IS ITS OWN CLASS
 * -------------------------
 * Three presenters need identical answers to the same awkward questions: what
 * does a false boolean render as, is `0` a value or an absence, does an array of
 * empty strings count as populated. When those answers lived inside one
 * presenter, the other two either did without them or grew their own slightly
 * different copies — and the failure mode of "slightly different" here is a
 * listing page full of blank rows, which is exactly what the brief forbids.
 *
 * THE EMPTY-VALUE RULE
 * --------------------
 * `format()` returns null for anything that is not a real, populated value:
 * null, empty string, whitespace, empty array, empty object, an array whose
 * every member is blank, and a `false` boolean. Callers skip a null. That single
 * rule is what stops a residential listing rendering "Dock Lift Capacity: —"
 * beside 400 other columns the feed left empty for it.
 *
 * ZERO IS A VALUE, AND FALSE IS NOT
 * ---------------------------------
 * `0` renders. "Application Fee: $0" and "Carport Spaces: 0" are facts a reader
 * acts on, and dropping them would silently turn "the landlord charges nothing"
 * into "we do not know". A `false` boolean does NOT render: a page listing
 * "Pool: No / Spa: No / Waterfront: No / Dock: No" buries the handful of facts
 * the reader came for under a wall of negatives, and absence already says it.
 *
 * The two look similar and are not. `0` is the feed answering a question with a
 * number; `false` is the feed answering a yes/no question with "no", where the
 * whole row exists only to say "yes".
 *
 * NOTHING IS REWORDED
 * -------------------
 * Multi-value fields are joined in SOURCE ORDER, not sorted: the feed's own
 * sequence is a faithful record of what it said, and re-sorting would be this
 * layer editorialising about a property it knows nothing about. "Size Limit"
 * does not become "size restrictions apply". Authored prose is precisely what
 * the display boundary excludes, and a formatter is the wrong place to invent it.
 */
final class MlsValueFormatter
{
    /**
     * Render one value, or null when there is nothing to show.
     *
     * @param bool $keepFalse render a false boolean as "No" instead of omitting
     *                        it. Used only where the negative is the point —
     *                        nothing currently sets it, and it exists so that a
     *                        future caller does not reach for a second formatter.
     */
    public static function format(mixed $value, bool $keepFalse = false): ?string
    {
        if ($value === null || $value === [] || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : ($keepFalse ? 'No' : null);
        }

        if (is_array($value)) {
            return self::formatList($value);
        }

        if (is_object($value)) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            // Whole floats print as integers: "3.0 Bays" reads as corrupted data.
            return is_float($value) && floor($value) === $value
                ? (string) (int) $value
                : (string) $value;
        }

        $string = trim((string) $value);

        if ($string === '') {
            return null;
        }

        // Feed booleans arrive as strings on some columns. Normalised to the
        // same treatment as a real boolean so the two spellings do not render
        // differently on the same page.
        $lower = strtolower($string);

        if (in_array($lower, ['true', 'yes', 'y'], true)) {
            return 'Yes';
        }

        if (in_array($lower, ['false', 'no', 'n'], true)) {
            return $keepFalse ? 'No' : null;
        }

        return $string;
    }

    /**
     * Is this value one a listing should render at all?
     *
     * The same question `format()` answers, asked without wanting the string —
     * used by the metadata builder to decide whether a field is worth
     * persisting.
     */
    public static function isPopulated(mixed $value): bool
    {
        return self::format($value) !== null;
    }

    /**
     * A list field, deduplicated, blanks dropped, source order preserved.
     *
     * Nested arrays and objects inside a list are SKIPPED rather than
     * stringified: "Array" is not a feature of anybody's house, and a feed that
     * nests something unexpected should lose that member, not corrupt the row.
     *
     * @param array<mixed> $value
     */
    private static function formatList(array $value): ?string
    {
        $parts = [];

        foreach ($value as $item) {
            if (is_array($item) || is_object($item)) {
                continue;
            }

            if (is_bool($item)) {
                $item = $item ? 'Yes' : 'No';
            }

            $item = trim((string) $item);

            if ($item !== '' && ! in_array($item, $parts, true)) {
                $parts[] = $item;
            }
        }

        return $parts === [] ? null : implode(', ', $parts);
    }
}
