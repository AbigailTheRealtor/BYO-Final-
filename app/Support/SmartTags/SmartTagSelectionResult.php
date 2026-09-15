<?php

namespace App\Support\SmartTags;

/**
 * What survived a selection projection, and why everything else did not.
 */
final class SmartTagSelectionResult
{
    public const REASON_NOT_A_KEY                  = 'not_a_canonical_key';
    public const REASON_UNKNOWN_KEY                = 'unknown_key';
    public const REASON_RETIRED                    = 'retired';
    public const REASON_NO_CONTEXT                 = 'listing_has_no_supported_property_type';
    public const REASON_NOT_APPLICABLE             = 'not_applicable_to_property_type';
    public const REASON_NOT_SELECTABLE             = 'not_selectable_on_this_surface';
    public const REASON_PENDING_REVIEW             = 'pending_compliance_review';
    public const REASON_ANSWERED_BY_PROPERTY_DETAILS = 'answered_by_property_details';
    public const REASON_PROHIBITED                 = 'prohibited_concept';

    /**
     * @param string[]              $accepted  canonical keys, deduplicated, in request order
     * @param array<string, string> $rejected  requested value => reason
     */
    public function __construct(
        public readonly array $accepted,
        public readonly array $rejected,
    ) {
    }

    public function hasRejections(): bool
    {
        return $this->rejected !== [];
    }
}
