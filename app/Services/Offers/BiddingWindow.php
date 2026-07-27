<?php

namespace App\Services\Offers;

use Carbon\CarbonImmutable;

/**
 * Immutable description of one listing's bidding window.
 *
 * Produced only by BiddingWindowService::for(). Nothing else should compute a
 * bidding deadline — the whole point of this object is that there is exactly one
 * answer to "when does bidding close?" shared by the timer, the server-side
 * enforcement guards, and the public feed.
 */
final class BiddingWindow
{
    public function __construct(
        public readonly bool $isBiddingPeriod,
        public readonly ?CarbonImmutable $startedAt,
        public readonly ?CarbonImmutable $endsAt,
        /**
         * True when endsAt was derived from the legacy fallback rather than from
         * a canonical bidding_started_at stamp. Surfaced so callers (and tests)
         * can tell a trustworthy deadline from a best-effort one.
         */
        public readonly bool $isLegacyFallback,
        public readonly string $legacyFallbackReason = '',
    ) {}

    public static function notBidding(): self
    {
        return new self(false, null, null, false);
    }

    /**
     * A window with no resolvable deadline is treated as OPEN, never closed.
     * Refusing bids because we could not compute a deadline would punish the
     * bidder for our own missing data.
     */
    public function isClosed(?CarbonImmutable $now = null): bool
    {
        if (! $this->isBiddingPeriod || $this->endsAt === null) {
            return false;
        }

        return ($now ?? CarbonImmutable::now())->greaterThanOrEqualTo($this->endsAt);
    }

    public function isOpen(?CarbonImmutable $now = null): bool
    {
        return $this->isBiddingPeriod && ! $this->isClosed($now);
    }

    public function hasDeadline(): bool
    {
        return $this->endsAt !== null;
    }

    /**
     * Seconds until close. Clamped at 0 — never negative, so a view can render
     * this straight into a countdown without re-checking the sign.
     */
    public function remainingSeconds(?CarbonImmutable $now = null): int
    {
        if ($this->endsAt === null) {
            return 0;
        }

        $now = $now ?? CarbonImmutable::now();

        return max(0, (int) $now->diffInSeconds($this->endsAt, false));
    }

    /**
     * Deadline rendered in the marketplace's display timezone.
     *
     * Storage stays UTC — this converts at the edge only, so nothing about the
     * app's global date handling changes.
     */
    public function endsAtForDisplay(): ?CarbonImmutable
    {
        return $this->endsAt?->setTimezone(BiddingWindowService::DISPLAY_TIMEZONE);
    }

    public function startedAtForDisplay(): ?CarbonImmutable
    {
        return $this->startedAt?->setTimezone(BiddingWindowService::DISPLAY_TIMEZONE);
    }
}
