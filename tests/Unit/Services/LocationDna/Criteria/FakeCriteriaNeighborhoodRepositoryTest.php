<?php

namespace Tests\Unit\Services\LocationDna\Criteria;

use App\Services\LocationDna\Criteria\FakeCriteriaNeighborhoodRepository;
use App\Services\LocationDna\Criteria\GeographyOption;
use PHPUnit\Framework\TestCase;

/**
 * Phase 1d-5, slice 2 — the in-memory neighbourhood repository.
 *
 * WHY A FAKE GETS ITS OWN SUITE
 * -----------------------------
 * Slice 3's resolver and validator tests will assert cascade behaviour against this class with no
 * database at all. If the fake's containment rule quietly differed from the census implementation's
 * — returning everything regardless of city, say — those suites would pass against behaviour the
 * production repositories do not have, and the divergence would surface as a cascade bug much later
 * and somewhere else entirely. So the fake is held to the same contract here, before anything
 * depends on it.
 *
 * PURE. No database, no container.
 */
class FakeCriteriaNeighborhoodRepositoryTest extends TestCase
{
    private function repo(): FakeCriteriaNeighborhoodRepository
    {
        return (new FakeCriteriaNeighborhoodRepository())
            ->withNeighborhood('900', 'Clearwater Beach', '1212875')
            ->withNeighborhood('901', 'Sand Key', '1212875')
            ->withNeighborhood('902', 'Snell Isle', '1263000');
    }

    /** @return list<string> */
    private function names(array $cityIds): array
    {
        return array_map(
            fn (GeographyOption $o): string => $o->name,
            $this->repo()->neighborhoodsInCities($cityIds)
        );
    }

    /** @test */
    public function it_returns_only_the_neighbourhoods_of_the_requested_cities(): void
    {
        $this->assertSame(['Clearwater Beach', 'Sand Key'], $this->names(['1212875']));
        $this->assertSame(['Snell Isle'], $this->names(['1263000']));
    }

    /** @test */
    public function several_cities_are_merged_and_ordered_by_name(): void
    {
        $this->assertSame(
            ['Clearwater Beach', 'Sand Key', 'Snell Isle'],
            $this->names(['1263000', '1212875']),
            'Ordering is by name, not by the order the cities were asked for.'
        );
    }

    /** @test */
    public function an_empty_city_list_yields_nothing(): void
    {
        $this->assertSame([], $this->repo()->neighborhoodsInCities([]));
    }

    /** @test */
    public function an_unknown_city_yields_nothing(): void
    {
        $this->assertSame([], $this->names(['9999999']));
    }

    /** @test */
    public function it_emits_neighbourhood_options_parented_by_their_city(): void
    {
        $option = $this->repo()->neighborhoodsInCities(['1212875'])[0];

        $this->assertTrue($option->is(GeographyOption::KIND_NEIGHBORHOOD));
        $this->assertSame('900', $option->id);
        $this->assertSame('1212875', $option->parentId);
        $this->assertNull($option->code);
    }

    /** @test */
    public function a_repeated_city_id_does_not_duplicate_results(): void
    {
        $this->assertSame(
            ['Clearwater Beach', 'Sand Key'],
            $this->names(['1212875', '1212875'])
        );
    }

    /** @test */
    public function an_empty_repository_answers_empty(): void
    {
        $this->assertSame(
            [],
            (new FakeCriteriaNeighborhoodRepository())->neighborhoodsInCities(['1212875'])
        );
    }
}
