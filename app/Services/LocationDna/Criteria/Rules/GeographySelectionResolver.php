<?php

namespace App\Services\LocationDna\Criteria\Rules;

use App\Services\LocationDna\Criteria\CriteriaGeographyRepository;
use App\Services\LocationDna\Criteria\GeographyOption;

/**
 * Phase 1b — the cascade engine. Given a selection, return the part of it the corpus still
 * justifies, plus an itemised account of what was cleared.
 *
 * RESOLUTION IS A FUNCTION OF (SELECTION, CORPUS) — NOT OF (PREVIOUS, NEXT)
 * ------------------------------------------------------------------------
 * The obvious design is a transition handler: "the state changed, so clear the counties". This is
 * not that. It takes ONE selection and keeps only what the corpus justifies, which turns out to
 * express every clearing rule without ever being told what changed:
 *
 *   - a new state is chosen  → the old counties are not among that state's counties → cleared (C1)
 *   - a county is deselected → its cities are no longer enumerable → cleared (C2)
 *   - a county is deselected → its ZIPs are re-derived from the survivors → cleared iff orphaned (C3)
 *
 * Three consequences worth having: the resolver is stateless, idempotence (C5) is structural rather
 * than something to be careful about, and there is no "previous selection" for a caller to get
 * wrong or forget to pass.
 *
 * THE STATE TIER IS NEVER ALTERED
 * -------------------------------
 * Nothing sits above the state to justify it, so an unknown state is not the resolver's to remove —
 * silently deleting what the user chose, with no error anywhere, is the worst available outcome.
 * An unknown state simply justifies no counties, so everything below it clears and
 * {@see GeographySelectionValidator} reports `StateUnknown` against the state itself.
 *
 * CITIES AND ZIPS ARE BOTH RE-DERIVED FROM THE SURVIVING COUNTIES
 * ---------------------------------------------------------------
 * Neither tier is cleared by remembering which county it came from. Both are re-enumerated from
 * the counties that survived and kept where the corpus still offers them:
 *
 *   CITY SELECTIONS RESOLVE THROUGH GEOGRAPHY RELATIONSHIPS AND IDENTIFIERS. The corpus states
 *   which counties claim a place, and this class asks it — it does not assume how many there are.
 *   Under the reference tables `us_cities.county_id` is a single foreign key, so in practice one
 *   county answers; under published Census geography `census_place_counties` is a real many-to-many
 *   and a place straddling a line is offered under each parent. The rule below is correct for both
 *   because it never encodes the count: a city is kept while ANY surviving county still enumerates
 *   it.
 *
 *   ZIPS ARE ASSOCIATION, and always visibly so. `us_zip_codes` has no county foreign key; Phase 1a
 *   associates a ZIP to a county by matching (bare county name, state abbreviation), and a ZCTA
 *   legitimately crosses county lines. So one ZIP can have several parent counties, and it survives
 *   while ANY of them survives.
 *
 * Getting that survival rule wrong is the expensive mistake available in this phase. "County removed
 * → remove its ZIPs" destroys valid data every time a ZIP straddles a boundary: select Suffolk and
 * Nassau, ZIP 11001 is associated with both, deselect Suffolk, and the naive rule deletes 11001
 * even though Nassau still justifies it. The implementation below re-derives BOTH tiers from the
 * SURVIVING counties and keeps the intersection, which cannot make that mistake at either tier.
 *
 * Exact geometric containment for ZIPs waits for the spatial corpus, exactly as Phase 1a's
 * interface documents. This layer must not pre-empt it.
 *
 * READ-ONLY. The only collaborator is the read repository (D3), and the namespace-wide inertness
 * guard proves nothing here can write.
 */
final class GeographySelectionResolver
{
    public function __construct(
        private readonly CriteriaGeographyRepository $geography,
    ) {
    }

    /**
     * Keep only what the corpus justifies.
     *
     * Guarantees, each pinned by a test:
     *   C4  clearing is transitive — a dropped county's cities go in the SAME pass
     *   C5  idempotent — resolve(resolve(x)) equals resolve(x)
     *   C6  subtractive — the result is always a subset of the input; nothing is ever added
     *
     * And the invariant that ties this class to its neighbour: the returned selection carries no
     * referential violation, i.e. the validator will call it valid (though possibly incomplete).
     */
    public function resolve(GeographySelection $selection): SelectionResolution
    {
        /** @var list<ClearedSelection> $cleared */
        $cleared = [];

        // ── Counties, justified by the state ────────────────────────────────
        $countyOptions = $selection->hasState()
            ? $this->geography->countiesInState((string) $selection->stateId)
            : [];

        $counties = $this->keepJustified(
            $selection->countyIds,
            $this->allowedIds($countyOptions, GeographyOption::KIND_COUNTY),
            GeographyTier::Counties,
            GeographyRule::CountyNotInState,
            $cleared,
        );

        // ── Cities, justified by a RELATIONSHIP to ANY surviving county ─────
        // Re-derived from the survivors rather than from the county the city was picked under, so a
        // place the corpus offers under two selected counties outlives the loss of either one.
        $cityOptions = $counties === [] ? [] : $this->geography->citiesInCounties($counties);

        $cities = $this->keepJustified(
            $selection->cityIds,
            $this->allowedIds($cityOptions, GeographyOption::KIND_CITY),
            GeographyTier::Cities,
            GeographyRule::CityNotInSelectedCounty,
            $cleared,
        );

        // ── ZIPs, justified by ASSOCIATION with ANY surviving county ────────
        // Re-derived from the survivors, so a ZIP shared by two selected counties outlives the loss
        // of either one. Collapsing the multi-parent option list to a set of ZIP codes IS the
        // association rule — the parent that produced each row is deliberately not consulted.
        $zipOptions = $counties === [] ? [] : $this->geography->zipsInCounties($counties);

        $zips = $this->keepJustified(
            $selection->zipCodes,
            $this->allowedIds($zipOptions, GeographyOption::KIND_ZIP),
            GeographyTier::ZipCodes,
            GeographyRule::ZipNotInSelectedCounties,
            $cleared,
        );

        return new SelectionResolution(
            GeographySelection::of($selection->stateId, $counties, $cities, $zips),
            $cleared,
        );
    }

    /**
     * Filter one tier down to the ids the corpus justifies, recording every removal.
     *
     * Also drops blanks and duplicates, so the result is a clean set rather than merely a justified
     * one — that is what makes the "a resolved selection is always valid" invariant hold.
     *
     * @param  list<string>            $selected
     * @param  array<string, true>     $allowed   justified ids, as a lookup
     * @param  list<ClearedSelection>  $cleared   accumulator, by reference
     * @return list<string>
     */
    private function keepJustified(
        array $selected,
        array $allowed,
        GeographyTier $tier,
        GeographyRule $unjustified,
        array &$cleared,
    ): array {
        $kept = [];
        $seen = [];

        foreach ($selected as $id) {
            if ($id === '') {
                $cleared[] = new ClearedSelection($tier, $id, GeographyRule::MalformedId);
                continue;
            }

            if (isset($seen[$id])) {
                $cleared[] = new ClearedSelection($tier, $id, GeographyRule::DuplicateSelection);
                continue;
            }

            if (! isset($allowed[$id])) {
                $cleared[] = new ClearedSelection($tier, $id, $unjustified);
                continue;
            }

            $seen[$id] = true;
            $kept[]    = $id;
        }

        return $kept;
    }

    /**
     * Collapse an enumerated option list to a lookup of justified ids.
     *
     * The kind filter is defensive rather than decorative: an implementation that returned a
     * wrong-kind option would otherwise silently widen what this tier accepts.
     *
     * @param  list<GeographyOption>  $options
     * @return array<string, true>
     */
    private function allowedIds(array $options, string $kind): array
    {
        $allowed = [];

        foreach ($options as $option) {
            if (! $option->is($kind)) {
                continue;
            }

            // Keying on id alone is what collapses a multi-parent ZIP to one justified code. It
            // deduplicates repeated rows for free, so no separate distinct() pass is needed —
            // one would be O(n^2) over the ~2,700 ZIPs a large state enumerates, for no gain.
            $allowed[$option->id] = true;
        }

        return $allowed;
    }
}
