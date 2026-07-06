<?php

namespace App\Services\Stellar\MatchCheck;

use Carbon\CarbonInterface;

/**
 * Immutable, read-only snapshot of the throttle state LocationDnaEnrichmentGuard needs to
 * decide (Phase 4 · git-C12 / Plan-C8 · F6).
 *
 * A throttle inherently needs two pieces of state: when the listing was last enriched
 * (per-listing cooldown / dedupe) and how many enrichment attempts the user has made in the
 * current trailing hour (per-user rate limit). Reading that state from a cache / RateLimiter
 * store — and RECORDING a new attempt after an allow — are reads/writes against persistence,
 * which git-C12 deliberately excludes to stay pure and inert.
 *
 * So the guard does no I/O of its own: the CALLER supplies this snapshot and the guard is a
 * total function of it. git-C13 builds the production snapshot (from RateLimiter / Cache reads,
 * following the AskAiRateLimitService idiom) and performs the RateLimiter::hit() recording after
 * an allow() — those reads and writes are that slice's job, not this one.
 *
 * See docs/match-check-phase4-git-c12-scope.md §2.4.
 */
final class EnrichmentThrottleSnapshot
{
    /**
     * @param  CarbonInterface|null  $listingLastEnrichedAt  When Location DNA was last computed for
     *                                                        this listing; null = never enriched.
     * @param  int  $userAttemptsInWindow  Enrichment attempts by this user in the trailing hour.
     * @param  CarbonInterface|null  $userWindowResetsAt  When the user's oldest in-window attempt ages
     *                                                     out (drives RATE_LIMITED retry-after); null =
     *                                                     no reset time known / not rate-limited.
     */
    public function __construct(
        public readonly ?CarbonInterface $listingLastEnrichedAt,
        public readonly int $userAttemptsInWindow,
        public readonly ?CarbonInterface $userWindowResetsAt = null,
    ) {
    }
}
