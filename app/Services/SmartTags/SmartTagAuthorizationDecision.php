<?php

namespace App\Services\SmartTags;

use Illuminate\Database\Eloquent\Model;

final class SmartTagAuthorizationDecision
{
    public const UNAUTHENTICATED = 'unauthenticated';
    public const NOT_OWNER_EDITABLE = 'listing_type_not_owner_editable';
    public const NOT_FOUND = 'listing_not_found';
    public const NOT_OWNER = 'not_listing_owner';
    public const NOT_OFFER_LISTING = 'not_an_offer_listing';
    public const ARCHIVED = 'listing_archived';

    private function __construct(
        public readonly bool $allowed,
        public readonly ?string $reason,
        public readonly ?Model $listing,
    ) {
    }

    public static function allow(Model $listing): self
    {
        return new self(true, null, $listing);
    }

    public static function deny(string $reason): self
    {
        return new self(false, $reason, null);
    }
}
