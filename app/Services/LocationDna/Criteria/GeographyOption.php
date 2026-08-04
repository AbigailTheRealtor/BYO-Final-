<?php

namespace App\Services\LocationDna\Criteria;

use InvalidArgumentException;

/**
 * Phase 1a — one selectable geography option in the Criteria cascade.
 *
 * INERT. Nothing in `App\Services\LocationDna\Criteria` is wired into any controller, Livewire
 * component, view, route or persistence path. It reads reference tables and returns DTOs; it
 * cannot write. See {@see \Tests\Unit\Services\LocationDna\Criteria\Phase1aCriteriaInertnessGuardTest}.
 *
 * WHY `id` IS THE PRIMARY KEY AND NOT THE FIPS CODE
 * -------------------------------------------------
 * The design intent is that a selection is carried by a stable identifier, never by a display
 * name — free-text names are what made the legacy criteria data unsafe to convert. FIPS was the
 * obvious candidate, but the reference data does not support it:
 *
 *   - `us_cities.fips_code` is empty for ALL 25,830 rows;
 *   - `us_counties.fips_code` has a duplicate, so it is not a key.
 *
 * So `id` carries the application primary key — unambiguous and always present — and `code`
 * carries the external identifier (state FIPS, county GEOID, ZIP) where one exists. Consumers
 * key on `id`; `code` is for display, export and eventual corpus joins.
 *
 * `id` is a STRING even though the underlying keys are integers, because ZIP options are keyed by
 * the ZIP itself and the cascade must not care which tier it is holding.
 */
final class GeographyOption
{
    public const KIND_STATE  = 'state';
    public const KIND_COUNTY = 'county';
    public const KIND_CITY   = 'city';
    public const KIND_ZIP    = 'zip';

    private const KINDS = [self::KIND_STATE, self::KIND_COUNTY, self::KIND_CITY, self::KIND_ZIP];

    /**
     * @param  string       $kind      one of the KIND_* constants
     * @param  string       $id        stable identifier within its kind
     * @param  string       $name      display label
     * @param  string|null  $code      external identifier: state FIPS, county GEOID, ZIP; null when absent
     * @param  string|null  $parentId  id of the option one tier up; null for states
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $code = null,
        public readonly ?string $parentId = null,
    ) {
        if (! in_array($kind, self::KINDS, true)) {
            throw new InvalidArgumentException(
                "`{$kind}` is not a geography kind; expected one of: ".implode(', ', self::KINDS)
            );
        }

        if ($id === '') {
            throw new InvalidArgumentException('A geography option must carry a non-empty id.');
        }

        if ($name === '') {
            throw new InvalidArgumentException('A geography option must carry a non-empty name.');
        }

        if ($kind === self::KIND_STATE && $parentId !== null) {
            throw new InvalidArgumentException('A state has no parent.');
        }

        if ($kind !== self::KIND_STATE && $parentId === null) {
            throw new InvalidArgumentException("A `{$kind}` option must name its parent.");
        }
    }

    public static function state(string $id, string $name, ?string $fips = null): self
    {
        return new self(self::KIND_STATE, $id, $name, $fips);
    }

    public static function county(string $id, string $name, string $stateId, ?string $geoid = null): self
    {
        return new self(self::KIND_COUNTY, $id, $name, $geoid, $stateId);
    }

    public static function city(string $id, string $name, string $countyId): self
    {
        return new self(self::KIND_CITY, $id, $name, null, $countyId);
    }

    public static function zip(string $zip, string $countyId): self
    {
        return new self(self::KIND_ZIP, $zip, $zip, $zip, $countyId);
    }

    /**
     * Phase 1b (D6) — is this option of the given kind?
     *
     * Additive convenience over the `kind` property, so consumers filtering an enumerated list read
     * as `$option->is(self::KIND_CITY)` rather than repeating the comparison at every call site.
     */
    public function is(string $kind): bool
    {
        return $this->kind === $kind;
    }

    /**
     * Phase 1b (D6) — are these the same option?
     *
     * IDENTITY IS THE TRIPLE (kind, id, parentId), AND THAT IS NOT THE OBVIOUS ANSWER
     * ------------------------------------------------------------------------------
     * For states, counties and cities the id alone would do. For ZIPs it will not: the same ZIP is
     * emitted once per associated county, so two options carrying id "11001" under two different
     * counties are genuinely DIFFERENT options, not a duplicate to be collapsed. Writing that down
     * here is the point of this method — a reader who assumes id-equality would be entitled to
     * deduplicate one of those rows away and destroy the association that lets a shared ZIP survive
     * the removal of one of its counties.
     *
     * The display name and the external code are deliberately excluded. A selection is never
     * carried by a display name; free-text names are what made the legacy criteria data unsafe to
     * convert in the first place.
     *
     * Note that SELECTION identity is a different question and deliberately has a different answer:
     * {@see \App\Services\LocationDna\Criteria\Rules\GeographySelection} keys ZIPs on the code
     * alone, because a selected ZIP is satisfied by ANY of its counties.
     */
    public function matches(self $other): bool
    {
        return $this->kind === $other->kind
            && $this->id === $other->id
            && $this->parentId === $other->parentId;
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'kind'      => $this->kind,
            'id'        => $this->id,
            'name'      => $this->name,
            'code'      => $this->code,
            'parent_id' => $this->parentId,
        ];
    }
}
