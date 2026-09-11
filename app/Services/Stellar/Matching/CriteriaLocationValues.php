<?php

namespace App\Services\Stellar\Matching;

/**
 * City and county values on their way from a criteria record to the matcher.
 *
 * The matcher compares them EXACTLY — `whereIn('city', …)` in BuyerMatchQueryBuilder and
 * `in_array($listing->city, …)` in BuyerMatchScorer — against Bridge's own spelling ("Tampa",
 * "Saint Petersburg", "Pinellas"). Criteria values arrive as people and widgets write them
 * ("Tampa, FL", "St. Petersburg", "Pinellas County, FL"), so they are normalised to that
 * spelling first. These are the rules BuyerCriteriaLoader has always applied; they live here so
 * the Tenant Criteria loader applies the same ones instead of growing its own.
 *
 * Pure: no I/O, no state.
 */
final class CriteriaLocationValues
{
    /**
     * Strip a trailing state (", FL") and expand the common abbreviations, so "St. Petersburg, FL"
     * reads as "Saint Petersburg", the way Bridge stores it.
     */
    public static function normalizeCity(string $city): string
    {
        $city = preg_replace('/,\s*[A-Z]{2}\s*$/u', '', trim($city));
        $city = preg_replace(['/\bSt\.\s+/u', '/\bFt\.\s+/u', '/\bMt\.\s+/u'], ['Saint ', 'Fort ', 'Mount '], $city);

        return trim($city);
    }

    /** Strip a trailing state and the word "County": "Pinellas County, FL" reads as "Pinellas". */
    public static function normalizeCounty(string $county): string
    {
        $county = preg_replace('/,\s*[A-Z]{2}\s*$/u', '', trim($county));
        $county = preg_replace('/\s+County\s*$/iu', '', trim($county));

        return trim($county);
    }

    /** @param  array<mixed>  $values  @return list<string> normalised, non-empty, de-duplicated */
    public static function cities(array $values): array
    {
        return self::clean($values, [self::class, 'normalizeCity']);
    }

    /** @param  array<mixed>  $values  @return list<string> normalised, non-empty, de-duplicated */
    public static function counties(array $values): array
    {
        return self::clean($values, [self::class, 'normalizeCounty']);
    }

    /**
     * The EXPLICIT form field when it holds anything, otherwise the Location DNA widget's list.
     *
     * A fallback, never a union and never an override: a listing whose explicit field names Tampa
     * and whose map names Orlando matches Tampa — the two representations existing side by side
     * must not widen the search, and a map value must not silently replace what was typed into
     * the field the form labels as the answer.
     *
     * @param  list<string>  $explicit  already cleaned
     * @param  list<string>  $map       already cleaned
     * @return list<string>
     */
    public static function explicitElseMap(array $explicit, array $map): array
    {
        return $explicit !== [] ? $explicit : $map;
    }

    private static function clean(array $values, callable $normalize): array
    {
        $out = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }
            $value = $normalize($value);
            if ($value !== '' && !in_array($value, $out, true)) {
                $out[] = $value;
            }
        }

        return $out;
    }
}
