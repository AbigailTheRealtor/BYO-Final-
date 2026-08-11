<?php

namespace Tests\Feature\LocationDna;

use App\Services\LocationDna\Criteria\Search\FakeGeographySearchRepository;
use App\Services\LocationDna\Criteria\Search\GeographyQuery;
use App\Services\LocationDna\Criteria\Search\GeographySearchRepository;
use App\Services\LocationDna\Criteria\Search\LocationPlaceSearchRepository;
use App\Services\LocationDna\Criteria\Search\NullGeographySearchRepository;
use Tests\TestCase;

/**
 * M1 — which implementation answers, and the fact that M1 ships inert.
 *
 * The binding is the gate. Search must be unavailable under the shipped defaults, must be
 * unavailable under `eloquent` for a data reason rather than a policy one, and must agree with the
 * cascade about whether the neighbourhood tier exists.
 */
class GeographySearchBindingTest extends TestCase
{
    private function bound(): GeographySearchRepository
    {
        // The binding reads config at resolution time, so a fresh instance is required after any
        // config change — a cached one would report the previous environment's decision.
        $this->app->forgetInstance(GeographySearchRepository::class);

        return $this->app->make(GeographySearchRepository::class);
    }

    /**
     * THE SHIPPED DEFAULT IS NO SEARCH. `geography_source` defaults to `eloquent`, so an
     * environment that says nothing gets the null object — exactly the behaviour of every
     * environment before M1.
     *
     * @test
     */
    public function the_shipped_default_resolves_to_no_search(): void
    {
        $config = require base_path('config/criteria_location_dna.php');

        $this->assertSame('eloquent', $config['geography_source'], 'the source default must stay eloquent');

        config(['criteria_location_dna.geography_source' => 'eloquent']);

        $this->assertInstanceOf(NullGeographySearchRepository::class, $this->bound());
    }

    /**
     * It FAILS TO EMPTY rather than throwing, and the contrast with the geography-source binding is
     * deliberate: there an unknown source serves real data from the wrong corpus, here the only
     * alternative is no search at all.
     *
     * @test
     */
    public function the_null_implementation_answers_every_query_with_nothing(): void
    {
        config(['criteria_location_dna.geography_source' => 'eloquent']);

        $result = $this->bound()->search(GeographyQuery::for('Clearwater'));

        $this->assertTrue($result->isEmpty());
        $this->assertFalse($result->truncated);
    }

    /**
     * The `census` source resolves to the CANONICAL PLACE LAYER implementation.
     *
     * The names differ on purpose: the config key states the identifier lineage, the class states
     * which table it reads. They are the same decision seen from two sides.
     *
     * @test
     */
    public function the_census_source_resolves_to_the_location_place_implementation(): void
    {
        config(['criteria_location_dna.geography_source' => 'census']);

        $this->assertInstanceOf(LocationPlaceSearchRepository::class, $this->bound());
    }

    /** @test */
    public function the_fake_source_resolves_to_the_fake(): void
    {
        config(['criteria_location_dna.geography_source' => 'fake']);

        $this->assertInstanceOf(FakeGeographySearchRepository::class, $this->bound());
    }

    /**
     * Search must not offer a neighbourhood the cascade has no tier to hold — that would be a match
     * a user can select and nothing can accept. Asserted through behaviour rather than by reading
     * the constructor argument, so a later refactor that keeps the argument and drops the effect
     * still fails here.
     *
     * @test
     */
    public function neighborhood_searchability_follows_the_cascade_tier_flag(): void
    {
        config([
            'criteria_location_dna.geography_source'          => 'census',
            'criteria_location_dna.neighborhood_tier_enabled' => false,
        ]);
        $off = $this->bound();

        config(['criteria_location_dna.neighborhood_tier_enabled' => true]);
        $on = $this->bound();

        $probe = new \ReflectionProperty(LocationPlaceSearchRepository::class, 'neighborhoodsEnabled');
        $probe->setAccessible(true);

        $this->assertFalse($probe->getValue($off), 'the tier is off, so search must not reach it');
        $this->assertTrue($probe->getValue($on));
    }

    /**
     * EXACTLY ONE VIEW CONSUMES THE SEAM, AND IT IS THE M2 SEARCH PARTIAL.
     *
     * This replaces M1's `no_view_consumes_the_search_seam_yet`, which asserted that NOTHING
     * rendered the seam and was written to fail the moment a UI arrived. It has now done its job.
     * The successor keeps the same property under guard rather than dropping it: the surface is
     * still enumerated, so a second view reaching the search layer — a Tenant tab, an Offer tab, a
     * legacy criteria page — fails here instead of quietly widening a Hire-Buyer-only milestone.
     *
     * @test
     */
    public function only_the_search_partial_consumes_the_search_seam(): void
    {
        $hits = [];

        /** @var iterable<\SplFileInfo> $files */
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            if (str_contains($source, 'GeographySearchRepository') || str_contains($source, 'GeographyQuery')) {
                $hits[] = str_replace(resource_path('views').'/', '', $file->getPathname());
            }
        }

        $this->assertSame(
            ['partials/location-dna/geography-search.blade.php'],
            $hits,
            'M2 is Hire Buyer only; a second view reaching the search layer widens it silently.'
        );
    }

    /**
     * The search partial renders only from the cascade, and only behind its own flag.
     *
     * Two independent gates, asserted structurally: the include is wrapped, and the wrapper
     * null-coalesces so a host that never declares the property renders exactly what it rendered
     * before. That default is what keeps the catch-all `TenantAgentAuction` — which also renders
     * the Buyer tab, and does NOT carry the search trait — unchanged.
     *
     * @test
     */
    public function the_search_partial_is_included_behind_its_own_flag(): void
    {
        $cascade = (string) file_get_contents(
            resource_path('views/partials/location-dna/geography-cascade.blade.php')
        );

        $this->assertStringContainsString('@if ($geoSearchEnabled ?? false)', $cascade);
        $this->assertStringContainsString("@include('partials.location-dna.geography-search')", $cascade);

        $gate    = strpos($cascade, '@if ($geoSearchEnabled ?? false)');
        $include = strpos($cascade, "@include('partials.location-dna.geography-search')");

        $this->assertNotFalse($gate);
        $this->assertNotFalse($include);
        $this->assertLessThan($include, $gate, 'the include must sit inside the gate');
    }
}
