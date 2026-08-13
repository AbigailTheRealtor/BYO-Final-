<?php

namespace Tests\Feature\LocationDna;

use App\Http\Livewire\Concerns\HasGeographyCascade;
use App\Models\LocationPlace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 1d-5, slice 5 — the neighbourhood tier as the Livewire host sees it.
 *
 * THE TWO QUESTIONS THIS SUITE ANSWERS
 * ------------------------------------
 *   1. WITH THE FLAG OFF, does the host behave exactly as it did before the tier existed? This is
 *      the one that matters for merging: the flag ships false, so this is what every environment
 *      runs. "Exactly" means the projected blob is byte-identical, the tier property stays empty,
 *      and no enumeration happens.
 *   2. WITH THE FLAG ON, does a selected neighbourhood reach the stored `cities` array without
 *      adding a key, duplicating a city, or disturbing the other three tiers?
 *
 * The host is an anonymous class using the trait, mirroring
 * {@see GeographyCascadeStateResolutionTest}. Mounting a full wizard would drag in the whole bid
 * form and test almost none of it; the trait IS the surface under test.
 */
class NeighborhoodTierSurfaceTest extends TestCase
{
    use DatabaseTransactions;

    private const FL         = '12';
    private const PINELLAS   = '12103';
    private const CLEARWATER = '1212875';

    protected function setUp(): void
    {
        parent::setUp();

        // The cascade is exercised against the census corpus, which is the only source the
        // neighbourhood tier can join to.
        config()->set('criteria_location_dna.geography_source', 'census');
        config()->set('criteria_location_dna.geography_cascade_enabled', true);
        config()->set('criteria_location_dna.geography_cascade_workflows', ['hire_buyer']);

        $this->seedCorpus();
    }

    private function seedCorpus(): void
    {
        DB::table('census_states')->insert([['geoid' => self::FL, 'usps' => 'FL', 'name' => 'Florida']]);

        DB::table('census_counties')->insert([
            ['geoid' => self::PINELLAS, 'state_geoid' => self::FL, 'countyfp' => '103', 'name' => 'Pinellas County', 'basename' => 'Pinellas'],
        ]);

        DB::table('census_places')->insert([
            ['geoid' => self::CLEARWATER, 'state_geoid' => self::FL, 'placefp' => '12875', 'name' => 'Clearwater', 'namelsad' => 'Clearwater city', 'classfp' => 'C1'],
        ]);

        DB::table('census_place_counties')->insert([
            ['place_geoid' => self::CLEARWATER, 'county_geoid' => self::PINELLAS],
        ]);

        DB::table('census_zctas')->insert([['zcta5' => '33767']]);
        DB::table('census_zcta_counties')->insert([['zcta5' => '33767', 'county_geoid' => self::PINELLAS]]);

        $clearwater = LocationPlace::create([
            'name' => 'Clearwater', 'type' => LocationPlace::TYPE_CITY, 'state_geoid' => self::FL,
            'county_geoid' => self::PINELLAS, 'census_place_geoid' => self::CLEARWATER,
            'source' => LocationPlace::SOURCE_CENSUS, 'active' => true,
        ]);

        foreach (['Clearwater Beach', 'Sand Key'] as $name) {
            LocationPlace::create([
                'name' => $name, 'type' => LocationPlace::TYPE_NEIGHBORHOOD, 'state_geoid' => self::FL,
                'county_geoid' => self::PINELLAS, 'parent_place_id' => $clearwater->id,
                'source' => LocationPlace::SOURCE_CURATED, 'active' => true,
            ]);
        }
    }

    /** A host carrying the trait, booted for the Hire Buyer workflow. */
    private function host(bool $tierEnabled): object
    {
        config()->set('criteria_location_dna.neighborhood_tier_enabled', $tierEnabled);

        $host = new class {
            use HasGeographyCascade;

            public $location_dna_preferences_json = '';

            public function boot(): void
            {
                $this->bootGeographyCascade('hire_buyer');
            }

            public function load(array $blob): void
            {
                $this->loadGeographyCascade($blob);
            }

            public function refresh(): void
            {
                $this->refreshGeographyCascade();
            }

            public function projection(): array
            {
                return $this->geographyProjection();
            }

            public function applyToPayload(): void
            {
                $this->applyGeographyCascadeToPayload();
            }

            public function neighborhoodOptions(): array
            {
                return $this->geoNeighborhoodOptions();
            }
        };

        $host->boot();

        return $host;
    }

    private function neighborhoodId(string $name): string
    {
        return (string) LocationPlace::where('name', $name)->value('id');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // The flag OFF — what every environment runs today
    // ═════════════════════════════════════════════════════════════════════════

    /** @test */
    public function with_the_flag_off_the_tier_is_not_enabled_even_though_the_cascade_is(): void
    {
        $host = $this->host(false);

        $this->assertTrue($host->geoCascadeEnabled, 'The cascade itself must still be on.');
        $this->assertFalse($host->geoNeighborhoodTierEnabled);
    }

    /** @test */
    public function with_the_flag_off_no_neighborhood_options_are_enumerated(): void
    {
        $host = $this->host(false);
        $host->geoStateId  = self::FL;
        $host->geoCountyIds = [self::PINELLAS];
        $host->geoCityIds   = [self::CLEARWATER];
        $host->refresh();

        $this->assertSame([], $host->neighborhoodOptions());
        $this->assertSame([], $host->geoNeighborhoodIds);
    }

    /** @test */
    public function with_the_flag_off_a_stored_neighbourhood_label_stays_preserved(): void
    {
        $host = $this->host(false);
        $host->load([
            'state'    => 'Florida',
            'counties' => ['Pinellas County, FL'],
            'cities'   => ['Clearwater, FL', 'Clearwater Beach, FL'],
        ]);

        $this->assertSame([], $host->geoNeighborhoodIds);
        $this->assertSame(['Clearwater Beach, FL'], $host->geoPreserved['cities']);
        $this->assertSame(['Clearwater, FL', 'Clearwater Beach, FL'], $host->projection()['cities']);
    }

    /**
     * @test
     *
     * The merge guarantee: with the flag off, the projected blob must be identical to what a build
     * from before this tier existed would produce.
     */
    public function with_the_flag_off_the_projection_is_identical_to_the_flag_on_case_when_nothing_is_selected(): void
    {
        $blob = [
            'state'     => 'Florida',
            'counties'  => ['Pinellas County, FL'],
            'cities'    => ['Clearwater, FL'],
            'zip_codes' => ['33767'],
        ];

        $off = $this->host(false);
        $off->load($blob);

        $on = $this->host(true);
        $on->load($blob);

        $this->assertSame($off->projection(), $on->projection());
        $this->assertSame($blob, $off->projection());
    }

    // ═════════════════════════════════════════════════════════════════════════
    // The flag ON
    // ═════════════════════════════════════════════════════════════════════════

    /** @test */
    public function with_the_flag_on_neighborhoods_of_the_selected_city_are_offered(): void
    {
        $host = $this->host(true);
        $host->geoStateId   = self::FL;
        $host->geoCountyIds = [self::PINELLAS];
        $host->geoCityIds   = [self::CLEARWATER];
        $host->refresh();

        $this->assertSame(
            ['Clearwater Beach', 'Sand Key'],
            array_column($host->neighborhoodOptions(), 'name')
        );
    }

    /** @test */
    public function no_neighborhoods_are_offered_until_a_city_is_selected(): void
    {
        $host = $this->host(true);
        $host->geoStateId   = self::FL;
        $host->geoCountyIds = [self::PINELLAS];
        $host->refresh();

        $this->assertSame([], $host->neighborhoodOptions(), 'The tier hangs off cities, not counties.');
    }

    /** @test */
    public function a_selected_neighborhood_projects_into_the_cities_key_with_no_fifth_key(): void
    {
        $host = $this->host(true);
        $host->geoStateId          = self::FL;
        $host->geoCountyIds        = [self::PINELLAS];
        $host->geoCityIds          = [self::CLEARWATER];
        $host->geoNeighborhoodIds  = [$this->neighborhoodId('Clearwater Beach')];
        $host->refresh();

        $projection = $host->projection();

        $this->assertSame(['Clearwater, FL', 'Clearwater Beach, FL'], $projection['cities']);
        $this->assertSame(['state', 'counties', 'cities', 'zip_codes'], array_keys($projection));
        $this->assertArrayNotHasKey('neighborhoods', $projection);
    }

    /** @test */
    public function the_written_payload_carries_no_neighborhoods_key(): void
    {
        $host = $this->host(true);
        $host->geoStateId         = self::FL;
        $host->geoCountyIds       = [self::PINELLAS];
        $host->geoCityIds         = [self::CLEARWATER];
        $host->geoNeighborhoodIds = [$this->neighborhoodId('Clearwater Beach')];
        $host->refresh();
        $host->applyToPayload();

        $written = json_decode($host->location_dna_preferences_json, true);

        $this->assertArrayNotHasKey('neighborhoods', $written);
        $this->assertContains('Clearwater Beach, FL', $written['cities']);
    }

    /** @test */
    public function a_stored_neighbourhood_label_is_recognised_and_re_emitted_unchanged(): void
    {
        $blob = [
            'state'     => 'Florida',
            'counties'  => ['Pinellas County, FL'],
            'cities'    => ['Clearwater, FL', 'Clearwater Beach, FL'],
            'zip_codes' => [],
        ];

        $host = $this->host(true);
        $host->load($blob);

        $this->assertSame([$this->neighborhoodId('Clearwater Beach')], $host->geoNeighborhoodIds);
        $this->assertSame([], $host->geoPreserved['cities'], 'It was recognised, so it is no longer history.');
        $this->assertSame($blob, $host->projection(), 'And it round-trips byte for byte.');
    }

    /** @test */
    public function deselecting_the_city_clears_its_neighborhoods(): void
    {
        $host = $this->host(true);
        $host->geoStateId         = self::FL;
        $host->geoCountyIds       = [self::PINELLAS];
        $host->geoCityIds         = [self::CLEARWATER];
        $host->geoNeighborhoodIds = [$this->neighborhoodId('Clearwater Beach')];
        $host->refresh();

        $host->geoCityIds = [];
        $host->refresh();

        $this->assertSame([], $host->geoNeighborhoodIds);
        $this->assertNotSame([], $host->geoCleared, 'The user must be told something was cleared.');
    }

    /** @test */
    public function the_other_three_tiers_are_unaffected_by_a_neighborhood_selection(): void
    {
        $host = $this->host(true);
        $host->geoStateId         = self::FL;
        $host->geoCountyIds       = [self::PINELLAS];
        $host->geoCityIds         = [self::CLEARWATER];
        $host->geoZipCodes        = ['33767'];
        $host->geoNeighborhoodIds = [$this->neighborhoodId('Sand Key')];
        $host->refresh();

        $projection = $host->projection();

        $this->assertSame('Florida', $projection['state']);
        $this->assertSame(['Pinellas County, FL'], $projection['counties']);
        $this->assertSame(['33767'], $projection['zip_codes']);
    }

    /** @test */
    public function no_duplicate_city_label_is_produced(): void
    {
        $host = $this->host(true);
        $host->geoStateId         = self::FL;
        $host->geoCountyIds       = [self::PINELLAS];
        $host->geoCityIds         = [self::CLEARWATER];
        $host->geoNeighborhoodIds = [
            $this->neighborhoodId('Clearwater Beach'),
            $this->neighborhoodId('Sand Key'),
        ];
        $host->refresh();

        $cities = $host->projection()['cities'];

        $this->assertSame(array_values(array_unique($cities)), $cities);
        $this->assertSame(['Clearwater, FL', 'Clearwater Beach, FL', 'Sand Key, FL'], $cities);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // The gate cannot be widened by the tier flag alone
    // ═════════════════════════════════════════════════════════════════════════

    /** @test */
    public function a_host_with_no_workflow_never_enables_the_tier(): void
    {
        // Seller and landlord map to a NULL workflow in the shared catch-all component.
        config()->set('criteria_location_dna.neighborhood_tier_enabled', true);

        $host = new class {
            use HasGeographyCascade;

            public function bootAs(?string $workflow): void
            {
                $this->bootGeographyCascade($workflow);
            }
        };

        $host->bootAs(null);

        $this->assertFalse($host->geoCascadeEnabled);
        $this->assertFalse($host->geoNeighborhoodTierEnabled, 'No workflow means no tier, whatever the flag says.');
    }

    /** @test */
    public function a_workflow_outside_the_scope_list_never_enables_the_tier(): void
    {
        config()->set('criteria_location_dna.neighborhood_tier_enabled', true);

        $host = new class {
            use HasGeographyCascade;

            public function bootAs(?string $workflow): void
            {
                $this->bootGeographyCascade($workflow);
            }
        };

        $host->bootAs('hire_tenant');   // not in geography_cascade_workflows

        $this->assertFalse($host->geoCascadeEnabled);
        $this->assertFalse($host->geoNeighborhoodTierEnabled);
    }
}
