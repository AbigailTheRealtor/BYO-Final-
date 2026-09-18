<?php

namespace App\Services\SmartTags\Seeker;

use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagSelectionResult;

/**
 * What a seeker preference write did, and what it refused.
 *
 * A refusal is a value, not an exception: these writes happen inside a criteria
 * save that must not be taken down by a stale tag key. The caller decides
 * whether to surface anything; nothing is ever silently rewritten.
 */
final class SmartTagSeekerPreferenceResult
{
    public const REFUSED_FEATURE_DISABLED    = 'seeker_preferences_disabled';
    public const REFUSED_UNSUPPORTED_SUBJECT = 'unsupported_subject_type';
    public const REFUSED_UNSAVED_SUBJECT     = 'subject_has_no_id';
    public const REFUSED_NOT_OWNER           = 'actor_is_not_the_owner';
    public const REFUSED_NO_CONTEXT          = 'criteria_has_no_supported_property_type';

    private function __construct(
        public readonly bool $accepted,
        public readonly ?string $refusalReason,
        public readonly ?SmartTagSelectionResult $selection,
        public readonly ?SmartTagContext $context,
    ) {
    }

    public static function refused(string $reason): self
    {
        return new self(false, $reason, null, null);
    }

    public static function applied(SmartTagSelectionResult $selection, SmartTagContext $context): self
    {
        return new self(true, null, $selection, $context);
    }

    /** @return string[] canonical keys actually stored */
    public function storedKeys(): array
    {
        return $this->selection?->accepted ?? [];
    }

    /** @return array<string, string> key => rejection reason */
    public function rejectedKeys(): array
    {
        return $this->selection?->rejected ?? [];
    }
}
