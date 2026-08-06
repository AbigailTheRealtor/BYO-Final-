<?php

namespace App\Services\LocationDna\Criteria;

/**
 * Phase 1a — the read-only enumeration seam behind the Criteria geography cascade.
 *
 * WHAT THIS IS FOR
 * ----------------
 * The approved Criteria hierarchy is: state (required) → counties (required) → ZIPs and cities
 * (optional). Serving that needs ENUMERATION — "which counties are in this state?" — which nothing
 * in the codebase provided. It is deliberately a different responsibility from
 * {@see \App\Contracts\BoundaryAdapterInterface}, which resolves a KNOWN name to geometry for
 * display and calls the Census API to do it. This interface answers "what may I choose?"; that one
 * answers "where is the thing I chose?". Phase 1a does not touch it.
 *
 * READ-ONLY BY CONSTRUCTION
 * -------------------------
 * Every method returns {@see GeographyOption} lists. There is no write method, no model is
 * returned, and no implementation may persist anything — the inertness guard asserts that no
 * `saveMeta(` or `->save(` appears anywhere in this namespace. That is what makes Phase 1a
 * riskless: it cannot change a single stored byte.
 *
 * WHY AN INTERFACE AT ALL, THIS EARLY
 * -----------------------------------
 * Two reasons, both practical:
 *
 *   1. TESTABILITY. The suite runs SQLite in-memory. The reference tables exist there, but the
 *      cascade RULES (Phase 1b) must be testable without any database, and
 *      {@see FakeCriteriaGeographyRepository} makes that possible — the same fake-collaborator
 *      discipline the G1f suites used.
 *   2. SUBSTRATE INDEPENDENCE. Phase 1 reads the `us_*` reference tables. Phase 4 adds PostGIS
 *      geometry for map overlays. When that lands, a second implementation satisfies this
 *      interface and no caller changes.
 *
 * IDENTIFIER CONTRACT
 * -------------------
 * Callers pass and receive `GeographyOption::$id` values, never display names. A `$stateId` is a
 * state option's id; `$countyIds` are county option ids. Passing a name is a caller bug and
 * yields an empty list rather than a guess.
 */
interface CriteriaGeographyRepository
{
    /**
     * Every selectable state, ordered by name.
     *
     * @return list<GeographyOption> kind = state
     */
    public function states(): array;

    /**
     * The counties of one state, ordered by name.
     *
     * @param  string  $stateId  a state option's id
     * @return list<GeographyOption> kind = county; empty when the state is unknown
     */
    public function countiesInState(string $stateId): array;

    /**
     * The cities of the given counties, ordered by name.
     *
     * @param  list<string>  $countyIds  county option ids
     * @return list<GeographyOption> kind = city; empty when the list is empty or unknown
     */
    public function citiesInCounties(array $countyIds): array;

    /**
     * The ZIP codes associated with the given counties, ordered by ZIP.
     *
     * ASSOCIATION, NOT CONTAINMENT. A ZIP may be returned under more than one county: ZCTAs cross
     * county lines, and the reference data associates a ZIP with a county by NAME rather than by
     * geometry. Callers must treat the result as many-to-many. Exact geometric containment is a
     * PostGIS concern and is deliberately out of Phase 1's scope — encoding containment here would
     * bake in an assumption the data cannot support.
     *
     * @param  list<string>  $countyIds  county option ids
     * @return list<GeographyOption> kind = zip
     */
    public function zipsInCounties(array $countyIds): array;
}
