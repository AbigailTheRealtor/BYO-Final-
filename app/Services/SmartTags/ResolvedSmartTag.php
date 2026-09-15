<?php

namespace App\Services\SmartTags;

use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagSource;
use App\Support\SmartTags\SmartTagState;

/**
 * The one canonical answer for a listing × tag, and where it came from.
 */
final class ResolvedSmartTag
{
    /**
     * @param string[] $conflictTags other tags whose weaker evidence this one overrode
     */
    public function __construct(
        public readonly string $tagKey,
        public readonly SmartTagState $state,
        public readonly SmartTagSource $winningSource,
        public readonly SmartTagContext $context,
        public readonly bool $hasConflict,
        public readonly array $conflictTags = [],
    ) {
    }
}
