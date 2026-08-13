<?php

namespace App\Services\Location\AddressCorpus;

use App\Services\Location\Coordinates\CoordinatePrecision;
use App\Services\Location\Coordinates\PropertyAddress;

/**
 * One accepted address row from any authoritative source, normalized into the
 * shape `addresses` stores.
 *
 * Immutable and database-free. It knows the column *values* a row would carry;
 * it does not know how to write them, which is what keeps the dry run incapable
 * of touching a database by construction rather than by care.
 *
 * WAS `NadAddressRecord`
 * ---------------------
 * Its contents were always source-neutral — number, street, unit, city, state,
 * postcode, a point and some provenance. Only the name said NAD. Once a NENA
 * NG9-1-1 county normalizer produced the same object, the name was the one thing
 * making a generic pipeline look NAD-specific, and a reader would reasonably
 * conclude the county path was borrowing something not meant for it.
 *
 * `normalized` is produced by {@see PropertyAddress::coordinateLookupLine()} —
 * the same method a user-typed address goes through. That is the whole reason a
 * corpus can be matched at all, and it is why this class holds a PropertyAddress
 * rather than re-deriving the string from its own parts. There is exactly one
 * street normalization in this codebase and every source runs through it.
 *
 * PRECISION IS CARRIED, NOT INFERRED
 * ----------------------------------
 * `precision` is what the *source* justifies, resolved by that source's
 * normalizer, and it is allowed to be null when the source declines to say. A
 * consumer must not upgrade it: a point attached to an address is not evidence
 * of a rooftop, and the difference decides whether the coordinate may drive
 * flood-boundary work. `rawPlacement` travels alongside so the decision can
 * later be revisited from evidence rather than from this field.
 */
final class CorpusAddressRecord
{
    public function __construct(
        /** Which authoritative source produced this row: 'nad', 'pinellas', … */
        public readonly string $source,
        /** The upstream record's own stable id — the dedupe key's third member. */
        public readonly string $sourceRef,
        public readonly string $number,
        public readonly string $street,
        public readonly string $unit,
        public readonly string $city,
        public readonly string $state,
        public readonly string $postcode,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly string $stateFips,
        public readonly string $county,
        /** The raw placement/location value exactly as the source wrote it. */
        public readonly ?string $rawPlacement,
        /** Which locality field supplied `city`: 'post_city', 'inc_muni' or 'none'. */
        public readonly string $localitySource,
        /** Which field supplied `unit`: 'subaddress', 'unit' or 'none'. */
        public readonly string $unitSource,
        public readonly PropertyAddress $address,
        /**
         * Folded placement value for reporting, or '' when the source said
         * nothing. Source-specific vocabularies fold to their own labels; the
         * report only groups by this string and never interprets it.
         */
        public readonly string $placementLabel = '',
        /** Whether the source's own placement vocabulary recognised the value. */
        public readonly bool $placementRecognised = false,
        /** The precision this source justifies, or null when it declines to say. */
        public readonly ?CoordinatePrecision $precision = null,
        /** Human-readable jurisdiction, e.g. 'Pinellas County, FL'. */
        public readonly string $jurisdiction = '',
        /** 'column' when the source stated the state, 'injected' when configured. */
        public readonly string $stateProvenance = 'column',
        /** 'column' when the source stated the county, 'injected' when configured. */
        public readonly string $countyProvenance = 'column',
        /** The source's own lifecycle status, e.g. 'Current'. */
        public readonly string $status = '',
    ) {
    }

    /** The canonical unit-free lookup line the AddressPoint rung matches on. */
    public function normalized(): string
    {
        return $this->address->coordinateLookupLine();
    }

    /** The unit-bearing line that distinguishes two condos in one building. */
    public function identity(): string
    {
        return $this->address->propertyIdentityLine();
    }

    public function hasUnit(): bool
    {
        return $this->unitSource !== 'none';
    }

    public function hasLocality(): bool
    {
        return $this->localitySource !== 'none';
    }

    /** True when any address component was supplied by configuration, not data. */
    public function hasInjectedJurisdiction(): bool
    {
        return $this->stateProvenance === 'injected' || $this->countyProvenance === 'injected';
    }
}
