<?php

namespace App\Services\ListingPreferences;

use App\Support\SmartTags\SmartTagListingRef;

/**
 * One customer-safe property summary for the preference management area.
 *
 * AN ALLOW-LIST, LIKE EVERY OTHER PROJECTION THAT TOUCHES MLS DATA. Each field
 * is a named readonly property and {@see toArray()} writes every key by hand.
 * There is no path from a BridgeProperty or an auction row into a view through
 * this object — the hydrator has to choose each value deliberately.
 *
 * "UNAVAILABLE" IS A FIRST-CLASS SHAPE, not an error. A customer can have
 * passed on a listing that has since been removed, and their own decision is
 * still theirs to see and undo. Such a card carries `available = false`, no
 * property facts at all, and nothing invented: no address, no price, no
 * photograph. The page says the listing is no longer available and keeps the
 * preference controls working.
 */
final class ListingPreferenceListingCard
{
    /**
     * @param string|null $title        the listing's own heading, or null when withheld/unknown
     * @param string|null $addressLine  street line, ONLY where the source permits displaying it
     * @param string|null $locationLine city / state / ZIP, which an address-suppressed IDX listing may still show
     * @param string|null $priceDisplay pre-formatted; this object never does currency maths
     * @param string|null $url          the canonical public page, when one exists
     * @param string|null $photoUrl     hero image, when the source permits one
     */
    public function __construct(
        public readonly SmartTagListingRef $ref,
        public readonly bool $available,
        public readonly string $sourceLabel,
        public readonly ?string $title = null,
        public readonly ?string $addressLine = null,
        public readonly ?string $locationLine = null,
        public readonly ?string $priceDisplay = null,
        public readonly ?string $url = null,
        public readonly ?string $photoUrl = null,
        public readonly ?string $factsLine = null,
    ) {
    }

    /**
     * The card for a listing that cannot be shown.
     *
     * Carries the reference and nothing else. The customer keeps their
     * preference, their reasons and their history; the platform simply does not
     * claim to know anything about the property any more.
     */
    public static function unavailable(SmartTagListingRef $ref, string $sourceLabel): self
    {
        return new self($ref, false, $sourceLabel);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'listing_type'  => $this->ref->type->value,
            'listing_id'    => $this->ref->id,
            'available'     => $this->available,
            'source_label'  => $this->sourceLabel,
            'title'         => $this->title,
            'address_line'  => $this->addressLine,
            'location_line' => $this->locationLine,
            'price_display' => $this->priceDisplay,
            'url'           => $this->url,
            'photo_url'     => $this->photoUrl,
            'facts_line'    => $this->factsLine,
        ];
    }

    public function key(): string
    {
        return "{$this->ref->type->value}:{$this->ref->id}";
    }
}
