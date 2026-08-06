<?php

namespace Tests\Feature\LocationDna;

use App\Services\LocationDna\Criteria\EloquentCriteriaGeographyRepository;
use App\Services\LocationDna\Criteria\GeographyOption;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 1a — the Eloquent repository against the real `us_*` reference tables.
 *
 * WHY THIS SUITE EXISTS ALONGSIDE THE FAKE'S
 * ------------------------------------------
 * The fake proves the CONTRACT. This proves the two things only a real schema can answer, both of
 * which were measured in the live data and both of which would silently corrupt the cascade:
 *
 *   1. `us_zip_codes` has no county FK. It joins on a bare county NAME ("Suffolk") plus
 *      `state_abbrev`, while `us_counties.name` carries the class suffix ("Autauga County").
 *      Louisiana uses "Parish", Alaska "Borough" / "Census Area" / "Municipality", Virginia has
 *      independent "city" rows. Every one of those must resolve.
 *   2. 3,000 of 34,741 ZIPs are stored unpadded — the first row in the table is `"501"`, which is
 *      ZIP 00501. Emitting that verbatim produces an option no user can match.
 *
 * It also pins the portability constraint: nothing here may use `ILIKE`, which SQLite rejects and
 * which the reference models' own `search()` helpers all use.
 *
 * Rows are inserted directly rather than through factories: these are reference tables, not
 * domain models, and several have no factory.
 */
class EloquentCriteriaGeographyRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private EloquentCriteriaGeographyRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = new EloquentCriteriaGeographyRepository();
    }

    private function state(string $name, string $abbrev, ?string $fips = null): int
    {
        return (int) DB::table('us_states')->insertGetId([
            'name'         => $name,
            'abbreviation' => $abbrev,
            'fips_code'    => $fips,
        ]);
    }

    private function county(string $name, int $stateId, ?string $fips = null): int
    {
        return (int) DB::table('us_counties')->insertGetId([
            'name'      => $name,
            'state_id'  => $stateId,
            'fips_code' => $fips,
        ]);
    }

    private function city(string $name, int $stateId, int $countyId): int
    {
        return (int) DB::table('us_cities')->insertGetId([
            'name'      => $name,
            'state_id'  => $stateId,
            'county_id' => $countyId,
        ]);
    }

    private function zip(string $zip, string $city, string $abbrev, ?string $county): void
    {
        DB::table('us_zip_codes')->insert([
            'zip_code'     => $zip,
            'city'         => $city,
            'state_abbrev' => $abbrev,
            'state_name'   => $abbrev,
            'county'       => $county,
        ]);
    }

    // ── the cascade ──────────────────────────────────────────────────────────

    public function test_states_are_returned_with_their_fips_as_the_code(): void
    {
        $id = $this->state('Zzytopia', 'ZZ', '99');

        $match = collect($this->repo->states())->firstWhere('id', (string) $id);

        $this->assertNotNull($match);
        $this->assertSame('Zzytopia', $match->name);
        $this->assertSame('99', $match->code);
        $this->assertSame(GeographyOption::KIND_STATE, $match->kind);
    }

    /**
     * Phase 1d-3 — the abbreviation comes from `us_states.abbreviation`.
     *
     * It is the suffix in the cascade's stored `Pinellas County, FL` label. Asserted at the
     * repository boundary so the label's raw material is pinned to a column rather than to a
     * lookup a consumer performs for itself — the coupling that made this value wrong under the
     * census source.
     */
    public function test_a_state_carries_its_usps_abbreviation(): void
    {
        $id = $this->state('Zzytopia', 'ZZ', '99');

        $match = collect($this->repo->states())->firstWhere('id', (string) $id);

        $this->assertNotNull($match);
        $this->assertSame('ZZ', $match->abbreviation);
    }

    /** Only states carry one — the tiers below have no abbreviation to carry. */
    public function test_no_sub_state_tier_carries_an_abbreviation(): void
    {
        $stateId = $this->state('Zzytopia', 'ZZ', '99');
        $this->county('Pinellas County', $stateId, '99103');

        $this->assertNull($this->repo->countiesInState((string) $stateId)[0]->abbreviation);
    }

    public function test_counties_are_scoped_to_their_state_and_carry_the_geoid(): void
    {
        $fl = $this->state('Floridia', 'FZ', '90');
        $al = $this->state('Alabamia', 'AZ', '91');

        $this->county('Pinellas County', $fl, '90103');
        $this->county('Baldwin County', $al, '91003');

        $counties = $this->repo->countiesInState((string) $fl);

        $this->assertCount(1, $counties);
        $this->assertSame('Pinellas County', $counties[0]->name);
        $this->assertSame('90103', $counties[0]->code);
        $this->assertSame((string) $fl, $counties[0]->parentId);
    }

    public function test_cities_span_the_selected_counties_only(): void
    {
        $st = $this->state('Citystan', 'CZ', '92');
        $a  = $this->county('Alpha County', $st);
        $b  = $this->county('Beta County', $st);

        $this->city('Aville', $st, $a);
        $this->city('Bville', $st, $b);

        $cities = $this->repo->citiesInCounties([(string) $a]);

        $this->assertSame(['Aville'], array_map(fn ($o) => $o->name, $cities));
        $this->assertSame((string) $a, $cities[0]->parentId);
    }

    // ── data reality 1: the county-name join ─────────────────────────────────

    /**
     * Every county-class suffix resolves against the bare name `us_zip_codes` stores.
     *
     * Parish / Borough / Census Area / Municipality are not decoration — they are the actual
     * `us_counties.name` forms for Louisiana and Alaska, and a naive `" County"`-only strip
     * silently returns no ZIPs for every one of those counties.
     */
    public function test_every_county_class_suffix_joins_to_the_zip_table(): void
    {
        $st = $this->state('Suffixia', 'SX', '93');

        $cases = [
            'Orleans Parish'          => 'Orleans',
            'Nome Census Area'        => 'Nome',
            'Anchorage Municipality'  => 'Anchorage',
            'Juneau City and Borough' => 'Juneau',
            'Kodiak Island Borough'   => 'Kodiak Island',
            'Pinellas County'         => 'Pinellas',
        ];

        $ids  = [];
        $code = 70000;

        foreach ($cases as $fullName => $bareName) {
            $ids[$fullName] = $this->county($fullName, $st);
            $this->zip((string) $code++, 'Somewhere', 'SX', $bareName);
        }

        $zips = $this->repo->zipsInCounties(array_map(strval(...), array_values($ids)));

        $this->assertCount(
            count($cases),
            $zips,
            'every county-class suffix must resolve; a missing one means that county silently '
            .'returns no ZIPs in production'
        );

        // And each ZIP is attributed to the county that actually produced it.
        $byParent = [];
        foreach ($zips as $zip) {
            $byParent[$zip->parentId] = $zip->id;
        }
        $this->assertCount(count($cases), $byParent, 'each county must own exactly one ZIP here');
    }

    /** A county whose name carries no recognised suffix still matches on its full name. */
    public function test_a_county_without_a_class_suffix_matches_on_its_full_name(): void
    {
        $st = $this->state('Bareland', 'BX', '94');
        $c  = $this->county('Districtia', $st);

        $this->zip('71000', 'Somewhere', 'BX', 'Districtia');

        $zips = $this->repo->zipsInCounties([(string) $c]);

        $this->assertSame(['71000'], array_map(fn ($o) => $o->id, $zips));
    }

    /**
     * A same-named county in a different state does not leak in.
     *
     * Selecting ONE county is not enough to exercise this: the `state_abbrev` whereIn already
     * excludes the other state, so the pair check is not load-bearing in that case. (A mutation
     * probe that removed the pair check passed this test in its original single-county form —
     * the test was weaker than it looked.)
     */
    public function test_a_same_named_county_in_another_state_does_not_leak(): void
    {
        $one = $this->state('Onetopia', 'O1', '95');
        $two = $this->state('Twotopia', 'T2', '96');

        $wanted = $this->county('Suffolk County', $one);
        $this->county('Suffolk County', $two);

        $this->zip('72000', 'Here', 'O1', 'Suffolk');
        $this->zip('72001', 'There', 'T2', 'Suffolk');

        $zips = $this->repo->zipsInCounties([(string) $wanted]);

        $this->assertSame(['72000'], array_map(fn ($o) => $o->id, $zips));
        $this->assertSame((string) $wanted, $zips[0]->parentId);
    }

    /**
     * THE CROSS-PRODUCT CASE · a (county name, state) pair that was never requested is excluded.
     *
     * `zipsInCounties()` issues two independent `whereIn` clauses — one over county names, one
     * over state abbreviations. When the selection spans two states AND two names, SQL admits the
     * combinations nobody asked for: here "Alpha" is in the name list and "S2" is in the state
     * list, so a ZIP in Alpha County, State 2 comes back from the database even though the user
     * selected Alpha in State 1 and Beta in State 2.
     *
     * The (name, state) pair lookup is the only thing that rejects it. This is the test that makes
     * that check load-bearing; without at least two states and two names in play, removing the
     * check changes nothing observable.
     */
    public function test_a_cross_product_county_state_pair_is_excluded(): void
    {
        $s1 = $this->state('Firstland', 'S1', '80');
        $s2 = $this->state('Secondland', 'S2', '81');

        $alphaInS1 = $this->county('Alpha County', $s1);
        $betaInS2  = $this->county('Beta County', $s2);

        // Both requested pairs.
        $this->zip('74000', 'A', 'S1', 'Alpha');
        $this->zip('74001', 'B', 'S2', 'Beta');

        // The cross-product row: name from the first selection, state from the second.
        $this->zip('74002', 'Ghost', 'S2', 'Alpha');

        $zips = $this->repo->zipsInCounties([(string) $alphaInS1, (string) $betaInS2]);

        $this->assertSame(
            ['74000', '74001'],
            array_map(fn ($o) => $o->id, $zips),
            'ZIP 74002 is Alpha County in State 2 — a pair the user never selected.'
        );
    }

    // ── data reality 2: unpadded ZIPs ────────────────────────────────────────

    /** A short-stored ZIP is emitted in canonical five-digit form. */
    public function test_unpadded_zips_are_returned_padded_to_five_digits(): void
    {
        $st = $this->state('Padland', 'PZ', '97');
        $c  = $this->county('Holtsville County', $st);

        $this->zip('501', 'Holtsville', 'PZ', 'Holtsville');

        $zips = $this->repo->zipsInCounties([(string) $c]);

        $this->assertSame(['00501'], array_map(fn ($o) => $o->id, $zips));
        $this->assertSame('00501', $zips[0]->code, 'the code must be padded too');
    }

    // ── input hygiene ────────────────────────────────────────────────────────

    public function test_empty_and_non_numeric_inputs_yield_empty_lists(): void
    {
        $this->assertSame([], $this->repo->countiesInState(''));
        $this->assertSame([], $this->repo->countiesInState('Florida'));
        $this->assertSame([], $this->repo->countiesInState('0'));
        $this->assertSame([], $this->repo->citiesInCounties([]));
        $this->assertSame([], $this->repo->citiesInCounties(['not-an-id']));
        $this->assertSame([], $this->repo->zipsInCounties([]));
        $this->assertSame([], $this->repo->zipsInCounties(['not-an-id']));
    }

    // ── the safety property ──────────────────────────────────────────────────

    /**
     * THE PHASE 1a GUARANTEE · reading the cascade writes nothing.
     *
     * Asserted from the query log rather than from source, so it covers whatever the ORM decides
     * to do rather than what the code appears to say.
     */
    public function test_the_repository_performs_no_writes(): void
    {
        $st = $this->state('Readonlyia', 'RZ', '98');
        $c  = $this->county('Quiet County', $st);
        $this->city('Hush', $st, $c);
        $this->zip('73000', 'Hush', 'RZ', 'Quiet');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->repo->states();
        $this->repo->countiesInState((string) $st);
        $this->repo->citiesInCounties([(string) $c]);
        $this->repo->zipsInCounties([(string) $c]);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertNotEmpty($queries, 'the repository must actually query');

        foreach ($queries as $query) {
            $sql = strtolower((string) ($query['query'] ?? ''));

            foreach (['insert', 'update', 'delete', 'alter', 'drop', 'create'] as $write) {
                $this->assertStringNotContainsString(
                    $write.' ',
                    $sql,
                    "the repository issued a `{$write}` statement: {$sql}"
                );
            }
        }
    }
}
