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
    public const STATUS_BUDGET_LIMITED = 'budget_limited';

    private function __construct(
        public readonly string $status,
        public readonly bool $complete,
        public readonly bool $degraded,
        public readonly int $recordCount,
        public readonly int $providerRequests,
        public readonly ?string $reason = null,
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
     * minutes rather than persisting for the full hour. A pass stopped by the
     * budget writes no cache entry at all.
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

    /**
     * A budget ceiling refused a provider request — before anything was sent,
     * or part-way through a pass.
     *
     * DEGRADED, NEVER EMPTY. This is the state the whole guard exists to make
     * safe: budget exhaustion must look like "we cannot refresh this area right
     * now", never like "there are no homes here". So it reports `complete =
     * false`, which suppresses the withhold-unconfirmed-rows rule and leaves
     * last-known inventory on the map, and `degraded = true`, so the surface
     * says so out loud rather than presenting a thin answer as a full one.
     *
     * Refused part-way, the pages already admitted were sent and their rows
     * kept — `$recordCount` and `$providerRequests` report them — and the
     * refused page was never sent. Refused up front, both are zero, which is
     * what distinguishes this from {@see unavailable()}: there the provider was
     * contacted and did not answer.
     *
     * The reason travels for telemetry only. It names which ceiling stopped the
     * call — global or actor, hourly or daily, or the kill switch — because
     * "we stopped calling Bridge today" and "one visitor hit their hourly
     * ceiling" are entirely different operational problems.
     */
    public static function budgetLimited(string $reason, int $recordCount = 0, int $providerRequests = 0): self
    {
        return new self(
            self::STATUS_BUDGET_LIMITED,
            complete: false,
            degraded: true,
            recordCount: $recordCount,
            providerRequests: $providerRequests,
            reason: $reason,
        );
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
