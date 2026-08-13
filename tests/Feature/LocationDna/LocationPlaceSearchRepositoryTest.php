<?php

namespace Tests\Feature\LocationDna;

use App\Models\LocationPlace;
use App\Services\LocationDna\Criteria\GeographyOption;
use App\Services\LocationDna\Criteria\Rules\GeographyTier;
use App\Services\LocationDna\Criteria\Search\GeographyQuery;
use App\Services\LocationDna\Criteria\Search\GeographySearchResult;
use App\Services\LocationDna\Criteria\Search\LocationPlaceSearchRepository;
use App\Services\LocationDna\Criteria\Search\MatchType;
use App\Services\LocationDna\Places\PlaceNameKey;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * M1 — geography search against the canonical place layer.
 *
 * WHAT ONLY A REAL SCHEMA CAN ANSWER
 * ----------------------------------
 * The ranker and classifier suites prove the pure rules. This proves what is true of the canonical
 * layer and of nothing else, each of which would fail quietly rather than loudly:
 *
 *   1. Search issues the identifiers the CASCADE issues — a city carries its seven-digit
 *      `census_place_geoid`, never the `location_places` surrogate key. An id of the wrong shape
 *      would not error; it would resolve to nothing, one tier down, on a later request.
 *   2. Cities and neighbourhoods come from ONE table, split by `type`, and neither leaks into the
 *      other's tier.
 *   3. The canonical parent comes from `is_primary`, not from a derived rule.
 *   4. A canonical row the cascade cannot hold — no census id, no county, inactive — is not offered.
 *   5. Published spellings still work for the tiers that have no match surface (counties, states),
 *      and need no spelling expansion for the tiers that do.
 *   6. Nothing is written.
 */
class LocationPlaceSearchRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private LocationPlaceSearchRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = new LocationPlaceSearchRepository();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Fixture builders
    // ─────────────────────────────────────────────────────────────────────

    private function state(string $geoid, string $usps, string $name): void
    {
        DB::table('census_states')->insert(compact('geoid', 'usps', 'name'));
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

    /**
     * A canonical place. `$counties` is ordered: the FIRST is written as `is_primary`, so a test can
     * state which parent is canonical instead of depending on GEOID ordering.
     */
    private function place(
        string $name,
        string $stateGeoid,
        ?string $censusPlaceGeoid,
        array $counties = [],
        string $type = LocationPlace::TYPE_CITY,
        ?int $parentId = null,
        bool $active = true,
    ): int {
        $id = (int) DB::table('location_places')->insertGetId([
            'name'               => $name,
            'name_key'           => PlaceNameKey::of($name),
            'type'               => $type,
            'state_geoid'        => $stateGeoid,
            'census_place_geoid' => $censusPlaceGeoid,
            'parent_place_id'    => $parentId,
            'source'             => $censusPlaceGeoid === null
                ? LocationPlace::SOURCE_SUPPLEMENTAL
                : LocationPlace::SOURCE_CENSUS,
            'active'             => $active,
        ]);

        foreach ($counties as $i => $countyGeoid) {
            DB::table('location_place_counties')->insert([
                'location_place_id' => $id,
                'county_geoid'      => $countyGeoid,
                'is_primary'        => $i === 0,
            ]);
        }

        return $id;
    }

    private function neighborhood(string $name, string $stateGeoid, int $parentId, bool $active = true): int
    {
        return $this->place(
            $name,
            $stateGeoid,
            null,
            [],
            LocationPlace::TYPE_NEIGHBORHOOD,
            $parentId,
            $active,
        );
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

    /**
     * Florida: two counties, Clearwater straddling both with Pinellas primary, plus Tampa.
     *
     * @return array{clearwater: int, tampa: int}
     */
    private function seedFlorida(): array
    {
        $this->state('12', 'FL', 'Florida');
        $this->county('12103', 'Pinellas County', 'Pinellas');
        $this->county('12057', 'Hillsborough County', 'Hillsborough');

        return [
            // Hillsborough has the LOWER geoid, so a "lowest wins" rule would pick it — the primary
            // flag says Pinellas, and that is what must be honoured.
            'clearwater' => $this->place('Clearwater', '12', '1212925', ['12103', '12057']),
            'tampa'      => $this->place('Tampa', '12', '1271000', ['12057']),
        ];
    }

    /** @return list<string> */
    private function labels(GeographySearchResult $r): array
    {
        return array_map(static fn ($m): string => $m->label(), $r->matches);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Identifier parity — the contract that lets search seed the cascade
    // ─────────────────────────────────────────────────────────────────────

    /**
     * A city carries the CENSUS place GEOID, never the `location_places` surrogate key. This is the
     * assertion the whole refactor had to preserve.
     *
     * @test
     */
    public function a_city_match_issues_the_census_geoid_not_the_canonical_row_id(): void
    {
        $ids = $this->seedFlorida();

        $match = $this->repo->search(GeographyQuery::for('Tampa'))->ofKind(GeographyOption::KIND_CITY)[0];

        $this->assertSame('1271000', $match->option->id);
        $this->assertNotSame((string) $ids['tampa'], $match->option->id, 'the surrogate key must not leak');
        $this->assertSame('12057', $match->option->parentId);
        $this->assertSame(MatchType::Exact, $match->matchType);
    }

    /** @test */
    public function leading_zeros_survive_the_query_layer(): void
    {
        $this->state('01', 'AL', 'Alabama');
        $this->county('01001', 'Autauga County', 'Autauga');
        $this->place('Prattville', '01', '0100100', ['01001']);

        $city = $this->repo->search(GeographyQuery::for('Prattville'))->ofKind(GeographyOption::KIND_CITY)[0];

        $this->assertSame('0100100', $city->option->id);
        $this->assertSame('01001', $city->option->parentId);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Multi-county behaviour — preserved, and driven by is_primary
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function a_county_straddling_place_is_one_row_carrying_both_parents(): void
    {
        $this->seedFlorida();

        $cities = $this->repo->search(GeographyQuery::for('Clearwater'))->ofKind(GeographyOption::KIND_CITY);

        $this->assertCount(1, $cities);
        $this->assertTrue($cities[0]->hasMultipleParents());
        $this->assertEqualsCanonicalizing(['12103', '12057'], $cities[0]->parentIds);
    }

    /**
     * THE CANONICAL PARENT COMES FROM `is_primary`, NOT FROM A DERIVED RULE.
     *
     * Hillsborough (12057) sorts before Pinellas (12103), so the first cut's "lowest GEOID wins"
     * would have picked it. The layer records Pinellas as primary and that must win — deriving a
     * canonical parent when the data states one is how two components come to disagree.
     *
     * @test
     */
    public function the_primary_county_is_the_canonical_parent(): void
    {
        $this->seedFlorida();

        $city = $this->repo->search(GeographyQuery::for('Clearwater'))->ofKind(GeographyOption::KIND_CITY)[0];

        $this->assertSame('12103', $city->option->parentId, 'is_primary must beat GEOID ordering');
        $this->assertSame('12103', $city->parentIds[0]);
        $this->assertStringStartsWith('Pinellas County', $city->breadcrumb);
        $this->assertStringContainsString('+1', $city->breadcrumb);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Rows the cascade cannot hold are not offered
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function a_canonical_place_without_a_census_id_is_not_offered_as_a_city(): void
    {
        $this->state('12', 'FL', 'Florida');
        $this->county('12103', 'Pinellas County', 'Pinellas');
        $this->place('Ghostville', '12', null, ['12103']);

        $this->assertEmpty($this->repo->search(GeographyQuery::for('Ghostville'))->ofKind(GeographyOption::KIND_CITY));
    }

    /** @test */
    public function a_place_with_no_county_relationship_is_not_offered(): void
    {
        $this->state('12', 'FL', 'Florida');
        $this->place('Orphanville', '12', '1299999');

        $this->assertTrue($this->repo->search(GeographyQuery::for('Orphanville'))->isEmpty());
    }

    /** @test */
    public function an_inactive_place_is_not_offered(): void
    {
        $this->state('12', 'FL', 'Florida');
        $this->county('12103', 'Pinellas County', 'Pinellas');
        $this->place('Sleepyville', '12', '1288888', ['12103'], LocationPlace::TYPE_CITY, null, active: false);

        $this->assertTrue($this->repo->search(GeographyQuery::for('Sleepyville'))->isEmpty());
    }

    // ─────────────────────────────────────────────────────────────────────
    // Ambiguity
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function an_ambiguous_name_returns_every_candidate_with_distinguishing_context(): void
    {
        $this->state('12', 'FL', 'Florida');
        $this->state('17', 'IL', 'Illinois');
        $this->county('12103', 'Pinellas County', 'Pinellas');
        $this->county('17167', 'Sangamon County', 'Sangamon');
        $this->place('Springfield', '12', '1267875', ['12103']);
        $this->place('Springfield', '17', '1772000', ['17167']);

        $cities = $this->repo->search(GeographyQuery::for('Springfield'))->ofKind(GeographyOption::KIND_CITY);

        $this->assertCount(2, $cities);

        $breadcrumbs = array_map(static fn ($m): string => $m->breadcrumb, $cities);
        $this->assertCount(2, array_unique($breadcrumbs), 'identical breadcrumbs disambiguate nothing');
        $this->assertContains('Pinellas County, FL', $breadcrumbs);
        $this->assertContains('Sangamon County, IL', $breadcrumbs);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scope
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function a_typed_state_suffix_narrows_the_search(): void
    {
        $this->state('12', 'FL', 'Florida');
        $this->state('17', 'IL', 'Illinois');
        $this->county('12103', 'Pinellas County', 'Pinellas');
        $this->county('17167', 'Sangamon County', 'Sangamon');
        $this->place('Springfield', '12', '1267875', ['12103']);
        $this->place('Springfield', '17', '1772000', ['17167']);

        $cities = $this->repo->search(GeographyQuery::for('Springfield, IL'))->ofKind(GeographyOption::KIND_CITY);

        $this->assertCount(1, $cities);
        $this->assertSame('1772000', $cities[0]->option->id);
    }

    /** @test */
    public function an_unknown_state_suffix_does_not_empty_the_result(): void
    {
        $this->seedFlorida();

        $this->assertNotEmpty($this->repo->search(GeographyQuery::for('Tampa, XX'))->ofKind(GeographyOption::KIND_CITY));
    }

    /** @test */
    public function an_explicit_county_scope_restricts_places(): void
    {
        $this->seedFlorida();

        $in  = $this->repo->search(GeographyQuery::for('Tampa', countyIds: ['12057']))->ofKind(GeographyOption::KIND_CITY);
        $out = $this->repo->search(GeographyQuery::for('Tampa', countyIds: ['12103']))->ofKind(GeographyOption::KIND_CITY);

        $this->assertCount(1, $in);
        $this->assertCount(0, $out);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Tiers
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function a_county_is_found_by_its_bare_basename_as_an_exact_match(): void
    {
        $this->seedFlorida();

        $counties = $this->repo->search(GeographyQuery::for('Pinellas'))->ofKind(GeographyOption::KIND_COUNTY);

        $this->assertCount(1, $counties);
        $this->assertSame('12103', $counties[0]->option->id);
        $this->assertSame(MatchType::Exact, $counties[0]->matchType);
    }

    /** @test */
    public function a_state_is_found_by_name_and_by_exact_abbreviation(): void
    {
        $this->seedFlorida();

        $byName = $this->repo->search(GeographyQuery::for('Florida'))->ofKind(GeographyOption::KIND_STATE);
        $byUsps = $this->repo->search(GeographyQuery::for('FL'))->ofKind(GeographyOption::KIND_STATE);

        $this->assertSame('12', $byName[0]->option->id);
        $this->assertSame('12', $byUsps[0]->option->id);
        $this->assertSame('FL', $byUsps[0]->option->abbreviation);
    }

    /**
     * A state's exact name outranks a real city of the same name, and the city survives.
     *
     * Florida, Missouri is a real place. So are Florida, New York and Florida, Ohio.
     *
     * @test
     */
    public function an_exact_state_name_outranks_a_city_of_the_same_name(): void
    {
        $this->state('12', 'FL', 'Florida');
        $this->state('29', 'MO', 'Missouri');
        $this->county('29137', 'Monroe County', 'Monroe');
        $this->place('Florida', '29', '2926586', ['29137']);

        $result = $this->repo->search(GeographyQuery::for('Florida'));

        $this->assertSame(GeographyOption::KIND_STATE, $result->matches[0]->option->kind);
        $this->assertSame('12', $result->matches[0]->option->id);

        // Reordered, not filtered.
        $cities = $result->ofKind(GeographyOption::KIND_CITY);
        $this->assertCount(1, $cities, 'the Missouri city must still be findable');
        $this->assertSame('2926586', $cities[0]->option->id);
    }

    /**
     * AN EXACT ABBREVIATION IS AN EXACT MATCH, even though the state's NAME only prefix-matches it.
     *
     * This is the regression that made `FL` return the city of Flat, Alaska first: the abbreviation
     * used to be consulted only when the name matched nothing, and "Florida" prefix-matches "fl",
     * so the two-letter code was never examined and was recorded as a weak partial.
     *
     * @test
     */
    public function an_exact_state_abbreviation_ranks_above_prefix_matched_cities(): void
    {
        $this->state('12', 'FL', 'Florida');
        $this->state('02', 'AK', 'Alaska');
        $this->county('02290', 'Yukon-Koyukuk Census Area', 'Yukon-Koyukuk');
        $this->place('Flat', '02', '0225100', ['02290']);

        $result = $this->repo->search(GeographyQuery::for('FL'));

        $this->assertSame(GeographyOption::KIND_STATE, $result->matches[0]->option->kind);
        $this->assertSame('12', $result->matches[0]->option->id);
        $this->assertSame(
            MatchType::Exact,
            $result->matches[0]->matchType,
            'the abbreviation is an exact match even when the name only prefixes'
        );

        $this->assertNotEmpty($result->ofKind(GeographyOption::KIND_CITY), 'Flat must still be offered');
    }

    /**
     * The abbreviation is weighed ALONGSIDE the name rather than only when the name fails.
     *
     * Asserted on the match type directly, because the ranking assertion above would still pass if
     * the state won for some unrelated reason.
     *
     * @test
     */
    public function a_state_abbreviation_is_classified_exact_even_when_the_name_prefix_matches(): void
    {
        $this->state('12', 'FL', 'Florida');

        $states = $this->repo->search(GeographyQuery::for('FL'))->ofKind(GeographyOption::KIND_STATE);

        $this->assertCount(1, $states);
        $this->assertSame(MatchType::Exact, $states[0]->matchType);
    }

    /**
     * AMBIGUITY SURVIVES THE RANKING CHANGE.
     *
     * "Springfield" names no state, so nothing here promotes anything; the point is that the new
     * bonus cannot collapse a genuinely ambiguous result into one row.
     *
     * @test
     */
    public function the_ranking_change_does_not_collapse_ambiguous_results(): void
    {
        $this->state('12', 'FL', 'Florida');
        $this->state('17', 'IL', 'Illinois');
        $this->state('29', 'MO', 'Missouri');
        $this->county('12103', 'Pinellas County', 'Pinellas');
        $this->county('17167', 'Sangamon County', 'Sangamon');
        $this->county('29077', 'Greene County', 'Greene');
        $this->place('Springfield', '12', '1267875', ['12103']);
        $this->place('Springfield', '17', '1772000', ['17167']);
        $this->place('Springfield', '29', '2970000', ['29077']);

        $cities = $this->repo->search(GeographyQuery::for('Springfield'))->ofKind(GeographyOption::KIND_CITY);

        $this->assertCount(3, $cities);

        $breadcrumbs = array_map(static fn ($m): string => $m->breadcrumb, $cities);
        $this->assertCount(3, array_unique($breadcrumbs), 'each candidate keeps distinguishing context');
    }

    /** @test */
    public function a_zip_is_found_by_prefix_and_collapses_across_its_counties(): void
    {
        $this->seedFlorida();
        $this->zcta('33756', '12103', '12057');

        $zips = $this->repo->search(GeographyQuery::for('337'))->ofKind(GeographyOption::KIND_ZIP);

        $this->assertCount(1, $zips);
        $this->assertSame('33756', $zips[0]->option->id);
        $this->assertSame(MatchType::Prefix, $zips[0]->matchType);
        $this->assertEqualsCanonicalizing(['12057', '12103'], $zips[0]->parentIds);
    }

    /** @test */
    public function a_non_numeric_term_returns_no_zips(): void
    {
        $this->seedFlorida();
        $this->zcta('33756', '12103');

        $this->assertCount(0, $this->repo->search(GeographyQuery::for('Clearwater'))->ofKind(GeographyOption::KIND_ZIP));
    }

    /** @test */
    public function a_caller_can_restrict_the_search_to_one_tier(): void
    {
        $this->seedFlorida();

        $result = $this->repo->search(GeographyQuery::for('Pinellas', [GeographyTier::Counties]));

        $this->assertNotEmpty($result->ofKind(GeographyOption::KIND_COUNTY));
        $this->assertEmpty($result->ofKind(GeographyOption::KIND_CITY));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Spellings — no expansion needed on the match surface
    // ─────────────────────────────────────────────────────────────────────

    /**
     * `name_key` is the stored match surface, so every spelling reduces to it and the three-way
     * expansion the first cut needed is gone.
     *
     * @test
     */
    public function a_saint_place_is_found_by_every_spelling_without_expansion(): void
    {
        $this->state('12', 'FL', 'Florida');
        $this->county('12103', 'Pinellas County', 'Pinellas');
        $this->place('St. Petersburg', '12', '1263000', ['12103']);

        foreach (['St. Petersburg', 'Saint Petersburg', 'St Petersburg'] as $typed) {
            $cities = $this->repo->search(GeographyQuery::for($typed))->ofKind(GeographyOption::KIND_CITY);

            $this->assertCount(1, $cities, "`{$typed}` should find the canonical St. Petersburg");
            $this->assertSame('1263000', $cities[0]->option->id);
        }
    }

    /**
     * Counties have no match surface of their own — they are still read from the published census
     * column, so the published abbreviation must still be findable.
     *
     * @test
     */
    public function a_county_published_with_an_abbreviation_is_still_found(): void
    {
        $this->state('29', 'MO', 'Missouri');
        $this->county('29189', 'St. Louis County', 'St. Louis');

        $counties = $this->repo->search(GeographyQuery::for('St. Louis'))->ofKind(GeographyOption::KIND_COUNTY);

        $this->assertCount(1, $counties);
        $this->assertSame('29189', $counties[0]->option->id);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Neighbourhood tier — one table, two tiers, no leakage
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function neighborhoods_are_absent_while_the_tier_is_off(): void
    {
        $ids = $this->seedFlorida();
        $this->neighborhood('Clearwater Beach', '12', $ids['clearwater']);

        $result = (new LocationPlaceSearchRepository(false))->search(GeographyQuery::for('Clearwater Beach'));

        $this->assertEmpty($result->ofKind(GeographyOption::KIND_NEIGHBORHOOD));
    }

    /** @test */
    public function neighborhoods_are_searchable_when_the_tier_is_on(): void
    {
        $ids  = $this->seedFlorida();
        $hood = $this->neighborhood('Clearwater Beach', '12', $ids['clearwater']);

        $hits = (new LocationPlaceSearchRepository(true))
            ->search(GeographyQuery::for('Clearwater Beach'))
            ->ofKind(GeographyOption::KIND_NEIGHBORHOOD);

        $this->assertCount(1, $hits);
        $this->assertSame((string) $hood, $hits[0]->option->id, 'a neighbourhood keeps its surrogate id');
        $this->assertSame('1212925', $hits[0]->option->parentId, 'and is parented by its CITY geoid');
        $this->assertSame('Clearwater, FL', $hits[0]->breadcrumb);
    }

    /**
     * Cities and neighbourhoods share a table; a search that matches both must place each in its
     * own tier rather than letting the wider one absorb the other.
     *
     * @test
     */
    public function one_query_splits_cities_and_neighborhoods_into_their_own_tiers(): void
    {
        $ids = $this->seedFlorida();
        $this->neighborhood('Clearwater Beach', '12', $ids['clearwater']);

        $result = (new LocationPlaceSearchRepository(true))->search(GeographyQuery::for('Clearwater'));

        $this->assertCount(1, $result->ofKind(GeographyOption::KIND_CITY));
        $this->assertCount(1, $result->ofKind(GeographyOption::KIND_NEIGHBORHOOD));

        // The exact city outranks the prefix-matched neighbourhood.
        $this->assertSame('Clearwater', $result->matches[0]->label());
    }

    /** @test */
    public function an_inactive_neighborhood_is_not_offered(): void
    {
        $ids = $this->seedFlorida();
        $this->neighborhood('Clearwater Beach', '12', $ids['clearwater'], active: false);

        $hits = (new LocationPlaceSearchRepository(true))
            ->search(GeographyQuery::for('Clearwater Beach'))
            ->ofKind(GeographyOption::KIND_NEIGHBORHOOD);

        $this->assertCount(0, $hits);
    }

    /**
     * A neighbourhood whose parent city is inactive has no tier above it to hang from — the same
     * rule the neighbourhood tier itself enforces.
     *
     * @test
     */
    public function a_neighborhood_whose_parent_city_is_inactive_is_not_offered(): void
    {
        $this->state('12', 'FL', 'Florida');
        $this->county('12103', 'Pinellas County', 'Pinellas');
        $city = $this->place('Clearwater', '12', '1212925', ['12103'], LocationPlace::TYPE_CITY, null, active: false);
        $this->neighborhood('Clearwater Beach', '12', $city);

        $hits = (new LocationPlaceSearchRepository(true))
            ->search(GeographyQuery::for('Clearwater Beach'))
            ->ofKind(GeographyOption::KIND_NEIGHBORHOOD);

        $this->assertCount(0, $hits);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Safety
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function an_unusable_term_never_reaches_the_database(): void
    {
        $this->seedFlorida();

        DB::enableQueryLog();
        $result = $this->repo->search(GeographyQuery::for('c'));
        $log    = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertTrue($result->isEmpty());
        $this->assertSame([], $log, 'a term below the floor must not issue a query');
    }

    /** @test */
    public function like_wildcards_in_the_term_are_neutralised(): void
    {
        $this->seedFlorida();

        $this->assertTrue($this->repo->search(GeographyQuery::for('%%'))->isEmpty());
        $this->assertTrue($this->repo->search(GeographyQuery::for('__'))->isEmpty());
    }

    /**
     * THE LIKE ESCAPE CHARACTER MUST NOT BE A BACKSLASH.
     *
     * This is a source assertion rather than a behavioural one, and that is deliberate: the bug it
     * guards against is INVISIBLE TO THIS SUITE. PDO reads the backslash in `escape '\'` as
     * escaping the closing quote, decides the literal is unterminated, and swallows the `?`
     * placeholders that follow — so Postgres raises `SQLSTATE[HY093]: Invalid parameter number` for
     * every tier that ORs more than one such clause together. SQLite parses the same statement
     * without complaint, so all 26 tests here passed against code that could not run in production.
     *
     * A behavioural test cannot catch that on the suite's driver. A source guard can, and does.
     *
     * @test
     */
    public function the_like_escape_character_is_not_a_backslash(): void
    {
        $source = (string) file_get_contents(
            base_path('app/Services/LocationDna/Criteria/Search/LocationPlaceSearchRepository.php')
        );

        $this->assertStringNotContainsString(
            "escape \\'\\\\\\'",
            $source,
            'A backslash LIKE escape breaks placeholder parsing on Postgres. Use LIKE_ESCAPE.'
        );

        $this->assertMatchesRegularExpression(
            "/private const LIKE_ESCAPE\s*=\s*'!'/",
            $source,
            'The escape character must stay a non-backslash literal.'
        );

        // Every raw LIKE goes through the constant rather than an inline literal, so there is one
        // place to change and one place to be wrong.
        $total  = preg_match_all('/like \? escape /', $source);
        $viaConst = preg_match_all("/like \? escape '\.self::LIKE_ESCAPE_SQL/", $source);

        $this->assertGreaterThan(0, $total, 'the raw LIKE clauses should still be here');
        $this->assertSame(
            $total,
            $viaConst,
            'Every raw LIKE clause must reference LIKE_ESCAPE_SQL, never an inline quoted escape.'
        );
    }

    /**
     * A term containing the escape character itself still matches literally.
     *
     * The escape char has to be escaped FIRST in {@see LocationPlaceSearchRepository::escapeLike()},
     * or a `!` typed by a user would consume the character after it and silently change the search.
     *
     * @test
     */
    public function a_literal_escape_character_in_the_term_is_matched_literally(): void
    {
        $this->state('12', 'FL', 'Florida');
        $this->county('12103', 'Pinellas County', 'Pinellas');
        $this->place('Hi! Beach', '12', '1277777', ['12103']);
        $this->place('Hix Beach', '12', '1277778', ['12103']);

        $cities = $this->repo->search(GeographyQuery::for('Hi! Beach'))->ofKind(GeographyOption::KIND_CITY);

        $this->assertCount(1, $cities, 'the `!` must be a literal, not an escape');
        $this->assertSame('1277777', $cities[0]->option->id);
    }

    /**
     * A multi-word term exercises the OR'd multi-pattern path — the shape that failed on Postgres.
     *
     * Kept as a behavioural companion to the source guard above: it proves the patterns still find
     * what they should, on whichever driver the suite runs.
     *
     * @test
     */
    public function a_multi_word_term_still_matches_across_the_or_patterns(): void
    {
        $this->state('12', 'FL', 'Florida');
        $this->county('12103', 'Pinellas County', 'Pinellas');
        $this->place('Palm Harbor', '12', '1254075', ['12103']);

        foreach (['Palm Harbor', 'palm harbor', 'Harbor'] as $typed) {
            $cities = $this->repo->search(GeographyQuery::for($typed))->ofKind(GeographyOption::KIND_CITY);

            $this->assertCount(1, $cities, "`{$typed}` should match Palm Harbor");
            $this->assertSame('1254075', $cities[0]->option->id);
        }
    }

    /** @test */
    public function searching_writes_nothing(): void
    {
        $this->seedFlorida();

        $before = [
            'places'   => DB::table('location_places')->count(),
            'pivot'    => DB::table('location_place_counties')->count(),
            'counties' => DB::table('census_counties')->count(),
        ];

        $this->repo->search(GeographyQuery::for('Clearwater'));
        $this->repo->search(GeographyQuery::for('337'));

        $this->assertSame($before['places'], DB::table('location_places')->count());
        $this->assertSame($before['pivot'], DB::table('location_place_counties')->count());
        $this->assertSame($before['counties'], DB::table('census_counties')->count());
    }
}
