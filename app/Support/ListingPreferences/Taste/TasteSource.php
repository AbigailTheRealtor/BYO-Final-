<?php

namespace App\Support\ListingPreferences\Taste;

/**
 * Where a piece of evidence came from.
 *
 *   stated_reason           a structured reason chip the customer chose — what
 *                           they told us, the stronger kind of evidence
 *   listing_characteristic  a governed structured fact of the listing they
 *                           chose (a resolved Smart Tag, bedrooms, sub-type) —
 *                           what the home had, which is weaker: somebody who
 *                           Saves three homes with garages may not care about
 *                           garages at all
 *
 * There is no third source. Free text is never parsed, photographs are never
 * analysed, and nothing is read from other customers.
 */
enum TasteSource: string
{
    case StatedReason          = 'stated_reason';
    case ListingCharacteristic = 'listing_characteristic';
}
