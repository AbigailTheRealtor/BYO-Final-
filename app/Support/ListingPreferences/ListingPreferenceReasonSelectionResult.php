<?php

namespace App\Support\ListingPreferences;

/**
 * What survived a reason projection, and why everything else did not.
 *
 * Shaped after {@see \App\Support\SmartTags\SmartTagSelectionResult} so the two
 * write boundaries report refusals the same way.
 */
final class ListingPreferenceReasonSelectionResult
{
    public const REASON_NOT_A_KEY          = 'not_a_reason_key';
    public const REASON_UNKNOWN_KEY        = 'unknown_reason_key';
    public const REASON_RETIRED            = 'retired';
    public const REASON_NOT_FOR_STATE      = 'not_offered_for_this_state';
    public const REASON_NOT_APPLICABLE     = 'not_applicable_to_property_type';
    public const REASON_TAG_NOT_SELECTABLE = 'linked_smart_tag_is_not_seeker_selectable';

    /**
     * @param list<string>          $accepted reason keys, deduplicated, in request order
     * @param array<string, string> $rejected requested value => reason
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
