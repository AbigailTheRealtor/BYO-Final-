<?php

namespace App\Services\SmartTags;

use App\Support\SmartTags\SmartTagContext;

final class DerivationOutcome
{
    public const NOT_AN_OFFER_LISTING = 'not_an_offer_listing';
    public const ARCHIVED = 'listing_archived';
    public const NO_CONTEXT = 'no_supported_property_type';
    public const MLS_REMARKS_NOT_APPROVED = 'mls_remarks_processing_not_approved';

    /**
     * @param string[] $notes
     */
    public function __construct(
        public readonly bool $derived,
        public readonly ?string $skippedReason,
        public readonly ?SmartTagContext $context,
        public readonly bool $structuredDerived,
        public readonly bool $descriptionParsed,
        public readonly array $notes = [],
        public readonly ?SmartTagResolution $resolution = null,
    ) {
    }

    public static function skipped(string $reason, ?SmartTagContext $context = null, ?SmartTagResolution $resolution = null): self
    {
        return new self(false, $reason, $context, false, false, [], $resolution);
    }
}
