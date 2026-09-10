<?php

namespace App\Services\Explore;

/**
 * What happened when Explore asked the provider about a viewport, and — the part
 * that matters — whether the answer is complete enough to withhold rows on.
 *
 * WHY COMPLETENESS IS A FIRST-CLASS FIELD
 * ---------------------------------------
 * Explore withholds a locally-stored row that the latest discovery pass did not
 * confirm: that is how a listing which went Pending, lost IDX participation or
 * left the feed disappears from the map without anything being deleted.
 *
 * That inference is only sound when the pass actually saw everything in the
 * viewport. If pagination hit a ceiling, an absent listing means "we stopped
 * asking", not "it is gone" — and withholding on that reading would hide real,
 * current inventory. So a partial pass reports `complete = false` and the
 * repository does not apply the rule.
 *
 * A FAILED PASS IS NOT AN EMPTY MARKET
 * ------------------------------------
 * When the provider cannot be reached, Explore serves the last known rows and
 * says so. Emptying the map would state that a neighbourhood has nothing for
 * sale, which is a claim about the world rather than about our connectivity;
 * serving stale rows silently would be the same lie in the other direction.
 * `degraded` is what the response carries so the surface can say which it is.
 */
final class ExploreDiscoveryOutcome
{
    public const STATUS_DISABLED    = 'disabled';
    public const STATUS_FETCHED     = 'fetched';
    public const STATUS_CACHED      = 'cached';
    public const STATUS_PARTIAL     = 'partial';
    public const STATUS_UNAVAILABLE = 'unavailable';

    private function __construct(
        public readonly string $status,
        public readonly bool $complete,
        public readonly bool $degraded,
        public readonly int $recordCount,
        public readonly int $providerRequests,
    ) {}

    /**
     * Discovery is switched off. Explore serves what the shared MLS cache
     * already holds — honestly labelled, because a cache-only answer is not a
     * statement about what exists in the market.
     */
    public static function disabled(): self
    {
        return new self(self::STATUS_DISABLED, complete: false, degraded: false, recordCount: 0, providerRequests: 0);
    }

    /** A full pass ran against the provider and consumed the whole result set. */
    public static function fetched(int $recordCount): self
    {
        return new self(self::STATUS_FETCHED, complete: true, degraded: false, recordCount: $recordCount, providerRequests: 1);
    }

    /**
     * The fetch cache for this tile is still warm, so no request was sent.
     *
     * Treated as complete: the warm entry was written by a pass that ran inside
     * the same window the row-confirmation rule uses. A pass that was PARTIAL
     * writes a much shorter TTL (`bridge.lazy_partial_ttl_minutes`, 5 minutes
     * against 60), so a stale-but-warm partial answer self-corrects within
     * minutes rather than persisting for the full hour.
     */
    public static function cached(int $recordCount): self
    {
        return new self(self::STATUS_CACHED, complete: true, degraded: false, recordCount: $recordCount, providerRequests: 0);
    }

    /** A pagination ceiling was reached. Absence proves nothing; withhold nothing. */
    public static function partial(int $recordCount): self
    {
        return new self(self::STATUS_PARTIAL, complete: false, degraded: false, recordCount: $recordCount, providerRequests: 1);
    }

    /** The provider could not be reached. Serve last-known, and say so. */
    public static function unavailable(): self
    {
        return new self(self::STATUS_UNAVAILABLE, complete: false, degraded: true, recordCount: 0, providerRequests: 1);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'status'   => $this->status,
            'complete' => $this->complete,
            'degraded' => $this->degraded,
        ];
    }
}
