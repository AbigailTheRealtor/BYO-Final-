<?php

namespace App\Services\Stellar\Matching;

/**
 * One listing's resolved Smart Tags, as far as the match engine needs them: a fact
 * the scorer reads, like any field of {@see ListingMatchFacts}.
 *
 * Filled before scoring, from the resolved present assignments, by
 * {@see \App\Services\SmartTags\Seeker\ListingSmartTagIndex} — never derived, and
 * never read from remarks, descriptions or photos. It carries keys only, and only
 * the keys the seeker asked about: the rules compare, they do not browse.
 *
 * Two facts, because the rules explain two different gaps. A listing that HAS
 * resolved tags but not the ones asked about is a known non-match ("Does not
 * list: …"); a listing with no resolved tag at all could not be checked. Both
 * earn nothing for the picks.
 *
 * Pure data: no container, no query, no I/O.
 */
final class ListingSmartTagFacts
{
    /**
     * @param list<string> $presentKeys       the requested keys this listing has, resolved present
     * @param bool         $hasAnyResolvedTag whether the listing has any resolved present tag at all
     */
    public function __construct(
        public readonly array $presentKeys,
        public readonly bool $hasAnyResolvedTag,
    ) {}
}
