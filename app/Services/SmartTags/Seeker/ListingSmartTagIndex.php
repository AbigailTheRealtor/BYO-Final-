<?php

namespace App\Services\SmartTags\Seeker;

use App\Models\BridgeProperty;
use App\Models\SmartTagAssignment;
use App\Support\SmartTags\SmartTagListingType;
use App\Support\SmartTags\SmartTagState;

/**
 * The resolved PRESENT Smart Tags of a set of candidate listings, read in ONE
 * query, for the matcher to look up per listing without querying again.
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
     */
    private function __construct(
        private readonly array $presentByBridgeId,
        private readonly array $withAnyTag,
    ) {
    }

    public static function empty(): self
    {
        return new self([], []);
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

        return new self($present, $withAny);
    }

    /**
     * The listing's present keys among those requested — possibly none — or null
     * when the listing has no resolved present tag at all. Both earn no credit
     * for the keys they lack; null additionally means "we could not check".
     *
     * @return list<string>|null
     */
    public function presentKeysFor(BridgeProperty $row): ?array
    {
        $id = (int) ($row->id ?? 0);

        if (! isset($this->withAnyTag[$id])) {
            return null;
        }

        return $this->presentByBridgeId[$id] ?? [];
    }
}
