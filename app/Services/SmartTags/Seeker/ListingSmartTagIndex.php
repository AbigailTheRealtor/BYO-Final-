<?php

namespace App\Services\SmartTags\Seeker;

use App\Models\BridgeProperty;
use App\Models\SmartTagAssignment;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\Matching\ListingSmartTagFacts;
use App\Support\SmartTags\SmartTagListingType;
use App\Support\SmartTags\SmartTagState;

/**
 * The resolved PRESENT Smart Tags of a set of candidate listings, read in batch
 * BEFORE scoring, and handed to the scorer per listing as {@see ListingSmartTagFacts}.
 *
 * THIS IS INPUT CONSTRUCTION, NOT SCORING. BuyerMatchScorer::scoreFacts() and its
 * category rules are pure and read facts only; the one Smart Tag read in matching
 * is here, called by the adapters — BuyerMatchScorer::scoreAll() for a result
 * set, and each single-listing surface (property-detail match context, Match
 * Check) for its one row — so every surface scores from the same facts.
 *
 * Reads `smart_tag_assignments` only — the canonical per-listing answer the
 * resolver already produced — and derives nothing. Identity is the registry's
 * own `(listing_type, listing_id)`: Stellar candidates are `bridge` rows keyed by
 * `bridge_properties.id`. Only the tags the seeker selected are fetched, so the
 * row count is bounded by candidates × selections, not by the taxonomy.
 *
 * TWO QUERIES PER 500 CANDIDATES, WHATEVER THE SELECTION. The second asks only
 * which candidates have ANY resolved present tag, so "this home has tags, just
 * not the ones you picked" and "we hold no feature data for this home" stay
 * different answers — the first is a real non-match, the second is missing data
 * and is worded as such.
 *
 * No BYO Seller/Landlord listing is a Stellar candidate — BYO search has no score
 * by design — so no native assignment is read here. A Bridge row is never merged
 * with a BYO listing that happens to share its MLS key: that would be a second,
 * unreviewed identity rule.
 */
final class ListingSmartTagIndex
{
    /**
     * @param array<int, list<string>> $presentByBridgeId selected keys each listing has
     * @param array<int, true>         $withAnyTag        listings with at least one resolved present tag
     * @param bool                     $read              whether any key was asked about (a read happened)
     */
    private function __construct(
        private readonly array $presentByBridgeId,
        private readonly array $withAnyTag,
        private readonly bool $read,
    ) {
    }

    public static function empty(): self
    {
        return new self([], [], false);
    }

    /**
     * The candidates' tags for the picks this search SCORES — after the structured-criterion
     * deduplication ({@see BuyerMatchScorer::scoredSeekerTags()}). No scored picks (including
     * matching switched off, which empties the payload's picks) reads nothing.
     *
     * @param iterable<BridgeProperty> $rows
     */
    public static function forCandidates(iterable $rows, BuyerCriteriaPayload $criteria): self
    {
        return self::forBridgeRows($rows, BuyerMatchScorer::scoredSeekerTags($criteria));
    }

    /**
     * @param iterable<BridgeProperty> $rows
     * @param list<string>             $tagKeys the seeker's selection; empty reads nothing
     */
    public static function forBridgeRows(iterable $rows, array $tagKeys): self
    {
        $ids = [];

        foreach ($rows as $row) {
            $id = (int) ($row->id ?? 0);

            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        if ($ids === [] || $tagKeys === []) {
            return self::empty();
        }

        $present = [];
        $withAny = [];

        foreach (array_chunk(array_values($ids), 500) as $chunk) {
            $tagged = SmartTagAssignment::query()
                ->where('listing_type', SmartTagListingType::Bridge->value)
                ->whereIn('listing_id', $chunk)
                ->where('state', SmartTagState::Present->value)
                ->distinct()
                ->pluck('listing_id');

            foreach ($tagged as $taggedId) {
                $withAny[(int) $taggedId] = true;
            }

            $rowsFound = SmartTagAssignment::query()
                ->where('listing_type', SmartTagListingType::Bridge->value)
                ->whereIn('listing_id', $chunk)
                ->where('state', SmartTagState::Present->value)
                ->whereIn('tag_key', array_values($tagKeys))
                ->orderBy('listing_id')
                ->orderBy('tag_key')
                ->get(['listing_id', 'tag_key']);

            foreach ($rowsFound as $found) {
                $present[(int) $found->listing_id][] = (string) $found->tag_key;
            }
        }

        return new self($present, $withAny, true);
    }

    /**
     * One listing's Smart Tag facts for the scorer — or null when nothing was asked about, in
     * which case the scorer has no picks to compare and needs none.
     */
    public function factsFor(BridgeProperty $row): ?ListingSmartTagFacts
    {
        if (! $this->read) {
            return null;
        }

        $id = (int) ($row->id ?? 0);

        return new ListingSmartTagFacts(
            presentKeys:       $this->presentByBridgeId[$id] ?? [],
            hasAnyResolvedTag: isset($this->withAnyTag[$id]),
        );
    }
}
