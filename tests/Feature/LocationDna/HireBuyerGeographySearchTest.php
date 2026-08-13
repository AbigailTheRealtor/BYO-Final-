<?php

namespace Tests\Feature\LocationDna;

use App\Http\Livewire\Concerns\HasGeographySearch;
use App\Models\LocationPlace;
use App\Services\LocationDna\Criteria\GeographyOption;
use App\Services\LocationDna\Places\PlaceNameKey;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

/**
 * M2 — the search shortcut, and the claim the whole milestone rests on.
 *
 * SEARCH IS A SHORTCUT, NOT A SECOND WAY TO CHOOSE. Everything here exists to prove that a
 * geography reached by searching is INDISTINGUISHABLE from the same geography reached by working
 * down the three selects — same selection, same projection, same stored payload. If that holds,
 * nothing downstream has to know search exists; if it does not, M2 has quietly introduced a second
 * source of truth and the storage format is no longer one thing.
 *
 * WHY THE HOST IS A STAND-IN RATHER THAN THE REAL COMPONENT
 * ---------------------------------------------------------
 * `BuyerAgentAuction` hydrates auctions, resolves drafts and touches a dozen services during
 * `mount()`, none of which this suite is asking about. The stand-in below carries the SAME TWO
 * REAL TRAITS the real components carry and nothing else, so what is exercised is the traits'
 * interaction — which is the entire subject — without a fixture of unrelated collaborators. The
 * wiring of the real components is asserted separately, by source, in
 * `hire_buyer_components_are_wired_for_search()`.
 */
class HireBuyerGeographySearchTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Search only answers under the census source, and only with both gates open.
        config([
            'criteria_location_dna.geography_source'            => 'census',
            'criteria_location_dna.geography_cascade_enabled'   => true,
            'criteria_location_dna.geography_cascade_workflows' => ['hire_buyer'],
            'criteria_location_dna.geography_search_enabled'    => true,
            'criteria_location_dna.neighborhood_tier_enabled'   => false,
        ]);

        $this->seedCorpus();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Fixture
    // ─────────────────────────────────────────────────────────────────────

    private function seedCorpus(): void
    {
        DB::table('census_states')->insert([
            ['geoid' => '12', 'usps' => 'FL', 'name' => 'Florida'],
            ['geoid' => '17', 'usps' => 'IL', 'name' => 'Illinois'],
        ]);

        DB::table('census_counties')->insert([
            ['geoid' => '12103', 'state_geoid' => '12', 'countyfp' => '103', 'name' => 'Pinellas County', 'basename' => 'Pinellas'],
            ['geoid' => '12057', 'state_geoid' => '12', 'countyfp' => '057', 'name' => 'Hillsborough County', 'basename' => 'Hillsborough'],
            ['geoid' => '17167', 'state_geoid' => '17', 'countyfp' => '167', 'name' => 'Sangamon County', 'basename' => 'Sangamon'],
        ]);

        DB::table('census_places')->insert([
            ['geoid' => '1212925', 'state_geoid' => '12', 'placefp' => '12925', 'name' => 'Clearwater', 'namelsad' => 'Clearwater city'],
            ['geoid' => '1271000', 'state_geoid' => '12', 'placefp' => '71000', 'name' => 'Tampa', 'namelsad' => 'Tampa city'],
        ]);

        DB::table('census_place_counties')->insert([
            ['place_geoid' => '1212925', 'county_geoid' => '12103'],
            ['place_geoid' => '1271000', 'county_geoid' => '12057'],
        ]);

        DB::table('census_zctas')->insert([['zcta5' => '33756']]);
        DB::table('census_zcta_counties')->insert([['zcta5' => '33756', 'county_geoid' => '12103']]);

        $this->canonicalPlace('Clearwater', '12', '1212925', '12103');
        $this->canonicalPlace('Tampa', '12', '1271000', '12057');
    }

    private function canonicalPlace(string $name, string $stateGeoid, string $censusGeoid, string $countyGeoid): void
    {
        $id = (int) DB::table('location_places')->insertGetId([
            'name'               => $name,
            'name_key'           => PlaceNameKey::of($name),
            'type'               => LocationPlace::TYPE_CITY,
            'state_geoid'        => $stateGeoid,
            'census_place_geoid' => $censusGeoid,
            'source'             => LocationPlace::SOURCE_CENSUS,
            'active'             => true,
        ]);

        DB::table('location_place_counties')->insert([
            'location_place_id' => $id,
            'county_geoid'      => $countyGeoid,
            'is_primary'        => true,
        ]);
    }

    /** A host carrying the two real traits and nothing else. */
    private function host(): object
    {
        $component = new class
        {
            use \App\Http\Livewire\Concerns\HasGeographyCascade;
            use HasGeographySearch;

            public $location_dna_preferences_json = '';

            public function boot(): void
            {
                $this->bootGeographyCascade('hire_buyer');
                $this->bootGeographySearch();
            }
        };

        $component->boot();

        return $component;
    }

    /** Invoke a protected/private member without widening it in production code. */
    private function invoke(object $target, string $method, array $args = []): mixed
    {
        $m = new ReflectionMethod($target, $method);
        $m->setAccessible(true);

        return $m->invoke($target, ...$args);
    }

    private function read(object $target, string $property): mixed
    {
        $p = new ReflectionProperty($target, $property);
        $p->setAccessible(true);

        return $p->getValue($target);
    }

    /** The canonical projection — what actually reaches storage. */
    private function projection(object $host): array
    {
        return $this->invoke($host, 'geographyProjection');
    }

    // ═════════════════════════════════════════════════════════════════════
    // 1 · THE PARITY CLAIM
    // ═════════════════════════════════════════════════════════════════════

    /**
     * THE LOAD-BEARING TEST OF M2.
     *
     * Manual: state → county → city. Searched: pick the city and let back-fill supply the two
     * tiers above it. The two must produce the same selection AND the same projected payload —
     * because the projector, not this trait, is what storage sees.
     *
     * @test
     */
    public function a_searched_city_produces_the_same_payload_as_the_manual_cascade(): void
    {
        // ── Manual: three deliberate steps, in cascade order.
        $manual = $this->host();
        $manual->geoStateId = '12';
        $manual->updatedGeoStateId();
        $manual->geoCountyIds = ['12103'];
        $manual->updatedGeoCountyIds();
        $manual->geoCityIds = ['1212925'];
        $manual->updatedGeoCityIds();

        // ── Searched: one action.
        $searched = $this->host();
        $searched->geoSearchTerm = 'Clearwater';
        $searched->updatedGeoSearchTerm();
        $searched->selectGeographyMatch(GeographyOption::KIND_CITY, '1212925');

        $this->assertSame($manual->geoStateId, $searched->geoStateId, 'state must match');
        $this->assertSame($manual->geoCountyIds, $searched->geoCountyIds, 'county must be back-filled');
        $this->assertSame($manual->geoCityIds, $searched->geoCityIds, 'city must match');

        // The claim that matters: identical stored/projected geography.
        $this->assertSame(
            $this->projection($manual),
            $this->projection($searched),
            'a searched selection must project byte-identically to a manual one'
        );
    }

    /**
     * The state label suffix survives back-fill.
     *
     * `, FL` comes from a cache populated by `rememberSelectedState()`, which is PRIVATE to the
     * cascade trait and reachable only through `updatedGeoStateId()`. Assigning `$geoStateId`
     * directly would leave it stale and every stored label would silently lose its state — the
     * exact divergence the parity test above would otherwise catch only in aggregate.
     *
     * @test
     */
    public function back_fill_populates_the_state_name_and_abbreviation(): void
    {
        $host = $this->host();
        $host->geoSearchTerm = 'Clearwater';
        $host->updatedGeoSearchTerm();
        $host->selectGeographyMatch(GeographyOption::KIND_CITY, '1212925');

        $this->assertSame('Florida', $host->geoStateName);
        $this->assertSame('FL', $host->geoStateAbbrev);
    }

    // ═════════════════════════════════════════════════════════════════════
    // 2 · BACK-FILL SURVIVES RESOLUTION
    // ═════════════════════════════════════════════════════════════════════

    /**
     * The selection is still there after the resolver has run.
     *
     * Order is the whole risk: a city assigned before its county is not a city the resolver can
     * see, so it clears it on the next pass and the user watches their choice vanish a moment
     * after making it.
     *
     * @test
     */
    public function a_searched_city_survives_cascade_resolution(): void
    {
        $host = $this->host();
        $host->geoSearchTerm = 'Clearwater';
        $host->updatedGeoSearchTerm();
        $host->selectGeographyMatch(GeographyOption::KIND_CITY, '1212925');

        $this->invoke($host, 'refreshGeographyCascade');

        $this->assertSame(['1212925'], $host->geoCityIds, 'the city must survive a second resolution pass');
        $this->assertSame(['12103'], $host->geoCountyIds);
        $this->assertSame('12', $host->geoStateId);
    }

    /** @test */
    public function a_searched_zip_back_fills_its_county_and_state(): void
    {
        $host = $this->host();
        $host->geoSearchTerm = '33756';
        $host->updatedGeoSearchTerm();
        $host->selectGeographyMatch(GeographyOption::KIND_ZIP, '33756');

        $this->assertSame('12', $host->geoStateId);
        $this->assertSame(['12103'], $host->geoCountyIds);
        $this->assertSame(['33756'], $host->geoZipCodes);
    }

    /** @test */
    public function a_searched_county_back_fills_its_state(): void
    {
        $host = $this->host();
        $host->geoSearchTerm = 'Pinellas';
        $host->updatedGeoSearchTerm();
        $host->selectGeographyMatch(GeographyOption::KIND_COUNTY, '12103');

        $this->assertSame('12', $host->geoStateId);
        $this->assertSame(['12103'], $host->geoCountyIds);
    }

    /**
     * Search ADDS within a tier rather than replacing it — a Criteria selection is a set of places
     * the buyer will consider, and the user is building it up.
     *
     * @test
     */
    public function selecting_a_second_city_adds_to_the_selection(): void
    {
        $host = $this->host();

        $host->geoSearchTerm = 'Clearwater';
        $host->updatedGeoSearchTerm();
        $host->selectGeographyMatch(GeographyOption::KIND_CITY, '1212925');

        $host->geoSearchTerm = 'Tampa';
        $host->updatedGeoSearchTerm();
        $host->selectGeographyMatch(GeographyOption::KIND_CITY, '1271000');

        $this->assertEqualsCanonicalizing(['12103', '12057'], $host->geoCountyIds);
        $this->assertEqualsCanonicalizing(['1212925', '1271000'], $host->geoCityIds);
    }

    // ═════════════════════════════════════════════════════════════════════
    // 3 · THE CLEARED NOTICE
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Back-fill does not raise a cleared-selection notice.
     *
     * Choosing a city legitimately reshapes the tiers above it. Reporting that as "selections were
     * cleared" describes the user's own deliberate action back to them as data loss.
     *
     * @test
     */
    public function a_search_selection_raises_no_cleared_notice(): void
    {
        $host = $this->host();
        $host->geoStateId = '17';
        $host->updatedGeoStateId();
        $host->geoCountyIds = ['17167'];
        $host->updatedGeoCountyIds();
        $host->geoCleared = [];

        // Jumping to a Florida city clears the Illinois county — deliberately, by the user.
        $host->geoSearchTerm = 'Clearwater, FL';
        $host->updatedGeoSearchTerm();
        $host->selectGeographyMatch(GeographyOption::KIND_CITY, '1212925');

        $this->assertSame([], $host->geoCleared, 'back-fill churn must not surface as a warning');
        $this->assertSame('12', $host->geoStateId);
    }

    /**
     * A notice already on screen SURVIVES a search selection.
     *
     * Suppression discards only what back-fill caused; it is not a blanket dismissal, or a real
     * warning would disappear the moment the user searched for something unrelated.
     *
     * @test
     */
    public function an_existing_cleared_notice_is_preserved_through_a_search_selection(): void
    {
        $host = $this->host();
        $host->geoCleared = [['tier' => 'cities', 'label' => 'Somewhere, FL']];

        $host->geoSearchTerm = 'Clearwater';
        $host->updatedGeoSearchTerm();
        $host->selectGeographyMatch(GeographyOption::KIND_CITY, '1212925');

        $this->assertSame(
            [['tier' => 'cities', 'label' => 'Somewhere, FL']],
            $host->geoCleared,
            'a pre-existing notice is not the search’s to discard'
        );
    }

    /**
     * SAME-STATE BACK-FILL RAISES NO NOTICE AT ALL — neither the cleared warning nor the
     * location-change one.
     *
     * Adding Tampa to a selection already inside Florida changes nothing about the user's context,
     * so a notice would be noise that trains them to ignore the one that matters.
     *
     * @test
     */
    public function a_same_state_search_backfill_raises_no_notice(): void
    {
        $host = $this->host();
        $host->geoStateId = '12';
        $host->updatedGeoStateId();
        $host->geoCountyIds = ['12103'];
        $host->updatedGeoCountyIds();
        $host->geoCleared = [];

        $host->geoSearchTerm = 'Tampa';
        $host->updatedGeoSearchTerm();
        $host->selectGeographyMatch(GeographyOption::KIND_CITY, '1271000');

        $this->assertSame([], $host->geoCleared, 'no cleared warning for same-state back-fill');
        $this->assertSame('', $host->geoStateChangedTo, 'the state did not move, so nothing to announce');
        $this->assertSame('12', $host->geoStateId);
    }

    /**
     * CROSS-STATE SELECTION ANNOUNCES THE MOVE.
     *
     * Losing an entire state's context is the largest silent change search can make. The generic
     * cleared warning is still suppressed — it would enumerate every dropped county as though
     * something had failed — and is replaced by one accurate statement of what happened.
     *
     * @test
     */
    public function a_cross_state_search_selection_creates_a_location_change_notice(): void
    {
        $host = $this->host();
        $host->geoStateId = '17';
        $host->updatedGeoStateId();
        $host->geoCountyIds = ['17167'];
        $host->updatedGeoCountyIds();
        $host->geoCleared = [];

        $host->geoSearchTerm = 'Clearwater, FL';
        $host->updatedGeoSearchTerm();
        $host->selectGeographyMatch(GeographyOption::KIND_CITY, '1212925');

        $this->assertSame('Florida', $host->geoStateChangedTo, 'the move must be announced by name');
        $this->assertSame([], $host->geoCleared, 'the generic warning stays suppressed');
        $this->assertSame('12', $host->geoStateId);
        $this->assertSame(['12103'], $host->geoCountyIds, 'the Illinois county is gone, as chosen');
    }

    /** Choosing the FIRST state is not a change of context — there was none to lose. */
    /** @test */
    public function selecting_a_state_from_an_empty_selection_is_not_announced(): void
    {
        $host = $this->host();

        $host->geoSearchTerm = 'Clearwater';
        $host->updatedGeoSearchTerm();
        $host->selectGeographyMatch(GeographyOption::KIND_CITY, '1212925');

        $this->assertSame('12', $host->geoStateId);
        $this->assertSame('', $host->geoStateChangedTo, 'a first selection is not a move');
    }

    /** A later same-state selection must not leave the previous move's notice on screen. */
    /** @test */
    public function the_location_change_notice_does_not_persist_into_the_next_selection(): void
    {
        $host = $this->host();
        $host->geoStateId = '17';
        $host->updatedGeoStateId();

        $host->geoSearchTerm = 'Clearwater, FL';
        $host->updatedGeoSearchTerm();
        $host->selectGeographyMatch(GeographyOption::KIND_CITY, '1212925');
        $this->assertSame('Florida', $host->geoStateChangedTo);

        $host->geoSearchTerm = 'Tampa';
        $host->updatedGeoSearchTerm();
        $host->selectGeographyMatch(GeographyOption::KIND_CITY, '1271000');

        $this->assertSame('', $host->geoStateChangedTo, 'a stale notice would misdescribe the last action');
    }

    /** @test */
    public function the_location_change_notice_can_be_dismissed(): void
    {
        $host = $this->host();
        $host->geoStateId = '17';
        $host->updatedGeoStateId();

        $host->geoSearchTerm = 'Clearwater, FL';
        $host->updatedGeoSearchTerm();
        $host->selectGeographyMatch(GeographyOption::KIND_CITY, '1212925');

        $host->dismissGeographyStateChange();

        $this->assertSame('', $host->geoStateChangedTo);
    }

    /** The view renders the notice, and phrases it as the requirement specifies. */
    /** @test */
    public function the_search_partial_renders_the_location_change_notice(): void
    {
        $partial = (string) file_get_contents(
            base_path('resources/views/partials/location-dna/geography-search.blade.php')
        );

        $this->assertStringContainsString("@if ((\$geoStateChangedTo ?? '') !== '')", $partial);
        $this->assertStringContainsString('Location updated to {{ $geoStateChangedTo }}.', $partial);
        $this->assertStringContainsString('wire:click="dismissGeographyStateChange"', $partial);
    }

    /**
     * MANUAL CHANGES KEEP THEIR CLEARING BEHAVIOUR — the suppression is scoped to back-fill only.
     *
     * @test
     */
    public function a_manual_dropdown_change_still_reports_clearing(): void
    {
        $host = $this->host();
        $host->geoStateId = '12';
        $host->updatedGeoStateId();
        $host->geoCountyIds = ['12103'];
        $host->updatedGeoCountyIds();
        $host->geoCityIds = ['1212925'];
        $host->updatedGeoCityIds();
        $host->geoCleared = [];

        // Manually dropping the county orphans the city — the user should be told.
        $host->geoCountyIds = [];
        $host->updatedGeoCountyIds();

        $this->assertNotEmpty($host->geoCleared, 'manual clearing must still be reported');
        $this->assertSame([], $host->geoCityIds);
    }

    // ═════════════════════════════════════════════════════════════════════
    // 4 · SEARCH BEHAVIOUR
    // ═════════════════════════════════════════════════════════════════════

    /** @test */
    public function typing_populates_results_with_breadcrumbs(): void
    {
        $host = $this->host();
        $host->geoSearchTerm = 'Clearwater';
        $host->updatedGeoSearchTerm();

        $this->assertNotEmpty($host->geoSearchResults);
        $this->assertTrue($host->geoSearchPerformed);

        $city = collect($host->geoSearchResults)->firstWhere('kind', GeographyOption::KIND_CITY);

        $this->assertNotNull($city);
        $this->assertSame('Clearwater', $city['label']);
        $this->assertSame('Pinellas County, FL', $city['breadcrumb']);
    }

    /** @test */
    public function a_term_below_the_floor_returns_nothing_and_is_not_reported_as_a_miss(): void
    {
        $host = $this->host();
        $host->geoSearchTerm = 'c';
        $host->updatedGeoSearchTerm();

        $this->assertSame([], $host->geoSearchResults);
        $this->assertFalse($host->geoSearchPerformed, '"too short" must not read as "no matches"');
    }

    /** @test */
    public function selecting_a_result_clears_the_search_box(): void
    {
        $host = $this->host();
        $host->geoSearchTerm = 'Clearwater';
        $host->updatedGeoSearchTerm();
        $host->selectGeographyMatch(GeographyOption::KIND_CITY, '1212925');

        $this->assertSame('', $host->geoSearchTerm);
        $this->assertSame([], $host->geoSearchResults);
    }

    /**
     * A stale click — the list moved on before the request landed — is a no-op, not a back-fill of
     * something the user cannot see.
     *
     * @test
     */
    public function selecting_a_result_that_is_not_in_the_current_list_does_nothing(): void
    {
        $host = $this->host();
        $host->geoSearchTerm = 'Clearwater';
        $host->updatedGeoSearchTerm();

        $host->selectGeographyMatch(GeographyOption::KIND_CITY, '9999999');

        $this->assertSame('', $host->geoStateId);
        $this->assertSame([], $host->geoCountyIds);
    }

    /**
     * A TYPED STATE SUFFIX OVERRIDES THE CURRENT SELECTION.
     *
     * The repository resolves an explicit scope ahead of a typed suffix, so it falls to the caller
     * not to assert a scope the user has just contradicted. Without that, someone with Illinois
     * selected who types "Clearwater, FL" gets an empty list and no explanation.
     *
     * @test
     */
    public function a_typed_state_suffix_overrides_an_existing_state_selection(): void
    {
        $host = $this->host();
        $host->geoStateId = '17';
        $host->updatedGeoStateId();

        $host->geoSearchTerm = 'Clearwater, FL';
        $host->updatedGeoSearchTerm();

        $city = collect($host->geoSearchResults)->firstWhere('kind', GeographyOption::KIND_CITY);

        $this->assertNotNull($city, 'a typed suffix must be able to leave the selected state');
        $this->assertSame('1212925', $city['id']);
    }

    /** An already-selected state narrows the search, so the local Springfield is not buried. */
    /** @test */
    public function an_existing_state_selection_scopes_the_search(): void
    {
        $host = $this->host();
        $host->geoStateId = '12';
        $host->updatedGeoStateId();

        $host->geoSearchTerm = 'Pinellas';
        $host->updatedGeoSearchTerm();

        foreach ($host->geoSearchResults as $result) {
            if ($result['kind'] === GeographyOption::KIND_COUNTY) {
                $this->assertSame('12103', $result['id']);
            }
        }
    }

    // ═════════════════════════════════════════════════════════════════════
    // 5 · GATES
    // ═════════════════════════════════════════════════════════════════════

    /** @test */
    public function the_search_flag_ships_disabled(): void
    {
        $config = require base_path('config/criteria_location_dna.php');

        $this->assertFalse($config['geography_search_enabled'], 'M2 must ship off.');
    }

    /** @test */
    public function search_is_inert_when_its_flag_is_off(): void
    {
        config(['criteria_location_dna.geography_search_enabled' => false]);

        $host = $this->host();

        $this->assertFalse($host->geoSearchEnabled);

        $host->geoSearchTerm = 'Clearwater';
        $host->updatedGeoSearchTerm();

        $this->assertSame([], $host->geoSearchResults);

        $host->selectGeographyMatch(GeographyOption::KIND_CITY, '1212925');
        $this->assertSame('', $host->geoStateId, 'a disabled surface must not back-fill');
    }

    /**
     * Search cannot outlive the cascade: where the tiers do not render there is nothing to seed.
     * This is also what keeps Seller and Landlord excluded without a second rule to maintain.
     *
     * @test
     */
    public function search_is_off_wherever_the_cascade_is_off(): void
    {
        config(['criteria_location_dna.geography_cascade_enabled' => false]);

        $host = $this->host();

        $this->assertFalse($host->geoCascadeEnabled);
        $this->assertFalse($host->geoSearchEnabled);
    }

    /** @test */
    public function neighborhoods_are_never_requested_by_the_search_surface(): void
    {
        $host  = $this->host();
        $query = $this->invoke($host, 'geographySearchQuery');

        $this->assertFalse(
            $query->wantsTier(\App\Services\LocationDna\Criteria\Rules\GeographyTier::Neighborhoods),
            'M2 does not search neighbourhoods; the tier ships off and is not asked for.'
        );
    }

    // ═════════════════════════════════════════════════════════════════════
    // 6 · WIRING AND ISOLATION
    // ═════════════════════════════════════════════════════════════════════

    /** Both Hire Buyer surfaces carry the trait and boot it AFTER the cascade. */
    /** @test */
    public function hire_buyer_components_are_wired_for_search(): void
    {
        foreach ([
            'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php',
            'app/Http/Livewire/HireBuyerAgent/BuyerAgentAuctionEdit.php',
        ] as $relative) {
            $source = (string) file_get_contents(base_path($relative));

            $this->assertStringContainsString('HasGeographySearch', $source, "{$relative} must carry the trait");

            $cascade = strpos($source, 'bootGeographyCascade(');
            $search  = strpos($source, 'bootGeographySearch(');

            $this->assertNotFalse($search, "{$relative} must boot search");
            $this->assertLessThan(
                $search,
                $cascade,
                "{$relative}: search boots AFTER the cascade, whose flag it reads."
            );
        }
    }

    /**
     * THE CATCH-ALL CARRIES SEARCH TOO, AND THAT IS WHERE THE REAL HIRE WORKFLOW LIVES.
     *
     * This assertion used to say the opposite. It was written when M2 was believed to be Hire
     * Buyer only, and it was wrong about which component Hire Buyer actually uses:
     * `HireBuyerAgent\BuyerAgentAuction` serves `buyer/add-auction`, while
     * `hire/agent/auction/{user_type?}` and BOTH edit routes resolve to `TenantAgentAuction{,Edit}`.
     * A Buyer could therefore create a listing with the search box and edit it without one.
     *
     * Wiring the catch-all closes that gap. It changes nothing for any role whose workflow map
     * returns null — which is still every role but Buyer — because the search gate reads
     * `$geoCascadeEnabled`.
     *
     * @test
     */
    public function the_catch_all_components_are_wired_for_search(): void
    {
        foreach ([
            'app/Http/Livewire/TenantAgentAuction.php',
            'app/Http/Livewire/TenantAgentAuctionEdit.php',
        ] as $relative) {
            $source = (string) file_get_contents(base_path($relative));

            $this->assertStringContainsString('HasGeographySearch', $source, "{$relative} must carry the trait");

            $cascade = strpos($source, 'bootGeographyCascade(');
            $search  = strpos($source, 'bootGeographySearch(');

            $this->assertNotFalse($search, "{$relative} must boot search");
            $this->assertLessThan(
                $search,
                $cascade,
                "{$relative}: search boots AFTER the cascade, whose flag it reads."
            );
        }
    }

    /**
     * CLAIMING A WORKFLOW KEY IS NOT ENABLING THE ROLE.
     *
     * The map now names `hire_tenant` alongside `hire_buyer` — step 4 of the Tenant rollout. That
     * is still not enablement: the scope list is consulted only after a non-null key exists, and
     * it names `hire_buyer` alone, so a tenant resolves to a workflow that is out of scope and
     * both the cascade and the search gate above it stay off.
     *
     * Seller and Landlord remain absent from the map entirely, which is the stronger guarantee —
     * no value of `CRITERIA_LDNA_CASCADE_WORKFLOWS` can reach a role that has no key.
     *
     * @test
     */
    public function the_catch_all_map_claims_buyer_and_tenant_and_no_one_else(): void
    {
        foreach ([
            'app/Http/Livewire/TenantAgentAuction.php',
            'app/Http/Livewire/TenantAgentAuctionEdit.php',
        ] as $relative) {
            $source = (string) file_get_contents(base_path($relative));
            $start  = strpos($source, 'protected function geographyCascadeWorkflow(): ?string');
            $body   = substr($source, (int) $start, (int) strpos($source, "\n    }", (int) $start) - (int) $start);

            // Whitespace-tolerant: the arms are `=>`-aligned, and alignment shifts whenever an
            // arm is added. A literal-with-one-space match would fail on formatting alone.
            $this->assertMatchesRegularExpression("/'buyer'\s*=>\s*'hire_buyer',/", $body, $relative);
            $this->assertMatchesRegularExpression("/'tenant'\s*=>\s*'hire_tenant',/", $body, $relative);
            $this->assertMatchesRegularExpression('/default\s*=>\s*null,/', $body, $relative);

            $this->assertStringNotContainsString("'seller' =>", $body);
            $this->assertStringNotContainsString("'landlord' =>", $body);
        }

        // The gate that keeps the claim inert. This is the line that moves in step 5.
        //
        // It moved for `create_buyer`, which is a different workflow family from the catch-all map
        // asserted above: the map here belongs to the HIRE components and still claims only
        // `hire_buyer` and `hire_tenant`. Create Buyer's key comes from the Offer components' own
        // map, so nothing above is weakened by this entry.
        $config = require base_path('config/criteria_location_dna.php');
        $this->assertSame(['hire_buyer', 'create_buyer'], $config['geography_cascade_workflows']);
    }

    /**
     * Seller, Landlord and the still-unwired Offer components remain untouched by the search
     * rollout.
     *
     * TWO DIFFERENT CLAIMS LIVE IN THIS LIST, AND ONLY ONE OF THEM IS PERMANENT
     * -------------------------------------------------------------------------
     * For Seller and Landlord it is a STRUCTURAL guarantee: those workflows have no geography
     * surface, so search must never reach them and these four entries never leave this list.
     *
     * For the Offer components it was only ever a statement of ROLLOUT SCOPE at the time search
     * shipped. `BuyerOfferListing` has since been wired deliberately — it carries both traits, its
     * tab renders the cascade, and `create_buyer` is in the scope list — so asserting its absence
     * would now be asserting that an approved slice had not happened.
     *
     * `TenantOfferListing` stays listed, and that is the entry doing real work: Create Tenant is
     * NOT wired, because its legacy `zipCodes` meta never reaches the Location DNA blob on its load
     * path. Until that normalization lands, a cascade there would hydrate empty and overwrite a
     * populated field. This line is what keeps that from being wired by accident.
     */
    /** @test */
    public function no_seller_or_landlord_component_carries_the_search_trait(): void
    {
        foreach ([
            'app/Http/Livewire/HireSellerAgent/SellerAgentAuction.php',
            'app/Http/Livewire/HireSellerAgent/SellerAgentAuctionEdit.php',
            'app/Http/Livewire/HireLandLordAgent/LandLordAgentAuction.php',
            'app/Http/Livewire/HireLandLordAgent/LandLordAgentAuctionEdit.php',
            // The Tenant Offer components were listed here while Create Tenant was unwired. T2
            // wired them deliberately — they carry both traits, resolve `create_tenant` for the
            // tenant role only, and stay gated by config — so asserting their absence would now
            // assert that an approved step had not happened. Their exclusion rules are asserted
            // where they belong, in CreateTenantGeographyWiringTest.
            //
            // WHAT REMAINS IS THE PERMANENT CLAIM. Seller and Landlord have no geography surface at
            // all, so search must never reach them, and these four entries never leave this list.
            'app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php',
            'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php',
        ] as $relative) {
            if (! file_exists(base_path($relative))) {
                continue;
            }

            $this->assertStringNotContainsString(
                'HasGeographySearch',
                (string) file_get_contents(base_path($relative)),
                "{$relative}: the search rollout does not reach this surface."
            );
        }
    }

    /** @test */
    public function the_cascade_trait_is_not_modified_by_m2(): void
    {
        $trait = (string) file_get_contents(base_path('app/Http/Livewire/Concerns/HasGeographyCascade.php'));

        $this->assertStringNotContainsString('geoSearch', $trait, 'HasGeographyCascade must know nothing of search.');
        $this->assertStringNotContainsString('HasGeographySearch', $trait);
    }

    /** @test */
    public function the_search_surface_contains_no_google_reference(): void
    {
        foreach ([
            'resources/views/partials/location-dna/geography-search.blade.php',
            'app/Http/Livewire/Concerns/HasGeographySearch.php',
        ] as $relative) {
            $source = (string) file_get_contents(base_path($relative));

            foreach (['google', 'maps.googleapis', 'Autocomplete'] as $marker) {
                $this->assertStringNotContainsString(
                    $marker,
                    $source,
                    "{$relative} must carry no Google dependency."
                );
            }
        }
    }
}
