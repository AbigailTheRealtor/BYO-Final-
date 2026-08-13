<?php

namespace App\Services\LocationDna\Criteria\Search;

use App\Services\LocationDna\Criteria\GeographyOption;
use InvalidArgumentException;

/**
 * M1 — one search hit: a selectable option, plus why it matched and how to tell it apart.
 *
 * WHY THE BREADCRUMB IS A FIRST-CLASS FIELD RATHER THAN A UI CONCERN
 * ------------------------------------------------------------------
 * "Springfield" is a real place in roughly 34 states. A list of identical names is not a search
 * result, it is a coin toss, and the ONLY thing that made the Google dropdown usable was the
 * context line under each row. Losing that is the single most likely way this replacement gets
 * judged worse than what it replaced.
 *
 * Building it here rather than in Blade is deliberate: the repository already holds the joined
 * county and state names, so it can say `Pinellas County, FL` for free. A view would have to
 * re-query for them, once per row.
 *
 * WHY `parentIds` EXISTS ALONGSIDE `option->parentId`
 * ----------------------------------------------------
 * Two tiers are genuinely many-to-many. `census_place_counties` is a real relation, so a place
 * straddling a county line has more than one valid parent, and a ZCTA crossing a county line has
 * the same shape. Enumeration handles that by emitting the option once per parent — correct there,
 * wrong here: the same city appearing three times in a typeahead reads as a bug.
 *
 * So search COLLAPSES to one match per place and carries every valid parent in `parentIds`.
 * `option->parentId` holds the canonical one (the lowest GEOID, chosen for determinism) so a
 * consumer that only needs somewhere to anchor the selection has an answer; `parentIds` is there
 * so a later UI can back-fill the cascade without re-deriving the relation. Dropping the list and
 * re-querying for it later would be a breaking change to this DTO, so it is carried from the start.
 */
final class GeographyMatch
{
    /**
     * @param  GeographyOption  $option     the selectable option this hit resolves to
     * @param  MatchType        $matchType  how the term matched
     * @param  string           $breadcrumb human-readable disambiguation, e.g. "Pinellas County, FL"
     * @param  list<string>     $parentIds  every valid parent id; canonical one first
     */
    public function __construct(
        public readonly GeographyOption $option,
        public readonly MatchType $matchType,
        public readonly string $breadcrumb = '',
        public readonly array $parentIds = [],
    ) {
        if ($parentIds !== [] && $option->parentId !== null && $parentIds[0] !== $option->parentId) {
            throw new InvalidArgumentException(
                'The first parent id must be the option\'s own parent, so a consumer reading either '
                .'field alone agrees with one reading the other.'
            );
        }
    }

    /**
     * A stable identity for collapsing duplicates ACROSS tiers.
     *
     * Keyed on kind and id but NOT on parent, which is the opposite of
     * {@see GeographyOption::matches()} and deliberately so. That method answers "are these the
     * same enumerated row?", where two ZIP options under different counties are genuinely
     * different. This answers "are these the same thing to show the user?", where they are one row
     * with two parents. Using the enumeration's identity here would reintroduce the duplicate rows
     * this DTO exists to collapse.
     */
    public function key(): string
    {
        return $this->option->kind.':'.$this->option->id;
    }

    /** The label a UI would render as the primary line. */
    public function label(): string
    {
        return $this->option->name;
    }

    /** Does this hit have more than one valid parent? */
    public function hasMultipleParents(): bool
    {
        return count($this->parentIds) > 1;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'option'     => $this->option->toArray(),
            'match_type' => $this->matchType->value,
            'breadcrumb' => $this->breadcrumb,
            'parent_ids' => $this->parentIds,
        ];
    }
}
