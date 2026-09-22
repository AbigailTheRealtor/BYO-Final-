<?php

namespace App\Support\ListingPreferences\Taste;

/**
 * The governed, structured characteristics of one home — and nothing else.
 *
 * AN ALLOW-LIST BY CONSTRUCTION. Every field is a named property; there is no
 * bag of extra attributes, so a reader cannot pass a city, a ZIP, a coordinate
 * or a school through this object however it is written. That is the structural
 * half of the rule that Taste DNA never learns anything about WHERE a home is.
 *
 * `tagKeys` are the listing's resolved PRESENT Smart Tags. Absent-state rows are
 * not carried: a Pass on a house without a pool is not evidence about pools.
 */
final class TasteListingFacts
{
    /**
     * @param list<string> $tagKeys     canonical keys, resolved `present`
     * @param list<string> $subtypes    structured sub-type values as published
     */
    public function __construct(
        public readonly array $tagKeys = [],
        public readonly ?float $bedrooms = null,
        public readonly ?float $bathrooms = null,
        public readonly ?float $livingArea = null,
        public readonly ?float $lotAcres = null,
        public readonly array $subtypes = [],
    ) {
    }

    public function numeric(TasteDimension $dimension): ?float
    {
        return match ($dimension) {
            TasteDimension::Bedrooms   => $this->bedrooms,
            TasteDimension::Bathrooms  => $this->bathrooms,
            TasteDimension::LivingArea => $this->livingArea,
            TasteDimension::LotSize    => $this->lotAcres,
            default                    => null,
        };
    }
}
