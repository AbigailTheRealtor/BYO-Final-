<?php

namespace Tests\Unit\Services\LocationDna\Criteria\Projection;

use App\Services\LocationDna\Criteria\FakeCriteriaGeographyRepository;
use App\Services\LocationDna\Criteria\Projection\GeographyLabelProjector;
use App\Services\LocationDna\Criteria\Projection\GeographySelectionHydrator;
use PHPUnit\Framework\TestCase;

/**
 * Phase 1c — the hydrator that reads a stored blob back into an id-carried selection.
 *
 * THIS IS THE CLASS THAT CAN DESTROY DATA, SO IT IS THE ONE THAT REFUSES TO
 * ------------------------------------------------------------------------
 * Every record written before this phase carries free-text labels produced by a third-party
 * autocomplete. Some of them will not be in the reference corpus: renamed places, hand-typed
 * values, formats from an earlier version of the widget, and the class-suffix variants the
 * corpus spells differently.
 *
 * The tempting behaviour is to drop what cannot be matched. That would mean a user opening an old
 * listing, changing an unrelated field, and saving — and losing counties they never touched, with
 * no message anywhere. The approved rule is the opposite one, and it is asserted here more than
 * once because it is the whole reason the class exists:
 *
 *     an unmatched label is PRESERVED VERBATIM, never dropped and never guessed at.
 *
 * Verbatim matters as much as preserved: the label is carried back out byte-identical, so a save
 * that changes nothing writes back exactly what it read.
 *
 * NO DATABASE. The fake repository stands in for the corpus, which is what lets these rules be
 * stated as behaviour rather than as fixtures.
 */
class GeographySelectionHydratorTest extends TestCase
{
    private function corpus(): FakeCriteriaGeographyRepository
    {
        return (new FakeCriteriaGeographyRepository())
            ->withState('1', 'Florida')
            ->withState('2', 'Louisiana')
            ->withCounty('10', 'Pinellas County', '1')
            ->withCounty('11', 'Hillsborough County', '1')
            ->withCounty('20', 'Orleans Parish', '2')
            ->withCity('100', 'St. Petersburg', '10')
            ->withCity('101', 'Tampa', '11')
            ->withZip('33708', '10')
            ->withZip('00501', '10');
    }

    private function hydrate(array $blob): object
    {
        return (new GeographySelectionHydrator($this->corpus()))->fromLabels($blob);
    }

    /** A blob in exactly the shape the widget has always written. */
    private function storedBlob(): array
    {
        return [
            'state'     => 'Florida',
            'counties'  => ['Pinellas County, FL'],
            'cities'    => ['St. Petersburg, FL'],
            'zip_codes' => ['33708'],
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · MATCHING THE STORED FORMAT
    // ═════════════════════════════════════════════════════════════════════════

    public function test_it_resolves_a_stored_blob_to_ids(): void
    {
        $result = $this->hydrate($this->storedBlob());

        $this->assertSame('1', $result->selection->stateId);
        $this->assertSame(['10'], $result->selection->countyIds);
        $this->assertSame(['100'], $result->selection->cityIds);
        $this->assertSame(['33708'], $result->selection->zipCodes);
    }

    /** Nothing was unmatched, so nothing is preserved. */
    public function test_a_fully_matched_blob_preserves_nothing(): void
    {
        $this->assertTrue($this->hydrate($this->storedBlob())->preserved->isEmpty());
    }

    /** The state suffix is stripped before matching — the corpus does not carry one. */
    public function test_the_state_suffix_is_stripped_before_matching(): void
    {
        $result = $this->hydrate(['state' => 'Florida', 'counties' => ['Pinellas County, FL']]);

        $this->assertSame(['10'], $result->selection->countyIds);
    }

    /** A label stored without the suffix still matches — older records lack it. */
    public function test_a_label_without_a_suffix_still_matches(): void
    {
        $result = $this->hydrate(['state' => 'Florida', 'counties' => ['Pinellas County']]);

        $this->assertSame(['10'], $result->selection->countyIds);
    }

    /** Matching is case- and whitespace-insensitive; storage never was. */
    public function test_matching_tolerates_case_and_padding(): void
    {
        $result = $this->hydrate([
            'state'    => '  florida ',
            'counties' => ['  PINELLAS COUNTY, fl '],
        ]);

        $this->assertSame('1', $result->selection->stateId);
        $this->assertSame(['10'], $result->selection->countyIds);
    }

    /** A county stored bare where the corpus spells the class suffix still matches. */
    public function test_a_county_missing_its_class_suffix_still_matches(): void
    {
        $result = $this->hydrate(['state' => 'Florida', 'counties' => ['Pinellas, FL']]);

        $this->assertSame(['10'], $result->selection->countyIds);
    }

    /** A non-"County" class is not assumed — a Parish is matched as a Parish. */
    public function test_a_parish_matches_without_being_renamed(): void
    {
        $result = $this->hydrate(['state' => 'Louisiana', 'counties' => ['Orleans Parish, LA']]);

        $this->assertSame(['20'], $result->selection->countyIds);
        $this->assertTrue($result->preserved->isEmpty());
    }

    /** Unpadded ZIPs are canonicalised on the way in, exactly as the repository pads on the way out. */
    public function test_an_unpadded_zip_is_padded_before_matching(): void
    {
        $result = $this->hydrate([
            'state'     => 'Florida',
            'counties'  => ['Pinellas County, FL'],
            'zip_codes' => ['501'],
        ]);

        $this->assertSame(['00501'], $result->selection->zipCodes);
        $this->assertTrue($result->preserved->isEmpty());
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · UNMATCHED LABELS ARE PRESERVED — NEVER DROPPED
    // ═════════════════════════════════════════════════════════════════════════

    public function test_an_unmatched_county_is_preserved_verbatim(): void
    {
        $result = $this->hydrate([
            'state'    => 'Florida',
            'counties' => ['Pinellas County, FL', 'Ye Olde County, FL'],
        ]);

        $this->assertSame(['10'], $result->selection->countyIds);
        $this->assertSame(['Ye Olde County, FL'], $result->preserved->counties);
    }

    public function test_an_unmatched_city_is_preserved_verbatim(): void
    {
        $result = $this->hydrate([
            'state'    => 'Florida',
            'counties' => ['Pinellas County, FL'],
            'cities'   => ['St. Petersburg, FL', 'Nowhereville, FL'],
        ]);

        $this->assertSame(['100'], $result->selection->cityIds);
        $this->assertSame(['Nowhereville, FL'], $result->preserved->cities);
    }

    public function test_an_unmatched_zip_is_preserved_verbatim(): void
    {
        $result = $this->hydrate([
            'state'     => 'Florida',
            'counties'  => ['Pinellas County, FL'],
            'zip_codes' => ['33708', '99999'],
        ]);

        $this->assertSame(['33708'], $result->selection->zipCodes);
        $this->assertSame(['99999'], $result->preserved->zipCodes);
    }

    /** A malformed ZIP is not repaired into something else — it is kept as it was found. */
    public function test_a_malformed_zip_is_preserved_not_repaired(): void
    {
        $result = $this->hydrate([
            'state'     => 'Florida',
            'counties'  => ['Pinellas County, FL'],
            'zip_codes' => ['ABCDE'],
        ]);

        $this->assertSame([], $result->selection->zipCodes);
        $this->assertSame(['ABCDE'], $result->preserved->zipCodes);
    }

    /**
     * An unknown state preserves EVERYTHING below it.
     *
     * With no usable state there is nothing to enumerate counties from, so nothing below can be
     * matched. Dropping the lot would be the worst available outcome — the record would come back
     * from an edit with its entire location gone.
     */
    public function test_an_unknown_state_preserves_the_whole_selection(): void
    {
        $result = $this->hydrate([
            'state'     => 'Atlantis',
            'counties'  => ['Pinellas County, FL'],
            'cities'    => ['St. Petersburg, FL'],
            'zip_codes' => ['33708'],
        ]);

        $this->assertTrue($result->selection->isEmpty());
        $this->assertSame('Atlantis', $result->preserved->state);
        $this->assertSame(['Pinellas County, FL'], $result->preserved->counties);
        $this->assertSame(['St. Petersburg, FL'], $result->preserved->cities);
        $this->assertSame(['33708'], $result->preserved->zipCodes);
    }

    /** A city under an UNMATCHED county is preserved, not orphaned into the selection. */
    public function test_a_city_below_an_unmatched_county_is_preserved(): void
    {
        $result = $this->hydrate([
            'state'    => 'Florida',
            'counties' => ['Ye Olde County, FL'],
            'cities'   => ['St. Petersburg, FL'],
        ]);

        $this->assertSame([], $result->selection->countyIds);
        $this->assertSame([], $result->selection->cityIds);
        $this->assertSame(['St. Petersburg, FL'], $result->preserved->cities);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · HOSTILE AND EMPTY INPUT
    // ═════════════════════════════════════════════════════════════════════════

    public function test_an_empty_blob_yields_an_empty_selection_and_preserves_nothing(): void
    {
        $result = $this->hydrate([]);

        $this->assertTrue($this->hydrate([])->selection->isEmpty());
        $this->assertTrue($result->preserved->isEmpty());
    }

    /** Blanks are not selections and are not history worth preserving either. */
    public function test_blank_entries_are_neither_selected_nor_preserved(): void
    {
        $result = $this->hydrate([
            'state'    => 'Florida',
            'counties' => ['', '   ', 'Pinellas County, FL'],
        ]);

        $this->assertSame(['10'], $result->selection->countyIds);
        $this->assertSame([], $result->preserved->counties);
    }

    /** A wrong-typed blob value never throws — hydration runs on untrusted stored data. */
    public function test_it_never_throws_on_a_malformed_blob(): void
    {
        $result = $this->hydrate([
            'state'     => ['not', 'a', 'string'],
            'counties'  => 'not-an-array',
            'cities'    => null,
            'zip_codes' => 12345,
        ]);

        $this->assertTrue($result->selection->isEmpty());
    }

    /** The same label twice collapses to one selection, not a duplicate the validator must report. */
    public function test_a_duplicated_label_collapses(): void
    {
        $result = $this->hydrate([
            'state'    => 'Florida',
            'counties' => ['Pinellas County, FL', 'Pinellas County'],
        ]);

        $this->assertSame(['10'], $result->selection->countyIds);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 9 · `Saint` / `St.` — THE TWO CORPORA SPELL THE SAME PLACES DIFFERENTLY
    // ═════════════════════════════════════════════════════════════════════════
    //
    // `us_cities` holds 159 names beginning `Saint ` and 13 beginning `St. `. `census_places` holds
    // 210 beginning `St. ` and NOT ONE beginning `Saint `. So `Saint Petersburg, FL` — a label that
    // really is stored in this database — matched the reference tables and matches nothing in the
    // Census corpus. Preserved rather than dropped, so nothing was ever lost; but the place is in
    // the corpus, spelled the other way, and a preserved chip for a place the user can see in the
    // dropdown is a bug the user has no way to fix.
    //
    // The fold is COMPARISON ONLY. What these tests pin down is that it changes which id a stored
    // label resolves to, and NOTHING about what is stored, preserved, or displayed.

    private function saintCorpus(): FakeCriteriaGeographyRepository
    {
        return (new FakeCriteriaGeographyRepository())
            ->withState('1', 'Florida')
            ->withCounty('10', 'Pinellas County', '1')
            ->withCounty('11', 'St. Johns County', '1')
            ->withCity('100', 'St. Petersburg', '10')
            ->withCity('101', 'Tampa', '10')
            ->withCity('102', 'Stevensville', '10')
            ->withCity('103', 'Ste. Genevieve', '10')
            ->withCity('104', 'Mount Saint Francis', '10');
    }

    private function hydrateSaint(array $blob): object
    {
        return (new GeographySelectionHydrator($this->saintCorpus()))->fromLabels($blob);
    }

    /** @return array{0: list<string>, 1: list<string>} matched city ids, preserved city labels */
    private function cityOutcome(string $label): array
    {
        $result = $this->hydrateSaint([
            'state'    => 'Florida',
            'counties' => ['Pinellas County, FL'],
            'cities'   => [$label],
        ]);

        return [$result->selection->cityIds, $result->preserved->cities];
    }

    // ── 1 · Saint Petersburg matches St. Petersburg ──────────────────────────

    /** The headline case, in the exact stored format: `Saint X, ST` finds the corpus's `St. X`. */
    public function test_a_stored_saint_label_matches_the_corpus_st_spelling(): void
    {
        [$matched, $preserved] = $this->cityOutcome('Saint Petersburg, FL');

        $this->assertSame(['100'], $matched);
        $this->assertSame([], $preserved);
    }

    /**
     * Every spelling of the same place lands on the same id.
     *
     * `St Petersburg` — the abbreviation with no period — is included because the legacy tables
     * carry three such names and a hand-typed label may carry any of them.
     */
    public function test_every_saint_spelling_resolves_to_the_same_option(): void
    {
        foreach ([
            'Saint Petersburg, FL',
            'Saint Petersburg',
            'SAINT PETERSBURG',
            'saint petersburg',
            'St. Petersburg, FL',
            'St Petersburg, FL',
        ] as $label) {
            [$matched] = $this->cityOutcome($label);

            $this->assertSame(['100'], $matched, "[{$label}] did not resolve to the St. Petersburg option");
        }
    }

    /** The fold is not city-only: a county stored as `Saint Johns` finds `St. Johns County` too. */
    public function test_the_fold_applies_to_the_county_tier(): void
    {
        $result = $this->hydrateSaint([
            'state'    => 'Florida',
            'counties' => ['Saint Johns County, FL'],
        ]);

        $this->assertSame(['11'], $result->selection->countyIds);
        $this->assertSame([], $result->preserved->counties);
    }

    /**
     * MATCHING ONLY — the stored label is never rewritten by hydration.
     *
     * The blob handed in comes back untouched. Hydration produces ids alongside it; it does not
     * edit the document, and nothing in this phase migrates stored labels in place.
     */
    public function test_hydration_does_not_rewrite_the_stored_blob(): void
    {
        $blob = [
            'state'    => 'Florida',
            'counties' => ['Pinellas County, FL'],
            'cities'   => ['Saint Petersburg, FL'],
        ];
        $before = $blob;

        $this->hydrateSaint($blob);

        $this->assertSame($before, $blob);
    }

    /**
     * The DISPLAY label is the corpus's own, not the stored variant.
     *
     * A folded match resolves to the corpus id, so the projector — which labels from the enumerated
     * options — emits `St. Petersburg, FL`. The stored spelling is replaced on the next save BY THE
     * USER'S ACTION, which is the whole difference between this and a migration.
     */
    public function test_a_folded_match_projects_the_canonical_corpus_label(): void
    {
        $repository = $this->saintCorpus();
        $hydrated   = (new GeographySelectionHydrator($repository))->fromLabels([
            'state'    => 'Florida',
            'counties' => ['Pinellas County, FL'],
            'cities'   => ['Saint Petersburg, FL'],
        ]);

        $projected = (new GeographyLabelProjector())->project(
            $hydrated->selection,
            'Florida',
            'FL',
            $repository->countiesInState('1'),
            $repository->citiesInCounties(['10']),
            $hydrated->preserved,
        );

        $this->assertSame(['St. Petersburg, FL'], $projected['cities']);
    }

    // ── 2 · Existing preserved labels are not lost ───────────────────────────

    /**
     * The Clearwater Beach case: a label the corpus does not contain under ANY spelling.
     *
     * This is the one the fold must not disturb. A neighbourhood that was never a Census place has
     * no `St.` counterpart to find, so it stays preserved — verbatim, suffix and all.
     */
    public function test_a_label_absent_from_the_corpus_is_still_preserved_verbatim(): void
    {
        [$matched, $preserved] = $this->cityOutcome('Clearwater Beach, FL');

        $this->assertSame([], $matched);
        $this->assertSame(['Clearwater Beach, FL'], $preserved);
    }

    /** A `Saint` label with no corpus counterpart is preserved, not folded onto something else. */
    public function test_a_saint_label_with_no_counterpart_is_preserved(): void
    {
        [$matched, $preserved] = $this->cityOutcome('Saint Cloud, FL');

        $this->assertSame([], $matched);
        $this->assertSame(['Saint Cloud, FL'], $preserved);
    }

    /** Matched and preserved coexist: folding one label does not disturb the other's history. */
    public function test_folding_one_label_leaves_other_preserved_labels_intact(): void
    {
        $result = $this->hydrateSaint([
            'state'    => 'Florida',
            'counties' => ['Pinellas County, FL'],
            'cities'   => ['Saint Petersburg, FL', 'Clearwater Beach, FL', 'Tampa'],
        ]);

        $this->assertSame(['100', '101'], $result->selection->cityIds);
        $this->assertSame(['Clearwater Beach, FL'], $result->preserved->cities);
    }

    // ── 3 · No unrelated cities are affected ─────────────────────────────────

    /**
     * `Sainte` is a different word and is DELIBERATELY not folded.
     *
     * The corpora do not disagree about it, and folding it would risk equating `Ste. Genevieve`
     * with a `St. Genevieve` that may be a different place.
     */
    public function test_sainte_is_not_folded_onto_saint(): void
    {
        [$matched, $preserved] = $this->cityOutcome('Sainte Genevieve');

        $this->assertSame([], $matched);
        $this->assertSame(['Sainte Genevieve'], $preserved);
    }

    /** `Ste.` still matches itself. The fold neither breaks nor widens it. */
    public function test_ste_still_matches_its_own_corpus_entry(): void
    {
        [$matched] = $this->cityOutcome('Ste. Genevieve');

        $this->assertSame(['103'], $matched);
    }

    /**
     * The token must be a WHOLE WORD. `Stevensville` starts with the letters `st` and is not a
     * saint; a fold without the trailing `\s+` would turn it into `evensville`.
     */
    public function test_a_name_merely_beginning_with_st_is_untouched(): void
    {
        [$matched, $preserved] = $this->cityOutcome('Stevensville');

        $this->assertSame(['102'], $matched);
        $this->assertSame([], $preserved);
    }

    /** The fold anchors at the START. A `Saint` in the middle of a name is left alone. */
    public function test_saint_is_not_folded_mid_name(): void
    {
        [$matched] = $this->cityOutcome('Mount Saint Francis');

        $this->assertSame(['104'], $matched);

        // ...and the St. spelling of a mid-name Saint is NOT invented as a match.
        [$other, $preserved] = $this->cityOutcome('Mount St. Francis');

        $this->assertSame([], $other);
        $this->assertSame(['Mount St. Francis'], $preserved);
    }

    /** A name with no saint in it at all resolves exactly as it did before the fold. */
    public function test_an_unrelated_city_is_unaffected(): void
    {
        [$matched, $preserved] = $this->cityOutcome('Tampa');

        $this->assertSame(['101'], $matched);
        $this->assertSame([], $preserved);
    }

    /** And an unrelated label that never matched still does not, with its bytes intact. */
    public function test_an_unrelated_unmatched_city_is_still_preserved(): void
    {
        [$matched, $preserved] = $this->cityOutcome('Nowhere Township');

        $this->assertSame([], $matched);
        $this->assertSame(['Nowhere Township'], $preserved);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Phase 1d-5 · A SECOND PASS OVER THE CITY TIER'S LEFTOVERS
    // ═════════════════════════════════════════════════════════════════════════

    private function neighborhoodCorpus(): \App\Services\LocationDna\Criteria\FakeCriteriaNeighborhoodRepository
    {
        return (new \App\Services\LocationDna\Criteria\FakeCriteriaNeighborhoodRepository())
            ->withNeighborhood('900', 'Snell Isle', '100')       // inside St. Petersburg
            ->withNeighborhood('901', 'Old Northeast', '100')
            ->withNeighborhood('910', 'Ybor City', '101');       // inside Tampa

    }

    private function hydrateWithNeighborhoods(array $blob): object
    {
        return (new GeographySelectionHydrator($this->corpus(), $this->neighborhoodCorpus()))
            ->fromLabels($blob);
    }

    /**
     * The default construction — what the Livewire trait still uses — must behave exactly as before:
     * a neighbourhood label is unrecognised and therefore preserved.
     */
    public function test_without_a_neighborhood_repository_a_neighborhood_label_is_preserved(): void
    {
        $hydrated = $this->hydrate([
            'state'    => 'Florida',
            'counties' => ['Pinellas County, FL'],
            'cities'   => ['St. Petersburg, FL', 'Snell Isle, FL'],
        ]);

        $this->assertSame(['100'], $hydrated->selection->cityIds);
        $this->assertSame([], $hydrated->selection->neighborhoodIds);
        $this->assertSame(['Snell Isle, FL'], $hydrated->preserved->cities);
    }

    public function test_a_leftover_city_label_is_matched_as_a_neighborhood(): void
    {
        $hydrated = $this->hydrateWithNeighborhoods([
            'state'    => 'Florida',
            'counties' => ['Pinellas County, FL'],
            'cities'   => ['St. Petersburg, FL', 'Snell Isle, FL'],
        ]);

        $this->assertSame(['100'], $hydrated->selection->cityIds);
        $this->assertSame(['900'], $hydrated->selection->neighborhoodIds);
        $this->assertSame([], $hydrated->preserved->cities);
    }

    /**
     * The safety rule. Without its parent city selected, the label must stay PRESERVED — promoting
     * it would hand the resolver something it clears immediately, and a cleared selection is not
     * preserved, so the label would be gone on the next save.
     */
    public function test_a_neighborhood_without_its_parent_city_stays_preserved(): void
    {
        $hydrated = $this->hydrateWithNeighborhoods([
            'state'    => 'Florida',
            'counties' => ['Pinellas County, FL'],
            'cities'   => ['Snell Isle, FL'],
        ]);

        $this->assertSame([], $hydrated->selection->cityIds);
        $this->assertSame([], $hydrated->selection->neighborhoodIds);
        $this->assertSame(['Snell Isle, FL'], $hydrated->preserved->cities);
    }

    public function test_a_neighborhood_of_an_unselected_city_stays_preserved(): void
    {
        // Tampa is not selected, so Ybor City has nothing to hang from.
        $hydrated = $this->hydrateWithNeighborhoods([
            'state'    => 'Florida',
            'counties' => ['Pinellas County, FL', 'Hillsborough County, FL'],
            'cities'   => ['St. Petersburg, FL', 'Ybor City, FL'],
        ]);

        $this->assertSame(['100'], $hydrated->selection->cityIds);
        $this->assertSame([], $hydrated->selection->neighborhoodIds);
        $this->assertSame(['Ybor City, FL'], $hydrated->preserved->cities);
    }

    public function test_a_label_matching_neither_tier_is_still_preserved(): void
    {
        $hydrated = $this->hydrateWithNeighborhoods([
            'state'    => 'Florida',
            'counties' => ['Pinellas County, FL'],
            'cities'   => ['St. Petersburg, FL', 'Snell Isle, FL', 'Nowhere At All, FL'],
        ]);

        $this->assertSame(['900'], $hydrated->selection->neighborhoodIds);
        $this->assertSame(['Nowhere At All, FL'], $hydrated->preserved->cities);
    }

    public function test_neighborhood_matching_tolerates_case_padding_and_the_state_suffix(): void
    {
        foreach (['Snell Isle, FL', '  snell   isle  ', 'SNELL ISLE'] as $label) {
            $hydrated = $this->hydrateWithNeighborhoods([
                'state'    => 'Florida',
                'counties' => ['Pinellas County, FL'],
                'cities'   => ['St. Petersburg, FL', $label],
            ]);

            $this->assertSame(['900'], $hydrated->selection->neighborhoodIds, "failed for: {$label}");
        }
    }

    public function test_several_neighborhoods_of_one_city_all_match(): void
    {
        $hydrated = $this->hydrateWithNeighborhoods([
            'state'    => 'Florida',
            'counties' => ['Pinellas County, FL'],
            'cities'   => ['St. Petersburg, FL', 'Snell Isle, FL', 'Old Northeast, FL'],
        ]);

        $this->assertSame(['900', '901'], $hydrated->selection->neighborhoodIds);
        $this->assertSame([], $hydrated->preserved->cities);
    }

    public function test_a_neighborhood_under_a_preserved_county_is_not_promoted(): void
    {
        // The county did not match, so no city matched, so nothing justifies the neighbourhood.
        $hydrated = $this->hydrateWithNeighborhoods([
            'state'    => 'Florida',
            'counties' => ['Ye Olde County, FL'],
            'cities'   => ['St. Petersburg, FL', 'Snell Isle, FL'],
        ]);

        $this->assertSame([], $hydrated->selection->neighborhoodIds);
        $this->assertSame(
            ['St. Petersburg, FL', 'Snell Isle, FL'],
            $hydrated->preserved->cities,
            'Both are preserved verbatim — nothing below an unmatched county is dropped.'
        );
    }
}
