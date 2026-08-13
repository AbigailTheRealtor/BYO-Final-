<?php

namespace Tests\Feature\LocationDna;

use App\Services\LocationDna\Criteria\CensusCriteriaGeographyRepository;
use App\Services\LocationDna\Criteria\CriteriaGeographyRepository;
use App\Services\LocationDna\Criteria\EloquentCriteriaGeographyRepository;
use App\Services\LocationDna\Criteria\FakeCriteriaGeographyRepository;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Phase 1d-2 — `criteria_location_dna.geography_source` resolves to exactly one implementation.
 *
 * THE FAILURE THIS FILE EXISTS TO PREVENT
 * ---------------------------------------
 * The binding was a ternary: `=== 'fake' ? Fake : Eloquent`. Every value that was not `'fake'`
 * returned the reference implementation, which means a typo, an unset environment variable in a
 * container that never received the config, or a value written before its class existed all
 * produced the SAME outcome — legacy `us_*` data served without a word.
 *
 * That is the worst available failure mode for a source swap. The whole point of turning the
 * Census source on is to verify the Census source is on, and a silent fallback makes the
 * observable behaviour of "it worked" and "it did nothing" identical. So `eloquent` is now an
 * explicit arm rather than the fall-through, and an unrecognised value throws.
 *
 * NO DATABASE CHECK BELONGS IN THE BINDING, and none is asserted here. Whether the corpus is
 * actually populated is a different question with a different answer — `census:verify-geography`
 * — because a container binding that queried a table would couple service resolution to database
 * availability on every single request.
 */
class GeographySourceBindingTest extends TestCase
{
    private function resolveWith(?string $source): object
    {
        config(['criteria_location_dna.geography_source' => $source]);

        // Forget any previously resolved instance so the closure runs against the new config.
        $this->app->forgetInstance(CriteriaGeographyRepository::class);

        return $this->app->make(CriteriaGeographyRepository::class);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Each recognised value resolves to its own implementation
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function eloquent_resolves_to_the_reference_repository(): void
    {
        $this->assertInstanceOf(
            EloquentCriteriaGeographyRepository::class,
            $this->resolveWith('eloquent')
        );
    }

    /** @test */
    public function census_resolves_to_the_census_repository(): void
    {
        $this->assertInstanceOf(
            CensusCriteriaGeographyRepository::class,
            $this->resolveWith('census')
        );
    }

    /** @test */
    public function fake_resolves_to_the_in_memory_repository(): void
    {
        $this->assertInstanceOf(
            FakeCriteriaGeographyRepository::class,
            $this->resolveWith('fake')
        );
    }

    /** Every arm satisfies the interface — the thing consumers actually depend on. */
    /** @test */
    public function every_recognised_source_satisfies_the_interface(): void
    {
        foreach (['eloquent', 'census', 'fake'] as $source) {
            $this->assertInstanceOf(
                CriteriaGeographyRepository::class,
                $this->resolveWith($source),
                "`{$source}` must resolve to something the consumers can use."
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Anything else is loud
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function an_unrecognised_source_throws_rather_than_falling_back(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->resolveWith('postgis');
    }

    /**
     * The near-miss cases, which are the ones that actually happen.
     *
     * A truncated value, a capitalised one, an empty string from an env var that was declared but
     * never set. Under the ternary every one of these silently served `us_*` data.
     */
    /** @test */
    public function a_near_miss_value_throws_rather_than_serving_legacy_data(): void
    {
        foreach (['censu', 'Census', 'CENSUS', 'eloquent ', '', 'null', 'true'] as $source) {
            try {
                $this->resolveWith($source);
                $this->fail("`{$source}` resolved silently; it must throw instead of falling back.");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString(
                    'geography_source',
                    $e->getMessage(),
                    'The message should name the setting so the fix is obvious.'
                );
            }
        }
    }

    /** A missing key is not a licence to guess either. */
    /** @test */
    public function an_absent_source_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->resolveWith(null);
    }

    // ─────────────────────────────────────────────────────────────────────
    // The default has not moved
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Phase 1d-2 adds a source; it does not turn one on.
     *
     * Read from the config file directly rather than from the live value, so a test that has
     * already overridden the runtime config cannot make this pass by accident.
     */
    /** @test */
    public function the_shipped_default_is_still_eloquent(): void
    {
        $config = require config_path('criteria_location_dna.php');

        $this->assertSame(
            'eloquent',
            $config['geography_source'],
            'Enabling the Census source is a rollout decision, not a code change.'
        );
    }
}
