<?php

namespace App\Services\LocationDna\Criteria;

/**
 * Phase 1d-5 — an in-memory {@see CriteriaNeighborhoodRepository} for tests and local composition.
 *
 * WHY IT EXISTS NOW RATHER THAN WITH THE CODE THAT NEEDS IT
 * ---------------------------------------------------------
 * Slice 3 teaches the resolver and the validator about this tier, and those suites run with NO
 * DATABASE — the whole Phase 1b rules layer is testable against
 * {@see FakeCriteriaGeographyRepository} precisely so that cascade rules can be asserted without a
 * corpus. A neighbourhood tier with only a database-backed implementation would force those tests
 * onto SQLite and quietly end that property, so the fake lands with the interface rather than after
 * it.
 *
 * IT IS NOT A SIMPLIFIED IMPLEMENTATION. It answers the same question with the same containment
 * rule: a neighbourhood is returned iff its city is among those asked for. Anything looser would
 * let a rules test pass against behaviour the real repositories do not have.
 *
 * Ordering matches the census implementation — by name, then by id — so a test asserting an option
 * sequence is asserting something the production class also guarantees.
 */
final class FakeCriteriaNeighborhoodRepository implements CriteriaNeighborhoodRepository
{
    /** @var list<GeographyOption> */
    private array $neighborhoods = [];

    /** Register a neighbourhood inside a city. Chainable, mirroring the geography fake. */
    public function withNeighborhood(string $id, string $name, string $cityId): self
    {
        $this->neighborhoods[] = GeographyOption::neighborhood($id, $name, $cityId);

        return $this;
    }

    /** {@inheritDoc} */
    public function neighborhoodsInCities(array $cityIds): array
    {
        $wanted = [];

        foreach ($cityIds as $cityId) {
            $wanted[trim((string) $cityId)] = true;
        }

        if ($wanted === []) {
            return [];
        }

        $matched = array_values(array_filter(
            $this->neighborhoods,
            static fn (GeographyOption $option): bool => isset($wanted[(string) $option->parentId])
        ));

        usort(
            $matched,
            static fn (GeographyOption $a, GeographyOption $b): int => [$a->name, $a->id] <=> [$b->name, $b->id]
        );

        return $matched;
    }
}
