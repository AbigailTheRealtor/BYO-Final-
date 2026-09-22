<?php

namespace App\Support\ListingPreferences\Taste;

/**
 * One customer-facing sentence about their own taste, and the evidence behind it.
 *
 * AN ALLOW-LIST OF STRINGS. Everything here is already worded for a person:
 * no score, no percentage, no key, no id, no subject, no class name. The page
 * renders these fields and nothing else, so an internal value cannot reach it
 * by being added to a signal later.
 */
final class TasteObservation
{
    public const GROUP_SAVE   = 'save';
    public const GROUP_PASS   = 'pass';
    public const GROUP_MIXED  = 'mixed';

    public function __construct(
        public readonly string $group,
        public readonly string $headline,
        public readonly string $summary,
        public readonly string $evidence,
        public readonly string $source,
    ) {
    }

    /** @return array{group: string, headline: string, summary: string, evidence: string, source: string} */
    public function toArray(): array
    {
        return [
            'group'    => $this->group,
            'headline' => $this->headline,
            'summary'  => $this->summary,
            'evidence' => $this->evidence,
            'source'   => $this->source,
        ];
    }
}
