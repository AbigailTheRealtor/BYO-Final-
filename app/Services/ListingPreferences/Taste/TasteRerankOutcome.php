<?php

namespace App\Services\ListingPreferences\Taste;

/**
 * What the results page needs to know after asking for a Taste rerank: the
 * cards, in their final order, and which of six states personalization is in.
 *
 *   inactive       a flag is off, or the viewer is not a Buyer/Tenant shopping
 *                  this side of the market — render exactly as before, say nothing
 *   explicit_sort  the viewer chose an order other than Best Match — untouched
 *   explicit_criteria  the searched criteria carry explicit Smart Tag picks, which
 *                  outrank learned taste and are not yet matched on — untouched
 *   opted_out      the viewer asked for standard Best Match — untouched, and the
 *                  page offers the way back
 *   no_signals     reranking was allowed but their taste has no pattern strong
 *                  enough to act on — untouched, nothing claimed
 *   personalized   ordered by Best Match refined with Your Home Taste
 *
 * The cards are the SAME cards: every state but `personalized` returns them in
 * the order they arrived, and `personalized` returns a permutation.
 */
final class TasteRerankOutcome
{
    public const INACTIVE      = 'inactive';
    public const EXPLICIT_SORT = 'explicit_sort';
    public const OPTED_OUT     = 'opted_out';
    public const EXPLICIT_CRITERIA = 'explicit_criteria';
    public const NO_SIGNALS    = 'no_signals';
    public const PERSONALIZED  = 'personalized';

    /**
     * @param list<array<string, mixed>> $cards
     */
    public function __construct(
        public readonly string $status,
        public readonly array $cards,
    ) {
    }

    public function isPersonalized(): bool
    {
        return $this->status === self::PERSONALIZED;
    }

    /** Whether the page should offer the personalization toggle at all. */
    public function offersToggle(): bool
    {
        return in_array($this->status, [self::PERSONALIZED, self::OPTED_OUT], true);
    }
}
