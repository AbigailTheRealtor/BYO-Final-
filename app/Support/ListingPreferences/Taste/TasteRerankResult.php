<?php

namespace App\Support\ListingPreferences\Taste;

/**
 * The reranker's answer: the SAME candidates, possibly in a different order.
 *
 * Membership is not a field that can differ — `orderedKeys` is a permutation of
 * the input keys, which TasteDnaReranker asserts before returning — and no base
 * score appears here at all, because reranking has no way to express one.
 */
final class TasteRerankResult
{
    /**
     * @param list<string>                        $orderedKeys
     * @param array<string, TasteRerankInfluence> $influences keyed by candidate key
     * @param bool                                $personalized at least one eligible signal existed,
     *                                                          whether or not it moved anything
     */
    public function __construct(
        public readonly array $orderedKeys,
        public readonly array $influences,
        public readonly bool $personalized,
    ) {
    }

    public function influence(string $key): ?TasteRerankInfluence
    {
        return $this->influences[$key] ?? null;
    }

    /** At least one candidate is in a different place than the base order put it. */
    public function changedOrder(): bool
    {
        foreach ($this->influences as $influence) {
            if ($influence->finalPosition !== $influence->basePosition) {
                return true;
            }
        }

        return false;
    }
}
