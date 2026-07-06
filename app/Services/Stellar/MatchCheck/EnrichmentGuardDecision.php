<?php

namespace App\Services\Stellar\MatchCheck;

/**
 * Immutable result of LocationDnaEnrichmentGuard::decide() (Phase 4 · git-C12 / Plan-C8 · F6).
 *
 * Carries a machine-readable $reason so the eventual dispatcher (git-C13) and the consumer
 * surface (git-C14) can branch on *why* enrichment is (dis)allowed — serve cached vs. show
 * "try again later" vs. silently no-op — without re-deriving the throttle check.
 *
 * $retryAfterSeconds is the soonest, in whole seconds, that the same request could be allowed:
 *   - COOLDOWN_ACTIVE → seconds until the per-listing cooldown expires
 *   - RATE_LIMITED    → seconds until the per-user hourly window frees a slot
 *   - ALLOWED / FEATURE_DISABLED → null (no meaningful retry time)
 *
 * See docs/match-check-phase4-git-c12-scope.md.
 */
final class EnrichmentGuardDecision
{
    public const REASON_ALLOWED          = 'allowed';
    public const REASON_FEATURE_DISABLED = 'feature_disabled';
    public const REASON_COOLDOWN_ACTIVE  = 'cooldown_active';   // per-listing dedupe
    public const REASON_RATE_LIMITED     = 'rate_limited';      // per-user hourly cap

    public function __construct(
        public readonly bool $allowed,
        public readonly string $reason,
        public readonly ?int $retryAfterSeconds,
    ) {
    }

    public static function allow(): self
    {
        return new self(true, self::REASON_ALLOWED, null);
    }

    public static function deny(string $reason, ?int $retryAfterSeconds = null): self
    {
        return new self(false, $reason, $retryAfterSeconds);
    }
}
