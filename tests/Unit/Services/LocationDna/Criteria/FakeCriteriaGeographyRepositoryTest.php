<?php

namespace Tests\Unit\Services\LocationDna\Criteria;

use App\Services\LocationDna\Criteria\FakeCriteriaGeographyRepository;
use App\Services\LocationDna\Criteria\GeographyOption;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Phase 1a — the fake repository and the option DTO.
 *
 * WHY THE FAKE IS TESTED AT ALL
 * -----------------------------
 * Phase 1b's cascade rules will be written against this fake, so a defect here would silently
 * weaken every test that depends on it — the classic "the fixture was wrong" failure. It is
 * therefore held to the same contract as the real implementation: same ordering, same
 * many-to-many ZIP behaviour, same empty-input handling.
 *
 * No database. This suite runs on plain PHPUnit.
 */
class FakeCriteriaGeographyRepositoryTest extends TestCase
{
    private function populated(): FakeCriteriaGeographyRepository
    {
        return (new FakeCriteriaGeographyRepository())
            ->withState('12', 'Florida', '12')
            ->withState('01', 'Alabama', '01')
            ->withCounty('c-hills', 'Hillsborough County', '12', '12057')
            ->withCounty('c-pin', 'Pinellas County', '12', '12103')
            ->withCounty('c-bald', 'Baldwin County', '01', '01003')
            ->withCity('city-tampa', 'Tampa', 'c-hills')
            ->withCity('city-clw', 'Clearwater', 'c-pin')
            ->withCity('city-fair', 'Fairhope', 'c-bald')
            ->withZip('33602', 'c-hills')
            ->withZip('33701', 'c-pin')
            ->withZip('33755', 'c-pin');
    }

    // ── states ───────────────────────────────────────────────────────────────

    public function test_states_are_returned_sorted_by_name(): void
    {
        $states = $this->populated()->states();

        $this->assertSame(['Alabama', 'Florida'], array_map(fn ($o) => $o->name, $states));
        $this->assertSame(GeographyOption::KIND_STATE, $states[0]->kind);
        $this->assertNull($states[0]->parentId, 'a state has no parent');
    }

    // ── counties ─────────────────────────────────────────────────────────────

    public function test_counties_are_scoped_to_their_state_and_sorted(): void
    {
        $counties = $this->populated()->countiesInState('12');

        $this->assertSame(
            ['Hillsborough County', 'Pinellas County'],
            array_map(fn ($o) => $o->name, $counties)
        );

        foreach ($counties as $county) {
            $this->assertSame('12', $county->parentId);
            $this->assertSame(GeographyOption::KIND_COUNTY, $county->kind);
        }
    }

    public function test_an_unknown_state_yields_no_counties_rather_than_all_of_them(): void
    {
        $this->assertSame([], $this->populated()->countiesInState('99'));
    }

    /** A name is not an id. Passing one must return nothing, never a guess. */
    public function test_a_display_name_is_not_accepted_as_a_state_id(): void
    {
        $this->assertSame([], $this->populated()->countiesInState('Florida'));
    }

    // ── cities ───────────────────────────────────────────────────────────────

    public function test_cities_span_the_selected_counties_only(): void
    {
        $cities = $this->populated()->citiesInCounties(['c-hills', 'c-pin']);

        $this->assertSame(['Clearwater', 'Tampa'], array_map(fn ($o) => $o->name, $cities));
        $this->assertNotContains('Fairhope', array_map(fn ($o) => $o->name, $cities));
    }

    public function test_no_counties_selected_yields_no_cities(): void
    {
        $this->assertSame([], $this->populated()->citiesInCounties([]));
    }

    // ── zips ─────────────────────────────────────────────────────────────────

    public function test_zips_are_scoped_to_the_selected_counties_and_sorted_by_code(): void
    {
        $zips = $this->populated()->zipsInCounties(['c-pin']);

        $this->assertSame(['33701', '33755'], array_map(fn ($o) => $o->id, $zips));
        $this->assertSame(GeographyOption::KIND_ZIP, $zips[0]->kind);
        $this->assertSame('c-pin', $zips[0]->parentId);
    }

    /**
     * A ZIP crossing two selected counties is emitted once PER COUNTY.
     *
     * This is the many-to-many the interface documents, not a duplicate. ZCTAs genuinely cross
     * county lines, and a consumer that assumed containment would drop one of the parents.
     */
    public function test_a_zip_spanning_two_counties_is_emitted_under_each(): void
    {
        $repo = (new FakeCriteriaGeographyRepository())
            ->withState('12', 'Florida')
            ->withCounty('c-a', 'A County', '12')
            ->withCounty('c-b', 'B County', '12')
            ->withZip('34677', 'c-a')
            ->withZip('34677', 'c-b');

        $zips = $repo->zipsInCounties(['c-a', 'c-b']);

        $this->assertCount(2, $zips);
        $this->assertSame(['34677', '34677'], array_map(fn ($o) => $o->id, $zips));
        $this->assertEqualsCanonicalizing(
            ['c-a', 'c-b'],
            array_map(fn ($o) => $o->parentId, $zips),
            'each parent county must be represented'
        );
    }

    public function test_an_empty_repository_returns_empty_lists_everywhere(): void
    {
        $repo = new FakeCriteriaGeographyRepository();

        $this->assertSame([], $repo->states());
        $this->assertSame([], $repo->countiesInState('12'));
        $this->assertSame([], $repo->citiesInCounties(['c-a']));
        $this->assertSame([], $repo->zipsInCounties(['c-a']));
    }

    // ── the DTO's own invariants ─────────────────────────────────────────────

    public function test_an_unknown_kind_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new GeographyOption('parish', 'x', 'X');
    }

    public function test_a_non_state_option_must_name_its_parent(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new GeographyOption(GeographyOption::KIND_COUNTY, 'c-1', 'Somewhere County');
    }

    public function test_a_state_may_not_have_a_parent(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new GeographyOption(GeographyOption::KIND_STATE, '12', 'Florida', '12', 'us');
    }

    public function test_an_empty_id_or_name_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        GeographyOption::state('', 'Florida');
    }

    public function test_to_array_exposes_the_wire_shape(): void
    {
        $this->assertSame(
            ['kind' => 'county', 'id' => 'c-1', 'name' => 'Pinellas County', 'code' => '12103', 'parent_id' => '12', 'abbreviation' => null],
            GeographyOption::county('c-1', 'Pinellas County', '12', '12103')->toArray()
        );
    }
}
