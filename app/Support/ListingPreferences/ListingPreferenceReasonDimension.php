<?php

namespace App\Support\ListingPreferences;

/**
 * What kind of thing a reason is about — and, decisively, whether it may ever
 * become a learned signal.
 *
 * The four dimensions exist because the customer's reason vocabulary spans
 * three subsystems that deliberately do not overlap:
 *
 *   smart_tag    property characteristics — config/smart_tags.php
 *   criteria     price, size, fees — structured search criteria, which the
 *                Smart Tag taxonomy header excludes by name
 *   location     proximity and the customer's own places — Location DNA
 *   unspecified  real customer language with no structured counterpart
 *
 * Forcing all four into Smart Tags would create exactly the duplicate
 * vocabulary the taxonomy governance forbids; forcing them into criteria would
 * lose the tag link that makes a reason scorable. Hence a dimension per domain.
 *
 * LEARNABILITY IS A PROPERTY OF THE DIMENSION, not of the reason. `unspecified`
 * is captured and never learned: there is nothing structured to learn it
 * against, and inferring one would be inventing the vocabulary this design
 * exists to avoid. No learning code exists yet — this flag is the contract that
 * code must honour, and the governance doc states the same rule in prose.
 */
enum ListingPreferenceReasonDimension: string
{
    case SmartTag    = 'smart_tag';
    case Criteria    = 'criteria';
    case Location    = 'location';
    case Unspecified = 'unspecified';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }

    /** Whether a reason in this dimension may contribute to a learned signal. */
    public function isLearnable(): bool
    {
        return $this !== self::Unspecified;
    }

    /** Whether a reason in this dimension must name a canonical Smart Tag. */
    public function requiresSmartTag(): bool
    {
        return $this === self::SmartTag;
    }

    /** Whether a reason in this dimension must name a match-scorer category. */
    public function requiresCriteriaDimension(): bool
    {
        return $this === self::Criteria;
    }
}
