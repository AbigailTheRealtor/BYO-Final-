<?php

namespace Tests\Feature\LocationDna;

use App\Models\LocationPlace;
use App\Services\LocationDna\Places\LocationPlaceResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 1d-3 — the supplemental layer answering the question the Census corpus cannot.
 *
 * THE CASE THIS SUITE EXISTS FOR
 * ------------------------------
 * `Clearwater Beach` must resolve to a NEIGHBOURHOOD, inside `Clearwater`, in `Pinellas County`,
 * with ZIP `33767`, and must not be treated as an error. The Phase 1d-1 audit found it as the one
 * Pinellas name published geography structurally cannot supply — it is a barrier island inside a
 * city, not a unit of government — and everything in this phase exists to make it resolvable
 * without disturbing the corpus underneath.
 *
 * Rows are inserted by hand, as in the sibling Census suites: these are reference tables with no
 * factories, and hand-written fixtures are what let a case carry a deliberately awkward name.
 */
class LocationPlaceResolverTest extends TestCase
{
    use DatabaseTransactions;

    private LocationPlaceResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new LocationPlaceResolver();

        DB::table('census_states')->insert([
            ['geoid' => '12', 'usps' => 'FL', 'name' => 'Florida'],
            ['geoid' => '06', 'usps' => 'CA', 'name' => 'California'],
        ]);

        DB::table('census_counties')->insert([
            ['geoid' => '12103', 'state_geoid' => '12', 'countyfp' => '103', 'name' => 'Pinellas County', 'basename' => 'Pinellas'],
            ['geoid' => '12057', 'state_geoid' => '12', 'countyfp' => '057', 'name' => 'Hillsborough County', 'basename' => 'Hillsborough'],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Fixture builders
    // ─────────────────────────────────────────────────────────────────────

    /**
     * A place, with its primary county pivot row — the pairing the builder always writes.
     *
     * The pivot is created here rather than in individual tests because county membership is not
     * optional: a place with no pivot row is invisible to every county-scoped query, and a fixture
     * that omitted it would make a test pass for the wrong reason.
     */
    private function place(array $overrides = []): LocationPlace
    {
        $place = LocationPlace::create(array_merge([
            'name'         => 'Clearwater',
            'type'         => LocationPlace::TYPE_CITY,
            'state_geoid'  => '12',
            'county_geoid' => '12103',
            'source'       => LocationPlace::SOURCE_CENSUS,
            'active'       => true,
        ], $overrides));

        if ($place->county_geoid !== null) {
            $this->linkCounty($place, $place->county_geoid, true);
        }

        return $place;
    }

    /** An additional county for a place that straddles a line. */
    private function linkCounty(LocationPlace $place, string $countyGeoid, bool $isPrimary = false): void
    {
        DB::table('location_place_counties')->insert([
            'location_place_id' => $place->id,
            'county_geoid'      => $countyGeoid,
            'is_primary'        => $isPrimary,
        ]);
    }

    private function zip(int $placeId, string $zip, bool $isZcta = true): void
    {
        DB::table('location_place_zips')->insert([
            'location_place_id' => $placeId,
            'zip'               => $zip,
            'is_zcta'           => $isZcta,
            'source'            => 'usps',
        ]);
    }

    /** Clearwater + Clearwater Beach, wired as the builder wires them. */
    private function clearwaterBeach(): LocationPlace
    {
        $clearwater = $this->place(['census_place_geoid' => '1212875']);

        $beach = $this->place([
            'name'            => 'Clearwater Beach',
            'type'            => LocationPlace::TYPE_NEIGHBORHOOD,
            'parent_place_id' => $clearwater->id,
            'source'          => LocationPlace::SOURCE_CURATED,
        ]);

        $this->zip($beach->id, '33767');

        return $beach;
    }

    // ─────────────────────────────────────────────────────────────────────
    // The Clearwater Beach case
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function clearwater_beach_resolves_to_a_neighbourhood_of_clearwater(): void
    {
        $this->clearwaterBeach();

        $match = $this->resolver->resolve('Clearwater Beach', '12', ['12103']);

        $this->assertNotNull($match, 'Clearwater Beach must resolve, not be treated as an error.');
        $this->assertSame('Clearwater Beach', $match->name());
        $this->assertSame(LocationPlace::TYPE_NEIGHBORHOOD, $match->type());
        $this->assertSame('Clearwater, FL', $match->parentLabel());
        $this->assertSame('Pinellas County', $match->countyName());
        $this->assertSame('33767', $match->primaryZip());
        $this->assertTrue($match->isSubPlace());
        $this->assertFalse($match->ambiguous);
    }

    /** @test */
    public function it_resolves_the_stored_label_form_and_ignores_case_and_padding(): void
    {
        $this->clearwaterBeach();

        foreach (['Clearwater Beach, FL', '  clearwater   beach  ', 'CLEARWATER BEACH'] as $input) {
            $match = $this->resolver->resolve($input, '12', ['12103']);

            $this->assertNotNull($match, "Failed to resolve: {$input}");
            $this->assertSame('Clearwater Beach', $match->name());
        }
    }

    /** @test */
    public function it_resolves_without_a_state_or_county_hint(): void
    {
        $this->clearwaterBeach();

        $match = $this->resolver->resolve('Clearwater Beach');

        $this->assertNotNull($match);
        $this->assertSame('Clearwater, FL', $match->parentLabel());
    }

    /** @test */
    public function the_full_hierarchy_is_reachable_from_one_resolution(): void
    {
        $this->clearwaterBeach();

        $match = $this->resolver->resolve('Clearwater Beach', '12', ['12103']);

        $this->assertSame('Florida', $match->state['name']);
        $this->assertSame('12103', $match->county['geoid']);
        $this->assertSame('Clearwater', $match->parent->name);
        $this->assertSame('Clearwater Beach', $match->place->name);
        $this->assertSame(['33767'], $match->zips);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Precedence, ambiguity, and refusal to guess
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function a_curated_neighbourhood_outranks_a_bulk_supplemental_row_of_the_same_name(): void
    {
        // The builder suppresses this collision at write time; the resolver must also order
        // correctly, so that a curated entry cannot be masked by a stray community row.
        $this->clearwaterBeach();

        $this->place([
            'name'            => 'Clearwater Beach',
            'type'            => LocationPlace::TYPE_COMMUNITY,
            'parent_place_id' => null,
            'source'          => LocationPlace::SOURCE_SUPPLEMENTAL,
        ]);

        $match = $this->resolver->resolve('Clearwater Beach', '12', ['12103']);

        $this->assertSame(LocationPlace::TYPE_NEIGHBORHOOD, $match->type());
        $this->assertSame(LocationPlace::SOURCE_CURATED, $match->place->source);
        $this->assertTrue($match->ambiguous, 'Both rows exist, so the caller must be told.');
    }

    /** @test */
    public function a_municipality_outranks_a_same_named_sub_place(): void
    {
        $this->place(['name' => 'Seminole', 'type' => LocationPlace::TYPE_CITY]);
        $this->place([
            'name'   => 'Seminole',
            'type'   => LocationPlace::TYPE_COMMUNITY,
            'source' => LocationPlace::SOURCE_SUPPLEMENTAL,
        ]);

        $match = $this->resolver->resolve('Seminole', '12', ['12103']);

        $this->assertSame(LocationPlace::TYPE_CITY, $match->type());
        $this->assertTrue($match->ambiguous);
    }

    /** @test */
    public function a_county_hint_disambiguates_a_repeated_name(): void
    {
        $this->place(['name' => 'University', 'type' => LocationPlace::TYPE_CDP, 'county_geoid' => '12103']);
        $this->place(['name' => 'University', 'type' => LocationPlace::TYPE_CDP, 'county_geoid' => '12057']);

        $match = $this->resolver->resolve('University', '12', ['12057']);

        $this->assertSame('12057', $match->place->county_geoid);
        $this->assertFalse($match->ambiguous, 'Narrowed to one county, there is nothing ambiguous left.');
    }

    /** @test */
    public function an_unknown_name_resolves_to_null_rather_than_a_guess(): void
    {
        $this->clearwaterBeach();

        $this->assertNull($this->resolver->resolve('Nowhere At All', '12', ['12103']));
        $this->assertNull($this->resolver->resolve('', '12'));
        $this->assertNull($this->resolver->resolve('   ', '12'));
    }

    /** @test */
    public function an_inactive_place_is_not_resolved(): void
    {
        $beach = $this->clearwaterBeach();
        $beach->update(['active' => false]);

        $this->assertNull($this->resolver->resolve('Clearwater Beach', '12', ['12103']));
    }

    /** @test */
    public function a_malformed_geoid_hint_is_ignored_rather_than_coerced(): void
    {
        $this->clearwaterBeach();

        // A four-digit surrogate key from the legacy corpus must not narrow anything, the same
        // refusal CensusCriteriaGeographyRepository makes.
        $match = $this->resolver->resolve('Clearwater Beach', '12', ['1210']);

        $this->assertNotNull($match, 'A malformed county hint must not silently exclude everything.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Hierarchy traversal
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function sub_places_of_a_city_are_listed(): void
    {
        $beach      = $this->clearwaterBeach();
        $clearwater = $beach->parent;

        $this->place([
            'name'            => 'Sand Key',
            'type'            => LocationPlace::TYPE_NEIGHBORHOOD,
            'parent_place_id' => $clearwater->id,
            'source'          => LocationPlace::SOURCE_CURATED,
        ]);

        $names = array_map(fn ($p) => $p->name, $this->resolver->subPlacesOf($clearwater->id));

        $this->assertSame(['Clearwater Beach', 'Sand Key'], $names);
    }

    /** @test */
    public function sub_places_in_counties_excludes_places_proper(): void
    {
        $this->clearwaterBeach();

        $names = array_map(fn ($p) => $p->name, $this->resolver->subPlacesInCounties(['12103']));

        $this->assertSame(['Clearwater Beach'], $names, 'Clearwater itself is a place, not a sub-place.');
        $this->assertSame([], $this->resolver->subPlacesInCounties([]));
        $this->assertSame([], $this->resolver->subPlacesInCounties(['bogus']));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Multi-county places — the Phase 1d-4 pivot
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @test
     *
     * The regression the pivot exists to fix. Before it, a place was only findable under the
     * lowest-GEOID county it belonged to, so searching its OTHER county returned nothing — real
     * place, correct county, empty result, no error.
     */
    public function a_multi_county_place_resolves_under_every_one_of_its_counties(): void
    {
        // Primary is Hillsborough (the lower GEOID); the place also sits in Pinellas.
        $place = $this->place(['name' => 'Straddle City', 'county_geoid' => '12057']);
        $this->linkCounty($place, '12103');

        foreach (['12057', '12103'] as $county) {
            $match = $this->resolver->resolve('Straddle City', '12', [$county]);

            $this->assertNotNull($match, "Not found under county {$county}");
            $this->assertSame('Straddle City', $match->name());
        }
    }

    /** @test */
    public function a_multi_county_place_reports_all_its_counties_primary_first(): void
    {
        $place = $this->place(['name' => 'Straddle City', 'county_geoid' => '12057']);
        $this->linkCounty($place, '12103');

        $match = $this->resolver->resolve('Straddle City', '12');

        $this->assertTrue($match->spansCounties());
        $this->assertSame(['Hillsborough County', 'Pinellas County'], $match->countyNames());
        $this->assertTrue($match->counties[0]['primary'], 'The primary county must be listed first.');
        $this->assertFalse($match->counties[1]['primary']);

        // The scalar column still answers "mainly where?", and agrees with the flagged row.
        $this->assertSame('Hillsborough County', $match->countyName());
    }

    /** @test */
    public function a_place_in_two_selected_counties_is_returned_once(): void
    {
        $place = $this->place(['name' => 'Straddle City', 'county_geoid' => '12057']);
        $this->linkCounty($place, '12103');

        // Both parents selected at once: a join would return this place twice.
        $found = LocationPlace::query()->active()->inCounties(['12057', '12103'])->get();

        $this->assertCount(1, $found);
        $this->assertSame('Straddle City', $found->first()->name);
    }

    /** @test */
    public function a_single_county_place_is_unaffected_by_the_pivot(): void
    {
        $this->place(['name' => 'Dunedin']);

        $match = $this->resolver->resolve('Dunedin', '12', ['12103']);

        $this->assertNotNull($match);
        $this->assertFalse($match->spansCounties());
        $this->assertSame(['Pinellas County'], $match->countyNames());
        $this->assertNull($this->resolver->resolve('Dunedin', '12', ['12057']), 'Must not leak into a county it is not in.');
    }

    /** @test */
    public function a_curated_neighbourhood_carries_its_single_county_through_the_pivot(): void
    {
        $this->clearwaterBeach();

        $match = $this->resolver->resolve('Clearwater Beach', '12', ['12103']);

        $this->assertSame(['Pinellas County'], $match->countyNames());
        $this->assertFalse($match->spansCounties());
        $this->assertSame(['12103'], $match->place->countyGeoids());
    }

    /** @test */
    public function sub_places_in_counties_finds_a_neighbourhood_through_the_pivot(): void
    {
        $this->clearwaterBeach();

        $names = array_map(fn ($p) => $p->name, $this->resolver->subPlacesInCounties(['12103']));

        $this->assertSame(['Clearwater Beach'], $names);
        $this->assertSame([], $this->resolver->subPlacesInCounties(['12057']));
    }

    /** @test */
    public function a_place_with_no_county_row_is_absent_from_county_scoped_queries_but_still_resolvable(): void
    {
        // 98 supplemental rows are in this state: a real USPS name whose legacy county could not
        // be resolved. It must not vanish — it simply cannot be found BY county.
        $this->place(['name' => 'Countyless', 'county_geoid' => null, 'source' => LocationPlace::SOURCE_SUPPLEMENTAL]);

        $this->assertNull($this->resolver->resolve('Countyless', '12', ['12103']));

        $match = $this->resolver->resolve('Countyless', '12');

        $this->assertNotNull($match, 'A place with no county must still resolve by name.');
        $this->assertSame([], $match->counties);
        $this->assertNull($match->countyName());
    }

    // ─────────────────────────────────────────────────────────────────────
    // ZIP preservation
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function residential_zips_are_listed_before_po_box_zips_and_neither_is_dropped(): void
    {
        $place = $this->place(['name' => 'St. Petersburg']);

        $this->zip($place->id, '33731', false);   // PO-box: real, but not a ZCTA
        $this->zip($place->id, '33701', true);

        $this->assertSame(['33701', '33731'], $this->resolver->zips($place->id));
    }

    // ─────────────────────────────────────────────────────────────────────
    // The model's own invariant
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function the_name_key_is_derived_on_save_so_it_cannot_disagree_with_the_name(): void
    {
        $place = $this->place(['name' => '  Saint   Petersburg  ']);

        $this->assertSame('st petersburg', $place->fresh()->name_key);

        $place->update(['name' => 'Clearwater']);

        $this->assertSame('clearwater', $place->fresh()->name_key);
    }

    /** @test */
    public function a_legacy_saint_spelling_resolves_to_the_census_st_spelling(): void
    {
        $this->place(['name' => 'St. Petersburg']);

        $match = $this->resolver->resolve('Saint Petersburg, FL', '12', ['12103']);

        $this->assertNotNull($match);
        $this->assertSame('St. Petersburg', $match->name(), 'The corpus spelling is what is returned.');
    }
}
