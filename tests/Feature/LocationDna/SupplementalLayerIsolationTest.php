<?php

namespace Tests\Feature\LocationDna;

use App\Models\LocationPlace;
use App\Services\LocationDna\Criteria\CensusCriteriaGeographyRepository;
use App\Services\LocationDna\Criteria\Projection\GeographySelectionHydrator;
use App\Services\LocationDna\Criteria\Rules\GeographyTier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 1d-4 — the supplemental layer is INERT with respect to the live cascade.
 *
 * WHY A TEST FOR SOMETHING THAT DOES NOT HAPPEN
 * ---------------------------------------------
 * The neighbourhood tier is deliberately not wired into `GeographyTier`, the validator, the
 * projector, the hydrator or the four Livewire wizards yet. Until it is, the correct behaviour for
 * a stored `Clearwater Beach, FL` is the one it has always had: PRESERVED VERBATIM, matched
 * against nothing.
 *
 * That is easy to state and easy to break by accident. The moment someone wires the resolver into
 * the hydrator "just to see", every stored blob starts round-tripping differently — and because
 * the hydrator's contract is to preserve rather than to error, the change would be silent. This
 * suite fails loudly instead, and it will keep failing until the wiring is done deliberately, with
 * its own tests, and these expectations are updated as part of that work.
 *
 * It also pins the property that made adding the layer safe in the first place: 39,282 new rows
 * exist, and the cascade behaves exactly as it did when there were none.
 */
class SupplementalLayerIsolationTest extends TestCase
{
    use DatabaseTransactions;

    private GeographySelectionHydrator $hydrator;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('census_states')->insert([
            ['geoid' => '12', 'usps' => 'FL', 'name' => 'Florida'],
        ]);

        DB::table('census_counties')->insert([
            ['geoid' => '12103', 'state_geoid' => '12', 'countyfp' => '103', 'name' => 'Pinellas County', 'basename' => 'Pinellas'],
        ]);

        DB::table('census_places')->insert([
            ['geoid' => '1212875', 'state_geoid' => '12', 'placefp' => '12875', 'name' => 'Clearwater', 'namelsad' => 'Clearwater city', 'classfp' => 'C1'],
        ]);

        DB::table('census_place_counties')->insert([
            ['place_geoid' => '1212875', 'county_geoid' => '12103'],
        ]);

        DB::table('census_zctas')->insert([['zcta5' => '33767']]);
        DB::table('census_zcta_counties')->insert([['zcta5' => '33767', 'county_geoid' => '12103']]);

        // The supplemental layer, fully populated — and the whole point is that it changes nothing.
        $clearwater = LocationPlace::create([
            'name' => 'Clearwater', 'type' => LocationPlace::TYPE_CITY, 'state_geoid' => '12',
            'county_geoid' => '12103', 'census_place_geoid' => '1212875',
            'source' => LocationPlace::SOURCE_CENSUS, 'active' => true,
        ]);

        LocationPlace::create([
            'name' => 'Clearwater Beach', 'type' => LocationPlace::TYPE_NEIGHBORHOOD, 'state_geoid' => '12',
            'county_geoid' => '12103', 'parent_place_id' => $clearwater->id,
            'source' => LocationPlace::SOURCE_CURATED, 'active' => true,
        ]);

        $this->hydrator = new GeographySelectionHydrator(new CensusCriteriaGeographyRepository());
    }

    /** @test */
    public function a_neighbourhood_label_is_still_preserved_rather_than_matched_by_the_cascade(): void
    {
        $result = $this->hydrator->fromLabels([
            'state'     => 'Florida',
            'counties'  => ['Pinellas County, FL'],
            'cities'    => ['Clearwater, FL', 'Clearwater Beach, FL'],
            'zip_codes' => ['33767'],
        ]);

        // Clearwater is a Census place and matches. Clearwater Beach is not, and must not start
        // matching just because the supplemental layer now knows what it is.
        $this->assertSame(['1212875'], $result->selection->idsFor(GeographyTier::Cities));
        $this->assertSame(['Clearwater Beach, FL'], $result->preserved->cities);
    }

    /** @test */
    public function nothing_is_lost_from_a_stored_blob(): void
    {
        $result = $this->hydrator->fromLabels([
            'state'     => 'Florida',
            'counties'  => ['Pinellas County, FL'],
            'cities'    => ['Clearwater Beach, FL', 'Saint Petersburg, FL'],
            'zip_codes' => ['33767', '00501', 'notazip'],
        ]);

        $survivors = array_merge(
            $result->selection->idsFor(GeographyTier::Cities),
            $result->preserved->cities,
            $result->selection->idsFor(GeographyTier::ZipCodes),
            $result->preserved->zipCodes,
        );

        foreach (['Clearwater Beach, FL', 'Saint Petersburg, FL', 'notazip'] as $label) {
            $this->assertContains($label, $survivors, "Lost: {$label}");
        }
    }

    /** @test */
    public function the_cascade_repository_does_not_read_the_supplemental_tables(): void
    {
        $repo = new CensusCriteriaGeographyRepository();

        $cities = array_map(fn ($o) => $o->name, $repo->citiesInCounties(['12103']));

        $this->assertSame(['Clearwater'], $cities, 'The city tier must offer Census places only, for now.');
        $this->assertNotContains('Clearwater Beach', $cities);
    }

    /** @test */
    public function the_supplemental_tables_are_populated_while_all_of_the_above_holds(): void
    {
        // Guards against the suite passing because the layer was empty.
        $this->assertSame(2, LocationPlace::count());
        $this->assertNotNull(LocationPlace::where('name_key', 'clearwater beach')->first());
    }
}
