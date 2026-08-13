<?php

namespace App\Services\LocationDna\Criteria\Search;

use App\Services\LocationDna\Places\PlaceNameKey;

/**
 * M1 — decide HOW a name matched a term, in one place.
 *
 * WHY THIS IS SHARED RATHER THAN DUPLICATED
 * -----------------------------------------
 * The census implementation narrows candidates in SQL, because scanning 32,188 places per keystroke
 * in PHP would be silly. But it still has to classify each surviving row to rank it, and the fake
 * has to classify too. Written twice, the two would drift — SQL `LIKE` would keep saying "matched"
 * while the PHP classifier disagreed about WHY, and the ranker would then order census results
 * differently from fake ones for identical input. Every ranking test would be testing the fake.
 *
 * So SQL decides only WHETHER a row is a candidate (a cheap, index-friendly `LIKE`), and this class
 * decides what KIND of match it is. One rule, one file, both implementations.
 *
 * NORMALISATION IS BORROWED. {@see PlaceNameKey::of()} is what `location_places.name_key` is stored
 * in, so using anything else here would classify rows that column matched as not matching at all.
 */
final class TermMatcher
{
    /**
     * How `$name` matches `$normalizedTerm`, or null if it does not.
     *
     * `$normalizedTerm` must ALREADY be normalised — {@see GeographyQuery::normalizedTerm()}. Taking
     * it pre-normalised rather than normalising here keeps the cost off the per-row path, where it
     * would run once per candidate instead of once per query.
     */
    public static function classify(string $name, string $normalizedTerm): ?MatchType
    {
        return self::classifyNormalized(PlaceNameKey::of($name), $normalizedTerm);
    }

    /**
     * The same decision, for a name that is ALREADY in normalised form.
     *
     * `location_places.name_key` is stored as the match surface — the model guarantees it equals
     * {@see PlaceNameKey::of()} of the display name — so re-normalising it per row would be work
     * repeated 39,282 times to produce the value already in hand. It also states the intent: the
     * match surface is what matching compares, and the display name is only for display.
     */
    public static function classifyNormalized(string $normalizedName, string $normalizedTerm): ?MatchType
    {
        if ($normalizedTerm === '') {
            return null;
        }

        $key = $normalizedName;

        if ($key === $normalizedTerm) {
            return MatchType::Exact;
        }

        if (str_starts_with($key, $normalizedTerm)) {
            return MatchType::Prefix;
        }

        return self::startsAWord($key, $normalizedTerm) ? MatchType::Word : null;
    }

    /**
     * Does the term begin any word inside the name?
     *
     * WORD-INITIAL ONLY, NEVER A BARE SUBSTRING. "beach" should find "Clearwater Beach"; "each"
     * should not. A mid-word substring match produces results the user cannot explain to
     * themselves — the searched-for text is visibly buried inside an unrelated word — and an
     * unexplainable result reads as a broken search rather than a generous one.
     *
     * Hyphens and slashes split words because place names use them structurally: "Winston-Salem"
     * must be findable by "salem", and "Bar Nunn/Casper"-style compounds by either side.
     */
    private static function startsAWord(string $key, string $normalizedTerm): bool
    {
        foreach (preg_split('/[\s\-\/]+/', $key) ?: [] as $word) {
            if ($word !== '' && str_starts_with($word, $normalizedTerm)) {
                return true;
            }
        }

        return false;
    }
}
