<?php

namespace App\Services\Stellar\MatchCheck;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Production throttle store for Match Check-initiated Location DNA enrichment
 * (Phase 4 · git-C13a / Plan-C9 · F6).
 *
 * LocationDnaEnrichmentGuard::decide() (git-C12) is a pure function of an injected
 * EnrichmentThrottleSnapshot; git-C12 deliberately left the state I/O — reading that snapshot and
 * recording an attempt after an allow — to this slice. This class is that I/O, following the
 * AskAiRateLimitService idiom (RateLimiter for the per-user hourly cap; a Cache marker for the
 * per-listing cooldown):
 *
 *   - snapshot()      READ  — build the snapshot the guard needs (never mutates state).
 *   - recordAttempt() WRITE — after an allowed dispatch, bump the user's hourly counter and set the
 *                             per-listing cooldown marker so the next request is deduped.
 *
 * COOLDOWN SEMANTICS (owner decision 3): a dedicated per-listing marker we set on OUR dispatch —
 * "did *we* enrich this recently" — NOT the listing's Location-DNA computed-at timestamp. This
 * throttles our dispatch cadence independently of data freshness and mirrors AskAiRateLimitService.
 *
 * INERT / UNWIRED beyond Match Check. Only MatchCheckOrchestrator's analyze*() path touches this,
 * and that path short-circuits before any store access while the master flag is OFF (its default).
 */
class EnrichmentThrottleStore
{
    /** Cache key prefix for the per-listing cooldown marker (stores the dispatch unix timestamp). */
    private const COOLDOWN_KEY_PREFIX = 'mc_dna_cooldown';

    /** RateLimiter key prefix for the per-user trailing-hour enrichment counter. */
    private const RATE_KEY_PREFIX = 'mc_dna_rate';

    /** Rate-limiter decay window in seconds — the per-user cap is hourly (mirrors AskAiRateLimitService). */
    private const RATE_DECAY_SECONDS = 3600;

    /**
     * Read the current throttle state for a (listing, user) into the snapshot the guard consumes.
     * Read-only: no RateLimiter::hit(), no Cache::put().
     */
    public function snapshot(string $listingType, int $listingId, int $userId): EnrichmentThrottleSnapshot
    {
        $marker = Cache::get($this->cooldownKey($listingType, $listingId));
        $listingLastEnrichedAt = $marker !== null
            ? CarbonImmutable::createFromTimestamp((int) $marker)
            : null;

        $rateKey  = $this->rateKey($userId);
        $attempts = RateLimiter::attempts($rateKey);

        // Only meaningful (and only queryable) once the user has at least one in-window attempt.
        $userWindowResetsAt = $attempts > 0
            ? CarbonImmutable::now()->addSeconds(RateLimiter::availableIn($rateKey))
            : null;

        return new EnrichmentThrottleSnapshot(
            listingLastEnrichedAt: $listingLastEnrichedAt,
            userAttemptsInWindow: $attempts,
            userWindowResetsAt: $userWindowResetsAt,
        );
    }

    /**
     * Record one allowed enrichment attempt: bump the per-user hourly counter and (re)set the
     * per-listing cooldown marker to now, with a TTL of the configured cooldown window. Call this
     * ONLY after the guard allows and the dispatch is made.
     */
    public function recordAttempt(string $listingType, int $listingId, int $userId): void
    {
        RateLimiter::hit($this->rateKey($userId), self::RATE_DECAY_SECONDS);

        $cooldownHours = (int) config('mls_match_check.dna_cooldown_hours', 24);
        if ($cooldownHours > 0) {
            Cache::put(
                $this->cooldownKey($listingType, $listingId),
                CarbonImmutable::now()->getTimestamp(),
                $cooldownHours * 3600,
            );
        }
    }

    private function cooldownKey(string $listingType, int $listingId): string
    {
        return self::COOLDOWN_KEY_PREFIX . ":{$listingType}:{$listingId}";
    }

    private function rateKey(int $userId): string
    {
        return self::RATE_KEY_PREFIX . ":{$userId}";
    }
}
