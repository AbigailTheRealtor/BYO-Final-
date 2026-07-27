<?php

namespace App\Services\Offers;

use Carbon\CarbonImmutable;

/**
 * Immutable description of one listing's bidding window.
 *
 * Produced only by BiddingWindowService. Nothing else may compute a bidding
 * deadline — the whole point of this object is that there is exactly one answer
 * to "when does bidding close?", shared by the timer, the server-side
 * enforcement guards, the presenters and the public feed.
 *
 * The deadline carried here is READ FROM STORAGE. It is never derived, never
 * recomputed from auction_time, and never influenced by the listing's
 * expiration_date. See Owner-Approved Decision A.
 *
 * THREE STATES, and only three:
 *
 *   notBidding()   The listing is not a Bidding Period listing at all.
 *   uninitialized  A Bidding Period listing whose canonical window was never
 *                  stamped. No countdown renders and no bidder is blocked.
 *   canonical      Both timestamps present, read straight from offer_auctions.
 */
final class BiddingWindow
{
    public function __construct(
        public readonly bool $isBiddingPeriod,
        public readonly ?CarbonImmutable $startsAt,
        public readonly ?CarbonImmutable $endsAt,
    ) {}

    public static function notBidding(): self
    {
        return new self(false, null, null);
    }

    /**
     * A Bidding Period listing with no stored window.
     *
     * Deliberately distinct from notBidding(): the listing IS a timed listing,
     * we simply have no trustworthy window for it. Callers that need to explain
     * a missing countdown to a user can tell the two apart.
     */
    public static function uninitialized(): self
    {
        return new self(true, null, null);
    }

    /**
     * True only when both canonical timestamps are present.
     *
     * A half-stamped row (start without end) is NOT canonical. Treating it as
     * canonical would mean inventing the missing half at read time, which is
     * exactly what Decision A forbids.
     */
    public function isCanonical(): bool
    {
        return $this->isBiddingPeriod
            && $this->startsAt !== null
            && $this->endsAt !== null;
    }

    public function isUninitialized(): bool
    {
        return $this->isBiddingPeriod && ! $this->isCanonical();
    }

    public function hasDeadline(): bool
    {
        return $this->isCanonical();
    }

    /**
     * A window with no canonical deadline is treated as OPEN, never closed.
     * Refusing bids because we never stamped a window would punish the bidder
     * for our own missing data.
     */
    public function isClosed(?CarbonImmutable $now = null): bool
    {
        if (! $this->isCanonical()) {
            return false;
        }

        return ($now ?? CarbonImmutable::now())->greaterThanOrEqualTo($this->endsAt);
    }

    public function isOpen(?CarbonImmutable $now = null): bool
    {
        return $this->isBiddingPeriod && ! $this->isClosed($now);
    }

    /**
     * Seconds until close. Clamped at 0 — never negative, so a view can render
     * this straight into a countdown without re-checking the sign.
     */
    public function remainingSeconds(?CarbonImmutable $now = null): int
    {
        if (! $this->isCanonical()) {
            return 0;
        }

        return max(0, (int) ($now ?? CarbonImmutable::now())->diffInSeconds($this->endsAt, false));
    }

    /**
     * Deadline rendered in the marketplace's display timezone.
     *
     * Storage stays UTC — this converts at the edge only, so nothing about the
     * app's global date handling changes.
     */
    public function endsAtForDisplay(): ?CarbonImmutable
    {
        return $this->isCanonical()
            ? $this->endsAt->setTimezone(BiddingWindowService::DISPLAY_TIMEZONE)
            : null;
    }

    public function startsAtForDisplay(): ?CarbonImmutable
    {
        return $this->isCanonical()
            ? $this->startsAt->setTimezone(BiddingWindowService::DISPLAY_TIMEZONE)
            : null;
    }

    /**
     * Timezone abbreviation for the rendered deadline (EST / EDT).
     *
     * Requirement 8 and 9 of the approved architecture both call for the exact
     * deadline AND its timezone to be shown; a bare local time is ambiguous
     * twice a year.
     */
    public function displayTimezoneAbbreviation(): ?string
    {
        return $this->endsAtForDisplay()?->format('T');
    }
}
