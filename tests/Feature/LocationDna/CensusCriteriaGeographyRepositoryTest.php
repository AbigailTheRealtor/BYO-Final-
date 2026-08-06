<?php

namespace Tests\Feature\LocationDna;

use App\Services\LocationDna\Criteria\CensusCriteriaGeographyRepository;
use App\Services\LocationDna\Criteria\GeographyOption;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 1d-2 — the Census repository against the real `census_*` schema.
 *
 * WHAT ONLY A REAL SCHEMA CAN ANSWER
 * ----------------------------------
 * The fake proves the contract and the Eloquent suite proves the reference tables. This proves the
 * four things that are true of the Census corpus and of nothing else, each of which would fail
 * silently rather than loudly:
 *
 *   1. GEOIDs keep their leading zeros through the query layer. `01` arriving as `1` would not
 *      error — it would simply match nothing, one tier down, on a later request.
 *   2. A place spanning two counties is emitted once per county. `us_cities` cannot express that
 *      at all, so no existing test can have covered it.
 *   3. ZIP options are identical in shape to the ones the reference repository emits, because the
 *      selection format must not change when the source does.
 *   4. Nothing is written. This namespace is read-only by construction and the guard asserts the
 *      source contains no write; this asserts the behaviour as well.
 *
 * Rows are inserted directly. These are reference tables with no factories, and writing them by
 * hand is also what lets a fixture carry a deliberately awkward GEOID.
 */
class CensusCriteriaGeographyRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private CensusCriteriaGeographyRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = new CensusCriteriaGeographyRepository();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Fixture builders
    // ─────────────────────────────────────────────────────────────────────

    private function state(string $geoid, string $usps, string $name): void
    {
        DB::table('census_states')->insert([
            'geoid' => $geoid,
            'usps'  => $usps,
            'name'  => $name,
        ]);
    }

    private function county(string $geoid, string $name, ?string $basename = null): void
    {
        DB::table('census_counties')->insert([
            'geoid'       => $geoid,
            'state_geoid' => substr($geoid, 0, 2),
            'countyfp'    => substr($geoid, 2),
            'name'        => $name,
            'basename'    => $basename ?? $name,
        ]);
    }

    private function place(string $geoid, string $name): void
    {
        DB::table('census_places')->insert([
            'geoid'       => $geoid,
            'state_geoid' => substr($geoid, 0, 2),
            'placefp'     => substr($geoid, 2),
            'name'        => $name,
            'namelsad'    => $name.' city',
        ]);
    }

    private function placeInCounty(string $placeGeoid, string $countyGeoid): void
    {
        DB::table('census_place_counties')->insert([
            'place_geoid'  => $placeGeoid,
            'county_geoid' => $countyGeoid,
        ]);
    }

    private function zcta(string $zcta5, string ...$countyGeoids): void
    {
        DB::table('census_zctas')->insert(['zcta5' => $zcta5]);

        foreach ($countyGeoids as $countyGeoid) {
            DB::table('census_zcta_counties')->insert([
                'zcta5'        => $zcta5,
                'county_geoid' => $countyGeoid,
            ]);
        }
    }

    /** Alabama, two counties, one place in each. The baseline most tests build on. */
    private function seedAlabama(): void
    {
        $this->state('01', 'AL', 'Alabama');
        $this->county('01001', 'Autauga County', 'Autauga');
        $this->county('01003', 'Baldwin County', 'Baldwin');
        $this->place('0100100', 'Prattville');
        $this->place('0100300', 'Fairhope');
        $this->placeInCounty('0100100', '01001');
        $this->placeInCounty('0100300', '01003');
    }

    /** @param list<GeographyOption> $options @return list<string> */
    private function ids(array $options): array
    {
        return array_map(static fn (GeographyOption $o): string => $o->id, $options);
    }

    // ─────────────────────────────────────────────────────────────────────
    // States
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function states_come_from_the_census_table_and_are_ordered_by_name(): void
    {
        $this->state('01', 'AL', 'Alabama');
        $this->state('06', 'CA', 'California');
        $this->state('02', 'AK', 'Alaska');

        $states = $this->repo->states();

        $this->assertSame(['Alabama', 'Alaska', 'California'], array_map(
            static fn (GeographyOption $o): string => $o->name,
            $states
        ));

        foreach ($states as $option) {
            $this->assertTrue($option->is(GeographyOption::KIND_STATE));
            $this->assertNull($option->parentId, 'A state has no parent.');
        }
    }

    /** @test */
    public function a_state_carries_its_geoid_as_both_id_and_code(): void
    {
        $this->state('01', 'AL', 'Alabama');

        $state = $this->repo->states()[0];

        $this->assertSame('01', $state->id);
        $this->assertSame('01', $state->code);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Counties
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function counties_resolve_from_the_state_geoid(): void
    {
        $this->seedAlabama();
        $this->state('02', 'AK', 'Alaska');
        $this->county('02013', 'Aleutians East Borough', 'Aleutians East');

        $counties = $this->repo->countiesInState('01');

        $this->assertSame(['01001', '01003'], $this->ids($counties));
        $this->assertSame(['Autauga County', 'Baldwin County'], array_map(
            static fn (GeographyOption $o): string => $o->name,
            $counties
        ));

        foreach ($counties as $county) {
            $this->assertTrue($county->is(GeographyOption::KIND_COUNTY));
            $this->assertSame('01', $county->parentId);
        }
    }

    /**
     * The published form is kept, because that is what the stored labels carry.
     *
     * `census_counties` stores both `name` ("Autauga County") and `basename` ("Autauga"). Emitting
     * the basename would leave every historical record — which says `Pinellas County, FL` — unable
     * to match, with nothing raised anywhere.
     */
    /** @test */
    public function a_county_option_carries_the_published_name_not_the_basename(): void
    {
        $this->seedAlabama();

        $this->assertSame('Autauga County', $this->repo->countiesInState('01')[0]->name);
    }

    /** @test */
    public function an_unknown_state_yields_no_counties(): void
    {
        $this->seedAlabama();

        $this->assertSame([], $this->repo->countiesInState('99'));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Cities — from census_places, and many-to-many
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function cities_come_from_census_places_via_the_relationship_table(): void
    {
        $this->seedAlabama();

        $cities = $this->repo->citiesInCounties(['01001']);

        $this->assertSame(['0100100'], $this->ids($cities));
        $this->assertSame('Prattville', $cities[0]->name);
        $this->assertSame('01001', $cities[0]->parentId);
        $this->assertTrue($cities[0]->is(GeographyOption::KIND_CITY));
    }

    /**
     * THE CASE `us_cities` CANNOT REPRESENT AT ALL.
     *
     * `us_cities.county_id` is a single foreign key, so a city belongs to exactly one county. The
     * published data says otherwise — roughly 1,430 places straddle a county line, and Kansas City
     * MO spans four. A place is therefore emitted once per county, exactly as ZIPs already are.
     */
    /** @test */
    public function a_place_spanning_two_counties_is_emitted_once_per_county(): void
    {
        $this->seedAlabama();
        $this->place('0199999', 'Straddleton');
        $this->placeInCounty('0199999', '01001');
        $this->placeInCounty('0199999', '01003');

        $options = $this->repo->citiesInCounties(['01001', '01003']);

        $straddling = array_values(array_filter(
            $options,
            static fn (GeographyOption $o): bool => $o->id === '0199999'
        ));

        $this->assertCount(2, $straddling, 'One option per parent county, not one collapsed option.');
        $this->assertSame(
            ['01001', '01003'],
            array_map(static fn (GeographyOption $o): string => (string) $o->parentId, $straddling),
            'Each option must name the county that produced it.'
        );

        // The two are DIFFERENT options by the DTO's own identity rule, which is what stops a
        // later reader deduplicating one of them away.
        $this->assertFalse($straddling[0]->matches($straddling[1]));
    }

    /** @test */
    public function a_straddling_place_survives_selecting_only_one_of_its_counties(): void
    {
        $this->seedAlabama();
        $this->place('0199999', 'Straddleton');
        $this->placeInCounty('0199999', '01001');
        $this->placeInCounty('0199999', '01003');

        $this->assertContains('0199999', $this->ids($this->repo->citiesInCounties(['01003'])));
    }

    /** @test */
    public function no_counties_yields_no_cities(): void
    {
        $this->seedAlabama();

        $this->assertSame([], $this->repo->citiesInCounties([]));
    }

    // ─────────────────────────────────────────────────────────────────────
    // ZIPs — from the ZCTA tables
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function zips_resolve_from_the_zcta_tables(): void
    {
        $this->seedAlabama();
        $this->zcta('36067', '01001');
        $this->zcta('36532', '01003');

        $zips = $this->repo->zipsInCounties(['01001']);

        $this->assertSame(['36067'], $this->ids($zips));
        $this->assertSame('01001', $zips[0]->parentId);
        $this->assertTrue($zips[0]->is(GeographyOption::KIND_ZIP));
    }

    /**
     * The ZIP option's shape must not change when the source does.
     *
     * Both repositories key a ZIP by the ZIP itself — id, name and code are all the code. The
     * selection carries ZIP codes, so any divergence here would change the stored blob format.
     */
    /** @test */
    public function a_zip_option_carries_the_code_as_id_name_and_code(): void
    {
        $this->seedAlabama();
        $this->zcta('36067', '01001');

        $zip = $this->repo->zipsInCounties(['01001'])[0];

        $this->assertSame('36067', $zip->id);
        $this->assertSame('36067', $zip->name);
        $this->assertSame('36067', $zip->code);
    }

    /** @test */
    public function a_zcta_crossing_two_counties_is_emitted_once_per_county(): void
    {
        $this->seedAlabama();
        $this->zcta('36000', '01001', '01003');

        $zips = $this->repo->zipsInCounties(['01001', '01003']);

        $this->assertSame(['36000', '36000'], $this->ids($zips));
        $this->assertSame(
            ['01001', '01003'],
            array_map(static fn (GeographyOption $o): string => (string) $o->parentId, $zips)
        );
    }

    /**
     * A relationship row whose ZCTA is absent from the roster must not become an option.
     *
     * The join to `census_zctas` is what enforces this. Without it the row would surface as an
     * option naming a ZCTA that does not exist.
     */
    /** @test */
    public function a_relationship_row_without_a_roster_entry_is_not_emitted(): void
    {
        $this->seedAlabama();

        DB::table('census_zcta_counties')->insert([
            'zcta5'        => '99999',
            'county_geoid' => '01001',
        ]);

        $this->assertSame([], $this->repo->zipsInCounties(['01001']));
    }

    /** @test */
    public function no_counties_yields_no_zips(): void
    {
        $this->seedAlabama();

        $this->assertSame([], $this->repo->zipsInCounties([]));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Leading zeros
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Every tier, end to end, with the most damaging value the corpus contains.
     *
     * Alabama is `01`. If anything in the query layer treated a GEOID as a number, `01` would come
     * back as `1`, `01001` as `1001` and `0100100` as `100100` — and every one of those is a
     * lookup that quietly returns nothing rather than an error anyone would see.
     */
    /** @test */
    public function leading_zeros_survive_every_tier(): void
    {
        $this->seedAlabama();
        $this->zcta('01001', '01001');

        $state = $this->repo->states()[0];
        $this->assertSame('01', $state->id);
        $this->assertSame('01', $state->code);

        $county = $this->repo->countiesInState('01')[0];
        $this->assertSame('01001', $county->id);
        $this->assertSame('01001', $county->code);
        $this->assertSame('01', $county->parentId);

        $city = $this->repo->citiesInCounties(['01001'])[0];
        $this->assertSame('0100100', $city->id);
        $this->assertSame('01001', $city->parentId);

        $zip = $this->repo->zipsInCounties(['01001'])[0];
        $this->assertSame('01001', $zip->id);
    }

    /**
     * An id of the wrong width is refused rather than coerced.
     *
     * Both repositories use numeric string ids, which makes one from the other look plausible. The
     * reference implementation casts with `(int)`, so a county GEOID `01001` handed to it becomes
     * id `1001` — a real, different county, returned without complaint. This one answers with an
     * empty list instead, which is what the interface already specifies for an unknown id.
     */
    /** @test */
    public function an_identifier_of_the_wrong_width_matches_nothing(): void
    {
        $this->seedAlabama();

        // A surrogate primary key from the other repository: four digits, not five.
        $this->assertSame([], $this->repo->citiesInCounties(['1001']));
        $this->assertSame([], $this->repo->zipsInCounties(['1001']));

        // A county GEOID that lost its leading zero.
        $this->assertSame([], $this->repo->citiesInCounties(['1001']));

        // A state id that is not two digits.
        $this->assertSame([], $this->repo->countiesInState('1'));
        $this->assertSame([], $this->repo->countiesInState('001'));

        // Non-numeric input is a caller bug and yields nothing rather than a guess.
        $this->assertSame([], $this->repo->countiesInState('Alabama'));
        $this->assertSame([], $this->repo->citiesInCounties(['Autauga County']));
    }

    /** @test */
    public function well_formed_ids_mixed_with_malformed_ones_still_resolve(): void
    {
        $this->seedAlabama();

        $cities = $this->repo->citiesInCounties(['1001', '01001', 'nonsense', '']);

        $this->assertSame(['0100100'], $this->ids($cities));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Read-only
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function enumerating_every_tier_writes_nothing(): void
    {
        $this->seedAlabama();
        $this->zcta('36067', '01001');

        $tables = [
            'census_states', 'census_counties', 'census_places',
            'census_place_counties', 'census_zctas', 'census_zcta_counties',
            'census_geography_meta',
        ];

        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->count();
        }

        $this->repo->states();
        $this->repo->countiesInState('01');
        $this->repo->citiesInCounties(['01001', '01003']);
        $this->repo->zipsInCounties(['01001', '01003']);

        foreach ($tables as $table) {
            $this->assertSame(
                $before[$table],
                DB::table($table)->count(),
                "{$table} must be unchanged — this namespace is read-only by construction."
            );
        }
    }

    /**
     * The legacy reference tables are not read and not written.
     *
     * Phase 1d-2 is a second source, not a migration of the first. The `us_*` tables still serve
     * every consumer surface through the other implementation, and nothing here may disturb them.
     */
    /** @test */
    public function the_legacy_us_tables_are_left_untouched(): void
    {
        $this->seedAlabama();
        $this->zcta('36067', '01001');

        $legacy = ['us_states', 'us_counties', 'us_cities', 'us_zip_codes'];

        $before = [];
        foreach ($legacy as $table) {
            $before[$table] = DB::table($table)->count();
        }

        $this->repo->states();
        $this->repo->countiesInState('01');
        $this->repo->citiesInCounties(['01001']);
        $this->repo->zipsInCounties(['01001']);

        foreach ($legacy as $table) {
            $this->assertSame(
                $before[$table],
                DB::table($table)->count(),
                "{$table} must not be touched by the Census repository."
            );
        }
    }

    /** The source itself names no `us_*` table, so the point above cannot regress quietly. */
    /** @test */
    public function the_source_references_no_legacy_reference_table(): void
    {
        $source = (string) file_get_contents(
            app_path('Services/LocationDna/Criteria/CensusCriteriaGeographyRepository.php')
        );

        foreach (['us_states', 'us_counties', 'us_cities', 'us_zip_codes'] as $table) {
            $this->assertStringNotContainsString(
                "'".$table."'",
                $source,
                "The Census repository must not name {$table}."
            );
        }

        foreach (['UsState', 'UsCounty', 'UsCity', 'UsZipCode'] as $model) {
            $this->assertStringNotContainsString(
                $model,
                $source,
                "The Census repository must not use the {$model} model."
            );
        }
    }
}
