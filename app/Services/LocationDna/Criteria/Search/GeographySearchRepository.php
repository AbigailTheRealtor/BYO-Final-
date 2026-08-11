<?php

namespace App\Services\LocationDna\Criteria\Search;

/**
 * M1 — the read-only SEARCH seam behind Location DNA geography selection.
 *
 * WHY THIS IS NOT A FIFTH METHOD ON {@see \App\Services\LocationDna\Criteria\CriteriaGeographyRepository}
 * -------------------------------------------------------------------------------------------------------
 * Exactly the argument {@see \App\Services\LocationDna\Criteria\CriteriaNeighborhoodRepository}
 * already made, and it applies here for the same reason. That interface has three implementations
 * and only one of them could answer this question:
 *
 *   - `us_cities.fips_code` is empty for all 25,830 rows and `us_counties.fips_code` is not unique,
 *     so the legacy tables cannot even identify what they would return;
 *   - the fake exists to make the cascade RULES testable without a database, and search is not a
 *     rule.
 *
 * Adding `search()` there would force a permanently-empty implementation onto Eloquent, and a
 * method returning nothing because it CANNOT answer is indistinguishable, at the call site, from
 * one returning nothing because there is genuinely no match. Keeping it separate says the same
 * thing structurally: a caller that wants search asks for this interface, and whether anything
 * answers is a BINDING decision. The Phase 1a interface stays frozen and untouched.
 *
 * ENUMERATION AND SEARCH ARE DIFFERENT QUESTIONS
 * ----------------------------------------------
 * Enumeration walks DOWN a known hierarchy — "what counties are in this state?" — and its answer is
 * exhaustive, ordered by name, and correct by construction. Search jumps INTO the hierarchy from a
 * string, and its answer is a ranked guess that can be wrong. Conflating them would put ranking
 * concerns into the cascade's option lists, where the whole point is that the list is complete.
 *
 * SEARCH SEEDS THE CASCADE, IT DOES NOT REPLACE IT
 * -------------------------------------------------
 * A match resolves to a {@see \App\Services\LocationDna\Criteria\GeographyOption} of exactly the
 * kind the corresponding tier already accepts. A consumer takes the option, feeds it into the
 * existing selection rules, and everything downstream — resolver, validator, label projector,
 * hydrator, storage format — is untouched. That is what makes replacing the Google autocomplete a
 * contained change rather than a rewrite of the geography stack.
 *
 * READ-ONLY BY CONSTRUCTION, like everything in this namespace. One method, returning DTOs, no
 * model, no write path.
 */
interface GeographySearchRepository
{
    /**
     * The geographies that might match the query's term, ranked best-first.
     *
     * CONTRACT NOTES
     * --------------
     *   - An unusable query ({@see GeographyQuery::isUsable()}) yields
     *     {@see GeographySearchResult::empty()} rather than an exception. A user mid-keystroke is
     *     not an error, and every caller is a typeahead that would have to catch it.
     *   - Results are COLLAPSED to one match per (kind, id). Where enumeration emits a
     *     county-straddling place once per parent, search emits it once and carries every parent in
     *     {@see GeographyMatch::$parentIds}.
     *   - Ids are the same values the bound {@see \App\Services\LocationDna\Criteria\CriteriaGeographyRepository}
     *     issues, so a match can be handed straight to the cascade. An implementation that cannot
     *     guarantee that must return empty rather than ids of a different shape.
     *   - Truncation is reported, never silent.
     */
    public function search(GeographyQuery $query): GeographySearchResult;
}
