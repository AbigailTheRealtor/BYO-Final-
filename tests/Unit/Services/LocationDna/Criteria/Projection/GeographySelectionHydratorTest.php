<?php

namespace Tests\Unit\Services\LocationDna\Criteria\Projection;

use App\Services\LocationDna\Criteria\FakeCriteriaGeographyRepository;
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
}
