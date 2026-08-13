<?php

namespace App\Services\LocationDna\Places;

use App\Models\LocationPlace;

/**
 * Phase 1d-3 — a resolved place and the hierarchy above and below it.
 *
 * Immutable and self-contained: everything a surface needs to render "Clearwater Beach —
 * neighbourhood of Clearwater, Pinellas County, FL — 33767" without going back to the database.
 * That matters because the alternative is a view issuing four lookups per chip.
 *
 * `$ambiguous` IS PART OF THE ANSWER, NOT AN ERROR FLAG. A name matching several rows is a normal
 * property of US geography — the corpus holds 202 same-state place-name collisions — and the
 * honest response is to hand back the best candidate AND say that others exist, so the caller can
 * disambiguate rather than discover the problem later.
 */
final class PlaceMatch
{
    /**
     * @param  array{geoid: string, name: string}|null                    $county    the PRIMARY county
     * @param  list<array{geoid: string, name: string, primary: bool}>    $counties  every county, primary first
     * @param  array{geoid: string, name: string, usps: string}|null      $state
     * @param  list<string>                                               $zips
     * @param  list<LocationPlace>                                        $candidates
     */
    public function __construct(
        public readonly LocationPlace $place,
        public readonly ?LocationPlace $parent,
        public readonly ?array $county,
        public readonly array $counties,
        public readonly ?array $state,
        public readonly array $zips,
        public readonly bool $ambiguous,
        public readonly array $candidates,
    ) {
    }

    /** True when the place is published as spanning more than one county. */
    public function spansCounties(): bool
    {
        return count($this->counties) > 1;
    }

    /** @return list<string> */
    public function countyNames(): array
    {
        return array_map(fn (array $c): string => $c['name'], $this->counties);
    }

    public function name(): string
    {
        return $this->place->name;
    }

    public function type(): string
    {
        return $this->place->type;
    }

    public function isSubPlace(): bool
    {
        return $this->place->isSubPlace();
    }

    /** The parent's stored-label form, `Clearwater, FL`, or null when the place stands alone. */
    public function parentLabel(): ?string
    {
        if ($this->parent === null) {
            return null;
        }

        $usps = $this->state['usps'] ?? null;

        return $usps === null ? $this->parent->name : $this->parent->name.', '.$usps;
    }

    /**
     * The label format stored blobs use — `{name}, {ST}`.
     *
     * Identical to what {@see App\Services\LocationDna\Criteria\Projection\GeographyLabelProjector}
     * emits, so a resolved sub-place can be written into the same document as a place without
     * introducing a third format that nothing downstream matches.
     */
    public function label(): string
    {
        $usps = $this->state['usps'] ?? null;

        return $usps === null ? $this->place->name : $this->place->name.', '.$usps;
    }

    public function countyName(): ?string
    {
        return $this->county['name'] ?? null;
    }

    public function primaryZip(): ?string
    {
        return $this->zips[0] ?? null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name'        => $this->name(),
            'label'       => $this->label(),
            'type'        => $this->type(),
            'parent'      => $this->parentLabel(),
            'county'      => $this->countyName(),
            'counties'    => $this->countyNames(),
            'state'       => $this->state['name'] ?? null,
            'zips'        => $this->zips,
            'source'      => $this->place->source,
            'ambiguous'   => $this->ambiguous,
        ];
    }
}
