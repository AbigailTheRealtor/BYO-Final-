<?php

namespace App\Services\SmartTags;

/**
 * The resolver's output: canonical assignments, plus the tags that cancelled
 * each other out (equal-strength conflicting evidence → unknown).
 */
final class SmartTagResolution
{
    /**
     * @param array<string, ResolvedSmartTag> $assignments       keyed by tag
     * @param string[]                        $droppedForConflict
     */
    public function __construct(
        public readonly array $assignments,
        public readonly array $droppedForConflict,
    ) {
    }

    /** @return string[] */
    public function presentKeys(): array
    {
        return array_keys(array_filter($this->assignments, static fn (ResolvedSmartTag $r) => $r->state->value === 'present'));
    }

    /** @return string[] */
    public function absentKeys(): array
    {
        return array_keys(array_filter($this->assignments, static fn (ResolvedSmartTag $r) => $r->state->value === 'absent'));
    }
}
