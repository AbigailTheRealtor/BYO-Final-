<?php

namespace App\Services\Stellar\Matching;

/**
 * One listing's Smart Tag facts for the seeker's selected tags, as far as the match
 * engine needs them: a fact the scorer reads, like any field of {@see ListingMatchFacts}.
 *
 * Provider-neutral and filled BEFORE scoring — for Bridge by
 * {@see \App\Services\SmartTags\Seeker\ListingSmartTagIndex}, which decides per tag
 * whether the listing's data could check it. Never derived here, never read from
 * remarks, descriptions or photos. Keys only, and only the keys the seeker asked about.
 *
 * THREE ANSWERS PER SELECTED TAG, and only two are listed:
 *   • present      — the listing has it (a match);
 *   • known absent — the listing's data was checked and it does not have it;
 *   • unknown      — everything else: the data could not answer. Never a miss.
 *
 * Pure data: no container, no query, no I/O.
 */
final class ListingSmartTagFacts
{
    /**
     * @param list<string> $presentKeys     requested keys the listing has
     * @param list<string> $knownAbsentKeys requested keys the listing's data checked and did not find
     */
    public function __construct(
        public readonly array $presentKeys,
        public readonly array $knownAbsentKeys = [],
    ) {}
}
