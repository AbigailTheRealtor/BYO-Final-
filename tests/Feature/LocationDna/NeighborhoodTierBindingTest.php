<?php

namespace Tests\Feature\LocationDna;

use App\Services\LocationDna\Criteria\CensusCriteriaNeighborhoodRepository;
use App\Services\LocationDna\Criteria\CriteriaNeighborhoodRepository;
use App\Services\LocationDna\Criteria\FakeCriteriaNeighborhoodRepository;
use App\Services\LocationDna\Criteria\NullCriteriaNeighborhoodRepository;
use Tests\TestCase;

/**
 * Phase 1d-5, slice 2 — what the container hands back, under every combination that matters.
 *
 * THE GATE IS THE FEATURE
 * -----------------------
 * Two conditions must both hold before anything but the null object resolves: the tier flag is on
 * AND the geography source is one that can answer. Neither is a formality:
 *
 *   - The FLAG is what makes this slice mergeable while the surface does not exist yet.
 *   - The SOURCE check is a correctness guard. The tier joins a city option's id to
 *     `location_places.census_place_geoid`, which are the same string under `census` and unrelated
 *     under `eloquent`. An operator who turns the flag on without switching the source must get
 *     NOTHING, not neighbourhoods attached to whichever place shares a number with a `us_cities`
 *     surrogate key.
 *
 * AND IT NEVER THROWS, unlike the geography binding beside it. That difference is asserted here
 * rather than left to a reader to notice: an empty tier is the pre-Phase-1d-5 behaviour of every
 * environment, so failing to it is safe, where the geography binding's alternatives are all
 * real-but-wrong data.
 */
class NeighborhoodTierBindingTest extends TestCase
{
    private function bindingFor(?bool $tierEnabled, ?string $source): CriteriaNeighborhoodRepository
    {
        config()->set('criteria_location_dna.neighborhood_tier_enabled', $tierEnabled);
        config()->set('criteria_location_dna.geography_source', $source);

        $this->app->forgetInstance(CriteriaNeighborhoodRepository::class);

        return $this->app->make(CriteriaNeighborhoodRepository::class);
    }

    // ─────────────────────────────────────────────────────────────────────
    // The flag
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function the_tier_ships_disabled(): void
    {
        $config = require base_path('config/criteria_location_dna.php');

        $this->assertFalse(
            $config['neighborhood_tier_enabled'],
            'The neighborhood tier must ship off — no surface renders it yet.'
        );
    }

    /** @test */
    public function the_flag_off_resolves_to_the_null_object_under_every_source(): void
    {
        foreach (['eloquent', 'census', 'fake'] as $source) {
            $this->assertInstanceOf(
                NullCriteriaNeighborhoodRepository::class,
                $this->bindingFor(false, $source),
                "source {$source} leaked a live repository while the tier was off"
            );
        }
    }

    /** @test */
    public function the_flag_on_with_the_census_source_resolves_to_the_census_repository(): void
    {
        $this->assertInstanceOf(
            CensusCriteriaNeighborhoodRepository::class,
            $this->bindingFor(true, 'census')
        );
    }

    /** @test */
    public function the_flag_on_with_the_fake_source_resolves_to_the_fake_repository(): void
    {
        $this->assertInstanceOf(
            FakeCriteriaNeighborhoodRepository::class,
            $this->bindingFor(true, 'fake')
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // The source guard
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function the_eloquent_source_yields_no_neighbourhoods_even_with_the_flag_on(): void
    {
        // The join key does not exist under `eloquent`. Empty is the only honest answer.
        $repo = $this->bindingFor(true, 'eloquent');

        $this->assertInstanceOf(NullCriteriaNeighborhoodRepository::class, $repo);
        $this->assertSame([], $repo->neighborhoodsInCities(['1212875']));
    }

    /** @test */
    public function an_unrecognised_source_yields_the_null_object_rather_than_throwing(): void
    {
        // Deliberately unlike the geography binding, which throws. Turning a disabled feature into
        // an outage would be the worse failure — and the geography binding already throws first for
        // any request that reaches a cascade.
        foreach (['CENSUS', 'censuss', '', 'postgis'] as $source) {
            $this->assertInstanceOf(
                NullCriteriaNeighborhoodRepository::class,
                $this->bindingFor(true, $source),
                "source {$source} did not fail safe"
            );
        }
    }

    /** @test */
    public function an_absent_flag_is_treated_as_off(): void
    {
        $this->assertInstanceOf(
            NullCriteriaNeighborhoodRepository::class,
            $this->bindingFor(null, 'census')
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // The contract
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function every_combination_satisfies_the_interface(): void
    {
        foreach ([true, false] as $enabled) {
            foreach (['eloquent', 'census', 'fake', 'nonsense'] as $source) {
                $this->assertInstanceOf(
                    CriteriaNeighborhoodRepository::class,
                    $this->bindingFor($enabled, $source)
                );
            }
        }
    }

    /** @test */
    public function the_null_object_ignores_whatever_it_is_asked(): void
    {
        $repo = new NullCriteriaNeighborhoodRepository();

        $this->assertSame([], $repo->neighborhoodsInCities([]));
        $this->assertSame([], $repo->neighborhoodsInCities(['1212875']));
        $this->assertSame([], $repo->neighborhoodsInCities(['garbage', '', '1263000']));
    }

    /** @test */
    public function the_geography_binding_is_unaffected_by_this_phase(): void
    {
        // Slice 2 must not have disturbed the Phase 1a interface or its three implementations.
        $config = require base_path('config/criteria_location_dna.php');

        $this->assertSame('eloquent', $config['geography_source']);
        $this->assertFalse($config['geography_cascade_enabled']);
        $this->assertSame(['hire_buyer'], $config['geography_cascade_workflows']);
    }
}
