<?php

namespace App\Services\LocationDna\Criteria\Search;

use App\Services\LocationDna\Criteria\GeographyOption;

/**
 * M1 — merge, deduplicate, order and truncate raw per-tier hits.
 *
 * WHY RANKING IS A CLASS AND NOT AN `ORDER BY`
 * ---------------------------------------------
 * The tiers are searched separately — they are different tables with different join shapes — so
 * their results have to be merged in PHP whatever happens. Once that is true, doing the ordering
 * in SQL as well would split one decision across five queries plus a merge step, and the ordering
 * would then be untestable without a database. Here it is pure: a list in, a list out.
 *
 * THE ORDERING IS TOTAL, AND THAT IS A REQUIREMENT RATHER THAN A NICETY
 * ---------------------------------------------------------------------
 * A typeahead re-runs on every keystroke. If two candidates can compare equal, their order is left
 * to the sort's internals and the list can visibly reshuffle between two keystrokes that returned
 * the same rows — which reads as flicker and destroys confidence in the result. So the comparator
 * ends with the option id, which is unique within a kind, and nothing can tie.
 *
 * SIGNAL BANDS ARE DELIBERATELY SEPARATED. {@see MatchType::weight()} is multiplied by ten so the
 * gap between two match types (1000) cannot be crossed by any combination of the bonuses below
 * (140 at most). A weaker match never outranks a stronger one; the bonuses only order candidates
 * that matched equally well. Collapsing those bands into one additive score is the classic way a
 * ranking function starts returning fuzzy matches above exact ones.
 */
final class GeographyMatchRanker
{
    /**
     * Which tier a user most likely meant, all else being equal.
     *
     * ZIP leads because a five-digit string is the least ambiguous thing anyone types into a
     * geography box. Neighbourhood trails because it is the narrowest tier and the one a user is
     * least likely to be reaching for when its name collides with a city's.
     */
    private const TIER_BONUS = [
        GeographyOption::KIND_ZIP          => 60,
        GeographyOption::KIND_CITY         => 40,
        GeographyOption::KIND_COUNTY       => 30,
        GeographyOption::KIND_STATE        => 25,
        GeographyOption::KIND_NEIGHBORHOOD => 15,
    ];

    /** Awarded when the term itself named the tier, e.g. "Pinellas County". */
    private const NAMED_TIER_BONUS = 45;

    /** Awarded when the hit falls inside a scope the caller already established. */
    private const IN_SCOPE_BONUS = 35;

    /**
     * @param  list<GeographyMatch>  $matches raw hits, any order, duplicates allowed
     */
    public function rank(array $matches, GeographyQuery $query): GeographySearchResult
    {
        $deduped = $this->dedupe($matches, $query);

        usort($deduped, function (array $a, array $b) use ($query): int {
            return $b['score'] <=> $a['score']
                ?: mb_strlen($a['match']->label()) <=> mb_strlen($b['match']->label())
                ?: strcmp($a['match']->label(), $b['match']->label())
                ?: strcmp($a['match']->option->kind, $b['match']->option->kind)
                ?: strcmp($a['match']->option->id, $b['match']->option->id);
        });

        $truncated = count($deduped) > $query->limit;
        $kept      = $truncated ? array_slice($deduped, 0, $query->limit) : $deduped;

        return GeographySearchResult::of(
            array_map(static fn (array $row): GeographyMatch => $row['match'], $kept),
            $truncated,
        );
    }

    /**
     * One row per (kind, id), keeping the strongest.
     *
     * Two tiers can legitimately produce the same option twice — a place searched under a county
     * scope that names two of its parents, for instance. Keeping the higher-scoring copy rather
     * than the first-seen one keeps the result independent of the order the tiers were queried in,
     * which is otherwise an invisible coupling to the implementation's method order.
     *
     * @param  list<GeographyMatch>  $matches
     * @return list<array{match: GeographyMatch, score: int}>
     */
    private function dedupe(array $matches, GeographyQuery $query): array
    {
        $best = [];

        foreach ($matches as $match) {
            $score = $this->score($match, $query);
            $key   = $match->key();

            if (! isset($best[$key]) || $score > $best[$key]['score']) {
                $best[$key] = ['match' => $match, 'score' => $score];
            }
        }

        return array_values($best);
    }

    private function score(GeographyMatch $match, GeographyQuery $query): int
    {
        $score = $match->matchType->weight() * 10;

        $score += self::TIER_BONUS[$match->option->kind] ?? 0;

        if ($match->option->is(GeographyOption::KIND_COUNTY) && $query->looksLikeCounty()) {
            $score += self::NAMED_TIER_BONUS;
        }

        if ($this->isInScope($match, $query)) {
            $score += self::IN_SCOPE_BONUS;
        }

        return $score;
    }

    /**
     * Does this hit sit inside a scope the caller already established?
     *
     * Only the COUNTY scope is decided here. A state scope is applied by the repository as a query
     * predicate — out-of-state rows are never fetched, so rewarding them for being in scope would
     * award the bonus to every row and order nothing.
     */
    private function isInScope(GeographyMatch $match, GeographyQuery $query): bool
    {
        if (! $query->hasCountyScope()) {
            return false;
        }

        foreach ($match->parentIds as $parentId) {
            if (in_array($parentId, $query->countyIds, true)) {
                return true;
            }
        }

        return false;
    }
}
