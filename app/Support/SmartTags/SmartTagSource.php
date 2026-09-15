<?php

namespace App\Support\SmartTags;

/**
 * Why a listing has a Smart Tag.
 *
 * Every piece of evidence records exactly one source, and sources are never
 * collapsed: the resolver needs to know which one won, and later explanations
 * need to say "From MLS Details" rather than an unexplained boolean.
 *
 * PRECEDENCE (lower rank = stronger):
 *   1. structured_mls              — authoritative MLS fields
 *   2. structured_native_listing   — authoritative BidYourOffer form fields
 *   3. manual_listing_owner        — owner-selected canonical tags
 *   4. mls_remarks / native_listing_description — deterministic prose parsing
 *
 * CONFIRMED ABSENCE may only come from a structured source. A description that
 * does not mention a feature, or an owner who did not select it, is UNKNOWN.
 */
enum SmartTagSource: string
{
    case StructuredMls            = 'structured_mls';
    case MlsRemarks               = 'mls_remarks';
    case StructuredNativeListing  = 'structured_native_listing';
    case NativeListingDescription = 'native_listing_description';
    case ManualListingOwner       = 'manual_listing_owner';

    /** Lower is stronger. */
    public function precedenceRank(): int
    {
        return match ($this) {
            self::StructuredMls            => 1,
            self::StructuredNativeListing  => 2,
            self::ManualListingOwner       => 3,
            self::MlsRemarks,
            self::NativeListingDescription => 4,
        };
    }

    /** Only authoritative structured data may say "this property does not have it". */
    public function canAssertAbsent(): bool
    {
        return $this === self::StructuredMls || $this === self::StructuredNativeListing;
    }

    /** Produced by a deterministic deriver (re-derivable), as opposed to a person. */
    public function isDerived(): bool
    {
        return $this !== self::ManualListingOwner;
    }

    /** Parsed from listing prose. */
    public function isDescription(): bool
    {
        return $this === self::MlsRemarks || $this === self::NativeListingDescription;
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(static fn (self $s) => $s->value, self::cases());
    }
}
