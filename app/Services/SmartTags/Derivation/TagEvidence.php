<?php

namespace App\Services\SmartTags\Derivation;

use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagSource;
use App\Support\SmartTags\SmartTagState;

/**
 * One reason to believe a listing does (or, from structured data only, does
 * not) have a Smart Tag. Immutable; carries no listing text.
 */
final class TagEvidence
{
    public function __construct(
        public readonly string $tagKey,
        public readonly SmartTagState $state,
        public readonly SmartTagSource $source,
        public readonly SmartTagContext $context,
        public readonly int $confidence,
        public readonly ?string $sourceField = null,
        public readonly ?string $ruleId = null,
        /** The field ANSWERS the tag outright (a Yes/No question) — blocks a manual selection. */
        public readonly bool $authoritative = false,
    ) {
    }
}
