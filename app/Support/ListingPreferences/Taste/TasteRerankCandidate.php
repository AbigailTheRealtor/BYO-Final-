<?php

namespace App\Support\ListingPreferences\Taste;

/**
 * One already-eligible result handed to the reranker, in the order the existing
 * matcher produced.
 *
 * It carries only what reranking may read: an opaque key the caller uses to put
 * its own rows back in order, the EXISTING base score (read, never written), and
 * the listing's governed facts — or null when they are unavailable, in which
 * case the candidate simply receives no Taste influence.
 */
final class TasteRerankCandidate
{
    public function __construct(
        public readonly string $key,
        public readonly int|float $baseScore,
        public readonly ?TasteListingFacts $facts,
    ) {
    }
}
