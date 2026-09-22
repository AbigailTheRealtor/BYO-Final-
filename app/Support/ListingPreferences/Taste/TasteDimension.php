<?php

namespace App\Support\ListingPreferences\Taste;

/**
 * WHAT a learned signal is about — a CLOSED list, and the closure is a Fair
 * Housing property rather than tidiness.
 *
 * Every dimension here is a characteristic of a PROPERTY, read from a governed
 * structured source, or a structured reason the customer chose themselves:
 *
 *   smart_tag         a canonical Smart Tag, from a tag-backed reason the
 *                     customer chose OR the listing's own resolved assignment —
 *                     either way gated live by `isSeekerSelectable()`
 *   reason            a structured `criteria` reason (price, size, fees) — what
 *                     the customer SAID, never a figure we inferred
 *   bedrooms / bathrooms / living_area / lot_size
 *                     the listing's structured facts
 *   property_subtype  the listing's structured sub-type (Condominium, …)
 *
 * WHAT IS ABSENT, DELIBERATELY, AND MUST STAY ABSENT (governance §6, §13):
 * no neighbourhood, city, ZIP, school, coordinate, address, demographic, crime
 * or "area" dimension of any kind; no dimension computed from OTHER customers'
 * choices. Location reasons are not learned in Phase 4 either — a preference
 * row carries no structural link to the Important Places it would have to be
 * measured against, and guessing one would be inventing a location signal.
 *
 * Adding a case is a governance change, and TasteDnaArchitectureGuardTest pins
 * the list so it cannot grow by accident.
 */
enum TasteDimension: string
{
    case SmartTag        = 'smart_tag';
    case Reason          = 'reason';
    case Bedrooms        = 'bedrooms';
    case Bathrooms       = 'bathrooms';
    case LivingArea      = 'living_area';
    case LotSize         = 'lot_size';
    case PropertySubtype = 'property_subtype';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }

    /** Numeric facts are summarised as a band, not as a direction per value. */
    public function isNumeric(): bool
    {
        return in_array($this, [self::Bedrooms, self::Bathrooms, self::LivingArea, self::LotSize], true);
    }
}
