<?php

namespace Tests\Feature\LocationDna;

use App\Models\LocationPlace;
use App\Services\LocationDna\Places\LocationPlaceResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 1d-4 — `location:build-places` against a miniature but real corpus.
 *
 * WHAT ONLY THE COMMAND CAN BE ASKED
 * ----------------------------------
 * The resolver suite proves what the layer DOES once it is populated. This proves the population
 * itself, and covers three things that are properties of the build rather than of any query:
 *
 *   1. A name in BOTH the USPS corpus and curated config produces ONE row, not two. This is the
 *      bug the Phase 1d-3 build hit: Clearwater Beach was written once as a parentless
 *      `community` and once as a `neighborhood`, and the resolver then had to choose. A duplicate
 *      here is not cosmetic — picking the wrong row stores a label that means the right place with
 *      none of the hierarchy attached.
 *   2. The county pivot mirrors `census_place_counties` exactly, so a straddling place is
 *      reachable under every parent rather than only the lowest GEOID.
 *   3. The rebuild is idempotent. It deletes and recreates, so running it twice must leave the
 *      same rows — otherwise no environment could safely re-run it.
 *
 * The corpus is built by hand and kept tiny on purpose: every row here is one a test asserts
 * something about, which is what makes a failure point at a cause.
 */
class BuildLocationPlacesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCensusCorpus();
        $this->seedLegacyCorpus();

        config()->set('location_places.neighborhoods', [
            [
                'state'  => 'FL',
                'county' => 'Pinellas County',
                'name'   => 'Clearwater Beach',
                'parent' => 'Clearwater',
                'type'   => 'neighborhood',
                'zips'   => ['33767'],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Fixture corpus
    // ─────────────────────────────────────────────────────────────────────

    private function seedCensusCorpus(): void
    {
        DB::table('census_states')->insert([
            ['geoid' => '12', 'usps' => 'FL', 'name' => 'Florida'],
        ]);

        DB::table('census_counties')->insert([
            ['geoid' => '12103', 'state_geoid' => '12', 'countyfp' => '103', 'name' => 'Pinellas County', 'basename' => 'Pinellas'],
            ['geoid' => '12057', 'state_geoid' => '12', 'countyfp' => '057', 'name' => 'Hillsborough County', 'basename' => 'Hillsborough'],
        ]);

        DB::table('census_places')->insert([
            ['geoid' => '1212875', 'state_geoid' => '12', 'placefp' => '12875', 'name' => 'Clearwater',    'namelsad' => 'Clearwater city',    'classfp' => 'C1'],
            ['geoid' => '1254350', 'state_geoid' => '12', 'placefp' => '54350', 'name' => 'Palm Harbor',   'namelsad' => 'Palm Harbor CDP',    'classfp' => 'U1'],
            // Straddles the county line — the case the pivot exists for. Its PRIMARY county is
            // Hillsborough (12057), the lower GEOID, so a scalar-column query would lose it from
            // Pinellas entirely.
            ['geoid' => '1299999', 'state_geoid' => '12', 'placefp' => '99999', 'name' => 'Straddle City', 'namelsad' => 'Straddle City city', 'classfp' => 'C1'],
        ]);

        DB::table('census_place_counties')->insert([
            ['place_geoid' => '1212875', 'county_geoid' => '12103'],
            ['place_geoid' => '1254350', 'county_geoid' => '12103'],
            ['place_geoid' => '1299999', 'county_geoid' => '12057'],
            ['place_geoid' => '1299999', 'county_geoid' => '12103'],
        ]);

        DB::table('census_zctas')->insert([
            ['zcta5' => '33767'], ['zcta5' => '33755'], ['zcta5' => '34683'],
        ]);

        DB::table('census_zcta_counties')->insert([
            ['zcta5' => '33767', 'county_geoid' => '12103'],
            ['zcta5' => '33755', 'county_geoid' => '12103'],
            ['zcta5' => '34683', 'county_geoid' => '12103'],
        ]);
    }

    private function seedLegacyCorpus(): void
    {
        $stateId = DB::table('us_states')->insertGetId([
            'name' => 'Florida', 'abbreviation' => 'FL', 'fips_code' => '12',
        ]);

        $countyId = DB::table('us_counties')->insertGetId([
            'name' => 'Pinellas County', 'fips_code' => '103', 'state_id' => $stateId,
        ]);

        DB::table('us_cities')->insert([
            // Already in the Census corpus — must NOT be added again as supplemental.
            ['name' => 'Clearwater',       'state_id' => $stateId, 'county_id' => $countyId],
            // In the USPS corpus AND in curated config — the duplication trap.
            ['name' => 'Clearwater Beach', 'state_id' => $stateId, 'county_id' => $countyId],
            // In neither — a genuine supplemental community.
            ['name' => 'Ozona',            'state_id' => $stateId, 'county_id' => $countyId],
        ]);

        DB::table('us_zip_codes')->insert([
            ['zip_code' => '33767', 'city' => 'Clearwater Beach', 'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Pinellas'],
            ['zip_code' => '33755', 'city' => 'Clearwater',       'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Pinellas'],
            // Not a ZCTA in this fixture: the PO-box case, which must be kept and flagged.
            ['zip_code' => '33757', 'city' => 'Clearwater',       'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Pinellas'],
            ['zip_code' => '34683', 'city' => 'Ozona',            'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Pinellas'],
        ]);
    }

    private function build(): void
    {
        $this->artisan('location:build-places')->assertExitCode(0);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Non-duplication
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function clearwater_beach_is_written_exactly_once_despite_being_in_two_sources(): void
    {
        $this->build();

        $rows = LocationPlace::where('name_key', 'clearwater beach')->get();

        $this->assertCount(1, $rows, 'Clearwater Beach must not be both a community and a neighbourhood.');
        $this->assertSame(LocationPlace::TYPE_NEIGHBORHOOD, $rows->first()->type);
        $this->assertSame(LocationPlace::SOURCE_CURATED, $rows->first()->source);
    }

    /** @test */
    public function the_curated_neighbourhood_keeps_its_parent_county_and_zip(): void
    {
        $this->build();

        $match = (new LocationPlaceResolver())->resolve('Clearwater Beach', '12', ['12103']);

        $this->assertNotNull($match);
        $this->assertSame('Clearwater, FL', $match->parentLabel());
        $this->assertSame('Pinellas County', $match->countyName());
        $this->assertSame('33767', $match->primaryZip());
        $this->assertFalse($match->ambiguous, 'One row means nothing to disambiguate.');
    }

    /** @test */
    public function a_usps_name_the_census_already_publishes_is_not_duplicated(): void
    {
        $this->build();

        $rows = LocationPlace::where('name_key', 'clearwater')->get();

        $this->assertCount(1, $rows);
        $this->assertSame(LocationPlace::SOURCE_CENSUS, $rows->first()->source);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Each source lands
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function census_places_are_projected_with_their_type_and_geoid(): void
    {
        $this->build();

        $clearwater = LocationPlace::where('name_key', 'clearwater')->first();
        $this->assertSame(LocationPlace::TYPE_CITY, $clearwater->type);
        $this->assertSame('1212875', trim($clearwater->census_place_geoid));

        $palmHarbor = LocationPlace::where('name_key', 'palm harbor')->first();
        $this->assertSame(LocationPlace::TYPE_CDP, $palmHarbor->type, 'A CDP must not be typed as a city.');
    }

    /** @test */
    public function a_usps_only_name_becomes_a_supplemental_community(): void
    {
        $this->build();

        $ozona = LocationPlace::where('name_key', 'ozona')->first();

        $this->assertNotNull($ozona, 'Ozona is in no Census place list and must survive as supplemental.');
        $this->assertSame(LocationPlace::SOURCE_SUPPLEMENTAL, $ozona->source);
        $this->assertSame(LocationPlace::TYPE_COMMUNITY, $ozona->type);
        $this->assertSame('12103', trim($ozona->county_geoid));

        $match = (new LocationPlaceResolver())->resolve('Ozona', '12', ['12103']);
        $this->assertNotNull($match, 'A supplemental place must resolve like any other.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // The county pivot
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function the_pivot_mirrors_census_place_counties_exactly(): void
    {
        $this->build();

        $censusLinks = DB::table('location_place_counties as lc')
            ->join('location_places as p', 'p.id', '=', 'lc.location_place_id')
            ->where('p.source', LocationPlace::SOURCE_CENSUS)
            ->count();

        $this->assertSame(DB::table('census_place_counties')->count(), $censusLinks);
    }

    /** @test */
    public function a_straddling_place_is_reachable_under_both_counties(): void
    {
        $this->build();

        $resolver = new LocationPlaceResolver();

        foreach (['12057', '12103'] as $county) {
            $this->assertNotNull(
                $resolver->resolve('Straddle City', '12', [$county]),
                "Straddle City must be findable under {$county}."
            );
        }

        $match = $resolver->resolve('Straddle City', '12');
        $this->assertTrue($match->spansCounties());
        $this->assertSame(['Hillsborough County', 'Pinellas County'], $match->countyNames());
    }

    /** @test */
    public function every_place_with_a_county_has_exactly_one_primary_pivot_row(): void
    {
        $this->build();

        $withCounty = LocationPlace::whereNotNull('county_geoid')->count();

        $primaries = DB::table('location_place_counties')->where('is_primary', true)->count();
        $this->assertSame($withCounty, $primaries);

        $multi = DB::table('location_place_counties')
            ->where('is_primary', true)
            ->selectRaw('location_place_id')
            ->groupBy('location_place_id')
            ->havingRaw('count(*) > 1')
            ->get();

        $this->assertCount(0, $multi, 'A place cannot be mainly in two counties.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // ZIPs and idempotence
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function a_po_box_zip_is_kept_and_flagged_rather_than_dropped(): void
    {
        $this->build();

        $clearwater = LocationPlace::where('name_key', 'clearwater')->first();

        $zips = DB::table('location_place_zips')
            ->where('location_place_id', $clearwater->id)
            ->pluck('is_zcta', 'zip')
            ->mapWithKeys(fn ($v, $k) => [trim($k) => (bool) $v])
            ->all();

        $this->assertArrayHasKey('33757', $zips, 'A PO-box ZIP must not be discarded.');
        $this->assertFalse($zips['33757']);
        $this->assertTrue($zips['33755']);
    }

    /** @test */
    public function rebuilding_twice_leaves_the_same_rows(): void
    {
        $this->build();

        $before = [
            'places'   => LocationPlace::count(),
            'counties' => DB::table('location_place_counties')->count(),
            'zips'     => DB::table('location_place_zips')->count(),
        ];

        $this->build();

        $this->assertSame($before, [
            'places'   => LocationPlace::count(),
            'counties' => DB::table('location_place_counties')->count(),
            'zips'     => DB::table('location_place_zips')->count(),
        ]);

        $this->assertCount(1, LocationPlace::where('name_key', 'clearwater beach')->get());
    }

    /** @test */
    public function it_never_writes_to_the_census_corpus(): void
    {
        $before = [
            DB::table('census_places')->count(),
            DB::table('census_counties')->count(),
            DB::table('census_place_counties')->count(),
            DB::table('census_zctas')->count(),
        ];

        $this->build();

        $this->assertSame($before, [
            DB::table('census_places')->count(),
            DB::table('census_counties')->count(),
            DB::table('census_place_counties')->count(),
            DB::table('census_zctas')->count(),
        ]);
    }
}
