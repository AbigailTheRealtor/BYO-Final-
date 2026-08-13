<?php

namespace App\Services\LocationDna\Criteria\Search;

/**
 * M1 — how a candidate matched the search term.
 *
 * WHY THIS IS AN ENUM AND NOT A BARE SCORE
 * ----------------------------------------
 * Ranking needs to be explainable. A float alone cannot answer "why is Clearwater above Clearwater
 * Beach?", and every ranking bug in a typeahead is a bug in that question. Carrying the reason
 * lets {@see GeographyMatchRanker} sort by it, lets a test assert on it directly, and lets a later
 * UI show it if disambiguation needs the help.
 *
 * THE ORDER OF THE CASES IS NOT THE RANKING ORDER — {@see self::weight()} is. Enum case order is
 * easy to change by accident during a merge; a method is not.
 */
enum MatchType: string
{
    /** The normalised name is exactly the normalised term. */
    case Exact = 'exact';

    /** The normalised name starts with the normalised term. */
    case Prefix = 'prefix';

    /**
     * The term matches the start of a word inside the name — "beach" in "Clearwater Beach".
     *
     * Distinct from a bare substring match on purpose: matching mid-word ("each" in "Beach")
     * produces results a user cannot explain to themselves, which reads as a broken search.
     */
    case Word = 'word';

    /**
     * Higher sorts first.
     *
     * The gaps are wide so a later signal added to the ranker can move a match WITHIN a band
     * without ever letting a Word match outrank a Prefix one. A tier bonus or a scope bonus is a
     * tiebreak between equally-matched candidates, never a reason to promote a weaker match.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Exact  => 300,
            self::Prefix => 200,
            self::Word   => 100,
        };
    }
}
