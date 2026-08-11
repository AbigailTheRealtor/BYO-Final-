<?php

namespace App\Services\LocationDna\Criteria\Search;

/**
 * M1 — geography search, absent.
 *
 * WHAT PRODUCES IT
 * ----------------
 * One situation, and it is normal rather than exceptional: `geography_source = eloquent`, which is
 * the shipped default. The `us_*` reference tables cannot support search in a way a caller could
 * trust — `us_cities.fips_code` is empty for every row and `us_counties.fips_code` is not unique,
 * so there is no stable identifier to hand back and no honest answer to give.
 *
 * FAILING TO EMPTY IS CORRECT HERE, WHERE THE GEOGRAPHY SOURCE BINDING THROWS
 * ---------------------------------------------------------------------------
 * That binding refuses an unknown source because every alternative serves REAL DATA FROM THE WRONG
 * CORPUS, which is indistinguishable from success. The only alternative here is no search — which
 * is precisely the behaviour of every environment before M1 existed, and which cannot corrupt
 * anything. Throwing would turn "this feature is not available on this source" into an outage.
 *
 * WHY A CLASS RATHER THAN A NULL CHECK AT THE CALL SITE
 * -----------------------------------------------------
 * The consumer never asks whether search is available; it asks for matches and gets none. The gate
 * stays in ONE place — the binding — instead of at every call site, where a missed check would
 * either fatal on a null or silently skip search for a reason nobody wrote down.
 */
final class NullGeographySearchRepository implements GeographySearchRepository
{
    /**
     * {@inheritDoc}
     *
     * Always empty. The query is deliberately not inspected: there is no term that could make this
     * class find something, and pretending to look would invite a reader to believe otherwise.
     */
    public function search(GeographyQuery $query): GeographySearchResult
    {
        return GeographySearchResult::empty();
    }
}
