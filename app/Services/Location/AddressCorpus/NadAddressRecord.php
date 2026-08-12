<?php

namespace App\Services\Location\AddressCorpus;

use App\Services\Location\Coordinates\PropertyAddress;

/**
 * One accepted NAD row, normalized into the shape `addresses` stores.
 *
 * Immutable and database-free. It knows the column *values* a row would carry;
 * it does not know how to write them, which is what keeps the dry run incapable
 * of touching a database by construction rather than by care.
 *
 * `normalized` is produced by {@see PropertyAddress::coordinateLookupLine()} —
 * the same method a user-typed address goes through. That is the whole reason
 * the corpus can be matched at all, and it is why this class holds a
 * PropertyAddress rather than re-deriving the string from its own parts.
 */
final class NadAddressRecord
{
    public function __construct(
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
        /** The raw Placement value exactly as the source wrote it. */
        public readonly ?string $rawPlacement,
        /** Which locality field supplied `city`: 'post_city', 'inc_muni' or 'none'. */
        public readonly string $localitySource,
        /** Which field supplied `unit`: 'subaddress', 'unit' or 'none'. */
        public readonly string $unitSource,
        public readonly PropertyAddress $address,
    ) {
    }

    /** The canonical unit-free lookup line the AddressPoint rung matches on. */
    public function normalized(): string
    {
        return $this->address->coordinateLookupLine();
    }

    public function hasUnit(): bool
    {
        return $this->unitSource !== 'none';
    }

    public function hasLocality(): bool
    {
        return $this->localitySource !== 'none';
    }
}
