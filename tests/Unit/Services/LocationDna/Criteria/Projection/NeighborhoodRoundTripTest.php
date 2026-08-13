<?php

namespace Tests\Unit\Services\LocationDna\Criteria\Projection;

use App\Services\LocationDna\Criteria\FakeCriteriaGeographyRepository;
use App\Services\LocationDna\Criteria\FakeCriteriaNeighborhoodRepository;
use App\Services\LocationDna\Criteria\GeographyOption;
use App\Services\LocationDna\Criteria\Projection\GeographyLabelProjector;
use App\Services\LocationDna\Criteria\Projection\GeographySelectionHydrator;
use App\Services\LocationDna\Criteria\Rules\GeographySelectionResolver;
use App\Services\LocationDna\Criteria\Rules\GeographyTier;
use PHPUnit\Framework\TestCase;

/**
 * Phase 1d-5, slice 4 — Decision 1A end to end: blob → selection → blob.
 *
 * WHY A ROUND-TRIP SUITE RATHER THAN TWO UNIT SUITES
 * --------------------------------------------------
 * The projector and the hydrator are each simple and each already covered. The risk this phase
 * introduces lives BETWEEN them: neighbourhoods are stored in the `cities` array, so hydration has
 * to recognise a label the city tier rejected, and projection has to put it back in the same place
 * without duplicating the cities beside it. Either half can be correct in isolation while the pair
 * silently loses, duplicates or reorders a label — and every one of those failures shows up as
 * stored data, not as an exception.
 *
 * THE RESOLVER IS IN THE LOOP ON PURPOSE
 * --------------------------------------
 * `HasGeographyCascade::loadGeographyCascade()` hydrates and then immediately resolves. A
 * neighbourhood promoted by hydration but not justified by the resolver would be cleared one line
 * later, and a cleared selection is NOT preserved — the label would be gone from the next save.
 * So several tests here run hydrate → resolve → project, which is the real sequence, rather than
 * hydrate → project, which is the flattering one.
 *
 * No database. Both fakes, both holding the Pinellas shape the phase was built around.
 */
class NeighborhoodRoundTripTest extends TestCase
{
    private const FL         = '12';
    private const PINELLAS   = '12103';
    private const CLEARWATER = '1212875';
    private const ST_PETE    = '1263000';
    private const BEACH      = '900';   // Clearwater Beach, a neighbourhood of Clearwater
    private const SAND_KEY   = '901';   // Sand Key, likewise

    private function geography(): FakeCriteriaGeographyRepository
    {
        return (new FakeCriteriaGeographyRepository())
            ->withState(self::FL, 'Florida', '12')
            ->withCounty(self::PINELLAS, 'Pinellas County', self::FL, '12103')
            ->withCity(self::CLEARWATER, 'Clearwater', self::PINELLAS)
            ->withCity(self::ST_PETE, 'St. Petersburg', self::PINELLAS)
            ->withZip('33767', self::PINELLAS);
    }

    private function neighborhoods(): FakeCriteriaNeighborhoodRepository
    {
        return (new FakeCriteriaNeighborhoodRepository())
            ->withNeighborhood(self::BEACH, 'Clearwater Beach', self::CLEARWATER)
            ->withNeighborhood(self::SAND_KEY, 'Sand Key', self::CLEARWATER);
    }

    /**
     * blob → hydrate → resolve → project → blob, the sequence the Livewire host actually runs.
     *
     * @param  bool  $tierOn  false reproduces today's production wiring, where the tier is bound to
     *                        the null object and nothing can recognise a neighbourhood
     * @return array{state: string, counties: list<string>, cities: list<string>, zip_codes: list<string>}
     */
    private function roundTrip(array $blob, bool $tierOn = true): array
    {
        $geography     = $this->geography();
        $neighborhoods = $this->neighborhoods();

        $hydrator = $tierOn
            ? new GeographySelectionHydrator($geography, $neighborhoods)
            : new GeographySelectionHydrator($geography);

        $hydrated = $hydrator->fromLabels($blob);

        $resolver = $tierOn
            ? new GeographySelectionResolver($geography, $neighborhoods)
            : new GeographySelectionResolver($geography);

        $selection = $resolver->resolve($hydrated->selection)->selection;

        $stateName = null;
        $abbrev    = null;

        foreach ($geography->states() as $option) {
            if ($option->id === $selection->stateId) {
                $stateName = $option->name;
                $abbrev    = 'FL';
            }
        }

        $countyOptions       = $selection->hasState() ? $geography->countiesInState((string) $selection->stateId) : [];
        $cityOptions         = $geography->citiesInCounties($selection->countyIds);
        $neighborhoodOptions = $tierOn ? $neighborhoods->neighborhoodsInCities($selection->cityIds) : [];

        return (new GeographyLabelProjector())->project(
            $selection,
            $stateName,
            $abbrev,
            $countyOptions,
            $cityOptions,
            $hydrated->preserved,
            $neighborhoodOptions,
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · EXISTING CITY-ONLY SELECTIONS HYDRATE UNCHANGED
    // ═════════════════════════════════════════════════════════════════════════

    /** @test */
    public function a_city_only_blob_round_trips_byte_for_byte_with_the_tier_on(): void
    {
        $blob = [
            'state'     => 'Florida',
            'counties'  => ['Pinellas County, FL'],
            'cities'    => ['Clearwater, FL', 'St. Petersburg, FL'],
            'zip_codes' => ['33767'],
        ];

        $this->assertSame($blob, $this->roundTrip($blob));
    }

    /** @test */
    public function a_city_only_blob_is_identical_whether_the_tier_is_on_or_off(): void
    {
        $blob = [
            'state'     => 'Florida',
            'counties'  => ['Pinellas County, FL'],
            'cities'    => ['Clearwater, FL', 'St. Petersburg, FL'],
            'zip_codes' => ['33767'],
        ];

        $this->assertSame(
            $this->roundTrip($blob, false),
            $this->roundTrip($blob, true),
            'Turning the tier on must not change a blob that names no neighbourhood.'
        );
    }

    /** @test */
    public function hydration_of_a_city_only_blob_selects_no_neighbourhoods(): void
    {
        $hydrated = (new GeographySelectionHydrator($this->geography(), $this->neighborhoods()))
            ->fromLabels([
                'state'    => 'Florida',
                'counties' => ['Pinellas County, FL'],
                'cities'   => ['Clearwater, FL'],
            ]);

        $this->assertSame([self::CLEARWATER], $hydrated->selection->cityIds);
        $this->assertSame([], $hydrated->selection->neighborhoodIds);
        $this->assertSame([], $hydrated->preserved->cities);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · NEIGHBOURHOOD SELECTIONS PROJECT CORRECTLY
    // ═════════════════════════════════════════════════════════════════════════

    /** @test */
    public function a_neighbourhood_is_recognised_and_projected_back_into_cities(): void
    {
        $blob = [
            'state'     => 'Florida',
            'counties'  => ['Pinellas County, FL'],
            'cities'    => ['Clearwater, FL', 'Clearwater Beach, FL'],
            'zip_codes' => [],
        ];

        $out = $this->roundTrip($blob);

        $this->assertSame(['Clearwater, FL', 'Clearwater Beach, FL'], $out['cities']);
        $this->assertArrayNotHasKey('neighborhoods', $out, 'Decision 1A: there is no fifth key.');
        $this->assertSame(['state', 'counties', 'cities', 'zip_codes'], array_keys($out));
    }

    /** @test */
    public function hydration_splits_a_shared_cities_array_into_two_tiers(): void
    {
        $hydrated = (new GeographySelectionHydrator($this->geography(), $this->neighborhoods()))
            ->fromLabels([
                'state'    => 'Florida',
                'counties' => ['Pinellas County, FL'],
                'cities'   => ['Clearwater, FL', 'Clearwater Beach, FL', 'Sand Key, FL'],
            ]);

        $this->assertSame([self::CLEARWATER], $hydrated->selection->cityIds);
        $this->assertSame([self::BEACH, self::SAND_KEY], $hydrated->selection->idsFor(GeographyTier::Neighborhoods));
        $this->assertSame([], $hydrated->preserved->cities, 'Everything was recognised.');
    }

    /** @test */
    public function the_round_trip_is_stable_across_repeated_saves(): void
    {
        $blob = [
            'state'     => 'Florida',
            'counties'  => ['Pinellas County, FL'],
            'cities'    => ['Clearwater, FL', 'Clearwater Beach, FL'],
            'zip_codes' => ['33767'],
        ];

        $once  = $this->roundTrip($blob);
        $twice = $this->roundTrip($once);

        $this->assertSame($once, $twice, 'A no-op save must not look like a change.');
    }

    /** @test */
    public function selected_cities_come_before_neighbourhoods_and_history_comes_last(): void
    {
        $blob = [
            'state'    => 'Florida',
            'counties' => ['Pinellas County, FL'],
            'cities'   => ['Clearwater Beach, FL', 'Nowhere At All, FL', 'Clearwater, FL'],
        ];

        $out = $this->roundTrip($blob);

        $this->assertSame(
            ['Clearwater, FL', 'Clearwater Beach, FL', 'Nowhere At All, FL'],
            $out['cities'],
            'Fixed ordering: cities, then neighbourhoods, then preserved history.'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · CLEARWATER BEACH AND SUPPLEMENTAL PLACES ARE NOT LOST
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * @test
     *
     * The load-bearing safety case. Without its parent city the neighbourhood cannot be justified,
     * so it must stay PRESERVED — promoting it would hand the resolver something it clears one line
     * later, and a cleared selection is not preserved.
     */
    public function clearwater_beach_without_its_parent_city_is_preserved_not_lost(): void
    {
        $blob = [
            'state'     => 'Florida',
            'counties'  => ['Pinellas County, FL'],
            'cities'    => ['Clearwater Beach, FL'],
            'zip_codes' => [],
        ];

        $hydrated = (new GeographySelectionHydrator($this->geography(), $this->neighborhoods()))
            ->fromLabels($blob);

        $this->assertSame([], $hydrated->selection->idsFor(GeographyTier::Neighborhoods));
        $this->assertSame(['Clearwater Beach, FL'], $hydrated->preserved->cities);

        // And it survives the full sequence, which is what actually matters.
        $this->assertSame(['Clearwater Beach, FL'], $this->roundTrip($blob)['cities']);
    }

    /** @test */
    public function clearwater_beach_survives_with_the_tier_off_exactly_as_it_does_today(): void
    {
        $blob = [
            'state'     => 'Florida',
            'counties'  => ['Pinellas County, FL'],
            'cities'    => ['Clearwater, FL', 'Clearwater Beach, FL'],
            'zip_codes' => [],
        ];

        $this->assertSame(
            ['Clearwater, FL', 'Clearwater Beach, FL'],
            $this->roundTrip($blob, false)['cities']
        );
    }

    /** @test */
    public function an_unmatchable_supplemental_label_is_never_dropped(): void
    {
        $blob = [
            'state'     => 'Florida',
            'counties'  => ['Pinellas County, FL'],
            'cities'    => ['Clearwater, FL', 'Ozona, FL', 'Bardmoor, FL'],
            'zip_codes' => [],
        ];

        $out = $this->roundTrip($blob);

        foreach (['Clearwater, FL', 'Ozona, FL', 'Bardmoor, FL'] as $label) {
            $this->assertContains($label, $out['cities'], "Lost: {$label}");
        }
    }

    /** @test */
    public function nothing_is_lost_when_the_state_itself_cannot_be_resolved(): void
    {
        $blob = [
            'state'     => 'Atlantis',
            'counties'  => ['Nowhere County, XX'],
            'cities'    => ['Clearwater Beach, FL'],
            'zip_codes' => ['99999'],
        ];

        $out = $this->roundTrip($blob);

        $this->assertSame('Atlantis', $out['state']);
        $this->assertSame(['Nowhere County, XX'], $out['counties']);
        $this->assertSame(['Clearwater Beach, FL'], $out['cities']);
        $this->assertSame(['99999'], $out['zip_codes']);
    }

    /** @test */
    public function a_legacy_saint_spelling_still_resolves_alongside_a_neighbourhood(): void
    {
        $blob = [
            'state'    => 'Florida',
            'counties' => ['Pinellas County, FL'],
            'cities'   => ['Saint Petersburg, FL', 'Clearwater, FL', 'Clearwater Beach, FL'],
        ];

        $out = $this->roundTrip($blob);

        // Two properties at once:
        //
        //   1. The corpus SPELLING wins on re-emission — `Saint Petersburg` comes back as
        //      `St. Petersburg`, exactly as it did before this tier existed.
        //   2. The stored ORDER is kept. Cities are emitted in the order the blob listed them, not
        //      in corpus enumeration order, because the selection carries label order through
        //      hydration and the resolver is subtractive. That is what makes a no-op save produce
        //      identical bytes rather than a reshuffled array that every document comparison would
        //      read as a change.
        $this->assertSame(
            ['St. Petersburg, FL', 'Clearwater, FL', 'Clearwater Beach, FL'],
            $out['cities']
        );

        // And the whole thing is stable on a second pass.
        $this->assertSame($out, $this->roundTrip($out));
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 4 · NO DUPLICATE CITIES ARE CREATED
    // ═════════════════════════════════════════════════════════════════════════

    /** @test */
    public function a_label_appearing_twice_in_the_stored_blob_emits_once(): void
    {
        $out = $this->roundTrip([
            'state'    => 'Florida',
            'counties' => ['Pinellas County, FL'],
            'cities'   => ['Clearwater, FL', 'Clearwater, FL', 'Clearwater Beach, FL', 'Clearwater Beach, FL'],
        ]);

        $this->assertSame(['Clearwater, FL', 'Clearwater Beach, FL'], $out['cities']);
    }

    /** @test */
    public function the_projected_cities_array_never_contains_a_repeat(): void
    {
        $out = $this->roundTrip([
            'state'    => 'Florida',
            'counties' => ['Pinellas County, FL'],
            'cities'   => ['Clearwater, FL', 'Clearwater Beach, FL', 'Sand Key, FL', 'Ozona, FL', 'Ozona, FL'],
        ]);

        $this->assertSame(
            array_values(array_unique($out['cities'])),
            $out['cities'],
            'Cities and neighbourhoods share one key, so the merge must deduplicate across both.'
        );
    }

    /**
     * @test
     *
     * A neighbourhood sharing a city's name collapses to one entry rather than appearing twice.
     * Cities are labelled first, so the city wins the position — and the user sees one chip.
     */
    public function a_neighbourhood_named_like_a_city_does_not_duplicate_the_label(): void
    {
        $geography = $this->geography();

        $neighborhoods = (new FakeCriteriaNeighborhoodRepository())
            ->withNeighborhood('999', 'St. Petersburg', self::CLEARWATER);

        $hydrated = (new GeographySelectionHydrator($geography, $neighborhoods))->fromLabels([
            'state'    => 'Florida',
            'counties' => ['Pinellas County, FL'],
            'cities'   => ['Clearwater, FL', 'St. Petersburg, FL'],
        ]);

        $out = (new GeographyLabelProjector())->project(
            $hydrated->selection,
            'Florida',
            'FL',
            $geography->countiesInState(self::FL),
            $geography->citiesInCounties([self::PINELLAS]),
            $hydrated->preserved,
            $neighborhoods->neighborhoodsInCities($hydrated->selection->cityIds),
        );

        $this->assertSame(['Clearwater, FL', 'St. Petersburg, FL'], $out['cities']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // The storage contract itself
    // ═════════════════════════════════════════════════════════════════════════

    /** @test */
    public function the_projection_always_emits_exactly_the_four_canonical_keys(): void
    {
        foreach (
            [
                ['state' => 'Florida', 'counties' => ['Pinellas County, FL'], 'cities' => ['Clearwater, FL', 'Clearwater Beach, FL']],
                ['state' => 'Florida', 'counties' => [], 'cities' => [], 'zip_codes' => []],
                [],
            ] as $blob
        ) {
            $out = $this->roundTrip($blob);

            $this->assertSame(
                ['state', 'counties', 'cities', 'zip_codes'],
                array_keys($out),
                'Decision 1A: four keys, always, whatever the tier resolved.'
            );
        }
    }

    /** @test */
    public function a_neighbourhood_option_is_labelled_with_the_state_suffix_like_every_other_tier(): void
    {
        $out = (new GeographyLabelProjector())->project(
            \App\Services\LocationDna\Criteria\Rules\GeographySelection::of(
                self::FL,
                [self::PINELLAS],
                [],
                [],
                [self::BEACH],
            ),
            'Florida',
            'FL',
            [],
            [],
            \App\Services\LocationDna\Criteria\Projection\PreservedGeographyLabels::none(),
            [GeographyOption::neighborhood(self::BEACH, 'Clearwater Beach', self::CLEARWATER)],
        );

        $this->assertSame(['Clearwater Beach, FL'], $out['cities']);
    }
}
