<?php

namespace App\Services\LocationDna\Criteria;

/**
 * Phase 1a — an in-memory {@see CriteriaGeographyRepository} for tests and local composition.
 *
 * WHY IT LIVES IN `app/` RATHER THAN `tests/`
 * -------------------------------------------
 * Phase 1b's cascade rules must be testable with no database at all, and the Livewire preview in
 * Phase 1c must be renderable against a known fixture. Both need this class, and a test-only class
 * cannot be bound in a service container for a local or demo environment. It is the same choice
 * Laravel makes for its own array/null drivers.
 *
 * It is nonetheless INERT production code: it holds arrays, returns DTOs, and can neither read a
 * database nor write one. The inertness guard asserts that.
 *
 * ORDERING MATCHES THE ELOQUENT IMPLEMENTATION so a test written against the fake describes the
 * real thing: states/counties/cities by name, ZIPs by code. Callers that depend on order are
 * therefore not accidentally coupled to insertion order here.
 */
final class FakeCriteriaGeographyRepository implements CriteriaGeographyRepository
{
    /** @var list<GeographyOption> */
    private array $states = [];

    /** @var list<GeographyOption> */
    private array $counties = [];

    /** @var list<GeographyOption> */
    private array $cities = [];

    /** @var list<GeographyOption> */
    private array $zips = [];

    public function withState(string $id, string $name, ?string $fips = null): self
    {
        $this->states[] = GeographyOption::state($id, $name, $fips);

        return $this;
    }

    public function withCounty(string $id, string $name, string $stateId, ?string $geoid = null): self
    {
        $this->counties[] = GeographyOption::county($id, $name, $stateId, $geoid);

        return $this;
    }

    public function withCity(string $id, string $name, string $countyId): self
    {
        $this->cities[] = GeographyOption::city($id, $name, $countyId);

        return $this;
    }

    /**
     * Associate a ZIP with a county.
     *
     * Calling this twice for the same ZIP under different counties is legitimate and models the
     * cross-county ZCTA the interface documents.
     */
    public function withZip(string $zip, string $countyId): self
    {
        $this->zips[] = GeographyOption::zip($zip, $countyId);

        return $this;
    }

    /** {@inheritDoc} */
    public function states(): array
    {
        return $this->sortedByName($this->states);
    }

    /** {@inheritDoc} */
    public function countiesInState(string $stateId): array
    {
        return $this->sortedByName(
            array_values(array_filter(
                $this->counties,
                static fn (GeographyOption $o): bool => $o->parentId === $stateId
            ))
        );
    }

    /** {@inheritDoc} */
    public function citiesInCounties(array $countyIds): array
    {
        return $this->sortedByName($this->childrenOf($this->cities, $countyIds));
    }

    /** {@inheritDoc} */
    public function zipsInCounties(array $countyIds): array
    {
        $matched = $this->childrenOf($this->zips, $countyIds);

        usort($matched, static fn (GeographyOption $a, GeographyOption $b): int => $a->id <=> $b->id);

        return $matched;
    }

    /**
     * @param  list<GeographyOption>  $options
     * @param  list<string>           $parentIds
     * @return list<GeographyOption>
     */
    private function childrenOf(array $options, array $parentIds): array
    {
        if ($parentIds === []) {
            return [];
        }

        $wanted = array_flip(array_map(strval(...), $parentIds));

        return array_values(array_filter(
            $options,
            static fn (GeographyOption $o): bool => isset($wanted[(string) $o->parentId])
        ));
    }

    /**
     * @param  list<GeographyOption>  $options
     * @return list<GeographyOption>
     */
    private function sortedByName(array $options): array
    {
        usort($options, static fn (GeographyOption $a, GeographyOption $b): int => $a->name <=> $b->name);

        return $options;
    }
}
