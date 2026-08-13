<?php

namespace Tests\Unit\Services\LocationDna\Criteria\Projection;

use App\Services\LocationDna\Criteria\GeographyOption;
use App\Services\LocationDna\Criteria\Projection\GeographyLabelProjector;
use App\Services\LocationDna\Criteria\Projection\PreservedGeographyLabels;
use App\Services\LocationDna\Criteria\Rules\GeographySelection;
use PHPUnit\Framework\TestCase;

/**
 * Phase 1c — the projector that turns an id-carried selection back into the label strings the
 * stored blob has always held.
 *
 * WHY THIS CLASS IS THE CENTRE OF THE PHASE
 * -----------------------------------------
 * The Phase 1a/1b foundation carries a selection as reference-table IDs. Every consumer of the
 * stored data — the canonical blob, the legacy mirrors, the Stellar loaders, the match engine —
 * reads display LABELS, and those labels carry a state suffix the reference corpus does not:
 * stored data says `Pinellas County, FL` while `us_counties.name` says `Pinellas County`.
 *
 * So the byte-format asserted here is not cosmetic. Emitting `Pinellas County` instead of
 * `Pinellas County, FL` would silently stop every historical record from matching, with no error
 * anywhere. That is the single most expensive mistake available in this phase, and these tests
 * exist to make it impossible to make quietly.
 *
 * PURE BY CONSTRUCTION. The projector is handed option lists that have already been enumerated,
 * so it performs no lookup, needs no database, and cannot be slow at save time on a selection
 * that spans thousands of cities.
 */
class GeographyLabelProjectorTest extends TestCase
{
    private GeographyLabelProjector $projector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projector = new GeographyLabelProjector();
    }

    /** @return list<GeographyOption> */
    private function counties(): array
    {
        return [
            GeographyOption::county('10', 'Pinellas County', '1'),
            GeographyOption::county('11', 'Hillsborough County', '1'),
            GeographyOption::county('12', 'Orleans Parish', '1'),
        ];
    }

    /** @return list<GeographyOption> */
    private function cities(): array
    {
        return [
            GeographyOption::city('100', 'St. Petersburg', '10'),
            GeographyOption::city('101', 'Tampa', '11'),
        ];
    }

    private function project(
        GeographySelection $selection,
        ?string $stateName = 'Florida',
        ?string $abbrev = 'FL',
        ?PreservedGeographyLabels $preserved = null,
    ): array {
        return $this->projector->project(
            $selection,
            $stateName,
            $abbrev,
            $this->counties(),
            $this->cities(),
            $preserved ?? PreservedGeographyLabels::none(),
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · THE LABEL FORMAT IS THE STORED FORMAT
    // ═════════════════════════════════════════════════════════════════════════

    public function test_a_county_is_labelled_with_its_state_abbreviation(): void
    {
        $result = $this->project(GeographySelection::of('1', ['10']));

        $this->assertSame(['Pinellas County, FL'], $result['counties']);
    }

    public function test_a_city_is_labelled_with_its_state_abbreviation(): void
    {
        $result = $this->project(GeographySelection::of('1', ['10'], ['100']));

        $this->assertSame(['St. Petersburg, FL'], $result['cities']);
    }

    /** The state is the raw name — no suffix, because the stored value never carried one. */
    public function test_the_state_is_the_raw_name(): void
    {
        $result = $this->project(GeographySelection::of('1', ['10']));

        $this->assertSame('Florida', $result['state']);
    }

    /** ZIPs are bare five-digit codes. A suffix here would corrupt every ZIP comparison. */
    public function test_zips_carry_no_suffix(): void
    {
        $result = $this->project(GeographySelection::of('1', ['10'], [], ['33708', '00501']));

        $this->assertSame(['33708', '00501'], $result['zip_codes']);
    }

    /** A non-"County" class suffix is carried verbatim; the corpus name is the truth. */
    public function test_a_parish_keeps_its_own_class_suffix(): void
    {
        $result = $this->project(GeographySelection::of('1', ['12']));

        $this->assertSame(['Orleans Parish, FL'], $result['counties']);
    }

    /** With no abbreviation available the bare name is emitted rather than a dangling comma. */
    public function test_a_missing_abbreviation_yields_a_bare_name(): void
    {
        $result = $this->project(GeographySelection::of('1', ['10']), 'Florida', null);

        $this->assertSame(['Pinellas County'], $result['counties']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · THE PROJECTION SHAPE MATCHES THE CANONICAL BLOB
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Exactly the four blob keys, always present.
     *
     * Presence is what the command builder reads: a key present with an empty value is a CLEAR,
     * an absent key is a preserve. The cascade always states all four tiers, so it always emits
     * all four keys — the same thing the widget it replaces does.
     */
    public function test_it_emits_exactly_the_four_canonical_geography_keys(): void
    {
        $result = $this->project(GeographySelection::empty());

        $this->assertSame(['state', 'counties', 'cities', 'zip_codes'], array_keys($result));
    }

    public function test_an_empty_selection_projects_to_the_canonical_empties(): void
    {
        $result = $this->project(GeographySelection::empty(), null, null);

        $this->assertSame('', $result['state']);
        $this->assertSame([], $result['counties']);
        $this->assertSame([], $result['cities']);
        $this->assertSame([], $result['zip_codes']);
    }

    /** Lists are JSON arrays, never objects — a gap in the keys would serialise as `{"1":…}`. */
    public function test_the_lists_are_serialisable_as_json_arrays(): void
    {
        $result = $this->project(GeographySelection::of('1', ['10', '11']));

        $this->assertSame('["Pinellas County, FL","Hillsborough County, FL"]', json_encode($result['counties']));
    }

    /** An id with no matching option is skipped rather than emitted as a broken label. */
    public function test_an_unknown_id_is_skipped_not_guessed(): void
    {
        $result = $this->project(GeographySelection::of('1', ['10', '999']));

        $this->assertSame(['Pinellas County, FL'], $result['counties']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · PRESERVED LEGACY LABELS SURVIVE — THE DATA-SAFETY GUARANTEE
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * A stored label the corpus cannot match is carried through untouched.
     *
     * This is the approved answer to the largest risk in Phase 1c. A record saved years ago may
     * hold a county spelling the reference tables do not contain. Dropping it would delete a
     * user's selection during an edit they did not make — silently, and with no way to notice.
     */
    public function test_an_unmatched_legacy_county_is_carried_through(): void
    {
        $result = $this->project(
            GeographySelection::of('1', ['10']),
            preserved: new PreservedGeographyLabels(counties: ['Ye Olde County, FL']),
        );

        $this->assertSame(['Pinellas County, FL', 'Ye Olde County, FL'], $result['counties']);
    }

    public function test_unmatched_legacy_cities_and_zips_are_carried_through(): void
    {
        $result = $this->project(
            GeographySelection::of('1', ['10'], ['100'], ['33708']),
            preserved: new PreservedGeographyLabels(
                cities: ['Nowhereville, XX'],
                zipCodes: ['ABCDE'],
            ),
        );

        $this->assertSame(['St. Petersburg, FL', 'Nowhereville, XX'], $result['cities']);
        $this->assertSame(['33708', 'ABCDE'], $result['zip_codes']);
    }

    /** A preserved state is used only when the cascade holds none — never over a live choice. */
    public function test_a_preserved_state_fills_in_only_when_nothing_is_selected(): void
    {
        $withSelection = $this->project(
            GeographySelection::of('1', ['10']),
            preserved: new PreservedGeographyLabels(state: 'Old Dominion'),
        );
        $withoutSelection = $this->project(
            GeographySelection::empty(),
            null,
            null,
            new PreservedGeographyLabels(state: 'Old Dominion'),
        );

        $this->assertSame('Florida', $withSelection['state']);
        $this->assertSame('Old Dominion', $withoutSelection['state']);
    }

    /** A preserved label that the cascade also produced must not appear twice. */
    public function test_a_preserved_label_is_not_duplicated(): void
    {
        $result = $this->project(
            GeographySelection::of('1', ['10']),
            preserved: new PreservedGeographyLabels(counties: ['Pinellas County, FL']),
        );

        $this->assertSame(['Pinellas County, FL'], $result['counties']);
    }

    /** Selected values lead; preserved values trail. Order is stable so saves are idempotent. */
    public function test_selected_values_lead_and_preserved_values_trail(): void
    {
        $result = $this->project(
            GeographySelection::of('1', ['11', '10']),
            preserved: new PreservedGeographyLabels(counties: ['A County, FL', 'B County, FL']),
        );

        $this->assertSame(
            ['Hillsborough County, FL', 'Pinellas County, FL', 'A County, FL', 'B County, FL'],
            $result['counties'],
        );
    }

    /** Projecting twice yields the same result — the save path must not drift on re-save. */
    public function test_projection_is_idempotent(): void
    {
        $selection = GeographySelection::of('1', ['10', '11'], ['100'], ['33708']);
        $preserved = new PreservedGeographyLabels(counties: ['Ye Olde County, FL']);

        $this->assertSame(
            $this->project($selection, preserved: $preserved),
            $this->project($selection, preserved: $preserved),
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Phase 1d-5 · NEIGHBOURHOODS FOLD INTO `cities` (Decision 1A)
    // ═════════════════════════════════════════════════════════════════════════

    /** @return list<GeographyOption> */
    private function neighborhoods(): array
    {
        return [
            GeographyOption::neighborhood('900', 'Snell Isle', '100'),
            GeographyOption::neighborhood('901', 'Old Northeast', '100'),
        ];
    }

    private function projectWithNeighborhoods(
        GeographySelection $selection,
        ?PreservedGeographyLabels $preserved = null,
    ): array {
        return $this->projector->project(
            $selection,
            'Florida',
            'FL',
            $this->counties(),
            $this->cities(),
            $preserved ?? PreservedGeographyLabels::none(),
            $this->neighborhoods(),
        );
    }

    /**
     * The seventh argument is optional, and omitting it must reproduce the pre-1d-5 output exactly.
     * Every existing caller — including the Livewire trait, untouched by this slice — relies on it.
     */
    public function test_omitting_the_neighborhood_argument_projects_exactly_as_before(): void
    {
        $selection = GeographySelection::of('1', ['10'], ['100'], ['33708']);

        $this->assertSame(
            ['state' => 'Florida', 'counties' => ['Pinellas County, FL'], 'cities' => ['St. Petersburg, FL'], 'zip_codes' => ['33708']],
            $this->project($selection)
        );
    }

    /** A selection carrying neighbourhoods but projected without options emits no label for them. */
    public function test_a_neighborhood_with_no_matching_option_is_skipped_not_guessed_at(): void
    {
        $selection = GeographySelection::of('1', ['10'], ['100'], [], ['900']);

        $this->assertSame(['St. Petersburg, FL'], $this->project($selection)['cities']);
    }

    public function test_a_neighborhood_is_emitted_into_the_cities_key(): void
    {
        $selection = GeographySelection::of('1', ['10'], ['100'], [], ['900']);

        $result = $this->projectWithNeighborhoods($selection);

        $this->assertSame(['St. Petersburg, FL', 'Snell Isle, FL'], $result['cities']);
        $this->assertSame(['state', 'counties', 'cities', 'zip_codes'], array_keys($result));
        $this->assertArrayNotHasKey('neighborhoods', $result);
    }

    public function test_neighborhood_labels_carry_the_state_suffix(): void
    {
        $selection = GeographySelection::of('1', ['10'], [], [], ['900', '901']);

        $this->assertSame(['Snell Isle, FL', 'Old Northeast, FL'], $this->projectWithNeighborhoods($selection)['cities']);
    }

    public function test_order_is_cities_then_neighborhoods_then_preserved(): void
    {
        $selection = GeographySelection::of('1', ['10'], ['100'], [], ['900']);
        $preserved = new PreservedGeographyLabels(cities: ['Ye Olde Village, FL']);

        $this->assertSame(
            ['St. Petersburg, FL', 'Snell Isle, FL', 'Ye Olde Village, FL'],
            $this->projectWithNeighborhoods($selection, $preserved)['cities']
        );
    }

    public function test_a_neighborhood_sharing_a_city_label_is_not_duplicated(): void
    {
        $projector = $this->projector;

        $result = $projector->project(
            GeographySelection::of('1', ['10'], ['100'], [], ['902']),
            'Florida',
            'FL',
            $this->counties(),
            $this->cities(),
            PreservedGeographyLabels::none(),
            [GeographyOption::neighborhood('902', 'St. Petersburg', '100')],
        );

        $this->assertSame(['St. Petersburg, FL'], $result['cities']);
    }

    public function test_projection_with_neighborhoods_is_idempotent(): void
    {
        $selection = GeographySelection::of('1', ['10'], ['100'], ['33708'], ['900', '901']);

        $this->assertSame(
            $this->projectWithNeighborhoods($selection),
            $this->projectWithNeighborhoods($selection),
        );
    }
}
