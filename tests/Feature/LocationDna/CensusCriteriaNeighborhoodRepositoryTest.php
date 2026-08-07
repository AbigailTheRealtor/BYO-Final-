<?php

namespace Tests\Feature\LocationDna;

use App\Models\LocationPlace;
use App\Services\LocationDna\Criteria\CensusCriteriaGeographyRepository;
use App\Services\LocationDna\Criteria\CensusCriteriaNeighborhoodRepository;
use App\Services\LocationDna\Criteria\GeographyOption;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 1d-5, slice 2 — the neighbourhood repository against the real `location_places` schema.
 *
 * THE ASSERTION THAT MATTERS MOST IS THE JOIN
 * -------------------------------------------
 * This tier only works because a census city option's id and `location_places.census_place_geoid`
 * are the same seven-digit string. That equivalence is an accident of two separate design choices
 * made in different phases, nothing enforces it at the type level, and if it ever broke the symptom
 * would be an empty neighbourhood list — not an error. So it is asserted directly, by asking the
 * geography repository for a city option and feeding its id straight into this one.
 */
class CensusCriteriaNeighborhoodRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private CensusCriteriaNeighborhoodRepository $repo;

    /** Clearwater, as a `location_places` row. */
    private LocationPlace $clearwater;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = new CensusCriteriaNeighborhoodRepository();

        DB::table('census_states')->insert([
            ['geoid' => '12', 'usps' => 'FL', 'name' => 'Florida'],
        ]);

        DB::table('census_counties')->insert([
            ['geoid' => '12103', 'state_geoid' => '12', 'countyfp' => '103', 'name' => 'Pinellas County', 'basename' => 'Pinellas'],
        ]);

        DB::table('census_places')->insert([
            ['geoid' => '1212875', 'state_geoid' => '12', 'placefp' => '12875', 'name' => 'Clearwater',     'namelsad' => 'Clearwater city', 'classfp' => 'C1'],
            ['geoid' => '1263000', 'state_geoid' => '12', 'placefp' => '63000', 'name' => 'St. Petersburg', 'namelsad' => 'St. Petersburg city', 'classfp' => 'C1'],
        ]);

        DB::table('census_place_counties')->insert([
            ['place_geoid' => '1212875', 'county_geoid' => '12103'],
            ['place_geoid' => '1263000', 'county_geoid' => '12103'],
        ]);

        $this->clearwater = $this->city('Clearwater', '1212875');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Fixture builders
    // ─────────────────────────────────────────────────────────────────────

    private function city(string $name, string $geoid, bool $active = true): LocationPlace
    {
        return LocationPlace::create([
            'name'               => $name,
            'type'               => LocationPlace::TYPE_CITY,
            'state_geoid'        => '12',
            'county_geoid'       => '12103',
            'census_place_geoid' => $geoid,
            'source'             => LocationPlace::SOURCE_CENSUS,
            'active'             => $active,
        ]);
    }

    private function neighborhood(string $name, ?LocationPlace $parent, array $overrides = []): LocationPlace
    {
        return LocationPlace::create(array_merge([
            'name'            => $name,
            'type'            => LocationPlace::TYPE_NEIGHBORHOOD,
            'state_geoid'     => '12',
            'county_geoid'    => '12103',
            'parent_place_id' => $parent?->id,
            'source'          => LocationPlace::SOURCE_CURATED,
            'active'          => true,
        ], $overrides));
    }

    /** @return list<string> */
    private function names(array $cityIds): array
    {
        return array_map(
            fn (GeographyOption $o): string => $o->name,
            $this->repo->neighborhoodsInCities($cityIds)
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // The join
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function a_city_option_id_from_the_geography_repository_finds_its_neighbourhoods(): void
    {
        $this->neighborhood('Clearwater Beach', $this->clearwater);

        $cityOption = collect((new CensusCriteriaGeographyRepository())->citiesInCounties(['12103']))
            ->firstWhere('name', 'Clearwater');

        $this->assertNotNull($cityOption, 'The geography repository must still enumerate Clearwater.');

        // The id is handed straight across with no translation — that IS the contract.
        $found = $this->repo->neighborhoodsInCities([$cityOption->id]);

        $this->assertCount(1, $found);
        $this->assertSame('Clearwater Beach', $found[0]->name);
        $this->assertSame($cityOption->id, $found[0]->parentId);
    }

    /** @test */
    public function the_emitted_option_is_a_neighbourhood_parented_by_its_city(): void
    {
        $beach = $this->neighborhood('Clearwater Beach', $this->clearwater);

        $option = $this->repo->neighborhoodsInCities(['1212875'])[0];

        $this->assertTrue($option->is(GeographyOption::KIND_NEIGHBORHOOD));
        $this->assertFalse($option->is(GeographyOption::KIND_CITY));
        $this->assertSame((string) $beach->id, $option->id);
        $this->assertSame('1212875', $option->parentId);
        $this->assertNull($option->code);
    }

    // ─────────────────────────────────────────────────────────────────────
    // What is and is not enumerable
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function a_community_with_no_parent_is_not_offered(): void
    {
        // ~7,000 supplemental rows are in this state. They name somewhere real, but the USPS corpus
        // never says which municipality contains them, so no selected city can justify one.
        $this->neighborhood('Ozona', null, [
            'type'   => LocationPlace::TYPE_COMMUNITY,
            'source' => LocationPlace::SOURCE_SUPPLEMENTAL,
        ]);

        $this->assertSame([], $this->names(['1212875']));
    }

    /** @test */
    public function a_parented_community_is_offered_alongside_neighbourhoods(): void
    {
        $this->neighborhood('Clearwater Beach', $this->clearwater);
        $this->neighborhood('Ozona', $this->clearwater, ['type' => LocationPlace::TYPE_COMMUNITY]);

        $this->assertSame(['Clearwater Beach', 'Ozona'], $this->names(['1212875']));
    }

    /** @test */
    public function an_inactive_neighbourhood_is_not_offered(): void
    {
        $this->neighborhood('Clearwater Beach', $this->clearwater)->update(['active' => false]);

        $this->assertSame([], $this->names(['1212875']));
    }

    /** @test */
    public function a_neighbourhood_under_an_inactive_city_is_not_offered(): void
    {
        $this->neighborhood('Clearwater Beach', $this->clearwater);
        $this->clearwater->update(['active' => false]);

        $this->assertSame([], $this->names(['1212875']), 'The tier above it is gone.');
    }

    /** @test */
    public function a_city_is_never_returned_as_its_own_neighbourhood(): void
    {
        $this->neighborhood('Clearwater Beach', $this->clearwater);

        foreach ($this->repo->neighborhoodsInCities(['1212875']) as $option) {
            $this->assertNotSame('Clearwater', $option->name);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Selection semantics
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function only_the_requested_cities_neighbourhoods_come_back(): void
    {
        $stPete = $this->city('St. Petersburg', '1263000');

        $this->neighborhood('Clearwater Beach', $this->clearwater);
        $this->neighborhood('Snell Isle', $stPete);

        $this->assertSame(['Clearwater Beach'], $this->names(['1212875']));
        $this->assertSame(['Snell Isle'], $this->names(['1263000']));
        $this->assertSame(['Clearwater Beach', 'Snell Isle'], $this->names(['1212875', '1263000']));
    }

    /** @test */
    public function results_are_ordered_by_name(): void
    {
        $this->neighborhood('Sand Key', $this->clearwater);
        $this->neighborhood('Clearwater Beach', $this->clearwater);
        $this->neighborhood('Island Estates', $this->clearwater);

        $this->assertSame(['Clearwater Beach', 'Island Estates', 'Sand Key'], $this->names(['1212875']));
    }

    /** @test */
    public function a_repeated_city_id_does_not_duplicate_its_neighbourhoods(): void
    {
        $this->neighborhood('Clearwater Beach', $this->clearwater);

        $this->assertSame(['Clearwater Beach'], $this->names(['1212875', '1212875']));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Identifier discipline
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function an_empty_city_list_yields_nothing_without_querying(): void
    {
        $this->neighborhood('Clearwater Beach', $this->clearwater);

        $this->assertSame([], $this->repo->neighborhoodsInCities([]));
    }

    /** @test */
    public function a_wrong_width_city_id_is_refused_rather_than_coerced(): void
    {
        $this->neighborhood('Clearwater Beach', $this->clearwater);

        // A us_cities surrogate key, a truncated GEOID, a GEOID that lost its leading zero, and a
        // name. None may be padded or cast into a place that happens to exist.
        foreach (['227', '121287', '212875', 'Clearwater', ''] as $bad) {
            $this->assertSame([], $this->repo->neighborhoodsInCities([$bad]), "accepted: {$bad}");
        }
    }

    /** @test */
    public function a_well_formed_id_still_works_alongside_a_malformed_one(): void
    {
        $this->neighborhood('Clearwater Beach', $this->clearwater);

        $this->assertSame(['Clearwater Beach'], $this->names(['227', '1212875']));
    }

    /** @test */
    public function an_unknown_city_geoid_yields_nothing(): void
    {
        $this->neighborhood('Clearwater Beach', $this->clearwater);

        $this->assertSame([], $this->names(['9999999']));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Read-only
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function enumerating_writes_nothing(): void
    {
        $this->neighborhood('Clearwater Beach', $this->clearwater);

        $before = [
            DB::table('location_places')->count(),
            DB::table('census_places')->count(),
        ];

        $this->repo->neighborhoodsInCities(['1212875', 'garbage']);

        $this->assertSame($before, [
            DB::table('location_places')->count(),
            DB::table('census_places')->count(),
        ]);
    }
}
